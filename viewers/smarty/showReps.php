<?php

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
* showReps.php
*
* parameters
* 'template' or 'template_body' - template file name or template value as a text
* 'replevel'  - 1 notices, 2 all, 3 debug mode
*
* 'output' - full file path to be saved
* 'mode' - if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only),
*
* other parameters are hquery's
* 
* 
* smarty_function_wrap  - function for var wrap
* 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/* TODO: rename to showReports.php */


define('ISSERVICE',1);
define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../records/files/downloadFile.php');

require_once(dirname(__FILE__).'/../../external/geoPHP/geoPHP.inc');

require_once(dirname(__FILE__).'/libs.inc.php');
require_once(dirname(__FILE__).'/reportRecord.php');

$outputfile = null;
$isJSout = false;

$rtStructs = null;
$dtStructs = null;
$dtTerms = null;

$gparams = null;
$loaded_recs = array();
$max_allowed_depth = 2;
$publishmode = 0;
$execution_counter = 0;
$execution_total_counter = 0;
$session_id = @$_REQUEST['session'];
$mysqli = null;

if( (@$_REQUEST['q'] || @$_REQUEST['recordset']) &&
(array_key_exists('template',$_REQUEST) || array_key_exists('template_body',$_REQUEST)))
{

    executeSmartyTemplate($_REQUEST);
}


