/**
* Query result listing.
*
* Requires hclient/widgets/viewers/resultListMenu.js (must be preloaded)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.resultList", {

    // default options
    options: {
        is_h6style: false,
        view_mode: null, // 'list','icons','thumbs','thumbs3','horizontal','icons_list','record_content' 
        list_mode_is_table: false,

        select_mode:null,//none, manager, select_single, select_multi
        selectbutton_label:'Select',
        action_select:null,  //array of actions
        action_buttons:null,
                           
        //action for onselect event - open preview inline, popup or none - used in cms for example
        recordview_onselect: false, //false/none, inline or popup - show record viewer/info on select
        multiselect: true,    //allows highlight several records

        eventbased:true, //if false it does not listen global events

        show_toolbar: true,   //toolbar contains menu,savefilter,counter,viewmode and paginathorizontalion
        show_search_form: false,
        show_menu: false,       //@todo ? - replace to action_select and action_buttons
        support_collection: false,
        support_reorder: false,  // show separate reorder button
        show_savefilter: false,
        show_counter: true,
        show_viewmode: true,
        show_inner_header: false, // show title of current search in header (above toolbar)
        header_class: null,       //class name for menu
        show_url_as_link:false,

        title: null,  //see show_inner_header
        //searchsource: null,

        emptyMessageURL: null, //url of page to be loaded on empty result set 
        empty_remark:'No entries match the filter criteria (entries may exist but may not have been made visible to the public or to your user profile)', //html content for empty message
        pagesize: -1,
        
        groupByMode: null, //[null|'none','tab','accordion'],
        groupByField:null,
        groupByRecordset: null,
        groupOnlyOneVisible:false,
        groupByCss:null, //css for group content

        renderer: null,    // custom renderer function to draw item
        rendererHeader: null,   // renderer function to draw header for list view-mode (for content)
        rendererGroupHeader: null,   // renderer function for group header (see groupByField)
        
        recordDivClass: '', // additional class that modifies recordDiv appearance (see for example "public" in h4styles.css) 
                            // it is used if renderer is null
        recordDivEvenClass:null,  //additional class for even entries recordDiv
        
        // smarty template or url (or todo function) to draw inline record details when recordview_onselect='inline'. (LINE view mode only)
        rendererExpandDetails: null,  //name of smarty template or function to draw expanded details
        rendererExpandInFrame: true, 
        expandDetailsOnClick: true,

        searchfull: null,  // custom function to search full data
        
        sortable: false, //allows drag and sort entries
        onSortStop: null,
        draggable: null, // callback function to init dragable - it is called after 
                         // finish render and assign draggable widget for all record divs
        droppable: null, //callback function to init dropable (see refreshPage)

        //events  
        onselect: null,  //on select event 

        onPageRender: null, //event listner on complete of page render

        navigator:'auto',  //none, buttons, menu, auto

        entityName:'records',   //records by default
        
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null,  //NOT USED query string or svs_ID for initial search
        
        supress_load_fullrecord: false, //do not load full record data
        
        transparent_background: false,
        
        aggregate_values: null, //supplementary values per record id - usually to store counts, sum, avg 
        aggregate_link: null,    //link to assigned to aggregate value label
		
		blog_result_list: false	//whether the result list is used for blog records, limiting pagesize if it is
    },

    _is_publication:false, //this is CMS publication - take css from parent
    
    _query_request: null, //keep current query request

    _events: null,                                                   
    _lastSelectedIndex: null, //required for shift-click selection
    _count_of_divs: 0,

    //navigation-pagination
    current_page: 0,
    max_page: 0,
    count_total: null,  //total records in query - actual number can be less
    hintDiv:null, // rollover for thumbnails
    _current_view_mode: null,

    _currentRecordset:null,
    _currentMultiSelection:null, //for select_multi - to keep selection across pages and queries
    _fullRecordset:null, //keep full set for multiselection (to get records diregard current filters)

    _startupInfo:null,

    _init_completed:false,
    
    _expandAllDivs: false,
    
    _myTimeoutCloseRecPopup: 0,
    _myTimeoutOpenRecPopup: 0, 
    
    _grp_keep_status:{}, //expanded groups
    
    // the constructor
    _create: function() {

        var that = this;

        if(this.options.pagesize<50 || this.options.pagesize>5000){
            if(this.options.blog_result_list==true){
                this.options.pagesize = 10;
            }
            else{
                this.options.pagesize = window.hWin.HAPI4.get_prefs('search_result_pagesize');
            }
        }

        this._is_publication  = this.element.parent().attr('data-heurist-app-id');
        
        //this.element.css({'font-size':'0.9em'});

        this._initControls();

        //-----------------------     listener of global events
        if(this.options.eventbased)
        {
            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
                + ' ' + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
                + ' ' + window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_COLLECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;

            /*    
            this.document[0].addEventListener('start_search', function(event) {
                   console.log('CUSTOM EVENT!!!!');
                });
            */    
                
            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){

                    that._showHideOnWidth();

                }else  if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(!window.hWin.HAPI4.has_access()){ //logout
                        that.updateResultSet(null);
                    }
                    that._refresh();

                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                    //if(!that._isSameRealm(data)) return;
                    that.setCollected( data.collection );
                
                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
                {
                    
                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;

                    that.span_pagination.hide();
                    that.span_info.hide();

                    that.setSelected(null);
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:null, source:that.element.attr('id'), search_realm:that.options.search_realm} );
                        
                    if(that.options.show_search_form){
                        if (data.primary_rt && !data.ispreview)
                        {
                            //this is faceted search - hide input search form
                            that.div_search_form.hide();
                        }else{
                            that.div_search_form.show();
                        }
                        that._adjustHeadersPos();
                    }

                    if(data.reset){
                        
                        //fake restart
                        that.clearAllRecordDivs('');
                        //that.loadanimation(true);
                        that._renderStartupMessageComposedFromRecord();
                        
                    }else{
                        
                        if(that._query_request==null || data.id!=that._query_request.id) {  //data.source!=that.element.attr('id') ||
                            //new search from outside
                            var new_title;
                            if(data.qname>0 && window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                                window.hWin.HAPI4.currentUser.usr_SavedSearch[data.qname])
                            {
                                that._currentSavedFilterID = data.qname;
                                new_title = window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][_NAME];
                            }else{
                                that._currentSavedFilterID = 0;
                                if(data.qname>0 || window.hWin.HEURIST4.util.isempty(data.qname)){
                                    new_title = window.hWin.HR('Filtered Result');            
                                }else{
                                    new_title = window.hWin.HR(data.qname);
                                }
                                
                            }
                            
                            that.clearAllRecordDivs(new_title);
                            
                            if(that.search_save_hint){
                                that.search_save_hint.attr('show_hint', data.qname?0:1);
                                //that.search_save_hint.show('slide', {}, 1000); //.animate({left: '250px'});
                            }
                            

                            if(!window.hWin.HEURIST4.util.isempty(data.q)){
                                that.loadanimation(true);
                                that._renderProgress();
                            }else{
                                that._renderMessage('<div style="font-style:italic;">'+window.hWin.HR(data.message)+'</div>');
                            }

                        }

                        that._query_request = data;  //keep current query request

                    }

                    that._renderSearchInfoMsg(null);

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;
                    
                    that.loadanimation(false);
                    
                    var recset = data.recordset;

                    if(recset==null){
                        
                        if(data.empty_remark){
                            that.div_content.html( data.empty_remark );
                        }else{

                            var recID_withStartupInfo = window.hWin.HEURIST4.util.getUrlParameter('Startinfo');
                            if(recID_withStartupInfo>0){
                                that._renderStartupMessageComposedFromRecord();
                                
                            }else if(that.options.emptyMessageURL){
                                    that.div_content.load( that.options.emptyMessageURL );
                            }else{
                                    that._renderEmptyMessage(0);
                            }
                        }

                        if(that.btn_search_save){
                            that.btn_search_save.hide();
                        } 
                    }else if(that._query_request!=null && recset.queryid()==that._query_request.id) {
                        
                        //it accepts only results that has the same query id as it was set in ON_REC_SEARCHSTART
                        
                            
                        if(that._query_request.viewmode){
                                that.applyViewMode( that._query_request.viewmode );
                        }

                        that._renderRecordsIncrementally(recset); //hRecordSet - render record on search finish
                        
                        if(that.btn_search_save) {
                            that.btn_search_save.show();
                            if(that.search_save_hint.attr('show_hint')==1){
                                that.search_save_hint.show('fade',{},1000);
                                setTimeout(function(){that.search_save_hint.hide('slide', {}, 6000);}, 5000);    
                            }
                        }
                    }
                    
                    that._showHideOnWidth();
                    that._renderPagesNavigator();
                    that._renderSearchInfoMsg(recset);
                    

                }
                else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //this selection is triggered by some other app - we have to redraw selection
                    if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {
                        
                        if(data.reset){ //clear selection
                            that.setSelected(null);
                        }else{
                            if(data.map_layer_status){  //visible hidden loading error
                                
                                if(data.selection && data.selection.length==1){
                                    
                                    var rdiv = that.div_content.find('.recordDiv[recid="'+data.selection[0]+'"]');
                                    if(rdiv.length>0)
                                    {
                                        rdiv.find('.rec_expand_on_map > .ui-icon').removeClass('rotate');
                                        if(data.map_layer_status=='visible'){
                                            s = 'Hide data';
                                            
                                            //zoom to loaded data
                                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                                            {selection:data.selection, 
                                                map_layer_action: 'zoom',
                                                source:that.element.attr('id'), search_realm:that.options.search_realm} );
                                            
                                        }else if(data.map_layer_status=='loading'){
                                            s = 'loading';
                                            rdiv.find('.rec_expand_on_map > .ui-icon').addClass('rotate');
                                        }else if(data.map_layer_status=='error'){
                                            s = 'Error';
                                            rdiv.find('.rec_expand_on_map > .ui-icon').removeClass('ui-icon-globe').addClass('ui-icon-alert');
                                        }else{
                                            s = 'Show data';
                                        }
                                        
                                        rdiv.find('.rec_expand_on_map').attr('data-loaded', data.map_layer_status);
                                        rdiv.find('.rec_expand_on_map > .ui-button-text').text( s );
                                    }
                                    
                                }
                                
                            }else{
                                that.setSelected(data.selection);        
                            }
                        }
                        
                        
                    }
                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)
                {
                    that.options.pagesize = window.hWin.HAPI4.get_prefs('search_result_pagesize');
                }
                //that._refresh();
            });

        }

        this._init_completed = true;

        this._refresh();

        this._renderStartupMessageComposedFromRecord();
        
        // 
        if(this.options.search_initial){
            
            var request = {q:this.options.search_initial, w: 'a', detail: 'ids', 
                        source:'init', search_realm: this.options.search_realm };
            window.hWin.HAPI4.SearchMgr.doSearch(this.document, request);
            
        }else if(false){
            this.updateResultSet(recordset); // render records if search in this realm has been performed earlier
        }
        
    
    }, //end _create
    
    //
    //
    //
    _isSameRealm: function(data){
        return !this.options.search_realm || (data && this.options.search_realm==data.search_realm);
    },

    //
    //
    //
    _initControls: function() {

        var that = this;

        var right_padding = window.hWin.HEURIST4.util.getScrollBarWidth()+1;

        /*if(hasHeader){
        var hasHeader = ($(".header"+that.element.attr('id')).length>0);
        var header = $(".header"+that.element.attr('id'));
        header.css({'padding-left':'0.7em','background':'none','border':'none'}).html('<h3>'+header.text()+'</h3>');
        header.parent().css({'background':'none','border':'none'});
        header.parent().parent().css({'background':'none','border':'none'});
        } */

        //------------------------------------------       

        this.div_coverall = $( "<div>" )                
        .addClass('coverall-div-bare')
        .css({'zIndex':10000,'background':'white'})
        .hide()
        .appendTo( this.element );

        //------------------------------------------       

        this.div_header =  $( "<div>" ).css('height','auto').appendTo( this.element ); //41px
        
        if(this.options.is_h6style){

            $('<div class="result-list-header ui-widget-content" ' //was ui-heurist-heade
            + 'style="font-size:1em;text-align:left;padding-left:12px;position:relative;'
            + 'font-weight: bold;letter-spacing: 0.26px;padding:10px;"/>')
                .appendTo( this.div_header );
        }else{
        
            //padding left to align to search input field
            $('<div class="result-list-header">')
                .css({'padding':'0.7em 0 0 20px', 'font-size': '1.17em'})   
                .appendTo(this.div_header);
        }
        
        
        //add label to display number of selected, button and selected onlu checkbox
        if(this.options.select_mode=='select_multi'){
            this.show_selected_only = $( "<div>" )
            .addClass('ent_select_multi')  //ui-widget-content 
            .css({'right':right_padding+2})
            .html(
                '<label style="padding:0 0.4em;">'
                +'<input id="cb_selected_only" type="checkbox" style="vertical-align:-0.3em;"/>'
                +'&nbsp;show selected only</label>'
                +'<div id="btn_select_and_close"></div>')
            .appendTo( this.div_header );

            //init checkbox and button
            this.btn_select_and_close = this.element.find('#btn_select_and_close')
            .css({'min-width':'11.9em'})
            .button({label: window.hWin.HR( this.options.selectbutton_label )})
            .click(function(e) {
                that._trigger( "onaction", null, 'select-and-close' );
            });

            this.cb_selected_only = this.element.find('#cb_selected_only')
            this._on( this.cb_selected_only, {
                change: this.showRetainedSelection} );

        }

        // -------------------------------------------

        this.div_toolbar = $( "<div>" )
        .addClass('div-result-list-toolbar ent_header')
        .css({'width':'100%','top':'0px'})
        .appendTo( this.element );

        this.div_content = $( "<div>" )
        .addClass('div-result-list-content ent_content_full')
        //.css({'border-top':'1px solid #cccccc'})  //,'padding-top':'1em'
        .css({'overflow-y':'auto'})
        .appendTo( this.element );
        

        this.div_loading = $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.element ).hide();

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_buttons)){

            this.action_buttons_div.css({'display':'inline-block', 'padding':'0 0 4px 1em'})
                .hide().appendTo( this.div_toolbar );    
            
            var idx = 0;
            for(idx in this.options.action_buttons){

                var key = this.options.action_buttons[idx].key;
                var title = this.options.action_buttons[idx].title;

                var btn_icon = null;
                if(key=='add') btn_icon = 'ui-icon-plus'
                else if(key=='edit') btn_icon = 'ui-icon-pencil'
                    else if(key=='edit_ext') btn_icon = 'ui-icon-newwin'
                        else if(key=='delete') btn_icon = 'ui-icon-minus';

                btn_icon = {primary: btn_icon};
                $('<div>',{'data-key':key}).button({icons: btn_icon, text:true, label:window.hWin.HR(title) })
                .appendTo(this.action_buttons_div)
                .click(function( event ) {
                    var key = $(event.target).parent().attr('data-key');
                    that._trigger( "onaction", null, key );
                });
            }

        }
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_select)){

            var idx = 0;
            var smenu = "";
            for(idx in this.options.action_select){
                var key = this.options.action_select[idx].title
                var title = this.options.action_select[idx].title;
                smenu = smenu + '<li data-key="'+key+'"><a href="#">'+window.hWin.HR(title)+'</a></li>';
            }

            this.menu_actions = $('<ul>'+smenu+'</ul>')   //<a href="#">
            .css({position:'absolute', zIndex:9999})
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var key =  ui.item.attr('data-key');
                    that._trigger( "onaction", null, key );
            }})
            .hide();

            this.btn_actions = $( "<button>" )
            .appendTo( this.action_buttons_div )
            .button({icons: { secondary: "ui-icon-triangle-1-s"}, text:true, label: window.hWin.HR("Actions")});

            this._on( this.btn_actions, {
                click: function() {
                    $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                    var menu = $( this.menu_actions )
                    //.css('width', this.div_search_as_user.width())
                    .show()
                    .position({my: "right top", at: "right bottom", of: this.btn_actions });
                    $( document ).one( "click", function() { menu.hide(); });
                    return false;
                }
            });
        }

        //------------------
        this.reorder_button = $( '<button>' )
                .button({icon: "ui-icon-signal", showLabel:false, label:window.hWin.HR('reorder')})
                .css({'font-size':'0.93em','float':'right','margin':'2px '+right_padding+'px'})
                .hide()
                .appendTo( this.div_toolbar );
        this.reorder_button.find('span.ui-icon').css({'transform':'rotate(90deg)'});
                
        this._on(this.reorder_button, {click: this.setOrderAndSaveAsFilter});

        //------------------
        this.view_mode_selector = $( "<div>" )
        //.css({'position':'absolute','right':right_padding+'px'})
        .css({'float':'right','padding':'2px '+right_padding+'px'})
        .html('<button value="list" class="btnset_radio"/>'
            +'<button value="icons" class="btnset_radio"/>'
            +'<button value="thumbs" class="btnset_radio"/>'
            +'<button value="thumbs3" class="btnset_radio"/>'
            +(this.options.entityName=='records'?'<button value="record_content" class="btnset_radio"/>':'')
        )
        .appendTo( this.div_toolbar );
        
        this.view_mode_selector.find('button[value="list"]')
            .button({icon: "ui-icon-menu", showLabel:false, label:window.hWin.HR('Single lines')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="icons"]')
            .button({icon: "ui-icon-list", showLabel:false, label:window.hWin.HR('Single lines with icon')})
            .css('font-size','1em'); //ui-icon-view-icons-b
        this.view_mode_selector.find('button[value="thumbs"]')
            .button({icon: "ui-icon-view-icons", showLabel:false, label:window.hWin.HR('Small images')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="thumbs3"]')
            .button({icon: "ui-icon-stop", showLabel:false, label:window.hWin.HR('Large image')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="record_content"]')
            .button({icon: "ui-icon-template", showLabel:false, label:window.hWin.HR('Record contents')})
            .css('font-size','1em'); //ui-icon-newspaper
        this.view_mode_selector.controlgroup();
        
        this._on( this.view_mode_selector.find('button'), {
            click: function(event) {
                    var btn = $(event.target).parent('button');
                    var view_mode = btn.attr('value');

                    this.applyViewMode(view_mode);
                    window.hWin.HAPI4.save_pref('rec_list_viewmode_'+this.options.entityName, view_mode);
        }});

        this.span_pagination = $( "<div>")
        .css({'float':'right','padding': '3px 0.5em 0 0'})
        //'vertical-align':'top',
        //.css({'float':'right','padding':'6px 0.5em 0 0'})
        .appendTo( this.div_toolbar );

        this.span_info = $( "<div>")
        .css({'float':'right','padding': '6px 0.5em 0 0','font-style':'italic'})
        //'vertical-align':'top',
        //.css({'float':'right','padding':'0.6em 0.5em 0 0','font-style':'italic'})
        .appendTo( this.div_toolbar );

        
        if(this.options.is_h6style && this.options.show_search_form){

                this.div_search_form = $('<div>').search({
                        is_h6style: this.options.is_h6style,
                        btn_visible_newrecord: false,
                        search_button_label: 'Filter',
                        btn_entity_filter: false})
                    .css({  //display:'block','max-height':'55px','height':'55px',
                            padding:'15px 10px 0px 4px','border-bottom':'1px solid gray'}) //,width:'100%'
                    .appendTo(this.div_header);
        
        }
        
        if(this.options.show_menu){
            if($.isFunction($('body').resultListMenu)){

                this.div_actions = $('<div>').resultListMenu({
                        is_h6style: this.options.is_h6style,
                        menu_class: this.options.header_class,
                        resultList: this.element});
                
                if(this.options.is_h6style){
                    
                    this.div_actions.css({display:'inline-block','padding':'4px','min-height':'30px'})
                    .appendTo(this.div_header);
                    
                }else{
                    this.div_actions.css({display:'inline-block','padding-bottom':'4px','padding-left':'6px'})
                    //.css({'position':'absolute','top':3,'left':2})
                    .appendTo(this.div_toolbar);
                }
            }
        }else{
            
        }    
        
        //
        //
        if(this.options.show_savefilter){
            //special feature to save current filter
            //.css({position:'absolute',bottom:0,left:0,top:'2.8em'})
            var btndiv = $('<div>').css({display:'block',height:'2.4em','padding-left':'5px'})
                .appendTo(this.div_toolbar);
            if(!this.options.is_h6style){
                btndiv.addClass('ui-widget-content')
            }else{
                btndiv.css({'padding-left':'9px'})
            }
                
            this.btn_search_save = $( "<button>", {
                text: window.hWin.HR('Save Filter'),
                title: window.hWin.HR('Save the current filter and rules as a link in the navigation tree')
            })
            .css({'min-width': '80px','font-size':'0.8em', 'height': '21px', background: 'none'})
            .addClass(this.options.is_h6style?'ui-heurist-btn-header1':'ui-state-focus svs-header')
            .appendTo( btndiv )
            .button({icons: {
                primary: 'ui-icon-arrowthick-1-w'
            }}).hide();
            
            this.search_save_hint = $('<span style="padding-left:10px;font-size:0.8em">'
            +'<span class="ui-icon ui-icon-arrow-1-w" style="font-size:0.9em"/>click me to save the current filter for future navigation &nbsp;</span>')
                    .appendTo( btndiv ).hide();

            this.btn_search_save.find('.ui-button-icon-primary').css({'left':'0.1em'});

            this._on( this.btn_search_save, {  click: function(){
                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                    
                    var  app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('svs_list');
                    if(app && app.widget){
                        $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method
                    }else{
                        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                        if(widget){
                            widget.mainMenu6('addSavedSearch', 'saved'); //call public method
                        }
                        
                    } 
                });
            } });    
            
            //order manually and save as search filter
            /*
            this.btn_search_save_withorder = $( "<button>", {
                text: window.hWin.HR('Re-order and Save'),
                title: window.hWin.HR('Allows manual reordering of the current results and saving as a fixed list of ordered records')
            })
            .css({'min-width': '80px','font-size':'0.8em', 'height': '21px', background: 'none', float:'right'})
            .addClass('ui-state-focus svs-header')
            .appendTo( btndiv )
            .button().hide();
            
            this._on( this.btn_search_save_withorder, {  click: this.setOrderAndSaveAsFilter });    
            */
            
        }             
        
        
        
        if(this.options.header_class){
            this.div_header.addClass(this.options.header_class);
            this.div_toolbar.removeClass('ui-heurist-bg-light').addClass(this.options.header_class);
            this.view_mode_selector.find('button').addClass(this.options.header_class).css({'border':'none'});
            this.span_pagination.find('button').addClass(this.options.header_class).css({'border':'none'});
        }

        
        this._showHideOnWidth();

        //-----------------------
        this.setHeaderText(window.hWin.HR('Filtered Result'));

    },
    
    _setOptions: function() {
        /*if(!(arguments['pagesize']>0)){
        arguments['pagesize'] = 9999999999999;
        }*/
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rendererHeader' || key == 'view_mode'){
            this.applyViewMode(this.options.view_mode, true);
        }
    },


    /* private function */
    _refresh: function(){

        if(!this._init_completed) return;

        this.applyViewMode(this.options.view_mode);

        if(window.hWin.HAPI4.currentUser.ugr_ID>0){
            $(this.div_toolbar).find('.logged-in-only').css('visibility','visible');
            $(this.div_content).find('.logged-in-only').css('visibility','visible');
        }else{
            $(this.div_toolbar).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
        }

        if(this._is_publication || this.options.transparent_background){
            //this is CMS publication - take css from parent
            this.element.removeClass('ui-heurist-bg-light').addClass('ui-widget-content').css({'background':'none','border':'none'});
            this.div_toolbar.css({'background':'none'});
            this.div_content.removeClass('ui-heurist-bg-light').css({'background':'none'});
        }else{
            this.element.addClass('ui-heurist-bg-light');
            
            if(true || this.element.parent().hasClass('ui-widget-content')){
                this.element.parent().css({'background':'none','border':'none'});
            }

            //this.div_toolbar.addClass('ui-heurist-bg-light');
            this.div_content.addClass('ui-heurist-bg-light');
        }
        
        
/*
        var abtns = (this.options.actionbuttons?this.options.actionbuttons:"tags,share,more,sort,view").split(',');
        var that = this;
        $.each(this._allbuttons, function(index, value){

        var btn = that['btn_'+value];
        if(btn){
        btn.css('display',($.inArray(value, abtns)<0?'none':'inline-block'));
        }
        });
        */
        

        //show/hide elements on toolbar
        if(this.div_actions){
            if(this.options.show_menu){        
                this.div_actions.show();
            }else{
                this.div_actions.hide();
            }
        }
        
        //counter and pagination        
        this._showHideOnWidth();
    },

    //adjust top,height according to visibility settings -----------
    _adjustHeadersPos: function(){

        var top = 0;    
        if(this.options.show_inner_header){
            this.div_header.show();
            top = this.div_header.height();
        }else{
            this.div_header.hide();
        }
//console.log('1. '+top)
        if(this.options.show_toolbar){
            this.div_toolbar.css({'top':(top-1)+'px', height:'auto'});
            this.div_toolbar.show();
            top = top + this.div_toolbar.height();
        }else{
            this.div_toolbar.hide();
        }
//console.log('2. '+top)

/*        
        var top = 4;
        if(this.options.show_inner_header){
            this.div_header.show();
            top = top + this.div_header.height()-5;
        }else{
            this.div_header.hide();
        }
        this.div_toolbar.css({'top':top+'px', height:'auto'});//this.options.show_savefilter?'4.9em':'2.5em'});
        if(this.options.show_toolbar){
            this.div_toolbar.show();
            top = top + this.div_toolbar.height();
        }else{
            this.div_toolbar.hide();
        }
*/
        var has_content_header = this.div_content_header && this.div_content_header.is(':visible');
        //!window.hWin.HEURIST4.util.isempty(this.div_content_header.html());

        if(has_content_header){ //table_header
        
            this.div_content_header
                    .position({my:'left bottom', at:'left top', of:this.div_content});
                    
            //adjust columns width in header with columns width in div_content
            if(!this.options.list_mode_is_table){
                var header_columns = this.div_content_header.find('.item');
                var cols = this.div_content.find('.recordDiv:first').find('.item');
                var tot = 0;
                cols.each(function(i, col_item){
                    if(i>0 & i<header_columns.length){ //skip recordSelector
                        $(header_columns[i-1]).width( $(col_item).width() );    
                        tot = tot + $(col_item).width() + 4;
                    }
                });
                //adjust last column
                if(header_columns.length>0){
                    $(header_columns[header_columns.length-1]).width( this.div_content_header.width()-tot-20 );    
                }
            }
            
            top = top + this.div_content_header.height();
        }
        
        //move content down to leave space for header
        this.div_content.css({'top': top+'px'}); //'110px'});

        
        
    },
    //
    // show hide pagination and info panel depend on width
    //
    _showHideOnWidth: function(){      
        
        var total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;
        
        if(this.options.show_viewmode==true && total_inquery>0){
            this.view_mode_selector.show();
        }else{
            this.view_mode_selector.hide();
        }
        if(this.options.support_reorder==true && window.hWin.HAPI4.has_access()){
            this.reorder_button.show();
        }else{
            this.reorder_button.hide();
        }
        

        if(this.options.show_counter){

            if(this.max_page>1) this.span_pagination.css({'display':'inline-block'});
            this.span_info.css({'display':'inline-block'});
            

            this._updateInfo();

        }else{
            this.span_pagination.hide();
            this.span_info.hide();
        }
        
        this._adjustHeadersPos();

    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        if(this._events){
            $(this.document).off(this._events);
        }

        var that = this;
        /*$.each(this._allbuttons, function(index, value){
        var btn = that['btn_'+value];
        if(btn) btn.remove();
        });*/

        if(this.div_header) this.div_header.remove();
        if(this.div_content_header) this.div_content_header.remove();

        // remove generated elements
        this.action_buttons_div.remove();
        if(this.btn_search_save){
            this.btn_search_save.remove(); 
            //this.btn_search_save_withorder.remove(); 
            if(this.sortResultListDlg){
                    this.sortResultList.remove();
                    this.sortResultListDlg.remove();
            }
        } 
        if(this.div_actions) this.div_actions.remove();
        if(this.div_search_form) this.div_search_form.remove();
        this.reorder_button.remove();
        this.div_toolbar.remove();
        this.div_content.remove();
        this.div_coverall.remove();

        this.menu_tags.remove();
        this.menu_share.remove();
        this.menu_more.remove();
        this.menu_view.remove();

        this._removeNavButtons();


        this._currentMultiSelection = null;

    },

    _removeNavButtons: function(){
        if(this.btn_page_menu){
            this._off( this.btn_page_menu, 'click');
            this._off( this.btn_page_prev, 'click');
            this._off( this.btn_page_next, 'click');
            this.btn_page_menu.remove();
            this.btn_page_prev.remove();
            this.btn_page_next.remove();
            this.menu_pages.remove();
        }
    },

    //
    // switcher listener - list;icons;thumbs
    //
    applyViewMode: function(newmode, forceapply){

        
        var allowed = ['list','icons','thumbs','thumbs3','horizontal','icons_list','record_content'];
        
        if(newmode=='icons_expanded') newmode=='record_content'; //backward capability 

        if(window.hWin.HEURIST4.util.isempty(newmode) || allowed.indexOf(newmode)<0) {
            newmode = window.hWin.HAPI4.get_prefs('rec_list_viewmode_'+this.options.entityName);
        }

        if(window.hWin.HEURIST4.util.isempty(newmode) 
            || (newmode=='record_content' && this.options.entityName!='records')){
            newmode = 'list'; //default
        }
        if(newmode=='record_content') {
            newmode = 'icons';    
            this._expandAllDivs = true;
            forceapply = true;
        }else{
            forceapply = this._expandAllDivs;
            this._expandAllDivs = false;
        }


        if(!this.div_content.hasClass(newmode) || forceapply===true){
            
            this.closeExpandedDivs();
            this.options.view_mode = newmode;
            
            /*
            if(newmode){
                this.options.view_mode = newmode;
            }else{
                //load saved value
                if(!this.options.view_mode){
                    this.options.view_mode = window.hWin.HAPI4.get_prefs('rec_list_viewmode_'+this.options.entityName);
                }
                if(!this.options.view_mode){
                    this.options.view_mode = 'list'; //default value
                }
                newmode = this.options.view_mode;
            }
            */
            
            this.div_content.removeClass('list icons thumbs thumbs3 horizontal icons_list');
            this.div_content.addClass(newmode);
            
            this._current_view_mode = newmode;
            
            if(newmode=='horizontal'){ // || newmode=='icons_list'){
                this._on(this.div_content,
                        {'mousewheel':this._recordDivNavigateUpDown
                /*
                function(event) {
                    if(event.originalEvent){
                        this.scrollLeft += (event.originalEvent.deltaY);
                        event.preventDefault();
                    }
                }*/
                });
                
                if(newmode=='horizontal'){
                
                    var h = this.div_content.height();
                        h = (((h<60) ?60 :((h>200)?230:h))-30) + 'px';
                    this.div_content.find('.recordDiv').css({
                        height: h,
                        width: h,
                        'min-width': h
                    });
                    
                }
                
            }else{
                if(newmode=='list' && this.options.list_mode_is_table){
                    this.div_content.css({'display':'table','width':'100%'});
                }else{
                    this.div_content.css({'display':'block'});
                    
                    if(this._expandAllDivs){
                        this.expandDetailsInlineALL();   
                    }
                }
                    
            
                this.div_content.find('.recordDiv').attr('style',null);
                this._off(this.div_content, 'mousewheel');
            }
        }
        
        //show hide table header
        if($.isFunction(this.options.rendererHeader)){
            
            var header_html = (this.options.view_mode=='list')?this.options.rendererHeader():'';
            
            //create div for table header
            if( window.hWin.HEURIST4.util.isnull(this.div_content_header )){
                    this.div_content_header = $('<div>').addClass('table_header')
                        .insertBefore(this.div_content);
                    if(this.options.list_mode_is_table){
                        this.div_content_header.css('font-size', '10px');
                    }
                        
            }
            if(window.hWin.HEURIST4.util.isempty(header_html)){
                this.div_content_header.hide();
            }else{
                this.div_content_header.html( header_html ).show(); //css('display','table');
            }
        } 
    
        this._adjustHeadersPos();
        //this.element.find('input[type=radio][value="'+newmode+'"]').prop('checked', true);

        if(this.view_mode_selector){
            
            this.view_mode_selector.find('button')
                //.removeClass(this.options.is_h6style?'':'ui-heurist-btn-header1')
                .removeClass('ui-heurist-btn-header1')
                .css({'border':'none'});
            if(this._expandAllDivs){
                newmode='record_content';
                this._current_view_mode = newmode;
            } 
          
               
            var btn =   this.view_mode_selector.find('button[value="'+newmode+'"]');
            
            if(this.options.header_class==null) btn.addClass('ui-heurist-btn-header1')                
            btn.css({'border':'1px solid'});
        }
        
        $('.password-reminder-popup').dialog('close');
    },

    //
    //
    //
    clearAllRecordDivs: function(new_title, message){

        $('.password-reminder-popup').dialog('close');
        //this._currentRecordset = null;
        this._lastSelectedIndex = null;

        if(this.div_coverall){
            this.div_coverall.hide();
        }
        

        if(this.div_content){
            var eles = this.div_content.find('div.recordTitle');
            $.each(eles,function(i,e){if($(e).tooltip('instance')) $(e).tooltip('destroy');});
            eles = this.div_content.find('div.rolloverTooltip');
            $.each(eles,function(i,e){if($(e).tooltip('instance')) $(e).tooltip('destroy');});
            
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content[0].innerHTML = message?message:'';//.empty();  //clear
        }

        if(new_title!=null){

            /* tab control header
            var $header = $(".header"+this.element.attr('id'));
            if($header.length>0){
                $header.html('<h3>'+new_title+'</h3>');
                $('a[href="#'+this.element.attr('id')+'"]').html(new_title);
            }
            */

            if(this.div_header!=null) {
                this.setHeaderText(new_title);
            }
            if(new_title==''){
                this.triggerSelection();
            }
        }


        this._count_of_divs = 0;
    },
    
    //
    // this is public method, it is called on search complete (if events are not used)
    //
    updateResultSet: function( recordset, request ){

        this.loadanimation(false);
        
        if(this.options.list_mode_is_table){
            this.applyViewMode('list',true); //redraw header
        }
        
        this.clearAllRecordDivs(null);
        this._renderPagesNavigator();
        this._renderRecordsIncrementally(recordset);
        this._showHideOnWidth()
    },

    //
    // Add new divs for current page
    //
    // @param recordset
    //
    _renderRecordsIncrementally: function( recordset ){

        this._currentRecordset = recordset;

        var total_count_of_curr_request = 0;

        if(this._currentRecordset){
            total_count_of_curr_request = (recordset!=null)?recordset.count_total():0;
            this._renderProgress();
        }

        if( total_count_of_curr_request > 0 )
        {
            if(this._count_of_divs<this.options.pagesize){ // DRAW CURRENT PAGE
                this._renderPage(0, recordset);
            }

        }else if(this._count_of_divs<1) {   // EMPTY RESULT SET

            this._renderPagesNavigator();

            if(this.options.emptyMessageURL){
                this.div_content.load( this.options.emptyMessageURL );
            }else{
                this._renderEmptyMessage( 1 );
            }

        }

    },

    //
    // Add message on div_content 
    // for search start and empty result
    //
    _renderMessage: function(msg){

        var $emptyres = $('<div>')
        .css('padding','1em')
        .html(msg)
        .appendTo(this.div_content);

        return $emptyres;
    },
    
    //
    // mode 
    // 0 - no startup filter
    // 1 - no result
    //
    _renderEmptyMessage: function(mode){

        if( !window.hWin.HEURIST4.util.isempty(this.options['empty_remark']) ){
            
            $('<div>').css('padding','8px').html(this.options['empty_remark']).appendTo(this.div_content);

        }else
        if(this.options.entityName!='records'){

            $('<div>').css('padding','10px')
                .html('<h3 class="not-found" style="color:red;">Filter/s are active (see above)</h3><br />'
                        + '<h3 class="not-found" style="color:teal">No entities match the filter criteria</h3>')
                .appendTo(this.div_content);
            
        }else{

            var that = this;
        
            var $emptyres = $('<div>')
            .css('padding','1em')
            .load(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/resultListEmptyMsg.html',
                function(){
                    $emptyres.find('.acc')
                    .accordion({collapsible:true,heightStyle:'content',
                            activate: function( event, ui ) {
                                //$emptyres.find('.acc > h3').removeClass('ui-state-active');
                            }});
                    $emptyres.find('p').css({'padding-top':'10px'});
                    
                    $emptyres.find('.acc > div').css({
                        background: 'none', border: 'none'});    
                        
                    if(mode==0){   //no filter
                        $emptyres.find('.no-filter').show();
                        $emptyres.find('.not-found').hide();
                        $emptyres.find('.acc1').accordion({active:false});
                    }else{       //no result
                        $emptyres.find('.no-filter').hide();
                        $emptyres.find('.not-found').show();
                        $emptyres.find('.acc2').accordion({active:false});

                         //logged in and current search was by bookmarks
                        if(window.hWin.HAPI4.currentUser.ugr_ID>0 && that._query_request){
                            var domain = that._query_request.w
                            if((domain=='b' || domain=='bookmark')){
                                var $al = $('<a href="#">')
                                .text(window.hWin.HR('Click here to search the whole database'))
                                .appendTo($emptyres);
                                that._on(  $al, {
                                    click: that._doSearch4
                                });
                            }
                            var q = that._query_request.q, rt = 0;
                            if ($.isPlainObject(q) && Object.keys(q).length==1 && !q['t']){
                                rt = q['t'];
                            }else if (q.indexOf('t:')==0){
                                  q = q.split(':');
                                  if(window.hWin.HEURIST4.util.isNumber(q[1])){
                                      rt = q[1];
                                  }
                            }
                            if(rt>0 && $Db.rty(rt,'rty_Name')){
                                $('<span style="padding: 0 10px;font-weight:bold">('
                                        +$Db.rty(rt,'rty_Plural')+')</span>')
                                    .appendTo($emptyres.find('.not-found2'));
                                $('<div>').button({label:'Add '+$Db.rty(rt,'rty_Name'), icon:'ui-icon-plusthick'})
                                    .click(function(){
                                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                            {new_record_params:{RecTypeID:rt}});                                        
                                    })
                                    .appendTo($emptyres.find('.not-found2'));
                            }
                        }
                    
                    }
                    
                    $emptyres.find('.acc > h3')
                        //.removeClass('ui-state-active')
                        //.addClass('ui-widget-no-background')
                        .css({
                                border: 'none',
                                'font-size': 'larger',
                                'font-weight': 'bold'
                            });
                }
            ).appendTo(this.div_content);

        }        
    },
    
    //
    //  
    //
    _renderStartupMessageComposedFromRecord: function(recID){

        var recID = window.hWin.HEURIST4.util.getUrlParameter('Startinfo');
        if(recID>0){

            if(this._startupInfo){
                this.div_coverall.show();
            }else if(recID>0){

                var details = [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']];

                var request = request = {q: 'ids:'+recID, w: 'all', detail:details };

                var that = this;

                window.hWin.HAPI4.SearchMgr.doSearchWithCallback( request, function( new_recordset )
                    {
                        if(new_recordset!=null){
                            var record = new_recordset.getFirstRecord();

                            var title = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
                            var summary = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY']);
                            var extended = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']);

                            //compose
                            that._startupInfo = '<div style="padding:20px 2em"><h2>'+title+'</h2><div style="padding-top:10px">'
                            +(summary?summary:'')+'</div><div>'
                            +(extended?extended:'')+'</div></div>';

                            that.div_coverall.empty();
                            $(that._startupInfo).appendTo(that.div_coverall);
                            that.div_coverall.show();
                        }
                });

            }


        }        
    },

    //
    //  div for not loaded record
    //
    _renderRecord_html_stub: function(recID){

        var html = '<div class="recordDiv" recid="'+recID+'" >' //id="rd'+recID+'" 
        + '<div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif">'
        + '</div>'
        + '<div class="recordTitle">' + recID
        + '...</div>'
/*        
        + '<div title="Click to edit record (opens new tab)" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '<div title="Click to view record (opens as popup)" class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'
*/        
        + '</div>';

        return html;

    },

    //
    // Render div for particular record
    // it can call external renderer if it is defined in options
    //
    _renderRecord_html: function(recordset, record){

        //call external/custom function to render
        if($.isFunction(this.options.renderer)){
            return this.options.renderer.call(this, recordset, record);
        }

        //@todo - move render for Records into separate method of manageRecords

        function fld(fldname){
            return recordset.fld(record, fldname);
        }

        /*
        0 .'bkm_ID,'
        1 .'bkm_UGrpID,'
        2 .'rec_ID,'
        3 .'rec_URL,'
        4 .'rec_RecTypeID,'
        5 .'rec_Title,'
        6 .'rec_OwnerUGrpID,'
        7 .'rec_NonOwnerVisibility,'
        8. rec_ThumbnailURL

        9 .'rec_URLLastVerified,'
        10 .'rec_URLErrorMessage,'
        11 .'bkm_PwdReminder ';
        11  thumbnailURL - may not exist
        */
        
        var is_logged = window.hWin.HAPI4.has_access();

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var bkm_ID = fld('bkm_ID');
        var recTitle = fld('rec_Title'); 
        var recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HEURIST4.util.stripTags(recTitle));
        var recTitle_strip1 = window.hWin.HEURIST4.util.stripTags(recTitle,'u, i, b, strong');
        var recTitle_strip2 = window.hWin.HEURIST4.util.stripTags(recTitle,'a, u, i, b, strong');
        var recIcon = fld('rec_Icon');
        if(!recIcon) recIcon = rectypeID;
        recIcon = window.hWin.HAPI4.iconBaseURL + recIcon + '.png';


        //get thumbnail if available for this record, or generic thumbnail for record type
        var html_thumb = '', rectypeTitleClass = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" title="'+
                recTitle_strip_all+'" style="background-image: url(&quot;'
                + fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            rectypeTitleClass = 'recordTitleInPlaceOfThumb';
            html_thumb = '<div class="recTypeThumb rectypeThumb" title="'
                +recTitle_strip_all+'" style="background-image: url(&quot;'
                + window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&quot;);"></div>';
        }

        // Show a key icon and popup if there is a password reminder string
        var html_pwdrem = '';
        var pwd = window.hWin.HEURIST4.util.htmlEscape(fld('bkm_PwdReminder'));
        if(pwd){
            html_pwdrem =  '<span class="logged-in-only ui-icon ui-icon-key rec_pwdrem" style="display:inline;left:14px;font-size:0.99em"></span>';
            pwd = ' pwd="'+pwd+'" ';
        }else{
            pwd = '';
        }

        function __getOwnerName(ugr_id){ //we may use SystemMgr.usr_names however it calls server
            if(ugr_id==0){
                return 'Everyone';
            }else if(ugr_id== window.hWin.HAPI4.currentUser.ugr_ID){
                return window.hWin.HAPI4.currentUser.ugr_FullName;
            }else if(window.hWin.HAPI4.sysinfo.db_usergroups[ugr_id]){
                return window.hWin.HAPI4.sysinfo.db_usergroups[ugr_id];    
            }else{
                return 'user# '+ugr_id;
            }
        }

        // Show owner group and accessibility to others as colour code
        var html_owner = '';
        var owner_id = fld('rec_OwnerUGrpID');
        if(owner_id){  // && owner_id!='0'
            // 0 owner group is 'everyone' which is treated as automatically making it public (although this is not logical)
            // TODO: I think 0 should be treated like any other owner group in terms of public visibility
            var visibility = fld('rec_NonOwnerVisibility');
            // gray - hidden, green = viewable (logged in user) = default, orange = pending, red = public = most 'dangerous'
            var clr  = 'blue';
            
            if(visibility=='hidden'){
                clr = 'red';
                visibility = 'private - hidden from non-owners';
            }else if(visibility=='viewable'){
                clr = 'orange';
                visibility = 'visible to any logged-in user';
            }else if(visibility=='pending'){
                clr = 'green';
                visibility = 'pending (viewable by anyone, changes pending)';
            }else { //(visibility=='public')
                clr = 'blue';
                visibility = 'public (viewable by anyone)';
            }

            var hint = __getOwnerName(owner_id)+', '+visibility;

            // Displays oner group ID, green if hidden, gray if visible to others, red if public visibility
            html_owner =  '<span class="rec_owner logged-in-only" style="width:20px;padding-top:2px;display:inline-block;color:'
                     + clr + '" title="' + hint + '"><b>' + (owner_id==0?'':owner_id) + '</b></span>';
            
            if(clr != 'blue')         
            html_owner =  html_owner + '<span class="ui-icon ui-icon-cancel" '
            +'style="color:darkgray;display:inline;font-size:1.3em;vertical-align: -1px"></span>'
                +'<span class="ui-icon ui-icon-eye" style="font-size:0.8em;color:darkgray;display:inline;vertical-align:0px" title="'
                + 'This record is not publicly visible - user must be logged in to see it'
                + '" ></span>';
                     

        }
        
        var btn_icon_only = window.hWin.HEURIST4.util.isempty(this.options.recordDivClass)
                                ?' ui-button-icon-only':'';

        var sCount = '';
        if(this.options.aggregate_values){
            sCount = this.options.aggregate_values[recID];
            if(!(sCount>0)) {
                sCount = ''
            }else {
                
                if(this.options.aggregate_link){    
                    sCount = '<a href="'
                    + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                    + '&q=' + encodeURIComponent(this.options.aggregate_link.replace('[ID]',recID))
                    + '" target="_blank" title="Count of records which reference this record. Opens list in new tab">'+sCount+'</a>';
                }
                sCount = '<span style="margin-right:10px">'+sCount+'</span>';
            }
            
        }                        
                                
        // construct the line or block
        var html = '<div class="recordDiv '+this.options.recordDivClass
        +'" recid="'+recID+'" '+pwd+' rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">' //id="rd'+recID+'" 
        + html_thumb
        
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/> '
        +     '<span class="logged-in-only ui-icon ui-icon-bookmark" style="color:'+(bkm_ID?'#ff8844':'#dddddd')+';display:inline-block;"></span>'
        +     html_owner
        +     html_pwdrem
        +     '<span class="recid-in-list">id: '+recID+'</span>'
        + '</div>'


        // it is useful to display the record title as a rollover in case the title is too long for the current display area
        + '<div title="'+(is_logged?'dbl-click to edit: ':'')+recTitle_strip_all+'" class="recordTitle '+rectypeTitleClass+'">'
        +   sCount  
        +     (this.options.show_url_as_link && fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"
            + recTitle_strip1 + "</a>") :recTitle_strip2)  
        + '</div>'

        
        //action button container
        + '<div class="action-button-container">' 

        + '<div title="Click to edit record" '
        + 'class="rec_edit_link action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false" data-key="edit">'
        + '<span class="ui-button-text">Edit</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span>'
        + '</div>'

        + '<div title="Click to edit record (opens in new tab)" '
        + ' class="rec_edit_link_ext action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only"'
        + ' role="button" aria-disabled="false" data-key="edit_ext">'
        + '<span class="ui-button-text">New tab</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-newwin"/>'
        + '</div>'  // Replace ui-icon-pencil with ui-icon-extlink and swap position when this is finished 
        
        /* Ian removed 5/2/2020. TODO: Need to replace with Select, Preview and Download buttons */
        + ((this.options.recordview_onselect===false || this.options.recordview_onselect==='none')
          ?('<div title="Click to view record (opens in popup)" '
        + 'class="rec_view_link action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Preview</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-comment"/>'
        + '</div>'):'')
        
        //toadd and toremove classes works with div.collected see h4styles
        + ((this.options.support_collection)
          ?('<div title="Click to collect/remove from collection" '
        + 'class="rec_collect action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text toadd" style="min-width: 28px;">Collect</span>'
        + '<span class="ui-button-text toremove" style="display:none;min-width: 28px;">Remove</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-circle-plus toadd" style="font-size:11px"></span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-circle-minus toremove" style="display:none;font-size:11px"></span>'
        + '</div>'):'')

        // Icons at end allow editing and viewing data for the record when the Record viewing tab is not visible
        // TODO: add an open-in-new-search icon to the display of a record in the results list
        + ((!this.options.show_url_as_link && fld('rec_URL'))
            ?
        '<div title="Click to view external link (opens in new window)" '
        + 'class="rec_view_link_ext action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Link</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-extlink"/>'
        + '</div>'
            :'')

        + ((rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] ||
            rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'])
            ?
        '<div title="Click to show/hide on map" '
        + 'class="rec_expand_on_map action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Show data</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-globe"/>'
        + '</div>'
        +'<div title="Download dataset" '
        + 'class="rec_download action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Download</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-arrowstop-1-s"/>'
        + '</div>'
            :'')
        
        + ((rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'])
            ?
        '<div title="Click to embed" '
        + 'class="rec_view_link_ext action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Embed</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-globe"/>'
        + '</div>'
        + '<div title="Click to delete" '
        + 'class="rec_delete action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Delete</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-trash"/>'
        + '</div>'
            :'')
        
        + '</div>' //END action button container
        
        + '</div>';


        return html;
    },

    //
    //
    //
    _recordDivOnHover: function(event){
        
        if($.isFunction(this.options.renderer)) return;
    
        var $rdiv = $(event.target);
        if($rdiv.hasClass('rt-icon') && !$rdiv.attr('title')){

            $rdiv = $rdiv.parents('.recordDiv')
            var rectypeID = $rdiv.attr('rectype');
            var title = $Db.rty(rectypeID,'rty_Name') + ' [' + rectypeID + ']';
            $rdiv.attr('title', title);
        }
    },
    
    
    //
    //
    //
    setMultiSelection: function(ids){
         this._currentMultiSelection = ids;
    },
    //
    //
    //
    _manageMultiSelection: function(recID, is_add){
        var idx = this._currentMultiSelection==null 
                    ? -1
                    :window.hWin.HEURIST4.util.findArrayIndex(recID, this._currentMultiSelection);
        if(is_add){
              if(idx<0){
                  if(this._currentMultiSelection==null){
                      this._currentMultiSelection = [];
                  }
                  this._currentMultiSelection.push( recID );
              }
        }else if(idx>=0){
            this._currentMultiSelection.splice(idx,1);
        } 
    },
    

    //
    //
    //
    _recordDivNavigateUpDown:function(event){
        
          //this is scroll event  
          var key;
          if(event.originalEvent && event.originalEvent.deltaY){
              key = (event.originalEvent.deltaY>0)?40:38;
          }else{
              key = event.which || event.keyCode;
              if(this.options.view_mode=='horizontal'){  //|| this.options.view_mode=='icons_list'){
                        if (key == 37) { 
                            key = 38;
                        }else if (key == 39) { //right
                            key=40;
                        }
              }
          }
          
          if(key==38 || key==40){ //up and down
              
              var curr_sel = null;
              
              if(this.options.select_mode=='select_multi'){
                   curr_sel = this.div_content.find('.selected');
              }else{
                   curr_sel = this.div_content.find('.selected_last');
              }
              
              if(curr_sel.length > 0)
              { 
                  //find next of previous div
                  if(key==38){
                     curr_sel = $( curr_sel ).prev( '.recordDiv' );
                  }else{
                     curr_sel = $( curr_sel ).next( '.recordDiv' );    
                  }
              }
              
              if(curr_sel.length==0)  //not found - take first
              {
                  curr_sel = this.div_content.find('.recordDiv');
              }
              
              if(curr_sel.length > 0)
              { 
                event.target = curr_sel[0];
                
                this._recordDivOnClick(event);    
                
                if(this.options.view_mode=='horizontal'){ //|| this.options.view_mode=='icons_list'){
                    var spos = this.div_content.scrollLeft();
                    var spos2 = curr_sel.position().left;
                    var offh = spos2 + curr_sel.width() - this.div_content.width() + 10;
                   
                    if(spos2 < 0){
                        this.div_content.scrollLeft(spos+spos2);
                    }else if ( offh > 0 ) {
                        this.div_content.scrollLeft( spos + offh );
                    }
                }else{
                    var spos = this.div_content.scrollTop();
                    var spos2 = curr_sel.position().top;
                    var offh = spos2 + curr_sel.height() - this.div_content.height() + 10;
                   
                    if(spos2 < 0){
                        this.div_content.scrollTop(spos+spos2);
                    }else if ( offh > 0 ) {
                        this.div_content.scrollTop( spos + offh );
                    }
                }
                
                window.hWin.HEURIST4.util.stopEvent(event);
                
                return false;
              }
          }
    },
    
    
    //
    // close expanded recordDivs
    //
    closeExpandedDivs: function(){
        var exp_div = this.div_content.find('.record-expand-info');
        
        var spos = this.div_content.scrollTop();
        if(exp_div.length>0){
            exp_div.remove();
            /*
            var rdiv2 = exp_div.parent();
            exp_div.remove();
            var tmp_parent = rdiv2.parent('.tmp_parent');
            rdiv2.css({'height':'','width':''}).insertBefore(tmp_parent);
            tmp_parent.remove();
            */
            var rdivs = this.div_content.find('.recordDiv');
            if(this.options.view_mode=='thumbs'){
                rdivs.css({height:'154px', width:'128px'});
            }
            rdivs.removeClass('expanded').show();
                                        
            $.each(rdivs, function(i,rdiv){ 
                $(rdiv).children().not('.recTypeThumb').show();
                $(rdiv).find('.action-button').addClass('ui-button-icon-only');
            });
            
            this.div_content.scrollTop(spos);
        }
                            
        this.div_content.find('.recordDiv .action-button-container').css('display','');
    },
    
    //
    //
    //
    _recordDivOnClick: function(event){

        //var $allrecs = this.div_content.find('.recordDiv');

        var $target = $(event.target),
        that = this,
        $rdiv;

        if($target.is('a')) return;

        if(!$target.hasClass('recordDiv')){
            $rdiv = $target.parents('.recordDiv');
        }else{
            $rdiv = $target;
        }

        var selected_rec_ID = $rdiv.attr('recid');

        var action =  $target.attr('data-key') || $target.parents().attr('data-key');//parents('div[data-key!=""]');
        if(!window.hWin.HEURIST4.util.isempty(action)){ //action_btn && action_btn.length()>0){
            //var action = action_btn.attr('data-key');
            if(this.options.renderer){
                //custom handler
                this._trigger( "onaction", null, {action:action, recID:selected_rec_ID, target:$target});

            }else if (action=='edit'){

                /* OUTDATED
                var query = null;
                if(this._currentRecordset && this._currentRecordset.length()<1000){
                    //it breaks order in edit form - previos/next becomes wrong
                    query = 'ids:'+this._currentRecordset.getIds().join(',');
                }else{
                    query = this._query_request;
                }*/
                
                var ordered_recordset = null;
                if(this._currentRecordset){
                    ordered_recordset = this._currentRecordset;
                }else{
                    ordered_recordset = this._query_request;
                }

                window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, ordered_recordset, true, null);
                //@todo callback to change rectitle

            }else if (action=='edit_ext'){

                var url = window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                window.open(url, "_new");
            }
            
            // remove this remark to prevent selection on action button click
            //return;
        }

        var ispwdreminder = $target.hasClass('rec_pwdrem'); //this is password reminder click
        if (ispwdreminder){
            var pwd = $rdiv.attr('pwd');
            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HEURIST4.util.htmlEscape(pwd),
                    null, "Password reminder", 
                {my: "left top", at: "left bottom", of: $target, modal:false}
            );
            $dlg.addClass('password-reminder-popup'); //class all these popups on refresh
            return;
        }else{
            
            //this.options.recordview_onselect=='popup'
             //(this.options.recordview_onselect!==false && this.options.view_mode!='list')
            var isview = (this.options.recordview_onselect=='popup' ||
                            $target.parents('.rec_view_link').length>0); //this is VIEWER click
                
            if(isview){ //popup record view
            
                this._clearTimeouts();
                this._showRecordViewPopup( selected_rec_ID );

                return;
            }else
            if($target.hasClass('rec_expand_on_map') || $target.parents('.rec_expand_on_map').length>0){
                if(this._currentRecordset){
                    
                    var btn = $target.hasClass('rec_expand_on_map')?$target:$target.parents('.rec_expand_on_map');
                    if(btn.attr('data-loaded')=='loading') return; 
                    
                    /*
                    if(btn.attr('data-loaded')!='visible'){
                         btn.attr('data-loaded','visible');
                         btn.find('.ui-button-text').text('Hide data');
                    }else{
                         btn.attr('data-loaded','hidden');
                         btn.find('.ui-button-text').text('Show data');
                    }
                    //+ '<span class="ui-button-icon-primary ui-icon ui-icon-globe"/>'
                    */     
                        //var record = this._currentRecordset.getById(selected_rec_ID);
                        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:[selected_rec_ID], 
                            map_layer_action: 'trigger_visibility',  //dataset_visibility: true, 
                            source:this.element.attr('id'), search_realm:this.options.search_realm} );
                    
                    
                }            
                return;            
            }else
            if($target.hasClass('rec_download') || $target.parents('.rec_download').length>0){
                
                if(this._currentRecordset){
                    
                    $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                    {selection:[selected_rec_ID], 
                        map_layer_action: 'download', //dataset_download: true, 
                        source:this.element.attr('id'), search_realm:this.options.search_realm} );
                    
                }
                return;            
                
            }else
            if($target.hasClass('rec_view_link_ext') || $target.parents('.rec_view_link_ext').length>0){
                //View external link (opens in new window)
                //OR Embed map document
                if(this._currentRecordset){
                    var record = this._currentRecordset.getById(selected_rec_ID);
                    var rectypeID = this._currentRecordset.fld(record, 'rec_RecTypeID' );
                    //show embed dialog
                    if(rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){
                        window.hWin.HEURIST4.ui.showPublishDialog({mode:'mapspace',mapdocument_id: selected_rec_ID});
                    }else{
                        var url = this._currentRecordset.fld(record, 'rec_URL' );
                        if(url) window.open(url, "_new");
                    }
                }
                return;
            }else 
            if($target.hasClass('rec_delete') || $target.parents('.rec_delete').length>0){
                if(this._currentRecordset){
                    var record = this._currentRecordset.getById(selected_rec_ID)
                    var rectypeID = this._currentRecordset.fld(record, 'rec_RecTypeID' );
                    //show delete dialog
                    if(rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){

                        window.hWin.HAPI4.currentRecordsetSelection = [selected_rec_ID];
                        if(Hul.isempty(window.hWin.HAPI4.currentRecordsetSelection)) return;
                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {
                            hide_scope: true,
                            title: 'Delete map document. Associated map layers and data sources retain',
                            onClose:
                           function( context ){
                               if(context){
                                   // refresh search
                                   window.hWin.HAPI4.SearchMgr.doSearch( that, that._query_request );
                               }
                           }
                        });
                        
                        
/* old version                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {
                            map_document_id: selected_rec_ID,
                            title: 'Delete map document and associated map layers and data sources',
                            onClose:
                           function( context ){
                               if(context){
                                   // refresh search
                                   window.hWin.HAPI4.SearchMgr.doSearch( this, this._query_request );
                               }
                           }
                        });
*/                        
                    }
                }
                return;
                
            }else 
            if($target.hasClass('rec_collect') || $target.parents('.rec_collect').length>0){

                if($rdiv.hasClass('collected')){
                    window.hWin.HEURIST4.collection.collectionDel(selected_rec_ID);        
                }else{
                    window.hWin.HEURIST4.collection.collectionAdd(selected_rec_ID);
                }
                return;
            }
        }

        //select/deselect on click
        if(this.options.select_mode=='none'){
          //do nothing
        }else 
        if(this.options.select_mode=='select_multi'){
            
            if($rdiv.hasClass('selected')){
                $rdiv.removeClass('selected');
                $rdiv.find('.recordSelector>input').prop('checked', '');
                //this._currentSelection.removeRecord(selected_rec_ID);
                this._manageMultiSelection(selected_rec_ID, false);
            }else{
                $rdiv.addClass('selected')
                $rdiv.find('.recordSelector>input').prop('checked', 'checked');
                this._manageMultiSelection(selected_rec_ID, true);
                /*if(this._currentSelection==null){
                    this._currentSelection = [selected_rec_ID]; //this._currentRecordset.getSubSetByIds([selected_rec_ID]);
                }else{
                    this._currentSelection.addRecord(selected_rec_ID, this._currentRecordset.getById(selected_rec_ID));
                }*/
            }
            this._updateInfo();

        }else{

            this.div_content.find('.selected_last').removeClass('selected_last');

            if(this.options.multiselect && event.ctrlKey){

                if($rdiv.hasClass('selected')){
                    $rdiv.removeClass('selected');
                    this._lastSelectedIndex = null;
                    //$rdiv.removeClass('ui-state-highlight');
                }else{
                    $rdiv.addClass('selected');
                    $rdiv.addClass('selected_last');
                    this._lastSelectedIndex = selected_rec_ID;
                    //$rdiv.addClass('ui-state-highlight');
                }
                //this._lastSelectedIndex = selected_rec_ID;

            }else if(this.options.multiselect && event.shiftKey){

                if(this._lastSelectedIndex!=null){
                    var nowSelectedIndex = selected_rec_ID;

                    this.div_content.find('.selected').removeClass('selected');

                    var isstarted = false;

                    this.div_content.find('.recordDiv').each(function(ids, rdiv){
                        var rec_id = $(rdiv).attr('recid');

                        if(rec_id == that._lastSelectedIndex || rec_id==nowSelectedIndex){
                            if(isstarted){ //stop selection and exit
                                $(rdiv).addClass('selected');
                                return false;
                            }
                            isstarted = true;
                        }
                        if(isstarted) {
                            $(rdiv).addClass('selected');
                        }
                    });

                    $rdiv.addClass('selected_last');
                    that._lastSelectedIndex = selected_rec_ID;

                }else{
                    lastSelectedIndex = selected_rec_ID;
                }


            }else{
                //remove selection from all recordDiv
                this.div_content.find('.selected').removeClass('selected');
                $rdiv.addClass('selected');
                $rdiv.addClass('selected_last');
                this._lastSelectedIndex = selected_rec_ID;

            }

        }
        
        //$.isFunction(this.options.renderer) && 
        if((this.options.view_mode!='horizontal')   // && this.options.view_mode!='icons_list'
            && this.options.recordview_onselect=='inline'
            && this.options.expandDetailsOnClick){ // && this.options.view_mode=='list'

            this.expandDetailsInline( selected_rec_ID );
        }
        

        //window.hWin.HEURIST4.util.stopEvent(event);
        
        this.triggerSelection();
    },
    //_recordDivOnClick
    
    //
    // expand ALL recordDivs
    //
    expandDetailsInlineALL: function(can_proceed){
        
        var that = this;
        
        if(can_proceed!==true && this.allowedPageSizeForRecContent(function(){
            that.expandDetailsInlineALL(true);
        })) return;
    
        $allrecs = this.div_content.find('.recordDiv');
        $allrecs.each(function(idx, item){
            that.expandDetailsInline($(item).attr('recid'));
        });
        
    },
    
    //
    //
    //
    expandDetailsInline: function(recID){
        
        var $rdiv = this.div_content.find('div[recid='+recID+']');
        if($rdiv.length==0) return; //no such recod in current page
        
        
        var that = this;
        
            var exp_div = this.div_content.find('.record-expand-info[data-recid='+recID+']');
            var is_already_opened = (exp_div.length>0);
            
            if(is_already_opened){
                if(!this._expandAllDivs) this.closeExpandedDivs();
            }else{
                //close other expanded recordDivs
                if(!this._expandAllDivs) this.closeExpandedDivs();
                
           
                //expand selected recordDiv and draw record details inline
                if($.isFunction(this.options.rendererExpandDetails)){
                    this.options.rendererExpandDetails.call(this, this._currentRecordset, recID);
                    //this.options.rendererExpandDetails(recID, ele, function(){ ele.removeClass('loading'); });
                }else {
                    
                    //add new record-expand-info 
                    var ele = $('<div>')
                        .attr('data-recid', recID)
                        .css({'overflow':'hidden','padding-top':'5px','height':'25px'}) //'max-height':'600px','width':'100%',
                        .addClass('record-expand-info');
                        
                    if(this.options.view_mode=='list'){
                        ele.appendTo($rdiv);
                    }else{
                        ele.css({'box-shadow':'0px 3px 8px #666','margin':'6px',
                                 //'width':'97%',
                                  padding:'5px',
                                 'border-radius': '3px 3px 3px 3px',
                                 'border':'2px solid #62A7F8'});
                                 
                        if(this.options.view_mode=='icons' && this._expandAllDivs){

                            $rdiv.addClass('expanded');
                            $rdiv.children().not('.recTypeThumb').hide();
                            //show on hover as usual 
                            $rdiv.find('.action-button-container').show();
                            $rdiv.find('.action-button').removeClass('ui-button-icon-only');
                            if(window.hWin.HAPI4.has_access()){
                                ele.css({'margin':'0px 0px 0px 80px'});
                            }else{
                                ele.css({'margin':'0px'});
                            }
                            ele.appendTo($rdiv);
                        }else{
                            ele.insertAfter($rdiv);
                        }
                                 
                    }
                        
                    
                    var infoURL;
                    var isSmarty = false;
                    
                    if( typeof this.options.rendererExpandDetails === 'string' 
                            && this.options.rendererExpandDetails.substr(-4)=='.tpl' ){

                        infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?publish=1&debug=0&q=ids:'
                        + recID 
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(this.options.rendererExpandDetails);
                                
                        isSmarty = true;
                    }else{
                        //content is record view 
                        infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='
                        +recID
                        +'&db='+window.hWin.HAPI4.database;
                    }
                    
                    
                    //content is smarty report
                    if( this.options.rendererExpandInFrame ||  !isSmarty)
                    {
                        
                        //var ele2 = that.div_content.find('.record-expand-info[data-recid='+recID+']');
                        ele.addClass('loading');
                        
                        $('<iframe>').attr('data-recid',recID)
                            .appendTo(ele)
                            .css('opacity',0)
                            .attr('src', infoURL).on('load',function()
                            { 
                                var _recID = $(this).attr('data-recid');
                                var ele2 = that.div_content.find('.record-expand-info[data-recid='+_recID+']');
                            //var ele2 = 
                            var h = 300;

                            try{
                                
                                var cw = this.contentWindow.document;
                                
                                var cw2  = this.contentWindow.document.documentElement;//.scrollHeight

                                function __adjustHeight(){
                                    //h = $(cw).height();
                                    if(cw2){
                                        var bh = cw.body?cw.body.scrollHeight:0;
                                        var h = cw2.scrollHeight;                               
        //console.log('scroll='+sh+'  h='+h+'  bh='+bh);
                                        if(bh>0 && h>0){
                                            h = Math.max(bh,h);
                                        }else{
                                            h = 300 //default value
                                        }
                                        ele2.height(h);//+(h*0.05)    
                                        
                                    }
                                }
                                
                               __adjustHeight();

                                setTimeout(__adjustHeight, 2000);
                                setTimeout(__adjustHeight, 4000);
                                setTimeout(function(){
                                    ele2.removeClass('loading');
                                    ele2.find('iframe').css('opacity',1);
                                }, 2100);
                                //setTimeout(__adjustHeight, 10000);
                                
                            }catch(e){
                                ele2.removeClass('loading').height(400);    
                                console.log(e);
                            }
                            /*
                            //ele2.removeClass('loading').height('auto');    
                            if(that._expandAllDivs){
                                ele2.removeClass('loading').height('auto');    
                            }else{
                                ele2.removeClass('loading').animate({height:h},300);    
                            }
                            */
                        });
                        

                    }else{

                        ele.addClass('loading').css({'overflow-y':'auto'}).load(infoURL, function(){ 
//console.log('loaded in div');                            
                            var ele2 = $(this);
                            //var ele2 = that.div_content.find('.record-expand-info[data-recid='+recID+']');
                            var h = ele2[0].scrollHeight+10;
                            //h = Math.min(h+10, 600);
                            ele2.removeClass('loading').height('auto');    
                            
/*                            if(that._expandAllDivs){
console.log('scroll h='+h+'  auto='+ele2.height());                                                            
setTimeout("console.log('2. auto='+ele2.height());",1000);
                            }*/
                            
                            /*
                            if(that._expandAllDivs){
                                ele2.removeClass('loading').height('auto');    
                            }else{
                                ele2.removeClass('loading').animate({height:h},300);
                            }*/
                        });   
                    }  
                    
                }
            }        
        
    },
    
    //
    // keeps full set for multiselection 
    //
    fullResultSet: function(recset){
        this._fullRecordset = recset;
    },
    
    //
    // trigger global event
    //
    triggerSelection: function(){


        if(this.options.eventbased){
            var selected_ids = this.getSelected( true );
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                {selection:selected_ids, source:this.element.attr('id'), search_realm:this.options.search_realm} );
        }else{
            var selected_recs = this.getSelected( false );
            this._trigger( "onselect", null, selected_recs );
        }
    },

    /**
    * return hRecordSet of selected records
    */
    getSelected: function( idsonly ){


        if(this.options.select_mode == 'select_multi'){

            if(this._currentMultiSelection==null){
                return null;
            }else if(idsonly){
                return this._currentMultiSelection; //.getIds();
            }else if(this._fullRecordset){
                return this._fullRecordset.getSubSetByIds(this._currentMultiSelection);
            }else{
                return this._currentRecordset.getSubSetByIds(this._currentMultiSelection);
            }


        }else{

            var selected = []
            if(this._currentRecordset){
                var that = this;
                this.div_content.find('.selected').each(function(ids, rdiv){
                    var rec_ID = $(rdiv).attr('recid');
                    if(that._lastSelectedIndex!=rec_ID){
                        selected.push(rec_ID);
                    }
                });
                if(this._lastSelectedIndex!=null){
                    selected.push(""+this._lastSelectedIndex);
                }
            }

            if(idsonly){
                return selected;
            }else if(this._currentRecordset){
                return this._currentRecordset.getSubSetByIds(selected);
            }else{
                return null;
            }

        }

    },

    /**
    * selection - hRecordSet or array of record Ids
    *
    * @param record_ids
    */
    setSelected: function(selection){

        //clear selection
        this.div_content.find('.selected').removeClass('selected');
        this.div_content.find('.selected_last').removeClass('selected_last');

        if (selection == "all") {
            this.div_content.find('.recordDiv').addClass('selected');
        }else{

            var recIDs_list = window.hWin.HAPI4.getSelection(selection, true); //need to rewrite since it works with global currentRecordset
            if( window.hWin.HEURIST4.util.isArrayNotEmpty(recIDs_list) ){

                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                    var rec_id = $(rdiv).attr('recid');
                    var idx = window.hWin.HEURIST4.util.findArrayIndex(rec_id, recIDs_list);
                    if(idx>=0){ 
                        $(rdiv).addClass('selected');
                        //if(that._lastSelectedIndex==rec_id){
                        //    $(rdiv).addClass('selected_last');
                        //}
                    }
                });
                if(recIDs_list.length==1){
                    var rdiv = this.div_content.find('.recordDiv[recid='+recIDs_list[0]+']');
                    if(rdiv.length>0 && rdiv.position()){
                        this.div_content.scrollTop(rdiv.position().top);
                    }
                }

            }
        }

    },
    
    //
    //
    //
    setCollected: function(collection){
        
        this.div_content.find('.collected').removeClass('collected');
        
        if(this.options.support_collection){

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(collection)){
    
                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                    var rec_id = $(rdiv).attr('recid');
                    var idx = window.hWin.HEURIST4.util.findArrayIndex(rec_id, collection);
                    if(idx>=0){ 
                        $(rdiv).addClass('collected');
                    }
                });
            }else if(collection==null){
                window.hWin.HEURIST4.collection.collectionUpdate();
            }
        }
    },


    loadanimation: function(show){
        if(show){
            this.div_loading.show();
            //this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
            this.element.css('cursor', 'progress');
        }else{
            this.div_loading.hide();
            this.element.css('cursor', 'auto');
            //this.div_content.css('background','none');
        }
    },

    // 
    // in case nothing found for bookmarks, we offer user to search entire db (w=a)
    //
    _doSearch4: function(){

        if ( this._query_request ) {

            this._query_request.w = 'a';
            this._query_request.source = this.element.attr('id'); //orig = 'rec_list';

            window.hWin.HAPI4.SearchMgr.doSearch( this, this._query_request );
        }

        return false;
    }

    //
    //
    //
    , _renderProgress: function(){

    },

    //
    // alternative info function that is invoked ONLY ONCE on search finish
    // see EN
    //
    _renderSearchInfoMsg: function(){

    },

    //
    // number of records in result set (query total count) and number of selected records
    // this function is invoked many times (depends on width, page, selection etc)
    //
    _updateInfo: function(){

        var total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;

        //IJ wants just n=
        var sinfo = 'n = '+total_inquery;
        //var sinfo = 'Records: '+total_inquery;

        this.span_pagination.attr('title', sinfo);

        var w = this.element.width();

        if(this.options.select_mode=='select_multi' && this._currentMultiSelection!=null && this._currentMultiSelection.length>0){
            sinfo = sinfo + " | Selected: "+this._currentMultiSelection.length;
            if(w>600){
                sinfo = sinfo+' <a href="#">clear</a>';
            }
        }
/*
            var pv = false;

            // pagination has LESS priority than reccount
            if ( w > 530 && this.max_page>1) {
                this.span_pagination.show();
                pv = true;
            }else{
                this.span_pagination.hide();
            }
            if ( w > (470 + (pv?60:0)) ) {
                this.span_info.show();
            }else{
                this.span_info.hide();
            }
  */                  
        /*
        if(w<530){
            this.span_info.prop('title',sinfo);
            if(w<530){
                this.span_info.hide();
            }else {
                this.span_info.show();

                //IJ wants just n=
                this.span_info.html(w>340 || total_inquery<1000?('n = '+total_inquery):'i');
            }

        }else{
        */
        this.span_info.prop('title','');
        this.span_info.html(sinfo);
        



        if(this.options.select_mode=='select_multi'){
            var that = this;
            this.span_info.find('a').click(function(){
                that._currentMultiSelection = null;
                that._updateInfo();

                that.div_content.find('.recordDiv').removeClass('selected');
                that.div_content.find('.recordDiv .recordSelector>input').prop('checked', '');

                that.triggerSelection();

                return false; });
        }
    },

    //
    // redraw list of pages
    //
    _renderPagesNavigator: function(){

        this.count_total = (this._currentRecordset!=null)?this._currentRecordset.length():0;
        // length() - downloaded records, count_total() - number of records in query
        var total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;

        this.max_page = 0;
        //this.current_page = 0;

        if(this.count_total>0){

            this.max_page = Math.ceil(this.count_total / this.options.pagesize);
            if(this.current_page>this.max_page-1){
                this.current_page = 0;
            }
        }

        var pageCount = this.max_page;
        var currentPage = this.current_page;
        var start = 0;
        var finish = 0;

        //this._renderRecNumbers();
        this._removeNavButtons();

        var span_pages = $(this.span_pagination); //.children()[0]);//first();
        span_pages.empty();

        this._updateInfo();

        if (pageCount > 1) {
            
            if(this.options.navigator=='none'){
                this._renderPage(0);
            }else{
                
                // KJ's patented heuristics for awesome useful page numbers
                if (pageCount > 9) {
                    if (currentPage < 5) { start = 1; finish = 8; }
                    else if (currentPage < pageCount-4) { start = currentPage - 2; finish = currentPage + 4; }
                        else { start = pageCount - 7; finish = pageCount; }
                } else {
                    start = 1; finish = pageCount;
                }


                /*if (currentPage == 0) {
                this.btn_goto_prev.hide();
                }else{
                this.btn_goto_prev.show();
                }
                if (currentPage == pageCount-1) {
                this.btn_goto_next.hide();
                }else{
                this.btn_goto_next.show();
                }*/

                var that = this;

                var ismenu = that.options.navigator!='buttons' && (that.options.navigator=='menu' || (that.element.width()<450));

                var smenu = '';

                if (start != 1) {    //force first page
                    if(ismenu){
                        smenu = smenu + '<li id="page0"><a href="#">1</a></li>'
                        if(start!=2){                                                                              
                            smenu = smenu + '<li>...</li>';
                        }
                    }else{
                        $( "<button>", { text: "1", id:'page0'}).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages ).on("click", function(){ 
                            that._renderPage(0); 
                        } );
                        if(start!=2){
                            $( "<span>" ).html("..").appendTo( span_pages );
                        }
                    }
                }
                for (i=start; i <= finish; ++i) {
                    if(ismenu){
                        smenu = smenu + '<li id="page'+(i-1)+'"><a href="#">'+i+'</a></li>'
                    }else{

                        var $btn = $( "<button>", { text:''+i, id: 'page'+(i-1) }).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages )
                        .click( function(event){
                            var page = Number(this.id.substring(4));
                            that._renderPage(page);
                        } );
                        if(i-1==currentPage){
                            //$btn.button('disable').addClass('ui-state-active').removeClass('ui-state-disabled');
                        }
                    }
                }
                if (finish != pageCount) { //force last page
                    if(ismenu){
                        if(finish!= pageCount-1){
                            smenu = smenu + '<li>...</li>';
                        }
                        smenu = smenu + '<li id="page'+(pageCount-1)+'"><a href="#">'+pageCount+'</a></li>';
                    }else{
                        if(finish!= pageCount-1){
                            $( "<span>" ).html("..").appendTo( span_pages );
                        }
                        $( "<button>", { text: ''+pageCount, id:'page'+finish }).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages ).on("click", function(){ that._renderPage(pageCount-1); } );
                    }
                }

                if(ismenu){
                    //show as menu
                    this.btn_page_prev = $( "<button>", {text:currentPage} )
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em', 'width':'1.6em'})
                    .button({icons: {
                        primary: "ui-icon-triangle-1-w"
                        }, text:false});

                    this.btn_page_menu = $( "<button>", {
                        text: (currentPage+1)
                    })
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em'})
                    .button({icons: {
                        secondary: "ui-icon-triangle-1-s"
                    }});

                    this.btn_page_menu.find('.ui-icon-triangle-1-s').css({'font-size': '1.3em', right: 0});

                    this.btn_page_next = $( "<button>", {text:currentPage} )
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em', 'width':'1.6em'})
                    .button({icons: {
                        primary: "ui-icon-triangle-1-e"
                        }, text:false});


                    this.menu_pages = $('<ul>'+smenu+'</ul>')   //<a href="#">
                    .css({position:'absolute', zIndex:9999, 'font-size':'0.7em'})
                    .appendTo( this.document.find('body') )
                    .menu({
                        select: function( event, ui ) {
                            var page =  Number(ui.item.attr('recid')); 
                            that._renderPage(page);
                    }})
                    .hide();

                    this._on( this.btn_page_prev, {
                        click: function() {  that._renderPage(that.current_page-1)  }});
                    this._on( this.btn_page_next, {
                        click: function() {  that._renderPage(that.current_page+1)  }});

                    this._on( this.btn_page_menu, {
                        click: function() {
                            $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                            var menu = $( this.menu_pages )
                            //.css('min-width', '80px')
                            .show()
                            .position({my: "right top", at: "right bottom", of: this.btn_page_menu });
                            $( document ).one( "click", function() { menu.hide(); });
                            return false;
                        }
                    });

                }
                
                
                if(this.options.header_class){
                    this.span_pagination.find('button').addClass(this.options.header_class).css({'border':'none'});
                }else if(this.options.is_h6style){
                    this.span_pagination.find('button').removeClass('ui-heurist-btn-header1')
                        .css({'background':'none'});//.css({'border':'none !important'});
                }
                
                if(!ismenu){
                    if(this.options.is_h6style){
                        span_pages.find('#page'+currentPage).addClass('ui-heurist-btn-header1')
                    }else{
                        span_pages.find('#page'+currentPage).css({'border':'1px solid white'});
                    }
                }
                    

            }
        }

        this._showHideOnWidth();
    }

    //
    //
    //    
    , refreshPage: function( callback ){
        
        var keep_selection = this.getSelected(true);
        this._renderPage(this.current_page);
        if(keep_selection && keep_selection.length>0){
            //this.setSelected(keep_selection);
        }
    }
    
    , allowedPageSizeForRecContent: function( callback ){

        if(!this._currentRecordset) return true;
        
        var n = Math.min(this._currentRecordset.length(),this.options.pagesize);
        
        if(n>10){
                var that = this;
                
                var s = '';
                if(window.hWin.HAPI4.has_access()){
                    s = '<p style="color:green">This warning is triggered if there are more than 10 records</p>'; // (edit here to change)
                }
                
                var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>You have selected '+n
                +' records. This display mode loads complete information for each record and will take a long time to load and display all of these data.</p>'
                +s,
                {'Proceed as is' :function(){ 
                    callback.call();
                    $__dlg.dialog( "close" );
                },
                'Single Line Display Mode (this time)':function(){
                    that.applyViewMode('list');
                    $__dlg.dialog( "close" );
                },
                'Switch to Single Display Mode':function(){
                    that.applyViewMode('list');
                    window.hWin.HAPI4.save_pref('rec_list_viewmode_'+that.options.entityName, 'list');
                    $__dlg.dialog( "close" );
                }
                }, {title:'Warning'});
				
            return true;          
        }else{
            return false;
        }
        
    }

    //
    // render the given page (called from navigator and on search finish)
    //
    , _renderPage: function(pageno, recordset, is_retained_selection){

        var idx, len, pagesize;
        var that = this;

        if(is_retained_selection){ //draw retained selection

            recordset = this._currentRecordset.getSubSetByIds(this._currentMultiSelection);
            this._removeNavButtons();
            idx = 0;
            len = recordset.length();
            pagesize = len;

        }else{

            if(this.cb_selected_only) this.cb_selected_only.prop('checked', '');

            if(!recordset){
                recordset = this._currentRecordset;
            }

            if(!recordset) return;

            this._renderPagesNavigator(); //redraw paginator
            if(pageno<0){
                pageno = 0;
            }else if(pageno>=this.max_page){
                pageno= this.max_page - 1;
            }
            this.current_page = pageno<0?0:pageno;

            idx = pageno*this.options.pagesize;
            len = Math.min(recordset.length(), idx+this.options.pagesize)
            pagesize = this.options.pagesize;
            
        }
        
        
        this.clearAllRecordDivs(null);
        
        var recs = recordset.getRecords();
        var rec_order = recordset.getOrder();
        var rec_toload = [];
        var rec_onpage = [];

        var html = '', html_groups = {}, recID;
        
        for(; (idx<len && this._count_of_divs<pagesize); idx++) {
            recID = rec_order[idx];
            if(recID){
                if(recs[recID]){
                    //var recdiv = this._renderRecord(recs[recID]);
                    var rec_div = this._renderRecord_html(recordset, recs[recID]);
                    if(this.options.groupByField){
                        var grp_val = recordset.fld(recs[recID], this.options.groupByField);
                        if(!html_groups[grp_val]) html_groups[grp_val] = '';
                        html_groups[grp_val] += rec_div;
                    }else{
                        html  += rec_div;
                    }
                    rec_onpage.push(recID);
                    
                }else{
                    //record is not loaded yet
                    html  += this._renderRecord_html_stub( recID );    
                    if(this.options.supress_load_fullrecord===false) rec_toload.push(recID);
                }

                this._count_of_divs++;
                /*this._on( recdiv, {
                click: this._recordDivOnClick
                });*/
            }
        }
        if(this.options.groupByField){
            //Object.keys(html_groups);
            var hasRender = $.isFunction(this.options.rendererGroupHeader);

            //
            if(this.options.groupOnlyOneVisible && 
                $.isEmptyObject(this._grp_keep_status))
            { //initially expand first only
                var isfirst = true;
                for (var grp_val in html_groups){
                    if(!window.hWin.HEURIST4.util.isempty(html_groups[grp_val])){
                        this._grp_keep_status[grp_val] = isfirst?1:0;
                        isfirst = false;
                    }
                }   
            }

            function __drawGroupHeader(i, grp_val){
                
                    if(!html_groups[grp_val]){
                        html_groups[grp_val] = 'empty';  
                        that._grp_keep_status[grp_val] = 0;
                    }
                    
                    var is_expanded = ($.isEmptyObject(that._grp_keep_status) || that._grp_keep_status[grp_val]==1);
                    
                    var gheader = (hasRender)
                        ?that.options.rendererGroupHeader.call(that, grp_val, is_expanded)
                        :'<div style="width:100%">'+grp_val+'</div>';  
                        
                    html += (gheader+'<div data-grp-content="'+grp_val
                        +'" style="display:'+(is_expanded?'block':'none')
                        +'">'+html_groups[grp_val]+'</div>');
            }
            
            if(this.options.groupByRecordset){
                $.each(this.options.groupByRecordset.getOrder(),__drawGroupHeader);
            }else{
                //
                for (var grp_val in html_groups){
                    __drawGroupHeader(0, grp_val);
                }
            }
        }
        
        //special div for horizontal
        if(this.options.view_mode == 'horizontal'){  //|| this.options.view_mode == 'icons_list'){
            html = '<div>'+html+'</div>';
        }
        
        this.div_content[0].innerHTML += html;
        
        if(this.options.groupByField){ //init show/hide btn for groups
            if(this.options.groupByCss!=null){
                this.div_content.find('div[data-grp-content]').css( this.options.groupByCss );
            }
        
            this.div_content.find('div[data-grp]')
                    .click(function(event){
                        var btn = $(event.target);
                        var grp_val = btn.attr('data-grp');
                        if(!grp_val){
                            btn = $(event.target).parents('div[data-grp]');
                            grp_val = btn.attr('data-grp');
                        }
                        var ele = that.div_content.find('div[data-grp-content="'+grp_val+'"]');
                        if(ele.is(':visible')){
                            that._grp_keep_status[grp_val] = 0;
                            ele.hide();
                            btn.find('.expand_button').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                        }else{
                            if(that.options.groupOnlyOneVisible){
                                //collapse other groups
                                that.div_content.find('div[data-grp]')
                                        .find('.expand_button')
                                        .removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                                that.div_content.find('div[data-grp-content]').hide();
                                that._grp_keep_status = {};
                                that._grp_keep_status[grp_val] = 1;    
                            }else{
                                that._grp_keep_status[grp_val] = 1;    
                            }
                            
                            
                            ele.show();
                            btn.find('.expand_button').removeClass('ui-icon-triangle-1-e').addClass("ui-icon-triangle-1-s");
                        }
                    });
        }
        
        
        function ___ontooltip(){
                var ele = $( this );
                var s = ele.attr('title');
                return window.hWin.HEURIST4.util.isempty(s)?'':s;
        }
        
        this.div_content.find('div.recordTitle').tooltip({content: ___ontooltip}); //title may have html format - use jquery tooltip
        this.div_content.find('div.rolloverTooltip').tooltip({content: ___ontooltip}); //use jquery tooltip

        if(this.options.select_mode!='select_multi'){
            this.div_content.find('.recordSelector').hide();
            
            if(this.options.view_mode == 'horizontal'){
                //always select first div for horizontal viewmode
                var ele = this.div_content.find('.recordDiv:first');//.addClass('selected');
                if(ele.length>0) this._recordDivOnClick({target:ele[0]});
            }            
            
        }else if(this._currentMultiSelection!=null) { //highlight retained selected records

            for(idx=0; idx<rec_onpage.length; idx++){
                recID = rec_onpage[idx];
                var index = window.hWin.HEURIST4.util.findArrayIndex(recID, this._currentMultiSelection);
                if(index>=0){ //this._currentSelection.getById(recID)!=null
                    var $rdiv = this.div_content.find('.recordDiv[recid="'+recID+'"]');
                    $rdiv.find('.recordSelector>input').prop('checked','checked');
                    $rdiv.addClass('selected');
                }
            }
        }
        

        /*var lastdiv = this.div_content.last( ".recordDiv" ).last();
        this._on( lastdiv.nextAll(), {
        click: this._recordDivOnClick
        });*/

        $allrecs = this.div_content.find('.recordDiv');
        
        if(this.options.view_mode == 'horizontal'){ // || this.options.view_mode == 'icons_list'
            var h = this.div_content.height();
                h = (((h<60) ?60 :((h>200)?230:h))-30) + 'px';
            $allrecs.css({
                        height: h,
                        width: h,
                        'min-width': h
            });
        }
        
        //tabindex is required to keydown be active
        $allrecs.each(function(idx, item){
            $(item).prop('tabindex',idx);
        });


        // show record info on mouse over
        //
        if(this.options.recordview_onselect===false || this.options.recordview_onselect==='none'){
            
            this._on( this.div_content.find('.rec_view_link'), {
                mouseover: function(event){
                    
                    var ele = $(event.target).parents('.recordDiv');
                    var rec_id = ele.attr('recid');
                    
                    this._clearTimeouts();
                    
                    this._myTimeoutOpenRecPopup = setTimeout(function(){
                        that._showRecordViewPopup( rec_id );
                    },1000);

                }
                ,mouseout: function(){
                    this._clearTimeouts();
                    this._closeRecordViewPopup();
                }

            });
        }
        
        //
        //        
        this._on( $allrecs, {
            click: this._recordDivOnClick,
            //keypress: this._recordDivNavigateUpDown,
            keydown: this._recordDivNavigateUpDown,
            //keyup: this._recordDivNavigateUpDown,
            mouseover: this._recordDivOnHover,
            /* enable but specify entityName to edit in options */
            dblclick: function(event){ //start edit on dblclick
                if(!$.isFunction(this.options.renderer)){
                    
                    if(window.hWin.HAPI4.has_access()){
                        
                        var $rdiv = $(event.target);
                        if(!$rdiv.hasClass('recordDiv')){
                            $rdiv = $rdiv.parents('.recordDiv');
                        }
                        var selected_rec_ID = $rdiv.attr('recid');

                        event.preventDefault();
                        
                        /* old way
                        var query = null;
                        if(this._currentRecordset && this._currentRecordset.length()<1000){
                            query = 'ids:'+this._currentRecordset.getIds().join(',');
                        }else{
                            query = this._query_request;
                        }
                        window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, query, true, null);
                        */
                        var ordered_recordset = null;
                        if(this._currentRecordset){
                            ordered_recordset = this._currentRecordset;
                        }else{
                            ordered_recordset = this._query_request;
                        }
                        window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, ordered_recordset, true, null);                        
                        
                        //@todo callback to change rectitle    
                    }else{
                        this._recordDivOnClick(event);
                    }
                }else{
                    var selected_recs = this.getSelected( false );
                    this._trigger( "ondblclick", null, selected_recs );
                }
            }
        });
        var inline_selectors = this.div_content.find('.recordDiv select');
        if(inline_selectors.length>0){

            inline_selectors.find('option[value=""]').remove();
            $.each(inline_selectors, function(idx, ele){
                $(ele).val($(ele).attr('data-grpid')); 
            });

            this._on( inline_selectors, {
                //click: function(){ return false; },
                change: function(event){
                    var $rdiv = $(event.target).parents('.recordDiv');
                    var recID = $rdiv.attr('recid');
                    this._trigger( "onaction", null, {action:'group-change', recID:recID, grpID: $(event.target).val()});
                }
            });
        }
        
        if(this.options.sortable){
            var that = this;
            this.div_content.sortable({stop:function(event, ui){
                
                var rec_order = that._currentRecordset.getOrder();
                var idx = that.current_page*that.options.pagesize;
                //var len = Math.min(that._currentRecordset.length(), idx+that.options.pagesize);
                //var pagesize = this.options.pagesize;
                
                that.div_content.find('.recordDiv').each(function(index, rdiv){
                    var rec_id = $(rdiv).attr('recid');
                    rec_order[idx+index] = rec_id;
                });
                that._currentRecordset.setOrder(rec_order);
                
                if($.isFunction(that.options.onSortStop)){
                    that.options.onSortStop.call(that, this.div_content);    
                }
            }});
            //$allrecs.draggable({containment:this.div_content});    
        }
        if($.isFunction(this.options.draggable)){
            this.options.draggable.call();
        }
        if($.isFunction(this.options.droppable)){
            this.options.droppable.call();
        }

        

        /* show image on hover
        var that = this;
        $(".realThumb").hover( function(event){
        var bg = $(event.target).css('background-image');
        $("#thumbnail_rollover_img").css({'background-image': bg,
        'background-size':'contain', 'background-repeat':'no-repeat', 'background-position': 'center' } );
        that.hintDiv.showAt(event);
        },
        function(){ that.hintDiv.hide(); } );
        */


        //hide edit link
        if(!window.hWin.HAPI4.has_access()){
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
        }

        //rec_toload - list of ids
        //load full record info - record header111
        if(rec_toload.length>0){
            var that = this;

            that.loadanimation(true);

            if($.isFunction(this.options.searchfull)){
                //call custom function 
                this.options.searchfull.call(this, rec_toload, this.current_page, 
                    function(response){ 
                        that._onGetFullRecordData(response, rec_toload); 
                });

            }else{

                var ids = rec_toload.join(',');
                var request = { q: '{"ids":"'+ ids+'"}',
                    w: 'a',
                    detail: 'header',
                    id: window.hWin.HEURIST4.util.random(),
                    pageno: that.current_page,
                    source:this.element.attr('id') };

                window.hWin.HAPI4.RecordMgr.search(request, function(response){
                    that._onGetFullRecordData(response, rec_toload);   
                });
            }


        }
        
        this.setCollected( null );
        
        this._trigger( "onpagerender", null, this );
        
        //@todo replace it to event listener in manageRecUploadedFiles as in manageSysGroups
        if($.isFunction(this.options.onPageRender)){
            this.options.onPageRender.call(this);
        }

        if(this.options.recordDivEvenClass){
            //$allrecs.addClass(this.options.recordDivEvenClass);    
            var that = this;
            $allrecs.each(function(idx, item){
                if(idx % 2 == 0)
                {
                    $(item).addClass(that.options.recordDivEvenClass);    
                }
            });
        }
        
        if(this.div_content_header){
            this._adjustHeadersPos();
        }
        
        
        if(this._expandAllDivs){
            this.expandDetailsInlineALL();   
        }
        
    },
    
    //
    //
    //
    _onGetFullRecordData: function( response, rec_toload ){

        this.loadanimation(false);
        if(response.status == window.hWin.ResponseStatus.OK){

            if(response.data.pageno==this.current_page) { //response.data.queryid==this.current_page || 

                var resp = new hRecordSet( response.data );
                this._currentRecordset.fillHeader( resp );

                //remove records that we can't recieve data
                for(i in rec_toload){
                    var recID = rec_toload[i];
                    if(resp.getById(recID)==null){
                        this._currentRecordset.removeRecord(recID);
                        //this._currentRecordset.setRecord(recID,{rec_ID:recID, rec_Title:'Error! Cannot get data from server'});
                    }        
                }

                this._renderPage( this.current_page );
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }

    },

    showRetainedSelection: function(){

        //if(window.hWin.HEURIST4.util.isnull(need_show)){
        var need_show = this.cb_selected_only.is(':checked');


        if(need_show && this._currentMultiSelection!=null && this._currentMultiSelection.length>0){
            this._renderPage(0, null, true);
        }else{
            this._renderPage(this.current_page);
        }
    },

    getRecordSet: function(){
        return this._currentRecordset;    
    },

    //
    //
    //
    getRecordsById: function (recIDs){
        if(this._currentRecordset){
            return this._currentRecordset.getSubSetByIds(recIDs);
        }else{
            return null;
        }        
    },

    //
    // NOT USED
    //
    applyFilter:function(request){
        /*
        $.each(this._currentRecordset)
        this.recordList.find
        */
    },
    
    resetGroups: function(){
        this._grp_keep_status = {};
    },
    
    setHeaderText: function(newtext, headercss){
        
        var stext
        if(this.options.is_h6style){
            stext = window.hWin.HEURIST4.util.isempty(newtext) ?this.options.title:newtext;
            if(window.hWin.HEURIST4.util.isempty(stext)) stext = window.hWin.HR('Filtered Result');
        }else{
            stext = '<h3 style="margin:0">'+(this.options.title ?this.options.title :newtext)+'</h3>';
            if(this.options.title && newtext) stext = stext +  '<h4 style="margin:0;font-size:0.9em">'+newtext+'</h4>';
        }
    
        this.div_header.find('div.result-list-header').html( stext );
        if(headercss){
            this.div_header.css(headercss);    
        }
        this._adjustHeadersPos();
        
        this.refreshSubsetSign();    
    },
    
    //
    //
    //
    refreshSubsetSign: function(){
        if(this.div_header){
            var container = this.div_header.find('div.result-list-header');
            container.find('span').remove();
            
            var s = '<span class="subset-sign" style="position:absolute;left:10px;top:10px;font-size:0.6em;">';    
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                
                if(this.options.show_menu){ 
                    if(false){ // IJ 2020-11-13 set subset is allowed from main menu only
                  s = s+'<span class="set_subset" '
                      +'title="Make the current filter the active subset to which all subsequent actions are applied">Set</span>'
                      +'&nbsp;&nbsp;';
                      }
                  s = s
                      +'<span class="ui-icon ui-icon-arrowrefresh-1-w clear_subset" style="font-size:1em;" title="Click to revert to whole database"></span>&nbsp;';
                }    
                
                $(s
                +'<span style="padding:.4em 1em 0.3em;background:white;color:red;vertical-align:sub;font-size: 11px;font-weight: bold;"'
                +' title="'+window.hWin.HAPI4.sysinfo.db_workset_count+' records"'
                +'>SUBSET ACTIVE n='+window.hWin.HAPI4.sysinfo.db_workset_count+'</span></span>')
                    .appendTo(container);
                    
                var w = container.find('span.subset-sign').width()+20;

                container.css('padding','10px 10px 10px '+w+'px');            
                    
            }else if(this.options.show_menu) { 
            
                container.css('padding','10px');            
            
                if(false){ // IJ 2020-11-13 set subset is allowed from main menu only
                    $(s+'<span class="set_subset" '
                    +'title="Make the current filter the active subset to which all subsequent actions are applied">Set subset</span></span>')
                    .appendTo(container);
                }
            }
            if(this.options.show_menu){
                this._on(container.find('span.set_subset').button(),
                    {click: function(){this.callResultListMenu('menu-subset-set');}} );
                this._on(container.find('span.clear_subset').css('cursor','pointer'),
                    {click: function(){this.callResultListMenu('menu-subset-clear');}} );
            }
            
            
        }
    },
    
    //
    //
    //
    setOrderAndSaveAsFilter: function(){
                    
        if(!this.sortResultList){
            
            this.sortResultListDlg = $('<div>').appendTo(this.element);
            //init result list
            this.sortResultList = $('<div>').appendTo(this.sortResultListDlg)
                .resultList({
                   recordDivEvenClass: 'recordDiv_blue',
                   eventbased: false, 
                   multiselect: false,
                   view_mode: 'list',
                   sortable: true,
                   show_toolbar: false,
                   select_mode: 'select_single',
                   entityName: this._entityName,
                   pagesize: 9999999999999,
                   renderer: function(recordset, record){ 
                       var recID = recordset.fld(record, 'rec_ID');
                        return '<div class="recordDiv" recid="'+recID+'">'  //id="rd'+recID+'" 
                                //+'<span style="min-width:150px">'
                                //+ recID + '</span>'
                                + window.hWin.HEURIST4.util.htmlEscape( recordset.fld(record, 'rec_Title') ) 
                                + '</div>';
                   }
                   });     
        }
        
        //fill result list with current page ids
        //get all ids on page
        var ids_on_current_page = [];
        this.div_content.find('.recordDiv').each(function(ids, rdiv){
            ids_on_current_page.push($(rdiv).attr('recid'));
        });
        if(ids_on_current_page.length==0) return;
        //get susbet
        var page_recordset = this._currentRecordset.getSubSetByIds(ids_on_current_page);
        page_recordset.setOrder(ids_on_current_page); //preserver order
        this.sortResultList.resultList('updateResultSet', page_recordset);
        
        var that = this;    
            
        var $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({element: $(this.sortResultListDlg)[0],
            title:'Drag records up and down to position, then Save order to save as a fixed list in this order',
            height:500,
            buttons:[
                {text:'Save Order', click: function(){
                    //get new order of records ids
                    var recordset = that.sortResultList.resultList('getRecordSet');
                    var new_rec_order = recordset.getOrder();
                    
                    $dlg.dialog( "close" );
                    
                    if(new_rec_order.length>0){
                    
                        var svsID;
                        if(that._currentSavedFilterID>0 && window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                            window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID]){
                           
                           //if current saved search has sortby:set - just edit with new query
                           var squery = window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][_QUERY];
                           if(squery.indexOf('sortby:set')>=0){
                                svsID = that._currentSavedFilterID;
                           }else{
                                var groupID =  window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][_GRPID];
                                window.hWin.HAPI4.save_pref('last_savedsearch_groupid', groupID);
                           }
                        }
                    
                        //call for saved searches dialog
                        var squery = 'ids:'+new_rec_order.join(',')+' sortby:set';
                        var  widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                        if(widget){
                            widget.svs_list('editSavedSearch', 'saved', null, svsID, squery, null, true); //call public method
                        }
                    }
                }},
                {text:'Cancel', click: function(){$dlg.dialog( "close" );}}
            ]
            });
                        
                
    },    
    
    //
    //
    //
    callResultListMenu: function( action ){
        if(this.div_actions){
            this.div_actions.resultListMenu('menuActionHandler', action);
        }
    },
    
    //
    // 
    //
    _closeRecordViewPopup: function(){
        
        var crs = $('#recordview_popup').css('cursor');
        if(crs && crs.indexOf('resize')>0) return;
        
        this._myTimeoutCloseRecPopup = setTimeout(function(){
            var dlg = $('#recordview_popup');
            var crs = dlg.css('cursor');
            if(crs && crs.indexOf('resize')>0) return;
            
            if(dlg.dialog('instance')) dlg.dialog('close');
        },  2000); //600
                        
                        
        
    },
    
    _clearTimeouts: function(){
            clearTimeout(this._myTimeoutOpenRecPopup);
            this._myTimeoutOpenRecPopup = 0;
            clearTimeout(this._myTimeoutCloseRecPopup);
            this._myTimeoutCloseRecPopup = 0;
    },
    
    //
    //
    //
    _showRecordViewPopup: function( rec_ID ){
                    
                var recInfoUrl = null;
                if(this._currentRecordset && rec_ID>0){
                    recInfoUrl = this._currentRecordset.fld( this._currentRecordset.getById(rec_ID), 'rec_InfoFull' );
                }else{
                    return;
                }
                var lt = 'WebSearch';//window.hWin.HAPI4.sysinfo['layout'];  
                if( !recInfoUrl ){
                    
                    if ( typeof this.options.rendererExpandDetails === 'string' && this.options.rendererExpandDetails.substr(-4)=='.tpl' ){

                        recInfoUrl = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?publish=1&debug=0&q=ids:'
                        + rec_ID
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(this.options.rendererExpandDetails);
                    }else{
                        recInfoUrl = window.hWin.HAPI4.baseURL + "viewers/record/renderRecordData.php?db="
                                +window.hWin.HAPI4.database+"&ll="+lt+"&recID="+rec_ID;  
                    }
                }

                var that = this;
                
                var pos = null;
                var dlg = $('#recordview_popup');               
                
                
                var opts = { 
                        is_h6style: true,
                        modal: false,
                        dialogid: 'recordview_popup',    
                        //width: 700, height: 800, //(lt=='WebSearch'?(window.hWin.innerWidth*0.9):
                        onmouseover: function(){
                            that._clearTimeouts();
                        },
                        title:'Record Info'}                
                    
                if(!(dlg.length>0)){
                    
                    if(this.element.parent().attr('data-heurist-app-id')){ //CMS publication 
                        pos = {my:'center', of: window};
                        opts.width = window.hWin.innerWidth*0.8;
                        opts.height = window.hWin.innerHeight*0.9;
                    }else{
                        //set intial position right to result list - for main interface only!
                        pos = { my: "left top", at: "right top+100", of: $(this.element) };
                    }
                    
                    opts.position = pos;
                }
                
                
                
                window.hWin.HEURIST4.msg.showDialog(recInfoUrl, opts);

                if(pos!=null){
                    dlg = $('#recordview_popup').css('padding',0);
                    this._on(dlg,{
                        mouseout:function(){
                            that._closeRecordViewPopup();
                        }
                    });
                    var dlg_header = dlg.parent().find('.ui-dialog-titlebar');
                    this._on(dlg_header,{mouseout:function(){
                        that._closeRecordViewPopup();
                    }});
                    
                }
                
                /*
                this._on(dlg, ifrm[0].contentWindow,{mouseover:function(){
                    console.log('OVER');
                }});
                */
                
                        
    }                        
    
    
});
