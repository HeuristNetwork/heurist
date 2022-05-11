/**
* Builds and manages display of the main Heurist page - search and visualisation
*
* Before this widget was generic, however search on main page became very distinctive and got lot of additional ui comonents.
* Thus, we have the specific search widget and this one remains for main ui
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @note        Completely revised for Heurist version 4
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


$.widget( "heurist.search", {

    // default options
    options: {
        is_h6style: false,

        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        btn_visible_newrecord: false, // show add record button
        btn_visible_save: false,      // save search popup button  NOT USED
        btn_entity_filter: true,      // show buttons: filter by entity
        search_button_label: '',
        search_input_label: '',

        button_class: 'ui-heurist-btn-header1',

        // callbacks
        onsearch: null,  //on start search
        onresult: null,   //on search result

        search_page: null, //target page (for CMS)
        search_realm:  null  //accepts search/selection events from elements of the same realm only
    },

    _total_count_of_curr_request: 0, //total count for current request (main and rules) - NOT USED

    query_request:null,

    buttons_by_entity:[], //

    _is_publication:false, //this is CMS publication - take css from parent

    // the constructor
    _create: function() {

        var that = this;

        if(this.element.parent().attr('data-heurist-app-id') || this.element.hasClass('cms-element')){

            this.options.button_class = '';
            this._is_publication = true;
            //this is CMS publication - take bg from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else if(!this.options.is_h6style){
            this.element.css({height:'100%','font-size':'0.8em'});
            this.element.addClass('ui-widget-content');
        }else {
            this.options.button_class = '';
        }

        var sz_search = '600px',
        sz_input = '450px',
        sz_search_padding = this.options.is_h6style?'5px':'20px';

        //------------------------------------------- filter by entities
        this.options.btn_entity_filter = this.options.btn_entity_filter && (window.hWin.HAPI4.get_prefs_def('entity_btn_on','1')=='1');

        if(this.options.btn_entity_filter){

            function __initEntityFilter(){

                if($.isFunction($('body')['searchByEntity'])){ //OK! widget script js has been loaded            
                    this.div_entity_fiter   = $('<div>').searchByEntity({is_publication:this._is_publication})
                    .css({'height':'auto','font-size':'1em'})
                    .appendTo( this.element );
                }else{

                    $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/search/searchByEntity.js', 
                        function() {  //+'?t='+(new Date().getTime())
                            if($.isFunction($('body')['searchByEntity'])){
                                __initEntityFilter();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr('Widget searchByEntity not loaded. Verify your configuration');
                            }
                    });

                }
            }

            __initEntityFilter();



        }         
        //------------------------------------------- filter inputs                        

        // Search functions container
        //'height':'100%', 'float':'left'   , 'min-width':sz_search  ,  
        this.div_search   = $('<div>').css({ 'width':'100%', display:'table' }).appendTo( this.element ); //was table-row



        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'auto','text-align':'right','line-height':'31px','padding-left':sz_search_padding}) //'height':'6.88em',
        .addClass('div-table-cell')
        .appendTo( this.div_search );

        if(this.options.search_input_label){
            this.div_search_header.text( this.options.search_input_label ).css({'padding-right':'4px'});     
        }


        /* hidden on 2016-11-11        
        $( "<label>" ).text(window.hWin.HR("Filter"))
        .css({'font-weight':'bold','font-size':'1.2em','padding-left':'1em','padding-right':'1em','vertical-align': 'top', 'line-height':'20px'})
        .appendTo( this.div_search_header );
        */

        // Search field
        this.div_search_input = $('<div>')
        .addClass('div-table-cell')       
        .appendTo( this.div_search );

        //promt to be shown when input is empty        
        this.input_search_prompt = $( "<span>" )
        .text(this._is_publication?'':window.hWin.HR("enter search/filter or use filter builder at right"))
        .addClass('graytext')
        .css({'font-size':'0.8em', 'margin': '18px 0 0 0.5em','position': 'absolute'})
        .appendTo( this.div_search_input );
        this._on( this.input_search_prompt, {click: function(){
            this._setFocus();
        }} );


        this.input_search = $( "<textarea>" )
        .css({//'margin-right':'0.2em', 
            'height':'41px', 
            'max-height':'70px', 
            'max-width':'99%',
            'resize':'none', 
            'padding':'2px 0px',
            'min-height':'41px', 'line-height': '14px', 
            'min-width':'80px', 'width':'100%', 'padding-right':'28px' })  //was width:sz_input, 'max-width':sz_input,  
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );

        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);

        //promt to be shown when input has complex search expression (json search)
        this.input_search_prompt2 = $( "<span>" )
        .html('<span style="font-size:1em;padding:3px;display:inline-block;">'+window.hWin.HR("filter")
            +'</span>&nbsp;&nbsp;<span class="ui-icon ui-icon-eye" style="font-size:1.8em;width: 1.7em;margin-top:1px"/>')
        .css({ height:isNotFirefox?28:24, 'margin':'1px '+(isNotFirefox?'4px':'7px')+' 1px 2px',
            'position':'relative', 'text-align':'left', display:'block'})
        .appendTo( this.div_search_input );
        this._on( this.input_search_prompt2, {click: function(){
            this.input_search_prompt2.css({visibility:'hidden'});//hide();
            this._setFocus();
        }} );

        if(this._is_publication || this.options.is_h6style){

            this.input_search.css({'height':'27px','min-height':'27px','padding':'2px 0px'}); //, 'width':'400'

            var sTop = isNotFirefox?'-35px':'-28px';
            this.input_search_prompt2.addClass('ui-widget-content').css({border:'none',top:sTop});

            this.input_search.css({width:'100%'});
            this.input_search_prompt2.css({height:'calc(100%-2px)',
                width:'calc(100%-2px)'});    
        }else{
            this.input_search_prompt2.css({top:'-35px'}); //'background':'#F4F2F4',
        }

        // AAAA
        this._on( this.input_search, {
            click: function(){ 
                if(this.input_search_prompt2.is(':visible')){
                    this.input_search_prompt2.css({visibility:'hidden'}); 
                }
            },
            keyup: this._showhide_input_prompt, 
            change: this._showhide_input_prompt
        });

        //disable because of initial search
        if(this.options.btn_visible_newrecord){
            window.hWin.HEURIST4.util.setDisabled(this.input_search, true); 
            this.input_search.css({'width':'400px','height':'1.4em','max-width':'650px'});
            this.div_search.css({'float':'left'});
        }else{

            if(this.options.is_h6style){
                this.div_search_input.css({'width':'85%','min-width':'80px',height:'30px'}); //'max-width':'470px',  
            }else{
                this.div_search.css({'display':'table',height:'30px'});
            }
        }

        //help link and quick access of saved filters
        if(!this._is_publication && this.options.is_h6style){
            var div_search_help_links = $('<div>')
            //.css({position: 'absolute', top:'auto', 'font-size':'10px'})
            .css({position: 'absolute',top:'85px','font-size':'10px'})
            .appendTo(this.div_search_input);

            var link = $('<span title="Show syntax and examples of the Heurist query/filter language">'
                +'Simple filter help <span class="ui-icon ui-icon-info" style="font-size:0.8em"></span></span>')
            .attr('id', 'search_help_link')
            .addClass('graytext')
            .css({'text-decoration':'none','outline':0, cursor:'pointer'})
            .appendTo(div_search_help_links);

            this._on( link, {  click: function(){
                window.open('context_help/advanced_search.html','_blank');
            } });
            
            this._on( this.input_search, {  click: function(){ // open search textarea in a popup, for more space, add more instructions and include filter help link

                var org_val = this.input_search.val();
                var newline_matches = org_val.match(/\r|\n/); // check for newline characters

                // Check for horizontal scrolling
                this.input_search.css('white-space', 'nowrap');
                var nw_scrollWidth = this.input_search[0].scrollWidth;
                var nw_width = this.input_search.width();                
                this.input_search.css('white-space', '');

                if(window.hWin.HEURIST4.util.isempty(org_val) 
                    || (window.hWin.HEURIST4.util.isempty(newline_matches) 
                        && (nw_scrollWidth <= nw_width))){ 
                    return;
                }

                var $dlg;
                var $help_link = this.div_search_input.find('#search_help_link').clone();

                var msg = '<div class="heurist-helper1" style="font-size: 1em;">'
                + 'The filter function accepts two types of filter string - a simple search format (see filter help)'
                + '<br>and a JSon format which is built by the filter builders and is documented in the main Help files.'
                + '<br><br>The JSon format is enclosed in [ ] and consists of a series of comma-separated specifications in { }'
                        + '<br>giving a tag (generally a field ID, field name or special indicator) followed by a colon ( ; ) then a value or values.'
                        + '<br>Tags and values are generally enclosed in double quotes ( " )</div><br>'
                        + '<textarea style="padding: 5px; margin: 5px 0px; height: 150px; width: 500px;" class="text ui-widget-content ui-corner-all">' 
                            + org_val 
                        + '</textarea><br><div id="search_help_container"></div>';

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, 
                    function(){

                        var new_val = $dlg.find('textarea').val();
                        if(org_val != new_val){

                            // Update value
                            that.input_search.val(new_val);

                            // Perform search
                            that._doSearch();
                        }

                        $dlg.dialog('close');

                    }, {yes: 'Search', no: 'Cancel'}, {title: 'Search filter', default_palette_class: 'ui-heurist-explore'}
                );

                $dlg.find('#search_help_container').append($help_link);

                $dlg.find('#search_help_link').on('click', function(){ window.open('context_help/advanced_search.html','_blank'); });
            }});
        }


        var menu_h = window.hWin.HEURIST4.util.em(1); //get current font size in em

        this.input_search.data('x', this.input_search.outerWidth());
        this.input_search.data('y', this.input_search.outerHeight());

        /*AAAA*/
        this.input_search.mouseup(function () {
            var $this = $(this);
            if ($this.outerWidth() != $this.data('x') || $this.outerHeight() != $this.data('y')) {
                //alert($this.outerWidth() + ' - ' + $this.data('x') + '\n' + $this.outerHeight() + ' - ' + $this.data('y'));
                if($this.outerHeight()<25){
                    //aaa  that.div_search.css('padding-top','1.8em');
                    $this.height(23);
                }else{
                    if($this.outerHeight()> that.element.height()-menu_h-8){    //, 'max-height': (this.element.height()-12)+':px'
                        $this.height(that.element.height()-menu_h-10);
                        pt = '2px';
                    }else{
                        //parseFloat(that.div_search.css('padding-top'))
                        pt =  (that.element.height() - $this.height())/2 - menu_h;
                    }
                    //aaa that.div_search.css('padding-top', pt );
                }
            }
            // set new height/width
            $this.data('x', $this.outerWidth());
            $this.data('y', $this.outerHeight());
        }) // this.input_search.mouseup

        //
        // quick filter builder buttons
        //
        this.div_buttons = $('<div>')
        .addClass('div-table-cell')
        .css({'text-align': 'center', 'vertical-align': 'baseline'})
        .appendTo( this.div_search );

        //
        // show saved filters tree
        //         
        if(!this._is_publication && this.options.is_h6style){

            this.div_buttons.css({'min-width':'70px'});

            this.btn_saved_filters = 
            $('<span class="ui-main-color" '
                +'style="font-size: 9px;position: relative;margin-left: -50px;min-width: 50px;cursor:pointer;">'
                +'<span style="display:inline-block;width:30px;margin-top: 4px;">saved filters</span>'
                +'<span class="ui-icon ui-icon-carat-1-s" style="font-size: inherit;height: 11px;display: inline-block;vertical-align: super;">'
                +'</span></span>')
            .appendTo(this.div_buttons);

            this._on(this.btn_saved_filters, {click: function(){
                var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                if(widget){
                    var ele = this.btn_saved_filters;
                    widget.mainMenu6('show_ExploreMenu', null, 'search_filters', 
                        {top:ele.position().top+18 , left:ele.offset().left });
                }            
            }});

            //
            var linkGear = $('<a><span class="ui-icon ui-icon-magnify-explore" style="height:22px"/></a>',{href:'#', 
                title:window.hWin.HR('Build a filter expression using a for3m-driven approach')})
            .css({display:'inline-block','padding':'12px 4px 0 4px',width:30, cursor: 'pointer'})
            .addClass('btn-aux')
            //.addClass('') //was ui-icon-gear was ui-icon-filter-form
            .appendTo(this.div_buttons);
            this._on( linkGear, {  click: this.showSearchAssistant });

            this.btn_faceted_wiz = $('<a>',{href:'#', 
                title:window.hWin.HR('Build new faceted search')})
            .css({display:'inline-block',padding:'6 2px',width:30,'margin-top':'-8px'})
            .addClass('ui-icon ui-icon-box ui-main-color btn-aux') //was ui-icon-gear was ui-icon-filter-form
            .appendTo(this.div_buttons);
            this._on( this.btn_faceted_wiz, {  click: function(){

                var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                if(widget){
                    var ele = this.btn_faceted_wiz;
                    widget.mainMenu6('show_ExploreMenu', null, 'svsAddFaceted', 
                        {top:ele.offset().top , left:ele.offset().left });
                }            
            }});



        }

        //
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_user = $('<div>') //.css({'min-width':'18em','padding-right': '10px'})
        .addClass('div-table-cell')  // logged-in-only
        .appendTo( this.div_search );

        this.btn_search_as_user = $( "<button>", {
            label: window.hWin.HR(this.options.search_button_label), 
            title: "Apply the filter/search in the search field and display results in the central panel below"
        })
        .css({'min-height':'30px','min-width':'90px'})
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' ui-button-action')
        .button({showLabel:true, icon:this._is_publication?'ui-icon-search':'ui-icon-filter'});

        if(!(this._is_publication || this.options.is_h6style)){
            this.btn_search_as_user.css({'font-size':'1.3em','min-width':'9em'})      
        }else
            if(this.options.search_button_label){
                var w = window.hWin.HEURIST4.util.px(this.options.search_button_label, this.btn_search_as_user);
                this.btn_search_as_user.css({'width': (30+w)+'px'}); 
            }

        this.btn_search_domain = $( "<button>", {
            label: window.hWin.HR("filter option")
        })
        .css({'font-size':'1em','vertical-align':'top'}) 
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' heurist-bookmark-search')
        .button({icon:'ui-icon-carat-1-s',  label: window.hWin.HR("filter domain"), showLabel:false});
        //.height( this.btn_search_as_user.height() );

        //btn_search_domain is hidden this.div_search_as_user.controlgroup();

        var dset = ((this.options.search_domain_set)?this.options.search_domain_set:'a,b').split(',');//,c,r,s';
        var smenu = "";
        $.each(dset, function(index, value){
            var lbl = that._getSearchDomainLabel(value);
            if(lbl){
                smenu = smenu + '<li id="search-domain-'+value+'"><a href="#">'+window.hWin.HR(lbl)+'</a></li>'
            }
        });

        this.menu_search_domain = $('<ul>'+smenu+'</ul>')   //<a href="#">
        .css({position:'absolute', zIndex:9999})
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                var mode =  ui.item.attr('id').substr(14);  //(ui.item.attr('id')=="search-domain-b")?"b":"a";
                that.option("search_domain", mode);
                that._refresh();
        }})
        .hide();

        this._on( this.btn_search_domain, {
            click: function() {
                $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                var menu = $( this.menu_search_domain )
                .css('width', '100px')     //was this.div_search_as_user.width()
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_domain });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
            }
        });

        //
        // Save button AFTER Filter (since 2021-01-17)
        //        
        if(!this._is_publication && this.options.is_h6style){

            var div_save_filter = $('<div>').addClass('div-table-cell logged-in-only')
            .css({'padding-top':'5px', 'vertical-align':'baseline'})
            .appendTo( this.div_search );

            this.btn_save_filter = $('<button>')
            .button({icon:'ui-icon-save',showLabel:false, label:'Save filter',iconPosition:'end'})
            .css({'margin-left': '15px'})
            .appendTo(div_save_filter);

            this._on(this.btn_save_filter, {click: function(){
                var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                if(widget){
                    var ele = this.btn_saved_filters;
                    //console.log(ele.offset().top+'  '+ele.position().top);

                    widget.mainMenu6('show_ExploreMenu', null, 'svsAdd', 
                        {top:ele.offset().top , left:ele.offset().left-300 });
                }            
            }});
        }else  
            //
            // NOT USED
            //        
            if(this.options.btn_visible_save){  

                // Save search popup button
                var div_save_filter = $('<div>').addClass('div-table-cell logged-in-only')

                if(window.hWin.HAPI4.sysinfo['layout']=='original'){
                    div_save_filter.appendTo( this.div_search );
                }else{
                    div_save_filter.css({'min-width': '245px'});
                    div_save_filter.insertBefore( this.div_search_header );
                }

                this.btn_search_save = $( "<button>", {
                    label: window.hWin.HR("Save"),
                    title: window.hWin.HR('Save the current filter and rules as a link in the navigation tree in the left panel')
                })
                .css({'min-width': '110px','vertical-align':'top','margin-left': '15px'})
                .addClass(this.options.button_class)
                .appendTo(div_save_filter)
                .button({icon: 'ui-icon-circle-arrow-s'});

                this._on( this.btn_search_save, {  click: function(){
                    window.hWin.HAPI4.SystemMgr.verify_credentials(function(){ 
                        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                        if(widget){
                            widget.svs_list('editSavedSearch', 'saved'); //call public method
                        }
                    });
                } });
            }

        /* rotate icon with given interval
        setInterval( function(){ linkGear.addClass('rotate'); 
        setTimeout( function(){ linkGear.removeClass('rotate'); }, 1000 ) }, 5000 );
        */            

        // Add record button
        if(this.options.btn_visible_newrecord){

            /* on right hand side
            this.div_add_record = $('<div>')
            .addClass('logged-in-only')
            .css({'float': 'right', 'padding': '23px 23px 0 0'})
            .appendTo( this.element );
            */

            this.div_add_record = $('<div>').css({'min-width':'36em','padding-left':'40px'})
            .addClass('div-table-cell logged-in-only')
            .appendTo( this.div_search );


            this.btn_add_record = $( "<button>", {
                title: "Click to select a record type and create a new record (entity) in the database"
            })
            .css({'font-size':'1.3em','min-width':'110px','max-width':'250px'})  
            //.addClass('logged-in-only')
            //.addClass(this.options.button_class)
            .appendTo( this.div_add_record )
            .button({label: window.hWin.HR("Add Record"), icon:'ui-icon-plusthick'}) //"ui-icon-circle-plus"
            .addClass('truncate')
            .click( function(){ 
                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                    if(that.select_rectype_addrec.val()>0){
                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                            {new_record_params:{RecTypeID:that.select_rectype_addrec.val()}});
                    }else{
                        that.btn_select_rt.click();
                    }
                }); 
            });

            this.btn_select_rt = $( "<button>")
            .css({'font-size':'1.3em'})
            .appendTo( this.div_add_record )
            //.addClass(this.options.button_class+' heurist-bookmark-search')
            .button({label:window.hWin.HR("Select record type"), icon: "ui-icon-carat-1-s", showLabel:false});

            this.btn_add_record_dialog = $( "<button>")
            .css({'font-size':'0.8em'})
            .appendTo( this.div_add_record )
            .button({label: '<div style="text-align:left;display:inline-block">'
                +window.hWin.HR('Define Parameters') +'<br>'+window.hWin.HR('Add Record')+'</div>', 
                icon: "ui-icon-carat-1-s", iconPosition:'end',
                title:'Click to define parameters and add new record'})
            .click( function(){ 
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');            
            });

            this.btn_add_record_dialog.find('.ui-button-icon').css('vertical-align','baseline');

            this.btn_select_owner = $( "<button>")
            .css({'font-size':'0.8em'})
            .appendTo( this.div_add_record )
            .button({label:'owner', icon: "ui-icon-carat-1-s", iconPosition:'end',
                title:'Ownership and access rights for new record'}).hide();
            //.addClass('truncate');
            this.btn_select_owner.find('.ui-button-icon').css('vertical-align','baseline');

            /*
            this.btn_lookup_TEMP = $( "<button>")
            .css({'font-size':'0.8em'})
            .appendTo( this.div_add_record )
            .button({label: 'lookup'})
            .click( function(){ 
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordLookup');            
            });
            */            

            /*
            this.select_rectype_addrec = $('<select>')   
            .attr('size',20)
            .addClass('text ui-corner-all ui-widget-content select_rectype_addrec') 
            .css({'position':'absolute','min-width':'250'})
            .appendTo( $('body') ) 
            .hide();
            */

            this._on( this.btn_select_rt, {
                click:  function(){

                    this.select_rectype_addrec.hSelect('open');
                    this.select_rectype_addrec.hSelect('menuWidget')
                    .position({my: "left top", at: "left bottom", of: this.btn_add_record });
                    return false;

            }});

            var that = this;
            this._on( this.btn_select_owner, {
                click:  function(){

                    var btn_select_owner = this.btn_select_owner;

                    var add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                    if(!$.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                        add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
                    }
                    if(add_rec_prefs.length<5){ //visibility groups
                        add_rec_prefs.push('');
                    }

                    //show dialog that changes ownership and view access                   
                    window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {
                        currentOwner:  add_rec_prefs[1],
                        currentAccess: add_rec_prefs[2],
                        currentAccessGroups: add_rec_prefs[4],
                        scope_types: 'none',
                        height:400, 
                        title: window.hWin.HR('Default ownership and access for new record'),
                        onClose:                         
                        function(context){

                            if(context && context.NonOwnerVisibility && 
                                (context.NonOwnerVisibility!=add_rec_prefs[2] || 
                                    context.OwnerUGrpID!=add_rec_prefs[1] ||
                                    context.NonOwnerVisibilityGroups!=add_rec_prefs[4])){

                                add_rec_prefs[1] = context.OwnerUGrpID;  
                                add_rec_prefs[2] = context.NonOwnerVisibility;  
                                add_rec_prefs[4] = context.NonOwnerVisibilityGroups;  

                                that.setOwnerAccessButtonLabel( add_rec_prefs );

                                window.hWin.HAPI4.save_pref('record-add-defaults', add_rec_prefs);
                                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'search'});
                            }

                    }});

                    return false;

            }});

            this.div_add_record.controlgroup();

        } // add record button





        // bind click events
        this._on( this.btn_search_as_user, {
            click:  function(){
                //that.option("search_domain", "a");
                that._doSearch(true);}
        });
        /* AAAA */        
        this._on( this.input_search, {
            keypress: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch(true);
                }
            },
            keydown: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 65 && e.ctrlKey) {
                    e.target.select();
                }
            }
        });

        //-----------------------

        //global listeners
        $(this.document).on(
            window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
            +window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE+' '
            +window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE){
                    //@todo update btn_select_owner label
                }
                if(!data || data.origin!='search'){
                    that._refresh();
                }
        });

        $(this.document).on(
            window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { that._onSearchGlobalListener(e, data) } );


        this.div_search.find('.div-table-cell').css('vertical-align','top');

        this._refresh();

    }, //end _create

    //
    // set label for default ownership/access button
    //   
    setOwnerAccessButtonLabel: function( add_rec_prefs ){

        var that = this;

        window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:add_rec_prefs[1]},
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var ownership = [], title = [], cnt = 0;
                    for(var ugr_id in response.data){
                        if(cnt<1){
                            ownership = response.data[ugr_id];    
                        }
                        title.push(response.data[ugr_id]);
                        cnt++;
                    }
                    if(cnt>1){
                        ownership = cnt + ' groups'; 
                        title = 'Default owners: '+title.join(', ')+'. ';
                    }else{
                        title = '';
                    }

                    var access = {hidden:'Owner only', viewable:'Logged-in', pending:'Public pending', public:'Public'};
                    if(add_rec_prefs[4]){
                        access = 'Groups';
                        title = title + 'Viewable for '+(add_rec_prefs[4].split(',').length)+' groups';
                    }else{
                        access = access[add_rec_prefs[2]];
                    }

                    that.btn_select_owner.button({'label':
                        '<div style="text-align:left;display:inline-block" title="'+title+'">'
                        +ownership+'<br>'+access+'</div>'});
                }
        });

    },

    //
    // help text inside input field
    //
    _showhide_input_prompt:function() {
        if(this.input_search.val()==''){
            this.input_search_prompt.show();    
            this.input_search_prompt2.css({'visibility':'hidden'});//hide();    
        }else{
            this.input_search_prompt.hide();     
        }
    },

    /* EXPERIMENTAL
    _initPagination: function(){
    this.div_paginator = $('<span>')
    .css('display', 'inline-block')
    .appendTo( this.div_search )
    .pagination();
    },
    */

    _setOption: function( key, value ) {
        this._super( key, value );

        if(key=='search_domain'){
            this._refresh();
        }
    },

    /* private function */
    _refresh: function(){

        if(window.hWin.HAPI4.has_access()){
            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.div-table-cell.logged-in-only').css({'display':'table-cell'});

            if(this.options.is_h6style){
                $(this.element).find('.div-table-cell:visible').css({'display':'table-cell'});
            }

            //$(this.element).find('.logged-in-only').css('visibility','visible');
            //$(this.element).find('.logged-out-only').css('visibility','hidden');
            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            //$(this.element).find('.logged-in-only').css('visibility','hidden');
            //$(this.element).find('.logged-out-only').css('visibility','visible');

            $(this.element).find('.logged-out-only').show();
            if(this.options.is_h6style){
                $(this.element).find('.div-table-cell:visible').css({'display':'table-cell'});
            }

        }

        //ART        $(this.element).find('.div-table-cell').height( $(this.element).height() );

        this.btn_search_as_user.button( "option", "label", window.hWin.HR(this._getSearchDomainLabel(this.options.search_domain)));

        this.btn_search_domain.css('display', (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1')?'inline-block':'none');

        if(this.options.btn_visible_newrecord){

            if(!this.select_rectype_addrec){ //add record selector

                this.select_rectype_addrec = window.hWin.HEURIST4.ui.createRectypeSelect();
                if(this.select_rectype_addrec.hSelect("instance")!=undefined){
                    this.select_rectype_addrec.hSelect( "menuWidget" ).css({'max-height':'450px'});                        
                }

                var that = this;
                this.select_rectype_addrec.hSelect({change: function(event, data){

                    var selval = data.item.value;
                    that.select_rectype_addrec.val(selval);
                    var opt = that.select_rectype_addrec.find('option[value="'+selval+'"]');
                    that.btn_add_record.button({label: 'Add '+opt.text().trim()});

                    var prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                    if(!$.isArray(prefs) || prefs.length<4){
                        prefs = [selval, 0, 'viewable', '']; //default to everyone   window.hWin.HAPI4.currentUser['ugr_ID']
                    }else{
                        prefs[0] = selval; 
                    }
                    window.hWin.HAPI4.save_pref('record-add-defaults', prefs);

                    window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'search'});

                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:{RecTypeID:selval}});
                    return false;
                    }
                });
                this.select_rectype_addrec.hSelect('hideOnMouseLeave', this.btn_select_rt);

            }

            var add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
            if(!$.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
            }
            if(add_rec_prefs.length<4){
                add_rec_prefs.push(''); //visibility groups
            }

            if(add_rec_prefs[0]>0) {
                this.select_rectype_addrec.val(add_rec_prefs[0]); 
                var opt = this.select_rectype_addrec.find('option[value="'+add_rec_prefs[0]+'"]');
                this.btn_add_record.button({label: 'Add '+opt.text()});
            }

            this.setOwnerAccessButtonLabel( add_rec_prefs );

        }

        this._showhide_input_prompt();

        if(!this._is_publication && this.options.is_h6style 
            && this.btn_search_as_user && this.btn_search_as_user.button('instance')){

            if(this.element.width()<440){

                this.div_buttons.css('min-width',50);
                this.div_buttons.find('.btn-aux').width(20);
                this.btn_search_as_user.button('option','showLabel',false);
                this.btn_search_as_user.css('min-width',31);
                this.btn_save_filter.css('margin-left',0);
            }else{
                this.div_buttons.css('min-width',70);
                this.div_buttons.find('.btn-aux').width(30);
                this.btn_search_as_user.button('option','showLabel',true);
                this.btn_search_as_user.css('min-width',90);
                this.btn_save_filter.css('margin-left',15);
            }

            if(this.element.width()<201){
                this.div_buttons.hide();
                this.btn_save_filter.parent().hide();
            }else{
                this.div_buttons.show();
                this.btn_save_filter.parent().show();
            }

        }

    },

    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },


    _onSearchGlobalListener: function(e, data){

        var that = this;

        if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
        {

            //accept events from the same realm only
            if(!that._isSameRealm(data)) return;

            //data is search query request
            if(data.reset){
                that.input_search.val('');
                that.input_search.change();
            }else            
                //topids not defined - this is not rules request
                if(window.hWin.HEURIST4.util.isempty(data.topids) && data.apply_rules!==true){

                    //request is from some other widget (outside)
                    if(data.source!=that.element.attr('id')){
                        var qs;
                        if($.isArray(data.q)){
                            qs = JSON.stringify(data.q);
                        }else{
                            qs = data.q;
                        }

                        if(!window.hWin.HEURIST4.util.isempty(qs)){

                            if(qs.length<10000){
                                that.input_search.val(qs);
                                that.options.search_domain = data.w;
                                that.query_request = data;
                                that._refresh();
                            }
                            if( true || window.hWin.HEURIST4.util.isJSON(data.q) || qs.length>100 ){
                                that.input_search_prompt2.css({'visibility':'visible'}); //{display:'block'}
                            }
                        }
                    }

                    var is_keep = window.hWin.HAPI4.get_prefs('searchQueryInBrowser');
                    is_keep = (is_keep==1 || is_keep==true || is_keep=='true');

                    if(is_keep && !this.options.search_realm){
                        var qs = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest(data, true);
                        if(qs && qs.length<2000){
                            var s = location.pathname;
                            while (s.substring(0, 2) === '//') s = s.substring(1);

                            window.history.pushState("object or string", "Title", s+'?'+qs );
                        }
                    }

                    that.input_search.change();

                }


            //ART that.div_search.css('display','none');
        }else 
            if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

                //accept events from the same realm only
                if(!that._isSameRealm(data)) return;

                window.hWin.HEURIST4.util.setDisabled(this.input_search, false);

                this._setFocus();
                //show if there is resulst
                if(this.btn_search_save){
                    if(window.hWin.HAPI4.currentRecordset && window.hWin.HAPI4.currentRecordset.length()>0) //
                    {
                        this.btn_search_save.show();
                    }else{
                        this.btn_search_save.hide();
                    }
                }

            }else 
                if(e.type == window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE){

                    //force recreate rectype selectors
                    if(this.select_rectype_addrec!=null){
                        this.select_rectype_addrec.remove();
                        this.select_rectype_addrec = null;
                        this._refresh();
                    }

                }



    },

    _setFocus: function(){

        if(this.input_search.is(':visible')) {
            try{
                this.input_search.focus();
            }catch(e){}
        }

    },


    /*
    _handleKeyPress: function(e){
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code == 13) {
    this._doSearch();
    }
    },
    */
    _getSearchDomainLabel: function(value){
        var lbl = null;
        if(value=='b' || value=='bookmark') { lbl = 'Bookmarks'; }
        else if(value=='r') { lbl = 'recently added'; } //not implemented
            else if(value=='s') { lbl = 'recently selected'; } //not implemented
                else if(value=='c') { lbl = 'Search (in current)'; } //todo
                    else { lbl = this.options.search_button_label; this.options.search_domain='a';}
        return lbl;
    },

    //
    // search from input - query is defined manually
    //
    _doSearch: function(fl_btn){

        var qsearch = qsearch = this.input_search.val();

        qsearch = qsearch.replace(/,\s*$/, "");

        if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            var that = this;

            /* concatemation with previos search  -- NOT USED
            if(this.options.search_domain=="c" && !window.hWin.HEURIST4.util.isnull(this.query_request)){ 
            this.options.search_domain = this.query_request.w;
            qsearch = this.query_request.q + ' AND ' + qsearch;
            }
            */

            window.hWin.HAPI4.SystemMgr.user_log('search_Record_direct');

            var request = window.hWin.HEURIST4.util.parseHeuristQuery(qsearch);

            request.w  = this.options.search_domain;
            request.detail = 'ids'; //'detail';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            request.search_page = this.options.search_page;

            this.query_request = request;

            window.hWin.HAPI4.RecordSearch.doSearch( this, request );

        }

    }



    /**
    *  public method
    *
    * @returns {Boolean}
    */
    , showSearchAssistant: function() {

        if(event && (event.detail === 2)){
            //ignore dbl click
            return;
        }

        var that = this;


        if(this.options.is_h6style){
            var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
            if(widget){
                var pos = this.element.offset();
                widget.mainMenu6('show_ExploreMenu', null, 'searchBuilder', {top:pos.top+10, left:pos.left});
            }
        }else{

            if(!$.isFunction($('body')['showSearchBuilder'])){ //OK! widget script js has been loaded

                var path = window.hWin.HAPI4.baseURL + 'hclient/widgets/search/';
                var scripts = [ path+'searchBuilder.js', 
                    path+'searchBuilderItem.js',
                    path+'searchBuilderSort.js'];
                $.getMultiScripts(scripts)
                .done(function() {
                    showSearchBuilder({ search_realm:that.options.search_realm, 
                                        search_page:that.options.search_page });
                }).fail(function(error) {
                    //console.log(error);                
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                }).always(function() {
                    // always called, both on success and error
                });

                return;            
            }


            showSearchBuilder({search_realm:that.options.search_realm});
        }
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);

        // remove generated elements
        //this.btn_search_allonly.remove();  // bookamrks search off
        if(this.btn_saved_filters) this.btn_saved_filters.remove();
        this.btn_search_as_user.remove();  // bookamrks search on
        this.btn_search_domain.remove();

        this.menu_search_domain.remove();
        this.input_search.remove();
        this.input_search_prompt.remove();

        this.div_search_as_user.remove();

        if(this.div_paginator) this.div_paginator.remove();

        this.div_search.remove();
    }



});
