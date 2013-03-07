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



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/



session_cache_limiter('no-cache');

define('SAVE_URI', 'disabled');

define('SEARCH_VERSION', 1);

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/parseQueryToSQL.php');

// if the user isn't logged in, never do a usrBookmarks search
if (! is_logged_in())
	$_REQUEST['w'] = 'all';


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['q']  &&  ! @$_REQUEST['s']) return;	// wwgd

if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
else
	$search_type = BOTH;	// all records


mysql_connection_select(DATABASE);

if (preg_match('/\\b_BROKEN_\\b/', $_REQUEST['q'])) {
	$broken = 1;
	$_REQUEST['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $_REQUEST['q']);
}

$query = REQUEST_to_query("select rec_ID, bkm_ID ", $search_type);

if (@$broken) {
	$query = str_replace(' where ', ' where (to_days(now()) - to_days(rec_URLLastVerified) >= 8) and ', $query);
}

$res = mysql_query($query);
$ids = array();
while ($row = mysql_fetch_assoc($res)) {
	array_push($ids, array("recID" => $row["rec_ID"], "bkmk_id" => $row["bkm_ID"]));
}

print json_format($ids);

?>
