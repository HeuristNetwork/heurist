/**
* @file recordImportAnnotations.js
* @brief Import annotations from registered IIIF manifests.
* @fileOverview This file defines the `recordImportAnnotations` widget. It provides functionality to
* import annotations from IIIF (International Image Interoperability Framework) manifests that have
* been registered within the Heurist system. The widget typically interacts with a server-side
* controller to process these manifests, create or update annotation records, and report on the
* import process (e.g., total processed, added, updated, missed, issues).
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
 * @widget heurist.recordImportAnnotations
 * @extends $.heurist.recordAction
 * @description jQuery widget for importing annotations from registered IIIF manifests.
 * This widget provides a user interface to trigger the import process for annotations
 * associated with IIIF manifests stored in the system. It communicates with a server-side
 * controller to fetch and process these annotations, and then displays a report
 * summarizing the import results.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=780] - The height of the dialog.
 * @param {number} [options.width=800] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.title='Import annotations from registered IIIF manifests'] - Title for the dialog.
 * @param {string} [options.htmlContent='recordImportAnnotations'] - Base name for the HTML file ('.html' will be appended).
 */
$.widget( "heurist.recordImportAnnotations", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordImportAnnotations
     * @type {object}
     * @property {number} [height=780] - Dialog height.
     * @property {number} [width=800] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [title='Import annotations from registered IIIF manifests'] - Dialog title.
     * @property {string} [htmlContent='recordImportAnnotations'] - Base name for HTML content file.
     */
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Import annotations from registered IIIF manifests',
        
        htmlContent: 'recordImportAnnotations'
    },
    
    /**
     * @function _init
     * @memberof heurist.recordImportAnnotations
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
     * @memberof heurist.recordImportAnnotations
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Makes action buttons jQuery UI buttons and attaches click handlers.
     * Calls the parent widget's `_initControls` method.
     * @returns {boolean|undefined} Value returned by parent's `_initControls`.
     */
    _initControls:function(){
        
        this._$('.btnAction').button();
        
        this._on(this._$('.btnAction'), {click:this.doAction});
        
        return this._super();
    },
    
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordImportAnnotations
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
     * @memberof heurist.recordImportAnnotations
     * @private
     * @description Renders the import report in the UI using data received from the server.
     * Displays counts for total, processed, missed, added, updated, and retained annotations,
     * as well as a list of any issues encountered, with links to relevant records.
     * @param {object} data - The report data from the server. Expected to contain keys like
     *                        'total', 'processed', 'missed', 'without_annotations', 'added',
     *                        'updated', 'retained', and 'issues'.
     */
    _renderReport: function(data)
    {
        this._$('#div_header').hide();
        this._$('#div_result').show();
        
        this._$('#total').text( data['total'] );
        this._$('#processed').text( data['processed'] );
        this._$('#missed').text( data['missed'] );
        
        let link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:';
        
        let ids = [];
        let s = ' ';
        
        for (const ulfID in data['without_annotations']) {
           ids.push(data['without_annotations'][ulfID]); 
        }
        if(ids.length>0){
           s = `<a href="${link+ids.join(',')}" target="_blank">${ids.length} <span class="ui-icon ui-icon-extlink">&nbsp;</span></a><br>`;
        }
        this._$('#without_annotations').html( s);

        
        if(data['added'].length>0){
            this._$('#added').html( `<a href="${link+data['added'].join(',')}" target="_blank">${data['added'].length}</a>` );
        }else{
            this._$('#added').text( '0' );
        }
        
        if(data['updated'].length>0){
            this._$('#updated').html( `<a href="${link+data['updated'].join(',')}" target="_blank">${data['updated'].length}</a>` );
        }else{
            this._$('#updated').text( '0' );
        }

        if(data['retained'].length>0){
            this._$('#retained').html( `<a href="${link+data['retained'].join(',')}" target="_blank">${data['retained'].length}</a>` );
        }else{
            this._$('#retained').text( '0' );
        }
        
        s = ' ';
        ids = [];
        for (const recID in data['issues']) {
           s = s +  `<a href="${link+recID}" target="_blank">${data['issues'][recID]}</a><br>`;
           ids.push(recID);
        }
        if(ids.length>1){
           s = s + `<a href="${link+ids.join(',')}" target="_blank">all issues ( ${ids.length} ) <span class="ui-icon ui-icon-extlink">&nbsp;</span></a><br>`;
        }
        
        this._$('#issues').html( s );
        
    },
        
    /**
     * @function doAction
     * @memberof heurist.recordImportAnnotations
     * @private
     * @description Performs the annotation import action.
     * Constructs a request to a server-side controller ('ImportAnnotations').
     * Includes options like whether to create direct links or thumbnails.
     * Shows progress and calls `_renderReport` with the server response.
     */
    doAction: function(){

            let request = {
                db: window.hWin.HAPI4.database,
                controller: 'ImportAnnotations',
                session  : window.hWin.HEURIST4.msg.showProgress(),
                direct_link: this._$('#chb_direct_link').is(':checked')?1:0,
                create_thumb: this._$('#chb_create_thumbs').is(':checked')?1:0
            };
            
            let url = window.hWin.HAPI4.baseURL;
            
            let that = this;

            window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.hideProgress();
                
                //render groups
                if(response.status == window.hWin.ResponseStatus.OK){
                    that._renderReport( response.data );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);    
                }
            });
        
                           
            
    }
  
});

