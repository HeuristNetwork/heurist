<?php
/**
* syncZotero.php - Handles synchronization with Zotero (zotero.org)
* 
* Sync Heurist database with zotero group or user items
* Set zotero API key in sys_SyncDefsWithDB/HEURIST_ZOTEROSYNC 
* Mapping is specified in zoteroMap.xml
* 
* @project     Heurist academic knowledge management system
* @package  import\biblio
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.2
*/
// Return a small loading page before any database/API setup. The real page
// is requested separately so web-server buffering cannot hide this message.
if (intval($_REQUEST['step'] ?? 0)!==2 && !isset($_GET['zotero_setup'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Zotero synchronisation</title></head><body>'
        .'<div id="processResults" style="margin:20px;font:14px Arial;max-width:1000px;line-height:1.5">Checking configuration, querying and verifying the Zotero library. This may take a couple of minutes</div>'
        .'<script>const u=new URL(location.href);u.searchParams.set("zotero_setup","1");'
        .'fetch(u,{credentials:"same-origin"}).then(r=>{if(!r.ok)throw new Error("Server response "+r.status);return r.text();})'
        .'.then(html=>{document.open();document.write(html);document.close();})'
        .'.catch(e=>{document.getElementById("processResults").append(document.createElement("br"),document.createTextNode("Setup could not complete: "+e.message));});</script></body></html>';
    exit;
}

use hserv\structure\ConceptCode;
use hserv\utilities\Temporal;
use hserv\utilities\DbUtils;

// Catch exceptions/fatal errors before the final report is reached. Buffer incidental
// PHP output so an error response remains valid JSON for the dialogue.
$zoteroDiagnosticStage = 'Initialising syncZotero.php';
$zoteroDiagnosticFinished = false;
$zoteroDiagnosticReserve = str_repeat(' ', 262144);
if(intval($_REQUEST['step'] ?? 0) === 2){
    $zoteroDiagnosticBufferLevel = ob_get_level();
    ob_start();
    set_exception_handler(function($exception){
        zoteroDiagnosticAbort(get_class($exception).': '.$exception->getMessage(), $exception->getFile(), $exception->getLine());
    });
    register_shutdown_function(function(){
        global $zoteroDiagnosticFinished, $zoteroDiagnosticReserve;
        if($zoteroDiagnosticFinished){ return; }
        $zoteroDiagnosticReserve = null;
        $error = error_get_last();
        $fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        zoteroDiagnosticAbort($fatal ? $error['message'] : 'The script exited before completing the synchronisation report.',
            $fatal ? $error['file'] : __FILE__, $fatal ? $error['line'] : 0);
    });
}

function zoteroDiagnosticAbort($message, $file, $line){
    global $zoteroDiagnosticStage, $zoteroDiagnosticFinished, $zoteroDiagnosticBufferLevel, $outputLines;
    $zoteroDiagnosticFinished = true;
    while(ob_get_level() > $zoteroDiagnosticBufferLevel){ ob_end_clean(); }
    // Never expose a Zotero API key embedded in an exception URL.
    $message = preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message);
    $detail = 'Zotero diagnostics 2026-10-01-collections-v11: '.$zoteroDiagnosticStage.'. '.$message
        .' — '.$file.':'.intval($line);
    error_log($detail);
    $report = '<div style="max-width:1000px;padding:15px"><h3>Synchronisation incomplete</h3><p>'
        .htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        .'</p><p>The run did not reach confirmed completion. Check settings/zotero_sync_status.json for the saved version. '
        .'Records already saved remain in the database.</p>'
        .implode('', is_array($outputLines) ? $outputLines : []).'</div>';
    if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['status' => defined('HEURIST_OK') ? HEURIST_OK : 'ok', 'data' => $report], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

ini_set('max_execution_time', '0');

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';
require_once dirname(__FILE__).'/../../hserv/structure/import/dbsImport.php';
require_once dirname(__FILE__).'/../../external/php/phpZotero.php';
require_once __DIR__.'/zoteroSyncStatus.php';
require_once __DIR__.'/zoteroCollections.php';
require_once __DIR__.'/../../hserv/structure/import/importHierarchicalTerms.php';

$system->defineConstants();

define('H_ID','h-id');

global $rectypes, $is_verbose, $report_log, $rep_errors_only, $dt_SourceRecordID, $dtDefines;
global $alldettypes, $allterms, $fi_dettype, $fi_constraint, $fi_trmlabel;
global $mapping_dt, $mapping_errors, $warning_count, $transfer_errors, $successful_rows, $outputLines, $terminatedByUser;
global $cnt_report, $cnt_updated, $cnt_added, $cnt_ignored, $cnt_empty, $cnt_notmapped, $cnt_notfound;
global $arr_ignored, $arr_ignored_by_type, $arr_empty, $arr_notmapped, $arr_notfound;

$step = intval(@$_REQUEST['step']);
$syncingStep = $step === 2;

if($syncingStep){
    header(CTYPE_JSON);
}

$dt_SourceRecordID = defined('DT_ORIGINAL_RECORD_ID') ? DT_ORIGINAL_RECORD_ID : 0;
if($dt_SourceRecordID == 0){ //this field is critical - need to download it from heurist core defintions database

    $isOK = false;
    $importDef = new DbsImport( $system );
    if($importDef->doPrepare(  array('defType'=>'detailtype',
    'conceptCode'=>$dtDefines['DT_ORIGINAL_RECORD_ID'] ) ))
    {
        $isOK = $importDef->doImport();
    }

    if(!$isOK){

        $errMsg = 'Cannot download field "Source record" required by the function you have requested. ';
        if($syncingStep){
            exitServerCall($errMsg, HEURIST_ERROR);
        }

        $system->addErrorMsg($errMsg);
        include_once ERROR_REDIR;

        exit;
    }
    if(!$system->defineConstant('DT_ORIGINAL_RECORD_ID', true)){

        $errMsg = 'Detail type "source record" is not defined';

        if($syncingStep){
            exitServerCall($errMsg, HEURIST_ERROR);
        }

        $system->addError(HEURIST_ERROR, $errMsg);
        include_once ERROR_REDIR;

        exit;
    }

    $dt_SourceRecordID = DT_ORIGINAL_RECORD_ID;
}

$HEURIST_ZOTEROSYNC = $system->settings->get('sys_SyncDefsWithDB');

$mapping_file = "zoteroMap.xml";
$fh_data = null;

if(!file_exists($mapping_file) || !is_readable($mapping_file)){

    $errMsg = 'Sorry, could not find/read configuration file .../import/biblio/zoteroMap.xml required for Zotero synchronisation - please ask your system administrator to copy it from Heurist source code';

    if($syncingStep){
        exitServerCall($errMsg, HEURIST_ERROR);
    }

    $system->addError(HEURIST_ERROR, $errMsg);
    include_once ERROR_REDIR;

    exit;
}

// 1) load config file from import/biblio/zoteroMap.xml.
// This file maps Zotero names to Heurist codes according to context
$fh_data = simplexml_load_file($mapping_file);
if($fh_data==null || is_string($fh_data)){

    $errMsg = 'Sorry, configuration file import/biblio/zoteroMap.xml for Zotero synchronisation is corrupted - please ask your system administrator to update it from Heurist source code';

    if($syncingStep){
        exitServerCall($errMsg, HEURIST_ERROR);
    }

    $system->addError(HEURIST_ERROR, $errMsg);
    include_once ERROR_REDIR;

    exit;
}
if(!$syncingStep){
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Zotero synchronization</title>

        <?php
        includeJQuery();
        ?>

        <!-- Heurist -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/admin/progressReport.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <style type="text/css">
            .tbl-head > td:nth-child(1) {
                font-size: 1.2em;
            }

            .ui-accordion-header.ui-state-active .ui-icon {
                background-image: url('https://code.jquery.com/ui/1.12.1/themes/base/images/ui-icons_444444_256x240.png') !important;
            }
            #processResults { box-sizing:border-box; margin:10px 0 0; }
            .zotero-sync-report { height:100%; box-sizing:border-box; overflow-y:scroll;
                border:1px solid #9aa9b2; background:#fff; padding:16px; word-break:break-word; }
            .zotero-sync-report > * { max-width:1050px; }
            .zotero-sync-report p, .zotero-sync-report details { margin:0 0 18px; }
            details summary { cursor:pointer; padding:8px 0; }
            .zotero-failures table { border-collapse:collapse; margin:12px 0; font-size:0.9em; }
            .zotero-failures td, .zotero-failures th { border:1px solid #ccd3d7; padding:7px; text-align:left; vertical-align:top; }
        </style>

        <script>
            function __startProcess(libraryIndex, count, syncID, retryOnly = false){

                const sessionID = window.hWin.HEURIST4.util.random();

                showProcess(sessionID);

                let request = {
                    step: 2,
                    cnt: count,
                    db: '<?= $system->dbname() ?>',
                    lib_key: libraryIndex,
                    sessionID: sessionID,
                    retryOnly: retryOnly ? 1 : 0
                };

                if(window.hWin.HEURIST4.util.isPositiveInt(syncID)){
                    request['sinceSync'] = syncID;
                }

                window.hWin.HEURIST4.util.sendRequest(`${window.hWin.HAPI4.baseURL}import/biblio/syncZotero.php`, request, null, (response) => {

                    window.hWin.HEURIST4.msg.hideProgress();

                    $('#processProgressbar').hide();
                    if(!response || response.status !== window.hWin.ResponseStatus.OK){
                        $('#processResults').text('Zotero diagnostics v4: the server did not return a valid completion report. Check the PHP/server error log.');
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    $('#processResults').html(response.data);
                    resizeZoteroReport();

                });
            }

            function markZoteroManuallyCorrected(key){
                window.hWin.HEURIST4.util.sendRequest(`${window.hWin.HAPI4.baseURL}import/biblio/syncZotero.php`, {
                    step:2, db: <?= json_encode($system->dbname()) ?>,
                    lib_key: <?= json_encode((string)($_REQUEST['lib_key'] ?? '0')) ?>, manuallyResolved:key
                }, null, (response) => {
                    if(response && response.status === window.hWin.ResponseStatus.OK){ window.location.reload(); }
                    else { window.hWin.HEURIST4.msg.showMsgErr(response); }
                });
            }
            function resizeZoteroReport(){
                const panel = document.getElementById('processResults');
                if(panel && panel.querySelector('.zotero-sync-report')){
                    panel.style.height = Math.max(200, window.innerHeight - panel.getBoundingClientRect().top - 12) + 'px';
                }
            }
            window.addEventListener('resize', resizeZoteroReport);

            function showProcess(sessionID){

                $('#setupContainer').hide();

                $('#processResults').text('Checking configuration, querying and verifying the Zotero library. This may take a couple of minutes');

                let $progressDiv = $('#processProgressbar');
                $progressDiv.show();

                window.hWin.HEURIST4.msg.showProgress({container: $progressDiv, session_id: sessionID, interval: 1000});
            }

            function open_sysIdentification(){
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification',
                    {onClose:function(){
                        location.reload();
                }});
                return false;
            }

            function showMappingReport(){

                var $report_ele = $('#mapping_report');
                if($report_ele.is(':hidden')){
                    $report_ele.show();
                    $('.report-btn').text('Hide Report');
                }else{
                    $report_ele.hide();
                    $('.report-btn').text('Show Report');
                }
            }
        </script>
    </head>

    <body class="popup" style="margin-right:30px;overflow:auto">
        <div id="processResults"><p>Checking configuration, querying and verifying the Zotero library. This may take a couple of minutes</p></div>
        <?php if (ob_get_level()) { ob_flush(); } flush(); ?>

        <?php

        if($HEURIST_ZOTEROSYNC==''){
        ?>
            <p class="ui-state-error" style="padding:20px;text-align:center">
                Library key for Zotero synchronisation is not defined.<br><br>
                <a href="#dbprop" onclick="open_sysIdentification()">
                    Click here to edit properties which determine Zotero connection</a>
            </p>
        </body>
    </html>

            <?php
            exit;
        }
}

$user_ID = null;
$group_ID = null;
$api_Key = null;
$mapping_dt = null;
$mapping_rt = array();
$warning_count = 0;
$mapping_errors = array();
$transfer_errors = array();
$successful_rows = array();
$mapping_rt_errors2 = array();
$rep_errors_only = true;

$lib_keys = explode("|", $HEURIST_ZOTEROSYNC);

if(!$step){
    if(count($lib_keys)>1){ //select key
    ?>

        <form action="syncZotero.php" style="padding:20px;">

            <input type="hidden" name="db" value="<?=$system->dbname()?>" />
            <input type="hidden" name="step" value="1" />
            Please select library:
            <select name="lib_key">
                <?php
                foreach($lib_keys as $idx=>$key){
                    $vals = explode(",",$key);
                    print "<option value='{$idx}'>{$vals[0]}</option>";
                }
                ?>
            </select>
            <input type="submit" value="Start" />
        </form>
        </body>
        </html>

<?php
        exit;
    }else{
        $lib_key_idx = 0;
        $step = 1;
    }
}else{
    $lib_key_idx = @$_REQUEST['lib_key'];
}

$key = $lib_keys[$lib_key_idx];
$syncID = intval(@$_REQUEST['sinceSync']);

$vals = explode(",",$key);

$user_Name = @$vals[0];
$user_ID = @$vals[1];
$group_ID = @$vals[2];
$api_Key  = @$vals[3];

if($user_Name != null) {$user_Name = trim($user_Name);}
if($user_ID != null) {$user_ID = trim($user_ID);}
if($group_ID != null) {$group_ID = trim($group_ID);}
if($api_Key != null) {$api_Key = trim($api_Key);}

$is_verbose = true;

$rectypes = dbs_GetRectypeStructures($system, null, 2);
if(!$syncingStep){
    print '<div id="setupContainer">';
    print '<div id="mapping_report" style="display: none;">';
}

$mapping_errors = [];
$transfer_errors = [];
$successful_rows = [];

// 2) verify heurist codes in mapping and create mapping array
foreach ($fh_data->children() as $f_gen){
    if($f_gen->getName()=="zTypes"){

        foreach ($f_gen->children() as $f_type){

            if($f_type->getName()=="typeMap"){
                $arr = $f_type->attributes();
                if(@$arr[H_ID]){

                    $zType = strval($arr['zType']);
                    // find record type with such code (or concept code)
                    $org_rt_id = strval($arr[H_ID]);
                    $rt_id = ConceptCode::getRecTypeLocalID($arr[H_ID]);

                    printMappingReport_rt($arr, $rt_id);

                    if($rt_id != null){

                        $mapping_dt = array();

                        foreach ($f_type->children() as $f_field){
                            if($f_field->getName()=="field"){
                                $arr = $f_field->attributes();

                                if(strval($arr['value'])=="creator"){

                                    foreach ($f_field->children() as $f_ctype){
                                        if($f_ctype->getName()=="creatorType"){
                                            $arr = $f_ctype->attributes();
                                            if(@$arr[H_ID])
                                            {
                                                addMapping($arr, $zType, $rt_id, $org_rt_id);
                                            }
                                        }
                                    }

                                }elseif(@$arr[H_ID])
                                {
                                    addMapping($arr, $zType, $rt_id, $org_rt_id);
                                }
                            }
                        }

                        if(empty($mapping_dt)){
                            array_push($mapping_rt_errors2, $zType);
                            $warning_count ++;
                        }else{
                            $mapping_dt["h3rectype"] = $rt_id;
                            $mapping_rt[$zType] = $mapping_dt;
                        }

                    }
                }
            }
        }
    }
}///foreach

if($step == 1){  // info about current status
    // show mapping and transfer issues report, also show the success mappings+transfers
    if(!empty($mapping_rt_errors2)
    || !empty($mapping_errors)
    || !empty($transfer_errors)){

        if(!empty($mapping_errors)){
            print "<strong>Data not mapped</strong><br>";
            print "<em>The following data has not been mapped for transfer from Zotero to Heurist.<br>If you require these record types or fields to be mapped,<br>please email a list to the Heurist team (support at HeuristNetwork.org).</em><br><br>";
            print TABLE_S . implode('', $mapping_errors) . TABLE_E . '<br>';
        }

        if(!empty($transfer_errors)){

            $headings = "<tr class='tbl-head'><th colspan='3' style='text-align:left;font-size:1.3em;'>Record type</th><th colspan='4' style='text-align:left;font-size:1.3em;'>Fields required</th></tr>";

            print "<strong>Data not transfered</strong><br>";
            print "<em>The following fields in Zotero have been mapped into the Heurist database but will<br>"
            . "not be saved as the record type does not contain a field to hold them. If you feel that<br>"
            . "any of these fields are needed, you may add the indicated base field to the record type.</em><br><br>";
            print TABLE_S . $headings . implode('', $transfer_errors) . TABLE_E . '<br>';
        }

        if(!empty($mapping_rt_errors2)){
            print "<p style='color:red'><br>No proper field mapping found for record types:";
            print '<br><br>' . implode('<br>', $mapping_rt_errors2) . '</p>';
        }

        print "<p style='color: red;margin-top: 0px;'>You may be able to find missing fields in the Heurist_Core_Definitions database (# 2) using Design > Browse templates, however the bibliographic definitions are normally integral to all new databases.</p>";
    }

    if(!empty($successful_rows)){

        $headings = <<<HEADING
        <tr class='tbl-head'>
            <th colspan='3' style='text-align:left;font-size:1.3em;'>Base field concept IDs<span style='padding-left:5.5em;'>map to --></span></th>
            <th colspan='2' style='text-align:left;font-size:1.3em;'>local field name and code</th>
        </tr>
        HEADING;

        print "<div id='success-accordion'><h3><strong>Data mapped for transfer</strong></h3>";
        print DIV_S.TABLE_S.$headings.implode("", $successful_rows).TABLE_E.DIV_E."</div><br><br>";

        // Make this section an accordion (jQuery UI)
        print '<script> $("#success-accordion").accordion({collapsible: true, heightStyle: "content", active: false});';
        print '$("#success-accordion").find(".ui-accordion-content").css({background: "none", border: "none"});';
        print '$("#success-accordion").find(".ui-accordion-header").css({color: "black", "font-size": "larger", "padding-left": "0px"});';
        print 'let $icon = $("<span>", {class: "ui-icon ui-icon-triangle-1-s", style: "float:right;"}); let $rows = $(".connectingField");';
        print '$rows.each((idx, tr) => { tr = $(tr); let $prev = tr.parent().prev().find("td").first(); $prev.append($icon.clone()); })';
        print '</script>';
    }

}


if(!$syncingStep){

    print DIV_E;
        
    print "<div>Mapping check completed. {$warning_count} warnings"
    .'<button class="h3button report-btn" onclick="showMappingReport()" style="margin-left: 10px;">Show report</button>'
    .'</div><br>';
}

if( is_empty($group_ID) && is_empty($user_ID) || is_empty($api_Key) ){

    if($syncingStep){
        exitServerCall('Invalid Zotero credentials provided, ' . (is_empty($api_Key) ? 'missing the API key.' : 'group or user ID is required.'), HEURIST_ACTION_BLOCKED);
    }

    print "<div class='ui-state-error' style='padding:20px'>Current Zotero access settings incomplete: {$key}";
    print '<br><br><a href="#" onclick="open_sysIdentification()">Click here to edit properties which determine Zotero connection</a>';
    print '</div></body></html>';
    exit;
}

// Language values use the immutable base field, including non-standard fields.
foreach (['2-965'=>'detailtype','2-496'=>'term'] as $concept=>$definitionType) {
    $localID = $definitionType==='term' ? ConceptCode::getTermLocalID($concept) : ConceptCode::getDetailTypeLocalID($concept);
    if (!$localID) {
        if (!$syncingStep) { print '<p>Importing missing Language definition '.htmlspecialchars($concept).' automatically...</p>'; }
        $import = new DbsImport($system);
        if (!$import->doPrepare(['defType'=>$definitionType,'conceptCode'=>$concept]) || !$import->doImport()) {
            $message = 'Cannot import Language definition '.$concept.': '.$system->getErrorMsg();
            if ($syncingStep) { exitServerCall($message, HEURIST_ERROR); }
            print '<p>'.htmlspecialchars($message).'</p>'; exit;
        }
    }
}
$collectionSetupMessages = [];
$missingCollectionDefinitions = [];
foreach (['1774-1189','1774-1190','1774-1191'] as $concept) {
    if (!ConceptCode::getDetailTypeLocalID($concept)) { $missingCollectionDefinitions[] = $concept; }
}
if ($missingCollectionDefinitions && !$syncingStep) {
    print '<p style="max-width:800px;line-height:1.5">Importing missing Zotero collection fields from Heurist_Core_Definitions automatically...</p>';
}
try {
    zoteroEnsureCollectionDefinitions($system, $collectionSetupMessages);
} catch (Throwable $e) {
    // A real download failure is actionable; missing record-type assignment is not.
    if ($syncingStep) { exitServerCall($e->getMessage(), HEURIST_ERROR); }
    print '<p>'.htmlspecialchars($e->getMessage()).'</p>';
    exit;
}
$collectionRecordTypes = [];
foreach ($mapping_rt as $mapping) {
    $rt = intval($mapping['h3rectype']);
    $collectionRecordTypes[$rt] = $rectypes['names'][$rt] ?? ('Record type '.$rt);
}
$collectionFields = zoteroCollectionFields($system->getMysqli(), $collectionRecordTypes, $collectionConfigErrors, $collectionNonstandardTypes);
$collectionConfigReport = zoteroCollectionConfigurationReport($collectionConfigErrors, $collectionNonstandardTypes, $collectionSetupMessages);
if (!$syncingStep) {
    print $collectionConfigReport;
    print '<p style="max-width:800px;line-height:1.5">Collection fields: <strong>1774-1189</strong> — collection/sub-collection name; '
        .'<strong>1774-1190</strong> — collection identifier; <strong>1774-1191</strong> — hierarchical terms for the complete path. '
        .'Run a full library update to fill these fields for records already synchronised, and after renaming or moving collections.</p>';
}

$zotero = null;
$zotero = new phpZotero($api_Key);

// Stable library identity, independent of its editable display name.
$syncIndex = ($group_ID ? 'groups/' : 'users/').($group_ID ?: $user_ID);
$legacy = $system->settings->getDatabaseSetting('External IDs');
$legacyIndex = 'ZoteroSync_'.($user_Name ?? $lib_key_idx);
try {
    $syncStatus = new ZoteroSyncStatus($system->getSysDir('settings'), $syncIndex, $legacy[$legacyIndex] ?? []);
    // Copy the old checkpoint once. Future reads/writes use only the dedicated file.
    $syncStatus->save();
    if (is_array($legacy) && isset($legacy[$legacyIndex])) {
        unset($legacy[$legacyIndex]);
        if (!$system->settings->setDatabaseSetting('External IDs', $legacy)) {
            error_log('Zotero status was migrated, but the obsolete external_IDs.json sync entry could not be removed.');
        }
    }
} catch (Throwable $e) {
    if ($syncingStep) { exitServerCall($e->getMessage(), HEURIST_ACTION_BLOCKED); }
    print errorDiv(htmlspecialchars($e->getMessage()));
    exit;
}
$previousSync = ['id' => intval($syncStatus->state['last_version']), 'date' => htmlspecialchars($syncStatus->state['last_sync'] ?? '')];
$retryOnly = !empty($_REQUEST['retryOnly']);
$retryKeys = array_keys($syncStatus->state['failed_records']);
if ($syncingStep && !empty($_REQUEST['manuallyResolved'])) {
    $key = (string)$_REQUEST['manuallyResolved'];
    if (!isset($syncStatus->state['failed_records'][$key])) {
        exitServerCall('This Zotero key is not in the retry list.', HEURIST_ACTION_BLOCKED);
    }
    $syncStatus->complete($key);
    $syncStatus->save();
    $syncStatus->release();
    exitServerCall('Removed manually corrected record from the retry list.', HEURIST_OK);
}
if (!$syncingStep) { $syncStatus->release(); }

$lastSync = $previousSync['id'] > 0 ? "<br><br>Last Sync Version: <strong>{$previousSync['id']} ({$previousSync['date']})</strong>" : '';
if(!$syncingStep){
    print "<div><b>Zotero connection configured</b>{$lastSync}</div><p style='font-size:0.9em;color:#666'>Zotero diagnostics 2026-10-01-collections-v11</p>";
    print zoteroReportSpacing(zoteroFailedRecordsReport($syncStatus->state['failed_records']));
    if ($retryKeys) {
        $onclick = '__startProcess('.json_encode($lib_key_idx).', '.count($retryKeys).', '.$previousSync['id'].', true)';
        print '<p><button class="h3button" onclick="'.htmlspecialchars($onclick, ENT_QUOTES).'">Retry failed records only</button></p>';
    }
    print '<br><a href="#" onclick="open_sysIdentification()">Click here to modify properties which determine Zotero connection</a><br><br>';
}

if($step == 1){  //first step - info about current status

    // 1) verify connection to zotero (get total count of top-level items in zotero)
    $items = false;
    if($group_ID){
        $items = $zotero->getItemsTop($group_ID, ['format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc'], "groups");
    }else{
        $items = $zotero->getItemsTop($user_ID, ['format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'sort'=>'dateModified', 'direction'=>'desc']);
    }

    $code = $zotero->getResponseStatus();

    if($code > 499){

        print "<div class='ui-state-error' style='padding:20px'>Zotero Server Side Error: returns response code: {$code}.<br><br>Please try this operation later.</div>";

    }elseif($code > 399){

        $msg = "<div class='ui-state-error' style='padding:20px'>Error. Cannot connect to Zotero API: returns response code: {$code}.<br><br>";
        if($code == 400 || $code == 401 || $code == 403){
            $msg .= "Please verify Zotero API key in Database > Properties - it may be incorrect or truncated.";
        }elseif($code == 404){
            $msg .= "Please verify Zotero User and Group ID in Database > Properties - values may be incorrect.";
        }elseif($code == 407){
            $msg .= "Proxy Authentication Required, please ask system administrator to set it";
        }

        print $msg.DIV_E;

    }elseif(!$items){

        print "<div class='ui-state-error' style='padding:20px'>Unrecognized Error: cannot connect to Zotero API: returns response code: {$code}</div>";
        if($code == 0){
            print "<div class='ui-state-error' style='padding:20px'>Please ask your system administrator to check that the Heurist proxy settings are correctly set.</div>";
        }

    }else{

        //Responses for multi-object read requests will include a custom HTTP header, Total-Results
        $totalitems = $zotero->getTotalCount();
        $extraMessage = '';

        if($previousSync['id'] > 0){

            $syncCount = false;
            $latestSyncID = false;
            if($group_ID){
                [$syncCount, $latestSyncID] = getZoteroHeaders($api_Key, 'groups', $group_ID, $previousSync['id']);
            }else{
                [$syncCount, $latestSyncID] = getZoteroHeaders($api_Key, 'users', $user_ID, $previousSync['id']);
            }

            if($syncCount !== false && $latestSyncID !== false && $syncCount > 0){

                print <<<HTML
                <div class='divStart' style='margin-bottom: 2em;'>
                    <span style="padding-right: 3em;">Newest version: <strong>{$latestSyncID}</strong></span>
                    <span>Changes since last sync: <strong>{$syncCount}</strong></span><br><br>
                    <a href='#' onclick='__startProcess("{$lib_key_idx}", "{$syncCount}", "{$previousSync["id"]}")'><button class='h3button'>Sync to latest version</button></a>
                </div><br>
                HTML;
            }elseif($syncCount === 0 && $latestSyncID !== false){
                $extraMessage = '<span style="font-weight: bold; color: #5cb760;">No changes since last sync</span><br>';
            }else{
                $extraMessage = '<span class="ui-state-error">Could not check Zotero for changes. Please retry later.</span><br>';
            }
        }

        if($totalitems > 0){
            if($previousSync['id'] <= 0){
                $extraMessage = 'No saved Zotero sync version was found. Run a full update to establish one.<br>';
            }

            print <<<HTML
            <div class='divStart'>
                Total items count in library: <strong>{$totalitems}</strong><br>
                {$extraMessage}<br>
                <a href='#' onclick='__startProcess("{$lib_key_idx}", "{$totalitems}", 0);return false;'><button class='h3button'>Update existing records from Zotero (full library)</button></a><br>
                <span><br>Updates matched records in place. Existing Heurist record IDs, 
                <br>links and fields not supplied by Zotero are retained.</span>
            </div><br><br>
            HTML;
            print "<div id='divLoading' style='display:none;height:40px;background-color:#FFF; background-image: url(../../hclient/assets/loading-animation-white.gif);background-repeat: no-repeat;background-position:50%;'>loading...</div>";
        }else{

            print "No items found within Library";
        }

        print "</div><div class='ent_wrapper' id='processProgressbar' style='background: white;z-index: 6000001;display: none;'></div><div id='processResultsPosition'></div><script>$('#processResults').appendTo('#processResultsPosition').empty();</script>";
    }
}elseif($syncingStep){ //second step - sync

    $alldettypes = dbs_GetDetailTypes($system);
    $allterms = dbs_GetTerms($system);

    $fi_dettype = $alldettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $fi_constraint = $alldettypes['typedefs']['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
    $fi_trmlabel = $allterms['fieldNamesToIndex']['trm_Label'];

    $report_log = "";
    $unresolved_pointers = [];

    // 1) start loop: fetch items by 100
    $cnt_updated = [];
    $cnt_added = [];
    $cnt_report = [];

    //not recognized zotero entries (rectypes)
    $cnt_ignored = 0;
    $arr_ignored = [];
    $arr_ignored_by_type = [];

    //ignored zote entries since no keys are mapped
    $cnt_empty = 0;
    $arr_empty = [];

    //not recognized zotero keys (fields)
    $cnt_notmapped = 0;
    $arr_notmapped = [];

    //detail type not found in this databse
    $cnt_notfound = 0;
    $arr_notfound = [];

    $start = 0;
    $syncID = $syncID > 0 ? $previousSync['id'] : 0;
    [$totalitems, $runVersion] = getZoteroHeaders($api_Key, $group_ID ? 'groups' : 'users', $group_ID ?: $user_ID, $syncID, $diagnostic);
    if ($runVersion === false) { exitServerCall($diagnostic, HEURIST_ACTION_BLOCKED); }
    if ($retryOnly) { $totalitems = 0; }
    // Retries have no since filter; otherwise unchanged failed items never return.
    $retryBatches = array_chunk($retryKeys, 50);
    $fetch = min($totalitems, 100);
    $enumerationComplete = true;
    $recordKeys = [];
    $recordTitles = [];
    $new_recid = 0;
    $isFailure = false;

    $is_echo = false;

    $mysqli = $system->getMysqli();

    $sessionID = intval(@$_REQUEST['sessionID']);
    $sessionCount = 0;
    $terminatedByUser = false;
    if($sessionID > 0){
        DbUtils::setSessionId($sessionID);
        DbUtils::setSessionVal("0,{$totalitems}");
    }

    $outputLines = [];

    $outputLines[] = '<br>Starting Zotero Library Sync for '. intval($totalitems) .' records...<br>';

    $collectionMap = [];
    $collectionFetchError = null;
    $collectionVocabulary = [];
    if ($collectionFields) {
        try { $collectionMap = zoteroFetchCollections($api_Key, $group_ID ? 'groups' : 'users', $group_ID ?: $user_ID); }
        catch (Throwable $e) { $collectionFetchError = $e->getMessage(); }
    }
    $searchOptions = [
        'format' => 'atom',
        'content' => 'json',
        'order' => 'dateAdded',
        'sort' => 'asc'
    ];
    if($syncID > 0){
        $searchOptions['since'] = intval($syncID);
    }

    $attemptedKeys = [];
    $progressTotal = $totalitems;
    while ($start < $totalitems || $retryBatches){
        $isRetryBatch = $start >= $totalitems;
        $batchKeys = $isRetryBatch ? array_values(array_filter(array_shift($retryBatches), function($key) use ($syncStatus, $attemptedKeys) {
            return !isset($attemptedKeys[$key]) && isset($syncStatus->state['failed_records'][$key]);
        })) : [];
        if ($isRetryBatch && !$batchKeys) { continue; }
        $options = $searchOptions;
        if ($isRetryBatch) {
            unset($options['since']);
            $options['itemKey'] = implode(',', $batchKeys);
            $options['start'] = 0;
            $options['limit'] = 50;
        } else {
            $options['start'] = $start;
            $options['limit'] = min(100, $totalitems - $start);
        }
        $unresolved_pointers = [];


        $searchOptions['start'] = $start;
        $searchOptions['limit'] = $fetch;

        $zoteroDiagnosticStage = 'Fetching Zotero items, offset '.intval($start).', count '.intval($fetch);
        if($group_ID){
            $items = $zotero->getItemsTop($group_ID, $options, "groups");
        }else{
            $items = $zotero->getItemsTop($user_ID, $options);
        }

        $zoteroDiagnosticStage = 'Reading Zotero items, offset '.intval($start).', count '.intval($fetch);
        $zdata = $items ? simplexml_load_string($items) : false;
        if ($zotero->getResponseStatus() >= 400) { $zdata = false; }

        if($zdata===false){

            $outputLines[] = "<div style='color:red'>Error: zotero returns non valid xml response for range $start ~ ".intval($start+$fetch)." </div>";
            $isFailure = true;
            $enumerationComplete = false;

            $system->addError(HEURIST_ERROR, 'Zotero Synchronisation, Invalid XML Response',
                'Zotero Synchronisation has Encountered an Invalid XML Response',
                "Error: zotero returns non valid xml response for range $start ~ ".intval($start+$fetch));

            break;
        }elseif(!$isRetryBatch && count($zdata->entry) < 1){

            $outputLines[] = "<div style='color:red'>Error: zotero returns empty response for range $start ~ ".intval($start+$fetch)." </div>";
            $isFailure = true;
            $enumerationComplete = false;

            $system->addError(HEURIST_ERROR, 'Zotero Synchronisation, Empty Response',
                'Zotero Synchronisation has encountered an Empty Response',
                "Error: zotero returns empty response for range $start ~ ".intval($start+$fetch));

            break;
        }

        if (!$isRetryBatch && count($zdata->entry) !== $options['limit']) {
            $enumerationComplete = false;
            $outputLines[] = errorDiv('Zotero returned fewer items than expected. The previous version will be retained.');
        }

        if ($isRetryBatch) { $progressTotal += count($zdata->entry); }

        // Persist the fetched work BEFORE modifying any Heurist record. A fatal error
        // leaves these keys available for retry rather than silently skipping them.
        foreach ($zdata->entry as $pendingEntry) {
            $pendingKey = strval(findXMLelement($pendingEntry, 'zapi', 'key'));
            if (!$pendingKey) { $enumerationComplete = false; continue; }
            if (isset($attemptedKeys[$pendingKey])) { continue; }
            $syncStatus->fail($pendingKey, strval(findXMLelement($pendingEntry, null, 'title')), 0,
                'Processing interrupted before this item completed.', 'pending');
        }
        $syncStatus->save();
        $seenKeys = [];
        foreach ($zdata->children() as $entry){

            if($entry->getName() == "entry"){

                $zotero_itemid = strval(findXMLelement($entry, "zapi", "key"));
                if (!$zotero_itemid) { $enumerationComplete = false; continue; }
                $seenKeys[] = $zotero_itemid;
                if (isset($attemptedKeys[$zotero_itemid])) { continue; }
                $attemptedKeys[$zotero_itemid] = true;
                if ($sessionID > 0) {
                    ++$sessionCount;
                    if (DbUtils::setSessionVal("{$sessionCount},{$progressTotal}")) {
                        $terminatedByUser=true;
                        $outputLines[]='<div style="font-weight:bold">Process terminated by User</div>';
                        break 2;
                    }
                }


                // 2) get content of item if itemType is supported
                $itemtype = strval(findXMLelement($entry, "zapi", "itemType"));
                $itemtitle = strval(findXMLelement($entry, null, "title"));

                if(!array_key_exists($itemtype, $mapping_rt)){ //this type is not mapped

                    $syncStatus->fail($zotero_itemid, $itemtitle, 0, 'Zotero record type is not mapped: '.$itemtype, 'mapping');
                    $syncStatus->state['failed_records'][$zotero_itemid]['zotero_type'] = $itemtype;
                    $itemtype = htmlspecialchars($itemtype);
                    $itemtitle = htmlspecialchars($itemtitle);



                    if(!@$arr_ignored_by_type[$itemtype]) {$arr_ignored_by_type[$itemtype] = 0;}
                    $arr_ignored_by_type[$itemtype]++;
                    $cnt_ignored++;

                    continue;
                }

                $recId = null;
                $rec_URL = null;

                // 3) try to search record in database by zotero id
                $query = "select r.rec_ID, r.rec_Modified from Records r, recDetails d ".
                "where  r.rec_Id=d.dtl_recId and d.dtl_DetailTypeID="
                .intval($dt_SourceRecordID)." and d.dtl_Value='"
                .$mysqli->real_escape_string($zotero_itemid)."'";
                try { $res = $mysqli->query($query); } catch (Throwable $e) { $res = false; }
                if ($res === false) {
                    $syncStatus->fail($zotero_itemid, $itemtitle, 0, 'Could not look up the existing Heurist record; no new record was created. Check the database error log.');
                    $isFailure = true;
                    continue;
                }
                if($res){
                    $row = $res->fetch_row();
                    if($row){
                        $recId = $row[0];

                        $rec_modified = strtotime($row[1]);

                        // 4) compare updated time - if it is less than in Heurist database, ignore this entry
                        $t_updated = strtotime(strval(findXMLelement($entry, null, "updated")));
                    }
                }

                $content = json_decode(strval(findXMLelement($entry, null, "content")));
                if (!is_object($content)) {
                    $syncStatus->fail($zotero_itemid, $itemtitle, $recId, 'Invalid Zotero item JSON.');
                    $isFailure = true;
                    continue;
                }

                // 5) create "details" array based on mapping

                $unresolved_records = array();
                $details = array();

                //find heurist record type mapped to zotero entry
                $mapping_dt = $mapping_rt[$itemtype];
                $recordType = $mapping_dt["h3rectype"];

                $is_empty_zotero_entry = true;
                $itemMappingErrors = [];
                $emptyCollectionFields = [];
                $collectionItemError = null;
                if (!empty($collectionFields[$recordType])) {
                    try {
                        if ($collectionFetchError) { throw new RuntimeException($collectionFetchError); }
                        if (!isset($content->collections)) { throw new RuntimeException('Zotero item response lacks collection memberships.'); }
                        $values = zoteroCollectionValues($content->collections, $collectionMap, ($group_ID ? 'groups/' : 'users/').($group_ID ?: $user_ID));
                        foreach ($collectionFields[$recordType] as $kind => $fieldID) {
                            $fieldValues = $values[$kind];
                            if ($kind==='paths' && $fieldValues) {
                                if (!isset($collectionVocabulary[$fieldID])) { $collectionVocabulary[$fieldID] = zoteroCollectionVocabulary($system, $fieldID); }
                                $leafIDs = importHierarchicalTerms($system, $collectionVocabulary[$fieldID], $fieldValues);
                                $fieldValues = array_values(array_unique(array_values($leafIDs)));
                                // recordSave validates terms against the current vocabulary tree.
                                $alldettypes = dbs_GetDetailTypes($system);
                                $allterms = dbs_GetTerms($system);
                            }
                            if ($fieldValues) { $details['t:'.$fieldID] = $fieldValues; }
                            else { $emptyCollectionFields[] = $fieldID; }
                        }
                    } catch (Throwable $e) {
                        $collectionItemError = ($collectionItemError ? $collectionItemError.' ' : '').'Collections: '.$e->getMessage();
                        // Preserve existing collection fields when metadata could not be read.
                        foreach ($collectionFields[$recordType] as $fieldID) { unset($details['t:'.$fieldID]); }
                        $emptyCollectionFields = [];
                    }
                }

                foreach ($content as $zkey => $value){
                    if ($zkey==='collections') { continue; }

                    if($value===null || $value==='' || $value===[]) {continue;}

                    $is_empty_zotero_entry = false;

                    if($zkey == "creators"){

                        /* sample of creator objects in Zoterp
                        Array (
                        [0] => stdClass Object
                        (
                        [creatorType] => editor
                        [firstName] => Harold
                        [lastName] => Mytum
                        )

                        [1] => stdClass Object
                        (
                        [creatorType] => editor
                        [firstName] => Gilly
                        [lastName] => Carr
                        )

                        [2] => stdClass Object
                        (
                        [creatorType] => author
                        [firstName] => John H.
                        [lastName] => Jameson
                        )
                        )
                        */


                        foreach($value as $creator){

                            $prop = 'creatorType';
                            $ctype = @$creator->$prop;

                            $key = @$mapping_dt[$ctype];
                            if(!$key) { zoteroCountUnassigned($recordType, $ctype); continue;}

                            $prop = 'name';
                            $title = @$creator->$prop;

                            if(!is_array($key)){
                                if(!$title){
                                    $key = array($key, RT_PERSON, 0);
                                }else{
                                    $key = array($key, RT_ORGANISATION, 0);
                                }
                            }

                            if(!$title){
                                $prop = 'lastName';
                                $lastName = @$creator->$prop;

                                if($lastName){
                                    $prop = 'firstName';
                                    assignUnresolvedPointer($unresolved_records, $key,
                                        array(DT_GIVEN_NAMES => @$creator->$prop, DT_NAME => $lastName) );
                                    continue;
                                }
                            }

                            if ($title){
                                assignUnresolvedPointer($unresolved_records, $key, array(DT_NAME => $title));
                            }


                        }

                        continue;
                    }

                    if($zkey == "url"){
                        $rec_URL = $value;
                    }

                    //find heurist field type mapped to zotero key
                    $key = $zkey==='language' ? ConceptCode::getDetailTypeLocalID('2-965') : @$mapping_dt[$zkey];
                    $resource_rt_id = null;
                    $resource_dt_id = null;

                    if($key){

                        if(is_array($key)){ //reference to record pointer
                            $detail_id = $key[0];
                        }else{
                            $detail_id = $key;
                        }

                        if(!@$alldettypes['typedefs'][$detail_id] && $zkey != 'url'){
                            //field id not found in this db
                            $msg = $itemtype.'.'.$zkey.' -> '.$detail_id;
                            zoteroCountUnassigned($recordType, $zkey);
                            $itemMappingErrors[] = 'Mapped field for Zotero '.$zkey.' is missing (field Concept ID '.ConceptCode::getDetailTypeConceptID($detail_id).').';
                            if(!in_array($msg, $arr_notfound)){
                                array_push($arr_notfound, $msg);
                                $cnt_notfound++;
                            }
                            continue;
                        }

                        // Language must never fall through to text storage, even if cached
                        // metadata was loaded before definitions were imported.
                        $dt_type = $zkey==='language' ? 'enum' : mysql__select_value($system->getMysqli(), 'SELECT dty_Type FROM defDetailTypes WHERE dty_ID='.intval($detail_id));

                        if($dt_type=='enum' || $dt_type=='relationtype'){
                            // 6) find terms by label values
                            $trm_value = resolveTermValue($detail_id, $dt_type, $value, $recordType);
                            if(!is_numeric($trm_value) || intval($trm_value)<=0){
                                $fieldName = $alldettypes['names'][$detail_id] ?? ('Field '.$detail_id);
                                $itemMappingErrors[] = 'Zotero value "'.(is_scalar($value) ? $value : json_encode($value)).'" does not match a selectable term in '.$fieldName.' (Concept ID '.ConceptCode::getDetailTypeConceptID($detail_id).') .';
                                continue;
                            }

                            $value = intval($trm_value);

                        }elseif($dt_type=='resource'){

                            // 7) store pointer titles in 'unresolved' pointers
                            if(!is_array($key)){ //by default
                                $key = array($detail_id, RT_NOTE, DT_NAME);
                            }

                            assignUnresolvedPointer($unresolved_records, $key, $value);

                            continue;
                        }
                        if($zkey=="pages"){

                            $pages = explode("-",$value);
                            $details["t:".$detail_id] = array("0"=>$pages[0]);
                            $detail_id2 = ConceptCode::getDetailTypeLocalID("3-1027");//MAGIC NUMBER
                            if($detail_id2){
                                $details["t:".$detail_id2] = array("0"=>(count($pages)>1)?$pages[1] :$pages[0]);
                            }

                        }else{

                            if($dt_type=='freetext' || $dt_type=='blocktext'){
                                $value = html_entity_decode($value);
                                //$val = htmlspecialchars_decode($val);
                            }

                            $details["t:".$detail_id] = array("0"=>$value);
                        }

                    }elseif(!($zkey == 'url' || $zkey=='key')){
                        zoteroCountUnassigned($recordType, $zkey);
                    }
                }//for fields in content

                $new_recid = null;

                if($is_empty_zotero_entry){

                    $syncStatus->fail($zotero_itemid, $itemtitle, $recId, 'No data recorded in Zotero.');
                    $outputLines[] = errorDiv('Warning: zotero id '.htmlspecialchars($zotero_itemid).': no data recorded in Zotero for this entry');

                }elseif(empty($details)){
                    //no one zotero key has proper mapping to heurist fields
                    array_push($arr_empty, $zotero_itemid);
                    $cnt_empty++;
                    $syncStatus->fail($zotero_itemid, $itemtitle, $recId, 'No Zotero fields have a usable mapping.', 'mapping');
                }else{
                    $zoteroDiagnosticStage = 'Saving Zotero item '.$zotero_itemid.', Heurist record '.intval($recId).', record type '.intval($recordType);
                    $itemErrorStart = count($outputLines);
                    try {
                        $new_recid = addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, $is_echo, $totalitems);
                    } catch (Throwable $e) {
                        $outputLines[] = errorDiv(htmlspecialchars(preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $e->getMessage())));
                        $new_recid = 0;
                    }
                    if($new_recid){
                        if ($emptyCollectionFields) {
                            try {
                                $cleared = $mysqli->query('DELETE FROM recDetails WHERE dtl_RecID='.intval($new_recid).' AND dtl_DetailTypeID IN ('.implode(',', array_map('intval',$emptyCollectionFields)).')');
                            } catch (Throwable $e) { $cleared = false; }
                            if (!$cleared) { $collectionItemError = 'Could not clear removed Zotero collection memberships for H-ID '.intval($new_recid).'.'; }
                        }
                        $recordKeys[$new_recid] = $zotero_itemid;
                        $recordTitles[$new_recid] = $itemtitle;
                        if (empty($unresolved_records)) { $syncStatus->complete($zotero_itemid); }
                        else { $syncStatus->fail($zotero_itemid, $itemtitle, $new_recid, 'Author/institution links awaiting resolution.', 'pending'); }
                        if(!empty($unresolved_records)){
                            $unresolved_pointers[$new_recid] = $unresolved_records;
                        }
                        if(!@$cnt_report[$recordType]) {$cnt_report[$recordType] = ['added' => [], 'updated' => []];}

                        if($recId==$new_recid){
                            $cnt_updated[]=$new_recid;
                            $cnt_report[$recordType]['updated'][] = $new_recid;
                        }else{
                            $cnt_added[]=$new_recid;
                            $cnt_report[$recordType]['added'][] = $new_recid;
                        }
                    }else{
                        $syncStatus->fail($zotero_itemid, $itemtitle, $recId,
                            strip_tags(implode(' ', array_slice($outputLines, $itemErrorStart))) ?: 'Record save failed.');
                        $isFailure = true;
                    }
                }
                if ($collectionItemError) {
                    $itemMappingErrors[] = $collectionItemError;
                }
                if ($itemMappingErrors) {
                    $syncStatus->fail($zotero_itemid, $itemtitle, $new_recid ?: $recId, implode("\n", $itemMappingErrors), 'field_mapping');
                }
            }//entry

        }//end of for each loop by items in fetch

        if (!handleUnresolvedPointers($mysqli, $unresolved_pointers)) { $isFailure = true; }
        if ($isRetryBatch) {
            foreach (array_diff($batchKeys, $seenKeys) as $missingKey) {
                $old = $syncStatus->state['failed_records'][$missingKey] ?? [];
                $syncStatus->fail($missingKey, $old['title'] ?? '', $old['heurist_record_id'] ?? 0,
                    'Item was not returned by Zotero (deleted, trashed, or no longer top-level).', 'unavailable');
            }
        }
        $syncStatus->save();
        if (!$isRetryBatch) { $start += $options['limit']; }

    }// end of while loop

    prepareErrors();

    if(!empty($unresolved_pointers)){
        $outputLines[] = '<br>';
    }

    //$output = ob_get_clean();

    $detailLines = $outputLines;
    $outputLines = $collectionConfigReport ? [$collectionConfigReport] : [];
    // Item failures are now a retry queue, not a reason to replay the whole library.
    // Stop/fetch failures still retain the old cursor because unseen keys are unknown.
    if (!$terminatedByUser && $enumerationComplete && !$retryOnly) {
        [, $endVersion] = getZoteroHeaders($api_Key, $group_ID ? 'groups' : 'users', $group_ID ?: $user_ID, $syncID, $diagnostic);
        if ($endVersion !== false && $endVersion === $runVersion) {
            $syncStatus->state['last_version'] = $runVersion;
            $syncStatus->state['last_sync'] = gmdate('c');
        } else {
            $outputLines[] = errorDiv('The library changed during synchronisation, or its final version could not be checked. The previous checkpoint is retained to avoid missing changes.');
        }
    }
    if ($terminatedByUser || !$enumerationComplete) {
        $outputLines[] = errorDiv('Download was interrupted. The previous Zotero version is retained; fetched unfinished items remain in the retry list.');
    }
    $syncStatus->save();
    $remaining = count($syncStatus->state['failed_records']);
    $outputLines[] = '<p><strong>Saved Zotero version: '.intval($syncStatus->state['last_version']).'</strong><br>'
        .'Status file: settings/zotero_sync_status.json<br>Records awaiting retry: '.$remaining.'</p>';
    $actionable = array_filter($syncStatus->state['failed_records'], function($entry) { return $entry['category'] !== 'mapping'; });
    prepareUpdateTable(!$enumerationComplete || count($actionable) > 0);
    if ($actionable) {
        foreach ($actionable as $entry) {
            $id = intval($entry['heurist_record_id']);
            $link = $id ? '<a target="_blank" href="'.HEURIST_BASE_URL.'?db='.rawurlencode($system->dbname()).'&amp;q=ids:'.$id.'">H-ID '.$id.'</a>' : 'Not yet saved in Heurist';
            $outputLines[] = '<p>'.$link.' — '.htmlspecialchars($entry['title']).'<br>'.nl2br(htmlspecialchars(preg_replace('/\.\s+(?=Zotero value)/', ".\n", $entry['error']))).'</p>';
        }
    }
    $outputLines[] = zoteroReportSpacing(zoteroFailedRecordsReport($syncStatus->state['failed_records']));
    $outputLines[] = '<p><a href="'.htmlspecialchars($_SERVER['PHP_SELF']).'?db='.rawurlencode($system->dbname())
        .'&amp;lib_key='.rawurlencode($lib_key_idx).'&amp;step=1">Return to Zotero synchronisation / retry failed records</a></p>';
    $outputLines[] = '<details><summary>Mapping warnings and diagnostic details — show</summary>'
        .implode('', $detailLines).'</details>';
    $report = '<div class="zotero-sync-report"><p><strong>Zotero synchronisation 2026-10-01-collections-v11</strong></p>'
        .implode('', $outputLines).'</div>';
    $syncStatus->release();
    exitServerCall(str_replace('Heurist H-ID', 'H-ID', $report), HEURIST_OK);
}

/**
 * Compose an HTML link to a Heurist search query for the given IDs.
 *
 * @param int[] $ids An array of Heurist record IDs.
 * @return string An HTML link, or '0' if the array is empty.
 */
function composeLinkForAllIds($ids){
    global $system;
    if(empty($ids)){
        return '0';
    }else{
        return '<a target="_blank" href="'
        .HEURIST_BASE_URL.'?db='.$system->dbname().'&q=ids:'
        .htmlspecialchars(implode(',',$ids)).'">'
        .count($ids).'</a>';
    }
}

/**
 * Add mapping for a given Zotero type.
 *
 * @param SimpleXMLElement $arr Attributes of the Zotero field.
 * @param string $zType Zotero type.
 * @param int $rt_id Heurist record type ID.
 * @param string $org_rt_id Original Zotero record type ID (code).
 * @return void
 */
function addMapping($arr, $zType, $rt_id, $org_rt_id){

    global $mapping_dt, $mapping_errors, $warning_count;

    $dt_code = strval($arr[H_ID]);
    $resource_rt_id = null;
    $resource_dt_id = null;

    $extra_info = array();// [0] => Zotero rectype id, [1] => Zotero rectype name, [2] => field id, [3] => field name
    array_push($extra_info, $org_rt_id, $zType, $dt_code, $arr['value']);

    //pointer mapping
    if(strpos($dt_code,".")>0){

        $res = getResourceMapping($dt_code, $rt_id, $arr, $extra_info);
        if(is_array($res)){
            $mapping_dt[strval($arr['value'])] = $res;
        }else{

            // Resource, NOT FOUND
            if(array_key_exists($dt_code, $mapping_errors)){

                $mapping_errors[$dt_code] = str_replace(TR_E, "", $mapping_errors[$zType]).", ".$res.TR_E;
                $warning_count ++;

            }else{
                $mapping_errors[$dt_code] = "<tr><td colspan='3'><strong>".$zType." (".$org_rt_id."):</strong></td><td colspan='4'>".$res.TR_E;
                $warning_count ++;
            }
        }

    }else{

        $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);

        if($dt_id != null){
            $mapping_dt[strval($arr['value'])] = $dt_id;
        }

        printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info);
    }
}

//
//
//
/**
 * Print mapping report for a Zotero record type.
 * Outputs HTML table rows to global report arrays.
 *
 * @param SimpleXMLElement|string $arr Input attributes or code string.
 *                                     If object, expected to have 'zType' and H_ID attributes.
 * @param int|null $rt_id Heurist record type ID, or null if not found.
 * @return void
 */
function printMappingReport_rt($arr, $rt_id){

    global $rectypes, $mapping_errors, $successful_rows, $warning_count;

    $table_class = is_object($arr) && $rt_id != null ? 'tbl-head' : 'tbl-row';

    if(is_object($arr)){
        $zType = strval($arr['zType']);
        $code = $arr[H_ID];
    }else{
        $zType = '->';
        $code = $arr;
    }

    if($rt_id==null){ // NOT FOUND

        if($zType != '->'){
            //-> will be covered during resource (record type) field handling
            $rt_id = strval($code);
            $mapping_errors[$zType] = "<tr class='{$table_class}'><td colspan='3'><strong>{$zType} ({$rt_id}):</strong></td><td colspan='4'>no field mappings available</td></tr>";
            $warning_count ++;
        }
    }elseif($zType === '->'){

        $successful_rows[] = "<tr class='{$table_class}'><td colspan='2' class='connectingField'>&nbsp;</td><td><strong>{$code}</strong></td>"
        ."<td><strong>&rArr;{$rectypes['names'][$rt_id]}</strong></td><td><strong>{$rt_id}</strong></td></tr>";
    }else{

        if(count($successful_rows) > 1){
            $successful_rows[] = "<tr class='tbl-row'><td colspan='5' style='padding-top: 1em;'><hr></td></tr>";
        }
        $successful_rows[] = "<tr class='{$table_class}'><td colspan='2'><strong style='font-size: 1.3em;'>{$zType}</strong></td><td><strong>{$code}</strong></td>"
        ."<td><strong>&rArr;{$rectypes['names'][$rt_id]}</strong></td><td><strong>{$rt_id}</strong></td></tr>";
    }
}

//
//
//
/**
 * Print mapping report for a Zotero detail type (field).
 * Outputs HTML table rows to global report arrays.
 *
 * @param SimpleXMLElement|array|string $arr Input attributes, array, or code string.
 *                                           If object, expected to have 'value' and H_ID attributes.
 *                                           If array, specific indices are used.
 * @param int $rt_id Heurist record type ID.
 * @param int|null $dt_id Heurist detail type ID, or null if not found/mapped.
 * @param array|null $extra_info Additional information for reporting. Expected structure:
 *                                 [0] => Zotero record type ID (string)
 *                                 [1] => Zotero record type name (string)
 *                                 [2] => Zotero field ID (string)
 *                                 [3] => Zotero field name (string)
 * @return void
 */
function printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info){

    global $rectypes, $mapping_errors, $transfer_errors, $successful_rows, $warning_count;

    if(is_object($arr)){
        $label = $arr['value'];
        $code = $arr[H_ID];
    }elseif(is_array($arr)){
        $label = $arr[3][0];
        $code = $arr[2];
    }else{
        $label = '';
        $code = $arr;

        if(is_array($arr)){ error_log(print_r($arr, true));}
    }

    if($extra_info == null){
        if(is_array($arr)){
            $extra_info = $arr;
        }
    }

    $dt_str = '';
    if(is_array($extra_info)){

        if(is_empty($extra_info[0])){
            $extra_info[0] = $rt_id;
        }
        if($label == ''){
            $dt_str = $extra_info[3][0]."(".$code.")";
        }else{
            $dt_str = $label."(".$code.")";
        }
    }

    if($dt_id==null){

        if($extra_info != null){

            if(array_key_exists($extra_info[1], $mapping_errors)){ // NOT FOUND

                if(strpos($mapping_errors[$extra_info[1]], $dt_str) === false){ // Check if field is already listed
                    $mapping_errors[$extra_info[1]] = str_replace(TR_E, "", $mapping_errors[$extra_info[1]]).", ".$dt_str.TR_E;
                    $warning_count ++;
                }
            }else{
                $mapping_errors[$extra_info[1]] = "<tr class='tbl-row'><td colspan='3'><strong>".$extra_info[1]." (".$extra_info[0]."):</strong></td><td colspan='4'>unmapped fields: ".$dt_str.TR_E;
                $warning_count ++;
            }
        }
    }else{
        if(@$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id]){
            $successful_rows[] = "<tr class='tbl-row'><td colspan='2'>".$label.TD.$code."</td><td>&rArr;".$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id][0].TD.$dt_id.TR_E;
        }else{ // NOT IN RECORD TYPE STRUCTURE

            if($extra_info != null){

                if(array_key_exists($extra_info[1], $transfer_errors)){

                    if(strpos($transfer_errors[$extra_info[1]], $dt_str) === false){
                        $transfer_errors[$extra_info[1]] = str_replace(TR_E, "", $transfer_errors[$extra_info[1]]).", ".$dt_str.TR_E;
                        $warning_count ++;
                    }
                }else{
                    $transfer_errors[$extra_info[1]] = "<tr class='tbl-row'><td colspan='3'><strong>".$extra_info[1]." (".$extra_info[0]."):</strong></td><td colspan='4'>".$dt_str.TR_E;
                    $warning_count ++;
                }
            }
        }
    }
}

