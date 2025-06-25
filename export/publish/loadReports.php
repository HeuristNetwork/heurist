<?php
/**
* loadReports.php: AJAX endpoint for managing scheduled reports.
*
* This script handles CRUD-like operations for records in the `usrReportSchedule` database table.
* It expects a 'method' parameter in the request to determine the action to perform.
* 
* Supported methods:
*  - 'searchreports': Searches for scheduled reports based on provided criteria (name).
*  - 'getreport': Retrieves a specific scheduled report by its ID.
*  - 'savereport': Saves (inserts or updates) one or more scheduled report records.
*  - 'deletereport': Deletes a specific scheduled report by its ID.
*
* All responses are in JSON format. Access to this script requires the user to be logged in.
* 
* @todo use entity\DbUsrReportSchedule
*
* @package     Heurist academic knowledge management system
* @subpackage  export\publish
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.27
*
* @global array $sys_usrReportSchedule_ColumnNames Maps column names from `usrReportSchedule` table to their data types (i, s, etc.) for prepared statements.
*
* @uses $_REQUEST['db'] Database name for system initialization.
* @uses $_REQUEST['method'] The operation to perform (e.g., 'searchreports', 'getreport').
* @uses $_REQUEST['recID'] Record ID, used by 'getreport', 'savereport' (for update), 'deletereport'.
* @uses $_REQUEST['name'] Name to search for, used by 'searchreports'.
* @uses $_REQUEST['usrID'] User ID (currently noted as @todo), potentially for 'searchreports'.
* @uses $_REQUEST['data'] Data payload for 'savereport', expected to be an array with 'report' -> 'colNames' and 'defs'.
* @uses isEmptyArray() Utility function to check if an array is empty.
* @uses mysql__select_value() Heurist utility function for selecting a single value.
* @uses mysql__exec_param_query() Heurist utility function for executing parameterized queries.
*/
require_once dirname(__FILE__).'/../../autoload.php';

// Initialize Heurist system
$system = new hserv\System();
if (!$system->init(@$_REQUEST['db'])) {
    $system->errorExit('Database initialization failed.');
}

// Check user access
if (!$system->hasAccess()) {
   $system->errorExit('To perform this action you must be logged in', HEURIST_REQUEST_DENIED);
}

// Set content type to JSON for all responses
header('Content-Type: application/json; charset=utf-8');

/**
 * Global array mapping usrReportSchedule column names to their prepared statement data types.
 * 'i' for integer, 's' for string.
 * @var array
 */
global $sys_usrReportSchedule_ColumnNames; // Declared global, defined below.

$sys_usrReportSchedule_ColumnNames = array(
    "rps_ID" => "i",
    "rps_Type" => "s",
    "rps_Title" => "s",
    "rps_FilePath" => "s",
    "rps_URL" => "s",
    "rps_FileName" => "s",
    "rps_HQuery" => "s",
    "rps_Template" => "s",
    "rps_IntervalMinutes" => "i"
);

$method = @$_REQUEST['method'];
$mysqli = $system->getMysqli();

