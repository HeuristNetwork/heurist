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
* Very rudimentary autocomplete box -- attach it to a textbox input;
* instantiate it with
* new top.HEURIST.autocomplete.AutoComplete( textbox , dataFunction , options )
*
* July 2008 - made Heurist-independent (KJ)
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


if (!top.HEURIST) {
	top.HEURIST = {};
}

// ripped from heurist.js
top.HEURIST.registerEvent = function(element, eventType, fn, capture, priority) {
	if (window.addEventListener) {
		element.addEventListener(eventType, fn, capture);
	} else if (window.attachEvent) {
		element.attachEvent("on" + eventType, fn);
	} else {
		/* nothing we can do, really, without [possibly] clobbering an existing handler */
	}
};


// ripped from heurist.js
top.HEURIST.getPosition = function(element, absolute) {
	// should really be in util, but it is very much part of the standard toolkit
	var x = 0, y = 0;
	var _element = element;
	while (element) {
		x += element.offsetLeft;
		y += element.offsetTop;
		element = element.offsetParent;
	}

	// We are getting a position relative to the TOP window
	if (absolute) {
		while (_element.nodeType != 9) _element = _element.parentNode;
		var documentRef = _element;

		var windowRef = (documentRef.parentWindow  ||  documentRef.defaultView);
		if (windowRef &&  windowRef.frameElement) {
			var windowPos = top.HEURIST.getPosition(windowRef.frameElement, true);

			var scrollLeft = windowRef.document.body.scrollLeft || windowRef.document.documentElement.scrollLeft;
			var scrollTop = windowRef.document.body.scrollTop || windowRef.document.documentElement.scrollTop;
			x += windowPos.x - scrollLeft;
			y += windowPos.y - scrollTop;
		}
	}

	return { "x": x, "y": y };
};

top.HEURIST.autocomplete = {};
var sE = top.HEURIST.autocomplete.soundEmbed = document.createElement("embed");
	sE.style.position = "absolute";
	sE.style.width = "1px";
	sE.style.height = "1px";
	sE.style.left = "-1000px";
	sE.style.top = "-1000px";
	sE.src = "../../common/media/beep.swf";
	document.body.appendChild(sE);
top.HEURIST.autocomplete.beep = function() {
	try {
		// ooh, fancy.  Try using flash to make a tiny little beep
		var sE = top.HEURIST.autocomplete.soundEmbed;
		sE.TGotoLabel("/mygtalkmc", "start");
		sE.TPlay("/mygtalkmc");
	} catch (e) {}
};

top.HEURIST.autocomplete.AutoComplete = function(textbox, dataFunction, options) {
	if (! (textbox  &&  dataFunction)) throw "AutoComplete constructor requires an input field and a dataFunction";
	var thisRef = this;

	this.textbox = textbox;
		this.textbox.setAttribute("autocomplete", "off");	// nb: not FALSE -- this works on IE and firefox 3+
		this.textbox.expando = true;
		this.textbox.autocompleteObject = this;

	if (options && options.prompt) {
		this.emptyPrompt = options.prompt;
		this.textbox.title = options.prompt;
		if (! this.textbox.value) {
			this.textbox.value = options.prompt;
			this.textbox.onfocus = function() { textbox.value = ""; textbox.onfocus = null; };
		}
	}

	if (options && options.nonVocabularyCallback) {
		this.nonVocabularyCallback = options.nonVocabularyCallback;
	}

	this.dataFunction = dataFunction;

	var elt = textbox;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	this.completionsIframe = this.document.createElement("iframe");
		this.completionsIframe.style.position = "absolute";
		this.completionsIframe.style.display = "none";
		this.completionsIframe.frameBorder = 0;
	textbox.parentNode.appendChild(this.completionsIframe);

	this.completions = this.document.createElement("div");
		this.completions.className = "autocomplete";
		this.completions.id = "autocomplete-output";
		this.completions.style.position = "absolute";	// important one
		this.completions.style.display = "none";
		this.completions.expando = true;
		this.completions.autocompleteObject = this;
	textbox.parentNode.appendChild(this.completions);

	if (! options  ||  options.multiWord !== false) {
		this.multiWord = true;
		if (options && options.delimiter) {
			this.delimiter = options.delimiter;
		} else {
			this.delimiter = ",";
		}
	} else this.multiWord = false;

	if (options  &&  options.delay) {
		this.delay = parseFloat(options.delay);
	} else {
		this.delay = 0;
	}

	top.HEURIST.registerEvent(this.textbox, "keypress", this.textbox_onkeypress);
	top.HEURIST.registerEvent(this.textbox, "blur", this.textbox_onblur);
	top.HEURIST.registerEvent(this.textbox, "mousedown", function() { thisRef.collapse() });	// hide options while mousing textbox
	top.HEURIST.registerEvent(this.textbox, "click", this.textbox_onclick);

	this.currentWord = -1;
	this.currentWordValue = null;

	this.options = [];
	this.selectedIndex = -1;
	this.expanded = false;
};

