<?php

    /**
    * Import terms from CSV
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

define('MANAGER_REQUIRED',1);
define('PDIR','../../');    

require_once(dirname(__FILE__)."/../../hclient/framecontent/initPage.php");
require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_file.php');

$post_max_size = get_php_bytes('post_max_size');
$file_max_size = get_php_bytes('upload_max_filesize');
$format = @$_REQUEST['format'];
if(!$format) $format='csv';
?>
        <script type="text/javascript" src="importRecordsCSV.js"></script>

        <script type="text/javascript">

            var importRecordsCSV;
            // Callback function after initialization
            function onPageInit(success){
                if(success){
                    var max_size = Math.min(<?php echo $file_max_size;?>, <?php echo $post_max_size;?>);
                    importRecordsCSV = new hImportRecordsCSV(
                                window.hWin.HEURIST4.util.getUrlParameter('imp_ID', window.location.search), 
                                max_size, "<?php echo $format;?>");
                }
            }
        
        
        </script>
        <style type="text/css">
            .subh
            {
                border-left: 1px solid lightgray;
                border-bottom: 1px solid lightgray;
                border-top: 1px solid lightgray;
            }
            .tbpreview{
                font-size:0.9em;
                margin:10px;                
            }
            .tbpreview, .tbpreview td
            {
                border: 1px solid lightgray;
            }
            .tbpreview td{
                vertical-align:top;
            }
            .tbfields{
               font-size: 0.9em;
               border-collapse: collapse;
               text-align: left;
            }
            .tbfields td, .tbpreview th{
                padding: 2px;
            }
            .tbresults, .tbresults td{
               font-size: 1em;
               border:none;
            }
            .tbresults{
                border: 1px blue solid;
                background-color: lightblue;
                padding: 4px;
            }
            .tberror td{
                text-align:center;
            }
            .action_buttons{
               /*position: absolute;
               right:20px;
               top:2px;*/
            } 
            .action_buttons > div{
                margin-left:1em;
            }
            .select_rectype_seq{
                cursor: pointer;
                display: inline-block;
                padding:0.2em;
                margin-bottom:1em;
            }
            .select_rectype_seq > .hid_temp, .select_rectype_seq > .rt_arrow{
                display:none;    
            }
            .select_rectype_seq:hover > .hid_temp, .select_rectype_seq:hover > .rt_arrow{
                display:inline-block;    
            }
            h2{
                font-size:1.2em;
                margin:0;
            }
            .step-ctrls{
                padding-top:0.5em;
                margin-left:2em;
                position: absolute;
                top: 150px;
            }
        </style>
    </head>

    <!-- HTML -->
    <body class="ui-heurist-bg-light" style="overflow:hidden;min-height:400px;font-size:0.7em;">

<!-- STEP 1 upload data/select session -->    
<div style="width:100%; height:100%;" id="divStep1">
    <div class="ent_header" style="height:50%;padding:20px;">
    
        
        <div id="divCsvIntro" style="padding:0 20 20 20;min-width:1210" class="format-csv">
        
            <p style="margin:0">
Importing all but the simplest spreadsheet is a complex business. It is important to clean up the data as much as possible in advance, so that there is one row per entry and columns contain a single element of data (which may include repeated values, normally separated by pipe symbols | ). Split concatenated values into separate columns; place notes about data items in a separate column - don't append to the data value; coded columns should use a consistent set of codes. In addition to your spreadsheet program, you may find OpenRefine (<a href="http://openrefine.org" target="_blank">http://openrefine.org</a>) a useful tool for checking and correcting coded columns, splitting fields, georeferencing, finding URL references and so forth.
            </p><br>
            <p style="margin:0">
We strongly suggest editing the structure of the database to add any fields and terms that you will require for the import, before attempting to load the data. If you start trying to load data without the appropriate fields in place you will find it frustrating having to exit the process repeatedly to add fields.
            </p><br>
            <p style="margin:0">
If you have missing data for Required fields, you may find it convenient to set those fields to Optional before importing, then set them back to Required, then use Database > Verify Structure and Data to get a list of the records which need correcting. Alternatively you will need to add some dummy value to the data, such as 'Missing', and search for this value after import.            
            </p>        
        
        </div>      

        <div id="divKmlIntro" style="padding:0 20 20 20;min-width:1210;display:none">
