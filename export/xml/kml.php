<?php

/*
* Copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Returns kml for given record id. It searches detail with type 221 or 551
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     HeuristWebService
* @subpackage  Export
* @todo        Only one KML per record is currently handled; consider returning a combination if multiple KML sources exist for a record.
* @todo        Review and potentially replace `mysql__select_row` and `mysql__select_value` with more modern equivalents if available in Heurist core.
*
* @uses $_REQUEST['db'] Database name for system initialization.
* @uses $_REQUEST['id'] Record ID for fetching a single KML (either an uploaded KML file or a KML snippet).
* @uses $_REQUEST['q'] Query string for fetching a list of records to convert to KML placemarks or network links.
* @uses $_REQUEST['file'] If `1`, sets Content-Disposition to download the KML file.
* @uses $_REQUEST['limit'] Limits the number of records processed in list mode.
* @uses geoPHP For converting WKT geometry to KML.
* @uses hserv\System For Heurist system initialization and database access.
* @uses hserv\utilities\USanitize For path sanitization.
* @uses hserv\utilities\Temporal For handling date/time to KML TimeSpan/TimeStamp.
* @uses recordSearch() For fetching record IDs based on a query.
*
* @const KML_CLOSE Defines the closing tags for a KML document.
* @const XML_HEADER Defines the XML declaration header (expected Heurist constant).
* @const HEURIST_SCRATCHSPACE_DIR Path to the scratch space for temporary files.
* @const HEURIST_BASE_URL Base URL of the Heurist instance.
* @const DT_FILE_RESOURCE Detail Type ID for generic file resources.
* @const DT_KML_FILE Detail Type ID for uploaded KML files.
* @const DT_KML Detail Type ID for KML snippets stored directly in details.
* @const DT_DATE Detail Type ID for simple date fields.
* @const DT_START_DATE Detail Type ID for start date fields (for time spans).
* @const DT_END_DATE Detail Type ID for end date fields (for time spans).
*/
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;

// Required Heurist files
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php'; // For recordSearch()
require_once dirname(__FILE__).'/../../vendor/autoload.php'; // For geoPHP library

// Initialize Heurist system
$system = new hserv\System();
if (!$system->init(@$_REQUEST['db'])) {
    // REMARK: Changed die() to a more graceful exit for potential programmatic use.
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Cannot connect to database.";
    error_log("kml.php: Cannot connect to database specified by 'db' parameter: " . @$_REQUEST['db']);
    exit;
}

/**
 * Defines the closing tags for a KML document.
 * @var string
 */
define('KML_CLOSE', '</Document></kml>');

// Determine if the script should output a list of placemarks/network links based on a query,
// or a single KML content.
$islist = array_key_exists("q", $_REQUEST);

// Set HTTP headers for KML output
if (@$_REQUEST['file'] == 1 || @$_REQUEST['file'] === true) { // Suggest filename for download if 'file=1'
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=\"heurist_export.kml\""); // Generic filename
}
header("Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8"); // Correct KML MIME type
// REMARK: Changed Content-Type from text/xml to the official KML MIME type.

// Define Detail Type ID constants for geographic and date fields.
// These rely on constants being defined in Heurist's core (via $system->defineConstant).
$dtFile = ($system->defineConstant('DT_FILE_RESOURCE') ? DT_FILE_RESOURCE : 0);
$dtKMLfile = ($system->defineConstant('DT_KML_FILE') ? DT_KML_FILE : 0);
$dtKML = ($system->defineConstant('DT_KML') ? DT_KML : 0);
$dtDate = ($system->defineConstant('DT_DATE') ? DT_DATE : 0);
$dtDateStart = ($system->defineConstant('DT_START_DATE') ? DT_START_DATE : 0);
$dtDateEnd = ($system->defineConstant('DT_END_DATE') ? DT_END_DATE : 0);

$mysqli = $system->getMysqli();

