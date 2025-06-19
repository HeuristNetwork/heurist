/**
 * @file utils.js
 * @brief Collection of various JavaScript utility functions for the Heurist client.
 * @fileOverview This file provides a set of utility functions primarily grouped under the
 * `window.hWin.HEURIST4.util` namespace. These utilities cover a range of functionalities
 * including type checking (isnull, isempty, istrue, isFunction, isNumber, isPositiveInt, isJSON,
 * isArrayNotEmpty, isArray, isGeoJSON, isBase64), string manipulation (byteLength, trim_IanGt,
 * htmlEscape, stripTags, stripFirstElement, stripScripts, capitalize, lpad), DOM/UI helpers
 * (setDisabled, getScrollBarWidth, getCSS, cssToJson), data handling (cloneJSON, getUrlParameter,
 * getUrlParams, getParamsFromString, random, findArrayIndex, sameArrays, formatFileSize,
 * base64ToBytes, bytesToBase64), communication helpers (interpretServerError, sendRequest,
 * windowOpenInPost, downloadURL, downloadInnerHtml, downloadData), media utilities
 * (getMediaServerFromURL, getFileExtension, restoreRelativeURL), version comparison
 * (versionCompare), array utilities (uniqueArray), date/time utilities (parseDates,
 * getTimeForLocalTimeZone), and clipboard operations (copyStringToClipboard).
 * It also extends String.prototype with `htmlEscape` and `capitalize`, `lpad`, and adds jQuery
 * extensions `$.getStyles`, `selectorExists`, `$.getMultiScripts2`, `$.getMultiScripts`.
 * A function `tinymceURLConverter` for TinyMCE is also included.
 * Some functions are noted with @todo for future refactoring.
 * @see editing_input.js
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 * @todo - split to generic utilities and UI utilities
 * @todo - split utilities for hapi and load them dynamically from hapi
 */

