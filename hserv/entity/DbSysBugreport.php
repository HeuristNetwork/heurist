<?php
/**
* DbSysBugreport.php - Class DbSysBugreport
*
* Handles bug reports and contact form submissions.
*
* @project     Heurist academic knowledge management system
* @package Entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.6.5
*/
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\System;
use hserv\entity\DbRecUploadedFiles;
use hserv\utilities\USystem;

// ---
// This entity depends on record/file helper functions (e.g. recordSave, file registration helpers).
// It is required here because this class both creates records and registers uploaded/downloaded files.
// ---
require_once dirname(__FILE__).'/../records/search/recordFile.php';

/**
* Class DbSysBugreport
*
* Handles bug reports and contact form submissions.
*
* This class has two main functionalities:
* 1. Creating bug report records: It can create new task records (Type 56, e.g., "Features, Bug, Issue")
*    in a designated Heurist bug tracker database (often `HEURIST_BUGREPORT_DATABASE` on `HEURIST_MAIN_SERVER`).
*    This may involve remote communication if the current Heurist instance is not the main server.
*    It also handles sending email notifications about the bug report.
* 2. Processing website contact forms: If specific 'email' and 'content' fields are provided,
*    it sends an email to the database owner or a specified address.
*
* Search and direct delete/batch operations on bug reports via this class are typically disabled.
*
*/
class DbSysBugreport extends DbEntityBase
{

    /** @var bool Flag to determine if logout should be performed after an action (e.g., public bug submission). */
    private $performLogout = false;

    /** @var string Email template for bug report notifications. Placeholders like __LINK__, __DESC__ are replaced. */
    private $reportEmail = <<<EMAIL
    Your ticket has been successfully added to, or updated in, the Heurist Job tracker database. 
    <br>
    <br>
    Description:__DESC__
 
    <hr>
    
    You can test in the hx-alpha version which is updated nightly (it may therefore not be updated until tomorrow). The standard /heurist/ version is updated infrequently (our target is monthly).
    <br>
    If you are not running the alpha version, replace /heurist/ in the URL with /hx-alpha/ where x is the version required (7 as of 2026).
    <br>
    <br>
    You can view your ticket at: <a href="__LINK__">__LINK__</a><br><br>

    Heurist development team only: <a href="__EDIT__">Edit</a><br><br>
    
    For current and resolved tickets see: <a href="__DB_JOBTRAK__/web/64/1526">__DB_JOBTRAK__</a><br><br>
    <br>
    Reporter: __NAME__ [__EMAIL__]<br>
    Database: __DBLINK__<br>
    __MEMBER__<br>
    
    EMAIL;

    /** @var string Message about membership for non-member emails, replaces __MEMBER__ within the report email. */
    private $membershipString = <<<MEMBERSHIP
    Priority is given to fixing critical bugs affecting many users and to tickets submitted by members<br>
    of the <em>Heurist Network</em> association. Please consider <a href="https://forms.gle/xdAhjcZaSxpzkAsh9" target=_blank>joining the association</a> to support Heurist.<br>
    MEMBERSHIP;
    
    /** @var int The Heurist Record Type ID for bug reports/tasks (typically 56). */
    private $bugReportType = 56;

    /** @var bool Whether the current server is the main server */
    private $isMainServer = false;

    // ---------------------------------------------------------------------
    // Constructor: initialise the entity and detect whether we are running on
    // the designated 'main server' (HEURIST_MAIN_SERVER) for central services.
    // ---------------------------------------------------------------------
    /**
     * Constructor for DbSysBugreport.
     *
     * Calls the parent constructor and sets `requireAdminRights` to false,
     * allowing non-admin users (including guests for public bug tracker) to submit reports.
     *
     * @param \hserv\System $system The main Heurist system object.
     * @param array|null $data Optional data to initialize the entity with.
     */
    public function __construct( $system, $data=null ) {
        parent::__construct( $system, $data );
        $this->requireAdminRights = false;

        // Determine whether the current base URL appears to be the configured main server.
        // ChatGPT:ToDo Consider using parse_url() and a strict host compare to avoid false positives
        // (e.g. if HEURIST_MAIN_SERVER is a substring of another domain).
        $this->isMainServer = strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false;
    }

