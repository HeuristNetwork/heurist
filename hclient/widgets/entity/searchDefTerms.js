/**
* Search header for DefTerms manager
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

$.widget( "heurist.searchDefTerms", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;
            
        this.selectGroup = this.element.find('#sel_group');

        this.input_search_code = this.element.find('#input_search_code');
        this._on( this.input_search_code, {
                keypress:
                function(e){
                    var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            top.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            this.startSearch();
                        }
                }});
        
                      
        this._on( this.element.find('select'), {
                change: function(event){
                    this.startSearch();
                }
            });
        
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
        
            if(this.selectGroup.val()!=''){
                request['trm_Domain'] = this.selectGroup.val();
            }   
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
                request['request_id'] = top.HEURIST4.util.random();
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                
                top.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });
            }            
    },
    

});
