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

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once (dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

    /*if(isForAdminOnly("to upgrade database structure")){
       return;
    }*/
    
    $src_ver = explode(".", HEURIST_DBVERSION);
    $src_maj = intval($src_ver[0]);
    $src_min = intval($src_ver[1]);

    $trg_ver = explode(".", HEURIST_MIN_DBVERSION);
    $trg_maj = intval($trg_ver[0]);
    $trg_min = intval($trg_ver[1]);
    
    if($src_maj==$trg_maj && $src_min == $trg_min){
       header('Location: ' . HEURIST_BASE_URL);  
       exit();    
    }
?>
<html>
<head>
<title>Heurist Reference Database - Need DB upgrade</title>

<link rel=stylesheet type='text/css' href='../../common/css/global.css'>
<link rel=stylesheet type='text/css' href='../../common/css/admin.css'>
<link rel=stylesheet type='text/css' href='../../common/css/login.css'>
</head>
<body>
<div id=page style="padding: 20px;">

<div id="logo" title="Click the logo at top left of any Heurist page to return to your Favourites"></div>

<div>
<h2>Database version upgrade</h2>
</div>
<div id="loginDiv" style="height:auto; margin-top:44px; overflow-y:auto;text-align:left;">

<?php

if(is_admin() && @$_REQUEST['mode']=='action' && $src_maj==$trg_maj){
        
        $upgrade_success = true;
        $keep_minver = $src_min;
        $dir = HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/setup/';
        while ($src_min<$trg_min) {
            $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).".0.sql";
            if( file_exists($dir.$filename) ){
    
                if(executeScript($dir.$filename)){
                    $src_min++;    
                    print "<p>Upgraded to ".$src_maj.".".$src_min.".0</p>";
                }else{
                    $upgrade_success = false;
                }
                
            }else{
                print "<p style='font-weight:bold'>Can not find the upgrade script '".$filename."'. Please contact Heurist support</p>";
                $upgrade_success = false;
                break;
            }
        } 
        
        if($src_min>$keep_minver){ //update database
             mysql_connection_overwrite(DATABASE);
             mysql__update("sysIdentification", "sys_ID=1", array("sys_dbSubVersion"=>$src_min, "sys_dbSubSubVersion"=>"0"));
             print "<br>";             
        }
        
        if($upgrade_success){
            print "<p>Upgrade is successeful.&nbsp;&nbsp;<a href='".HEURIST_BASE_URL."?db=".HEURIST_DBNAME."'>Proceed to main page</a></p>";
        }
    
}else{
?>

<p>Your database <?=HEURIST_DBNAME?> is currently version <?=HEURIST_DBVERSION?></p>

<p>This version of the software (<?=HEURIST_VERSION?>) requires upgrade of your database to version <?=HEURIST_MIN_DBVERSION?></p>

<?php
       
    if(is_admin()){
    
    if($src_maj!=$trg_maj){
        print "<p style='font-weight:bold'>It is possible to upgrade minor versions only. Please contact Heurist support to upgrade major version</p>";
    }else{
        
        $scripts_info = "";
        $is_allfind = true;
        //DBUpgrade_1.1.0_to_1.2.0.sql
        $dir = HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/setup/';
        while ($src_min<$trg_min) {
            $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).".0.sql";
            if( file_exists($dir.$filename) ){
                
                //$content = file_get_contents($dir.$filename);
                $safety = "";
                $description = "";
                 
                $file = fopen($dir.$filename, "r");
                if($file){
                    while(!feof($file)){
                        $line = fgets($file);
                        
                        if(strpos($line,"-- Safety rating")===0){
                            
                            $safety = substr($line,17);
                            
                        }else if(strpos($line,"-- Description")===0){
                            
                            $description = substr($line,15);
                        }
                        
                        if($safety && $description){
                            break;
                        }
                    }
                    fclose($file);
                }else{
                    print "<p style='font-weight:bold'>Can not read the upgrade script '".$filename."'. Please contact Heurist support</p>";
                    $is_allfind = false;
                    break;
                }
                 
                 $scripts_info  = $scripts_info."<tr><td width='100'>".$src_maj.".".$src_min.".0 to ".$src_maj.".".($src_min+1).".0</td><td>".$safety." ".$description."</td></tr>";
                
                 $src_min++;
            }else{
                print "<p style='font-weight:bold'>Can not find the upgrade script '".$filename."'. Please contact Heurist support</p>";
                $is_allfind = false;
                break;
            }
        }
/*
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      if(!is_dir("$dir/$file") && strpos($file, "DBUpgrade_")===0){
          
      }
    }
*/
        if($is_allfind)    {
?>

<div>Upgrades marked SAFE are unlikely to harm your database but will make it incompatible with older versions of the code. Upgrades marked DANGEROUS involve major changes to your database structure and could cause data loss.</div>
<br>
<div>If you need a partial upgrade eg. to an intermediate version, you will need to run an older version of the software - please consult Heurist support.</div>
<br>
<div>Please determine whether you need backward compatibility and/or clone your database before proceeding. Specific changes in each version upgrade are listed below:</div>
<br>
<table>
<?=$scripts_info?>
</table>
<p>
<form action="upgradeDatabase.php">
    <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
    <input type="hidden" name="mode" value="action">
    <input type="submit" value="Upgrade database">
</form>    
</p>
<?php
        }
    }
        
    }else{ //not admin
?>
    <p style='font-weight:bold'>You must be logged in as database owner to upgrade database structure</p>
    <p>
        <a href="<?=HEURIST_BASE_URL?>common/connect/login.php?logout=1&amp;db=<?=HEURIST_DBNAME?>">Log in as database owner</a>
    </p>
<?php
    }
}
?>
    <a href="<?=HEURIST_BASE_URL?>common/connect/selectDatabase.php?$msg=Select database">Select different database</a>
</div>
</div>
</body>
</html>
<?php
function executeScript($filename){
    
            $cmdline="mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D".DATABASE." < ".$filename;
            $output2 = exec($cmdline . ' 2>&1', $output, $res2);

            if ($res2 != 0 ) {
                echo ("<p class='error'>Error $res2 on MySQL exec: Unable to execute $filename for database ".HEURIST_DBNAME."<br>");
                echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br></p>");
                echo($output2);
                return false;
            }else{
                return true;
            }
            
}
?>