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

    $msg = "Ambiguous database name, or no database name supplied";
    if(@$_REQUEST['msg']){
        $msg = $_REQUEST['msg'];
    }

    // Globals to pass back the queries executed in case we need to report the error
    $query_create = "";
    $query_owner  = "";

    $sandpitDB = "H3Sandpit";

    function buildSandpitDB($dbName){  // creates the sandpit database as a starting point for users to register and create databases
        
        echo_flush ("<p>Create new empty database</p>");
        
        if(!createDatabaseEmpty($dbName)){
            return false;
        }
        createDatabaseFolders($dbName);
        
        return true;
        
    } //buildSandpitDB
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
                                // successful: sandpit database has been created - now proceed to login
                                print "<h2>Initialisation: $sandpitDB database created</h2>";
                                print "<br />This database is required to allow initial login and to manage registration of new users";
                                print "<br />These will allow the owner to login and approve new registrations on the $sandpitDB database";
                                print "<br /><br /><input type='button' onclick='{location.href=\"".HEURIST_BASE_URL
                                        ."/common/connect/login.php?db=$sandpitDB\"}' value='Proceed to registration form'/>";
                            } else {
                                print "<p color='red'>Unable to create sample database $sandpitDB</p><p>Please contact Heurist developers for help</p>";
                            }
                        } // no databases, creation of sandpit database
                    ?>
                </ul>
            </div>
        </div>
    </body>

</html>



