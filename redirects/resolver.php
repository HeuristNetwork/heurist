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
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
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

$recid = null;         
if(@$_REQUEST['recID']){
    $recid = $_REQUEST['recID'];    
}elseif(@$_REQUEST['recid']){
    $recid = $_REQUEST['recid'];        
}
if(@$_REQUEST['fmt']){
    $format = $_REQUEST['fmt'];    
}elseif(@$_REQUEST['format']){
    $format = $_REQUEST['format'];        
}else{
    $format = 'xml';
}

//form accepting recID=123-3456 which redirects to record 3456 on database 123
if(strpos($recid, '-')>0){
    list($database_id, $recid) = explode('-', $recid, 2);
}else if (is_int(@$_REQUEST['db'])){
    $database_id = $_REQUEST['db'];
}else{
    $database_id = 0;
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
    $redirect = $database_url.'&recID='.$recid.'&fmt'.$format;
}else if($format=='html'){
    $redirect = '../viewers/record/viewRecord.php?db='.$_REQUEST['db'].'&recID='.$recid;
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
