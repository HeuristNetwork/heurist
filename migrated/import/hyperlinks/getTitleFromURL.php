<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


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
<meta http-equiv="content-type" content="text/html; charset=utf-8">
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
