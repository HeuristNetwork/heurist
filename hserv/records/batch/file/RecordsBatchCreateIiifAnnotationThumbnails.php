<?php
namespace hserv\records\batch\file;

use hserv\entity\DbAnnotations;
use hserv\records\batch\RecordsBatchAction;

/**
 * Creates thumbnails for selected IIIF Annotation records.
 *
 * The action accepts a normal record batch selection, filters it to records of
 * type `RT_IIIF_ANNOTATION`, and calls
 * `DbAnnotations::createAnnotationThumbnail()` for each annotation record.
 *
 * Two modes are supported:
 * - Missing thumbnails only: `missedonly=1`; existing thumbnails are retained
 *   and `$replaceExisting` is passed as `false`.
 * - Recreate all thumbnails: `missedonly` is absent or zero;
 *   `$replaceExisting` is passed as `true`.
 *
 * Expected parameters in `$this->data`:
 * - `recIDs`: Record IDs to process, or `ALL` for an administrator.
 * - `rtyID`: Optional record-type filter used by the common batch selection.
 * - `missedonly`: Optional flag. When `1`, existing thumbnails are not replaced.
 * - `session`: Optional progress-session identifier used by the batch controller.
 *
 * Report format:
 * - `passed`: Number of selected accessible records.
 * - `noaccess`: Number of selected records excluded by access checks.
 * - `processed`: Number of annotation records for which a thumbnail exists after
 *   the operation (newly created, replaced, or retained in missing-only mode).
 * - `skipped`: Number of selected records that are not IIIF Annotation records.
 * - `skipped_list`: IDs of records skipped because their type is not
 *   `RT_IIIF_ANNOTATION`.
 * - `fails`: Number of annotation records for which no thumbnail could be created.
 * - `fails_list`: Annotation record IDs for which thumbnail creation returned zero.
 *
 * @return array|false Batch report on success, or `false` on permission,
 *                     parameter, definition, or database failure.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchCreateIiifAnnotationThumbnails extends RecordsBatchAction
{
    public function execute()
    {
        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif(isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        if(!$this->system->defineConstant('RT_IIIF_ANNOTATION')){
            $this->system->addError(
                HEURIST_NOT_FOUND,
                'Record type "IIIF Annotation" (2-109) is not defined in this database'
            );
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $annotationTypeID = intval(RT_IIIF_ANNOTATION);

        if(@$this->recIDs[0]==='all'){
            $annotationRecIDs = mysql__select_list(
                $mysqli,
                'Records',
                'rec_ID',
                'rec_RecTypeID='.$annotationTypeID
            );
            if($annotationRecIDs===null && $mysqli->error){
                $this->system->addError(
                    HEURIST_DB_ERROR,
                    'Cannot identify IIIF Annotation records',
                    $mysqli->error
                );
                return false;
            }
            $annotationRecIDs = prepareIds($annotationRecIDs);
            $skippedRecIDs = array();
            $skippedCount = max(0, intval(@$this->result_data['passed']) - count($annotationRecIDs));
        }else{
            $annotationRecIDs = mysql__select_list(
                $mysqli,
                'Records',
                'rec_ID',
                'rec_RecTypeID='.$annotationTypeID
                .' AND rec_ID in ('.implode(',', $this->recIDs).')'
            );
            if($annotationRecIDs===null && $mysqli->error){
                $this->system->addError(
                    HEURIST_DB_ERROR,
                    'Cannot identify IIIF Annotation records',
                    $mysqli->error
                );
                return false;
            }

            $annotationRecIDs = prepareIds($annotationRecIDs);
            $skippedRecIDs = array_values(array_diff($this->recIDs, $annotationRecIDs));
            $skippedCount = count($skippedRecIDs);
        }

        $replaceExisting = intval(@$this->data['missedonly'])!==1;
        $dbAnnotations = new DbAnnotations($this->system);
        $processedRecIDs = array();
        $failedRecIDs = array();

        foreach($annotationRecIDs as $recID){
            $recID = intval($recID);
            $thumbnailID = $dbAnnotations->createAnnotationThumbnail($recID, $replaceExisting);
            if($thumbnailID>0){
                $processedRecIDs[] = $recID;
            }else{
                $failedRecIDs[$recID] = 'Thumbnail was not created';
            }
        }

        $this->_assignTagsAndReport('processed', $processedRecIDs, null);
        if($skippedCount>0){
            $this->result_data['skipped'] = $skippedCount;
            if(!empty($skippedRecIDs)){
                $this->result_data['skipped_list'] = $skippedRecIDs;
            }
        }
        $this->_assignTagsAndReport('fails', $failedRecIDs, null);

        return $this->result_data;
    }
}
