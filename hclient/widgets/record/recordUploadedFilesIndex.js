/**
* @file recordUploadedFilesIndex.js
* @brief Registers files in specified server media folders into the `recUploadedFiles` entity.
* @fileOverview This file defines the `recordUploadedFilesIndex` widget. It provides a user interface
* to register files located in specified server media folders (like 'file_uploads/' or other
* configured media folders) into the Heurist `recUploadedFiles` entity. This process makes Heurist
* aware of these files, allowing them to be linked to records and managed within the system. The
* widget displays a report of the indexing process.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordUploadedFilesIndex
 * @extends $.heurist.recordAction
 * @description jQuery widget for indexing uploaded files from server media folders
 * into the `recUploadedFiles` entity. Users can select media folders, and the widget
 * initiates a batch process to register files found in those folders.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=500] - The height of the dialog/widget area.
 * @param {number} [options.width=800] - The width of the dialog/widget area.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.title='Index uploaded files / external transfers'] - Title for the dialog.
 * @param {string} [options.htmlContent='recordUploadedFilesIndex'] - Base name for the HTML file ('.html' will be appended).
 * @param {boolean} [options.isdialog=true] - Indicates if the widget is presented as a dialog.
 */
$.widget( "heurist.recordUploadedFilesIndex", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordUploadedFilesIndex
     * @type {object}
     * @property {number} [options.height=500] - Widget/dialog height.
     * @property {number} [options.width=800] - Widget/dialog width.
     * @property {boolean} [options.modal=true] - Is dialog modal.
     * @property {string} [options.title='Index uploaded files / external transfers'] - Dialog title.
     * @property {string} [options.htmlContent='recordUploadedFilesIndex'] - Base name for HTML content file.
     * @property {boolean} [options.isdialog=true] - True if displayed as a dialog (controls button visibility).
     */
    options: {
    
        height: 500,
        width:  800,
        modal:  true,
        title:  'Index uploaded files / external transfers',
        
        htmlContent: 'recordUploadedFilesIndex'
    },
    
    /**
     * @function _init
     * @memberof heurist.recordUploadedFilesIndex
     * @private
     * @description Initializes the widget. Appends '.html' to `options.htmlContent`.
     * Calls the parent widget's `_init` method.
     */
    _init: function() {
        this.options.htmlContent = this.options.htmlContent+'.html';
                    //+(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')
        this._super();    
    },
    
    /**
     * @function _initControls
     * @memberof heurist.recordUploadedFilesIndex
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Populates a dropdown (`#mediafolders`) with available media folders from `HAPI4.sysinfo`.
     * Displays allowed media extensions.
     * Adjusts UI elements based on whether the widget is in dialog mode (`options.isdialog`).
     * Calls the parent widget's `_initControls` method.
     * @returns {boolean|undefined} Value returned by parent's `_initControls`.
     */
    _initControls:function(){
        
        //fill media folders and exts
        let folders = window.hWin.HAPI4.sysinfo['mediaFolder'];
        folders = folders.split(';');
        folders.unshift('file_uploads');
        folders.unshift('all');        
        window.hWin.HEURIST4.ui.createSelector(this._$('#mediafolders').get(0), folders);
        
        this._$('#mediaexts').text(window.hWin.HAPI4.sysinfo['media_ext_index'] ?? window.hWin.HAPI4.sysinfo['media_ext']);
        
        if(this.options.isdialog)
        { 
            this._$('#div_result').css('margin-top',0);
            this._$('.btnAction').hide();
            window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), false );  
        }else{
            
            this._$('.btnAction').button();
            this._on(this._$('.btnAction'), {click:this.doAction});           
        }    

        
        return this._super();
    },
    
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordUploadedFilesIndex
     * @private
     * @description Gets action buttons for the dialog, setting labels to 'Proceed' and 'Close'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Proceed');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    

    /**
     * @function _renderReport
     * @memberof heurist.recordUploadedFilesIndex
     * @private
     * @description Renders the report from the file indexing process in the UI.
     * Displays the raw HTML data received from the server in the `#div_result_msg` element.
     * @param {string} data - The HTML report string from the server.
     */
    _renderReport: function(data)
    {
        this._$('#div_header').hide();
        this._$('#div_result').show();
        this._$('#div_result_msg').html( data );
    },
        
    /**
     * @function doAction
     * @memberof heurist.recordUploadedFilesIndex
     * @private
     * @description Performs the file indexing action.
     * Retrieves the selected media folders.
     * Constructs a batch request to the `EntityMgr` for the `recUploadedFiles` entity
     * with `bulk_reg_filestore: 1` to trigger the indexing.
     * Shows progress and calls `_renderReport` with the server response or displays an error/flash message.
     */
    doAction: function(){
        
            const selected_folders = this._$('#mediafolders').val();
        
            let request = {
                'a': 'batch',
                'entity': 'recUploadedFiles',
                'request_id': window.hWin.HEURIST4.util.random(),
                'folders': selected_folders,
                'bulk_reg_filestore': 1
            };
            
            let that = this;

            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(window.hWin.HEURIST4.util.isempty(response.data)){
                        window.hWin.HEURIST4.msg.showMsgFlash('No new files to index', 3000);
                    }else{
                        that._renderReport( response.data );
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    }
  
});

