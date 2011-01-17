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

require_once(dirname(__FILE__)."/../woot.php");

$data = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

print json_encode(saveWoot($data));

?>
