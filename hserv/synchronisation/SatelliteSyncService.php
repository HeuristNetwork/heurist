<?php
namespace hserv\synchronisation;

/**
 * Runs complete term and file manifests in strictly separated stages.
 *
 * Quoting the specification: "We send a full list of these concept codes" and
 * the receiver requests objects "missing or incompletely recorded". Therefore
 * interruption needs no cursor: the next run reconstructs the outstanding work
 * from the authoritative data tables. Record content intentionally remains out
 * of scope until its separate specification is approved.
 */
final class SatelliteSyncService
{
    private \hserv\System $system; private \mysqli $mysqli; private array $config;
    public function __construct(\hserv\System $system,SyncConfig $unused,array $config)
    {$this->system=$system;$this->mysqli=$system->getMysqli();$this->config=$config;}

    public function runAllocationStage():array
    {
        if(!SyncSchema::ensure($this->mysqli))return $this->system->addError(HEURIST_DB_ERROR,'Unable to initialise synchronisation support tables.');
        $lock='heurist-manifest-sync-'.$this->system->dbname();
        if((int)mysql__select_value($this->mysqli,'SELECT GET_LOCK(?,0)',['s',$lock])!==1)
            return $this->system->addError(HEURIST_ACTION_BLOCKED,'Another synchronisation is already running.');
        $progress=new SyncProgress($this->system);$progress->begin(0,0);
        try{
            $local=new ManifestSyncService($this->system);$client=new SyncHttpClient($this->system,$this->config);
            $progress->update('INTEGRITY','Checking Satellite term and file identities and MD5 checksums.');
            $integrity=$local->checkIntegrity();
            foreach($integrity['missingPhysicalFiles'] as $item)$progress->update('WARNING','Satellite file missing or unreadable: '.$item);

            // TERMS is completed before FILES begins.
            $satTerms=$local->satelliteTermManifest();
            $progress->update('TERMS_TO_MASTER','Sending '.count($satTerms).' Satellite-created term concepts to Master.');
            $comparison=$this->ok($client->termCompare($satTerms));if($comparison===false)return $this->system->getError();
            $wanted=array_fill_keys($comparison['missingConcepts']??[],true);
            $termPayload=array_values(array_filter($satTerms,static fn($row)=>isset($wanted[$row['conceptID']])));
            if($termPayload){$uploaded=$this->ok($client->termUpload($termPayload));if($uploaded===false)return $this->system->getError();}
            $progress->update('TERMS_TO_MASTER',count($termPayload).' missing Satellite terms added to Master.');

            $masterTermsResponse=$this->ok($client->termManifest());if($masterTermsResponse===false)return $this->system->getError();
            $masterTerms=$masterTermsResponse['manifest']??[];$localIndex=[];
            foreach($local->masterTermManifest() as $row)$localIndex[$row['conceptID']]=$row;
            $need=[];
            foreach($masterTerms as $row){$have=$localIndex[$row['conceptID']]??null;
                if(!$have||$have['label']!==$row['label']||($have['parentConceptID']??null)!==($row['parentConceptID']??null))$need[]=$row['conceptID'];}
            $progress->update('TERMS_FROM_MASTER','Master advertises '.count($masterTerms).' terms; requesting '.count($need).' missing or different terms.');
            $termResult=['inserted'=>0,'updated'=>0];
            if($need){$fetched=$this->ok($client->termFetch($need));if($fetched===false)return $this->system->getError();$termResult=$local->applyMasterTerms($fetched['terms']??[]);}
            $progress->update('TERMS_COMPLETE','Terms complete: '.$termResult['inserted'].' added and '.$termResult['updated'].' labels/positions updated from Master.');

            // FILES start only after term stages have completed.
            $satFiles=$local->fileManifest();$progress->update('FILES_ALLOCATE','Sending '.count($satFiles).' file concepts to Master for ID allocation.');
            $allocation=$this->ok($client->fileAllocate($satFiles));if($allocation===false)return $this->system->getError();
            foreach($allocation['warnings']??[] as $warning)$progress->update('WARNING',$warning);
            $remap=(new FileIDRemapper($this->system))->remap($allocation['mapping']??[]);
            if(($remap['status']??null)!==HEURIST_OK)return $remap;
            $progress->update('FILES_ALLOCATE',(int)($remap['data']['remapped']??0).' Satellite file IDs changed to Master IDs in one transaction.');

            $sent=0;
            foreach($allocation['requestedConcepts']??[] as $concept){
                $payload=$local->filePayload([$concept]);
                $progress->update('FILE_TO_MASTER','Sending '.$this->describeFile($payload[0]).' to Master.');
                $applied=$this->ok($client->fileUpload($payload));if($applied===false)return $this->system->getError();$sent+=(int)($applied['completed']??0);
                foreach($applied['warnings']??[] as $warning)$progress->update('WARNING',$warning);
            }
            $progress->update('FILES_TO_MASTER',$sent.' requested files completed on Master.');

            $masterFilesResponse=$this->ok($client->fileManifest());if($masterFilesResponse===false)return $this->system->getError();
            $masterFiles=$masterFilesResponse['manifest']??[];$prepared=$local->prepareMasterFiles($masterFiles);
            foreach($prepared['warnings']??[] as $warning)$progress->update('WARNING',$warning);
            $received=0;
            foreach($prepared['requestedConcepts']??[] as $concept){
                $fetched=$this->ok($client->fileFetch([$concept]));if($fetched===false)return $this->system->getError();
                $payload=$fetched['files']??[];
                if($payload)$progress->update('FILE_FROM_MASTER','Receiving '.$this->describeFile($payload[0]).' from Master.');
                $applied=$local->applyFiles($payload,true);$received+=(int)$applied['completed'];
                foreach($applied['warnings']??[] as $warning)$progress->update('WARNING',$warning);
            }
            $message='Term and file synchronisation completed. Records were not synchronised because record transfer is the next separately specified stage.';
            $progress->complete($message,0,0);
            return ['status'=>HEURIST_OK,'data'=>['state'=>'TERMS_AND_FILES_COMPLETE','satelliteTermConcepts'=>count($satTerms),
                'termsAddedToMaster'=>count($termPayload),'masterTermConcepts'=>count($masterTerms),
                'termsAddedToSatellite'=>$termResult['inserted'],'termsUpdatedOnSatellite'=>$termResult['updated'],
                'satelliteFileConcepts'=>count($satFiles),'filesSentToMaster'=>$sent,
                'masterFileConcepts'=>count($masterFiles),'filesReceivedBySatellite'=>$received,
                'checksumsCalculated'=>$integrity['checksumsCalculated'],'missingPhysicalFiles'=>$integrity['missingPhysicalFiles']]];
        }catch(\Throwable $e){$this->system->addError(HEURIST_ACTION_BLOCKED,$e->getMessage(),get_class($e));$progress->fail($e->getMessage());return $this->system->getError();}
        finally{mysql__select_value($this->mysqli,'SELECT RELEASE_LOCK(?)',['s',$lock]);}
    }

    private function ok(array $response)
    {
        if(($response['status']??null)!==HEURIST_OK){$this->system->addError($response['status']??HEURIST_ACTION_BLOCKED,
            (string)($response['message']??$response['msg']??'Master synchronisation request failed.'),(string)($response['sysmsg']??''));return false;}
        return is_array($response['data']??null)?$response['data']:[];
    }
    private function describeFile(array $payload):string
    {
        $meta=$payload['metadata']??[];$decoded=isset($payload['contentBase64'])&&is_string($payload['contentBase64'])
            ?base64_decode($payload['contentBase64'],true):false;$bytes=is_string($decoded)?strlen($decoded):0;
        return ($payload['conceptID']??'?').' "'.($meta['ulf_OrigFileName']??'unnamed').'" (MD5 '.($meta['ulf_MD5Checksum']??'none').', '.$bytes.' bytes)';
    }
}
