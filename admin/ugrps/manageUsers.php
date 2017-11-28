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
* brief description of file
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



/**
* manageUsers.php
* users listing
*
* @version 2011.0510
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristNetwork.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if(!array_key_exists('grpID', $_REQUEST)){
    if(isForAdminOnly("to add or change users")){
        return;
    }
}

$isPopup = (array_key_exists('popup', $_REQUEST) && $_REQUEST['popup']=="yes");
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Manage Users</title>

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

		<!-- PAGINATOR -->
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
		<!-- END PAGINATOR -->

		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/container/container-min.js"></script>

		<script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
		<style type="text/css">
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
<?php if($isPopup){ ?>
			#page-inner {top:5 !important}
<?php } ?>
		</style>

	</head>

	<body class="popup yui-skin-sam" style="overflow:auto;">
        <div>
            <?php if(!$isPopup){ ?>
    	        <div class="banner" id="titleBanner"><h2>Manage Users</h2></div>
            <?php } ?>
            
		    <script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		    <script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		    <script src="../../common/php/displayPreferences.php"></script>

		    <!-- access to functions about current user -->
		    <script src="loadUserInfoShort.php"></script>
		    <script type="text/javascript" src="manageUsers.js"></script>

		    <div class="tooltip" id="toolTip2"><p>popup popup</p></div>

	        <div id="page-inner">
			    <div id="currUserInfo"></div>

			    <!-- <h2 id="lblGroupTitleSelection" class="selection"></h2> -->

			    <div  style="float: left;">
				    <div id="pnlFilterByGroup">
                            <label style="width:120px;display:inline-block;text-align:right" for="inputFilterByGroup">Filter by group:</label>
                                <select id="inputFilterByGroup" size="1" style="width:138px">
                                    <option value="all">all groups</option>
                                </select>
						    <!-- Too many bells and whistles, really confusing, will never have more than a few groups
                            <label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Filter pulldown:</label>
						    <input type="text" id="inputFilterGroup"  style="width:40px;" value=""/> (3+ characters)
                            -->
				    </div>

				    <!--
				    <div id="pnlGroupTitle" style="display:none;">
					    <h2 id="lblGroupTitle"></h2>
				    </div>
				    -->

                    <div id="pnlFilterByRole" style="display:none;">
					    <br>
                        <label for="inputFilterByRole" style="width:120px;display:inline-block;text-align:right">Filter by role:</label>
					    <select id="inputFilterByRole" name="inputFilterByRole" size="1" style="width:75px">
						    <option value="all">all roles</option>
						    <option value="admin">admin</option>
						    <option value="member">member</option>
						    <!-- <option value="invited">invited</option>
						    <option value="request">request</option> -->
                	    </select>
				    </div>
			    </div>

			    <div id="dbOwnerInfo" style="display:none;float: right; width:40%">
                    <i>User #2 - the first in this group - is the OWNER of this database (the only user who can register the database with the Heurist master index, who receives email to the owner and is permitted certain destructive actions)</i>
			    </div>

                <p></p>

			    <div id="toolbar2" style="clear: both;text-align: left;">

				    <label style="width:120px;display:inline-block;text-align:right">Filter by name:</label>
					    <input type="text" id="inputFilterByName" style="width:140px;" value=""/>

				    <div id="divFilterByEnable" class="listing">
					    <label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
                        <input type="radio" id="inputFilterByEnable1" name="inputFilterByDisbale"
                                value="all" checked="checked"/>&nbsp;&nbsp;&nbsp;All&nbsp;&nbsp;&nbsp;
                        <input type="radio" id="inputFilterByEnable2" name="inputFilterByDisbale"
                                value="disbledonly"/> &nbsp;&nbsp;Inactive
				    </div>

				    <div id="divFilterBySelection" class="selection">
					    <label>Show:</label>
                        <input type="radio" id="inputFilterBySelection1"
                                name="inputFilterBySelection" value="all" checked="checked"/>&nbsp;All&nbsp;
                        <input type="radio" id="inputFilterBySelection2"
                                name="inputFilterBySelection" value="selonly"/> Selected

                    <!--&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" id="btnClearSelection" value="clear" title="clear selection"/> -->
				    </div>

			    </div>

			    <div id="tb_top" style="height:30">
				    <div style="display:inline-block; max-width:150;"><div id="dt_pagination_top"></div></div>
				    <!-- selection controls -->
				    <div id="pnlCtrlSel1"  class="selection" style="float:right; text-align:right;padding-top:5px;">
					    <label id="lblSelect1"></label>
					    <input type="button" tabindex="12" value="Cancel" onClick="userManager.cancel();" />
					    <input type="button" tabindex="11" id="btnApply1" value="Add Users to Group" onClick="userManager.returnSelection();" />
				    </div>
				    <!-- edit contols -->
				    <div class="listing" style="float:right; text-align:right;padding-top:5px;">
					    <?php if(array_key_exists('grpID', $_REQUEST) && $isPopup){?>
						    <input type="button" tabindex="13" id="btnBack1" value="Back to Groups" onClick="{backToGroup()};" />
					    <?php } ?>
					    <div id="pnlCtrlEdit1" style="float:right; text-align:right;padding-right:2px;padding-left:2px">
						    <div id="pnlAdd1" style="display: inline-block;"><input type="button" tabindex="12" id="btnAdd1" value="Create New User" onClick="userManager.editUser(-1);" /></div>
						    <div id="btnSelectAdd1"><input type="button" tabindex="11" value="Find and Add User"
							    title="Find and add user to this group" onClick="userManager.findAndAddUser();" /></div>
					    </div>
				    </div>
			    </div>

			    <div id="tabContainer">
				    <script  type="text/javascript">

				    function backToGroup(){
					    window.location.href = top.HEURIST.baseURL + "admin/ugrps/manageGroups.php?db=<?=$_REQUEST['db']?>&popup=<?=@$_REQUEST['popup']?>";
					    //window.history.go(-1);
				    }

				    //  starts initialization on load completion of this window
				    function createManagerObj(){
					    userManager = new  UserManager(false, false, true); //nonfilter, no selection, in window
				    }
				    YAHOO.util.Event.addListener(window, "load", createManagerObj);

				    </script>
			    </div>


			    <div id="tb_top" style="height:30">
				    <div style="display:inline-block; max-width:150;"><div id="dt_pagination_bottom"></div></div>

				    <!-- selection controls -->
				    <div id="pnlCtrlSel1"  class="selection" style="float:right; text-align:right;padding-top:5px;">
					    <label id="lblSelect1"></label>
					    <input type="button" tabindex="12" value="Cancel" onClick="userManager.cancel();" />
					    <input type="button" tabindex="11" id="btnApply2" value="Add Users to Group" onClick="userManager.returnSelection();" />
				    </div>
				    <!-- edit contols -->
				    <div class="listing" style="float:right; text-align:right;padding-top:5px;">
					    <?php if(array_key_exists('grpID', $_REQUEST) && $isPopup){?>
						    <input type="button" tabindex="13" id="btnBack2" value="Back to Groups" onClick="{backToGroup()};" />
					    <?php } ?>
					    <div id="pnlCtrlEdit2" style="float:right; text-align:right;padding-right:2px">
						    <div id="pnlAdd2" style="display: inline-block;"><input type="button" tabindex="12" id="btnAdd2" value="Create New User" onClick="userManager.editUser(-1);" /></div>
						    <div id="btnSelectAdd2"><input type="button" tabindex="11" value="Find and Add User"
							    title="Find and add user to this group" onClick="userManager.findAndAddUser();" /></div>
					    </div>
				    </div>
			    </div>
            </div>
	    </div>
	</body>
</html>
