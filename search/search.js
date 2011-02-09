/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

/* search.js
 * Copyright 2006 - 2009 University of Sydney Digital Innovation Unit
 * http://heuristscholar.org/
 *
 * Programming: Tom Murtagh, Kim Jackson
 *
 * JS for the main Heurist search page:
 * functions are installed as  top.HEURIST.search.*
 * search data is loaded into  top.HEURIST.search.results.*
 *
 * NOTE: personal keywords are now referred to as "tags", to distinguish
 * them from workgroup keywords.  The code still refers to both as keywords.
 * -kj, 2007-08-13
 * Note: now there are tags (user) and wgTags (workgroup)
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


String.prototype.htmlEscape = function() {
	return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;");
}

top.HEURIST.search = {
	VERSION: "1",

	pageLimit: 1000,
	resultsPerPage: top.HEURIST.displayPreferences["results-per-page"],
	currentPage: 0,

	bib_ids: {},
	bkmk_ids: {},
	record_view_style: "full",

	checkSearchForm: function() {
		var e;
		e = document.getElementById('stype');
		if (! e.value || e.value == '') {
			e.parentNode.removeChild(e);
		}
		e = document.getElementById('w-input');
		if (! e.value || e.value == '') {
			e.parentNode.removeChild(e);
		}
	},

	submitSearchForm: function() {
		top.HEURIST.search.checkSearchForm();
		document.forms[0].submit();
	},

	searchNotify: function(results) {
		top.HEURIST.search.results = {
			records: [],
			bibIdToResultIndex: {},
			totalRecordCount: results.totalRecordCount
		};

		top.HEURIST.search.sid = results.sid;

		top.HEURIST.search.infos = {};

		if (top.HEURIST.search.results.totalRecordCount == 0)
			top.HEURIST.registerEvent(window, "load", top.HEURIST.search.clearResultRows);

		top.HEURIST.registerEvent(window, "load", top.HEURIST.search.renderNavigation);

		results.notified = true;
	},

	searchResultsNotify: function(newResults, startIndex) {
		for (var i=0; i < newResults.records.length; ++i) {
			top.HEURIST.search.results.records[i + startIndex] = newResults.records[i];
			top.HEURIST.search.results.bibIdToResultIndex[newResults.records[i][2]] = i + startIndex;
		}
		newResults.records = [];

		// check if we've just loaded the page we were expecting to render
		if (Math.floor(startIndex / top.HEURIST.search.resultsPerPage) == top.HEURIST.search.currentPage) {
			// should have some sort of "searchready" event -- FIXME as part of holistic event-based solution
			var lastIndex = Math.min(startIndex + top.HEURIST.search.resultsPerPage, top.HEURIST.search.results.totalRecordCount);
			top.HEURIST.registerEvent(window, "contentloaded", function() {
				top.HEURIST.search.renderSearchResults(startIndex, lastIndex-1);
				top.HEURIST.search.renderNavigation();
			});
			top.HEURIST.registerEvent(window, "load", function() {
				document.getElementById("viewer-frame").src = top.HEURIST.basePath+ "viewers/printview/";
//				top.HEURIST.search.toggleResultItemSelect(top.HEURIST.search.results.records[startIndex][2]);
//				top.HEURIST.search.toggleResultItemSelect(top.HEURIST.search.results.records[0][2]);
			});
		}
	},


	loadSearchLocation: function(strLocation) {
		var temp = strLocation + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		window.location.href = temp;
	},

	loadSearch: function() {
		if (! window.HEURIST.parameters["q"]) {
			top.HEURIST.registerEvent(window, "contentloaded",
			function() { document.getElementById("search-status").className = "noquery"; });
			return;
		}
		var iframeElt = document.createElement("iframe");
		iframeElt.style.border = "0";
		iframeElt.style.width = "0";
		iframeElt.style.height = "0";
		iframeElt.frameBorder = 0;
		iframeElt.style.position = "absolute";
		iframeElt.src = top.HEURIST.basePath+"search/getResultsPageAsync.php?" +
			("w=" + encodeURIComponent(window.HEURIST.parameters["w"])) + "&" +
			("stype=" + (window.HEURIST.parameters["stype"] ? encodeURIComponent(window.HEURIST.parameters["stype"]) : "")) + "&" +
			("ver=" + top.HEURIST.search.VERSION) + "&" +
			("q=" + encodeURIComponent(window.HEURIST.parameters["q"])+
			(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));
		document.body.appendChild(iframeElt);
	},

	loadSearchParameters: function() {
		var args = window.HEURIST.parameters;

		// set search type
		if (top.HEURIST.is_logged_in()  &&  args["w"] === "bookmark") {
			args["w"] = "bookmark";
		} else {
			args["w"] = "all";
		}

		if (! args["q"]) {
			args["q"] = "";
		}

		// set search version
		args["ver"] = top.HEURIST.search.VERSION;

		if (args["view"]) {
			top.HEURIST.search.setResultStyle(args["view"]);
		}

		if (! top.suppressAutoSearch) top.HEURIST.search.loadSearch();
	},

	renderResult: function(style, res) {
		if (style == "list"  ||  style == "two-col") {
			return top.HEURIST.search.renderResultRow(res);
		} else if (style == "thumbs") {
			return top.HEURIST.search.renderResultThumb(res);
		}
	},

	renderResultRow: function(res) {

		/* res[0]   res[1]        res[2]  res[3]   res[4]       res[5]     res[6]        */
		/* bkmk_id, bkmk_user_id, bib_id, bib_url, bib_rectype, bib_title, bib_workgroup */
		/* res[7] => bib_visibility, res[8] => bib_url_last_verified, res[9] => bib_url_error, res[10] => bkmk_user_pwd */

		var pinAttribs = res[0]? "class='logged-in-only bookmarked' title='Bookmarked - click to see details'"
		                       : "class='logged-in-only unbookmarked' title='Bookmark this record'";

		var href = res[3];
		var linkText = res[5] + " ";
		var wgID = parseInt(res[6]);

		var linkTitle = "";
		var wgHTML = "";
		var wgColor = "";
		if (res[6]  &&  res[6] != "0") {
			linkTitle = "Restricted to workgroup " + top.HEURIST.workgroups[wgID].name + " - " + (res[7]==1? "hidden" : "read-only") + " to others";
			wgHTML = res[6];
			wgColor = " style='color:" + ((res[7]==1)? "red" : "green") + "'";
		}

		var verified_date = null;
		if (res[8]) {
			// locale-independent date parsing (early Webkit uses the local format)
			var dateBits = res[8].match(/^([^-]+)-([^-]+)-([^-]+) ([^:]+):([^:]+):([^.]*)/);
			verified_date = new Date();
			verified_date.setFullYear(dateBits[1], dateBits[2], dateBits[3]);
			verified_date.setHours(dateBits[4], dateBits[5], dateBits[6], 0);
		}

		var daysBad = "";
		if (href) {
			if (! href.match(/^[^\/\\]*:/))
				href = "http://" + href;
			if (href.substring(0, 5).toLowerCase() != "file:") {
				var err = top.HEURIST.search.format_web_error(res[9], verified_date, href);
				if (err) daysBad = err;
			}
			else {
				daysBad = "[local file]";
			}
			href = href.htmlEscape();
		}
		else if (res[4] == 2) {
			// special handling for notes rectype: link to view page if no URL
			href = top.HEURIST.basePath+ "records/view/viewRecord.php?bib_id="+res[2] + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		}

		var userPwd;
		if (res[10]) userPwd = "style='display:inline;cursor:pointer;' user_pwd='"+res[10].htmlEscape()+"'";
		else userPwd = "style='display:none;'";

		var rectypeImg = "style='background-image:url("+ top.HEURIST.basePath+"common/images/rectype-icons/" + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeTitle = "Click to see details";
		if (top.HEURIST.rectypes.names[parseInt(res[4])])
			rectypeTitle = top.HEURIST.rectypes.names[parseInt(res[4])] + " - click to see details";

		var html = "<div class=result_row title='Double-click to edit' bkmk_id='"+res[0]+"' bib_id="+res[2]+">"+
		"<img src=" +top.HEURIST.basePath+ "common/images/13x13.gif " + pinAttribs + ">"+
		"<span class='wg-id-container logged-in-only'>"+
		"<span class=wg-id title='"+linkTitle+"' " + (wgColor? wgColor: "") + ">" + (wgHTML? wgHTML.htmlEscape() : "") + "</span>"+
		"</span>"+
		"<img src=" +top.HEURIST.basePath+ "common/images/16x16.gif title='"+rectypeTitle.htmlEscape()+"' "+rectypeImg+" class=rft>"+
		"<span class='rec_title'>" + (res[3].length ? "<a href='"+res[3]+"' target='_blank'>"+linkText + "</a>" : linkText ) + "</span>" +
		"<div class=right_margin_info>"+
		"<span><img class='passwordIcon' onclick=top.HEURIST.search.passwordPopup(this) title='Click to see password reminder' src=" +top.HEURIST.basePath+ "common/images/lock.png " + userPwd + "></span>"+
		"<div class=mini-tools >" +
			"<span id='rec_edit_link' title='Click to edit'><a href='"+
			top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
			top.HEURIST.search.sid + "&bib_id="+ res[2] +
			(top.HEURIST.instance && top.HEURIST.instance.name ? '&instance=' + top.HEURIST.instance.name : '') +
			"' target='_blank'><img src='"+	top.HEURIST.basePath + "common/images/edit_pencil_small.png'/>edit</a> | </span>" +
			"<span id='rec_explore_link' title='Click to explore'><a href='/cocoon" +top.HEURIST.basePath +'relbrowser/main/item/' + res[2] +
					(top.HEURIST.instance && top.HEURIST.instance.name ? '/?instance=' + top.HEURIST.instance.name : '') +
					"' target='_blank'><img src='"+	top.HEURIST.basePath + "common/images/explore.png'/>explore</a></span>" +

			"<span id='spacer'><img src='"+	top.HEURIST.basePath + "common/images/16x16.gif'/></span>" +
		"</div>" +
		"<span style='display:none' class=daysbad title='Detection of broken URLs is carried out once a day'>"+daysBad+"</span>"+
		"</div>" +
		"<input type=checkbox name=bib[] onclick=top.HEURIST.search.cb_onclick(this) class='logged-in-only' title='Check box to apply Actions to this record'>"+
		"</div>";

		return html;
	},

	renderResultThumb: function(res) {

		/* res[0]   res[1]        res[2]  res[3]   res[4]       res[5]     res[6]        */
		/* bkmk_id, bkmk_user_id, bib_id, bib_url, bib_rectype, bib_title, bib_workgroup */

		/* res[7]          res[8]                 res[9]         res[10]        res[11]   */
		/* bib_visibility, bib_url_last_verified, bib_url_error, bkmk_user_pwd, thumbnail */

		var pinAttribs = res[0]? "class='logged-in-only bookmarked' title='Bookmarked - click to see details'"
		                       : "class='logged-in-only unbookmarked' title='Bookmark this record'";

		var href = res[3];
		var linkText = res[5] + " ";
		var wgID = parseInt(res[6]);

		var linkTitle = "";
		var wgHTML = "";
		var wgColor = "";
		if (res[6]  &&  res[6] != "0" && res[6] != res[1]) {	// check if this is a usergroup owned record
			linkTitle = "Restricted to workgroup " + top.HEURIST.workgroups[wgID].name + " - " + (res[7]==1? "hidden" : "read-only") + " to others";
			wgHTML = res[6];
			wgColor = " style='color:" + ((res[7]==1)? "red" : "green") + "'";
		}

		var verified_date = null;
		if (res[8]) {
			// locale-independent date parsing (early Webkit uses the local format)
			var dateBits = res[8].match(/^([^-]+)-([^-]+)-([^-]+) ([^:]+):([^:]+):([^.]*)/);
			verified_date = new Date();
			verified_date.setFullYear(dateBits[1], dateBits[2], dateBits[3]);
			verified_date.setHours(dateBits[4], dateBits[5], dateBits[6], 0);
		}

		if (href) {
			if (! href.match(/^[^\/\\]*:/))
				href = "http://" + href;
			href = href.htmlEscape();
		}
		else if (res[4] == 2) {
			// special handling for notes rectype: link to view page if no URL
			href = top.HEURIST.basePath+ "records/view/viewRecord.php?bib_id="+res[2] +(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		}

		var userPwd;
		if (res[10]) userPwd = "style='display:inline;cursor:pointer;' user_pwd='"+res[10].htmlEscape()+"'";
		else userPwd = "style='display:none;'";

		var rectypeImg = "style='background-image:url("+ top.HEURIST.basePath+"common/images/rectype-icons/" + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeThumb = "style='background-image:url("+ top.HEURIST.basePath+"common/images/rectype-icons/thumb/th_" + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeTitle = "Click to see details";
		if (top.HEURIST.rectypes.names[parseInt(res[4])])
			rectypeTitle = top.HEURIST.rectypes.names[parseInt(res[4])] + " - click to see details";

		var html =
		"<div class=result_thumb  title='Double-click to edit' bkmk_id='"+res[0]+"' bib_id="+res[2]+">" +
		"<input style='display:none' type=checkbox name=bib[] onclick=top.HEURIST.search.resultItemOnClick(this) class='logged-in-only' title='Check box to apply Actions to this record'>"+
		   (res[11] && res[11].length ? "<div class='thumbnail' style='background-image:url("+res[11]+")' ></div>":"<div class='no-thumbnail' "+rectypeThumb+" ></div>") +
		"<div class='rec_title'>" + (res[3].length ? "<a href='"+res[3]+"' target='_blank'>"+linkText + "</a>" : linkText ) + "</div>" +
		   "<div class=icons  bkmk_id='"+res[0]+"' bib_id="+res[2]+">" +


		   "<img src='"+ top.HEURIST.basePath+"common/images/16x16.gif' title='"+rectypeTitle.htmlEscape()+"' "+rectypeImg+" class='rft'>"+
		   "<img src='"+ top.HEURIST.basePath+"common/images/13x13.gif' " + pinAttribs + ">"+
		   "<span class='wg-id-container logged-in-only'>"+
		   "<span class=wg-id title='"+linkTitle.htmlEscape()+"' " + (wgColor? wgColor: "") + ">" + (wgHTML? wgHTML.htmlEscape() : "") + "</span>"+
		   "</span>"+
		   "<img onclick=top.HEURIST.search.passwordPopup(this) title='Click to see password reminder' src='"+ top.HEURIST.basePath+"common/images/lock.png' " + userPwd + ">"+
		    "</div>" +
		    "<div class=mini-tools >" +
		    	"<div id='links'>" +
				"<span id='rec_edit_link' title='Click to edit'><a href='"+
			top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
			top.HEURIST.search.sid + "&bib_id="+ res[2] +
			(top.HEURIST.instance && top.HEURIST.instance.name ? '&instance=' + top.HEURIST.instance.name : '') +
			"' target='_blank'><img src='"+	top.HEURIST.basePath + "common/images/edit_pencil_small.png'/>edit</a> | </span>" +
				(res[3].length ? "<span><a href='"+res[3]+"' target='_blank'><img src='"+ top.HEURIST.basePath+"common/images/external_link_16x16.gif' title='go to link'>visit</a> | </span>" : "") +
						"<span id='rec_explore_link' title='Click to explore'><a href='/cocoon" +top.HEURIST.basePath +'relbrowser/main/item/' + res[2] +
					(top.HEURIST.instance && top.HEURIST.instance.name ? '/?instance=' + top.HEURIST.instance.name : '') +
					"' target='_blank'><img src='"+	top.HEURIST.basePath + "common/images/explore.png'/>explore</a></span>" +
				"<span id='spacer'><img src='"+	top.HEURIST.basePath + "common/images/16x16.gif'/></span>" +
				"</div>" +
		   "</div>" +

		"</div>";
		return html;
	},

	displaySearchParameters: function() {
		// Transfer query components to their form elements
		var params = window.HEURIST.parameters;
		if (! params["w"]) {
			if (top.HEURIST.is_logged_in()  &&  params["w"] === "bookmark") {
				params["w"] = "bookmark";
			} else {
				params["w"] = "all";
			}
		}
		var w_input = document.getElementById("w-input");
		if (w_input  &&  params["w"] != "all") {
			w_input.value = params["w"];
		}

		var inst_input = document.getElementById("instance");
		if (inst_input  &&  params["instance"]) {
			inst_input.value = params["instance"];
		}

		// set body class for elements whose display depends on search mode
		document.body.className += (document.body.className ? " " : "") + "w-" + params["w"];

		if (params["cf"]) {
			document.getElementById("q").value = "";
			set_stype_option("stype-0");
		} else {
			document.getElementById("q").value = params["q"] ? params["q"] : "";
		}
		if (params["label"]) {
			document.getElementById("active-label").innerHTML = params["label"];
			if (params["label"] == "Favourites") {
				document.getElementById("results-message").innerHTML = "Click on a record to view details. CTRL click to select multiple records";
				document.getElementById("results-message").style.display = "block";
			}
		} else {
			document.getElementById("active-label").innerHTML = "Search results";
		}
		if (params["description"]) {
			document.getElementById("search-description").innerHTML = params["description"];
			document.getElementById("search-description-row").style.display = "";
		}

		var msg = "";

		var m = params["q"] ? params["q"].match(/sortby:(-?)(.)/) : null;
		if (m && m[2] === "a") {
			if (params["w"] === "bookmark") {
				msg = "Records sorted by date of bookmarking ("
					+ (m[1] === "-" ? "descending" : "ascending") + "). "
					+ "To sort by date of record addition, use 'all records' search";
			} else {
				msg = "Records sorted by date of record addition ("
					+ (m[1] === "-" ? "descending" : "ascending") + "). "
					+ "To sort by date of bookmarking, use 'my bookmarks' search";
			}
			document.getElementById("results-message").innerHTML = msg;
			document.getElementById("results-message").style.display = "block";
		}
		if (m && m[2] === "m") {
			if (params["w"] === "bookmark") {
				msg = "Records sorted by date of modification of Private info ("
					+ (m[1] === "-" ? "descending" : "ascending") + "). "
					+ "To sort by date of modification of Public info, use 'All records' search";
			} else {
				msg = "Records sorted by date of modification of Public info ("
					+ (m[1] === "-" ? "descending" : "ascending") + "). "
					+ "To sort by date of modification of Private info, use 'my bookmarks' search";
			}
			document.getElementById("results-message").innerHTML = msg;
			document.getElementById("results-message").style.display = "block";
		}
	},

	format_web_error: function(err, ver_date, href) {
		if (! err  &&  ! ver_date)
			return "[URL not yet tested]";

		var err_string;

		if (err.match(/401/) || err.match(/403/))
			err_string = err;

		var day_count;
		if (ver_date) day_count = ((new Date()).getTime() - ver_date.getTime()) / (24*60*60*1000);
		else day_count = 0;

		if (day_count > 365*2)
			err_string = err + " - inaccessible for "+Math.floor(day_count / 365)+" years";	// fore-warned is fore-armed ...
		else if (day_count > 60)
			err_string = err + " - inaccessible for "+Math.floor(day_count / 30)+" months";
		else if (day_count > 14)
			err_string = err + " - inaccessible for "+Math.floor(day_count / 7)+" weeks";
		else if (day_count > 1.5)
			err_string = err + " - inaccessible for "+Math.round(day_count) + " days";
		else if (day_count > 1.3)
			err_string = err + " - inaccessible for 1 day";
		else if (! ver_date)
			err_string = err + " - inaccessible since addition";
		else
			return "";

		return "[" + err_string + " - <a target=_blank href=http://web.archive.org/web/*/" + href + ">page history</a>]";
	},

	renderLoginDependentContent: function() {
		var logged_in_elt = document.getElementById("logged-in");
		var left_panel_elt = document.getElementById("left-panel-content");
		if (top.HEURIST.is_logged_in()) {
			logged_in_elt.innerHTML = top.HEURIST.get_user_name() + " : <a href=" +top.HEURIST.basePath+ "common/connect/login.php?logout=1"+(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "")+">log&nbsp;out</a>";
		} else {
			logged_in_elt.innerHTML = "not logged in : <a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "")+">log in</a>";
			left_panel_elt.innerHTML =
				"<div style=\"padding: 10px;\">\n" +
				" Existing users:\n" +
				" <div id=login-button><a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "")+" title=\"Log in to use Heurist - new users please register first\"><img src=../common/images/111x30.gif></a></div>\n" +
				" <p style=\"text-align: center;\"><a onclick=\"InstallTrigger.install({'Heurist Toolbar':this.href}); return false;\" href=" +top.HEURIST.basePath+ "tools/toolbar/HeuristToolbar.xpi title=\"Get Firefox toolbar - rapid access to bookmark web pages, import hyperlinks, view and edit data for the current web page, and synchronise Heurist with Zotero\">Get toolbar</a><br>(Firefox)</p>\n" +
				" <p style=\"text-align: center;\"><a href=\"javascript:" + top.HEURIST.bookmarkletCode + "\" onclick=\"alert('Drag the Heurist Bookmarklet link to your browser bookmarks toolbar, or right-click the link, choose Bookmark This Link, and add the link to your Bookmarks Toolbar or Favorites.');return false;\" title=\"Get Bookmarklet - bookmarks web pages and harvests web links \(we recommend Firefox and the Firefox toolbar - it has more features\)\">Heurist Bookmarklet</a><br>(other browsers)</p>" +
				" New users:\n" +
				" <div id=tour-button><a href=" +top.HEURIST.basePath+ "help/tour.html title=\"Take a quick tour of Heurist's major features\" target=\"_blank\"><img src=../common/images/111x30.gif></a></div>\n" +
				" <div id=register-button><a href=" +top.HEURIST.basePath+ "admin/ugrps/findAddUser.php?register=1"+(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "")+" target=\"_blank\" title=\"Register to use Heurist - takes only a couple of minutes\"><img src=../common/images/111x30.gif></a></div>\n" +
				"</div>";

			document.getElementById("my-records-button").disabled = true;
			/*
			var label = document.getElementById("w-bookmark-label");
			label.style.color = "#888888";
			label.title = "log in to enable this function";
			var img = label.getElementsByTagName("img")[0];
			img.style.visibility = "hidden";
			*/
		}
	},

	clearResultRows: function() {
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		document.getElementById("result-rows").innerHTML = "";
		top.HEURIST.search.selectedRecordIds = [];
		top.HEURIST.search.selectedRecordDivs = {};
	},

	renderSearchResults: function(firstIndex, lastIndex) {
		document.getElementById("search-status").className = "";
		// This is rooted ... Firefox doesn't render the display: none on this element until after the results are actually loaded.
		// We shall do a manual override.
		var currentHTML = document.getElementById("loading-search-status").innerHTML;
		document.getElementById("loading-search-status").innerHTML = "";
		setTimeout(function() { document.getElementById("loading-search-status").innerHTML = currentHTML }, 0);

		// clear out old data before rendering anew
		top.HEURIST.search.clearResultRows();

		var style = top.HEURIST.util.getDisplayPreference("search-result-style");

		var innerHTML = "";
		var leftHTML = "";
		var rightHTML = "";
		var recordCount = lastIndex - firstIndex + 1;
		for (var i = 0; i < recordCount; ++i) {
			if (top.HEURIST.search.results.records[firstIndex+i]) {
				var row = top.HEURIST.search.renderResult(style, top.HEURIST.search.results.records[firstIndex+i]);
				innerHTML += row;
				if (i < recordCount / 2) {
					leftHTML += row;
				} else {
					rightHTML += row;
				}
			}
		}

		var resultsDiv = document.getElementById("result-rows");

		if (style == "list"  ||  style == "thumbs") {
			resultsDiv.innerHTML = innerHTML;
		} else if (style = "two-col") {
			resultsDiv.innerHTML = "<div class=two-col-cell><div class=two-col-inner-cell-left>" + leftHTML + "</div></div><div class=two-col-cell><div class=two-col-inner-cell-right>" + rightHTML + "</div></div>";
		}

		// add click handlers
		var rows = $(".result_row", resultsDiv).get();
		for (var i=0; i < rows.length; ++i) {

			var result = rows[i];
			var t_span = result.childNodes[0];
			var rec_title = $(".rec_title", result)[0];
			var rectype_img = result.childNodes[4];
			var pin_img = result.childNodes[0];

			top.HEURIST.registerEvent(result, "click", top.HEURIST.search.resultItemOnClick);
			top.HEURIST.registerEvent(t_span, "click", top.HEURIST.search.resultItemOnClick);
			top.HEURIST.registerEvent(rectype_img, "click", top.HEURIST.search.resultItemOnClick);
			top.HEURIST.registerEvent(rec_title, "click", top.HEURIST.search.resultItemOnClick);
			top.HEURIST.registerEvent(pin_img, "click", result.getAttribute("bkmk_id") ? top.HEURIST.search.resultItemOnClick : top.HEURIST.search.addBookmark);
			top.HEURIST.registerEvent(result, "dblclick", (top.HEURIST.util.getDisplayPreference("double-click-action") == "edit") ? top.HEURIST.search.edit : top.HEURIST.search.resultItemOnClick);
		}

		var thumbs = $(".result_thumb", resultsDiv).get();
		for (var i=0; i < thumbs.length; ++i) {
			var result = thumbs[i];
			var pin_img = $(".unbookmarked", result)[0];
			var thumb_img = $(".thumbnail, .no-thumbnail, .rec_title", result)[0];
			var rec_title = $(".rec_title", result)[0];

			top.HEURIST.registerEvent(result, "click", top.HEURIST.search.resultItemOnClick);

			if (pin_img) {
				top.HEURIST.registerEvent(pin_img, "click", result.getAttribute("bkmk_id") ? function(){} : top.HEURIST.search.addBookmark);
			}
			top.HEURIST.registerEvent(result, "dblclick", (top.HEURIST.util.getDisplayPreference("double-click-action") == "edit") ? top.HEURIST.search.edit : top.HEURIST.search.open_out);
			if (rec_title) {
					top.HEURIST.registerEvent(rec_title, "click", top.HEURIST.search.resultItemOnClick);
			}
			if (thumb_img) {
					top.HEURIST.registerEvent(thumb_img, "click", top.HEURIST.search.resultItemOnClick);
			}

		}

		top.HEURIST.registerEvent(window, "load", top.HEURIST.search.trimAllLinkTexts);
		top.HEURIST.registerEvent(window, "load", function() {
			if (document.getElementById("legend-box")) {
				top.HEURIST.search.toggleLegend();
				top.HEURIST.search.toggleLegend();
			}
		});
	},

	setResultStyle: function(style) {
		for (var i in top.HEURIST.search.infos) {  //closes all infos
		var info = top.HEURIST.search.infos[i];
  		info.parentNode.removeChild(info);
		delete top.HEURIST.search.infos[i];
		}
		var current_style = top.HEURIST.util.getDisplayPreference("search-result-style");
		if (style !== current_style) {
			top.HEURIST.util.setDisplayPreference("search-result-style", style);
			if (top.HEURIST.search.results.totalRecordCount) {
				if (style == "thumbs") {
					top.HEURIST.search.loadSearch();
				} else {
					top.HEURIST.search.gotoResultPage(top.HEURIST.search.currentPage);
				}
			};
		}
	},

	trimLinkText: function(link_elt, offset_width) {
		// If the link text is too long, it wraps onto another line.  Abbreviate it.

		if( offset_width < 0  ||  link_elt.parentNode.style.display == "none") return;

		link_elt.innerHTML = link_elt.getAttribute("origInnerHTML");
		if (link_elt.offsetWidth >= offset_width) {
			while (link_elt.offsetWidth >= offset_width) {
				if (link_elt.offsetWidth / offset_width > 1.1)
					link_elt.innerHTML = link_elt.innerHTML.substring(0, Math.round(link_elt.innerHTML.length * offset_width / link_elt.offsetWidth));
				else
					link_elt.innerHTML = link_elt.innerHTML.substring(0, link_elt.innerHTML.length-20);
			}

			link_elt.innerHTML = link_elt.innerHTML + "...&nbsp; ";
		}

		link_elt.parentNode.style.visibility = "visible";
	},

	trimAllLinkTexts: function() {
		var results = $(".result_row", "#result-rows").get();
		for (var i=0; i < results.length; ++i) {
			var offset_width = Math.floor(0.9 * results[i].parentNode.offsetWidth) - 20;
			var days_bad = results[i].childNodes[6];
			var self_link = results[i].childNodes[5];
			top.HEURIST.search.trimLinkText(self_link, offset_width - (days_bad && days_bad.offsetWidth ? days_bad.offsetWidth : 0));
		}
	},

	renderNavigation: function() {
		// render the navigation necessary to move between pages of results

		if (! top.HEURIST.search.results  ||  top.HEURIST.search.results.totalRecordCount == 0) {
			// no results ... don't display page navigation
			if (document.getElementById("page-nav"))
				document.getElementById("page-nav").innerHTML = "";
			document.getElementById("search-status").className = "noresult"
				+ (window.HEURIST.parameters["w"] == "bookmark" ? "" : " all");
			document.getElementById("resource-count").innerHTML = "";
			return;
		}

		var i;
		var totalRecordCount = top.HEURIST.search.results.totalRecordCount;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		var pageCount = Math.ceil(totalRecordCount / resultsPerPage);
		var currentPage = top.HEURIST.search.currentPage;

		var firstRes = (currentPage*top.HEURIST.search.resultsPerPage+1);
		var lastRes = Math.min((currentPage+1)*resultsPerPage, totalRecordCount);


		if (totalRecordCount == 1) {
			document.getElementById("resource-count").innerHTML = "1 record";
		} else {
			document.getElementById("resource-count").innerHTML = firstRes + " - " + lastRes + " [ of " + totalRecordCount + " ]";
		}

		var innerHTML = "";
		var start, finish;

		if (pageCount < 2) {
			if (document.getElementById("page-nav"))
				document.getElementById("page-nav").innerHTML = "";
			return;
		}

		// KJ's patented heuristics for awesome useful page numbers
		if (pageCount > 9) {
			if (currentPage < 5) { start = 1; finish = 8; }
			else if (currentPage < pageCount-5) { start = currentPage - 2; finish = currentPage + 4; }
			else { start = pageCount - 7; finish = pageCount; }
		} else {
			start = 1; finish = pageCount;
		}

		innerHTML = "<span class=nav>";
		if (firstRes > 1) {
			innerHTML += "<a id=prev_page href=# onclick=\"top.HEURIST.search.gotoPage('prev'); return false;\">previous&nbsp;page</a>&nbsp;&nbsp;";
		} else if (pageCount > 1) {	// don't display this, but let it affect the layout
			innerHTML += "<a id=prev_page href=# style=\"visibility: hidden;\">previous&nbsp;page</a>&nbsp;&nbsp;";
		}

		if (start != 1) {
			innerHTML += " <a href=# id=p0 onclick=\"top.HEURIST.search.gotoPage(0); return false;\">1</a> ... ";
		}
		for (i=start; i <= finish; ++i) {
			var cstring = (i == currentPage+1)? "class=active " : "";
			innerHTML += " <a href=# id=p"+(i-1)+" "+cstring+"onclick=\"top.HEURIST.search.gotoPage("+(i-1)+"); return false;\">"+i+"</a> ";
		}
		if (finish != pageCount) {
			innerHTML += " ... <a href=# id=p"+(pageCount-1)+" onclick=\"top.HEURIST.search.gotoPage("+(pageCount-1)+"); return false;\">"+pageCount+"</a> ";
		}
		if (lastRes < totalRecordCount) {
			innerHTML += "&nbsp;&nbsp;<a id=next_page href=# onclick=\"top.HEURIST.search.gotoPage('next'); return false;\">next&nbsp;page</a>";
		} else if (pageCount > 1) {
			innerHTML += "&nbsp;&nbsp;<a id=next_page href=# style=\"visibility: hidden;\">next&nbsp;page</a>";
			// innerHTML += "&nbsp;&nbsp;<span id=next_page>next&nbsp;page</span>";
		}

		if (pageCount > 1  &&  totalRecordCount < top.HEURIST.search.pageLimit) {
			innerHTML += "&nbsp;&nbsp;<a href=# onclick=\"top.HEURIST.search.showAll(); return false;\">show&nbsp;all</a>";
		}

		innerHTML += "</span>";

		if (document.getElementById("page-nav"))
			document.getElementById("page-nav").innerHTML = innerHTML;
	},

	gotoPage: function(pageNum) {
		if (pageNum == "prev") {
			pageNum = top.HEURIST.search.currentPage - 1;
		} else if (pageNum == "next") {
			pageNum = top.HEURIST.search.currentPage + 1;
		}

		if (pageNum < 0) {
			pageNum = 0;
		} else if (top.HEURIST.search.results.totalRecordCount <= pageNum*top.HEURIST.search.resultsPerPage) {
			pageNum = Math.floor(top.HEURIST.search.results.totalRecordCount / top.HEURIST.search.resultsPerPage);
		}

		if (pageNum != top.HEURIST.search.currentPage) {
			top.HEURIST.search.gotoResultPage(pageNum);
		}
	},

	showAll: function() {
		top.HEURIST.search.gotoResultPage(0, true);
	},

	gotoResultPage: function(pageNumber, all) {
		var results = top.HEURIST.search.results;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;

		if (all) {
			resultsPerPage = top.HEURIST.search.resultsPerPage = results.totalRecordCount;
			pageNumber = 0;
		}

		if (pageNumber < 0  ||  results.totalRecordCount <= pageNumber*resultsPerPage) {
			alert("No more results available");	// can't imagine how we'd get here ... evile haxxors?
			return;
		}

		// Remove any info iframes ...
		top.HEURIST.search.closeInfos();

		top.HEURIST.search.currentPage = pageNumber;
		top.HEURIST.search.deselectAll();

		// Check if we've already loaded the given page ...
		var firstOnPage = pageNumber*resultsPerPage;
		var lastOnPage = Math.min((pageNumber+1)*resultsPerPage, results.totalRecordCount)-1;
		if (results.records[firstOnPage]  &&  results.records[lastOnPage]) {
			// Hoorah ... all the data is loaded (well, the first and last entries on the page ...), so render it
			document.getElementById("results").scrollTop = 0;
			top.HEURIST.search.renderSearchResults(firstOnPage, lastOnPage);
//			top.HEURIST.search.toggleResultItemSelect(results.records[firstOnPage][2]);
			var viewerFrame = document.getElementById("viewer-frame");
			if (all) {
				top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectall");
			}else{
				top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-pagechange", "pageNum=" + (pageNumber +1));
			}
			top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange");
		} else {
			// Have to wait for the data to load
			document.getElementById("result-rows").innerHTML = "";
			document.getElementById("search-status").className = "loading";
		}

		top.HEURIST.search.renderNavigation();
	},

	edit: function(e, targ) {
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) e.stopPropagation();
		}

		if (! targ) {
			if (e.target) targ = e.target;
			else if (e.srcElement) targ = e.srcElement;
			if (targ.nodeType == 3) targ = targ.parentNode;
		}

		if (targ.getAttribute("bib_id")) {
			var result_div = targ;
		}else if (targ.parentNode  &&  targ.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode;
		}else if (targ.parentNode.parentNode  &&  targ.parentNode.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode.parentNode;
		}else return;

		var bib_id = result_div.getAttribute("bib_id");

		window.open(top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
					top.HEURIST.search.sid + "&bib_id="+bib_id+
					(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));

		return false;
	},

	explore: function(e, targ) {
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) e.stopPropagation();
		}

		if (! targ) {
			if (e.target) targ = e.target;
			else if (e.srcElement) targ = e.srcElement;
			if (targ.nodeType == 3) targ = targ.parentNode;
		}

		if (targ.getAttribute("bib_id")) {
			var result_div = targ;
		}
		else if (targ.parentNode  &&  targ.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode;
		}
		else if (targ.parentNode.parentNode  &&  targ.parentNode.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode.parentNode;
		}
		else return;

		var bib_id = result_div.getAttribute("bib_id");
		//TODO: parameterise the codebase for the relbrowser
		window.open( "/cocoon"+top.HEURIST.basePath+"relbrowser/main/item/" + bib_id +
					(top.HEURIST.instance && top.HEURIST.instance.name ? "/?instance=" + top.HEURIST.instance.name : ""));
