
/**
* gephi.js: Functions to download the displayed nodes in GEPHI format
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
/* global settings */

 // Functions to download the displayed nodes in GEPHI format.

/** Transforms the visualisation into Gephi format */
function getGephiFormat() {
    // Get data
    let data = settings.getData.call(this, settings.data);
                                                             
    // META
    let gexf = '<?xml version="1.0" encoding="UTF-8"?>';
    
    gexf += '<gexf xmlns="http://www.gexf.net/1.2draft"';
gexf += ' xmlns:xsi="http://www.w3.org/2001/XMLSchema−instance"';
gexf += ' xsi:schemaLocation="http://www.gexf.net/1.2draft https://gexf.net/1.2draft/gexf.xsd"';
gexf += ' version="1.2">';
//    gexf +=      '<gexf xmlns="http://www.gexf.net/1.2draft" version="1.2">';
    gexf +=        '<meta lastmodifieddate="'+ (new Date()).toISOString().split('T')[0] +'">';
    gexf +=          '<creator>HeuristNetwork.org</creator>';
    gexf +=          '<description>Visualisation export</description>';
    gexf +=        '</meta>' ;
    gexf +=        '<graph mode="static" defaultedgetype="directed">';
    
    // NODE ATTRIBUTES
    gexf += '<attributes class="node">';
    gexf +=     '<attribute id="0" title="name" type="string"/>';
    gexf +=     '<attribute id="1" title="image" type="string"/>';
    gexf +=     '<attribute id="2" title="rectype" type="string"/>';
    gexf +=     '<attribute id="3" title="count" type="float"/>';
    gexf += '</attributes>';
    
    // EDGE ATTRIBUTES
    gexf += '<attributes class="edge">';
    gexf +=     '<attribute id="0" title="relation-id" type="float"/>';
    gexf +=     '<attribute id="1" title="relation-name" type="string"/>';
    gexf +=     '<attribute id="2" title="relation-image" type="string"/>';
    gexf +=     '<attribute id="3" title="relation-count" type="float"/>';
    gexf += '</attributes>';
     
    // NODES
    gexf += '<nodes>';
    for(let key in data.nodes) {
        let node = data.nodes[key];
        
        const name = window.hWin.HEURIST4.util.htmlEscape(node.name);
        let rectype = '';
        if(node.image && node.image.indexOf('&icon=')>0){
             rectype = parseInt(node.image.substring(node.image.indexOf('&icon=')+6));
        }
        const image_url = window.hWin.HEURIST4.util.htmlEscape(node.image);
        
        
        gexf += '<node id="'+node.id+'" label="'+name+'">';
        gexf +=     '<attvalues>';
        gexf +=         '<attvalue for="0" value="'+name+'"/>';
        gexf +=         '<attvalue for="1" value="'+image_url+'"/>'; //(node.image?node.image.replace(/&/g,'&amp;'):'')
        gexf +=         '<attvalue for="2" value="'+rectype+'"/>';
        if(node.count>0){
        gexf +=         '<attvalue for="3" value="'+node.count+'"/>';
        }
        gexf +=     '</attvalues>'; 
        gexf += '</node>';
    }
    gexf += '</nodes>';
    
    // EDGES
    gexf += '<edges>';
    for(let i = 0; i < data.links.length; i++) {
        let edge = data.links[i]; 
        const name = window.hWin.HEURIST4.util.htmlEscape(edge.relation.name);
        
        gexf += '<edge id="'+i+'" source="'+edge.source.id+'" target="'+edge.target.id+'" weight="'
                    +(edge.targetcount>0?edge.targetcount:1)+'">';
        gexf +=     '<attvalues>';  
        
        if(!isNaN(Number(edge.relation.id))){
        gexf +=         '<attvalue for="0" value="'+edge.relation.id+'"/>';      
        }
        gexf +=         '<attvalue for="1" value="'+name+'"/>';
        gexf +=         '<attvalue for="2" value="'+(edge.relation.image?edge.relation.image.replace(/&/g,'&amp;'):'')+'"/>';
        gexf +=         '<attvalue for="3" value="'+(edge.targetcount>0?edge.targetcount:1)+'"/>';
        gexf +=     '</attvalues>';
        gexf += '</edge>';
    }
    gexf += '</edges>';
    
    // COMPLETE
    gexf +=         '</graph>';
    gexf +=       '</gexf>';
    
    // DOWNLOAD 
    //that's duplication of  window.hWin.HEURIST4.util.downloadData(getDatabaseName()+".gexf", gexf);
    const filename = window.hWin.HAPI4.database+".gexf"; //getDatabaseName()
    const mimeType = 'text/plain';
    const content = 'data:' + mimeType  +  ';charset=utf-8,' + encodeURIComponent(gexf);

    let link = document.createElement("a");
    link.setAttribute('download', filename);
    link.setAttribute('href', content);
    if (window.webkitURL != null)
    {
        // Chrome allows the link to be clicked
        // without actually adding it to the DOM.
        link.trigger('click');        
        link = null;
    }
    else
    {
        // Firefox requires the link to be added to the DOM
        // before it can be clicked.
        link.onclick = function(){ document.body.removeChild(link); link=null;} //destroy link;
        link.style.display = "none";
        document.body.appendChild(link);
        link.trigger('click');        
    }

}