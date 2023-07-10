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
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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
    
    if (@$ARGV['-purge']) $arg_need_action = true;   
    if (@$ARGV['-report']) $arg_need_report = true;

    $is_command_line = true;
    
}else{
    //from browser
    define('ADMIN_REQUIRED',1);
    require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;text-align:left">';
    $tabs = "</div>".$tabs0;
    //exit('This function must be run from the shell');
    
    $arg_need_report = true;
    $arg_need_action = (@$_REQUEST['purge']=='1');
}


require_once(dirname(__FILE__).'/../../configIni.php'); // read in the configuration file
require_once(dirname(__FILE__).'/../../hsapi/consts.php');
require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/dbUtils.php');

//retrieve list of databases
$system = new System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

if(!defined('HEURIST_MAIL_DOMAIN')) define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) define('HEURIST_SERVER_NAME', $serverName);//'heurist.huma-num.fr'
if(!defined('HEURIST_SERVER_NAME')) define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');

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

set_time_limit(0); //no limit
ini_set('memory_limit','1024M');

$datetime1 = date_create('now');
$cnt_archived = 0;
$email_list = array();
$email_list_deleted = array();
$tot_size = 0;

//$databases = array('falk_playspace');

foreach ($databases as $idx=>$db_name){

    $dir_root = HEURIST_FILESTORE_ROOT.$db_name.'/';

    if(file_exists($dir_root)){

        $dir_backup = $dir_root.'backup/';
        $dir_scratch = $dir_root.'scratch/';
        $dir_docs = $dir_root.'documentation_and_templates/';
        
        $report = '';
        $db_size = 0;
        
        //only list with size summary
        $res = listFolderContent($dir_root);
        $db_size = $db_size + $res[0];
        $report .= $tabs0.'..  '.$res[0].$eol;

        $sz = folderSize2($dir_backup);
        $report .= $tabs0.substr($dir_backup, strrpos($dir_backup, '/',-2)+1, -1).'  '.$sz.$eol;
        $db_size = $db_size + $sz;

        $sz = folderSize2($dir_scratch);
        $report .= $tabs0.substr($dir_scratch, strrpos($dir_scratch, '/',-2)+1, -1).'  '.$sz.$eol;
        $db_size = $db_size + $sz;

        if(file_exists($dir_docs)){
            $sz = folderSize2($dir_docs);
            $report .= $tabs0.substr($dir_docs, strrpos($dir_docs, '/',-2)+1, -1).'  '.$sz.$eol;
            $db_size = $db_size + $sz;
        }
            
        if($arg_need_action){
            
            //1 root 
            $content = folderContent($dir_root);
            foreach ($content['records'] as $object) {
                if ($object[1] != '.' && $object[1] != '..' && strpos($object[1],'ulf_')===false) {
                    unlink($object[2].'/'.$object[1]);
                }
            }
            folderAddIndexHTML($dir_root);
            
            //2 backup   
            folderDelete($dir_backup, false);
            folderAddIndexHTML($dir_backup);

            //3 scratch   
            folderDelete($dir_scratch, false);
            folderAddIndexHTML($dir_scratch);
            
            //documents
            if(file_exists($dir_docs))
                folderDelete($dir_docs, true);
        
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

    //echo "   ".$db_name." OK \n"; //.'  in '.$folder


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

echo ($tabs0.'finished'.$eol);

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
            $list = $list.'<tr><td>'.$object[1].'</td><td align="right">'.$object[4].'</td></tr>'; //(intdiv($object[4], 1024))
            $size += $object[4]; 
        }
    }
    
    $list = $list.'<tr><td align="left">total</td><td align="right">'.($size).'</td></tr></table>';
    return array($size, $list);
}
?>