<?php
namespace hserv\records\batch\relationship;

use hserv\records\batch\RecordsBatchAction;

/**
 * Converts existing records to child record for given rectype/detailtype
 * (adds reverse pointer field DT_PARENT_ENTITY) to child records.
 *
 * This method identifies parent-child relationships based on a specified pointer field (`dtyID`)
 * within a given parent record type (`rtyID`). For each such identified child record, it adds
 * a "Parent Entity" (DT_PARENT_ENTITY) detail pointing back to its parent.
 * It also updates the child record's title and sets the `rst_CreateChildIfRecPtr` flag
 * to 1 for the specified parent pointer field in `defRecStructure`.
 *
 * This operation requires admin privileges.
 * The constant `DT_PARENT_ENTITY` must be defined.
 *
 * Expected parameters in `$this->data`:
 * - 'rtyID': (int) The record type ID of the parent records.
 * - 'dtyID': (int) The detail type ID of the pointer field in the parent records that points to the child records.
 * - 'allow_multi_parent': (bool, optional) If true, allows a child record to have multiple Parent Entity pointers.
 *                           If false (default), an existing Parent Entity pointer might be updated if one already exists.
 *
 * Report format:
 * - passed, noaccess, disambiguation: input relationship counts.
 * - processedParents: parent record IDs processed.
 * - childInserted, childUpdated, childAlready: child outcome ID arrays.
 * - childMiltiplied: children receiving an additional parent pointer when allowed.
 * - titlesFailed: child IDs whose titles could not be updated.
 *
 * @return array|false Returns an associative array summarizing the operation on success, including counts of:
 *                     - 'passed': Total potential parent-child links found.
 *                     - 'noaccess': Child records the current user couldn't access (should be 0 for admin).
 *                     - 'disambiguation': Child records linked from multiple parents when `allow_multi_parent` is false.
 *                     - 'processedParents': Array of parent record IDs that were processed.
 *                     - 'childInserted': Array of child record IDs where a new Parent Entity pointer was inserted.
 *                     - 'childUpdated': Array of child record IDs where an existing Parent Entity pointer was updated.
 *                     - 'childAlready': Array of child record IDs that already had the correct Parent Entity pointer.
 *                     - 'childMiltiplied': Array of child record IDs that received an additional Parent Entity pointer (if `allow_multi_parent` was true).
 *                     - 'titlesFailed': Array of child record IDs whose titles failed to update.
 *                     Returns `false` if there's a permission error, critical system error (like DT_PARENT_ENTITY not defined),
 *                     invalid parameters, or a database error during the process. Errors are added to the system object.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */

class RecordsBatchAddReverseChildPointer extends RecordsBatchAction
{
    
