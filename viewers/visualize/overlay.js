
/**
* overlay.js: Functions to handle node and relationship overlays
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global svg, settings, currentMode, 
drag_link_source_id, drag_link_target_id, drag_link_line,
getSetting, tick, filterData */

//global variables
window.drag_link_source_id = null;
window.drag_link_target_id = null;
window.drag_link_line = null;

let drag_link_timer = null;

// Functions to handle node and relationship overlays

//common function 
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

// called from selection - may be private
/** Finds all outgoing links from a clicked record */
function getRecordOverlayData(record) {

    let maxLength = getSetting('setting_textlength');
    let rectypeLength = 20;
    let array = [];

    // Header
    let header = {text: truncateText(window.hWin.HEURIST4.util.stripTags(record.name), rectypeLength), 
                  count: record.count, rtyid: record.id,
                  size: "9px", weight: "bold", height: 15, enter: true, image:record.image}; 

    if(settings.showCounts) {
        header.text += ", n=" + record.count;  
    }
    array.push(header);

    let fontSize = getSetting('setting_fontsize', 12);
    let xpos = 17;

    // Going through the current displayed data
    let data = settings.getData.call(this, settings.data);
    if(data && data.links.length > 0) {
        let map = {};
        for(let i = 0; i < data.links.length; i++) {
            let link = data.links[i];
            let isRequired = (settings.isDatabaseStructure && $Db.rst(link.source.rty_ID, link.relation.id, 'rst_RequirementType') == 'required') ? 'y' : 'n';

            if(link.relation.name == null && link.relation.type == 'resource'){
                link.relation.name = $Db.rst(link.source.rty_ID, link.relation.id, 'rst_DisplayName'); 
                //'Resource(s) id='+link.relation.id;
            }

            // Does our record point to this link?
            if(link.source.id == record.id) {
                // New name?
                if(!Object.hasOwn(map, link.relation.name)) {
                    map[link.relation.name] = {require_type: isRequired, dtyid: link.relation.id, weight: (isRequired == 'y') ? 'bold' : 'normal'};
                }

                if(!settings.isDatabaseStructure){
                    // Relation
                    let relation = {text: "➜ " + truncateText(window.hWin.HEURIST4.util.stripTags(link.target.name), maxLength), size: "8px", height: 11, subheader:1, xpos:xpos, multiline:true};
                    if(settings.showCounts) {
                        relation.text += ", n=" + link.targetcount;                      
                    }
                
                    // Add record relation to map
                    if(map[link.relation.name][relation.text] == undefined) {
                        //Displays list of connected records below the connection fields - could overload large graphs
                        //map[link.relation.name][relation.text] = relation;
                    }
                }
            }

            // Is our record a relation?
            if(link.relation.id == record.id && link.relation.name == record.name) {
                // New name?
                if(!Object.hasOwn(map, link.relation.name)) {
                    map[link.relation.name] = {require_type: isRequired, dtyid: link.relation.id, weight: (isRequired == 'y') ? 'bold' : 'normal'};
                }
               
                // Relation
                let relation = {text: truncateText(window.hWin.HEURIST4.util.stripTags(link.source.name), maxLength) + " ↔ " + truncateText(window.hWin.HEURIST4.util.stripTags(link.target.name), maxLength), 
                        size: "8px", height: fontSize, xpos:xpos, multiline:true};
                if(settings.showCounts) {
                    relation.text += ", n=" + link.relation.count
                }
                  
                // Add relation to map
                if(map[link.relation.name][relation.text] == undefined) {
                    map[link.relation.name][relation.text] = relation;
                }
            }
        }

        // Convert map to array
        for(let key in map) {
            let details = {text: truncateText(key, maxLength), size: "8px", xpos:xpos, multiline:true,
                    style:"italic", height: fontSize, enter: true, subheader:1};

            if(map[key]['require_type'] != null){
                details['require_type'] = map[key]['require_type'];
                delete map[key]['require_type'];
            }
            if(map[key]['weight'] != null){
                details['weight'] = map[key]['weight'];
                delete map[key]['weight'];
            }
            if(map[key]['rtyid'] != null){
                details['rtyid'] = map[key]['rtyid'];
                delete map[key]['rtyid'];
            }
            if(map[key]['dtyid'] != null){
                details['dtyid'] = map[key]['dtyid'];
                delete map[key]['dtyid'];
            }

            array.push(details); // Heading
            for(let text in map[key]) {
                array.push(map[key][text]);
            }
        }
    }

    return array;
}