/**
* Main function
*
* @param mixed $_REQUEST
*/
function executeSmartyTemplate($params){

    global $smarty, $outputfile, $isJSout, $rtStructs, $dtStructs, $dtTerms, $gparams, $max_allowed_depth, $publishmode,
    $execution_counter, $execution_total_counter, $session_id, $mysqli;

    set_time_limit(0); //no script execution time limit

    mysql_connection_overwrite(DATABASE);

    //AO: mysql_connection_select - does not work since there is no access to stored procedures(getTemporalDateString)
    //    which Steve used in some queries
    //TODO SAW  grant ROuser EXECUTE on getTemporalDate and any other readonly procs

    //load definitions (USE CACHE)
    //$rtStructs = getAllRectypeStructures(true);
    //$dtStructs = getAllDetailTypeStructures(true);
    //$dtTerms = getTerms(true);

    $params["f"] = 1; //always search (do not use cache)

    $isJSout	 = (array_key_exists("mode", $params) && $params["mode"]=="js"); //use javascript wrap
    $outputfile  = (array_key_exists("output", $params)) ? $params["output"] :null;
    $publishmode = (array_key_exists("publish", $params))? intval($params['publish']):0;
    $emptysetmessage = (array_key_exists("emptysetmessage", $params))? $params['emptysetmessage']:null;


    $gparams = $params; //keep to use in other functions

    if( !array_key_exists("limit", $params) ){ //not defined

        if($publishmode==0){

            $limit_for_interface = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['smarty-output-limit']);
            if (!$limit_for_interface || $limit_for_interface<1){
                $limit_for_interface = 50; //default limit in dispPreferences
            }

            $params["limit"] = $limit_for_interface; //force limit
        }else{
            $params["limit"] = PHP_INT_MAX;
        }
    }
    
    $recIDs = array();

    if(@$params['recordset']){ //we already have the list of record ids

        if(is_array($params['recordset'])){
            $qresult = $params['recordset'];  
        }else{
            $qresult = json_decode($params['recordset'], true);    
        }

        //truncate recordset  - limit does not work for publish mode
        if($publishmode==0 && $qresult && array_key_exists('recIDs',$qresult)){
            $recIDs = explode(',', $qresult['recIDs']);
            if($params["limit"]<count($recIDs)){
                $qresult['recIDs'] = implode(',', array_slice($recIDs, 0, $params["limit"]));
            }
        }

    }else if(@$params['h4']==1){ //search with h4 search engine and got list of ids

        /*    for future use
        $params['detail']='ids';
        $params['vo']='h3';
        $qresult = recordSearch($system, $params);
        */    
        $url = "";
        foreach($params as $key=>$value){
            $url = $url.$key."=".urlencode($value)."&";
        }

        $url = HEURIST_BASE_URL."hserver/controller/record_search.php?".$url."&detail=ids&vo=h3";

        $result = loadRemoteURLviaSocket($url);// loadRemoteURLContent($url);

        $qresult = json_decode($result, true);

    }else{
        $qresult = loadSearch($params); //from search/getSearchResults.php - loads array of records based og GET request
    }


    // EMPTY RESULT SET - EXIT
    if( !$qresult || ( !array_key_exists('recIDs',$qresult) && !array_key_exists('records',$qresult) ) ||  $qresult['resultCount']==0 )    {

        if ($emptysetmessage) {
            $error = $emptysetmessage; // allows publisher of URL to customise the message if no records retrieved
        } else {

            if($publishmode>0){
                $error = "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
            }else{
                $error = "<b><font color='#ff0000'>Search or Select records to see template output</font></b>";
            }
        }

        if($isJSout){
            $error = add_javascript_wrap4($error, null);
        }

        if($publishmode>0 && $outputfile!=null){ //save empty output into file
            save_report_output2($error."<div style=\"padding:20px;font-size:110%\">Currently there are no results</div>");
        }else{
            echo $error;    
        }

        exit();
    }

    //get name of template file
    $template_file = (array_key_exists('template',$params)?$params['template']:null);

    //get template body from request (for execution from editor)
    $template_body = (array_key_exists('template_body',$params)?$params['template_body']:null);

    if(null!=$template_file){
        if(substr($template_file,-4)!=".tpl"){
            $template_file = $template_file.".tpl";
        }
        if(file_exists(HEURIST_SMARTY_TEMPLATES_DIR.$template_file)){
            $content = file_get_contents(HEURIST_SMARTY_TEMPLATES_DIR.$template_file);
        }else{
            $error = "<b><font color='#ff0000'>Template file $template_file does not exist</font></b>";
            echo $error;
            if($publishmode>0 && $outputfile!=null){ //save empty output into file
                save_report_output2($error);
            }
            exit();
        }
    }else{
        $content = $template_body;
    }
    
    //verify that template has new features
    //need to detect $heurist->getRecord - if it is not found this is old version - show error message
    if(strpos($content, '$heurist->getRecord(')===false){
            
           $error = '<p>To improve performance we have made some small changes to the report template specifications (July 2016).</p>'. 
                    '<p>Please edit the report and add  <b>{$r = $heurist->getRecord($r)}</b><br/>'
                    .'immediately after the start of the main record loop indicated by {foreach $results as $r}, like this:<p/>'
                    .'<p><br/>{*------------------------------------------------------------*}'
                    .'<br/>{foreach $results as $r}'
                    .'<br/><b>{$r = $heurist->getRecord($r)}</b>'
                    .'<br/>{*------------------------------------------------------------*}</p>'
                    .'<p><br/>If your report format contains repeating value loops, you will also need to add similar<br/>'
                    .'expressions to these loops, for example: {$r.f103 = $heurist->getRecord($r.f103)}.<br/>'
                    .'Generate a new report to obtain examples, then cut and paste into your existing report.</p>'
                    .'<p>If you are stuck, please send your report template to <br/>'
                    .'support at HeuristNetwork dot org and we will adjust the template for you.</p>';
           
           if($publishmode>0 && $outputfile!=null){
                save_report_output2($error);
           }else{
                echo $error;
           }
           exit();
    }
    

    $k = strpos($content, "{*depth");
    $kp = 8;
    if(is_bool($k) && !$k){
        $k = strpos($content, "{* depth");
        $kp = 9;
    }
    if(is_numeric($k) && $k>=0){
        $nd = substr($content,$k+$kp, 1); //strpos($content,"*}",$k)-$k-8);
        if(is_numeric($nd) && $nd<3){
            $max_allowed_depth = $nd;
        }
    }
    //end pre-parsing of template


    $mysqli = mysqli_connection_overwrite(DATABASE);
    if($publishmode==0 && $session_id!=null){
        updateProgress($mysqli, $session_id, true, '0,0');
    }

    //convert to array that will assigned to smarty variable
    if(array_key_exists('recIDs',$qresult)){

        $results =  explode(",", $qresult["recIDs"]);
        $execution_total_counter = count($results);
        
/* OLD WAY
        $records =  explode(",", $qresult["recIDs"]);
        $results = array();
        $k = 0;
        $execution_total_counter = count($records); //'tot_count'=>$tot_count,

        foreach ($records as $recordID){

            if(smarty_function_progress(array('done'=>$k), $smarty)){
                echo 'Execution was terminated';
                return;
            }

            $rec = loadRecord($recordID, false, true); //from search/getSearchResults.php

            $res1 = getRecordForSmarty($rec, 0, $k);
            $res1["recOrder"]  = $k;
            $k++;
            array_push($results, $res1);
        }
*/
    }else{

        $records =  $qresult["records"];
        $execution_total_counter = count($records); //'tot_count'=>$tot_count,
        //v5.5+ $results =  array_column($records, 'recID');
        
        $results = array_map(function ($value) {
                return  @$value['recID']?$value['recID']:array();
            }, $records);
        
/*  OLD WAY        
        $records =  $qresult["records"];
        $execution_total_counter = count($records); //'tot_count'=>$tot_count,
        $results = array();
        $k = 0;
        foreach ($records as $rec){

            if(smarty_function_progress(array('done'=>$k), $smarty)){
                echo 'Execution was terminated';
                return;
            }

            $res1 = getRecordForSmarty($rec, 0, $k);
            $res1["recOrder"]  = $k;
            $k++;
            array_push($results, $res1);
        }
*/
    }
    //activate default template - generic list of records

    //we have access to 2 methods getRecord and getRelatedRecords
    $heuristRec = new ReportRecord();
    //$smarty->registerObject('heurist', $heuristRec, array('getRecord'), false);
    $smarty->assignByRef('heurist', $heuristRec);
    
    $smarty->assign('results', $results); //assign 

    //$smarty->getvar()

    ini_set( 'display_errors' , 'false'); // 'stdout' );
    $smarty->error_reporting = 0;

    if($template_body)
    {	//execute template from string - modified template in editor

        //error report level: 1 notices, 2 all, 3 debug mode
        $replevel = (array_key_exists('replevel',$params) ?$params['replevel']:0);

        if($replevel=="1" || $replevel=="2"){
            ini_set( 'display_errors' , 'true');// 'stdout' );
            $smarty->debugging = false;
            if($replevel=="2"){
                $smarty->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE;
            }else{
                $smarty->error_reporting = E_NOTICE;
            }
        }else{
            $smarty->debugging = ($replevel=="3");
        }

        $smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';

        //save temporary template
        //this is user name $template_file = "_temp.tpl";
        $template_file = "_".get_user_username().".tpl";
        $file = fopen ($smarty->template_dir.$template_file, "w");
        fwrite($file, $template_body);
        fclose ($file);

        //$smarty->display('string:'.$template_body);
    }
    else
    {	// usual way - from file
        if(!$template_file){
            $template_file = 'test01.tpl';
        }
        
        $smarty->debugging = false;
        $smarty->error_reporting = 0;

        if($outputfile!=null){
            $smarty->registerFilter('output', 'smarty_output_filter');
        }else if($isJSout){
            $smarty->registerFilter('output', 'add_javascript_wrap5');
        }
    }
    //DEBUG   
    $smarty->registerFilter('post','smarty_post_filter');

    if($publishmode==0 && $session_id!=null){
        updateProgress($mysqli, $session_id, true, '0,'.count($results));
        /*session_start();
        $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['smarty_progress2'] = '0,'.count($results);
        session_write_close();*/
    }
    $execution_counter = -1;
    $execution_total_counter = count($results);
    try{
        $smarty->display($template_file);
    } catch (Exception $e) {
        echo 'Exception on execution: ', $e->getMessage(), "\n";
    }

    if($publishmode==0 && $session_id!=null){
        updateProgress($mysqli, $session_id, false, 'REMOVE');
    }
    $mysqli->close();        
    
    if($publishmode==0 && $params["limit"] < count($recIDs)){
        echo '<div><b>Report preview has been truncated to '.$params["limit"].' records.<br>'
        .'Please use publish or print to get full set of records.<br Or increase the limit in preferences</b></div>';
    }
    


}

