/**
* Search header for DefDetailTypes manager
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

$.widget( "heurist.searchDefDetailTypes", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;
            
        this.selectRole   = this.element.find('#sel_datatype'); //status of record type - for manager mode only
        
        var key;
        var data_types = [{key:'',title:window.hWin.HR('Any Type')}];
        for (key in window.hWin.HEURIST4.detailtypes.lookups){
            if(window.hWin.HEURIST4.detailtypes.lookups[key]){
                data_types.push({key:key,title:window.hWin.HEURIST4.detailtypes.lookups[key]});
            }
        }
        window.hWin.HEURIST4.ui.createSelector(that.selectRole.get(0), data_types);
        
        this.selectGroup = this.element.find('#sel_group');
        this.selectGroup.empty();
            
        //@todo change to ui.createEntitySelector
        window.hWin.HAPI4.EntityMgr.doRequest({a:'search','details':'name', //'DBGSESSID':'423997564615200001;d=1,p=0,c=0',
                        'entity':'defDetailTypeGroups','dtg_ID':that.options.filter_groups},
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var groups = new hRecordSet(response.data).makeKeyValueArray('dtg_Name');
                            
                            if(window.hWin.HEURIST4.util.isempty(that.options.filter_groups)){
                                groups.unshift({key:'',title:window.hWin.HR('Any Group')});
                            }
                            
                            window.hWin.HEURIST4.ui.createSelector(that.selectGroup.get(0), groups);
                            
                            if(!window.hWin.HEURIST4.util.isempty(that.options.filter_group_selected)){
                                that.selectGroup.val(that.options.filter_group_selected);
                            }
                            
                            that.startSearch();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                      
                      
            this._on( this.element.find('select'), {
                change: function(event){
                    this.startSearch();
                }
            });
                
    },  


    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            if(this.selectGroup.val()!=''){
                request['dty_DetailTypeGroupID'] = this.selectGroup.val();
            }   
            if(this.selectRole.val()!=''){
                request['dty_Type'] = this.selectRole.val();
            }   
            if(this.input_search.val()!=''){
                request['dty_Name'] = this.input_search.val();
            }
            
            //noothing defined
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;  //'defDetailTypes'
                request['details']    = 'list'; //'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
            }
    },
    

});
