/**
* Search header for DefTerms manager
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

$.widget( "heurist.searchRecUploadedFiles", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;
        
        
        this.btn_add_record_loc = this.element.find('#btn_add_record_loc');
        this.btn_add_record_ext = this.element.find('#btn_add_record_ext');
        this.btn_add_record_popup = this.element.find('#btn_add_record_popup'); 
        this.btn_add_record_any = this.element.find('#btn_add_record_any');
        this.btn_edit_mimetypes = this.element.find('#btn_edit_mimetypes');
        
        if(this.options.edit_mode=='none'){
            this.element.find('#div_add_record').hide();
        }else{
            //this.btn_add_record_inline.hide();
            //.css({position:'absolute',top:0,right:170,'max-width':300,'max-height':150});
                        
            
            this.btn_add_record_loc.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Select file to upload"), icons: {
                            primary: "ui-icon-plus"
                    }})
                .click(function(e) {
                    that._trigger( "onaddlocal" );
                }); 

            this.btn_add_record_popup.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Drag and drop file to upload"), icons: {
                            primary: "ui-icon-plus"
                    }})
                .click(function(e) {
                    that._trigger( "onaddpopup" );
                }); 
            
            this.btn_add_record_ext.css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR("Specify external file/URL"),icons: {
                            primary: "ui-icon-plus"
                    }})
                .click(function(e) {
                    that._trigger( "onaddext" );
                }); 
                
                
            this.btn_edit_mimetypes
                    .button({label: window.hWin.HR("Define mime types"),icons: {
                            primary: "ui-icon-pencil"
                    }})
                .click(function(e) {
                    window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                                                {edit_mode:'inline', width:900});
                }); 
                
            
            /*    
            this.btn_add_record_any.css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR("Add any"),icons: {
                            primary: "ui-icon-plus"
                    }})
                .click(function(e) {
                    that._trigger( "onaddany" );
                }); */
                
            /*
            if(this.options.edit_mode=='inline'){
                this.btn_add_record_loc.parent().css({'float':'left','border-bottom':'1px lightgray solid',
                'width':'100%', 'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }     
            */                  
        }
        
            
        this.selectGroup = this.element.find('#sel_group');
        
        //only one domain to show - as specified in options
        if(!window.hWin.HEURIST4.util.isempty(this.options.filter_groups) && this.options.filter_groups.indexOf(',')<0){
            this.options.filter_group_selected = this.options.filter_groups;
            this.selectGroup.hide();
        }
        this.selectGroup.css({position:'absolute','height':'1.8em','bottom':0});
        this.selectGroup.tabs();
        if(!window.hWin.HEURIST4.util.isempty(this.options.filter_group_selected)){
                this.selectGroup.tabs('option','active',this.options.filter_group_selected=='external'?1:0);
        }
        this.selectGroup.find('ul').css({'background':'none','border':'none'});
        this.selectGroup.css({'background':'none','border':'none'});
        
        this._on( this.selectGroup, { tabsactivate: this.startSearch  });
        
        //-----------------
        this.input_search_path = this.element.find('#input_search_path');
        this.input_search_type = this.element.find('#input_search_type');
        this.input_search_url =  this.element.find('#input_search_url');

        this.input_search_my = this.element.find('#input_search_my');
        this.input_sort_type =  this.element.find('#input_sort_type');
        
        this._on( this.input_search_url, { keypress: this.startSearchOnEnterPress });
        this._on( this.input_search_path, { keypress: this.startSearchOnEnterPress });
        this._on(this.input_search_my,  { change:this.startSearch });
        this._on(this.input_sort_type,  { change:this.startSearch });

        
        if(this.options.select_mode=='manager'){
            this.element.find('#input_search_type_div').css('float','left');
        }
        
        //this.btn_search_start.removeClass('ui-button-icon-only').css('margin-left','4em');
                      
        this.startSearch();   
        
        this.input_search.focus();         
        
        that._trigger( "oninit" );
    },  

    clearInputs: function(){
        this.input_search.val('');
        this.input_search_url.val('');
        this.input_search_path.val('');
        this.input_search_type.val('');
    },
    //
    // special case to show recently added record
    //
    searchRecent: function(domain){
        this.clearInputs();
        
        //this.input_search_recent.prop('checked', true);
        this.input_sort_type.val('recent');
        this.selectGroup.tabs('option','active',domain=='external'?1:0);
        this.startSearch();
    },
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            var domain = this.currentDomain();
            
            

            if(domain=='external'){

                this.input_search.parent().hide();
                this.input_search_path.parent().hide();
                this.input_search_url.parent().show();
                //this.element.find('span.local').hide();
                //this.element.find('span.external').show();
                this.element.find('.heurist-helper1 > .local').hide();
                this.element.find('.heurist-helper1 > .external').show();

                if(this.input_search_url.val()!=''){
                    request['ulf_ExternalFileReference'] = this.input_search_url.val();    
                }else{
                    request['ulf_ExternalFileReference'] = '-NULL';                        
                }
                
            }else{
                
                this.input_search_url.parent().hide();
                this.input_search.parent().show();
                this.input_search_path.parent().show();
                this.element.find('.heurist-helper1 > .local').show();
                this.element.find('.heurist-helper1 > .external').hide();
                
                request['ulf_ExternalFileReference'] = 'NULL';
                
                if(this.input_search_path.val()!=''){
                    request['ulf_FilePath'] = this.input_search_path.val();    
                }
                if(this.input_search.val()!=''){
                    request['ulf_OrigFileName'] = this.input_search.val();    
                }
            }
            if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
                    request['ulf_Parameters'] = this.input_search_type.val();    
            }
            
            if(this.input_search_my.is(':checked')){
                request['ulf_UploaderUGrpID'] = window.hWin.HAPI4.currentUser.ugr_ID; 
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='size'){
                request['sort:ulf_FileSizeKB'] = '-1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:ulf_Added'] = '-1' 
            }else{
                request['sort:ulf_OrigFileName'] = '-1';   
            }
            
            
            
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'id'; //'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
            }            
    },
    
    currentDomain:function(){
            var domain = this.selectGroup.tabs('option','active');
            return domain==1?'external':'local';
    },
    
    getUploadContainer:function(){
        return this.btn_add_record_inline; //element.find('#btn_add_record_loc');
    }
    

});
