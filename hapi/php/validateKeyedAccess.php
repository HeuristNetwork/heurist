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

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

function get_location($key) {
	mysql_connection_select("hapi");
	return mysql_fetch_assoc(mysql_query("select * from hapi_locations where hl_key='".addslashes($key)."'"));
}

?>
