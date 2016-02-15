<?php

/**
* createNewDB.php: Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Extensively modified 4/8/11 by Ian Johnson to reduce complexity and load new database in
* a series of files with checks on each stage and cleanup code. New database creation functions
* Oct 2014 by Artem Osmakov to replace command line execution to allow operation on dual tier systems
* 7 July 2015: modifications to allow use of registered databases as templates for new database
*/

define('NO_DB_ALLOWED',1);
define('SKIP_VERSIONCHECK2',1);
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/utilsMail.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');

$blankServer = (HEURIST_DBNAME==''); //@todo check exxistense of other databases

// must be logged in anyway to define the master user for the database
if (!($blankServer || is_logged_in())) {
    $spec_case = "";
    if(HEURIST_DBNAME=='Heurist_Sandpit'){ // the sandpit is no longer used (from late 2015)
        //special case - do not show database name
        $spec_case = "&register=1";
    }
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.$spec_case.
        '&last_uri='.urlencode(HEURIST_CURRENT_URL) );
    return;
}


// clean up string for use in SQL query
function prepareDbName(){
    $db_name = substr(get_user_username(),0,5);
    $db_name = preg_replace("/[^A-Za-z0-9_\$]/", "", $db_name);
    return $db_name;
}

function errorOut($msg){
    print '<p class="error ui-state-error"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'
    .$msg.'<p>';
}

//
//
//
function user_EmailAboutNewDatabase($ugr_Name, $ugr_FullName, $ugr_Organisation, $ugr_eMail, $newDatabaseName, $ugr_Interests){

    //create email text for admin
    $email_text =
    "There is new Heurist database.\n".
    "The user who created the new database is:\n".
    "Database name: ".$newDatabaseName."\n".
    "Full name:    ".$ugr_FullName."\n".
    "Email address: ".$ugr_eMail."\n".
    "Organisation:  ".$ugr_Organisation."\n".
    "Research interests:  ".$ugr_Interests."\n".
    "Go to the address below to review further details:\n".
    HEURIST_BASE_URL."admin/adminMenu.php?db=".$newDatabaseName;

    $email_title = 'New database: '.$newDatabaseName.' by '.$ugr_FullName.' ['.$ugr_eMail.']';

    //HEURIST_MAIL_TO_ADMIN
    $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $email_title, $email_text, null);
    if($rv != 'ok'){
        return false;
    }
    return true;
}

