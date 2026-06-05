<?php
/**
* DEPRECATED - to be changed to localStorage
* 
* collectionController.php - Controller to manage user's collection of record ids
*
* Manages user's collection of record ids stored in SESSION
* see for client side utilsCollection.js, used in recordList
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JS);

$db = @$_REQUEST['db'];
if(!$db) {exit;}

if(strpos($db, HEURIST_DB_PREFIX)===0){
    $dbname_full = $db;
}else{
    $dbname_full = HEURIST_DB_PREFIX.$db;
}

//since this script is called after system is inited we can be sure that session is available already
if (@$_COOKIE['heurist-sessionid'] && session_status() !== PHP_SESSION_ACTIVE) {
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

/**
 * Checks if a string contains only digits.
 *
 * @param string $s The string to check.
 * @return bool True if the string contains only digits, false otherwise.
 */
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
$_SESSION[$dbname_full]['record-collection'] = $collection;
session_write_close();

$rv = array(
    'count' => count($collection)
);

if (array_key_exists('fetch', $_REQUEST)) {
    $rv['ids'] = @$collection ? array_keys($collection): array();
}

print json_encode($rv);
