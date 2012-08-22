<?php

/**
 * editRectypeConstraints.php
 *
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

// User must be system administrator or admin of the owners group for this database
if (!is_admin()) {
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to modify database structure</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
	return;
}
?>
<html>
	<head>

		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Manage Record type Constraints</title>

		<link rel=stylesheet href="../../common/css/global.css">

		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/container/assets/skins/sam/container.css">

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

		<!-- PAGINATOR
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
		 END PAGINATOR -->

		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/container/container-min.js"></script>

		<script type="text/javascript" src="../../external/jquery/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
		<style type="text/css">
			.yui-skin-sam .yui-dt td {
				margin:0;padding:0;
				border:none;
				text-align:left;
			}
			.yui-skin-sam .yui-dt-list td {
				border-right:none; /* disable inner column border in list mode */
			}
			.yui-skin-sam .yui-dt tr.inactive{/* inactive users */
				/*background-color: #EEE;*/
				color:#999 !important;
			}
			.yui-skin-sam .yui-dt tr.inactive *{/* inactive users */
				/*background-color: #EEE;*/
				color:#999 !important;
			}

			.labeldiv{
				display: inline-block;
				width: 60px;
				text-align: right;
			}
			.yui-dt table {
    			width: 800;
			}
			.listing{
			}
			.selection{
			}
			.activated{
				display:inline-block;
			}
			.deactivated{
				display:none;
			}

		</style>

	</head>

	<body class="popup yui-skin-sam" style="overflow:auto;">
    <div>

    	<div class="banner" id="titleBanner"><h2>Manage Record type Constraints</h2></div>

		<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		<script src="../../common/php/displayPreferences.php"></script>

		<script type="text/javascript" src="editRectypeConstraints.js"></script>

	<div id="page-inner">

			<div id="tb_top" style="height:30">

				<!-- new constrain form -->
				<div id="pnlAddConstraint" style="float:right; text-align:right;padding-top:5px;">
					<label>Select record types</label>
					<input type="button" tabindex="12" value="Cancel" onClick="userManager.cancel();" />
					<input type="button" tabindex="11" value="Save" onClick="constraintManager.addConstraint();" />
				</div>
				<!-- add button -->
				<div style="float:right; text-align:right;padding-top:5px;">
						<input type="button" tabindex="12" value="Add Constraint" onClick="" />
				</div>
			</div>

			<div id="tabContainer">

				<script  type="text/javascript">

				//  starts initialization on load completion of this window
				function createManagerObj(){
					constraintManager = new  ConstraintManager();
				}
				YAHOO.util.Event.addListener(window, "load", createManagerObj);

				</script>
			</div>


	</div>
	</div>

	</body>
</html>