/* global ActiveXObject,Temporal,TDate */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.util) 
{
    
window.hWin.HEURIST4.cssFilesAdded = []; // TODO: Document this global array if it's publicly used or related to util functionality. For now, focusing on util object.
    
/**
 * @namespace HEURIST4.util
 * @memberof HEURIST4
 * @description A collection of general-purpose utility functions for the Heurist client.
 * This namespace includes functions for type checking, string manipulation, DOM/UI assistance,
 * data handling, AJAX requests, and various other common tasks.
 * An alias `Hul` is also available for this namespace (window.Hul).
 */
window.hWin.HEURIST4.util = {

    /**
     * Checks if an object is null or undefined.
     * @param {*} obj - The object or value to check.
     * @returns {boolean} True if the object is undefined or null, false otherwise.
     */
    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    /**
     * Checks if an object is null, undefined, an empty string, or an empty array.
     * Also checks for the string "null".
     * @param {*} obj - The object or value to check.
     * @returns {boolean} True if the object is considered empty, false otherwise.
     */
    isempty: function(obj){
        if (window.hWin.HEURIST4.util.isnull(obj)){
            return true;
        }else if(Array.isArray(obj)){
            return obj.length<1;
        }else{
            return (obj==="") || (obj==="null");
        }
    },

    /**
     * Checks if a value is considered true. Handles boolean true, and strings 'yes', 'y', 'true', 't', '1'.
     * @param {*} val - The value to check.
     * @param {boolean} [def=true] - The default value to return if `val` is null or undefined.
     * @returns {boolean} True if the value is considered true, false otherwise.
     */
    istrue: function(val, def){
        def = window.hWin.HEURIST4.util.isnull(def)?true:def;
        if(window.hWin.HEURIST4.util.isnull(val)){
            return def;
        }else if(val===true){
            return true;
        }else if(typeof val==='string'){ // Corrected: typeof obj to typeof val
            val =  val.toLowerCase();
            return val=='yes' || val=='y'  || val=='true' || val=='t' || val=='1';
        }else{
            return val==1;
        }
    },
    
    /**
     * Checks if a string is a valid CSS color.
     * @param {string} strColor - The color string to check.
     * @returns {boolean} True if it's a valid color, false otherwise.
     */
    isColor: function (strColor){
        if (window.hWin.HEURIST4.util.isempty(strColor)) {
            return false
        }
        let s = new Option().style;
        s.color = strColor;
        return s.color == strColor.toLowerCase(); // Browsers often normalize color values
    },   

    /**
     * Calculates the byte length of a UTF-8 string.
     * @param {string} str - The input string.
     * @returns {number} The byte length of the string.
     */
    byteLength: function(str) {
      let s = str.length;
      let i=str.length-1;
      while (i>=0)
      {
        let code = str.charCodeAt(i);
        if (code > 0x7f && code <= 0x7ff) s++;
        else if (code > 0x7ff && code <= 0xffff) s+=2;
        if (code >= 0xDC00 && code <= 0xDFFF) i--;
        i--;
      }
      return s;
    },    
    
    /**
     * Trims whitespace and removes a trailing ">" character, potentially part of an old format for pointer fields.
     * @param {string} name - The string to process.
     * @returns {string} The processed string.
     */
    trim_IanGt: function(name){
            if(name){
                name = name.trim();
                if(name.substr(name.length-1,1)=='>') name = name.substr(0,name.length-1); // Corrected to remove only one char
                return name;
            }else{
                return '';
            }
    },
    
    /**
     * Checks if a variable is a function.
     * @param {*} f - The variable to check.
     * @returns {boolean} True if `f` is a function, false otherwise.
     */
    isFunction: function(f){
        return typeof f === 'function';
    },
    
    /**
     * Checks if a value is a number (and finite).
     * @param {*} n - The value to check.
     * @returns {boolean} True if `n` is a finite number, false otherwise.
     */
    isNumber: function (n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    },
    
    /**
     * Checks if a value is a positive integer.
     * @param {*} n - The value to check.
     * @returns {boolean} True if `n` is a positive integer, false otherwise.
     */
    isPositiveInt: function (n) {
        if(window.hWin.HEURIST4.util.isempty(n) || !(typeof n === 'string' || typeof n === 'number')){
            return false;
        }
        if(typeof n === 'string'){
            n = parseInt(n, 10); // Added radix 10
        }
        return !isNaN(n) && Number.isInteger(n) && n>0; // Used Number.isInteger for clarity
    },

    /**
     * Checks if the value of a jQuery input element matches a regular expression and adds/removes 'ui-state-error' class.
     * @param {jQuery} o - jQuery object representing the input element.
     * @param {RegExp} regexp - The regular expression to test against.
     * @returns {boolean} True if the value matches the regexp, false otherwise.
     */
    checkRegexp:function ( o, regexp ) {
        if ( !( regexp.test( o.val() ) ) ) {
            o.addClass( "ui-state-error" );
            return false;
        } else {
            o.removeClass("ui-state-error"); // remove error class on success
            return true;
        }
    },
    
    /**
     * Validates an email address format for a jQuery input element.
     * Uses a regular expression and applies 'ui-state-error' class on failure.
     * @param {jQuery} email_input - jQuery object representing the email input element.
     * @returns {boolean} True if the email format is valid, false otherwise.
     */
    checkEmail:function ( email_input ) {
        const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\\.,;:\s@\"]+\.)+[^<>()[\]\\.,;:\s@\"]{2,})$/i;
        return window.hWin.HEURIST4.util.checkRegexp( email_input, re);
    },    

    /**
     * Creates a deep clone of an object or array using JSON stringify and parse.
     * @param {*} data - The data to clone.
     * @returns {*|Array} The cloned data, or an empty array if cloning fails.
     */
    cloneJSON:function (data){
        try{
            return JSON.parse(JSON.stringify(data));
        }catch (ex2){
            return [];
        }
    },

    /**
     * Converts a value in em units to pixels, based on the body's font size.
     * @param {number} input - The value in ems.
     * @returns {number} The equivalent value in pixels.
     */
    em: function(input) {
        let emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },

    /**
     * Gets the font size (in pixels) of a given element or the body.
     * @param {jQuery|HTMLElement} [ele=jQuery('body')] - The element to get the font size from.
     * @returns {number} The font size in pixels.
     */
    em2px: function(ele) {
        if(!ele) { ele = $("body"); }
        const fs = $(ele).css('font-size'); // Ensure ele is jQuery object
        return parseFloat(fs);
    },
    
    /**
     * Estimates the width of a string in pixels based on the font size of an element.
     * @param {string} input - The string to measure.
     * @param {jQuery|HTMLElement} [ele] - The element to use for font size reference. Defaults to body if not provided.
     * @returns {number} The estimated width in pixels.
     */
    px: function(input, ele) {
        let emSize = window.hWin.HEURIST4.util.em2px(ele);
        return (input.length * emSize * 0.6); // Added an average character width factor (approx 0.6 of font-size)
    },

    /**
     * Enables or disables one or more form elements, including custom hSelect elements.
     * @param {jQuery|HTMLElement|Array<jQuery|HTMLElement>} element - The element(s) to enable/disable.
     * @param {boolean} is_disabled - True to disable, false to enable.
     * @returns {void}
     */
    setDisabled: function(element, is_disabled){
        if(!element){ return; }
        if(!Array.isArray(element) && !(element instanceof jQuery)){ // Check if not jQuery object
            element = [element];
        } else if (element instanceof jQuery && element.length > 1) { // If jQuery object with multiple elements
             // Iterate over jQuery collection
        } else if (element instanceof jQuery) { // Single jQuery element
            element = [element.get(0)]; // Get underlying DOM element
        }

        $.each(element, function(idx, ele_item){ // Renamed ele to ele_item
            let current_ele = $(ele_item); // Ensure it's a jQuery object for hSelect check
            if(window.hWin.HEURIST4.util.isFunction(current_ele.hSelect) && current_ele.hSelect('instance')!=undefined){
                current_ele.hSelect(is_disabled ? 'disable' : 'enable');
            }else if (ele_item.nodeType){ // Check if it's a DOM element
                if (is_disabled) {
                    ele_item.setAttribute('disabled', 'disabled');
                    ele_item.classList.add('ui-state-disabled');
                } else {
                    ele_item.removeAttribute('disabled');
                    ele_item.classList.remove('ui-state-disabled', 'ui-button-disabled');
                }                    
            }
        });
    },
    
    /**
     * Checks if the current browser is Internet Explorer.
     * @returns {number|false} The IE version number if IE, otherwise false.
     */
    isIE: function () {
        let myNav = navigator.userAgent.toLowerCase();
        return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1], 10) : false; // Added radix
    },
    
    /**
     * Attempts to check if a protocol (e.g., mailto) is supported by the browser.
     * Shows an error message if determined not supported (behavior varies by browser).
     * @param {string} url - The URL with the protocol to check (e.g., "mailto:test@example.com").
     * @returns {void}
     */
    checkProtocolSupport: function(url){
        if (window.hWin.HEURIST4.util.isIE()) {
            if (typeof (navigator.msLaunchUri) == typeof (Function)) {
                navigator.msLaunchUri(url, function () {}, function () { window.hWin.HEURIST4.msg.showMsgErr('Not supported'); });
                return;
            }
            try { new ActiveXObject("Plugin.mailto"); } catch (e) { /*not installed*/ }
        } else {
           let mime = navigator.mimeTypes && navigator.mimeTypes['application/x-mailto']; // Check navigator.mimeTypes exists
           if(!mime) { window.hWin.HEURIST4.msg.showMsgErr('Not supported'); }
        }      
    },

    /**
     * Encodes specified parameters within a request object.
     * Supports URL encoding or JSON stringification based on `need_encode` value.
     * @param {Object} request - The request object to modify.
     * @param {Array<string>} params - An array of parameter names in `request` to encode.
     * @param {1|2|3} [need_encode] - Encoding type: 1 or 2 for `encodeURIComponent`, 3 for `JSON.stringify`.
     *                              Defaults to `window.hWin.HAPI4.sysinfo['need_encode']`.
     * @returns {void}
     */
    encodeRequest: function(request, params, need_encode){
        if(!(need_encode>0) && window.hWin.HAPI4 && window.hWin.HAPI4.sysinfo){ // Check HAPI4.sysinfo exists
            need_encode = window.hWin.HAPI4.sysinfo['need_encode'];
        }
        if(need_encode>0){
            let f_encode = null;
            if(need_encode==2 || need_encode==1){ f_encode = encodeURIComponent; }
            else if(need_encode==3){ f_encode = JSON.stringify; }
            if(f_encode != null){
                for(let i=0; i<params.length; i++){
                    if(request[params[i]]){ request[params[i]] = f_encode(request[params[i]]); }
                }
            }
            request.details_encoded = need_encode;
        }
    },
    
    /**
     * NOT USED. Replaces "../" with "^^/" and " style=" with " xxx_style=".
     * @param {string|Object|Array} val - The value to encode. If object/array, it's stringified.
     * @returns {string} The encoded string.
     */
    encodeSuspectedSequences: function (val) {
        if(typeof val !== 'string' && (Array.isArray(val) || $.isPlainObject(val))) {
            val = JSON.stringify(val);
        }
        return encodeURIComponent(String(val).replace(/(\.\.\/)/g, '^^/').replace(/( style=)/g,' xxx_style=')); // Ensure val is string
    },
    
    /**
     * Checks if a value is a valid JSON string and parses it, or if it's already an object/array.
     * @param {*} value - The value to check.
     * @returns {Object|Array|false} The parsed JSON object/array, or the original if already object/array.
     *                               Returns false if not valid JSON or not an object/array.
     */
    isJSON: function(value){
        let res = false;
        try {
            if(typeof value === 'string'){
                value = value.replace(/[\n\r]+/g, ''); value = JSON.parse(value);
            }
            if(Array.isArray(value) || $.isPlainObject(value)){ res = value; }
        } catch (err) { res = false; }
        return res;
    },
    
    /**
     * Extracts a specific parameter's value from a URL query string.
     * @param {string} name - The name of the parameter to extract.
     * @param {string} [query=window.location.search] - The query string (e.g., "?foo=bar&baz=qux") or full URL.
     *                                                  Defaults to the current window's search string.
     * @returns {string|null} The decoded parameter value, or null if not found.
     */
    getUrlParameter: function(name, query){
        if(!query){ query = window.location.search; }
        else if(query.startsWith('http')){ let parts = query.split('?'); parts.shift(); query = parts.join('?');}
        const urlParams = new URLSearchParams(query);
        return urlParams.get(name); // Already decoded
    },

    /**
     * Gets the URL parameters from a full URL string.
     * @param {string} url - The URL.
     * @returns {Object} The URL parameters as a key-value object.
     */
    getUrlParams: function (url) {
        let parser = document.createElement('a'); parser.href = url;
        let query = parser.search.substring(1);
        return window.hWin.HEURIST4.util.getParamsFromString(query, '&', true);
    },

    /**
     * Parses a query string into an object of key-value pairs.
     * @param {string} queryString - The query string (without the leading '?').
     * @param {string} [sep='&'] - The separator for key-value pairs.
     * @param {boolean} [decode=true] - Whether to decode URI components.
     * @returns {Object} An object containing the parsed parameters.
     */
    getParamsFromString: function (queryString, sep='&', decode=true) { // Renamed url to queryString
        let params = {};
        let vars = queryString.split(sep);
        for (let i = 0; i < vars.length; i++) {
            let pair = vars[i].split('=');
            if (pair.length === 2) { // Ensure there's a key and a value
                params[pair[0]] = decode ? decodeURIComponent(pair[1]) : pair[1];
            } else if (pair.length === 1 && pair[0] !== "") { // Handle keys without values
                 params[pair[0]] = "";
            }
        }
        return params;
    },

    /**
     * Checks if a value is an array and is not empty.
     * @param {*} a - The value to check.
     * @returns {boolean} True if `a` is a non-empty array, false otherwise.
     */
    isArrayNotEmpty: function (a){
        return (Array.isArray(a) && a.length>0);
    },

    /**
     * Checks if a value is an array. (Note: `Array.isArray()` is the standard modern equivalent).
     * @param {*} a - The value to check.
     * @returns {boolean} True if `a` is an array, false otherwise.
     */
    isArray: function (a) { return Array.isArray(a); },
    
    /**
     * Checks if a value is a GeoJSON object (Feature, FeatureCollection, or GeometryCollection) or an array of such.
     * @param {*} a - The value to check.
     * @param {boolean} [allowempty=false] - If true, an empty array is also considered valid GeoJSON.
     * @returns {boolean} True if it conforms to expected GeoJSON structure, false otherwise.
     */
    isGeoJSON: function(a, allowempty){
        if(allowempty && Array.isArray(a) && a.length==0){ return true; }
        else if($.isPlainObject(a)){ return (a['type']=='Feature' || a['type']=='FeatureCollection' || a['type']=='GeometryCollection'); }
        else{ return (window.hWin.HEURIST4.util.isArrayNotEmpty(a) && (a[0]['type']=='Feature' || a[0]['type']=='FeatureCollection'));}
    },
    
    /**
     * Escapes HTML special characters in a string.
     * @param {string} text - The string to escape.
     * @returns {string} The HTML-escaped string.
     */
    htmlEscape: function (text) {
      let map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
      if(window.hWin.HEURIST4.util.isempty(text)){ return ''; }
      else{ return (''+text).replace(/[&<>"']/g, function(m) { return map[m]; }); }
    },  
    
    /**
     * Strips HTML tags from a string. A whitelist of tags to keep can be provided.
     * If whitelist is false, performs HTML escaping instead.
     * @param {string} text - The HTML string to process.
     * @param {string|false} [whitelist] - Comma-separated string of tags to keep (e.g., "p,img").
     *                                     If false, escapes HTML. If undefined/empty, strips all tags.
     * @returns {string} The processed string.
     */
    stripTags: function(text, whitelist){
        if(whitelist===false){ return window.hWin.HEURIST4.util.htmlEscape(text); }
        else{
            let link = document.createElement("span"); link.style.display = "none"; link.innerHTML = text;
            document.body.appendChild(link);
            let eles = $(link).find('*');
            if(!window.hWin.HEURIST4.util.isempty(whitelist)){ eles = eles.not(whitelist); }
            eles.each(function() { let content = $(this).contents(); $(this).replaceWith(content); });
            text = window.hWin.HEURIST4.util.isempty(whitelist) ? $(link).text().trim() : $(link).html();
            document.body.removeChild(link); link = null;
            return text;
        }
    },
    
    /**
     * Removes the first HTML element found in a string, returning the remaining inner HTML of the parent.
     * @param {string} text - The HTML string.
     * @returns {string} The HTML string with the first element removed.
     */
    stripFirstElement: function(text){
        let link = document.createElement("span"); link.style.display = "none"; link.innerHTML = text;
        document.body.appendChild(link);
        $(link).find('*').first().remove(); // Use jQuery's first()
        text = $(link).html();
        document.body.removeChild(link); link = null;
        return text;
    },
    
    /**
     * Removes `<script>` tags from an HTML string.
     * @param {string} s - The HTML string.
     * @returns {string} The HTML string with script tags removed.
     */
    stripScripts: function(s) {
        return $('<div>').append($.parseHTML(s)).html();
    },

    /**
     * Checks if a value is a plain object.
     * (Note: Modern checks might be more robust, e.g. considering `null` or arrays).
     * @param {*} a - The value to check.
     * @returns {boolean} True if `a` is a plain object, false otherwise.
     */
    isObject: function (a) { return Object.prototype.toString.apply(a) === '[object Object]'; },

    /**
     * Stops event propagation and prevents default action for a browser event.
     * @param {Event} e - The event object.
     * @returns {Event} The modified event object.
     */
    stopEvent: function(e){
        if (!e) e = window.event;
        if (e) {
            e.cancelBubble = true; if (e.stopPropagation) e.stopPropagation();
            e.returnValue = false; if (e.preventDefault) e.preventDefault(); // Check preventDefault exists
        }
        return e;
    },

    /**
     * Interprets a jQuery AJAX error (jqXHR) and returns a standardized error response object.
     * @param {jqXHR} jqXHR - The jQuery XHR object from the failed AJAX call.
     * @param {string} url - The URL of the failed request.
     * @param {Object} request_code - An object containing script and action codes for context.
     * @returns {Object} A standardized error response object with status, message, and request_code.
     */
    interpretServerError: function(jqXHR, url, request_code){
        let err_message = '';
        if(window.hWin.HEURIST4.util.isempty(jqXHR.responseText)){
            if(jqXHR.status==500){ err_message = 'Error_Server_Side'; }
            else{ err_message = 'Error_Connection_Reset'; }
            console.error(err_message, url);
        }else{ err_message = jqXHR.responseText; }
        return { status: window.hWin.ResponseStatus.UNKNOWN_ERROR, message: err_message, request_code: request_code };
    },
       
    /**
     * Sends an AJAX POST request. Used for calls where HAPI might not be initialized or for third-party services.
     * @param {string} url - The URL to send the request to.
     * @param {Object} [request_data] - Data to send with the request.
     * @param {*} [caller] - Context object to be passed to the callback.
     * @param {function} [callback] - Callback function to handle the response. Receives `(caller, response)` or `(response)`.
     * @param {string} [dataType="json"] - The expected data type of the response. 'auto' to let jQuery decide.
     * @param {number} [timeout] - Request timeout in milliseconds.
     * @returns {void}
     */
    sendRequest: function(url, request_data, caller, callback, dataType, timeout){ // Renamed request
        let action = '';
        if(request_data){
            if(!request_data.db && window.hWin && window.hWin.HAPI4){ request_data.db = window.hWin.HAPI4.database; }
            action = url.substring(url.lastIndexOf('/')+1);
            if(action.indexOf('.php')>0) { action = action.substring(0,action.indexOf('.php')); }
        }
        let request_code_ajax = {script:action, action: (request_data ? request_data.a : '')}; // Renamed, added check for request_data
        let options = {
            url: url, type: "POST", data: request_data, cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
                if(callback){ let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code_ajax); if(caller){ callback(caller, response); }else{ callback(response); }}
            },
            success: function( response, textStatus, jqXHR ){
                if(callback){ if(caller){ if($.isPlainObject(response)){ response.request_code = request_code_ajax; } callback(caller, response); }else{ callback(response); }}
            },
            fail: function( jqXHR, textStatus, errorThrown ) {
                if(callback){ let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code_ajax); if(caller){ callback(caller, response); }else{ callback(response); }}
            }
        };
        if(window.hWin.HEURIST4.util.isnull(dataType)){ options['dataType'] = 'json'; }
        else if(dataType!='auto'){ options['dataType'] = dataType; }
        if(timeout>0){ options['timeout'] = timeout; }
        $.ajax(options);
    },
    
    /**
     * Opens a new window and submits data to it via a POST request using a temporary form.
     * @param {string} actionUrl - The URL to submit the POST request to.
     * @param {string} windowName - The name for the new window (will be made unique with a timestamp).
     * @param {string} windowFeatures - Window features string (e.g., "width=800,height=600").
     * @param {Object} params - Key-value pairs of data to be sent in the POST request.
     * @returns {void}
     */
    windowOpenInPost: function(actionUrl, windowName, windowFeatures, params) {
        let mapForm = document.createElement("form"); let milliseconds = new Date().getTime();
        windowName = windowName+milliseconds; mapForm.target = windowName; mapForm.method = "POST"; mapForm.action = actionUrl;
        for (const key in params){ if (Object.hasOwnProperty.call(params, key)) { // Added hasOwnProperty check
            let mapInput = document.createElement("input"); mapInput.type = "hidden"; mapInput.name = key; mapInput.value = params[key]; mapForm.appendChild(mapInput);
        }}
        document.body.appendChild(mapForm);
        let map = window.open('', windowName, windowFeatures);
        if (map) { mapForm.submit(); } else { alert('You must allow popups for this map to work.'); }
        document.body.removeChild(mapForm); // Clean up form
    },    
    
    /**
     * Calculates the width of the browser's scrollbar.
     * @returns {number} The scrollbar width in pixels.
     */
    getScrollBarWidth: function() {
        let $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body');
        let widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    },

    /**
     * Parses start and end date strings using the Temporal library.
     * Converts dates to a format suitable for VIS.js timeline (ISO string with timezone).
     * @param {string} [start] - The start date string.
     * @param {string} [end] - The end date string. If `start` is null and `end` is provided, `end` is treated as `start`.
     * @returns {Array<string>|null} An array `[startDate, endDate]` in ISO format, or `null` if parsing fails or input is invalid.
     */
    parseDates: function(start, end){
        if(window['Temporal'] && (start || end)){   
            if(start==null && end!=null){ start = end; end = null; }
            function __forVis(dt){
                if(dt){ if(!dt.getMonth()){ dt.setMonth(1); } if(!dt.getDay()){ dt.setDay(1); } return dt.toString('yyyy-MM-ddTHH:mm:ssz'); }
                else{ return ''; }
            }    
            try{
                let temporal_start, temporal_end; // Renamed
                if(start!='' && typeof start === 'string'){
                    if(start.search(/VER=/)!==-1){
                        temporal_start = new Temporal(start);
                        if(temporal_start){
                            let dt_start = temporal_start.getTDate('TPQ') || temporal_start.getTDate('PDB') || temporal_start.getTDate('DAT'); // More robust check
                            if(dt_start){
                                let dt2_start = temporal_start.getTDate('TAQ') || temporal_start.getTDate('PDE');  // More robust check
                                if (dt2_start) end = __forVis(dt2_start); // end might be defined by start string if it's a range
                                start = __forVis(dt_start);
                            } else { return null; }
                        }
                    }else{ start = __forVis(new TDate(start)); }
                }
                if(end!='' && typeof end === 'string') {
                    if(end.search(/VER=/)!==-1){
                        temporal_end = new Temporal(end); // Renamed
                        if(temporal_end){
                            let dt_end = temporal_end.getTDate('TAQ') || temporal_end.getTDate('PDE') || temporal_end.getTDate('DAT'); // More robust check
                            end = __forVis(dt_end);
                        }
                    }else{ end = __forVis(new TDate(end)); }
                }
            }catch(e){ return null; }
            return [start, end];
        }
        return null;
    },    

    /**
     * Gets the computed value of a CSS property for a given class name by temporarily applying it to a hidden element.
     * @param {string} prop - The CSS property name (e.g., "background-color").
     * @param {string} fromClass - The class name to apply.
     * @returns {string} The value of the CSS property.
     */
    getCSS: function (prop, fromClass) {
        let $inspector = $("<div>").css('display', 'none').addClass(fromClass);
        $("body").append($inspector);
        try { return $inspector.css(prop); }
        finally { $inspector.remove(); }
    },

    /**
     * Converts a CSS style string (e.g., "color: red; font-size: 12px;") into a JSON object.
     * Converts boolean-like string values "true"/"false" to actual booleans.
     * @param {string} css - The CSS string.
     * @returns {Object} A JSON object representing the CSS styles.
     */
    cssToJson: function(css){
        let json = {};
        if(css && typeof css === 'string'){ // Added typeof check
            let styles = css.split(';'), i= styles.length, k, v;
            while (i--) {
                if (styles[i].trim() === "") continue; // Skip empty parts
                let pos = styles[i].indexOf(':');
                if(pos>0){ // Ensure colon is present and not at the beginning
                    k = String(styles[i].substr(0,pos)).trim();
                    v = String(styles[i].substr(pos+1)).trim();
                    if (k && v && k.length > 0 && v.length > 0) {
                        if(v==='true')v=true; else if(v==='false')v=false;
                        json[k] = v;
                    }
                }
            }
        }
        return json;        
    },

    /**
     * Generates a 32-bit integer hash from a string.
     * @param {string} str - The input string.
     * @returns {number} The calculated hash value.
     */
    hashString: function(str) {
        let hash = 0, i, c; let strlen = str?str.length:0;
        if (strlen == 0) return hash;
        for (i = 0; i < strlen; i++) { c = str.charCodeAt(i); hash = ((hash<<5)-hash)+c; hash = hash & hash; }
        return hash;    
    },

    /**
     * Formats a file size in bytes into a human-readable string (KB, MB, GB).
     * @param {number} bytes - The file size in bytes.
     * @returns {string} The formatted file size string, or an empty string if input is not a number.
     */
    formatFileSize: function (bytes) {
        if (typeof bytes !== 'number' || isNaN(bytes)) { return ''; } // Added NaN check
        if (bytes >= 1000000000) { return (bytes / 1000000000).toFixed(2) + ' GB'; }
        if (bytes >= 1000000) { return (bytes / 1000000).toFixed(2) + ' MB'; }
        return (bytes / 1000).toFixed(2) + ' KB';
    },

    /**
     * Initiates a file download by setting an iframe's source to the given URL.
     * @param {string} url - The URL of the file to download.
     * @param {function} [callback] - Optional callback to execute when the iframe loads (may not reliably indicate download completion).
     * @returns {void}
     */
    downloadURL: function(url, callback) {
        let $idown = $('#idown');
        if ($idown.length==0) { $idown = $('<iframe>', { id:'idown' }).hide().appendTo('body'); }
        if (window.hWin.HEURIST4.util.isFunction(callback)) { $idown.on('load', callback); }
        $idown.attr('src',url);
    },

    /**
     * Initiates a download of the inner HTML content of a given element as a file.
     * @param {string} filename - The desired name for the downloaded file.
     * @param {jQuery|HTMLElement|string} ele - The element or selector for the element whose content is to be downloaded.
     * @param {string} [mimeType="text/plain"] - The MIME type for the download.
     * @returns {void}
     */
    downloadInnerHtml: function (filename, ele, mimeType) {
        let elHtml = $(ele).html();
        window.hWin.HEURIST4.util.downloadData(filename, elHtml, mimeType);
    }, 

    /**
     * Initiates a download of provided data as a file.
     * @param {string} filename - The desired name for the downloaded file.
     * @param {string} data - The data content to download.
     * @param {string} [mimeType="text/plain"] - The MIME type for the download.
     * @returns {void}
     */
    downloadData: function (filename, data, mimeType) {
        mimeType = mimeType || 'text/plain';
        let content = 'data:' + mimeType  +  ';charset=utf-8,' + encodeURIComponent(data);
        let link = document.createElement("a");
        link.setAttribute('download', filename); link.setAttribute('href', content);
        if (window.webkitURL != null) { link.click(); link = null; }
        else { link.onclick = function(){ document.body.removeChild(link); link=null;}; link.style.display = "none"; document.body.appendChild(link); link.click(); }
    },    

    /**
     * Checks if an object is an instance of HRecordSet.
     * @param {*} recordset - The object to check.
     * @returns {boolean} True if it's an HRecordSet instance, false otherwise.
     */
    isRecordSet: function(recordset){
        return !window.hWin.HEURIST4.util.isnull(recordset) && window.hWin.HEURIST4.util.isFunction(recordset.isA) && recordset.isA("HRecordSet");   
    },

    /**
     * Generates a random string of numbers, using `window.crypto` if available, otherwise falls back to Math.random.
     * @returns {string} A random string of numbers.
     */
    random: function(){
        if(window.crypto && window.crypto.getRandomValues){ // Check crypto.getRandomValues
            const typedArray = new Uint8Array(10); window.crypto.getRandomValues(typedArray);
            return typedArray.join('').substr(0,15);
        }else{ return ''+Math.floor(Date.now() * Math.random()); }
    },

    /**
     * Searches for an object by its name within all iframes of the current document.
     * @param {string} name - The name of the object to find in `contentWindow` of iframes.
     * @returns {*|null} The found object, or null if not found in any frame.
     */
    findObjInFrame: function(name){
        let i, frames; frames = document.getElementsByTagName("iframe");
        for (i = 0; i < frames.length; ++i) {
            try { // Add try-catch for cross-origin frames
                if(frames[i].contentWindow && !window.hWin.HEURIST4.util.isnull(frames[i]['contentWindow'][name])){ return frames[i]['contentWindow'][name]; }
            } catch(e) { /* Cross-origin access denied */ }
        }
        return null;
    },

    /**
     * Detects the media server (youtube, vimeo, soundcloud) from a given URL.
     * @param {string} filename - The URL to check.
     * @returns {string|null} The name of the media server if detected, otherwise null.
     */
    getMediaServerFromURL:function(filename){
        if (typeof filename !== 'string') return null; // Added type check
        filename = filename.toLowerCase();
        if(filename.indexOf('youtu.be')>=0 || filename.indexOf('youtube.com')>=0){ return 'youtube'; }
        else if(filename.indexOf('vimeo.com')>=0){ return 'vimeo'; }
        else if(filename.indexOf('soundcloud.com')>=0){ return 'soundcloud'; }
        else{ return null; }
    },

    /**
     * Extracts the file extension from a filename or URL.
     * @param {string} filename - The filename or URL.
     * @returns {string} The file extension (without the dot), or an empty string if not found or input is invalid.
     */
    getFileExtension:function(filename){
        if(filename && typeof filename === 'string'){ // Added typeof check
            let res = filename.match(/\.([^\./\?]+)(?:$|\?)/); // Non-capturing group for end or query
            return (res && res.length>1)?res[1]:'';
        }else{ return ''; }
    },

    /**
     * Compares two version strings (e.g., "1.0.0", "1.0.1b").
     * @param {string} v1 - The first version string.
     * @param {string} v2 - The second version string.
     * @param {Object} [options] - Options for comparison.
     * @param {boolean} [options.lexicographical=false] - If true, compares alphabetically after splitting.
     * @param {boolean} [options.zeroExtend=false] - If true, pads shorter version parts with zeros.
     * @returns {number|NaN}
     *          - -1 if v1 < v2
     *          -  0 if v1 == v2
     *          -  1 if v1 > v2
     *          - NaN if versions have invalid parts.
     *          (Note: Original code's return of -2 for v1 > v2 was specific to its logic, this is more standard).
     */
    versionCompare: function(v1, v2, options) {
        let lexicographical = options && options.lexicographical, zeroExtend = options && options.zeroExtend;
        let v1parts = String(v1).split('.'), v2parts = String(v2).split('.'); // Ensure strings
        function isValidPart(x) { return (lexicographical ? /^\d+[A-Za-z]*$/ : /^\d+$/).test(x); }
        if (!v1parts.every(isValidPart) || !v2parts.every(isValidPart)) { return NaN; }
        if (zeroExtend) {
            while (v1parts.length < v2parts.length) v1parts.push("0");
            while (v2parts.length < v1parts.length) v2parts.push("0");
        }
        if (!lexicographical) { v1parts = v1parts.map(Number); v2parts = v2parts.map(Number); }
        for (let i = 0; i < v1parts.length; ++i) {
            if (v2parts.length == i) { return 1; } // v1 is longer
            if (v1parts[i] < v2parts[i]) { return -1; }
            if (v1parts[i] > v2parts[i]) { return 1; }
        }
        if (v1parts.length != v2parts.length) { return -1; } // v2 is longer
        return 0;
    },

    /**
     * Returns a new array with unique values from the input array.
     * For objects, uniqueness is based on reference.
     * @param {Array} arr - The input array.
     * @returns {Array} A new array with unique values.
     */
    uniqueArray: function(arr){
        let n = {},r=[];
        for(let i = 0; i < arr.length; i++) {
            if($.isPlainObject(arr[i])){ r.push(arr[i]); } // Objects are pushed as is (reference uniqueness)
            else if (!n[arr[i]]) { n[arr[i]] = true; r.push(arr[i]); }
        }
        return r;            
    },

    /**
     * Finds the index of an element in an array. Uses `==` for comparison.
     * @param {*} elt - The element to find.
     * @param {Array} arr - The array to search in.
     * @param {number} [from=0] - The index to start searching from.
     * @returns {number} The index of the element, or -1 if not found.
     */
    findArrayIndex: function(elt, arr /*, from*/) {
        if( window.hWin.HEURIST4.util.isempty(arr) ) return -1;
        let len = arr.length; let from = Number(arguments[2]) || 0;
        from = (from < 0) ? Math.ceil(from) : Math.floor(from);
        if (from < 0) from += len;
        for (; from < len; from++) { if (from in arr && arr[from] == elt) return from; }
        return -1;
    },

    /**
     * Checks if two arrays are the same (same length and same elements in the same order using `==` comparison).
     * @param {Array} arr1 - The first array.
     * @param {Array} arr2 - The second array.
     * @returns {boolean} True if the arrays are considered the same, false otherwise.
     */
    sameArrays: function(arr1, arr2){
        if(!Array.isArray(arr1) || !Array.isArray(arr2)){ return false; }
        else if(arr1.length != arr2.length){ return false; } // Check length first
        else if(arr1 === arr2){ return true; } // Same reference
        return arr1.every((value, index) => value == arr2[index]);
    },

    /**
     * Converts a UTC date string to a local time string (HH:mm:ss).
     * @param {string} sdate - The UTC date string.
     * @returns {string} The time string in local timezone.
     */
    getTimeForLocalTimeZone: function (sdate){
        let date = new Date(sdate+"+00:00"); // Append UTC indicator for correct parsing
        return String(date.getHours()).padStart(2, "0") + ':' + String(date.getMinutes()).padStart(2, "0") + ':' + String(date.getSeconds()).padStart(2, "0");
    },

    /**
     * Copies a string to the clipboard using the `document.execCommand('copy')` method.
     * @param {string} string_to_copy - The string to copy.
     * @returns {void}
     */
    copyStringToClipboard: function(string_to_copy) {
        function handler (event){ event.clipboardData.setData('text/plain', string_to_copy); event.preventDefault(); document.removeEventListener('copy', handler, true); }
        document.addEventListener('copy', handler, true); document.execCommand('copy');
    },

    /**
     * Extracts the YouTube video ID from various YouTube URL formats.
     * @param {string} url - The YouTube URL.
     * @returns {string|null} The YouTube video ID, or null if not found or URL is invalid.
     */
    get_youtube_id: function(url){
        if (typeof url !== 'string') return null; // Added type check
        let matches = url.match(/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/);
        return matches ? matches[1] : null; // Return null if no match
    },

    /**
     * Performs a merge sort on an array.
     * @param {Array} array - The array to sort.
     * @param {function} [compare] - Optional comparison function `(a, b)` that returns true if `a` should come before `b`.
     *                               Defaults to `a < b`.
     * @returns {Array} The sorted array.
     */
    merge_sort: function(array, compare){
        if(!Array.isArray(array) || array.length < 2){ return array; }
        if(!compare){ compare = (a, b) => { return a < b; }; }
        else if(!window.hWin.HEURIST4.util.isFunction(compare)){ return array; }
        let arr_len = array.length; let mid = Math.floor(arr_len / 2);
        let left_array = window.hWin.HEURIST4.util.merge_sort(array.slice(0, mid), compare);
        let right_array = window.hWin.HEURIST4.util.merge_sort(array.slice(mid), compare);
        let sorted_array = [];
        while(left_array.length != 0 && right_array.length != 0){
            if(compare(left_array[0], right_array[0])){ sorted_array.push(left_array.shift()); }
            else{ sorted_array.push(right_array.shift()); }
        }
        return sorted_array.concat(left_array.concat(right_array));
    },
    
    /**
     * Restores relative image URLs to absolute URLs based on `HAPI4.baseURL_pro`.
     * It inspects `src` and `data-id` attributes of an element (expected to be an image).
     * Also handles some specific URL parameters for embedding and display.
     * @param {HTMLElement} ele - The HTML element (typically `<img>`) whose src needs to be restored.
     * @returns {void}
     */
    restoreRelativeURL: function(ele) {
        let src = ele.getAttribute('src'); let file_id = ele.getAttribute('data-id');
        let db = (window.hWin.HAPI4 && window.hWin.HAPI4.database) ? window.hWin.HAPI4.database : ''; // Check HAPI4 exists
        let extra_params = '';
        if(!window.hWin.HEURIST4.util.isempty(src)){
            let query = src.slice(src.indexOf('?'));
            if(src.indexOf('file=') > 0){ file_id = window.hWin.HEURIST4.util.getUrlParameter('file', query); }
            if(src.indexOf('embedplayer=') > 0){ extra_params += '&embedplayer='+window.hWin.HEURIST4.util.getUrlParameter('embedplayer', query); }
            if(src.indexOf('fancybox=') > 0){ extra_params += '&fancybox='+window.hWin.HEURIST4.util.getUrlParameter('fancybox', query); }
            else{ extra_params += '&fancybox=1'; }
            if(window.hWin.HAPI4 && window.hWin.HAPI4.is_publish_mode){
                let webcached = window.hWin.HEURIST4.util.getUrlParameter('fullres', query);
                webcached = !webcached ? 0 : webcached; extra_params += `&fullres=${webcached}`;
            }
            if(src.indexOf('db=') > 0){ db = window.hWin.HEURIST4.util.getUrlParameter('db', query); }
        }
        if(!window.hWin.HEURIST4.util.isempty(file_id) && !window.hWin.HEURIST4.util.isempty(db) && window.hWin.HAPI4){ // Check HAPI4
            src = window.hWin.HAPI4.baseURL_pro + '?db=' + db + '&file=' + file_id + extra_params;
            ele.setAttribute('src', src);
        }else if (!window.hWin.HEURIST4.util.isempty(src) && (src.indexOf('./')==0 || src.indexOf('/')==0) && window.hWin.HAPI4){ // Check HAPI4
              src = window.hWin.HAPI4.baseURL + src.substring(src.indexOf('/')==0?1:2);
              ele.setAttribute('src', src);
        }        
    },
    
    /**
     * Converts a Base64 encoded string to a Uint8Array of bytes.
     * @param {string} base64 - The Base64 encoded string.
     * @returns {Uint8Array} The array of bytes.
     */
    base64ToBytes: function(base64) {
        const binString = atob(base64);
        return Uint8Array.from(binString, (m) => m.codePointAt(0));
    },

    /**
     * Converts a Uint8Array of bytes to a Base64 encoded string.
     * @param {Uint8Array} bytes - The array of bytes.
     * @returns {string} The Base64 encoded string.
     */
    bytesToBase64: function(bytes) {
        const binString = Array.from(bytes, (x) => String.fromCodePoint(x)).join("");
        return btoa(binString);
    },

    /**
     * Checks if a string is a valid Base64 encoded string.
     * @param {string} str - The string to check.
     * @returns {boolean} True if the string is valid Base64, false otherwise.
     */
    isBase64: function(str) {
          const notBase64 = /[^A-Z0-9+\/=]/i;
          if(typeof str === 'string'){
              const len = str.length;
              if (!len || len % 4 !== 0 || notBase64.test(str)) { return false; }
              const firstPaddingChar = str.indexOf('=');
              return firstPaddingChar === -1 || firstPaddingChar === len - 1 || (firstPaddingChar === len - 2 && str[len - 1] === '=');
          }else{ return false; }
    }
}; //end HEURIST4.util

