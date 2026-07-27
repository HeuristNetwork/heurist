<?php
namespace hserv\records\batch;

use hserv\records\batch\field\RecordsBatchDetailAdd;
use hserv\records\batch\field\RecordsBatchDetailReplace;
use hserv\records\batch\field\RecordsBatchDetailDelete;

/**
 * Executes a sequence of batch actions (add, replace, delete) in a single transaction.
 *
 * The actions to be performed are defined in the `$this->data['actions']` array.
 * Each element in this array is an associative array specifying an action ('a') and its parameters.
 * Supported actions:
 *  - 'add': Calls `detailsAdd()`.
 *  - 'replace': Calls `detailsReplace()`.
 *  - 'addreplace': Calls `detailsReplace()`. If `detailsReplace` indicates the field was undefined (value not found to replace),
 *                  it then calls `detailsAdd()` to add the value as a new detail.
 *  - 'delete': Calls `detailsDelete(true)`.
 *
 * All database operations are wrapped in a single MySQL transaction. If any action fails,
 * the transaction is rolled back.
 * The results of each action (specifically the 'processed' count) are aggregated into `$this->result_data`.
 *
 * Expected structure for `$this->data['actions']`:
 * `[
 *   ['a' => 'action_name_1', 'param1' => 'value1', ...],
 *   ['a' => 'action_name_2', 'param1' => 'valueX', ...],
 *   ...
 * ]`
 * Each sub-array's parameters must match what's expected by the corresponding method
 * (`detailsAdd`, `detailsReplace`, `detailsDelete`).
 *
 * Report format:
 * - The report from the most recently executed child action is returned.
 * - 'processed' contains the existing aggregated processed value used by this operation.
 * - Child action reports may also contain passed, noaccess, undefined, limited, errors,
 *   their corresponding *_list values and optional tag information.
 *
 * @return array|false The aggregated `$this->result_data` array if all actions succeed and the transaction is committed.
 *                     Returns `false` if any action returns `false` (indicating a critical error),
 *                     leading to a transaction rollback. System errors are set by the individual action methods.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */

class RecordsBatchMultiAction extends RecordsBatchAction
{
    public function execute()
    {
        $main_data = $this->data['actions'];

        $mysqli = $this->system->getMysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);
        $res = null;

        foreach ($main_data as $action_data) {

            if(@$action_data['a'] == 'add'){

                $action = new RecordsBatchDetailAdd($this->system, $action_data);
                $res = $action->execute();

            }elseif(@$action_data['a'] == 'replace'){

                $action = new RecordsBatchDetailReplace($this->system, $action_data);
                $res = $action->execute();

            }elseif(@$action_data['a'] == 'addreplace'){

                $action = new RecordsBatchDetailReplace($this->system, $action_data);
                $res = $action->execute();

                if(is_array($res) && @$res['passed']==1 && @$res['undefined']==1){
                    $action = new RecordsBatchDetailAdd($this->system, $action_data);
                    $res = $action->execute();
                }

            }elseif(@$action_data['a'] == 'delete'){

                $action_data['unconditionally'] = true;
                $action = new RecordsBatchDetailDelete($this->system, $action_data);
                $res = $action->execute();
            }

            if($res===false){
                break;
            }else{
                if(isset($action)){
                    $this->result_data = $action->getReport();
                }
                if(!@$this->result_data['processed']) {$this->result_data['processed'] = 0;}
                $this->result_data['processed'] = $this->result_data['processed']
                    +(@$res['processed']>0?$res['processed']:0);
            }
        }

        if($res===false){
            $mysqli->rollback();
            $res_data = $res;
        }else{
            $mysqli->commit();
            $res_data = $this->result_data;
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res_data;
    }
}
