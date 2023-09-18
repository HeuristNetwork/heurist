<?php

/**
*
* resolver.php
* Acts as a PID redirector to an XML rendition of the record (database on current server).
* Future version will resolve to remote databases via a lookup on the Heurist master index
* and caching of remote server URLs to avoid undue load on the Heurist master index.
*
* Note: up to Dec 2015 V4.1.3, resolver.php redirected to a human-readable form, viewRecord.php
*       from Jan 2016 V4.1.4, resolver.php is intended to return a machine consumable XML rendition
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
// Input is of the form .../redirects/resolver.php?db=mydatabase&recID=3456

// TODO: future form accepting recID=123-3456 which redirects to record 3456 on database 123.
//       This will require qizzing the central Heurist index to find out the location of database 123.
//       The location of database 123 should then be cached so it does not require a hit on the
//       master index server for every record. By proceeding in this way, every Heurist database
//       becomes a potential global resolver.
// Redirect to .../records/view/viewRecordAsXML.php (TODO:)
// TODO: write /redirects/resolver.php as an XML feed with parameterisation for a human-readable view
// TODO: the following is a temporary redirect to viewRecord.php which renders a human-readable form

// Add to httpd.conf
// RewriteRule ^/heurist/([A-Za-z0-9_]+)/(web|tpl|hml|view)/(.*)$ /heurist/redirects/resolver.php
//redirection for CMS, Smarty, hml output and record view
// web - cms website
// tpl - smarty
// hml - xml output
// view - record view
// edit - record edit
// adm - main admin ui
//  heurist/database_name/action/param1/param2
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'],'/'));
$allowedActions = array('web','hml','tpl','view','edit','adm');

//print $_SERVER['REQUEST_URI'];
//print print_r($requestUri,true);
//exit();


/*
if(count($requestUri)==1){
   if($requestUri[0]==='heurist'){
       header('Location: ../index.php');
       exit();
   }else{
       $_SERVER["SCRIPT_NAME"] .= '/web/';
       array_push($requestUri, 'web');
   }
}
http://127.0.0.1/heurist/MBH
*/
if(count($requestUri)==1 && ($requestUri[0]=='heurist' || $requestUri[0]=='h6-alpha')){

    header('Location: /'.filter_var($requestUri[0]).'/index.php');  
    exit();
    
}else if ((count($requestUri)==1)
     || 
    (count($requestUri)==2 && (!in_array($requestUri[1],$allowedActions) || $requestUri[1]=='startup'))
    )
{ //&& (@$requestUri[0]=='MBH' || @$requestUri[0]=='johns_test_BnF')){
    $dbname = filter_var((count($requestUri)==1)?$requestUri[0]:$requestUri[1]); //to avoid "Open redirect" security report
    
    if($dbname=='startup'){
        header('Location: /'.filter_var($requestUri[0]).'/startup/index.php');  
        exit();
    }else
    if(!preg_match('/[^A-Za-z0-9_\$]/', $dbname)){
        header('Location: /'.$dbname.'/web/');  
        exit();
    }
}


                           // dbname/action                               heurist/dbname/action
