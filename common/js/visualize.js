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
(function ( $ ) {
    var settings;   // Plugin settings object
    var svg;        // The SVG where the visualisation will be executed on

    /**
    * jQuery plugin hook, this is where the magic happens
    * @param options Custom options given when this function is called
    */
    $.fn.visualize = function( options ) {
        svg = d3.select("#d3svg");
        
        // Default plugin settings
        settings = $.extend({
            // Custom functions
            getData: $.noop(),
            // User settings
            linecolor: "#22a",
            markercolor: "#000",
            countcolor: "#262",
            textcolor: "#b22",
            linetype: "straight",
            linelength: 300,
            linewidth: 15,
            entityradius: 30,
            formula: "linear",
            gravity: "touch",
            attraction: -700,
            fisheye: false
        }, options );
 
        // Handle settings
        checkStoredSettings();
        handleSettingsInUI();

        // Transform
        visualizeData();
        
        return this;
    };
    
    
    
    /*********************************** START OF SETTING FUNCTIONS **************************************/
    /** SETTING NAMES */
    var setting_linecolor     = "setting_linecolor";
    var setting_markercolor   = "setting_markercolor";
    var setting_countcolor    = "setting_countcolor";
    var setting_textcolor     = "setting_textcolor";
    var setting_linetype      = "setting_linetype";
    var setting_linelength    = "setting_linelength";
    var setting_linewidth     = "setting_linewidth";
    var setting_entityradius  = "setting_entityradius";
    var setting_formula       = "setting_formula";
    var setting_gravity       = "setting_gravity";
    var setting_attraction    = "setting_attraction";
    var setting_fisheye       = "setting_fisheye";

    /**
     * Returns a setting from the localStorage
     * @param setting The setting to retrieve
     */
    function getSetting(setting) {
        return localStorage.getItem(setting);
    }
    
    /**
    * Checks if a setting has been set, if not it sets the default value
    * @param key    Localstorage key
    * @param value  The default value
    */
    function checkSetting(key, value) {
        var obj = getSetting(key);
        if(obj === null) {
            localStorage.setItem(key, value);
        }
    }

    /**
     * This function makes sure the default settings are stored in the localStorage.
     * @param settings The plugin settings object
     */
    function checkStoredSettings() {
        checkSetting(   setting_linecolor,     settings.linecolor   );
        checkSetting(   setting_markercolor,   settings.markercolor );
        checkSetting(   setting_countcolor,    settings.countcolor  );
        checkSetting(   setting_textcolor,     settings.textcolor   );
        checkSetting(   setting_linetype,      settings.linetype    );
        checkSetting(   setting_linelength,    settings.linelength  );
        checkSetting(   setting_linewidth,     settings.linewidth   );
        checkSetting(   setting_entityradius,  settings.entityradius);
        checkSetting(   setting_formula,       settings.formula     );
        checkSetting(   setting_gravity,       settings.gravity     );
        checkSetting(   setting_attraction,    settings.attraction  );
        checkSetting(   setting_fisheye,       settings.fisheye     );
    }
    
    /**
    * This function sets the settings in the UI
    */
    function handleSettingsInUI() {
        /** LINE COLOR SETTING */
        // Set line color setting in UI
        $("#linecolor").css("background-color", getSetting(setting_linecolor));

        // Listen to 'line color' selection changes
        $('#linecolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                localStorage.setItem(setting_linecolor, color);
                $(".link").attr("stroke", color);
        
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
        
        /** MARKER COLOR SETTING */
        // Set marker color in UI
        $("#markercolor").css("background-color", getSetting(setting_markercolor));
        
        // Listen to 'marker color' selection changes
        $('#markercolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                localStorage.setItem(setting_markercolor, color);
                $("marker").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
        
        /** COUNT COLOR SETTING */
        // Set count color in UI
        $("#countcolor").css("background-color", getSetting(setting_countcolor));

        // Listen to 'count color' selection changes
        $('#countcolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                localStorage.setItem(setting_countcolor, color);
                $(".background").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
        
        /** TEXT COLOR SETTING */
        // Set text color in UI
        $("#textcolor").css("background-color", getSetting(setting_textcolor));

        // Listen to 'count color' selection changes
        $('#textcolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                localStorage.setItem(setting_textcolor, color);
                $(".namelabel").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
        
        /** LINE TYPE SETTING */
        // Set line type setting in UI
        $("#linetype option[value='" +getSetting(setting_linetype)+ "']").attr("selected", true);
        
        // Listens to linetype selection changes
        $("#linetype").change(function(e) {
            localStorage.setItem(setting_linetype, $("#linetype").val());
            visualizeData();
        });
        
        /** LINE LENGTH SETTING */
        // Set line length setting in UI
        $("#linelength").val(getSetting(setting_linelength));
        
        // Listen to line length changes
        $("#linelength").change(function() {
            localStorage.setItem(setting_linelength, $(this).val());
            visualizeData();
        });
        
        /** LINE WIDTH SETTING */
        // Set line width setting in UI
        $("#linewidth").val(getSetting(setting_linewidth));
        
        // Listen to line width changes
        $("#linewidth").change(function() {
            localStorage.setItem(setting_linewidth, $(this).val());
            visualizeData();
        });
        
        /**  MAX RADIUS SETTING */
        // Set entity radius setting in UI
        $("#entityradius").val(getSetting(setting_entityradius));
        
        // Listen to line width changes
        $("#entityradius").change(function() {
            localStorage.setItem(setting_entityradius, $(this).val());
            visualizeData();
        });
        
        /** LINE LENGTH SETTING */
        // Set formula setting in UI
        $("#formula option[value='" +getSetting(setting_formula)+ "']").attr("selected", true); 

        // Listen to formula changes
        $("#formula").change(function() {
            localStorage.setItem(setting_formula, $(this).val());
            visualizeData();
        });
        
        /** GRAVITY SETTING */
        // Set gravity setting in UI
        $("#gravity option[value='" +getSetting(setting_gravity)+ "']").attr("selected", true);

        // Listen to gravity changes
        $("#gravity").change(function() {
            localStorage.setItem(setting_gravity,  $(this).val());
            visualizeData();
        });
        
        /** ATTRACTION SETTING */
        // Set attraction setting in UI
        $("#attraction").val(getSetting(setting_attraction));
        
        // Listen to attraction changes
        $("#attraction").change(function() {
            localStorage.setItem(setting_attraction, $(this).val());
            visualizeData();
        });
        
        /** FISH EYE */
        // Set fish eye setting in UI
        if(getSetting(setting_fisheye) === "true") {
            $("#fisheye").prop("checked", true);
        }
        
        // Listen to fisheye changes
        $("#fisheye").change(function(e) {
            localStorage.setItem(setting_fisheye, $(this).is(':checked'));
            visualizeData();
        });
    }
    /************************************ END OF SETTING FUNCTIONS ***************************************/
    
    
    
    /*******************************START OF VISUALISATION HELPER FUNCTIONS*******************************/
    /** Calculates log base 10 */
    function log10(val) {
        return Math.log(val) / Math.LN10;
    }

    /** Executes the chosen formula with a chosen count & max size */
    var maxCount = 100; 
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
            if(count > 0) {
                return 2;
            }else{
                return 0;
            }                                            
        }else {  // Linear
            return (count/maxCount) * maxSize; 
        }       
    }

    /** Calculates the line width that should be used */
    function getLineWidth(count) {
        var maxWidth = getSetting(setting_linewidth);                                                                     
        return 0.5 + (executeFormula(count, maxWidth) / 2);
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
        return circleSize + executeFormula(count, maxRadius);
    }
    /********************************END OF VISUALISATION HELPER FUNCTIONS********************************/



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
        
        // Color settings
        var linecolor = getSetting(setting_linecolor);
        var markercolor = getSetting(setting_markercolor);
        var countcolor = getSetting(setting_countcolor);
        var textcolor = getSetting(setting_textcolor);
        
        // Line settings
        var linetype = getSetting(setting_linetype);
        var linelength = getSetting(setting_linelength);

        // Gravity settings
        var gravity = getSetting(setting_gravity);
        var attraction = getSetting(setting_attraction);
        var fisheyeEnabled = getSetting(setting_fisheye);
        
        console.log("SETTINGS HAVE BEEN SET");
        
        // Container
        var zoom = d3.behavior.zoom()
                     .scaleExtent([-10, 10])
                     .on("zoom", zoomed);  
                     
        function zoomed() {
            container.attr("transform", "translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
        }             
        
        svg.call(zoom);
        var container = svg.append("g");

        // Creating D3 force
        var force = d3.layout.force()
                             .nodes(d3.values(data.nodes))
                             .links(data.links)
                             .charge(attraction)        // Using the attraction setting
                             .linkDistance(linelength)  // Using the linelength setting 
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
        console.log("FORCE STARTED");   
                       
        // Adding marker definitions
        container.append("defs")
                 .selectAll("marker")
                 .data(force.links())
                 .enter()
                 .append("svg:marker")    
                 .attr("id", function(d) {
                    return "marker" + d.relation.id;
                 })
                 .attr("markerWidth", function(d) {    
                    return getMarkerWidth(d.relation.count);             
                 })
                 .attr("markerHeight", function(d) {
                    return getMarkerWidth(d.relation.count);
                 })
                 .attr("refX", -1)
                 .attr("refY", 0)
                 .attr("viewBox", "0 -5 10 10")
                 .attr("markerUnits", "userSpaceOnUse")
                 .attr("orient", "auto")
                 .attr("fill", markercolor) // Using the markercolor setting
                 .attr("opacity", "0.6")
                 .append("path")
                 .attr("d", "M0,-5L10,0L0,5");

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
        lines.attr("class", "link") 
             .attr("stroke", linecolor) 
             .attr("marker-mid", function(d) {
                return "url(#marker" + d.relation.id + ")";
             })
             .style("stroke-width", function(d) { 
                return 0.5 + getLineWidth(d.relation.count);
             })
             .style("stroke-dasharray", (function(d) {
                 if(d.relation.count == 0) {
                    return "3, 3"; 
                 } 
             })) 
             .on("click", function(d) {
                 // Construct line overlay data, then use it to generate the overlay itself
                 createOverlay(d3.event.offsetX, d3.event.offsetY, d.relation);    
             })
             .on("mouseout", function(d) {
                 removeOverlay();                                 
             });
             
        // Check what methods to call on drag
         var node_drag;
         if(gravity === "aggressive") {
             // Default method
             node_drag = force.drag;
         }else{
             // Custom methods
             node_drag = d3.behavior.drag()
                                    .on("dragstart", dragstart)
                                    .on("drag", dragmove)
                                    .on("dragend", dragend);
         }
                                   
        /** Called when a dragging event starts */
        function dragstart(d, i) {
            force.stop() // Stop force from auto positioning
            if(gravity === "touch") {
                svg.selectAll(".node").attr("fixed", function(d, i) {
                    d.fixed = false;
                    return false;
                });
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
            localStorage.setItem(d.id, JSON.stringify(obj));
        
            // Update nodes & lines                                                           
            if(linetype == "curved") { 
                 curvedTick();
            }else{
                 straightTick();
            }
        }
        
        /** Called when a dragging event ends */
        function dragend(d, i) {
             d.fixed = true; // Node may not be auto positioned
             
             // Update nodes & lines 
             if(linetype == "curved") { 
                 curvedTick();
             }else{
                 straightTick();
             } 
             
             // Check if force may resume
              if(gravity === "touch") {
                    force.resume(); 
              } 
        }
             
        // Defining the nodes
        var node = container.selectAll(".node")
                      .data(force.nodes())
                      .enter().append("g")
                      .attr("class", function(d, i) {
                          return "node " + d.type;
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
                                createOverlay(d3.event.offsetX, d3.event.offsetY, d);
                           }
                      })
                      .on("mouseout", function(d) {
                         removeOverlay();                                 
                      })
                      .call(node_drag);
        
        // Adding the background circles to the nodes
        var bgcircle = node.append("circle")
                           .attr("r", function(d) {
                                //console.log("COUNT for " + d.name + ": " + d.count);
                                return getEntityRadius(d.count);
                           })
                           .attr("class", "background")
                           .attr("fill", countcolor);

        // Adding the foreground circles to the nodes
        var fgcircle = node.append("circle")
                           .attr("r", circleSize)
                           .attr("class", "around foreground");
       
        // Adding icons to the nodes 
        var icon = node.append("svg:image") 
                       .attr("xlink:href", function(d) {
                            return d.image;
                       })
                       .attr("x", iconSize/-2)
                       .attr("y", iconSize/-2)
                       .attr("height", iconSize)
                       .attr("width", iconSize);

        // Adding shadow text to the nodes 
        var shadowtext = node.append("text")
                             .attr("x", 0)
                             .attr("y", -iconSize)
                             .attr("class", "center shadow")
                             .text(function(d) { 
                                return d.name; 
                             });
            
        // Adding normal text to the nodes 
        var fronttext = node.append("text")
                            .attr("x", 0)
                            .attr("y", -iconSize)
                            .attr("class", "center namelabel")
                            .attr("fill", textcolor)
                            .text(function(d) { 
                               return d.name; 
                            });
        
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
                }else{
                    // Straight
                    lines.attr("points", function(d) {
                       return d.source.fisheye.x + "," + d.source.fisheye.y + " " +
                              (d.source.fisheye.x +(d.target.fisheye.x-d.source.fisheye.x)/2) + "," + 
                              (d.source.fisheye.y +(d.target.fisheye.y-d.source.fisheye.y)/2) + " " +  
                              d.target.fisheye.x + "," + d.target.fisheye.y;
                    });
                }
                
            }); 
        }    
  
        /** Tick handler for curved lines */
        function curvedTick() {
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
    }
    
    /*************************************** OVERLAY ****************************************/
    /**
    * Creates an overlay on the location that the user has clicked on.
    * @param x Coord-x
    * @param y Coord-y
    * @param record Record info
    */
    function createOverlay(x, y, record) {
        var horizontalOffset = 5;
        var verticalOffset = 12;
        console.log("CREATE OVERLAY");
        console.log(record);
        
        $("#overlay").remove();
    
        // Add overlay container                 
        var overlay = svg.append("g")
                         .attr("id", "overlay")      
                         .attr("transform", "translate(" +x+ "," +(y+20)+ ")");
        
        // Draw a semi transparant rectangle       
        var rect = overlay.append("rect")
                          .attr("class", "semi-transparant")              
                          .attr("x", 0)
                          .attr("y", 0);
                          
        // Adding text  
        var info = [record.name];  
        var text = overlay.selectAll("text")
                          .data(info)
                          .enter()
                          .append("text")
                          .text(String)
                          .attr("x", horizontalOffset)        // Some left padding
                          .attr("y", function(d, i) {
                              return (i+0.5)*verticalOffset;      // Position calculation
                          });
                      
        // Calculate optimal rectangle size
        var maxWidth = 100;
        var maxHeight = 100;                              
        
        // Set optimal width & height
        rect.attr("width", maxWidth + 3*horizontalOffset)
            .attr("height", maxHeight + verticalOffset);
    }
    
    
    /** Slight delay, fades away and then removes itself. */
    function removeOverlay() {
        $("#overlay").delay(500).fadeOut(500, function() {
             $(this).remove();
        });
    }
 
}( jQuery ));