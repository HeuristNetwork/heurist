<?php
    /*
    * Copyright (C) 2005-2020 University of Sydney
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
    * Find missed system folders for all databases
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

ini_set('max_execution_time', '0');
 
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

/*
if( $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <form action="verifyConceptCodes4.php" method="POST">
        <div style="padding:20px 0px">
            Only an administrator (server manager) can carry out this action.<br />
            This action requires a special system administrator password (not a normal login password)
        </div>
    
        <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
        <input type="password" name="pwd" autocomplete="off" />

        <input type="submit" value="OK" />
    </form>

    <?php
    exit();
}
*/
if(!@$_REQUEST['mail']){
?>            
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">

        <script>window.history.pushState({}, '', '<?php echo $_SERVER['PHP_SELF']; ?>')</script>  
        <div style="font-family:Arial,Helvetica;font-size:12px">
                <h2>List of databases with missing or non-writeable folders</h2>
<?php            
}

    $mysqli = $system->get_mysqli();
    
    //1. find all database
    $databases = mysql__getdatabases4($mysqli, false);   

/*    
    $query = 'show databases';
    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while (($row = $res->fetch_row())) {
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }
*/

    $root = $system->getFileStoreRootFolder();
    
    $check_subfolders = true;
    
    $folders = $system->getArrayOfSystemFolders();
      
    $not_exists = array();
    $not_writeable = array();
    $not_exists2 = array();
    $not_writeable2 = array();
    
    foreach ($databases as $idx=>$db_name){

        //list($database_name_full, $db_name) = mysql__get_names($db_name);
            
        $dir = $root.$db_name.'/';
        
        $check = folderExists($dir, true);
        if($check==-1){
            $not_exists[] = $db_name;
        }else if($check<0){    
            $not_writeable[] = $db_name;
        }else if($check_subfolders){
             //check subfolders
             foreach ($folders as $folder_name=>$folder){
                 
                 if($folder[0]=='' || $folder[0]==null || $folder_name=='uploaded_tilestacks') continue;
                 
                 $subdir = $dir.$folder_name.'/';
                 $check = folderExists($subdir, true);
                 if($check==-1){
                      $not_exists2[] = $subdir;
                 }else if($check<0){    
                      $not_writeable2[] = $subdir;
                 }
             }
             
        }
            
    }//while  databases
    
    $rep = '';

    if(count($not_exists)>0){
        $rep.='<h3>MISSED HEURIST_FILESTORE_DIR for databases:</h3>';
        
        foreach ($not_exists as $db_name){
                $rep.=$db_name.'<br>';
        }
        $rep.='<hr>';
    }
    if(count($not_writeable)>0){
        $rep.='<h3>NOT WRITEABLE HEURIST_FILESTORE_DIR for databases:</h3>';
        
        foreach ($not_writeable as $db_name){
                $rep.=$db_name.'<br>';
        }
        $rep.='<hr>';
    }

    if(count($not_exists2)>0){
        $rep.='<h3>MISSED subfolders</h3>';
        
        foreach ($not_exists2 as $dir){
            if(strpos($dir,'file_uploads')>0){
                $rep.='<b>'.$dir.'</b><br>';
            }else{
                $rep.=$dir.'<br>';
            }
        }
        $rep.='<hr>';
    }
    if(count($not_writeable2)>0){
        $rep.='<h3>NOT WRITEABLE subfolders</h3>';
        
        foreach ($not_writeable2 as $dir){
            if(strpos($dir,'file_uploads')>0){
                $rep.='<b>'.$dir.'</b><br>';
            }else{
                $rep.=$dir.'<br>';
            }
        }
        $rep.='<hr>';
    }

    
    $rep.='[end report]</div>';
    
    if(@$_REQUEST['mail']){
        
        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'List of databases with missing/inaccessible folders', 
                                            $rep, true);
        exit();
    }else{
        
        print $rep;
        print '</body></html>';

    }
    
   
?>
