<?php

/**
 * emailProcessor.php
 *
 * main script for scraping and parsing email import
 *
 * 2011/06/07
 * @author: Artem Osmakov, Maxim Nikitin
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 **/

 
// TODO: Needs a progress bar or counter as it takes soem time to complete

// TO DO: Needs to use global concept nubmers in place of local codes for rectype/fields

set_time_limit(0);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

$_POST["save-mode"] = "none";
require_once(dirname(__FILE__).'/../../records/edit/saveRecordDetails.php');

include_once 'classEmailProcessor.php';

mysql_connection_db_overwrite(DATABASE);

/* get mail server options

$query=mysql_query("select `sys_email_imap_server`, `sys_email_imap_port`, `sys_email_imap_protocol`, `sys_email_imap_username`, `sys_email_imap_password` from `system`");
$params=mysql_fetch_assoc($query);
//print_r($params);

// get emails
$query=mysql_query("select `EMail` from `ACLAdmin`.`Users` where `id`='".get_user_id()."'");
$email=mysql_fetch_assoc($query);
$senders=$email['EMail'];
$query=mysql_query("select `email_addresses` from `ACLAdmin`.`Users` where `id`='".get_user_id()."'");
$email=mysql_fetch_assoc($query);
if($email){
    $senders.=','.$email['email_addresses'];
}
//$senders.=',maxim.nikitin.work@gmail.com';
//$senders='';
*/

// TODO: This needs to be configred from data in the database

$params = array();
$params['sys_email_imap_server'] = 'imap.gmail.com';
$params['sys_email_imap_port'] = '993';
$params['sys_email_imap_username'] = 'prime.heurist@gmail.com';
$params['sys_email_imap_password'] = 'sydarb43';
$params['sys_email_imap_protocol'] = '';

$senders='bugs@acl.arts.usyd.edu.au, osmakov@gmail.com, steven.hayes@sydney.edu.au, stephenawhite@hotmail.com';
//ianjohnson@usyd.edu.au, ijohnson222@gmail.com
$use_ssl=true;
// delete processed mails from mail server
$delete_processed=false;

// path to attachments directory
//$attachments_save_path='/home/maxim/mail_attachments/';

// maximum size of attachment, in bytes
$attachment_size_max=8*1024*1024;

$server   = $params['sys_email_imap_server'];
$port     = $params['sys_email_imap_port'];
$username = $params['sys_email_imap_username'];
$password = $params['sys_email_imap_password'];
if($params['sys_email_imap_protocol']!='noprotocol'){
    $use_ssl=true;
}else{
    $use_ssl=false;
}

// count of emails processed
$emails_processed=0;

function add_email_as_record($email){

    global $emails_processed, $attachment_size_max;
    $emails_processed++;

    $description=$email->getBody();

    /* use UNIX-style lines
	$description=str_replace("\r\n", "\n", $description);
	$description=str_replace("\r", "\n", $description);
	*/

//error_log(">>>desc:".$description);

	$description=str_replace("\r\n", "", $description);
	$description=str_replace("\r", "", $description);

    $arr = json_decode($description, true);

//error_log(">>>>".is_array($arr)."  ".count($arr)); //print_r($_POST, true));

	if(is_array($arr) && count($arr)>2){
		//this is from export record from another heurist instance

		//assosiated files
		$files_arr = $arr['type:221'];

//error_log(">>>>files_arr=".print_r($files_arr, true));
		if($files_arr){
			$arr['type:221'] = saveAttachments($files_arr, $email);
			if($arr['type:221']==0) return false;
		}

		$_POST = $arr;

	}else{
		// this is from usual email - we will add email rectype

    	// liposuction away those unsightly double, triple and quadruple spaces
		$description=preg_replace('/ +/', ' ', $description);

    	// trim() each line
		$description=preg_replace('/^[ \t\v\f]+|[ \t\v\f]+$/m', '', $description);
		$description=preg_replace('/^\s+|\s+$/s', '', $description);

    	// reduce anything more than two newlines in a row
		$description=preg_replace("/\n\n\n+/s", "\n\n", $description);

		//consider this message as usual email and
		$_POST["save-mode"]="new";
		$_POST["notes"]="";
		$_POST["bib_url"]="";
		$_POST["type:160"] = array($email->getSubject());
		$_POST["type:166"] = array(date('Y-m-d H:i:s')); //date sent
		$_POST["type:521"] = array($email->getFrom()); //email owner
		$_POST["type:650"] = array($email->getFrom()); //email sender
		$_POST["type:651"] = array("prime.heurist@gmail.com"); //recipients
		//$_POST["type:221"] = array(); //attachments
		$_POST["type:560"] = array($description);

		$_POST["rectype"]="183";
		$_POST["check-similar"]="1";

		$arr = saveAttachments(null, $email);
		if($arr==0){
			return false;
		}else if(count($arr)>0){
			$_POST['type:221'] = $arr;
			//array_push($_POST['type:221'], $arr);
//error_log(">>>>>>>>>ARRAY>>".print_r($arr, true));
		}

//error_log(">>>>>>>>>HERE>>>>>>".print_r($_POST['type:221'],true));
	}

    $updated = insertRecord();
    $rec_id = null;

    if ($updated) {
    		$rec_id = $_REQUEST["rec_ID"];
    		$email->setRecId($rec_id);
	}


    printEmail($email);

    if($rec_id){ //($rec_id){
        return true;
    }else{
        return false;
    }
}