// --- Main Method Dispatcher ---
if ($method == "searchreports") {
    // Handles searching for scheduled reports.
    // Expects optional 'name' parameter for filtering by title.
    
    $f_name = $mysqli->real_escape_string(filter_var(@$_REQUEST['name'], FILTER_SANITIZE_STRING));
    // $f_userid = @$_REQUEST['usrID']; // not currently used in query.

    $records = array();
    // Base query to select all report schedule entries
    $query = "SELECT rps_ID, rps_Type, rps_Title, rps_FilePath, rps_URL, rps_FileName, rps_HQuery, rps_Template, rps_IntervalMinutes, 0 AS selection, 0 AS status FROM usrReportSchedule";

    if ($f_name && $f_name != "") {
        $query .= " WHERE rps_Title LIKE '%".$f_name."%'"; // Append name filter if provided
    }

    $res = $mysqli->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['status'] = getStatus($row); // Determine status of the report (e.g., file existence)
            $records[] = $row;
        }
        $res->close();
    } else {
        // Basic error handling for query failure.
        $system->errorExit('Error executing searchreports query: ' . $mysqli->error);
    }

    $response = array("status" => HEURIST_OK, "data" => $records);
    echo json_encode($response);

} elseif ($method == "getreport") {
    // Handles fetching a single scheduled report by its ID.
    // Expects 'recID' parameter.
    $recID = @$_REQUEST['recID'];
    if ($recID == null) {
          $system->errorExit('Invalid call to loadReports (getreport), recID is required');
    }

    $colNames = array("rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes");
    $records = array();
    $records['fieldNames'] = $colNames;
    $records['records'] = array(); // Data will be keyed by rps_ID

    $query = "SELECT ".implode(",", $colNames)." FROM usrReportSchedule ";

    if (isPositiveInt($recID)) { // Fetch specific record if recID is valid
        $query .= " WHERE rps_ID=".intval($recID);
        $res = $mysqli->query($query);
        if ($res) {
            while ($row = $res->fetch_row()) { // fetch_row as data is numerically indexed for this structure
                $records['records'][$row[0]] = $row; // Key by rps_ID (first column)
            }
            $res->close();
        } else {
            $system->errorExit('Error executing getreport query: ' . $mysqli->error);
        }
    }
    // If recID is not > 0, it returns empty 'records' array, which is acceptable.

    $response = array("status" => HEURIST_OK, "data" => $records);
    echo json_encode($response);

} elseif ($method == "savereport") {
    // Handles saving (inserting or updating) scheduled report(s).
    // Expects 'data' parameter containing report definitions.
    $data  = @$_REQUEST['data'];

    // Validate incoming data structure
    if (!is_array($data) ||
        !array_key_exists('report', $data) || !is_array($data['report']) ||
        !array_key_exists('colNames', $data['report']) ||
        !array_key_exists('defs', $data['report'])) {
          $system->errorExit('Invalid data structure sent with savereport method call to loadReports.php');
    }

    $colNames = $data['report']['colNames'];
    $results = array(); // Stores results of each update/insert operation

    foreach ($data['report']['defs'] as $recID_str => $rt_values) {
        $results[] = updateReportSchedule($mysqli, $colNames, intval($recID_str), $rt_values);
    }

    $response = array("status" => HEURIST_OK, "data" => $results);
    echo json_encode($response);

} elseif ($method == "deletereport") {
    // Handles deleting a scheduled report by its ID.
    // Expects 'recID' parameter.
    $recID  = @$_REQUEST['recID'];
    $result_data = array();
    if (!isPositiveInt($recID)) {
          $system->errorExit('Invalid or missing recID sent with deletereport method call to loadReports.php');
    } else {
        $result_data = deleteReportSchedule($mysqli, intval($recID));
        if (@$result_data['error']) {
            $system->errorExit($result_data['error'], HEURIST_ERROR);
        } else {
            $response = array("status" => HEURIST_OK, "data" => $result_data['result']);
            echo json_encode($response);
        }
    }
} else {
    // Handle invalid or missing method parameter
    $system->errorExit('Invalid or no method provided to loadReports.php');
}

