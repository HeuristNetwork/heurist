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
*  Class to store history of visited pages. They are rendered as breadcrumbs on top of the page
*
* @author      Kim Jackson
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
(function () {

/* Private variables and functions */

var _cookieName = "DoSHistory";
var _cookieLengthLimit = 4000;
var _maxEntries = 20;

var _encode = function (entries) {
	var i, s = "";
	for (i = 0; i < entries.length; ++i) {
		if (s !== "") {
			s += "|";
		}
		s += [
			escape(entries[i].url) || "",
			entries[i].id || "",
			entries[i].type || ""
		].join(",");
	}
	return s;
};

var _decode = function (s) {
	var i, entries;
	if (! s) {
		return [];
	}
	entries = s.split("|");
	if (! entries  ||  entries.length < 1) {
		return [];
	}
	for (i = 0; i < entries.length; ++i) {
		fields = entries[i].split(",");
		entries[i] = {
			url: unescape(fields[0]),
			id: fields[1],
			type: fields[2]
		};
	}
	return entries;
};


if (! window.RelBrowser) { RelBrowser = {baseURL: "../"}; }

RelBrowser.History = {

	get: function () {
		return _decode(getCookieValue(_cookieName));
	},

	add: function () {
		var s, entries, current, latest;

		entries = RelBrowser.History.get();
		current = {
			url: document.location.href,
			id: $("meta[name=id]").attr("content"),
			type: $("meta[name=class]").attr("content")
		};

		if (! current.id) {
			// maybe this is a search page?
			matches = location.search.match(/zoom_query=([^&]*)/);
			if (matches  && matches[1]) {
				current.id = matches[1];
				current.type = "search";
			} else {
				return;
			}
		}

		if (entries.length > 0) {
			latest = entries[entries.length - 1];
			if (latest  &&
				latest.url === current.url  &&
				latest.type === current.type) {
				return;
			}
		}

		entries.push(current);
		while (entries.length > _maxEntries) {
			entries.shift();
		}

		s = _encode(entries);
		while (s.length > _cookieLengthLimit) {
			entries.shift();
			s = _encode(entries);
		}

		writePersistentCookie(_cookieName, s, "days", 5);
	},

	show: function (elem) {
		// this needs to happen before DOS.ToolTip.addPreviewToolTips()
		// in order for previews to be loaded
		var entries, i, e, className;

		entries = RelBrowser.History.get();
		for (i = 0; i < entries.length; ++i) {
			e = entries[i];
			className = e.type === "search" ? "search-tooltip" : "preview-" + e.id;
			$(elem).append(
				"<div class='breadcrumb nav-" + e.type + "'>" +
					"<a href='" + e.url + "' class='" + className + "'>" +
						"<img src='" + RelBrowser.baseURL + "images/16x16-clear.gif'/>" +
						(e.type === "search" ? "<div class='search-term'>" + unescape(e.id).replace('+', ' ') + "</div>" : "") +
					"</a>" +
				"</div>"
			);

			if (e.type !== "search") {
				if ($("#previews #preview-" + entries[i].id).length < 1) {
					$("#previews").append(
						"<div class='preview' id='preview-" + e.id + "'/>"
					);
				}
			}
		}
		$(elem).append("<div class='clearfix'/>");
	}
};

})();


$(function () {
	RelBrowser.History.add();
	RelBrowser.History.show($("#breadcrumbs").get(0));
});