window.Hul = window.hWin.HEURIST4.util; // Alias for HEURIST4.util

//-------------------------------------------------------------

/**
 * Escapes HTML special characters in a string.
 * @returns {string} The HTML-escaped string.
 * @memberof String.prototype
 */
String.prototype.htmlEscape = function() {
    return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#039;").replace(/"/g, "&#34;");
}

/**
 * Capitalizes the first letter of a string.
 * @returns {string} The string with the first letter capitalized.
 * @memberof String.prototype
 */
String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

/**
 * Left-pads a string with a specified character to a certain length.
 * @param {string} padString - The string to use for padding.
 * @param {number} length - The desired total length of the string.
 * @returns {string} The left-padded string.
 * @memberof String.prototype
 */
String.prototype.lpad = function(padString, length) {
    let str = String(this); // Ensure 'this' is treated as a string
    while (str.length < length)
        str = padString + str;
    return str;
}

if (!Array.prototype.indexOf) {
    /**
     * Polyfill for Array.prototype.indexOf.
     * Finds the first index at which a given element can be found in the array, or -1 if it is not present.
     * @param {*} elt - Element to locate in the array.
     * @param {number} [from=0] - The index to start the search at.
     * @returns {number} The first index of the element in the array; -1 if not found.
     * @memberof Array.prototype
     */
    Array.prototype.indexOf = function(elt /*, from*/) {
        let len = this.length;
        let from = Number(arguments[1]) || 0;
        from = (from < 0) ? Math.ceil(from) : Math.floor(from);
        if (from < 0) from += len;
        for (; from < len; from++) {
            if (from in this && this[from] === elt) return from;
        }
        return -1;
    };
}
} // End of HEURIST4.util initialization block

