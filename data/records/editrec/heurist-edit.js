	/* heurist-edit.js

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	*/

top.HEURIST.edit = {
	modules: {
		'public': { url: 'tabs/public-tab.html', 'link-id': 'public-link', loaded: false, loading: false, changed: false,
				preload: function() { return (top.HEURIST.record.bibID  &&  top.HEURIST.record.bibID != 0); } },
		'personal': { url: 'tabs/personal-tab.html', 'link-id': 'personal-link', loaded: false, loading: false, changed: false,
				preload: function() { return (top.HEURIST.record.bkmkID  &&  top.HEURIST.record.bkmkID != 0); },
				disabledFunction: function() { top.HEURIST.edit.addMissingBookmark() } },
		'annotation': { url: 'tabs/annotation-tab.html', 'link-id': 'annotation-link', loaded: false, loading: false, changed: false,
				preload: function() { return true; } },
		'workgroups': { url: 'tabs/workgroups-tab.html', 'link-id': 'workgroups-link', loaded: false, loading: false, changed: false,
				preload: function() { return (top.HEURIST.record.bibID  &&  top.HEURIST.record.bibID != 0  &&  top.HEURIST.user.workgroups.length > 0); } },
		'relationships': { url: 'tabs/relationships-tab.html', 'link-id': 'relationships-link', loaded: false, loading: false, changed: false,
				preload: function() { return (top.HEURIST.record.bibID  &&  top.HEURIST.record.bibID != 0); } }
	},

	loadModule: function(name) {
		if (! top.HEURIST.edit.modules[name]) return false;	// unknown module, do not waste my time
		var module = top.HEURIST.edit.modules[name];
		if (module.loaded  ||  module.loading) return true;

		var sidebarLink = document.getElementById(module["link-id"]);

		// check the preload function to see if it should in fact be loaded
		if (module.preload  &&  ! module.preload()) {
			var disabledDescription = sidebarLink.getAttribute("disabledDescription");
			for (var i=0; i < sidebarLink.childNodes.length; ++i) {
				var child = sidebarLink.childNodes[i];
				if (child.id == "desc") {
					if (! sidebarLink.getAttribute("enabledDescription"))
						sidebarLink.setAttribute("enabledDescription", child.innerHTML);
					// child.removeChild(child.firstChild);
					child.innerHTML = "";
					child.appendChild(document.createTextNode(disabledDescription));
					break;
				}
			}
			sidebarLink.title = sidebarLink.getAttribute("disabledTitle");

			return false;
		}
		else if (sidebarLink.getAttribute("enabledDescription")) {
			// we are patching a module that was previously disabled but is now enabled
			var enabledDescription = sidebarLink.getAttribute("enabledDescription");
			for (var i=0; i < sidebarLink.childNodes.length; ++i) {
				var child = sidebarLink.childNodes[i];
				if (child.id == "desc") {
					child.removeChild(child.firstChild);
					child.appendChild(document.createTextNode(enabledDescription));
					break;
				}
			}
			sidebarLink.className = sidebarLink.className.replace(/ disabled$/, "");
			sidebarLink.removeAttribute("enabledDescription");
			sidebarLink.title = "";
		}

		module.loading = true;

		var newIframe = top.document.createElement('iframe');
			newIframe.className = "tab";
			newIframe.frameBorder = 0;
			var newHeuristID = newIframe.HEURIST_WINDOW_ID = top.HEURIST.createHeuristWindowID(module.url);
// FIXME: seems that safari 1 requires these dimensions set explicitly here even though they're in .tab ..?
			newIframe.style.width = "100%";
			newIframe.style.height = "100%";

			var oneTimeOnload = function() {
				// One time onload
				if (module.postload) module.postload.apply(newIframe.contentWindow);
				top.HEURIST.edit.moduleLoaded(name);
				top.HEURIST.deregisterEvent(newIframe, "load", oneTimeOnload);
			};

			top.HEURIST.registerEvent(newIframe, "load", function() {
				module.loaded = true;
				module.loading = false;
				try {
					newIframe.contentWindow.HEURIST_WINDOW_ID = newHeuristID;
				} catch (e) { }
			});

		var urlBits = [];
		var parameters = top.HEURIST.record;
		if (parameters.bibID) urlBits.push("bib_id=" + parameters.bibID);
		if (parameters.bkmkID) urlBits.push("bkmk_id=" + parameters.bkmkID);

		var url = module.url;
		if (urlBits.length > 0) url += "?" + urlBits.join("&");
		newIframe.src = url;

		var tabHolder = document.getElementById("tab-holder");
		module.frame = newIframe;
		tabHolder.appendChild(newIframe);

		return true;
	},

	showModule: function(name) {
		if (top.HEURIST.edit.preventTabSwitch) return false;

		if (! top.HEURIST.edit.loadModule(name)) {	// make sure the page is in the loading queue ...
			if (top.HEURIST.edit.modules[name].disabledFunction) {
				top.HEURIST.edit.modules[name].disabledFunction();
			}
			return false;
		}

		var modules = top.HEURIST.edit.modules;

		var tabHolder = document.getElementById("tab-holder");
		// get rid of any non-iframe children ... obviously have to do it backwards or they will change position!
		// also set display: none on all the iframe children
		for (var i=tabHolder.childNodes.length-1; i >= 0; --i) {
			if (! (tabHolder.childNodes[i].className  &&  tabHolder.childNodes[i].className.match(/\btab\b/))) {
				tabHolder.removeChild(tabHolder.childNodes[i]);
			}
			else if (top.HEURIST.browser.isEarlyWebkit) {
				if (parseInt(tabHolder.childNodes[i].style.height) > 0) {
					tabHolder.childNodes[i].savedHeight = tabHolder.childNodes[i].style.height;
				}
				tabHolder.childNodes[i].style.height = "0";
				tabHolder.childNodes[i].style.overflow = "hidden";
			}
			else {
				tabHolder.childNodes[i].style.display = "none";
			}
		}
		if (top.HEURIST.browser.isEarlyWebkit) {
			modules[name].frame.style.height = modules[name].frame.savedHeight;
		} else {
			modules[name].frame.style.display = "block";
		}

		// presentation stuff: mark the currently visible module
		for (var eachName in modules) {
			if (eachName !== name) {
				var sidebarLink = document.getElementById(modules[eachName]["link-id"]);
				sidebarLink.className = sidebarLink.className.replace(/\b\s*selected\s*\b/g, " ");
			}
		}

		// record the current panel so we can bookmark it properly ...
		// the conditional is so that safari 1 doesn't constantly reload
		if (document.location.hash != ("#"+name))
			document.location.replace("#" + name);

		document.getElementById(modules[name]["link-id"]).className += " selected";
		document.body.className = document.body.className.replace(/\b\s*mode-[a-z]+\b|$/, " mode-" + name);

		if (modules[name].frame.contentWindow.onshow) {
			modules[name].frame.contentWindow.onshow.call(modules[name].frame.contentWindow);
		}

		return true;
	},

	loadAllModules: function() {
		// first, mark all modules as disabled ...
		var sidebarDiv = document.getElementById("sidebar");
		for (var i=0; i < sidebarDiv.childNodes.length; ++i) {
			var child = sidebarDiv.childNodes[i];
			if (child.className  &&  child.className.match(/\bsidebar-link\b/)) {
				if (! child.className.match(/\bdisabled\b/)) child.className += " disabled";
			}
		}


		var firstName = null;
		for (var eachName in top.HEURIST.edit.modules) {
			var linkButton = document.getElementById(top.HEURIST.edit.modules[eachName]["link-id"]);

			if (top.HEURIST.edit.loadModule(eachName)) {
				if (! firstName) firstName = eachName;
				linkButton.className = linkButton.className.replace(/ disabled$/, "");
			}

			top.HEURIST.registerEvent(linkButton, "click", function(name) { return function() { top.HEURIST.edit.showModule(name); } }(eachName) );
		}

		var hash = document.location.hash.substring(1);
		if (top.HEURIST.edit.modules[hash]  &&  (top.HEURIST.edit.modules[hash].loading  ||  top.HEURIST.edit.modules[hash].loaded)) {
			if (top.HEURIST.edit.showModule(hash)) return;
		}
		if (firstName) {
			top.HEURIST.edit.showModule(firstName);
		}
	},

	moduleLoaded: function(moduleName) {

	},

	showRecordProperties: function() {
		// fill in the toolbar fields with the details for this record
//		document.getElementById('reftype-val').innerHTML = '';
//		document.getElementById('reftype-val').appendChild(document.createTextNode(top.HEURIST.record.reftype));

		document.getElementById('reftype-img').style.backgroundImage = "url("+ top.HEURIST.basePath+"common/images/reftype-icons/" + top.HEURIST.record.reftypeID + ".gif)";

		document.getElementById('title-val').innerHTML = '';
		document.getElementById('title-val').appendChild(document.createTextNode(top.HEURIST.record.title));

		if (top.HEURIST.record.workgroup) {
			document.getElementById('workgroup-val').innerHTML = '';
			document.getElementById('workgroup-val').appendChild(document.createTextNode(top.HEURIST.record.workgroup));
			document.getElementById('workgroup-div').style.display = "block";

			var othersAccess = (top.HEURIST.record.workgroupVisibility == "Hidden")? "not visible" : "visible";
			document.getElementById('workgroup-access').innerHTML = othersAccess;
		} else {
			document.getElementById('workgroup-val').innerHTML = 'everyone';
			document.getElementById('workgroup-div').style.display = "block";

			document.getElementById('workgroup-access').innerHTML = 'visible';
		}
	},

	addMissingBookmark: function() {
		if (! top.HEURIST.record.bibID) return;
		if (confirm("You haven't bookmarked this record.\nWould you like to add a bookmark now?")) {
			// only call the disabledFunction callback once -- a safeguard against infinite loops
			top.HEURIST.edit.modules.personal.disabledFunction = null;

			// add the bookmark, patch the record structure, and view the personal tab
			top.HEURIST.util.getJsonData(top.HEURIST.basePath + "data/bookmarks/add-bookmark.php?bib_id=" + top.HEURIST.record.bibID, function(vals) {
				for (var i in vals) {
					top.HEURIST.record[i] = vals[i];
				}
				top.HEURIST.edit.showModule("personal");
			});
		}
	},

	cancelSave: function() {
		// Cancel the saving of the record (reload the page)
		// Out of courtesy, check if there have been any changes that WOULD have been saved.
		var modules = top.HEURIST.edit.modules;
		var changedModuleNames = [];
		for (var eachName in modules) {
			if (modules[eachName].changed)
				changedModuleNames.push(eachName);
		}

		var message;
		if (changedModuleNames.length == 0) {
			// APPARENTLY the correct behaviour when there are no changes is to reload everything.
			// Thanks for the vote of confidence, Ian.
			message = null;

		} else if (changedModuleNames.length == 1) {
			message = "Abandon changes to " + changedModuleNames[0] + " data?";
		} else {
			var lastName = changedModuleNames.pop();
			message = "Abandon changes to " + changedModuleNames.join(", ") + " and " + lastName + " data?";
		}

		if (message === null  ||  confirm(message)) {
			for (var eachName in modules)
				top.HEURIST.edit.unchanged(eachName);
			top.location.reload();
		} else {
			// Nothing
		}

		top.HEURIST.edit.doSaveAction();
	},

	savePopup: null,
	save: function() {
		// Attempt to save all the modules that need saving

		// Display a small saving window
/*
		if (! top.HEURIST.edit.savePopup) {
			top.HEURIST.edit.savePopup = top.HEURIST.util.popupURL(top, "img/saving-animation.gif", { "no-save": true, "no-resize": true, "no-titlebar": true });
		}
*/
		var personalWindow = top.HEURIST.edit.modules.personal  &&  top.HEURIST.edit.modules.personal.frame  &&  top.HEURIST.edit.modules.personal.frame.contentWindow;
		if (personalWindow  &&  ! personalWindow.tagCheckDone  &&  personalWindow.document.getElementById("tags").value.replace(/^\s+|\s+$/g, "") == "") {
			// personal tags field is empty -- popup the add keywords dialogue
			personalWindow.tagCheckDone = true;
			top.HEURIST.util.popupURL(top, top.HEURIST.basePath + "data/tags/add-tags.html?no-tags", { callback: function(tags) {
				if (tags) {
					personalWindow.document.getElementById("tags").value = tags;
					top.HEURIST.edit.changed("personal");
				}
				setTimeout(function() { top.HEURIST.edit.save(); }, 0);
			} });

			return;
		}


		for (var moduleName in top.HEURIST.edit.modules) {
			var module = top.HEURIST.edit.modules[moduleName];

			var contentWindow = module.frame  &&  module.frame.contentWindow;
			if (! contentWindow) continue; /* not loaded yet! no need to save */

			var form = contentWindow.document.forms[0];

			if (form.heuristForceSubmit) {
				// there are some things that don't set module.changed
				// but we still want to ensure they are saved
				form.heuristForceSubmit();
			}

			if (module.changed) { // don't bother saving unchanged tabs
				if (form.onsubmit  &&  ! form.onsubmit()) {
					top.HEURIST.edit.showModule(moduleName);
					return;	// submit failed ... up to the individual form handlers to display messages
				}

				// Make a one-shot onload function to mark this module as unchanged and continue saving
				var moduleUnchangeFunction = function() {
					top.HEURIST.deregisterEvent(module.frame, "load", moduleUnchangeFunction);
					top.HEURIST.edit.unchanged(moduleName);
					top.HEURIST.edit.save();	// will continue where we left off
				};
				top.HEURIST.registerEvent(module.frame, "load", moduleUnchangeFunction);
				(form.heuristSubmit || form.submit)();
				return;
			}
		}


		// If we get here, then every module has been marked as unchanged (i.e. saved or equivalent)
		// Do whatever it is we need to do.

		//setTimeout(function() { top.HEURIST.util.closePopup(top.HEURIST.edit.savePopup.id); }, 5000);

		top.HEURIST.edit.showRecordProperties();
		setTimeout(function() {
			document.getElementById("popup-saved").style.display = "block";
			setTimeout(function() {
				document.getElementById("popup-saved").style.display = "none";
				top.HEURIST.edit.doSaveAction();
			}, 1000);
		}, 0);
	},
	doSaveAction: function() {
		if (document.getElementById("act-close").checked) {
			// try to close this window, and restore focus to the window that opened it
			try {
				var topOpener = top.opener;
				top.close();
				if (topOpener) topOpener.focus();
			} catch (e) { }
		}
		else if (document.getElementById("act-recent").checked) {
			setTimeout(function() {
				top.location.href = ".?q=sortby:-m";
			}, 0);
		}
	},

	changed: function(moduleName) {
		// mark the given module as changed
		if (! top.HEURIST.edit.modules[moduleName]) return;

		if (top.HEURIST.edit.modules[moduleName].changed) return;	// already changed

		top.HEURIST.edit.modules[moduleName].changed = true;
		var link = document.getElementById(top.HEURIST.edit.modules[moduleName]['link-id']);
		if (link) {
			link.className += ' changed';
			link.title = "Content has been changed";
		}

/*
		// Enable save buttons
		var sbs = document.getElementsByName("save-button");
		for (var i=0; i < sbs.length; ++i)
			sbs[i].disabled = false;
*/
	},

	unchanged: function(moduleName) {
		// mark the given module as changed
		if (! top.HEURIST.edit.modules[moduleName]) return;	// should raise an exception here ... FIXME

		top.HEURIST.edit.modules[moduleName].changed = false;
		var link = document.getElementById(top.HEURIST.edit.modules[moduleName]['link-id']);
		link.className = link.className.replace(/(^|\s+)changed/, '');
		link.title = "";

/*
		// Disable save buttons if there is nothing to save
		var anyChanges = false;
		var modules = top.HEURIST.edit.modules;
		for (var moduleName in modules) {
			if (modules[moduleName].changed) {
				anyChanges = true;
				break;
			}
		}
		if (anyChanges) return;

		var sbs = document.getElementsByName("save-button");
		for (var i=0; i < sbs.length; ++i)
			sbs[i].disabled = true;
*/
	},

	onbeforeunload: function() {
		var changed = false;
		for (var moduleName in top.HEURIST.edit.modules) {
			if (top.HEURIST.edit.modules[moduleName].changed) {
				changed = true;
				break;
			}
		}
// FIXME ... we can do better than this
		if (changed) return "You have made changes to the details for this record.  If you continue, all changes will be lost.";
	},


	getBibDetailRequirements: function(reftypeID) {
		if (! top.HEURIST.user.bibDetailRequirements  ||  ! top.HEURIST.user.bibDetailRequirements.valuesByReftypeID[reftypeID]) {
			// easy case -- no special bibDetailRequirements for this reftype, considering this user's workgroups
			return top.HEURIST.bibDetailRequirements.valuesByReftypeID[reftypeID];
		}
		else if (top.HEURIST.patchedBibDetailRequirements  &&  top.HEURIST.patchedBibDetailRequirements[reftypeID]) {
			return top.HEURIST.patchedBibDetailRequirements[reftypeID];
		}
		else {
			// make a copy of the original bdrs and override with any workgroup-specific stuff
			var bdrs = {};
			var orig_bdrs = top.HEURIST.bibDetailRequirements.valuesByReftypeID[reftypeID];
			var wg_bdrs = top.HEURIST.user.bibDetailRequirements.valuesByReftypeID[reftypeID];
			var precedence = { "Y": 4, "R": 3, "O": 2, "X": 1 };

			// keep track of the original requiremences, as a fallback if they're not overridden
			for (var bdt_id in orig_bdrs) {
				bdrs[bdt_id] = [];
				for (var i=0; i < orig_bdrs[bdt_id].length; ++i)
					bdrs[bdt_id][i] = orig_bdrs[bdt_id][i];
			}
			// Now, go through the workgroup-specific requiremences, and take the maximum
			for (var bdt_id in wg_bdrs) {
				var names = [];
				var prompts = [];
				var maxRequiremence = '';
				var repeat = 0;

				for (var i=0; i < wg_bdrs[bdt_id].length; ++i) {
					var wg_vals = wg_bdrs[bdt_id][i];
					if (wg_vals[0]) names.push(wg_vals[0]);
					if (wg_vals[1]) prompts.push(wg_vals[1]);
					if (! maxRequiremence  ||  precedence[wg_vals[3]] > precedence[maxRequiremence]) maxRequiremence = wg_vals[3];
					if (wg_vals[4]) repeat = wg_vals[4];
				}

				// there might be bdts that are not in the original requiremences
				if (! bdrs[bdt_id]) bdrs[bdt_id] = [];

				if (names.length) bdrs[bdt_id][0] = names.join(' / ');
				if (prompts.length) bdrs[bdt_id][1] = prompts.join(' / ');
				if (maxRequiremence  &&  bdrs[bdt_id][3] != 'Y') bdrs[bdt_id][3] = maxRequiremence;
				if (repeat > 0) bdrs[bdt_id][4] = "1";
			}

			if (! top.HEURIST.patchedBibDetailRequirements) top.HEURIST.patchedBibDetailRequirements = {};
			top.HEURIST.patchedBibDetailRequirements[reftypeID] = bdrs;
			return bdrs;
		}
	},

	getBibDetailNonRequirements: function(reftypeID) {
		var non_reqs = {};
		var reqs = top.HEURIST.edit.getBibDetailRequirements(reftypeID);
		for (var bdt_id in top.HEURIST.bibDetailTypes.valuesByBibDetailTypeID) {
			var skip = false;
			for (var i in reqs) {
				if (i == bdt_id) skip = true;
			}
			if (skip) continue;
			non_reqs[bdt_id] = top.HEURIST.bibDetailTypes.valuesByBibDetailTypeID[bdt_id];
		}
		return non_reqs;
	},

	getBibDetailOrder: function (reftypeID) {
		var order = top.HEURIST.bibDetailRequirements.orderByReftypeID[reftypeID];
		var bdrs = top.HEURIST.bibDetailRequirements.valuesByReftypeID[reftypeID];

		if (top.HEURIST.user.bibDetailRequirements  &&
			top.HEURIST.user.bibDetailRequirements.valuesByReftypeID[reftypeID]) {
			// add any wg overrides that are additions - they get pushed on the end
			var wg_bdrs = top.HEURIST.user.bibDetailRequirements.valuesByReftypeID[reftypeID];
			for (var bdt_id in wg_bdrs) {
				if (! bdrs[bdt_id]) {
					order.push(bdt_id);
				}
			}
		}

		return order;
	},

	focusFirstElement: function() {
		// try to move focus to the first textbox on the page that will accept focus
		var elts = document.forms[0].elements;
		for (var i=0; i < elts.length; ++i) {
			if (elts[i].type == 'text') {
				try {
					elts[i].focus();
					return;
				} catch (e) { }
			}
		}
	},

	allInputs: [],
	createInput: function(bibDetailTypeID, reftypeID, bdValues, container) {
		// Get Detail Type info  id, name, canonical type, rec type contraint
		var bdt = top.HEURIST.bibDetailTypes.valuesByBibDetailTypeID[bibDetailTypeID];
		var bdr;
		if (reftypeID) {
			bdr = top.HEURIST.edit.getBibDetailRequirements(reftypeID)[bibDetailTypeID];
		} else {
			// fake low-rent bdr if reftype isn't specified
			// name, prompt,default, required, repeatable, size, match
			bdr = [ bdt[1], "", "", "O", 0, 0, 0 ];
		}

		var newInput;
		switch (bdt[2]) {
			case "freetext":
				newInput = new top.HEURIST.edit.inputs.BibDetailFreetextInput(bdt, bdr, bdValues, container);
				break;
			case "blocktext":
				newInput = new top.HEURIST.edit.inputs.BibDetailBlocktextInput(bdt, bdr, bdValues, container);
				break;
			case "integer":
				newInput = new top.HEURIST.edit.inputs.BibDetailIntegerInput(bdt, bdr, bdValues, container);
				break;
			case "year":
				newInput = new top.HEURIST.edit.inputs.BibDetailYearInput(bdt, bdr, bdValues, container);
				break;
			case "date":
				newInput = new top.HEURIST.edit.inputs.BibDetailTemporalInput(bdt, bdr, bdValues, container);
				break;
			case "boolean":
				newInput = new top.HEURIST.edit.inputs.BibDetailBooleanInput(bdt, bdr, bdValues, container);
				break;
			case "resource":
				newInput = new top.HEURIST.edit.inputs.BibDetailResourceInput(bdt, bdr, bdValues, container);
				break;
			case "float":
				newInput = new top.HEURIST.edit.inputs.BibDetailFloatInput(bdt, bdr, bdValues, container);
				break;
			case "enum":
				newInput = new top.HEURIST.edit.inputs.BibDetailDropdownInput(bdt, bdr, bdValues, container);
				break;
			case "file":
				newInput = new top.HEURIST.edit.inputs.BibDetailFileInput(bdt, bdr, bdValues, container);
				break;
			case "geo":
				newInput = new top.HEURIST.edit.inputs.BibDetailGeographicInput(bdt, bdr, bdValues, container);
				break;
			case "separator": // note we make sure that values are ignored for a separator as it is not an input
				newInput = new top.HEURIST.edit.inputs.BibDetailSeparator(bdt, bdr, [], container);
				break;
			case "relmarker": // note we make sure that values are ignored for a relmarker as it gets it's data from the record's related array
				newInput = new top.HEURIST.edit.inputs.BibDetailRelationMarker(bdt, bdr, [], container);
				break;
			default:
				//alert("Type " + bdt[2] + " not implemented");
				newInput = new top.HEURIST.edit.inputs.BibDetailUnknownInput(bdt, bdr, bdValues, container);
		}
		top.HEURIST.edit.allInputs.push(newInput);
		return newInput;
	},

	getRecTypeConstraintsList: function(dTypeID) {
		var listRecTypeConst = "";
		var first = true;
		var rdtID = top.HEURIST.record.rtConstraintsByDType[dTypeID] ? dTypeID : 200;
		for (var rType in top.HEURIST.record.rtConstraintsByDType[rdtID]) {	// saw TODO  need to change this to dTypeID for relmarkers
			if (first) {
				listRecTypeConst += "" + rType;
				first = false;
			}else{
				listRecTypeConst += "," + rType;
			}
		}
		if (!listRecTypeConst) {
			listRecTypeConst = "0";
		}
		return listRecTypeConst;
	},

	getLookupConstraintsList: function(dTypeID) {
		var rdtConstrainedLookups = {};
		var rdtID = top.HEURIST.record.rtConstraintsByDType[dTypeID] ? dTypeID : 200;
		for (var rType in top.HEURIST.record.rtConstraintsByDType[rdtID]) {
			var dtRelConstForRecType = top.HEURIST.record.rtConstraintsByDType[rdtID][rType];
			if (!dtRelConstForRecType) continue;
			for (var i = 0; i<dtRelConstForRecType.length; i++) {
				var list = dtRelConstForRecType[i]['rdl_ids'];
				if (list) {
					list = list.split(",");
					for (var j = 0; j < list.length; j++) {
						rdtConstrainedLookups[list[j]] = '';
					}
				} else if (dtRelConstForRecType[i]['ont_id']) {	// get all the rel lookups for this ontology
					var ontRelations = top.HEURIST.bibDetailLookups[200][dtRelConstForRecType[i]['ont_id']];
					for (var j = 0; j < ontRelations.length; j++) {
						rdtConstrainedLookups[ontRelations[j][0]] = '';
					}
				}
			}
		}
		empty = true;
		for (var test in rdtConstrainedLookups) {
			empty = false;
			break;
		}
		if (empty) {
			rdtConstrainedLookups = null;
		}
		return rdtConstrainedLookups;
	},
/*
	uploadsDiv: null,
	uploadsInProgress: { counter: 0, names: {} },
*/
	uploadFileInput: function(fileInput) {
		var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

		if (fileInput.value == "") return;

		if (!confirm("By uploading this file you certify that you have copyright rights" +
			" to upload this file and to make it available on the web through the Heurist" +
			" interface. Note that although records can be restricted to specified workgroups," +
			" this restriction can be changed by members of the workgroup, so all uploaded" +
			" files should be regarded as publicly accessible.")) {
			fileInput.value = "";
			return;
		}

		if (! windowRef.HEURIST.uploadsDiv  ||  ! this.document.getElementById("uploads")) {
			var uploadsDiv = windowRef.HEURIST.uploadsDiv = this.document.body.appendChild(this.document.createElement("div"));
				uploadsDiv.id = "uploads";
		}

		var statusDiv = this.document.createElement("div");
			statusDiv.className = "upload-status";
			statusDiv.appendChild(this.document.createTextNode("Uploading file ..."));
		windowRef.HEURIST.uploadsDiv.appendChild(statusDiv);

		var progressBar = this.document.createElement("div");
			progressBar.className = "progress-bar";
		var progressDiv = progressBar.appendChild(this.document.createElement("div"));
		statusDiv.appendChild(progressBar);

		var element = fileInput.parentNode;
		var inProgressSpan = this.document.createElement("span");
			inProgressSpan.className = "in-progress";
			inProgressSpan.appendChild(this.document.createTextNode("Uploading file ..."));
		element.appendChild(inProgressSpan);

		var thisRef = this;
		var saver = new HSaver(
			function(i,f) {
				top.HEURIST.edit.fileInputUploaded.call(thisRef, element, statusDiv, { file: f });
			},
			function(i,e) {
				top.HEURIST.edit.fileInputUploaded.call(thisRef, element, statusDiv, { error: e, origName: i.value.replace(/.*[\/\\]/, "") });
			}
		);
		HeuristScholarDB.saveFile(fileInput, saver, function(fileInput, bytesUploaded, bytesTotal) {
			if (bytesUploaded  &&  bytesTotal) {
				var pc = Math.min(Math.round(bytesUploaded / bytesTotal * 100), 100);
				if (! progressDiv.style.width  ||  parseInt(progressDiv.style.width) < pc) progressDiv.style.paddingLeft = pc + "%";
			}
		});
	},

	fileInputUploaded: function(element, statusDiv, fileDetails) {
		var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

		var closeButton = this.document.createElement("img");
			closeButton.src = "../img/black-cross.gif";
			closeButton.style.width = "12px";
			closeButton.style.height = "12px";
			closeButton.style.cursor = "pointer";
			closeButton.style.cssFloat = "right";
			closeButton.style.styleFloat = "right";
			closeButton.onclick = function() { statusDiv.parentNode.removeChild(statusDiv); };

		statusDiv.innerHTML = "";
		statusDiv.appendChild(closeButton);


		if (fileDetails.error) {
			// There was an error!  Display it.
			element.input.replaceInput(element);
			statusDiv.className = "error";
			var b = statusDiv.appendChild(this.document.createElement("b"));
				b.appendChild(this.document.createTextNode(fileDetails.origName));
				b.title = fileDetails.origName;
			statusDiv.appendChild(this.document.createElement("br"));
			statusDiv.appendChild(this.document.createTextNode(fileDetails.error));
		} else {
			// translate the HFile object back into something we can use here
			fileObj = {
				id: fileDetails.file.getID(),
				origName: fileDetails.file.getOriginalName(),
				url: fileDetails.file.getURL(),
				fileSize: fileDetails.file.getSize()
			};

			var b = statusDiv.appendChild(this.document.createElement("b"));
				b.appendChild(this.document.createTextNode(fileObj.origName));
				b.title = fileObj.origName;
			statusDiv.appendChild(this.document.createElement("br"));
			statusDiv.appendChild(this.document.createTextNode("File has been uploaded"));

			// update the BibDetailFileInput to show the file
			element.input.replaceInput(element, { file: fileObj });
			windowRef.changed();
		}
	},

	createInputsForReftype: function(reftypeID, bdValues, container) {
		var bdrs = top.HEURIST.edit.getBibDetailRequirements(reftypeID);
		if (! container.ownerDocument) {
			var elt = container;
			do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
			var documentRef = elt;
		} else {
			var documentRef = container.ownerDocument;
		}
		var windowRef = documentRef.parentWindow  ||  documentRef.defaultView  ||  this.document._parentWindow;

		var inputs = [];

		var defaultURL = (windowRef.parent.HEURIST.record  &&  windowRef.parent.HEURIST.record.url)? windowRef.parent.HEURIST.record.url : "";
		var required = (reftypeID == 1);	// URL input is only REQUIRED for internet bookmark
		var URLInput = new top.HEURIST.edit.inputs.BibURLInput(container, defaultURL, required);
		top.HEURIST.edit.allInputs.push(URLInput);
		inputs.push(URLInput);

		var order = top.HEURIST.edit.getBibDetailOrder(reftypeID);

		var i, l = order.length;
		for (i = 0; i < l; ++i) {
			var bdtID = order[i];
			if (bdrs[bdtID][3] == "X") continue;

			var newInput = top.HEURIST.edit.createInput(bdtID, reftypeID, bdValues[bdtID] || [], container);
			inputs.push(newInput);
		}

		return inputs;
	},

	createInputsNotForReftype: function(reftypeID, bdValues, container) {
		var bdrs = top.HEURIST.edit.getBibDetailRequirements(reftypeID);

		var inputs = [];
		for (var bdtID in bdValues) {
			if (bdrs  &&  bdrs[bdtID]) continue;

			var input = top.HEURIST.edit.createInput(bdtID, 0, bdValues[bdtID] || [], container);
			//input.setReadonly(true);
			inputs.push(input);
		}

		return inputs;
	},

	requiredInputsOK: function(inputs, windowRef) {
		// Return true if and only if all required fields have been filled in.
		// Otherwise, display a terse message describing missing fields.

		var missingFields = [];
		var firstInput = null;
		for (var i=0; i < inputs.length; ++i) {
			if (inputs[i].required !== "required") continue;

			if (! inputs[i].verify()) {
				// disaster! incomplete input
/*
				var niceName = inputs[i].bibDetailRequirements[0].toLowerCase();
				    niceName = niceName.substring(0, 1).toUpperCase() + niceName.substring(1);
*/
				missingFields.push("\"" + inputs[i].shortName + "\" field requires " + inputs[i].typeDescription);

				if (! firstInput) firstInput = inputs[i];
			}
		}

		if (windowRef.HEURIST.uploadsInProgress  &&  windowRef.HEURIST.uploadsInProgress.counter > 0) {
			// can probably FIXME if it becomes an issue ... register an autosave with the upload completion handler
			alert("File uploads are in progress ... please wait");
			return false;
		}

		if (missingFields.length == 0) return true;

		if (missingFields.length == 1) {
			alert("There was a problem with one of your inputs:\n" + missingFields[0]);
		} else {	// many errors
			alert("There were problems with your inputs:\n - " + missingFields.join("\n - "));
		}

		firstInput.focus();
		return false;
	},

	createDraggableTextarea: function(name, value, parentElement, options) {
		var elt = parentElement;
		do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
		var ownerDocument = elt;

		var positionDiv = ownerDocument.createElement("div");
			positionDiv.expando = true;
			positionDiv.isScratchpad = true;
			positionDiv.className = "draggable";
			if (options  &&  options.style) {
				for (var property in options.style) {
					if (property !== "width"  &&  property !== "height")
						positionDiv.style[property] = options.style[property];
				}
			}

		var newTable = positionDiv.appendChild(ownerDocument.createElement("table"));
			newTable.border = 0;
			newTable.cellSpacing = 0;
			newTable.cellPadding = 0;
		var newTbody = newTable.appendChild(ownerDocument.createElement("tbody"));

		var headerTd = newTbody.appendChild(ownerDocument.createElement("tr")).appendChild(ownerDocument.createElement("td"));
			headerTd.className = "header";

			// resize handle
			var resizeDiv = ownerDocument.createElement("div");
				resizeDiv.className = "resize-div";
				resizeDiv.onmousemove = function() { return false; };
			top.HEURIST.registerEvent(resizeDiv, "mousedown", function(e) { return top.HEURIST.util.startResize(e, newTextarea, true); });
			headerTd.appendChild(resizeDiv);

			// minimise button
			var minimiseDiv = ownerDocument.createElement("div");
				minimiseDiv.className = "minimise-div";
			headerTd.appendChild(minimiseDiv);

			// description when not minimised
			var descriptionDiv = document.createElement("div");
				descriptionDiv.className = "description";
				if (options  &&  options.description) {
					descriptionDiv.innerHTML = options.description;
				} else {
					descriptionDiv.innerHTML = "click to drag";
				}
				top.HEURIST.registerEvent(descriptionDiv, "mousedown", function(e) {
					return top.HEURIST.util.startMove(e, positionDiv, true);
				});
			headerTd.appendChild(descriptionDiv);

			// short description when minimised
			var minDescriptionDiv = document.createElement("div");
				minDescriptionDiv.className = "min-description";
				if (options  &&  options.minimisedDescription) {
					minDescriptionDiv.innerHTML = options.minimisedDescription;
				} else {
					minDescriptionDiv.innerHTML = "click to restore";
				}
			headerTd.appendChild(minDescriptionDiv);

			// add minimise / restore functions
			var minimise = function() {
				positionDiv.className += " minimised";
				positionDiv.oldBottom = positionDiv.style.bottom;
				positionDiv.oldRight = positionDiv.style.right;
				positionDiv.oldMinWidth = positionDiv.style.minWidth;
				positionDiv.style.bottom = "";
				positionDiv.style.right = "";
				positionDiv.style.minWidth = "";
				headerTd.removeChild(descriptionDiv);
				top.HEURIST.util.setDisplayPreference("scratchpad", "hide");
			};

			var restore = function() {
				positionDiv.className = positionDiv.className.replace(/ minimised/g, "");
				positionDiv.style.bottom = positionDiv.oldBottom;
				positionDiv.style.right = positionDiv.oldRight;
				positionDiv.style.minWidth = positionDiv.oldMinWidth;
				headerTd.insertBefore(descriptionDiv, minDescriptionDiv);
				top.HEURIST.util.setDisplayPreference("scratchpad", "show");
			};

			top.HEURIST.registerEvent(minimiseDiv, "click", minimise);
			top.HEURIST.registerEvent(minDescriptionDiv, "click", restore);

			if (top.HEURIST.util.getDisplayPreference("scratchpad") == "hide") {
				minimise();
			}


		var textareaTd = newTbody.appendChild(ownerDocument.createElement("tr")).appendChild(ownerDocument.createElement("td"));
			var iframeHack = textareaTd.appendChild(ownerDocument.createElement("iframe"));
				iframeHack.frameBorder = 0;	// cover any dropdowns etc; textarea then goes on top of the iframe
			positionDiv.expando = true;
			positionDiv.iframe = iframeHack;
		var newTextarea = textareaTd.appendChild(ownerDocument.createElement("textarea"));
			newTextarea.name = name;
			newTextarea.value = value;
			newTextarea.className = "in";
			if (options  &&  options.style) {
				if (options.style.width) iframeHack.style.width = newTextarea.style.width = options.style.width;
				if (options.style.height) iframeHack.style.height = newTextarea.style.height = options.style.height;
				if (options.style.minWidth) newTextarea.style.minWidth = options.style.minWidth;
				if (options.style.minHeight) newTextarea.style.minHeight = options.style.minHeight;
			}

		if (options.scratchpad) {
			newTextarea.expando = true;
			newTextarea.isScratchpad = true;
			positionDiv.style.zIndex = 100001;
			newTextarea.id = "notes";
		}

		parentElement.appendChild(positionDiv);

		return newTextarea;
	},

	makeDateButton: function(dateBox, doc) {
		var buttonElt = doc.createElement("input");
			buttonElt.type = "button";
			buttonElt.className = "date-button";
		if (dateBox.nextSibling)
			dateBox.parentNode.insertBefore(buttonElt, dateBox.nextSibling);
		else
			dateBox.parentNode.appendChild(buttonElt);

		var popupOptions = {
			callback: function(date) {
				if (date) {
					dateBox.value = date;
					windowRef.changed();
				}
			},
			"close-on-blur": true,
			"no-titlebar": true,
			"no-resize": true
		};

		var windowRef = doc.parentWindow  ||  doc.defaultView  ||  this.document._parentWindow;
		buttonElt.onclick = function() {
			var buttonPos = top.HEURIST.getPosition(buttonElt, true);
			popupOptions.x = buttonPos.x + 8 - 120;
			popupOptions.y = buttonPos.y + 8 - 80;

			top.HEURIST.util.popupURL(windowRef,top.HEURIST.basePath + "common/lib/calendar.html#"+dateBox.value, popupOptions);
		}

		return buttonElt;
	},

	makeTemporalButton: function(dateBox, doc) {
		var buttonElt = doc.createElement("input");
			buttonElt.type = "button";
			buttonElt.title = "Create a fuzzy date";
			buttonElt.className = "temporal-button";
		if (dateBox.nextSibling)
			dateBox.parentNode.insertBefore(buttonElt, dateBox.nextSibling);
		else
			dateBox.parentNode.appendChild(buttonElt);
		function decodeValue (inputStr) {
			var str = inputStr;
			if (str.search(/\|/) != -1) {
				dateBox.disabled = true;
				if (str.search(/SRT/) != -1 && str.match(/SRT=([^\|]+)/)) {
					str = str.match(/SRT=([^\|]+)/)[1];
				}else if (str.search(/TYP=s/) != -1 ) {
					if (str.match(/DAT=([^\|]+)/)) {
						str = str.match(/DAT=([^\|]+)/)[1];
						dateBox.disabled = false;
					}else if (str.search(/COM=[^\|]+/) != -1) {
						str = str.match(/COM=([^\|]+)/)[1];
						dateBox.disabled = false;
					}
				}
			}
			return str;
		}
		if (dateBox.value) {
			dateBox.value = decodeValue(dateBox.value);
		}
		var popupOptions = {
			callback: function(str) {
				dateBox.strTemporal = str;
				dateBox.value = decodeValue(str);
				if( dateBox.strTemporal != dateBox.value) {
					windowRef.changed();
				}
			},
			width: "700",
			height: "500"
		};

		var windowRef = doc.parentWindow  ||  doc.defaultView  ||  this.document._parentWindow;
		buttonElt.onclick = function() {
			var buttonPos = top.HEURIST.getPosition(buttonElt, true);
			popupOptions.x = buttonPos.x + 8 - 380;
			popupOptions.y = buttonPos.y + 8 - 380;

			top.HEURIST.util.popupURL(windowRef, top.HEURIST.basePath + "data/temporal/temporal.html?" + (dateBox.strTemporal ? dateBox.strTemporal : dateBox.value), popupOptions);
		}

		return buttonElt;
	},

	makeWiki: function(document, title, name, precis) {
		if (! precis) precis = "";

		var tr = document.createElement("tr");
			tr.className = "wiki";
		var td = tr.appendChild(document.createElement("td"));
			td.className = "wiki-name";
			td.innerHTML = title;

		td = tr.appendChild(document.createElement("td"));
		if (precis) {
			var precisSpan = td.appendChild(document.createElement("span"));
				precisSpan.className = "precis";
				precisSpan.appendChild(document.createTextNode(precis + " "));
		}

		if (precis) {
			var wikiLinkSpan = td.appendChild(document.createElement("span"));
				wikiLinkSpan.className = "wiki-link";
			var wikiLink = wikiLinkSpan.appendChild(document.createElement("a"));
				wikiLink.href = "/tmwiki/index.php/" + name;
				wikiLink.target = "_wiki_edit";
				wikiLink.appendChild(document.createTextNode("view"));
		}

		td.appendChild(document.createTextNode(" "));
		var wikiLinkSpan = td.appendChild(document.createElement("span"));
			wikiLinkSpan.className = "wiki-link";
		var wikiLink = wikiLinkSpan.appendChild(document.createElement("a"));
			wikiLink.href = "/tmwiki/index.php/" + name + "?action=edit";
			wikiLink.target = "_wiki_edit";
			wikiLink.appendChild(document.createTextNode(precis? "edit" : "add"));

		return tr;
	},

	addOption: function(document, dropdown, text, value) {
		var newOption = document.createElement("option");
			newOption.value = value;
			newOption.appendChild(document.createTextNode(text));
		return dropdown.appendChild(newOption);
	}
};

