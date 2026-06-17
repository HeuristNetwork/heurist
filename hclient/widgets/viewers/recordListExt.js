/**
* @file        recordListExt.js
* @brief       Extended record list for iframe-based content display and integrations.
* @fileOverview This file provides the `heurist.recordListExt` jQuery UI widget.
*              It is designed to display Heurist record sets or selections within
*              an iframe or directly in a div. It supports loading content from a
*              specified URL, which can be a Smarty template, a record viewer, or
*              other Heurist applications. The widget can operate with single record
*              selections, multiple selections, or entire record sets. It handles
*              events for search results and selections to refresh its content, and
*              provides options for initial data loading, placeholder text, and
*              custom styling. It also includes functionality for print and export
*              of the displayed content.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/* global initLinksAndImages */

/**
 * @widget heurist.recordListExt
 * @description An extended record list viewer that displays Heurist record data,
 * often by loading content (like Smarty reports or other views) into an iframe
 * or directly into its element. It reacts to search and selection events to update
 * the displayed content and supports various configurations for single/multiple
 * record display, initial data loading, and UI customizations like print/export buttons.
 */
$.widget( "heurist.recordListExt", {

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {?string} options.widget_id - Outdated: User identifier for custom JS script on web/CMS page.
     * @property {string} [options.title=''] - Title for the viewer (not actively used to set header).
     * @property {boolean} [options.is_single_selection=false] - If true, works with a single selected record,
     *           reloading content on every selection change.
     * @property {boolean} [options.is_multi_selection=false] - If true, works with all selected records.
     * @property {boolean} [options.init_show_all=false] - If true, shows the complete recordset on initialization.
     * @property {?HRecordSet} options.recordset - The Heurist record set to be displayed or used as context.
     * @property {?Array<number>} options.selection - An array of selected record IDs.
     * @property {?string} options.url - The URL to load content from. Can contain placeholders like `[recID]`,
     *           `[query]`, `[dbname]`, `[lang]`.
     * @property {boolean} [options.is_frame_based=true] - If true, loads content into an iframe. Otherwise,
     *           loads directly into the widget's element.
     * @property {boolean} [options.is_popup=false] - If true, displays the content in a popup dialog on every refresh.
     * @property {?Object|string} options.popup_position - Position for the popup dialog if `is_popup` is true.
     *           (e.g., 'center', 'left', jQuery UI position object).
     * @property {boolean} [options.reload_for_recordset=false] - If true, refreshes content every time the
     *           recordset changes (e.g., for Smarty reports from CMS).
     * @property {?string} options.search_page - Target page for search links, primarily for CMS integration.
     * @property {?string} options.search_realm - A string identifier to scope event listening.
     * @property {?string|Object} options.search_initial - A query string or SVS_ID for an initial search on load.
     * @property {?string} options.custom_css_for_frame - Custom CSS to be injected into the iframe if `is_frame_based`.
     * @property {number} [options.record_with_custom_styles=0] - Record ID from which to load custom CSS
     *           (DT_CMS_CSS) and external files (DT_CMS_EXTFILES) for iframe content.
     * @property {?function} options.onLoadComplete - Callback function executed after content is loaded.
     * @property {?string} options.empty_remark - HTML content to display when a search returns no results.
     *           'def' uses a default localized string.
     * @property {?string} options.placeholder_text - Text to display when no record/recordset is loaded initially.
     *           'def' uses a default localized string.
     * @property {boolean} [options.show_export_button=false] - If true, shows an export button for the current record set.
     * @property {boolean} [options.show_print_button=false] - If true, shows a print button for the current content.
     * @property {string} [options.export_options='all'] - Specifies allowed export formats (e.g., 'csv,json', or 'all').
     * @property {number} [options.fontsize=0] - Base font size for rendered content. If 0, attempts to inherit.
     * @property {string} [options.language='def'] - Language code for content (e.g., 'en', 'fr', 'def' for default).
     */
    options: {
        widget_id: null, //outdated: user identificator to find this widget custom js script on web/CMS page
        title: '',

        //display range for current result set - all, page or selection
        is_single_selection: false, //work with the only record - reloads content on every selection event
        is_multi_selection: false, //work with all selected records
        show_page: false, //work with current page only
        show_all: true,
        
        init_show_all: false, //show complete recordset at initialisation

        recordset: null,
        selection: null,  //list of selected record ids
        url:null,               //
        is_frame_based: true,
        is_popup: false, //show popup dialog on every refresh
        popup_position: null,
        
        reload_for_recordset: false, //refresh every time recordset is changed - for smarty report from CMS

        search_page: null, //target page (for CMS)
        search_realm: null,
        search_initial: null,  //Query or svs_ID for initial search
        
        custom_css_for_frame: null,
        record_with_custom_styles: 0, //record id with custom css and style links DT_CMS_CSS and DT_CMS_EXTFILES
        
        onLoadComplete: null,  //callback

        empty_remark: '', //html content for empty message  (search returns empty result)
        placeholder_text: '', //text to display while no record/recordset is loaded  (search is not performed)
        
        showProgress: true,
        show_export_button: false, // show button to export current record set
        show_print_button: false, // show button to print current record set
        export_options: 'all', // export formats allowed

        fontsize: 0, //base font size for renderRecordData otherwise it takes from user preferences
        useRelmarkerTitle: 0, // replace the curated relmarker string with the relationship record title
        
        language: 'def'
    },

    /** @memberof heurist.recordListExt @instance @private @property {?string} _current_url - Stores the currently loaded URL for the iframe or content area. */
    _current_url: null, //keeps current url - see loadURL 
    /** @memberof heurist.recordListExt @instance @private @property {?Object} _query_request - Stores the last query request object that populated the recordset. */
    _query_request: null, //keeps current query request
    /** @memberof heurist.recordListExt @instance @private @property {?string} _events - Concatenated string of Heurist event names the widget listens to. */
    _events: null,
    /** @memberof heurist.recordListExt @instance @private @property {?Object} _dataTable - Reference to a DataTable instance if used (currently null, seems unused). */
    _dataTable: null,
    
    /** @memberof heurist.recordListExt @instance @private @property {boolean} _is_publication - Flag indicating if running in CMS publication mode. */
    _is_publication:false, //this is CMS publication - take css from parent

    /** @memberof heurist.recordListExt @instance @private @property {?jQuery} placeholder_ele - jQuery element holding the placeholder or empty message text. */
    placeholder_ele: null, //element holding the placeholder text
    
    resetLink: null,
    
    /** @memberof heurist.recordListExt @instance @private @property {?jQuery} export_button - jQuery object for the export button. */
    export_button: null, // export button
    /** @memberof heurist.recordListExt @instance @private @property {?jQuery} print_button - jQuery object for the print button. */
    print_button: null, // print button

    /** @memberof heurist.recordListExt @instance @private @property {?jQuery} _print_frame - Hidden iframe used for printing content. */
    _print_frame: null, // for printing
    
    _progressDiv: null,
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Widget constructor. Sets up the initial DOM structure (iframe or direct content div),
     * initializes event listeners for Heurist system events (search, selection, login),
     * and handles initial data loading if `options.search_initial` is provided.
     * Also sets up placeholder text and print/export buttons if configured.
     */
    _create: function() {

        if(this.options.widget_id){ //outdated
            this.element.attr('data-widgetid', this.options.widget_id);
        }
        
        this._is_publication = !window.hWin.HAPI4.isAdminInterface;
        
        let that = this;

        this.div_content = this.element;
        if(this.div_content.parent('.tab_ctrl').length==0 && !this.element.attr('data-widgetid')){
            this.div_content.css({width:'100%', height:'100%'}); 
        }

        let useBlankEmptyRemark = this.options.empty_remark_option == 'blank' || this.options.empty_remark_option == 'provided' && this.options.empty_remark === '';
        if(useBlankEmptyRemark){
            this.options.empty_remark = '';
        }else if(this.options.empty_remark_option == 'provided' && this.options.empty_remark_recent){
            this.options.empty_remark = this.options.empty_remark_recent;
        }else{
            this.options.empty_remark = window.hWin.HR('resultListExt_empty_remark');
            let templateName = window.hWin.HEURIST4.util.getUrlParameter('template', this.options.url) ?? 'Record Viewer';
            this.options.empty_remark = templateName ? this.options.empty_remark.replace('__REPORTNAME__', templateName) : this.options.empty_remark;
        }

        let useBlankPlaceholder = this.options.placeholder_option == 'blank' || this.options.placeholder_option == 'provided' && this.options.placeholder_text === '';
        if(useBlankPlaceholder){
            this.options.placeholder_text = '';
        }else if(this.options.placeholder_option == 'provided' && this.options.placeholder_recent){
            this.options.placeholder_text = this.options.placeholder_recent;
        }else{
            this.options.placeholder_text = window.hWin.HR('resultListExt_placeholder_text');
        }

        if(this.options.css){
            this.div_content.css( this.options.css );
        }
        if(this.options.fontsize==0){
            if(this.element.css('font-size')){
                this.options.fontsize = parseFloat(this.element.css('font-size'));
            }else if(this.div_content.css('font-size')){
                this.options.fontsize = parseFloat(this.div_content.css('font-size'));
            }
        }
        if(this.options.useRelmarkerTitle == 0){
            window.hWin.HAPI4.get_prefs_def('useRelmarkerTitle', 0);
        }
        
        
        if(this.options.is_frame_based){
            this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'})
            //.attr('src',window.hWin.HAPI4.baseURL+"common/html/msgNoRecordsSelected.html")
            .appendTo( this.div_content );
        }
        if(!this._is_publication){
            this.resetLink = $( '<a href="#">Standard View</a>' ).css({zIndex: 99999, position: 'absolute', right:'15px', top:'2px'})
            .appendTo( this.element ).hide();
            this._on(this.resetLink, {
                click: ()=>{
                    window.hWin.HAPI4.save_pref('main_recview', 'default');
                    this._refresh();
                }});
        }
        
        if(this.options.showProgress){
            this._progressDiv = $('<div id="progressbar_div" class="ent_content_full" style="display:none;top:0px;padding:5px;z-index:999;background:white;"></div>').appendTo( this.div_content );
        }
        
        if(this.options.is_popup){
            if(!this.options.popup_width) this.options.popup_width = this.element.css('width');
            if(!this.options.popup_height) this.options.popup_height = this.element.css('height');
            this.element.hide();
        }

        if(this.options.show_print_button){

            this._print_frame = $('<iframe>', {style: 'width:0px;height:0px;'}).appendTo(this.div_content);

            this.print_button = $('<button>', {
                text: window.hWin.HR('Print'), title: window.hWin.HR('Print current results'), 
                class: 'btnPrintRecords', style: `height:25px;float:right;${this.options.show_export_button ? 'margin-right:15px;' : ''}`
            })
            .button({
                icon: 'ui-icon-print',
                showLabel: false
            })
            .prependTo(this.div_content)
            .hide();

            this._on(this.print_button, {
                click: function(){

                    let has_frame = this._print_frame && this._print_frame.length > 0;
                    let has_records = this.options.recordset && this.options.recordset.length() > 0;
                    if(!has_records){
                        return;
                    }

                    let content = null;
                    if(this.options.is_frame_based && this.dosframe.contents().length > 0){

                        let frame = this.dosframe[0].contentWindow;
                        let frame_doc = this.dosframe[0].contentDocument || this.dosframe[0].contentWindow.document;

                        // Check for content to print
                        if(!frame_doc || frame_doc.body.childElementCount == 0){
                            return;
                        }

                        frame.print();

                        return;
                    }

                    content = this.div_content.html();

                    // Check for content to print
                    if(window.hWin.HEURIST4.util.isempty(content)){
                        return;
                    }

                    let print_doc = this._print_frame[0].contentDocument || this._print_frame[0].contentWindow.document;
                    print_doc = print_doc.document ? print_doc.document : print_doc;

                    print_doc.write('<head><title></title>');
                    print_doc.write('</head><body onload="this.focus(); this.print();">');
                    print_doc.write(content);
                    print_doc.write('</body>');
                    print_doc.close();
                }
            });

        }

        if(this.options.show_export_button){

            this.export_button = $('<button>', {
                text: window.hWin.HR('Export'), title: window.hWin.HR('Export current results'), 
                class: 'btnExportRecords', style: 'height:25px;float:right;'
            })
            .button({
                icon: 'ui-icon-download'
            })
            .prependTo(this.div_content)
            .hide();

            this.export_button[0].style.setProperty('color', '#FFF', 'important');

            this._on(this.export_button, {
                click: function(){

                    if(!this.options.recordset || this.options.recordset.length() == 0){
                        window.hWin.HEURIST4.msg.showMsgFlash('No records to export...', 3000);
                        return;
                    }

                    // Set current query and current recordset
                    if(!this._query_request && this.options.selection && this.options.selection.length > 0){
                        window.hWin.HEURIST4.current_query_request = {
                            q: `[{"ids":"${this.options.selection.join(',')}"}]`,
                            w: 'a',
                            db: window.hWin.HAPI4.database,
                            search_realm: this.options.search_realm
                        };
                    }else{
                        window.hWin.HEURIST4.current_query_request = this._query_request;
                    }

                    if(this.options.selection && this.options.selection.length > 0){ // filter complete subset by selected records

                        let records = this.options.recordset.getSubSetByIds(this.options.selection);
                        window.hWin.HAPI4.currentRecordset = records;
                    }else{
                        window.hWin.HAPI4.currentRecordset = this.options.recordset;
                    }

                    // open export menu in dialog/popup
                    let url = `${window.hWin.HAPI4.baseURL}hclient/framecontent/exportMenu.php?db=${window.hWin.HAPI4.database}`;
                    
                    if(typeof this.options.export_options !== 'string'){
                        this.options.export_options = 'all';
                    }

                    let handle_formats = !window.hWin.HEURIST4.util.isempty(this.options.export_options) && this.options.export_options != 'all';
                    if(handle_formats){
                        url += `&output=${this.options.export_options}`
                    }

                    window.hWin.HEURIST4.msg.showDialog(url, {width: 650, height: 568, dialogid: 'export_record_popup', 
                        onpopupload: function(){
                            if(handle_formats){
                                $('#export_record_popup').dialog('widget').hide();
                            }
                        }
                    });
                }
            });

        }

        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
        + ' ' + window.hWin.HAPI4.Event.ON_REC_PAGE_RENDERED
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.options.recordset = null;
                    that._refresh();
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.recordset = data.recordset; //HRecordSet
                let ignoreSelectionSetting = false;

                if(that.options.is_multi_selection && Object.hasOwn(data, 'showing_collection')){
                    that.options.selection = data.showing_collection && that.options.recordset && that.options.recordset.length() > 0 ? data.recordset.getIds() : null;
                    ignoreSelectionSetting = true;
                }

                if(that.options.show_all || ignoreSelectionSetting){
                    that._run_initial = !ignoreSelectionSetting;

                    that._refresh();
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_PAGE_RENDERED && that.options.show_page){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.selection = data.selection;//ids

                that._refresh();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(!that._isSameRealm(data)) return;
                
                if(data && !data.reset){
                    that.updateDataset( jQuery.extend(true, {}, data) ); //keep current query request (clone)
                }

                if(data && data.facet_value){
                    that._facet_value = data.facet_value;
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                //selection happened somewhere else
                if((that.options.is_single_selection || that.options.is_multi_selection) && that._isSameRealm(data) && data.source!=that.element.attr('id')){
                    if(data.reset){
                       
                        that.options.selection = null;
                    }else{
                        let sel = window.hWin.HAPI4.getSelection(data.selection, true); //get ids
                        that.options.selection = sel;
                       
                    }

                    let smarty_template = window.hWin.HAPI4.get_prefs_def('main_recview', 'default'); // default = standard record viewer
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(that.options.selection) && that.options['url'] 
                        && that.options['url'].indexOf('renderRecordData') != -1 && smarty_template != 'default'){

                        let recIDs_list = that.options.selection;

                        if(recIDs_list.length>0){
                            let recID = recIDs_list[recIDs_list.length-1];

                            // check if the custom report exists
                            window.hWin.HAPI4.SystemMgr.reportAction({action:'check', template:smarty_template}, 
                                function(response){
                                    if(response?.data == 'exist'){
                                        
                                        let newurl = '?template=' + encodeURIComponent(smarty_template)
                                            + '&q=ids:' + recID
                                            + '&db=' + window.hWin.HAPI4.database;
                                            
                                        newurl = that._assignLang(newurl, that.options.language);
                                        
                                        if(that._current_url != newurl){    
                                            that.loadURL(newurl);
                                        }
                                        
                                    }else{
                                        
                                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                            "You have specified a custom report format '"+ smarty_template.slice(0, -4) +"' to use in this view,<br>"
                                            + "however this format no longer exists.<br><br>Please go to Design > My preferences to choose a new format.", 
                                            null,
                                            {ok: 'Close', title: 'Custom format unavailable'},
                                            {default_palette_class: 'ui-heurist-explore'}
                                        );

                                        that._refresh(); // display normal record view - custom report missing
                                        
                                    }
                                    //window.hWin.HEURIST4.msg.showMsgErr(response);
                            });

                        }
                    }else{
                        that._refresh();
                    }
                    
                }
            }
           
        });

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
            
        if(this.options.is_frame_based){
            this.dosframe.on('load', function(){
                that.onLoadComplete();
            });
        }

        this.placeholder_ele = $('<div>')
                .css({'white-space': 'pre-wrap', 'padding-top': '20px'})
                .prependTo(this.element)
                .html(this.options.empty_remark)
                .hide();

        if(this.options.search_initial){
            this.doSearch( this.options.search_initial );
            this.options.search_initial = null;
        }else{
            this.placeholder_ele.show();
        }

    }, //end _create

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Loads content from the specified URL into the iframe or div.
     * This is used when `options.is_single_selection` or `options.reload_for_recordset`
     * triggers a content reload based on selection or recordset changes.
     * @param {string} newurl - The URL to load.
     */
    loadURL: function( newurl ){
        
        let that = this;

        this._current_url = newurl;
        
        if(window.isCMS_active || window.hWin.HEURIST4.util.getParentWinProperty('cmsEditor')){
            newurl = newurl + '&limit=5&publish=0&cmseditor=1';
            this.options.showProgress = false;
        }
        
        if(this.options.showProgress){
            const session_id = window.hWin.HEURIST4.msg.showProgress({container:this._progressDiv});
            newurl = newurl + '&session=' + session_id;
        }else{
            this.loadanimation(true);    
        }

        if(this._print_frame){
            this._print_frame.attr('src', '');
        }
        
        if(this.options.is_frame_based){
            this.dosframe.attr('src', newurl).show();
        }else{
            this.div_content.load(newurl, function(){ that.onLoadComplete(); })
        }
        
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Callback function executed after content is loaded into the iframe or div.
     * It hides the loading animation, calls the user-defined `options.onLoadComplete` callback,
     * injects custom CSS into the iframe if specified, and initializes links for CMS/publication mode.
     * It also manages the visibility and layout of print/export buttons.
     */
    onLoadComplete: function(){
        this.loadanimation(false);
        if(!this.options.reload_for_recordset && this.options.is_frame_based && !this.options.is_single_selection && !this.options.is_multi_selection){
              this._refresh();
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onLoadComplete)){
            this.options.onLoadComplete.call(this);
        }
        if(this.placeholder_ele != null){
            this.placeholder_ele.hide();
        }
        
        //add custom css to iframe  besides see cssid parameter
        if(this.options.is_frame_based && this.options.custom_css_for_frame){
            
            let fdoc = this.dosframe[0].contentWindow.document;
            
            let style = document.createElement('style');
            style.type = 'text/css'; 
            style.innerHTML = this.options.custom_css_for_frame;
            fdoc.getElementsByTagName('head')[0].appendChild(style);
            
        }
        
        if(this.options.is_frame_based){                
            
            let fdoc = this.dosframe[0].contentWindow.document;
            let smarty_template = window.hWin.HEURIST4.util.getUrlParameter('template', this.options.url);
            
            if(this._is_publication && window.hWin.HEURIST4.util.isFunction(initLinksAndImages))
            {
                //init "a href" for CMS pages
                if(!window.hWin.HEURIST4){
                    let script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.src = window.hWin.HAPI4.baseURL + 'hclient/core/detectHeurist.js';
                    fdoc.getElementsByTagName('head')[0].appendChild(script);
                }
                
                initLinksAndImages($(fdoc.body), {
                        search_page: this.options.search_page, 
                        search_realm: this.options.search_realm,
                        smarty_template: smarty_template
                });
            }else if(smarty_template){
                
                $(fdoc.body).find('a').each(function(i,link){
                    let href = $(link).attr('href');
                    if ((href && href.indexOf('q=')===0) || $(link).attr('data-query')) 
                    {
                        let query = $(link).attr('data-query')
                                ? $(link).attr('data-query')
                                : href.substring(2);
                        
                        let request = {detail:'ids', neadall:1, w:'a', q:query};
                        if(this.options.search_realm) request['search_realm'] = this.options.search_realm;
                        
                        if(!href || href=='#' || href.indexOf('q=')===0){
                            //need for right click - open link in new tab
                            //href = '/' + window.hWin.HAPI4.database+'/tpl/'+smarty_template+'/'+encodeURIComponent(query);
                            href = window.hWin.HEURIST4.ui.getTemplateLink(smarty_template, query);
                            $(link).attr('href', href);
                        }
                                    
                        $(link).on('click', function(event){
                            window.hWin.HEURIST4.util.stopEvent(event);
                            window.hWin.HAPI4.RecordSearch.doSearch(window.hWin,request);
                            return false;
                        });

                        
                        
                    }
                    if (!window.hWin.HEURIST4.util.isempty(href) && href!='#' && (href.indexOf('./')==0 || href.indexOf('/')==0)){ //relative path
                            href = window.hWin.HAPI4.baseURL + href.substring(href.indexOf('/')==0?1:2);
                            $(link).attr('href',href);
                    }
                    
                });
            }
            
            if(this.resetLink){
                if(smarty_template || window.hWin.HAPI4.get_prefs_def('main_recview', 'default')!=='default'){
                    this.resetLink.show();
                }else{
                    this.resetLink.hide();
                }
            }
            

            // Toggle display of buttons
            let hasContent = !window.hWin.HEURIST4.util.isempty(this.dosframe.attr('src')) || fdoc.body.childElementCount > 0;
            let hasRecords = this.options.recordset && this.options.recordset.length() > 0;

            this.dosframe.css('height', '');
            let new_height = this.element.height() - 25;

            if(this.export_button){
                // Allow print if there is content to print
                if(!hasRecords){
                    this.export_button.hide();
                }else{
                    this.export_button.show();
                    this.dosframe.css('height', `${new_height}px`);
                }
            }

            if(this.print_button){
                // Allow export if there are records
                if(!hasContent){ //!hasRecords && 
                    this.print_button.hide();
                }else{
                    this.print_button.show().css('margin-right', `${this.export_button && this.export_button.is(':visible') ? '15px' : ''}`);
                    this.dosframe.css('height', `${new_height}px`);
                }
            }
        }
        
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Initiates a Heurist search with the given query.
     * The search results will be handled by the global event listeners.
     * @param {string|Object} query - The search query string or query object.
     */
    doSearch: function(query){
        let request = {q:query, w: 'a', detail: 'ids', 
                        source: 'init', search_realm: this.options.search_realm };
        window.hWin.HAPI4.RecordSearch.doSearch(this.document, request);
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Updates the widget's dataset based on a new search request.
     * Stores the request, clears current selection and recordset, shows loading animation,
     * and triggers a refresh.
     * @param {Object} request - The new search request object.
     */
    updateDataset: function(request){
        this._query_request = request;
        this.options.selection = null;
        this.options.recordset = null;
        if(request.q!=''){
            //    this.loadanimation(true);    
        }
        this._refresh();
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Checks if the provided event data belongs to the same search realm as this widget.
     * @param {Object} data - The event data object, expected to have a `search_realm` property.
     * @returns {boolean} True if realms match or if realms are not defined for comparison, false otherwise.
     */
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Handles setting multiple options for the widget. Calls the superclass's method.
     * Note: Does not automatically trigger a refresh here; individual option changes might.
     */
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
       
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Handles setting a single option for the widget.
     * If the `selection` option is changed, it updates `options.selection` and triggers a `_refresh`.
     * Otherwise, it calls the superclass's method.
     * @param {string} key - The option key to set.
     * @param {*} value - The value to set for the option.
     */
    _setOption:function(key, value){
        if(key == 'selection'){
            this.options.selection = value;
            this._refresh();
        }else{
            this._superApply( arguments );
        }
    },

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Refreshes the content displayed by the widget. This is the core logic for updating
     * the view based on the current options (recordset, selection, URL, etc.).
     * It handles various scenarios:
     *  - If the widget is not visible or the URL is not set, it does nothing.
     *  - If `is_single_selection` or `is_multi_selection` is true, it constructs a new URL based on
     *    the current selection and `options.url` template, then loads it using `loadURL()`.
     *    Handles placeholder display if no selection.
     *  - If `reload_for_recordset` is true, it constructs a URL based on the current recordset and loads it.
     *  - If content needs to be loaded initially or the URL has changed, it loads `options.url`.
     *  - If the recordset is empty, it displays `options.empty_remark`.
     *  - If content is already loaded and `is_frame_based`, it might interact with content inside the
     *    iframe (e.g., for crosstabs or rule builder).
     *  - Manages the display of a popup if `options.is_popup` is true.
     */
    _refresh: function(){

        if(this.options.title!=''){
            let id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }
        if(this.placeholder_ele != null){
            this.placeholder_ele.hide();
        }
        
        //refesh if element is visible only - otherwise it costs much resources
        let templateName = window.hWin.HEURIST4.util.getUrlParameter('template', this.options.url) ?? 'Record Viewer';
        if(  (!this.element.is(':visible') && !this._is_publication) 
            || window.hWin.HEURIST4.util.isempty(this.options.url)){
            return;  
        }else{

            let useBlankEmptyRemark = this.options.empty_remark_option == 'blank' || this.options.empty_remark_option == 'provided' && this.options.empty_remark === '';
            if(useBlankEmptyRemark){
                this.options.empty_remark = '';
            }else if(this.options.empty_remark_option=='provided' && this.options.empty_remark_recent){
                this.options.empty_remark = this.options.empty_remark_recent;
            }else{
                this.options.empty_remark = window.hWin.HR('resultListExt_empty_remark');
            }
            this.options.empty_remark = this.options.empty_remark.replace('__REPORTNAME__', templateName);
        }

        let empty_results = this.options.recordset==null || this.options.recordset.length()==0;
        let content_updated = false;

        if(this.options.is_single_selection || this.options.is_multi_selection || this.options.show_page){ //reload content on every selection event

            let newurl = null;
            let show_all = (this._run_initial && this.options.init_show_all && !empty_results);
            this._run_initial = false;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection) || show_all){

                let recIDs_list = !show_all ? this.options.selection : this.options.recordset.getIds();

                if(recIDs_list.length>0){
                    
                    let recID = recIDs_list;
                    let mainRecID = recIDs_list[0];
                    if(!show_all){
                        recID = this.options.is_single_selection ? recIDs_list[recIDs_list.length-1] : recIDs_list.join(',');
                    }

                    newurl = this.options.url;
                    
                    if(newurl.indexOf('[recID]') > 0){
                        newurl = newurl.replace("[recID]", recIDs_list[0]);
                        newurl += this.options.is_multi_selection && recIDs_list.length > 1 ? `&ids=${recIDs_list.join(',')}` : '';
                    }else{
                        newurl = newurl.replace("[query]", ('q=ids:'+recID));
                    }
                    if(newurl.indexOf('[dbname]')>0){
                        newurl = newurl.replace('[dbname]', window.hWin.HAPI4.database);
                    }else if(newurl.indexOf('db=')<0){
                        newurl = newurl + '&db=' + window.hWin.HAPI4.database;    
                    }
                    
                    
                    newurl = this._assignLang(newurl, this.options.language);
                    
                    if(!window.hWin.HEURIST4.util.isempty(this._facet_value)){
                        newurl += `&facet_val=${this._facet_value}`;
                    }
                    
                    if(this.options.record_with_custom_styles){
                        newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                    }
                    if(this.options.fontsize>0){
                        newurl = newurl + '&fontsize=' + this.options.fontsize;
                    }
                    if(this.options.useRelmarkerTitle != 0){
                        newurl += `&useRelmarkerTitle=1`;
                    }
                }
            }
            if(newurl==null){

                this._current_url = null;
                
                let useBlank = this.options.placeholder_option == 'blank' || this.options.placeholder_option == 'provided' && this.options.placeholder_text === '';
                if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)){
                    if(this.placeholder_ele != null) {
                        this.placeholder_ele.html(this.options.placeholder_text).show();   
                    }
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', null).hide();
                    }else{
                        this.div_content.empty();
                    }
                }else if(!useBlank){
                    
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', window.hWin.HRes('recordSelectMsg'));
                    }else{
                        this.div_content.load(window.hWin.HRes('recordSelectMsg'));
                    }
                }
            }else{
                newurl = window.hWin.HAPI4.baseURL +  newurl;

                if(this._current_url!==newurl){
                    this.loadURL(newurl);
                    content_updated = true;
                }
            }

        }else 
        if(!this.options.reload_for_recordset && this._current_url!==this.options.url){

            this.options.url = window.hWin.HAPI4.baseURL +  this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);

            this.options.url = this._assignLang(this.options.url, this.options.language);
            
            this.loadURL( this.options.url );
            
            content_updated = true;

        }else if(empty_results && this.placeholder_ele!=null){

            this.placeholder_ele.html(this.options.empty_remark);

            if(this.options.is_frame_based){
                this.dosframe.hide();
            }else{
                this.div_content.empty();
            }

            this.placeholder_ele.show();

            content_updated = true;

        }else{ //content has been loaded already ===============================

            let query_string_all = null,
            query_string_sel = null,
            query_string_main = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( this._query_request, true );

            if(this.options.reload_for_recordset){ //reloads content entirely

                if(this.options.show_all && !empty_results && query_string_main.indexOf('q=') === -1){
                    query_string_main = `q=ids:${this.options.recordset.getIds().join(',')}&${query_string_main}`;
                }

                let newurl = window.hWin.HAPI4.baseURL +  this.options.url.replace("[query]", query_string_main);
                
                if(this.options.record_with_custom_styles){ //to load custom css and style links
                    newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                }
                
                newurl = this._assignLang(newurl, this.options.language);
                
                this.loadURL( newurl );
                content_updated = true;
                return;    
            }
            

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                let recIDs_list = this.options.selection;
                if(!window.hWin.HEURIST4.util.isempty(recIDs_list.length)){
                    //NOT USED
                    query_string_sel = 'db=' + window.hWin.HAPI4.database
                    + '&w=' + window.hWin.HEURIST4.util.isnull(this._query_request)?this._query_request.w:'all'
                    + '&q=ids:'+recIDs_list.join(',');
                }
            }

            if(this.options.is_frame_based){
                //there is heurist apps in iframe - smarty report, crosstabs analysis or mapping

                this.dosframe.show();

                if (this.dosframe[0].contentWindow.crosstabsAnalysis) {
                    
                    if (this.options.recordset!=null){
                        this._checkRecordsetLengthAndRunCrosstabsAnalysis(6000, query_string_main);
                    }
                    
                }else{
                    let showMap = this.dosframe[0].contentWindow.showMap;
                    if(showMap){ //not used anymore
                        showMap.processMap();
                    }else if(this.dosframe[0].contentWindow.updateRuleBuilder && this.options.recordset) {

                        //todo - swtich to event trigger????
                        this.dosframe[0].contentWindow.updateRuleBuilder(this.options.recordset.getRectypes(), this._query_request);
                    }
                }
            }
            
            this.loadanimation(false);
        }
        
        if(this.options.is_popup && content_updated){
            if(this.reportPopupDlg && this.reportPopupDlg.dialog('instance')){
                this.reportPopupDlg.dialog('open');
            }else{
                this.element.width()
                
                let opts = {
                    window:  window.hWin, //opener is top most heurist window
                    title: window.hWin.HR('Record Info'),
                    width: this.options.popup_width,
                    height: this.options.popup_height,
                    modal: false,
                    element: this.element[0],
                    resizable: true
                    //h6style_class: 'ui-heurist-publish'
                    //buttons: codeEditorBtns,
                    //default_palette_class: 'ui-heurist-publish'
                    //close: function(){}
                };   
                if(this.options.popup_position){
                    opts.position = { my: "center center", at: "center center", of: $(document) };                    
                    if(this.options.popup_position=='left' || this.options.popup_position=='right'){
                        opts.position.my = this.options.popup_position+' center';    
                    }else{
                        opts.position.my = 'center '+this.options.popup_position;    
                    }
                    opts.position.at = opts.position.my;
                }
                
                this.reportPopupDlg = window.hWin.HEURIST4.msg.showElementAsDialog(opts);
                
                this.element.parent().css('padding',0);
                this.element.width('100%');
                this.element.height('100%');
                
            }         
        }        

    },

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Cleans up the widget before it's removed. Unbinds global event listeners,
     * closes any popup dialog, and removes generated iframe/div content.
     */
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        let that = this;
        
        if(this.reportPopupDlg){
            if(this.reportPopupDlg.dialog('instance')){
                this.reportPopupDlg.dialog('close');
            }
            this.reportPopupDlg.remove();
        }

        // remove generated elements
        if(this.dosframe) this.dosframe.remove();
        if(this.div_content) this.div_content.empty();
        
        window.hWin.HEURIST4.msg.hideProgress();
    },
    
    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Appends language parameter to a URL if specified and not already present.
     * @param {string} newurl - The URL to modify.
     * @param {string} lang - The language code (e.g., 'en', 'fr'). 'def' is ignored.
     * @returns {string} The modified URL with the language parameter.
     */
    _assignLang: function (newurl, lang){
        if(lang && lang!='def'){
            if(newurl.indexOf('[lang]')>0){
                newurl = newurl.replace('[lang]', lang);
            }else if(newurl.indexOf('lang=')<0){
                newurl = newurl + '&lang=' + lang;    
            }
        }
        return newurl;
    },

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @description Shows or hides a loading animation overlay on the main content div.
     * @param {boolean} show - True to show the loading animation, false to hide it.
     */
    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
            if(this.options.showProgress){
                window.hWin.HEURIST4.msg.hideProgress(this._progressDiv);
            }
        }
    },

    /**
     * @memberof heurist.recordListExt
     * @instance
     * @private
     * @description Checks and runs crosstabs analysis if the content loaded in the iframe
     * is the crosstabs tool. It prepares the recordset data and passes it to the
     * `assignRecordset` function within the iframe's `crosstabsAnalysis` object.
     * @param {number} limit - The record limit (seems unused in current implementation of this method,
     *                         as `getIds()` is called without it).
     * @param {string} query_string_main - The main query string representing the current recordset.
     * @todo Review the `limit` parameter usage.
     */
    _checkRecordsetLengthAndRunCrosstabsAnalysis: function(limit, query_string_main){
/* @todo */
        if(!this.options.is_frame_based) return;
        
        let crosstabs = this.dosframe[0].contentWindow.crosstabsAnalysis;
        if(!crosstabs) return;

        let recordset, recIDs_list = [];

        if (this.options.recordset!=null) {

            let tot_cnt = this.options.recordset.length();
            window.hWin.HEURIST4.totalQueryResultRecordCount = tot_cnt;

            recIDs_list = this.options.recordset.getIds();//limit

            let rectype_first = 0;
            if(recIDs_list.length>0){
                let rec = this.options.recordset.getFirstRecord();
                rectype_first = this.options.recordset.fld(rec, 'rec_RecTypeID');
            }

            recordset = {
                resultCount: tot_cnt, recordCount: recIDs_list.length,
                query_main:  query_string_main,
                recIDs: recIDs_list.join(','), recTypes: this.options.recordset.getRectypes(),
                first_rt: rectype_first
            };

        }else{
            window.hWin.HEURIST4.totalQueryResultRecordCount = 0;
            
            recordset = {resultCount: 0, recordCount: 0, recIDs: "", recTypes: ""};
        }

        crosstabs.assignRecordset(recordset);

    }
    

});
