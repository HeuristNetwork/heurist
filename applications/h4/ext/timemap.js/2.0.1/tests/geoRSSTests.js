function exposeTestFunctionNames() {
    return [
        'testDatasetsAreDefined',
        'testRSSItemLoaded',
        'testRSSEarliestDate',
        'testRSSItemAttributes',
        'testAtomItemLoaded',
        'testAtomEarliestDate',
        'testAtomItemAttributes',
        'testMixedItemsLoaded',
        'testMixedPlacemarksFound',
        'testMixedKMLTime', 
        'testMixedExtraTags'
    ];
}

function testDatasetsAreDefined() {
    assertNotUndefined("RSS dataset is defined", tm.datasets["rss"]);
    assertNotUndefined("Atom dataset is defined", tm.datasets["atom"]);
    assertNotUndefined("Mixed dataset is defined", tm.datasets["mixed"]);
}

function testRSSItemLoaded() {
    var ds = tm.datasets["rss"];
    assertEquals("one item in item array", ds.getItems().length, 1);
}

function testRSSEarliestDate() {
    var ds = tm.datasets["rss"];
    assertEquals("year matches", ds.eventSource.getEarliestDate().getUTCFullYear(), 1980);
    assertEquals("month matches", ds.eventSource.getEarliestDate().getUTCMonth(), 0);
    // Timeline seems to adjust for the timezone after parsing :(
    assertEquals("day matches", ds.eventSource.getEarliestDate().getUTCDate(), 2);
}

function testRSSItemAttributes() {
    var items = tm.datasets["rss"].getItems();
    var item = items[0];
    assertEquals("title matches", item.getTitle(), "Test Event");
    assertEquals("placemark type matches", item.getType(), "marker");
    var point = new mxn.LatLonPoint(23.456, 12.345);
    assertTrue("point matches", item.getInfoPoint().equals(point));
}

function testAtomItemLoaded() {
    var ds = tm.datasets["atom"];
    assertEquals("one item in item array", ds.getItems().length, 1);
}

function testAtomEarliestDate() {
    var ds = tm.datasets["atom"];
    assertEquals("year matches", ds.eventSource.getEarliestDate().getUTCFullYear(), 1980);
    assertEquals("month matches", ds.eventSource.getEarliestDate().getUTCMonth(), 0);
    // Timeline seems to adjust for the timezone after parsing :(
    assertEquals("day matches", ds.eventSource.getEarliestDate().getUTCDate(), 2);
}

function testAtomItemAttributes() {
    var items = tm.datasets["atom"].getItems();
    var item = items[0];
    assertEquals("title matches", item.getTitle(), "Test Event");
    assertEquals("placemark type matches", item.getType(), "marker");
    var point = new mxn.LatLonPoint(23.456, 12.345);
    assertTrue("point matches", item.getInfoPoint().equals(point));
}

function testMixedItemsLoaded() {
    var ds = tm.datasets["mixed"];
    assertEquals("Fourteen items in item array", 14, ds.getItems().length);
}

