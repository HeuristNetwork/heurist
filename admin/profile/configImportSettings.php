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
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

$word_limit = 0;

mysql_connection_db_overwrite(USERS_DATABASE);

if (@$_REQUEST['submitted']) {  //reload with new word limit

	$word_limit = intval(@$_REQUEST['word_limit']);

	mysql_query('update sysUGrps usr set ugr_MinHyperlinkWords = '.$word_limit.' where usr.ugr_ID='.get_user_id());


}else{
	$res = mysql_query('select ugr_MinHyperlinkWords from sysUGrps usr where usr.ugr_ID = '.get_user_id());
	$row = mysql_fetch_row($res);
	$word_limit = $row[0];	// minimum number of spaces that must appear in the link text
}

mysql_connection_db_overwrite(DATABASE);

if (@$_REQUEST['new_hyp_text']) {
		//add new filter text if not found
		$res = mysql_query('select count(*) from usrHyperlinkFilters
		                     where (hyf_UGrpID is null or hyf_UGrpID='.get_user_id().')
		                       and hyf_String="'.addslashes(@$_REQUEST['new_hyp_text']).'"');
		$row = mysql_fetch_array($res);
		if ($row[0] == 0) {
			mysql__insert('usrHyperlinkFilters',
			             array('hyf_String' => @$_REQUEST['new_hyp_text'],
			                   'hyf_UGrpID' => get_user_id()));
		}
}

$alinks = mysql__select_array('usrHyperlinkFilters', 'hyf_String', 'hyf_UGrpID is null or hyf_UGrpID='.get_user_id());
if($alinks==null) $alinks =  array();

$hyperlinks_ignored = '<div>'.join("</div>\n<div>",$alinks).'</div>';

//for SELECT
function _issel($val){
	global $word_limit;
	return ($word_limit==$val)?'selected':'';
}
?>
<html>

<head>
<title>Configuration</title>

	<link rel="stylesheet" type="text/css" href= "../../common/css/global.css">

	<style type="text/css">
		#ignored_hyperlinks {
				overflow: auto; background-color: #AAAAAA;
				padding-left: 5px;
				height: 260px;
		}
	</style>

</head>

<body class="popup" width=600 height=450>

<h2>Bookmark import settings</h2>

<form method="post">
	<input type="hidden" name="submitted" value="1">

	<table border="0" cellspacing="4" width="100%">
	<tr>
	<td width="260px" valign="top">
		<p>
		Heurist can import bookmarks from any html file, notably bookmarks.htm/html
		which is either used by (IE) or can be exported by (Firefox - Bookmarks
		- Organize Bookmarks ... File &gt; Export) most internet browsers. To
		avoid many unnecessary hyperlinks when importing search engine results,
		it can ignore commonly occurring hyperlink text and very short hyperlinks.
		</p>
		<p>While importing bookmarks, <b>show only hyperlinks</b> with
	<select name="word_limit" onChange="form.submit();">
		<option value="0" <?=_issel(0)?>>any number of words</option>
		<option value="1" <?=_issel(1)?>>at least one word</option>
		<option value="2" <?=_issel(2)?>>at least two words</option>
		<option value="3" <?=_issel(3)?>>at least three words</option>
		<option value="4" <?=_issel(4)?>>at least four words</option>
		<option value="5" <?=_issel(5)?>>at least five words</option>
	</select>
	in the text of the link.
		</p>
	</td>
	<td>
			Hyperlink text to ignore:<br />
	 		<input type="text" name="new_hyp_text" value="" size="30">
	 		<input type="submit" value="Add"> <br />

			<b>Ignore the following hyperlink texts when importing bookmarks:</b><br /><br />
			<div id="ignored_hyperlinks">
				<?=$hyperlinks_ignored?>
			</div>

	</td>
	</tr>
	</table>

</form>

	<div style="position:absolute;bottom:10; text-align:right;">
		<input type=button value=Done onclick="window.close();">
	</div>

</body>

</html>