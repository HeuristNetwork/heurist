<?php

/**
*
* resolver.php     (developed 2015)
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
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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
//       This will require quizzing the central Heurist index to find out the location of database 123.
//       The location of database 123 should then be cached so it does not require a hit on the
//       master index server for every record. By proceeding in this way, every Heurist database
//       becomes a potential global resolver.

// Redirect to .../records/view/viewRecordAsXML.php (TODO:)
// TODO: write /redirects/resolver.php as an XML feed with parameterisation for a human-readable view
// TODO: the following is a temporary redirect to viewRecord.php which renders a human-readable form

// NOTE: THIS HAS BEEN SUBSTANTIALLY DEVELOPED AND IS NOW DOCUMENTED IN /server_scripts/utility/apache_configurations.txt
// Add to httpd.conf
// RewriteRule ^/heurist/([A-Za-z0-9_]+)/(website|web|tpl|hml|view)/(.*)$ /heurist/redirects/resolver.php
// redirection for CMS, Smarty, hml output and record view
// website or web - cms website
// tpl - smarty
// hml - xml output
// view - record view
// edit - record edit
// adm - main admin ui
//  heurist/database_name/action/param1/param2
//
// special case for dicobiosport.huma-num.fr
//

$requestUri = explode('/', trim($_SERVER['REQUEST_URI'],'/'));
$allowedActions = array('website','web','hml','tpl','view','edit','adm');
$requestContent = array('xml'=>'text/xml',
                        'hml'=>'application/hml+xml',
                        'json'=>'application/json',
                        'rdf'=>'application/rdf+xml',
                        'html'=>'text/html');

$format = null;
$redirection_path = '../';

$is_own_domain = (strpos($_SERVER["SERVER_NAME"],'.huma-num.fr')>0 && $_SERVER["SERVER_NAME"]!='heurist.huma-num.fr');

//print $_SERVER["SERVER_NAME"].'  '.$is_own_domain.'   '.substr($_SERVER["SERVER_NAME"],0,-12);
//exit;

if($is_own_domain){
    //'dicobiosport'
    //detect databasename
    $database_name_from_domain = substr($_SERVER["SERVER_NAME"],0,-12);//remove .huma-num.fr
    if(empty($requestUri) || $requestUri[0]!=$database_name_from_domain){
        array_unshift($requestUri, $database_name_from_domain);
    }
}

/*
if(count($requestUri)==1){
   if($requestUri[0]==='heurist'){
       redirectURL2('../index.php');
       exit;
   }else{
       $_SERVER["SCRIPT_NAME"] .= '/web/';
       array_push($requestUri, 'web');
   }
}
http://127.0.0.1/heurist/MBH
*/
if(count($requestUri)==1 && ($requestUri[0]=='heurist' || $requestUri[0]=='h6-alpha')){

    redirectURL2('/'.rawurlencode($requestUri[0]).'/index.php');
    exit;

}else
if ((count($requestUri)==1)
     ||
    (count($requestUri)==2 && (!in_array($requestUri[1],$allowedActions) || $requestUri[1]=='startup'))
    )
{ //&& (@$requestUri[0]=='MBH' || @$requestUri[0]=='johns_test_BnF')){
    $dbname = filter_var((count($requestUri)==1)?$requestUri[0]:$requestUri[1]);//to avoid "Open redirect" security report

    if($dbname=='startup'){
        redirectURL2('/'.rawurlencode($requestUri[0]).'/startup/index.php');
        exit;
    }else
    if(!preg_match('/[^A-Za-z0-9_\$]/', $dbname)){
        redirectURL2('/'.rawurlencode($dbname).'/web/');
        exit;
    }
}

$isMediaRequest = false;

// db/record/2312-123  or  db/record/2312-123.rdf  or db/record/123?db=somedb&fmt=rdf
if(count($requestUri)==3 && $requestUri[0]=='db' && ($requestUri[1]=='record' || $requestUri[1]=='file')){
    //redirect to record info
    $recID = $requestUri[2];

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
    $_REQUEST['recID'] = $recID;

    $redirection_path = '../../heurist/';

    $isMediaRequest = ($requestUri[1]=='file');

    //redirectURL2('/h6-alpha/redirects/resolver.php?recID='.$requestUri[2].'&fmt='.$format);

}else                            // dbname/action                               heurist/dbname/action
if(count($requestUri)>1 && (in_array($requestUri[1],$allowedActions) || in_array(@$requestUri[2],$allowedActions)))
{
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

    $error_msg = null; //mysql__check_dbname($requestUri[1]);
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
        }elseif($database=='heurist' || $database=='h6-alpha'){
            redirectURL2('/'.rawurlencode($database).'/index.php');
            exit;
        }

        $params['db'] = $database;

        require_once '../hserv/utilities/USystem.php';
        $host_params = hserv\utilities\USystem::getHostParams();

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
            $_SERVER["REQUEST_URI"] = $host_params['install_dir'];//'/heurist/';

            define('PDIR', $host_params['server_url'] . $host_params['install_dir']);

            $rewrite_path = dirname(__FILE__).'/../index.php';

        }else {
            require_once dirname(__FILE__).'/../hserv/dbaccess/utils_db.php';

            $redirect = $host_params['server_url'] . $host_params['install_dir'];

            if($action=='view' || $action=='edit'){

                if(@$requestUri[3] && ctype_digit($requestUri[3]) && $requestUri[3]>0){
                    $redirect .= ("viewers/record/viewRecord.php?db=$database&recID=".intval($requestUri[3]));
                    $params['recID'] = intval($requestUri[3]);

                    if($action=='view'){
                        $rewrite_path = dirname(__FILE__).'/../viewers/record/viewRecord.php';
                    }else{
                        define('PDIR', $host_params['server_url'] . $host_params['install_dir']);
                        $rewrite_path = dirname(__FILE__).'/../hclient/framecontent/recordEdit.php';
                    }

                }else{
                    $error_msg = 'Record ID is not defined';
                }

            }elseif($action=='hml'){

                //example: https://example.org/heurist/dbname/hml/18/1

                if(@$requestUri[3]){
                    $redirect .= ('export/xml/flathml.php?db='.$database.'&w=a&q=');

                    $ids = prepareIds(@$requestUri[3]);

                    if(!empty($ids)){
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

            }elseif($action=='adm'){

                $redirect = $redirect.'?db='.$database;

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
                //define('PDIR', $host_params['server_url'] . $host_params['install_dir']);
                //$rewrite_path = dirname(__FILE__).'/../index.php';

            }elseif($action=='tpl'){

        //http://127.0.0.1/heurist/osmak_9c/tpl/Basic%20(initial%20record%20types)/t:10
                $query = null;

                if(@$requestUri[3]){

                    if(@$requestUri[4]){

                        $redirect .= ('viewers/smarty/showReps.php?db='.$database.'&template='.basename($requestUri[3]).'&publish=1&w=a&q=');

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
                        $rewrite_path = dirname(__FILE__).'/../viewers/smarty/showReps.php';

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
    }elseif($rewrite_path){
        $_REQUEST = $params;
        include_once $rewrite_path;
        exit;
    }

    redirectURL2($redirect);
    exit;

}
elseif(count($requestUri)>2 && ($requestUri[0]=='heurist' || $requestUri[0]=='h6-alpha') && $requestUri[1]=='viewers'){
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
//      web, website  - redirect ot website
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


}elseif($format=='web' || $format=='website'){ //redirect to website

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

function redirectURL2($url){
    header('Location: '.$url);
}
?>
