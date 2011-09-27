<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__).'/../../common/config/defineFriendlyServers.php');
require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once('libs.inc.php');

//error_log(">>>>>>>>>>>>>".print_r($_REQUEST, true));

	mysql_connection_db_select(DATABASE);

	//load definitions (USE CACHE)
	$rtStructs = getAllRectypeStructures(true);
	$dtStructs = getAllDetailTypeStructures(true);
	$dtTerms = getTerms(true);

	$qresult = loadSearch($_REQUEST); //from search/getSearchResults.php - loads array of records based og GET request

	if(!array_key_exists('records',$qresult)){
//error_log(">>>>>>>>NOTHING FOUND");
		echo "Select somthing in search result to execute the template";
		exit();
	}

	//get name of tempalte file
	$template_file = (array_key_exists('template',$_REQUEST)?$_REQUEST['template']:null);
	//get template body from request (for execution from editor)
	$template_body = (array_key_exists('template_body',$_REQUEST)?$_REQUEST['template_body']:null);

	$isdebug = (array_key_exists('debug',$_REQUEST)?$_REQUEST['debug']:0);

//DEBUG error_log(">>>>>>>>".$template_file);
	//convert to array that will assigned to smarty variable
	$records =  $qresult["records"];
	$results = array();
	foreach ($records as $rec){
		$res1 = getRecordForSmarty($rec, 0);
		array_push($results, $res1);
	}
	//activate default template - generic list of records

	$smarty->assign('results', $results);

	if($template_body)
	{	//execute template from string - modified temoplate in editor
//DEBUG error_log(">>>".$template_body."<<<");
error_log(">>>>>>>".$isdebug."<<<<<<");

		$smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';
		$smarty->debugging = ($isdebug=="1");

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
//error_log(">>>>>>>>PRINT".$template_file);
		$smarty->debugging = false;
		$smarty->display($template_file);
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

exit();

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

		$record = array();

		//loop for all record properties
		foreach ($rec as $key => $value){
			$pos = strpos($key,"rec_");
			if(is_numeric($pos) && $pos==0){
				//array_push($record, array(substr($key,4) => $value));
				$record['rec'.substr($key,4)] = $value;

				if($key=="rec_RecTypeID"){ //additional field
					$record["recTypeName"] = $rtStructs['typedefs'][$value]['commonFields'][0];
				}

			}
			else  if ($key == "details")
			{

				$details = array();
				foreach ($value as $dtKey => $dtValue){
					$dt = getDetailForSmarty($dtKey, $dtValue, $recursion_depth);
					if($dt){
//DEBUG error_log("ADD ".$kk[0]."=".$dt[$kk[0]]);
						//$kk = array_keys($dt);
						//$record[ $kk[0] ] = $dt[ $kk[0] ];

						$record = array_merge($record, $dt);
					}
				}

				//$record['rec'.substr($key,4)] = $details;
			}
		}

		return $record;
	}
}

//
// convert details to array to be assigned to smarty variable
//
// @todo - implement as method
function getDetailForSmarty($dtKey, $dtValue, $recursion_depth){

	global $dtStructs, $dtTerms, $rtStructs;
	$dtNames = $dtStructs['names'];
	$rtNames = $rtStructs['names'];

	if($dtNames[$dtKey]){

		$dtname = getVariableNameForSmarty($dtNames[$dtKey]);

//error_log(">>>>>>>".$dtKey."=".$dtNames[$dtKey]."=".$dtname."====".$dtValue);

	if(is_array($dtValue)){ //complex type - need more analize
		$res = null;

		$dtDef = $dtStructs['typedefs'][$dtKey]['commonFields'];
		if($dtDef){
			$detailType = $dtDef[2];  //HARDCODED!!!!

//error_log(">>>>>>>".$dtKey."=".$dtNames[$dtKey].">>>".$dtname." TYPE=".$detailType);

			switch ($detailType) {
			case 'enum':

				$res = "";
				foreach ($dtValue as $key => $value){
					$term_value = $dtTerms['termsByDomainLookup']['enum'][$value][0];
					if($term_value){
						if(strlen($res)>0) $res = $res.", ";
						$res = $res.$term_value;
					}
				}
				if(strlen($res)==0){ //no valid terms
					$res = null;
				}else{
					$res = array( $dtname=>$res );
				}

			break;

			case 'file':
			break;
			case 'relmarker':
			break;
			case 'relationtype':
			break;
			case 'resource': // link to another record type

				//@todo - parsing will depend on depth level
				// if there are not mentions about this record type in template (based on obtained array of variables)
				// we will create href link to this record
				// otherwise - we have to obtain this record (by ID) and add subarray

				$res = array();
				$rectypeID = null;
				$prevID = null;


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
				}//for each

				if( count($res)>0 && array_key_exists($rectypeID, $rtNames))
				{
					$recordTypeName = $rtNames[$rectypeID];
					$recordTypeName = getVariableNameForSmarty($recordTypeName, false);
//error_log(">>>>>>>>>>".$rectypeID."=".$rtNames[$rectypeID]."=".$recordTypeName);
					$res = array( $recordTypeName."s" =>$res, $recordTypeName =>$res[0] );
				}else{
					$res = null;
				}

			break;
			case 'geo':
			break;
			case 'calculated':
			break;
			case 'fieldsetmarker':
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
// smarty plugin function
//
function smarty_function_out($params, &$smarty)
{
	if($params['var']){
    	return '<div><div class="tlbl">'.$params['lbl'].': </div><b>'.$params['var'].'</b></div>';
	}else{
		return '';
	}
}

//
// smarty plugin function
//
function smarty_function_out2($params, &$smarty)
{
	if($params['var']){
    	return $params['lbl'].': <b>'.$params['var'].'</b>';
	}else{
		return '';
	}
}

?>
