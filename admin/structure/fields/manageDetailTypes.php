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
* manageDetailTypes.php
* display tabbed view of fields (detail types) and link to field type addition and editor  
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to modify database structure")){
        return;
    }
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Manage Field Types</title>

		<!-- YUI -->
		<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
		<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/tabview/tabview-min.js"></script>
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/dragdrop/dragdrop-min.js"></script>
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/json/json-min.js"></script>

		<!-- DATATABLE DEFS -->
		<link type="text/css" rel="stylesheet" href="../../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
		<!-- datatable Dependencies -->
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
		<!-- OPTIONAL: Drag Drop (enables resizeable or reorderable columns) -->
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/dragdrop/dragdrop-min.js"></script>
		<!-- Source files -->
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
		<!-- END DATATABLE DEFS-->

		<!-- PAGINATOR -->
		<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
		<!-- END PAGINATOR -->

		<script type="text/javascript" src="../../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
		<!-- <script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script> -->


        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
    	<link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
		<style>
.yui-dt-highlighted{
	cursor:auto !important;
}
		</style>
	</head>

	<body class="popup yui-skin-sam">

		<script src="../../../common/js/utilsLoad.js"></script>
		<script src="../../../common/php/displayPreferences.php"></script>
		<script src="../../../common/php/loadCommonInfo.php"></script>
		<script type="text/javascript" src="../../../common/js/hintDiv.js"></script>
        <script type="text/javascript" src="../../../common/js/tabDragDrop.js"></script>
		<script src="manageDetailTypes.js"></script>


	<div id="delete-message" style="display:none;">
		<div id="delete-message-text" style="position:absolute;left:0;right:0;top:26;bottom:0;padding:15px;overflow:auto;"></div>
	</div>

<?php
if(@$_REQUEST['popup']!=1){
?>    
	<div class="banner"><h2>Manage Field Types</h2></div>
<?php
}
?>    
	<div id="page-inner">

<!--   height:auto; max-height:500px;
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