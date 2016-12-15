
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

// Functions to handle node and relationship overlays
 
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
    //console.log(record);
    var maxLength = getSetting(setting_textlength);
    var array = [];
    
    // Header
    var header = {text: truncateText(record.name, maxLength), size: "11px", style: "bold", height: 15,
                                 enter: true, image:record.image}; 
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
                //console.log(link.source.name + " -> " + link.target.name);
                // New name?
                if(!map.hasOwnProperty(link.relation.name)) {
                    map[link.relation.name] = {};
                }
                
                if(!settings.isDatabaseStructure){
                    // Relation
                    var relation = {text: "➜ " + truncateText(link.target.name, maxLength), size: "9px", height: 11, indent: true};
                    if(settings.showCounts) {
                        relation.text += ", n=" + link.targetcount;                      
                    }
                
                    // Add record relation to map
                    if(map[link.relation.name][relation.text] == undefined) {
                        map[link.relation.name][relation.text] = relation;
                    }
                
                }
            }

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
        console.log("Record overlay data", map);

        // Convert map to array
        for(key in map) {                                   
            array.push({text: truncateText(key, maxLength), size: "8px", style:"italic", height: 12, enter: true}); // Heading
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
    
    console.log("Line overlay data", array);
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

    var iconSize = 16;
    
    // Show overlays?
    var showOverlay = $("#overlay").hasClass("selected");
     if(showOverlay) {
        // Add overlay container            
        var overlay = svg.append("g")
                         .attr("class", "overlay "+type+ " " + selector)      
                         .attr("transform", "translate(" +(x-iconSize/2)+ "," +(y-iconSize/2)+ ")"); //was shifted  +5
        var rty_ID = '';                 
        // Title
        var rollover = info[0].text;
        //var title = overlay.append("title").text(info[0].text+' rollover?');
        if(settings.isDatabaseStructure && type=='record'){
            rty_ID = selector.substr(2);
            
            var fidx = window.hWin.HEURIST4.rectypes.typedefs['commonNamesToIndex']['rty_Description'];
            rollover = rollover + ' ' + window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields[fidx];
        }
    
        // Draw a semi transparant rectangle       
        var rect = overlay.append("rect")
                          .attr("class", "semi-transparant")              
                          .attr("x", 0)
                          .attr("y", 0)
                          .attr("rx", 6)
                          .attr("ry", 6)
                          .style('stroke','#ff0000')
                          .style("stroke-width", 0.5)
                          .style("filter", "url(#drop-shadow)")
                          .on("drag", overlayDrag);
                                    
        rect.append("title").text(rollover);                    
                                         
        //Adding icon
        var icons = overlay
                  .append("svg:image")
                  .attr("class", "icon")
                  .attr("xlink:href", function(d) {
                        if(info[0].image){
                            return info[0].image;
                        }else{
                            return '';
                        }
                  })
                  .attr("x", 3)  //iconSize/-2
                  .attr("y", 3)  //iconSize/-2
                  .attr("height", iconSize)
                  .attr("width", iconSize);  
                
        
        // Adding text 
        var offset = 26;  
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
                          .style("font-style", function(d) {   // Font style based on style property
                              return d.style;
                          }, "important")
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
      
      if(settings.isDatabaseStructure && type=='record'){
        //edit button
        var btnEdit = overlay
                  .append("svg:image")
                  .attr("class", "icon")
                  .attr("xlink:href", function(d) {
                        return window.hWin.HAPI4.basePathV4+'hclient/assets/edit-pencil.png';
                  })
                  .attr("transform", "translate("+(maxWidth-iconSize-50)+","+(maxHeight-iconSize-3)+")")
                  /*.attr("x", 2)
                  .attr("y", 2) */
                  .attr("height", iconSize)
                  .attr("width", iconSize)
                  .on("mouseup", function(d) {
                      event.preventDefault();
                      _editRecStructure(rty_ID);
                  });;  
      
        //link button      
        var btnAddLink = overlay
                  .append("g")
                  .attr("class", "addlink")
                  .attr("transform", "translate("+(maxWidth-50)+","+(maxHeight-iconSize-3)+")")
                  .attr("height", iconSize)
                  .attr("width", 30)
                  .on("mouseup", function(d) {
                      event.preventDefault();
                      _editRecStructure(rty_ID);
                  });;  
        // Close text        
        btnAddLink.append("text")
             .attr("class", "addlink-text")
             .text("add link")
             .attr("x", 2)
             .attr("y", iconSize*0.75);
      
      }else{
        // Close button
        var buttonSize = 15;
        var close = overlay.append("g")
                           .attr("class", "close")
                           .attr("transform", "translate("+(maxWidth-buttonSize)+",0)")
                           .on("mouseup", function(d) {
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
             
      }
             
        /** Handles dragging events on overlay */
        function overlayDrag(d) {
            overlay.attr("transform", "translate("+d.x+","+d.y+")");
        }          
     }       
}


//
// edit strcuture (from image link in table)
//
function _editRecStructure(rty_ID) {

    var URL = window.hWin.HAPI4.basePathV4 + "admin/structure/fields/editRecStructure.html?db="
            +  window.hWin.HAPI4.database+ '&rty_ID='+rty_ID;

    //var dim = Hul.innerDimensions(top);

    window.hWin.HEURIST4.msg.showDialog(URL, {
            "close-on-blur": false,
            "no-resize": false,
            title: 'RECORD STRUCTURE',
            height: 600, //dim.h*0.9,
            width: 860,
            "no-close": true,
            closeCallback: function(){ },
            callback: function(context) { }
    });
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