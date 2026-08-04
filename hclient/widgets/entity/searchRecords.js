/**
 * @file        searchRecords.js
 * @brief       Provides the main search interface for Heurist Records.
 * @fileOverview This complex widget handles the primary record search functionality. It integrates with various other components
 *              to allow searching by keywords, record types, and specific field values. It supports different modes
 *              for adding or browsing records and can interact with a parent entity context.
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
 * @widget heurist.searchRecords
 * @brief Main search widget for Heurist Records.
 * @augments $.heurist.searchEntity
 * @description This widget provides the primary user interface for searching and adding Heurist records.
 *              It handles selection of record types, text-based search, application of predefined filters,
 *              and interaction with parent entity contexts. The UI and behavior adapt based on various options.
 *
 * @property {string} [init_filter=''] Initial search term to populate the search input or to be used for pre-filling data on new records.
 * @property {string} [fill_data=''] Data used to pre-fill fields when adding a new record. If empty, `init_filter` may be used.
 * @property {(string|string[])} [rectype_set=null] Defines the set of record type IDs available for searching or adding.
 *           Can be a comma-separated string or an array. If multiple types are provided and less than 20, tabs may be created for each.
 *           If one type is provided, the UI simplifies.
 * @property {string} [pointer_mode='addorbrowse'] Controls the widget's primary mode:
 *           'addorbrowse' (default, allows both adding and searching),
 *           'browseonly' (disables adding capabilities, e.g., for guest users),
 *           'addonly' (focuses on adding records, may hide search elements).
 * @property {?string} pointer_filter A pre-defined Heurist query (JSON string or compatible object) to be applied as an
 *           initial, non-modifiable filter, often used when this search is part of a pointer field selection.
 * @property {?number} pointer_field_id The ID of the Heurist detail type (field) this search widget is associated with,
 *           particularly in a pointer field context. Used for contextual operations like fetching link counts.
 * @property {?number} pointer_source_rectype The record type ID of the source record when this search is used for a pointer field.
 *           Used with `pointer_field_id` for contextual operations.
 * @property {?number} parententity The ID of a parent record. If provided, the UI adjusts to show context related to this parent,
 *           and searches may exclude records already linked as children (e.g., via the DT_PARENT_ENTITY field).
 * @property {?number} parentselect The record type ID of a parent record's type, used to display informational messages about the parent context.
 * @property {?object} fixed_search A fixed Heurist query object. If provided, this query is executed directly,
 *           bypassing most other search criteria constructed from UI inputs.
 *
 * @listens heurist.searchRecords#onaddrecord - Fired when the "Add Record" button is clicked for a specific record type.
 *          Event data: `{ _rectype_id: string, fill_in_data: string }`
 *          - `_rectype_id`: The ID of the record type to be added.
 *          - `fill_in_data`: The data to pre-fill in the new record form.
 * @listens heurist.searchRecords#onlinkscount - Fired when link count data is received as part of a search result,
 *          typically when `pointer_field_id` and `pointer_source_rectype` options are active.
 *          Event data: `{ links_count: object, links_query: object }`
 *          - `links_count`: Object containing count data.
 *          - `links_query`: The query used to get the counts.
 * @listens heurist.searchRecords#onstart - Inherited from `$.heurist.searchEntity`, but also triggered here before search execution.
 *          Used to signal dependents (like a record list) to adjust their state (e.g., show loading indicator).
 */
