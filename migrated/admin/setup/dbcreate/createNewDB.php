<?php

/**
* createNewDB.php: Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');

// must be logged in anyway to define the master user for the database
if (!is_logged_in()) {
    $spec_case = "";
    if(HEURIST_DBNAME=='Heurist_Sandpit'){
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
?>
<html>
    <head>
        <title>Create New Heurist Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">

        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <script type="text/javascript" src="../../../external/jquery/jquery.js"></script>

        <style>
            .detailType {width:180px !important}
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

                $('.error').hide();

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


            // does a simple word challenge to allow admin to globally restrict new database creation
            function challengeForDB(){
                var pwd_value = document.getElementById("pwd").value;
                if(pwd_value==="<?=$passwordForDatabaseCreation?>"){
                    document.getElementById("challengeForDB").style.display = "none";
                    document.getElementById("createDBForm").style.display = "block";
                }else{
                    alert("Password incorrect, please check with system administrator. Note: password is case sensitive");
                }
            }


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

                        //@todo - IMPORTANT! it works for old registration only. for new ones "/migrated" should to be addded

                        regurl = reginfo[1];
                        if(regurl=='http://heurist.sydney.edu.au/h3/'){
                            regurl = 'http://heurist.sydney.edu.au/h4/migrated/';
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

                showProgress(true);
                return true;
            }

            var is_db_got = false;
            function getRegisteredDatabases(){

                //only once
                if(is_db_got) return;
                is_db_got = true;

                // request for server side
                var baseurl = "<?=HEURIST_BASE_URL?>admin/setup/dbproperties/getRegisteredDBs.php";
                var params = "db=<?=HEURIST_DBNAME?>";   // &named=1&excluded=dbid
                top.HEURIST.util.getJsonData(baseurl,
                    // fillRegisteredDatabasesTable
                    function(responce){

                        registeredDBs = responce;
                        var ddiv = document.getElementById('registered_dbs');
                        var s = '', regurl;

                        if(!top.HEURIST.util.isnull(responce)){

                            for(var idx in responce){
                                if(idx){

                                    //regurl = responce[idx][0]

                                    s = s + '<div style="display:table-row">'
                                    +'<div style="width:50px;display:table-cell"><input type="radio" name="dbreg" value="'+idx+'" id="rbdb'+idx+'"/></div>'
                                    +'<div style="width:50px;display:table-cell"><label for="rbdb'+idx+'">'+responce[idx][0]+'</label></div>'
                                    +'<div style="width:300px;display:table-cell"><label for="rbdb'+idx+'">'+responce[idx][2]+'</label></div>'
                                    +'<div style="width:900px;display:table-cell"><label for="rbdb'+idx+'">'+responce[idx][1]+'</label></div></div>';
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
                            s = '<p class="error">Cannot access Heurist database index<p>';
                        }
                        ddiv.innerHTML = s;
                    },
                params);
            }

            function closeDialog(newDBName){

                var url = "<?php echo HEURIST_BASE_URL_V4?>"+"?db="+newDBName;
                window.open(url, "_blank" );

                <?php echo (@$_REQUEST['popup']=="1"?"window.close();":"") ?>
            }

        </script>
    </head>

    <body class="popup">

        <?php echo (@$_REQUEST['popup']=="1"?"":"<div class='banner'><h2>Create New Database</h2></div>") ?>

        <div id="page-inner" style="overflow:auto">
            <div id="loading" style="display:none">
                <img alt="loading ..." src="../../../common/images/mini-loading.gif" width="16" height="16" />
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
            $isCreateNew = true;

            if(isset($_POST['dbname'])) {
                $isCreateNew = false;
                $isTemplateDB = ($_POST['dbtype']=='1');

                /* TODO: verify that database name is unique - currently rather ugly error trap
                $list = mysql__getdatabases();
                $dbname = $_POST['uname']."_".$_POST['dbname'];
                if(array_key_exists($dbname, $list)){
                echo "<h3>Database '".$dbname."' already exists. Choose different name</h3>";
                }else{
                */

                echo_flush( '<script type="text/javascript">showProgress(true);</script>' );

                // *****************************************

                makeDatabase(); // this does all the work

                // *****************************************

                //echo_flush( '<script type="text/javascript">hideProgress();</script>' );
            }

            print '<script type="text/javascript">hideProgress();</script>';

            if($isCreateNew){
?>

                <h2>Creating new database on server</h2>
                <br />

                <div id="challengeForDB" style="<?='display:'.(($passwordForDatabaseCreation=='')?'none':'block')?>;">
                    <h3>Enter the password set by your system administrator for new database creation:</h3>
                    <input type="password" maxlength="64" size="25" id="pwd">
                    <input type="button" onclick="challengeForDB()" value="OK" style="font-weight: bold;" >
                </div>


                <div id="createDBForm" style="<?='display:'.($passwordForDatabaseCreation==''?'block':'none')?>;padding-top:5px;">
                    <form action="createNewDB.php?db=<?= HEURIST_DBNAME ?>&popup=<?=@$_REQUEST['popup']?>"
                        method="POST" name="NewDBName" onsubmit="return onBeforeSubmit()">

                        <input type="hidden" id="url_template" name="url_template">

                        <div>
                        <?=HEURIST_BASE_URL?><br/>
                        <?=HEURIST_DIR?>
                        
                        </div>
                        
                        <div style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
                            <input type="radio" name="dbtype" value="0" id="rb1" checked /><label for="rb1"
                                class="labelBold">Standard starter database</label>
                            <div style="padding-left: 38px;padding-bottom:10px">
                                Gives an uncluttered database with essential record types, fields,
                                terms and relationships, including bibliographic and spatial entities.<br />
                                Recommended for most new databases unless you wish to copy a particular template (next option).
                            </div>

                            <!-- 7 July 2015: Replaced HuNI & FAIMS inbuilt templates with access to registered databases as templates -->

                            <input type="radio" name="dbtype" value="1" id="rb2" onclick="getRegisteredDatabases()"/><label for="rb2"
                                class="labelBold" >Use a registered database as template</label>
                            <div style="padding-left: 38px;">
                                Use a database registered with the Heurist Network as a template.
                                Copies record types, fields, terms and relationships from the database selected.<br />
                                Databases with an ID &lt; 1000 are curated by the Heurist team and include templates
                                for the HuNI and FAIMS infrastructure projects,
                                <br />as well as community servers maintained by other research groups.
                            </div>

                            <div id="registered_dbs"  style="max-height:300px;overflow-y:auto;padding-left: 38px;margin-top: 30px; background-color:lightgray">
                                <!-- DATABASE TEMPLATES NOT YET IMPLEMENTED -->
                            </div>
                            <!-- TO DO: NEED TO DISPLAY A DROPDOWN LIST OF DATABASES HERE, OR A BROWSE LIST WITH FILTER -->

                             <div style="padding-left: 38px; margin-top: 30px; margin-bottom: 20px;">
                                <div><b>Suggested next steps</b></div>
                                <div>
                                <br />After the database is created, we suggest visiting Database &gt; Import Structure and Database &gt; Annotated Templates to download
                                <br />pre-configured templates or individual record types and fields from databases registered with the Heurist Network.
                                <br />New databases are created on the current server. You will become the owner and administrator of the new database.
                                </div>
                            </div>
                        </div>

                        <h3 style="margin-left: 38px">Enter a name for the new database</h3>
                        <div style="margin-left: 60px; margin-top: 10px;">
                            <!-- user name used as prefix -->
                            <i>no spaces or punctuation other than underscore)</i><br />&nbsp;<br />
                            <b><?= HEURIST_DB_PREFIX ?>
                                <input type="text" maxlength="30" size="6" name="uname" id="uname" onkeypress="{onKeyPress(event)}"
                                    style="padding-left:3px; font-weight:bold;" value=<?=(is_logged_in()?prepareDbName():'')?> > _  </b>
                            <input type="text" maxlength="64" size="30" name="dbname"  onkeypress="{onKeyPress(event);}">
                            <input type="submit" name="submit" value="Create database" style="font-weight: bold;"  >
                            <p>The user name prefix is editable, and may be blank, but we suggest using a consistent prefix for personal<br>
                            databases so that they are easily identified and appear together in the list of databases.<p></p>
                        </div>
                    </form>
                </div> <!-- createDBForm -->
