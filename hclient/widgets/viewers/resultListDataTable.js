/**
 * @file resultListDataTable.js
 * @brief Displays Heurist search results in a table powered by the DataTables.net jQuery plugin.
 * @fileOverview
 * This file defines the `heurist.resultListDataTable` jQuery UI widget. It is designed to integrate
 * Heurist record sets with the powerful DataTables.net library, providing an interactive and feature-rich
 * tabular display for search results. Key functionalities include server-side processing for large datasets,
 * client-side display for smaller sets, customizable column visibility and ordering (potentially through
 * configuration widgets), record type filtering, and visual highlighting of selected records.
 * The widget listens to global Heurist events for search completion and record selection to update
 * its display accordingly. It also manages DataTables initialization, refresh, and destruction.
 *
 * @package Heurist academic knowledge management system
 * @subpackage hclient\widgets\viewers
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @widget heurist.resultListDataTable
 * @memberof heurist
 * @augments jQuery.Widget
 * @description A widget that renders a {@link heurist.HRecordSet} or search results into an interactive
 * HTML table using the DataTables.net jQuery plugin. It supports features like pagination, searching,
 * column configuration, record type filtering, and server-side processing for large datasets.
 *
 * @example
 * $('#myDataTableContainer').resultListDataTable({
 *     recordset: myHeuristRecordSet,
 *     show_rt_filter: true,
 *     show_export_buttons: true,
 *     dataTableParams: {
 *         pageLength: 25
 *     }
 * });
 */
