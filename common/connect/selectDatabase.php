<?php

    /**
    * selectDatabase.php: select database from list of databases, create and configure H3Sandpit if it doesn't exist
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Stephen White
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    // TODO: Why are these needed, what are they doing here? Noit used in this file. Need doco if they are to be here.
    define('SKIP_VERSIONCHECK2', 1);
    define('SKIP_VERSIONCHECK', 1);
    define('NO_DB_ALLOWED',1);

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');
    require_once(dirname(__FILE__).'/../../common/php/dbScript.php');

    $msg = "Ambiguous database name, or no database name supplied";
    if(@$_REQUEST['msg']){
        $msg = $_REQUEST['msg'];
    }

    // Globals to pass back the queries executed in case we need to report the error
    $query_create = "";
    $query_owner  = "";

    $sandpitDB = "H3Sandpit";

    function buildSandpitDB($dbName){  // creates the sandpit database as a starting point for users to register and create databases
        global $query_create, $dbPrefix;
        
        $full_dbname = $dbPrefix.$dbName;
        
                    if(!db_create($full_dbname)){
                        return false;
                    }

                    if(!db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/buildExampleDB.sql")){
                        return false;
                    }
/*
                    if(!db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/addReferentialConstraints.sql")){
                        return false;
                    }

                    if(!db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/addProceduresTriggers.sql")){
                        return false;
                    }
*/                    

