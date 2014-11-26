/**
* H4 connections between records
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.connections", {

    // default options
    options: {
        title: '',
        is_single_selection: false, //work with the only record
        recordset: null,
        selection: null,
        url:null
    },

    _query_request: null, //keep current query request
    _events: null,

    // the constructor
    _create: function() {

        var that = this;
        
        this.div_content = $('<div>')
                   .css({
                        position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                        'background':'url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center'})
                   .appendTo( this.element );
                   
        this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.div_content );


        //-----------------------     listener of global events
        this._events = top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT + ' ' 
            + top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + top.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {
            // Login
            if(e.type == top.HAPI4.Event.LOGIN){

                that._refresh();

            // Logout
            }else  if(e.type == top.HAPI4.Event.LOGOUT) { 
                
                that.option("recordset", null);

            // Search results
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

                that.option("recordset", data); //hRecordSet
                that.loadanimation(false);

            // Search start
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                that._query_request = data;  //keep current query request 
                that.option("recordset", null);
                that.loadanimation(true);
              
            // Record selection  
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id')) { 
                   
                   data = data.selection;
                
                   if(data && (typeof data.isA == "function") && data.isA("hRecordSet") ){
                       that.option("selection", data);
                   }else{
                       that.option("selection", null);
                   }
                }
            }
        });

        
        // Refreshing
        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        if(!this.options.is_single_selection){
            this.dosframe.on('load', function(){
                    that._refresh();
            });
        }
        
    }, //end _create


    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },  

    /* private function */
    _refresh: function(){

        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }
        
        //refesh if element is visible only - otherwise it costs much resources        
        if(!this.element.is(':visible') || top.HEURIST4.util.isempty(this.options.url)) return;
        
        // Single selection
        if(this.options.is_single_selection){
            
            var newurl = "common/html/msgNoRecordsSelected.html";
            
            if (this.options.selection!=null) {
                
                var recIDs_list = this.options.selection.getIds();
                
                if(recIDs_list.length>0){
                     var recID = recIDs_list[recIDs_list.length-1];
                     newurl = this.options.url.replace("[recID]", recID).replace("[dbname]",  top.HAPI4.database);         
                }
            }
            
            newurl = top.HAPI4.basePathOld +  newurl;
                
            if(this.dosframe.attr('src')!==newurl){
                this.dosframe.attr('src', newurl);
            }
       
        // Set new source     
        }else if(this.dosframe.attr('src')!==this.options.url){
            
            this.options.url = top.HAPI4.basePathOld +  this.options.url.replace("[dbname]",  top.HAPI4.database);
            this.dosframe.attr('src', this.options.url);
          
        // Content loaded already    
        }else{
            // SPRING DIAGRAM CODE
            console.log("CONTENT LOADED ALREADY");  
            
            if(this.options.recordset !== null) {
                var records = this.options.recordset.getRecords();
                console.log(records);  
                
                // TODO: Update to H4 completely after Artem adds relationships
                var data = parseRecSet2(records);  // Parse the Javascript data
                visualize(data);                   // Visualize the data
            }
            
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        var that = this;

        // remove generated elements
        this.dosframe.remove();
        this.div_content.remove();
    },
    
    loadanimation: function(show){
        if(show){
            //this.dosframe.hide();
            this.div_content.css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
            //this.dosframe.show();
        }
    },

});




/** Gets the selected IDs from top.HEURIST.search */
function getSelectedIDs() {
    var selectedIDs = [];  
    var recIDs = top.HEURIST.search.getSelectedRecIDs(); 
    if(recIDs) {
        for(var key in recIDs) {
            if(!isNaN(key)) {
                selectedIDs.push(recIDs[key]);       
            }
        }
    } 
    console.log("SELECTED IDs");
    console.log(selectedIDs);
    return selectedIDs;
}

