/**
* @file        connections.js
* @brief       Network graph/connections viewer.
* @fileOverview This file provides the `heurist.connections` jQuery UI widget, which is
*              responsible for displaying network diagrams of Heurist result sets. It
*              loads an iframe pointing to `viewers/visualize/springDiagram.php`,
*              which likely uses a library like VivaGraphJS or a similar
*              force-directed graph layout algorithm. The widget listens to Heurist
*              system events to receive record sets and selections, fetches
*              relationship data, parses it into a graph format (nodes and links),
*              and then passes this data to the iframe for visualization. It also
*              handles user interactions from the graph, such as node selection, and
*              can expand the graph by fetching related records.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
 * @widget heurist.connections
 * @description Displays a network graph of connections between records in a result set.
 * It embeds an iframe that loads `springDiagram.php` to render the graph.
 * The widget listens to Heurist events for data changes and selections,
 * processes the data to find relationships, and communicates with the
 * embedded graph visualization.
 */
$.widget( "heurist.connections", {

    /**
     * @memberof heurist.connections
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {string} [options.title=''] - Title for the connections viewer (not actively used to set header).
     * @property {?HRecordSet} options.recordset - The primary record set whose connections are to be visualized.
     * @property {?Array<number>} options.selection - An array of record IDs to be highlighted in the graph.
     * @property {?string} options.search_realm - A string identifier to scope event listening. Only events
     *           from the same realm will be processed.
     * @property {boolean} [options.init_at_once=false] - If true, attempts to load and display the graph
     *           immediately upon widget creation.
     */
    options: {
        egoGraph: false, // whether to use the newer minimal graph

        title: '',
        recordset: null,
        selection: null, //list of record ids
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null,  //Query or svs_ID for initial search
        init_at_once: false,

        show_recent_selection: false, //work with the recently selected record
        show_selection: false, //work with all selectd records
        show_page: false, //work with the current page of records
        show_all: true, //work with all records in the current results
        
        empty_remark: '', //html content for empty message  (search returns empty result)
        placeholder_text: '', //text to display while no record/recordset is loaded  (search is not performed)

        nodeLimit: 20, // limit the initial number of nodes, to avoid overworking the browser
        autoExtendSelection: true // auto extend the graph when is ONLY ONE record shown
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @property {?string} _events - A string concatenating Heurist event names that this widget listens to.
     * Initialized in `_create`.
     */
    _events: null,
    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @property {boolean} recordset_changed - Flag indicating if the main `recordset` has changed
     * and the graph display needs to be updated.
     */
    recordset_changed: true,

    page_recordIDs: null,

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Widget constructor. Initializes the iframe for the graph, sets up event listeners
     * for data changes and selections, and handles initial loading if `options.init_at_once` is true.
     */
    _create: function() {
        let that = this;
        
        this.framecontent = $('<div>')
                   .css({
                        width:'100%', height:'100%', overflow:'hidden',
                       // position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                        'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})
                   .appendTo( this.element );
                   
        this.graphframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.framecontent );


        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
            + ' ' + window.hWin.HAPI4.Event.ON_SYSTEM_INITED
            + ' ' + window.hWin.HAPI4.Event.ON_REC_PAGE_RENDERED;

        $(this.document).on(this._events, function(e, data) {

            let isSameRealm = that._isSameRealm(data);
            let fromThisSource = data?.source == that.element.attr('id');

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS) { 
                
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.recordset_changed = true;
                    that.options.recordset = null;
                    that._refresh();
                }
                

            // Search results
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                //accept events from the same realm only
                if(!isSameRealm) return;

                //find all relation within given result set
                that.recordset_changed = true;
                that.options.relations = null;
                that.options.recordset = data.recordset; //HRecordSet
                that._lastRequest = data.request; //last request, contains query

                that._refresh();

            // Search start
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                //accept events from the same realm only
                if(!isSameRealm) return;

                that.options.relations = null;
                that.options.recordset = null;
                that.options.selection = null;

                if(data && !data.reset && data.q!=''){
                    that.loadanimation(true);
                }else{
                    that.recordset_changed = true;
                    that._refresh();
                }
            // Record selection  
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(!isSameRealm || fromThisSource) return;

                if(that.options.show_selection || that.options.show_recent_selection){

                    let selection = that.options.show_recent_selection ? data.selection[data.selection.length - 1] : data.selection;
                    let sel = window.hWin.HAPI4.getSelection(selection, true); //get ids
                    that.options.relations = null;
                    that.options.selection = sel;
                    that.recordset_changed = true;
                    that._refresh();

                }else if(data.reset){
                    that.options.selection = null;
                }else{
                    that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_PAGE_RENDERED && isSameRealm && !fromThisSource && that.options.show_page){

                that.options.relations = null;
                that.page_recordIDs = data.selection;
                that.recordset_changed = true;

                that._refresh();

            }else if (e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
                that._refresh();
            }
        });

        
        this.graphframe.on('load', function(){
                that._refresh();
        });
        
        // Refreshing
        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });

        if(this.options.search_initial){
            this.doSearch( this.options.search_initial );
            this.options.search_initial = null;
        }else if(this.options.init_at_once){
            this._refresh();  
        }

    }, //end _create

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Initiates a Heurist search with the given query.
     * The search results will be handled by the global event listeners.
     * @param {string|Object} query - The search query string or query object.
     */
    doSearch: function(query){
        let request = {q:query, w: 'a', detail: 'ids', 
                        source: 'init', search_realm: this.options.search_realm };
        window.hWin.HAPI4.RecordSearch.doSearch(this.document, request);
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Refreshes the graph display. If the widget is visible and the recordset has changed:
     * - If the iframe (`graphframe`) is not yet loaded or its URL is incorrect, it sets the iframe's `src`
     *   to `viewers/visualize/springDiagram.php` with the current database parameter.
     * - If the iframe is loaded:
     *   - If `options.recordset` is available but `options.relations` is not, it calls `_getRelations()`
     *     to fetch relationship data.
     *   - If both `recordset` and `relations` are available, it parses this data using `_parseData()`
     *     and then visualizes it using `_doVisualize()`.
     *   - If `recordset` is null but the visualization is initialized, it clears the graph.
     */
    _refresh: function(){

        /* change title
        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }*/
        
        //refesh if element is visible only - otherwise it costs much resources        
        if( this.element.is(':visible') && this.recordset_changed) {

            if( window.hWin.HEURIST4.util.isempty(this.graphframe.attr('src')) || this.graphframe.attr('src')!==this.options.url)
            {
                
                this.options.url = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php?db=${window.hWin.HAPI4.database}&mini=${this.options.egoGraph ? 1 : 0}`;
                this.graphframe.attr('src', this.options.url);
              
            // Content loaded already
            }else{
                // SPRING DIAGRAM CODE
                let recset = this.options.show_selection || this.options.show_recent_selection || this.options.show_page ? this.options.selection : this.options.recordset;
                recset = this.options.show_page ? this.page_recordIDs : recset;

                if(recset !== null) {
                    
                    if(this.options.relations == null){ //relation not yet loaded
                        
                        this._getRelations(recset);
                        
                    }else{
                        
                        let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                    
                        let records_ids = Array.isArray(recset) ? recset : recset.getIds(MAXITEMS);
                        let relations = this.options.relations;
                        
                        // Parse response to spring diagram format
                        let data = this._parseData(records_ids, relations);
                        this._doVisualize(data);

                    }
                }else if(this._isVisualizeInited()){
                    //clear
                    this.graphframe[0].contentWindow.showData(null);
                }
                
            }
        
        }
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Cleans up the widget before it's removed. Unbinds global event listeners
     * and removes the generated iframe and container elements.
     */
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        let that = this;

        // remove generated elements
        this.graphframe.remove();
        this.framecontent.remove();
    },
    
    /**
     * @memberof heurist.connections
     * @instance
     * @description Shows or hides a loading animation overlay on the graph iframe container.
     * @param {boolean} show - True to show the loading animation, false to hide it.
     */
    loadanimation: function(show){
        if(show){
           
            this.framecontent.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.framecontent.css('background','none');
           
        }
    },
    
    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Checks if the provided event data belongs to the same search realm as this widget.
     * @param {Object} data - The event data object, expected to have a `search_realm` property.
     * @returns {boolean} True if realms match or if realms are not defined for comparison, false otherwise.
     */
    _isSameRealm: function(data){

        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        || (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    /**
    * @memberof heurist.connections
    * @instance
    * @private
    * @description Fetches relationship data for the given record set.
    * It sends a request to the server (`RecordMgr.search_related`) with the IDs from the
    * record set. On success, it stores the relationship data in `this.options.relations`,
    * parses the data using `_parseData`, and then visualizes it using `_doVisualize`.
    * @param {HRecordSet} recordset - The record set for which to fetch relationships.
    * @param {bool} extendingSelection - Is this call extending the previous call.
    */
    _getRelations: function( recordset, extendingSelection = false ){

        if(window.hWin.HEURIST4.util.isnull(recordset)) return;

        this.options.relations = null;
        
        if(!this.element.is(':visible')){
            return;
        }
        
        let that2 = this; 
        //get first MAXITEMS records and send their IDS to server to get related record IDS
        let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
        let records_ids = Array.isArray(recordset)?recordset:recordset.getIds(MAXITEMS);
        if(records_ids.length>0){

            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')},(response)=>
            {
                let resdata = null;
                if(response.status == window.hWin.ResponseStatus.OK){

                    let isSelection = this.options.show_selection || this.options.show_recent_selection;
                    if(!extendingSelection && isSelection && this.options.autoExtendSelection && Object.keys(response.data.headers).length > 1){ // automatically extend when in selection mode
                        this._getRelations(Object.keys(response.data.headers), true);
                        return;
                    }

                    // Store relationships
                    this.option("relations", response.data);
                    
                    // Parse response to spring diagram format
                    let data = this._parseData(records_ids, response.data);
                    this._doVisualize(data);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                //this.option("recordset", recordset); //HRecordSet
                this.loadanimation(false);
                
            });
        }
    },

    /**
    * @memberof heurist.connections
    * @instance
    * @private
    * @description Parses record IDs and relationship data into a format suitable for graph visualization (nodes and links).
    * Nodes are created from `records_ids` and information in `relations.headers`.
    * Links are created by processing `relations.direct` and `relations.reverse`.
    * @param {Array<number|string>} records_ids - An array of record IDs that form the primary nodes of the graph.
    * @param {Object} relations - An object containing relationship data. Expected to have:
    *   - `relations.headers`: An object mapping record IDs to their title and record type ID (for icons).
    *   - `relations.direct`: An array of direct relationships (source -> target).
    *   - `relations.reverse`: An array of reverse relationships (target -> source).
    * Each relationship object should have `recID` (source), `targetID`, `dtID` (detail type ID for resource relations),
    * and `trmID` (term ID for relationship types).
    * @returns {Object} An object with two properties: `nodes` (an array of node objects)
    *                   and `links` (an array of link objects).
    *                   Each node object has `id`, `name`, `image` (icon URL), `count`, `depth`, `rty_ID`.
    *                   Each link object has `source` (node object), `target` (node object), `targetcount`,
    *                   and `relation` (with `id`, `name`, `type`).
    */
    _parseData: function (records_ids, relations) {

        let data = {}; 
        let nodes = {};                         
        let links = [];

        if(records_ids !== undefined && relations !== undefined) {

            // Construct nodes for each record
            let i;
            for(i=0;i<records_ids.length;i++) {
                let recId = records_ids[i];
                if(relations.headers[recId]){
                    let node = {id: parseInt(recId),
                                name: relations.headers[recId][0],  //record title   records[id][5]
                                image: window.hWin.HAPI4.iconBaseURL+relations.headers[recId][1],  //rectype id  records[id][4]
                                count: 0,
                                depth: 1,
                                rty_ID: relations.headers[recId][1]
                               };
                    nodes[recId] = node;
                }
            }
            
            
            /**
            * Determines links between nodes
            * @ignore
            * @param {Object} nodes      All nodes keyed by ID.
            * @param {Array<Object>} relations  Array of relationship objects.
            * @returns {Array<Object>} Array of link objects.
            */
            function __getLinks(nodes, relations) {
                let links = [];
                
                // Go through all relations
                for(let i = 0; i < relations.length; i++) { 
                    // Null check
                    let source = relations[i].recID;
                    let target = relations[i].targetID;
                    let dtID = relations[i].dtID;
                    let trmID = relations[i].trmID;
                    let relationName = "Floating relationship";
                    if(dtID > 0) {
                        relationName = $Db.dty(dtID, 'dty_Name');
                    }else if(trmID > 0) {
                        relationName = $Db.trm(trmID, 'trm_Label');
                    }

                    // Link check  - both source and target must be in main result set (nodes)
                    if(source !== undefined && nodes[source] !== undefined && target !== undefined && nodes[target] !== undefined) { 
                        // Construct link
                        let link = {source: nodes[source],
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

        if(this._isVisualizeInited && window.hWin.HEURIST4.util.isFunction(this.graphframe[0].contentWindow.addNewConnections)){
            this.graphframe[0].contentWindow.addNewConnections(records_ids, relations);
        }

        // Construct data object with nodes as array
        let array = [];
        for(let id in nodes) {
            array.push(nodes[id]);
        }
        return {nodes: array, links: links};
    },
    
    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Checks if the graph visualization iframe is initialized and has the `showData` function.
     * @returns {boolean} True if the visualization is ready, false otherwise.
     */
    _isVisualizeInited(){
        
        return !window.hWin.HEURIST4.util.isnull(this.graphframe) && this.graphframe.length > 0 &&
         window.hWin.HEURIST4.util.isFunction(this.graphframe[0].contentWindow.showData);
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Sends data to the graph visualization iframe (`springDiagram.php`) to be rendered.
     * It calls the `showData` function within the iframe, passing the graph data, current selection,
     * last search request, and callback functions for interactions (selection, get relations, expand search).
     * @param {Object} data - The graph data, typically an object with `nodes` and `links` properties,
     *                        as returned by `_parseData`.
     */
    _doVisualize: function (data, skipNodeCheck = false) {

        let nodeCount = data.nodes.length;
        if(!skipNodeCheck && nodeCount > this.options.nodeLimit){

            let $dlg;
            let content = `<div>
                The website designer has limited the number of initial nodes to <strong>${this.options.nodeLimit}</strong> because<br>
                large number of nodes will bog down your browser and make an unreadable graph.<br>
                Node count: <strong>${nodeCount}</strong><br><br>

                <label> <input type="checkbox" id="enableContinue" /> check this box if you wish to proceed in any case</label>
            </div>`;

            let btns = {};
            btns[window.hWin.HR('Draw graph')] = () => {
                this._doVisualize(data, true);
                $dlg.dialog('close');
            };
            btns[window.hWin.HR('Cancel')] = () => {
                $dlg.dialog('close');
            };

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, {title: 'Exceeding limit set'}, {dialogId: 'visualiser-node-limit'});

            let $btnContinue = $dlg.parent().find('.ui-dialog-buttonpane button').first();
            this._on($dlg.find('#enableContinue'), {
                change: () => {
                    window.hWin.HEURIST4.util.setDisabled($btnContinue, !$dlg.find('#enableContinue').is(':checked'));
                }
            });
            window.hWin.HEURIST4.util.setDisabled($btnContinue, true);

            return;
        }

        if(this._isVisualizeInited() ){
            let that = this;
            this.graphframe[0].contentWindow.showData(data, this.options.selection, this._lastRequest,
                    function(selected){ //on select
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        { selection:selected, source:that.element.attr('id'), search_realm:that.options.search_realm } );
                    },
                    function(selected){ //on refresh
                        that._getRelations(that.options.recordset);
                    },
                    function(type, rec_ID){ //on expand search
                        that._expandSearch(type, rec_ID);
                    }
            );
            this.recordset_changed = false;
        }

    },    

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Sends the current selection (`this.options.selection`) to the graph visualization iframe
     * to highlight the selected nodes. Calls the `showSelection` function within the iframe.
     * @param {Array<number>} selection - An array of record IDs to be selected/highlighted in the graph.
     */
    _doVisualizeSelection: function (selection) {

            if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

            this.options.selection = selection;
            
            if(this.element.is(':visible') && this._isVisualizeInited()){
                this.graphframe[0].contentWindow.showSelection(this.options.selection);
            }
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Expands the graph by fetching and adding records related to a specific node (`rec_ID`).
     * It modifies the last search query (`this._lastRequest.q`) to include related records based on the
     * `type` of relationship (e.g., 'related', 'linked_to') and then performs a new search using `_performSearch`.
     * This method is typically called from an interaction within the graph visualization (e.g., double-clicking a node).
     * The query modification logic attempts to merge the new relationship predicate into existing 'any' clauses
     * or adds it appropriately to maintain a valid query structure.
     * @param {string} type - The type of relationship to expand by (e.g., "related", "linked_to", "related_from").
     *                        This corresponds to Heurist search predicates.
     * @param {number|string} rec_ID - The ID of the record (node) from which to expand connections.
     */
    _expandSearch: function(type, rec_ID){

        if(this.options.egoGraph){ // disabled for mini graph - this function is handled by the visualiser directly
            return;
        }

        let new_query = [];
        let existing_query = window.hWin.HEURIST4.util.isJSON(this._lastRequest.q);

        function mergeAnyPred(any_index){

            if(!this._mergeRecordIDs(existing_query[any_index]['any'], type, rec_ID)){

                new_query = {[type]: rec_ID};
                let new_any = window.hWin.HEURIST4.query.mergeHeuristQuery(existing_query[any_index]['any'], new_query);

                new_query = existing_query;
                new_query[any_index]['any'] = new_any;
            }else{
                new_query = existing_query;
            }
        }

        if(existing_query){ // JSON query

            let has_any = false;

            for(const key in existing_query){

                if(key == 'any' || (window.hWin.HEURIST4.util.isObject(existing_query) && Object.hasOwn(existing_query[key], 'any'))){
                    has_any = key;
                }
            }

            if(!has_any){
                // Add new top-level 'any' predicate

                new_query = [{[type]: rec_ID}];
                new_query = window.hWin.HEURIST4.query.mergeHeuristQuery(existing_query, new_query);
                new_query = [{any: new_query}];

            }else if(window.hWin.HEURIST4.util.isPositiveInt(has_any) || Number.parseInt(has_any) === 0){
                // Merge first existing top-level 'any' predicate

                mergeAnyPred(has_any); // new_query is updated within

            }else if(!this._mergeRecordIDs(existing_query, type, rec_ID)){
                // Merge 'any' predicates

                new_query = {any: [{[type]: rec_ID}]};
                new_query = window.hWin.HEURIST4.query.mergeHeuristQuery(existing_query, new_query);
            }

        }else if(typeof this._lastRequest.q === 'string' && !window.hWin.HEURIST4.util.isempty(this._lastRequest.q)){ // Old format
            new_query = window.hWin.HEURIST4.query.mergeHeuristQuery(this._lastRequest.q, [{[type]: rec_ID}]);
            new_query = [{any: new_query}];
        }

        if(window.hWin.HEURIST4.util.isempty(new_query)){
            return;
        }

        this._performSearch(new_query);
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Merges a new record ID into an existing query predicate of a specific `type`.
     * If the predicate `type` already exists in the `query` object/array, its value (which can be
     * a single ID or a comma-separated list of IDs) is updated to include the new `rec_ID`.
     * If the predicate `type` does not exist, it's added to the query.
     * This is a helper function for `_expandSearch` to avoid duplicating relationship predicates.
     * @param {Object|Array} query - The part of the Heurist search query to modify.
     * @param {string} type - The predicate type (e.g., "related", "linked_to").
     * @param {string|number} rec_ID - The new record ID to add to the predicate's value.
     * @returns {boolean} True if the query was successfully updated, false otherwise.
     */
    _mergeRecordIDs: function(query, type, rec_ID){

        if(!window.hWin.HEURIST4.util.isObject(query) && !Array.isArray(query)){
            return false;
        }

        rec_ID = typeof rec_ID === 'string' ? rec_ID : rec_ID.toString();

        /**
         * Updates the existing list of values
         * @ignore
         * @param {string|number} curValue current record ID(s) being used for the predicate type
         * @returns {string} updated list of values
         */
        function updateValue(curValue){

            if(typeof curValue === 'number'){
                curValue = curValue.toString();
            }else if(typeof curValue !== 'string'){
                curValue = '';
            }

            curValue = curValue.split(',');
            curValue.indexOf(rec_ID) >= 0 || curValue.push(rec_ID);

            return curValue.join(',');
        }

        /**
         * Micro function to appease Sonarcloud
         * @ignore
         */
        function updateArrayPred(){
            if(Object.hasOwn(query, type)){
                query[type] = updateValue(query[type]);
            }else{                
                query = [query];
                query.push({[type]: rec_ID});
            }
        }

        // Convert query to an array, if not already one
        if(!Array.isArray(query)){

            updateArrayPred();

            return true;
        }

        let found = false;
        for(let idx in query){

            let param = query[idx];
            if(!window.hWin.HEURIST4.util.isObject(param)){
                continue;
            }

            let key = Object.keys(param)[0];
            if(key !== type){
                continue;
            }

            // Ensure value is either an integer, string integer, or comma separated list
            let value = param[key];
            if(typeof value === 'number' && Number.isInteger(value)){
                value = value.toString();
            }else if(typeof value !== 'string' || /^\d+(,\d+)*$/.exec(value) === null){
                continue;
            }

            query[idx][key] = updateValue(value);

            found = true;
            break;
        }

        if(!found){
            query.push({[type]: rec_ID});
        }
        
        return true;
    },

    /**
     * @memberof heurist.connections
     * @instance
     * @private
     * @description Performs a new Heurist search with the provided `new_query`.
     * This is typically used by `_expandSearch` to fetch additional records and their relationships
     * to update the graph. On successful search, it updates `this.options.recordset`,
     * resets `this.options.relations`, updates `this._lastRequest`, and calls `_refresh()`
     * to re-render the graph with the new data.
     * @param {Object|string} new_query - The Heurist search query (JSON object or query string).
     */
    _performSearch: function(new_query){

        let that = this;
        let request = window.hWin.HEURIST4.util.cloneJSON(this._lastRequest);
        request['q'] = new_query;

        window.hWin.HAPI4.RecordSearch.doSearchWithCallback(request, (response) => {

            if(!response){
                return;
            }

            that.recordset_changed = true;
            that.options.relations = null;
            that.options.recordset = response;
            that._lastRequest = request;

            that._refresh();
        });
    }

});