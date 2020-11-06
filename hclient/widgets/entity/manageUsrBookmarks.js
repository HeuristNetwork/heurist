/**
* manageUsrBookmarks.js - main widget to manage users bookmarks
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
// there is no search, select mode for bookmarks - only edit
//
$.widget( "heurist.manageUsrBookmarks", $.heurist.manageEntity, {
   
    _entityName:'usrBookmarks',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 620;
        this.options.height = 320;
        

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        //load bookmark for given record id
        if(this.options.bkm_RecID>0){
                var request = {};
                request['bkm_RecID']  = this.options.bkm_RecID;
                request['a']          = 'search'; //action
                request['entity']     = this._entityName;//options.entity.entityName;
                request['details']    = 'full';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
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
    
    // change label for remove
    _getEditDialogButtons: function(){
        var btns = this._super();
        
        var that = this;
        for(var idx in btns){
            if(btns[idx].id=='btnRecRemove'){
                btns[idx].text = window.hWin.HR('Delete bookmark');
                /*btns[idx].click = function(){
                        window.hWin.HEURIST4.msg.showMsgDlg(
            'Are you sure you wish to delete this bookmark? Proceed?', function(){
                that._deleteAndClose();
                }, {title:'Warning',yes:'Proceed',no:'Cancel'});        
                }*/
                break;
            }
        }
        
        return btns;
    },
    
//----------------------------------------------------------------------------------    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this bookmark? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    _saveEditAndClose: function( fields, afteraction ){
        
        var ele2 = this._editing.getFieldByName('bkm_RecID');
        ele2.editing_input('setValue', this.options.bkm_RecID );
        
        this._super();// null, afteraction );
    },
    
    
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        this.closeDialog(true); //force to avoid warning
    },
    
    
});
