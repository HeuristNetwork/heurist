/**
* Search header for DefTerms manager
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.searchDefTerms", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;

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

    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            request['trm_Domain'] = this.currentDomain();

            if(this.input_search_code.val()!=''){
                request['trm_Code'] = this.input_search_code.val();
            }   
            if(this.input_search.val()!=''){
                request['trm_Label'] = this.input_search.val();
            }
            
            if(this.options.use_cache){
                this._trigger( "onfilter", null, request);
                
                
            //NOTE use_cache=false for terms has no practical sense                    
            }else if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'list'; //'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
            }            
    },
    
    currentDomain:function(){
            var domain = this.selectGroup.tabs('option','active');
            return domain==1?'relation':'enum';
    },
    

});
