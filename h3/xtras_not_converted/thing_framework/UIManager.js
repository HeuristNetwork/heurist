function UIManager(em){
    //private memebers
    var _className = "UIManager";
    // setup HTML for top level YUI layout object
    var _htmlTopLayout = 	'<div id="header">' +
    '<div id="appHeader" style="height:25px;" >' +
    '</div>' +
    '<div id="myMenuBar" class="yuimenubar yuimenubarnav">' +
    '<span style="float:right;"><input type="text"  id="newQuery" style="width:150px; margin-top:3px;">' +
    '<label onclick="' +"App.em.fire('NewQuery',document.getElementById('newQuery').value);" +
    '" for="newQuery">&nbsp;&nbsp;Search&nbsp;&nbsp;</label></span>' +
    '</div>' +
    '</div>' +
    '<div id="footer"></div>' +
    '<div id="main"></div>';
    // config structure to tell YUI which elements belong to which layout units. Note position of unit determines final rendered location
    var _cfgTopLayout = {
        units: [
            { position: 'top', height: 50,  body: 'header',scroll: null, zIndex: 1},
            { position: 'bottom',  height: 20, body: 'footer'},
            { position: 'center', body: 'main'},
        ]
    };
    function _initTopMenu() {

        /*
        Define an array of object literals, each containing
        the data necessary to create a submenu.
        */

        var menuData = [

            {
                text: "File",
                submenu: {
                    id: "file",
                    itemdata: [
                        { text: "Load Search"},
                        { text: "Save Search"},
                        { text: "Import",
                            submenu: {
                                id: "import",
                                itemdata: [
                                    { text: "KML"},
                                    { text: "XML"},
                                    { text: "SQL"},
                                    { text: "CSV"}
                                ]
                            }
                        }
                    ]
                }
            },

            {
                text: "View",
                submenu: {
                    id: "view",
                    itemdata: [
                        { text: "List"},
                        { text: "TreeView"},
                        { text: "Map"},
                        { text: "Directed Graph"},
                        { text: "3D Directed Graph"},
                        { text: "XML"},
                        { text: "3D Virtual"}
                    ]
                }
            }
        ];

        /*
        Instantiate a MenuBar:  The first argument passed to the
        constructor is the id of the element in the page
        representing the MenuBar; the second is an object literal
        of configuration properties.
        */

        var tMenuBar = new YAHOO.widget.MenuBar("thingMenuBar", {
                autosubmenudisplay: true,
                hidedelay: 200,
                itemdata: menuData,
                lazyload: true,
                effect: {
                    effect: YAHOO.widget.ContainerEffect.FADE,
                    duration: 0.25
                }
        });


        //   tMenuBar.addItems(menuData);

        tMenuBar.render("myMenuBar");


        function onClick(p_sType, p_aArgs) {

            var oEvent = p_aArgs[0],    // DOM Event
            oMenuItem = p_aArgs[1]; // YAHOO.widget.MenuItem instance

            if (oMenuItem) {
                console.debug("sending command event for :" + oMenuItem.cfg.getProperty("text"));
                em.fire("Command", oMenuItem.cfg.getProperty("text"),oMenuItem);
            }
        }
        // Subscribe to the "click" event

        tMenuBar.subscribe("click", onClick);
        /*
        Call the "render" method with id of the element to insert the
        markup for this MenuBar instance.
        */


    };

    var _topLayout;
    /* The init function is for construction time code that sets up the basic infrastructure for the object
    - creating contained objects
    - registration
    - system set up
    */
    function _init() {
        em.addEvent("UILoaded", that);  // this is fired by uim when teh ui is loaded or re-loaded
        em.addEvent("NewLayout", that);  // this is fired by other modules to request a layout change
        em.addEvent("Command", that);  // this is fired by other modules to request a layout change
        // in this model we assume that the application creates all framework modules
        // since UIManager is a framework module we register handlers for thestart up sequence
        em.subscribe("AppInit",_onAppInit,that);
        em.subscribe("AppLoaded",_onAppLoaded,that);
        //insert the main layout framework
        document.getElementById("topDiv").innerHTML = _htmlTopLayout;
        //register code to setup Layout and Menus when the DOM is ready
        Event.onDOMReady(function() {
                _topLayout = new YAHOO.widget.Layout(_cfgTopLayout);
                _topLayout.render();
                _initTopMenu();
        });
    };


    /* The AppInit function is for initialization that relies on co-objects to be created
    - wiring up with co-objects (getting interface - or subscribing to services or events)

    at bare minimum all system level objects must subscribe to AppInit event and implement a
    callback that fires the ModuleLoaded event passing it's name. This allow the framework to
    know when all modules are completely loaded so that it can fire the AppLoaded event.
    */
    function _onAppInit(eventName, params, obj) {
        // we can now subscribe to any events we want to receive
        em.subscribe("NewLayout",_onNewLayout,that);
        em.subscribe("RecordsLoaded",_onRecordSetLoaded,that);
        // notify the frame work that we are done loading - assuming that this is the end of loading
        em.fire("ModuleLoaded",that.name);
    };

    /* The AppLoaded function is for code to start the app running. It singles that the UI is loaded and ready for
    user interaction. Typically one of the system modules will start the ball rolling.
    */
    function _onAppLoaded(eventName, params, obj) {
        // we can now start running the app
        console.debug(" UIM received AppLoaded ");

    };

    function _onNewLayout(eventName, params, obj) {
        //someone has signaled a new layout parse the params and update the UI
        console.debug(" UIM received NewLayout ");
    };

    function _onRecordSetLoaded(eventName, params, obj) {
        //someone has signaled a new layout parse the params and update the UI
        console.debug(" UIM received "+eventName + " " + params);
    };

    //public members
    var that = {
        name : "uim",
        getClass: function() { return _className;},
        isA: function(strClass){ if(strClass === _className) return true; return false;}
    };

    _init();
    return that;
}