<h4>This function imports a KML file into a set of map-enabled Heurist records</h4><br><br>
<p>Each spatial object in the KML file generates a Heurist record which also stores attached attributes.  You may select the type of record to be created in a following step.</p><br><br>
<p>A KML file can also be mapped directly by uploading it as a file attached to a record or storing it as a KML snippet in a text field within a record - use the KML record type for this purpose (standard in all databases). However, plotting KML in this way does not allow any additional information to be attached to the spatial objects, all you get is a single monolithic map layer.</<p><br><br>
<p><a href="<?php echo HEURIST_BASE_URL;?>context_help/kml_import.html">More info</a></p>
        </div>      
        
    
        <h2 style="display:inline-block;padding:5px;width:280px;text-align:right;" id="lblUploadFile">Upload new file (CSV/TSV)</h2>
            <input type="file" id="uploadFile" style="display:none">
            <div id="btnUploadFile" title="Browse for CSV/TSV file that contains your data to be imported into Heurist database">
                Upload File</div>
            <span class="format-csv">The first line must have field names with correct number of fields</span>  

        <h2 style="padding:10 0 10 120">OR</h2>
        
        <h2 style="display:inline-block;padding:5px;width:280px;text-align:right;">Select previously uploaded file</h2>
            <select id="selImportId" class="text ui-widget-content ui-corner-all" style="width:auto"></select>
            <a href="#" id="btnClearAllSessions"
                title="All uploaded files will be removed from the sytem. Start this action if you sure that you do not need any import data anymore"
                            style="margin-left: 10px;">Clear all files</a>        
            
        <h2 style="padding:10 0 10 120"  class="format-csv">OR</h2>
        <h2 style="display:inline-block;padding:5px;width:280px;text-align:right;"  class="format-csv">Paste delimited data in area below</h2>
        <div id="btnUploadData"  class="format-csv"
            title="Upload content of text area below to server side and use it as source for CSV/TSV import operation">Upload Data</div>
    </div>
    <div class="ent_content_full" style="top:50%;width:100%;">
        <textarea id="sourceContent" style="height:100%;width:100%;resize:none;" class="format-csv"></textarea>
    </div>
</div>
<!-- STEP 2 parse uploaded data -->
<div style="width:100%; height:100%;display:none;" id="divStep2" class="selectmenu-parent">
    <div class="ent_header" style="height:27em;padding-top:1em;">
    
        <div id="btnBackToStart2"
                style="margin-left:2em;"
                title="Return to the upload screen to select a new delimited file to upload to the server for processing">
                Back to start</div>
        <!--
        <h2 style="padding: 10px 0 10px 2em;">Define parse parameters</h2>
        -->
        <fieldset style="width:380px;">
                <div>
                    <div class="header" style="min-width: 50px;"><label>Encoding:</label></div>
                    <div class="input-cell">        
                        <select id="csv_encoding" class="text ui-widget-content ui-corner-all" style="width:120px;">
