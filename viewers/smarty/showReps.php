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
* showReps.php
*
* parameters
* 'template' or 'template_body' - template file name or template value as a text
* 'replevel'  - 1 notices, 2 all, 3 debug mode
*
* 'output' - full file path to be saved
* 'mode' - if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),  
*             1 - publish,  
*             2 - no browser output (save into file only)
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
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/* TODO: rename to showReports.php */

require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');

require_once(dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
require_once(dirname(__FILE__).'/../../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php');

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
$is_jsallowed = true;

$is_included = isset($system); //this script is included into other one

if(!$is_included){
    $system = new System(); 
    if(!$system->init(@$_REQUEST['db'])){
        exit;
    }
}

require_once(dirname(__FILE__).'/smartyInit.php');
require_once(dirname(__FILE__).'/reportRecord.php');

if( (@$_REQUEST['q'] || @$_REQUEST['recordset']) &&
(array_key_exists('template',$_REQUEST) || array_key_exists('template_body',$_REQUEST)))
{
    executeSmartyTemplate($system, $_REQUEST);
}

/*
executeSmartyTemplate - main routine
smarty_post_filter - SMARTY callback: adds a small piece of code in main loop with function smarty_function_progress - need to    
                        maintain progress
smarty_output_filter - SMARTY callback:  calls save_report_output2
smarty_output_filter_strip_js  - purify html and strip js

save_report_output2  - save report output as file (if there is parameter output)
*/

/**
* Main function
*
* @param mixed $_REQUEST
*/
function executeSmartyTemplate($system, $params){

    //$smarty is inited in smartyInit.php
    global $smarty, $outputfile, $isJSout, $gparams, $max_allowed_depth, $publishmode,
           $execution_counter, $execution_total_counter, $is_included, $is_jsallowed;

           
    $isJSout     = (array_key_exists("mode", $params) && $params["mode"]=="js"); //use javascript wrap
    $outputfile  = (array_key_exists("output", $params)) ? $params["output"] :null;
    $publishmode = (array_key_exists("publish", $params))? intval($params['publish']):0;
    $emptysetmessage = (array_key_exists("emptysetmessage", $params))? $params['emptysetmessage']:null;
           
    if(!isset($system) || !$system->is_inited()){
        smarty_error_output( $system, null );
        return;
    }
    
    $is_jsallowed = $system->is_js_acript_allowed();
           
    $mysqli = $system->get_mysqli();

    set_time_limit(0); //no script execution time limit

    $session_id = @$params['session']; //session progress id
    
    $params["f"] = 1; //always search (do not use cache)

    $gparams = $params; //keep to use in other functions

    if( !array_key_exists("limit", $params) ){ //not defined

        if($publishmode==0){
            $limit_for_interface = intval($system->user_GetPreference('smarty-output-limit'));
            
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

        //truncate recordset for output in smarty TAB - limit does not work for publish mode
        if($publishmode==0 && $qresult && array_key_exists('recIDs',$qresult)){
                        
            $recIDs = prepareIds($qresult['recIDs']);
            if($params["limit"]<count($recIDs)){
                //$qresult['recIDs'] = implode(',', array_slice($recIDs, 0, $params["limit"]));
                $qresult = array('records'=>array_slice($recIDs, 0, $params["limit"]) );
                $qresult['reccount'] = count($recIDs);
            }else{
                $qresult = array('records'=>$recIDs );        
                $qresult['reccount'] = count($qresult['records']);
            }
        }

    }else { //search with h4 search engine and got list of ids

        $params['detail'] = 'ids'; // return ids only
        //$params['vo']     = 'h3';  // in h3 format
        $qresult = recordSearch($system, $params); //see db_recsearch.php

        if(@$qresult['status']==HEURIST_OK){
            $qresult = $qresult['data'];
        }else{
           smarty_error_output( $system, null ); 
        }
    }


    // EMPTY RESULT SET - EXIT
    if( !$qresult ||  !array_key_exists('records', $qresult) || !(intval(@$qresult['reccount'])>0) ){
    
        if ($emptysetmessage) {
            $error = $emptysetmessage; // allows publisher of URL to customise the message if no records retrieved
        } else {

            if($publishmode>0){
                $error = "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
            }else{
                $error = "<b><font color='#ff0000'>Search or Select records to see template output</font></b>";
            }
        }
        
        smarty_error_output( $system, $error );
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
            
            smarty_error_output($system, $error);
            exit();
        }
    }else{
        $content = $template_body;
    }
    
    //verify that template has new features
    //need to detect $heurist->getRecord - if it is not found this is old version - show error message
    if(strpos($content, '{foreach $results as ')>0 && strpos($content, '$heurist->getRecord(')===false){
            
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
           
           smarty_error_output($system, $error);
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

    if($publishmode==0 && $session_id!=null){
        mysql__update_progress($mysqli, $session_id, true, '0,0');
    }

    //convert to array that will assigned to smarty variable
    $results =  $qresult["records"];
    $execution_total_counter = count($results); //$qresult["reccount"];

    //we have access to 2 methods getRecord and getRelatedRecords
    $heuristRec = new ReportRecord();
    
    $smarty->assignByRef('heurist', $heuristRec);
    
    $smarty->assign('results', $results); //assign 
    $smarty->assign('template_file', $template_file);

    //$smarty->getvar()

    ini_set( 'display_errors' , 'false'); // 'stdout' );
    $smarty->error_reporting = 0;

    $need_output_filter = true;
    
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
        $user = $system->getCurrentUser();
        
        $template_file = "_".$user['ugr_Name'].".tpl";
        $template_folder = $smarty->getTemplateDir();
        if(is_array($template_folder)) $template_folder = $template_folder[0];
        $file = fopen ($template_folder.$template_file, "w");
        fwrite($file, $template_body);
        fclose ($file);

        //$smarty->display('string:'.$template_body);
    }
    else
    {	// usual way - from file
        if(!$template_file){
            $template_file = 'test01.tpl';
        }
        
        //$smarty->debugging = true;
        //$smarty->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE;

        $smarty->debugging = false;
        $smarty->error_reporting = 0;
        
        if($outputfile!=null){
            $smarty->registerFilter('output', 'smarty_output_filter');  //to preform output into file
            $need_output_filter = false;
        }else if($isJSout){
            $smarty->registerFilter('output', 'smarty_output_js_filter');
            $need_output_filter = false;
        }
    }
    if($need_output_filter){
        $smarty->registerFilter('output', 'smarty_output_filter_strip_js');
    }
    
    //$smarty->registerFilter('pre','smarty_pre_filter'); //before compilation: remove script tags
    $smarty->registerFilter('post','smarty_post_filter'); //after compilation: to add progress support

    if($publishmode==0 && $session_id!=null){
        mysql__update_progress($mysqli, $session_id, true, '0,'.count($results));
    }
    $execution_counter = -1;
    $execution_total_counter = count($results);
    try{
        if($outputfile==null && $publishmode==1 && !$is_included){
            header("Content-type: text/html;charset=UTF-8");
        }
        $smarty->display($template_file);
    } catch (Exception $e) {
        smarty_error_output($system, 'Exception on execution: '.$e->getMessage());
        //echo 'Exception on execution: ', $e->getMessage(), "\n";
    }

    if($publishmode==0 && $session_id!=null){
        mysql__update_progress($mysqli, $session_id, false, 'REMOVE');
    }
    $mysqli->close();        
    
    if($publishmode==0 && $params["limit"] < count($recIDs)){
        echo '<div><b>Report preview has been truncated to '.$params["limit"].' records.<br>'
        .'Please use publish or print to get full set of records.<br Or increase the limit in preferences</b></div>';
    }
    
}

//
// remove all <script> tags from template
//
function smarty_pre_filter($tpl_source, Smarty_Internal_Template $template){
    $s = preg_replace("/<!--#.*-->/U",'',$tpl_source);
    return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $s);    
}

