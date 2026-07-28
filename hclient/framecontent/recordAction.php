<?php
/**
* recordAction.php - Provides the HTML structure and client-side script initialization for the record batch actions dialogue.
*
* This file defines the user interface for performing batch operations on records,
* such as changing record types, or adding, replacing, or deleting field values.
* It includes the `recordAction.js` script which handles the dialog's logic and interactions,
* and other necessary JavaScript components for editing inputs. The specific action to be
* performed is typically passed as a URL parameter.
* 
* @project     Heurist academic knowledge management system
* @package  hclient\framecontent
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       5.0
*/

/*
workflow

resultList.js -> resultListMenuSelected.html/resultListMenu.js

detailBatchEditPopup opens either

to convert
    recordAddLink - define links/relationship -> RecordMgr.batch_details for links, RecordMgr.save new record for relationship
    recordAction - change details in batch    -> RecordMgr.batch_details

    recordAccess - define ownership and access rights  -> h3 executeAction("set_wg_and_vis") or just close window
    recordAdd - define record add initial preferences  -> save prefs and RecordMgr.add -> record_edit.php -> recordModify.php

todo
*    recordRate - assign record rating -> EntityMgr.doRequest -> dbUsrBookmarks.php
*    recordBookmark - assign/remove record bookmark -> EntityMgr.doRequest -> dbUsrBookmarks.php

*    recordDelete -> RecordMgr.remove or (EntityMgr.doRequest) -> dbRecords.php
     recordTitle - update titles -> RecordMgr.remove (or EntityMgr.doRequest) -> dbRecords.php use TitleMask class

*    recordTag - add/remove tags in batch   -> EntityMgr.doRequest ->  dbUsrTags (use dbUsrBookmarks)

    recordNotify - send email about record

on server side - controller
RecordMgr.add, save, duplicate, remove  -> record_edit.php -> recordModify.php

RecordMgr.batch_details -> record_batch.php  -> recordsBatch

*/
define('LOGIN_REQUIRED',1);

require_once 'initPage.php';

//verify parameter action
$action_type = @$_REQUEST['action'];
$allowed_actions = array('add_detail','replace_detail','delete_detail','rectype_change',
            'extract_pdf','url_to_file','local_to_repository','reset_thumbs',
            'case_conversion','nl2br','translation','increment','iiif_thumbs');
if(!in_array($action_type, $allowed_actions)){
    //@todo - it does not work since initPage already produces some output
    // need to call this piece of code with callback function in initPage after system itit
    redirectURL(ERROR_REDIR.'?msg=Action is not defined or not allowed');
    exit;
}

?>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/admin/progressReport.js"></script>
<script type="text/javascript" src="recordAction.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>


<script type="text/javascript">
    /**
     * Callback function executed when the page initialization is complete.
     * If initialization was successful, it retrieves URL parameters (action, scope, ft, value)
     * and instantiates the hRecordAction JavaScript object to manage the record action UI and logic.
     *
     * @param {boolean} success - Indicates whether the page initialization (via initPage.php) was successful.
     */
    function onPageInit(success){
        if(success){

            var action = window.hWin.HEURIST4.util.getUrlParameter('action', window.location.search);
            var scope = window.hWin.HEURIST4.util.getUrlParameter('scope', window.location.search);
            var field_type = window.hWin.HEURIST4.util.getUrlParameter('ft', window.location.search);
            var field_value = window.hWin.HEURIST4.util.getUrlParameter('value', window.location.search);

            var recordAction = new hRecordAction(action, scope, field_type, field_value);
        }
    }
</script>
<style type="text/css">
    #div_result > div > span {
        min-width:200px;
        display:table-cell;
        font-size:1.1em;
    }

.calendars-jumps .calendars-cmd-prev, .calendars-jumps .calendars-cmd-next { width: 20%;}
.calendars-month-header, .calendars-nav, .calendars-month th,
.calendars-month-header select, .calendars-ctrl, .calendars a{
    background-color:lightgray;
    color:black;
}
.calendars-month, .calendars{
    border-color:lightgray;
}
.calendars-month table, .calendars-month-header select{
    font-size: 1.2em;
}
</style>
</head>

<!-- HTML -->
<body style="overflow:hidden;" class="ui-heurist-explore">

    <div id="div_parameters" class="popup_content_div" style="overflow-y:auto;overflow-x:hidden;bottom:3.5em;">

        <div id="div_header" style="padding: 0.2em; min-width: 600px;">

        </div>

        <fieldset><legend style="display:none"></legend>
            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_record_scope">Records scope:</label></div>
                <select id="sel_record_scope" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
            <div id="div_sel_fieldtype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_fieldtype">Field to modify:</label></div>
                <select id="sel_fieldtype" class="ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>

            <div id="div_sel_rectype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_recordtype">Convert to record type:</label></div>
                <select id="sel_recordtype" style="max-width:30em"></select>

                <div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>
                <div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>
            </div>


            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding:16px;"><label for="cb_add_tags">Tag affected records (auto-generated tag)</label></div>
                <input id="cb_add_tags" type="checkbox" class="text ui-widget-content ui-corner-all">
            </div>

        </fieldset>

        <div id="div_widget" style="padding-left: 16px; width: 100%;">
            <fieldset><legend style="display:none"></legend>
            </fieldset>
        </div>

    </div>

    <div id="div_progress" class="content_div" style="display:none; min-height:140px; padding:16px;">
    </div>

    <div id="div_result" class="content_div" style="display: none;">
        RESULT
    </div>

    <div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix popup_buttons_div" style="padding:10px">
        <div class="ui-dialog-buttonset">
            <div id="btn-ok" class="ui-state-disabled">OK</div>
            <div id="btn-cancel">Cancel</div>
        </div>
    </div>

    <div class="loading" style="width:100%;height:100%;display: none;">
    </div>
</body>
</html>
