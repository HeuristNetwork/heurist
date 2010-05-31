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

if (! @$_REQUEST["key"]) {
	print 'alert("There is no Heurist API key specified for this web site");';
	return;
}

if (! @$_REQUEST["v"]) {
	$version = STABLE_VERSION;
}
else if (preg_match('/^(d|devel)$/i', $_REQUEST["v"])) {
	$version = DEVEL_VERSION;
}
else {
	$version = $_REQUEST["v"];
}


$scripts = array(
	HAPI_HOME . "php/common-data.php?key=" . addslashes($_REQUEST["key"]),
	HAPI_HOME . "php/user-data.php?key=" . addslashes($_REQUEST["key"]),
	HAPI_HOME . "js/hapi.js"
);

?>
/* The key will be set once the javascript files are loaded */
var HeuristApiKey = "<?= addslashes(@$_REQUEST["key"]) ?>";
var HeuristInstance = "<?= addslashes(@$_REQUEST["instance"]) ?>";
var HeuristBaseURL = "<?= addslashes(HEURIST_URL_BASE) ?>";

/* Load the necessary JS files from <?= HAPI_HOME ?> */
(function() {
<?php
	foreach ($scripts as $src) {
		echo "\tdocument.write('<' + 'script src=\"" . $src . "\" type=\"text/javascript\"><' + '/script>');\n";
	}
?>
})();