$.widget( "heurist.resultListDataTable", {

    /**
     * @typedef {object} heurist.resultListDataTable.options
     * @description Options for configuring the resultListDataTable widget.
     * @property {heurist.HRecordSet|null} [recordset=null]
     *  The Heurist recordset to display. If `eventbased` is true, this can be updated
     *  via the `ON_REC_SEARCH_FINISH` event.
     * @property {Array<number>|null} [selection=null]
     *  An array of selected record IDs. This is used to highlight rows in the table.
     *  Can be updated via the `ON_REC_SELECT` event if `eventbased` is true.
     * @property {boolean} [eventbased=true]
     *  If true, the widget listens to global Heurist events (e.g., for search results,
     *  selection changes) to update its state. If false, it operates only on the initially
     *  provided `recordset` and `selection`.
     * @property {string|null} [search_realm=null]
     *  The search realm this widget belongs to. Used to filter global events if `eventbased` is true.
     * @property {boolean} [serverSide=false]
     *  This option is dynamically managed by the widget based on record count.
     *  If true, DataTables will use server-side processing for pagination, searching, etc.
     *  This is typically enabled for large recordsets.
     * @property {boolean} [show_rt_filter=false]
     *  If true, displays a dropdown filter for record types above the table.
     * @property {boolean} [show_column_config=true]
     *  If true, displays controls for configuring table columns (e.g., selecting fields, visibility).
     *  This often integrates with another widget like `configEntity`.
     * @property {boolean} [show_search=false]
     *  If true, displays the DataTables native search/filter input box.
     * @property {boolean} [show_counter=true]
     *  If true, displays the DataTables information label (e.g., "Showing 1 to 10 of 57 entries").
     * @property {boolean} [show_export_buttons=false]
     *  If true, displays DataTables export buttons (e.g., Copy, Excel, PDF).
     * @property {string|null} [emptyTableMsg=null]
     *  Custom message to display when the table is empty. If null, DataTables default is used.
     *  This message is also shown separately if the recordset is empty before DataTables initialization.
     * @property {string|null} [placeholder_text=null]
     *  Text or HTML to display as a placeholder before the initial data load or when the recordset is null.
     * @property {string|null} [search_initial=null]
     *  An initial search query string to execute when the widget is created.
     * @property {object|null} [dataTableParams=null]
     *  Additional parameters to pass directly to the DataTables.net constructor.
     *  This can include options for styling, features, internationalization, etc.
     *  See <a href="https://datatables.net/reference/option/">DataTables options</a>.
     */
    options: {
        recordset: null,
        selection: null,  //list of selected record ids

        eventbased:true, //if false it does not listen global events
        
        search_realm: null,
        serverSide: false,
        
        show_rt_filter:false,
        show_column_config:true,
        show_search:false,
        show_counter:true,
        show_export_buttons:false,

        emptyTableMsg: null,
        placeholder_text: null,
        
        search_initial:null,
        
        dataTableParams: null
    },

    /**
     * @property {string|null} _current_query
     * @private
     * @description Stores the current query string that resulted in the displayed `recordset`.
     * Used to determine if a full table rebuild is needed.
     */
    _current_query: null,
    /**
     * @property {string|null} _current_url
     * @private
     * @description Stores the query string associated with the currently initialized DataTable.
     * This is compared against `_current_query` to decide if the DataTable needs to be destroyed and recreated.
     */
    _current_url: null, // Effectively the query for the current DataTable instance
    /**
     * @property {string|null} _events
     * @private
     * @description A string containing the names of global Heurist events that this widget listens to,
     * such as `ON_CREDENTIALS`, `ON_REC_SEARCH_FINISH`, etc. Only used if `options.eventbased` is true.
     */
    _events: null,
    /**
     * @property {object|null} _dataTable
     * @private
     * @description Holds the DataTables.net API instance after initialization.
     * Null if DataTable has not been initialized or has been destroyed.
     */
    _dataTable: null,    
    
    /**
     * @property {jQuery|null} selConfigs
     * @private
     * @description jQuery object referencing the column configuration UI element, typically
     * an instance of another widget like `configEntity`. Used to manage column display settings.
     */
    selConfigs: null,

    /**
     * @property {Array<number>|null} hidden_cols
     * @private
     * @description An array of column indices that are currently set to be hidden in the DataTable.
     */
    hidden_cols: null, // datatable columns ids that are set to hidden

    /**
     * @property {jQuery|null} no_records_message
     * @private
     * @description jQuery object for the DOM element that displays the `emptyTableMsg`
     * when no records are available and DataTables itself is not yet initialized or is empty.
     */
    no_records_message: null, // element containing the 'no records' message

    /**
     * @property {jQuery|null} placeholder_ele
     * @private
     * @description jQuery object for the DOM element that displays the `placeholder_text`
     * before any data is loaded or when the widget is in an initial empty state.
     */
    placeholder_ele: null, 

    /**
     * @function _create
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Initializes the widget. Creates the main content div and the table element.
     * Parses `options.dataTableParams`. Sets up global event listeners if `options.eventbased` is true.
     * Initializes placeholder and empty message elements if corresponding options are provided.
     * Triggers an initial search if `options.search_initial` is set.
     */
    _create: function() {

        let that = this;

       
        this.div_content = $('<div>').css({width:'100%', height:'100%'}).appendTo( this.element );
        
        this.options.dataTableParams = window.hWin.HEURIST4.util.isJSON(this.options.dataTableParams);
        
        if(!this.options.dataTableParams) this.options.dataTableParams = {};
        
        //table table-striped table-bordered - for bootstrap.css
        let classes = window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['classes'])
                            ?'display compact nowrap cell-border'
                            :this.options.dataTableParams['classes'];

        //this.div_content.css({'padding-top':'5px'}); //,'overflow-y': 'auto'
        this.div_datatable = $('<table>').css({'width':'98%'})
            .addClass(classes).appendTo(this.div_content);
        
        this.options.is_single_selection = false;
        this.options.reload_for_recordset =false;
        this.options.is_frame_based = false;
        
        if(this.options.eventbased){

            //-----------------------     listener of global events
            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

            $(this.document).on(this._events, function(e, data) {

                
                if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(!window.hWin.HAPI4.has_access()){ //logout
    that._dout('credentials');
                        that.options.recordset = null;
                        that._refresh();
                    }
                    return;
                }                
                
                if (!(that._isSameRealm(data) && data.source!=that.element.attr('id'))) return;
                
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

    that._dout('search finished');
    
                    that._current_query = data.query;
                    that.options.recordset = data.recordset; //HRecordSet

                    that._refresh();
                    that.loadanimation(false);

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    that.loadanimation(true);
                    that.options.recordset = null;
                    that._refresh();
                    /*if(data && !data.reset){
                        that.updateDataset( jQuery.extend(true, {}, data) ); //keep current query request (clone)
                    }*/

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

    that._dout('selected');
                        let sel = window.hWin.HAPI4.getSelection(data.selection, true)
                        that.options.selection = sel;
                        that._refresh();
                }
               
            });
        
        }
        
        if(!window.hWin.HEURIST4.util.isempty(this.options.emptyTableMsg)){
            if(this.options.dataTableParams['language'] == null){
                this.options.dataTableParams['language'] = {};
            }
            this.options.dataTableParams['language']['emptyTable'] = this.options.emptyTableMsg;

            this.no_records_message = $('<div>')
                .css('white-space', 'pre-wrap')
                .html(this.options.emptyTableMsg)
                .appendTo(this.div_content)
                .hide();
        }
        if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)){
            this.placeholder_ele = $('<div>')
                .css('white-space', 'pre-wrap')
                .prependTo(this.div_content)
                .html(this.options.placeholder_text);
        }

        this.element.on("myOnShowEvent", function(event){ // Custom event, likely for visibility changes in complex layouts
            if( event.target.id == that.element.attr('id')){
that._dout('myOnShowEvent');                
                that._refresh();
            }
        });


        if(this.options.search_initial)
        {
            let request = { q:this.options.search_initial, w: 'a', detail: 'ids', 
                        source:'init', search_realm: this.options.search_realm };
            window.hWin.HAPI4.RecordSearch.doSearch(this.document, request);
        }
    }, //end _create

    /**
     * @function _isSameRealm
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Checks if the widget's current search realm matches the realm from incoming event data.
     * This is used to ensure the widget only responds to events relevant to its configured context.
     * An empty or null realm on either side is considered a match for broader compatibility.
     * @param {object} data Event data, expected to have a `search_realm` property.
     * @returns {boolean} True if the realms are considered the same, false otherwise.
     */
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    /**
     * @function _setOptions
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Called when options are set on the widget. Uses `_superApply` to call the base
     * widget's method, ensuring proper option handling.
     * @param {object} options An object containing option key-value pairs to set.
     */
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
    },
    
    /**
     * @function _dout
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Debugging output helper. Currently does nothing.
     * Could be used for conditional console logging based on a debug flag.
     * @param {string} msg The message to log.
     */
    _dout: function(msg){
        //if(this.options.url  && this.options.url.indexOf('renderRecordData')>0){
       
        //}
    },
    

    /**
     * @function _refresh
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Refreshes the DataTable. If the widget is visible and a recordset is available:
     * - Hides any placeholder text.
     * - Stops loading animation.
     * - If the underlying query (`_current_query`) has changed since the last DataTable initialization (`_current_url`),
     *   it destroys the existing DataTable (if any), then re-initializes it.
     * - DataTable initialization involves setting up parameters (server-side vs client-side processing,
     *   AJAX source, column definitions, DOM layout, page length, etc.).
     * - For server-side processing, it first makes a request to register the query and get a datatable_id.
     * - If no records are available, it shows the `no_records_message`.
     * - If the query hasn't changed, it only calls `_highlightSelected` to update row selections.
     */
    _refresh: function(){

        this._dout('refresh vis='+this.element.is(':visible'));            

        if(this.options.recordset && this.element.is(':visible')){

            if(this.placeholder_ele != null){
                this.placeholder_ele.hide();
            }

            this.loadanimation(false);

            let recIds_list = this.options.recordset.getIds();

            if(this._current_query!=this._current_url){                    

                let that = this;
        
                this._current_url = this._current_query;

                if(this._dataTable!=null){
                    this._dataTable.destroy();
                    this._dataTable = null;
                    this.div_datatable.empty();
                }

                if(recIds_list.length>0){

                    let queryURL = window.hWin.HAPI4.baseURL+'hserv/controller/record_output.php';

                    let queryStr = '';
                    let rec_total_count = recIds_list.length;
                    
                    this.options.serverSide = true; //(rec_total_count>0); 
                    if(rec_total_count>0){ //5000
                        queryStr = this._current_query;
                    }else{
                        queryStr = '{"ids":"'+this.options.recordset.getIds().join(',')+'"}';
                    }
                    
                    this.options.dataTableParams['scrollCollapse'] = true;
                    this.options.dataTableParams['scrollY'] = this.div_content.height()-120;
                    this.options.dataTableParams['scrollX'] = true;
                    this.options.dataTableParams['autoWidth'] = false;
                    
                    this.options.dataTableParams['initComplete'] = function(settings, data) {that._onDataTableInitComplete(settings, data);}
                    
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['dom'])){
                        let dom = '';
                        if(this.options.show_rt_filter || this.options.show_column_config){
                            dom = dom + '<"selectors">';
                        }
                        if(this.options.show_search){
                              dom = dom + 'f';
                        }
                        dom = dom + 'rt';  //t - table
                        if(this.options.show_counter){
                            dom = dom + 'i';
                        }                   
                        dom = dom + 'p'; //pagination
                        
                        if(this.options.show_export_buttons){
                            dom = dom + 'B'; 
                            this.options.dataTableParams['buttons'] = ['copy', 'excel', {
                                extend: 'pdfHtml5',
                                orientation: 'portrait',
                                pageSize: 'A4',
                                customize: (doc) => {
                                    // Change to landscape for larger tables
                                    let setting = window.hWin.HAPI4.get_prefs('columns_datatable');
                                    let col_count = 0;

                                    if(setting && setting.columns.length > 0){

                                        setting.columns.forEach(field => {
                                            if(field.visible){
                                                col_count += $Db.dty(field.data, 'dty_Type') == 'blocktext' ? 3 : 1;
                                            }
                                        });
                                    }else{
                                        let tableNode = doc.content[1];// [0] => Title
                                        col_count = tableNode && tableNode.table ? tableNode.table.body[0].length : 10;
                                    }

                                    if(col_count > 5){
                                        doc.pageOrientation = 'landscape';
                                    }
                                }
                            }];    
                        }

                        this.options.dataTableParams['dom'] = dom;//'<"selectors">frtip'; //l - for page length
                    }
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['pageLength'])){
                        this.options.dataTableParams['pageLength'] = window.hWin.HAPI4.get_prefs('search_result_pagesize');
                    }
                    
                    this.options.dataTableParams['ordering'] = false;
                    
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['columns'])){
                        
                        let settings = window.hWin.HAPI4.get_prefs('columns_datatable');
                        
                        if(settings){
                            this.options.initial_cfg = settings;
                            this.options.dataTableParams['columns'] = settings.columns;
                        }else{
                            this.options.dataTableParams['columns'] = [
                                { data: 'rec_ID', title:'ID' },
                                { data: 'rec_Title', title:'Title' },
                                { data: 'rec_RecTypeID', title:'Type', visible:false }
                            ];
                        }
                        
                    }
                    

                    let cols = this.options.dataTableParams['columns'];
                    this.hidden_cols = [];
                    for(let i=0;i<cols.length;i++){
                        /* custom rendereing is not use - remarked due a secirity reason - using eval
                        if(typeof cols[i]['render']==='string'){
                            let fooName = cols[i]['render']
                            if(typeof(eval(fooName))=='function'){ 
                                cols[i]['render'] = eval(fooName);//function(data,type){ [fooName](data,type); }
                            }else{
                                cols[i]['render'] = null;
                            }
                        }
                        */
                        cols[i]['render'] = null;

                        if(cols[i]['visible'] === "false" || cols[i]['visible'] === false){
                            this.hidden_cols.push(i);
                        }
                    }
                    

