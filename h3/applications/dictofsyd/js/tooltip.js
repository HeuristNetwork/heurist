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
* Show tooltips for images and links
*
* @author      Kim Jackson
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
if (! window.DOS) { DOS = {}; }

DOS.ToolTip = {

	preloadLimit: 50,

	showToolTip: function (e, $elem) {
		if ('ontouchstart' in document.documentElement ) {
			return
		}else{
		$(".preview").hide();
		var maxX, minY, x, y;
		$elem.show();
		maxX = $(window).width() + $(window).scrollLeft();
		minY = $(window).scrollTop();
		x = e.pageX;
		y = e.pageY - $elem.height() - 5;
		if (x + $elem.width() > maxX) {
			x = maxX - $elem.width();
		}
		if (y < minY) {
			// if the tooltip goes off the top of the page, flip it down below the cursor instead
			y = e.pageY + 5;
		}
		$elem.css({'left':x+'px','top':y+'px'});
		}
	},

	makeToolTip: function(type, title) {
		return $(
			"<div class='preview balloon-container'>" +
				"<div class='balloon-top'/>" +
				"<div class='balloon-middle'>" +
					"<div class='balloon-heading balloon-" + type + "'>" +
						"<h2>" + title + "</h2>" +
					"</div>" +
				"</div>" +
				"<div class='balloon-bottom'/>" +
			"</div>"
		);
	},

	//popup for all links
	addPreviewToolTips: function ($elems) {
		$elems.live("mouseover", function (e) {
			var myClass, $preview, $that;
			myClass = $(this).attr("class").replace(/.*(preview-\S+).*/, "$1");
			$preview = $("#"+myClass);
			if (! $preview.length) {
				$preview = $("<div class='preview' id='" + myClass + "'/>").appendTo("#previews");
			}
			if ($preview.children().length > 0) {
				// preview content is loaded
				DOS.ToolTip.showToolTip(e, $preview);
			} else {
				$that = $(this);
				$that.data("loading", true);
				$preview.load(RelBrowser.baseURL + myClass.replace(/-/, "/"), function () {
					if ($that.data("loading")) {
						$that.removeData("loading");
						DOS.ToolTip.showToolTip(e, $preview);
					}
				});
			}
		}).live("mouseout", function () {
			$(this).removeData("loading");
			var myClass = $(this).attr("class").replace(/.*(preview-\S+).*/, "$1");
			$("#"+myClass).hide();
		});
	},

	addSearchToolTips: function ($elems) {
		$elems.hover(function (e) {
			var term, $tooltip;
			$tooltip = $(this).data("tooltip");
			if (! $tooltip) {
				term = $(this).find(".search-term").text();
				$tooltip = DOS.ToolTip.makeToolTip("search", "Search: " + term).appendTo("#previews");
				$(this).data("tooltip", $tooltip);
			}
			DOS.ToolTip.showToolTip(e, $tooltip);
		}, function () {
			$(this).data("tooltip").hide();
		});
	}
};

$(document).ready(function () {
/* ARTEM TODO
	$previews = $("#previews div");
	if ($previews.length <= DOS.ToolTip.preloadLimit) {
		// load preview contents
		$previews.each(function () {
			$(this).load(this.id.replace(/^preview-(.*)$/, RelBrowser.baseURL + "preview/$1"));
		});
	}
*/
	DOS.ToolTip.addPreviewToolTips($("a[class*=preview-]:not(.annotation)"));
	DOS.ToolTip.addSearchToolTips($("a.search-tooltip"));
});