top.HEURIST.edit.inputs = { };

top.HEURIST.edit.inputs.BibDetailInput = function(bibDetailType, bibDetailRequirements, bdValues, parentElement) {
	if (arguments.length == 0) return;	// for prototyping
	var thisRef = this;

	this.bibDetailType = bibDetailType;
	this.bibDetailRequirements = bibDetailRequirements;
	this.parentElement = parentElement;
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;
	this.shortName = bibDetailRequirements[0];

	var required = bibDetailRequirements[3];
		if (required == "O") required = "optional";
		else if (required == "Y") required = "required";
		else if (required == "R") required = "recommended";
		else required = "";
	this.required = required;

	this.repeatable = (bibDetailRequirements[4] == "1")? true : false;

	this.row = parentElement.appendChild(this.document.createElement("tr"));
		this.row.className = "input-row " + required;

	this.headerCell = this.row.appendChild(this.document.createElement("td"));
		this.headerCell.className = "input-header-cell";
		this.headerCell.appendChild(this.document.createTextNode(bibDetailRequirements[0]));	// bdr_name
	if (this.repeatable) {
		var dupImg = this.headerCell.appendChild(this.document.createElement('img'));
			dupImg.src = top.HEURIST.basePath + "common/images/duplicate.gif";
			dupImg.className = "duplicator";
			dupImg.alt = dupImg.title = "Add another " + bibDetailRequirements[0] + " field";
			top.HEURIST.registerEvent(dupImg, "click", function() { thisRef.duplicateInput.call(thisRef); } );
	}

	this.inputCell = this.row.appendChild(this.document.createElement("td"));
		this.inputCell.className = "input-cell";

	// make sure that the promptDiv is the last item in the input cell
	this.promptDiv = this.inputCell.appendChild(this.document.createElement("div"));
		this.promptDiv.className = "help prompt";
		this.promptDiv.innerHTML = bibDetailRequirements[1];

	this.inputs = [];
	if (this.repeatable) {
		for (var i=0; i < bdValues.length; ++i) {
			this.addInput(bdValues[i]);
		}
		if (bdValues.length == 0) {
			this.addInput();	// add an empty input
		}
	} else {
		if (bdValues.length > 0) {
			this.addInput(bdValues[0]);
		} else {
			this.addInput();
		}
	}
};
top.HEURIST.edit.inputs.BibDetailInput.prototype.focus = function() { this.inputs[0].focus(); };
top.HEURIST.edit.inputs.BibDetailInput.prototype.setReadonly = function(readonly) {
	if (readonly) {
		this.inputCell.className += " readonly";
	} else {
		this.inputCell.className = this.inputCell.className.replace(/\s*\breadonly\b/, "");
	}
	for (var i=0; i < this.inputs.length; ++i) {
		this.inputs[i].readOnly = readonly;
	}
};
top.HEURIST.edit.inputs.BibDetailInput.prototype.duplicateInput = function() { this.addInput(); };
top.HEURIST.edit.inputs.BibDetailInput.prototype.addInputHelper = function(bdValue, element) {
	this.elementName = "type:" + this.bibDetailType[0];
		element.name = (bdValue && bdValue.id)? (this.elementName + "[bd:" + bdValue.id + "]") : (this.elementName + "[]");
		element.title = this.bibDetailRequirements[0];
		element.setAttribute("bib-detail-type", this.bibDetailType[0]);
		var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

		if (element.value !== undefined) {	/* if this is an input element, register the onchange event */
			top.HEURIST.registerEvent(element, "change", function() { windowRef.changed(); });

			/* also, His Nibs doesn't like the fact that onchange doesn't fire until after the user has moved to another field,
			 * so ... jeez, I dunno.  onkeypress?
			 */
			top.HEURIST.registerEvent(element, "keypress", function() { windowRef.changed(); });
		}
	if (this.bibDetailType[2] === "resource" || this.bibDetailType[2] === "relmarker") {	// bdt_type
		if (this.bibDetailType[3]) {	// bdt_constrain_reftype
			this.constrainReftype = this.bibDetailType[3]; // saw TODO  modify this to validate the list first.
		}
		else	this.constrainReftype = 0;
	}
	if (parseFloat(this.bibDetailRequirements[5]) > 0) {	//if the size is greater than zero
		element.style.width = Math.round(4/3 * this.bibDetailRequirements[5]) + "ex";
	}

	element.expando = true;
	element.input = this;
	element.bdID = bdValue? bdValue.id : null;

	this.inputs.push(element);
	this.inputCell.insertBefore(element, this.promptDiv);

	if ((this.bibDetailType[0] === "198"  ||  this.bibDetailType[0] === "187"  ||  this.bibDetailType[0] === "188")
			&&  bdValue  &&  bdValue.value) { // DOI, ISBN, ISSN
		this.webLookup = this.document.createElement("a");
		if (this.bibDetailType[0] === "198") {
			this.webLookup.href = "http://dx.doi.org/" + bdValue.value;
		} else if (this.bibDetailType[0] === "187") {
			// this.webLookup.href = "http://www.biblio.com/search.php?keyisbn=" + bdValue.value;	// doesn't work anymore
			// this.webLookup.href = "http://www.biblio.com/isbnmulti.php?isbns=" + encodeURIComponent(bdValue.value) + "&stage=1";	// requires POST
			this.webLookup.href = "http://www.biblio.com/search.php?keyisbn=" + encodeURIComponent(bdValue.value);
		} else if (this.bibDetailType[0] === "188") {
			var matches = bdValue.value.match(/(\d{4})-?(\d{3}[\dX])/);
			if (matches) {
				this.webLookup.href = "http://www.oclc.org/firstsearch/periodicals/results_issn_search.asp?database=%25&fulltext=%22%22&results=paged&PageSize=25&issn1=" + matches[1] + "&issn2=" + matches[2];
			}
		}

		if (this.webLookup.href) {
			this.webLookup.target = "_blank";
			var span = this.document.createElement("span");
				span.style.paddingLeft = "20px";
				span.style.lineHeight = "16px";
				span.style.backgroundImage = "url("+top.HEURIST.basePath+"common/images/external_link_16x16.gif)";
				span.style.backgroundRepeat = "no-repeat";
				span.style.backgroundPosition = "center left";
			span.appendChild(this.document.createTextNode("look up"));
			this.webLookup.appendChild(span);
			this.inputCell.insertBefore(this.webLookup, this.promptDiv);
		}
	}

};
top.HEURIST.edit.inputs.BibDetailInput.prototype.getValue = function(input) { return { value: input.value }; };
top.HEURIST.edit.inputs.BibDetailInput.prototype.getPrimaryValue = function(input) { return input? input.value : ""; };
top.HEURIST.edit.inputs.BibDetailInput.prototype.inputOK = function(input) {
	// Return false only if the input doesn't match the regex for this input type
	if (! this.regex) return true;
	if (this.getPrimaryValue(input).match(this.regex)) return true;
	return false;
};
top.HEURIST.edit.inputs.BibDetailInput.prototype.verify = function() {
	// Return true if at least one of this input's values is okay
	for (var i=0; i < this.inputs.length; ++i) {
		if (this.inputOK(this.inputs[i])) return true;
	}
	return false;
};
top.HEURIST.edit.inputs.BibDetailInput.prototype.getValues = function() {
	// Return JS object representing the value(s) of this input
	var values = [];
	for (var i=0; i < this.inputs.length; ++i) {
		if (this.inputOK(this.inputs[i])) {
			var inputValue = this.getValue(this.inputs[i]);
			if (this.inputs[i].bdID) inputValue.id = this.inputs[i].bdID;
		}
		values.push(inputValue);
	}
	return values;
};