//
// adds smarty_function_progress into main loop
//
function smarty_post_filter($tpl_source, Smarty_Internal_Template $template)
{
    //error_log($template);
    //error_log($tpl_source);
    //find fist foreach and insert as first operation
    $offset = strpos($tpl_source,'foreach ($_from as $_smarty_tpl');
    if($offset>0){
        $pos = strpos($tpl_source,'{',$offset);

        $res = substr($tpl_source,0,$pos)
        ."\n".'{ if(smarty_function_progress(array(), $_smarty_tpl)){ return; }'."\n"
        .substr($tpl_source,$pos+1);

        //DEBUG error_log($res);
        return $res;
    }else{
        return $tpl_source;
    }
}

//
// save report output as file (if there is parameter output)
//
function smarty_output_filter($tpl_source, Smarty_Internal_Template $template)
{
    save_report_output2($tpl_source);
}

function save_report_output2($tpl_source){

    global $outputfile, $isJSout, $gparams, $publishmode;

    $errors = null;
    $res_file = null;

    if($publishmode<2){ //save into file - otherwise download with given file name
    //$publishmode = (array_key_exists("publish", $gparams))? intval($gparams['publish']):0;
    try{

        $path_parts = pathinfo($outputfile);
        $dirname = (array_key_exists('dirname',$path_parts))?$path_parts['dirname']:"";


        if(!file_exists($dirname)){
            $errors = "Output folder $dirname does not exist";
        }else{
            if($isJSout){
                $tpl_res = add_javascript_wrap4($tpl_source);
                $ext =  ".js";
            }else{
                $tpl_res = $tpl_source;
                $ext =  ".html";
            }

            $res_file = $dirname."/".$path_parts['filename'].$ext;
            $file = fopen ($res_file, "w");
            if(!$file){
                $errors = "Can't write file $res_file. Check permission for directory";
            }else{
                fwrite($file, $tpl_source);
                fclose ($file);
            }

        }

    }catch(Exception $e)
    {
        $errors = $e->getMessage();
    }
    }

    //$publishmode=2 download
    if($publishmode!=1){

        if($errors!=null){
            $tpl_source = $tpl_source."<div style='color:#ff0000;font-weight:bold;'>$errors</div>";
        }

        if($isJSout){
            header("Content-type: text/javascript");
            $tpl_res = add_javascript_wrap4($tpl_source);
        }else{
            header("Content-type: text/html;charset=UTF-8");
            $tpl_res = $tpl_source;
        }
        if($publishmode==2){
            //set file name
            header('Pragma: public');
            header('Content-Disposition: attachment; filename="'.$outputfile.'"'); 
            header('Content-Length: ' . strlen($tpl_res));
        }

        echo $tpl_res;

    }else if ($publishmode==1){
        
        if($errors!=null){
            header("Content-type: text/html;charset=UTF-8");
            echo $errors;
        }else{
            ?>
            <html>
            <head>
                <meta http-equiv="content-type" content="text/html; charset=utf-8">
                <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
            </head>
            <body style="margin: 25px;">
            <h2>
                The following file has been updated:  <?=$res_file?></h2><br />

            <?php
            $rps_recid = @$gparams['rps_id'];
            if($rps_recid){

                $link = HEURIST_BASE_URL."viewers/smarty/updateReportOutput.php?db=".HEURIST_DBNAME."&publish=3&id=".$rps_recid;
                ?>

                <p style="font-size: 14px;">View the generated files by clicking the links below:<br /><br />
                HTML: <a href="<?=$link?>" target="_blank" style="font-weight: bold;"><?=$link?></a><br />
                Javascript: <a href="<?=$link?>&mode=js" target="_blank" style="font-weight: bold;"><?=$link?>&mode=js</a><br />

                <?php
            }

            // code for insert of dynamic report output - duplication of functionality in repMenu.html
            $surl = HEURIST_BASE_URL."viewers/smarty/showReps.php?db=".HEURIST_DBNAME.
            "&ver=".$gparams['ver']."&w=".$gparams['w']."&q=".$gparams['q'].
            "&publish=1&debug=0&template=".$gparams['template'];

            if(@$gparams['rules']){
                $surl = $surl."&rules=".$gparams['rules'];
            }
            if(@$gparams['h4']){
                $surl = $surl."&h4=".$gparams['h4'];
            }


            ?><br />
            To publish the report as dynamic (generated on-the-fly) output, use the code below.
            <br /><br />
            URL:<br />
            <textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; font-size: 10px; width: 70%; height: 60px;"
                id="code-textbox1" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);"><?=$surl?></textarea>

            <br />
            Javascript wrap:<br />
            <textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; font-size: 10px; width: 70%; height: 100px;"
                id="code-textbox2" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);">
                <script type="text/javascript" src="<?=$surl?>&mode=js"></script><noscript><iframe width="80%" height="70%" frameborder="0" src="<?=$surl?>"></iframe></noscript>
            </textarea>
            <?php
            echo "</p></body></html>";

        }
    }
}



