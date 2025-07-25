/**
 * @file selectFile.js
 * @brief Widget to select image files or tiled images from various sources.
 *
 * @fileOverview Defines the `heurist.selectFile` jQuery UI widget.
 *               This widget provides a UI for selecting files, primarily images (for thumbnails/icons)
 *               or tiled image mbtiles from various sources like the image library, uploaded tile stacks,
 *               or specific archive folders. It can be displayed as a dialog or embedded in a page element.
 *               It uses an internal `resultList` widget to display selectable files.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * @class selectFile
 * @memberof Widgets.Editing
 * @description A jQuery UI widget for selecting files (images, mbtiles) from different sources.
 * It can operate as a dialog or be embedded. It features filtering and uses a `resultList`
 * for displaying files.
 *
 * @example
 * // Initialize as a dialog to select JPG images from assets
 * $('<div></div>').selectFile({
 *     isdialog: true,
 *     source: 'assets',
 *     extensions: 'jpg,jpeg',
 *     title: 'Select a JPG Image',
 *     onselect: function(fileDetails) {
 *         console.log('Selected file:', fileDetails.filename);
 *         // fileDetails: { filename: string, url: string, path: string }
 *     }
 * });
 */
$.widget( "heurist.selectFile", {

    /**
     * @type {object}
     * @instance
     * @memberof Widgets.Editing.selectFile
     * @description Default options for the selectFile widget.
     * @property {boolean} [showFilter='false'] - Whether to display a simple filter input field for filtering files by name.
     * @property {boolean} [is_dialog='true'] - If true, the widget will be displayed as a modal dialog. If false, it will be embedded within the element it's initialized on.
     * @property {function(Object):void|null} [onselect='null'] - Callback function executed when a file is selected. The function is called with the widget instance as `this` and one argument:
an object containing details of the selected file (`{ filename: string, url: string, path: string }`).
     * @property {string} [source='assets'] - Specifies the source from which to load files. Can be 'assets' (for general image library), 'uploaded_tilestacks',
or a numeric string representing the ID of an archive folder.
     * @property {string|null} [extensions=null] - comma separated list of allowed extensions
     * @property {string} [title='Select Image'] - The title for the dialog window
     * @property {boolean} [keep_dialogue='false'] - If true and `isdialog` is true the dialog will be just hidden on close
     */
    options: {
        showFilter: false, //simple filter by name
        isdialog: true, //show in dialog or embedded
        onselect: null,
        source:'assets', //or uploaded_tilestacks or id of archive folder
        extensions: null, //string comma separated list of exts
        title: 'Select Image',
        size: 64, //The size (height) for thumbnails when displaying image assets.
        keep_dialogue: false
    },
    
    /**
     * Reference to the jQuery UI dialog instance if `options.isdialog` is true.
     * @private
     * @type {jQuery|null}
     */
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    /**
     * Stores the last fetched HRecordSet of files. Used for client-side filtering if `options.showFilter` is true.
     * @private
     * @type {HRecordSet|null}
     */
    _cachedRecordset: null,
    /**
     * Flag indicating if the current `options.source` is an archive folder (parsed as an integer ID).
     * Set in `_init`.
     * @private
     * @type {boolean}
     */
    _is_archive_folder: false,
    /**
     * Flag used to indicate if `options.source` or `options.extensions` have changed via `_setOption`,
     * requiring a refetch of files by `_gettingFiles` when the dialog is next opened or shown.
     * @private
     * @type {boolean}
     */
    _is_source_changed: false,
    /**
     * Message displayed by the internal `resultList` widget when no files matching the criteria are found.
     * Set in `_init`.
     * @private
     * @type {string}
     */
    _emptyMessage: '',

    /**
     * Initializes the selectFile widget.
     * This is the constructor for the widget.
     * If `options.isdialog` is true and the dialog instance `_as_dialog` already exists,
     * it reopens the dialog, potentially refreshing data via `_gettingFiles()` if `_is_source_changed` is true.
     * Otherwise (if not a dialog or dialog doesn't exist yet), it sets up the widget's HTML structure
     * within `this.element`. This includes a filter input if `options.showFilter` is true.
     * It then initializes the internal `resultList` widget with a custom renderer and configuration,
     * binds to its `resultlistonselect` event to trigger `options.onselect`,
     * and finally calls `_gettingFiles()` to fetch and display the initial list of files.
     * @private
     */
    _init: function() {

        if(this.options.isdialog && this._as_dialog){ // If dialog exists and widget is in dialog mode
            
            if(this._is_source_changed){ // If source/extensions changed, refetch
                this._gettingFiles();    
            }else{ // Otherwise, just reopen existing dialog
                this._as_dialog.dialog('open');
            }
            return; // Initialization was for reopening dialog
        }
        
        let that = this; // For use in closures
        
        let sFilter = ''; // HTML string for filter input
        
        if(this.options.showFilter){
            sFilter = '<div class="ent_header">'
            +'<div class="header4" style="display: inline-block;width:7em;text-align:right;">'+window.hWin.HR('Find')+'&nbsp;&nbsp;</div>'
            +'<input class="input_search text ui-widget-content ui-corner-all" style="width:90px; margin-right:0.2em" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false">'
            +'</div>';
        }

        // Append basic structure to the widget element
        $('<div class="ent_wrapper">'
                +sFilter
                +'<div class="ent_content_full recordList"></div>'
                +'</div>').appendTo( this.element );

        if(this.options.showFilter){
            this._on(this.element.find('.input_search'),  { keyup:this.filterRecordList }); // Bind filter input
        }else{
            this.element.find('.recordList').css('top',0); // Adjust layout if no filter
        }
        
        // Determine if source is an archive folder based on whether it's a number
        this._is_archive_folder = parseInt(this.options.source)>0;

        // Set message for when no files are found
        this._emptyMessage = `Specified files (${this.options.extensions}) are not found in `
            +(this._is_archive_folder?'given folder':this.options.source);
        
        // Initialize the resultList widget for displaying files
        this.recordList = this.element.find('.recordList');
        this.recordList
                    .resultList({ // Options for resultList
                       recordDivEvenClass: 'recordDiv_blue',
                       eventbased: false, // Does not listen to global events
                       multiselect: false, // Single selection mode

                       select_mode: 'select_single',
                       show_toolbar: true, // Show resultList's toolbar
                       show_viewmode: false, // Hide resultList's view mode switcher
                       
                       
                       entityName: 'files', // Entity name for resultList context
                       view_mode: this._is_archive_folder?'list':'thumbs', // View mode depends on source type
                       
                       pagesize: 500, // Number of items per page
                       
                       empty_remark: this._emptyMessage, // Message when no files
                       renderer: function(recordset, record){ // Custom renderer for each file item
                           
                           let recID   = recordset.fld(record, 'file_id');
                           let recThumb;
                           if(recordset.fld(record, 'file_url')){ // If URL is provided directly
                                recThumb = recordset.fld(record, 'file_url')+recordset.fld(record, 'file_name');    
                           }else{ // Construct URL from base, dir, and name
                                recThumb = window.hWin.HAPI4.baseURL 
                                            + recordset.fld(record, 'file_dir')
                                            + recordset.fld(record, 'file_name');
                           }
                           
                           let html;
        
                           if(that.options.source.indexOf('assets')<0) { // Non-asset sources (e.g., uploaded_tilestacks, archive folders)
                               
                               let sz = (that.options.extensions=='zip') // Display size in MB for zips, KB otherwise
                               ? Math.round(recordset.fld(record, 'file_size')/1024/1024)+'MB'
                               : Math.round(recordset.fld(record, 'file_size')/1024)+'KB';

                               if(that._is_archive_folder){ // List view for archive folders
                                   html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                                   + '" style="height:20px !important"><p style="margin-top: 4px;">'
                                   + recordset.fld(record, 'file_name')+'<span style="float:right">'
                                   + sz+'</span></p></div>';
                               }else{ // Different list view for other non-asset sources
                                   html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                                   + '" style="width:250px !important;height:50px !important"><p>'
                                   + recordset.fld(record, 'file_name')+'</p>size: '
                                   + sz+'</div>';
                               }
                               
                           }else{ // Asset source (typically images, show thumbnails)

                               let html_thumb = '<div class="recTypeThumb" style="top:0px !important;background-image: url(&quot;'
                               +recThumb+'&quot;);opacity:1;height:'+that.options.size+'px !important">'
                               +'</div>';

                               html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                               + '" style="width:'+(that.options.size+4)+'px !important;height:'
                               + (that.options.size+4)+'px !important">' // Adjust div size based on option.size
                               + html_thumb + '</div>';
                           }
                           return html;  
                       }
                    });     

                // Bind to the custom 'resultlistonselect' event from the resultList widget
                this._on( this.recordList, {        
                        "resultlistonselect": function(event, selected_recs){ // selected_recs is an HRecordSet
                            
                                    let recordset = selected_recs;
                                    let record = recordset.getFirstRecord(); // Get the first (and only in single select) record
                                    let filename = recordset.fld(record, 'file_name')
                                    // Prepare file details object for the onselect callback
                                    let res = { filename: filename,
                                                url:recordset.fld(record, 'file_url')+filename, // Full URL if available
                                                path:recordset.fld(record, 'file_dir')+filename}; // Path relative to Heurist data

                                    that.options.onselect.call(that, res); // Call the user-provided onselect callback
                                    if(that._as_dialog){ // If in dialog mode, close the dialog after selection
                                        that._as_dialog.dialog('close');
                                    }
                                }
                        });        
         
                this._gettingFiles(); // Initial fetch of files
         
    }, //end _init

    /**
     * Cleans up the widget instance upon removal.
     * Removes the internally created `recordList` element and the dialog element (`_as_dialog`)
     * if it exists. This is part of the standard jQuery UI widget lifecycle.
     * @private
     */
    _destroy: function() {
        // remove generated elements
        this.recordList.remove();
        // Note: _as_dialog is removed in its own 'close' handler if it exists.
    },
    
    /**
     * Sets an option value after widget initialization.
     * This is a standard jQuery UI widget lifecycle method.
     * It specifically handles changes to `extensions` and `source` options by setting the
     * `_is_source_changed` flag to `true` if the new value differs from the current one.
     * This flag prompts a data reload by `_gettingFiles()` when the widget is next shown or interacted with.
     * @private
     * @param {string} key - The name of the option to set.
     * @param {any} value - The new value for the option.
     */
    _setOption: function( key, value ){
        if(key==='extensions'){
            if(this.options.extensions!=value){
                this.options.extensions = value;
                this._is_source_changed = true; // Flag that source data needs refresh
            }
        }else if(key==='source'){
            if(this.options.source!=value){
                this.options.source = value;
                this._is_source_changed = true; // Flag that source data needs refresh
            }
        }
    },
    
    /**
     * @memberof Widgets.Editing.selectFile
     * @instance
     */
    open: function(){
        this._init();  
    },
    
    /**
     * Filters the displayed file list in the `recordList` widget based on the value
     * entered in the search input field (`.input_search`).
     * This method is typically called as a 'keyup' event handler.
     * It performs a client-side filter on the `_cachedRecordset` by matching filenames
     * and then updates the `recordList` with the filtered subset.
     * 
     * @memberof Widgets.Editing.selectFile
     * @private
     * @param {jQuery.Event} event - The keyup event object from the filter input field.
     */
    filterRecordList: function(event){
        
        let val = this.element.find('.input_search').val().trim(); // Get search term
        let subset;
        if(val==''){ // If search term is empty, show all cached records
            subset = this._cachedRecordset;
        }else{ // Otherwise, filter the cached recordset
            subset = this._cachedRecordset.getSubSetByRequest({'file_name':val}, null);
        }
            
        this.recordList.resultList('updateResultSet', subset); // Update the resultList display
    },
    
    /**
     * Fetches file data from the server using `window.hWin.HAPI4.SystemMgr.get_foldercontent`.
     * It uses `options.source` (e.g., 'assets', 'uploaded_tilestacks', or a folder ID)
     * and `options.extensions` (comma-separated list) to make the server request.
     *
     * While fetching, it displays a loading indicator.
     * On a successful response (`response.status == window.hWin.ResponseStatus.OK`):
     *  - Resets the `_is_source_changed` flag.
     *  - Creates an `HRecordSet` from the `response.data`.
     *  - If files are found in the recordset:
     *    - If `options.isdialog` is true:
     *      - If `_as_dialog` (the dialog instance) exists, it's opened.
     *      - Otherwise, a new jQuery UI dialog is created using `this.element` as its content,
     *        configured with `options.title` and other dialog settings. The dialog instance
     *        is stored in `_as_dialog`. The `close` handler for this new dialog checks `options.keep_dialogue`
     *
     *    - The fetched recordset is cached in `_cachedRecordset`.
     *    - The internal `recordList` widget is updated with the new recordset.
     *  - If no files are found, the dialog (if open) is closed, and a flash message
     *    (using `_emptyMessage`) is shown.
     * On a failed response, the dialog (if open) is closed, and an error message is shown.
     * @private
     */
    _gettingFiles: function(){
        
            let that = this; // For use in closures
            
            // Show loading indicator
            window.hWin.HEURIST4.msg.bringCoverallToFront(null, {opacity: '0.3'}, window.hWin.HR('Getting files...'));
            $('body').css('cursor','progress');
       
            // API call to get folder content
            window.hWin.HAPI4.SystemMgr.get_foldercontent(this.options.source, this.options.extensions,
                function(response){ // Async callback
                    $('body').css('cursor','auto'); // Restore cursor
                    window.hWin.HEURIST4.msg.sendCoverallToBack(true); // Hide loading indicator
                    
                    if(response.status == window.hWin.ResponseStatus.OK){ // If request was successful
                        
                        that._is_source_changed = false; // Reset flag
                        let recset = new HRecordSet(response.data); // Create HRecordSet from response
                        if(recset.length()>0){ // If files were returned
                            
                            if(that.options.isdialog){ // If in dialog mode
                                
                                if(that._as_dialog){ // If dialog instance already exists
                                    that._as_dialog.dialog('open');
                                }else{ // Create new dialog instance
                                    let $dlg = that.element.dialog({
                                        autoOpen: true,
                                        height: 640,
                                        width: 840,
                                        modal: true,
                                        title: window.hWin.HR(that.options.title),
                                        resizeStop: function( event, ui ) { // Adjust size on resize
                                            let pele = that.element.parents('div[role="dialog"]');
                                            that.element.css({overflow: 'none !important', width:pele.width()-24 });
                                        },
                                        close:function(){ // Dialog close behavior
                                            if(that.options.keep_dialogue){
                                                that._as_dialog.dialog('close');
                                            }else{
                                                that._as_dialog.remove();        
                                            }
                                        }
                                    });
                                    that._as_dialog = $dlg; // Store dialog instance
                                }
                            }
                            
                            that._cachedRecordset = recset; // Cache the fetched data
                            
                            that.recordList.resultList('updateResultSet', recset); // Update the resultList
                        }else{ // No files found
                            if(that._as_dialog) that._as_dialog.dialog('close'); // Close dialog if open
                            window.hWin.HEURIST4.msg.showMsgFlash(that._emptyMessage); // Show empty message
                        }

                    }else{ // API request failed
                        if(that._as_dialog) that._as_dialog.dialog('close'); // Close dialog if open
                        window.hWin.HEURIST4.msg.showMsgErr(response); // Show error message
                    }
                });
    }
});