<option>UTF-8</option>
<option>UTF-16</option>
<option>UTF-16BE</option>
<option>UTF-16LE</option>
<option>CP1251</option>
<option>CP1252</option>
<option>KOI8-R</option>
<option>UCS-4</option>
<option>UCS-4BE</option>
<option>UCS-4LE</option>
<option>UCS-2</option>
<option>UCS-2BE</option>
<option>UCS-2LE</option>
<option>UTF-32</option>
<option>UTF-32BE</option>
<option>UTF-32LE</option>
<option>UTF-7</option>
<option>UTF7-IMAP</option>
<option>ASCII</option>
<option>EUC-JP</option>
<option>SJIS</option>
<option>eucJP-win</option>
<option>SJIS-win</option>
<option>ISO-2022-JP</option>
<option>ISO-2022-JP-MS</option>
<option>CP932</option>
<option>CP51932</option>
<option>MacJapanese</option>
<option>SJIS-DOCOMO</option>
<option>SJIS-KDDI</option>
<option>SJIS-SOFTBANK</option>
<option>UTF-8-DOCOMO</option>
<option>UTF-8-KDDI</option>
<option>UTF-8-SOFTBANK</option>
<option>ISO-2022-JP-KDDI</option>
<option>JIS</option>
<option>JIS-ms</option>
<option>CP50220</option>
<option>CP50220raw</option>
<option>CP50221</option>
<option>CP50222</option>
<option>ISO-8859-1</option>
<option>ISO-8859-2</option>
<option>ISO-8859-3</option>
<option>ISO-8859-4</option>
<option>ISO-8859-5</option>
<option>ISO-8859-6</option>
<option>ISO-8859-7</option>
<option>ISO-8859-8</option>
<option>ISO-8859-9</option>
<option>ISO-8859-10</option>
<option>ISO-8859-13</option>
<option>ISO-8859-14</option>
<option>ISO-8859-15</option>
<option>byte2be</option>
<option>byte2le</option>
<option>byte4be</option>
<option>byte4le</option>
<option>BASE64</option>
<option>HTML-ENTITIES</option>
<option>7bit</option>
<option>8bit</option>
<option>EUC-CN</option>
<option>CP936</option>
<option>GB18030</option>
<option>HZ</option>
<option>EUC-TW</option>
<option>CP950</option>
<option>BIG-5</option>
<option>EUC-KR</option>
<option>UHC</option>
<option>ISO-2022-KR</option>
<option>CP866</option>
                    </select>
                    </div>
                </div>
                <div>
                    <div class="header" style="min-width: 50px;"><label>Field separator:</label></div>
                    <div class="input-cell">  
                        <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;">
                                <option value="," selected>comma ,</option>
                                <option value="tab">tab</option>
                                <option value=";">semicolon ;</option>
                                <option value="|">pipe |</option>
                                <option value=":">colon :</option>
                                <option value="-">hyphen -</option>
                                <option value="=">equal sign =</option>
                                <option value="#">hash #</option>
                                <option value="$">dollar sign $</option>
                                <option value="@">at sign @</option>
                                <option value="&">ampersand &amp;</option>
                                <option value="*">asterisk *</option>
                                <option value="(">left parentheses (</option>
                                <option value=")">right parentheses )</option>
                                <option value="[">left bracket [</option>
                                <option value="]">right bracket ]</option>
                                <option value="/">slash /</option>
                                <option value="\">backslash \</option>
                        </select>                          
                    </div>
                </div>
                <div>
                    <div class="header" style="min-width: 50px;"><label>Fields enclosed in:</label></div>
                    <div class="input-cell">   
                        <select id="csv_enclosure" class="text ui-widget-content ui-corner-all" style="width:120px;">
                            <option selected value='2'>"</option><option value="1">'</option>
                            <option value="none">no enclosure</option>
                        </select>                         
                    </div>
                </div>
                <div>
                    <div class="header" style="min-width: 50px;"><label>Line separator:</label></div>
                    <div class="input-cell">        
                        <select id="csv_linebreak" class="text ui-widget-content ui-corner-all" style="width:120px;">
                            <option value="auto">auto detect</option>
                            <option selected value="win">Windows</option>
                            <option value="nix">Unix</option>
                            <option value="mac">Mac</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="header" style="min-width: 50px;"><label>Multivalue separator:</label></div>
                    <div class="input-cell">        
                        <select id="csv_mvsep" class="text ui-widget-content ui-corner-all" style="width:120px;">
                                <option value="|" selected>pipe |</option>
                                <option value=",">comma ,</option>
                                <option value="tab">tab</option>
                                <option value=";">semicolon ;</option>
                                <option value=":">colon :</option>
                                <option value="-">hyphen -</option>
                                <option value="=">equal sign =</option>
                                <option value="#">hash #</option>
                                <option value="$">dollar sign $</option>
                                <option value="@">at sign @</option>
                                <option value="&">ampersand &amp;</option>
                                <option value="*">asterisk *</option>
                                <option value="(">left parentheses (</option>
                                <option value=")">right parentheses )</option>
                                <option value="[">left bracket [</option>
                                <option value="]">right bracket ]</option>
                                <option value="/">slash /</option>
                                <option value="\">backslash \</option>
                                <option value="none">no separator</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="header" style="min-width: 50px;"><label>Date format:</label></div>
                    <div class="input-cell">        
                        <select id="csv_dateformat" class="text ui-widget-content ui-corner-all" style="width:120px;">
                            <option selected value='1'>dd/mm/yyyy</option><option value="2">mm/dd/yyyy</option></select>
                        </select>
                        <div class="heurist-helper1" style="display: block;">
                            Also supports ISO yyyy-mm-dd (and optional hh:mm:ss)
                            and human friendly dates such as 1827, 1st Sept 1827, 1 sep 1827
                        </div>
                    </div>
                </div>
                
                
                <div id="btnParseStep1" style="position:absolute;bottom:1em;left:2em" 
                    title="By clicking on this button Heurist starts to analyse the header of uploaded CSV data according to your parse parameters, extracts column names and verifies encoding and tries to convert it to UTF8">Analyse data</div>
        </fieldset>            
        <div style="position:absolute;right:10px;left:440px;top:1.2em;bottom:0">
                <div class="ent_header" style="border:none;display:none" id="divFieldRolesHeader">
                    <h2>Specify identifier and date columns</h2><br>
                    <div style="font-weight:bold;padding-bottom:1em">
                        Identifier columns are those that contain a Heurist record ID.<br>
                        They MUST contain "H-ID" somewhere in the column name.                    
                    </div>
                    
                </div>
                <div class="ent_content" id="divFieldRoles" style="top:6em;bottom:3.5em;padding: 0.2em 0.5em;border: 1px solid lightgray;">
                    
                    <table class="tbfields" style="font-weight:bold"><thead><tr>
                                <td style="width:150px">Column</td>
                                <td style="width:50px">&nbsp;</td>
                                <td style="width:50px;text-align:center">Heurist<br>Identifier</td>
                                <td style="width:50px;text-align:center">Date</td>
                                <td style="width:200px"><span id="lbl_ID_select" style="display:none">IDs for which record type?<span></td></tr></thead><tbody></tbody></table>
                    
                    
                </div>
                <div  class="ent_footer">
                    <div id="btnParseStep2" style="bottom:5px"
                    title="Start upload your CSV data into temporary database table, converts it to UTF8, parses data and verifies ID columns for valid integer values"
                    >Continue</div>
                    <label id="lblParseStep2" style="font-style:italic">
                        If Continue is disabled, you need to select record types in the list above
                    </label>
                </div>
        </div>
        
    </div>
    <div class="ent_content_full" style="top:28em;padding:0.5em" id="divParsePreview">
    
    </div>
