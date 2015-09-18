/*
* Copyright (C) 2005-2015 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


if(top && top.HEURIST.edit){

top.HEURIST.edit.inputs.BibDetailURLincludeInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.focus = function() { this.inputs[0].textElt.focus(); };
//top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.regex = new RegExp("^[1-9]\\d*$");
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.typeDescription = "a url to be included";

/*
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.autoSize = function(textElt){

		var o = {
		maxWidth: 1000,
		minWidth: 200,
		comfortZone: 10};

		var input = $(textElt);

		//var d = this.document.getElementById("testerwidth");
		if($("#testerwidth").length == 0){
			$('<div id="testerwidth"></div>').appendTo('body')
		}

		var testSubject = $('#testerwidth').css({
						position: 'absolute',
						top: -9999,
						left: -9999,
						width: 'auto',
						fontSize: input.css('fontSize'),
						fontFamily: input.css('fontFamily'),
						fontWeight: input.css('fontWeight'),
						letterSpacing: input.css('letterSpacing'),
						whiteSpace: 'nowrap'
					});

		// Enter new content into testSubject
		var escaped = textElt.value.replace(/&/g, '&amp;').replace(/\s/g,'&nbsp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		testSubject.html(escaped);

		// Calculate new width + whether to change
		var testerWidth = testSubject.width(),
		newWidth = (testerWidth + o.comfortZone) >= o.minWidth ? testerWidth + o.comfortZone : o.minWidth,
		currentWidth = input.width(),
		isValidWidthChange = (newWidth < currentWidth && newWidth >= o.minWidth)
												|| (newWidth > o.minWidth && newWidth < o.maxWidth);

		// Animate width
		if (isValidWidthChange) {
				input.width(newWidth);
		}
};
*/

/**
* creates visible input to display file name or URL
* and invisible to keep real value - either file ID (for uploaded) or URL|source|type for remote resources
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.addInput = function(bdValue) {


	var thisRef = this;	// provide input reference for closures

	var newDiv = this.document.createElement("div");
		newDiv.expando = true;
		newDiv.bdValue = null;
	this.addInputHelper.call(this, bdValue, newDiv);

	if(this.promptDiv.innerHTML){
		/* in chrome it give a huge gap - issue was fixed with edit.css
		var br = this.document.createElement("br");
		br.style.lineHeight = "2px";
		newDiv.parentNode.insertBefore(br, this.promptDiv);*/
	}

	var valueVisible = "";
	var valueHidden = "";
	var thumbUrl = top.HEURIST.basePath+"common/images/icon_file.jpg";

	if(bdValue){
		if(bdValue.file){
			//new way
			valueHidden = JSON.stringify(bdValue.file); // YAHOO.lang.

			if(bdValue.file.remoteURL){
				//remote resource
				valueVisible = bdValue.file.remoteURL;
				//newDiv.bdValue = valueHidden + '|' + bdValue.file.remoteURL+'|'+bdValue.file.remoteSource+'|'+bdValue.file.mediaType;
			}else{
				//local uploaded file or file on server
				valueVisible = bdValue.file.origName;
				//newDiv.bdValue =  valueHidden + '|'+ bdValue.file.URL+'|heurist|'+(bdValue.file.mediaType?bdValue.file.mediaType:'')+'|'+bdValue.file.ext;
			}

			if(bdValue.file.thumbURL){
				thumbUrl = bdValue.file.thumbURL;
			}

		}else{
			/* old way for real urlinclude - @todo remove
			valueVisible = bdValue.value;
			var arr = valueVisible.split("|");
			if(arr && arr.length>0){
				valueVisible = arr[0]; //url only
			}
			valueHidden = (bdValue.value)?bdValue.value:"";
			*/
		}
	}

	newDiv.className = top.HEURIST.util.isempty(valueVisible)? "file-resource-div empty" : "file-resource-div";


	var thumbDiv = this.document.createElement("div");
		thumbDiv.className = "thumbPopup";
		thumbDiv.style.backgroundImage = "url("+thumbUrl+")";
		thumbDiv.style.display = "none";
		newDiv.appendChild(thumbDiv);

	var hiddenElt = newDiv.hiddenElt = this.document.createElement("input");
		hiddenElt.name = newDiv.name;
		hiddenElt.value = hiddenElt.defaultValue = valueHidden;
		hiddenElt.type = "hidden";
		newDiv.appendChild(hiddenElt);

	var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
		textElt.type = "text";
		textElt.title = "Click here to upload file or define the URL";
		textElt.setAttribute("autocomplete", "off");
		textElt.className = "resource-title";
		//textElt.className = "in"; //"resource-title";
		//textElt.style.width = 500;
		textElt.onmouseover = function(e){
			if(top.HEURIST && !top.HEURIST.util.isempty(textElt.value)){
				thumbDiv.style.display = "block";
			}
		}
		textElt.onmouseout = function(e){
			thumbDiv.style.display = "none";
		}

		textElt.onkeypress = function(e) {
			// refuse non-tab key-input
			if (! e) e = window.event;

			if (! newDiv.readOnly  &&  e.keyCode != 9  &&  ! (e.ctrlKey  ||  e.altKey  ||  e.metaKey)) {
				// invoke popup
				thisRef.defineURL(newDiv);
				return false;
			}
			else return true;	// allow tab or control/alt etc to do their normal thing (cycle through controls)
		};

		textElt.value = textElt.defaultValue = valueVisible;

		var maxWidth = this.parentElement.width;
		if(!maxWidth || maxWidth>500){
				maxWidth = 500;
		}

		top.HEURIST.util.autoSize(textElt, {maxWidth:maxWidth});

		/*$('input#'+textElt.id).autoGrowInput({
    		comfortZone: 50,
    		minWidth: 20,
    		maxWidth: '90%',
    		id:'#'+textElt.id
		});*/


	top.HEURIST.registerEvent(textElt, "click", function() { thisRef.defineURL(newDiv); });
	top.HEURIST.registerEvent(textElt, "mouseup", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });
	top.HEURIST.registerEvent(textElt, "mouseover", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });


	var removeImg = newDiv.appendChild(this.document.createElement("img"));
		removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
		removeImg.className = "delete-resource";
		removeImg.title = "Clear";
		var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
		top.HEURIST.registerEvent(removeImg, "click", function() {
			if (! newDiv.readOnly) {
				thisRef.clearURL(newDiv);
				if (windowRef.changed) windowRef.changed();
			}
		});

