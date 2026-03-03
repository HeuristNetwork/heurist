/**
* exportMenu.js 
* 
* Initializes and manages the export menu functionality.
* This includes setting up UI elements, handling user interactions for various export formats,
* and constructing URLs for data export.
* 
* @todo - convert to widget based on HBaseView
*
* @param {jQuery} container - The jQuery object representing the container for the export menu.
*                             This could be the main body for a dedicated export page or a specific
*                             menu container element.
* @returns {object} An object with public methods to interact with the export menu instance.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
function hexportMenu( container ) {

    const _className = "exportMenu";

    const _version   = "0.4";
    /* Options to be passed to dialogs, can be set externally. */
    let dialog_options=null;
    /* Prepared output parameters ID */
    let preparedSessionID = 0;
    /* Skip including additional fields */
    let skipFields = false;

    /**
     * Initializes the export menu. Currently, it directly calls `_initMenu`.
     * @private
     * @param {jQuery} container - The jQuery object for the export menu container.
     */
    function _init( container ){

        _initMenu( container );        
        
    }

    /**
     * Sets up the export menu, including link behaviors and handling of direct export via URL parameters.
     * Differentiates initialization based on container type (e.g., 'menu_container' or 'heurist-export-menu6').
     * Also handles the 'output' URL parameter to trigger specific exports or show a selection dialog.
     * @private
     * @param {jQuery} container - The jQuery object for the export menu container.
     */
    function _initMenu( container ){

        if(container && container.attr('id')=='menu_container'){
            container.addClass('ui-menu ui-widget ui-widget-content ui-corner-all');
            container.find('li').addClass('ui-menu-item');
        }

        if(container && container.hasClass('heurist-export-menu6')){
            _initLinks_v6( container );
        }else{
            _initLinks();

            if( window.hWin.HAPI4.sysinfo.db_registeredid>0 ){
                $('#divWarnAboutReg').hide();    
            }else{
                $('#divWarnAboutReg').show();    
            }
        }

        let recIDs = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);
        if(!window.hWin.HEURIST4.util.isempty(recIDs)){
            recIDs = recIDs.split(',').filter((recID) => window.hWin.HEURIST4.util.isPositiveInt(recID));
            if(recIDs.length > 0){
                this._selectionRecordIDs = recIDs;
            }
        }

        skipFields = window.hWin.HEURIST4.util.getUrlParameter('skipFields', location.search) == 1;
        let outputs = window.hWin.HEURIST4.util.getUrlParameter('output', location.search);

        if(outputs && outputs != 'all'){

            outputs = outputs.split(',');

            if(outputs.length == 1){
                container.find(`#menu-export-${outputs[0]} > button`).trigger('click');
            }else{

                let $dlg;
                let msg = 'Select an export format: <select style="margin-left: 10px;">';
                for(const format of outputs){
                    msg += `<option>${format}</option>`;
                }
                msg += '</select>';

                let btns = {};
                btns['Export'] = function(){

                    let format = $dlg.find('select').val();
                    container.find(`#menu-export-${format} > button`).trigger('click');

                    $dlg.dialog('close');

                    window.close();
                };
                btns['Close'] = function(){
                    $dlg.dialog('close');
                    window.close();
                };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Export records', yes: 'Export', no: 'Close'}, {default_palette_class: 'ui-heurist-publish'});
            }
        }
    }
    
    
    /**
     * Initializes click listeners and href attributes for export links, typically for a layout
     * where export actions are triggered by buttons associated with anchor tags.
     * It modifies hrefs to include the database name and handles h3link compatibility.
     * Click events on anchor tags or associated spans can trigger `_menuActionHandler` or `_onPopupLink`.
     * @private
     * @param {jQuery} [menu] - The menu container. Though not directly used in the current logic,
     *                          it's passed as a parameter, possibly for future use or by convention.
     */
    function _initLinks(menu){

        $('.export-button').each(function(){
            let ele = $(this);
            
            //find url
            let lnk = ele.parent().find('a').css({'text-decoration':'none','color':'black'});
            let href = lnk.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                
                if(href!='#'){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;
                    if(lnk.hasClass('h3link')){
                        //h3link class on menus implies location of older (vsn 3) code
                        href = window.hWin.HAPI4.baseURL + href;
                    }
                }
                
                lnk.attr('href', href).on('click',
                    function(event){
                        let save_as_file = true;
                        
                        let ele = $(event.target);
                        if(ele.is('span')){ // If a span inside the link (e.g., for feed icons) is clicked
                            save_as_file = false; // Feed links don't save as files directly
                            
                            if(ele.hasClass('mirador')){
                                save_as_file = 'mirador'; // Special case for Mirador
                            }
                            
                            ele = ele.parent(); // Get the parent anchor tag
                        }
                        let action = ele.attr('data-action');
                        if(action){
                            _menuActionHandler(event, action, ele.attr('data-logaction'), save_as_file);
                            return false;
                        }else{
                            // Fallback for links without a data-action, potentially for generic popups
                            _onPopupLink(event);
                        }
                    }
                );
            }

            // Make the button itself trigger the click on its associated anchor tag
            ele.button().on('click',
                function(event){
                    $(this).parent().find('a').trigger('click');
                }
            );
            
        });
        
    }

    /**
     * Initializes click listeners for links in a "version 6" UI menu.
     * Assumes `li` elements with `data-export-action` attributes.
     * @private
     * @param {jQuery} menu - The jQuery object for the v6 menu container.
     */
    function _initLinks_v6(menu){
     
        menu.find('li[data-export-action]').on({click:function(event){
            
            let ele = $(event.target);
            if(!ele.is('li')){ // Ensure the event target is the li element itself
                ele = ele.parents('li');
            }
            let action = ele.attr('data-export-action');

            _menuActionHandler(event, action, ele.attr('data-logaction'), true); // save_as_file is true by default for v6 menu actions
            
            return false;
        }});
        
        menu.find('li[data-export-action]').css({'font-size':'smaller', padding:'6px'});
    }
    
    /**
     * Handles clicks on links that are intended to open a popup dialog.
     * It determines the URL and dimensions for the popup based on the link's classes and attributes.
     * It also appends current query parameters to the URL if available.
     * User activity is logged if `data-logaction` is present.
     * @private
     * @param {Event} event - The click event object.
     * @returns {boolean} False to prevent default link behavior.
     */
    function _onPopupLink(event){
        
        let action = $(event.target).attr('id'); // Potentially used for specific logic, though not in current flow
        
        let body = $(window.hWin.document).find('body');
        let dim = {h:body.innerHeight(), w:body.innerWidth()},
        link = $(event.target);

        let options = { title: link.html() };

        if (link.hasClass('small')){
            options.height=dim.h*0.6; options.width=dim.w*0.5;
        }else if (link.hasClass('portrait')){
            options.height=dim.h*0.8; options.width=dim.w*0.55;
            if(options.width<700) options.width = 700;
        }else if (link.hasClass('large')){
            options.height=dim.h*0.8; options.width=dim.w*0.8;
        }else if (link.hasClass('verylarge')){
            options.height = dim.h*0.95;
            options.width  = dim.w*0.95;
        }else if (link.hasClass('fixed')){
            options.height=dim.h*0.8; options.width=800;
        }else if (link.hasClass('fixed2')){
            if(dim.h>700){ options.height=dim.h*0.8;}
            else { options.height=dim.h-40; }
            options.width=800;
        }else if (link.hasClass('landscape')){
            options.height=dim.h*0.5;
            options.width=dim.w*0.8;
        }

        let url = link.attr('href');
        
            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                
                let q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(q)) q = '&'+q;
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
                url = url + '&w=all&a=1' + q;
            }
        
        
        
        if (link.hasClass('refresh_structure')) {
               options['afterclose'] = this._refreshLists;
        }

        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }

        window.hWin.HEURIST4.msg.showDialog(url, options);

        event.preventDefault();
        return false;
    }
    
    /**
     * Checks if the current result set is empty.
     * If it is, it displays a message dialog to the user.
     * This function is similar to one in resultListMenu.
     * @private
     * @returns {boolean} True if the result set is empty, false otherwise.
     */
    function isResultSetEmpty(){
        let recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    }
    
    /**
     * Handles menu actions triggered by user clicks.
     * It logs the action (if `action_log` is provided) and then calls the appropriate
     * export function based on the `action` string.
     * @private
     * @param {Event} event - The click event object.
     * @param {string} action - The action identifier (e.g., "menu-export-csv", "menu-export-hml-resultset").
     * @param {string} [action_log] - An optional string for logging the user action.
     * @param {boolean|string} save_as_file - Indicates whether the export should be saved as a file.
     *                                        Can be boolean `true`/`false`, or string 'mirador'.
     */
    function _menuActionHandler(event, action, action_log, save_as_file){

        if(action_log){
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }

        if(action == "menu-export-csv"){
        
           
            if(isResultSetEmpty()) return;
            
            //window.hWin.HAPI4.currentRecordsetSelection is assigned in resultListMenu

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordExportCSV', dialog_options);
            
        }else if(action == "menu-export-hml-resultset"){ // Current resultset, including rules-based expansion iof applied
            _exportRecords({format:'hml', multifile:false, save_as_file:save_as_file});
            
/*            
        }else if(action == "menu-export-hml-selected"){ // Currently selected records only
            _exportRecords({format:'hml', isAll:false, multifile:false, save_as_file:save_as_file});
            
        }else if(action == "menu-export-hml-plusrelated"){ // Current resulteset plus any related records
            _exportRecords({format:'hml', isAll:true, includeRelated:true, multifile:false, save_as_file:save_as_file});
*/
        }else if(action == "menu-export-html"){ 
            _exportRecords({format:'html', save_as_file:save_as_file});
            
        }else if(action == "menu-export-hml-multifile"){ // selected + related
            _exportRecords({format:'hml', save_as_file:save_as_file});
            
        }else if(action == "menu-export-json"){ 
            _exportRecords({format:'json', save_as_file:save_as_file});
            
        }else if(action == "menu-export-geojson"){ 
            _exportRecords({format:'geojson', save_as_file:save_as_file});
            
        }else if(action == "menu-export-rdf"){ 
            _exportRecords({format:'rdf', save_as_file:save_as_file});
            
        }else if(action == "menu-export-gephi"){ 

            if(skipFields){
                _exportRecords({format:'gephi', save_as_file:save_as_file});
            }else{
                _popupFields({format:'gephi', save_as_file:save_as_file});
            }

        }else if(action == "menu-export-iiif"){
            _exportRecords({format:'iiif', save_as_file:save_as_file});
            
        }else if(action == "menu-export-kml"){
            _exportKML(true, save_as_file);
        }else if(action == "menu-export-rss"){ //hidden
            _exportFeed('rss');
        }else if(action == "menu-export-atom"){ //hidden
            _exportFeed('atom');
        }
        
        event.preventDefault();
    }
    
    /**
     * Handles the export of records in various formats (HML, JSON, GeoJSON, RDF, IIIF, GEPHI).
     *
     * It constructs the export URL based on the provided options, current query,
     * and user selections (e.g., for following pointers, including human-readable names,
     * selecting specific fields for GEPHI).
     * It also handles format-specific checks (like RDF registration) and dialogs.
     *
     * @private
     * @param {object} opts - Options for the export.
     * @param {string} opts.format - The export format (e.g., 'hml', 'json', 'geojson', 'rdf', 'iiif', 'gephi').
     * @param {boolean} [opts.isAll=true] - If true, exports the current result set. If false, exports only currently selected records (though this path seems less used now).
     * @param {boolean} [opts.includeRelated] - (Potentially deprecated by linksMode) If true, includes related records.
     * @param {boolean} [opts.multifile=false] - If true (for HML), exports as multiple files (HuNI format).
     * @param {boolean|string} opts.save_as_file - If true, prompts to save as a file. If 'mirador' (for IIIF), opens in Mirador.
     * @param {string} [opts.linksMode] - Controls how linked records are handled ('direct', 'direct_links', 'none', 'all'). Set via dialog.
     * @param {boolean} [opts.questionResolved] - Internal flag to track if the pointer-following dialog has been shown.
     * @param {boolean} [opts.showHumanReadableNames] - (For HML) If true, includes human-readable names.
     * @param {string} [opts.columns] - (For GEPHI) Comma-separated string of dty_IDs for additional fields to export.
     * @returns {boolean|void} False if there's an issue preventing export, otherwise void as it opens a new window/tab.
     */
    async function _exportRecords(opts){ // isAll = resultset, false = current selection only

        if(opts.format=='rdf' && !(window.hWin.HAPI4.sysinfo['db_registeredid']>0) ){

           window.hWin.HEURIST4.msg.showMsgDlg(
    '<p>Sorry, RDF is only available for databases which have been registered. This is required in order to make your Subject, Predicate and Object URIs unique within the Heurist namespace.</p>'
    +'<p>Please go to Design > Register to register your database.</p>');
            return;
        }

        let q = "";
        const parameterLimit = 5000;

        let isEntireDb = false;

        opts.isAll = (opts.isAll!==false && !window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs));
        opts.multifile = (opts.multifile===true);

        if(opts.isAll){

            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){

                q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);

                isEntireDb = (window.hWin.HAPI4.currentRecordset &&
                    window.hWin.HAPI4.currentRecordset.length()==window.hWin.HAPI4.sysinfo.db_total_records);

            }

        }else{    //selected only

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }

            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");

        }

        if(window.hWin.HEURIST4.util.isempty(q)){
            return;
        }

        let script;
        const showOptionsDialog = (opts.format!='html');

        // ---- pick depth/linkmode defaults (previously via params string) ----
        // We'll store these in locals then set them onto newURLParams later.
        let depthValue = null;

        if(showOptionsDialog){

            if(isEntireDb){
                depthValue = '0';
                opts.linksMode = 'none';
            }else {
                if(opts.format!='iiif' && opts.questionResolved!==true){
                    let $expdlg = window.hWin.HEURIST4.msg.showMsgDlg(
    '<p>The records you are exporting may contain pointers to other records which are not in your current results set. These records may additionally point to other records.</p>'
    +'<p style="padding:20px 0"><label><input type="radio" name="links" value="direct" style="float:left;margin-right:8px;" checked/>Follow pointers and relationship markers in records <b>(recommended)</b></label>'
    +'<br><br><label><input type="radio" name="links" value="direct_links" style="float:left;margin-right:8px;"/>Follow only pointers, ignore relationship markers <warning about losing relationships></label>'
    +'<br><br><label><input type="radio" name="links" value="none" style="float:left;margin-right:8px;"/>Don\'t follow pointers or relationship markers (you will lose any data which is referenced by pointer fields in the exported records)</label>'
    +'<br><br><label><input type="radio" name="links" value="all" style="float:left;margin-right:8px;"/>Follow ALL connections including reverse pointers" (warning: any commonly used connection, such as to Places, will result in a near-total dump of the database)</label></p>'
    +(opts.format=='hml'?'<p><input type="checkbox" name="human_readable_names"/>Include human-readable names and local IDs for everything '
    +'<div class="heurist-helper3">(NOT RECOMMENDED except for small subset troubleshooting.If checked this will result in a VERY large file and VERY long export time)</div>':'')
    +(opts.format=='rdf'?'<p>Since, RDF export is experimental please specify the access word: <input type="password" name="rdfpwd"/>':'')

                    , function(){
                        if(opts.format=='rdf' && $expdlg.find('input[name="rdfpwd"]').val()!='Tehri'){
                            return;
                        }

                        let val = $expdlg.find('input[name="links"]:checked').val();

                        opts.linksMode = val;
                        opts.questionResolved=true;

                        opts.showHumanReadableNames = $expdlg.find('input[name="human_readable_names"]').is(':checked');

                        _exportRecords( opts );
                    },
                    {
                        yes: 'Proceed',
                        no: 'Cancel'
                    });

                    return;
                }
                depthValue = 'all';
            }
        }

        // ---- parse the existing query string into params ----
        // q is expected to include a leading "?" already; URLSearchParams accepts that.
        const qParams = new URLSearchParams(q);

        if(!window.hWin.HEURIST4.util.isempty(opts.columns)){
            qParams.set('columns', opts.columns);
        }

        // ---- build final URL params here (no string concatenation) ----
        const newURLParams = new URLSearchParams();

        // move safe params over; pre-send long ones
        let preparedSessionID;
        const toStoreParams = {};
        const longParameters = new Set(['q', 'columns']);

        // IMPORTANT: forEach(async ...) won't await; use for..of
        for (const [key, value] of qParams.entries()) {

            if (typeof value === 'string' && value.length > parameterLimit) {

                // pre-send larger parameters in chunks, to avoid a 414 error
                const paramChunks = Math.ceil(value.length / parameterLimit);
                let start = 0;

                for (let i = 0; i < paramChunks; i++) {
                    preparedSessionID = await _preSendParameters({ [key]: value.substring(start, start + parameterLimit) });
                    start += parameterLimit;
                }

                continue;
            }

            if (!longParameters.has(key)) {
                newURLParams.append(key, value);
            } else {
                toStoreParams[key] = value;
            }
        }

        if(Object.keys(toStoreParams).length > 0){
            preparedSessionID = await _preSendParameters(toStoreParams);
        }

        // depth/linkmode (only when showOptionsDialog true in old logic)
        if (showOptionsDialog && depthValue !== null) {
            newURLParams.set('depth', depthValue);
        }
        if (showOptionsDialog && opts.linksMode) {
            newURLParams.set('linkmode', opts.linksMode);
        }

        // ---- select script + format-specific params ----
        if(opts.format=='hml'){

            script = 'export/xml/flathml.php';

            // multifile is for HuNI
            if (opts.multifile) newURLParams.set('multifile', '1');

            if(opts.showHumanReadableNames){
                newURLParams.set('human_readable_names', '1');
            }

        }else{

            script = 'hserv/controller/record_output.php';

            if(opts.format=='iiif'){

                if(opts.save_as_file==='mirador'){
                    // create dynamic manifest with given set of media
                    script = 'hclient/widgets/viewers/miradorViewer.php';
                }else{
                    newURLParams.set('format', 'iiif');
                }

            }else{
                newURLParams.set('format', opts.format);

                if(opts.format=='gephi'){
                    if ($('#limitGEPHI').is(':checked')) newURLParams.set('limit', '1000');

                }else if(opts.format=='geojson'){
                    newURLParams.set('detail_mode', $('input[name="detail_mode"]:checked').val());

                }else if(opts.format=='rdf'){
                    newURLParams.set('vers', '2');
                    newURLParams.set('serial_format', $('input[name="serial_format"]:checked').val());

                    let include_additional_info = '';
                    include_additional_info += $('#include_definition_label').is(':checked')?'1':'0';
                    include_additional_info += $('#include_resource_term_label').is(':checked')?'1':'0';
                    include_additional_info += $('#include_resource_rec_title').is(':checked')?'1':'0';
                    include_additional_info += $('#include_resource_file_info').is(':checked')?'1':'0';

                    if(include_additional_info=='1111'){
                        include_additional_info = '1';
                    }
                    if(include_additional_info!==''){
                        newURLParams.set('extinfo', include_additional_info);
                    }

                }else if(opts.format!=='html'){
                    newURLParams.set('defs', '0');
                    newURLParams.set('extended', ($('#extendedJSON').is(':checked')?2:1));
                }
            }
        }

        if(opts.save_as_file===true){
            newURLParams.set('file', '1'); // save as file
        }

        if(window.hWin.HEURIST4.util.isPositiveInt(preparedSessionID)){
            newURLParams.set('preparedID', String(preparedSessionID));
        }

        // always include db
        newURLParams.set('db', window.hWin.HAPI4.database);

        // final URL: always exactly one "?" and no duplicated "&"
        const url = `${window.hWin.HAPI4.baseURL}${script}?${newURLParams.toString()}`;

        window.open(url, '_blank');

        return false;
    }

    
    /**
     * Handles the export of records in KML format.
     *
     * It constructs the KML export URL based on the current query or selected records.
     *
     * @private
     * @param {boolean} isAll - If true, exports the current result set. If false, exports only currently selected records.
     * @param {boolean} save_as_file - If true, prompts to save the KML as a file.
     * @returns {boolean|void} False if there's an issue preventing export (e.g., no records selected), otherwise void.
     */
    function _exportKML(isAll, save_as_file){

        let q = "";
        if(isAll){

            q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);

            if(q=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }


        }else{

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");
        }

        if(q!=''){
            let url = window.hWin.HAPI4.baseURL + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + window.hWin.HAPI4.database;
            if(save_as_file){
                url = url + '&file=1';
            }
            
            
            window.open(url, '_blank');
        }

        return false;
    }

    /**
     * Handles the export of records as an RSS or Atom feed.
     * Note: This function is marked as hidden/not used in comments.
     * @private
     * @param {string} mode - The feed type, typically 'rss' or 'atom'.
     */
    function _exportFeed(mode){

        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
            let q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);

            if(!window.hWin.HEURIST4.util.isempty(q)){
                let w = window.hWin.HEURIST4.current_query_request.w;
                if(window.hWin.HEURIST4.util.isempty(w)) w = 'a';
                if(mode=='rss') {
                    mode = '';
                }else{
                    mode = '&feed='+mode;
                }
                let rules = '';
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    rules = '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }


                let url = window.hWin.HAPI4.baseURL + 'export/xml/feed.php?&q=' + q + '&w=' + w + '&db=' + window.hWin.HAPI4.database + mode + rules;
                window.hWin.open(url, '_blank');
            }
        }
    }

    /**
     * Displays a dialog to allow the user to select additional fields for export.
     * This is typically used for formats like GEPHI where users might want to include
     * specific data attributes. If fields are selected, they are added to the `opts.fields`
     * property and then `_exportRecords` is called.
     * @private
     * @param {object} opts - The export options object, which will be modified with selected fields
     *                        and then passed to `_exportRecords`.
     */
    function _popupFields(opts){

        let $dlg;

        let msg = 'Would you like to export additional fields, or proceed with the standard fields?';

        let btns = {};
        btns[window.hWin.HR('Select additional fields')] = () => {

            const dty_dialog_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 540,
                selection_on_init: [],
                title: 'Select fields to export',
                filters: { // Define which field types can be selected
                    types: [ "enum", "float", "date", "file", "geo", "freetext", "blocktext", "integer", "year", "boolean" ]
                },
                onselect: function(event, data){ // Callback when fields are selected

                    if(data && data.selection){
                        opts.columns = data.selection.join();
                    }

                    _exportRecords(opts); // Proceed with export
                }
            }

            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', dty_dialog_options);

            $dlg.dialog('close');
        };

        btns[window.hWin.HR('Proceed without other fields')] = () => { $dlg.dialog('close'); _exportRecords(opts); };

        btns[window.hWin.HR('Cancel')] = () => { $dlg.dialog('close'); };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Add additional fields to export'}, {default_palette_class: 'ui-heurist-publish'});
    }

    async function _preSendParameters(parameters){

        return new Promise((resolve) => {

            parameters['preparedID'] = preparedSessionID;
            parameters['preparedMode'] = 2;

            window.hWin.HAPI4.callserver('record_output', parameters, (response) => {
                resolve(response.data);
            });
        });
    }
     
    //public members
    let that = {
        
        /**
         * Sets dialog options that might be used by functions within this module
         * when showing dialogs (e.g., for CSV export options).
         * @param {object} _dialog_options - The dialog options object.
         */
        setDialogOptions: function( _dialog_options ){
            dialog_options = _dialog_options
        }

    }

    _init( container );
    return that;  //returns object
}
    