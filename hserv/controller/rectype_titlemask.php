<?php
/**
* rectype_titlemask.php - handler for record's title masks operations
* 
* @see records/edit/recordTitleMask.php
*
* parameters:
* 
* rty_id - The ID of the record type to check or use.
* mask   - The title mask string. If not defined and 'check' is 0, the current mask for the rty_id is used.
* rec_id - (Optional) The record ID for which to execute/generate the title mask. Used when 'check' is 0.
* 
* check  - (Optional) Defines the operation mode:
*          0 - Execute: Generate title for the given rec_id using the mask (default if 'check' is not provided).
*          1 - Validate: Validate the provided title mask syntax for the given rty_id.
*          2 - Get Coded: Convert the human-readable mask to its internal coded format.
*          3 - Get Human Readable: Convert the internal coded mask back to a human-readable format.
* 
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0 
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../records/edit/recordTitleMask.php';

// Initialize a System object that uses the requested database
$system = new hserv\System();
if( $system->init(@$_REQUEST['db']) ){

            $rectypeID = @$_REQUEST['rty_id'];
            $mask = @$_REQUEST['mask'];
            $check_mode = @$_REQUEST["check"];

            $invalid_mask = null;
            $response = null;

            if($check_mode==2){ //get coded mask

                $res = TitleMask::execute($mask, $rectypeID, 1, null, ERROR_REP_MSG);
                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }

            }elseif($check_mode==3){ //to human readable

                $res = TitleMask::execute($mask, $rectypeID, 2, null, ERROR_REP_MSG);

                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }

            }elseif($check_mode==1){ //verify text title mask

                $check = TitleMask::check($mask, $rectypeID, true);

                if (!empty($check)) { //empty means titlemask is valid
                    $invalid_mask =$check;
                }else{
                    $response = null;
                }

            }else{

                $recID = @$_REQUEST['rec_id'];
                $new_title = TitleMask::execute($mask, $rectypeID, 3, $recID, ERROR_REP_WARN);//convert to coded and fill values
                $response =  $new_title;

            }

    $system->dbclose();

    $response = array("status"=>HEURIST_OK, 'data'=>$response, 'message'=>$invalid_mask );

}else{
    $response = $system->getError();
}

// Returning result as JSON
header(CTYPE_JSON);
print json_encode($response);
?>