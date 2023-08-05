<?php

/**
* Script is run by daily cronjob
* It performs the following actions
* 
* 1. Send record remainders sepcified in usrReminders
* 2. Updates reports by schedule specified in usrReportSchedule
* 
* Databases in HEURIST/databases_exclude_cronjobs.txt are ignored
* 
* Runs from shell only
* 
* in heuristConfigIni.php define $serverName
* if (!@$serverName && php_sapi_name() == 'cli') $serverName = 'heuristref.net';
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
$arg_no_action = true;  
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

$do_reports = false;
$do_reminders = false;
$do_url_check = false;

$func_return = 1; // for checkRecURL.php

if (@$argv) {
    
// example:
//  sudo php -f /var/www/html/heurist/setup/dboperations/dailyCronJobs.php -- reminder report

    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {  //pair: -param value                  
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[substr($argv[$i],1)] = $argv[$i + 1];
                ++$i;
            }
        } else {
            $ARGV[$argv[$i]] = true;
        }
    }
    
//print print_r($ARGV,true)."\n";
//exit();    

    if(@$ARGV['reminder']){
        $do_reminders = true;
    }
    if(@$ARGV['report']){
        $do_reports = true;        
    }
    if(@$ARGV['url']){
        $do_url_check = true;
    }
    if(!$do_reminders && !$do_reports && !$do_url_check){
        $do_reminders = true;
        $do_reports = true;
        $do_url_check = true;
    }

    
}else{
    exit('This function must be run from the shell');
    /*
        $do_reminders = true;
        $do_reports = false;        
        $eol = "<br>";
    */    
}

require_once(dirname(__FILE__).'/../../../configIni.php'); // read in the configuration file
require_once(dirname(__FILE__).'/../../../hsapi/consts.php');
require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../../hsapi/entity/dbUsrReminders.php');
require_once(dirname(__FILE__).'/../../../admin/verification/checkRecURL.php');

//retrieve list of databases
$system = new System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

require_once(dirname(__FILE__).'/../../../viewers/smarty/updateReportOutput.php');


if(!defined('HEURIST_MAIL_DOMAIN')) define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) define('HEURIST_SERVER_NAME', $serverName);//'heurist.huma-num.fr'
if(!defined('HEURIST_SERVER_NAME')) define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');

print 'Mail: '.HEURIST_MAIL_DOMAIN.'   Domain: '.HEURIST_SERVER_NAME."\n";

$mysqli = $system->get_mysqli();
$databases = mysql__getdatabases4($mysqli, false);  //list of all databases without hdb_ prefix 

$upload_root = $system->getFileStoreRootFolder();

echo "root : ".$upload_root."\n";

//define('HEURIST_FILESTORE_ROOT', $upload_root );

$exclusion_list = exclusion_list(); //read databases_not_to_crons 

set_time_limit(0); //no limit
//ini_set('memory_limit','1024M');

$datetime1 = date_create('now');
$cnt_archived = 0;
$report_list = array();
$email_list = array();
$url_list = array();
$reminders = null;

if($do_reminders){
//echo ">>>>>".spl_object_id($system)."\n";      
    $reminders = new DbUsrReminders($system, $params);
}

print 'HEURIST_SERVER_NAME='.HEURIST_SERVER_NAME."\n";
print 'HEURIST_BASE_URL='.HEURIST_BASE_URL."\n";
print 'HEURIST_MAIL_TO_INFO='.HEURIST_MAIL_TO_INFO."\n";

//print $do_reports.'  '.$do_reminders;
//print print_r($databases,true);
//print print_r($exclusion_list,true);

// HEURIST_SERVER_NAME
// HEURIST_MAIL_TO_INFO

// HEURIST_SMARTY_TEMPLATES_DIR  $system->getSysDir('smarty-templates')
// HEURIST_SCRATCHSPACE_DIR      $system->getSysDir('scratch')
// HEURIST_DBNAME                $system->dbname()   

//$databases = array(0=>'osmak_1');

