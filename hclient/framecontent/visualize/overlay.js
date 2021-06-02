
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

    var maxLength = getSetting(setting_textlength);
    var array = [];
    
    // Header
    var header = {text: truncateText(record.name, maxLength), 
                  count: record.count,  
                  size: "11px", style: "bold", height: 15, indent:false,
                                 enter: true, image:record.image}; 
    if(settings.showCounts) {
        header.text += ", n=" + record.count;  
    }
    array.push(header);    

    var fontSize = getSetting(setting_fontsize, 12);        

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
                    var relation = {text: "➜ " + truncateText(link.target.name, maxLength), size: "9px", height: 11, indent: true, subheader:1, xpos:10, multiline:true};
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
                var relation = {text: truncateText(link.source.name, maxLength) + " ↔ " + truncateText(link.target.name, maxLength), size: "9px", height: fontSize, indent: true, xpos:10, multiline:true};
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count
                }
                  
                // Add relation to map
                if(map[link.relation.name][relation.text] == undefined) {
                    map[link.relation.name][relation.text] = relation;
                }
            }
        }

//console.log("Record overlay data", map);

        // Convert map to array
        var xpos = 10; //!!!!
        for(key in map) {                                   
            array.push({text: truncateText(key, maxLength), size: "8px", xpos:xpos, multiline:true,
                    style:"italic", height: fontSize, indent:true, enter: true, subheader:1}); // Heading
            for(text in map[key]) {
                array.push(map[key][text]);    
            }
            //xpos = xpos + 10;
        }
    }

    return array;                                                                                
}

/** get info about particular relation */
function getRelationOverlayData(line) {
    var array = [];
    var maxLength = getSetting(setting_textlength);

    // Header
    var header1 = truncateText(line.source.name, maxLength);
    var header2 = truncateText(line.target.name, maxLength);
    if(header1.length+header2.length > maxLength) {
        array.push({text: header1 + " >", size: "11px", style: "bold", height: 15});
        array.push({text: header2, size: "11px", style: "bold", height: 10, enter: true});
    }else{
        array.push({text: header1+" > "+header2, size: "11px", style: "bold", height: 15, enter: true}); 
    }

    var data = settings.getData.call(this, settings.data);
    if(data && data.links.length > 0 && $('#expand-links').is(':not(:Checked)')){

      for (var i = 0; i < data.links.length; i++){
        var link = data.links[i];

        // Show information for all links, with same source and target ids
        if(link.source.id == line.source.id && link.target.id == line.target.id){
          var relation = {type: link.relation.type, cnt: link.targetcount, 
            text: truncateText(link.relation.name, maxLength) + ', n=' + link.targetcount, size: "20px", height: 20, dir: "to"};

          array.push(relation);
          continue;
        }

        // Reverse Links, information about links that are sourced from the target
        if(link.source.id == line.target.id && link.target.id == line.source.id){
          var relation = {type: link.relation.type, cnt: link.targetcount, 
            text: truncateText(link.relation.name, maxLength) + ', n=' + link.targetcount, size: "20px", height: 20, dir: "from"};

          array.push(relation);
          continue;
        }
        

      }
    }else{

      // Show information for this link only
      var relation = {type: line.relation.type, cnt: line.targetcount, text: 
              truncateText(line.relation.name, maxLength) + ", n=" + line.targetcount, size: "9px", height: 11, subheader:1};

      array.push(relation);
    }

    return array;
}

var drag_link_source_id, drag_link_target_id, drag_link_line, drag_link_timer; 

