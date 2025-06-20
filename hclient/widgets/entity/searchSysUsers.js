/**
 * @file        searchSysUsers.js
 * @brief       Provides a search interface for System Users.
 * @fileOverview This widget handles the search functionality for System User accounts, allowing filtering by name, group, role, and status (active/inactive).
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\entity
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchSysUsers
 * @brief Search widget for System User accounts.
 * @extends $.heurist.searchEntity
 * @description This widget provides a user interface for searching system user accounts.
 *              It allows filtering by user name, group membership, role within a group,
 *              and active/inactive status.
 *
 * @property {?string} subtitle If provided, this text is displayed as an H3 subtitle within the widget.
 * @property {string} [edit_mode='none'] Defines the editing capabilities available in the UI.
 *           Impacts visibility of "Add New User" and "Find/Add User" buttons. Inherited, but its usage is prominent.
 * @property {?number} ugl_GroupID If provided, the user search is contextualized to this specific Group ID.
 *           If the ID is positive, it filters users within that group. If negative, it finds users *not* in the
 *           group (identified by the absolute value of the ID). This option significantly affects the UI
 *           for group and role selection.
 *
 * @listens heurist.searchSysUsers#onadd - Fired when the "Add New User" button is clicked.
 * @listens heurist.searchSysUsers#onfind - Fired when the "Find/Add User" button is clicked.
 */
$.widget( "heurist.searchSysUsers", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the System Users search widget.
     * @override
     * @memberof heurist.searchSysUsers
     * @description Sets up various UI elements:
     *              - An optional subtitle.
     *              - A dropdown for selecting user groups (`input_search_group`), populated based on admin status.
     *              - "Add New User" and "Find/Add User" buttons, with visibility and behavior
     *                dependent on `options.edit_mode` and `options.ugl_GroupID`.
     *              - A checkbox to include inactive users (`input_search_inactive`).
     *              - A dropdown for user roles (`input_search_role`), often shown in context of a selected group.
     *              - A sort type dropdown (`input_sort_type`).
     *              Helper text visibility is adjusted based on `options.select_mode`.
     *              Triggers an initial search upon completion.
     */
    _initControls: function() {
        
        let that = this;

        if(this.options.subtitle){
            let ele = this.element.find('.sub-title');
            if(ele.length>0){
                ele.html('<h3>'+this.options.subtitle+'</h3>');
            }
        }
        
        this.input_search_group = this.element.find('#input_search_group');   //user group
        if(window.hWin.HAPI4.is_admin()){
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], 'all_my_first' , 
                        [{key:'any',title:'any group'}]);
        }else{
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
                        [{key:'any',title:'any group'}]);
        }
        
        this._super();

        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_find_record = this.element.find('#btn_find_record');

        if(this.options.edit_mode=='none'){
            this.btn_add_record.hide();
            this.btn_find_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New User"), icon: "ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 

            this.btn_find_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Find/Add User"), icon: "ui-icon-search"})
                .on('click', function(e) {
                    that._trigger( "onfind" );
                }); 
                
            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }else if(this.options.ugl_GroupID > 0){
                this.btn_add_record.parent().css({
                    top: '10px',
                    right: '0px',
                    left: ''
                });
            }
        }
        
        this.input_search_inactive = this.element.find('#input_search_inactive');
        this.input_search_role = this.element.find('#input_search_role');

        this._on(this.input_search_group,  { change:this.startSearch });
        this._on(this.input_search_role,  { change:this.startSearch });
        this._on(this.input_search_inactive,  { change:this.startSearch });

        if( this.options.ugl_GroupID>0 ){
            this.input_search_group.parent().hide();
            this.input_search_group.val(this.options.ugl_GroupID);
            
            this.input_search_role.parent().show();
            
            if(!window.hWin.HAPI4.is_admin()){
                this.btn_add_record.hide();
                this.btn_find_record.hide();
            }
        }else if( this.options.ugl_GroupID<0 ){  //addition of users to group
            //find any user not in given group
            //exclude this group from selector
            this.input_search_group.find('option[value="'+Math.abs(this.options.ugl_GroupID)+'"]').remove();
        }else{
            this.btn_find_record.hide();
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        this.startSearch();            
    },  

    
    /**
     * @brief Initiates a search for system users.
     * @override
     * @memberof heurist.searchSysUsers
     * @description Constructs a search request based on the user name from `input_search`,
     *              selected group from `input_search_group`, role from `input_search_role`,
     *              and the "inactive" checkbox state.
     *              Handles special logic for `options.ugl_GroupID` (filtering by group, or excluding from a group if negative).
     *              Determines sort order based on `input_sort_type`.
     *              Populates `this._search_request` and calls the parent `startSearch` method.
     */
    startSearch: function(){
            
            let request = {}
        
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }
            
            if( this.options.ugl_GroupID<0 ){
                //find any user not in given group
                request['not:ugl_GroupID'] = Math.abs(this.options.ugl_GroupID);
            }
        
            if(this.input_search_group.val()>0){
                
                request['ugl_GroupID'] = this.input_search_group.val();
                
                this.input_search_role.parent().show();

                let gr_role = this.input_search_role.val();
                if(gr_role!='' && gr_role!='any'){
                    
                    if(gr_role=='admin'){
                        request['ugl_Role'] = 'admin';
                    }else
                    if(gr_role=='member'){  
                        request['ugl_Role'] = 'member';
                    }
                }
                
                if( window.hWin.HAPI4.has_access( this.input_search_group.val() )
                    && this.options.edit_mode!='none'){
                    this.btn_find_record.show();
                }
            }else{
                this.input_search_role.parent().hide();
                this.btn_find_record.hide(); 
            }
            
            if(this.input_search_inactive.is(':checked')){
                request['ugr_Enabled'] = 'n';
            }     

            if(this.options.ugl_GroupID < 0)
            {
                request['ugr_Enabled'] = '-n'; // Ensure only enabled users are searched when excluding from a group
                this.input_search_inactive.prop('disabled', true);
            }       
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='lastname'){
                request['sort:ugr_LastName'] = '1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:ugr_ID'] = '-1' 
            }else{ // name
                request['sort:ugr_Name'] = '1';   
            }
            
            this._search_request = request;
            this._super();
                           
    }
});
