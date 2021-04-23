<?php

/**
* createNewDB.php: Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

define('PDIR','../../../');  //need for proper path to js and css    
    
require_once(dirname(__FILE__)."/../../../hsapi/System.php");

$error_msg = '';
$isSystemInited = false;

sanitizeRequest($_REQUEST);

// init main system class
$system = new System();
$current_user = null;

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    if(!$system->init(@$_REQUEST['db'])){
        include dirname(__FILE__).'/../../../hclient/framecontent/infoPage.php';
        exit();
    }
    $registrationRequired = false;
    
    $current_user = $system->getCurrentUser();
}else{
    $registrationRequired = true;
}
?>
<html>
    <head>
        <title>Create New Heurist Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- jQuery UI -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php'; ?>

        <!-- Heurist JS -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_edit.js"></script>

        <script type="text/javascript">

                //registration data can be assigned via url parameters
                var edit_data = {};
                
<?php
    if(@$_REQUEST['name']){
          $names = explode(' ', $_REQUEST['name']);
          $_REQUEST['ugr_FirstName'] = $names[0];
          if(count($names)>0) $_REQUEST['ugr_LastName'] = $names[1];
          $isRegdataFromParams = true;
    }
    if(@$_REQUEST['email']){
          $_REQUEST['ugr_eMail'] = $_REQUEST['email'];
          $isRegdataFromParams = true;
    }
    if(@$_REQUEST['interests']){
          $_REQUEST['ugr_Interests'] = $_REQUEST['interests'];
          $isRegdataFromParams = true;
    }
                    
    //restore USER registration parameters in case creation fails
    foreach ($_REQUEST as $param_name => $param_value){
        if(strpos($param_name,'ugr_')===0){ //&& $param_name!='ugr_Password' keep password
            //print '<input id="'.$param_name.'" name="'.$param_name.'" value="'.$param_value.'">';
            print "edit_data['$param_name'] = '$param_value';";
        }
    }
?>
                var isRegdataEntered = !$.isEmptyObject(edit_data);
                
                $(document).ready(function() {

                    if($("#createDBForm").length>0){
                        if(!window.hWin.HAPI4) window.hWin.HAPI4 = new hAPI(null); //init hapi without db
                        var prefs = window.hWin.HAPI4.get_prefs();
                        
                        window.hWin.HAPI4.sysinfo.sysadmin_email = '<?php echo HEURIST_MAIL_TO_ADMIN;?>';
                        //loads localization
                        window.hWin.HR = window.hWin.HAPI4.setLocale(prefs['layout_language']);

                        $("#btnRegister").button({label:'Register as User'}).on('click', doRegister);
                        $("#btnCreateDb").button().on('click', doCreateDatabase);

<?php 
if($passwordForDatabaseCreation){ //deined on confiigIni.php
        print '$("#div_need_password").show();';
}

if($registrationRequired) //show user registration dialog at once
{
?>
                        if(!isRegdataEntered){
                            doRegister();
                            
                            $("#div_register").show();
                            $("#div_register_entered").hide();
                            $("#div_create").hide();
                        }else{
                            onRegisterDialogClose(edit_data); //proceed with data from url parameteres
                        }
<?php                        
}else{
?>
                        //propose part of databasename as username
                        setUserPartOfName( '<?php echo $current_user['ugr_Name'];?>' );
<?php    
}
?>
                    }
                });


                var profile_edit_dialog = null;

                //
                // transfer data from edit profile to registration form
                //
                function onRegisterDialogClose(new_edit_data){

                    if(!new_edit_data){
                        new_edit_data = profile_edit_dialog.profile_edit('option','edit_data');
                    }

                    var frm = $("#createDBForm");
                    for (var fld_name in new_edit_data) {
                        if (new_edit_data.hasOwnProperty(fld_name)) {
                            var fld = frm.find('#'+fld_name);
                            if(fld.length>0){
                                fld.val( new_edit_data[fld_name] );
                            }else{
                                $('<input>', {id:fld_name, type:'hidden', name:fld_name, value:new_edit_data[fld_name]} ).appendTo(frm);
                            }
                        }
                    }
                    
                    $("#div_register").hide();
                    $("#div_register_entered").show();
                    $("#div_create").show();
                    setUserPartOfName( document.getElementById("ugr_Name").value );
                }

                //
                // open registration dialogue
                //
                function doRegister(event){

                    window.hWin.HEURIST4.util.stopEvent(event);

                    if($.isFunction($('body').profile_edit)){
                        
                        //@todo replace with entity sysUsers
                        if(profile_edit_dialog==null){
                            profile_edit_dialog = $('#heurist-profile-dialog');
                            if(profile_edit_dialog.length<1){
                                profile_edit_dialog = $( '<div id="heurist-profile-dialog">' )
                                        .addClass('ui-heurist-bg-light')
                                        .appendTo( $('body') );
                            }
                            profile_edit_dialog.profile_edit({'ugr_ID': -1, 
                                needclear:false, 
                                edit_data:edit_data,
                                callback:onRegisterDialogClose  });
                        }else{
                            profile_edit_dialog.profile_edit('open');
                        }


                    }else{
                        $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_edit.js', function() {
                            if($.isFunction($('body').profile_edit)){
                                doRegister();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr('Widget "Profile edit" cannot be loaded!');
                            }
                        });
                    }

                }
                
                //
                // 
                //
                function doCreateDatabase(){
                    //get all inputs and send request to server
                    
                    var url = '<?php echo HEURIST_BASE_URL; ?>admin/setup/dboperations/createDB.php';
                    
                    var request = {db:window.hWin.HEURIST4.util.getUrlParameter('db')};
                    var inputs = $("#createDBForm").find('input');
                    inputs.each(function(idx, inpt){
                        if($(inpt).attr('type')=='radio'){
                            if ($(inpt).is(':checked')){
                                request[$(inpt).attr('name')] =  $(inpt).val();
                            }
                        }else if($(inpt).attr('name') && $(inpt).val()){
                            request[$(inpt).attr('name')] =  $(inpt).val();
                        }
                    });

                    $("#createDBForm").hide();
                    showProgress( true );
                    
                    window.hWin.HEURIST4.util.sendRequest(url, request, null,
                        function(response){
                            
                            hideProgress();
                            
                            if(response.status == window.hWin.ResponseStatus.OK){

                                $("#div_result").show();
                                $('#newdbname').text(response.newdbname);
                                $('#newusername').text(response.newusername);
                                $('#newdblink').attr('href',response.newdblink).text(response.newdblink);
                                
                                if(response.warnings && response.warnings.length>0){
                                    $('#div_warnings').html(response.warnings.join('<br><br>')).show();
                                    $('#div_login_info').hide();
                                }
                                   
                                //clear local list of databases   
                                if(window.hWin && window.hWin.HAPI4){
                                    window.hWin.HAPI4.EntityMgr.emptyEntityData('sysDatabases');
                                }    
                                
                            }else{
                                $("#createDBForm").show();
                                
                                //either wrong captcha or invalid registration values
                                if(response.status == window.hWin.ResponseStatus.ACTION_BLOCKED){
                                    doRegister();
                                }
                                
                                window.hWin.HEURIST4.msg.showMsgErr(response, false);
                            }
                        }
                    );
                    
                }

            //to remove - show coverall instead
            function hideProgress(){
                var ele = document.getElementById("loading");
                if(ele){
                    ele.style.display = "none";
                }
            }

            //
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

            //
            //allow only alphanumeric characters for db name
            //
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
            function setUserPartOfName( user_name ){
                var ele = document.getElementById("uname");
                if(true || ele.value==""){
                    ele.value = user_name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,'');
                }

                $("#dbname").focus();
            }

            //
            //
            //
            function closeDialog(){
                <?php echo (@$_REQUEST['popup']=="1"?"setTimeout(function(){window.close();},1500)":"") ?>
            }


        </script>
        
        <style>
            .detailType {width:180px !important}
            h2 {padding:10px; margin:0}
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
            /*
            .truncate {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            */
        </style>
    </head>

    <body class="popup ui-heurist-admin">
    
        <!-- div class="ui-corner-all  ui-widget-content"
                style="text-align:left; width:70%; min-height:550px; margin:0px auto; padding: 0.5em;background:white;" -->
        
