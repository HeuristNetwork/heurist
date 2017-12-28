/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

/**
* Visualisation plugin
* Requirements:
* 
* Internal Javascript:
* - settings.js
* - overlay.js
* - gephi.js
* - visualize.js
* 
* External Javascript:
* - jQuery          http://jquery.com/
* - D3              http://d3js.org/
* - D3 fisheye      https://github.com/d3/d3-plugins/tree/master/fisheye
* - Colpick         http://colpick.com/plugin
*
* Objects must have at least the following properties:
* - id
* - name
* - image
* - count
* 
* Available settings and their default values:
* - linetype: "straight",
* - linelength: 100,
* - linewidth: 15,
* - linecolor: "#22a",
* - markercolor: "#000",
* 
* - entityradius: 30,
* - entitycolor: "#b5b5b5",
* 
* - labels: true,
* - fontsize: "8px",
* - textlength: 60,
* - textcolor: "#000",
* 
* - formula: "linear",
* - fisheye: false,
* 
* - gravity: "off",
* - attraction: -3000,
* 
* - translatex: 0,
* - translatey: 0,
* - scale: 1
*/

var settings;   // Plugin settings object
var svg;        // The SVG where the visualisation will be executed on
(function ( $ ) {
    // jQuery extension
    $.fn.visualize = function( options ) {
        
        // Select and clear SVG.
        svg = d3.select("#d3svg");
        svg.selectAll("*").remove();
        svg.append("text").text("Building graph ...").attr("x", "25").attr("y", "25");   
        
        
        // Default plugin settings
        settings = $.extend({
            // Custom functions
            getData: $.noop(), // Needs to be overriden with custom function
            getLineLength: function() { return getSetting(setting_linelength); },
            
            selectedNodeIds: [],
            onRefreshData: function(){},
            triggerSelection: function(selection){}, 
            
            isDatabaseStructure: false,
            
            showCounts: true,
            
            // UI setting controls
            showLineSettings: true,
            showLineType: true,
            showLineLength: true,
            showLineWidth: true,
            showLineColor: true,
            showMarkerColor: true, 
            
            showEntitySettings: true, 
            showEntityRadius: true,
            showEntityColor: true,
            
            showTextSettings: true,
            showLabels: true,
            showFontSize: true,
            showTextLength: true,
            showTextColor: true,
            
            showTransformSettings: true,
            showFormula: true,
            showFishEye: true,
            
            showGravitySettings: true,
            showGravity: true,
            showAttraction: true,
            
            
            // UI default settings
            advanced: false,
            linetype: "straight",
            linelength: 100,
            linewidth: 3,
            linecolor: "#22a",
            markercolor: "#000",
            
            entityradius: 30,
            entitycolor: "#b5b5b5",
            
            labels: true,
            fontsize: "8px",
            textlength: 60,
            textcolor: "#000",
            
            formula: "linear",
            fisheye: false,
            
            gravity: "off",
            attraction: -3000,
            
            translatex: 0,
            translatey: 0,
            scale: 1
        }, options );
 
        // Handle settings (settings.js)
        checkStoredSettings();  //restore default settings
        handleSettingsInUI();

        // Check visualisation limit
        var amount = Object.keys(settings.data.nodes).length;
        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
        
        visualizeData();    

        var ele_warn = $('#net_limit_warning');
        if(amount >= MAXITEMS) {
            ele_warn.html('These results are limited to '+MAXITEMS+' records<br>(limit set in your profile Preferences)<br>Please filter to a smaller set of results').show();//.delay(2000).fadeOut(10000);
        }else{
            ele_warn.hide();
        }
 
        return this;
    };
}( jQuery ));
    

/*******************************START OF VISUALISATION HELPER FUNCTIONS*******************************/
var maxCountForNodes, maxCountForLinks; 
function determineMaxCount(data) {
    maxCountForNodes = 1;
    maxCountForLinks = 1;
    if(data && data.nodes.length > 0) {
        for(var i = 0; i < data.nodes.length; i++) {
            //console.log(data.nodes[i]);
            if(data.nodes[i].count > maxCountForNodes) {
                maxCountForNodes = data.nodes[i].count;
            } 
        }
    }
    if(data && data.links.length > 0) {
        for(var i = 0; i < data.links.length; i++) {
            if(data.links[i].targetcount > maxCountForLinks) {
                maxCountForLinks = data.links[i].targetcount;
            } 
        }
    }
    //console.log("Max count: " + maxCount);
}

