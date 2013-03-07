<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


define('ISSERVICE',1);

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
//	HAPI_HOME . "../common/php/getMagicNumbers.php?db=".$instance,
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
var HeuristIconURL = "<?= addslashes(HEURIST_ICON_SITE_PATH) ?>";
/* Load the necessary JS files from <?= HAPI_HOME ?> */
(function() {
 <?php
	foreach ($scripts as $src) {
		echo "\tdocument.write('<' + 'script src=\"" . $src . "\" type=\"text/javascript\"><' + '/script>');\n";
	}
?>
})();


