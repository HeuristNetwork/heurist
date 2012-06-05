/**
* calendarViewer.js
* Creates popup div with calendar
*
* requires
* common/js/hintDiv.js (popup div)
* common/js/calendar.js
* common/css/calendar.css
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

function CalendarViewer() {

	//private members
	var _className = "CalendarViewer";

    							//id                               content
	var hintDiv = new HintDiv('calendarPopup', 260, 170, '<div id="calendar-div"></div>');

	function _showAt(xy, datevalue, callback)
	{
			hintDiv.showAtXY(xy);

			init_calendar(datevalue, callback); //from calendar.js
	}

	//public members
	var that = {

		showAt: function(xy, datevalue, callback){
			_showAt(xy, datevalue, callback);
		},

		close: function(){
			hintDiv.close();
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

var calendarViewer = new CalendarViewer();