function getNodeDataById(id){
    if(data && data.nodes.length > 0) {
        for(var i = 0; i < data.nodes.length; i++) {
            //console.log(data.nodes[i]);
            if(data.nodes[i].id==id) {
                return data.nodes[i];
            } 
        }
    }
    return null;
}

/** Calculates log base 10 */
function log10(val) {
    return Math.log(val) / Math.LN10;
}

function _addDropShadowFilter(){

// filter chain comes from:
// https://github.com/wbzyl/d3-notes/blob/master/hello-drop-shadow.html
// cpbotha added explanatory comments
// read more about SVG filter effects here: http://www.w3.org/TR/SVG/filters.html

// filters go in defs element
var defs = svg.append("defs");

// create filter with id #drop-shadow
// height=130% so that the shadow is not clipped
var filter = defs.append("filter")
    .attr("id", "drop-shadow")
    .attr("height", "120%");

// SourceAlpha refers to opacity of graphic that this filter will be applied to
// convolve that with a Gaussian with standard deviation 3 and store result
// in blur
filter.append("feGaussianBlur")
    .attr("in", "SourceAlpha")
    .attr("stdDeviation", 3)
    .attr("result", "blur");

// translate output of Gaussian blur to the right and downwards with 2px
// store result in offsetBlur
filter.append("feOffset")
    .attr("in", "blur")
    .attr("dx", 3)
    .attr("dy", 3)
    .attr("result", "offsetBlur");

// overlay original SourceGraphic over translated blurred opacity by using
// feMerge filter. Order of specifying inputs is important!
var feMerge = filter.append("feMerge");

feMerge.append("feMergeNode")
    .attr("in", "offsetBlur")
feMerge.append("feMergeNode")
    .attr("in", "SourceGraphic");
}

var iconSize = 16; // The icon size
var circleSize = 12; //iconSize * 0.75; // Circle around icon size
var currentMode = 'infoboxes'; //or 'icons';
var maxEntityRadius = 40;
var maxLinkWidth = 25;


/** Executes the chosen formula with a chosen count & max size */

function executeFormula(count, maxCount, maxSize) {
    // Avoid minus infinity and wrong calculations etc.
    if(count <= 0) {
        count = 1;
    }
    
    //console.log("Count: " + count + ", max count: " + maxCount + ", max Size: " + maxSize);
    var formula = getSetting(setting_formula);
    if(formula == "logarithmic") { // Log                                                           
        return maxCount>1?(Math.log(count) / Math.log(maxCount)*maxSize):1;
    }
    else if(formula == "unweighted") { // Unweighted
        return maxSize;                                          
    }else {  // Linear
        return (maxCount>0)?((count/maxCount)* maxSize):1 ; 
    }       
}

/** Returns the line length */
function getLineLength(record) {
//console.log("Default line length function");
    return getSetting(setting_linelength);
}

/** Calculates the line width that should be used */
function getLineWidth(count) {

    count = Number(count);
    var maxWidth = Number(getSetting(setting_linewidth));
    
    if(maxWidth>maxLinkWidth) {maxSize = maxLinkWidth;}
    else if(maxWidth<1) {maxSize = 1;}
    
//console.log('cnt='+count);    
    
    if(count > maxCountForLinks) {
        maxCountForLinks = count;
    }
    
    var val = (count==0)?0:executeFormula(count, maxCountForLinks, maxWidth);
    if(val<1) val = 1;
    return val;
}            

/** Calculates the marker width that should be used */
function getMarkerWidth(count) {
    if(isNaN(count)) count = 0;
    return 4 + getLineWidth(count)*2;
}

/** Calculates the entity raadius that should be used */
function getEntityRadius(count) {
    
    var maxRadius = getSetting(setting_entityradius);
    if(maxRadius>maxEntityRadius) {maxRadius = maxEntityRadius;}
    else if(maxRadius<1) {maxRadius = 1;}
    
    if(getSetting(setting_formula)=='unweighted'){
        return maxRadius;
    }else{
        if(count==0){
            return 0; //no records - no circle
        }else{
            
            if(count > maxCountForNodes) {
                maxCountForNodes = count;
            }
            
            var val = circleSize + executeFormula(count, maxCountForNodes, maxRadius);
            if(val<circleSize) val = circleSize;
            return val;
        }
    }
}

/***********************************START OF VISUALISATION FUNCIONS***********************************/
/** Visualizes the data */ 
var data; // Currently visualised dataset
var zoomBehaviour;
var force;


function getDataFromServer(){

    var url = window.hWin.HAPI4.baseURL+"hserver/controller/rectype_relations.php" + window.location.search;
    d3.json(url, function(error, json_data) {
        // Error check
        if(error) {
            return alert("Error loading JSON data: " + error.message);
        }
        
        settings.data = json_data; //all data
        filterData(json_data);
        
    });
    
}

