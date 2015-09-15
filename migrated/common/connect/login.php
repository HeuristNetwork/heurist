<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

define('SKIP_VERSIONCHECK2', 1);

define('SAVE_URI', 'disabled');
require_once(dirname(__FILE__).'/applyCredentials.php');
require_once(dirname(__FILE__).'/../php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../php/dbUtils.php');
//require_once('applyCredentials.php');
//require_once('../php/dbMySqlWrappers.php');
session_start();

$last_uri = urldecode(@$_REQUEST['last_uri']);

// TODO: should this be deleted. Is it useful?
//if (! $last_uri)
//	$last_uri = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['last_uri'];
if (! $last_uri) {
    if (@$_SERVER['HTTP_REFERER']  &&  strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME']) === false) {
        $last_uri = $_SERVER['HTTP_REFERER'];
    }else if (defined('HEURIST_BASE_URL')) {
        $last_uri = HEURIST_BASE_URL.'?db=' . HEURIST_DBNAME;
    }
}

if (@$_REQUEST['logout']) {
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_realname']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_systemadmin']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['sessionid']);

    unset($_SESSION[DATABASE]['ugr_ID']); //unset h4

    setcookie('favourites', '', time() - 3600);

    header('Location: login.php?db='.HEURIST_DBNAME . ($last_uri ? '&last_uri=\'' . urlencode($last_uri).'\'' : ''));

    return;
}

function hash_it ($passwd) {
    $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
    $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
    return crypt($passwd, $salt);
} // hash_it

$needRegistration = true;

if(@$_REQUEST['registration']==1){
    $username = @$_REQUEST['username'];
    $password = @$_REQUEST['password'];

    if(!$username){
        $LOGIN_ERROR = 'Username is mandatory';
    }
    if(!$password){
        $LOGIN_ERROR = 'Password is mandatory';
    }
    if(strlen($username)<5){
        $LOGIN_ERROR = 'Username must be 5 characters at least';
    }
    if(strlen($password)<5){
        $LOGIN_ERROR = 'Password must be 5 characters at least';
    }

    $eMail =    @$_REQUEST['email'];

    mysql_connection_insert(USERS_DATABASE);

    // Make the current user the owner and admin of the new database
    mysql_query('UPDATE '.USERS_TABLE.' SET '
        .($eMail?' ugr_eMail="'.mysql_real_escape_string($eMail).'", ':'')
        .'ugr_Name="'.mysql_real_escape_string($username).'", '
        .'ugr_Password="'.mysql_real_escape_string(hash_it($password)).'" WHERE ugr_ID=2');

    if (mysql_error()) {
        print "<h2>Unable to update owner email and password for database<h2> SQL error:".mysql_error();
        "<p>Please contact Heurist developers for help</p>";
    }else{
        $needRegistration = false;
        $_REQUEST['username'] = $username;
        $_REQUEST['password'] = $password;
    }

}else{
    mysql_connection_select(USERS_DATABASE);
    //verify that this database has proper owner userId=2
    $res = mysql_query('select ugr_Password from '.USERS_TABLE.' where '.USERS_ID_FIELD.' = 2');
    $needRegistration = ( ($user = mysql_fetch_assoc($res)) && ($user[USERS_PASSWORD_FIELD]=="TO BE RESET") );
}


if(!$needRegistration){
    $LOGIN_ERROR = '';
    if (@$_REQUEST['username']  or  @$_REQUEST['password']) {

        $res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.mysql_real_escape_string($_REQUEST['username']).'"');
        if ( ($user = mysql_fetch_assoc($res))  &&
        $user[USERS_ACTIVE_FIELD] == 'y'  &&
        crypt($_REQUEST['password'], $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD] ) {

            $groups = reloadUserGroups($user[USERS_ID_FIELD]);

            $groups[$user[USERS_ID_FIELD]] = 'member'; // a person in a member of his own user type group, not admin as can't add users to this group

            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['cookie_version'] = COOKIE_VERSION;
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_name'] = $user[USERS_USERNAME_FIELD];
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_realname'] = $user[USERS_FIRSTNAME_FIELD] . ' ' . $user[USERS_LASTNAME_FIELD];
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_id'] = $user[USERS_ID_FIELD];
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'] = $groups;
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_systemadmin'] = (defined('HEURIST_MAIL_TO_ADMIN') && (@$user[USERS_EMAIL_FIELD]==HEURIST_MAIL_TO_ADMIN))?"1":"0";


            $time = 0;
            if ($_REQUEST['session_type'] == 'public') { //expire on exit
                $time = 0;
            } else if ($_REQUEST['session_type'] == 'shared') {
                $time = time() + 24*60*60;
            } else if ($_REQUEST['session_type'] == 'remember') {
                //Besides make sure thah php.ini session.gc_maxlifetime set to the similar value
                $time = time() +  30*24*60*60; //remember for 30 days
                $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['keepalive'] = true;
            }
            setcookie('heurist-sessionid', session_id(), $time, '/'); //, HEURIST_DOMAIN);

            /* bookkeeping */
            mysql_connection_overwrite(USERS_DATABASE);
            mysql_query('update sysUGrps usr set usr.ugr_LastLoginTime=now(), usr.ugr_LoginCount=usr.ugr_LoginCount+1
                where usr.ugr_ID='.$user[USERS_ID_FIELD]);

            mysql_connection_select(USERS_DATABASE);

            checkDatabaseFunctions();

            if (@$last_uri){
                header('Location: ' . $last_uri);
            }

        } else {
            $LOGIN_ERROR = 'Incorrect Username / Password - try email address for user name';
        }

    }
}



