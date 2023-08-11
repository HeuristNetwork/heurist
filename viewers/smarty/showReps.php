<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* showReps.php - executes smarty template file or text
*
* parameters
* 'template' or 'template_body' - template file name or template value as a text
* 'replevel'  - 1 notices, 2 all, 3 debug mode
*
* 'output' - full file path to be saved
* 'mode' - if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),  
*             1,2,3 - different behaviour when output is defined 
*             if output si null if html and js - output to browser, otherwise download
*             4 - for calculation fields  
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
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
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

// param: mode 
// text or text/plain - without header and body tags  $is_headless=true
// html or text/html  - usual mode
// js or text/javascript
// csv or text/csv
// xml or text/xml
// NOTE: if script $is_included it can't set Content-type
$outputmode = 'html'; 
$is_headless = false; //param:  snippet=1 or Content-type other than text/html and text/javascript


$rtStructs = null;
$dtStructs = null;
$dtTerms = null;

$gparams = null;
$loaded_recs = array();
$max_allowed_depth = 2;

//"publish"
// 0  - Heurist User Interface (smarty tab) it truncates number of records to be out (see smarty-output-limit)
// 4 - execute smarty from string (for calculated fields and title mask). It is nearly same as 0
// 
// otherwise it works in publish mode and takes all records from search output
// if param "output" ($outputfile) is defined it saves smarty report into file 
// and
//    1 produces info page (user report) only - Content-type is text/html always 
//    2 downloads it under given output name (no browser output) 
//    3 outputs smarty report into browser (for html and js) and download for other content types
//
$publishmode = 0;

$execution_counter = 0;
$execution_total_counter = 0;
$is_jsallowed = true;
$record_with_custom_styles = 0; //record id with custom css and style links DT_CMS_CSS and DT_CMS_EXTFILES

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
smarty_output_filter - SMARTY callback:  calls save_report_into_file
smarty_output_filter_strip_js  - purify html and strip js - this is last filter

save_report_into_file  - save report output as file (if there is parameter "output")
*/

