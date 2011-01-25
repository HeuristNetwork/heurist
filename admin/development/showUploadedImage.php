<?php
header('Content-type: image/png');

if (! @$_REQUEST['w']  &&  ! @$_REQUEST['h']  &&  ! @$_REQUEST['maxw']  &&  ! @$_REQUEST['maxh']) {
	$noResize = true;
} else {
	$x = @$_REQUEST['w'] ? intval($_REQUEST['w']) : 0;
	$y = @$_REQUEST['h'] ? intval($_REQUEST['h']) : 0;
	$x = @$_REQUEST['maxw'] ? intval($_REQUEST['maxw']) : $x;
	$y = @$_REQUEST['maxh'] ? intval($_REQUEST['maxh']) : $y;
	$noResize = false;
}

$img = null;


$filename = $_REQUEST['filename'];

$mimeExt = $_REQUEST['ext'];

error_log("filename = $filename and mime = $mimeExt");

switch($mimeExt) {
case 'image/jpeg':
case 'jpeg':
case 'jpg':
	$img = imagecreatefromjpeg($filename);
	break;
case 'image/gif':
case 'gif':
	$img = imagecreatefromgif($filename);
	break;
case 'image/png':
case 'png':
	$img = imagecreatefrompng($filename);
	break;
default:
	$desc = '***' . strtoupper($filename) . ' file';
	$img = make_file_image($desc);
	break;
}

if (!$img) {
	header('Location: ../../common/images/100x100-check.gif');
	return;
}
// calculate image size
// note - we never change the aspect ratio of the image!
$resized_file = tempnam('/tmp', 'resized');
if (!$noResize){
	$orig_x = imagesx($img);
	$orig_y = imagesy($img);
	$rx = $x / $orig_x;
	$ry = $y / $orig_y;

	$scale = $rx ? $ry ? min($rx, $ry) : $rx : $ry;

	if ($scale > 1) {
		$scale = 1;
	}

	$new_x = ceil($orig_x * $scale);
	$new_y = ceil($orig_y * $scale);

	$img_resized = imagecreatetruecolor($new_x, $new_y)  or die;
	imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;
	imagepng($img_resized, $resized_file);
	imagedestroy($img_resized);
	imagedestroy($img);
}else{
	imagepng($img, $resized_file);
	imagedestroy($img);
}
$resized = file_get_contents($resized_file);
unlink($resized_file);
echo $resized;

//imagepng($img);


function make_file_image($desc) {
	$desc = preg_replace('/\\s+/', ' ', $desc);

	$font = 3; $fw = imagefontwidth($font); $fh = imagefontheight($font);
	$desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", false));
	$longlines = false;
	if (count($desc_lines) > intval(100/$fh)) {
		$longlines = true;
	} else {
		foreach ($desc_lines as $line) {
			if (strlen($line) >= intval(100/$fw)) {
				$longlines = true;
				break;
			}
		}
	}
	if ($longlines) {
		$font = 1; $fw = imagefontwidth($font); $fh = imagefontheight($font);
		$desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", true));
	}


	$im = imagecreate(100, 100);
	$white = imagecolorallocate($im, 255, 255, 255);
	$grey = imagecolorallocate($im, 160, 160, 160);
	$black = imagecolorallocate($im, 0, 0, 0);
	imagefilledrectangle($im, 0, 0, 100, 100, $white);

	//imageline($im, 35, 25, 65, 75, $grey);
	imageline($im, 33, 25, 33, 71, $grey);
	imageline($im, 33, 25, 62, 25, $grey);
	imageline($im, 62, 25, 67, 30, $grey);
	imageline($im, 67, 30, 62, 30, $grey);
	imageline($im, 62, 30, 62, 25, $grey);
	imageline($im, 67, 30, 67, 71, $grey);
	imageline($im, 67, 71, 33, 71, $grey);

	$y = intval((100 - count($desc_lines)*$fh) / 2);
	foreach ($desc_lines as $line) {
		$x = intval((100 - strlen($line)*$fw) / 2);
		imagestring($im, $font, $x, $y, $line, $black);
		$y += $fh;
	}

	return $im;
}

?>
