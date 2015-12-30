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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

var BrowserDetect = {
	init: function () {
		this.browser = this.searchString(this.dataBrowser) || "other";
		this.version = this.searchVersion(navigator.userAgent) || this.searchVersion(navigator.appVersion) || "other";
		this.OS = this.searchString(this.dataOS) || "an unknown OS";
	},
	searchString: function (data) {
		for (var i=0;i<data.length;i++)	{
			var dataString = data[i].string;
			var dataProp = data[i].prop;
			this.versionSearchString = data[i].versionSearch || data[i].identity;
			if (dataString) {
				if (dataString.indexOf(data[i].subString) != -1)
					return data[i].identity;
			}
			else if (dataProp)
				return data[i].identity;
		}
	},
	searchVersion: function (dataString) {
		var index = dataString.indexOf(this.versionSearchString);
		if (index == -1) return;
		return parseFloat(dataString.substring(index+this.versionSearchString.length+1));
	},
	dataBrowser: [
		{
			string: navigator.userAgent,
			subString: "Chrome",
			identity: "Chrome"
		},
//		{ 	string: navigator.userAgent,
//			subString: "OmniWeb",
//			versionSearch: "OmniWeb/",
//			identity: "OmniWeb"
//		},
		{
			string: navigator.vendor,
			subString: "Apple",
			identity: "Safari",
			versionSearch: "Version"
		},
//		{
//			prop: window.opera,
//			identity: "Opera",
//			versionSearch: "Version"
//		},
//		{
//			string: navigator.vendor,
//			subString: "iCab",
//			identity: "iCab"
//		},
//		{
//			string: navigator.vendor,
//			subString: "KDE",
//			identity: "Konqueror"
//		},
		{
			string: navigator.userAgent,
			subString: "Firefox",
			identity: "Firefox"
		},
//		{
//			string: navigator.vendor,
//			subString: "Camino",
//			identity: "Camino"
//		},
//		{		// for newer Netscapes (6+)
//			string: navigator.userAgent,
//			subString: "Netscape",
//			identity: "Netscape"
//		},
		{
			string: navigator.userAgent,
			subString: "MSIE",
			identity: "Explorer",
			versionSearch: "MSIE"
		},
		{
			string: navigator.userAgent,
			subString: "Gecko",
			identity: "Mozilla",
			versionSearch: "rv"
		},
//		{ 		// for older Netscapes (4-)
//			string: navigator.userAgent,
//			subString: "Mozilla",
//			identity: "Netscape",
//			versionSearch: "Mozilla"
//		}
	],
	dataOS : [
		{
			string: navigator.platform,
			subString: "Win",
			identity: "Windows"
		},
		{
			string: navigator.platform,
			subString: "Mac",
			identity: "Mac"
		},
		{
			string: navigator.userAgent,
			subString: "iPhone",
			identity: "iPhone/iPod"
		},
		{
			string: navigator.platform,
			subString: "Linux",
			identity: "Linux"
		},
		{
			string: navigator.platform,
			subString: "android",
			identity: "android"
		}
	]

};
BrowserDetect.init();
var browserString = BrowserDetect.OS+" "+BrowserDetect.browser+" "+BrowserDetect.version;
var browser;
if ((BrowserDetect.browser == "Explorer") || (BrowserDetect.browser == "other") || (BrowserDetect.browser == "Firefox" && BrowserDetect.version < 6) || (BrowserDetect.browser == "Safari" && BrowserDetect.version < 5) || (BrowserDetect.browser == "Chrome" && BrowserDetect.version < 10)) {
		window.open("../common/html/browserErrorMsg.html?msg="+browserString, "_self");
}