/**
* Creates an overlay over the node / on the location that the user has clicked on.
* 
*  type - record   icon and info box over node
*  type - relation
* 
* @param x Coord-x
* @param y Coord-y
* @param selector event target
* @param node_obj information
*/
function createOverlay(x, y, type, selector, node_obj, parent_node) {
    
    if(type=='record'){
        info = getRecordOverlayData(node_obj);
    }else{
        info = node_obj;
    }
    
    var iconSize = 16;
    
    var overlay = null;

    // Add overlay container    
    if(parent_node){
        overlay  = parent_node.append("g")
                              .attr("transform", "translate(" +(x-iconSize/2-6)+ "," +(y-iconSize/2-6)+ ")");
    }else{
        overlay = svg.append("g")
                     .attr("class", "overlay "+type+ " " + selector)      
                     .attr("transform", "translate(" +x +','+y+')');
    }
    
    var rty_ID = '', rec_ID = '';                 
    
    // Title
    info[0].text = window.hWin.HEURIST4.util.stripTags(info[0].text);
    var rollover = info[0].text;
    
    if(type=='record'){
        if(settings.isDatabaseStructure){
            rty_ID = selector.substr(2);
            var desc = $Db.rty(rty_ID, 'rty_Description');
            if(desc!=null){
                rollover = rollover + ' ' + desc;
            }else{
                console.log('rectype not found '+rty_ID);
            }
        }else{
            rec_ID = selector.substr(2);
        }
            
    }
    
    if(parent_node){
        parent_node
        .on("mouseover", function(d) {
            if(drag_link_source_id!=null){
                //cancel timer
                if(drag_link_timer>0){
                    clearTimeout(drag_link_timer);
                    drag_link_timer = 0;
                }
                drag_link_target_id = d.id;
                drag_link_line.attr("stroke","#00ff00");
            }            
        })
        .on("mouseout", function(d) {
            if(drag_link_source_id!=null){
            drag_link_timer = setTimeout(function(){
                drag_link_target_id = null;
                if(drag_link_line) drag_link_line.attr("stroke","#ff0000");
            },300);
            }            
        });
    }

    
    
    // Draw a semi transparant rectangle       
    var rect_full = overlay.append("rect")
                           .attr("class", "semi-transparant info-mode-full rect-info-full")              
                           .attr("x", 0)
                           .attr("y", 0)
                           .attr("rx", 6)
                           .attr("ry", 6)
                           .style('stroke','#ff0000')
                           .style("stroke-width", 0.5)
                           .style("filter", "url(#drop-shadow)");

    var rect_info = overlay.append("rect")
                           .attr("class", "semi-transparant info-mode rect-info")              
                           .attr("x", 0)
                           .attr("y", 0)
                           .attr("rx", 6)
                           .attr("ry", 6)
                           .style('stroke','#ff0000')
                           .style("stroke-width", 0.5)
                           .style("filter", "url(#drop-shadow)");
        
    /* TEXT SECTION */
    var maxLength = getSetting(setting_textlength);             
                
    var fontSize = getSetting(setting_fontsize, 12);
    var textLength = getSetting(setting_textlength, 200);    
    var fontColor = getSetting(setting_textcolor, '#000000');

    var offset = (type=='record')?26:6;  
    var indent = 5;
    var position = 16;

    // Adding text
    var text;
    if(type=='record'){ // Nodes
        text = overlay.selectAll("text")
                      .data(info)
                      .enter()
                      .append("text")
                      .text(function(d) {
                          return d.text;
                      })
                      .attr("class", function(d, i) {
                          if(i>0 && d.subheader==1){ // d.style=='italic'
                                return 'info-mode-full namelabel';
                          }else{
                                return (i>0?'info-mode ':'nodelabel ')+'namelabel';     
                          }
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
                          if(d.multiline) {
                              if(d.xpos>0){
                                position = position + d.xpos;
                              }
                          }
                          return position; // Position calculation
                      })
                      .attr("fill", function(d) { 
                          return fontColor;
                      })
                      .attr("font-weight", function(d) {  // Font weight based on style property
                          return d.style;
                      })
                      .style("font-style", function(d) {   // Font style based on style property
                          return d.style;
                      }, "important")
                      .style("font-size", function(d) {   // Font size based on size property
                          return fontSize;//d.size;
                      }, "important");            
    }else{ // link information, onhover
      
      position = 0;
      var k, text = [[]];
      // Prepare icon + label combo prepare
      for(k = 0; k < info.length; k++){
          
          /* ICON */
          var linkicon = overlay
            .append("svg:image")
            .attr("class", 'icon info-mode')
            .attr("xlink:href", function(d) { // pick relation icon
                  
                if(info[k].type=='resource'){ // single arrow
                  return window.hWin.HAPI4.baseURL+'hclient/framecontent/visualize/arrow_1.png'; 
                }
                else if(info[k].type=='relmarker'){ // double arrow
                  return window.hWin.HAPI4.baseURL+'hclient/framecontent/visualize/arrow_2.png';
                }
                else{
                  return '';
                }
            })
            .attr("x", function(d) { // if the icon has been rotated it needs to be moved left to keep it next to text
                if(info[k].dir=='to' || $('#expand-links').is(':Checked')){
                  return 2;
                }else{
                  return -18;
                }
            }) 
            .attr("y", function(d, i) { // move relation icon down to sit next to text
                  return position + 7.5;
            })
            .attr("height", iconSize)
            .attr("width", iconSize);

          if(info[k].dir == 'from'){ // rotate icon
            linkicon.style("transform", "scaleX(-1)");
          }

          text[0].push(linkicon[0][0]);

          /* LABEL */
          var linkline = overlay.append("text")
            .text(info[k].text)
            .attr("class", 'info-mode')
            .attr("x", iconSize+2)
            .attr("y", function(d, i) { // calculate position
                position = position + (iconSize*1.2);
                return position;
            })
            .attr("fill", fontColor)
            .attr("font-weight", info[k].style)
            .style("font-style", info[k].style, "important")
            .style("font-size", fontSize, "important");
                    
                    
          text[0].push(linkline[0][0]);
      }
    }      
        
    // Calculate Box sizes
    var maxWidth = 1;
    var maxHeight = 10;                              
    var widthTitle = 1;
    for(var i = 0; i < text[0].length; i++) {
        var bbox = text[0][i].getBBox(); // get bounding box

        // Width
        var width = bbox.width;
        if(width > maxWidth) {
            maxWidth = width;
        }
        
        if(i==0) widthTitle = maxWidth;
        
        // Height
        var y = bbox.y*1.1;
        if(y > maxHeight) {
            maxHeight = y;
        }
    }
    maxWidth  += ((type=='record')?(offset):10)*2;//*3.5;
    maxHeight = maxHeight + offset*1;//fontSize*1 + 
      
    //drag and edit icons and actions for records
    if(type=='record'){
                 
      var drag2 = d3.behavior.drag()
         .on("dragstart", function(d, i){
            d3.event.sourceEvent.stopPropagation();

            drag_link_source_id = d.id;
           
            var bbox = $(".node.id"+d.id + " .foreground")[0].getBoundingClientRect();
            var svgPos = $("svg").position();
            var x = bbox.left + bbox.width/2 - svgPos.left;
            var y = bbox.top + bbox.height/2 - svgPos.top;  
            
            var dx = (x < (event.clientX - svgPos.left))?-2:2;
            var dy = (y < (event.clientY - svgPos.top))?-2:2;
            
            drag_link_line = svg.append("svg:line")
               .attr("stroke","#ff0000")
               .attr("stroke-width",4)
               .attr("x1", x).attr("y1", y)
               .attr("x2", event.clientX - svgPos.left+dx).attr("y2", event.clientY - svgPos.top+dy);
            
         })
         .on("drag", function(){
            if(drag_link_line){
                //drag_link_line
                //.attr("x2", Number(drag_link_line.attr("x2"))+d3.event.dx)
                //.attr("y2", Number(drag_link_line.attr("y2"))+d3.event.dy); //scale is not used
                var svgPos = $("svg").position();

            var dx = (drag_link_line.attr('x1') < (event.clientX - svgPos.left))?-2:2;
            var dy = (drag_link_line.attr('y1') < (event.clientY - svgPos.top))?-2:2;

                drag_link_line
                    .attr("x2", event.clientX - svgPos.left+dx)
                    .attr("y2", event.clientY - svgPos.top+dy);
                
            }
            
         })
         .on("dragend", function(){
             if(drag_link_source_id!=null && drag_link_target_id!=null){
                    _addNewLinkField(drag_link_source_id, drag_link_target_id);  
                    setTimeout(function(){drag_link_line.attr("stroke","#00ff00");}, 500);
             }else{
                drag_link_source_id = null;
                if(drag_link_line) drag_link_line.remove();
                drag_link_line = null;
             }
         });

      var icons_cnt = settings.isDatabaseStructure?3:2;

      widthTitle = widthTitle+(iconSize+3)*(icons_cnt+2);
      if(widthTitle>maxWidth) maxWidth = widthTitle;
      
      if( settings.isDatabaseStructure ||  settings.onRefreshData){
      
          //link button      
          var btnAddLink = overlay
                    .append("svg:image")
                    .attr("class", "icon info-mode")
                    .attr("xlink:href", function(d) {
                          return window.hWin.HAPI4.baseURL+'hclient/assets/edit-link.png';
                    })
                    .attr("transform", "translate("+(maxWidth-icons_cnt*iconSize-3)+",3)")
                    .attr("height", iconSize)
                    .attr("width", iconSize)
                    .on("mousedown", function(d) {
                        
                        var svgPos = $("svg").position();
                        var x = event.clientX - svgPos.left;
                        var y = event.clientY + 26 - svgPos.top 
                        
                        var hintoverlay = svg.append("g")
                                             .attr("class", "hintoverlay")
                                             .attr("transform", "translate(" +x +','+y+')');
                        
                        var hintrect = hintoverlay.append("rect")
                                                  .attr("class", "semi-transparant")              
                                                  .attr("x", 0)
                                                  .attr("y", 0)
                                                  .attr("rx", 0)
                                                  .attr("ry", 0)
                                                  .style('stroke','#000000')
                                                  .style("stroke-width", 0.5);
                           
                        var hinttext = hintoverlay.append("text")
                                                  .text('drag me to another node …')
                                                  .attr('x',3)                
                                                  .attr('y',10)
                                                  .attr("fill", fontColor)
                                                  .style("font-style", 'italic', "important")
                                                  .style("font-size", 10, "important");
                        
                        var bbox = hinttext[0][0].getBBox();
                        
                        hintrect.attr("width", bbox.width+6)
                                .attr("height", bbox.height+4);      

                        $('.hintoverlay').fadeOut(3000, function() {
                              $(this).remove();
                        });    
                    })
                    .call(drag2);

          btnAddLink.append("title")
                    .text(function(d) {
                         return 'Click and drag to another node to create link';
                    });
      }

      //edit button
      var btnEdit = overlay
            .append("svg:image")
            .attr("class", "icon info-mode")
            .attr("xlink:href", function(d) {
                  return window.hWin.HAPI4.baseURL+'hclient/assets/edit-pencil.png';
            })
            .attr("transform", "translate("+(maxWidth-(icons_cnt-1)*iconSize-3)+",3)")
            .attr("height", iconSize)
            .attr("width", iconSize)
            .on("mouseup", function(d) {

                event.preventDefault();
                
                if(settings.isDatabaseStructure){
                    if(window.hWin.HAPI4.is_admin())
                      _editRecStructure(rty_ID);    
                }else{
                      window.open(window.hWin.HAPI4.baseURL
                          +'?fmt=edit&db='+window.hWin.HAPI4.database+'&recID='+rec_ID, '_new');
                }
                
                
            });  
                
      if(settings.isDatabaseStructure){ 

        if (window.hWin.HAPI4.is_admin()) { // add edit button

            btnEdit.append("title")
                   .text(function(d) {
                        return 'Click to edit the entity / record type structure';
                   });
        }else{ // disabled buttons

            btnEdit.style('display', 'none');
            btnAddLink.style('display', 'none');
        }
      }else{ // add edit button
        
        btnEdit.append("title")
               .text(function(d) {
                    return 'Click to edit the record';
               });
      }
                

      if(settings.isDatabaseStructure){

        // Close button
        var close = overlay.append("g")
                           .attr("class", "close info-mode")
                           .attr("transform", "translate("+(maxWidth-10)+",3)")  //position of icon maxWidth-iconSize
                           .on("mouseup", function(d) {
                               $(".show-record[name='"+node_obj.name+"']").prop('checked', false).change();
                            });
                           
        // Close rectangle                                                                     
        close.append("rect")
             .attr("class", "close-button");
        
        // Close text        
        close.append("text")
             .attr("class", "close-text")
             .text("x")
             .attr("x", iconSize/4-3)
             .attr("y", 7);       
      }
                
    }else{
        maxHeight = maxHeight + 12;
    }
    
      
    // Set optimal width & height
    rect_full.attr("width", maxWidth+4)
             .attr("height", maxHeight);      
    
    if(type=='relation'){
        rect_info.attr("width", maxWidth+4)
                 .attr("height", maxHeight);      
    }else{
        rect_info.attr("width", maxWidth+4)
                 .attr("height", 26);
    }
    
    
    if(type=='record'){

      if(currentMode=='infoboxes'){

        rect_full.style('display', 'none');
        overlay.selectAll(".info-mode-full").style('display', 'none');
      }else if(currentMode=='infoboxes_full'){
          
        rect_info.style('display', 'none');
      }else if(currentMode=='icons'){
          
        overlay.selectAll('.info-mode').style('display', 'none');
        overlay.selectAll('.info-mode-full').style('display', 'none');
        overlay.selectAll(".rect-info-full").style('display', 'none');
        overlay.selectAll(".rect-info").style('display', 'none');
      }
    } 
   
   return overlay;     
}


