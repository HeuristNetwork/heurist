/**
* Search header for sysUsers manager
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

$.widget( "heurist.searchSysUsers", $.heurist.searchEntity, {

    // the widget's constructor
    _create: function() {
        this._super();

        this._htmlContent =  'searchSysUsers.html';
        this._helpContent = 'sysUGrps.html';
        this._helpTitle = 'About User and Groups';
    }, //end _create

    //
    _initControls: function() {
        this._super();
        
        var that = this;
            
        this.selectRole   = this.element.find('#sel_role');
        this.selectGroup = this.element.find('#sel_group');
        this.selectGroup.empty();
            
        window.hWin.HAPI4.EntityMgr.doRequest({a:'search','details':'name', //'DBGSESSID':'423997564615200001;d=1,p=0,c=0',
                        'entity':'sysUGrps','ugr_Type':'workgroup','ugr_ID':that.options.filter_groups},
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var groups = new hRecordSet(response.data).makeKeyValueArray('ugr_Name');
                            
                            if(window.hWin.HEURIST4.util.isempty(that.options.filter_groups)){
                                groups.unshift({key:'',title:window.hWin.HR('Any Group')});
                            }
                            
                            window.hWin.HEURIST4.ui.createUserGroupsSelect(that.selectGroup.get(0), 
                                [], 
                                groups,
                                function(){ //on load completed
                                    //force search 
                                    //predefined search values
                                    if(!window.hWin.HEURIST4.util.isempty(that.options.filter_group_selected)){
                                            that.selectGroup.val(that.options.filter_group_selected);  
                                    } 
                                    that.selectGroup.change(); //force search
                                });
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                      
                      
            this._on( this.element.find('select'), {
                change: function(event){
                    if(this.selectGroup.val()>0){
                        that.selectRole.parent().show();
                        //that.element.find('span[for="sel_role"]').show();
                        //that.element.find('#sel_role_lbl').show();
                    }else{
                        that.selectRole.parent().hide();
                        //that.element.find('#sel_role_lbl').hide();
                    }
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
                request['ugl_GroupID'] = this.selectGroup.val();
                if(this.selectRole.val()!=''){
                    request['ugl_Role'] = this.selectRole.val();
                }   
            }   
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }


            if(this.element.find('#cb_selected').is(':checked')){
                request['ugr_ID'] = window.hWin.HAPI4.get_prefs('recent_Users');
            }
            if(this.element.find('#cb_modified').is(':checked')){
                var d = new Date(); 
                //d = d.setDate(d.getDate()-7);
                d.setTime(d.getTime()-7*24*60*60*1000);
                request['ugr_Modified'] = '>'+d.toISOString();
            }
            if(this.element.find('#cb_inactive').is(':checked')){
                request['ugr_Enabled'] = 'n';
            }
            
            //noothing defined
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = 'sysUGrps';
                request['details']    = 'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['ugr_Type']    = 'user';
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                //that.loadanimation(true);
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
