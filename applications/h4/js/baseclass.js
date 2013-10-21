/**
* NOT USED (just template for copy paste)
*/
function BaseClass(args) {
     var _className = "BaseClass",
         _version   = "0.4";

    /**
    * Initialization
    */
    function _init(args) {

    }

    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},



    }

    _init(args);
    return that;  //returns object
}
