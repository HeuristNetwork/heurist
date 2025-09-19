<?php
/**
* bulkEmailController.php - Handles server-side logic for the bulk email utility.
*
* @fileOverview This script acts as the controller for the bulk email functionality.
*               It processes AJAX requests from the `bulkEmailMain.php` interface.
*               Operations include listing databases, filtering databases based on criteria
*               (record count, last modification date), counting users based on roles,
*               retrieving email template details, and initiating the email sending process
*               via `bulkEmailSystem.php`. Most actions require System Administrator privileges.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

define('PDIR','../../');//need for proper path to js and css

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JSON);

/**
 * Controller class for handling bulk email operations.
 *
 * This class processes requests from the bulk email interface, interacts with
 * the Heurist system and database, and prepares JSON responses.
 *
 * @property hserv\System $system The Heurist system object.
 * @property array $request Sanitized input request parameters.
 * @property array $allowedAction List of valid actions for the controller.
 * @property string|null $sysadminPWD System administrator password from request.
 * @property array|string|null $response The response to be sent to the client.
 */
class BulkEmailController{

    private $system;
    private $request;
    private $allowedAction = ['list_databases', 'email_details', 'record_count', 'user_count', 'send_emails', 'csv_export', 'session', 'prepare_email'];

    private $sysadminPWD;
    private $response;

    /**
     * Constructor for BulkEmailController.
     *
     * Initializes the controller, sanitizes input, and sets up the Heurist system object.
     *
     * @param hserv\System $system The Heurist system object.
     */
    public function __construct($system){

        $this->sysadminPWD = USanitize::getAdminPwd('pwd');
        $this->request = USanitize::sanitizeInputArray();

        $this->system = $system;
        
        if(!$this->system->init(@$this->request['db'])){
            $this->response = $this->system->getError();
        }

        if(!array_key_exists('a', $this->request) || !in_array($this->request['a'], $this->allowedAction)){
            $this->response = ['status' => HEURIST_INVALID_REQUEST, 'message' => 'Missing action', 'other' => $this->request];
        }
    }

    /**
     * Runs the requested action based on the 'a' parameter in the request.
     *
     * This is the main entry point for routing actions within the controller.
     * It sets time limits and calls the appropriate private method for the action.
     *
     * @return void
     */
    public function run(){

        if(!empty($this->response)){
            return;
        }

        if($this->request['a'] !== 'send_emails'){
            set_time_limit(300);
        }

        switch($this->request['a']){

            case 'list_databases': // Get a list of DBs based on the list of provided filters, first search gets all dbs
                $this->getDatabases();
                break;
            case 'record_count': // Get a count of records
                $this->getRecordCount();
                break;
            case 'user_count': // Get a count of distinct users
                $this->getUserCount();
                break;
            case 'email_details': // Get the Title and Short Summary field for the selected id, id is for Email record
                $this->getEmailDetails();
                break;
            case 'send_emails':
                $this->sendEmails();
                break;
            case 'csv_export':
                $this->exportCSV();
                break;
            case 'session':
                $this->getSessionResult();
                break;
            case 'prepare_email':
                $this->prepareEmail();
                break;
            default:
                $this->response = ['status' => HEURIST_ERROR, 'message' => "Invalid action provided to bulk mailer, action provided: {$this->request['a']}"];
                break;
        }
    }

    /**
     * Retrieves a list of databases, optionally filtered by criteria.
     *
     * If 'db_filtering' is 'all', it fetches details for all available databases.
     * If 'db_filtering' is an array (with count, lastmod_logic, lastmod_period, lastmod_unit),
     * it filters the databases accordingly.
     * Sets $this->response with the list of databases and their details.
     *
     * @access private
     * @return void
     */
    private function getDatabases(){

        if(!isset($this->request['db_filtering'])){
            return;
        }

        $mysqli = $this->system->getMysqli();

        $dbRequest = $this->request['db_filtering'];
        $dbList = $this->getAvailableDatabases(); // list of databases

        if(empty($dbList)){
            return;
        }

        if($dbRequest == 'all'){ // No additional filtering needed

            $data = ['list' => $dbList, 'details' => []];
            $details = $this->getDatabaseDetails($mysqli, $dbList);
            $data['details'] = $details;

        }elseif(is_array($dbRequest) && count($dbRequest)==4){ // Do filtering, record count and last modified
            $data = $this->filterDatabases($dbList);
        }

        $this->response = ['status' => HEURIST_OK, 'data' => $data, 'request' => $dbRequest];
    }

