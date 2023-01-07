<?php
/**
* record_template.php: Exports record structure templates in JSON format, 
*	or calls record_output.php to export actual records (if rectype_ids is not provided)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
* @author      Brandon McKay     <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');

if(!array_key_exists('rectype_ids', $_REQUEST)){
    require_once(dirname(__FILE__).'/../../hsapi/controller/record_output.php'); // attempt to export actual records
    exit();
}

if(!defined('PDIR')){
    $system = new System();
    if( !$system->init(@$_REQUEST['db']) ){
        die("Cannot connect to database");
    }
}

$rectype_ids = $_REQUEST['rectype_ids'];

if(!is_array($rectype_ids)){
    $rectype_ids = explode(',', $rectype_ids);
}

// Record type names
$rst = dbs_GetRectypeStructures($system, null, 2);
$rty_names = $rst['names'];

$import_help = "{"
    . "\n \t\t\"TRM_ID\": \"Specifies any of the following, which are evaluated in order: local ID, concept code, label or standard code. If no match is found, the value will be added as a new term\","
    . "\n \t\t\"DATE\": \"Specify date field values in ISO format (yyyy or yyyy-mm or yyyy-mm-dd)\","
    . "\n \t\t\"RECORD_REFERENCE\": \"May be replaced with a numeric or alphanumeric reference to another record. Note that this reference will be replaced with an automatically generated numeric Heurist record ID (H-ID), and the reference supplied will be recorded in a field Original ID.\","
    . "\n \t\t\"RELATIONSHIP_RECORD\": \"Special fields that contain no data; instead new records of type RELATIONSHIP should be imported. They will appear in the marker fields when the data is viewed.\""
    . "\n \t}";

// START OUTPUT
$fd = fopen('php://output', 'w');

fwrite($fd, "{\"heurist\":{\n \t\"help\": ". $import_help .",\n \t\"records\":["); // starting string

// RECORD STRUCTURES
$file_field = '{"file": {"ulf_ExternalFileReference": "FILE_OR_URL", "fxm_MimeType": "TEXT", "ulf_Description": "MEMO_TEXT", "ulf_OrigFileName": "TEXT"}}';
$geo_field = '{"geo": {"wkt": "WKT_VALUE"}}';

$sep = '';
$rectypes = '';
foreach ($rectype_ids as $rty_id) {

    $rectype_structure = recordTemplateByRecTypeID($system, $rty_id); // db_recsearch.php
    $rec_templates = '';

    if(!array_key_exists('error', $rectype_structure)){

        // Add record fields
        $rec_templates = $sep . "\n \t\t{\"rec_ID\": \"RECORD-IDENTIFIER\", \"rec_RecTypeID\": ". $rectype_structure['rec_RecTypeID'] .", \"rec_URL\": \"URL\", \"rec_ScratchPad\": \"MEMO_TEXT\", \"details\": [";
        $dtl_output = '';
        $fld_sep = '';

        // Add record detail fields
        foreach ($rectype_structure['details'] as $dt => $details) {

            foreach ($details as $value) {
                if(array_key_exists('file', $value)){ // file field
                    $value = $file_field;
                }else if(array_key_exists('geo', $value)){ // geo field
                    $value = $geo_field;
                }else if(array_key_exists('id', $value) && $value['id'] == 'RECORD_REFERENCE'){
                    $value = '{"id": "RECORD_REFERENCE", "type": "RTY_ID", "title": "TEXT"}';
                }else if($value == 'VALUE'){
                    $value = '"TRM_ID"';
                }else if($value == 'SEE NOTES AT START'){
                    $value = '"RELATIONSHIP_RECORD"';
                }else{
                    $value = '"' . $value . '"';
                }

                $rec_templates .= $fld_sep . "\n \t\t\t{\"dty_ID\":" . $dt . ", \"value\":" . $value . "}";
                $fld_sep = ',';
            }
        }
        $rec_templates .= "\n \t\t]}";

        // Prepare rectypes list
        $rty_conceptID = ConceptCode::getRecTypeConceptID($rty_id);

        $rectypes .= $sep ."\n \t\t\t\"". $rty_id ."\": {\n \t\t\t\t\"name\": \"". $rty_names[$rty_id] ."\",\n \t\t\t\t\"id\": \"". $rty_conceptID ."\",\n \t\t\t\t\"count\": 1\n \t\t\t}";

    }else{
        $rec_templates = $sep. "\n \t\t{\"" . $rty_id . "\": \"" . $rectype_structure['error'] . "\"}]}";
    }

    fwrite($fd, $rec_templates); // add template to file

    $sep = ',';
}

fwrite($fd, "\n \t],"); // end of record templates

// Add database details
$db_details = "\n \t\"database\":{"
    . "\n \t\t\"id\": \"". $system->get_system('sys_dbRegisteredID') ."\","
    . "\n \t\t\"db\": \"". $system->dbname() ."\","
    . "\n \t\t\"url\": \"". HEURIST_BASE_URL ."\","
    . "\n \t\t\"rectypes\": {". $rectypes ."\n \t\t}"
    . "\n \t}";
fwrite($fd, $db_details);

// Close off
fwrite($fd, "\n}}");

$filename = 'Template_' . $_REQUEST['db'] . '_' . date("YmdHis") . '.json';

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="'.$filename.'";');
header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

exit();
?>