//		window.open(top.HEURIST.instance.exploreURL+ "" + bib_id);

		return false;
	},

	edit_short: function(bib_id,result_div) {
		top.HEURIST.search.closeInfos;
		//top.HEURIST.search.setRecordView("full");
		var infos = top.HEURIST.search.infos;
		if (infos["bib:" + bib_id]) {
			// bib info is already displaying -- hide it
			var info = infos["bib:" + bib_id];
			result_div.className = result_div.className.replace(" expanded", "");
			info.parentNode.removeChild(info);
			delete infos["bib:" + bib_id];
		}
		var info_div = document.createElement("iframe");
		var pageRight = document.getElementById('page-right');
		info_div.className = "info";
		info_div.frameBorder = 0;
		info_div.style.height = "100%";
		info_div.src = top.HEURIST.basePath+ "records/edit/editRecord.html?bib_id="+bib_id + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		infos["bib:" + bib_id] = info_div;
		result_div.className += " expanded";
		pageRight.appendChild(info_div);
		return false;
	},

	publisherTest: function() {
		top.HEURIST.search.closeInfos(); // closes infos
		top.HEURIST.search.setRecordView("full"); // sets full view
		var info_div = document.createElement("iframe");
		var infos = top.HEURIST.search.infos;
		var pageRight = document.getElementById('page-right');
		info_div.className = "info";
		info_div.frameBorder = 0;
		infos["publish"] = info_div;
		info_div.src =  top.HEURIST.basePath+"viewers/printview/";
		document.getElementById("helper").style.display = "none";
		pageRight.appendChild(info_div);
		return false;
	},

	selectedRecordIds: [],
	selectedRecordDivs: {},

	toggleResultItemSelect: function(bib_id,resultDiv) {
		if (top.HEURIST.search.selectedRecordDivs[bib_id]) { // was selected so deselect
			top.HEURIST.search.deselectResultItem(bib_id);
		}else if (bib_id) {
			if (!resultDiv) {
				resultDiv = $('div[class~=result_thumb] , div[class~=result_row]').filter('div[bib_id='+ bib_id +']').get(0);
			}
			var cb = resultDiv.getElementsByTagName("INPUT");
			if (cb[0] && cb[0].name == "bib[]") {
				cb = cb[0];
			}else{
				cb = null;
			}
			top.HEURIST.search.bib_ids[bib_id] = true;	//deprecated
			if (cb) cb.checked = true;					//deprecated
			var bkmk_id = cb.parentNode.getAttribute("bkmk_id");
			if (bkmk_id)
				top.HEURIST.search.bkmk_ids[bkmk_id] =  true;
			resultDiv.className += " selected";
			top.HEURIST.search.selectedRecordDivs[bib_id] = resultDiv;
			top.HEURIST.search.selectedRecordIds.push(bib_id);
		}
	},

	deselectResultItem: function(bib_id) {
		var resultDiv = top.HEURIST.search.selectedRecordDivs[bib_id];
		if (resultDiv) {
			delete top.HEURIST.search.selectedRecordDivs[bib_id];
			var cb = resultDiv.getElementsByTagName("INPUT");
			if (cb[0] && cb[0].name == "bib[]") {
				cb = cb[0];
			}else{
				cb = null;
			}
			top.HEURIST.search.bib_ids[bib_id] = null;
			if (cb) cb.checked = false;
			var bkmk_id = cb.parentNode.getAttribute("bkmk_id");
			if (bkmk_id)
				top.HEURIST.search.bkmk_ids[bkmk_id] =  false;
			resultDiv.className = resultDiv.className.replace(" selected", "");
			for(var i = 0; i < top.HEURIST.search.selectedRecordIds.length; i++){
				if (top.HEURIST.search.selectedRecordIds[i] == bib_id) {
					top.HEURIST.search.selectedRecordIds.splice(i,1);
					break;
				}
			}
		}
	},

	resultItemOnClick: function(e, targ) {
		//find the event and target element
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) e.stopPropagation();
		}

		if (! targ) {
			if (e.target) targ = e.target;
			else if (e.srcElement) targ = e.srcElement;
			if (targ.nodeType == 3) targ = targ.parentNode;
		}

		if (targ.getAttribute("bib_id")) {
			var result_div = targ;
		}
		else if (targ.parentNode  &&  targ.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode;
		}
		else return;  // no target so we can't do anything

		var bib_id = result_div.getAttribute("bib_id");

		if ( e.ctrlKey) { // CTRL + Click -  do multiselect functionality single item select and unselect from list
			top.HEURIST.search.toggleResultItemSelect(bib_id,result_div);
		}else if (e.shiftKey){//SHIFT + Click
		// find all items from current click item to last selected
			var bibIdToIndexMap = top.HEURIST.search.results.bibIdToResultIndex;
			var selectedBibIds = top.HEURIST.search.selectedRecordIds;
			var lastSelectedIndex = bibIdToIndexMap[selectedBibIds[selectedBibIds.length - 1]];
			var clickedIndex = bibIdToIndexMap[bib_id];
			var newSelectedBibIdMap = {};
			var newSelectedBibIds = [];
			// order this object so lasted selected remains last.
			if (clickedIndex < lastSelectedIndex){
				for(var i = clickedIndex; i <= lastSelectedIndex; i++) {
					newSelectedBibIdMap[top.HEURIST.search.results.records[i][2]] = true;
					newSelectedBibIds.push(top.HEURIST.search.results.records[i][2]);
				}
			}else{
				for(var i = clickedIndex; i >= lastSelectedIndex; i--) {
					newSelectedBibIdMap[top.HEURIST.search.results.records[i][2]] = true;
					newSelectedBibIds.push(top.HEURIST.search.results.records[i][2]);
				}
			}
		// for all selectedBibIds items not in new selection set, deselect item (which also removes it from selectedBibIds)
			for(var j =0;  j< selectedBibIds.length; j++) {
				if (!newSelectedBibIdMap[selectedBibIds[j]]){
					top.HEURIST.search.deselectResultItem(selectedBibIds[j]);
					j--; //adjust for changed selectedBibIds
				}
			}
		//select all unselected new items and replace the selectedBidIds
			for(var newSelectedBibId in newSelectedBibIdMap){
				if (!top.HEURIST.search.selectedRecordDivs[newSelectedBibId]){
					top.HEURIST.search.toggleResultItemSelect(newSelectedBibId);
				}
			}
			 top.HEURIST.search.selectedRecordIds = newSelectedBibIds;
		}else{ //Normal Click -- assume this is just a normal click and single select;
			var clickedResultFound = false;
			var j=0;
			while (top.HEURIST.search.selectedRecordIds.length && !clickedResultFound ||  clickedResultFound && top.HEURIST.search.selectedRecordIds.length > 1) {
				if (!clickedResultFound && top.HEURIST.search.selectedRecordIds[0] == bib_id) {
					clickedResultFound=true;
					j=1;
					continue;
				}
				top.HEURIST.search.deselectResultItem(top.HEURIST.search.selectedRecordIds[j]);
			}
			if (!clickedResultFound) { // clicked result was not previously selected so toggle to select
				top.HEURIST.search.toggleResultItemSelect(bib_id,result_div);
			}
		}

		//send selectionChange event
		var viewerFrame = document.getElementById("viewer-frame");
		var mapFrame = document.getElementById("map-frame");
		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", "selectedIds=" + top.HEURIST.search.selectedRecordIds.join(","));
		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange", "selectedIds=" + top.HEURIST.search.selectedRecordIds.join(","));
		return false;

	},


	hideLoading: function (){
		var page_right = document.getElementById("page-right");
	},

	infoHeight: function (bib_id){
		var the_height=document.getElementById(bib_id).contentWindow.document.body.scrollHeight;
		//change the height of the iframe
		document.getElementById(bib_id).height=the_height;
	},

	setRecordView: function(recordViewStyle) {
		top.HEURIST.search.record_view_style = recordViewStyle;
		var page_right = document.getElementById("page-right");
		if (recordViewStyle == "summary") {
			document.getElementById("record-style-full-link").className = "";
			document.getElementById("record-style-summary-link").className = "pressed";
			var frames = $(".info", page_right).get();
			for (var i=0; i < frames.length; ++i) {
			var result = frames[i];
			result.className = "info summary";}
			} else {
			document.getElementById("record-style-full-link").className = "pressed";
			document.getElementById("record-style-summary-link").className = "";
			var frames = $(".info", page_right).get();
			for (var i=0; i < frames.length; ++i) {
			var result = frames[i];
			result.className = "info";

			}
		}

	},

	conditional_open_out: function(e) {
		if (! e) e = window.event;
		var targ = null;
		if (e.target) targ = e.target;
		else if (e.srcElement) targ = e.srcElement;
		if (targ.nodeType == 3) targ = targ.parentNode;
		if (targ  &&  targ.className.match("result_row")) top.HEURIST.search.open_out(null, targ.childNodes[3]);
	},

	closeInfos: function() {
		top.HEURIST.search.hideLoading();
		for (var i in top.HEURIST.search.infos) {
			var info = top.HEURIST.search.infos[i];
			//info.parentNode.className = info.parentNode.className.replace(" expanded", "");
			info.parentNode.removeChild(info);
			delete top.HEURIST.search.infos[i];}
		var resultsDiv = document.getElementById("result-rows");
		for (var i=0; i < resultsDiv.childNodes.length; ++i) {
			var result = resultsDiv.childNodes[i];
			result.className = result.className.replace(" expanded", "");
		}
	},

	addBookmark: function(e) {
		if (! e) e = window.event;
		var targ = null;
		if (e.target) targ = e.target;
		else if (e.srcElement) targ = e.srcElement;
		if (targ.nodeType == 3) targ = targ.parentNode;
		if (! targ) return;
		var row = targ.parentNode;
		if (! row.getAttribute("bib_id")) {
			row = row.parentNode;
		}
		if (! row) return;

		var action_fr = document.getElementById("i_action");
		var bib_ids_elt = action_fr.contentWindow.document.getElementById("bib_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bib_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = top.HEURIST.basePath+ "search/actions/actionHandler.php" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "");
			return;
		}

		bib_ids_elt.value = row.getAttribute("bib_id");
		action_elt.value = "bookmark_reference";
		action_elt.form.submit();
	},


	fillInSavedSearches: function(wg) {
		printSavedSearch = function(cmb, wg) {
			var sid = cmb[2];

			var active = (sid == window.HEURIST.parameters["sid"]);


			var innerHTML = "<nobr" + (active ? " id=activesaved" : "") + (cmb[4] || wg ? ">" : " class=bkmk>");
			if (active) {

				innerHTML += "<img class=\"saved-search-edit\" title=\"rename\" src=\"" +top.HEURIST.basePath+ "common/images/edit_pencil_9x11.gif\" align=absmiddle onclick=\"top.HEURIST.util.popupURL(window, '" +top.HEURIST.basePath+ "search/saved/saveSearchPopup.html?mode=rename&slabel=" + encodeURIComponent(cmb[0]) + "&sid="+sid+"&wg="+ (wg ? wg : 0) +(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "")+"');\">";
				innerHTML += "<img class=\"saved-search-edit\" title=\"delete\" src=\"" +top.HEURIST.basePath+ "common/images/delete6x7.gif\" align=absmiddle onclick=\"top.HEURIST.search.deleteSearch('"+ cmb[0] +"',"+ (wg ? wg : 0) + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "") +");\">";
			}
			innerHTML += "<div><a id='ss" + sid + "' href='" + (wg ? top.HEURIST.basePath : "")
					  + cmb[1] + "&amp;label=" + encodeURIComponent(cmb[0]) + "&amp;sid=" + sid + (top.HEURIST.instance && top.HEURIST.instance.name ? "&amp;instance=" + top.HEURIST.instance.name : "") + "'>" + cmb[0] + "</a></div>";


			innerHTML += "</nobr>";
			innerHTML += " ";
			return innerHTML;
		};

		if (! top.HEURIST.user) return;

		var savedSearches = wg ? top.HEURIST.user.workgroupSavedSearches[wg] : top.HEURIST.user.savedSearches;

		if (! savedSearches) return;
		var innerHTML = "";
		if (wg) {
			for (var i = 0; i < savedSearches.length; ++i) {
				cmb = savedSearches[i];
				innerHTML += printSavedSearch(cmb, wg);
			}
			if (innerHTML == "") innerHTML = "No saved searches for workgroup";
		} else {
			var showSS = {
				"my": (top.HEURIST.util.getDisplayPreference("my-records-searches") == "show"),
				"all": (top.HEURIST.util.getDisplayPreference("all-records-searches") == "show"),
				"wg": (top.HEURIST.util.getDisplayPreference("workgroup-searches") == "show")
			};

			var myDiv, allDiv, wgDiv,
				className, searchDiv;

			// my records searches
			myDiv = document.getElementById("my-records-saved-searches");
			className = myDiv.className.replace(/\s*hide/, "");
			myDiv.className = (showSS["my"] ? className : className + " hide");
			searchDiv = document.getElementById("my-records-saved-searches-searches");

			innerHTML = "";
			for (var i = 0; i < savedSearches.length; ++i) {
				cmb = savedSearches[i];
				if (! cmb[4])
					innerHTML += printSavedSearch(cmb, wg);
			}
			searchDiv.innerHTML = innerHTML;

			document.getElementById("my-blog-link").href = top.HEURIST.basePath+ "viewers/blog/index.html?u=" + top.HEURIST.get_user_id() +
				(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");


			// all records searches
			allDiv = document.getElementById("all-records-saved-searches");
			className = myDiv.className.replace(/\s*hide/, "");
			allDiv.className = (showSS["all"] ? className : className + " hide");
			searchDiv = document.getElementById("all-records-saved-searches-searches");

			innerHTML = "";
			for (var i = 0; i < savedSearches.length; ++i) {
				cmb = savedSearches[i];
				if (cmb[4])
					innerHTML += printSavedSearch(cmb, wg);
			}
			searchDiv.innerHTML = innerHTML;


			// workgroup saved searches
			wgDiv = document.getElementById("workgroup-saved-searches");
			className = myDiv.className.replace(/\s*hide/, "");
			wgDiv.className = (showSS["wg"] ? className : className + " hide");
			searchDiv = document.getElementById("workgroup-saved-searches-searches");

			innerHTML = "";
			for (var i = 0; i < top.HEURIST.user.workgroups.length; ++i) {
				var w = top.HEURIST.user.workgroups[i];

				var pref = top.HEURIST.util.getDisplayPreference("workgroup-searches-" + w);
				var hide = (! pref  ||  pref == "hide");

				innerHTML += "<div id=workgroup-searches-" + w + (hide ? " class=hide" : "") + ">";
				innerHTML += "<div class=saved-search-subheading title=\"" + (top.HEURIST.workgroups[w].description || "" ) + "\" onclick=\"top.HEURIST.search.toggleSavedSearches(this.parentNode);\">" + top.HEURIST.workgroups[w].name + "</div>";
				innerHTML += "<div class=content>";
				innerHTML += "<div class=saved-search-subsubheading><a href='" +top.HEURIST.basePath+ "search/usergroupHomepage.html?wg=" + w +(top.HEURIST.instance && top.HEURIST.instance.name ? "&amp;instance=" + top.HEURIST.instance.name : "")+ "'>Workgroup page</a></div>";
				innerHTML += "<div class=saved-search-subsubheading><a target=\"_blank\" href='" +top.HEURIST.basePath+ "viewers/blog/index.html?g=" + w + (top.HEURIST.instance && top.HEURIST.instance.name ? "&amp;instance=" + top.HEURIST.instance.name : "") +"'>"+top.HEURIST.workgroups[w].name+" Blog</a></div>";

				var searches = top.HEURIST.user.workgroupSavedSearches[w];
				if (searches  &&  searches.length) {
					innerHTML += "<div class=saved-search-subsubheading>Saved searches (shared)</div>";
					for (var j = 0; j < searches.length; ++j) {
						innerHTML += printSavedSearch(searches[j], w);
					}
				}

				var tags = [];
				for (var j = 0; j < top.HEURIST.user.workgroupTagOrder.length; ++j) {
					var tag = top.HEURIST.user.workgroupTags[top.HEURIST.user.workgroupTagOrder[j]];
					if (tag[0] == w) tags.push(tag[1]);
				}

				if (tags.length) {
					innerHTML += "<div class=saved-search-subsubheading>Workgroup Tags</div>";
					for (var j = 0; j < tags.length; ++j) {
						innerHTML += "<nobr><a href='"+top.HEURIST.basePath+"search/search.html?ver=1&w=all&q=tag:\"" + top.HEURIST.workgroups[w].name + "\\" + tags[j] + "\"&label=Tag+\"" + tags[j] + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "") + "\"'>" + tags[j] + "</a></nobr>";
					}
				}

				innerHTML += "</div>";
				innerHTML += "</div>";
			}
			if (innerHTML == "") {
				innerHTML = "<div class=saved-search-subheading>You are not a member of any workgroups</div>";
			}
			searchDiv.innerHTML = innerHTML;

			var scrollPos = top.HEURIST.util.getDisplayPreference("left-panel-scroll");
			if (scrollPos > 0) {
				document.getElementById("left-panel-content").scrollTop = scrollPos;
			}

			$("#saved a").click(function() {
				var scroll = document.getElementById("left-panel-content").scrollTop;
				if (top.HEURIST.util.getDisplayPreference("left-panel-scroll") != scroll) {
					top.HEURIST.util.setDisplayPreference("left-panel-scroll", scroll);
				}
			});

		}
	},

	toggleSavedSearches: function(div) {
		var prefName;
		if (div.id == "my-records-saved-searches") {
			prefName = "my-records-searches";
		} else if (div.id == "all-records-saved-searches") {
			prefName = "all-records-searches";
		} else if (div.id == "workgroup-saved-searches") {
			prefName = "workgroup-searches";
		} else if (div.id.match(/workgroup-searches-[0-9]+/)) {
			prefName = div.id;
		} else {
			return;
		}

		if (div.className.match(/hide/)) {
			div.className = div.className.replace(/\s*hide/, "");
			top.HEURIST.util.setDisplayPreference(prefName, "show");
		} else {
			div.className += " hide";
			top.HEURIST.util.setDisplayPreference(prefName, "hide");
		}
	},

	fillInKeywordSearches: function() {
		if (! top.HEURIST.user) return;

		var tags = top.HEURIST.user.tags;
		var innerHTML = "";
		var kwd;
		for (var i = 0; i < tags.length; ++i) {
			kwd = encodeURIComponent(tags[i]);
//			innerHTML += "<a href='"+top.HEURIST.basePath+"search/search.html?ver=1&w=bookmark&q=tag:\"" + kwd + "\"&label=Tag+\"" + kwd + "\"'>" + tags[i] + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "") + "</a> ";
			innerHTML += "<a href='"+top.HEURIST.basePath+"search/search.html?ver=1&w=bookmark&q=tag:\"" + kwd + "\"&label=Tag+\"" + kwd + "\"'>" + tags[i] + "</a> ";
		}
		var kwd_search_elt = top.document.getElementById("tag-search-links");
		if (kwd_search_elt) {
			kwd_search_elt.innerHTML = innerHTML;
		}
	},

	insertSavedSearch: function(label, url, wg, svs_ID) {
		var w_all = url.match(/w=bookmark/) ? 0 : 1;
		var savedSearches = wg ? top.HEURIST.user.workgroupSavedSearches[wg] : top.HEURIST.user.savedSearches;
		var newEntry = wg ? [label, url, svs_ID] : [label, url, svs_ID, 0, w_all];
		var i = 0;
		while (i < savedSearches.length) {
			var sid = savedSearches[i][2];
			//if this is a renamed search or ovewritten search
			if (sid == svs_ID) {
				document.getElementById("active-label").innerHTML = newEntry[0];
				savedSearches[i] = newEntry;
				top.HEURIST.search.fillInSavedSearches();
				return;
			}
			// if it's a workgroup search, or the right type of personal search,
			// and it should go before the current search, splice it in
			if ((wg || savedSearches[i][4] == w_all)  &&  savedSearches[i][0].toLowerCase() >= label.toLowerCase())
				break;
			// the case below is when the search should be placed last in the list of "My records" searches
			if (! wg  &&  savedSearches[i][4] != w_all  &&  i > 0  &&  savedSearches[i-1][4] == w_all)
				break;
			++i;
		}
		savedSearches.splice(i, 0, newEntry);
		top.HEURIST.search.fillInSavedSearches();
	},

	removeSavedSearch: function(label, wg) {
		var savedSearches = wg ? top.HEURIST.user.workgroupSavedSearches[wg] : top.HEURIST.user.savedSearches;
		var i = 0;
		while (i < savedSearches.length) {
			if (savedSearches[i][0]  == label)
				break;
			++i;
		}
		savedSearches.splice(i, 1);
		top.HEURIST.search.fillInSavedSearches(top.HEURIST.workgroup ? top.HEURIST.workgroup.wg_id : null);
	},

	deleteSearch: function(name, wg) {
		if (wg > 0 ){
			if (!confirm("Are you sure you wish to delete this saved search?\n" + "This will affect other workgroup members.")) {
		 		return;
			}
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+ "search/saved/deleteSavedSearch.php?wg="+wg+"&label="+escape(name) + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""), function(response) {
			if (response.deleted) {
				if (top.HEURIST.search) {
					top.HEURIST.search.removeSavedSearch(name, wg);
				}
			}
		});
	},

	launchAdvancedSearch: function() {
		var q = document.getElementById("q").value;
		var url = top.HEURIST.basePath+ "search/queryBuilderPopup.php?" + encodeURIComponent(q) + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		top.HEURIST.util.popupURL(window, url, { callback: top.HEURIST.search.advancedSearchCallback });
	},

	advancedSearchCallback: function(q) {
		if (q === undefined) {
			// user clicked close-button ... do nothing
		} else {
			this.document.getElementById("q").value = q;
			this.document.forms[0].submit();
		}
		return true;
	},

	autoPopupLink: function(e) {
		if (! e) e = window.event;
		var targ;
		if (e.target) targ = e.target;
		else if (e.srcElement) targ = e.srcElement;
		if (targ.nodeType == 3) targ = targ.parentNode;

		if (! targ.href  &&  targ.parentNode.href) targ = targ.parentNode;	// sometimes get the span, not the link
		top.HEURIST.util.popupURL(top, targ.href);

		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		return false;
	},

	cb_onclick: function(cb) {
		if (cb  &&  cb.name == "bib[]") {
			var bib_id = cb.parentNode.getAttribute("bib_id");
			top.HEURIST.search.bib_ids[bib_id] = cb.checked ? true : null;
			var bkmk_id = cb.parentNode.getAttribute("bkmk_id");
			if (bkmk_id)
				top.HEURIST.search.bkmk_ids[bkmk_id] = cb.checked ? true : null;
		}
	},

	mapSelected: function() {
		if (top.HEURIST.search.selectedRecordIds.length == 0) {
			alert("Selected a record first");
			return;
		};
		var p = top.HEURIST.parameters;
		var recIds = top.HEURIST.search.selectedRecordIds.slice(0,500); // maximum number of records ids 500
		if (top.HEURIST.search.selectedRecordIds.length >= 500) {
			alert("Selected record count is great than 500, mapping the first 500 records!");
		}
		var query_string = '?ver='+(p['ver'] || "") + '&w=all&q=ids:' +
			recIds.join(",") +
			'&stype='+(p['stype'] || "") +
			'&instance='+(p['instance'] || "");
		query_string = encodeURI(query_string);
		url = top.HEURIST.basePath+ "viewers/map/showGMapWithTimeline.html" + query_string;
		// set frame source to url
		// make frame visible
		var mapDiv = document.createElement("div");
		mapDiv.id = "mapDiv";
		mapDiv.style.display = "block";
		var closeMapButton = document.createElement("div");
		closeMapButton.className = "close-button";
		closeMapButton.onclick = top.HEURIST.search.closeMap;
		closeMapButton.title = "Close Map";
		mapDiv.appendChild(closeMapButton);
		var mapiFrame = document.createElement("iFrame");
		mapiFrame.style.border = "0";
		mapiFrame.style.width = "100%";
		mapiFrame.style.height = "100%";
		mapiFrame.src = url
		mapDiv.appendChild(mapiFrame);
		//document.getElementById("page").appendChild(mapDiv);
		document.getElementById("map-frame").src = url;
	},
	closeMap: function() {
		var mapDiv = document.getElementById("mapDiv");
		mapDiv.parentNode.removeChild(mapDiv);
	},

	selectAll: function() {
		if (top.HEURIST.search.results.totalRecordCount > top.HEURIST.search.resultsPerPage) {
			top.HEURIST.search.selectAllPages();
		} else {
			top.HEURIST.search.selectAllFirstPage();
		}
	},

	selectAllFirstPage: function() {
		var bibs = document.getElementsByName("bib[]");
		for (var i=0; i < bibs.length; ++i) {
			bibs[i].checked = true;
			var bib_id = bibs[i].parentNode.getAttribute("bib_id");
			top.HEURIST.search.bib_ids[bib_id] = true;
			var bkmk_id = bibs[i].parentNode.getAttribute("bkmk_id");
			if (bkmk_id)
				top.HEURIST.search.bkmk_ids[bkmk_id] = true;
		}
	},

	selectAllPages: function() {
		// User has asked for ALL the results on ALL pages ...
		// Since the web interface only loads the first 100 results at first, we don't have all the results lying around.
		// However, we only need the bib_ids so we do a separate request.

		if (top.HEURIST.search.results.totalRecordCount > 1000) {
			alert("There are too many search results to perform operations on.  Please refine your search first.");
			document.getElementById("select-all-checkbox").checked = false;
			return;
		}
		top.HEURIST.util.bringCoverallToFront(true);

		var search = top.location.search || "?w=" + top.HEURIST.parameters["w"] + "&q=" + top.HEURIST.parameters["q"];
		top.HEURIST.util.sendRequest(top.HEURIST.basePath+ "search/getRecordIDsForSearch.php" + search + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""), function(xhr) {
			if (! xhr.responseText.match(/^\[({[a-zA-Z0-9_,:"]*},?)*\]$/)) {
				top.HEURIST.search.selectAllPagesCallback([]);
			}
			else {
				top.HEURIST.search.selectAllPagesCallback(eval(xhr.responseText));
			}
		});
	},

	selectAllPagesCallback: function(bibIDs) {
		var i;
		var search_bib_ids;
		var search_bkmk_ids;

		if (bibIDs  &&  bibIDs.length > 0) {
//			if (confirm("Select all " + bibIDs.length + " records on all pages?")) {
				document.getElementById("select-all-checkbox").checked = true;
				top.HEURIST.search.selectAllFirstPage();

				search_bib_ids = {};
				search_bkmk_ids = {};
				for (i=0; i < bibIDs.length; ++i) {
					var bib_id = bibIDs[i].bib_id;
					var bkmk_id = bibIDs[i].bkmk_id;
					search_bib_ids[bib_id] = true;
					if (bkmk_id)
						search_bkmk_ids[bkmk_id] = true;
				}
				top.HEURIST.search.bib_ids = search_bib_ids;
				top.HEURIST.search.bkmk_ids = search_bkmk_ids;
//			}
		}
		else {	// no bib ids sent through
			alert("Problem contacting server");
		}
		top.HEURIST.util.sendCoverallToBack();
	},

	deselectAll: function() {
		var bibs = document.getElementsByName("bib[]");
		for (var i=0; i < bibs.length; ++i) {
			bibs[i].checked = false;
		}
		top.HEURIST.search.bib_ids = {};
		top.HEURIST.search.bkmk_ids = {};
		top.HEURIST.selectedRecordIds = [];

		top.HEURIST.selectedRecordDivs = {};
	},

	get_bib_ids: function() {
		var bib_ids_list = new Array();
		for (var i in top.HEURIST.search.bib_ids)
			if (top.HEURIST.search.bib_ids[i]) bib_ids_list.push(i);
		return bib_ids_list;
	},

	get_bkmk_ids: function() {
		var bkmk_ids_list = new Array();
		for (var i in top.HEURIST.search.bkmk_ids)
			if (top.HEURIST.search.bkmk_ids[i]) bkmk_ids_list.push(i);
		return bkmk_ids_list;
	},

	notificationPopup: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			top.HEURIST.search.selectBookmarkMessage("for notification");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/actions/sendNotificationsPopup.php" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : ""));
	},

	addTagsPopup: function(reload) {
		if (top.HEURIST.parameters["w"] == "all") {
			var bib_ids_list = top.HEURIST.search.get_bib_ids();
			var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
			if (bib_ids_list.length == 0  &&  bkmk_ids_list.length == 0) {
				//nothing selected
				alert("Select at least one record to add tags");
			}
			else if (bib_ids_list.length > bkmk_ids_list.length) {
				// at least one unbookmarked record selected
				top.HEURIST.search.addBookmarks();
			} else {
				top.HEURIST.search.addRemoveTagsPopup(reload);
			}
		} else {
			top.HEURIST.search.addRemoveTagsPopup(reload);
		}
	},
	removeTagsPopup: function(reload) {
		top.HEURIST.search.addRemoveTagsPopup(reload);
	},
	addRemoveTagsPopup: function(reload) {
		var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
		if (bkmk_ids_list.length == 0) {
			top.HEURIST.search.selectBookmarkMessage("to add / remove tags");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/tags/updateTagsSearchPopup.html?show-remove" + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""), { callback: function(add, tags) {
			if (! tags) {
				if (reload) top.location.reload();
				return;
			}

			var action_fr = top.document.getElementById("i_action");
			var action_elt = action_fr.contentWindow.document.getElementById('action');
			var bkmk_ids_elt = action_fr.contentWindow.document.getElementById('bkmk_ids');
			var tagString_elt = action_fr.contentWindow.document.getElementById('tagString');
			var reload_elt = action_fr.contentWindow.document.getElementById('reload');

			if (! action_fr  ||  ! action_elt  ||  ! bkmk_ids_elt  ||  ! tagString_elt  ||  ! reload_elt) {
				alert("Problem contacting server - try again in a moment");
				return;
			}

			action_elt.value = (add ? "add" : "remove") + "_tags";
			tagString_elt.value = tags;
			var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
			bkmk_ids_elt.value = bkmk_ids_list.join(',');
			reload_elt.value = reload ? "1" : "";

			action_elt.form.submit();
		} });
	},
	addRemoveKeywordsPopup: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to add / remove workgroup tags");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/tags/editUsergroupTagsPopup.html" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : ""), { callback: function(add, wgTag_ids) {
			if (! wgTag_ids) return;

			var action_fr = top.document.getElementById("i_action");
			var action_elt = action_fr.contentWindow.document.getElementById('action');
			var bib_ids_elt = action_fr.contentWindow.document.getElementById('bib_ids');
			var wgTag_ids_elt = action_fr.contentWindow.document.getElementById('wgTag_ids');

			if (! action_fr  ||  ! action_elt  ||  ! bib_ids_elt  ||  ! wgTag_ids_elt) {
				alert("Problem contacting server - try again in a moment");
				return;
			}

			action_elt.value = (add ? "add" : "remove") + "_wgTags_by_id";
			wgTag_ids_elt.value = wgTag_ids.join(",");
			bib_ids_elt.value = bib_ids_list.join(',');

			action_elt.form.submit();
		} });
	},

	setRatingsPopup: function() {
		var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
		if (bkmk_ids_list.length == 0) {
			top.HEURIST.search.selectBookmarkMessage("to set ratings");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/actions/setRatingsPopup.php" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : ""));
	},

	setWorkgroupPopup: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to set workgroup ownership and visibility");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/permissions/setRecordOwnership.html" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : ""), {
			callback: function(wg, hidden) {
				if (wg === undefined) return;

				var action_fr = top.document.getElementById("i_action");
				var action_elt = action_fr.contentWindow.document.getElementById('action');
				var bib_ids_elt = action_fr.contentWindow.document.getElementById('bib_ids');
				var wg_elt = action_fr.contentWindow.document.getElementById('wg_id');
				var vis_elt = action_fr.contentWindow.document.getElementById('vis');

				if (! action_fr  ||  ! action_elt  ||  ! bib_ids_elt  ||  ! wg_elt  ||  ! vis_elt) {
					alert("Problem contacting server - try again in a moment");
					return;
				}

				action_elt.value = "set_wg_and_vis";
				bib_ids_elt.value = bib_ids_list.join(',');
				wg_elt.value = wg;
				vis_elt.value = hidden ? "Hidden" : "Viewable";

				action_elt.form.submit();
			}
		});
	},

	addRelationshipsPopup: function() {
		if (top.HEURIST.search.get_bib_ids().length === 0) {
			alert("Select at least one record to add relationships");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/actions/setRelationshipsPopup.html" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : ""));
	},

	selectBookmarkMessage: function(operation) {
		alert("Select at least one bookmark " + operation
			+ (top.HEURIST.parameters["w"]=="all"
				? "\n(operation can only be carried out on bookmarked records, shown by a yellow star )"
				: ""));
	},

	addBookmarks: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to bookmark");
			return;
		}

		var action_fr = document.getElementById("i_action");
		var bib_ids_elt = action_fr.contentWindow.document.getElementById("bib_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bib_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = top.HEURIST.basePath+ "search/actions/actionHandler.php" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "");
			return;
		}

		bib_ids_elt.value = bib_ids_list.join(",");
		action_elt.value = "bookmark_reference";
		action_elt.form.submit();
	},

	deleteBookmarks: function() {
		var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
		if (bkmk_ids_list.length == 0) {
			alert("Select at least one bookmark to delete");
			return;
		} else if (bkmk_ids_list.length == 1) {
			if (! confirm("Do you want to delete one bookmark?\n(this ONLY removes the bookmark from your resources,\nit does not delete the bibliographic entry)")) return;
		} else {
			if (! confirm("Do you want to delete " + bkmk_ids_list.length + " bookmarks?\n(this ONLY removes the bookmarks from your resources,\nit does not delete the bibliographic entries)")) return;
		}

		var action_fr = document.getElementById("i_action");
		var bkmk_ids_elt = action_fr.contentWindow.document.getElementById("bkmk_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bkmk_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = "actions/actionHandler.php" + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "");
			return;
		}

		bkmk_ids_elt.value = bkmk_ids_list.join(",");
		action_elt.value = "delete_bookmark";
		action_elt.form.submit();
	},

	deleteBiblios: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to delete");
			return;
		}
		top.HEURIST.util.popupURL(window, "actions/deleteRecordsPopup.php?ids="+bib_ids_list.join(",")+(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));
	},

	renderCollectionUI: function() {
		if (typeof top.HEURIST.search.collectCount == "undefined") {
			top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php?fetch=1", function (results){
				top.HEURIST.search.collectCount = results.count;
				top.HEURIST.search.collection = results.ids;
				top.HEURIST.search.renderCollectionUI();
			});
			return;
		}
		if (top.HEURIST.search.collectCount > 0) {
			if (window.HEURIST.parameters["q"]  &&  window.HEURIST.parameters["q"].match(/_COLLECTED_/)) {
				$('.not-query-collected').removeClass('not-query-collected').addClass('query-collected');
			} else {
				$('.query-collected').removeClass('query-collected').addClass('not-query-collected');
			}
		}
		document.getElementById("collection-label").innerHTML = "Collected: " + top.HEURIST.search.collectCount;
