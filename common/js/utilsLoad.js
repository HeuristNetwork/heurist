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
* Essential utility functions/vars for loading Heurist pages -- make sure these are loaded first
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
* @subpackage  Common
*/


if (! document.body) {
    // Document manipulation becomes much harder if we can't access the body.
    throw document.location.href + ": include utilsLoad.js in the body, not the head";
}

if (! top.HEURIST) {
    var installDir = top.location.pathname.replace(/(((admin|applications|common|export|external|hapi|help|import|records|search|viewers)\/.*)|(index.*))/, "");

    /**
     * main Heurist Namespace
     * @namespace
     * @type object
     */
    top.HEURIST = {
        /**
         * current version - it will be overwritten by setting in loadCommonInfo.php
         * @type String
         */
        VERSION: "*.*.*",

        basePath: installDir,
/**
 * bookmarklet code snippet to launch an applet for Heurist bookmarking from the browser.
 * @todo needs to change to be perminent heuristscholar or relative to the installed version's INSTALL_DIR  can use basePath
 */
        bookmarkletCode: "(function(){h='" + document.location.href.replace(/search\/.*/,"") + "';d=document;c=d.contentType;if(c=='text/html'||!c){if(d.getElementById('__heurist_bookmarklet_div'))return%20Heurist.init();s=d.createElement('script');s.type='text/javascript';s.src=h+'import/bookmarklet/bookmarkletPopup.php?db=;d.getElementsByTagName('head')[0].appendChild(s);}else{e=encodeURIComponent;w=open(h+'records/add/addRecord.php?db=&t='+e(d.title)+'&u='+e(location.href));window.setTimeout('w.focus()',200);}})();",
/**
* generate a unique id for a window
*
* @param {string} url
*/
        createHeuristWindowID: function(url) {
            return ((Math.random().toString()).substring(2) + "-" + url).substring(0, 255);
        },

        contentLoadedSubscribers: [],
        onloadSubscribers: [], prioritisedOnloadSubscribers: {},
/**
* register or subscribe to an event, so the system will call the fn function when the event is fired
*
* @param {Object} element
* @param {String} eventType
* @param {Function} fn
* @param {Boolean} capture
* @param {int} priority
*/
        registerEvent: function(element, eventType, fn, capture, priority) {
            eventType = eventType.toLowerCase();

            if (eventType == 'contentloaded') {
                if (window.contentLoaded) {
                    // if content already loaded, invoke our event right away
                    if (top.HEURIST_USE_ASYNC_HANDLERS)
                        setTimeout(function() { fn.apply(element); }, 0);
                    else
                        fn.apply(element);
                } else {
                    top.HEURIST.contentLoadedSubscribers.push([element, fn]);
                }
                return;
            }
            if (eventType == 'load') {
                if (element == window  &&  window.loaded) {
                    // in case onload has already fired, we should make this fire straight away
                    if (top.HEURIST_USE_ASYNC_HANDLERS)
                        setTimeout(function() { fn.apply(element); }, 0);
                    else
                        fn.apply(element);
                    return;

                } else {
                    if (top.HEURIST_USE_FAKE_LOAD_EVENT) {
                        // otherwise, keep our own event queue.  Onload is tricky, we want to have control over order.
                        if (! priority) {
                            top.HEURIST.onloadSubscribers.push([element, fn]);
                        } else {
                            if (! top.HEURIST.prioritisedOnloadSubscribers[priority]) {
                                top.HEURIST.prioritisedOnloadSubscribers[priority] = [];
                            }
                            top.HEURIST.prioritisedOnloadSubscribers[priority].push([element, fn]);
                        }
                        return;
                    }
                }
            }

            if (eventType.match(/^heurist-/)) {
                // custom event
                if (! element.heuristListeners) {
                    element.expando = true;
                    element.heuristListeners = {};
                }
                if (! element.heuristListeners[eventType]) element.heuristListeners[eventType] = [];
                element.heuristListeners[eventType].push(fn);
            }
            else {
                // regular event
                if (window.addEventListener) {
                    element.addEventListener(eventType, fn, capture);
                } else if (window.attachEvent) {
                    element.attachEvent("on" + eventType, fn);
                } else {
                    /* nothing we can do, really, without [possibly] clobbering an existing handler */
                }
            }

            return fn;
        },
/**
* remove listen request for a given event
*
* @param element
* @param eventType
* @param fn
* @param capture
*/
        deregisterEvent: function(element, eventType, fn, capture) {
            eventType = eventType.toLowerCase();
            if (eventType.match(/^heurist-/)) {
                // custom event
                if (! element.heuristListeners  ||  ! element.heuristListeners[eventType]) return;

                var listeners = element.heuristListeners[eventType];
                for (var i=0; i < listeners.length; ++i) {
                    if (listeners[i] == fn) {
                        listeners.splice(i, 1);
                        return fn;
                    }
                }
            }
            else {
                if (window.removeEventListener) {
                    element.removeEventListener(eventType, fn, capture);
                } else if (window.detachEvent) {
                    element.detachEvent("on" + eventType, fn);
                }
            }

            return fn;
        },
/**
* list of events that have been fired
*
* @type Object
*/
        firedEvents: {},
/**
* fires event to all registered listeners
*
* @param element
* @param eventType
* @param arglist
*/
        fireEvent: function(element, eventType, arglist) {
            if(element){
                eventType = eventType.toLowerCase();
                top.HEURIST.firedEvents[eventType] = true;

                if (eventType.match(/^heurist-/)) {
                    // custom event
                    if (! element.heuristListeners) return;	// nothing to do here
                    if (! element.heuristListeners[eventType]) return;

                    var listeners = element.heuristListeners[eventType];
                    for (var i=0; i < listeners.length; ++i)
                        listeners[i].call(element, eventType, arglist);
                }
                else {
                    // regular event ... make no promises!
                    try {
                        element[eventType]();
                    } catch (e) {}
                }
            }
        },
/**
* resets event fired flag
*
* @param eventType
*/
        resetEventFlag: function(eventType) {
            top.HEURIST.firedEvents[eventType] = false;
        },
/**
* load script file synchronously or asynchronously
*
* @param scriptURL
* @param {Boolean} blocking
*/
        loadScript: function(scriptURL, blocking) {
            /* Load a script without blocking the load of the rest of the document */
            /* 2007/07/12: If blocking is true, then load the script *with* blocking */
            if (! this.document) win = top;
            else win = this;

            if (top.HEURIST.browser.isEarlyWebkit)	// old safari doesn't grok the non-blocking script loading
                blocking = true;

            if (! blocking) {
                var scriptElt = win.document.createElement("script");

                // nb: set type and src BEFORE inserting the script into the document,
                // otherwise early Safari chokes
                scriptElt.type = "text/javascript";
                scriptElt.src = scriptURL;
                win.document.getElementsByTagName("head")[0].appendChild(scriptElt);
            } else {
                // We want to do this -- sometimes we don't know the name of the script we need to load inline
                win.document.write('<script src="' + encodeURI(scriptURL) + '"></script>');
            }
        },

        get_user_id: function() { return 0; },
        get_user_name: function() { return "anonymous"; },
        get_user_username: function() { return "not logged in"; },
/**
* get position of a document element as absolute or relative screen coordinates
*
* @param element
* @param {Boolean} absolute
*
* @returns {Object}
*/
        getPosition: function(element, absolute) {
            // should really be in util, but it is very much part of the standard toolkit
            var x = 0, y = 0;
            var _element = element;
            while (element) {
                x += element.offsetLeft;
                y += element.offsetTop;
                element = element.offsetParent;
            }

            // We are getting a position relative to the TOP window
            if (absolute) {
                while (_element.nodeType != 9) _element = _element.parentNode;
                var documentRef = _element;

                var windowRef = (documentRef.parentWindow  ||  documentRef.defaultView);
                if (windowRef &&  windowRef.frameElement) {
                    var windowPos = top.HEURIST.getPosition(windowRef.frameElement, true);

                    var scrollLeft = windowRef.document.body.scrollLeft || windowRef.document.documentElement.scrollLeft;
                    var scrollTop = windowRef.document.body.scrollTop || windowRef.document.documentElement.scrollTop;
                    x += windowPos.x - scrollLeft;
                    y += windowPos.y - scrollTop;
                }
            }

            return { "x": x, "y": y };
        },
/**
* parse a string for parameters assuming a URI type query string
*   ?param=val&param2=val2
* parses window.location.search if no parameter string passed in and
* stores the value in HEURIST.parameters
*
* @param paramString
*
* @returns {Object}
*/
        parseParams: function(paramString) {
            if (! paramString) {
                paramString = window.location.search;
                var setHeuristGlobal = true;
            } else {
                var setHeuristGlobal = false;
            }
            if (paramString.charAt(0) == '?') {
                paramString = paramString.substring(1);
            }

            paramString = paramString.replace(/[+]/g, ' ');	// frustratingly, decodeURIComponent does not decode '+' to ' '

            var parmBits = paramString.split('&');
            var parms = {};
            for (var i=0; i < parmBits.length; ++i) {
                var equalPos = parmBits[i].indexOf('=');
                var parmName = decodeURIComponent(parmBits[i].substring(0, equalPos));
                if (equalPos >= 0) {
                    parms[parmName] = decodeURIComponent(parmBits[i].substring(equalPos+1));
                } else {
                    parms[parmName] = null;
                }
            }

            if (setHeuristGlobal) {
                if (! window.HEURIST) window.HEURIST = {};
                window.HEURIST.parameters = parms;
            }

            return parms;
        },

/**
* Returns value of given parametr for current query
*/
        getQueryVar: function(varName){
            // Grab and unescape the query string - appending an '&' keeps the RegExp simple
            // for the sake of this example.
            var queryStr = unescape(window.location.search) + '&';

            // Dynamic replacement RegExp
            var regex = new RegExp('.*?[&\\?]' + varName + '=(.*?)&.*');

            // Apply RegExp to the query string
            val = queryStr.replace(regex, "$1");

            // If the string is the same, we didn't find a match - return false
            return val == queryStr ? false : val;
        },
/**
* Special event to be called when the page has been parsed, but external
* content has not necessarily been loaded.  Use this for when you need
* to refer to document elements, but don't care about final layout.
*/
        contentLoaded: function() {

            if (window.contentLoaded) return;	/* only invoke once */
            window.contentLoaded = true;

            var runner = function() {
                for (var i=0; i < top.HEURIST.contentLoadedSubscribers.length; ++i) {
                    (top.HEURIST.contentLoadedSubscribers[i][1]).apply(top.HEURIST.contentLoadedSubscribers[i][0]);
                }
            };

            if (top.HEURIST_USE_ASYNC_HANDLERS)
                setTimeout(runner, 0);	// detach from main thread
            else
                runner();
        },
/**
* IE7 doesn't appear to finalise layout until *after* the load has finished.
* By firing our onload subscribers out-of-band from the usual onload mechanism,
* we can avoid some layout problems to do with the autosize.
*/
        invokeOnloadSubscribers: function() {
            window.loaded = true;

            var runner = function() {
                for (var i=0; i < top.HEURIST.onloadSubscribers.length; ++i) {
                    (top.HEURIST.onloadSubscribers[i][1]).apply(top.HEURIST.onloadSubscribers[i][0]);
                }

                // Uglay ... find if any priorities subscribers have been used, then invoke them in order
                var priorities = [];
                for (var priority in top.HEURIST.prioritisedOnloadSubscribers) {
                    priorities.push(parseInt(priority));
                }
                if (priorities.length == 0) return;

                priorities.sort(function(a,b) { return a-b; });
                for (var i=0; i < priorities.length; ++i) {
                    var subscribers = top.HEURIST.prioritisedOnloadSubscribers[priorities[i]];
                    for (var j=0; j < subscribers.length; ++j) {
                        (subscribers[j][1]).apply(subscribers[j][0]);
                    }
                }
            };

            if (top.HEURIST_USE_ASYNC_HANDLERS)
                setTimeout(runner, 0);	// detach from main thread
            else
                runner();
        },
/**
* arrays of the objects waiting for given object to load
*
* @type Object
*/
        whenLoadedLists: {},
/**
* execute code based on objects loaded, if no objects or all objects are loaded run code immediately
* else register for delay callback until all listed objects have signaled they are loaded
*
* @param {Array} objects
* @param fn
*/
        whenLoaded: function(objects, fn) {
            // invoke the given function when ALL the named objects (script files, html files) have loaded
            if (! objects) { fn(); return; }

            if (objects.substring) objects = [ objects ];	// just a single name

            var notYetLoaded = [];
            for (var i=0; i < objects.length; ++i) {
                var objectName = objects[i].toLowerCase();
                if (! top.HEURIST.firedEvents["heurist-" + objectName + "-loaded"])
                    notYetLoaded.push(objectName);
            }
            if (notYetLoaded.length == 0) { fn(); return; }

            // okay: we have some objects that are NOT YET LOADED: register handlers for their load events
            for (var i=0; i < notYetLoaded.length; ++i) {
                var objectName = notYetLoaded[i];
                if (! top.HEURIST.whenLoadedLists[objectName]) {
                    // This is the first callback waiting for this object to load.  Set up a handler ...
                    top.HEURIST.whenLoadedLists[objectName] = [];
                    top.HEURIST.registerEvent(top, "heurist-"+objectName+"-loaded", top.HEURIST.whenLoadedHandler);
                }
                top.HEURIST.whenLoadedLists[objectName].push({ callback: fn, list: notYetLoaded });
            }
        },
/**
* when an object fires it's load event check if there are any waiting functions to execute.
*
* @param eventName
*/
        whenLoadedHandler: function(eventName) {
            var objectName = eventName.replace(/^heurist-(.*)-loaded$/, "$1").toLowerCase();
            var whenLoadedList = top.HEURIST.whenLoadedLists[objectName];
            if (! whenLoadedList) return;

            // whenLoadedList is a list of all the callbacks that are waiting for this object (and possibly other objects) to load
            for (var i=0; i < whenLoadedList.length; ++i) {
                var callbackInfo = whenLoadedList[i];
                for (var j=0; j < callbackInfo.list.length; ++j) {
                    if (callbackInfo.list[j] == objectName) {
                        callbackInfo.list.splice(j, 1);	// remove this object from the list of objects awaiting load
                        break;
                    }
                }
                if (callbackInfo.list.length == 0) {
                    // great ... all the objects we were waiting for have arrived.  Execute the callback.
                    callbackInfo.callback();

                    // housekeeping -- remove this callback info, careful not to mess up the array iteration
                    whenLoadedList.splice(i, 1);
                    --i;
                }
            }

            delete top.HEURIST.whenLoadedLists[objectName];
            top.HEURIST.deregisterEvent(top, eventName, top.HEURIST.whenLoadedHandler);
        },

        browser: {
            isEarlyWebkit: function() {
                var webkitTest = navigator.userAgent.match(/webkit\/(\S+)/i);
                return (webkitTest  &&  parseInt(webkitTest[1]) < 400);
            }()
        }
        
    };
/**
* Legacy code to setup fake console for debug output
* @deprecated
*/
    if (! window.console) {
        if (false) {
            window.console = {
                stringify: function(val) {
                    if (typeof val == "object") {
                        if (val.length) {
                            var valStr = "";
                            for (var i=0; i < val.length; ++i) {
                                if (valStr) valStr += ", ";
                                valStr += window.console.stringify(val[i]);
                            }
                            return "[ " + valStr + " ]";
                        }
                        else {
                            var valStr = "";
                            for (var propName in val) {
                                if (valStr) valStr += ", ";
                                valStr += propName + ": " + window.console.stringify(val[propName]);
                            }
                            return "{ " + valStr + " }";
                        }
                    }
                    else if (typeof val == "string") return ('"' + val + '"');
                    else if (typeof val == "function") return "<function>";
                    else return (val + "");
                },

                log: function(val) {
                    setTimeout(function() {
                            var new_div = document.createElement('div')
                            new_div.appendChild(document.createTextNode(window.console.stringify(val)));
                            document.getElementById('fake-console').appendChild(new_div);
                        }, 1000);
                }
            };
            var cons = document.createElement('div');
            cons.id = "fake-console";
            document.body.appendChild(cons);
        } else {
            window.console = {};
            window.console.log = function() { };
        }
    }

    window.HEURIST_USE_ASYNC_HANDLERS = false;

    if (window.addEventListener) {
        window.addEventListener("load", top.HEURIST.invokeOnloadSubscribers, false);
    } else if (window.attachEvent) {
        window.attachEvent("onload", top.HEURIST.invokeOnloadSubscribers);
    }

} else { // top.HEURIST is defined so setup window's HEURIST
    if (! window.HEURIST) window.HEURIST = {};
    if (! window.console) window.console = top.console;

    window.HEURIST.loadScript = function(win) {
        var _loadScript = top.HEURIST.loadScript;
        return function(scriptURL, blocking) { _loadScript.call(win, scriptURL, blocking); };
    }(window);
}

