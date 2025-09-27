/**
 * @file        searchRecUploadedFiles.js
 * @brief       Provides a search interface for Uploaded Files associated with records.
 * @fileOverview This widget handles the search functionality for files uploaded to records, allowing users to find specific files based on various criteria like name, path, type, and domain (local, external, tiled).
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchRecUploadedFiles
 * @brief Search widget for Uploaded Files.
 * @augments $.heurist.searchEntity
 * @description This widget provides a comprehensive interface for searching and managing uploaded files
 *              associated with records. It supports filtering by file domain (local, external, tiled),
 *              MIME types, and various other attributes. It also includes action menus for file operations.
 *
 * @property {string} [edit_mode='none'] Defines the editing capabilities available in the UI (e.g., 'inline', 'dialog', 'none').
 *           This impacts the visibility and behavior of add/edit buttons and menus. Inherited from `searchEntity` but its usage is prominent here.
 * @property {?string} filter_groups A comma-separated string defining the available file domains (e.g., 'local,external,tiled') for filtering via tabs.
 * @property {?string} filter_group_selected The default file domain (e.g., 'local') to be active when the widget loads.
 * @property {?string} filter_types A pre-selected MIME type value to filter the search results. If set, the MIME type filter UI might be hidden.
 * @property {?number} rec_ID The ID of the record for which files are being managed. While not directly used in the `startSearch`
 *           request construction by this widget, it is a crucial contextual option typically set by the parent manager
 *           (e.g., `manageRecUploadedFiles`) to scope file operations and listings to a specific record.
 *
 * @listens heurist.searchRecUploadedFiles#onaction - Fired when an action is selected from one of the dynamic menus (e.g., add file, delete selected).
 *          Event data: `{string}` The ID of the selected menu action.
 * @listens heurist.searchRecUploadedFiles#oninit - Fired when the widget's `_initControls` method has completed its setup.
 *          No parameters.
 */
