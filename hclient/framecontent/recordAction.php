<?php

/**
* Page for popup modal dialog to perform various actions related to records
*
*    add/edit/delete details
*    set relationship
*    bookmark/unbookmark
*    assign personal tags
*    assign wg tags
*    rating
*    ownership(who can edit), access(visibility)
*
*    merge
*    delete
*    mailing list based on records
*
*    STRUCTURE:
*    header that describes the action
*    selector of records: all, selected, by record type
*    widget to enter data
*    request to server
*    results
*        given
*        processed
*        rejected (rights)
*        error
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

define('LOGIN_REQUIRED',1);

require_once(dirname(__FILE__)."/initPage.php");

//verify parameter action
$action_type = @$_REQUEST['action'];
$allowed_actions = array('add_record','add_detail','replace_detail','delete_detail','merge_term','rectype_change','ownership');
if(!in_array($action_type, $allowed_actions)){
    //@todo - it does not work since initPage already produces some output
    // need to call this piece of code with callback function in initPage after system itit
    header('Location: '.ERROR_REDIR.'?msg=Action is not defined or not allowed');
    exit();
}

?>
<!-- date picker -->        
<script type="text/javascript" src="<?php echo PDIR;?>external/js/jquery.calendars-1.2.1/jquery.calendars.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/jquery.calendars-1.2.1/jquery.calendars.plus.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/jquery.calendars-1.2.1/jquery.calendars.picker.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/js/jquery.calendars-1.2.1/jquery.calendars.picker.css">

<script type="text/javascript" src="recordAction.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>

<script type="text/javascript">
    // Callback function on page init complete
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
    
.calendars-jumps .calendars-cmd-prev, .calendars-jumps .calendars-cmd-next { width: 20%; }
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
<body style="overflow:hidden;background:white">

    <div id="div_parameters" class="popup_content_div">

        <div id="div_header" style="padding: 0.2em; min-width: 600px;">

        </div>

        <fieldset>
            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_record_scope">Records scope:</label></div>
                <select id="sel_record_scope" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
            <div id="div_sel_fieldtype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_record_scope">Field to modify:</label></div>
                <select id="sel_fieldtype" class="ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>

            <div id="div_sel_rectype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_recordtype">Convert to record type:</label></div>
                <select id="sel_recordtype" style="max-width:30em"></select>
                
                <div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>
                <div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>
            </div>

            <div id="div_sel_ownership" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_Ownership">Record is editable by:</label></div>
                <select id="sel_Ownership" style="max-width:30em;margin-bottom: 1em;"></select>
            </div>
            
            <div id="div_sel_access" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_Ownership">Access outside this group:</label></div>
                
                <div class="detailRow" style="padding-bottom:1em;">
                    <label><input type="radio" name="rb_Access" value="hidden" id="rb_Access-hidden">
                    Only members of this group can view the record</label>
                </div>
                <div class="detailRow" style="padding-bottom:1em;">
                    <label><input type="radio" name="rb_Access" value="viewable" id="rb_Access-viewable">
                    Any logged-in user can view the record</label>
                </div>
                <div class="detailRow" style="padding-bottom:1em;">
                    <label><input type="radio" name="rb_Access" value="pending" id="rb_Access-pending">
                    Flagged for external publication, any logged-in user</label>
                    <div style="margin-left: 20px; font-style: italic;">No effect on existing external views - hml, html etc.</div>
                    <div style="margin-left: 20px; font-style: italic;">Can be auto-set whenever a public record is edited.</div>
                </div>
                <div class="detailRow" style="padding-bottom:20px">
                    <label>
                    <input type="radio" name="rb_Access" value="public" id="rb_Access-public">
                    Published - written automatically to external views</label>
                </div>
            </div>
            
            <div id="div_sel_access2" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_Ownership">Outside this group record is:</label></div>
            
                            <select name="sel_Access" id="sel_Access" style="min-width: 3   00px;">
                                <option value="hidden">Hidden (restricted to owners)</option>
                                <option value="viewable" selected>Viewable (logged-in users only)</option>
                                <option value="pending">Pending (marked for potential publication)</option>
                                <option value="public">Public (automatically published to hml etc.)</option>
                            </select>
            </div>
            

            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding:16px;"><label for="cb_add_tags">Tag affected records (auto-generated tag)</label></div>
                <input id="cb_add_tags" type="checkbox" class="text ui-widget-content ui-corner-all">
            </div>
            
            <div id="div_more_options" style="padding: 0.2em; width: 100%;display:none">
                <div id="btn_more_options" style="cursor:pointer;float:right;color:#7D9AAA;padding:2px 4px;">show more options</div>
            </div>

            <hr class="add_record" style="display:none;color:#6A7C99"/>
            
            <div style="padding: 0.2em; min-width: 600px;display:none;border-top:1px solid #6A7C99" class="add_record">
                <div class="header" style="padding:0 16px;padding-top:20px;text-align:left"><label>ADVANCED</label></div>
            </div>
            
            <div id="div_sel_tags" style="padding: 0.2em; min-width: 600px;display:none;" class="add_record">
                <div class="header" style="padding:0 16px;"><label>Add these tags:</label></div>
                <div id="div_sel_tags2" style="padding-bottom:20px"></div>
            </div>
            <div id="div_add_link" style="padding: 0.2em; min-width: 600px;display:none;" class="input add_record">
                <div class="header" style="padding: 0 16px;vertical-align:top">
                        <label for="txt_add_link">Hyperlink this URL in a web page, browser bookmark or desktop shortcut to provide one-click addition of records:</label>
                </div>
                <textarea id="txt_add_link" readonly
                    onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);"
                    class="text ui-widget-content ui-corner-all" rows=8 style="width:110ex"></textarea>
            </div>
            
        </fieldset>

        <div id="div_widget" style="padding: 0.2em; width: 100%;">
            <fieldset>
            </fieldset>
        </div>

    </div>

    <div id="div_result" class="content_div" style="display: none;">
        RESULT
    </div>
    
    <div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix popup_buttons_div">
        <div class="ui-dialog-buttonset">
            <div id="btn-ok" class="ui-state-disabled">OK</div>
            <div id="btn-cancel">Cancel</div>
        </div>
    </div>

    <div class="loading" style="width:100%;height:100%;display: none;">
    </div>
</body>
</html>