/**
 * Dynamically loads a CSS file by creating a <link> element in the document's head.
 * @memberof jQuery
 * @function getStyles
 * @param {string} path - The URL of the CSS file to load.
 * @returns {void}
 */
$.getStyles = function(path){
    var head = document.getElementsByTagName('head')[0];
    var style = document.createElement('link');
    style.href = path; style.type = 'text/css'; style.rel = 'stylesheet';
    head.append(style); 
}

/**
 * Checks if a CSS selector exists in any of the document's stylesheets.
 * @param {string} selector - The CSS selector to check (e.g., ".my-class", "#my-id").
 * @returns {boolean} True if the selector exists, false otherwise.
 */
function selectorExists(selector) { 
    function getAllSelectors() { 
        var ret = [];
        for(var i = 0; i < document.styleSheets.length; i++) {
            if(document.styleSheets[i].href!=null) continue; // Skip external stylesheets that might cause CORS issues
            try{
                var rules = document.styleSheets[i].rules || document.styleSheets[i].cssRules;
                for(var x in rules) { if(Object.hasOwnProperty.call(rules, x) && typeof rules[x].selectorText == 'string') ret.push(rules[x].selectorText); } // Added hasOwnProperty
            }catch(e){}
        }
        return ret;
    }
    var selectors = getAllSelectors();
    for(var i = 0; i < selectors.length; i++) { if(selectors[i] == selector) return true; }
    return false;
}