if(count($requestUri)>1 && (in_array($requestUri[1],$allowedActions) || in_array(@$requestUri[2],$allowedActions))){
/*
To enable this redirection add to httpd.conf

RewriteEngine On
#if URI starts with web/ redirect it to redirects/resolver.php
RewriteRule ^heurist/web/(.*)$ /heurist/redirects/resolver.php
RewriteRule ^web/(.*)$ /heurist/redirects/resolver.php

https://HeuristRef.net/web/johns_test_63/1463/2382     
→ https://heuristref.net/heurist/?db=johns_test_063&website&id=1463&pageid=2382         

The IDs for the website and the pageid are optional, so in most cases, w
here the website is the first or only one for the database, 
all that is needed is the database name like this:

https://HeuristRef.net/web/johns_test_63

$requestUri:
0 - "heurist"
1 - "web" 
2 - database
3 - website id
4 - page id
*/    
    if(in_array($requestUri[1],$allowedActions)){
       array_unshift($requestUri, 'heurist');//not used
    }                           

    $error_msg = null; //System::dbname_check($requestUri[1]);
    if($requestUri[1]=='' || preg_match('/[^A-Za-z0-9_\$]/', $requestUri[1])){
        $error_msg = 'Database parameter is wrong';
    }
    $params = array();
        
    if($error_msg==null){
        
        $database = filter_var($requestUri[1]);
        $action = filter_var($requestUri[2]);
        $redirect = '';
        
        if($database=='MBH'){
            $database='MBH_Manuscripta_Bibliae_Hebraicae';
        }else if($database=='heurist' || $database=='h6-alpha'){
            header('Location: /'.$database.'/index.php');  
            exit();
        }

        $params['db'] = $database;
        
        require_once ('../hsapi/utilities/utils_host.php');
        $host_params = getHostParams();
        
        if($action=='web' || $action=='website'){
            
            $redirect .= '?db='.$database.'&website';
                        //substr($_SERVER['SCRIPT_URI'],0,strpos($_SERVER['SCRIPT_URI'],$requestUri[0]))
                        //.$requestUri[0].'/?website&db='.$requestUri[2];
            $params['website'] = 1;
                        
            if(intval(@$requestUri[3])>0){
                $redirect .= '&id='.intval($requestUri[3]);    
                $params['id'] = intval($requestUri[3]);
            } 
            if(intval(@$requestUri[4])>0) { //it may be both website pageid and record id
                $redirect .= '&pageid='.intval($requestUri[4]);    
                $params['pageid'] = intval($requestUri[4]);
            }
            $_SERVER["REQUEST_URI"] = $host_params['install_dir']; //'/heurist/';
            
            define('PDIR', $host_params['server_url'] . $host_params['install_dir']);
            
            $rewrite_path = '../index.php';

        }else {
            require_once ('../hsapi/dbaccess/utils_db.php');
            
            $redirect = $host_params['server_url'] . $host_params['install_dir'];
        
            if($action=='view' || $action=='edit'){
            
                if(@$requestUri[3] && ctype_digit($requestUri[3]) && $requestUri[3]>0){
                    $redirect .= ('viewers/record/viewRecord.php?db='.$database.'&recID='.intval($requestUri[3]));
                    $params['recID'] = intval($requestUri[3]);
                    
                    if($action=='view'){
                        $rewrite_path = '../viewers/record/viewRecord.php';    
                    }else{
                        define('PDIR', $host_params['server_url'] . $host_params['install_dir']);
                        $rewrite_path = '../hclient/framecontent/recordEdit.php';
                    }
                    
                }else{
                    $error_msg = 'Record ID is not defined';
                }

            }else if($action=='hml'){
          
        // http://127.0.0.1/heurist/osmak_9c/hml/18/1

                if(@$requestUri[3]){
                    $redirect .= ('export/xml/flathml.php?db='.$database.'&w=a&q=');

                    $ids = prepareIds(@$requestUri[3]);
                    
                    //if(ctype_digit($requestUri[3]) && $requestUri[3]>0){
                    if(count($ids)>0){
                        $redirect .= ('ids:'.$requestUri[3]);    
                    }else{
                        $redirect .= $requestUri[3];     
                    }
                    
                    if(@$requestUri[4]!=null && ctype_digit($requestUri[4]) && $requestUri[4]>=0){
                        $redirect .= ('&depth='.intval($requestUri[4]));         
                    }else{
                        $redirect .= '&depth=1';     
                    }
                    
                }else{
                    $error_msg = 'Query or Record ID is not defined';
                }

            }else if($action=='adm'){

                $redirect = $redirect.'?db='.$database;

                $query = null;
                if(@$requestUri[3]){
                    $ids = prepareIds(@$requestUri[3]);
                    //if(ctype_digit($requestUri[4]) && $requestUri[4]>0){
                    if(count($ids)>0){
                        $query = ('ids:'.$requestUri[3]);    
                    }else{
                        $query = urldecode($requestUri[3]);     
                    }
                    $params['w'] = 'a';
                    $params['q'] = $query;
                    
                    $redirect = $redirect.'&q='.$query;
                }
                //define('PDIR', $host_params['server_url'] . $host_params['install_dir']);    
                //$rewrite_path = '../index.php';
                
            }else if($action=='tpl'){
                
        //http://127.0.0.1/heurist/osmak_9c/tpl/Basic%20(initial%20record%20types)/t:10        
                $query = null;
        
                if(@$requestUri[3]){
                
                    if(@$requestUri[4]){ 
                        
                        $redirect .= ('viewers/smarty/showReps.php?db='.$database.'&template='.basename($requestUri[3]).'&publish=1&w=a&q=');
                        
                        $ids = prepareIds(@$requestUri[4]);
                        //if(ctype_digit($requestUri[4]) && $requestUri[4]>0){
                        if(count($ids)>0){
                            $query = ('ids:'.$requestUri[4]);    
                        }else{
                            $query = $requestUri[4];     
                        }
                        $redirect .= $query;     

                        $params['w'] = 'a';
                        $params['q'] = urldecode($query);
                        $params['template'] = urldecode($requestUri[3]);
                        $rewrite_path = '../viewers/smarty/showReps.php';
                        
                    }else{
                        $error_msg = 'Query or Record ID is not defined';
                    }
                    
                }else{
                    $error_msg = 'Template is not defined';
                }
                
            }
        }
    }
    
    if($error_msg){
       $redirect .= ('/hclient/framecontent/infoPage.php?error='.rawurlencode($error_msg)); 
    }else if($rewrite_path){
        $_REQUEST = $params;
        include $rewrite_path;
        exit();
    }
    
    header('Location: '.$redirect);  
    exit();

}


