<?php
/**
* recordVerify.php - handler for fix record duplication routine
* 
* @see recordFindDuplicates.js for client side
* @see recordDupes.php for actions
*
* parameters
* db - heurist database
* a or action
*   dupes
*        mode - levenshtein or metaphone
*        rty_ID
*        fields - comma separated list or array of dty_IDs and header fields
*        distance
*
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/

    require_once dirname(__FILE__).'/../../autoload.php';
    require_once dirname(__FILE__).'/../records/search/recordsDupes.php';

    $response = array();
    $res = false;

    $system = new hserv\System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        $response = $system->getError();

    }else {

        set_time_limit(0);

        if(@$_REQUEST['a'] == 'dupes' || @$_REQUEST['action'] == 'dupes'){

            if( @$_REQUEST['ignore'] ){
                $response = RecordsDupes::setIgnoring( $_REQUEST );
            }elseif(@$_REQUEST['export']){
                $response = RecordsDupes::exportList($_REQUEST);
            }else{
                $response = RecordsDupes::findDupes( $_REQUEST );
            }

            $system->dbclose();

            if( is_bool($response) && !$response ){
                $response = $system->getError();
                $system->setResponseHeader();
                print json_encode($response);
            }else{
                $system->setResponseHeader();
                print json_encode(array('status'=>HEURIST_OK, 'data'=>$response));
            }
        }
    }
?>