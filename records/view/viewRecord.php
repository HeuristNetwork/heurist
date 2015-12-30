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
* UI for record view
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/View
*/


if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/findReplacedRecord.php');

mysql_connection_select(DATABASE);

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

//    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.'&last_uri='.urlencode(HEURIST_CURRENT_URL));
//    return;

	header('Location: ' . HEURIST_BASE_URL . 'common/html/msgAccessDenied.html?'.$rec_id);
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
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
	<link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">

	<link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
</head>

<body <?php if (@$_REQUEST['popup']) { ?>style="width: 480px; height: 600px; background-color: transparent;" <?php } ?> class="popup">
	<div><h2>Record details</h2></div>
	<div>
	<h3><?= htmlspecialchars($rec_title) ?></h3>
	<iframe name="viewer" frameborder="0" style="width: 100%;height: 100%;" src="<?=HEURIST_BASE_URL?>records/view/renderRecordData.php?<?= ($bkm_ID ? ('bkmk_id='.$bkm_ID) : ('recID='.$rec_id)) ?><?= $noclutter ?>"></iframe>
	</div>
</body>
</html>

