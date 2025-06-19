/**
 * @file        searchDefTerms.js
 * @brief       Provides a search interface for Terms within vocabularies.
 * @fileOverview This widget handles the search functionality for Terms, allowing users to find specific terms in vocabularies, often filtered by vocabulary or vocabulary group.
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
 * @class heurist.searchDefTerms
 * @brief Search widget for Terms.
 * @augments $.heurist.searchEntity
 * @property {?string} filter_groups Comma-separated string of groups (domains like 'relation', 'enum') to make available for filtering. If only one is provided, the group selection UI may be hidden.
 * @property {?string} filter_group_selected The initially selected group (domain, e.g., 'relation') when the widget loads.
 */
$.widget( "heurist.searchDefTerms", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the search widget.
     * @override
     * @memberof heurist.searchDefTerms
     * @description Sets up UI elements like view mode tabs, group selection tabs, and search input fields.
     *              It also triggers an initial search if `use_cache` option is true.
     */
    _initControls: function() {
        this._super();
        
        this.selectViewmode = this.element.find('#sel_viewmode');
        this.selectViewmode.tabs()
            .css({position:'absolute','height':'1.8em','bottom':0,'background':'none','border':'none'});
        this.selectViewmode.find('ul').css({'background':'none','border':'none'});
        this._on( this.selectViewmode, { tabsactivate:  
                function(){this._trigger("onviewmode", null, this.selectViewmode.tabs('option','active'));} } );
        
        
        this.selectGroup = this.element.find('#sel_group');
        
        //only one domain to show
        if(!window.hWin.HEURIST4.util.isempty(this.options.filter_groups) && this.options.filter_groups.indexOf(',')<0){
            this.options.filter_group_selected = this.options.filter_groups;
            this.selectGroup.hide();
        }
        
        this.selectGroup.tabs()
            .css({position:'absolute','height':'1.8em','bottom':0,'background':'none','border':'none'});
        if(!window.hWin.HEURIST4.util.isempty(this.options.filter_group_selected)){
            this.selectGroup.tabs('option','active',this.options.filter_group_selected=='relation'?1:0);
        }
        this.selectGroup.find('ul').css({'background':'none','border':'none'});
        
        this._on( this.selectGroup, { tabsactivate: this.startSearch  });
        
        this.input_search_code = this.element.find('#input_search_code');
        this._on(this.input_search,  { keyup:this.startSearch });
        this._on(this.input_search_code,  { keyup:this.startSearch });
                      
        if(this.options.use_cache){
            this.startSearchInitial();            
        }else{
            this.startSearch();            
        }
    },  

    /**
     * @brief Initiates a search for terms based on current input fields and selected domain.
     * @override
     * @memberof heurist.searchDefTerms
     * @description Constructs a request object with term code, label, and domain,
     *              then triggers an "onfilter" event or calls the parent's search method.
     */
    startSearch: function(){
        
        let request = {}
    
        request['trm_Domain'] = this.currentDomain();

        if(this.input_search_code && this.input_search_code.val()!=''){
            request['trm_Code'] = this.input_search_code.val();
        }   
        if(this.input_search && this.input_search.val()!=''){
            request['trm_Label'] = this.input_search.val();
        }
        
        if(this.options.use_cache){
            this._trigger( "onfilter", null, request);
            
            
        //NOTE use_cache=false for terms has no practical sense                    
        }else{
            request['details']   = 'list';
            this._search_request = request;
            this._super();
        }
    },
    
    /**
     * @brief Determines the current search domain ('relation' or 'enum') based on the active tab in the group selector.
     * @memberof heurist.searchDefTerms
     * @returns {string} The current domain, which is either 'relation' or 'enum'.
     */
    currentDomain:function(){
            let domain = this.selectGroup.tabs('option','active');
            return domain==1?'relation':'enum';
    },
    

});
