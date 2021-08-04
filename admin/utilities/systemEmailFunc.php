<?php 

/**
*  Related class 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
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

	public $databases; // list of selected DBs

	public $users; // user options => owner: DB Owners, admin: Workgroup Admins, user: All Users 
	public $workgroups; // workgroup options => 1: DB Managers, 3: Other users, 4: Website filters, ...

	private $user_details; // list of user details and databases, indexed on user emails

	public $email_body_id; // record id for note record containing the email body

	public $rec_count; // number of records to include, default: 0
	
	public $rec_lastmod_period;	// time period, default: 6
	public $rec_lastmod_unit; // unit of time, default: MONTH
	public $rec_lastmod_logic; // logic, default: <=

	private $records; // array of records+last modified information

	private $log; // log of emails, to be placed within a note record within the databases, extended version of receipt
	private $receipt; // receipt for all email transactions, is saved into current db as a note record with the Note's title (not rec_Title) set to "System Email Receipt"  
	private $error_msg; // error message

	private $user_options = array("owner", "admin", "users"); // available user options
	private $substitute_vals = array("##firstname##", "##lastname##", "##email##", "##database##", "##dburl##", "##records##", "##lastmodified##"); // available email body substitutions

	/*
	 * Process the data received
	 *
	 * Param: $data => Passed Form Data
	 *
	 * Return: VOID || Error Code
	 */

	public function processFormData($data) {

		$rtn = 0;

		if (!isset($data["db"])) { // A current database is needed
			$this->set_error("processFormData() Error: The current databases has not been set, data[db] => " . $data["db"]);
			return -1;
		}

		if (isset($data["databases"]) && is_array($data["databases"]) && count($data["databases"]) >= 1) { // Get list of Databases
			$this->databases = $data["databases"];
		} else {

			$this->set_error("processFormData() Error: No databases or invalid databases have been provided, data[databases] => " . $data["databases"]);
			return -1;
		}
		
		if (isset($data["users"]) && in_array($data["users"], $this->user_options)) { // Get list of Users
			$this->users = $data["users"];
		} else {

			$this->set_error("processFormData() Error: No users or invalid users have been provided, data[users] => " . $data["users"]);
			return -1;
		}

		$this->workgroups = @$data["workgroups"]; // Get list of Workgroups

		if (!empty($this->workgroups)) { 

			if (is_array($this->workgroups)) {

				$this->workgroups = array_filter($this->workgroups, function($val) {
					return is_string($val);
				});

			} else if (!is_string($this->workgroups)) {
				
				$this->set_error("processFormData() Error: Provided workgroups are invalid, workgroups => " . implode(",", $this->workgroups));
				return -1;
			}
		}

		if (isset($data["emailOutline"]) && is_numeric($data["emailOutline"])) { // Get Note record ID, ensure it is a number
			$this->email_body_id = $data["emailOutline"];
		} else {

			$this->set_error("processFormData() Error: No note id or an invalid one has been provided, data[emailOutline] => " . $data["emailOutline"]);
			return -1;
		}

		// Get record filtering options, use defaults if none supplied

		$this->rec_count = (isset($data["recTotal"]) && is_numeric($data["recTotal"]) && $data["recTotal"] >= 0) ? $data["recTotal"] : 0;

		$this->rec_lastmod_period = (isset($data["recModVal"]) && is_numeric($data["recModVal"]) && $data["recModVal"] >= 0) ? $data["recModVal"] : 6;
		$this->rec_lastmod_unit = isset($data["recModInt"]) ? $data["recModInt"] : "MONTH";
		$this->rec_lastmod_logic = isset($data["recModLogic"]) ? $data["recModLogic"] : "<=";

		// Initialise other variables

		$this->user_details = array();

		$this->log = "";
		$this->receipt = "";
		$this->error_msg = "";

		$rtn = $this->createRecordsList(); // save record counts and last modified record dates for each db

		if ($rtn != 0) {
			return $rtn;
		}

		$rtn = $this->createUserList(); // save user information; first and last name, email, and list of databases

		if ($rtn != 0) {
			return $rtn;
		}

		return 0;
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
		$groups = @$this->workgroups;

		$wg_count = 0;

		if ($users == "admin") { // validate workgroups

			if (!empty($groups)) {
				if (is_array($groups)) {

					$groups = array_filter($groups, function($val) {
						return is_string($val) && $val !== "Everybody";
					});
					$wg_count = count($groups);

				} else if (is_string($groups) && $val !== "Everybody") {
					$wg_count = 1;
				} else {

		   			$this->set_error("createUserList() Error: No valid Work Groups were provided, groups => " . implode(",", $groups) . ", count => " . $wg_count);
		   			return -1;
		   		}
		   	} else {

		   		$this->set_error("createUserList() Error: Workgroups are required for emailing administrator users");
		   		return -1;
		   	}
		}

		foreach ($dbs as $db){

			$where_clause = "";

			// Create WHERE clause
			if ($users == "owner") {
				$where_clause = "WHERE ugr.ugr_ID = 2";
			} else if ($users == "admin") {

				if ($wg_count >= 1) {

					$where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugl.ugl_GroupID IN 
							  		 (SELECT ugr_ID 
	 						   		  FROM " . $db . ".sysUGrps 
	 						   		  WHERE ugr_Name IN ('" . implode("','", $groups) . "') AND ugr_Type = 'workgroup')";

		   		} else {

		   			$this->set_error("createUserList() Error: No valid Work Groups were provided, groups => " . implode(",", $groups) . ", count => " . $wg_count);
		   			return -1;
		   		}
			
			} else if ($users == "user") {
				$where_clause = "WHERE ugr.ugr_Type = 'user'";
			}

			// Execute WHERE Clause
			if (empty($where_clause)) {

				$this->error_msg = "createUserList() Error: Unable to construct WHERE clause for User List query, users => " . $users;
				return -1;
			} else {

				$query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail 
						  FROM " . $db . ".sysUsrGrpLinks AS ugl  
						  INNER JOIN " . $db . ".sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
						. $where_clause;

				$res = $mysqli->query($query);
				if (!$res) {

					$this->set_error("createUserList() Error: Unable to complete query for list of users, MySQLi Error => " .$mysqli->error. ", Query => " .$query);  
					return -2;
				}

				while ($row = $res->fetch_row()) {

					$db_name = substr($db,strlen(HEURIST_DB_PREFIX));

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

		$record_count = $this->rec_count;

		$lastmod_period = $this->rec_lastmod_period;
		$lastmod_unit = $this->rec_lastmod_unit;
		$lastmod_logic = $this->rec_lastmod_logic;

		// Create Last modified's WHERE Clause
		$lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic . " date_format(curdate(), '%Y-%m-%d') - INTERVAL " . $lastmod_period . " " . $lastmod_unit . " " : "";

		foreach ($dbs as $db) {

			$count = 0;
			$date = "unknown";

			if ($record_count > 0) { // Check if a record count was requested
				$query = "SELECT COUNT(*)
						  FROM (
						   SELECT *
						   FROM " . $db . ".Records AS rec
						   WHERE rec_Title IS NOT NULL 
						   AND rec_Title != '' " . $lastmod_where .
						  "LIMIT " . $record_count . "
						  ) AS a";

			  	$res = $mysqli->query($query);
			  	if (!$res) { 

			  		$this->set_error("createRecordsList() Error: Unable to complete query for record count, MySQLi Error => " .$mysqli->error. ", Query => " .$query); 
			  		return -2;
			  	}

			  	if ($row = $res->fetch_row()) {
			  		$count = $row[0];
			  	}

			  	$res->close();
			}

			// Get newest record/last edited record, ignore system email receipt records
		  	$query = "SELECT rec_Modified
					  FROM " . $db . ".Records AS rec
					  WHERE rec_Title IS NOT NULL 
					  AND rec_Title != '' " . $lastmod_where .
					 "AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
					  ORDER BY rec_Modified DESC
					  LIMIT 1";

			$res = $mysqli->query($query);
		  	if (!$res) { 

		  		$this->set_error("createRecordsList() Error: Unable to complete query for last modified record, MySQLi Error => " .$mysqli->error. ", Query => " .$query); 
		  		return -2;
		  	}

		  	if ($row = $res->fetch_row()) { 
		  		$date_obj = new DateTime($row[0]);
		  		$date = $date_obj->format("Y-m-d");
		  	}

		  	$this->records[$db] = array($count, $date); // save results
		}

		return 0;
	}

	/*
	 * Retrieve the note record to be used as the email's body
	 *
	 * Param: None
	 *
	 * Return:
	 *	String => Email Body
	 *	, or Error Code
	 */

	private function getEmailBody($id=null) {

		global $system;
		$mysqli = $system->get_mysqli();

		if (empty($id) || intval($id) <= 0) { // validate id

			if (empty($this->email_body_id) || intval($this->email_body_id) <= 0) {

				$this->set_error("getEmailBody() Error: Provided record ID is invalid for retrieving the email body from a Heurist note record");
				return -1;
			} else {
				$id = $this->email_body_id;
			}
		}

		// Get note record
		$query = "SELECT dtl_Value
				  FROM recDetails
				  WHERE dtl_RecID = " . $id . " AND dtl_DetailTypeID = 
				  (SELECT dty_ID
				   FROM defDetailTypes
				   WHERE dty_OriginatingDBID = 2 AND dty_IDInOriginatingDB = 3)";

		$res = $mysqli->query($query);
		if (!$res) {  

			$this->set_error("getEmailBody() Error: Unable to complete query for email body (note record within the current Heurist DB), MySQLi Error => " .$mysqli->error. ", Query => " .$query);  
			return -2;
		}

		if ($row = $res->fetch_row()){
			return htmlspecialchars($row[0], ENT_COMPAT, "UTF-8");
		} else {

			$this->set_error("getEmailBody() Error: No Note record found of ID => " . $id . ", Query => " . $query);
			return -1;
		}
	}	

	/*
	 * Prepare email body for sending
	 * 
	 * Param: None
	 *
	 * Return: VOID || Error Code
	 */

	public function constructEmails() {

		$email_rtn = 0;

		// Get email body
		$email_body = $this->getEmailBody();
		if ($email_body <= -1) {

			return $email_body;
		}

		// Initialise PHPMailer
		$mailer = new PHPMailer(true);
		$mailer->CharSet = "UTF-8";
		$mailer->Encoding = "base64";
        $mailer->isHTML(true);
        $mailer->SetFrom(HEURIST_MAIL_TO_ADMIN, 'Heurist System Email');

		foreach ($this->user_details as $email => $details) {

			$email_rtn = 0;

			$db_url_arr = array();
			
			$records_arr = array();
			$lastmod_arr = array();

			$db_listed;
			$db_url_listed;

			$dbs = $details["db_list"];

			// Convert information into arrays and get DB urls
			foreach ($dbs as $db) {
				$db_url_arr[] = HEURIST_BASE_URL . "?db=" . $db;

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

			$body = str_ireplace($this->substitute_vals, $replace_with, $email_body);

			$title = "Heurist system email for databases: " . $db_url_listed;

			if (!empty($body)) { // Check if email body is valid, then send email

				$mailer->Subject = $title;
				$mailer->Body = $body;
				$mailer->AddAddress( $email );

				try {
				    $mailer->send();
				} catch (phpmailerException $e) {
				    $this->set_error($e->errorMessage());
				    $email_rtn = -3;
				} catch (Exception $e) {
				    $this->set_error($e->getMessage());
				    $email_rtn = -3;
				}

				$status_msg = "";
				if ($email_rtn == 0) {
					$status_msg = "Sent, Sent Message: " . $body;
				} else {
					$status_msg = "Failed, Error Message: " . $this->get_error();
				}

				$this->log .= "Values: {databases: {".$db_listed."}, email: $email, name: " .$details['first_name']. " " .$details["last_name"]
						   .", record_count: {".$records_listed."}, last_modified: {".$lastmod_listed."} },"
						   ."Timestamp: " . date("Y-m-d H:i:s") . ", Status: " . $status_msg
						   . "<br/><br/>";
			} else {

				$this->set_error("constructEmails() Error: Unable to proceed as the email body is empty, Email Body Outline => " . $email_body);
				return -1;
			}

			if ($email_rtn != 0) {

				if ($email_rtn == -3){
					$this->set_error("constructEmails() Error: Email sending has stopped due to an error with the email system, Error => " . $this->error_msg);
				}

				$this->save_receipt($email_rtn, $email_body);

				return $email_rtn;
			}

			$email->clearAddresses(); // ensure that the current email is gone
		}

		$this->save_receipt($email_rtn, $email_body);

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

		$email_body = $this->getEmailBody();

		// Create and Save information in temporary file
		$fd = fopen('php://temp/maxmemory:1048576', 'w');

		$filename = "System_Email_Export.csv";

		if ($fd == null) {

			$this->set_error("exportDetailsToCSV() Error: Unable to open temporary files for CSV exporting");
			return -4;
		}

		fputcsv($fd, array("User Email", "User Name", "Databases", "Database URLs", "Record Counts", "Last Modified Record", "request: {record_limit, lastmodification_period, lastmodification_unit, lastmodification_logic}, Email")); //, "Email"

		foreach ($this->user_details as $email => $details) {
			
			$name = $details["first_name"] . " " . $details["last_name"];

			$db_url_arr = array();
			$record_count_arr = array();
			$record_mod_arr = array();

			$dbs = $details["db_list"];

			foreach ($dbs as $db) {
				$db_url_arr[] = HEURIST_BASE_URL . "?db=" . $db;

				$row = $this->records[HEURIST_DB_PREFIX.$db];

				$record_count_arr[] = $row[0];
				$record_mod_arr[] = $row[1];
			}

			// Then transform from array into readable list
			$db_listed = $this->createListFromArray($dbs);
			$db_url_listed = $this->createListFromArray($db_url_arr);
			$records_listed = $this->createListFromArray($record_count_arr);
			$lastmod_listed = $this->createListFromArray($record_mod_arr);

			// Replace placeholders with information
			$replace_with = array($details["first_name"], $details["last_name"], $email, $db_listed, $db_url_listed, $records_listed, $lastmod_listed);

			$body = str_ireplace($this->substitute_vals, $replace_with, $email_body);

			$request = "request: {".$this->rec_count.", ".$this->rec_lastmod_period.", ".$this->rec_lastmod_unit.", ".$this->rec_lastmod_logic."}";

			fputcsv($fd, array($email, $name, implode(",", $dbs), implode(",", $db_url_arr), implode(",", $record_count_arr), implode(",", $record_mod_arr), $request, $body));
		}

		rewind($fd);
		$content = stream_get_contents($fd);
		fclose($fd);

		// Send information back in php request
		$content_length = strlen($content);
		if ($content_length < 0) {
			$content_length = 0;
		}

		if (!isset($content) || $content_length == 0) {

			$this->set_error("exportDetailsToCSV() Error: No data is available for CSV exporting");
			return -1;
		}

		$content_length = $content_length + 3;

		header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header('Content-Length: ' . $content_length);

        echo "\xEF\xBB\xBF";	// UTF-8 Byte Order Mark

        exit($content);
	}

	/*
	 * Converts php Array into English list
	 * 
	 * Param: $array => array to convert
	 *
	 * Return: Array() => converted array, or empty string
	 */

	protected function createListFromArray($array) {

		if (is_array($array)) {
			$last_item = array_pop($array);

			if (empty($last_item) && !is_numeric($last_item)) {
				return '';
			}

			if ($array) {
				return implode(", ", $array) . " and " . $last_item;
			} else {
				return $last_item;
			}
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
	 *			$email_body => (string) email template used
	 *
	 *	get_receipt() => get the value of receipt
	 *
	 *	export_receipt() => save receipt value into Note record, Titled: System Email Receipt [Current Date]
	 */

	private function save_receipt($status, $email_body) {

		$db = implode(", ", $this->databases);
		$db_list = str_replace("hdb_", "", $db);
		$u = $this->users;
		$wg_list = implode(", ", $this->workgroups);
		
		$note_id = $this->email_body_id;

		$r_cnt = $this->rec_count;

		$lm = $this->rec_lastmod_logic . " " . $this->rec_lastmod_period . " " . $this->rec_lastmod_unit;

		$status_msg = $status==0 ? "Success" : "Failed, Error Message: " . $this->get_error();

		$this->receipt = "Parameters: {<br/>"
					   . "&nbsp;&nbsp;Databases: $db_list <br/>"
					   . "&nbsp;&nbsp;User Type: $u <br/>"
					   . "&nbsp;&nbsp;Workgroups: $wg_list <br/>"
					   . "&nbsp;&nbsp;Note Rec ID: $note_id <br/>"
					   . "&nbsp;&nbsp;Record Limit:$r_cnt <br/>"
					   . "&nbsp;&nbsp;Last Modified Filter: $lm <br/>"
					   . "}, <br/> Timestamp: " . date("Y-m-d H:i:s") . ", Status: " . $status_msg
					   . ", <br/> Email Template: <br/>" . $email_body;
	}
	public function get_receipt() {
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

			$this->set_error("export_receipt() Error: Unable to retrieve needed details type for the Note record type");
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

					return 0;
				} else {

					$this->set_error("export_receipt() Error: " . print_r($system->getError(), TRUE));
					return -1;
				}
			} else {

				$this->set_error("export_receipt() Error: Unable to create Note record for receipt, " . $data["message"]);
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
		$email_obj->export_receipt();

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