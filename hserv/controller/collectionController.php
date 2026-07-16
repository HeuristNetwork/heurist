<?php
/**
* DEPRECATED - to be changed to localStorage
*
* collectionController.php - Controller to manage user's collection of record ids
*
* Temporary server-side fallback for user's collection of record ids.
* This controller intentionally uses a separate "heurist-collection" session,
* not the main "heurist-sessionid" authentication session.
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

$hasWriteAction = array_key_exists('add', $_REQUEST)
    || array_key_exists('remove', $_REQUEST)
    || array_key_exists('clear', $_REQUEST);

$needsSession = $hasWriteAction || array_key_exists('fetch', $_REQUEST);

/**
 * Checks if a string contains only digits.
 *
 * @param string $s The string to check.
 * @return bool True if the string contains only digits, false otherwise.
 */
function digits ($s) {
    return preg_match('/^\d+$/', $s);
}

$collection = array();

if ($needsSession) {

    /*
     * Deprecated collection storage is isolated from the main Heurist
     * authentication session. Do not use "heurist-sessionid" here:
     * raw session_start() would otherwise overwrite remember-me cookies.
     */
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('heurist-collection');
        session_cache_limiter('none');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        @session_start();
    }

    if (isset($_SESSION[$dbname_full]['record-collection'])
        && is_array($_SESSION[$dbname_full]['record-collection'])) {
        $collection = $_SESSION[$dbname_full]['record-collection'];
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

    if ($hasWriteAction) {
        if (!isset($_SESSION[$dbname_full]) || !is_array($_SESSION[$dbname_full])) {
            $_SESSION[$dbname_full] = array();
        }
        $_SESSION[$dbname_full]['record-collection'] = $collection;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

$rv = array(
    'count' => count($collection)
);

if (array_key_exists('fetch', $_REQUEST)) {
    $rv['ids'] = $collection ? array_keys($collection): array();
}

print json_encode($rv);
