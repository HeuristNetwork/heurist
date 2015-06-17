<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* 'publish' - 0 H3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only),
*
* other parameters are hquery's
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('ISSERVICE',1);
define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');
require_once(dirname(__FILE__).'/../../records/files/downloadFile.php');

include_once('../../external/geoPHP/geoPHP.inc');

require_once('libs.inc.php');

	$outputfile = null;
	$isJSout = false;

	$rtStructs = null;
	$dtStructs = null;
	$dtTerms3 = null;

	$gparams = null;
    $loaded_recs = array();
    $max_allowed_depth = 2;


	if(array_key_exists("q", $_REQUEST) &&
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

	global $smarty, $outputfile, $isJSout, $rtStructs, $dtStructs, $dtTerms, $gparams, $max_allowed_depth;

	mysql_connection_overwrite(DATABASE); //AO: mysql_connection_select - does not work since there is no access to stored procedures(getTemporalDateString) Steve uses in some query
											//TODO SAW  grant ROuser EXECUTE on getTemporalDate and any other readonly procs

	//load definitions (USE CACHE)
	$rtStructs = getAllRectypeStructures(true);
	$dtStructs = getAllDetailTypeStructures(true);
	$dtTerms = getTerms(true);

	$params["f"] = 1; //always search (do not use cache)

	$isJSout	 = (array_key_exists("mode", $params) && $params["mode"]=="js"); //use javascript wrap
	$outputfile  = (array_key_exists("output", $params)) ? $params["output"] :null;
	$publishmode = (array_key_exists("publish", $params))? intval($params['publish']):0;

	$gparams = $params; //keep to use in other functions

	if( !array_key_exists("limit", $params) ){ //not defined

		$limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
		if (!$limit || $limit<1){
			$limit = 1000; //default limit in dispPreferences
		}

		$params["limit"] = $limit; //force limit
	}

//debug error_log(print_r($params, true));
    
    if(@$params['rules']){ //search with h4 search engine
    
        $url = "";
        foreach($params as $key=>$value){
            $url = $url.$key."=".urlencode($value)."&";    
        }
    

        $url = HEURIST_BASE_URL."applications/h4/php/api/record_search.php?".$url."&idonly=1&vo=h3";
//debug error_log($url);        
        $result = loadRemoteURLContent($url);
        $qresult = json_decode($result, true);

//debug error_log(print_r($qresult, true));
        
    }else{
	    $qresult = loadSearch($params); //from search/getSearchResults.php - loads array of records based og GET request
    }
    
/*****DEBUG****///error_log(print_r($params,true));
/*****DEBUG****///error_log(print_r($qresult,true));

	if(  ( !array_key_exists('recIDs',$qresult) && !array_key_exists('records',$qresult) ) ||  $qresult['resultCount']==0 ){
		if($publishmode>0){
			$error = "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
		}else{
			$error = "<b><font color='#ff0000'>Search or Select records to see template output</font></b>";
		}

		if($isJSout){
			$error = add_javascript_wrap4($error, null);
		}

		echo $error;

		if($publishmode>0 && $outputfile!=null){ //save empty output into file
			save_report_output2("<div style=\"padding:20px;font-size:110%\">Currently there are no results</div>");
		}

		exit();
	}

	//get name of template file
	$template_file = (array_key_exists('template',$params)?$params['template']:null);
    
	//get template body from request (for execution from editor)
	$template_body = (array_key_exists('template_body',$params)?$params['template_body']:null);
    
    if($template_file){
        if(substr($template_file,-4)!=".tpl"){
            $template_file = $template_file.".tpl";
        }
        $content = file_get_contents(HEURIST_SMARTY_TEMPLATES_DIR.$template_file);
    }else{
        $content = $template_body; 
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

    //convert to array that will assigned to smarty variable
    if(array_key_exists('recIDs',$qresult)){

        $records =  explode(",", $qresult["recIDs"]);
        $results = array();
        $k = 0;
        foreach ($records as $recordID){

            $rec = loadRecord($recordID, false, true); //from search/getSearchResults.php
            
            $res1 = getRecordForSmarty($rec, 0, $k);
            $res1["recOrder"]  = $k;
            $k++;
            array_push($results, $res1);
        }  
        
    
    }else{

	    $records =  $qresult["records"];
	    $results = array();
	    $k = 0;
	    foreach ($records as $rec){

		    $res1 = getRecordForSmarty($rec, 0, $k);
            $res1["recOrder"]  = $k;
		    $k++;
		    array_push($results, $res1);
        }  
    } 
	//activate default template - generic list of records

	$smarty->assign('results', $results);

	ini_set( 'display_errors' , 'false'); // 'stdout' );
	$smarty->error_reporting = 0;

	if($template_body)
	{	//execute template from string - modified temoplate in editor
/*****DEBUG****///error_log(">>>".$template_body."<<<");
/*****DEBUG****///error_log(">>>>>>>".$replevel."<<<<<<");

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
			$smarty->registerFilter('output','save_report_output');
		}else if($isJSout){
			$smarty->registerFilter('output','add_javascript_wrap5');
		}

		//$smarty->unregisterFilter('post','add_javascript_wrap');
	}

	try{
			$smarty->display($template_file);
	} catch (Exception $e) {
    		echo 'Exception on execution: ',  $e->getMessage(), "\n";
	}

	//$tpl_vars = $smarty->get_template_vars();
	//var_dump($tpl_vars);


	//DEBUG stuff
	//@todo - return the list of record types - to obtain the applicable templates
	//echo "query result = ".print_r($qresult,true)."\n";
	//header("Content-type: text/javascript");

	//header('Content-type: text/html; charset=utf-8');
	//echo json_format( $qresult, true);
	//echo "<br/>***<br/>";

	//echo json_format( $results, true);
	//END DEBUG stuff
}

