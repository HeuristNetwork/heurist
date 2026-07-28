<?php
namespace hserv\records\batch\file;

use hserv\entity\DbRecUploadedFiles;

/**
 * Uploads locally stored files (associated with a specified detail field in a batch of records)
 * to an external repository (currently supports Nakala) and updates the record details
 * to point to the new external URL.
 *
 * Key operations:
 * - Validates parameters and record accessibility.
 * - Retrieves user credentials for the specified repository using `user_getRepositoryCredentials2`.
 * - For each record and its values in the specified file field (`dtyID`):
 *   - Identifies local files (not `_remote`, `__iiif__`, or `__tiled__`).
 *   - For each unique local file (`ulf_ID`):
 *     - Gathers metadata for the file (name, path, MIME type, description, uploader, added date).
 *     - Prepares repository-specific metadata (e.g., for Nakala: title, type, license, creator).
 *     - Uploads the file to the repository (e.g., `uploadFileToNakala`).
 *     - If upload is successful, registers the returned URL as a new `_remote` entry in `recUploadedFiles`
 *       using `DbRecUploadedFiles::registerURL()`, associating repository info in `ulf_Parameters`.
 *     - Updates all `recDetails` that pointed to the original local `ulf_ID` to now point to the new
 *       `ulf_ID` for the remote URL, using `_updateUploadedFileIDs()`.
 * - Optionally deletes the original local file and its `recUploadedFiles` entry if `$this->data['delete_file']` is 1
 *   and all references to it have been updated or if it's no longer referenced.
 * - Assigns system tags if enabled and reports outcomes.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
 * - 'repository': (string, required) Service ID of the target repository (e.g., "nakala.fr", "test.nakala.fr").
 * - 'license': (string, required for some repositories like Nakala) License for the uploaded file.
 * - 'delete_file': (int, optional) If 1, delete original local file after successful upload and reference update.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records whose local files were uploaded and references updated.
 * - errors: records with upload, registration, reference-update or SQL errors.
 * - fails: number of files that could not be processed; fails_list contains their identifiers.
 * - processed/errors may include *_list and optional tag information.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation.
 *                     Returns `false` on critical validation/upload failure or if repository credentials are not found/valid.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchUploadToRepository extends RecordsBatchFileAction
{
    
    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $today = date(DATE_8601);

        $dtyID = $this->data['dtyID'];
        $dtyName = @$this->data['dtyName'] ? "'{$this->data['dtyName']}'" : "id:{$this->data['dtyID']}";
        $baseTag = "~replace file to url $dtyName $today";

        $processedRecIDs = [];
        $sqlErrors = [];
        $uploadError = [];
        $failedIDs = [];

        $fileEntity = new DbRecUploadedFiles($this->system);

        // Find relevant local files
        $query = 'SELECT dtl_ID, ulf_ID, dtl_RecID '
        .'FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND '
        .'(NOT(ulf_OrigFileName="_remote" OR ulf_OrigFileName LIKE "'.ULF_IIIF.'%" OR ulf_OrigFileName LIKE "'.ULF_TILED_IMAGE.'%" '
        .'OR COALESCE(ulf_PreferredSource,"") LIKE "iiif%" OR COALESCE(ulf_PreferredSource,"") LIKE "tiled%"))'
        .' AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs)
        .' ORDER BY ulf_ID';
        $res = $mysqli->query($query);
        /** $row:
         * [0] => Rec Detail ID
         * [1] => File ID
         * [2] => Record ID
         */

        if(!$res){ // mysql error, end
            $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to retrieve records using locally stored files.<br><br>MySQLi Error: {$mysqli->error}");
            return false;
        }

        $cur_ulfID = 0;
        $new_ulfID = 0;
        $dtlIDs = [];
        $recIDs = [];
        $completed_ulfIDs = [];

        //2024-03-23
        // Obtain write API key/credentials
        $serviceID = $this->data['repository'];

        $credentials = user_getRepositoryCredentials2($this->system, $serviceID);

        if($credentials==null){

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Credentials for sepecified repository and user/group not found');
            return false;

        }elseif(!@$credentials[$serviceID]['params']['writeApiKey']){

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Write Credentials for sepecified repository and user/group not defined');
            return false;

        }elseif(strpos($serviceID,'nakala')===0 || strpos($serviceID,'nakala')===1){

            if(!array_key_exists('license', $this->data) || empty($this->data['license'])){ // ensure a license has been provided
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'A license is missing');
                return false;
            }

            $metaValues = [];
            $file = [];

            // General Meta data
            // Normal Creator field (we use alternative author field, as this requires Author Ids/ORCIDs)
            $metaValues['creator'] = [
                'value' => null,
                'lang' => null,
                'typeUri' => null,
                'propertyUri' => NAKALA_REPO.'terms#creator'
            ];
            // Provided by user - used for all files
            $metaValues['license'] = [
                'value' => $this->data['license'],
                'lang' => null,
                'typeUri' => W3_XML_SCHEMA_STRING,
                'propertyUri' => NAKALA_REPO.'terms#license'
            ];

            $apiKey = $credentials[$serviceID]['params']['writeApiKey']; // $this->system->settings->get('sys_NakalaKey')
            $status = @$this->data['status'] === 'pending' || @$this->data['status'] === 'published' ? $this->data['status'] : 'pending'; // pending | published

            $progressSeen = array();
            while($row = $res->fetch_row()){
                $progressSeen[intval($row[2])] = true;
                if(!$this->_progressStep(count($progressSeen)-1, null, 'Uploading files', 1)){
                    break;
                }

                if($cur_ulfID != $row[1]){

                    if($new_ulfID > 0){
                        if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                            $completed_ulfIDs[$row[1]] = $new_ulfID;
                            $processedRecIDs = array_merge($processedRecIDs, $recIDs);
                        }else{
                            $failedIDs = array_merge($failedIDs, $recIDs);
                        }
                    }

                    $cur_ulfID = $row[1];
                    $dtlIDs = [];
                    $recIDs = [];
                    $new_ulfID = 0;

                    [$fileMetadata, $file] = getFileDetailsForNakala($mysqli, $row[1]);
                    if(!$fileMetadata){
                        $sqlErrors[$row[2]][] = $file;
                        $failedIDs[] = $row[2];
                        continue;
                    }

                    $fileMetadata = array_merge($fileMetadata, $metaValues);

                    $rtn = uploadFileToNakala($this->system, [
                        'apiKey' => $apiKey, 'file' => $file,
                        'meta' => $fileMetadata, 'status' => $status
                    ]);

                    if($rtn){ // register URL ($rtn)

                        $nakalaIdentifier = @$rtn['DOI']; // reserved DOI string, set by uploadFilesToNakala() regardless of returnType

                        $ulfParams = ['repository' => $serviceID];
                        if($nakalaIdentifier){
                            // "Touch" the file record with its DOI - the Nakala identifier IS the DOI
                            // string from creation onward, but is only registered/resolvable with
                            // DataCite once $status is 'published' (see getNakalaDataDetails() to
                            // confirm/regain this later if it changes after the fact)
                            $ulfParams['doi'] = $nakalaIdentifier;
                            $ulfParams['doiRegistered'] = $status === 'published';
                        }

                        $fields = [];
                        if($serviceID){
                            $fields['ulf_Parameters'] = json_encode($ulfParams);
                        }else{
                            $fields = null;
                        }

                        $new_ulfID = $fileEntity->registerURL($rtn['URL'], false, 0, $fields);// register nakala url
                        if(!isPositiveInt($new_ulfID)){
                            $sqlErrors[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $mysqli->error;
                            $failedIDs[] = $row[2];
                        }elseif($nakalaIdentifier){
                            // Also log it at database level, alongside any other repository deposits
                            // (eg. the whole-database archive backup) in this database's external_IDs.json
                            recordExternalIdentifier($this->system, "{$serviceID}_{$new_ulfID}", [
                                'Service' => 'nakala',
                                'Label' => "Nakala file transfer (file #{$new_ulfID})",
                                'ID' => $nakalaIdentifier,
                                'DOI' => $nakalaIdentifier,
                                'DOIRegistered' => ($status === 'published'),
                                'URL' => $rtn['URL'],
                                'Date' => $today
                            ]);
                        }
                    }else{

                        $errMsg = $this->system->getError();

                        if(array_key_exists('message', $errMsg) && !empty($errMsg['message'])){
                            $errMsg = $errMsg['message'];
                        }else{
                            $errMsg = 'Unknown error occurred while uploading to Nakala';
                        }

                        $uploadError[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $errMsg;
                        $failedIDs[] = $row[2];
                    }
                }

                $dtlIDs[] = intval($row[0]);
                $recIDs[] = intval($row[3]);

            } // while
        }
        if($new_ulfID > 0){
            if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                $completed_ulfIDs[$row[1]] = $new_ulfID;
                $processedRecIDs = array_merge($processedRecIDs, $recIDs);
            }else{
                $failedIDs = array_merge($failedIDs, $recIDs);
            }
        }

        if(!empty($completed_ulfIDs)){
            $ulfToDelete = [];
            foreach ($completed_ulfIDs as $org_ulfID => $new_ulfID) {
                $query = "SELECT dtl_ID FROM recDetails WHERE dtl_UploadedFileID = {$org_ulfID}";
                $dtlIDs = mysql__select_list2($mysqli, $query, 'intval');

                if(!$dtlIDs){
                    continue;
                }

                if(empty($dtlIDs)){ // delete file reference + local file
                    $ulfToDelete[] = $org_ulfID;
                }elseif(array_key_exists('delete_file', $this->data) && $this->data['delete_file'] == 1){
                    // update references
                    $dtlIDs = prepareIds($dtlIDs);//for snyk
                    if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                        // then delete the file reference + local file
                        $ulfToDelete[] = $org_ulfID;
                    }
                }
            }

            if(!empty($ulfToDelete)){
                $curData = $fileEntity->getData();
                $curData['ulf_ID'] = array_unique($ulfToDelete);
                $fileEntity->setData($curData);
                $fileEntity->delete();
            }
        }

        $failedIDs = array_unique($failedIDs);

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  array_merge($sqlErrors, $uploadError), $baseTag);
        $this->result_data['fails'] = count($failedIDs);
        $this->result_data['fails_list'] = $failedIDs;

        return $this->result_data;
    }

}