top.HEURIST.autocomplete.AutoComplete.prototype.stopEvent = function(e) {
	if (e.stopPropagation) {
		e.stopPropagation();
	} else {
		e.cancelBubble = true;
	}
	if (e.preventDefault) {
		e.preventDefault();
	} else {
		e.returnValue = false;
	}
};

top.HEURIST.autocomplete.AutoComplete.prototype.textbox_onblur = function(e) {
	if (! e) e = window.event;
	var targ = e.target;  if (! targ) targ = e.srcElement;

	var acObject = targ.autocompleteObject;
	if (! acObject) throw "couldn't find autocomplete object";

	acObject.collapse();
	if (! acObject.currentWordOkay()) {
		acObject.stopEvent(e);
		return false;
	}
};

top.HEURIST.autocomplete.AutoComplete.prototype.textbox_onkeypress = function(e) {
	if (! e) e = window.event;
	var targ = e.target;  if (! targ) targ = e.srcElement;

	var acObject = targ.autocompleteObject;
	if (acObject && acObject.frozen) {
		top.HEURIST.autocomplete.beep();
		acObject.stopEvent(e);
		return false;
	}

	var code = e.keyCode || e.which;
	switch (code) {
	  case 38: // up
		if (acObject.expanded) {
			// Highlight the previous option
			if (acObject.selectedIndex == -1) acObject.selectedIndex = acObject.options.length-1;
			else acObject.selectedIndex = (acObject.selectedIndex - 1 + acObject.options.length) % acObject.options.length;
			acObject.updateSelection();
		} else {
			// Show options
			acObject.expand();
		}
		break;

	  case 40: // down
		if (acObject.expanded) {
			// Highlight the next option
			acObject.selectedIndex = (acObject.selectedIndex + 1) % acObject.options.length;
			acObject.updateSelection();
		} else {
			// Show options
			acObject.expand();
		}
		break;

	  case 10: case 13: // enter
		if (acObject.expanded) {
			// Choose the current autocomplete option
			acObject.chooseCurrentSelection();
			acObject.collapse();
			break;
		}
		return;	// allow this to have its usual effect ... allows submission of forms, for database.
		// break;

	  case 27: // esc
		if (acObject.expanded) {
			// Hide options
			acObject.collapse();
		}
		// Firefox Mac (inter alia?) refuses to cancel the escape key, and resets the value
		setTimeout(function() { acObject.textbox.value = acObject.textbox.defaultValue; }, 0);
		break;

	  case 9: // tab
		// Cycle between words (separated by the delimiter character) in the text input
		if (! (acObject.multiWord && acObject.nextTextboxWord(e.shiftKey))) return;
		break;

		// Move left or right in the textbox -- might change which word we are autocompleting
	  case 37: // left
		if (! (acObject.multiWord && acObject.checkTextboxWord(-1))) return;
		break;

	  case 39: // right
		if (! (acObject.multiWord && acObject.checkTextboxWord(1))) return;
		break;

	  case 92:	// backslash
		acObject.textbox.style.borderColor = acObject.textbox.style.outlineColor = "red";
		setTimeout(function() { acObject.textbox.style.borderColor = acObject.textbox.style.outlineColor = ""; }, 100);
		acObject.stopEvent(e);
		return false;
		break;

	  default:
		setTimeout(function() { acObject.textbox.defaultValue = acObject.textbox.value; }, 0);
		if (acObject.nonVocabularyCallback) acObject.nonVocabularyCallback.call(acObject, "");

		if (acObject.updateTimeoutID) {
			clearTimeout(acObject.updateTimeoutID);
		}
		acObject.updateTimeoutID = setTimeout(
			function() {
				acObject.checkTextboxWord(0);
				acObject.updateOptions();
				if (acObject.options.length  &&  ! acObject.expanded) acObject.expand();
			}, acObject.delay*1000);
		return;
	}
	acObject.updateTimeoutID = 0;

	// Prevent the default action of this keypress
	acObject.stopEvent(e);
};