if(@$_REQUEST['fmt']){
    $format = filter_var($_REQUEST['fmt'], FILTER_SANITIZE_STRING);    
}elseif(@$_REQUEST['format']){
    $format = filter_var($_REQUEST['format'], FILTER_SANITIZE_STRING);        
}else{
    $format = 'xml';
}
$entity = null;
$recid = null;         
$database_id = 0;

if(@$_REQUEST['recID']){
    $recid = intval($_REQUEST['recID']);    
}else if(@$_REQUEST['recid']){
    $recid = intval($_REQUEST['recid']);        
}else if (@$_REQUEST['rty'] || @$_REQUEST['dty'] || @$_REQUEST['trm']){
    
    if(@$_REQUEST['rty']) $entity = 'rty';
    else if(@$_REQUEST['dty']) $entity = 'dty';
    else if(@$_REQUEST['trm']) $entity = 'trm';
    
    $recid = filter_var($_REQUEST[$entity], FILTER_SANITIZE_STRING);
    $format = 'xml';

    if(strpos($recid, '-')>0){    
        $vals = explode('-', $recid);
        if(count($vals)==3){
            $database_id = intval($vals[0]);
            $recid = intval($vals[1]).'-'.intval($vals[2]);
        }
    }
    
}

//form accepting recID=123-3456 which redirects to record 3456 on database 123
if(!$entity && strpos($recid, '-')>0){
    list($database_id, $recid) = explode('-', $recid, 2);
}else if (is_int(@$_REQUEST['db'])){
    $database_id = intval($_REQUEST['db']);
}

$database_url = null;    

if ($database_id>0) {
    
    $to_include = dirname(__FILE__).'/../admin/setup/dbproperties/getDatabaseURL.php';
    if (is_file($to_include)) {
        include $to_include;
    }
    
    if(isset($error_msg)){
        header('Location:../hclient/framecontent/infoPage.php?error='.rawurlencode($error_msg));
        exit();
    }

}

if($database_url!=null){ //redirect to resolver for another database
    if($entity!=null){
        $redirect = $database_url.'&'.$entity.'='.$recid;        
    }else{
        $redirect = $database_url.'&recID='.$recid.'&fmt='.$format;    
    }
}else if($entity!=null){
    
    $redirect = '../admin/describe/getDBStructureAsXML.php?db='.$_REQUEST['db'].'&'.$entity.'='.$recid;
    
}else if($format=='html'){
    
    if(@$_REQUEST['noheader']){
        $redirect = '../viewers/record/renderRecordData.php?db='
            .$_REQUEST['db'].'&noheader=1&recID='.$recid;    
    }else{
        $redirect = '../viewers/record/viewRecord.php?db='.$_REQUEST['db'].'&recID='.$recid;    
    }
    
    
}else if($format=='web' || $format=='website'){
    
    $redirect = '../hclient/widgets/cms/websiteRecord.php?db='.$_REQUEST['db'].'&recID='.$recid;
    if(@$_REQUEST['field']>0){
        $redirect = $redirect.'&field='.$_REQUEST['field'];    
    }
    
}else if($format=='edit'){
    //todo include resolver recordSearchReplacement
    $redirect = '../hclient/framecontent/recordEdit.php?'.$_SERVER['QUERY_STRING'];
}else{
    //todo include resolver  recordSearchReplacement
    $redirect = '../export/xml/flathml.php?db='.$_REQUEST['db'].'&depth=1&w=a&q=ids:'.$recid;
}

header('Location: '.$redirect);
return;
?>
