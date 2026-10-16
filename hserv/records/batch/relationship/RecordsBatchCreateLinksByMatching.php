<?php
namespace hserv\records\batch\relationship;

use hserv\records\batch\RecordsBatchAction;

/**
 * Creates links (resource/record pointer field) or Adds relationship records between records
 * based on certain fields values matching
 *
 * $data parameters
 *          dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
 *          rty_src or recids_src, dty_src, rty_trg, dty_trg - matching conditions
 *          `replace` - (int, optional) If 1, existing links of the same type on the source record will be replaced. Otherwise, new links are added.
 *
 * The method first validates parameters and user permissions (admin only).
 * It then uses `recordSearchMatchedValues()` to find pairs of source and target record IDs based on matching field values.
 * For each pair:
 *  - If `dty_ID` (resource pointer field) is specified, it calls `addPointerField()` to create/update the link.
 *  - If `trm_ID` (relation type) is specified, it's intended to create a relationship record (currently, this part seems to just set `$res=1` without full implementation for relationship record creation).
 * It handles progress tracking if a `session_id` is provided.
 *
 * Expected parameters in `$this->data`:
 * - 'dty_ID': (int, optional) Resource pointer field to populate.
 * - 'trm_ID': (int, optional) Relationship type; one of dty_ID or trm_ID is required.
 * - 'recids_src' or 'rty_src': Source record selection.
 * - 'dty_src': Source matching field.
 * - 'rty_trg': Target record type.
 * - 'dty_trg': Target matching field.
 * - 'replace': (int, optional) If 1, replace existing pointer values.
 * - 'session': (string, optional) Progress session identifier.
 *
 * Report format:
 * - added: number of new links created.
 * - exist: number of links already present.
 * - records_updated: number of unique source records changed.
 *
 * @return array|false Returns an associative array `['added' => count, 'exist' => count, 'records_updated' => count]` on success.
 *                     `added`: New links/relationships created.
 *                     `exist`: Links/relationships that already existed and were skipped.
 *                     `records_updated`: Number of unique source records that had links added/updated.
 *                     Returns `false` on error (e.g., permission denied, invalid parameters, DB error, user termination).
 *                     Errors are added to the system object.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */

class RecordsBatchCreateLinksByMatching extends RecordsBatchAction
{
    
        public function execute(){

            // Can only be used by an administrator
            if(!$this->system->isAdmin()){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can add multiple links');
                return false;
            }

            $system = $this->system;
            $mysqli = $system->getMysqli();

            $data = $this->data;

            //dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
            //recIDs or rty_src, dty_src, rty_trg, dty_trg - matching conditions

            //1. Validate dty_ID or trm_ID from parameters
            $dty_ID = intval(@$data['dty_ID']);
            $trm_ID = intval(@$data['trm_ID']);


            //check that this is resouce filed
            if($dty_ID>0){

                if('resource' != $this->getDetailType($dty_ID)){
                    $system->addError(HEURIST_INVALID_REQUEST, 'Wrong paramters for records link creation. Given field is not a record pointer ("resource") field');

                    return false;
                }
            }elseif($trm_ID>0){
                //check that trm_ID is valid

            }



            $to_replace = (@$data['replace']==1);//replace existing link

            //2. Find matching pairs - [source rec_ID, target rec_ID]
            $data['pairs'] = 1;
            $pairs = recordSearchMatchedValues($system, $data);

            if(@$pairs['status']==HEURIST_OK){
                $pairs = $pairs['data'];
            }else{
                return false;
            }

            //3. Add pointer or create relationship record

            if($trm_ID>0){
                $system->defineConstant('RT_RELATION');
                $system->defineConstant('DT_PRIMARY_RESOURCE');
                $system->defineConstant('DT_TARGET_RESOURCE');
            }

            $keep_autocommit = mysql__begin_transaction($mysqli);

            $this->result_data = array('added'=>0,'exist'=>0,'records_updated'=>0);

            $res = true;

            $execution_counter = 0;
            $tot_count = count($pairs);

            $prev_rec_ID = 0;

            foreach($pairs as $row){

                $source_id = $row[0];
                $target_id = $row[1];

                if($trm_ID>0){
                    $res = 1;
                }else{
                    $res = addPointerField($system, $source_id, $target_id, $dty_ID, $to_replace);
                }
                if($res<0){
                    $mysqli->rollback();
                    $res = false;
                    break;
                }elseif($res==0){
                    $res = true;
                    $this->result_data['exist']++;
                }else{
                    $this->result_data['added']++;
                    if($prev_rec_ID!=$source_id){
                        $prev_rec_ID=$source_id;
                        $this->result_data['records_updated']++;
                    }
                }


                if($this->session_id!=null){
                    //check for termination and set new value
                    $execution_counter++;
                    $session_val = $execution_counter.','.$tot_count;
                    $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                    if($current_val=='terminate'){ //session was terminated from client side
                        $system->addError(HEURIST_ACTION_BLOCKED, 'Action has been terminated by user');
                        return false;
                    }
                }
            }

            if($res){
                $mysqli->commit();
            }
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
            
            //update titles for both source and target records
            foreach ([0, 1] as $idx) {
                $titleMask = null;
                foreach($pairs as $row){
                    $recID = intval($row[$idx]);
                    if ($recID <= 0) {
                        continue;
                    }
                    if(!$titleMask){
                        
                        $titleMask = mysql__select_value(
                            $mysqli,
                            'SELECT rty_TitleMask
                               FROM Records
                               JOIN defRecTypes ON rty_ID = rec_RecTypeID
                              WHERE rec_ID = '.$recID
                        );
                        
                    }
                    recordUpdateTitle($system, $recID, $titleMask, null);
                }    
            }    

            return $res?$this->result_data:false;
        }
}
