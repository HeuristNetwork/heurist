<?php
/**
* getRectypesAsJSON.php - Loads record type names and groups into a JavaScript object for use in the bookmarklet popup.
*
* @project     Heurist academic knowledge management system
* @package  import\bookmarklet
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';

$system = new hserv\System();
if(!$system->init(@$_REQUEST['db'])){
    return;
}

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", '5');
//ob_start('ob_gzhandler');

$mysqli = $system->getMysqli();

header(CTYPE_JSON);

$lastModified = mysql__select_value($mysqli, "select max(rty_Modified) from defRecTypes");
$lastModified = strtotime($lastModified[0]);

if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit;
}

ob_start();

$eol = ";\n\n";

print "HEURIST_rectypes = {}$eol";

$names = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Name from defRecTypes order by rty_Name');

print "top.HEURIST_rectypes.names = " . json_encode($names) . $eol;

$names = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Plural from defRecTypes');

print "top.HEURIST_rectypes.pluralNames = " . json_encode($names) . $eol;

print "top.HEURIST_rectypes.groups = " . json_encode(dbs_GetRectypeGroups($mysqli, true)) . $eol;

print "if (window.HEURIST_rectypesOnload) HEURIST_rectypesOnload()$eol";

ob_end_flush();
?>
