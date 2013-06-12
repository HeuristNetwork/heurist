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
* Main index page. It is invoked if request does not have extension
* see .htaccess
* RewriteRule ^([^/]+)/([^/]+)$ ?sub=$1&name=$2 [L,NC]
*
* utilized for browse, citation, preview and popup
*
* if last path of request path is numeric it generates entity page with given ID
*
* for browse it redirects to browse.php?name=
* otherwise it includes the required script
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


require_once(dirname(__FILE__)."/php/incvars.php");

require_once(dirname(__FILE__)."/php/Record.php");
require_once(dirname(__FILE__)."/php/getRecordInfo.php");
require_once(dirname(__FILE__)."/php/utilsMakes.php");

//error_log(">>".print_r($_REQUEST,true)." browse=".@$_REQUEST['sub']."   id=".@$_REQUEST['id']);

///count($_REQUEST)==0
if( (@$_REQUEST['sub']=="" and  @$_REQUEST['id']=="") ||  @$_REQUEST['sub']=="browse" || @$_REQUEST['id']=="browse"){

	$url = 'Location: '.$urlbase.'browse.php';
	if(@$_REQUEST['name']){
		$url .= '?r='.@$_REQUEST['name'];
	}

	header($url);

}else {

	$out =null;

	if(@$_REQUEST['sub']=="preview"){

		require(dirname(__FILE__)."/php/pagePreview.php");

		//echo '<div class="balloon-container">'.$_REQUEST['name'].'</div>';
		//WORKING XSLT VERSION $out = showPreview(@$_REQUEST['name']);

	}else if(@$_REQUEST['sub']=="popup"){


		//WORKING XSLT VERSION $out = showPopup(@$_REQUEST['name']);

		require(dirname(__FILE__)."/php/pagePopup.php");


	/*} XSLT way - working
    else if(@$_REQUEST['sub']=="test"){

		$rec_id = $_REQUEST['name'];
		$out = showRecord($rec_id);
    */
    }else if(@$_REQUEST['sub']=="citation"){

        require(dirname(__FILE__)."/php/pageCitation.php");

	}else if(@$_REQUEST['id']){

		$_REQUEST['name'] = $_REQUEST['id'];
		require(dirname(__FILE__)."/php/pageFrame.php");

	}

	if($out){
		echo $out;
	}


}
exit();
?>
