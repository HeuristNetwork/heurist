/**
 * uploadFileOrDefineURL.js
 *
 * Popup dialogue to define URLinclude field type in editRecord
 * URL is either to external resource or link file uploaded to heurist
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 * @todo - implement basic preview
 */
var viewerObject,
	Hul = top.HEURIST.util,
	curr_link,
	URLInput;

 /**
 * User has uploaded the file - change the url to point to this file and change source and type
 */
 updateURLtoFile = function(input)
 {
		var rec_url = URLInput.inputs[0]; //document.getElementById("rec_url");
 		rec_url.value = input.link;
 		onChangeURL(null, input.fileType);
 }

 /**
 * User changes the link and exit from this input control - try to find the source and type
 */
 onChangeURL = function(event, ext){

 	var rec_url = URLInput.inputs[0]; //document.getElementById("rec_url");
 	if(rec_url.value!=="" && curr_link!==rec_url.value){

 		curr_link = rec_url.value;
 		var oType = detectSourceAndType(curr_link, ext);

 		document.getElementById("cbSource").value = oType.source;
 		document.getElementById("cbType").value = oType.type;

 		showViewer(document.getElementById('preview'), curr_link+'|'+oType.source+'|'+oType.type)
	}
 }

// @todo MOVE to HEURIST.util ????
//
// find detail type by "origin dbid"-"id in original db"
//
function findDetailTypeByType(type){

	var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
	var dt;
	for (dt in top.HEURIST.detailTypes.typedefs){
		if(!(Hul.isnull(dt) || isNaN(Number(dt)))) {
			var dtype= top.HEURIST.detailTypes.typedefs[dt];

			if(dtype['commonFields'][fi.dty_Type]===type){
				return dtype;
			}
		}
	}
	return null; //nothing found
}


//
function initPage() {

		var fieldValues = [];

		//toremove
		var dt = findDetailTypeByType("file"); //top.HEURIST.detailTypes.typedefs[221]['commonFields']; //associated file
		var rfr = ["File", "File", null, "005", "60", "", "0", null, "optional", "viewable", "open", null, "1", "0", "1",
			null,null,null,null,"","9",null,null];

		var sUrl = "";
		var sType = "";
		var sSource = "";

		if(location.search.length > 1) {
			top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

			var acfg = top.HEURIST.parameters['value'].split('|');
			if(Hul.isnull(acfg) || acfg.length<1){
				return;
			}

			sUrl = acfg[0];

			if(acfg.length<3){
				var oType = detectSourceAndType(sUrl, null);
				sType = oType.type;
				sSource = oType.source;
			}else{
				sSource = acfg[1];
				sType = acfg[2];
			}
		}

		//add file upload component
		var container = document.getElementById("div_fileupload");
		var newInput = new top.HEURIST.edit.inputs.BibDetailFileInput("0", dt, rfr, fieldValues, container);

		newInput.onchange = updateURLtoFile;

		//add URL component
		container = document.getElementById("div_url");
		URLInput = new top.HEURIST.edit.inputs.BibURLInput(container, sUrl, false);

		// access to input element URLInput.inputs[0]
		this.changed = function(){};
		URLInput.inputs[0].onblur = onChangeURL;

		if(Hul.isempty(sSource)){
			document.getElementById("cbSource").selectedIndex = 0;
		}else{
			document.getElementById("cbSource").value = sSource;
		}
		if(Hul.isempty(sSource)){
			document.getElementById("cbType").selectedIndex = 0;
		}else{
			document.getElementById("cbType").value = sType;
		}

		if(!Hul.isempty(sUrl)){
			onChangeURL(null, null);
		}

}

function onApply(){
	window.close(curr_link, document.getElementById("cbSource").value, document.getElementById("cbType").value);
}

function onCancel(){
	window.close(null);
}
