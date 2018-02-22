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
* emailRecordDetailsphp, generic function to email a record from a Heurist database (generally to another), AO 2011/06/07
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export
*/


define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');

require_once(dirname(__FILE__).'/../../external/php/geekMail-1.0.php');

if(defined('HEURIST_MAIL_TO_INFO')){
    $toEmailAddress = HEURIST_MAIL_TO_INFO;
}
if ( !(isset($toEmailAddress) && $toEmailAddress) && defined('HEURIST_MAIL_TO_ADMIN')){
    $toEmailAddress = HEURIST_MAIL_TO_ADMIN;
}
if (($_POST["rectype"] == RT_BUG_REPORT) && defined('HEURIST_MAIL_TO_BUG')){
    $toEmailAddress = HEURIST_MAIL_TO_BUG;
}

if(!(isset($toEmailAddress) && $toEmailAddress)){
    print '({"error":"The owner of this instance of Heurist has not defined either the info nor system emails"})';
    exit();
}

//send an email
$geekMail = new geekMail();
$geekMail->setMailType('html');
$geekMail->to($toEmailAddress);

// This is set up to send email to a Heurist instance via an email account (from which the records are harvested)
// TODO: replace hard-cioded email address with address passed to request
// TODO: for bug reporting, change back to generic gmail account for Heurist once a workflow
// TODO: has been establsihed for automatically harvesting and notifying emails
// $geekMail->to('prime.heurist@gmail.com');
// but during development it's best just to send it to the team so we actually SEE them ...



$ids = "";

if($_POST["rectype"] == RT_BUG_REPORT){

    if(defined('HEURIST_MAIL_TO_BUG')){
        $toEmailAddress = HEURIST_MAIL_TO_BUG;
    }

    $bug_title = $_POST["type:".DT_BUG_REPORT_NAME];
    
    $geekMail->from("bugs@HeuristNetwork.org", "Bug reporter"); //'noreply@HeuristNetwork.org', 'Bug Report');
    $geekMail->subject('Bug report or feature request: '.$bug_title[0]);

    //keep new line
    $bug_descr = $_POST["type:".DT_BUG_REPORT_DESCRIPTION];
    if($bug_descr){
        $bug_descr = str_replace("\n","&#13;",$bug_descr);
        $bug_descr = str_replace("\"","\\\"",$bug_descr);
        $_POST["type:".DT_BUG_REPORT_DESCRIPTION] = $bug_descr;
    }


    $key_steps = "type:".DT_BUG_REPORT_STEPS;
    $repro_steps = $_POST[$key_steps];
    if(!is_array($repro_steps)) {
        $repro_steps = array();
        if($_POST[$key_steps]!="" && $_POST[$key_steps]!=null){
            array_push($repro_steps, $_POST[$key_steps]);
        }
    }else if (count($repro_steps) ===1){
        $repro_steps = explode("\n",$repro_steps[0]);  //split on line break
    }
    $_POST[$key_steps] = $repro_steps;
    //add current system information into message
    $key_extra = "type:".DT_BUG_REPORT_EXTRA_INFO;
    $ext_info = array();
    array_push($ext_info, "    Browser information: ".$_SERVER['HTTP_USER_AGENT']);
    //add current heurist information into message
    //add current heurist information into message
    array_push($ext_info, "   Heurist codebase: ".HEURIST_BASE_URL);
    array_push($ext_info, "   Heurist version: ".HEURIST_VERSION);
    array_push($ext_info, "   Heurist dbversion: ".HEURIST_DBVERSION);
    array_push($ext_info, "   Heurist database: ". DATABASE);
    array_push($ext_info, "   Heurist user: ".get_user_name());
    $_POST[$key_extra] = $ext_info;
}else{
    $geekMail->from('bugs@HeuristNetwork.org', "Record sender"); //'noreply@HeuristNetwork.org', 'Bug Report');
    $geekMail->subject('Record from '.DATABASE);
}


