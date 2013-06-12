function exposeTestFunctionNames() {
    return [
        'testLoaded',
        'testEmptyTag',
        'testExtendedData',
        'testUnboundedSpan',
        'testLongTagValue',
        'testSiblingDate',
        'testFolderDate'
    ];
}

function testLoaded() {
    var ds = tm.datasets["test"];
    assertNotUndefined("Dataset is defined", ds);
    assertEquals("Correct number of items in item array", 8, ds.getItems().length);
}

function testEmptyTag() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[0];
    assertEquals("Description not found", "", item.opts.description);
}

function testExtendedData() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[1];
    assertEquals("ExtendedData element loaded", "Test 1", item.opts.Test1);
    assertEquals("Mapped ExtendedData element loaded", "Test 2", item.opts.foo);
}

function testUnboundedSpan() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[2];
    assertEquals("Start year matches", 1980, item.getStart().getUTCFullYear());
    assertEquals("Start month matches", 0, item.getStart().getUTCMonth());
    assertEquals("Start day matches", 2, item.getStart().getUTCDate());
    d = new Date();
    assertEquals("End year defaults to present", d.getUTCFullYear(), item.getEnd().getUTCFullYear());
    assertEquals("End month defaults to present", d.getUTCMonth(), item.getEnd().getUTCMonth());
    assertEquals("End day defaults to present", d.getUTCDate(), item.getEnd().getUTCDate());
}

function testLongTagValue() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[3];
    assertEquals("Description char count correct", 5000, item.opts.description.length);
}

function testSiblingDate() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[5];
    assertEquals("Start year matches", 1980, item.getStart().getUTCFullYear());
    assertEquals("Start month matches", 0, item.getStart().getUTCMonth());
    assertEquals("Start day matches", 2, item.getStart().getUTCDate());
    item = items[4];
    assertNull(item.getTitle() + " start is not null - inherited from sibling", item.getStart());
}

function testFolderDate() {
    var ds = tm.datasets["test"],
        items = ds.getItems(),
        item = items[7];
    assertEquals("Start year does not match", 1980, item.getStart().getUTCFullYear());
    assertEquals("Start month does not match", 0, item.getStart().getUTCMonth());
    assertEquals("Start day does not match", 2, item.getStart().getUTCDate());
    item = items[6];
    assertEquals("Start year does not match Folder", 1990, item.getStart().getUTCFullYear());
    assertEquals("Start month does not match Folder", 0, item.getStart().getUTCMonth());
    assertEquals("Start day does not match Folder", 2, item.getStart().getUTCDate());
}


var tm = null;

function setUpPage() {
    TimeMap.util.nsMap['dc'] = 'http://purl.org/dc/elements/1.1/';
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset: KML",
                id: "test",
                type: "kml",
                options: {
                    url: "data/test.kml",
                    extendedData: ['Test1', 'Test2'],
                    tagMap: {
                        'Test2':'foo'
                    }
                }
            }
        ],
        dataDisplayedFunction: function() { setUpPageStatus = "complete"; }
    });
}
