/**
* Search header for DefTerms manager
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.searchUsrTags", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;

        
        this.input_search_group = this.element.find('#input_search_group');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
            [{key:'any', title:window.hWin.HR('Any')},
             {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:'Personal tags'}]);
        
        this.btn_search_start.css('float','right');   
        

        this.input_sort_name = this.element.find('#input_sort_name');
        this.input_sort_popular = this.element.find('#input_sort_popular');
        this.input_sort_recent =  this.element.find('#input_sort_recent');
        this._on(this.input_sort_name,  { change:this.startSearch });
        this._on(this.input_sort_popular,  { change:this.startSearch });
        this._on(this.input_sort_recent,  { change:this.startSearch });
        this._on(this.input_search_group,  { change:
            function(){
                this._trigger( "ongroupfilter", null, this.input_search_group.val());
            }
        });
        this._on( this.input_search, { keyup: this.startSearch });
        
        //hide all help divs except current mode
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1 > span').hide();
        this.element.find('.heurist-helper1 > span.'+smode+',span.common_help').show();
        
        if(this.options.use_cache){
            this.startSearchInitial();            
        }else{
            this.startSearch();            
        }
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            /* we don't filter by group - just hide acccordion
            if(this.input_search_group.val()!='any'){
                request['tag_UGrpID'] = this.input_search_group.val();    
            }
            */
            request['tag_Text'] = this.input_search.val();    
            
            if(this.input_sort_popular.is(':checked')){
                request['sort:tag_Usage'] = '-1';
            }else
            if(this.input_sort_recent.is(':checked')){
                request['sort:tag_Modified'] = '-1' 
            }else
            if(this.input_sort_name.is(':checked')){
                request['sort:tag_Text'] = '1' 
            }

            //if we use cache                
            this._trigger( "onfilter", null, request);
      
    },

});
