<?php

/**
* cleanupFoldersDBs.php - cleanup temporary and logs from database folder 
* 
* Remove contents of scratch
* Remove content of backup
* Remove documentation and templates
* Remove all files from root (except index.html)
* 
* Runs from shell and from Server Manager menu
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Default values for arguments
$arg_need_report = false;  
$arg_need_action = false;  
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';
$is_command_line = false;
$is_shell = true;

if (@$argv) {
    
// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/cleanupFoldersDBs.php -- -purge
//  sudo php -f cleanupFoldersDBs.php -- -purge  -  action,  -report - report only

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {                    
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                if(strpos($argv[$i],'-purge')===0){
                    $ARGV['-purge'] = true;
                }else if(strpos($argv[$i],'-report')===0){
                    $ARGV['-report'] = true;
                }else{
                    $ARGV[$argv[$i]] = true;    
                }


            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }
    
    if (@$ARGV['-purge']) {$arg_need_action = true;}   
    if (@$ARGV['-report']) {$arg_need_report = true;}

    $is_command_line = true;
    
}else{
    $is_shell = false;
    //from browser
    define('OWNER_REQUIRED',1);
    require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;text-align:left">';
    $tabs = "</div>".$tabs0;
    //exit('This function must be run from the shell');
    
    $arg_need_report = true;
    $arg_need_action = (@$_REQUEST['purge']=='1');
}


require_once dirname(__FILE__).'/../../configIni.php';// read in the configuration file
require_once dirname(__FILE__).'/../../hserv/consts.php';
require_once dirname(__FILE__).'/../../hserv/System.php';
require_once dirname(__FILE__).'/../../hserv/utilities/dbUtils.php';

$sysadmin_pwd = System::getAdminPwd();

//retrieve list of databases
$system = new System();

if(!$is_shell && $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions) ){
        include_once ERROR_REDIR;
        exit;
}

if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

if(!defined('HEURIST_MAIL_DOMAIN')) {define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');}
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) {define('HEURIST_SERVER_NAME', $serverName);}//'heurist.huma-num.fr'
if(!defined('HEURIST_SERVER_NAME')) {define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');}

//print 'Mail: '.HEURIST_MAIL_DOMAIN.'   Domain: '.HEURIST_SERVER_NAME."\n";

$mysqli = $system->get_mysqli();
$databases = mysql__getdatabases4($mysqli, false);

$upload_root = $system->getFileStoreRootFolder();

define('HEURIST_FILESTORE_ROOT', $upload_root );

$exclusion_list = array();
//$exclusion_list = exclusion_list();

if(!$arg_no_action){

    $action = 'cleanupFoldersDBs';
    if(false && !isActionInProgress($action, 1)){
        exit("It appears that cleanup operation has been started already. Please try this function later\n");
    }
}

/*TMP
//Arche_RECAP
//AmateurS1
//$databases = array('ARNMP_COMET','ArScAn_Material','arthur_base','arvin_stamps');
$databases = array('AmateurS1');
//$databases = array('ARNMP_COMET');
*/

//userInteraction.log

set_time_limit(0);//no limit
ini_set('memory_limit','1024M');

$today = strtotime('now');
$cnt_archived = 0;
$email_list = array();
$email_list_deleted = array();
$tot_size = 0;

//$databases = array('falk_playspace');