exit; // Ensure script terminates after handling the request.

    /**
     * Checks the status of a scheduled report.
     * Status is determined by the existence of its Smarty template, output folder, and output file.
     *
     * @param array $row Associative array containing report schedule data (must include 'rps_Template', 'rps_FilePath', 'rps_FileName').
     * @return int Status code:
     *             0 - OK (all files/folders exist).
     *             1 - Template file missing.
     *             2 - Output folder does not exist.
     *             3 - Output file does not exist.
     *
     * @uses HEURIST_SMARTY_TEMPLATES_DIR Path to Smarty templates.
     * @uses HEURIST_FILESTORE_DIR Path to the Heurist filestore.
     */
    function getStatus($row)
    {
        // Check if the Smarty template file exists
        if (!file_exists(HEURIST_SMARTY_TEMPLATES_DIR.$row['rps_Template'])) {
            return 1; // Template file missed
        }

        // Determine the output directory path
        if ($row['rps_FilePath'] != null) {
            $dir = $row['rps_FilePath'];
            if (substr($dir, -1) != "/") {
                $dir = $dir."/";
            }
        } else {
            $dir = HEURIST_FILESTORE_DIR."generated-reports/"; // Default directory
        }

        // Check if the output directory exists
        if (!file_exists($dir)) {
            return 2; // Output folder does not exist
        }

        // Determine the output filename
        $filename = ($row['rps_FileName'] != null) ? $row['rps_FileName'] : $row['rps_Template'];
        $outputfile = $dir.$filename;

        // Ensure .html extension if none is provided
        $path_parts = pathinfo($outputfile);
        $ext = array_key_exists('extension', $path_parts) ? $path_parts['extension'] : null;
        if ($ext == null) {
            $outputfile = $outputfile.".html";
        }

        // Check if the output file exists
        if (!file_exists($outputfile)) {
            return 3; // Output file does not exist
        } else {
            return 0; // OK
        }
    }

    /**
     * Deletes a report schedule entry from the `usrReportSchedule` table.
     *
     * @param mysqli $mysqli The mysqli database connection object.
     * @param int $recID The ID of the report schedule record to delete.
     * @return array An associative array: `['result' => $recID]` on success, or `['error' => 'Error message']` on failure.
     */
    function deleteReportSchedule($mysqli, $recID)
    {
        $ret = array();
        $query = 'DELETE FROM usrReportSchedule WHERE rps_ID='.intval($recID); // Ensure recID is an integer
        $res = $mysqli->query($query);

        if ($mysqli->error) {
            $ret['error'] = 'Db error deleting record from report schedules: ' . $mysqli->error;
        } else {
            $ret['result'] = $recID;
        }
        return $ret;
    }

    /**
     * Updates or inserts a report schedule entry in the `usrReportSchedule` table.
     * Uses prepared statements for database interaction.
     *
     * @param mysqli $mysqli The mysqli database connection object.
     * @param array $colNames An array of column names to be updated or inserted.
     * @param int $recID The ID of the record to update. If $recID < 0, an insert operation is performed.
     * @param array $values An array of values corresponding to the $colNames.
     * @return int|string Returns the record ID (negative for new inserts, positive for updates) on success,
     *                    or an error message string on failure.
     *
     * @global array $sys_usrReportSchedule_ColumnNames Maps column names to their data types for binding parameters.
     * @uses isEmptyArray() Checks if the $colNames array is empty.
     * @uses mysql__select_value() Executes a query and returns a single value.
     * @uses mysql__exec_param_query() Executes a parameterized query.
     */
    function updateReportSchedule($mysqli, $colNames, $recID, $values)
    {
        global $sys_usrReportSchedule_ColumnNames;
        $ret = null;

        if (!isEmptyArray($colNames) && is_array($values)) {
            $isInsert = ($recID < 0);
            $query_parts = array(); // To build "col = ?" parts for UPDATE or "?" for INSERT
            $fieldNames_for_insert = array(); // For "INSERT INTO table (col1, col2)"
            $parameters = array(''); // First element for type string (e.g., "ssi")
            $rps_Title = '';

            foreach ($colNames as $colName) {
                $val = array_shift($values); // Get corresponding value

                if (array_key_exists($colName, $sys_usrReportSchedule_ColumnNames)) {
                    if ($isInsert) {
                        $fieldNames_for_insert[] = $colName;
                        $query_parts[] = "?";
                    } else {
                        $query_parts[] = "$colName = ?";
                    }
                    $parameters[0] .= $sys_usrReportSchedule_ColumnNames[$colName]; // Append data type
                    $parameters[] = $val; // Append value
                    if ($colName == 'rps_Title') {
                        $rps_Title = $val;
                    }
                }
            }

            if (!empty($query_parts)) {
                if ($isInsert) {
                    $query = "INSERT INTO usrReportSchedule (".implode(",", $fieldNames_for_insert).") VALUES (".implode(",", $query_parts).")";
                } else {
                    $query = "UPDATE usrReportSchedule SET ".implode(",", $query_parts)." WHERE rps_ID = ".intval($recID);
                }

                // Check for duplicate title before saving
                // Ensure $rps_Title is properly escaped for the check query, even though main query uses prepared statement.
                $check_query = 'SELECT rps_ID FROM usrReportSchedule WHERE rps_ID!='.intval($recID).' AND rps_Title="'.$mysqli->real_escape_string($rps_Title).'"';
                $rid = mysql__select_value($mysqli, $check_query);
                if ($rid > 0) {
                    $ret = 'Duplicate entry. There is already a report with the same name.';
                } else {
                    // Temporary ALTER TABLE logic from 2016-05-17. This should ideally be a one-time migration.
                    $res_struct = $mysqli->query("SHOW FIELDS FROM usrReportSchedule WHERE Field='rps_IntervalMinutes'");
                    if ($res_struct) {
                        $struct = $res_struct->fetch_assoc();
                        if ($struct && strpos($struct['Type'], 'tinyint') !== false) {
                            $mysqli->query('ALTER TABLE `usrReportSchedule` CHANGE COLUMN `rps_IntervalMinutes` `rps_IntervalMinutes` INT NULL DEFAULT NULL');
                        }
                        $res_struct->close();
                    }

                    $rows_affected = mysql__exec_param_query($mysqli, $query, $parameters, true);

                    if ($rows_affected === 0 && !$isInsert) { // For UPDATE, 0 rows affected might mean no change or record not found.
                        // To differentiate, one might check if the record exists first or if values are identical.
                        // For simplicity, we'll assume it's not an error if data was identical.
                        // If it's critical to know if it was "no change" vs "not found", more logic is needed.
                        $ret = $recID; // Assume success if no error and 0 rows affected on update (values might be same)
                    } elseif (is_string($rows_affected)) { // Error message returned
                        $oper = ($isInsert) ? "inserting" : "updating";
                        $ret = "Error $oper in updateReportSchedule - ".$rows_affected.' SQL: '.$query;
                    } elseif ($rows_affected > 0 || $rows_affected === 0) { // Success for INSERT (usually >0) or UPDATE (can be 0 if no change)
                        if ($isInsert) {
                            $ret = -$mysqli->insert_id; // Return negative new ID for insert
                        } else {
                            $ret = $recID; // Return existing ID for update
                        }
                    } else { // Should not happen if mysql__exec_param_query returns string for error, or int for rows.
                         $ret = "Unknown error or no rows affected in updateReportSchedule. Query: " . $query;
                    }
                }
            } else {
                $ret = "No valid columns provided for update/insert - $recID";
            }
        } else {
            $ret = "No data or column names supplied for updating report - $recID";
        }
        return $ret;
    }
?>