function filterData(json_data) {
    
        if(!json_data) json_data = settings.data; 
    
        var names = [];
        $(".show-record").each(function() {
            var name = $(this).attr("name");
            if(!$(this).is(':checked')){ //to exclude
                names.push(name);
            }
        });    
        
        // Filter nodes
        var map = {};
        var size = 0;
        var nodes = json_data.nodes.filter(function(d, i) {
            if($.inArray(d.name, names) == -1) {
                map[i] = d;
                return true;
            }
            return false;
        });

        
//console.log(json_data.links);        
        
        // Filter links
        var links = [];
        json_data.links.filter(function(d) {
            if(map.hasOwnProperty(d.source) && map.hasOwnProperty(d.target)) {
                var link = {source: map[d.source], target: map[d.target], relation: d.relation, targetcount: d.targetcount};
                links.push(link);
            }
        })

        var data_visible = {nodes: nodes, links: links};
        settings.getData = function(all_data) { return data_visible; }; 
        visualizeData();
}

function visualizeData() {
    svg.selectAll("*").remove();
    addSelectionBox();
    //define shadow filter
    _addDropShadowFilter();
    
    
    /*Set up tooltip
    var tip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function (d) {
        return  d.name + "";
    })
    svg.call(tip);*/
    
    // SVG data  
    this.data = settings.getData.call(this, settings.data);
    determineMaxCount(data);
    //console.log("Data used for visualisation", data);

    // Container with zoom and force
    var container = addContainer();
    svg.call(zoomBehaviour); 
    force = addForce();

    // Lines 
    addMarkerDefinitions(); //markers/arrows on lines
    addLines("bottom-lines", getSetting(setting_linecolor), 0);
    
    addLines("top-lines", "rgba(255, 255, 255, 0.0)", 8.5); //tick transparent line to catch mouse over

/*    
svg.append("svg:defs").selectAll("marker")
    .data(data.links)
  .enter().append("svg:marker")
    .attr("id", 'marker')
    .attr("viewBox", "0 -5 10 10")
    .attr("refX", 5)
    .attr("markerWidth", 6)
    .attr("markerHeight", 6)
    .attr("orient", "auto")
  .append("svg:path")
    .attr("d", "M0,-5L10,0L0,5");    
var lines = svg.append("svg:g").selectAll("path")
    .data(data.links)
    .enter().append("svg:path")
    .attr("marker-mid", "url(#marker)")
    .style("stroke-width", 2)
    .attr("stroke", '#ff0000');    
  lines.attr("class", function(d) {
            return "bottom-lines link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
         });
*/    
    // Nodes
    addNodes();
    //addTitles();
    
    // Circles
    //addBackgroundCircles();
    //addForegroundCircles();
    //addIcons();
    
    // Labels
    if(getSetting(setting_labels) == "true") {
    // addLabels("shadow", "#000");
    // addLabels("namelabel", getSetting(setting_textcolor));
    }
    //DEBUG console.log("EVERYTHING HAS BEEN ADDED");
    /*
    // Get everything into positon when gravity is off.
    if(getSetting(setting_gravity) == "off") {
        force.stop();
        
        for(var i=0; i<10; i++) {
            force.tick();
        }
        
        force.tick();
        d3.selectAll(".node").attr("fixed", true);
        
    }else{
        force.start();
    }
    */
    
    /*var gravity = getSetting(setting_gravity);
    svg.selectAll(".node")
       .attr("fixed", function(d, i) {
            d.fixed = (gravity == "off");
            return d.fixed;
       }); 
    d.fixed = true;*/
    
    if(settings.isDatabaseStructure){
        
        var cnt_vis = data.nodes?data.nodes.length:0;
        var cnt_tot = (settings.data && settings.data.nodes)?settings.data.nodes.length:0;
        
        if(cnt_vis==0){
            sText = 'Select record types to show';
        }else{
            sText = 'Showing '+cnt_vis+' of '+cnt_tot;
        }
        
        $('#showRectypeSelector').button({label:sText, icons:{secondary:'ui-icon-carat-1-'
            +($('#list_rectypes').is(':visible')?'n':'s')   }}).css({'padding':'4px 2px'});
    }
    
    $('#btnZoomIn').button({icons:{primary:'ui-icon-plus'},text:false}).click(
    function(){
         zoomBtn(true);
    }
    );

    $('#btnZoomOut').button({icons:{primary:'ui-icon-minus'},text:false}).click(
    function(){
         zoomBtn(false);
    }
    );

    if(settings.isDatabaseStructure || isStandAlone){
        $('#embed-export').css('visibility','hidden');//hide();
    }else{
        $('#embed-export').button({icons:{primary:'ui-icon-globe'},text:false}).click(
        function(){
             showEmbedDialog();
        }
        );
    }
    
} //end visualizeData

