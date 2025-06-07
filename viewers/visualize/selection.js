/**
* selection.js: Functions to select nodes in the visualisation
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

class VisualiseSelection{

    visualiser = null;

    mode = 'single';

    #defForegroundColour = '#FFF';
    #rightClicked = false; // @todo - rename

    #selectionColour = '#BEE4F8';
    #selectionBox = null;
    #selectedNodeIds = [];

    #positions = {};

    constructor(visualiserContext, selectedNodeIds = []){

        this.visualiser = visualiserContext;

        let group = this.visualiser.svg.append('g')
                                       .attr('class', 'selector-group');

        this.#selectionBox = group.append('rect')
                                  .attr('id', 'selectionBox')
                                  .attr('x', 0)
                                  .attr('y', 0);

        this.visualiser.svg.on('contextmenu', () => window.d3.event.preventDefault())
                           .on('mousedown', () => this.#onMouseDown())
                           .on('mousemove', () => this.#onMouseMove())
                           .on('mouseup', () => this.#onMouseUp());

        if(Array.isArray(selectedNodeIds)){
            this.#selectedNodeIds = selectedNodeIds;
        }else if(window.hWin.HEURIST4.util.isPositiveInt(selectedNodeIds)){
            this.#selectedNodeIds = [selectedNodeIds];
        }
        this.#cleanSelectedIDs();
    }

    #onMouseDown(){

        if(this.visualiser.options.isStructure){
            $('body.popup div.layout-container').first().layout().close('west');
        }

        this.#rightClicked = this.mode === 'multi';

        if(!this.#rightClicked){
            return;
        }

        window.d3.event.preventDefault();
        this.visualiser.svg.on('.zoom', null);

        // X-position
        this.#positions.x1 = window.d3.event.offsetX; 
        this.#positions.clickX1 = window.d3.event.x;
    
        // Y-position 
        this.#positions.y1 = window.d3.event.offsetY; 
        this.#positions.clickY1 = window.d3.event.y;
        
        // Deselect all nodes
        const bgColor = this.visualiser.settings.get('entitycolor');
        this.updateCircles('.node', this.#defForegroundColour, bgColor);
    }

    #onMouseMove(){

        if(!this.#rightClicked){
            return;
        }

        // X-positions
        this.#positions.x2 = window.d3.event.offsetX;
        this.#positions.clickX2 = window.d3.event.x;

        if(this.#positions.x1 < this.#positions.x2) {
            this.#selectionBox.attr('x', this.#positions.x1);
        }else{
            this.#selectionBox.attr('x', this.#positions.x2);
        }
        this.#selectionBox.attr('width', Math.abs(this.#positions.x2-this.#positions.x1));

        // Y-positions
        this.#positions.y2 = window.d3.event.offsetY;
        this.#positions.clickY2 = window.d3.event.y;

        if(this.#positions.y1 < this.#positions.y2) {
            this.#selectionBox.attr('y', this.#positions.y1);
        }else{
            this.#selectionBox.attr('y', this.#positions.y2);
        }
        this.#selectionBox.attr('height', Math.abs(this.#positions.y2-this.#positions.y1));
        this.#selectionBox.style('display', 'block');
    }

    #onMouseUp(){

        if(!this.#rightClicked){
    
            // Remove all selections
            const bgColor = this.visualiser.settings.get('entitycolor');
            this.updateCircles('.node', this.#defForegroundColour, bgColor);
    
            this.visualiser.svg.call(this.visualiser.zoomBehaviour);
            return;
        }
    
        this.#rightClicked = false;
        this.#selectionBox.style('display', 'none'); 
    
        // Calculate which nodes are in the selection box
        window.d3.selectAll('.node').each((data) => {
    
            let selector = `.node.id${data.id}`;
            let nodePos = $(selector).offset();
    
            let isWithinX = (nodePos.left >= this.#positions.clickX1 && nodePos.left <= this.#positions.clickX2) ||
                            (nodePos.left <= this.#positions.clickX1 && nodePos.left >= this.#positions.clickX2);// X in selection box?
            let isWithinY = (nodePos.top >= this.#positions.clickY1 && nodePos.top <= this.#positions.clickY2) ||
                            (nodePos.top <= this.#positions.clickY1 && nodePos.top >= this.#positions.clickY2);// Y in selection box?
    
            if(isWithinX && isWithinY){
                // Node is in selection box
                this.updateCircles(selector, this.#selectionColour, this.#selectionColour);
            }
        });
    
        this.visualiser.svg.call(this.visualiser.zoomBehaviour);
    }

    updateCircles(selector, fgColor, bgColor){

        if(!fgColor){
            fgColor = this.#defForegroundColour; 
        }

        let nodes = window.d3.selectAll(selector);
        nodes.select('.foreground').style('fill', fgColor);
        nodes.select('.background').style('fill', bgColor);
    }

    updateRectangles(selector, colour){

        let nodes = window.d3.selectAll(selector);
        nodes.select('rect.info-mode-full').style('fill', colour);
        nodes.select('rect.info-mode').style('fill', colour);
    }

    highlightSelection(selectedNodeIds){

        this.#selectedNodeIds = selectedNodeIds; // Update settings object
        this.#cleanSelectedIDs();

        let entity_colour = this.visualiser.settings.get('entitycolor');
        if(this.visualiser.currentMode == 'icons'){
            this.updateCircles('.node', this.#defForegroundColour, entity_colour); // Deselect all
        }else if(this.#selectedNodeIds && this.#selectedNodeIds.length > 0){
            this.updateRectangles('.node', entity_colour);
        }else{
            this.updateRectangles('.node', this.#defForegroundColour);
        }

        // Select new nodes
        for(const nodeID of this.#selectedNodeIds){

            let selector = `.id${nodeID}`;
    
            if(this.visualiser.currentMode == 'icons'){
                this.updateCircles(selector, this.#selectionColour, this.#selectionColour);
            }else{
                this.updateRectangles(selector, this.#selectionColour);
            }
        }
    }

    onSelection(data){

        let event = window.d3.event.sourceEvent;
        const rec_ID = typeof data.id === 'string' ? data.id : data.id.toString();
        let selector = `.id${rec_ID}`;
        
        if(this.#selectedNodeIds === null){
            this.#selectedNodeIds = [];
        }

        const bgColour = this.visualiser.settings.get('entitycolor');

        if(event.ctrlKey){
            let idx = this.#selectedNodeIds.indexOf(rec_ID);
            if(idx !== -1){

                this.updateCircles(selector, this.#defForegroundColour, bgColour);

                this.#selectedNodeIds.splice(idx, 1);
                this.visualiser.selectNode(this.#selectedNodeIds);

                return;
            }
        }else{
            this.updateCircles('.node', this.#defForegroundColour, bgColour);
            this.#selectedNodeIds = [];
        }

        data.selected = true;
        this.#selectedNodeIds.push(rec_ID);

        this.updateCircles(selector, this.#selectionColour, this.#selectionColour);

        let position = $(selector).offset();
        const r = this.visualiser.getEntityRadius(data.count);

        const dx = event.x - event.offsetX;
        const dy = event.y - event.offsetY;

        this.visualiser.overlay.createOverlay(Math.round(position.left - dx + r), Math.round(position.top - dy + r), 'record', `id${rec_ID}`, data);

        this.visualiser.selectNode(this.#selectedNodeIds);
    }

    #cleanSelectedIDs(){

        if(!Array.isArray(this.#selectedNodeIds)){
            this.#selectedNodeIds = [];
            return;
        }

        this.#selectedNodeIds = this.#selectedNodeIds.reduce((selected, id) => {
            if(!window.hWin.HEURIST4.util.isPositiveInt(id)){
                return selected;
            }

            selected.push(typeof id === 'string' ? id : id.toString());
        }, []);
    }
}