// error collection-info
		document.getElementById("collection-info").innerHTML = "<a href='?q=_COLLECTED_&amp;label=Collected'>Collected: " + top.HEURIST.search.collectCount + "</a>";
	},

	addRemoveCollectionCB: function(results) {
		var refresh = false;
		if (typeof results.count != "undefined") {
			if (top.HEURIST.search.collectCount != results.count) {
				refresh = true;
			}
			top.HEURIST.search.collectCount = results.count;
		}
		top.HEURIST.search.collection = results.ids;
		top.HEURIST.search.renderCollectionUI();
		if (window.HEURIST.parameters["q"].match(/_COLLECTED_/) && refresh) {
			top.location.reload();
		}
		top.HEURIST.search.collChangeTimer = 0;
	},

	// set a timer to highlite that the collection has changed  for 10 sec or until the count is updated on the screen
	showCollectionChange: function(firstTime) {
		if (firstTime) {
			top.HEURIST.search.collChangeTimer = 8;
			setTimeout("top.HEURIST.search.showCollectionChange(false)",2000);
			document.getElementById("collection-label").className = "show-changed";
			return;
		}
		if (top.HEURIST.search.collChangeTimer > 0){
			top.HEURIST.search.collChangeTimer--;
			setTimeout("top.HEURIST.search.showCollectionChange(false)",1000);
			return;
		}
		document.getElementById("collection-label").className = "";
	},

	addToCollection: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to bookmark");
			return;
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&add=" + bib_ids_list.join(","));
		top.HEURIST.search.deselectAll();
		document.getElementById("collection-label").className += "show-changed";
		top.HEURIST.search.showCollectionChange(true);
	},

	removeFromCollection: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to remove from collection basket");
			return;
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&remove=" + bib_ids_list.join(","));
	},

	showTagSearchLinks: function() {
		var elt = document.getElementById("tag-search-links");
		var link = document.getElementById("saved");
		var pos = top.HEURIST.getPosition(link);
		top.HEURIST.util.popupTinyElement(window, elt, { x: pos.x, y: pos.y, width: link.offsetWidth, height: 300 });
	},

	passwordPopup: function(elt) {
		var pos = top.HEURIST.getPosition(elt);
		var scroll = document.getElementById("results").scrollTop;
		var pwd = document.getElementById("password");
		document.getElementById("reminder").innerHTML = elt.getAttribute("user_pwd");
		top.HEURIST.util.popupTinyElement(window, pwd, { x: pos.x + elt.offsetWidth, y: pos.y - scroll, width: 200, height: 50 });
	},

	launchWebSearch: function() {
		var q = document.getElementById("q").value;
		// look up rectypes
		var re = new RegExp("(t|type):([0-9]+)");
		var matches;
		while (matches = q.match(re)) {
			var type = top.HEURIST.rectypes.names[matches[2]];
			q = q.replace(new RegExp("(t|type):"+matches[2], "g"), "\""+type+"\"");
		}
		// look up workgroups
		var re = new RegExp("(wg|workgroup):([0-9]+)");
		while (matches = q.match(re)) {
			var wg = top.HEURIST.workgroups[matches[2]].name;
			q = q.replace(new RegExp("(wg|workgroup):"+matches[2], "g"), "\""+wg+"\"");
		}
		// remove sortbys, and all other predicate names
		q = q.replace(new RegExp("sortby:[^\\s]*|[^\\s\"]*:", "g"), "");
		// split workgroup tags
		q = q.replace(new RegExp("(\"[^\"]*)\\\\([^\"]*\")", "g"), "$1\" \"$2");
		q = q.replace(new RegExp("\\\\", "g"), " ");
		// OR and AND have to be uppercase for google
		q = q.replace(new RegExp("\\bor\\b", "i"), "OR");
		q = q.replace(new RegExp("\\band\\b", "i"), "AND");
		window.open("http://www.google.com/search?q="+q);
	},

	searchAll: function() {
		var w = document.getElementById("w-input");
		w.value = "";
		w.form.submit();
	},

	setContactLink: function() {
		document.getElementById("contact-link").href += "?subject=HEURIST%20v" + top.HEURIST.VERSION +
														"%20user:" + top.HEURIST.get_user_username() +
														(window.HEURIST.parameters["q"]
															? "%20q:" + window.HEURIST.parameters["q"]
															: "") + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
	},

	setHomeLink: function() {
		 document.getElementById("home-link").href = top.HEURIST.basePath + (top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "");
	},

	writeRSSLinks: function() {

		var link = document.createElement("link");
		link.rel = "alternate";
		link.type = "application/rss+xml";
		link.title = "RSS feed";
		link.href = "feed://"+window.location.host+top.HEURIST.basePath+"export/feeds/searchRSS.php"+(document.location.search ? document.location.search : "?q=tag:Favourites") + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		document.getElementsByTagName("head")[0].appendChild(link);
		document.getElementById("httprsslink").href += (document.location.search ? document.location.search : "?q=tag:Favourites" + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));
	},

	toggleLegend: function() {
		var legend_box_elt = document.getElementById("legend-box");
		if (legend_box_elt) {
			legend_box_elt.parentNode.removeChild(legend_box_elt);
			return;
		}

		var visible_rectypes = {};
		var results = top.HEURIST.search.results;

		if (! results) return;

		var currentPage = top.HEURIST.search.currentPage;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		var firstOnPage = currentPage * resultsPerPage;
		var lastOnPage = Math.min((currentPage+1)*resultsPerPage, results.totalRecordCount)-1;
		for (var i = firstOnPage; i <= lastOnPage; ++i) {
			visible_rectypes[results.records[i][4]] = true;
		}

		var legend_box_elt = document.createElement("div");
		legend_box_elt.id = "legend-box";

		var iHTML = "<div id=legend-title><img onclick=\"top.HEURIST.search.toggleLegend();\" src='"+top.HEURIST.basePath+"common/images/x2.gif'>Legend</div><br>";
		iHTML += "<ul id=legend-box-list>";
		iHTML += "</ul>";
		legend_box_elt.innerHTML = iHTML;

		var results_elt = document.getElementById("results");
		var results_pos = top.HEURIST.getPosition(results_elt);
		var results_width = results_elt.offsetWidth;

		legend_box_elt.style.left = (results_pos.x + results_width - 193) + "px";
		legend_box_elt.style.top = (results_pos.y + 3) + "px";
		document.body.appendChild(legend_box_elt);

		var iHTML = "";
		for (var i in visible_rectypes) {
			if (top.HEURIST.rectypes.names[i])
				iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/rectype-icons/"+i+".png'>"+top.HEURIST.rectypes.names[i]+"</li>";
		}

		document.getElementById("legend-box-list").innerHTML = iHTML;

		iHTML = "<hr><ul>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow.gif'><span>Resource is in </span><span style=\"font-weight: bold;\">my records</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-green-7.gif'><span>Belongs to workgroup 7<br></span><span style=\"margin-left: 25px;\">- read-only to others</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-red-3.gif'><span>Belongs to workgroup 3<br></span><span style=\"margin-left: 25px;\">- hidden to others</span></li>";
		iHTML += "</ul>";
		iHTML += "<hr><div><a href=# onclick=\"top.HEURIST.util.popupURL(window, '"+ top.HEURIST.basePath+"common/php/showRectypeLegend.php"+(top.HEURIST.instance && top.HEURIST.instance.name ? "?instance=" + top.HEURIST.instance.name : "")+"' ); return false;\">show full legend</a></div>";
		legend_box_elt.innerHTML += iHTML;
	},

	toggleHelp: function() {
		top.HEURIST.util.setDisplayPreference("help", top.HEURIST.util.getDisplayPreference("help") == "hide" ? "show" : "hide");
	},

	fixDuplicates: function() {
		var bib_ids = top.HEURIST.search.get_bib_ids();
		if (bib_ids.length === 0) {
			alert("Select at least one record to fix duplicates");
		} else {
			window.location.href = top.HEURIST.basePath+"admin/verification/combineDuplicateRecords.php?bib_ids=" + bib_ids.join(",") + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
		}
	},

	collectDuplicates: function() {
		var p = window.HEURIST.parameters;
		var args = [];
		if (p['ver']) args.push('ver='+p['ver']);
		if (p['w']) args.push('w='+p['w']);
		if (p['stype']) args.push('stype='+p['stype']);
		if (p['q']) args.push('q='+escape(p['q']));
		var query_string = args.join('&');
		window.location.href = top.HEURIST.basePath+"admin/verification/collectDuplicateRecords.php?"+ query_string + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
	},

	setupSearchPage: function() {
		top.HEURIST.registerEvent(window, "contentloaded", top.HEURIST.search.renderSearchPage);
		top.HEURIST.registerEvent(window, "resize", top.HEURIST.search.trimAllLinkTexts);
		top.HEURIST.registerEvent(window, "resize", function() {
			if (document.getElementById("legend-box")) {
				top.HEURIST.search.toggleLegend();
				top.HEURIST.search.toggleLegend();
			}
		});
		top.HEURIST.registerEvent(window, "load", function() {
			document.getElementById("q").focus();
			document.getElementById("q").select();
		});
		top.HEURIST.search.loadSearchParameters();
	},

	buildPublishLinks: function() {
		var p = window.HEURIST.parameters;
		var args = [];
		if (p['ver']) args.push('ver='+p['ver']);
		if (p['w']) args.push('w='+p['w']);
		if (p['stype']) args.push('stype='+p['stype']);
		if (p['q']) args.push('q='+escape(p['q']));
		var query_string = args.join('&');

//		if (top.HEURIST.instance.name === "") {
			var im_container = document.getElementById("publish-image-placeholder");
			var a = document.createElement("a");
				a.className = "toolbar-large-icon-link logged-in-only";
				a.title = "Publish the current search results as formatted output which can be printed, saved or generated (live) in a web page";
				a.href = "#";
			a.onclick = function() {
				if (window.HEURIST.parameters["label"] && window.HEURIST.parameters["sid"]) {
					window.open(top.HEURIST.basePath+ "viewers/publish/publisher.php?pub_id=" + window.HEURIST.parameters["sid"] + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));
				} else {
					top.HEURIST.util.popupURL(window, top.HEURIST.basePath + 'search/saved/saveSearchPopup.html?publish=yes' + (top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : ""));
				}
				return false;
			}
			icon = document.createElement("img");
			icon.src = top.HEURIST.basePath + "common/images/publish.png"
			a.appendChild(icon);

			//a.appendChild(document.createTextNode("publish"));
			im_container.appendChild(a);
			//im_container.appendChild(document.createTextNode(" | "));