/****************************************** CONTAINER **************************************/
/**
* Adds a <g> container to the SVG, which all other elements will get added to.
* The previous translateX, translateY and scale is re-used.
*/
function addContainer() {
    // Zoom settings
    var scale = getSetting(setting_scale);
    var translateX = getSetting(setting_translatex);
    var translateY = getSetting(setting_translatey);
    
    var s ='';
    if(isNaN(translateX) || isNaN(translateY) ||  translateX==null || translateY==null ||
        Math.abs(translateX)==Infinity || Math.abs(translateY)==Infinity){
        
        translateX = 1;
        translateY = 1;
    }
    s = "translate("+translateX+", "+translateY+")";    
    if(!(isNaN(scale) || scale==null || Math.abs(scale)==Infinity)){
        s = s + "scale("+scale+")";
    }
    
    //s = "translate(1,1)scale(1)";    
    
    // Append zoomable container       
    var container = svg.append("g")
                       .attr("id", "container")
                       .attr("transform", s);
    // Zoom behaviour                   
    this.zoomBehaviour = d3.behavior.zoom()
                           .translate([translateX, translateY])
                           .scale(scale)
                           .scaleExtent([0.05, 10]) //settings.isDatabaseStructure?[0.75,1.5]:
                           .on("zoom", zoomed);
                    
    return container;
}

/**
* Called after a zoom-event takes place.
*/
function zoomed() { 
    //keep current setting Translate   
    var translateXY = [];
    if(d3.event.translate !== undefined) {
        if(!isNaN(d3.event.translate[0])) {           
            putSetting(setting_translatex, d3.event.translate[0]); 
        }
        if(!isNaN(d3.event.translate[1])) {    
            putSetting(setting_translatey, d3.event.translate[1]);
        }
/*    
        if(prev_translate!=null && !isNaN(prev_translate[0]) && !isNaN(prev_translate[1])){
            translateXY[0] = prev_translate[0] + 
                        (d3.event.translate[0]==prev_translate[0])?0:Math.pow(d3.event.translate[0]-prev_translate[0], 0.5);
            translateXY[1] = prev_translate[1] + 
                        (d3.event.translate[1]==prev_translate[1])?0:Math.pow(d3.event.translate[1]-prev_translate[1], 0.5);
            prev_translate = [translateXY[0], translateXY[1]];
        }else{
            prev_translate = d3.event.translate;
            translateXY[0] = d3.event.translate[0];
            translateXY[1] = d3.event.translate[1];
        }
console.log('A '+translateXY[0]+','+translateXY[1]);        
console.log('B '+d3.event.translate);    
*/    
    }
    
    var scale = d3.event.scale; //Math.pow(d3.event.scale,0.75);
    
    //keep current setting Scale
    if(!isNaN(d3.event.scale)){
        putSetting(setting_scale, scale);
    }
   
    
//console.log( 'trans='+d3.event.translate );
//console.log( 'scale='+scale );
    
    // Transform  d3.event.translate  d3.event.translate
    var transform = "translate("+d3.event.translate
            //((d3.event.translate==undefined)?d3.event.translate
            //                            :(translateXY[0]+','+translateXY[1]))
                                        +")scale("+scale+")";
    onZoom(transform);
}  

function onZoom( transform ){
    d3.select("#container").attr("transform", transform);
    
    var scale = this.zoomBehaviour.scale();
    
    d3.selectAll(".bottom-lines").style("stroke-width", 
            function(d) { 
                var w = getLineWidth(d.targetcount); //width for scale 1
                return  (scale>1)?w:(w/scale);
            });
    d3.selectAll(".top-lines").style("stroke-width", 
            function(d) { 
                var w = getLineWidth(d.targetcount)+8.5; //width for scale 1
                return  (scale>1)?w:(w/scale);
            });

    updateOverlays();
}

