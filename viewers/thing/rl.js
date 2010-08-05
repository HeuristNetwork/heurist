/*
 *  Heurist Navigator framework
 *  Kim Jackson, October 2008
 */

var HNavigator = (function() {

/* private */ function _stateChange(module, state) {
	console.debug(module, ": ", state);
	$("#" + module).text(state);
};

return {

	init: function(defaults) {
		var initOps, initFilters, initViews;

		initOps = YAHOO.util.History.getBookmarkedState("ops");
		initFilters = YAHOO.util.History.getBookmarkedState("filters");
		initViews = YAHOO.util.History.getBookmarkedState("views");
		if (! initOps  &&  defaults["ops"]) {
			initOps = defaults["ops"];
		}
		if (! initFilters  &&  defaults["filters"]) {
			initFilters = defaults["filters"];
		}
		if (! initViews  &&  defaults["views"]) {
			initViews = defaults["views"];
		}

		YAHOO.util.History.register("ops", initOps, function(state) {
			_stateChange("ops", state);
		});
		YAHOO.util.History.register("filters", initFilters, function(state) {
			_stateChange("filters", state);
		});
		YAHOO.util.History.register("views", initViews, function(state) {
			_stateChange("views", state);
		});

		YAHOO.util.History.onReady(function() {
			_stateChange("ops", YAHOO.util.History.getCurrentState("ops"));
			_stateChange("filters", YAHOO.util.History.getCurrentState("filters"));
			_stateChange("views", YAHOO.util.History.getCurrentState("views"));
		});

		try {
			YAHOO.util.History.initialize("yui-history-field", "yui-history-iframe");
		} catch (e) {
			console.log("history manager init failed");
		}
	},

};



})();

