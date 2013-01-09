<?php

/**
* editTerms.php
*
* add/edit/delete terms. Treeveiew on the left side with tabview to select domain: enum or relation
* form to edit term on the right side
*
* 2011-03-25
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
*
* @todo
**/

// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
if (!is_admin()) {
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to modify database structure</span><p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
    return;
}
?>

<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Heurist - Edit terms</title>

		<!-- YUI -->
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
		<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/tabview/assets/skins/sam/tabview.css" />
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/element/element-min.js"></script>
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/tabview/tabview-min.js"></script>
		<!--script type="text/javascript" src="../../external/yui/2.8.2r1/build/history/history-min.js"></script!-->

		<!-- TREEVIEW DEFS -->
		<!-- Required CSS -->
		<link type="text/css" rel="stylesheet" href="../../external/yui/2.8.2r1/build/treeview/assets/skins/sam/treeview.css">
		<!-- Optional dependency source file -->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/animation/animation-min.js"></script>
		<!-- Optional dependency source file to decode contents of yuiConfig markup attribute-->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/json/json-min.js" ></script>
		<!-- TreeView source file -->
		<script type="text/javascript" src="../../external/yui/2.8.2r1/build/treeview/treeview-min.js" ></script>
		<!-- END TREEVIEW DEFS-->
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
   		<!--<link rel=stylesheet href="../../common/css/admin.css">-->
		<style type="text/css">
			.dtyField {
				padding-bottom: 3px;
				padding-top: 3px;
				display: inline-block;
			}
			.dtyLabel {
				display: inline-block;
				width: 150px;
				text-align: right;
				padding-right: 3px;
			}
		</style>

</head>
<body class="popup yui-skin-sam">

	<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
	<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
	<script src="../../common/php/loadCommonInfo.php"></script>

	<script type="text/javascript" src="editTerms.js"></script>

    <div id="divBanner" class="banner"><h2>Edit Terms</h2></div>
	<div id="page-inner">

	<div id="pnlLeft" style="height:100%; width:300; max-width:300; float: left; padding-right:5px; overflow: hidden;">

		<div style="padding-left:120px"><input id="btnAddChild" type="button" value="Add Vocabulary (top level)" onClick="{editTerms.doAddChild(true)}"/>
		</div>

		<!-- Container for tab control component, each tab contains tree view, one for enums, one for relationship types-->
        <div id="tabContainer" class="yui-navset yui-navset-top" style="y:138; height:70%; width:400; max-width:300; float: left; overflow: hidden;"></div><br/>

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

	<div id="formContainer" style="float: left; padding-bottom:5px; padding-left: 10px;">

		<h3 id="formMessage" style="border-style: none;display:block;text-align:center;width:300px;">
			Select a term in the tree to edit or add child terms
		</h3>
		Click button on the left to add a new vocabulary

		<div id="deleteMessage" style="display:none;width:500px;">
			<h3 style="border-style: none;">Deleting Term</h3>
		</div>

	    <!-- Edit form for modifying characteristics of terms, including insertion of child terms and deletion -->
        <div id="formEditor" style="display:none;width:500px;">
			<h3 style="border-style: none;">Edit Selected Term</h3>
			<div style="border: black; border-style: solid; border-width:thin; padding:10px;">

			<div class="dtyField">
				<label class="dtyLabel">ID:</label>
				<input id="edId" readonly="readonly" style="width:50px"/>
				<input id="edParentId" type="hidden"/>
				&nbsp;&nbsp;&nbsp;<div id="div_ConceptID" style="display: inline-block;"></div>
				<div id="div_SaveMessage" style="text-align: center; display:none;color:#0000ff;width:140px;">
					<b>Term saved</b>
				</div>
			</div>
			<div class="dtyField">
				<div style="display:inline-block;"><label class="dtyLabel" style="color: red;">Display name:</label><input id="edName" style="width:160px"/></div>

				<div style="float:right; text-align: right; width:140px;">
					<input id="btnSave" type="button" value="Save" onClick="{editTerms.doSave()}" />
					<div id='div_btnAddChild' style="text-align: right; display:inline-block;">
						<input id="btnAddChild" type="button" value="Add Child" onClick="{editTerms.doAddChild(false)}"/>
					</div>
				</div>
			</div>
			<div class="dtyField"><label class="dtyLabel">Code:</label><input id="edCode" style="width:80px"/></div>
			<div class="dtyField"><label class="dtyLabel">Description:</label><input id="edDescription" style="width:300px"/></div>

			<div id="divInverse" class="dtyField"><label class="dtyLabel">Inverse Term:</label><input id="edInverseTerm" readonly="readonly" style="width:250px"/>
				<input id="btnInverseSetClear" type="button" value="clear" style="width:45px"/>
				<input id="edInverseTermId" type="hidden"/>
			</div>

<!--
				<p><h2>WARNING</h2> ADDING TERMS TO THE TREE DOES NOT ADD THEM TO ENUMERATED FIELDS</p>
				<p>To add terms to fields, edit the field and click the [Vocabulary] button</p>
-->
			<div style="display:inline-block;">
				<input id="btnImport" type="button" value="Import from file" onClick="{editTerms.doImport(false)}"/>&nbsp;&nbsp;&nbsp;
				<input id="btnSetParent" type="button" value="Change Parent" title="Change the parent" onClick="{editTerms.selectParent()}"/>&nbsp;&nbsp;&nbsp;
				<input id="btnDelete" type="button" value="Delete Term" onClick="{editTerms.doDelete()}" />
			</div>
	        <div id="divStatus" class="dtyField" style="float:right;width:130px;"><label>Status:</label>
	                <select class="dtyValue" id="trm_Status" onChange="editTerms.onChangeStatus(event)">
	                    <option selected="selected">open</option>
	                    <option>pending</option>
	                    <option>approved</option>
	                    <!-- option>reserved</option -->
	                </select>
	        </div>

			</div>

			<div id="formAffected" style="display:none;padding:10px;width:480px;">
				<p><h2>WARNING</h2> ADDING TERMS TO THE TREE DOES NOT ADD THEM TO ENUMERATED FIELDS</p>
				You have added terms to the term tree. Since terms are chosen individually for each field, you will need to update your selection for enumerated fields using these terms.
				<p align="center">
					<input id="btnUpdateFieldTypes" type="button" value="Update Field Types" onClick="{editTerms.showFieldUpdater()}" />
				</p>
			</div>
			<div id="formEditFields" style="padding:10px;width:480px;">

			</div>
		</div>


        <!-- search for and set inverse terms, only for relationship types -->
		<div id="formInverse" style="display:none;">

			<h3 style="border-style: none;">Select Inverse Term</h3>
			<div style="border: black; border-style: solid; border-width:thin; padding:10px;">

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

		<div id="divApply" style="position: relative; text-align:right; display: block; margin:20px 0;">
		Note: new terms must be selected (selection window behind this one) <br>after addition to the tree, in order to appear in data entry pulldown lists<p>
		<input type="button" id="btnApply1" value="Close window" onclick="editTerms.applyChanges();" />
	</div>

	</div>

	</div>

	<div id="divMessage" style="display:none;height:80px;text-align: center; ">
		<div id="divMessage-text" style="text-align:left;width:280px;color:red;font-weight:bold;margin:10px;"></div>
		<button onclick="{top.HEURIST.util.closePopupLast();}">Close</button>
	</div>


	<script  type="text/javascript">

		YAHOO.util.Event.addListener(window, "load", function(){ editTerms = new EditTerms();} );
		//YAHOO.util.Event.onDOMReady(EditTerms.init);
	</script>

</body>
</html>