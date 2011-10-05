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

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once('findReplacedRecord.php');

if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
        return;
}

mysql_connection_db_select(DATABASE);

if (@$_REQUEST['bkmk_id']) {
	$bkm_ID = $_REQUEST['bkmk_id'];
	$res = mysql_query('select * from usrBookmarks where bkm_ID = ' . $bkm_ID);
	$bkmk = mysql_fetch_assoc($res);
	$rec_id = $bkmk['bkm_recID'];
} else {
	$rec_id = @$_REQUEST['recID'];

	// check if this bib has been replaced
	$replacement = get_replacement_bib_id($rec_id);
	if ($replacement) $rec_id = $replacement;
	/*
	if (! $rec_id) {
		print '<html><body><p>No such resource</p>';
		print '<p><a href="..">Return to Heurist</a></p></body></html>';
		return;
	}
	*/

	$res = mysql_query('select * from usrBookmarks where bkm_recID = ' . $rec_id . ' and bkm_UGrpID = ' . get_user_id());
	$bkmk = mysql_fetch_assoc($res);
	$bkm_ID = $bkmk['bkm_ID'];
}

require_once(dirname(__FILE__).'/testPermissions.php');
if (! canViewRecord($rec_id)) {
	header('Location: ' . BASE_PATH . 'common/html/denied.html?'.$rec_id);
	return;
}


$noclutter = array_key_exists('noclutter', $_REQUEST)? '&amp;noclutter' : '';

$res = mysql_query('select rec_Title from Records where rec_ID = ' . $rec_id);
$row = mysql_fetch_assoc($res);
$rec_title = $row['rec_Title'];

?>

<html>

<head>
	<title>HEURIST - View record</title>
	<link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
	<link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">

	<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
</head>

<body <?php if (@$_REQUEST['popup']) { ?>style="width: 480px; height: 600px; background-color: transparent;" <?php } ?> class="popup">
	<div><h2>Record details</h2></div>
	<div>
	<h3><?= htmlspecialchars($rec_title) ?></h3>
	<iframe name="viewer" frameborder="0" style="width: 100%;height: 100%;" src="<?=HEURIST_SITE_PATH?>records/view/renderRecordData.php?<?= ($bkm_ID ? ('bkmk_id='.$bkm_ID) : ('recID='.$rec_id)) ?><?= $noclutter ?>"></iframe>
	</div>
</body>
</html>

