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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (!is_logged_in()) {
	header('Location: '.HEURIST_URL_BASE.'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

mysql_connection_db_overwrite(DATABASE);
$template = file_get_contents('viewUserDetails.html');
$template = str_replace('[logged-in-user-id]', intval(get_user_id()), $template);

$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->global_vars['sort'] = ($_REQUEST['sort'] == 'alpha' ? 'alpha' : 'freq');

$name = mysql__select_array(USERS_DATABASE.'.'.USERS_TABLE, "concat(".USERS_FIRSTNAME_FIELD.",' ',".USERS_LASTNAME_FIELD.")", USERS_ID_FIELD.'='.$_REQUEST['Id']);
$name = $name[0];

$body->global_vars['tags'] = '';
$body->global_vars['dbname'] = HEURIST_DBNAME;

$res = mysql_query('select tag_Text,count(rtl_ID) as bkmks
                      from usrRecTagLinks
                 left join usrTags on rtl_TagID=tag_ID
                     where tag_UGrpID='.$_REQUEST['Id'].'
                  group by tag_Text
                  order by '. ($_REQUEST['sort'] == 'alpha' ? 'tag_Text, bkmks desc' : 'bkmks desc, tag_Text'));

$body->global_vars['tags'] .= '<span id="top10">'."\n";
$i = 0;
while ($row = mysql_fetch_assoc($res)) {
	if ($i == 10)
		$body->global_vars['tags'] .= "</span>\n".'<span id="top20" style="display: none;">'."\n";
	if ($i == 20)
		$body->global_vars['tags'] .= "</span>\n".'<span id="top50" style="display: none;">'."\n";
	if ($i == 50)
		$body->global_vars['tags'] .= "</span>\n".'<span id="top100" style="display: none;">'."\n";
	$i++;
	$body->global_vars['tags'] .= '<a target="_top" href="'.HEURIST_URL_BASE.'search/search.html?w=all&q=tag:%22'.urlencode($row['tag_Text']).'%22+user:'.$_REQUEST['Id'].'" title="Search for '.$name.'\'s references with the tag \''.$row['tag_Text'].'\'"><nobr>'.$row['tag_Text'].' ('.$row['bkmks'].")</nobr></a>&nbsp&nbsp\n";
}
$body->global_vars['tags'] .= "</span>\n";


$body->verify();
$body->render();

?>
