<?php
namespace hserv\records\batch\file;

use hserv\entity\DbRecUploadedFiles;

/**
 * Converts remote file URLs (stored as `_remote` type in `recUploadedFiles`) within a specified
 * file-type detail field (`dtyID`) into locally stored files for a batch of records.
 *
 * Key operations:
 * - Validates parameters and record accessibility.
 * - Iterates through records and the specified `dtyID`:
 *   - Finds `recDetails` entries that link to `recUploadedFiles` where `ulf_OrigFileName` is "_remote".
 *   - Optionally filters by a substring in `ulf_ExternalFileReference` (`$this->data['url_substring']`).
 *   - For each unique remote URL (`ulf_ExternalFileReference`):
 *     - Downloads the file and registers it as a new local entry in `recUploadedFiles` using `DbRecUploadedFiles::downloadAndRegisterdURL()`.
 *       This method can check for existing files by name/checksum to avoid duplicates, based on the `$match_only` parameter.
 *     - If successful, the new local `ulf_ID` is obtained.
 *   - Updates all `recDetails` entries that pointed to the old remote `ulf_ID` to now point to the new local `ulf_ID`
 *     using `_updateUploadedFileIDs()`. This also updates `dtl_Modified`.
 * - If `$this->data['delete_file']` is 1, and after updating references, if the original remote `ulf_ID`
 *   is no longer referenced by any details, or if all its references were updated, the original `recUploadedFiles`
 *   entry for the remote URL is deleted.
 * - Assigns system tags if enabled and reports outcomes.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
 * - 'url_substring': (string, optional) Only process URLs containing this substring.
 * - 'match_only': (int, optional) Matching mode for `downloadAndRegisterdURL`. 1 for name only, 2 for name and checksum.
 * - 'delete_file': (int, optional) If 1, delete original `_remote` `recUploadedFiles` entry if no longer used or if all references updated.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records whose remote file references were replaced by local files.
 * - errors: records with SQL or reference-update errors.
 * - fails: number of failed downloads; fails_list contains their identifiers/details.
 * - processed/errors may include *_list and optional tag information.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation.
 *                     Returns `false` on critical validation failure.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchUrlToFile extends RecordsBatchFileAction
{
    
    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        $date_mode = date(DATE_8601);

        $tot_count = count($this->recIDs);

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace url to file $dtyName $date_mode";

        $processedRecIDs = array();
        $sqlErrors = array();
        $downloadError = array();

        //1. find external urls for field values
        //2. ulf_ExternalFileReference - extract filename and decode it
        //3. match_only!=1 Download of the remote file and check if the file already exists with the same name and checksum in the database and will not create a duplicate.
        //4. match_only==1 Check the file name only (avoids having to download the remote file if the name exists)
        //5. If download - register new file
        //6. Replace ulf_ID in dtl_UploadedFileID

        $file_entity = new DbRecUploadedFiles($this->system);

        //1. find external urls for field values
        $query = 'SELECT dtl_ID, ulf_ID, ulf_ExternalFileReference, dtl_RecID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND ulf_OrigFileName="_remote" AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        if($this->data['url_substring']){
            $query = $query.' AND ulf_ExternalFileReference LIKE "%'.$mysqli->real_escape_string($this->data['url_substring']).'%"';
        }

        $query = $query.' ORDER BY ulf_ID';

        $res = $mysqli->query($query);
        if ($res){
            $ulf_ID = null;
            $dtl_IDs = array();
            $rec_IDs = array();
            $ulf_ID_new = null;

            $progressSeen = array();
            while ($row = $res->fetch_row()){
                $progressSeen[intval($row[3])] = true;
                if(!$this->_progressStep(count($progressSeen)-1, null, 'Downloading files', 1)){
                    break;
                }
                if($ulf_ID!=$row[1]){

                    if($ulf_ID_new>0){
                        if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                            $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                        }else{
                            $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                        }
                    }

                    $ulf_ID = $row[1];
                    $dtl_IDs = array();
                    $rec_IDs = array();
                    $ulf_ID_new = null;

                    //find local ulf_ID

                    //2. ulf_ExternalFileReference
                    $surl = $row[2];

                    //5. If download - register new file
                    $file_entity->setRecords(null);
                    //$ulf_ID_new = false;
                    $ulf_ID_new = $file_entity->downloadAndRegisterdURL($surl, null, (@$this->data['match_only']==1)?1:2);//it returns ulf_ID
                    if(!$ulf_ID_new){
                        //can't download
                        $downloadError[] = $row[3];//rec_ID
                    }

                }

                $dtl_IDs[] = intval($row[0]);
                $rec_IDs[] = intval($row[3]);


            }//while

            if($ulf_ID_new>0){
                if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                    $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                }else{
                    $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                }
            }
        }

        //$this->result_data['processed'] = $tot_count;

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sqlErrors, $baseTag);
        $this->result_data['fails'] = count($downloadError);
        $this->result_data['fails_list'] = $downloadError;

        return $this->result_data;
    }

}