</div>
<!-- STEP 3 matching and import -->
<div style="width:100%; height:100%;display:none;overflow:auto" id="divStep3" class="selectmenu-parent">

    <div class="ent_header" style="height:362;border:none;padding:5 6 0 6;min-width:970px">
    
        <div style="position:absolute;left:0;right:6;padding-left:2em">
    
            <div style="max-width: 500;float:left;">
                <div id="btnBackToStart"
                    title="Return to the upload screen to select a new delimited file to upload to the server for processing">
                    Back to start</div>
                    
                <div style="font-size:1.1em;padding-top:12px">
                    Importing: <h2 id="lblPrimaryRecordType" style="display: inline-block;font-weight: bold;"></h2>
                    <a href="#" id="btnSetPrimaryRecType"
                                title="Change primary record type"
                    style="margin-left:10px;font-size:0.9em;text-decoration:none;color:gray;font-style:italic">change</a>        
                                <!-- display:none; todo restore this feature -->
                </div>    
            </div>        
            <div id="btnClearFile"  style="float: right;"
                title="Clear the data for this uploaded file from the server">
                Clear uploaded file</div>
                
            <div id="helper1" class="heurist-helper1" style="float: right; padding:0.5em;">
                Note: Data is retained between sessions until cleared
            </div>    

            <div id="btnDownloadFile" style="float: right;"
                title="Download the data as currently displayed (including matching/IDs) to a new delimited file for further desktop editing">
                Download data to file</div>

    
        </div>

        <fieldset style="position: absolute;left: 0;right: 0;top:60px;">
        <div>
            <div class="header optional" style="min-width: 80px; width: 80px;">
                <label style="vertical-align: top;">Importing:</label><br>
                <label style="vertical-align: bottom;font-size:0.8em">(rollover for details)</label>
            </div>
            <div class="input-cell">
                <div class="heurist-prompt ui-state-error" style="display: none; height: auto; padding: 0.2em; margin-bottom: 0.2em;"></div>
                <div class="input-div">
                    <div id="sa_rectype_sequence" style="min-height:4em;height:6em"></div>
                </div>
                <!-- div class="heurist-helper1" style="display: block;">
                    If a record type is not shown in the pulldown, check the 'Show' column in Database > Manage Structure
                </div -->
            </div>        
        </div>                    
        </fieldset>
        
        <img id="img_arrow1" src="../../hclient/assets/blackdot.png" height="2" style="position:absolute;left:0px;width:100px;display:none" >
        <img id="img_arrow2" src="../../hclient/assets/blackdot.png" width="2"  style="position:absolute;left:0px;height:16px;display:none">
        <img id="img_arrow3" src="../../hclient/assets/arrow.png" style="position:absolute;left:0px;display:none">        
        <img id="img_arrow4" src="../../hclient/assets/blackdot.png" width="2" style="position:absolute;left:0px;height:16px;display:none">        
        
        <div style="padding:1em 0 1em 1em;position: absolute;top:110;" id="divheader">
            
            <div  id="divActionsMatching" class="action_buttons step3" style="padding-left: 35px">
                
                <h2 style="display:inline-block">step 1: MATCHING</h2>
                
                <div id="btnMatchingStart" class="normal" 
                    title="Start matching operation. Matching sets this ID field for existing records and allows the creation of new records for unmatched rows">Match against existing records</div>

                <div style="display:none" class="skip_step prompt">
                    Click on list of record types to skip steps
                </div>