function testMixedPlacemarksFound() {
    var items = tm.datasets["mixed"].getItems();
    var pmTypes = ['GeoRSS-Simple','GML (pos)','GML (coordinates)','W3C Geo'];
    var offset;
    for (x=0; x<pmTypes.length; x++) {
        var item = items[x];
        assertEquals(pmTypes[x] + ": placemark type matches", item.getType(), "marker");
        var point = new mxn.LatLonPoint(23.456, 12.345);
        assertTrue(pmTypes[x] + ": point matches", item.getInfoPoint().equals(point));
    }
    pmTypes = ['Polyline Simple','Polyline GML'];
    offset = 4;
    var points = [
        new mxn.LatLonPoint(45.256, -110.45), 
        new mxn.LatLonPoint(46.46, -109.48), 
        new mxn.LatLonPoint(43.84, -109.86)
    ];
    for (x=0; x<pmTypes.length; x++) {
        var item = items[x + offset];
        assertEquals(pmTypes[x] + ": item type matches", "polyline", item.getType());
        assertEquals(pmTypes[x] + ": placemark type matches", "polyline", TimeMap.util.getPlacemarkType(item.placemark));
        assertNotUndefined("polyline points undefined", item.placemark.points);
        assertEquals(pmTypes[x] + ": vertex count matches", 3, item.placemark.points.length);
        assertTrue(pmTypes[x] + ": info point matches middle point", item.getInfoPoint().equals(points[1]));
        for (var y=0; y<points.length; y++) {
            assertTrue("vertex " + y + " matches", item.placemark.points[y].equals(points[y]));
        }
    }
    pmTypes = ['Polygon Simple','Polygon GML'];
    offset = 6;
    // polygon bounds center
    var point = new mxn.LatLonPoint(45.150000000000006, -109.965);
    for (x=0; x<pmTypes.length; x++) {
        var item = items[x + offset];
        assertEquals(pmTypes[x] + ": placemark type matches", "polygon", item.getType());
        assertEquals(pmTypes[x] + ": placemark type matches", "polygon", TimeMap.util.getPlacemarkType(item.placemark));
        // Google seems to count the last vertex of a closed polygon
        assertNotUndefined("polyline points undefined", item.placemark.points);
        assertEquals(pmTypes[x] + ": vertex count matches", 4, item.placemark.points.length);
        assertTrue(pmTypes[x] + ": info point matches middle point", item.getInfoPoint().equals(point));
        for (var y=0; y<points.length; y++) {
            assertTrue("vertex " + y + " matches", item.placemark.points[y].equals(points[y]));
        }
    }
}

function testMixedKMLTime() {
    var ds = tm.datasets["mixed"];
    var items = tm.datasets["mixed"].getItems(), item, d, prefix;
    // TimeSpan
    item = items[8];
    // start
    d = item.event.getStart();
    prefix = item.getTitle() + " start ";
    assertEquals(prefix + "year matches", 1985, d.getUTCFullYear());
    assertEquals(prefix + "month matches", 0, d.getUTCMonth());
    assertEquals(prefix + "day matches", 2, d.getUTCDate());
    // end
    d = item.event.getEnd();
    prefix = item.getTitle() + " end ";
    assertEquals(prefix + "year matches", 2000, d.getUTCFullYear());
    assertEquals(prefix + "month matches", 0, d.getUTCMonth());
    assertEquals(prefix + "day matches", 2, d.getUTCDate());
    // TimeStamp
    item = items[9];
    // start
    d = item.event.getStart();
    prefix = item.getTitle() + " start ";
    assertEquals(prefix + "year matches", 1985, d.getUTCFullYear());
    assertEquals(prefix + "month matches", 0, d.getUTCMonth());
    assertEquals(prefix + "day matches", 2, d.getUTCDate());
    // is instant
    assertTrue(item.getTitle() + " event is instant", item.event.isInstant());
}

function testMixedExtraTags() {
    var ds = tm.datasets["mixed"];
    var items = tm.datasets["mixed"].getItems(), item, d, prefix;
    // Using a bare tag from the RSS xmlns
    item = items[10];
    assertEquals(item.getTitle() + " has link tag data", "http://www.example.com/", item.opts.link);
    // Using a namespaced tag from the DC xmlns
    item = items[11];
    assertEquals(item.getTitle() + " has dc:subject tag data", "Testing", item.opts['dc:subject']);
    // Using a bare tag from the RSS xmlns, mapped
    item = items[12];
    assertEquals(item.getTitle() + " has mapped tag data", "nick@example.com", item.opts.email);
    // Using a namespaced tag from the DC xmlns, mapped
    item = items[13];
    assertEquals(item.getTitle() + " has mapped tag data", "Nick", item.opts.issuer);
}


var tm = null;

function setUpPage() {
    TimeMap.util.nsMap['dc'] = 'http://purl.org/dc/elements/1.1/';
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset: RSS",
                id: "rss",
                type: "georss",
                options: {
                    url: "data/data.rss" 
                }
            },
            {
                title: "Test Dataset: Atom",
                id: "atom",
                type: "georss",
                options: {
                    url: "data/data-atom.xml" 
                }
            },
            {
                title: "Test Dataset: RSS, mixed formats",
                id: "mixed",
                type: "georss",
                options: {
                    url: "data/data-mixed.xml",
                    extraTags: ['link', 'dc:subject', 'author', 'dc:publisher'],
                    tagMap: {
                        'author':'email',
                        'dc:publisher':'issuer'
                    }
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}
