/**
*  Various utility functions
*
* @todo - split to generic utilities and UI utilities
* @todo - split utilities for hapi and load them dynamically from hapi
*
* @see editing_input.js
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.util) 
{

window.hWin.HEURIST4.util = {


    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        if (window.hWin.HEURIST4.util.isnull(obj)){
            return true;
        }else if(window.hWin.HEURIST4.util.isArray(obj)){
            return obj.length<1;
        }else{
            return (obj==="") || (obj==="null");
        }

    },
    
    //
    //remove ian's trailing &gt; used to designate a pointer field and replace consistently with >>
    //
    trim_IanGt: function(name){
            if(name){
                name = name.trim();
                if(name.substr(name.length-1,1)=='>') name = name.substr(0,name.length-2);
                return name;
            }else{
                return '';
            }
    },
    

    isNumber: function (n) {
        //return typeof n === 'number' && isFinite(n);
        return !isNaN(parseFloat(n)) && isFinite(n);
    },

    cloneJSON:function (data){
        try{
            return JSON.parse(JSON.stringify(data));
        }catch (ex2){
            console.log('cannot clone json array '+data);
            return [];
        }
    },

    // get current font size in em
    em: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },

    // get current font size in pixels
    px: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (input / emSize);
    },

    //
    // enable or disable element
    //
    setDisabled: function(element, mode){
      if(element){
          if(!$.isArray(element)){
                element = [element];
          }
          $.each(element, function(idx, ele){
              ele = $(ele);
              
              //if(mode !== (ele.prop('disabled')=='disabled')){
              
                  if (mode) {
                        ele.prop('disabled', 'disabled');
                        ele.addClass('ui-state-disabled');
                  }else{
                        ele.removeProp('disabled');
                        ele.removeClass('ui-state-disabled ui-button-disabled');
                  }
              
              //}
          });
      }
    },
    
    isIE: function () {
        var myNav = navigator.userAgent.toLowerCase();
        return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    },
    
    //
    //
    //
    checkProtocolSupport: function(url){
        
        if (window.hWin.HEURIST4.util.isIE()) { //This bastard always needs special treatment
        
            if (typeof (navigator.msLaunchUri) == typeof (Function)) {
                
                navigator.msLaunchUri(url,
                    function () { /* Success */ },
                    function () { /* Failure */ window.hWin.HEURIST4.msg.showMsgErr('Not supported') });
                return;
            }
                
            try {
                var flash = new ActiveXObject("Plugin.mailto");
            } catch (e) {
                //not installed
            }
        } else { //firefox,chrome,opera
            //navigator.plugins.refresh(true);
            var mimeTypes = navigator.mimeTypes;
            var mime = navigator.mimeTypes['application/x-mailto'];
            if(mime) {
                //installed
            } else {
                //not installed
                 window.hWin.HEURIST4.msg.showMsgErr('Not supported');
            }
        }      
      
        
    },

    //--- HEURIST QUERY ROUTINE -------
    
    //
    // from object to query string
    //
    composeHeuristQueryFromRequest: function(query_request, encode){
            var query_string = 'db=' + window.hWin.HAPI4.database;
        
            if(!window.hWin.HEURIST4.util.isnull(query_request)){

                query_string = query_string + '&w='+query_request.w;
                
                if(!window.hWin.HEURIST4.util.isempty(query_request.q)){
                    
                    if($.isArray(query_request.q)){
                        sq = JSON.stringify(query_request.q);
                    }else{
                        sq = query_request.q;
                    }
                    
                    if(encode){
                        sq = encodeURIComponent(sq);
                    }
                    
                    query_string = query_string + '&q=' + sq;
                }
                if(!window.hWin.HEURIST4.util.isempty(query_request.rules)){
                    //@todo simplify rules array - rempove redundant info
                    query_string = query_string + '&rules=' + 
                        (encode?encodeURIComponent(query_request.rules):query_request.rules) + 
                        ((query_request.rulesonly==1 || query_request.rulesonly==true)?'&rulesonly=1':'');
                        
                }
            }else{
                query_string = query_string + '&w=all';
            }        
            return query_string;        
    },

    composeHeuristQuery2: function(params, encode){
        if(params){
        
            var rules = params.rules;
            rules = window.hWin.HEURIST4.util.cleanRules(rules);  
            if(rules!=null){ 
                rules = JSON.stringify(rules);
            }
        
            return window.hWin.HEURIST4.util.composeHeuristQuery(params.q, 
                                        params.w, rules, params.rulesonly, params.notes, encode);
        }else
            return '?';
    },

    composeHeuristQuery: function(query, domain, rules, rulesonly, notes, encode){
            var query_to_save = [];
            if(!(window.hWin.HEURIST4.util.isempty(domain) || domain=="all")){
                query_to_save.push('w='+domain);
            }
            if(!window.hWin.HEURIST4.util.isempty(query)){
                
               if($.isArray(query) || $.isPlainObject(query)){
                  query = JSON.stringify(query);
               }  
               query_to_save.push('q='+ (encode?encodeURIComponent(query):query) );
            }
            if(!window.hWin.HEURIST4.util.isempty(rules)){
              query_to_save.push('rules='+ (encode?encodeURIComponent(rules):rules));
              if(rulesonly==1 || rulesonly==true){
                  query_to_save.push('rulesonly=1');
              }
            }
            if(!window.hWin.HEURIST4.util.isempty(notes)){
               query_to_save.push('notes='+ (encode?encodeURIComponent(notes):notes));
            }
            return '?'+query_to_save.join('&');
    },
    
    
    cleanRules: function(rules){
        
        if(window.hWin.HEURIST4.util.isempty(rules)){
            return null;
        }
        
        if(typeof rules==='string'){
            try{
                rules = JSON.parse(rules);
            }catch(ex){
                return null;
            }
        }
        
        for(var k=0; k<rules.length; k++){
            delete rules[k]['codes'];
            var rl = null;
            if(rules[k]['levels'] && rules[k]['levels'].length>0){
                var rl = window.hWin.HEURIST4.util.cleanRules(rules[k]['levels']);
            }
            if(rl==null){
                delete rules[k]['levels'];    
            }else{
                rules[k]['levels'] = rl;    
            }
            
        }
        
        return rules;        
    },

    //
    // both parameter should be JSON array or Object
    //
    mergeHeuristQuery: function(){
        
        var res_query = [];
        
        if(arguments.length>0){

            var idx=1, len = arguments.length;
            
            res_query = arguments[0];
            for (;idx<len;idx++){
                   res_query = window.hWin.HEURIST4.util.mergeTwoHeuristQueries(res_query, arguments[idx]);
            }     
        }   
        
        return res_query;
    },

    mergeTwoHeuristQueries: function(query1, query2){
        
        if(jQuery.type(query1) === "string"){
            var notJson = true;
            try{
                //query1 = JSON.parse(query1);
                var query1a = $.parseJSON(query1);
                if(window.hWin.HEURIST4.util.isJSON(query1a)){
                    query1 = query1a;
                    notJson = false;
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query1)){
                    query1 = {};    
                }else{
                    query1 = {plain: encodeURIComponent(query1)}; //query1.split('"').join('\\\"')};    
                }
            }
        }
        
        if(jQuery.type(query2) === "string"){
            var notJson = true;
            try{
                //query2 = JSON.parse(query2);
                var query2a = $.parseJSON(query2);
                if(window.hWin.HEURIST4.util.isJSON(query2a)){
                    query2 = query2a;
                    notJson = false;
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query2)){
                    query2 = {};    
                }else{
                    query2 = {plain: encodeURIComponent(query2)}; //query2.split('"').join('\\\"')};    
                }
            }
        }
        if(window.hWin.HEURIST4.util.isnull(query1) || $.isEmptyObject(query1)){
            return query2;
        }
        if(window.hWin.HEURIST4.util.isnull(query2) || $.isEmptyObject(query2)){
            return query1;
        }
        if(!$.isArray(query1)){
            query1 = [query1];
        }
        if(!$.isArray(query2)){
            query2 = [query2];
        }
        return query1.concat(query2)
    },
    
    //
    // converts query string to object
    //
    parseHeuristQuery: function(qsearch)
    {
        var domain = null, rules = '', rulesonly = 0, notes = '';
        if(qsearch && qsearch.indexOf('?')==0){
            domain  = window.hWin.HEURIST4.util.getUrlParameter('w', qsearch);
            rules   = window.hWin.HEURIST4.util.getUrlParameter('rules', qsearch);
            rulesonly = window.hWin.HEURIST4.util.getUrlParameter('rulesonly', qsearch);
            notes   = window.hWin.HEURIST4.util.getUrlParameter('notes', qsearch);
            qsearch = window.hWin.HEURIST4.util.getUrlParameter('q', qsearch);
        }
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

        return {q:qsearch, w:domain, rules:rules, rulesonly:rulesonly, notes:notes};
    },

    //
    //
    //
    isJSON: function(value){
        
            try {
                if(typeof value === 'string'){
                    value = $.parseJSON(value);    
                }
                if($.isArray(value) || $.isPlainObject(value)){
                    return true;
                }
            }
            catch (err) {
            } 
            
            return false;       
    },
    
    //
    // get combination query and rules as json array for map query layer
    //    
    getJSON_HeuristQueryAndRules: function(filter, rules){

        var res = '';
        if(!window.hWin.HEURIST4.util.isempty(filter.trim())){
            
            var hasRules = !window.hWin.HEURIST4.util.isempty(rules);

            if(window.hWin.HEURIST4.util.isJSON(filter)){
                res = (hasRules?'{"q":':'')+filter;
            }else{
                //this is not json query
                //escape backslash to avoid errors
                res = hasRules?('{"q":"'+filter.split('"').join('\\\"')+'"'):filter;
            }

            if(hasRules){
                res = res + ',"rules":'+rules+'}';
            } else{
                //res = res + '}';     
            }
        }
        
        return res;
    },
    
    //
    // Extract parameter from given URL or from current window.location.search
    //
    getUrlParameter: function getUrlParameter(name, query){

        if(!query){
            query = window.location.search;
        }

        var regexS = "[\\?&]"+name+"=([^&#]*)";
        var regex = new RegExp( regexS );
        var results = regex.exec( query );

        if( results == null ) {
            return null;
        } else {
            try{
                return decodeURIComponent(results[1]);
            }catch (ex){
                return results[1];
                //console.log('cant decode '+name+'='+results[1]);
            }
        }
    },

    isArrayNotEmpty: function (a){
        return (window.hWin.HEURIST4.util.isArray(a) && a.length>0);
    },

    isArray: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Array]';
    },

    /*
    htmlEscape: function(s) {
        return s?s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;").replace(/"/g, "&#34;"):'';
    },
    */
    
    htmlEscape: function (text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',  //&#34
        "'": '&#039;'
      };

      return text? (''+text).replace(/[&<>"']/g, function(m) { return map[m]; }):'';
    },    

    isObject: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Object]';
    },

    stopEvent: function(e){
        if (!e) e = window.event;

        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
            e.preventDefault();
        }
        return e;
    },

    //
    // we have to reduce the usage to minimum. Need to implement method in hapi via central controller
    // this method is used for call H3 scripts in H4 code
    //
    sendRequest: function(url, request, caller, callback){

        if(!request.db){
            request.db = window.hWin.HAPI4.database;
        }

        //note jQuery ajax does not properly in the loop - success callback does not work often
        $.ajax({
            url: url,
            type: "POST",
            data: request,
            dataType: "json",
            cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
                if(callback){
                    if(caller){
                        callback(caller, {status:window.hWin.HAPI4.ResponseStatus.UNKNOWN_ERROR,
                                message: jqXHR.responseText });
                    }else{
                        callback({status:window.hWin.HAPI4.ResponseStatus.UNKNOWN_ERROR,
                                message: jqXHR.responseText });
                    }
                }
                //message:'Error connecting server '+textStatus});
            },
            success: function( response, textStatus, jqXHR ){
                if(callback){
                    if(caller){
                        callback(caller, response);
                    }else{
                        callback(response);    
                    }
                }
            }
        });
    },

    getScrollBarWidth: function() {
        var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
            widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    },

    /**
    * convert wkt to
    * format - 0 timemap, 1 google
    *
    * @todo 2 - kml
    * @todo 3 - OpenLayers
    */
    parseCoordinates: function(type, wkt, format, google) {

        if(type==1 && typeof google.maps.LatLng != "function") {
            return null;
        }

        var matches = null;

        switch (type) {
            case "p":
            case "point":
                matches = wkt.match(/POINT\s?\((\S+)\s+(\S+)\)/i);
                break;

            case "c":  //circle
            case "circle":
                matches = wkt.match(/LINESTRING\s?\((\S+)\s+(\S+),\s*(\S+)\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\S+\)/i);
                break;

            case "l":  //polyline
            case "polyline":
            case "path":
                matches = wkt.match(/LINESTRING\s?\((.+)\)/i);
                if (matches){
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;

            case "r":  //rectangle
            case "rect":
                //matches = wkt.match(/POLYGON\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);
                //break;
            case "pl": //polygon
            case "polygon":
                matches = wkt.match(/POLYGON\s?\(\((.+)\)\)/i);
                if (matches) {
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;
        }


        var bounds = null, southWest, northEast,
        shape  = null,
        points = []; //google points

        if(matches && matches.length>0){

            switch (type) {
                case "p":
                case "point":
                
                    var x0 = parseFloat(matches[1]);
                    var y0 = parseFloat(matches[2]);
                    
                    if(format==0){
                        shape = { point:{lat: y0, lon:x0 } };
                    }else{
                        point = new google.maps.LatLng(y0, x0);
                        points.push(point);
                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(y0 - 0.5, x0 - 0.5),
                            new google.maps.LatLng(y0 + 0.5, x0 + 0.5));
                    }
                    
                    

                    break;

                /*
                case "r":  //rectangle
                case "rect":

                    if(matches.length<6){
                        matches.push(matches[3]);
                        matches.push(matches[4]);
                    }

                    var x0 = parseFloat(matches[0]);
                    var y0 = parseFloat(matches[2]);
                    var x1 = parseFloat(matches[5]);
                    var y1 = parseFloat(matches[6]);

                    if(format==0){
                        shape  = [
                            {lat: y0, lon: x0},
                            {lat: y0, lon: x1},
                            {lat: y1, lon: x1},
                            {lat: y1, lon: x0},
                        ];

                        shape = {polygon:shape};
                    }else{

                        southWest = new google.maps.LatLng(y0, x0);
                        northEast = new google.maps.LatLng(y1, x1);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);

                        points.push(southWest, new google.maps.LatLng(y0, x1), northEast, new google.maps.LatLng(y1, x0));
                    }

                    break;
                */
                case "c":  //circle
                case "circle":  //circle

                    if(format==0){

                        var x0 = parseFloat(matches[1]);
                        var y0 = parseFloat(matches[2]);
                        var radius = parseFloat(matches[3]) - parseFloat(matches[1]);

                        shape = [];
                        for (var i=0; i <= 40; ++i) {
                            var x = x0 + radius * Math.cos(i * 2*Math.PI / 40);
                            var y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
                            shape.push({lat: y, lon: x});
                        }
                        shape = {polygon:shape};
                        /*
                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(y0 - radius, x0 - radius),
                            new google.maps.LatLng(y0 + radius, x0 + radius));
                         */
                        
                    }else{
                        /* ARTEM TODO
                        var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
                        var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
                        setstartMarker(centre);
                        createcircle(oncircle);

                        //bounds = circle.getBounds();
                        */
                    }

                    break;

                case "l":  ///polyline
                case "path":
                case "polyline":

                case "r":  //rectangle
                case "rect":
                case "pl": //polygon
                case "polygon":

                    shape = [];

                    var j;
                    var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
                    for (j=0; j < matches.length; ++j) {
                        var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);

                        var point = {lat:parseFloat(match_matches[2]), lon:parseFloat(match_matches[1])};

                        if(format==0){
                            shape.push(point);
                        }else{
                            points.push(new google.maps.LatLng(points.lat, points.lon));
                        }
                        
                        if (point.lat < minLat) minLat = point.lat;
                        if (point.lat > maxLat) maxLat = point.lat;
                        if (point.lon < minLng) minLng = point.lon;
                        if (point.lon > maxLng) maxLng = point.lon;
                        
                    }

                    if(format==0){
                        shape = (type=="l" || type=="polyline")?{polyline:shape}:{polygon:shape};
                    }else{
                        southWest = new google.maps.LatLng(minLat, minLng);
                        northEast = new google.maps.LatLng(maxLat, maxLng);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);
                    }
                    
            }

        }
        
        if(format==0){
            return shape; //{bounds:bounds, shape:shape};
        }else{
            return {bounds:bounds, points:points};
        }

    },//end parseCoordinates

    // @todo change temporal to moment.js for conversion
    parseDates: function(start, end){
         if(window['Temporal'] && (start || end)){   
                //Temporal.isValidFormat(start)){
                if(start==null && end!=null){
                    start = end;
                    end = null;
                }
                
                            // for VISJS timeline
                            function __forVis(dt){
                                if(dt){
                                    var res = dt.toString('yyyy-MM-ddTHH:mm:ssz');
                                    if(res.indexOf('-')==0){ //BCE
                                        res = res.substring(1);
                                        res = '-'+('000000'+res).substring(res.length);
                                    }
                                    return res;
                                }else{
                                    return '';
                                }
                                
                            }    
                
                
                            try{
                                var temporal;
                                if($.type( start ) === "string"  && start.search(/VER=/)){
                                    temporal = new Temporal(start);
                                    if(temporal){
                                        var dt = temporal.getTDate('PDB');  //probable begin
                                        if(!dt) dt = temporal.getTDate('TPQ');
                                        
                                        if(dt){ //this is range - find end date
                                            var dt2 = temporal.getTDate('PDE'); //probable end
                                            if(!dt2) dt2 = temporal.getTDate('TAQ');
                                            end = __forVis(dt2);
                                        }else{
                                            dt = temporal.getTDate('DAT');  //simple date
                                        }
                                        
                                        if(dt){
                                            start = __forVis(dt);
                                        }else{
                                            return null;
                                        }
                                    }
                                }
                                if(start!="" && $.type( end ) === "string"  && end.search(/VER=/)){
                                    temporal = new Temporal(end);
                                    if(temporal){
                                        var dt = temporal.getTDate('PDE'); //probable end
                                        if(!dt) dt = temporal.getTDate('TAQ');
                                        if(!dt) dt = temporal.getTDate('DAT');
                                        end = __forVis(dt);
                                    }
                                }
                            }catch(e){
                                return null;
                            }
                            return [start, end];
         }
         return null;
   },    
    
    //
    // Get CSS property value for a not yet applied class
    //
    getCSS: function (prop, fromClass) {
        var $inspector = $("<div>").css('display', 'none').addClass(fromClass);
        $("body").append($inspector); // add to DOM, in order to read the CSS property
        try {
            return $inspector.css(prop);
        } finally {
            $inspector.remove(); // and remove from DOM
        }
    },
    
    /*: function(e){
        for(var r=0,i=0;i<e.length;i++){
            r=(r<<5)-r+e.charCodeAt(i),r&=r;   
        }
        return r
    },*/

    hashString: function(str) {
    
        var hash = 0, i, c;
        var strlen = str?str.length:0;
        if (strlen == 0) return hash;
        
        for (i = 0; i < strlen; i++) {
            c = str.charCodeAt(i);
            hash = ((hash<<5)-hash)+c;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash;    
    },
    
    //
    // download given url as a file (repalcement of usage A)
    //
    downloadURL: function(url, callback) {
        $idown = $('#idown');

        if ($idown.length==0) {
            $idown = $('<iframe>', { id:'idown' }).hide().appendTo('body');
        }
        if ($.isFunction(callback)) {
                $idown.on('load', callback);   
        }
        $idown.attr('src',url);
    },
    
    //
    // download content of given element (for example text area) as a text file
    //
    downloadInnerHtml: function (filename, ele, mimeType) {
        
            var elHtml = $(ele).html();
            window.hWin.HEURIST4.util.downloadData(filename, elHtml, mimeType);
    }, 
       
    downloadData: function (filename, data, mimeType) {
        
        mimeType = mimeType || 'text/plain';
        var  content = 'data:' + mimeType  +  ';charset=utf-8,' + encodeURIComponent(data);

        var link = document.createElement("a");
        link.setAttribute('download', filename);
        link.setAttribute('href', content);
        if (window.webkitURL != null)
        {
            // Chrome allows the link to be clicked
            // without actually adding it to the DOM.
            link.click();        
            link = null;
        }
        else
        {
            // Firefox requires the link to be added to the DOM
            // before it can be clicked.
            link.onclick = function(){ document.body.removeChild(link); link=null;} //destroy link;
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();        
        }
        
    },    
    
    isRecordSet: function(recordset){
        return !window.hWin.HEURIST4.util.isnull(recordset) && $.isFunction(recordset.isA) && recordset.isA("hRecordSet");   
    },
    
    random: function(){
        //Math.round(new Date().getTime() + (Math.random() * 100));
        return Math.floor((Math.random() * 10000) + 1);
    },
    
    //scan all frames of current window and return object by name
    findObjInFrame: function(name){
      
      var i, frames;
      frames = document.getElementsByTagName("iframe");
      for (i = 0; i < frames.length; ++i)
      {  
         if( !window.hWin.HEURIST4.util.isnull(frames[i]['contentWindow'][name])){
             return frames[i]['contentWindow'][name];
         }
      }
      return null;
    },

    getMediaServerFromURL:function(filename){
        filename = filename.toLowerCase();
        if(filename.indexOf('youtu.be')>=0 || filename.indexOf('youtube.com')>=0){
            return 'youtube';
        }else if(filename.indexOf('vimeo.com')>=0){
            return 'vimeo';
        }else if(filename.indexOf('soundcloud.com')>=0){
            return 'soundcloud';            
        }else{
            return null;
        }
    },
    
    getFileExtension:function(filename){
        // (/[.]/.exec(filename)) ? /[^.]+$/.exec(filename)[0] : undefined;
        // filename.split('.').pop();
        //filename.slice((filename.lastIndexOf(".") - 1 >>> 0) + 2);
        var res = filename.match(/\.([^\./\?]+)($|\?)/);
        return (res && res.length>1)?res[1]:'';
    },
    
    wktValueToDescription:function(wkt){
        
        // parse a well-known-text value and return the standard description (type + summary)
        var matches = wkt.match(/^(p|c|r|pl|l) (?:point|polygon|linestring)\s?\(?\(([-0-9.+, ]+?)\)/i);
        if(matches && matches.length>1){
            
        var typeCode = matches[1];

        var pointPairs = matches[2].split(/,/);
        var X = [], Y = [];
        for (var i=0; i < pointPairs.length; ++i) {
            var point = pointPairs[i].split(/\s+/);
            X.push(parseFloat(point[0]));
            Y.push(parseFloat(point[1]));
        }

        if (typeCode == "p") {
            return { type: "Point", summary: X[0].toFixed(5)+", "+Y[0].toFixed(5) };
        }
        else if (typeCode == "l") {
            return { type: "Path", summary: "X,Y ("+ X.shift().toFixed(5)+","+Y.shift().toFixed(5)+") - ("+X.pop().toFixed(5)+","+Y.pop().toFixed(5)+")" };
        }
        else {
            X.sort();
            Y.sort();

            var type = "Unknown";
            if (typeCode == "pl") type = "Polygon";
            else if (typeCode == "r") type = "Rectangle";
                else if (typeCode == "c") type = "Circle";
                    else if (typeCode == "l") type = "Path";

            var minX = X[0];
            var minY = Y[0];
            var maxX = X.pop();
            var maxY = Y.pop();
            return { type: type, summary: "X "+minX.toFixed(5)+","+maxX.toFixed(5)+" Y "+minY.toFixed(5)+","+maxY.toFixed(5) };
        }
        }else{
            return {type:'',summary:''};
        }
        
    },
    
     versionCompare: function(v1, v2, options) {
         // determines if the version in the cache (v1) is older than the version in configIni.php (v2)
         // used to detect change in version so that user is prompted to clear cache and reload
         // returns -1 if v1 is older, -2 v1 is newer, +1 if they are the same
        var lexicographical = options && options.lexicographical,
            zeroExtend = options && options.zeroExtend,
            v1parts = v1.split('.'),
            v2parts = v2.split('.');

        function isValidPart(x) {
            return (lexicographical ? /^\d+[A-Za-z]*$/ : /^\d+$/).test(x);
        }

        if (!v1parts.every(isValidPart) || !v2parts.every(isValidPart)) {
            return NaN;
        }

        if (zeroExtend) {
            while (v1parts.length < v2parts.length) v1parts.push("0");
            while (v2parts.length < v1parts.length) v2parts.push("0");
        }

        if (!lexicographical) {
            v1parts = v1parts.map(Number);
            v2parts = v2parts.map(Number);
        }

        for (var i = 0; i < v1parts.length; ++i) {
            if (v2parts.length == i) {
                return 1; // versions are the saame
            }

            if (v1parts[i] == v2parts[i]) {
                continue; // sub elements are the same, continue compare
            }
            else if (v1parts[i] > v2parts[i]) {
                return -2; // cached version is newer, we will still need to clear cache and reload
            }
            else {
                return -1; // cached version is older, we will need to clear cache and reload
            }
        }

        if (v1parts.length != v2parts.length) {
            return -1;
        }

        return 0;
    },
    
    uniqueArray: function(arr){
        
            var n = {},r=[];
            for(var i = 0; i < arr.length; i++) 
            {
                if($.isPlainObject(arr[i])){
                    r.push(arr[i]);
                }else if (!n[arr[i]]) 
                {
                    n[arr[i]] = true; 
                    r.push(arr[i]); 
                }
            }
            return r;            
    },
    
    //not strict search - valuable for numeric vs string 
    findArrayIndex: function(elt, arr /*, from*/)
    {
        var len = arr.length;

        var from = Number(arguments[2]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in arr &&
                arr[from] == elt)
                return from;
        }
        return -1;
    }
        

}//end util

String.prototype.htmlEscape = function() {
    return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;");
}
String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}
String.prototype.lpad = function(padString, length) {
    var str = this;
    while (str.length < length)
        str = padString + str;
    return str;
}

if (!Array.prototype.indexOf)
{
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        var len = this.length;

        var from = Number(arguments[1]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}



/*
if (!Array.prototype.unique){

    Array.prototype.unique = function()
    {
        
        //return $.grep(this, function(el, index) {
        //    return index === $.inArray(el, this);
        //});
        
        
            var n = {},r=[];
            for(var i = 0; i < this.length; i++) 
            {
                if (!n[this[i]]) 
                {
                    n[this[i]] = true; 
                    r.push(this[i]); 
                }
            }
            return r;        
    };
}
*/
}


$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}

