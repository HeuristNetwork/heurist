<?php
/**
* DefinitionSnapshotService.php - Compact database-structure snapshot builder
*
* Produces the minimal record-type / field / term / structure snapshot consumed
* by client query builders (HFilterBuilder) and the query-language describer.
* The payload is cached as def-snapshot.json in the database "entity" system
* directory and rebuilt on demand after System::cleanDefCache() removes it.
*
* @project     Heurist academic knowledge management system
* @package     Definitions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Definitions;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;

/** Builds and caches the read-only database-definitions snapshot. */
final class DefinitionSnapshotService
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private string $cacheDirectory;

    /** @param string $cacheDirectory Absolute path of the database "entity" dir, or '' to disable caching. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        string $cacheDirectory = ''
    ) {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->cacheDirectory = $cacheDirectory === ''
            ? ''
            : rtrim(str_replace('\\', '/', $cacheDirectory), '/').'/';
    }

    /** Return the snapshot payload, using or refreshing the on-disk cache. */
    public function get(array $params = array()): array
    {
        $file = $this->cacheFile();
        if($file !== '' && is_readable($file)){
            $cached = json_decode((string)file_get_contents($file), true);
            if(is_array($cached) && isset($cached['meta'])){
                $mtime = @filemtime($file);
                if($mtime !== false){ $cached['meta']['version'] = (string)$mtime; }
                return $cached;
            }
        }

        $snapshot = $this->build();
        if($file !== '' && is_dir($this->cacheDirectory) && is_writable($this->cacheDirectory)){
            $written = @file_put_contents(
                $file,
                json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            if($written !== false){
                $mtime = @filemtime($file);
                if($mtime !== false){ $snapshot['meta']['version'] = (string)$mtime; }
            }
        }
        return $snapshot;
    }

    /** Cache-file mtime used as the HTTP ETag, or '0' when not yet cached. */
    public function version(): string
    {
        $file = $this->cacheFile();
        if($file !== '' && is_readable($file)){
            $mtime = @filemtime($file);
            if($mtime !== false){ return (string)$mtime; }
        }
        return '0';
    }

    /** Compose the full snapshot directly from the def* tables. */
    public function build(): array
    {
        $registeredId = intval($this->database->fetchValue(
            'SELECT sys_dbRegisteredID FROM sysIdentification LIMIT 1', array(), 0
        ));
        return array(
            'meta'          => $this->meta($registeredId),
            'rectypeGroups' => $this->groups('defRecTypeGroups', 'rtg'),
            'fieldGroups'   => $this->groups('defDetailTypeGroups', 'dtg'),
            'rectypes'      => $this->rectypes($registeredId),
            'fields'        => $this->fields($registeredId),
            'structure'     => $this->structure(),
            'terms'         => $this->terms($registeredId),
            'termlinks'     => $this->termlinks()
        );
    }

    private function cacheFile(): string
    {
        return $this->cacheDirectory === '' ? '' : $this->cacheDirectory.'def-snapshot.json';
    }

    private function meta(int $registeredId): array
    {
        $languages = array_values(array_filter(array_map(
            'strval',
            $this->database->fetchColumn(
                'SELECT DISTINCT trn_LanguageCode FROM defTranslations ORDER BY trn_LanguageCode'
            )
        ), static function($code){ return $code !== ''; }));

        return array(
            'db'        => $this->runtime->databaseName,
            'dbId'      => $registeredId,
            'version'   => (string)time(),   // replaced with the cache-file mtime by get()
            'generated' => gmdate('c'),
            'language'  => 'eng',            // translations deferred; see _README
            'languages' => $languages,
            'dbconst'   => $this->dbconst()
        );
    }

    /** Local IDs of the relationship record type and the relationship marker fields. */
    private function dbconst(): array
    {
        return array(
            'RT_RELATION'         => $this->localByConcept('defRecTypes', 'rty', 2, 1),
            'DT_PRIMARY_RESOURCE' => $this->localByConcept('defDetailTypes', 'dty', 2, 7),
            'DT_TARGET_RESOURCE'  => $this->localByConcept('defDetailTypes', 'dty', 2, 5),
            'DT_RELATION_TYPE'    => $this->localByConcept('defDetailTypes', 'dty', 2, 6)
        );
    }

    private function localByConcept(string $table, string $prefix, int $originDb, int $originId): int
    {
        return intval($this->database->fetchValue(
            'SELECT '.$prefix.'_ID FROM '.$table.' WHERE '
            .$prefix.'_OriginatingDBID=? AND '.$prefix.'_IDInOriginatingDB=? LIMIT 1',
            array($originDb, $originId), 0
        ));
    }

    private function groups(string $table, string $prefix): array
    {
        $out = array();
        $rows = $this->database->fetchAll(
            'SELECT '.$prefix.'_ID AS id,'.$prefix.'_Name AS name,'.$prefix.'_Order AS ord '
            .'FROM '.$table.' ORDER BY '.$prefix.'_Order,'.$prefix.'_Name'
        );
        foreach($rows as $row){
            $out[(string)intval($row['id'])] = array(
                'name'  => (string)$row['name'],
                'order' => intval($row['ord'])
            );
        }
        return $out;
    }

    private function rectypes(int $registeredId): array
    {
        $out = array();
        $rows = $this->database->fetchAll(
            'SELECT rty_ID,rty_Name,rty_Plural,rty_Description,rty_RecTypeGroupID,'
            .'rty_OriginatingDBID,rty_IDInOriginatingDB,rty_ShowInLists '
            .'FROM defRecTypes ORDER BY rty_ID'
        );
        foreach($rows as $row){
            $id = intval($row['rty_ID']);
            $plural = trim((string)$row['rty_Plural']);
            $entry = array(
                'name'    => (string)$row['rty_Name'],
                'plural'  => $plural !== '' ? $plural : (string)$row['rty_Name'],
                'group'   => intval($row['rty_RecTypeGroupID']),
                'concept' => $this->conceptCode(
                    $registeredId, $row['rty_OriginatingDBID'], $row['rty_IDInOriginatingDB'], $id
                ),
                'showInLists' => intval($row['rty_ShowInLists']) === 1
            );
            $description = trim((string)$row['rty_Description']);
            if($description !== '' && stripos($description, 'Description of this record type') === false){
                $entry['description'] = $description;
            }
            $out[(string)$id] = $entry;
        }
        return $out;
    }

    /** Global field definitions. Layout-only "separator" pseudo-fields are omitted. */
    private function fields(int $registeredId): array
    {
        $out = array();
        $rows = $this->database->fetchAll(
            'SELECT dty_ID,dty_Name,dty_Type,dty_DetailTypeGroupID,dty_JsonTermIDTree,'
            .'dty_PtrTargetRectypeIDs,dty_OriginatingDBID,dty_IDInOriginatingDB '
            .'FROM defDetailTypes WHERE dty_Type<>? ORDER BY dty_ID',
            array('separator')
        );
        foreach($rows as $row){
            $id = intval($row['dty_ID']);
            $entry = array(
                'name'    => (string)$row['dty_Name'],
                'type'    => (string)$row['dty_Type'],
                'group'   => intval($row['dty_DetailTypeGroupID']),
                'concept' => $this->conceptCode(
                    $registeredId, $row['dty_OriginatingDBID'], $row['dty_IDInOriginatingDB'], $id
                )
            );
            $vocabulary = $this->firstId($row['dty_JsonTermIDTree']);
            if($vocabulary > 0){ $entry['vocabulary'] = $vocabulary; }
            $targets = $this->idList($row['dty_PtrTargetRectypeIDs']);
            if(!empty($targets)){ $entry['targetTypes'] = $targets; }
            $out[(string)$id] = $entry;
        }
        return $out;
    }

    /** Per-record-type field placement. Separator fields are excluded. */
    private function structure(): array
    {
        $out = array();
        $rows = $this->database->fetchAll(
            'SELECT rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_DisplayOrder,rst_RequirementType '
            .'FROM defRecStructure '
            .'WHERE rst_DetailTypeID NOT IN (SELECT dty_ID FROM defDetailTypes WHERE dty_Type=?) '
            .'ORDER BY rst_RecTypeID,rst_DisplayOrder,rst_DetailTypeID',
            array('separator')
        );
        foreach($rows as $row){
            $out[] = array(
                'rty'   => intval($row['rst_RecTypeID']),
                'dty'   => intval($row['rst_DetailTypeID']),
                'name'  => (string)$row['rst_DisplayName'],
                'order' => intval($row['rst_DisplayOrder']),
                'req'   => (string)$row['rst_RequirementType']
            );
        }
        return $out;
    }

    private function terms(int $registeredId): array
    {
        $out = array();
        $rows = $this->database->fetchAll(
            'SELECT trm_ID,trm_Label,trm_Code,trm_Domain,trm_InverseTermID,'
            .'trm_OriginatingDBID,trm_IDInOriginatingDB '
            .'FROM defTerms ORDER BY trm_ID'
        );
        foreach($rows as $row){
            $id = intval($row['trm_ID']);
            $entry = array(
                'label'   => (string)$row['trm_Label'],
                'concept' => $this->conceptCode(
                    $registeredId, $row['trm_OriginatingDBID'], $row['trm_IDInOriginatingDB'], $id
                )
            );
            $code = trim((string)$row['trm_Code']);
            if($code !== ''){ $entry['code'] = $code; }
            if(strtolower((string)$row['trm_Domain']) === 'relation'){ $entry['domain'] = 'relation'; }
            $inverse = intval($row['trm_InverseTermID']);
            if($inverse > 0){ $entry['inverse'] = $inverse; }
            $out[(string)$id] = $entry;
        }
        return $out;
    }

    /** Term hierarchy from defTermsLinks, falling back to trm_ParentTermID. */
    private function termlinks(): array
    {
        $rows = $this->database->fetchRows(
            'SELECT trl_ParentID,trl_TermID FROM defTermsLinks ORDER BY trl_ParentID,trl_TermID'
        );
        if(empty($rows)){
            $rows = $this->database->fetchRows(
                'SELECT trm_ParentTermID,trm_ID FROM defTerms '
                .'WHERE trm_ParentTermID IS NOT NULL AND trm_ParentTermID>0 ORDER BY trm_ParentTermID,trm_ID'
            );
        }
        $out = array();
        foreach($rows as $row){
            $parent = intval($row[0]);
            $term = intval($row[1]);
            if($parent > 0 && $term > 0){
                $out[] = array('parent'=>$parent, 'term'=>$term);
            }
        }
        return $out;
    }

    /**
     * Stable OriginatingDBID-ID concept code.
     * Local definitions (origin 0, null, or this database) use the registered ID.
     */
    private function conceptCode(int $registeredId, $originDb, $originId, int $localId): string
    {
        $originDb = intval($originDb);
        $originId = intval($originId);
        if($originDb > 0 && $originId > 0 && $originDb !== $registeredId){
            return $originDb.'-'.$originId;
        }
        return ($registeredId > 0 ? $registeredId : 0).'-'.$localId;
    }

    /** Positive integers embedded in a CSV list or JSON term-ID tree string. */
    private function idList($value): array
    {
        if($value === null || $value === ''){ return array(); }
        preg_match_all('/\d+/', (string)$value, $matches);
        $ids = array();
        foreach($matches[0] as $match){
            $id = intval($match);
            if($id > 0){ $ids[$id] = $id; }
        }
        return array_values($ids);
    }

    /** First positive integer in a value - the vocabulary root term ID, or 0. */
    private function firstId($value): int
    {
        if($value === null || $value === ''){ return 0; }
        return preg_match('/\d+/', (string)$value, $match) ? intval($match[0]) : 0;
    }
}
