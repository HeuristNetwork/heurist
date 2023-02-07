<?php

    /**
    * springDiagram.php: Renders search resultset as a network diagram. Uses hclient/visualize/visualize.js
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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

if(!defined('PDIR')) define('PDIR','../../../');
require_once(dirname(__FILE__)."/../initPage.php");
?>
        <style>
            body, html {
                background-color: #fff;
            }
        </style>

        <!-- D3 -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/d3.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/fisheye.js"></script>

        <!-- Colpick -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
        <link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">
        
        <!-- Visualize plugin -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/settings.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/overlay.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/selection.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/gephi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/drag.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.css">
        
        <script type="text/javascript">

var isStandAlone = false;
        
// Callback function on page initialization - see initPage.php
function onPageInit(success){

    if(!success) return;
                
        var q = window.hWin.HEURIST4.util.getUrlParameter('q', location.search);
        
        //t:26 f:85:3313  f:1:building
        // Perform database query if possible (for standalone mode - when map.php is separate page)
        if( !window.hWin.HEURIST4.util.isempty(q) )
        {
            isStandAlone = true;
            
            var rules = window.hWin.HEURIST4.util.getUrlParameter('rules', location.search);
            
            if(!window.hWin.HEURIST4.util.isempty(rules)){
                try{
                    rules = JSON.parse(rules);
                }catch(ex){
                    rules = null;    
                }
            }else{
                rules = null;
            }
            
            var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');

            window.hWin.HAPI4.RecordMgr.search({q: q, rules:rules, w: "a", detail:'detail', l:MAXITEMS},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        //console.log("onNetInit response");
                        //console.log(response);

                        var recordset = new hRecordSet(response.data);
                          
                        var records_ids = recordset.getIds(MAXITEMS);
                //console.log('was '+recordset.getIds().length+'  send for '+records_ids.length);        
                        if(records_ids.length>0){
                            
                            var callback = function(response)
                            {
                                var resdata = null;
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    // Store relationships
                //console.log("Successfully retrieved relationship data!", response.data);
                                    
                                    // Parse response to spring diagram format
                                    var data = __parseData(records_ids, response.data);
                                    
                                    showData(data, [], null, null);
                                    
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                                
                            }

                            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')}, callback);
                        }
                                    

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        }
}
   
        </script>
    </head>

    <body>
        <!-- Visualize HTML -->
        <?php include "visualize.html"; ?>

        <!-- Call from parent iframe -->
        <script>
        
        /**
        * Parses record data and relationship data into usable D3 format
        * 
        * @param records    Object containing all record
        * @param relations  Object containing direct & reverse links
        * 
        * @returns {Object}
        */
        function __parseData(records_ids, relations) {
            
            var data = {}; 
            var nodes = {};                         
            var links = [];

            if(records_ids !== undefined && relations !== undefined) {
                // Construct nodes for each record
                var i;
                for(i=0;i<records_ids.length;i++) {
                    var recId = records_ids[i];
                    var node = {id: parseInt(recId),
                                name: relations.headers[recId][0],  //record title   records[id][5]
                                image: window.hWin.HAPI4.iconBaseURL+relations.headers[recId][1],  //rectype id  records[id][4]
                                count: 0,
                                depth: 1,
                                rty_ID: relations.headers[recId][1] 
                               };
                    nodes[recId] = node;
                }
                
                
                /**
                * Determines links between nodes
                * 
                * @param nodes      All nodes
                * @param relations  Array of relations
                */
                function __getLinks(nodes, relations) {
                    var links = [];
                    
                    // Go through all relations
                    for(var i = 0; i < relations.length; i++) { 
                        // Null check
                        var source = relations[i].recID;
                        var target = relations[i].targetID;
                        var dtID = relations[i].dtID;
                        var trmID = relations[i].trmID;
                        var relationName = "Floating relationship";
                        if(dtID > 0) {
                            relationName = $Db.dty(dtID, 'dty_Name');
                        }else if(trmID > 0) {
                            relationName = $Db.trm(trmID, 'trm_Label');
                        }

                        // Link check
                        if(source !== undefined && nodes[source] !== undefined && target !== undefined && nodes[target] !== undefined) { 
                            // Construct link
                            var link = {source: nodes[source],
                                        target: nodes[target],
                                        targetcount: 1,
                                        relation: {id: dtID>0?dtID:trmID, 
                                                   name: relationName,
                                                   type: dtID>0?'resource':'relationship'} 
                                       };
                            links.push(link); 
                        }      
                    }   
                    
                    return links;
                }
                        
                       
                
                // Links
                links = links.concat( __getLinks(nodes, relations.direct)  ); // Direct links
                links = links.concat( __getLinks(nodes, relations.reverse) ); // Reverse links
            }

            // Construct data object with nodes as array
            var array = [];
            for(var id in nodes) {
                array.push(nodes[id]);
            }
            return {nodes: array, links: links};
        }
                            
        /** Shows data visually */
        function showSelection( selectedRecordsIds ){
             visualizeSelection( selectedRecordsIds );
        }

        //
        //
        //
        function showData(data, selectedRecordsIds, onSelectEvent, onRefreshData) {
               // Processing...
//if(data && data.nodes && data.links)
    //console.log("showData called inside springDiagram nodes:"+data.nodes.length+'  edges:'+data.links.length);
   
                if(data && data.nodes){
                    $("#d3svg").html('<text x="25" y="25" fill="black">Buiding graph ...</text>');
                }else{
                    $("#d3svg").html('<text x="25" y="25" fill="black">No data for graph</text>');
                    return;
                }

                // Custom data parsing
                function getData(data) {
                    //console.log("Custom getData() call");
                    return data;
                }

                // Calculates the line length
                function getLineLength(record) {
                    var length = getSetting(setting_linelength);
                    if(record !== undefined && record.hasOwnProperty("depth")) {
                        length = length / (record.depth+1);
                    }
                    return length;
                }
                
                $(window).resize(onVisualizeResize);
                onVisualizeResize();

                $("#visualize").visualize({
                    data: data,
                    getData: function(data) { return getData(data); },
                    getLineLength: function(record) { return getLineLength(record); },

                    selectedNodeIds: selectedRecordsIds,   //assign current selection
                    triggerSelection: onSelectEvent,
                    onRefreshData: onRefreshData,
                    /*function(selection){
                        //parentDocument    top.window.document
                        $(parentDocument).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, { selection:selection, source:'d3svg' } ); //this.element.attr('id')} );
                    },*/

                    entityradius: 1,
                    linewidth: 1,

                    showCounts: false,
                    showEntitySettings: false,
                    showFormula: false,
                    gravity: 'touch' // activate gravity, for a moment, to scatter graph
                });

                setTimeout(function(){ setGravity('off'); }, 3000); // turn off gravity

                changeViewMode('icons');
        }
            
        function onVisualizeResize(){
                var width = $(window).width();
                var supw = (width<744)?3.8:0; //1120
                $('#divSvg').css('top', 5+supw+'em');
        }
            
        </script>
    </body>

</html>