top.HEURIST.edit.inputs.BibDetailFreetextInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.regex = new RegExp("\\S");	// text field is okay if it contains non-whitespace
top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.typeDescription = "a text value";
top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.addInput = function(bdValue) {
	var newInput = this.document.createElement("input");
		newInput.setAttribute("autocomplete", "off");
		newInput.type = "text";
		newInput.className = "in";
		if (bdValue) newInput.value = bdValue.value;

		this.addInputHelper.call(this, bdValue, newInput);
};

top.HEURIST.edit.inputs.BibDetailIntegerInput = function() { top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype.typeDescription = "an integer value";
top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype.regex = new RegExp("^\\s*-?\\d+\\s*$");	// obvious integer regex

top.HEURIST.edit.inputs.BibDetailFloatInput = function() { top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailFloatInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.typeDescription = "a numeric value";
top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.regex = new RegExp("^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+))\\s*$");	// extended float regex

top.HEURIST.edit.inputs.BibDetailYearInput = function() { top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailYearInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
top.HEURIST.edit.inputs.BibDetailYearInput.prototype.typeDescription = "a year, or \"in press\"";
top.HEURIST.edit.inputs.BibDetailYearInput.prototype.regex = new RegExp("^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$", "i");

top.HEURIST.edit.inputs.BibDetailDateInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailDateInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
top.HEURIST.edit.inputs.BibDetailDateInput.prototype.addInput = function(bdValue) {
	var newDiv = this.document.createElement("div");
		newDiv.className = "date-div";
		newDiv.expando = true;
		newDiv.style.whiteSpace = "nowrap";
	this.addInputHelper.call(this, bdValue, newDiv);

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
		textElt.setAttribute("autocomplete", "off");
		textElt.name = newDiv.name;
		textElt.value = bdValue? bdValue.value : "";
		textElt.className = "in";
		textElt.style.width = newDiv.style.width;
		newDiv.style.width = "";
		top.HEURIST.registerEvent(textElt, "change", function() { windowRef.changed(); });

	top.HEURIST.edit.makeDateButton(textElt, this.document);
};
top.HEURIST.edit.inputs.BibDetailDateInput.prototype.getPrimaryValue = function(input) { return input? input.textElt.value : ""; };
top.HEURIST.edit.inputs.BibDetailDateInput.prototype.typeDescription = "a date value";
top.HEURIST.edit.inputs.BibDetailDateInput.prototype.regex = new RegExp("\\S");

top.HEURIST.edit.inputs.BibDetailTemporalInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.addInput = function(bdValue) {
	var newDiv = this.document.createElement("div");
		newDiv.className = "temporal-div";
		newDiv.expando = true;
		newDiv.style.whiteSpace = "nowrap";
	this.addInputHelper.call(this, bdValue, newDiv);

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
		textElt.setAttribute("autocomplete", "off");
		textElt.name = newDiv.name;
		textElt.value = bdValue? bdValue.value : "";
		textElt.className = "in";
		textElt.title = "Enter date";  //sw
		textElt.style.width = newDiv.style.width;
		newDiv.style.width = "";
		top.HEURIST.registerEvent(textElt, "change", function() { windowRef.changed(); });
//	var isTemporal = /^\|\S\S\S=/.test(textElt.value);		//sw check the beginning of the string for temporal format
//	var isDate = ( !isTemporal && /\S/.test(textElt.value));	//sw not temporal and has non - white space must be a date
//	if (!isTemporal) top.HEURIST.edit.makeDateButton(textElt, this.document); //sw
	top.HEURIST.edit.makeTemporalButton(textElt, this.document); //sw

};

top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.getPrimaryValue = function(input) { return input? input.textElt.value : ""; };
top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.typeDescription = "a temporal value";
top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.regex = new RegExp("\\S");


top.HEURIST.edit.inputs.BibDetailBlocktextInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.typeDescription = "a text value";
top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.addInput = function(bdValue) {
	var newInput = this.document.createElement("textarea");
		newInput.rows = "3";
		newInput.className = "in";
		if (bdValue) newInput.value = bdValue.value;

	this.addInputHelper.call(this, bdValue, newInput);
};

top.HEURIST.edit.inputs.BibDetailResourceInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.focus = function() { this.inputs[0].textElt.focus(); };
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.regex = new RegExp("^[1-9]\\d*$");
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.typeDescription = "a record";
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.addInput = function(bdValue) {
	var thisRef = this;	// provide input reference for closures

	var newDiv = this.document.createElement("div");
		newDiv.className = bdValue? "resource-div" : "resource-div empty";
		newDiv.expando = true;
	this.addInputHelper.call(this, bdValue, newDiv);

	var editImg = newDiv.appendChild(this.document.createElement("img"));
		editImg.src = top.HEURIST.basePath +"common/images/edit-pencil.gif";
		editImg.className = "edit-resource";
		editImg.title = "Edit this resource";

	var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
		textElt.type = "text";
		/* textElt doesn't need to have a name -- it's just for show
		textElt.name = "text-" + newDiv.name;
		 */
		textElt.value = textElt.defaultValue = bdValue? bdValue.title : "";
		textElt.title = "Click here to search for a record, or drag-and-drop a search value";
		textElt.setAttribute("autocomplete", "off");
		textElt.className = "resource-title";
		textElt.onkeypress = function(e) {
			// refuse non-tab key-input
			if (! e) e = window.event;

			if (! newDiv.readOnly  &&  e.keyCode != 9  &&  ! (e.ctrlKey  ||  e.altKey  ||  e.metaKey)) {
				// invoke popup
				thisRef.chooseResource(newDiv);
				return false;
			}
			else return true;	// allow tab or control/alt etc to do their normal thing (cycle through controls)
		};
		top.HEURIST.registerEvent(textElt, "click", function() { if (! newDiv.readOnly) thisRef.chooseResource(newDiv); });
		top.HEURIST.registerEvent(textElt, "mouseup", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });
		top.HEURIST.registerEvent(textElt, "mouseover", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });
	var hiddenElt = newDiv.hiddenElt = this.document.createElement("input");
		hiddenElt.name = newDiv.name;
		hiddenElt.value = hiddenElt.defaultValue = bdValue? bdValue.value : "0";
		hiddenElt.type = "hidden";
		newDiv.appendChild(hiddenElt);	// have to do this AFTER the type is set

	top.HEURIST.registerEvent(editImg, "click", function() {
		top.HEURIST.util.popupURL(window,top.HEURIST.basePath +"data/records/editrec/mini-edit.html?bib_id=" + hiddenElt.value, {
			callback: function(bibTitle) { if (bibTitle) textElt.defaultValue = textElt.value = bibTitle; }
		});
	});

	var removeImg = newDiv.appendChild(this.document.createElement("img"));
		removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
		removeImg.className = "delete-resource";
		removeImg.title = "Remove this resource reference";
		var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
		top.HEURIST.registerEvent(removeImg, "click", function() {
			if (! newDiv.readOnly) {
				thisRef.clearResource(newDiv);
				windowRef.changed();
			}
		});

	if (window.HEURIST.parameters["title"]  &&  bdValue  &&  bdValue.title  &&  windowRef.parent.frameElement) {
		// we've been given a search string for a record pointer field - pop up the search box
		top.HEURIST.registerEvent(windowRef.parent.frameElement, "heurist-finished-loading-popup", function() {
			thisRef.chooseResource(newDiv, bdValue.title);
		});
	}
};
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.getPrimaryValue = function(input) { return input? input.hiddenElt.value : ""; };
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.chooseResource = function(element, searchValue) {
	if (this.choosing) return;	// we are already choosing a resource!
	this.choosing = true;
	var thisRef = this;

	if (! searchValue) searchValue = element.textElt.value;
	var url = top.HEURIST.basePath+"data/pointer/bib-list.html?q="+encodeURIComponent(searchValue)
	if (element.input.constrainReftype)
		url += "&t="+element.input.constrainReftype;
	top.HEURIST.util.popupURL(window, url, {
		callback: function(bibID, bibTitle) {
			if (bibID) element.input.setResource(element, bibID, bibTitle);
			thisRef.choosing = false;
			setTimeout(function() { element.textElt.focus(); }, 100);
		}
	} );
};
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.clearResource = function(element) { this.setResource(element, 0, ""); };
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.setResource = function(element, bibID, bibTitle) {
	element.textElt.title = element.textElt.value = element.textElt.defaultValue = bibTitle? bibTitle : "";
	element.hiddenElt.value = element.hiddenElt.defaultValue = bibID? bibID : "0";
	if (bibID) {
		element.className = element.className.replace(/(^|\s+)empty(\s+|$)/g, " ");
	} else if (! element.className.match(/(^|\s+)empty(\s+|$)/)) {
		element.className += " empty";
	}
	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	windowRef.changed();
};

top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.handlePossibleDragDrop = function(input, element) {
	/*
	 * Invoked by the mouseup property on resource textboxes.
	 * We can't reliably detect a drag-drop action, but this is our best bet:
	 * if the mouse is released over the textbox and the value is different from what it *was*,
	 * then automatically popup the search-for-resource box.
	 */
	if (element.textElt.value != element.textElt.defaultValue  &&  element.textElt.value != "") {
		var searchValue = this.calculateDroppedText(element.textElt.defaultValue, element.textElt.value);

		// pause, then clear search value
		setTimeout(function() { element.textElt.value = element.textElt.defaultValue; }, 1000);

		element.input.chooseResource(element, searchValue);
	}
};
top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.calculateDroppedText = function(oldValue, newValue) {
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
};

top.HEURIST.edit.inputs.BibDetailBooleanInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.regex = new RegExp("^(?:true|false)$");
top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.typeDescription = "either \"yes\" or \"no\"";
top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.addInput = function(bdValue) {
	var newInput = this.document.createElement("select");
		top.HEURIST.edit.addOption(this.document, newInput, "", "");
		top.HEURIST.edit.addOption(this.document, newInput, "yes", "true");
		top.HEURIST.edit.addOption(this.document, newInput, "no", "false");

	if (! bdValue) {
		newInput.selectedIndex = 0;
	} else if (bdValue.value === "no"  ||  bdValue.value === "false") {
		newInput.selectedIndex = 2;
	} else if (bdValue.value) {
		newInput.selectedIndex = 1;
	} else {
		newInput.selectedIndex = 0;
	}

	this.addInputHelper.call(this, bdValue, newInput);
	newInput.style.width = "4em";	// Firefox for Mac workaround -- otherwise dropdown is too narrow (has to be done after inserting into page)
};


top.HEURIST.edit.inputs.BibDetailDropdownInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.getPrimaryValue = function(input) { return input? (input.selectedIndex !== -1 && input.options[input.selectedIndex].value) : ""; };
top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.typeDescription = "a value from the dropdown";
top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.regex = new RegExp(".");
top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.addInput = function(bdValue) {
	var allOptions = top.HEURIST.bibDetailLookups[this.bibDetailType[0]]  ||  [];

	var newInput = this.document.createElement("select");
		var newOption = newInput.appendChild(this.document.createElement("option"));	// default blank option
			newOption.text = "";
			newOption.value = "";
		var selectIndex = 0;
		for (var ont in allOptions) {
			var grp = document.createElement("optgroup");
			grp.label = top.HEURIST.ontologyLookup[ont];
			newInput.appendChild(grp);
			for (var i = 0; i < allOptions[ont].length; ++i) {
				var rdl = allOptions[ont][i];
				newInput.options[newInput.length] = new Option(rdl[1], rdl[0]);
				if (bdValue && bdValue.value == rdl[0]) {
					selectIndex = newInput.length - 1;
				}
			}
		}
	newInput.selectedIndex = selectIndex;
	this.addInputHelper.call(this, bdValue, newInput);
	newInput.style.width = "auto";
};


top.HEURIST.edit.inputs.BibDetailFileInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailFileInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.focus = function() { try { this.inputs[0].valueElt.focus(); } catch(e) { } };
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.typeDescription = "a file";
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.addInput = function(bdValue) {
	var newDiv = this.document.createElement("div");
	this.addInputHelper.call(this, bdValue, newDiv);

	this.constructInput(newDiv, bdValue);
};
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.replaceInput = function(inputDiv, bdValue) {
	inputDiv.innerHTML = "";
	this.constructInput(inputDiv, bdValue);
};
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.constructInput = function(inputDiv, bdValue) {
	var thisRef = this;	// for great closure
	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

	if (bdValue  &&  bdValue.file) {
		// A pre-existing file: just display details and a remove button
		var hiddenElt = inputDiv.hiddenElt = this.document.createElement("input");
			hiddenElt.name = inputDiv.name;
			hiddenElt.value = hiddenElt.defaultValue = (bdValue && bdValue.file)? bdValue.file.id : "0";
			hiddenElt.type = "hidden";
			inputDiv.appendChild(hiddenElt);

		var link = inputDiv.appendChild(this.document.createElement("a"));
			if (bdValue.file.nonce) {
				link.href = top.HEURIST.basePath+"data/files/fetch_file.php/" + /*encodeURIComponent(bdValue.file.origName)*/
								"?file_id=" + encodeURIComponent(bdValue.file.nonce);
			} else if (bdValue.file.url) {
				link.href = bdValue.file.url;
			}
			link.target = "_surf";
			link.onclick = function() { top.open(link.href, "", "width=300,height=200,resizable=yes"); return false; };

		link.appendChild(this.document.createTextNode(bdValue.file.origName));

		var linkImg = link.appendChild(this.document.createElement("img"));
			linkImg.src = top.HEURIST.basePath+"common/images/external_link_16x16.gif";
			linkImg.className = "link-image";

		var fileSizeSpan = inputDiv.appendChild(this.document.createElement("span"));
			fileSizeSpan.className = "file-size";
			fileSizeSpan.appendChild(this.document.createTextNode("[" + bdValue.file.fileSize + "]"));

		var removeImg = inputDiv.appendChild(this.document.createElement("img"));
			removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
			removeImg.className = "delete-file";
			removeImg.title = "Remove this file";
			var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
			top.HEURIST.registerEvent(removeImg, "click", function() {
				thisRef.removeFile(inputDiv);
				windowRef.changed();
			});
		inputDiv.valueElt = hiddenElt;
		inputDiv.className = "file-div";

	} else {
		if (top.HEURIST.browser.isEarlyWebkit) {
			var newIframe = this.document.createElement("iframe");
				newIframe.src = top.HEURIST.basePath+"data/records/editrec/mini-file-upload.php?bib_id=" + windowRef.parent.HEURIST.record.bibID + "&bdt_id=" + this.bibDetailType[0];
				newIframe.frameBorder = 0;
				newIframe.style.width = "90%";
				newIframe.style.height = "2em";
			inputDiv.appendChild(newIframe);

			newIframe.submitFunction = function(fileDetails) {
				inputDiv.input.replaceInput(inputDiv, fileDetails);
			};
		}
		else {
			var fileElt = this.document.createElement("input");
				fileElt.name = inputDiv.name;
				fileElt.className = "file-select";
				fileElt.type = "file";

			inputDiv.appendChild(fileElt);

			var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
				// top.HEURIST.registerEvent(fileElt, "change", function() { windowRef.changed(); });
				// (nowadays an uploaded file is automatically saved to the relevant record)
			inputDiv.valueElt = fileElt;

			var thisRef = this;
			fileElt.onchange = function() { top.HEURIST.edit.uploadFileInput.call(thisRef, fileElt); };
			inputDiv.className = "file-div empty";
		}
	}

// FIXME: change references to RESOURCE to RECORD
// FIXME: make sure that all changed() calls are invoked (esp. RESOURCES -- chooseResource, deleteResource ...)
};
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.removeFile = function(input) {
	// Remove the given file ... this involves hiding the hidden field, setting its value to 0, and perhaps adding a new file input
	if (input.hiddenElt) {
		input.hiddenElt.value = "";
		input.style.display = "none";
	} else {
		input.parentNode.removeChild(input);
	}

	for (var i=0; i < this.inputs.length; ++i) {
		if (input === this.inputs[i]) {
			this.inputs.splice(i, 1);	// remove this input from the list
			break;
		}
	}

	if (this.inputs.length == 0) {	// Oh no!  We just deleted the only file input.  Build a new one (with the old bd_id if had one)
		if (input.bdID) {
			this.addInput({ id: input.bdID });
		} else {
			this.addInput();
		}
	}

	// FIXME: window.changed()
};
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.getPrimaryValue = function(input) { return input? input.valueElt.value : ""; };
top.HEURIST.edit.inputs.BibDetailFileInput.prototype.regex = new RegExp("\\S");


top.HEURIST.edit.inputs.BibDetailGeographicInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.regex = new RegExp("^(?:p|c|r|pl|l) (?:point|polygon|linestring)\\(?\\([-0-9.+, ]+?\\)\\)?$", "i");
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.getPrimaryValue = function(input) { return input? input.input.value : ""; };
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.typeDescription = "a geographic value";
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setReadonly = function(readonly) {
	this.prototype.setReadonly.apply(this, readonly);
	for (var i=0; i < this.inputs.length; ++i) {
		this.inputs[i].editLink.innerHTML = readonly? "view" : "edit";
	}
};
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.geoValueToDescription = function(geo) {
	function R(x) { return Math.round(x*10)/10; }

	var geoSummary;
	var geoType = geo.type.charAt(0).toUpperCase() + geo.type.substring(1);
	if (geo.type == "point") {
		geoSummary = "X ("+R(geo.x)+") - Y ("+R(geo.y)+")";
	} else {
		geoSummary = "X ("+R(geo.minX)+","+R(geo.maxX)+") - Y ("+R(geo.minY)+","+R(geo.maxY)+")";
	}

	return { type: geoType, summary: geoSummary };
};
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.wktValueToDescription = function(wkt) {
	// parse a well-known-text value and return the standard description (type + summary)
	var matches = wkt.match(/^(p|c|r|pl|l) (?:point|polygon|linestring)\(?\(([-0-9.+, ]+?)\)/i);
	var typeCode = matches[1];

	var pointPairs = matches[2].split(/,/);
	var X = [], Y = [];
	for (var i=0; i < pointPairs.length; ++i) {
		var point = pointPairs[i].split(/\s+/);
		X.push(parseFloat(point[0]));
		Y.push(parseFloat(point[1]));
	}

	if (typeCode == "p") {
		var Xp = Math.round(X[0]*10) / 10;
		var Yp = Math.round(Y[0]*10) / 10;
		return { type: "Point", summary: "X ("+Xp+") - Y ("+Yp+")" };
	}
	else if (typeCode == "l") {
		return { type: "Path", summary: "X,Y ("+Math.round(X.shift()*10)/10+","+Math.round(Y.shift()*10)/10+") - ("+Math.round(X.pop()*10)/10+","+Math.round(Y.pop()*10)/10+")" };
	}
	else {
		X.sort();
		Y.sort();

		var type = "Unknown";
		if (typeCode == "pl") type = "Polygon";
		else if (typeCode == "r") type = "Rectangle";
		else if (typeCode == "c") type = "Circle";
		else if (typeCode == "l") type = "Path";

		var minX = Math.round(X[0] * 10) / 10;
		var minY = Math.round(Y[0] * 10) / 10;
		var maxX = Math.round(X.pop() * 10) / 10;
		var maxY = Math.round(Y.pop() * 10) / 10;
		return { type: type, summary: "X ("+minX+","+maxX+") - Y ("+minY+","+maxY+")" };
	}
};
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.addInput = function(bdValue) {
	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var thisRef = this;

	var newDiv = this.document.createElement("div");
		newDiv.className = (bdValue && bdValue.geo) ? "geo-div" : "geo-div empty";
	this.addInputHelper.call(this, bdValue, newDiv);

	var input = this.document.createElement("input");
	    input.type = "hidden";
		// This is a bit complicated:
		// We don't put in an input if there's already a value,
		// because MySQL says   bd_geo != geomfromtext(astext(bd_geo))   so we would get false deltas.
		// save-biblio-data.php leaves bib_detail rows alone if they are not mentioned in $_POST.
		// We give it the name (underscore + name), and only give it the proper name if we try to edit the value.
		if (bdValue  &&  bdValue.id  && ! bdValue.geo) {
			// as from removeGeo - we want to save this deletion!
			input.name = newDiv.name;
		} else {
			input.name = "_" + newDiv.name;
		}
	newDiv.appendChild(input);
	newDiv.input = input;

	var geoImg = this.document.createElement("img");
	    geoImg.src = top.HEURIST.basePath+"common/images/16x16.gif";
	    geoImg.className = "geo-image";
	    newDiv.appendChild(geoImg);

	var descriptionSpan = newDiv.appendChild(this.document.createElement("span"));
	    descriptionSpan.className = "geo-description";
	newDiv.descriptionSpan = descriptionSpan;

	var editLink = this.document.createElement("span")
	    newDiv.editLink = editLink;
	    editLink.className = "geo-edit";
	    editLink.onclick = function() {
			if (top.HEURIST.browser.isEarlyWebkit) {
				alert("Geographic objects use Google Maps API, which doesn't work on this browser - sorry");
				return;
			}

			HAPI.PJ.store("gigitiser_geo_object", input.value, {
				callback: function(_, _, response) {
					top.HEURIST.util.popupURL(
						windowRef,
						"gigitiser/?" + (response.success ? "edit" : encodeURIComponent(input.value)),	// FIXME: need to map this to new location of gigitiser
						{ callback: function(type, value) { thisRef.setGeo(newDiv, value? (type+" "+value) : ""); } }
					);
				}
			});

		};
	var editSpan = newDiv.appendChild(this.document.createElement("span"));
	    editSpan.appendChild(editLink);

	var removeImg = newDiv.appendChild(this.document.createElement("img"));
		removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
		removeImg.className = "delete-geo";
		removeImg.title = "Remove this geographic object";
		top.HEURIST.registerEvent(removeImg, "click", function() {
			thisRef.removeGeo(newDiv);
			windowRef.changed();
		});

	if (bdValue && bdValue.geo) {
		input.value = bdValue.geo.value;

		var description = this.geoValueToDescription(bdValue.geo);
		descriptionSpan.appendChild(this.document.createElement("b")).appendChild(this.document.createTextNode(" " + description.type));
		descriptionSpan.appendChild(this.document.createTextNode(" " + description.summary + " "));

		editLink.innerHTML = "edit";

	} else {
		descriptionSpan.innerHTML = " ";
		editLink.innerHTML = "add";
	}

	newDiv.style.width = "auto";
};
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setGeo = function(element, value) {
	if (! value) return; // "cancel"

	var description = this.wktValueToDescription(value);

	element.input.name = element.input.name.replace(/^_/, "");
	element.input.value = value;
	element.descriptionSpan.innerHTML = "";
	element.descriptionSpan.appendChild(this.document.createElement("b")).appendChild(this.document.createTextNode(" " + description.type));
	element.descriptionSpan.appendChild(this.document.createTextNode(" " + description.summary + " "));
	element.editLink.innerHTML = "edit";
	element.className = "geo-div";	// not empty

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	windowRef.changed();
};
top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.removeGeo = function(input) {
	input.parentNode.removeChild(input);

	for (var i=0; i < this.inputs.length; ++i) {
		if (input === this.inputs[i]) {
			this.inputs.splice(i, 1);	// remove this input from the list
			break;
		}
	}

	if (input.bdID) {
		this.addInput({ id: input.bdID });
	} else {
		this.addInput();
	}
};


top.HEURIST.edit.inputs.BibDetailUnknownInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype.typeDescription = "some value";
top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype.addInput = function(bdValue) {
	var allOptions = top.HEURIST.bibDetailLookups[this.bibDetailType[0]]  ||  [];

	var newInput = this.document.createElement("div");
		newInput.appendChild(this.document.createTextNode("Input type \"" + this.bibDetailType[2] + "\" not implemented"));
	this.addInputHelper.call(this, bdValue, newInput);
};

top.HEURIST.edit.inputs.BibDetailSeparator = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailSeparator.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailSeparator.prototype.typeDescription = "record details separator";
top.HEURIST.edit.inputs.BibDetailSeparator.prototype.addInput = function(bdValue) {

	var newInput = this.document.createElement("div");
		newInput.style.border = '1px solid grey';
		newInput.style.width = '105ex';	// this will effectively override the sizing control in the record definition, may have to remove
	this.addInputHelper.call(this, bdValue, newInput);
	if (this.promptDiv){
		this.promptDiv.className = "";
		this.promptDiv.style.display = "none";
		newInput.title = this.bibDetailRequirements[1];
	}
};

top.HEURIST.edit.inputs.BibDetailRelationMarker = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.typeDescription = "record relationship marker";
top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.changeNotification = function(cmd, relID) {
	this.windowRef.changed();
};
top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.addInput = function(bdValue) {

	this.windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var newInput = this.document.createElement("table");
	newInput.border = 0;
	newInput.cellpadding = 0;
	newInput.cellspacing = 0;
	newInput.style.marginLeft = "1ex";
	newInput.style.width = "100%";
	this.addInputHelper.call(this, bdValue, newInput);
	var tb = this.document.createElement("tbody");
	tb.id = "relations-tbody";
	newInput.appendChild(tb);
	var relatedRecords = parent.HEURIST.record.relatedRecords;
	this.relManager = new RelationManager(tb,top.HEURIST.record.reftypeID, relatedRecords,this.bibDetailType[0],this.changeNotification,true);

};

top.HEURIST.edit.Reminder = function(parentElement, reminderDetails) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	this.reminderID = reminderDetails.id;

	var who;
	if (reminderDetails.user) {
		who = top.HEURIST.allUsers? top.HEURIST.allUsers[reminderDetails.user][1] : top.HEURIST.get_user_name();
	} else if (reminderDetails.group) {
		who = top.HEURIST.workgroups[reminderDetails.group]
		if (who) who = who.name;
	} else if (reminderDetails.colleagueGroup) {
		who = top.HEURIST.user.colleagueGroups[reminderDetails.colleagueGroup];
	} else if (reminderDetails.email) {
		who = reminderDetails.email;
	} else {
		who = "";
	}

	this.reminderDiv = this.document.createElement("div");
		this.reminderDiv.className = "reminder-div";
		if (reminderDetails.message != "") {
			this.reminderDiv.innerHTML = "<b>"+who+" "+reminderDetails.frequency+"</b> from <b>" + reminderDetails.when + "</b> with message: \"";
			this.reminderDiv.appendChild(this.document.createTextNode(reminderDetails.message + "\""));	// plaintext
		} else {
			this.reminderDiv.innerHTML = "<b>"+who+" "+reminderDetails.frequency+"</b> from <b>" + reminderDetails.when + "</b>";
		}

	var removeImg = this.reminderDiv.appendChild(this.document.createElement("img"));
		removeImg.src = top.HEURIST.basePath+"common/images/cross.gif";
		removeImg.title = "Remove this reminder";
		var thisRef = this;
		removeImg.onclick = function() { if (confirm("Remove this reminder?")) thisRef.remove(); };

	parentElement.appendChild(this.reminderDiv);
};
top.HEURIST.edit.Reminder.prototype.remove = function() {
	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var fakeForm = { action: top.HEURIST.basePath+"data/reminders/save-reminder.php",
	                 elements: [ { name: "rem_id", value: this.reminderID },
	                             { name: "bib_id", value: windowRef.parent.HEURIST.record.bibID },
	                             { name: "save-mode", value: "delete" } ] };
	var thisRef = this;
	top.HEURIST.util.xhrFormSubmit(fakeForm, function(json) {
		var val = eval(json.responseText);
		if (val == 1) {
			/* deletion was successful */
			thisRef.reminderDiv.parentNode.removeChild(thisRef.reminderDiv);

			/* find the reminder in HEURIST.record ... */
			var reminders = windowRef.parent.HEURIST.record.reminders;
			for (var i=0; i < reminders.length; ++i) {
				if (reminders[i].id == thisRef.reminderID) {
					reminders.splice(i, 1);
					break;
				}
			}
		}
		else {
			alert(val.error);
		}
	});
};

top.HEURIST.edit.inputs.ReminderInput = function(parentElement) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	var oneWeekFromNow = new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000);
		var month = oneWeekFromNow.getMonth()+1;  if (month < 10) month = "0" + month;
		var day = oneWeekFromNow.getDate();  if (day < 10) day = "0" + day;
		oneWeekFromNow = oneWeekFromNow.getFullYear() + "-" + month + "-" + day;

	var reminderDetails = {
		user: top.HEURIST.get_user_id(),
		group: 0,
		colleagueGroup: 0,
		email: "",
		when: oneWeekFromNow,
		frequency: "once",
		message: ""
	};
	this.details = reminderDetails;

	var thisRef = this;

	this.fieldset = parentElement.appendChild(this.document.createElement("fieldset"));
		this.fieldset.className = "reminder";
		this.fieldset.appendChild(this.document.createElement("legend")).appendChild(this.document.createTextNode("Add reminder"));

	var p = this.fieldset.appendChild(this.document.createElement("p"));
	p.appendChild(this.document.createTextNode("Set up email reminders about this record"));

	this.detailTable = this.fieldset.appendChild(this.document.createElement("table"));
		this.detailTable.frameBorder = 0;
		this.detailTable.className = "reminder-table";
	var tbody = this.detailTable.appendChild(this.document.createElement("tbody"));
	var tr1 = tbody.appendChild(this.document.createElement("tr"));
		var tds = [];
		for (var i=0; i < 8; ++i) {
			var td = tr1.appendChild(this.document.createElement("td"));
			td.className = "col-"+(i+1);
			tds.push(td);
		}

		tds[0].appendChild(this.document.createTextNode("Who"));

		var userTextbox = this.userTextbox = tds[1].appendChild(this.document.createElement("input"));
			this.userTextbox.name = "reminder-user";
			this.userTextbox.className = "in";
			this.userTextbox.setAttribute("prompt", "Type user name here");
			new top.HEURIST.autocomplete.AutoComplete(userTextbox, top.HEURIST.util.userAutofill,
			                { multiWord: false, prompt: this.userTextbox.getAttribute("prompt"),
					  nonVocabularyCallback: function(value) { if (value) alert("Unknown user '"+value+"'"); return false; } });

		tds[2].appendChild(this.document.createTextNode("or"));

		this.groupDropdown = tds[3].appendChild(this.document.createElement("select"));
			this.groupDropdown.className = "group-dropdown";
			this.groupDropdown.name = "reminder-group";
			top.HEURIST.edit.addOption(this.document, this.groupDropdown, "Group ...", "");
			this.groupDropdown.selectedIndex = 0;
		var i = 0;
		for (var j in top.HEURIST.user.workgroups) {
			var groupID = top.HEURIST.user.workgroups[j];
			var group = top.HEURIST.workgroups[groupID];
			if (! group) continue;

			top.HEURIST.edit.addOption(this.document, this.groupDropdown, group.name, groupID);
			if (groupID == reminderDetails.group)
				this.groupDropdown.selectedIndex = i;
		}

		tds[4].appendChild(this.document.createTextNode("or"));

		this.colleaguesDropdown = tds[5].appendChild(this.document.createElement("select"));
			this.colleaguesDropdown.className = "colleagues-dropdown";
			this.colleaguesDropdown.name = "reminder-colleagues";
			top.HEURIST.edit.addOption(this.document, this.colleaguesDropdown, "Colleague group ...", "");
			this.colleaguesDropdown.selectedIndex = 0;
		var i = 0;
		for (var cgID in top.HEURIST.user.colleagueGroups) {
			var cgName = top.HEURIST.user.colleagueGroups[cgID];
			if (! cgName) continue;

			top.HEURIST.edit.addOption(this.document, this.colleaguesDropdown, cgName, cgID);
			if (cgID == reminderDetails.colleagueGroup)
				this.colleaguesDropdown.selectedIndex = i;
		}

		tds[6].appendChild(this.document.createTextNode("or email"));

		this.emailTextbox = tds[7].appendChild(this.document.createElement("input"));
			this.emailTextbox.type = "text";
			this.emailTextbox.className = "in";
			this.emailTextbox.name = "reminder-email";
			this.emailTextbox.value = reminderDetails.email;

		var ut = this.userTextbox, gd = this.groupDropdown, cd = this.colleaguesDropdown, et = this.emailTextbox;
		ut.onchange = function() { gd.selectedIndex = cd.selectedIndex = 0; et.value = ""; }
		gd.onchange = function() { cd.selectedIndex = 0; ut.value = et.value = ""; }
		cd.onchange = function() { gd.selectedIndex = 0; ut.value = et.value = ""; }
		et.onchange = function() { gd.selectedIndex = cd.selectedIndex = 0; ut.value = ""; }

	var tr2 = tbody.appendChild(this.document.createElement("tr"));
		var td = tr2.appendChild(this.document.createElement("td"));
		td.className = "col-1";
		td.appendChild(this.document.createTextNode("When"));

		td = tr2.appendChild(this.document.createElement("td"));
		td.colSpan = 7;

		this.nowRadioButton = this.document.createElement("input");
		this.nowRadioButton.type = "radio";
		this.nowRadioButton.name = "when";
		this.nowRadioButton.checked = true;
		this.nowRadioButton.onclick = function() { thisRef.whenSpan.style.display = "none"; thisRef.saveButton.value = "Send"; };
		td.appendChild(this.nowRadioButton);
		td.appendChild(this.document.createTextNode("Now"));

		this.laterRadioButton = this.document.createElement("input");
		this.laterRadioButton.type = "radio";
		this.laterRadioButton.name = "when";
		this.laterRadioButton.style.marginLeft = "20px";
		this.laterRadioButton.onclick = function() { thisRef.whenSpan.style.display = ""; thisRef.saveButton.value = "Store" };
		td.appendChild(this.laterRadioButton);
		td.appendChild(this.document.createTextNode("Later / periodic"));

		this.whenSpan = td.appendChild(this.document.createElement("span"));
		this.whenSpan.style.display = "none";

		this.whenTextbox = this.document.createElement("input");
			this.whenTextbox.type = "text";
			this.whenTextbox.className = "in";
			this.whenTextbox.name = "reminder-when";
			this.whenTextbox.style.width = "90px";
			this.whenTextbox.style.marginLeft = "10px";
			this.whenTextbox.value = reminderDetails.when;
		this.whenSpan.appendChild(this.whenTextbox);
		this.whenButton = top.HEURIST.edit.makeDateButton(this.whenTextbox, this.document);

		this.frequencyDropdown = this.whenSpan.appendChild(this.document.createElement("select"));
			this.frequencyDropdown.className = "frequency-dropdown";
			this.frequencyDropdown.name = "reminder-frequency";
			top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Once only", "once");
			top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Annually", "annually");
			top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Monthly", "monthly");
			top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Weekly", "weekly");
			top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Daily", "daily");
		for (var i=0; i < this.frequencyDropdown.options.length; ++i) {
			if (this.frequencyDropdown.options[i].value == reminderDetails.frequency)
				this.frequencyDropdown.selectedIndex = i;
		}

	var tr3 = tbody.appendChild(this.document.createElement("tr"));
		td = tr3.appendChild(this.document.createElement("td"));
		td.className = "col-1";
		td.appendChild(this.document.createTextNode("Message"));

		td = tr3.appendChild(this.document.createElement("td"));
		td.colSpan = 8;

		this.messageBox = td.appendChild(this.document.createElement("textarea"));
			this.messageBox.className = "in";
			this.messageBox.name = "reminder-message";
			this.messageBox.value = reminderDetails.message;
			this.messageBox.style.width = "600px";
			this.messageBox.style.height = "70px";

	var tr4 = tbody.appendChild(this.document.createElement("tr"));
		td = tr4.appendChild(this.document.createElement("td"));
		td = tr4.appendChild(this.document.createElement("td"));
		td.colSpan = 1;
		td.style.verticalAlign = "bottom";
		td.style.textAlign = "right";
		this.saveButton = this.document.createElement("input");
			this.saveButton.type = "button";
			this.saveButton.value = "Send";
			this.saveButton.style.fontWeight = "bold";
			this.saveButton.style.marginBottom = "1em";
			this.saveButton.style.display = "block";
			var thisRef = this;
			this.saveButton.onclick = function() { thisRef.save(thisRef.nowRadioButton.checked); };
			td.appendChild(this.saveButton);
		td = tr4.appendChild(this.document.createElement("td"));
		td.colSpan = 6;
		td.style.textAlign = "left";
		td.style.verticalAlign = "baseline";
		td.style.paddingTop = "10px";
		td.appendChild(this.document.createTextNode("You must Send (now) or Set (periodic) your reminder before saving record. " +
			"Reminders are sent shortly after midnight (server time) on the reminder day."));

		var bibIDelt = this.document.createElement("input");
			bibIDelt.type = "hidden";
			bibIDelt.name = "bib_id";
			bibIDelt.value = parent.HEURIST.record.bibID;
			td.appendChild(bibIDelt);
		var addElt = this.document.createElement("input");
			addElt.type = "hidden";
			addElt.name = "save-mode";
			addElt.value = "add";
			td.appendChild(addElt);
};
top.HEURIST.edit.inputs.ReminderInput.prototype.isEmpty = function() {
	if (this.messageBox.value.match(/\S/)) return false;
	return true;
};
top.HEURIST.edit.inputs.ReminderInput.prototype.save = function(immediate, auto) {
	// Verify inputs and save form via XHR
	// If "auto" is set, then we will abort silently rather than alert the user

	if ((! this.userTextbox.value  ||  this.userTextbox.value === this.userTextbox.getAttribute("prompt"))  &&
		! this.groupDropdown.value  &&  ! this.colleaguesDropdown.value  &&  ! this.emailTextbox.value) {
		this.userTextbox.focus();
		if (! auto) alert("You must select a user, a group, or an email address");
		return true;
	}

	if (! immediate) {
		if (! this.whenTextbox.value) {
			this.whenTextbox.focus();
			if (! auto) alert("You must choose a date for the reminder");
			return true;
		}else if (new Date((this.whenTextbox.value).replace(/\-/g,"/")) <= Date.now()){
			this.whenTextbox.focus();
			alert("You must choose a future date (beyond today)");
			return false;
		}
	}

	if (immediate) {
		var immediateElt = document.createElement("input");
			immediateElt.type = "hidden";
			immediateElt.name = "mail-now";
			immediateElt.value = "1";
		this.whenTextbox.parentNode.appendChild(immediateElt);
	}

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
	var thisRef = this;
	top.HEURIST.util.xhrFormSubmit(this.messageBox.form, function(json) {
		if (auto) return;

		// callback
		var vals = eval(json.responseText);
		if (! vals) {
			alert("Oops - error while saving");
		} else if (vals.error) {
			alert("Error while saving:\n" + vals.error);
		} else if (immediate) {
			alert("Email sent");
		} else {
			// Add the reminder to the record ...
			var newReminder = vals.reminder;
			windowRef.parent.HEURIST.record.reminders.push(newReminder);

			// ... remove the previous inputs ...
			thisRef.fieldset.parentNode.removeChild(thisRef.fieldset);

			// ... add in the new reminder ...
// FIXME: shouldn't use that getbyid
			new top.HEURIST.edit.Reminder(thisRef.document.getElementById("reminders"), newReminder);

			// ... and add a new set of inputs.
			windowRef.reminderInput = new top.HEURIST.edit.inputs.ReminderInput(thisRef.document.getElementById("reminders"));
		}
	});
};


