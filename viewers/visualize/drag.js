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

    #drag = null;

    constructor(visualiserContext){

        this.visualiser = visualiserContext;

        this.#drag = window.d3.behavior.drag()
                        .on('dragstart', this.#dragStart)
                        .on('drag', this.#dragMove)
                        .on('dragend', this.#dragEnd);
    }

    get drag(){
        return this.#drag;
    }

    #dragStart(data){

        window.d3.event.sourceEvent.stopPropagation();
        window.d3.event.sourceEvent.preventDefault();

        this.visualiser.force.stop();

        // Fixed node positions?
        const gravity = this.visualiser.settings.get('gravity');
        this.visualiser.svg.selectAll('.node').attr('fixed', (d, i) => {
            d.fixed = gravity == 'off';
            return d.fixed;
        });

        data.fixed = true; 

        this.#currentNode = data.id;

        let colour = this.visualiser.selection.colour;
        this.visualiser.updateShape('circles', [`.node.id${data.id}`, colour, colour]); // updateCircles
    }

    #dragMove(){

        // Update all selected nodes. A node is selected when the .foreground color is 190,228,248
        this.visualiser.svg.selectAll(`.node.id${this.#currentNode}`).each((d, i) => {

            if(d.id == currentNode) {
                // Update locations
                d.px += window.d3.event.dx;
                d.py += window.d3.event.dy;
                d.x += window.d3.event.dx;
                d.y += window.d3.event.dy;
            }   
        });

        // Update nodes & lines
        this.visualiser.tick();
    }

    #dragEnd(data){

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
}