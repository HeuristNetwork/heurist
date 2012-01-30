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

// called by applyCredentials  require_once(dirname(__FILE__).'/../../common/config/initialise.php');
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

	$_REQUEST["f"] = 1; //always search

	if( !array_key_exists("limit", $_REQUEST) ){ //not defined
		$_REQUEST["limit"] = "500"; //force offset and limit (max 500)
	}

	$qresult = loadSearch($_REQUEST); //from search/getSearchResults.php - loads array of records based og GET request

//error_log(">>>>>>>>".print_r($qresult,true));

	if(!array_key_exists('records',$qresult) ||  $qresult['resultCount']==0 ){
		if(array_key_exists("publish", $_REQUEST)){
			echo "<b><font color='#ff0000'>Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</font></b>";
		}else{
			echo "<b><font color='#ff0000'>Search or Select records to see template output</font></b>";
		}
		exit();
	}

	//get name of tempalte file
	$template_file = (array_key_exists('template',$_REQUEST)?$_REQUEST['template']:null);
	//get template body from request (for execution from editor)
	$template_body = (array_key_exists('template_body',$_REQUEST)?$_REQUEST['template_body']:null);

	$replevel = (array_key_exists('replevel',$_REQUEST) ?$_REQUEST['replevel']:0);

//DEBUG error_log(">>>>>>>>".$template_file);
	//convert to array that will assigned to smarty variable
	$records =  $qresult["records"];
	$results = array();
	foreach ($records as $rec){
//error_log(print_r($rec, true));

		$res1 = getRecordForSmarty($rec, 0);
		array_push($results, $res1);
	}
	//activate default template - generic list of records

	$smarty->assign('results', $results);

	ini_set( 'display_errors' , 'false');// 'stdout' );
	$smarty->error_reporting = 0;

	if($template_body)
	{	//execute template from string - modified temoplate in editor
//DEBUG error_log(">>>".$template_body."<<<");
error_log(">>>>>>>".$replevel."<<<<<<");

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
//error_log(">>>>>>>>PRINT".$template_file);
		$smarty->debugging = false;
		$smarty->error_reporting = 0;
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
					$record["recTypeName"] = $rtStructs['typedefs'][$value]['commonFields'][ $rtStructs['typedefs']['commonNamesToIndex']['rty_Name'] ];
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

/* DEBUG
		foreach ($record as $dtKey => $dtValue){
error_log(">>>".$dtKey."=".$dtValue);
		}*/
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
			$detailType = $dtDef[ $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type']  ];

//error_log(">>>>>>>".$dtKey."=".$dtNames[$dtKey].">>>".$dtname." TYPE=".$detailType);
//ENUM('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude')

			switch ($detailType) {
			case 'enum':

				$res = "";
				foreach ($dtValue as $key => $value){
					$term_value = $dtTerms['termsByDomainLookup']['enum'][$value][ $dtTerms['fieldNamesToIndex']['trm_Label'] ];
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
			   //get url for file download

			   //if image - special case
//error_log("FILE>>>>>".print_r($dtValue, true));

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

//error_log("RES>>>>>".print_r($res, true));

			break;
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

//error_log("rr111>>>>>".print_r($dtValue, true));
//error_log("rr222>>>>>".print_r($res, true));

			break;
			case 'relmarker':
			break;
			case 'relationtype':
			break;
			case 'geo':
			break;
			case 'separator':
			break;
			case 'calculated':
			break;
			case 'fieldsetmarker':
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
//error_log(">>>>>>>>>>".$rectypeID."=".$rtNames[$rectypeID]."=".$recordTypeName."array=".print_r($res[0],true));
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

// Linkify youtube URLs which are not already links.
function linkifyYouTubeURLs($text, $size) {
    $text = preg_replace('~
        # Match non-linked youtube URL in the wild. (Rev:20111012)
        https?://         # Required scheme. Either http or https.
        (?:[0-9A-Z-]+\.)? # Optional subdomain.
        (?:               # Group host alternatives.
          youtu\.be/      # Either youtu.be,
        | youtube\.com    # or youtube.com followed by
          \S*             # Allow anything up to VIDEO_ID,
          [^\w\-\s]       # but char before ID is non-ID char.
        )                 # End host alternatives.
        ([\w\-]{11})      # $1: VIDEO_ID is exactly 11 chars.
        (?=[^\w\-]|$)     # Assert next char is non-ID or EOS.
        (?!               # Assert URL is not pre-linked.
          [?=&+%\w]*      # Allow URL (query) remainder.
          (?:             # Group pre-linked alternatives.
            [\'"][^<>]*>  # Either inside a start tag,
          | </a>          # or inside <a> element text contents.
          )               # End recognized pre-linked alts.
        )                 # End negative lookahead assertion.
        [?=&+%\w]*        # Consume any URL (query) remainder.
        ~ix',
        '<iframe '.$size.' src="http://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>',
        $text);

        //'<a href="http://www.youtube.com/watch?v=$1">YouTube link: $1</a>'
        //'<iframe width="420" height="345" src="http://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>',
//error_log(">>>".$text."<<<<");
    return $text;
}
//
//
function detectSourceAndType($url){

	$source = 'Arbitrary';
	$type = 'Unknown';

	//1. detect source
	if(strpos($url,'http://heuristscholar.org')==0){
		$source = 'Heurist';
	}else if(strpos($url,'http://www.flickr.com')==0){
		$source = 'Flickr';
		$type = 'Image';
	}else if(strpos($url, 'http://www.panoramio.com/')==0){
		$source = 'Panoramio';
		$type = 'Image';
	}else if(preg_match('http://(www.)?locr\.de|locr\.com', $url)){
		$source = 'Locr';
		$type = 'Image';
	//}else if(link.indexOf('http://www.youtube.com/')==0 || link.indexOf('http://youtu.be/')==0){
	}else if(preg_match('http://(www.)?youtube|youtu\.be', $url)){
		$source = 'Youtube';
		$type = 'Video';
	}

	//try to detect type by extension and protocol
	if($type=='Unknown'){

		//get extension from url - unreliable
		$extension = null;
		$ap = parse_url($url);
		if( array_key_exists('path', $ap) ){
			$path = $ap['path'];
			if($path){
				$extension = pathinfo($path, PATHINFO_EXTENSION);
			}
		}
		if($extension){
			$extension = strtolower($extension);
		}

		if($extension=="jpg" || $extension=="jpeg" || $extension=="png" || $extension=="gif"){
			$type = 'Image';
		}else if($extension=="mp4" || $extension=="mov" || $extension=="avi"){
			$type = 'Video';
		}else if($extension=="mp3" || $extension=="wav"){
			$type = 'Audio';
		}else if($extension=="html" || $extension=="htm" || $extension=="txt"){
			$type = 'Text/HTML';
		}else if($extension=="pdf" || $extension=="doc" || $extension=="xls"){
			$type = 'Document';
		}else if($extension=="swf"){
			$type = 'Flash';
		}
	}

	return array($source, $type);
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

		}else if($dt=="urlinclude") {
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
					//var id = /^.*((youtu.be\/)|(v\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/.exec(curr_link);
//error_log(">>>>>URL=".$url);
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

		}else{
    		return $label.$params['var'].'<br/>';
		}
	}else{
		return '';
	}
}

?>
