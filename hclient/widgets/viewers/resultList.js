/**
* Query result listing.
*
* Requires hclient/widgets/viewers/resultListMenu.js (must be preloaded)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
        hide_view_mode: false,
        select_mode:null,//manager, select_single, select_multi
        selectbutton_label:'Select',
        action_select:null,  //array of actions
        action_buttons:null,

        recordview_onselect: false,
        multiselect: true,    //@todo replace to select_mode
        isapplication: true,  //do if false it does not listen global events @todo merge with eventbased
        showcounter: true,
        showmenu: true,       //@todo - replace to action_select and action_buttons
        innerHeader: false,   // show title of current search in header
        title: null,
        eventbased:true,
        //searchsource: null,

        empty_remark:'',
        pagesize: 100,

        renderer: null,    // renderer function to draw item
        rendererHeader: null,    // renderer function to draw header for list view-mode
        searchfull: null,  // search full list data

        //event
        onselect: null  //on select event for non event based
    },


    _query_request: null, //keep current query request

    _events: null,
    _lastSelectedIndex: -1, //required for shift-click selection
    _count_of_divs: 0,

    //navigation-pagination
    current_page: 0,
    max_page: 0,
    count_total: null,  //total records in query - actual number can be less
    hintDiv:null, // rollover for thumbnails

    _currentRecordset:null,
    _currentSelection:null, //for select_multi - to keep selection across pages and queries

    // the constructor
    _create: function() {

        var that = this;

        //that.hintDiv = new HintDiv('resultList_thumbnail_rollover', 160, 160, '<div id="thumbnail_rollover_img" style="width:100%;height:100%;"></div>');

        //this.div_actions = $('<div>').css({'width':'100%', 'height':'2.8em'}).appendTo(this.element);

        var right_padding = window.hWin.HEURIST4.util.getScrollBarWidth()+1;

        /*if(hasHeader){
        var hasHeader = ($(".header"+that.element.attr('id')).length>0);
        var header = $(".header"+that.element.attr('id'));
        header.css({'padding-left':'0.7em','background':'none','border':'none'}).html('<h3>'+header.text()+'</h3>');
        header.parent().css({'background':'none','border':'none'});
        header.parent().parent().css({'background':'none','border':'none'});
        } */

        this.div_header = null;
        if(this.options.innerHeader || this.options.select_mode=='select_multi'){
            this.div_header =  $( "<div>" ).css('height','2.2em').appendTo( this.element );

            if(this.options.innerHeader){
                $('<h3>'+window.hWin.HR('Filtered Result')+'</h3>')
                .css('padding','1em 0 0 0.7em')
                .appendTo(this.div_header);
                //set background to none
                this.element.parent().css({'background':'none','border':'none'});
                this.element.parent().parent().css({'background':'none','border':'none'});
            }

            if(this.options.select_mode=='select_multi'){
                this.show_selected_only = $( "<div>" )
                .addClass('ui-widget-content ent_select_multi')
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
        }

        this.div_toolbar = $( "<div>" )
                .addClass('div-result-list-toolbar ent_header')
                .css({top:(this.div_header!=null)?'2.5em':'0', 'border-bottom':'1px solid #cccccc'})
                .appendTo( this.element );
        this.div_content = $( "<div>" )
                .addClass('div-result-list-content ent_content_full')
                .css({'top':(this.div_header!=null)?'5.5em':'2.5em',
                              'overflow-y':'scroll'})   //@todo - proper relative layout        
        /*'left':0,'right':'0.3em','overflow-y':'scroll','padding':'0em',
            'position':'absolute',
            'border-top': '1px solid #cccccc','bottom':'15px'
            */
        .appendTo( this.element );

        this.div_loading = $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.basePathV4+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.element ).hide();

        this.action_buttons_div = $( "<span>" )
        .css({'display':'inline-block', 'padding-left':'1em'})
        .appendTo( this.div_toolbar );

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_buttons)){

            var idx = 0;
            for(idx in this.options.action_buttons){

                var key = this.options.action_buttons[idx].key;
                var title = this.options.action_buttons[idx].title;

                var btn_icon = null;
                if(key=='add') btn_icon = 'ui-icon-plus'
                else if(key=='edit') btn_icon = 'ui-icon-pencil'
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
            .zIndex(9999)
            .css('position','absolute')
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
                    $('.ui-menu').not('.horizontalmenu').hide(); //hide other
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
        .html('<input id="cb1_'+rnd+'" type="radio" name="list_lo" checked="checked" value="list"/>'
            +'<label for="cb1_'+rnd+'">'+window.hWin.HR('list')+'</label>'
            +'<input  id="cb2_'+rnd+'" type="radio" name="list_lo" value="icons"/>'
            +'<label for="cb2_'+rnd+'">'+window.hWin.HR('icons')+'</label>'
            +'<input  id="cb3_'+rnd+'" type="radio" name="list_lo" value="thumbs"/>'
            +'<label for="cb3_'+rnd+'">'+window.hWin.HR('thumbs')+'</label>'
        )
        .buttonset()
        .appendTo( this.div_toolbar );

        this._on( this.view_mode_selector, {
            click: function(event) { //it works twice - first for button, then for buttonset
                var rbid = $(event.target).parent().attr('for');
                if(!window.hWin.HEURIST4.util.isnull(rbid)){
                    var view_mode = this.element.find('#'+rbid).val();
                    //var view_mode = this.element.find("input[name='list_lo']:checked").val();
                    //console.log(this.element.parent().parent().attr('id')+'  '+rbid+' '+view_mode);
                    this._applyViewMode(view_mode);
                }
        }});

        this.element.find('input[type=radio][value="list"]').button({icons: {primary: "ui-icon-list"}, text:false, title:window.hWin.HR('list')});
        this.element.find('input[type=radio][value="icons"]').button({icons: {primary: "ui-icon-view-icons-b"}, text:false, title:window.hWin.HR('icons')});
        this.element.find('input[type=radio][value="thumbs"]').button({icons: {primary: "ui-icon-view-icons"}, text:false, title:window.hWin.HR('thumbs')});

        //----------------------
        //,'min-width':'10em'
        
        this.span_pagination = $( "<div>")
                .css({'float':'right','padding':'6px 0.5em 0 0'})
                .appendTo( this.div_toolbar );
        
        this.span_info = $( "<div>")
                .css({'float':'right','padding':'0.6em 0.5em 0 0','font-style':'italic'})
                .appendTo( this.div_toolbar );

        this._showHideOnWidth();
        
        //-----------------------

        if(this.options.showmenu){
            this.div_actions = $('<div>')
            //.css({'position':'absolute','top':3,'left':2})
            .resultListMenu()
            .appendTo(this.div_toolbar);
        }else if(!this.options.innerHeader) {
                    this.div_toolbar.hide();
        }


        //-----------------------     listener of global events
        if(this.options.eventbased)
        {
            this._events = window.hWin.HAPI4.Event.LOGIN+' '+window.hWin.HAPI4.Event.LOGOUT + " " + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE;
            if(this.options.isapplication){
                this._events = this._events + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;
            }

            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){

                    that._showHideOnWidth();
                 

                }else if(e.type == window.hWin.HAPI4.Event.LOGIN){

                    that._refresh();

                }else  if(e.type == window.hWin.HAPI4.Event.LOGOUT)
                {
                    that._clearAllRecordDivs('');

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server

                    that.loadanimation(false);

                    if(that._query_request!=null && data && data.queryid()==that._query_request.id) {

                        that._renderRecordsIncrementally(data); //hRecordSet
                    }

                    //@todo show total number of records ???


                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    that.span_pagination.hide();

                    that.setSelected(null);
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                                {selection:null, source:that.element.attr('id')} );

                    if(data){

                        if(that._query_request==null || data.id!=that._query_request.id) {  //data.source!=that.element.attr('id') ||
                            //new search from outside
                            var new_title = window.hWin.HR(data.qname || that.options.title || 'Filtered Result');
                            that._clearAllRecordDivs(new_title);

                            if(!window.hWin.HEURIST4.util.isempty(data.q)){
                                that.loadanimation(true);
                                that._renderProgress();
                            }else{
                                that._renderMessage('<div style="font-style:italic;">'+window.hWin.HR(data.message)+'</div>');
                            }

                        }

                        that._query_request = data;  //keep current query request

                    }else{ //fake restart
                        that._clearAllRecordDivs('');
                        //that.loadanimation(true);
                    }

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    that._showHideOnWidth();
                    that._renderPagesNavigator();
                    
                    if(data==null){
                        var empty_message = window.hWin.HR('No filter defined');
                        var $emptyres = that._renderMessage( empty_message );
                    }

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //this selection is triggered by some other app - we have to redraw selection
                    if(data && data.source!=that.element.attr('id')) {
                        that.setSelected(data.selection);
                    }
                }
                //that._refresh();
            });

        }
        /*
        if(this.options.isapplication){
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {
        that.option("recordset", data); //hRecordSet
        that._refresh();
        });
        }
        */

        this._refresh();

    }, //end _create

    _setOptions: function() {
        /*if(!(arguments['pagesize']>0)){
            arguments['pagesize'] = 9999999999999;
        }*/
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },

    /*_setOption: function( key, value ) {
    this._super( key, value );
    },*/


    /* private function */
    _refresh: function(){

        // repaint current record set
        //??? this._renderRecords();  //@todo add check that recordset really changed  !!!!!
        if(this.options.hide_view_mode==true){
            this.view_mode_selector.hide();
        }else{
            this.view_mode_selector.show();
        }
        this._applyViewMode(this.options.view_mode);
        
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
        if(this.div_actions) this.div_actions.remove();
        this.div_toolbar.remove();
        this.div_content.remove();

        this.menu_tags.remove();
        this.menu_share.remove();
        this.menu_more.remove();
        this.menu_view.remove();

        this._removeNavButtons();


        this._currentSelection = null;

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
    // show hide pagination and info panel depend on width
    //
    _showHideOnWidth: function(){                    
            var w = this.element.width();
            /* pagination has more priority than reccount
            if ( w < 380 || (w < 440 && this.max_page>1) ) {
                this.span_info.hide();
            }else{
                this.span_info.show();
            }
            if ( w < 380 ) {
                this.span_pagination.hide();
            }else{
                this.span_pagination.show();
            }
            */
            // pagination has LESS priority than reccount
            if ( w > 440 && this.max_page>1) {
                this.span_pagination.show();
            }else{
                this.span_pagination.hide();
            }
            if ( true || w > 340 ) {
                this.span_info.show();
            }else{
                this.span_info.hide();
            }
            
            this._updateInfo();
            
    },

    //
    // switcher listener - list;icons;thumbs
    //
    _applyViewMode: function(newmode){
        
        var allowed = ['list','icons','thumbs'];
        
        if(window.hWin.HEURIST4.util.isempty(newmode) || allowed.indexOf(newmode)<0) {
            newmode = 'list';
        }

        if(!this.div_content.hasClass(newmode)){

        
            //var $allrecs = this.div_content.find('.recordDiv');
            if(newmode){
                var oldmode = this.options.view_mode;
                this.options.view_mode = newmode;
                //this.option("view_mode", newmode);
                if(oldmode)this.div_content.removeClass(oldmode);

                //save viewmode is session
                window.hWin.HAPI4.save_pref('rec_list_viewmode', newmode);

            }else{
                //load saved value
                if(!this.options.view_mode){
                    this.options.view_mode = window.hWin.HAPI4.get_prefs('rec_list_viewmode');
                }
                if(!this.options.view_mode){
                    this.options.view_mode = 'list'; //default value
                }

                newmode = this.options.view_mode;
            }
            this.div_content.addClass(newmode);

            //show hide header
            if($.isFunction(this.options.rendererHeader)){
                
                var header_html = (newmode=='list')
                                ?this.options.rendererHeader()
                                :'';
                
                if(header_html!=''){
                    if( window.hWin.HEURIST4.util.isnull(this.div_content_header )){
                        this.div_content_header = $('<div>')
                            .css({'height':'1.5em','width':'100%','padding-left':'0.8em'})
                            .addClass('ui-widget-content"') //ui-widget ui-widget-header
                            .insertBefore(this.div_content);
                    }
                    this.div_content.css('top',(this.div_header!=null)?'7em':'4em');
                    this.div_content_header.show(); 
                    this.div_content_header.html( header_html );       
                }else if(!window.hWin.HEURIST4.util.isnull(this.div_content_header)){
                    this.div_content.css('top',(this.div_header!=null)?'5.5em':'2.5em');
                    this.div_content_header.hide();        
                }   
            }
            
        }
        //this.btn_view.button( "option", "label", window.hWin.HR(newmode));
        //this.element.find('#list_layout_'+newmode).attr('checked','checked');
        this.element.find('input[type=radio][value="'+newmode+'"]').prop('checked','checked');

        //if(this.view_mode_selector.data('uiButtonset'))
        //        this.view_mode_selector.buttonset('refresh');
    },

    //
    //
    //
    _clearAllRecordDivs: function(new_title){

        //this._currentRecordset = null;
        this._lastSelectedIndex = -1;

        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content[0].innerHTML = '';//.empty();  //clear
        }

        if(new_title!=null){

            var $header = $(".header"+this.element.attr('id'));
            if($header.length>0){
                $header.html('<h3>'+new_title+'</h3>');
                $('a[href="#'+this.element.attr('id')+'"]').html(new_title);
            }

            if(this.div_header!=null) {
                this.div_header.find('h3').html(new_title);
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

            var empty_message = window.hWin.HR('No records match the search')+
            '<div class="prompt">'+window.hWin.HR((window.hWin.HAPI4.currentUser.ugr_ID>0)
                ?'Note: some records may only be visible to members of particular workgroups'
                :'To see workgoup-owned and non-public records you may need to log in')+'</div>';

            if(this.options['empty_remark']!=''){
                empty_message = empty_message + this.options['empty_remark'];
            }

            var $emptyres = this._renderMessage( empty_message );

            if(window.hWin.HAPI4.currentUser.ugr_ID>0 && this._query_request){ //logged in and current search was by bookmarks
                var domain = this._query_request.w
                if((domain=='b' || domain=='bookmark')){
                    var $al = $('<a href="#">')
                    .text(window.hWin.HR('Click here to search the whole database'))
                    .appendTo($emptyres);
                    this._on(  $al, {
                        click: this._doSearch4
                    });

                }
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
    //  div for not loaded record
    //
    _renderRecord_html_stub: function(recID){

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" >'
        + '<div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.basePathV4+'hclient/assets/16x16.gif">'
        + '</div>'
        + '<div class="recordTitle">id ' + recID
        + '...</div>'
        + '<div title="Click to edit record (opens new tab)" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '<div title="Click to view record (opens as popup)" class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'
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
        var recTitle = window.hWin.HEURIST4.util.htmlEscape(fld('rec_Title'));
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
            html_pwdrem =  '<span class="ui-icon ui-icon-key rec_pwdrem" style="display: inline-block;"></span>';
            pwd = ' pwd="'+pwd+'" ';
        }else{
            pwd = '';
        }

        function __getOwnerName(ugr_id){
            if(ugr_id== window.hWin.HAPI4.currentUser.ugr_ID){
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
        if(owner_id && owner_id!='0'){
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
            html_owner =  '<span class="rec_owner" style="color:' + clr + '" title="' + hint + '">&nbsp;&nbsp;<b>' + owner_id + '</b></span>';
            
        }

        // construct the line or block
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" '+pwd+' rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">'
        + html_thumb
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.basePathV4+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>'
        +     '<span class="ui-icon ui-icon-bookmark" style="color:'+(bkm_ID?'#ff8844':'#dddddd')+';display:inline;left:4px">&nbsp;&nbsp;</span>'
        +     html_owner
        +     html_pwdrem
        + '</div>'

        // it is useful to display the record title as a rollover in case the title is too long for the current display area
        + '<div title="'+recTitle+' &nbsp;&nbsp;&nbsp;[ dbl-click to edit ]" class="recordTitle">'
        +     (fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"
        + recTitle + "</a>") :recTitle)
        + '</div>'

        // Icons at end allow editing and viewing data for the record when the Record viewing tab is not visible
        // TODO: add an open-in-new-search icon to the display of a record in the results list
        + '<div title="Click to edit record (opens in new tab)" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>'

        + '<div title="Click to view record (opens in popup)" '
        + '   class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + '   role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '</div>';


        return html;



        /*$('<div>',{
        id: 'rec_edit_link',
        title: 'Click to edit record'
        })
        .addClass('logged-in-only')
        .button({icons: {
        primary: "ui-icon-pencil"
        },
        text:false})
        .click(function( event ) {
        event.preventDefault();
        window.open(window.hWin.HAPI4.basePathV4 + "hclient/framecontent/recordEdit.php?db="+window.hWin.HAPI4.database+"&q=ids:"+recID, "_blank");
        })
        .appendTo($recdiv);*/


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

        var action = $target.parents().attr('data-key');//parents('div[data-key!=""]');
        if(!window.hWin.HEURIST4.util.isempty(action)){ //action_btn && action_btn.length()>0){
            //var action = action_btn.attr('data-key');
            if(this.options.renderer){
                this._trigger( "onaction", null, {action:action, recID:selected_rec_ID});
            }else if (action=='edit'){
                
                var url = window.hWin.HAPI4.basePathV3 + "records/edit/editRecord.html?db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                window.open(url, "_new");
            }
            return;
        }
        
        /* OLD WAY - to remove
        var isedit = ($target.parents('div[data-key="edit"]').length>0); //this is edit click .rec_edit_link
        var isdelete = ($target.parents('div[data-key="delete"]').length>0); //this is delete click

        if(isedit){
            if(this.options.renderer){
                this._trigger( "onaction", null, {action:'edit', recID:selected_rec_ID});
            }else{
                var url = window.hWin.HAPI4.basePathV3 + "records/edit/editRecord.html?db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                window.open(url, "_new");
            }
            return;
        }else if(isdelete){
            if(this.options.renderer){
                this._trigger( "onaction", null, {action:'delete', recID:selected_rec_ID});
            }
            return;
        }*/
            
            var ispwdreminder = $target.hasClass('rec_pwdrem'); //this is password reminder click
            if (ispwdreminder){
                var pwd = $rdiv.attr('pwd');
                window.hWin.HEURIST4.msg.showMsgDlg(pwd, null, "Password reminder", $target);
                return;
            }else{

                var isview = (this.options.recordview_onselect || 
                              $target.parents('.rec_view_link').length>0); //this is VIEWER click
                if(isview){

                    var recInfoUrl = (this._currentRecordset)
                    ?this._currentRecordset.fld( this._currentRecordset.getById(selected_rec_ID), 'rec_InfoFull' )
                    :null;

                    if( !recInfoUrl ){
                        recInfoUrl = window.hWin.HAPI4.basePathV3 + "records/view/renderRecordData.php?db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                    }

                    window.hWin.HEURIST4.msg.showDialog(recInfoUrl, { width: 700, height: 800, title:'Record Info', });
                    return;
                }
            }

        if(this.options.select_mode=='select_multi'){
            if($rdiv.hasClass('selected')){
                $rdiv.removeClass('selected');
                $rdiv.find('.recordSelector>input').prop('checked', '');
                this._currentSelection.removeRecord(selected_rec_ID);
            }else{
                $rdiv.addClass('selected')
                $rdiv.find('.recordSelector>input').prop('checked', 'checked');
                if(this._currentSelection==null){
                    this._currentSelection = this._currentRecordset.getSubSetByIds([selected_rec_ID]);
                }else{
                    this._currentSelection.addRecord(selected_rec_ID, this._currentRecordset.getById(selected_rec_ID));
                }
            }
            this._updateInfo();

        }else{


            this.div_content.find('.selected_last').removeClass('selected_last');

            if(this.options.multiselect && event.ctrlKey){

                if($rdiv.hasClass('selected')){
                    $rdiv.removeClass('selected');
                    this._lastSelectedIndex = 0;
                    //$rdiv.removeClass('ui-state-highlight');
                }else{
                    $rdiv.addClass('selected');
                    $rdiv.addClass('selected_last');
                    this._lastSelectedIndex = selected_rec_ID;
                    //$rdiv.addClass('ui-state-highlight');
                }
                //this._lastSelectedIndex = selected_rec_ID;

            }else if(this.options.multiselect && event.shiftKey){

                if(Number(this._lastSelectedIndex)>0){
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

        this.triggerSelection();
    },

    triggerSelection: function(){


        if(this.options.isapplication){
            var selected_ids = this.getSelected( true );
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, {selection:selected_ids, source:this.element.attr('id')} );
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

            if(this._currentSelection==null){
                return null;
            }else if(idsonly){
                return this._currentSelection.getIds();
            }else{
                return this._currentSelection;
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
                if(Number(this._lastSelectedIndex)>0){
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

            var recIDs_list = window.hWin.HAPI4.getSelection(selection, true);
            if( window.hWin.HEURIST4.util.isArrayNotEmpty(recIDs_list) ){

                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                    var rec_id = $(rdiv).attr('recid');
                    if(recIDs_list.indexOf(rec_id)>=0){
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
            //this.div_content.css('background','url('+window.hWin.HAPI4.basePathV4+'hclient/assets/loading-animation-white.gif) no-repeat center center');
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
    // number of records in result set (query total count) and number of selected records
    //
    _updateInfo: function(){

        var total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;

        //IJ wants just n=
        var sinfo = 'n = '+total_inquery;
        //var sinfo = 'Records: '+total_inquery;

        this.span_pagination.attr('title', sinfo);
        
        var w = this.element.width();
        
        if(this.options.select_mode=='select_multi' && this._currentSelection!=null && this._currentSelection.length()>0){
            sinfo = sinfo + " | Selected: "+this._currentSelection.length();
            if(w>400){
                sinfo = sinfo+' <a href="#">clear</a>';
            }
        }
        
        if(w<380){
            this.span_info.prop('title',sinfo);
            if(w<320){
                this.span_info.hide();
            }else {
                this.span_info.show();

                //IJ wants just n=
                this.span_info.html(w>340 || total_inquery<1000?('n = '+total_inquery):'i');
            
                /* alternative  Records->Rec->n= ->i
                if(w<340){
                    this.span_info.html(total_inquery<1000?('n='+total_inquery):'i');
                }else if(w<360){
                    this.span_info.html('n='+total_inquery);
                }else{
                    this.span_info.html('Rec:'+total_inquery);
                }*/
            }
            
        }else{
            this.span_info.prop('title','');
            this.span_info.html(sinfo);
        }
        
        

        if(this.options.select_mode=='select_multi'){
            var that = this;
            this.span_info.find('a').click(function(){
                that._currentSelection=null;
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

        if (pageCount < 2) {
            return;
        }

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

        var ismenu = (that.element.width()<620);

        var smenu = '';

        if (start != 1) {    //force first page
            if(ismenu){
                smenu = smenu + '<li id="page0"><a href="#">1</a></li>'
                if(start!=2){                                                                              6
                    smenu = smenu + '<li>...</li>';
                }
            }else{
                $( "<button>", { text: "1"}).css({'font-size':'0.7em'}).button()
                .appendTo( span_pages ).on("click", function(){ that._renderPage(0); } );
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
                    $btn.button('disable').addClass('ui-state-active').removeClass('ui-state-disabled');
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
                $( "<button>", { text: ''+pageCount }).css({'font-size':'0.7em'}).button()
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
            .zIndex(9999)
            .css({'font-size':'0.7em', 'position':'absolute'})
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
                    $('.ui-menu').not('.horizontalmenu').hide(); //hide other
                    var menu = $( this.menu_pages )
                    //.css('min-width', '80px')
                    .show()
                    .position({my: "right top", at: "right bottom", of: this.btn_page_menu });
                    $( document ).one( "click", function() { menu.hide(); });
                    return false;
                }
            });

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

            recordset = this._currentSelection;
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
            this.current_page = pageno;

            idx = pageno*this.options.pagesize;
            len = Math.min(recordset.length(), idx+this.options.pagesize)
            pagesize = this.options.pagesize;
        }

        this._clearAllRecordDivs(null);

        var recs = recordset.getRecords();
        var rec_order = recordset.getOrder();
        var rec_toload = [];
        var rec_onpage = [];

        var html = '', recID;

        for(; (idx<len && this._count_of_divs<pagesize); idx++) {
            recID = rec_order[idx];
            if(recID){
                if(recs[recID]){
                    //var recdiv = this._renderRecord(recs[recID]);
                    html  += this._renderRecord_html(recordset, recs[recID]);
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
        this.div_content[0].innerHTML += html;

        if(this.options.select_mode!='select_multi'){
            this.div_content.find('.recordSelector').hide();
        }else if(this._currentSelection!=null) { //highlight retained selected records

            for(idx=0; idx<rec_onpage.length; idx++){
                recID = rec_onpage[idx];
                if(this._currentSelection.getById(recID)!=null){
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
        this._on( $allrecs, {
            click: this._recordDivOnClick,
            mouseover: this._recordDivOnHover,
            dblclick: function(event){ //start edit on dblclick

                var $rdiv = $(event.target);
                if(!$rdiv.hasClass('recordDiv')){
                    $rdiv = $rdiv.parents('.recordDiv');
                }
                var recID = $rdiv.attr('recid');

                event.preventDefault();
                window.open(window.hWin.HAPI4.basePathV3 + "records/edit/editRecord.html?db="+window.hWin.HAPI4.database+"&recID="+recID, "_new");

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
        if(!window.hWin.HAPI4.is_logged()){
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
        }

        //load full record info - record header111
        if(rec_toload.length>0){
            var that = this;

            that.loadanimation(true);

            if($.isFunction(this.options.searchfull)){

                this.options.searchfull.call(this, rec_toload, this.current_page,
                    function(response){
                        that.loadanimation(false);
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            if(response.data.queryid==that.current_page) {
                                that._currentRecordset.fillHeader( new hRecordSet( response.data ));
                                that._renderPage( that.current_page );
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            }else{

                var ids = rec_toload.join(',');
                var request = { q: 'ids:'+ ids,
                    w: 'a',
                    detail: 'header',
                    id: that.current_page,
                    source:this.element.attr('id') };

                window.hWin.HAPI4.RecordMgr.search(request, function( response ){

                    that.loadanimation(false);

                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                        if(response.data.queryid==that.current_page) {

                            var resp = new hRecordSet( response.data );
                            that._currentRecordset.fillHeader( resp );

                            //remove records that we can't recieve data
                            for(i in rec_toload){
                                var recID = rec_toload[i];
                                if(resp.getById(recID)==null){
                                    that._currentRecordset.removeRecord(recID);
                                    //that._currentRecordset.setRecord(recID,{rec_ID:recID, rec_Title:'Error! Cannot get data from server'});
                                }
                            }

                            that._renderPage( that.current_page );
                        }

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                });

            }


        }

    },

    showRetainedSelection: function(){

        //if(window.hWin.HEURIST4.util.isnull(need_show)){
        var need_show = this.cb_selected_only.is(':checked');


        if(need_show && this._currentSelection!=null && this._currentSelection.length()>0){
            this._renderPage(0, null, true);
        }else{
            this._renderPage(this.current_page);
        }
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
    
    applyFilter:function(request){
        
        $.each(this._currentRecordset)
        this.recordList.find
        
        
    },
    
    hideHeader: function(do_hide){
        if(do_hide){
            if(this.div_header!=null) this.div_header.hide();
            this.div_toolbar.hide();
            this.div_content.css('top','0px');
        }else{
            if(this.div_header!=null) this.div_header.show();
            this.div_toolbar.show();
            this.div_content.css('top',(this.div_header!=null)?'5.5em':'2.5em');
        }
    }

});
