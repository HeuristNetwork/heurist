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
* @version     4
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once (dirname(__FILE__).'/../../configIni.php'); // read in the configuration file
require_once (dirname(__FILE__).'/../consts.php');

header('Content-type: text/javascript');

$db = @$_REQUEST['db'];
if(!$db) exit();

if(strpos($db, HEURIST_DB_PREFIX)===0){
    $dbname_full = $db;
}else{
    $dbname_full = HEURIST_DB_PREFIX.$db;
}

//since this script is called after system is inited we can be sure that session is available already
if (@$_COOKIE['heurist-sessionid']) {
        session_name('heurist-sessionid');
        /* @todo test
        session_set_cookie_params ( 0, '/', '', $is_https);
        session_cache_limiter('none');
        session_id($_COOKIE['heurist-sessionid']);
        */
        @session_start();
}

// note $collection is a reference - SW also we suppress warnings to let the system create the key
$collection = &$_SESSION[$dbname_full]['record-collection'];

function digits ($s) {
	return preg_match('/^\d+$/', $s);
}

if (array_key_exists('add', $_REQUEST)) {
	$ids = array_filter(explode(',', $_REQUEST['add']), 'digits');
	foreach ($ids as $id) {
		$collection[$id] = true;
	}
}

if (array_key_exists('remove', $_REQUEST)) {
	$ids = array_filter(explode(',', $_REQUEST['remove']), 'digits');
	foreach ($ids as $id) {
		unset($collection[$id]);
	}
}

if (array_key_exists('clear', $_REQUEST) || !$collection) {
	$collection = array();
}

$rv = array(
	'count' => count($collection)
);

if (array_key_exists('fetch', $_REQUEST)) {
	$rv['ids'] = @$collection ? array_keys($collection): array();
}

print json_encode($rv);
?>
