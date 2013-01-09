<?php

/**
* manageMimetypes.php
* mimetypes listing
*
* @version 2012.1217
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if (! is_logged_in()) {
	header("Location: " . HEURIST_BASE_URL . "common/connect/login.php?db=".HEURIST_DBNAME);
	return;
}
if (!is_admin()) {
	print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be an adminstrator to edit the mime types</span><p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
	return;
}
?>
<html>
	<head>

		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Manage Mime types</title>

		<link rel=stylesheet href="../../common/css/global.css">

		<!-- YUI -->
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/element/element-min.js"></script>
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

		<script type="text/javascript" src="../../external/jquery/jquery.js"></script>
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">

	</head>

	<body class="popup yui-skin-sam" style="overflow:auto;">
    <div>
    	<div id="titleBanner" class="banner"><h2>Manage Mime types</h2></div>
		<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>

		<script type="text/javascript" src="manageMimetypes.js"></script>

	<div id="page-inner">

			<div id="toolbar" style="height:22px;">

				<div style="display: inline-block;padding-left:40px;">
					<label>Filter by extension:</label>
					<input type="text" id="inputFilterByName" name="inputFilterByName" style="width:140px;" value=""/>
				</div>
			</div>

			<!-- edit contols -->
			<div id="tb_top" style="height:30">
				<div style="display:inline-block; max-width:150;"><div id="dt_pagination_top"></div></div>
				<div style="float:right; text-align:right;padding-top:5px;">
					<input type="button" tabindex="11" id="btnAdd1" value="Add New Mime type" onClick="mimetypeManager.editMimetype(-1);" />
				</div>
			</div>

			<div id="tabContainer">

				<script  type="text/javascript">

				//  starts initialization on load completion of this window
				function createManagerObj(){
					mimetypeManager = new  MimetypeManager();
				}
				YAHOO.util.Event.addListener(window, "load", createManagerObj);

				</script>
			</div>


			<!-- edit contols -->
			<div id="tb_top" style="height:30">
				<div style="display:inline-block; max-width:150;"><div id="dt_pagination_bottom"></div></div>
				<div style="float:right; text-align:right;padding-top:5px;">
					<input type="button" tabindex="11" id="btnAdd2" value="Add New Mime type" onClick="mimetypeManager.editMimetype(-1);" />
				</div>
			</div>
		</div></div>

	</body>
</html>
