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
require_once(dirname(__FILE__).'/../common/config/heurist-ini.php');


define("DEVEL_VERSION", "03");
define("STABLE_VERSION", "03");
define("HAPI_HOME" /* makes ME laugh */, HEURIST_URL_BASE."hapi/");


header("Content-type: text/javascript");
$instance = (@$_REQUEST["instance"] ? $_REQUEST['instance']:"");

$scripts = array(
	HAPI_HOME . "php/common-data.php?key=" . addslashes($_REQUEST["key"])."&instance=".$instance,
	HAPI_HOME . "php/user-data.php?key=" . addslashes($_REQUEST["key"])."&instance=".$instance,
	HAPI_HOME . "js/hapi.js?instance=".$instance
);

if (@$_REQUEST["inclGeo"]) {
	array_push($scripts,HAPI_HOME."js/geo.js?instance=".$instance);
}

?>
/* The key will be set once the javascript files are loaded */
var HeuristApiKey = "<?= addslashes(@$_REQUEST["key"]) ?>";
var HeuristInstance = "<?= addslashes($instance) ?>";
var HeuristBaseURL = "<?= addslashes(HEURIST_URL_BASE) ?>";
var HeuristSitePath = "<?= addslashes(HEURIST_SITE_PATH) ?>";
/* Load the necessary JS files from <?= HAPI_HOME ?> */
(function() {
<?php
	foreach ($scripts as $src) {
		echo "\tdocument.write('<' + 'script src=\"" . $src . "\" type=\"text/javascript\"><' + '/script>');\n";
	}
?>
})();