foreach ($databases as $idx=>$db_name){

    $dir_root = HEURIST_FILESTORE_ROOT.$db_name.'/';

    $db_name = htmlspecialchars($db_name);
    
    if(file_exists($dir_root)){

        $dir_backup = $dir_root.'backup/';
        $dir_scratch = $dir_root.'scratch/';
        $dir_docs = $dir_root.'documentation_and_templates/';
        
        $report = '';
        $db_size = 0;
        
        //only list with size summary
        $res = listFolderContent($dir_root);
        $root_line = $tabs0.'..  '.intval($res[0]).$eol;
        $db_size = $db_size + intval($res[0]);

        $sz = folderSize2($dir_backup);
        $backup_line = $tabs0.substr($dir_backup, strrpos($dir_backup, '/',-2)+1, -1).'  '.$sz.$eol;
        $db_size = $db_size + $sz;

        $sz = folderSize2($dir_scratch);
        $scratch_line = $tabs0.substr($dir_scratch, strrpos($dir_scratch, '/',-2)+1, -1).'  '.$sz.$eol;
        $db_size = $db_size + $sz;

        if(file_exists($dir_docs)){
            $sz = folderSize2($dir_docs);
            $doc_line = $tabs0.substr($dir_docs, strrpos($dir_docs, '/',-2)+1, -1).'  '.$sz.$eol;
            $db_size = $db_size + $sz;
        }

        if($arg_need_action){
            
            //1 root 
            $content = folderContent($dir_root);
            $added_root_line = false;
            foreach ($content['records'] as $object) {

                if(strpos($object[1], 'userInteraction') === 0){ // remove interactions older than a year

                    $log_fd = fopen($object[2].'/'.$object[1], 'r');

                    $log_file = $object[2].'/'.$object[1];
                    $log_tmp = $object[2].'/log.tmp';

                    if(filesize($log_file) == 0){
                        continue;
                    }

                    // Setup file objects
                    $org_log = null;
                    $new_log = null;
                    try{
                        $org_log = new SplFileObject($log_file);
                        if(!$org_log->isReadable()){ // check file is readable
                            throw new Exception("Log file is not readable");
                        }
                    }catch(RuntimeException $e){
                        $report .= "{$tabs}Unable to open log file{$eol}";
                        continue;
                    }catch(Exception $e){
                        $err = $e->getMessage();
                        $report .= "{$tabs}{$err}{$eol}";

                        continue;
                    }
                    try{
                        $new_log = new SplFileObject($log_tmp, 'w');// to replace log, if lines removed
                        if(!$new_log->isWritable()){ // check if file is writable
                            throw new Exception("Temporary log file is not writable");
                        }
                    }catch(RuntimeException $e){
                        $report .= "{$tabs}Failed to create temporary log file{$eol}";
                        continue;
                    }catch(Exception $e){
                        $err = $e->getMessage();
                        $report .= "{$tabs}{$err}{$eol}";

                        continue;
                    }

                    $remove_lines = 0;
                    $skip_overwrite = false;
                    
                    // Check each line's date
                    while($org_log!=null && !$org_log->eof()){

                        $line = $org_log->fgets();// get line

                        $chunks = explode(',', $line);
                        if(count($chunks) < 3){ // invalid line, skip it
                            continue;
                        }

                        $date = strtotime('+1 week', strtotime($chunks[2]));// ge expiry date

                        if($date < $today){ // expired action
                            $remove_lines ++;
                            continue;
                        }

                        if($remove_lines == 0){ // nothing has changed, escape
                            break;
                        }

                        $res_write = $new_log->fwrite($line);// write to temp file
                        if(!$res_write){ // unable to write to temp file
                            $report .= "{$tabs}Failed to write to temporary log file{$eol}";
                            $remove_lines = 0;
                            break;
                        }
                    }

                    // Destroy file objects
                    unset($org_log);
                    unset($new_log);

                    if($remove_lines > 0 && filesize($log_tmp) > 0){ // replace existing file with temp
                        fileCopy($log_tmp, $log_file);
                        $report .= "{$tabs}Removed {$remove_lines} interactions from the log file{$eol}";
                    }

                    fileDelete($log_tmp);// delete temp file

                }else if ($object[1] != '.' && $object[1] != '..' && 
                    strpos($object[1],'ulf_')===false && strpos($object[1],'userNotifications')===false) {
                        
                    if(strpos($object[1], 'index.html') === false){

                        if(!$added_root_line){
                            $report .= $root_line;
                            $added_root_line = true;
                        }

                        $f_size = filesize($object[2].'/'.$object[1]);
                        $report .= "{$tabs}Deleted file {$object[2]}/{$object[1]}, size: {$f_size}{$eol}";
                    }

                    unlink($object[2].'/'.$object[1]);
                }
            }
            folderAddIndexHTML($dir_root);
            
            //2 backup   
            $delete_log = folderDelete($dir_backup, false, true);
            if(count($delete_log) > 1){ // check that more than index.html has been deleted
                $report .= $backup_line;
                foreach($delete_log as $log){
                    if(strpos($log, 'index.html') === false){
                        $report .= $tabs.$log.$eol;
                    }
                }
            }
            folderAddIndexHTML($dir_backup);

            //3 scratch   
            $delete_log = folderDelete($dir_scratch, false, true);
            if(count($delete_log) > 1){ // check that more than index.html has been deleted
                $report .= $scratch_line;
                foreach($delete_log as $log){
                    if(strpos($log, 'index.html') === false){
                        $report .= $tabs.$log.$eol;
                    }
                }
            }
            folderAddIndexHTML($dir_scratch);

            //documents
            if(file_exists($dir_docs)){
                $delete_log = folderDelete($dir_docs, true, true);
                if(count($delete_log) > 1){ // check that more than index.html has been deleted
                    $report .= $doc_line;
                    foreach($delete_log as $log){
                        if(strpos($log, 'index.html') === false){
                            $report .= $tabs.$log.$eol;
                        }
                    }
                }
            }
        
        }

        if($arg_need_report){
            if($report!=''){
                echo $tabs0.'---'.$eol;
                echo $tabs0.$db_name.$eol;
                echo $tabs0.$report.$eol;
            }
        }     
        
        $cnt_archived++;
        $tot_size = $tot_size + $db_size; 
    }else{
        //database folder is missed   
        echo $tabs0.$db_name.' file folder not found'.$eol;
    }

}//databases

    //echo "   ".$db_name." OK \n";//.'  in '.$folder


