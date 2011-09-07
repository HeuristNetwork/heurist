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

//	bib_ids: {},	//depricated  by SAW 21/7/11  newer selection model
//	bkmk_ids: {},
	record_view_style: "full",

	checkSearchForm: function() {
		var e;
		e = document.getElementById('stype');
		if (! e.value || e.value == '') {
			e.parentNode.removeChild(e);
		}
		e = document.getElementById('w-input');
		if (! e.value || e.value == '') {
//			e.parentNode.removeChild(e);
		}
	},

	submitSearchForm: function() {
//		top.HEURIST.search.checkSearchForm();
//		document.forms[0].submit();
		top.HEURIST.search.clearResultRows();
		top.HEURIST.search.clearRelatesRows();
		top.HEURIST.search.reloadSearch();
	},

	searchNotify: function(results) {
		top.HEURIST.search.results = {
			recSet: {},
			infoByDepth: [ {
				count : 0,
				recIdToIndexMap : {},
				recIDs : [],
				rectypes : {}
				}],
			recSetCount: results.totalRecordCount,
			totalQueryResultRecordCount: results.totalRecordCount
		};
		top.HEURIST.search.sid = results.sid;

		if (top.HEURIST.search.results.totalQueryResultRecordCount == 0) {
			top.HEURIST.registerEvent(window, "load", function(){
						top.HEURIST.search.clearResultRows();
						top.HEURIST.search.clearRelatesRows();
						top.HEURIST.search.renderNavigation();
			});
		}
//			top.HEURIST.registerEvent(window, "load", top.HEURIST.search.clearRelatesRows);

//		top.HEURIST.registerEvent(window, "load", top.HEURIST.search.renderNavigation);

		results.notified = true;
	},

	searchResultsNotify: function(newResults, startIndex) {
		var rt,rec, recID;
		for (var i=0; i < newResults.records.length; ++i) {// save the new results
			top.HEURIST.search.results.infoByDepth[0].count++;
			rec = newResults.records[i];
			recID = rec[2];
			rt = rec[4];
			top.HEURIST.search.results.infoByDepth[0].recIDs[i + startIndex] = recID;
			top.HEURIST.search.results.infoByDepth[0].recIdToIndexMap[recID] = i + startIndex;
			if (!top.HEURIST.search.results.infoByDepth[0].rectypes[rt]) {
				top.HEURIST.search.results.infoByDepth[0].rectypes[rt] = [recID];
			}else{
				top.HEURIST.search.results.infoByDepth[0].rectypes[rt].push(recID);
			}
			top.HEURIST.search.results.recSet[recID] = { depth:0,
														record:rec};
		}
		newResults.records = [];	//clear results

		// check if we've just loaded the page we were expecting to render
		if (Math.floor(startIndex / top.HEURIST.search.resultsPerPage) == top.HEURIST.search.currentPage) {
			// should have some sort of "searchready" event -- FIXME as part of holistic event-based solution
			var lastIndex = Math.min(startIndex + top.HEURIST.search.resultsPerPage, top.HEURIST.search.results.totalQueryResultRecordCount);
			top.HEURIST.registerEvent(window, "contentloaded", function() {
				top.HEURIST.search.renderSearchResults(startIndex, lastIndex-1);
				top.HEURIST.search.renderNavigation();
			});
			top.HEURIST.registerEvent(window, "load", function() {
				document.getElementById("viewer-frame").src = top.HEURIST.basePath+ "viewers/printview/index.html" +
					(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
			});
		}

		if (top.HEURIST.search.results.infoByDepth[0].count == top.HEURIST.search.results.totalQueryResultRecordCount){
			top.HEURIST.search.loadRelatedResults();
		}
	},


	loadSearchLocation: function(strLocation) {
		var temp = strLocation + ("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));
		window.location.href = temp;
	},

	reloadSearch: function() {
		window.HEURIST.parameters["q"] = document.getElementById("q").value;
		window.HEURIST.parameters["w"] = document.getElementById("w-input").value;
		if (!$("#simple-search").hasClass("collapsed")) {
			$("#simple-search").toggleClass("collapsed");
			$("div.simplesearch").toggleClass("collapsed");
		}
		top.HEURIST.search.loadSearch();
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
			(window.HEURIST.database && window.HEURIST.database.name ? "&db=" + window.HEURIST.database.name : ""));
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

	loadRelatedResults: function() {
		var URL = top.HEURIST.basePath+"search/getRelatedResultSet.php?" +
			("w=" + (window.HEURIST.parameters["w"] ? encodeURIComponent(window.HEURIST.parameters["w"]) : "all")) +
			(window.HEURIST.parameters["stype"] ? "&stype=" +encodeURIComponent(window.HEURIST.parameters["stype"]) : "") +
			("&ver=" + top.HEURIST.search.VERSION) +
			("&q=" + encodeURIComponent(window.HEURIST.parameters["q"])) +
			("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))) +
			"&depth=3&limit=1000";
		top.HEURIST.registerEvent(window, "heurist-related-recordset-loaded",
									function (evt) {
													top.HEURIST.search.loadLevelFilter(0);
													top.HEURIST.search.addResultLevelLinks(0);
													if (top.HEURIST.search.results.infoByDepth.length >1 &&
															top.HEURIST.search.results.infoByDepth[1].count > 0) {
														top.HEURIST.search.loadLevelFilter(1);
													}
									});
		top.HEURIST.util.getJsonData(URL,
				function(related) {
					var results = top.HEURIST.search.results,
						recID,i,j;
					if (related && related.count >= results.totalQueryResultRecordCount) {
						results.recSetCount = related.count;
						results.params = related.params;
						for (recID in related.relatedSet){
							var recInfo = related.relatedSet[recID];
							if (!results.recSet[recID]){
								results.recSet[recID] = recInfo;
							}else if(recInfo.depth == 0){
								if ( recInfo.ptrLinks ) {
									results.recSet[recID].ptrLinks = recInfo.ptrLinks;
								}
								if ( recInfo.revPtrLinks ) {
									results.recSet[recID].revPtrLinks = recInfo.revPtrLinks;
								}
								if ( recInfo.relLinks ) {
									results.recSet[recID].relLinks = recInfo.relLinks;
								}
								if ( recInfo.revRelLinks ) {
									results.recSet[recID].revRelLinks = recInfo.revRelLinks;
								}
							}
						}
						if (related.infoByDepth.length > 1) {
							for (i=1; i < related.infoByDepth.length; i++) {
								results.infoByDepth[i] = related.infoByDepth[i];
								results.infoByDepth[i].count = results.infoByDepth[i].recIDs.length;
								results.infoByDepth[i].recIdToIndexMap = {};
								for ( j=0; j<results.infoByDepth[i].count; j++) {
									results.infoByDepth[i].recIdToIndexMap[results.infoByDepth[i].recIDs[j]] = j;
								}
							}
						}
					}
					top.HEURIST.fireEvent(window, "heurist-related-recordset-loaded");
				});
	},

	loadLevelFilter: function(level){
		var results = top.HEURIST.search.results;
		var maxDepth = Math.min((results.params ? results.params.depth : 4), results.infoByDepth.length - 1);
		if (level > maxDepth) {
			return;
		}
		var depthInfo = results.infoByDepth[level];
		if (depthInfo.count == 0) {
			return;
		}
		var resultsDiv =  $("#results-level" + level);
		if (resultsDiv.length == 0) {
			resultsDiv = document.createElement("div");
			resultsDiv.id = "results-level" + level;
			$(resultsDiv).attr("level",level);
			resultsDiv.className = "icons"; //saw TODO: change this to get preference
			document.getElementById("results").appendChild(resultsDiv);
		}else{
			resultsDiv =resultsDiv.get(0);
		}
		var filterDiv =  $(".filter",resultsDiv);
		if (filterDiv.length == 0) {
			filterDiv = document.createElement("div");
			filterDiv.className = "filter";
			if (resultsDiv.firstChild) {
				resultsDiv.insertBefore(filterDiv, resultsDiv.firstChild);
			}else{
				resultsDiv.appendChild(filterDiv);
		}
		}else{
			filterDiv = filterDiv.get(0);
			filterDiv.innerHTML = "";
		}

		if(level>0){
			var showRelatedMenuItem = document.createElement("div");
//			<!--showRelatedMenuItem.innerHTML = "<a title=\"Click to show records related to the records listed above\" href='#' onclick=top.HEURIST.search.toggleRelated("+level+")>Show Related Records</a>";-->
			showRelatedMenuItem.innerHTML = "<a title=\"Click to show "+depthInfo.count+" records related to the records listed above\" href='#' onclick=top.HEURIST.search.toggleRelated("+level+")>Show Level "+level+" Related Records <span class=\"relatedCount\">"+depthInfo.count+"</span></a>";
			showRelatedMenuItem.id = "showrelated"+level;
			showRelatedMenuItem.className = "showrelated level" + level;
			filterDiv.appendChild(showRelatedMenuItem);
			resultsDiv.className += " collapsed";
		}

		var filterMenu = document.createElement("ul");
		filterMenu.id = "filter" + level;
		filterMenu.className = "horizontal menu level"+level;
		filterDiv.appendChild(filterMenu);
		if (depthInfo) {
			var levelRecTypes = (level <= maxDepth && depthInfo.rectypes ? depthInfo.rectypes : null);
			var levelPtrTypes = (level <= maxDepth && depthInfo.ptrtypes ? depthInfo.ptrtypes: null);
			var levelRelTypes = (level <= maxDepth && depthInfo.reltypes? depthInfo.reltypes : null);
		}
		//create rectype filter menu

		if (levelRecTypes){
			var j;
			var rtNames = $.map(levelRecTypes, function(recIDs,rtID) {
								return (top.HEURIST.rectypes.names[rtID] ? "" + top.HEURIST.rectypes.names[rtID] + ":" + rtID : null);
							});
			rtNames.sort();
			var rectypeMenuItem = document.createElement("li");
			(level == 0 ? rectypeMenuItem.innerHTML = "<span>Filter</span>" :  rectypeMenuItem.innerHTML = "<span>Filter by Rectype</span>") ;
			var rectypeList = document.createElement("ul");
			rectypeList.className = "rectype level"+level;
			var li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",true)>Select All</a>";
			li.className = "cmd";
			rectypeList.appendChild(li);
			li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",false)>Select None</a>";
			li.className = "cmd";
			rectypeList.appendChild(li);
			for (j=0; j< rtNames.length; j++) {
				var rtInfo = rtNames[j].split(":");
				li = document.createElement("li");
				li.rectype = rtInfo[1];
				$(li).attr("rectype", rtInfo[1]);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.toggleRectypeFilter(this.parentNode,"+level+","+rtInfo[1]+")>" + rtInfo[0] + "</a>";
				li.className = "checked level"+level;
				rectypeList.appendChild(li);
			}
			rectypeMenuItem.appendChild(rectypeList);
			filterMenu.appendChild(rectypeMenuItem);
		}
		if (levelPtrTypes){
			var j;
			var ptrNames = $.map(levelPtrTypes, function(recIDs,dtlID) {
								return (top.HEURIST.detailTypes.names[dtlID] ? "" + top.HEURIST.detailTypes.names[dtlID] + ":" + dtlID : null);
							});
			ptrNames.sort();
			var ptrtypeMenuItem = document.createElement("li");
			ptrtypeMenuItem.innerHTML = "<span>Filter by Pointer Type</span>";
			var ptrtypeList = document.createElement("ul");
			ptrtypeList.className = "ptrtype level"+level;
			var li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",true)>Select All</a>";
			li.className = "cmd";
			ptrtypeList.appendChild(li);
			li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",false)>Select None</a>";
			li.className = "cmd";
			ptrtypeList.appendChild(li);
			for (j=0; j< ptrNames.length; j++) {
				var ptrInfo = ptrNames[j].split(":");
				li = document.createElement("li");
				li.ptrtype = ptrInfo[1];
				$(li).attr("ptrtype", ptrInfo[1]);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.togglePtrtypeFilter(this.parentNode,"+level+","+ptrInfo[1]+")>" + ptrInfo[0] + "</a>";
				li.className = "checked level"+level;
				ptrtypeList.appendChild(li);
			}
			ptrtypeMenuItem.appendChild(ptrtypeList);
			filterMenu.appendChild(ptrtypeMenuItem);
		}
		if (levelRelTypes){
			var j;
			var relNames = $.map(levelRelTypes, function(recIDs,trmID) {
								return (top.HEURIST.terms.termsByDomainLookup.relation[trmID] ? "" + top.HEURIST.terms.termsByDomainLookup.relation[trmID][0] + ":" + trmID : null);
							});
			relNames.sort();
			var reltypeMenuItem = document.createElement("li");
			reltypeMenuItem.innerHTML = "<span>Filter by Relation Type</span>";
			var reltypeList = document.createElement("ul");
			reltypeList.className = "reltype level"+level;
			var li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",true)>Select All</a>";
			li.className = "cmd";
			reltypeList.appendChild(li);
			li = document.createElement("li");
			li.innerHTML = "<a href='#' onclick=top.HEURIST.search.setAllFilterItems(this.parentNode.parentNode," +level + ",false)>Select None</a>";
			li.className = "cmd";
			reltypeList.appendChild(li);
			for (j=0; j< relNames.length; j++) {
				var relInfo = relNames[j].split(":");
				var li = document.createElement("li");
				li.reltype = relInfo[1];
				$(li).attr("reltype", relInfo[1]);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.toggleReltypeFilter(this.parentNode,"+level+","+relInfo[1]+")>" + relInfo[0] + "</a>";
				li.className = "checked level"+level;
				reltypeList.appendChild(li);
			}
			reltypeMenuItem.appendChild(reltypeList);
			filterMenu.appendChild(reltypeMenuItem);
		}

	},

	toggleRelated: function(level){
		var className =  document.getElementById("showrelated" + level).className;
		$("#results-level" + level).toggleClass("collapsed");
		if (className.match(/loaded/)) {
			if ($("#results-level" + level).hasClass("collapsed")) {
				$("#showrelated" + level).html("<a onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Show Level "+level+" Related Records</a>");
				}else{
				$("#showrelated" + level).html("<a style='background-image:url(../common/images/heading_saved_search.png)' onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Hide Level "+level+" Related Records</a>");
				};
		}else{
			top.HEURIST.search.loadRelatedLevel(level);
			document.getElementById("showrelated" + level).className = className + " loaded";
			$("#showrelated" + level).html("<a style='background-image:url(../common/images/heading_saved_search.png)' onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Hide Level "+level+" Related Records</a>");
			top.HEURIST.search.filterRelated(level);
		}
	},

	setAllFilterItems: function(filterMenu, level, toChecked){
		var selector = "li:not(.checked,.cmd)";
		if (!toChecked){
			selector = "li:not(.cmd).checked"
		}
		$(selector,filterMenu).toggleClass('checked');
		top.HEURIST.search.filterRelated(level);
	},

	toggleRectypeFilter: function(menuItem, level, rtID){
		var resultsDiv =  $("#results-level" + level).get(0);
		var recIDs = top.HEURIST.search.results.infoByDepth[level].rectypes[rtID];
		$.each(recIDs,function(i,recID){
				$('.recordDiv[recID='+recID+']',resultsDiv).toggleClass('filtered');
			});
		$(menuItem).toggleClass('checked')
		top.HEURIST.search.filterRelated(level+1);
	},

	togglePtrtypeFilter: function(menuItem, level, dtyID){
		var recalcFilters = false;
		var resultsDiv =  $("#results-level" + level).get(0);
		var recIDs = top.HEURIST.search.results.infoByDepth[level].ptrtypes[dtyID];
		if ($(menuItem).hasClass('checked')) {// filter all pointer links of type dtyID by removing a "lnk" class
			$.each(recIDs,function(i,recID){
					recalcFilters = !$('.recordDiv[recID='+recID+']',resultsDiv).removeClass('lnk').hasClass('lnk');
				});
		}else{
			$.each(recIDs,function(i,recID){
					recalcFilters = !$('.recordDiv[recID='+recID+']',resultsDiv).hasClass('lnk');
					$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
				});
		}
		$(menuItem).toggleClass('checked');
//		if (recalcFilters) {
			top.HEURIST.search.filterRelated(level+1);
//		}
	},

	toggleReltypeFilter: function(menuItem, level, trmID){
		var recalcFilters = false;
		var resultsDiv =  $("#results-level" + level).get(0);
		var recIDs = top.HEURIST.search.results.infoByDepth[level].reltypes[trmID];
		if ($(menuItem).hasClass('checked')) {// filter all relation links of type dtyID by removing a "lnk" class
			$.each(recIDs,function(i,recID){
					recalcFilters = !$('.recordDiv[recID='+recID+']',resultsDiv).removeClass('lnk').hasClass('lnk');
				});
		}else{
			$.each(recIDs,function(i,recID){
					recalcFilters = !$('.recordDiv[recID='+recID+']',resultsDiv).hasClass('lnk');
					$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
				});
		}
		$(menuItem).toggleClass('checked')
//		if (recalcFilters) {
			top.HEURIST.search.filterRelated(level+1);
//		}
	},

	// new render common result record
	renderResultRecord: function(res) {

		/* res[0]   res[1]        res[2]  res[3]   res[4]       res[5]     res[6]        */
		/* bkm_ID, bkm_UGrpID, rec_ID, rec_URL, rec_RecTypeID, rec_Title, rec_OwnerUGrpID */

		/* res[7]          res[8]                 res[9]         res[10]        res[11]   */
		/* rec_NonOwnerVisibility, rec_URLLastVerified, rec_URLErrorMessage, bkm_PwdReminder, thumbnailURL */

		var pinAttribs = res[0]? "class='logged-in-only bookmarked' title='Bookmarked - click to see details'"
		                       : "class='logged-in-only unbookmarked' title='Bookmark this record'";

		var href = res[3];
		var linkText = res[5] + " ";
		var wgID = parseInt(res[6]);

		var linkTitle = "";
		var wgHTML = "";
		var wgColor = "";
		if (res[6]  &&  res[6] != "0" && res[6] != res[1]) {	// check if this is a usergroup owned record
			linkTitle = "Owned by " + (top.HEURIST.workgroups[wgID] ? "workgroup " + top.HEURIST.workgroups[wgID].name:top.HEURIST.allUsers[wgID][0]) + " - " + (res[7]==1? "hidden" : "read-only") + " to others";
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

		if (href) {
			if (! href.match(/^[^\/\\]*:/))
				href = "http://" + href;
			href = href.htmlEscape();
		}
		else if (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['RT_NOTE'] && res[4] == top.HEURIST.magicNumbers['RT_NOTE']) {
			// special handling for notes rectype: link to view page if no URL
			href = top.HEURIST.basePath+ "records/view/renderRecordData.php?recID="+res[2] +(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
		}

		var userPwd;
		if (res[10]) userPwd = "style='display:inline;cursor:pointer;margin-left:8px' user_pwd='"+res[10].htmlEscape()+"'";
		else userPwd = "style='display:none;'";

		var rectypeImg = "style='background-image:url("+ top.HEURIST.basePath+"common/images/rectype-icons/" + top.HEURIST.database.id + "-" + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeThumb = "style='background-image:url("+ top.HEURIST.basePath+"common/images/rectype-icons/thumb/th_" + top.HEURIST.database.id + "-" + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeTitle = "Click to see details";
		if (top.HEURIST.rectypes.names[parseInt(res[4])])
			rectypeTitle = top.HEURIST.rectypes.names[parseInt(res[4])] + " - click to see details";
		var html =
		"<div class='recordDiv' title='Select to view, Ctrl-or Shift- for multiple select' bkmk_id='"+res[0]+"' recID="+res[2]+" rectype="+res[4]+">" +
		"<input style='display:none' type=checkbox name=bib[] onclick=top.HEURIST.search.resultItemOnClick(this) class='logged-in-only' title='Check box to apply Actions to this record'>"+
		"<div class='recTypeThumb' "+rectypeThumb+" ></div>" +
		(res[11] && res[11].length ? "<div class='thumbnail' style='background-image:url("+res[11]+")' ></div>":"") +
		   "<div class=recordIcons  bkmk_id='"+res[0]+"' recID="+res[2]+">" +
		   "<img src='"+ top.HEURIST.basePath+"common/images/16x16.gif' title='"+rectypeTitle.htmlEscape()+"' "+rectypeImg+" class='rft'>"+
		   "<img src='"+ top.HEURIST.basePath+"common/images/13x13.gif' " + pinAttribs + ">"+
		   "<span class='wg-id-container logged-in-only'>"+
		   "<span class=wg-id title='"+linkTitle.htmlEscape()+"' " + (wgColor? wgColor: "") + ">" + (wgHTML? wgHTML.htmlEscape() : "") + "</span>"+
		   "</span>"+
		   "<img onclick=top.HEURIST.search.passwordPopup(this) title='Click to see password reminder' src='"+ top.HEURIST.basePath+"common/images/lock.png' " + userPwd + ">"+
		    "</div>" +
			"<div class='recordTitle' title='"+linkText+"'>" + (res[3] && res[3].length ? daysBad +"<a href='"+res[3]+"' target='_blank'>"+linkText + "</a>" : linkText ) + "</div>" +
			"<div id='recordID'>"+top.HEURIST.rectypes.names[parseInt(res[4])]+"<br><a href='"+top.HEURIST.basePath+"search/search.html?q=ids:"+res[2]+
			(top.HEURIST.database && top.HEURIST.database.name ? '&db=' + top.HEURIST.database.name : '') +
			"' target='_blank' title='Open in new window'>ID: "+res[2]+"</a>"+
			"<div id='rec_edit_link' class='logged-in-only' title='Click to edit'><a href='"+
			top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
			top.HEURIST.search.sid + "&recID="+ res[2] +
			(top.HEURIST.database && top.HEURIST.database.name ? '&db=' + top.HEURIST.database.name : '') +
			"' target='_blank'><img src='"+	top.HEURIST.basePath + "common/images/edit_pencil_small.png'/></a></div>" +
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

		var inst_input = document.getElementById("db");
		if (inst_input  &&  params["db"]) {
			inst_input.value = params["db"];
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
			document.getElementById("search-description").style.display = "block";
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
			return "<img src=\"../common/images/url_warning.png\" class=\"daysbad\" title=\"URL not yet tested\">";

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

		return "<img src=\"../common/images/url_error.png\" class=\"daysbad\" title=\"" + err_string +" \">";
	},

	renderLoginDependentContent: function() {
		var logged_in_elt = document.getElementById("logged-in");
		var left_panel_elt = document.getElementById("left-panel-content");
		if (top.HEURIST.is_logged_in()) {
			logged_in_elt.innerHTML = top.HEURIST.get_user_name() + " : <a href=" +top.HEURIST.basePath+ "common/connect/login.php?logout=1"+(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "")+">log&nbsp;out</a>";
		} else {
			logged_in_elt.innerHTML = "not logged in : <a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "")+">log in</a>";
			left_panel_elt.innerHTML =
				"<div style=\"padding: 10px;\">\n" +
				" <br>Existing users:\n" +
				" <div id=login-button><a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "")+" title=\"Log in to use Heurist - new users please register first\"><img src=../common/images/111x30.gif></a></div>\n" +
				" <p style=\"text-align: center;\"><a href=\"javascript:" + top.HEURIST.bookmarkletCode + "\" onclick=\"alert('Drag the Heurist Bookmarklet link to your browser bookmarks toolbar, or right-click the link, choose Bookmark This Link, and add the link to your Bookmarks Toolbar or Favorites.');return false;\" title=\"Get Bookmarklet - bookmarks web pages and harvests web links \(we recommend Firefox and the Firefox toolbar - it has more features\)\">Heurist Bookmarklet</a><br></p>" +
				" <br><br>New users:\n" +
				" <div id=tour-button><a href=" +top.HEURIST.basePath+ "help/tour.html title=\"Take a quick tour of Heurist's major features\" target=\"_blank\"><img src=../common/images/111x30.gif></a></div>\n" +
				" <div id=register-button><a href=" +top.HEURIST.basePath+ "admin/ugrps/findAddUser.php?register=1"+(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "")+" target=\"_blank\" title=\"Register to use Heurist - takes only a couple of minutes\"><img src=../common/images/111x30.gif></a></div>\n" +
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
		$("#results-level0 div.recordDiv").remove();
	},

	clearRelatesRows: function() {
		$("div[id!=results-level0][id*=results-level]").remove();
		top.HEURIST.search.selectedRecordIds = [];
		top.HEURIST.search.selectedRecordDivs = [];
	},

	loadRelatedLevel: function(level){
		if (level == 0) {
			return;
		}
		var resultsDiv = document.getElementById("results-level"+level);
		var prevLevelResultDiv = document.getElementById("results-level"+(level-1));
		if (!resultsDiv || !prevLevelResultDiv) {
			return;
		}
		//get recIDs of level - 1 records.
		// and for each calculate filtered record set for this level.
		var recSet = top.HEURIST.search.results.recSet;
		var depthInfo = top.HEURIST.search.results.infoByDepth[level];
		var recordIDs = {};
		var html = "";
		$.each(depthInfo.recIDs,function(i,recID){
				html += top.HEURIST.search.renderResultRecord(recSet[recID].record);
			});
		resultsDiv.innerHTML += html;
		top.HEURIST.search.addResultLevelEventHandlers(level);
		top.HEURIST.search.addResultLevelLinks(level);
		//for each link type add lnkrel or lnkptr  to the recordDiv's class for next level records
		//when all links are filtered (removed) the record is hidden. This is how we get multivalued filtering
		$('ul.ptrtype>li.checked',resultsDiv).each( function(i,li){
				var dtyID = $(li).attr('ptrtype');
				$.each(depthInfo.ptrtypes[dtyID],function(i,recID){
						$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
					});
			});
		$('ul.reltype>li.checked',resultsDiv).each( function(i,li){
				var dtyID = $(li).attr('reltype');
				$.each(depthInfo.reltypes[dtyID],function(i,recID){
						$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
					});
			});
		top.HEURIST.search.loadLevelFilter(level + 1);
	},

	filterRelated: function(level){
		if (level == 0 || !$("#showrelated" + level).hasClass("loaded")) {
			return;
		}
		var resultsDiv = document.getElementById("results-level"+level);
		var parentLevelResultDiv = document.getElementById("results-level"+(level-1));
		if (!resultsDiv || !parentLevelResultDiv) {
			return;
		}
		//find unfiltered types from level filter
		var rectypes = {};
		var ptrtypes = {};
		var reltypes = {};
		//get recIDs of level - 1 records that are not filtered.
		// and for each calculate filtered record set for this level.
		var recSet = top.HEURIST.search.results.recSet;
		var depthInfo = top.HEURIST.search.results.infoByDepth[level];
		var recordIDs = {};
		$('.recordDiv',parentLevelResultDiv).each(function(i,recDiv){
				// for all parentlevel records that are not filtered or without links
				if ($(recDiv).hasClass('filtered') || (!$(recDiv).hasClass('lnk') && (level-1 > 0))){
					return;
				}
				var recInfo = recSet[$(recDiv).attr("recID")];
				if (recInfo.ptrLinks){
					$.each(recInfo.ptrLinks.byDtlType,function(dtlID, recIDs){
							var j, recID, recTypeID;
							ptrtypes[dtlID] =1;
							for (j in recIDs){
								recID = recIDs[j];
								recTypeID = recSet[recID].record[4];
								rectypes[recTypeID] = 1;
								recordIDs[recID] = 1;
							}
						});
				}
				if (recInfo.revPtrLinks){
					$.each(recInfo.revPtrLinks.byInvDtlType,function(dtlID, recIDs){
							var j, recID, recTypeID;
							ptrtypes[dtlID] =1;
							for (j in recIDs){
								recID = recIDs[j];
								recTypeID = recSet[recID].record[4];
								rectypes[recTypeID] = 1;
								recordIDs[recID] = 1;
							}
						});
				}
				if (recInfo.relLinks){
					$.each(recInfo.relLinks.byRelType,function(trmID, recIDs){
							var j, recID, recTypeID;
							reltypes[trmID] =1;
							for (j in recIDs){
								recID = recIDs[j];
								recTypeID = recSet[recID].record[4];
								rectypes[recTypeID] = 1;
								recordIDs[recID] = 1;
							}
						});
				}
				if (recInfo.revRelLinks){
					$.each(recInfo.revRelLinks.byInvRelType,function(trmID, recIDs){
							var j, recID, recTypeID;
							reltypes[trmID] = 1;
							for (j in recIDs){
								recID = recIDs[j];
								recTypeID = recSet[recID].record[4];
								rectypes[recTypeID] = 1;
								recordIDs[recID] = 1;
							}
						});
				}
			});
		//un check all filter menu items
//		$('ul>li.checked',resultsDiv).each( function(i,li){
//				$(li).toggleClass('checked');
//			});
		//enable all filter menu items
		$('ul>li.disabled',resultsDiv).each( function(i,li){
				$(li).toggleClass('disabled');
			});
		// remove all lnk class tags
		$('.recordDiv',resultsDiv).each( function(i,recDiv){
				while($(recDiv).hasClass('lnk')){
					$(recDiv).toggleClass('lnk');
				}
				var recID = $(recDiv).attr('recID');
				if (recordIDs[recID] == 1) {//record related to upper level unfiltered record
					if($(recDiv).hasClass('filtered')){
						$(recDiv).toggleClass('filtered');
					}
				}else{
					if(!$(recDiv).hasClass('filtered')){
						$(recDiv).toggleClass('filtered');
					}
				}
			});
		//for each of the menus disable reltype menu items not in the reltype set and lnk records for those that are checked
		$('ul.reltype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var trmID = $(li).attr('reltype');
				if (reltypes[trmID] === 1) {
					if ($(li).hasClass('checked')) {
						$.each(depthInfo.reltypes[trmID],function(i,recID){
								if (recordIDs[recID] === 1) {
									$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
								}
							});
					}
				}else{
					$(li).toggleClass('disabled');
				}
			});
		//for each of the menus disable ptrtype menu items not in the ptrtype set and lnk records for those that are checked
		$('ul.ptrtype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var dtyID = $(li).attr('ptrtype');
				if (ptrtypes[dtyID] === 1) {
					if ($(li).hasClass('checked')) {
						$.each(depthInfo.ptrtypes[dtyID],function(i,recID){
								if (recordIDs[recID] === 1) {
									$('.recordDiv[recID='+recID+']',resultsDiv).get(0).className += ' lnk';
								}
							});
					}
				}else{
					$(li).toggleClass('disabled');
				}
			});
		//for each of the menus disable rectype menu items not in the rectype set and filter records of those that are not checked
		$('ul.rectype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var rtID = $(li).attr('rectype');
				if (rectypes[rtID] === 1) { //rectype menu is in the related set
					if (!$(li).hasClass('checked')) { // if it's not checked then filter all records of this type
						$.each(depthInfo.rectypes[rtID],function(i,recID){
								var recDiv = $('.recordDiv[recID='+recID+']',resultsDiv);
								if (!recDiv.hasClass('filtered')) {
									recDiv.toggleClass('filtered');
								}
							});
					}
				}else{
					$(li).toggleClass('disabled');
				}
			});
		top.HEURIST.search.filterRelated(level + 1);
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
		//if (style != "list"  ||  style != "thumbnails" || style !="icons") {
		//	style = "thumbnails"; // fall back for old styles
			top.HEURIST.util.setDisplayPreference("search-result-style", style);
		//}
		var resultsDiv = document.getElementById("results-level0");
		var pageWidth = document.getElementById('page').offsetWidth;
		if (pageWidth < 390 && style == "two-col") {
			resultsDiv.className = "list";
		}else{
			resultsDiv.className = style;
		};

		var recSet = top.HEURIST.search.results.recSet;
		var recInfo = top.HEURIST.search.results.infoByDepth[0];

		var innerHTML = "";
		var leftHTML = "";
		var rightHTML = "";
		var recordCount = lastIndex - firstIndex + 1;
		for (var i = 0; i < recordCount; ++i) {
			if (recInfo.recIDs[firstIndex+i]) {
				var recHTML = top.HEURIST.search.renderResultRecord(recSet[recInfo.recIDs[firstIndex+i]].record);
				innerHTML += recHTML;
				if (i < recordCount / 2) {
					leftHTML += recHTML;
				} else {
					rightHTML += recHTML;
				}
			}
		}

		//if (style == "list"  ||  style == "thumbnails" || style =="icons") {
			resultsDiv.innerHTML += innerHTML;
		//} else if (style = "two-col") {
		//	resultsDiv.innerHTML = "<div class=two-col-cell><div class=two-col-inner-cell-left>" + leftHTML + "</div></div><div class=two-col-cell><div class=two-col-inner-cell-right>" + rightHTML + "</div></div>";
		//}

		// add click handlers
		top.HEURIST.search.addResultLevelEventHandlers(0);
		top.HEURIST.search.addResultLevelLinks(0);
		top.HEURIST.search.filterRelated(1);

		top.HEURIST.registerEvent(window, "load", function() {top.HEURIST.search.trimAllLinkTexts(0);});
		top.HEURIST.registerEvent(window, "load", function() {
			if (document.getElementById("legend-box")) {
				top.HEURIST.search.toggleLegend();
				top.HEURIST.search.toggleLegend();
			}
		});
	},

	addResultLevelLinks: function(level) {
		var resultsDiv = document.getElementById("results-level"+level);
		var recSet = top.HEURIST.search.results.recSet;
		//apply classes for linked recordIDs
		$('.recordDiv',resultsDiv).each(function(i, recDiv){
				var recInfo = recSet[$(recDiv).attr("recID")];
				var linkedRecIDs = {};
				if (recInfo.ptrLinks){
					for (recID in recInfo.ptrLinks.byRecIDs){
						linkedRecIDs[recID] = 1;
					}
				}
				if (recInfo.revPtrLinks){
					for (recID in recInfo.revPtrLinks.byRecIDs){
						linkedRecIDs[recID] = 1;
					}
				}
				if (recInfo.relLinks){
					for (recID in recInfo.relLinks.byRecIDs){
						linkedRecIDs[recID] = 1;
					}
				}
				if (recInfo.revRelLinks){
					for (recID in recInfo.revRelLinks.byRecIDs){
						linkedRecIDs[recID] = 1;
					}
				}
				var classLinkedRecIDs = $.map(linkedRecIDs,function(i,recID){
																return "link" + recID;
															});
				if (level == 0) {
					classLinkedRecIDs.push("lnk");
				}
				$(recDiv).addClass(classLinkedRecIDs.join(" "));
			});
	},

	addResultLevelEventHandlers: function(level) {
		// add click handlers
		var recDivs = $(".recordDiv", $("#results-level"+level)).get();
		for (var i=0; i < recDivs.length; ++i) {
			var recDiv = recDivs[i];
			var pin_img = $(".unbookmarked", recDiv)[0];
			var thumb_img = $(".thumbnail, .no-thumbnail, .rec_title", recDiv)[0];
			var rec_title = $(".recordTitle", recDiv)[0];

			top.HEURIST.registerEvent(recDiv, "click", top.HEURIST.search.resultItemOnClick);
			top.HEURIST.registerEvent(recDiv, "mouseover", top.HEURIST.search.resultItemMouseOver);
			top.HEURIST.registerEvent(recDiv, "mouseout", top.HEURIST.search.resultItemMouseOut);
			$("a",recDiv).mouseover(function(e){
										top.HEURIST.search.resultItemMouseOver(e,this.parentNode);
										return true;
									});
			$("a",recDiv).mouseout(function(e){
										top.HEURIST.search.resultItemMouseOut(e,this.parentNode);
										return true;
									});
			if (pin_img) {
				top.HEURIST.registerEvent(pin_img, "click", recDiv.getAttribute("bkmk_id") ? function(){} : top.HEURIST.search.addBookmark);
			}
			if (top.HEURIST.util.getDisplayPreference("double-click-action") == "edit") {
				top.HEURIST.registerEvent(recDiv, "dblclick", top.HEURIST.search.edit);
			}
			if (rec_title) {
					top.HEURIST.registerEvent(rec_title, "click", top.HEURIST.search.resultItemOnClick);
			}
			if (thumb_img) {
					top.HEURIST.registerEvent(thumb_img, "click", top.HEURIST.search.resultItemOnClick);
			}
		}

	},

	setResultStyle: function(style) {
		var searchWidth = top.HEURIST.util.getDisplayPreference("searchWidth");
		if (style == "two-col" && searchWidth < 180) {return;}
		top.HEURIST.util.setDisplayPreference("search-result-style", style);
		document.getElementById("results-level0").className = style;
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

	trimAllLinkTexts: function(level) {
		var results = $(".result_row", "#results-level" + level).get();
		for (var i=0; i < results.length; ++i) {
			var offset_width = Math.floor(0.9 * results[i].parentNode.offsetWidth) - 20;
			var days_bad = results[i].childNodes[6];
			var self_link = results[i].childNodes[5];
			top.HEURIST.search.trimLinkText(self_link, offset_width - (days_bad && days_bad.offsetWidth ? days_bad.offsetWidth : 0));
		}
	},

	renderNavigation: function() {
		// render the navigation necessary to move between pages of results

		if (! top.HEURIST.search.results  ||  top.HEURIST.search.results.totalQueryResultRecordCount == 0) {
			// no results ... don't display page navigation
			if (document.getElementById("page-nav"))
				document.getElementById("page-nav").innerHTML = "";
			document.getElementById("search-status").className = "noresult"
				+ (window.HEURIST.parameters["w"] == "bookmark" ? "" : " all");
			document.getElementById("resource-count").innerHTML = "";
			return;
		}

		var i;
		var totalRecordCount = top.HEURIST.search.results.totalQueryResultRecordCount;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		var pageCount = Math.ceil(totalRecordCount / resultsPerPage);
		var currentPage = top.HEURIST.search.currentPage;

		var firstRes = (currentPage*top.HEURIST.search.resultsPerPage+1);
		var lastRes = Math.min((currentPage+1)*resultsPerPage, totalRecordCount);


		if (totalRecordCount == 1) {
			document.getElementById("resource-count").innerHTML = "1 record";
		} else {
			document.getElementById("resource-count").innerHTML = firstRes + " - " + lastRes + " / " + totalRecordCount;
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

		if (firstRes > 1) {
			innerHTML += "<a id=prev_page href=# onclick=\"top.HEURIST.search.gotoPage('prev'); return false;\"><img src=\'../common/images/previous_page.png\'></a>";
		} else if (pageCount > 1) {	// don't display this, but let it affect the layout
			innerHTML += "<a id=prev_page href=# style=\"visibility: hidden;\"><img src=\'../common/images/previous_page.png\'></a>";
		}

		if (start != 1) {
			innerHTML += "<a href=# id=p0 onclick=\"top.HEURIST.search.gotoPage(0); return false;\">1</a> ... ";
		}
		for (i=start; i <= finish; ++i) {
			var cstring = (i == currentPage+1)? "class=active " : "";
			innerHTML += "<a href=# id=p"+(i-1)+" "+cstring+"onclick=\"top.HEURIST.search.gotoPage("+(i-1)+"); return false;\">"+i+"</a>";
		}
		if (finish != pageCount) {
			innerHTML += " ... <a href=# id=p"+(pageCount-1)+" onclick=\"top.HEURIST.search.gotoPage("+(pageCount-1)+"); return false;\">"+pageCount+"</a>";
		}
		if (lastRes < totalRecordCount) {
			innerHTML += "<a id=next_page href=# onclick=\"top.HEURIST.search.gotoPage('next'); return false;\"><img src=\'../common/images/next_page.png\'></a>";
		} else if (pageCount > 1) {
			innerHTML += "<a id=next_page href=# style=\"visibility: hidden;\"><img src=\'../common/images/next_page.png\'></a>";
			// innerHTML += "&nbsp;&nbsp;<span id=next_page>next&nbsp;page</span>";
		}

		if (pageCount > 1  &&  totalRecordCount < top.HEURIST.search.pageLimit) {
			innerHTML += "<a href=# onclick=\"top.HEURIST.search.showAll(); return false;\">show&nbsp;all</a>";
		}

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
		} else if (top.HEURIST.search.results.totalQueryResultRecordCount <= pageNum*top.HEURIST.search.resultsPerPage) {
			pageNum = Math.floor(top.HEURIST.search.results.totalQueryResultRecordCount / top.HEURIST.search.resultsPerPage);
		}

		if (pageNum != top.HEURIST.search.currentPage) {
			top.HEURIST.search.gotoResultPage(pageNum);
		}
	},

	showAll: function() {
		if (top.HEURIST.search.results.totalQueryResultRecordCount > 1000) {
			alert("There are too many search results to perform operations on.  Please refine your search first.");
			document.getElementById("select-all-checkbox").checked = false;
			return;
		}
		top.HEURIST.search.gotoResultPage(0, true);
	},

	calcShowSimpleSearch: function () {
		var q = $("#rectype-select").val();
		var fld = $("#field-select").val();
		var ctn = $("#input-contains").val();
		q = (q? (fld?q+" ": q ):"") + (fld?fld + (ctn?'"'+ctn+'"':""):"");
		if (q) {
			$("#q").val(q);
		}
	},

	createUsedRectypeSelector: function (useIDs) {
		var rectypes = top.HEURIST.rectypes;
		var rectypeValSelect = document.getElementById("rectype-select");
		rectypeValSelect.innerHTML = '<option value="" selected>Any rectype</option>';
		rectypeValSelect.onchange = function(){
			if (this.selectedIndex) {
				var rtID = $(this.options[this.selectedIndex]).attr("rectype");
				top.HEURIST.search.createRectypeFieldSelector(rtID,true);
			}else{
				top.HEURIST.search.createUsedDetailTypeSelector(true);
			}
			top.HEURIST.search.calcShowSimpleSearch();
		}
		// rectypes displayed in Groups by group display order then by display order within group
		for (var grpID in rectypes.groups){
			var grp = document.createElement("optgroup");
			var firstInGroup = true;
			grp.label = rectypes.groups[grpID].name;
			for (var recTypeID in rectypes.groups[grpID].types) {
				if (rectypes.groups[grpID].types[recTypeID] && rectypes.usageCount[recTypeID]) {
					if (firstInGroup){
						rectypeValSelect.appendChild(grp);
						firstInGroup = false;
					}
					var name = rectypes.names[recTypeID];
					var value =  "t:" + (useIDs ? recTypeID : '"'+name+'"');
					var opt = new Option(name,value);
					$(opt).attr("rectype",recTypeID);
					$(opt).attr("title","" + rectypes.usageCount[recTypeID] + " records");
					rectypeValSelect.appendChild(opt);
//					if (name == top.HEURIST.edit.record.rectype) {
//						rectypeValSelect.selectedIndex = rectypeValSelect.options.length-1;
//					}
				}
			}
		}
	},

	createUsedDetailTypeSelector: function (useIDs) {
		var detailTypes = top.HEURIST.detailTypes;
		var fieldValSelect = document.getElementById("field-select");
		fieldValSelect.innerHTML = '<option value="" selected>Any field</option>';
		fieldValSelect.onchange =  top.HEURIST.search.calcShowSimpleSearch;

		// rectypes displayed in Groups by group display order then by display order within group
		for (var grpID in detailTypes.groups){
			var grp = document.createElement("optgroup");
			var firstInGroup = true;
			grp.label = detailTypes.groups[grpID].name;
			for (var detailTypeID in detailTypes.groups[grpID].types) {
				if (detailTypes.groups[grpID].types[detailTypeID] && detailTypes.usageCount[detailTypeID]) {
					if (firstInGroup){
						fieldValSelect.appendChild(grp);
						firstInGroup = false;
					}
					var name = detailTypes.names[detailTypeID];
//					var name = detailTypes.names[detailTypeID] +" (detail:" + detailTypeID + ")";
					var value =  "f:" + (useIDs ? detailTypeID : '"'+name+'"') + ":";
					fieldValSelect.appendChild(new Option(name,value));
//					if (name == top.HEURIST.edit.record.rectype) {
//						rectypeValSelect.selectedIndex = rectypeValSelect.options.length-1;
//					}
				}
			}
		}
	},


	createRectypeFieldSelector: function (rt,useIDs) {
		var fields = top.HEURIST.rectypes.typedefs[rt].dtFields;
		var fieldValSelect = document.getElementById("field-select");
		fieldValSelect.innerHTML = '<option value="" selected>Any field</option>';
		fieldValSelect.onchange =  top.HEURIST.search.calcShowSimpleSearch;
		// rectypes displayed in Groups by group display order then by display order within group
		for (var dtID in fields){
			var name = fields[dtID][0] +" (" + dtID + ")";
			var value =  "f:" + (useIDs ? dtID : '"'+name+'"') + ":";
			fieldValSelect.appendChild(new Option(name,value));
//			if (name == top.HEURIST.edit.record.rectype) {
//				rectypeValSelect.selectedIndex = rectypeValSelect.options.length-1;
//			}
		}
	},

	gotoResultPage: function(pageNumber, all) {
		var results = top.HEURIST.search.results;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;

		if (all) {
			resultsPerPage = top.HEURIST.search.resultsPerPage = results.totalQueryResultRecordCount;
			pageNumber = 0;
		}

		if (pageNumber < 0  ||  results.totalQueryResultRecordCount <= pageNumber*resultsPerPage) {
			alert("No more results available");	// can't imagine how we'd get here ... evile haxxors?
			return;
		}

		top.HEURIST.search.currentPage = pageNumber;
		top.HEURIST.search.deselectAll();

		// Check if we've already loaded the given page ...
		var firstOnPage = pageNumber*resultsPerPage;
		var lastOnPage = Math.min((pageNumber+1)*resultsPerPage, results.totalQueryResultRecordCount)-1;
		if (results.infoByDepth[0].recIDs[firstOnPage]  &&  results.infoByDepth[0].recIDs[lastOnPage]) {
			// Hoorah ... all the data is loaded (well, the first and last entries on the page ...), so render it
			document.getElementById("results").scrollTop = 0;
			top.HEURIST.search.renderSearchResults(firstOnPage, lastOnPage);
			var viewerFrame = document.getElementById("viewer-frame");
			if (all) {
				top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectall");
			}else{
				top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-pagechange", "pageNum=" + (pageNumber +1));
			}
			top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange");
		}
		/*else {
			// Have to wait for the data to load
			document.getElementById("results-level0").innerHTML = "";
			document.getElementById("search-status").className = "loading";
		}*/

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

		if (targ.getAttribute("recID")) {
			var resultDiv = targ;
		}else if (targ.parentNode  &&  targ.parentNode.getAttribute("recID")) {
			var resultDiv = targ.parentNode;
		}else if (targ.parentNode.parentNode  &&  targ.parentNode.parentNode.getAttribute("recID")) {
			var resultDiv = targ.parentNode.parentNode;
		}else return;

		var recID = $(resultDiv).attr("recID");

		window.open(top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
					top.HEURIST.search.sid + "&recID="+recID+
					(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));

		return false;
	},


	selectedRecordIds: [],
	selectedRecordDivs: [],

	toggleResultItemSelect: function(recID,resultDiv,level) {
		if (typeof level === "undefined") {
			level = 0;
		}
		if (top.HEURIST.search.selectedRecordDivs[level] && top.HEURIST.search.selectedRecordDivs[level][recID]) { // was selected so deselect
			top.HEURIST.search.deselectResultItem(recID,level);
		}else if (recID) {
			if (!resultDiv) {
				resultDiv = $('div[class~=recordDiv]',$("#results-level"+level)).filter('div[recID='+ recID +']').get(0);
			}
			$(resultDiv).toggleClass("selected");
			$(".link"+recID,$("#results")).addClass("linkSelected");
			if (!top.HEURIST.search.selectedRecordDivs[level]) {
				top.HEURIST.search.selectedRecordDivs[level] = {};
			}
			top.HEURIST.search.selectedRecordDivs[level][recID] = resultDiv;
			if (!top.HEURIST.search.selectedRecordIds[level]) {
				top.HEURIST.search.selectedRecordIds[level] = [];
			}
			top.HEURIST.search.selectedRecordIds[level].push(recID);
		}
	},

	deselectResultItem: function(recID,level) {
		var resultDiv = top.HEURIST.search.selectedRecordDivs[level][recID];
		if (resultDiv) {
			delete top.HEURIST.search.selectedRecordDivs[level][recID];
			$(resultDiv).toggleClass("selected");
			$(".link"+recID,$("#results")).removeClass("linkSelected");
			for(var i = 0; i < top.HEURIST.search.selectedRecordIds[level].length; i++){
				if (top.HEURIST.search.selectedRecordIds[level][i] == recID) {
					top.HEURIST.search.selectedRecordIds[level].splice(i,1);
					break;
				}
			}
		}
	},

	resultItemMouseOver: function(e, targ) {
		//find the event and target element
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) {
				e.stopPropagation();
			}
		}

		if (! targ) {
			if (e.target) {
				targ = e.target;
			}else if (e.srcElement) {
				targ = e.srcElement;
			}
			if (targ.nodeType == 3) {
				targ = targ.parentNode;
			}
		}

		if (targ.getAttribute("recID")) {
			var resultDiv = targ;
		}else if (targ.parentNode  &&  targ.parentNode.getAttribute("recID")) {
			var resultDiv = targ.parentNode;
		}else{
			return;  // no target so we can't do anything
		}

		var recID = $(resultDiv).attr("recID");
		if(parseInt(recID)>=0) {
			if ($(resultDiv).hasClass("hilited")) return;
			$(resultDiv).addClass("hilited");
			$(".link"+recID,$("#results")).toggleClass("linkHilited");
		}
	},

	resultItemMouseOut: function(e, targ) {
		//find the event and target element
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) {
				e.stopPropagation();
			}
		}

		if (! targ) {
			if (e.target) {
				targ = e.target;
			}else if (e.srcElement) {
				targ = e.srcElement;
			}
			if (targ.nodeType == 3) {
				targ = targ.parentNode;
			}
		}

		if (targ.getAttribute("recID")) {
			var resultDiv = targ;
		}else if (targ.parentNode  &&  targ.parentNode.getAttribute("recID")) {
			var resultDiv = targ.parentNode;
		}else{
			return;  // no target so we can't do anything
		}

		var recID = $(resultDiv).attr("recID");
		if(parseInt(recID)>=0) {
			$(resultDiv).removeClass("hilited");
			$(".link"+recID,$("#results")).toggleClass("linkHilited");
		}
	},

	resultItemOnClick: function(e, targ) {
		//find the event and target element
		if (! e) e = window.event;

		if (e) {
			e.cancelBubble = true;
			if (e.stopPropagation) {
				e.stopPropagation();
			}
		}

		if (! targ) {
			if (e.target) {
				targ = e.target;
			}else if (e.srcElement) {
				targ = e.srcElement;
			}
			if (targ.nodeType == 3) {
				targ = targ.parentNode;
			}
		}

		if (targ.getAttribute("recID")) {
			var resultDiv = targ;
		}else if (targ.parentNode  &&  targ.parentNode.getAttribute("recID")) {
			var resultDiv = targ.parentNode;
		}else{
			return;  // no target so we can't do anything
		}

		var recID = $(resultDiv).attr("recID");
		var level = $(resultDiv).parent().attr("level");
		var results = top.HEURIST.search.results;

		if ( e.ctrlKey) { // CTRL + Click -  do multiselect functionality single item select and unselect from list
			top.HEURIST.search.toggleResultItemSelect(recID,resultDiv,level);
		}else if (e.shiftKey){//SHIFT + Click
		// find all items from current click item to last selected
			var recIdToIndexMap = results.infoByDepth[level].recIdToIndexMap;
			var selectedBibIds = top.HEURIST.search.selectedRecordIds[level];
			var lastSelectedIndex = recIdToIndexMap[selectedBibIds[selectedBibIds.length - 1]];
			var clickedIndex = recIdToIndexMap[recID];
			var newSelectedRecIdMap = {};
			var newSelectedRecIds = [];
			var recIDs = results.infoByDepth[level].recIDs,
				recID;
			// order this object so lasted selected remains last.
			if (clickedIndex < lastSelectedIndex){
				for(var i = clickedIndex; i <= lastSelectedIndex; i++) {
					recID = recIDs[i];
					if (!$(".recordDiv[recID="+recID,$("#results-level"+level)).hasClass("filtered")){
						newSelectedRecIdMap[recID] = true;
						newSelectedRecIds.push(recID);
					}
				}
			}else{
				for(var i = clickedIndex; i >= lastSelectedIndex; i--) {
					recID = recIDs[i];
					if (!$(".recordDiv[recID="+recID,$("#results-level"+level)).hasClass("filtered")){
						newSelectedRecIdMap[recID] = true;
						newSelectedRecIds.push(recID);
					}
				}
			}
		// for all selectedBibIds items not in new selection set, deselect item (which also removes it from selectedBibIds)
			for(var j =0;  j< selectedBibIds.length; j++) {
				if (!newSelectedRecIdMap[selectedBibIds[j]]){
					top.HEURIST.search.deselectResultItem(selectedBibIds[j],level);
					j--; //adjust for changed selectedBibIds
				}
			}
		//select all unselected new items and replace the selectedBidIds
			for(var newSelectedRecId in newSelectedRecIdMap){
				if (!top.HEURIST.search.selectedRecordDivs[level][newSelectedRecId]){
					top.HEURIST.search.toggleResultItemSelect(newSelectedRecId,null,level);
				}
			}
			top.HEURIST.search.selectedRecordIds[level] = newSelectedRecIds;
		}else{ //Normal Click -- assume this is just a normal click and single select;
			var clickedResultFound = false;
			var j=0;
			if (top.HEURIST.search.selectedRecordIds[level]) {
				while (top.HEURIST.search.selectedRecordIds[level].length && !clickedResultFound ||  clickedResultFound && top.HEURIST.search.selectedRecordIds[level].length > 1) {
					if (!clickedResultFound && top.HEURIST.search.selectedRecordIds[level][0] == recID) {
						clickedResultFound=true;
						j=1;
						continue;
					}
					top.HEURIST.search.deselectResultItem(top.HEURIST.search.selectedRecordIds[level][j],level);
				}
			}
			if (!clickedResultFound) { // clicked result was not previously selected so toggle to select
				top.HEURIST.search.toggleResultItemSelect(recID,resultDiv,level);
			}
		}

		//send selectionChange event
		var selectedRecIDs = top.HEURIST.search.getSelectedRecIDs().get();
        var recordFrame = document.getElementById("record-view-frame");
		if (!selectedRecIDs.length){
            recordFrame.src = top.HEURIST.basePath+"common/html/msgNoRecordsSelected.html";
            }else{
			recordFrame.src = top.HEURIST.basePath+"common/html/msgLoading.html";
			setTimeout(function(){recordFrame.src = top.HEURIST.basePath+"records/view/renderRecordData.php?recID="+recID+
							("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
							(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));},
							50);
            };
		var viewerFrame = document.getElementById("viewer-frame");
//		var mapFrame = document.getElementById("map-frame");
		var mapFrame3 = document.getElementById("map-frame3");
		var smartyFrame = document.getElementById("smarty-frame");

//        var sidebysideFrame = document.getElementById("sidebyside-frame");
//		sidebysideFrame.src = top.HEURIST.basePath+"viewers/sidebyside/sidebyside.html"+
//		("?db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
//						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));
		var ssel = "selectedIds=" + selectedRecIDs.join(",");
        top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);
//		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(mapFrame3.contentWindow.showMap,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(smartyFrame.contentWindow.showReps,"heurist-selectionchange",  ssel);

		return false;

	},

	addBookmark: function(e) {
		if (! e) e = window.event;
		var targ = null;
		if (e.target) targ = e.target;
		else if (e.srcElement) targ = e.srcElement;
		if (targ.nodeType == 3) targ = targ.parentNode;
		if (! targ) return;
		var row = targ.parentNode;
		if (! row.getAttribute("recID")) {
			row = row.parentNode;
		}
		if (! row) return;

		var action_fr = document.getElementById("i_action");
		var bib_ids_elt = action_fr.contentWindow.document.getElementById("bib_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bib_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = top.HEURIST.basePath+ "search/actions/actionHandler.php" +
				(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
			return;
		}

		bib_ids_elt.value = row.getAttribute("recID");
		action_elt.value = "bookmark_reference";
		action_elt.form.submit();
	},


	fillInSavedSearches: function(wg) {
		printSavedSearch = function(cmb, wg) {
			var sid = cmb[2];

			var active = (sid == window.HEURIST.parameters["sid"]);


			var innerHTML = "<nobr" + (active ? " id=activesaved" : "") + (cmb[4] || wg ? ">" : " class=bkmk>");
			if (active) {

				innerHTML += "<img class=\"saved-search-edit\" title=\"rename\" src=\"" +top.HEURIST.basePath+ "common/images/edit_pencil_9x11.gif\" align=absmiddle onclick=\"top.HEURIST.util.popupURL(window, '" +top.HEURIST.basePath+ "search/saved/saveSearchPopup.html?mode=rename&slabel=" + encodeURIComponent(cmb[0]) + "&sid="+sid+"&wg="+ (wg ? wg : 0) +(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "")+"');\">";
				innerHTML += "<img class=\"saved-search-edit\" title=\"delete\" src=\"" +top.HEURIST.basePath+ "common/images/delete6x7.gif\" align=absmiddle onclick=\"top.HEURIST.search.deleteSearch('"+ cmb[0] +"',"+ (wg ? wg : 0) +");\">";
			}
			innerHTML += "<div><a id='ss" + sid + "' href='" + (wg ? top.HEURIST.basePath : "")
					  + cmb[1] + "&amp;label=" + encodeURIComponent(cmb[0]) + "&amp;sid=" + sid + (top.HEURIST.database && top.HEURIST.database.name ? "&amp;db=" + top.HEURIST.database.name : "") + "'>" + cmb[0] + "</a></div>";

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
				(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");


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
				innerHTML += "<div class=saved-search-subsubheading><a href='" +top.HEURIST.basePath+ "search/usergroupHomepage.html?wg=" + w +(top.HEURIST.database && top.HEURIST.database.name ? "&amp;db=" + top.HEURIST.database.name : "")+ "'>Workgroup page</a></div>";
				innerHTML += "<div class=saved-search-subsubheading><a target=\"_blank\" href='" +top.HEURIST.basePath+ "viewers/blog/index.html?g=" + w + (top.HEURIST.database && top.HEURIST.database.name ? "&amp;db=" + top.HEURIST.database.name : "") +"'>"+top.HEURIST.workgroups[w].name+" Blog</a></div>";

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
						innerHTML += "<nobr><a href='"+top.HEURIST.basePath+"search/search.html?ver=1&w=all&q=tag:\"" + top.HEURIST.workgroups[w].name + "\\" + tags[j] + "\"&label=Tag+\"" + tags[j] + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "") + "\"'>" + tags[j] + "</a></nobr>";
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
			innerHTML += "<a href='"+top.HEURIST.basePath+"search/search.html?ver=1&w=bookmark&q=tag:\"" + kwd + "\"&label=Tag+\"" + kwd + "\""+ (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "") +"'>" + tags[i] + "</a> ";
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
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+ "search/saved/deleteSavedSearch.php?wg="+wg+"&label="+escape(name) + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""),
										 function(response) {
			if (response.deleted) {
				if (top.HEURIST.search) {
					top.HEURIST.search.removeSavedSearch(name, wg);
				}
			}
		});
	},

    launchAdvancedSearch: function() {
        var q = document.getElementById("q").value;
        var url = top.HEURIST.basePath+ "search/queryBuilderPopup.php?" + encodeURIComponent(q) + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
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

		if (! targ.href  && targ.parentNode && targ.parentNode.href) targ = targ.parentNode;	// sometimes get the span, not the link
		top.HEURIST.util.popupURL(top, targ.href);

		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		return false;
	},

	openSelected: function() {
			var p = top.HEURIST.parameters;
			var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs.length >= 500) {// maximum number of records ids 500
				alert("Selected record count is great than 500, opening the first 500 records!");
				recIDs = recIDs.slice(0,500);
			}
			var query_string = '?ver='+(p['ver'] || "") + '&w=all&q=ids:' +
				recIDs.join(",") +
				'&stype='+(p['stype'] || "") +
				'&db='+(p['db'] || "");
			window.open(top.HEURIST.basePath+'search/search.html'+query_string,"_blank");
	},

	mapSelected: function() {
			var p = top.HEURIST.parameters;
			var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs.length >= 500) {// maximum number of records ids 500
				alert("Selected record count is great than 500, mapping the first 500 records!");
				recIDs = recIDs.slice(0,500);
			}
			var query_string = '?ver='+(p['ver'] || "") + '&w=all&q=ids:' +
				recIDs.join(",") +
				'&stype='+(p['stype'] || "") +
				'&db='+(p['db'] || "");
			query_string = encodeURI(query_string);
			url = top.HEURIST.basePath+ "viewers/map/showGMapWithTimeline.html" + query_string;
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
			document.getElementById("map-frame").src = url;
	},
	closeMap: function() {
		var mapDiv = document.getElementById("mapDiv");
		mapDiv.parentNode.removeChild(mapDiv);
	},

	//ARTEM
	//listener of selection in search result - to refelect on map tab
	mapSelected3: function() {
			var p = top.HEURIST.parameters;
			var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs.length >= 500) {// maximum number of records ids 500
				alert("Selected record count is great than 500, mapping the first 500 records!");
				recIDs = recIDs.slice(0,500);
			}
			var query_string = 'ver='+(p['ver'] || "") + '&w=all&q=ids:' +
				recIDs.join(",") +
				'&stype='+(p['stype'] || "") +
				'&db='+(p['db'] || "");
			query_string = encodeURI(query_string);

			var mapframe = document.getElementById("map-frame3");
			if(mapframe.src){ //do not reload map frame
				if(mapframe.contentWindow.showMap){
					mapframe.contentWindow.showMap.reload(query_string);
				}
			}else{
			 	url = top.HEURIST.basePath+ "viewers/map/showMap.html?" + query_string;
				mapframe.src = url;
			}

	},

	//ARTEM
	//listener of selection in search result - to refelect on smarty tab
	smartySelected: function() {
			var p = top.HEURIST.parameters;
			var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs.length >= 500) {// maximum number of records ids 500
				alert("Selected record count is great than 500, reporting the first 500 records!");
				recIDs = recIDs.slice(0,500);
			}
			var query_string = 'ver='+(p['ver'] || "") + '&w=all&q=ids:' +
				recIDs.join(",") +
				'&stype='+(p['stype'] || "") +
				'&db='+(p['db'] || "");
			query_string = encodeURI(query_string);

			var repframe = document.getElementById("smarty-frame");
			if(repframe.src){ //do not reload  frame
				if(repframe.contentWindow.showReps){
					repframe.contentWindow.showReps.processTemplate(query_string);
				}
			}else{
			 	url = top.HEURIST.basePath+ "viewers/smarty/showReps.html?" + query_string;
				repframe.src = url;
			}
	},

	selectAll: function() {
		$("div.recordDiv:not(.filtered,.selected).lnk").each(function(i,recDiv) {
				var level = $(recDiv.parentNode).attr("level");
				var recID = $(recDiv).attr("recID");
				recDiv.className += " selected";
				if (!top.HEURIST.search.selectedRecordDivs[level]) {
					top.HEURIST.search.selectedRecordDivs[level] = {};
				}
				if (!top.HEURIST.search.selectedRecordIds[level]) {
					top.HEURIST.search.selectedRecordIds[level] = [];
				}
				top.HEURIST.search.selectedRecordDivs[level][recID] = recDiv;
				top.HEURIST.search.selectedRecordIds[level].push(recID);
			});

		var viewerFrame = document.getElementById("viewer-frame");
//		var mapFrame = document.getElementById("map-frame");
		var mapFrame3 = document.getElementById("map-frame3");
		var smartyFrame = document.getElementById("smarty-frame");

		var ssel = "selectedIds=" + top.HEURIST.search.getSelectedRecIDs().get().join(",");


		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);
