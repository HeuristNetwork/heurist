<?php

	/* getDBStructureAsforms.php - returns database definitions (rectypes, details etc.)
	* as form data ready for use in mobile app - primarily intended for NeCTAR FAIMS project
	*
	* @Author Stephen White
	* @author Artem Osmakov
	* @copyright (C) 2012 AeResearch University of Sydney.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
	* @todo
	*
	*
	-->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__).'/../../admin/describe/rectypeXFormLibrary.php');

	// Deals with all the database connections stuff
	//define("DT_DRAWING","2-59");
	mysql_connection_db_select(DATABASE);
	if(mysql_error()) {
		die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
	}
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
/* Ian's request 2012-11-23
	if (! is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to export structure as forms</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
*/
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Export record type structures to forms</title>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
	</head>
	<?php
		if(array_key_exists("rectypes", $_REQUEST)){
			$rectypes = $_REQUEST['rectypes'];
		}else{
			$rectypes = "";
		}

		if(!array_key_exists("mode", $_REQUEST) || $_REQUEST['mode']!="export"){
		?>
		<body onload="{_recreateRecTypesList('<?=$rectypes?>', true)}" style="padding: 10px;">
			<script src="../../common/php/loadCommonInfo.php"></script>
			<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
			<script type="text/javascript">

				var Hul = top.HEURIST.util;

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
						} //for
					}

					document.getElementById("form1").style.display = Hul.isempty(txt)?"none":"block";

					if(isFirst && Hul.isempty(txt)){
						_onSelectRectype();
						document.getElementById("rectypesList").innerHTML = "";
					}else{
						document.getElementById("rectypesList").innerHTML = "<h3>Record types to export:</h3>&nbsp;"+txt;
					}

				}

				function _onSelectRectype()
				{
					var URL = top.HEURIST.basePath + "admin/structure/selectRectype.html?type=resource&ids="+document.getElementById("rectypes").value+"&db=<?=HEURIST_DBNAME?>";

					Hul.popupURL(top, URL, {
							"close-on-blur": false,
							"no-resize": true,
							height: 480,
							width: 440,
							callback: function(recordTypesSelected) {
								if(!Hul.isnull(recordTypesSelected)) {
									document.getElementById("rectypes").value = recordTypesSelected;

									_recreateRecTypesList(recordTypesSelected, false);
								}
							}
					});
				}
			</script>

			<div style="" id="rectypesList"></div>
			<input type="button" value="Select rectypes" onclick="_onSelectRectype()">
			<br/>
			<form id="form1" action="getDBStructureAsXForms.php" method="post">
				<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
				<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
				<input name="mode" value="export" type="hidden">
				<input id="btnStart" type="submit" value="Start export">
			</form>

		</body>
	</html>
	<?php
	}else{
	?>
	<body style="padding: 10px;">
		Export in progress....

		<?php

			$folder = HEURIST_UPLOAD_DIR."xforms/";

			if(!file_exists($folder)){
				if (!mkdir($folder, 0777, true)) {
					print '<font color="red">Failed to create folder for forms!</font>';
					return;
				}
			}

			$a_rectypes = explode(",",$rectypes);
			$formsList = "<?xml version=\"1.0\"?>\n".
						"<forms>\n";
			$xformsList = "<?xml version=\"1.0\"?>\n".
						"<xforms>\n";

			foreach ($a_rectypes as $rt_id) {
				if($rt_id){
					print "<div>".createform($rt_id)."</div> ";
				}
			}

			$formsList .= "</forms>\n";
			$xformsList .= "</xforms>\n";
			file_put_contents($folder."formList", $formsList);
			file_put_contents($folder."xformList", $xformsList);
//			chgrp($folder."formList","acl");
			print "<div>Wrote $folder"."xformList </div>\n";
		?>
		<br/><br/>
		<form id="form2" action="getDBStructureAsXForms.php" method="get">
			<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
			<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
			<input type="submit" value="Start over">
		</form>
	</body>
	</html>
	<?php
	}

	return;

	//
	// Creates form, save it into FILESTORE/forms folder and add it to the manifest lists
	//
	function createform($rt_id){

		global $folder, $formsList, $xformsList;


		try{

			list($form,$rtName,$rtConceptID,$rtDescription,$report) = buildform($rt_id);

			if ($form){
				$filename = preg_replace('/[^a-zA-Z0-9-_\.]/','', $rtName);//todo this is not international, need to strip only illegal filesys characters and perhaps trim spaces to single space
				$fullfilename = $folder.$filename.".xml";

				file_put_contents($fullfilename, $form);
	//			chgrp($fullfilename,"acl");
				//TODO: update formlist for this form.
				$report = $report."$rtName Form Saved ok ($fullfilename)<br/>";
				$xformEntry = "<xform>\n".
								"<downloadUrl>http://heuristscholar.org/hayes/xforms/$filename.xml</downloadUrl>\n".
								"<formID>$rtConceptID</formID>\n".
								"<name>$rtName Record Form</name>\n".
								"<descriptionText>$rtName Record as defined in the \"".HEURIST_DBNAME."\" database described as \"$rtDescription\" </descriptionText>\n".
								"<majorMinorVersion>".date("Ymd")."</majorMinorVersion>\n".
								"<version>".date("Ymd")."</version>\n".
								"<hash>md5:".md5_file($fullfilename)."</hash>\n".
							"</xform>\n";
				$formEntry = "<form url=\"http://heuristscholar.org/hayes/xforms/$filename.xml\">$rtName</form>\n";
			}
		} catch (Exception $e) {
			$report = $report.'Exception '.($e->getMessage());
			$formEntry = $xformEntry = "";
		}
		$formsList .= ($formEntry?$formEntry:"");
		$xformsList .= ($xformEntry?$xformEntry:"");
		return "<h2>$rtName</h2>".$report."<br/>";
	}

//end function

?>