    // ---------------------------------------------------------------------
    // Permission model:
    // - Normally handled by DbEntityBase::_validatePermission().
    // - If that fails, and we are on the public bug tracker DB hosted on the main server,
    //   we try to log in as a public 'extern' user to allow anonymous submissions.
    // ---------------------------------------------------------------------
    /**
     * Validates user permissions for bug report submission.
     *
     * Overrides the parent method. If the initial permission check fails
     * (e.g., user not logged in) and the current database is the public
     * bug report database on the main server, it attempts to log in as
     * a public guest user ('extern') to allow submission.
     * Sets `$this->performLogout` if public login is successful.
     *
     * @return bool True if permissions are sufficient (either originally or via public guest login),
     *              false otherwise.
     */
    protected function _validatePermission(){

        $res = parent::_validatePermission();

        if(!$res){

            // If we're on the main server AND in the configured bug tracker database,
            // allow public submission by switching to the 'extern' account.
            $attempt_public_login = $this->isMainServer && $this->system->dbname() == HEURIST_BUGREPORT_DATABASE; //dbnameWithoutHost

            // performLogout is used later to restore the original (anonymous) state after saving.
            $this->performLogout = $attempt_public_login ? $this->system->doLogin('extern', null, 'public', true) : false; // attempt login to publicly available guest account

            if($this->performLogout){
                $this->system->getCurrentUserAndSysInfo(false); // refresh system vars before continuing
                $res = true;
            }
        }

        return $res;
    }

   /**
     * Searches for bug reports (currently disabled).
     *
     * This method is intended for searching bug reports but is currently disabled
     * and will always return null.
     *
     * @return null This method is disabled and always returns null.
     */
    public function search(){
        return null;
    }

    //
    //   This is virtual "save". In fact it sends email
    //
    // ---------------------------------------------------------------------
    // save(): Virtual 'save' for this entity.
    // It either:
    //  (A) sends a contact-form email (website widget), OR
    //  (B) creates a ticket/bug-report record (possibly via the main server).
    // ---------------------------------------------------------------------
    /**
     * Handles saving a bug report or processing a contact form email.
     *
     * This method has two main operational modes:
     * 1. **Contact Form Email (Website Integration):** If `$this->records[0]` contains 'email' and 'content' keys
     *    (typically from a CMS website contact form), it calls `_prepareEmail()` to send the content
     *    to the database owner or a pre-configured address.
     * 2. **Bug Report Creation:** Otherwise, it proceeds to create a bug report.
     *    - If `$this->data['new_record']` is set (indicating a request from an external Heurist server),
     *      it calls `createBugReportRecord()` with that data.
     *    - Otherwise, it processes `$this->records[0]` (prepared from `$this->data['fields']` by `prepareRecords`),
     *      gathers necessary information (user details, browser agent, Heurist version, URLs),
     *      and then either creates the record directly (if on the main bug tracker server)
     *      or makes a remote request to the main server's `entityScrud.php` to create the record.
     *    - Sends an email notification with details of the created bug report.
     *
     * Validates user permissions (potentially logging in a public guest user) and mandatory fields.
     *
     * @return array|bool For contact form: Result of `_prepareEmail()`.
     *                    For bug report: An array containing a success message and link on success,
     *                                   or false on failure. Errors are added to the system object.
     */
    public function save(){

        // prepareRecords() converts incoming $this->data['fields'] into $this->records[]
        // and performs basic normalisation expected by DbEntityBase.
        if(!$this->prepareRecords()){
            return false;
        }

        // Mode A: CMS contact-form integration (expects a single record with 'email' + 'content').
        if(is_array($this->records) && count($this->records)==1){
            //SPECIAL CASE - this is response to emailForm widget (from CMS website page)
            // it sends email to owner of database or to email specified in website_id record
            $fields = $this->records[0];
            if(array_key_exists('email', $fields) && array_key_exists('content', $fields)){
                return $this->_prepareEmail($fields);
            }
        }

        // Mode B: bug report submission - enforce permissions (may auto-login 'extern' on main tracker).
        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }

        // If called from an external server, the upstream server passes a prepared 'new_record' payload.
        // In that case we skip local field mapping and just create the record directly.
        if(array_key_exists('new_record', $this->data)){
            // Called from external server

            $res = $this->createBugReportRecord($this->data['new_record']);

            if($this->performLogout){
                $this->system->doLogout();
            }

            return $res !== false ? $res['data'] : $res;
        }

