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
	//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	$_POST["save-mode"] = "none";
	require_once(dirname(__FILE__).'/../../records/edit/saveRecordDetails.php');

	include_once 'classEmailProcessor.php';


	function returnErrorMsgPage2($msg) {
		$msg2= "<p>&nbsp;<h2>Email configuration error</h2></p><p>".$msg."</p><p><i>Please edit the system configuration (DBAdmin > Database > Advanced Properties) or user configuration ( DBAdmin > Access > Users)</i></p>";
		header("Location: ".HEURIST_BASE_URL."common/html/msgErrorMsg.html?msg=$msg2");
		// echo "location.replace(\"".HEURIST_BASE_URL."common/html/msgErrorMsg.html?msg=$msg2\");";
	} // returnErrorMsgPage2


	mysql_connection_overwrite(DATABASE);

	/*****DEBUG****///error_log(HEURIST_DBNAME."         ".HEURIST_BASE_URL.'            '.DATABASE);

	// get mail server options from database
	$res = mysql_query('select * from sysIdentification');
	if (!$res) returnErrorMsgPage2("Unable to retrieve email configuration from sysIdentification record, MySQL error: ".mysql_error());
	$sysValues = mysql_fetch_assoc($res);
	$server   = $sysValues['sys_eMailImapServer'];
	$port     = $sysValues['sys_eMailImapPort'];
	$username = $sysValues['sys_eMailImapUsername'];
	$password = $sysValues['sys_eMailImapPassword'];
	$protocol = $sysValues['sys_eMailImapProtocol'];

	$senders = null;
	$ownership = 0;
	$sys_sender = null;
	if(is_admin()){
		$sys_sender = $sysValues['sys_IncomingEmailAddresses'];
		if($sys_sender) $sys_sender=split(",", $sys_sender);
		$senders = $sysValues['sys_IncomingEmailAddresses'];
		$ownership = $sysValues['sys_OwnerGroupID'];
	}

	/* hardcoded
	$server   = 'imap.gmail.com';
	$port     = '993';
	$username = 'prime.heurist@gmail.com';
	$password = 'sydarb43';
	$protocol = '';
	*/

	// get list of emails addresses - messages from them will be processed
	$res=mysql_query("select ugr_IncomingEmailAddresses from sysUGrps where ugr_ID=".get_user_id());
	if (!$res) returnErrorMsgPage2("Unable to retrieve incoming email address information from user's sysUGrps record, MySQL error: ".mysql_error());
	$email = mysql_fetch_assoc($res);
	if($email && $email['ugr_IncomingEmailAddresses'] && $email['ugr_IncomingEmailAddresses']!="undefined"){
		if($senders){
			$senders = $senders.',';
		}
		$senders = $senders.$email['ugr_IncomingEmailAddresses'];
	}
	//TODO!!!!  remove this before shipping -- hardcoded $senders='osmakov@gmail.com, steven.hayes@sydney.edu.au, stephenawhite@hotmail.com';

	$use_ssl = ($protocol!='noprotocol');
	// delete processed mails from mail server
	$delete_processed=true;

	// path to attachments directory
	//$attachments_save_path='/home/maxim/mail_attachments/';

	// maximum size of attachment, in bytes
	$attachment_size_max=8388608; //8*1024*1024;


	// count of emails processed
	$emails_processed=0;
	$emails_failed=0;
	$emails_processed_ids="";

	$oldFileArray = false;
	/**
	* call back function of classEmailProcessor
	*
	* @param mixed $email
	* @return true - then classEmailProcessor may remove this email
	*/
	function add_email_as_record($email){

		global $emails_processed, $emails_failed, $attachment_size_max, $sys_sender, $ownership,$oldFileArray;
		$emails_processed++;

		$description=$email->getBody();
		$rec_id = null;

		/* use UNIX-style lines
		$description=str_replace("\r\n", "\n", $description);
		$description=str_replace("\r", "\n", $description);
		*/
		/*****DEBUG****///error_log(">>>desc:".$description);

		$description=str_replace("\r\n", "", $description);
		$description=str_replace("\r", "", $description);

		$arr = json_decode($description, true);


		/*****DEBUG****///error_log("arr=>>>>".is_array($arr)."  ".count($arr)."  ".print_r($arr, true));

		// assume all id (rtID, dtID, trmID and ontID) are all concept ids (dbID - ID)
		// get rectype concept id and  convert to local id or notes
		// for each detail type: convert to local ids
		//if type is file the save Attachment first
		//if type is enum then convert term ID to local
		// for any id that doesn't convert, save info in scratch in record.
		if(is_array($arr) && count($arr)>2){
			//this is from export record from another heurist instance

			$key_file = null; //id of field type for assoc files (attachments)
			$arrnew = array();

			//convert all global id to local id
			foreach ($arr as $key => $value)
			{
				$pos = strpos($key, "type:");
				/*****DEBUG****///error_log(">>>> ".(is_numeric($pos) && $pos == 0)."    ".$pos);

				if (is_numeric($pos) && $pos == 0)
				{


					//@todo we have to convert the content of fields as well -
					// since it may contain terms and references to other rectypes !!!1
					$typeid = substr($key, 5);
					// temporay code to translate existing reports
					switch ($typeid){
						case "2-221":
						case "68-221":
							$typeid = DT_BUG_REPORT_FILE;
							$oldFileArray = true;
							break;
						case "2-179":
						case "68-179":
							$typeid = DT_BUG_REPORT_NAME;
							break;
						case "2-303":
						case "68-303":
							$typeid = DT_BUG_REPORT_DESCRIPTION;
							break;
						case "2-560":
						case "68-560":
							$typeid = DT_BUG_REPORT_STEPS;
							break;
						case "2-725":
						case "68-725":
							$typeid = DT_BUG_REPORT_STATUS;
							break;
					}

					$newkey = getDetailTypeLocalID($typeid);

					/*****DEBUG****///error_log(">>>> ".$newkey."  dettype=".$typeid);

					$value=str_replace("&#13;", "\n", $value);

					if($newkey){

						$arrnew["type:".$newkey] = $value;
						if($newkey == DT_FILE_RESOURCE){
							$key_file = "type:".$newkey;
						}
					}else{
						$email->setErrorMessage("Can't find the local id for fieldtype #".$typeid);
						$key_file = null; //avoid processing attachments
						break;
					}
				}else{
					$arrnew[$key] = $value;
				}
			}//for
			$rectype = $arr["rectype"];
			switch ($rectype){
				case "2-216":
				case "68-216":
					$rectype = RT_BUG_REPORT;
			}
			$newrectype = getRecTypeLocalID($rectype);
			if($newrectype){
				$arrnew["rectype"] = $newrectype;
				if ($rectype == RT_BUG_REPORT){

				}
			}else{
				$email->setErrorMessage("Can't find the local id for rectype #".$_POST["rectype"]);
				$key_file = null; //avoid processing attachments
				exit();
			}


			/*****DEBUG****///error_log("KEY FILE ".$key_file);
			//assosiated files
			if($key_file){
				$files_arr = $arrnew[$key_file];

				if($files_arr && count($files_arr) > 0) //  && preg_match("/\S+/",$files_arr[0]))
				{
					/*****DEBUG****///error_log(">>>>files_arr=".print_r($files_arr[0], true));
					$arrnew[$key_file] = saveAttachments($files_arr, $email);
					if($arrnew[$key_file]==0) return false;
				}
				else
				{
					unset($arrnew[$key_file]);
				}
			}


			/*$bug_descr = $arrnew["type:".DT_BUG_REPORT_DESCRIPTION];
			if($bug_descr){
				$bug_descr = str_replace("<br>","\n",$bug_descr);
				$arrnew["type:".DT_BUG_REPORT_DESCRIPTION] = $bug_descr;
			}*/

			/*****DEBUG****///error_log(">>>>ARRAY=".print_r($arrnew, true));

			$_POST = $arrnew;
			$statusKey = getDetailTypeLocalID(DT_BUG_REPORT_STATUS);
/*****DEBUG****///error_log("status key ".print_r($statusKey,true));
			if ($statusKey){
				$_POST["type:".$statusKey] = array("Received");
			}
		}else if(defined('RT_NOTE')){
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
			$_POST["rec_url"]="";

			/* FOR EMAIL RECORD TYPE  - BUT IAN REQUIRES POST "NOTE" RECORDTYPE
			$_POST["type:160"] = array($email->getSubject());
			$_POST["type:166"] = array(date('Y-m-d H:i:s')); //date sent
			$_POST["type:521"] = array($email->getFrom()); //email owner
			$_POST["type:650"] = array($email->getFrom()); //email sender
			$_POST["type:651"] = array("prime.heurist@gmail.com"); //recipients
			//$_POST["type:221"] = array(); //attachments
			$_POST["type:560"] = array($description);

			$_POST["rectype"]="183";  //EMAIL
			*/

			if(defined('DT_NAME')) $_POST["type:".DT_NAME] = array($email->getSubject()); //title
			//$_POST["type:158"] = array("email harvesting"); //creator (recommended)
			if(defined('DT_DATE')) $_POST["type:".DT_DATE] = array(date('Y-m-d H:i:s')); //specific date
			if(defined('DT_SHORT_SUMMARY')) $_POST["type:".DT_SHORT_SUMMARY] = array($email->getFrom()." ".$description); //notes

			$_POST["rectype"] = RT_NOTE;  //NOTE


			$_POST["check-similar"]="1";

			$arr = saveAttachments(null, $email);
			if($arr==0){
				return false;
			}else if(count($arr)>0 ){
				if (defined('DT_FILE_RESOURCE')) $_POST['type:'.DT_FILE_RESOURCE] = $arr;
				//array_push($_POST['type:221'], $arr);
				/*****DEBUG****///error_log(">>>>>>>>>ARRAY>>".print_r($arr, true));
			}

			/*****DEBUG****///error_log(">>>>>>>>>HERE>>>>>>".print_r($_POST['type:221'],true));
		}

		if($email->getErrorMessage() == null)
		{

			$_POST["owner"] = get_user_id();
			$sss = $email->getFrom();
			if(is_array($sys_sender)){
				foreach($sys_sender as $sys_sender_email){
					if($sys_sender_email==$sss || strpos($sss,"<"+$sys_sender_email+">")>=0){
						$_POST["owner"] = $ownership;
						break;
					}
				}
			}

			$_POST["visibility"] = 'hidden';
			/*****DEBUG****///error_log(">>>>before insert POST=".print_r($_POST, true));

			$updated = insertRecord(); //from saveRecordDetails.php
			if ($updated) {
				$rec_id = $_REQUEST["recID"];
				$email->setRecId($rec_id);
			}

			/*****DEBUG****///error_log("updated = $updated  recID = $rec_id ");
		}

		printEmail($email);

		//$rec_id=null;

		if($rec_id){ //($rec_id){
			return true;
		}else{
			$emails_failed++;
			return false;
		}
	}

	//
	// save attachements
	// returns array of id for uploaded files in DB
	//
	function saveAttachments($files_arr, $email){

		global $attachment_size_max,$oldFileArray;

		$arr_res = array();

		/*****DEBUG****///error_log("WE ARE IN SAVEATTACH");

		$attachments=$email->getAttachments();
		foreach($attachments as $attachment){
			$body=$attachment->getBody();
/*****DEBUG****/ //error_log("attachment >>>> ".print_r($attachment,true));

			$file_size = strlen($body);

			/*****DEBUG****/ //error_log("SIZE ".$file_size.">>>>>".$attachment_size_max);

			if($file_size>$attachment_size_max){
				continue;
			}

			$filename=$attachment->getFilename();
				/*****DEBUG****/ //error_log("AA11>>>".$filename);
			if(!$filename){
				$filename=$attachment->getName();
			}
			if($filename){

				/*****DEBUG****///error_log("AAAA>>>".$filename."     ".$attachment->getName());

/*****DEBUG****/ //error_log("file arr >>>>>>".print_r($files_arr,true));				//find file name and related info if $files_arr
				if($files_arr){
					$file_data = null;

					foreach ($files_arr as $temp_arr) {
						if($oldFileArray){
							$temp_arr = array($temp_arr[1], $temp_arr[3]);
						}
						/*****DEBUG****/ //error_log("test $filename is eq to filearray name ".print_r($temp_arr[0],true));
						if(preg_match("/".$temp_arr[0]."/",$filename)){
							$file_data = $temp_arr;
							/*****DEBUG****/ //error_log("BBBB>>>print_r=".print_r($file_data, true));
							break;
						}
					}

					if($file_data){
						$name = $file_data[0];
						$date_uploaded = date('Y-m-d H:i:s'); //$file_data[2];
						$mimetypeExt = $file_data[1];
						//$file_size = $file_data[4];
					}else{
						//skip this attachment
						continue;
					}

				}else{ //from usual email
					$name = $filename;
					$date_uploaded = date('Y-m-d H:i:s');
					$mimetypeExt = strtolower(substr($filename,-3,3));
					if($mimetypeExt=="peg") $mimetypeExt = "jpg";
					/*****DEBUG****/ //error_log("SAVING>>>>>>>>>>>>>>>>>>>>>".$mimetypeExt."   ".strrpos($filename,".")."    ".substr($filename,strrpos($filename,".")+1));
				}

				//save into database
				$file_id = upload_file($name, $mimetypeExt, null, null, $file_size, null, false);

				if(is_numeric($file_id)){

					$full_name = HEURIST_UPLOAD_DIR."/ulf_".$file_id."_".$name;

					/*****DEBUG****/ //error_log("CCCCC>>>".$full_name);

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

				}else{
					error_log("error inserting file upload info: " . $file_id);
					return 0;
				}

				/*****DEBUG****/ //error_log("DDDDDDDD>> SAVED");

				array_push($arr_res, $file_id);
			}
		}

		return $arr_res;
	}


	//callback function for processing of email. Takes object of class Email as parameter, returns boolean.
	function printEmail($email){

		global $emails_processed_ids;

		echo '<tr><td width="30%" valign="top">';
		echo '<b>From:</b> '.htmlentities($email->getFrom()).'<br>';
		echo '<b>Subject:</b> '.htmlentities($email->getSubject()).'<br>';

		if($email->getRecId()){
			echo '<b>Status:</b> inserted as record with id '.htmlentities($email->getRecId()).'<br>';
			$emails_processed_ids = $emails_processed_ids.($email->getRecId()).",";
		}else{
			echo '<div style="color:#ff0000;"><b>Status:</b> adding to Heurist failed '.$email->getErrorMessage().'</div>';
		}

		echo '</td><td>';

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
		flush();
	}
