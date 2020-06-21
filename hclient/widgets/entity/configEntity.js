/**
* Save/load cfg files for given entity
* configuration is stored in [dbfilestore]/entity/[entity name]/[config name]/[id].cfg
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


$.widget( "heurist.configEntity", {

    // default options
    options: {
        entityName: null,
        configName: null,
        entityID: null,
        
        getSettings: null, //callback function to retieve configuration
        setSettings: null, //callback function to apply configuration
        
        //divLoadSettingsName: this.element
        divSaveSettings: null,  //element
        allowRenameDelete: false,
        useHTMLselect: false
    },
    
    sel_saved_settings: null,
    inpt_save_setting_name: null,
    btnSaveSettings: null,

    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        var that = this;


        $('<div class="header" style="padding: 0 16px 0 16px;"><label for="sel_saved_settings">Saved settings:</label></div>'
            +'<select class="sel_saved_settings text ui-widget-content ui-corner-all" style="max-width:30em"></select>&nbsp;&nbsp;'
            +'<span class="ui-icon ui-icon-pencil" style="font-size:smaller;cursor:pointer"></span>'
            +'<span class="ui-icon ui-icon-delete" style="font-size:smaller;cursor:pointer"></span>')
        .appendTo(this.element);

        this.sel_saved_settings = this.element.find('.sel_saved_settings');
        
        if(!this.options.useHTMLselect){
            window.hWin.HEURIST4.ui.initHSelect(this.sel_saved_settings, false);
        }
            

        //
        // rename
        //         
        this._on(this.element.find('span.ui-icon-pencil'), {click:function(){
            var fileName = this.sel_saved_settings.val();
            if(fileName!=''){

                //show prompt
                window.hWin.HEURIST4.msg.showPrompt('Enter new name: ',
                    function(value){

                        var request = {
                            'a'          : 'files',
                            'operation'  : 'rename',
                            'entity'     : that.options.entityName,
                            'folder'     : that.options.configName,    
                            'rec_ID'     : that.options.entityID,
                            'fileOld'    : fileName,    
                            'file'       : value+'.cfg'
                        };

                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    var filename = response.data;
                                    var ele = that.sel_saved_settings.find('option:selected')
                                    ele.attr('value',filename).text( filename.substring(0,filename.indexOf('.cfg')) )
                                    if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                        that.sel_saved_settings.hSelect('refresh'); 
                                    }
                                    
                                    window.hWin.HEURIST4.msg.showMsgFlash('Settings are renamed');

                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                        });      


                    }, 'Rename settings');                
            }
        }});        

        //
        // delete
        //         
        this._on(this.element.find('span.ui-icon-delete'), {click:function(){
            var fileName = this.sel_saved_settings.val();
            if(fileName!=''){


                //show confirmation dialog
                var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    'Delete selected settings?',
                    {'Yes, delete' :function(){ 

                        var request = {
                            'a'          : 'files',
                            'operation'  : 'delete',
                            'entity'     : that.options.entityName,
                            'folder'     : that.options.configName,    
                            'rec_ID'     : that.options.entityID,
                            'file'       : fileName   
                        };

                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    that.sel_saved_settings.find('option:selected').remove();
                                    if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                        that.sel_saved_settings.hSelect('refresh'); 
                                    }
                                    window.hWin.HEURIST4.msg.showMsgFlash('Settings are removed');
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

        //
        // save settings
        //
        if(this.options.divSaveSettings){
            $('<div class="header" style="padding: 0 16px 0 16px;"><label>Save settings for future use</label></div>'
                +'&nbsp;&nbsp;<b>Name</b>&nbsp;<input class="inpt_save_setting_name text ui-widget-content ui-corner-all" style="max-width:30em"/>'
                +'&nbsp;&nbsp;<button class="btnSaveSettings">Save</button>')
            .appendTo(this.options.divSaveSettings);
            this.inpt_save_setting_name = this.options.divSaveSettings.find('.inpt_save_setting_name');        
            this.btnSaveSettings = this.options.divSaveSettings.find('.btnSaveSettings');        

            //
            // save settings
            //        
            this._on(this.btnSaveSettings, {click: function(){

                var fileName = this.inpt_save_setting_name.val();

                if(fileName.trim()==''){
                    window.hWin.HEURIST4.msg.showMsgFlash('Name not defined');
                    return;
                }

                var settings = this.options.getSettings();            
                if(!settings) return;

                var request = {
                    'a'          : 'files',
                    'operation'  : 'put',
                    'entity'     : this.options.entityName,
                    'folder'     : this.options.configName,    
                    'rec_ID'     : this.options.entityID,
                    'file'       : fileName+'.cfg',    
                    'content'    : JSON.stringify(settings)    
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that.inpt_save_setting_name.val('');
                            var filename = response.data;
                            var ele = that.sel_saved_settings.find('option[value="'+filename+'"]');
                            if(ele.length>0){
                                
                            }else{
                                window.hWin.HEURIST4.ui.addoption(that.sel_saved_settings[0], 
                                    filename, filename.substring(0,filename.indexOf('.cfg')) );    
                                if(that.sel_saved_settings.hSelect("instance")!=undefined){
                                        that.sel_saved_settings.hSelect('refresh'); 
                                }
                            }
                            
                            that.element.show();    
                            window.hWin.HEURIST4.msg.showMsgFlash('Settings are saved');

                        }else{
                            that.element.hide();
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                });      


            }});

        }

        //
        //load settings
        //
        this._on(this.sel_saved_settings, {change: function(){

            var fileName = this.sel_saved_settings.val();

            if(fileName.trim()==''){
                return;
            }

            var request = {
                'a'          : 'files',
                'operation'  : 'get',
                'entity'     : this.options.entityName,
                'folder'     : this.options.configName,    
                'rec_ID'     : this.options.entityID,
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
                        } 

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        
                    }
                    //restore selection
                    if($.isFunction(that.options.setSettings))that.options.setSettings( settings );  

            });      

        }});


    },
    
    //
    //
    //
    updateList: function( rtyID ){
        
        this.options.entityID = rtyID;
        
        if(!(this.options.entityID>0)){
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
                    window.hWin.HEURIST4.ui.addoption(ele[0], '', '');
                    
                    var recset = new hRecordSet(response.data);
                    if(recset.length()>0){
                        
                        recset.each(function(recID, rec){
                            var filename = recset.fld(rec, 'file_name');
                            window.hWin.HEURIST4.ui.addoption(ele[0], filename, filename.substring(0,filename.indexOf('.cfg')));
                        });
                        if(that.sel_saved_settings.hSelect("instance")!=undefined){
                            that.sel_saved_settings.hSelect('refresh'); 
                        }
                        that.element.show();    
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });      
        
console.log('SOW');        
        if(this.options.divSaveSettings){
            this.options.divSaveSettings.show();
        }

    }


});
