<?php
/**
* getDatabaseURL.php - Retrieves the URL for a registered Heurist database by its ID.
*
* @fileOverview This script provides backward compatibility for older versions of Heurist
*               and has largely been replaced by `DbRegis::registrationGet()`.
*               It requests the URL for a registered database (identified by `$database_id`)
*               from the Heurist Reference Index server. If the current server is not the
*               reference index, it forwards the request to `HEURIST_INDEX_BASE_URL`.
*               The script outputs a JSON response containing the `rec_URL` or an `error_msg`.
*               This script can be included (where `$database_id` is predefined) or invoked via HTTP.
*
* @project     Heurist academic knowledge management system
* @package Admin/dbproperties
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/



use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../../autoload.php';

$isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);//this is reference server
if($isOutSideRequest){ //this is request from outside - redirect to master index

    $reg_url = HEURIST_INDEX_BASE_URL
    .'admin/setup/dbproperties/getDatabaseURL.php?db='.HEURIST_INDEX_DATABASE
    .'&remote=1&id='.$database_id;

    $data = loadRemoteURLContentSpecial($reg_url);//get registered database URL

    if (!$data) {

        $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        $error_msg = "Unable to connect Heurist Reference Index, possibly due to timeout or proxy setting<br>"
            . $error_code . "<br>"
            . "URL requested: ".$reg_url."<br><br>";
    }else{

        $data = json_decode($data, true);

        // Artem TODO: What circumstance would give rise to this? Explain how the data is 'wrong'/'incorrect'
        // Artem: cannot connect to Master Reference Database, Records table is corrupted, $database_id is not found
        if(@$data['error_msg']){
            $error_msg = $data['error_msg'];
        }elseif(!@$data['rec_URL']){
            $error_msg = "Heurist Reference Index returns incorrect data for registered database # ".$database_id.
            " The page may contain an invalid database reference (0 indicates no reference has been set)";
        }else{
            $database_url = $data['rec_URL'];
        }
    }

}else{
    //on this server

    $system2 = new hserv\System();
    $system2->init(HEURIST_INDEX_DATABASE, true, false);//init without paths and consts

    if(@$_REQUEST['remote']){
        $database_id = @$_REQUEST["id"];
    }
    $rec = array();
    if($database_id>0){

        ConceptCode::setSystem($system2);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        $rec = mysql__select_row_assoc($system2->getMysqli(),
            'select rec_Title, rec_URL from Records where rec_RecTypeID='
            .$rty_ID_registered_database.' and rec_ID='  //1-22
            .$database_id);

        if ($rec!=null){
            $database_url = @$rec['rec_URL'];
            if($database_url==null || $database_url==''){
                $error_msg = 'Database URL is not set in Heurist_Reference_Index database for database ID#'.$database_id;
            }

        }else{
            $err = $system2->getMysqli()->error;
            if(err){
                $error_msg = 'Heurist Reference Index database is not accessible at the moment. Please try later';
            }else{
                $error_msg = 'Database with ID#'.$database_id.' is not found in Heurist Reference Index';
            }
        }
    }else{
        $error_msg = 'Database ID is not set or invalid. It must be an interger positive value.';
    }

    if(@$_REQUEST['remote']) {
        header(CTYPE_JS);

        if(isset($error_msg)){
            $res = array('error_msg'=>$error_msg);
        }else{
            $res = array('rec_URL'=>$database_url);
        }
        print json_encode($res);
    }
}
?>