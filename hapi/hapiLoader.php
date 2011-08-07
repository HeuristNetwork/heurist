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

/* Provide a JavaScript file which loads the latest (or a given) version of the Heurist API.
 * The stable version is supplied by default;
 * a specific version may be requested with the v= parameter;
 * the latest development version may be requested with v=d or v=devel.
 */
require_once(dirname(__FILE__).'/../common/config/initialise.php');


define("DEVEL_VERSION", "03");
define("STABLE_VERSION", "03");
define("HAPI_HOME" /* makes ME laugh */, HEURIST_BASE_URL."hapi/");


header("Content-type: text/javascript");
$instance = (@$_REQUEST["db"] ? $_REQUEST["db"]:"");

$scripts = array(
//	HAPI_HOME . "php/loadHapiCommonInfo.php?key=" . addslashes($_REQUEST["key"])."&db=".$instance,
//	HAPI_HOME . "php/loadHapiUserInfo.php?key=" . addslashes($_REQUEST["key"])."&db=".$instance,
	HAPI_HOME . "php/loadHapiCommonInfo.php?db=".$instance,
	HAPI_HOME . "php/loadHapiUserInfo.php?db=".$instance,
	HAPI_HOME . "js/hapi.js"
);

if (@$_REQUEST["inclGeo"]) {
	array_push($scripts,HAPI_HOME."js/geo.js");
}

?>

/* The key will be set once the javascript files are loaded */
//var HeuristApiKey = "<?= addslashes(@$_REQUEST["key"]) ?>";
var HeuristInstance = "<?= addslashes($instance) ?>";
var HeuristBaseURL = "<?= addslashes(HEURIST_BASE_URL) ?>";
var HeuristSitePath = "<?= addslashes(HEURIST_SITE_PATH) ?>";
/* Load the necessary JS files from <?= HAPI_HOME ?> */
(function() {
 <?php
	foreach ($scripts as $src) {
		echo "\tdocument.write('<' + 'script src=\"" . $src . "\" type=\"text/javascript\"><' + '/script>');\n";
	}
?>
})();