/**
 * Recursively parse a Zotero resource mapping string (dot-separated codes).
 * Determines the Heurist detail type ID, resource record type ID, and further nested mappings.
 *
 * @param string $dt_code The dot-separated Zotero mapping code string (e.g., "ZA.ZB.ZC").
 * @param int $rt_id The current Heurist record type ID context.
 * @param SimpleXMLElement|array|string|null $arr Optional attributes or code string for reporting.
 *                                              Passed to printMappingReport_dt.
 * @param array|null $extra_info Optional extra information for reporting. Expected structure:
 *                                [0] => Zotero record type ID (string)
 *                                [1] => Zotero record type name (string)
 *                                [2] => Zotero field ID (string)
 *                                [3] => Zotero field name (string)
 *                                Passed to printMappingReport_dt and recursive calls.
 * @return array|string An array representing the parsed mapping (e.g., [dt_id, res_rt_id, res_dt_id]
 *                      or [dt_id, res_rt_id, [nested_mapping]]) if successful,
 *                      or an error message string if parsing fails at any point.
 */
function getResourceMapping($dt_code, $rt_id, $arr=null, $extra_info=null){

    $arrdt = explode(".",$dt_code);
    if(count($arrdt) <= 2){
        return "Invalid resource mapping for id: ".$dt_code;
    }

    $dt_code = array_shift($arrdt);// $arrdt[0];
    $resource_rt_id = array_shift($arrdt);//$arrdt[1];//resource record type
    $resource_dt_id = $arrdt[0];

    $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);

    if($arr!=null){
        printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info);
    }else{
        printMappingReport_dt($dt_code, $rt_id, $dt_id, $extra_info);
    }

    if($dt_id == null){
        return "Unable to find the detail type for id: ".$dt_code;
    }

    $res_rt_id = ConceptCode::getRecTypeLocalID($resource_rt_id);

    printMappingReport_rt($resource_rt_id, $res_rt_id);

    if($res_rt_id == null){
        return "Resource record type not recognised for id: ".$resource_rt_id;
    }

    $res_dt_id = ConceptCode::getDetailTypeLocalID($resource_dt_id);

    if($res_dt_id == null){
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id, $extra_info);
        return "Detail type for resource (record pointer) not recognised for id: ".$resource_dt_id;
    }

    if(count($arrdt)>1){
        // next level
        $subres = getResourceMapping( implode(".",$arrdt), $res_rt_id, $extra_info );
        if(is_array($subres)){
            $res = array($dt_id, $res_rt_id, $subres);
        }else{
            return $subres;
        }
    }else{
        //pointer detail type and detail type in resource record
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id, $extra_info);
        $res = array($dt_id, $res_rt_id, $res_dt_id);
    }

    return $res;
}