echo $tabs0.'---'.$eol;
if($arg_need_action){
    echo $tabs0.'Processed '.$cnt_archived.' databases. Total disk volume cleaned: '.round($tot_size/(1024*1024)).'Mb'.$eol;    
    /*
    if(count($email_list_deleted)>0){
        $sTitle = 'Cleanup databases on '.HEURIST_SERVER_NAME;                
        sendEmail(array(HEURIST_MAIL_TO_ADMIN), $sTitle, $sTitle.' <table>'.implode("\n",$email_list_deleted).'</table>',true);
    }
    */
}else{
    echo $tabs0.'Databases: '.$cnt_archived.'. Total size: '.round($tot_size/(1024*1024)).'Mb'.$eol;    
}

echo $tabs0.'finished'.$eol;

if(!$is_command_line) print '</body></html>';

/*
if(is_array($email_list) && count($email_list)>0){
    
sendEmail(HEURIST_MAIL_TO_ADMIN, "List of inactive databases on ".HEURIST_SERVER_NAME,
    "List of inactive databases for more than a year with more than 200 records:\n"
    .implode(",\n", $email_list));
}
*/
function listFolderContent($dir){
    
    $size = 0;
    $list = '<div>'.substr($dir, strrpos($dir, '/',-2)).'</div><table style="min-width:500px;border:1px solid red"><tr><th align="left">file</th><th align="right">size</th></tr>';
    $content = folderContent($dir);
    
    foreach ($content['records'] as $object) {
        if ($object[1] != '.' && $object[1] != '..') {
            $list = $list.'<tr><td>'.$object[1].'</td><td align="right">'.$object[4].'</td></tr>';//(intdiv($object[4], 1024))
            $size += intval($object[4]);
        }
    }
    
    $list = $list.'<tr><td align="left">total</td><td align="right">'.($size).'</td></tr></table>';
    return array($size, $list);
}
?>