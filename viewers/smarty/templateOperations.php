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
* teamplateOperations.pho
* operations with template files - save, delete, get, list
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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


if (! is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
	//header('Content-type: text/html; charset=utf-8');
	return;
}


/*****DEBUG****///error_log(">>>>>>>>>>>>>".print_r($_REQUEST, true));

$dir = HEURIST_SMARTY_TEMPLATES_DIR;

$mode = $_REQUEST['mode'];

if($mode){ //opeartion with template files

	//get name of tempalte file
	$template_file = (array_key_exists('template',$_REQUEST)?  urldecode($_REQUEST['template'])  :null);
/*****DEBUG****///error_log(">>>>>>>>>>".$template_file);
	try{

	switch ($mode) {
		case 'list':
			getList();
		break;
		case 'get':
			getTemplate($template_file);
		break;
		case 'save':
			//get template body from request (for execution from editor)
			$template_body = (array_key_exists('template_body',$_REQUEST)?$_REQUEST['template_body']:null);
			//add extension and save in default template directory

			$template_file = $dir.$template_file;
			$path_parts = pathinfo($template_file);
			$ext = (array_key_exists('extension',$path_parts))?strtolower($path_parts['extension']):"";
			if($ext!="tpl"){
				$template_file = $template_file.".tpl";
			}

			$file = fopen ($template_file, "w");
			if(!$file){
				print json_format(array("error"=>"Can't write file. Check permission for smarty template directory"));
				exit();
			}
			fwrite($file, $template_body);
			fclose ($file);

			print json_format(array("ok"=>$mode));

		break;
		case 'delete':

				$template_file = $dir.$template_file;
				if(file_exists($template_file)){
					unlink($template_file);
				}else{
					throw new Exception("Template file does not exist");
				}

			header("Content-type: text/javascript");
			print json_format(array("ok"=>$mode));

		break;
	}

	}
	catch(Exception $e)
	{
		header("Content-type: text/javascript");
		print json_format(array("error"=>$e->getMessage()));
	}

}

exit();

/**
* Returns the list of available tempaltes as json array
*/
function getList(){

	global $dir;

	$files = scandir($dir);
	$results = array();

	foreach ($files as $filename){
		//$ext = strtolower(end(explode('.', $filename)));
		//$ext = preg_replace('/^.*\.([^.]+)$/D', '$1', $filename);

		$path_parts = pathinfo($filename);
		if(array_key_exists('extension', $path_parts))
		{
			$ext = strtolower($path_parts['extension']);
/*****DEBUG****///error_log(">>>>".$path_parts['filename']."<<<<<");//."    ".$filename.indexOf("_")."<<<<");

			$ind = strpos($filename,"_");
	        $isnot_temp = (!(is_numeric($ind) && $ind==0));

			if(file_exists($dir.$filename) && $ext=="tpl" && $isnot_temp)
			{
				//$path_parts['filename'] )); - does not work for nonlatin names
				$name = substr($filename, 0, -4);
				array_push($results, array( 'filename'=>$filename, 'name'=>$name));
			}
		}
	}
	header("Content-type: text/javascript");
	//header('Content-type: text/html; charset=utf-8');
/*****DEBUG****/// error_log(">>>>>>>>>>>>>".print_r($results, true));

	print json_format( $results, true );
}

/**
* Returns the content of template file as text
*
* @param mixed $filename - name of template file
*/
function getTemplate($filename){

	global $dir;
	if($filename && file_exists($dir.$filename)){
		header('Content-type: text/html; charset=utf-8');
		print file_get_contents($dir.$filename);
	}else{
		header("Content-type: text/javascript");
		print json_format(array("error"=>"file not found"));
	}
}
?>
