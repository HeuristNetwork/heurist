// Simple Debug, written by Chris Klimas
// licensed under the GNU LGPL.
// http://www.gnu.org/licenses/lgpl.txt
//
// There are three functions defined here:
//
// log (message)
// Logs a message. Every second, all logged messages are displayed
// in an alert box. This saves you from having to hit Return a ton
// of times as your script executes.
//
// inspect (object)
// Logs the interesting properties an object possesses. Skips functions
// and anything in CAPS_AND_UNDERSCORES.
//
// inspectValues (object)
// Like inspect(), but displays values for the properties. The output
// for this can get very large -- for example, if you are inspecting
// a DOM element.


function log (message, loop)
{
	if (! _log_timeout)
		_log_timeout = window.setTimeout(dump_log, 1000);
	
	_log_messages.push(message);


	function dump_log()
	{
		var message = '';
		
		for (var i = 0; i < _log_messages.length; i++)
			message += _log_messages[i] + '\n';
				
        clearTimeout(_log_timeout);

		alert(message);

        _log_timeout = null;
        delete _log_messages;
        _log_messages = new Array();
		
	}
}


function inspect (obj)
{
	var message = 'Object possesses these properties:\n';
	
	if (obj)
	{
		for (var i in obj)
		{
			if ( (typeof(obj[i]) == "function") || (obj[i] == null) ||
					(i.toUpperCase() == i))
				continue;
			
			message += i + ', ';
		}
		
		message = message.substr(0, message.length - 2);
	}
	else
		message = 'Object is null';
	
	log(message);
}

function inspectValues (obj)
{
	var message = '';
	
	if (obj)
		for (var i in obj)
		{
			if ((typeof(obj[i]) == "function") || (obj[i] == null) ||
					(i.toUpperCase() == i))
				continue;
			
			message += i + ': ' + obj[i] + '\n';
		}
	else
		message = 'Object is null';
	
	log(message);
}

var _log_timeout;
var _log_messages = new Array();
