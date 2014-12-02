/**
* Visualisation plugin
* Requires:
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
* Available settings:
* - color
* - backgroundColor
* - query
* etc.
* 

* 
* 
* 
*/

var settings;   // Plugin settings object
var svg;        // The SVG where the visualisation will be executed on
(function ( $ ) {
    /**
    * jQuery plugin hook, this is where the magic happens
    * @param options Custom options given when this function is called
    */
    $.fn.visualize = function( options ) {
        console.log("INSIDE VISUALIZE FUNCTION");
        svg = d3.select("#d3svg");
        
        // Default plugin settings
        settings = $.extend({
            // Custom functions
            getData: $.noop(),
            getLineLength: function() { return getSetting(setting_linelength); },
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
            linetype: "straight",
            linelength: 100,
            linewidth: 15,
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
            attraction: -700,
            
            translatex: 0,
            translatey: 0,
            scale: 1
        }, options );
 
        console.log("CALLING FUNCTIONS");
        // Handle settings
        checkStoredSettings();
        handleSettingsInUI();

        // Transform
        console.log("CALLING VISUALIZE DATA");
        visualizeData();
 
        return this;
    };
}( jQuery ));
    
    
    
/*********************************** START OF SETTING FUNCTIONS **************************************/
/** SETTING NAMES */
var setting_linetype      = "setting_linetype";
var setting_linelength    = "setting_linelength";
var setting_linewidth     = "setting_linewidth";
var setting_linecolor     = "setting_linecolor";
var setting_markercolor   = "setting_markercolor";
var setting_entityradius  = "setting_entityradius";
var setting_entitycolor   = "setting_entitycolor";
var setting_labels        = "setting_labels";
var setting_fontsize      = "setting_fontsize";
var setting_textlength    = "setting_textlength";
var setting_textcolor     = "setting_textcolor";
var setting_formula       = "setting_formula";
var setting_gravity       = "setting_gravity";
var setting_attraction    = "setting_attraction";
var setting_fisheye       = "setting_fisheye";
var setting_translatex    = "setting_translatex";
var setting_translatey    = "setting_translatey";
var setting_scale         = "setting_scale";  

/**
* Returns a URL parameter
* @param name Name of the parameter
*/
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

/**
* Returns the database name from the search query string
*/
function getDatabaseName() {
    return getParameterByName("db");
}

/**
* Returns the current displayed URL
* 
*/
function getURL() {
    return window.location.href; 
}

/**
 * Returns a setting from the localStorage
 * @param setting The setting to retrieve
 */
function getSetting(setting) {
    return localStorage.getItem(getURL()+setting);
}

/**
* Stores a value in the localStorage
*/
function putSetting(key, value) {
    localStorage.setItem(getURL()+key, value);
}

/**
* Checks if a setting has been set, if not it sets the default value
* @param key    Localstorage key
* @param value  The default value
*/
function checkSetting(key, value) {
    var obj = getSetting(key);
    if(obj === null) {
        putSetting(key, value);
    }
}

/**
 * This function makes sure the default settings are stored in the localStorage.
 * @param settings The plugin settings object
 */
function checkStoredSettings() {
    checkSetting(   setting_linetype,      settings.linetype    );
    checkSetting(   setting_linelength,    settings.linelength  );
    checkSetting(   setting_linewidth,     settings.linewidth   );
    checkSetting(   setting_linecolor,     settings.linecolor   );
    checkSetting(   setting_markercolor,   settings.markercolor );
    checkSetting(   setting_entityradius,  settings.entityradius);
    checkSetting(   setting_entitycolor,   settings.entitycolor );
    checkSetting(   setting_labels,        settings.labels      );
    checkSetting(   setting_fontsize,      settings.fontsize    );
    checkSetting(   setting_textlength,    settings.textlength  );
    checkSetting(   setting_textcolor,     settings.textcolor   );
    checkSetting(   setting_formula,       settings.formula     );
    checkSetting(   setting_gravity,       settings.gravity     );
    checkSetting(   setting_attraction,    settings.attraction  );
    checkSetting(   setting_fisheye,       settings.fisheye     );
    checkSetting(   setting_translatex,    settings.translatex  );
    checkSetting(   setting_translatey,    settings.translatey  );
    checkSetting(   setting_scale,         settings.scale       );
}

