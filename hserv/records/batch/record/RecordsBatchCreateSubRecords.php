<?php
namespace hserv\records\batch\record;

use hserv\records\batch\RecordsBatchAction;

/**
 * Create sub records for the given record type
 *  Selected record fields are transferred to the newly created records of the selected 'sub-record' type
 *  Selected record fields are transferred to the newly created records of the selected 'sub-record' type.
 *  These new 'sub-records' are then set as child records to the original source record by:
 *  1. Adding a "Parent Entity" (DT_PARENT_ENTITY) pointer in the new sub-record, pointing to the original source record.
 *  2. Adding a record pointer detail in the original source record (field specified by `trg_dty`), pointing to the new sub-record.
 *  The original detail values are moved from the source record to the new sub-record(s).
 *
 * This operation requires admin privileges and the `DT_PARENT_ENTITY` constant to be defined.
 *
 * Expected parameters in `$this->data`:
 * - 'src_rty': (int) ID of the source record type from which values will be taken.
 * - 'trg_rty': (int) ID of the target record type for the new sub-records.
 * - 'src_dtys': (array|string) Detail type ID(s) in the source record type whose values will be moved.
 * - 'trg_dty': (int) Detail type ID in the source record type that will be used to point to the newly created sub-record(s).
 *                  This field must be of type 'resource'.
 * - 'split_values': (int, optional) If 0 (default), all selected details from a source record are moved to one new sub-record.
 *                     If 1, each individual detail value from the source fields will result in a separate new sub-record.
 *
 * Report format:
 * - count: number of sub-records created.
 * - record_ids: comma-separated IDs of the new sub-records.
 * - false is returned on validation, permission or database failure.
 *
 * @return array|false Returns an associative array `['count' => (int)num_new_records, 'record_ids' => (string)comma_separated_IDs]` on success.
 *                     Returns `false` on error (e.g., permission denied, parameters missing/invalid, DB error, DT_PARENT_ENTITY not defined).
 *                     Errors are added to the system object.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */

class RecordsBatchCreateSubRecords extends RecordsBatchAction
{
    
