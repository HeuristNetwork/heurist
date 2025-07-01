/**
 * @file        searchEntity.js
 * @brief       Provides a base search interface for various Heurist entities.
 * @fileOverview This widget serves as a base for specific entity search widgets. It provides common search functionalities like input fields, filter mechanisms, and result event handling.
 * @project     Heurist academic knowledge management system
 * @package  hclient\widgets\entity
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */

/**
 * @widget heurist.searchEntity
 * @brief Base widget for entity search interfaces.
 * @description This widget provides core functionality for searching entities within the Heurist system.
 *              It is intended to be extended by more specific search widgets (e.g., for records, terms, etc.).
 *              It handles loading search form layouts, initializing common controls, and managing search requests.
 *
 * @property {string} [select_mode='manager'] Defines the operational mode of the search, influencing UI and behavior.
 *           Possible values: 'manager' (full interface), 'select_single' (for picking one item), 'select_multi' (for picking multiple items).
 * @property {?string} filter_title Initial text to populate the main search input field.
 * @property {?string} filter_group_selected The key of the filter group to be selected by default.
 * @property {?string} filter_groups A comma-separated string defining available filter groups (e.g., for tabs or dropdowns).
 * @property {?object} initial_filter An object representing the initial search criteria to apply when the widget loads.
 * @property {boolean} [search_form_visible=true] Determines if the search form controls are visible upon initialization.
 * @property {boolean} [use_cache=false] If true, the widget attempts to load all relevant entity data into client-side cache for faster filtering.
 *           This is suitable for smaller datasets. If false, searches are performed server-side.
 * @property {object} entity An essential configuration object that defines the target entity for the search.
 * @property {string} entity.entityName The name of the entity being searched (e.g., 'hrev_Record', 'hdef_Term').
 * @property {string} entity.searchFormContent The filename (relative to `hclient/widgets/entity/`) of the HTML template for the search form.
 * @property {?string} database The specific database to target for the search, if different from the current default.
 *
 * @listens heurist.searchEntity#onstart - Fired when a search operation begins.
 *          No parameters.
 * @listens heurist.searchEntity#onresult - Fired when a search operation completes and data is received.
 *          Event data: `{ recordset: HRecordSet, request: object }`
 *          - `recordset`: An HRecordSet instance containing the search results.
 *          - `request`: The original search request object.
 * @listens heurist.searchEntity#onfilter - Fired by derived widgets, typically when `use_cache` is true and client-side filtering occurs.
 *          Event data: Varies by implementation, usually the filter criteria.
 */
