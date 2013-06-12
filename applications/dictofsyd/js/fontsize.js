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
* Change font size for entire website and stores it in cookies
*
* @author      Kim Jackson
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
(function () {

/* Private variables and functions */

var _cookieName = "DoSFontSizeNum";
var _classNameBase = "fontSize";
var _minSize = -1;
var _maxSize = 3;
var _defaultSize = 0;

var _set = function (n) {
	var matches, i;

	if (n < _minSize) {
		n = _minSize;
	}
	if (n > _maxSize) {
		n = _maxSize;
	}

	if ($("body").hasClass(_classNameBase + n)) {
		return;
	}
	matches = $("body").attr("class").match(/fontSize-?\d+/g);
	if (matches) {
		for (i = 0; i < matches.length; ++i) {
			$("body").removeClass(matches[i]);
		}
	}
	if (n != 0) {
		$("body").addClass(_classNameBase + n);
	}

	if (window.alignImages) {
		alignImages();
	}
	if (RelBrowser.Mapping  &&
		RelBrowser.Mapping.tmap  &&
		RelBrowser.Mapping.tmap.timeline) {
		RelBrowser.Mapping.tmap.timeline.layout();
	}
	writePersistentCookie(_cookieName, n, "days", 5);
};


if (! window.RelBrowser) { RelBrowser = {baseURL: "../"}; }

RelBrowser.FontSize = {

	restore: function () {
		var n = getCookieValue(_cookieName);
		if (n !== false  &&  n != 0) {
			_set(parseInt(n));
		}
	},

	increase: function () {
		var n = getCookieValue(_cookieName);
		if (n === false) {
			n = _defaultSize;
		}
		_set(parseInt(n) + 1);
	},

	decrease: function () {
		var n = getCookieValue(_cookieName);
		if (n === false) {
			n = _defaultSize;
		}
		_set(parseInt(n) - 1);
	}
};

})();


$(function () {
	RelBrowser.FontSize.restore();
	$(".increasefont").click(function () {
		RelBrowser.FontSize.increase();
		return false;
	});
	$(".decreasefont").click(function () {
		RelBrowser.FontSize.decrease();
		return false;
	});
});
