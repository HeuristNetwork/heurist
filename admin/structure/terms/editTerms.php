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
?>



<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Manage terms for term list fields and relationship type</title>

        <link rel="stylesheet" type="text/css" href="../../../ext/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
        <link rel="stylesheet" type="text/css" href="../../../ext/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css" />
        
        <script type="text/javascript" src="../../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-ui-1.12.1/jquery-ui.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script type="text/javascript" src="../../../ext/jquery-file-upload/js/jquery.fileupload.js"></script>

        <link rel="stylesheet" type="text/css" href="../../../ext/fancytree/skin-themeroller/ui.fancytree.css" />
        <script type="text/javascript" src="../../../ext/fancytree/jquery.fancytree-all.min.js"></script>
        <!-- <script type="text/javascript" src="https://github.com/mar10/fancytree/src/jquery.fancytree.wide.js"></script> -->


        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
        <link rel="stylesheet" type="text/css" href="../../../h4styles.css">
        <style type="text/css">
            .dtyField {
                padding-bottom: 3px;
                padding-top: 3px;
                display: inline-block;
                vertical-align: top;
            }
            .dtyLabel {
                display: inline-block;
                width: 100px;
                text-align: right;
                padding-right: 3px;
            }
            .save-disabled, #btnSave.save-disabled:hover{
                color:lightgray !important;
            }

            /* it allows scroll on DnD */
            ul.fancytree-container {
                position: relative;
                height: 99%;
                overflow-y: auto !important;
            }            
            ul.fancytree-container li {
                width:100%;
            }
            /*
            ul.fancytree-container li:hover {
                background-color: rgb(142, 169, 185); 
            }
            */
            span.fancytree-node,
            span.fancytree-title,
            div.svs-contextmenu3 > span.ui-icon{
                vertical-align:bottom;
                padding-left:2px;
            }
            div.svs-contextmenu3{
                cursor:pointer;
                position:absolute;
                right:5px;
                /*min-width: 80px;*/
                /*
                color: white !important;
                visibility: hidden;
                float:right;
                padding-left:10px;
                */
                padding:3px;
                display:none;
                background:white;
                color:#95A7B7;
                border-radius:2px;
            }
            span.fancytree-node{
                min-width: 300px;
                padding:2px !important;
            }
            #term_tree{
                 background:none;
                 border:none;
            }
            span.fancytree-node {
                border: none !important;
                font-weight:bold !important;
                display: block;
                width:100%;
            }
            
            span.fancytree-node.fancytree-active{
                background: #95A7B7 !important;
                color: white !important;
            }
            
            
        /*
        width":"100%","height":"100%
            
            span.fancytree-title:hover, .fancytree-title-hovered{ background-color: #E6F8FD; border-color: #B8D6FB; }
            .ui-state-active{ background-color: #E6F8FD; }

            ul > li > .svs-contextmenu2 {
                display: none;
                cursor: pointer;
                float:right;
            }
        */
            
        </style>
    </head>

    <body class="popup yui-skin-sam">

        <script type="text/javascript" src="../../../common/js/utilsLoad.js"></script>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <script src="../../../common/php/loadCommonInfo.php"></script>

        <script type="text/javascript" src="editTerms.js"></script>

        <div class="ent_wrapper"> <!-- style="top:0px"-->
            <div class="ent_header" id="divBanner" style="height:3em;border:none;">
                <h2></h2>
                <label style="padding:14px 0px;font-size:14px;font-weight:bold">Vocabularies</label>
            </div>
            <div class="ent_content_full" style="overflow:hidden;top:5em">
            
            <div id="termTree" style="width:350;max-width:350;top:0;bottom:0;padding-right:5px;overflow:hidden;position:absolute;border:1px gray solid;">
            </div>

            <div id="formContainer"style="top:0;bottom:0;left:350;right:0;padding-left:10px;position:absolute;vertical-align:top;overflow-y:auto;border:1px gray solid;">
            
               <div style="margin-left:2px; margin-top:15px;">
                    <span class="ui-icon ui-icon-arrowthick-1-w" style="font-size:18px;color:gray"></span>
                    <input id="btnAddRoot1" type="button"
                        value="Add Vocabulary" onClick="{editTerms.doAddChild(true)}"/>
                    <span style="margin-left:10px;"> (adds a new root to the tree)</span>
                </div>
                <!-- Navigation: Search form to do partial match search on terms in the tree -->
                <div id="formSearch" style="padding-top:15px">
                    <div class="dtyField"><label class="dtyLabel" style="width:30px;">Find:</label>
                        <input id="edSearch" style="width:70px"  onkeyup="{doSearch(event)}"/>
                        <br><label style="padding-left:37px">&gt;2 characters</label>
                    </div>
                    <div class="dtyField">
                        <select id="resSearch" size="5" style="width:300px" onclick="{editTerms.doEdit()}"></select>
                    </div>
                </div>

             
                <h2 id="formMessage" style="margin-left:10px; border-style:none;display:block;text-align:left;width:400px;">
                    Rollover terms in the tree to show available actions<br>
                    Drag terms to reposition or merge <br/> Select term to edit label and description
                </h2>

                <div style="margin-left:10px;display:none">
                    <input id="btnAddRoot2" type="button"
                        value="Add Vocabulary" onClick="{editTerms.doAddChild(true)}"/>
                    <span style="margin-top:5px; margin-left:10px; display:none" > (add a new root to the tree)</span>
                </div>

                <div id="deleteMessage" style="display:none;width:500px;">
                    <h3 style="margin-left:10px; border-style:none;">Deleting term</h3>
                </div>

                <!-- Edit form for modifying characteristics of terms, including insertion of child terms and deletion -->
                <div id="formEditor" class="form_editor" style="display:none;width:600px;padding-top:5px">
                    <h2 style="margin-left:5px; margin-top:0px; margin-bottom:10px;border-style:none;display:inline-block">Edit selected term / vocabulary</h2>
                    <div id="div_SaveMessage" style="text-align: center; display:none;color:#0000ff;width:140px;">
                        <b>term saved successfully!</b>
                    </div>

                    <div style="margin-left:5px; border: black; border-style: solid; border-width:thin; padding:10px;">

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


                        <div class="dtyField" style="display:none">
                            <label class="dtyLabel">Definition URL(s)</label>
                            <input id="edURL" style="width:350px; margin-top:5px;"/>
                            <div style="padding-left:105;padding-top:3px;  font-size:smaller;">
                                One or more comma-seperated URls to sources which provide an authorative definition<br/>of the term. By default the definition is asumming to resolve to an html page, but an <br/> alternative form, such as XML can be specified by preceding the URl with an appropraite<br/> term e.g XML htpp://somesite.org.terms/123
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
                            <div id="termImageForNew" style="vertical-align: middle;display:none;padding-left:3px">
                                  <h3 style="padding-top:5px">Save term first to allow upload of an image representing the term</h3>
                            </div>
                            
                            <div style="vertical-align: middle;display:inline-block; padding-left:3px">
                                <div id="termImage" style="min-height:100px;min-width:100px;border:gray; border-radius: 3px; box-shadow: 0 1px 3px RGBA(0,0,0,0.5);" >
                                </div>
                            </div>

                            <a href='#' id="btnClearImage" style="margin-top:10px;vertical-align: top; padding-left:5px"
                                onClick="{editTerms.clearImage(); return false;}">
                                <img src="../../../common/images/cross-grey.png" style="vertical-align:top;width:12px;height:12px">Clear image</a>
                            <!--
                            <input id="btnClearImage" type="button" value="Clear"
                            title="Remove image"
                            style="margin-top:10px;vertical-align: top;"
                            onClick="{editTerms.clearImage()}"/>
                            -->

                            <div style="padding-left:105;padding-top:3px; font-size:smaller;">
                                Images can be used to provide a visual description of a term such as an <br>architectural or clothing style, structural position, artefact type or soil texture"
                            </div>
                        </div>



                        <!--
                         These buttons are hidden now. They are replaced by action icons in treeview (see __defineActionIcons)
                         Either this section of code will be removed or restored in next months
                        -->
                        <div style="display:inline-block; margin-top:30px;width:90%;">
                            <span style="visibility:hidden;">
                            <input id="btnImport" type="button" value="Import"
                                title="IMPORT a comma-delimited list of terms (and optional codes and labels) as children of this term"
                                onClick="{editTerms.doImport(false)}"/>
                            <input id="btnExport" type="button" value="Export"
                                title="EXPORT this vocabulary to a text file"
                                onClick="{editTerms.doExport(false)}"/>
                            <!-- <input id="btnSetParent" type="button" value="Move"
                            style="display:none;margin-left:20px;"
                            title="Change the parent" onClick="{editTerms.selectParent()}"/>
                            <input id="btnMerge" type="button" value="Merge"
                            style="display:none;"
                            title="Merge this term with another term and update all records to reference the new term"
                            onClick="{editTerms.mergeTerms()}"/> -->
                            <input id="btnDelete" type="button" value="Delete"
                                title="Delete this term (if unused in database)"
                                onClick="{editTerms.doDelete()}" />
                            </span>
                            
                            <input id="btnSave" class="btn_Save" type="button" value="Save changes to this term"
                                style="margin-left:80px;font-style: bold !important; color:black; display:none"
                                title=" "
                                onClick="{editTerms.doSave()}" />

                            <span id = "Saved" style="display:none;"><button id="btnSaved" type="button"
                                style="border-radius: 6px; background-color:gray;margin-left:5px;font-style: bold !important;  color:black; display:none;"
                                title=" ">Saved...</button></span>

                            <div id='div_btnAddChild' style="text-align: right; float:right; margin-left:10px; font-style: bold; colour:black;">
                                    <input id="btnAddChild" type="button"
                                         title="Add a child term (a term hierarchichally below the current vocabulary or term)"
                                         value="Add Child"
                                         onClick="{editTerms.doAddChild(false)}"/>
                            </div>
                        </div>
                        <!--
                        <div style="float:right; text-align: right;">
                        </div>
                        -->

                    </div>

                    <div style="padding-top:20px; margin-left:10px">
                        Term(s) have been added to the term tree but this does not add them to the  individual trees for different fields, since these are individually selected from the complete term tree. Please update the lists for each field to which these terms should be added. <br/><br/> The felds most likely to be affected are

                    </div>

                    <div style="margin-left:10px; padding-top:15px; width:600px;; padding-left:30px">
                        <label>Relationship type:</label>
                        <input id="btnChangeVocab" type="button" style="display:inline-block"
                            value="Change Vocabulary" />

                        <span>
                            <select style="background-color:buttonface; font-weight:bold; color:#666; width:200px" id="trm_relationtype" >
                                <option   selected="selected">isRelatedTo</option>
                                <option>#</option>
                                <option>#</option>

                            </select></span>

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
                            <input id="edSearchInverse" style="width:70px" /><br/> <!--onkeyup="{doSearch(event)}" Better to add in js file to allow events recognise other ja funnctions-->
                            type 3 or more letters<br/><br/>select inverse from list
                            <input id="btnSelect2" type="button" value="and Set As Inversed" /> <!-- "onClick="{editTerms.doSelectInverse()}" -->
                        </div>
                        <div style="display:inline-block;">
                            <select id="resSearchInverse" size="5" style="width:320px" ></select><!--ondblclick="{editTerms.doSelectInverse()}"-->
                        </div>
                    </div>

                </div>

                <div id="divApply" style="margin-left:10px; margin-top:15px; text-align:left; display: block;">
                    Warning: if a field uses individually selected terms, new terms must be selected in record structure edit
                    to appear in data entry dropdown. If field uses a vocabulary, new terms are added automatically.<p>
                        <input type="button" id="btnApply1" style="float:right;" value="Close window" onclick="editTerms.applyChanges();" /></p>
                </div>

            </div>

            
            </div> <!-- ent_content_full -->
        </div>

        <div id="divMessage" style="display:none;height:80px;text-align: center; ">
            <div id="divMessage-text" style="text-align:left;width:280px;color:red;font-weight:bold;margin:10px;"></div>
            <button onclick="{top.HEURIST.util.closePopupLast();}">Close</button>
        </div>


        <div id="divTermMergeConfirm" style="display:none;width:100%;padding:5px">

            <div id="divInsertAsChild" style="border-bottom: dotted 1px;">
                <br/>
                <h2 style="width: 300px;display: inline-block;">Insert as child</h2>
                <div style="float:right;margin-right:30px">
                    <input id="moveBtn" type="button" value="Move"
                        title=""  style="width:70px"/>
                </div>
                <div>
                    <br/>
                    <span id ="moveText"></span>
                    <span>&nbsp;&nbsp;</span>
                </div>
                <br/>
            </div>
            
            <br/>
            <h2 style="width: 200px;display: inline-block;">Merge</h2>
            <div style="margin-right:30px;float:right;">
                <input id="btnMergeOK" type="button" value="Merge"
                    title=""  style="width:70px"/>
            </div>
            <br/><br/>
            <table border="0" cellpadding="2px;">
            
            
                <!--<tr>
                <td><h2>Insert as child</h2></td>
                <td></td>
                </tr> !-->
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
                    <td><br>Standard Code:</td>
                    <td><br>
                        <input id="rbMergeCode1" type="checkbox" name="rbMergeCode"/> <!-- initially was checked="checked"-->
                        <label for="rbMergeCode1" id="lblMergeCode1"></label>
                    </td>
                </tr>
                <tr id="mergeCode2">
                    <td>&nbsp;</td>
                    <td>
                        <input id="rbMergeCode2" type="checkbox" name="rbMergeCode"/>
                        <label for="rbMergeCode2" id="lblMergeCode2" ></label>
                    </td>
                </tr>

                <tr>
                    <td>Description:</td>
                    <td><input id="rbMergeDescr1" type="checkbox" name="rbMergeDescr"/> <!-- initially was checked="checked"-->
                        <label for="rbMergeDescr1" id="lblMergeDescr1"></label>
                    </td>
                </tr>
                <tr id="mergeDescr2">
                    <td>&nbsp;</td>
                    <td>
                        <input id="rbMergeDescr2" type="checkbox" name="rbMergeDescr"/>
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
            
            <div style="margin-right:30px;float:right;">
                    <input id="btnMergeCancel" type="button" value="Cancel"
                        title=""  style="width:70px; padding-left:10px"/>
                    <!-- input id="moveBtnCancel" type="button" value="Cancel"
                                title=""  style="width:70px; padding-left:10px"/ -->
            </div>
        </div>

        
        

        <div id=move_mergeTerms style="display:none ">
            <div id="mergeText" style="font-weight:bold;">Are you sure you want to Merge terms?</div>
            <input id="mergeBtn" type="button" value="Merge" title="" style="width:70px"/>
            <input id="cancelBtn" type="button" value="Cancel" title="" style="width:70px; margin:2px"/>
        </div>
        <input type="file" id="new_term_image" style="display:none"/>

        <script  type="text/javascript">
            $( document ).ready( function(){ editTerms = new EditTerms();} );
        </script>

    </body>
</html>