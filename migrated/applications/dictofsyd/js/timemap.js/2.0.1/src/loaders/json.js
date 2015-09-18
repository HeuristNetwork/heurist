
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* 
 * Timemap.js Copyright 2010 Nick Rabinowitz.
 * Licensed under the MIT License (see LICENSE.txt)
 */

/**
 * @fileOverview
 * JSON Loaders (JSONP, JSON String)
 *
 * @author Nick Rabinowitz (www.nickrabinowitz.com)
 */
 
// for JSLint
/*global TimeMap */

(function() {
    var loaders = TimeMap.loaders;

/**
 * @class
 * JSONP loader - expects a service that takes a callback function name as
 * the last URL parameter.
 *
 * <p>The jsonp loader assumes that the JSON can be loaded from a url with a "?" instead of
 * the callback function name, e.g. "http://www.test.com/getsomejson.php?callback=?". See
 * <a href="http://api.jquery.com/jQuery.ajax/">the jQuery.ajax documentation</a> for more
 * details on how to format the url, especially if the parameter is not called "callback".
 * This works for services like Google Spreadsheets, etc., and accepts remote URLs.</p>
 * @name TimeMap.loaders.jsonp
 * @augments TimeMap.loaders.remote
 *
 * @example
TimeMap.init({
    datasets: [
        {
            title: "JSONP Dataset",
            type: "jsonp",
            options: {
                url: "http://www.example.com/getsomejson.php?callback=?"
            }
        }
    ],
    // etc...
});
 *
 * @constructor
 * @param {Object} options          All options for the loader:
 * @param {String} options.url          URL of JSON service to load, callback name replaced with "?"
 * @param {mixed} [options[...]]        Other options (see {@link loaders.remote})
 */
loaders.jsonp = function(options) {
    var loader = new loaders.remote(options);
    
    // set ajax settings for loader
    loader.opts.dataType = 'jsonp';
    
    return loader;
};

/**
 * @class
 * JSON string loader - expects a plain JSON array.
 *
 * <p>The json_string loader assumes an array of items in plain JSON, with no
 * callback function - this will require a local URL.</p>
 * @name TimeMap.loaders.json
 * @class
 *
 * @augments TimeMap.loaders.remote
 *
 * @example
TimeMap.init({
    datasets: [
        {
            title: "JSON String Dataset",
            type: "json_string",
            options: {
                url: "mydata.json"    // Must be a local URL
            }
        }
    ],
    // etc...
});
 *
 * @param {Object} options          All options for the loader
 * @param {String} options.url          URL of JSON file to load
 * @param {mixed} [options[...]]        Other options (see {@link loaders.remote})
 */
loaders.json = function(options) {
    var loader = new loaders.remote(options);
    
    // set ajax settings for loader
    loader.opts.dataType =  'json';
    
    return loader;
};

// For backwards compatibility
loaders.json_string = loaders.json;

})();