/**
* Main function
*
* @param mixed $_REQUEST
*/
function executeSmartyTemplate($system, $params){

    //$smarty is inited in smartyInit.php
    global $smarty, $outputfile, $outputmode, $gparams, $max_allowed_depth, $publishmode,
           $execution_counter, $execution_total_counter, $is_included, $is_jsallowed,
           $record_with_custom_styles, $is_headless;

           
    $outputfile  = (array_key_exists("output", $params)) ? htmlspecialchars($params["output"]) :null;
    $publishmode = (array_key_exists("publish", $params))? intval($params['publish']):0;
    $emptysetmessage = (array_key_exists("emptysetmessage", $params))? $params['emptysetmessage']:null;
    $record_with_custom_styles = (array_key_exists("cssid", $params))? $params['cssid']:null;
    
// text or text/plain - without header and body tags  $is_headless=true
// html or text/html  - usual mode
// js or text/javascript
// csv or text/csv
// xml or text/xml        
    $is_headless = @$params['snippet']==1; //former $is_snippet_output
    if(array_key_exists("mode", $params) && $params["mode"]){
        $outputmode = $params["mode"];
    }
    if($outputmode!='js' && $outputmode!='html'){
        $is_headless = true;            
    }
    if($outputmode=='text') $outputmode = 'txt';   
           
    if(!isset($system) || !$system->is_inited()){
        smarty_error_output( $system, null );
        return false;
    }
    
    $is_jsallowed = $system->is_js_acript_allowed();
           
    $mysqli = $system->get_mysqli();

    set_time_limit(0); //no script execution time limit

    $session_id = @$params['session']; //session progress id
    
    $params["f"] = 1; //always search (do not use cache)

    $params["void"] = (@$params["void"]==true);
    
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

    }else { //search record ids with query params

        $params['detail'] = 'ids'; // return ids only
        $qresult = recordSearch($system, $params); //see db_recsearch.php

        if(@$qresult['status']==HEURIST_OK){
            $qresult = $qresult['data'];
        }else{
            smarty_error_output( $system, null );  //output error
        }
    }


    // EMPTY RESULT SET - EXIT
    if( !$qresult ||  !array_key_exists('records', $qresult) || !(intval(@$qresult['reccount'])>0) ){
    
        if($publishmode==4){ //from string var
            echo htmlspecialchars(($emptysetmessage && $emptysetmessage != 'def') ? $emptysetmessage : '');
        }else{
            
            if ($emptysetmessage && $emptysetmessage != 'def') {
                $error = $emptysetmessage; // allows publisher of URL to customise the message if no records retrieved
            } else {

                if($publishmode>0){
                    $error = "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
                }else{
                    //message for UI
                    $error = '<b><font color="#ff0000">Search or Select records to see template output</font></b>';
                }
            }
            
            smarty_error_output( $system, $error );
        }
        return true; //exit();
    }

    //get name of template file
    $template_file = (array_key_exists('template',$params)?basename($params['template']):null);

    //get template body from request (for execution from editor)
    $template_body = (array_key_exists('template_body',$params)?$params['template_body']:null);

    if(null!=$template_file){
        if(substr($template_file,-4)!=".tpl"){
            $template_file = $template_file.".tpl";
        }
        
        if(file_exists($system->getSysDir('smarty-templates').$template_file)){
            $content = file_get_contents($system->getSysDir('smarty-templates').$template_file);
        }else{
            $error = "<b><font color='#ff0000'>Template file $template_file does not exist</font></b>";
            
            smarty_error_output($system, $error);
            return false; //exit();
        }
    }else{
        $content = $template_body;
        if($publishmode!=4) $publishmode = 0;
        $outputmode = 'html';
    }
    
    //verify that template has new features
    //need to detect $heurist->getRecord - if it is not found this is old version - show error message
    if($publishmode!=4 && strpos($content, '{foreach $results as ')>0 && strpos($content, '$heurist->getRecord(')===false){
            
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
           return false; //exit();
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

    
    if(!isset($smarty) || $smarty==null){
        initSmarty($system->getSysDir('smarty-templates')); //global function from smartyInit.php
        if(!isset($smarty) || $smarty==null){
            smarty_error_output($system, 'Cannot init Smarty report engine');
            exit();
        }
    }

    
    if($publishmode==0 && $session_id!=null){
        mysql__update_progress($mysqli, $session_id, true, '0,0');
    }else{
        $session_id = null;
    }

    //convert to array that will assigned to smarty variable
    $results =  $qresult["records"];
    $execution_total_counter = count($results); //$qresult["reccount"];

    //we have access to 2 methods getRecord and getRelatedRecords
    $heuristRec = new ReportRecord();
    
    $smarty->assignByRef('heurist', $heuristRec);
    
    $smarty->assign('results', $results); //assign record ids
    

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
        if($replevel>0){
            $smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';    
        }

        //save temporary template
        //this is user name $template_file = "_temp.tpl";
        $user = $system->getCurrentUser();
        
        $template_file = '_'.fileNameSanitize($user['ugr_Name']).'.tpl'; //snyk SSRF
        $template_folder = $smarty->getTemplateDir();
        if(is_array($template_folder)) $template_folder = $template_folder[0];
        $file = fopen ($template_folder.$template_file, "w"); 
        fwrite($file, $template_body);
        fclose ($file);

        //$smarty->display('string:'.$template_body);
        if($publishmode==4){ //test for calculation fields
            $need_output_filter = false;
            $smarty->assign('r', $heuristRec->getRecord($results[0]));
            //$smarty->registerFilter('output', 'smarty_output_filter_strip_js');
            try{
                $output = $smarty->fetch($template_file);
                
            } catch (Exception $e) {
                $output = 'Exception on calc field execution: '.$e->getMessage();
            }
            if(file_exists($template_folder.$template_file)){
                unlink($template_folder.$template_file);   
            }
            echo $output;
            return true; //exit();
        }
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
        }else if($outputmode=='js'){
            $smarty->registerFilter('output', 'smarty_output_filter_wrap_js');
            $need_output_filter = false;
        }
    }
    if($need_output_filter){ //Strip js and clean html
        $smarty->registerFilter('output', 'smarty_output_filter_strip_js');
    }
    
    $smarty->registerFilter('pre', 'smarty_pre_filter'); //before compilation: handle short form term translations
    if($publishmode==0 && $session_id>0)
    {
        $smarty->registerFilter('post','smarty_post_filter'); //after compilation: to add progress support
        mysql__update_progress($mysqli, $session_id, true, '0,'.count($results));
    }
    
    $execution_counter = -1;
    $execution_total_counter = count($results);
    
    
    $result = true;
    
    $smarty->assign('template_file', $template_file);    
    try{
        
        //if $outputfile is not defined - define content type
        if($outputfile==null && !$is_included){
            //header("Content-type: text/html;charset=UTF-8");
            
            if($outputmode=='js'){
                header("Content-type: text/javascript");
            }else if($publishmode>0 && $publishmode<4){ 
                
                if($outputmode=='txt'){
                    $mimetype = 'plain/text';
                }else if($outputmode=='json'){
                    $mimetype = 'application/json';
                }else{
                    $mimetype = "text/$outputmode";    
                }
                
                if(!$is_headless && $outputmode!='html'){
                    header("Content-type: $mimetype;charset=UTF-8");
                }
                
                if($outputmode!='html'){
                    $outputfile = 'heurist_output.'.$outputmode;
                    header('Pragma: public');
                    header('Content-Disposition: attachment; filename="'.$outputfile.'"'); 
                    //header('Content-Length: ' . strlen($tpl_res));
                }
            }
        }
        
        if($gparams['void']){
            $smarty->fetch($template_file);
        }else{
            $smarty->display($template_file);    
        }
        
        //not record list, not from editor
        if(!$is_headless && !@$template_body && !$params["void"]){
            // log activity, rec ids separated by spaces
            log_smarty_activity($system, $results);
            //$system->user_LogActivity('custRep', array(implode(' ',$results), count($results)), null, TRUE); 
        }
        
    } catch (Exception $e) {
        smarty_error_output($system, 'Exception on execution: '.$e->getMessage());
        //echo 'Exception on execution: ', $e->getMessage(), "\n";
        $result = false;
    }

    if($session_id>0){
        mysql__update_progress($mysqli, $session_id, false, 'REMOVE');
    }
    if(!$params["void"]){
        $mysqli->close();        
    }
    
    if($publishmode==0 && $params["limit"] < count($recIDs)){
        echo '<div><b>Report preview has been truncated to '.intval($params["limit"]).' records.<br>'
        .'Please use publish or print to get full set of records.<br Or increase the limit in preferences</b></div>';
    }
    
    return $result;
    
} //END executeSmartyTemplate