top.HEURIST.autocomplete.AutoComplete.prototype.textbox_onclick = function(e) {
	if (! e) e = window.event;
	var targ = e.target;  if (! targ) targ = e.srcElement;

	var acObject = targ.autocompleteObject;
	if (acObject && acObject.frozen) return false;

	if (! acObject.checkTextboxWord(0)) acObject.stopEvent(e);
};


top.HEURIST.autocomplete.AutoComplete.prototype.getTextboxWordInfo = function(adj) {
	// selectionStart gives us the current caret position (adjust by the given parameter)
	var sStart = this.textbox.selectionStart + adj;

	var tValue = this.textbox.value;
	var magic = tValue.match(
		new RegExp("^(.{0,"+(sStart-1)+"}"+this.delimiter+"|)([^"+this.delimiter+"]*)(.|$)")
	);

	return {
		nextWordOffset: magic[0].length,
		currentWordOffset: magic[1].length,
		currentWordLength: magic[2].length,
		currentWordIsLastWord: (magic[3].length == 0)
	};
};
top.HEURIST.autocomplete.AutoComplete.prototype.getSelectionInfo = function() {
	if (selectionStart in this.textbox) {
		return { selectionStart: this.textbox.selectionStart, selectionEnd: this.textbox.selectionEnd };
	}
	else if (document.selection) {
		// INTERNET EXPLORER ... a hack to get the selection extent
		this.textbox.focus();
		var origValue = this.textbox.value;
		var range = document.selection.createRange();

		// find a text string which isn't in the text value
		var caret = "^";
		while (origValue.indexOf(caret) >= 0)
			caret += "^";
		caret = "*" + caret + "*";

		range.text = caret;
		var selectionStart = this.textbox.value.indexOf(caret);
		var selectionEnd = selectionStart + (origValue.length - (this.textbox.value.length - caret.length));

		return { selectionStart: selectionStart, selectionEnd: selectionEnd };
	}
};


top.HEURIST.autocomplete.AutoComplete.prototype.nextTextboxWord = function(goBackwards) {
	// can't do anything useful in this function unless we can set the selection range
	if (! this.multiWord) return false;
	if (! this.textbox.setSelectionRange  &&  ! this.textbox.createTextRange) return false;

	var tValue = this.textbox.value;
	var info = this.getTextboxWordInfo(0);

	if ((! goBackwards  &&  info.currentWordIsLastWord) || (goBackwards  &&  info.currentWordOffset == 0)) {
		// if we are tabbing past the last word (or back-tabbing past the first word)
		// then don't tab between words ... let the normal inter-element tabbing take place
		return false;
	}

	var newStart, newEnd;
	if (! goBackwards) {	// scan forwards (go to next word)
		newStart = (info.currentWordIsLastWord)? 0 : info.nextWordOffset;

		var nextDelimiterPos = tValue.indexOf(this.delimiter, newStart);
		newEnd = (nextDelimiterPos > 0)? nextDelimiterPos : tValue.length;

	} else {	// scan backwards (go to previous word)
		var newEnd = (info.currentWordOffset == 0)? tValue.length : (info.currentWordOffset-1);

		var previousDelimiterPos = tValue.lastIndexOf(this.delimiter, newEnd-1);
		newStart = previousDelimiterPos + 1;
	}

	this.setTextboxRange(newStart, newEnd);

	this.currentWord = tValue.substring(0, newStart).replace(/[^,]*/g, '').length;
	this.currentWordValue = tValue.substring(newStart, newEnd);
	this.selectedIndex = -1;
	this.updateOptions();
	this.expand();	// this actually works pretty well ... tab to a new word, see the options.  Fantastic!

	return true;
};

