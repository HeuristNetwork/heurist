/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Creates popup div with Gmap to view given coordinates  
* 
* requires
* hclient/core/hintDiv.js (popup div)
* need to define mapStaticURL!!!
*
* used in rendereRecordData.php
* 
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Map  
*/
var mapStaticURL = '';
if(window.hWin && window.hWin.HAPI4){
    mapStaticURL = window.hWin.HAPI4.baseURL+"viewers/gmap/mapStatic.php?width=300&height=300&db="+window.hWin.HAPI4.database;
}

function MapViewer() {

	//private members
	var _className = "MapViewer";
    							//id                               content
	var hintDiv = new HintDiv('mapPopup', 300, 300, '<div id="map_viewer" style="width:100%;height:100%;"></div>');


	function _showAt(event, geovalue)
	{
			hintDiv.showAt(event);

			initmap_viewer('map_viewer', geovalue); //from digitizer.js
	}

	function _showAtStatic(event, recid, value)
	{
			hintDiv.showAt(event);

			//add image with url to static google map
			var mapImg = this.document.getElementById('map_static_image');
			if(!mapImg){
				var map_viewer = this.document.getElementById('map_viewer');
				mapImg = map_viewer.appendChild(this.document.createElement("img"));
				mapImg.id = "map_static_image";
			}
			var d = new Date().getTime()

			var surl = mapStaticURL+"&t="+d;

			if(value){
				surl = surl + "&value="+encodeURIComponent(value);
			}else{
				surl = surl + "&q=ids:"+recid;
			}
			mapImg.src = surl;
	}


	//public members
	var that = {

		showAt: function(event, geovalue){
			_showAt(event, geovalue);
		},
		showAtStatic: function(event, recid, value){
			_showAtStatic(event, recid, value);
		},
		hide: function(){
			hintDiv.hide();
		},
		getClass: function () {
			return _className;
		},

		isA: function (strClass) {
			return (strClass === _className);
		}

	}

	return that;
}

var mapViewer = new MapViewer();