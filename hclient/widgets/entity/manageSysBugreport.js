/**
* manageSysBugreport.js - prepare and send bugreport by email
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
        this.options.height = 825;

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

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

        var that = this;

        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        var ele = this._as_dialog.find('input[type=file]');
        if(ele.length>0){
            ele.fileupload('option','pasteZone',this._as_dialog);
        }

		// Add spacing between fields, and give textarea's larger height
        var eles = this._editing.getAllFields();
        for(var i = 0; i < eles.length; i++){ // ignore last element (image field)

            var $ele = $(eles[i]);

            if($ele.find('textarea').length != 0 || $ele.find('.fileupload').length != 0){
                $ele.css({'padding-top': '2.2em', 'display': 'block'});

                if($ele.find('textarea').length != 0){
                    setTimeout(function(idx){ $(eles[idx]).find('textarea').attr('rows', 14); }, 1500, i);
                }
            }else{
                // text input, first element
                $ele.css({'padding-top': '1.2em', 'display': 'block'});
                // add extra info at top
				var $extra_info = $('<div>')
                                    .append('<div class="header">')
                                    .append($ele.find('span.editint-inout-repeat-button').clone())
                                    .append('<div class="input-cell">');

                $extra_info.find('div.input-cell')
                            .html('For bug reports it is very helpful if you can provide a screen capture,<br>preferably of the whole screen including the URL');

                $ele.parent().prepend($extra_info);
            }
        }
    },    
    
});
