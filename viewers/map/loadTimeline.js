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
* @author      Kim Jackson
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
* @deprecated
*/
// ARTEM: TO REMOVE


var timeline;
var eventSource = new Timeline.DefaultEventSource();

function loadTimeline() {
	var parseDate = eventSource._events.getUnit().getParser(null);
	var eventCount = 0;
	var bigDate = false;
	for (var bibID in HEURIST.tmap.records) {
		var record = HEURIST.tmap.records[bibID];
		if (! record.start) continue;

		if (Math.max(Math.abs(parseInt(record.start)), Math.abs(parseInt(record.end))) > 250000) {
			/* can't deal with dates outside about 250000BCE .. 250000CE */
			bigDate = true;
			continue;
		}

		// Simile is a real stickler -- doesn't understand YYYY-MM-DD, just YYYY/MM/DD ...
		// I mean, standards are great, but we've got DATA here instead
		record.start = ((record.start || "") + "").replace(/^(\d+)-(\d+)-(\d+)/, "$1/$2/$3");
		if (record.end) record.end = ((record.end || "") + "").replace(/^(\d+)-(\d+)-(\d+)/, "$1/$2/$3");

		var newEvent = new Timeline.DefaultEventSource.Event(
			/* start */  parseDate(record.start),
			/* end */  parseDate(record.end || record.start),
			/* latest start */  parseDate(null),
			/* earliest end */  parseDate(null),
			/* instantaneous? */ (record.start == record.end)  ||  record.end === undefined,
			/* title */ record.title,
			/* description */ record.description,
			/* URL image */ "",
			/* URL link */ "",
			/* URL icon */ top.HEURIST.iconDir + record.rectype + ".png",
			/* colour */ "#aa0000",
			/* text colour */ "#000000"
		);
		newEvent.heuristRecord = record;
		eventSource._events.add(newEvent);
		++eventCount;
	}

	if (eventCount == 0) {
		var tdTimeline = document.getElementById("timeline-cell");
		tdTimeline.style.display = "none";
		tdTimeline.parentNode.style.display = "none";
		var tdResizer = document.getElementById("resizer");
		tdResizer.style.display = "none";
		tdResizer.parentNode.style.display = "none";
		if (bigDate)
			document.getElementById("info").innerHTML = "<b>Time data lies outside 250000BCE .. 250000CE.</b> Timebar cannot display these dates.";
		else
			document.getElementById("info").innerHTML = "<b>No time data attached to any records</b>";
		return;
	}
	else if (bigDate) {
		document.getElementById("info").innerHTML += "<br><b>Some time data lies outside 250000BCE .. 250000CE.</b> Timebar cannot display these dates.";
	}


	var timelineDiv = document.getElementById("timeline");

	//For options that a theme can have look at themes.js
	var theme2 = new Timeline.ClassicTheme.create();
	//it is possible to set the background color of the ether
	theme2.ether.backgroundColors = [ "#D1CECA", "#e3c5a6", "#E8E8F4", "#D0D0E8"];


	var timelineView = calculateGoodTimelineView();
	var intervalWidth = Math.floor(timelineDiv.offsetWidth / (2 * timelineView.multiplier));

	var bandInfos = [
		Timeline.createBandInfo({
			eventSource:    eventSource,
			date:           timelineView.midDate,
			width:          "80%",
			intervalUnit:   timelineView.intervalUnit0,
			intervalPixels: intervalWidth,
			trackHeight:    1.6,
			showEventText:  true
		}),

		Timeline.createBandInfo({
			eventSource:    eventSource,
			date:           timelineView.midDate,
			width:          "20%",
			intervalUnit:   timelineView.intervalUnit1? timelineView.intervalUnit1 : undefined,
			intervalPixels: timelineView.intervalUnit1? intervalWidth : undefined,
			showEventText:  false,
			trackHeight:    0.5,
			theme:          theme2
		})
	];


	bandInfos[1].syncWith = 0;
	bandInfos[1].highlight = true;

	Timeline._Band.prototype._onKeyUp = Timeline._Band.prototype._onMouseUp = function(innerFrame, evt, target) {
		this._dragging = false;
		this._keyboardInput.focus();
		timeChange();
	}
	timeline = Timeline.create(timelineDiv, bandInfos);
}


function timeChange() {
	var startDate, endDate;

	if (document.getElementById("filter").checked) {
		var band = timeline.getBand(0);
//		startDate = band.getMinVisibleDate().getFullYear() + 1;
//		endDate = band.getMaxVisibleDate().getFullYear();
		startDate = band.getMinVisibleDate();
		endDate = band.getMaxVisibleDate();

		timeFilterRecords(startDate, endDate);
	}
	else	timeFilterRecords();
}