<!--    
                <div id="btnNextRecType1" style="display:none" class="skip_step" 
                    title="It appears that every row in import data has valid Heurist record ID value. You may proceed to import of next record type in sequence">Skip to next record type</div>
-->                    
                <div id="btnBackToMatching2" class="need_resolve" style="margin-right:35px"
                    title="Return to matching step to redefine mapping that may fix ambiguous matches">
                    Match Again</div>
                <div id="btnResolveAmbiguous" class="need_resolve"
                    title="Show list of ambiguous matches, select the correct matching and continue import">
                    Resolve ambiguous matches</div>
                
            </div>
            
            <div  id="divActionsImport" style="display:none;" class="action_buttons step4 step5">
                <div id="btnBackToMatching" style="margin-right:20px;display:inline-block;"
                    title="Return to matching step to redefine record IDs">
                    Match Again</div>
                    
                <h2 class="step4" style="display:none;">step 2: FIELDS TO IMPORT</h2>
                <h2 class="step5" style="display:none;">step 3: INSERT/UPDATE</h2>
                    
                <div id="btnNextRecType2" style="display:none" class="skip_step" 
                    title="All input rows have been matched to existing records. This probably means that you are simply matching existing records and don't need to update them">
                    Skip update</div>
                    
                <div id="btnPrepareStart" class="step4 step5"
                    title="Verify that you map all required fields and that values in import table fit to constraints in Heurist database scheme">
                    Prepare Insert/Update</div>
                <div id="btnImportStart" class="step4 step5"
                    title="Start real import data into Heurist database">
                    Start Insert/Update</div>
                    
                <div style="display:none" class="skip_step prompt">
                    Click on list of record types to skip steps
                </div>


            </div>            
        </div>
<!-- radiogroup setting divs -->        

        <div  id="divMatchingSetting" class="step3 step-ctrls" style="display:none">
            <input type="radio" checked="" name="sa_match" id="sa_match0" value="0" class="text" onchange="{importRecordsCSV.onMatchModeSet()}">&nbsp;
            <label for="sa_match0" style="padding-right:3em">Match on column(s)</label>

            <input type="radio" name="sa_match" id="sa_match1" value="1" class="text" 
                        onchange="{importRecordsCSV.onMatchModeSet();importRecordsCSV.doMatchingInit();}">&nbsp;
            <label for="sa_match1" id="lbl_sa_match1" style="padding-right:3em">Use Heurist ID column</label>

<!--            
            <input type="radio" name="sa_match" id="sa_match2" value="2" class="text" onchange="{importRecordsCSV.onMatchModeSet()}">&nbsp;
            <label for="sa_match2">Skip matching (all new records)</label>
