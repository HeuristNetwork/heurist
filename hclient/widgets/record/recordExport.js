/**
* @file recordExport.js
* @brief Provides a widget for exporting records to various formats like XML, JSON, KML, or HML.
* @fileOverview This file defines the `recordExport` widget. It allows users to export record data
* from the Heurist system into different structured formats. The widget typically takes the current
* record set (or a selection) and constructs a URL or a form post to a server-side script that
* generates the export file (e.g., XML, JSON, KML, HML). Options for including linked records and
* definitions can be configured.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordExport
 * @augments $.heurist.recordAction
 * @description jQuery widget for exporting records to various formats (XML, JSON, KML, HML).
 * This widget prepares and initiates a download of record data based on the current
 * recordset and selected export format.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=780] - The height of the dialog/widget area.
 * @param {number} [options.width=800] - The width of the dialog/widget area.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal (if applicable).
 * @param {string} [options.title='Export records to '] - Base title for the dialog; format is appended.
 * @param {string} [options.format='xml'] - The export format (e.g., 'xml', 'json', 'kml', 'hml', 'iiif').
 * @param {string} [options.htmlContent='recordExport.html'] - The HTML file for the widget's content.
 * @param {boolean} [options.isdialog=true] - Indicates if the widget is presented as a dialog. If false, it might render inline and create its own toolbar.
 */
$.widget( "heurist.recordExport", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordExport
     * @type {object}
     * @property {number} [height=780] - Widget/dialog height.
     * @property {number} [width=800] - Widget/dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [title='Export records to '] - Base dialog title.
     * @property {string} [options.format='xml'] - Export format (e.g., 'xml', 'json', 'kml', 'hml', 'iiif').
     * @property {string} [htmlContent='recordExport.html'] - HTML content file.
     * @property {boolean} [isdialog=true] - True if displayed as a dialog.
     */
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Export records to ',
        
        format: 'xml',
        
        htmlContent: 'recordExport.html'
    },

    /**
     * @member {?jQuery} toolbar
     * @memberof heurist.recordExport
     * @description jQuery object for the dynamically created toolbar when `options.isdialog` is false.
     */
    toolbar: null,

    /**
     * @function _initControls
     * @memberof heurist.recordExport
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Appends the export format to the title. If not in dialog mode (`!options.isdialog`),
     * it creates a toolbar and action buttons. Hides the standard scope selector as exports
     * usually operate on the 'current' recordset. Shows format-specific info sections (KML, IIIF).
     * @returns {boolean} True.
     */
    _initControls: function() {

        this._super();    
                    
        this.options.title += (' '+ this.options.format.toUpperCase());     

        if(!this.options.isdialog){
            
            //add action button to bottom bar
            let fele = this._$('.ent_wrapper:first');
            fele.css({top:'36px',bottom:'40px'});
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    

            let toolbar_height = '20px';
            if(navigator.userAgent.indexOf('Firefox') >= 0){
                toolbar_height = '40px';
            }
            this.toolbar = $('<div class="ent_footer button-toolbar ui-heurist-header" style="height:'+ toolbar_height +'"></div>').insertAfter(fele);

            //append action buttons
            this.toolbar.empty();
            this._$('.kml-buttons').empty();
            let btns = this._getActionButtons();

            for(let idx in btns){
                
                let $cont = this.toolbar;
                if(this.options.format=='kml'){
                    $cont = this._$('.kml-buttons');
                }else if (this.options.format=='iiif'){
                    $cont = this._$('.iiif-buttons');
                }
                
                this._defineActionButton2(btns[idx], $cont);
            }
        }
        
        this.selectRecordScope.val('current');
        this.selectRecordScope.parent().hide();
        
        if(this.options.format=='kml'){
            this._$('.ent_content').hide();
            this._$('.kml-info').show();
        }else if(this.options.format=='iiif'){
            this._$('.ent_content').hide();
            this._$('.iiif-info').show();
        }
        

        return true;
    },
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordExport
     * @private
     * @description Gets action buttons for the dialog/toolbar, setting labels to 'Download' and 'Close'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Download');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    
        
    /**
     * @function doAction
     * @memberof heurist.recordExport
     * @private
     * @description Performs the export action.
     * Determines the scope of records (currently defaults to 'current' recordset).
     * Constructs the export URL and parameters based on the selected format, current query,
     * and options (like link mode, depth).
     * Initiates the download by opening the URL in a new window or submitting a form for larger data.
     * Displays a message if no records are found for export.
     */
    doAction: function(){

            let scope_val = this.selectRecordScope.val();
            
            scope_val = 'current';
           
            let q;
            let isEntireDb = false;
            let scope = [], //ids to be exported
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;

                q = '?w=all&q=ids:'+scope.join(',');
                
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }else{
                    isEntireDb = (scope.length==window.hWin.HAPI4.sysinfo.db_total_records);
                }   
                
                //'+(rec_RecTypeID>0?('t:'+rec_RecTypeID+' '):'')+'
                
                q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
                
            }
            
            if(scope.length<1){ //IDS
                window.hWin.HEURIST4.msg.showMsgFlash('No results found. '
                +'Please modify search/filter to return at least one result record.', 2000);
                return;
            }
            
            let request = {
                //'request_id' : window.hWin.HEURIST4.util.random(),
                'db': window.hWin.HAPI4.database,
                'format': this.options.format,
                'a': 1,
                'depth': isEntireDb?0:'all'};

            if(!isEntireDb){                
                
                let linksMode = this._$('input[name="links"]:checked').val();
                request['linkmode'] = linksMode; 
                
                if(rec_RecTypeID>0){
                    request['rec_RecTypeID'] = rec_RecTypeID;
                }
            }
           
            
            let url = window.hWin.HAPI4.baseURL;
                           
            if(this.options.format=='kml'){
                url += 'export/xml/kml.php';
            }else if(this.options.format=='hml' || this.options.format=='xml'){
                url += 'export/xml/flathml.php';
            }else{
                request['extended'] = 1;
                request['defs'] = 0; //don't include defintions
                url += 'hserv/controller/record_output.php';
            }

            const open_in_popup  = false;
            if(open_in_popup){
                request['ids'] = scope;
                
                //posting via form allows send large list of ids
                this._$('#postdata').val( JSON.stringify(request) );
                this._$('#postform').attr('action', url);
                this._$('#postform').trigger('submit');
            }else{
                
                url = url + q;
                for(let key in request){
                    url += ('&' + key + '=' + request[key]);
                }
                
                window.open(url, '_blank');
            }
            
            
    }
  
});