// public from visualize
/** get info about particular relation */
function getRelationOverlayData(line) {
    let array = [];
    let maxLength = 60;

    // Header
    let header1 = truncateText(window.hWin.HEURIST4.util.stripTags(line.source.name), maxLength);
    let header2 = truncateText(window.hWin.HEURIST4.util.stripTags(line.target.name), maxLength);
    if(header1.length+header2.length > maxLength) {
        array.push({text: header1 + " >", size: "11px", style: "bold"});
        array.push({text: header2, size: "11px", style: "bold", enter: true});
    }else{
        array.push({text: header1+" > "+header2, size: "11px", style: "bold", enter: true}); 
    }

    let data = settings.getData.call(this, settings.data);
    if(data && data.links.length > 0 && $('#expand-links').is(':not(:Checked)')){

        for (let i = 0; i < data.links.length; i++){
            let link = data.links[i];

            let count = (!settings.isDatabaseStructure && link.targetcount <= 1) ? '' : ', n=' + link.targetcount;

            // Show information for all links, with same source and target ids
            if(link.source.id == line.source.id && link.target.id == line.target.id){
                let relation = {type: link.relation.type, cnt: link.targetcount, 
                        text: truncateText(window.hWin.HEURIST4.util.stripTags(link.relation.name), maxLength) + count, size: "10px", dir: "to"};

                array.push(relation);

                if(settings.isDatabaseStructure){

                    if($Db.rst(link.source.id, link.relation.id, 'rst_MaxValues') != 1){
                        array.push({text: 'multi value', size: '9px', style: 'italic', subheader:1});
                    }else{
                        array.push({text: 'single value', size: '9px', style: 'italic', subheader:1});
                    }
                }

                continue;
            }

            // Reverse Links, information about links that are sourced from the target
            if(link.source.id == line.target.id && link.target.id == line.source.id){
                let relation = {type: link.relation.type, cnt: link.targetcount, 
                        text: truncateText(window.hWin.HEURIST4.util.stripTags(link.relation.name), maxLength) + count, size: "10px", dir: "from"};

                array.push(relation);

                if(settings.isDatabaseStructure){

                    if($Db.rst(link.source.id, link.relation.id, 'rst_MaxValues') != 1){
                        array.push({text: 'multi value', size: '9px', style: 'italic', subheader:1});
                    }else{
                        array.push({text: 'single value', size: '9px', style: 'italic', subheader:1});
                    }
                }

                continue;
            }
        }
    }else{

        let count = (!settings.isDatabaseStructure && line.targetcount <= 1) ? '' : ', n=' + line.targetcount;

        // Show information for this link only
        let relation = {type: line.relation.type, cnt: line.targetcount, text: 
                truncateText(window.hWin.HEURIST4.util.stripTags(line.relation.name), maxLength) + count, size: "10px", subheader:0};

        array.push(relation);
    }

    return array;
}

//private 
/**
 * Get all record pointers (fields) that point towards a rectypes not shown yet
 * 
 * @param node_info current array of info 
 */
function addMissingFields(node_info){

    // Setup basic info
    let rty_id = node_info[0].rtyid; //record type id
    let records = $Db.rst(rty_id); //list of fields

    if(records == null){
        return node_info;
    }

    let record = records.getRecords();
    let order = records.getOrder(); //order and number of fields
    let count = order.length;

    //additional settings
    let xpos = 17;
    let maxLength = getSetting('setting_textlength');
    let fontSize = getSetting('setting_fontsize', 12);

    let new_fields = [];

    for(let i = 0; i < count; i++){

        let field = record[order[i]];
        let alreadyListed = false;

        // only record pointer or relamrkers
        if($Db.dty(field['rst_DetailTypeID'], 'dty_Type') != 'resource' && $Db.dty(field['rst_DetailTypeID'], 'dty_Type') != 'relmarker'){
            continue;
        }
        // only non-hidden fields
        if(field['rst_RequirementType'] == 'forbidden'){
            continue;
        }

        // check if field is already listed
        for(let j = 1; j < node_info.length; j++){

            if(node_info[j]['dtyid'] == field['rst_DetailTypeID']){
                alreadyListed = true;
                break;
            }
        }

        if(alreadyListed){
            continue;
        }

        // add new field
        new_fields.push({text: truncateText(window.hWin.HEURIST4.util.stripTags(field['rst_DisplayName']), maxLength), size: "8px", xpos:xpos, multiline:true, 
                        weight: (field['rst_RequirementType']=='required') ? "bold" : "normal", style:"italic", height: fontSize, enter: true, subheader: 1, 
                        require_type: (field['rst_RequirementType']=='required') ? 'y' : 'n', dtyid: field['rst_DetailTypeID']});
    }

    if(new_fields.length > 0){
        // add additional fields to original
        node_info = node_info.concat(new_fields);
    }

    return node_info;
}