/** Parses a recSet into usable data */ 
function parseRecSet() {
    var selectedIDs = getSelectedIDs();    
    var results = top.HEURIST.search.results;
    var nodes = {};
    var links = [];
            
    // Building nodes
    for(var id in results.recSet) {
        // Get details
        var record = results.recSet[id].record;
        var depth = results.recSet[id].depth;
        var name = record["5"];  
        var group = record["4"];
        var image = top.HEURIST.iconBaseURL + group + ".png";
        var selected = selectedIDs.indexOf(id.toString()) > -1;

        // Construct node
        var node = {id: parseInt(id), name: name, image: image, count: 1, depth: depth, selected: selected};
        nodes[id] = node;    
        //console.log("Node #" + id);    
    }
    
    
    /**
    * Finds links in a revPtrLinks or revRelLinks object
    */
    function findLinks(source, object, type) {
        var recIDs = object.byRecIDs;
        for(var recID in recIDs) {
            //console.log("ID " +id+ " points to recID: " + recID);
            var target = nodes[recID];
            if(target !== undefined) {
                var ids = recIDs[recID];
                //console.log("RELATION ID's");
                //console.log(ids);
                if(ids !== undefined && ids.length > 0) {
                    for(var i = 0; i < ids.length; i++) {
                        // Define relation    
                        //console.log("Relation #" + i + " ID: " + ids[i]);        
                        var relation = nodes[ids[i]];
                        if(relation === undefined) {
                            // Look up the typedef by ID
                            var typedef = top.HEURIST.detailTypes.typedefs[ids[i]];
                            //console.log(typedef);
                            if(typedef !== undefined) {
                                // Construct relation details
                                var name = typedef.commonFields[1];
                                var group = "group";
                                var image = top.HEURIST.iconBaseURL + group + ".png";
                                relation = {id: ids[i], name: name, image: image, count: 1, pointer: type.indexOf("ointer")>0};
                            }
                        }
                        
                        // Construct a link
                        if(relation !== undefined) {
                            var link;
                            if(type.indexOf("ointer")>0) { // Swap source & target
                                link = {source: target, relation: relation, target: source, targetcount: source.count};
                            }else{ // Source --> Target
                                link = {source: source, relation: relation, target: target, targetcount: target.count};
                            }
                            //console.log("LINK");
                            //console.log(link);   
                            links.push(link);  
                        }
                    } 
                }  
            }
        }
    }

    // Go through all records
    for(var id in results.recSet) {
        //console.log("RecSet["+id+"]:");
        //console.log(results.recSet[id]);
        var source = nodes[id];
        
        // Determine links
        if(source !== undefined) {
            var map = {"ptr": "Pointer", "revPtr": "Reverse Pointer", "revPtrLinks": "Reverse pointer link",
                       "rel": "Relationship", "relLinks": "Relationship marker", "revRelLinks": "Reverse relationship marker"};
                       
            for(var key in map) {
                var object = results.recSet[id][key];
                //console.log(key + " for recSet["+id+"]");
                //console.log(object);
                if(object !== undefined) {
                    findLinks(source, object, map[key]);
                }
            }
        }
    }
    
    // Construct data object
    var data = {nodes: nodes, links: links};
    console.log("DATA");
    console.log(data);
    return data;
}





/** RecSet parsing for Heurist 4 */
function parseRecSet2(records) {
    var data = {};
    var nodes = {};
    var links = [];
                      
    // All nodes
    for(var id in records) {
        var node = {id: parseInt(id),
                    name: records[id][5],
                    image: top.HAPI4.iconBaseURL+records[id][4],
                    count: 0,
                    depth: 1
                   };
        nodes[id] = node;
    }
    
    // Construct data object
    var data = {nodes: nodes, links: links};
    console.log("DATA");
    console.log(data);
    return data;
}

/** Calls the visualisation plugin */
function visualize(data) {
    console.log("Visualize called in connections.js");

    // Call plugin
    var iframe = $("iframe[src*=springDiagram]");
    iframe[0].contentWindow.showData(data);
    
    
}