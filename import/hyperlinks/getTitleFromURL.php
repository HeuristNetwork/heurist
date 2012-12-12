<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

$error = null;
$title = '';
$url = @$_REQUEST['url'];

if (! is_logged_in()) {
	$error = "You must be logged in";
}else if ( !$url  ||  (!intval($_REQUEST['num'])  &&  $_REQUEST['num'] != 'popup')) {
	$error = "URL is not defined";
}else{

	$url = str_replace(' ', '+', $url);

	$data = loadRemoteURLContentWithRange($url, "0-10000");

	if ($data){

		preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
		if ($matches) $title = preg_replace('/\s+/', ' ', $matches[1]);

		if (! $title) {
			//type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			//if (preg_match('!^image/!i', $type)) {
			//	preg_match('!.*/(.*)!', $_REQUEST['url'], $matches);
			//	$title = 'Image - ' . $matches[1];
			//}
		}


	}else{
		$error = 'URL could not be retrieved';
	}
}
?>
<html>
<head>

<script language="JavaScript">
<!--

function setTitle() {
	var num = '<?= addslashes($_REQUEST['num']) ?>';
	var title = '<?= addslashes($title) ?>';

	var lockedTitleElt, lockedLookupElt, titlegrabberLock;
	if (num != 'popup') {
		lockedTitleElt = parent.document.forms['mainform'].elements['title['+num+']'];
		lockedLookupElt = parent.document.forms['mainform'].elements['lookup['+num+']'];
	} else {
		lockedTitleElt = parent.document.getElementById('popupTitle');
		lockedLookupElt = parent.document.getElementById('popupLookup');
	}
	titlegrabberLock = parent.document.forms['mainform'].elements['titlegrabber_lock'];
	if (! lockedTitleElt  ||  ! lockedLookupElt  ||  ! titlegrabberLock) return;

	if (title) lockedTitleElt.value = title;

	if (! '<?= addslashes($error) ?>'){
		lockedLookupElt.value = 'Revert';
		lockedLookupElt.title = "Revert title";
	}else{
		lockedLookupElt.value = 'URL error';
		lockedLookupElt.title = "";
	}

	lockedLookupElt.disabled = false;
	lockedTitleElt.disabled = false;

	if (titlegrabberLock.value == num) titlegrabberLock.value = 0;
}
//-->
</script>
</head>

<body style="border: 0px; margin: 0px; padding: 0px;"
      <?php if ($error) { ?> onLoad='alert("<?= htmlspecialchars($error) ?>");'
      <?php } else if ($title) { ?> onLoad="setTitle();" <?php } ?>>
</body>

</html>
