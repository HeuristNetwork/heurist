/* heurist-search.js
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
		}
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
			iframeElt.src = "json-search.php?" +
			("w=" + encodeURIComponent(window.HEURIST.parameters["w"])) + "&" +
			("stype=" + (window.HEURIST.parameters["stype"] ? encodeURIComponent(window.HEURIST.parameters["stype"]) : "")) + "&" +
			("ver=" + top.HEURIST.search.VERSION) + "&" +
			("q=" + encodeURIComponent(window.HEURIST.parameters["q"]));
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
		/* bkmk_id, bkmk_user_id, bib_id, bib_url, bib_reftype, bib_title, bib_workgroup */
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
			// special handling for notes reftype: link to view page if no URL
			href = top.HEURIST.basePath+ "data/records/viewrec/view.php?bib_id="+res[2];
		}

		var userPwd;
		if (res[10]) userPwd = "style='display:inline;cursor:pointer;' user_pwd='"+res[10].htmlEscape()+"'";
		else userPwd = "style='display:none;'";

		var reftypeImg = "style='background-image:url("+ top.HEURIST.basePath+"common/images/reftype-icons/" + (res[4]? res[4] : "blank") + ".gif)'";
		var reftypeTitle = "Click to see details";
		if (top.HEURIST.reftypes.names[parseInt(res[4])])
			reftypeTitle = top.HEURIST.reftypes.names[parseInt(res[4])] + " - click to see details";

		var html = "<div class=result_row title='Double-click to edit' bkmk_id='"+res[0]+"' bib_id="+res[2]+">"+
"<span class=sp title='Click to see details'></span>"+
"<input type=checkbox name=bib[] onclick=top.HEURIST.search.cb_onclick(this) class='logged-in-only' title='Check box to apply Actions to this record'>"+
"<img src=" +top.HEURIST.basePath+ "common/images/13x13.gif " + pinAttribs + ">"+
"<span class='wg-id-container logged-in-only'>"+
"<span class=wg-id title='"+linkTitle+"' " + (wgColor? wgColor: "") + ">" + (wgHTML? wgHTML.htmlEscape() : "") + "</span>"+
"</span>"+
"<img src=" +top.HEURIST.basePath+ "common/images/16x16.gif title='"+reftypeTitle.htmlEscape()+"' "+reftypeImg+" class=rft>"+
"<a expando=true" + (href? " href='"+href+"'" : "") +
    ((linkTitle || href)  ?  " title='" + (linkTitle? linkTitle.htmlEscape() : href) + "'"  :  "") +
    " origInnerHtml='"+linkText.htmlEscape()+"'>" + linkText + "</a>"+
