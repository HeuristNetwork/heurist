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

var _TAB_MAP = 1,
	_TAB_SMARTY = 2,
	_TAB_SPECIAL = 3;

top.HEURIST.search = {
	VERSION: "1",

	pageLimit: 5000,
	resultsPerPage: top.HEURIST.util.getDisplayPreference("results-per-page"),
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
		top.HEURIST.search.currentPage = 0;
		top.HEURIST.search.resultsPerPage = top.HEURIST.util.getDisplayPreference("results-per-page");
		top.HEURIST.search.clearResultRows();
		top.HEURIST.search.clearRelatesRows();
		top.HEURIST.search.reloadSearch();
		top.HEURIST.search.setBodyClass();
	},

	searchNotify: function(results) {
		top.HEURIST.search.results = {
			recSet: {},
			infoByDepth: [ {
				count : 0,
				recIdToIndexMap : {}, //????? artem:what's this
				recIDs : [],
				rectypes : {}
				}],
			recSetCount: results.totalRecordCount,
			totalQueryResultRecordCount: results.totalRecordCount,
			querySid : results.sid
		};

		if (top.HEURIST.search.results.totalQueryResultRecordCount == 0) {
			top.HEURIST.registerEvent(window, "load", function(){
						top.HEURIST.search.currentPage = 0;
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

		var loadedRecCount = top.HEURIST.search.results.infoByDepth[0].count;
		// check if we've fully loaded the page we were expecting to render
		var firstOnPage = top.HEURIST.search.currentPage*top.HEURIST.search.resultsPerPage;
		var lastOnPage = Math.min(top.HEURIST.search.results.totalQueryResultRecordCount,
										(top.HEURIST.search.currentPage + 1)*top.HEURIST.search.resultsPerPage)-1;
		if (startIndex <= lastOnPage &&
			top.HEURIST.search.results.infoByDepth[0].recIDs[firstOnPage] &&
			top.HEURIST.search.results.infoByDepth[0].recIDs[lastOnPage]) {
			// should have some sort of "searchready" event -- FIXME as part of holistic event-based solution
			top.HEURIST.registerEvent(window, "contentloaded", function() {
				top.HEURIST.search.currentPage = 0;
				top.HEURIST.search.renderSearchResults(firstOnPage, lastOnPage);
				top.HEURIST.search.renderNavigation();
				top.HEURIST.search.updateMapOrSmarty();
			});
			top.HEURIST.registerEvent(window, "load", function() {
				document.getElementById("viewer-frame").src = top.HEURIST.basePath+ "viewers/printview/index.html" +
					(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
			});
		}
		// when the last result is loaded start loading related if user wants it or it's impled from a filter in the URL
		if (top.HEURIST.search.results.infoByDepth[0].count == top.HEURIST.search.results.totalQueryResultRecordCount &&
			(top.HEURIST.util.getDisplayPreference("loadRelatedOnSearch") === "true" ||
				top.HEURIST.parameters['rtfilters'] ||
				top.HEURIST.parameters['prtfilters'] ||
				top.HEURIST.parameters['relfilters'] )){
				top.HEURIST.search.loadRelatedResults();
		}
	},

	loadSearchLocation: function(strLocation) {
		var temp = strLocation + ("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));
		window.location.href = temp;
	},

	loadFavourites: function(strLocation) {
		var temp = strLocation + top.HEURIST.util.getDisplayPreference("favourites") + ("&db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));
		window.location.href = temp;
	},

	reloadFromParameters: function() {
		var temp = top.HEURIST.basePath+"search/search.html?" +
			("w=" + encodeURIComponent(top.HEURIST.parameters["w"])) + "&" +
			("stype=" + (top.HEURIST.parameters["stype"] ? encodeURIComponent(top.HEURIST.parameters["stype"]) : "")) + "&" +
			("ver=" + top.HEURIST.search.VERSION) + "&" +
			("q=" + encodeURIComponent(top.HEURIST.parameters["q"])+
			(window.HEURIST.database && window.HEURIST.database.name ? "&db=" + window.HEURIST.database.name : ""));
		window.location.href = temp;
	},

	reloadSearch: function() {
		top.HEURIST.parameters["q"] = document.getElementById("q").value;
		top.HEURIST.parameters["w"] = document.getElementById("w-input").value;
		if (top.HEURIST.parameters["label"]) {
			delete top.HEURIST.parameters["label"];
		}
		if (!$("#simple-search").hasClass("collapsed")) {
			$("#simple-search").toggleClass("collapsed");
			$("div.simplesearch").toggleClass("collapsed");

		}
		if (!$("#searchButtons").hasClass("collapsed")) {
			$("#searchButtons").toggleClass("collapsed");
			$("div.searchButtonsTrigger").toggleClass("collapsed");
		}
		top.HEURIST.search.loadSearch();
	},

	loadSearch: function() {
		if (! top.HEURIST.parameters["q"]) {
			top.HEURIST.registerEvent(window, "contentloaded",
			function() { document.getElementById("search-status").className = "noquery"; });
			return;
		}
		var iframeElt = document.createElement("iframe");
		var params = top.HEURIST.parameters;
		if (params["label"]) {
			document.getElementById("active-label").innerHTML = params["label"];
			if (params["label"] == "Favourites") {
				document.getElementById("results-message").innerHTML = "Click on a record to view details. CTRL click to select multiple records";
				document.getElementById("results-message").style.display = "block";
			}
		} else if (params['q']){
			document.getElementById("active-label").innerHTML = params['q'];
		}else{
			document.getElementById("active-label").innerHTML = "Search results";
		}
		iframeElt.style.border = "0";
		iframeElt.style.width = "0";
		iframeElt.style.height = "0";
		iframeElt.frameBorder = 0;
		iframeElt.style.position = "absolute";
		iframeElt.src = top.HEURIST.basePath+"search/getResultsPageAsync.php?" +
			("w=" + encodeURIComponent(params["w"])) + "&" +
			("stype=" + (params["stype"] ? encodeURIComponent(params["stype"]) : "")) + "&" +
			("ver=" + top.HEURIST.search.VERSION) + "&" +
			("q=" + encodeURIComponent(params["q"]))+
			("&db=" + (params['db'] ? params['db'] :
					(window.HEURIST.database && window.HEURIST.database.name ? window.HEURIST.database.name : "")));

		document.body.appendChild(iframeElt);
	},

	loadSearchParameters: function() {
		var params = top.HEURIST.parameters;

		// set search type
		if (top.HEURIST.is_logged_in()  &&  params["w"] === "bookmark") {
			params["w"] = "bookmark";
		} else if (top.HEURIST.is_logged_in()  && params["w"] === "" &&  top.HEURIST.util.getDisplayPreference("defaultMyBookmarksSearch") == "true") {
			params["w"] = "bookmark";
		} else {
			params["w"] = "all";
		}

		if (! params["q"]) {
			params["q"] = "";
		}

		// set search version
		params["ver"] = top.HEURIST.search.VERSION;

		if (params["view"]) {
			top.HEURIST.search.setResultStyle(params["view"]);
		}

		if (! top.suppressAutoSearch) top.HEURIST.search.loadSearch();
	},

	loadRelatedResults: function() {
		var params = top.HEURIST.parameters;
		var URL = top.HEURIST.basePath+"search/getRelatedResultSet.php?" +
			("w=" + (params["w"] ? encodeURIComponent(params["w"]) : "all")) +
			(params["stype"] ? "&stype=" +encodeURIComponent(params["stype"]) : "") +
			("&ver=" + top.HEURIST.search.VERSION) +
			("&q=" + encodeURIComponent(params["q"])) +
			("&db=" + (params['db'] ? params['db'] :
						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : ""))) +
			"&depth=3";
		top.HEURIST.registerEvent(window, "heurist-related-recordset-loaded",
					function (evt) {
									var i,layout,style,
										maxFilterDepth=0,
										maxDepth=0;
										top.HEURIST.search.addResultLevelLinks(0); // now we know the links add the tags to top level
										top.HEURIST.search.loadLevelFilter(0);
									if (top.HEURIST.parameters &&
										(	top.HEURIST.parameters['rtfilters'] ||
											top.HEURIST.parameters['ptrfilters'] ||
											top.HEURIST.parameters['relfilters'] ||
											(top.HEURIST.parameters['layout'] &&
												top.HEURIST.parameters['layout'].indexOf('srch') != -1 ))) {
											//calculate max depth of filters
											if (top.HEURIST.parameters['layout'] &&
												top.HEURIST.parameters['layout'].indexOf('srch:') != -1 ) {
												//
												layout = top.HEURIST.parameters['layout'].match(/srch:([^\|]+)/);
												layout = layout[1].split("-");
											}
											var rtf = top.HEURIST.parameters['rtfilters'],
												ptrf = top.HEURIST.parameters['ptrfilters'],
												relf = top.HEURIST.parameters['relfilters'];
											for (i=1; i<=3; i++) {
												if (rtf && rtf.search('"' + i + '":') != -1 ||
													ptrf && ptrf.search('"' + i + '":') != -1 ||
													relf && relf.search('"' + i + '":') != -1) {
														maxFilterDepth = i;
													}
											}
											//expand to the the max defined by the filter or layout  or by the data
											maxDepth = Math.min(Math.max((layout? layout.length -1 : 0), maxFilterDepth), top.HEURIST.search.results.infoByDepth.length-1);
											//for each level loadLevelFilter Menu, loadRelatedLevel results, expand level, filterRelated results with noPush
											if (layout) {
												top.HEURIST.search.setResultStyle(layout[0][0],0);
											}
											top.HEURIST.search.filterRelated(0,true,true);
											for (i=1; i<=maxDepth; i++) {
												var depthInfo = top.HEURIST.search.results.infoByDepth[i];
												//loadLevelFilter Menu
												top.HEURIST.search.loadLevelFilter(i,(layout && layout[i] && layout[i][0] ? layout[i][0]: null));
												//loadRelatedLevel results
												top.HEURIST.search.loadRelatedLevel(i,true);
												document.getElementById("showrelated" + i).className += " loaded";
												//filterRelated results with noPush
												top.HEURIST.search.filterRelated(i,true,true);
												// expand level
												if (!(layout && layout[i][1] && layout[i][1].toLowerCase() == "c")) {
													$("#results-level"+i).removeClass('collapsed');// remove collapsed class on parent
													$("#showrelated" + i).html("<a style='background-image:url(../common/images/heading_saved_search.png)' onclick='top.HEURIST.search.toggleRelated(" +i + ")' href='#'>Level "+i+" Related Records </a><span class=\"relatedCount\">"+depthInfo.count+"</span><span class=\"selectedCount\" id=\"selectedCount-"+i+"\"></span>");
												}else{// make sure collapsed class is on parent
													if ($("#results-level"+i).hasClass('collapsed')){
														$("#results-level"+i).addClass('collapsed');
													};
													$("#showrelated" + i).html("<a onclick='top.HEURIST.search.toggleRelated(" +i + ")' href='#'>Level "+i+" Related Records </a><span class=\"relatedCount\">"+depthInfo.count+"</span><span class=\"selectedCount\" id=\"selectedCount-"+i+"\"></span>");
												}
											}
											top.HEURIST.search.setSelectedCount();
											if (maxDepth < top.HEURIST.search.results.infoByDepth.length - 1 &&
												top.HEURIST.search.results.infoByDepth[maxDepth+1].count > 0){
												top.HEURIST.search.loadLevelFilter(maxDepth+1);
											}
											//clear the filters
											if (rtf) {
												delete top.HEURIST.parameters['rtfilters'];
											}
											if (ptrf) {
												delete top.HEURIST.parameters['ptrfilters'];
											}
											if (relf) {
												delete top.HEURIST.parameters['relfilters'];
											}
											if (layout) {
												delete top.HEURIST.parameters['layout'];
											}
										}else{
											// if filters then load related to depth..
											if (top.HEURIST.search.results.infoByDepth.length >1 &&
													top.HEURIST.search.results.infoByDepth[1].count > 0) {
												top.HEURIST.search.loadLevelFilter(1);
											}
											top.HEURIST.search.filterRelated(0);
										}
					});
		top.HEURIST.util.getJsonData(URL,
				function(related) {	// callback function to process related record results into HEURIST search results
					var results = top.HEURIST.search.results,
						recID,i,j;
					if (related && related.count >= results.totalQueryResultRecordCount) { //check that we have related records
						results.recSetCount = related.count;
						results.params = related.params;
						for (recID in related.recSet){
							var recInfo = related.recSet[recID];
							if (!results.recSet[recID]){
								results.recSet[recID] = recInfo;
							}else if(recInfo.depth == 0){// it's possible that related levels are linked and have added link info here
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
						if (related.infoByDepth.length > 1) {// if we have more that just level 0 we need to update the levels
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

	loadLevelFilter: function(level,style){	// it's important that this function be called after results are completely loaded
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
			document.getElementById("results").appendChild(resultsDiv);
		}else{
			resultsDiv =resultsDiv.get(0);
		}
		if (!style) {
			style = top.HEURIST.util.getDisplayPreference("search-result-style"+level); // get startup prefernce style
		}
		if (level > 0 ) {
			resultsDiv.className = " related-results";
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
		if(level==0) {//create count button
			var showSearchResults = document.createElement("div");
			showSearchResults.innerHTML = "<a title=\"Click to toggle results\" href='#' onclick=top.HEURIST.search.toggleResults()>Search Results <span class=\"relatedCount\">"+top.HEURIST.search.results.totalQueryResultRecordCount+"</span></a><span id=\"selectedCount-0\"></span>";
			showSearchResults.className = "showrelated";
			filterDiv.appendChild(showSearchResults);
		}
		if(level>0){// create load-show-hide  with count banner for a related level
			var showRelatedMenuItem = document.createElement("div");
			showRelatedMenuItem.innerHTML = "<a title=\"Click to show "+depthInfo.count+" records related to the records listed above\" href='#' onclick=top.HEURIST.search.toggleRelated("+level+")>Load Level "+level+" Related Records <span class=\"relatedCount\">"+depthInfo.count+"</span></a>";
			showRelatedMenuItem.id = "showrelated"+level;
			showRelatedMenuItem.className = "showrelated level" + level;
			filterDiv.appendChild(showRelatedMenuItem);
			if (!$(resultsDiv).hasClass('collapsed')) {
				resultsDiv.className += " collapsed";
			}
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

		// check for filtering from the uri
		var rtfilter = top.HEURIST.parameters['rtfilters'] ? top.YAHOO.lang.JSON.parse(top.HEURIST.parameters['rtfilters']) : null,
			ptrfilter = top.HEURIST.parameters['ptrfilters'] ? top.YAHOO.lang.JSON.parse(top.HEURIST.parameters['ptrfilters']) : null,
			relfilter = top.HEURIST.parameters['relfilters'] ? top.YAHOO.lang.JSON.parse(top.HEURIST.parameters['relfilters']) : null;
		var filterRtIDs = (rtfilter && rtfilter[level] ? rtfilter[level] : null),
			filterPtrDtIDs = (ptrfilter && ptrfilter[level] ? ptrfilter[level] : null),
			filterRelTrmIDs = (relfilter && relfilter[level] ? relfilter[level] : null);

		// create  view Style menu
		var viewStyleMenu = document.createElement("li");
		viewStyleMenu.className = "view";
		viewStyleMenu.innerHTML = '<span>View</span>'+
									'<ul>'+
										'<li><a href="#" class="list" title="View results as a list" onClick="top.HEURIST.search.setResultStyle(\'list\','+level+');">1 column</a></li>'+
										'<li><a href="#" class="two-col" title="View results as 2 column list" onClick="top.HEURIST.search.setResultStyle(\'two-col\','+level+');">2 columns</a></li>'+
										'<li><a href="#" class="icons" title="View results as icons" onClick="top.HEURIST.search.setResultStyle(\'icons\','+level+');">Icons</a></li>'+
										'<li><a href="#" class="thumbnails" title="View results as thumbnails" onClick="top.HEURIST.search.setResultStyle(\'thumbnails\','+level+');">Thumbnails</a></li>'+
									'</ul>';
		filterMenu.appendChild(viewStyleMenu);
		top.HEURIST.search.setResultStyle(style,level);

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
				li.rectype = rtID = rtInfo[1];
				$(li).attr("rectype", rtInfo[1]);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.toggleRectypeFilter(this.parentNode,"+level+","+rtID+")>" + rtInfo[0] + "</a>";
				li.className = (!filterRtIDs || filterRtIDs &&
													(filterRtIDs.indexOf(parseInt(rtID)) != -1 ||
														filterRtIDs.indexOf(rtID) != -1)? "checked ":'') + "level"+level;
				rectypeList.appendChild(li);
			}
			rectypeMenuItem.appendChild(rectypeList);
			filterMenu.appendChild(rectypeMenuItem);
		}

		//create ptrtype filter menu
		if (levelPtrTypes){	// this is skipped for level 0 since there are no links from an upper level since level 0 is the top level
			var j;
			var ptrNames = [];
			var ptrDirection = {};
			var dtlID;
			if (levelPtrTypes.fwd) {
				$.each(levelPtrTypes.fwd, function(dtlID,linkSetIDs) {
					if (top.HEURIST.detailTypes.names[dtlID]) {
						ptrNames.push("" + top.HEURIST.detailTypes.names[dtlID]+ ":" + dtlID);
						ptrDirection[dtlID] = "down";//towards increased depth
					}
				});
			}
			if (levelPtrTypes.rev) {
				$.each(levelPtrTypes.rev, function(dtlID,linkSetIDs) {
					if (top.HEURIST.detailTypes.names[dtlID]) {
						if (ptrDirection[dtlID]) {//name exist from forward
							ptrDirection[dtlID] = "both";//both directions
						}else{
							ptrNames.push("" + top.HEURIST.detailTypes.names[dtlID]+ ":" + dtlID);
							ptrDirection[dtlID] = "up";//towards decreased depth
						}
					}
				});
			}
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
				li.ptrtype = dtlID = ptrInfo[1];
				$(li).attr("ptrtype", dtlID);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.togglePtrtypeFilter(this.parentNode,"+level+","+dtlID+")>" +
								(ptrDirection[dtlID] == "both"? "↕": (ptrDirection[dtlID] == "up"?"↑" :"↓")) + ptrInfo[0] + "</a>";
				li.className = "checked level"+level;
				li.className = (!filterPtrDtIDs || filterPtrDtIDs &&
													(filterPtrDtIDs.indexOf(parseInt(dtlID)) != -1 ||
														filterPtrDtIDs.indexOf(dtlID) != -1)? "checked ":'') + "level"+level;
				ptrtypeList.appendChild(li);
			}
			ptrtypeMenuItem.appendChild(ptrtypeList);
			filterMenu.appendChild(ptrtypeMenuItem);
		}

		//create reltype filter menu
		if (levelRelTypes){	// this is skipped for level 0 since there are no links from an upper level since level 0 is the top level
			var j;
			var relNames = [];
			var relDirection = {};
			var trmID;
			if (levelRelTypes.fwd) {
				$.each(levelRelTypes.fwd, function(trmID,linkSetIDs) {
					if (top.HEURIST.terms.termsByDomainLookup.relation[trmID] && top.HEURIST.terms.termsByDomainLookup.relation[trmID][0]) {
						relNames.push("" + top.HEURIST.terms.termsByDomainLookup.relation[trmID][0]+ ":" + trmID);
						relDirection[trmID] = "down";//towards increased depth
					}
				});
			}
			if (levelRelTypes.rev) {
				$.each(levelRelTypes.rev, function(trmID,linkSetIDs) {
					if (top.HEURIST.terms.termsByDomainLookup.relation[trmID] && top.HEURIST.terms.termsByDomainLookup.relation[trmID][0]) {
						if (relDirection[trmID]) {//name exist from forward
							relDirection[trmID] = "both";//both directions
						}else{
							relNames.push("" + top.HEURIST.terms.termsByDomainLookup.relation[trmID][0]+ ":" + trmID);
							relDirection[trmID] = "up";//towards decreased depth
						}
					}
				});
			}
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
				li.reltype = trmID = relInfo[1];
				$(li).attr("reltype", trmID);
				li.innerHTML = "<a href='#' onclick=top.HEURIST.search.toggleReltypeFilter(this.parentNode,"+level+","+trmID+")>" +
								(relDirection[trmID] == "both"? "↕": (relDirection[trmID] == "up"?"↑" :"↓")) + relInfo[0] + "</a>";
				li.className = "checked level"+level;
				li.className = (!filterRelTrmIDs || filterRelTrmIDs &&
													(filterRelTrmIDs.indexOf(parseInt(trmID)) != -1 ||
														filterRelTrmIDs.indexOf(trmID) != -1)? "checked ":'') + "level"+level;
				reltypeList.appendChild(li);
			}
			reltypeMenuItem.appendChild(reltypeList);
			filterMenu.appendChild(reltypeMenuItem);
		}
	},

	setBodyClass: function() {
		top.HEURIST.parameters["w"] = document.getElementById("w-input").value;
		var searchType = document.getElementById("w-input").value;
		if ($("body").hasClass("w-all")) {
			$("body").removeClass("w-all");}
		if ($("body").hasClass("w-bookmark")) {
			$("body").removeClass("w-bookmark");}
		var bodyClass = "w-" + searchType;
		$("body").addClass(bodyClass);
	},
	setSelectedCount: function() {
		for (i=0;i<=3;i++) {
			if (!top.HEURIST.search.selectedRecordIds[i] || top.HEURIST.search.selectedRecordIds[i].length == 0) {
			$("#selectedCount-"+i).html("");
			}else{
			$("#selectedCount-"+i).html("<span title=\"Number of records selected in this level is "+top.HEURIST.search.selectedRecordIds[i].length +"\">"+top.HEURIST.search.selectedRecordIds[i].length +"</span>");
			}
		}
	},
	toggleResults: function(){
		$("#results-level0").toggleClass("collapsed");
	},
	toggleRelated: function(level){
		var className =  document.getElementById("showrelated" + level).className;
		var depthInfo = top.HEURIST.search.results.infoByDepth[level];
		$("#results-level" + level).toggleClass("collapsed");
		if (className.match(/loaded/)) {  //loaded
			if ($("#results-level" + level).hasClass("collapsed")) {
				$("#showrelated" + level).html("<a onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Level "+level+" Related Records </a><span class=\"relatedCount\">"+depthInfo.count+"</span><span class=\"selectedCount\" id=\"selectedCount-"+level+"\"></span>");
				top.HEURIST.search.setSelectedCount();
			}else{
				$("#showrelated" + level).html("<a style='background-image:url(../common/images/heading_saved_search.png)' onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Level "+level+" Related Records </a><span class=\"relatedCount\">"+depthInfo.count+"</span><span class=\"selectedCount\" id=\"selectedCount-"+level+"\"></span>");
				top.HEURIST.search.setSelectedCount();
			};
		}else{  //not loaded yet
			//This is where we load the next related level and it's important to load all parts (recDiv and Filters before filtering.
			top.HEURIST.search.loadRelatedLevel(level);
			document.getElementById("showrelated" + level).className = className + " loaded";
			$("#showrelated" + level).html("<a style='background-image:url(../common/images/heading_saved_search.png)' onclick='top.HEURIST.search.toggleRelated(" +level + ")' href='#'>Level "+level+" Related Records </a><span class=\"relatedCount\">"+depthInfo.count+"</span><span class=\"selectedCount\" id=\"selectedCount-"+level+"\"></span>");
			top.HEURIST.search.setSelectedCount();
			top.HEURIST.search.filterRelated(level);
		}
		top.HEURIST.search.updateMapRelated();
	},

	// toggle selection in filter menu
	setAllFilterItems: function(filterMenu, level, toChecked){
		var selector = "li:not(.checked,.cmd)";
		if (!toChecked){
			selector = "li:not(.cmd).checked"
		}
		$(selector,filterMenu).toggleClass('checked');
		top.HEURIST.search.filterRelated(level);
		top.HEURIST.search.updateMapRelated();
	},

	toggleRectypeFilter: function(menuItem, level, rtID){
		var resultsDiv =  $("#results-level" + level).get(0);
		var recIDs = top.HEURIST.search.results.infoByDepth[level].rectypes[rtID];
		// for each record in this level of the given type toggle the filter class
		$.each(recIDs,function(i,recID){
				var recDiv = $('.recordDiv[recID='+recID+']', resultsDiv);
				if(recDiv){
					recDiv.toggleClass('filtered');
				}
			});
		$(menuItem).toggleClass('checked')
		// recalc lower level filters (pushdown filtering)
		top.HEURIST.search.recalcLinkMenus(level);
		top.HEURIST.search.filterRelated(level+1);
		top.HEURIST.search.updateMapRelated();
	},

	// this functions assumes the rectypes for this level are stable. It checks for links between this level and it's parent.
	recalcLinkMenus: function(level) {
		if (level == 0){
			return;
		}
		var resultsDiv =  $("#results-level" + level).get(0);
		var parentLevelResultDiv =  $("#results-level" + (level-1)).get(0);
		var depthInfo = top.HEURIST.search.results.infoByDepth[level];
		var activeRecIDs = {},
			activeParentRecIDs = {},
			ptrRecordIDs = {},
			relRecordIDs = {},
			activePtrTypes = {},
			activeRelTypes = {};

		//find all recIds of unfiltered records in this level
		$('div.recordDiv:not(.filtered)',resultsDiv).each(function(i,recDiv){
				activeRecIDs[$(this).attr('recid')] = 1;
			})
		// find all visible records in parent level
		$('div.recordDiv'+(level==1?'':'.lnk') +':not(.filtered)',parentLevelResultDiv).each(function(i,recDiv){
				activeParentRecIDs[$(this).attr('recid')] = 1;
			})
		//enable all link filter menus' items
		$('li:not(.view)>ul:not(.rectype)>li.disabled',resultsDiv).each( function(i,li){
				$(li).toggleClass('disabled');
			});
		// remove all lnk class tags
		$('.recordDiv',resultsDiv).each( function(i,recDiv){
				while($(recDiv).hasClass('lnk')){
					$(recDiv).removeClass('lnk');
				}
			});
		$('ul.reltype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var trmID = $(li).attr('reltype'),
					isChecked = $(li).hasClass('checked');;

				var hasActiveLink = false;
				if (depthInfo.reltypes.fwd && depthInfo.reltypes.fwd[trmID]) {
					$.each(depthInfo.reltypes.fwd[trmID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									if (activeRecIDs[linkedRecIDs[j]]) { // link's level record is active
										if (isChecked) {
											$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
										}
										hasActiveLink = true;//found link of this type that is active
									}
								}
							}
						});
				}
				if (depthInfo.reltypes.rev && depthInfo.reltypes.rev[trmID]) {
					$.each(depthInfo.reltypes.rev[trmID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									if (activeRecIDs[linkedRecIDs[j]]) {// link's level record is active
										if (isChecked) {
											$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
										}
										hasActiveLink = true;//found link of this type that is active
									}
								}
							}
						});
				}

				if (!hasActiveLink) {
					$(li).toggleClass('disabled');
				}
			});
		//for each of the menus disable ptrtype menu items not in the ptrtype set and lnk records for those that are checked
		$('ul.ptrtype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var dtyID = $(li).attr('ptrtype'),
					isChecked = $(li).hasClass('checked');;

				var hasActiveLink = false;
				if (depthInfo.ptrtypes.fwd && depthInfo.ptrtypes.fwd[dtyID]) {
					$.each(depthInfo.ptrtypes.fwd[dtyID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){/// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									if (activeRecIDs[linkedRecIDs[j]]) {// link's level record is active
										if (isChecked) {
											$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
										}
										hasActiveLink = true;
									}
								}
							}
						});
				}
				if (depthInfo.ptrtypes.rev && depthInfo.ptrtypes.rev[dtyID]) {
					$.each(depthInfo.ptrtypes.rev[dtyID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									if (activeRecIDs[linkedRecIDs[j]]) {// link's level record is active
										if (isChecked) {
											$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
										}
										hasActiveLink = true;
									}
								}
							}
						});
				}

				if (!hasActiveLink) {// no active links
					$(li).toggleClass('disabled');
				}
			});
	},


	togglePtrtypeFilter: function(menuItem, level, dtyID){
		$(menuItem).toggleClass('checked');
		top.HEURIST.search.recalcLinkMenus(level);
		top.HEURIST.search.filterRelated(level+1);
		top.HEURIST.search.updateMapRelated();
	},

	toggleReltypeFilter: function(menuItem, level, trmID){
		$(menuItem).toggleClass('checked')
		top.HEURIST.search.recalcLinkMenus(level);
		top.HEURIST.search.filterRelated(level+1);
		top.HEURIST.search.updateMapRelated();
	},

	// new render common result record
	renderResultRecord: function(res) {

		/* res[0]   res[1]        res[2]  res[3]   res[4]       res[5]     res[6]        */
		/* bkm_ID, bkm_UGrpID, rec_ID, rec_URL, rec_RecTypeID, rec_Title, rec_OwnerUGrpID */

		/* res[7]          res[8]                 res[9]         res[10]        res[11]   */
		/* rec_NonOwnerVisibility, rec_URLLastVerified, rec_URLErrorMessage, bkm_PwdReminder, thumbnailURL */

		var pinAttribs = res[0]? "class='logged-in-only bookmarked' title='Bookmarked - click to see details'"
								:"class='logged-in-only unbookmarked' title='Bookmark this record'";

		var href = res[3];
		var linkText = res[5] + " ";
		var wgID = parseInt(res[6]);

		var linkTitle = "";
		var wgHTML = "";
		var wgColor = "";
		if (res[6] && res[6] != "0" ) {	// check if this is an usergroup or user owned record
			linkTitle = "Owned by " + ( top.HEURIST.workgroups[wgID] ? "workgroup " + top.HEURIST.workgroups[wgID].name:
										top.HEURIST.allUsers[wgID] ? top.HEURIST.allUsers[wgID][0] : "unknown group") + " - " +
										((res[7]=='hidden')? "hidden" : "read-only") + " to others";
			wgHTML = res[6];
			wgColor = " style='color:" + ((res[7]=='hidden')? "red" : "green") + "'";
		}

		var editLinkIcon = "<div id='rec_edit_link' class='logged-in-only'><a href='"+
			top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
			top.HEURIST.search.results.querySid + "&recID="+ res[2] +
			(top.HEURIST.database && top.HEURIST.database.name ? '&db=' + top.HEURIST.database.name : '');
		if (top.HEURIST.user && res[6] && (top.HEURIST.user.isInWorkgroup(res[6])|| res[6] == top.HEURIST.get_user_id()) || res[6] == 0) {
			editLinkIcon += "' target='_blank' title='Click to edit record'><img src='"+
							top.HEURIST.basePath + "common/images/edit_pencil_small.png'/></a></div>";
		}else{
			editLinkIcon += "' target='_blank' title='Click to edit record extras only'><img src='"+
							top.HEURIST.basePath + "common/images/partial_edit_pencil_small.png'/></a></div>";
		}
		var newSearchWindow = "<div><a href='"+top.HEURIST.basePath+"search/search.html?q=ids:"+res[2]+
			(top.HEURIST.database && top.HEURIST.database.name ? '&db=' + top.HEURIST.database.name : '') +
			"' target='_blank' title='Open in new window' class='externalLink'></a></div>"
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

		var rectypeImg = "style='background-image:url("+ top.HEURIST.iconBaseURL + (res[4]? res[4] : "blank") + ".png)'";
		var rectypeThumb = "style='background-image:url("+ top.HEURIST.iconBaseURL + "thumb/th_" + (res[4]? res[4] : "blank") + ".png)'";
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
		"<div id='recordID'>"+
		newSearchWindow +
		editLinkIcon +
		"</div>" +
		"</div>" +
		"</div>";
		return html;
	},


	displaySearchParameters: function() {
		// Transfer query components to their form elements
		var params = top.HEURIST.parameters;
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
//		if (!$("body").hasClass("w-all") || !$("body").hasClass("w-bookmark")) {
		//document.body.className += (document.body.className ? " " : "") + "w-" + params["w"];
//		}
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
		if (top.HEURIST.is_logged_in && top.HEURIST.is_logged_in()) {
			logged_in_elt.innerHTML = top.HEURIST.get_user_name() + " : <a href=" +top.HEURIST.basePath+ "common/connect/login.php?logout=1"+(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "")+">log&nbsp;out</a>";
		} else {
			logged_in_elt.innerHTML = "not logged in : <a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "")+">log in</a>";
			left_panel_elt.innerHTML =
				"<div style=\"padding: 10px;\">\n" +
				" <br>Existing users:\n" +
				" <div id=login-button><a href=" +top.HEURIST.basePath+ "common/connect/login.php"+(top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "")+" title=\"Log in to use Heurist - new users please register first\"><img src=../common/images/111x30.gif></a></div>\n" +
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
		if (top.HEURIST.util.getDisplayPreference("loadRelatedOnSearch") ==="false"){
			$("#results-level0 div.filter").remove();
		}
	},

	clearRelatesRows: function() {
		$("div[id!=results-level0][id*=results-level]").remove();
		top.HEURIST.search.selectedRecordIds = [];
		top.HEURIST.search.selectedRecordDivs = [];
	},

	loadRelatedLevel: function(level, noFilter){
		//level 0 is the result set and should already be loaded
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
		if (!noFilter) {
			var activeParentRecIDs = {},
				j;
			$("div.recordDiv"+(level-1 > 0?".lnk":"")+":not(.filtered)",$("#results-level" + (level -1)).get(0)).each(function(){
				activeParentRecIDs[$(this).attr('recid')]=1;});
			$('ul.ptrtype>li.checked',resultsDiv).each( function(i,li){
					var dtyID = $(li).attr('ptrtype');
					if (depthInfo.ptrtypes.fwd && depthInfo.ptrtypes.fwd[dtyID]) {
						$.each(depthInfo.ptrtypes.fwd[dtyID],function(parentRecID,linkedRecIDs){
								if (activeParentRecIDs[parentRecID]){// active link
									for (var j=0; j<linkedRecIDs.length; j++) {
										$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
									}
								}
							});
					}
					if (depthInfo.ptrtypes.rev && depthInfo.ptrtypes.rev[dtyID]) {
						$.each(depthInfo.ptrtypes.rev[dtyID],function(parentRecID,linkedRecIDs){
								if (activeParentRecIDs[parentRecID]){// active link
									for (var j=0; j<linkedRecIDs.length; j++) {
										$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
									}
								}
							});
					}
				});
			$('ul.reltype>li.checked',resultsDiv).each( function(i,li){
					var trmID = $(li).attr('reltype');
					if (depthInfo.reltypes.fwd && depthInfo.reltypes.fwd[trmID]) {
						$.each(depthInfo.reltypes.fwd[trmID],function(parentRecID,linkedRecIDs){
								if (activeParentRecIDs[parentRecID]){// active link
									for (var j=0; j<linkedRecIDs.length; j++) {
										$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
									}
								}
							});
					}
					if (depthInfo.reltypes.rev && depthInfo.reltypes.rev[trmID]) {
						$.each(depthInfo.reltypes.rev[trmID],function(parentRecID,linkedRecIDs){
								if (activeParentRecIDs[parentRecID]){// active link
									for (var j=0; j<linkedRecIDs.length; j++) {
										$('.recordDiv[recID='+linkedRecIDs[j]+']',resultsDiv).get(0).className += ' lnk';
									}
								}
							});
					}
				});
			top.HEURIST.search.loadLevelFilter(level + 1);
		}
	},

	//assumes that we are pushing down with complete reset and recalculation
	//filters are applied accordingly.
	filterRelated: function(level,noPush,useParamFilters){
		//terminate pushdown calculation
		if (level != 0 && !$("#showrelated" + level).hasClass("loaded")) {
			return;
		}

		var resultsDiv = document.getElementById("results-level"+level);
		var parentLevelResultDiv = (level == 0 ? null : document.getElementById("results-level"+(level-1)));
		if (!resultsDiv || (level>0 && !parentLevelResultDiv)) {
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
		var ptrRecordIDs = {};
		var relRecordIDs = {};
		var activeRecIDs = {};
		var activeParentRecIDs = {};
		var recInfo;
		if (level==0) {// there is not parent level record set
			//calculate the rectypes in this recset page with filtering
			for ( rtID in depthInfo.rectypes) {
				rectypes[rtID] = 1;
			}
		}else{
			//for each active parent record
			$('div.recordDiv'+(level==1?'':'.lnk') +':not(.filtered)',parentLevelResultDiv).each(function(i,recDiv){
				//for this parentlevel record find the rectypes of all validly linked child records
				var parentRecID = $(recDiv).attr("recID");
				activeParentRecIDs[parentRecID] = 1;
				});

				if (depthInfo.reltypes && depthInfo.reltypes.fwd) {
					for (trmID in depthInfo.reltypes.fwd){
						$.each(depthInfo.reltypes.fwd[trmID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									recTypeID = recSet[linkedRecIDs[j]].record[4];
									rectypes[recTypeID] = 1;
								}
							}
						});
					}
				}
				if (depthInfo.reltypes && depthInfo.reltypes.rev) {
					for (trmID in depthInfo.reltypes.rev){
						$.each(depthInfo.reltypes.rev[trmID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									recTypeID = recSet[linkedRecIDs[j]].record[4];
									rectypes[recTypeID] = 1;
								}
							}
						});
					}
				}
				if (depthInfo.ptrtypes && depthInfo.ptrtypes.fwd) {
					for (dtyID in depthInfo.ptrtypes.fwd){
						$.each(depthInfo.ptrtypes.fwd[dtyID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									recTypeID = recSet[linkedRecIDs[j]].record[4];
									rectypes[recTypeID] = 1;
								}
							}
						});
					}
				}
				if (depthInfo.ptrtypes && depthInfo.ptrtypes.rev) {
					for (dtyID in depthInfo.ptrtypes.rev){
						$.each(depthInfo.ptrtypes.rev[dtyID],function(parentRecID,linkedRecIDs){
							if (activeParentRecIDs[parentRecID]){// link's parent record is active
								for (var j=0; j<linkedRecIDs.length; j++) {
									recTypeID = recSet[linkedRecIDs[j]].record[4];
									rectypes[recTypeID] = 1;
								}
							}
						});
					}
				}
		}