// --- Mode 1: Output KML for a single record ID ---
// This mode is triggered if 'q' (query for list) is NOT set.
// It attempts to find an uploaded KML file or a KML snippet associated with the given 'id'.
if (!$islist) {
    // Check if 'id' parameter is provided and not empty.
    if (array_key_exists("id", $_REQUEST) && $_REQUEST["id"] != "") {
        $record_id = intval($_REQUEST["id"]); // Sanitize record ID.
        $kml_content_to_output = null;

        // Attempt to find an uploaded KML file first.
        // It checks for generic files (DT_FILE_RESOURCE) and specific KML files (DT_KML_FILE)
        // and ensures the extension is 'kml'.
        // REMARK: Assumes mysql__select_row is a Heurist utility function.
        $kml_file_details = mysql__select_row(
            $mysqli,
            'SELECT ulf_FilePath, ulf_FileName FROM recDetails ' .
            'LEFT JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID WHERE dtl_RecID = ' .
            $record_id . " AND (dtl_DetailTypeID = " .
            $dtFile . " OR dtl_DetailTypeID = " . $dtKMLfile . ") AND ulf_MimeExt = 'kml'"
        );

        if ($kml_file_details && !empty($kml_file_details[0]) && !empty($kml_file_details[1])) {
            // Construct full path to the KML file.
            // resolveFilePath is an assumed Heurist utility to get the full server path.
            $path = USanitize::sanitizePath(resolveFilePath($kml_file_details[0]));
            $kml_filename_on_disk = basename($kml_file_details[1]);
            $full_kml_path = $path . $kml_filename_on_disk;

            if (file_exists($full_kml_path)) {
                $kml_content_to_output = file_get_contents($full_kml_path);
            }
        } else {
            // If no uploaded KML file, look for a KML snippet stored directly in record details (DT_KML).
            // REMARK: Assumes mysql__select_value is a Heurist utility function.
            $kml_snippet = mysql__select_value(
                $mysqli,
                "SELECT dtl_Value FROM recDetails WHERE dtl_RecID = " .
                $record_id . " AND dtl_DetailTypeID = " . $dtKML
            );

            if ($kml_snippet != null) {
                // Wrap the KML snippet in a full KML document structure for validity.
                // XML_HEADER is an assumed Heurist global constant (e.g., "<?xml version=\"1.0\" encoding=\"UTF-8\"?>").
                $kml_content_to_output = (defined('XML_HEADER') ? XML_HEADER : "<?xml version=\"1.0\" encoding=\"UTF-8\"?>") . "\n";
                $kml_content_to_output .= '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom"><Document>';
                $kml_content_to_output .= $kml_snippet; // The KML snippet from database
                $kml_content_to_output .= KML_CLOSE; // Append standard KML closing tags
            }
        }

        // Output the determined KML content or an empty/error KML document.
        if ($kml_content_to_output) {
            echo $kml_content_to_output;
        } else {
            // Fallback: Output an empty KML document indicating no content was found.
            echo (defined('XML_HEADER') ? XML_HEADER : "<?xml version=\"1.0\" encoding=\"UTF-8\"?>") . "\n";
            echo '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>No KML content found for record ' . $record_id . '</name></Document></kml>';
        }
    } else {
        // If 'id' parameter is missing for single KML mode.
        echo (defined('XML_HEADER') ? XML_HEADER : "<?xml version=\"1.0\" encoding=\"UTF-8\"?>") . "\n";
        echo '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>Error: Record ID not specified.</name></Document></kml>';
    }
    exit; // Terminate script after handling single KML mode.
}

// --- Mode 2: Output KML for a list of records (based on 'q' parameter) ---
// This mode generates KML Placemarks from WKT data found in records,
// and KML NetworkLinks for records that have associated KML files.

