
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

function exposeTestFunctionNames() {
    return [
        'testItemsLoaded',
        'testItemsLoadedInEventSource'
    ];
}

function testItemsLoaded() {
    var ds = tm.datasets["test"];
    assertEquals("All items loaded in item array", 6, ds.getItems().length);
}

function testItemsLoadedInEventSource() {
    var ds = tm.datasets["test"];
    assertEquals("All items with events loaded in eventSource", 5, ds.eventSource.getCount());
}

var tm = null;

function setUpPage() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                type: "basic",
                options: {
                    items: [
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Event 1",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // missing event
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.3453
                           },
                          "title" : "Test Event 2",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // missing placemark
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "title" : "Test Event 3",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // missing point data
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "title" : "Test Event 4",
                          "point" : {}
                        },
                        { // missing polygon data
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "title" : "Test Event 5",
                          "polygon" : []
                        },
                        { // empty placemark array
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "title" : "Test Event 6",
                          "placemarks" : []
                        }
                    ]
                }
            }
        ]
    });
    setUpPageStatus = "complete";
}

function setUp() {
    var eventSource = tm.timeline.getBand(0).getEventSource();
    tm.timeline.getBand(0).setCenterVisibleDate(eventSource.getEarliestDate());
    tm.showDatasets();
}
