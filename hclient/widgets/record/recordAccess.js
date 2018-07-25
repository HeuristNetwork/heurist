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
        currentOwner: 0,
        currentAccess: null,
        htmlContent: 'recordAccess.html',
        helpContent: 'recordAccess.html' //in context_help folder
    },

    _initControls:function(){
        
        var fieldSelect = this.element.find('#sel_Ownership');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(fieldSelect[0], null,  //take groups of current user
                [{key:0, title:'Everyone (no restriction)'}, 
                 {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName']}]);
        
        if(this.options.currentOwner>=0 && this.options.currentAccess){
            fieldSelect.val(this.options.currentOwner);
            this.element.find('#rb_Access-'+this.options.currentAccess).prop('checked', true);
        }
        
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
            var ownership = this.element.find('#sel_Ownership').val();
            var visibility = this.element.find('input[type="radio"][name="rb_Access"]:checked').val();
            
            if (!(scope_val!='' && ownership>=0 && visibility))  return;
            
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
            
            var request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'ids'  : scope,
                'OwnerUGrpID': ownership,
                'NonOwnerVisibility': visibility
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                var that = this;                                                
                
                window.hWin.HAPI4.RecordMgr.access(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.updated>0);
                            
                            that.closeDialog();
                            
                            var msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Updated: '
                                + response.data.updated  + ' record'
                                + (response.data.updated>1?'s':'');
                           if(response.data.noaccess>0){
                               msg += ('<br><br>No enough rights for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    _onRecordScopeChange: function () 
    {
        var scope_val = this.selectRecordScope.val();
        var ownership = this.element.find('#sel_Ownership').val();
        var visibility = this.element.find('input[type="radio"][name="rb_Access"]:checked').val();
            
        var isdisabled = (!(scope_val!='' && ownership>=0 && visibility));
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
    },
  
});