//handle the zoom buttons
function zoomBtn(zoom_in){
    var zoom = this.zoomBehaviour; 
    
    var scale = zoom.scale(),
        extent = zoom.scaleExtent(),
        translate = zoom.translate(),
        x = translate[0], y = translate[1],
        factor = zoom_in ? 1.3 : 1/1.3,
        target_scale = scale * factor;

    // If we're already at an extent, done
    if (target_scale === extent[0] || target_scale === extent[1]) { return false; }
    // If the factor is too much, scale it down to reach the extent exactly
    var clamped_target_scale = Math.max(extent[0], Math.min(extent[1], target_scale));
    if (clamped_target_scale != target_scale){
        target_scale = clamped_target_scale;
        factor = target_scale / scale;
    }

    var width = $("#divSvg").width();
    var height = $("#divSvg").height();
    var center = [width / 2, height / 2];
    // Center each vector, stretch, then put back
    x = (x - center[0]) * factor + center[0];
    y = (y - center[1]) * factor + center[1];

    zoom.scale(target_scale)
        .translate([x,y]);    
   
    var transform = "translate(" + zoom.translate() + ")scale(" + zoom.scale() + ")";   
    onZoom(transform);
}

/********************************************* FORCE ***************************************/
/**
* Constructs a force layout
*/
function addForce() {
    var width = parseInt(svg.style("width"));
    var height = parseInt(svg.style("height"));
    var attraction = getSetting(setting_attraction);
    
    var force = d3.layout.force()
                  .nodes(d3.values(data.nodes))
                  .links(data.links)
                  .charge(attraction)        // Using the attraction setting
                  .linkDistance(function(d) {         
                     var linkDist = settings.getLineLength.call(this, d.target);
                     return linkDist;//linkDist;
                  })  // Using the linelength setting 
                  .on("tick", tick)
                  .size([width, height])
                  .start();
                  
    return force;
}  

/*************************************************** MARKERS ******************************************/
/**
* Adds marker definitions to a container
*/
function addMarkerDefinitions() {
    var linetype = getSetting(setting_linetype,'straight');
    var linelength = getSetting(setting_linelength);
    var markercolor = getSetting(setting_markercolor);
    
    
    
    var markers = d3.select("#container")
                    .append("defs")
                    .selectAll("marker")
                    .data(data.links)
                    .enter()
                    .append("svg:marker")    
                    .attr("id", function(d) {  //use this id to connect markers to the appropriate link
                        return "marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
                    })
                    .attr("markerWidth", function(d) {    
                        return getMarkerWidth(d.targetcount);             
                    })
                    .attr("markerHeight", function(d) {
                       return getMarkerWidth(d.targetcount);
                    })
                    .attr("refX", function(d) {
                        // Move markers to display a pointer on a straight line
                        if(linetype=="straight" && d.relation.pointer) {
                           return linelength*0.5;
                        }
                        return -1;
                    })
                    .attr("refY", 0)
                    .attr("viewBox", "0 -5 10 10")
                    .attr("markerUnits", "userSpaceOnUse")
                    .attr("orient", "auto")
                    .attr("fill", markercolor)// color of arrows on links (Using the markercolor setting)
                    //.attr("stroke", markercolor)
                    //.attr("stroke-width", 2)
                    .attr("opacity", "0.6")
                    .append("path")                
                    .attr("d",
                        function(d) { 
                            return d.relation.type=='resource' 
                                            ?'M0,-5 L10,0 L0,5' 
                                            :'M0,-5 L5,0 L0,5 M5,-5 L10,0 L5,5'});  //double arrow
                                            
     return markers;
/*     
CROSS  M 3,3 L 7,7 M 3,7 L 7,3

    { id: 0, name: 'circle', path: 'M 0, 0  m -5, 0  a 5,5 0 1,0 10,0  a 5,5 0 1,0 -10,0', viewbox: '-6 -6 12 12' }
  , { id: 1, name: 'square', path: 'M 0,0 m -5,-5 L 5,-5 L 5,5 L -5,5 Z', viewbox: '-5 -5 10 10' }
  , { id: 2, name: 'arrow', path: 'M 0,0 m -5,-5 L 5,0 L -5,5 Z', viewbox: '-5 -5 10 10' }
  , { id: 2, name: 'stub', path: 'M 0,0 m -1,-5 L 1,-5 L 1,5 L -1,5 Z', viewbox: '-1 -5 2 10' }     
*/  
}

