/**
* drag.js
* 
* Functions to add nodes and make them draggable
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

class VisualiseDrag{

    visualiser = null;

    #currentNode = null;

    #selectionDrag = null;
    #linkDrag = null;

    #linkSource = null;
    #linkTarget = null;
    #linkLine = null;
    #linkTimer = null;

    constructor(visualiserContext){

        this.visualiser = visualiserContext;

        this.#selectionDrag = window.d3.behavior.drag()
                        .on('dragstart', () => this.#selectionDragStart())
                        .on('drag', () => this.#selectionDragMove())
                        .on('dragend', () => this.#selectionDragEnd());

        this.#linkDrag = window.d3.behavior.drag()
                        .on('dragstart', () => this.#linkDragStart())
                        .on('drag', () => this.#linkDragMove())
                        .on('dragend', () => this.#linkDragEnd());
    }

    get selectionDrag(){
        return this.#selectionDrag;
    }
    get linkDrag(){
        return this.#linkDrag;
    }

    #selectionDragStart(data){

        window.d3.event.sourceEvent.stopPropagation();
        window.d3.event.sourceEvent.preventDefault();

        this.visualiser.force.stop();

        // Fixed node positions?
        const gravity = this.visualiser.settings.get('gravity');
        this.visualiser.svg.selectAll('.node').attr('fixed', (data) => {
            data.fixed = gravity === 'off';
            return data.fixed;
        });

        data.fixed = true; 

        this.#currentNode = data.id;

        let colour = this.visualiser.selection.colour;
        this.visualiser.updateShape('circles', [`.node.id${data.id}`, colour, colour]); // updateCircles
    }

    #selectionDragMove(){

        // Update all selected nodes. A node is selected when the .foreground color is 190,228,248
        this.visualiser.svg.selectAll(`.node.id${this.#currentNode}`).each((data) => {

            let event = window.d3.event;

            if(data.id == currentNode){

                // Update locations
                data.px += event.dx;
                data.py += event.dy;
                data.x += event.dx;
                data.y += event.dy;
            }   
        });

        // Update nodes & lines
        this.visualiser.tick();
    }

    #selectionDragEnd(data){

        // Update nodes & lines
        const gravity = this.visualiser.settings.get('gravity');
        data.fixed = gravity !== 'aggressive';

        // Update the location in localstorage
        const record = this.visualiser.settings.get(data.id);

        let obj;
        if(record === null) {
            obj = {};
        }else{
            obj = JSON.parse(record);
        }

        // Set attributes 'x' and 'y' and store object
        obj.px = data.px;
        obj.py = data.py;
        obj.x = data.x;
        obj.y = data.y;
        this.visualiser.settings.put(data.id, JSON.stringify(obj));

        // Check if force may resume
        if(gravity !== 'off'){
            this.visualiser.force.resume();
        }

        if(this.#currentNode == data.id){
            this.#currentNode = null;
        }
    }

    #linkDragStart(data){

        let event = window.d3.event.sourceEvent;
        event.stopPropagation();

        this.#linkSource = data.id;

        let node = $(`.node.id${data.id}`);
        const x = node.offset().left - 5;
        const y = node.offset().top - 55;

        let svgPos = $('svg').position();
        const dx = x < (event.clientX - svgPos.left) ? -2 : 2;
        const dy = y < (event.clientY - svgPos.top) ? -2 : 2;

        this.#linkLine = this.visualiser.svg.append('svg:line')
            .attr('stroke', '#ff0000')
            .attr('stroke-width', 4)
            .attr('x1', x).attr('y1', y)
            .attr('x2', event.clientX - svgPos.left + dx)
            .attr('y2', event.clientY - svgPos.top + dy);
    }

    #linkDragMove(){

        if(!this.#linkLine){
            return;
        }

        let event = window.d3.event.sourceEvent;
        let svgPos = this.visualiser.svg.position();

        const dx = this.#linkLine.attr('x1') < (event.clientX - svgPos.left) ? -2 : 2;
        const dy = this.#linkLine.attr('y1') < (event.clientY - svgPos.top) ? -2 : 2;

        this.#linkLine
            .attr('x2', event.clientX - svgPos.left + dx)
            .attr('y2', event.clientY - svgPos.top + dy);
    }

    #linkDragEnd(){

        if(this.#linkSource != null && this.#linkTarget != null){
            this.visualiser.overlay.addNewLinkField(this.#linkSource, this.#linkTarget);
            setTimeout(() => this.#linkLine.attr('stroke', '#00ff00'), 500);
        }else{
            this.#linkSource = null;
            if(this.#linkLine) this.#linkLine.remove();
            this.#linkLine = null;
        }
    }

    linkMouseover(data){

        if(!this.#linkSource){
            return
        }

        //cancel timer
        if(this.#linkTimer > 0){
            clearTimeout(this.#linkTimer);
            this.#linkTimer = 0;
        }

        this.#linkTarget = data.id;
        this.#linkLine.attr('stroke', '#00ff00');
    }

    linkMouseout(){

        if(!this.#linkSource){
            return;
        }

        this.#linkTimer = setTimeout(() => {
            this.#linkTarget = null;
            if(this.#linkLine) this.#linkLine.attr('stroke', '#ff0000');
        }, 300);
    }

    removeLink(){

        this.#linkSource = null;
        this.#linkTarget = null;

        if(this.#linkLine) this.#linkLine.remove();
        this.#linkLine = null;

        if(this.#linkTimer > 0){
            clearTimeout(this.#linkTimer);
            this.#linkTimer = 0;
        }
    }
}