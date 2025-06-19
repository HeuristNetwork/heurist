/**
 * @file        searchSysGroups.js
 * @brief       Provides a search interface for System User Groups.
 * @fileOverview This widget handles the search functionality for System User Groups, allowing filtering by group name and user role, especially in the context of a specific user.
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\entity
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class heurist.searchSysGroups
 * @brief Search widget for System User Groups.
 * @augments $.heurist.searchEntity
 * @description This widget provides a user interface for searching system user groups (workgroups).
 *              It allows filtering by group name and user role, and can be contextualized to a specific user
 *              if `ugl_UserID` is provided.
 *
 * @property {?string} subtitle If provided, this text is displayed as an H3 subtitle within the widget.
 * @property {string} [edit_mode='none'] Defines the editing capabilities available in the UI (e.g., 'inline', 'dialog', 'none').
 *           This impacts the visibility and behavior of the "Add New Group" button. Inherited, but its usage is prominent.
 * @property {?number} ugl_UserID If provided, the search for groups (especially when filtering by role)
 *           is contextualized to this specific User ID. If not provided, role-specific searches default
 *           to the current logged-in user.
 * @property {?string} sort_type_int The initial value for the sort type dropdown (e.g., 'member', 'recent', 'name').
 *
 * @listens heurist.searchSysGroups#onadd - Fired when the "Add New Group" button is clicked.
 */
$.widget( "heurist.searchSysGroups", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the System User Groups search widget.
     * @override
     * @memberof heurist.searchSysGroups
     * @description Sets up the "Add New Group" button (visibility based on `edit_mode`),
     *              the role filter dropdown (behavior adjusted by `select_mode`, `ugl_UserID`, and admin status),
     *              and the sort type dropdown. It also displays an optional subtitle and helper text
     *              based on the operational context. Triggers an initial search.
     */
    _initControls: function() {
        this._super();
        
        let that = this;

        if(this.options.subtitle){
            let ele = this.element.find('.sub-title');
            if(ele.length>0){
                ele.html('<h3>'+this.options.subtitle+'</h3>');
            }
        }
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        
        if(this.options.edit_mode=='none'){
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Group"), icon:"ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 
                
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'width':'100%', 'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }                       
        }
        
        if(this.options.edit_mode=='inline'){
            this.btn_search_start.css('float','right');   
        }

        if(this.options.ugl_UserID > 0){
            this.btn_add_record.css({
                bottom: '',
                left: '',
                right: '15px'
            });
        }
        
        this.input_search_role = this.element.find('#input_search_type');
        
        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        if(smode=='manager'){
            if(this.options.ugl_UserID>0){
                smode = 'manager_for_user';
            }else if (window.hWin.HAPI4.is_admin()){
                smode = 'manager_for_admin';
            }
        }
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        //
        
        //
        if(this.options.select_mode=='select_roles'){
            this.input_search_role.parent().hide(); //always search any group               
        }else
        if(this.options.ugl_UserID>0){//select roles for given user
            //this.options.select_mode=='select_role'){
            //this.input_search_role.hide(); //always search any group    
        }else
        if(!window.hWin.HAPI4.is_admin()){
            this.input_search_role.find('option[value="any"]').remove();    
        }
        
        this._on(this.input_search_role,  { change:this.startSearch });

        if(this.options.select_mode=='manager'){
            this.element.find('#input_search_type_div').css('float','left');
        }
           
        this.input_sort_type = this.element.find('#input_sort_type');
        
        if( !window.hWin.HEURIST4.util.isempty(this.options.sort_type_int) ){
            this.input_sort_type.val(this.options.sort_type_int)
        }
        this._on(this.input_sort_type,  { change:this.startSearch });
        
        this.startSearch();            
    },  

    
    /**
     * @brief Initiates a search for system user groups.
     * @override
     * @memberof heurist.searchSysGroups
     * @description Constructs a search request based on the group name from `input_search`,
     *              the selected role from `input_search_role` (contextualized by `options.ugl_UserID`
     *              or current user), and the chosen sort order.
     *              Populates `this._search_request` and calls the parent `startSearch` method.
     *              Note: This method calls `this._super()` twice, which might be unintentional.
     */
    startSearch: function(){
        
            let request = {}
        
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }
        
            // actually we may take list of groups from currentUser['ugr_Groups']
            let gr_role = this.input_search_role.val();
            if(gr_role!='' && gr_role!='any'){
                
                if(gr_role=='admin'){
                    request['ugl_Role'] = 'admin';
                }else
                if(gr_role=='member'){  
                    request['ugl_Role'] = 'member';
                } 
                
                //only groups for current user
                request['ugl_UserID'] = (this.options.ugl_UserID>0)
                                ?this.options.ugl_UserID
                                :window.hWin.HAPI4.currentUser['ugr_ID']; 
            }else 
            //for group mgm per user search role for any request
            if(this.options.ugl_UserID>0){
                request['ugl_UserID'] = this.options.ugl_UserID; 
                request['ugl_Join'] = true;
            }
            //always search for roles for current or given user            
            
            this._super(); // First call to parent's startSearch
            
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='member'){
                request['sort:ugr_Members'] = '-1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:ugr_ID'] = '-1' 
            }else{ // name
                request['sort:ugr_Name'] = '-1';   
            }
            
            this._search_request = request;
            this._super(); // Second call to parent's startSearch
    }
});
