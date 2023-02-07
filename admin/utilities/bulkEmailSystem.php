<?php 

/**
*  Related class for Heurist System Email (massEmailSystem.php)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_records.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
	Return Codes:

	 1 => Function Specific
	 0 => No Error
	-1 => General/Script Error
	-2 => MySQLi Error
	-3 => phpMailer Error
	-4 => File Error
*/

class systemEmailExt {

	private $cur_user; // logged in user's details

	public $databases; // list of selected DBs
	public $invalid_dbs; // list of invalid DBs

	public $users; // user options => owner: DB Owners, manager: DB Manager Admins, user: All Users, admin: All Admins

	private $user_details; // list of user details and databases, indexed on user emails

	public $email_subject; // email title/subject, from the name/title field from a notes record
	public $email_body; // email body, from the short summary field from a notes record + final editing

	public $rec_count; // number of records to include, default: 0
	
	public $rec_lastmod_period;	// time period, default: 6
	public $rec_lastmod_unit; // unit of time, default: MONTH
	public $rec_lastmod_logic; // logic, default: <=

	private $records; // array of records+last modified information

    private $use_native_mail_function = false;
    private $debug_run = false;
    
	private $log; // log of emails, to be placed within a note record within the databases, extended version of receipt
	private $receipt; // receipt for all email transactions, is saved into current db as a note record with the Notes title (not rec_Title) set to "Heurist System Email Receipt"  
	private $error_msg; // error message

	private $user_options = array("owner", "admin", "manager", "user"); // available user options
	private $substitute_vals = array("##firstname##", "##lastname##", "##email##", "##database##", "##dburl##", "##records##", "##lastmodified##"); // available email body substitutions

	/*
	 * Process the data received
	 *
	 * Param: $data => (int) Passed Form Data
	 *
	 * Return: VOID || Error Code
	 */

	public function processFormData($data) {

		global $system;

		$rtn = 0;

		if (!isset($data["db"])) { // A current database is needed
			// Can only be reached by another php script accessing the class or the outside functions at the end
			$this->set_error("No current database has been provided<br>Please contact the Heurist team if this problem persists.");
			return -1;
		}
        
        $this->databases = null;

		if (isset($data["databases"])){
            if(!is_array($data["databases"])){
                $data["databases"] = explode(',',$data["databases"]);
            }
            if(is_array($data["databases"]) && count($data["databases"]) >= 1) { // Get list of Databases
			    $this->databases = $this->validateDatabases($data["databases"]);
            }
		}

        if(!(is_array($this->databases) && count($this->databases)>0)){
			// Here databases is not an array
			$provided_dbs = is_array($data["databases"]) ? "" : "<br>databases => " . $data["databases"];
			$this->set_error("No valid databases have been provided, these databases are required to retrieve a list of users, the last modified records and record count values." . $provided_dbs);
			return -1;
		}

		if(!(is_array($this->databases) && count($this->databases)>0)){
			// No valid databases found
			$this->set_error("All provided databases are broken or invalid, these databases are required to retrieve a list of users, the last modified records and a record count values.");
			return -1;
		}
		
		if (isset($data["users"]) && in_array($data["users"], $this->user_options)) { // Get list of Users
			$this->users = $data["users"];
		} else {

			$main_msg = "No valid users have been provided, this is needed to retrieve a list of users to email from the selected databases.<br>users => " . $data["users"];
			$this->set_error($main_msg); // . "<br>Please contact the Heurist team if this problem persists."
			return -1;
		}

		if (isset($data["emailTitle"]) && is_string($data["emailTitle"])) { // Get Email subject
			$this->email_subject = $data["emailTitle"];
		} else {
			$this->email_subject = null;
		}

		if (isset($data["emailBody"]) && is_string($data["emailBody"])) { // Get Email body
			$this->email_body = $data["emailBody"];
		} else {

			$this->set_error("No email body has been provided, the Heurist team has been notified");
			//$system->addError(HEURIST_ERROR, "Bulk Email System: The email's body (emailBody) is missing from the form data. Data => " . print_r($data, TRUE));
			return -1;
		}

		// Get record filtering options, use defaults if none supplied
		$this->rec_count = (isset($data["recTotal"]) && is_numeric($data["recTotal"]) && $data["recTotal"] >= 0) ? $data["recTotal"] : "none";

		$this->rec_lastmod_period = (isset($data["recModVal"]) && is_numeric($data["recModVal"]) && $data["recModVal"] > 0) ? $data["recModVal"] : 6;
		$this->rec_lastmod_unit = isset($data["recModInt"]) ? $data["recModInt"] : "MONTH";
		$this->rec_lastmod_logic = isset($data["recModLogic"]) ? $data["recModLogic"] : "<=";

		// Initialise other variables
		$this->user_details = array();

		$this->log = "";
		$this->receipt = null;
		$this->error_msg = "";
        
        if(@$data["use_native"]==1){
            $this->use_native_mail_function = true;    
        }

		$rtn = $this->createUserList(); // save user information; first and last name, email, and list of databases

		if ($rtn != 0) {
			return $rtn;
		}else if(is_array($this->user_details) && count($this->user_details) == 0){

			$this->set_error("No users have been retrieved, no emails have been sent");
			return -1;
		}

		$rtn = $this->createRecordsList(); // save record counts and last modified record dates for each db

		if ($rtn != 0) {
			return $rtn;
		}

		// Retrieve current user's email address
		$this->cur_user = $system->getCurrentUser();
		$this->getUserEmail();

		return 0;
	}