/**
 * Add a value to a multi-dimensional array of unresolved resource record pointers.
 * This function is called recursively to handle nested pointer structures.
 * The $unresolved array is modified by reference.
 *
 * @param array &$unresolved The array storing unresolved pointers. Modified by reference.
 *                           Structure: $unresolved[detail_id][resource_rt_id][resource_dt_id] = value
 *                           or $unresolved[detail_id][resource_rt_id][] = value (for creators)
 * @param array $key An array defining the path to store the value.
 *                   Expected structure: [$detail_id, $resource_rt_id, $resource_dt_id_or_nested_key]
 *                   If $key[2] is an array, it triggers a recursive call.
 * @param mixed $value The value to assign (e.g., a string, or an array of creator details).
 * @return void
 */
function assignUnresolvedPointer(&$unresolved, $key, $value){

    $detail_id      = $key[0];
    $resource_rt_id = $key[1];
    $resource_dt_id = $key[2];

    if(!@$unresolved[$detail_id]){
        $unresolved[$detail_id] = array();
    }
    if(!@$unresolved[$detail_id][$resource_rt_id]){
        $unresolved[$detail_id][$resource_rt_id] = array();
    }
    if(is_array($resource_dt_id)){

        assignUnresolvedPointer($unresolved[$detail_id][$resource_rt_id], $resource_dt_id, $value);

    }else{
        if( is_array($value) ){ //this is creator  detail id in value
            array_push($unresolved[$detail_id][$resource_rt_id], $value);
        }else{
            $unresolved[$detail_id][$resource_rt_id][$resource_dt_id] = $value;
        }
    }
}

