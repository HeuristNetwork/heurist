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
* brief description of file
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

require_once (dirname(__FILE__).'/../../hsapi/System.php');

header('Content-type: text/javascript; charset=utf-8');

$title = '';
$url = @$_REQUEST['url'];

$rv = array('num'=>$_REQUEST['num']);

$system = new System();
if(!$system->init(@$_REQUEST['db'])){
    print json_encode( $system->getError() );
}else if(!$system->has_access() ){
    print json_encode( $system->addError(HEURIST_REQUEST_DENIED) );
}else if ( !$url  ||  (!intval($_REQUEST['num'])  &&  $_REQUEST['num'] != 'popup')) {
    print json_encode( $system->addError(HEURIST_INVALID_REQUEST), 'URL is not defined' );
}else{

	$url = str_replace(' ', '+', $url);

	$data = loadRemoteURLContentWithRange($url, "0-10000");//get title of webpage

	if ($data){

		preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
		if ($matches) {
            $title = preg_replace('/\s+/', ' ', $matches[1]);   
        }

		if ($title) {
            $rv['title']=$title;
			//type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			//if (preg_match('!^image/!i', $type)) {
			//	preg_match('!.*/(.*)!', $_REQUEST['url'], $matches);
			//	$title = 'Image - ' . $matches[1];
			//}
		}else{
            $rv['error']='Title is not defined';
        }


	}else{
		$rv['error']='URL could not be retrieved';
	}

    
    print json_encode(array('status'=>HEURIST_OK, 'data'=>$rv));
}
?>