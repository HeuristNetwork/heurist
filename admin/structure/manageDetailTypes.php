<?php

/**
* manageDetailTypes.php - display tabbed view of fields (detail types) and link to field type addition and editor
* 2011-03-14
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
if (!is_admin()) {
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to modify database structure</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
    return;
}
?>

<html>
	<head>


		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Heurist - Field Types</title>

		<link rel=stylesheet href="../../common/css/global.css">

		<!-- YUI -->
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/element/element-min.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/tabview/tabview-min.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/dragdrop/dragdrop-min.js"></script>
		<!--script type="text/javascript" src="../../external/yui/2.8.2r1/build/history/history-min.js"></script!-->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/json/json-min.js"></script>

		<!-- DATATABLE DEFS -->
		<link type="text/css" rel="stylesheet" href="../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
		<!-- datatable Dependencies -->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
		<!-- OPTIONAL: Drag Drop (enables resizeable or reorderable columns) -->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/dragdrop/dragdrop-min.js"></script>
		<!-- Source files -->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
		<!-- END DATATABLE DEFS-->

		<!-- PAGINATOR -->
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
		<!-- END PAGINATOR -->

		<!-- OVERLAYS
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/button/button-min.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/container/container-min.js"></script>
		END OVERLAYS -->

		<script type="text/javascript" src="../../external/jquery/jquery.js"></script>
		<!-- <script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script> -->

		
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/edit.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">

	</head>

	<body class="popup yui-skin-sam">

		<script src="../../common/js/utilsLoad.js"></script>
		<script src="../../common/php/displayPreferences.php"></script>
		<script src="../../common/php/loadCommonInfo.php"></script>
		<script type="text/javascript" src="../../common/js/hintDiv.js"></script>
		<script src="tabDragDrop.js"></script>
		<script src="manageDetailTypes.js"></script>



	<div>
	<div class="banner"><h2>Field Types</h2></div>
	<div id="page-inner">

<!--
				<div style="float: right; padding-top: 15px"><label id="lblNoticeAboutChanges"
						style="padding-left:3px; padding-right:3px; background-color:white; color:red; display: inline-block;"></label>
						&nbsp;&nbsp;&nbsp;
						<input id="btnSave" type="button" value="Save Changes" style="padding:2px; color:red; float: right; display: none;"/></div>
-->

			<!--  style="position:absolute; z-index:0;"
			Static markup required by the browser history utility. Note that the
			iframe is only used on Internet Explorer. If this page is server
			generated (by a PHP script for example), it is a good idea to create
			the IFrame ONLY for Internet Explorer (use server side user agent sniffing) -->

			<input id="yui-history-field" type="hidden">

			<div id="modelTabs" class="yui-navset yui-navset-top">

				<script  type="text/javascript">
					detailTypeManager = new DetailTypeManager();
					YAHOO.util.Event.addListener(window, "load", detailTypeManager.init());

					// prevents exit if there are changes
	//
					window.onbeforeunload = function () {
						var changed = detailTypeManager.hasChanges();
		if (changed) return "You have made changes.  If you continue, all changes will be lost.";
					}
				</script>
			</div>
			</div>
		</div>
	</body>
</html>