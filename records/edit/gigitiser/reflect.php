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


/* Reflect the uploaded file(s) back in JSON */

header("Content-type: text/javascript");

$first = true;
print "({\n";
foreach ($_FILES as $uploadName => $file) {
	if (! $first) print ",\n";
	$first = false;

	printAsJSON($uploadName, $file);
}
print "\n})\n";


function printAsJSON($uploadName, $file) {
	$content = file_get_contents($file["tmp_name"]);

	$jsonContent = str_replace('\\', '\\\\', $content);
	$jsonContent = str_replace('"', '\\"', $jsonContent);
	$jsonContent = str_replace("\n", '\\n', $jsonContent);
	$jsonContent = str_replace("\r", '\\r', $jsonContent);
	$jsonContent = str_replace("\0", '\\u0000', $jsonContent);
	$jsonContent = preg_replace("/([\\001-\\037\\177])/e", "sprintf('\\u%04X', ord('\\1'));", $jsonContent);

	print "\"" . addslashes($uploadName) . "\": { fileName: \"" . addslashes($file["name"]) . "\", content: \"" . $jsonContent . "\" }";
}

?>
