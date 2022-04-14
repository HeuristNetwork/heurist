<?php
/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* getDatabaseURL.php - requests URL for registered DB by its ID from Heurist Master Index
*
* this script may be inited via http, otherwise it is included and $database_id already defined
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once(dirname(__FILE__)."/../../../hsapi/System.php");

$isOutSideRequest = (strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===false);
if($isOutSideRequest){ //this is request from outside - redirect to master index    

    $reg_url = HEURIST_INDEX_BASE_URL 
    .'admin/setup/dbproperties/getDatabaseURL.php?db='.HEURIST_INDEX_DATABASE
    .'&remote=1&id='.$database_id;

    $data = loadRemoteURLContentSpecial($reg_url); //get registered database URL

    if (!$data) {
        $error_msg = "Unable to connect Heurist Master Index, possibly due to timeout or proxy setting<br /><br />".
        "URL requested: ".$reg_url;
    }else{

        $data = json_decode($data, true);

        // Artem TODO: What circumstance would give rise to this? Explain how the data is 'wrong'/'incorrect'
        // Artem: cannot connect to Heurist_Master_Index, Records table is corrupted, $database_id is not found 
        if(@$data['error_msg']){
            $error_msg = $data['error_msg'];
        }else if(!@$data['rec_URL']){
            $error_msg = "Heurist Master Index returns incorrect data for registered database # ".$database_id.
            " The page may contain an invalid database reference (0 indicates no reference has been set)";
        }else{
            $database_url = __replaceToLastServe($data['rec_URL']);
        }
    }

}else{
    //on this server

    $system2 = new System();
    $system2->init('hdb_Heurist_Master_Index', true, false); //init without paths and consts

    if(@$_REQUEST['remote']){ 
        $database_id = @$_REQUEST["id"];   
    }
    $rec = array();
    if($database_id>0){

        $rec = mysql__select_row_assoc($system2->get_mysqli(),
            'select rec_Title, rec_URL from Records where rec_RecTypeID='.HEURIST_INDEX_DBREC.' and rec_ID='  //22
            .$database_id);
        if ($rec!=null){
            $database_url = __replaceToLastServe(@$rec['rec_URL']);
            if($database_url==null || $database_url==''){
                $error_msg = 'Database URL is not set Heurist Master Index for database ID#'.$database_id;
            }
            //fix registration bug
            if(strpos($database_url,'http:///heuristplus')===0){
                $database_url = 'https://'.substr($database_url,8); 
            }else if(strpos($database_url,'http:///')===0){
                $database_url = 'http://'.substr($database_url,8); 
            }else if(strpos($database_url,'https:///')===0){
                $database_url = 'https://'.substr($database_url,9); 
            }
                
        }else{
            $err = $system2->get_mysqli()->error;
            if(err){
                $error_msg = 'Heurist Master Index database is not accessible at the moment. Please try later';
            }else{
                $error_msg = 'Database with ID#'.$database_id.' is not found in Heurist Master Index';    
            }
        }
    }else{
        $error_msg = 'Database ID is not set or invalid. It must be an interger positive value.';    
    }

    if(@$_REQUEST['remote']) {
        header('Content-type: text/javascript');

        if(isset($error_msg)){
            $res = array('error_msg'=>$error_msg);    
        }else{
            $res = array('rec_URL'=>$database_url);    
        }
        print json_encode($res);
    }
}

// Feb 2019: Redirect old calls to new server/address.
function __replaceToLastServe($url){
    if($url!=null){
        if(strpos($url, 'http://heurist.sydney.edu.au')!==false){
            $url = str_replace( 'http://heurist.sydney.edu.au', HEURIST_MAIN_SERVER, $url);
        }else if(strpos($url, 'http:///heuristplus.sydney.edu.au')!==false || strpos($url, 'http://heuristplus.sydney.edu.au')!==false){
            $url = str_replace( 'http://', 'https://', $url);    
        }
         
     }
    return $url;
}
?>