//
// save report output as file (if there is parameter output)
//
function save_report_output($tpl_source, Smarty_Internal_Template $template)
{
	save_report_output2($tpl_source);
}

function save_report_output2($tpl_source){

	global $outputfile, $isJSout, $gparams;

	$errors = null;
	$res_file = null;

	$publishmode = (array_key_exists("publish", $gparams))? intval($gparams['publish']):0;

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

	if($publishmode==0){

		if($errors!=null){
			$tpl_source = $tpl_source."<div style='color:#ff0000;font-weight:bold;'>$errors</div>";
		}

		if($isJSout){
			header("Content-type: text/javascript");
			$tpl_res = add_javascript_wrap4($tpl_source);
		}else{
			header("Content-type: text/html");
			$tpl_res = $tpl_source;
		}
		echo $tpl_res;

	}else if ($publishmode==1){

		header("Content-type: text/html");

		if($errors!=null){
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
	The following file has been updated:  <?=$res_file?></h2><br>
<?php
			$rps_recid = @$gparams['rps_id'];
			if($rps_recid){

				$link = HEURIST_BASE_URL."viewers/smarty/updateReportOutput.php?db=".HEURIST_DBNAME."&publish=3&id=".$rps_recid;
?>
<p style="font-size: 14px;">You may view the content of report by click hyperlinks below:<br />
HTML: <a href="<?=$link?>" target="_blank" style="font-weight: bold;"><?=$link?></a><br />
Javascript: <a href="<?=$link?>&mode=js" target="_blank" style="font-weight: bold;"><?=$link?>&mode=js</a><br />
<?php
			}

// code for insert of dynamic report output - duplication of functionality in repMenu.html
			$surl = HEURIST_BASE_URL."viewers/smarty/showReps.php?db=".HEURIST_DBNAME.
						"&ver=".$gparams['ver']."&w=".$gparams['w']."&q=".$gparams['q'].
						"&publish=1&debug=0&template=".$gparams['template'];

?><br /><br />
	If you wish to publish the link to report that returns dynamic report output use the following code below. Copy to clipboard by hitting Ctrl-C or [Enter]
	<br />
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

//
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
// convert record array to array to be assigned to smarty variable
//
// @todo - implement as method
function getRecordForSmarty($rec, $recursion_depth, $order){

	global $rtStructs;

	if(!$rec){
		return null;
	}
	else
	{
		$relRT = (defined('RT_RELATION')?RT_RELATION:0);
		$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
		$relTrgDT = (defined('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);

/*****DEBUG****///error_log("RElation constants=".$relRT."  ".$relSrcDT."    ".$relTrgDT);

		$record = array();
		$recTypeID = null;


/*****DEBUG****///error_log("REC=".print_r($rec, true));
		$record["recOrder"] = $order;

		//loop for all record properties
		foreach ($rec as $key => $value){
           
            
			$pos = strpos($key,"rec_");
			if(is_numeric($pos) && $pos==0){
				//array_push($record, array(substr($key,4) => $value));
				$record['rec'.substr($key,4)] = $value;

				if($key=="rec_RecTypeID"){ //additional field
					$recTypeID = $value;
                    $record["recTypeID"] = $recTypeID;
					$record["recTypeName"] = $rtStructs['typedefs'][$value]['commonFields'][ $rtStructs['typedefs']['commonNamesToIndex']['rty_Name'] ];
				}else if ($key=="rec_ID"){ //load woottext once per record

					$record["recWootText"] = getWootText($value);
				}

			}
			else  if ($key == "details")
			{
                
				$details = array();
				foreach ($value as $dtKey => $dtValue){
					$dt = getDetailForSmarty($dtKey, $dtValue, $recursion_depth, $recTypeID, $record['recID']);
					if($dt){
						$record = array_merge($record, $dt);
					}
				}

				//$record['rec'.substr($key,4)] = $details;
			}
		}

		/* find related records */
		if($recursion_depth==0 && $relRT>0 && $relSrcDT>0 && $relTrgDT>0){

			$dtValue = array();

			$from_res = mysql_query('select recDetails.*
	                           from recDetails
	                      left join Records on rec_ID = dtl_RecID
	                          where dtl_DetailTypeID = '.$relSrcDT.
	                           ' and rec_RecTypeID = '.$relRT.
	                           ' and dtl_Value = ' . $record["recID"]);        //primary resource
			$to_res = mysql_query('select recDetails.*
	                         from recDetails
	                    left join Records on rec_ID = dtl_RecID
	                        where dtl_DetailTypeID = '.$relTrgDT.
	                         ' and rec_RecTypeID = '.$relRT.
	                         ' and dtl_Value = ' . $record["recID"]);          //linked resource

			if (mysql_num_rows($from_res) > 0  ||  mysql_num_rows($to_res) > 0) {

					while ($reln = mysql_fetch_assoc($from_res)) {
						$bd = fetch_relation_details($reln['dtl_RecID'], true);
						array_push($dtValue, $bd);
					}
					while ($reln = mysql_fetch_assoc($to_res)) {
						$bd = fetch_relation_details($reln['dtl_RecID'], false);
						array_push($dtValue, $bd);
					}


					$dt = getDetailForSmarty(-1, $dtValue, 2, $recTypeID, $record["recID"]);
					if($dt){
						$record = array_merge($record, $dt);
					}
			}

			/* OLD WAY
			if($relSrcDT>0){

				//current record is source
				$rel_query = "select r2.dtl_Value as id from recDetails r1 left join recDetails r2 on r1.dtl_RecID = r2.dtl_RecID and r2.dtl_DetailTypeID = $relTrgDT
where r1.dtl_DetailTypeID = $relSrcDT and r1.dtl_Value = ".$record["recID"];

				$to_res = mysql_query($rel_query);

				while ($reln = mysql_fetch_assoc($to_res)) {
					array_push($dtValue, $reln);
					$k++;
				}//while

			}

			if($relTrgDT>0){
				//current record is target
				$rel_query = "select r2.dtl_Value as id from recDetails r1 left join recDetails r2 on r1.dtl_RecID = r2.dtl_RecID and r2.dtl_DetailTypeID = $relSrcDT
where r1.dtl_DetailTypeID = $relTrgDT and r1.dtl_Value = ".$record["recID"];

				$to_res = mysql_query($rel_query);

				while ($reln = mysql_fetch_assoc($to_res)) {
					array_push($dtValue, $reln);
					$k++;
				}//while
			}

			if($k>1){
					$dt = getDetailForSmarty(-1,$dtValue, $recursion_depth, $recTypeID, $record["recID"]);
					if($dt){
						$record = array_merge($record, $dt);
					}
			}
			*/

		}

		return $record;
	}
}

/**
*
*/
function _add_term_val($res, $val){

	if($val){
		if(strlen($res)>0) $res = $res.", ";
		$res = $res.$val;
	}
	return $res;
}


//
// convert details to array to be assigned to smarty variable
// $dtKey - detailtype ID, if <1 this dummy relationship detail
//
// @todo - implement as method
function getDetailForSmarty($dtKey, $dtValue, $recursion_depth, $recTypeID, $recID){

	global $dtStructs, $dtTerms, $rtStructs, $loaded_recs, $max_allowed_depth;
    
	$dtNames = $dtStructs['names'];
	$rtNames = $rtStructs['names'];
	$dty_fi = $dtStructs['typedefs']['fieldNamesToIndex'];
    $rt_structure = null;
    $issingle = true;


/*****DEBUG****///	if($dtKey==9){
/*****DEBUG****///error_log("KEY=".$dtKey."   NAME=".$dtNames[$dtKey]);
/*****DEBUG****///error_log("dtValue=".print_r($dtValue, true));
/*****DEBUG****///	}


	if($dtKey<1 || $dtNames[$dtKey]){

		if($dtKey<1){
			$dt_label = "Relationship";
            $dtname = "Relationship";
            $issingle = false;
            $dtDef = "dummy";
/*****DEBUG****///error_log("111>>>>>".print_r($dtValue, true));
/*****DEBUG****///error_log("222>>>>>".$recTypeID);

		}else{
			$rt_structure = $rtStructs['typedefs'][$recTypeID]['dtFields'];
            $rst_fi = $rtStructs['typedefs']['dtFieldNamesToIndex'];
			$dtlabel_index = $rst_fi['rst_DisplayName'];
            $dtmaxval_index = $rst_fi['rst_MaxValues'];
			if(array_key_exists($dtKey, $rt_structure)){
				$dt_label = $rt_structure[$dtKey][ $dtlabel_index ];
				//$dtname = getVariableNameForSmarty($dt_label);
			}
			$dtname = "f".$dtKey; //ART 23013-12-09 getVariableNameForSmarty($dtNames[$dtKey]);
            
            $dtDef = @$dtStructs['typedefs'][$dtKey]['commonFields'];
		}


	if(is_array($dtValue)){ //complex type - need more analize
		$res = null;

		if($dtDef){
            
            if($dtKey<1){
                $detailType =  "relmarker";
            }else{
                $detailType =  $dtDef[ $dty_fi['dty_Type']  ];
                //detect single or repeatable - if repeatabel add as array for enum and pointers
                $dt_maxvalues = @$rt_structure[$dtKey][$dtmaxval_index];
                if($dt_maxvalues){
                    $issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)===1);
                }else{
                    $issingle = false;
                }
                $issingle = false; //ARTEM 2014-01-16
            }
            

			switch ($detailType) {
			case 'enum':

				$fi = $dtTerms['fieldNamesToIndex'];

				$res_id = "";
				$res_cid = "";
				$res_code = "";
				$res_label = "";
                $res = array();
                
				foreach ($dtValue as $key => $value){
					if(array_key_exists($value, $dtTerms['termsByDomainLookup']['enum'])){
						$term = $dtTerms['termsByDomainLookup']['enum'][$value];

						$res_id = _add_term_val($res_id, $value);
						$res_cid = _add_term_val($res_cid, $term[ $fi['trm_ConceptID'] ]);
						$res_code = _add_term_val($res_code, $term[ $fi['trm_Code'] ]);
						$res_label = _add_term_val($res_label, $term[ $fi['trm_Label'] ]);
                    
                        //NOTE id and label are for backward
                        array_push($res, array("id"=>$value, "internalid"=>$value, "code"=>$term[ $fi['trm_Code'] ], "label"=>$term[ $fi['trm_Label'] ], "term"=>$term[ $fi['trm_Label'] ], "conceptid"=>$term[ $fi['trm_ConceptID'] ]));
					}
				}
                $res_united = array("id"=>$res_id, "internalid"=>$res_id, "code"=>$res_code, "term"=>$res_label, "label"=>$res_label, "conceptid"=>$res_cid);

                if(count($res)>0){
                        if($issingle){
                            $res = array( $dtname =>$res_united );    
                        }else{
                            $res = array( $dtname =>$res[0], $dtname."s" =>$res );    
                        }
                }

				/*if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res );
				}*/

			break;

			case 'date':

				$res = "";
				$origvalues = array();
				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";
					$res = $res.temporalToHumanReadableString($value);
					array_push($origvalues, temporalToHumanReadableString($value));
				}
				if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res, $dtname."_originalvalue"=>$origvalues);
				}
				break;

			case 'file':
			   //get url for file download

			   //if image - special case

				$res = "";
				$origvalues = array();

				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";
					$res = $res.$value['file']['URL'];


					//original value keeps the whole 'file' array
					array_push($origvalues, $value['file']);

					//$dtname2 = $dtname."_originalvalue";
					//$arres = array_merge($arres, array($dtname2=>$value['file']));
/*****DEBUG****///error_log(">>>>>".$dtname2."= ".print_r($value['file'],true));

				}

				if(strlen($res)==0){
					$res = null;
				}else{
					$res = array($dtname=>$res, $dtname."_originalvalue"=>$origvalues);
					//array_merge($arres, array($dtname=>$res));
				}

			break;
/* NOT USED ANYMORE
			case 'urlinclude':
				//
				$res = "";
				$arres = array();

				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";

					$arr = explode('|', $value);
					$res = $res.$arr[0];

					//original value keeps source and type
					$dtname2 = $dtname."_originalvalue";
					$arres = array_merge($arres, array($dtname2=>$value));
				}
				if(strlen($res)==0){
					$res = null;
				}else{
					$res = array_merge($arres, array($dtname=>$res));
				}

			break;
*/

			case 'geo':

				$res = "";
				$arres = array();
				foreach ($dtValue as $key => $value){
/*****DEBUG****///error_log("GEO=>>>>".print_r($value, true));

						//original value keeps whole geo array
						$dtname2 = $dtname."_originalvalue";
						$value['geo']['recid'] = $recID;
						$arres = array_merge($arres, array($dtname2=>$value['geo']));

						$res = $value['geo']['wkt'];
						break; //only one geo location at the moment

						/*
						$geom = geoPHP::load($value['geo']['wkt'],'wkt');
						if(!$geom->isEmpty()){
							$point = $geom->centroid();
							$res = "http://maps.google.com/maps?z=18&q=".$point->y().",".$point->x();
							break;
						}*/
				}

				if(strlen($res)==0){
					$res = null;
				}else{
					$res = array_merge($arres, array($dtname=>$res));
					//$res = array( $dtname=>$res );
				}

				break;

			case 'separator':
			break;
			case 'calculated':
			break;
			case 'fieldsetmarker':
			break;

			case 'relmarker':
			case 'resource': // link to another record type

//error_log("dtValue>>>>>".print_r($dtValue,true));
//break;            
            
				//@todo - parsing will depend on depth level
				// if there are not mentions about this record type in template (based on obtained array of variables)
				// we will create href link to this record
				// otherwise - we have to obtain this record (by ID) and add subarray

				$res = array();
				$rectypeID = null;
				$prevID = null;
/*****DEBUG****///error_log("dtValue>>>>>".print_r($dtValue,true));
				$order_sub = 0;

				foreach ($dtValue as $key => $value){

					if($recursion_depth<$max_allowed_depth && (array_key_exists('id',$value) || array_key_exists('RelatedRecID',$value)))
					{

						//this is record ID
						if(array_key_exists('RelatedRecID',$value)){
							$recordID = $value['RelatedRecID']['rec_ID'];
/*****DEBUG****///error_log(">>>>value=".print_r($value, true));
						}else{
							$recordID = $value['id'];
						}

						//get full record info
                        if(@$loaded_recs[$recordID]){
                            //already loaded
                            $rec0 = $loaded_recs[$recordID];
                            $rec0["recOrder"] = count($res);
                            array_push($res, $rec0);
                            
                            $rectypeID = $rec0['recTypeID'];
                            
                        }else{
                        
						    $record = loadRecord($recordID, false, true); //from search/getSearchResults.php

						    $res0 = null;
						    if(true){  //64719  45171   48855    57247
							    $res0 = getRecordForSmarty($record, $recursion_depth+1, $order_sub); //@todo - need to
							    $order_sub++;
						    }

						    if($res0){

							    if($rectypeID==null && @$res0['recRecTypeID']){
								    $rectypeID = $res0['recRecTypeID'];
								    /* TEMP DEBUG
								    if(array_key_exists($rectypeID, $rtNames))
								    {
									    $pointerIDs = ($dtKey<1) ?"" :$dtDef[ $dty_fi['dty_PtrTargetRectypeIDs'] ];
									    $isunconstrained = ($pointerIDs=="");
    if($isunconstrained){
    /*****DEBUG****///error_log($dt_label.">>>>>>>");
    /*}
								    }            */
							    }

							    //add relationship specific variables
							    if(array_key_exists('RelatedRecID',$value) && array_key_exists('RelTerm',$value)){
									    $res0["recRelationType"] = $value['RelTerm'];
									    /*if(array_key_exists('interpRecID', $value)){
										    $record = loadRecord($value['interpRecID']);
										    $res0["recRelationInterpretation"] = getRecordForSmarty($record, $recursion_depth+2, $order);
									    }*/
									    if(array_key_exists('Notes', $value)){
										    $res0["recRelationNotes"] = $value['Notes'];
									    }
									    if(array_key_exists('StartDate', $value)){
										    $res0["recRelationStartDate"] = temporalToHumanReadableString($value['StartDate']);
									    }
									    if(array_key_exists('EndDate', $value)){
										    $res0["recRelationEndDate"] = temporalToHumanReadableString($value['EndDate']);
									    }

    /*****DEBUG****///error_log(">>>>RECID=".$recordID." >>>>>".print_r($res0, true));
							    }

                                $loaded_recs[$recordID] = $res0;
                                $res0["recOrder"] = count($res);
							    array_push($res, $res0);

                            }
                        }
					}
                }//for each repeated value

				if( count($res)>0 && array_key_exists($rectypeID, $rtNames))
				{
                    /*                    
					$pointerIDs = ($dtKey<1) ?"" :$dtDef[ $dty_fi['dty_PtrTargetRectypeIDs'] ];

					if($pointerIDs==""){ //unconstrainted pointer - we will use as name of variable display name for current record type
						$recordTypeName = $dt_label;
					}else{
						$recordTypeName = $rtNames[$rectypeID];
					}*/
                    

                    
                    if(@$dtname){
                        /* ART 2013-012-09                    
					    $recordTypeName = $dt_label;
					    $recordTypeName = getVariableNameForSmarty($recordTypeName, false);
					    $res = array( $recordTypeName."s" =>$res, $recordTypeName =>$res[0] );
                        */

//error_log(">>>>>>".$dtKey ."   ".$dtname."   ".$issingle);
                        
                        if($issingle){
                            $res = array( $dtname =>$res[0] );    
                        }else{
                            $res = array( $dtname =>$res[0], $dtname."s" =>$res );    
                        }
                    }
                    
				}else{
					$res = null;
				}

			break;
			default:
				// repeated basic detail types
				$res = "";
				$origvalues = array();
				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";
					$res = $res.$value;
					array_push($origvalues, $value);
				}
				if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res, $dtname."_originalvalue"=>$origvalues);
				}


			}
			//it depends on detail type - need specific behaviour for each type
			//foreach ($value as $dtKey => $dtValue){}
		}//end switch

		return $res;
	}
	else {
			return array( $dtname=>$dtValue );
	}

	}else{ //name is not defined
			return null;
	}

}

