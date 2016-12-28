
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
            triggerSelection: function(selection){}, 
            
            isDatabaseStructure: false,
            
            showCounts: true,
            
            // Limits tha maximum results whichg can be plotted to avoid overloading the browser - user will get warning message
            limit: 2000,
            
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
            
            gravity: "touch",
            attraction: -3000,
            
            translatex: 0,
            translatey: 0,
            scale: 1
        }, options );
 
        // Handle settings (settings.js)
        checkStoredSettings();
        handleSettingsInUI();

        // Check visualisation limit
        var amount = Object.keys(settings.data.nodes).length;
        if(amount > settings.limit) {
             $("#d3svg").html('<text x="25" y="25" fill="black">Sorry, the visualisation limit is set to ' +settings.limit+ ' records to avoid overloading the browser. You are trying to visualize ' +amount+ ' records. Please refine your filter to reduce the number of results.</text>');  
             return; 
        }else{
            visualizeData();    
        }
 
        return this;
    };
}( jQuery ));
    

/*******************************START OF VISUALISATION HELPER FUNCTIONS*******************************/
var maxCount; 
function determineMaxCount(data) {
    maxCount = 1;
    if(data && data.nodes.length > 0) {
        for(var i = 0; i < data.nodes.length; i++) {
            //console.log(data.nodes[i]);
            if(data.nodes[i].count > maxCount) {
                maxCount = data.nodes[i].count;
            } 
        }
    }
    //console.log("Max count: " + maxCount);
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

function executeFormula(count, maxSize) {
    // Avoid minus infinity and wrong calculations etc.
    if(count <= 0) {
        count = 1;
    }
    
    if(count > maxCount) {
        maxCount = count;
    }
    
    //console.log("Count: " + count + ", max count: " + maxCount + ", max Size: " + maxSize);
    var formula = getSetting(setting_formula);
    if(formula == "logarithmic") { // Log                                                           
        return maxCount>1?(Math.log(count) / Math.log(maxCount)*maxSize):1;
    }
    else if(formula == "unweighted") { // Unweighted
        return 2;                                          
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
    //old way var maxWidth = getSetting(setting_linewidth);                                                                     
    //old way return 1.5 + (executeFormula(count, maxWidth) / 2);

    if(getSetting(setting_formula)=='unweighted'){
        return getSetting(setting_linewidth);
    }else{
        var val = (count==0)?0:executeFormula(count, maxLinkWidth);
        if(val<1) val = 1;
        return val;
    }
    
}            

/** Calculates the marker width that should be used */
function getMarkerWidth(count) {
    if(isNaN(count)) count = 0;
    return 4 + getLineWidth(count)*2;
}

/** Calculates the entity raadius that should be used */
function getEntityRadius(count) {
    if(getSetting(setting_formula)=='unweighted'){
        return getSetting(setting_entityradius);
    }else{
        if(count==0){
            return 0; //no records - no circle
        }else{
            var val = circleSize + executeFormula(count, maxEntityRadius);
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
    addLines("bottom-lines", getSetting(setting_linecolor), 0.5);
    //addLines("top-lines", "rgba(255, 255, 255, 0.0)", 8.5);

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
            +($('#list_rectypes').is(':visible')?'n':'s')   }});
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
    if(isNaN(translateX) || isNaN(translateY) || 
        Math.abs(translateX)==Infinity || Math.abs(translateY)==Infinity){
        
        translateX = 1;
        translateY = 1;
    }
    s = "translate("+translateX+", "+translateY+")";    
    if(!isNaN(scale)){
        s = s + "scale("+scale+")";
    }
    
    // Append zoomable container       
    var container = svg.append("g")
                       .attr("id", "container")
                       .attr("transform", s);
                       
    // Zoom behaviour                   
    this.zoomBehaviour = d3.behavior.zoom()
                           .translate([translateX, translateY])
                           .scale(scale)
                           .scaleExtent(settings.isDatabaseStructure?[0.75,1.5]:[0.05, 10]) 
                           .on("zoom", zoomed);
                      
    return container;
}

/**
* Called after a zoom-event takes place.
*/
function zoomed() { 
    //keep current setting Translate   
    if(d3.event.translate !== undefined) {
        if(!isNaN(d3.event.translate[0])) {           
            putSetting(setting_translatex, d3.event.translate[0]); 
        }
        if(!isNaN(d3.event.translate[1])) {    
            putSetting(setting_translatey, d3.event.translate[1]);
        }
    }
    
    //keep current setting Scale
    if(!isNaN(d3.event.scale)) {
        putSetting(setting_scale, d3.event.scale);
    }   
    
    // Transform  
    var transform = "translate("+d3.event.translate+")scale("+d3.event.scale+")"; 
    d3.select("#container").attr("transform", transform);
    
    //d3.selectAll(".node").attr('transform',"translate("+d3.event.translate+")scale(1)");
    //d3.selectAll("path").attr("transform", transform); //"scale("+d3.event.scale+")");
    //updateNodes();

    updateOverlays();
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
    var linetype = getSetting(setting_linetype);
    var linelength = getSetting(setting_linelength);
    var markercolor = getSetting(setting_markercolor);
    
    
    var markers = d3.select("#container")
                    .append("defs")
                    .selectAll("marker")
                    .data(data.links)
                    .enter()
                    .append("svg:marker")    
                    .attr("id", function(d) {
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
                           return linelength*-0.2;
                        }
                        return -1;
                    })
                    .attr("refY", 0)
                    .attr("viewBox", "0 -5 10 10")
                    .attr("markerUnits", "userSpaceOnUse")
                    .attr("orient", "auto")
                    .attr("fill", markercolor) // color of arrows on links (Using the markercolor setting)
                    .attr("opacity", "0.6")
                    .append("path")                                                      
                    .attr("d", "M0,-5L10,0L0,5");
     return markers;
}

