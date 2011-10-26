<?php
	/*<!--
	* createCrosswalkTable.php, Imports recordtypes from another Heurist database, 17-05-2011, by Juan Adriaanse
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	*
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
#yui-dt0-th-import {width:30px}
.yui-dt0-col-import div.yui-dt-liner {text-align:center}
#yui-dt0-th-arrow {width:24px}
.yui-dt0-col-matches .yui-dt-liner, .yui-dt0-col-import .yui-dt-liner {text-align: center;}


#popup-saved {text-align :center; color:#FFF; font-size: 18px; background-color: RGBA(0,0,0,0.8);padding: 0; width: 200px; height: 75px; top: 50%; left:50%; margin :-50px -100px; position: absolute;overflow: visible;-moz-border-radius-bottomleft:10px;-moz-border-radius-bottomright:10px;-moz-border-radius-topleft:10px;-moz-border-radius-topright:10px;-webkit-border-bottom-left-radius:10px;-webkit-border-bottom-right-radius:10px;-webkit-border-top-left-radius:10px;-webkit-border-top-right-radius:10px;border-bottom-left-radius:10px;border-bottom-right-radius:10px;border-top-left-radius:10px;border-top-right-radius:10px;border :2px solid #FFF;-webkit-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);-moz-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);z-index: 100;}
#popup-saved b {font-size: 16px;line-height:75px;}
</style>

<script type="text/javascript">
var sourceDBID = <?=$source_db_id?>;
//var crwDefType = "";
//var crwLocalCode = "";
var replaceRecTypeName = "";

var rectypeStructures = [];
var approxRectypes = [];
// Fills the YUI Datatable with all recordtypes from the temp DB
function insertData() {
	<?php
	$rectypes = mysql_query("select * from ".$tempDBName.".defRecTypes order by rty_Name");
	$approxMatches = array();
	$tableRows = array();
	// For every recordtype in the temp DB
	while($rectype = mysql_fetch_assoc($rectypes)) {
		$OriginatingDBID = $rectype["rty_OriginatingDBID"];
		$IDInOriginatingDB = $rectype["rty_IDInOriginatingDB"];
		$nameInTempDB = mysql_real_escape_string($rectype["rty_Name"]);

		// Find recordtypes that are already in the local DB (comparing OriginatingDBID and IDInOriginatingDB
		$identicalMatches = mysql_query("select rty_ID from " . DATABASE . ".defRecTypes where rty_OriginatingDBID = $OriginatingDBID AND rty_IDInOriginatingDB = $IDInOriginatingDB");

		// These rectypes are not in the importing database
		if(!mysql_num_rows($identicalMatches)) {
			$approxMatchesRes = mysql_query("select rty_Name, rty_Description from " . DATABASE . ".defRecTypes where (rty_Name like '%$nameInTempDB%')"); // TODO: if rectype is more than one word, check for both words
			$numberOfApproxMatches = mysql_num_rows($approxMatchesRes);
			// Add all approximate matches to a javascript array
			if($numberOfApproxMatches > 0) {
				while($approxRectype = mysql_fetch_assoc($approxMatchesRes)) {
					$approxRty_Name = mysql_escape_string($approxRectype["rty_Name"]);
					$approxRty_Description = mysql_escape_string($approxRectype["rty_Description"]);
					if (!$approxMatches[$rectype["rty_ID"]]){
						$approxMatches[$rectype["rty_ID"]] = array($approxRty_Name,$approxRty_Description);
					}else{
						array_push($approxMatches[$rectype["rty_ID"]],array($approxRty_Name,$approxRty_Description));
					}
				}
			}
			// Add recordtypes to the table
			array_push($tableRows,array('arrow'=>"<img id=\"arrow".$rectype["rty_ID"]."\" src=\"../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png\" />",
										'rtyID'=>$rectype["rty_ID"],
										'rectype'=>$rectype["rty_Name"],
										'matches'=>$numberOfApproxMatches,
										'import'=>"<a href=\"#import\"><img id=\"importIcon".$rectype["rty_ID"]."\" src=\"../../common/images/download.png\" width=\"16\" height=\"16\" /></a>"));
		}
	}

	echo "approxRectypes = ".json_format($approxMatches,true). "\n";
	echo "var tableData = ".json_format($tableRows,true). "\n\n";
?>
	var l = tableData.length,
		i;
	for (i=0; i<l; i++) {
		myDataTable.addRow(tableData[i]);
	}
<?php
	mysql_query("use ".$tempDBName);
	$rtyRes = mysql_query("select rty_ID,
									rst_ID,
									rst_DetailTypeID,
									rst_DisplayName,
									dty_ID,
									dty_Name,
									dty_Type,
									dty_Status,
									if(dty_OriginatingDBID and dty_IDInOriginatingDB,dty_IDInOriginatingDB,dty_ID) as origDtyID,
									if(dty_OriginatingDBID,dty_OriginatingDBID,$source_db_id) as origDtyDBID,
									rty_Description
							from defRecTypes
								left join defRecStructure on rty_ID = rst_RecTypeID
								left join defDetailTypes on rst_DetailTypeID = dty_ID
							order by rty_ID");
	// For every recordtype, add the structure to a javascript array, to show in a foldout panel
	$rectypeStructures = array();
	if(isset($rtyRes)) {
		while($rtStruct = mysql_fetch_assoc($rtyRes)) {
			// check to see if the source rectype field's detailType exist in our DB
			$dtyExistRes = mysql_query("select dty_ID from " . DATABASE . ".defDetailTypes ".
										"where dty_OriginatingDBID = ".$rtStruct['origDtyDBID'].
										" AND dty_IDInOriginatingDB = ".$rtStruct['origDtyID']);
			$dtyAlreadyImported = mysql_num_rows($dtyExistRes);

			$rtsShortRow = array($rtStruct["rst_DisplayName"],						//[0]
								mysql_escape_string($rtStruct["dty_Name"]),			//[1]
								$rtStruct["dty_Type"],								//[2]
								$rtStruct["dty_Status"],							//[3]
								mysql_escape_string($rtStruct["rty_Description"]),	//[4]
								$dtyAlreadyImported ? 1: 0);						//[5]

			if (!$rectypeStructures[$rtStruct["rty_ID"]]){
				$rectypeStructures[$rtStruct["rty_ID"]] = array($rtsShortRow);
			}else{
				array_push($rectypeStructures[$rtStruct["rty_ID"]],$rtsShortRow);
			}
		}
	}
	echo "rectypeStructures = ".json_format($rectypeStructures,true). "\n";
	?>
}
<?php
echo 'var tempDBName = "'.$tempDBName.'";'. "\n";
echo 'var sourceDBName = "'.$source_db_name.'";'. "\n";
echo 'var URLBase = "'.HEURIST_URL_BASE.'";'. "\n";
echo 'var importTargetDBName = "'.HEURIST_DBNAME.'";'. "\n";
echo 'var importTargetDBFullName = "'.DATABASE.'";'. "\n";
?>
var myDataTable;
var hideTimer;
var needHideTip;
YAHOO.util.Event.addListener(window, "load", function() {
	YAHOO.example.Basic = function() {
		// Create the columns. Arrow contains the collapse/expand arrow, rtyID is hidden and contains the ID, rectype contains the name, matches the amount of matches and a tooltip, import a button
		var myColumnDefs = [
			{ key:"arrow", label:"click for details ...", formatter:YAHOO.widget.RowExpansionDataTable.formatRowExpansion },
			{ key:"import", label:"Import", sortable:false, resizeable:false, width:30 },
			{ key:"rtyID", label:"<u>ID</u>", sortable:true, hidden:true },
			{ key:"rectype", label:"<span title='Click on row to view information about the record type'><u>Record type</u></span>", sortable:true, resizeable:true, width:150 },
			{ key:"matches", label:"<span title='Shows the number of record types in the current database with simliar names'><u>Potential dupes in this DB</u></span>", sortable:true, resizeable:true, parser:'number', width:50 }
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
					var info = "<i>" + rectypeStructures[rty_ID][0][4] + "</i><br />";
					info += '<table><tr><th><b>Field name</b></th><th><b>Field type</b></th><th><b>Data type</b></th><th class=\"status\"><b>Status</b></th></tr>';

					// 0 = rst_DisplayName
					// 1 = dty_Name
					// 2 = dty_Type
					// 3 = dty_Status
					// 4 = dty_Description
					// 5 = dty already imported

					for(i = 0; i < rectypeStructures[rty_ID].length; i++) {
						if (rectypeStructures[rty_ID][i][3] == "reserved") {
							dtyStatus = "<img src=\"../../common/images/lock_bw.png\">";
						}else{
							dtyStatus = rectypeStructures[rty_ID][i][3];
						};
						info += "<tr"+ (rectypeStructures[rty_ID][i][5] == 1? ' style="background-color:#CCCCCC;"' : "") +
								"><td>" + (rectypeStructures[rty_ID][i][5] == 1? "(imported) " : "") + rectypeStructures[rty_ID][i][0] +
								"</td><td>" + rectypeStructures[rty_ID][i][1] +
								"</td><td>" + rectypeStructures[rty_ID][i][2] +
								"</td><td class=\"status\">" + dtyStatus + "</td></tr>";
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
		top.HEURIST.util.showPopupDivAt(my_tooltip, xy, $(window).scrollTop(), $(window).width(), $(window).height(),0);
		hideTimer = window.setTimeout(_hideToolTip, 2000);
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
<body class="popup yui-skin-sam" onbeforeunload="dropTempDB(false)">
<div id=popup-saved style="display: none">
	<b>Import succesful</b>
</div>

<div class="banner"><h2>Import record types from <?= "\"".($source_db_id ?$source_db_id." : " : "").$source_db_name."\""?> </h2></div>
<script src="../../common/js/utilsLoad.js"></script>
<script src="../../common/js/utilsUI.js"></script>
<div id="page-inner" style="overflow:auto">

<!--<button id="finish1" onClick="dropTempDB(true)" class="button">Back to databases</button>
--><div id="crosswalk" style="width:100%;margin:auto;">
	<div id="topPagination"></div>
	<div id="crosswalkTable"></div>
	<div id="bottomPagination"></div>
</div>
<i>Note: If this function reports 'No records found' this normally means that there are no
definitions in the selected database which are not already in the current database.<p>
In version 3.0 this may also mean that the database is in a different format version which is not being read correctly
</i>
<!-- TODO: need a check on format version and report if there is a difference in format version -->

<br>&nbsp;<br>
<button id="finish2" onClick="dropTempDB(true)" class="button">Back to databases</button>
<div class="tooltip" id="toolTip"><p>tooltip</p></div>

<a id="shortLog" onClick="showShortLog()" href="#">Show short log</a><br />
<a id="detailedLog" onClick="showDetailedLog()" href="#">Show detailed log</a><br /><br />
<div id="log"></div><br />
<div id="log"></div>

<script type="text/javascript">

var detailedImportLog = "";
var shortImportLog = "";
var result = "";
var importedRowID;
var importPending = false;
// Start an asynchronous call, sending the recordtypeID and action
function processAction(rtyID, action, rectypeName) {
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
		// executed on change of ready state:
		// 0=not init, 1=server connection, 2=request received, 3=processing request, 4= done
		if (xmlhttp.readyState==4 && xmlhttp.status==200) { // done and OK
			importPending = false;
			var response = xmlhttp.responseText;
			// Handle the response, and give feedback
			if(response.substring(0,6) == "prompt") {
				changeDuplicateEntryName(rtyID, rectypeName);
			} else if(response.substring(0,5) == "Error") {

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
				document.getElementById("popup-saved").innerHTML = "<b>Import error</b>";
			} else {
				document.getElementById("importIcon"+rtyID).src = "../../common/images/import_icon.png";
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
	} // end readystate callback

	xmlhttp.open("GET","processAction.php?"+
						"action="+action+
						"&tempDBName="+tempDBName+
						"&sourceDBName="+sourceDBName+
						"&sourceDBID="+ (sourceDBID ? sourceDBID : "0")+
						"&importRtyID="+rtyID+
//						"&crwDefType="+crwDefType+
//						"&crwLocalCode="+crwLocalCode+
						"&replaceRecTypeName="+replaceRecTypeName+
						"&importingTargetDBName="+importTargetDBFullName,
						true);
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
function changeDuplicateEntryName(rtyID,rectypeName) {
	var newRecTypeName = rectypeName + 1;
	if (rectypeName) {
		var match = rectypeName.match(/(.*[\D])(\d+)$/);
		if (match && match[2]){
			newRecTypeName = match[1] + (parseInt(match[2]) + 1);
		}
	}
	newRecTypeName = prompt("Duplicate record type name\n\nPlease enter a new name for this record type",newRecTypeName);
	if(newRecTypeName != replaceRecTypeName) {
		replaceRecTypeName = newRecTypeName;
		window.setTimeout(function() { processAction(rtyID, "import", newRecTypeName);},0);
	} else {
//		dropTempDB(true);
	}
}

// Drop the temp DB when the page is closed, or 'Finished' is clicked
var dropped = false;
function dropTempDB(redirect) {
	if(!dropped) {
		dropped = true;
		processAction(0, "drop");
	}
	if(redirect) {
		window.location = "selectDBForImport.php";
	} else {
		if(result == "") {
			// Annoying alert, you already know this: alert("Nothing was imported");
		} else {
			// potentially useful but very poorly formatted: alert(result);
		}
	}
}
</script>
</div>
</body>
</head>