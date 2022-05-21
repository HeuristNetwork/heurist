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
require_once(dirname(__FILE__).'/bulkEmailSystem.php');

if (@$_REQUEST["exportCSV"] == "true") {

    getCSVDownload($_REQUEST);
    exit();
}

if ( !isset($_REQUEST['db']) && $system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <h3> A Heurist database and Server Manager password are required to enter this function </h3>

    <?php
    exit();
} else if (isset($_REQUEST['databases']) && isset($_REQUEST['users']) && isset($_REQUEST['emailBody']) && isset($_REQUEST['db']) && isset($_REQUEST['pwd'])) {

    if($system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions)){

        echo "The System Administrator password is invalid, please re-try in the previous tab/window";
        exit();
    } else {

        $rtn = sendSystemEmail($_REQUEST);

        if ($rtn["status"] == "ok") {
            echo "<br><br><div>A receipt of the process has been saved as a Notes Record<br><br>Record ID => ". $rtn["data"] ."<br>Record Title => ". $rtn["rec_Title"] ."</div>";
        }

        //echo "<script>window.close();</script>";

        exit();
    }
}

$mysqli = $system->get_mysqli();

$emails = array();

$has_emails = false;

$current_db = HEURIST_DB_PREFIX . $_REQUEST['db'];

$email_rectype_id = ConceptCode::getRecTypeLocalID("2-9");
if (empty($email_rectype_id)) {
    print "Unable to retrieve the id for the Email record type.<br>Please download this record type from the Heurist Core Definitions database, available via Design > Browser templates.";
    exit();
}

$query = "SELECT rec_ID, rec_Title FROM Records WHERE rec_RecTypeID = " 
            . $email_rectype_id 
            . " AND rec_Title != '' AND rec_Title IS NOT NULL AND rec_FlagTemporary != 1";

$email_list = $mysqli->query($query);
if (!$email_list) { 
    print "Either unable to retrieve Email records from the current database, Error => " . $mysqli->error. ", Query => " .$query; 
    exit();
}

while($email = $email_list->fetch_row()){

    if(empty($email[1])) {
        continue;
    }

    $emails[$email[0]] = $email[1]; //id -> title

    $has_emails = true;
}