/************************************ LINES **************************************/      
/**
* Constructs lines, either straight or curved based on the settings 
* @param name Extra class name 
*/
function addLines(name, color, thickness) {
    // Add the chosen lines [using the linetype setting]
    var lines;
    
    var linetype = getSetting(setting_linetype,'straight');
    
    if(true){ //getSetting(setting_linetype) != "straight"){//} == "curved") {
        // Add curved lines
         lines = d3.select("#container")
                   .append("svg:g")
                   .selectAll("path")
                   .data(data.links)
                   .enter()
                   .append("svg:path");
    }else{
        // Add straight lines
        lines = d3.select("#container")
                  .append("svg:g")
                  .selectAll("polyline.link")
                  .data(data.links)
                  .enter()
                  .append("svg:polyline");
    }    

    //var thickness = parseInt(getSetting(setting_linewidth))+1;
    var scale = this.zoomBehaviour.scale(); //current scale
    
    // Adding shared attributes
    lines.attr("class", function(d) {
            return name + " link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
         })
         .attr("stroke", color)
         .style("stroke-dasharray", (function(d) {
             if(d.targetcount == 0) {
                return "3, 3"; 
             } 
         })) 
         .style("stroke-width", function(d) { 
             var w = getLineWidth(d.targetcount)+thickness; //width for scale 1
             return  (scale>1)?w:(w/scale);
         });
         
    if(name=='bottom-lines' && linetype != "stepped"){
         lines.attr("marker-mid", function(d) {
            return "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")"; //reference to marker id
         });
    }
    
    if(name=='top-lines'){
         lines.on("mouseover", function(d) {
//console.log(d.relation.id);  //field type id           
             var selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             createOverlay(d3.event.offsetX, d3.event.offsetY, "relation", selector, getRelationOverlayData(d));  
         })
         .on("mouseout", function(d) {
             var selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             removeOverlay(selector, 0);
         });
    }
         
    return lines;
}

/**
* Updates the correct lines based on the linetype setting 
*/
function tick() {
    
    //not used anymore 
    var topLines = d3.selectAll(".top-lines"); 
    var bottomLines = d3.selectAll(".bottom-lines");
    
    var linetype = getSetting(setting_linetype,'straight');
    if(linetype == "curved") {
        updateCurvedLines(topLines);
        updateCurvedLines(bottomLines);     
    }else if(linetype == "stepped") {
        updateSteppedLines(topLines, 'top');
        updateSteppedLines(bottomLines,'bottom'); //with marker
    }else{
        updateStraightLines(topLines);
        updateStraightLines(bottomLines);   
    }
    
    // Update node locations
    updateNodes();
    
    // Update overlay
    updateOverlays(); 
}

/**
* Updates all curved lines
* @param lines Object holding curved lines
*/
function updateCurvedLines(lines) {
    
    var pairs = {};
    
    // Calculate the curved segments
    lines.attr("d", function(d) {
        
       var key = d.source.id+'_'+d.target.id; 
       if(!pairs[key]){
           pairs[key] = 1.5;
       }else{
           pairs[key] = pairs[key]+0.25;
       } 
       var k = pairs[d.source.id+'_'+d.target.id];
       
       var target_x = d.target.x,
           target_y = d.target.y;
       if(d.target.id==d.source.id){
           //link to itself
           target_x = d.source.x+100;
           target_y = d.source.y-100;
       }
        
        var dx = target_x - d.source.x,
            dy = target_y - d.source.y,
            dr = Math.sqrt(dx * dx + dy * dy)/k,
            mx = d.source.x + dx,
            my = d.source.y + dy;

       if(d.target.id==d.source.id){

            return [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              "A",dr,dr,0,0,1,target_x,target_y,
              "A",dr,dr,0,0,1,d.source.x,d.source.y
            ].join(" ");
           
       }else{
            
            return [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              "A",dr,dr,0,0,1,target_x,target_y
            ].join(" ");
       }
    });

}

