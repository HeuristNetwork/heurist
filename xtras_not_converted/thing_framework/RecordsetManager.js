// Thing recordset manager is the layer that sits between HAPI and the application.


function RecordsetManager(em) {
    //private memebers
    var _className = "RecordsetManager";

    function _init() {
        em.addEvent("RecordsLoaded", that);  // this is fired by rm when ever records are finished loading
        em.subscribe("AppInit",_onAppInit,that);
        em.addEvent("NewQuery", that);  // this is fired by other modules to request a NewQuery
        em.subscribe("AppLoaded",_onAppLoaded,that);
    };

    function _onAppInit( eventName, params, obj) {
        // this event is fired by App when all the modules have been created  and have registered their events
        // we can now subscribe to any we want to receive
        em.subscribe("NewQuery",_onNewQuery,that);
        em.fire("ModuleLoaded", that.name);
    };

    function _onAppLoaded( eventName, params, obj) {
        // this event is fired by App when all the modules have been loaded
        // we can now start running the app
        console.debug(" RM received AppLoaded ");

    };

    function _onNewQuery(eventName, params, obj) {
        //someone has signaled a new query parse the params and load the records
        console.debug(" RM received "+eventName + " " + params);
        setTimeout(function(){
                _onQueryResultsCallback("received results for "+ params + " query!");
            },3000);
    };

    function _onQueryResultsCallback(results) {
        //someone has signaled a new query parse the params and load the records
        console.debug(" RM received resulsts for NewQuery ");
        em.fire("RecordsLoaded", that.name + " " + results);
    };

    //public members
    var that = {
        name : "rm",
        getClass: function() { return _className;},
        isA: function(strClass) { if(strClass === _className) return true; return false;}
    };

    _init();
    return that;
}