//
// edit strcuture (from image link in table)
//
function _editRecStructure(rty_ID) {

    //edit structure (it opens fake record and switches to edit structure mode)
    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:{RecTypeID: rty_ID}, edit_structure:true});
    
}

/** Repositions all overlays */
function updateOverlays() {
    return;
    
    $(".overlay").each(function() {
        // Get information
        var pieces = $(this).attr("class").split(" ");
        var type = pieces[1];
        var id = pieces[2];
        
        // Select element to align to
        var bbox;
        if(type == "record") {
            bbox = $(".node."+id + " .foreground")[0].getBoundingClientRect(); //was icon  
        }else{
            bbox = $(".link."+id)[0].getBoundingClientRect();
        }
        //console.log(bbox);
        
        
        // Update position 
        var svgPos = $("svg").position();
        x = bbox.left + bbox.width/2 - svgPos.left;
        y = bbox.top + bbox.height/2 - svgPos.top;  
        //var iconSize = 16;
        $(this).attr("transform", "translate("+(x - iconSize/2 -3)+","+(y-iconSize/2 -3)+")");
        //$(this).attr("transform", "translate("+(x)+","+(y)+")");
        
        
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
function removeOverlay(selector, delay) {
    if(!(delay>=0)) delay = 1000;
    $(".overlay."+selector).fadeOut(delay, function() {
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

//
// open popup dialog to define new link or relationship
//
function _addNewLinkField(source_ID, target_ID){
    
            var body = $(this.document).find('body');
            var dim = { h:480, w:700 };//Math.max(900, body.innerWidth()-10) };                
            
            //ar target_ID = 10;
            
            var url = window.hWin.HAPI4.baseURL;
            var dlg_title = '';
            
            if(settings.isDatabaseStructure){
            
                url = url + "admin/structure/fields/selectLinkField.html?&db="
                    + window.hWin.HAPI4.database
                    + '&source_ID='+source_ID;
               
               dlg_title = 'Select or Create new link field type';   
            }else{
                
                _linkTwoRecords(source_ID, target_ID);
                return;
            }
                   
            if(target_ID>0){
                url = url +'&target_ID='+target_ID;
            }
            
            var hWin = window.hWin?window.hWin:top;
                
            hWin.HEURIST4.msg.showDialog(url, 
                {
                    "close-on-blur": false,
                    title: dlg_title,
                    height: dim.h,
                    width: dim.w,
                    afterclose: function(){
                        //remove link line
                        drag_link_source_id = null;
                        if(drag_link_line) drag_link_line.remove();
                        drag_link_line = null;
                    },
                    callback: function(context) {
                        
                        if(context!="" && context!=undefined) {
                            var sMsg = (context==true)?'Link created...':context;
                            hWin.HEURIST4.msg.showMsgFlash(sMsg, 2000);
                            if(settings.isDatabaseStructure){
                                getDataFromServer();    
                            }else if(settings.onRefreshData){
                                // Trigger refresh
                                settings.onRefreshData.call(this);
                            }
                            
                        }
                    }
              });
                
}

function _linkTwoRecords(source_ID, target_ID){
    
        function __onCloseAddLink(context){
                if(context && context.count>0 && settings.onRefreshData){
                            // Trigger refresh
                            settings.onRefreshData.call(this);
                }
                
                drag_link_source_id = null;
                if(drag_link_line) drag_link_line.remove();
                drag_link_line = null;
        }                            
        
        var opts = {
            source_ID: source_ID,
            onClose: __onCloseAddLink 
        };
        if(target_ID){
            opts['target_ID'] = target_ID;
        }
    
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);
    
    
}