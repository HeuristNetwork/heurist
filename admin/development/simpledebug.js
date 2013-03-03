/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

﻿// Simple Debug, written by Chris Klimas
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
