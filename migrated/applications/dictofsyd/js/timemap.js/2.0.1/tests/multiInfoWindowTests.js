

IWT.dataset = {
    id: "test",
    type: "basic",
    options: {
        items: [
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 23.0,
                    "lon" : 12.0
                },
                "title" : "Test Event 1",
                "options" : {
                    "description": "Test Description"
                }
            },
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 24.0,
                    "lon" : 13.0
                },
                "title" : "Test Event 2",
                "options" : {
                    "description": "Test Description"
                }
            },
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 25.0,
                    "lon" : 14.0
                },
                "title" : "Test Event 3",
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
        'testMultipleWindows',
        'testCloseOnScroll'
    ];
}

IWT.setup = function() {
    // this is the test preamble
    var ds = IWT.tm.datasets['test'],
        x = 0;
    // we need some delay or Google maps v3 won't close windows
    function openItem() {
        var item = ds.getItems()[x++];
        if (item) {
            item.openInfoWindow();
            setTimeout(openItem, 150);
        }
    }
    openItem();
}
IWT.setupTest = function() {
    // this is effectively the assertion
    return $('div.infotitle, div.infodescription').length == 2 &&
        $('div.infotitle').text() == 'Test Event 3'
        
}
function testMultipleWindows() {
    assertTrue('Timeout with more or fewer than one info window, or wrong info window open', IWT.success);
}

function testCloseOnScroll() {
    IWT.tm.timeline.getBand(0).setCenterVisibleDate(new Date());
    if (IWT.tm.map.api == 'microsoft') {
        // Bing just hides the divs
        assertEquals('Info window content is not hidden', 
            'hidden',$('div.infotitle').css('visibility'));
    } else {
        // everyone else seems to remove the divs from the DOM
        assertEquals('Info window div were found after the timeline was scrolled',
            0, $('div.infotitle').length);
    }
}