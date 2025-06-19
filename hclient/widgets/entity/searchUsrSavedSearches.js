/**
 * @file        searchUsrSavedSearches.js
 * @brief       Provides a search interface for User Saved Searches.
 * @fileOverview This widget handles the search functionality for User Saved Searches, allowing filtering by name and user group.
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
 * @class heurist.searchUsrSavedSearches
 * @brief Search widget for User Saved Searches.
 * @augments $.heurist.searchEntity
 * @description This widget provides a user interface for searching user-saved searches (filters).
 *              It allows filtering by search name and by user group.
 *
 * @property {string} [edit_mode='none'] Defines the editing capabilities. If not 'none' and `search_form_visible` is true,
 *           an "Add New Filter" button is shown. Inherited, but its usage is prominent.
 * @property {?number} svs_UGrpID If provided, the list of saved searches is filtered by this User Group ID,
 *           and the group selection dropdown is hidden.
 * @property {?object} initial_filter An initial filter object to apply to the search. If `options.search_form_visible`
 *           is true, this filter is applied once and then cleared. Inherited, specific behavior noted.
 * @property {boolean} [search_form_visible=true] Controls visibility of the search form.
 *           If false, the "Add New Filter" button is hidden. Inherited, specific behavior noted.
 *
 * @listens heurist.searchUsrSavedSearches#onadd - Fired when the "Add New Filter" button is clicked.
 */
$.widget( "heurist.searchUsrSavedSearches", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the User Saved Searches interface.
     * @override
     * @memberof heurist.searchUsrSavedSearches
     * @description Sets up the user group selection dropdown (`input_search_group`),
     *              the "Add New Filter" button (visibility based on `options.edit_mode` and `options.search_form_visible`),
     *              and the sort type dropdown. Helper text visibility is adjusted based on `options.select_mode`.
     *              If `options.svs_UGrpID` is provided, the group selector is hidden and pre-filled.
     *              Triggers an initial search.
     */
    _initControls: function() {
        
        let that = this;
        
        this.input_search_group = this.element.find('#input_search_group');   //user group
        let topOptions = [{key:'any',title:'any group'},{key:window.hWin.HAPI4.user_id(),title:'My Filters'}];
        
        if(window.hWin.HAPI4.is_admin()){
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], 'all_my_first' , 
                        topOptions);
        }else{
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
                        topOptions);
        }
        
        this._super();

        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');

        if(this.options.edit_mode=='none' || this.options.search_form_visible==false){
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Filter"), icon: "ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 

            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }                       
        }
        
        this._on(this.input_search_group,  { change:this.startSearch });
        
        if( this.options.svs_UGrpID>0 ){ //show filters from given group only
            this.input_search_group.parent().hide();
            this.input_search_group.val(this.options.svs_UGrpID);
            
            if(!window.hWin.HAPI4.is_admin()){
                this.btn_add_record.hide();
            }
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        this.startSearch();            
    },  

    
    /**
     * @brief Initiates a search for user-saved searches.
     * @override
     * @memberof heurist.searchUsrSavedSearches
     * @description Constructs a search request. It starts with `options.initial_filter` if available
     *              (and clears it if `options.search_form_visible` is true).
     *              Then adds filters for search name (from `input_search`) and user group
     *              (from `input_search_group`, unless `options.svs_UGrpID` is set).
     *              Determines sort order based on `input_sort_type`.
     *              Populates `this._search_request` and calls the parent `startSearch` method.
     */
    startSearch: function(){
        
            let request = {}
            
            if(this.options.initial_filter!=null){
                request = this.options.initial_filter;
                if(this.options.search_form_visible){
                    this.options.initial_filter = null;
                }
            }
                
            if(this.input_search.val()!=''){
                request['svs_Name'] = this.input_search.val();
            }
            
            if(this.input_search_group.val()>0){
                request['svs_UGrpID'] = this.input_search_group.val();
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='name'){
                request['sort:svs_Name'] = '1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:svs_ID'] = '-1' 
            }else{ // Default sort, or if 'name' is chosen from a potentially different set of options not shown
                request['sort:svs_Name'] = '1';   
            }
            
            this._search_request = request;
            this._super();
    }
});