/**
 * Try to find an existing resource record based on its details, or create a new one if not found.
 * This function can be called recursively, especially when $recdetails represents multiple creators
 * or when a detail itself is a resource requiring its own record.
 *
 * @param mysqli $mysqli The mysqli database connection object.
 * @param int $record_type The Heurist record type ID for the resource record.
 * @param array $recdetails An array of details for the resource record.
 *                          Format: [detail_type_id => value, ...].
 *                          If $recdetails is a numerically indexed array (e.g., for creators),
 *                          the function calls itself for each item.
 * @param int $missing_pointers_count Count of unresolved pointers, used to modulate email notifications
 *                                    in addRecordFromZotero.
 * @return int|int[] The ID of the found/created resource record. If $recdetails represented
 *                   multiple creators, an array of their corresponding record IDs is returned.
 */
function createResourceRecord($mysqli, $record_type, $recdetails, $missing_pointers_count){

    global $alldettypes, $fi_dettype, $report_log, $outputLines;

    if(is_array($recdetails) && array_key_exists(0, $recdetails)){ //these are creators
        $recource_recids = array();
        foreach($recdetails as $idx=>$creator){
            array_push($recource_recids, createResourceRecord($mysqli, $record_type, $creator, $missing_pointers_count));
        }
        return $recource_recids;
    }

    $value_params = array('');
    $query = '';
    $details = array();
    $unusableValues = [];
    $dcnt = 1;
    $recource_recid = null; //returned value

    foreach($recdetails as $dt_id=>$recdata){  //detail id in main record


        if(!@$alldettypes['typedefs'][$dt_id]) { $unusableValues[]='Missing mapped field '.ConceptCode::getDetailTypeConceptID($dt_id); continue;}  //detail type not found

        $dt_type = $alldettypes['typedefs'][$dt_id]['commonFields'][$fi_dettype];
        if($dt_type=='enum' || $dt_type=='relationtype'){

            $trm_value = resolveTermValue($dt_id, $dt_type, $recdata, $record_type);
            if(!is_numeric($trm_value) || intval($trm_value)<=0){
                $report_log = $report_log."<br> term not found for ".$recdata;
                continue;
            }
            $value = intval($trm_value);

        }elseif($dt_type=='resource'){ //next level of reference

            if(!is_array($recdata)){

                $record_type_2 =  getConstrainedRecordType($dt_id);
                if($record_type_2){
                    $recdata = array(DT_NAME=>$recdata);
                    $value = createResourceRecord($mysqli, $record_type_2, $recdata, $missing_pointers_count);
                }else{
                    $report_log = $report_log."<br> resource (record pointer) record type unconstrained for detail type: ".$dt_id;
                    continue;
                }

            }else{
                $value = array();
                foreach($recdata as $record_type_2=>$recdata_nextlevel){ //recordtype

                    $value = createResourceRecord($mysqli, $record_type_2, $recdata_nextlevel, $missing_pointers_count);//return rec_id
                    break;

                }
            }
        }else{
            $value = $recdata;
            if($dt_id==DT_DATE){

                $originalDate = $value;
                $value = zoteroPublicationDate($value);
                if (!$value) { $unusableValues[] = 'Publication date "'.$originalDate.'" could not be converted to a date in '.($alldettypes['names'][$dt_id] ?? 'Date').' (Concept ID '.ConceptCode::getDetailTypeConceptID($dt_id).').'; }
                /*
                try{
                $t2 = new DateTime($value);
                $value = $t2->format(DATE_8601);
                } catch (Exception  $e){
                }
                */
            }
        }


        if($value){

            if (!is_array($value)) {
                $value = array("0"=>$value);
            }
            //query to search similar record

            $details['t:'.$dt_id] = $value;
            foreach($value as $idx=>$val){
                $value_params[0] .= 's';
                $value_params[] = $val;
                $query = $query." and r.rec_Id=d$dcnt.dtl_recId and d$dcnt.dtl_DetailTypeID=".intval($dt_id).
                " and d$dcnt.dtl_Value=? ";
                $dcnt++;
            }
        }
    }//for recdetails

    // try to find the existing record
    if($query){
        $qd = "";
        for ($idx=1; $idx<$dcnt; $idx++){ //count of details
            $qd = $qd.",recDetails d$idx ";
        }

        //find resouce record , if not found create new one
        // Aug 2026 (Dharma IDENK project): Indonesian names, among others, may only use a single name. 
        // If this is entered as a single name rather than a surname with missing given name, the 
        // person is identified as an organisation when exported and imported to Heurist. 
        // The solution is to convert the Organisation record to a Person record, but then it
        // needs to search both types to avoid creating a duplicate 
        if($record_type == RT_PERSON || $record_type == RT_ORGANISATION){
            $query = "select r.rec_ID from Records r $qd where r.rec_RecTypeID in (".
            intval(RT_PERSON).",".intval(RT_ORGANISATION).")".$query;
        }else{ // all other record types
            $query = "select r.rec_ID from Records r $qd where r.rec_RecTypeID=".intval($record_type).$query;
        }
          
        // The SELECT is already complete above; do not prepend another SELECT.
        //$res = $mysqli->query($query);
        $res = mysql__select_param_query($mysqli,$query,$value_params);
        if($res === false){
            $outputLines[] = errorDiv('Resource lookup failed in createResourceRecord (import/biblio/syncZotero.php), target record type '
                .intval($record_type).', MySQL error '.intval($mysqli->errno).': '.$mysqli->error
                .'. No replacement resource record was created.');
            return 0;
        }
        if($res){
            $row = $res->fetch_row();
            if($row){
                $recource_recid = intval($row[0]);
            }
        }
    }

    if (empty($details)) {
        $outputLines[] = errorDiv('Cannot create or identify '.htmlspecialchars($GLOBALS['rectypes']['names'][$record_type] ?? 'unknown type')
            .' (Concept ID '.ConceptCode::getRecTypeConceptID($record_type).'): '
            .htmlspecialchars(implode(' ', $unusableValues) ?: 'The supplied values produced no mapped target fields.')
            .' The bibliography record is saved, but this link is missing. Open the H-ID listed below, edit the record and select or create the target in the indicated link field.');
        return 0;
    }
    if(!($recource_recid>0)){
        //such record not found - create new one
        $recource_recid = addRecordFromZotero(null, $record_type, null, $details, null, false, $missing_pointers_count);
    }

    return intval($recource_recid);
}

