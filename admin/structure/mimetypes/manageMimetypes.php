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
* manageMimetypes.php
* mimetypes listing
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


require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to modify database structure")){
        return;
    }

?>
<html>
	<head>

		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Manage Mime types</title>

		<link rel=stylesheet href="../../../common/css/global.css">

		<!-- YUI -->
		<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
		<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
		<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
		<!--script type="text/javascript" src="../../external/yui/2.8.2r1/build/history/history-min.js"></script!-->
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
    	<link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">

	</head>

	<body class="popup yui-skin-sam" style="overflow:auto;">
        <div>
    	    <div id="titleBanner" class="banner"><h2>Manage Mime types</h2></div>
		    <script type="text/javascript" src="../../../common/js/utilsLoad.js"></script>
		    <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>

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
	        </div>
        </div>
	</body>
</html>
