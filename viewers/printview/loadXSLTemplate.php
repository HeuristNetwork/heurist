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

	/* load the read through the xsl directory detecting thoses xsl files that are to written for print formatting. Output an array
	into JS */
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	$style = @$_REQUEST['style'];

	define("SAVE_URI", "disabled");
	if ( preg_match("/(http:\/\/|\/)/",$style)){
//error_log("style is ".print_r($style,true));
		$handle = fopen($style, "rb");
		$contents = stream_get_contents($handle);
		fclose($handle);
		header('Content-type: text/xml; charset=utf-8');
		echo $contents;
		return;
	}else if (is_dir(HEURIST_UPLOAD_DIR)) {
		define('DIR', HEURIST_UPLOAD_DIR.'xsl-templates');
	}else if(is_dir('xsl-templates')) {
		define('DIR', 'xsl-templates');
	}else if(is_dir('xsl')) {
		define('DIR', 'xsl');
	}
	// using ob_gzhandler makes this stuff up on IE6-

	header('Content-type: text/xml; charset=utf-8');

	if (!$style) {
		echo "<error> no style specified please call with &style=  and specify a valid style </error>";
		return;
	}


	//open directory and read in file names
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
		if ($filename != $style.".xsl") {
			continue;
		}
		$filePath = DIR."/".$filename;
		//read the required contents of the file.
		$handle = fopen($filePath, "rb");
		$contents = fread($handle, filesize($filePath));
		fclose($handle);
		echo $contents;
		return;
	}

?>