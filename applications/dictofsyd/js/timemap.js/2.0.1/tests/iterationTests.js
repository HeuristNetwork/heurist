function exposeTestFunctionNames() {
    return [
        'testGetItems',
        'testGetNext',
        'testGetPrev',
        'testEach'
    ];
}

function testGetItems() {
    var eventSource = tm.timeline.getBand(0).getEventSource(), items, item;
    assertEquals("Four items in eventSource", 4, eventSource.getCount());
    items = tm.getItems();
    assertEquals("Timemap got all items", 4, items.length);
    items = tm.datasets['test1'].getItems();
    
    items = tm.datasets['test2'].getItems();
    assertEquals("Dataset 2 got its items", 2, items.length);
    item = tm.datasets['test1'].getItems(1);
    assertEquals("Dataset got correct item by index", 'Test 1', item.getTitle());
}

function testGetNext() {
    var item, target;
    item = tm.datasets['test1'].getItems(1);
    target = tm.datasets['test1'].getItems(0);
    assertEquals("Found next w/in dataset", target, item.getNext());
    item = tm.datasets['test1'].getItems(0);
    target = tm.datasets['test2'].getItems(1);
    assertEquals("Found next across datasets", target, item.getNext());
    item = tm.datasets['test2'].getItems(0);
    target = null;
    assertEquals("Last item had no next", target, item.getNext()); 
}

function testGetPrev() {
    var eventSource = tm.timeline.getBand(0).getEventSource();
    // getPrev requires Timeline 2.2.0+ - skip test otherwise
    if (eventSource.getReverseIterator) {
        var item, target;
        item = tm.datasets['test1'].getItems(0);
        target = tm.datasets['test1'].getItems(1);
        assertEquals("Found previous w/in dataset", target, item.getPrev());
        item = tm.datasets['test2'].getItems(1);
        target = tm.datasets['test1'].getItems(0);
        assertEquals("Found previous across datasets", target, item.getPrev());
        item = tm.datasets['test1'].getItems(1);
        target = null;
        assertEquals("First item had no previous", target, item.getPrev()); 
    }
}

function testEach() {
    var f1 = function(item) { item.flag = 1; };
    tm.datasets['test1'].each(f1);
    var items = tm.datasets['test1'].getItems();
    for (var x=0; x<items.length; x++) {
        assertEquals("Database function has been applied to item " + items[x].getTitle(), 1, items[x].flag);
    }
    var f2 = function(item) { item.flag = 2; };
    tm.eachItem(f2);
    var items = tm.getItems();
    for (x=0; x<items.length; x++) {
        assertEquals("Timemap function has been applied to item " + items[x].getTitle(), 2, items[x].flag);
    }
    var f3 = function(ds) { ds.flag = 3; };
    tm.each(f3);
    for (x=1; x<=2; x++) {
        var ds = tm.datasets['test' + x];
        assertEquals("Timemap function has been applied to dataset " + ds.getTitle(), 3, ds.flag);
    }
}

// page setup script
function setUpPage() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset 1",
                id: "test1",
                type: "basic",
                options: {
                    items: [
                        {
                          "start" : "1980-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test 2"
                        },
                        {
                          "start" : "1980-01-01",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test 1"
                        }
                    ]
                }
            },
            {
                title: "Test Dataset 2",
                id: "test2",
                type: "basic",
                options: {
                    items: [
                        {
                          "start" : "1980-01-05",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test 4"
                        },
                        {
                          "start" : "1980-01-04",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test 3"
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