/**
* Updates a straight line             
* @param lines Object holding straight lines
*/
function updateStraightLines(lines) {
    
    var pairs = {};
    
    $(".icon_self").each(function() {
            $(this).remove();
    });

    
    // Calculate the straight points
    lines.attr("d", function(d) {
        
       var key = d.source.id+'_'+d.target.id,
           indent = 30;
       
       if(pairs[d.target.id+'_'+d.source.id]){
           key = d.target.id+'_'+d.source.id;
       }else if(!pairs[key]){
           indent = 0;
       }
       
       if(indent>0){
           pairs[key] = pairs[key]+20;
       }else{
           pairs[key] = 1;
       } 
       var R = pairs[key];
       var pnt = [];
       var target_x = d.target.x,
           target_y = d.target.y;
           
       if(d.target.id==d.source.id){
           //link to itself
           target_x = d.source.x+100;
           target_y = d.source.y-100;
           
           var dx = target_x - d.source.x,
               dy = target_y - d.source.y,
               dr = Math.sqrt(dx * dx + dy * dy)/1.5,
               mx = d.source.x + dx,
               my = d.source.y + dy;
           
            pnt = [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              //"A",dr,dr,0,0,1,target_x,target_y,
              "L",d.source.x+50,d.source.y-50,
              "L",d.source.x,d.source.y
              //"A",dr,dr,0,0,1,d.source.x,d.source.y
            ];
           
       }else{
       
           var dx = (target_x-d.source.x)/2;
           var dy = (target_y-d.source.y)/2;

           var tg = (dx!=0)?Math.atan(dy/dx):0;
           
           var dx2 = dx-R*Math.sin(tg);
           var dy2 = dy+R*Math.cos(tg);
      
           pnt = [
                  "M", d.source.x, d.source.y,
                  "L", (d.source.x + dx-R*Math.sin(tg)), (d.source.y + dy+R*Math.cos(tg)),
                  "L", target_x, target_y ];
  
       }
       
       return pnt.join(' '); 
  
       //for polyline
       /*return d.source.x + "," + d.source.y + " " +
              //(d.source.x + dx/2-R*Math.sin(tg)) + "," + 
              //(d.source.y + dy/2+R*Math.cos(tg)) + " " +  
              (d.source.x + dx-R*Math.sin(tg)) + "," + 
              (d.source.y + dy+R*Math.cos(tg)) + " " +  
              target_x + "," + target_y;
       */
    });
    
}

function updateSteppedLines(lines, type){
    var pairs = {};
    
    
    $(".hidden_line_for_markers").remove();
    
    var mode = 0; //
    
    // Calculate the straight points
    lines.attr("d", function(d) {
        
       var dx = (d.target.x-d.source.x)/2,
           dy = (d.target.y-d.source.y)/2;
       
       var indent = ((Math.abs(dx)>Math.abs(dy))?dx:dy)/4;
       
       var key = d.source.id+'_'+d.target.id;
       if(pairs[d.target.id+'_'+d.source.id]){
           key = d.target.id+'_'+d.source.id;
       }else if(!pairs[key]){
           pairs[key] = 1-indent;
       }
       
       pairs[key] = pairs[key]+indent;
       var k = pairs[key];
       
       var target_x = d.target.x,
           target_y = d.target.y;
       var res = [];    
           
       if(d.target.id==d.source.id){
           //link to itself
           target_x = d.source.x+65;
           target_y = d.source.y-65;
           
           var dx = target_x - d.source.x,
               dy = target_y - d.source.y,
               dr = Math.sqrt(dx * dx + dy * dy)/1.5,
               mx = d.source.x + dx,
               my = d.source.y + dy;
           
            res = [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              //"A",dr,dr,0,0,1,target_x,target_y,
              "L",d.source.x+35,d.source.y-35,
              "L",d.source.x,d.source.y
              //"A",dr,dr,0,0,1,d.source.x,d.source.y
            ];
            
            if($.isFunction($(this).attr))
            $(this).attr("marker-mid", "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")");
           
       }else{  
      
           var dx2 = 45*(dx==0?0:((dx<0)?-1:1));
           var dy2 = 45*(dy==0?0:((dy<0)?-1:1));

           //path
           res = [
                  "M",d.source.x,d.source.y,
                  "L",(d.source.x + dx2),(d.source.y + dy2 ),
                  "L",(d.source.x + dx2 + dx + k),(d.source.y + dy2),
                  "L",(d.source.x + dx2 + dx + k),target_y,
                  "L",target_x,target_y
                ];
           
           
           if(type=='bottom'){
                //add 3 lines - specially for markers
                var g = d3.select("#container").append("svg:g").attr("class", "hidden_line_for_markers");
                
                var pnt = [
                  "M",(d.source.x + dx2),(d.source.y + dy2 ),
                  "L",(d.source.x + dx2 + dx/2 + k),(d.source.y + dy2)];
                
                g.append("svg:path")
                        .attr("d", pnt.join(' '))
                        //reference to marker id
                        .attr("marker-end", "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")");

                pnt = [
                  "M",(d.source.x + dx2 + dx + k), (d.source.y + dy2),
                  "L",(d.source.x + dx2 + dx + k), d.source.y + dy2 + (target_y - d.source.y - dy2)/2 ];
                        
                g.append("svg:path")
                        //.attr("class", "hidden_line_for_markers")
                        .attr("d", pnt.join(' '))
                        //reference to marker id
                        .attr("marker-end", "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")");

           }
           dx = dx + k;
           
       }
       
       return res.join(' ');      
    });
    
}