// ATTACHMENTS - find file fieldtype in POST
$key_file = "type:".DT_BUG_REPORT_FILE;
foreach ($_POST as $key => $value)
{


    if (is_array($value) && $key == $key_file ) {
        foreach ($value as $subvalue) {
            if($subvalue){
                if($ids==""){
                    $ids = "(";
                }else{
                    $ids = $ids.",";
                }
                $ids = $ids.$subvalue;
            }
        }
    }
}

if($ids!=""){
    mysql_connection_overwrite(DATABASE);

    $query = "select ulf_ID, ulf_OrigFileName, ulf_Added, ulf_MimeExt, ulf_FileSizeKB, ulf_FilePath, ulf_FileName from recUploadedFiles where ulf_ID in ".$ids.")";

    $files_arr = array();

    $res = mysql_query($query);
    while ($row = mysql_fetch_row($res)) { //mysql_fetch_assoc

        if ($row[6]) {
            // post 18/11/11 proper file path and name
            if($row[5]){
                $filename = $row[5].$row[6];
                
                $filename = resolveFilePath($filename);
            }else{
                $filename = HEURIST_FILESTORE_DIR.$row[6];
            }

        } else {
            $filename = HEURIST_FILESTORE_DIR.$row[0]; // pre 18/11/11 - bare numbers as names, just use file ID
        }

        $geekMail->attach($filename);
        array_push($files_arr, array($row[1],$row[3])); //name, ext   $row);
    }

    $_POST[$key_file] = $files_arr;

    //@todo delete from database and remove files (after send an email)

}

//files already on server side in database - we don't need to analyse
/*
if ($_FILES) {
foreach ($_FILES as $eltName => $upload) {
// check that $elt_name is a sane element name
if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

if (! $upload["size"]) continue;
foreach ($upload["size"] as $eltID => $size) {
if ($size <= 0) continue;

$geekMail->attach($tmp_name);

if (!$_POST[$eltName]) $_POST[$eltName] = $upload;
}
}
}
*/

/*
$message = '';
foreach ($_POST as $key => $value) {
if (is_array($value)) {
$message .= "$key:\n";
foreach ($value as $subkey => $subvalue)      {
$message .= "\t$subkey: $subvalue\n";
}
} else {
$message .= "$key: $value\n";
}
}
*/

// Converts all record and type codes into Concept
$arr = array();
if($_POST["rectype"] == RT_BUG_REPORT){
    //bug reporting already codes in global
    $arr = $_POST;
}else{

    foreach ($_POST as $key => $value)
    {
        $pos = strpos($key, "type:");

        if (is_numeric($pos) && $pos == 0)
        {
            //@todo we have to convert the content of fields as well -
            // since it may contain terms and references to other rectypes !!!1
            $typeid = substr($key, 5); //, $top-5);
            $newkey = getDetailTypeConceptID($typeid);
            if($newkey){
                $arr["type:".$newkey] = $value;
            }else{
                print '({"error":"Can\'t find the global concept ID for field type #"'.$typeid.'"})';
                exit();
            }
        }else{
            $arr[$key] = $value;
        }
    }//for

    $newrectype = getRecTypeConceptID($_POST["rectype"]);
    if($newrectype){
        $arr["rectype"] = $newrectype;
    }else{
        print '({"error":"Can\'t find the global concept ID for record type #"'.$_POST["rectype"].'"})';
        exit();
    }

}

// converts _POST array into string
//$message = json_format($_POST);

$message =  json_encode($arr);

$geekMail->message($message);

if (!$geekMail->send())
{
    //$errors = $geekMail->getDebugger();
    //print_r($errors);
    
    print '({"error":"Cannot send email. Please ask system administrator to verify that mailing is enabled on your server"})';
}else{
    print '({"result":"ok"})';
}

//print '({"result":"ok"})';
?>
