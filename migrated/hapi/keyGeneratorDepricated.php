<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* Generate a Heurist API key for a given site */

require_once(dirname(__FILE__)."/../common/connect/applyCredentials.php");

$instances = get_all_instances();
$user_instances = array();
$logged_in = false;
foreach (array_keys($instances) as $instance) {
	if (@$_SESSION[($instance ? $instance."." : "") . "heurist"]["user_name"]) {
		array_push($user_instances, $instance);
	}
}

if (count($user_instances) == 0) {
	header("Location: ".HEURIST_BASE_URL_V3."common/connect/login.php?last_uri=".HEURIST_BASE_URL_V3."hapi/key.php");
}

if (count($user_instances) == 1  &&  $user_instances[0] == ""  &&
	$_SESSION["heurist"]["user_access"][$instances[""]["admingroup"]] != "admin") {
	header("Location: ".HEURIST_BASE_URL_V3."common/connect/login.php?last_uri=".HEURIST_BASE_URL_V3."hapi/key.php");
}

?>

<html>
 <head>
  <title>Generate a Heurist API key</title>
 </head>
 <body>

  <a style="float: right;" href=/>HAPI home</a>
  <h3>Generate a Heurist API key</h3>
<?php

if (! @$_REQUEST["url"]) {
?>
  <p>
   A Heurist API (HAPI) key will let you host HAPI applications on your own site.
   A single HAPI key is valid for a single directory on your site, and all directories beneath it.
   For example, a key registered for <tt>http://my.domain/foo/</tt> is also valid for <tt>http://my.domain/foo/bar/</tt>, but not <tt>http://my.domain/moo/</tt>.
  </p>
  <form>
   <p>
    Web site URL: <input type=text name=url value=http:// style="width: 200px;"></input>
   </p>
<?php
	if (count($user_instances) == 1) {
		print "<input type=hidden name=instance value=".$user_instances[0]."></input>\n";
		print "<p>Note: you are logged in to the ".($user_instances[0] ? "<tt>".$user_instances[0]."</tt>" : "primary")." database.  To generate a key for a different database, log in to that database first.</p>";
	} else {
		print "<p>You are logged into more than one Heurist database.  Select one: <select name=instance>\n";
		foreach ($user_instances as $instance) {
			print "<option value=$instance>".($instance ? $instance : "the \"primary\" Heurist database") ."</option>\n";
		}
		print "</select></p>\n";
	}
?>
   <br>
   <input type=submit value="Generate HAPI key"></input>
  </form>

<?php
	return;
}

require_once(dirname(__FILE__)."/../common/php/dbMySqlWrappers.php");
mysql_connection_insert("hapi");

$url = $_REQUEST["url"];
$instance = $_REQUEST["db"];
$user_id = $instance ? @$_SESSION[$instance.".heurist"]["user_id"] : get_user_id();

if (! $user_id) {
	print "<p>You are not logged in to the specified database</p>\n";
	return;
}

if (substr($url, -1) != "/") $url .= "/";

$res = mysql_query("select hl_key
                      from hapi_locations
                     where hl_location = '" . mysql_real_escape_string($url) . "'
                       and hl_instance = '" . mysql_real_escape_string($instance) . "'");
if (mysql_num_rows($res) > 0) {
	$row = mysql_fetch_assoc($res);
	$key = $row["hl_key"];
} else {
	mysql_query("insert into hapi_locations (hl_location, hl_instance, hl_user_id, hl_key, hl_created)
					values ('" . mysql_real_escape_string($url) . "', '" . mysql_real_escape_string($instance) . "', " . $user_id . ", sha1('" . mysql_real_escape_string($instance) . mysql_real_escape_string($url) . "'), now())");
	$res = mysql_query("select hl_key
	                      from hapi_locations
	                     where hl_location = '" . mysql_real_escape_string($url) . "'
	                       and hl_instance = '" . mysql_real_escape_string($instance) . "'");
	if (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_assoc($res);
		$key = $row["hl_key"];
	}
}
?>
  <p>Your HAPI key is:</p>
  <pre><?= $key ?></pre>
  <p>This key is for the Heurist database:</p>
  <pre><?= $instance ? '"'.$instance.'"' : '"" (the "primary" Heurist database)' ?></pre>
  <p>This key is valid for all URLs in this directory:</p>
  <pre><?= $url ?></pre>
  <p>To load the Heurist API, you can use:</p>
  <pre>&lt;script src=http:<?=HEURIST_BASE_URL_V3?>/hapi/load.php?<?= $instance ? "db=$instance&amp;" : "" ?>key=<?=$key?>&gt;&lt;/script&gt;</pre>
