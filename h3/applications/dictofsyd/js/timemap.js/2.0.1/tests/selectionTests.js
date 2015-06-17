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
