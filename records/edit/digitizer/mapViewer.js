/**
* mapViewer.js
* Creates popup div with Gmap to view given coordinates
*
* requires
* common/js/hintDiv.js
* records/edit/digitizer/digitizer.js
* 		viewer/map/mapping.js
* 		jquery
*
* 11/04/2012
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

function MapViewer() {

	//private members
	var _className = "MapViewer";

	var hintDiv = new HintDiv('mapPopup', 300, 300, '<div id="map_viewer" style="width:100%;height:100%"></div>');


	function _showAt(event, geovalue)
	{
			hintDiv.showAt(event);

			initmap_viewer('map_viewer', geovalue); //from digitizer.js
	}

	//public members
	var that = {

		showAt: function(event, geovalue){
			_showAt(event, geovalue);
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
