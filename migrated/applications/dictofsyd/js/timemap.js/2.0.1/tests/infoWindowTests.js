
// namespace for info window tests
var IWT = {};
    
IWT.setUpEventually = function() {
    var timeoutLimit = 3000,
        timeoutInterval = 100,
        elapsed = 0,
        timeoutId;
    function look() {
        IWT.success = IWT.setupTest();
        if (IWT.success || elapsed > timeoutLimit) {
            setUpPageStatus = "complete";
        }
        else {
            elapsed += timeoutInterval;
            timeoutId = window.setTimeout(look, timeoutInterval);
        }
    }
    look();
}

function setUpPage() {
    IWT.tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [ 
            IWT.dataset
        ]
    });
    IWT.setup();
    IWT.setUpEventually();
}