/**
 * Find a specific XML element within a SimpleXMLElement object, possibly within a given namespace.
 * This function searches recursively through child elements.
 *
 * @param SimpleXMLElement $xml The SimpleXMLElement object to search within.
 * @param string|null $ns The XML namespace to search in. Null or empty if no namespace.
 * @param string $name The name of the XML element to find.
 * @return SimpleXMLElement|null The found SimpleXMLElement object, or null if the element is not found.
 */
function findXMLelement($xml, $ns, $name){

    if($ns){
        $children = $xml->children($ns, true);
    }else{
        $children = $xml->children();
    }


    foreach ($children as $f_gen){
        if($f_gen->getName()==$name){
            return $f_gen;
        }else{
            $res = findXMLelement($f_gen, $ns, $name);
            if($res){
                return $res;
            }
        }
    }

    return null;
}

/** Resolve only inside the field vocabulary and record-type term restrictions. */
/** Count each nonempty source value once per record type and Zotero field. */
function zoteroReportSpacing($html) {
    return str_replace('<p><a download=', '<p style="margin-top:18px"><a download=', $html);
}

function zoteroCountUnassigned($recordType, $sourceField) {
    global $rectypes, $arr_notmapped, $cnt_notmapped;
    $label = ($rectypes['names'][$recordType] ?? ('Record type '.$recordType)).' : '.ucfirst((string)$sourceField);
    $arr_notmapped[$label] = ($arr_notmapped[$label] ?? 0)+1;
    $cnt_notmapped++;
}

