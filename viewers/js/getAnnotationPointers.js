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
* brief description of file
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
*/


function findSelectionAddressInDocs (docList) {
	if (typeof $.fn.hasParent === 'undefined'){
		$.fn.hasParent = function(objs) {
			// ensure that objs is a jQuery array
			objs = $(objs); var found = false;
			$(this[0]).parents().andSelf().each(function() {
				if ($.inArray(this, objs) != -1) {
					found = true;
					return false; // stops the each...
				}
			});
			return found;
		}
	}
	var sel = window.getSelection();
	var startNode = sel.getRangeAt(0).startContainer;
	for ( var i in docList){
		var docContainer = docList[i];
		if ($(startNode).hasParent($(docContainer))){
			return { 'docElem': docContainer, 'addr': getSelectionAddress(docContainer)};
		}
	}
	return null;
}
// IE not supported, for now
function getSelectionAddress (root) {
	var sel = window.getSelection();

	if (! sel  ||  sel.isCollapsed  ||  ! sel.containsNode(root, true)) {
		return null;
	}

	var range = sel.getRangeAt(0);

/*
	// sometimes selections start outside the spans!
	if (range.startContainer.nodeName == "#text"  &&
		range.startContainer.parentNode.nodeName == "P"  &&
		range.startContainer.nextSibling  &&
		range.startContainer.nextSibling.firstChild)
		range.setStart(range.startContainer.nextSibling.firstChild, 0);
	if (range.endContainer.nodeName == "#text"  &&
		range.endContainer.parentNode.nodeName == "P"  &&
		range.endContainer.previousSibling)
		range.setEnd(range.endContainer.previousSibling.lastChild, range.endContainer.previousSibling.lastChild.textContent.length);
*/

//	if (range.endContainer.parentNode.nodeName == "P"  &&  range.endContainer.previousSibling)
//		endNode = range.endContainer.nextSibling;

	var startNode = range.startContainer;
	var endNode = range.endContainer;
	var selectedText = range.cloneContents().textContent;
	// startContainer and endContainer could be text nodes
	if (startNode.nodeName == "#text")
		startNode = startNode.parentNode;
	if (endNode.nodeName == "#text")
		endNode = endNode.parentNode;

	// jump up out of links that were inserted by the highlight script
	while (endNode.nodeName == "A"  &&  endNode.className.match(/\bannotation\b/))
		endNode = endNode.parentNode;
	while (startNode.nodeName == "A"  &&  startNode.className.match(/\bannotation\b/))
		startNode = startNode.parentNode;


	var startAddr, endAddr;
	[ startAddr, endAddr ] = findSelection(root, startNode, endNode);

	// use the range's startContainer and endContainer to determine word offset
	var startWord = findWordOffset(range.startContainer.textContent, range.startOffset, true);
	var endWord = findWordOffset(range.endContainer.textContent, range.endOffset, false);

	// handle selection in elements that have already been split
	// by existing references: count back and add words in preceding siblings
	startWord += offsetCorrection(range.startContainer);
	if (!top.jQuery.contains(range.endContainer,range.startContainer)){//added  SAW  for miscount when end contains
		endWord += offsetCorrection(range.endContainer);
	}

	var retStruct = { "startElems": startAddr, "endElems": endAddr, "startWord": startWord, "endWord": endWord, "text": selectedText};
	if ($(root).attr("recID")){
		retStruct["recID"] = $(root).attr("recID");
	}
	return retStruct;
}

function findSelection (node, startNode, endNode) {
	// find child elements
	var children = [];
	for (var i = 0; i < node.childNodes.length; ++i) {
		var childNode = node.childNodes[i];
		if (childNode.nodeType === Node.ELEMENT_NODE && childNode.nodeName != "BR") {		// skip non-elements - SAW 25/06/12 need to skip br elements
			if (! (childNode.nodeName === "A"  &&  childNode.className.match(/\bannotation\b/)) ) {	// skip annotation links
				children.push(childNode);
			}
		}
	}

	var startAddr = [];
	var endAddr = [];

	if (node == startNode) {
		startAddr = true;
	}
	if (node == endNode) {
		endAddr = true;
	}

	for (var i = 0; i < children.length; ++i) {
		var rv = findSelection(children[i], startNode, endNode);

		if (rv[0] === true)
			startAddr = [i+1];
		else if (rv[0].length)
			startAddr = [i+1].concat(rv[0]);

		if (rv[1] === true)
			endAddr = [i+1];
		else if (rv[1].length)
			endAddr = [i+1].concat(rv[1]);
	}

	return [ startAddr, endAddr ];
}

function findWordOffset(text, offset, start) {

	// correct for leading whitespace
	var matches = text.match(/^\s*/m);
	var leadingSpace = matches[0];
	text = text.slice(leadingSpace.length);
	offset -= leadingSpace.length;
	if (offset < 0) {
		offset = 0;
	}

	// correct for trailing whitespace
	text = text.replace(/\s*$/, "");
	if (offset >= text.length) {
		offset = text.length;
	}

	var w = 1;
	for (var i = 0; i < text.length; ++i) {
		if (start) {
			if (i > 0  &&  text[i-1].match(/[^\s]/)  &&  text[i].match(/\s/)) {		// transition out of a word
				++w;
			}
		} else {
			if (i > 1  &&  text[i-2].match(/\s/)  &&  text[i-1].match(/[^\s]/)) {     // transition into a word
				++w;
			}
		}
		if (i === offset) return w;
	}
	return w;
}

function offsetCorrection(elem) {
	var e = elem;
	var correction = 0;

	// jump up out of annotations
	if (e.nodeName === "#text"  &&  e.parentNode.nodeName === "A"  &&  e.parentNode.className.match(/\bannotation\b/)) {
		e = e.parentNode;
	}

	while (e.previousSibling) {
		e = e.previousSibling;
		if(e.nodeName == "BR"){
				continue;
		}
		if (! (e.nodeName === "A"  &&  e.className.match(/\bannotation\b/)  &&  e.className.match(/\bsuperscript\b/)) ) {
			if (! e.textContent.match(/^\s+$/)) {
				var text = e.textContent.replace(/^\s+/, "").replace(/\s+$/, "");
				correction += text.split(/\s+/).length;
			}
		}
	}
	return correction;
}
