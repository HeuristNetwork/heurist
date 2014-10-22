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
* A couple of function to create, delelet, clean the entire HEURIST database
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage DataStore
* @todo       funnel all calls to functions in this file. Not all system code uses there abstractions. Especially services.
*/

/**
*
* Function list:
* - server_connect()
* - db_create()
* - db_drop()
* - db_dump()
* - db_clean()
* - db_script()  - see dbScript.php
*/

function server_connect($verbose = true){

    $mysqli = new mysqli(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
    // Check connection
    if (mysqli_connect_errno()) {
        if($verbose) echo ("<p class='error'>Failed to connect to MySQL database server: ".mysqli_connect_error()."<br/></p>");
        return false;
    }

    return $mysqli;
}


function db_create($db_name, $verbose = true){

    $res = false;

    // Avoid illegal chars in db 
    if (preg_match('[\W]', $db_name)){
        echo ("<p class='error'>Error. Only letters, numbers and underscores (_) are allowed in the database name<br/></p>");
    }else{

        $mysqli = server_connect();
        if($mysqli){
            // Create database
            $sql = "CREATE DATABASE ".$db_name;
            if ($mysqli->query($sql)) {
                $res = true;
            }else if($verbose) {
                echo ("<p class='error'>Error ".$mysqli->error.". Unable to create database $db_name<br/></p>");   
            }
            $mysqli->close();
        }
    }

    return $res;
}

function db_drop($db_name, $verbose = true) { // called in case of failure to remove the partially created database

      $res = false;
    
      $mysqli = server_connect();
      if($mysqli){

            $sql = "DROP DATABASE ".$db_name;
            if ($mysqli->query($sql)) {
                $res = true;
            }
            $mysqli->close();
      }
    
      if($verbose) {
          if($res){
             echo ("<p>Database cleanup for $db_name, completed<br/></p>");
          }else{
             echo ("<p class='error'>Error ".$mysqli->error.". Unable to cleanup database $db_name<br/></p>");    
          }
      }
    
      return $res;

   /* OLD APPROACH
                $cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
                $output2=exec($cmdline . ' 2>&1', $output, $res2);
                echo "<br>Database cleanup for $newname, completed<br>&nbsp;<br>";
                echo($output2);
                */
} 

function empty_table($mysqli, $name, $remark, $verbose){
   
   if($verbose) echo ("Deleting ".$remark."</br>");
    
    
   if(!$mysqli->query("delete from recThreadedComments")){
       if($verbose) {
            echo ("<br/><p>Warning: Unable to clean ".$remark
            ." - SQL error: ".$mysqli->error."</p>");
       }
       return false;
   }else{
       return true;
   }
}

function db_clean($db_name, $verbose=true){
    
      $res = true;
    
      $mysqli = server_connect();
      if($mysqli){
          
                if(!$mysqli->select_db($db_name)){
                    $res = false;
                       if($verbose) {
                            echo ("<br/><p>Warning: Could not open database ".$db_name);
                       }
                }

                if($res){

                    if(!$mysqli->query("update recThreadedComments set cmt_ParentCmtID = NULL where cmt_ID>0")){
                        $res = false;
                        if($verbose) {
                            echo ("<br/><p>Warning: Unable to set parent IDs to null for Comments".
                                " - SQL error: ".$mysqli->error."</p>");
                        }
                    }

                    if($res){
                        $tables = array(
                            "recThreadedComments" => "Comments",
                            "recForwarding" => "Forwarding",
                            "recRelationshipsCache" => "Relationships Cache",
                            "recSimilarButNotDupes" => "List of Similar Records",
                            "usrRecTagLinks" => "Tag Links",
                            "usrReminders" => "Reminders",
                            "usrRemindersBlockList" => "Reminders Block List",
                            "recDetails" => "Details",
                            "usrBookmarks" => "Bookmarks",
                            "Records" => "Records"
                        );

                        foreach ($tables as $name => $remark) {
                            if(!empty_table($mysqli, $name, $remark, $verbose)){
                                $res = false;
                                break;
                            }
                        }

                        $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                    }
                }

                $mysqli->close();
      }else{
          $res = false;
      }
          
      return $res;
}

?>
