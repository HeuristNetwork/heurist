
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


var tm = null, items = null;

function exposeTestFunctionNames() {
    return [
        'testScrollToEarliest',
        'testScrollToLatest',
        'testScrollToStringDate',
        'testScrollToDateObject',
        'testScrollToItem'
    ];
}

function testScrollToEarliest() {
    loadWithScrollTo('earliest', 1980);
    loadWithScrollTo('first', 1980);
}

function testScrollToLatest() {
    loadWithScrollTo('latest', 2000);
    loadWithScrollTo('last', 2000);
}

function testScrollToStringDate() {
    // have to be somewhat loose here because of pixel-to-date conversion
    loadWithScrollTo('1990-01-03', 1990);
}

function testScrollToDateObject() {
    loadWithScrollTo(new Date(1990, 1, 1), 1990);
}

function testScrollToItem() {
    var x, d, item, years=[1980,2000];
    for (x=0; x<1; x++) {
        item = tm.getItems()[x];
        item.scrollToStart();
        d = tm.timeline.getBand(0).getCenterVisibleDate();
        assertEquals('Failed scrolling to item ' + item.getTitle(), years[x], d.getUTCFullYear());
    }
}

var items = [
    {
        "start" : "1980-01-02",
        "title" : "Test Event 1980",
        "point" : {
            "lat" : 23.456,
            "lon" : 12.345
        }
    },
    {
        "start" : "2000-01-02",
        "title" : "Test Event 2000",
        "point" : {
            "lat" : 23.456,
            "lon" : 12.345
        }
    }
];

function loadWithScrollTo(scrollTo, year) {
    // fix for a bug in early simile version
    if (TimeMap.util.TimelineVersion() == "1.2") {
        tm.timeline.getBand(0)._eventPainter._layout._laidout = false;
    }
    // initialize load manager
    var loadManager = TimeMap.loadManager;
    loadManager.init(tm, 1, {
        scrollTo: scrollTo, 
        dataDisplayedFunction: function() {
            var d = tm.timeline.getBand(0).getCenterVisibleDate();
            assertEquals('Testing "' + scrollTo + '"', year, d.getUTCFullYear());
        }
    });
    // load items
    var callback = function() { loadManager.complete(); };
    loader = new TimeMap.loaders.basic({items: items});
    loader.load(tm.datasets["test"], callback);
}

function setUpPage() {
    
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required)
        scrollTo: "earliest",
        datasets: [ 
            {
                title: "Test Dataset",
                id: "test",
                type: "basic",
                options: {
                    items: []
                }
            }
        ]
    });
    setUpPageStatus = "complete";
}