this._dout('reload datatable '+this.options.serverSide);                  
                    
                    if(this.options.serverSide){
                        //pass query to server side
                        this.options.dataTableParams['processing'] = true;
                        this.options.dataTableParams['serverSide'] = true;
                        
                        let datatable_id = window.hWin.HEURIST4.util.random();
                   
                        //to avoid passs thousands of recids for each page request 
                        //pass and save query on server side 
                        window.hWin.HEURIST4.util.sendRequest(queryURL,
                            {q:queryStr, datatable:datatable_id, format:'json', db:window.hWin.HAPI4.database}, null, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    that.options.dataTableParams['ajax'] = {
                                            "type": "POST",
                                            "url": queryURL,
                                            "data":{
                                                "db": window.hWin.HAPI4.database,
                                                "format": 'json',
                                                "recordsTotal":rec_total_count,
                                                "datatable": datatable_id
                                            }
                                    };

                                    that._dataTable = that.div_datatable.DataTable( that.options.dataTableParams );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response, true);    
                                }
                            }
                        );

                    }else{

                        this.options.dataTableParams['processing'] = false;
                        this.options.dataTableParams['serverSide'] = false;                    
                        this.options.dataTableParams['ajax'] = {
                                            "type": "POST",
                                            "url": queryURL,  
                                            "data":{
                                                "db": window.hWin.HAPI4.database,
                                                "format": 'json',
                                                "q":queryStr,
                                                "datatable": 1
                                            }
                                            };
                        this._dataTable = this.div_datatable.DataTable( this.options.dataTableParams );
                    }

                    if(this.no_records_message != null){
                        // hide 'no records' message
                        this.no_records_message.hide();
                    }
                }else{
                    if(this.no_records_message != null){
                        // show 'no records' message
                        this.no_records_message.show();
                    }
                }
            }else{
                this._highlightSelected();
            }

        }

    },
    
    /**
     * @function _onDataTableInitComplete
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Callback function executed when DataTables has finished its initialization.
     * Adjusts the CSS of various DataTables elements (length selector, filter input, info display,
     * pagination, buttons) for better integration with the Heurist UI.
     * Initializes column visibility based on `this.hidden_cols`.
     * Adds tooltips to truncated cells.
     * If `options.show_rt_filter` or `options.show_column_config` is true, it sets up the
     * record type filter dropdown and/or the column configuration widget (`configEntity`).
     */
    _onDataTableInitComplete:function(){
        
        //adjust position for datatable controls    
        this.div_content.find('.dataTables_length').css('padding','5 0 0 10');
        let lele = this.div_content.find('.dataTables_filter').css('padding','5 10 0 0');
        
        this.div_content.find('.dt-info').css({float:'left','padding-top':'11px','padding-left':'10px','padding-right':'10px'}); //was dataTables_info
       
        this.div_content.find('.dataTables_scrollBody').css({'width':'100%'});
        this.div_content.find('.dataTables_wrapper').css('padding','0 8px');
        this.div_content.find('.dataTable').css({'font-size':'inherit','width':'100%'});
        
        this.div_content.find('.dt-paging').css({float:'right','padding-top':'7px'}); //was dataTables_paginate
        
        this.div_content.find('.paginate_button').css('padding','2px');
        
        this.div_content.find('.dt-buttons').css('padding-top','7px');
        this.div_content.find('.dt-button').css('padding','2px');
        
        this.selConfigs = null;

        const that = this;
		
        // Ensure that columns set to hidden are hidden
        if(this.hidden_cols.length > 0){
            this._dataTable.columns(this.hidden_cols).visible(false);
        }
        
        // Add title to elements that will truncate
        let cells = this.div_content.find('div.dataTables_scroll td.truncate, div.dataTables_scroll th.truncate');
        if(cells.length > 0){
            $.each(cells, function(idx, cell){

                let $ele = $(cell);
                $ele.attr('title', $ele.text());
            });
        }

        if(this.options.show_rt_filter || this.options.show_column_config){

            let sel_container = this.div_content.find('div.selectors').css({float:'left',padding:'15px 0px','min-width':'570px'});

            if(this.options.show_rt_filter){
                
                //add record type selector - filter by record types
                let rectype_Ids = this.options.recordset.getRectypes();

                if(rectype_Ids.length>1){
                    $('<label>Filter by:&nbsp;</label>').appendTo(sel_container)
                    let selScope = $('<select>').appendTo(sel_container).css({'min-width':'12em'});
                    
                    let opt = window.hWin.HEURIST4.ui.addoption(selScope[0],'','select record type …');
                    $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});

                    rectype_Ids.forEach(rty => {
                        if(rty>0 && $Db.rty(rty,'rty_Name') ){
                            
                            let name = $Db.rty(rty,'rty_Plural');
                            if(!name) name = $Db.rty(rty,'rty_Name');
                            
                            window.hWin.HEURIST4.ui.addoption(selScope[0], rty, name ); //'only: '+
                        }
                    });
                    window.hWin.HEURIST4.ui.addoption(selScope[0],'', 'Any record type');
                    
                    this._on( selScope, {
                        change: this._onRecordTypeFilter} );        

                    
                    window.hWin.HEURIST4.ui.initHSelect(selScope);
                }
            }
            
            if(this.options.show_column_config){

                if(window.hWin.HEURIST4.util.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded
                    this.selConfigs = $('<div>').appendTo(sel_container);
                    
                    this.selConfigs.configEntity({
                        entityName: 'defRecTypes',
                        configName: 'datatable',
                        loadSettingLabel: 'Field list',

                        getSettings: null,
                        setSettings: function( settings ){ //callback function to apply configuration
                                that._onApplyColumnDefinition( settings ); 
                        }, 

                        divSaveSettings: null,
                        showButtons: true,
                        buttons: {rename:'save as', openedit:'select fields for display', remove:'delete'},
                        openEditAction: function(is_new){ //overwrite default behaviour - open configuration popup
                                that._openColumnDefinition( is_new );
                        }
                    });
                    
                    this.selConfigs.find('div.header').css({padding: '7px 16px 3px 0', float: 'left'});
                    this.selConfigs.find('span.btn-action-div').css({display: 'inline-block','padding-top':'10px'});
                    this.selConfigs.configEntity('updateList', 'all', 
                            that.options.initial_cfg?that.options.initial_cfg.cfg_name:null);

                }                    
                
                //add button to configure columns
                /*
                var btn_cfg = $('<button>').button({icon: "ui-icon-pencil", label:'Configure columns', showLabel:false})
                        .css({height:'20px'}).appendTo(sel_container);
                
                this._on( btn_cfg, {
                        click: this._openColumnDefinition} );        
                */
            }                            
        }

        this._highlightSelected();
    },
    
    /**
     * @function _onRecordTypeFilter
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Event handler for when the record type filter dropdown changes.
     * It filters the DataTable by the `rec_RecTypeID` column based on the selected value.
     * Also updates the column configuration list (`selConfigs`) if available.
     * @param {jQuery.Event} e The change event object from the select element.
     */
    _onRecordTypeFilter: function(e){
        
        let rty_ID = $(e.target).val();
        let that = this;
        
        $.each(this.options.dataTableParams['columns'],function(idx,item){
            if(item.data=='rec_RecTypeID'){
                that._dataTable.column(idx).search((rty_ID>0?rty_ID:'')).draw();        
            }
        });
        
        if(this.selConfigs)
            this.selConfigs.configEntity('updateList', rty_ID>0?rty_ID:'all');
        
        
    },
    
    /**
     * @function _destroy
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Cleans up the widget when it is destroyed. Unbinds global and custom event listeners.
     * Removes DOM elements created by the widget, including the DataTable.
     */
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events); // Assumes this._events is properly namespaced or managed

        let that = this; // 'that' is not used here.

        // remove generated elements
        if (this._dataTable) { // Destroy DataTable instance
            this._dataTable.destroy();
            this._dataTable = null;
        }
        this.div_datatable.remove(); // Remove table element
        this.div_content.remove(); // Remove main content div
    },

    /**
     * @function loadanimation
     * @memberof heurist.resultListDataTable
     * @instance
     * @description Shows or hides a loading animation on the widget's content area.
     * @param {boolean} show If true, displays the loading animation. If false, removes it.
     */
    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },
    
    /**
     * @function _onApplyColumnDefinition
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Callback function executed when new column definitions are applied,
     * typically from a column configuration dialog. Saves the new configuration as a preference,
     * updates the widget's `dataTableParams.columns`, sets `_current_url` to null to force a
     * DataTable rebuild, and then calls `_refresh()`.
     * @param {object} config The new column configuration object. Expected to have a `columns`
     * property (array of DataTables column definitions) and potentially `cfg_name`.
     */
    _onApplyColumnDefinition: function(config){
        
       window.hWin.HAPI4.save_pref('columns_datatable', config);        
       
       this.options.dataTableParams['columns'] = config.columns;
       this.options.initial_cfg = config;
       this._current_url = null; //to force reset datatable
       this._refresh();
    },
    
    /**
     * @function _openColumnDefinition
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Opens a dialog for configuring DataTable columns.
     * This typically involves showing another widget or UI component specialized for
     * column selection and ordering (e.g., `recordDataTable` action dialog).
     * @param {boolean} is_new True if opening for a new configuration, false if editing an existing one.
     * This influences whether `options.initial_cfg` is passed to the dialog.
     */
    _openColumnDefinition: function( is_new ){
        
        let that = this;
    
        let opts = {
            currentRecordset: this.options.recordset,
            initial_cfg: (is_new===true)?null:that.options.initial_cfg,
            onClose: function(context){
                if(context){
                    that._onApplyColumnDefinition(context);
                }
            }
        };
        //see widgets/record/recordDataTable.js                                                                                 
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDataTable', opts);        
        
    },

    /**
     * @function _highlightSelected
     * @memberof heurist.resultListDataTable
     * @instance
     * @private
     * @description Highlights rows in the DataTable that correspond to record IDs present
     * in `this.options.selection`. Removes highlighting from other rows.
     */
    _highlightSelected: function(){

        const that = this;
        let $rows = this.div_content.find('table.dataTable tbody tr');

        // No rows
        if($rows.length == 0){
            return;
        }

        // Remove previous highlighting
        $rows.removeClass('ui-highlight');

        if(!this.options.selection || this.options.selection.length == 0){
            return;
        }

        // Highlight selected
        $.each($rows, (idx, row) => {

            let row_data = that._dataTable.row(row).data();

            if(row_data && that.options.selection.indexOf(row_data?.rec_ID) !== -1){
                $(row).addClass('ui-highlight');
            }
        });
    }

});
