/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

/* heurist-workgroup.js
 * Copyright 2006, 2007 Tom Murtagh and Kim Jackson
 * http://heuristscholar.org/
 *
 * JS for the Heurist workgroup home page:
 * functions are installed as  top.HEURIST.workgroup.*
 *
 */

/*
This file is part of Heurist.

Heurist is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

Heurist is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

top.HEURIST.workgroup = {

	wg_id: 0,

	renderWorkgroupSection: function() {
		if (! top.HEURIST.workgroup.wg_id) return;

		var wg = top.HEURIST.workgroups[top.HEURIST.workgroup.wg_id];
		document.title = "Heurist Workgroup home page - " + wg.name;
		document.getElementById("wg-name").innerHTML = wg.name;
		document.getElementById("wg-description").innerHTML = wg.description ? "<table style='height:100%'><tbody><tr><td>"+wg.description+"</td></tr></tbody></table>" : "";
		document.getElementById("wg-description").title = wg.description ? wg.description : "This workgroup does not currently have a description";
		document.getElementById("wg-members").innerHTML = wg.memberCount;
		var admins = "";
		for (var i=0; wg.admins && i < wg.admins.length; ++i) {
			admins += "<a href=mailto:"+wg.admins[i].email+"><nobr>"+wg.admins[i].name+"</nobr></a>&nbsp;&nbsp; ";
		}
		document.getElementById("wg-admins").innerHTML = admins;

		var link = document.getElementById("wg-link");
		if (wg.url) {
			link.href = (wg.url + "").match(/^http:\/\//)? wg.url : ("http://" + wg.url);
			link.style.display = "";
		}
		else {
			link.style.display = "none";
		}

		document.getElementById("blog-link").href += top.HEURIST.workgroup.wg_id;

		document.getElementById("wg-manage-saved-searches-link").href += "?wg=" + top.HEURIST.workgroup.wg_id;

		document.getElementById("add-fav-link").href += "&t=" + encodeURIComponent(document.title) + "&u=" + encodeURIComponent(window.location.href);
	},



	renderFilterOptions: function() {
		var rectype_select = document.getElementById("rectype-select");
		// rectypes displayed in Groups by group display order then by display order within group
		for (var grpID in top.HEURIST.rectypes.groups){
			var grp = document.createElement("optgroup");
			grp.label = top.HEURIST.rectypes.groups[grpID].name;
			rectype_select.appendChild(grp);
			for (var i=0; i < top.HEURIST.rectypes.groups[grpID].types.length; ++i) {
				var value = top.HEURIST.rectypes.groups[grpID].types[i];
				var name = top.HEURIST.rectypes.names[value];
				rectype_select.options[j++] = new Option(name, value);
			}
		}

		var wgtag_select = document.getElementById("wgtag-select");
		if (wgtag_select.options.length <= 1) {
			top.HEURIST.workgroup.kwds = [];
			if (top.HEURIST.user  &&  top.HEURIST.user.workgroupTags) {
				for (var i = 0; i < top.HEURIST.user.workgroupTagOrder.length; ++i) {
					var kwdId = top.HEURIST.user.workgroupTagOrder[i];
					var kwd = top.HEURIST.user.workgroupTags[kwdId];
					if (kwd[0] == top.HEURIST.workgroup.wg_id) {
						wgtag_select[wgtag_select.length] = new Option(kwd[1],kwdId);
						top.HEURIST.workgroup.kwds.push(kwdId);
					}
				}
			}
		}
	},

	filterSearch: function(predicate, value) {
		var rft = document.getElementById("rectype-select").value;
		var kwd = document.getElementById("wgtag-select").value;
		var sortby = document.getElementById("sortby-select").value;
		var q = (rft ? " type:" + rft + ((kwd || top.HEURIST.workgroup.kwds.length) ? " AND ": "") : "")
			  + (kwd ? " tag:\"" + kwd + "\"" : "tag:\"" + top.HEURIST.workgroup.kwds.join(",") + "\"")
			  + " sortby:" + sortby;
		window.HEURIST.parameters["q"] = q;
		top.HEURIST.search.closeInfos();
		top.HEURIST.search.currentPage = 0;
		top.HEURIST.search.loadSearchParameters();
	},

	setupWorkgroupPage: function() {
		top.HEURIST.search.resultsDiv = document.getElementById("result-rows");
		var wg_id = top.HEURIST.workgroup.wg_id = window.HEURIST.parameters["wg"];
		if (! wg_id) {
			// home?
			return;
		}
		if (! top.HEURIST.is_logged_in()) {
			top.location.href = top.HEURIST.basePath + "common/connect/login.php";
		}
		if (! top.HEURIST.user.isInWorkgroup(wg_id)) {
			// TODO: display something?  redirect somewhere?
			top.HEURIST.workgroup.wg_id = 0;
			return;
		}

		top.HEURIST.registerEvent(window, "contentloaded", top.HEURIST.workgroup.renderWorkgroupPage);
		top.HEURIST.registerEvent(window, "contentloaded", function() {
			top.HEURIST.json.loadWorkgroupDetails(wg_id, function() {
				top.HEURIST.search.fillInSavedSearches(wg_id);
			});
		});

		top.HEURIST.registerEvent(window, "resize", top.HEURIST.search.trimAllLinkTexts);
		top.HEURIST.registerEvent(window, "resize", function() {
			if (document.getElementById("legend-box")) {
				top.HEURIST.search.toggleLegend();
				top.HEURIST.search.toggleLegend();
			}
		});

		// load inital results: all workgroup records
		//window.HEURIST.parameters["q"] = "wg:" + wg_id + " sortby:t";
		//window.HEURIST.parameters["w"] = "all";
		top.HEURIST.workgroup.filterSearch();
		//top.HEURIST.search.loadSearchParameters();
	},

	renderWorkgroupPage: function() {
		top.HEURIST.workgroup.renderWorkgroupSection();
		top.HEURIST.workgroup.renderFilterOptions();
		top.HEURIST.search.renderLoginDependentContent();
		top.HEURIST.search.setContactLink();
	}

};

top.HEURIST.fireEvent(window, "heurist-workgroup-js-loaded");

