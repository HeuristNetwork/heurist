function exposeTestFunctionNames() {
    return [
        'testThemeCascade',
        'testEventIconPath',
        'testMarkerIconPath'
    ];
}

function testThemeCascade() {
    var tmTheme = TimeMap.themes.blue;
    var dsThemeA = TimeMap.themes.green;
    var dsThemeA2 = TimeMap.themes.orange;
    var dsThemeB2 = TimeMap.themes.yellow;
    var dsThemeC = TimeMap.themes.purple;
    var dsThemeC3 = customTheme;
    // go through the items one by one
    var item = tm.datasets["testA"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Dataset A theme", dsThemeA.color, item.event._color);
    var item = tm.datasets["testA"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Dataset A theme", dsThemeA2.color, item.event._color);
    var item = tm.datasets["testB"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Timemap theme", tmTheme.color, item.event._color);
    var item = tm.datasets["testB"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Timemap theme", dsThemeB2.color, item.event._color);
    var item = tm.datasets["testC"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Dataset C theme", dsThemeC.color, item.event._color);
    var item = tm.datasets["testC"].getItems()[2];
    assertEquals(item.getTitle() + " uses custom theme color", dsThemeC3.color, item.event._color);
}

function testEventIconPath() {
    var tmPath = '../images/';
    var dsPathA = '../images/dsA/';
    var dsPathA2 = '../images/dsA2/';
    var dsPathC3 = '../images/dsC3/';
    // go through the items one by one
    var item = tm.datasets["testA"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Dataset A path", dsPathA, item.event._icon.substr(0, 14));
    var item = tm.datasets["testA"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Dataset A path", dsPathA2, item.event._icon.substr(0, 15));
    var item = tm.datasets["testB"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Timemap path", tmPath, item.event._icon.substr(0, 10));
    var item = tm.datasets["testB"].getItems()[1];
    assertEquals(item.getTitle() + " inherits Timemap path", tmPath, item.event._icon.substr(0, 10));
    var item = tm.datasets["testC"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Timemap path", tmPath, item.event._icon.substr(0, 10));
    var item = tm.datasets["testC"].getItems()[2];
    assertEquals(item.getTitle() + " inherits Timemap path", tmPath, item.event._icon.substr(0, 10));
}

function testMarkerIconPath() {
    var tmTheme = TimeMap.themes.blue;
    var dsThemeA = TimeMap.themes.green;
    var dsThemeA2 = TimeMap.themes.orange;
    var dsThemeB2 = TimeMap.themes.yellow;
    var dsThemeC = TimeMap.themes.purple;
    var dsThemeC3 = customTheme;
    // go through the items one by one
    var item = tm.datasets["testA"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Dataset A marker icon", dsThemeA.icon, item.placemark.iconUrl);
    var item = tm.datasets["testA"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Dataset A marker icon", dsThemeA2.icon, item.placemark.iconUrl);
    var item = tm.datasets["testB"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Timemap marker icon", tmTheme.icon, item.placemark.iconUrl);
    var item = tm.datasets["testB"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Timemap marker icon", dsThemeB2.icon, item.placemark.iconUrl);
    var item = tm.datasets["testC"].getItems()[0];
    assertEquals(item.getTitle() + " inherits Dataset C marker icon", dsThemeC.icon, item.placemark.iconUrl);
    var item = tm.datasets["testC"].getItems()[1];
    assertEquals(item.getTitle() + " overrides Dataset C marker icon", customIcon, item.placemark.iconUrl);
    var item = tm.datasets["testC"].getItems()[2];
    assertEquals(item.getTitle() + " uses custom theme marker icon", dsThemeC3.icon, item.placemark.iconUrl);
}

var tm = null, customIcon, customTheme;

function setUpPage() {
    customIcon = "fakeimg.png";
    
    customTheme = new TimeMapTheme({
        "color": "#0000FF",
        "icon": customIcon,
        "eventIconPath": '../images/dsC3/'
    });

    tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required)
        options: {
            eventIconPath: '../images/',
            theme: "blue"
        },
        datasets: [
            {
                title: "Test Dataset A",
                id: "testA",
                theme: "green",
                type: "basic",
                options: {
                    eventIconPath: '../images/dsA/',
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
                          "title" : "Test Event A2",
                          "options": {
                            "eventIconPath": '../images/dsA2/',
                            "theme": "orange"
                          }
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
                          "title" : "Test Event B2",
                          "options": {
                            "theme": "yellow"
                          }
                        }
                    ]
                }
            },
            {
                title: "Test Dataset C",
                id: "testC",
                theme: "purple",
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
                          "title" : "Test Event C1"
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Event C2",
                          "options" : {
                              "icon": customIcon
                          }
                        },
                        {
                          "start" : "1980-01-02",
                          "end" : "1990-01-02",
                          "point" : {
                              "lat" : 23.456,
                              "lon" : 12.345
                           },
                          "title" : "Test Event C3",
                          "options": {
                            "theme": customTheme
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
    var eventSource = tm.timeline.getBand(0).getEventSource();
    tm.timeline.getBand(0).setCenterVisibleDate(eventSource.getEarliestDate());
    tm.showDatasets();
}