//Performs the following, before Smarty processes the report:
// Convert short form term translations
//
function smarty_pre_filter($tpl_source, Smarty_Internal_Template $template){
    /* Original pre filter - remove all <script> tags from template
    $s = preg_replace("/<!--#.*-->/U",'',$tpl_source);
    return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $s);
    */

    global $system;
    $mysqli = $system->get_mysqli();
    $matches = array();

    // Handle shorthand term translations {trm_id \Language_1 \Language_2 ...}
    if(preg_match_all('/{\\d*\\s*(?:\\\\\\w{3}\\s*)+}/', $tpl_source, $matches)){

        $query = "SELECT trn_Translation FROM defTranslations WHERE trn_Code={id} AND trn_Source='trm_Label' AND trn_LanguageCode='{lang}'";
        $to_replace = array('{id}', '{lang}');
        $done = array();

        foreach ($matches[0] as $match) {

            if(in_array($match, $done)){ // already replaced
                continue;
            }

            $parts = explode('\\', trim($match, ' {}'));
            $str_replace = '';

            if(count($parts) == 0 || intval($parts[0]) < 1){ // ignore
                continue;
            }

            $id = intval(array_shift($parts));

            foreach ($parts as $lang) {
                $str_replace = mysql__select_value($mysqli, str_replace($to_replace, array($id, $lang), $query));

                if(!empty($str_replace)){
                    break;
                }
            }

            if(empty($str_replace)){
                $str_replace = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID=$id");
                $str_replace = $str_replace;
            }

            $tpl_source = str_replace($match, $str_replace, $tpl_source);
            array_push($done, $match);
        }
    }

    return $tpl_source;
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
// it calls before other output filters
//
function smarty_output_filter_strip_js($tpl_source, Smarty_Internal_Template $template){
    
    global $system, $is_jsallowed, $record_with_custom_styles, $is_headless, $outputmode;
    
    if($outputmode=='js' || $outputmode=='html'){
    
    if($is_jsallowed){
        
        if(!$is_headless){ //full html output. inside iframe - add all styles and scripts to header at once
        
            //adds custom scripts and styles to html head
        
            $head = '';
            $close_tags = '';
        
            if(strpos($tpl_source, '<html>')===false){
                $open_tags = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
                $close_tags = '</body></html>';
            }
        
            //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
            if($record_with_custom_styles>0){
                //find record with css fields
                $css_fields = array();
                if($system->defineConstant('DT_CMS_CSS')){
                    array_push($css_fields, DT_CMS_CSS);
                }
                if($system->defineConstant('DT_CMS_EXTFILES')){
                    array_push($css_fields, DT_CMS_EXTFILES);
                }
                if(count($css_fields)>0){
                    $record = recordSearchByID($system, $record_with_custom_styles,$css_fields,'rec_ID');
                    if($record && @$record['details']){

                        if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
                           //add to begining 
                           $head .= '<style>'.recordGetField($record, DT_CMS_CSS).'</style>';
                        }
                        
                        if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
                            //add to header
                            $external_files = @$record['details'][DT_CMS_EXTFILES];
                            if($external_files!=null){
                                if(!is_array($external_files)){
                                    $external_files = array($external_files);
                                }
                                foreach ($external_files as $ext_file){
                                    $head .= $ext_file;
                                }
                            }
                        }
                    }
                }
            }            

            //check if need to init mediaViewer
            if(strpos($tpl_source,'fancybox-thumb')>0){

                $head .= 
                    ('<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>'
                    .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery-ui-1.12.1/jquery-ui.min.js"></script>'
                    .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.js"></script>'
                    .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'hclient/core/detectHeurist.js"></script>'
                    .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'hclient/widgets/viewers/mediaViewer.js"></script>'
                    .'<link rel="stylesheet" href="'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.css" />');
                
                //init mediaviewer after page load
                $head .=  ('<script>'
                .'var rec_Files=[];'
                .'$(document).ready(function() {'
                    .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                    .'database:"'.$system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'    
                  .'});'
                .'</script>'
                .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
            }

            
            //forcefully adds html and body tags
            $tpl_source = $open_tags.$tpl_source.$close_tags;
            
            $tpl_source = str_replace('<body>','<body class="smarty-report">', $tpl_source);
            if($head!=''){
                $tpl_source = str_replace('</head>',$head.'</head>', $tpl_source);
            }
            
            
        }else{ //html snippet output (without head) ----------------------------
        
            //adds custom scripts and styles to parent document head (insertAdjacentHTML and )
        
            $head = '';
        
            //check if need to init mediaViewer
            if(strpos($tpl_source,'fancybox-thumb')>0){
                
                $head = 
                        '<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>'
                        .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery-ui-1.12.1/jquery-ui.js"></script>'
                        .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.js"></script>'
                        .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'hclient/core/detectHeurist.js"></script>'
                        .'<script type="text/javascript" src="'.HEURIST_BASE_URL.'hclient/widgets/viewers/mediaViewer.js"></script>'
                        .'<script>'
                        .'var rec_Files=[];</script>';
                
                $head .= (
                    '<script>$(document).ready(function() {'
                    
    .'document.getElementsByTagName("head")[0].insertAdjacentHTML("beforeend","<link rel=\"stylesheet\" href=\"'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.css\" />");'                
                    
                        .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                        .'database:"'.$system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'    
                      .'});'
                    .'</script>'
                    .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
            }
            
            //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
            if($record_with_custom_styles>0){
                //find record with css fields
                $css_fields = array();
                if($system->defineConstant('DT_CMS_CSS')){
                    array_push($css_fields, DT_CMS_CSS);
                }
                if($system->defineConstant('DT_CMS_EXTFILES')){
                    array_push($css_fields, DT_CMS_EXTFILES);
                }
                if(count($css_fields)>0){
                    $record = recordSearchByID($system, $record_with_custom_styles,$css_fields,'rec_ID');
                    if($record && @$record['details']){

                        if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
                           //add to begining 
                           $head = '<style>'.recordGetField($record, DT_CMS_CSS).'</style>'.$head;
                           
                           $head = $head.'<script>if(document.body){
                                document.body.classList.add("smarty-report");
                           } </script>';
                        }
                        
                        if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
                            //add to header
                            $external_files = @$record['details'][DT_CMS_EXTFILES];
                            if($external_files!=null){
                                if(!is_array($external_files)){
                                    $external_files = array($external_files);
                                }
                                if(count($external_files)>0){
                                    foreach ($external_files as $ext_file){
                                        if(strpos($ext_file,'<link')===0){ // || strpos($ext_file,'<script')===0
                                            $head = $head .$ext_file;
                                        }
                                    }
/*                                    
                $head = $head.'<script>(function() {';
                                    foreach ($external_files as $ext_file){
                                        if(strpos($ext_file,'<link')===0){ // || strpos($ext_file,'<script')===0
                $head = $head.'document.getElementsByTagName("head")[0].insertAdjacentHTML("beforeend",\''
                                        .$ext_file //str_replace('"','\"',$ext_file)
                                        .'\');';                                        }
                                    }
                                    
                $head = $head.' })();</script>';
*/                
                                }
                            }
                        }
                    }
                }
            }

            //
            if($head!=''){
                //$tpl_source = removeHeadAndBodyTags($tpl_source);
                
                if(strpos($tpl_source, '<head>')>0){
                    $tpl_source = str_replace('</head>',$head.'</head>', $tpl_source);    
                }else{
                    $tpl_source = removeHeadAndBodyTags($tpl_source);
                    $tpl_source = $head.$tpl_source;
                }            
            }
        }
         
    }else{
        
        //if javascript not allowed, use html purifier to remove suspicious code

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');        

        $config->set('HTML.DefinitionID', 'html5-definitions'); // unqiue id
        $config->set('HTML.DefinitionRev', 1);

        //$config = HTMLPurifier_Config::createDefault();
        $config->set('Cache', 'SerializerPath', $system->getSysDir('scratch'));
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
        
        $tpl_source = $purifier->purify($tpl_source);
        
        //$styles = $purifier->context->get('StyleBlocks');
    }
    
    }else{
        //other than html or js output - it removes html and body tags
        $tpl_source = removeHeadAndBodyTags($tpl_source);
    }
    
    $onclick = '';
    if($publishmode==0){
        $onclick = 'onclick="{try{var h=window.hWin?window.hWin.HEURIST4:window.parent.hWin.HEURIST4;h.msg.showDialog(event.target.href,{title:\'.\',width: 600,height:500,modal:false});return false}catch(e){return true}}" ';
    }
    
    $tpl_source = preg_replace('/href=["|\']?(\d+)["|\']?/',
        $onclick.'href="'.$system->recordLink('$1').'"', 
        $tpl_source);
    
    return $tpl_source;
    
}