-->
            <div class="heurist-helper1" id="divMatchingSettingHelp" style="display:block;padding-top:1em;padding-bottom:3px;">
            </div>
        </div>
        <div  id="divPrepareSetting" class="step4 step-ctrls" style="display:none">
            <div class="heurist-helper1" id="divPrepareSettingHelp" style="display:block;">
            </div>
        </div>
        
        <div  id="divImportSetting" class="step5 step-ctrls" style="display:none">
            <div class="heurist-helper1" id="divImportSettingHelp" style="display:block;padding-bottom:1em">
                You are now ready to update the database. This step applies the changes you have prepared and is not (easily) reversible.            
            </div>

            <input type="radio" checked="" name="sa_upd" id="sa_upd0" value="0" class="text" onchange="{importRecordsCSV.onUpdateModeSet()}">&nbsp;
            <label for="sa_upd0">Retain existing values and append distinct new data as repeat values
                (existing values are not duplicated)</label><br>

            <input type="radio" name="sa_upd" id="sa_upd1" value="1" class="text" onchange="{importRecordsCSV.onUpdateModeSet()}">&nbsp;
            <label for="sa_upd1">Add new data only if field is empty (new data ignored for non-empty fields)</label><br>

            <input type="radio" name="sa_upd" id="sa_upd2" value="2" class="text" onchange="{importRecordsCSV.onUpdateModeSet()}">&nbsp;
            <label for="sa_upd2">Replace all existing value(s) for the fields specified below</label>
            
                    <div style="padding-left: 60px; font-size: 0.9em; vertical-align: top; display: none;" id="divImport2">
                        <input type="radio" checked="" name="sa_upd2" id="sa_upd20" value="0" class="text">&nbsp;
                        <label for="sa_upd20" style="font-size:0.9em;">Retain existing if no new data supplied for record</label><br>

                        <input type="radio" name="sa_upd2" id="sa_upd21" value="1" class="text">&nbsp;
                        <label for="sa_upd21" style="font-size:0.9em;">Delete existing even if no new data supplied for record</label>
                    </div>            
        </div>
<!-- end radiogroup setting divs -->     

        <div id="divFieldMapping2" class="step4" style="display:none;position:absolute;bottom:37px">
            <table class="tbresults" style="display:inline-block">
                <tbody>
                                    <tr>
                                        <td rowspan="3" width="250">
                                            <h2 id="mrr_big">Existing: 586  New: 100</h2>
                                        </td>
                                        <td rowspan="3">
                                            <div id="prepareWarnings" 
                                                style="display:none;padding:2px;background-color:#ffaaaa;border-color:red;margin-left:2em">
                                                <h2 id="mrr_warning" style="display:inline-block;margin:0 10px;">Warnings: 0</h2>
                                                <div id="btnShowWarnings" style="display:none"></div>
                                                <div id="btnShowUTMWarnings" style="display:none"></div>
                                            </div>
                                        </td>
                                        <td rowspan="3">
                                            <div id="prepareErrors" 
                                                style="display:none;padding:2px;background-color:#ffaaaa;border-color:red;margin-left:2em">
                                                <h2 id="mrr_error" style="display:inline-block;margin:0 10px;">Errors: 0</h2>
                                                <div id="btnShowErrors"></div>
                                            </div>
                                        </td>
                                        <td width="50" align=left style="padding-left:30px">Existing:</td>
                                        <td width="50" id="mrr_cnt_update"></td>
                                        <td width="50" class="mrr_update">rows:</td>
                                        <td width="50" class="mrr_update" id="mrr_cnt_update_rows"></td>
                                        <td width="50" class="mrr_update"><a href="#" onclick="importRecordsCSV.showRecords2('update',false)">show</a></td>
                                        <td width="50" class="mrr_update"><a href="#" onclick="importRecordsCSV.showRecords2('update',true)">download</a></td>
                                    </tr>
                                    <tr><td align=left style="padding-left:30px">New:</td>
                                        <td width="50" id="mrr_cnt_insert"></td>
                                        <td width="50" class="mrr_insert">rows:</td>
                                        <td width="50" class="mrr_insert" id="mrr_cnt_insert_rows"></td>
                                        <td width="50" class="mrr_insert"><a href="#" onclick="importRecordsCSV.showRecords2('insert',false)">show</a></td>
                                        <td width="50" class="mrr_insert"><a href="#" onclick="importRecordsCSV.showRecords2('insert',true)">download</a></td>
                                    </tr>
                                    <tr><td align=left style="padding-left:30px">Blank match fields:</td>
                                        <td width="50" id="mrr_cnt_ignore"></td>
                                        <td width="50" class="mrr_ignore">rows:</td>
                                        <td width="50" class="mrr_ignore" id="mrr_cnt_ignore_rows"></td>
                                        <td width="50"></td>
                                        <td width="50"></td>
                                    </tr>
                </tbody>
            </table>

        </div>
   
        
        <table class="tbmain" style="width:99%;position:absolute;bottom:0" cellspacing="0" cellpadding="2">
            <thead><tr> <!-- Table headings -->
                <th style="width:75px;">Use&nbsp;<br/>value</th>
                <th style="width:75px;">Unique&nbsp;<br/>values</th>
                <th style="width:300px;">Column</th>
                <th style="width:300px;" id="mapping_column_header">Column to Field Mapping</th>
                <!-- last column allows step through imported data records-->
                <th style="text-align: left;padding-left: 16px;">
                    <a href="#" class="navigation" style="display: inline-block;"><span data-dest="0" class="ui-icon ui-icon-seek-first"/></a>
                    <a href="#" class="navigation" style="display:inline-block;"><span data-dest="-1" class="ui-icon ui-icon-triangle-1-w"/></a>
                    <div style="display: inline-block;vertical-align: super;">Values in row <span id="current_row"></span></div>
                    <a href="#" class="navigation" style="display: inline-block;"><span data-dest="1" class="ui-icon ui-icon-triangle-1-e"/></a>
                    <a href="#" class="navigation" style="display: inline-block;"><span data-dest="last" class="ui-icon ui-icon-seek-end"/></a>
                </th></tr>
            </thead>
        </table>    
    </div>
    <!-- CONTENT : MAPPING TABLE COLUMNS TO HEURIST FIELDS  -->
    <div class="ent_content" style="bottom:0;top:367;padding: 0em 0.5em;" id="divFieldMapping">
                <table id="tblFieldMapping" class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
                    <!-- <thead><tr>
                        <th style="width:75px;">Use&nbsp;<br/>value</th>
                        <th width="75px">Unique&nbsp;<br/>values</th>
                        <th width="300px">Column</th>
                        <th width="300px">Column to Field Mapping</th>
                        <th></th></tr></thead> -->
                    <tbody>
                    
                    </tbody>
                </table>    
    </div>
    <div class="ent_footer" style="height:11em;padding: 0em 0.5em;display:none" id="divImportActions">
    </div>