        public function execute(){

            // Can only be used by an administrator
            if(!$this->system->isAdmin()){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can create sub records');
                return false;
            }

            $system = $this->system;
            $mysqli = $system->getMysqli();

            $system->defineConstant('DT_PARENT_ENTITY');
            if(!defined('DT_PARENT_ENTITY')){
                $system->addError(HEURIST_ERROR, 'An error occurred while attempting to define system constants');
                return false;
            }

            $data = $this->data;

            // Retrieve and validate values

            /** List of values:
             * src_rty => Source record type
             * src_dtys => Source base fields
             * trg_rty => Target record type
             * trg_dty => Target record pointer field
             * split_values => Create a record per repeated value for each record
             */

            if(empty(@$data['src_rty']) || empty(@$data['trg_rty']) || empty(@$data['src_dtys']) || empty(@$data['trg_dty'])){
                $system->addError(HEURIST_INVALID_REQUEST, 'Parameters missing');
                return false;
            }

            $source_rty = intval($data['src_rty']);
            $target_rty = intval($data['trg_rty']);
            $split_values = empty(@$data['split_value']) ? 0 : $data['split_value'];
            $source_ids = prepareIds($data['src_dtys']);
            $target_field = intval($data['trg_dty']);

            if($source_rty <= 0){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source record type provided');
                return false;
            }
            if($target_rty <= 0){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target record type provided');
                return false;
            }
            if(empty($source_ids)){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source fields prepared');
                return false;
            }
            if($target_field <= 0){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target field provided');
                return false;
            }

            // Ensure target field exists in source structure and is a record pointer
            $query = "SELECT rst_ID FROM defRecStructure INNER JOIN defDetailTypes ON dty_ID = rst_DetailTypeID WHERE rst_RecTypeID = $source_rty AND rst_DetailTypeID = $target_field AND dty_Type = 'resource'";
            $target_in_struct = mysql__select_value($mysqli, $query);
            if($target_in_struct <= 0){
                $system->addError(HEURIST_ACTION_BLOCKED, 'Invalid target field');
                return false;
            }

            // Retieve existing records of source type
            $record_ids = mysql__select_list2($mysqli, "SELECT rec_ID FROM Records WHERE rec_FlagTemporary != 1 AND rec_RecTypeID = $source_rty", 'intval');

            if(empty($record_ids)){
                return ['count' => 0, 'record_ids' => []];
            }

            $rec_count = count($record_ids);// this is to avoid multiple swf emails when creating records
            $cur_count = 0;
            if($this->session_id != null){
                mysql__update_progress($mysqli, $this->session_id, true, "0,{$rec_count}");
            }

            $new_records = [];// final array of newly created records

            $keep_autocommit = mysql__begin_transaction($mysqli);

            if(!$this->checkRecordStructure($target_rty, $source_ids, $source_rty)){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return false;
            }

            foreach($record_ids as $rec_id){

                $cur_count ++;
                $rec_id = intval($rec_id);//snyk does not see intval in mysql__select_list2

                if($this->session_id != null){
                    $current_val = mysql__update_progress($mysqli, $this->session_id, true, "{$cur_count},{$rec_count}");
                    if($current_val == 'terminate'){
                        break;
                    }
                }

                // 1. Get values -----
                $details_to_transfer = array();
                $has_values = false;

                foreach($source_ids as $dty_id){

                    $details = mysql__select_list2($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $rec_id AND dtl_DetailTypeID = $dty_id");

                    if(empty($details)){ // skip
                        continue;
                    }

                    $idx = 0;

                    if($split_values == 0){
                        array_push($details_to_transfer, ...$details);
                    }else{
                        foreach($details as $dtl_ID){

                            if(!array_key_exists($idx, $details_to_transfer)){
                                $details_to_transfer[$idx] = array();
                            }
                            array_push($details_to_transfer[$idx], ...$dtl_ID);

                            ++ $idx;
                        }
                    }

                    $has_values = true;
                }

                if(!$has_values){
                    continue;
                }

                // 2. Create new sub-records -----
                // Include references to the parent record
                $record = [
                    'ID' => 0,
                    'no_validation' => 'ignore_all',
                    'rec_RecTypeID' => $target_rty,
                    'details' => [
                        DT_PARENT_ENTITY => [$rec_id]
                    ]
                ];

                $new_rec_ids = array();
                if($split_values == 0){

                    //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                    $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                    if($result['status'] != HEURIST_OK){

                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}

                        return false;
                    }

                    $new_rec_ids[] = $result['data'];
                }else{

                    foreach($details_to_transfer as $details){

                        //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                        $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                        if($result['status'] != HEURIST_OK){

                            $mysqli->rollback();
                            if($keep_autocommit===true) {$mysqli->autocommit(true);}

                            return false;
                        }

                        $new_rec_ids[] = $result['data'];
                    }
                }

