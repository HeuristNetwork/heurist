<?php
    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * Creates central index database, fill it and update triggers in all databases
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
    print 'disabled in code';
    exit();
    

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../../hserver/dbaccess/utils_db_load_script.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (!is_admin()) {
        print "Sorry, you need to be a database owner to be able to modify the database structure";
        return;
    }
    
    //1. create and fill central index
    $dir = HEURIST_DIR.'admin/setup/dbupgrade/';
    $filename1 = $dir."DB_index.sql";
    $filename2 = $dir."DB_index_triggers.sql";
    
    
    
    if( file_exists($filename1) && file_exists($filename2) ){
    
        if(true){
            print 'Create index database and fill it<br>';
            if(!executeScript(DATABASE, $filename1)){
                exit;
            }
        }
    }else{
        print 'sql script not found '.$filename1.'  '.$filename2;
        exit;
    }
    
    mysql_connection_insert(DATABASE);
    //2. update triggers 
    $query = 'show databases';

    $res = mysql_query($query);
    if (!$res) {  print $query.'  '.mysql_error();  return; }
    $databases = array();
    while (($row = mysql_fetch_row($res))) {
        //print $row[0].',  ';
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0] == 'hdb_osmak_1')
                $databases[] = $row[0];
        }
    }

    print 'Update '.count($databases).' databases<br>';
    
    //fill database
    foreach ($databases as $idx=>$db_name){

        //if($idx<304) continue;
        
        /* verification that all sysIdentification are valid
        $query = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '$db_name' AND table_name = 'sysIdentification'";
        $res = mysql_query($query);
        $row = mysql_fetch_row($res);
        if($row[0]!=32){
            print $idx.'  '.$db_name.'<br>';     
        }
        */
        
       print $idx.'  '.$db_name.'<br>';     
       
       $res = mysql_query("delete from `Heurist_DBs_index`.`sysIdentifications` where `sys_Database`='$db_name'");
       if(!$res) {print mysql_error(); break;}
       $res = mysql_query("insert into `Heurist_DBs_index`.`sysIdentifications` select '$db_name' as dbName, s.* from `$db_name`.`sysIdentification` as s");
       if(!$res) {print mysql_error(); break;}

       $res = mysql_query("delete from `Heurist_DBs_index`.`sysUsers` where `sus_Database`='$db_name' AND sus_ID>0");
       if(!$res) {print mysql_error(); break;}
       $res = mysql_query("insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) "
       ."select ugr_Email, '$db_name' as dbaname, IF(ugr_ID=2,'owner',COALESCE(ugl_Role,'member')) as role from `$db_name`.sysUGrps "
       ."LEFT JOIN `$db_name`.sysUsrGrpLinks on ugr_ID=ugl_UserID and ugl_GroupID=1 and ugl_Role='admin' where ugr_Type='user'");
       if(!$res) {print mysql_error(); break;}
    }  
    
    print 'recreate triggers '.count($databases).' databases<br>';
    foreach ($databases as $idx=>$db_name){
       print $idx.'  '.$db_name.'<br>';     
       //run sql script to update triggers    
       if(!executeScript($db_name, $filename2)){
              break;
       }
    }//while  databases
    
    //
    //
    //
    function executeScript($db_name, $filename){

                    if(db_script($db_name, $filename)){
                       return true;
                    }else{
                        echo ("<p class='error'>Error: Unable to execute $filename for database ".$db_name."<br>");
                        echo ("Please check whether this file is valid; consult Heurist helpdesk if needed <br />&nbsp;<br></p>");
                        return false;
                    }
    }
    
?>