<?php
            if($registrationRequired){
                print '<div class="logo" style="background-color:#2e3e50;width:100%;margin-bottom:20px"></div>';
            }else{
                //<div class="banner"><h2>Register Database with Heurist Master Index</h2></div>
            }
?>

        <div id="page-inner" style="overflow:auto;<?php echo (@$_REQUEST['popup']=='1'?'top:10px;':'') ?>">
            <div id="loading" style="display:none">
                <img alt="creation ..." src="../../../common/images/mini-loading.gif" width="16" height="16" />
                <strong><span id="divProgress">&nbsp; Creation of database will take a few seconds </span></strong>
            </div>

            <div id="createDBForm" style="padding-top:5px;">

                <h3 style="margin-left:40px" class="ui-heurist-title">Creating new database on server</h3>

                <input type="hidden" id="url_template" name="url_template">
                <input type="hidden" id="exemplar" name="exemplar">

                <div style="margin-left:40px">
                    <label for="rb1" class="labelBold ui-heurist-title">
                        <input type="radio" name="dbtype" value="0" id="rb1" checked 
                            onclick="{$('#registered_dbs').hide()}" style="vertical-align:-0.2em"/>
                        Standard database template
                    </label>
                    <div style="padding:10px 0 10px 26px;width:600px">
                        Gives an uncluttered database with essential record types, fields,
                        terms and relationships, including bibliographic and spatial entities.
                        Recommended for most new databases unless you wish to copy a particular template (next option).
                    </div>

                    <!-- Added training database 12 Feb 2016 -->
                    <!-- TODO: referencing Shakespeare exemplar database by name will fail on other servers - should use registered ID-->
                    <!-- TODO: would be better to import the Shakespeare (or other) exemplar data directly from HML although this will expose all public data to easy copying -->
                    <!-- Note: the value for the radio button indicates the name of the database whose
                    structure will be copied. Data is then inserted from the file <dbname>_data.sql -->
                    <label for="rb3" class="labelBold ui-heurist-title">
                        <input type="radio" name="dbtype" value="Heurist_Shakespeare_Exemplar" id="rb3" 
                            disabled style="vertical-align:-0.2em"/>
                        Example database (Shakespeare's plays)
                    </label>

                    <div style="padding:10px 0 10px 26px;width:600px">
                        THIS DATABASE IS CURRENTLY UNDER DEVELOPMENT - PLEASE DON'T USE THIS OPTION
                        <br/>
                        A training database consisting of interlinked information about Shakespeare's plays, company, actors, theatres, performances etc.
                        Use this database as a starting point for becoming familiar with Heurist<br>(See <a href="http://HeuristNetwork.org/screencasts" target="_blank">introductory video</a>).
                    </div>

                    <div id="nextSteps" style="padding:10px 0 10px 26px;width:600px;display:none">
                        <div class="labelBold ui-heurist-title">Suggested next steps</div>
                        <div>
                            <br />After the database is created, we suggest visiting Manage &gt; Structure &gt; Browse templates
                            to download pre-configured templates or individual record types and fields from databases registered with the Heurist Network.
                            <br />New databases are created on the current server. You will become the owner and administrator of the new database.
                        </div>
                    </div>
                </div>

                <div style="border-bottom: 1px solid #7f9db9;"></div>
                
                <div id="div_register_entered" style="display: none;">
                    <h3 style="margin:5px 0 10px 38px;color:darkgreen;">Registration information entered</h3>
                </div>

                <div id="div_create" style="margin-left:40px" >

                    <div id="div_need_password" style="display:none">
                        <h3 class="ui-heurist-title">Enter the password set by your system administrator for new database creation</h3>
                        <div style="margin-top: 0px;">
                            <input type="password" maxlength="64" size="25" id="create_pwd" name="create_pwd">
                        </div>
                    </div>

                    <h3 class="ui-heurist-title">Enter a name for the new database</h3>
                    <div style="margin-top: 0px;">
                        <!-- user name used as prefix -->
                        <b> <!-- This is irrelevant to the user removed Ian 23/4/21 < ?= HEURIST_DB_PREFIX ?> -->
                            <input type="text" maxlength="30" size="6" name="uname" id="uname"
                                onkeypress="{onKeyPress(event)}"
                                style="padding-left:3px; font-weight:bold;">
                        </b>
                        <b>_</b>
                        <input type="text" maxlength="64" size="30" id="dbname" name="dbname"  onkeypress="{onKeyPress(event);}">
                        <input id="btnCreateDb" value="Create Database" style="font-weight: bold;" class="ui-button-action" >
                        <div class="heurist-helper3" style="padding-top:0em; max-width:500px">
                            <i>optional &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; required</i><br>
                        </div>
                        <div style="padding-top:1em; max-width:500px">
                            No spaces or punctuation other than underscore. Database names are case-sensitive.
                        </div> 
                        <div class="heurist-helper3" style="padding-top:1em; max-width:500px">
                            The user name prefix is editable, and may be left blank, but we suggest using a consistent prefix 
                            for personal databases so that they are easily identified and appear together in the list of databases.
                        </div>
                    </div>

                </div>


                <div id="div_register" style="margin-left:40px; display: none;">
                    <!-- h3 style="margin-left: 38px">Define Database Administrator</h3 -->
                    <div style="margin-top: 30px;">
                        <button id="btnRegister" style="font-weight: bold;" />
                    </div>
                    <div style="margin-top: 20px; margin-bottom: 20px;" class="heurist-helper3">
                        Please register in order to define the user who will become the database owner and administrator.<br><br>
                        If you are already a user of another database on this server, we suggest logging into that database (<a href="../../../index.php">select database here</a>)<br/>
                        and creating your new database with Database &gt; New Database, as this will carry over your login information from the existing database.
                    </div>
                </div>

            </div> 
            <!-- createDBForm -->
            
        <!-- /div -->
        
        <div id="div_result" style="margin-left:40px;display:none">
            <h4 style='padding-bottom:10px;margin:0' class="ui-heurist-title">Congratulations, your new database  [ <span id="newdbname"></span>  ]  has been created</h4>
            <div id="div_login_info">
                <div><strong>Admin username:</strong> <span id="newusername"></span></div>
                <div><strong>Admin password:</strong> &#60;<i>same as the account you are currently logged in as</i>&#62;</div>
            </div>
            <div class="ui-state-error" id="div_warnings" style="display:none;padding:10px"></div>
            <div style="padding-top:20px">Log into your new database with the following link:</div>
            <div style="padding-top:20px"><b><a id="newdblink" href="#" oncontextmenu="return false;" 
                        title="" onclick="{closeDialog()}" target="blank"></a></b>&nbsp;&nbsp;&nbsp;&nbsp; <i>(we suggest bookmarking this link)</i></div>

            <div style="padding-top:20px;width:600px">
                After logging in to your new database, we suggest you import some additional entity types from one of the
                curated Heurist databases, or from one of the other databases listed in the central database catalogue,
                using Database &gt; Structure &gt; Browse templates 
                <!--or Database &gt; Structure &gt; Acquire from Templates -->
            </div>
        </div>        
    </body>
</html>
