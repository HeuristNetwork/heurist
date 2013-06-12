/*! 
 * Timemap.js Copyright 2008 Nick Rabinowitz.
 * Licensed under the MIT License (see LICENSE.txt)
 */

Timemap.js
By Nick Rabinowitz (www.nickrabinowitz.com)

Timemap.js is intended to sync a SIMILE Timeline with a web-based map. 
Depends on: jQuery, Mapstraction 2.x, a map provider of your choice, SIMILE Timeline v1.2 - 2.3.1. 
Tested browsers: Firefox 3.x, Google Chrome, IE7, IE8
Tested map providers: Google v2, Google v3, OpenLayers, Bing Maps
Thanks to Jörn Clausen (http://www.oe-files.de) for initial concept and code.
-------------------------------------------------------------------------------

Getting Started

The best way to get started depends on your learning style, but here are the
places you should look:

  * Working Examples: ./examples/index.html
  * Basic Usage: http://code.google.com/p/timemap/wiki/BasicUsage
  * Code Documentation: ./docs/index.html
  * Homepage: http://code.google.com/p/timemap/
  * Discussion Group: http://groups.google.com/group/timemap-development

-------------------------------------------------------------------------------

Files in the project, in order of importance:

Packed files (YUI Compressor)
  * timemap_full.pack.js:  The library and all helper files. This is the recommended file to use in production.
  * timemap.pack.js:       Just the core library file

Examples (in examples/)
  * I recommend starting with index.html, which describes each example.          
  
Documentation
  * docs/             Code documentation produced by jsdoc-toolkit
  * examples/         Example HTML code
  * LICENSE.txt       The MIT license
  * README.txt        This file

Supporting Libraries (in lib/)
  * timeline-1.2.js     Packed version of Timeline v1.2 - smaller and faster than SIMILE version
  * timeline-2.3.0.js   Packed version of Timeline v2.3.0 - *required* to use this version of Timeline,
                        as the SIMILE version removes jQuery from the global namespace.
  * mxn/*               Mapstraction library - fork found here: https://github.com/nrabinowitz/mxn
                        This version is *required* for timemap.js, until my changes get pulled into the
                        official library
  * jquery-1.x.x.min.js jQuery. Just for convenience - use a CDN version if you prefer
  * json2.pack.js       JSON library - usually not required
  
Source files (in src/)
  * timemap.js:       The core timemap.js library
  * param.js          Abstraction layer for parameters
  * state.js          Functions for loading and serializing timemap state
  * manipulation.js:  Additional functions to manipulate a timemap after loading

Loaders (in src/loaders/)
  * flickr.js         Loader for geotagged Flickr feeds
  * kml.js            Loader for KML files
  * georss.js         Loader for GeoRSS files
  * xml.js            Base loader for XML files
  * google_spreadsheet.js Loader for the Google Spreadsheets API
  * json.js:          Loaders for JSON (both string and jsonp)
  * progressive.js    Loader for data loaded in chunks based on timeline location
  * metaweb.js        Loader for Metaweb data from freebase.com

Other stuff
  * src/ext/          A couple of extension files I didn't think were worth being in the core library
  * images/           Simple icons for timeline events
  * tests/            jsUnit tests
  
Comments welcomed at nick (at) nickrabinowitz (dot) com.
