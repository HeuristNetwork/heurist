
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
 * Flickr Loader
 *
 * @author Nick Rabinowitz (www.nickrabinowitz.com)
 */
 
// for JSLint
/*global TimeMap */

/**
 * @class
 * Flickr loader: Load JSONP data from Flickr. 
 *
 * <p>This is a loader for Flickr data. You probably want to use it with a
 * URL for the Flickr Geo Feed API: <a href="http://www.flickr.com/services/feeds/geo/">http://www.flickr.com/services/feeds/geo/</a></p>
 *
 * <p>The loader takes a full URL, minus the JSONP callback function.</p>
 *
 * @augments TimeMap.loaders.jsonp
 * @requires loaders/json.js
 *
 * @example
TimeMap.init({
    datasets: [
        {
            title: "Flickr Dataset",
            type: "flickr",
            options: {
                // This is just the latest geotagged photo stream - try adding
                // an "id" or "tag" or "photoset" parameter to get what you want
                url: "http://www.flickr.com/services/feeds/geo/?format=json&jsoncallback=?"
            }
        }
    ],
    // etc...
});
 * @see <a href="../../examples/pathlines.html">Flickr Pathlines Example</a>
 *
 * @param {Object} options          All options for the loader
 * @param {String} options.url          Full JSONP url of Flickr feed to load
 * @param {mixed} [options[...]]        Other options (see {@link TimeMap.loaders.jsonp})
 */
TimeMap.loaders.flickr = function(options) {
    var loader = new TimeMap.loaders.jsonp(options);
    
    // set ajax settings for loader
    loader.opts.jsonp = 'jsoncallback';
    
    /**
     * Preload function for Flickr feeds
     * @name TimeMap.loaders.flickr#preload
     * @function
     * @parameter {Object} data     Data to preload
     * @return {Array} data         Array of item data
     */
    loader.preload = function(data) {
        return data.items;
    };
    
    /**
     * Transform function for Flickr feeds
     * @name TimeMap.loaders.flickr#transform
     * @function
     * @parameter {Object} data     Data to transform
     * @return {Object} data        Transformed data for one item
     */
    loader.transform = function(data) {
        var item = {
            title: data.title,
            start: data.date_taken,
            point: {
                lat: data.latitude,
                lon: data.longitude
            },
            options: {
                description: data.description
                    .replace(/&gt;/g, ">")
                    .replace(/&lt;/g, "<")
                    .replace(/&quot;/g, '"')
            }
        };
        if (options.transformFunction) {
            item = options.transformFunction(item);
        }
        return item;
    };

    return loader;
};
