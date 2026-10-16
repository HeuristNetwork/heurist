<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/**
 * Exhaustive-manifest synchronisation for terms and uploaded files.
 *
 * Design copied from the approved specification:
 * - identity is always origin database ID + ID in the origin database;
 * - every run compares complete manifests, so no last-sync timestamps, change
 *   journals or permanent session progress are needed;
 * - Master content is authoritative once an object exists on Master;
 * - Satellite may contribute only new terms and new file concepts;
 * - operations are idempotent, and incomplete placeholder rows advertise what
 *   remains to be transferred after interruption;
 * - no term, file registration or physical file is deleted.
 */
final class ManifestSyncService
{
    private \hserv\System $system;
    private \mysqli $mysqli;
    private int $databaseID;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        $this->databaseID = (int)$system->settings->get('sys_dbRegisteredID');
        ConceptCode::setSystem($system);
    }

    /**
     * Populate permanent identities and MD5 checksums before manifest exchange.
     * MD5 is fundamental to the existing Heurist file table and is retained.
     * Missing physical files are reported but never represented as complete.
     */
    public function checkIntegrity(): array
    {
        if ($this->databaseID < 1) throw new \RuntimeException('Synchronisation requires a registered database.');
        $this->mysqli->begin_transaction();
        try {
            $this->mysqli->query('UPDATE defTerms SET trm_OriginatingDBID='.$this->databaseID
                .',trm_IDInOriginatingDB=trm_ID,trm_NameInOriginatingDB=trm_Label '
                .'WHERE COALESCE(trm_OriginatingDBID,0)=0 OR COALESCE(trm_IDInOriginatingDB,0)=0');
            $this->mysqli->query('UPDATE recUploadedFiles SET ulf_OriginatingDBID='.$this->databaseID
                .',ulf_IDInOriginatingDB=ulf_ID WHERE COALESCE(ulf_OriginatingDBID,0)=0 '
                .'OR COALESCE(ulf_IDInOriginatingDB,0)=0');
            $this->mysqli->query('UPDATE Records SET rec_OriginatingDBID='.$this->databaseID
                .',rec_IDInOriginatingDB=rec_ID WHERE COALESCE(rec_OriginatingDBID,0)=0 '
                .'OR COALESCE(rec_IDInOriginatingDB,0)=0');
            $duplicates = mysql__select_assoc($this->mysqli,
                'SELECT CONCAT(ulf_OriginatingDBID,"-",ulf_IDInOriginatingDB) concept,COUNT(*) n '
                .'FROM recUploadedFiles GROUP BY ulf_OriginatingDBID,ulf_IDInOriginatingDB HAVING n>1') ?: [];
            if ($duplicates) throw new \RuntimeException('Duplicate uploaded-file concepts exist: '.implode(', ', array_keys($duplicates)).'.');
            $this->mysqli->commit();
        } catch (\Throwable $e) {
            $this->mysqli->rollback();
            throw $e;
        }

        $checked = 0; $calculated = 0; $missing = [];
        $result = $this->mysqli->query('SELECT ulf_ID,ulf_OrigFileName,ulf_FilePath,ulf_FileName,'
            .'ulf_ExternalFileReference,ulf_MD5Checksum FROM recUploadedFiles ORDER BY ulf_ID');
        while ($result && ($row = $result->fetch_assoc())) {
            $checked++;
            $hasExternal = trim((string)$row['ulf_ExternalFileReference']) !== '';
            $path = $this->localFilePath($row);
            if ($path !== null && is_file($path)) {
                $md5 = md5_file($path);
                if ($md5 === false) { $missing[] = (int)$row['ulf_ID'].': unreadable file'; continue; }
                if (!hash_equals(strtolower((string)$row['ulf_MD5Checksum']), strtolower($md5))) {
                    $stmt = $this->mysqli->prepare('UPDATE recUploadedFiles SET ulf_MD5Checksum=?,ulf_SyncState=IF(ulf_SyncState="local","local","complete") WHERE ulf_ID=?');
                    $id = (int)$row['ulf_ID']; $stmt->bind_param('si', $md5, $id); $stmt->execute(); $stmt->close();
                    $calculated++;
                }
            } elseif (!$hasExternal) {
                $missing[] = (int)$row['ulf_ID'].': '.(string)$row['ulf_OrigFileName'];
                $this->mysqli->query('UPDATE recUploadedFiles SET ulf_SyncState="pending_content" WHERE ulf_ID='.(int)$row['ulf_ID']);
            }
        }
        if ($result) $result->close();
        return ['checked' => $checked, 'checksumsCalculated' => $calculated, 'missingPhysicalFiles' => $missing];
    }

    /** Satellite offers only terms created on that Satellite. */
    public function satelliteTermManifest(): array
    {
        return $this->termManifest('WHERE trm_OriginatingDBID='.$this->databaseID);
    }

    /** Master offers every term because it is authoritative for Satellite labels. */
    public function masterTermManifest(): array
    {
        return $this->termManifest('');
    }

    private function termManifest(string $where): array
    {
        $rows = [];
        $sql = 'SELECT trm_ID,trm_Label,trm_OriginatingDBID,trm_IDInOriginatingDB,trm_ParentTermID '
            .'FROM defTerms '.$where.' ORDER BY trm_Depth,trm_ID';
        $result = $this->mysqli->query($sql);
        while ($result && ($row = $result->fetch_assoc())) {
            $parentConcept = null;
            if ((int)$row['trm_ParentTermID'] > 0) $parentConcept = ConceptCode::getTermConceptID((int)$row['trm_ParentTermID']);
            $rows[] = [
                'conceptID' => (int)$row['trm_OriginatingDBID'].'-'.(int)$row['trm_IDInOriginatingDB'],
                'localID' => (int)$row['trm_ID'],
                'label' => (string)$row['trm_Label'],
                'parentConceptID' => $parentConcept
            ];
        }
        if ($result) $result->close();
        return $rows;
    }

    /** Return concepts from a Satellite manifest which do not yet exist on Master. */
    public function missingTerms(array $manifest, int $satelliteID): array
    {
        $missing = [];
        foreach ($manifest as $term) {
            $concept = (string)($term['conceptID'] ?? '');
            [$origin, $originID] = $this->splitConcept($concept);
            if ($origin !== $satelliteID || $originID < 1) throw new \RuntimeException("Invalid Satellite term concept $concept.");
            if ((int)ConceptCode::getTermLocalID($concept) < 1) $missing[] = $concept;
        }
        return $missing;
    }

    /**
     * Insert only missing Satellite terms. Existing Master terms are ignored:
     * the Master is authoritative and Satellite content may never overwrite it.
     */
    public function acceptSatelliteTerms(array $terms, int $satelliteID): int
    {
        $inserted = 0; $pending = array_values($terms);
        while ($pending) {
            $progress = false;
            foreach ($pending as $index => $term) {
                $concept = (string)($term['conceptID'] ?? '');
                [$origin, $originID] = $this->splitConcept($concept);
                if ($origin !== $satelliteID || $originID < 1) throw new \RuntimeException("Invalid Satellite term concept $concept.");
                if ((int)ConceptCode::getTermLocalID($concept) > 0) { unset($pending[$index]); $progress = true; continue; }
                $parentConcept = (string)($term['parentConceptID'] ?? '');
                $parentID = (int)ConceptCode::getTermLocalID($parentConcept);
                if ($parentID < 1) continue;
                $parent = mysql__select_row($this->mysqli,
                    'SELECT trm_Domain,trm_Depth,trm_VocabularyGroupID FROM defTerms WHERE trm_ID=?', ['i',$parentID]);
                if (!$parent) continue;
                $label = trim((string)($term['label'] ?? ''));
                if ($label === '') throw new \RuntimeException("Satellite term $concept has no label.");
                $stmt = $this->mysqli->prepare('INSERT INTO defTerms '
                    .'(trm_Label,trm_OriginatingDBID,trm_NameInOriginatingDB,trm_IDInOriginatingDB,trm_Domain,'
                    .'trm_ParentTermID,trm_Depth,trm_VocabularyGroupID,trm_Status,trm_IsLocalExtension) '
                    .'VALUES (?,?,?,?,?,?,?,?,"open",1)');
                $depth = (int)$parent[1] + 1; $group = (int)$parent[2]; $domain = (string)$parent[0];
                $stmt->bind_param('sisisiii', $label, $origin, $label, $originID, $domain, $parentID, $depth, $group);
                if (!$stmt->execute()) throw new \RuntimeException('Unable to insert Satellite term: '.$stmt->error);
                $stmt->close(); unset($pending[$index]); $inserted++; $progress = true;
            }
            if (!$progress) throw new \RuntimeException('Satellite terms refer to parent concepts missing from Master: '
                .implode(', ', array_map(static fn($t)=>(string)($t['conceptID']??'?'), $pending)).'.');
            $pending = array_values($pending);
        }
        return $inserted;
    }

    /** Master payload for requested term concepts: hierarchy and label only. */
    public function requestedTerms(array $concepts): array
    {
        $wanted = array_fill_keys(array_map('strval', $concepts), true);
        return array_values(array_filter($this->masterTermManifest(),
            static fn($row) => isset($wanted[$row['conceptID']])));
    }

    /** Add missing Master terms and enforce Master label/hierarchical position. */
    public function applyMasterTerms(array $terms): array
    {
        $inserted = 0; $updated = 0; $pending = array_values($terms);
        while ($pending) {
            $progress = false;
            foreach ($pending as $index => $term) {
                $concept = (string)($term['conceptID'] ?? '');
                [$origin, $originID] = $this->splitConcept($concept);
                $parentConcept = (string)($term['parentConceptID'] ?? '');
                $parentID = $parentConcept === '' ? 0 : (int)ConceptCode::getTermLocalID($parentConcept);
                if ($parentConcept !== '' && $parentID < 1) continue;
                $localID = (int)ConceptCode::getTermLocalID($concept);
                $label = trim((string)($term['label'] ?? ''));
                if ($label === '' || $origin < 1 || $originID < 1) throw new \RuntimeException("Incomplete Master term $concept.");
                if ($localID > 0) {
                    $stmt = $this->mysqli->prepare('UPDATE defTerms SET trm_Label=?,trm_ParentTermID=? WHERE trm_ID=?');
                    $parentValue = $parentID ?: null; $stmt->bind_param('sii', $label, $parentValue, $localID);
                    if (!$stmt->execute()) throw new \RuntimeException('Unable to update Master term locally: '.$stmt->error);
                    if ($stmt->affected_rows) $updated++; $stmt->close();
                } else {
                    if($parentConcept==='') throw new \RuntimeException("Master term $concept is a vocabulary or top-level structure item missing from the Satellite. Run database structure synchronisation from the Master configuration form.");
                    $domain='enum'; $depth=0; $group=0;
                    if ($parentID > 0) {
                        $parent=mysql__select_row($this->mysqli,'SELECT trm_Domain,trm_Depth,trm_VocabularyGroupID FROM defTerms WHERE trm_ID=?',['i',$parentID]);
                        $domain=(string)$parent[0]; $depth=(int)$parent[1]+1; $group=(int)$parent[2];
                    }
                    $stmt=$this->mysqli->prepare('INSERT INTO defTerms '
                        .'(trm_Label,trm_OriginatingDBID,trm_NameInOriginatingDB,trm_IDInOriginatingDB,trm_Domain,'
                        .'trm_ParentTermID,trm_Depth,trm_VocabularyGroupID,trm_Status) VALUES (?,?,?,?,?,?,?,?,"open")');
                    $parentValue=$parentID?:null;
                    $stmt->bind_param('sisisiii',$label,$origin,$label,$originID,$domain,$parentValue,$depth,$group);
                    if(!$stmt->execute()) throw new \RuntimeException('Unable to add Master term locally: '.$stmt->error);
                    $stmt->close(); $inserted++;
                }
                unset($pending[$index]); $progress=true;
            }
            if(!$progress) throw new \RuntimeException('Master term hierarchy cannot be resolved on Satellite. Run database structure synchronisation from the Master configuration form.');
            $pending=array_values($pending);
        }
        return ['inserted'=>$inserted,'updated'=>$updated];
    }

    /** Complete file manifest. Origin pair is identity; MD5 diagnoses differing content. */
    public function fileManifest(): array
    {
        $rows=[];
        $result=$this->mysqli->query('SELECT ulf_ID,ulf_OriginatingDBID,ulf_IDInOriginatingDB,ulf_MD5Checksum,ulf_SyncState FROM recUploadedFiles ORDER BY ulf_ID');
        while($result && ($row=$result->fetch_assoc())) $rows[]=[
            'conceptID'=>(int)$row['ulf_OriginatingDBID'].'-'.(int)$row['ulf_IDInOriginatingDB'],
            'localID'=>(int)$row['ulf_ID'],
            'md5'=>strtolower((string)$row['ulf_MD5Checksum']),
            'state'=>(string)$row['ulf_SyncState']
        ];
        if($result)$result->close();
        return $rows;
    }

    /**
     * Master allocates ulf_ID placeholders for unknown concepts. The returned
     * mapping is authoritative and the Satellite remaps its ulf_ID plus every
     * recDetails file pointer transactionally before file content transfer.
     */
    public function allocateMasterFiles(array $manifest): array
    {
        $mapping=[]; $requested=[]; $warnings=[];
        $transaction=new SyncTransaction($this->mysqli,true);
        try {
            foreach($manifest as $file){
                $concept=(string)($file['conceptID']??''); [$origin,$originID]=$this->splitConcept($concept);
                if($origin<1||$originID<1) throw new \RuntimeException("Invalid file concept $concept.");
                $row=mysql__select_row($this->mysqli,
                    'SELECT ulf_ID,ulf_MD5Checksum,ulf_SyncState FROM recUploadedFiles WHERE ulf_OriginatingDBID=? AND ulf_IDInOriginatingDB=?',
                    ['ii',$origin,$originID]);
                if(!$row){
                    // Initial legacy-clone rule from the specification: equal
                    // ulf_ID + equal MD5 is the same pre-existing file, despite
                    // the new origin columns not existing when it was copied.
                    $legacy=mysql__select_row($this->mysqli,
                        'SELECT ulf_ID,ulf_OriginatingDBID,ulf_IDInOriginatingDB,ulf_MD5Checksum FROM recUploadedFiles WHERE ulf_ID=?',
                        ['i',(int)($file['localID']??0)]);
                    $incomingMD5=strtolower((string)($file['md5']??''));
                    $legacyMD5=strtolower((string)($legacy[3]??''));
                    if($legacy&&$incomingMD5!==''&&$legacyMD5!==''&&hash_equals($legacyMD5,$incomingMD5)){
                        $masterID=(int)$legacy[0];$masterConcept=(int)$legacy[1].'-'.(int)$legacy[2];
                    }else{
                        // Equal numeric IDs but differing MD5 values are not
                        // merged: concept identity says they may be distinct.
                        // The collision is non-blocking and remains visible.
                        if($legacy)$warnings[]="File $concept has Satellite ID ".(int)$legacy[0].
                            ' already used on Master with a different MD5; a separate Master row was allocated.';
                        $name='Pending synchronised file '.$concept;
                        $stmt=$this->mysqli->prepare('INSERT INTO recUploadedFiles '
                            .'(ulf_OrigFileName,ulf_Added,ulf_OriginatingDBID,ulf_IDInOriginatingDB,ulf_SyncState) '
                            .'VALUES (?,NOW(),?,?,"pending_metadata")');
                        $stmt->bind_param('sii',$name,$origin,$originID);
                        if(!$stmt->execute()) throw new \RuntimeException('Unable to allocate Master file ID: '.$stmt->error);
                        $masterID=(int)$stmt->insert_id;$masterConcept=$concept;$stmt->close();$requested[]=$concept;
                    }
                }else{
                    $masterID=(int)$row[0];
                    $masterConcept=$concept;
                    if(in_array((string)$row[2],['pending_metadata','pending_content'],true)) $requested[]=$concept;
                    $incomingMD5=strtolower((string)($file['md5']??'')); $masterMD5=strtolower((string)$row[1]);
                    if($incomingMD5!==''&&$masterMD5!==''&&!hash_equals($masterMD5,$incomingMD5))
                        $warnings[]="File $concept has different MD5 checksums; Master content will be retained and sent back to Satellite.";
                }
                $mapping[]=['conceptID'=>$concept,'masterConceptID'=>$masterConcept,
                    'satelliteFileID'=>(int)($file['localID']??0),'masterFileID'=>$masterID];
            }
            $ok=$transaction->finish(true);
        } finally {$transaction->close();}
        if(!$ok) throw new \RuntimeException('Master file allocation failed and was rolled back.');
        return ['mapping'=>$mapping,'requestedConcepts'=>array_values(array_unique($requested)),'warnings'=>$warnings];
    }

    /** Return complete metadata and, for local files, verified bytes. */
    public function filePayload(array $concepts): array
    {
        $payload=[];
        foreach(array_map('strval',$concepts) as $concept){
            [$origin,$originID]=$this->splitConcept($concept);
            $result=mysql__select_param_query($this->mysqli,
                'SELECT * FROM recUploadedFiles WHERE ulf_OriginatingDBID=? AND ulf_IDInOriginatingDB=?',['ii',$origin,$originID]);
            $row=$result?$result->fetch_assoc():null; if($result)$result->close();
            if(!$row) throw new \RuntimeException("Requested file $concept does not exist.");
            $path=$this->localFilePath($row); $content=null;
            if($path!==null && is_file($path)){
                $bytes=file_get_contents($path); if($bytes===false) throw new \RuntimeException("Unable to read file $concept.");
                $md5=md5($bytes); $row['ulf_MD5Checksum']=$md5; $content=base64_encode($bytes);
            }elseif(trim((string)$row['ulf_ExternalFileReference'])===''){
                throw new \RuntimeException("File $concept has neither local content nor an external reference.");
            }
            unset($row['ulf_Thumbnail'],$row['ulf_FilePath'],$row['ulf_FileName']);
            $payload[]=['conceptID'=>$concept,'metadata'=>$row,'contentBase64'=>$content];
        }
        return $payload;
    }

    /**
     * Apply file payloads. If $masterAuthoritative is false, an already complete
     * Master row is never overwritten by Satellite. If true, Master metadata and
     * replacement content overwrite the Satellite copy under the same concept.
     */
    public function applyFiles(array $payloads, bool $masterAuthoritative): array
    {
        $completed=0; $warnings=[];
        foreach($payloads as $payload){
            $concept=(string)($payload['conceptID']??''); [$origin,$originID]=$this->splitConcept($concept);
            $metadata=is_array($payload['metadata']??null)?$payload['metadata']:[];
            $row=mysql__select_row($this->mysqli,
                'SELECT ulf_ID,ulf_MD5Checksum,ulf_SyncState FROM recUploadedFiles WHERE ulf_OriginatingDBID=? AND ulf_IDInOriginatingDB=?',
                ['ii',$origin,$originID]);
            if(!$row) throw new \RuntimeException("No allocated row exists for file $concept.");
            $localID=(int)$row[0];
            if(!$masterAuthoritative && (string)$row[2]==='complete'){
                $incoming=strtolower((string)($metadata['ulf_MD5Checksum']??'')); $existing=strtolower((string)$row[1]);
                if($incoming!==''&&$existing!==''&&!hash_equals($incoming,$existing)) $warnings[]="File $concept differs; Master copy retained.";
                continue;
            }
            $content=$payload['contentBase64']??null; $storedName=null; $storedPath=null;
            if(is_string($content)){
                $bytes=base64_decode($content,true); if($bytes===false) throw new \RuntimeException("Invalid file data for $concept.");
                $expected=strtolower((string)($metadata['ulf_MD5Checksum']??'')); $actual=md5($bytes);
                if($expected===''||!hash_equals($expected,$actual)) throw new \RuntimeException("MD5 verification failed for $concept.");
                $dir=$this->system->getSysDir(DIR_FILEUPLOADS);
                $extension=preg_replace('/[^A-Za-z0-9]/','',(string)($metadata['ulf_MimeExt']??''));
                $storedName=SyncFileStorage::store($dir,'sync_'.$origin.'_'.$originID,$extension,$bytes);
                $storedPath=DIR_FILEUPLOADS;
            }elseif(trim((string)($metadata['ulf_ExternalFileReference']??''))===''){
                throw new \RuntimeException("File $concept has no transferable content or external reference.");
            }
            $sql='UPDATE recUploadedFiles SET ulf_OrigFileName=?,ulf_ExternalFileReference=?,ulf_PreferredSource=?,'
                .'ulf_Caption=?,ulf_Description=?,ulf_Copyright=?,ulf_Copyowner=?,ulf_MimeExt=?,ulf_FileSizeKB=?,'
                .'ulf_FilePath=?,ulf_FileName=?,ulf_Parameters=?,ulf_WhoCanView=?,ulf_MD5Checksum=?,ulf_SyncState="complete" WHERE ulf_ID=?';
            $stmt=$this->mysqli->prepare($sql);
            $original=basename((string)($metadata['ulf_OrigFileName']??'synchronised-file'));
            $external=$metadata['ulf_ExternalFileReference']??null; $preferred=(string)($metadata['ulf_PreferredSource']??'local');
            $caption=$metadata['ulf_Caption']??null; $description=$metadata['ulf_Description']??null;
            $copyright=$metadata['ulf_Copyright']??null; $owner=$metadata['ulf_Copyowner']??null;
            $mime=$metadata['ulf_MimeExt']??null; $size=isset($metadata['ulf_FileSizeKB'])?(int)$metadata['ulf_FileSizeKB']:null;
            $params=$metadata['ulf_Parameters']??null; $view=$metadata['ulf_WhoCanView']??null; $md5=$metadata['ulf_MD5Checksum']??null;
            $stmt->bind_param('ssssssssisssssi',$original,$external,$preferred,$caption,$description,$copyright,$owner,$mime,$size,
                $storedPath,$storedName,$params,$view,$md5,$localID);
            if(!$stmt->execute()) throw new \RuntimeException("Unable to complete file $concept: ".$stmt->error);
            $stmt->close(); $completed++;
        }
        return ['completed'=>$completed,'warnings'=>$warnings];
    }

    /** Satellite creates missing Master rows at their Master ulf_ID before requesting payloads. */
    public function prepareMasterFiles(array $masterManifest): array
    {
        $requested=[]; $warnings=[];
        foreach($masterManifest as $file){
            $concept=(string)($file['conceptID']??''); [$origin,$originID]=$this->splitConcept($concept);
            $masterID=(int)($file['localID']??0);
            $row=mysql__select_row($this->mysqli,
                'SELECT ulf_ID,ulf_MD5Checksum,ulf_SyncState FROM recUploadedFiles WHERE ulf_OriginatingDBID=? AND ulf_IDInOriginatingDB=?',
                ['ii',$origin,$originID]);
            if(!$row){
                if(mysql__select_value($this->mysqli,'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ID=?',['i',$masterID]))
                    throw new \RuntimeException("Master file ID $masterID is occupied by a different concept on Satellite.");
                $name='Pending Master file '.$concept;
                $stmt=$this->mysqli->prepare('INSERT INTO recUploadedFiles '
                    .'(ulf_ID,ulf_OrigFileName,ulf_Added,ulf_OriginatingDBID,ulf_IDInOriginatingDB,ulf_SyncState) '
                    .'VALUES (?,?,NOW(),?,?,"pending_metadata")');
                $stmt->bind_param('isii',$masterID,$name,$origin,$originID);
                if(!$stmt->execute()) throw new \RuntimeException('Unable to allocate Satellite file row: '.$stmt->error);
                $stmt->close(); $requested[]=$concept; continue;
            }
            if((int)$row[0]!==$masterID) throw new \RuntimeException("File $concept is not using Master file ID $masterID on Satellite.");
            $masterMD5=strtolower((string)($file['md5']??'')); $localMD5=strtolower((string)$row[1]);
            if(in_array((string)$row[2],['local','pending_metadata','pending_content'],true)
                ||($masterMD5!==''&&!hash_equals($masterMD5,$localMD5))){
                if($masterMD5!==''&&$localMD5!==''&&!hash_equals($masterMD5,$localMD5))
                    $warnings[]="File $concept differs; Master content will replace Satellite content.";
                $requested[]=$concept;
            }
        }
        return ['requestedConcepts'=>array_values(array_unique($requested)),'warnings'=>$warnings];
    }

    private function splitConcept(string $concept): array
    {
        if(!preg_match('/^(\d+)-(\d+)$/',$concept,$m)) return [0,0];
        return [(int)$m[1],(int)$m[2]];
    }

    private function localFilePath(array $row): ?string
    {
        if(empty($row['ulf_FilePath'])||empty($row['ulf_FileName'])) return null;
        $root=realpath($this->system->getSysDir());
        $path=realpath($this->system->getSysDir().$row['ulf_FilePath'].$row['ulf_FileName']);
        if(!$root||!$path||strpos($path,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)!==0) return null;
        return $path;
    }
}
