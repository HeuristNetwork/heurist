/**
* Save/load cfg files for given entity
* configuration is stored in [dbfilestore]/entity/[entity name]/[config name]/[entity_id]/name.cfg
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


$.widget( "heurist.configEntity", {

    
    // default options
    options: {
        entityName: null,
        configName: null,
        entityID: null,
        
        loadSettingLabel:null,
        
        getSettings: null, //callback function to retieve configuration
        setSettings: null, //callback function to apply configuration
        
        //divLoadSettingsName: this.element
        divSaveSettings: null,  //element
        showButtons: false,
        openEditAction: null, //
        buttons: null, //{rename:, openedit:, remove: } if false hidden, if empty - icons only

        saveOnExit: false,  //auto save on exit
        
        useHTMLselect: false,
        //createNewFromSelect: false
        is_vertical_layout: false
    },
    
    sel_saved_settings: null,
    inpt_save_setting_name: null,
    btnSaveSettings: null,
    
    original_settings: null,

    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        var that = this;

        var st = '';
        if(!this.options.is_vertical_layout){
            st = ' style="display:table-cell"';
        }

        let loadSettingLabel = this.options.loadSettingLabel?this.options.loadSettingLabel:'Saved settings:';

        if(this.options.configName == "crosstabs"){

            $('<div style="margin-left: 20px;"><label for="sel_saved_settings">'
                +(loadSettingLabel)+'</label></div>'
                +'<div style="margin-left: 10px;"><select class="sel_saved_settings text ui-widget-content ui-corner-all" style="width:100%;"></select></div>'
                + ((this.options.showButtons)?
                ('<div class="btn-action-div" style="margin-left: 15px;"><span class="btn-action btn-rename bg-warning saveEditButton"/>'
                +'<span class="btn-action btn-openedit"/>'
                +'<span style="margin: 2px;" class="btn-action btn-remove bg-danger saveRemoveButton"/></div></div>'):'') )
            .appendTo(this.element);
        }else if(this.options.configName == "datatable"){ // TODO - setup for datatable version

            $('<div style="display:flex; align-content: center; flex-wrap: wrap; align-items: center;">'
            + ((this.options.showButtons)?'<div style="flex: 0 0 140px;"><span class="btn-action btn-openedit"/></div>' : '')
            + '<div style="flex: 0 0 300px;">'
                + '<label for="sel_saved_settings" style="display:inline-block; margin-right: 10px;">'+loadSettingLabel+'</label><select class="sel_saved_settings text ui-widget-content ui-corner-all"></select>'
            + '</div>'
            +  ((this.options.showButtons)?
                ('<div class="btn-action-div" style="flex: 0 0 125px">&nbsp;&nbsp;'
                    + '<span class="btn-action btn-rename"/>'
                    + '<span class="btn-action btn-remove"/>'
                + '</div>'):'')
            + '</div>').appendTo(this.element);
        }else{

            $('<div'+st+'><label for="sel_saved_settings">'
                +(loadSettingLabel)+'</label></div>'
                +'<div'+st+'><select class="sel_saved_settings text ui-widget-content ui-corner-all"></select></div>'
                + ((this.options.showButtons)?
                ('<div class="btn-action-div"'+st+'>&nbsp;&nbsp;<span class="btn-action btn-rename"/>'
                +'<span class="btn-action btn-openedit"/>'
                +'<span class="btn-action btn-remove"/></div></div>'):'') )
                //('<span class="ui-icon ui-icon-pencil" style="font-size:smaller;cursor:pointer"></span>'
                //+'<span class="ui-icon ui-icon-delete" style="font-size:smaller;cursor:pointer"></span>'):'') )
            .appendTo(this.element);
        }

        this.sel_saved_settings = this.element.find('.sel_saved_settings');
        
        if(!this.options.useHTMLselect){
            window.hWin.HEURIST4.ui.initHSelect(this.sel_saved_settings, false);

            if(this.sel_saved_settings.hSelect("instance")!=undefined){
                var w = (this.options.configName=="datatable")?226:302;
                this.sel_saved_settings.hSelect("widget").css("width", w+'px');
            }
        }

        //
        // rename
        //         
        if(this.options.showButtons){
            
            this.element.find('.btn-action-div').hide();
            this.element.find('.btn-action').css({'font-size':'smaller'}).hide();
            
            if(!this.options.buttons) this.options.buttons = {rename:'', openedit:false, remove:''};
            
            var ele = this.element.find('.btn-rename');
            if(this.options.buttons['rename']!==false){
                
                var showLabel = !window.hWin.HEURIST4.util.isempty(this.options.buttons['rename']);
                ele = ele.button({icon:showLabel?null:'ui-icon-pencil', 
                        label:this.options.buttons['rename'],
                        showLabel:showLabel}).show();
                    
                this._on(ele, {click:function(){
       
                    //default rename action
                    var fileName = this.sel_saved_settings.val();
                    if(fileName!=''){

                        var entity_ID = that.options.entityID; 
                        if(fileName.indexOf('/')>0){
                            fileName = fileName.split('/');
                            entity_ID = fileName[0];
                            fileName = fileName[1];
                        }
                        
                        //show prompt
                        window.hWin.HEURIST4.msg.showPrompt('Enter new name: ',
                            function(value){

                                var request = {
                                    'a'          : 'files',
                                    'operation'  : 'rename',
                                    'entity'     : that.options.entityName,
                                    'folder'     : that.options.configName,    
                                    'rec_ID'     : entity_ID,
                                    'fileOld'    : fileName,    
                                    'file'       : value+'.cfg'
                                };

                                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                    function(response){
                                        if(response.status == window.hWin.ResponseStatus.OK){

                                            var filename = response.data;
                                            var ele = that.sel_saved_settings.find('option:selected')
                                            ele.attr('value',entity_ID+'/'+filename)
                                              .text( filename.substring(0,filename.indexOf('.cfg')) )
                                            
                                            if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                                that.sel_saved_settings.hSelect('refresh'); 
                                            }

                                            window.hWin.HEURIST4.msg.showMsgFlash('Settings have been renamed');

                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                });      


                            }, 'Rename settings');                
                    }
                    
                }});        
            }
            
            //
            //
            //
            ele = this.element.find('.btn-openedit');
            if(this.options.buttons['openedit']!==false && $.isFunction(this.options.openEditAction)){
                
                var showLabel = !window.hWin.HEURIST4.util.isempty(this.options.buttons['openedit']);
                ele = ele.button({icon:null, //showLabel?null:'ui-icon-pencil', 
                        label:this.options.buttons['openedit'],
                        showLabel:showLabel}).css({'margin-left':10}).show();
                
                        this._on(ele, { click: this.options.openEditAction });
            }else if(this.options.buttons['openedit'] === false){
                ele.parent().hide();
            }

            if(this.options.configName == "datatable"){
                ele.css('font-weight', 'bold');
            }
            
            //
            // delete
            //        
            ele = this.element.find('.btn-remove');
            if(this.options.buttons['remove']!==false){
                var showLabel = !window.hWin.HEURIST4.util.isempty(this.options.buttons['remove']);
                ele = ele.button({icon:showLabel?null:'ui-icon-delete', 
                        label:this.options.buttons['remove'],
                        showLabel:showLabel}).css({'margin-left':10}).show();
                
                this._on(ele, {click:function(){
                var fileName = that.sel_saved_settings.val();
                if(fileName!=''){


                    //show confirmation dialog
                    var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'Delete selected settings?',
                        {'Yes, delete' :function(){ 
                            
                            
                            var entity_ID = that.options.entityID; 
                            if(fileName.indexOf('/')>0){
                                fileName = fileName.split('/');
                                entity_ID = fileName[0];
                                fileName = fileName[1];
                            }

                            var request = {
                                'a'          : 'files',
                                'operation'  : 'delete',
                                'entity'     : that.options.entityName,
                                'folder'     : that.options.configName,    
                                'rec_ID'     : entity_ID,
                                'file'       : fileName   
                            };

                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        that.sel_saved_settings.find('option:selected').remove();
                                        if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                            that.sel_saved_settings.hSelect('refresh'); 
                                        }
                                        that.element.find('.btn-action-div').hide();
                                        window.hWin.HEURIST4.msg.showMsgFlash('Settings have been removed');
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                            });      

                            $__dlg.dialog( "close" );
                            },
                            'Cancel':function(){ 
                                $__dlg.dialog( "close" );
                        }}, {title:'Delete settings'});

                    }
                }});    
            }    
        }

        //
        // save settings
        //
        if(this.options.divSaveSettings){

            if(this.options.configName == "crosstabs"){
                $('<div style="display: block;"><label>Save settings as </div> '
                + '<div style="margin-left: 6px;"><input class="inpt_save_setting_name text form-control rounded-0" style="font-size: small;"/></label></div>'
                + (this.options.saveOnExit?'':'<div style="margin-left: 10px;">'
                + '<button class="btn btn-success btnSaveSettings" style="font-size:10px;"><i class="ui-icon ui-icon-save"></i> Save </button></div>'))
                .appendTo(this.options.divSaveSettings);
            }else{
                $('<div style="display:table-cell"><label>Save settings as <input class="inpt_save_setting_name text form-control"/></label></div>'
                + (this.options.saveOnExit?'':'<div style="display:table-cell">&nbsp;<button class="btn btn-success btnSaveSettings">Save</button></div>'))
                .appendTo(this.options.divSaveSettings);
            }
            this.inpt_save_setting_name = this.options.divSaveSettings.find('.inpt_save_setting_name');        
            
            if(!this.options.saveOnExit){
            
                this.btnSaveSettings = this.options.divSaveSettings.find('.btnSaveSettings');        

                //
                // save settings
                //        
                this._on(this.btnSaveSettings, {click: this.saveSettings});
            }
        }

        //
        //load settings
        //
        this._on(this.sel_saved_settings, {change: function(){

            var fileName = this.sel_saved_settings.val();
            if(fileName=='new'){
                this.options.openEditAction.call(this, true);
                this.sel_saved_settings[0].selectedIndex = 0;
                if(this.sel_saved_settings.hSelect("instance")!=undefined){
                    this.sel_saved_settings.hSelect('refresh'); 
                }
                this.element.find('.btn-action-div').hide();
                return;
            }
            
            var entity_ID = this.options.entityID; 
            if(fileName.indexOf('/')>0){
                fileName = fileName.split('/');
                entity_ID = fileName[0];
                fileName = fileName[1];
            }
            
            if(fileName.trim()==''){
                this.element.find('.btn-action-div').hide();
                return;
            }
            this.element.find('.btn-action-div').show();

            var request = {
                'a'          : 'files',
                'operation'  : 'get',
                'entity'     : this.options.entityName,
                'folder'     : this.options.configName,    
                'rec_ID'     : entity_ID, 
                'file'       : fileName
            };

            var that = this;                                                
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    var settings = null;
                    if(response.status == window.hWin.ResponseStatus.OK){

                        settings = window.hWin.HEURIST4.util.isJSON(response.data);
                        if(settings==false){
                            settings = null;
                            window.hWin.HEURIST4.msg.showMsgFlash('Settings are invalid');
                        }else if(that.options.saveOnExit && that.sel_saved_settings){
                            that.original_settings = response.data;
                            that.inpt_save_setting_name.val(that.sel_saved_settings.find('option:selected').text());
                        } 

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        
                    }
                    //callback function to apply configuration
                    if($.isFunction(that.options.setSettings)){
                        settings.cfg_name = that.sel_saved_settings.val();
                        that.options.setSettings( settings );      
                    }

            });      

        }});


    },
    
    //
    //
    //
    isSomethingChanged: function(){

        var new_settings = this.options.getSettings();            
        if(!new_settings) return '';
        
        if((this.original_settings != JSON.stringify(new_settings))){
            return true;
        }else{
            return this.sel_saved_settings.val();
        }
    },
    
    //
    //
    //
    getFileName: function(){
        return 
    },
    
    //
    //
    //
    saveSettings: function(callback){

        var fileName = this.inpt_save_setting_name.val();
        
        var entity_ID = this.options.entityID; 

        if(fileName.trim()==''){
            if(this.options.saveOnExit){
                fileName = 'Temporary list for '+$Db.rty(entity_ID,'rty_Name');
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Name not defined');
                return;
            }
        }

        /*if(fileName.indexOf('/')>0){
            fileName = fileName.split('/');
            entity_ID = fileName[0];
            fileName = fileName[1];
        }*/
        
        var settings = this.options.getSettings();            
        if(!settings) return;
        
        this.original_settings = JSON.stringify(settings);

        var request = {
            'a'          : 'files',
            'operation'  : 'put',
            'entity'     : this.options.entityName,
            'folder'     : this.options.configName,    
            'rec_ID'     : entity_ID,
            'file'       : fileName+'.cfg',    
            'content'    : JSON.stringify(settings)    
        };

        var that = this;
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    that.inpt_save_setting_name.val('');
                    var filename = response.data;
                    var ele = that.sel_saved_settings.find('option[value="'+filename+'"]');
                    if(ele.length>0){
                        
                    }else{
                        window.hWin.HEURIST4.ui.addoption(that.sel_saved_settings[0], 
                            entity_ID+'/'+filename, 
                            filename.substring(0,filename.indexOf('.cfg')) );    
                        if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                that.sel_saved_settings.hSelect('refresh'); 
                        }
                    }
                    
                    that.element.show();    
                    window.hWin.HEURIST4.msg.showMsgFlash('Settings have been saved');
                    
                    if($.isFunction(callback)){
                        callback.call( this, entity_ID+'/'+filename );
                    } 

                }else{
                    that.element.hide();
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });      


    },    
    
    //
    // initial_value - selected config file
    //
    updateList: function( rtyID, initial_value ){
        
        this.options.entityID = rtyID;
        
        if(rtyID!='all' && !(this.options.entityID>0)){
            this.element.hide();    
            if(this.options.divSaveSettings){
                this.options.divSaveSettings.hide();
            }
            return;
        }
        
        //get list of settings

        var request = {
                'a'          : 'files',
                'operation'  : 'list',
                'entity'     : this.options.entityName,
                'folder'     : this.options.configName,    
                'rec_ID'     : this.options.entityID
            };
            
        var that = this;                                                
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var ele = that.sel_saved_settings.empty();
                    window.hWin.HEURIST4.ui.addoption(ele[0], '', 'select...');
                    if($.isFunction(that.options.openEditAction)){
                        window.hWin.HEURIST4.ui.addoption(ele[0], 'new', 'Create new field list');    
                    }
                    ele[0].selectedIndex = 0;
                    
                    var recset = new hRecordSet(response.data);
                    if(recset.length()>0){
                        
                        recset.each(function(recID, rec){
                            var filename = recset.fld(rec, 'file_name');
                            
                            var entity_id = recset.fld(rec, 'file_dir');
                            entity_id = entity_id.split('/')
                            entity_id = entity_id[entity_id.length-2];
                            entity_id  = entity_id+'/'+filename;
                            filename = filename.substring(0,filename.indexOf('.cfg'))

                            var opt = window.hWin.HEURIST4.ui.addoption(ele[0], 
                                                    entity_id, 
                                                    filename);
                            if(initial_value==entity_id){
                                $(opt).attr('selected',true);
                                that.element.find('.btn-action-div').show();
                                if(that.inpt_save_setting_name){
                                    that.inpt_save_setting_name.val(filename);
                                }
                            }
                        });
                        //ele[0].selectedIndex = sel_idx;
                        
                        that.element.show();    
                    }
                    if(that.sel_saved_settings.hSelect("instance")!=undefined){
                        that.sel_saved_settings.hSelect('refresh'); 
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });      
        
        if(this.options.divSaveSettings){
            this.options.divSaveSettings.show();
        }

    }


});
