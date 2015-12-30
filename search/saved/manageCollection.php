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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

header('Content-type: text/javascript');

session_start();

// note $collection is a reference - SW also we suppress warnings to let the system create the key
$collection = &$_SESSION[@HEURIST_SESSION_DB_PREFIX.'heurist']['record-collection'];

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

if (array_key_exists('clear', $_REQUEST)) {
	$collection = array();
}

$rv = array(
	'count' => count($collection)
);

if (array_key_exists('fetch', $_REQUEST)) {
	$rv['ids'] = @$collection ? array_keys($collection): array();
}

print json_format($rv);

?>