/**
* This function sets the settings in the UI
*/
function handleSettingsInUI() {
    // LINE SETTINGS
    if(settings.showLineSettings) {
        /** LINE TYPE SETTING */
        if(settings.showLineType) {
            // Set line type setting in UI
            $("#linetype option[value='" +getSetting(setting_linetype)+ "']").attr("selected", true);
            
            // Listens to linetype selection changes
            $("#linetype").change(function(e) {
                putSetting(setting_linetype, $("#linetype").val());
                visualizeData();
            });
        }else{
            $("#linetypeContainer").remove();
        }
        
        /** LINE LENGTH SETTING */
        if(settings.showLineLength) {
            // Set line length setting in UI
            $("#linelength").val(getSetting(setting_linelength));
            
            // Listen to line length changes
            $("#linelength").change(function() {
                putSetting(setting_linelength, $(this).val());
                visualizeData();
            });
        }else{
            $("#linelengthContainer").remove();
        }
        
        /** LINE WIDTH SETTING */
        if(settings.showLineWidth) {
            // Set line width setting in UI
            $("#linewidth").val(getSetting(setting_linewidth));
            
            // Listen to line width changes
            $("#linewidth").change(function() {
                putSetting(setting_linewidth, $(this).val());
                visualizeData();
            });
        }else{
            $("#linewidthContainer").remove();
        }
        
        /** LINE COLOR SETTING */
        if(settings.showLineColor) {
            // Set line color setting in UI
            $("#linecolor").css("background-color", getSetting(setting_linecolor));

            // Listen to 'line color' selection changes
            $('#linecolor').colpick({
                layout: 'hex',
                onSubmit: function(hsb, hex, rgb, el) {
                    var color = "#"+hex; 
                    
                    putSetting(setting_linecolor, color);
                    $(".link").attr("stroke", color);
            
                    $(el).css('background-color', color);
                    $(el).colpickHide();
                }
            });
        }else{
            $("#linecolorContainer").remove();
        }
        
        /** MARKER COLOR SETTING */
        if(settings.showMarkerColor) {
            // Set marker color in UI
            $("#markercolor").css("background-color", getSetting(setting_markercolor));
            
            // Listen to 'marker color' selection changes
            $('#markercolor').colpick({
                layout: 'hex',
                onSubmit: function(hsb, hex, rgb, el) {
                    var color = "#"+hex; 
                    
                    putSetting(setting_markercolor, color);
                    $("marker").attr("fill", color);
                    
                    $(el).css('background-color', color);
                    $(el).colpickHide();
                }
            });
        }else{
            $("#markercolorContainer").remove();
        }
    }else{
        $("#lineSettings").remove();
    }
    
    // ENTITY SETTINGS
    if(settings.showEntitySettings) {
        /**  MAX RADIUS SETTING */
        if(settings.showEntityRadius) {
            // Set entity radius setting in UI
            $("#entityradius").val(getSetting(setting_entityradius));
            
            // Listen to line width changes
            $("#entityradius").change(function() {
                putSetting(setting_entityradius, $(this).val());
                visualizeData();
            });
        }else{
            $("#entityradiusContainer").remove();
        }
        
        /** COUNT COLOR SETTING */
        if(settings.showEntityColor) {
            // Set count color in UI
            $("#entitycolor").css("background-color", getSetting(setting_entitycolor));

            // Listen to 'count color' selection changes
            $('#entitycolor').colpick({
                layout: 'hex',
                onSubmit: function(hsb, hex, rgb, el) {
                    var color = "#"+hex; 
                    
                    putSetting(setting_entitycolor, color);
                    $(".background").attr("fill", color);
                    
                    $(el).css('background-color', color);
                    $(el).colpickHide();
                }
            });
        }else{
            $("#entitycolorSettings").remove();
        }
    }else{
        $("#entitySettings").remove();
    }
    
    // LABEL SETTINGS
    if(settings.showTextSettings) {
        /** Labels visible */
        if(settings.showLabels) {
            // Set checkbox value
            if(getSetting(setting_labels) == "false") {
                $("#labelCheckBox").prop("checked", false);
            }
            
            // Listen to changes
            $("#labelCheckBox").change(function(e) {
                putSetting(setting_labels, $(this).is(':checked'));
                visualizeData();
            });
        }else{
            $("#labelCheckBox").remove();
        }
        
        /** TEXT FONT SIZE SETTING */
        if(settings.showFontSize) {
            // Set font size setting in UI
            $("#fontsize").val(parseInt(getSetting(setting_fontsize)));
            
            // Listen to font size changes
            $("#fontsize").change(function() {
                putSetting(setting_fontsize, $(this).val()+"px");
                $(".node text").css("font-size", getSetting(setting_fontsize), "important");
                $(".node text").each(function() {
                    this.style.setProperty("font-size", getSetting(setting_fontsize), "important"); 
                });
          
            });
        }else{
            $("#fontsizeContainer").remove();
        }
        
        /** TEXT LENGTH SETTING */
        if(settings.showTextLength) {
            // Set text length in UI
            $("#textlength").val(getSetting(setting_textlength));
            
            // Listen to text length changes
            $("#textlength").change(function() {
                putSetting(setting_textlength, $(this).val());
                visualizeData();
            });
        }else{
            $("#textlengthContainer").remove();
        }
        
        /** TEXT COLOR SETTING */
        if(settings.showTextColor) {
            // Set text color in UI
            $("#textcolor").css("background-color", getSetting(setting_textcolor));

            // Listen to 'count color' selection changes
            $('#textcolor').colpick({
                layout: 'hex',
                onSubmit: function(hsb, hex, rgb, el) {
                    var color = "#"+hex; 
                    
                    putSetting(setting_textcolor, color);
                    $(".namelabel").attr("fill", color);
                    
                    $(el).css('background-color', color);
                    $(el).colpickHide();
                }
            });
        }else{
            $("#textcolorContainer").remove();
        }
    }else{
        $("#textSettings").remove();
    }
    
    // TRANSFORM SETTINGS
    if(settings.showTransformSettings) {
        /** LINE LENGTH SETTING */
        if(settings.showFormula) {
            // Set formula setting in UI
            $("#formula option[value='" +getSetting(setting_formula)+ "']").attr("selected", true); 

            // Listen to formula changes
            $("#formula").change(function() {
                putSetting(setting_formula, $(this).val());
                visualizeData();
            });
        }else{
            $("#formulaContainer").remove();
        }

        /** FISH EYE */
        if(settings.showFishEye) {
            // Set fish eye setting in UI
            if(getSetting(setting_fisheye) === "true") {
                $("#fisheye").prop("checked", true);
            }
            
            // Listen to fisheye changes
            $("#fisheye").change(function(e) {
                putSetting(setting_fisheye, $(this).is(':checked'));
                visualizeData();
            });
        }else{
            $("#fisheyeContainer").remove();
        }
    }else{
        $("#transformSettings").remove();
    }
    
    // GRAVITY SETTINGS
    if(settings.showGravitySettings) {
        /** GRAVITY SETTING */
        if(settings.showGravity) {
            // Set gravity setting in UI
            $("#gravity option[value='" +getSetting(setting_gravity)+ "']").attr("selected", true);

            // Listen to gravity changes
            $("#gravity").change(function() {
                putSetting(setting_gravity,  $(this).val());
                visualizeData();
            });
        }else{
            $("#gravityContainer").remove();
        }
        
        /** ATTRACTION SETTING */
        if(settings.showAttraction) {
            // Set attraction setting in UI
            $("#attraction").val(getSetting(setting_attraction));
            
            // Listen to attraction changes
            $("#attraction").change(function() {
                putSetting(setting_attraction, $(this).val());
                visualizeData();
            });
        }else{
            $("#attractionContainer").remove();
        }
    }else{
        $("#gravitySettings").remove();
    }
}
/************************************ END OF SETTING FUNCTIONS ***************************************/



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
}

