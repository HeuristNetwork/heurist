/**
* visualise.js: Visualisation plugin
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
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
* - exporter.js
* - selection.js
* - drag.js
* - visualise.js
* 
* External Javascript:
* - jQuery          http://jquery.com/
* - D3              http://d3js.org/
* - D3 fisheye      https://github.com/d3/d3-plugins/tree/master/fisheye
* - Colpicker       https://github.com/evoluteur/colorpicker
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

$.widget('heurist.visualise', {

    options: {
        isStructure: false,
        isStandAlone: false,

        showCounts: false
    },

    svg: null,
    data: null,
    request: null,
    zoomBehaviour: null,
    force: null,

    settings: null,
    overlay: null,
    drag: null,
    selection: null,
    exporter: null,

    _maxCountForNodes: 1,
    _maxCountForLinks: 1,
    maxEntityRadius: 40,

    _attraction: -3000,

    iconSize: 16,
    circleSize: 12,
    currentMode: 'infoboxes_full',

    getData: (data) => data,
    onExpandNode: () => {},
    onRefreshData: () => [],
    onSelectNode: () => {},

    _init: function(){

        this.svg = window.d3.select('#d3svg');
        this.svg.selectAll('*').remove();
        this.svg.append('text').text('Building graph...').attr('x', '25').attr('y', '25');

        this.settings = new VisualiseSettings(this, options);
        this.overlay = new VisualiseOverlay(this);
        this.drag = new VisualiseDrag(this);
        this.selection = new VisualiseSelection(this, this.options.selectedNodeIds);
        this.exporter = new VisualiseExporter(this);

        this._initControls();
    },

    _initControls: function(){

        this.settings.settingsToUI();

        if(data && !window.hWin.HEURIST4.util.isObject(this.data)){
            return;
        }

        // Check visualisation limit
        let amount = Object.keys(this.data?.nodes).length;
        const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');

        let $ele_warn = $('#net_limit_warning');
        if(amount >= MAXITEMS) {
            $ele_warn.html(`These results are limited to ${MAXITEMS} records<br>(limit set in your profile Preferences)<br>Please filter to a smaller set of results`).show();
        }else{
            $ele_warn.hide();
        }

        this._initToolbar();
    },

    _initToolbar: function(){

        $('#btnZoomIn').button({icon:'ui-icon-plus',showLabel:false}).on('click', () => this._zoomBtn(true));
        $('#btnZoomOut').button({icon:'ui-icon-minus',showLabel:false}).on('click', () => this._zoomBtn(false));
        $('#btnFitToExtent').button({icon:'ui-icon-fullscreen',showLabel:false}).on('click', () => this._zoomToFit());

        $('#btnRefreshData').button({icon:'ui-icon-refresh'}).on('click', () => location.reload());

        $('#btnReset').button().on('click', () => this.resetDiagram())
        $('#windowPopOut').button().on('click', () => this.openWindow());
    },

    _setOption: function(key, value){

        if(typeof value === 'function' && ['getData', 'onExpandNode', 'onRefreshData', 'onSelectNode'].indexOf(key) > -1
        || key === 'data' || key === 'request'){
            this[key] = value;
        }else{
            return;
        }

        delete this.options[key];
    },

    visualise: function(){

        this.svg.selectAll('*').remove();
        this.selection.addSelectionBox();

        //define shadow filter
        this._addDropShadow();
        
        // SVG data  
        this.data = this.getCurrentData();
        this._getMaxCount();

        // Container with zoom and force
        this._addContainer();
        this.svg.call(this.zoomBehaviour); 
        this._addForce();

        // Markers
        this._addMarkers(); // all marker/arrow types on lines

        // Lines 
        this._addLines('bottom-lines', this.settings.get('linecolor', '#000'), 1); // shows connections
        this._addLines('top-lines', '#FFF', 1); // displays direction arrows
        this._addLines('rollover-lines', '#FFF', 3); // invisible thicker line for rollover
    
        // Nodes
        this.overlay.addNodes(this.data);
        this._addTitles();

        if(this.options.isStructure){
            
            let cnt_vis = this.data.nodes ? this.data.nodes.length : 0;
            let cnt_tot = this.data && this.data.nodes ? this.data.nodes.length : 0;

            let sText;
            if(cnt_vis==0){
                sText = 'Select record types to show';
            }else{
                sText = `Showing ${cnt_vis} of ${cnt_tot}`;
            }

            $('#lblShowRectypeSelector').text(sText);
        }else{
            this.inIframe();
        }

        this.tick(); // update display
    },

    /**
     * Adds a <g> container to the SVG, which all other elements will get added to.
     * The previous translateX, translateY and scale is re-used.
     */
    _addContainer: function(){

        // Zoom settings, these affect adding/removing nodes as well
        let scale = this.settings.get('scale', 1);
        let translateX = this.settings.get('translatex', 200);
        let translateY = this.settings.get('translatey', 200);

        if(isNaN(translateX) || isNaN(translateY)
        || translateX == null || translateY == null
        || Math.abs(translateX) === Infinity || Math.abs(translateY) === Infinity){

            translateX = 0;
            translateY = 0;
        }

        let translate = `translate(${translateX}, ${translateY})`;    
        if(!isNaN(scale) && scale != null && Math.abs(scale) !== Infinity && scale >= 0.5){
            translate += `scale(${scale})`;
        }
    
        // Append zoomable container
        this.svg.append('g')
            .attr('id', 'container')
            .attr('transform', translate);

        let scaleExtentVals = !this.options.isStructure ? [0.2, 15] : [0.9, 2];

        // Zoom behaviour                   
        this.zoomBehaviour = window.d3.behavior.zoom()
            .translate([translateX, translateY])
            .scale(scale)
            .scaleExtent(scaleExtentVals)
            .on('zoom', () => this._zoomed());
    },

    _addTitle: function(){

        this.svg.selectAll('.node')
            .append('title')
            .text((data) => data.name);
    },

    _addLines: function(name, colour, thickness){

        // Add the chosen lines [using the linetype setting]
        let lines;

        let linetype = this.settings.get('linetype', 'straight');
        let hide_empty = this.settings.get('line_empty_link', 1) == 0;

        lines = this.svg.select('#container')
            .append('svg:g')
            .attr('id', name)
            .selectAll('path')
            .data(data.links)
            .enter()
            .append('svg:path');

        let scale = this.zoomBehaviour.scale(); //current scale

        // Adding shared attributes
        lines.attr('class', (data) => `${name} link s${data.source.id}r${data.relation.id}t${data.target.id}`)
            .attr('stroke', (data) => {
                if(hide_empty && data.targetcount == 0 || name === 'rollover-lines' || name == 'top-lines'){
                    return 'rgba(255, 255, 255, 0.0)'; //hidden
                }else if(data.targetcount == 0 && name === 'bottom-lines') {
                    return '#d9d8d6';
                }else{
                    return colour;
                }
            })
            .attr('stroke-linecap', 'round')
            .style('stroke-width', (data) => { 
                let w = this.getLineWidth(data.targetcount) + thickness; // width for scale 1
                if(name == 'top-lines'){
                    w *= 0.2;
                }else if(name == 'rollover-lines'){
                    w *= 3;
                }
                return scale > 1 ? w : (w / scale);
            });

        // visible line, pointing from one node to another
        if(name=='top-lines' && linetype == 'straight' && this.currentMode == 'infoboxes_full'){

            lines.attr('marker-end', (data) => {

                if(hide_empty && d.targetcount == 0){
                    return null;
                }

                // reference to marker id
                if($Db.rst(data.source.id, data.relation.id, 'rst_CreateChildIfRecPtr') == 1){ // double different size arrows
                    return 'url(#marker-childptr-end)';
                }else if(data.relation.type == 'resource'){ // single arrow
                    return 'url(#marker-ptr-end)';
                }else{ // other/error
                    return null;
                }
            });

            lines.attr('marker-mid', (data) => {
                // reference to marker id
                if((hide_empty && data.targetcount == 0) || (data.relation.type != 'relmarker' && data.relation.type != 'relationship')){
                    return null;
                }

                return 'url(#marker-rel-mid)'; // double same size arrows
            });
        }else if(name=='top-lines' && linetype != 'stepped'){

            lines.attr('marker-mid', (data) => {
                if(hide_empty && data.targetcount == 0){
                    return null;
                }

                // reference to marker id
                if($Db.rst(data.source.id, data.relation.id, 'rst_CreateChildIfRecPtr') == 1){ // double different size arrows
                    return 'url(#marker-childptr-mid)';
                }else if(data.relation.type == 'resource'){ // single arrow
                    return 'url(#marker-ptr-mid)';
                }else if(data.relation.type == 'relmarker' || data.relation.type == 'relationship'){ // double same size arrows
                    return 'url(#marker-rel-mid)';
                }else{ // error
                    return null;
                }
            });
        }

        if(name == 'rollover-lines'){

            lines.on('mouseover', (data) => {
                if(hide_empty && data.targetcount == 0){
                    return;
                }
                let selector = `s${data.source.id}r${data.relation.id}t${data.target.id}`;
                this.overlay.createOverlay(window.d3.event.offsetX, window.d3.event.offsetY, 'relation', selector, data);
            })
            .on('mouseout', (data) => {
                let selector = `s${data.source.id}r${data.relation.id}t${data.target.id}`;
                this.overlay.removeOverlay(selector, 0);
            });
        }

        return lines;
    },

    _addMarkers: function(){

        let markercolour = this.settings.get('markercolor', '#000');

        let markers = this.svg.select('#container').append('defs'); // create container

        // *** Marker Mid ***
        markers.append('svg:marker') // Single arrow, pointing from field to rectype (for resources/pointers)
            .attr('id', 'marker-ptr-mid')
            .attr('markerWidth', 30)
            .attr('markerHeight', 30)
            .attr('refX', -1)
            .attr('refY', 0)
            .attr('viewBox', [-20, -20, 30, 30])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')
            .attr('d', 'M0,5 L10,0 L0,-5');

        markers.append('svg:marker') // Double arrows, pointing opposite directions (for relmarkers)
            .attr('id', 'marker-rel-mid')
            .attr('markerWidth', 30)
            .attr('markerHeight', 30)
            .attr('refX', -1)
            .attr('refY', 0)
            .attr('viewBox', [-20, -20, 30, 30])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')
            .attr('d', 'M1,-5 L9,0 L1,5 M-1,-5 L-9,0 L-1,5');

        markers.append('svg:marker') // Large and Small (child records) single arrows, pointing at each other
            .attr('id', 'marker-childptr-mid')
            .attr('markerWidth', 40)
            .attr('markerHeight', 40)
            .attr('refX', -1)
            .attr('refY', 0)
            .attr('viewBox', [-30, -30, 40, 40])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')
            .attr('d', 'M-30,5 L-20,0 L-30,-5 M6,3 L-2,0 L6,-3');

        // *** Marker-End ***
        markers.append('svg:marker') // Single arrow, pointing from field to rectype (for resources/pointers)
            .attr('id', 'marker-ptr-end')
            .attr('markerWidth', 30)
            .attr('markerHeight', 30)
            .attr('refX', 50)
            .attr('refY', 0)
            .attr('viewBox', [-20, -20, 30, 30])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')                
            .attr('d', 'M0,5 L10,0 L0,-5');

        markers.append('svg:marker') // Double arrows, pointing opposite directions (for relmarkers)
            .attr('id', 'marker-rel-end')
            .attr('markerWidth', 30)
            .attr('markerHeight', 30)
            .attr('refX', 50)
            .attr('refY', 0)
            .attr('viewBox', [-20, -20, 30, 30])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')                
            .attr('d', 'M1,-5 L9,0 L1,5 M-1,-5 L-9,0 L-1,5');

        markers.append('svg:marker') // Large and Small (child records) single arrows, pointing at each other
            .attr('id', 'marker-childptr-end')
            .attr('markerWidth', 40)
            .attr('markerHeight', 40)
            .attr('refX', 20)
            .attr('refY', 0)
            .attr('viewBox', [-30, -30, 40, 40])
            .attr('markerUnits', 'userSpaceOnUse')
            .attr('orient', 'auto')
            .attr('fill', markercolour)
            .attr('opacity', 0.6)
            .append('path')
            .attr('d', 'M-30,5 L-20,0 L-30,-5 M6,3 L-2,0 L6,-3');

        // *** Misc ***
        markers.append('svg:marker') // Circle blob, for end of lines/extra connectors
            .attr('id', 'blob')
            .attr('markerWidth', 5)
            .attr('markerHeight', 5)
            .attr('refX', 5)
            .attr('refY', 5)
            .attr('viewBox', [0, 0, 20, 20])
            .append('circle')
            .attr('cx', 5)
            .attr('cy', 5)
            .attr('r', 5)
            .style('fill', 'darkgray');
            
        markers.append('svg:marker') // Text, for self linking nodes
            .attr('id', 'self-link')
            .attr('markerWidth', 10)
            .attr('markerHeight', 10)
            .attr('refX', 0)
            .attr('refY', 0)
            .attr('viewBox', [0, 0, 20, 20])
            .attr('overflow', 'visible')
            .append('text')
            .attr('x', -6)
            .attr('y', -1)
            .style('fill', 'black')
            .style('font-size', '6.1px')
            .text('Self');

        return markers;
    },

    _addForce: function(){

        let width = parseInt(this.svg.style('width'));
        let height = parseInt(this.svg.style('height'));

        this.force = window.d3.layout.force()
            .nodes(window.d3.values(this.data.nodes))
            .links(this.data.links)
            .charge(this._attraction) // Using the attraction setting
            .linkDistance((data) => this.getLineLength(data.target))  // Using the linelength setting 
            .on('tick', () => this.tick())
            .size([width, height])
            .start();
    },

    _addDropShadow: function(){

        // filter chain comes from:
        // https://github.com/wbzyl/d3-notes/blob/master/hello-drop-shadow.html
        // cpbotha added explanatory comments
        // read more about SVG filter effects here: http://www.w3.org/TR/SVG/filters.html

        // filters go in defs element
        let defs = this.svg.append('defs');

        // create filter with id #drop-shadow
        // height=120% so that the shadow is not clipped
        let filter = defs.append('filter')
            .attr('id', 'drop-shadow')
            .attr('height', '120%');

        // SourceAlpha refers to opacity of graphic that this filter will be applied to
        // convolve that with a Gaussian with standard deviation 3 and store result
        // in blur
        filter.append('feGaussianBlur')
            .attr('in', 'SourceAlpha')
            .attr('stdDeviation', 3)
            .attr('result', 'blur');

        // translate output of Gaussian blur to the right and downwards with 2px
        // store result in offsetBlur
        filter.append('feOffset')
            .attr('in', 'blur')
            .attr('dx', 3)
            .attr('dy', 3)
            .attr('result', 'offsetBlur');

        // overlay original SourceGraphic over translated blurred opacity by using
        // feMerge filter. Order of specifying inputs is important!
        let feMerge = filter.append('feMerge');

        feMerge.append('feMergeNode')
            .attr('in', 'offsetBlur')
        feMerge.append('feMergeNode')
            .attr('in', 'SourceGraphic');
    },

    tick: function(){

        //grab each set of lines
        let topLines = this.svg.selectAll('.top-lines');
        let bottomLines = this.svg.selectAll('.bottom-lines');
        let rolloverLines = this.svg.selectAll('.rollover-lines');

        let linetype = this.settings.get('linetype', 'straight');
        if(linetype == 'curved'){
            this._updateCurvedLines(topLines);
            this._updateCurvedLines(bottomLines);
            this._updateCurvedLines(rolloverLines);
        }else if(linetype == 'stepped'){
            this._updateSteppedLines(topLines, false);
            this._updateSteppedLines(bottomLines, true);
            this._updateSteppedLines(rolloverLines, false);
        }else{
            this._updateStraightLines(bottomLines, true);
            this._updateStraightLines(topLines, false);
            this._updateStraightLines(rolloverLines, false);
        }

        // Update node locations
        this.updateNodes();

        // Update the furthest possible zoom
        if(!this.options.isStructure){

            let cur_scaleExtend = this.zoomBehaviour.scaleExtent();
            let lower_extent = this.getFitToExtentScale();

            if(lower_extent != null && !isNaN(Number(lower_extent))){
                this.zoomBehaviour.scaleExtent([lower_extent, cur_scaleExtend[1]]);
            }
            if(this.zoomBehaviour.scale() < lower_extent){
                this.zoomBehaviour.scale(lower_extent);
            }
        }
    },

    _updateCurvedLines: function(lines){

        let pairs = {};

        // Calculate the curved segments
        lines.attr('d', (data) => {

            let key = `${data.source.id}_${data.target.id}`; 
            if(!pairs[key]){
                pairs[key] = 1.5;
            }else{
                pairs[key] += 0.25;
            }

            let k = pairs[key];        
            let target_x = data.target.x,
                target_y = data.target.y;

            if(data.target.id == data.source.id){
                // Self Link, Affects Loop Size
                target_x = data.source.x + 70;
                target_y = data.source.y - 70;
            }

            let dx = target_x - data.source.x,
                dy = target_y - data.source.y,
                dr = Math.sqrt(dx * dx + dy * dy) / k,
                mx = data.source.x + dx,
                my = data.source.y + dy;

            if(data.target.id == data.source.id){ // Self Linking Node
                return `M ${data.source.x} ${data.source.y} A ${dr} ${dr} 0 0 1 ${mx} ${my} A ${dr} ${dr} 0 0 1 ${target_x} ${target_y} A ${dr} ${dr} 0 0 1 ${data.source.x} ${data.source.y}`;
            }else{ // Node to Node Link
                return `M ${data.source.x} ${data.source.y} A ${dr} ${dr} 0 0 1 ${mx} ${my} A ${dr} ${dr} 0 0 1 ${target_x} ${target_y}`;
            }
        });
    },

    _updateSteppedLines: function(lines, isBottomLine = false){

        let pairs = {};

        $('.hidden_line_for_markers').remove();

        // Calculate the straight points
        lines.attr('d', (data, idx, elements) => {

            let dx = (data.target.x - data.source.x) / 2,
                dy = (data.target.y - data.source.y) / 2;

            let indent = (Math.abs(dx) > Math.abs(dy) ? dx : dy) / 4;

            let key = `${data.source.id}_${data.target.id}`;
            if(pairs[`${data.target.id}_${data.source.id}`]){
                key = `${data.target.id}_${data.source.id}`;
            }else if(!pairs[key]){
                pairs[key] = 1 - indent;
            }
            pairs[key] = pairs[key] + indent;

            let k = pairs[key];
            let target_x = data.target.x,
                target_y = data.target.y;

            let res = '';
            let marker_type = (data.relation.type == 'resource') ? 'url(#marker-ptr-mid)' : 'url(#marker-rel-mid)';

            if(d.target.id == d.source.id){ // Self Linking Node

                // Affects Loop Size
                target_x = data.source.x + 65;
                target_y = data.source.y - 65;

                dx = target_x - data.source.x;
                dy = target_y - data.source.y;

                let dr = Math.sqrt(dx * dx + dy * dy) / 1.5,
                    mx = data.source.x + dx,
                    my = data.source.y + dy;

                res = `M ${data.source.x} ${data.source.y} A ${dr} ${dr} 0 0 1 ${mx} ${my} L ${data.source.x + 35} ${data.source.y -35} L ${data.source.x} ${data.source.y}`;

                if(window.hWin.HEURIST4.util.isFunction($(elements[idx]).attr)){
                    $(elements[idx]).attr('marker-mid', marker_type);
                }

            }else{  // Node to Node Link

                let x = dx < 0 ? -1 : 1;
                let y = dy < 0 ? -1 : 1;

                let dx2 = 45 * (dx == 0 ? 0 : x);
                let dy2 = 45 * (dy == 0 ? 0 : y);

                //path
                let midx = data.source.x + dx2 + dx + k;
                res = `M ${data.source.x} ${data.source.y} L ${data.source.x + dx2} ${data.source.x + dy2} L ${midx} ${data.source.y + dy2} L ${midx} ${target_y} L ${target_x} ${target_y}`;

                if(isBottomLine){

                    //add 3 lines - specially for markers
                    let g = this.svg.select('#container')
                        .append('svg:g')
                        .attr('class', 'hidden_line_for_markers');
                    
                    let pnt = `M ${data.source.x + dx2} ${data.source.y + dy2} L M ${data.source.x + dx2 + dx / 2 + k} ${data.source.y + dy2}`;

                    g.append('svg:path')
                        .attr('d', pnt)
                        //reference to marker id
                        .attr('marker-end', marker_type);

                    pnt = `M ${midx} ${data.source.y + dy2} L ${midx} ${data.source.y + dy2 + (target_y - data.source.y - dy2) / 2}`;
                    g.append('svg:path')
                        .attr('d', pnt.join(' '))
                        //reference to marker id
                        .attr('marker-end', marker_type);
                }
            }

            return res;
        });
    },

    _updateStraightLines: function(lines, isBottomLine = false){

        let pairs = {};
        let isExpanded = $('#expand-links').is(':checked');    
        $('.icon_self').remove();

        let container = this.svg.select('#container');

        // Calculate the straight points
        lines.attr('d', (data) => {

            if(data == null){
                return '';
            }
            
            //are source and target defined
            if(data.source.id && data.target && (isNaN(data.source.x) || isNaN(data.source.y) || isNaN(data.target.x) || isNaN(data.target.y))){
                return false;
            }
            
            let key = `${data.source.id}_${data.target.id}`,
                indent = 20;

            if(pairs[`${data.target.id}_${data.source.id}`]){
                key = `${data.target.id}_${data.source.id}`;
            }else if(!pairs[key]){
                indent = 0;
            }

            if(indent > 0){ // This controls how far apart lines will be when going to and from the same node

                if(isExpanded){ // This is for the expanded option, displays all lines
                    pairs[key] += indent;
                }else{ // This will hide all other lines, default behaviour
                    return [''];
                }
            }else{
                pairs[key] = 1;
            }

            let R = pairs[key];
            let pnt = '';

            let s_x = data.source.x,
                s_y = data.source.y,
                t_x = data.target.x,
                t_y = data.target.y;

            let ismultivalue = this.options.isStructure && $Db.rst(data.source.id, data.relation.id, 'rst_MaxValues') != 1 && $Db.rst(data.source.id, data.relation.id, 'rst_MaxValues') != null;

            if(data.target.id == data.source.id){ // Self Linking Node
            
                let target_x, target_y, dx, dy, dr, mx, my;

                if(this.currentMode == 'infoboxes_full'){

                    let $detail = $(`.id${data.source.id}`).find(`[dtyid="${data.relation.id}"]`);

                    if($detail.length == 1){

                        // Get detail's y location within the source object
                        const detail_y = $detail[0].getBBox().y;
                        s_y += detail_y - this.iconSize * 0.6;
                    }

                    // Reduce x and y locations
                    s_x -= (this.iconSize / 1.5);

                    // Prepare extra lines
                    const s_x2 = s_x;
                    s_x -= 12;

                    if(isBottomLine){

                        let id = `selfibfbtlinesrc_${data.source.id}_${data.relation.id}`;
                        let selectedLine = container.select(`#${id}`);
                        //add extra starting line
                        if(selectedLine.empty()){
                            selectedLine = container.insert('svg:line', `.id${data.source.id} + *`)
                                .attr('class', 'offset_line')
                                .attr('id', id)
                                .attr('stroke', 'darkgray')
                                .attr('stroke-linecap', 'round')
                                .style('stroke-width', '3px')
                                .attr('marker-end', 'url(#blob)')
                                .attr('marker-start', 'url(#self-link)');
                        }

                        selectedLine.style('display', 'inline')
                            .attr('x1', s_x)
                            .attr('y1', s_y)
                            .attr('x2', s_x2)
                            .attr('y2', s_y);
                    }
                }else{

                    // Affects Loop Size
                    target_x = s_x+70;
                    target_y = s_y-70;

                    dx = target_x - s_x;
                    dy = target_y - s_y;
                    dr = Math.sqrt(dx * dx + dy * dy) / 1.5;
                    mx = s_x + dx;
                    my = s_y + dy;

                    return `M ${s_x} ${s_y} A ${dr} ${dr} 0 0 1 ${mx} ${my} L ${s_x + 35} ${s_y - 35} L ${s_x} ${s_y}`;
                }
            }else{ // Node to Node Link

                let dx, dy, tg, dx2, dy2, mdx, mdy, s_x2, t_x2, t_y2;
                let elevation_diff = false;
                let threshold = 60;

                if(this.currentMode == 'infoboxes_full'){

                    // Relevant svg Elements/Items
                    let $source_rect = $($(`.id${data.source.id}`).find(`rect[rtyid="${data.source.id}"]`)[0]),
                        $target_rect = $($(`.id${data.target.id}`).find(`rect[rtyid="${data.target.id}"]`)[0]),
                        $detail = $(`.id${data.source.id}`).find(`[dtyid="${data.relation.id}"]`);

                    // Get the width for source and target rectangles
                    let source_width = Number($source_rect.attr('width')),
                        target_width = Number($target_rect.attr('width'));

                    if($detail.length > 0){ // Check that the location of the detail can be found

                        // Get detail's y location within the source object
                        const detail_y = $detail[0].getBBox().y;
                        s_y += detail_y - this.iconSize * 0.6;
                    }

                    // Get target's bottom y location
                    let b_target_y = t_y + Number($target_rect.attr('height')) - this.iconSize + 2;

                    // Left Side: x Point for starting and ending nodes
                    s_x -= this.iconSize;
                    t_x -= this.iconSize;
                    // Right Side: x Point for starting and ending nodes
                    let r_source_x = s_x + source_width + this.iconSize / 4;
                    let r_target_x = t_x + target_width + this.iconSize / 4;

                    if(r_source_x + threshold < t_x){ // Right to Left Connection, Change source x location
                        
                        s_x = r_source_x;

                        s_x2 = s_x - 5;
                        t_x2 = t_x;

                        s_x += 7;
                        t_x -= 7;
                    }else if(s_x > r_target_x + threshold){ // Left to Right Connection, Change target x location

                        t_x = r_target_x;

                        s_x2 = s_x + 5;
                        t_x2 = t_x;

                        s_x -= 7;
                        t_x += 7;
                    }else{ // target is above/below source and was same side connectors

                        t_x += (target_width / 2);
                        t_x2 = t_x;

                        if(t_y < s_y){ // target is higher than source
                            t_y2 = b_target_y;
                            t_y = b_target_y + 10;
                        }else{
                            t_y2 = t_y - this.iconSize;
                            t_y -= this.iconSize + 10;
                        }

                        // Differences between points (x coord)
                        let left_diff = (t_x - s_x > s_x - t_x) ? t_x - s_x : s_x - t_x;
                        let right_diff = (t_x - r_source_x > r_source_x - t_x) ? t_x - r_source_x : r_source_x - t_x;

                        if(right_diff < left_diff){ // right 2 right

                            s_x = r_source_x;

                            s_x2 = s_x - 5;

                            s_x += 7;
                        }else{ // left 2 left

                            s_x2 = s_x + 5;
                            s_x -= 7;
                        }

                        elevation_diff = true;
                    }

                    if(isBottomLine){

                        let id = `n2nibfbtlinesrc_${data.source.id}_${data.relation.id}_${data.target.id}`;
                        let selectedLine = container.select(`#${id}`);
                        if(selectedLine.empty()){

                            //add extra starting line + blob
                            selectedLine = container.insert('svg:line', `.id${data.source.id} + *`)
                                .attr('class', 'offset_line')
                                .attr('id', id)
                                .attr('stroke', 'darkgray')
                                .attr('stroke-linecap', 'round')
                                .style('stroke-width', '3px')
                                .attr('marker-end', 'url(#blob)');
                        }

                        selectedLine.style('display', 'inline')
                            .attr('x1', s_x)
                            .attr('y1', s_y)
                            .attr('x2', s_x2)
                            .attr('y2', s_y);
                        
                        let linecolour = !ismultivalue ? 'darkgray' : 'dimgray';
                        let linewidth = !ismultivalue ? '3px' : '2px';
                        // Node2NodeInfoBoxesFullBottomLineTarget
                        id = `n2nibfbltgt_${data.target.id}_${data.relation.id}_${data.source.id}`;
                        selectedLine = container.select(`#${id}`);

                        if(!elevation_diff){ // add extra ending line

                            if(selectedLine.empty()){ // check the line exist

                                // if not exist create the line
                                selectedLine = container.insert('svg:line', `.id${data.target.id} + *`)
                                    .attr('class', 'offset_line')
                                    .attr('id', id)
                                    .attr('stroke', linecolour)
                                    .attr('stroke-linecap', 'round')
                                    .style('stroke-width', linewidth);
                            }

                            // update the coordinates
                            selectedLine.style('display', 'inline')
                                .attr('x1', t_x)
                                .attr('y1', t_y)
                                .attr('x2', t_x2)
                                .attr('y2', t_y);

                            //add crows foot, if multi value
                            if(ismultivalue){

                                let hideId = `#n2nibfsrc_${data.target.id}_${data.relation.id}_${data.source.id}`;
                                let hideLine = container.select(hideId);
                                if(!hideLine.empty()){
                                    hideLine.style("display", "none")
                                }

                                // Node2NodeInfoBoxesFullBottomLineSourceMultiValue
                                id = `n2nibfblsrcmv_${data.source.id}_${data.relation.id}_${data.target.id}`;
                                selectedLine = container.select(`#${id}`);
                                if(selectedLine.empty()){
                                    selectedLine = container.insert('svg:path', `.id${data.source.id} + *`)
                                        .attr('id', id)
                                        .attr('class', 'offset_line')
                                        .attr('stroke-linecap', 'round')
                                        .attr('fill', 'none');
                                }

                                selectedLine.style('display', 'inline')
                                    .attr('stroke-width', linewidth)
                                    .attr('stroke', linecolour)
                                    .style('display', null)
                                    .attr('d', `M ${t_x2} ${t_y + 5} L ${t_x} ${t_y} L ${t_x2} ${t_y - 5}`);
                            }
                        }else{

                            //add crows foot, if multi value
                            if(ismultivalue){

                                if(selectedLine.empty()){
                                    selectedLine = container.insert('svg:line', `.id${data.target.id} + *`)
                                        .attr('class', 'offset_line')
                                        .attr('id', id)
                                        .attr('stroke', linecolour)
                                        .attr('stroke-linecap', 'round')
                                        .style('stroke-width', linewidth);
                                }

                                // add extra ending line
                                selectedLine.style('display', 'inline')
                                    .attr('x1', t_x)
                                    .attr('y1', t_y)
                                    .attr('x2', t_x)
                                    .attr('y2', t_y2);
                                
                                let hideId = `#n2nibfblsrcmv_${data.source.id}_${data.relation.id}_${data.target.id}`;
                                let hideLine = container.select(hideId);
                                if(!hideLine.empty()){
                                    hideLine.style('display', 'none');
                                }

                                id = `n2nibfsrc_${data.target.id}_${data.relation.id}_${data.source.id}`;
                                selectedLine = container.select(`#${id}`);
                                if(selectedLine.empty()){
                                    selectedLine = container.insert('svg:path', `.id${data.source.id} + *`)
                                        .attr('id', id)
                                        .attr('class', 'offset_line')
                                        .attr('stroke-linecap', 'round')
                                        .attr('fill', 'none');
                                }

                                selectedLine.style('display', 'inline')
                                    .attr('stroke', linecolour)
                                    .attr('stroke-width', linewidth)
                                    .attr('fill', 'none')
                                    .attr('d', `M ${t_x + 5} ${t_y2} L ${t_x} ${t_y} L ${t_x - 5} ${t_y2}`);
                            }else{

                                if(!this.options.isStructure){

                                    let hideId = `#n2nibfbltgt_${data.target.id}_${data.relation.id}_${data.source.id}`;
                                    let hideLine = container.select(hideId);
                                    if(!hideLine.empty()){
                                        hideLine.style('display', 'none');
                                    }
                                }

                                t_y = t_y2;
                            }
                        }
                    }

                    dx = (t_x - s_x) / 2;
                    dy = (t_y - s_y) / 2;

                    mdx = s_x + dx;
                    mdy = s_y + dy;

                }else{

                    dx = (t_x - s_x) / 2;
                    dy = (t_y - s_y) / 2;

                    tg = dx != 0 ? Math.atan(dy / dx) : 0;

                    dx2 = dx - R * Math.sin(tg);
                    dy2 = dy + R * Math.cos(tg);

                    mdx = s_x + dx2;
                    mdy = s_y + dy2;

                }

                pnt = `M ${s_x} ${s_y} L ${mdx} ${mdy} L ${t_x} ${t_y}`;
            }
        
            return pnt; 
        });
    },

    updateNodes: function(){

        window.d3.selectAll('.node').attr('transform', (data) => {

            // Store new position
            if(data.x==null || data.y==null || isNaN(data.x) || isNaN(data.y)){
                data.x=0;
                data.y=0;
            }

            const obj = { px: data.px, py: data.py, x: data.x, y: data.y };

            this.visualiser.settings.put(data.id, JSON.stringify(obj));

            return `translate(${data.x},${data.y})`;
        });
    },

    updateLabels: function(){

        // Zoom Scaling
        this.svg.selectAll('.nodelabel')
            .style('scale', 1)
            .style('transform', 'translate(0px, 0px)');
    },

    updateShape: function(type, parameters){

        switch(type){

            case 'circles':
                this.selection.updateCircles(...parameters);
                break;
            case 'rectangles':
                this.selection.updateRectangles(...parameters);
                break;
            default:
                break;
        }
    },

    filterData: function(json_data){

        if(!json_data) json_data = this.data;

        let names = [];
        $('.show-record').each((idx, element) => {
            const name = $(element).attr("name");
            if(!$(element).is(':checked')){ //to exclude
                names.push(name);
            }
        });

        // Filter nodes
        let map = {};
        let nodes = json_data.nodes.filter((data, idx) => {
            if($.inArray(data.name, names) == -1){
                map[idx] = data;
                return true;
            }
            return false;
        });

        // Filter links
        let links = [];
        json_data.links.filter((data) => {
            if(Object.hasOwn(map, data.source) && Object.hasOwn(map, data.target)){
                let link = {source: map[data.source], target: map[data.target], relation: data.relation, targetcount: data.targetcount};
                links.push(link);
            }
        });

        let data_visible = {nodes: nodes, links: links};
        this.getData = (all_data) => data_visible;
        this.visualise();
    },

    getEntityRadius: function(count){

        let maxRadius = this.settings.get('entityradius');

        if(maxRadius > this.maxEntityRadius){ maxRadius = this.maxEntityRadius; }
        else if(maxRadius < 1){ maxRadius = 1; }

        if(this.settings.get('formula')=='unweighted'){
            return maxRadius;
        }else if(count == 0){
            return 0; //no records - no circle
        }

        if(count > this._maxCountForNodes){
            this._maxCountForNodes = count;
        }

        let val = this.circleSize + this.executeFormula(count, this._maxCountForNodes, maxRadius);
        if(val < this.circleSize) val = this.circleSize;
        return val;
    },

    getLineWidth: function(count){

        count = Number(count);
        let maxWidth = Number(this.settings.get('linewidth', 3));

        let maxSize = 1;
        if(maxWidth > this.maxLinkWidth){ maxSize = this.maxLinkWidth; }
        if(maxWidth < 1){ maxSize = 1; }

        if(count > this._maxCountForLinks){
            this._maxCountForLinks = count;
        }

        let val = count == 0 ? 0 : this.executeFormula(count, this._maxCountForLinks, maxWidth);
        return val < 1 ? 1 : val;
    },

    getLineLength: function(record){

        let length = this.settings.get('linelength', 200);

        if(record !== undefined && Object.hasOwn(record, 'depth')){
            length /= (record.depth + 1);
        }

        return length;
    },

    getMarkerWidth: function(count){

        if(isNaN(count)){
            count = 0;
        }

        return 4 + this.getLineWidth(count) * 10;
    },

    executeFormula: function(count, maxCount, maxSize){

        // Avoid minus infinity and wrong calculations etc.
        if(count <= 0) {
            count = 1;
        }
        
        let formula = this.settings.get('formula');
        if(formula == 'logarithmic'){ // Log                                                           
            return maxCount > 1 ? (Math.log(count) / Math.log(maxCount) * maxSize) : 1;
        }else if(formula == 'unweighted'){ // Unweighted
            return maxSize;      
        }else{ // Linear
            return (maxCount > 0) ? ((count / maxCount) * maxSize) : 1;
        }
    },

    getFitToExtentScale: function(){

        let fullWidth = $('#divSvg').width();
        let fullHeight = $('#divSvg').height();

        const box = this.svg.select('#container').node().getBBox();

        let width  = box.width,
            height = box.height;

        if(width == 0 || height == 0){ // nothing to fit
            return null;
        }

        return 0.85 / Math.max(width / fullWidth, height / fullHeight);
    },

    _zoomed: function(){

        this.updateLabels();

        //keep current setting Translate
        let transform = 'translate(0,0)';
        let translate = window.d3.event.translate;
        if(translate !== undefined){

            if(isNaN(translate[0]) || !isFinite(translate[0])){
                translate[0] = 0;
            }else{
                this.settings.put('translatex', translate[0]); 
            }

            if(isNaN(translate[1]) || !isFinite(translate[1])) {
                translate[1] = 0;
            }else{
                this.settings.put('translatey', translate[1]);
            }

            transform = `translate(${translate})`;
        }

        //keep current setting Scale
        let scale = window.d3.event.scale;
        if(!isNaN(scale) && isFinite(scale) && scale != 0){
            this.settings.put('scale', scale);
            transform += `scale(${scale})`;
        }

        this._onZoom(transform);
    },

    _onZoom: function(transform){

        this.svg.select('#container').attr('transform', transform);

        let scale = this.zoomBehaviour.scale();
        if(isNaN(scale) || !isFinite(scale) || scale == 0){
            scale = 1;
        }
    },

    _zoomToFit: function(){

        let fullWidth = $('#divSvg').width();
        let fullHeight = $('#divSvg').height();

        const box = this.svg.select('#container').node().getBBox();

        let width  = box.width,
            height = box.height;

        let midX = box.x + width / 2,
            midY = box.y + height / 2;

        let scale = this.getFitToExtentScale();
        if(scale == null && isNaN(Number(scale))){
            return; // nothing to fit
        }

        let translate = [
            fullWidth / 2 - scale * midX,
            fullHeight / 2 - scale * midY
        ];

        //reset
        this.zoomBehaviour.scale(scale)
            .translate(translate);

        let transform = `translate(${this.zoomBehaviour.translate()})scale(${this.zoomBehaviour.scale()})`;
        this._onZoom(transform);
    },

    _zoomBtn: function(zoom_in){

        let zoom = this.zoomBehaviour; 

        let scale = zoom.scale(),
            extent = zoom.scaleExtent(),
            translate = zoom.translate(),
            x = translate[0], y = translate[1],
            factor = zoom_in ? 1.3 : 1 / 1.3,
            target_scale = scale * factor;

        if(isNaN(x) || !isFinite(x)){ x = 0; }
        if(isNaN(y) || !isFinite(y)){ y = 0; }

        // If we're already at an extent, done
        if (target_scale === extent[0] || target_scale === extent[1]) { return false; }

        // If the factor is too much, scale it down to reach the extent exactly
        let clamped_target_scale = Math.max(extent[0], Math.min(extent[1], target_scale));
        if (clamped_target_scale != target_scale){
            target_scale = clamped_target_scale;
            factor = target_scale / scale;
        }

        let width = $("#divSvg").width();
        let height = $("#divSvg").height();
        let center = [width / 2, height / 2];
        // Center each vector, stretch, then put back
        x = (x - center[0]) * factor + center[0];
        y = (y - center[1]) * factor + center[1];

        zoom.scale(target_scale)
            .translate([x,y]);

        let transform = `translate(${zoom.translate()})scale(${zoom.scale()})`;
        this._onZoom(transform);
    },

    getCurrentData: function(){
        return typeof this.getData !== 'function' ? [] : this.getData.call(this, this.data);
    },

    expandNode: function(cmd, rec_ID){
        if(typeof this.onExpandNode === 'function'){
            this.onExpandNode.call(this, cmd, rec_ID);
        }
    },
    
    refreshData: function(){
        if(typeof this.onRefreshData === 'function'){
            this.onRefreshData.call(this);
        }
    },

    selectNode: function(selected){
        if(typeof this.onRefreshData === 'function'){
            this.onSelectNode.call(this, selected);
        }
    },

    _getMaxCount: function(){

        this._maxCountForNodes = 1;
        this._maxCountForLinks = 1;

        if(!this.data){
            return;
        }

        if(this.data.nodes.length > 0){

            for(const node of this.data.nodes){
                if(node.count > this._maxCountForNodes){
                    this._maxCountForNodes = node.count;
                }
            }
        }

        if(this.data.links.length > 0){

            for(const link of this.data.links){
                if(link.count > this._maxCountForLinks){
                    this._maxCountForLinks = link.count;
                }
            }
        }
    },

    inIframe: function(){

        let $btnFullscreen = $('#windowPopOut');
        let $btnClose = $('#closegraphbutton');

        let $btnRefresh = $('#resetbutton');
        let $btnGravity = $('#gravityMode0, #gravityMode1');

        $btnRefresh.show();
        $btnGravity.show();

        if(window.location !== window.parent.location){
            //Page is in iFrame
            $btnFullscreen.show();
            $btnClose.hide();
        }else{
            //Page is not in iFrame
            $btnFullscreen.hide();
            $btnClose.show();
        }
    },

    openWindow: function(){

        let hrefnew = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        hrefnew += `${hrefnew == '?' ? '' : '&'}db=${window.hWin.HAPI4.database}`;

        let url = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php${hrefnew}`;
        window.open(url);
    },

    resetDiagram: function(){

        if(window.location === window.parent.location){ // handle iframe
            location.reload();
            return;
        }

        let query = settings.request ? settings.request : window.hWin.HEURIST4.current_query_request;
        query = window.hWin.HEURIST4.query.composeHeuristQuery2(query, false);
        query += `${query == '?' ? '' : '&'}db=${window.hWin.HAPI4.database}`;

        location.href = query;
    }
});