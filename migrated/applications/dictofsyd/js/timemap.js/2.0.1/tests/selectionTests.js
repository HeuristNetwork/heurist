
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
        'testDirectSelection',
        'testWindowSelection'
    ];
}

function assertSelected(item) {
    var ds = tm.datasets["test"],
        items = ds.getItems();
    assertEquals('Item ' + item.getTitle() + ' has not been selected', item, tm.getSelected());
    assertTrue('Item ' + item.getTitle() + ' thinks it is not selected', item.isSelected());
    for (var x=0; x<items.length; x++) {
        if (items[x] != item) {
            info('Current selection: ' + item.getTitle());
            assertNotSelected(items[x])
        }
    }
}

function assertNotSelected(item) {
    assertNotEquals('Item ' + item.getTitle() + ' is incorrectly set as selected', item, tm.getSelected());
    assertFalse('Item ' + item.getTitle() + ' thinks it is selected', item.isSelected());
}

function assertNoSelection(msg) {
    assertFalse("Selection should be clear, but isn't (" + msg + ")", !!tm.getSelected());
}

function testDirectSelection() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        x=0, item=items[0];
    assertNoSelection('initial');
    for (; x<items.length; item=items[x++]) {
        tm.setSelected(item);
        assertSelected(item);
        tm.setSelected(undefined);
        assertNoSelection(item.getTitle());
    }
}

function testWindowSelection() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        x=0, item=items[0];
    assertNoSelection('initial');
    for (; x<items.length; item=items[x++]) {
        item.openInfoWindow();
        assertSelected(item);
        item.closeInfoWindow();
        assertNoSelection(item.getTitle());
    }
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
                              "lat" : 40.0,
                              "lon" : 12.0
                           },
                          "title" : "Test Event 1"
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 41.0,
                              "lon" : 13.0
                           },
                          "title" : "Test Event 2"
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 42.0,
                              "lon" : 14.0
                           },
                          "title" : "Test Event 3",
                          "options" : {
                            "description": "Test Description",
                            "openInfoWindow": function() {
                                $('body').append('<div id="custom3">' + this.getInfoHtml() + '<div>');
                            },
                            "closeInfoWindow": function() {
                                $('#custom3').remove();
                            }
                          }
                        }
                    ]
                }
            }
        ]
    });
    setUpPageStatus = "complete";
}

function setUp() {
    tm.setSelected(undefined);
}
