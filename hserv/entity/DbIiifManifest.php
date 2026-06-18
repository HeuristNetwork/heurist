<?php
/**
* DbIiifManifest.php - Record-type-backed entity for RT_IIIF_MANIFEST.
*/
namespace hserv\entity;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';

/**
 * Manages IIIF Manifest records stored as user records of RT_IIIF_MANIFEST.
 */
class DbIiifManifest extends DbRecordTypeEntity
{
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_IIIF_MANIFEST';
        $this->recordTypeConceptCode = '2-110';

        $this->requiredConstants = array(
            'RT_IIIF_MANIFEST',
            'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            'DT_FILE_RESOURCE',
            'DT_URL',
            'DT_IIIF_MANIFEST_ID',
            'DT_IIIF_IMPORT_MODE'
        );

        // Fill these when the term concept codes are final.
        $this->requiredTermConstants = array(
            'TRM_IIIF_IMPORT_MODE_OVERLAY' => '2-10444',
            'TRM_IIIF_IMPORT_MODE_PRESERVE_CANVASES' => '2-10446',
            'TRM_IIIF_IMPORT_MODE_MANAGED' => '2-10445'
        );
    }

    /**
     * Create or update a minimal RT_IIIF_MANIFEST record for a registered Manifest file.
     * $manifestFile is the resolved file array from ImportAnnotations.
     */
    public function ensureFromManifestFile(array $manifestFile, array $manifest, string $importMode='overlay')
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return 0;
        }

        $manifestId = $this->getJsonId($manifest);
        $recordId = $this->findManifestRecord($manifestFile, $manifestId);

        $details = array();
        $title = $this->normaliseLangValue(@$manifest['label']);
        if(!$title){
            $title = basename((string)@$manifestFile['source_url']);
        }

        $this->setField($details, 'DT_NAME', $title);
        $this->setField($details, 'DT_FILE_RESOURCE', intval(@$manifestFile['ulf_ID']));

        if(@$manifestFile['ulf_ExternalFileReference']){
            $this->setField($details, 'DT_URL', $manifestFile['ulf_ExternalFileReference']);
        }

        $this->setField($details, 'DT_IIIF_MANIFEST_ID', $manifestId);

        $modeValue = $this->resolveImportModeTerm($importMode);
        if($modeValue){
            $this->setField($details, 'DT_IIIF_IMPORT_MODE', $modeValue);
        }

        $desc = $this->normaliseLangValue(@$manifest['summary']);
        if(!$desc){
            $desc = $this->normaliseLangValue(@$manifest['description']);
        }
        $this->setField($details, 'DT_EXTENDED_DESCRIPTION', $desc);

        $res = $this->saveRecordDetails($recordId, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return 0;
        }
        return intval($res['data']);
    }

    private function resolveImportModeTerm(string $importMode): ?int
    {
        switch($importMode){
            case 'overlay':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_OVERLAY') ?: $this->getTermId('overlay');
            case 'preserve_canvases':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_PRESERVE_CANVASES') ?: $this->getTermId('preserve_canvases');
            case 'managed':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_MANAGED') ?: $this->getTermId('managed');
            default:
                return $this->getTermId($importMode);
        }
    }

    private function findManifestRecord(array $manifestFile, ?string $manifestId): int
    {
        $mysqli = $this->system->getMysqli();
        $rty = $this->recordTypeId();
        if(!$rty){
            return 0;
        }

        $conditions = array();

        if(defined('DT_FILE_RESOURCE') && intval(@$manifestFile['ulf_ID'])>0){
            $ulfID = intval($manifestFile['ulf_ID']);
            $conditions[] = '(d.dtl_DetailTypeID='.DT_FILE_RESOURCE.' AND (d.dtl_UploadedFileID='.$ulfID.' OR d.dtl_Value="'.$ulfID.'"))';
        }

        if(defined('DT_URL') && @$manifestFile['ulf_ExternalFileReference']){
            $conditions[] = '(d.dtl_DetailTypeID='.DT_URL.' AND d.dtl_Value="'.addslashes($manifestFile['ulf_ExternalFileReference']).'")';
        }

        if(defined('DT_IIIF_MANIFEST_ID') && $manifestId){
            $conditions[] = '(d.dtl_DetailTypeID='.DT_IIIF_MANIFEST_ID.' AND d.dtl_Value="'.addslashes($manifestId).'")';
        }

        if(empty($conditions)){
            return 0;
        }

        $query = 'SELECT r.rec_ID FROM Records r, recDetails d WHERE r.rec_ID=d.dtl_RecID '
            .'AND r.rec_RecTypeID='.$rty.' AND ('.implode(' OR ', $conditions).') LIMIT 1';
        $recID = mysql__select_value($mysqli, $query);
        return $recID ? intval($recID) : 0;
    }
}