top.HEURIST.autocomplete.AutoComplete.prototype.checkTextboxWord = function(move) {
	// Check if the current position of the cursor (or directly left of it if move == -1, directly right of it if move == 1)
	// lies in a DIFFERENT WORD from the previously known position.
	// If we're in a different word then we need to update autocomplete suggestions etc.
	if (! this.multiWord) {
		this.currentWordValue = this.textbox.value;
		return;
	}

	if (this.textbox.selectionStart !== this.textbox.selectionEnd) {
		// we have a block of text selected -- do system default
		return false;
	}

	var tValue = this.textbox.value;
	var info = this.getTextboxWordInfo(move);

// count how many words there are before this one
	var newCurrentWord = tValue.substring(0, info.currentWordOffset).replace(new RegExp(this.delimiter, "g"), '').length;

	var newStart = info.currentWordOffset;
	var newEnd = info.currentWordOffset + info.currentWordLength;

	if (this.currentWord != newCurrentWord) {
		if (! this.currentWordOkay()) return true;
		this.currentWordValue = tValue.substring(newStart, newEnd);

		// We have moved into a new word!  Highlight that word
		if (move) this.setTextboxRange(newStart, newEnd);

		this.currentWord = newCurrentWord;
		this.selectedIndex = -1;
		if (this.expanded) {
			this.expand();
		}
		return true;
	}
	this.currentWordValue = tValue.substring(newStart, newEnd);

	if (this.expanded) this.collapse();

	return false;
};

top.HEURIST.autocomplete.AutoComplete.prototype.setTextboxRange = function(start, end) {
	if (this.textbox.setSelectionRange) {
		this.textbox.setSelectionRange(start, end);
	} else if (this.textbox.createTextRange) {
		var range = this.textbox.createTextRange();
		range.moveStart("character", start);
		range.moveEnd("character", end-this.textbox.value.length);
		range.select();
	}
};


top.HEURIST.autocomplete.AutoComplete.prototype.expand = function() {
	// make sure we have the latest greatest options
	this.updateOptions();
	if (this.options.length == 0) {
		this.collapse();
		return;
	}

	var textboxPosition = top.HEURIST.getPosition(this.textbox);

	if (this.multiWord) {
		/* calculate a plausible left-offset for the given word */
		var info = this.getTextboxWordInfo(0);
		var textBeforeWord = this.textbox.value.substring(0, info.currentWordOffset);
		var invisibleTextbox = this.document.createElement("span");
			invisibleTextbox.className = "invisibleTextbox";
			invisibleTextbox.appendChild(this.document.createTextNode(textBeforeWord));
			this.document.body.appendChild(invisibleTextbox);
		var calculatedPosition = textboxPosition.x + invisibleTextbox.offsetWidth - 4;
			this.document.body.removeChild(invisibleTextbox);

		/* calculate the width of the textbox minus the width of the output box -- gives a rightmost position */
		this.completionsIframe.style.display = "block";
		this.completions.style.display = "block";
		var rightmostPosition = textboxPosition.x + this.textbox.offsetWidth - this.completions.offsetWidth;

		this.completions.style.left = this.completionsIframe.style.left = (Math.max(textboxPosition.x, Math.min(calculatedPosition, rightmostPosition))-160) + "px";
	}
	else {
		this.completionsIframe.style.display = "block";
		this.completions.style.display = "block";
		this.completions.style.left = this.completionsIframe.style.left = textboxPosition.x + "px";
	}
	this.completions.style.top = this.completionsIframe.style.top = textboxPosition.y + (this.textbox.offsetHeight - 90) + "px";
	this.completionsIframe.style.width = this.completions.offsetWidth + "px";
	this.completionsIframe.style.height = this.completions.offsetHeight + "px";

	this.expanded = true;
};

top.HEURIST.autocomplete.AutoComplete.prototype.collapse = function() {
	this.completionsIframe.style.display = "none";
	this.completions.style.display = "none";

	this.expanded = false;
};


top.HEURIST.autocomplete.AutoComplete.prototype.updateSelection = function() {
	for (var i=0; i < this.options.length; ++i) {
		if (i == this.selectedIndex) {
			this.options[i].div.className = "option selected";
		} else {
			this.options[i].div.className = "option";
		}
	}
};