//
// wrap smarty output into javascript function
//
function add_javascript_wrap5($tpl_source, Smarty_Internal_Template $template)
{
    return add_javascript_wrap4($tpl_source);
}
function add_javascript_wrap4($tpl_source)
{
    $tpl_source = str_replace("\n","",$tpl_source);
    $tpl_source = str_replace("\r","",$tpl_source);
    $tpl_source = str_replace("'","&#039;",$tpl_source);
    return "document.write('". $tpl_source."');";
}



// NOT USED
// convert record or detail name string to PHP applicable variable name (index in smarty variable)
// for field(detail) type it will  in low case
//
function getVariableNameForSmarty($name, $is_fieldtype = true){

    //$dtname = strtolower(str_replace(' ','_',strtolower($dtNames[$dtKey]));
    //'/[^(\x20-\x7F)\x0A]*/'     "/^[a-z0-9]+$/"

    if($is_fieldtype){
        $name = strtolower($name);
    }

    $goodname = preg_replace('~[^a-z0-9]+~i','_', $name);
    $goodname = str_replace('__','_',$goodname);

    return $goodname;
}




// Smarty plugin functions allow new functionality to be added to Smarty reports
// Can be of particular use for complex functions or functions that are used repeatedly by particular installations


//
// quick solution for progress tracking
// it is added into smarty report code into main loop in postfilter event listener
//
function smarty_function_progress($params, &$smarty){
    global $publishmode, $execution_counter, $execution_total_counter,$session_id,$mysqli;

    $res = false;

    if($publishmode==0 && $session_id!=null){ //check that this call from ui

        if(@$params['done']==null){//not set, this is execution from smarty
            $execution_counter++;
        }else{
            $execution_counter = @$params['done'];
        }

        if(isset($execution_total_counter) && $execution_total_counter>0){
            $tot_count = $execution_total_counter;
        }else 
            if(@$params['tot_count']>=0){
                $tot_count = $params['tot_count'];
            }else{
                $tot_count = count(@$smarty->getVariable('results')->value);
        }

        if($execution_counter<2 || $execution_counter % 10==0 || $execution_counter>$tot_count-3){


            //$mysqli = mysqli_connection_overwrite(DATABASE);

            //get 
            $current_val = updateProgress($mysqli, $session_id, false, null);

            if($current_val && $current_val=='terminate'){

                $session_val = ''; //remove from db
                $res = true;
            }else{
                /*
                if($execution_counter<2 || $execution_counter>$tot_count-3){  
                error_log('next '.$execution_counter.'   '.@$params['done'].'  '.$tot_count.'  current_val='.$current_val);        
                }
                */
                $session_val = $execution_counter.','.$tot_count;
            }
            //set
            updateProgress($mysqli, $session_id, false, $session_val);

            //$mysqli->close();

            /* it does not worl properly            
            session_start();
            $current_val = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['smarty_progress2'];
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['smarty_progress2'] = $execution_counter.','.$tot_count;
            session_write_close();
            */
        }

    }
    return $res;
}