/*
	var editImg = newDiv.appendChild(this.document.createElement("img"));
		editImg.src = top.HEURIST.basePath +"common/images/edit-pencil.png";
		editImg.className = "edit-resource";
		editImg.title = "Edit this record";

	top.HEURIST.registerEvent(editImg, "click", function() {
		top.HEURIST.util.popupURL(window,top.HEURIST.basePath +"records/edit/formEditRecordPopup.html?recID=" + hiddenElt.value, {
			callback: function(bibTitle) { if (bibTitle) textElt.defaultValue = textElt.value = bibTitle; }
		});
	});
*/

	/* ??????????
	if (window.HEURIST && window.HEURIST.parameters && window.HEURIST.parameters["title"]  &&  bdValue  &&  bdValue.title  &&  windowRef.parent.frameElement) {
		// we've been given a search string for a record pointer field - pop up the search box
		top.HEURIST.registerEvent(windowRef.parent.frameElement, "heurist-finished-loading-popup", function() {
			thisRef.defineURL(newDiv, bdValue.title);
		});
	}*/
};
/**
*  returns the value - ulf_ID or combined URL|source|type
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.getPrimaryValue = function(input) { return input? input.hiddenElt.value : ""; };

/**
* Open popup - to upload file and specify URL
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.defineURL = function(element, editValue) {

	var thisRef = this;
	var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

	if (!editValue) {
		editValue = element.hiddenElt.value; //json string with bdValue.file
	}
	var recID = "";
	if(top.HEURIST.edit.record && top.HEURIST.edit.record.bibID){
		recID = "&recid="+top.HEURIST.edit.record.bibID;
	}

	var url = top.HEURIST.basePath+"records/files/uploadFileOrDefineURL.html?value="+encodeURIComponent(editValue)+recID+"&db="+_db;
	/*if (element.input.constrainrectype){
		url += "&t="+element.input.constrainrectype;
	}*/

	top.HEURIST.util.popupURL(window, url, {
		height: 480,
		width: 640,
		"no-close": true,
		callback: function(isChanged, fileJSON) {

			if(isChanged){

				if(top.HEURIST.util.isnull(fileJSON)){
					var r = confirm('You uploaded/changed the file data. If you continue, all changes will be lost.');
					return r;
				}else{
					//var filedata = (typeof fileJSON == 'String')?top.HEURIST.util.expandJsonStructure(fileJSON) :fileJSON;
					var filedata = top.HEURIST.util.expandJsonStructure(fileJSON);
					if(filedata){
						element.input.setURL(element, ((filedata.remoteSource=='heurist')?filedata.origName:filedata.remoteURL), fileJSON);
					}
				}

			}
			var helpDiv = document.getElementById("help-link");
			if(helpDiv) {
				top.HEURIST.util.setHelpDiv(helpDiv,null);
			}

			return true; //prevent close


/*
			//it returns url - link to external or heurist file
			//			source - name of source/service
			//			type - type of media
			if(top.HEURIST.util.isempty(url)){
				//element.input.setURL(element, "", "");
			}else{
				element.input.setURL(element, url, url+'|'+source+"|"+type);
			}
*/
		}
	} );
};

