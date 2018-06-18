/**
* recordAccess.js - apply ownership and access rights
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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

$.widget( "heurist.recordAccess", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 340,
        width:  640,
        modal:  true,
        init_scope: 'selected',
        title:  'Change Record Access and Ownership',
        htmlContent: 'recordAccess.html',
        helpContent: 'recordAccess.html' //in context_help folder
    },

    _initControls:function(){
        
        var fieldSelect = $('#sel_Ownership');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(fieldSelect[0], null,  //take groups of current user
                [{key:0, title:'Everyone (no restriction)'}, 
                 {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName']}]);
        
        return this._super();
    },
    
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR('Apply');
        return res;
    },    
    
    //
    //
    //
    doAction: function(){

            var scope_val = this.selectRecordScope.val();
            if(scope_val=='')    return;
            
            var scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
            
            return;            
        
            var request = {
                'a'          : 'batch',
                'entity'     : 'usrBookmarks',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'rating'     : rating,
                'bkm_RecID'  : scope
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.updated>0);
                            
                            that.closeDialog();
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(
                                response.data.processed + ' bookmarked record'
                                + (response.data.processed>1?'s':'') +' processed<br><br>for '
                                + response.data.updated  + ' bookmark'
                                + (response.data.updated>1?'s':'') + ' the rating was updated',2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

