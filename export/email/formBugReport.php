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

//		jsonAction="stub"
  </script>

 </head>
 <body class="editTab">
  <script src="../../common/js/utilsLoad.js"></script>
  <script src="../../common/php/displayPreferences.php"></script>

<form method=post enctype="multipart/form-data"
		onsubmit="return verifyInput();"
		action="stub" target=save-frame>

<input type="hidden" name="save-mode" id="save-mode">
<input type="hidden" name="notes" id="notes">
<input type="hidden" name="rec_url" id="rec_url">
<input type="hidden" name="rectype" id="rectype" value="<?=RT_BUG_REPORT?>"> <!-- magic number -->
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
		<div class="file-div empty" style="width: 80ex;" title="Screenshot">
			<input class="file-select" name="type:<?=DT_BUG_REPORT_FILE?>[]" type="file" onchange="doUploadFile(event)"></div>
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