$.widget( "heurist.searchRecUploadedFiles", $.heurist.searchEntity, {

    /**
     * @brief Initializes the UI controls for the uploaded files search widget.
     * @override
     * @memberof heurist.searchRecUploadedFiles
     * @description Sets up various UI elements including action buttons (add local/external file, edit mimetypes),
     *              a dynamic context menu (`#btn_menu`), domain selection tabs (`#sel_group`),
     *              and multiple input fields for filtering by path, type, reference status, URL, ownership, and sort order.
     *              Adjusts UI elements based on `edit_mode`, `select_mode`, and admin status.
     *              Triggers an initial search and an "oninit" event upon completion.
     */
    _initControls: function() {
        this._super();
        
        let that = this;
        
        
        this.btn_add_record_loc = this.element.find('#btn_add_record_loc');
        this.btn_add_record_ext = this.element.find('#btn_add_record_ext');
        this.btn_edit_mimetypes = this.element.find('#btn_edit_mimetypes');

        if(this.options.edit_mode=='none' || (this.options.select_mode != 'manager' && this.options.select_mode!='select_single')){
            this.element.find('#div_add_record').hide();
        }else if(this.options.select_mode=='select_single'){
            
            this.element.find('#btn_menu').buttonsMenu({
                menuContent:
                    '<div>'
                    +'<ul id="menu-file-add-local" link-style="background:#ededed" title="Select file to upload" data-icon="ui-icon-plus"></ul>'
                    +'<ul id="menu-file-add-ext" link-style="background:#ededed" title="Select external file/URL" data-icon="ui-icon-plus"></ul>'
                    +'</div>',
                manuActionHandler:function(action){
                    that._trigger('onaction', null, action);   
                }
            });
            
        }else{
            this.btn_edit_mimetypes
                    .button({label: window.hWin.HR("Define mime types"),icon:'ui-icon-pencil'})
                .on('click', function(e) {
                    window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                                                {edit_mode:'inline', width:900});
                }); 

            this.element.find('#btn_menu').buttonsMenu({
                menuContent:
                    '<div>'
                    +'<ul id="menu-file-add-local" link-style="background:#ededed" title="Select file to upload" data-icon="ui-icon-plus"></ul>'
                    +'<ul id="menu-file-add-ext" link-style="background:#ededed" title="Select external file/URL" data-icon="ui-icon-plus"></ul>'
                    +'<ul id="menu-file-import-csv" link-style="background:#ededed" title="Import file data from CSV" data-icon="ui-icon-file-table"></ul>'
                    +'<ul title="Selected" link-style="width:100px" style="margin-left:150px">'
                    +'<li id="menu-file-select-all"><a href="#">Select All</a></li>'
                    +'<li id="menu-file-select-none"><a href="#">Select None</a></li>'
                    +'<li>---------------</li>'
                    +'<li id="menu-file-scaled-images"><a href="#">Optimise image sizes for selection</a></li>'
                    +'<li id="menu-file-export-csv-essential"><a href="#">Download CSV (essential info) for selection</a></li>'
                    +'<li id="menu-file-export-csv"><a href="#">Download CSV (all info) for selection</a></li>'
                    +'<li id="menu-file-refrec-show"><a href="#">Show records referencing selection</a></li>'
                    +'<li id="menu-file-refrec-localremote"><a href="#">Transfer local files to/from remote</a></li>'
                    +'<li id="menu-file-refrec-add"><a href="#">Create multimedia records for selection</a></li>'
                    +'<li id="menu-file-delete-selected"><a href="#">Delete files in selection</a></li>'
                    +'</ul>'
                    +'<ul title="Integrity" link-style="width:100px">'
                    +'<li id="menu-file-merge-dupes"><a href="#">Combine duplicates</a></li>'
                    +'<li id="menu-file-refresh-index"><a href="#">Refresh index</a></li>'
                    +'<li id="menu-file-check-files"><a href="#">Check files</a></li></ul></div>',
                manuActionHandler:function(action){
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
        this.input_search_referenced = this.element.find('#input_search_referenced');
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
        
        this.input_search.trigger('focus');         
        
        that._trigger( "oninit" );
    },  

    /**
     * @brief Clears the content of various search input fields.
     * @memberof heurist.searchRecUploadedFiles
     */
    clearInputs: function(){
        this.input_search.val('');
        this.input_search_url.val('');
        this.input_search_path.val('');
        this.input_search_type.val('');
        this.input_search_referenced.val('');
    },

    /**
     * @brief Initiates a search specifically for recently added files.
     * @memberof heurist.searchRecUploadedFiles
     * @param {string} [domain] Optional. The file domain ('local', 'external', 'tiled') to filter by.
     *                          If provided, the domain tabs will be switched accordingly.
     * @description Clears existing search inputs, sets the sort type to 'recent',
     *              optionally switches to the specified domain, and then calls `startSearch`.
     */
    searchRecent: function(domain){
        this.clearInputs();
        
       
        this.input_sort_type.val('recent');

        if(!window.hWin.HEURIST4.util.isempty(domain)){
            this.selectGroup.tabs('option','active',(domain=='tiled')?2:(domain=='external'?1:0));
        }

        this.startSearch();
    },
    
    /**
     * @brief Initiates a search for uploaded files based on the current UI selections.
     * @override
     * @memberof heurist.searchRecUploadedFiles
     * @description Constructs a search request object based on the active domain tab (local, external, tiled)
     *              and the values in various input fields (path, original filename, URL, MIME type,
     *              referenced status, uploader, sort order). It then populates `this._search_request`
     *              and calls the parent `startSearch` method.
     */
    startSearch: function(){
        
            let request = {}
        
            let domain = this.currentDomain();
            
            if(domain=='tiled'){
                
                request['ulf_OrigFileName'] = '_tiled';                        

            }else if(domain=='external'){

                this.input_search.parent().hide();
                this.input_search_path.parent().hide();
                this.input_search_url.parent().show();
               
               
                this.element.find('.heurist-helper1 > .local').hide();
                this.element.find('.heurist-helper1 > .external').show();

                if(this.input_search_url.val()!=''){
                    request['ulf_ExternalFileReference'] = this.input_search_url.val();    
                }else{
                    request['ulf_ExternalFileReference'] = '-NULL';                        
                }
                request['ulf_OrigFileName'] = '-_tiled';
            }
            else{ // local
                
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
            if(this.input_search_referenced.val()!='' && this.input_search_referenced.val()!='both'){
                    request['ulf_Referenced'] = this.input_search_referenced.val();  
            }
            
            if(this.input_search_my.is(':checked') || !window.hWin.HAPI4.is_admin()){
                request['ulf_UploaderUGrpID'] = window.hWin.HAPI4.currentUser.ugr_ID; 
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='size'){
                request['sort:ulf_FileSizeKB'] = '-1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:ulf_Added'] = '-1' 
            }else{ // name
                request['sort:ulf_OrigFileName'] = '-1';   
            }
            
            this._search_request = request;
            this._super();
    },
    
    /**
     * @brief Determines the current search domain based on the active tab in the group selector.
     * @memberof heurist.searchRecUploadedFiles
     * @returns {string} The current domain, which can be 'local', 'external', or 'tiled'.
     */
    currentDomain:function(){
            let domain = this.selectGroup.tabs('option','active');
            return domain==1?'external':((domain==2)?'tiled':'local');
    },
    
    /**
     * @brief Returns the jQuery element intended as a container for inline file uploads.
     * @memberof heurist.searchRecUploadedFiles
     * @returns {jQuery} The jQuery object for the upload container element.
     * @description This is likely used by the parent manager widget (`manageRecUploadedFiles`)
     *              to integrate with a file uploader. `btn_add_record_inline` is expected to be defined in the HTML template.
     */
    getUploadContainer:function(){
        return this.btn_add_record_inline;
    }

});
