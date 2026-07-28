<?php
namespace hserv\records\batch\record;

use hserv\records\batch\RecordsBatchAction;
use TitleMask;

/**
 * Changes the record type (`rec_RecTypeID`) for a batch of records.
 *
 * Key operations:
 * - Validates parameters and record accessibility using `_validateParamsAndCounts`.
 *   (A dummy `dtyID` is set to pass this validation as it's not strictly needed here).
 * - Iterates through the accessible records and updates their `rec_RecTypeID` and `rec_Modified` timestamp.
 * - Updates the title for each modified record using `TitleMask::fill` (which uses the new record type's mask).
 * - Assigns system tags if enabled.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (original type for filtering, optional), 'tag': Common batch parameters.
 * - 'rtyID_new': (int, required) The ID of the new record type to assign.
 * - 'rtyName': (string, optional) The name of the new record type (for tagging).
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records changed to the new record type.
 * - errors: records whose update failed.
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

class RecordsBatchChangeRecordType extends RecordsBatchAction
{
    
        public function execute(){

            $this->data['dtyID'] = '1';//dumb value to pass validation

            if(!$this->_validateParamsAndCounts()){
                return false;
            }elseif (isEmptyArray(@$this->recIDs)){
                return $this->result_data;
            }

            $rtyID_new = $this->data['rtyID_new'];
            $rtyName = (@$this->data['rtyName'] ? "'".$this->data['rtyName']."'" : "id:".$this->data['rtyID_new']);

            $mysqli = $this->system->getMysqli();

            $processedRecIDs = array();//success
            $sqlErrors = array();

            $now = date(DATE_8601);
            $dtl = array('dtl_Modified'  => $now);
            $rec_update = array('rec_ID'  => 'to-be-filled',
                                'rec_Modified'  => $now,
                                'rec_RecTypeID'  => $rtyID_new);

            $baseTag = "~changed rectype $rtyName $now";

            foreach ($this->recIDs as $progressIdx => $recID) {
            if(!$this->_progressStep($progressIdx, null, 'Processing records', 10)){
                break;
            }
                   //update record edit date
                   $rec_update['rec_ID'] = $recID;

                   $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                   if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
                   }else{
                       array_push($processedRecIDs, $recID);
                       //update title
                       $new_title = TitleMask::fill($recID); //on change rectype
                       $rec_update2 = array('rec_ID'  => $recID, 'rec_Title'  => $new_title);
                       mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update2);
                   }
            }//for recors



            //assign special system tags
            $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
            $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

            return $this->result_data;
        }
}
