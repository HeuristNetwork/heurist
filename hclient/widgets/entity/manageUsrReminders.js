/**
* manageUsrReminders.js - main widget to manage users reminders
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

//
// there is no search, select mode for reminders - only edit
//
$.widget( "heurist.manageUsrReminders", $.heurist.manageEntity, {
   
    _entityName:'usrReminders',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 790;
        this.options.height = 600;
        

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
//
console.log('....'+this.options.rem_RecID);        
        //load bookmark for given record id
        if(this.options.rem_RecID>0){
                var request = {};
                request['rem_RecID']  = this.options.rem_RecID;
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'full';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            var recset = new hRecordSet(response.data);
                            if(recset.length()>0){
                                that.updateRecordList(null, {recordset:recset});
                                that.addEditRecord( recset.getOrder()[0] );
                            }
                            else {
                                //nothing found - add new bookmark
                                that.addEditRecord(-1);
                            }                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.closeEditDialog();
                        }
                    });        
                    
        }else{
            this.addEditRecord(-1);
        }
        
        return true;
    },
    
//----------------------------------------------------------------------------------    

    _saveEditAndClose: function( fields, afteraction ){
        
        var ele2 = this._editing.getFieldByName('rem_RecID');
        ele2.editing_input('setValue', this.options.rem_RecID );
        
        this._super();// null, afteraction );
    },
    
    
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        this.closeDialog();
    },
    
    _afterInitEditForm: function(){

        this._super();
    
        var that = this;
        var ele = this._editing.getFieldByName('rem_IsPeriodic');
        var val = this._getField('rem_StartDate');
        
        var isManual = window.hWin.HEURIST4.util.isempty(val) || val=='0000-00-00';
        
        function __onChangeType(){ 
            var ele1 = that._editing.getFieldByName('rem_Freq');
            var ele2 = that._editing.getFieldByName('rem_StartDate');
            
            var res = ele.editing_input('getValues'); 
            if(res[0]=='now'){
                    ele2.editing_input('setValue', '');
                    ele1.hide();
                    ele2.hide();
            }else{
                    ele1.show();
                    ele2.show();
            }
        }
        
        ele.editing_input('option', 'change', __onChangeType);
        ele.editing_input('setValue', isManual?'now':'later');
        __onChangeType();
        
        
        var ele1 = this._editing.getFieldByName('rem_ToWorkgroupID');
        var ele2 = this._editing.getFieldByName('rem_ToUserID');
        var ele3 = this._editing.getFieldByName('rem_ToEmail');
        
        
        function __onChange2( ){
//console.log($(this.element).editing_input('option','dtID'));
           
           var res = $(this.element).editing_input('getValues')
           if(res[0]!=''){
               var dtID = $(this.element).editing_input('option','dtID');
               if(dtID!='rem_ToWorkgroupID') ele1.editing_input('setValue', '');
               if(dtID!='rem_ToUserID') ele2.editing_input('setValue', '');
               if(dtID!='rem_ToEmail') ele3.editing_input('setValue', '');
           }
        }
    
        ele1.editing_input('option', 'change', __onChange2);
        ele2.editing_input('option', 'change', __onChange2);
        ele3.editing_input('option', 'change', __onChange2);

    
    }
    
});