?>
<html>
    <head>
        <title>Create New Heurist Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <?php
        if($blankServer){
            ?>
            <link rel=icon href="../../../favicon.ico" type="image/x-icon">
            <link rel="stylesheet" href="../../../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
            <link rel="stylesheet" type="text/css" href="../../../h4styles.css">

            <!-- TODO: These all climb laboriously out to codebase then into /ext, /js etc...
            /localization does not even exist -->
            <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
            <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
            <script type="text/javascript" src="../../../hclient/core/localization.js"></script>
            <script type="text/javascript" src="../../../hclient/core/utils.js"></script>
            <script type="text/javascript" src="../../../hclient/core/utils_msg.js"></script>
            <script type="text/javascript" src="../../../hclient/core/hapi.js"></script>

            <script type="text/javascript" src="../../../hclient/widgets/profile/profile_edit.js"></script>

            <script type="text/javascript">
                $(document).ready(function() {

                    if($("#createDBForm").length>0){
                        top.HAPI4 = new hAPI(null);
                        var prefs = top.HAPI4.get_prefs();
                        //loads localization
                        top.HR = top.HAPI4.setLocale(prefs['layout_language']);

                        <?php if(!$passwordForDatabaseCreation)
                        {
                            echo 'doRegister();';
                        }
                        ?>


                        $("#btnRegister").button({label:'Register as User'}).on('click', doRegister);
                        $("#div_register").show();
                        $("#div_register_entered").hide();
                        $("#div_create").hide();
                    }
                });


                var profile_edit_dialog = null;

                function onRegisterDialogClose(){
                    var edit_date = profile_edit_dialog.profile_edit('option','edit_data');

                    var frm = $("#dbForm");
                    for (var fld_name in edit_date) {
                        if (edit_date.hasOwnProperty(fld_name)) {
                            var fld = frm.find('#'+fld_name);
                            if(fld.length>0){
                                fld.val( edit_date[fld_name] );
                            }else{
                                $('<input>', {id:fld_name, type:'hidden', name:fld_name, value:edit_date[fld_name]} ).appendTo(frm);
                            }
                        }
                    }

                    $("#div_register").hide();
                    $("#div_register_entered").show();
                    $("#div_create").show();
                    setUserPartOfName();
                }

                function doRegister(event){

                    top.HEURIST4.util.stopEvent(event);

                    if($.isFunction($('body').profile_edit)){

                        var edit_data = {};
                        <?php
                        //restore use registration parameters in case creation fails
                        foreach ($_REQUEST as $param_name => $param_value){
                            if(strpos($param_name,'ugr_')===0 && $param_name!='ugr_Password'){
                                //print '<input id="'.$param_name.'" name="'.$param_name.'" value="'.$param_value.'">';
                                print "edit_data['$param_name'] = '$param_value';";
                            }
                        }
                        ?>

                        if(profile_edit_dialog==null){
                            profile_edit_dialog = $('#heurist-profile-dialog');
                            if(profile_edit_dialog.length<1){
                                profile_edit_dialog = $( '<div id="heurist-profile-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $('body') );
                            }
                            profile_edit_dialog.profile_edit({'ugr_ID': -1, needclear:false, edit_data:edit_data, callback:onRegisterDialogClose  });
                        }else{
                            profile_edit_dialog.profile_edit('open');
                        }


                    }else{
                        $.getScript(top.HAPI4.basePathV4+'hclient/widgets/profile/profile_edit.js', function() {
                            if($.isFunction($('body').profile_edit)){
                                doRegister();
                            }else{
                                top.HEURIST4.msg.showMsgErr('Widget "Profile edit" cannot be loaded!');
                            }
                        });
                    }

                }

            </script>
            <?php
        }else{
            ?>
            <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
            <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
            <link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
            <!-- already referenced above <link rel="stylesheet" type="text/css" href="../../../h4styles.css"> -->
            <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
            <?php
        }
        ?>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>

        <style>
            .detailType {width:180px !important}
            h2 {padding:10px}
            .error {
                width:90%;
                margin:auto;
                margin-top:10px;
                padding:10px;
            }
            p{
                padding:5px;
            }
            .lastcol{
                display:table-cell;
                /*width:50%;
                min-width:400px;*/
            }
            .tabrow{
                /*width:90%;*/
                display:table-row;
                padding-left: 38px;
            }


        </style>

        <script>
            var registeredDBs = null;

            function hideProgress(){
                var ele = document.getElementById("loading");
                if(ele){
                    ele.style.display = "none";
                }
            }


            function showProgress(force){

                var ele = document.getElementById("loading");
                if(force) ele.style.display = "block";
                if(ele.style.display != "none"){
                    ele = document.getElementById("divProgress");
                    if(ele){
                        ele.innerHTML = ele.innerHTML + ".";
                        setTimeout(showProgress, 500);
                    }
                }
            }


            // does a simple word challenge (if set) to allow admin to globally restrict new database creation
            function challengeForDB(){
                var pwd_value = document.getElementById("pwd").value;
                if(pwd_value==="<?=$passwordForDatabaseCreation?>"){
                    document.getElementById("challengeForDB").style.display = "none";
                    document.getElementById("createDBForm").style.display = "block";
                }else{
                    alert("Password incorrect, please check with system administrator. Note: password is case sensitive");
                }
            }

            //allow only alphanumeric characters for db name
            function onKeyPress(event){

                event = event || window.event;
                var charCode = typeof event.which == "number" ? event.which : event.keyCode;
                if (charCode && charCode > 31)
                {
                    var keyChar = String.fromCharCode(charCode);
                    if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){
                        event.cancelBubble = true;
                        event.returnValue = false;
                        event.preventDefault();
                        if (event.stopPropagation) event.stopPropagation();
                        return false;
                    }
                }
                return true;
            }

            // assign user name as part of db name
            function setUserPartOfName(){
                var ele = document.getElementById("uname");
                if(ele.value==""){
                    ele.value = document.getElementById("ugr_Name").value.substr(0,5);
                }
            }


            //
            // fill hidden field url_template with link to script getDBStructureAsSQL
            //
            function onBeforeSubmit(){

                var ele = document.getElementById("url_template");
                if(document.getElementById("rb2").checked){

                    ele.value = '';

                    var bd_reg_idx = document.forms[0].elements['dbreg'].value;
                    if(!bd_reg_idx){
                        alert('Select database you wish use as a template');
                        return false;
                    }else{
                        var reginfo = registeredDBs[bd_reg_idx];

                        regurl = reginfo[1];
                        // Backwards compatibility for dbs originally registered with h3
                        if(regurl=='http://heurist.sydney.edu.au/h3/'){
                            regurl = 'http://heurist.sydney.edu.au/heurist/';
                        }

                        //url + script + db
                        ele.value = regurl + 'admin/describe/getDBStructureAsSQL.php?db='+reginfo[2];
                    }

                }else{
                    ele.value = '';
                }

                ele = document.getElementById("createDBForm");
                if(ele) ele.style.display = "none";

                //return false;
                $('.error').hide();
                showProgress(true);
                return true;
            }


            //
            // get list of all registered databases to allow new database to be based on an existing database
            //
            var is_db_got = false;
            function getRegisteredDatabases(){

                var ddiv = $('#registered_dbs');
                ddiv.show();

                //only once
                if(is_db_got) return;
                is_db_got = true;

                ddiv.height('40px');

                // request for server side on the Heurist master index
                var baseurl = "<?=HEURIST_BASE_URL?>admin/setup/dbproperties/getRegisteredDBs.php";
                var params = "db=<?=HEURIST_INDEX_DBNAME?>";   // &named=1&excluded=dbid
                top.HEURIST.util.getJsonData(baseurl,
                    //top.HEURIST.database.name

                    // fillRegisteredDatabasesTable
                    function(responce){

                        registeredDBs = responce;
                        var ddiv = document.getElementById('registered_dbs');
                        var s = '';

                        if(!top.HEURIST.util.isnull(responce)){

                            for(var idx in responce){
                                if(idx){

                                    //regurl = responce[idx][0]
                                    var bgcolor = (idx % 2 == 0)?'#EDF5FF':'#FFF';

                                    s = s + '<div class="tabrow" style="background-color:'+bgcolor+'">'
                                    +'<div style="min-width:20px;display:table-cell"><input type="radio" name="dbreg" value="'+idx+'" id="rbdb'+idx+'"/></div>'
                                    +'<div style="min-width:40px;display:table-cell"><label for="rbdb'+idx+'">'+responce[idx][0]+'</label></div>'
                                    +'<div style="width:300px;display:table-cell">'
                                    +'<label for="rbdb'+idx+'" '+(Number(responce[idx][0])<1000?'style="font-weight:bold;"':'')+'>'+responce[idx][2]+'</label></div>'
                                    +'<div class="truncate lastcol" >'
                                    +'<label for="rbdb'+idx+'" title="'+responce[idx][3]+'">'+responce[idx][3]+'</label></div></div>';
                                }
                            }

                            /*
                            var ddiv = $("#registered_dbs")
                            ddiv.empty();
                            for(var idx in responce){
                            if(idx){
                            ddiv.append(
                            $("<div>"+responce[idx][0]+"  "+responce[idx][2]+"</div>")
                            );
                            }
                            }
                            */

                        }
                        if(s==''){
                            s = '<p class="error ui-state-error">Cannot access Heurist databases index at: ';
                            s = s + baseurl + ' (' + params + ')<p>';
                        }
                        ddiv.innerHTML = s;
                        $(ddiv).height('300px');
                    },
                    params);
            }

            function closeDialog(newDBName){

                var url = "<?php echo HEURIST_BASE_URL?>"+"?db="+newDBName;
                window.open(url, "_blank" );

                <?php echo (@$_REQUEST['popup']=="1"?"window.close();":"") ?>
            }

        </script>
    </head>

    <?php
    if($blankServer){
        ?>
        <body style="padding:45px">
            <div class="ui-corner-all ui-widget-content"
                style="text-align:left; width:70%; min-height:550px; margin:0px auto; padding: 0.5em;">
            <div class="logo" style="background-color:#2e3e50;width:100%;margin-bottom:20px"></div>
            <?php
        }else{
            echo '<body class="popup">';
            echo (@$_REQUEST['popup']=='1'?'':'<div class="banner"><h2>Create New Database</h2></div>');
        }
        ?>

        <div id="page-inner" style="overflow:auto;<?php echo (@$_REQUEST['popup']=='1'?'top:10px;':'') ?>">
            <div id="loading" style="display:none">
                <img alt="creation ..." src="../../../common/images/mini-loading.gif" width="16" height="16" />
                <strong><span id="divProgress">&nbsp; Creation of database will take a few seconds </span></strong>
            </div>

            <?php

            $newDBName = "";
            // Used by buildCrosswalks to detemine whether to get data from coreDefinitions.txt (for new basic database)
            // or by querying an existing Heurist database using getDBStructureAsSQL (for crosswalk or use of database as template)
            $isNewDB = false;

            global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred
            global $done; // Prevents the makeDatabase() script from running twice
            $done = false; // redundant
            $isDefineNewDatabase = true;

            if(isset($_REQUEST['dbname'])) {   //name of new database
                $isDefineNewDatabase = false;
                $isTemplateDB = ($_REQUEST['dbtype']=='1');


                echo_flush( '<script type="text/javascript">showProgress(true);</script>' );

                // *****************************************
                $dataInsertionSQLFile = "";

                makeDatabase($dataInsertionSQLFile); // this does all the work

                // *****************************************

                //echo_flush( '<script type="text/javascript">hideProgress();</script>' );
            }

            print '<script type="text/javascript">hideProgress();</script>';

            $dataInsertionSQLFile = ""; // normal case is not to insert any data

            if($isDefineNewDatabase){
                ?>

                <h3 style="padding:0 0 0 10px;">Creating new database on server</h3>

                <div id="challengeForDB" style="<?='display:'.(($passwordForDatabaseCreation=='')?'none':'block')?>;">
                    <label class="labelBold">Enter the password set by your system administrator for new database creation:</label>
                    <input type="password" maxlength="64" size="25" id="pwd">
                    <input type="button" onclick="challengeForDB()" value="OK" style="font-weight: bold;" >
                </div>


                <div id="createDBForm" style="<?='display:'.($passwordForDatabaseCreation==''?'block':'none')?>;padding-top:5px;">
                    <form action="createNewDB.php"
                        method="POST" id="dbForm" onsubmit="return onBeforeSubmit()">

                        <input type="hidden" id="url_template" name="url_template">
                        <input type="hidden" name="db" value="<?php echo HEURIST_DBNAME; ?>">
                        <input type="hidden" name="popup" value="<?php echo @$_REQUEST['popup']?'1':''; ?>">

                        <div style="border-bottom: 1px solid #7f9db9;padding:10px;margin-bottom:2px;">
                            <input type="radio" name="dbtype" value="0" id="rb1" checked
                                onclick="{$('#registered_dbs').hide()}"/><label for="rb1"
                                class="labelBold" style="padding-left: 2em;">Standard database template</label>
                            <div style="padding-left: 38px;padding-bottom:10px;width:600px">
                                Gives an uncluttered database with essential record types, fields,
                                terms and relationships, including bibliographic and spatial entities.
                                Recommended for most new databases unless you wish to copy a particular template (next option).
                            </div>

                            <!-- Added training database 12 Feb 2016 -->
                            <input type="radio" name="dbtype" value="2" id="rb3"
                                onclick="{$('#registered_dbs').hide()}"/><label for="rb3"
                                class="labelBold" style="padding-left: 2em;">Example database (Shakespeare)</label>
                            <div style="padding-left: 38px;padding-bottom:10px;width:600px">
                                A training database consisting of interlinked information about Shakespeare's plays, company, actors, theatres, performances etc.
                                Use this database as a starting point for becoming familiar with Heurist (See <a href="http://HeuristNetwork.org/screencasts" target="_blank">introductory video</a>).
                            </div>

                            <!-- 7 July 2015: Replaced HuNI & FAIMS inbuilt templates with access to registered databases as templates -->
                            <input type="radio" name="dbtype" value="1" id="rb2" onclick="getRegisteredDatabases()"/><label for="rb2"
                                class="labelBold"  style="padding-left: 2em;">Use a registered database as template</label>
                            <div style="padding-left: 38px;width:600px">
                                Use a database registered with the Heurist Network as a template.
                                Copies record types, fields, terms and relationships from the database selected.
                                Databases with an ID &lt; 1000 are curated by the Heurist team and include templates
                                for the HuNI and FAIMS infrastructure projects,
                                as well as community servers maintained by other research groups.
                            </div>

                            <div id="registered_dbs"  style="max-height:300px;overflow-y:auto;overflow-x:hidden;margin-top:10px;
                                background:url(../../../hclient/assets/loading-animation-white.gif) no-repeat center center;">
                                <!-- list of registered DATABASEs  -->
                            </div>

                            <div id="nextSteps"
                                style="padding-left: 38px; wdith:620px;display:none">
                                <div><b>Suggested next steps</b></div>
                                <div>
                                    <br />After the database is created, we suggest visiting Database &gt; Acquire from databases and Database > Acquire from templates
                                    to download pre-configured templates or individual record types and fields from databases registered with the Heurist Network.
                                    <br />New databases are created on the current server. You will become the owner and administrator of the new database.
                                </div>
                            </div>
                        </div>

                        <div id="div_register_entered" style="display: none;">
                            <h3 style="margin:5px 0 10px 38px;color:darkgreen;">Registration information entered</h3>
                        </div>

                        <div id="div_create">
                            <h3 style="margin-left: 38px">Enter a name for the new database</h3>
                            <div style="margin-left:60px; margin-top: 10px;">
                                <!-- user name used as prefix -->
                                <i>no spaces or punctuation other than underscore</i><br />&nbsp;<br />
                                <b><?= HEURIST_DB_PREFIX ?>
                                    <input type="text" maxlength="30" size="6" name="uname" id="uname"
                                        onkeypress="{onKeyPress(event)}"
                                        style="padding-left:3px; font-weight:bold;"
                                        value=<?=(is_logged_in()?prepareDbName():'')?>
                                        >
                                </b>
                                -
                                <input type="text" maxlength="64" size="30" name="dbname"  onkeypress="{onKeyPress(event);}">
                                <input id="btnCreateDb" type="submit" name="submit" value="Create database" style="font-weight: bold;"  >
                                <p>The user name prefix is editable, and may be blank, but we suggest using a consistent prefix for personal<br>
                                    databases so that they are easily identified and appear together in the list of databases.</p>
                            </div>

                        </div>

                    </form>

                    <div id="div_register" style="display: none;">
                        <!-- h3 style="margin-left: 38px">Define Database Administrator</h3 -->
                        <div style="margin-left: 60px; margin-top: 30px;">
                            <button id="btnRegister" style="font-weight: bold;" />
                        </div>
                        <div style="padding-left: 38px; margin-top: 20px; margin-bottom: 20px;">
                            Please register in order to define the user who will become the database owner and administrator.<br>/<br/>
                            If you are already a user of another database on this server, we suggest logging into that database (<a href="../../../index.php">select database here</a>)<br/>
                            and creating your new database with Database &gt; New Database, as this will carry over your login information from the existing database.
                        </div>
                    </div>

                </div> <!-- createDBForm -->
                <?php
            }

            function arraytolower($item)
            {
                return strtolower($item);
            }

            // rollback - delete database
            //
            function cleanupNewDB($dbname){
                db_drop($dbname);
            }

            //return false if valid otherwise error string
            function isDefinitionsInvalid($data){

                if(!$data){
                    return "coreDefinition data is empty";
                }

                //check the number of start and end quotes
                if(substr_count ( $data , ">>StartData>>" )!=substr_count ( $data , ">>EndData>>" )){
                    return "Error: core Definition data is invalid: The number of open and close tags must be equal";
                }

                //verify that start is always before end
                $offset = 0;
                while (true) {
                    $pos1 = strpos(">>StartData>>", $data, $offset);
                    $pos2 = strpos(">>EndData>>", $data, $offset+10);
                    if($pos1===false){
                        break;
                    }
                    if($pos2>$pos1){
                        return  "Error: core Definition data is invalid: Missed open tag";
                    }
                    $offset = $pos2;
                }
                return false;
            }

            function getUsrField($name){
                return @$_REQUEST[$name]?mysql_real_escape_string($_REQUEST[$name]):'';
            }



            // Creates a new database and populates it with triggers, constraints and core definitions
            // if $dataInsertionSQLFile is set, also inserts data (used for Example database(s))

            function makeDatabase($dataInsertionSQLFile) {

                global $newDBName, $isNewDB, $done, $isDefineNewDatabase, $isTemplateDB, $errorCreatingTables;

                $error = false;
                $warning=false;

                if (isset($_REQUEST['dbname'])) {

                    // Check that there is a current administrative user who can be made the owner of the new database
                    $message = "MySQL username and password have not been set in configIni.php ".
                    "or heuristConfigIni.php<br/> - Please do so before trying to create a new database.<br>";
                    if(ADMIN_DBUSERNAME == "" || ADMIN_DBUSERPSWD == ""){
                        errorOut($message);
                        return false;
                    } // checking for current administrative user

                    if(!is_logged_in()) { //this is creation+registration

                        $captcha_code = getUsrField('ugr_Captcha');

                        //check capture
                        if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != $captcha_code) {
                            errorOut('Are you a bot? Please enter the correct answer to the challenge question');
                            $isDefineNewDatabase = true;
                            return false;
                        }
                        if (@$_SESSION["captcha_code"]){
                            unset($_SESSION["captcha_code"]);
                        }

                        $firstName = getUsrField('ugr_FirstName');
                        $lastName = getUsrField('ugr_LastName');
                        $eMail = getUsrField('ugr_eMail');
                        $name = getUsrField('ugr_Name');
                        $password = getUsrField('ugr_Password');
                        if($firstName=='' || $lastName=='' || $eMail=='' || $name=='' || $password==''){
                            errorOut('Mandatory data for your registration profile (first and last name, email, password) are not completed. Please fill out registration form');
                            $isDefineNewDatabase = true;
                            return false;
                        }
                    }

                    // Create a new blank database
                    $newDBName = trim($_REQUEST['uname']).'_';

                    if ($newDBName == '_') {$newDBName='';}; // don't double up underscore if no user prefix
                    $newDBName = $newDBName . trim($_REQUEST['dbname']);
                    $newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix then user prefix

                    $list = mysql__getdatabases();
                    $list = array_map("arraytolower", $list);
                    if(in_array(strtolower($newDBName), $list)){
                        errorOut ('Warning: database "'.$newname.'" already exists. Please choose a different name');
                        $isDefineNewDatabase = true;
                        return false;
                    }

                    //get path to registered db template and download coreDefinitions.txt
                    $reg_url = @$_REQUEST['url_template'];

                    $name = '';

                    if(true){ // For debugging: set to false to avoid real database creation

                        // this is global variable that is used in buildCrosswalks.php
                        $templateFileName = "NOT DEFINED";
                        $templateFoldersContent = "NOT DEFINED";

                        if($reg_url){ // getting definitions from an external registered database

                            $nouse_proxy = true;

                            $isTemplateDB = true;

                            $data = loadRemoteURLContent($reg_url, $nouse_proxy); //without proxy
                            $resval = isDefinitionsInvalid($data);

                            if($resval){
                                if(defined("HEURIST_HTTP_PROXY")){
                                    $nouse_proxy = false;
                                    $data = loadRemoteURLContent($reg_url, $nouse_proxy); //with proxy
                                    $resval = isDefinitionsInvalid($data);
                                    if($resval){
                                        $data = null;
                                    }
                                }else{
                                    $data = null;
                                }
                            }
                            if($resval){
                                errorOut ("Error importing core definitions from template database for database $newname<br>"
                                    .$resval
                                    .'<br>Please check whether this database is valid; consult Heurist support if needed');
                                return false;
                            }

                            //save data into file
                            if(defined('HEURIST_SETTING_DIR')){
                                $templateFileName = HEURIST_SETTING_DIR . get_user_id() . '_dbtemplate.txt';
                            }else{
                                $templateFileName = HEURIST_UPLOAD_ROOT . '0_dbtemplate.txt';
                            }

                            $res = file_put_contents ( $templateFileName, $data );
                            if(!$res){
                                errorOut('Error: cannot save definitions from template database into local file.'
                                    .' Please verify that folder '
                                    .(defined('HEURIST_SETTING_DIR')?HEURIST_SETTING_DIR:HEURIST_UPLOAD_ROOT)
                                    .' is writeable');
                                return false;
                            }

                            //download content of some folder from template database

                            $reg_url = str_replace("getDBStructureAsSQL", "getDBFolders", $reg_url); //replace to other script
                            $data = loadRemoteURLContent($reg_url, $nouse_proxy); //with proxy

                            if($data){
                                if(defined('HEURIST_SETTING_DIR')){
                                    $templateFoldersContent = HEURIST_SETTING_DIR . get_user_id() . '_dbfolders.zip';
                                }else{
                                    $templateFoldersContent = HEURIST_UPLOAD_ROOT . '0_dbfolders.zip';
                                }

                                $res = file_put_contents ( $templateFoldersContent, $data );
                                if(!$res){
                                    errorOut ('Warning: cannot save content of settings folders from template database into local file. '
                                        .' Please verify that folder '
                                        .(defined('HEURIST_SETTING_DIR')?HEURIST_SETTING_DIR:HEURIST_UPLOAD_ROOT)
                                        .' is writeable');
                                    return false;
                                }
                            }else{
                                errorOut ('Warning: server does not return the content of settings folders from template database. '
                                    .'Please ask system adminstrator to verify that zip extension on remote server is installed and that upload folder is writeable');
                                return false;
                            }




                        }else if($isTemplateDB){
                            errorOut ('Wrong parameters: Template database is not defined.');
                            return false;
                        }else{
                            $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
                        }

                        if(!file_exists($templateFileName)){
                            errorOut('Error: template database structure file '.$templateFileName.' not found');
                            return false;
                        }

                        if(!createDatabaseEmpty($newDBName)){
                            $isDefineNewDatabase = true;
                            return false;
                        }

                        // Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
                        // yes, this is badly structured, but it works - if it ain't broke ...

                        $isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt

                        require_once(dirname(__FILE__).'/../../structure/import/buildCrosswalks.php');

                        // errorCreatingTables is set to true by buildCrosswalks if an error occurred
                        if($errorCreatingTables) {
                            errorOut('Error importing core definitions from '
                                .($isTemplateDB?"template database":"coreDefinitions.txt")
                                .' for database '.$newname.'<br>'
                                .'Please check whether this file or database is valid; consult Heurist support if needed');
                            cleanupNewDB($newname);
                            return false;
                        }



                        // Get and clean information for the user creating the database
                        if(!is_logged_in()) {
                            $longName = "";

                            $firstName = getUsrField('ugr_FirstName');
                            $lastName = getUsrField('ugr_LastName');
                            $eMail = getUsrField('ugr_eMail');
                            $name = getUsrField('ugr_Name');
                            $password = getUsrField('ugr_Password');
                            $department = getUsrField('ugr_Department');
                            $organisation = getUsrField('ugr_Organisation');
                            $city = getUsrField('ugr_City');
                            $state = getUsrField('ugr_State');
                            $postcode = getUsrField('ugr_Postcode');
                            $interests = getUsrField('ugr_Interests');

                            $ugr_IncomingEmailAddresses = getUsrField('ugr_IncomingEmailAddresses');
                            $ugr_TargetEmailAddresses = getUsrField('ugr_TargetEmailAddresses');
                            $ugr_URLs = getUsrField('ugr_URLs');

                            $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                            $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                            $password = crypt($password, $salt);

                        }else{
                            mysql_connection_insert(DATABASE);
                            $query = mysql_query('SELECT ugr_LongName, ugr_FirstName, ugr_LastName, ugr_eMail, ugr_Name, ugr_Password, '
                                .'ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, ugr_Interests, '
                                .'ugr_IncomingEmailAddresses, ugr_TargetEmailAddresses, ugr_URLs '
                                .'FROM sysUGrps WHERE ugr_ID='.get_user_id());
                            $details = mysql_fetch_row($query);
                            $longName = mysql_real_escape_string($details[0]);
                            $firstName = mysql_real_escape_string($details[1]);
                            $lastName = mysql_real_escape_string($details[2]);
                            $eMail = mysql_real_escape_string($details[3]);
                            $name = mysql_real_escape_string($details[4]);
                            $password = mysql_real_escape_string($details[5]);
                            $department = mysql_real_escape_string($details[6]);
                            $organisation = mysql_real_escape_string($details[7]);
                            $city = mysql_real_escape_string($details[8]);
                            $state = mysql_real_escape_string($details[9]);
                            $postcode = mysql_real_escape_string($details[10]);
                            $interests = mysql_real_escape_string($details[11]);
                            $ugr_IncomingEmailAddresses = mysql_real_escape_string($details[12]);
                            $ugr_TargetEmailAddresses = mysql_real_escape_string($details[13]);
                            $ugr_URLs = mysql_real_escape_string($details[14]);

                        }

                        //	 todo: code location of upload directory into sysIdentification, remove from edit form (should not be changed)
                        //	 todo: might wish to control ownership rather than leaving it to the O/S, although this works well at present


                        createDatabaseFolders($newDBName);

                        if(file_exists($templateFoldersContent) && filesize($templateFoldersContent)>0){ //override content of setting folders with template database files - rectype icons, smarty templates etc
                            unzip($templateFoldersContent, HEURIST_UPLOAD_ROOT.$newDBName."/");
                        }

                        // Prepare to write to the newly created database
                        mysql_connection_insert($newname);

                        // Make the current user the owner and admin of the new database
                        mysql_query('UPDATE sysUGrps SET ugr_Enabled="Y", ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'",
                            ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'",
                            ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'",
                            ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'",
                            ugr_IncomingEmailAddresses="'.$ugr_IncomingEmailAddresses.'",
                            ugr_TargetEmailAddresses="'.$ugr_TargetEmailAddresses.'",
                            ugr_URLs="'.$ugr_URLs.'",
                            ugr_interests="'.$interests.'" WHERE ugr_ID=2');
                        // TODO: error check, although this is unlikely to fail


                        if ($dataInsertionSQLFile) { // insert data from SQL file for example database

                            // TODO 13 feb 2016: insert data from chosen SQL file in /admin/setup/dbcreate

                        };


                        user_EmailAboutNewDatabase($name, $firstName.' '.$lastName, $organisation, $eMail, $newDBName, $interests);
                    }

                    ?>
                    <div  style='padding:0px 0 10px 0; font-size:larger;'>
                        <h2 style='padding-bottom:10px'>Congratulations, your new database <?php echo $newDBName;?> has been created</h2>
                        <?php
                        if(@$_REQUEST['db']!='' && @$_REQUEST['db']!=null){
                            ?>
                            <p style="padding-left:10px"><strong>Admin username:</strong> <?php echo $name ?></p>
                            <p style="padding-left:10px"><strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;</p>
                            <?php
                        }
                        ?>
                        <p style="padding-left:10px">Click here to log in to your new database:</p>
                        <p style="padding-left:6em"><b><a href="#"
                                    title="" onclick="{closeDialog('<?php echo $newDBName;?>'); return false;}">
                                    <?php echo HEURIST_BASE_URL."?db=".$newDBName; ?>
                                </a></b>&nbsp;&nbsp;&nbsp;&nbsp; <i>(we suggest bookmarking this link)</i></p>

                        <p style="padding-left:6em">
                            After logging in to your new database, we suggest you import some additional entity types from one of the<br />
                            curated Heurist databases, or from one of the other databases listed in the central database catalogue,<br />
                            using Database &gt; Structure &gt; Acquire from Databases or Database &gt; Structure &gt; Acquire from Templates</p>
                    </div>
                    <?php
                    // TODO: automatically redirect to the new database in a new window
                    // this is a point at which people tend to get lost

                    return false;
                } // isset

            } //makedatabase
            if($blankServer){
                echo '</div>';
            }
            ?>
        </div>
    </body>
</html>