top.HEURIST.autocomplete.AutoComplete.prototype.setOptions = function(values) {
	var currentSelection;
	if (this.selectedIndex !== -1) {
		currentSelection = this.options[this.selectedIndex].value;
	}

	// hard limit of ten options
	var newOptions = [];
	var newSelectedIndex = -1;
	for (var i=0; i < values.length  &&  i < 10; ++i) {
		var newOption = { value: values[i],
		                  div: this.document.createElement("div") };
		newOption.div.appendChild(this.document.createTextNode(values[i]));
		newOption.div.className = "option";
		newOption.div.title = values[i];
		newOption.div.expando = true;
		newOption.div.autocompleteObject = this;
		newOption.div.optionIndex = i;
		top.HEURIST.registerEvent(newOption.div, "mousedown", this.option_onmousedown);

		if (values[i] == currentSelection  &&  newSelectedIndex == -1) {
			newOption.div.className = "option selected";
			newSelectedIndex = i;
		}

		this.completions.appendChild(newOption.div);
		newOptions.push(newOption);
	}
	this.selectedIndex = newSelectedIndex;

	for (var i=0; i < this.options.length; ++i) {
		this.completions.removeChild(this.options[i].div);
	}

	this.options = newOptions;
	if (this.options.length == 0) {
		/* don't bother showing if there are no options */
		this.collapse();
	}
};


top.HEURIST.autocomplete.AutoComplete.prototype.chooseCurrentSelection = function() {
	// replace current word with the current selection
	if (this.selectedIndex == -1  ||  ! this.options[this.selectedIndex]) return;
	var newWord = this.options[this.selectedIndex].value;

	if (this.multiWord) {
		var sStart = this.textbox.selectionStart;

		var words = this.textbox.value.split(this.delimiter);
		var aggregLength = 0;
		var currentWord = -1;
		for (var i=0; i < words.length; ++i) {
			if (aggregLength <= sStart  &&  sStart <= (aggregLength+words[i].length)) {
				currentWord = i;
				break;
			}
			aggregLength += words[i].length + 1;
		}
		if (currentWord == -1) return false;	// couldn't find the word (shouldn't happen)

		var wordStart = aggregLength;
		var wordEnd = wordStart + words[currentWord].length;

		// replace the current selection with our new word
		this.textbox.value = this.textbox.defaultValue = this.textbox.value.substring(0, wordStart) + newWord + this.textbox.value.substring(wordEnd);
	}
	else {	// single-word mode
		var wordStart = 0;
		this.textbox.value = this.options[this.selectedIndex].value;
	}

	if (this.textbox.onchange) this.textbox.onchange();
	//this.setTextboxRange(wordStart, wordStart + newWord.length);
	this.setTextboxRange(wordStart + newWord.length, wordStart + newWord.length);

	this.currentWordValue = newWord;
	this.updateOptions();
};


top.HEURIST.autocomplete.AutoComplete.prototype.option_onmousedown = function(e) {
	if (! e) e = window.event;
	var targ = e.target;  if (! targ) targ = e.srcElement;

	var acObject = targ.autocompleteObject;
	if (! acObject) throw "couldn't find autocomplete object";

	acObject.selectedIndex = targ.optionIndex;
	acObject.updateSelection();
	acObject.chooseCurrentSelection();
	acObject.collapse();

	acObject.stopEvent(e);	// prevent blur event on textbox
};


top.HEURIST.autocomplete.AutoComplete.prototype.updateOptions = function() {
	// find the options for the current word

	if (this.currentWordValue === null) {
		this.setOptions([]);
		this.selectedIndex = -1;
		return;
	}

	var options = this.dataFunction(this.currentWordValue.toLowerCase().replace(/^\s+|\s+$/g, ''));
	this.setOptions(options);
};

top.HEURIST.autocomplete.AutoComplete.prototype.freeze = function() {
	// "Freeze" the textbox attached to the autocomplete, by setting it readonly and enforcing focus
	var that = this;
	var t = this.textbox;

	t.readOnly = true;
	t.onblur = function(e) {
		if (! e) e = window.event;
		that.stopEvent(e);

		top.HEURIST.autocomplete.beep();
		setTimeout(function() { t.focus(); }, 0);

		return false;
	};
	t.onmousedown = function() { return false; };
	t.oncontextmenu = function() { return false; };

	this.frozen = true;

	setTimeout(function() { t.focus(); }, 0);
};
top.HEURIST.autocomplete.AutoComplete.prototype.unfreeze = function() {
	// Remove the effects of a freeze on the autocomplete
	var t = this.textbox;

	t.readOnly = false;
	t.onblur = null;
	t.onmousedown = null;

	this.frozen = false;
};