?>
<html>
    <head>
        <title>Heurist Login</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel=icon href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>
        <link rel="shortcut icon" href='<?=HEURIST_SITE_PATH?>favicon.ico' type=image/x-icon>

        <link rel=stylesheet type=text/css href='<?=HEURIST_SITE_PATH?>common/css/global.css'>
        <link rel=stylesheet type=text/css href='<?=HEURIST_SITE_PATH?>common/css/login.css'>



    </head>

    <body onload="if (elt=document.getElementById('username')) elt.focus();">
        <script>
            if (window.parent != window) {
                <?php if (@$last_uri) { ?>
                    top.location = "login.php?db=<?=HEURIST_DBNAME?>&last_uri=<?=urlencode($last_uri)?>";
                    <?php } else { ?>
                    top.location = window.location;
                    <?php } ?>
            }


            function onKeyPress(event) {
                var charCode;

                if(event && event.which){
                    charCode = event.which;
                }else if(window.event){
                    event = window.event;
                    charCode = event.keyCode;
                }

                if(charCode == 13 && document.getElementById('password').value && document.getElementById('username').value) {
                    document.forms['mainform'].submit();
                }
            }

        </script>
        <script src='../../common/js/utilsLoad.js'></script>
        <script src='../../common/js/utilsUI.js'></script>


        <div id=page style="padding: 20px;">

            <a id="home-link" href='../../'  title="Click the logo at top left of any Heurist page to return to your main page">
                <div id="logo">&nbsp;</div>
            </a>

            <div><h1>PLEASE LOGIN or <a href='../../' style='font-size:16px'>SEARCH</a></h1></div>

            <form name=mainform method=post>

                <div id="loginDiv">

                    <?php
                    echo "<input type=hidden name=last_uri value={$last_uri}>\n";

                    //echo "SESSION=".print_r(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'],true)."<br>";
                    //  var/log/apache2
                    //  var/www

                    if (! is_logged_in() ) {
                        ?>

                        <table cellpadding=3 id="login-table" border="0">
                            <?php
                            if(@$_REQUEST['register']==1 && HEURIST_DBNAME=="Heurist_Sandpit"){
                                ?>
                                <tr class="input-row">
                                    <td colspan="2" style="padding-left:50px">
                                        <b>Please enter the username and password you used to register</b>
                                    </td>
                                </tr>
                                <?php
                            }else{
                                ?>
                                <tr class="input-row">
                                    <td class="input-header-cell">Database name</td>
                                    <td class="input-cell" style="padding-top:0px !important;"><h2 style="font-size: 20px"><?php echo HEURIST_DBNAME; ?></h2></td>
                                </tr>

                                <tr class="input-row">
                                    <td class="input-header-cell">Prefix</td>
                                    <td class="input-cell"><?php echo HEURIST_DB_PREFIX; ?></td>
                                </tr>

                                <?php
                            }

                            if (@$LOGIN_ERROR){
                                echo '<tr class="input-row"><td colspan="2"><p style="margin-left: 100px; color: red;">'.$LOGIN_ERROR.'</p></td></tr>';
                            }
                            if($needRegistration){
                                ?>
                                <tr class="input-row">
                                    <td colspan="2">
                                        This database is required to allow initial login and to manage registration of new users
                                        <br />Please enter identification details for the owner of this Heurist instance
                                        <br />These will allow the owner to login and approve new registrations on database
                                        <input type="hidden" name="registration" id="registration" value="1">
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>

                            <tr class="input-row">
                                <td class="input-header-cell">Username</td>
                                <td class="input-cell"><input type="text" name="username" id="username" size="20" class="in" onkeypress="onKeyPress(event)"><br/>email address by default</td>
                            </tr>

                            <tr class="input-row">
                                <td class="input-header-cell">Password</td>
                                <td class="input-cell"><input type="password" name="password" id="password" size="20" class="in" onkeypress="onKeyPress(event)"><br/>case sensitive</td>
                            </tr>

                            <?php
                            if($needRegistration){
                                ?>
                                <tr class="input-row">
                                    <td class="input-header-cell">Email</td>
                                    <td class="input-cell"><input type="text" name="email" id="email" size="20" class="in"><br/></td>
                                </tr>
                                <?php
                            }
                            ?>

                            <tr class="input-row">
                                <td class="input-header-cell"></td>
                                <td class="input-cell"></td>
                            </tr>

                            <tr class="input-row">
                                <td class="input-header-cell"></td>
                                <td class="input-cell"><input type="radio" name="session_type" value="public">&nbsp;Expire on browser close (public computer)<br>
                                    <input type="radio" name="session_type" value="shared">&nbsp;Expire on user logout (shared computer)<br>
                                    <input type="radio" name="session_type" value="remember" checked>&nbsp;Remember me on this computer (your computer)</td>
                            </tr>

                            <tr><td colspan="2"></td></tr>

                            <tr>
                                <td align="right">&nbsp;</td>
                                <td align="left">
                                    <div id=login-button><a href="#" onclick="{document.forms['mainform'].submit()}" title="Log in to use Heurist - new users please register first">
                                        <img src=../images/111x30.gif></a></div>
                                    <?php if(false && !$needRegistration && defined('HEURIST_ALLOW_REGISTRATION') && HEURIST_ALLOW_REGISTRATION){?>
                                        <div id=register-button><a href="#"
                                            onclick="{top.HEURIST.util.popupURL(top, '../../admin/ugrps/editUser.html?db=<?=HEURIST_DBNAME?>', {height:640, width:700});return false}"
                                            title="Register to use Heurist - takes only a couple of minutes"><img src=../images/111x30.gif></a></div>
                                        <?php
                                    }
                                    //onclick="window.open(this.href,'','status=0,scrollbars=0,width=500,height=200'); return false;"
                                    ?>
                                </td>
                            </tr>

                        </table>

                        <p align=center>
                            Forgotten your password?
                            &nbsp;
                            <a href='#'
                                onclick="{top.HEURIST.util.popupURL(top, '../../admin/ugrps/resetUserPassword.php?db=<?=HEURIST_DBNAME?>', {height:200, width:400});return false}">
                                Click here to reset your password</a>
                        </p>
                        <?php if(!$needRegistration && defined('HEURIST_ALLOW_REGISTRATION') && HEURIST_ALLOW_REGISTRATION){?>
                            <p align=center>
                                <a href='#'
                                    onclick="{top.HEURIST.util.popupURL(top, '../../admin/ugrps/editUser.html?db=<?=HEURIST_DBNAME?>', {height:620, width:740});return false}">
                                    Don't have a login? Click here to register</a>
                            </p>
                            <?php } ?>





                        <?php
                    } else {
                        ?>


                        <table id=login_table>
                            <tr><td>
                                    <p>You are currently logged-in as <b><?= get_user_name() ?> (<?= get_user_username() ?>)</b></p>

                                    <p><b><a href="<?=HEURIST_BASE_URL?>">Heurist home</a></b></p>

                                    <?php
                                    if ($last_uri)
                                        echo "<p><b><a href=\"{$last_uri}\">Return to application</a></b></p>\n\n";
                                    ?>

                                    <p><b><a href='login.php?db=<?=HEURIST_DBNAME?>&logout=1'>Log out</a></b></p>

                                    <br>

                                    <p>
                                        If you did not specifically request the login page, you may be seeing this page<br>
                                        because you don't have sufficient access level for the function requested,<br>
                                        or do not belong to the group of users who own it.
                                    </p>

                                    <?php
                                    $query = mysql_query("SELECT ugr_FirstName, ugr_LastName, ugr_eMail FROM sysUGrps WHERE ugr_ID=2");
                                    $details = mysql_fetch_row($query);
                                    $fullName = $details[0] . " " . $details[1];
                                    $eMail = $details[2];
                                    print "<p>Please log in as an administrator or contact <a href=\"mailto:$eMail\">$fullName</a>".
                                    "to request a higher<br> level of access or membership of the group of owners of this function.".
                                    "<br>If you have lost access rights that you think you should have, please log out and log back in again.</p>"
                                    ?>

                                </td></tr>
                        </table>

                        <?php
                    }
                    ?>

                </div>

            </form>

        </div>
    </body>
</html>
