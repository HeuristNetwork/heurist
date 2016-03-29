/**
* Search header for sysGroups manager
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


$.widget( "heurist.searchSysGroups", $.heurist.searchEntity, {

    // the widget's constructor
    _create: function() {
        
        this._super();

        this._htmlContent =  'searchSysGroups.html';
        this._helpContent = 'sysUGrps.html';
        this._helpTitle = 'About User and Groups';
        
    }, //end _create

    //
    _initControls: function() {
        this._super();
        
        //assign unique name for radio group (to avoid conflicts with other instances of widget)
        this.element.find('.ent_search_cb input[type="radio"]').prop('name', 'rbg_'+top.HEURIST4.util.random());
        
        this.startSearch();
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
        
            var request = {}
        
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }

            // actually we may take list of groups from currentUser['ugr_Groups']
            var gr_role = this.element.find('.ent_search_cb input[type="radio"]:checked').val();
            
            if(gr_role!='anygroup'){
            
                request['ugl_UserID'] = top.HAPI4.currentUser['ugr_ID'];
                
                if(gr_role=='admin'){
                    request['ugl_Role'] = 'admin';
                }else
                if(gr_role=='member'){  
                    request['ugl_Role'] = 'member';
                }
            }
            
            //nothing defined
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = 'sysUGrps';
                request['details']    = 'id';
                request['request_id'] = top.HEURIST4.util.random();
                request['ugr_Type']    = 'workgroup';
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                //that.loadanimation(true);
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
