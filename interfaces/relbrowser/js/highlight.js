var currentRefs;

function highlight (root, refs) {
	// normalise addresses
	for (var i = 0; i < refs.length; ++i) {

		if (! refs[i].endElems  ||  ! refs[i].endElems.length) {
			refs[i].endElems = refs[i].startElems;
		}
		else {
			for (var j = 0; j < refs[i].startElems.length; ++j) {
				if (j < refs[i].endElems.length  &&  refs[i].endElems[j] === null) {
					refs[i].endElems[j] = refs[i].startElems[j];
				}
			}
		}

		if (refs[i].startWord !== null  &&  refs[i].endWord === null) {
			refs[i].endWord = refs[i].startWord;
		}
	}

	//console.log("normalised refs: " + refs.toSource());
	currentRefs = [];
	traverse(root, refs, []);
}

function traverse (elem, refs, address) {
	var startingRefs = [];
	var endingRefs = [];

	// find refs starting at this elem
	for (var i = 0; i < refs.length; ++i) {
		// only check refs which are not already current
		var skip = false;
		for (var j = 0; j < currentRefs.length; ++j) {
			if (currentRefs[j] == refs[i]) skip = true;
		}
		if (! skip) {
			var match = checkAddress(address, refs[i].startElems);
			if (match) startingRefs.unshift(i);
		}
	}

	// find refs ending at this elem
	for (var i = 0; i < refs.length; ++i) {
		var match = checkAddress(address, refs[i].endElems);
		if (match) endingRefs.unshift(i);
	}

//console.log("traverse " + address.join(",") + (startingRefs.length ? " startingRefs: " + startingRefs.join(",") : "")
//											+ (endingRefs.length ? " endingRefs: " + endingRefs.join(",") : "")
//											+ (currentRefs.length ? " currentRefs: " + currentRefs.join(",") : ""));



	// copy childNodes - more children might be inserted as we go!
	var children = [];
	for (var i = 0; i < elem.childNodes.length; ++i) {
		children.push(elem.childNodes[i]);
	}

	var elemNumber = 0;
	var wordOffset = 0;
	for (var i = 0; i < children.length; ++i) {
		if (children[i].nodeType === Node.ELEMENT_NODE) {
			// may alter currentRefs!
			wordOffset += traverse(children[i], refs, address.concat([++elemNumber]));
		}
		else if (children[i].nodeType === Node.TEXT_NODE) {
			// may alter currentRefs
			wordOffset += transformTextNode(children[i], refs, startingRefs, endingRefs, wordOffset);
		}
	}

	return wordOffset;
}

// check currrent location against given address
function checkAddress (currLoc, addr) {
	var i;

	if (currLoc.length !== addr.length) return false;

	for (i = 0; i < addr.length; ++i) {
		if (addr[i] === null) break;
		if (currLoc.length <= i) return false;
		if (currLoc[i] !== addr[i]) return false;
	}

	return true;
}