$.widget( "heurist.searchEntity", {

    // default options
    options: {
        select_mode: 'manager', //'select_single','select_multi','manager'
        
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,
        
        //request for initial filter
        initial_filter: null,
        search_form_visible: true,

        use_cache: false,
        
        /* callbacks - events
        onstart:null,
        onresult:null,
        onfilter:null,*/
        
        entity:{}
    },
    
    _need_load_content:true, // internal flag: do not load search form html content if already loaded
    _search_request:{}, // internal object to store current search parameters

    /**
     * @brief Widget constructor.
     * @memberof heurist.searchEntity
     * @description Prevents double-click text selection on the widget's main element.
     */
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    /**
     * @brief Initializes the widget.
     * @memberof heurist.searchEntity
     * @description This method is called upon widget creation and subsequent calls without arguments or with an options hash.
     *              It handles the asynchronous loading of the search form's HTML content if defined in `options.entity.searchFormContent`.
     *              Once content is loaded (or if not needed), it calls `_initControls()`.
     */
    _init: function() {

            let that = this;
            
            if(this._need_load_content && this.options.entity.searchFormContent){        
                this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/entity/'+this.options.entity.searchFormContent+'?t'+window.hWin.HEURIST4.util.random(), 
                function(response, status, xhr){
                    that._need_load_content = false;
                    if ( status == "error" ) {
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: response,
                            error_title: 'Failed to load HTML content',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }else{
                        that._initControls();
                    }
                });
                return;
            }else{
                //template for search not defined 
                // define btn_search_start and input_search at least in manageEntity
                that._initControls();
            }
            
    },
    
    /**
     * @brief Initializes UI controls within the search form.
     * @memberof heurist.searchEntity
     * @description This method is intended to be called after the search form's HTML content has been loaded.
     *              It sets up common UI elements like the main search button, search input field,
     *              event handlers for radio buttons/checkboxes, and a summary button if present.
     *              It also disables browser autofill on input fields.
     *              Derived widgets may override or extend this to initialize their specific controls.
     */
    _initControls: function() {
            
            //init buttons
            this.btn_search_start = this.element.find('#btn_search_start')
                //.css({'width':'6em'})
                .button({label: window.hWin.HR("Start search"), showLabel:false, 
                        icon:"ui-icon-search", iconPosition:'end'});
                 
                    
            //this is default search field - define it in your instance of html            
            this.input_search = this.element.find('#input_search');
            if(this.input_search.length==0) this.input_search = this.element.find('.input_search');
            if(!window.hWin.HEURIST4.util.isempty(this.options.filter_title)) {
                this.input_search.val(this.options.filter_title);    
            }
            
            this._on( this.input_search, { keypress: this.startSearchOnEnterPress });
            this._on( this.btn_search_start, {
                click: this.startSearch });
                
            this._on( this.element.find('.ent_search_cb input'), {  //input[type=radio]
                change: this.startSearch });
                
            // summary button - to show various counts for entity 
            // number of group members, records by rectypes, tags usage
            this.btn_summary = this.element.find('#btn_summary')
                .button({label: window.hWin.HR("Show/refresh counts"), showLabel:false, iconPosition:'end', icon:'ui-icon-retweet'});
            if(this.btn_summary.length>0){
                this._on( this.btn_summary, { click: this.startSearch });
            }
                
            let right_padding = window.hWin.HEURIST4.util.getScrollBarWidth()+4;
            this.element.find('#div-table-right-padding').css('min-width',right_padding);
        
        
           
            window.hWin.HEURIST4.ui.disableAutoFill( this.element.find( 'input' ) );
            
    },  
    
    /**
     * @brief Handles the 'Enter' key press event in search input fields.
     * @memberof heurist.searchEntity
     * @param {Event} e The jQuery Event object for the keypress.
     * @description If the 'Enter' key (code 13) is pressed, it prevents default form submission
     *              and calls `startSearch()` to initiate the search.
     */
    startSearchOnEnterPress: function(e){
        
        let code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this.startSearch();
        }

    },
    
    /**
     * @brief Initiates a search when `use_cache` is true.
     * @memberof heurist.searchEntity
     * @fires heurist.searchEntity#onstart
     * @fires heurist.searchEntity#onresult
     * @description This method is called when `use_cache` is true. It fetches all data for the
     *              specified entity and then triggers an "onresult" event. After the initial load,
     *              `startSearch()` is called to apply any client-side filters.
     */
    startSearchInitial: function(){
        
            this._trigger( "onstart" );
            
            let that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                function(response){
                        that._trigger( "onresult", null, {recordset:response} );
            
                        that.startSearch(); //apply filter
                        
                });
    },

    /**
     * @brief Executes a server-side search.
     * @memberof heurist.searchEntity
     * @fires heurist.searchEntity#onstart
     * @fires heurist.searchEntity#onresult
     * @description This is the primary method for performing server-side searches.
     *              It constructs a search request object using `this._search_request` (which should be
     *              populated by derived widgets or other interactions), sets common parameters like
     *              action, entity name, and request ID. It then makes an asynchronous request
     *              via `HAPI4.EntityMgr.doRequest`. On successful response, it triggers an "onresult"
     *              event with the HRecordSet and the original request. Errors are shown via `HEURIST4.msg.showMsgErr`.
     *              If `use_cache` is false (default), this method is typically called.
     *              If `use_cache` is true, this method might be called after `startSearchInitial` to apply filters,
     *              or a derived widget might override it to perform client-side filtering.
     */
    startSearch: function(){
        
            if(!this._search_request){
                this._search_request = {};
            }
                    
            this._trigger( "onstart" );
    
            this._search_request['a']          = 'search'; //action
            this._search_request['entity']     = this.options.entity.entityName;
            this._search_request['request_id'] = window.hWin.HEURIST4.util.random();
            if(!this._search_request['details']){
                this._search_request['details'] = 'id';
            }
            if(this.options.database){
                this._search_request['db'] = this.options.database;    
            }
            

            let that = this;                                                
       
            window.hWin.HAPI4.EntityMgr.doRequest(this._search_request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that._trigger( "onresult", null, 
                            {recordset:new HRecordSet(response.data), request:this._search_request} );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });      
    },
    

});