function removeHeadAndBodyTags($content){
    
            $dom = new domDocument;
            $dom->preserveWhiteSpace = false;
            //$dom->formatOutput       = true;
            @$dom->loadHTML($content);
            $body = $dom->getElementsByTagName('body');
            if($body){
                $content = $dom->saveHtml($body[0]); //outer html  
                $content = preg_replace( '@^<body[^>]*>|</body>$@', '', $content );
                $content = preg_replace( '@^<p[^>]*>|</p>$@', '', $content );
            }
            
            return $content;
    
}

//
// SMARTY callback:  calls save_report_into_file
// executed after smarty execution - save output to file
// before this it calls smarty_output_filter_strip_js to strip js
//
function smarty_output_filter($tpl_source, Smarty_Internal_Template $template)
{
    save_report_into_file( smarty_output_filter_strip_js($tpl_source, $template) );
}

//
// save smarty report output as a file (if there is parameter "output" -> $outputfile) 
//
// if param "output" ($outputfile) is defined it saves smarty report into file 
// and
// $publishmode - 1 produces info page (user report) only 
//                2 downloads ONLY it under given output name (no file save, no browser output) 
//                3 outputs smarty report into browser
//
function save_report_into_file($tpl_source){

    global $system, $outputfile, $outputmode, $gparams, $publishmode;

    $errors = null;
    $res_file = null;
    
    if($publishmode!=2){ //saves into $outputfile 

        try{

            $path_parts = pathinfo($outputfile);
            $dirname = (array_key_exists('dirname',$path_parts))?$path_parts['dirname']:'';
            
            //if folder is not defined - output into generated-reports
            if(!$dirname){
                $dirname = $system->getSysDir('generated-reports');
                if(!folderCreate($dirname, true)){
                    $errors = 'Failed to create folder for generated reports';
                }
            }else if(!file_exists($dirname)){
                $errors = "Output folder $dirname does not exist";
            }


            if($errors==null){
                if($outputmode=='js'){
                    $tpl_res = add_javascript_wrap4($tpl_source);
                }else{
                    $tpl_res = $tpl_source;
                }
                $ext =  '.'.$outputmode;

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
        
        if($gparams['void'] && $errors!=null){
            echo htmlspecialchars($errors)."\n";        
        }
    }
    
    
    if(!$gparams['void'])
    {

    if($publishmode!=1){
        //2 - download with given file name (no browser output)
        //3 - smarty report output

        if($errors!=null){
            $tpl_source = $tpl_source."<div style='color:#ff0000;font-weight:bold;'>$errors</div>";
        }

        if($outputmode=='js'){
            header("Content-type: text/javascript");
            $tpl_res = add_javascript_wrap4($tpl_source);
        }else{
            
            if($outputmode=='txt'){
                $mimetype = 'plain/text';
            }else if($outputmode=='json'){
                $mimetype = 'application/json';
            }else{
                $mimetype = "text/$outputmode";    
            }

            header("Content-type: $mimetype;charset=UTF-8");
            $tpl_res = $tpl_source;
        }
        if($publishmode==2){
            //set file name - for download
            header('Pragma: public');
            header('Content-Disposition: attachment; filename="'.$outputfile.'"'); 
            header('Content-Length: ' . strlen($tpl_res));
        }
        
        echo $tpl_res;

    }else if ($publishmode==1){ //info about success of saving into file and where to get it
        
        if($errors!=null){
            header("Content-type: text/html;charset=UTF-8");
            echo htmlspecialchars($errors);
        }else{
            ?>
            <html>
            <head>
                <meta http-equiv="content-type" content="text/html; charset=utf-8">
                <link rel="stylesheet" type="text/css" href="../../h4styles.css">
            </head>
            <body style="margin: 25px;">
            <h3>
                The following file has been updated:  <?php echo htmlspecialchars($res_file);?></h3><br />

            <?php
            $rps_recid = @$gparams['rps_id'];
            if($rps_recid){

                $link = str_replace('&amp;','&',htmlspecialchars(HEURIST_BASE_URL."viewers/smarty/updateReportOutput.php?db=".$system->dbname()."&publish=3&id=".$rps_recid));
                ?>

                <p style="font-size: 14px;">View the generated files by clicking the links below:<br /><br />
                HTML: <a href="<?=$link?>" target="_blank" style="font-weight: bold;font-size: 0.9em;"><?=$link?></a><br />
                Javascript: <a href="<?=$link?>&mode=js" target="_blank" style="font-weight: bold;font-size: 0.9em;"><?=$link?>&mode=js</a><br />

                <?php
            }

            // code for insert of dynamic report output - duplication of functionality in repMenu.html
            $surl = HEURIST_BASE_URL."viewers/smarty/showReps.php?db=".$system->dbname().
            "&ver=".$gparams['ver']."&w=".$gparams['w']."&q=".$gparams['q'].
            "&publish=1&debug=0&template=".$gparams['template'];

            if(@$gparams['rules']){
                $surl = $surl."&rules=".$gparams['rules'];
            }
            if(@$gparams['h4']){
                $surl = $surl."&h4=".$gparams['h4'];
            }

            $surl = str_replace('&amp;','&',htmlspecialchars($url, ENT_QUOTES));
            
            ?><br />
            To publish the report as dynamic (generated on-the-fly) output, use the code below.
            <br /><br />
            URL:<br />
            <textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; font-size: 10px; width: 70%; height: 60px;"
                id="code-textbox1" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);"><?php echo $surl;?></textarea>

            <br />
            Javascript wrap:<br />
            <textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; font-size: 10px; width: 70%; height: 100px;"
                id="code-textbox2" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);">
                <script type="text/javascript" src="<?php echo $surl;?>&mode=js"></script><noscript><iframe width="80%" height="70%" frameborder="0" src="<?php echo $surl;?>"></iframe></noscript>
            </textarea>
            <?php
            echo "</p></body></html>";

        }
    }
    }
}