//
// save attachements
// returns array of id for uploaded files in DB
//
function saveAttachments($files_arr, $email){

	    global $attachment_size_max;

		$arr_res = array();

//error_log("WE ARE IN SAVEATTACH");

        $attachments=$email->getAttachments();
        foreach($attachments as $attachment){
            $body=$attachment->getBody();


			$file_size = strlen($body);

//error_log("SIZE ".$file_size.">>>>>".$attachment_size_max);

            if($file_size>$attachment_size_max){
                continue;
            }

            $filename=$attachment->getFilename();
            if(!$filename){
                $filename=$attachment->getName();
            }
            if($filename){

//error_log("AAAA>>>".$filename."     ".$attachment->getName());

            	//find file name and related info if $files_arr
            	if($files_arr){
					$file_arr = null;

			      	foreach ($files_arr as $temp_arr) {
			      		if($temp_arr[0]==$filename){
			      			$file_arr = $temp_arr;
//error_log("BBBB>>>print_r=".print_r($file_arr, true));
			      			break;
						}
			  		}

					if($file_arr){
						$name = $file_arr[1];
						$date_uploaded = date('Y-m-d H:i:s'); //$file_arr[2];
						$mimetypeExt = $file_arr[3];
						//$file_size = $file_arr[4];
					}else{
						//skip this attachment
						continue;
					}

				}else{ //from usual email
					$name = $filename;
					$date_uploaded = date('Y-m-d H:i:s');
					$mimetypeExt = strtolower(substr($filename,-3,3));
					if($mimetypeExt=="peg") $mimetypeExt = "jpg";
//error_log("SAVING>>>>>>>>>>>>>>>>>>>>>".$mimetypeExt."   ".strrpos($filename,".")."    ".substr($fielname,strrpos($filename,".")+1));
				}

            	//save into database
	$res = mysql__insert('recUploadedFiles', array(	'ulf_OrigFileName' => $name,
													'ulf_UploaderUGrpID' => get_user_id(),
													'ulf_Added' => date('Y-m-d H:i:s'),
													'ulf_MimeExt ' => $mimetypeExt,
													'ulf_FileSizeKB' => $file_size,
													'ulf_Description' => NULL ));
	if (! $res) { error_log("error inserting file upload info: " . mysql_error()); return 0; }
	$file_id = mysql_insert_id();
	mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);

            	$full_name = HEURIST_UPLOAD_PATH.$file_id;

//error_log("CCCCC>>>".$full_name);

                /*if(!is_file($full_name))
                {
                    $filenameparts=split("[/\\.]", $filename);

                    $index=2;
                    do{
                        $filenameparts=split("[/\\.]", $filename);
                        if(count($filenameparts)>1){
                            $filenameparts[count($filenameparts)-2].='('.$index.')';
                        }else{
                            $filenameparts[0].='('.$index.')';
                        }
                        $filename_new=join('.', $filenameparts);
                        $index++;
                    }while(is_file($attachments_save_path.$rec_id.'__'.$filename_new));
                }*/
                $attachment->setFilename($full_name);
                $attachment->setName($full_name);

                $file=fopen($full_name, 'wb');
                fwrite($file, $attachment->getBody(), $attachment_size_max);
                fclose($file);

                array_push($arr_res, $file_id);
            }
        }

		return $arr_res;
}


//callback function for processing of email. Takes object of class Email as parameter, returns boolean.
function printEmail($email){

    echo '<tr><td width="30%" valign="top" style="border: 1px solid black;">';
    echo '<b>From:</b> '.htmlentities($email->getFrom()).'<br>';
    echo '<b>Subject:</b> '.htmlentities($email->getSubject()).'<br>';

    if($email->getRecId()){
        echo '<b>Status:</b> inserted as record with id '.htmlentities($email->getRecId()).'<br>';
    }else{
        echo '<b>Status:</b> adding to Heurist failed <br>';
    }

    echo '</td><td style="border: 1px solid black;">';

    $body=ereg_replace('<script.*.</script>', ' ', $email->getBody());
    $body=ereg_replace('<style.*.</style>', ' ', $body);
    $body=strip_tags($body);
    echo htmlentities($body);

    $attachments=$email->getAttachments();
    if(count($attachments)){
        echo '<br><br><b>Attachements:</b><br><ul>';
        foreach($attachments as $attachment){
            $filename=$attachment->getFilename();
            if(!$filename){
                $filename=$attachment->getName();
            }
            if($filename){
                echo '<li>'.htmlentities($filename).'</li>';
            }
        }
        echo '</ul>';
    }

    echo '</td></tr>';
}
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    </head>
    <body>

<?php
try{
    if(!isset($_REQUEST['p'])){
        if($senders){
            echo '<b>Please wait. Processing incoming emails from '.$senders.' ...</b><br><br>';
        }else{
            echo '<b>Please wait. Processing incoming emails ...</b><br><br>';
        }
?>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="process_form">
            <input type="hidden" name="p" value="1">
        </form>

        <script type="text/javascript">
            function processEmails(){

                document.getElementById("process_form").submit();
            }
            window.onload=processEmails;
        </script>
<?php
    }else{
        if($senders){
            echo '<b>Processed incoming emails from '.$senders.':</b><br><br>';
        }else{
            echo '<b>Processed incoming emails:</b><br><br>';
        }
        $mail_processor=new EmailProcessor($server, $port, $username, $password, $use_ssl);
        echo '<table style="border: 1px solid black;">';
        $mail_processor->process('add_email_as_record', $senders, $delete_processed);
        echo '</table><br>';

        echo '<b>Total emails processed: '.$emails_processed.'</b>';
    }
}catch(Exception $e){
    echo '<b>An error occured: '.$e->getMessage().'</b>';
}
?>

    </body>
</html>