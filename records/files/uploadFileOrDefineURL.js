/*
* Copyright (C) 2005-2016 University of Sydney
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
* uploadFileOrDefineURL.js
* Popup dialogue to define URLinclude field type in editRecord
* URL is either to external resource or link file uploaded to heurist
* requires initViewer
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
* @todo        implement basic preview  
*/


var viewerObject,
	curr_link,
	curr_ext,
	URLInput,
	fileUploadInput,
	_filedata = null,
	_recordID = null,
    _fileselected = false,
	_fileuploaded = false;

 /**
 * User has uploaded the file - change the url to point to this file and change source and type
 */
 updateURLtoFile = function(input)
 {
		var rec_url = URLInput.inputs[0]; //document.getElementById("rec_url");
 		rec_url.value = input.link;
        _fileselected = false;
 		_fileuploaded = true;
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

        //it is possible to define link to resource within heurist domain as REMOTE
        if(oType.source=='heurist' && (!_fileuploaded || _filedata.remoteURL) ){
            oType.source = 'generic';
        }

 		document.getElementById("cbSource").value = oType.source;
 		document.getElementById("cbType").value = oType.type;
 		curr_ext = oType.extension;

 		showViewer(document.getElementById('preview'), [curr_link, oType.source, oType.type], _recordID)
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
		if(!(top.HEURIST.util.isnull(dt) || isNaN(Number(dt)))) {
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
    
        if(!top.HEURIST.detailTypes){
            top.HEURIST.rectypes = window.hWin.HEURIST4.rectypes;                        
            top.HEURIST.detailTypes = window.hWin.HEURIST4.detailtypes;                        
/*            
            $.getScript(window.hWin.HAPI4.baseURL + 'common/php/loadCommonInfo.php?db='+window.hWin.HAPI4.database, function(){ initPage(); } );
            return
*/            
        }
        
    

		var fieldValues = [];

		//toremove
		var dt = findDetailTypeByType("file"); //top.HEURIST.detailTypes.typedefs[221]['commonFields']; //associated file
		var rfr = ["File", "File", null, "005", "60", "", "0", null, "optional", "viewable", "open", null, "1", "0", "1",
			null,null,null,null,"","9",null,null];

		var sUrl = "",
		    sType = "",
		    sSource = "",
            sDisplay = null,
            canEdit = true;

		if(location.search.length > 1) {
			top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

			_recordID = top.HEURIST.parameters['recid'];
			_filedata = top.HEURIST.util.expandJsonStructure(top.HEURIST.parameters['value']);

            if(_filedata.remoteURL){
                sUrl    = _filedata.remoteURL;
            }else{
                sDisplay = _filedata.origName;
                canEdit = false
                sUrl    = _filedata.URL;
            }
			sType   = _filedata.mediaType;
			sSource = _filedata.remoteSource;

			if( !top.HEURIST.util.isempty(sUrl) && (top.HEURIST.util.isempty(sSource) || top.HEURIST.util.isempty(sType)) ){
				var oType = detectSourceAndType(sUrl, _filedata.ext);
				sType = oType.type;
				sSource = oType.source;
			}

			/*var acfg = top.HEURIST.parameters['value'].split('|');
			if(top.HEURIST.util.isnull(acfg) || acfg.length<1){
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

        // change default appearence - it does not work
        /*setTimeout(function(){
            var ele = $(container).find('input[type=file]')
            ele.css({'border':'none !important', 'border-color':'white'});
            console.log(ele.css('border'));
        },2000);
        */
        
        $(container).find('.input-header-cell').text('');
        
		fileUploadInput.promptDiv.innerHTML = '';
		fileUploadInput.onchange = updateURLtoFile;

		//add URL component
		container = document.getElementById("div_url");
		URLInput = new top.HEURIST.edit.inputs.BibURLInput(container, sUrl, false, sDisplay, canEdit);

		//URLInput.headerCell.innerHTML = "URL or Absolute Path";

		// access to input element URLInput.inputs[0]
		this.changed = function(){};
		URLInput.inputs[0].onblur = onChangeURL;

		$(".input-header-cell").css({"min-width":"25px","width":25,"font-weight":"bold","color":"#354F75"});
		$("input[type=file]").css({"border":"none !important"});
		$("#rec_url").css({"min-width":"580px"});

		/*
		if(top.HEURIST.util.isempty(sSource)){
			document.getElementById("cbSource").selectedIndex = 0;
		}else{
			document.getElementById("cbSource").value = sSource;
		}
		if(top.HEURIST.util.isempty(sType)){
			document.getElementById("cbType").selectedIndex = 0;
		}else{
			document.getElementById("cbType").value = sType;
		}*/

		if(!top.HEURIST.util.isempty(sUrl)){
			if(top.HEURIST.util.isempty(sSource) || top.HEURIST.util.isempty(sType)) {
				onChangeURL(null, null);
			}else{
 				curr_link = sUrl;
 				document.getElementById("cbSource").value = sSource;
 				document.getElementById("cbType").value = sType;
 				showViewer(document.getElementById('preview'), [curr_link, sSource, sType], _recordID);
			}
		}

}
//
//
//
function showFileSelectionPopup(){

    var url = top.HEURIST.baseURL + "hclient/framecontent/test/testFileSelector.php?db="+top.HEURIST.database.name;
    top.HEURIST.util.popupURL(this, url, { height: 700, width:700, title:'Select uploaded file',
                            callback: function(context) {
                            if(context) {
                                //alert('selected '+context);
           
_fileuploaded = false;                                
_fileselected = true;
_filedata = top.HEURIST.util.expandJsonStructure(context);
_filedata = _filedata['file'];
/*{"id":23,"origName":"0_a1ed2_3548064c_XL.jpg",
"URL":"http://127.0.0.1/h4-ao/records/files/downloadFile.php/0_a1ed2_3548064c_XL.jpg?db=artem_8&ulf_ID=c373fc6140bcd755a1c24c5a6380d37192cffe4e",
"fileSize":"171","ext":"jpg",
"remoteURL":null,"remoteSource":"heurist","mediaType":"image"};*/
                               
                 curr_link = _filedata.URL;
                 URLInput.inputs[0].value = curr_link;
                 document.getElementById("cbSource").value = _filedata.remoteSource;
                 document.getElementById("cbType").value = _filedata.mediaType;
                 showViewer(document.getElementById('preview'), [curr_link, _filedata.remoteSource, _filedata.mediaType], _recordID);
                               
                                
                            }
                            }});
}



// returns
function isChanged(){
  if(_fileuploaded || _fileselected){
  		return true;
  }else if (top.HEURIST.util.isempty(_filedata) && top.HEURIST.util.isempty(curr_link)){ //was open and closed without any change
  		return false;
  }else{
  		var src = document.getElementById("cbSource").value;

  		var mt = document.getElementById("cbType").value;
  		if(mt=="unknown") mt = "";

  		if(src!=='heurist' && !top.HEURIST.util.isempty(curr_link)){
			 return (
			 _filedata.remoteURL != curr_link ||
			 _filedata.remoteSource != src ||
			 _filedata.mediaType != mt);
			 //(top.HEURIST.util.isnullcurr_ext && _filedata.ext != curr_ext));
		}else{
			return (_filedata.mediaType != mt);
		}
  }
  return false;
}

function onApply(){
/*
	if(top.HEURIST.util.isempty(_filedata)){ //new

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

	if(_filedata.remoteSource=='heurist' &&  !top.HEURIST.util.isempty(fileId) ){  //!!!!!
		//take id from
		_filedata.id = fileId;
	}
*/

	var wasChanged = isChanged();

	if(_fileuploaded){
		_filedata = fileUploadInput.getFileData();
	}

	var src = document.getElementById("cbSource").value;

	if(top.HEURIST.util.isempty(_filedata)){ //new
		_filedata = {id:0,
					remoteURL: (src=='heurist')?null:curr_link,
					ext:curr_ext,
					remoteSource: src,
					mediaType:document.getElementById("cbType").value };
	}else{
		_filedata.remoteURL = (src=='heurist')?null:curr_link;
		_filedata.remoteSource = src;
		_filedata.mediaType = document.getElementById("cbType").value;
		if(curr_ext) _filedata.ext = curr_ext;
	}

	var res = top.YAHOO.lang.JSON.stringify(_filedata);
	window.close( wasChanged, res );//curr_link, document.getElementById("cbSource").value, document.getElementById("cbType").value);
}

function onCancel(){
	window.close( isChanged(), null);
}
