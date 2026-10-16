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
 * - processed: number of non-PDF thumbnail files successfully deleted.
 * - pdfrebuilt: number of PDF thumbnails generated immediately.
 * - errors/errors_list: failed PDF rendering or deletion, with record IDs and diagnostics.
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
        $query = 'SELECT DISTINCT ulf_ObfuscatedFileID, ulf_MimeExt, dtl_RecID, ulf_ID, ulf_OrigFileName FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID '
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        $cnt = 0;
        $pdfResults = [];
        $errors = [];
        $pdfCount = 0;
        $fileCount = 0;
        $pdfMatched = 0;
        // PDF refresh renders immediately so failures appear in the batch report.
        // Other file types keep the established lazy regeneration behaviour.
        require_once __DIR__.'/../../search/recordFile.php';
        $res = $mysqli->query($query);
        if ($res){

            $progressDone = 0;
            while ($row = $res->fetch_row()){
                if(!$this->_progressStep($progressDone, null, 'Resetting thumbnails', 1)){
                    break;
                }
                $progressDone++;
                $fileCount++;
                $obfuscation_id = preg_replace('/[^a-z0-9]/', "", $row[0]);//for snyk
                $thumbnail_file = HEURIST_THUMB_DIR.'ulf_'.$obfuscation_id.'.png';//'ulf_ObfuscatedFileID'
                if(in_array(strtolower($row[1] ?? ''), ['pdf','application/pdf'])
                        || strtolower(pathinfo($row[4] ?? '', PATHINFO_EXTENSION))==='pdf'){
                    $pdfMatched++;
                    if(!array_key_exists($obfuscation_id, $pdfResults)){
                        $pdfResults[$obfuscation_id] = \fileCreateThumbnail($this->system, intval($row[3]), false);
                        if($pdfResults[$obfuscation_id]===true){ $pdfCount++; }
                    }
                    if($pdfResults[$obfuscation_id]!==true){
                        $errors[$row[2]] = htmlspecialchars('PDF file '.$obfuscation_id.': '
                            .($pdfResults[$obfuscation_id] ?: 'PDF source was not found or could not be rendered.'), ENT_QUOTES, 'UTF-8');
                    }
                }elseif(file_exists($thumbnail_file)){
                    if(@unlink($thumbnail_file)){ $cnt++; }
                    else { $errors[$row[2]] = 'Cannot remove thumbnail: check file permissions.'; }
                }
            }
        }else{
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot query files for thumbnail refresh: '.$mysqli->error);
            return false;
        }

        $this->result_data['filesmatched'] = $fileCount;
        $this->result_data['pdfmatched'] = $pdfMatched;
        $this->result_data['processed'] = $cnt;
        $this->result_data['pdfrebuilt'] = $pdfCount;
        $this->result_data['errors'] = count($errors);
        $this->result_data['errors_list'] = $errors;
        return $this->result_data;
    }

}
