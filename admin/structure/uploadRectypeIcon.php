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
* uploadRectypeIcon.php
* uploads icons and thumbnails for record types
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

	define('SAVE_URI', 'disabled');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/imageLibrary.php');

	if (!is_admin()) return;//TOD change this for just admin and return msg. Is probably only called where user is admin

	$rt_id = intval($_REQUEST['rty_ID']);
	$mode = intval($_REQUEST['mode']);  //0 - icon, 1 - thumbnail

	if (!$rt_id) { // no ID set, hopefully this should not occur
		error_log("uploadRectypeIcon.php called without a record type ID set");
		return;
	}

	$dim = ($mode==0)?16:75; // appropriate sizes for icons and thumbnails

	$image_dir = HEURIST_ICON_DIR.(($mode==0)?'':'thumb/th_');
	$image_url = (($mode==0)?getRectypeIconURL($rt_id):getRectypeThumbURL($rt_id));

/*****DEBUG****///error_log("image directory / image url: ".$image_dir."  /  ".$image_url);


	/* ???????
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	mysql_connection_select(DATABASE);
	$res = mysql_query('select * from defRecTypes where rty_ID = ' . $rt_id);
	$rt = mysql_fetch_assoc($res);
	*/

	list($success_msg, $failure_msg) = upload_file($rt_id, $dim);
?>
<html>
	<head>

		<title>Upload reference type icon</title>
		<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
		<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/edit.css">
		<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/admin.css">

		<style type="text/css">
			.success { font-weight: bold; color: green; margin-left: 3px; }
			.failure { font-weight: bold; color: red; margin-left: 3px; }
			.input-row div.input-header-cell {width:90px; vertical-align:baseline; min-width:90px;}
		</style>
	</head>

	<body class="popup">
		<div class="input-row">
			<div class="input-header-cell">Current <?=(($mode==0)?'icon':'thumbnail')?>:</div>
			<div class="input-cell"><img src="<?=$image_url?>?<?= time() ?>" style="vertical-align: middle; height:<?=$dim?>px;"></div>
		</div>

		<?php	if ($success_msg) { ?>
			<div class="success"><?= $success_msg ?></div>
			<?php	} else if ($failure_msg) { ?>
			<div class="failure"><?= $failure_msg ?></div>
			<?php	} else { ?>
			<div style="padding:10px 0">Uploaded image will be scaled to <?=$dim?>x<?=$dim?></div>
			<?php	} ?>

		<form action="uploadRectypeIcon.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" border="0">
			<input type="hidden" name="rty_ID" value="<?= $rt_id ?>">
			<input type="hidden" name="mode" value="<?= $mode ?>">
			<input type="hidden" name="uploading" value="1">


			<div class="input-row">
				<div class="input-header-cell">Select new image</div>
				<div class="input-cell"><input type="file" name="new_icon" style="display:inline-block;"></div>
			</div>
			<div class="actionButtons" style="position:absolute; right:10px; bottom:10px">
				<input type="button" onClick="window.document.forms[0].submit();" value="Upload" style="margin-right:10px">
				<input type="button" value="Close window" onClick="window.close(null);"></div>
			</div>
		</form>
	</body>
</html>
<?php

	/***** END OF OUTPUT *****/

	function upload_file($rt_id, $dim) {

		global $image_dir, $mode;

		if (! @$_REQUEST['uploading']) return;
		if (! $_FILES['new_icon']['size']) return array('', 'Error occurred during upload - file had zero size');
		/*****DEBUG****///error_log(" file info ".print_r($_FILES,true));
		$mimeExt = $_FILES['new_icon']['type'];
		$filename = $_FILES['new_icon']['tmp_name'];
		$origfilename = $_FILES['new_icon']['name'];
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

		// check that the image is not mroe than trwice desired size to avoid scaling issues
		//if (imagesx($img) > ($dim*2)  ||  imagesy($img) > ($dim*2)) return array('','Uploaded file must be no larger than twice '.$dim.' pixels in any direction');

		$newfilename = $image_dir . $rt_id . '.png'; // tempnam('/tmp', 'resized');

		$error = convert_to_png($img, $dim, $filename);

		if($error){
			return array('', $error);
		}else if (move_uploaded_file($filename, $newfilename)) { // actually save the file
			return array('File has been uploaded successfully', '');
		}
		/* something messed up ... make a note of it and move on */
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . $newfilename . ">");
		return array('', "upload file: $name couldn't be saved to upload path definied for db = ". HEURIST_DBNAME);
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