                // 3. Update dtl_RecID for original values to point to new records -----
                foreach($new_rec_ids as $idx => $rec_id){

                    $dtl_IDs = $details_to_transfer;
                    if($split_values != 0){
                        $dtl_IDs = $details_to_transfer[$idx];
                    }

                    $dtl_IDs = prepareIds($dtl_IDs);//for snyk

                    $upd_where = count($dtl_IDs) == 1 ? ("= " . $dtl_IDs[0]) : ("IN (" . implode(',', $dtl_IDs) . ")");
                    $upd_query = "UPDATE recDetails SET dtl_RecID = $rec_id WHERE dtl_ID $upd_where";
                    $res = $mysqli->query($upd_query);

                    if(!$res || $mysqli->affected_rows == 0){ // affected rows should always be greater than 0

                        $msg = "<br><br>Error => " . $mysqli->error . "<br><br>Query => $upd_query";
                        $msg .= "<br><br>dtl_IDs => " . print_r($dtl_IDs, true);
                        $system->addError(HEURIST_DB_ERROR, "An SQL error occurred while attempting to update the original values from record #$rec_id");

                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}

                        return false;
                    }
                }

                // 4. Add child reference to original record -----

                // Get original record's header fields, to avoid lossing them
                $record = recordSearchByID($this->system, $rec_id, false);
                if(!$record){

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}
                    return false;
                }

                // Add rec pointer value(s)
                $record['ID'] = $record['rec_ID'];
                unset($record['rec_ID']);
                $record['no_validation'] = 1;
                $record['details'] = array(
                    $target_field => $new_rec_ids
                );

                $result = recordSave($this->system, $record, false, false, 2);
                if($result['status'] != HEURIST_OK || $result['data'] != $record['ID']){

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    return false;
                }

                array_push($new_records, ...$new_rec_ids);// add new rec ids to array
            }

            $mysqli->commit();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}

            $final_count = count($new_records);// get final count of new records

            return ['count' => $final_count, 'record_ids' => implode(',', $new_records)];
        }

    /**
         * Checks if a target record type (`$rtyID`) includes all specified detail types (`$dtyIDs`).
         * If `$importFromRty` is provided and greater than 0, and a field is missing in `$rtyID`,
         * this function will attempt to copy the field definition from `$importFromRty`'s structure
         * into `$rtyID`'s structure.
         *
         * @access private
         * @param int $rtyID The ID of the target record type whose structure is to be checked (and potentially updated).
         * @param array $dtyIDs An array of detail type IDs that must exist in the target record type's structure.
         * @param int $importFromRty Optional. The ID of a source record type from which to copy field definitions
         *                           if they are missing in `$rtyID`. Defaults to 0 (no import).
         * @return bool True if all specified `$dtyIDs` exist in `$rtyID`'s structure (either initially or after import),
         *              false otherwise (e.g., invalid parameters, a field is missing and cannot be imported, DB error).
         *              Errors are added to the system object on failure.
         */
        private function checkRecordStructure($rtyID, $dtyIDs, $importFromRty = 0){

            $dtyIDs = prepareIds($dtyIDs);
            $rtyID = intval($rtyID);
            $importFromRty = intval($importFromRty);

            if($rtyID <= 0 || empty($dtyIDs)){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $rtyID <= 0 ? 'Invalid record type to check has been provided' : 'No fields have been provided to check for');
                return false;
            }

            $mysqli = $this->system->getMysqli();
            $hasAllFields = true;

            foreach($dtyIDs as $dtyID){

                $hasFld = mysql__select_value($mysqli, "SELECT rst_ID FROM defRecStructure WHERE rst_DetailTypeID = ? AND rst_RecTypeID = ?", ['ii', $dtyID, $rtyID]);

                if($hasFld > 0){
                    continue;
                }

                if($importFromRty <= 0){
                    $hasAllFields = $dtyID;
                    break;
                }

                $fieldDetails = mysql__select_row_assoc($mysqli, "SELECT * FROM defRecStructure WHERE rst_DetailTypeID = {$dtyID} AND rst_RecTypeID = {$importFromRty}");
                if(empty($fieldDetails)){
                    $hasAllFields = $dtyID;
                    break;
                }

                unset($fieldDetails['rst_ID']);

                $fieldDetails['rst_RecTypeID'] = $rtyID;

                $rstID = mysql__insertupdate($mysqli, 'defRecStructure', 'rst', $fieldDetails, true);

                if(!$rstID){
                    $hasAllFields = $dtyID;
                    break;
                }
            }

            if(is_int($hasAllFields)){
                $this->system->addError(HEURIST_ACTION_BLOCKED, "Record structure is missing field type {$hasAllFields}");
                return false;
            }

            return true;
        }
}
