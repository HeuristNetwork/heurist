/**
 * @file        searchSysDatabases.js
 * @brief       Provides a search interface for System Databases.
 * @fileOverview This widget handles the search functionality for System Databases, allowing users to find and select registered databases, with options to filter by database name, user email, and role.
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchSysDatabases
 * @brief Search widget for System Databases.
 * @augments $.heurist.searchEntity
 * @description This widget provides a user interface for searching system databases.
 *              Users can filter by database name, user email (associated with the database), and user role.
 *              Sorting options are also available.
 *
 * @property {?string} subtitle If provided, this text is displayed as an H3 subtitle within the widget's header area.
 */
$.widget( "heurist.searchSysDatabases", $.heurist.searchEntity, {

    input_email: null,

    /**
     * @brief Initializes the controls for the System Databases search widget.
     * @override
     * @memberof heurist.searchSysDatabases
     * @description Sets up input fields for database name, user email, user role (type), and sort order.
     *              It also handles the display of an optional subtitle.
     */
    _initControls: function() {
        this._super();
        
        this.input_search.parent().css('padding-top','15px'); 

        this.input_search_type = this.element.find('#input_search_type');
        this._on(this.input_search_type,  { change:this.startSearch });

        this.input_sort_type = this.element.find('#input_sort_type');
        this.input_sort_type.parent().hide();
        this._on(this.input_sort_type,  { change:this.startSearch });

        this._on(this.input_search,  { keydown: window.hWin.HEURIST4.ui.preventNonAlphaNumeric, keyup:this.startSearch });
        
        this.input_search.trigger('focus');         

        // Setup email filtering
        this.element.find('#input_import_only').show();
        this.input_email = this.element.find('.input_search_email');
        this._on(this.input_email, {
            keydown: (e) => {
                if(e.key == "Enter"){
                    this.startSearch();
                }
            }
        });
        this._on(this.element.find('#btn_filter_email').button(), {
            click: this.startSearch
        });
        
        if(this.options.subtitle){
            let ele = this.element.find('.sub-title');
            if(ele.length>0){
                ele.html('<h3 style="margin:1em 0 0 0">'+this.options.subtitle+'</h3>');
            }
        }
    },  

    /**
     * @brief Initiates a search for system databases.
     * @override
     * @memberof heurist.searchSysDatabases
     * @description Constructs a search request based on the values in the database name,
     *              user email, user role, and sort type input fields.
     *              Triggers an "onfilter" event with the request object.
     *              This widget typically operates with `use_cache: true` in its parent manager,
     *              so it triggers "onfilter" for client-side filtering rather than making a server call itself.
     */
    startSearch: function(){
        
        let request = {};
        
        if(this.input_search.val() != ''){
            request['sys_Database'] = this.input_search.val();
        }
        if(this.input_email.val() != ''){
            request['ugr_eMail'] = this.input_email.val();
        }

        if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
            request['sus_Role'] = this.input_search_type.val();
        }
        
        if(this.input_sort_type.val()=='name'){
            request['sort:sys_Database'] = 1;
        }else if(this.input_sort_type.val()=='register'){
            request['sort:sys_dbRegisteredID'] = -1;
        }else  if(this.input_sort_type.val()=='member'){
            request['sort:sus_Count'] = -1;
        }
        
        this._trigger( "onfilter", null, request);
    }


});
