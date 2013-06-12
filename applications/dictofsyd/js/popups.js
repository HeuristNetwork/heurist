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
* Loads popup using jquery-boxy
*
* @author      Kim Jackson
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
$(document).ready(function () {

	Boxy.DEFAULTS.unloadOnHide = true;

	if (!('ontouchstart' in document.documentElement || navigator.userAgent.match(/MSIE [7|8]\./))) {
		$(".popup").click(function () {
			var matches, src;

			matches = this.href.match(/\d+$/);
			if (matches) {
				src = RelBrowser.baseURL + "popup/" + matches[0];
			} else {
				src = this.href;
			}

			Boxy.load(src, {
				center: true,
				modal: true,
				afterShow: function () {
					var that = this;
					$("img", this.boxy).load(function () {
						that.center();
					});
					$("#audio a", this.boxy).each(function () {
						DOS.Media.playAudio('audio', $(this).attr("href"));
					});
					$("#video a", this.boxy).each(function () {
						DOS.Media.playVideo('video', $(this).attr("href"));
					});
					$("#citation a", this.boxy).each(function () {
						that.center();
					});
				}
			});
		return false;
		});
	}

	$(".citation-link").click(function () {
	if ('ontouchstart' in document.documentElement || navigator.userAgent.match(/MSIE [7|8]\./)) {
		var matches, src;
		src = this.href;
		$('#content').load(src, function() {
				$(".citation-container").width("auto");
				var months = new Array("January","February","March","April","May","June","July","August","September","October","November","December");
				var day = ((new Date).getDate()<10 ? "0"+(new Date).getDate() : (new Date).getDate());
				var month = months[(new Date).getMonth()];
				var year = (new Date).getFullYear();
				var today = day +" "+month+ " "+year;

				$(".date").html(today);
				$(".citation-close").click(function() {
					location.reload(true);
				});
		});
	}else{
		var matches, src;
			src = this.href;
			Boxy.load(src, {
				center: true,
				modal: true,
				afterShow: function () {
					var that = this;
					$("a", this.boxy).each(function () {
						that.center();
						var months = new Array("January","February","March","April","May","June","July","August","September","October","November","December");
						var day = ((new Date).getDate()<10 ? "0"+(new Date).getDate() : (new Date).getDate());
						var month = months[(new Date).getMonth()];
						var year = (new Date).getFullYear();
						var today = day +" "+month+ " "+year;
						$(".date").html(today);
					});
				}
			});}
	return false;

	});
});