<?php
            }

            function arraytolower($item)
            {
                return strtolower($item);
            }

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

            function makeDatabase() { // Creates a new database and populates it with triggers, constraints and core definitions

                global $newDBName, $isNewDB, $done, $isCreateNew, $isTemplateDB, $errorCreatingTables;

                $error = false;
                $warning=false;

                if (isset($_POST['dbname'])) {

                    // Check that there is a current administrative user who can be made the owner of the new database
                    $message = "MySQL username and password have not been set in configIni.php ".
                    "or heuristConfigIni.php<br/> - Please do so before trying to create a new database.<br>";
                    if(ADMIN_DBUSERNAME == "") {
                        if(ADMIN_DBUSERPSWD == "") {
                            echo $message;
                            return;
                        }
                        echo $message;
                        return;
                    }
                    if(ADMIN_DBUSERPSWD == "") {
                        echo $message;
                        return;
                    } // checking for current administrative user


                    // Create a new blank database
                    $newDBName = trim($_POST['uname']).'_';

                    if ($newDBName == '_') {$newDBName='';}; // don't double up underscore if no user prefix
                    $newDBName = $newDBName . trim($_POST['dbname']);
                    $newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix then user prefix

                    $list = mysql__getdatabases();
                    $list = array_map("arraytolower", $list);
                    if(in_array(strtolower($newDBName), $list)){
                        echo ("<p class='error'>Error: database '".$newname."' already exists. Choose different name<br/></p>");
                        $isCreateNew = true;
                        return false;
                    }

                    //get path registered db template and download coreDefinitions.txt
                    $reg_url = @$_REQUEST['url_template'];

                    //debug print $reg_url."</br>";

if(true){
                    $templateFileName = "NOT DEFINED";
                    $templateFoldersContent = "NOT DEFINED";

                    if($reg_url){

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
                                echo ("<p class='error'>Error importing core definitions from template database "
                                        ." for database $newname<br>");
                                echo ( $resval );
                                echo ("<br>Please check whether this database is valid; consult Heurist support if needed</p>");
                                return false;
                            }

                        //save data into file
                        if(defined('HEURIST_SETTING_DIR')){

                              $templateFileName = HEURIST_SETTING_DIR . get_user_id() . '_dbtemplate.txt';
                              $res = file_put_contents ( $templateFileName, $data );
                              if(!$res){
                                echo ("<p class='error'>Error: cannot save definitions from template database into local file.");
                                echo ("Please verify that folder ".HEURIST_SETTING_DIR." is writeable</p>");
                                return false;
                             }

                            //download content of some folder from template database

                            $reg_url = str_replace("getDBStructureAsSQL", "getDBFolders", $reg_url); //replace to other script
                            $data = loadRemoteURLContent($reg_url, $nouse_proxy); //with proxy

                            if($data){

                                $templateFoldersContent = HEURIST_SETTING_DIR . get_user_id() . '_dbfolders.zip';
                                $res = file_put_contents ( $templateFoldersContent, $data );
                                if(!$res){
                                    echo ("<p class='error'>Warning: cannot save content of settting folders from template database into local file.");
                                    echo ("Please verify that folder ".HEURIST_SETTING_DIR." is writeable</p>");
                                    return false;
                                }
                            }else{
                                    echo ("<p class='error'>Warning: server does not return the content of settting folders from template database.");
                                    echo ("Please verify that zip extension on remote server is installed and upload folder is writeable</p>");
                                    return false;
                            }

                        }


                    }else if($isTemplateDB){
                                echo ("<p class='error'> Wrong parameters: Template database is not defined.</p>");
                                return false;
                    }else{
                        $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
                    }

                    if(!file_exists($templateFileName)){
                                echo ("<p class='error'>Error: definitions file ".$templateFileName."not found</p>");
                                return false;
                    }

                    if(!createDatabaseEmpty($newDBName)){
                        $isCreateNew = true;
                        return false;
                    }

                    // Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
                    // yes, this is badly structured, but it works - if it ain't broke ...

                    $isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt

                    require_once('../../structure/import/buildCrosswalks.php');

                    // errorCreatingTables is set to true by buildCrosswalks if an error occurred
                    if($errorCreatingTables) {
                        echo ("<p class='error'>Error importing core definitions from ".
                            ($isTemplateDB?"template database":"coreDefinitions.txt").
                            " for database $newname<br>");
                        echo ("Please check whether this file or database is valid; consult Heurist support if needed</p>");
                        cleanupNewDB($newname);
                        return false;
                    }

                    // Get and clean information for the user creating the database
                    if(!is_logged_in()) {
                        $longName = "";
                        $firstName = $_REQUEST['ugr_FirstName'];
                        $lastName = $_REQUEST['ugr_LastName'];
                        $eMail = $_REQUEST['ugr_eMail'];
                        $name = $_REQUEST['ugr_Name'];
                        $password = $_REQUEST['ugr_Password'];
                        $department = '';
                        $organisation = '';
                        $city = '';
                        $state = '';
                        $postcode = '';
                        $interests = '';

                        $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                        $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                        $password = crypt($password, $salt);

                    }else{
                        mysql_connection_insert(DATABASE);
                        $query = mysql_query("SELECT ugr_LongName, ugr_FirstName, ugr_LastName, ugr_eMail, ugr_Name, ugr_Password, " .
                            "ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, ugr_Interests FROM sysUGrps WHERE ugr_ID=".
                            get_user_id());
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
                    mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'",
                        ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'",
                        ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'",
                        ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'",
                        ugr_interests="'.$interests.'" WHERE ugr_ID=2');
                    // TODO: error check, although this is unlikely to fail

}

                    echo "<h2>Congratulations, your new database '$newDBName' has been created</h2>";

                    echo "<p><strong>Admin username:</strong> ".$name."<br />";
                    echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;</p>";

                    echo "<p>Click here to log in to your new database: <p style=\"padding-left:6em\"><b><a href=\"#\"".
                    " title=\"\" onclick=\"closeDialog('".$newDBName."'); return false;\">".
                    HEURIST_BASE_URL_V4."?db=".$newDBName.
                    "</a></b>&nbsp;&nbsp;&nbsp;&nbsp; <i>(we suggest bookmarking this link)</i></p>";

                    echo "<p style=\"padding-left:6em\">".
                    "After logging in to your new database, we suggest you import some additional entity types from one of the<br />".
                    "curated Heurist databases, or from one of the other databases listed in the central database catalogue,<br />".
                    "using Database &gt; Import Structure or Database &gt; Annotated Templates</p>";

                    // TODO: automatically redirect to the new database in a new window
                    // this is a point at which people tend to get lost

                    return false;
                } // isset

            } //makedatabase
?>
        </div>
    </body>
</html>