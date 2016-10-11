<?php

/**
* editTerms.php: add/edit/delete terms. Treeveiew on the left side with tabview to select domain: enum or relation
* form to edit term on the right side
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

if(isForAdminOnly("to modify database structure")){
    return;
}

if(isset($_GET['treetype']))
{
    $value = $_GET['treetype'];
}
?>



<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Manage terms for term list fields and relationship type</title>

        <!-- YUI -->
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/tabview/tabview-min.js"></script>
        <!--script type="text/javascript" src="../../external/yui/2.8.2r1/build/history/history-min.js"></script!-->

        <!-- TREEVIEW DEFS -->
        <!-- Required CSS -->
        <link type="text/css" rel="stylesheet" href="../../../external/yui/2.8.2r1/build/treeview/assets/skins/sam/treeview.css">
        <!-- Optional dependency source file -->
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/animation/animation-min.js"></script>
        <!-- Optional dependency source file to decode contents of yuiConfig markup attribute-->
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/json/json-min.js" ></script>
        <!-- TreeView source file -->
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/treeview/treeview-min.js" ></script>
        <!-- END TREEVIEW DEFS-->

        <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-file-upload/js/jquery.fileupload.js"></script>

        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
        <!--<link rel=stylesheet href="../../common/css/admin.css">-->
        <style type="text/css">
            .dtyField {
                padding-bottom: 3px;
                padding-top: 3px;
                display: inline-block;
            }
            .dtyLabel {
                display: inline-block;
                width: 100px;
                text-align: right;
                padding-right: 3px;
            }
        </style>
    </head>

    <body class="popup yui-skin-sam">

        <script type="text/javascript" src="../../../common/js/utilsLoad.js"></script>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <script src="../../../common/php/loadCommonInfo.php"></script>

        <script type="text/javascript">var treetype = "<?= $value ?>";</script>
        <script type="text/javascript" src="editTerms.js"></script>

        <div id="divBanner" class="banner">
            <h2>Manage terms for term list fields and relationship type</h2>
        </div>
        <div><br/></div>
        <div style="margin-left:10px; padding-top:15px;">
            <input id="btnAddChild" type="button"
                value="Add Vocabulary" onClick="{editTerms.doAddChild(true)}"/>
            <span style="margin-top:5px; margin-left:10px;"> Adds a new root to the tree</span>
        </div>


        <div id="page-inner">
            <div><br/><br/><br/></div>
            <div>&nbsp;&nbsp;&nbsp;&#129155;</div>
            <div><br/></div>
            <div id="pnlLeft" style="height:100%; width:300; max-width:300; float: left; padding-right:5px; overflow: hidden;">

                <!-- Container for tab control component, each tab contains tree view, one for enums, one for relationship types-->
                <div id="tabContainer"
                    class="yui-navset yui-navset-top" style="y:138; height:70%; width:300; max-width:300; float: left; overflow: hidden;">
                </div><br/>

                <!-- Navigation: Search form to do partial match search on terms in the tree -->
                <div id="formSearch" style="display:block;height:136px;">
                    <div class="dtyField"><label class="dtyLabel" style="width:30px;">Find:</label>
                        <input id="edSearch" style="width:70px"  onkeyup="{doSearch(event)}"/>
                        <label>type 3 or more letters</label>
                    </div>
                    <div class="dtyField">
                        <select id="resSearch" size="5" style="width:300px" onclick="{editTerms.doEdit()}"></select>
                    </div>
                </div>

            </div>

            <div id="formContainer" style="position:absolute;left:303px;top:0;bottom:0;right:0; padding-bottom:5px; padding-left: 10px;">
                <h3 id="formMessage" style="margin-left:10px; border-style:none;display:block;text-align:left;width:300px;">Rollover items in the tree to show available actions<br/>Drag terms to reposition, merge or set as inverse of one-another<br/> Select term to edit label and description</h3>
                <h3 id="formMessage" style="margin-left:10px; border-style:none;display:none;text-align:left;width:300px;">
                    Select a term in the tree to edit or add child terms
                </h3>

                <div style="margin-left:10px; padding-top:15px;display:none">
                    <input id="btnAddChild" type="button"
                        value="Add Vocabulary" onClick="{editTerms.doAddChild(true)}"/>
                    <span style="margin-top:5px; margin-left:10px; display:none" > Adds a new root to the tree</span>
                </div>

                <div id="deleteMessage" style="display:none;width:500px;">
                    <h3 style="margin-left:10px; border-style:none;">Deleting term</h3>
                </div>

                <!-- Edit form for modifying characteristics of terms, including insertion of child terms and deletion -->
                <div id="formEditor" style="display:none;width:600px;">
                    <h3 style="margin-left:10px; margin-top:0px; border-style:none;display:inline-block"><br/><br/>Edit selected term / vocabulary</h3>
                    <div id="div_SaveMessage" style="text-align: center; display:none;color:#0000ff;width:140px;">
                        <b>term saved</b>
                    </div>

                    <div style="margin-left:10px; border: black; border-style: solid; border-width:thin; padding:10px;">

                        <div class="dtyField">
                            <label class="dtyLabel">ID:</label>
                            <input id="edId" readonly="readonly" style="width:50px"/>
                            <input id="edParentId" type="hidden"/>
                            &nbsp;&nbsp;&nbsp;
                            <div id="div_ConceptID" style="display: inline-block;">
                            </div>
                        </div>

                        <div id="divStatus" class="dtyField" style="float:right;width:130px;"><label>Status:</label>
                            <select class="dtyValue" id="trm_Status" onChange="editTerms.onChangeStatus(event)">
                                <option selected="selected">open</option>
                                <option>pending</option>
                                <option>approved</option>
                                <!-- put reserved option here for internal use only ? -->
                            </select>
                        </div>


                        <div class="dtyField">
                            <div style="float:left;">
                                <label class="dtyLabel" style="color: red; margin-top:10px;">
                                    Term (label)</label>
                                <input id="edName" style="width:350px" onkeyup="editTerms.isChanged();" />
                                <div style="padding-left:105;padding-top:3px; font-size:smaller;">
                                    The term or label describing the category. The label is the normal<br/>
                                    way of expressing the term. Dropdowns are ordered alphabetically.<br />
                                    Precede terms with 01, 02, 03 ... to control order if required.
                                </div>
                            </div>

                        </div>

                        <div class="dtyField">
                            <label class="dtyLabel" style="vertical-align: top;">Description of term</label>
                            <textarea id="edDescription" rows="3"  style="width:350px; margin-top:5px;" title=""
                                onkeyup="editTerms.isChanged();"></textarea>
                            <div style="padding-left:105;padding-top:3px; font-size:smaller;">
                                A concise but comprehensive description of this term or category.
                            </div>
                        </div>

                        <div class="dtyField">
                            <label class="dtyLabel">Standard code</label>
                            <input id="edCode" style="width:80px; margin-top:5px;" onkeyup="editTerms.isChanged();"/>
                            <div style="padding-left:105;padding-top:3px;  font-size:smaller;">
                                A domain or international standard code for this term or category.<br/>
                                May also be used for a local code value to be used in importing data.
                            </div>
                        </div>


                        <!--
                        Fields for relationship type terms only
                        -->
                        <div id="divInverseType" style="margin-top:20px; margin-left:115px; margin-bottom:5px;">
                            <input id="cbInverseTermItself" type="radio" name="rbInverseType"/>
                            <label for="cbInverseTermItself">Term is non-directional</label>
                            <input id="cbInverseTermOther" type="radio" name="rbInverseType" style="margin-left:10px;"/>
                            <label for="cbInverseTermItself">Term is inverse of another term</label>
                        </div>
                        <div id="divInverse" class="dtyField"><label class="dtyLabel">Inverse Term</label>
                            <input id="edInverseTerm" readonly="readonly" style="width:250px;background-color:#DDD;"/>
                            <input id="btnInverseSetClear" type="button" value="clear" style="width:45px"/>
                        </div>
                        <input id="edInverseTermId" type="hidden"/>


                        <div  class="dtyField" id="divImage">
                            <div style="float:left;">
                                <label class="dtyLabel" style="margin-top:10px;vertical-align: top;">Image (~400x400):</label>
                            </div>
                            <div style="vertical-align: middle;display:inline-block;">
                                <div id="termImage" style="min-height:100px;min-width:100px;border:gray; border-radius: 3px; box-shadow: 0 1px 3px RGBA(0,0,0,0.5);" >
                                </div>
                            </div>

                            <a href='#' id="btnClearImage" style="margin-top:10px;vertical-align: top;"
                                onClick="{editTerms.clearImage(); return false;}">
                                <img src="../../../common/images/cross-grey.png" style="vertical-align:top;width:12px;height:12px">Clear image</a>
                            <!--
                            <input id="btnClearImage" type="button" value="Clear"
                            title="Remove image"
                            style="margin-top:10px;vertical-align: top;"
                            onClick="{editTerms.clearImage()}"/>
                            -->

                            <div style="padding-left:105;padding-top:3px; font-size:smaller;">
                                Images can be used to provide a visual description of a term such as an architectural or clothing style, structural position, artefact type or soil texture"
                            </div>
                        </div>



                        <!--
                        NOTE: button labelling is set in the JS file
                        -->
                        <div style="display:inline-block; margin-top:30px;width:90%">
                            <input id="btnImport" type="button" value="Import"
                                title=""
                                onClick="{editTerms.doImport(false)}"/>
                            <input id="btnExport" type="button" value="Export"
                                title="Print vocabulary as a list"
                                onClick="{editTerms.doExport(false)}"/>
                            <input id="btnSetParent" type="button" value="Move"
                                style="margin-left:20px;"
                                title="Change the parent" onClick="{editTerms.selectParent()}"/>
                            <input id="btnMerge" type="button" value="Merge"
                                title="Merge this term with another term and update all records to reference the new term"
                                onClick="{editTerms.mergeTerms()}"/>
                            <input id="btnDelete" type="button" value="Delete"
                                title=" "
                                onClick="{editTerms.doDelete()}" />
                            <input id="btnSave" type="button" value="Save changes"
                                style="margin-left:80px;font-style: bold !important; color:black; display:none"
                                title=" "
                                onClick="{editTerms.doSave()}" />

                            <div id='div_btnAddChild'
                                style="text-align: right; float:right; margin-left:10px; font-style: bold; colour:black;">
                                <input id="btnAddChild" type="button" value="Add Child" onClick="{editTerms.doAddChild(false)}"/>
                            </div>
                        </div>
                        <!--
                        <div style="float:right; text-align: right;">
                        </div>
                        -->

                    </div>

                    <div id="formAffected" style="display:none;padding:10px;width:480px;">
                        <p><h2>WARNING</h2> ADDING TERMS TO THE TREE DOES NOT ADD THEM TO ENUMERATED FIELDS
                        <p>You have added terms to the term tree. Since terms are chosen individually for each field,
                        you will need to update your selection for enumerated fields using these terms.
                        <p align="center">
                            <input id="btnUpdateFieldTypes" type="button" value="Update Field Types" onClick="{editTerms.showFieldUpdater()}" />
                        </p>
                    </div>

                    <div id="formEditFields" style="padding:10px;width:480px;">

                    </div>

                </div>


                <!-- search for and set inverse terms, only for relationship types -->
                <div id="formInverse" style="display:none;width:500px;">

                    <h3 style="border-style: none;margin-left:10px;">Select Inverse Term</h3>
                    <div style="border: black; border-style: solid; border-width:thin; padding:10px;margin-left:10px;">

                        <div style="display:inline-block;vertical-align: top; width:130px;">
                            <label class="dtyLabel" style="width:30px;">Find:</label>
                            <input id="edSearchInverse" style="width:70px" onkeyup="{doSearch(event)}"/><br/>
                            type 3 or more letters<br/><br/>select inverse from list
                            <input id="btnSelect2" type="button" value="and Set As Inversed" onClick="{editTerms.doSelectInverse()}" />
                        </div>
                        <div style="display:inline-block;">
                            <select id="resSearchInverse" size="5" style="width:320px" ondblclick="{editTerms.doSelectInverse()}"></select>
                        </div>
                    </div>

                </div>

                <div id="divApply" style="margin-left:10px; margin-top:15px; text-align:left; display: block;">
                    Warning: if a field uses individually selected terms, new terms must be selected in record structure edit
                    to appear in data entry dropdown. If field uses a vocabulary, new terms are added automatically.<p>
                        <input type="button" id="btnApply1" style="float:right;" value="Close window" onclick="editTerms.applyChanges();" /></p>
                </div>

            </div>

        </div>

        <div id="divMessage" style="display:none;height:80px;text-align: center; ">
            <div id="divMessage-text" style="text-align:left;width:280px;color:red;font-weight:bold;margin:10px;"></div>
            <button onclick="{top.HEURIST.util.closePopupLast();}">Close</button>
        </div>


        <div id="divTermMergeConfirm" style="display:none;width:500px;padding:20px">

            <table border="0" cellpadding="2px;">
                <tr>
                    <td>Term to be retained:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    <td id="lblTerm_toRetain"></td>
                </tr>
                <tr>
                    <td>Term to be merged:</td>
                    <td id="lblTerm_toMerge"></td>
                </tr>

                <tr style="display: none;">
                    <td>Label:</td>
                    <td>
                        <input id="rbMergeLabel1" type="radio" name="rbMergeLabel" checked="checked"/>
                        <label for="rbMergeLabel1" id="lblMergeLabel1"></label>
                    </td>
                </tr>
                <tr id="mergeLabel2" style="display: none;">
                    <td>&nbsp;</td>
                    <td>
                        <input id="rbMergeLabel2" type="radio" name="rbMergeLabel"/>
                        <label for="rbMergeLabel2" id="lblMergeLabel2"></label>
                    </td>
                </tr>

                <tr>
                    <td><br/>Standard Code:</td>
                    <td><br/>
                        <input id="rbMergeCode1" type="radio" name="rbMergeCode"/> <!-- initially was checked="checked"-->
                        <label for="rbMergeCode1" id="lblMergeCode1"></label>
                    </td>
                </tr>
                <tr id="mergeCode2">
                    <td>&nbsp;</td>
                    <td>
                        <input id="rbMergeCode2" type="radio" name="rbMergeCode"/>
                        <label for="rbMergeCode2" id="lblMergeCode2" ></label>
                    </td>
                </tr>

                <tr>
                    <td><br/>Description:</td>
                    <td><br/>
                        <input id="rbMergeDescr1" type="radio" name="rbMergeDescr"/> <!-- initially was checked="checked"-->
                        <label for="rbMergeDescr1" id="lblMergeDescr1"></label>
                    </td>
                </tr>
                <tr id="mergeDescr2">
                    <td>&nbsp;</td>
                    <td>
                        <input id="rbMergeDescr2" type="radio" name="rbMergeDescr"/>
                        <label for="rbMergeDescr2" id="lblMergeDescr2"></label>
                    </td>
                </tr>

                <tr style='display:none'>
                    <td colspan="2">
                        <label id="lblRetainId"></label>
                        <label id="lblMergeId"></label>
                    </td>
                </tr>
            </table>



            <!--
            <div>
            <label class="dtyLabel">Term to be retained:</label>
            <label id="lblTerm_toRetain"></label>
            </div>
            <div style="padding-top: 4px;">
            <label class="dtyLabel">Term to be merged:</label>
            <label id="lblTerm_toMerge"></label>
            </div>

            <div style="padding-top: 4px;">
            <label class="dtyLabel">Label:</label>
            <input id="rbMergeLabel1" type="radio" name="rbMergeLabel" checked="checked"/>
            <label for="rbMergeLabel1" id="lblMergeLabel1"></label>
            <div style='padding-left:106px;'>
            <input id="rbMergeLabel2" type="radio" name="rbMergeLabel"/>
            <label for="rbMergeLabel2" id="lblMergeLabel2"></label>
            </div>
            </div>

            <div style="padding-top: 4px;">
            <label class="dtyLabel">Code:</label>
            <input id="rbMergeCode1" type="radio" name="rbMergeCode" checked="checked"/>
            <label for="rbMergeCode1" id="lblMergeCode1"></label>

            <div style='padding-left:106px;'>
            <input id="rbMergeCode2" type="radio" name="rbMergeCode"/>
            <label for="rbMergeCode2" id="lblMergeCode2"></label>
            </div>
            </div>

            <div style="padding-top: 4px;">
            <label class="dtyLabel">Description:</label>
            <input id="rbMergeDescr1" type="radio" name="rbMergeDescr" checked="checked"/>
            <label for="rbMergeDescr1" id="lblMergeDescr1"></label>
            <div style='padding-left:106px;'>
            <input id="rbMergeDescr2" type="radio" name="rbMergeDescr"/>
            <label for="rbMergeDescr2" id="lblMergeDescr2"></label>
            </div>
            </div>
            -->
            <div style="margin-top:30px;width:100%;text-align:center;">
                <input id="btnMergeOK" type="button" value="Merge"
                    title=""  style="width:70px"/>
                <input id="btnMergeCancel" type="button" value="Cancel"
                    title=""  style="width:70px; padding-left:10px"/>
            </div>
        </div>

        <input type="file" id="new_term_image" style="display:none"/>

        <script  type="text/javascript">

            YAHOO.util.Event.addListener(window, "load", function(){ editTerms = new EditTerms();} );
            //YAHOO.util.Event.onDOMReady(EditTerms.init);
        </script>

    </body>
</html>