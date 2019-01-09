<?php

/**
* getMagicNumbers: set values for special use concept codes
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once(dirname(__FILE__)."/../config/initialise.php");

header("Content-type: text/javascript");


print "if (!top.HEURIST) top.HEURIST = {};\n";
print "if (!top.HEURIST.magicNumbers) { \n top.HEURIST.magicNumbers = {";

$rtDefines = getRTDefineKeys();
$const = get_defined_constants(true);
$userDefines = $const['user'];

foreach ($rtDefines as $magicRTName) {
	print " '$magicRTName' : ".(array_key_exists($magicRTName,$userDefines)?$userDefines[$magicRTName]:"''").",\n";
}

$dtDefines = getDTDefineKeys();

foreach ($dtDefines as $magicDTName) {
	print " '$magicDTName' : ".(array_key_exists($magicDTName,$userDefines)?$userDefines[$magicDTName]:"''").",\n";
}

print "'DEFAULT':0};};\n";
print "if (top != window && !window.HEURIST) window.HEURIST = {};\n";
print "if (!window.HEURIST.magicNumbers) { \n window.HEURIST.magicNumbers = top.HEURIST.magicNumbers;\n};";

?>