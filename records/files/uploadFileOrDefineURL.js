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
	curr_ext,
	URLInput,
	fileUploadInput,
	_filedata = null;

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
 		curr_ext = oType.extension;

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

			_filedata = Hul.expandJsonStructure(top.HEURIST.parameters['value']);

			sUrl    = _filedata.remoteURL?_filedata.remoteURL:_filedata.URL;
			sType   = _filedata.mediaType;
			sSource = _filedata.remoteSource;

			if( !Hul.isempty(sUrl) && (Hul.isempty(sSource) || Hul.isempty(sType)) ){
				var oType = detectSourceAndType(sUrl, _filedata.ext);
				sType = oType.type;
				sSource = oType.source;
			}

			/*var acfg = top.HEURIST.parameters['value'].split('|');
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
			}*/
		}

		//add file upload component
		var container = document.getElementById("div_fileupload");
		fileUploadInput = new top.HEURIST.edit.inputs.BibDetailFileInput("0", dt, rfr, fieldValues, container);

		fileUploadInput.onchange = updateURLtoFile;

		//add URL component
		container = document.getElementById("div_url");
		URLInput = new top.HEURIST.edit.inputs.BibURLInput(container, sUrl, false);

		// access to input element URLInput.inputs[0]
		this.changed = function(){};
		URLInput.inputs[0].onblur = onChangeURL;

		/*
		if(Hul.isempty(sSource)){
			document.getElementById("cbSource").selectedIndex = 0;
		}else{
			document.getElementById("cbSource").value = sSource;
		}
		if(Hul.isempty(sType)){
			document.getElementById("cbType").selectedIndex = 0;
		}else{
			document.getElementById("cbType").value = sType;
		}*/

		if(!Hul.isempty(sUrl)){
			if(Hul.isempty(sSource) || Hul.isempty(sType)) {
				onChangeURL(null, null);
			}else{
 				curr_link = sUrl;
 				document.getElementById("cbSource").value = sSource;
 				document.getElementById("cbType").value = sType;
 				showViewer(document.getElementById('preview'), curr_link+'|'+sSource+'|'+sType);
			}
		}

}

function onApply(){
/*
	if(Hul.isempty(_filedata)){ //new

		_filedata = {id:0, remoteURL:curr_link,
					origName:
					remoteSource:document.getElementById("cbSource").value,
					mediaType:document.getElementById("cbType").value };

	}else{
		_filedata.origName =
		_filedata.remoteURL = curr_link;
		_filedata.remoteSource = document.getElementById("cbSource").value;
		_filedata.mediaType = document.getElementById("cbType").value;
	}

	_filedata = fileUploadInput.getFileId();

	if(_filedata.remoteSource=='heurist' &&  !Hul.isempty(fileId) ){  //!!!!!
		//take id from
		_filedata.id = fileId;
	}
*/
	_filedata = fileUploadInput.getFileData();

	if(Hul.isempty(_filedata)){ //new
		_filedata = {id:0, remoteURL:curr_link,
					ext:curr_ext,
					remoteSource:document.getElementById("cbSource").value,
					mediaType:document.getElementById("cbType").value };
	}else{
		_filedata.remoteURL = curr_link;
		_filedata.remoteSource = document.getElementById("cbSource").value;
		_filedata.mediaType = document.getElementById("cbType").value;
		if(curr_ext) _filedata.ext = curr_ext;
	}

	var res = top.YAHOO.lang.JSON.stringify(_filedata);
	window.close( res );//curr_link, document.getElementById("cbSource").value, document.getElementById("cbType").value);
}

function onCancel(){
	window.close(null);
}
