<?php
namespace hserv\records\batch\file;

use hserv\records\batch\RecordsBatchAction;

/**
 * Deletes thumbnail image files for all files associated with the selected records.
 *
 * This method identifies all uploaded files (`recUploadedFiles`) linked to the
 * specified batch of records via `recDetails`. For each such file, it constructs
 * the path to its thumbnail (e.g., `HEURIST_THUMB_DIR.'ulf_'.$obfuscatedFileID.'.png'`)
 * and deletes the thumbnail file if it exists.
 *
 * Note: This action is identified by `$this->data['a'] == 'reset_thumbs'` within `_validateParamsAndCounts`
 * to bypass the usual detail type validation, as it operates on all file fields.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional): Common batch parameters to select records.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: number of thumbnail files successfully deleted.
 *
 * @return array|false The result array (`$this->result_data`) with `['processed']` set to the count
 *                     of successfully deleted thumbnail files.
 *                     Returns `false` on critical validation failure.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchResetThumbnails extends RecordsBatchAction
{
    
    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        //1. find external urls for field values
        $query = 'SELECT ulf_ObfuscatedFileID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID '
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        $cnt = 0;
        $res = $mysqli->query($query);
        if ($res){

            while ($row = $res->fetch_row()){
                $obfuscation_id = preg_replace('/[^a-z0-9]/', "", $row[0]);//for snyk
                $thumbnail_file = HEURIST_THUMB_DIR.'ulf_'.$obfuscation_id.'.png';//'ulf_ObfuscatedFileID'
                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                    $cnt++;
                }
            }
        }

        $this->result_data['processed'] = $cnt;
        return $this->result_data;
    }

}
