<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *   database definitions - record types, record structure, field types, terms
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    * @deprecated  since Heurist 6.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
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
    $remoteURL = null;
    
    //get list of registered database and master index db on the same server
    if(@$_REQUEST['remote']){
        
       $remoteURL = $_REQUEST['remote'];
       preg_match("/db=([^&]*).*$/", $remoteURL, $match);
        
       if(strpos($remoteURL, HEURIST_SERVER_URL)===0){ //same domain

            unset($_REQUEST['remote']);
            $_REQUEST['db'] = $match[1];
       }else{
                if(@$match[1]==null || $match[1]==''){
                     $data = __getErrMsg($remoteURL, HEURIST_ERROR, 'Cannot detect database parameter in registration URL');
                     $data = json_encode($data); 
                }else{
           
                    //load structure from remote server
                    $splittedURL = explode('?', $remoteURL);
                    
                    $remoteURL_original = $remoteURL;
                       
                    $remoteURL = $splittedURL[0].'hsapi/controller/sys_structure.php?db='.$match[1];

                    if (@$_REQUEST['rectypes']) $remoteURL = $remoteURL.'&rectypes='.$_REQUEST['rectypes'];
                    if (@$_REQUEST['detailtypes']) $remoteURL = $remoteURL.'&detailtypes='.$_REQUEST['detailtypes'];
                    if (@$_REQUEST['terms']) $remoteURL = $remoteURL.'&terms='.$_REQUEST['terms'];
                    if (@$_REQUEST['translations']) $remoteURL = $remoteURL.'&translations='.$_REQUEST['translations'];
                    if (@$_REQUEST['mode']) $remoteURL = $remoteURL.'&mode='.$_REQUEST['mode'];

                    $data = loadRemoteURLContent($remoteURL); //load defitions from remote database
                
                    if($data==false){
                        
                        //Server not found 
                        //No response from server
                        $data = __getErrMsg($remoteURL_original, $glb_curl_code, $remoteURL.' '.$glb_curl_error);
                        $data = json_encode($data); 
                    }else{
//$defs = json_decode(gzdecode($data), true);
                        
                        header('Content-Encoding: gzip');
                    }
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
        
        if($remoteURL!=null){
            
            //cannot connect to registered database on the same server
            $response = __getErrMsg($remoteURL, $response['status'], $response['message']);
            
        }
        
        
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
                        'is_rename_target'=>(@$_REQUEST["is_rename_target"]==1),
                        'conceptCode'=>@$_REQUEST['conceptCode']) ))
            {
                $isOK = $importDef->doImport();
            }

            if(!$isOK){
                $system->error_exit(null);  //produce json output and exit script
            }
            $response = $importDef->getReport(true); //with updated definitions and sysinfo

            $response['status'] = HEURIST_OK;            

            //
            // send error report about terms that were failed
            //            
            if(@$response['report']['broken_terms'] && count($response['report']['broken_terms'])>0){
                
                $sText = 'Target database '.HEURIST_DBNAME;
                $sText .= ("\n".'Source database '.@$_REQUEST["databaseID"]);
                $sText .= ("\n".count($response['report']['broken_terms']).' terms were not imported.');
                foreach($response['report']['broken_terms'] as $idx => $term){
                    $sText .= ("\n".print_r($term, true));
                    $sText .= ("\n reason: ".$response['report']['broken_terms_reason'][$idx]);
                }
                //$sText .= ('</ul>');
                
                sendEmail(HEURIST_MAIL_TO_BUG, 'Import terms report', $sText);
                
                $response['report']['broken_terms_reason'] = null;
            }
            if(@$response['report'] && $response['report']['rectypes']){

                $sText = 'Target database '.HEURIST_DBNAME;
                $sText .= ("<br>".'Source database '.@$_REQUEST["databaseID"]);
                $sText .= ('<table><tr><td colspan="2">source</td><td colspan="2">target</td></tr>'
                        .$response['report']['rectypes'].'</table>');
                        
                sendEmail(HEURIST_MAIL_TO_ADMIN, 'Download templates', $sText, true);
            }
            

        }else{

            //$currentUser = $system->getCurrentUser();
            $data = array();

            if (@$_REQUEST['translations']){
                $data['translations'] = dbs_GetTranslations($system, $_REQUEST['translations']);
            }

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
                
                    $data["db_version"] =  $system->get_system('sys_dbVersion').'.'
                                        .$system->get_system('sys_dbSubVersion');
                
                    $response = array("status"=>HEURIST_OK, "data"=> $data );
            }   
        
        }

        $system->dbclose();
    }            

    
    /*
    if ( extension_loaded('zlib') && (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) )
    {
            ob_start('ob_gzhandler');
    }*/    
/*
    ini_set("zlib.output_compression", '4096');
    ini_set("zlib.output_compression_level", '6');
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

function __getErrMsg($remoteURL, $code, $err_msg){

            if($code=='curl'){
                $reason = 'This may be due to a missing server or timeout on connection';     
            }else if($code==HEURIST_SYSTEM_FATAL){
                $reason = 'This may be due to an installation problem on the server';
            }else{
                $reason = 'This may be due to an error in the registration information recorded in the Heurist master index';
            }
    
            $message = '<h3>Unable to obtain database structure information</h3>'
            .'<p>We are unable to contact the selected source database</p>'
            .'<p>URL: <a href="'.$remoteURL.'">'.$remoteURL.'</a></p>'
            .'<p>Error returned: '.$err_msg.'</p><p>'
            .$reason
            .'</p>'
            .'<p>Please ask the owner of the database to correct registration information in the Heurist master index '
            .'(login to their database and use Design > Register to correct the information), or ask the system administrator '
            .'of the server in question to correct the installation. If the server/database no-longer exists please '
            .CONTACT_HEURIST_TEAM.' with the URL to the target database</p>';
            
            return array("status"=>HEURIST_ERROR, "message"=>$message, "sysmsg"=>null);
    
}
?>