//
// smarty plugin function
//
function smarty_function_out($params, &$smarty)
{
    $dt = null;

    if($params['var']){
        return '<div><div class="tlbl">'.$params['lbl'].': </div><b>'.$params['var'].'</b></div>';
    }else{
        return '';
    }
}



//
// smarty plugin function
// {wrap var=$s.f8_originalvalue dt="file" width="100" height="auto"}
//
function smarty_function_wrap($params, &$smarty)
{

    if($params['var']){


        if(array_key_exists('dt',$params)){
            $dt = $params['dt'];
        }
        if(array_key_exists('mode',$params)){
            $mode = $params['mode'];
        }else{
            $mode = null;
        }

        $label = "";
        if(array_key_exists('lbl',$params) && $params['lbl']!=""){
            $label = $params['lbl'];
        }
        $width = "";
        $mapsize = "width=200";

        if(array_key_exists('width',$params) && $params['width']!=""){
            $width = $params['width'];
            if(is_numeric($width)<0){
                $width = $width."px";
                $mapsize = "width=".$width;
            }
        }
        $height = "";
        if(array_key_exists('height',$params) && $params['height']!=""){
            $height = $params['height'];
            if(is_numeric($height)<0){
                $height = $height."px";
                $mapsize = $mapsize."&height=".$height;
            }
        }
        if(!(strpos($mapsize,"&")>0)){
            $mapsize = $mapsize."&height=200";
        }

        $size = "";
        if($width=="" && $height==""){
            $size = "width=".(($dt=='geo')?"200px":"'300px'");
        }else {
            if($width!=""){
                $size = "width='".$width."'";
            }
            if($height!=""){
                $size = $size." height='".$height."'";
            }
        }

        if($dt=="url"){

            return "<a href='".$params['var']."' target='_blank'>".$params['var']."</a>";

        }else if($dt=="file"){
            //insert image or link
            $values = $params['var'];

            $limit = intval(@$params['limit']);

            $sres = "";

            if(!is_array($values) || !array_key_exists(0,$values)) $values = array($values);

            foreach ($values as $idx => $value){

                if($limit>0 && $idx>=$limit) break;

                $mimeType = $value['mimeType'];
                
                $value['playerURL'] = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$value['nonce'].'&mode=tag';
                //$value['URL'] = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$value['nonce'];
                
                if($mode=="link") {

                    $sname = ($value['origName']=='_remote')?$value['URL']:$value['origName'];
                    $sres = $sres."<a href='".$value['URL']."' target='_blank' title='".$value['description']."'>".$sname."</a>";
                    
                }else 
                if($mode=="thumbnail" ){

                    $sres = $sres."<a href='".$value['URL']."' target='_blank'>".
                    "<img src='".$value['thumbURL']."' title='".$value['description']."'/></a>";

                }else{ //player is default

                    
                    $sres = $sres.getPlayerTag($value['nonce'], $value['mimeType'], $value['URL'], $size);
                    //$value['playerURL'] = $value['playerURL'].'&size='.$size;
                    //it does not work  $sres = $sres.file_get_contents($value['playerURL']);
                    
                    /*
                    if($type_media == 'image'){
                        $sres = $sres."<img src='".$value['URL']."' ".$size." title='".$value['description']."'/>"; //.$value['origName'];
                    }else if($value['remoteSource']=='youtube' || $value['mimeType'] == 'video/youtube' || $value['ext'] == 'youtube'){
                     //video/youtube
                        $sres = $sres.linkifyYouTubeURLs($value['URL'], $size);

                        
                    }else if($value['mimeType'] == 'video/vimeo' || $value['ext'] == 'vimeo'){
                     //video/viemo
                        $sres = $sres.linkifyVimeoURLs($value['URL'], $size);
                        
                    }else if($value['mimeType'] == 'audio/soundcloud' || $value['ext'] == 'soundcloud'){
                     //audio/soundcloud
                        $sres = $sres.linkifySoundcloudURL($value['URL'], $size);                        
                                                    
                    }else if($value['remoteSource']=='gdrive' ){
                        $sres = $sres.linkifyGoogleDriveURLs($value['URL'], $size);

                    }else if($type_media=='document' && $value['mimeType']) {

                        $sres = $sres.'<embed $size name="plugin" src="'.$value['URL'].'" type="'.$value['mimeType'].'" />';

                    }else if($type_media=='video' ||  strpos($value['mimeType'],'video')===0){
                        // UNFORTUNATELY HTML5 rendering does not work properly
                        // $sres = $sres.createVideoTag($value['URL'], $value['mimeType'], $size);

                        $sres = $sres.createVideoTag2($value['URL'], $value['mimeType'], $size);

                    }else if($type_media=='audio' ||  strpos($value['mimeType'],'audio')===0){
                        $sres = $sres.createAudioTag($value['URL'], $value['mimeType']);
                    }else{
                        $sres = $sres."Unsupported media type ".$type_media;
                    }
                    */
                }

            }

            return $sres;

        }else if($dt=='geo'){

            $value = $params['var'];
            $res = "";

            if($value && $value['wkt']){
                $geom = geoPHP::load($value['wkt'],'wkt');
                if(!$geom->isEmpty()){

                    if(array_key_exists('mode',$params) && $params['mode']=="link"){
                        $point = $geom->centroid();
                        if($label=="") $label = "on map";
                        $res = '<a href="http://maps.google.com/maps?z=18&q='.$point->y().",".$point->x().'" target="_blank">'.$label."</a>";
                    }else{
                        $recid = $value['recid'];
                        $url = HEURIST_BASE_URL."viewers/map/showMapUrl.php?".$mapsize."&q=ids:".$recid."&db=".HEURIST_DBNAME; //"&t="+d;
                        return "<img src=\"".$url."\" ".$size."/>";
                    }
                }
            }
            return $res;
        }
        else{
            if($label!="") $label = $label.": ";
            return $label.$params['var'].'<br/>';
        }
    }else{
        return '';
    }
}



//-----------------------------------------------------------------------

function getSmartyVars($string){
    // regexp
    $fullPattern = '`{[^\\$]*\\$([a-zA-Z0-9]+)[^\\}]*}`';
    $separateVars = '`[^\\$]*\\$([a-zA-Z0-9]+)`';

    $smartyVars = array();
    // We start by extracting all the {} with var embedded
    if(!preg_match_all($fullPattern, $string, $results)){
        return $smartyVars;
    }
    // Then we extract all smarty variables
    foreach($results[0] AS $result){
        if(preg_match_all($separateVars, $result, $matches)){
            $smartyVars = array_merge($smartyVars, $matches[1]);
        }
    }
    return array_unique($smartyVars);
}

?>