function timeFilterRecords(startDate, endDate) {
	var filteredRecordCount = 0;
	var parseDate = eventSource._events.getUnit().getParser(null);
	var rs, re;
	for (var bibID in HEURIST.tmap.records) {
		var record = HEURIST.tmap.records[bibID];
		if (! record.overlays) continue;

		rs = record.start ? parseDate(record.start) : null;
		re = record.end ? parseDate(record.end) : rs;

//		var hidden = arguments  &&  (record.start  &&  (record.start > endDate  ||  ((record.end || record.start) < startDate)));

		var hidden = arguments  &&  (rs  &&  (rs > endDate  ||  re < startDate));

		if (! hidden) ++filteredRecordCount;
		if (hidden == record.overlays[0].isHidden()) continue;

		for (var j=0; j < record.overlays.length; ++j) {
			if (hidden) record.overlays[j].hide();
			else record.overlays[j].show();
		}
	}

	document.getElementById("record-count").innerHTML = "" + filteredRecordCount;
}


function calculateGoodTimelineView() {
	var times = [];
	for (var bibID in HEURIST.tmap.records) {
		var record = HEURIST.tmap.records[bibID];
		if (! record.start) continue;

		if (Math.abs(parseInt(record.start)) > 250000) {
			continue;
		}

		var midDate = record.end? Math.round((parseInt(record.start)+parseInt(record.end))/2) : parseInt(record.start);
		times.push(midDate);
	}
	if (! times) return { midDate: 1000, intervalUnit0: DECADE, intervalUnit1: CENTURY };

	if (times.length == 1  &&  record.end != record.start) {
		times = [ parseInt(record.start), parseInt(record.end) ];
	}

	/* consider the dates without the top and bottom deciles ... */
	times.sort();

	if (times[0] == times[times.length-1]) {
		if ((times[0]+"").match(/^-?\d+$/)) {
			// single year
			var intervalUnit0 = Timeline.DateTime.YEAR;
			var intervalUnit1 = Timeline.DateTime.DECADE;
		}
		else {
			var intervalUnit0 = Timeline.DateTime.WEEK;
			var intervalUnit1 = Timeline.DateTime.MONTH;
		}
		var multiplier = 10;
		return { midDate: times[0], intervalUnit0: intervalUnit0, intervalUnit1: intervalUnit1, multiplier: multiplier };
	}

	var decileLength = Math.floor(times.length / 10);
	var minDate = times[decileLength];
	var maxDate = times[times.length - decileLength - 1];
	if (times.length > 2)
		var midDate = times[Math.round(times.length / 2)];	// centre on the median date
	else
		var midDate = Math.round((parseInt(minDate) + parseInt(maxDate)) / 2);

	/* expand the date range by 20% on either side */
	var range = maxDate - minDate;
	minDate = Math.round(minDate - range/10);
	maxDate = Math.round(maxDate + range/10);

	/* see what sort of date ranges we are looking at if we split the view into 20 intervals */
	var interval = (maxDate - minDate) / 20;
	// Find a close approximation that is a round number
	var intervals = [ 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000 ];	// Simile conks out well before the 10-millenium mark
	if (interval > intervals[intervals.length-1]) {
		var intervalLength = intervals[intervals.length-1];
	}
	else {
		for (var i=0; i < intervals.length; ++i) {
			if (interval < intervals[i]) {
				var intervalLength = intervals[i];
				break;
			}
		}
	}

	if (intervalLength < 5) {
		var intervalUnit0 = Timeline.DateTime.MONTH;
		var intervalUnit1 = Timeline.DateTime.YEAR;
		var multiplier = intervalLength;
	}
	if (intervalLength < 50) {
		var intervalUnit0 = Timeline.DateTime.YEAR;
		var intervalUnit1 = Timeline.DateTime.DECADE;
		var multiplier = intervalLength;
	}
	else if (intervalLength < 500) {
		var intervalUnit0 = Timeline.DateTime.DECADE;
		var intervalUnit1 = Timeline.DateTime.CENTURY;
		var multiplier = intervalLength / 10;
	}
	else if (intervalLength < 5000) {
		var intervalUnit0 = Timeline.DateTime.CENTURY;
		var intervalUnit1 = Timeline.DateTime.MILLENNIUM;
		var multiplier = intervalLength / 100;
	}
	else if (intervalLength < 50000) {
		var intervalUnit0 = Timeline.DateTime.MILLENNIUM;
		var intervalUnit1 = Timeline.DateTime.TENTHOUSAND;
		var multiplier = Math.min(4, intervalLength / 1000);
// 70000 years is a long long time ... 70000 years on my mind
// Looks like simile doesn't like to display more than that, however, so we're kind of screwed with this hard-coded multiplier
	}
	else if (intervalLength < 500000) {
		var intervalUnit0 = Timeline.DateTime.TENTHOUSAND;
		var intervalUnit1 = Timeline.DateTime.HUNDREDTHOUSAND;
		var multiplier = Math.min(4, intervalLength / 10000);
	}
	else if (intervalLength < 5000000) {
		var intervalUnit0 = Timeline.DateTime.HUNDREDTHOUSAND;
		var intervalUnit1 = Timeline.DateTime.MILLION;
		var multiplier = Math.min(4, intervalLength / 100000);
	}
	else {
		var intervalUnit0 = Timeline.DateTime.MILLION;
		var intervalUnit1 = 0;
		var multiplier = 4;
	}

	return { midDate: midDate, intervalUnit0: intervalUnit0, intervalUnit1: intervalUnit1, multiplier: multiplier };
}