        public function execute(){


            if (! $this->system->isAdmin() ) {
                $this->system->addError(HEURIST_REQUEST_DENIED);
                return false;
            }

            if(!$this->system->defineConstant('DT_PARENT_ENTITY')){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type 2-247 is not defined in this database');
                return false;
            }

            if(!$this->_validateDetailType()){
                return false;
            }

            $allow_multi_parent = ($this->data['allow_multi_parent']==true);

            $mysqli = $this->system->getMysqli();

            //1. find resource (child) records for given record type and detail
            $query = 'SELECT dtl_RecID as parent_id, d.dtl_Value as child_id, child.rec_OwnerUGrpID, child.rec_RecTypeID, child.rec_Title '
                .'FROM  recDetails d LEFT JOIN Records child on child.rec_ID=d.dtl_Value, Records parent '
                .'WHERE d.dtl_RecID=parent.rec_ID and parent.rec_RecTypeID='
                .$this->data['rtyID'].' and d.dtl_DetailTypeID='.$this->data['dtyID'];

            $res = $mysqli->query($query);
            if ($res){

                    $passedValues = 0;       //total values found
                    $inAccessibleRecCnt = 0; //no rights for child records
                    $cntDisambiguation = 0;  //more than one parent record

                    $childNotFound = array();
                    $parentRecords = array();
                    $childRecords = array();

                    $toProcess = array();

                    $groups = $this->system->getUserGroupIds();
                    array_push($groups, 0);

                    while ($row = $res->fetch_row()){

                        $passedValues++;

                        if(!($row[3]>0)){  //rec_RecTypeID
                            array_push($childNotFound, $row[0]);
                        }elseif(in_array($row[2], $groups)){  //rec_OwnerUGrpID
                            if($allow_multi_parent || !@$childRecords[$row[1]]){

                                $toProcess[] = $row;  //parent_id,child_id,0,child_rectype,child_title
                                if(!in_array($row[0],$parentRecords)){
                                    $parentRecords[] = $row[0];
                                }
                                $childRecords[$row[1]] = 1;

                            }else{
                                $cntDisambiguation++;
                            }
                        }else{
                            $inAccessibleRecCnt++;
                        }
                    }
                    $res->close();
            }else{
                $this->system->addError(HEURIST_DB_ERROR, "Can't find child records ".$mysqli->error );
                return false;
            }

            $this->result_data = array('passed'=> $passedValues,
                                       'noaccess'=> $inAccessibleRecCnt,
                                       'disambiguation'=> $cntDisambiguation);

            $keep_autocommit = mysql__begin_transaction($mysqli);

            if (!empty($toProcess)){
            //3. add reverse pointer field in child record to parent record
            $processedParents = array();
            $childInserted = array();
            $childUpdated = array();
            $childAlready = array();
            $titlesFailed = array();
            $childMiltiplied = array();

            foreach ($toProcess as $row) {
                //parent_id,child_id,0,child_rectype,child_title

                //check if child record has parent already
                $parent_id = $row[0];
                $child_id = $row[1];
                $res = addReverseChildToParentPointer($mysqli, $child_id, $parent_id, 0, $allow_multi_parent);

                if($res<0){
                    $syserror = $mysqli->error;
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}
                    return $this->system->addError(HEURIST_DB_ERROR,
                        'Unable to insert reverse pointer for child record ID:'.$child_id.' - ', $syserror);
                }elseif($res==0){
                     array_push($childAlready, $child_id);
                }else{

                    if($res==2){
                        if($allow_multi_parent){
                            if(!in_array($child_id, $childMiltiplied)) {array_push($childMiltiplied, $child_id);}
                        }else{
                            array_push($childUpdated, $child_id);
                        }

                    }else{
                        array_push($childInserted, $child_id);
                    }
                    if(!in_array($parent_id, $processedParents)){
                        array_push($processedParents, $parent_id);
                    }

                    //update record title for child record
                    $child_rectype = $row[3];
                    $child_title = $row[4];

                    if(!recordUpdateTitle($this->system, $child_id, $child_rectype, $child_title)){ //on add child record
                        $titlesFailed[] = $child_id;
                    }
                }



            } //foreach

            $this->result_data['processedParents'] = $processedParents;
            $this->result_data['childInserted'] = $childInserted;
            $this->result_data['childUpdated'] = $childUpdated;
            $this->result_data['childAlready'] = $childAlready;
            $this->result_data['childMiltiplied'] = $childMiltiplied;
            $this->result_data['titlesFailed'] = $titlesFailed;


            }
            //set rst_CreateChildIfRecPtr=1
            $query = 'UPDATE defRecStructure set rst_CreateChildIfRecPtr=1 WHERE rst_RecTypeID='
                .$this->data['rtyID'].' and rst_DetailTypeID='.$this->data['dtyID'];

            $res = $mysqli->query($query);
            if(!$res){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return $this->system->addError(HEURIST_DB_ERROR,
                    'Unable to set value in record sructure table', $syserror);
            }


            $mysqli->commit();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}

            return $this->result_data;
        }
}