/* if the "content loaded" event is not invoked earlier, it should fire before any other load functions */
top.HEURIST.registerEvent(window, "load", top.HEURIST.contentLoaded);

if (! window.HEURIST_WINDOW_ID) {
    // create a unique window ID for this page
    if (window.parent  &&  window.parent.frameElement  &&  window.parent.frameElement.HEURIST_WINDOW_ID)
        window.HEURIST_WINDOW_ID = window.parent.frameElement.HEURIST_WINDOW_ID;
    else
        window.HEURIST_WINDOW_ID = top.HEURIST.createHeuristWindowID(window.document.location.pathname);
}

top.HEURIST.parseParams.apply(window);

if (! top.HEURIST.util) top.HEURIST.loadScript(top.HEURIST.basePath+"common/js/utilsUI.js", true);
//if (! top.HEURIST.json) top.HEURIST.loadScript(top.HEURIST.basePath+"common/js/loadGroupInfo.js", true);//saw  moved to util

if (window != top) {
    /* Invoke  autosizeAllElements()  when the window loads or is resized */
    top.HEURIST.registerEvent(window, "load", top.HEURIST.util.autosizeAllElements, false, 99);
    top.HEURIST.registerEvent(window, "resize", top.HEURIST.util.autosizeAllElements);
}

if (! document.parentWindow) document._parentWindow = window;	// for Safari
if (! document.parentWindow) document.parentWindow = window;	// for Safari