//
// Returns the united woot text
//
function getWootText($recID){

	$res = "";

	$woot = loadWoot(array("title"=>"record:".$recID));
	if(@$woot["success"])
	{
		if(@$woot["woot"]){

			$chunks = $woot["woot"]["chunks"];
			$cnt = count($chunks);

			for ($i = 0; $i < $cnt; $i++) {
    			$chunk = $chunks[$i];
    			if(@$chunk["text"]){
					$res = $res.$chunk["text"];
				}
			}//for
		}
	}else if (@$woot["errorType"]) {
		$res = "WootText: ".$woot["errorType"];
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
//
function smarty_function_wrap($params, &$smarty)
{

	if($params['var']){

/*****DEBUG****///error_log("WRAP>>>>".print_r($params,true));

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


//!!!!!
/*****DEBUG****///error_log("WARP VALUE>>>>".$mode."  ".print_r($values,true));

			$sres = "";

			foreach ($values as $value){

				$type_media = $value['mediaType'];

				if($mode=="thumbnail"){

			 		$sres = $sres."<a href='".($value['playerURL']?$value['playerURL']:$value['URL'])."' target='_blank'>".
							"<img src='".$value['thumbURL']."' title='".$value['description']."'/></a>";

				}else if($mode=="player"){

					if($type_media == 'image'){
						$sres = $sres."<img src='".$value['URL']."' ".$size." title='".$value['description']."'/>"; //.$value['origName'];
					}else if($value['remoteSource']=='youtube' ){

						$sres = $sres.linkifyYouTubeURLs($value['URL'], $size);

                    }else if($value['remoteSource']=='gdrive' ){
                        $sres = $sres.linkifyGoogleDriveURLs($value['URL'], $size);

					}else if($type_media=='document' && $value['mimeType']) {

						$sres = $sres.'<embed $size name="plugin" src="'.$value['URL'].'" type="'.$value['mimeType'].'" />';

					}else if($type_media=='video'){
						//UNFORTUNATELY HTML5 dores not work properly
						// $sres = $sres.createVideoTag($value['URL'], $value['mimeType'], $size);

						$sres = $sres.createVideoTag2($value['URL'], $value['mimeType'], $size);

					}else if($type_media=='audio'){
						$sres = $sres.createAudioTag($value['URL'], $value['mimeType']);
					}else{
						$sres = $sres."Unsupported media type ".$type_media;
					}

				}else{

					if($type_media == 'image'){
						$sres = $sres."<img src='".$value['URL']."' ".$size." title='".$value['description']."'/>"; //.$value['origName'];
					}else if( $value['remoteSource']=='youtube' ){
						$sres = $sres.linkifyYouTubeURLs($value['URL'], $size);
                    }else if( $value['remoteSource']=='gdrive' ){
                        $sres = $sres.linkifyGoogleDriveURLs($value['URL'], $size);
					}else{
						$sres = $sres."<a href='".$value['URL']."' target='_blank' title='".$value['description']."'>".$value['origName']."</a>";
					}

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
						$url = HEURIST_SITE_PATH."viewers/map/showMapUrl.php?".$mapsize."&q=ids:".$recid."&db=".HEURIST_DBNAME; //"&t="+d;
						return "<img src=\"".$url."\" ".$size."/>";
					}
				}
			}
			return $res;
		}
		/*

		NOT USED ANYMORE


		else if($dt=="urlinclude") {
			//insert image or youtube if appropriate
			$value = $params['var'];
			//get url, source and type
			$acfg = explode('|', $value);
			$url = $acfg[0];


			if(count($acfg)<3){
	   			$oType = detectSourceAndType($url);
	   			$url_type = $oType[0];
	   			$url_source = $oType[1];
	   		}else{
	   			$url_source = $acfg[1];
	   			$url_type = $acfg[2];
	   		}

/*****DEBUG****///error_log(">>>>>>>>".$value."  type=".$url_type."  source=".$url_source);
/*
			if($url_type == "Image"){

				return "<img src='".$url."' width='300px'/>";

			}else if ($url_type == "Text/HTML"){
				//load file and return its value as string


			}else if ($url_type == "Video"){

				if($url_source == "Youtube"){
					$size = "";
					if($width=="" && $height==""){
						$size = "width='420' height='345'";
					}else {
						if($width!=""){
							$size = "width='".$width."'";
						}
						if($height!=""){
							$size = $size." height='".$height."'";
						}
					}

					return linkifyYouTubeURLs($url, $size);
				}
			}

		}*/
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