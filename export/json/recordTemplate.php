<?php
<?php
/**
 * recordTemplate.php: Exports record structure templates in JSON format.
 *
 * This script generates a JSON template representing the structure of specified
 * record types from a Heurist database. If the 'rectype_ids' request parameter
 * is not provided, it includes 'record_output.php' to attempt an export of
 * actual records instead.
 *
 * REMARK: A significant behavior of this script is that it first prints a large
 * block of instructional help text directly to the output, followed by the JSON data.
 * This help text is intended for users generating the template and includes a warning
 * "REMOVE THIS HELP WHEN IMPORTING!!". Programmatic consumers of this script's output
 * will need to account for and potentially strip this preamble.
 *
 * The generated JSON includes:
 *  - A "help" section with guidance on placeholder values (TRM_ID, DATE, etc.).
 *  - An array of "records", where each element is a template for a specific record type.
 *  - Placeholders for file fields, geo fields, and record references.
 *  - Database details including its registered ID, name, URL, and a list of the
 *    record types included in the template.
 *
 * The output is served as a downloadable JSON file.
 *
 * @package     HeuristWebService
 * @subpackage  Export
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network Ltd.
 * @author      Brandon McKay     <blmckay13@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     7
 *
 * @uses $_REQUEST['rectype_ids'] Comma-separated list of record type IDs to include, or 'y'/'all' for all types.
 *                                If not present, script diverts to 'record_output.php'.
 * @uses $_REQUEST['db'] The name of the database to connect to.
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\structure\ConceptCode;

// If rectype_ids is not specified, this script's purpose changes to exporting actual records.
if (!array_key_exists('rectype_ids', $_REQUEST)) {
    // REMARK: This diverts execution to a different script for actual record export.
    // The rest of this script (template generation) will not run.
    require_once dirname(__FILE__).'/../../hserv/controller/record_output.php';
    exit;
}

// Standard Heurist includes
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php'; // For dbs_GetRectypeStructures
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php'; // For recordTemplateByRecTypeID

// Initialize Heurist system if not already done (e.g., if PDIR is not defined)
if (!defined('PDIR')) {
    $system = new hserv\System();
    if (!$system->init(filter_var(@$_REQUEST['db']))) {
        // REMARK: Changed die() to a more standard error output for API-like scripts.
        // Consider JSON error output if this script is ever purely programmatic.
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error: Cannot connect to database specified by 'db' parameter.";
        exit;
    }
}

// --- Prepare Record Type Information ---
// Get record type names and structures
$rst = dbs_GetRectypeStructures($system, null, 0); // 0 = only names and groupnames
$rty_names = $rst['names'];

// Determine which record type IDs to process
if (@$_REQUEST['rectype_ids'] == 'y' || @$_REQUEST['rectype_ids'] == 'all') { // 'y' or 'all' means all record types
    $rectype_ids = array_keys($rty_names);
} else {
    // prepareIds function is expected to be globally available or via autoload.
    // It likely cleans and splits a comma-separated string of IDs.
    $rectype_ids = prepareIds(filter_var(@$_REQUEST['rectype_ids']));
}

// --- Output Preamble Help Text ---
// REMARK: This extensive help text is printed directly to the output stream BEFORE the JSON content.
// This is highly unconventional for a JSON export and means the output is not pure JSON.
// Consumers of this script must be aware of this preamble.
print "REMOVE THIS HELP WHEN IMPORTING!!\nFor preparing an JSON file
with Heurist schema which can be imported into a Heurist database.
\n
The file must indicate a source database in database=>id.
This is added automatically when JSON or this template is exported
from a registered Heurist database (it is set to 0 if the
database is not registered).
\n
In the case of data from a non-Heurist source, the file should
indicate a database which contains definitions for all the
record types and fields to be imported. This can either be
the database from which this template is exported or zero or the
target database if all the necessary record types and fields
exist in the target (either by being imported into it or through
cloning from a suitable source). If definitions are missing Heurist
will update the target database structure from the indicated source
(if specified). If required definitions cannot be obtained, it will
report an error indicating the missing definitions.
\n
Values to be replaced are indicated with ALLCAPS, such as
WKT_VALUE (WellKnownText), NUMERIC, TRM_ID, DATE etc.
\n
RECORD_REFERENCE may be replaced with a numeric or alphanumeric
reference to another record, indicated by the id field.
Note that this reference will be replaced with an automatically
generated numeric Heurist record ID (H-ID), which will be different
from the reference supplied. The reference supplied will be recorded
in a field Original ID.
\n
If you wish to specify existing Heurist records in the target
database as the target (value) of a Record Pointer field, specify
their Heurist record ID (H-ID) in the form H-ID-nnnn, where nnnn
is the H-ID of the target record in the target database. Specifying
non-existent record IDs will throw an error. The record type of
target records are not checked on import; pointers to records of
the wrong type can be found later with Admin > Verify integrity.
\n
If you use an H-ID-nnnn format specification in the <id> tag of
a record, it will be regarded as an unknown alphanumeric identifier
and will simply create a new record with a new H-ID.
\n
\"URL\" This record level tag specifies a special URL
attached directly to the record which is used to hyperlink
record lists and for which checking can be automated.
Primarily used for internet bookmarks.
\n
Specify date field values in ISO format (yyyy or yyyy-mm or yyyy-mm-dd)
\n
termID= specifies any of the following, which are evaluated
in order: local ID, concept code, label or standard code.
If no match is found, the value will be added as a new term
\n
Relationship markers: these are indicated by \"RELATIONSHIP_RECORD\" in
the value. Relationship markers are special fields as they contain no data;
they are simply a marker in the data structure indicating the display
of relationship records which satisfy particular criteria (relationship type
and target entity type). They also trigger the creation of relationships
with particular parameters during data entry.
\n
Relationships should therefore be imported by importing as records of
type RELATIONSHIP. They will appear in the marker fields when the data is
viewed. Leave at least one copy of each relationship marker field in the
file as this will trigger download of the field definition if it is not in
the target database. Only one copy of each relationship marker is needed to
trigger the download of the definition, duplicates can be deleted if there
is a need to limit file size.
\n
The JSON file may (optionally) specify a Heurist database ID
with database=>id. If a database ID is specified, synchronisation
of definitions from that database will be performed before the data
are imported. Since imported files will normally use a template for
record types and fields exported from the target database, this is
only useful for synchronising vocabularies and terms.\n\n";

// This $import_help string is embedded as a JSON object within the main JSON output.
$import_help = "{"
. "\n \t\t\"TRM_ID\": \"Specifies any of the following, which are evaluated in order: local ID, concept code, label or standard code. If no match is found, the value will be added as a new term\","
. "\n \t\t\"DATE\": \"Specify date field values in ISO format (yyyy or yyyy-mm or yyyy-mm-dd)\","
. "\n \t\t\"RECORD_REFERENCE\": \"May be replaced with a numeric or alphanumeric reference to another record. Note that this reference will be replaced with an automatically generated numeric Heurist record ID (H-ID), and the reference supplied will be recorded in a field Original ID.\","
. "\n \t\t\"RELATIONSHIP_RECORD\": \"Special fields that contain no data; instead new records of type RELATIONSHIP should be imported. They will appear in the marker fields when the data is viewed.\","
. "\n \t\t\"RECORD-IDENTIFIER\": \"Specify the record identifier in the source database (numeric or alphanumeric) if the record could be the target of a record pointer field, including the target record pointer of a relationship record.\""
. "\n \t}";

// --- Start JSON Output Construction ---
$json = "{\"heurist\":{\n \t\"help\": ". $import_help .",\n \t\"records\":[";

// Define templates for specific complex field types
// Template for a file field
$file_field = '{"file": {"ulf_ExternalFileReference": "FILE_OR_URL", "fxm_MimeType": "TEXT", "ulf_Description": "MEMO_TEXT", "ulf_OrigFileName": "TEXT"}}';
// Template for a geographic (WKT) field
$geo_field = '{"geo": {"wkt": "WKT_VALUE"}}';

$sep = ''; // Separator for JSON array elements (initially empty)
$rectypes_info_json = ''; // JSON string for record type details to be included in the "database" section

// --- Loop Through Each Record Type ID to Generate its Template ---
foreach ($rectype_ids as $rty_id) {
    // Get the specific record type structure template
    // recordTemplateByRecTypeID is from hserv/records/search/recordSearch.php
    $rectype_structure = recordTemplateByRecTypeID($system, $rty_id);
    $rec_template_json_segment = '';

    if (!array_key_exists('error', $rectype_structure)) {
        // Start building the JSON for this record type template
        $rec_template_json_segment = $sep . "\n \t\t{\"rec_ID\": \"RECORD-IDENTIFIER\", \"rec_RecTypeID\": ". $rectype_structure['rec_RecTypeID'] .", \"rec_URL\": \"URL\", \"rec_ScratchPad\": \"MEMO_TEXT\", \"details\": [";

        $fld_sep = ''; // Separator for detail fields

        // Iterate over the detail fields of the record type
        foreach ($rectype_structure['details'] as $dty_id => $details_group) { // $dt is dty_ID (detail type ID)
            foreach ($details_group as $value_template) {
                // Handle different types of field value templates
                if (is_array($value_template)) {
                    if (array_key_exists('file', $value_template)) { // File field
                        $value_json = $file_field;
                    } elseif (array_key_exists('geo', $value_template)) { // Geo field
                        $value_json = $geo_field;
                    } elseif (array_key_exists('id', $value_template) && strpos($value_template['id'], 'RECORD_REFERENCE') === 0) { // Record pointer field
                        // REMARK: RTY_ID placeholder should be replaced with the actual target record type ID if known, or a generic placeholder.
                        $value_json = '{"id": "RECORD_REFERENCE", "type": "RTY_ID", "title": "TEXT"}';
                    } else { // Other array values (should be simple arrays if any, or this might need more specific handling)
                        $value_json = '"' . json_encode($value_template) . '"'; // Generic JSON encoding for other array types
                    }
                } elseif (strpos($value_template, 'VALUE') !== false) { // Standard value field, placeholder for Term ID
                    $value_json = '"TRM_ID"';
                } elseif (strpos($value_template, 'SEE NOTES AT START') !== false) { // Relationship marker field
                    $value_json = '"RELATIONSHIP_RECORD"';
                } else { // Other simple string placeholder values (e.g., TEXT, NUMERIC)
                    $value_json = '"' . $value_template . '"';
                }

                $rec_template_json_segment .= $fld_sep . "\n \t\t\t{\"dty_ID\":" . $dty_id . ", \"value\":" . $value_json . "}";
                $fld_sep = ','; // Use comma for subsequent fields
            }
        }
        $rec_template_json_segment .= "\n \t\t]}"; // Close "details" array and record object

        // Prepare information for the "rectypes" list in the "database" section
        $rty_conceptID = ConceptCode::getRecTypeConceptID($rty_id);
        $rectypes_info_json .= $sep ."\n \t\t\t\"". $rty_id ."\": {\n \t\t\t\t\"name\": \"". htmlspecialchars($rty_names[$rty_id]) ."\",\n \t\t\t\t\"code\": \"". htmlspecialchars($rty_conceptID) ."\",\n \t\t\t\t\"count\": 1\n \t\t\t}";
        // REMARK: "count": 1 is a placeholder, actual count might vary in real data.
    } else { // Error fetching record type structure
        $rec_template_json_segment = $sep. "\n \t\t{\"" . $rty_id . "\": \"" . htmlspecialchars($rectype_structure['error']) . "\"}]}";
        // REMARK: This error structure might break JSON validity if not handled carefully by the loop structure.
        // However, the current loop structure seems to append this as one of the record templates.
    }

    $json .= $rec_template_json_segment; // Append this record type's template to the main JSON string
    $sep = ','; // Use comma for subsequent record type templates
}

$json .= "\n \t],"; // Close "records" array

// --- Add Database Details to JSON ---
$db_details_json = "\n \t\"database\":{"
. "\n \t\t\"id\": \"". htmlspecialchars($system->settings->get('sys_dbRegisteredID')) ."\"," // Registered ID of the source database
. "\n \t\t\"db\": \"". htmlspecialchars($system->dbname()) ."\"," // Name of the source database
. "\n \t\t\"url\": \"". htmlspecialchars(HEURIST_BASE_URL) ."\"," // Base URL of the Heurist instance
. "\n \t\t\"rectypes\": {". $rectypes_info_json ."\n \t\t}" // Information about included record types
. "\n \t}";
$json .= $db_details_json;

// --- Finalize JSON Output ---
$json .= "\n}}"; // Close "heurist" object

// --- Set Headers and Output JSON ---
$filename = 'Template_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $_REQUEST['db']) . '_' . date("YmdHis") . '.json'; // Sanitize DB name for filename

header('Content-Type: application/json; charset=utf-8'); // Specify JSON content type and charset
header('Content-Disposition: attachment; filename="'.$filename.'";'); // Suggest filename for download

echo $json; // Output the complete JSON string (which is preceded by the help text)