/** Calculates log base 10 */
function log10(val) {
    return Math.log(val) / Math.LN10;
}

/** Executes the chosen formula with a chosen count & max size */

function executeFormula(count, maxSize) {
    // Avoid minus infinity and wrong calculations etc.
    if(count <= 0) {
        count = 1;
    }
    
    //console.log("Count: " + count + ", max count: " + maxCount + ", max Size: " + maxSize);
    var formula = getSetting(setting_formula);
    if(formula == "logarithmic") { // Log                                                           
        return Math.log(count) / Math.log(maxCount) * maxSize;
    }
    else if(formula == "unweighted") { // Unweighted
        return 2;                                          
    }else {  // Linear
        return (count/maxCount) * maxSize; 
    }       
}

/** Returns the line length */
function getLineLength(record) {
    console.log("Default line length function");
    return getSetting(setting_linelength);
}

/** Calculates the line width that should be used */
function getLineWidth(count) {
    var maxWidth = getSetting(setting_linewidth);                                                                     
    return 1.5 + (executeFormula(count, maxWidth) / 2);
}            

/** Calculates the marker width that should be used */
function getMarkerWidth(count) {
    return 4 + getLineWidth(count)*2;
}

/** Calculates the entity raadius that should be used */
var iconSize = 16; // The icon size
var circleSize = iconSize * 0.75; // Circle around icon size
function getEntityRadius(count) {
    var maxRadius = getSetting(setting_entityradius);
    return circleSize + executeFormula(count, maxRadius) - 1;
}

