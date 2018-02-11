<?php
//MOVE TO H4

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
    //if(!defined('NO_DB_ALLOWED')) define('NO_DB_ALLOWED',1);
    if(!defined('ISSERVICE')) define('ISSERVICE',1);

    require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
    require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');

    $isOutSideRequest = (strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===false);
    if($isOutSideRequest){ //this is request from outside - redirect to master index    

        require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
    
        $reg_url =   HEURIST_INDEX_BASE_URL . "admin/setup/dbproperties/getDatabaseURL.php?db=Heurist_Master_Index&remote=1&id=".$database_id;

        $data = loadRemoteURLContentSpecial($reg_url);

        if (!$data) {
            $error_msg = "Unable to contact Heurist Master Index, possibly due to timeout or proxy setting<br /><br />".
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
                $database_url = $data['rec_URL'];
                
                // Artem: FOR heurist.sydney.edu.au change to latest h4-ao
                if(strpos($database_url, 'heurist.sydney.edu.au/h3/')>0){
                        $database_url = str_replace( 'heurist.sydney.edu.au/h3/', 'heurist.sydney.edu.au/h4-ao/', $database_url);
                }

            }
        }
       
    }else{
        mysql_connection_insert("hdb_Heurist_Master_Index");

        if(@$_REQUEST['remote']){
            $database_id = @$_REQUEST["id"];   
        }
        $rec = array();
        if($database_id>0){

            $res = mysql_query("select rec_Title, rec_URL from Records where rec_RecTypeID=22 and rec_ID=".$database_id);
            if ($res){
                $rec = mysql_fetch_assoc($res);
                $database_url = @$rec['rec_URL'];
                if($database_url==null || $database_url==''){
                    $error_msg = 'Database URL is not set Heurist Master Index for database ID#'.$database_id;
                }else{
                    // Artem: FOR heurist.sydney.edu.au change to latest h4-ao
                    if(strpos($database_url, 'heurist.sydney.edu.au/h3/')>0){
                        $database_url = str_replace( 'heurist.sydney.edu.au/h3/', 'heurist.sydney.edu.au/h4-ao/', $database_url);
                    }
                }
            }else{
                $error_msg = 'Database with ID#'.$database_id.' is not found in Heurist Master Index';
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
?>