//		}

	},

	showPublishPopup: function() {
		var div, a, p, popup, param = window.HEURIST.parameters;
		if (param['pub']  &&  param['sid']) {
			setTimeout('window.open("'+top.HEURIST.basePath+'viewers/publish/publisher.php?pub_id=' + param["sid"]+'");',0);
			return;
			div = document.createElement('div');
			div.style.padding = '0 10px';
			p = div.appendChild(document.createElement('p'));
			p.innerHTML = 'The current search has been saved.';
			p = div.appendChild(document.createElement('p'));
			p.innerHTML = 'Click below to continue to the publishing wizard.';
			p = div.appendChild(document.createElement('p'));
			a = p.appendChild(document.createElement('a'));
			a.href = top.HEURIST.basePath+ 'viewers/publish/publisher.php?pub_id=' + param['sid'] +(top.HEURIST.instance && top.HEURIST.instance.name ? "&instance=" + top.HEURIST.instance.name : "");
			a.target = '_blank';
			a.onclick = function() {
				top.HEURIST.util.closePopup(popup.id);
				return true;
			}
			a.innerHTML = "Continue to publishing wizard";
			popup = top.HEURIST.util.popupElement(top, div, { width: '350', height: '150' });
		}
	},

	buildMenu: function() {
		YAHOO.util.Event.onDOMReady(function() {
			top.HEURIST.search.myHeuristMenu = new YAHOO.widget.Menu("my-heurist-menu", {
				context: ["my-heurist-menu-link", "tl", "bl", ["beforeShow", "windowResize"]]
			});
			top.HEURIST.search.myHeuristMenu.render();
			document.getElementById("my-heurist-menu").style.display = "block";
			$("#my-heurist-menu-link a").bind("click mouseover", function() {
				top.HEURIST.search.myHeuristMenu.show(); return false;
			});
		});
	},

	popupNotice: function(content, reload) {
		setTimeout(function() {
			document.getElementById("popup-notice-content").innerHTML = content;
			document.getElementById("popup-notice").style.display = "block";
		}, 0);
		setTimeout(function() {
			if (reload) {
				top.location.reload();
			} else {
				document.getElementById("popup-notice").style.display = "none";
			}
		}, 2000);
	},

	printResultRow: function() {
		var content = document.getElementById("result-rows");
		var pri = document.getElementById("printingFrame").contentWindow;
		var GlobalcssLink = document.createElement("link")
		GlobalcssLink.href = top.HEURIST.basePath+"common/css/global.css";
		GlobalcssLink .rel = "stylesheet";
		GlobalcssLink .type = "text/css";
		var SearchcssLink = document.createElement("link")
		SearchcssLink.href = top.HEURIST.basePath+"common/css/search.css";
		SearchcssLink .rel = "stylesheet";
		SearchcssLink .type = "text/css";
		var PrintcssLink = document.createElement("link")
		PrintcssLink.href = top.HEURIST.basePath+"common/css/print.css";
		PrintcssLink .rel = "stylesheet";
		PrintcssLink .type = "text/css";
		PrintcssLink.media = "print";



		pri.document.open();
		pri.document.write(content.innerHTML);
		pri.document.body.appendChild(GlobalcssLink);
		pri.document.body.appendChild(SearchcssLink);
		pri.document.body.appendChild(PrintcssLink);
		pri.document.close();
		pri.focus();
		pri.print();
	},

	renderSearchPage: function() {
		top.HEURIST.search.fillInSavedSearches();
		top.HEURIST.search.fillInKeywordSearches();
		top.HEURIST.search.displaySearchParameters();
		top.HEURIST.search.buildMenu();
		top.HEURIST.search.renderLoginDependentContent();
		top.HEURIST.search.renderCollectionUI();
		top.HEURIST.search.setContactLink();
		top.HEURIST.search.setHomeLink();
		top.HEURIST.search.writeRSSLinks();
		top.HEURIST.search.buildPublishLinks();
		top.HEURIST.search.showPublishPopup();

		var autoPopups = document.getElementsByName("auto-popup");
		for (var i=0; i < autoPopups.length; ++i) {
			autoPopups[i].onclick = top.HEURIST.search.autoPopupLink;
		}
		var searchForm = document.forms[0];
		var inputInstance = document.createElement("input");
		inputInstance.type = "hidden";
		inputInstance.id = "instance";
		inputInstance.name = "instance";
		inputInstance.value = top.HEURIST.instance.name;
		searchForm.appendChild(inputInstance);

		document.getElementById("select-all-checkbox").checked = false;
	}
};

top.HEURIST.fireEvent(window, "heurist-search-js-loaded");


