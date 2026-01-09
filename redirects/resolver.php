<?php
/**
* resolver.php - Universal Heurist URL resolver and redirector.
* @fileOverview This script acts as a central resolver for various Heurist URLs, including PIDs (Persistent Identifiers),
* database views, record views, edits, and exports. It interprets incoming URL patterns and redirects
* to the appropriate internal script or resource. It can handle requests for different output formats (XML, HML, JSON, RDF, HTML).
* It also supports resolving records across different databases, potentially involving a lookup
* on a master Heurist index for remote databases.
*
* Key functionalities:
* - Resolves short URLs for databases, CMS pages, record views/edits.
* - Handles requests for specific output formats (e.g., `fmt=xml`).
* - Redirects to appropriate viewers or export scripts.
* - Supports resolving records with database IDs (e.g., `recID=DBID-RecordID`).
* - Manages special domain configurations (e.g., *.huma-num.fr).
*
* Note: Up to Dec 2015 (V4.1.3), resolver.php redirected to a human-readable form (viewRecord.php).
* From Jan 2016 (V4.1.4 onwards), it's intended to primarily return machine-consumable XML/HML renditions by default for PIDs,
* while still supporting human-readable views and other formats via parameters or specific URL patterns.
*
* @project     Heurist academic knowledge management system
* @package  Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4
*/

// Input is of the form .../redirects/resolver.php?db=mydatabase&recID=3456

// TODO: future form accepting recID=123-3456 which redirects to record 3456 on database 123.
//       This will require quizzing the central Heurist index to find out the location of database 123.
//       The location of database 123 should then be cached so it does not require a hit on the
//       master index server for every record. By proceeding in this way, every Heurist database
//       becomes a potential global resolver.

// Redirect to .../records/view/viewRecordAsXML.php (TODO:)
// TODO: write /redirects/resolver.php as an XML feed with parameterisation for a human-readable view
// TODO: the following is a temporary redirect to viewRecord.php which renders a human-readable form

// NOTE: THIS HAS BEEN SUBSTANTIALLY DEVELOPED AND IS NOW DOCUMENTED IN /server_management/utility/apache_configurations.txt
// Add to httpd.conf
// RewriteRule ^/heurist/([A-Za-z0-9_-]+)/(website|web|tpl|hml|view)/(.*)$ /heurist/redirects/resolver.php
// redirection for CMS, Smarty, hml output and record view
// website or web - cms website
// tpl - smarty
// hml - xml output
// view - record view
// edit - record edit
// adm - main admin ui
//  heurist/database_name/action/param1/param2
//
// special case for dicobiosport.huma-num.fr and privileges.huma-num.fr
//

//error_log($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'resovler'));

$requestUri = str_replace( '?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI'] );

$requestUri = explode('/', trim($requestUri,'/'));
$allowedActions = array('website','web','hml','tpl','view','edit','adm');
$requestContent = array('xml'=>'text/xml',
                        'hml'=>'application/hml+xml',
                        'json'=>'application/json',
                        'rdf'=>'application/rdf+xml',
                        'html'=>'text/html');

$allowedVersions = ['heurist','h6-alpha','h7-alpha','h7-hn'];
                        
$format = null;
$redirection_path = '../';

$is_own_domain = (strpos($_SERVER["SERVER_NAME"],'.huma-num.fr')>0 && 
                    !($_SERVER["SERVER_NAME"]=='heurist.huma-num.fr' ||
                      $_SERVER["SERVER_NAME"]=='heurist2025.huma-num.fr'));

                      
//echo  $_SERVER["SERVER_NAME"].'  '.$is_own_domain.'  '.$requestUri;
//exit;
                      
if($is_own_domain){
    //'dicobiosport' and 'privileges'
    //detect databasename
    $database_name_from_domain = substr($_SERVER["SERVER_NAME"],0,-12);//remove .huma-num.fr
    //replace hyphens with underscores
    $database_name_from_domain = str_replace('-','_',$database_name_from_domain);
    
    if(!in_array($database_name_from_domain, $requestUri)){
        array_unshift($requestUri, $database_name_from_domain); //add to beginning of array
    }
}

