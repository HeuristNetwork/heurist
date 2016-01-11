<?php

/*
 * Copyright (C) 2005-2016 University of Sydney
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
 * use the style name passed in to find and return the corrosponding xsl file.
 *
 * @author      Stephen White   
 * @copyright   (C) 2005-2016 University of Sydney
 * @link        http://HeuristNetwork.org
 * @version     3.1.0
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 * @subpackage  Viewer/Transforms
 * @uses        HEURIST_FILESTORE_DIR
 */


require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../records/files/fileUtils.php');

$style = @$_REQUEST['style'];
define("SAVE_URI", "disabled");
if (preg_match("/(http:\/\/|\/)/", $style)) {
    
    $data = loadRemoteURLContent($style);
    if($data) {
    header('Content-type: text/xml; charset=utf-8');
        echo $data;
    }
    return;
    
    /*****DEBUG****/
    $handle = fopen($style, "rb");
    $contents = stream_get_contents($handle);
    fclose($handle);
    header('Content-type: text/xml; charset=utf-8');
    echo $contents;
    return;
} else if (is_dir(HEURIST_FILESTORE_DIR)) {
    if (is_dir(HEURIST_FILESTORE_DIR . 'xsl-templates')) {
        define('DIR', HEURIST_FILESTORE_DIR . 'xsl-templates');
    } else if (is_dir(HEURIST_FILESTORE_DIR . 'xsl')) {
        define('DIR', HEURIST_FILESTORE_DIR . 'xsl');
    }
} else if (is_dir('xsl-templates')) {
    define('DIR', 'xsl-templates');
} else if (is_dir('xsl')) {
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
foreach ($arr_files as $filename) {
    //if file is a stylesheet file
    if ($filename != $style . ".xsl") {
        continue;
    }
    $filePath = DIR . "/" . $filename;
    //read the required contents of the file.
    $handle = fopen($filePath, "rb");
    $contents = fread($handle, filesize($filePath));
    fclose($handle);
    if ($contents === false) {
        echo "<error> Error reading $filename from internal storage location </error>";
        return;
    }
    $parser = xml_parser_create();
    $ret = xml_parse_into_struct($parser, $contents, $vals, $index);
    if ($ret == 0) {
        echo "<parsererror> Error file $filename returns invalid XML (".xml_error_string(xml_get_error_code($parser)).")</parsererror>";
        xml_parser_free($parser);
        return;
    }
    echo $contents;
    return;
}
//fell through the loop above so no file found.
echo "<error> Specified file not found in internal storage location and specify a valid style </error>";
return;
?>