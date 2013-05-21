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


define('ISSERVICE',1);
define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_select(DATABASE);

header("Content-type: text/javascript");

if (! @$_REQUEST["url"]) return;

$url = $_REQUEST["url"];

if (substr($url, -1) == "/") $url = substr($url, 0, strlen($url)-1);

$query = "select rec_id from Records left join usrBookmarks on bkm_recID = rec_id where (rec_URL='".mysql_real_escape_string($url)."' or rec_URL='".mysql_real_escape_string($url)."/') ".
            "group by bkm_ID  order by count(bkm_ID), rec_id limit 1";

$res = mysql_query($query);

if ($row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bib_id = ".$row["rec_id"].";\n\n";
} else {
	print "HEURIST_url_bib_id = null;\n\n";
}

$query = "select bkm_ID from usrBookmarks left join Records on rec_id = bkm_recID where bkm_UGrpID=".get_user_id().
        " and (rec_URL='".mysql_real_escape_string($url)."' or rec_URL='".mysql_real_escape_string($url)."/') limit 1";

$res = mysql_query($query);
if ($res && $row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bkmk_id = ".$row["bkm_ID"].";\n\n";
} else {
	print "HEURIST_url_bkmk_id = null;\n\n";
}

print "if (window.HEURIST_urlBookmarkedOnload) HEURIST_urlBookmarkedOnload();\n\n";

ob_end_flush();
?>