	/*
	 * Get current user's email
	 *
	 * Param: None
	 *
	 * Return: VOID
	 */

	private function getUserEmail() {

		global $system;
		$mysqli = $system->get_mysqli();

		$query = "SELECT ugr.ugr_eMail FROM ". HEURIST_DBNAME_FULL .".sysUGrps AS ugr WHERE ugr.ugr_ID = ". $this->cur_user['ugr_ID'];

		$email = $mysqli->query($query);

		if(!$email){
			$this->cur_user['ugr_eMail'] = HEURIST_MAIL_TO_ADMIN;
		}else{
			$this->cur_user['ugr_eMail'] = filter_var($email->fetch_row()[0], FILTER_SANITIZE_EMAIL);
		}
	}

	/*
	 * Validate the list of database, ignore any invalid databases
	 *
	 * Param: $db_list => List of selected databases
	 *
	 * Return: 
	 *	Array, List of valid databases
	 */

	private function validateDatabases($db_list) {

		global $system;
		$mysqli = $system->get_mysqli();

		$valid_dbs = array();

		foreach($db_list as $db){

			// Required tables  are 'Records', 'recDetails', 'sysUGrps', and 'sysUsrGrpLinks'
			$query = "SHOW TABLES IN ".$db." WHERE Tables_in_".$db." = 'Records' OR Tables_in_".$db." = 'recDetails' OR Tables_in_".$db." = 'sysUGrps' OR Tables_in_".$db." = 'sysUsrGrpLinks'";

			$table_listing = $mysqli->query($query);
			if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables
				continue;
			}else{
				$valid_dbs[] = $db;
			}
		}

