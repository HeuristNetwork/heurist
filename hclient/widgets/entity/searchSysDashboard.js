/**
 * @file        searchSysDashboard.js
 * @brief       Provides a search interface for System Dashboards.
 * @fileOverview This widget handles the search functionality for System Dashboards, allowing users to find and select available dashboards. It also includes controls for managing dashboard preferences like visibility on startup.
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
 * @widget heurist.searchSysDashboard
 * @brief Search widget for System Dashboards.
 * @extends $.heurist.searchEntity
 * @description This widget provides a user interface for searching and managing System Dashboard entries.
 *              It includes controls for adding new dashboards, reordering existing ones, and setting user
 *              preferences related to dashboard visibility.
 *
 * @property {boolean} [isViewMode=false] If true, the search is configured for displaying dashboards to a user
 *           (filters for enabled, sorts by display order, and may hide dashboards not relevant for empty databases).
 *           If false, it's configured for management (allows searching inactive, sorts by order or label).
 *
 * @listens heurist.searchSysDashboard#onadd - Fired when the "Add New Entry" button is clicked.
 * @listens heurist.searchSysDashboard#onorder - Fired when the "Save New Order" button is clicked.
 * @listens heurist.searchSysDashboard#onclose - Fired when "View shortcuts", "Hide shortcuts", or "Don't show again" buttons are clicked,
 *           typically signaling the parent manager to close.
 * @listens heurist.searchSysDashboard#oninit - Fired when the `_initControls` method has completed its setup.
 */
$.widget( "heurist.searchSysDashboard", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the System Dashboard search widget.
     * @override
     * @memberof heurist.searchSysDashboard
     * @description Sets up buttons for adding new dashboard entries, applying a new order,
     *              setting dashboard viewing preferences (show as ribbon, show on startup),
     *              and filtering by active/inactive status. Triggers an "oninit" event
     *              and an initial search.
     */
    _initControls: function() {
        
        let that = this;
        
        this._super();

        //hide all help divs except current mode
        /*
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        */
        this.btn_add_record = this.element.find('.btn_AddRecord')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Entry"), icon: "ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 

        this.btn_apply_order = this.element.find('#btn_apply_order')
                .hide()
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Save New Order"), icon: "ui-icon-move-v"})
                .on('click', function(e) {
                    that._trigger( "onorder" );
                }); 

        this.btn_set_mode = this.element.find('#btn_set_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("View shortcuts")})
                .on('click', function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup: 1 });     
                    that._trigger( "onclose" );
                    
                   
                }); 

        this.btn_close_mode = this.element.find('#btn_close_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Hide shortcuts")})
                .on('click', function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup:0 });     
                    that._trigger( "onclose" );
                }); 
                
                
        this.btn_show_on_startup = this.element.find('#btn_show_on_startup2')
                .css({'min-width':'9m'})
                    .button({label: window.hWin.HR("Don't show again")})
                .on('click', function(e) {
                    
                    //don't show  dashboard on startup
                    let params = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_as_ribbon:0} );
                    params['show_on_startup'] = 0;
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', params);     
                    
                    that._trigger( "onclose" );
                }); 
                
                
        this.input_search_inactive = this.element.find('#input_search_inactive');
        this._on(this.input_search_inactive,  { change:this.startSearch });
        
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                     
        this._trigger( "oninit", null );
                      
        this.startSearch();            
    },  

    
    /**
     * @brief Initiates a search for System Dashboard entries.
     * @override
     * @memberof heurist.searchSysDashboard
     * @description Constructs a search request based on the `isViewMode` option.
     *              If `isViewMode` is true, it filters for enabled dashboards, sorts by display order,
     *              and potentially filters out dashboards not relevant for empty databases.
     *              Otherwise (management mode), it can filter by inactive status and sorts by order or label.
     *              Calls the parent `startSearch` method with the constructed request.
     */
    startSearch: function(){
        
            let request = {}
            
            if(this.options.isViewMode){
                
                request['dsh_Enabled'] = 'y';
                request['sort:dsh_Order'] = '1' 
                
                //if database empty - hide some entries
                if(window.hWin.HAPI4.sysinfo['db_total_records']<1){
                    request['dsh_ShowIfNoRecords'] = 'y';
                }
                
            }else{
                /*
                if(this.input_search.val()!=''){
                    request['dsh_Label'] = this.input_search.val();
                }
                
                this.input_sort_type = this.element.find('#input_sort_type');
                if(this.input_sort_type.val()=='order'){
                    request['sort:dsh_Order'] = '1' 
                }else {
                    request['sort:dsh_Label'] = '1';   
                }
                */
                if(this.input_search_inactive.is(':checked')){
                    request['dsh_Enabled'] = 'n';
                }
                request['sort:dsh_Order'] = '1' 
            }
            
            this._search_request = request;
            this._super();
    }
});
