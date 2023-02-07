/**
* recordRate.js - assign rate for scope of records
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

$.widget( "heurist.recordRate", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 300,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Set Record Rating',
        helpContent: 'recordBookmark.html'
    },

    _initControls:function(){
        
        $('<table style="margin:auto;padding-top: 14px;font-size:1em">'
                +'<tbody><tr><td><input type="radio" value="0" name="r" id="r0"></td><td><label for="r0">'
                        +window.hWin.HR('No Rating')+'</label></td></tr>'
                +'<tr><td><input type="radio" value="1" name="r" id="r1"></td><td><label for="r1" class="yellow_star" style="width:14px;"></label></td></tr>'
                +'<tr><td><input type="radio" value="2" name="r" id="r2"></td><td><label for="r2" class="yellow_star" style="width:24px;"></label></td></tr>'
                +'<tr><td><input type="radio" value="3" name="r" id="r3"></td><td><label for="r3" class="yellow_star" style="width:38px;"></label></td></tr>'
                +'<tr><td><input type="radio" value="4" name="r" id="r4"></td><td><label for="r4" class="yellow_star" style="width:50px;"></label></td></tr>'
                +'<tr><td><input type="radio" value="5" name="r" id="r5"></td><td><label for="r5" class="yellow_star" style="width:64px;"></label></td></tr>'
        +'</tbody></table>').appendTo( this.element.find('#div_fieldset'));
        
        return this._super();
    },
    
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR('Set Rating');
        return res;
    },    
    
    //
    //
    //
    doAction: function(){

            var scope_val = this.selectRecordScope.val();
            if(scope_val=='')    return;
            
            var rating = this.element.find('input[type=radio]:checked').val();
            
            if(!(rating>=0 && rating<6)){
                window.hWin.HEURIST4.msg.showMsgErr('Please specify rating value');
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
                                window.hWin.HR('Processed records')+': '+response.data.processed+'<br>'
                                +window.hWin.HR('Rating updated')+': '+response.data.updated, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

