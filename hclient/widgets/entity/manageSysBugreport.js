/**
* manageSysBugreport.js - prepare and send bugreport by email
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
// there is no search, select mode for bug report - only add and send by email
//
$.widget( "heurist.manageSysBugreport", $.heurist.manageEntity, {
   
    _entityName:'sysBugreport',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        this.options.title = 'Bug Report';
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 700;
        this.options.height = 800;

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
        
        // always new report
        this.addEditRecord(-1);
        
        return true;
    },
    
    // change label for remove
    _getEditDialogButtons: function(){
        var btns = this._super();
        
        var that = this;
        for(var idx in btns){
            if(btns[idx].id=='btnRecSave'){
                btns[idx].text = window.hWin.HR('Send to heurist development team');
                break;
            }
        }
        
        return btns;
    },
    
//----------------------------------------------------------------------------------    
    _afterSaveEventHandler: function( recID, fields ){
        window.hWin.HEURIST4.msg.showMsgFlash(this.options.entity.entityTitle+' '+window.hWin.HR('has been sent'));
        this.closeDialog(true); //force to avoid warning
    },
    
    _afterInitEditForm: function(){
        this._super();
        
        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        var ele = this._as_dialog.find('input[type=file]');
        if(ele.length>0){
            ele.fileupload('option','pasteZone',this._as_dialog);
        }
        
    },    
    
});
