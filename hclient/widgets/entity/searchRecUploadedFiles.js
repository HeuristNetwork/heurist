/**
* Search header for Record Uploaded Files manager
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

$.widget( "heurist.searchRecUploadedFiles", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        let that = this;
        
        
        this.btn_add_record_loc = this.element.find('#btn_add_record_loc');
        this.btn_add_record_ext = this.element.find('#btn_add_record_ext');
        this.btn_edit_mimetypes = this.element.find('#btn_edit_mimetypes');


        if(this.options.edit_mode=='none'){
            this.element.find('#div_add_record').hide();
        }else{
            this.btn_edit_mimetypes
                    .button({label: window.hWin.HR("Define mime types"),icons: {
                            primary: "ui-icon-pencil"
                    }})
                .on('click', function(e) {
                    window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                                                {edit_mode:'inline', width:900});
                }); 

            this.element.find('#btn_menu').buttonsMenu({
                menuContent:
                    '<div>'
                    +'<ul id="menu-file-add-local" name="Select file to upload" data-icon="ui-icon-plus"></ul>'
                    +'<ul id="menu-file-add-ext" name="Select external file/URL" data-icon="ui-icon-plus"></ul>'
                    +'<ul name="Selected">'
                    +'<li id="menu-file-select-all"><a href="#">Select All</a></li>'
                    +'<li id="menu-file-select-none"><a href="#">Select None</a></li>'
                    +'<li id="menu-file-refrec-show"><a href="#">Show referenced records</a></li>'
                    +'<li id="menu-file-export-csv"><a href="#">Download CSV of file references</a></li>'
                    +'<li id="menu-file-refrec-add"><a href="#">Create multimedia record</a></li>'
                    +'<li id="menu-file-delete-selected"><a href="#">Delete</a></li>'
                    +'</ul>'
                    +'<ul name="Integrity">'
                    +'<li id="menu-file-merge-dupes"><a href="#">Combine duplicates</a></li>'
                    +'<li id="menu-file-refresh-index"><a href="#">Refresh index</a></li>'
                    +'<li id="menu-file-check-files"><a href="#">Check files</a></li></ul></div>',
                actionHandler:function(action){
                    that._trigger('onaction', null, action);   
                }
            });
                
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
                let grp_idx = 0;
                if(this.options.filter_group_selected=='external'){
                    grp_idx = 1;
                }else if(this.options.filter_group_selected=='tiled'){
                    grp_idx = 2;
                }
                this.selectGroup.tabs('option','active',grp_idx);
        }
        this.selectGroup.find('ul').css({'background':'none','border':'none'});
        this.selectGroup.css({'background':'none','border':'none'});
        
        this._on( this.selectGroup, { tabsactivate: this.startSearch  });

        this.element.find('#group_help').position({
            my: 'left+35 top', at: 'right center', of: this.selectGroup
        });
        
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

        
        if(!window.hWin.HEURIST4.util.isempty(this.options.filter_types)){
            this.input_search_type.val(this.options.filter_types);
            this.element.find('#input_search_type_div').hide();
        }        
        
        if(this.options.select_mode=='manager'){

            this.element.find('#input_search_type_div').css('float','left');

            if(!window.hWin.HAPI4.is_admin()){
                this.element.find('.admin-only').hide().off('click'); // hide and remove functions 
                this.input_search_my.hide().prop('checked', true);
            }else{
                this.element.find('.admin-only').show();
                this.input_search_my.show();
            }

            this.input_sort_type.val('name');

        }else{
            this.element.find('.manager-only').hide().off('click'); // hide and remove functions

            this.input_sort_type.val('recent');
        }

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

        if(!window.hWin.HEURIST4.util.isempty(domain)){
            this.selectGroup.tabs('option','active',(domain=='tiled')?2:(domain=='external'?1:0));
        }

        this.startSearch();
    },
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            let request = {}
        
            let domain = this.currentDomain();
            
            if(domain=='tiled'){
                
                request['ulf_OrigFileName'] = '_tiled';                        

            }else if(domain=='external'){

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
                request['ulf_OrigFileName'] = '-_tiled';
            }
            else{
                
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
            //it does not search actually for this field  - it searches for mimetype
            if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
                    request['fxm_MimeType'] = this.input_search_type.val();  
            }
            
            if(this.input_search_my.is(':checked') || !window.hWin.HAPI4.is_admin()){
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
            
            
            
            this._trigger( "onstart" );
    
            request['a']          = 'search'; //action
            request['entity']     = this.options.entity.entityName;
            request['details']    = 'id'; //'id';
            request['request_id'] = window.hWin.HEURIST4.util.random();
            
            let that = this;                                                
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that._trigger( "onresult", null, 
                            {recordset:new HRecordSet(response.data), request:request} );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                    
    },
    
    currentDomain:function(){
            let domain = this.selectGroup.tabs('option','active');
            return domain==1?'external':((domain==2)?'tiled':'local');
    },
    
    getUploadContainer:function(){
        return this.btn_add_record_inline; //element.find('#btn_add_record_loc');
    }

});
