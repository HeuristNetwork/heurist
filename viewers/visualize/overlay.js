/**
* overlay.js: Functions to handle node and relationship overlays
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.7
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

class VisualiseOverlay{

    visualiser = null;

    #overlay = null;

    #iconCount = 4;
    #iconSize = 16;

    // Node information, right hand side
    infoDiv = null;
    infoFrame = null;
    infoBox = null;
    infoButtons = {};
    infoControllers = null;

    constructor(visualiserContext){

        this.visualiser = visualiserContext;
        this.#iconCount = this.visualiser.options.isStructure ? 4 : 3;

        this.#addInfoDiv();
    }

    addNodes(data){

        // Append nodes
        let nodes = window.d3.select('#container')
                .selectAll('.node')
                .data(data.nodes)
                .enter()
                .append('g')
                .on('dblclick', (data) => {
                    if(!this.visualiser.options.isStructure){ // Added Double Click to Edit Function
                        window.open(`${window.hWin.HAPI4.baseURL}?fmt=edit&db=${window.hWin.HAPI4.database}&recID=${data.id}`, '_blank');
                    }else if(window.hWin.HAPI4.is_admin()){
                        _editRecStructure(data.id);
                    }
                });

        let entitycolor = this.visualiser.settings.get('entitycolor');

        // Details for each node            
        nodes.each((data, index) => {

            // Restore location data
            let record = this.visualiser.settings.get(data.id);
            if(record){

                const obj = JSON.parse(record);
                if(obj.hasOwn('x')){
                    data.x = obj.x;
                }
                if(obj.hasOwn('y')){
                    data.y = obj.y;
                }
                if(obj.hasOwn('px')){
                    data.px = obj.px;
                }
                if(obj.hasOwn('py')){
                    data.py = obj.py;
                }
            }

            let node = nodes[0][index];

            let icon_display = this.visualiser.currentMode == 'icons' ? 'initial' : 'none';

            //add infobox
            this.createOverlay(0, 0, 'record', `id${data.id}`, data, node);

            //add outer circle
            node.append('circle')
                .attr('r', (data) => this.visualiser.getEntityRadius(data.count))
                .attr('class', 'background icon-background')
                .style({'fill-opacity': '0.5', display: icon_display})
                .attr('fill', (data) => this.#determineColour(data)); // entitycolor

            //add internal circle
            node.append('circle')
                .attr('r', this.visualiser.circleSize)
                .attr("class", 'foreground icon-foreground')
                .attr('fill', entitycolor)
                .style({stroke: "#ddd", display: icon_display})
                .style("stroke-opacity", (data) => data.selected === true ? 1 : .25);

            //add icon
            node.append('svg:image')
                .attr('class', 'icon node-icon')
                .attr('xlink:href', (data) => !window.hWin.HEURIST4.util.isempty(data.image) ? data.image : '')
                .attr('x', -this.#iconSize / 2)
                .attr('y', -this.#iconSize / 2)
                .attr('height', this.#iconSize)
                .attr('width', this.#iconSize)
                .on('mouseover', (data) => this.visualiser.drag.linkMouseover(data))
                .on('mouseout', () => this.visualiser.drag.linkMouseout())
                .style('display', icon_display);
                                
            let gravity = this.visualiser.settings.get('gravity');
            
            // Attributes
            node.attr('class', `node id${data.id}`)
                .attr('transform', 'translate(10, 10)')
                .attr('x', data.x)
                .attr('y', data.y)
                .attr('px', data.px)
                .attr('py', data.py)
                .attr('fixed', (data) => {
                    if(record && gravity == "off") {
                        data.fixed = true;
                        return true;
                    }
                    return false;
                })    
                .on('click', (data) => this.#onNodeClick(data))
                .on('contextmenu', (data) => this.#onNodeClick(data))
                .call(this.visualiser.drag.selectionDrag);
        });

        return nodes;
    }

    truncateText(text, maxLength){

        if(window.hWin.HEURIST4.util.isempty(text)){
            return '[no name]';
        }

        return text.length > maxLength ? `${text.substring(0, maxLength - 1)}…` : text;
    }

    #getRecordOverlayData(record){

        let maxLength = this.visualiser.settings.get('textlength');
        let rectypeLength = 20;
        let array = [];

        // Header
        let header = {text: this.truncateText(window.hWin.HEURIST4.util.stripTags(record.name), rectypeLength), 
                    count: record.count, rtyid: record.id,
                    size: '9px', weight: 'bold', height: 15, enter: true, image:record.image}; 

        if(this.visualiser.options.showCounts){
            header.text += `, n=${record.count}`;  
        }

        array.push(header);

        let fontSize = this.visualiser.settings.get('fontsize', 12);
        let xpos = 17;

        // Going through the current displayed data
        let data = this.visualiser.getCurrentData();
        if(!data && data.links.length == 0){
            return [];
        }

        let map = {};
        for(const link of data.links){

            let isRequired = this.visualiser.options.isStructure && $Db.rst(link.source.rty_ID, link.relation.id, 'rst_RequirementType') == 'required' ? 'y' : 'n';
            let sourceName = this.truncateText(window.hWin.HEURIST4.util.stripTags(link.source.name), maxLength);
            let targetName = this.truncateText(window.hWin.HEURIST4.util.stripTags(link.target.name), maxLength);
            let weight = isRequired == 'y' ? 'bold' : 'normal';
        
            if(link.relation.name == null && link.relation.type == 'resource'){
                link.relation.name = $Db.rst(link.source.rty_ID, link.relation.id, 'rst_DisplayName'); 
                //'Resource(s) id='+link.relation.id;
            }
        
            // Does our record point to this link?
            if(link.source.id == record.id){
                // New name?
                if(!Object.hasOwn(map, link.relation.name)) {
                    map[link.relation.name] = { require_type: isRequired, dtyid: link.relation.id, weight: weight };
                }
        
                if(!this.visualiser.options.isStructure){
                    // Relation
                    let relation = { text: `➜ ${targetName}`, size: '8px', height: 11, subheader: 1, xpos: xpos, multiline: true };
                    if(this.visualiser.options.showCounts) {
                        relation.text += `, n=${link.targetcount}`;                      
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
                    map[link.relation.name] = { require_type: isRequired, dtyid: link.relation.id, weight: weight };
                }
            
                // Relation
                let relation = { text: `${sourceName} ↔ ${targetName}`, size: '8px', height: fontSize, xpos: xpos, multiline: true };
                if(this.visualiser.options.showCounts) {
                    relation.text += `, n=${link.relation.count}`;
                }
                
                // Add relation to map
                if(map[link.relation.name][relation.text] == undefined) {
                    map[link.relation.name][relation.text] = relation;
                }
            }
        }

        // Convert map to array
        for(let key in map) {

            let details = { text: this.truncateText(key, maxLength), size: '8px', xpos: xpos, multiline: true, style: 'italic', height: fontSize, enter: true, subheader: 1 };

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

        return array;
    }

    #getRelationOverlayData(line){

        if(!this.visualiser.options.isStructure){
            return node_info;
        }

        let array = [];
        let maxLength = 60;

        // Header
        let header1 = this.truncateText(window.hWin.HEURIST4.util.stripTags(line.source.name), maxLength);
        let header2 = this.truncateText(window.hWin.HEURIST4.util.stripTags(line.target.name), maxLength);

        if(header1.length+header2.length > maxLength) {
            array.push({ text: `${header1} >`, size: '11px', style: 'bold' });
            array.push({ text: header2, size: '11px', style: 'bold', enter: true });
        }else{
            array.push({ text: `${header1} > ${header2}`, size: '11px', style: 'bold', enter: true }); 
        }

        let data = this.visualiser.getCurrentData();

        if(!data || data.links.leng === 0 || $('#expand-links').is(':checked')){

            let count = !this.visualiser.options.isStructure && line.targetcount <= 1 ? '' : ', n=' + line.targetcount;

            // Show information for this link only
            let text = `${this.truncateText(window.hWin.HEURIST4.util.stripTags(line.relation.name), maxLength)}${count}`;

            return [{ type: line.relation.type, cnt: line.targetcount, text: text, size: '10px', subheader: 0 }];
        }

        for(const link of data.links){

            let count = !this.visualiser.options.isStructure && link.targetcount <= 1 ? '' : `, n=${link.targetcount}`;
            const linkName = `${this.truncateText(window.hWin.HEURIST4.util.stripTags(link.relation.name), maxLength)}${count}`;

            // Show information for all links, with same source and target ids
            if(link.source.id == line.source.id && link.target.id == line.target.id){

                array.push({ type: link.relation.type, cnt: link.targetcount, text: linkName, size: '10px', dir: 'to' });

                if(this.visualiser.options.isStructure){

                    if($Db.rst(link.source.id, link.relation.id, 'rst_MaxValues') != 1){
                        array.push({ text: 'multi value', size: '9px', style: 'italic', subheader: 1 });
                    }else{
                        array.push({ text: 'single value', size: '9px', style: 'italic', subheader: 1 });
                    }
                }

                continue;
            }

            // Reverse Links, information about links that are sourced from the target
            if(link.source.id == line.target.id && link.target.id == line.source.id){

                array.push({ type: link.relation.type, cnt: link.targetcount, text: linkName, size: '10px', dir: 'from' });

                if(!this.visualiser.options.isStructure){
                    continue;
                }

                if($Db.rst(link.source.id, link.relation.id, 'rst_MaxValues') != 1){
                    array.push({ text: 'multi value', size: '9px', style: 'italic', subheader: 1 });
                }else{
                    array.push({ text: 'single value', size: '9px', style: 'italic', subheader: 1 });
                }

                continue;
            }
        }

        return array;
    }

    #addMissingFields(node_info){

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
        let maxLength = this.visualiser.settings.get('textlength');
        let fontSize = this.visualiser.settings.get('fontsize', 12);

        let new_fields = [];

        for(const recID of order){

            let field = record[recID];
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
            for(const node of node_info){
                if(node['dtyid'] == field['rst_DetailTypeID']){
                    alreadyListed = true;
                    break;
                }
            }
    
            if(alreadyListed){
                continue;
            }
    
            let weight = field['rst_RequirementType'] == 'required' ? 'bold' : 'normal';
            let isRequired = weight == 'bold' ? 'y' : 'n';
            let name = truncateText(window.hWin.HEURIST4.util.stripTags(field['rst_DisplayName']), maxLength);
    
            // add new field
            new_fields.push({
                text: name, size: '8px', xpos: xpos, multiline: true, weight: weight, style:"italic",
                height: fontSize, enter: true, subheader: 1, require_type: isRequired, dtyid: field['rst_DetailTypeID']
            });
        }

        if(new_fields.length > 0){
            // add additional fields to original
            node_info = node_info.concat(new_fields);
        }

        return node_info;
    }

    #addNodeInfo(info){

        const fontColor = this.visualiser.settings.get('textcolor', '#000');
        let position = 16;

        let offset = type == 'record' ? 10 : 6;
        if(currentMode == 'icons'){
            offset = type == 'record' ? 29 : 25;
        }

        if(this.visualiser.options.isStructure){
            info = this.#addMissingFields(info);
        }

        let textNodes = this.#overlay.selectAll('text')
                    .data(info)
                    .enter()
                    .append('text')
                    .text((data) => data.text)
                    .attr('class', (data, index) => {
                        if(index > 0 && data.subheader==1){
                            return 'info-mode-full namelabel';
                        }else{
                            return `${index > 0 ? 'info-mode' : 'nodelabel'} namelabel`;
                        }
                    })
                    .attr('x', () => offset) // Some left padding
                    .attr('y', (data) => {
                        // Multiline check
                        if(data.multiline) {
                            if(position == 16){
                                position += 3;
                            }
                            if(window.hWin.HEURIST4.util.isPositiveInt(data.xpos)){
                                position = position + data.xpos;
                            }
                        }
                        return position; // Position calculation
                    })
                    .attr('fill', (data) => {
                        if(data.subheader == 1){
                            return data.require_type == 'y' ? '#CC0000' : '#000';
                        }
                        return fontColor;
                    })
                    .attr('font-weight', (data) => data.weight) // Font weight based on weight property
                    .attr('rtyid', (data) => data.rtyid) // Record type id
                    .attr('dtyid', (data) => data.dtyid) // Detail type id
                    .style('font-style', (data) => data.style, 'important') // Font style based on style property
                    .style('font-size', (data) => data.size, 'important'); // Font size based on size property

        if(this.visualiser.options.isStructure){

			// Display rectypes used by selected fields
            this.#overlay.selectAll('text.info-mode-full, text.nodelabel').on('click', (data) => {

                if(window.d3.event.defaultPrevented){
                    return;
                }

                let dtyid = data.dtyid;
                let rtyid = data.rtyid;

                let ids = '';

                if(window.hWin.HEURIST4.util.isPositiveInt(rtyid)){

                    ids = $Db.getLinkedRecordTypes_cache(rtyid, false, 'from');
                    ids = ids.join(', #');

                    ids = !window.hWin.HEURIST4.util.isempty(ids) ? `#${ids}` : '';
                }else if(window.hWin.HEURIST4.util.isPositiveInt(dtyid)){

                    ids = $Db.dty(data.dtyid, 'dty_PtrTargetRectypeIDs');
                    ids = ids.indexOf(',') !== -1 ? ids.replaceAll(/,/g, ', #') : ids;

                    ids = !window.hWin.HEURIST4.util.isempty(ids) ? `#${ids}` : '';
                }

                if(!window.hWin.HEURIST4.util.isempty(ids)){ // display linked record types
                    $('#records').find(ids).prop('checked', true).trigger('change');
                }

            }).style('cursor', 'pointer');
        }else{
            // Add context menu for nodes, allowing users to expand node connections
            // @todo: allow for both vis, disabled and hide based on: this.visualiser.options.isStructure and typeof onExpandNode === 'function'

            let data = this.#overlay.data();

            $(this.#overlay.node()).contextmenu({
                delegate: 'text.nodelabel',
                position: (event, ui) => ({my: 'left top', at: 'right+5 top', of: ui.target }),
                menu: [
                    {title: 'Get all linked and related records', cmd: 'links', data: {id: data[0].id}},
                    {title: 'Get Relationship markers:', isHeader: true},
                    {title: 'Related To', cmd: 'related_to', data: {id: data[0].id}},
                    {title: 'Related From', cmd: 'related_from', data: {id: data[0].id}},
                    {title: 'Related Both ways', cmd: 'related', data: {id: data[0].id}},
                    {title: 'Get Record pointers:', isHeader: true},
                    {title: 'Linked To', cmd: 'linked_to', data: {id: data[0].id}},
                    {title: 'Linked From', cmd: 'linked_from', data: {id: data[0].id}},
                    //{title: 'Linked Both ways', cmd: 'linked', data: {id: data[0].id}} @todo - need to add handling for linked, performs both to and from like related
                    //{title: 'Remove', isHeader: true},
                    //{title: 'Node', cmd: 'remove_node', data: {id: data[0].id}},
                    //{title: 'Connected Nodes', cmd: 'remove_connections', data: {id: data[0].id}}
                ],
                select: (event, ui) => {

                    let cmd = ui.cmd;
                    let rec_ID = ui.item.data().id;

                    if(cmd !== 'viewer'){
                        this.visualiser.expandNode(cmd, rec_ID);
                        return;
                    }

                    this.showNodeInformation(ui.item.data());
                },
                beforeOpen: (event, ui) => {
                    let style = $(ui.menu).attr('style');

                    if(style.indexOf('100000') == -1){
                        style += 'z-index: 100000 !important;';
                        $(ui.menu).attr('style', style);
                    }
                }
            });
        }

        return textNodes;
    }

    #addLinkInfo(info){

        const fontColor = this.visualiser.settings.get('textcolor', '#000');
        let position = 0;

        let textNodes = [[]];

        // Prepare icon + label combo prepare
        for(const link of info){

            /* ARROW ICON */
            let linkicon = this.#overlay.append('svg:image')
                .attr('class', 'icon info-mode')
                .attr('xlink:href', () => { // pick relation icon

                    let type = link.type;
                    if(type != 'resource' && type != 'relmarker'){
                        return '';
                    }

                    // arrow_1 - single arrow for record pointers
                    // arrow_2 - double arrow for relationships
                    return `${window.hWin.HAPI4.baseURL}viewers/visualize/assets/arrow_${type == 'resource' ? 1 : 2}.png`;
                })
                .attr('x', (d) => link.dir=='to' || $('#expand-links').is(':checked') ? 2 : -18) // if the icon has been rotated it needs to be moved left to keep it next to text
                .attr('y', () => this.visualiser.options.isStructure && $('#expand-links').is(':checked') ? position : (position + 7.5)) // move relation icon down to sit next to text
                .attr('height', this.#iconSize)
                .attr('width', this.#iconSize);

            if(link.dir == 'from'){ // rotate icon
                linkicon.style('transform', 'scaleX(-1)');
            }

            textNodes[0].push(linkicon.node());

            /* LABEL */
            let linkline = this.#overlay.append('text')
                .text(link.text)
                .attr('class', 'info-mode')
                .attr('x', this.#iconSize + 2)
                .attr('y', () => position + (this.#iconSize * ( k + 1 ) * (link.subheader == 1 ? 0.8 : 1.2))) // calculate position
                .attr('fill', () => link.subheader == 1 ? 'gray' : fontColor)
                .attr('font-weight', link.style)
                .style('font-style', link.style, 'important')
                .style('font-size', link.size, 'important');
                
            textNodes[0].push(linkline.node());
            
        }//for

        return textNodes;
    }

    createOverlay(x, y, type, selector, nodeObject, parent_node) {

        let info = [];
        if(type=='record'){
            info = this.#getRecordOverlayData(nodeObject);
        }
        info = this.#getRelationOverlayData(nodeObject);

        const is_admin = window.hWin.HAPI4.is_admin();

        // Add overlay container    
        if(parent_node){
            this.#overlay = parent_node.append('g')
                                .attr('transform', `translate(${x - this.#iconSize / 2 - 6},${y - this.#iconSize / 2 - 6})`);
        }else{
            this.#overlay = svg.append('g')
                        .attr('class', `overlay ${type} ${selector}`)      
                        .attr('transform', `translate(${x},${y})`);
        }

        let rty_ID = '', rec_ID = '';

        // Title
        info[0].text = window.hWin.HEURIST4.util.stripTags(info[0].text);
        let rollover = info[0].text;

        if(type=='record'){

            if(this.visualiser.options.isStructure){

                rty_ID = selector.substring(2);
                const desc = $Db.rty(rty_ID, 'rty_Description');

                if(desc != null){
                    rollover += ` ${desc}`;
                }else{
                    console.error(`Rectype not found ${rty_ID}`);
                }
            }else{
                rec_ID = selector.substring(2);
            }

        }
        
        if(parent_node){
            parent_node
                .on('mouseover', () => this.visualiser.drag.linkMouseover())
                .on('mouseout', () => this.visualiser.drag.linkMouseout());
        }

        const outline_colour = (type == 'record') ? '#666' : '#ff0000';
        
        const nodecolor = this.visualiser.settings.get('entitycolor');

        // Draw a semi transparant rectangle       
        let rect_full = this.#overlay.append('rect')
                            .attr('class', 'semi-transparant info-mode-full rect-info-full')
                            .attr('x', 0)
                            .attr('y', 0)
                            .attr('rx', 6)
                            .attr('ry', 6)
                            .attr('rtyid', info[0].rtyid)
                            .attr('fill', nodecolor)
                            .style('stroke', outline_colour)
                            .style('stroke-width', 0.75);

        let rect_info = this.#overlay.append('rect')
                            .attr('class', 'semi-transparant info-mode rect-info')
                            .attr('x', 0)
                            .attr('y', 0)
                            .attr('rx', 6)
                            .attr('ry', 6)
                            .attr('rtyid', info[0].rtyid)
                            .attr('fill', nodecolor)
                            .style('stroke', outline_colour)
                            .style('stroke-width', 0.75);

        let offset = type == 'record' ? 10 : 6;
        if(currentMode == 'icons'){
            offset = type == 'record' ? 29 : 25;
        }

        // Adding text
        let text = [[]];
        if(type=='record'){ // Nodes
            text = this.#addNodeInfo(info);
        }else{ // link information, onhover
            text = this.#addLinkInfo(info);
        }
            
        // Calculate Box sizes
        let maxWidth = 1;
        let maxHeight = 10;                              
        let widthTitle = 1;
        for(const node of text[0]){

            let bbox = node.getBBox(); // get bounding box

            // Width
            const width = bbox.width;
            if(width > maxWidth) {
                maxWidth = width;
            }
            
            if(i == 0) widthTitle = maxWidth;
            
            // Height
            const y = bbox.y * 1.1;
            if(y > maxHeight) {
                maxHeight = y;
            }
        }

        if(maxWidth <= 1){
            maxWidth = 130; // Roughly enough space for 25 characters
        }

        maxWidth += (type == 'record' ? offset : 10) * 2;
        maxHeight = maxHeight + offset * 1;

        //drag and edit icons and actions for records
        if(type=='record'){

            widthTitle += (this.#iconSize + 3) * 2;
            if(widthTitle > maxWidth) maxWidth = widthTitle;

            if(!this.visualiser.options.isStructure || is_admin){
                this.#addExpanderButton(maxWidth, rect_full, rect_info);
            }

            if(!this.visualiser.options.isStructure){
                this.#addLinkButton(maxWidth);
            }

            this.#addEditButton(maxWidth, rty_ID);

            if(this.visualiser.options.isStructure){
                this.#addRemoveButton(maxWidth);
            }
        }else{
            maxHeight = maxHeight + 12;
        }
        
        // Set optimal width & height
        rect_full.attr('width', maxWidth)
                .attr('height', maxHeight);
        
        if(type=='relation'){
            rect_info.attr('width', maxWidth)
                    .attr('height', maxHeight);
        }else{
            rect_info.attr('width', maxWidth)
                    .attr('height', 26);
        }

        if(type != 'record'){
            return;
        }

        if(currentMode=='infoboxes'){

            rect_full.style('display', 'none');
            this.#overlay.selectAll(".info-mode-full").style('display', 'none');
        }else if(currentMode=='infoboxes_full'){

            rect_info.style('display', 'none');

            if(info.length > 2){

                let rectype_details = info.shift(); // ignore rectangle "title" (rectype name)
                let last_field = info.pop(); // ignore last field
                let positionX = 26, positionY = 26; // for y1 and y2 values

                this.#overlay.selectAll('line')
                        .data(info)
                        .enter()
                        .append('svg:line')
                        .attr('class', 'innerDividers')
                        .attr('X1', 0)
                        .attr('y1', (data) => {
                            positionX += data.xpos;
                            return positionX;
                        })
                        .attr('x2', maxWidth)
                        .attr('y2', (data) => {
                            positionY += data.xpos
                            return positionY;
                        })
                        .attr('stroke', 'gray')
                        .attr('stroke-width', 0.75);

                info.unshift(rectype_details); // re-add the shifted item
                info.push(last_field); // re-add the pop'd item
            }

            if(info.length > 1){

                // Add line between rectype and fields here
                this.#overlay.append('svg:line')
                        .attr('class', 'innerDividers')
                        .attr('x1', 0)
                        .attr('y1', 23)
                        .attr('x2', maxWidth)
                        .attr('y2', 23)
                        .attr('stroke', '#666')
                        .attr('stroke-width', 1.25)
                        .attr('id', 'line_divider');
            }

        }else if(currentMode=='icons'){
            this.#overlay.selectAll('.info-mode, .info-mode-full, .rect-info-full, .rect-info').style('display', 'none');
        }
    }

    #addExpanderButton(maxWidth, rectFull, rectMin){

        let iconPlacement = `translate(${maxWidth - this.#iconSize},3)`;

        this.#overlay.append('svg:image')
                .attr('class', 'icon info-mode menu-close')
                .attr('xlink:href', () => `${window.hWin.HAPI4.baseURL}viewers/visualize/assets/arrow_1.png`)
                .attr('transform', iconPlacement)
                .attr('height', this.#iconSize)
                .attr('width', this.#iconSize)
                .style('cursor', 'pointer')
                .on('mouseup', () => {

                    let $icon = $(window.d3.event.target);
                    
                    if($icon.hasClass('menu-close')){

                        let dem = this.getBBox();
                        const x = dem.x + dem.width / 2;
                        const y = dem.y + dem.height / 2;

                        let box_width = maxWidth + this.#iconCount * this.#iconSize - (this.visualiser.options.isStructure ? 3 : 12);

                        $icon.attr('transform', `${iconPlacement}rotate(180,${x},${y})`);

                        // Set optimal width & height
                        rectFull.attr('width', box_width);
                        rectMin.attr('width', box_width);

                        rectFull.selectAll('.innerDividers').attr('x2', box_width);

                        $(this.#overlay.node()).find('.addLink, .editBtn, .close').show();
                    }else{

                        $icon.attr('transform', iconPlacement);

                        // Set optimal width & height
                        rectFull.attr('width', maxWidth);  
                        rectMin.attr('width', maxWidth);

                        rectFull.selectAll('.innerDividers').attr('x2', maxWidth);

                        $(this.#overlay.node()).find('.addLink, .editBtn, .close').hide();
                    }

                    $icon.toggleClass('menu-open menu-close');
                    this.visualiser.tick();
                });
    }

    #addLinkButton(maxWidth){

        const fontColor = this.visualiser.settings.get('textcolor', '#000');
        let iconPosition = maxWidth + (this.#iconCount - 3) * this.#iconSize - 3;

        //link button      
        let btnLink = this.#overlay.append('svg:image')
            .attr('class', 'icon node-action addLink')
            .attr('xlink:href', () => `${window.hWin.HAPI4.baseURL}hclient/assets/edit-link.png`)
            .attr('transform', `translate(${iconPosition},3)`)
            .attr('height', this.#iconSize)
            .attr('width', this.#iconSize)
            .style('display', 'none')
            .style('cursor', 'pointer')
            .on('mousedown', () => {

                let event = window.d3.event.sourceEvent;

                let svgPos = this.visualiser.svg.position();
                const x = event.clientX - svgPos.left;
                const y = event.clientY + 26 - svgPos.top;

                let hintoverlay = this.visualiser.svg.append('g')
                                    .attr('class', 'hintoverlay')
                                    .attr('transform', `translate(${x},${y})`);
                
                let hintrect = hintoverlay.append('rect')
                                        .attr('class', 'semi-transparant')
                                        .attr('x', 0)
                                        .attr('y', 0)
                                        .attr('rx', 0)
                                        .attr('ry', 0)
                                        .style('stroke','#000')
                                        .style('stroke-width', 0.5);
                
                let hinttext = hintoverlay.append('text')
                                        .text('drag me to another node …')
                                        .attr('x', 3)                
                                        .attr('y', 10)
                                        .attr('fill', fontColor)
                                        .style('font-style', 'italic', 'important')
                                        .style('font-size', 10, 'important');

                let bbox = hinttext.node().getBBox();

                hintrect.attr('width', bbox.width+6)
                        .attr('height', bbox.height+4);

                $('.hintoverlay').fadeOut(3000, function() {
                    $(this).remove();
                });
            })
            .call(this.visualiser.drag.linkDrag);

        btnLink.append('title').text(() => 'Click and drag to another node to create link');

        if(this.visualiser.options.isStructure && !window.hWin.HAPI4.is_admin()){
            btnLink.style('display', 'none');
        }
    }

    #addEditButton(maxWidth, rty_ID){

        if(this.visualiser.options.isStructure && !window.hWin.HAPI4.is_admin()){
            return;
        }

        let iconPosition = maxWidth + (this.#iconCount - 2) * this.#iconSize - 3;

        //edit button
        let btnEdit = this.#overlay
                .append('svg:image')
                .attr('class', 'icon node-action editBtn')
                .attr('xlink:href', () => `${window.hWin.HAPI4.baseURL}hclient/assets/edit-pencil.png`)
                .attr('transform', `translate(${iconPosition},3)`)
                .attr('height', this.#iconSize)
                .attr('width', this.#iconSize)
                .style('display', 'none')
                .style('cursor', 'pointer')
                .on('mouseup', () => {

                    let event = window.d3.event.sourceEvent;

                    event.preventDefault();
                    
                    if(!this.visualiser.options.isStructure){
                        window.open(`${window.hWin.HAPI4.baseURL}?fmt=edit&db=${window.hWin.HAPI4.database}&recID=${rec_ID}`, '_new');
                    }else if(window.hWin.HAPI4.is_admin()){
                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, { new_record_params: { RecTypeID: rty_ID }, edit_structure: true });
                    }  
                });

        if(this.visualiser.options.isStructure){ 
            btnEdit.append("title").text(() => 'Click to edit the entity / record type structure');
        }else{ // add edit button
            btnEdit.append("title").text(() => 'Click to edit the record');
        }
    }

    #addRemoveButton(maxWidth){

        let x_pos = window.hWin.HAPI4.is_admin() ? maxWidth + (this.#iconCount - 1) * this.#iconSize - 3 : maxWidth - 13;

        // Close button
        let btnClose = this.#overlay.append('g')
                .style('display', 'none')
                .attr('class', 'close node-action')
                .attr('transform', `translate(${x_pos}, 7)`)
                .on('mouseup', () => $(`.show-record[name='${nodeObject.name}']`).prop('checked', false).trigger('change'));

        // Close rectangle                                              
        btnClose.append('rect').attr('class', 'close-button');

        // Close text
        btnClose.append('text')
                .attr('class', 'close-text')
                .text('x')
                .attr('x', this.#iconSize / 4 - 3)
                .attr('y', 7);
    }

    removeOverlay(selector, delay){

        if(delay <= 0) delay = 1000;

        $(`.overlay.${selector}`).fadeOut(delay, function(){
            $(this).remove();
        });
    }

    addNewLinkField(source_ID, target_ID){

        let dim = { h: 480, w: 700 };

        if(!this.visualiser.options.isStructure){
            this.#linkTwoRecords(source_ID, target_ID);
            return;
        }

        let url = `${window.hWin.HAPI4.baseURL}viewers/visualize/selectLinkField.php?&db=${window.hWin.HAPI4.database}&source_ID=${source_ID}`
        let dlg_title = 'Select or Create new link field type';   

        if(window.hWin.HEURIST4.util.isPositiveInt(target_ID)){
            url += `&target_ID=${target_ID}`;
        }

        let hWin = window.hWin ? window.hWin : top;

        hWin.HEURIST4.msg.showDialog(url, {
            "close-on-blur": false,
            title: dlg_title,
            height: dim.h,
            width: dim.w,
            afterclose: () => {
                //remove link line
                this.visualiser.drag.removeLink();
            },
            callback: (context) => {

                if(!context || context == ''){
                    return;
                }
                
                const sMsg = context == true ? 'Link created...' : context;
                hWin.HEURIST4.msg.showMsgFlash(sMsg, 3000);

                if(this.visualiser.options.isStructure){
                    this.#getDataFromServer();    
                }else{
                    // Trigger refresh
                    this.visualiser.refreshData();
                }
            },
            default_palette_class:'ui-heurist-design'
        });

    }

    #linkTwoRecords(source_ID, target_ID){

        function __onCloseAddLink(context){
            if(window.hWin.HEURIST4.util.isPositiveInt(context?.count)){
                // Trigger refresh
                this.visualiser.refreshData();
            }

            this.visualiser.drag.removeLink();
        }

        let opts = {
            source_ID: source_ID,
            onClose: (context) => __onCloseAddLink(context) 
        };
        if(target_ID){
            opts['target_ID'] = target_ID;
        }

        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);
    }

    #getDataFromServer(){

        const url = `${window.hWin.HAPI4.baseURL}hserv/controller/rectype_relations.php${window.location.search}`;

        window.d3.json(url, function(error, json_data) {
            // Error check
            if(error) {
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: `Error loading JSON data: ${error.message}`,
                    error_title: 'Unable to load diagram',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
            }

            this.visualiser.data = json_data; //all data
            this.visualiser.filterData(json_data);
        });
    }

    #onNodeClick(data){

        $('body.popup div.layout-container').first().layout().close('west');

        // Check if it's not a click after dragging
        if(!window.d3.event.defaultPrevented){
            this.showNodeInformation(data); // Load record details
        }

        if(this.visualiser.options.isStructure || !window.hWin.HEURIST4.util.isPositiveInt(data?.id)){
            return;
        }

        this.visualiser.selection.onSelection(data);
    }

    showNodeInformation(data){

        if(this.infoDiv.length == 0 || this.infoFrame.length == 0 || this.infoBox.length == 0){
            return;
        }

        let $infoDiv = $(this.infoDiv.node());

        if($infoDiv.resizable('instance') === undefined){ // setup resizing
            $infoDiv.resizable({
                maxHeight: 400,
                minHeight: this.visualiser.options.isStructure ? 150 : 300,
                resize: (event, ui) => {
                    infoFrame.style('height', `${$infoDiv.height()}px`);
                    infoBox.style('height', `${$infoDiv.height()}px`);
                },
                handles: 's'
            });
        }

        this.infoDiv.style("display", "block"); // make info div visible

        function displayRecordViewer(){

            this.infoControllers.style('display', 'block');
            this.infoFrame.style('display', 'inline');
            this.infoBox.style('display', 'none');

            if(this.infoFrame.attr("data-hid") == data.id){ // block retrival of last record in quick succession
                return;
            }

            window.hWin.HEURIST4.msg.bringCoverallToFront(this.infoDiv, {'background-color': 'white', 'opacity': 1, 'font-weight': 'bold', 'font-size': 'smaller', 'color': 'black'}, 
                `Loading<br><br>${window.hWin.HEURIST4.util.stripTags(truncateText(data.name, 40))}`);

            const srcURL = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?noclutter=1&recID=${data.id}&db=${window.hWin.HAPI4.database}`; // URL for source of information iframe

            this.infoFrame.attr("src", srcURL)
                    .attr("data-hid", data.id)
                    .on('load', () => {

                        window.hWin.HEURIST4.msg.sendCoverallToBack(true);

                        let viewMaxHeight = document.querySelector('#divSvg').scrollHeight;
                        viewMaxHeight = viewMaxHeight <= 0 ? 500 : viewMaxHeight - 20;

                        let height = this.infoFrame.node().contentWindow.document.body.scrollHeight;
                        height += 15;

                        if(height <= 100 || height >= viewMaxHeight){
                            height = viewMaxHeight
                        }

                        this.infoFrame.style('height', `${height}px`);

                        this.infoDiv.style('max-height', `${height}px`);
                        this.infoDiv.style('height', `${height}px`);
                        $infoDiv.resizable('option', 'maxHeight', height);
                    });//supply document to iframe
        }

        function displayRecTypeInfo(){

            this.infoControllers.style('display', 'none');
            this.infoButtons.close.style('display', 'block');
            this.infoFrame.style('display', 'none');
            this.infoBox.style('display', 'block');

            if(infoBox.attr("data-rtyID") == data.id){ // block retrival of last record in quick succession
                return;
            }

            let recType = $Db.rty(data.id);

            let icon_URL = window.hWin.HAPI4.getImageUrl('rty', recType.rty_ID, 'thumb', 2, window.hWin.HAPI4.database);
            let rty_Icon = `<img
            height="25" width="25"
            src="${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif"
            class="rt-icon"
            style="background-image: url(&quot;${icon_URL}&quot;);" />`;

            let rectypeDetails = `<br>
            ${rty_Icon}<br><br>
            <strong>ID</strong>: ${recType.rty_ID}<br>
            <strong>Name</strong>: ${recType.rty_Name}<br>
            <strong>Count</strong>: ${recType.rty_RecCount}<br>
            <strong>Description</strong>:<br>${recType.rty_Description}<br>
            `;

            this.infoBox.attr('data-rtyID', data.id)
                .html(rectypeDetails);

            let viewMaxHeight = document.querySelector('#divSvg').scrollHeight;
            viewMaxHeight = viewMaxHeight <= 0 ? 500 : viewMaxHeight - 20;

            let height = this.infoBox.node().scrollHeight;
            height += 15;

            if(height <= 100 || height >= viewMaxHeight){
                height = viewMaxHeight
            }

            this.infoBox.style('height', `${height}px`);

            this.infoDiv.style('max-height', `${height}px`);
            this.infoDiv.style('height', `${height}px`);
            $infoDiv.resizable('option', 'maxHeight', height);
        }

        if(this.visualiser.options.isStructure){
            displayRecTypeInfo();
        }else{
            displayRecordViewer();
        }

    }

    #addInfoDiv(){

        if(window.d3.select('#infoDiv').length > 0){
            this.#assignInfoDivVars();
            return;
        }

        this.infoDiv = window.d3.select('body').append('div').attr('id', 'infoDiv');
        this.infoFrame = this.infoDiv.append('iframe').attr('id', 'infoIframe');
        this.infoBox = this.infoDiv.append('div').attr('id', 'infoBox');

        this.infoBox.style('padding', '10px');

        this.infoButtons = {
            tab: this.infoDiv.append('button').attr('id', 'btnCtrlNewtab').attr('class', 'iframeControls').attr('title', 'Open in new tab').on('click', () => this.#handleInfoAction('tab')),
            expand: this.infoDiv.append('button').attr('id', 'btnCtrlPopup').attr('class', 'iframeControls').attr('title', 'Open in popup').on('click', () => this.#handleInfoAction('popup')),
            close: this.infoDiv.append('button').attr('id', 'btnCtrlClose').attr('class', 'iframeControls').attr('title', 'Close record viewer').on('click', () => this.#handleInfoAction('close'))
        };

        this.infoButtons.tab.append('span').attr('class', 'ui-icon ui-icon-newwin');
        this.infoButtons.expand.append('span').attr('class', 'ui-icon ui-icon-comment');
        this.infoButtons.close.append('span').attr('class', 'ui-icon ui-icon-close');

        this.infoControllers = this.infoDiv.selectAll('.iframeControls');
    }

    #assignInfoDivVars(){

        this.infoDiv = window.d3.select('#infoDiv');
        this.infoFrame = this.infoDiv.select('#infoIframe');
        this.infoBox = this.infoDiv.select('#infoBox');

        this.infoButtons = {
            tab: this.infoDiv.select('#btnCtrlNewtab'),
            expand: this.infoDiv.select('#btnCtrlPopup'),
            close: this.infoDiv.select('#btnCtrlClose'),
        };

        this.infoControllers = this.infoDiv.selectAll('.iframeControls');
    }

    #handleInfoAction(action = 'close'){

        if(action == 'close'){
            window.d3.select('#infoDiv').style('display', 'none');//close the box when clicked 
            return;
        }

        let rec_ID = this.infoFrame.attr('data-hid');

        if(!window.hWin.HEURIST4.util.isPositiveInt(rec_ID)){
            let recviewer_URL = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?recID=${rec_ID}&db=${window.hWin.HAPI4.database}`;
            action == 'popup' ? window.hWin.HEURIST4.ui.openRecordInPopup(rec_ID, null, false) : window.open(recviewer_URL, '_blank');
        }
    }

    #determineColour(dataColour){

        //In the array below there are currently 100 colours which will match up to all 100 unique node type ID's
        const colours = ['#FFEBEE', '#FFCDD2', '#EF9A9A', '#E57373', '#EF5350', '#FCE4EC', '#F8BBD0', '#F48FB1', '#F06292', '#EC407A', 
                        '#FF8A80', '#E1BEE7', '#CE93D8', '#BA68C8','#AB47BC', '#EDE7F6', '#D1C4E9', '#B39DDB','#9575CD', '#7E57C2', 
                        '#E8EAF6', '#C5CAE9','#9FA8DA', '#7986CB', '#5C6BC0', '#E3F2FD','#BBDEFB', '#90CAF9', '#64B5F6', '#42A5F5', 
                        '#E1F5FE', '#B3E5FC', '#81D4FA', '#4FC3F7','#29B6F6', '#E0F7FA', '#B2EBF2', '#80DEEA','#4DD0E1', '#26C6DA', 
                        '#E0F2F1', '#B2DFDB','#80CBC4', '#4DB6AC', '#26A69A', '#E8F5E9','#C8E6C9', '#A5D6A7', '#81C784', '#66BB6A', 
                        '#F1F8E9','#F3E5F5', '#DCEDC8', '#C5E1A5', '#AED581','#9CCC65', '#F9FBE7', '#F0F4C3', '#E6EE9C','#DCE775', 
                        '#D4E157', '#FFFDE7', '#FFF9C4','#FFF59D', '#FFF176', '#FFEE58', '#fff8e1','#ffecb3', '#ffe082', '#ffd54f', 
                        '#ffca28', '#FFF3E0', '#FFE0B2', '#FFCC80', '#FFB74D','#FFA726', '#FBE9E7', '#FFCCBC', '#FFAB91','#FF8A65', 
                        '#FF7043', '#EFEBE9', '#D7CCC8','#BCAAA4', '#A1887F', '#8D6E63', '#FAFAFA','#F5F5F5', '#EEEEEE', '#E0E0E0', 
                        '#BDBDBD', '#ECEFF1', '#CFD8DC', '#B0BEC5', '#90A4AE','#78909C',  '#FF80AB', '#EA80FC', '#B388FF', '#8C9EFF'];

        let idx = dataColour.rty_ID - 1;
        if(idx > 0 && idx < colours.length){
            return colours[idx];
        }
    }
}