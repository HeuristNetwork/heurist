<?php

/**
*  Email Users of any Heurist database located on this server, requires a Heurist Database + System Administrator password
*  For server calls from main form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../');//need for proper path to js and css

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JSON);

$system = new hserv\System();

$sysadmin_pwd = USanitize::getAdminPwd('pwd');
$request = USanitize::sanitizeInputArray();

$data = null;
$response = [];
$rtn = false;

$isSystemInited = $system->init(@$request['db']);

if(!$isSystemInited) {

    $response = $system->getError();
    $rtn = json_encode($response);

    print $rtn;
    exit;
}

if(isset($request['databases'], $request['users'], $request['emailBody'], $request['db'], $sysadmin_pwd)){
    sendEmails($request);
}

$mysqli = $system->getMysqli();

if(isset($request['get_email']) && isset($request['recid'])) {/* Get the Title and Short Summary field for the selected id, id is for Email record */

    $email_title = "";
    $email_body = "";
    $id = intval($request['recid']);

    // Validate ID
    if(!is_numeric($id) || intval($id) < 1){

        $response = ["status"=>HEURIST_ACTION_BLOCKED, "message"=>"An invalid Email record id was provided.", "request"=>htmlspecialchars($id)];
        $system->addError(HEURIST_ERROR, "Bulk Email Other: The record IDs used for the Email selector are invalid or have not been retrieved correctly. Invalid ID => " . htmlspecialchars($request['recid']));
        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    // Get title/name and short summary detail type ids
    $title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
    $shortsum_detiltype_id = ConceptCode::getDetailTypeLocalID("2-3");
    if (empty($title_detailtype_id) || empty($shortsum_detiltype_id)) {
        $missing = "";

        if(empty($title_detailtype_id) && empty($shortsum_detiltype_id)){
            $missing = "for both title and short summary detail types.";
        }else{
            $missing = empty($title_detailtype_id) ? "for the title detail type." : "for the short summary detail type.";
        }

        $response = ["status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve the local id $missing <br>If this problem persists, please notify the Heurist team."];

        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    $query = "SELECT dtl_Value, dtl_DetailTypeID
              FROM recDetails
              WHERE dtl_RecID = $id AND dtl_DetailTypeID IN (".$shortsum_detiltype_id.", ".$title_detailtype_id.")";

    $detail_rtn = $mysqli->query($query);
    if(!$detail_rtn){

        $response = ["status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve the details of Email record ID => $id.<br>If this persists, please notify the Heurist team.<br>", "error_msg"=>$mysqli->error, "request"=>$id];
        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    while($email_dtl = $detail_rtn->fetch_row()){
        if($email_dtl[1] == $shortsum_detiltype_id){
            $email_body = $email_dtl[0];
        }elseif($email_dtl[1] == $title_detailtype_id){
            $email_title = $email_dtl[0];
        }
    }

    $data = [$email_title, $email_body];

    $response = ["status"=>HEURIST_OK, "data"=>$data, "request"=>$id];
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($request['db_filtering'])) { /* Get a list of DBs based on the list of provided filters, first search gets all dbs */

    $db_request = $request['db_filtering'];
    $dbs = [];// list of databases
    $databases = [];// array of database details
    $invalid_dbs = [];

    // Get all dbs that start with the Heurist prefix
    $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE `SCHEMATA`.`SCHEMA_NAME` LIKE '".HEURIST_DB_PREFIX."%' ORDER BY `SCHEMATA`.`SCHEMA_NAME` COLLATE utf8_general_ci";

    $db_list = $mysqli->query($query);
    if (!$db_list) {

        $response = ["status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve a list of Heurist databases.<br>", "error_msg"=>$mysqli->error, "request"=>$db_request];
        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    while($db = $db_list->fetch_row()){

        //check version - use >=1.3.0
        $query = 'SELECT sys_dbVersion, sys_dbSubVersion from '.$db[0].'.sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if(!$ver){
            continue; //skip - broken database
        }else{
            if($ver['sys_dbSubVersion']<3){
                continue; //skip - old database
            }
        }


        // Ensure that the Heurist db has the required tables, ignore if they don't
        $dbname = $db[0];
        if(preg_match('/[^A-Za-z0-9_\$]/', $db_name)){ //invalid dbname
            continue;
        }
        $query = "SHOW TABLES IN $dbname WHERE Tables_in_$dbname = 'Records' OR Tables_in_$dbname = 'recDetails' OR Tables_in_$dbname = 'sysUGrps' OR Tables_in_$dbname = 'sysUsrGrpLinks'";

        $table_listing = $mysqli->query($query);
        if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables

            if($table_listing && $db_request == "all"){
                $invalid_dbs[] = $db[0];
            }

          continue;
        }

        $dbs[] = $db[0];
    }//while

    if($db_request == "all"){ // No additional filtering needed

        $data = ['list' => $dbs, 'details' => []];
        $details = getDatabaseDetails($mysqli, $dbs);
        $data['details'] = $details;

    } elseif(is_array($db_request) && count($db_request)==4){ // Do filtering, record count and last modified

        $count = intval($db_request['count']);
        if(!($count>0)){
            $count = 0;
        }

        $lastmod_logic = $mysqli->real_escape_string( filter_var($db_request['lastmod_logic'],FILTER_SANITIZE_STRING) );
        $lastmod_logic = $lastmod_logic == 'more' ? '<=' : '>=';
        $lastmod_period = intval($db_request['lastmod_period']);

        //to avoid injection
        $lastmod_unit = 'ALL';
        switch (strtoupper(@$db_request['lastmod_unit'])) {
            case 'DAY':  $lastmod_unit = 'DAY'; break;
            case 'MONTH':  $lastmod_unit = 'MONTH'; break;
            case 'YEAR':  $lastmod_unit = 'YEAR'; break;
            default;
        }

        $lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic
                    . " date_format(curdate(), '%Y-%m-%d') - INTERVAL "
                    . $lastmod_period . " " . $lastmod_unit . " " : "";

        foreach ($dbs as $db) {

            $db = preg_replace(REGEX_ALPHANUM, "", $db);
            
            $isok = true;
            
            if($count>0){
                $count_res = mysql__select_value($mysqli, "select count(*) from $db.`Records` where (not rec_FlagTemporary)");
                $isok =  intval($count_res)>$count;
            }
            
            if($isok && $lastmod_unit!="ALL"){
                $cnt = mysql__select_value($mysqli, "select count(*) from $db.`Records` where (not rec_FlagTemporary) ".$lastmod_where);    
                $isok =  $cnt>0;
            }
            if($isok){
                $data[] = $db;
            }
        }
    }

    $response = ["status"=>HEURIST_OK, "data"=>$data, "request"=>$db_request];
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($request['user_count']) && isset($request['db_list'])) { // Get a count of distinct users

    $user_request = $request['user_count'];
    $dbs = $request['db_list'];
    if(!is_array($dbs)){
        $dbs = explode(',', $dbs);
    }

    $data = 0;
    $email_list = [];

    foreach($dbs as $db){

        $db = preg_replace(REGEX_ALPHANUM, "", $db);//for snyk
        
        $query = 'SELECT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail FROM `' . $db . '`.sysUGrps AS ugr ';
        $need_groups = false;

        if($user_request == "owner"){ // Owners
            $where_clause = 'ugr.ugr_ID = 2';
        }elseif($user_request == "manager"){ // Admins of Database Managers Workgroup
        
            $need_groups = true;

            $where_clause = "ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID = 1";

        }elseif($user_request == "admin"){ // Admins for any workgroups

            $need_groups = true;
            
            $where_clause = "ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID IN
                   (SELECT ugr_ID
                         FROM `" . $db . "`.sysUGrps
                         WHERE ugr_Type = 'workgroup' AND ugr_Enabled != 'n')";

        }elseif($user_request == "user"){ // ALL users
            $where_clause = "ugr.ugr_Type = 'user' AND ugr.ugr_Enabled != 'n'";
        }else{

            $response = ["status"=>HEURIST_INVALID_REQUEST, "message"=>"Invalid user choice", "request"=>$user_request];
            $rtn = json_encode($response);

            print $rtn;
            exit;
        }

        if($need_groups){
            $query .= ", `{$db}`.sysUsrGrpLinks AS ugl ";
            $where_clause = "ugl.ugl_UserID = ugr.ugr_ID AND {$where_clause}";
        }

        $query .= " WHERE {$where_clause}";

        $res = $mysqli->query($query);
        if(!$res){
            //Unable to retrieve user count for databases
            continue;
        }

        while($row = $res->fetch_row()){

            if(!in_array($row[2], $email_list)){
                $data += 1;
                $email_list[] = $row[2];
            }
        }

    }

    $response = ["status"=>HEURIST_OK, "data"=>$data, "request"=>$user_request];
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($request['rec_count']) && isset($request['db_list'])){ // Get a count of records

    $dbs = $request['db_list'];
    if(!is_array($dbs)){
        $dbs = explode(',', $dbs);
    }

    $data = [];
    foreach($dbs as $db){
        if(strpos($db, HEURIST_DB_PREFIX)===0){
            $db = preg_replace(REGEX_ALPHANUM, "", $db);//for snyk
            $query = 'SELECT count(*) FROM `' . $db . '`.`Records` WHERE rec_FlagTemporary != 1';
            $res = $mysqli->query($query);
            if(!$res){
                $data[$db] = 'error';
                continue;
            }

            while($row = $res->fetch_row()){
                $data[$db] = $row[0];
            }
        }
    }

    $response = ["status"=>HEURIST_OK, "data"=>$data, "request"=>implode(',', $dbs)];
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($request['session'])) {

    $progress = mysql__update_progress($mysqli, $request['session'], false, null);
    $status = !$progress || $progress == 'terminate' ? HEURIST_INVALID_REQUEST : HEURIST_OK;
    $progress = !$progress || $progress == 'terminate' ? '' : $progress;

    if($status === HEURIST_OK){
        mysql__update_progress($mysqli, $request['session'], false, 'REMOVE');
    }

    print json_encode(['status' => $status, 'data' => $progress]);

} else { // Invalid Request

    $response = ["status"=>HEURIST_INVALID_REQUEST, "message"=>"invalid request sent", "request"=>$request];
    $rtn = json_encode($response);

    print $rtn;
}

//
// Retrieve the record count and last update (record or structure, depending on which is newer)
//  for each provided database
//
function getDatabaseDetails($mysqli, $db_list){


    $details = [];

    // Retrieve record count and last update (record or structure)
    foreach ($db_list as $database) {

        $database = preg_replace(REGEX_ALPHANUM, "", $database);

        $db_data = ['name' => $database, 'rec_count' => 0, 'last_update' => null];

        // Get record count
        $db_data['rec_count'] = mysql__select_value($mysqli, "SELECT COUNT(*) FROM `$database`.Records WHERE rec_FlagTemporary != 1");

        $last_recent = mysql__select_value($mysqli,
        "SELECT CONVERT_TZ(MAX(rec_Modified), @@session.time_zone, \"+00:00\") FROM `$database`.Records WHERE rec_FlagTemporary != 1");

        if(!$last_recent){
            $last_recent = date_create($last_recent);
        }

        $last_struct = getDefinitionsModTime($mysqli, true);

        if(!$last_recent || $last_struct > $last_recent){
            $last_recent = $last_struct;
        }

        $db_data['last_update'] = $last_recent->format('Y-m-d');

        $details[] = $db_data;
    }

    return $details;
}

function sendEmails($params){

    global $system, $sysadmin_pwd, $passwordForServerFunctions;

    require_once __DIR__ . '/bulkEmailSystem.php';

    if ($system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)) {
        print json_encode(['status' => HEURIST_ACTION_BLOCKED, 'message' => 'The System Administrator password is invalid, please re-try in the previous tab/window.']);
        exit;
    }

    // Attempt to send the system email.
    $rtn = sendSystemEmail($params);
    
    // Check the result of the email sending process.
    print json_encode($rtn);
    
    exit;
}