$.widget( "heurist.searchRecords", $.heurist.searchEntity, {

    options:{
        init_filter: '',
        fill_data: ''
        // rectype_set, pointer_mode, pointer_filter, pointer_field_id, pointer_source_rectype,
        // parententity, parentselect, fixed_search are also used but inherited or set dynamically.
    },

    _select_mode: 1, //0 - add, 1 - search. Internal flag to track if interaction is for adding or searching.
    
    /**
     * @brief Initializes the controls for the record search widget.
     * @override
     * @memberof heurist.searchRecords
     * @description Sets up a complex UI involving record type selection (dropdown or tabs),
     *              "Add Record" buttons, various filter checkboxes and radio buttons (e.g., for initial filter, bookmarks),
     *              and handles special UI adjustments for modes like 'addonly', 'browseonly', or when in a 'parententity' context.
     *              Manages visibility and behavior of these controls based on widget options and user permissions.
     *              Triggers an "onstart" event at the end to signal readiness or initial search.
     */
    _initControls: function() {
        this._super();

        let that = this;

        //-----------------
        this.element.css('min-width','255px');
        this.selectRectype = this.element.find('#sel_rectypes');
        
        let rt_list = this.options.rectype_set;
        let is_expand_rt_list = false;
        let is_only_rt = false;
        if(!window.hWin.HEURIST4.util.isempty(rt_list)){
            if(!Array.isArray(rt_list)){
                rt_list = rt_list.split(',');
            }
            is_expand_rt_list = (rt_list.length>1 && rt_list.length<20);
            is_only_rt = (rt_list.length==1);
        }else{
            rt_list = [];
        }
        
        let recType_topOption = window.hWin.HR('Any Record Type');
        if(!window.hWin.HEURIST4.util.isempty(this.options.rectype_set)){

            if(this.options.rectype_set.indexOf(',') !== -1){
                recType_topOption = [{key: this.options.rectype_set, title: 'All Record Types', selected: true}]; // search all record types
            }else{
                recType_topOption = '';
            }
        }

        this.selectRectype.empty();
        window.hWin.HEURIST4.ui.createRectypeSelect(this.selectRectype.get(0), 
            this.options.rectype_set, 
            recType_topOption, 
            false);
            
        this.btn_add_record = this.element.find('.btn_AddRecord');    
        this.btn_select_rt = this.element.find( "#btn_select_rt").hide();
        
        let is_browse = (that.options.pointer_mode == 'browseonly' || window.hWin.HAPI4.is_guest_user());
        let is_addonly = (that.options.pointer_mode == 'addonly');
        
        if(that.options.pointer_mode != 'addorbrowse'){
            $('#addrec_helper > .heurist-helper1').css('visibility','hidden');
        }
        if(is_addonly){
            this.element.find('#lbl_add_record').text('Add new');
            this.element.find('.not-addonly').hide();
            this.options.pointer_filter = '';
            this._select_mode = 0;
        }else{
            this.element.find('#cb_initial').prop('checked', 
                !window.hWin.HEURIST4.util.isempty(this.options.pointer_filter));
        }
        if(window.hWin.HEURIST4.util.isempty(this.options.pointer_filter)){
            this.element.find('#lbl_initial_filter').text('');
            this.element.find('.i-filter').hide();
        }else{
            //initial pre-filter (see rst_PointerBrowseFilter)

            let plain_text = window.hWin.HEURIST4.query.jsonQueryToPlainText(this.options.pointer_filter);

            this.element.find('#lbl_initial_filter').text(this.options.pointer_filter).attr('title', plain_text);
            this.element.find('#lbl_filter_text').attr('title', plain_text);
            this.element.find('.i-filter').show();

            this._on(this.element.find('#lbl_filter_text'), {
                click: () => {
                    window.hWin.HEURIST4.msg.showMsgDlg(plain_text, null, {title: 'Preset filter'});
                }
            });

            let $tooltip = null;
            this._on(this.element.find('.i-filter'), {
                mouseenter: () => {

                    if(window.hWin.HEURIST4.util.isempty(plain_text)){
                        return;
                    }

                    $tooltip = this.element.find('#lbl_filter_text').tooltip({
                        show: {
                            delay: 500,
                            duration: 0
                        },
                        content: plain_text,
                        open: (event, ui) => {
                            ui.tooltip.css({
                                'font-size': '12px',
                                'background-color': '#D4DBEA'
                            })
                        }
                    });
                    $tooltip.tooltip('open');
                },
                mouseleave: () => {
                    if($tooltip && $tooltip.tooltip('instance') !== undefined){
                        $tooltip.tooltip('destroy');
                    }
                }
            });
        }
        if(this.options.pointer_field_id>0 && this.options.pointer_source_rectype>0){
            this.element.find('.i-counts').show();
        }else{
            this.element.find('.i-counts').hide();
        }

        this.btn_add_record
            .button({label: `<span class="btn-label">${window.hWin.HR('Add Record')}</span> <span class="ui-icon ui-icon-carat-1-s btn-dropdown" style="margin-left: 1em;" title="Select a record type to create"></span>`, 
                        icon: is_browse?null:"ui-icon-plus"})
            .addClass('ui-button-action');

        this._on(this.btn_add_record, {
            click: function(e) {

                if($(e.target).hasClass('btn-dropdown')){
                    that.btn_select_rt.trigger('click');
                    return;
                }

                let search_val = that.element.find('#fill_in_data').val();
                search_val = search_val == '' ? that.options.init_filter : search_val;
                if(!window.hWin.HEURIST4.util.isempty(search_val)){
                    window.hWin.HEURIST4.util.copyStringToClipboard(search_val);
                }

                if(that.selectRectype.val().indexOf(',') === -1){
                    that._trigger( "onaddrecord", null, {'_rectype_id': that.selectRectype.val(), 'fill_in_data': search_val} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgFlash('Cannot create a record of All types, please select type from tabs', 5000);
                }
            }
        });

        //open and adjust position of dropdown
        this._on( this.btn_select_rt, {
            click:  function(){
                this._select_mode = 0;
                this.selectRectype.hSelect('open'); console.log(this.selectRectype.hSelect('menuWidget'));
                this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.btn_add_record });
                return false;
            }
        });

        if(is_browse){
            this.element.find('#lbl_add_record').text('');
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.show();
        }

        // create list of tabs for every rectype in this.options.rectype_set
        if(!is_addonly && is_expand_rt_list){
            
            this.element.find('label[for="sel_rectypes-button"]').hide();
            this.element.find('#sel_rectypes-button').hide();
            let cont = this.element.find('#row_tabulator');

            let groupTabHeader = $('<ul>').appendTo(cont);
            
            if(rt_list.length > 1){
                    $('<li>').html('<a href="#rty'+ rt_list.join(',') +'"><span style="font-weight:bold">All valid types</span></a>')
                        .appendTo(groupTabHeader);
                    $('<div id="rty'+ rt_list.join(',') +'">').appendTo(cont);
            }

            for(let idx=0; idx<rt_list.length; idx++){
                
                let rectypeID = rt_list[idx];
                if(rectypeID>0){
                    let name = $Db.rty(rectypeID,'rty_Name');
                    let label = window.hWin.HEURIST4.util.htmlEscape(name.trim());
                    if(!name) continue;

                    $('<li>').html('<a href="#rty'+rectypeID
                                        +'"><span style="font-weight:bold">'
                                        +label+'</span></a>')
                            .appendTo(groupTabHeader);
                    $('<div id="rty'+rectypeID+'">').appendTo(cont);
                }
            }//for
            
            //on switch - change filter
            cont.tabs({activate:function( event, ui ) {
                let rtyid = ui.newPanel.attr('id').slice(3);
                that._select_mode = 1; //search
                that.selectRectype.val( rtyid ).hSelect('refresh');
                that.selectRectype.trigger('change');
            }});
            groupTabHeader.css('background','none');
            
        }else{

            //adjust position of dropdown    
            this.sel_rectypes_btn = this.element.find( "#sel_rectypes-button");
            this._on( this.sel_rectypes_btn, {
                click:  function(){
                    this._select_mode = 1;
                    this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.sel_rectypes_btn });
            }});

            this.btn_add_record.parent().controlgroup();

            if(is_only_rt){
                this.element.find('label[for="sel_rectypes-button"]').hide();
                this.element.find('#sel_rectypes-button').hide();
                this.btn_add_record.find('.btn-dropdown').hide();

                if(is_addonly){

                    let search_val = that.element.find('#fill_in_data').val();
                    search_val = search_val == '' ? that.options.init_filter : search_val;
                    if(!window.hWin.HEURIST4.util.isempty(search_val)){
                        window.hWin.HEURIST4.util.copyStringToClipboard(search_val);
                    }

                    if(that.selectRectype.val().indexOf(',') === -1){
                        that._trigger( "onaddrecord", null, {'_rectype_id': that.selectRectype.val(), 'fill_in_data': search_val} );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('Cannot create a record of All types, please select type from tabs', 5000);
                    }
                }
            }else if(is_addonly){
                that.btn_select_rt.trigger('click'); //show dropdown
            }

        }//is_expand_rt_list    
        
        // change label for btn_add_record 
        //
        function __onSelectRecType(sel){

            if(is_browse){
                that.btn_add_record.hide();
            }else{
                that.btn_add_record.show();
            }

            if(that.btn_add_record.is(':visible')){

                let lbl;
                if(sel.val() > 0){
                    lbl = `${window.hWin.HR('Add')} <span style="margin-left: 1em;">${sel.find('option:selected').text()}</span>`.trim();
                }else{
                    lbl = window.hWin.HR('Add Record');
                }

                that.btn_add_record.find('.btn-label').html(lbl);
            }

            if(is_browse){
                that.element.find('#lbl_add_record').text('');
            }else if(is_addonly){
                that.element.find('#lbl_add_record').text('Add new');
            }else{
                that.element.find('#lbl_add_record').html('<span style="padding: 0px 5px;">OR</span> Add a new record');
            }
        }
        //force search if rectype_set is defined
        this._on( this.selectRectype, {
            change: function(event){
                __onSelectRecType(this.selectRectype);
                if(this._select_mode==1){
                    this.startSearch();    
                }else{
                    this.btn_add_record.trigger('click');
                }
                
            }
        });

        this._on( this.element.find('input[type=radio], input[type=checkbox]'), {
            change: function(event){
                this.startSearch();

                if(event.target.id == 'rb_selected'){
                    window.hWin.HAPI4.save_pref('rSearch_Recent', event.target.checked);
                }
            }
        });
		
        // User Preference for filter buttons
        let filter_pref = window.hWin.HAPI4.get_prefs_def('rSearch_filter', 'rb_alphabet');
        if (filter_pref != 'rb_alphabet'){
            this.element.find('#'+filter_pref).prop('checked', true);
        }

        let show_recent = !window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.get_prefs('recent_Records')) 
                            && window.hWin.HAPI4.get_prefs_def('rSearch_Recent', true);
        this.element.find('#rb_selected').prop('checked', show_recent);

        if(is_addonly){
            __onSelectRecType(this.selectRectype);
        }else{
            if(this.options.parententity>0){
                
                this.element.find('#row_parententity_helper4').show();
                this.element.find('#parententity_header').show();
                this._on(this.element.find('#parententity_header'), {
                    'click': function(event){

                        let $header_icon = this.element.find('#parententity_header .ui-icon');
                        let is_expanding = $header_icon.hasClass('ui-icon-triangle-1-e');
                        let is_results_only = $header_icon.parent().hasClass('search-results-only');

                        $header_icon.toggleClass('ui-icon-triangle-1-e ui-icon-triangle-1-s');

                        if(is_expanding){
                            if(!is_results_only){
                                this.element.find('#row_parententity_helper, #row_parententity_helper2').css({'display':'table-row'});
                                this.element.parent().find('.recordList').hide();
                            }else{
                                this.element.find('#row_parententity_helper2').hide();
                                this.element.find('#row_parententity_helper').css({'display':'table-row'});
                                this.element.parent().find('.recordList').show();
                            }
                            this.element.find('#row_parententity_helper4').hide();
                        }else{
                            this.element.find('#row_parententity_helper, #row_parententity_helper2').hide();
                            this.element.parent().find('.recordList').hide();
                            this.element.find('#row_parententity_helper4').show();
                        }
                    }
                });
                this.element.parent().find('.recordList').hide();
                this.element.find('#row_parententity_helper2').parent('.ent_header').css({'z-index':99});
                
                this.btn_search_start2 = this.element.find('#btn_search_start2').css({height:'20px',width:'20px'})
                    .button({showLabel:false, icon:"ui-icon-search", iconPosition:'end'});
                    
                this._on( this.btn_search_start2, {click: this.startSearch });
                    
                __onSelectRecType(this.selectRectype);
            }else{
                if(this.options.parentselect>0){
                    let ele = this.element.find('#row_parententity_helper3').css({'display':'table-row'});
                    ele.find('span').text( $Db.rty(this.options.parentselect,'rty_Name') );
                }
                //start search
                if(!window.hWin.HEURIST4.util.isempty(this.selectRectype.val())){
                    this.selectRectype.trigger('change'); 
                }
            }
        }
        
        this.input_search.trigger('focus');

        this.element.find('#fill_in_data').parent().hide();
        if(!window.hWin.HEURIST4.util.isempty(this.options.fill_data) || !window.hWin.HEURIST4.util.isempty(this.options.init_filter)){

            this.input_search.val(this.options.init_filter); // enter value

            if(!window.hWin.HEURIST4.util.isempty(this.options.fill_data)){

                //move search box
                this.input_search.css({'max-width': '20em', 'width': '20em'}).parent().css({
                    'display': 'block',
                    'position': 'relative',
                    'z-index': 1,
                    'text-align': ''
                });

                let fill_in = !window.hWin.HEURIST4.util.isempty(this.options.fill_data) ? this.options.fill_data : this.options.init_filter;
                this.element.find('#fill_in_data').val(fill_in).css({'max-width': '20em', 'width': '20em'}).parent().show();
            }else{
                this.options.init_filter = '';
            }
        }

        this._trigger( "onstart" ); //trigger ajust          
        
        if(this.options.fixed_search || this.options.parententity > 0 || !window.hWin.HEURIST4.util.isempty(this.options.init_filter)){
            setTimeout(() => { that.startSearch(); }, 500);
        }
    },  

    /**
     * @brief Initiates a record search based on the current UI state.
     * @override
     * @memberof heurist.searchRecords
     * @description Constructs a Heurist query object based on selections in the record type dropdown/tabs,
     *              text in the search input, state of filter checkboxes (e.g., bookmarks, initial filter),
     *              and sort preferences (modified date or alphabetical).
     *              Handles special conditions like `parententity` (to exclude children) and `fixed_search` (to use a predefined query).
     *              If `pointer_field_id` and `pointer_source_rectype` are set, it may request link counts.
     *              Triggers "onstart", "onresult", and potentially "onlinkscount" events.
     */
    startSearch: function(){
        
        let ele = this.element.find('#row_parententity_helper2').hide();
        ele = this.element.find('#parententity_header');

        if(ele.is(':visible')){
            ele.addClass('search-results-only');
            ele.find('.ui-icon').addClass('ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-e');
            this.element.parent().find('.recordList').show();
        }

        let qstr = '', domain = 'a', qobj = [];
        
        let links_count = null;

        //by record type
        let rectype_filter = this.selectRectype.val();
        if(rectype_filter!=''){
            qstr = qstr + 't:'+rectype_filter;
            qobj.push({"t":rectype_filter});
            
            if(this.options.pointer_field_id>0 && this.options.pointer_source_rectype>0){
                if(this.element.find('#cb_getcounts').is(':checked')){
                    links_count = {source:this.options.pointer_source_rectype, 
                                   target:this.rectype_filter, // Note: this.rectype_filter is not explicitly defined, likely meant rectype_filter
                                   dty_ID:this.options.pointer_field_id};
                }            
            }    
        }

        //by title        
        if(this.input_search.val().trim()!=''){
            let is_whole_phrase = true;
            let s = this.input_search.val().trim();
            if(s.length>4 && s[0]=='"' && s[s.length-1]=='"'){
                s = s.substring(1,s.length-2);
                is_whole_phrase = true;
            }else{
                s = s.split(' ');
                is_whole_phrase = !(s.length>1);
            }    
                
            if(is_whole_phrase){
                s = this.input_search.val().trim()
                qstr = qstr + ' title:'+s;
                qobj.push({"title":s});
            }else{        
                for(let i=0;i<s.length;i++)
                if(s[i]!=''){
                    qobj.push({"title":s[i]});    
                    qstr = qstr + ' title:'+s[i];
                }
            }
        
        }

        //exclude already children
        if(this.options.parententity>0){
            //filter out records with parent entiy (247) field
            let DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
            let pred = {}; pred["f:"+DT_PARENT_ENTITY]="NULL";
            qobj.push(pred);
            
            this.element.find('#parententity_helper').css({'display':'table-row'});
        }
        
        let limit = 100000;
        let needall = 1
        
        if(this.element.find('#rb_modified').is(':checked')){
            qstr = qstr + ' sortby:-m after:"1 week ago"';
            qobj.push({"sortby":"-m"}); // after:\"1 week ago\"
            limit = 100;
            needall = 0;

            window.hWin.HAPI4.save_pref('rSearch_filter', 'rb_modified');
        }else{
            // qstr = 'sortby:t'; // This was overriding previous qstr, seems incorrect
            qobj.push({"sortby":"t"}); //sort by record title
        }

        if(this.element.find('#rb_alphabet').is(':checked')){
            window.hWin.HAPI4.save_pref('rSearch_filter', 'rb_alphabet');
        }
		
        if(this.element.find('#cb_bookmarked').is(':checked')){
            domain = 'b';
        }       
        
        if(this.element.find('#cb_initial').is(':checked')){
            qobj = window.hWin.HEURIST4.query.mergeHeuristQuery(qobj, 
                            (this.options.pointer_filter?this.options.pointer_filter:''));
        }
        
        if(this.options.fixed_search){
            qstr = 'x'; // Indicate fixed search, though qobj is primary driver
            qobj = this.options.fixed_search;
        }
        
        if(qobj.length === 0 && qstr === ''){ // Check if qobj is empty as well
            this._trigger( "onresult", null, {recordset:new HRecordSet()} );
        }else{
            this._trigger( "onstart" );

            let request = { 
                //q: qstr, // qstr is mostly for debugging or simpler queries; qobj is more robust
                q: qobj,
                w: domain,
                limit: limit,
                needall: needall,
                detail: 'ids',
                links_count: links_count,
                id: window.hWin.HEURIST4.util.random()}
           

            let that = this;                                                
           

            window.hWin.HAPI4.RecordMgr.search(request, function( response ){
               
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(response.data.links_count){
                        that._trigger( "onlinkscount", null, 
                            {links_count: response.data.links_count,
                             links_query: response.data.links_query});
                    }else{
                        that._trigger( "onlinkscount", null, 
                            {links_count: null,
                             links_query: null});
                    }
                    
                    let order_recent = !window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.get_prefs('recent_Records'))
                                        && that.element.find('#rb_selected').prop('checked');

                    let recset = new HRecordSet(response.data);

                    if(order_recent){

                        let recent = window.hWin.HAPI4.get_prefs('recent_Records').split(',');
                        let order = recset.getOrder();
                        let changed_order = false;

                        for(let id of recent){

                            const rec_id = parseInt(id, 10);
                            const cur_idx = order.indexOf(rec_id);

                            if(cur_idx === -1){
                                continue;
                            }

                            order.splice(cur_idx, 1);
                            order.unshift(rec_id);

                            changed_order = true;
                        }

                        if(changed_order){
                            recset.setOrder(order);
                        }
                    }

                    that._trigger( "onresult", null, {recordset:recset, request:request} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });
            
        }            
    }
    

});
