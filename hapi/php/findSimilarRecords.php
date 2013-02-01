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

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

session_cache_limiter('no-cache');
define('SAVE_URI', 'disabled');
define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/disambig/findFuzzyRecordMatches.php");

$data = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$details = @$data["details"];
$types = @$data["types"];
$id = @$data["id"] ? $data["id"] : null;
$fuzziness = @$data["fuzziness"] ? $data["fuzziness"] : null;

if (! $details  || ! $types) {
	print json_format(array("error" => "invalid arguments"));
	return;
}

mysql_connection_select(DATABASE);

$matches = findFuzzyMatches($details, $types, $id, $fuzziness);

print json_format(array("matches" => $matches));