top.HEURIST.autocomplete.AutoComplete.prototype.currentWordOkay = function() {
	// If we have an appropriate callback defined,
	// check whether the current word is in the autocomplete vocabulary.
	// If not, invoke that callback.

	if (! this.nonVocabularyCallback) return true;
	if (! this.currentWordValue) return true;

	var canonicalWord = this.currentWordValue.toLowerCase().replace(/\s+/g, ' ').replace(/^ | $/g, '');
	if (! canonicalWord) return true;

	var options = this.dataFunction(canonicalWord);
	for (var i=0; i < options.length; ++i) {
		var option = options[i].toLowerCase().replace(/\s+/g, ' ').replace(/^ | $/g, '');
		if (option == canonicalWord) return true;
	}

	// Okay -- no matches.  Call the callback to let them know.
	if (this.nonVocabularyCallback.call(this, this.currentWordValue)) return true;
	else if (! this.frozen) {
		var start = this.textbox.value.indexOf(this.delimiter + this.currentWordValue + this.delimiter) + 1;
		if (start == 0  &&  this.textbox.value.substring(this.textbox.value.length - this.currentWordValue.length) == this.currentWordValue)
			start = this.textbox.value.length - this.currentWordValue.length;
		if (start < 0) start = 0;

		var end = start + this.currentWordValue.length;
		var thisRef = this;
		setTimeout(function() {
			thisRef.textbox.focus();
			thisRef.setTextboxRange(start, end);
			//thisRef.setTextboxRange(end, end);
		}, 0);
	}
};

top.HEURIST.tagAutofill = function(term) {
	return HTagManager.getMatchingTags(term, 20);
};

top.HEURIST.getKeywordAutofillFn = function(group) {
	var wgTags = HWorkgroupTagManager.getWorkgroupTags(group);
	var count = 20;
	return function(term) {
		var regex, regexSafeTerm;
		var bits, firstBit;
		var startMatches, otherMatches, matches;
		var i;
		var match;

		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }

		if (term === "") { return []; }

		term = term.replace(/[^a-zA-Z0-9]/g, " ");      // remove punctuation and other non-alphanums
		term = term.toLowerCase().replace(/^ +| +$/, "");       // trim whitespace from start and end
		regexSafeTerm = term.replace(/[\\^.$|()\[\]*+?{}]/g, "\\$0");       // neutralise any special regex characters
		if (regexSafeTerm.indexOf(" ") === -1) {
			// term is a single word ... look for it to start any word in the tag
			regex = new RegExp("(.?)\\b" + regexSafeTerm);
		}
		else {
			// multiple words: match all of them at the start of words in the tag
			/*
			for input
				WORD1 WORD2 WORD3 WORD4 ...
			want output
				(?=.*?\bWORD2)(?=.*?\bWORD3)(?=.*?\bWORD4) ... (^.*?)\bWORD1
			This matches all words in the search term using look-ahead (no matter what order they are in)
			except for the first word, which it prefers to match at the beginning of the tag.
			When it matches at the beginning, $1 is empty: we test this to float it to the top of the results.
			*/
			bits = regexSafeTerm.split(/ /);
			firstBit = bits.shift();

			regexSafeTerm = "(?=.*?\\b" + bits.join(")(?=.*?\\b") + ")(^.*?)\\b" + firstBit;
			regex = new RegExp(regexSafeTerm);
		}
		startMatches = [];
		otherMatches = [];

		for (i=0; i < wgTags.length; ++i) {
			match = wgTags[i].getName().toLowerCase().match(regex);

			if (match  &&  match[1] === "") {
				startMatches.push(wgTags[i].getName());
			}
			else if (match) {
				otherMatches.push(wgTags[i].getName());
			}
		}

		// splice start matches at the beginning of the other matches
		matches = otherMatches;
		startMatches.unshift(0, 0);
		matches.splice.apply(matches, startMatches);

		if (matches.length > count) {
			return matches.slice(0, count);
		}
		else {
			return matches;
		}
	};
};