/************************************ LINES **************************************/      
/**
* Constructs lines, either straight or curved based on the settings 
* @param name Extra class name 
*/
function addLines(name, color, thickness) {
    // Add the chosen lines [using the linetype setting]
    var lines;
    
    if(getSetting(setting_linetype) == "curved") {
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

    var thickness = parseInt(getSetting(setting_linewidth))+1;

    // Adding shared attributes
    lines.attr("class", function(d) {
            return name + " link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
         }) 
         .attr("marker-mid", function(d) {
            return "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")";
         })
         .attr("stroke", color)
         .style("stroke-dasharray", (function(d) {
             if(d.targetcount == 0) {
                return "3, 3"; 
             } 
         })) 
         
         
         .style("stroke-width", function(d) { return getLineWidth(d.targetcount); })
         .on("click", function(d) {
             // Close all overlays and create a line overlay
             /* 2016-12-15 it works, however we do not need info for links anymore
             removeOverlays();
             var selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             createOverlay(d3.event.offsetX, d3.event.offsetY, "relation", selector, getRelationOverlayData(d));  
             */
         });
         
    return lines;
}

/**
* Updates the correct lines based on the linetype setting 
*/
function tick() {
    
    //not used anymore var topLines = d3.selectAll(".top-lines"); 
    var bottomLines = d3.selectAll(".bottom-lines");
    
    var linetype = getSetting(setting_linetype);
    if(linetype == "curved") {
        //updateCurvedLines(topLines);
        updateCurvedLines(bottomLines);     
    }else{
        //updateStraightLines(topLines);
        updateStraightLines(bottomLines);   
    }
    
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
        
        var dx = d.target.x - d.source.x,
            dy = d.target.y - d.source.y,
            dr = Math.sqrt(dx * dx + dy * dy)/k,
            mx = d.source.x + dx,
            my = d.source.y + dy;
            
        return [
          "M",d.source.x,d.source.y,
          "A",dr,dr,0,0,1,mx,my,
          "A",dr,dr,0,0,1,d.target.x,d.target.y
        ].join(" ");
    });

    // Update node locations
    updateNodes();
}

/**
* Updates a straight line             
* @param lines Object holding straight lines
*/
function updateStraightLines(lines) {
    
    var pairs = {};
    
    // Calculate the straight points
    lines.attr("points", function(d) {
        
       var key = d.source.id+'_'+d.target.id; 
       if(!pairs[key]){
           pairs[key] = 1;
       }else{
           pairs[key] = pairs[key]+30;
       } 
       var k = pairs[d.source.id+'_'+d.target.id];
        
       return d.source.x + "," + d.source.y + " " +
              (d.source.x + (d.target.x-d.source.x)/2+k) + "," + 
              (d.source.y + (d.target.y-d.source.y)/2-k) + " " +  
              d.target.x + "," + d.target.y;
    });
    
    // Update node locations
    updateNodes();
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