"<span class=daysbad title='Detection of broken URLs is carried out once a day'>"+daysBad+"</span>"+
"<img onclick=top.HEURIST.search.passwordPopup(this) title='Click to see password reminder' src=" +top.HEURIST.basePath+ "common/images/key.gif " + userPwd + ">"+
"</div>";

		return html;
	},

	renderResultThumb: function(res) {

		/* res[0]   res[1]        res[2]  res[3]   res[4]       res[5]     res[6]        */
		/* bkmk_id, bkmk_user_id, bib_id, bib_url, bib_reftype, bib_title, bib_workgroup */

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

		if (href) {
			if (! href.match(/^[^\/\\]*:/))
				href = "http://" + href;
			href = href.htmlEscape();
		}
		else if (res[4] == 2) {
			// special handling for notes reftype: link to view page if no URL
			href = top.HEURIST.basePath+ "data/records/viewrec/view.php?bib_id="+res[2];
		}

		var userPwd;
		if (res[10]) userPwd = "style='display:inline;cursor:pointer;' user_pwd='"+res[10].htmlEscape()+"'";
		else userPwd = "style='display:none;'";

		var reftypeImg = "style='background-image:url("+ top.HEURIST.basePath+"common/images/reftype-icons/" + (res[4]? res[4] : "blank") + ".gif)'";
		var reftypeThumb = "style='background-image:url("+ top.HEURIST.basePath+"common/images/reftype-icons/thumb/th_" + (res[4]? res[4] : "blank") + ".png)'";
		var reftypeTitle = "Click to see details";
		if (top.HEURIST.reftypes.names[parseInt(res[4])])
			reftypeTitle = top.HEURIST.reftypes.names[parseInt(res[4])] + " - click to see details";

		var html = "<div title='Double-click to edit'  class=result_thumb  bkmk_id='"+res[0]+"' bib_id="+res[2]+">" +
		       "<div class=thumbnail>" +
			   (res[11] && res[11].length && res[3].length ? "<a href='"+res[3]+"'>" : "") +
		       (res[11] && res[11].length ? "<img bkmk_id='"+res[0]+"' bib_id="+res[2]+" src='"+res[11]+"'>"
			                   : "<div class='no-thumbnail' "+reftypeThumb+" ></div>") +
			   (res[11] && res[11].length && res[3].length ? "</a>" : "") +
		       "</div>" +
			   (res[3].length ? "<div class=rec_title title='"+linkText.htmlEscape()+"'><a href='"+res[3]+"'>"+ linkText + "</a></div>" : "<div class=rec_title title='"+linkText.htmlEscape()+"'>"+ linkText + "</div>") +
		       "<div class=icons  bkmk_id='"+res[0]+"' bib_id="+res[2]+">" +
			   "<input type=checkbox name=bib[] onclick=top.HEURIST.search.cb_onclick(this) class='logged-in-only' title='Check box to apply Actions to this record'>"+
		       "<img src='"+ top.HEURIST.basePath+"common/images/16x16.gif' title='"+reftypeTitle.htmlEscape()+"' "+reftypeImg+" class=rft>"+
		       "<img src='"+ top.HEURIST.basePath+"common/images/13x13.gif' " + pinAttribs + ">"+
		       "<span class='wg-id-container logged-in-only'>"+
		       "<span class=wg-id title='"+linkTitle.htmlEscape()+"' " + (wgColor? wgColor: "") + ">" + (wgHTML? wgHTML.htmlEscape() : "") + "</span>"+
		       "</span>"+
               "<DIV id='toolbox'>" +
			   "<span id='rec_edit_link' title='Click to edit'>edit</span>" +
			   "<span id='rec_view_link' title='Click to see details'>view</span>" +
			   "<span id='rec_explore_link' title='Click to see details'>explore</span>" +
			   "<img onclick=top.HEURIST.search.passwordPopup(this) title='Click to see password reminder' src='"+ top.HEURIST.basePath+"common/images/key.gif' " + userPwd + ">"+
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
				document.getElementById("results-message").innerHTML = "Click on the Heurist logo at the top left of any screen for quick return to your Favourites";
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
			logged_in_elt.innerHTML = top.HEURIST.get_user_name() + " : <a href=" +top.HEURIST.basePath+ "common/connect/login.php?logout=1>log&nbsp;out</a>";
		} else {
			logged_in_elt.innerHTML = "not logged in : <a href=" +top.HEURIST.basePath+ "common/connect/login.php>log in</a>";
			left_panel_elt.innerHTML =
				"<div style=\"padding: 10px;\">\n" +
				" Existing users:\n" +
				" <div id=login-button><a href=" +top.HEURIST.basePath+ "common/connect/login.php title=\"Log in to use Heurist - new users please register first\"><img src=/common/images/111x30.gif></a></div>\n" +
				" <p style=\"text-align: center;\"><a onclick=\"InstallTrigger.install({'Heurist Toolbar':this.href}); return false;\" href=" +top.HEURIST.basePath+ "tools/toolbar/HeuristToolbar.xpi title=\"Get Firefox toolbar - rapid access to bookmark web pages, import hyperlinks, view and edit data for the current web page, and synchronise Heurist with Zotero\">Get toolbar</a><br>(Firefox)</p>\n" +
				" <p style=\"text-align: center;\"><a href=\"javascript:" + top.HEURIST.bookmarkletCode + "\" onclick=\"alert('Drag the Heurist Bookmarklet link to your browser bookmarks toolbar, or right-click the link, choose Bookmark This Link, and add the link to your Bookmarks Toolbar or Favorites.');return false;\" title=\"Get Bookmarklet - bookmarks web pages and harvests web links \(we recommend Firefox and the Firefox toolbar - it has more features\)\">Heurist Bookmarklet</a><br>(other browsers)</p>" +
				" New users:\n" +
				" <div id=tour-button><a href=" +top.HEURIST.basePath+ "help/tour.html title=\"Take a quick tour of Heurist's major features\" target=\"_blank\"><img src=/common/images/111x30.gif></a></div>\n" +
				" <div id=register-button><a href=" +top.HEURIST.basePath+ "admin/users/add.php?register=1 target=\"_blank\" title=\"Register to use Heurist - takes only a couple of minutes\"><img src=/common/images/111x30.gif></a></div>\n" +
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
			var reftype_img = result.childNodes[4];
			var pin_img = result.childNodes[2];

			top.HEURIST.registerEvent(t_span, "click", top.HEURIST.search.open_out);
			top.HEURIST.registerEvent(reftype_img, "click", top.HEURIST.search.open_out);
			top.HEURIST.registerEvent(pin_img, "click", result.getAttribute("bkmk_id") ? top.HEURIST.search.open_out : top.HEURIST.search.addBookmark);
			top.HEURIST.registerEvent(result, "dblclick", (top.HEURIST.util.getDisplayPreference("double-click-action") == "edit") ? top.HEURIST.search.edit : top.HEURIST.search.open_out);
		}

		var thumbs = $(".result_thumb", resultsDiv).get();
		for (var i=0; i < thumbs.length; ++i) {
			var result = thumbs[i];
			var pin_img = $(".unbookmarked", result)[0];
			var thumb_img = $(".thumbnail img", result)[0];
			var rec_edit_link = $("#rec_edit_link", result)[0];
			var rec_view_link = $("#rec_view_link", result)[0];
			var rec_quickview_link = $("#rec_quickview_link", result)[0];
			var rec_explore_link = $("#rec_explore_link", result)[0];

			if (pin_img) {
				top.HEURIST.registerEvent(pin_img, "click", result.getAttribute("bkmk_id") ? function(){} : top.HEURIST.search.addBookmark);
			}
			top.HEURIST.registerEvent(result, "dblclick", (top.HEURIST.util.getDisplayPreference("double-click-action") == "edit") ? top.HEURIST.search.edit : top.HEURIST.search.open_out);
			//top.HEURIST.registerEvent(result, "click", top.HEURIST.search.openRecordDetails);
			if (rec_edit_link) {
					top.HEURIST.registerEvent(rec_edit_link, "click", top.HEURIST.search.edit);
			}
			if (rec_view_link) {
					top.HEURIST.registerEvent(rec_view_link, "click", top.HEURIST.search.openRecordDetails);
			}
			if (rec_quickview_link) {
				top.HEURIST.registerEvent(rec_quickview_link, "click", top.HEURIST.search.quickview);
			}
			if (rec_explore_link) {
					top.HEURIST.registerEvent(rec_explore_link, "click", top.HEURIST.search.explore);
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
			top.HEURIST.search.trimLinkText(self_link, offset_width - (days_bad.offsetWidth ? days_bad.offsetWidth : 0));
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

		// Check if we've already loaded the given page ...
		var firstOnPage = pageNumber*resultsPerPage;
		var lastOnPage = Math.min((pageNumber+1)*resultsPerPage, results.totalRecordCount)-1;
		if (results.records[firstOnPage]  &&  results.records[lastOnPage]) {
			// Hoorah ... all the data is loaded (well, the first and last entries on the page ...), so render it
			document.getElementById("results").scrollTop = 0;
			top.HEURIST.search.renderSearchResults(firstOnPage, lastOnPage);
		} else {
			// Have to wait for the data to load
			document.getElementById("result-rows").innerHTML = "";
			document.getElementById("search-status").className = "loading";
		}

		top.HEURIST.search.renderNavigation();
		top.HEURIST.search.deselectAll();
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

		window.open(top.HEURIST.basePath+ "data/records/editrec/heurist-edit.html?sid=" + top.HEURIST.search.sid + "&bib_id="+bib_id);

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

		window.open( "/cocoon/h3/explore/main/item/" + bib_id);
//		window.open(top.HEURIST.instance.exploreURL+ "" + bib_id);

		return false;
	},

	quickview: function(e, targ) {
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

		var options = {};
		options["title"] = "Preview of record id:" + bib_id;
		options["close-on-blur"] = true;
		options["no-resize"] = true;
		options["width"] = 480;
		options["height"] = 300;
		options["no-close"] = true;
		options["no-titlebar"] = true;

		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "data/records/viewrec/quickview.php?bib_id=" + bib_id, options);

		return false;
	},

	openRecordDetails: function(e, targ) {

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
		else if (targ.parentNode.parentNode  &&  targ.parentNode.parentNode.getAttribute("bib_id")) {
			var result_div = targ.parentNode.parentNode;
		}
		else return;

		var bib_id = result_div.getAttribute("bib_id");

		window.open(top.HEURIST.basePath+ "data/records/viewrec/view.php?bib_id=" + bib_id +"&noclutter=1");

		return false;
	},

	open_out: function(e, targ) {
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
		else return;

		var bib_id = result_div.getAttribute("bib_id");

		var infos = top.HEURIST.search.infos;
		if (infos["bib:" + bib_id]) {
			// bib info is already displaying -- hide it
			var info = infos["bib:" + bib_id];
			result_div.className = result_div.className.replace(" expanded", "");
			info.parentNode.removeChild(info);
			delete infos["bib:" + bib_id];
			return;
		}

		var info_div = document.createElement("iframe");
		info_div.className = "info";
		info_div.frameBorder = 0;
		info_div.style.height = "0px";
		info_div.src = top.HEURIST.basePath+ "data/records/viewrec/info.php?bib_id="+bib_id;
		infos["bib:" + bib_id] = info_div;
		result_div.className += " expanded";

		result_div.appendChild(info_div);

		return false;
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
		for (var i in top.HEURIST.search.infos) {
			var info = top.HEURIST.search.infos[i];
			info.parentNode.className = info.parentNode.className.replace(" expanded", "");
			info.parentNode.removeChild(info);
			delete top.HEURIST.search.infos[i];
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
			action_fr.src = top.HEURIST.basePath+ "search/action.php";
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

				innerHTML += "<img class=\"saved-search-edit\" title=\"rename\" src=\"" +top.HEURIST.basePath+ "common/images/edit_pencil_9x11.gif\" align=absmiddle onclick=\"top.HEURIST.util.popupURL(window, '" +top.HEURIST.basePath+ "search/saved/save-search.html?mode=rename&slabel=" + encodeURIComponent(cmb[0]) + "&sid="+sid+"&wg="+ (wg ? wg : 0)+"');\">";
				innerHTML += "<img class=\"saved-search-edit\" title=\"delete\" src=\"" +top.HEURIST.basePath+ "common/images/delete6x7.gif\" align=absmiddle onclick=\"top.HEURIST.search.deleteSearch('"+ cmb[0] +"',"+ (wg ? wg : 0) +");\">";
			}
			innerHTML += "<div><a id='ss" + sid + "' href='" + (wg ? top.HEURIST.basePath : "")
					  + cmb[1] + "&amp;label=" + encodeURIComponent(cmb[0]) + "&amp;sid=" + sid + "'>" + cmb[0] + "</a></div>";


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

			document.getElementById("my-blog-link").href = top.HEURIST.basePath+ "interfaces/blog/index.html?u=" + top.HEURIST.get_user_id();


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
				innerHTML += "<div class=saved-search-subsubheading><a href='" +top.HEURIST.basePath+ "search/workgroup/workgroup.html?wg=" + w + "'>Workgroup page</a></div>";
				innerHTML += "<div class=saved-search-subsubheading><a target=\"_blank\" href='" +top.HEURIST.basePath+ "interfaces/blog/index.html?g=" + w + "'>"+top.HEURIST.workgroups[w].name+" Blog</a></div>";

				var tags = [];
				for (var j = 0; j < top.HEURIST.user.workgroupKeywordOrder.length; ++j) {
					var tag = top.HEURIST.user.workgroupKeywords[top.HEURIST.user.workgroupKeywordOrder[j]];
					if (tag[0] == w) tags.push(tag[1]);
				}

				if (tags.length) {
					innerHTML += "<div class=saved-search-subsubheading>Workgroup Tags</div>";
					for (var j = 0; j < tags.length; ++j) {
						innerHTML += "<nobr><a href='"+top.HEURIST.basePath+"search/heurist-search.html?ver=1&w=all&q=tag=\"" + top.HEURIST.workgroups[w].name + "\\" + tags[j] + "\"&label=Tag+\"" + tags[j] + "\"'>" + tags[j] + "</a></nobr>";
					}
				}

				var searches = top.HEURIST.user.workgroupSavedSearches[w];
				if (searches  &&  searches.length) {
					innerHTML += "<div class=saved-search-subsubheading>Saved searches (shared)</div>";
					for (var j = 0; j < searches.length; ++j) {
						innerHTML += printSavedSearch(searches[j], w);
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

		var keywords = top.HEURIST.user.keywords;
		var innerHTML = "";
		var kwd;
		for (var i = 0; i < keywords.length; ++i) {
			kwd = encodeURIComponent(keywords[i]);
			innerHTML += "<a href='"+top.HEURIST.basePath+"search/heurist-search.html?ver=1&w=bookmark&q=tag=\"" + kwd + "\"&label=Tag+\"" + kwd + "\"'>" + keywords[i] + "</a> ";
		}
		var kwd_search_elt = top.document.getElementById("keyword-search-links");
		if (kwd_search_elt) {
			kwd_search_elt.innerHTML = innerHTML;
		}
	},

	insertSavedSearch: function(label, url, wg, ss_id) {
		var w_all = url.match(/w=bookmark/) ? 0 : 1;
		var savedSearches = wg ? top.HEURIST.user.workgroupSavedSearches[wg] : top.HEURIST.user.savedSearches;
		var newEntry = wg ? [label, url, ss_id] : [label, url, ss_id, 0, w_all];
		var i = 0;
		while (i < savedSearches.length) {
			var sid = savedSearches[i][2];
			//if this is a renamed search or ovewritten search
			if (sid == ss_id) {
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
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+ "search/saved/delete-saved-search.php?wg="+wg+"&label="+escape(name), function(response) {
			if (response.deleted) {
				if (top.HEURIST.search) {
					top.HEURIST.search.removeSavedSearch(name, wg);
				}
			}
		});
	},

	launchAdvancedSearch: function() {
		var q = document.getElementById("q").value;
		var url = top.HEURIST.basePath+ "search/advanced/popup_advanced.php?" + encodeURIComponent(q);
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
		top.HEURIST.util.sendRequest(top.HEURIST.basePath+ "search/fetch-bib-ids.php" + search, function(xhr) {
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
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/popup_notification.php");
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
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "data/tags/tags.html?show-remove", { callback: function(add, tags) {
			if (! tags) {
				if (reload) top.location.reload();
				return;
			}

			var action_fr = top.document.getElementById("i_action");
			var action_elt = action_fr.contentWindow.document.getElementById('action');
			var bkmk_ids_elt = action_fr.contentWindow.document.getElementById('bkmk_ids');
			var keywordstring_elt = action_fr.contentWindow.document.getElementById('keywordstring');
			var reload_elt = action_fr.contentWindow.document.getElementById('reload');

			if (! action_fr  ||  ! action_elt  ||  ! bkmk_ids_elt  ||  ! keywordstring_elt  ||  ! reload_elt) {
				alert("Problem contacting server - try again in a moment");
				return;
			}

			action_elt.value = (add ? "add" : "remove") + "_keywords";
			keywordstring_elt.value = tags;
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
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "data/tags/keywords.html", { callback: function(add, kwd_ids) {
			if (! kwd_ids) return;

			var action_fr = top.document.getElementById("i_action");
			var action_elt = action_fr.contentWindow.document.getElementById('action');
			var bib_ids_elt = action_fr.contentWindow.document.getElementById('bib_ids');
			var kwd_ids_elt = action_fr.contentWindow.document.getElementById('kwd_ids');

			if (! action_fr  ||  ! action_elt  ||  ! bib_ids_elt  ||  ! kwd_ids_elt) {
				alert("Problem contacting server - try again in a moment");
				return;
			}

			action_elt.value = (add ? "add" : "remove") + "_keywords_by_id";
			kwd_ids_elt.value = kwd_ids.join(",");
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
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/popup_set_ratings.php");
	},

	setWorkgroupPopup: function() {
		var bib_ids_list = top.HEURIST.search.get_bib_ids();
		if (bib_ids_list.length == 0) {
			alert("Select at least one record to set workgroup ownership and visibility");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/workgroup/set-workgroup.html", {
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
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/add-relationships.html");
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
			action_fr.src = top.HEURIST.basePath+ "search/action.php";
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
			action_fr.src = "action.php";
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
		top.HEURIST.util.popupURL(window, "delete_bib.php?ids="+bib_ids_list.join(","));
	},

	renderCollectionUI: function() {
		if (typeof top.HEURIST.search.collectCount == "undefined") {
			top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/collection.php?fetch=1", function (results){
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
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/collection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&add=" + bib_ids_list.join(","));
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
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/collection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&remove=" + bib_ids_list.join(","));
	},

	showKeywordSearchLinks: function() {
		var elt = document.getElementById("keyword-search-links");
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
		// look up reftypes
		var re = new RegExp("(t|type):([0-9]+)");
		var matches;
		while (matches = q.match(re)) {
			var type = top.HEURIST.reftypes.names[matches[2]];
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
		// split workgroup keywords
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
															: "");
	},

	setHomeLink: function() {
		 document.getElementById("home-link").href = top.HEURIST.basePath;
	},

	writeRSSLinks: function() {
		/*
		var link = document.createElement("link");
		link.rel = "alternate";
		link.type = "application/rss+xml";
		link.title = "HTTP RSS feed";
		link.href = "http://"+window.location.host+"+top.HEURIST.basePath+"legacy/search_rss.php"+document.location.search;
		document.getElementsByTagName("head")[0].appendChild(link);
		*/
		var link = document.createElement("link");
		link.rel = "alternate";
		link.type = "application/rss+xml";
		link.title = "RSS feed";
		link.href = "feed://"+window.location.host+top.HEURIST.basePath+"feeds/search_rss.php"+(document.location.search ? document.location.search : "?q=tag:Favourites");
		document.getElementsByTagName("head")[0].appendChild(link);
		document.getElementById("httprsslink").href += (document.location.search ? document.location.search : "?q=tag:Favourites");
	},

	toggleLegend: function() {
		var legend_box_elt = document.getElementById("legend-box");
		if (legend_box_elt) {
			legend_box_elt.parentNode.removeChild(legend_box_elt);
			return;
		}

		var visible_reftypes = {};
		var results = top.HEURIST.search.results;

		if (! results) return;

		var currentPage = top.HEURIST.search.currentPage;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		var firstOnPage = currentPage * resultsPerPage;
		var lastOnPage = Math.min((currentPage+1)*resultsPerPage, results.totalRecordCount)-1;
		for (var i = firstOnPage; i <= lastOnPage; ++i) {
			visible_reftypes[results.records[i][4]] = true;
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
		for (var i in visible_reftypes) {
			if (top.HEURIST.reftypes.names[i])
				iHTML += "<li><img src='src='"+ top.HEURIST.basePath+"common/images/reftype-icons/"+i+".gif'>"+top.HEURIST.reftypes.names[i]+"</li>";
		}

		document.getElementById("legend-box-list").innerHTML = iHTML;

		iHTML = "<hr><ul>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow.gif'><span>Resource is in </span><span style=\"font-weight: bold;\">my records</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-green-7.gif'><span>Belongs to workgroup 7<br></span><span style=\"margin-left: 25px;\">- read-only to others</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-red-3.gif'><span>Belongs to workgroup 3<br></span><span style=\"margin-left: 25px;\">- hidden to others</span></li>";
		iHTML += "</ul>";
		iHTML += "<hr><div><a href=# onclick=\"top.HEURIST.util.popupURL(window, '"+ top.HEURIST.basePath+"common/lib/reftype_legend.php'); return false;\">show full legend</a></div>";
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
			window.location.href = top.HEURIST.basePath+"admin/verification/fix_dupes.php?bib_ids=" + bib_ids.join(",");
		}
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

		if (top.HEURIST.instance.name === "") {
			var im_container = document.getElementById("publish-image-placeholder");
			var a = document.createElement("a");
				a.className = "toolbar-large-icon-link logged-in-only";
				a.title = "Publish the current search results as formatted output which can be printed, saved or generated (live) in a web page";
				a.href = "#";
			a.onclick = function() {
				if (window.HEURIST.parameters["label"] && window.HEURIST.parameters["sid"]) {
					window.open(top.HEURIST.basePath+ "output/pubwizard/publish.php?pub_id=" + window.HEURIST.parameters["sid"]);
				} else {
					top.HEURIST.util.popupURL(window, top.HEURIST.basePath + 'search/saved/save-search.html?publish=yes');
				}
				return false;
			}
			a.appendChild(document.createTextNode("publish"));
			im_container.appendChild(a);
			im_container.appendChild(document.createTextNode(" | "));
		}

	},

	showPublishPopup: function() {
		var div, a, p, popup, param = window.HEURIST.parameters;
		if (param['pub']  &&  param['sid']) {
			div = document.createElement('div');
			div.style.padding = '0 10px';
			p = div.appendChild(document.createElement('p'));
			p.innerHTML = 'The current search has been saved.';
			p = div.appendChild(document.createElement('p'));
			p.innerHTML = 'Click below to continue to the publishing wizard.';
			p = div.appendChild(document.createElement('p'));
			a = p.appendChild(document.createElement('a'));
			a.href = top.HEURIST.basePath+ 'output/pubwizard/publish.php?pub_id=' + param['sid'];
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

		document.getElementById("select-all-checkbox").checked = false;
	}
};

top.HEURIST.fireEvent(window, "heurist-search-js-loaded");


