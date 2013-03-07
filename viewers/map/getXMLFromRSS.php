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
* brief description of file
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



// ARTEM: TO REMOVE


/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


header('Content-type: text/xml; charset=utf-8');

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

define("KML_DETAIL_TYPE", 551);
define("RSSFEED_DETAIL_TYPE", 610);
define("FILE_DETAIL_TYPE", 221);

mysql_connection_select(DATABASE);
$res = mysql_query("select dtl_Value from recDetails where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = " . RSSFEED_DETAIL_TYPE);
if (mysql_num_rows($res)) {
	//read in the feed data
	$rssURL = mysql_fetch_array($res);
	$ch = curl_init($rssURL[0]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}
	$rawXML = curl_exec($ch);
	curl_close($ch);
	// clean up encodings that make the default parsers barf.
	$pat = array(
				'@&\s@i',
				'@&rsquo;@i',
				'@&lsquo;@i',
				'@&rdquo;@i',
				'@&ldquo;@i',
				'@&ndash;@i'
				);
	$repPat = array(
				'&amp;',
				'%92',
				'%91',
				'%94',
				'%93',
				'%97'
				);
	$text = preg_replace($pat,$repPat,$rawXML);
	print $text;
} else {	//leaving the this after the rssfeed check allows use to cache a file in case the feed doesn't work. More coding (try catch ) is needed for this
	$res = mysql_query("select ulf_ID from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = " . FILE_DETAIL_TYPE);
	$file_id = mysql_fetch_array($res);
	$file_id = $file_id[0];
	print file_get_contents(HEURIST_UPLOAD_DIR . "/" . $file_id);
}
?>
