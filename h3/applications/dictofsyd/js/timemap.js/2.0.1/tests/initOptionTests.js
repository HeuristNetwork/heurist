function exposeTestFunctionNames() {
    return [
        'testDefaultMapType',
        'testCustomMapType',
        'testAutoCenterAndZoom',
        'testCustomCenterAndZoom',
        'testPointCenterAndZoom',
        'testDefaultTimelineBands',
        'testCustomBandIntervals',
        'testCustomBandInfo',
        'testCustomBands'
    ];
}

// custom assertions
function assertBandInterval(timemap, bandIndex, interval) {
    assertEquals("Timeline band " + bandIndex + " not " + interval, 
        timemap.timeline.getBand(bandIndex).getEther()._interval,
        Timeline.DateTime.gregorianUnitLengths[Timeline.DateTime[interval]]);
}

function assertMapType(timemap, type) {
    if (!tm.map.api == 'openlayers') {
        assertEquals("mapType not " + type, 
            mxn.Mapstraction[type], 
            timemap.map.getMapType());
    } // else pass - OpenLayers doesn't support other map types in Mapstraction
}

// tests: map types

function testDefaultMapType() {
    switch (tm.map.api) {
        case "google":
        case "googlev3":
            assertMapType(tm, 'PHYSICAL');
            break;
        case "openlayers":
        case "microsoft":
            assertMapType(tm, 'ROAD');
            break;
        default:
            fail("Map API not defined, or tests not defined for this API");
    }
}

function testCustomMapType() {
    // this will fail for openlayers right now
    assertMapType(tm2, 'SATELLITE');
}

// tests: center and zoom

function testAutoCenterAndZoom() {
    assertTrue("centerOnItems not set as default", tm.opts.centerOnItems);
    // okay, let's do this with broad strokes
    var mapZoom = tm.map.getZoom();
    assertTrue("Map auto-zoom too high - expected ~7, got " + mapZoom, mapZoom <= 8);
    assertTrue("Map auto-zoom too low - expected ~7, got " + mapZoom, mapZoom >= 6);
    var center = tm.map.getCenter();
    assertTrue("Map auto-center latitude too far south - expected ~41, got " + center.lat, center.lat > 40.0);
    assertTrue("Map auto-center latitude too far north - expected ~41, got " + center.lat, center.lat < 42.0);
    assertTrue("Map auto-center longitude too far east - expected ~12, got " + center.lon, center.lon < 12.5);
    assertTrue("Map auto-center longitude too far west - expected ~12, got " + center.lon, center.lon > 11.5);
}

function testCustomCenterAndZoom() {
    assertFalse("centerOnItems incorrectly set as default", tm2.opts.centerOnItems);
    var center = tm2.map.getCenter();
    assertTrue("Map center latitude too far south - expected ~38, got " + center.lat, center.lat > 37.9);
    assertTrue("Map center latitude too far north - expected ~38, got " + center.lat, center.lat < 38.1);
    assertTrue("Map center longitude too far east - expected ~-123, got " + center.lon, center.lon < -122.9);
    assertTrue("Map center longitude too far west - expected ~-123, got " + center.lon, center.lon > -123.1);
    assertEquals("Map zoom wrong", 8, tm2.map.getZoom());
}

function testPointCenterAndZoom() {
    assertFalse("centerOnItems incorrectly set as default", tm3.opts.centerOnItems);
    var center = tm3.map.getCenter();
    assertTrue("Map center latitude too far south - expected ~38, got " + center.lat, center.lat > 37.9);
    assertTrue("Map center latitude too far north - expected ~38, got " + center.lat, center.lat < 38.1);
    assertTrue("Map center longitude too far east - expected ~-123, got " + center.lon, center.lon < -122.9);
    assertTrue("Map center longitude too far west - expected ~-123, got " + center.lon, center.lon > -123.1);
    assertEquals("Map zoom wrong", 8, tm3.map.getZoom());
}

// tests: timeline bands

function testDefaultTimelineBands() {
    assertEquals("Timeline doesn't have two bands", 2, tm.timeline.getBandCount());
    assertEquals("Timeline bands are not properly synched", 
        tm.timeline.getBand(0), tm.timeline.getBand(1)._syncWithBand
    );
    assertBandInterval(tm, 0, 'WEEK');
    assertBandInterval(tm, 1, 'MONTH');
}

function testCustomBandIntervals() {
    assertEquals("Timeline doesn't have two bands", 2, tm2.timeline.getBandCount());
    assertNull("Timeline bands are synched but shouldn't be", tm2.timeline.getBand(1)._syncWithBand);
    assertBandInterval(tm2, 0, 'DECADE');
    assertBandInterval(tm2, 1, 'CENTURY');
}

