/*
* Copyright (C) 2005-2013 University of Sydney
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
*   Render for browse page.
*   content (javascript arrays) is generated in gen_browse.php
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


if (! window.DOS) { DOS = {}; }

DOS.Browse = (function () {

	var _chunkSize = 50;
	var _sortBy = null;

	function _getSubtypes (ids) {
		var i, s = "";
		if (DOS.Browse.subtypes.length > 0) {
			for (i = 0; i < ids.length; ++i) {
				if (DOS.Browse.subtypes[ids[i]] && DOS.Browse.subtypes[ids[i]][0]){
					if (i > 0) {
						s+= ", ";
					}
					s += DOS.Browse.subtypes[ids[i]][0];
				}
			}
		}
		return s;
	}

	function _getSortingLetter (name) {
		//var matches = name.match(/^('|the )?(.)/i);

        if(name.indexOf("'")==0){
            name = name.substring(1);
        }

        var matches = name.match(/^(the |a |an )?(.)/i);
		if (matches  &&  matches[2]) {
			return matches[2].toUpperCase();
		}
	}

	function _getHref (id, title) {

		return RelBrowser.baseURL + id; //ARTEM

		DOS.Browse.pathBase	= RelBrowser.baseURL;

		if (DOS.Browse.pathBase) {
			if (DOS.Browse.pathBase === "map") {
				return "../map/" + id;
			}else if (DOS.Browse.pathBase === "multimedia") {
				return "../item/" + id;
			} else {
				return "../" + DOS.Browse.pathBase + "/" + escape(
					title.toLowerCase()
						.replace(/ /g, "_")
						.replace(/,/g, "")
						.replace(/'/g, "")
				);
			}
		} else {
			return "../item/" + id;
		}
	}

return {

	// alphabetic list
	renderAlphaList: function(offset, heading, $ul) {
		var id, entity, title, href, newheading, limit, i, className, shadeRows;

		shadeRows = false;

		if (! offset) {
			offset = 0;
		}

		limit = Math.min(offset + _chunkSize, DOS.Browse.orderedEntities.length);

		for (i = offset; i < limit; ++i) {
			id = DOS.Browse.orderedEntities[i];
			entity = DOS.Browse.entities[id];
			title = entity[0];
			href = _getHref(id, title);

			newheading = _getSortingLetter(title);
			if (newheading < "0") {
				newheading = "Symbols";
			} else if (newheading < "A") {
				newheading = "0-9";
			}
			if (newheading != heading) {
				heading = newheading;
				$("#browse-alpha-index").append("<li><a href='#"+heading+"'>"+heading+"</a></li>");
				$("#entities-alpha").append("<h2 id='"+heading+"'>"+heading+"</h2>");
				$ul = $("<ul/>").appendTo("#entities-alpha");
			}

			className = entity[2] ? " class='has-entry'" : "";
			if (entity[1]) {
				$ul.append("<li"+className+"><div class='left'><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></div><div class='right'>"+_getSubtypes(entity[1])+"</div><div class='clearfix'/></li>");
				shadeRows = true;
			} else {
				$ul.append("<li"+className+"><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></li>");
			}
		}


		if (limit === DOS.Browse.orderedEntities.length) {
			$("#browse-alpha-index").append("<div class='clearfix'/>");
			if (! _sortBy) {
				$("#loading").remove();
				_sortBy = "alpha";
				DOS.Browse.sortByName();
			}
			if (shadeRows) {
				// alternate row shading
				$("#entities-alpha ul").each(function () {
					$(this).find("li:odd").addClass("shade"); }
				);
			}
			DOS.Browse.renderSortbyLinks();
		}
		else {
			setTimeout(function () {
				DOS.Browse.renderAlphaList(limit, heading, $ul);
			}, 0);
		}
	},

	// list by sub-type
	renderSubTypeList: function(i) {
		var id, entity, title, href, type, subtype, entityIDs, $ul, className;

		if (! i) {
			i = 0;
		}

		if (! DOS.Browse.orderedSubtypes.length) {
			DOS.Browse.renderAlphaList();
			return;
		}

		type = DOS.Browse.orderedSubtypes[i];
		subtype = DOS.Browse.subtypes[type];

		if (DOS.Browse.orderedSubtypes.length > 1) {
			$("#browse-type-index").append("<li><a href='#"+type+"'>"+subtype[0]+"</a></li>");
		}

		$("#entities-type").append("<h2 id='"+type+"'>"+subtype[0]+"</h2>");
		entityIDs = subtype[1];
		$ul = $("<ul/>").appendTo("#entities-type");
		for (j = 0; j < entityIDs.length; ++j) {
			id = entityIDs[j];
			entity = DOS.Browse.entities[id];
			title = entity[0];
			href = _getHref(id, title);
			if (entity) {
				className = entity[2] ? " class='has-entry'" : "";
				$ul.append("<li"+className+"><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></li>");
			}
		}

		if (i + 1 === DOS.Browse.orderedSubtypes.length) {
			$("#browse-type-index").append("<div class='clearfix'/>");

			if (! _sortBy) {
				if (DOS.Browse.orderedSubtypes[0] != "Thematic") {
					$("#loading").remove();
					_sortBy = "subtype";
					DOS.Browse.sortByType();
				}
			}
			DOS.Browse.renderAlphaList();
		}
		else {
			setTimeout(function () {
				DOS.Browse.renderSubTypeList(i + 1);
			}, 0);
		}
	},

	// list by licence type
	renderLicenceTypeList: function(i) {
		var id, entity, title, href, licence, licenceName, licenceIcon, entityIDs, $ul, className;

		if (! i) {
			i = 0;
		}

		if (! DOS.Browse.orderedLicenceTypes  ||  ! DOS.Browse.orderedLicenceTypes.length) {
			DOS.Browse.renderSubTypeList();
			return;
		}

		licence = DOS.Browse.orderedLicenceTypes[i];

		licenceName = "Other";
		licenceIcon = "";

		if (licence === "CC-Generic") {
			licenceName = "Creative Commons";
			licenceIcon = " <a rel='license' target='_blank' href='http://creativecommons.org/licenses/by/2.5/au/'>"
				+ "<img alt='Creative Commons License' src='http://i.creativecommons.org/l/by/2.5/au/80x15.png'/></a>";
		}
		if (licence === "CC-SA") {
			licenceName = "Creative Commons";
			licenceIcon = " <a rel='license' target='_blank' href='http://creativecommons.org/licenses/by-sa/2.5/au/'>"
				+ "<img alt='Creative Commons License' src='http://i.creativecommons.org/l/by-sa/2.5/au/80x15.png'/></a>";
		}

		if (DOS.Browse.orderedLicenceTypes.length > 1) {
			$("#browse-licence-index").append("<li><a href='#"+licence+"'>"+licenceName+"</a></li>");
		}

		entityIDs = DOS.Browse.licenceTypes[licence];

		$("#entities-licence").append("<h2 id='"+licence+"'>" + licenceName + licenceIcon + "</h2>");
		$ul = $("<ul/>").appendTo("#entities-licence");
		for (j = 0; j < entityIDs.length; ++j) {
			id = entityIDs[j];
			entity = DOS.Browse.entities[id];
			title = entity[0];
			href = _getHref(id, title);
			if (entity) {
				className = entity[2] ? " class='has-entry'" : "";
				$ul.append("<li"+className+"><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></li>");
			}
		}

		if (i + 1 === DOS.Browse.orderedLicenceTypes.length) {
			$("#browse-licence-index").append("<div class='clearfix'/>");
			DOS.Browse.renderSubTypeList();
		}
		else {
			setTimeout(function () {
				DOS.Browse.renderLicenceTypeList(i + 1);
			}, 0);
		}
	},


	// split into entities with entries and those without
	renderContentList: function(offset) {
		var id, entity, title, href, limit, i, $ul, className, shadeRows;

		shadeRows = false;

		if (! offset) {
			offset = 0;
		}

		limit = Math.min(offset + _chunkSize, DOS.Browse.orderedEntities.length);

		for (i = offset; i < limit; ++i) {
			id = DOS.Browse.orderedEntities[i];
			entity = DOS.Browse.entities[id];
			title = entity[0];
			href = _getHref(id, title);

			className = entity[2] ? " class='has-entry'" : "";
			$ul = entity[2] ? $("#entities-with-entries") : $("#entities-without-entries");

			if (entity[1]) {
				$ul.append("<li"+className+"><div class='left'><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></div><div class='right'>"+_getSubtypes(entity[1])+"</div><div class='clearfix'/></li>");
				shadeRows = true;
			} else {
				$ul.append("<li"+className+"><a class='preview-"+id+"' href='"+href+"'>"+title+"</a></li>");
			}
		}

		if (limit === DOS.Browse.orderedEntities.length) {
			if ($("#entities-with-entries li").length > 0) {
				$("#entities-content h2").show();
				$("#loading").remove();
				_sortBy = "content";
				DOS.Browse.sortByContent();
			} else {
				$("#entities-content").empty();
			}

			if (shadeRows) {
				// alternate row shading
				$("#entities-content ul").each(function () {
					$(this).find("li:odd").addClass("shade"); }
				);
			}
			DOS.Browse.renderLicenceTypeList();
		}
		else {
			setTimeout(function () {
				DOS.Browse.renderContentList(limit);
			}, 0);
		}
	},


	renderSortbyLinks: function() {
		var entries, types, licences;

		entries = $("#entities-with-entries li").length > 0;
		types = DOS.Browse.orderedSubtypes.length > 0;
		licences = DOS.Browse.orderedLicenceTypes  &&  DOS.Browse.orderedLicenceTypes.length > 0;

		if (entries && types) {
			// Sort by Content, Name or Type
			$("#sub-title").append("Sort by <a id='content-sort-link' href='#'>Content</a>")
			               .append(", <a id='name-sort-link' href='#'>Name</a>")
			               .append(" or <a id='type-sort-link' href='#'>Type</a>");
		} else if (entries) {
			// Sort by Content or Name
			$("#sub-title").append("Sort by <a id='content-sort-link' href='#'>Content</a>")
			               .append(" or <a id='name-sort-link' href='#'>Name</a>")
		} else if (types && licences) {
			// Sort by Name or Type or Licence type
			$("#sub-title").append("Sort by <a id='name-sort-link' href='#'>Name</a>")
			               .append(", <a id='type-sort-link' href='#'>Type</a>")
			               .append(" or <a id='licence-sort-link' href='#'>Licence type</a>");
		} else if (types) {
			// Sort by Name or Type
			$("#sub-title").append("Sort by <a id='name-sort-link' href='#'>Name</a>")
			               .append(" or <a id='type-sort-link' href='#'>Type</a>");
		}

		if (_sortBy === "content") {
			$('#content-sort-link').addClass('selected');
		} else if (_sortBy === "subtype") {
			$('#type-sort-link').addClass('selected');
		} else {
			$('#name-sort-link').addClass('selected');
		}

		$('#type-sort-link').click(DOS.Browse.sortByType);
		$('#name-sort-link').click(DOS.Browse.sortByName);
		$('#content-sort-link').click(DOS.Browse.sortByContent);
		$('#licence-sort-link').click(DOS.Browse.sortByLicence);
	},

	render: function() {
		$("#entities-alpha, #browse-alpha-index, #entities-type, #browse-type-index, #entities-licence, #browse-licence-index, #entities-content").hide();
		DOS.Browse.renderContentList();
	},

	sortByName: function() {
		$('#type-sort-link, #licence-sort-link, #content-sort-link').removeClass('selected');
		$('#name-sort-link').addClass('selected');
		$("#entities-type, #browse-type-index, #entities-licence, #browse-licence-index, #entities-content").hide();
		$("#entities-alpha, #browse-alpha-index").show();
		return false;
	},

	sortByType: function() {
		$('#name-sort-link, #licence-sort-link, #content-sort-link').removeClass('selected');
		$('#type-sort-link').addClass('selected');
		$("#entities-alpha, #browse-alpha-index, #entities-licence, #browse-licence-index, #entities-content").hide();
		$("#entities-type, #browse-type-index").show();
		return false;
	},

	sortByLicence: function() {
		$('#name-sort-link, #type-sort-link, #content-sort-link').removeClass('selected');
		$('#licence-sort-link').addClass('selected');
		$("#entities-alpha, #browse-alpha-index, #entities-type, #browse-type-index, #entities-content").hide();
		$("#entities-licence, #browse-licence-index").show();
		return false;
	},

	sortByContent: function() {
		$('#name-sort-link, #type-sort-link, #licence-sort-link').removeClass('selected');
		$('#content-sort-link').addClass('selected');
		$("#entities-alpha, #browse-alpha-index, #entities-type, #browse-type-index, #entities-licence, #browse-licence-index").hide();
		$("#entities-content").show();
		return false;
	}
}

})();

//$(DOS.Browse.render);
