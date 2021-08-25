<?php

/**
*  Email Users of any Heurist database located on this server, requires a Heurist Database + System Administrator password
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

define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');
require_once(dirname(__FILE__).'/systemEmailFunc.php');

if (@$_REQUEST["exportCSV"] == "true") {

    getCSVDownload($_REQUEST);
    exit();
}

if ( !isset($_REQUEST['db']) && $system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <h3> A Heurist database and Server Manager password are required to enter this function </h3>

    <?php
    exit();
} else if (isset($_REQUEST['databases']) && isset($_REQUEST['users']) && isset($_REQUEST['emailOutline']) && isset($_REQUEST['db']) && isset($_REQUEST['pwd'])) {

    if($system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions)){

        echo "The system administrator password is invalid";
        exit();
    } else {

        sendSystemEmail($_REQUEST);        
        exit();
    }
}

$mysqli = $system->get_mysqli();

$db_workgroups = array();
$notes = array();

$has_notes = false;

$query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE `SCHEMATA`.`SCHEMA_NAME` LIKE 'hdb_%' ORDER BY `SCHEMATA`.`SCHEMA_NAME` COLLATE utf8_general_ci";

$db_list = $mysqli->query($query);
if (!$db_list) {  
    print "Unable to retrieve list of databases on server, Error => " .$mysqli->error. ", Query => " .$query;  
    return; 
}

while($db = $db_list->fetch_row()){

    $workgroups = array();

    $query = "SELECT ugr_ID, ugr_Name FROM ". $db[0] .".sysUGrps WHERE ugr_Type = 'workgroup'";

    $workgroup_list = $mysqli->query($query);
    if (!$workgroup_list) {

        print "Unable to retrieve workgroups from db => " . $db[0] . ", Error => " . $mysqli->error. ", Query => " .$query;
        return;
    }

    while($wg = $workgroup_list->fetch_row()){

        $workgroups[$wg[0]] = $wg[1];
    }

    $db_workgroups[$db[0]] = (count($workgroups) > 1 ? $workgroups : null);
}

$current_db = "hdb_" . $_REQUEST['db'];

$note_rectype_id = ConceptCode::getRecTypeLocalID("2-3");
if (empty($note_rectype_id)) {

    print "Unable to retrieve the id for the Notes record type";
}

$shortsum_detiltype_id = ConceptCode::getDetailTypeLocalID("2-3");
if (empty($shortsum_detiltype_id)) {

    print "Unable to retrieve the id for the short summary detail type";
    return;
}

$query = "SELECT rec_ID, rec_Title FROM Records WHERE rec_RecTypeID = " 
            . $note_rectype_id 
            . " AND rec_Title NOT LIKE 'Heurist System Email Receipt%' AND rec_Title <> ''";

$notes_list = $mysqli->query($query);
if (!$notes_list) { 
    print "Either unable to retrieve Note records from the current database, Error => " . $mysqli->error. ", Query => " .$query; 
    return; 
}

while($note = $notes_list->fetch_row()){

    if(empty($note[1])) {
        continue;
    }

    $query = 'SELECT dtl_Value FROM recDetails WHERE dtl_RecID = '
            . $note[0] .' AND dtl_DetailTypeID = ' . $shortsum_detiltype_id;

    $note_val = $mysqli->query($query);
    if(!$note_val) {

        print "Unable to retrieve details of note id => " . $note[0] . ", Error => " . $mysqli->error;
        return;
    }

    if ($val = $note_val->fetch_row()){
        if (empty($val[0])) {
            continue;
        }
        $notes[$note[0]] = array($note[1], $val[0]);
    }

    $has_notes = true;
}

if(!$has_notes || empty($notes)) {
    print "<br><br>This function sends bulk emails based on text in a selected Notes record<br><br>"
    . "The Notes record to be used must contain a title field and a short summary field - the latter will be used as the email's body.<br><br>" 
    . $_REQUEST['db'] . " contains no valid Notes records. Please create a Notes record with your text using ##...## markers for values to be inserted.<br><br>"
    . "Placeholders that will be replaced with proper values (case insensitive):<br><br>"
    . "##firstname## &rarr; User's First Name,<br>"
    . "##lastname## &rarr; User's Last Name,<br>" 
    . "##email## &rarr; User's Email,<br>" 
    . "##database## &rarr; Database Name,<br>" 
    . "##dburl## &rarr; Database URL,<br>" 
    . "##record## &rarr; Record Count, and<br>" 
    . "##lastmodified## &rarr; Date of the Last Modified Record<br>";
    return;
}
?>  
<html>
    
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Heurist System Email</title>

        <!-- Style Sheets -->
        <!--<link rel="stylesheet" type="text/css" href="../../external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />-->
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="../../external/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css" />

        <link rel="stylesheet" type="text/css" href="../../h4styles.css">
        <link rel="stylesheet" type="text/css" href="../../h6styles.css">

        <!-- Scripts -->
        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="../../hclient/core/utils.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_msg.js"></script>

        <!-- Inner Styling and Script -->
        <style type="text/css">
            
            .fieldsets {
                border: 2px groove threedface;

                margin: 10px 5px;
                padding: 5px 10px 15px;
            }

            .input-num {
                margin: 5px 5px 0;
            }

            #dbSelection.fieldsets {
                max-width: 85%;
                max-height: 190px;

                overflow-y: auto;
            }

            #userSelection.fieldsets {
                display: inline-block;

                width: 23%;
            }

            #recSelection.fieldsets {
                display: inline-block;

                width: 26%;
                max-height: 135px;
            }

            #authenContainer.fieldsets {
                display: inline-block;
        
                border: 0;
            }

            #dbSelection .label, 
            #userSelection .label {
                font-size: 13px;

                color: #000 !important;
                background: #F6F6F6 !important;

                margin: 5px 10px 5px 0;
            }

            .non-selectable .ui-checkboxradio-icon-space {
                margin-right: 5px;
            }

            input[type=number] {

                font-size: 1.2em;

                width: 50px;
            }

            textarea#emailContent:focus,
            input#sm_pwd:focus {
                border-color: rgb(133, 133, 133) !important;
            }

            #recModifiedLogic-button {
                min-width: 8em !important;

                margin-right: 5px;
            }

            #recModifiedLogic-button span.ui-selectmenu-text {
                padding: 0.1em 0.1em 0.2em 0.2em;
            }

            #recModifiedSel-button {
                min-width: 7em !important;

                margin-left: 5px;
            }

            #recModifiedSel-button > .ui-selectmenu-text {
                padding-right: 0px;
            }

            /* Active State CSS */
            span.ui-button:active {
                color: #000 !important;
                background: #F6F6F6 !important;
            }

            div.ui-state-active {
                color: #000 !important;
                background: #DDDDDD !important;
            }

            span.ui-selectmenu-text {
                background-color: #f6f6f6 !important;
            }

            /* Misc */
            .invalid-input {
                border-color: #ff3e00;
            }

            .non-selectable {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;        
            }

            .error-msg {
                color: #FF3E00 !important;
            }

            .row {
                box-sizing: border-box;
                display: flex;
                flex-wrap: nowrap;
                flex-direction: row;
            }

            .row:after {
                content: "";
                display: table;
                clear: both;
            }

        </style>

        <script type="text/javascript">

            window.history.pushState({}, '', '<?php echo $_SERVER['PHP_SELF']; ?>');

            var db_workgroups = <?php echo json_encode($db_workgroups); ?>; // Object of Workgroups
            var all_notes = <?php echo json_encode($notes); ?>; // Object of Notes records

            function exportCSV(isValid, err_text) {

                var action = $("#emailOptions").attr("onsubmit");
                $("#emailOptions").attr("onsubmit", "");
                $("input[name='exportCSV']").val(true);

                if(isValid) {
                    var data = {};

                    $.map($("#emailOptions").serializeArray(), function(obj, idx) {
                        data[obj['name']] = obj['value'];
                    });

                    $("#exportData").val(data);
                    $("#emailOptions").submit();
                } else {

                    window.hWin.HEURIST4.msg.showMsgFlash(err_text, 5000);
                }

                $("#emailOptions").attr("onsubmit", action);
                $("input[name='exportCSV']").val(false);

                return false;
            }

            function validateForm(e) {

                var isValid = true;

                var err_text = "The following actions are required:<br/><br/>";
                var messages = {
                    "dbs": "Select at least one database for use<br/>",
                    "user": "Select a user type to email<br/>",
                    "workgroups": "Select at least one workgroup for use<br/>",
                    "note": "Select a email template to form the email body<br/>",
                    "pwd": "Enter the System Admin password to proceed<br/>"
                };

                var $dbSel = $("#dbSelection");
                var $userSel = $("#userSel");
                var $noteSel = $("#emailOutline");

                if(!$dbSel.find("input[type='checkbox']").is(":checked")){

                    $dbSel.addClass("invalid-input");
                    isValid = false;

                    err_text += messages["dbs"];
                } else {
                    $dbSel.removeClass("invalid-input");
                }

                if($userSel.val() == "null"){

                    //$("#userSel-button").addClass("invalid-input");
                    $("#userSel").addClass("invalid-input");
                    isValid = false;

                    err_text += messages["user"];
                } else if($userSel.val() == "admin"){

                    var $group_selection = $("#wg-container").find("input").is(":checked");

                    //$("#userSel-button").removeClass("invalid-input");
                    $("#userSel").removeClass("invalid-input");
                    if(!$group_selection){

                        $("#userSelection").addClass("invalid-input");
                        isValid = false;

                        err_text += messages["workgroups"];
                    } else {
                        $("#userSelection").removeClass("invalid-input");
                    }
                } else {
                    //$("#userSel-button").removeClass("invalid-input");
                    $("#userSel").removeClass("invalid-input");
                    $("#userSelection").removeClass("invalid-input");
                }

                if($noteSel.val() == "null"){

                    //$("#emailOutline-button").addClass("invalid-input");
                    $("#emailOutline").addClass("invalid-input");
                    isValid = false;

                    err_text += messages["note"];
                } else {
                    //$("#emailOutline-button").removeClass("invalid-input");
                    $("#emailOutline").removeClass("invalid-input");
                }

                if($(e.target).attr("id")=="csvExport") { 

                    return exportCSV(isValid, err_text); 
                }

                if(window.hWin.HEURIST4.util.isempty($("#sm_pwd").val())){

                    $("#sm_pwd").addClass("invalid-input");
                    isValid = false;

                    err_text += messages["pwd"];
                } else {
                    $("#sm_pwd").removeClass("invalid-input");
                }

                if(!isValid) { 

                    window.hWin.HEURIST4.msg.showMsgFlash(err_text, 5000);
                }

                return isValid;
            }

            function changeWorkgroupList(element) { // Default Workgroups 0: Everybody, 1: Database Managers, 3: Other users, 4: Website filters

                var hasDBSelected = true;

                var $ele = $(element.target);
                var db_name = $ele.val();
                var workgroups = db_workgroups[db_name];

                var $group_selection = $("#wg-container");

                if(!$("#dbSelection").find("input[type='checkbox']").is(":checked")) {
                    hasDBSelected = false;
                }

                if($ele.is(":checked")){
                    
                    $.each(workgroups, function(id, name){

                        if(id == 0 || name == "Everybody") { return; }

                        if($group_selection.find("label[for='"+name+"']").length > 0) { return 0; }

                        $group_selection.append(
                                "<label class='label non-selectable' for='"+ name +"'> " + name
                              + "   <input type='checkbox' name='workgroups[]' value='"+ name +"' id='"+ name +"'><br/>"
                              + "</label>"
                        );

                    });

                    $group_selection.find("input[type='checkbox']").checkboxradio();
                } else {

                    $.each(workgroups, function(id, name){

                        if(id == 0 || name == "Everybody") { return; }

                        if((id==1||id==3||id==4) && hasDBSelected) { return 0; }

                        if($group_selection.find("#"+id)) {
                            $group_selection.find("label[for='"+ name +"']").remove();
                        }
                    });

                    if($("#dbSelection").find("input[type='checkbox']").is(":checked").length == 0) {

                        $group_selection.find("label[for='1']").remove();
                        $group_selection.find("label[for='3']").remove();
                        $group_selection.find("label[for='4']").remove();
                    }
                }
            }

            function setupDBSelection() {

                var $db_selection = $("#dbSelection");

                $.each(db_workgroups, function(key, value) {

                    var name = key.substring(4);

                    $db_selection.append(
                        "<label class='label non-selectable' for='"+ key +"'> " + name
                      + "   <input type='checkbox' class='dbListCB' name='databases[]' value='"+ key +"' id='"+ key +"'>"
                      + "</label>"
                    );
                });

                $db_selection.find(".dbListCB")
                    .checkboxradio()
                    .on("change", changeWorkgroupList);

                $db_selection.find("#allDBs")
                    .on("click", function(e){

                        var is_checked = $(e.target).is(":checked");

                        $db_selection.find(".dbListCB").prop("checked", is_checked).checkboxradio('refresh');
                    })
                    .css("vertical-align", "middle");
            }

            function setupUserSelection() {

                var $user_selection = $('#userSelection');

                $user_selection.append("<label for='users'>Select User Type:</label><br/>");

                var select = $("<select>")
                    .attr("name", "users")
                    .attr("id", "userSel")
                    .appendTo($user_selection);

                var options = [
                    //{key:"null", title:"Select user group..."},
                    {key:"owner", title:"Database Owner/s", selected: true},
                    {key:"admin", title:"Administrators"},
                    {key:"user", title:"All Users"}
                ];

                window.hWin.HEURIST4.ui.createSelector(select.get(0), options);
                window.hWin.HEURIST4.ui.initHSelect(select, true);

                if(select.hSelect("instance")!=undefined) {
                    select.hSelect("widget").css({"margin-top": "5px", "min-width": "13em"});
                }

                select.on({
                    change: function(event) {
                        
                        var showWorkgroups = $(event.target).val()==='admin';

                        if($(event.target).val()==='admin') {
                            $("#wg-container").show();
                        } else {
                            $("#wg-container").hide();
                        }
                    }
                });

                $("<span id='wg-error' class='error-msg non-selectable' style='width: 234px;'></span>").appendTo($user_selection).hide();
                $("<div style='float: right;' id='wg-container'></div>").appendTo($user_selection).hide();
            }

            function setupNoteSelection() {

                var $note_selection = $("#emailOutline");

                var options = [
                    {key:"null", title: "Select a notes record..."},
                ];

                $.each(all_notes, function(idx, value){

                    var opt = {key: idx, title: value[0]};

                    options.push(opt);
                });

                window.hWin.HEURIST4.ui.createSelector($note_selection.get(0), options);
                window.hWin.HEURIST4.ui.initHSelect($note_selection, true);

                if($note_selection.hSelect("instance")!=undefined) {
                    $note_selection.hSelect("widget").css({"min-width": "20em"});
                }

                $note_selection.on({
                    change: function(event) {

                        var emailDraft = $(event.target).val();

                        if (emailDraft == null || emailDraft == "null") {
                            $("#emailContent").text("");
                        } else {
                            $("#emailContent").text(all_notes[emailDraft][1]);
                        }
                    }
                });
            }            

            function setupOtherElements() {

                var modifySel = $("#recModifiedSel");
                window.hWin.HEURIST4.ui.initHSelect(modifySel.get(0), true);

                modifySel.on({
                    change: function(event, data){

                        if($(event.target).val()==='ALL') {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);
                            //window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic-button"), true);
                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), true);
                        } else {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), false);
                            //window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic-button"), false);
                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), false);
                        }
                    }
                });

                $("#recTotal").spinner({
                    spin: function(event, ui) {

                        if(ui.value < 0) {
                            $(this).spinner("value", 0);
                            return false;
                        }
                    }
                });

                $("#recModified").spinner({
                    spin: function(event, ui) {

                        if(ui.value < 1) {
                            $(this).spinner("value", 1);
                            return false;
                        }
                    }
                });

                var modifyLogic = $("#recModifiedLogic");
                window.hWin.HEURIST4.ui.initHSelect(modifyLogic.get(0), true);
            }

            $(document).ready(function() {

                setupDBSelection();
                setupUserSelection();
                setupNoteSelection();
                setupOtherElements();

                if(!window.hWin.HR){
                    window.hWin.HR = function(token){return token};
                }

                $("#csvExport")
                    .position({
                        my: "left top",
                        at: "right top",
                        of: "#authenContainer"
                    })
                    .css('margin-top', '13px');
            });

        </script>
        
    </head>

    <body style="margin: 10px 10px 10px 20px;">
        
        <div style="font-family:Arial,Helvetica;">
            <h2>Heurist System Email</h2>
            
            <label class="label">
                This tool allows you to email all users / specified types of user on all / selected Heurist databases available on this server. <br/><br/>
                The email to be sent should be created as a <strong>Notes</strong> record in the current database,<br>
                including subject line, body text and fields to be substitued using ##....## notation.<br>
                The body text can be edited in the preview field below.
            </label>

            <form id="emailOptions" action="massSystemEmail.php" method="POST" target="_blank" onsubmit="return validateForm(event);">

                <div class="row">

                    <fieldset class="fieldsets" id="dbSelection">
                        <legend>Select the Heurist Databases to pull Users from: <label for="allDBs" class="non-selectable"><input type="checkbox" id="allDBs">Select All</label> </legend>

                    </fieldset>

                </div>

                <div class="row">
                    
                    <fieldset class="fieldsets" id="userSelection">
                        <legend>Select the Group of Users to email: </legend>

                    </fieldset>

                    <fieldset class="fieldsets" id="recSelection">
                        <legend>Record Filtering: </legend>

                        <label for="recTotal" class="label non-selectable"> Record Count Limit: <br/>
                            <input type="number" class="input-num" name="recTotal" id="recTotal" min="0" value="0">
                        </label>

                        <br/><br/>

                        <label for="recModified" class="label non-selectable"> Last Modified: <br/> </label>
                            <select name="recModLogic" id="recModifiedLogic">
                                <option value="<=">older than</option>
                                <option value=">=" selected>younger than</option>
                            </select>
                            <input type="number" class="input-num" name="recModVal" id="recModified" min="1" value="6">
                            <select name="recModInt" id="recModifiedSel">
                                <option value="DAY">Days</option>
                                <option value="MONTH" selected>Months</option>
                                <option value="YEAR">Years</option>
                                <option value="ALL">All</option>
                            </select>

                    </fieldset>

                </div>

                <div class="row">
                
                    <fieldset>
                        <legend>Email Content: </legend>

                        <div  style="float: left;margin-right: 10px;">
                            
                            <label class="label non-selectable" style="margin-bottom: 5px;"> 
                                Please select the Note record that contains the email outline (previewed on the right)<br/><br/>
                                
                                <select id="emailOutline" name="emailId"></select>
                            </label>

                            <label class="label">
                                <br/><br/>Placeholders that will be replace with proper values (case insensitive): <br/><br/>

                                ##firstname## &rarr; User's First Name,<br/>
                                ##lastname## &rarr; User's Last Name,<br/> 
                                ##email## &rarr; User's Email,<br/> 
                                ##database## &rarr; Database Name,<br/> 
                                ##dburl## &rarr; Database URL,<br/> 
                                ##record## &rarr; Record Count, and<br/> 
                                ##lastmodified## &rarr; Date of the Last Modified Record<br/>
                            </label>

                        </div>

                        
                        <div style="float: right;margin-left: 30px;">
                            <label class="label non-selectable" style="display: block;margin-bottom: 10px;">Email Preview:</label>
                            <textarea id="emailContent" rows="15" cols="75" name="emailOutline"></textarea>
                        </div>
                    </fieldset>

                </div>

                <fieldset class="fieldsets" id="authenContainer">
                    <span style="display: inline-block;padding: 10px 0px;">Please enter the System Manager password to comfirm:&nbsp;</span>

                    <input name="db" value="<?php echo $_REQUEST['db']; ?>" style="display: none;" readonly />
                    <input name="exportCSV" value="false" style="display: none;" readonly />
                    <input type="password" name="pwd" autocomplete="off" id="sm_pwd" />
    
                    <input style="margin-left: 5px;" type="submit" value="Send Emails" />
                </fieldset>

            </form>

            <form id="csvExport" action="massSystemEmail.php" method="POST" target="_blank" onsubmit="return validateForm(event);" style="display: inline-block;">
                <input type="hidden" name="exportdata" id="exportData" />
                <input type="submit" value="Export CSV" />
            </form>
        </div>
        
    </body>
</html>