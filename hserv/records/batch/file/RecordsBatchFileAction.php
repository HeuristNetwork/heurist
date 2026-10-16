<?php
namespace hserv\records\batch\file;

use hserv\records\batch\RecordsBatchAction;

/**
 * Base class for batch actions that replace uploaded-file references.
 */
abstract class RecordsBatchFileAction extends RecordsBatchAction
{
    /**
 * Updates the `dtl_UploadedFileID` and `dtl_Modified` timestamp for a given list of detail IDs (`dtl_ID`).
 *
 * This is a helper function typically used after a file operation (e.g., downloading an external URL
 * to create a local file, or uploading a local file to a repository and then linking back to the URL).
 * It changes which uploaded file (`recUploadedFiles` entry) a set of `recDetails` entries point to.
 *
 * @access private
 * @param int $ulf_ID_new The new `ulf_ID` (ID from `recUploadedFiles`) to assign to the details. Must be > 0.
 * @param array $dtl_IDs An array of `dtl_ID`s (primary keys from `recDetails`) to update. Must not be empty.
 * @param string $date_mode The timestamp string (e.g., from `date(DATE_8601)`) to set for `dtl_Modified`.
 * @return bool True if the update query was successful (or if no update was needed due to invalid params),
 *              false if a database error occurred during the update.
 */
    protected function _updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode){

        if($ulf_ID_new>0 && !empty($dtl_IDs)){
            $mysqli = $this->system->getMysqli();
            //6. Replace ulf_ID in dtl_UploadedFileID
            $query2 = 'UPDATE recDetails SET dtl_Modified="'.$date_mode
                .'", dtl_UploadedFileID='.intval($ulf_ID_new).' WHERE dtl_ID in ('.implode(',',$dtl_IDs).')';
            $res2 = $mysqli->query($query2);

            if(!$res2){
                //$this->system->addError(HEURIST_DB_ERROR,'Cannot assign IDs for registered files', $mysqli->error );
                return false;
            }
            //$tag_count = $mysqli->affected_rows;
        }
        return true;

    }

}