/*		if (filterRtIDs) { // need to set to uri filter set
			//uncheck all rectype filter menu items
			$('ul.rectype>li:not(.cmd).checked',resultsDiv).each( function(i,li){
					$(li).toggleClass('checked');
				});
			$('ul.rectype>li:not(.cmd)',resultsDiv).each( function(i,li){
					var rtID = $(li).attr('rectype');
					if (filterRtIDs.indexOf(parseInt(rtID)) != -1){
						$(li).toggleClass('checked');
					}
				});
		}*/
		//enable all filter menu items
		$('li>ul>li.disabled',resultsDiv).each( function(i,li){
				$(li).toggleClass('disabled');
			});
		// remove all lnk class tags
		$('.recordDiv',resultsDiv).each( function(i,recDiv){
				while($(recDiv).hasClass('filtered')){
					$(recDiv).removeClass('filtered');
				}
			});

		//for each of the menus disable rectype menu items not in the rectype set and filter records of those that are not checked
		//DO THIS FIRST  so that the links can be validated for non-filtered records on this level
		var activeRecIDs = {};
		$('ul.rectype>li:not(.cmd)',resultsDiv).each( function(i,li){
				var rtID = $(li).attr('rectype'),
					isChecked = $(li).hasClass('checked');
				// if it's not an active rectype or this menu item is unchecked
				if (!rectypes[rtID] || !isChecked) {
					$.each(depthInfo.rectypes[rtID],function(i,recID){// filter all recordDivs of this rectype
						$('.recordDiv[recID='+recID+']', resultsDiv).toggleClass('filtered');
					});
					if (!rectypes[rtID]){//no connection to an active parent so disable this rectype menu item
						$(li).toggleClass('disabled');
					}
				}
			});

		top.HEURIST.search.recalcLinkMenus(level);
		if (!noPush) {
			top.HEURIST.search.filterRelated(level + 1, noPush, useParamFilters);
		}
	},

	renderSearchResults: function(firstIndex, lastIndex, style) {
		document.getElementById("search-status").className = "";
		// This is rooted ... Firefox doesn't render the display: none on this element until after the results are actually loaded.
		// We shall do a manual override.
		var currentHTML = document.getElementById("loading-search-status").innerHTML;
		document.getElementById("loading-search-status").innerHTML = "";
		setTimeout(function() { document.getElementById("loading-search-status").innerHTML = currentHTML }, 0);

		// clear out old data before rendering anew
		top.HEURIST.search.clearResultRows();

		if (!style) {
			style = top.HEURIST.util.getDisplayPreference("search-result-style0");
		}
		//if (style != "list"  ||  style != "thumbnails" || style !="icons") {
		//	style = "thumbnails"; // fall back for old styles
		//top.HEURIST.util.setDisplayPreference("search-result-style", style);
		//}
		var resultsDiv = document.getElementById("results-level0");
		var pageWidth = document.getElementById('page').offsetWidth;
		top.HEURIST.search.setResultStyle(style,0);
//		if (pageWidth < 390 && style == "two-col") {
//			resultsDiv.className = "list";
//		}else{
//			resultsDiv.className = style;
//		};

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
		if (recordCount == 1){
			document.getElementById("active-label").innerHTML = recSet[recInfo.recIDs[0]].record[5];
		}
		//if (style == "list"  ||  style == "thumbnails" || style =="icons") {
			resultsDiv.innerHTML += innerHTML;
		//} else if (style = "two-col") {
		//	resultsDiv.innerHTML = "<div class=two-col-cell><div class=two-col-inner-cell-left>" + leftHTML + "</div></div><div class=two-col-cell><div class=two-col-inner-cell-right>" + rightHTML + "</div></div>";
		//}

		// add click handlers
		top.HEURIST.search.addResultLevelEventHandlers(0);
//		top.HEURIST.search.addResultLevelLinks(0); // can't be done here since links aren't read in yet
		top.HEURIST.search.loadLevelFilter(0);
		top.HEURIST.search.filterRelated(0);
		top.HEURIST.search.updateMapRelated();

		top.HEURIST.registerEvent(window, "load", function() {top.HEURIST.search.trimAllLinkTexts(0);});
		top.HEURIST.registerEvent(window, "load", function() {
			if (document.getElementById("legend-box")) {
				top.HEURIST.search.toggleLegend();
				top.HEURIST.search.toggleLegend();
			}
		});
	},

	addResultLevelLinks: function(level) {// this function adds link+recID tags to a record for every related record fir highlight and selction code
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
				if (level !== 0) {
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

	setResultStyle: function(style,level) {
		var allLevels = typeof level == "undefined" || level === "" ? true:false;
		if (!allLevels) {
			if (isNaN(level)){
				return;
			}else{
				level = parseInt(level);
				if (level > 3) {
					return;
				}
			}
		}
		if (style.length <= 2) { // code style so decode
			switch (style[0]) {
				case "t":
					style="thumbnails";
				break;
				case "i":
					style="icons";
				break;
				case "2":
					style="two-col";
				break;
				default:
					style="list";
			}
		}
		var pageWidth = document.getElementById('page').offsetWidth;
		if (pageWidth < 390 && style == "two-col") {
			style = "list";
		}
		var i,l;
		if (allLevels) {
			i=0;
			l=4;
		}else{
			i = level,
			l = level+1;
		}
		var resultsDiv;
		for (;i<l; i++) {
			resultsDiv = document.getElementById("results-level"+i);
			if (resultsDiv) {
				top.HEURIST.util.setDisplayPreference("search-result-style"+ (allLevels?"":i), style, window,null,true,true);
				resultsDiv.className = style + (i>0?" related-results":"") +
										($(resultsDiv).hasClass("collapsed") ? " collapsed" : "");
				$("li.view > ul > li", resultsDiv).removeClass("checked");
				$("li.view > ul > li > a."+style, resultsDiv).parent().addClass("checked");
			}
		}
		return false;
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
				+ (top.HEURIST.parameters["w"] == "bookmark" ? "" : " all");
			document.getElementById("resource-count").innerHTML = "";
			return;
		}

		var i;
		var totalRecordCount = parseInt(top.HEURIST.search.results.totalQueryResultRecordCount);
		var resultsPerPage = parseInt(top.HEURIST.search.resultsPerPage);
		var pageCount = Math.ceil(totalRecordCount / resultsPerPage);
		var currentPage = top.HEURIST.search.currentPage;

		var firstRes = (currentPage*top.HEURIST.search.resultsPerPage+1);
		var lastRes = Math.min((currentPage+1)*resultsPerPage, totalRecordCount);


		if (totalRecordCount == 1) {
			document.getElementById("resource-count").innerHTML = "1 record";
			document.getElementById("selectRecords").innerHTML = "Select All";
		} else if (totalRecordCount <= resultsPerPage) {
			document.getElementById("resource-count").innerHTML = /*"Showing all */"<span class=\"recordCount\">" + totalRecordCount + "</span> records";
			document.getElementById("selectRecords").innerHTML = "Select All";
		} else {
			document.getElementById("resource-count").innerHTML = firstRes + " - " + lastRes + " / <span class=\"recordCount\">" + totalRecordCount + "</span>";
			document.getElementById("selectRecords").innerHTML = "Select All on Page";
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

		if (pageCount > 1  &&  totalRecordCount <= top.HEURIST.search.pageLimit) {
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
		if (top.HEURIST.search.results.totalQueryResultRecordCount > top.HEURIST.search.pageLimit) {
			alert("There are too many search results to perform operations on.  Please refine your search first.");
//			document.getElementById("select-all-checkbox").checked = false;
			return;
		}
		top.HEURIST.search.gotoResultPage(0, true);
	},

	handleFieldSelectSimpleSearch: function(){
		var fld = $("#field-select").val();
		var dtID = fld.match(/f\:(\d+)\:/);
		if (!dtID[1]){
			return;
		}else{
			dtID=dtID[1];
		}
		var dtyDefs = top.HEURIST.detailTypes.typedefs;
		// if detatilType is enumeration then create a select for the values.
		if (dtyDefs[dtID] && dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_Type']] === 'enum'){
			//tagging the div as enum hides the inputfield
			$("#field-select").parent().addClass('enum');
			//create selector from typedef
			var allTerms = top.HEURIST.util.expandJsonStructure(dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_JsonTermIDTree']]),
				disabledTerms = top.HEURIST.util.expandJsonStructure(dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']]);
			var enumSelector = top.HEURIST.util.createTermSelect(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup['enum'], null);
			if (enumSelector){
				enumSelector.id = "simple-search-enum-selector";
			}
			//attach onchange handler
			enumSelector.onchange = function(){
				top.HEURIST.search.calcShowSimpleSearch();
			}
			//add it to the popup
			YAHOO.util.Dom.insertAfter(enumSelector,$("#input-contains").get(0))
		}else{//reset to standard freetext input field
			//if enum selector exist remove it
			if ($("#simple-search-enum-selector").length>0) {
				$("#simple-search-enum-selector").remove();
			}
			//untagging the div shows the regular input field
			$("#field-select").parent().removeClass('enum');
		}
		top.HEURIST.search.calcShowSimpleSearch();
	},

	calcShowSimpleSearch: function () {
		var q = $("#rectype-select").val();
		var fld = $("#field-select").val();
		var ctn = $("#field-select").parent().hasClass('enum') ?$("#simple-search-enum-selector").val() :
																$("#input-contains").val();
		q = (q? (fld?q+" ": q ):"") + (fld?fld:" all:") + (ctn?'"'+ctn+'"':"");
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
		for (var index in rectypes.groups){
			if (index == 'groupIDToIndex' || top.HEURIST.rectypes.groups[index].showTypes.length < 1){
				continue;
			}
			var grp = document.createElement("optgroup");
			var firstInGroup = true,
				i=0;
			grp.label = rectypes.groups[index].name;
			for (; i < rectypes.groups[index].showTypes.length; i++) {
				var recTypeID = rectypes.groups[index].showTypes[i];
				if (recTypeID && rectypes.usageCount[recTypeID]) {
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
				}
			}
		}
	},

	createUsedDetailTypeSelector: function (useIDs) {
		var detailTypes = top.HEURIST.detailTypes;
		var fieldValSelect = document.getElementById("field-select");
		fieldValSelect.innerHTML = '<option value="" selected>Any field</option>';
		fieldValSelect.onchange =  top.HEURIST.search.handleFieldSelectSimpleSearch;

		// rectypes displayed in Groups by group display order then by display order within group
		for (var index in detailTypes.groups){
			if (index == 'groupIDToIndex'){
				continue;
			}
			var grp = document.createElement("optgroup");
			var firstInGroup = true,
				i=0;
			grp.label = detailTypes.groups[index].name;
			for (; i < detailTypes.groups[index].showTypes.length; i++) {
				var detailTypeID = detailTypes.groups[index].showTypes[i];
				if (detailTypeID && detailTypes.usageCount[detailTypeID]) {
					if (firstInGroup){
						fieldValSelect.appendChild(grp);
						firstInGroup = false;
					}
					var name = detailTypes.names[detailTypeID];
//					var name = detailTypes.names[detailTypeID] +" (detail:" + detailTypeID + ")";
					var value =  "f:" + (useIDs ? detailTypeID : '"'+name+'"') + ":";
					fieldValSelect.appendChild(new Option(name,value));
				}
			}
		}
	},


	createRectypeFieldSelector: function (rt,useIDs) {
		var fields = top.HEURIST.rectypes.typedefs[rt].dtFields;
		var fieldValSelect = document.getElementById("field-select");
		fieldValSelect.innerHTML = '<option value="" selected>Any field</option>';
		fieldValSelect.onchange = top.HEURIST.search.handleFieldSelectSimpleSearch;
		// rectypes displayed in Groups by group display order then by display order within group
		for (var dtID in fields){
			var name = fields[dtID][0] +" (" + dtID + ")";
			var value =  "f:" + (useIDs ? dtID : '"'+name+'"') + ":";
			fieldValSelect.appendChild(new Option(name,value));
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
		top.HEURIST.search.deselectAll(false);

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
		top.HEURIST.search.updateMapOrSmarty();
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
					top.HEURIST.search.results.querySid + "&recID="+recID+
					(top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));

		return false;
	},

	selectedRecordIds: [],
	selectedRecordDivs: [],

	toggleResultItemSelect: function(recID,resultDiv,level) {
		if (typeof level === "undefined") {
			level = 0;
		}
		if (top.HEURIST.search.selectedRecordDivs[level] && top.HEURIST.search.selectedRecordDivs[level][recID] &&
				$(top.HEURIST.search.selectedRecordDivs[level][recID]).hasClass("selected")){
			top.HEURIST.search.deselectResultItem(recID,level);
		}else if (recID) {
			if (!resultDiv) {
				resultDiv = $('div[class~=recordDiv]',$("#results-level"+level)).filter('div[recid='+ recID +']').get(0);
			}
			$(resultDiv).toggleClass("selected");
//			while ($(resultDiv).hasClass("relateSelected")){
//				$(resultDiv).removeClass("relateSelected");
//			}
			if (top.HEURIST.util.getDisplayPreference("autoSelectRelated") != "true"){
				$(".link"+recID,$("#results")).addClass("linkSelected");
			}else{// mark all related records
				//for each related record mark as relateSelected and add them into the selected array
				$(".link"+recID+":not(.selected)",$("#results")).each(function(){
					var div = $(this);
					var relRecID = div.attr("recid");
					var lvl = div.parent().attr("level");
					this.className = this.className + " relateSelected";
					if (!top.HEURIST.search.selectedRecordDivs[lvl]) {
						top.HEURIST.search.selectedRecordDivs[lvl] = {};
					}
					if (!top.HEURIST.search.selectedRecordIds[lvl]) {
						top.HEURIST.search.selectedRecordIds[lvl] = [];
					}
					if (!top.HEURIST.search.selectedRecordDivs[lvl][relRecID]) {
						top.HEURIST.search.selectedRecordDivs[lvl][relRecID] = this;
						top.HEURIST.search.selectedRecordIds[lvl].push(relRecID);
					}
				});
			}
			if (!top.HEURIST.search.selectedRecordDivs[level]) {
				top.HEURIST.search.selectedRecordDivs[level] = {};
			}
			top.HEURIST.search.selectedRecordDivs[level][recID] = resultDiv;
			if (!top.HEURIST.search.selectedRecordIds[level]) {
				top.HEURIST.search.selectedRecordIds[level] = [];
			}
			top.HEURIST.search.selectedRecordIds[level].push(recID);
			top.HEURIST.search.setSelectedCount(level);

		}
	},

	deselectResultItem: function(recID,level) {
		var resultDiv = top.HEURIST.search.selectedRecordDivs[level][recID];
		if (resultDiv && $(resultDiv).hasClass("selected")) {
			delete top.HEURIST.search.selectedRecordDivs[level][recID];
			$(resultDiv).toggleClass("selected");
			top.HEURIST.search.setSelectedCount(level);
			if (top.HEURIST.util.getDisplayPreference("autoSelectRelated") != "true"){
				$(".link"+recID,$("#results")).removeClass("linkSelected");
			}else{// unmark all related records
				//for each related record mark as relateSelected and add them into the selected array
				$(".link"+recID+".relateSelected",$("#results")).each(function(){
					var div = $(this);
					var relRecID = div.attr("recid");
					var lvl = div.parent().attr("level");
					div.removeClass("relateSelected");
					if (!div.hasClass("relateSelected")) {
						delete top.HEURIST.search.selectedRecordDivs[lvl][relRecID];
						for(var i = 0; i < top.HEURIST.search.selectedRecordIds[lvl].length; i++){
							if (top.HEURIST.search.selectedRecordIds[lvl][i] == relRecID) {
								top.HEURIST.search.selectedRecordIds[lvl].splice(i,1);
								break;
							}
						}
					}
				});
			}
			for(var i = 0; i < top.HEURIST.search.selectedRecordIds[level].length; i++){
				if (top.HEURIST.search.selectedRecordIds[level][i] == recID) {
					top.HEURIST.search.selectedRecordIds[level].splice(i,1);
					return true; // signal that we remove the id from selected ids
				}
			}
		}
		return false;
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

	activeLevel:0,

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
		var selectRelated = top.HEURIST.util.getDisplayPreference("autoSelectRelated") == "true";
		var deselectAllOnLevelChange = top.HEURIST.displayPreferences.autoDeselectOtherLevels == "true";

		if (deselectAllOnLevelChange && level != top.HEURIST.search.activeLevel){
			top.HEURIST.search.activeLevel = level;
			//reset selection on all recordDivs - linkSelect, relatedSelect and select
			$(".recordDiv.selected",$("#results")).each(function(){//selected
				while ($(this).hasClass("selected")){
					$(this).removeClass("selected");
				}
			});
			$(".recordDiv.linkSelected",$("#results")).each(function(){//linkSelected
				while ($(this).hasClass("linkSelected")){
					$(this).removeClass("linkSelected");
				}
			});
			$(".recordDiv.relateSelected",$("#results")).each(function(){//relateSelected
				while ($(this).hasClass("relateSelected")){
					$(this).removeClass("relateSelected");
				}
			});
			var i; // reset the selected RecIDs by level array
			for (i=0; i< top.HEURIST.search.selectedRecordIds.length; i++) {
				top.HEURIST.search.selectedRecordIds[i] = [];
				top.HEURIST.search.selectedRecordDivs[i] = [];
			}
		}

		if ( e.ctrlKey) { // CTRL + Click -  do multiselect functionality single item select and unselect from list
			top.HEURIST.search.toggleResultItemSelect(recID,resultDiv,level);
		}else if (e.shiftKey){//SHIFT + Click
		// find all items from current click item to last selected since index map is a display ordering by level
			var recIdToIndexMap = results.infoByDepth[level].recIdToIndexMap;
			var selectedBibIds = top.HEURIST.search.selectedRecordIds[level];
			var lastSelectedIndex = selectedBibIds.length?recIdToIndexMap[selectedBibIds[selectedBibIds.length - 1]]:0;
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
					if (top.HEURIST.search.deselectResultItem(selectedBibIds[j],level)) {
						j--; //adjust for changed selectedBibIds
					}
				}
			}
		//select all unselected new items and replace the selectedBidIds
			for(var newSelectedRecId in newSelectedRecIdMap){
				if (!top.HEURIST.search.selectedRecordDivs[level][newSelectedRecId] ||
					!$(top.HEURIST.search.selectedRecordDivs[level][newSelectedRecId]).hasClass("selected")){
					top.HEURIST.search.toggleResultItemSelect(newSelectedRecId,null,level);
				}
			}
			top.HEURIST.search.selectedRecordIds[level] = newSelectedRecIds;
		}else{ //Normal Click -- assume this is just a normal click and single select;
			var clickedResultFound = false;
			var j=0;
			if (top.HEURIST.search.selectedRecordIds[level]) {
				for (j=0; j<top.HEURIST.search.selectedRecordIds[level].length; j++) {
					if (!clickedResultFound &&
							top.HEURIST.search.selectedRecordIds[level][j] == recID &&
							top.HEURIST.search.selectedRecordDivs[level][recID] &&
							$(top.HEURIST.search.selectedRecordDivs[level][recID]).hasClass("selected") ) {
						clickedResultFound=true;
					}else{
						if (top.HEURIST.search.deselectResultItem(top.HEURIST.search.selectedRecordIds[level][j],level)){
							j--;
						}
					}
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

//        var sidebysideFrame = document.getElementById("sidebyside-frame");
//		sidebysideFrame.src = top.HEURIST.basePath+"viewers/sidebyside/sidebyside.html"+
//		("?db=" + (top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
//						(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "")));

		var viewerFrame = document.getElementById("viewer-frame");
		var ssel = "selectedIds=" + selectedRecIDs.join(",");
		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);

/*art temp old mapping
		var mapFrame = document.getElementById("map-frame");
		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);*/

/*
		var mapFrame3 = document.getElementById("map-frame3");
		var smartyFrame = document.getElementById("smarty-frame");
		top.HEURIST.fireEvent(mapFrame3.contentWindow.showMap,"heurist-selectionchange",  ssel);
		top.HEURIST.fireEvent(smartyFrame.contentWindow.showReps,"heurist-selectionchange",  ssel);
*/
		top.HEURIST.search.updateMapOrSmarty();

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

			var active = (sid == top.HEURIST.parameters["sid"]);


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

				if (!top.HEURIST.workgroups[w]) {
					continue;
				}
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
		var url = top.HEURIST.basePath+ "search/queryBuilderPopup.php?q=" + encodeURIComponent(q) + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
		top.HEURIST.util.popupURL(window, url, { callback: top.HEURIST.search.advancedSearchCallback });
	},


	advancedSearchCallback: function(q) {
		if (q === undefined) {
			// user clicked close-button ... do nothing
		} else {
			this.document.getElementById("q").value = q;
//			this.document.forms[0].submit();
			top.HEURIST.search.reloadSearch();
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
		var dim = top.HEURIST.util.innerDimensions(window);
		if (targ.className.match(/\bsmall\b/)){
			top.HEURIST.util.popupURL(top, targ.href, {height:dim.h*0.5, width:dim.w*0.5});
		}else if (targ.className.match(/\bportrait\b/)){
			top.HEURIST.util.popupURL(top, targ.href, {height:dim.h*0.9, width:dim.w*0.5});
		}else{
			top.HEURIST.util.popupURL(top, targ.href, {height:dim.h*0.8, width:dim.w*0.8});
		}
		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		return false;
	},

	popupLink: function(url,size) {

		var dim = top.HEURIST.util.innerDimensions(window);
		if (size == "small"){
			top.HEURIST.util.popupURL(top, url, {height:dim.h*0.5, width:dim.w*0.5});
		}else if (size =="wide"){
			top.HEURIST.util.popupURL(top, url, {height:dim.h*0.7, width:dim.w*0.9});
		}else{
			top.HEURIST.util.popupURL(top, url, {height:dim.h*0.8, width:dim.w*0.8});
		}
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
	//old map version
	//TODO: this code needs to be changed to the application model using events. Special knowledge of applications
	// couples this code to the application and is not maintainable in the long run.
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
	//
	// it should be replaced to "real" event listener in particual application (map or smarty)
	//TODO: this code needs to be changed to the application model using events. Special knowledge of applications
	// couples this code to the application and is not maintainable in the long run.
	updateMapOrSmarty: function(){

			if(!_tabView) return;
			var currentTab = _tabView.getTabIndex(_tabView.get('activeTab'));
			if(!(currentTab==_TAB_MAP || currentTab==_TAB_SMARTY)) return;

			var p = top.HEURIST.parameters,
				query_string = "w=all",
				query_string_sel = null,
				query_string_main = null;


			if(p["q"]){
				query_string = 'ver='+(p['ver'] || "") +
									'&w='+(p['w'] || "") +
									'&stype='+(p['stype'] || "");
			}

			var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
					 (top.HEURIST.database.name?top.HEURIST.database.name:''));
			query_string = query_string+"&db="+db;

			query_string_main = query_string + (p["q"]?"&q="+p["q"]:'');

			var limit = parseInt(top.HEURIST.util.getDisplayPreference("report-output-limit"));
			if(isNaN(limit)) {
				limit = 1000; //default dispPreference
			}

			//only selected
			var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
			top.HEURIST.currentQuery_sel_waslimited = false;
			if(recIDs && recIDs.length>0){
				top.HEURIST.currentQuery_sel_waslimited = (recIDs.length > limit);

				if (top.HEURIST.currentQuery_sel_waslimited) {
					//alert("Selected record count is great than 500, opening the first 500 records!");
					recIDs = recIDs.slice(0,limit);
				}
				query_string_sel = encodeURI(query_string + '&q=ids:' + recIDs.join(","));
			}

			//all visible records
			recIDs = [];
			for(var ilevel=0; ilevel<4; ilevel++){
				var resultsDiv = document.getElementById("results-level"+ilevel);
				if (resultsDiv &&
					( (ilevel===0) || !resultsDiv.className.match(/collapsed/) ))
				{  //visible

					$('.recordDiv',resultsDiv).each( function(i,recDiv){

						if( ((ilevel===0) || $(recDiv).hasClass('lnk')) && !$(recDiv).hasClass('filtered')){
							var recID = $(recDiv).attr('recid');
							recIDs.push(recID);
						}
					}); //each functions

				}
			}
			recIDs = jQuery.unique(recIDs);
			top.HEURIST.currentQuery_all_waslimited = (recIDs.length > limit);
			if (top.HEURIST.currentQuery_all_waslimited) {
				//alert("Selected record count is great than 500, opening the first 500 records!");
				recIDs = recIDs.slice(0,limit);
				top.HEURIST.currentQuery_all_limited = true;
			}
			top.HEURIST.currentQuery_all = encodeURI(query_string + '&q=ids:' + recIDs.join(","));

			/*var currentSearchQuery = '';
			var selmode = top.HEURIST.util.getDisplayPreference("showSelectedOnlyOnMapAndSmarty");
			if(selmode=="selected"){
				currentSearchQuery = query_string_sel;
			}else if(selmode=="all"){
				currentSearchQuery = query_string_all;
			}else{
				currentSearchQuery = query_string_main;
			}*/


			top.HEURIST.currentQuery_sel = query_string_sel;
			top.HEURIST.currentQuery_main = query_string_main;

			if(currentTab===_TAB_MAP){ //map

				var mapframe = document.getElementById("map-frame3");
				if(mapframe.src){ //do not reload map frame
					var showMap = mapframe.contentWindow.showMap;
					if(showMap){
						//showMap.setQuery( query_string_all, query_string_sel, query_string_main);
						showMap.processMap(); //reload
					}else{
						//alert('not inited 1');
					}
				}else{
					mapframe.src = top.HEURIST.basePath +
									"viewers/map/showMap.html"; //+"?"+currentSearchQuery;
				}

			}else if (currentTab===_TAB_SMARTY){ //smarty
				var smartyFrame = document.getElementById("smarty-frame");

				if(smartyFrame.src){ //do not reload map frame

					var showReps = smartyFrame.contentWindow.showReps;
					if(showReps){
						///showReps.setQuery( query_string_all, query_string_sel, query_string_main);
						showReps.processTemplate();
					}else{
						//alert('not inited 2');
					}

				}else{
					smartyFrame.src = top.HEURIST.basePath +
									"viewers/smarty/showReps.html"; //+"?"+currentSearchQuery;
				}
			}
	},

	//update related records on map
	//TODO: this code needs to be changed to the application model using events. Special knowledge of applications
	// couples this code to the application and is not maintainable in the long run.
	updateMapRelated: function(){
		top.HEURIST.search.updateMapOrSmarty();
	},

	/*outdated
	mapSelected3: function() {

			var p = top.HEURIST.parameters;
			var query_string = 'ver='+(p['ver'] || "") +
									'&w='+(p['w'] || "") +
									'&stype='+(p['stype'] || "") +
									'&db='+(p['db'] || "") +
									'&limit='+top.HEURIST.search.resultsPerPage;

			if(top.HEURIST.search.currentPage>0){
				query_string = query_string + '&offset=' +
					top.HEURIST.search.currentPage*top.HEURIST.search.resultsPerPage;
			}

			var mapframe = document.getElementById("map-frame3");
			if(mapframe.src){ //do not reload map frame

				var showMap = mapframe.contentWindow.showMap;
				if(showMap){
					var queryp = p["q"];
					var query_string_all = (queryp) ?encodeURI(query_string + '&q=' + queryp) :null;

					var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
					if (recIDs.length >= 500) {// maximum number of records ids 500
						alert("Selected record count is great than 500, mapping the first 500 records!");
						recIDs = recIDs.slice(0,500);
					}
					var query_string_sel = query_string + '&q=ids:' + recIDs.join(",");

					showMap.reload(query_string_all, encodeURI(query_string_sel));

			 		top.HEURIST.search.currentMapSearch = (showMap.isUseAllRecords) ?query_string_all :query_string_sel;
				}
			}else{
				//by default use all records
				query_string = query_string + '&q=' + top.HEURIST.parameters["q"];
			 	url = top.HEURIST.basePath+ "viewers/map/showMap.html?"+query_string;

			 	top.HEURIST.search.currentMapSearch = query_string;

				mapframe.src = url;
			}
	},*/

	/*outdaated
	//ARTEM
	//listener of selection in search result - to refelect on smarty tab
	smartySelected: function() {

			var p = top.HEURIST.parameters;
			var query_string = 'ver='+(p['ver'] || "") +
						'&w='+(p['w'] || "") +
						'&stype='+(p['stype'] || "") +
						'&db='+(p['db'] || "") +
						'&limit='+top.HEURIST.search.resultsPerPage;

			if(top.HEURIST.search.currentPage>0){
				query_string = query_string + '&offset=' +
					top.HEURIST.search.currentPage*top.HEURIST.search.resultsPerPage;
			}

			var repframe = document.getElementById("smarty-frame");
			if(repframe.src){ //do not reload  frame
				var showReps = repframe.contentWindow.showReps;
				if(showReps){

					var queryp = p["q"];
					var query_string_all = (queryp) ?encodeURI(query_string + '&q=' + queryp) :null;

					var recIDs = top.HEURIST.search.getSelectedRecIDs().get();
					if (recIDs.length >= 500) {// maximum number of records ids 500 - it should never happen since max per page is 500
						//alert("Selected record count is great than 500, reporting the first 500 records!");
						recIDs = recIDs.slice(0,500);
					}
					var query_string_sel = query_string + '&q=ids:' + recIDs.join(",");

					showReps.setQuery( query_string_all, encodeURI(query_string_sel));
					showReps.processTemplate();
				}
			}else{
				//by default use all records
				if(top.HEURIST.parameters["q"]){
					query_string = query_string + '&q=' + top.HEURIST.parameters["q"];
				}else{
					//query_string = "noquery";
				}
			 	url = top.HEURIST.basePath+ "viewers/smarty/showReps.html?"+query_string;
				repframe.src = url;
			}
	},*/

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

		var ssel = "selectedIds=" + top.HEURIST.search.getSelectedRecIDs().get().join(",");


		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);
/*art temp old mapping
		var mapFrame = document.getElementById("map-frame");
		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);*/

		top.HEURIST.search.updateMapOrSmarty();
		top.HEURIST.search.setSelectedCount();


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

		var ssel = "selectedIds=" + top.HEURIST.search.getSelectedRecIDs().get().join(",");

		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);
/*art temp old mapping
		var mapFrame = document.getElementById("map-frame");
		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange",  ssel);*/

		top.HEURIST.search.updateMapOrSmarty();
		top.HEURIST.search.setSelectedCount();

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

	deselectAll: function(fireEvent) {
//		_tabView.set('activeIndex', 0); //set printView tab before deselecting to avoid mapping error
		if (!top.HEURIST.search.selectedRecordIds || top.HEURIST.search.selectedRecordIds.length == 0) return false;
		var level = 0;
		for (level; level < top.HEURIST.search.selectedRecordIds.length; level++) {
			while (top.HEURIST.search.selectedRecordIds[level] && top.HEURIST.search.selectedRecordIds[level].length != 0 ) {
				recID = top.HEURIST.search.selectedRecordIds[level][0];
				top.HEURIST.search.deselectResultItem(recID,level);
			}
		}
		top.HEURIST.search.setSelectedCount();

		var viewerFrame = document.getElementById("viewer-frame");

		var recordFrame = document.getElementById("record-view-frame");
		recordFrame.src = top.HEURIST.basePath+"common/html/msgNoRecordsSelected.html";// saw BUG this code shoulw be part of the event response to selection change
		top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", "selectedIds=");

/*art temp old mapping
		var mapFrame = document.getElementById("map-frame");
		mapFrame.src = "";
		top.HEURIST.fireEvent(mapFrame.contentWindow,"heurist-selectionchange", "selectedIds=");*/

		if(fireEvent){
			top.HEURIST.search.updateMapOrSmarty();
		}

		return;
	},

	getSelectedRecIDs: function() {
		var recIDs = {};
		var selectedRecIDs = $("#results-level0 > .recordDiv.selected:not(.filtered),"+
								" #results-level0 > .recordDiv.relateSelected:not(.filtered),"+
								" .recordDiv.selected.lnk:not(.filtered),"+
								" .recordDiv.relateSelected.lnk:not(.filtered)",
								$("#results")).map(function(i,recdiv){
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
			return;
		}else if (recIDs_list.length > bkmkIDs_list.length) {
			// at least one unbookmarked record selected
			hasRecordsNotBkmkd = true;
		}
		top.HEURIST.util.popupURL(window,
					top.HEURIST.basePath+ "records/tags/updateTagsSearchPopup.html?show-remove" + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""),
					{ callback: function(add, tags) {//options
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
						}
					}
		);
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
			callback: function(wg,viewable,hidden,pending) {
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
				vis_elt.value = hidden ? "hidden" : viewable ? "viewable" : pending ? "pending" : "public";
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
			if (top.HEURIST.parameters["q"]  &&  top.HEURIST.parameters["q"].match(/_COLLECTED_/)) {
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
		if (top.HEURIST.parameters["q"].match(/_COLLECTED_/) && refresh) {
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
		top.HEURIST.search.deselectAll(true);
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

	getPushDownFilter: function(type){
		var ret = "",
			prefix;
		switch(type) {
			case 'rectype':
				prefix = 'rt';
				break;
			case 'reltype':
				prefix = 'rel';
				break;
			case 'ptrtype':
				prefix = 'ptr';
				break;
			default://invalid type so return empty
				return ret;
		}
		//find all the filters for loaded levels (this always includes level 0
		var filterMenus = $("div.filter:has(div[class*=loaded],ul#filter0) ul[id^=filter] ul."+type);
		// if there are no menus of this type return empty
		if (!filterMenus.length){
			return ret;
		}
		//for each levels filterMenu if any items are unchecked (user has used filtering)
		// then find id's of all check items
		var i,
			maxLevel = 0,
			filter = {};
		for (i=0; i<filterMenus.length; i++) {
			var level = filterMenus[i].className.match(/level(\d+)/);
			level = level[1];
			maxLevel = parseInt(level) > maxLevel ?  parseInt(level): maxLevel;
			if ($("li:not('[class*=checked],[class*=cmd]')",filterMenus[i]).length && level){//if not all checked then need to specify filtering
				filter[level] = [];
				$("li[class*=checked]:not([class*=disabled])",filterMenus[i]).each(function(){// find all enabled and checked menu items
					filter[level].push( $(this).attr(type));
				});
			}
		}
		ret = YAHOO.lang.JSON.stringify(filter);
		if (ret === "{}"){
			ret = "";
		}else{
			ret = prefix+"filters="+ret;
		}
		return [maxLevel,ret];
	},

	getLayoutString: function(){
		var ret = "layout=srch:",
			prefix;
		//for each level find its style and collapsed state
		// then find id's of all check items
		var i,
			len = 3,
			maxLevel = 0;
		for (i=0; i<=len; i++) {
			var resultsDiv = $("#results-level" + i);
			if (!resultsDiv || resultsDiv.length < 1) {
				break;
			}
			maxLevel = i;
			if (i>0) {// new level exist so add separator
				ret += "-";
			}
			if ($(resultsDiv).hasClass('thumbnails')) {
				ret += "t";
			}else if ($(resultsDiv).hasClass('icons')) {
				ret += "i";
			}else if ($(resultsDiv).hasClass('two-col')) {
				ret += "2";
			} else { // default to list
				ret += "l";
			}
			if ($(resultsDiv).hasClass('collapsed')) {
				ret += "c";
			}
		}
		return [maxLevel,ret];
	},

	exportHML: function(isAll,includeRelated){
		var database = top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
					(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "");

		var q = "",
			rtFilter,relFilter,ptrFilter,
			depth = 0;
		if(isAll){
			q = encodeURIComponent(top.HEURIST.parameters["q"]);//document.getElementById("q").value;
		}else{
			var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs_list.length == 0) {
				alert("Select at least one record to export");
				return false;
			}
			q = "ids:"+recIDs_list.join(",");
		}
		if (includeRelated){
			var rtFilter = top.HEURIST.search.getPushDownFilter('rectype');
			if (rtFilter[0] > depth){ // if filter max depth is greater than depth -> adjust depth
				depth = rtFilter[0];
			}
			rtFilter = rtFilter[1];
			var relFilter = top.HEURIST.search.getPushDownFilter('reltype');
			if (relFilter[0] > depth){
				depth = relFilter[0];
			}
			relFilter = relFilter[1];
			var ptrFilter = top.HEURIST.search.getPushDownFilter('ptrtype');
			if (ptrFilter[0] > depth){
				depth = ptrFilter[0];
			}
			ptrFilter = ptrFilter[1];
		}
		var sURL = "../export/xml/flathml.php?"+
						"w=all"+
						"&a=1"+
						"&depth="+depth +
						"&q=" + q +
						(rtFilter ? "&" + rtFilter : "") +
						(relFilter ? "&" + relFilter : "") +
						(ptrFilter ? "&" + ptrFilter : "") +
						"&db=" + database;

		window.open(sURL, '_blank');
		return false;
	},

	exportKML: function(isAll){
		var database = top.HEURIST.parameters['db'] ? top.HEURIST.parameters['db'] :
					(top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name : "");

		var q = "";
		if(false && isAll){
			q = encodeURIComponent(top.HEURIST.parameters["q"]);//document.getElementById("q").value;
		}else{
			var recIDs_list = top.HEURIST.search.getSelectedRecIDs().get();
			if (recIDs_list.length == 0) {
				alert("Select at least one record to export");
				return false;
			}
			q = "ids:"+recIDs_list.join(",");
		}

		var sURL = "../export/xml/kml.php?w=all&a=1&depth=1&q=" + q + "&db=" + database;

		window.open(sURL, '_blank');
		return false;
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
		document.getElementById("w-input").value = "all";
		top.HEURIST.search.submitSearchForm();
	},

	setContactLink: function() {
		document.getElementById("contact-link").href += "?subject=HEURIST%20v" + top.HEURIST.VERSION +
														"%20user:" + top.HEURIST.get_user_username() +
														(top.HEURIST.parameters["q"]
															? "%20q:" + top.HEURIST.parameters["q"]
															: "") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
	},

 	setHomeLink: function() {
//		 document.getElementById("home-link").href = top.HEURIST.basePath + "index.html";
		 document.getElementById("home-link").href = top.HEURIST.basePath + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
		 document.getElementById("dbSearch-link").href = top.HEURIST.basePath + (top.HEURIST.database && top.HEURIST.database.name ? "?db=" + top.HEURIST.database.name : "");
		 document.getElementById("bookmarklet-link").innerHTML = (top.HEURIST.database && top.HEURIST.database.name ? "<b>>> "+top.HEURIST.database.name + "</b>" : "<b>Bookmarklet</b>");
	},

	writeRSSLinks: function() {

		var link = document.createElement("link");
		link.rel = "alternate";
		link.type = "application/rss+xml";
		link.title = "RSS feed";
		link.href = "feed://"+window.location.host+top.HEURIST.basePath+"export/feeds/searchRSS.php"+(document.location.search ? document.location.search : "?q=" + top.HEURIST.util.getDisplayPreference("defaultSearch")) + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
		document.getElementsByTagName("head")[0].appendChild(link);
		document.getElementById("httprsslink").href += (document.location.search ? document.location.search : "?q=" + top.HEURIST.util.getDisplayPreference("defaultSearch") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
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
				iHTML += "<li><img src='"+ top.HEURIST.iconBaseURL+ rtID+".png'>"+top.HEURIST.rectypes.names[rtID]+"</li>";
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
	if (bib_ids.length < 2 ) {
		alert("Select at least two records to merge duplicate records");
	} else {
		var dim = top.HEURIST.util.innerDimensions(window);
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+"admin/verification/combineDuplicateRecords.php?bib_ids=" + bib_ids.join(",") + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""), {height:dim.h*0.6, width:dim.w*0.6, "no-help":true});
		}
	},

	fixDuplicatesPopup: function() {
		var rec_ids = top.HEURIST.search.getSelectedRecIDs().get();
		if (rec_ids.length < 2 ) {
			alert("Select at least two records to identify/merge duplicate records");
			return;
		}
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath+ "admin/verification/combineDuplicateRecords.php?bib_ids="
		+ rec_ids.join(",")
		+ (top.HEURIST.database && top.HEURIST.database.name ? "&db="
		+ top.HEURIST.database.name : ""),
		{callback : top.HEURIST.search.reloadSearch});
	},


	collectDuplicates: function() {
		var params = top.HEURIST.parameters;
		var args = [];
		if (params['ver']) args.push('ver='+params['ver']);
		if (params['w']) args.push('w='+params['w']);
		if (params['stype']) args.push('stype='+params['stype']);
		if (params['q']) args.push('q='+escape(params['q']));
		var query_string = args.join('&');
		//window.location.href = top.HEURIST.basePath+"admin/verification/listDuplicateRecordsForSearchResults.php?"+ query_string + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : "");
		top.HEURIST.search.popupLink(top.HEURIST.basePath+"admin/verification/listDuplicateRecordsForSearchResults.php?"+ query_string + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
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

/* Depricated
	buildPublishLinks: function() {
		var p = top.HEURIST.parameters;
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
				if (top.HEURIST.parameters["label"] && top.HEURIST.parameters["sid"]) {
					window.open(top.HEURIST.basePath+ "viewers/publish/publisher.php?pub_id=" + top.HEURIST.parameters["sid"] + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
				} else {
					top.HEURIST.util.popupURL(window, top.HEURIST.basePath + 'search/saved/saveSearchPopup.html?publish=yes' + (top.HEURIST.database && top.HEURIST.database.name ? "&db=" + top.HEURIST.database.name : ""));
				}
				return false;
			}
			a.appendChild(document.createTextNode("Publish"));
			//im_container.appendChild(a);
	},

	showPublishPopup: function() {
		var div, a, p, popup, param = top.HEURIST.parameters;
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
*/
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
//		top.HEURIST.search.buildPublishLinks();
//		top.HEURIST.search.showPublishPopup();

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
//		searchForm.appendChild(inputInstance);
		document.getElementsByTagName("body")[0].removeChild(document.getElementById("loadingCover"))
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
		var appPanelStatus = top.HEURIST.util.getDisplayPreference("applicationPanel");
		var searchWidth = top.HEURIST.util.getDisplayPreference("searchWidth");
		var oldSearchWidth = top.HEURIST.util.getDisplayPreference("oldSearchWidth");
		if (!searchWidth || !oldSearchWidth) {
			searchWidth = 400;
		}else if (appPanelStatus != "open"){
			searchWidth = oldSearchWidth;
		};
		var appPanelButton = document.getElementById("appPanelButton");
		var navButton = document.getElementById("navButton");
		var tabBar = document.getElementById("tabbar");
		var searchTable = document.getElementById("search");

		var layout = new YAHOO.widget.Layout(mainbody,{
			units: [
				//{ position: 'top', height: 95, body: 'masthead', header: '', gutter: '0', collapse: false, resize: false },
				{ position: 'bottom', height: 20, resize: false, body: 'footer', gutter: '0', collapse: false },
				{ position: 'left', width: leftWidth, resize: true, useShim: true, body: 'sidebar', gutter: '0', collapse: false, close: false, collapseSize: 0, scroll: false, animate: false, minWidth:150, maxWidth:300},
				{ position: 'center', body: 'page', gutter: '0', minWidth: 400 },
				{ position: 'right', width: searchWidth, resize: true, useShim: true, body: 'page-right', gutter:'0', collapse: false, close: false, collapseSize: 0, scroll: false, animate: false, minWidth:150},
			]
		});


		var setWidths = function() {
			var leftPanelWidth = layout.getSizes().left.w;
			var rightPanelWidth = layout.getSizes().right.w;
			var centerPanelWidth = layout.getSizes().center.w;
			var maxRightWidth = centerPanelWidth + rightPanelWidth -180;
			top.HEURIST.util.setDisplayPreference("leftWidth", leftPanelWidth);
			top.HEURIST.util.setDisplayPreference("searchWidth", rightPanelWidth);
			navButton.style.width = leftPanelWidth-1;
			tabBar.style.right = Math.max(21,rightPanelWidth-1)
			tabBar.style.left = Math.max(21,leftPanelWidth);
			searchTable.style.paddingLeft = (leftPanelWidth+5);
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
		layout.on('render',setWidths);
		layout.render();
		YAHOO.util.Event.addListener(window, "resize", setWidths);

		if (top.HEURIST.util.getDisplayPreference("sidebarPanel") == "closed"){
					layout.getUnitByPosition('left').collapse();
					navButton.className +=" closed";
					searchTable.style.paddingLeft = "5px";
					navButton.style.width = "20px";
					navButton.title = "Show Navigation Panel";
					//tabBar.style.width = layout.getSizes().center.w-29;
					};
		if (top.HEURIST.util.getDisplayPreference("applicationPanel") != "open"){
					layout.getUnitByPosition('right').collapse();
					appPanelButton.className +=" closed";
					appPanelButton.title = "Show Applications";
					};

		Event.on(window, 'resize', layout.resize, layout, true);

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
				layout.resize();
			}else{
				layout.getUnitByPosition('left').expand();
				top.HEURIST.util.setDisplayPreference("sidebarPanel","open");
				navButton.className = navButton.className.replace(" closed", "");
				searchTable.style.paddingLeft = top.HEURIST.util.getDisplayPreference("leftWidth");
				navButton.title = "Hide Navigation Panel";
				layout.resize();
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
				appPanelButton.style.width = "20px";
				appPanelButton.title = "Show Applications";
				layout.resize();
			}else{
				layout.getUnitByPosition('right').expand();
				top.HEURIST.util.setDisplayPreference("applicationPanel","open");
				appPanelButton.className = appPanelButton.className.replace(" closed", "");
				appPanelButton.title = "Hide Applications";
				layout.resize();
			}

		});
		Event.on('resetLayout', 'click', function(ev) {
			Event.stopEvent(ev);
					if (top.HEURIST.util.getDisplayPreference("sidebarPanel") != "open"){
						layout.getUnitByPosition('left').expand();
						top.HEURIST.util.setDisplayPreference("sidebarPanel","open");
						navButton.className = navButton.className.replace(" closed", "");
						navButton.title = "Hide Navigation Panel";
						};
					if (top.HEURIST.util.getDisplayPreference("applicationPanel") != "open"){
						layout.getUnitByPosition('right').expand();
						top.HEURIST.util.setDisplayPreference("applicationPanel","open");
						appPanelButton.className = appPanelButton.className.replace(" closed", "");
						appPanelButton.title = "Hide Applications";
					}
					layout.getUnitByPosition('left').set("width", 180);
					layout.getUnitByPosition('left').resize();
					top.HEURIST.util.setDisplayPreference("leftWidth", 180);
					layout.getUnitByPosition('right').set("width", 400);
					layout.getUnitByPosition('right').resize();
					top.HEURIST.util.setDisplayPreference("searchWidth", 400);
					tabBar.style.width = layout.getSizes().center.w - 9;
				});

	});

	_tabView = new YAHOO.widget.TabView('applications', { activeIndex: viewerTabIndex });
	//if (viewerTabIndex == _TAB_MAP){top.HEURIST.search.mapSelected()} //initialises map
	/*
	if (Number(viewerTabIndex) === _TAB_MAP){top.HEURIST.search.mapSelected3()} //initialises new map
	else if (Number(viewerTabIndex) === 3){top.HEURIST.search.smartySelected()}; //initialises smarty repsystem
	*/

	var handleActiveTabChange = function(e) {
		var currentTab = _tabView.getTabIndex(_tabView.get('activeTab'));
		top.HEURIST.util.setDisplayPreference("viewerTab", currentTab);

		if(currentTab===_TAB_SPECIAL){ //printview

			var viewerFrame = document.getElementById("viewer-frame");
			var selectedRecIDs = top.HEURIST.search.getSelectedRecIDs().get();
			var ssel = "selectedIds=" + selectedRecIDs.join(",");
			top.HEURIST.fireEvent(viewerFrame.contentWindow,"heurist-selectionchange", ssel);

		}else  if(currentTab===_TAB_MAP){ //map

			var mapFrame3 = document.getElementById("map-frame3");
			if(mapFrame3.src && mapFrame3.contentWindow.showMap){
				mapFrame3.contentWindow.showMap.setQueryMode(
						top.HEURIST.util.getDisplayPreference("showSelectedOnlyOnMapAndSmarty"));
				mapFrame3.contentWindow.showMap.checkResize(); //to fix gmap bug
			}
			top.HEURIST.search.updateMapOrSmarty();

		}else if (currentTab===_TAB_SMARTY){ //smarty
			var smartyFrame = document.getElementById("smarty-frame");
			if(smartyFrame.src && smartyFrame.contentWindow.showReps){
				smartyFrame.contentWindow.showReps.setQueryMode(
						top.HEURIST.util.getDisplayPreference("showSelectedOnlyOnMapAndSmarty"), false);
			}
			top.HEURIST.search.updateMapOrSmarty();
		}

	};
	_tabView.addListener('activeTabChange', handleActiveTabChange);
	_tabView.getTab(viewerTabIndex);
	//if (viewerTabIndex == _TAB_MAP){top.HEURIST.search.mapSelected()} //initialises map
	/*
	if (Number(viewerTabIndex) === _TAB_MAP){top.HEURIST.search.mapSelected3()} //initialises new map
	else if (Number(viewerTabIndex) === 3){top.HEURIST.search.smartySelected()}; //initialises smarty reports
	*/

	_tabView.addListener('activeTabChange',handleActiveTabChange);