/**
 * Loads multiple JavaScript files sequentially using async/await and Promises.
 * @memberof jQuery
 * @function getMultiScripts2
 * @param {Array<string>} arr - An array of script filenames (without path).
 * @param {string} [path=""] - Optional base path for the script files.
 * @returns {Promise<void>} A Promise that resolves when all scripts have been loaded, or rejects if any script fails.
 */
$.getMultiScripts2 = function(arr, path) {
    return new Promise(function(_resolve, _reject){
        (async () => {
          for (const scr of arr) { await $.getScript((path||"") + scr); }
          _resolve();
        })().catch((err) => { _reject(err); });
    });
}

/**
 * Loads multiple JavaScript files using jQuery's `$.getScript` and `$.when`.
 * Scripts may load in parallel, but the returned Promise resolves after all are done.
 * @memberof jQuery
 * @function getMultiScripts
 * @param {Array<string>} arr - An array of script filenames (without path).
 * @param {string} [path=""] - Optional base path for the script files.
 * @returns {jQuery.Promise} A jQuery Promise that resolves when all scripts have been loaded.
 */
$.getMultiScripts = function(arr, path) {
    let _arr = $.map(arr, function(scr) { return $.getScript( (path||"") + scr ); });
    _arr.push($.Deferred(function( deferred ){ $( deferred.resolve ); }));
    return $.when.apply($, _arr);
}

/**
 * URL converter function for TinyMCE editor instances within Heurist.
 * Converts absolute Heurist URLs (based on `HAPI4.baseURL_pro` or `HAPI4.baseURL`) to relative paths (e.g., "./...").
 * @param {string} url - The URL to convert.
 * @param {HTMLElement} node - The DOM node this URL is associated with (e.g., `<a>` or `<img>`).
 * @param {boolean} on_save - True if the conversion is happening on save, false on load.
 * @param {string} name - The attribute name being converted (e.g., "src", "href").
 * @returns {string} The converted URL.
 */
function tinymceURLConverter(url, node, on_save, name) {
    if (window.hWin.HAPI4) { // Check if HAPI4 is available
        if(url.indexOf(window.hWin.HAPI4.baseURL_pro)===0) { url = url.replace(window.hWin.HAPI4.baseURL_pro, './'); }
        else if(url.indexOf(window.hWin.HAPI4.baseURL)===0) { url = url.replace(window.hWin.HAPI4.baseURL, './'); }
    }
    return url;
}
