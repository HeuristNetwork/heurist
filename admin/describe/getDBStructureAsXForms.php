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
* writeout a XForm for each rectype and XForm manifest of created form definition using
* Heurist database definitions (rectypes, details etc.) for uses in ODK Collect Android app
* ready for use in mobile app - primarily intended for NeCTAR FAIMS project
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../common/php/dbMySqlWrappers.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');
require_once (dirname(__FILE__) . '/../../admin/describe/rectypeXFormLibrary.php');
// Deals with all the database connections stuff
mysql_connection_select(DATABASE);
if (mysql_error()) {
	die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
}
if (!is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=' . HEURIST_DBNAME);
	return;
}

?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Export record type structures to forms</title>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
	</head>
	<?php
if (array_key_exists("rectypes", $_REQUEST)) {
	$rectypes = $_REQUEST['rectypes'];
} else {
	$rectypes = "";
}
if (!array_key_exists("mode", $_REQUEST) || $_REQUEST['mode'] != "export") {
?>
	<body onload="{_recreateRecTypesList('<?=$rectypes?>', true)}" style="padding: 10px;">
		<script src="../../common/php/loadCommonInfo.php"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		<script type="text/javascript">

			function _recreateRecTypesList(value, isFirst) {

				var txt = "";

				if(value) {
					var arr = value.split(","),
					ind, dtName;
					for (ind in arr) {
						var ind2 = Number(arr[Number(ind)]);
						if(!isNaN(ind2)){
							dtName = top.HEURIST.rectypes.names[ind2];
							if(!txt) {
								txt = dtName;
							}else{
								txt += ", " + dtName;
							}
						}
					} //for loop
				}

				document.getElementById("form1").style.display = top.HEURIST.util.isempty(txt)?"none":"block";

				if(isFirst && top.HEURIST.util.isempty(txt)){
					_onSelectRectype();
					document.getElementById("rectypesList").innerHTML = "";
				}else{
					document.getElementById("rectypesList").innerHTML = "<h3>Record types to export:</h3>&nbsp;"+txt;
				}

			}

			function _onSelectRectype()
			{
				var URL = top.HEURIST.baseURL_V3 + "admin/structure/rectypes/selectRectype.html?type=resource&ids="+document.getElementById("rectypes").value+"&db=<?=HEURIST_DBNAME?>";

				top.HEURIST.util.popupURL(top, URL, {
						"close-on-blur": false,
						"no-resize": true,
						height: 480,
						width: 440,
						callback: function(recordTypesSelected) {
							if(!top.HEURIST.util.isnull(recordTypesSelected)) {
								document.getElementById("rectypes").value = recordTypesSelected;

								_recreateRecTypesList(recordTypesSelected, false);
							}
						}
				});
			}
		</script>

		<div style="" id="rectypesList"></div>
		<input type="button" value="Select record types" onclick="_onSelectRectype()">
		<br/>
		<form id="form1" action="getDBStructureAsXForms.php" method="post">
			<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
			<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
			<input name="mode" value="export" type="hidden">
			<input id="btnStart" type="submit" value="Start export">
		</form>

	</body>
<?php
} else {
?>
	<body style="padding: 10px;">
		Export in progress....

<?php
	$folder = HEURIST_FILESTORE_DIR . "xforms/";
	if (!file_exists($folder)) {
		if (!mkdir($folder, 0777, true)) {
			print '<font color="red">Failed to create folder for forms!</font>';
			return;
		}
	}
	$a_rectypes = explode(",", $rectypes);
	$formsList = "<?xml version=\"1.0\"?>\n" . "<forms>\n";
	$xformsList = "<?xml version=\"1.0\"?>\n" . "<xforms>\n";
	/**
	* Create Xform for each recType
	*/
	foreach ($a_rectypes as $rtyID) {
		if ($rtyID) {
			print "<div>" . createform($rtyID) . "</div> ";
		}
	}
	$formsList.= "</forms>\n";
	$xformsList.= "</xforms>\n";
	/**
	* Write out the XForm List file (also output oldstyle ODK list file
	*/
	file_put_contents($folder . "formList", $formsList);
	file_put_contents($folder . "xformList", $xformsList);
	print "<div>Wrote $folder" . "xformList </div>\n";
?>
		<br/><br/>
		<form id="form2" action="getDBStructureAsXForms.php" method="get">
			<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
			<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
			<input type="submit" value="Start over">
		</form>
	</body>
<?php
}
?>
</html>
<?php
return;

/**
 * Creates xform for $rtyID, saves it into HEURIST_FILESTORE_DIR/xforms/ folder and adds an entry to the manifest lists
 * @global   string [$folder] path of where to store output form
 * @global   string [$formsList] manifest stream of forms oldstyle ODK
 * @global   string [$xformsList] manifest stream of xforms
 * @param    integer [$rtyID] the local id of the recType to creat the xform for
 * @return   string report about success or failure of the forms creation
 * @uses     buildform() from rectypeXFormLibrary to build the form
 * @uses     HEURIST_DBNAME
 */
function createform($rtyID) {
	global $folder, $formsList, $xformsList;
	try {
		list($form, $rtName, $rtConceptID, $rtDescription, $report) = buildform($rtyID);
		if ($form) {
			$filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $rtName); //todo this is not international, need to strip only illegal filesys characters and perhaps trim spaces to single space
			$fullfilename = $folder . $filename . ".xml";
			file_put_contents($fullfilename, $form);
			//			chgrp($fullfilename,"acl");
			//TODO: update formlist for this form.
			$report = $report . "$rtName Form Saved ok ($fullfilename)<br/>";
			$xformEntry = "<xform>\n" . "<downloadUrl>http://heuristscholar.org/hayes/xforms/$filename.xml</downloadUrl>\n" . "<formID>$rtConceptID</formID>\n" . "<name>$rtName Record Form</name>\n" . "<descriptionText>$rtName Record as defined in the \"" . HEURIST_DBNAME . "\" database described as \"$rtDescription\" </descriptionText>\n" . "<majorMinorVersion>" . date("Ymd") . "</majorMinorVersion>\n" . "<version>" . date("Ymd") . "</version>\n" . "<hash>md5:" . md5_file($fullfilename) . "</hash>\n" . "</xform>\n";
			$formEntry = "<form url=\"http://heuristscholar.org/hayes/xforms/$filename.xml\">$rtName</form>\n";
		}
	}
	catch(Exception $e) {
		$report = $report . 'Exception ' . ($e->getMessage());
		$formEntry = $xformEntry = "";
	}
	$formsList.= ($formEntry ? $formEntry : "");
	$xformsList.= ($xformEntry ? $xformEntry : "");
	return "<h2>$rtName</h2>" . $report . "<br/>";
}
//end function

?>