/** Find the single vocabulary shared by every member of an explicit term list. */
/** Preserve month precision for French Zotero publication dates. */
function zoteroPublicationDate($value) {
    $months=['janvier'=>1,'février'=>2,'fevrier'=>2,'mars'=>3,'avril'=>4,'mai'=>5,'juin'=>6,
        'juillet'=>7,'août'=>8,'aout'=>8,'septembre'=>9,'octobre'=>10,'novembre'=>11,'décembre'=>12,'decembre'=>12];
    $text=trim((string)$value);
    if (preg_match('/^([\p{L}]+)\s+(\d{4})$/u', $text, $match)) {
        $month=function_exists('mb_strtolower') ? mb_strtolower($match[1],'UTF-8') : strtolower($match[1]);
        if (isset($months[$month])) { return sprintf('%04d-%02d',intval($match[2]),$months[$month]); }
    }
    return Temporal::dateToISO($value,1);
}

function zoteroVocabularyRoot($system, array $termIDs) {
    $roots=[];
    foreach ($termIDs as $id) {
        $seen=[];
        while ($id && !isset($seen[$id])) {
            $seen[$id]=true;
            $parent=mysql__select_value($system->getMysqli(),'SELECT trm_ParentTermID FROM defTerms WHERE trm_ID='.intval($id));
            if (!$parent) { $roots[intval($id)]=true; break; }
            $id=intval($parent);
        }
    }
    return count($roots)===1 ? intval(array_key_first($roots)) : 0;
}

