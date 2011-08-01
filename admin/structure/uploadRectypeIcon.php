<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
	define('SAVE_URI', 'disabled');

	define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	//require_once(dirname(__FILE__).'/../../common/T1000/.ht_stdefs');
	define('rectype_DIRECTORY', HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'common/images/rectype-icons/');

	if (! (is_logged_in()  &&  is_admin()  &&  HEURIST_SESSION_DB_PREFIX != "")) return;

	$rt_id = intval($_REQUEST['rty_ID']);

	if (! $rt_id) return;

	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	mysql_connection_db_select(DATABASE);

	$res = mysql_query('select * from defRecTypes where rty_ID = ' . $rt_id);
	$rt = mysql_fetch_assoc($res);

	list($success_msg, $failure_msg) = upload_file($rt_id);
?>
<html>
 <head>
  <title>Upload reference type icon</title>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/newshsseri.css">

  <style type="text/css">
.success { font-weight: bold; color: green; margin-left: 3px; }
.failure { font-weight: bold; color: red; margin-left: 3px; }
.rtyLabel {
	display: inline-block;
	width: 120px;
	text-align: right;
	padding-right: 3px;
}
.rtyField {
	padding-top: 10px;
    display: block;
}
  </style>
 </head>

 <body style="background-color: transparent; width: 320px; height: 120px;">
  <!-- div class="headline">Record type: <?= htmlspecialchars($rt['rty_Name']) ?></div -->

   <div class="rtyField">
   <label class="rtyLabel">Current icon:</label><img src="../../common/images/rectype-icons/<?= $rt_id ?>.gif?<?= time() ?>" style="vertical-align: middle; width: 16px; height: 16px;">
  </div>

<?php	if ($success_msg) { ?>
  <div class="success"><?= $success_msg ?></div>
<?php	} else if ($failure_msg) { ?>
  <div class="failure"><?= $failure_msg ?></div>
<?php	} ?>

  <form action="uploadRectypeIcon.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" border="1">
   <input type="hidden" name="rty_ID" value="<?= $rt_id ?>">
   <input type="hidden" name="uploading" value="1">

   <div class="rtyField">
    	<label class="rtyLabel">Select new icon</label><input type="file" name="new_icon" style="display:inline-block;">
   </div>
   <div style="line-height: 40px;text-align:center;width:100%;">
   		<input type="submit" value="Upload" style="font-weight: bold; line-height: 20px;">
   		<input type="button" value="Close window" onclick="window.close(null);">
   </div>
  </form>
 </body>
</html>
<?php

/***** END OF OUTPUT *****/

function upload_file($rt_id) {
	if (! @$_REQUEST['uploading']) return;
	if (! $_FILES['new_icon']['size']) return array('', 'Error occurred during upload - file had zero size');

	$im = @imagecreatefromgif($_FILES['new_icon']['tmp_name']);
	if (! $im) return array('', 'Uploaded file is not a GIF');
	if (imagesx($im) > 16  ||  imagesy($im) > 16) return array('','Uploaded file must be 16x16 pixels');

	$imstring = @file_get_contents($_FILES['new_icon']['tmp_name']);
	if (! test_transparency($imstring))
		$warning = '<div style="font-weight: bold; color: orange;">but the icon has no transparency defined - it may look dodgy</div>';
	else	$warning = '';

//error_log(">>>>>>>>>>>>>>".HEURIST_UPLOAD_PATH);
//error_log(">>>>>>>>>>>>>>".rectype_DIRECTORY . $rt_id . '.gif');
//rectype_DIRECTORY
	if (move_uploaded_file($_FILES['new_icon']['tmp_name'], rectype_DIRECTORY . $rt_id . '.gif'))
		return array('File has been uploaded successfully' . $warning, '');
	else
		return array('', 'An error occurred while uploading the file - check directory permissions');
}

function test_transparency($gifstring) {
	if (substr($gifstring, 0, 6) != 'GIF89a') return false;

	$gct_size = 2 << (ord($gifstring[10]) & 0x07);
	if (substr($gifstring, 13 + $gct_size*3, 2) != "\x21\xf9") return true;	// maybe transparency defined later

	$packed_fields = ord($gifstring[13 + $gct_size*3 + 3]);
	if ($packed_fields & 0x01) return true;
	else return false;
}

?>