if(!$has_emails || empty($emails)) {
    print "<br><br>This function sends bulk emails based on text in a selected <i>Email</i> record<br><br>"
    . "<strong>" . $_REQUEST['db'] . " contains no valid Email records.</strong><br><br>"
    . "<strong>Please create an Email record in the database containing the text<br>"
    . "you want to send out, using ##xxxx## markers for values to be inserted.</strong><br><br>"
    . "The Email record to be used must contain a title field and a short summary field - the latter will be used as the email's body. The title and body can be edited before sending. <br>"
    . "If you want to create your email on-the-fly simply create a dummy record with placeholders for title and body to enable this function. <br><br>" 
    . "Placeholders that will be replaced with proper values (case insensitive):<br><br>"
    . "##firstname## &rarr; User's First Name,<br>"
    . "##lastname## &rarr; User's Last Name,<br>" 
    . "##email## &rarr; User's Email,<br>" 
    . "##database## &rarr; Database Name,<br>" 
    . "##dburl## &rarr; Database URL,<br>" 
    . "##records## &rarr; Record Count, and<br>" 
    . "##lastmodified## &rarr; Date of the Last Modified Record<br>";
    exit();
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

            .label {
                font-size: 14px;

                height: 20px;

                padding: 5px;
            }
            .label:nth-child(odd) {
                background:-moz-linear-gradient(center top, #FFFFFF, #EEEEEE) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#FFFFFF), to(#EEEEEE));
            }
            .label:nth-child(odd):hover {
                background:-moz-linear-gradient(center top, #EFEFEF, #DDDDDD) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EFEFEF), to(#DDD));
            }
            .label:nth-child(even) {
                background:-moz-linear-gradient(center top, #EDF5FF, #EDF5FF) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EDF5FF), to(#EDF5FF));
            }

            .instruction {
                font-size: 0.9em;
            }

            input[type=number] {
                font-size: 0.9em;

                margin: 2px;

                width: 70px;
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
            span.ui-button.ui-state-active {
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
            .non-selectable {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;

                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;        
            }

            .t-row {
                display: block;

                margin: 10px 0px 30px 0px;
            }

            .l-col {
                float: left;

                width: 15%;
                max-width: 15%;
            }

            #dbSelection {
                overflow-y: auto;

                max-height: 75%;
            }

            .r-col {
                float: left;

                max-width: 70%;

                margin: 20px 0px 0px 65px;
            }

        </style>

        <script type="text/javascript">

            window.history.pushState({}, '', '<?php echo $_SERVER['PHP_SELF']; ?>');

            var all_emails = <?php echo json_encode($emails)?>; // Object of Email records id->title
            
            var current_db = "<?php echo $current_db ?>";

            function exportCSV(isValid) {

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
                    "workgroups": "Select at least one workgroup for use<br/>",
                    "title": "Please enter a Email Title<br/>",
                    "body": "Please enter a Email Body<br/>",
                    "pwd": "Enter the System Admin password to proceed<br/>",
                    "invalid_count": "Record count needs to be a non-negative number<br/>",
                    "invalid_period": "Last modified amount needs to be a non-negative number higher than one<br/>"
                };

                var $dbSel = $("#dbSelection");
                var $emailTitle = $("#emailTitle");
                var $emailBody = $("#emailBody");
                var $recCount = $("#recTotal");
                var $lmPeriod = $("#recModified");

                if(!$dbSel.find("input[type='checkbox']").is(":checked")){

                    err_text += messages["dbs"];
                    isValid = false;
                }

                if(window.hWin.HEURIST4.util.isempty($emailTitle.val())){

                    isValid = false;
                    err_text += messages["title"];
                }

                if(window.hWin.HEURIST4.util.isempty($emailBody.val())){

                    isValid = false;
                    err_text += messages["body"];
                }

                if($recCount.val() < 0){

                    isValid = false;
                    err_text += messages["invalid_count"];
                }

                if($lmPeriod.val() <= 0 && !$lmPeriod.attr("disabled")){

                    isValid = false;
                    err_text += messages["invalid_period"];
                }

                if(window.hWin.HEURIST4.util.isempty($("#sm_pwd").val())){

                    err_text += messages["pwd"];
                    isValid = false;
                }

                if(!isValid) { 
                    window.hWin.HEURIST4.msg.showMsgFlash(err_text, 5000);
                }else{
                    verifySystemAdminPwd();
                }
                if(isValid && $(e.target).attr("id")=="csvExport") { 

                    return exportCSV(isValid, err_text); 
                }
            }

            function setupDBSelection(dbs) {

                var $db_selection = $("#dbSelection");

                if(window.hWin.HEURIST4.util.isempty(dbs)){
                    window.hWin.HEURIST4.msg.showMsgFlash("There are no databases based on the filters");
                    $("#filterMsg").hide().text("Filtering Databases...");
                    return;
                }

                $.each(dbs, function(key, value) {

                    var name = value.substring(4);

                    $db_selection.append(
                        "<div class='label non-selectable' title='"+ name +"'> "
                      + "   <input type='checkbox' class='dbListCB' name='databases[]' value='"+ value +"' id='"+ value +"'><label for='"+ value +"'>" + name + "</label>"
                      + "</div>"
                    );
                });

                $db_selection.find("div")
                    .on('dblclick', function(e){

                        if($(e.target).is('div')){

                            $(e.target).find('input').click();
                        }
                    });

                $("#dbArea").find("#allDBs")
                    .on("click", function(e){

                        var is_checked = $(e.target).is(":checked");

                        $db_selection.find(".dbListCB").prop("checked", is_checked);

                        getUserCount();
                    })
                    .css("vertical-align", "middle");

                $db_selection.find(".dbListCB").on("change", getUserCount);

                $("#userCount").text("0");
                $("#filterMsg").hide().text("Filtering Databases...");
            }

            function setupUserSelection() {

                var $user_selection = $('#userSelection');

                var select = $("<select>")
                    .attr("name", "users")
                    .attr("id", "userSel")
                    .appendTo($user_selection);

                var options = [
                    {key:"owner", title:"Database Owner/s", selected: true},
                    {key:"manager", title:"Administrators - Database Managers"},
                    {key:"admin", title:"Administrators - All Workgroups"},
                    {key:"user", title:"All Users"}
                ];

                window.hWin.HEURIST4.ui.createSelector(select.get(0), options);
                //window.hWin.HEURIST4.ui.initHSelect(select, true); // was false, but apparently the menu widget will not open

                if(select.hSelect("instance")!=undefined) {
                    select.hSelect("widget").css({"margin-top": "5px", "min-width": "15em", "width": "310px"});
                }

                $("<span id='wg-error' class='error-msg non-selectable' style='width: 234px;'></span>").appendTo($user_selection).hide();

                select.on("change", getUserCount);
            }

            function setupEmailSelection() {
                
                var $email_selection = $("#emailOutline");

                var options = [
                    {key:"null", title: "Select a email record..."},
                ];

                $.each(all_emails, function(idx, value){

                    var opt = {key: idx, title: value};

                    options.push(opt);
                });

                window.hWin.HEURIST4.ui.createSelector($email_selection.get(0), options);
                /*
                window.hWin.HEURIST4.ui.initHSelect($email_selection, true); // was false, but apparently the menu widget will not open

                if($email_selection.hSelect("instance")!=undefined) {
                    $email_selection.hSelect("widget").css({"min-width": "20em"});
                }else{
console.log('FAIL');                                    
                }
                */

                $email_selection.on({
                    change: function(event) {

                        var emailDraft = $(event.target).val();

                        if (emailDraft == null || emailDraft == "null") {
                            $("#emailTitle").text("");
                            $("#emailBody").text("");
                        } else {
                            getEmailDetails(emailDraft);
                        }
                    }
                });
            }            

            function setupOtherElements() {

                var modifySel = $("#recModifiedSel");
                var modifyLogic = $("#recModifiedLogic");

                //window.hWin.HEURIST4.ui.initHSelect(modifySel.get(0), true); // was false, but apparently the menu widget will not open
                //window.hWin.HEURIST4.ui.initHSelect(modifyLogic.get(0), true); // was false, but apparently the menu widget will not open

                window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);
                window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic-button"), true);

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

                $("#btnApply").on({
                    click: function(event, data) {

                        $("#dbSelection").find(".dbListCB").off("change");
                        $("#dbSelection").empty();

                        $("#filterMsg").show();

                        var data = {
                            db: current_db,
                            db_filtering: {
                                count: $("#recTotal").val(),
                                lastmod_logic: $("#recModifiedLogic").val(),
                                lastmod_period: $("#recModified").val(),
                                lastmod_unit: $("#recModifiedSel").val()
                            }
                        }

                        $.ajax({
                            url: 'bulkEmailOther.php',
                            type: 'POST',
                            data: data,
                            dataType: 'json',
                            cache: false,
                            xhrFields: {
                                withCredentials: true
                            },
                            //fail:
                            error: function(jqXHR, textStatus, errorThrown){

                                window.hWin.HEURIST4.msg.showMsgErr("An error has occurred with retrieving the filtered list of databases."
                                        + "<br>Error Details: " + jqXHR.status + " => " + textStatus
                                        + "<br><br>Please contact the Heurist team if this problem persists");
                            },
                            //done:
                            success: function(response, textStatus, jqXHR){

                                if(response.status == "ok"){
                                    setupDBSelection(response.data);
                                } else {

                                    if(window.hWin.HEURIST4.util.isempty(response.message)){
                                        window.hWin.HEURIST4.msg.showMsgErr("An unknown error has occurred, please contact the Heurist team.");
                                    } else {
                                        var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                        window.hWin.HEURIST4.msg.showMsgErr({message: msg, title: "Heurist"});
                                    }
                                }
                            },
                            //always:
                            complete: function(jqXHR, textStatus){
                                if(textStatus == 'success'){
                                    $("#filterMsg").text('Database Filtering is Completed, Loading List');
                                }else{
                                    $("#filterMsg").text('An error has occurred!');
                                }
                            }
                        });
                    }
                });

                $("#btnEmail").on("click", function(event){

                    validateForm(event);
                });
            }

            function getDBList() {

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: {db: current_db, db_filtering: "all"},
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    //fail:
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr("An error has occurred with retrieving the filtered list of databases."
                                + "<br>Error Details: " + jqXHR.status + " => " + textStatus
                                + "<br><br>Please contact the Heurist team if this problem persists");
                    },
                    //done:
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            setupDBSelection(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr("An unknown error has occurred, please contact the Heurist team.");
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, title: "Heurist"});
                            }
                        }
                    }
                });
            }

            function getEmailDetails(id) {

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: {db: current_db, get_email: true, recid: id},
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr("An error has occurred with retrieving the Email record short summary field (email body)."
                                + "<br>Error Details: " + jqXHR.status + " => " + textStatus
                                + "<br><br>Please contact the Heurist team if this problem persists");
                    },
                    success: function(response, textStatus, jqXHR){
                        
                        if(response.status == "ok"){
                            $("#emailTitle").val(response.data[0]);
                            $("#emailBody").text(response.data[1]);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr("An unknown error has occurred, please contact the Heurist team.");
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, title: "Heurist"});
                            }
                        }
                    }
                });
            }

            function getUserCount() {

                var dbs = [];

                var checked_dbs = $("#dbSelection").find(".dbListCB:checked");
                if(checked_dbs.length == 0){
                    $("#userCount").text("0");
                    return;
                }

                checked_dbs.each(function(idx, ele){
                    dbs.push($(ele).attr("id"));
                });

                var data = {
                    db: current_db,
                    user_count: $("#userSel").val(),
                    db_list: dbs
                };

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr("An error has occurred with retrieving the the user count for the selected databases and user type."
                                + "<br>Error Details: " + jqXHR.status + " => " + textStatus
                                + "<br><br>Please contact the Heurist team if this problem persists");
                    },
                    success: function(response, textStatus, jqXHR){
                        
                        if(response.status == "ok"){
                            $("#userCount").text(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr("An unknown error has occurred, please contact the Heurist team.");
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, title: "Heurist"});
                            }
                        }
                    }
                });
            }

            function verifySystemAdminPwd() {

                var data = {
                    db: current_db,
                    sysadmin_pwd: $("#sm_pwd").val()
                };

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr("An error has occurred with verifying the System Administrator password."
                                + "<br>Error Details: " + jqXHR.status + " => " + textStatus
                                + "<br><br>Please contact the Heurist team if this problem persists");
                    },
                    success: function(response, textStatus, jqXHR){
                        
                        if(response.status == "ok"){
                            if(response.data == true){
                                $("#emailOptions").submit();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgFlash("The System Administrator password is incorrect.<br>Please re-enter it.", 5000);
                            }
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr("An unknown error has occurred, please contact the Heurist team.");
                            } else { // There is no error_msg
                                window.hWin.HEURIST4.msg.showMsgErr({message: response.message, title: "Heurist"});
                            }
                        }
                    }
                });

                return false;
            }

            $(document).ready(function() {

                setupUserSelection();
                setupEmailSelection();
                setupOtherElements();

                if(!window.hWin.HR){
                    window.hWin.HR = function(token){return token};
                }

                $("#btnEmail")
                    .position({
                        my: "left top",
                        at: "left bottom",
                        of: "#sm_pwd"
                    })
                    .css('margin-top', '20px');

                $("#csvExport")
                    .position({
                        my: "left top",
                        at: "right top",
                        of: "#btnEmail"
                    })
                    .css('margin-left', '10px');

                getDBList();
            });

        </script>
        
    </head>

    <body style="margin: 10px 10px 10px 20px;">
        
        <div style="font-family:Arial,Helvetica;">
            <h3>Heurist System Email</h3>
            
            <label class="instruction">
                This tool allows you to email all users / specified types of user on all / selected Heurist databases available on this server. <br/><br/>
                The email to be sent should be created as a <strong>Email</strong> record in the current database, including subject line, body text and fields to be substituted using ##....## notation. <br/><br/>
            </label>

            <form id="emailOptions" action="bulkEmailMain.php" method="POST" target="_blank">

                <div class="t-row">

                    <span style="margin-right: 50px;">Filter: </span>

                    <span>
                        
                        <label for="recTotal" class="non-selectable"> Over <input type="number" class="input-num" name="recTotal" id="recTotal" min="0" value="0"> records </label>

                        &nbsp;&nbsp;&nbsp;

                        <span class="non-selectable"> Last modified

                            <select name="recModLogic" id="recModifiedLogic">
                                <option value="<=">more than</option>
                                <option value=">=" selected>less than</option>
                            </select>

                            <input type="number" class="input-num" name="recModVal" id="recModified" min="1" value="">

                            <select name="recModInt" id="recModifiedSel">
                                <option value="DAY">Days</option>
                                <option value="MONTH">Months</option>
                                <option value="YEAR">Years</option>
                                <option value="ALL" selected>All</option>
                            </select>

                            Ago
                        </span>

                    </span>

                    <span style="margin-left: 15px;">
                        <button type="button" id="btnApply">Apply</button>
                        <label id="filterMsg" style="display: none;">Filtering Databases...</label>
                    </span>

                </div>

                <div class="l-col">

                    <div id="dbArea">

                        <div style="margin-bottom: 15px;">Get users from these databases:</div>
                        <div class="non-selectable" style="margin: 0px 0px 10px 5px;"><input type="checkbox" id="allDBs">Select All</div>
                        <div id="dbSelection">
                        </div>

                    </div>

                </div>

                <div class="r-col">
                    
                    <div style="margin-bottom: 20px;">
                        Send email to: <span id="userSelection"></span> &nbsp;&nbsp;&nbsp; Count of distinct users: <span id="userCount">0</span>
                    </div>

                    <div class="non-selectable" style="margin-bottom: 20px;"> 
                        Email record containing the email outline 
                        <br>
                        <select id="emailOutline" name="emailId" style="width: 99%;"></select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        
                        <div style="margin-bottom: 15px;">
                            Email Subject: <input type="text" id="emailTitle" name="emailTitle" style="margin-left: 5px;width: 86.6%;">
                        </div>

                        <div class="non-selectable" style="margin-bottom: 10px;">Email Body (use html tags):</div>
                        <textarea id="emailBody" rows="20" cols="90" name="emailBody"></textarea>
                    </div>

                    <div class="instruction" style="margin-bottom: 51px;">

                            <div style="margin-bottom: 10px;">Placeholders that will be replaced with proper values (case insensitive): </div>

                            <div style="display: inline-block;float: left;margin: 0px 20px 35px 0px;">
                                ##firstname## &rarr; User's First Name, <br/>
                                ##lastname## &rarr; User's Last Name, <br/>
                                ##email## &rarr; User's Email, <br/>
                                ##database## &rarr; Database Name
                            </div>

                            <div style="display: inline-block;">
                                ##dburl## &rarr; Database URL, <br/>
                                ##records## &rarr; Record Count, and <br/>
                                ##lastmodified## &rarr; Date of the Last Modified Record
                            </div>

                    </div>

                    <div id="authenContainer">

                        Please enter the System Manager password to comfirm:&nbsp;

                        <input type="password" name="pwd" autocomplete="off" id="sm_pwd" />

                    </div>
                    
                    <div style="margin-top: 10px;">

                        <button style="margin-left: 5px;" type="button" id="btnEmail">Send Emails</button>
                        
                    </div>

                </div>

                <input name="db" value="<?php echo $_REQUEST['db']; ?>" style="display: none;" readonly />
                <input name="exportCSV" value="false" style="display: none;" readonly />

            </form>


            <form id="csvExport" action="bulkEmailMain.php" method="POST" target="_blank" onsubmit="return validateForm(event);" style="display: inline-block;">
                <input type="hidden" name="exportdata" id="exportData" />
                <input type="submit" value="Export CSV" />
            </form>
        </div>
        
    </body>
</html>