    /**
     * Gets a list of all available and valid Heurist databases on the server.
     *
     * A database is considered valid if it starts with HEURIST_DB_PREFIX,
     * has a schema version >= 1.3.0, and contains the required tables
     * ('Records', 'recDetails', 'sysUGrps', 'sysUsrGrpLinks').
     *
     * @access private
     * @return array<string> An array of valid database names.
     */
    private function getAvailableDatabases(){

        $mysqli = $this->system->getMysqli();

        $dbRequest = $this->request['db_filtering'];
        $dbList = [];
        $invalidDBs = [];

        // Get all dbs that start with the Heurist prefix
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE `SCHEMATA`.`SCHEMA_NAME` LIKE '".HEURIST_DB_PREFIX."%' ORDER BY `SCHEMATA`.`SCHEMA_NAME` COLLATE utf8_general_ci";

        $heuristDBs = $mysqli->query($query);
        if(!$heuristDBs){

            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Unable to retrieve a list of Heurist databases.<br>', 'error_msg' => $mysqli->error, 'request' => $dbRequest];
            return [];
        }

        while($db = $heuristDBs->fetch_row()){

            $dbname = $db[0];
            if(preg_match('/[^A-Za-z0-9_\$]/', $dbname)){ //invalid dbname
                continue;
            }
            
            // check version - use >=1.3.0
            $query = "SELECT sys_dbVersion, sys_dbSubVersion FROM {$dbname}.sysIdentification";
            $ver = mysql__select_row_assoc($mysqli, $query);
            if(!$ver || $ver['sys_dbSubVersion'] < 3){
                continue; //skip - broken database || old database
            }

            // Ensure that the Heurist db has the required tables, ignore if they don't
            $query = "SHOW TABLES IN {$dbname} WHERE Tables_in_{$dbname} = 'Records' OR Tables_in_{$dbname} = 'recDetails' OR Tables_in_{$dbname} = 'sysUGrps' OR Tables_in_{$dbname} = 'sysUsrGrpLinks'";
            $table_listing = $mysqli->query($query);
            if(!$table_listing || mysqli_num_rows($table_listing) != 4){ // Skip, missing required tables

                if($table_listing && $dbRequest == 'all'){
                    $invalidDBs[] = $dbname;
                }

                continue;
            }

            $dbList[] = $dbname;
        }

        return $dbList;
    }

    /**
     * Gets the total record count for each database in the provided list.
     *
     * Sets $this->response with an associative array where keys are database names
     * and values are their record counts (or 'error' if retrieval fails).
     *
     * @access private
     * @return void
     */
    private function getRecordCount(){

        if(!isset($this->request['db_list'])){
            return;
        }

        $mysqli = $this->system->getMysqli();

        $dbList = $this->request['db_list'];
        if(!is_array($dbList)){
            $dbList = explode(',', $dbList);
        }

        $data = [];
        foreach($dbList as $db){

            if(strpos($db, HEURIST_DB_PREFIX) !== 0){
                continue;
            }

            $db = preg_replace(REGEX_ALPHANUM, '', $db);//for snyk

            $query = "SELECT count(*) FROM `{$db}`.`Records` WHERE rec_FlagTemporary != 1";
            $res = $mysqli->query($query);
            if(!$res){
                $data[$db] = 'error';
                continue;
            }

            while($row = $res->fetch_row()){
                $data[$db] = $row[0];
            }
        }

        $this->response = ['status' => HEURIST_OK, 'data' => $data, 'request' => implode(',', $dbList)];
    }