// Create a temporary file to build the KML document. This helps manage memory for large exports.
$kml_temp_file_path = tempnam(HEURIST_SCRATCHSPACE_DIR, "kml_export_"); // Added prefix for clarity
if ($kml_temp_file_path === false) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Could not create temporary file for KML export.";
    error_log("kml.php: Failed to create temporary file in " . HEURIST_SCRATCHSPACE_DIR);
    exit;
}
$kml_file_stream = fopen($kml_temp_file_path, 'w');
if ($kml_file_stream === false) { // Check if fopen failed
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: Could not open temporary file for KML export.";
    error_log("kml.php: Failed to open temporary file: " . $kml_temp_file_path);
    @unlink($kml_temp_file_path); // Attempt to delete if created by tempnam but not opened
    exit;
}


// Write KML header and document start tags.
fwrite($kml_file_stream, (defined('XML_HEADER') ? XML_HEADER : "<?xml version=\"1.0\" encoding=\"UTF-8\"?>") . "\n");
fwrite($kml_file_stream, '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">');
fwrite($kml_file_stream, '<Document>');
fwrite($kml_file_stream, '<name>Exported from Heurist</name>');
// Basic style for placemarks (can be customized or expanded)
fwrite($kml_file_stream, '<Style id="h-placemark-style"><IconStyle><Icon><href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href></Icon></IconStyle></Style>');


// Base SQL query parts for fetching WKT (Well-Known Text) geographic data and associated dates.
$squery_wkt_select = "SELECT rec_ID, rec_URL, rec_Title, d0.dtl_DetailTypeID, d0.dtl_Value, IF(d0.dtl_Geo IS NULL, NULL, ST_asWKT(d0.dtl_Geo)) AS dtl_Geo, " .
    "d1.dtl_Value AS Date0, d2.dtl_Value AS DateStart, d3.dtl_Value AS DateEnd ";
$squery_wkt_from = " LEFT JOIN recDetails d1 ON d1.dtl_RecID=rec_ID AND d1.dtl_DetailTypeID=" . intval($dtDate) .
    " LEFT JOIN recDetails d2 ON d2.dtl_RecID=rec_ID AND d2.dtl_DetailTypeID=" . intval($dtDateStart) .
    " LEFT JOIN recDetails d3 ON d3.dtl_RecID=rec_ID AND d3.dtl_DetailTypeID=" . intval($dtDateEnd) .
    ", recDetails d0"; // d0 is the primary recDetails table for geo data.
$squery_wkt_where_extra = " AND (d0.dtl_RecID=rec_ID) AND (d0.dtl_Geo IS NOT NULL " . ($dtKML > 0 ? " OR d0.dtl_DetailTypeID=" . intval($dtKML) : "") . ")";

// Base SQL query parts for fetching records linked to KML files.
$squery_kmlfile_select = "SELECT rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName ";
$squery_kmlfile_from = ", recDetails LEFT JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID";
$squery_kmlfile_where_extra = " AND (dtl_RecID=rec_ID) AND (dtl_DetailTypeID=" . intval($dtKMLfile) .
    ($dtFile > 0 ? " OR (dtl_DetailTypeID = " . intval($dtFile) . " AND ulf_MimeExt='kml')" : ")");

$isSearchKmlFiles = ($dtKMLfile > 0 || $dtFile > 0); // Flag indicating if KML file linking is active.

// Fetch record IDs based on the query parameter 'q'.
$_REQUEST['detail'] = 'ids'; // We only need record IDs from recordSearch.
$search_result = recordSearch($system, $_REQUEST); // recordSearch is an external Heurist function.

// Handle cases where no records are found or a search error occurs.
if (!(@$search_result['status'] == HEURIST_OK && @$search_result['data']['reccount'] > 0)) {
    $error_info = $system->getError();
    $error_message = !empty($error_info) && isset($error_info[0]['message']) ? $error_info[0]['message'] : 'No records found or error in search.';
    fwrite($kml_file_stream, '<description>' . htmlspecialchars($error_message) . '</description>');
    fwrite($kml_file_stream, KML_CLOSE);
    fclose($kml_file_stream);
    echo file_get_contents($kml_temp_file_path);
    @unlink($kml_temp_file_path);
    exit;
}