//
// adds a small piece of code in main loop with function smarty_function_progress - need to maintain progress
//
function smarty_post_filter($tpl_source, Smarty_Internal_Template $template)
{
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
// Strip js and clean html
//
function smarty_output_filter_strip_js($tpl_source, Smarty_Internal_Template $template){
    
    global $is_jsallowed;
    
    if($is_jsallowed){
        
        return $tpl_source;
        
    }else{

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');        

        $config->set('HTML.DefinitionID', 'html5-definitions'); // unqiue id
        $config->set('HTML.DefinitionRev', 1);

        //$config = HTMLPurifier_Config::createDefault();
        $config->set('Cache', 'SerializerPath', HEURIST_SCRATCHSPACE_DIR);
        $config->set('CSS.Trusted', true);
        $config->set('Attr.AllowedFrameTargets','_blank');
        $config->set('HTML.SafeIframe', true);
        //allow YouTube, Soundlcoud and Vimeo     
        // https://w.soundcloud.com/player/
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%'); 
        
        $def = $config->getHTMLDefinition(true);
        $def->addElement(
            'audio',
            'Block',
            'Flow',
            'Common',
            [
                'controls' => 'Bool',
                'autoplay' => 'Bool',
                'data-id' => 'Number'
            ]
        );        
        $def->addElement('source', 'Block', 'Flow', 'Common', array(
            'src' => 'URI',
            'type' => 'Text',
        ));

        /* @todo test it
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            // http://developers.whatwg.org/the-video-element.html#the-video-element
            $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                'src' => 'URI',
                'type' => 'Text',
                'width' => 'Length',
                'height' => 'Length',
                'poster' => 'URI',
                'preload' => 'Enum#auto,metadata,none',
                'controls' => 'Bool',
            ));
        }        
        */
        
        
        
        //$config->set('HTML.Trusted', true);
        //$config->set('Filter.ExtractStyleBlocks', true);
        $purifier = new HTMLPurifier($config);
        
        $res = $purifier->purify($tpl_source);
        
        //$styles = $purifier->context->get('StyleBlocks');
        return $res;
    }
    
}

//
// SMARTY callback:  calls save_report_output2
//
function smarty_output_filter($tpl_source, Smarty_Internal_Template $template)
{
    save_report_output2( smarty_output_filter_strip_js($tpl_source, $template) );
}

//
// save report output as file (if there is parameter output)
//
function save_report_output2($tpl_source){

    global $outputfile, $isJSout, $gparams, $publishmode, $is_included;

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

    //$publishmode=2 save into file or download (no browser output)
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

    }else if ($publishmode==1){ //report about success of publishing and where to get it
        
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
// wrap smarty output into javascript function document.write
//
function smarty_output_js_filter($tpl_source, Smarty_Internal_Template $template)
{
    return add_javascript_wrap4( smarty_output_filter_strip_js($tpl_source, $template) );
}
function add_javascript_wrap4($tpl_source)
{
    $tpl_source = str_replace("\n","",$tpl_source);
    $tpl_source = str_replace("\r","",$tpl_source);
    $tpl_source = str_replace("'","&#039;",$tpl_source);
    return "document.write('". $tpl_source."');";
}


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


            $session_val = $execution_counter.','.$tot_count;
            $current_val = mysql__update_progress($mysqli, $session_id, false, $session_val);
            if($current_val && $current_val=='terminate'){
                $session_val = ''; //remove from db
                $res = true;
            }

            //$mysqli->close();
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
        $size = '';
        $mapsize = '';
        $style = '';
        
        if(array_key_exists('style',$params) && $params['style']!=""){
        
            $style = ' style="'.$params['style'].'"';
            
        }else{            
            
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
                if($mode!='thumbnail'){
                    $size = "width=".(($dt=='geo')?"200px":"'300px'");
                }
            }else {
                if($width!=""){
                    $size = "width='".$width."'";
                }
                if($height!=""){
                    $size = $size." height='".$height."'";
                }
            }
        }

        if($dt=="url"){

            return "<a href='".$params['var']."' target='_blank' $style>".$params['var']."</a>";

        }else if($dt=="file"){
            //insert image or link
            $values = $params['var'];

            $limit = intval(@$params['limit']);

            $sres = "";

            if(!is_array($values) || !array_key_exists(0,$values)) $values = array($values);

            foreach ($values as $idx => $fileinfo){

                if($limit>0 && $idx>=$limit) break;

                $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
                $originalFileName = $fileinfo['ulf_OrigFileName'];
                $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
                    
                $file_playerURL = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce.'&mode=tag';
                $file_thumbURL  = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&thumb='.$file_nonce;
                $file_URL   = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce; //download
                            
                if($mode=="link") {

                    $sname = (!$originalFileName || $originalFileName=='_remote')?$external_url:$originalFileName;
                    $sres = $sres."<a href='".$file_URL."' target='_blank' title='".$fileinfo['ulf_Description']."' $style>".$sname."</a>";
                    
                }else 
                if($mode=="thumbnail" ){

                    $sres = $sres."<a href='".$file_URL."' target='_blank'>".
                    "<img src='".$file_thumbURL."' title='".$fileinfo['ulf_Description']."' $size $style/></a>";

                }else{ //player is default


                
                    $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType
                    //$params = $fileinfo['ulf_Parameters'];  //ulf_Parameters - not used anymore (for backward capability only)
                    
                    //$sres = $sres.getPlayerTag($value['nonce'], $value['mimeType'], $value['URL'], $size);
                    $sres = $sres.fileGetPlayerTag($file_nonce, $mimeType, $params, $external_url, $size, $style); //see db_files
                    
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
                        $url = HEURIST_BASE_URL."viewers/map/mapStatic.php?".$mapsize."&q=ids:".$recid."&db=".HEURIST_DBNAME; //"&t="+d;
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

//
//
//
function smarty_error_output($system, $error_msg){
    global $isJSout, $publishmode, $outputfile;  
    
    if(!isset($error_msg)){
        $error_msg = $system->getError();
        $error_msg = (@$error_msg['message'])?$error_msg['message']:'Undefined smarty error';
    }
 
    if($isJSout){
        $error_msg = add_javascript_wrap4($error_msg, null);
    }

    if($publishmode>0 && $outputfile!=null){ //save empty output into file
        save_report_output2($error_msg."<div style=\"padding:20px;font-size:110%\">Currently there are no results</div>");
    }else{
        echo $error_msg;    
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
?>