    /**
     * Gets the count of distinct users across the specified databases, based on user type.
     *
     * User types can be 'owner', 'manager', 'admin', or 'user'.
     * Sets $this->response with the total count of distinct users.
     *
     * @access private
     * @return void
     */
    private function getUserCount(){

        if(!isset($this->request['user_count'], $this->request['db_list'])){
            return;
        }

        $mysqli = $this->system->getMysqli();

        $userRequest = $this->request['user_count'];
        $dbList = $this->request['db_list'];
        if(!is_array($dbList)){
            $dbList = explode(',', $dbList);
        }

        $data = 0;
        $emailList = [];

        foreach($dbList as $db){

            $db = preg_replace(REGEX_ALPHANUM, '', $db);//for snyk
            
            $query = "SELECT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail FROM `{$db}`.sysUGrps AS ugr ";
            $needGroups = false;
            $whereClause = '';

            switch($userRequest){

                case 'owner': // Owners

                    $whereClause = 'ugr.ugr_ID = 2';
                    break;

                case 'manager': // Admins of Database Managers Workgroup

                    $needGroups = true;
                    $whereClause = "ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID = 1";

                    break;

                case 'admin': // Admins for any workgroups

                    $needGroups = true;
                    $whereClause = "ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID IN
                    (SELECT ugr_ID
                            FROM `{$db}`.sysUGrps
                            WHERE ugr_Type = 'workgroup' AND ugr_Enabled != 'n')";

                    break;

                case 'user': // ALL users

                    $needGroups = true;
                    $whereClause = "ugr.ugr_Type = 'user' AND ugr.ugr_Enabled != 'n'";

                    break;

                default:

                    $this->response = ['status' => HEURIST_INVALID_REQUEST, 'message' => 'Invalid user choice', 'request' => $userRequest];
                    break;
            }

            if(empty($whereClause)){
                return;
            }

            if($needGroups){
                $query .= ", `{$db}`.sysUsrGrpLinks AS ugl ";
                $whereClause = "ugl.ugl_UserID = ugr.ugr_ID AND {$whereClause}";
            }

            $query .= " WHERE {$whereClause}";

            $res = $mysqli->query($query);
            if(!$res){
                //Unable to retrieve user count for databases
                continue;
            }

            while($row = $res->fetch_row()){

                if(!in_array($row[2], $emailList)){
                    $data += 1;
                    $emailList[] = $row[2];
                }
            }

        }

        $this->response = ['status' => HEURIST_OK, 'data' => $data, 'request' => $userRequest];
    }

