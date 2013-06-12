function exposeTestFunctionNames() {
    return [
        'testDelete',
        'testClear'
    ];
}

function testDelete() {
    var ds = tm.datasets["testA"];
    assertEquals("Two items in item array", ds.getItems().length, 2);
    assertEquals("4 events in timeline", tm.timeline.getBand(0).getEventSource().getCount(), 4);
    var item = ds.getItems()[0];
    ds.deleteItem(item);
    assertEquals("One item in item array after deletion", ds.getItems().length, 1);
    assertEquals("3 events in timeline after deletion", tm.timeline.getBand(0).getEventSource().getCount(), 3);
    tm.deleteDataset("testC");
    assertUndefined("Dataset C has been deleted", tm.datasets["testC"]);
}

function testClear() {
    var ds = tm.datasets["testB"];
    assertEquals("two items in item array", ds.getItems().length, 2);
    assertEquals("3 events in timeline", tm.timeline.getBand(0).getEventSource().getCount(), 3);
    var item = ds.getItems()[0];
    item.clear();
    assertNull("Placemark has been deleted", item.placemark);
    assertNull("Event has been deleted", item.event);
    assertEquals("2 events in timeline", tm.timeline.getBand(0).getEventSource().getCount(), 2);
    ds.clear();
    assertEquals("No items in item array", ds.getItems().length, 0);
    assertEquals("1 event in timeline", tm.timeline.getBand(0).getEventSource().getCount(), 1);
    tm.clear();
    assertUndefined("Dataset A has been deleted", tm.datasets["testA"]);
    assertUndefined("Dataset B has been deleted", tm.datasets["testB"]);
    assertEquals("No events in timeline", tm.timeline.getBand(0).getEventSource().getCount(), 0);
}

var tm = null;

function setUpPage() {
    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [
            {
                title: "Test Dataset A",
                id: "testA",
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
                          "title" : "Test Event A1"
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Event A2"
                        }
                    ]
                }
            },
            {
                title: "Test Dataset B",
                id: "testB",
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
                          "title" : "Test Event B1"
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Event B2"
                        }
                    ]
                }
            },
            {
                title: "Test Dataset C",
                id: "testC",
                type: "basic",
                options: {
                    items: []
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
