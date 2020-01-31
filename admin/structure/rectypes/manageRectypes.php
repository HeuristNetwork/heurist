<?php

/*
* Copyright (C) 2005-2019 University of Sydney
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
* add/edit record types
* 
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Juan Adriaanse
* @copyright   (C) 2005-2019 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

define('MANAGER_REQUIRED',1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__)."/../../../hclient/framecontent/initPage.php");
?>
<title>Modify Schema / Structure</title>
<!-- script type="text/javascript" src="../../../hclient/widgets/dropdownmenus/mainMenu.js"></script -->

<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />

<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/tabview/tabview-min.js"></script>
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/dragdrop/dragdrop-min.js"></script>
<!--script type="text/javascript" src="../../external/yui/2.8.2r1/build/history/history-min.js"></script!-->
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/json/json-min.js"></script>

<!-- DATATABLE DEFS -->
<link type="text/css" rel="stylesheet" href="../../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
<!-- datatable Dependencies -->
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
<!-- Source files -->
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
<!-- END DATATABLE DEFS-->
<!-- PAGINATOR -->
<link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
<script type="text/javascript" src="../../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
<!-- END PAGINATOR -->

<link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
<link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
<link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">

<script type="text/javascript" src="../../../admin/structure/import/importStructure.js"></script>

<style>
.yui-skin-sam .yui-dt td {
    margin: 0;
    padding: 0;
    border: none;
    text-align: left;
}
.yui-skin-sam .yui-dt tr.separator, .yui-skin-sam .yui-dt tr.separator td.yui-dt-asc, .yui-skin-sam .yui-dt tr.separator td.yui-dt-desc, .yui-skin-sam .yui-dt tr.separator td.yui-dt-asc, .yui-skin-sam .yui-dt tr.separator td.yui-dt-desc {
    font-style: italic;
    font-weight: bold;
    font-size: 1.4em;
}
.yui-nav .selected a{
    background: #edf5ff !important;
    color: blue !important;
    font-weight:bold !important;
    border: 1.5px solid blue !important;
    border-bottom: 1px solid #edf5ff !important;
}
.yui-skin-sam .yui-navset .yui-nav{
    border: none !important;
}
.banner > h2{
    text-transform: uppercase;
    padding: 10px 16px;   
}

.ent_wrapper{position:absolute;top:0;bottom:0;left:0;right: 1px;overflow:hidden;}
.ent_header, .ent_footer, .ent_content{position:absolute; left:0; right:1px;}
.ent_header{top:0;height:9.2em;padding:30px 10px;min-width:999px;}
.ent_content{top:13.8em;bottom:6.8em; overflow-y:auto;padding:0px 10px}
.ent_footer{bottom:0px;height:5.6em;padding:20px}
/* https://codepen.io/sosuke/pen/Pjoqqp*/
.blue-img {
    filter: invert(62%) sepia(71%) saturate(1988%) hue-rotate(178deg) brightness(108%) contrast(104%)
}
</style>

        <script type="text/javascript" src="manageRectypes.js"></script>
        
        <script type="text/javascript">
            
            function onPageInit(success){
                
                if(!success) return;
                
                window.rectypeManager = new RectypeManager();
                window.rectypeManager.init()

                window.onbeforeunload = function () {
                    var changed = rectypeManager.hasChanges();
                    if (changed) return "You have made changes.  If you continue, all changes will be lost.";
                }
            }
        </script>
        

</head>
<body class="popup yui-skin-sam">

<script type="text/javascript" src="../../../hclient/core/hintDiv.js"></script>
<script type="text/javascript" src="../tabDragDrop.js"></script>

<div class="ent_wrapper">
    <div class="ent_header" style="text-align:center">
        <input id="yui-history-field" type="hidden">
        
        <div style="float:left">
        <img src="../../../hclient/assets/fi_add.png" height="64" style="margin-left:7em; padding: 0 120px;" class="blue-img"/>
        <input type="button" id="btnAddRecordType" value="Add new record type" class="add" style="margin-right:1em; float:none"/>
        <input type="button" id="btnImportFromDb" value="Get from templates" class="import" style="float:none"/>
        </div>

        <img src="../../../hclient/assets/fi_network.png" height="64" style="padding:0 20px;" class="blue-img"/>
        <input type="button" id="btnVisualize" value="Visualize"/>
    
        <div  style="margin-right:5em; float:right;min-width:380px">
            <img src="../../../hclient/assets/fi_settings.png" height="64" style="padding-right: 20px;float: left;" class="blue-img"/>
            <div>
            <input type="button" id="btnTerms" value="Vocabularies and terms" style="margin-right:1em; margin-top:10px;"/>
            <input type="button" id="btnFields" value="Base fields" style="margin-right:1em; margin-top:10px;"/>
            </div>
            <div>
            <input type="button" id="btnReltypes" value="Relation types" style="margin-right:1em; margin-top:10px;"/>
            <input type="button" id="btnMimetypes" value="Define mime types" style="margin-right:1em; margin-top:10px;"/>
            </div>
        </div>
        <div style="position:absolute;bottom:5px;left:10px"><h1 style="font-weight: 900;">Entity (record) types</h1></div>
    </div>
    <div class="ent_content">
        <div id="modelTabs" class="yui-navset yui-navset-top"></div>
    </div>
    <div class="ent_footer">
        <h4 style="line-height:3ex;">Use this function to build and extend your database by adding and modifying record (entity) types. 
        <br/>Allows the re-use of existing fields for consistency across entity types, as well as the creation of entirely new fields. 
        <br/>New databases are pre-populated with a range of useful record types and term (category) vocabularies.</h4>
    </div>
</div>

<div id="formGroupEditor2" style="display:none">
    <div class="input-row required">
        <div class="input-header-cell" style="display:inline-block;text-align:right;width:100px">Name: </div>
        <div class="input-cell" style="display: inline-block"><input id="edGroupName" style="width:150px"/></div>
    </div>
    <br>
    <div class="input-row required">
        <div class="input-header-cell"  style="display:inline-block;vertical-align: top;text-align:right;width:100px;">Description: </div>
        <div class="input-cell" style="display: inline-block"><textarea id="edDescription" style="width:500px" rows="2"></textarea></div>
    </div>
</div>

</body>
</html>
