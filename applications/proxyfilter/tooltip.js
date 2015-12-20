
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
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
				var url = baseURL + myClass.replace(/-/, "/");
				$preview.load(url, function () {
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
	$previews = $("#previews div");
	if ($previews.length <= DOS.ToolTip.preloadLimit) {
		// load preview contents
		$previews.each(function () {
			$(this).load(this.id.replace(/^preview-(.*)$/, baseURL + "preview/$1"));
		});
	}

	DOS.ToolTip.addPreviewToolTips($("a[class*=preview-]:not(.annotation)"));
	DOS.ToolTip.addSearchToolTips($("a.search-tooltip"));
});