        // Validate mandatory fields for each record (typically there is only one).
        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }
        }

        $record = $this->records[0];

        // We need mysqli for certain user lookups and to record errors centrally.
        $mysqli = $this->system->getMysqli();

        // Destination email for bug reports is a system configuration constant.
        // If missing, the instance cannot deliver reports (hard failure).
        $toEmailAddress = HEURIST_MAIL_TO_BUG;

        if(empty($toEmailAddress)){
             $this->system->addError(HEURIST_SYSTEM_CONFIG,
                    'The owner of this instance of Heurist has not defined either the info nor system emails');
             return false;
        }

        $toAddresses = ['to' => [$toEmailAddress]];
        $reportDetails = [];

        // Build the Heurist record payload that will be created in the Job Tracker database.
        // NOTE: 'details' keys are DetailType IDs (dty_ID) in the Job Tracker schema.
        $new_record = [
            'ID' => 0,// New record
            'RecTypeID' => $this->bugReportType,// Task (Feature, Bug, Issue) rectype on Heurist_Job_Tracker
            'NonOwnerVisibility' => 'public',// Force visibility to public
            'NonOwnerVisibilityGroups' => 0,// Force group visibility to everyone
            'OwnerUGrpID' => 0,// Force ownership to DB admins later
            'details' => []
        ];

        // Title: stored both as record title (detail 1) and used for email subject line.
        $report_title = htmlspecialchars($record['bug_Title']);
        $bug_title = "Bug report or feature request: $report_title";
        $new_record['details']['1'] = $report_title;
        $reportDetails['1'] = ['Title' => $report_title];

        // Description: converted to HTML <br> line breaks for email / rich-text display.
        // ChatGPT:ToDo Consider ENT_QUOTES + explicit charset in htmlspecialchars() for safety/consistency.
        //keep new line
        $bug_descr = htmlspecialchars($record['bug_Description']);
        if(!empty($bug_descr)){

            $bug_descr = str_replace("\n",'<br>', $bug_descr);

            $database = empty(@$record['bug_Database']) ? '' : "Database: {$record['bug_Database']}<br>";
            $server = empty(@$record['bug_Server']) ? '' : "Server: {$record['bug_Server']}<br>";

            $new_record['details']['3'] = "{$database}{$server}<p>{$bug_descr}</p>";
            $reportDetails['3'] = ['Issue description' => "{$database}{$server}{$bug_descr}"];
        }

        // Extra metadata fields (Type, Location, URL, etc.) are stored using fixed dty_IDs.
        // ChatGPT:ToDo Consider centralising these dty_ID constants in one place with descriptive names.
        //extra information
        $new_record['details']['960'] = array_key_exists('bug_Type', $record) ? $record['bug_Type'] : [6986];
        $reportDetails['960'] = ['Type' => $new_record['details']['960']];

        $new_record['details']['958'] = array_key_exists('bug_Location', $record) ? $record['bug_Location'] : [7105];
        $reportDetails['958'] = ['Location' => $new_record['details']['958']];

        $url = @$record['bug_URL'];
        $cur_url = HEURIST_BASE_URL.'?db='.$this->system->dbname();
        if(!empty($url)){
            $new_record['details']['993'] = [$url, $cur_url];
            $reportDetails['993'] = ['URL' => [$url, $cur_url]];
        }else{
            $new_record['details']['993'] = $cur_url;
            $reportDetails['993'] = ['URL' => $cur_url];
        }

        // Reporter identity:
        // - default: current logged-in user
        // - if bug_GuestUser==1, use name+email supplied in the form (required).
        $user_info = $this->system->getCurrentUser();
        if(@$record['bug_GuestUser'] == 1){
            if(empty($record['bug_Username']) || empty($record['bug_Email'])){
                $this->system->addError(HEURIST_ACTION_BLOCKED, "You must provided your name and email address for bug reports");
                return false;
            }
            $user_info = [
                'ugr_FullName' => $record['bug_Username'],
                'ugr_eMail' => $record['bug_Email'],
                'ugr_Organisation' => ''
            ];
        }
        if($user_info){

            // Fetch the full user record from DB (organisation + canonical email) using the user's ID.
            // ChatGPT:ToDo If $user_info is overridden for guests, it may not contain ugr_ID; ensure this call is safe.
            $user = user_getByField($mysqli, 'ugr_ID', $user_info['ugr_ID']);

            $new_record['details']['955'] = "{$user_info['ugr_FullName']} [{$user['ugr_Organisation']}]";
            $new_record['details']['956'] = $user['ugr_eMail'];

            $reportDetails['955'] = ["User's name" => $user['ugr_FullName']];
            $reportDetails['956'] = ["User's email" => $user['ugr_eMail']];
        }

        // Attachments (screenshots): bug_Image points to temporary uploaded files.
        // These are converted into accessible URLs that the tracker record can store.
        $filename = null;
        $attachment_temp_name = @$record['bug_Image'];
        if(!empty($attachment_temp_name)){

            if(!is_array($attachment_temp_name)){
                $attachment_temp_name = [$attachment_temp_name];
            }

            $filename = [];
            $new_record['details']['38'] = [];
            $reportDetails['38'] = ['Image URLs' => []];
            foreach ($attachment_temp_name as $file) {

                // Temp file naming normalisation: undo some URL encoding and strip '.png'.
                // ChatGPT:ToDo Use pathinfo() and rawurldecode() to handle more extensions and avoid partial replacements.
                // replace encoded space, brackets and remove extension
                $file = str_replace(['%20', '%28', '%29', '.png'], [' ', '(', ')', ''], $file);

                $info = parent::getTempEntityFile($file);

                if(!$info){
                    continue;
                }

                $filename[] = $info->getPathname();

                $image = $this->system->getSysUrl(DIR_ENTITY) . "{$this->config['entityName']}/{$info->getFilename()}";
                $new_record['details']['38'][] = $image;
                $reportDetails['38']['Image URLs'][] = $image;
            }
        }

        // Association membership flag (detail 1067): used to prioritise tickets and adjust messaging.
        $memberString = '';
        if($this->performLogout || USystem::checkAssociationMembership($this->system) !== 'nonmember'){
            $new_record['details']['1067'] = ['7643'];
        }else{
            $new_record['details']['1067'] = [];
        }

        // Create record either locally (if already on main server tracker DB) or via remote call to main server.
        // Remote call uses entityScrud.php with query string parameters.
        // ChatGPT:ToDo Consider POST (instead of GET) because 'new_record' can be large and may exceed URL limits.
        $res = false;
        if($this->isMainServer){ // on server with Heurist_Job_Tracker DB
            $res = $this->createBugReportRecord($new_record);
        }else{

            $params = [
                'a' => 'save',
                'entity' => 'sysBugreport',
                'db' => HEURIST_BUGREPORT_DATABASE,
                'new_record' => $new_record,
                'fields' => ['is_bug_report' => 1]
            ];
            // Construct remote endpoint URL to main server controller.
            // loadRemoteURLContentWithRange() fetches the JSON response (with timeout 60s).
            $url = HEURIST_MAIN_SERVER . '/heurist/hserv/controller/entityScrud.php?' . http_build_query($params);

            $res = loadRemoteURLContentWithRange($url, null, true, 60);

            $json = json_decode($res, true);
            $res = json_last_error() === JSON_ERROR_NONE
                    ? $json
                    : ['status' => HEURIST_UNKNOWN_ERROR, 'message' => 'An unknown response was returned from the main Heurist server.<br>Please, ' . CONTACT_HEURIST_TEAM . ' directly'];
        }

        // If we auto-logged-in as 'extern' earlier, we now logout to avoid persisting that session.
        if($this->performLogout){
            $this->system->doLogout();
        }

        $email_already_sent = false;
        if($res){

            // On success: build canonical tracker URLs, and prepare the confirmation email body.
            // Add record ID, title & url to edit record
            $rec_ID = @$res['status'] == HEURIST_OK ? $res['data']['recID'] : 0;
            $email_already_sent = $rec_ID > 0 ? $res['data']['email_sent'] : false;

            if($rec_ID > 0){

                $bug_title = "H#$rec_ID: {$record['bug_Title']}";
                $report_link = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/view/$rec_ID";
                $report_edit = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/edit/$rec_ID";

                $reportDetails['report'] = $report_link;

                $user_name = is_array($user_info) ? $user_info['ugr_FullName'] : 'None found';
                $user_email = is_array($user_info) ? $user_info['ugr_eMail'] : 'None found';

                $toAddresses = is_array($user_info) ? ['to' => [$user_email, HEURIST_MAIL_TO_BUG]] : $toAddresses;

                $memberString = $this->membershipString;
                if(!empty(@$new_record['details']['1067'])){
                    $memberString = '';
                }

                // Compose HTML email by replacing placeholders in the $reportEmail template.
                // ChatGPT:ToDo This uses $record['details']['3'] but $record here is the *input form record*
                // (keys like 'bug_Description'), not the tracker payload. This looks like a bug; likely intended
                // to use $new_record['details']['3'] or $reportDetails['3'].
                $res = str_replace(['__LINK__', '__DESC__','__NAME__','__EMAIL__','__DBLINK__','__DB_JOBTRAK__','__EDIT__','__MEMBER__'],
                    [$report_link, $record['details']['3'], $user_name, $user_email, $cur_url, HEURIST_MAIN_SERVER.'/'.HEURIST_BUGREPORT_DATABASE,$report_edit,$memberString],
                    $this->reportEmail);

            }elseif(is_array($res)){
                $this->system->addErrorArr($res);
                $res = false;
            }
        }

        // Fallback / backup email logic:
        // If the tracker server didn't send the confirmation email, send a 'backup report' email instead.
        if(!$email_already_sent){
            $email_already_sent = $this->sendBackupReport($toAddresses, $bug_title, $reportDetails, $filename);
            $res = $res ?: 'Your bug report has been sent to the Heurist team.';
        }

        if($res && $email_already_sent){
            return [$res];
        }else{

            // Generic failure path: if no email was sent, surface an error encouraging direct contact.
            // ChatGPT:ToDo Spelling: 'recieve' -> 'receive' (message text).
            $error_msg = 'An unknown error has prevented Heurist from create the bug report.<br>If you do not recieve an email confirming the bug report, please re-try in a few minutes.<br>However, if the issue persists please ' . CONTACT_HEURIST_TEAM . ' directly.';
            $email_already_sent || $this->system->addError(HEURIST_UNKNOWN_ERROR, $error_msg);
            return false;
        }
    }

    /**
     * Creates a bug report record in the Heurist Job Tracker database.
     *
     * This method handles the actual insertion of the bug report data as a new record.
     * If the current Heurist instance is not the main job tracker, it may involve
     * creating a temporary System object to interact with the job tracker database.
     * It registers any attached files (screenshots) with the job tracker database
     * and populates default values for the bug report record.
     * After successfully creating the record, it sends an email notification.
     *
     * @param array $record The bug report data, structured as a Heurist record array
     *                      (including 'RecTypeID', 'details', etc.).
     * @return array|false An associative array `['status' => HEURIST_OK, 'data' => ['recID' => ..., 'email_sent' => ...]]`
     *                     on success, or false on failure. Errors are added to `$this->system`.
     */
    // ---------------------------------------------------------------------
    // createBugReportRecord(): executes the actual record creation in the Job Tracker DB.
    // Key responsibilities:
    // - Ensure we are connected to the Job Tracker database (may differ from current DB).
    // - Register any screenshot URLs as uploaded-file entities in the tracker DB.
    // - Apply default detail values from defRecStructure.
    // - Save the record (recordSave), then update ownership/added-by metadata.
    // - Send confirmation email to reporter (if reporter email exists).
    // ---------------------------------------------------------------------
    private function createBugReportRecord($record){

        if(empty(@$record['details'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Ticket details are missing');
            return false;
        }

        // Determine whether we are already operating inside the Job Tracker database.
        $using_db = $this->system->dbname() == HEURIST_BUGREPORT_DATABASE; //dbnameWithoutHost
        $report_system = $using_db ? $this->system : null;
        if(!$using_db && $this->isMainServer){

            // If we're on the main server but not currently in the tracker DB, spin up a System instance
            // initialised against the tracker DB. This isolates DB context.
            $report_system = new System();
            $using_db = $report_system->init(HEURIST_BUGREPORT_DATABASE, true, false);

            if($using_db && !$report_system->hasAccess()){
                $using_db = $report_system->doLogin('extern', null, 'public', true);
                !$using_db || $report_system->getCurrentUserAndSysInfo();
            }
        }

        $report_system_ready = $report_system && $report_system->isInited() && $report_system->hasAccess();
        if($using_db !== true || !$report_system_ready){
            $action = $report_system && !$report_system->hasAccess() ? 'access' : 'connect to';
            $this->system->addError(HEURIST_ACTION_BLOCKED, "Heurist was unable to $action the Job tracker database");
            return false;
        }

        // Screenshot handling (detail 38): attempt to register images as uploaded files, so the tracker
        // stores stable file references rather than transient temp URLs.
        $files = [];
        $rec_uploads = new DbRecUploadedFiles($report_system);
        if(!empty($record['details']['38']) && $rec_uploads){

            foreach($record['details']['38'] as $idx => $file_url){

                $file_name = explode("\\", $file_url);
                $file_name = str_replace('~', 'bugreport_img_', array_pop($file_name));

                $fileResult = null;
                // Same-server optimisation: if the file URL points to this server, try to register locally
                // by converting the URL to a filesystem path.
                // ChatGPT:ToDo strpos() should be compared with !== false; if the match is at position 0 it is treated as false.
                // ChatGPT:ToDo $fileResult is not assigned in this branch; currently the code always falls through
                // to downloading even after local registration. Likely intended: $fileResult = $record['details']['38'][$idx].
                if(strpos($file_url, HEURIST_SERVER_URL)){ // same server, attempt local registeration

                    $urlBase = $this->system->getSysUrl(DIR_ENTITY);
                    $dirBase = $this->system->getSysDir(DIR_ENTITY);

                    $file = str_replace($urlBase, $dirBase, $file_url);
                    $record['details']['38'][$idx] = $rec_uploads->registerFile($file, null);
                }

                // If local registration didn't happen, try downloading the URL and registering it into recUploadedFiles.
                // (downloadAndRegisterdURL() appears to both fetch and register the file.)
                // ChatGPT:ToDo Verify method name spelling 'downloadAndRegisterdURL' (missing 'e') is correct in class.
                if(!$fileResult){
                    $fileResult = $rec_uploads->downloadAndRegisterdURL($file_url, ['ulf_NewName' => $file_name], 2);
                }

                // Backup: if we cannot register the file in the tracker DB, and we're not already in the tracker DB,
                // record it as an external URL reference instead of dropping the attachment.
                if(!$fileResult && $this->system->dbname() !== HEURIST_BUGREPORT_DATABASE){ //dbnameWithoutHost backup: register as external image
                    $fileResult = $rec_uploads->registerURL($file_url, false, 0);
                }

                if(!$fileResult){
                    continue;
                }

                $record['details']['38'][$idx] = $fileResult;
                $ulf_file_name = mysql__select_value($this->system->getMysqli(), "SELECT ulf_FileName FROM recUploadedFiles WHERE ulf_ID = {$record['details']['38'][$idx]}");
                $files[] = $this->system->getSysDir(DIR_FILEUPLOADS) . $ulf_file_name;
            }

            $record['details']['38'] = array_filter($record['details']['38']); // remove null/false values
        }

        // Identify the 'extern' user in the tracker DB so we can mark the record as created by that account.
        $mysqli = $report_system->getMysqli();
        $guest_user = user_getByField($mysqli, 'ugr_Name', 'extern');// to update AddedBy value in new record
        $uid = is_array($guest_user) ? $guest_user['ugr_ID'] : 0;

        // Apply default field values from defRecStructure (for fields not supplied by the form).
        // ChatGPT:ToDo addDefaultValues() currently receives $record by value; changes won't persist unless passed by reference
        // or the function returns the updated record. This likely means defaults are not actually applied.
        $this->addDefaultValues($report_system, $record);

        // Save the record in tracker DB.
        // Parameters: (system, record array, is_new, is_swf, ???, total_recs=2)
        // The comment suggests total_recs=2 suppresses a standard 'swf' email so we can send a custom email.
        $res = recordSave($report_system, $record, true, false, 0, 2);// set total recs to 2 to avoid sending the swf email, we will send a more specific email instead
        $sent_email = false;

        if(@$res['status'] != HEURIST_OK){
            // Transfer error across
            $this->system->addErrorArr($report_system->getError());
            return false;
        }

        $res = $res['data'];

        // Post-save: force AddedBy (extern) and Owner group to a known admin group (hardcoded as 2).
        // ChatGPT:ToDo Replace magic group IDs (2) with named constants or config; verify group 2 is stable across installs.
        mysql__insertupdate($mysqli, 'Records', 'rec', ['rec_ID' => $res, 'rec_AddedByUGrpID' => $uid, 'rec_OwnerUGrpID' => 2]);

        recordUpdateTitle($report_system, $res, $record['RecTypeID'], "Heurist ticket: {$record['details']['1']}");

        // If we have a reporter email (detail 956), send confirmation email (To: reporter, BCC: tracker admins).
        if(!empty($record['details']['956'])){

            $title = "H#$res: {$record['details']['1']}";

            $report_link = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/view/$res";
            $report_edit = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/edit/$res";

            $user_name = $record['details']['955'] ?? 'None found';
            $user_name = strpos($user_name, '[') > 0 ? explode('[', $user_name)[0] : $user_name;

            $user_email = $record['details']['956'] ?? 'None found';

            $db_link = is_array($record['details']['993']) ? $record['details']['993'][1] : $record['details']['993'];

            $memberString = $this->membershipString;
            if(!empty(@$record['details']['1067'])){
                $memberString = '';
            }

            $msg = str_replace(['__LINK__', '__DESC__', '__NAME__', '__EMAIL__','__DBLINK__','__DB_JOBTRAK__','__EDIT__','__MEMBER__'],
             [$report_link, $record['details']['3'], $user_name, $user_email, $db_link,HEURIST_MAIN_SERVER.'/'.HEURIST_BUGREPORT_DATABASE, $report_edit, $memberString],
              $this->reportEmail);

            $user_query = "SELECT ugr_eMail FROM sysUsrGrpLinks LEFT JOIN sysUGrps ON ugr_ID = ugl_UserID WHERE ugl_GroupID = 1 AND ugl_Role='admin'";
            $admin_emails = mysql__select_list2($mysqli, $user_query);

            $sent_email = sendPHPMailer(null, 'Heurist Tickets', ['to' => $record['details']['956'], 'bcc' => $admin_emails], $title, $msg, $files, true);
        }

        return ['status' => HEURIST_OK, 'data' => ['recID' => $res, 'email_sent' => $sent_email]];
    }

    // ---------------------------------------------------------------------
    // sendBackupReport(): resilience path when normal ticket creation or normal email fails.
    // It builds either:
    //  - a submittable HTML form containing the report (for the Heurist team), or
    //  - a plain summary report (for reporter + team) if the ticket exists but email didn't send.
    // ---------------------------------------------------------------------
    /**
     * Sends a backup bug report email in case the main server cannot be reached, or couldn't send an email
     *
     * If the main server is unavailable the email is sent to the Heurist team only, and includes:
     *  - A submittable HTML form made from the user's report
     *  - Submitting the form will attempt the normal process, ending with a confirmation email to the reporter
     *
     * If the report was made but the usual confirmation email wasn't sent, then the Heurist team and reporter will recieve a simple report summary
     *
     * @param array|string $toAddresses - 'to' addresses for email
     * @param string $emailTitle - Email title, dependant on whether the report was generated
     * @param array $details - Report details, to be displayed
     * @param array|null $files - screenshots + attachments
     * @return bool whether the email was successfully sent
     */
    private function sendBackupReport($toAddresses, $emailTitle, $details, $files = null){

        if(empty($toAddresses) || empty($emailTitle) || empty($details)){
            return false;
        }
        if(empty($files)){
            $files = null;
        }

        $form = '';
        $reportLink = null;

        if(array_key_exists('report', $details)){
            $reportLink = $details['report'];
            unset($details['report']);
        }

        // details format: [ dty_ID => [ 'Field label' => value(s) ], ... ]
        // Used to generate an HTML form that can be POSTed back to entityScrud.php as 'new_record[...]'.
        // [field ID => [field name => [ field values ]], ...]
        foreach($details as $dtyID => $values){

            $fieldName = array_keys($values)[0];
            $fieldValues = array_values($values)[0];
            $fieldValues = is_array($fieldValues) ? $fieldValues : [$fieldValues];

            // ChatGPT:ToDo BUG: variable $value is not defined here. Probably meant:
            //   if (empty($fieldValues)) { continue; }
            // As written, this may emit notices and skip the intended emptiness check.
            if(empty($value)){
                continue;
            }

            $form .= <<<ROW
                <div class="row">
                    <div class="fieldName">{$fieldName}</div>
                    <div class="value">
            ROW;
            $fieldID = "new_record[details][{$dtyID}]" . (count($fieldValues) > 1 ? '[]' : '');
            foreach($fieldValues as $value){

                $form .= <<<FIELD
                        <div class="fieldValue">
                            <textarea name="{$fieldID}">{$value}</textarea>
                        </div>
                FIELD;
            }
            $form .= <<<ROW
                    </div>
                </div>
            ROW;
        }

        $css = <<<CSS
            <style>
                div.row{
                    display: flex;
                    flex-direction: column;
                    margin: 8px 0px;
                }
                div.fieldName{
                    font-weight: bold;
                    font-size: 13px;
                }
                div.fieldValue{
                    margin-left: 10px;
                }
                textarea{
                    min-width: 550px;
                    min-height: 50px;
                }
            </style>
        CSS;

        $fromAddresses = $this->system->getSystemEmail();
        $toAddresses = is_array($toAddresses) ? $toAddresses : ['to' => $toAddresses];

        if(empty(@$toAddresses['to'])){
            $toAddresses = ['to' => $fromAddresses];
        }

        $email_to_reporter = in_array($fromAddresses, $toAddresses['to']);
        if($email_to_reporter && empty(@$toAddresses['bcc'])){
            $toAddresses['bcc'] = HEURIST_MAIL_TO_BUG;
        }

        // Construct actionable content: either a "resubmittable" form (if no tracker link)
        // or a report summary message.
        if(!$reportLink){

            $params = [
                'a' => 'save',
                'entity' => 'sysBugreport',
                'db' => HEURIST_BUGREPORT_DATABASE,
                'fields' => ['is_bug_report' => 1]
            ];
            $url = HEURIST_MAIN_SERVER . '/heurist/hserv/controller/entityScrud.php?' . http_build_query($params);

            $text = <<<EMAIL
                This is a backup message for bug reports and feature requests that were unable to be recorded and/or emailed normally.
                <br><br>
                Use the form below to submit the bug report to the Heurist Tracker database and send a confirmation to the reporter.
                <br><br>
                <form action="{$url}" method="POST" enctype="multipart/form-data">
                    <input type="submit" value="Submit report">
                    {$form}
                </form>
            EMAIL;

            $text = $css . $text;

            // If we couldn't create a tracker record, direct this email only to Heurist team/system mailbox.
            $toAddresses = ['to' => [HEURIST_MAIL_TO_BUG]];

        }else{

            $reportLink = "<a href=\"$reportLink\">$reportLink</a>";

            $text = <<<EMAIL
                This is a backup message for bug reports and feature requests where the report was created, but the confirmation email was not sent.
                <br><br>
                The report can be found at: $reportLink
                <br><br>
                Summary:
                <br>
                $form
            EMAIL;

            $text = $css . $text;
        }

        if(sendPHPMailer(null, 'Heurist Tickets', $toAddresses, $emailTitle, $text, $files, true)){
            return true;
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // _prepareEmail(): handles website contact-form submissions.
    // Flow:
    //  1) validate CAPTCHA (simple challenge stored in session)
    //  2) validate required fields: content + sender email
    //  3) determine recipient (db owner / website record / configured address)
    //  4) send via sendPHPMailer()
    // ---------------------------------------------------------------------
    /**
     * Prepares and sends an email from a website contact form.
     *
     * Validates a captcha code, then extracts email content and sender information,
     * determines recipient(s) (database owner or website record specified), and sends the email.
     *
     * @param array $fields Associative array containing contact form fields:
     *                      - 'captcha': Captcha input from user.
     *                      - 'content': Message content.
     *                      - 'email': The sender's email address.
     *                      - 'person': (Optional) The sender's name.
     *                      - 'website_id': (Optional) Record ID of a website record from which to get recipient details.
     * @return array|false `[1]` on successful email send, false otherwise.
     *                     Errors are added to the system object on failure (captcha, missing fields).
     */
    private function _prepareEmail($fields){

        // CAPTCHA validation relies on $_SESSION['captcha_code'] being set by the form generator.
        // ChatGPT:ToDo Consider rate-limiting / throttling in addition to CAPTCHA to reduce abuse.
        //1. verify captcha
        if (@$fields['captcha'] && @$_SESSION["captcha_code"]){

            $is_InValid = (@$_SESSION["captcha_code"] != @$fields['captcha']);

            if (@$_SESSION["captcha_code"]){
                unset($_SESSION["captcha_code"]);
            }

            if($is_InValid) {
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                   'Are you a bot? Please enter the correct answer to the challenge question');
                return false;
            }
        }else {
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Captcha is not defined. Please provide correct value');
            return false;
        }


        // Extract and validate required fields from the submitted payload.
        // Note the pervasive '@' suppression; this avoids notices but can hide bugs/missing fields.
        // ChatGPT:ToDo Consider replacing '@$fields[...]' with isset() checks for clearer error handling.
        //2. get email fields
        $email_text = @$fields['content'];
        if(!$email_text){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email message is not defined.');
            return false;
        }
        $email_from = @$fields['email'];
        if(!$email_from){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email address is not defined.');
            return false;
        }

        $email_title = isset($fields['title']) ? $fields['title'] : ('Request from: ' . (@$fields['person'] ? $fields['person'] : $email_from));

        $email_to = '';
        $email_to_name = '';

        //3. determine recipient(s)
        // - If website_id is given, get email from that record details (or DB owner)
        // - Otherwise, send to DB owner
        if(@$fields['website_id']){
            $rec = recordSearchByID($this->system, $fields['website_id'], true, false);
            if($rec){
                // dty: 799 = email / 800 = name (based on website record schema)
                $email_to = @$rec['details'][799];
                $email_to_name = @$rec['details'][800];
            }
        }

        if(!$email_to){
            $email_to = $this->system->getOwnerEmail();
            $email_to_name = $this->system->getOwnerName();
        }

        if(!$email_to){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot determine recipient address for contact form.');
            return false;
        }

        $fromAddresses = $this->system->getSystemEmail();
        $toAddresses = ['to' => [$email_to]];

        //4. send email
        if(sendPHPMailer($email_from, $email_to_name, $toAddresses, $email_title, $email_text,
                null, true))
        {
                return array(1);
        }else{
            return false;
        }

    }

    // This entity explicitly disallows deletion via the generic entity interface (safety).
    /**
     * Disables direct deletion of bug reports via this entity class.
     *
     * @param bool $disable_foreign_checks Unused.
     * @return false Always returns false.
     */
    public function delete($disable_foreign_checks = false){
        return false;
    }

    // This entity explicitly disallows batch operations via the generic entity interface (safety).
    /**
     * Disables batch actions for bug reports via this entity class.
     *
     * @return false Always returns false.
     */
    public function batch_action(){
         return false;
    }

    // ---------------------------------------------------------------------
    // addDefaultValues(): fetch default detail values for the bug report record type
    // (from defRecStructure) and apply them to the outgoing tracker record payload.
    // ---------------------------------------------------------------------
    /**
     * Adds default values to a bug report record's details.
     *
     * Retrieves default values defined in the `defRecStructure` for the bug report
     * record type and applies them to the `$record['details']` if the corresponding
     * detail field is not already present or is empty.
     *
     * @param \hserv\System $system The system object (used to get mysqli connection).
     * @param array &$record The bug report record array (passed by reference), specifically its 'details' sub-array.
     * @return void
     */
    private function addDefaultValues($system, $record){

        // Fetch defaults for the tracker record type (rst_DefaultValue can be scalar or encoded).
        // ChatGPT:ToDo This query interpolates $this->bugReportType directly; it's an int constant here,
        // but consider parameterisation if ever user-controlled.
        $def_values = mysql__select_assoc2($system->getMysqli(), "SELECT rst_DetailTypeID, rst_DefaultValue FROM defRecStructure WHERE rst_RecTypeID = {$this->bugReportType}");

        foreach($def_values as $dty_ID => $def_value){

            if($def_value == null || $def_value == '' || (array_key_exists($dty_ID, $record['details']) && !empty($record['details'][$dty_ID]))){
                continue;
            }

            // Apply the default only if the detail is missing/empty in the record payload.
            $record['details'][$dty_ID] = $def_value;
        }
    }
}
?>