function testCustomBandInfo() {
    assertEquals("Timeline doesn't have three bands", 3, tm3.timeline.getBandCount());
    assertEquals("Timeline band 1 is not properly synched", 
        tm3.timeline.getBand(0), tm3.timeline.getBand(1)._syncWithBand
    );
    assertEquals("Timeline band 2 is not properly synched", 
        tm3.timeline.getBand(0), tm3.timeline.getBand(2)._syncWithBand
    );
    assertBandInterval(tm3, 0, 'YEAR');
    assertBandInterval(tm3, 1, 'DECADE');
    assertBandInterval(tm3, 2, 'CENTURY');
    assertNull("Final band eventSource populated though set to false", 
        tm3.timeline.getBand(2).getEventSource()
    );
}

function testCustomBands() {
    assertEquals("Timeline doesn't have two bands", 2, tm4.timeline.getBandCount());
    assertEquals("Timeline bands are not properly synched", 
        tm.timeline.getBand(0), tm.timeline.getBand(1)._syncWithBand
    );
    assertEquals("Custom hotzone band 0 not used", 
        Timeline.HotZoneEther, tm4.timeline.getBand(0).getEther().constructor
    );
    assertEquals("Custom hotzone band 1 not used", 
        Timeline.HotZoneEther, tm4.timeline.getBand(1).getEther().constructor
    );
    assertEquals("Band event sources not set properly", 
        tm4.timeline.getBand(0).getEventSource, tm4.timeline.getBand(1).getEventSource
    );
}

var tm, tm2, tm3;
// page setup script
function setUpPage() {
    var items = [
        {
          "start" : "1452",
          "point" : {
              "lat" : 40.0,
              "lon" : 12.0
           },
          "title" : "Item 1"
        },
        {
          "start" : "1475",
          "point" : {
              "lat" : 42.0,
              "lon" : 12.0
           },
          "title" : "Item 2"
        }
    ];
    // tm
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                id: "test",
                type: "basic",
                options: {
                    items: items
                }
            }
        ]
    });
    // tm 2
    tm2 = TimeMap.init({
        mapId: "map2",               // Id of map div element (required)
        timelineId: "timeline2",     // Id of timeline div element (required) 
        options: {
            centerOnItems: false,
            mapZoom: 8,
            mapCenter: new mxn.LatLonPoint(38, -123),
            mapType: 'satellite',
            syncBands: false
        },
        bandIntervals: "dec",
        datasets: [
            {
                id: "test",
                type: "basic",
                options: {
                    items: items
                }
            }
        ]
    });
    // tm 3
    tm3 = TimeMap.init({
        mapId: "map3",               // Id of map div element (required)
        timelineId: "timeline3",     // Id of timeline div element (required) 
        options: {
            centerOnItems: false,
            mapZoom: 8,
            mapCenter: {
                lat:38.0, 
                lon:-123
            }
        },
        bandInfo: [
            {
                width:          "30%", 
                intervalUnit:   Timeline.DateTime.YEAR,
                intervalPixels: 100
            },
            {
                width:          "30%", 
                intervalUnit:   Timeline.DateTime.DECADE,
                intervalPixels: 100
            },
            {
                width:          "20%", 
                intervalUnit:   Timeline.DateTime.CENTURY,
                intervalPixels: 100,
                eventSource:    false
            }
        ],
        datasets: [
            {
                id: "test",
                type: "basic",
                options: {
                    items: items
                }
            }
        ]
    });
    // tm4
    tm4 = TimeMap.init({
        mapId: "map4",               // Id of map div element (required)
        timelineId: "timeline4",     // Id of timeline div element (required) 
        bands: [
            Timeline.createHotZoneBandInfo({
                width:          "75%",
                intervalUnit:   Timeline.DateTime.YEAR,
                intervalPixels: 65,
                zones:          [{   
                                    start:    "1450",
                                    end:      "1480",
                                    magnify:  5,
                                    unit:     Timeline.DateTime.MONTH
                                }]
            }),
            Timeline.createHotZoneBandInfo({
                width:          "25%",
                intervalUnit:   Timeline.DateTime.DECADE,
                intervalPixels: 85,     
                layout:         'overview',
                zones:          [{   
                                    start:    "1450",
                                    end:      "1480",
                                    magnify:  5,
                                    unit:     Timeline.DateTime.YEAR
                                }]
            })
        ],
        datasets: [
            {
                id: "test",
                type: "basic",
                options: {
                    items: items
                }
            }
        ]
    });
    setUpPageStatus = "complete";
}
