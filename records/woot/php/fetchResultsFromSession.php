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

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$token = $_REQUEST["token"];
/*****DEBUG****///error_log("[$token]");
if (! preg_match('/^data[a-f0-9]{16}$/', $token)) { return; }

if (@$_SESSION[$token]) {
	print $_SESSION[$token];
	unset($_SESSION[$token]);
}

?>
