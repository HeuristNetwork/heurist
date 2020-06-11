<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *   database definitions - record types, record structure, field types, terms
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_structure.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_structure_tree.php');
    require_once (dirname(__FILE__).'/../structure/dbsImport.php');

    /* DEBUG
    $_REQUEST['db'] = 'dos_3';
    $_REQUEST['q'] = 'manly';
    */
    
    $response = array();
    $is_remote = false;
    
    //get list of registered database and master index db on the same server
    if(@$_REQUEST['remote']){
        
       $remoteURL = $_REQUEST['remote'];
       preg_match("/db=([^&]*).*$/", $remoteURL, $match);
        
       if(strpos($remoteURL, HEURIST_SERVER_URL)===0){ //same domain

            unset($_REQUEST['remote']);
            $_REQUEST['db'] = $match[1];
       }else{
           //load structure from remote server
                $splittedURL = explode('?', $remoteURL);
                   
                $remoteURL = $splittedURL[0].'hsapi/controller/sys_structure.php?db='.$match[1];

                if (@$_REQUEST['rectypes']) $remoteURL = $remoteURL.'&rectypes='.$_REQUEST['rectypes'];
                if (@$_REQUEST['detailtypes']) $remoteURL = $remoteURL.'&detailtypes='.$_REQUEST['detailtypes'];
                if (@$_REQUEST['mode']) $remoteURL = $remoteURL.'&mode='.$_REQUEST['mode'];

                $data = loadRemoteURLContent($remoteURL);            
            
                if($data==false){
                    //$system->addError(HEURIST_ERROR,  );
                    $data = array("status"=>HEURIST_ERROR, "message"=>'Cannot access database structure for database '
                                            .$match[1].' on '.$splittedURL[0], "sysmsg"=>null);
                    $data = json_encode($data); 
                }else{
                    header('Content-Encoding: gzip');
                }
                
                header('Content-type: application/json;charset=UTF-8');
                echo $data; 
                exit();                
                //$response = json_decode($data, true);
                //$is_remote = true;
                
       }
    }
    

    $mode = 0;
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){

        //get error and response
        $response = $system->getError();
        
    }else{
        
        if(@$_REQUEST["import"]){ //this is import
            if(!$system->is_admin()){
                $system->error_exit('To perform this action you must be logged in as '
                        .'Administrator of group \'Database Managers\'',
                        HEURIST_REQUEST_DENIED);
            }
            
            //combination of db and record type id eg. 1126-13            
            $code = @$_REQUEST['code']; //this is not concept code - these are source database and rectype id in it
            //concept code is unique for record type unfortunately it does not specify exactly what database is preferable as a source of donwloading
            
            $isOK = false;  
ini_set('max_execution_time', 0);
            $importDef = new DbsImport( $system );

            if($importDef->doPrepare(  array('defType'=>$_REQUEST["import"], 
                        'databaseID'=>@$_REQUEST["databaseID"], 
                        'definitionID'=>@$_REQUEST["definitionID"], 
                        'conceptCode'=>@$_REQUEST['conceptCode']) ))
            {
                $isOK = $importDef->doImport();
            }

            if(!$isOK){
                $system->error_exit(null);  //produce json output and exit script
            }
            $response = $importDef->getReport(true); //with updated definitions and sysinfo

            $response['status'] = HEURIST_OK;            
            
        }else{

            //$currentUser = $system->getCurrentUser();
            $data = array();

            if (@$_REQUEST['terms']) {
                $data["terms"] = dbs_GetTerms($system);
            }

            if (@$_REQUEST['detailtypes']) {
                $ids = $_REQUEST['detailtypes']=='all'?null:$_REQUEST['detailtypes'];
                $data["detailtypes"] = dbs_GetDetailTypes($system, $ids, intval(@$_REQUEST['mode']) );
            }

            
            if (@$_REQUEST['rectypes']) {
                $ids = $_REQUEST['rectypes']=='all'?null:$_REQUEST['rectypes'];
                $mode = intval(@$_REQUEST['mode']);

                if($mode>2){
                    $data["rectypes"] = dbs_GetRectypeStructureTree($system, $ids, $mode, @$_REQUEST['fieldtypes'], @$_REQUEST['parentcode']);
                }else{
                    $data["rectypes"] = dbs_GetRectypeStructures($system, $ids, $mode );
                }

            }else{
                $mode = 0;
            }

            if($mode==5){
                if(count($data["rectypes"])==1){
                    $response = $data["rectypes"][0]['children'];
                }else{
                    $response = $data["rectypes"];
                }
    /* verify this piece after merging 25-Jul-18
                if($mode==4 && @$_REQUEST['lazyload']){
                    if(count($data["rectypes"])==1){
                        $response = $data["rectypes"][0]['children'];
                    }else{
                        $response = $data["rectypes"];
                    }
    */
            }else{
                    $response = array("status"=>HEURIST_OK, "data"=> $data );
            }   
        
        }

    
    }            

    
    /*
    if ( extension_loaded('zlib') && (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) )
    {
            ob_start('ob_gzhandler');
    }*/    
/*
    ini_set("zlib.output_compression", 4096);
    ini_set("zlib.output_compression_level", 6);
    header('Content-type: text/javascript');
    print json_encode($response);
*/
    
ob_start(); 
echo json_encode($response);
$output = gzencode(ob_get_contents(),6); 
ob_end_clean(); 

$system->setResponseHeader();    
header('Content-Encoding: gzip');
echo $output; 
unset($output);   
?>