<?php

    /**
    * manageGroups.php: Create workgroups, add users, change roles
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    /*
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    if (!is_admin()) {
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap>".
    "<div id=errorMsg><span>You must be logged in as system administrator to add or change Workgroups</span>".
    "<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
    " target='_top'>Log out</a></p></div></div></body></html>";
    return;
    }
    */

    $isPopup = (array_key_exists('popup', $_REQUEST) && $_REQUEST['popup']=="yes");
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Manage Workgroups</title>

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

        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <style>
            <?php if ($isPopup) { ?>
                #page-inner {top:0 !important}
                <?php } ?>
        </style>
    </head>

    <body width="650" height="450" class="popup yui-skin-sam" style="overflow:auto;">
        <div>
            <?php if (!$isPopup) { ?>
                <div id="titleBanner" class="banner"><h2>Manage Workgroups</h2></div>
                <?php } ?>
            <script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
            <script type="text/javascript" src="../../common/js/utilsUI.js"></script>
            <script type="text/javascript" src="../../common/js/hintDiv.js"></script>
            <script src="../../common/php/displayPreferences.php"></script>

            <!-- access to functions about current user -->
            <script src="loadUserInfoShort.php"></script>
            <script type="text/javascript" src="manageGroups.js"></script>

            <div id="page-inner">
                <p>To view users in a group or assign users to a group, click on the edit icon in the Edit Membership column</p>

                <div id="currUserInfo"></div>
                <div id="toolbar" style="height:22px;">

                    <div id="divFilterByMembership" style="display: inline-block;">
                        Show:&nbsp;&nbsp;&nbsp;
                        <input type="radio" id="inputFilterByMembership1" name="inputFilterByMembership"
                            value="all" style="display: inline-block;">
                        <label id="lblForInputFilterByMembership1" style="display: inline-block;">&nbsp;All groups&nbsp;</label>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="radio" id="inputFilterByMembership2" name="inputFilterByMembership"
                            value="any" checked="checked">&nbsp;My groups&nbsp;
                        <input type="radio" id="inputFilterByMembership3" name="inputFilterByMembership" value="admin">&nbsp;Admin. only&nbsp;
                        <input type="radio" id="inputFilterByMembership4" name="inputFilterByMembership" value="member">&nbsp;Member only&nbsp;&nbsp;
                    </div>
                    <div style="display: inline-block;padding-left:40px">
                        <label>Filter by name:</label>
                        <input type="text" id="inputFilterByName" name="inputFilterByName" style="width:140px;padding-left: 20px;" value=""/>
                    </div>
                </div>

                <!-- edit contols -->
                <div id="tb_top" style="height:30">
                    <div style="display:inline-block; max-width:150;"><div id="dt_pagination_top"></div></div>
                    <div style="float:right; text-align:right;padding-top:5px;">
                        <input type="button" tabindex="11" id="btnAdd1" value="Add New Group" onClick="groupManager.editGroup(-1);" />
                    </div>
                </div>

                <div id="tabContainer">
                    <script  type="text/javascript">
                        //  starts initialization on load completion of this window
                        function createManagerObj(){
                            groupManager = new  GroupManager(false, false, true); //nonfilter, no selection, in window
                        }
                        YAHOO.util.Event.addListener(window, "load", createManagerObj);
                    </script>
                </div>

                <!-- edit contols -->
                <div id="tb_top" style="height:30">
                    <div style="display:inline-block; max-width:150;"><div id="dt_pagination_bottom"></div></div>
                    <div style="float:right; text-align:right;padding-top:5px;">
                        <input type="button" tabindex="11" id="btnAdd2" value="Add New Group" onClick="groupManager.editGroup(-1);" />
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
