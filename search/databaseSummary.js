/*
* Copyright (C) 2005-2014 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Heurist Database Summary Helper 
*
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>    
* @copyright   (C) 2005-2014 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/** D3 */
var data = [
    {
        name: 'City',
        columns: [
            'id',
            'country_id',
            'name',
            'population'
        ]
    },
    {
        name: 'Country',
        columns: [
            'id',
            'continent_id',
            'name',
            'population',
            'gpd',
            'average_salary',
            'birth_rate'
        ]
    },
    {
        name: 'Continent',
        columns: [
            'id',
            'name',
            'population'
        ]
    }
];
console.log(data);                    

/** SVG details */
var fieldHeight = 30;
var fieldWidth = 150;
var offset = 300;
var svg = d3.select("svg");

/**
* Attaches drag detection to an element
*/
var drag = d3.behavior.drag()
             .origin(function() { 
                var t = d3.select(this);
                return {x: t.attr("x"), y: t.attr("y")};
             })
             .on("drag", dragMove);

/**
* Called when an element has been dragged
*/
function dragMove(d) {
    console.log(d3.event);
    var x = d3.event.x;
    var y = d3.event.y;
    
    d3.select(this)
      .attr("x", x)
      .attr("y", y)
      .attr("transform", "translate(" +x+ ", " +y+ ")");
}

/**
* Creates a 'g' element for each table
*/
var tables = svg.selectAll("g")
                .data(data)
                .enter()
                .append("g")
                .attr("class", "table")
                .attr("x", function(d, i) { 
                    console.log(offset);
                    return (offset + i*fieldWidth*1.05);
                })
                .attr("y", 0)
                .attr("transform", function(d, i) {
                    console.log(offset);
                    return "translate(" + (offset + i*fieldWidth*1.1) + ", 0)"; 
                })
                .call(drag);

/**
* Creates a 'g' element for each cell inside the table
*/
var cells = tables.selectAll("g")
                  .data(function(d) {
                        return d3.values(d.columns);
                    })
                  .enter()
                  .append("g")
                  .attr("class", "cell")
                  .attr("transform", function(d, i) {
                        return "translate(0, " + i*fieldHeight + ")"; 
                  });
/**
* Creates a 'rect' element inside a cell
*/
cells.append("rect")
     .attr("class", "empty")
     .attr("width", fieldWidth-1)
     .attr("height", fieldHeight-1);

/**
* Creates a 'text' element inside a cell
*/
cells.append("text")
     .text(String)
     .attr("class", "center")
     .attr("x", fieldWidth/2)
     .attr("y", fieldHeight/2);




console.log("Done");