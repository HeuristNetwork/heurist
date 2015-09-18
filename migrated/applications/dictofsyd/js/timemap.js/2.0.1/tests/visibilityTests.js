
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
        'testVisible',
        'testItemHideShow',
        'testItemHideShowEvent',
        'testItemHideShowPlacemark',
        'testDatasetHideShow',
        'testTimeMapDatasetHideShow',
        'testHidePast',
        'testHideFuture'
    ];
}

function testVisible() {
    var item = tm.datasets["test"].getItems()[0];
    var placemark = item.placemark;
    var eventSource = tm.timeline.getBand(0).getEventSource();
    assertTrue("Item thinks it's visible", item.visible);
    assertNotUndefined("Placemark exists", placemark);
    assertNotUndefined("Event exists", item.event);
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
    assertTrue("Item thinks its event is visible", item.eventVisible);
}

function testItemHideShow() {
    var item = tm.datasets["test"].getItems()[0];
    var placemark = item.placemark;
    var eventSource = tm.timeline.getBand(0).getEventSource();
    assertTrue("Item thinks it's visible", item.visible);
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    item.hide();
    assertFalse("Item thinks it's hidden", item.visible);
    assertEquals("Zero items in eventSource", 0, eventSource.getCount());
    assertFalse("Item thinks its event is hidden", item.eventVisible);
    assertTrue("Placemark thinks it's hidden", placemark.isHidden());
    assertFalse("Item thinks its placemark is hidden", item.placemarkVisible);
    item.show();
    assertTrue("Item thinks it's visible", item.visible);
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
}

function testItemHideShowPlacemark() {
    var item = tm.datasets["test"].getItems()[0];
    var placemark = item.placemark;
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
    item.hidePlacemark();
    assertTrue("Placemark thinks it's hidden", placemark.isHidden());
    assertFalse("Item thinks its placemark is hidden", item.placemarkVisible);
    item.showPlacemark();
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
}

function testItemHideShowEvent() {
    var item = tm.datasets["test"].getItems()[0];
    var eventSource = tm.timeline.getBand(0).getEventSource();
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    item.hideEvent();
    // no great way to test item visibility
    assertEquals("Zero items in eventSource", 0, eventSource.getCount());
    assertFalse("Item thinks its event is hidden", item.eventVisible);
    item.showEvent();
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
}

function testDatasetHideShow() {
    var ds = tm.datasets["test"];
    var item = tm.datasets["test"].getItems()[0];
    var placemark = item.placemark;
    var eventSource = tm.timeline.getBand(0).getEventSource();
    assertTrue("Dataset thinks it's visible", ds.visible);
    ds.hide();
    assertFalse("Dataset thinks it's hidden", ds.visible);
    assertTrue("Placemark thinks it's hidden", placemark.isHidden());
    assertEquals("Zero items in eventSource", 0, eventSource.getCount());
    assertFalse("Item thinks its event is hidden", item.eventVisible);
    assertFalse("Item thinks its placemark is hidden", item.placemarkVisible);
    ds.show();
    assertTrue("Dataset thinks it's visible", ds.visible);
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
}

function testTimeMapDatasetHideShow() {
    var ds = tm.datasets["test"];
    var item = tm.datasets["test"].getItems()[0];
    var placemark = item.placemark;
    var eventSource = tm.timeline.getBand(0).getEventSource();
    tm.hideDatasets();
    assertFalse("Dataset thinks it's hidden", ds.visible);
    assertTrue("Placemark thinks it's hidden", placemark.isHidden());
    assertEquals("Zero items in eventSource", 0, eventSource.getCount());
    assertFalse("Item thinks its event is hidden", item.eventVisible);
    assertFalse("Item thinks its placemark is hidden", item.placemarkVisible);
    tm.showDatasets();
    assertTrue("Dataset thinks it's visible", ds.visible);
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
    tm.hideDataset("test");
    assertFalse("Dataset thinks it's hidden", ds.visible);
    assertTrue("Placemark thinks it's hidden", placemark.isHidden());
    assertEquals("Zero items in eventSource", 0, eventSource.getCount());
    assertFalse("Item thinks its event is hidden", item.eventVisible);
    assertFalse("Item thinks its placemark is hidden", item.placemarkVisible);
    tm.showDatasets();
    tm.hideDataset("notarealid");
    assertTrue("Dataset thinks it's visible", ds.visible);
    assertFalse("Placemark thinks it's visible", placemark.isHidden());
    assertEquals("One item in eventSource", 1, eventSource.getCount());
    assertTrue("Item thinks its event is visible", item.eventVisible);
    assertTrue("Item thinks its placemark is visible", item.placemarkVisible);
}

function testHidePast() {
    var parser = Timeline.DateTime.parseIso8601DateTime;
    var items = tm.datasets["test"].getItems();
    var placemark = items[0].placemark;
    assertFalse(placemark.isHidden());
    var date = parser("1970-01-01");
    tm.timeline.getBand(0).setCenterVisibleDate(date);
    assertTrue(placemark.isHidden());
    var date = parser("1980-01-01");
    tm.timeline.getBand(0).setCenterVisibleDate(date);
    assertFalse(placemark.isHidden());
}

function testHideFuture() {
    var parser = Timeline.DateTime.parseIso8601DateTime;
    var items = tm.datasets["test"].getItems();
    var placemark = items[0].placemark;
    assertFalse(placemark.isHidden());
    var date = parser("2000-01-01");
    tm.timeline.getBand(0).setCenterVisibleDate(date);
    assertTrue(placemark.isHidden());
    var date = parser("1980-01-01");
    tm.timeline.getBand(0).setCenterVisibleDate(date);
    assertFalse(placemark.isHidden());
}

// page setup script
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
                          "title" : "Test Event",
                          "options" : {
                            "description": "Test Description"
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
    var eventSource = tm.timeline.getBand(0).getEventSource();
    tm.timeline.getBand(0).setCenterVisibleDate(eventSource.getEarliestDate());
    tm.showDatasets();
}
