/**
* exporter.js: Functions to download the displayed nodes in different formats
*   Currently supports: GEPHI
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

class VisualiseExporter{

    visualiser = null;

    #output = '';

    exportDiv = null;
    exportButtons = {};

    constructor(visualiserContext){

        this.visualiser = visualiserContext;

        this.#addExportButtons();
    }

    gephi(){

        let data = this.visualiser.getCurrentData(this.visualiser.data);

        let nodes = '<nodes>';
        for(const node of data.nodes){

            const name = window.hWin.HEURIST4.util.htmlEscape(node.name);
            const rectype = node.image && node.image.indexOf('&icon=') > 0 ? parseInt(window.hWin.HEURIST4.util.getUrlParameter('icon', node.image)) : '';
            const image_url = window.hWin.HEURIST4.util.htmlEscape(node.image);

            nodes += `
                    <node id="${node.id}" label="${name}">
                        <attvalues>
                            <attvalue for="0" value="${name}"/>
                            <attvalue for="1" value="${image_url}"/>
                            <attvalue for="2" value="${rectype}"/>
                            <attvalue for="3" value="${node.count > 0 ? node.count : 0}"/>
                        </attvalues>
                    </node>`;
        }
        nodes += `
                </nodes>`;

        let edges = '<edges>';
        for(const id in data.links){

            const edge = data.links[id];
            const name = window.hWin.HEURIST4.util.htmlEscape(edge.relation.name);
            const weight = edge.targetcount > 0 ? edge.targetcount : 1;
            const rel_ID = window.hWin.HEURIST4.util.isPositiveInt(edge.relation.id) ? edge.relation.id : 0;
            const image_url = Object.hasOwn(edge.relation, 'image') && !window.hWin.HEURIST4.util.isempty(edge.relation.image)
                ? edge.relation.image.replace(/&/g,'&amp;') : '';

            edges += `
                    <edge id="${id}" source="${edge.source.id}" target="${edge.target.id}" weight="${weight}">
                        <attvalues>
                            <attvalue for="0" value="${rel_ID}"/>
                            <attvalue for="1" value="${name}"/>
                            <attvalue for="2" value="${image_url}"/>
                            <attvalue for="3" value="${weight}"/>
                        </attvalues>
                    </edge>`;
        }
        edges += `
                </edges>`;

        this.#output = `<?xml version="1.0" encoding="UTF-8"?>
        <gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.gexf.net/1.2draft https://gexf.net/1.2draft/gexf.xsd" version="1.2">
            <meta lastmodifieddate="${(new Date()).toISOString().split('T')[0]}">
                <creator>HeuristNetwork.org</creator>
                <description>Visualisation export</description>
            </meta>
            <graph mode="static" defaultedgetype="directed">
                <attributes class="node">
                    <attribute id="0" title="name" type="string"/>
                    <attribute id="1" title="image" type="string"/>
                    <attribute id="2" title="rectype" type="string"/>
                    <attribute id="3" title="count" type="float"/>
                </attributes>
                <attributes class="edge">
                    <attribute id="0" title="relation-id" type="float"/>
                    <attribute id="1" title="relation-name" type="string"/>
                    <attribute id="2" title="relation-image" type="string"/>
                    <attribute id="3" title="relation-count" type="float"/>
                </attributes>
                ${nodes}
                ${edges}
            </graph>
        </gexf>`;

        this.#downloadFile(`${window.hWin.HAPI4.database}.gexf`);
    }

    links(){

        // url
        let query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        query += `${query === '?' ? '' : '&'}db=${window.hWin.HAPI4.database}`;
        const url = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php${query}`;

        // encode url
        query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        const url_enc = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php${query}`;

        window.hWin.HEURIST4.ui.showPublishDialog({mode:'graph', url: url, url_encoded: url_enc});
    }

    #downloadFile(filename, mimetype){

        if(window.hWin.HEURIST4.util.isempty(this.#output)){
            return;
        }
        if(window.hWin.HEURIST4.util.isempty(filename)){
            filename = `${window.hWin.HAPI4.database}.txt`;
        }
        if(window.hWin.HEURIST4.util.isempty(mimetype)){
            mimetype = 'text/plain';
        }

        const content = `data:${mimetype};charset=utf-8,${encodeURIComponent(this.#output)}`;

        let link = document.createElement('a');
        link.setAttribute('download', filename);
        link.setAttribute('href', content);

        if(window.webkitURL !== null){

            // Chrome allows the link to be clicked
            // without actually adding it to the DOM.
            link.click();
            link = null;

        }else{

            // Firefox requires the link to be added to the DOM
            // before it can be clicked.
            link.onclick = () => { document.body.removeChild(link); link=null; } // destroy link;
            link.style.display = 'none';

            document.body.appendChild(link);
            link.click();
        }
    }

    #addExportButtons(){

        this.exportDiv = window.d3.select('#setDivExport');

        this.exportButtons = {
            gephi: this.exportDiv.append('button').button({label: 'GEPHI'}).on('click', () => this.gephi()).attr('id', 'gephi-export'),
            embed: this.exportDiv.append('button').button({label: 'Embed', icon: 'ui-icon-globe', showLabel: false}).on('click', () => this.links()).attr('id', 'embed-export')
        };

        if(this.visualiser.options.isStructure || this.visualiser.options.isStandAlone){
            this.exportButtons.embed.style('visibility', 'hidden');
        }
    }
}