/**
* publishDialog.js - publish/embed dialogue 
* 
* Used for map, mapspace, saved filter, smarty, visualization graph.
* 
* @todo - converts to widget based on HBaseView
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\framecontent
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       5.0
*/

/**
 * Constructor for the HPublishDialog object.
 * This dialog provides users with URLs and embed codes for various Heurist views.
 *
 * @param {object} [_options] - Initial options for the dialog. These are typically
 *                              overridden or extended when `openPublishDialog` is called.
 * @param {string} [_options.mode] - The operational mode of the dialog, determining its UI and behavior.
 *                                  Possible values: 'mapspace', 'mapquery', 'smarty', 'graph', 'websearch'.
 * @param {object} [_options.mapwidget] - A reference to the map widget instance, required for map-related modes.
 * @param {number} [_options.mapdocument_id] - The ID of a specific map document, used in some map modes.
 *                                            Other properties like `url`, `url_encoded`, `url_schedule`
 *                                            are typically set by the `openPublishDialog` method.
 * @returns {object} An instance of HPublishDialog with public methods.
 */
function HPublishDialog( _options )
{
    /** @const {string} _className - The name of this class module. */
    const _className = "PublishDialog";
    /** @const {string} _version - The version of this class module. */
    const _version   = "0.4";

    /** @type {object} options - Internal storage for the dialog's configuration and state. */
    let options = {
        //container:null, // Example of a potential option, not currently used.
        mode: null, // Current operational mode: 'mapspace', 'mapquery', 'smarty', 'graph', 'websearch'.
        mapwidget:null, // Reference to the map widget if applicable.
        mapdocument_id:null, // ID of the map document if applicable.
        url: '',            // Base URL for sharing.
        url_encoded: '',    // URL-encoded version for safer embedding.
        url_schedule: ''    // URL for scheduling (e.g., for Smarty reports).
    };

    /** @type {?jQuery} popupelement - jQuery object for the main content of the dialog, loaded from HTML. */
    let popupelement = null;
    /** @type {?jQuery} popupdialog - jQuery UI dialog instance. */
    let popupdialog = null;
    
    /**
     * Initializes the controls within the publish dialog.
     * This function is called after the dialog's HTML content is loaded.
     * It sets up visibility of sections based on the current `options.mode`,
     * attaches event handlers to checkboxes, select dropdowns (map templates),
     * and buttons (Export KML, Schedule Smarty).
     * For map modes, it calls `_updateUrls` to populate initial URLs.
     * For other modes, it calls `_fillUrls`.
     * @private
     */
    function _initControls(){
        
        popupelement = popupdialog.find('#map-embed-dialog');
        
        popupelement.find('.modes').hide();
        
        popupelement.find('#div-content-type').hide();
        
        popupelement.find('.'+options.mode).show();    
        
        if(options.mode=='mapspace' || options.mode=='mapquery')
        {
            popupelement.find('input[type="checkbox"]').on({change:function(){
                _updateUrls();
            }});
            
            let $select = popupelement.find('#map_template');
            
            window.hWin.HEURIST4.ui.createTemplateSelector( $select, [{key:'',title:'Defaut map popup'}] );
            $select.on({change:function(){
                _updateUrls();
            }});  
            
            popupelement.find("#btnExportKML").button().on('click',_exportKML);
            
            _updateUrls();      
        }else{
            if(options.mode=='smarty'){
                popupelement.find("#btnScheduleSmarty").button().on('click',_scheduleSmarty);
                popupelement.find("#lbl_mode2").text(window.hWin.HR('javascript wrap'));
                
                popupelement.find('#div-content-type').show();
                
                popupelement.find('#select-content-type').on('change',_fillUrls);
            }
            
            _fillUrls();      
        }
        
        popupelement.find('input[type="radio"]').on({change:function(event){
            let val = $(event.target).val();
            $(popupelement).find('textarea[id^="code-textbox-"]').hide();
            $(popupelement).find('#code-textbox-'+val).show()
        }});
            
        popupelement = popupelement[0];
        popupdialog.height($(popupelement).height()+15);
    }

    /**
     * Populates the URL, embed code, and websafe code textareas in the dialog
     * based on the current `options.url`, `options.url_encoded`, and `options.mode`.
     * For 'smarty' mode, it also considers the selected content type.
     * @private
     */
    function _fillUrls(){
        //URL
        $(popupelement).find("#code-url").val(options.url); 
        
        if(options.mode=='smarty'){

            let content_type = $(popupelement).find('#select-content-type').val();
        
            $(popupelement).find("#link-url").attr('href', options.url+'&mode='+content_type); 
            
            $(popupelement).find("#code-textbox-embed").val('<iframe src=\'' + options.url +
                '\' width="80%" height="70%" frameborder="0"></iframe>');

            // For Smarty, websafe often means a JS include that writes an iframe, with a noscript fallback.
            $(popupelement).find("#code-textbox-websafe").val(
                '<script type="text/javascript" src="'+options.url+'&mode=js"><'+'/script>'+
                '<noscript>'+
                '<iframe width="80%" height="70%" frameborder="0" src=\''+options.url+'\'>'+
                '</iframe>'+
                '</noscript>');            
            
        }else{ // For map, graph, websearch modes
            $(popupelement).find("#link-url").attr('href', options.url); 

            
            //readable code / embed code
            $(popupelement).find("#code-textbox-embed").val('<iframe src=\'' + options.url +
                    '\' width="800" height="650" frameborder="0"></iframe>');

            //web safe - encoded URL in iframe
            $(popupelement).find("#code-textbox-websafe").val('<iframe src=\'' + options.url_encoded +
            '\' width="800" height="650" frameborder="0"></iframe>');
            
        }

    }
    
    /**
     * Updates `options.url` and `options.url_encoded` specifically for map modes ('mapspace', 'mapquery').
     * It constructs these URLs by combining a base URL with parameters derived from:
     * - The current map widget's query (if `m_query` is checked).
     * - Visible map document IDs (if `m_mapdocs` is checked or `options.mapdocument_id` is set).
     * - UI controls in the dialog (timeline, cluster, style, basemap, controls, legend, template).
     * After updating the URLs in `options`, it calls `_fillUrls` to refresh the dialog's textareas.
     * @private
     */
    function _updateUrls(){

        let base_url = window.hWin.HAPI4.baseURL+'viewers/map/'; //map.php
        let params_search,params_search_encoded;
        let layout_params = {};
        
        if(options.mapwidget){
            let hquery = (options.mapwidget)?options.mapwidget.current_query_layer['original_heurist_query']:'';
            
            if($(popupelement).find("#m_query").is(':checked')){
                params_search = window.hWin.HEURIST4.query.composeHeuristQuery2(hquery, false);
                params_search_encoded = window.hWin.HEURIST4.query.composeHeuristQuery2(hquery, true);
            }else{
                params_search = '?';
                params_search_encoded = '?';
            }
        
            if($(popupelement).find("#m_mapdocs").is(':checked')){
                let mapdocs = options.mapwidget.getMapManager().getMapDocumentsIds('visible');
                if(mapdocs.length>0){
                    layout_params['mapdocument'] = mapdocs.join(',');
                }
            }
        }else{
            params_search = '?';
            params_search_encoded = '?';
            
            layout_params['mapdocument'] = options.mapdocument_id;
        }
        params_search_encoded = params_search_encoded + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        params_search = params_search + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        
        //parameters for controls
        layout_params['notimeline'] = !$(popupelement).find("#use_timeline").is(':checked');
        layout_params['nocluster'] = !$(popupelement).find("#use_cluster").is(':checked');
        layout_params['editstyle'] = $(popupelement).find("#editstyle").is(':checked');
        //layout_params['extent'] =  @todo
        if($(popupelement).find("#basemap").is(':checked') && 
                    options.mapwidget && options.mapwidget.basemaplayer_name!='MapBox'){//MapBox is default
            layout_params['basemap'] = options.mapwidget.basemaplayer_name;
        }
        
        let ctrls = [];
        $(popupelement).find('input[name="controls"]:checked').each(
            function(idx,item){ctrls.push($(item).val());}
        );
        if(ctrls.length>0) layout_params['controls'] = ctrls.join(',');
        if(ctrls.indexOf('legend')>=0){
            ctrls = [];
            $(popupelement).find('input[name="legend"]:checked').each(
                function(idx,item){ctrls.push($(item).val());}
            );
            if(ctrls.length>0 && ctrls.length<3) layout_params['legend'] = ctrls.join(',');
        }
        
        if($(popupelement).find('#map_template').val()){
            layout_params['template'] = $(popupelement).find('#map_template').val();
        }
        
        let url     = base_url + params_search;
        let url_enc = base_url + params_search_encoded;
        for(let key in layout_params) {
            if(Object.hasOwn(layout_params,key) && layout_params[key]!==false){
                url = url + '&'+key+'='+(layout_params[key]===true?1:layout_params[key]);
                url_enc = url_enc + '&'+key+'='+(layout_params[key]===true?1:encodeURIComponent(layout_params[key]));
            }
        }
        
        options.url = url;
        options.url_encoded = url_enc;
        
        _fillUrls()

    }
    
    
    /**
     * Handles the KML export functionality for map modes.
     * It constructs a KML export URL based on the current map widget's query
     * and opens it in a new window/tab.
     * @private
     */
    function _exportKML(){

        if(options.mapwidget){
            let hquery = (options.mapwidget)?options.mapwidget.current_query_layer['original_heurist_query']:'';
            // Note: The following line seems to use current_query_request directly, which might differ from hquery if not updated.
            // Consider standardizing which query source is definitive for KML export.
            let query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
            if(query=='?'){ // Check if a query is actually defined
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
            }else{
                query = query + '&a=1&depth=1&db='+window.hWin.HAPI4.database;
                let url_kml = window.hWin.HAPI4.baseURL+"export/xml/kml.php" + query;
                let win = window.open(url_kml, "_new");
            }
        }
    }
    
    /**
     * Handles the action to schedule a Smarty report.
     * It closes the current publish dialog and opens a new dialog for scheduling,
     * using the `options.url_schedule` (which should be pre-set).
     * @private
     * @param {Event} event - The click event object from the "Schedule Smarty" button.
     */
    function _scheduleSmarty(event){
        
        popupdialog.dialog('close');
                        
        $(event.target).off('click'); // Remove click handler to prevent multiple executions if dialog is reopened.

        let body = $(window.hWin.document).find('body');
        let dim = {h:body.innerHeight(), w:body.innerWidth()};

        window.hWin.HEURIST4.msg.showDialog(options.url_schedule, 
        {   "close-on-blur": false,
            "no-resize": false,
            default_palette_class: 'ui-heurist-publish',
            height: 480,
            width: dim.w*0.9,
            callback: null
        });
        
        
    }
    
    

    //public members
    let that = {
        /**
         * Gets the class name of this module.
         * @returns {string} The class name "PublishDialog".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the given string matches the class name of this module.
         * @param {string} strClass - The class name to check.
         * @returns {boolean} True if `strClass` is "PublishDialog", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of this module.
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},

        /**
         * Opens or re-initializes the publish dialog with new options.
         * It determines the dialog title based on the `new_options.mode`,
         * then loads the dialog content from `publishDialog.html`.
         * After the content is loaded, `_initControls` is called to set up the UI.
         *
         * @param {object} new_options - The options to configure the dialog.
         * @param {string} new_options.mode - The operational mode (e.g., 'mapspace', 'smarty').
         * @param {string} new_options.url - The base URL to be published/embedded.
         * @param {string} [new_options.url_encoded] - An encoded version of the URL for safer embedding.
         * @param {string} [new_options.url_schedule] - A URL for scheduling (e.g., for Smarty reports).
         * @param {object} [new_options.mapwidget] - Reference to map widget if mode is map-related.
         * @param {number} [new_options.mapdocument_id] - ID of map document if mode is map-related.
         */
        openPublishDialog: function( new_options ){
            
            options = new_options; // Store the new options
            
            let sTitle = 'Publish/Embed'; // Default title
            if(options.mode=='mapspace' || options.mode=='mapquery'){
                sTitle = 'Publish Map';
            }else if(options.mode=='websearch'){
                sTitle = 'Embedding searches';
            }else if(options.mode=='smarty'){
                sTitle = 'Publish report';
            }else if(options.mode=='graph'){
                sTitle = 'Publish Network Diagram';
            }
        
            // Load dialog content from an HTML file, then initialize controls
            popupdialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                + 'hclient/framecontent/publishDialog.html?t=' // Append random string to prevent caching
                + window.hWin.HEURIST4.util.random(), 
                    null, window.hWin.HR(sTitle), // HR for localization
            {  
               container:'embed-publish-popup', // CSS class for styling
               default_palette_class: 'ui-heurist-publish', // UI theme
               height: 610,
               width: 700,
               close: function(){ // Cleanup on dialog close
                    if (popupdialog && typeof popupdialog.dialog === 'function' && popupdialog.dialog('instance')) {
                        popupdialog.dialog('destroy');
                    }
                    if (popupdialog) {
                        popupdialog.remove();
                    }
                    popupdialog = null;
               },
               open: function(){ // After dialog opens, initialize controls
                    setTimeout(_initControls, 500); // Timeout to ensure HTML is loaded
               }
            });        
        
        /* OLD method of showing dialog (likely pre-dating showMsgDlgUrl):
            _fillUrls();

            window.hWin.HEURIST4.msg.showElementAsDialog({
                element: popupelement,
                height: 600,
                width: 700,
                title: window.hWin.HR('Publish Map')
            });
        */
            
        },
        
        /**
         * Closes and cleans up the publish dialog if it is currently open.
         */
        closePublishDialog: function(){
            if(popupdialog && typeof popupdialog.dialog === 'function' && popupdialog.dialog('instance')){
                popupdialog.dialog('close'); // Triggers the close callback defined in openPublishDialog
            }
        }    

    }

    return that;  //returns object
};

        
        