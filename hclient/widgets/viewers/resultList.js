/**
* Query result listing.
*
* Requires hclient/widgets/viewers/resultListMenu.js (must be preloaded)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
        view_mode: null, // list|icons|thumbs   @toimplement detail, condenced

        select_mode:null,//manager, select_single, select_multi
        selectbutton_label:'Select',
        action_select:null,  //array of actions
        action_buttons:null,

        recordview_onselect: false, //false/none inline or popup - show record viewer/info on select
        multiselect: true,    //allows highlight several records
        isapplication: true,  //if false it does not listen global events @todo merge with eventbased
        eventbased:true,

        show_toolbar: true,   //toolbar contains menu,savefilter,counter,viewmode and pagination
        show_menu: false,       //@todo ? - replace to action_select and action_buttons
        show_savefilter: false,
        show_counter: true,
        show_viewmode: true,
        show_inner_header: false,   // show title of current search in header (above toolbar)
        header_class: null,       //class name for menu
        show_url_as_link:false,

        title: null,  //see show_inner_header
        //searchsource: null,

        empty_remark:'No entries match the filter criteria',
        pagesize: -1,
        
        groupByMode: null, //[null|'none','tab','accordion'],
        groupByField:null,
        groupOnlyOneVisible:false,
        groupByCss:null, //css for group content

        renderer: null,    // custom renderer function to draw item
        rendererHeader: null,   // renderer function to draw header for list view-mode (for content)
        rendererGroupHeader: null,   // renderer function for group header (see groupByField)
        // smarty template or url (or todo function) to draw inline record details when recordview_onselect='inline'. (LINE view mode only)
        rendererExpandDetails: null, 

        searchfull: null,  // custom function to search full data
        
        sortable: false, //allows drag and sort entries
        onSortStop: null,

        //event
        onselect: null,  //on select event for non event based

        onPageRender: null, //on complete of page render

        navigator:'auto',  //none, buttons, menu, auto

        entityName:'records',   //records by default
        
        recordDiv_class:null,  //additional class for even entries recordDiv
        
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null  //query string or svs_ID for initial search
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

    _currentRecordset:null,
    _currentMultiSelection:null, //for select_multi - to keep selection across pages and queries

    _startupInfo:null,

    _init_completed:false,
    
    _grp_keep_status:{}, //expanded groups

    // the constructor
    _create: function() {

        var that = this;

        if(this.options.pagesize<50 || this.options.pagesize>5000){
            this.options.pagesize = window.hWin.HAPI4.get_prefs('search_result_pagesize');
        }

        this._is_publication  = this.element.parent().attr('data-heurist-app-id');
        
        if(this._is_publication){
            //this is CMS publication - take css from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else{
            this.element.addClass('ui-heurist-bg-light');
        }
        //this.element.css({'font-size':'0.7em'});

        this._initControls();

        //-----------------------     listener of global events
        if(this.options.eventbased)
        {
            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS + " " + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE;
            if(this.options.isapplication){
                this._events = this._events 
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;
            }

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
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
                {
                    
                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;

                    that.span_pagination.hide();
                    that.span_info.hide();

                    that.setSelected(null);
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:null, source:that.element.attr('id'), search_realm:that.options.search_realm} );

                    if(data.reset){
                        
                        //fake restart
                        that._clearAllRecordDivs('');
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
                                    new_title = window.hWin.HR(that.options.title || 'Filtered Result');            
                                }else{
                                    new_title = window.hWin.HR(data.qname);
                                }
                                
                            }
                            
                            that._clearAllRecordDivs(new_title);
                            
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

                        var recID_withStartupInfo = window.hWin.HEURIST4.util.getUrlParameter('Startinfo');
                        if(recID_withStartupInfo>0){
                            that._renderStartupMessageComposedFromRecord();
                        }else                       
                            if(that.options.emptyMessageURL){
                                that.div_content.load( that.options.emptyMessageURL );
                            }else{
                                that._renderEmptyMessage(0);
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
                    

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //this selection is triggered by some other app - we have to redraw selection
                    if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {
                        
                        if(data.reset){
                            that.setSelected(null);
                        }else{
                            that.setSelected(data.selection);    
                        }
                        
                        
                    }
                }
                //that._refresh();
            });

        }

        this._init_completed = true;

        this._refresh();

        this._renderStartupMessageComposedFromRecord();
        
        // 
        if(false){
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

        //if(this.options.innerHeader || this.options.select_mode=='select_multi'){  .addClass('ui-widget-content')
        this.div_header =  $( "<div>" ).css('height','41px').appendTo( this.element );

        $('<div class="result-list-header">')
        .css({'padding':'0.7em 0 0 20px', 'font-size': '1.17em'})   //padding left to align to search input field
        .appendTo(this.div_header);
        
        this.setHeaderText('<h3 style="margin:0">'+window.hWin.HR('Filtered Result')+'</h3>');
        
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
        .css({'width':'100%','top':'4px'})
        .appendTo( this.element );

        this.div_content = $( "<div>" )
        .addClass('div-result-list-content ent_content_full')
        //.css({'border-top':'1px solid #cccccc'})  //,'padding-top':'1em'
        .css({'overflow-y':'auto'})
        .appendTo( this.element );
        
        if(this._is_publication){ //make transparent
            this.div_toolbar.css({'background':'none'});
            this.div_content.css({'background':'none'});
        }else{
            //set background to none ???? if inner header visible 
            this.element.parent().css({'background':'none','border':'none'});
            this.element.parent().parent().css({'background':'none','border':'none'});

            this.div_toolbar.addClass('ui-heurist-bg-light');
            this.div_content.addClass('ui-heurist-bg-light');
        }


        this.div_loading = $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.element ).hide();

        this.action_buttons_div = $( "<span>" ).hide().appendTo( this.div_toolbar );

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_buttons)){

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

            this.action_buttons_div.css({'display':'inline-block', 'padding':'0 0 4px 1em'});
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

        var rnd = window.hWin.HEURIST4.util.random();

        this.view_mode_selector = $( "<div>" )
        //.css({'position':'absolute','right':right_padding+'px'})
        .css({'float':'right','padding-right':right_padding+'px'})
        .html('<button id="cb1_'+rnd+'" value="list" class="btnset_radio"/>'
            +'<button  id="cb2_'+rnd+'" value="icons" class="btnset_radio"/>'
            +'<button  id="cb3_'+rnd+'" value="thumbs" class="btnset_radio"/>'
            +'<button  id="cb4_'+rnd+'" value="thumbs3" class="btnset_radio"/>'
        )
        .appendTo( this.div_toolbar );
        
        this.view_mode_selector.find('button[value="list"]').button({icon: "ui-icon-list", showLabel:false, label:window.hWin.HR('list')}).css('font-size','1em');
        this.view_mode_selector.find('button[value="icons"]').button({icon: "ui-icon-view-icons-b", showLabel:false, label:window.hWin.HR('icons')}).css('font-size','1em');
        this.view_mode_selector.find('button[value="thumbs"]').button({icon: "ui-icon-view-icons", showLabel:false, label:window.hWin.HR('thumbs')}).css('font-size','1em');
        this.view_mode_selector.find('button[value="thumbs3"]').button({icon: "ui-icon-stop", showLabel:false, label:window.hWin.HR('thumbs3')}).css('font-size','1em');
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

        

        if(this.options.show_menu){
            if($.isFunction($('body').resultListMenu)){
                this.div_actions = $('<div>')
                .css({display:'inline-block','padding-bottom':'4px','padding-left':'6px'})
                //.css({'position':'absolute','top':3,'left':2})
                .resultListMenu({
                        menu_class: this.options.header_class,
                        resultList: this.element})
                .appendTo(this.div_toolbar);
            }
        }else{
            
        }    
        
        //
        //
        if(this.options.show_savefilter){
            //special feature to save current filter
            //.css({position:'absolute',bottom:0,left:0,top:'2.8em'})
            var btndiv = $('<div>').css({display:'block','vertical-align':'top',
                padding: '5px', 'margin-top': '4px', height:'2.4em'})
                .addClass('ui-widget-content')
                .appendTo(this.div_toolbar);
                
            this.btn_search_save = $( "<button>", {
                text: window.hWin.HR('Save Filter'),
                title: window.hWin.HR('Save the current filter and rules as a link in the navigation tree')
            })
            .css({'min-width': '80px','font-size':'0.8em', 'height': '21px', background: 'none', color: 'rgb(142, 169, 185)'})
            .addClass('ui-state-focus')
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
                    }
                });
            } });    
            
            //order manually and save as search filter
            /*
            this.btn_search_save_withorder = $( "<button>", {
                text: window.hWin.HR('Re-order and Save'),
                title: window.hWin.HR('Allows manual reordering of the current results and saving as a fixed list of ordered records')
            })
            .css({'min-width': '80px','font-size':'0.8em', 'height': '21px', background: 'none', color: 'rgb(142, 169, 185)', float:'right'})
            .addClass('ui-state-focus')
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

        var has_content_header = this.div_content_header && this.div_content_header.is(':visible');
        //!window.hWin.HEURIST4.util.isempty(this.div_content_header.html());
        
        if(has_content_header){ //table_header
            top = top + this.div_content_header.height()-2;
        }

        this.div_content.css({'top': top+'px'}); //'110px'});
        
        if(has_content_header){ //table_header
            this.div_content_header
                    .position({my:'left bottom', at:'left top', of:this.div_content});
        }
        
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

        var allowed = ['list','icons','thumbs','thumbs3'];

        if(window.hWin.HEURIST4.util.isempty(newmode) || allowed.indexOf(newmode)<0) {
            newmode = window.hWin.HAPI4.get_prefs('rec_list_viewmode_'+this.options.entityName);
        }

        if(!this.div_content.hasClass(newmode) || forceapply===true){
            //var $allrecs = this.div_content.find('.recordDiv');
            if(newmode){
                if(newmode!='list'){
                    this.div_content.find('.record-expand-info').remove();
                }
                //var oldmode = this.options.view_mode;
                this.options.view_mode = newmode;
                //save viewmode is session
                //moved to change event window.hWin.HAPI4.save_pref('rec_list_viewmode', newmode);

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
            this.div_content.removeClass('list icons thumbs thumbs3');
            this.div_content.addClass(newmode);
        }
        
        //show hide table header
        if($.isFunction(this.options.rendererHeader)){
            
            var header_html = (newmode=='list')?this.options.rendererHeader():'';
            
            //create div for table header
            if( window.hWin.HEURIST4.util.isnull(this.div_content_header )){
                    this.div_content_header = $('<div>').addClass('table_header')
                    .insertBefore(this.div_content);
            }
            if(window.hWin.HEURIST4.util.isempty(header_html)){
                this.div_content_header.hide();
            }else{
                this.div_content_header.html( header_html ).show();
            }
        } 
    
        this._adjustHeadersPos();
        //this.element.find('input[type=radio][value="'+newmode+'"]').prop('checked', true);

        if(this.view_mode_selector){
            this.view_mode_selector.find('button').removeClass('ui-heurist-btn-header1').css({'border':'none'});
            var btn =   this.view_mode_selector.find('button[value="'+newmode+'"]');
            
            if(this.options.header_class==null) btn.addClass('ui-heurist-btn-header1')                
            btn.css({'border':'solid 1px'});
            //this.view_mode_selector.controlgroup('refresh');
        }
        
        $('.password-reminder-popup').dialog('close');
    },

    //
    //
    //
    _clearAllRecordDivs: function(new_title){

        $('.password-reminder-popup').dialog('close');
        //this._currentRecordset = null;
        this._lastSelectedIndex = null;

        if(this.div_coverall){
            this.div_coverall.hide();
        }
        

        if(this.div_content){
            this.div_content.find('div.recordTitle').tooltip('destroy');
            
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content[0].innerHTML = '';//.empty();  //clear
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
                this.setHeaderText('<h3 style="margin:0">'+new_title+'</h3>');
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
        this._clearAllRecordDivs(null);
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
            $('<div>').css('padding','10px').html(this.options['empty_remark']).appendTo(this.div_content);

        }else
        if(this.options.entityName!='records'){

            $('<div>').css('padding','10px')
                .html('<h3 class="not-found" style="color:teal">No entites match the filter criteria</h3>')
                .appendTo(this.div_content);
            
        }else{

            var that = this;
        
            var $emptyres = $('<div>')
            .css('padding','1em')
            .load(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/resultListEmptyMsg.html',
                function(){
                    $emptyres.find('.acc').accordion({collapsible:true,heightStyle:'content'});
                    $emptyres.find('p').css({'padding-top':'10px'});
                    
                    $emptyres.find('.acc > h3').css({
                        background: 'none',
                        border: 'none',
                        'font-size': 'larger',
                        'font-weight': 'bold'
                    });
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
                                this._on(  $al, {
                                    click: this._doSearch4
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
                            if(rt>0 && window.hWin.HEURIST4.rectypes.names[rt]){
                                $('<span style="padding: 0 10px;font-weight:bold">('+window.hWin.HEURIST4.rectypes.pluralNames[rt]+')</span>')
                                    .appendTo($emptyres.find('.not-found2'));
                                $('<div>').button({label:'Add '+window.hWin.HEURIST4.rectypes.names[rt], icon:'ui-icon-plusthick'})
                                    .click(function(){
                                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                            {new_record_params:{RecTypeID:rt}});                                        
                                    })
                                    .appendTo($emptyres.find('.not-found2'));
                            }
                        }
                    
                    }
                    
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

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" >'
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

        //call external function to render
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

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var bkm_ID = fld('bkm_ID');
        var recTitle = fld('rec_Title'); 
        var recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
        var recTitle_strip1 = window.hWin.HEURIST4.util.stripTags(recTitle,'u, i, b, strong');
        var recTitle_strip2 = window.hWin.HEURIST4.util.stripTags(recTitle,'a, u, i, b, strong');
        var recIcon = fld('rec_Icon');
        if(!recIcon) recIcon = rectypeID;
        recIcon = window.hWin.HAPI4.iconBaseURL + recIcon + '.png';


        //get thumbnail if available for this record, or generic thumbnail for record type
        var html_thumb = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&quot;);"></div>';
        }

        // Show a key icon and popup if there is a password reminder string
        var html_pwdrem = '';
        var pwd = window.hWin.HEURIST4.util.htmlEscape(fld('bkm_PwdReminder'));
        if(pwd){
            html_pwdrem =  '<span class="ui-icon ui-icon-key rec_pwdrem" style="display:inline;left:14px;font-size:0.99em"></span>';
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
            html_owner =  '<span class="rec_owner" style="width:20px;padding-top:6;display:inline-block;color:'
                     + clr + '" title="' + hint + '"><b>' + (owner_id==0?'':owner_id) + '</b></span>';
            
            if(clr != 'blue')         
            html_owner =  html_owner + '<span class="ui-icon ui-icon-cancel" '
            +'style="color:darkgray;display:inline;font-size:1.3em;vertical-align: -1px"></span>'
                +'<span class="ui-icon ui-icon-eye" style="font-size:0.8em;color:darkgray;display:inline;vertical-align:0px" title="'
                + 'This record is not publicly visible - user must be logged in to see it'
                + '" ></span>';
                     

        }

        // construct the line or block
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" '+pwd+' rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">'
        + html_thumb
        
        + '<div class="recordIcons logged-in-only">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/> '
        +     '<span class="ui-icon ui-icon-bookmark" style="color:'+(bkm_ID?'#ff8844':'#dddddd')+';display:inline-block;"></span>'
        +     html_owner
        +     html_pwdrem
        + '</div>'


        // it is useful to display the record title as a rollover in case the title is too long for the current display area
        + '<div title="dbl-click to edit : '+recTitle_strip_all+'" class="recordTitle">'
        +     (this.options.show_url_as_link && fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"
            + recTitle_strip1 + "</a>") :recTitle_strip2)
        + '</div>'

        + '<div title="Click to edit record (opens in new tab)" '
        + ' class="rec_edit_link_ext logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only"'
        + ' role="button" aria-disabled="false" data-key="edit_ext">'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-newwin"/><span class="ui-button-text"/>'
        + '</div>'  // Replace ui-icon-pencil with ui-icon-extlink and swap position when this is finished 

        + '<div title="Click to view record (opens in popup)" '
        + 'class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-comment"/><span class="ui-button-text"/>'
        + '</div>'

        + '<div title="Click to edit record" '
        + 'class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + 'role="button" aria-disabled="false" data-key="edit">'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-pencil" style="color:gray"/><span class="ui-button-text"/>'
        + '</div>'

        // Icons at end allow editing and viewing data for the record when the Record viewing tab is not visible
        // TODO: add an open-in-new-search icon to the display of a record in the results list
        + ((!this.options.show_url_as_link && fld('rec_URL'))
            ?
        '<div title="Click to view external link (opens in new window)" '
        + 'class="rec_view_link_ext ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-extlink"/><span class="ui-button-text"/>'
        + '</div>'
            :'')
        
        
        + '</div>';


        return html;
    },

    //
    //
    //
    _recordDivOnHover: function(event){
        var $rdiv = $(event.target);
        if($rdiv.hasClass('rt-icon') && !$rdiv.attr('title')){

            $rdiv = $rdiv.parents('.recordDiv')
            var rectypeID = $rdiv.attr('rectype');
            var title = window.hWin.HEURIST4.rectypes.names[rectypeID] + ' [' + rectypeID + ']';
            $rdiv.attr('title', title);
        }
    },
    
    
    //
    //
    //
    setMultiSelction: function(ids){
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
          var key = event.which || event.keyCode;
          if(key==38 || key==40){
              
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
                
                var spos = this.div_content.scrollTop();
                var spos2 = curr_sel.position().top;
                var offh = spos2 + curr_sel.height() - this.div_content.height() + 10;
               
                if(spos2 < 0){
                    this.div_content.scrollTop(spos+spos2);
                }else if ( offh > 0 ) {
                    this.div_content.scrollTop( spos + offh );
                }
                window.hWin.HEURIST4.util.stopEvent(event);
                
                return false;
              }
          }
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
                this._trigger( "onaction", null, {action:action, recID:selected_rec_ID});

            }else if (action=='edit'){

                var query = null;
                
                if(false && this._currentRecordset && this._currentRecordset.length()<1000){
                    //it breaks order in edit form - previos/next becomes wrong
                    query = 'ids:'+this._currentRecordset.getIds().join(',');
                }else{
                    query = this._query_request;
                }

                window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, query, true, null);
                //@todo callback to change rectitle

            }else if (action=='edit_ext'){

                var url = window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                window.open(url, "_new");
            }
            return;
        }

        var ispwdreminder = $target.hasClass('rec_pwdrem'); //this is password reminder click
        if (ispwdreminder){
            var pwd = $rdiv.attr('pwd');
            var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(pwd, null, "Password reminder", 
                {my: "left top", at: "left bottom", of: $target, modal:false}
            );
            $dlg.addClass('password-reminder-popup'); //class all these popups on refresh
            return;
        }else{

            var isview = (this.options.recordview_onselect=='popup' || 
                $target.parents('.rec_view_link').length>0); //this is VIEWER click
                
            if(isview){

                var recInfoUrl = null;
                if(this._currentRecordset){
                    recInfoUrl = this._currentRecordset.fld( this._currentRecordset.getById(selected_rec_ID), 'rec_InfoFull' );
                }
                var lt = 'WebSearch';//window.hWin.HAPI4.sysinfo['layout'];  
                if( !recInfoUrl ){
                    
                    if ( typeof this.options.rendererExpandDetails === 'string' && this.options.rendererExpandDetails.substr(-4)=='.tpl' ){

                        recInfoUrl = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?publish=1&debug=0&q=ids:'
                        + selected_rec_ID
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(this.options.rendererExpandDetails);
                    }else{
                        recInfoUrl = window.hWin.HAPI4.baseURL + "viewers/record/renderRecordData.php?db="
                                +window.hWin.HAPI4.database+"&ll="+lt+"&recID="+selected_rec_ID;  
                    }
                }

                window.hWin.HEURIST4.msg.showDialog(recInfoUrl, { 
                        width: (lt=='WebSearch'?(window.hWin.innerWidth*0.9):700),
                        height: 800, title:'Record Info'});
                return;
            }
            if($target.parents('.rec_view_link_ext').length>0){
                if(this._currentRecordset){
                    var url = this._currentRecordset.fld( this._currentRecordset.getById(selected_rec_ID), 'rec_URL' );
                    if(url) window.open(url, "_new");
                }
                return;
            }
        }

        //select/deselect on click
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
        if(this.options.recordview_onselect=='inline' && this.options.view_mode=='list'){
            
            var is_already_opened = $rdiv.find('.record-expand-info').length>0;
            //close other expanded recordDivs
            this.div_content.find('.record-expand-info').remove();
            
            if(!is_already_opened){
            
            //expand selected recordDiv and draw record details inline
            if($.isFunction(this.options.rendererExpandDetails)){
                this.options.rendererExpandDetails.call(this, recordset, record);
                //this.options.rendererExpandDetails(selected_rec_ID, ele, function(){ ele.removeClass('loading'); });
            }else {
                var ele = $('<div>')
                    .css({'width':'100%','max-height':'400px','overflow':'hidden','padding-top':'5px','height':'25px'})
                    .addClass('record-expand-info').appendTo($rdiv);
                
                var infoURL;
                
                if ( typeof this.options.rendererExpandDetails === 'string' && this.options.rendererExpandDetails.substr(-4)=='.tpl' ){

                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?publish=1&debug=0&q=ids:'
                    + selected_rec_ID
                    + '&db='+window.hWin.HAPI4.database+'&template='
                    + encodeURIComponent(this.options.rendererExpandDetails);

                    ele.addClass('loading').css({'overflow-y':'auto'}).load(infoURL, function(){ 
                        var ele2 = that.div_content.find('.record-expand-info');
                        var h = Math.min(ele2[0].scrollHeight+10, 400);
                        ele2.removeClass('loading').animate({height:h},300);});
                    
                }else{
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='
                    +selected_rec_ID
                    +'&db='+window.hWin.HAPI4.database;
                    
                    $('<iframe>').appendTo(ele).addClass('loading').attr('src', infoURL).on('load',function(){ 
                        var ele2 = that.div_content.find('.record-expand-info');
                        
                        var h = $(this.contentWindow.document).height();
                        
                        var h = Math.min(h+10, 400);
                        ele2.removeClass('loading').animate({height:h},300);});
                    
                }  
                
            }
            }
        }
        

        this.triggerSelection();
    },

    triggerSelection: function(){


        if(this.options.isapplication){
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
                            var page =  Number(ui.item.attr('id').substr(4));
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
                }
                if(!ismenu)
                    span_pages.find('#page'+currentPage).css({'border':'1px solid white'});

            }
        }

        this._showHideOnWidth();
    }

    //
    //
    //    
    , refreshPage: function(){
        this._renderPage(this.current_page);
    }

    //
    // render the given page (called from navigator and on search finish)
    //
    , _renderPage: function(pageno, recordset, is_retained_selection){

        var idx, len, pagesize;

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
        
        
        this._clearAllRecordDivs(null);

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
                    rec_toload.push(recID);
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

            //
            for (var grp_val in html_groups){
                var gheader = (hasRender)
                    ?this.options.rendererGroupHeader.call(this, grp_val, this._grp_keep_status)
                    :'<div style="width:100%">'+grp_val+'</div>';
                
                var is_expanded = ($.isEmptyObject(this._grp_keep_status) || this._grp_keep_status[grp_val]==1);
                html += (gheader+'<div data-grp-content="'+grp_val
                    +'" style="display:'+(is_expanded?'block':'none')
                    +'">'+html_groups[grp_val]+'</div>');
            }
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
                            btn.find('span.ui-icon').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                        }else{
                            if(that.options.groupOnlyOneVisible){
                                //collapse other groups
                                that.div_content.find('div[data-grp]')
                                        .find('span.ui-icon')
                                        .removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                                that.div_content.find('div[data-grp-content]').hide();
                                that._grp_keep_status = {};
                                that._grp_keep_status[grp_val] = 1;    
                            }else{
                                that._grp_keep_status[grp_val] = 1;    
                            }
                            
                            
                            ele.show();
                            btn.find('span.ui-icon').removeClass('ui-icon-triangle-1-e').addClass("ui-icon-triangle-1-s");
                        }
                    });
        }
        
        
        function ___ontooltip(){
                var ele = $( this );
                return ele.attr('title');
        }
        
        this.div_content.find('div.recordTitle').tooltip({content: ___ontooltip}); //title may have html format - use jquery tooltip

        if(this.options.select_mode!='select_multi'){
            this.div_content.find('.recordSelector').hide();
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
        
        if(this.options.recordDiv_class){
            //$allrecs.addClass(this.options.recordDiv_class);    
            var that = this;
            $allrecs.each(function(idx, item){
                if(idx % 2 ==0){
                    $(item).addClass(that.options.recordDiv_class);    
                }
            });
        }
        
        //tabindex is required to keydown be active
        $allrecs.each(function(idx, item){
            $(item).prop('tabindex',idx);
        });
        
        
        this._on( $allrecs, {
            click: this._recordDivOnClick,
            //keypress: this._recordDivNavigateUpDown,
            keydown: this._recordDivNavigateUpDown,
            //keyup: this._recordDivNavigateUpDown,
            mouseover: this._recordDivOnHover,
            /* enable but specify entityName to edit in options */
            dblclick: function(event){ //start edit on dblclick
                if(!$.isFunction(this.options.renderer)){
                    var $rdiv = $(event.target);
                    if(!$rdiv.hasClass('recordDiv')){
                        $rdiv = $rdiv.parents('.recordDiv');
                    }
                    var selected_rec_ID = $rdiv.attr('recid');

                    event.preventDefault();

                    var query = null;
                    if(this._currentRecordset && this._currentRecordset.length()<1000){
                        query = 'ids:'+this._currentRecordset.getIds().join(',');
                    }else{
                        query = this._query_request;
                    }

                    window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, query, true, null);
                    //@todo callback to change rectitle


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


        this._trigger( "onpagerender", null, this );
        
        //@toto replace it to event listener in manageRecUploadedFiles
        if($.isFunction(this.options.onPageRender)){
            this.options.onPageRender.call(this);
        }

        if(this.div_content_header){
            this._adjustHeadersPos();
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
        this.div_header.find('div.result-list-header').html(newtext);
        if(headercss){
            this.div_header.css(headercss);    
            this._adjustHeadersPos();
        }
    },
    
    
    setOrderAndSaveAsFilter: function(){
                    
        if(!this.sortResultList){
            
            this.sortResultListDlg = $('<div>').appendTo(this.element);
            //init result list
            this.sortResultList = $('<div>').appendTo(this.sortResultListDlg)
                .resultList({
                   recordDiv_class: 'recordDiv_blue',
                   eventbased: false, 
                   isapplication: false, //do not listent global events @todo merge with eventbased
                   multiselect: false,
                   view_mode: 'list',
                   sortable: true,
                   show_toolbar: false,
                   select_mode: 'select_single',
                   entityName: this._entityName,
                   pagesize: 9999999999999,
                   renderer: function(recordset, record){ 
                       var recID = recordset.fld(record, 'rec_ID');
                        return '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
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
                        var  app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('svs_list');
                        if(app && app.widget){
                            var squery = 'ids:'+new_rec_order.join(',')+' sortby:set';
                            $(app.widget).svs_list('editSavedSearch', 'saved', null, svsID, squery, null, true); //call public method
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
    callResultListMenu:function( action ){
        if(this.div_actions){
                this.div_actions.resultListMenu('menuActionHandler', action);
        }
    }
    
});
