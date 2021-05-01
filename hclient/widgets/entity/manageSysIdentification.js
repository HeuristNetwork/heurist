/**
* manageDefDetailTypeGroups.js - main widget mo manage defDetailTypeGroups
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


$.widget( "heurist.manageSysIdentification", $.heurist.manageEntity, {
    
    _entityName:'sysIdentification',
    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 1020;
        this.options.height = 800;
        this.options.use_cache = true;
        
        this._super();
    },
    
    _initControls: function() {

        if(!this._super()){
            return false;
        }

        var that = this;
        

        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                that._cachedRecordset = response;
                that.updateRecordList(null, {recordset:response});
                that.addEditRecord( response.getOrder()[0] );
            });
        
        if(!this.options.isdialog){
            var fele = this.element.find('.ent_wrapper:first');
            $(fele).on("mouseleave", function(){
                that.defaultBeforeClose();
            });
        }
            
        return true;
    }, 
    
    // change label for remove
    _getEditDialogButtons: function(){
        var btns = this._super();
        
        var that = this;
        for(var idx in btns){
            if(btns[idx].id=='btnRecRemove'){
                //remove this button -    
                btns.splice(idx,1);
                break;
            }
        }
        
        return btns;
    },
    
    
    _afterInitEditForm: function(){

        //make labels in edit form wider
        this.editForm.find('.header').css({'min-width':'250px','width':'250px', 'font-size': '0.9em'});
        
        this._super();
        
        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        var ele = this.editForm.find('input[type=file]');  //this._as_dialog.find
        if(ele.length>0){
            ele.fileupload('option','pasteZone', this.editForm); //this._as_dialog);
        }
        
        //add lookup services config
        var ele = this._editing.getFieldByName('sys_ExternalReferenceLookups');  
        ele = ele.find('.input-div');
        ele.find('input.ui-widget-content').hide();
        
        this.btn_config_lookups = $('<button>')
            .button({label:'Configure lookup services'})
            .css('min-width', '200px');
        this.btn_config_lookups.appendTo(ele);
        
        var that = this;
        
        this._on( this.btn_config_lookups, {    
            'click': function(event){
                
                var cfg_value = this._editing.getValue('sys_ExternalReferenceLookups')[0];

                window.hWin.HEURIST4.ui.showRecordActionDialog('recordLookupCfg',
                    {service_config: cfg_value,
                    onClose: function(context){
                        that._editing.setFieldValueByName2('sys_ExternalReferenceLookups',JSON.stringify(context),true);
                    }}
                );
            }});

        
    },
    
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        
        var that = this;
        
        //reload local sysinfo
        window.hWin.HAPI4.SystemMgr.sys_info(function(){
            that.closeDialog(true); //force to avoid warning    
            
            //close populate section
            $('.ui-menu6').mainMenu6('closeContainer', 'populate');

            
        });
        
        
        
        
    },
    
});