//
// wrap smarty output into javascript function document.write
// before this it calls smarty_output_filter_strip_js to strip js
//
function smarty_output_filter_wrap_js($tpl_source, Smarty_Internal_Template $template)
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
// {wrap var=$s.f8_originalvalue dt="file" width="100" height="auto" mode=""}
//
// $params - array of 
// var - value
// dt - detail type: url, file, geo
// mode - for dt=file only:   thumbnail, link or player by default
// lbl - description
// fancybox - fills rec_Files with file info, rec_Files will be data source for fancybox viewer
// style or width,height
// limit - limits output for multivalue fields
//
function smarty_function_wrap($params, &$smarty)
{
    global $system, $is_jsallowed;    


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
                $file_desc = htmlspecialchars(strip_tags($fileinfo['ulf_Description']));
                $mimeType = $fileinfo['fxm_MimeType'];
                $file_Ext= $fileinfo['ulf_MimeExt'];
                $sourceType = $fileinfo['ulf_PreferredSource'];
                    
                $file_playerURL = HEURIST_BASE_URL.'?db='.$system->dbname().'&file='.$file_nonce.'&mode=tag';
                $file_thumbURL  = HEURIST_BASE_URL.'?db='.$system->dbname().'&thumb='.$file_nonce;
                $file_URL   = HEURIST_BASE_URL.'?db='.$system->dbname().'&file='.$file_nonce; //download
                            
                if($mode=="link") {

                    $sname = (!$originalFileName || $originalFileName=='_remote' || strpos($originalFileName,'_iiif')===0)
                        ?$external_url:$originalFileName;
                        
                    if(@$params['fancybox']){
                        $sres = $sres."<a class=\"fancybox-thumb\" data-id=\"$file_nonce\" href='"
                            .$file_URL."' target='_blank' title='".$file_desc."' $style>".$sname."</a>";
                    }else{
                        $sres = $sres."<a href='".$file_URL."' target='_blank' title='".$file_desc."' $style>".$sname."</a>";
                    }
                    
                }else 
                if($mode=="thumbnail"){

                    if(@$params['fancybox']){
                        $sres .= "<img class=\"fancybox-thumb\" data-id=\"$file_nonce\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                    }else{
                        $sres = $sres."<a href='".$file_URL."' target='_blank'>".
                        "<img class=\"\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                    }
                    
                }else{ //player is default

                    $sres = $sres.fileGetPlayerTag($system, $file_nonce, $mimeType, $params, $external_url, $size, $style); //see db_files
                    
                }
                
                if(@$params['fancybox'] && $is_jsallowed){
                    
                    $mode_3d_viewer = detect3D_byExt($file_Ext);
                    
                    $sres .= ('<script>if(rec_Files)rec_Files.push({'
                            .'rec_ID:'.$fileinfo['rec_ID']
                            .',id:"'.$file_nonce
                            .'",mimeType:"'.$mimeType
                            .'",mode_3d_viewer:"'.$mode_3d_viewer
                            .'",filename:"'.htmlspecialchars($originalFileName)
                            .'",external:"'.htmlspecialchars($external_url).'"});</script>');
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
                        $url = HEURIST_BASE_URL."viewers/gmap/mapStatic.php?".$mapsize."&q=ids:".$recid."&db=".$system->dbname(); //"&t="+d;
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
    global $outputmode, $publishmode, $outputfile;  
    
    if(!isset($error_msg)){
        $error_msg = $system->getError();
        $error_msg = (@$error_msg['message'])?$error_msg['message']:'Undefined smarty error';
    }
 
    if($outputmode=='js'){
        $error_msg = add_javascript_wrap4($error_msg, null);
    }

    if($publishmode>0 && $publishmode<4 && $outputfile!=null){ //save empty output into file
        save_report_into_file($error_msg."<div style=\"padding:20px;font-size:110%\">Currently there are no results</div>");
    }else{
        echo sanitizeString($error_msg);    
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
    foreach($results[0] as $result){
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

//
// Log record ids used in smarty reports, ignore if more than 25 records are used
//
function log_smarty_activity($system, $rec_ids){

    if($system){
        if(count($rec_ids) > 25){ // check id count
            return;
        }

        // log each id one at a time
        for ($i=0; $i < count($rec_ids); $i++) {     
            $system->user_LogActivity('custRep', array($rec_ids, count($rec_ids)), null, TRUE);
        }
    }
}
?>