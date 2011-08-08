<?php
	/*<!--
	* createCrosswalkTable.php, Imports recordtypes from another Heurist database, 17-05-2011, by Juan Adriaanse
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo Sorting of columns - Matches sorts alphabatically, not numeric and Record type name doesn't seem to sort at all
	* @todo Find nicer way to show data in expanded row and tooltip
	-->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	// Requires admin user, access to definitions though get_definitions is open
	if (! is_admin()) {
		 print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You do not have sufficient privileges to access this page</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	mysql_connection_db_insert($tempDBName); // Use temp database
?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Heurist - Crosswalk</title>

<!-- YUI -->
<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/samples/yui-dt-expandable.css"/>
<link type="text/css" rel="stylesheet" href="../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/element/element-min.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/json/json-min.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
<script type="text/javascript" src="../../external/yui/2.8.2r1/samples/yui-dt-expandable.js"></script>
<script type="text/javascript" src="../../external/jquery/jquery.js"></script>
<style type="text/css">
.yui-skin-sam .yui-dt-liner {
	white-space:nowrap;
}
.yui-dt-expandablerow-trigger a {
	display:block;
	padding:20px 5px 0;
	cursor:pointer;
}
.tooltip {
	position:absolute;
	z-index:999;
	left:-9999px;
	top:0px;
	background-color:#dedede;
	padding:5px;
	border:1px solid #fff;
	min-width:200;
}
.yui-dt0-col-matches .yui-dt-liner, .yui-dt0-col-import .yui-dt-liner {
	text-align: center;
}
#popup-saved {text-align :center; color:#FFF; font-size: 18px; background-color: RGBA(0,0,0,0.8);padding: 0; width: 200px; height: 75px; top: 50%; left:50%; margin :-50px -100px; position: absolute;overflow: visible;-moz-border-radius-bottomleft:10px;-moz-border-radius-bottomright:10px;-moz-border-radius-topleft:10px;-moz-border-radius-topright:10px;-webkit-border-bottom-left-radius:10px;-webkit-border-bottom-right-radius:10px;-webkit-border-top-left-radius:10px;-webkit-border-top-right-radius:10px;border-bottom-left-radius:10px;border-bottom-right-radius:10px;border-top-left-radius:10px;border-top-right-radius:10px;border :2px solid #FFF;-webkit-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);-moz-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);z-index: 100;}
#popup-saved b {font-size: 16px;line-height:75px;}
</style>

<div id=popup-saved style="display: none">
	<b>Import succesfull</b>
</div>

<script type="text/javascript">
var crwSourceDBID = "1";
var crwDefType = "";
var crwLocalCode = "";
var replaceRecTypeName = "";

var rectypeStructures = [];
var approxRectypes = [];
// Fills the YUI Datatable with all recordtypes from the temp DB
function insertData() {
	<?php
	$rectypes = mysql_query("select * from ".$tempDBName.".defRecTypes");
	$approxMatches;
	// For every recordtype in the temp DB
	while($rectype = mysql_fetch_assoc($rectypes)) {
		$OriginatingDBID = $rectype["rty_OriginatingDBID"];
		$IDInOriginatingDB = $rectype["rty_IDInOriginatingDB"];
		$nameInTempDB = mysql_real_escape_string($rectype["rty_Name"]);

		// Find recordtypes that are already in the local DB (comparing OriginatingDBID and IDInOriginatingDB
		$identicalMatches = mysql_query("select rty_ID from " . DATABASE . ".defRecTypes where rty_OriginatingDBID = $OriginatingDBID AND rty_IDInOriginatingDB = $IDInOriginatingDB");

		// These rectypes are not in the importing database
		if(mysql_num_rows($identicalMatches) == 0) {
			$approxMatches = mysql_query("select rty_Name, rty_Description from " . DATABASE . ".defRecTypes where (rty_Name like '%$nameInTempDB%')"); // TODO: if rectype is more than one word, check for both words
			$numberOfApproxMatches = mysql_num_rows($approxMatches);
			// Add all approximate matches to a javascript array
			if($numberOfApproxMatches > 0) {
				while($approxRectypes = mysql_fetch_array($approxMatches, MYSQL_ASSOC)) {
					echo 'if(!approxRectypes['.$IDInOriginatingDB.']) {' . "\n";
					echo 'approxRectypes['.$IDInOriginatingDB.'] = new Array();' . "\n";
					echo '}' . "\n";
					echo 'var approxRtysRow = [];' . "\n";
					$approxRectypes["rty_Name"] = mysql_escape_string($approxRectypes["rty_Name"]);
					$approxRectypes["rty_Description"] = mysql_escape_string($approxRectypes["rty_Description"]);
					echo 'approxRtysRow[0] = "'.$approxRectypes["rty_Name"].'";' . "\n";
					echo 'approxRtysRow[1] = "'.$approxRectypes["rty_Description"].'";' . "\n";
					echo 'approxRectypes['.$IDInOriginatingDB.'].push(approxRtysRow);' . "\n";
				}
			}
			mysql_query("use ".$tempDBName);
			$rtyData = mysql_query("select defRecTypes.rty_ID, defRecStructure.rst_ID, defRecStructure.rst_DetailTypeID, defRecStructure.rst_DisplayName, defDetailTypes.dty_ID, defDetailTypes.dty_Name, defDetailTypes.dty_Type, defDetailTypes.dty_Status, defRecTypes.rty_Description from defRecTypes left join defRecStructure on defRecTypes.rty_ID=defRecStructure.rst_RecTypeID left join defDetailTypes on defRecStructure.rst_DetailTypeID=defDetailTypes.dty_ID order by defRecTypes.rty_ID");
			// Add recordtypes to the table
			echo 'myDataTable.addRow({arrow:"<img id=\"arrow'.$rectype["rty_ID"].'\" src=\"../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png\" />",rtyID:"'.$rectype["rty_ID"].'",rectype:"'.$rectype["rty_Name"].'",matches:"'.$numberOfApproxMatches.'",import:"<a href=\"#import\"><img id=\"importIcon'.$rectype["rty_ID"].'\" src=\"import_icon.png\" width=\"16\" height=\"16\" /></a>"});' . "\n";
		}
	}
	// For every recordtype, add the structure to a javascript array, to show in the tooltip
	if(isset($rtyData)) {
		while($rectypeStructure = mysql_fetch_array($rtyData, MYSQL_ASSOC)) {
			echo 'if(!rectypeStructures['.$rectypeStructure["rty_ID"].']) {' . "\n";
			echo 'rectypeStructures['.$rectypeStructure["rty_ID"].'] = new Array();' . "\n";
			echo '}' . "\n";
			echo 'var rtyDataRow = [];' . "\n";
			$rectypeStructure["rst_DisplayName"] = mysql_escape_string($rectypeStructure["rst_DisplayName"]);
			echo 'rtyDataRow[0] = "'.$rectypeStructure["rst_DisplayName"].'";' . "\n";
			$rectypeStructure["dty_Name"] = mysql_escape_string($rectypeStructure["dty_Name"]);
			echo 'rtyDataRow[1] = "'.$rectypeStructure["dty_Name"].'";' . "\n";
			echo 'rtyDataRow[2] = "'.$rectypeStructure["dty_Type"].'";' . "\n";
			echo 'rtyDataRow[3] = "'.$rectypeStructure["dty_Status"].'";' . "\n";
			$rectypeStructure["rty_Description"] = mysql_escape_string($rectypeStructure["rty_Description"]);
			echo 'rtyDataRow[4] = "'.$rectypeStructure["rty_Description"].'";' . "\n";
			echo 'rectypeStructures['.$rectypeStructure["rty_ID"].'].push(rtyDataRow);' . "\n";
		}
	}
	?>
}
<?php
echo 'var tempDBName = "'.$tempDBName.'";';
echo 'var URLBase = "'.HEURIST_URL_BASE.'";';
echo 'var importingDBName = "'.HEURIST_DBNAME.'";';
echo 'var importingDBFullName = "'.DATABASE.'";';
?>
var myDataTable;
var hideTimer;
var needHideTip;
YAHOO.util.Event.addListener(window, "load", function() {
	YAHOO.example.Basic = function() {
		// Create the columns. Arrow contains the collapse/expand arrow, rtyID is hidden and contains the ID, rectype contains the name, matches the amount of matches and a tooltip, import a button
		var myColumnDefs = [
			{ key:"arrow", label:"", formatter:YAHOO.widget.RowExpansionDataTable.formatRowExpansion },
			{ key:"rtyID", label:"<u>ID</u>", sortable:true, hidden:true },
			{ key:"rectype", label:"<u>Record type</u>", sortable:true, resizeable:true, width:200 },
			{ key:"matches", label:"<u>Matches</u>", sortable:true, resizeable:true, parser:'number', width:50 },
			{ key:"import", label:"Import", sortable:false, resizeable:false, width:30 }
		];

		var myDataSource = new YAHOO.util.DataSource();
		myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
		myDataSource.responseSchema = { fields: ["arrow","rtyID","rectype","matches","import"] };
		YAHOO.widget.DataTable.MSG_EMPTY = "There are no new record types to import from this database (all types already exist in the target)";
		// Create the RowExpansionDataTable
		myDataTable = new YAHOO.widget.RowExpansionDataTable(
			"crosswalkTable",
			myColumnDefs,
			myDataSource,
			{
			// Create the expansion for every recordtype, showing all it's recstructure, and the detailtype name and type the recstructures point to
			rowExpansionTemplate:
				function(obj) {
					var rty_ID = obj.data.getData('rtyID');
					var info = "<br /><i>" + rectypeStructures[rty_ID][0][4] + "</i><br /><br />";
					info += '<table border="1"><tr><td><strong>Fieldname</strong></td><td><strong>Fieldtype</strong></td><td><strong>Datatype</strong></td><td><strong>Status</strong></td></tr></strong><br />';

					// 0 = rst_DisplayName
					// 1 = dty_Name
					// 2 = dty_Type
					// 3 = dty_Status
					for(i = 0; i < rectypeStructures[rty_ID].length; i++) {
						info += "<tr><td>" + rectypeStructures[rty_ID][i][0] + "</td><td>" + rectypeStructures[rty_ID][i][1] + "</td><td>" + rectypeStructures[rty_ID][i][2] + "</td><td>" + rectypeStructures[rty_ID][i][3] + "</td></tr>";
					}
					info += "</table><br />";
					obj.liner_element.innerHTML += info;
				},
				paginator: new YAHOO.widget.Paginator({
                    rowsPerPage:50,
                    containers:['topPagination','bottomPagination']
                }),
                sortedBy: { key:'rectype' }
			}
		);
		insertData();

		myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow);
		myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow);
		// If a row is clicked, except the import column, expand the row, and change the arrow
		myDataTable.subscribe( 'cellClickEvent', function(oArgs) {
			var column = this.getColumn(oArgs.target);
			var record_id;
			var oRecord;
			var row;
			if(column.key != "import") {
				if(YAHOO.util.Dom.hasClass(oArgs.target, 'yui-dt-expandablerow-trigger')) {
					record_id = oArgs.target;
					oRecord = this.getRecord(record_id);
				} else {
					oRecord = this.getRecord(oArgs.target);
					record_id = myDataTable.getTdEl({record:oRecord, column:myDataTable.getColumn("rtyID")});
				}
				if(record_id != null) {
					oRecord = this.getRecord(record_id);
					var rty_ID = oRecord.getData("rtyID");
					myDataTable.toggleRowExpansion(record_id);
					expandedRecord = rty_ID;
				}
				var arrowElementSrc = document.getElementById("arrow"+rty_ID).src;
				if(arrowElementSrc.indexOf("arrow_closed") != -1) {
					document.getElementById("arrow"+rty_ID).src = "../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_open.png";
				} else {
					document.getElementById("arrow"+rty_ID).src = "../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png";
				}
			}
		});
		// If the import icon is clicked, start the import process, and lock the importing of other ones until it is done
		myDataTable.subscribe('linkClickEvent', function(oArgs) {
			var elLink = oArgs.target;
			var oRecord = this.getRecord(elLink);
			var rty_ID = oRecord.getData("rtyID");
			if(elLink.hash === "#import") {
				if(importPending) {
					alert("Please wait until previous import is complete.");
				} else {
					importedRowID = oRecord.getId();
					processAction(rty_ID, "import");
				}
			}
		});

		// Show tooltip on mouseover the matches column
		myDataTable.on('cellMouseoverEvent', function (oArgs) {
			var elLink = oArgs.target;
			var column = this.getColumn(elLink);
			if(column != null && column.key == 'matches') {
				var oRecord = this.getRecord(elLink);
				var rty_ID = oRecord.getData("rtyID");
				var rty_Name = oRecord.getData("rectype");
				showMatchesTooltip(rty_ID, rty_Name, oArgs.event);
			}
		});
		// Hide tooltip on mouseout
		myDataTable.on('cellMouseoutEvent', function (oArgs) {
			hideTimer = window.setTimeout(_hideToolTip, 2000);
		});

		return {
			oDS: myDataSource,
			oDT: myDataTable
		};
	}();
});

// Create the tooltip
var currentTipId;
var needHideTip;
function showMatchesTooltip(rty_ID, rty_Name, event) {
	// Tooltip div mouse out
	function __hideToolTip() {
		needHideTip = true;
	}
	// Tooltip div mouse over
	function __clearHideTimer() {
		needHideTip = false;
		clearHideTimer();
	}
	var forceHideTip = true;
	if(rty_ID != null) {
		if(currentTipId != rty_ID) {
			currentTipId = rty_ID;
			// 0 = rty_Name
			// 1 = rty_Description
			var textTip = '<strong>Approximate matches for record type: '+rty_Name+'</strong><br /><br />';
			for(i = 0; i < approxRectypes[rty_ID].length; i++) {
				textTip += "<li>"+approxRectypes[rty_ID][i][0] + " - " + approxRectypes[rty_ID][i][1] + "</li>";
			}
		} else {
			forceHideTip = false;
		}
	}
	if(textTip != null) {
		clearHideTimer();
		needHideTip = true;
		var my_tooltip = $("#toolTip");

		my_tooltip.mouseover(__clearHideTimer);
		my_tooltip.mouseout(__hideToolTip);
		var xy = top.HEURIST.util.getMousePos(event);
		my_tooltip.html(textTip);
		top.HEURIST.util.showPopupDivAt(my_tooltip, xy, $(window).scrollTop(), $(window).width(), $(window).height());
		var hideTimer = window.setTimeout(_hideToolTip, 2000);
	}
	else if(forceHideTip) {
		needHideTip = false;
		_hideToolTip();
	}
}
function clearHideTimer() {
	if(hideTimer) {
		window.clearTimeout(hideTimer);
		hideTimer = 0;
	}
}
function _hideToolTip(){
	if(needHideTip) {
		currentTipId = null;
		clearHideTimer();
		var my_tooltip = $("#toolTip");
		my_tooltip.css( {
			left:"-9999px"
		});
	}
}
</script>
<link rel=stylesheet href="../../common/css/global.css">
<link rel=stylesheet href="../../common/css/admin.css">
</head>
<div class="banner"><h2>Import record types</h2></div>
<body class="popup yui-skin-sam" style="overflow: auto;" onbeforeunload="dropTempDB(false)">
<script src="../../common/js/utilsLoad.js"></script>
<script src="../../common/js/utilsUI.js"></script>
<!-- <div id="page-inner" style="overflow:auto"> -->
<br /><br />
<a id="shortLog" onClick="showShortLog()" href="#">Show short log</a><br />
<a id="detailedLog" onClick="showDetailedLog()" href="#">Show detailed log</a><br /><br />
<div id="log"></div><br />
<div id="log"></div>
<button id="finish1" onClick="dropTempDB(true)">Finished</button>
<div id="crosswalk" style="width:100%;margin:auto;">
	<div id="topPagination"></div>
	<div id="crosswalkTable"></div>
	<div id="bottomPagination"></div>
</div>
<i>Note: If this function reports 'No records found' this normally means that there are no 
definitions in the selected database which are not already in the current database. 
</i>
<br>&nbsp;<br>

<button id="finish2" onClick="dropTempDB(true)">Finished</button>
<div class="tooltip" id="toolTip"><p>tooltip</p></div>

<script type="text/javascript">

var detailedImportLog = "";
var shortImportLog = "";
var result = "";
var importedRowID;
var importPending = false;
// Start an asynchronous call, sending the recordtypeID and action
function processAction(rtyID, action) {
	// Lock import, and set import icon to loading icon
	if(action == "import") {
		importPending = true;
		document.getElementById("importIcon"+rtyID).src = "../../common/images/mini-loading.gif";
	}
	var xmlhttp;
	if (action.length == 0) {
		document.getElementById("log").innerHTML="";
		return;
	}
	if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function() {
		if (xmlhttp.readyState==4 && xmlhttp.status==200) {
			importPending = false;
			var response = xmlhttp.responseText;
			// Handle the response, and give feedback
			if(response.substring(0,6) == "prompt") {
				document.getElementById("importIcon"+rtyID).src = "import_icon.png";
				changeDuplicateEntryName(rtyID);
			}
			else if(response.substring(0,5) == "Error") {
				document.getElementById("importIcon"+rtyID).src = "import_icon.png";

				shortImportLog += '<p style="color:red">'+response+"</p>";
				result += response+"\n\n";
				detailedImportLog += '<p style="color:red">'+response+"</p>";

				document.getElementById("popup-saved").innerHTML = "<b>Error. Check log for details</b>";
				setTimeout(function() {
					document.getElementById("popup-saved").style.display = "block";
					setTimeout(function() {
						document.getElementById("popup-saved").style.display = "none";
					}, 1000);
				}, 0);
				document.getElementById("log").style.color = "red";
				document.getElementById("log").innerHTML=response;
				document.getElementById("popup-saved").innerHTML = "<b>Import succesfull</b>";
			}
			else {
				detailedImportLog += response;
				response = response.split("<br />");
				result += response[0]+"\n\n";
				shortImportLog += response[0]+"<br />";

				setTimeout(function() {
					document.getElementById("popup-saved").style.display = "block";
					setTimeout(function() {
						document.getElementById("popup-saved").style.display = "none";
					}, 1000);
				}, 0);
				if(document.getElementById("detailedLog").innerHTML == "Hide detailed log") {
					document.getElementById("log").innerHTML = detailedImportLog;
				} else if(document.getElementById("shortLog").innerHTML == "Hide short log") {
					document.getElementById("log").innerHTML = shortImportLog;
				}

				document.getElementById("log").style.color = "green";
				myDataTable.deleteRow(importedRowID, -1);
			}
		}
		else {
		}
	}
	xmlhttp.open("GET","processAction.php?action="+action+"&tempDBName="+tempDBName+"&crwSourceDBID="+crwSourceDBID+"&crwSourceCode="+rtyID+"&crwDefType="+crwDefType+"&crwLocalCode="+crwLocalCode+"&replaceRecTypeName="+replaceRecTypeName+"&importingDB="+importingDBFullName,true);
	xmlhttp.send();
}

function showShortLog() {
	if(document.getElementById("detailedLog").innerHTML == "Hide detailed log") {
		document.getElementById("detailedLog").innerHTML = "Show detailed log";
	}
	if(document.getElementById("shortLog").innerHTML == "Show short log") {
		document.getElementById("shortLog").innerHTML = "Hide short log";
		if(shortImportLog == "") {
			document.getElementById("log").innerHTML = "Nothing has been imported yet";
		} else {
			document.getElementById("log").innerHTML = shortImportLog;
		}
	} else {
		document.getElementById("shortLog").innerHTML = "Show short log";
		document.getElementById("log").innerHTML = "";
	}
}

function showDetailedLog() {
	if(document.getElementById("shortLog").innerHTML == "Hide short log") {
		document.getElementById("shortLog").innerHTML = "Show short log";
	}
	document.getElementById("log").innerHTML = detailedImportLog;
	if(document.getElementById("detailedLog").innerHTML == "Show detailed log") {
		document.getElementById("detailedLog").innerHTML = "Hide detailed log";
		if(shortImportLog == "") {
			document.getElementById("log").innerHTML = "Nothing has been imported yet";
		} else {
			document.getElementById("log").innerHTML = detailedImportLog;
		}
	} else {
		document.getElementById("detailedLog").innerHTML = "Show detailed log";
		document.getElementById("log").innerHTML = "";
	}
}

// If after trying an import the response says that the rectype name already exists, ask user to enter a new one
var replaceRecTypeName = "";
function changeDuplicateEntryName(rtyID) {
	var newRecTypeName = prompt("An entry with the exact same name already exist.\n\nPlease enter a new name for this rectype, or cancel to stop importing it.","");
	if(newRecTypeName == null || newRecTypeName == "") {
		document.getElementById("log").style.color = "red";
		document.getElementById("log").innerHTML="You have to enter a valid new name to import a new rectype with an existing name.";
		alert("You have to enter a valid new name to import a new rectype with an existing name.");
	}
	else {
		replaceRecTypeName = newRecTypeName;
		processAction(rtyID, "import");
	}
}

// Drop the temp DB when the page is closed, or 'Finish crosswalk' is clicked
var dropped = false;
function dropTempDB(redirect) {
	if(!dropped) {
		dropped = true;
		processAction(0, "drop");
	}
	if(redirect) {
		window.location = "about:blank";
	} else {
		if(result == "") {
			// This is jsut a nuisance: alert("Nothing was imported");
		} else {
			alert(result);
		}
	}
}
</script>
</div>
</body>
</head>