    /**
     * Retrieves the title and body (short summary) for a specified Email record ID.
     *
     * Uses ConceptCodes '2-1' (Title/Name) and '2-3' (Short Summary).
     * Sets $this->response with an array containing the email title and body.
     *
     * @access private
     * @return void
     */
    private function getEmailDetails(){

        if(!isset($this->request['recid'])){
            return;
        }

        $mysqli = $this->system->getMysqli();

        $emailTitle = '';
        $emailBody = '';
        $id = intval($this->request['recid']);

        // Get title/name and short summary detail type ids
        $title_dtyID = ConceptCode::getDetailTypeLocalID('2-1');
        $shortsum_dtyID = ConceptCode::getDetailTypeLocalID('2-3');

        // Validate ID
        $missing = '';
        $missingField = empty($title_dtyID) || empty($shortsum_dtyID);
        if(empty($title_dtyID) && empty($shortsum_dtyID)){
            $missing = 'for both title and short summary detail types.';
        }elseif($missingField){
            $missing = empty($title_dtyID) ? 'for the title detail type.' : 'for the short summary detail type.';
        }
        if(!is_numeric($id) || intval($id) < 1){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'An invalid Email record id was provided.', 'request' => htmlspecialchars($id)];
        }elseif($missingField){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => "Unable to retrieve the local id {$missing} <br>If this problem persists, please notify the Heurist team."];
        }

        $query = "SELECT dtl_Value, dtl_DetailTypeID
                FROM recDetails
                WHERE dtl_RecID = $id AND dtl_DetailTypeID IN ({$shortsum_dtyID}, {$title_dtyID})";

        $detail_rtn = $mysqli->query($query);
        if(!$detail_rtn){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => "Unable to retrieve the details of Email record ID => $id.<br>If this persists, please notify the Heurist team.<br>", "error_msg"=>$mysqli->error, "request"=>$id];
            return;
        }

        while($emailDetails = $detail_rtn->fetch_row()){
            if($emailDetails[1] == $shortsum_dtyID){
                $emailBody = $emailDetails[0];
            }elseif($emailDetails[1] == $title_dtyID){
                $emailTitle = $emailDetails[0];
            }
        }

        $this->response = ['status' => HEURIST_OK, 'data' => [$emailTitle, $emailBody], 'request' => $id];
    }

    /**
     * Initiates the email sending process.
     *
     * Verifies required parameters and the system administrator password.
     * If valid, it includes `bulkEmailSystem.php` and calls `sendSystemEmail` to handle the actual sending.
     * Sets $this->response with the result from `sendSystemEmail`.
     *
     * @access private
     * @global string $passwordForServerFunctions The password required for server functions, defined in heuristConfigIni.php.
     * @return void
     */
    private function sendEmails(){

        global $passwordForServerFunctions;

        if(!isset($this->request['databases'], $this->request['users'], $this->request['db'], $this->sysadminPWD)){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Missing required parameters for sending bulk emails'];
            return;
        }elseif($this->system->verifyActionPassword($this->sysadminPWD, $passwordForServerFunctions)){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'The System Administrator password is invalid, please re-try in the previous tab/window.'];
            return;
        }

        // Attempt to send the system email.
        require_once __DIR__ . '/bulkEmailSystem.php'; // BulkEmailSystem
        $this->response = sendSystemEmail($this->request);
    }

    /**
     * Initiates the CSV export process.
     *
     * Verifies required parameters and the system administrator password.
     * If valid, it includes `bulkEmailSystem.php` and calls `getCSVDownload` to handle the actual sending.
     * Sets $this->response with the result from `sendSystemEmail`.
     *
     * @access private
     * @global string $passwordForServerFunctions The password required for server functions, defined in heuristConfigIni.php.
     * @return void
     */
    private function exportCSV(){

        global $passwordForServerFunctions;

        if(!isset($this->request['databases'], $this->request['users'], $this->request['db'], $this->sysadminPWD)){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Missing required parameters for sending bulk emails'];
            return;
        }elseif($this->system->verifyActionPassword($this->sysadminPWD, $passwordForServerFunctions)){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'The System Administrator password is invalid, please re-try in the previous tab/window.'];
            return;
        }

        // Attempt to send the system email.
        require_once __DIR__ . '/bulkEmailSystem.php'; // BulkEmailSystem
        $this->response = getCSVDownload($this->request);
    }

    /**
     * Retrieves the progress or result of an email sending session.
     *
     * Uses `mysql__update_progress` to get the session status. If the session is complete
     * or terminated, it removes the session progress indicator.
     * Sets $this->response based on the session status.
     * REMARK: This method relies on `mysql__update_progress` to eventually populate
     *         `$this->response` or indicate termination.
     *
     * @access private
     * @return void
     */
    private function getSessionResult(){

        if(!isset($this->request['session'])){
            $this->response = ['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Missing session ID'];
            return;
        }

        $mysqli = $this->system->getMysqli();

        $progress = mysql__update_progress($mysqli, $this->request['session'], false, null);
        $status = !$progress || $progress == 'terminate' ? HEURIST_INVALID_REQUEST : HEURIST_OK;
        $progress = !$progress || $progress == 'terminate' ? '' : $progress;

        if($status === HEURIST_OK || $progress == 'terminate'){
            mysql__update_progress($mysqli, $this->request['session'], false, 'REMOVE');
        }
    }

    /**
     * Prints the JSON encoded response and optionally exits.
     *
     * @param bool $exit If true, the script will exit after printing the response. Defaults to true.
     * @return void
     */
    public function printOutput($exit = true){

        if(empty($this->response)){
            if($exit){
                exit;
            }
            return;
        }

        if(is_array($this->response)){
            $this->response = json_encode($this->response);
        }

        print $this->response;

        if($exit){
            exit;
        }
    }

    /**
     * Gets the current response data.
     *
     * @return array|string|null The response data, which may be an array, a JSON string, or null.
     */
    public function getOutput(){
        return $this->response;
    }

    /**
     * Filters a list of databases based on record count and last modification date criteria.
     *
     * Criteria are provided in `$this->request['db_filtering']`.
     *
     * @access private
     * @param array<string> $dbList The initial list of database names to filter.
     * @return array<string> The filtered list of database names.
     */
    private function filterDatabases($dbList){

        $mysqli = $this->system->getMysqli();

        $dbRequest = $this->request['db_filtering'];
        $data = [];

        $count = intval($dbRequest['count']);
        if($count <= 0){
            $count = 0;
        }

        $lastmod_logic = $mysqli->real_escape_string( filter_var($dbRequest['lastmod_logic'],FILTER_SANITIZE_STRING) );
        $lastmod_logic = $lastmod_logic == 'more' ? '<=' : '>=';
        $lastmod_period = intval($dbRequest['lastmod_period']);

        //to avoid injection
        $lastmod_unit = 'ALL';
        switch(strtoupper(@$dbRequest['lastmod_unit'])){
            case 'DAY':  $lastmod_unit = 'DAY'; break;
            case 'MONTH':  $lastmod_unit = 'MONTH'; break;
            case 'YEAR':  $lastmod_unit = 'YEAR'; break;
            default;
        }

        $lastmod_where = $lastmod_unit !='ALL' ? "AND rec_Modified {$lastmod_logic} date_format(curdate(), '%Y-%m-%d') - INTERVAL {$lastmod_period} {$lastmod_unit}" : "";

        foreach($dbList as $db){

            $db = preg_replace(REGEX_ALPHANUM, '', $db);

            $isok = true;

            if($count > 0){
                $count_res = mysql__select_value($mysqli, "select count(*) from {$db}.`Records` where (not rec_FlagTemporary)");
                $isok =  intval($count_res) > $count;
            }
            
            if($isok && $lastmod_unit != 'ALL'){
                $cnt = mysql__select_value($mysqli, "select count(*) from {$db}.`Records` where (not rec_FlagTemporary) {$lastmod_where}");
                $isok = $cnt > 0;
            }

            if($isok){
                $data[] = $db;
            }
        }

        return $data;
    }

    /**
     * Retrieve the record count and last update (record or structure, whichever is newer) for a list of databases.
     *
     * @access private
     * @param \mysqli $mysqli The mysqli connection object.
     * @param array<string> $dbList An array of database names (prefixed with HEURIST_DB_PREFIX).
     * @return array<array<string, mixed>> An array of associative arrays, where each inner array contains:
     *                                     'name' (string) - The database name (without prefix).
     *                                     'rec_count' (int) - The number of non-temporary records.
     *                                     'last_update' (string|null) - The date of the last update (YYYY-MM-DD),
     *                                                                  or null if no updates found.
     */
    private function getDatabaseDetails($mysqli, $dbList){

        $details = [];

        // Retrieve record count and last update (record or structure)
        foreach ($dbList as $database) {

            $database = preg_replace(REGEX_ALPHANUM, "", $database);

            $dbData = ['name' => $database, 'rec_count' => 0, 'last_update' => null];

            // Get record count
            $dbData['rec_count'] = mysql__select_value($mysqli, "SELECT COUNT(*) FROM `$database`.Records WHERE rec_FlagTemporary != 1");

            $lastRecent = mysql__select_value($mysqli,
            "SELECT CONVERT_TZ(MAX(rec_Modified), @@session.time_zone, \"+00:00\") FROM `$database`.Records WHERE rec_FlagTemporary != 1");

            if(!$lastRecent){
                $lastRecent = date_create($lastRecent);
            }

            $lastStruct = getDefinitionsModTime($mysqli, true);

            if(!$lastRecent || $lastStruct > $lastRecent){
                $lastRecent = $lastStruct;
            }

            $dbData['last_update'] = $lastRecent->format('Y-m-d');

            $details[] = $dbData;
        }

        return $details;
    }

    /**
     * Save chunks of the email's body within a temporary file, to avoid request too large responses
     *
     * @access private
     * @return void
     */
    private function prepareEmail(){

        $append = $this->request['append'];
        $file = HEURIST_SCRATCH_DIR . "bulkmailer_{$this->request['sessionID']}.txt";

        $preparedBody = '';
        if($append && file_exists($file)){
            $preparedBody = file_get_contents($file);
        }

        $preparedBody .= $this->request['emailBody'];

        $result = file_put_contents($file, $preparedBody);

        $this->response = ['status' => !$result ? HEURIST_ERROR : HEURIST_OK, 'msg' => 'Failed to save the email body chunk', 'request' => $this->request['sessionID']];
    }
}

$system = new hserv\System();

$controller = new BulkEmailController($system);
$controller->run();
$controller->printOutput();