function resolveTermValue($fieldID, $dt_type, $value, $recordType = null) {
    global $allterms, $alldettypes, $rectypes, $fi_trmlabel, $system;
    if (!is_scalar($value) || trim((string)$value)==='') { return null; }
    $domain = $dt_type==='enum' ? 'enum' : 'relation';
    $indexes = $alldettypes['typedefs']['fieldNamesToIndex'];
    $field = $alldettypes['typedefs'][$fieldID]['commonFields'] ?? [];
    $tree = $field[$indexes['dty_JsonTermIDTree'] ?? -1] ?? null;
    $nonselectable = $field[$indexes['dty_TermIDTreeNonSelectableIDs'] ?? -1] ?? '';
    $getIDs = function($formatted) {
        if (!$formatted) { return []; }
        if (is_array($formatted)) { $formatted = json_encode($formatted); }
        return array_values(array_unique(array_filter(array_map('intval', getTermsFromFormat((string)$formatted)))));
    };
    $expand = function($formatted) use ($getIDs, $allterms) {
        $ids = $getIDs($formatted);
        if (!is_numeric($formatted)) { return $ids; }
        // A scalar vocabulary ID means its descendants, including references.
        $queue = [$ids[0] ?? 0]; $seen = []; $result = [];
        while ($queue) {
            $parent = array_pop($queue);
            if (!$parent || isset($seen[$parent])) { continue; }
            $seen[$parent] = true;
            foreach ($allterms['trm_Links'][$parent] ?? [] as $id) {
                $id = intval($id);
                if ($id !== intval($formatted)) { $result[$id] = $id; }
                $queue[] = $id;
            }
        }
        return array_values($result);
    };
    $isLanguage = $dt_type==='enum' && intval($fieldID)===intval(ConceptCode::getDetailTypeLocalID('2-965'));
    if ($isLanguage) { $tree = ConceptCode::getTermLocalID('2-496'); }
    // An unconfigured enum field is not permission to search every vocabulary.
    if (!$tree && $dt_type==='enum') { return null; }
    $lookup = $allterms['termsByDomainLookup'][$domain] ?? [];
    $allowed = $tree ? $expand($tree) : array_keys($lookup);
    $structIndexes = $rectypes['typedefs']['dtFieldNamesToIndex'] ?? [];
    $structure = $rectypes['typedefs'][$recordType]['dtFields'][$fieldID] ?? [];
    $filtered = $structure[$structIndexes['rst_FilteredJsonTermIDTree'] ?? -1] ?? null;
    if ($filtered) { $allowed = array_intersect($allowed, $expand($filtered)); }
    $excluded = $getIDs($nonselectable);
    foreach (['rst_TermIDTreeNonSelectableIDs','dty_TermIDTreeNonSelectableIDs'] as $key) {
        if (isset($structIndexes[$key])) { $excluded = array_merge($excluded, $getIDs($structure[$structIndexes[$key]] ?? '')); }
    }
    $allowed = array_diff($allowed, $excluded);
    $normalise = function($text) { $text = trim((string)$text); return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text); };
    $wanted = $normalise($value); $exact = []; $prefix = [];
    $codeIndex = $allterms['fieldNamesToIndex']['trm_Code'] ?? -1;
    foreach ($allowed as $id) {
        $term = $lookup[$id] ?? null;
        if (!$term) { continue; }
        $label = $normalise($term[$fi_trmlabel] ?? '');
        $code = $normalise($term[$codeIndex] ?? '');
        if ($label===$wanted || ($code!=='' && $code===$wanted)) { $exact[$id] = intval($id); }
        elseif (strlen($wanted)>=2 && strpos($label, $wanted)===0) { $prefix[$id] = intval($id); }
    }
    // Never pick an arbitrary first term when two allowed terms match.
    if ($exact) { return reset($exact); }
    // A prefix is not an exact incoming value: add it instead of rejecting it.
    if ($dt_type!=='enum') { return null; }
    $root = is_numeric($tree) ? intval($tree) : zoteroVocabularyRoot($system, $getIDs($tree));
    if (!$root) { return null; }
    // Reuse an exact term anywhere within this vocabulary, even when the
    // form's selectable list omitted it. Extend that list below.
    $id=0;
    foreach ($expand($root) as $existingID) {
        $existing = $lookup[$existingID] ?? [];
        if ($normalise($existing[$fi_trmlabel] ?? '')===$wanted
            || ($codeIndex>=0 && $normalise($existing[$codeIndex] ?? '')===$wanted)) { $id=intval($existingID); break; }
    }
    $parent = $root;
    if (!$isLanguage && $filtered && is_numeric($filtered)) { $parent = intval($filtered); }
    if (!$id) {
        $entity = new \hserv\entity\DbDefTerms($system);
        $entity->setData(['isfull'=>1,'fields'=>[['trm_Label'=>trim((string)$value),
            'trm_ParentTermID'=>$parent,'trm_Domain'=>'enum']]]);
        $ids = $entity->save();
        if ($ids===false || empty($ids)) { throw new RuntimeException('Cannot add term "'.trim((string)$value).'" to vocabulary Concept ID '.ConceptCode::getTermConceptID($parent).': '.$system->getErrorMsg()); }
        // Verify the stored row rather than trusting/casting a save response.
        $id = intval(mysql__select_value($system->getMysqli(),
            'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID=? AND trm_Label=? AND trm_Domain="enum"',
            ['is',$parent,trim((string)$value)]));
        if ($id<=0) { throw new RuntimeException('No valid stored term ID for "'.trim((string)$value).'" in vocabulary Concept ID '.ConceptCode::getTermConceptID($parent).'.'); }
    }
    // Explicit allowed term sets must also include the new term; otherwise it
    // would be stored outside the selectable field constraint on the next run.
    foreach ([['defDetailTypes','dty_', 'dty_ID', $fieldID,'dty_JsonTermIDTree',$tree],
              ['defRecStructure','rst_', 'rst_ID', $structure[$structIndexes['rst_ID'] ?? -1] ?? 0,'rst_FilteredJsonTermIDTree',$filtered]] as $constraint) {
        [$table,$prefixName,$idColumn,$rowID,$column,$format] = $constraint;
        if ($format && !is_numeric($format)) {
            $updated = $getIDs($format); $updated[]=$id;
            if (!$rowID && $table==='defRecStructure') { $rowID=mysql__select_value($system->getMysqli(),'SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID='.intval($recordType).' AND rst_DetailTypeID='.intval($fieldID)); }
            if (!$rowID || !mysql__insertupdate($system->getMysqli(),$table,$prefixName,[$idColumn=>intval($rowID),$column=>json_encode(array_values(array_unique($updated)))])) {
                throw new RuntimeException('Cannot include new term in the selectable list for field Concept ID '.ConceptCode::getDetailTypeConceptID($fieldID).'.');
            }
        }
    }
    // Refresh so subsequent items reuse the term and the normal column indexes.
    $allterms = dbs_GetTerms($system);
    $alldettypes = dbs_GetDetailTypes($system);
    $rectypes = dbs_GetRectypeStructures($system, null, 2);
    return $id;
}

/**
 * Get the primary constrained record type ID for a resource (record pointer) detail type.
 * If multiple record types are constrained, it returns the first one.
 *
 * @param int|string $resource_dt_id The detail type ID of the resource pointer.
 * @return string|null The constrained record type ID (as a string), or null if not set or not found.
 */
function getConstrainedRecordType($resource_dt_id){

    global $alldettypes, $fi_constraint;

    $pointer_constraint = @$alldettypes['typedefs'][$resource_dt_id]['commonFields'][$fi_constraint];
    if(strpos($pointer_constraint,",")>0){
        $pointer_constraint = explode(",", $pointer_constraint);
        $pointer_constraint = $pointer_constraint[0];
    }
    return $pointer_constraint;
}


/**
 * Saves a record (creates or updates) in the Heurist database based on Zotero item data.
 *
 * @param int|null $recId The Heurist record ID to update. If null or 0, a new record is created.
 * @param int $recordType The Heurist record type ID for the record.
 * @param string|null $rec_URL Optional URL to associate with the Heurist record.
 * @param array $details An array of details for the record, keyed by "t:<detail_type_id>".
 *                       This array may be modified to include the Zotero item ID.
 * @param string|null $zotero_itemid The Zotero item ID, to be stored in DT_ORIGINAL_RECORD_ID.
 * @param bool $is_echo If true, progress messages (Added/Updated ID) are printed.
 * @param int $record_count The total number of records being processed in the current batch,
 *                          passed to recordSave to potentially modulate email notifications.
 * @return int The Heurist record ID of the created or updated record. Returns 0 if the input
 *             $details array is empty or if $new_recid remains null after save attempt.
 */
function addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, $is_echo, $record_count){

    global $system, $rep_errors_only, $dt_SourceRecordID, $outputLines;

    $new_recid = null;

    if( !empty($details)){

        if($zotero_itemid){
            $details["t:".$dt_SourceRecordID] = array("0"=>$zotero_itemid);
        }
        // Reject raw labels and zero before recordSave's no-validation path.
        foreach ($details as $fieldKey=>$values) {
            if (strpos($fieldKey,'t:')!==0) { continue; }
            $fieldID=intval(substr($fieldKey,2));
            $type=mysql__select_value($system->getMysqli(),'SELECT dty_Type FROM defDetailTypes WHERE dty_ID='.$fieldID);
            if ($type!=='enum') { continue; }
            foreach ($values as $termID) {
                if (!preg_match('/^[0-9]+$/D',(string)$termID) || intval($termID)<=0
                    || !mysql__select_value($system->getMysqli(),'SELECT trm_ID FROM defTerms WHERE trm_ID='.intval($termID).' AND trm_Domain="enum"')) {
                    throw new RuntimeException('Invalid term value "'.(string)$termID.'" for field Concept ID '.ConceptCode::getDetailTypeConceptID($fieldID).'; the record was not saved with an invalid term.');
                }
            }
        }
        // 8) save rtecord
        $ref = null;

        //add-update Heurist record
        $record = array();
        $record['ID'] = $recId?$recId:0; //0 means insert
        $record['RecTypeID'] = $recordType;
        $record['AddedByImport'] = 2;
        $record['no_validation'] = true;
        $record['URL'] = $rec_URL;
        $record['ScratchPad'] = null;
        $record['details'] = $details;

        // Replace supplied Zotero fields, retaining unrelated local media and links.
        $out = recordSave($system, $record, true, false, 4, $record_count);//see recordModify.php

        if ( @$out['status'] != HEURIST_OK ) {
            $outputLines[] = "<div style='color:red'> Error: ".htmlspecialchars('Record save failed in addRecordFromZotero (import/biblio/syncZotero.php). Zotero item: '
                .($zotero_itemid ?: '(resource record)').'; Heurist record: '.($recId ?: '(new)')
                .'; record type: '.$recordType.'. '.($out['message'] ?? 'No error message returned')).DIV_E;
        }else{

            $new_recid = intval($out['data']);

            if($is_echo){
                $outputLines[] = '['.($new_recid==$recId?"Updated":"Added")."&nbsp;Id&nbsp".$new_recid.']<br>';
            }


            if(!$rep_errors_only){
                if (@$out['warning']) {
                    $outputLines[] = "<div style='color:red'>Warning: ".htmlspecialchars(implode(";",$out["warning"])).DIV_E;
                }
            }

        }

    }
    return intval($new_recid);
}

/**
 * Checks if a variable is null or an empty string (after trimming whitespace).
 * Inspired by a function often named isNullOrEmptyString.
 *
 * @param mixed $question The variable to check. Intended primarily for strings or null.
 *                        Behavior with other types might vary (e.g., trim() warning).
 * @return bool True if the variable is null, or an empty or whitespace-only string; false otherwise.
 */
function is_empty($question){
    $ret = (!isset($question) || trim($question)==='');
    return $ret;
}

function prepareUpdateTable($isFailure = false){

    global $rectypes, $cnt_report, $cnt_added, $cnt_updated, $arr_ignored, $outputLines, $terminatedByUser, $enumerationComplete;

    $status = $terminatedByUser ? 'Stopped' : (!$enumerationComplete ? 'Interrupted' : ($isFailure ? 'completed with records requiring attention' : 'completed'));
    $outputLines[] = "<p><b>Synchronisation {$status}</b></p>";

    $outputLines[] = TABLE_S.'<tr><td>&nbsp;</td><td>added</td><td>updated</td></tr>';
    foreach($cnt_report as $rty_ID => $cnt){
        $outputLines[] = TR_S.htmlspecialchars($rectypes['names'][$rty_ID])
        .'</td><td align="center">'.composeLinkForAllIds($cnt['added'])
        .'</td><td align="center">'.composeLinkForAllIds($cnt['updated']).TR_E;
    }

    $outputLines[] = TABLE_E.'<div><br>Records added : '.composeLinkForAllIds($cnt_added).DIV_E;

    $outputLines[] = '<div style="margin-bottom:18px">Records updated: '.composeLinkForAllIds($cnt_updated).DIV_E;

    if(!empty($arr_ignored)){
        $outputLines[] = '<details><summary>Unmapped records: '.count($arr_ignored).' — show list</summary>'.implode('', $arr_ignored).'</details>';
    }
}