		return $valid_dbs;
	}

	/*
	 * Create list of user details and associated databases
	 *	
	 * 
	 * Param: None
	 *
	 * Return: VOID || Error Code
	 */

	private function createUserList() {

		global $system;
		$mysqli = $system->get_mysqli();

		$dbs = $this->databases;
		$users = $this->users;

		$wg_count = 0;

		foreach ($dbs as $db){

			$where_clause = "";

			// Create WHERE clause
			if ($users == "owner") { // Owners
				$where_clause = "WHERE ugr.ugr_ID = 2";
			} else if ($users == "manager") { // Admins for workgroup Database Managers
				$where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled = 'y' AND ugl.ugl_GroupID = 1";
 			} else if ($users == "admin") { // Admins for ALL workgroups

 				$where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled = 'y' AND ugl.ugl_GroupID IN 
						  		 (SELECT ugr_ID 
 						   		  FROM " . $db . ".sysUGrps 
 						   		  WHERE ugr_Type = 'workgroup' AND ugr_Enabled = 'y')";

			} else if ($users == "user") { // ALL users
				$where_clause = "WHERE ugr.ugr_Type = 'user' AND ugr.ugr_Enabled = 'y'";
			}

			// Execute WHERE Clause
			if (empty($where_clause)) {

				$this->set_error("Unable to construct WHERE clause for User List query due to an invalid users option<br>users => " . $users);
				return -1;
			} else {

				$query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail 
						  FROM " . $db . ".sysUsrGrpLinks AS ugl  
						  INNER JOIN " . $db . ".sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
						. $where_clause;

				$res = $mysqli->query($query);
				if (!$res) {

					continue;
					//$this->set_error("Query Error: Unable to retrieve the list of users to email<br>Error => " .$mysqli->error);  
					//return -2;
				}

				while ($row = $res->fetch_row()) {

					$db_name = substr($db,strlen(HEURIST_DB_PREFIX));

					if($row[2]){
						$row[2] = filter_var($row[2], FILTER_SANITIZE_EMAIL);
					}

					if (array_key_exists($row[2], $this->user_details)) { // check if user already has data in user_details array, note: only the first set of first/last name are used

						if (!in_array($db_name, $this->user_details[$row[2]]["db_list"])) { // add db to list

							$this->user_details[$row[2]]["db_list"][] = $db_name;
						}
					} else {

						$details = array();
						$details["first_name"] = $row[0];
						$details["last_name"] = $row[1];
						$details["db_list"][] = $db_name;
						
						$this->user_details[$row[2]] = $details;
					}
				}

				$res->close();
			}
		}

		return 0;
	}

	/*
	 * Retrieve the record count and newest last modified date
	 *
	 * Param: None
	 *
	 * Return: VOID || Error Code
	 */

	private function createRecordsList() {

		global $system;
		$mysqli = $system->get_mysqli();

		$dbs = $this->databases;

		$lastmod_period = $this->rec_lastmod_period;
		$lastmod_unit = $this->rec_lastmod_unit;
		$lastmod_logic = $this->rec_lastmod_logic;

		// Create Last modified's WHERE Clause
		$lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic . " date_format(curdate(), '%Y-%m-%d') - INTERVAL " . $lastmod_period . " " . $lastmod_unit . " " : "";

		foreach ($dbs as $db) {

			$count = 0;
			$date = "unknown";

			// Get record count
			$query = "SELECT COUNT(*)
					  FROM (
					   SELECT *
					   FROM " . $db . ".Records AS rec
					   WHERE rec_Title IS NOT NULL
					   AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
					   AND rec_FlagTemporary != 1
					   AND rec_Title != '' " . $lastmod_where . "
					  ) AS a";

		  	$res = $mysqli->query($query);
		  	if (!$res) { 

		  		$this->set_error("Query Error: Unable to get record count for the $db database<br>Error => " .$mysqli->error); 
		  		return -2;
		  	}

		  	if ($row = $res->fetch_row()) {
		  		$count = $row[0];
		  	}

		  	$res->close();

			// Get newest record/last edited record, ignore system email receipt records
		  	$query = "SELECT max(rec_Modified)
					  FROM $db.Records AS rec
					  WHERE rec_Title IS NOT NULL 
					  AND rec_Title != '' $lastmod_where
					  AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
					  ORDER BY rec_Modified DESC
					  LIMIT 1";

			$res = $mysqli->query($query);
		  	if ($res && $row = $res->fetch_row()) { 

		  		$date_obj = new DateTime($row[0]);
		  		$date = $date_obj->format("Y-m-d");

		  	} else {

		  		$res->close();

		  		// Get newest edit to definitions
		  		$query = "SELECT max(newest)
						  FROM (
						   SELECT max(dty_Modified) AS newest FROM $db.defDetailTypes
						   UNION ALL
						   SELECT max(dtg_Modified) AS newest FROM $db.defDetailTypeGroups
						   UNION ALL
						   SELECT max(rst_Modified) AS newest FROM $db.defRecStructure
						   UNION ALL
						   SELECT max(rty_Modified) AS newest FROM $db.defRecTypes
						   UNION ALL
						   SELECT max(rtg_Modified) AS newest FROM $db.defRecTypeGroups
						   UNION ALL
						   SELECT max(trm_Modified) AS newest FROM $db.defTerms
						   UNION ALL
						   SELECT max(vcg_Modified) AS newest FROM $db.defVocabularyGroups
						  ) as maximum";

				$res = $mysqli->query($query);

				if (!$res) {

					$this->set_error("Query Error: Unable to retrieve a last modified record from $db<br>Error => " .$mysqli->error);
					return -2;
				}

				if ($row = $res->fetch_row()) {
					$date_obj = new DateTime($row[0]);
					$date = $date_obj->format("Y-m-d");
				}
		  	}

		  	$this->records[$db] = array($count, $date); // save results
		}

		return 0;
	}

	/*
	 * Prepare email body for sending
	 * 
	 * Param: None
	 *
	 * Return: VOID || Error Code
	 */

	public function constructEmails() {

		global $system;

		$email_rtn = 0;
		$user_cnt = 0;

        if (empty($this->email_body)) {
			$this->set_error("The email body is missing, this needs to be provided at class initialisation.");
			return -1;
        }

		// Initialise PHPMailer
		$mailer = new PHPMailer(true);
		$mailer->CharSet = "UTF-8";
		$mailer->Encoding = "base64";
        $mailer->isHTML(true);
        //OLD $mailer->SetFrom($this->cur_user["ugr_eMail"], $this->cur_user["ugr_FullName"]);
        
        $mailer->SetFrom('no-reply@'.HEURIST_DOMAIN, 'Heurist system');

		foreach ($this->user_details as $email => $details) {

			$db_url_arr = array();
			
			$records_arr = array();
			$lastmod_arr = array();

			$db_listed;
			$db_url_listed;

			$dbs = $details["db_list"];

			// Convert information into arrays and get DB urls
			foreach ($dbs as $db) {
				$url = HEURIST_BASE_URL . "?db=$db";
				$db_url_arr[] = "<a href='$url' target='_blank'>$url</a>";

				$row = $this->records[HEURIST_DB_PREFIX.$db];

				$records_arr[] = $row[0];
				$lastmod_arr[] = $row[1];
			}
			// Then transform from array into readable list
			$db_listed = $this->createListFromArray($dbs);
			$db_url_listed = $this->createListFromArray($db_url_arr);
			$records_listed = $this->createListFromArray($records_arr);
			$lastmod_listed = $this->createListFromArray($lastmod_arr);

			// Replace placeholders with information
			$replace_with = array($details["first_name"], $details["last_name"], $email, $db_listed, $db_url_listed, $records_listed, $lastmod_listed);

			$body = str_ireplace($this->substitute_vals, $replace_with, $this->email_body);

			$title = (isset($this->email_subject) ? $this->email_subject : "Heurist email about databases: " . $db_url_listed);
            $status_msg = '';

            

            if($this->debug_run){

                $status_msg = 'OK';

            }else if($this->use_native_mail_function){ //use php native mail

                $email_header = 'From: Heurist system <no-reply@'.HEURIST_DOMAIN.'>'
                ."\r\nContent-Type: text/html;charset=utf-8\r\n";

                $title = '=?utf-8?B?'.base64_encode($title).'?=';

                $rv = mail($email, $title, $body, $email_header);
                if(!$rv){
                    $this->set_error('Unknown error');
                    $email_rtn = -3;
                }

                //error_log('native mail : '.$email.'  '.$email_rtn);                    

            }else{

                try {
                    $mailer->AddAddress( $email );
                } catch (Exception $e) {
                    $email_rtn = -2;
                    $status_msg = 'Failed, Error Message: Invalid email '.$email;
                    $this->set_error( $status_msg );
                    error_log($status_msg.'  '.$db_listed);                        
                }
                if($email_rtn == 0){

                    $mailer->Subject = $title;
                    $mailer->Body = $body;

                    try {
                        $mailer->send();
                    } catch (phpmailerException $e) {
                        $this->set_error($e->errorMessage());
                        $email_rtn = -3;
                    } catch (Exception $e) {
                        $this->set_error($e->getMessage());
                        $email_rtn = -3;
                    }

                }
            }

            if ($email_rtn == 0) {
                $status_msg = "Sent, Sent Message: " . $body;
            } else {
                $status_msg = "Failed, Error Message: " . $this->get_error();
            }



//error_log('mailer : '.$email.'  '.$email_rtn.' '.$email->ErrorInfo);    
            
			$this->log .= "Values: {databases: {".$db_listed."}, email: $email, name: " .$details['first_name']. " " .$details["last_name"]
					   .", record_count: {".$records_listed."}, last_modified: {".$lastmod_listed."} },"
					   ."Timestamp: " . date("Y-m-d H:i:s") . ", Status: " . $status_msg
					   . "<br/><br/>";

			$mailer->clearAddresses(); // ensure that the current email is gone

			if ($email_rtn != 0) {

				if ($email_rtn == -3){
					$this->set_error("phpMailer has stopped sending emails due to an error with the email system, Error => " . $this->error_msg);
				}else if($email_rtn == -2){
                    continue;
                }

				$this->save_receipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);

				return $email_rtn;
			}

			$user_cnt++;
		}

		$this->save_receipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);

		return $email_rtn;
	}

	/*
	 * Export Email Detail's as a CSV File
	 *
	 * Param: None
	 * 
	 * Return: 
	 *	File => CSV File for Downloading
	 *	, or Error Code
	 */

	public function exportDetailsToCSV(){

		// Open descriptor to output buffer
		$fd = fopen('php://output', 'wb');

		if ($fd == null) {

			$this->set_error("exportDetailsToCSV() Error: Unable to open temporary files for CSV exporting");
			return -4;
		}

		// Construct initial headers
		$filename = "Heurist_System_Email_Export.csv";
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="' . $filename . '";');
		header('Pragma: no-cache');
		header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

        // Add column headers
		fputcsv($fd, array("User Email", "User Name", "Databases", "Database URLs", "Record Counts", "Last Modified Record", "request: {record_limit, lastmodification_period, lastmodification_unit, lastmodification_logic}", "Full Email"));

		// Add column data, row by row
		foreach ($this->user_details as $email => $details) {
			
			$name = $details["first_name"] . " " . $details["last_name"];

			$db_url_arr = array();
			$record_count_arr = array();
			$record_mod_arr = array();

			$dbs = $details["db_list"];

			// Raw listed information to Array
			foreach ($dbs as $db) {
				$db_url_arr[] = HEURIST_BASE_URL . "?db=" . $db;

				$row = $this->records[HEURIST_DB_PREFIX.$db];

				$record_count_arr[] = $row[0];
				$record_mod_arr[] = $row[1];
			}

			// Array to Readable List
			$db_listed = $this->createListFromArray($dbs);
			$db_url_listed = $this->createListFromArray($db_url_arr);
			$records_listed = $this->createListFromArray($record_count_arr);
			$lastmod_listed = $this->createListFromArray($record_mod_arr);

			// Construct email and request
			$replace_with = array($details["first_name"], $details["last_name"], $email, $db_listed, $db_url_listed, $records_listed, $lastmod_listed);

			$body = str_ireplace($this->substitute_vals, $replace_with, $this->email_body);
			$title = (isset($this->email_subject) ? $this->email_subject : "Heurist system email for databases: " . $db_url_listed);

			$full_email = $title . "\n\n" . $body;

			$request = "request: {".$this->rec_count.", ".$this->rec_lastmod_period.", ".$this->rec_lastmod_unit.", ".$this->rec_lastmod_logic."}";

			// Add row
			fputcsv($fd, array($email, $name, implode(",", $dbs), implode(",", $db_url_arr), implode(",", $record_count_arr), implode(",", $record_mod_arr), $request, $full_email));
		}

        // Close descriptor and exit
		fclose($fd);
        exit();
	}

	/*
	 * Converts php Array into English list
	 * 
	 * Param: $array => array to convert
	 *
	 * Return: Array() => converted array, or empty string
	 */

	public function createListFromArray($array) {

		if (is_array($array) && count($array) >= 1) {
			return implode(" | ", $array);
		} else {
			return $array;
		}
	}

	/*
	 * Error Message and Log functions:
	 *	
	 *	set_error() => set the value of error_msg to msg
	 *
	 *	get_error() => return the value of error_msg
	 *	get_log() => return the value of log
	 *	get_error_log() => return array containing both the values of error_msg and log
	 */

	public function set_error($msg) {
		$this->error_msg = $msg;
//error_log($msg);        
	}

	public function get_error() {
		return $this->error_msg;
	}
	public function get_log() {
		return $this->log;
	}
	public function get_error_log() {
		return array($this->error_msg, $this->log);
	}

	/*
	 * Receipt functions:
	 *
	 *	save_receipt() => prepare receipt value
	 *		Param:
	 *			$status => (int) 0 || < 0, whether the emails were all sent
	 *			$email_subject => (string) email subject used
	 *			$email_body => (string) email body used
	 *			$user_count => (int) count of users who have been emailed
	 *
	 *	get_receipt() => get the value of receipt
	 *
	 *	export_receipt() => save receipt value into Note record, Titled: System Email Receipt [Current Date]
	 */

	private function save_receipt($status, $email_subject, $email_body, $user_count = 0) {

		$max_size = 1024 * 64; // 64 KBytes
		$max_chars = $max_size / 4 - 1;	// Max Characters, allow roughly 4 bytes per character (for encoded/special chars)

		$db = implode(", ", $this->databases);
		$db_list = str_replace(HEURIST_DB_PREFIX, "", $db);
		$u = $this->users;

		$u_cnt = count($this->user_details);

		$r_cnt = $this->rec_count;

		$lm = $this->rec_lastmod_logic . " " . $this->rec_lastmod_period . " " . $this->rec_lastmod_unit;

		$status_msg = $status==0 ? "Success" : "Failed, Error Message: " . $this->get_error();

		$main = "Parameters: {<br/>"
			   . "&nbsp;&nbsp;Databases: $db_list <br/>"
			   . "&nbsp;&nbsp;User Type: $u <br/>"
			   . "&nbsp;&nbsp;Number of Users to Email: $u_cnt <br/>"
			   . "&nbsp;&nbsp;Number of Users Emailed: $user_count <br/>"
			   . "&nbsp;&nbsp;Record Limit:$r_cnt <br/>"
			   . "&nbsp;&nbsp;Last Modified Filter: $lm <br/>"
			   . "}, <br/> Timestamp: " . date("Y-m-d H:i:s") . ", Status: " . $status_msg
			   . ", <br/> Email Subject: " . $email_subject
			   . ", <br/> Email Body: <br/>" . $email_body;
	    $main_size = strlen($main);	// Main part in bytes

		$user_list = "Users: {<br/>";
		foreach ($this->user_details as $email => $details) {
			$user_list .= "&nbsp;&nbsp;". $details["first_name"] ." ". $details["last_name"] .": ". $email ."<br/>";
		}
		$user_list .= "}";
		$user_list_size = strlen($user_list); // User List part in bytes

		// Check if Main and User List parts can be placed together or in different blocktext fields
		if ($main_size+$user_list_size > $max_size) { // Save the text in chucks

			$this->receipt = array();

			if ($main_size < $max_size) {
				$this->receipt[] = $main;
			} else { // Save this part in chunks

				$main_t = mb_convert_encoding($main, "UTF-8", "auto");
				/*$main_s = mb_strlen($main_t);
				while ($main_s) {
					$this->receipt[] = mb_substr($main_t, 0, $max_chars, "UTF-8"); // Get chunk
					
					$main_t = mb_substr($main_t, $max_chars, $main_s, "UTF-8"); // Remove chunk from main string
					
					$main_s = mb_strlen($main_t); // Get length of new string
				}*/

				if ($main_t) {
					$start = 0;
					while ($start < mb_strlen($main_t)) {
						$this->receipt[] = mb_substr($main_t, $start, $max_chars);
						$start += $max_chars;
					}
				}

			}
			if ($user_list_size < $max_size) {
				$this->receipt[] = $user_list;
			} else { // Save this part in chunks

				$user_list_t = mb_convert_encoding($user_list, "UTF-8", "auto");
				/*$user_list_s = mb_strlen($user_list_t);
				while ($user_list_s) {
					$this->receipt[] = mb_substr($user_list_t, 0, $max_chars, "UTF-8"); // Get chunk
					
					$user_list_t = mb_substr($user_list_t, $max_chars, $user_list_s, "UTF-8"); // Remove chunk from main string
					
					$user_list_s = mb_strlen($user_list_t); // Get length of new string
				}*/

				if ($user_list_t) {
					$start = 0;
					while ($start < mb_strlen($user_list_t)) {
						$this->receipt[] = mb_substr($user_list_t, $start, $max_chars);
						$start += $max_chars;
					}
				}

			}

		} else { // Save together
			$this->receipt = $main . "<br/>" . $user_list;
		}
	}
	private function get_receipt() {
		return $this->receipt;
	}
	public function export_receipt() {
		
		global $system;
		$mysqli = $system->get_mysqli();

		// Get IDs
		$note_rectype_id = ConceptCode::getRecTypeLocalID("2-3");
		$title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
		$summary_detailtype_id = ConceptCode::getDetailTypeLocalID("2-3");
		$date_detailtype_id = ConceptCode::getDetailTypeLocalID("2-9");

		if (empty($note_rectype_id) || empty($title_detailtype_id) || empty($summary_detailtype_id) || empty($date_detailtype_id)) { // ensure all are valid

			$this->set_error("Unable to retrieve the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.<br>The Heurist team has been notified.");
			$system->addError(HEURIST_ERROR, "Bulk Email System Error: Unable to get the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.");
			return -1;
		}

		if (isset($this->receipt) && !empty($this->receipt)) {

			// Save receipt to note record
			$data = recordAdd($system, array("RecTypeID"=>$note_rectype_id), true);
			if (!empty($data["data"]) && is_numeric($data["data"])) {

				$rec_id = $data["data"];

				$details = array($title_detailtype_id=>"Heurist System Email Receipt", $date_detailtype_id=>"today", $summary_detailtype_id=>$this->get_receipt(), "rec_ID"=>$rec_id);

				// Proceed with saving
				$rtn = recordSave($system, array("ID"=>$rec_id, "RecTypeID"=>$note_rectype_id, "details"=>$details));

				if ($rtn["status"] === HEURIST_OK && $rtn["data"] == $rec_id) {

					return $rtn;
				} else {

					$this->set_error("An error has occurred with adding the new Notes record for the receipt, Error => " . print_r($system->getError(), TRUE));
					return -1;
				}
			} else {

				$this->set_error("Unable to create Note record for receipt, Error => " . $data["message"]);
				$system->addError(HEURIST_ERROR, "Bulk Email System: Unable to create Note record for receipt, Error => " . $data["message"]);
				return -1;
			}
		}

		return 0;
	}
}

