<?php

/**
 * showReps.php
 *
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
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

define('SEARCH_VERSION', 1);

// called by applyCredentials  require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');
require_once('libs.inc.php');

	$outputfile = null;
	$isJSwrap = false;
	$publishmode = 0;

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

	global $smarty, $outputfile, $isJSwrap, $publishmode;

	mysql_connection_db_select(DATABASE);

	//load definitions (USE CACHE)
	$rtStructs = getAllRectypeStructures(true);
	$dtStructs = getAllDetailTypeStructures(true);
	$dtTerms = getTerms(true);

	$params["f"] = 1; //always search (do not use cache)

	$isJSwrap	 = (array_key_exists("mode", $params) && $params["mode"]=="js"); //use javascript wrap
	$outputfile  = (array_key_exists("output", $params))? $params["output"] :null;
	$publishmode = (array_key_exists("publish", $params))?intval($params['publish']):0;

	if( !array_key_exists("limit", $params) ){ //not defined

		$limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
		if (!$limit || $limit<1){
			$limit = 1000; //default limit in dispPreferences
		}

		$params["limit"] = $limit; //force limit
	}

	$qresult = loadSearch($params); //from search/getSearchResults.php - loads array of records based og GET request

	if(!array_key_exists('records',$qresult) ||  $qresult['resultCount']==0 ){
		if($publishmode>0){
			$error = "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
		}else{
			$error = "<b><font color='#ff0000'>Search or Select records to see template output</font></b>";
		}
		if($isJSwrap){
			$error = add_javascript_wrap4($error, null);
		}
		echo $error;

		exit();
	}

	//get name of template file
	$template_file = (array_key_exists('template',$params)?$params['template']:null);
	//get template body from request (for execution from editor)
	$template_body = (array_key_exists('template_body',$params)?$params['template_body']:null);

	//convert to array that will assigned to smarty variable
	$records =  $qresult["records"];
	$results = array();
	foreach ($records as $rec){

		$res1 = getRecordForSmarty($rec, 0);
		array_push($results, $res1);
	}
	//activate default template - generic list of records

	$smarty->assign('results', $results);

	ini_set( 'display_errors' , 'false'); // 'stdout' );
	$smarty->error_reporting = 0;

	if($template_body)
	{	//execute template from string - modified temoplate in editor
//DEBUG
//error_log(">>>".$template_body."<<<");
//error_log(">>>>>>>".$replevel."<<<<<<");

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

		$smarty->display($template_file);
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
		}else if($isJSwrap){
			$smarty->registerFilter('output','add_javascript_wrap5');
		}
		$smarty->display($template_file);

		//$smarty->unregisterFilter('post','add_javascript_wrap');

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
	global $outputfile, $isJSwrap, $publishmode;

	$errors = null;

	try{

		$path_parts = pathinfo($outputfile);
		$dirname = (array_key_exists('dirname',$path_parts))?$path_parts['dirname']:"";


		if(!file_exists($dirname)){
			$errors = "Output folder $dirname does not exist";
		}else{
			if($isJSwrap){
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


	if($publishmode<2){

		if($errors!=null){
			$tpl_source = $tpl_source."<div style='color:#ff0000;font-weight:bold;'>$errors</div>";
		}

		if($isJSwrap){
			header("Content-type: text/javascript");
			$tpl_res = add_javascript_wrap4($tpl_source);
		}else{
			header("Content-type: text/html");
			$tpl_res = $tpl_source;
		}
		echo $tpl_res;
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
// convert record array to arrray to be assigned to smarty variable
//
// @todo - implement as method
function getRecordForSmarty($rec, $recursion_depth){

	global $rtStructs;

	if(!$rec){
		return null;
	}
	else
	{
		$relRT = (defined('RT_RELATION')?RT_RELATION:0);
		$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
		$relTrgDT = (defined('DT_LINKED_RESOURCE')?DT_LINKED_RESOURCE:0);

		$record = array();
		$recTypeID = null;

		//loop for all record properties
		foreach ($rec as $key => $value){
			$pos = strpos($key,"rec_");
			if(is_numeric($pos) && $pos==0){
				//array_push($record, array(substr($key,4) => $value));
				$record['rec'.substr($key,4)] = $value;

				if($key=="rec_RecTypeID"){ //additional field
					$recTypeID = $value;
					$record["recTypeName"] = $rtStructs['typedefs'][$value]['commonFields'][ $rtStructs['typedefs']['commonNamesToIndex']['rty_Name'] ];
				}else if ($key=="rec_ID"){ //load woottext once per record

					$record["recWootText"] = getWootText($value);
				}

			}
			else  if ($key == "details")
			{

				$details = array();
				foreach ($value as $dtKey => $dtValue){
					$dt = getDetailForSmarty($dtKey, $dtValue, $recursion_depth, $recTypeID);
					if($dt){
						$record = array_merge($record, $dt);
					}
				}

				//$record['rec'.substr($key,4)] = $details;
			}
		}

		if($relRT>0 && $relTrgDT>0){
			/* find related records */
			$to_res = mysql_query('select recDetails.dtl_RecID as id
	                         from recDetails
	                    left join Records on rec_ID = dtl_RecID
	                        where dtl_DetailTypeID = '.$relTrgDT.
	                         ' and rec_RecTypeID = '.$relRT.
	                         ' and dtl_Value = ' . $record["recID"]);          //linked resource


			$dtValue = array();
			$k=1;
			while ($reln = mysql_fetch_assoc($to_res)) {
				array_push($dtValue, $reln);
				$k++;
			}//while
			if($k>1){
				$dt = getDetailForSmarty(-1,$dtValue, $recursion_depth, $recTypeID);
				if($dt){
					$record = array_merge($record, $dt);
				}
			}
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
function getDetailForSmarty($dtKey, $dtValue, $recursion_depth, $recTypeID){

	global $dtStructs, $dtTerms, $rtStructs;
	$dtNames = $dtStructs['names'];
	$rtNames = $rtStructs['names'];
	$dty_fi = $dtStructs['typedefs']['fieldNamesToIndex'];

	if($dtKey<1 || $dtNames[$dtKey]){

		if($dtKey<1){
			$dt_label = "Relationship";
		}else{
			$rt_structure = $rtStructs['typedefs'][$recTypeID]['dtFields'];
			$dtlabel_index = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
			if(array_key_exists($dtKey,$rt_structure)){
				$dt_label = $rt_structure[$dtKey][ $dtlabel_index ];
			}
			$dtname = getVariableNameForSmarty($dtNames[$dtKey]);
		}


	if(is_array($dtValue)){ //complex type - need more analize
		$res = null;

		$dtDef = ($dtKey<1) ? "dummy" :$dtStructs['typedefs'][$dtKey]['commonFields'];

		if($dtDef){
			$detailType = ($dtKey<1) ?"relmarker" :$dtDef[ $dty_fi['dty_Type']  ];

			switch ($detailType) {
			case 'enum':

				$fi = $dtTerms['fieldNamesToIndex'];

				$res_id = "";
				$res_cid = "";
				$res_code = "";
				$res_label = "";

				foreach ($dtValue as $key => $value){
					if(array_key_exists($value, $dtTerms['termsByDomainLookup']['enum'])){
						$term = $dtTerms['termsByDomainLookup']['enum'][$value];

						$res_id = _add_term_val($res_id, $value);
						$res_cid = _add_term_val($res_cid, $term[ $fi['trm_ConceptID'] ]);
						$res_code = _add_term_val($res_code, $term[ $fi['trm_Code'] ]);
						$res_label = _add_term_val($res_label, $term[ $fi['trm_Label'] ]);
					}
				}

				$res = array("id"=>$res_id, "code"=>$res_code, "label"=>$res_label, "conceptid"=>$res_cid);
				$res = array( $dtname=>$res );

				/*if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res );
				}*/

			break;

			case 'file':
			   //get url for file download

			   //if image - special case

				$res = "";
				$arres = array();

				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";
					$res = $res.$value['file']['URL'];

					//original value keeps the whole 'file' array
					$dtname2 = $dtname."_originalvalue";
					$arres = array_merge($arres, array($dtname2=>$value['file']));
				}
				if(strlen($res)==0){
					$res = null;
				}else{
					$res = array_merge($arres, array($dtname=>$res));
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
			break;
			case 'separator':
			break;
			case 'calculated':
			break;
			case 'fieldsetmarker':
			break;

			case 'relmarker':
			case 'resource': // link to another record type

				//@todo - parsing will depend on depth level
				// if there are not mentions about this record type in template (based on obtained array of variables)
				// we will create href link to this record
				// otherwise - we have to obtain this record (by ID) and add subarray

				$res = array();
				$rectypeID = null;
				$prevID = null;
//error_log("dtValue>>>>>".print_r($dtValue,true));

				foreach ($dtValue as $key => $value){

					if($recursion_depth<2 && array_key_exists('id',$value))
					{

						//this is record ID
						$recordID = $value['id'];

						//get full record info
						$record = loadRecord($recordID); //from search/getSearchResults.php

						$res0 = null;
						if(true){  //64719  45171   48855    57247
							$res0 = getRecordForSmarty($record, $recursion_depth+1); //@todo - need to
						}

						if($res0){
							array_push($res, $res0);
							if($rectypeID==null){
								$rectypeID = $res0['recRecTypeID'];
							}
						}

					}
				}//for each repeated value

				if( count($res)>0 && array_key_exists($rectypeID, $rtNames))
				{
					$pointerIDs = ($dtKey<1) ?"" :$dtDef[ $dty_fi['dty_PtrTargetRectypeIDs'] ];
					if($pointerIDs==""){ //unconstrainted pointer - we will use as name of variable display name for current record type
						$recordTypeName = $dt_label;
					}else{
						$recordTypeName = $rtNames[$rectypeID];
					}
					$recordTypeName = getVariableNameForSmarty($recordTypeName, false);
					$res = array( $recordTypeName."s" =>$res, $recordTypeName =>$res[0] );
				}else{
					$res = null;
				}

			break;
			default:
				// repeated basic detail types
				$res = "";
				foreach ($dtValue as $key => $value){
					if(strlen($res)>0) $res = $res.", ";
					$res = $res.$value;
				}
				if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res );
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


		if(array_key_exists('dt',$params)){
			$dt = $params['dt'];
		}

		$label = "";
		if(array_key_exists('lbl',$params) && $params['lbl']!=""){
			$label = $params['lbl'].": ";
		}
		$width = "";
		if(array_key_exists('width',$params) && $params['width']!=""){
			$width = $params['width'];
			if(is_numeric($width)<0){
				$width = $width."px";
			}
		}
		$height = "";
		if(array_key_exists('height',$params) && $params['height']!=""){
			$height = $params['height'];
			if(is_numeric($height)<0){
				$height = $height."px";
			}
		}

		$size = "";
		if($width=="" && $height==""){
			$size = "width='300px'";
		}else {
			if($width!=""){
				$size = "width='".$width."'";
			}
			if($height!=""){
				$size = $size." height='".$height."'";
			}
		}

		if($dt=="file"){
			//insert image or link
			$value = $params['var'];

			if( strpos($value['type'],'image')==0 ){
				return "<img src='".$value['URL']."' ".$size." title='".$value['description']."'/>".$value['origName'];
			}else{
				return "<a href='".$value['URL']."' target='_blank' title='".$value['description']."'>".$value['origName']."</a>";
			}

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

//error_log(">>>>>>>>".$value."  type=".$url_type."  source=".$url_source);

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
    		return $label.$params['var'].'<br/>';
		}
	}else{
		return '';
	}
}

?>