//public 
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
    
    let info = node_obj;
    if(type=='record'){
        info = getRecordOverlayData(node_obj);
    }
    
    const iconSize = 16;
    const is_admin = window.hWin.HAPI4.is_admin();
    
    let overlay = null;
    let field_dividers = null;
    let divider = null;


    // Add overlay container    
    if(parent_node){
        overlay  = parent_node.append("g")
                              .attr("transform", "translate(" +(x-iconSize/2-6)+ "," +(y-iconSize/2-6)+ ")");
    }else{
        overlay = svg.append("g")
                     .attr("class", "overlay "+type+ " " + selector)      
                     .attr("transform", "translate(" +x +','+y+')');
    }
    
    let rty_ID = '', rec_ID = '';
    
    // Title
    info[0].text = window.hWin.HEURIST4.util.stripTags(info[0].text);
    let rollover = info[0].text;
    
    if(type=='record'){
        if(settings.isDatabaseStructure){
            rty_ID = selector.substr(2);
            const desc = $Db.rty(rty_ID, 'rty_Description');
            if(desc!=null){
                rollover = rollover + ' ' + desc;
            }else{
                console.error('Rectype not found '+rty_ID);
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
                window.drag_link_target_id = d.id;
                window.drag_link_line.attr("stroke","#00ff00");
            }            
        })
        .on("mouseout", function(d) {
            if(drag_link_source_id!=null){
                drag_link_timer = setTimeout(function(){
                    window.drag_link_target_id = null;
                    if(window.drag_link_line) window.drag_link_line.attr("stroke","#ff0000");
                },300);
            }            
        });
    }

    const outline_colour = (type == 'record') ? '#666' : '#ff0000';
    
    const nodecolor = getSetting('setting_entitycolor');

    // Draw a semi transparant rectangle       
    let rect_full = overlay.append("rect")
                           .attr("class", "semi-transparant info-mode-full rect-info-full")              
                           .attr("x", 0)
                           .attr("y", 0)
                           .attr("rx", 6)
                           .attr("ry", 6)
                           .attr("rtyid", info[0].rtyid)
                           .attr("fill", nodecolor)
                           .style('stroke', outline_colour)
                           .style("stroke-width", 0.75);

    let rect_info = overlay.append("rect")
                           .attr("class", "semi-transparant info-mode rect-info")              
                           .attr("x", 0)
                           .attr("y", 0)
                           .attr("rx", 6)
                           .attr("ry", 6)
                           .attr("rtyid", info[0].rtyid)
                           .attr("fill", nodecolor)
                           .style('stroke', outline_colour)
                           .style("stroke-width", 0.75);
        
    /* TEXT SECTION */                
    const fontSize = getSetting('setting_fontsize', 12);
    const textLength = getSetting('setting_textlength', 200);    
    const fontColor = getSetting('setting_textcolor', '#000000');

    let offset = (type=='record')?10:6;
    if (currentMode == 'icons'){
        offset = (type=='record')?29:25; 
    }
    let position = 16;

    // Adding text
    let text = [[]];
    if(type=='record'){ // Nodes

        if(settings.isDatabaseStructure){
            info = addMissingFields(info);
        }

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
                        return offset;
                      }) // Some left padding
                      .attr("y", function(d, i) {
                        // Multiline check
                        if(d.multiline) {
                            if(position == 16){
                                position += 3;
                            }
                            if(d.xpos>0){
                                position = position + d.xpos;
                            }
                        }
                        return position; // Position calculation
                      })
                      .attr("fill", function(d){
                        if(d.subheader == 1){ 
                            if(d.require_type == 'y'){
                                return '#CC0000'; 
                            }else{
                                return '#000000';
                            }
                        }
                        return fontColor;
                      })
                      .attr("font-weight", function(d) {  // Font weight based on weight property
                        return d.weight;
                      })
                      .attr("rtyid", function(d) { // Record type id
                        return d.rtyid;
                      })
                      .attr("dtyid", function(d) { // Detail type id
                        return d.dtyid;
                      })
                      .style("font-style", function(d) {   // Font style based on style property
                        return d.style;
                      }, "important")
                      .style("font-size", function(d) {   // Font size based on size property
                        return d.size;
                      }, "important");

        if(settings.isDatabaseStructure){

			// Display rectypes used by selected fields
            overlay.selectAll("text.info-mode-full, text.nodelabel").on("click", function(event){

                if(window.d3.event.defaultPrevented){
                    return;
                }

                let dtyid = event.dtyid;
                let rtyid = event.rtyid;

                let ids = '';

                if(rtyid && rtyid > 0){

                    ids = $Db.getLinkedRecordTypes_cache(rtyid, false, 'from');
                    ids = ids.join(', #');

                    ids = !window.hWin.HEURIST4.util.isempty(ids) ? `#${ids}` : '';
                }else if(dtyid && dtyid > 0){

                    ids = $Db.dty(event.dtyid, 'dty_PtrTargetRectypeIDs');
                    ids = ids.indexOf(',') !== -1 ? ids.replaceAll(/,/g, ', #') : ids;

                    ids = !window.hWin.HEURIST4.util.isempty(ids) ? `#${ids}` : '';
                }

                if(!window.hWin.HEURIST4.util.isempty(ids)){ // display linked record types
                    $('#records').find(ids).prop('checked', true).trigger('change');
                }

            }).style('cursor', 'pointer');
        }
    }else{ // link information, onhover

        position = 0;
        text = [[]];
        // Prepare icon + label combo prepare
        for(let k = 0; k < info.length; k++){

            /* ICON */
            let linkicon = overlay
                .append("svg:image")
                .attr("class", 'icon info-mode')
                .attr("xlink:href", function(d) { // pick relation icon

                    let type = info[k].type;
                    if(type != 'resource' && type != 'relmarker'){
                        return '';
                    }

                    // arrow_1 - single arrow for record pointers
                    // arrow_2 - double arrow for relationships
                    return `${window.hWin.HAPI4.baseURL}viewers/visualize/assets/arrow_${type == 'resource' ? 1 : 2}.png`;
                })
                .attr("x", function(d) { // if the icon has been rotated it needs to be moved left to keep it next to text
                    return info[k].dir=='to' || $('#expand-links').is(':Checked') ?
                                2 : -18;
                }) 
                .attr("y", function(d, i) { // move relation icon down to sit next to text
                    return settings.isDatabaseStructure && $('#expand-links').is(':Checked') ? 
                                position : (position + 7.5);
                })
                .attr("height", iconSize)
                .attr("width", iconSize);

            if(info[k].dir == 'from'){ // rotate icon
                linkicon.style("transform", "scaleX(-1)");
            }

            text[0].push(linkicon[0][0]);

            /* LABEL */
            let linkline = overlay.append("text")
                .text(info[k].text)
                .attr("class", 'info-mode')
                .attr("x", iconSize+2)
                .attr("y", function(d, i) { // calculate position
                    return position + (iconSize * (info[k].subheader == 1 ? 0.8 : 1.2));
                })
                .attr("fill", function(d){
                    return info[k].subheader == 1 ? 'gray' : fontColor;
                })
                .attr("font-weight", info[k].style)
                .style("font-style", info[k].style, "important")
                .style("font-size", info[k].size, "important");

            text[0].push(linkline[0][0]);
        }//for
    }
        
    // Calculate Box sizes
    let maxWidth = 1;
    let maxHeight = 10;                              
    let widthTitle = 1;
    for(let i = 0; i < text[0].length; i++) {
        let bbox = text[0][i].getBBox(); // get bounding box

        // Width
        const width = bbox.width;
        if(width > maxWidth) {
            maxWidth = width;
        }
        
        if(i==0) widthTitle = maxWidth;
        
        // Height
        const y = bbox.y*1.1;
        if(y > maxHeight) {
            maxHeight = y;
        }
    }

    if(maxWidth <= 1){
        maxWidth = 130; // Roughly enough space for 25 characters
    }

    maxWidth  += ((type=='record')?(offset):10)*2;
    maxHeight = maxHeight + offset*1;

    //drag and edit icons and actions for records
    if(type=='record'){

        let drag2 = window.d3.behavior.drag()
            .on("dragstart", function(d, i){
                window.d3.event.sourceEvent.stopPropagation();

                window.drag_link_source_id = d.id;

                let node = $(".node.id"+d.id);
                const x = node.offset().left - 5;
                const y = node.offset().top - 55;

                let svgPos = $("svg").position();
                const dx = (x < (event.clientX - svgPos.left))?-2:2;
                const dy = (y < (event.clientY - svgPos.top))?-2:2;

                window.drag_link_line = svg.append("svg:line")
                .attr("stroke","#ff0000")
                .attr("stroke-width",4)
                .attr("x1", x).attr("y1", y)
                .attr("x2", event.clientX - svgPos.left+dx)
                .attr("y2", event.clientY - svgPos.top+dy);
            })
            .on("drag", function(){
                if(window.drag_link_line){
                    let svgPos = $("svg").position();

                    const dx = (window.drag_link_line.attr('x1') < (event.clientX - svgPos.left))?-2:2;
                    const dy = (window.drag_link_line.attr('y1') < (event.clientY - svgPos.top))?-2:2;

                    window.drag_link_line
                        .attr("x2", event.clientX - svgPos.left+dx)
                        .attr("y2", event.clientY - svgPos.top+dy);

                }

            })
            .on("dragend", function(){
                if(window.drag_link_source_id!=null && window.drag_link_target_id!=null){
                        _addNewLinkField(window.drag_link_source_id, window.drag_link_target_id);  
                        setTimeout(function(){window.drag_link_line.attr("stroke","#00ff00");}, 500);
                }else{
                    drag_link_source_id = null;
                    if(window.drag_link_line) window.drag_link_line.remove();
                    window.drag_link_line = null;
                }
            });

        const icons_cnt = settings.isDatabaseStructure?4:3;

        widthTitle = widthTitle+(iconSize+3)*2;
        if(widthTitle>maxWidth) maxWidth = widthTitle;

        let menuarrow_transform = "translate("+(maxWidth-iconSize)+",3)";

        if(!settings.isDatabaseStructure || is_admin){

            overlay.append("svg:image")
                    .attr("class", "icon info-mode menu-close")
                    .attr("xlink:href", function(d){
                        return window.hWin.HAPI4.baseURL+'viewers/visualize/assets/arrow_1.png'
                    })
                    .attr("transform", menuarrow_transform)
                    .attr("height", iconSize)
                    .attr("width", iconSize)
                    .style('cursor', 'pointer')
                    .on("mouseup", function(d) {

                        let $icon = $(this);
                        
                        if($icon.hasClass('menu-close')){

                            let dem = this.getBBox();
                            const x = dem.x + dem.width / 2;
                            const y = dem.y + dem.height / 2;

                            let box_width = maxWidth + icons_cnt * iconSize - (settings.isDatabaseStructure ? 3 : 12);

                            $icon.attr('transform', menuarrow_transform + 'rotate(180,'+x+','+y+')');

                            // Set optimal width & height
                            rect_full.attr("width", box_width);
                            rect_info.attr("width", box_width);

                            if(divider != null) divider.attr("x2", box_width);

                            if(field_dividers != null) field_dividers.attr("x2", box_width);

                            $(overlay.node()).find('.addLink, .editBtn, .close').show();
                        }else{

                            $icon.attr('transform', menuarrow_transform);

                            // Set optimal width & height
                            rect_full.attr("width", maxWidth);  
                            rect_info.attr("width", maxWidth);

                            if(divider != null) divider.attr("x2", maxWidth);

                            if(field_dividers) field_dividers.attr("x2", maxWidth);

                            $(overlay.node()).find('.addLink, .editBtn, .close').hide();
                        }
                        $icon.toggleClass('menu-open menu-close');
                        tick();
                    });
        }

        let btnAddLink = null;

        if(!settings.isDatabaseStructure || settings.onRefreshData){

            //link button      
            btnAddLink = overlay
                        .append("svg:image")
                        .attr("class", "icon node-action addLink")
                        .attr("xlink:href", function(d) {
                            return window.hWin.HAPI4.baseURL+'hclient/assets/edit-link.png';
                        })
                        .attr("transform", "translate("+(maxWidth+(icons_cnt-3)*iconSize-3)+",3)")
                        .attr("height", iconSize)
                        .attr("width", iconSize)
                        .style('display', 'none')
                        .style('cursor', 'pointer')
                        .on("mousedown", function(d) {

                            let svgPos = $("svg").position();
                            const x = event.clientX - svgPos.left;
                            const y = event.clientY + 26 - svgPos.top;

                            let hintoverlay = svg.append("g")
                                                .attr("class", "hintoverlay")
                                                .attr("transform", "translate(" +x +','+y+')');
                            
                            let hintrect = hintoverlay.append("rect")
                                                    .attr("class", "semi-transparant")              
                                                    .attr("x", 0)
                                                    .attr("y", 0)
                                                    .attr("rx", 0)
                                                    .attr("ry", 0)
                                                    .style('stroke','#000000')
                                                    .style("stroke-width", 0.5);
                            
                            let hinttext = hintoverlay.append("text")
                                                    .text('drag me to another node …')
                                                    .attr('x',3)                
                                                    .attr('y',10)
                                                    .attr("fill", fontColor)
                                                    .style("font-style", 'italic', "important")
                                                    .style("font-size", 10, "important");

                            let bbox = hinttext[0][0].getBBox();

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
        let btnEdit = overlay
                .append("svg:image")
                .attr("class", "icon node-action editBtn")
                .attr("xlink:href", function(d) {
                    return window.hWin.HAPI4.baseURL+'hclient/assets/edit-pencil.png';
                })
                .attr("transform", "translate("+(maxWidth+(icons_cnt-2)*iconSize-3)+",3)")
                .attr("height", iconSize)
                .attr("width", iconSize)
                .style('display', 'none')
                .style('cursor', 'pointer')
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

            if (is_admin) { // add edit button

                btnEdit.append("title")
                    .text(function(d) {
                            return 'Click to edit the entity / record type structure';
                    });
            }else{ // disabled buttons

                btnEdit.style('display', 'none');
                if(btnAddLink != null){
                    btnAddLink.style('display', 'none');
                }
            }
        }else{ // add edit button
            
            btnEdit.append("title")
                .text(function(d) {
                        return 'Click to edit the record';
                });
        }
                    

        if(settings.isDatabaseStructure){

            let x_pos = is_admin ? maxWidth + (icons_cnt - 1) * iconSize - 3 : maxWidth - 13;

            // Close button
            let closeBtn = overlay.append("g")
                            .attr("class", "close node-action")
                            .attr("transform", `translate(${x_pos}, 7)`)
                            .on("mouseup", function(d) {
                                $(".show-record[name='"+node_obj.name+"']").prop('checked', false).trigger('change');
                            });

            // Close rectangle                                              
            closeBtn.append("rect")
                .attr("class", "close-button");

            // Close text
            closeBtn.append("text")
                .attr("class", "close-text")
                .text("x")
                .attr("x", iconSize/4-3)
                .attr("y", 7);
                
            if(is_admin){
                closeBtn.style('display', 'none');
            }
        }
                
    }else{
        maxHeight = maxHeight + 12;
    }
    
    // Set optimal width & height
    rect_full.attr("width", maxWidth)
             .attr("height", maxHeight);      
    
    if(type=='relation'){
        rect_info.attr("width", maxWidth)
                 .attr("height", maxHeight);      
    }else{
        rect_info.attr("width", maxWidth)
                 .attr("height", 26);
    }
    
    
    if(type=='record'){

        if(currentMode=='infoboxes'){

            rect_full.style('display', 'none');
            overlay.selectAll(".info-mode-full").style('display', 'none');

        }else if(currentMode=='infoboxes_full'){

            rect_info.style('display', 'none');

            if(info.length > 2){

                let rectype_details = info.shift(); // ignore rectangle "title" (rectype name)
                let last_field = info.pop(); // ignore last field
                let position1 = 26, position2 = 26; // for y1 and y2 values

                field_dividers = overlay.selectAll("line")
                                        .data(info)
                                        .enter()
                                        .append("svg:line")
                                        .attr("class", "inner_divider")
                                        .attr("X1", 0)
                                        .attr("y1", function(d){
                                            position1 += d.xpos;
                                            return position1;
                                        })
                                        .attr("x2", maxWidth)
                                        .attr("y2", function(d){
                                            position2 += d.xpos
                                            return position2;
                                        })
                                        .attr("stroke", "gray")
                                        .attr("stroke-width", 0.75);

                info.unshift(rectype_details); // re-add the shifted item
                info.push(last_field); // re-add the pop'd item
            }

            if(info.length > 1){

                // Add line between rectype and fields here
                divider = overlay.append("svg:line")
                                .attr("class", "inner_divider")
                                .attr("x1", 0)
                                .attr("y1", 23)
                                .attr("x2", maxWidth)
                                .attr("y2", 23)
                                .attr("stroke", "#666")
                                .attr("stroke-width", 1.25)
                                .attr("id", "line_divider");
            }

        }else if(currentMode=='icons'){
            
            overlay.selectAll('.info-mode').style('display', 'none');
            overlay.selectAll('.info-mode-full').style('display', 'none');
            overlay.selectAll(".rect-info-full").style('display', 'none');
            overlay.selectAll(".rect-info").style('display', 'none');
        }
    } 
   
   return overlay;     
}


// public (call here and from drag) - move to top
// edit strcuture (from image link in table)
//
function _editRecStructure(rty_ID) {

    //edit structure (it opens fake record and switches to edit structure mode)
    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:{RecTypeID: rty_ID}, edit_structure:true});
    
}