// --------------- INDEX or STARTUP
if(count($requestUri)==1 && in_array($requestUri[0], $allowedVersions)){
    //redirectURL2('/'.rawurlencode($requestUri[0]).'/index.php');
    include_once '../index.php';  //root index that goes to startup
    exit;

}elseif ($requestUri[0]=='startup' || @$requestUri[1]=='startup') {
    include_once '../startup/index.php';
    exit;

}
/*elseif (count($requestUri)==1){
    
    $dbname = filter_var($requestUri[0]);
    if(!preg_match('/[^A-Za-z0-9_\-\$]/', $dbname)){
        //include_once '../startup/index.php';
        redirectURL2('/'.rawurlencode($dbname).'/web/');
        exit;
    }
    
}*/
elseif (!( ($requestUri[0]=='db') 
    || (in_array($requestUri[0], $allowedVersions) && (@$requestUri[1]=='viewers' || @$requestUri[1]=='redirects'))
    || (count($requestUri)>1 && (in_array($requestUri[1], $allowedActions) || in_array(@$requestUri[2], $allowedActions)))
    ))
{
    
    $idx = in_array($requestUri[0], $allowedVersions)?1:0; // heurst/dbname/action
    $dbname = filter_var(@$requestUri[$idx]);
     
    if(!preg_match('/[^A-Za-z0-9_\-\$]/', $dbname)){
        //include_once '../startup/index.php';
        redirectURL2('/'.rawurlencode($dbname).'/web/');
        exit;
    }
}

$isMediaRequest = false;

// --------------- RECORD VIEW   in format db/record/[rec-id] 

// Define rules in apache config file
//
// RewriteRule /db/(record|rec|rty|dty|trm)/([0-9-]+\/?)([0-9]+)?$ /heurist/redirects/resolver.php
// RewriteRule /db/file/([a-z0-9-]+\/?)$ /heurist/redirects/resolver.php
// 

// Universal record/definion resolver. Record id is concept code DBID+RECORD ID.
// It returns in format specified by Content-type or by parameter fmt=

