<?php

if (! $_REQUEST['url']  ||  (! intval($_REQUEST['num'])  &&  $_REQUEST['num'] != 'popup')) return;
$_REQUEST['url'] = str_replace(' ', '+', $_REQUEST['url']);

$ch = curl_init($_REQUEST['url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
curl_setopt($ch, CURLOPT_RANGE, '0-10000');	// just look at the first 10kb for a title
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$data = curl_exec($ch);
$error = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (intval($code) >= 400) $error = 'URL could not be retrieved';

if (! $error) {
	preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
	if ($matches) $title = preg_replace('/\s+/', ' ', $matches[1]);

	if (! $title) {
		$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		if (preg_match('!^image/!i', $type)) {
			preg_match('!.*/(.*)!', $_REQUEST['url'], $matches);
			$title = 'Image - ' . $matches[1];
		}
	}

} else {
	$title = '';
}

curl_close($ch);

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

	if (! '<?= addslashes($error) ?>')
		lockedLookupElt.value = 'Revert';
	else
		lockedLookupElt.value = 'URL error';

	lockedLookupElt.disabled = false;
	lockedTitleElt.disabled = false;

	if (titlegrabberLock.value == num) titlegrabberLock.value = 0;
}
//-->
</script>
</head>

<body style="border: 0px; margin: 0px; padding: 0px;"
      <?php if ($error) { ?>onLoad="alert('<?= htmlspecialchars($error) ?>'); setTitle();"
      <?php } else if ($title) { ?>onLoad="setTitle();" <?php } ?>>
</body>

</html>
