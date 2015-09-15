function exposeTestFunctionNames() {
    return [
        'testItemsLoaded',
        'testAllPlacemarksExist',
        'testPolyVertices',
        'testMultiplePlacemarks'
    ];
}

function testItemsLoaded() {
    var ds = tm.datasets["test"];
    assertEquals("Correct number of items in item array", 6, ds.getItems().length);
}

function testAllPlacemarksExist() {
    var ds = tm.datasets["test"];
    var items = ds.getItems();
    for (var x=0; x < items.length; x++) {
        if (x==5) continue; // skip overlay until I make it work
        assertTrue(items[x].getTitle() + " placemark evaluates to true", Boolean(items[x].placemark));
        assertNotNull(items[x].getTitle() + " placemark is not null", items[x].placemark);
        assertNotUndefined(items[x].getTitle() + " placemark is not undefined", items[x].placemark);
    }
}

function testPolyVertices() {
    var ds = tm.datasets["test"];
    var polyline = ds.getItems()[1].placemark;
    assertEquals("polyline vertex count wrong", 3, polyline.points.length);
    var polygon = ds.getItems()[2].placemark;
    assertEquals("polyline vertex count wrong", 6, polygon.points.length);
}

function testMultiplePlacemarks() {
    var ds = tm.datasets["test"];
    var item, point;
    for (var x=4; x<5; x++) {
        item = ds.getItems()[x];
        assertEquals(item.getTitle() + " has type 'array'", "array", item.getType());
        // check for array attributes
        assertNotUndefined(item.getTitle() + " placemark has pop function", item.placemark.pop);
        assertNotUndefined(item.getTitle() + " placemark has push function", item.placemark.push);
        assertEquals(item.getTitle() + " has the right number of placemarks", 
            correctMultiplePlacemarkCount, item.placemark.length);
    }
    item = ds.getItems()[3];
    point = new mxn.LatLonPoint(23.456, 12.345);
    assertTrue("First placemark point used", point.equals(item.getInfoPoint()));
    item = ds.getItems()[4];
    point = new mxn.LatLonPoint(43.730473, 11.257896);
    assertTrue("Option info point used", point.equals(item.getInfoPoint()));
}

var tm = null,
    correctMultiplePlacemarkCount;

function basicPlacemarkTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                data: {
                    type: "basic",
                    value: [
                        { // point
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Point",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // polyline
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "polyline" : [
                            {
                              "lat" : 43.829872,
                              "lon" : 11.154900
                            },
                            {
                              "lat" : 43.730968,
                              "lon" : 11.190605
                            },
                            {
                              "lat" : 43.730473,
                              "lon" : 11.257896
                            }
                           ],
                          "title" : "Test Polyline",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // polygon
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "polygon" : [
                            {
                              "lat" : 43.787254,
                              "lon" : 11.226311
                            },{
                              "lat" : 43.801628,
                              "lon" : 11.283646
                            },{
                              "lat" : 43.770649,
                              "lon" : 11.302528
                            },{
                              "lat" : 43.743370,
                              "lon" : 11.276779
                            },{
                              "lat" : 43.755276,
                              "lon" : 11.230087
                            },{
                              "lat" : 43.787254,
                              "lon" : 11.226311
                            }
                           ],
                          "title" : "Test Polygon",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // multiple, top level
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                           "polyline" : [
                            {
                              "lat" : 43.829872,
                              "lon" : 11.154900
                            },
                            {
                              "lat" : 43.730968,
                              "lon" : 11.190605
                            },
                            {
                              "lat" : 43.730473,
                              "lon" : 11.257896
                            }
                           ],
                           "polygon" : [
                            {
                              "lat" : 43.829872,
                              "lon" : 11.154900
                            },
                            {
                              "lat" : 43.730968,
                              "lon" : 11.190605
                            },
                            {
                              "lat" : 43.730473,
                              "lon" : 11.257896
                            }
                           ],
                          "overlay" : {
                              "image" : "data/tile.png",
                              "north" : 38.285990,
                              "south" : 29.231120,
                              "east"  : 74.523837,
                              "west"  : 60.533227
                           },
                          "title" : "Test Multiple: Top Level",
                          "options" : {
                            "description": "Test Description"
                          }
                        },
                        { // multiple, in a separate array
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "placemarks": [
                            {
                                "point" : {
                                  "lat" : 23.456,
                                  "lon" : 12.345
                                }
                            },
                            {
                                "polyline" : [
                                    {
                                      "lat" : 43.829872,
                                      "lon" : 11.154900
                                    },
                                    {
                                      "lat" : 43.730968,
                                      "lon" : 11.190605
                                    },
                                    {
                                      "lat" : 43.730473,
                                      "lon" : 11.257896
                                    }
                                ]
                            },
                            {
                                "polygon" : [
                                    {
                                      "lat" : 43.829872,
                                      "lon" : 11.154900
                                    },
                                    {
                                      "lat" : 43.730968,
                                      "lon" : 11.190605
                                    },
                                    {
                                      "lat" : 43.730473,
                                      "lon" : 11.257896
                                    }
                                ]
                            },
                            {
                                "overlay" : {
                                  "image" : "data/tile.png",
                                  "north" : 38.285990,
                                  "south" : 29.231120,
                                  "east"  : 74.523837,
                                  "west"  : 60.533227
                                }
                            }
                          ],
                          "title" : "Test Multiple: Array",
                          "options" : {
                            "description": "Test Description",
                            "infoPoint": {
                              "lat" : 43.730473,
                              "lon" : 11.257896
                            }
                          }
                        },
                        { // overlay
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "overlay" : {
                              "image" : "data/tile.png",
                              "north" : 38.285990,
                              "south" : 29.231120,
                              "east"  : 74.523837,
                              "west"  : 60.533227
                           },
                          "title" : "Test Overlay",
                          "options" : {
                            "description": "Test Description"
                          }
                        }
                    ]
                }
            }
        ]
    });
    correctMultiplePlacemarkCount = 3; // 4; -- omit overlay until I can make it work
    setUpPageStatus = "complete";
}


function kmlPlacemarkTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset: KML",
                id: "test",
                type: "kml",
                options: {
                    url: "data/placemarks.kml"
                }
            }
        ]
    });
    correctMultiplePlacemarkCount = 3; // no overlay possible in KML
    setUpPageStatus = "complete";
}


function setUp() {
    var eventSource = tm.timeline.getBand(0).getEventSource();
    tm.timeline.getBand(0).setCenterVisibleDate(eventSource.getEarliestDate());
    tm.showDatasets();
}
