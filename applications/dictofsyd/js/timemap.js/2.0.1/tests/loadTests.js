function exposeTestFunctionNames() {
    return [
        'testDatasetIsDefined',
        'testItemLoaded',
        'testItemLoadedInEventSource',
        'testEarliestDate',
        'testLatestDate',
        'testItemAttributes',
        'testItemEvent',
        'testMarkerPlacemark',
        'testNativeMarkerPlacemark',
        'testPolylinePlacemark',
        'testNativePolylinePlacemark'
    ];
}

function testDatasetIsDefined() {
    assertNotUndefined("dataset is defined", tm.datasets["test"]);
}

function testItemLoaded() {
    var ds = tm.datasets["test"];
    assertEquals("two items in item array", 2, ds.getItems().length);
}

function testItemLoadedInEventSource() {
    var ds = tm.datasets["test"];
    assertEquals("two items in eventSource", 2, ds.eventSource.getCount());
}

function testEarliestDate() {
    var ds = tm.datasets["test"];
    assertDateMatches1980("Earliest date not correct", ds.eventSource.getEarliestDate());
}

function testLatestDate() {
    var ds = tm.datasets["test"];
    assertDateMatches2000("Latest date not correct", ds.eventSource.getLatestDate());
}

function testItemAttributes() {
    var items = tm.datasets["test"].getItems();
    // point
    var item = items[0];
    assertEquals("title matches", item.getTitle(), "Test Event");
    assertEquals("description matches", item.opts.description, "Test Description");
    // polyline
    item = items[1];
    assertEquals("title matches", item.getTitle(), "Test Event 2");
}

function testItemEvent() {
    var items = tm.datasets["test"].getItems();
    // point
    var item = items[0],
        title = item.getTitle();
    assertNotNull(title + ": event not null", item.event);
    assertNotUndefined(title + ": event undefined", item.event);
    assertEquals(title + ": event title matches", item.event.getText(), "Test Event");
    assertDateMatches1980(title + ": item event start wrong", item.getStart());
    assertDateMatches2000(title + ": item event end wrong", item.getEnd());
    // polyline
    item = items[1];
    title = item.getTitle();
    assertNotNull(title + ": event not null", item.event);
    assertNotUndefined(title + ": event undefined", item.event);
    assertEquals(title + ": event title matches", item.event.getText(), "Test Event 2");
    assertDateMatches1980(title + ": item event start wrong", item.getStart());
}

function testMarkerPlacemark() {
    var items = tm.datasets["test"].getItems(),
        item = items[0];
    assertNotNull("placemark not null", item.placemark);
    assertNotUndefined("placemark undefined", item.placemark);
    assertEquals("item type matches", "marker", item.getType());
    assertEquals("placemark type matches", "marker", TimeMap.util.getPlacemarkType(item.placemark));
    assertEquals("marker title wrong", "Test Event", item.placemark.labelText);
    var point = new mxn.LatLonPoint(23.456, 12.345);
    assertTrue("marker point matches", item.placemark.location.equals(point));
    assertTrue("info point matches", item.getInfoPoint().equals(point));
}

function testNativeMarkerPlacemark() {
    var items = tm.datasets["test"].getItems(),
        item = items[0],
        nativePlacemark = item.getNativePlacemark();
    assertNotNull("native placemark null", nativePlacemark);
    assertNotUndefined("native placemark undefined", nativePlacemark);
    switch (tm.map.api) {
        case "google":
            assertTrue("native marker not GMarker", nativePlacemark.constructor == GMarker);
            assertEquals("native marker title wrong", "Test Event", nativePlacemark.getTitle());
            var point = new GLatLng(23.456, 12.345);
            assertTrue("marker point matches", nativePlacemark.getLatLng().equals(point));
            break;
        case "googlev3":
            // ducktype
            assertTrue("native marker wrong", 'getMap' in nativePlacemark && 
                'setMap' in nativePlacemark &&
                'getIcon' in nativePlacemark);
            assertEquals("native marker title wrong", "Test Event", nativePlacemark.getTitle());
            var point = new google.maps.LatLng(23.456, 12.345);
            assertTrue("marker point matches", nativePlacemark.getPosition().equals(point));
            break;
        case "openlayers":
            assertTrue("native marker wrong", nativePlacemark.CLASS_NAME == "OpenLayers.Marker");
            // OpenLayers markers have no titles
            // XXX: not thrilled with this test - I think Mapstraction is screwy here
            var point = new mxn.LatLonPoint(23.456, 12.345).toProprietary("openlayers");
            assertTrue("marker point matches", nativePlacemark.lonlat.equals(point));
            break;
        case "microsoft":
            assertTrue("native marker wrong", nativePlacemark.constructor == VEShape &&
                nativePlacemark.GetShapeType() == "Point");
            assertEquals("native marker title wrong", "Test Event", nativePlacemark.GetTitle());
            assertEquals("native marker latitude wrong", 23.456, nativePlacemark.Latitude);
            assertEquals("native marker longitude wrong", 12.345, nativePlacemark.Longitude);
            break;
        default:
            fail("Map API not defined, or tests not defined for this API");
    }
}

function testPolylinePlacemark() {
    var items = tm.datasets["test"].getItems(),
        item = items[1];
    assertNotNull("placemark not null", item.placemark);
    assertNotUndefined("placemark undefined", item.placemark);
    assertEquals("item type matches", "polyline", item.getType());
    assertEquals("placemark type matches", "polyline", TimeMap.util.getPlacemarkType(item.placemark));
    var points = [
        new mxn.LatLonPoint(45.256, -110.45), 
        new mxn.LatLonPoint(46.46, -109.48), 
        new mxn.LatLonPoint(43.84, -109.86)
    ];
    assertNotUndefined("polyline points undefined", item.placemark.points);
    assertEquals("vertex count wrong", 3, item.placemark.points.length);
    assertTrue("info point doesn't match middle point", item.getInfoPoint().equals(points[1]));
    for (var x=0; x<points.length; x++) {
        assertTrue("vertex " + x + " matches", item.placemark.points[x].equals(points[x]));
    }
}