//		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(mapFrame3.contentWindow.showMap,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(smartyFrame.contentWindow.showReps,"heurist-selectionchange",  ssel);

		return false;
	},

	selectAllFirstPage: function() {
		var bibs = document.getElementsByName("bib[]");
		for (var i=0; i < bibs.length; ++i) {
			bibs[i].checked = true;
			var recID = bibs[i].parentNode.getAttribute("recID");
//			top.HEURIST.search.bib_ids[recID] = true;
			top.HEURIST.search.selectItem(recID);
			var bkmk_id = bibs[i].parentNode.getAttribute("bkmk_id");
		}
		var viewerFrame = document.getElementById("viewer-frame");
//		var mapFrame = document.getElementById("map-frame");
		var mapFrame3 = document.getElementById("map-frame3");
		var smartyFrame = document.getElementById("smarty-frame");

		var ssel = "selectedIds=" + top.HEURIST.search.getSelectedRecIDs().get().join(",");

		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);
//		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(mapFrame3.contentWindow.showMap,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(smartyFrame.contentWindow.showReps,"heurist-selectionchange",  ssel);
		return false;
	},

	selectItem: function(recID) {
		resultDiv = $('div[class~=recordDiv]').filter('div[recID='+ recID +']').get(0);
		resultDiv.className += " selected";
		if (!top.HEURIST.search.selectedRecordDivs[0]) {
			top.HEURIST.search.selectedRecordDivs[0] = {};
		}
		if (!top.HEURIST.search.selectedRecordIds[0]) {
			top.HEURIST.search.selectedRecordIds[0] = [];
		}
		top.HEURIST.search.selectedRecordDivs[0][recID] = resultDiv;
		top.HEURIST.search.selectedRecordIds[0].push(recID);
	},

	selectAllPages: function() {
		// User has asked for ALL the results on ALL pages ...
		// Since the web interface only loads the first 100 results at first, we don't have all the results lying around.
		// However, we only need the bib_ids so we do a separate request.

		if (top.HEURIST.search.results.totalQueryResultRecordCount > 1000) {
			alert("There are too many search results to perform operations on.  Please refine your search first.");
			return;
		}
		top.HEURIST.util.bringCoverallToFront(true);

		var search = top.location.search || "?w=" + top.HEURIST.parameters["w"] + "&q=" + top.HEURIST.parameters["q"];
		top.HEURIST.util.sendRequest(top.HEURIST.basePath+ "search/getRecordIDsForSearch.php" + search + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""), function(xhr) {
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
				top.HEURIST.search.selectAllFirstPage();

				search_bib_ids = {};
				search_bkmk_ids = {};
				for (i=0; i < bibIDs.length; ++i) {
					var recID = bibIDs[i].recID;
					var bkmk_id = bibIDs[i].bkmk_id;
					search_bib_ids[recID] = true;
					if (bkmk_id)
						search_bkmk_ids[bkmk_id] = true;
				}
//				top.HEURIST.search.bib_ids = search_bib_ids;  // depricated by SAW 21/7/11
//				top.HEURIST.search.bkmk_ids = search_bkmk_ids;
//			}
		}
		else {	// no bib ids sent through
			alert("Problem contacting server");
		}
		top.HEURIST.util.sendCoverallToBack();
	},

	deselectAll: function() {
//		_tabView.set('activeIndex', 0); //set printView tab before deselecting to avoid mapping error
		if (!top.HEURIST.search.selectedRecordIds || top.HEURIST.search.selectedRecordIds.length == 0) return false;
		var level = 0;
		for (level; level < top.HEURIST.search.selectedRecordIds.length; level++) {
			while (top.HEURIST.search.selectedRecordIds[level].length != 0 ) {
				recID = top.HEURIST.search.selectedRecordIds[level][0];
				top.HEURIST.search.deselectResultItem(recID,level);
			}
		}
		var viewerFrame = document.getElementById("viewer-frame");
//		var mapFrame = document.getElementById("map-frame");
		var mapFrame3 = document.getElementById("map-frame3");
		var smartyFrame = document.getElementById("smarty-frame");

//		mapFrame.src = "";
		smartyFrame.src = "";

		var recordFrame = document.getElementById("record-view-frame");
		recordFrame.src = top.HEURIST.basePath+"common/html/msgNoRecordsSelected.html";
		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", "selectedIds=");
//		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange", "selectedIds=");
		top.HEURIST.fireEvent(mapFrame3.contentWindow.showMap, "heurist-selectionchange", "selectedIds=");
		top.HEURIST.fireEvent(smartyFrame.contentWindow.showReps, "heurist-selectionchange", "selectedIds=");

		return;
	},

	getSelectedRecIDs: function() {
		var recIDs = {};
		var selectedRecIDs = $(".recordDiv.selected.lnk:not(.filtered) ",$("#results")).map(function(i,recdiv){
					var recID = $(recdiv).attr("recID");
					if (!recIDs[recID] && parseInt(recID)>=0) {
						recIDs[recID] = true;
						return recID;
					}
				});
		return selectedRecIDs;
	},

	getSelectedBkmIDs: function() {
		var bkmIDs = {};
		var selectedBkmIDs = $(".recordDiv.selected:not(.filtered) ",$("#results")).map(function(i,recdiv){
					var bkmID = $(recdiv).attr("bkmk_id");
					if (!bkmIDs[bkmID] && parseInt(bkmID)>=0) {
						bkmIDs[bkmID] = true;
						return bkmID;
					}
				});
		return selectedBkmIDs;
	},

    notificationPopup: function() {
        var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
        if (recIDs_list.length == 0) {
            top.HEURIST.search.selectBookmarkMessage("for notification");
            return;
        }else{
            recIDs_list = recIDs_list.join(",");
        }
        top.HEURIST.util.popupURL(window, top.HEURIST.baseURL+ "search/actions/sendNotificationsPopup.php?bib_ids=\""+recIDs_list+"\"" + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
    },

    emailToDatabasePopup: function() {
        var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
        if (recIDs_list.length == 0) {
            top.HEURIST.search.selectBookmarkMessage("for sending");
            return;
        }else{
            recIDs_list = recIDs_list.join(",");
        }
        top.HEURIST.util.popupURL(window, top.HEURIST.baseURL+ "search/actions/emailToDatabasePopup.php?bib_ids=\""+recIDs_list+"\"" + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
    },

	addTagsPopup: function(reload) {
			top.HEURIST.search.addRemoveTagsPopup(reload);
	},
	removeTagsPopup: function(reload) {
		top.HEURIST.search.addRemoveTagsPopup(reload);
	},
	addRemoveTagsPopup: function(reload) {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		var bkmkIDs_list = top.HEURIST.search.getSelectedBkmIDs().get();
		var hasRecordsNotBkmkd = false;
		if (recIDs_list.length == 0  &&  bkmkIDs_list.length == 0) {
			//nothing selected
			alert("Select at least one record to add tags");
		}
		else if (recIDs_list.length > bkmkIDs_list.length) {
			// at least one unbookmarked record selected
			hasRecordsNotBkmkd = true;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/tags/updateTagsSearchPopup.html?show-remove" + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""), { callback: function(add, tags) {
			if (! tags) {
				if (reload) top.location.reload();
				return;
			}

			var action_fr = top.document.getElementById("i_action");
			var action_elt = action_fr.contentWindow.document.getElementById('action');
			var bib_ids_elt = action_fr.contentWindow.document.getElementById('bib_ids');
			var bkmk_ids_elt = action_fr.contentWindow.document.getElementById('bkmk_ids');
			var tagString_elt = action_fr.contentWindow.document.getElementById('tagString');
			var reload_elt = action_fr.contentWindow.document.getElementById('reload');

			if (! action_fr  ||  ! action_elt  ||  ! bkmk_ids_elt  ||  ! tagString_elt  ||  ! reload_elt) {
				alert("Problem contacting server - try again in a moment");
				if ( action_fr ) {
					action_fr.src = "../search/actions/actionHandler.php";
				}
				return;
			}

			action_elt.value = (add ? (hasRecordsNotBkmkd? "bookmark_and":"add") : "remove") + "_tags";
			tagString_elt.value = tags;
			bkmk_ids_elt.value = bkmkIDs_list.join(',');
			bib_ids_elt.value = recIDs_list.join(',');
			reload_elt.value = reload ? "1" : "";

			action_elt.form.submit();
		} });
	},
	addRemoveKeywordsPopup: function() {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to add / remove workgroup tags");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/tags/editUsergroupTagsPopup.html" + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : ""), { callback: function(add, wgTag_ids) {
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
			bib_ids_elt.value = recIDs_list.join(',');

			action_elt.form.submit();
		} });
	},

	setRatingsPopup: function() {
		var bkmkIDs_list = top.HEURIST.search.getSelectedBkmIDs().get();
		if (bkmkIDs_list.length == 0) {
			top.HEURIST.search.selectBookmarkMessage("to set ratings");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/actions/setRatingsPopup.php" + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : ""));
	},

	setWorkgroupPopup: function() {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to set workgroup ownership and visibility");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "records/permissions/setRecordOwnership.html" + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : ""), {
			callback: function(wg,viewable,hidden) {
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
				bib_ids_elt.value = recIDs_list.join(',');
				wg_elt.value = wg;
				vis_elt.value = hidden ? "hidden" : viewable ? "viewable" : "public";
                action_elt.form.submit();
			}
		});
	},

	addRelationshipsPopup: function() {
		if (top.HEURIST.search.getSelectedRecIDs().get().length === 0) {
			alert("Select at least one record to add relationships");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "search/actions/setRelationshipsPopup.html" +
						(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : ""),
						{callback : top.HEURIST.search.reloadSearch});
	},

	selectBookmarkMessage: function(operation) {
		alert("Select at least one bookmark " + operation
			+ (top.HEURIST.parameters["w"]=="all"
				? "\n(operation can only be carried out on bookmarked records, shown by a yellow star )"
				: ""));
	},

	addBookmarks: function() {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to bookmark");
			return;
		}

		var action_fr = document.getElementById("i_action");
		var bib_ids_elt = action_fr.contentWindow.document.getElementById("bib_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bib_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = top.HEURIST.basePath+ "search/actions/actionHandler.php" + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
			return;
		}

		bib_ids_elt.value = recIDs_list.join(",");
		action_elt.value = "bookmark_reference";
		action_elt.form.submit();
	},

	deleteBookmarks: function() {
		var bkmkIDs_list = top.HEURIST.search.getSelectedBkmIDs().get();
		if (bkmkIDs_list.length == 0) {
			alert("Select at least one bookmark to delete");
			return;
		} else if (bkmkIDs_list.length == 1) {
			if (! confirm("Do you want to delete one bookmark?\n(this ONLY removes the bookmark from your resources,\nit does not delete the bibliographic entry)")) return;
		} else {
			if (! confirm("Do you want to delete " + bkmkIDs_list.length + " bookmarks?\n(this ONLY removes the bookmarks from your resources,\nit does not delete the bibliographic entries)")) return;
		}

		var action_fr = document.getElementById("i_action");
		var bkmk_ids_elt = action_fr.contentWindow.document.getElementById("bkmk_ids");
		var action_elt = action_fr.contentWindow.document.getElementById("action");
		if (! bkmk_ids_elt  ||  ! action_elt) {
			alert("Problem contacting server - try again in a moment");
			action_fr.src = "actions/actionHandler.php" + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
			return;
		}

		bkmk_ids_elt.value = bkmkIDs_list.join(",");
		action_elt.value = "delete_bookmark";
		action_elt.form.submit();
	},

	deleteBiblios: function() {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to delete");
			return;
		}
		top.HEURIST.util.popupURL(window, "actions/deleteRecordsPopup.php?ids="+recIDs_list.join(",")+
					("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))));
	},

	renderCollectionUI: function() {
		if (typeof top.HEURIST.search.collectCount === "undefined") {
			top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php?fetch=1" +
					("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))),
					 function (results){
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
//		document.getElementById("collection-info").innerHTML = "<a href='?q=_COLLECTED_&amp;label=Collected'>Collected: " + top.HEURIST.search.collectCount + "</a>";
	},

	addRemoveCollectionCB: function(results) {
		var refresh = false;
		if (typeof results.count !== "undefined") {
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
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to bookmark");
			return;
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&add=" + recIDs_list.join(",") +
				("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))));
		top.HEURIST.search.deselectAll();
		document.getElementById("collection-label").className += "show-changed";
		top.HEURIST.search.showCollectionChange(true);
	},

	removeFromCollection: function() {
		var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
		if (recIDs_list.length == 0) {
			alert("Select at least one record to remove from collection basket");
			return;
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"search/saved/manageCollection.php", top.HEURIST.search.addRemoveCollectionCB, "fetch=1&remove=" + recIDs_list.join(",") +
				("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
					(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))));
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
															: "") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
	},

 	setHomeLink: function() {
		 document.getElementById("home-link").href = top.HEURIST.basePath + "index.html";
//		 document.getElementById("home-link").href = top.HEURIST.basePath + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
		 document.getElementById("dbSearch-link").href = top.HEURIST.basePath + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
		 document.getElementById("bookmarklet-link").innerHTML = (top.HEURIST.database && top.HEURIST.database.name ? "<b>"+top.HEURIST.database.name + " Bookmarklet </b>" : "<b>Bookmarklet</b>");
	},

	writeRSSLinks: function() {

		var link = document.createElement("link");
		link.rel = "alternate";
		link.type = "application/rss+xml";
		link.title = "RSS feed";
		link.href = "feed://"+window.location.host+top.HEURIST.basePath+"export/feeds/searchRSS.php"+(document.location.search ? document.location.search : "?q=tag:Favourites") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
		document.getElementsByTagName("head")[0].appendChild(link);
		document.getElementById("httprsslink").href += (document.location.search ? document.location.search : "?q=tag:Favourites" + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
	},

	toggleLegend: function() {
		var legend_box_elt = document.getElementById("legend-box");
		if (legend_box_elt) {
			legend_box_elt.parentNode.removeChild(legend_box_elt);
			return;
		}

		var results = top.HEURIST.search.results;

		if (! results) return;

		var currentPage = top.HEURIST.search.currentPage;
		var resultsPerPage = top.HEURIST.search.resultsPerPage;
		var firstOnPage = currentPage * resultsPerPage;
		var lastOnPage = Math.min((currentPage+1)*resultsPerPage, results.totalQueryResultRecordCount)-1;

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

		var iHTML = "",
			rtID;
		for (var rtID in results.infoByDepth[0].rectypes) {
			if (top.HEURIST.rectypes.names[rtID])
				iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/rectype-icons/"+top.HEURIST.database.id + "-" + rtID+".png'>"+top.HEURIST.rectypes.names[rtID]+"</li>";
		}

		document.getElementById("legend-box-list").innerHTML = iHTML;

		iHTML = "<hr><ul>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow.gif'><span>Resource is in </span><span style=\"font-weight: bold;\">my records</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-green-7.gif'><span>Belongs to workgroup 7<br></span><span style=\"margin-left: 25px;\">- read-only to others</span></li>";
		iHTML += "<li><img src='"+ top.HEURIST.basePath+"common/images/star-yellow-with-red-3.gif'><span>Belongs to workgroup 3<br></span><span style=\"margin-left: 25px;\">- hidden to others</span></li>";
		iHTML += "</ul>";
		iHTML += "<hr><div><a href=# onclick=\"top.HEURIST.util.popupURL(window, '"+ top.HEURIST.basePath+"common/php/showRectypeLegend.php"+(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "")+"' ); return false;\">show full legend</a></div>";
		legend_box_elt.innerHTML += iHTML;
	},

	toggleHelp: function() {
		top.HEURIST.util.setDisplayPreference("help", top.HEURIST.util.getDisplayPreference("help") == "hide" ? "show" : "hide");
	},

	fixDuplicates: function() {
		var bib_ids = top.HEURIST.search.getSelectedRecIDs().get();
		if (bib_ids.length === 0) {
			alert("Select at least one record to fix duplicates");
		} else {
			window.location.href = top.HEURIST.basePath+"admin/verification/combineDuplicateRecords.php?bib_ids=" + bib_ids.join(",") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
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
		window.location.href = top.HEURIST.basePath+"admin/verification/collectDuplicateRecords.php?"+ query_string + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
	},

	setupSearchPage: function() {
		top.HEURIST.registerEvent(window, "contentloaded", top.HEURIST.search.renderSearchPage);
		top.HEURIST.registerEvent(window, "resize", top.HEURIST.search.trimAllLinkTexts(0));
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

//		if (top.HEURIST.database.name === "") {
			var im_container = document.getElementById("publish-image-placeholder");
			var a = document.createElement("a");
				a.className = "logged-in-only";
				a.title = "Publish the current search results as formatted output which can be printed, saved or generated (live) in a web page";
				a.href = "#";
			a.onclick = function() {
				if (window.HEURIST.parameters["label"] && window.HEURIST.parameters["sid"]) {
					window.open(top.HEURIST.basePath+ "viewers/publish/publisher.php?pub_id=" + window.HEURIST.parameters["sid"] + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
				} else {
					top.HEURIST.util.popupURL(window, top.HEURIST.basePath + 'search/saved/saveSearchPopup.html?publish=yes' + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
				}
				return false;
			}
			a.appendChild(document.createTextNode("Publish"));
			im_container.appendChild(a);
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
			a.href = top.HEURIST.basePath+ 'viewers/publish/publisher.php?pub_id=' + param['sid'] +(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
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
		var content = document.getElementById("results-level0");
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

		var autoPopups = document.getElementsByName("auto-popup"),
			demark;
		for (var i=0; i < autoPopups.length; ++i) {
			autoPopups[i].onclick = top.HEURIST.search.autoPopupLink;
			demark = autoPopups[i].href.search(/\?/);
			if (demark === -1) {
				autoPopups[i].href += "?db=" + (top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "");
			}else{
				autoPopups[i].href += "&amp;db=" + (top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "");
			}
		}
		var searchForm = document.forms[0];
		var inputInstance = document.createElement("input");
		inputInstance.type = "hidden";
		inputInstance.id = "db";
		inputInstance.name = "db";
		inputInstance.value = top.HEURIST.database.name;
		searchForm.appendChild(inputInstance);

	}
};

top.HEURIST.fireEvent(window, "heurist-search-js-loaded");


//fancy dialog boxes
// constants to define the title of the alert and button text.
var ALERT_BUTTON_TEXT = "OK";
var args = "";
// over-ride the alert method only if this a newer browser.
// Older browser will see standard alerts
if(document.getElementById) {
	window.alert = function(txt) {
		createCustomAlert(txt,args);
		return true;
	}
}

function createCustomAlert(txt,args) {
	// shortcut reference to the document object
	d = document;

	// if the modalContainer object already exists in the DOM, bail out.
	if(d.getElementById("modalContainer")) return;

	// create the modalContainer div as a child of the BODY element
	mObj = d.getElementsByTagName("body")[0].appendChild(d.createElement("div"));
	mObj.id = "modalContainer";
	 // make sure its as tall as it needs to be to overlay all the content on the page
	mObj.className = "coverall";
	mObj.style.zIndex = "100000";

	// create the DIV that will be the alert
	alertObj = mObj.appendChild(d.createElement("div"));
	alertObj.id = "alertBox";

	// create a paragraph element to contain the txt argument
	msg = alertObj.appendChild(d.createElement("p"));
	msg.innerHTML = txt;

	// create an anchor element to use as the confirmation button.
	btn = alertObj.appendChild(d.createElement("button"));
	btn.id = "closeBtn";
	btn.appendChild(d.createTextNode(ALERT_BUTTON_TEXT));
	btn.href = "#";
	// set up the onclick event to remove the alert when the anchor is clicked
	btn.onclick = function() {
		if (args = "map") _tabView.set('activeIndex', 0);
		removeCustomAlert();
		return ; }
}

// removes the custom alert from the DOM
function removeCustomAlert() {
	document.getElementsByTagName("body")[0].removeChild(document.getElementById("modalContainer"));return;
}

// layout

	var Dom = YAHOO.util.Dom,
		Event = YAHOO.util.Event;

	var viewerTabIndex = top.HEURIST.util.getDisplayPreference("viewerTab");

	Event.onDOMReady(function() {

		var leftWidth = top.HEURIST.util.getDisplayPreference("leftWidth");
		var oldLeftWidth = top.HEURIST.util.getDisplayPreference("oldLeftWidth");
		if (!leftWidth || !oldLeftWidth) {
			leftWidth = 180;
			}else if (top.HEURIST.util.getDisplayPreference("sidebarPanel") == "closed"){
			leftWidth = oldLeftWidth;
			};
		var searchWidth = top.HEURIST.util.getDisplayPreference("searchWidth");
		var oldSearchWidth = top.HEURIST.util.getDisplayPreference("oldSearchWidth");
		if (!searchWidth || !oldSearchWidth) {
			searchWidth = 180;
			}else if (top.HEURIST.util.getDisplayPreference("applicationPanel") == "closed"){
			searchWidth = oldSearchWidth;
			};
		var appPanelButton = document.getElementById("appPanelButton");
		var navButton = document.getElementById("navButton");
		var tabBar = document.getElementById("tabbar");
		var searchTable = document.getElementById("search");

		var layout = new YAHOO.widget.Layout({
			units: [
				{ position: 'top', height: 95, body: 'masthead', header: '', gutter: '0 10px', collapse: false, resize: false },
				{ position: 'bottom', height: 20, resize: false, body: 'footer', gutter: '0 10px 10px 10px', collapse: false },
				{ position: 'left', width: leftWidth, resize: true, useShim: true, body: 'sidebar', gutter: '0 0 0 10px', collapse: false, close: false, collapseSize: 0, scroll: false, animate: false, minWidth:150, maxWidth:300},
				{ position: 'center', body: 'page', gutter: '0', minWidth: 400 },
				{ position: 'right', width: searchWidth, resize: true, useShim: true, body: 'page-right', gutter: '0 10px 0 0', collapse: false, close: false, collapseSize: 0, scroll: false, animate: false, minWidth:150},
			]
		});


		var setWidths = function() {
			var leftPanelWidth = layout.getSizes().left.w;
			var rightPanelWidth = layout.getSizes().right.w;
			var centerPanelWidth = layout.getSizes().center.w;
			var maxRightWidth = centerPanelWidth + rightPanelWidth -180;
			top.HEURIST.util.setDisplayPreference("leftWidth", leftPanelWidth);
			top.HEURIST.util.setDisplayPreference("searchWidth", rightPanelWidth);
			navButton.style.width = leftPanelWidth-11;

			if (navButton.style.width == "20px" || appPanelButton.style.width == "20px"){
				tabBar.style.width = centerPanelWidth-29;
			}else if (navButton.style.width == "20px" && appPanelButton.style.width == "20px"){
				tabBar.style.width = centerPanelWidth-49;
			}else{
				tabBar.style.width = centerPanelWidth-8;
			};

			searchTable.style.paddingLeft = (leftPanelWidth);
			if (centerPanelWidth <= 180) {
				layout.getUnitByPosition('right').set("width",maxRightWidth);
				top.HEURIST.util.setDisplayPreference("searchWidth", maxRightWidth);
			}
			var currentStyle = top.HEURIST.util.getDisplayPreference("search-result-style");
			var twocollink = document.getElementById("result-style-twoCol");
			if (centerPanelWidth < 180 && currentStyle == "two-col") {
					 document.getElementById("results-level0").className = "list"; //temporarliy changes 2-col to list
					 };
				if (centerPanelWidth > 180) {
					 twocollink.className = twocollink.className.replace(" disabled","");
					 document.getElementById("results-level0").className = currentStyle;
					 };
				if (centerPanelWidth < 180 && twocollink.className !== " disabled") {
					 twocollink.className += " disabled";
					 };
		};

		layout.on('resize',setWidths);
		layout.render();

		if (top.HEURIST.util.getDisplayPreference("sidebarPanel") == "closed"){
					layout.getUnitByPosition('left').collapse();
					navButton.className +=" closed";
					searchTable.style.paddingLeft = "5px";
					navButton.style.width = "20px";
					navButton.title = "Show Navigation Panel";
					tabBar.style.width = layout.getSizes().center.w - 29;
					};
		if (top.HEURIST.util.getDisplayPreference("applicationPanel") == "closed"){
					layout.getUnitByPosition('right').collapse();
					appPanelButton.className +=" closed";
					appPanelButton.title = "Show Applications";
					};
		Event.on('navButton', 'click', function(ev) {
			Event.stopEvent(ev);
			if (top.HEURIST.util.getDisplayPreference("sidebarPanel") == "open"){
				var oldLeftPanelWidth = layout.getSizes().left.w;
					top.HEURIST.util.setDisplayPreference("oldLeftWidth", oldLeftPanelWidth);
				layout.getUnitByPosition('left').collapse();
				top.HEURIST.util.setDisplayPreference("sidebarPanel","closed");
				navButton.className +=" closed";
				navButton.style.width = "20px";
				searchTable.style.paddingLeft = "5px";
				navButton.title = "Show Navigation Panel";
				tabBar.style.width = layout.getSizes().center.w - 29;
				layout.resize();
			}else{
				layout.getUnitByPosition('left').expand();
				top.HEURIST.util.setDisplayPreference("sidebarPanel","open");
				navButton.className = navButton.className.replace(" closed", "");
				searchTable.style.paddingLeft = top.HEURIST.displayPreference.leftWidth;
				navButton.title = "Hide Navigation Panel";
			}
		});
		Event.on('appPanelButton', 'click', function(ev) {
				Event.stopEvent(ev);
				if (top.HEURIST.util.getDisplayPreference("applicationPanel") == "open"){
				var oldSearchWidth = layout.getSizes().center.w;
					top.HEURIST.util.setDisplayPreference("oldSearchWidth", oldSearchWidth);
				layout.getUnitByPosition('right').collapse();
				top.HEURIST.util.setDisplayPreference("applicationPanel","closed");
				appPanelButton.className +=" closed";
				tabBar.style.width = layout.getSizes().center.w - 26;
				appPanelButton.style.width = "20px";
				appPanelButton.title = "Show Applications";
			}else{
				layout.getUnitByPosition('right').expand();
				top.HEURIST.util.setDisplayPreference("applicationPanel","open");
				appPanelButton.className = appPanelButton.className.replace(" closed", "");
				appPanelButton.title = "Hide Applications";
				tabBar.style.width = layout.getSizes().center.w-8;
				if (top.HEURIST.util.getDisplayPreference("sidebarPanel") == "closed"){
					tabBar.style.width = layout.getSizes().center.w - 29;
				};
			}

		});





		Event.on('resetLayout', 'click', function(ev) {
			Event.stopEvent(ev);
					if (top.HEURIST.util.getDisplayPreference("sidebarPanel") != "open"){
						layout.getUnitByPosition('left').expand();
						top.HEURIST.util.setDisplayPreference("sidebarPanel","open");
						navButton.className = document.getElementById("navButton").className.replace(" closed", "");
						navButton.title = "Show Navigation Panel";
						};
					layout.getUnitByPosition('left').set("width", 180);
					layout.getUnitByPosition('left').resize();
					top.HEURIST.util.setDisplayPreference("leftWidth", 180);
					top.HEURIST.util.setDisplayPreference("sidebarPanel","open");
				});

	});

	_tabView = new YAHOO.widget.TabView('applications', { activeIndex: viewerTabIndex });
	//if (viewerTabIndex == 2){top.HEURIST.search.mapSelected()} //initialises map
	if (Number(viewerTabIndex) === 2){top.HEURIST.search.mapSelected3()} //initialises new map
	else if (Number(viewerTabIndex) === 3){top.HEURIST.search.smartySelected()}; //initialises smarty repsystem

	var handleActiveTabChange = function(e) {
		var currentTab = _tabView.getTabIndex(_tabView.get('activeTab'));
		top.HEURIST.util.setDisplayPreference("viewerTab", currentTab);
	};
	_tabView.addListener('activeTabChange',handleActiveTabChange);
	_tabView.getTab(viewerTabIndex);
	//if (viewerTabIndex == 2){top.HEURIST.search.mapSelected()} //initialises map
	if (Number(viewerTabIndex) === 2){top.HEURIST.search.mapSelected3()} //initialises new map
	else if (Number(viewerTabIndex) === 3){top.HEURIST.search.smartySelected()}; //initialises smarty reports

	_tabView.addListener('activeTabChange',handleActiveTabChange);


