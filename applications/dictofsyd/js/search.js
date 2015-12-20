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
* Render search results
*
* @author      Kim Jackson
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
if (! window.DOS) { DOS = {}; }

DOS.Search = {

	searchPrompt: "search..."

};

$(function () {

	var matches, query;

	$("#search-submit")
		.click(function () {
			$("#search-bar form").submit();
		})
		.hover(function () {
			$("#search-bar").addClass("active");
		}, function () {
			$("#search-bar").removeClass("active");
		});

	matches = location.search.match(/zoom_query=([^&]+)/);
	query = matches ? unescape(matches[1]).replace(/\+/g, " ") : null;

	$("#search, #bigsearch").val(query ? query : DOS.Search.searchPrompt)
		.focus(function () {
			if (this.value === DOS.Search.searchPrompt) {
				this.value = "";
			}
		})
		.blur(function () {
			if (this.value === "") {
				this.value = DOS.Search.searchPrompt;
			}
		});

	// process search results
	if ($(".results").length > 0) {
		$(".result_block, .result_altblock").each(function () {
			var id, className;

			id = $(".result_metavalue_id", this).text();
			className = $(".result_metavalue_class", this).text();

			$(".result_title a", this).addClass("preview-" + id);
			$(this).addClass("list-" + className);

			// this needs to happen before DOS.ToolTip.addToolTips()
			// in order for previews to be loaded
			$("#previews").append(
				"<div class='preview' id='preview-" + id + "'/>"
			);
		});
	}
});

