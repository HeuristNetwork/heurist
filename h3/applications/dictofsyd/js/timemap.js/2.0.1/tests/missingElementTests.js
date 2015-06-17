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
