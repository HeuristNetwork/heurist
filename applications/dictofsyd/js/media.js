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
* Embed flash player for audio/video and menu
*
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
if (! window.DOS) { DOS = {}; }

DOS.Media = {

	embed: function (swf, width, height, elemID, flashvars) {
		var params, attributes;

		params = {
			play: "true",
			loop: "true",
			menu: "false",
			quality: "high",
			scale: "showall",
			salign: "t",
			bgcolor: "#000000",
			wmode: "opaque",
			devicefont: "false",
			seamlesstabbing: "true",
			swliveconnect: "false",
			allowfullscreen: "false",
			allowscriptaccess: "sameDomain",
			allownetworking: "all"
		};

		attributes = {
			id: elemID,
			name: "player",
			align: "middle"
		};

		swfobject.embedSWF(swf, elemID, width, height, "8", "swf/expressInstall.swf", flashvars, params, attributes);
	},

	playAudio: function (elemID, file) {
		var flashvars = {
			src: file,
			length: 100,
			title: ""
		};
		if ('ontouchstart' in document.documentElement) {
			document.write("<audio src='"+file+".mp3' controls='controls' style='width:60%; height:100px; background-image:url(../images/img-entity-audio.jpg);background-repeat:no-repeat;background-size: 100% 100%;background-position:bottom'></audio>");
		}else{
			DOS.Media.embed((RelBrowser.baseURL && RelBrowser.baseURL != "" ? RelBrowser.baseURL:"../")+"swf/audio-player.swf", "358", "87", elemID, flashvars);
		}
	},

	playVideo: function (elemID, file, image) {
		var flashvars = {
			videoPath: file,
			autoStart: true,
			autoHide: true,
			autoHideTime: 3,
			hideLogo: true,
			volAudio: 100,
			disableMiddleButton: false
		};

		if (image) {
			flashvars.imagePath = image;
			flashvars.autoStart = false;
		}
		if ('ontouchstart' in document.documentElement) {
			document.write("<video controls='controls' style='width:60%;'><source src='"+file+".3gp' type='video/3gpp'></video>");
		}else{
			DOS.Media.embed((RelBrowser.baseURL && RelBrowser.baseURL != "" ? RelBrowser.baseURL:"../")+"swf/video-player.swf", "424", "346", elemID, flashvars);
		}
	},

	embedBrowser: function (elemID) {
		DOS.Media.embed( RelBrowser.baseURL +"swf/dosMenu.swf", "700", "320", elemID,null);
	}


};