// public  - from visualize
/** Removes the overlay with the given ID */
function removeOverlay(selector, delay) {
    if(!(delay>=0)) delay = 1000;
    $(".overlay."+selector).fadeOut(delay, function() {
        $(this).remove();
   }); 
}


// not used
/** Removes all overlays */
function removeOverlays() {
    $(".overlay").each(function() {
        $(this).fadeOut(300, function() {
            $(this).remove();
        })
    });
}

// private
// open popup dialog to define new link or relationship
//
function _addNewLinkField(source_ID, target_ID){

    let body = $(this.document).find('body');
    let dim = { h:480, w:700 };//Math.max(900, body.innerWidth()-10) };                

    //ar target_ID = 10;

    let url = window.hWin.HAPI4.baseURL;
    let dlg_title = '';

    if(settings.isDatabaseStructure){

        url = url + "viewers/visualize/selectLinkField.php?&db="
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

    let hWin = window.hWin?window.hWin:top;

    hWin.HEURIST4.msg.showDialog(url, 
        {
            "close-on-blur": false,
            title: dlg_title,
            height: dim.h,
            width: dim.w,
            afterclose: function(){
                //remove link line
                drag_link_source_id = null;
                if(window.drag_link_line) window.drag_link_line.remove();
                window.drag_link_line = null;
            },
            callback: function(context) {
                
                if(context!="" && context!=undefined) {
                    const sMsg = (context==true)?'Link created...':context;
                    hWin.HEURIST4.msg.showMsgFlash(sMsg, 2000);
                    if(settings.isDatabaseStructure){
                        getDataFromServer();    
                    }else if(settings.onRefreshData){
                        // Trigger refresh
                        settings.onRefreshData.call(this);
                    }
                    
                }
            },
            default_palette_class:'ui-heurist-design'
        });

}

// private
// 
function _linkTwoRecords(source_ID, target_ID){

    function __onCloseAddLink(context){
        if(context && context.count>0 && settings.onRefreshData){
            // Trigger refresh
            settings.onRefreshData.call(this);
        }

        drag_link_source_id = null;
        if(window.drag_link_line) window.drag_link_line.remove();
        window.drag_link_line = null;
    }                            

    let opts = {
        source_ID: source_ID,
        onClose: __onCloseAddLink 
    };
    if(target_ID){
        opts['target_ID'] = target_ID;
    }

    window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);    
}

function getDataFromServer(){

    const url = window.hWin.HAPI4.baseURL+"hserv/controller/rectype_relations.php" + window.location.search;
    window.d3.json(url, function(error, json_data) {
        // Error check
        if(error) {
            window.hWin.HEURIST4.msg.showMsgErr({
                message: `Error loading JSON data: ${error.message}`,
                error_title: 'Unable to load diagram',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
        }
        
        settings.data = json_data; //all data
        filterData(json_data);
        
    });
    
}