/**
* clear value
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.clearURL = function(element) { this.setURL(element, "", ""); };

/**
* assign new URL value - returns from uploadFileOrDefineURL
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.setURL = function(element, visibleValue, hiddenValue) {

	element.textElt.value = element.textElt.defaultValue = top.HEURIST.util.isempty(visibleValue)? "" :visibleValue;

	if (top.HEURIST.util.isempty(visibleValue)) {
		element.className += " empty";
	} else if (! element.className.match(/(^|\s+)empty(\s+|$)/)) {
		element.className = element.className.replace(/(^|\s+)empty(\s+|$)/g, "");
	}

	top.HEURIST.util.autoSize(element.textElt, {});

	element.hiddenElt.value = element.hiddenElt.defaultValue = top.HEURIST.util.isempty(hiddenValue)? "" :hiddenValue;

	element.className = top.HEURIST.util.isempty(hiddenValue)?"file-resource-div empty":"file-resource-div";

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	if (windowRef.changed) windowRef.changed();
};

/**
*  TODO - to implement
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.handlePossibleDragDrop = function(input, element) {
	/*
	 * Invoked by the mouseup property on resource textboxes.
	 * We can't reliably detect a drag-drop action, but this is our best bet:
	 * if the mouse is released over the textbox and the value is different from what it *was*,
	 * then automatically popup the search-for-resource box.

	if (element.textElt.value != element.textElt.defaultValue  &&  element.textElt.value != "") {
		var searchValue = this.calculateDroppedText(element.textElt.defaultValue, element.textElt.value);

		// pause, then clear search value
		setTimeout(function() { element.textElt.value = element.textElt.defaultValue; }, 1000);

		element.input.defineURL(element, searchValue);
	}
	 */
};

/**
*  TODO - to implement
*/
top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.calculateDroppedText = function(oldValue, newValue) {
/*
	// If a value is dropped onto a resource-pointer field which already has a value,
	// the string may be inserted into the middle of the existing string.
	// Given the old value and the new value we can determine the dropped value.
	if (oldValue == "") return newValue;

	// Compare the values character-by-character to find the longest shared prefix
	for (var i=0; i < oldValue.length; ++i) {
		if (oldValue.charAt(i) != newValue.charAt(i)) break;
	}

	// simple cases:
	if (i == oldValue.length) {
		// the input string was dropped at the end
		return newValue.substring(i);
	}
	else if (i == 0) {
		// the input string was dropped at the start
		return newValue.substring(0, newValue.length-oldValue.length);
	}
	else {
		// If we have ABC becoming ABXYBC,
		// then the dropped string could be XYB or BXY.
		// No way to tell the difference -- we always return the former.
		return newValue.substring(i, i + newValue.length-oldValue.length);
	}
*/
};

}