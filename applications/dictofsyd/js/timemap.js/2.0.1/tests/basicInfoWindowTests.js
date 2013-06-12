
IWT.dataset = {
    id: "test",
    type: "basic",
    options: {
        items: [
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 23.456,
                    "lon" : 12.345
                },
                "title" : "Test Event",
                "options" : {
                    "description": "Test Description"
                }
            }
        ]
    }
};

// tests

function exposeTestFunctionNames() {
    return [
        'testDefaultOpen',
        'testDefaultClose'
    ];
}

IWT.setup = function() {
    // this is the test preamble
    var ds = IWT.tm.datasets['test'];
    var item = ds.getItems()[0];
    item.openInfoWindow();
}

IWT.setupTest = function() {
    // this is effectively the assertion
    return $('div.infotitle, div.infodescription').length == 2
}
function testDefaultOpen() {
    assertTrue('Timeout with no info window divs found', IWT.success);
    assertEquals('Info window title incorrect', 
         'Test Event', $('div.infotitle').text());
    assertEquals('Info window description incorrect', 
        'Test Description', $('div.infodescription').text());
}

function testDefaultClose() {
    var ds = IWT.tm.datasets['test'];
    var item = ds.getItems()[0];
    item.closeInfoWindow();
    if (IWT.tm.map.api == 'microsoft') {
        // Bing just hides the divs
        assertEquals('Info window title is not hidden', 
            'hidden', $('div.infotitle').css('visibility'));
        assertEquals('Info window description is not hidden', 
            'hidden', $('div.infodescription').css('visibility'));
    } else {
        // everyone else seems to remove the divs from the DOM
        assertEquals('Info window div were found after the window was closed',
            0, $('div.infotitle, div.infodescription').length);
    }
}