function transformTextNode (elem, refs, startingRefs, endingRefs, wordOffset) {
//console.log("transformElement:" + " currentRefs: " + currentRefs.join(",")
//								+ " startingRefs: " + startingRefs.join(",")
//								+ " endingRefs: " + endingRefs.join(","));
	var parentElem = elem.parentNode;
	var text = elem.textContent;
	text = text.replace(/^\s+/, "").replace(/\s+$/, "");
	var words = text.split(/\s+/);

	var myStartingRefs = [];
	var myEndingRefs = [];

	// filter just the applicable refs from startingRefs and endingRefs
	for (var i = 0; i < startingRefs.length; ++i) {
		var s = refs[startingRefs[i]].startWord;
		if (wordOffset < s  &&  s <= (wordOffset + words.length)) {
			myStartingRefs.push(startingRefs[i]);
		}
	}
	for (var i = 0; i < endingRefs.length; ++i) {
		var s = refs[endingRefs[i]].endWord;
		if (wordOffset < s  &&  s <= (wordOffset + words.length)) {
			myEndingRefs.push(endingRefs[i]);
		}
	}

//console.log("myStartingRefs: " + myStartingRefs.join(",") + " myEndingRefs: " + myEndingRefs.join(","));

	// trivial trivial case: there is nothing happening in this text node.
	// we only visited it to find out how many words it contains
	if (! currentRefs.length  &&  ! myStartingRefs.length  &&  ! myEndingRefs.length) {
		return words.length;
	}

	// trivial case: there are no references starting or ending within this element
	// so just highlight it and link to the ref on the top of the currentRefs stack
	if (! myStartingRefs.length  &&  ! myEndingRefs.length) {
		var ref = refs[currentRefs[0]];
		var a = document.createElement("a");
		a.className = (currentRefs.length > 1 ? "annotation multiple" : "annotation");
		a.href = "#ref" + ref.recordID;
		a.title = ref.title;
		a.name = "ref" + ref.recordID;
		a.setAttribute("annotation-id", ref.recordID);
		a.onclick = function() { showFootnote(this.getAttribute("annotation-id")); highlightAnnotation(this.getAttribute("annotation-id")); };
		a.innerHTML = elem.textContent;
		elem.parentNode.replaceChild(a, elem);

	} else {

		var newElements = [];

		var startPositions = [];
		var endPositions = [];

		for (var i = 0; i < currentRefs.length; ++i) {
			startPositions.push( { "word": 1, "refId": currentRefs[i] } );
			// if this ref is in myEndingRefs, ignore it here -- it will be entered into the endPositions list correctly below
			var skip = false;
			for (var j = 0; j < myEndingRefs.length; ++j) {
				if (myEndingRefs[j] == currentRefs[i]) skip = true;
			}
			if (! skip)
				endPositions.push( { "word": words.length, "refId": currentRefs[i] } );
		}
		for (var i = 0; i < myStartingRefs.length; ++i) {
			var refId = myStartingRefs[i];
			var startWord = refs[refId].startWord - wordOffset;
			startPositions.push( { "word": startWord, "refId": refId, "starting": true } );
		}
		for (var i = 0; i < myEndingRefs.length; ++i) {
			var refId = myEndingRefs[i];
			var endWord = refs[refId].endWord - wordOffset;
			endPositions.push( { "word": endWord, "refId": refId, "ending": true } );
		}

		var sortfunc = function(a,b) {
			if (a.word == b.word)
				return a.refId - b.refId;
			else
				return a.word - b.word;
		};

		startPositions = startPositions.sort(sortfunc);
		endPositions = endPositions.sort(sortfunc);

console.log("startPositions:" + startPositions.toSource());
console.log("endPositions:" + endPositions.toSource());

		var refStack = [];
		var sections = [];
		var section = { "startWord": 1, "endWord": null, "refId": null, "refCount": 0, "refNums": [] };
		for (var w = 1; w <= words.length; ++w) {
			var startPos = null;
			// push any refs that start at this pos onto the stack
			while (startPositions.length > 0  &&  startPositions[0].word == w) {
				startPos = startPositions.shift();
				refStack.unshift(startPos.refId);

				if (section.startWord == w) {
					// there is already a section starting at this word
					// how?  either one finished at the previous word, or more than one ref starts here
					section.refId = startPos.refId;
					section.refCount = refStack.length;
				} else {
					// wrap up the current section and start a new one
					section.endWord = w - 1;
					sections.push(section);
					section = { "startWord": w, "endWord": null, "refId": startPos.refId, "refCount": refStack.length, "refNums": [], "starting": startPos.starting || false };
				}
			}

			var endPos = null;
			// remove any refs that end at this pos from the "stack"
			while (endPositions.length > 0  &&  endPositions[0].word == w) {
				endPos = endPositions.shift();
				var newRefStack = [];
				while (refStack.length) {
					var ref = refStack.shift();
					if (ref != endPos.refId) newRefStack.push(ref);
				}
				refStack = newRefStack;

				// a reference ends here
				section.endWord = w;
				section.ending = (section.ending || endPos.ending) || false;
				if (endPos.ending)
					section.refNums.push(endPos.refId);
				if (endPositions.length == 0  ||  endPositions[0].word != w) {
					// no more to come
					sections.push(section);
					if (w == words.length) {
						// this is the last word => no more sections
						section = null;
					} else {
						section = { "startWord": w + 1, "endWord": null, "refId": (refStack.length > 0 ? refStack[0] : null), "refCount": refStack.length, "refNums": [] };
					}
				}
			}
		}

		if (section) {
			section.endWord = words.length;
			sections.push(section);
		}
console.log("sections: " + sections.toSource());

		for (var i = 0; i < sections.length; ++i) {
			var section = sections[i];

			var wordString =
				(section.starting ? "" : " ") +
				words.slice(section.startWord - 1, section.endWord).join(" ") +
				(section.ending ? "" : " ");

			if (section.refId != null) {
				var ref = refs[section.refId];
				var a = document.createElement("a");
				a.className = (section.refCount > 1 ? "annotation multiple" : "annotation");
				a.href = "#ref" + ref.recordID;
				a.title = ref.title;
				a.name = "ref" + ref.recordID;
				a.setAttribute("annotation-id", ref.recordID);
				a.onclick = function() { showFootnote(this.getAttribute("annotation-id")); highlightAnnotation(this.getAttribute("annotation-id")); };
				a.innerHTML = wordString;
				newElements.push(a);

				for (var r = 0; r < section.refNums.length; ++r) {
					var ref = refs[section.refNums[r]];
					var a = document.createElement("a");
					a.className = "annotation superscript";
					a.href = "#ref" + (section.refNums[r] + 1);
					a.title = ref.title;
					a.onclick = function() { showFootnote(this.getAttribute("annotation-id")); highlightAnnotation(this.getAttribute("annotation-id")); };
					var s = a.appendChild(document.createElement("sup"));
					s.innerHTML = "[" + (section.refNums[r] + 1) + "]";
					//s.innerHTML = "";
					newElements.push(a);
				}

			} else {
				newElements.push(document.createTextNode(wordString));
			}

		}

		// replace elem with newElements
		var parentElem = elem.parentNode;
		parentElem.replaceChild(newElements[0], elem);
		for (var i = 1; i < newElements.length; ++i) {
			parentElem.insertBefore(newElements[i], newElements[i-1].nextSibling);
		}

		// now update currentRefs to reflect what happened within this text node
		// add refs that start at this elem
		if (myStartingRefs.length) {
			currentRefs = myStartingRefs.reverse().concat(currentRefs);
		}
		// remove refs which end here
		if (currentRefs.length  &&  myEndingRefs.length) {
			var newState = [];
			for (var i = 0; i < currentRefs.length; ++i) {
				var remove = false;
				for (var j = 0; j < myEndingRefs.length; ++j) {
					if (currentRefs[i] == myEndingRefs[j]) remove = true;
				}
				if (! remove) newState.push(currentRefs[i]);
			}
			currentRefs = newState;
		}
	}

	return words.length;
}



/* functions for highlighting annotation links */

var highlighted = {
	"inline-annotation" : null,
	"annotation-link" : null
};

function highlightAnnotation (id) {
	var links = document.getElementsByTagName("a");
	for (var i = 0; i < links.length; ++i) {
		var e = links[i];
		if (e.getAttribute("annotation-id") == id) {
			if (e.id.match(/^ref/)) {
				highlightElem("inline-annotation", e);
			} else {
				highlightElem("annotation-link", e);
			}
		}
	}
}

function highlightElem (name, e) {
	if (highlighted[name]) {
		var oldbg = highlighted[name].getAttribute("old-bg-color");
		highlighted[name].style.backgroundColor = oldbg;
		highlighted[name].setAttribute("old-bg-color", null);
	}
	if (e) {
		e.setAttribute("old-bg-color", e.style.backgroundColor);
		e.style.backgroundColor = "#ffbb00";
		highlighted[name] = e;
	}
}

function highlightOnLoad() {
	var matches = window.location.hash.match(/#ref([0-9]+)/);
	if (matches  &&  matches[1]) {
		showFootnote(matches[1]);
		highlightAnnotation(matches[1]);
	}
}
