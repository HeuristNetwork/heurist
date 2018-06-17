/**
* recordDelete.js - remove scope of records
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

$.widget( "heurist.recordDelete", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 300,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Delete Records',
        helpContent: 'recordDelete.html'
    },

    _initControls:function(){
        
        $('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html('Select the scope of records to be deleted.<br>'
            +' <br>');
        
        this.element.parents('.ui-dialog').find('#btnDoAction').attr('label', top.HR('Delete Records'));
        
        return this._super();
    },
    
    //
    //
    //
    doAction: function(isconfirm){

        var scope_val = this.selectRecordScope.val();
        if(scope_val=='') return;
        
        var that = this;
        
        if(isconfirm!==true){
            
        
            
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<span class="ui-icon ui-icon-alert" style="display:inline-block">&nbsp;</span>&nbsp;'
                +'Please confirm that you really wish to delete the selected records, <br/>along with all associated bookmarks?', 
            function(){
                    that.doAction(true);
                    },'Confirm');
            return;
        }            
            
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
                'a'          : 'batch',
                'entity'     : 'usrBookmarks',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'mode'       : 'remove',
                'bkm_RecID'  : scope
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.deleted>0);
                            
                            that.closeDialog();
                            
                            window.hWin.HEURIST4.msg.showMsgDlg(
                                'For '+response.data.processed + ' processed record'
                                + (response.data.processed>1?'s':'') +'<br><br> '
                                + response.data.deleted  + ' bookmark'
                                + (response.data.deleted>1?'s were':' was') + ' deleted',null, 'Result'
                            );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

