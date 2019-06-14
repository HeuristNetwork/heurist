/**
* Widget for network diagram of a result set. Calls hclient/visualize/springDiagram.php
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
        recordset: null,
        selection: null //list of record ids
    },

    _events: null,
    recordset_changed: true,

    // the constructor
    _create: function() {

        var that = this;
        
        this.framecontent = $('<div>')
                   .css({
                        width:'100%', height:'100%',
                       // position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                        'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})
                   .appendTo( this.element );
                   
        this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.framecontent );


        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS + ' ' 
            + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {
            
            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS) { 
                
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.recordset_changed = true;
                    that.option("recordset", null);
                }
                that._refresh();

            // Search results
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                //find all relation within given result set
                that.recordset_changed = true;
                that._getRelations( data );
                
                //that.option("recordset", data); //hRecordSet
                //that.loadanimation(false);

            // Search start
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                that.option("recordset", null);
                that.option("selection", null);
                if(data && data.q!=''){
                    that.loadanimation(true);
                }else{
                    that.recordset_changed = true;
                    that._refresh();
                }
                
                //???? that._refresh();
              
            // Record selection  
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id')) { //selection happened somewhere else
                  
                    that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                }            
            }
        });

        
        // Refreshing
        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        
        this.dosframe.on('load', function(){
                that._refresh();
        });
        
        
    }, //end _create


    /*
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },
    */  

    /* private function */
    _refresh: function(){

        /* change title
        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }*/
        
        //refesh if element is visible only - otherwise it costs much resources        
        if( this.element.is(':visible') && this.recordset_changed) {
        
            if(this.dosframe.attr('src')!==this.options.url){
                
                this.options.url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/visualize/springDiagram.php?db=' + window.hWin.HAPI4.database;
                this.dosframe.attr('src', this.options.url);
              
            // Content loaded already    
            }else{
                // SPRING DIAGRAM CODE
                // console.log("CONTENT LOADED ALREADY");  
                // console.log(this.options);
                
                if(this.options.recordset !== null) {
                    //console.log("Showing recordset connections");
                    
                    if(this.options.relations == null){ //relation not yet loaded
                        
                        this._getRelations(this.options.recordset);
                        
                    }else{
                        
                        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                    
                        var records_ids = this.options.recordset.getIds(MAXITEMS);
                        var relations = this.options.relations;
                        
                        // Parse response to spring diagram format
                        var data = this._parseData(records_ids, relations);
                        this._doVisualize(data);
                    
                    }
                }
                
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
        this.framecontent.remove();
    },
    
    loadanimation: function(show){
        if(show){
            //this.dosframe.hide();
            this.framecontent.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.framecontent.css('background','none');
            //this.dosframe.show();
        }
    },
    
    /**
    * private - send request to server side to find all relation withing given recordset
    * @param recordset
    */
    _getRelations: function( recordset ){
        //console.log("getRelations CALLED");
        //console.log(recordset);
        
        if(window.hWin.HEURIST4.util.isnull(recordset)) return;

        this.option("relations", null);
        
        if(!this.element.is(':visible')){
                this.option("recordset", recordset);
                return;
        }
        
        var that = this; 
        //get first MAXITEMS records and send their IDS to server to get related record IDS
        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
        var records_ids = recordset.getIds(MAXITEMS);
//console.log('was '+recordset.getIds().length+'  send for '+records_ids.length);        
        if(records_ids.length>0){
            
            var callback = function(response)
            {
                var resdata = null;
                if(response.status == window.hWin.ResponseStatus.OK){
                    // Store relationships
//console.log("Successfully retrieved relationship data!", response.data);
                    that.option("relations", response.data);
                    
                    // Parse response to spring diagram format
                    var data = that._parseData(records_ids, response.data);
                    that._doVisualize(data);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                that.option("recordset", recordset); //hRecordSet
                that.loadanimation(false);
                
            }

            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')}, callback);
        }
    }
    

    //@todo - move inside widget


    /**
    * Parses record data and relationship data into usable D3 format
    * 
    * @param records    Object containing all record
    * @param relations  Object containing direct & reverse links
    * 
    * @returns {Object}
    */
    , _parseData: function (records_ids, relations) {
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
                            depth: 1
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
                        //type = window.hWin.HEURIST4.detailtypes.typedefs[dtID].commonFields[1];
                        relationName = window.hWin.HEURIST4.detailtypes.names[dtID];
                    }else if(trmID > 0) {
                        relationName = window.hWin.HEURIST4.terms.termsByDomainLookup['relation'][trmID][0];
                    }

                    // Link check  - both source and target must be in main result set (nodes)
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

    /** Calls the visualisation plugin */
    , _doVisualize: function (data) {
        //console.log("Visualize called in connections.js");
        
        if( !window.hWin.HEURIST4.util.isnull(this.dosframe) && this.dosframe.length > 0 ){
            var that = this;
            this.dosframe[0].contentWindow.showData(data, this.options.selection, 
                    function(selected){
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        { selection:selected, source:that.element.attr('id') } );
                    },
                    function(selected){
                        that._getRelations(that.options.recordset);
                        //$(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, null);
                    }
            );
            this.recordset_changed = false;
        }
        /* Call showData method of the springDiagram iFrame
        var iframe = $("iframe[src*=springDiagram]");
        if(iframe != null && iframe !== undefined && iframe.length >= 1) {
            iframe[0].contentWindow.showData(data);
        }*/
    }    

    , _doVisualizeSelection: function (selection) {

            if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

            this.option("selection", selection);
            
            if(!this.element.is(':visible')
                || window.hWin.HEURIST4.util.isnull(this.dosframe) || this.dosframe.length < 1){
                    return;
            }
            
            this.dosframe[0].contentWindow.showSelection(this.options.selection);
    }    

});