?>
<html>
	<head>
		<title>Email harvester</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
	</head>
	<body class="popup" style="font-size: 10px;overflow:auto;">

		<?php
			try{
				if(!isset($_REQUEST['p'])){
					if($senders){
						echo '<div><b><br>Processing incoming emails from: '.$senders.' ...</b></div>';
					}else{
						echo '<div><b><br>Processing incoming emails ...</b></div>';
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
						echo '<br><b>Processing incoming emails from: '.$senders.':</b><br><br>';
					}else{
						echo '<br><b>Processing incoming emails:</b><br><br>';
					}
					$mail_processor=new EmailProcessor($server, $port, $username, $password, $use_ssl);
					echo '<table style="border: 1px solid black;">';
					$mail_processor->process('add_email_as_record', $senders, $delete_processed);
					echo '</table><br>';

					echo '<div><b>Total emails processed: '.$emails_processed.'</b></div>';
					echo '<div style="padding-left:20px"><b>Failed: '.$emails_failed.'</b></div>';
					echo '<div style="padding-left:20px"><b>Addedd: '.($emails_processed-$emails_failed).'</b></div>';

					if(($emails_processed-$emails_failed)>0){
						echo '<div>You may look at the added records:
						<a href="../../search/search.html?db='.HEURIST_DBNAME.'&q=ids:'.$emails_processed_ids.'" target="_blank">
						<img src="../../common/images/external_link_16x16.gif"/>HERE</a></div>';
					}

				}
			}catch(Exception $e){
				echo '<b>Processing failed with the following message: '.$e->getMessage().'</b>';
				echo "<p>If the user name and password or IMAP server details are incorrect, you may get 'Login aborted'</p>";
				echo "<p><a href='../../admin/setup/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."' target='_blank'>
				<img src='../../common/images/external_link_16x16.gif'/>Configure connection to IMAP mail server</a> (per-database)</p>";
			}
		?>

	</body>
</html>