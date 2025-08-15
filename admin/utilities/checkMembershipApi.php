<?php
/**
* checkMembershipApi.php - API endpoint for remote servers
*
* @project     Heurist academic knowledge management system
* @package     Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>  specification
* @author      Artem Osmakov <osmakov@gmail.com> corrections
* @since       7.0
*/
require_once 'checkMembershipLib.php';
/* ------------------------ Standalone handler ------------------------ */
if (php_sapi_name() !== 'cli') {
    if(@$_SERVER['REQUEST_METHOD']=='POST'){
        $req_params = filter_input_array(INPUT_POST);
    }else{
        $req_params = filter_input_array(INPUT_GET);
    }

    $email = isset($req_params['email']) ? trim((string)$req_params['email']) : '';
    $lastName = isset($req_params['lastName']) ? trim((string)$req_params['lastName']) : '';
    $firstName = isset($req_params['firstName']) ? trim((string)$req_params['firstName']) : '';
    $email = isset($req_params['email']) ? trim((string)$req_params['email']) : '';
    $host  = isset($req_params['host'])  ? trim((string)$req_params['host'])  : '';
    $db    = isset($req_params['db'])    ? trim((string)$req_params['db'])    : '';
    $ctx   = isset($req_params['ctx'])   ? trim((string)$req_params['ctx'])   : '';

    if ($email !== '' || ($firstName!=='' && $lastName!=='') || ($host !== '' && $db !== '')) {
        header('Content-Type: text/plain; charset=UTF-8');
        if(isset($req_params['log']) && trim((string)$req_params['log'])==='1'){
            checkMembershipLogNonmember($ctx, $email, $host, $db);
            echo "ok";
        }else{
            echo checkHeuristNetworkMembership($email, $host, $db, $ctx, $firstName, $lastName);    
        }
    }
}