/*
 * Prepare and Send Emails using the supplied details
 * 
 * Param: $data => Form Data
 *
 * Return: VOID || Error Message
 */

function sendSystemEmail($data) {

	$rtn_value = 0;
	$email_obj = new systemEmailExt();

	$rtn_value = $email_obj->processFormData($data);
	
	if ($rtn_value == 0) {

		$rtn_value = $email_obj->constructEmails();

		if ($rtn_value <= -1) {

			echo "An error occurred with preparing and sending the system emails<br/>";
			$output = $email_obj->get_error_log();
			echo $output[0];
			echo "<br/><br/>System Log: <br/>" . $output[1];

			$rtn_value = -1;
		}

		// create note record with that will contain the contents of log
		$rtn_value = $email_obj->export_receipt();

		return $rtn_value;
	} else {

		echo "An error occurred with processing the form's data<br/>";
		$output = $email_obj->get_error();
		print $output;
		return -1;
	}
}

/*
 * Export Selected data as CSV
 * 
 * Param: $data => Form Data
 *
 * Return: VOID || Error Message
 */

function getCSVDownload($data) {

	$rtn_value = 0;
	$csv_obj = new systemEmailExt();

	$rtn_value = $csv_obj->processFormData($data);
	
	if ($rtn_value == 0) {

		$rtn_value = $csv_obj->exportDetailsToCSV();

		if ($rtn_value <= -1) {

			echo "An error occurred with exporting the selected data as a CSV file<br/>";
			$output = $csv_obj->get_error();
			print $output[0];
			return -1;
		}

	} else {

		echo "An error occurred with processing the form's data<br/>";
		$output = $csv_obj->get_error();
		print $output;
		return -1;
	}	
}

?>