top.HEURIST.edit.inputs.BibURLInput = function(parentElement, defaultValue, required) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	this.typeDescription = "URL (internet address)";
	this.shortName = "URL";

	var row = parentElement.appendChild(this.document.createElement("tr"));
	if (required) {	// internet bookmark
		row.className = "input-row required";
		this.required = "required";
	} else {
		row.className = "input-row recommended";
		this.required = "optional";
	}
	var headerCell = row.appendChild(this.document.createElement("td"));
		headerCell.className = "input-header-cell";
		headerCell.appendChild(this.document.createTextNode("URL"));
	var inputCell = row.appendChild(this.document.createElement("td"));
		inputCell.className = "input-cell";

	var inputField = inputCell.appendChild(this.document.createElement("input"));
	this.inputs = [ inputField ];
		this.inputs[0].className = "in";
		this.inputs[0].style.width = "75ex";
		this.inputs[0].style.maxWidth = "90%";
		this.inputs[0].name = "bib_url";
		this.inputs[0].id = "bib_url";
		this.inputs[0].value = defaultValue  ||  "";

	if (defaultValue) {
		inputField.style.display = "none";

		var urlOutput = this.document.createElement("a");
			urlOutput.target = "_blank";
			urlOutput.href = defaultValue;
		var linkImg = urlOutput.appendChild(this.document.createElement("img"));
			linkImg.src = top.HEURIST.basePath+"common/images/external_link_16x16.gif";
			linkImg.className = "link-image";
		urlOutput.appendChild(this.document.createTextNode(defaultValue));

		var urlSpan = this.document.createElement("span");
			urlSpan.style.paddingLeft = "1em";
			urlSpan.style.color = "blue";
			urlSpan.style.cursor = "pointer";
		var editImg = urlSpan.appendChild(this.document.createElement("img"));
			editImg.src = top.HEURIST.basePath+"common/images/edit_pencil_16x16.gif";
		urlSpan.appendChild(editImg);
		urlSpan.appendChild(this.document.createTextNode("edit"));


		urlSpan.onclick = function() {
			inputCell.removeChild(urlOutput);
			inputCell.removeChild(urlSpan);
			inputField.style.display = "";
		};

		inputCell.appendChild(urlOutput);
		inputCell.appendChild(urlSpan);
	}

	var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
		top.HEURIST.registerEvent(this.inputs[0], "change", function() { windowRef.changed(); });
};
top.HEURIST.edit.inputs.BibURLInput.prototype.focus = function() { this.inputs[0].focus(); };
top.HEURIST.edit.inputs.BibURLInput.prototype.verify = function() {
	return (this.inputs[0].value != "");
};

top.HEURIST.fireEvent(window, "heurist-edit-js-loaded");