function updateSteppedLines_OLD(lines){
    var pairs = {};
    
    var mode = 0; //
    
    // Calculate the straight points
    lines.attr("points", function(d) {
        
       var dx = (d.target.x-d.source.x)/(mode==1?1:2),
           dy = (d.target.y-d.source.y)/(mode==1?1:2);
       
       var indent = ((Math.abs(dx)>Math.abs(dy))?dx:dy)/4;
       
       var key = d.source.id+'_'+d.target.id;
       if(pairs[d.target.id+'_'+d.source.id]){
           key = d.target.id+'_'+d.source.id;
       }else if(!pairs[key]){
           pairs[key] = 1-indent;
       }
       
       pairs[key] = pairs[key]+indent;
       var k = pairs[key];
       
       var target_x = d.target.x,
           target_y = d.target.y;
       if(d.target.id==d.source.id){
           //link to itself
           target_x = d.source.x+100;
           target_y = d.source.y-100;
       }
       
       var res = [d.source.x + "," + d.source.y];
       
       if(Math.abs(dx)>Math.abs(dy)) {
           dx = dx + k;
           res.push((d.source.x + dx) + ',' + (d.source.y));
           res.push((d.source.x + dx) + ',' + (target_y));
       }else{
           dy = dy + k;
           res.push((d.source.x) + ',' + (d.source.y+dy));
           res.push((target_x) + ',' + (d.source.y+dy));
       }

       res.push(target_x + "," + target_y);
       if(d.target.id==d.source.id){
           res.push((d.source.x - dx) + ',' + target_y);
           res.push(d.source.x  + ',' + d.source.y);
       }
       
       return res.join(' ');
    });
    
}


/************************************************** NODE CHILDREN ********************************************/
/**
* Adds <title> elements to all nodes 
*/
function addTitles() {
    var titles = d3.selectAll(".node")
                   .append("title")
                   .text(function(d) {
                        return d.name;
                   });
    return titles;
}

/**
* Adds background <circle> elements to all nodes
* These circles can be styled in the settings bar
*/
function addBackgroundCircles() {
    var entitycolor = getSetting(setting_entitycolor);
    var circles = d3.selectAll(".node")
                    .append("circle")
                    .attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
                    .attr("class", "background")
                    .attr("fill", entitycolor);
    return circles;
}

/**
* Adds foreground <circle> elements to all nodes
* These circles are white
*/
function addForegroundCircles() {
    //var circleSize = getSetting(setting_circlesize);
    var circles = d3.selectAll(".node")
                    .append("circle")
                    .attr("r", circleSize)
                    .attr("class", 'foreground')
                    .style("stroke", "#ddd")
                    .style("stroke-opacity", function(d) {
                        if(d.selected == true) {
                            return 1;
                        }
                        return .25;
                    });
    return circles;
}

/**
* Adds icon <img> elements to all nodes
* The image is based on the "image" attribute
*/
function addIcons() {
    var icons = d3.selectAll(".node")
                  .append("svg:image")
                  .attr("class", "icon")
                  .attr("xlink:href", function(d) {
                       return d.image;
                  })
                  .attr("x", iconSize/-2)
                  .attr("y", iconSize/-2)
                  .attr("height", iconSize)
                  .attr("width", iconSize);  
                  
    return icons;
}

/**
* Adds <text> elements to all nodes
* The text is based on the "name" attribute
* 
*/
function addLabels(name, color) {
    var maxLength = getSetting(setting_textlength);
    var labels = d3.selectAll(".node")
                  .append("text")
                  .attr("x", iconSize)
                  .attr("y", iconSize/4)
                  .attr("class", name + " bold")
                  .attr("fill", color)
                  .style("font-size", fontsize, "important")
                  .text(function(d) {
                      return truncateText(d.name, maxLength);
                  });
    return labels;
}

//
//
//
function showEmbedDialog(){

    var query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
    query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    var url = window.hWin.HAPI4.baseURL+'hclient/framecontent/visualize/springDiagram.php' + query;

    //document.getElementById("linkTimeline").href = url;

    document.getElementById("code-textbox").value = '<iframe src=\'' + url +
    '\' width="800" height="650" frameborder="0"></iframe>';

    //document.getElementById("linkKml").href = url_kml;

    //encode
    query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
    query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    url = window.hWin.HAPI4.baseURL+'hclient/framecontent/visualize/springDiagram.php' + query;
    document.getElementById("code-textbox2").value = '<iframe src=\'' + url +
    '\' width="800" height="650" frameborder="0"></iframe>';
    
    
    
    var $dlg = $("#embed-dialog");

    $dlg.dialog({
        autoOpen: true,
        height: 320,
        width: 700,
        modal: true,
        resizable: false,
        title: window.hWin.HR('Publish Network Diagram')
    });
}            