</div>

<div style="width:100%; height:100%;display:none;" id="divStep0" class="loading">

<div id="progressbar_div" style="width:80%;height:40px;padding:5px;text-align:center;margin:auto;margin-top:20%;display:none;">
    <div id="progressbar">
        <div class="progress-label">Loading data...</div>
    </div>
    <div id="progress_stop" style="text-align:center;margin-top:4px">Abort</div>
</div>

</div>

<div id="divPopupPreview" style="display:none"></div>
<div id="divPopupPreview2" style="display:none"></div>

<div id="divSelectPrimaryRecType" style="display:none;height:100%;" class="">
        <fieldset>
        <div>
            <div class="header optional" style="min-width: 150px; width: 150px;"><label>Select record type:</label></div>
            <div class="input-cell">
                <div class="heurist-prompt ui-state-error" style="display: none; height: auto; padding: 0.2em; margin-bottom: 0.2em;"></div>
                <div class="input-div">
                    <select id="sa_primary_rectype" class="text ui-widget-content ui-corner-all" style="width: 32ex;">
                    </select>
                </div>              
                <div class="heurist-helper1" style="display: block; padding-bottom: 1em;">
                    The primary record type is the one represented by each row of the input file. Additional record types may be imported  from selected columns prior to import of the primary, as determined by the dependencies below.
                </div>
                <div class="heurist-helper1" style="display: block;padding-top:0.5em">
                    The creation of the primary record type from rows in the input file depends on the prior identification of other entities which will be connected via pointer fields or relationships. The tree below shows the dependencies of the primary record type determined from its pointer and relationship marker fields. Where an input entity matches an existing record, its ID value will be recorded in an ID field which can be used subsequently as a pointer field value; where no existing record is matched a new record is created and the new ID recorded<br><br>
                    Check record types to be imported. <span style="color:red">Red</span> indicates required pointer field
                    <span style="float:right">Click to rename suggested column name</span>
                    <div style="display:none;float:right;/*hidden 2016-11-26*/">View Mode:
                    <input type="radio" name="mode_view" value="0" id="mode_view0" checked><label for="mode_view0">Dependency list</label>
                    &nbsp;&nbsp;
                    <input type="radio" name="mode_view" value="1" id="mode_view1"><label for="mode_view1">Treeview</label>
                    </div>
                 </div>
            </div>        
        </div>                    
        <div>
            <div id="lbl_dependencies_preview" class="header optional" style="vertical-align: top;min-width: 150px; width: 150px;">
                <label>Dependencies:</label>
            </div>
            <div class="input-div">
                <div id="dependencies_preview" xclass="ui-widget-content" 
                    style="min-height:1.8em;background-color:white">
                </div>    
                
            </div>
        </div>                    
        </fieldset>
</div>


</body>
</html>