foreach ($databases as $idx=>$db_name){
    
    if(in_array($db_name,$exclusion_list)){
        continue;
    }

    $report='';
    

    if(!mysql__usedatabase($mysqli, $db_name)){
        echo $system->getError()['message']."\n";
        continue;
    }
    
    $system->set_mysqli($mysqli);
    $system->set_dbname_full($db_name);
//echo spl_object_id($system).'    >>>'.isset($mysqli)."<<<<\n";      
    
    if($do_reports){
        $report = array(0=>0,1=>0,2=>0,3=>0);
        
        //regenerate all reports
        $is_ok = false;
        $res = $mysqli->query('select * from usrReportSchedule');
        if($res){
            
            $smarty = null; //reset
            
            while ($row = $res->fetch_assoc()) {
//echo print_r($row,true);
                if($smarty==null){ //init smarty for new db if there is at least one entry in schedule table
                    initSmarty($system->getSysDir('smarty-templates')); //reinit global smarty object for new database
                    if(!isset($smarty) || $smarty==null){
                        echo 'Cannot init Smarty report engine for database '.$db_name.$eol;
                        break;
                        //continue;
                    }
                }
                $rep = doReport($system, 4, 'html', $row);
                if($rep>0){
                    $report[$rep]++;
                    $is_ok = true;
                }
            }//while
            $res->close();        
        }
        
        if($is_ok){
            $report_list[$db_name] = $report;
            //echo $tabs0.$db_name;
            echo $tabs0.$db_name.$eol;
            echo $tabs.' reports: '.implode(' ',$report).$eol;
        }
            
    }
    
    if($do_reminders){
        $reminders->setmysql($mysqli);
        $report = $reminders->batch_action();
        if(count($report)>0){
            echo $tabs0.$db_name;
            echo $tabs.' reminders: ';
            foreach($report as $freq=>$cnt){
                echo $freq.':'.$cnt.'  ';  
                if(!@$email_list[$freq]) $email_list[$freq] = 0;
                $email_list[$freq] = $email_list[$freq] + $cnt;  
            }
            echo $eol;
        }
    }

    if($do_url_check){

        $perform_url_check = mysql__select_value($mysqli, 'SELECT sys_URLCheckFlag FROM sysIdentification');
        if(!$perform_url_check || $perform_url_check == 0){ // check for flag setting
            continue;
        }

        $url_results = checkURLs($system, true); // [0] => rec_URL, [1] => Freetext/blocktext fields, [2] => Files using external url

        $invalid_rec_urls = $url_results[0];
        $invalid_fb_urls = $url_results[1];
        $invalid_file_urls = $url_results[2];

        /*if(!empty($invalid_rec_urls[0]) || !empty($invalid_fb_urls) || !empty($invalid_file_urls)){
            echo $eol.$tabs0.$db_name;
            echo $eol.$tabs.' url checks: '.$eol;
        }*/

        if(!empty($invalid_rec_urls[0])){
            if(!is_array($invalid_rec_urls[0])){ // error
                echo $invalid_rec_urls[0];
            }else{

                echo 'invalid rec_URL: ' . implode(',', $invalid_rec_urls[0]);
                
                $url_list[$db_name] = array();
                $url_list[$db_name][0] = implode(',', $invalid_rec_urls[0]);
            }
        }

        if(!empty($invalid_fb_urls)){
            if(!is_array($invalid_fb_urls)){ // error
                echo $invalid_fb_urls;
            }else{

                echo 'fields containing invalid urls: ';
                foreach ($invalid_fb_urls as $rec_id => $flds) {
                    echo $eol.$rec_id.': ';
                    foreach($flds as $dty_id => $urls){
                        echo $eol.$tabs.$dty_id.': '.implode(',', $urls);
                    }

                    if(!array_key_exists($db_name, $url_list)){
                        $url_list[$db_name] = array();
                    }
                    if(!array_key_exists(1, $url_list[$db_name])){
                        $url_list[$db_name][1] = array();
                    }
                    $url_list[$db_name][1][] = $rec_id . ' : ' . implode(',', array_keys($flds));
                }
            }
        }else if(!empty($invalid_file_urls)){
            echo 'Record fields contain invalid urls: ';
        }

        if(!empty($invalid_file_urls)){
            if(!is_array($invalid_file_urls)){ // error
                echo $invalid_file_urls;
            }else{
                foreach ($invalid_file_urls as $rec_id => $flds) {
                    echo $eol.$rec_id.': ';
                    foreach($flds as $dty_id => $urls){
                        echo $eol.$tabs.$dty_id.': '.implode(',', $urls);
                    }

                    if(!array_key_exists($db_name, $url_list)){
                        $url_list[$db_name] = array();
                    }
                    if(!array_key_exists(1, $url_list[$db_name])){
                        $url_list[$db_name][1] = array();
                    }
                    $url_list[$db_name][1][] = $rec_id . ' : ' . implode(',', array_keys($flds));
                }
            }
        }
    }
//echo $tabs0.$db_name.' cannot execute query for Records table'.$eol;


    //echo "   ".$db_name." OK \n"; //.'  in '.$folder
}//foreach


echo ($eol.$tabs0.'finished'.$eol);

if(count($email_list)>0 || count($report_list)>0 || count($url_list)>0){

    $errors = 0;
    $created = 0;
    $updated = 0;
    $intacted = 0;
    foreach($report_list as $dbname=>$rep){
        $errors = $errors + $rep[0];
        $created = $created + $rep[1];
        $updated = $updated + $rep[2];    
        $intacted = $intacted + $rep[3];    
    }
    $text = '';
    foreach($email_list as $freq=>$cnt){
          $text = $text. $freq.':'.$cnt.'  ';
    }
    
    $text = "Reminder emails sent, reports updated and URLs verified\n\n"
    ."Number of reminder emails sent:\n"
    .$text
    ."\n\nNumber of reports "
    ."\n created: ".$created
    ."\n updated: ".$updated
    ."\n unchanged: ".$intacted
    ."\n errors: ".$errors."\n";
    
    $text = $text."\n\nInvalid urls: ";
    foreach($url_list as $dbname=>$reps){
        $rec_URL = 'None';
        $fld_URL = 'None';
        if(array_key_exists(0, $reps)){
            $rec_URL = $reps[0];
        }
        if(array_key_exists(1, $reps)){
            $fld_URL = "\n  ".implode("\n  ", $reps[1]);
        }
        $text = $text . "\n" . $dbname . "\n rec_URL => " . $rec_URL . "\n Fields => " . $fld_URL;
    }
    if(count($url_list) == 0){
        $text = $text . "None";
    }
    $text = $text . "\n";

    echo $text;
    
    $rep_count = $created + $updated + $intacted;
    sendEmail(HEURIST_MAIL_TO_ADMIN, HEURIST_SERVER_NAME." Emails sent: ".$cnt." | Reports:".$rep_count." | Errors: ".$errors." | Bad URLs: ".count($url_list),
                $text);
    
}

function exclusion_list(){
    
    $res = array();
    $fname = realpath(dirname(__FILE__)."/../../../../databases_exclude_cronjobs.txt");
    if($fname!==false && file_exists($fname)){
        //ini_set('auto_detect_line_endings', 'true');
        $handle = @fopen($fname, "r");
        while (!feof($handle)) {
            $line = trim(fgets($handle, 100));
            if($line=='' || substr($line,0,1)=='#') continue; //remarked
            $res[] = $line;
        }
        fclose($handle);
    }
    return $res;
}
?>