/**
* drag.js - Functions to add nodes and make them draggable
*
* @fileOverview Functions to add nodes and make them draggable.
* This file includes functionality for appending nodes to the D3 visualization,
* handling drag events (start, move, end), managing node clicks for displaying
* information, and updating node positions.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

/* global svg, data, settings, force, currentMode, circleSize, iconSize, editRecStructure,
drag_link_source_id, drag_link_target_id, drag_link_line, selectionColor, determineColour, closeRectypeSelector,
getSetting, putSetting, createOverlay, getEntityRadius, truncateText, updateCircles, tick */

/** @global {null|number} currentNode Stores the ID of the currently dragged node. */
let currentNode = null;

/** @global {int} whether a click was a double click (avoid opening node information) */
window.doubleClicked = 0;

/**
* Appends nodes to the D3 visualisation.
* This function creates the visual representation of nodes, including circles, icons,
* and attaches event listeners for interactions like double-click and dragging.
* It also restores node positions from local storage if available.
*
* @returns {object} The D3 selection of the appended nodes.
*/
function addNodes() {

   // Append nodes
   let nodes = window.d3.select("#container")
        .selectAll(".node")
        .data(data.nodes)
        .enter()
        .append("g")
        .on("dblclick", (d) => {

            window.doubleClicked = 2;

            if(settings.minimal && !settings.isDatabaseStructure && typeof expandNode === 'function'){ // expand node
                expandNode(d.id);
            }else if(!settings.isDatabaseStructure){ //Added Double Click to Edit Function - Travis Doyle 19/9
                window.open(window.hWin.HAPI4.baseURL + '?fmt=edit&db=' + window.hWin.HAPI4.database + '&recID=' + d.id, '_blank');
            }else if(window.hWin.HAPI4.is_admin()){
                editRecStructure(d.id);
            }
        });
                 
   // Dragging
   let drag = window.d3.behavior.drag()
                 .on("dragstart", dragstart)
                 .on("drag", dragmove)
                 .on("dragend", dragend);
     
   let entitycolor = getSetting('setting_entitycolor');
      
   // Details for each node            
   nodes.each(function(d, i) {
        // Restore location data
        let record = getSetting(d.id);
        if(record) {
            const obj = JSON.parse(record);
            if("x" in obj) {
                d.x = obj.x;
            }
            if("y" in obj) {
                d.y = obj.y;
            }
            if("px" in obj) {
                d.px = obj.px;
            }
            if("py" in obj) {
                d.py = obj.py;
            }
        }

        let node = window.d3.select(this);
        
        let icon_display = currentMode=='icons' ? 'initial' : 'none';
        
        //add infobox
        createOverlay(0, 0, "record", "id"+d.id, d, node);
        
        //add outer circle
        node.append("circle")
            .attr("r", function(d) {
                return getEntityRadius(d.count);
            })
            .attr("class", "background icon-background")
            .style({'fill-opacity': '0.5', 'display': icon_display})
            .attr("fill", determineColour); //entitycolor        
        
        //add internal circle
        node.append("circle")
            .attr("r", circleSize)
            .attr("class", 'foreground icon-foreground')
            .attr("fill", entitycolor)
            .style({"stroke": "#ddd", 'display': icon_display})
            .style("stroke-opacity", function(d) {
                if(d.selected == true) {
                    return 1;
                }
                return .25;
            });

        //add icon
        node.append("svg:image")
            .attr("class", "icon node-icon") 
            .attr("xlink:href", function(d) {
                if(d.image){
                    return d.image;
                }else{
                    return '';
                }
            })
            .attr("x", -iconSize/2)
            .attr("y", -iconSize/2)
            .attr("height", iconSize)
            .attr("width", iconSize)
            .on("mouseover", function(d) {
                if(drag_link_source_id!=null){
                    window.drag_link_target_id = d.id;
                    window.drag_link_line.attr("stroke","#00ff00");  //green
                }
            })
            .on("mouseout", function(d) {
                if(drag_link_source_id!=null){
                setTimeout(function(){
                    window.drag_link_target_id = null;
                    if(window.drag_link_line) window.drag_link_line.attr("stroke","#ff0000");  //red
                },200);
                }
            })
            .style('display', icon_display)
            .attr('data-icon-id', (d) => {
                return d.rty_ID ? d.rty_ID : d.id;
            });
                           
        let gravity = getSetting('setting_gravity');
        
        // Attributes
        node  //window.d3.select(this)
          .attr("class", "node id"+d.id)
          .attr("transform", "translate(10, 10)")
          .attr("x", d.x) 
          .attr("y", d.y)
          .attr("px", d.px)
          .attr("py", d.py)
          .attr("fixed", function(d) {
              if(record && gravity == "off") {
                  d.fixed = true;
                  return true;
              }
              return false;
          })    
         .on("click", onNodeClick)
         .on("contextmenu", onNodeClick)
         .call(drag);

     });            
     return nodes;
}