/* OLD APPROACH        
        // create database
        $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e 'create database ".$dbPrefix.$dbName."';";
        $output2 = exec($cmdline . ' 2>&1', $output, $res);
        if ($res != 0 ) { // could not create - probably wrong db permissions
            $query_create = str_replace(ADMIN_DBUSERPSWD, "xxxxxxxxxxx", $cmdline); // don't display mysql root pwd
            return $res;
        }
        // populate database
        $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$dbPrefix.$dbName ."< /var/www/html/HEURIST/h3/admin/setup/dbcreate/buildExampleDB.sql";
        $query_create = str_replace(ADMIN_DBUSERPSWD, "xxxxxxxxxxx", $cmdline); //in case we need error message, don't display pwd
        $output2 = exec($cmdline . ' 2>&1', $output, $res);
        return $res;
*/        
    } //buildSandpitDB

    function insertOwner($dbName, $LoginName, $Password, $eMail){ // inserts new credentials for the database owner
        global $query_owner, $dbPrefix;
        
        
                    mysql_connection_insert($dbPrefix.$dbName);

                    // Make the current user the owner and admin of the new database
                    mysql_query('UPDATE sysUGrps SET ".
                        ugr_eMail="'.mysql_real_escape_string($eMail).'", ugr_Name="'.mysql_real_escape_string($name).'",
                        ugr_Password="'.mysql_real_escape_string($password).'" WHERE ugr_ID=2');

                    if (mysql_error()) {
                        $query_owner = mysql_error();
                        return false;
                    }
                    
                    return true;
        
/* OLD APPROACH        
        $cmdline='mysql -u'.ADMIN_DBUSERNAME.' -p'.ADMIN_DBUSERPSWD.' '.$dbPrefix.$dbName.' -e "'.
        "update sysUGrps set ugr_Name='$LoginName' where ugr_ID=2;".
        "update sysUGrps set ugr_Password='$Password' where ugr_ID=2;".
        "update sysUGrps set ugr_eMail='$eMail' where ugr_ID=2;" . "\";" ;
        $query_owner = str_replace(ADMIN_DBUSERPSWD, "xxxxxxxxxxx", $cmdline); //in case we need error message, don't display pwd
        $output2 = exec($cmdline . ' 2>&1', $output, $res);
        return $res;
*/        
    } // insertOwner

    // TODO: Copied from resetUserPassword.php. This bit of code occurs in half a dozen places. It needs to be centralised in a library.
    function hash_it ($passwd) {
        $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
        $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
        return crypt($passwd, $salt);
    } // hash_it

?>

<html>

    <head>
        <title>Heurist Academic Knowledge Management System - unknown or missing database name</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel=stylesheet type='text/css' href='../../common/css/global.css'>
        <link rel=stylesheet type='text/css' href='../../common/css/admin.css'>
        <link rel=stylesheet type='text/css' href='../../common/css/login.css'>
    </head>

    <body>
        <div id=page style="padding: 20px;">

            <div id="logo" title="Click the logo at top left of any Heurist page to return to your default search">
            </div>

            <div align="left" style="margin-left: 20px;">
                <h2><?=$msg?></h2>
                <br>Please select a database from the list. For further information on Heurist please visit
                <b> <a href="http://HeuristNetwork.org" target="_blank">http://HeuristNetwork.org</a></b>
            </div>

            <div id="loginDiv" style="height:auto; margin-top:44px; overflow-y:auto;text-align:left;">
                <ul class='dbList'>
                    <?php

                        // Step 1 - mode not set - normally just list databases and select database
                        $list = mysql__getdatabases(false);
                        $i = 0;
                        foreach ($list as $name) {
                            print("<li><a href='".HEURIST_BASE_URL."?db=$name'>$name</a></li>");
                            $i++;
                        }

                        // but check to see if any databases have ben listed. if not it's an initialised instance, need to create sandpit db
                        if ( $i == 0 ) { // no database can be created until sandpit db exists, so this is an adequate test
                            $res = buildSandpitDB($sandpitDB); // TODO: supply prefix from code rather than hardcoded
                            
                            
                            //if ($res != 0 ) { //error
                            //    print "<h2>Unable to create $sandpitDB example database<h2> SQL error:".$res.
                            //   "Query: ".$query_create." <p>Please contact Heurist developers for help";
                            if($res){
                                // successful: sandpit database requires configuration of owner user name, email and password
                                if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('db', $_REQUEST)){
                                    print "<h2>Initialisation: $sandpitDB database created</h2>";
                                    print "<br />This database is required to allow initial login and to manage registration of new users";
                                    print "<p>Please enter identification details for the owner of this Heurist instance";
                                    print "<br />These will allow the owner to login and approve new registrations on the $sandpitDB database";
                                    print "<p>";
                                    print "<form name='getSandpitOwner' action='selectDatabase.php' method='post'>";
                                    print "<input name='mode' value='2' type='hidden'>"; // mode 2 = process user update info enterd in form
                                    print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
                                    print "<div style=\"padding:5px;\">";
                                    print "<b>Sysadmin / Master user information:</b><br /><br />";
                                    print "Username:&nbsp;<input type='text' name='username' id='username' size='20' ><br /><br />";
                                    print "Password:&nbsp;<input type='password' name='password' size='20' ><br /><br />";
                                    print "Email addr:&nbsp;<input type='text' name='email' id='email' size='50' ><br /><br />";
                                    print "</div>";
                                    print "<br />&nbsp;&nbsp;<input type='submit' value='Set owner details'/>";
                                    print "</form>";
                                    exit;
                                } // form to collect user details
                            } // else

                        } // no databases, creation of sandpit database

                        // -----------------------------------------------------------------------------------------------

                        if(@$_REQUEST['mode']=='2'){ // for sandpit database, insert the owner information collected in step 1

                            $Username = $_REQUEST['username'];
                            $Password = $_REQUEST['password'];
                            $eMail = $_REQUEST['email'];

                            $Password = hash_it ($Password); // encrypt password

                            // update the owner email, username and password in the sandpit database
                            $res=insertOwner($sandpitDB,$Username,$Password,$eMail); // TODO: supply prefix from code rather than hardcoded
                            if (!$res) { //error
                                print "<h2>Unable to update owner email and password for $sandpitDB database<h2> SQL error:".$res;
                                "<p>Please contact Heurist developers for help</p>";
                            }

                        } // mode = 2

                    ?>
                </ul>
            </div>
        </div>
    </body>

</html>



