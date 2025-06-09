<?php
/**
* rectype_titlemask.php - handler for record's title masks operations
* 
* @see records/edit/recordTitleMask.php
*
* parameters:
* 
* rty_id - record type id to check
* mask - title mask, if not defined we get current mask if check=0
* rec_id - execute mask for this record
* 
* check 0 - execute for given record id
*       1 - validate mask
*       2 - get coded mask
*       3-  get human readable
* 
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
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