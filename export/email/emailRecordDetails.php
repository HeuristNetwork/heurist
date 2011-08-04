<?php

/**
 * emailRecordDetailsphp
 *
 * Accept POST from
 *
 * 2011/06/07
 * @author: Artem Osmakov
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 * @todo - concer any field type "file" as attachment - currently only #221
 * @todo - remove files from database and folder after sending
 **/

define('SAVE_URI', 'disabled');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

require 'geekMail-1.0.php';

//send an email
$geekMail = new geekMail();
$geekMail->setMailType('html');
$geekMail->from("bugs@acl.arts.usyd.edu.au", "Bug reporter"); //'noreply@heuristscholar.org', 'Bug Report');
$geekMail->to('prime.heurist@gmail.com');
//$geekMail->cc('osmakov@gmail.com');
//$geekMail->bcc('willem@geekology.co.za');

$geekMail->subject('Bug Report');

  $ids = "";

  if($_POST["rectype"] == "253"){ //MAGIC BUG REPORTER

  	//category for documentation
	//$_POST["type:559"] = array("631");

	$ext_desc = $_POST["type:191"];
	if(!is_array($ext_desc)) {
		$ext_desc = array();
		if($_POST["type:191"]!="" && $_POST["type:191"]!=null){
			array_push($ext_desc, $_POST["type:191"]);
		}
	}
  	//add current system information into message
	array_push($ext_desc, "Browser information: ".$_SERVER['HTTP_USER_AGENT']);
  	//add current heurist information into message
	array_push($ext_desc, "Heurist information. codebase: ".HEURIST_BASE_URL." version: ".HEURIST_DBVERSION."  database: ". DATABASE."  user: ".get_user_name());

	$_POST["type:191"] = $ext_desc;

  }


  // ATTACHMENTS - find file fieldtype in POST
  foreach ($_POST as $key => $value)
  {
    if (is_array($value) && $key == "type:221" ) {
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
  	  	mysql_connection_db_overwrite(DATABASE);

		$query = "select ulf_ID, ulf_OrigFileName, ulf_Added, ulf_MimeExt, ulf_FileSizeKB from recUploadedFiles where ulf_ID in ".$ids.")";

//DEBUG error_log(">>>> ".$query);

		$files_arr = array();

		$res = mysql_query($query);
  		while ($row = mysql_fetch_row($res)) {
//DEBUG error_log(">>>> ".HEURIST_UPLOAD_PATH.$row[0]);
			$geekMail->attach(HEURIST_UPLOAD_PATH.$row[0]);
			array_push($files_arr, $row);
		}

		$_POST["type:221"] = $files_arr;

		//@todo delete from database and remove files (after send an email)

  }

	//files already on server side in database - we don't need to analize
	/*if ($_FILES) {
	foreach ($_FILES as $eltName => $upload) {
		// check that $elt_name is a sane element name
		if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

		if (! $upload["size"]) continue;
		foreach ($upload["size"] as $eltID => $size) {
			if ($size <= 0) continue;

error_log(">>>>>>".$tmp_name);

			$geekMail->attach($tmp_name);

			if (!$_POST[$eltName]) $_POST[$eltName] = $upload;
		}
	}
	}*/

  /*
  $message = '';
  foreach ($_POST as $key => $value)
  {
    if (is_array($value)) {
      $message .= "$key:\n";
      foreach ($value as $subkey => $subvalue)      {
     	$message .= "\t$subkey: $subvalue\n";
      }
    }
    else
    {
      $message .= "$key: $value\n";
    }
  }
  */

  // converts _POST array into string
  //$message = json_format($_POST);
  $message =  json_encode($_POST);

//DEBUG error_log(">>>> ".$message);
$geekMail->message($message);

/**/
if (!$geekMail->send())
{
	$errors = $geekMail->getDebugger();
  	print_r($errors);
}else{
	print '({"result":"ok"})';
}

?>
