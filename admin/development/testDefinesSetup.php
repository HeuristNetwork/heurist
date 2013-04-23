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



require_once(dirname(__FILE__).'/../../common/config/initialise.php');

echo "<span style=\"padding-right:73px;\">&nbsp;</span>HEURIST_SERVER_NAME - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". (defined("HEURIST_SERVER_NAME") ? HEURIST_SERVER_NAME : "not defined") ."<br><br>";
echo "<span style=\"padding-right:43px;\">&nbsp;</span>HEURIST_DOCUMENT_ROOT - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_DOCUMENT_ROOT") ?  HEURIST_DOCUMENT_ROOT : "not defined") ."<br><br>";
echo "<span style=\"padding-right:100px;\">&nbsp;</span>HEURIST_SITE_PATH - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_SITE_PATH") ?  HEURIST_SITE_PATH : "not defined") ."<br><br>";
echo "<span style=\"padding-right:102px;\">&nbsp;</span>HEURIST_BASE_URL - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_BASE_URL") ?  HEURIST_BASE_URL : "not defined") ."<br><br>";
echo "<span style=\"padding-right:68px;\">&nbsp;</span>HEURIST_HTTP_PROXY - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_HTTP_PROXY") ?  HEURIST_HTTP_PROXY : "not defined") ."<br><br>";
echo "<span style=\"padding-right:68px;\">&nbsp;</span>HEURIST_UPLOAD_ROOT - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_UPLOAD_ROOT") ?  HEURIST_UPLOAD_ROOT : "not defined") ."<br><br>";
echo "<span style=\"padding-right:83px;\">&nbsp;</span>HEURIST_UPLOAD_DIR - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_UPLOAD_DIR") ?  HEURIST_UPLOAD_DIR : "not defined") ."<br><br>";
echo "<span style=\"padding-right:8px;\">&nbsp;</span>HEURIST_ICON_BASE_SITE_PATH - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_ICON_BASE_SITE_PATH") ?  HEURIST_ICON_BASE_SITE_PATH : "not defined") ."<br><br>";
echo "<span style=\"padding-right:0px;\">&nbsp;</span>HEURIST_URL_BASE_UPLOAD_DIR - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_URL_BASE_UPLOAD_DIR") ?  HEURIST_URL_BASE_UPLOAD_DIR : "not defined") ."<br><br>";
echo "<span style=\"padding-right:60px;\">&nbsp;</span>HEURIST_ICON_SITE_PATH - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_ICON_SITE_PATH") ?  HEURIST_ICON_SITE_PATH : "not defined") ."<br><br>";
echo "<span style=\"padding-right:110px;\">&nbsp;</span>HEURIST_ICON_DIR - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_ICON_DIR") ?  HEURIST_ICON_DIR : "not defined") ."<br><br>";
echo "<span style=\"padding-right:93px;\">&nbsp;</span>HEURIST_THUMB_DIR - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_THUMB_DIR") ?  HEURIST_THUMB_DIR : "not defined") ."<br><br>";
echo "<span style=\"padding-right:41px;\">&nbsp;</span>HEURIST_THUMB_BASE_URL - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_THUMB_BASE_URL") ?  HEURIST_THUMB_BASE_URL : "not defined") ."<br><br>";
echo "<span style=\"padding-right:69px;\">&nbsp;</span>HEURIST_HML_PUBPATH - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_HML_PUBPATH") ?  HEURIST_HML_PUBPATH : "not defined") ."<br><br>";
echo "<span style=\"padding-right:62px;\">&nbsp;</span>HEURIST_HTML_PUBPATH - &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".(defined("HEURIST_HTML_PUBPATH") ?  HEURIST_HTML_PUBPATH : "not defined") ."<br><br>";
//echo "". ."<br><br>";
//echo "". ."<br><br>";
//echo "". ."<br><br>";
//echo "". ."<br><br>";
//echo "". ."<br><br>";

?>