function testNativePolylinePlacemark() {
    var items = tm.datasets["test"].getItems(),
        item = items[1],
        nativePlacemark = item.getNativePlacemark();
    assertNotNull("native placemark null", nativePlacemark);
    assertNotUndefined("native placemark undefined", nativePlacemark);
    switch (tm.map.api) {
        case "google":
            // ducktype
            assertTrue("native polyine wrong class", 'getVertex' in nativePlacemark && 
                'getVertexCount' in nativePlacemark);
            var points = [
                new GLatLng(45.256, -110.45), 
                new GLatLng(46.46, -109.48), 
                new GLatLng(43.84, -109.86)
            ];
            assertEquals("native vertex count wrong", 3, nativePlacemark.getVertexCount());
            for (var x=0; x<points.length; x++) {
                assertTrue("native vertex " + x + " wrong", 
                    nativePlacemark.getVertex(x).equals(points[x]));
            }
            break;
        case "googlev3":
            // ducktype
            assertTrue("native polyline wrong class", 'getMap' in nativePlacemark && 
                'setMap' in nativePlacemark &&
                'getPath' in nativePlacemark);
            var points = [
                new google.maps.LatLng(45.256, -110.45), 
                new google.maps.LatLng(46.46, -109.48), 
                new google.maps.LatLng(43.84, -109.86)
            ];
            assertEquals("native vertex count wrong", 3, nativePlacemark.getPath().getLength());
            for (var x=0; x<points.length; x++) {
                assertTrue("native vertex " + x + " wrong", 
                    nativePlacemark.getPath().getAt(x).equals(points[x]));
            }
            break;
        case "openlayers":
            assertTrue("native polyline wrong class", nativePlacemark.CLASS_NAME == "OpenLayers.Feature.Vector");
            // XXX: Not thrilled with this test - it's testing Mapstraction, not whether the lat/lons are correct
            var lls = [
                    new mxn.LatLonPoint(45.256, -110.45).toProprietary("openlayers"), 
                    new mxn.LatLonPoint(46.46, -109.48).toProprietary("openlayers"), 
                    new mxn.LatLonPoint(43.84, -109.86).toProprietary("openlayers")
                ],
                points = [], x;
            for (x=0; x<lls.length; x++) {
                points.push(new OpenLayers.Geometry.Point(lls[x].lon, lls[x].lat));
            }
            assertEquals("native vertex count wrong", 3, nativePlacemark.geometry.components.length);
            for (x=0; x<points.length; x++) {
                assertTrue("native vertex " + x + " wrong", 
                    nativePlacemark.geometry.components[x].equals(points[x]));
            }
            break;
        case "microsoft":
            assertTrue("native marker wrong", nativePlacemark.constructor == VEShape &&
                nativePlacemark.GetShapeType() == "Polyline");
            
            var points = [
                    [45.256, -110.45], 
                    [46.46, -109.48], 
                    [43.84, -109.86]
                ],
                nativePoints = nativePlacemark.GetPoints();
            assertEquals("native vertex count wrong", 3, nativePoints.length);
            for (var x=0; x<points.length; x++) {
                assertEquals("native vertex " + x + " latitude wrong", points[x][0], nativePoints[x].Latitude);
                assertEquals("native vertex " + x + " longitude wrong", points[x][1], nativePoints[x].Longitude);
            }
            break;
        default:
            fail("Map API not defined, or tests not defined for this API");
    }
}


function assertDateMatches1980(msg, d) {
    assertEquals(msg + ": year", 1980, d.getUTCFullYear());
    assertEquals(msg + ": month", 0, d.getUTCMonth());
    assertEquals(msg + ": day", 2, d.getUTCDate());
}

function assertDateMatches2000(msg, d) {
    assertEquals(msg + ": year", 2000, d.getUTCFullYear());
    assertEquals(msg + ": month", 0, d.getUTCMonth());
    assertEquals(msg + ": day", 2, d.getUTCDate());
}

var tm = null;

// page setup function - basic
function basicLoadTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                // this syntax should still work
                data: {
                    type: "basic",
                    value: [
                        {
                            "start" : "1980-01-02",
                            "end" : "2000-01-02",
                            "point" : {
                                "lat" : 23.456,
                                "lon" : 12.345
                            },
                            "title" : "Test Event",
                            "options" : {
                                "description": "Test Description"
                            }
                        },
                        {
                            "start" : "1980-01-02",
                            "polyline" : [
                                {
                                    "lat" : 45.256,
                                    "lon" : -110.45
                                },
                                {
                                    "lat" : 46.46,
                                    "lon" : -109.48
                                },
                                {
                                    "lat" : 43.84,
                                    "lon" : -109.86
                                }
                            ],
                            "title" : "Test Event 2"
                        }
                    ]
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}

// page setup function - kml
function kmlLoadTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                type: "kml",
                options: {
                    url: "data/data.kml" 
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}

// page setup function - jsonp
function jsonpLoadTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                type: "jsonp",
                options: {
                    url: "data/data.js?cb=" 
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}

// page setup function - json string
function jsonLoadTestSetup() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset",
                id: "test",
                type: "json_string",
                options: {
                    url: "data/data_string.js" 
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}