$result_data = $search_result['data'];
$rec_ids = $result_data['records'];

// Apply limit to the number of records processed, if specified.
$limit = intval(@$_REQUEST['limit']);
if ($limit > 0) {
    $rec_ids = array_slice($rec_ids, 0, $limit);
}

$squery_wkt = ""; // Initialize query string for WKT data.
$squery_kml_files = ""; // Initialize query string for KML file links.

// Compose full SQL queries if there are record IDs to process.
if (!empty($rec_ids)) {
    $squery_wkt = _composeQuery($squery_wkt_select, $squery_wkt_from, $rec_ids, $squery_wkt_where_extra);
    if ($isSearchKmlFiles) {
        $squery_kml_files = _composeQuery($squery_kmlfile_select, $squery_kmlfile_from, $rec_ids, $squery_kmlfile_where_extra);
    }
}

$wkt_reccount = 0;
$kml_files_reccount = 0;
$res_wkt = null;
$res_kml_files = null;

// Execute query for WKT data.
if (!empty($squery_wkt)) {
    $res_wkt = $mysqli->query($squery_wkt);
    if ($res_wkt === false) {
        error_log("KML Export: WKT Query Failed: " . $mysqli->error . " | Query: " . $squery_wkt);
    } else {
        $wkt_reccount = $res_wkt->num_rows;
    }
}

// Execute query for KML file links.
if ($isSearchKmlFiles && !empty($squery_kml_files)) {
    $res_kml_files = $mysqli->query($squery_kml_files);
    if ($res_kml_files === false) {
        error_log("KML Export: KML Files Query Failed: " . $mysqli->error . " | Query: " . $squery_kml_files);
    } else {
        $kml_files_reccount = $res_kml_files->num_rows;
    }
}

// Process WKT results into KML Placemarks.
if ($wkt_reccount > 0 && $res_wkt) {
    while ($row = $res_wkt->fetch_row()) { // Using fetch_row due to numeric indexing used below.
        $kml_geometry = null;
        // $row[0]=rec_ID, $row[1]=rec_URL, $row[2]=rec_Title
        // $row[3]=dtl_DetailTypeID, $row[4]=dtl_Value (KML snippet), $row[5]=dtl_Geo (WKT)
        // $row[6]=Date0, $row[7]=DateStart, $row[8]=DateEnd

        if ($row[5]) { // If WKT dtl_Geo data exists.
            try {
                $geom = geoPHP::load($row[5], 'wkt'); // Load WKT using geoPHP.
                $kml_geometry = $geom->out('kml');   // Convert to KML geometry string.
            } catch (Exception $e) {
                error_log("geoPHP Exception for rec_ID " . $row[0] . ": " . $e->getMessage());
                $kml_geometry = null;
            }
        } elseif ($row[3] == $dtKML && $row[4]) { // Else, if it's a KML snippet stored in dtl_Value.
             $kml_geometry = $row[4]; // The KML snippet itself.
        }

        if ($kml_geometry) { // If valid KML geometry was obtained.
            fwrite($kml_file_stream, '<Placemark>');
            fwrite($kml_file_stream, '<name>' . htmlspecialchars($row[2]) . '</name>');
            fwrite($kml_file_stream, '<styleUrl>#h-placemark-style</styleUrl>'); // Apply basic style.

            // Create a description with a link back to the Heurist record.
            if ($row[1]) { // Use rec_URL if available.
                $url = htmlentities($row[1]);
                fwrite($kml_file_stream, '<description><![CDATA[<a href="' . $url . '">View in Heurist</a>]]></description>');
            } else { // Fallback to a direct link using rec_ID.
                 fwrite($kml_file_stream, '<description><![CDATA[<a href="' . HEURIST_BASE_URL . '?db=' . htmlspecialchars($_REQUEST['db']) . '&recID=' . $row[0] . '">View in Heurist</a>]]></description>');
            }

            // Add TimeSpan or TimeStamp based on available date fields.
            $temporal_kml = '';
            if ($row[7] || $row[8]) { // DateStart or DateEnd for TimeSpan.
                $date_start_str = $row[7] ?: $row[8]; // Use end as start if start is missing.
                $date_end_str = $row[8] ?: $row[7];   // Use start as end if end is missing.
                $dt_span = Temporal::mergeTemporals($date_start_str, $date_end_str);
                if ($dt_span && $dt_span->isValid()) { $temporal_kml = $dt_span->toKML(); }
            } elseif ($row[6]) { // Single Date0 for TimeStamp.
                $dt_single = new Temporal($row[6]);
                if ($dt_single && $dt_single->isValid()) { $temporal_kml = $dt_single->toKML(); }
            }
            if ($temporal_kml) {
                fwrite($kml_file_stream, $temporal_kml);
            }

            // Add the KML geometry (Point, LineString, Polygon, etc.).
            fwrite($kml_file_stream, $kml_geometry);
            fwrite($kml_file_stream, '</Placemark>');
        }
    }
    if ($res_wkt) $res_wkt->close();
}

