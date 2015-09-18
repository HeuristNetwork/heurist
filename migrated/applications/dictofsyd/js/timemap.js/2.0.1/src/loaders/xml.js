
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
 * XML Loader
 *
 * @author Nick Rabinowitz (www.nickrabinowitz.com)
 */
 
/*globals TimeMap */

 /**
 * @class
 * This is a base loader class for XML files.
 *
 * @augments TimeMap.loaders.remote
 * @requires param.js
 *
 * @param {Object} options          All options for the loader
 * @param {String} options.url              URL of XML file to load (NB: must be local address)
 * @parem {String[]} [options.extraTags]    Array of names for extra tag elements to load
 * @param {Object} [options.tagMap]         Map of tagName:paramName pairs, if you want to load
 *                                          data into a differently-named elements
 * @param {mixed} [options[...]]            Other options (see {@link TimeMap.loaders.remote})
 * @return {TimeMap.loaders.remote} Remote loader configured for XML
 */
TimeMap.loaders.xml = function(options) {
    var loader = new TimeMap.loaders.remote(options),
        tagMap = options.tagMap || {},
        extraTags = options.extraTags || [],
        params = loader.params;
    
    /**
     * Load function for remote XML files.
     * @name TimeMap.loaders.xml#load
     * @function
     *
     * @param {TimeMapDataset} dataset  Dataset to load data into
     * @param {Function} callback       Function to call once data is loaded
     */
    loader.load = function(dataset, callback) {
        // get loader callback name (allows cancellation)
        loader.callbackName = loader.getCallbackName(dataset, callback);
        // set the callback function
        // see http://docs.jquery.com/Specifying_the_Data_Type_for_AJAX_Requests
        loader.opts.dataType =  $.browser.msie ? "text" : "xml";
        loader.opts.success = function(data) {
            var xml;
            if (typeof data == "string") {
                xml = new ActiveXObject("Microsoft.XMLDOM");
                xml.async = false;
                xml.loadXML(data);
            } else {
                xml = data;
            }
            TimeMap.loaders.cb[loader.callbackName](xml);
        };
        // download remote data
        $.ajax(loader.opts);
    };
    
    /**
     * Additional parameters to load
     * @name TimeMap.loaders.xml#extraParams
     * @type TimeMap.params.OptionParam[]
     */
    loader.extraParams = [];
    
    // set up extra params
    extraTags.forEach(function(tagName) {
        loader.extraParams.push(
            new TimeMap.params.OptionParam(tagMap[tagName] || tagName, {
                sourceName: tagName
            })
        );
    });
    
    /**
     * Parse any extra tags that have been specified into the config object
     * @name TimeMap.loaders.xml#parseExtra
     * @function
     *
     * @param {Object} config       Config object to modify
     * @param {XML NodeList} node   Parent node to look for tags in
     */
    loader.parseExtra = function(config, node) {
        loader.extraParams.forEach(function(ep) {
            ep.setConfigXML(config, node);
        });
        node = null;
    };
    
    return loader;
};
