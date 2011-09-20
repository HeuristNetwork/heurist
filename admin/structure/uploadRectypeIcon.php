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
	require_once(dirname(__FILE__).'/../../common/php/imageLibrary.php');

	if (! (is_logged_in()  &&  is_admin()  &&  HEURIST_SESSION_DB_PREFIX != "")) return;

	$rt_id = intval($_REQUEST['rty_ID']);
	$mode = intval($_REQUEST['mode']);  //0 - icon, 1 - thumbnail

	$dim = ($mode==0)?16:75;
	$image_dir = HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'common/images/'.HEURIST_DBNAME.'/rectype-icons/'.(($mode==0)?'':'thumb/th_');
	$image_url = (($mode==0)?getRectypeIconURL($rt_id):getRectypeThumbURL($rt_id));

	if (!$rt_id) return;

/* ???????
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	mysql_connection_db_select(DATABASE);
	$res = mysql_query('select * from defRecTypes where rty_ID = ' . $rt_id);
	$rt = mysql_fetch_assoc($res);
*/

	list($success_msg, $failure_msg) = upload_file($rt_id, $dim);
?>
<html>
 <head>
  <title>Upload reference type <?=(($mode==0)?'icon':'thumbnail')?></title>
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

   <div class="rtyField">
   <label class="rtyLabel">Current <?=(($mode==0)?'icon':'thumbnail')?>:</label>
   <img src="<?=$image_url?>?<?= time() ?>" style="vertical-align: middle; width:<?=$dim?>px; height:<?=$dim?>px;">
  </div>

<?php	if ($success_msg) { ?>
  <div class="success"><?= $success_msg ?></div>
<?php	} else if ($failure_msg) { ?>
  <div class="failure"><?= $failure_msg ?></div>
<?php	} ?>

  <form action="uploadRectypeIcon.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" border="1">
   <input type="hidden" name="rty_ID" value="<?= $rt_id ?>">
   <input type="hidden" name="mode" value="<?= $mode ?>">
   <input type="hidden" name="uploading" value="1">

   <div class="rtyField">
    	<label class="rtyLabel">Select new image</label><input type="file" name="new_icon" style="display:inline-block;">
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

function upload_file($rt_id, $dim) {

	global $image_dir;

	if (! @$_REQUEST['uploading']) return;
	if (! $_FILES['new_icon']['size']) return array('', 'Error occurred during upload - file had zero size');

	$mimeExt = $_FILES['new_icon']['type'];
	$filename = $_FILES['new_icon']['tmp_name'];
	$img = null;

	switch($mimeExt) {
	case 'image/jpeg':
	case 'jpeg':
	case 'jpg':
		$img = @imagecreatefromjpeg($filename);
		break;
	case 'image/gif':
	case 'gif':
		$img = @imagecreatefromgif($filename);
		break;
	case 'image/png':
	case 'png':
		$img = @imagecreatefrompng($filename);
		break;
	default:
		break;
	}

	if (! $img) return array('', 'Uploaded file is not supported format');
	//if (imagesx($img) > 16  ||  imagesy($img) > 16) return array('','Uploaded file must be 16x16 pixels');

	$filename = $image_dir . HEURIST_DBID. '-' . $rt_id . '.png'; // tempnam('/tmp', 'resized');

	$error = convert_to_png($img, $dim, $filename);

	if($error){
		return array('', $error);
	}else{
		return array('File has been uploaded successfully', '');
	}

}

// not used
function test_transparency($gifstring) {
	if (substr($gifstring, 0, 6) != 'GIF89a') return false;

	$gct_size = 2 << (ord($gifstring[10]) & 0x07);
	if (substr($gifstring, 13 + $gct_size*3, 2) != "\x21\xf9") return true;	// maybe transparency defined later

	$packed_fields = ord($gifstring[13 + $gct_size*3 + 3]);
	if ($packed_fields & 0x01) return true;
	else return false;
}

?>
