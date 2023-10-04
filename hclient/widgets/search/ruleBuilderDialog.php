<?php

/**
* RuleSet Builder Dialog
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPage.php');
?>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/ruleBuilder.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/ruleBuilderDialog.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilder.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderItem.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderSort.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancytree/jquery.fancytree-all.min.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />


<style>
    .rulebuilder{
        display: block !important;
        padding:4 4 4 0;
        width:99% !important;
        text-align:left !important;
    }
    .rulebuilder>div{
        text-align:left;
        display: table-cell;
        width:200px;
    }
    .rulebuilder>div>select{
        min-width:180px;
        max-width:180px;
    }
    .rulebuilder_hdear>div{
        min-width:180px;
        width:205px;
        font-weight:bold;
        display:table-cell;
    }
    
</style>
</head>
<body style="overflow:hidden">
    <div style="height:100%" class="selectmenu-parent">
        <div style="position:absolute;width:99%;top:0;font-size:0.8em;text-align: left !important;display:table-row" class="rulebuilder_hdear">
            <div style="width:230px;">Starting point (entity type)</div>
            <div>Relationship Field</div>
            <div style="width:195px;">Relationship Type</div>
            <div>Target entity type</div>
            <div>Optional Filter (query) for target type</div>
        </div>

        <div style="position:absolute;width:99%;top:2em;bottom:4em;overflow-y:auto" id="level1">

            <div id="div_add_level" style="padding-top:1em;"><button id="btn_add_level1">Add New Rule</button></div>
        </div>


        <!-- Slide-in help text - displays at start by default, can be closed and reopened -->
        <div id="helper" title="Rules and RuleSets">
            <!-- <div id="helper" class="ui-widget-content ui-corner-all">
            <h3 class="ui-widget-header ui-corner-all">Rules and RuleSets</h3> -->
            <p style="padding-bottom:1em">

                A <b>rule</b> describes the set of pointers and relationships (including reverse pointers)
                to follow from each of the records in the initial selection set (ie. the results of
                a search) in order to add related records to the current result set. A rule can
                comprise several steps out from the initial selection set.
            </p>
            <p style="padding-bottom:1em">
                For instance a rule building on a search that retrieved a set of menus might add a
                number of recipes referenced by the menu which in turn might add a number of ingredients
                referenced by the recipe; however this rule will not add the restaurants which reference
                the menus or the chefs referenced by the recipes as their creator. These would each be
                a separate rule starting from the initial set of menus (Menus << Restaurants and
                Menus >> Recipes >> Creators).
            </p>
            <p style="padding-bottom:1em">
                <b>RuleSets</b> may contain several rules. RuleSets can be saved - they appear in the RuleSets
                section of the navigation panel - and can be applied to any set of records retrieved by a
                search. RuleSets are also saved as part of the saved search when the search is saved
                after applying a RuleSet.
            </p>
            <p style="padding-bottom:1em">
                When a RuleSet is applied, it operates on the initial selection set resulting from the query.
                Each RuleSet applied <b>replaces</b> the results of the previous RuleSet - they are not additive.
                If you need to apply several rules to build the set of records you require, they should all
                be defined in one RuleSet.
            </p>
        </div>

        <div style="position:absolute;width:99%;height:2em;bottom:10px; text-align:right">

            <button id="btn_save">Save RuleSet</button>
            <!-- <button id="btn_apply">Apply Rules</button> -->
            <button id="btn_help">Help</button>
        </div>
    </div>
</body>
</html>
