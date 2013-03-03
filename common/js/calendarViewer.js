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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
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
		},

/*		parent_tab:function(sname){
			_parenttab = sname;
		}*/

	}

	return that;
}

var calendarViewer = new CalendarViewer();