// Process records linked to KML files into KML NetworkLinks.
if ($kml_files_reccount > 0 && $res_kml_files) {
    while ($file_data = $res_kml_files->fetch_row()) {
        // $file_data[0]=rec_ID, $file_data[1]=rec_URL, $file_data[2]=rec_Title
        // $file_data[3]=ulf_ID, $file_data[4]=ulf_FilePath, $file_data[5]=ulf_FileName
        if ($file_data[3]) { // Check if ulf_ID (uploaded file ID) exists.
            $record_id_for_link = intval($file_data[0]);
            fwrite($kml_file_stream, '<NetworkLink>');
            fwrite($kml_file_stream, '<name>' . htmlspecialchars($file_data[2]) . ' (KML File)</name>');
            fwrite($kml_file_stream, '<Link>');
            // Link to this script (kml.php) in single record mode to output the content of the linked KML file.
            fwrite($kml_file_stream, '<href>' . htmlspecialchars(HEURIST_BASE_URL . 'export/xml/kml.php?db=' . $_REQUEST['db'] . '&id=' . $record_id_for_link) . '</href>');
            fwrite($kml_file_stream, '</Link>');
            fwrite($kml_file_stream, '</NetworkLink>');
        }
    }
    if ($res_kml_files) $res_kml_files->close();
}

// Close KML document and the temporary file stream.
fwrite($kml_file_stream, KML_CLOSE);
fclose($kml_file_stream);

// Output the content of the temporary KML file to the client and then delete the temp file.
if (file_exists($kml_temp_file_path)) {
    readfile($kml_temp_file_path); // Use readfile for efficiency with larger files.
    @unlink($kml_temp_file_path); // Suppress error if unlink fails for some reason.
}


/**
 * Composes a SQL query string.
 *
 * @param string $select The SELECT part of the query.
 * @param string $from The FROM part of the query, including initial JOINs.
 * @param int|list<int> $rec_ids A single record ID or an array of record IDs to filter by `rec_ID`.
 * @param string $where Additional WHERE clause conditions to append.
 * @return string The fully composed SQL query string.
 */
function _composeQuery($select, $from, $rec_ids, $where) {


/**
 * Composes a SQL query string.
 *
 * @param string $select The SELECT part of the query.
 * @param string $from The FROM part of the query, including initial JOINs.
 * @param int|list<int> $rec_ids A single record ID or an array of record IDs to filter by `rec_ID`.
 * @param string $where Additional WHERE clause conditions to append.
 * @return string The fully composed SQL query string.
 */
function _composeQuery($select, $from, $rec_ids, $where) {

   if(is_array($rec_ids)){
        $where_ids = 'in ('.implode(',', prepareIds($rec_ids)).')';
   }else{
        $where_ids = '='.intval($rec_ids);
   }

   $squery = "$select from Records $from WHERE rec_ID $where_ids $where";

   return $squery;
}
?>