// db/record/2312-123  or  db/record/2312-123.rdf  or db/record/123?db=somedb&fmt=rdf  
// /db/file/2-26adca36f8e375ae0400b91c124aa04084c22e1e
// 
// /db/record/1376-11613  or /db/record/1376-11613?fmt= 'hml','xml','json','rdf','gephi','geojson','iiif'
// /db/rty/1376/10
if( (count($requestUri)==3 || count($requestUri)==4)
    && $requestUri[0]=='db'
    && in_array($requestUri[1], array('record','rec','rty','dty','rst','trm','file')) ) 
{
    //redirect to record info
    if(count($requestUri)==4){
        $recID = $requestUri[2].'-'.$requestUri[3];
    }else{
        $recID = $requestUri[2];    
    }
    

    if(strpos($recID,'?')>0){
        list($recID, $query) = explode('?',$recID);
    }
    if(strpos($recID,'.')>0){//not used
        list($recID, $format) = explode('.',$recID);
    }

    if(@$_REQUEST['fmt']==null){
        // take format from Accept or Content-typee
        //Accept: text/html, application/xhtml+xml
        //Accept: application/rdf+xml;q=0.7, text/html
        if($format==null){

            $contentType = @$_SERVER["HTTP_ACCEPT"];
            if($contentType){
                foreach ($requestContent as $fmt=>$mimeType){
                    if(strpos($contentType, $mimeType)!==false){
                        $format = $fmt;
                        break;
                    }
                }
            }
        }

        $_REQUEST['fmt'] = $format;
    }
    

    $redirection_path = '../../heurist/';

    $isMediaRequest = ($requestUri[1]=='file');
    
    if(in_array($requestUri[1], array('rty','dty','rst','trm'))){
        $_REQUEST[$requestUri[1]] = $recID;    
    }else{
        $_REQUEST['recID'] = $recID;    
    }
    
// --------------- 
    
}elseif(count($requestUri)>1 && (in_array($requestUri[1],$allowedActions) || in_array(@$requestUri[2],$allowedActions))){
                             // dbname/action                               heurist/dbname/action

/*
To enable this redirection add to httpd.conf

RewriteEngine On
#if URI starts with web/ redirect it to redirects/resolver.php
RewriteRule ^heurist/web/(.*)$ /heurist/redirects/resolver.php
RewriteRule ^web/(.*)$ /heurist/redirects/resolver.php

https://HeuristRef.net/johns_test_63/web/1463/2382
→ https://heuristref.net/heurist/?db=johns_test_063&website=1463&pageid=2382

The IDs for the website and the pageid are optional, so in most cases, w
here the website is the first or only one for the database,
all that is needed is the database name like this:

https://HeuristRef.net/johns_test_63/web

$requestUri:
0 - "heurist"
1 - database
2 - "web"
3 - website id
4 - page id
*/
    if(in_array($requestUri[1],$allowedActions)){
       array_unshift($requestUri, 'heurist');//not used
    }

    $error_msg = null; //mysql__check_dbname($requestUri[1]);
    if($requestUri[1]=='' || preg_match('/[^A-Za-z0-9_\-\$]/', $requestUri[1])){
        $error_msg = 'Database parameter is wrong';
    }
    $params = array();

    if($error_msg==null){

        $database = filter_var($requestUri[1]);
        $action = filter_var($requestUri[2]);
        $redirect = '';

        if($database=='MBH'){ //special case for MBH project, Elodie Attia, U. Aix-Maseeille on Huma-Num
            $database='MBH_Manuscripta_Bibliae_Hebraicae';
        }elseif ($database=='BEnum'){ //special case for Association des Études Grecques, Revue des études grecques on Huma-Num
            $database='BE_Bulletin_Epigraphique';
        }elseif(in_array($database, $allowedVersions)){
            redirectURL2('/'.rawurlencode($database).'/index.php');
            exit;
        }

        $params['db'] = $database;

        require_once '../configIni.php';
        require_once '../hserv/utilities/USystem.php';
        $host_params = hserv\utilities\USystem::getHostParams();

        if($action=='web' || $action=='website'){

            define('PDIR', $host_params['baseURL']);
            
            $redirect .= '?db='.$database.'&website';

            $params['db'] = $database;    
            
            if(intval(@$requestUri[3])>0){
                $redirect .= '&website='.intval($requestUri[3]);
                $params['website'] = intval($requestUri[3]);
            }else{
                $params['website'] = '0'; //default website
            }
            if(intval(@$requestUri[4])>0) { //it may be both website pageid and record id
                $redirect .= '&pageid='.intval($requestUri[4]);
                $params['pageid'] = intval($requestUri[4]);
            }
            if(@$_REQUEST['edit']){
                $params['edit'] = $_REQUEST['edit'];    
            }
            if(@$_REQUEST['ver']){
                $params['ver'] = $_REQUEST['ver'];    
            }
            if(@$_REQUEST['header']){
                $params['header'] = $_REQUEST['header'];    
            }
            if(@$_REQUEST['newlycreated']){
                $params['newlycreated'] = $_REQUEST['newlycreated'];    
            }
            
            //$_SERVER["REQUEST_URI"] = $host_params['install_dir'];
            $rewrite_path = dirname(__FILE__).'/../index.php';

        }
        else {
            require_once dirname(__FILE__).'/../hserv/dbaccess/utils_db.php';

            $redirect = $host_params['baseURL'];

            if($action=='view' || $action=='edit'){

                if(array_key_exists('privateDetails', $_REQUEST)){
                    $parts = explode('?', $requestUri[3]); // remove query from record viewer link
                    $requestUri[3] = $parts[0];
                    $params['privateDetails'] = $_REQUEST['privateDetails']; // add privateDetails to parameters
                }

                if(@$requestUri[3] && ctype_digit($requestUri[3]) && $requestUri[3]>0){
                    $redirect .= ("viewers/record/viewRecord.php?db=$database&recID=".intval($requestUri[3]));
                    $params['recID'] = intval($requestUri[3]);

                    if($action=='view'){
                        $rewrite_path = dirname(__FILE__).'/../viewers/record/viewRecord.php';
                    }else{
                        define('PDIR', $host_params['baseURL']);
                        $rewrite_path = dirname(__FILE__).'/../hclient/framecontent/recordEdit.php';
                    }

                }else{
                    $error_msg = 'Record ID is not defined';
                }

            }
            elseif($action=='hml'){

                //example: https://example.org/heurist/dbname/hml/18/1

                if(@$requestUri[3]){
                    $redirect .= ('export/xml/flathml.php?db='.$database.'&w=a&q=');

                    $ids = prepareIds(@$requestUri[3]);

                    if(!empty($ids)){
                        $redirect .= ('ids:'.implode(',',$ids)); //$requestUri[3]);
                    }else{
                        $redirect .= rawurlencode($requestUri[3]);
                    }

                    if(@$requestUri[4]!=null && ctype_digit($requestUri[4]) && $requestUri[4]>=0){
                        $redirect .= ('&depth='.intval($requestUri[4]));
                    }else{
                        $redirect .= '&depth=1';
                    }

                }else{
                    $error_msg = 'Query or Record ID is not defined';
                }

            }
            elseif($action=='adm'){  //admin user interface

                $redirect = $redirect.'?db='.$database;
                $params['db'] = $database;

                $query = null;
                if(@$requestUri[3]){
                    $ids = prepareIds(@$requestUri[3]);
                    if(!empty($ids)){
                        $query = ('ids:'.$requestUri[3]);
                    }else{
                        $query = urldecode($requestUri[3]);
                    }
                    $params['w'] = 'a';
                    $params['q'] = $query;

                    $redirect = $redirect.'&q='.$query;
                }
                
                // in case to keep url as   domain/database/adm remove these remarks
                //define('PDIR', $host_params['baseURL']);
                //$rewrite_path = dirname(__FILE__).'/../index.php';
            }
            elseif($action=='tpl'){ // call FrontController from index.php
            
                //   /tpl/template-name/query    /tpl/person-all/t:10 or /tpl/person-view/123
            
                $query = null;

                if(@$requestUri[3]){

                    if(@$requestUri[4]){

                        $redirect .= ('?db='.$database.'&template='.basename($requestUri[3]).'&w=a&q=');

                        $ids = prepareIds(@$requestUri[4]);
                        if(!empty($ids)){
                            $query = ('ids:'.$requestUri[4]);
                        }else{
                            $query = $requestUri[4];
                        }
                        $redirect .= $query;

                        $params['w'] = 'a';
                        $params['q'] = urldecode($query);
                        $params['template'] = urldecode($requestUri[3]);
                        $rewrite_path = dirname(__FILE__).'/../index.php';

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
    }elseif(isset($rewrite_path)){
        $_REQUEST = $params;
        include_once $rewrite_path;
        exit;
    }

    redirectURL2($redirect);
    exit;

}
elseif(count($requestUri)>2
      && ( in_array($requestUri[0], $allowedVersions) ) 
      && $requestUri[1]=='viewers'){
    //Redirects to index page for viewers plugins
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);
    redirectURL2('/'.filter_var($requestUri[0]).'/'.$requestUri[1].'/'.$requestUri[2].'/index.php?'.$query_string);
    exit;

}

//alowed
if(@$_REQUEST['fmt']){
    $format = filter_var($_REQUEST['fmt'], FILTER_SANITIZE_STRING);
}elseif(@$_REQUEST['format']){
    $format = filter_var($_REQUEST['format'], FILTER_SANITIZE_STRING);
}else{
    $format = ($isMediaRequest)?'html':'hml';
}

$entity = null;
$recid = null;
$database_id = 0;

if(@$_REQUEST['recID'] || @$_REQUEST['recid'] || @$_REQUEST['id']){

    $recid = $_REQUEST['recID']??($_REQUEST['recid']??$_REQUEST['id']);

}elseif (@$_REQUEST['rty'] || @$_REQUEST['dty'] || @$_REQUEST['trm']){

    if(@$_REQUEST['rty']) {$entity = 'rty';}
    elseif(@$_REQUEST['dty']) {$entity = 'dty';}
    elseif(@$_REQUEST['trm']) {$entity = 'trm';}

    $recid = filter_var($_REQUEST[$entity], FILTER_SANITIZE_STRING);
    $format = 'xml';
}

//form accepting recID=123-3456 which redirects to record 3456 on database 123
if($recid!=null){
    if(strpos($recid, '-')>0){
        list($database_id, $recid) = explode('-', $recid, 2);
        $database_id = intval($database_id);

    }else{
        if (is_int(@$_REQUEST['db'])){
            $database_id = intval($_REQUEST['db']);
        }
    }
}

if($isMediaRequest){
    if($recid==null){
        redirectURL2($redirection_path.'hclient/framecontent/infoPage.php?error=File ID is not defined');
        exit;
    }
}else{
    $recid = intval($recid);
    if(!($recid>0)){
        redirectURL2($redirection_path.'hclient/framecontent/infoPage.php?error=Record ID is not defined');
        exit;
    }
}

$database_url = null;

if ($database_id>0) {
    include_once dirname(__FILE__).'/../hserv/utilities/DbRegis.php';

    require_once dirname(__FILE__).'/../autoload.php';
    
    if(!isset($system)){
        $system = new hserv\System();//to keep error
    }

    $database_url = hserv\utilities\DbRegis::registrationGet(array('dbID'=>$database_id));
    if(!$database_url){
        $err = $system->getError();
        $error_msg = @$err['message'];
        redirectURL2($redirection_path.'hclient/framecontent/infoPage.php?error='.rawurlencode($error_msg));
        exit;
    }
}


//allowed formats
// for definitions
//      xml - xml template
// for records
//      web, website  - redirect ot website - TO BE REMOVED
//      edit - redirect to edit
//      hml (default)
//      xml - record_output
//      html
//      json
//      rdf
$database = @$_REQUEST['db'];

if($database_url!=null){ //redirect to resolver for another database
    if($entity!=null){
        $redirect = $database_url.'&'.$entity.'='.$recid;

    }elseif($isMediaRequest){

        $redirect = $database_url.'&file='.$recid;

        if($format=='html'){
            $redirect .= '&mode=page';
        }

    }else{
        $redirect = "$database_url&recID=$recid&fmt=$format";
    }
    $redirection_path = '';
}elseif($entity!=null){

    $redirect = "hserv/structure/export/getDBStructureAsXML.php?db=$database&$entity=$recid";

}elseif($isMediaRequest){

    $redirect = "?db=$database&mode=page&file=$recid";

}elseif($format=='html'){ //recirect to recordView

    if(@$_REQUEST['noheader']){
        $redirect = "viewers/record/renderRecordData.php?db=$database&noheader=1&recID=$recid";
    }else{
        $redirect = "viewers/record/viewRecord.php?db=$database&recID=$recid";
    }


}elseif($format=='web' || $format=='website'){ //redirect to website  - deprecated to be removed

    $redirect = "hclient/widgets/cms/websiteRecord.php?db=$database&recID=$recid";
    if(@$_REQUEST['field']>0){
        $redirect = $redirect.'&field='.$_REQUEST['field'];
    }

}elseif($format=='edit'){  //redirect to record edit

    //todo include resolver recordSearchReplacement
    $redirect = 'hclient/framecontent/recordEdit.php?'.$_SERVER['QUERY_STRING'];

}elseif(@$_REQUEST['db']){

    if(in_array($format, array('xml','json','rdf','gephi','geojson','iiif'))){

        $redirect = 'hserv/controller/record_output.php?vers=2&fmt='.$format;

    }else{
        //by default hml
        $redirect = 'export/xml/flathml.php?w=a';
    }

    $redirect .= '&db='.$_REQUEST['db'].'&q=ids:'.$recid;

    if(@$_REQUEST['depth']>0){
        $redirect .= '&depth='.intval($_REQUEST['depth']);
    }
}else{
    if(!isset($error_msg)){
        $error_msg = 'Can\'t resolve the given URI: '.$_SERVER['REQUEST_URI'];
    }
    $redirect = 'hclient/framecontent/infoPage.php?error='.rawurlencode($error_msg);
}

redirectURL2($redirection_path.$redirect);

/**
 * Performs a header redirect to the specified URL.
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function redirectURL2($url){
    if(isset($_REQUEST['debug'])){
        echo $url;
        exit;
    }
    header('Location: '.$url);
}
?>