top.HEURIST.showConfirmNewTag = function(tag) {
	// "this" is the autocomplete object
	var that = this;

	if (this.confirmImg) {
		if (! tag) {
			this.confirmImg.parentNode.removeChild(this.confirmImg);
			this.confirmImg = null;
		}
		return false;
	}
	else if (! tag) return false;

	var start = this.textbox.value.indexOf(this.delimiter + this.currentWordValue + this.delimiter) + 1;
	if (start == 0  &&  this.textbox.value.substring(this.textbox.value.length - this.currentWordValue.length) == this.currentWordValue)
		start = this.textbox.value.length - this.currentWordValue.length;
	if (start < 0) start = 0;
	var end = start + this.currentWordValue.length;

	this.currentWordStart = start;
	this.currentWordEnd = end;


	var confirmImg = this.confirmImg = this.document.createElement("div");
		confirmImg.className = "confirmImg";
	var confirmOption = confirmImg.appendChild(this.document.createElement("div"));
		confirmOption.style.top = "2px";
		confirmOption.style.color = "#fff";
		confirmOption.innerHTML = "<div style='display:inline-block;'><img src='"+top.HEURIST.baseURL_V3+"common/images/tick-grey.gif'></div>Confirm New Tag";
		confirmOption.className = "option";
		confirmOption.onmousedown = function() { top.HEURIST.autocompleteConfirm.call(that); return false; };
	var changeOption = confirmImg.appendChild(this.document.createElement("div"));
		changeOption.style.top = "14px";
		changeOption.style.color = "#fff";
		changeOption.innerHTML = "<div style='display:inline-block;'><img src='"+top.HEURIST.baseURL_V3+"common/images/cross.png'></div>Change Tag";
		changeOption.className = "option";
		changeOption.onmousedown = function() { top.HEURIST.autocompleteChange.call(that); return false; };


	// take a punt at where the caret is
	var offset = this.textbox.value.indexOf(","+tag+",")+1;	// could be somewhere in the middle ...
	if (offset == 0  &&  this.textbox.value.substring(this.textbox.value.length - tag.length) == tag) {
		offset = this.textbox.value.length - tag.length;	// could be at the end ...
	}
	if (offset < 0) offset = 0;	// otherwise it's at the beginning!


	var approxLeft = Math.round(offset * 5.5);
	if (approxLeft > this.textbox.offsetWidth) approxLeft = this.textbox.offsetWidth;
	else if (approxLeft < 20) approxLeft = 20;

	var tagsPosition = top.HEURIST.getPosition(this.textbox);
	confirmImg.style.left = (tagsPosition.x + approxLeft - 150) + "px";
	confirmImg.style.top = (tagsPosition.y - 80) + "px";
	confirmImg.style.height = "30px";

	//this.document.body.appendChild(confirmImg);
	this.textbox.parentNode.appendChild(confirmImg);

	this.setTextboxRange(start, end);
	this.freeze();
	if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = true;

	return false;
};

top.HEURIST.autocompleteConfirm = function() {
	// (this) is the AutoComplete
	var newTag = this.currentWordValue.replace(/^\s+|\s+$/g, "").replace(/\s+/g, " ");
	//top.HEURIST.user.tags.push(newTag);

	this.confirmImg.parentNode.removeChild(this.confirmImg);
	this.confirmImg = null;

	if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = false;
	this.unfreeze();

	// move caret to end of text
	if (this.textbox.createTextRange) {
		var range = this.textbox.createTextRange();
		range.collapse(false);
		range.select();
	} else if (this.textbox.setSelectionRange) {
		this.textbox.focus();
		var length = this.textbox.value.length;
		this.textbox.setSelectionRange(length, length);
	}
	this.currentWord = -1;
	this.currentWordValue = null;
};

top.HEURIST.autocompleteChange = function() {
	this.confirmImg.parentNode.removeChild(this.confirmImg);
	this.confirmImg = null;

	if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = false;
	this.unfreeze();

	if (this.currentWordEnd === this.textbox.value.length-1  &&  this.textbox.value.charAt(this.currentWordEnd) === ",") {
		this.textbox.value = this.textbox.value.substring(0, this.textbox.value.length - 1);
	}
	this.textbox.focus();
	this.setTextboxRange(this.currentWordEnd, this.currentWordEnd);
};