function prepareErrors(){

    global $system, $arr_ignored_by_type, $arr_empty, $arr_notfound, $arr_notmapped,
    $cnt_ignored, $cnt_empty, $cnt_notfound, $cnt_notmapped, $outputLines;

    $tot_erros = $cnt_ignored + $cnt_empty + $cnt_notfound + $cnt_notmapped;

    $err_msg = 'Zotero Synching has encountered issues in Database: ' . $system->dbname();
    $line_sep = '<br>- ';

    if($tot_erros > 0){

        // Ingored
        $outputLines[] = '<div style="color:red">';
        if($cnt_ignored > 0){
            $outputLines[] = '<br>Zotero entries that are not mapped to Heurist record types: '.intval($cnt_ignored).TABLE_S;
            $outputLines[] = '<br>You should obtain the record types from one of the curated templates using Design > Browse templates or ask the' 
                 .'<br>Heurist team to define and map them if they are not available, by submitting a bug/improvement ticket (top of page).';
            foreach($arr_ignored_by_type as $itemtype => $cnt){
                $outputLines[] = TR_S.htmlspecialchars($itemtype).TD.intval($cnt).TR_E;
            }
            $outputLines[] = '</table>';

            $err_msg .= "\nZotero entries that are not mapped to Heurist record types: {$cnt_ignored}";
        }

        // Empty
        if($cnt_empty > 0){
            $outputLines[] = "<br>Zotero entries ignored because there are no properly mapped keys: {$cnt_empty}";
            $outputLines[] = "<div style ='color:red; padding-left:20px'>- ".implode($line_sep, $arr_empty).DIV_E;

            $err_msg .= "\nZotero entries ignored because there are no properly mapped key: {$cnt_empty}";
        }

        // Not Found
        if($cnt_notfound > 0){
            $outputLines[] = "<br>Zotero keys are mapped to field types that are not found in this database: {$cnt_notfound}";
            $outputLines[] = "<div style ='color:red; padding-left:20px'>- ".implode($line_sep, $arr_notfound).DIV_E;

            $err_msg .= "\nZotero keys are mapped to field types that are not found in this database: {$cnt_notfound}";
        }
        $outputLines[] = DIV_E;

        // Not Mapped
        $outputLines[] = '<div style="color:black">';
        if($cnt_notmapped > 0){
            $outputLines[] = '<p style="margin-top:18px"><strong>Unassigned data values:</strong></p><div style="padding-left:20px">';
            foreach ($arr_notmapped as $label=>$count) { $outputLines[] = htmlspecialchars($label).' n='.intval($count).'<br>'; }
            $outputLines[] = '</div>';
            $err_msg .= "\nUnassigned data values: ".json_encode($arr_notmapped);
        }
        $outputLines[] = DIV_E;

        $system->addError('Zotero Synchronisation Warnings', "Zotero Synchronisation has reported {$tot_erros} warnings", $err_msg);

        $outputLines[] = '<span><br>If you think the Zotero import needs updating or wish to provide additional information please create a ticket - link at top of page.</span>';

        // Warnings remain in the report; no modal obscures the completion result.
    }
}

/**
 * @param mixed $mysqli
 * @param mixed $unresolved_pointers
 * @return void
 */
function handleUnresolvedPointers($mysqli, $unresolved_pointers){

    global $outputLines, $zoteroDiagnosticStage, $syncStatus, $recordKeys, $recordTitles, $alldettypes, $rectypes;
    $success = true;

    // try to find 'unresolved pointers
    // $rec_id - record to be updated
    // $dt_id - field that must contain pointer to resource
    // $resource_rt_id - record type for resouce record
    // $resource_dt_id

    $missing_pointers_count = count($unresolved_pointers);
    foreach($unresolved_pointers as $rec_id => $pntdata){

        // pntdata = array of  detail id in main record => record id of resource =>
        //           detail id in resource OR simialr array for next level  => value
        //  $dt_id=>$resource_rt_id=>$resource_details

        $rec_id = intval($rec_id);
        if(!isPositiveInt($rec_id)){
            $rec_id = htmlspecialchars($rec_id);
            $outputLines[] = "Invalid record ID provided for handling pointers: {$rec_id}";
            $success = false;
            continue;
        }

        $bibliographyType = intval(mysql__select_value($mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID='.$rec_id));
        $recordErrorStart = count($outputLines);
        $recordFailed = false;
        foreach($pntdata as $dt_id => $recdata){  //detail id in main record

            foreach($recdata as $resource_rt_id => $resource_details){ //recordtype

                $zoteroDiagnosticStage = 'Resolving resource for bibliography record '.intval($rec_id).', field '.intval($dt_id).', target record type '.intval($resource_rt_id);
                $resourceErrorStart = count($outputLines);
                try {
                    $recource_recid = createResourceRecord($mysqli, $resource_rt_id, $resource_details, $missing_pointers_count);
                } catch (Throwable $e) {
                    $outputLines[] = htmlspecialchars(preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $e->getMessage()));
                    $recource_recid = 0;
                }

                if(!is_array($recource_recid)){
                    $recource_recid = ["0" => $recource_recid];
                }

                foreach($recource_recid as $idx => $res_rec_id){

                    $res_rec_id = intval($res_rec_id);
                    if(!isPositiveInt($res_rec_id)){
                        $res_rec_id = htmlspecialchars($res_rec_id);
                        $requested = is_array($resource_details) && array_key_exists(0, $resource_details)
                            ? ($resource_details[$idx] ?? $resource_details) : $resource_details;
                        $sourceValues = [];
                        array_walk_recursive($requested, function($value) use (&$sourceValues) { if (is_scalar($value) && (string)$value !== '') { $sourceValues[] = (string)$value; } });
                        $fieldName = $alldettypes['names'][$dt_id] ?? 'Unknown field';
                        $targetName = $rectypes['names'][$resource_rt_id] ?? 'Unknown record type';
                        $sourceTypeName = $rectypes['names'][$bibliographyType] ?? 'Unknown record type';
                        $cause = trim(strip_tags(implode(' ', array_splice($outputLines, $resourceErrorStart))));
                        $message = 'Could not find or create the link for H-ID '.$rec_id
                            .' ('.$sourceTypeName.', Concept ID '.ConceptCode::getRecTypeConceptID($bibliographyType).'), title "'.($recordTitles[$rec_id] ?? '').'"'
                            .', Zotero key '.($recordKeys[$rec_id] ?? 'unknown').'. Field: '.$fieldName.' (Concept ID '.ConceptCode::getDetailTypeConceptID($dt_id).')'
                            .'; target: '.$targetName.' (Concept ID '.ConceptCode::getRecTypeConceptID($resource_rt_id).'). Source value: '.implode(' / ', $sourceValues).'. '
                            .($cause ?: 'No usable target record was produced; check the mapped name fields and required target fields.');
                        $outputLines[] = '<p class="ui-state-error">'.htmlspecialchars($message).'</p>';
                        $success = false;
                        $recordFailed = true;
                        continue;
                    }

                    //update main record
                    $insertValues = ['dtl_RecID' => $rec_id, 'dtl_DetailTypeID' => $dt_id, 'dtl_Value' => $res_rec_id, 'dtl_AddedByImport' => 1];
                    // A full reconciliation may encounter a pointer already present.
                    $existing = mysql__select_value($mysqli, 'SELECT dtl_ID FROM recDetails WHERE dtl_RecID='
                        .$rec_id.' AND dtl_DetailTypeID='.intval($dt_id).' AND dtl_Value='.$res_rec_id.' LIMIT 1');
                    $result = $existing ?: mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $insertValues);

                    if(!isPositiveInt($result)){

                        $recTitles = mysql__select_list2($mysqli, "SELECT rec_Title FROM Records WHERE rec_ID IN ({$rec_id},{$res_rec_id})");
                        $recTitles[$rec_id] ??= "Rec #<strong>{$rec_id}</strong>";
                        $recTitles[$res_rec_id] ??= "Rec #<strong>{$res_rec_id}</strong>";

                        $msg = "Failed to link target H-ID {$res_rec_id} (".htmlspecialchars($rectypes['names'][$resource_rt_id] ?? 'Unknown type').") to H-ID {$rec_id} (".htmlspecialchars($rectypes['names'][$bibliographyType] ?? 'Unknown type')."), field ".htmlspecialchars($alldettypes['names'][$dt_id] ?? 'Unknown field')." (Concept ID ".ConceptCode::getDetailTypeConceptID($dt_id).").";
                        $msg .= !empty($result) ? ", reason: {$result}" : '';
                        $outputLines[] = $msg;
                        $success = false;
                        $recordFailed = true;
                    }
                }
            }
        }
        $key = $recordKeys[$rec_id] ?? null;
        if ($key) {
            if ($recordFailed) {
                $syncStatus->fail($key, $recordTitles[$rec_id] ?? '', $rec_id,
                    strip_tags(implode(' ', array_slice($outputLines, $recordErrorStart))));
            } elseif (($syncStatus->state['failed_records'][$key]['category'] ?? '') === 'pending') { $syncStatus->complete($key); }
        }
    }
    return $success;
}

/**
 * @param string $apiKey
 * @param string $type
 * @param int $id
 * @param int $lastSync
 * @param string|null $diagnostic Safe request failure description (no API key).
 * @return array<bool|int|null>
 */
function getZoteroHeaders($apiKey, $type, $id, $lastSync = 0, &$diagnostic = null){

    $diagnostic = null;

    $sortBy = $type === 'groups' ? 'sort=desc' : 'direction=desc';
    $URL = "https://api.zotero.org/{$type}/{$id}/items/top?key={$apiKey}&format=atom&content=none&start=0&limit=1&order=dateModified&{$sortBy}";
    if($lastSync > 0){
        $URL .= "&since={$lastSync}";
    }

    $curlHandle = curl_init($URL);

    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true
        //,CURLOPT_NOBODY => true doesn't return last modified header
    ];

    $useProxy = defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE && defined('HEURIST_HTTP_PROXY');
    if($useProxy){
        $curlOptions[CURLOPT_PROXY] = HEURIST_HTTP_PROXY;
        if(defined('HEURIST_HTTP_PROXY_AUTH')){
            $curlOptions[CURLOPT_PROXYUSERPWD] = HEURIST_HTTP_PROXY_AUTH;
        }
    }

    curl_setopt_array($curlHandle, $curlOptions);

    $results = curl_exec($curlHandle);
    $error = curl_error($curlHandle);
    $httpStatus = intval(curl_getinfo($curlHandle, CURLINFO_HTTP_CODE));
    if($error || $results === false || $httpStatus !== 200){
        // Do not expose the URL or cURL message: either can contain the API key.
        $diagnostic = "Zotero checkpoint request failed: {$type}/{$id}/items/top, since=".intval($lastSync)
            .", HTTP {$httpStatus}, cURL error ".curl_errno($curlHandle)." (getZoteroHeaders).";
        curl_close($curlHandle);
        return [false, false];
    }

    $headerSize = curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);

    curl_close($curlHandle);
    $headers = substr($results, 0, $headerSize);
    $newline = strpos($headers, "\r") !== false ? "\r\n" : "\n";
    $headers = explode($newline, trim($headers));

    $totalResults = null;
    $syncID = null;

    foreach($headers as $header){

        $header = strtolower($header);
        if(strpos($header, 'total-results') !== false){
            $totalResults = preg_replace('/total\-results: ?/', '', $header);
            $totalResults = intval($totalResults);
        }elseif(strpos($header, 'last-modified-version') !== false){
            $syncID = preg_replace('/last\-modified\-version: ?/', '', $header);
            $syncID = intval($syncID);
        }

        if($totalResults !== null && $syncID !== null){
            break;
        }
    }

    if($syncID === null || $totalResults === null){
        $diagnostic = 'Zotero checkpoint response is missing '.($syncID === null ? 'Last-Modified-Version' : 'Total-Results')
            ."; {$type}/{$id}/items/top (getZoteroHeaders).";
        return [false, false];
    }
    return [$totalResults, $syncID];
}

/**
 * @param mixed $data
 * @param mixed $status
 * @return never
 */
function exitServerCall($data, $status){

    global $system, $zoteroDiagnosticFinished, $zoteroDiagnosticBufferLevel;

    // Clean buffered warnings/HTML before emitting the AJAX JSON response.
    if(isset($zoteroDiagnosticBufferLevel)){
        while(ob_get_level() > $zoteroDiagnosticBufferLevel){ ob_end_clean(); }
    }
    $zoteroDiagnosticFinished = true;
    if($status !== HEURIST_OK){
        $system->errorExitApi($data, $status, false);
    }

    print json_encode(['data' => $data, 'status' => $status], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if($syncingStep){
    exit;
}
?>

</body>

</html>
