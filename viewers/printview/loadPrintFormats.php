<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* Load a json structure with xls stylesheet info
*
* @author      Kim Jackson
* @author      Stephen White   
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewer/Transforms
*/


	/* load the read through the xsl directory detecting thoses xsl files that are to written for print formatting. Output an array
	into JS */
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


	define("SAVE_URI", "disabled");


	if (is_dir(HEURIST_FILESTORE_DIR)) {
		if (is_dir(HEURIST_FILESTORE_DIR.'xsl-templates')) {
			define('DIR', HEURIST_FILESTORE_DIR.'xsl-templates');
		} else if (is_dir(HEURIST_FILESTORE_DIR.'xsl')) {
			define('DIR', HEURIST_FILESTORE_DIR.'xsl');
		}
	}else if(is_dir('xsl-templates')) {
		define('DIR', 'xsl-templates');
	}else if(is_dir('xsl')) {
		define('DIR', 'xsl');
    }
	// using ob_gzhandler makes this stuff up on IE6-

    header('Content-type: text/javascript');

    if(!defined('DIR')){
            print "styles = {}";
            exit();
    }

	$arr_styles = load_output_styles();

	print "styles = {\n";
	$first = true;
	foreach ($arr_styles as $key => $value) { //load xsl-stylesheet based styles
		list($text,$path) = $value;
		if (! $first) print ",";  print "\n"; $first = false;
		print "\t \"".$key."\": [\"".$text."\", \"".$path."\" ]";
	}
	print "    };\n";
/**
		* This function loads stylesheet names into the dropdown list for heurist publishing wizard
		* @return  array  - array of stylesheet [stylesheetname]=>Name of Style
		*/

	function load_output_styles(){

		$arr_files = array();
		$arr_outputs = array();

		//open directory and read in file names
		// Ian changed 'DIR' below to HEURIST_FILESTORE_DIR.xsl-templates 25/1/12
		// TODO: check that the xsl template directory is hardcoded as subdirectory of the
		// databases's upload directory - I think it is
		if (is_dir(DIR)) {
			if ($dh = opendir(DIR)) {
				while (($file = readdir($dh)) !== false) {
					$arr_files[] = $file;
				}
				closedir($dh);
			}
		}

		foreach($arr_files as $filename){
			//if file is a stylesheet file
			$filePath = DIR."/".$filename;
			if (eregi ('.xsl', $filename)){
				//read the required contents of the file.
				$handle = fopen(DIR."/".$filename, "rb");
				$contents = fread($handle, filesize($filePath));
				fclose($handle);

				if (eregi('<xsl:comment>', $contents)){

					$out1 = explode('[output]', $contents);
					$out = explode ('[/output]', $out1[1]);

					$name1 = explode('[name]', $contents);
					$name = explode('[/name]', $name1[1]);

					//if not empty, read in the styles
					if ($out[0] && $name[0]){
						$arr_outputs[$name[0]] = array($out[0],$filePath);
					}
				}

			}
		}

		return $arr_outputs;

	}
?>