/**
 * Handles node click events.
 * Closes the record type selector and, if not a drag event, shows node information.
 * @param {object} d - The D3 data object for the clicked node.
 */
function onNodeClick(d){

    closeRectypeSelector();

    // Check if it's not a click after dragging
    if(window.d3.event.defaultPrevented || window.doubleClicked){
        return;
    }

    // Ensure it's not a double click
    setTimeout((d, e) => {

        if(window.doubleClicked > 0){
            window.doubleClicked--;
            return;
        }

        // Load record details
        showNodeInformation(d);
    }, 1000, d);
}

/**
 * Shows the record details in an iframe or a div when a node is clicked.
 * If `settings.isDatabaseStructure` is true, it displays record type information.
 * Otherwise, it displays the record viewer in an iframe.
 * @param {object} d - Node data object from D3.
 */
function showNodeInformation(d){

    let $infoDiv = $('#infoDiv');
    let infoDiv = window.d3.select("#infoDiv"); // select the parent div
    let infoFrame = window.d3.select("#infoIframe"); // select the iframe
    let infoBox = window.d3.select("#infoBox"); // select the info box

    if(infoDiv.length == 0 || infoFrame.length == 0 || infoBox.length == 0){
        return;
    }

    if($infoDiv.resizable('instance') === undefined){ // setup resizing
        $infoDiv.resizable({
            maxHeight: 400,
            minHeight: settings.isDatabaseStructure ? 150 : 300,
            resize: (event, ui) => {
                infoFrame.style('height', `${$infoDiv.height()}px`);
                infoBox.style('height', `${$infoDiv.height()}px`);
            },
            handles: 's'
        });
    }

    infoDiv.style("display", "block"); // make info div visible

    function displayRecordViewer(){

        $('.iframeControls').show();
        infoFrame.style('display', 'inline');
        infoBox.style('display', 'none');

        if(infoFrame.attr("data-hid") == d.id){ // block retrival of last record in quick succession
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(infoDiv, {'background-color': 'white', 'opacity': 1, 'font-weight': 'bold', 'font-size': 'smaller', 'color': 'black'}, 
            'Loading<br><br>'+ window.hWin.HEURIST4.util.stripTags(truncateText(d.name, 40)));
    
        const srcURL = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?noclutter=1&recID=${d.id}&db=${window.hWin.HAPI4.database}`; // URL for source of information iframe

        infoFrame.attr("src", srcURL)
                 .attr("data-hid", d.id)
                 .on('load', () => {

                    window.hWin.HEURIST4.msg.sendCoverallToBack(true);

                    let viewMaxHeight = document.querySelector('#divSvg').scrollHeight;
                    viewMaxHeight = viewMaxHeight <= 0 ? 500 : viewMaxHeight - 150;

                    let height = infoFrame.node().contentWindow.document.body.scrollHeight;
                    height += 15;

                    if(height <= 100 || height >= viewMaxHeight){
                        height = viewMaxHeight
                    }

                    infoFrame.style('height', `${height}px`);

                    infoDiv.style('max-height', `${height}px`);
                    infoDiv.style('height', `${height}px`);
                    $infoDiv.resizable('option', 'maxHeight', height);
                 });//supply document to iframe
    }

    function displayRecTypeInfo(){

        $('.iframeControls').hide();
        $('#btnCtrlClose').show();
        infoFrame.style('display', 'none');
        infoBox.style('display', 'block');

        let recType = $Db.rty(d.id);

        if(infoBox.attr("data-hid") == d.id || !recType){ // block retrival of last record in quick succession
            return;
        }

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

        infoBox.attr('data-hid', d.id)
               .html(rectypeDetails);

        let viewMaxHeight = document.querySelector('#divSvg').scrollHeight;
        viewMaxHeight = viewMaxHeight <= 0 ? 500 : viewMaxHeight - 20;

        let height = infoBox.node().scrollHeight;
        height += 15;

        if(height <= 100 || height >= viewMaxHeight){
            height = viewMaxHeight
        }

        infoBox.style('height', `${height}px`);

        infoDiv.style('max-height', `${height}px`);
        infoDiv.style('height', `${height}px`);
        $infoDiv.resizable('option', 'maxHeight', height);
    }

    if(settings.isDatabaseStructure){
        displayRecTypeInfo();
    }else{
        displayRecordViewer();
    }

}

/**
 * Handles actions for the node information display, such as closing or opening in a popup/new tab.
 * @param {string} [action='close'] - The action to perform: 'close', 'popup', or 'newtab'.
 */
function handleNodeAction(action = 'close'){

    if(action == 'minimise' || action == 'maximise'){
        toggleRecordViewer(action);
        return;
    }

    if(action == 'close'){
        window.d3.select('#infoDiv').style('display', 'none');//close the box when clicked
        window.d3.select('#infoDiv_stub').style('display', 'none');//close the box when clicked
        return;
    }

    let rec_ID = window.d3.select('#infoIframe').attr('data-hid');
    let recviewer_URL = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?recID=${rec_ID}&db=${window.hWin.HAPI4.database}`;

    action == 'popup' ? window.hWin.HEURIST4.ui.openRecordInPopup(rec_ID, null, false) : window.open(recviewer_URL, '_blank');
}

function toggleRecordViewer(action){
    
    let $infoDiv = $('#infoDiv');
    let $infoStub = $('#infoDiv_stub');
    let recID = document.querySelector('#infoIframe').getAttribute('data-hid');

    if(!window.hWin.HEURIST4.util.isPositiveInt(recID)){
        return;
    }

    if(action == 'minimise'){

        let data = settings.getData.call(this, settings.data);
        let recTitle = `Record #${recID}`;

        for(let i = 0; i < data.nodes.length; i++){

            if(data.nodes[i].id == recID){
                recTitle = data.nodes[i].name;
                break;
            }
        }

        $infoStub.find('#infoDiv_stubtitle').html(recTitle);

        $infoDiv.hide('slide', {direction: 'down'});
        $infoStub.show('slide', {direction: 'up'});

    }else{
        $infoStub.hide('slide', {direction: 'up'});
        $infoDiv.show('slide', {direction: 'down'});
    }


}

/**
* Updates the locations of all nodes in the visualization.
* Stores the new x, y, px, and py coordinates in local storage for each node.
*/
function updateNodes() {
    window.d3.selectAll(".node").attr("transform", function(d) { 
        // Store new position
        if(d.x==null || d.y==null || isNaN(d.x) || isNaN(d.y)){
            d.x=0;
            d.y=0;
        }
        const obj = {px: d.px, py: d.py, x: d.x, y: d.y};
        putSetting(d.id, JSON.stringify(obj));
        return "translate(" + d.x + "," + d.y + ")"; 
    });
}

// Functions to make dragging, moving and zooming possible

/**
 * Called when a D3 dragging event starts on a node.
 * Stops the force layout, sets the node as fixed, and updates its appearance.
 * @param {object} d - The D3 data object for the dragged node.
 * @param {number} i - The index of the dragged node.
 */
function dragstart(d, i) {
    
    window.d3.event.sourceEvent.stopPropagation();
    window.d3.event.sourceEvent.preventDefault();

    force.stop();

    // Fixed node positions?
    const gravity = getSetting('setting_gravity');
    svg.selectAll(".node")
       .attr("fixed", function(d, i) {
            d.fixed = (gravity == "off");
            return d.fixed;
       }); 
    d.fixed = true; 
    currentNode = d.id;

    updateCircles(".node", foregroundColor, false);
    updateCircles(`.node.id${d.id}`, selectionColor, true);
}

/**
 * Called when a D3 dragging move event occurs.
 * Updates the position of the dragged node (and potentially other selected nodes, though current implementation only moves the active one).
 * @param {object} d - The D3 data object for the dragged node.
 * @param {number} i - The index of the dragged node.
 */
function dragmove(d, i) {  
    
    // Update all selected nodes. A node is selected when the .foreground color is 190,228,248
    svg.selectAll(".node").each(function(d, i) {
        //const color = window.d3.select(this).select(".foreground").style("fill");
        if(d.id == currentNode) {
            // Update locations
            d.px += window.d3.event.dx;
            d.py += window.d3.event.dy;
            d.x += window.d3.event.dx;
            d.y += window.d3.event.dy;
        }   
    });

    // Update nodes & lines
    tick();                                                          

}

/**
 * Called when a D3 dragging event ends.
 * Sets the node's fixed status based on gravity settings, updates its position in local storage,
 * and resumes the force layout if applicable.
 * @param {object} d - The D3 data object for the dragged node.
 * @param {number} i - The index of the dragged node.
 */
function dragend(d, i) {
    
    // Update nodes & lines
    const gravity = getSetting('setting_gravity');
    d.fixed = ( gravity !== "aggressive");
    
    // Update the location in localstorage
    const record = getSetting(d.id); 

    let obj;
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
    
    // Check if force may resume
    if(gravity !== "off") {
        force.resume(); 
    }

    if(currentNode == d.id){
        currentNode = null;
    }
}