/***********************************START OF VISUALISATION FUNCIONS***********************************/
/** Visualizes the data */ 
function visualizeData() {
    // SVG data  
    var width = parseInt(svg.style("width"));
    var height = parseInt(svg.style("height"));
    svg.selectAll("*").remove();
    
    // Record data
    var data = settings.getData.call(this, settings.data);
    console.log("RECORD DATA");
    console.log(data); 
      
    // Limit check
    if(Object.keys(data.nodes).length > 2000) {
        svg.selectAll("text")
           .data(["Sorry, there are too many records to render.", "The limit is set at 2000 records."])
           .enter()
           .append("text")
           .text(function(d) {
               return d;
           })
           .attr("x", width/2-100)
           .attr("y", function(d,i) {
               return height/2 + i*15;
           });             
        return false;
    }    
   
    // Line settings
    var linetype = getSetting(setting_linetype);
    var linelength = getSetting(setting_linelength);
    var linecolor = getSetting(setting_linecolor);
    var markercolor = getSetting(setting_markercolor);
    
    // Entity
    var entitycolor = getSetting(setting_entitycolor);
    
    // Text
    var fontsize = getSetting(setting_fontsize);
    var textcolor = getSetting(setting_textcolor);

    // Gravity settings
    var gravity = getSetting(setting_gravity);
    var attraction = getSetting(setting_attraction);
    var fisheyeEnabled = getSetting(setting_fisheye);
    
    // Zoom settings
    var scale = getSetting(setting_scale);
    var translateX = getSetting(setting_translatex);
    var translateY = getSetting(setting_translatey);
                                                    
    // Append zoomable container       
    var container = svg.append("g")
                       .attr("id", "the-container");    
    
    // Adding zoom
    var zoomBehaviour = d3.behavior.zoom()
                          .scale(scale)
                          /*.translate(function(d) {
                              return [5,5];
                          }) */
                          .scaleExtent([0.1, 5])
                          .on("zoom", zoomed);
     
    /** Updates the container after a zoom event */             
    function zoomed() {      
        // Translate
        //console.log(d3.event.translate);   
        if(d3.event.translate !== undefined) {
            if(!isNaN(d3.event.translate[0])) {           
                putSetting(setting_translatex, d3.event.translate[0]);
            }
            if(!isNaN(d3.event.translate[1])) {    
                putSetting(setting_translatey, d3.event.translate[1]);
            }
        }
        // Scale
        //console.log(d3.event.scale);
        if(!isNaN(d3.event.scale)) {
            putSetting(setting_scale, d3.event.scale);
        }   
        // Transform         
        var transform = "translate("+d3.event.translate+")scale("+d3.event.scale+")";
        //console.log("ZOOMED --> " + transform);
        container.attr("transform", transform);
        updateOverlays();           
    }  
    svg.call(zoomBehaviour); 

    // Creating D3 force
    determineMaxCount(data);
    var force = d3.layout.force()
                         .nodes(d3.values(data.nodes))
                         .links(data.links)
                         .charge(attraction)        // Using the attraction setting
                         .linkDistance(function(d) {         
                             return settings.getLineLength.call(this, d.target);
                         })  // Using the linelength setting 
                         .on("tick", function(d, i) {
                             // Determine what function to call on force.drag
                             if(linetype == "curved") { 
                                 return curvedTick();
                             }else{
                                 return straightTick();
                             }
                         })
                         .size([width, height])
                         .start();  
                   
    // Adding marker definitions
    container.append("defs")
             .selectAll("marker")
             .data(force.links())
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
             .attr("fill", markercolor) // Using the markercolor setting
             .attr("opacity", "0.6")
             .append("path")                                                      
             .attr("d", "M0,-5L10,0L0,5");
    
    /** Add lines */        
    function addLines() {
        // Add the chosen lines [using the linetype setting]  
        var lines;
        if(linetype == "curved") {
            // Add curved lines
             lines = container.append("svg:g")
                              .selectAll("path")
                              .data(force.links())
                              .enter()
                              .append("svg:path");
        }else{
            // Add straight lines
            lines = container.append("svg:g")
                             .selectAll("polyline.link")
                             .data(force.links())
                             .enter()
                             .append("svg:polyline");
        }    
         
        // Adding shared attributes
        lines.attr("class", function(d) {
                return "link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             }) 
             .attr("marker-mid", function(d) {
                return "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")";
             })
             .style("stroke-dasharray", (function(d) {
                 if(d.targetcount == 0) {
                    return "3, 3"; 
                 } 
             })) 
             .on("click", function(d) {
                 // Close all overlays and create a line overlay
                 removeOverlays();
                 var selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
                 createOverlay(d3.event.offsetX, d3.event.offsetY, "relation", selector, getRelationOverlayData(d));  
                 
             });
             
        return lines;
    }
    
    // Bottom lines
    var bottomLines = addLines();
    bottomLines.attr("stroke", linecolor)
               .style("stroke-width", function(d) { 
                    return 0.5 + getLineWidth(d.targetcount);
               });
    
    // Top lines
    var topLines = addLines();
    topLines.attr("stroke", "rgba(255, 255, 255, 0.0)")
            .style("stroke-width", function(d) { 
                   return 8.5 + getLineWidth(d.targetcount);
            });
    
    // Check what methods to call on drag
    var node_drag = d3.behavior.drag()
                               .on("dragstart", dragstart)
                               .on("drag", dragmove)
                               .on("dragend", dragend);
                           
    /** Called when a dragging event starts */
    function dragstart(d, i) {
        d3.event.sourceEvent.stopPropagation();
            
        if(gravity !== "aggressive") {    
            force.stop() // Stop force from auto positioning
            if(gravity === "touch") {
                svg.selectAll(".node").attr("fixed", function(d, i) {
                    d.fixed = false;
                    return false;
                });
            }
        }
    }

    /** Caled when a dragging move event occurs */
    function dragmove(d, i) {  
        // Update locations
        d.px += d3.event.dx;
        d.py += d3.event.dy;
        d.x += d3.event.dx;
        d.y += d3.event.dy;
        
        // Update the location in localstorage
        var record = localStorage.getItem(d.id);
        var obj;
        if(record === null) {
            obj = {}; 
        }else{
            obj = JSON.parse(record);
        }  
        
        // Set attributes 'x' and 'y' and store object
        obj.px = d.px;
        obj.py = d.py;
        obj.x = d.x;
        obj.y = d.y;
        putSetting(d.id, JSON.stringify(obj));
    
        // Update nodes & lines                                                           
        if(linetype == "curved") { 
             curvedTick();
        }else{
             straightTick();
        }
        
        // Update overlay
        updateOverlays();
    }
    
    /** Called when a dragging event ends */
    function dragend(d, i) {
         if(gravity !== "aggressive") { 
            d.fixed = true; // Node may not be auto positioned
        }
        
        // Update nodes & lines 
        if(linetype == "curved") { 
            curvedTick();
        }else{
            straightTick();
        } 
        
        // Check if force may resume
        if(gravity !== "off") {    
            force.resume(); 
        } 
    }
         
    // Defining the nodes
    var node = container.selectAll(".node")
                  .data(force.nodes())
                  .enter().append("g")
                  .attr("class", function(d) {
                      return "node id" + d.id;
                  }) 
                  .attr("transform", "translate(100, 100)")
                  .attr("x", function(d) {
                      // Setting 'x' based on localstorage
                      var record = localStorage.getItem(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("x" in obj) { // Check if attribute is valid
                              d.x = obj.x;
                              return obj.x;
                          }
                      }    
                  })
                  .attr("y", function(d) {
                      // Setting 'y' based on localstorage
                      var record = localStorage.getItem(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("y" in obj) { // Check if attribute is valid
                              d.y = obj.y;
                              return obj.y;  
                          }
                      }      
                  })
                  .attr("px", function(d) {
                      // Setting 'px' based on localstorage
                      var record = localStorage.getItem(d.id);
                      if(record) {
                         var obj = JSON.parse(record);
                         if("px" in obj) { // Check if attribute is valid
                             d.px = obj.px;
                             return obj.px;
                         }
                      }         
                  })
                  .attr("py", function(d) {
                      // Setting 'py' based on localstorage
                      var record = localStorage.getItem(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("py" in obj) { // Check if attribute is valid
                              d.py = obj.py; 
                              return obj.py;
                          }
                      }    
                  })
                  .attr("fixed", function(d) {
                      // Setting 'fixed' based on localstorage
                      var record = localStorage.getItem(d.id);
                      if(record) {
                          if(d.x > 0 && d.x < width && d.y > 0 && d.y < height && gravity !== "aggressive") {
                                d.fixed = true;
                                return true;
                          }else{
                                d.fixed = false;
                                return false; 
                          }
                      }           
                      return false;
                  })        
                  .on("click", function(d) {
                       // Check if it's not a click after dragging
                       if(!d3.event.defaultPrevented) {
                           // Remove all overlays and create a record overlay
                           removeOverlays();
                           createOverlay(d3.event.offsetX, d3.event.offsetY, "record", "id"+d.id, getRecordOverlayData(d));  
                       }
                  })
                  .call(node_drag);
    
    // Adding the background circles to the nodes
    var bgcircle = node.append("circle")
                       .attr("r", function(d) {
                            //console.log("COUNT for " + d.name + ": " + d.count);
                            return getEntityRadius(d.count);
                       })
                       .attr("class", "background")
                       .attr("fill", entitycolor);

    // Adding the foreground circles to the nodes
    var fgcircle = node.append("circle")
                       .attr("r", circleSize)
                       .attr("class", function(d) {
                            if(d.selected == true) {
                                return "foreground selected";
                            }
                            return "foreground around";
                       })
                       .style("stroke", "#ddd")
                       .style("stroke-opacity", function(d) {
                           if(d.selected == true) {
                               return 1;
                           }
                           return .25;
                       })
   
    // Adding icons to the nodes 
    var icon = node.append("svg:image")
                   .attr("class", "icon")
                   .attr("xlink:href", function(d) {
                        return d.image;
                   })
                   .attr("x", iconSize/-2)
                   .attr("y", iconSize/-2)
                   .attr("height", iconSize)
                   .attr("width", iconSize);
                   
    // Add labels?
    if(getSetting(setting_labels) == "true") {
        // Adding shadow text to the nodes 
        var maxLength = getSetting(setting_textlength);
        var shadowtext = node.append("text")
                             .attr("x", iconSize)
                             .attr("y", iconSize/4)
                             .attr("class", "shadow bold")
                             .style("font-size", fontsize, "important")
                             .text(function(d) {
                                 return truncateText(d.name, maxLength);
                             });
            
        // Adding normal text to the nodes 
        var fronttext = node.append("text")
                            .attr("x", iconSize)
                            .attr("y", iconSize/4)
                            .attr("class", "namelabel bold")
                            .attr("fill", textcolor)
                            .style("font-size", fontsize, "important")
                            .text(function(d) {
                                return truncateText(d.name, maxLength);
                            });
    }
    
    // Fish eye check
    if(fisheyeEnabled == "true") {
        // Create fish eye
        var fisheye = d3.fisheye.circular()
                                .radius(300)
                                .distortion(2);
                                
        // Listen to mouse movements                         
        svg.on("mousemove", function() {
            force.stop();
            fisheye.focus(d3.mouse(this));
            
            node.each(function(d) { 
                d.fisheye = fisheye(d); 
            })
            .attr("x", function(d) {
                return d.fisheye.x;
            })
            .attr("y", function(d) {
                return d.fisheye.y;
            })
            .attr("transform", function(d) {
                return "translate(" + d.fisheye.x + "," + d.fisheye.y + ")"; 
            });
            
            // Resize background circles
            bgcircle.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("r", function(d) {
                return getEntityRadius(d.count)*d.fisheye.z;
            });
            
            // Resize foreground circles
            fgcircle.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("r", function(d) {
                return circleSize*d.fisheye.z;
            });
            
            // Resize icons
            icon.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("x", function(d) {
                return (iconSize/-2) * d.fisheye.z;
            })
            .attr("y", function(d) {
                return (iconSize/-2) * d.fisheye.z;
            })
            .attr("height", function(d) {
                return iconSize * d.fisheye.z;
            })
            .attr("width", function(d) {
                return iconSize * d.fisheye.z;
            });
            
            // Shadow text
            shadowtext.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("y", function(d) {
                return -iconSize*d.fisheye.z;   
            })
            .style("font-size", function(d) {
                return 10*d.fisheye.z + "px";
            }, "important");
            
            // Front text
            fronttext.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("y", function(d) {
                return -iconSize*d.fisheye.z;   
            })
            .style("font-size", function(d) {
                return 10*d.fisheye.z + "px";
            }, "important");
            
            // Lines
            if(linetype == "curved") {
                // Curved
                force.stop();
                curvedFisheye(topLines);
                curvedFisheye(bottomLines);
                
                /** Applies fish eye effect to curved lines */
                function curvedFisheye(lines) {
                    lines.each(function(d) {
                        d.fisheye = fisheye(d);
                    })
                    .attr("d", function(d) {
                        var dx = d.target.fisheye.x - d.source.fisheye.x,
                            dy = d.target.fisheye.y - d.source.fisheye.y,
                            dr = Math.sqrt(dx * dx + dy * dy)/1.5,
                            mx = d.source.fisheye.x + dx,
                            my = d.source.fisheye.y + dy;
                            
                        return [
                          "M",d.source.fisheye.x,d.source.fisheye.y,
                          "A",dr,dr,0,0,1,mx,my,
                          "A",dr,dr,0,0,1,d.target.fisheye.x,d.target.fisheye.y
                        ].join(" ");
                    });  
                }
                
            }else{
                // Straight
                straightFisheye(topLines);
                straightFisheye(bottomLines);
                
                /** Applies fish eye effect to straight lines */
                function straightFisheye(lines) {
                    lines.attr("points", function(d) {
                       return d.source.fisheye.x + "," + d.source.fisheye.y + " " +
                              (d.source.fisheye.x +(d.target.fisheye.x-d.source.fisheye.x)/2) + "," + 
                              (d.source.fisheye.y +(d.target.fisheye.y-d.source.fisheye.y)/2) + " " +  
                              d.target.fisheye.x + "," + d.target.fisheye.y;
                    });
                }
            }
        }); 
    }    

    /** Tick handler for curved lines */
    function curvedTick() {
        updateCurvedLines(bottomLines);
        updateCurvedLines(topLines);
    }
    
    /** Updates curved lines */
    function updateCurvedLines(lines) {
        // Calculate the curved segments
        lines.attr("d", function(d) {
            var dx = d.target.x - d.source.x,
                dy = d.target.y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy)/1.5,
                mx = d.source.x + dx,
                my = d.source.y + dy;
                
            return [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              "A",dr,dr,0,0,1,d.target.x,d.target.y
            ].join(" ");
        });

        // Update node locations
        node.attr("transform", function(d) { 
             return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    /** Tick handler for straight lines */  
    function straightTick() {
        updateStraightLines(bottomLines);
        updateStraightLines(topLines);
    }
    
    /** Updates straight lines */                   
    function updateStraightLines(lines) {
        // Calculate the straight points
        lines.attr("points", function(d) {
           return d.source.x + "," + d.source.y + " " +
                  (d.source.x +(d.target.x-d.source.x)/2) + "," + 
                  (d.source.y +(d.target.y-d.source.y)/2) + " " +  
                  d.target.x + "," + d.target.y;
        });

         // Update node locations
        node.attr("transform", function(d) { 
             return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    // Finally apply translate & scale
    //container.attr("transform", "translate("+translateX+","+translateY+")scale("+scale+")");
    container.attr("transform", "translate(0,0)scale("+scale+")");
}

/*************************************** OVERLAY ****************************************/  

/**
* Truncates text after 80 characaters
* @param text The text to truncate
*/
function truncateText(text, maxLength) {
    if(text !== null) {
        if(text.length > maxLength) {
            return text.substring(0, maxLength-1) + "...";
        }
        return text;
    }
    return "[no name]"; 
}

/** Finds all outgoing links from a clicked record */
function getRecordOverlayData(record) {
    console.log(record);
    var maxLength = getSetting(setting_textlength);
    var array = [];
    
    // Header
    var header = {text: truncateText(record.name), size: "11px", style: "bold", height: 15, enter: true}; 
    if(settings.showCounts) {
        header.text += ", n=" + record.count;  
    }
    array.push(header);    

    // Going through the current displayed data
    var data = settings.getData.call(this, settings.data); 
    if(data && data.links.length > 0) {
        var map = {};
        for(var i = 0; i < data.links.length; i++) {
            var link = data.links[i];
            //console.log(link);
              
            // Does our record point to this link?
            if(link.source.id == record.id) {
                console.log(link.source.name + " -> " + link.target.name);
                // New name?
                if(!map.hasOwnProperty(link.relation.name)) {
                    map[link.relation.name] = {};
                }
                
                // Relation
                var relation = {text: "➜ " + truncateText(link.target.name, maxLength), size: "9px", height: 11, indent: true};
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count;                      
                }
                
                // Add record relation to map
                if(map[link.relation.name][relation.text] == undefined) {
                    map[link.relation.name][relation.text] = relation;
                }
            }
            
            // Does this link point to our record?
            /*
            if(link.target.id == record.id) {
                if(!map.hasOwnProperty(link.relation.name)) {
                    map[link.relation.name] = [];
                }

                var obj = {text: "← " + link.source.name + " (n=" + link.source.count + ")", size: "9px"};
                map[link.relation.name].push(obj);
            }
            */
            
            // Is our record a relation?
            if(link.relation.id == record.id && link.relation.name == record.name) {
                // New name?
                if(!map.hasOwnProperty(link.relation.name)) {
                    map[link.relation.name] = {};
                }
               
                // Relation
                var relation = {text: truncateText(link.source.name, maxLength) + " ↔ " + truncateText(link.target.name, maxLength), size: "9px", height: 12, indent: true};
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count
                }
                  
                // Add relation to map
                if(map[link.relation.name][relation.text] == undefined) {
                    map[link.relation.name][relation.text] = relation;
                }
            }
        }
        
        console.log("MAP");
        console.log(map);
        
        // Convert map to array
        for(key in map) {                                   
            array.push({text: truncateText(key, maxLength), size: "11px", height: 14, enter: true}); // Heading
            for(text in map[key]) {
                array.push(map[key][text]);    
            }
        }
    }

    return array;
}

/** Finds all relationships between the source and target */
function getRelationOverlayData(line) {
    console.log(line);
    var array = [];
    var maxLength = getSetting(setting_textlength);
    
    // Header
    var header1 = truncateText(line.source.name, maxLength);
    var header2 = truncateText(line.target.name, maxLength);
    if(header1.length+header2.length > maxLength) {
        array.push({text: header1 + " ↔", size: "11px", style: "bold", height: 15});
        array.push({text: header2, size: "11px", style: "bold", height: 10, enter: true});
    }else{
        array.push({text: header1+" ↔ "+header2, size: "11px", style: "bold", height: 15, enter: true}); 
    }

    // Going through the current displayed data
    var data = settings.getData.call(this, settings.data); 
    if(data && data.links.length > 0) {
        var map = {};
        for(var i = 0; i < data.links.length; i++) {
            var link = data.links[i];
            //console.log(link);
            
            // Pointing to target
            if(link.source.id == line.source.id && link.target.id == line.target.id) {
                var relation = {text: "➜ " + truncateText(link.relation.name, maxLength), size: "9px", height: 11}; 
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count
                }
                array.push(relation);  
            }
   
           // Pointing to source 
           if(link.source.id == line.target.id && link.target.id == line.source.id) {
                var relation = {text: "← "+ truncateText(link.relation.name, maxLength), size: "9px", height: 11}; 
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count
                }
                array.push(relation);  
            }
        }
    }
    
    return array;
}

/**
* Creates an overlay on the location that the user has clicked on.
* @param x Coord-x
* @param y Coord-y
* @param record Record info
*/
function createOverlay(x, y, type, selector, info) {
    svg.select(".overlay."+selector).remove();

    // Add overlay container            
    var overlay = svg.append("g")
                     .attr("class", "overlay "+type+ " " + selector)      
                     .attr("transform", "translate(" +(x+5)+ "," +(y+5)+ ")");
                     
    // Draw a semi transparant rectangle       
    var rect = overlay.append("rect")
                      .attr("class", "semi-transparant")              
                      .attr("x", 0)
                      .attr("y", 0)
                      .on("drag", overlayDrag);
            
    // Adding text 
    var offset = 10;  
    var indent = 5;
    var position = 0;
    var text = overlay.selectAll("text")
                      .data(info)
                      .enter()
                      .append("text")
                      .text(function(d) {
                          return d.text;
                      })
                      .attr("x", function(d, i) {
                          // Indent check
                          if(d.indent) {
                            return offset+indent;
                          }
                          return offset;
                      })        // Some left padding
                      .attr("y", function(d, i) {
                          // Multiline check
                          if(!d.multiline) { 
                              position += d.height;
                          }
                          return position; // Position calculation
                      })
                      .attr("font-weight", function(d) {  // Font weight based on style property
                          return d.style;
                      })
                      .style("font-size", function(d) {   // Font size based on size property
                          return d.size;
                      }, "important");
                      
                  
    // Calculate optimal rectangle size
    var maxWidth = 1;
    var maxHeight = 1;                              
    for(var i = 0; i < text[0].length; i++) {
        var bbox = text[0][i].getBBox();

        // Width
        var width = bbox.width;
        if(width > maxWidth) {
            maxWidth = width;
        }
        
        // Height
        var y = bbox.y;
        if(y > maxHeight) {
            maxHeight = y;
        }
    }
    maxWidth  += offset*3.5;
    maxHeight += offset*2; 
    
    // Set optimal width & height
    rect.attr("width", maxWidth)
        .attr("height", maxHeight);
  
    // Close button
    var buttonSize = 15;
    var close = overlay.append("g")
                       .attr("class", "close")
                       .attr("transform", "translate("+(maxWidth-buttonSize)+",0)")
                       .on("click", function(d) {
                           removeOverlay(selector);                              
                       });
                       
    // Close rectangle                                                                     
    close.append("rect")
           .attr("class", "close-button")
           .attr("width", buttonSize)
           .attr("height", buttonSize)

    // Close text        
    close.append("text")
         .attr("class", "close-text")
         .text("X")
         .attr("x", buttonSize/4)
         .attr("y", buttonSize*0.75);
         
    /** Handles dragging events on overlay */
    function overlayDrag(d) {
        overlay.attr("transform", "translate("+d.x+","+d.y+")");
    }                 
}


/** Repositions all overlays */
function updateOverlays() {
    $(".overlay").each(function() {
        // Get information
        var pieces = $(this).attr("class").split(" ");
        var type = pieces[1];
        var id = pieces[2];
        //console.log("Type: " + type + ", id:" + id);
        
        // Select element to align to
        var bbox;
        if(type == "record") {
            bbox = $(".node."+id + " .icon")[0].getBoundingClientRect();
        }else{
            bbox = $(".link."+id)[0].getBoundingClientRect();
        }
        //console.log(bbox);
        
        // Update position 
        var svgPos = $("svg").position();
        x = bbox.left + bbox.width/2 - svgPos.left
        y = bbox.top + bbox.height/2 - svgPos.top;  
        $(this).attr("transform", "translate("+x+","+y+")");
    });
    
    /*
    
    
    if(pos && svgPos) {
        var x = pos.left - svgPos.left;
        var y = pos.top - svgPos.top;
        $(".overlay.id"+id).attr("transform", "translate("+x+","+y+")");   
    }
    */
}

/** Removes the overlay with the given ID */
function removeOverlay(selector) {
    $(".overlay."+selector).fadeOut(300, function() {
        $(this).remove();
   }); 
}

/** Removes all overlays */
function removeOverlays() {
    $(".overlay").each(function() {
        $(this).fadeOut(300, function() {
            $(this).remove();
        })
    });
}



 
/********************************* MENU ***********************************/
$(document).ready(function() {
    /* For future usage 
    // Menu popup link click
    $(".popup-link").click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var width = $(window).width() / 1.5;
        var height = $(window).height() / 1.5;
        
        var url = $(this).attr("href");
        top.HEURIST.util.popupURL(top, url, {height:height, width:width});
    });
    */
    
    $("#gephi-export").click(function(e) {
        getGephiFormat();
    });
});

/** Constructs an URL that shows the results independently */
function getCustomURL() {
    // Get search data
    var data = settings.getData.call(this, settings.data);
    console.log(data);
    
    // Construct ID's
    var ids = [];
    for(var key in data.nodes) {
        ids.push(data.nodes[key].id);
    }

    // Construct url
    var json = JSON.stringify(ids);
    var url = location.protocol + "//" + location.host + location.pathname + 
              "?db=" + getDatabaseName() + "&ids=" + json;
              
    console.log("URL: " + url);
    return url;
}

/** Function to download */
function download(filename, text) {
   
}

/** Transforms the visualisation into Gephi format */
function getGephiFormat() {
    // Get data
    var data = settings.getData.call(this, settings.data);
    console.log("GEPHI DATA");
    console.log(data);
    
    // META
    var gexf = '<?xml version="1.0" encoding="UTF-8"?>';
    gexf +=      '<gexf xmlns="http://www.gexf.net/1.2draft" version="1.2">';
    gexf +=        '<meta lastmodifieddate="2014-10-10">';
    gexf +=          '<creator>HeuristNetwork.org</creator>';
    gexf +=          '<description>Visualisation export</description>';
    gexf +=        '</meta>' ;
    gexf +=        '<graph mode="static" defaultedgetype="directed">';
    
    // NODE ATTRIBUTES
    gexf += '<attributes class="node">';
    gexf +=     '<attribute id="0" title="name" type="string"/>';
    gexf +=     '<attribute id="1" title="image" type="string"/>';
    gexf +=     '<attribute id="2" title="count" type="float"/>';
    gexf += '</attributes>';
    
    // EDGE ATTRIBUTES
    gexf += '<attributes class="edge">';
    gexf +=     '<attribute id="0" title="relation-id" type="float"/>';
    gexf +=     '<attribute id="1" title="relation-name" type="string"/>';
    gexf +=     '<attribute id="2" title="relation-image" type="string"/>';
    gexf +=     '<attribute id="3" title="relation-count" type="float"/>';
    gexf += '</attributes>';
     
    // NODES
    gexf += '<nodes>';
    for(var key in data.nodes) {
        var node = data.nodes[key];
        gexf += '<node id="'+node.id+'" label="'+node.name+'">';
        gexf +=     '<attvalues>';
        gexf +=         '<attvalue for="0" value="'+node.name+'"/>';
        gexf +=         '<attvalue for="1" value="'+node.image+'"/>';
        gexf +=         '<attvalue for="2" value="'+node.count+'"/>';
        gexf +=     '</attvalues>'; 
        gexf += '</node>';
    }
    gexf += '</nodes>';
    
    // EDGES
    gexf += '<edges>';
    for(var i = 0; i < data.links.length; i++) {
        var edge = data.links[i]; 
        gexf += '<edge id="'+i+'" source="'+edge.source.id+'" target="'+edge.target.id+'" weight="'+edge.targetcount+'">';
        gexf +=     '<attvalues>';  
        gexf +=         '<attvalue for="0" value="'+edge.relation.id+'"/>';      
        gexf +=         '<attvalue for="1" value="'+edge.relation.name+'"/>';
        gexf +=         '<attvalue for="2" value="'+edge.relation.image+'"/>';
        gexf +=         '<attvalue for="3" value="'+edge.targetcount+'"/>';
        gexf +=     '</attvalues>'; 
        gexf += '</edge>';
    }
    gexf += '</edges>';
    
    // COMPLETE
    gexf +=         '</graph>';
    gexf +=       '</gexf>';
    
    // DOWNLOAD
    var pom = document.createElement('a');
    pom.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(gexf));
    pom.setAttribute('download', getDatabaseName()+".gexf");
    pom.click();
    
}