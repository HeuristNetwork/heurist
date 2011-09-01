<?php
/**
 * formBugReport.html
 *
 * form to enter info about bug, on submit it send this info as rectype 68-216 to h3dev
 *
 * 2011/06/07
 * @author: Artem Osmakov
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 * @todo - load rectype definition from remote heurist instance
 **/

 require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

 ?>
<html>
 <head>
  <link rel=stylesheet href="../../common/css/global.css">
  <link rel=stylesheet href="../../common/css/edit.css">

  <script type="text/javascript">

function submit_email() {
	top.HEURIST.util.xhrFormSubmit(window.document.forms[0], handleSaved)
}
function handleSaved(json) {
	var vals = eval(json.responseText);
	if (! vals) {
		window.location.reload();	// no changes to save ... make with the flishy-flashy
		return;
	}

	if (vals.error) {
		alert(vals.error);
		return;
	}

	if (! vals.matches) {
		// regular case -- we get back an object containing updates to the record details
		//for (var i in vals)	parent.HEURIST.edit.record[i] = vals[i];
		window.location.reload();
	}
	else {
		// we have been supplied with a list of biblio records that look like this one
		//if (parent.popupDisambiguation) parent.popupDisambiguation(vals.matches);
	}
}

function verifyInput() {
// Return true if and only if all required fields have been filled in.
// Otherwise, display a terse message describing missing fields.
if (window.HEURIST.uploadsInProgress && window.HEURIST.uploadsInProgress.counter > 0) {
// can probably FIXME if it becomes an issue ... register an autosave with the upload completion handler
alert("File uploads are in progress ... please wait");
return false;
}
var elname = window.document.getElementById('type:<?=DT_BUG_REPORT_NAME?>[]');
if(elname.value === ''){
	alert("There was a problem:\n'Bug Title' field is required. Specify short name for bug.");
	elname.focus();
	return false;
}
window.document.forms[0].heuristSubmit = submit_email;
return true;
}

//
function doUploadFile(event)
{
	top.HEURIST.edit.uploadFileInput.call(window, event.target);
};

//
var uploadCompleted = function(inputDiv, bdValue) {
	//var thisRef = this;	// for great closure
	//var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

	if (bdValue  &&  bdValue.file) {
		// A pre-existing file: just display details and a remove button
		var hiddenElt = inputDiv.hiddenElt = window.document.createElement("input");
			hiddenElt.name = "type:<?=DT_BUG_REPORT_FILE?>[]";//inputDiv.name;
			hiddenElt.value = hiddenElt.defaultValue = (bdValue && bdValue.file)? bdValue.file.id : "0";
			hiddenElt.type = "hidden";
			inputDiv.appendChild(hiddenElt);

		var link = inputDiv.appendChild(window.document.createElement("a"));
			if (bdValue.file.nonce) {
				link.href = top.HEURIST.basePath+"records/files/downloadFile.php/" + /*encodeURIComponent(bdValue.file.origName)*/
								"?ulf_ID=" + encodeURIComponent(bdValue.file.nonce);
			} else if (bdValue.file.url) {
				link.href = bdValue.file.url;
			}
			link.target = "_surf";
			link.onclick = function() { top.open(link.href, "", "width=300,height=200,resizable=yes"); return false; };

		link.appendChild(window.document.createTextNode(bdValue.file.origName));	//saw TODO: add a title to this which is the bdValue.file.description

		var linkImg = link.appendChild(window.document.createElement("img"));
			linkImg.src = top.HEURIST.basePath+"common/images/external_link_16x16.gif";
			linkImg.className = "link-image";

		var fileSizeSpan = inputDiv.appendChild(window.document.createElement("span"));
			fileSizeSpan.className = "file-size";
			fileSizeSpan.appendChild(window.document.createTextNode("[" + bdValue.file.fileSize + "]"));
/*
		var removeImg = inputDiv.appendChild(window.document.createElement("img"));
			removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
			removeImg.className = "delete-file";
			removeImg.title = "Remove this file";
			var windowRef = window.document.parentWindow  ||  window.document.defaultView  ||  window.document._parentWindow;
			top.HEURIST.registerEvent(removeImg, "click", function() {
				thisRef.removeFile(inputDiv);
				windowRef.changed();
			});
*/
		inputDiv.valueElt = hiddenElt;
		inputDiv.className = "file-div";
	}
}


function addFileUploadInput()
{
	//var dt = ["AssociatedFile",null,"file","0",	"AssociatedFile","1","open","1",null,null,null,null,"221"];
	//var rfr =
	//var newInput = new top.HEURIST.edit.inputs.BibDetailFileInput(dt, rfr, fieldValues, container);

	var el = window.document.getElementById("fileUploadInput");
	var el_div = window.document.getElementById("fileUploadDiv");

	el_div.input = el;
	el.constructInput = uploadCompleted;
	el.replaceInput = function(inputDiv, bdValue) {
		inputDiv.innerHTML = "";
		this.constructInput(inputDiv, bdValue);
	};
}

//		jsonAction="stub"
  </script>

 </head>
 <body class="editTab" onload="addFileUploadInput()">
  <script src="../../common/js/utilsLoad.js"></script>
  <script src="../../common/php/displayPreferences.php"></script>

<form method=post enctype="multipart/form-data"
		onsubmit="return verifyInput();"
		action="stub" target=save-frame>

<input type="hidden" name="save-mode" id="save-mode">
<input type="hidden" name="notes" id="notes">
<input type="hidden" name="rec_url" id="rec_url">
<input type="hidden" name="rectype" id="rectype" value="<?=RT_BUG_REPORT?>">
<div id=all-inputs>

<div class="input-row required">
	<div class="input-header-cell">Bug Title</div>
	<div class="input-cell">
		<input style="width: 80ex;" title="Bug Title" id="type:<?=DT_BUG_REPORT_NAME?>[]" name="type:<?=DT_BUG_REPORT_NAME?>[]" class="in" autocomplete="off" type="text">
		<div class="help prompt">Specify short name for bug</div>
	</div>
</div>
<div class="input-row">
	<div class="input-header-cell">Bug description</div>
	<div class="input-cell">
		<textarea style="width: 80ex;" title="Bug description" name="type:<?=DT_BUG_REPORT_DESCRIPTION?>[]" class="in" rows="3"></textarea>
		<div class="help prompt">Detail description of bug</div>
	</div>
</div>
<div class="input-row">
	<div class="input-header-cell">Steps to reproduce</div>
	<div class="input-cell">
		<textarea style="width: 80ex;" title="Steps to reproduce" name="type:<?=DT_BUG_REPORT_ABSTRACT?>[]" class="in" rows="3"></textarea>
		<div class="help prompt">Describe what actions you took to obtain the bug</div>
	</div>
</div>
<div class="input-row">
	<div class="input-header-cell">Screenshot
		<img alt="Add another Screenshot field" title="Add another Screenshot field" class="duplicator" src="/h3-ao/common/images/duplicate.gif">
	</div>
	<div class="input-cell">
		<div id="fileUploadDiv" class="file-div empty"
				style="width: 80ex;" title="Screenshot">
			<input class="file-select" id="fileUploadInput"
				name="type:<?=DT_BUG_REPORT_FILE?>[]" type="file" onchange="doUploadFile(event)"></div>
			<div class="help prompt">Make a screenshot of bug and browser this image file</div>
	</div>
</div>
<!--
bib-detail-type="179"
bib-detail-type="191"
bib-detail-type="259"
bib-detail-type="221"
-->
</div>
</form>

 </body>
</html>
