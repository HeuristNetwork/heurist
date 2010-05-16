// Thing event manager is a thin wrapper on top of YUI events to enable messaging between components of this application.


function EventManager() {
	// private memebers
	var _events = new Array();


	// public members
	var that = {
		name : "em",

		fire : function(name, data) {
					console.debug("em.fire - " + name + " " + data);
					if (_events[name]) {
						return _events[name].fire(data);
					}
		       },

		subscribe : function(name, callback, obj) {
					console.debug("em.subscribe - " + name + " by " + (obj && obj.name ? obj.name: ""));
					if (_events[name]) {
						_events[name].subscribe(callback, obj);
					}
		       },

		// create a custom event so that others can subscribe to it.
		addEvent : function(name, scopeForHandler) {
					if ( !_events[name]) {  // Fixme  add code to verify that the scope is the same
						_events[name] = new YAHOO.util.CustomEvent(name, scopeForHandler);
					}
					return _events[name];
		       }

	};

	return that;
}