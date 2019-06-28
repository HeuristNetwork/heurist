/**
* Builds and manages display of the main Heurist page - search and visualisation
*
* Before this widget was generic, however search on main page became very distinctive and got lot of additional ui comonents.
* Thus, we have the specific search widget and this one remains for main ui
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        isloginforced:true,

        btn_visible_newrecord: true, //show add record button
        btn_visible_save: false,

        btn_entity_filter: true,
        
        // before this widget was generic, however search on main page became very distinctive and got
        // lot of additional ui comonents. thus, we have the specific search widget and this one remains for main ui
        // for dialog mode - remove into separate widget
        isrectype: false,  // show rectype selector
        rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

        isapplication:true,  // send and recieve the global events
        // callbacks
        onsearch: null,  //on start search
        onresult: null   //on search result
    },

    _total_count_of_curr_request: 0, //total count for current request (main and rules) - NOT USED

    query_request:null,
    
    buttons_by_entity:[], //

    
    // the constructor
    _create: function() {

        var that = this;


        /*if(!$.isFunction( hSearchIncremental )){        //jquery.fancytree-all.min.js
        $.getScript(window.hWin.HAPI4.baseURL+'hclient/core/search_incremental.js', function(){ that._create(); } );
        return;
        }*/
        this.element.css({'height':'7.88em', 'min-width':'1100px'});//'border-bottom':'1px solid lightgray'
        if(window.hWin.HAPI4.sysinfo['layout']!='H4Default' && window.hWin.HAPI4.sysinfo['layout']!='H5Default'){
            this.element.addClass('ui-heurist-header1'); //dark navy bg - used long ago in original layout - to remove
        }else{
            this.element.addClass('ui-widget-content');
        }

        //var css_valign = {'position': 'relative', 'top': '50%', 'transform': 'translateY(-50%)',
        //          '-webkit-transform': 'translateY(-50%)', '-ms-transform': 'translateY(-50%)'};

        var sz_search = '500px',
        sz_input = '350px',
        sz_search_padding = '0';


        var div_left_visible = (!this.options.isloginforced || this.options.btn_visible_dbstructure);

        if(false) //div_left_visible)
        {

            // database summary, login and register buttons in navigation panel
            var div_left  = $('<div>')
            .css({'height':'2em','width':0, 'padding':'1.8em','float':'left'})
            .appendTo( this.element );

            if(!this.options.isloginforced){
                // Login button if not already logged in
                this.btn_login = $( "<button>" ) // login button
                .css('width',(window.hWin.HAPI4.sysinfo.registration_allowed==1)?'80px':'160px')
                .addClass('logged-out-only')
                .addClass('ui-heurist-btn-header1')
                .appendTo(div_left)
                .button({label: window.hWin.HR("Login"), icon:'ui-icon-key'})
                .click( function(){ that._doLogin(); });

                // Register button if the database permits user registration
                if(window.hWin.HAPI4.sysinfo.registration_allowed==1){
                    this.btn_register = $( "<button>", {
                        label: window.hWin.HR("Register")
                    })
                    .css('width','80px')
                    .addClass('logged-out-only')
                    .addClass('ui-heurist-btn-header1')
                    .appendTo(div_left)
                    .button()
                    .click( function(){ that._doRegister(); });
                } // register button
                
                div_left.css('width','200px');

            } // not bypassing login

        }else{ // lefthand - navigation - panel not visible
            sz_search = '600px';
            sz_input = '450px';
            sz_search_padding = '20px';
        }

        //------------------------------------------- filter by entities
        var is_vis = (window.hWin.HAPI4.get_prefs_def('entity_btn_on','1')=='1');
        
        this.div_entity_btns   = $('<div>').addClass('heurist-entity-filter-buttons')
                                .css({ 'display':'block','padding':'5px '+sz_search_padding,
                                    'visibility':is_vis?'visible':'hidden',
                                    'height':is_vis?'auto':'10px'})
                                .appendTo( this.element );
        //quick filter by entity
        this.btns_by_entity = $('<button>').button({label: window.hWin.HR("Show list of entities to filter"), 
                showLabel:false, icon:'ui-icon-gear'})
        .css({'font-size':'0.9em'})        
        .appendTo(this.div_entity_btns);
        
        this.btns_by_entity_options = {select_name:'select_btns_by_entity', 
                useIcons: true, useCounts:true, useGroups:true, useCheckboxes:true, 
                ancor: this.btns_by_entity, 
                marked: this.buttons_by_entity,
                onmarker: function (ele){
                    var is_checked = !ele.hasClass('ui-icon-check-on');
                    var rty_ID = ele.attr('data-id');
                    
                    ele.removeClass('ui-icon-check-'+(is_checked?'off':'on'))
                        .addClass('ui-icon-check-'+(is_checked?'on':'off'));                    
                    
                    var idx = window.hWin.HEURIST4.util.findArrayIndex(rty_ID, this.buttons_by_entity);
                    if(is_checked){
                        if(idx<0) this.buttons_by_entity.push(rty_ID);    
                    }else{
                        if(idx>=0) this.buttons_by_entity.splice(idx, 1);    
                    }
                    this._redraw_buttons_by_entity();
                },
                onselect: function __onSelectRectypeFilter(event, data){
                               var selval = data.item.value;
                               if(selval>0){
                                   that.select_rectype_filter.val(selval);
                                   that.input_search.val('t:'+selval);
                                   that._doSearch(true);
                               }
                               return false;
                           }};            
        
        if(!this.select_btns_by_entity){
            this._openSelectRectypeFilter(this.btns_by_entity_options, true);
        }
        
        this._on( this.btns_by_entity, {  click: function(){
                this._openSelectRectypeFilter( this.btns_by_entity_options );
                return false;
        } });

         
        //------------------------------------------- filter inputs                        

        // Search functions container
        //'height':'100%', 'float':'left'   , 'min-width':sz_search
        this.div_search   = $('<div>').css({ 'float':'left', 
                                'padding-left':sz_search_padding}).appendTo( this.element );

               
        
        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'0','text-align':'right', 'height':'6.88em'}) //was width:110px
        .addClass('div-table-cell')
        .appendTo( this.div_search );
/* hidden on 2016-11-11        
        $( "<label>" ).text(window.hWin.HR("Filter"))
        .css({'font-weight':'bold','font-size':'1.2em','padding-left':'1em','padding-right':'1em','vertical-align': 'top', 'line-height':'20px'})
        .appendTo( this.div_search_header );
*/

        // Search field
        this.div_search_input = $('<div>')
        .addClass('div-table-cell')
        .appendTo( this.div_search );

        this.input_search_prompt = $( "<span>" ).text(window.hWin.HR("enter search/filter or use filter builder at right"))
        .css({'color':'gray','font-size':'0.8em', 'margin': '22px 0 0 0.5em',
              'position': 'absolute'})
        .appendTo( this.div_search_input );
        this._on( this.input_search_prompt, {click: function(){
                //AAAA 
                this.input_search.focus()
        }} );

        
        this.input_search = $( "<textarea>" )
        .css({'margin-right':'0.2em', 'height':'2.5em', 'max-height':'6.6em', //'height':'2.5em', 'max-height':'67px', 
            'max-width':sz_input, 'padding':'0.4em', 
            'min-width':'10em', 'width':sz_input, 'padding-right':'18px' }) 
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );
        
        this._on( this.input_search, {
            keyup: this._showhide_input_prompt, 
            change: this._showhide_input_prompt
            });
        
        window.hWin.HEURIST4.util.setDisabled(this.input_search, true);
        
        var div_search_help_links = $('<div>').css('padding-top','6px').appendTo(this.div_search_input);

        var link = $('<span title="Show syntax and examples of the Heurist query/filter language">'
        +'filter help <span class="ui-icon ui-icon-info" style="font-size:0.8em"></span></span>')                
        .css({'text-decoration':'none','color':'gray', 'outline':0, cursor:'pointer'})
        .appendTo(div_search_help_links);
        
        this._on( link, {  click: function(){
            window.open('context_help/advanced_search.html','_blank');
        } });
        
        //quick filter by entity
        this.filter_by_entity = $('<span title="Show list of entities to filter">'
        +'entity types <span class="ui-icon ui-icon-triangle-1-s"></span></span>')                                          //
        .css({'text-decoration':'none','color':'gray','padding-left':'16px','outline':0, cursor:'pointer'})
        .appendTo(div_search_help_links);
        
        this.filter_by_entity_options = {select_name:'select_rectype_filter', 
                useIcons: true, useCounts:true, useGroups:false, 
                ancor:this.filter_by_entity, 
                onselect: function __onSelectRectypeFilter(event, data){
                               var selval = data.item.value;
                               if(selval>0){
                                   that.select_rectype_filter.val(selval);
                                   that.input_search.val('t:'+selval);
                                   that._doSearch(true);
                               }
                               return false;
                           }};            
        
        if(!this.select_rectype_filter){
            this._recreateSelectRectypeFilter(this.filter_by_entity_options);
        }
        
        this._on( this.filter_by_entity, {  click: function(){
                this._openSelectRectypeFilter( this.filter_by_entity_options );
                return false;
        } });
        
       
        /*div_search_help_links.position({
            of: this.input_search,
            my: 'left top',
            at: 'left bottom'});*/

        
        var menu_h = window.hWin.HEURIST4.util.em(1);


        this.input_search.data('x', this.input_search.outerWidth());
        this.input_search.data('y', this.input_search.outerHeight());


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
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_guest = $('<div>')
        .addClass('div-table-cell logged-out-only')
        .appendTo( this.div_search );

        this.btn_search_as_guest = $( "<button>")
        .appendTo( this.div_search_as_guest )
        .addClass('ui-heurist-btn-header1')
        .button({label: window.hWin.HR("filter"), iconPosition: 'end', icon:"ui-icon-search"});

        this.div_search_as_user = $('<div>')
        .addClass('div-table-cell logged-in-only')
        .css({'padding-right': '10px'})
        .appendTo( this.div_search );

        this.btn_search_as_user = $( "<button>", {
            label: window.hWin.HR("filter"), title: "Apply the filter/search in the search field and display results in the central panel below"
        })
        .css({'font-size':'1.3em', 'width':'8em'})  //'width':'10em', 
        .appendTo( this.div_search_as_user )
        .addClass('ui-heurist-btn-header1')
        .button({showLabel:true, icon:'ui-icon-filter'});
        
        this.btn_search_domain = $( "<button>", {
            label: window.hWin.HR("filter option")
        })
        .css({'vertical-align':'top', 'font-size':'1.45em'})
        .appendTo( this.div_search_as_user )
        .addClass('ui-heurist-btn-header1 heurist-bookmark-search')
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
            .addClass('ui-heurist-btn-header1')
            .appendTo(div_save_filter)
            .button({icon: 'ui-icon-circle-arrow-s'});

            this._on( this.btn_search_save, {  click: function(){
                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){ 
                var  app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('svs_list');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method
                }
                });
            } });
        }


        // Manage structure button
        if(window.hWin.HAPI4.sysinfo['layout']=='original'){
            
        this.btn_manage_structure = $( "<button>", {
                label: window.hWin.HR("Manage Structure"),
                title: "Add new / modify existing record types - general characteristics, data fields and rules which compose a record"
            })
            .css({'width':'140px','min-width': '120px','margin-left':'3em'})
            //.addClass('logged-in-only')
            .addClass('ui-heurist-btn-header1')
            .appendTo( this.div_add_record )
            .button()
            .click(function(){ 
                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){ 
                    window.hWin.HEURIST4.msg.showDialog(window.HAPI4.baseURL + 'admin/structure/rectypes/manageRectypes.php?popup=1&db='+window.hWin.HAPI4.database,
                    { width:1200, height:600, title:'Manage Structure', 
                      afterclose: function(){ window.hWin.HAPI4.SystemMgr.get_defs_all( false, that.document)}} )
                });
            });
        }    
        

        this.div_buttons = $('<div>')
        .addClass('div-table-cell logged-in-only')
        .css({'text-align': 'center'}) // , 'width':'50px'
        .insertBefore( this.div_search_as_guest );
        
        /* according to new design 2016-10-05 - outdated

        // Quick search builder dropdown form
        var link = $('<button>')
        .button({icon: 'ui-icon-arrowthick-1-s', showLabel:false,
            label:'Dropdown form for building a simple filter expression',
            title:window.hWin.HR('Build a filter expression using a form-driven approach (simple and advanced options)')})
        .addClass('ui-heurist-btn-header1')
        .css({'width':'40px','vertical-align': '-4px'})  //'padding':'0 1.0em',
        .appendTo(this.div_buttons);*/

        var linkGear = $('<a>',{href:'#', 
        title:window.hWin.HR('Build a filter expression using a form-driven approach (simple and advanced options)')})
        .css({'padding-right':'1.5em','display':'inline-block','margin-left':'-65px','opacity':'0.5','margin-top': '0.6em'})
        .addClass('ui-icon ui-icon-filter-form') //was ui-icon-gear
        .appendTo(this.div_buttons);
        this._on( linkGear, {  click: this.showSearchAssistant });
        
        /* rotate icon with given interval
        setInterval( function(){ linkGear.addClass('rotate'); 
                    setTimeout( function(){ linkGear.removeClass('rotate'); }, 1000 ) }, 5000 );
        */            

        this.search_assistant = null;

        if(this.options.isrectype){

            $("<label>for&nbsp;</label>").appendTo( this.div_search );

            this.select_rectype = $( "<select>" )
            .addClass('text ui-corner-all ui-widget-content') 
            .css('width','auto')
            .css('max-width','200px')
            //.val(value)
            .appendTo( this.div_search );
        }
        
        // Info button - moved after search buttons
        this.div_buttons = $('<div>')
        .addClass('div-table-cell')
        .css({'text-align': 'center'}) // ,     ,'width': '20px'
        .appendTo( this.div_search ); //.insertBefore( this.div_search_as_guest );

        // Add record button
        if(this.options.btn_visible_newrecord){

            /* on right hand side
            this.div_add_record = $('<div>')
            .addClass('logged-in-only')
            .css({'float': 'right', 'padding': '23px 23px 0 0'})
            .appendTo( this.element );
            */
            
            this.div_add_record = $('<div>')
            .addClass('div-table-cell logged-in-only')
            .appendTo( this.div_search );


            this.btn_add_record = $( "<button>", {
                title: "Click to select a record type and create a new record (entity) in the database"
            })
            .css({'min-width':'110px','margin-left':'4em','font-size':'1.3em','max-width':'250px'})
            //.addClass('logged-in-only')
            //.addClass('ui-heurist-btn-header1')
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
            //.addClass('ui-heurist-btn-header1 heurist-bookmark-search')
            .button({label:window.hWin.HR("Select record type"), icon: "ui-icon-carat-1-s", showLabel:false});
            
            this.btn_add_record_dialog = $( "<button>")
            .css({'font-size':'0.765em'})
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
            .css({'font-size':'0.765em'})
            .appendTo( this.div_add_record )
            .button({label:'owner', icon: "ui-icon-carat-1-s", iconPosition:'end',
                     title:'Ownership and access rights for new record'}).hide();
            //.addClass('truncate');
            this.btn_select_owner.find('.ui-button-icon').css('vertical-align','baseline');
            
            
            
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

        this._on( this.btn_search_as_guest, {
            click: function(){
                that.option("search_domain", "a");
                that._doSearch(true);
            }
        });
        
        this._on( this.input_search, {
            keypress: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch(true);
                }
            }
        });

        //-----------------------

        //global listeners
        $(window.hWin.document).on(
            window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
                +window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {
                    
            if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE){
                //@todo update btn_select_owner label
            }
            if(!data || data.origin!='search'){
                that._refresh();
            }
        });
        $(window.hWin.document).on(
            window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { that._onSearchGlobalListener(e, data) } );
            
            
        this.div_search.find('.div-table-cell').css('vertical-align','top');

        this._refresh();

        /* search on load
        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.query_request)){
        this.input_search.val(window.hWin.HEURIST4.query_request.q);
        this.options.search_domain = window.hWin.HEURIST4.query_request.w;
        }
        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.query_request)){
        this._doSearch()
        }*/

    }, //end _create
             
    //
    //
    //
    _redraw_buttons_by_entity: function(is_init){
        
        if(is_init===true){
            //get from preferences
            this.buttons_by_entity = window.hWin.HAPI4.get_prefs_def('entity_filter_btns','');
            
            if(window.hWin.HEURIST4.util.isempty(this.buttons_by_entity)){
                this.buttons_by_entity = [];
                
                if(true){
                    //get 5 from first group
                    var rectypes = window.hWin.HEURIST4.rectypes.groups[0].allTypes;
                    for(var m=0; m<rectypes.length && m<5; m++){
                        this.buttons_by_entity.push(rectypes[m]);
                    }
                }else{
                    //get 5 top most used rectypes
                    var sorted = [];
                    for(var rty_ID in window.hWin.HEURIST4.rectypes.counts)
                    if(rty_ID>0){
                        sorted.push({'id':rty_ID, 'cnt':window.hWin.HEURIST4.rectypes.counts[rty_ID]});
                    }
                    sorted.sort(function(a,b){
                         return Number(a['cnt'])<Number(b['cnt'])?1:-1;
                    });
                    for(var idx=0; idx<sorted.length && idx<5; idx++){
                        this.buttons_by_entity.push(sorted[idx]['id']);    
                    }
                }
            }else{
                this.buttons_by_entity = this.buttons_by_entity.split(',');    
            }
            
            
            
        }
        
        
        this.div_entity_btns.find('.entity-filter-button').remove();
        
        var idx=0;
        while(idx<this.buttons_by_entity.length){
            
            var rty_ID = this.buttons_by_entity[idx];
            
            if(rty_ID>0 && window.hWin.HEURIST4.rectypes.names[rty_ID]) {           
            
                $('<button>').button({label: '<span class="truncate" style="max-width:100px;display:inline-block;">'
                        + window.hWin.HEURIST4.rectypes.names[rty_ID] + '</span>'
                        + '<span style="float:right;font-size:0.8em;padding:2px">('
                        +  ((window.hWin.HEURIST4.rectypes.counts[rty_ID]>0)?window.hWin.HEURIST4.rectypes.counts[rty_ID]:0)
                        +')</span>', 
                    showLabel:true})  //icon:window.hWin.HAPI4.iconBaseURL + rty_ID + '.png'
                    .attr('data-id', rty_ID)
                    .css({'font-size':'0.9em'})        
                    .addClass('entity-filter-button ui-state-active')
                    .insertBefore(this.btns_by_entity); //appendTo(this.div_entity_btns);
                
                this._on( this.div_entity_btns.find('.entity-filter-button'), {  click: function(e){
                       var selval = $(e.target).is('button')?$(e.target):$(e.target).parent('button');
                       selval = selval.attr('data-id');
                       if(selval>0){
                           this.select_rectype_filter.val(selval);
                           this.input_search.val('t:'+selval);
                           this._doSearch(true);
                       }
                } });
            
                idx++;
            }else{
                //remove wrong(removed) rectypes
                is_init = false;
                this.buttons_by_entity.splice(idx,1);
            }
            
        }//for
        
        if(is_init!==true){
            //save in user preferences
            window.hWin.HAPI4.save_pref('entity_filter_btns', this.buttons_by_entity.join(','));
        }
            
    },

    
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
    
    
    _showhide_input_prompt:function() {
                if(this.input_search.val()==''){
                    this.input_search_prompt.show();    
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

        if(key=='rectype_set' || key=='search_domain'){
            this._refresh();
        }
    },

    /* private function */
    _refresh: function(){

        if(window.hWin.HAPI4.has_access()){
            $(this.element).find('.logged-in-only').show();
            //$(this.element).find('.logged-in-only').css('visibility','visible');
            //$(this.element).find('.logged-out-only').css('visibility','hidden');
            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            //$(this.element).find('.logged-in-only').css('visibility','hidden');
            //$(this.element).find('.logged-out-only').css('visibility','visible');
            $(this.element).find('.logged-out-only').show();

            if(this.options.isloginforced){
                this._doLogin();
            }
        }

//ART        $(this.element).find('.div-table-cell').height( $(this.element).height() );

        this.btn_search_as_user.button( "option", "label", window.hWin.HR(this._getSearchDomainLabel(this.options.search_domain)));

        this.btn_search_domain.css('display', (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1')?'inline-block':'none');

        if(this.select_rectype){ 
            this.select_rectype.empty();
            window.hWin.HEURIST4.ui.createRectypeSelect(this.select_rectype.get(0), 
                        this.options.rectype_set, 
                        !this.options.rectype_set, false);
                        
        }
        if(!this.select_rectype_addrec){ //add record selctor

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
        
        if(!this.select_rectype_filter){
            this._recreateSelectRectypeFilter( this.filter_by_entity_options );
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
       
        this._showhide_input_prompt();
    },

    //
    //
    //
    _recreateSelectRectypeFilter: function(opts){
            var that = this;

            var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
            
            var select_rectype = opts['select_name'];
            
            opts.useIds = (exp_level<2);
            
            this[select_rectype] = window.hWin.HEURIST4.ui.createRectypeSelectNew(null, opts);
                        
            if(this[select_rectype].hSelect("instance")!=undefined){
                var menu = this[select_rectype].hSelect( "menuWidget" );
                menu.css({'max-height':'450px'});                        
            }
            
            
            this[select_rectype].hSelect({change: opts.onselect});
            this[select_rectype].hSelect('hideOnMouseLeave', opts.ancor);
    },
    
    _openSelectRectypeFilter: function( opts, is_init ){
        
                var select_rectype = opts['select_name'];
        
                var that = this;
                function __openSelect(){
                    
                    that[select_rectype].hSelect('open');
                    that[select_rectype].val(-1);
                    that[select_rectype].hSelect('menuWidget')
                        .position({my: "left top", at: "left+10 bottom-4", of: opts['ancor']});
            
                    var menu = $(that[select_rectype].hSelect('menuWidget'));
                    var ele = $(menu[0]);
                    ele.scrollTop(0);        
                   
                    if(opts.useCheckboxes && $.isFunction(opts.onmarker)){
                        var spans = menu.find('span.rt-checkbox');
                        that._off(spans,'click');
                        that._on(spans,{'click':function(e){
                            if($(event.target).is('span')){
                                opts.onmarker.call(that, $(event.target) );
                                window.hWin.HEURIST4.util.stopEvent(e);
                            }}});
                        /*
                        menu.find('span.rt-checkbox').click(function(e){
                            if($(event.target).is('span')){
                                opts.onmarker.call(that, $(event.target) );
                                window.hWin.HEURIST4.util.stopEvent(e);
                            }
                        });
                        */
                    }
                    
                }
            
                if(!window.hWin.HEURIST4.rectypes.counts ||
                    (new Date()).getTime() - window.hWin.HEURIST4.rectypes.counts_update > 30000)  //30 seconds
                {
                        window.hWin.HEURIST4.rectypes.counts_update = (new Date()).getTime();
                    
                        var request = {
                                'a'       : 'counts',
                                'entity'  : 'defRecTypes',
                                'mode'    : 'record_count',
                                'ugr_ID'  : window.hWin.HAPI4.user_id()
                                };
                                
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                    
                                    window.hWin.HEURIST4.rectypes.counts = response.data;
                                    
                                    if(is_init==true){ //it applicable for entity filter buttons
                                        that._redraw_buttons_by_entity(true);
                                        opts.marked = that.buttons_by_entity;
                                    }                                    
                                    
                                    that._recreateSelectRectypeFilter( opts );
                                    
                                    if(is_init!==true){
                                        __openSelect();    
                                    }
                                    
                        
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                    window.hWin.HEURIST4.rectypes.counts_update = 0;
                                }
                        });

                }else{
                    __openSelect();
                }    
    },
        



    _showAdvancedAssistant: function(){
        //call Heurist vsn 3 search builder
        var q = "",
        that = this;
        if(this.input_search.val()!='') {
            q ="&q=" + encodeURIComponent(this.input_search.val());
        }else if(!Hul.isnull(this.query_request) && !Hul.isempty(this.query_request.q)){
            q ="&q=" + encodeURIComponent(this.query_request.q);
        }

        var url = window.hWin.HAPI4.baseURL+ "hclient/widgets/search/queryBuilderPopup.php?db=" 
                            + window.hWin.HAPI4.database + q;

        window.hWin.HEURIST4.msg.showDialog(url, { width:740, height:540, title:'Advanced Search Builder', callback:
            function(res){
                if(!Hul.isempty(res)) {
                    that.input_search.val(res);
                    that.input_search.change();
                    that._doSearch(true);
                }
        }});
    },


    _onSearchGlobalListener: function(e, data){

        var that = this;

        if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

            //data is search query request
            //topids not defined - this is not rules request
            if(data!=null && window.hWin.HEURIST4.util.isempty(data.topids) && data.apply_rules!==true){

                //request is from some other widget (outside)
                if(data.source!=that.element.attr('id')){
                    var qs;
                    if($.isArray(data.q)){
                        qs = JSON.stringify(data.q);
                    }else{
                        qs = data.q;
                    }

                    if(!window.hWin.HEURIST4.util.isempty(qs) && qs.length<10000){
                        that.input_search.val(qs);
                        that.options.search_domain = data.w;
                        that._refresh();
                    }
                }

                var is_keep = window.hWin.HAPI4.get_prefs('searchQueryInBrowser');
                is_keep = (is_keep==1 || is_keep==true || is_keep=='true');
                
                if(is_keep){
                    var qs = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest(data, true);
                    if(qs && qs.length<2000){
                        window.history.pushState("object or string", "Title", location.pathname+'?'+qs );
                    }
                }
                
                that.input_search.change();

            }else if(data==null){
                that.input_search.val('');
                that.input_search.change();
            }
            

            //ART that.div_search.css('display','none');

        }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server


        }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

            window.hWin.HEURIST4.util.setDisabled(this.input_search, false);
            //AAAA 
            if(this.input_search.is(':visible')) {
                try{
                    this.input_search.focus();
                }catch(e){}
            }
            
            //show if there is resulst
            if(this.btn_search_save){
                if(window.hWin.HAPI4.currentRecordset && window.hWin.HAPI4.currentRecordset.length()>0) //
                {
                    this.btn_search_save.show();
                }else{
                    this.btn_search_save.hide();
                }
            }

        }else if(e.type == window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE){

            if(this.search_assistant!=null){
                this.search_assistant.remove();
                this.search_assistant = null;
            }
            
            if(this.select_rectype_addrec!=null){
                this.select_rectype_addrec.remove();
                this.select_rectype_addrec = null;
                this._refresh();
            }
            
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
                    else { lbl = 'Filter'; this.options.search_domain='a';}
        return lbl;
    },

    //
    // search from input - query is defined manually
    //
    _doSearch: function(fl_btn){

        //if(!window.hWin.HEURIST4.util.isempty(search_query)){
        //    this.input_search.val(search_query);
        //}

        var qsearch;
        if(!fl_btn){

            var select_rectype = $("#sa_rectype");
            var select_fieldtype = $("#sa_fieldtype");
            var select_fieldvalue = $("#sa_fieldvalue");
            var select_sortby = $("#sa_sortby");
            var select_terms = $("#sa_termvalue");
            var select_coord1 = $("#sa_coord1");
            var select_coord2 = $("#sa_coord2");
            var sortasc =  $('#sa_sortasc');

            if( (select_rectype && select_rectype.val()) || 
                (select_fieldtype && select_fieldtype.val()) || 
                (select_fieldvalue && select_fieldvalue.val())){
               
                this.calcShowSimpleSearch();
                qsearch = this.input_search.val();
            }
                
        }else{
            qsearch = this.input_search.val();
        }


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
            request.detail = 'detail';
            request.source = this.element.attr('id');

            window.hWin.HAPI4.SearchMgr.doSearch( this, request );

        }

    }



    /**
    *  public method
    *
    * @returns {Boolean}
    */
    , showSearchAssistant: function() {
        $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
        $('.menu-or-popup').hide(); //hide other

        if(this.search_assistant){ //inited already

            var popup = $( this.search_assistant );
            var inpt = $(this.input_search).offset();
            popup.css({'left': inpt.left, 'top':inpt.top+$(this.input_search).height()+3 });
            //popup.position({my: "left top+3", at: "left bottom", of: this.input_search })

            popup.show( "blind", {}, 500 );


            function _hidethispopup(event) {
                if($(event.target).closest(popup).length==0 && $(event.target).attr('id')!='menu-search-quick-link'){
                    popup
                    //.position({my: "left top+3", at: "left bottom", of: this.input_search })
                    .hide( "blind", {}, 500 );
                }else{
                    $( document ).one( "click", _hidethispopup);
                    //return false;
                }
            }

            $( document ).one( "click", _hidethispopup);//hide itself on click outside
        }else{ //not inited yet
            this._initSearchAssistant();
        }

        return false;
    }

    , _initSearchAssistant: function(){

        var $dlg = this.search_assistant = $( "<div>" )
        .addClass('text ui-corner-all ui-widget-content ui-heurist-bg-light')  // menu-or-popup
        .css({position:'absolute', zIndex:9999})
        .appendTo( this.document.find('body') )
        .hide();

        var that = this;

        //load template
        $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/search_quick.html?t="+(new Date().getTime()), function(){


            var search_quick_close = $( "<button>", {
                label: window.hWin.HR("close")
            })
            .appendTo( $dlg )
            .addClass('ui-heurist-btn-header1')
            .css({position:'absolute', zIndex:9999, 'right':4, top:4, width:16, height:16, 'font-size':'0.8em'})
            .button({icon: "ui-icon-triangle-1-n", showLabel:false});
            that._on( search_quick_close, {
                click: function(event){
                    //$(document).off('keypress');
                    $dlg.hide( "blind", {}, 500 );
                }
            });


            var dv = $dlg.find('#btns')
            dv.css({'display':'block !important'});

            var link = $('<a>',{
                text: 'Advanced Search Builder', href:'#'
            })
            .css('font-size','1em')
            .appendTo( dv );
            that._on( link, {  click: function(event){
                $dlg.hide( "blind", {}, 500 );
                //$(document).off('keypress');
                that._showAdvancedAssistant();
            } });


            var search_quick_go = $( "<button>")
            .appendTo( dv )
            .addClass('ui-heurist-btn-header1')
            //.css({position:'absolute', zIndex:9999, 'right':4, top:4, width:18, height:18})
            .css('float', 'right')
            .button({
                label: window.hWin.HR("Go"), showLabel:true
            });
            that._on( search_quick_go, {
                click: function(event){
                    $dlg.hide( "blind", {}, 500 );
                    that._doSearch();
                }
            });

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(window.hWin.HR($(this).html()));
            });

            var select_rectype = $("#sa_rectype");
            var select_fieldtype = $("#sa_fieldtype");
            var select_sortby = $("#sa_sortby");
            var select_terms = $("#sa_termvalue");
            var sortasc =  $('#sa_sortasc');
            $dlg.find("#fld_enum").hide();
            
            var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);

            select_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew(select_rectype.get(0), 
                        {useIcons: false, useCounts:true, useGroups:true, useIds: (exp_level<2), 
                            topOptions:window.hWin.HR('Any record type')});

            var allowed = Object.keys(window.hWin.HEURIST4.detailtypes.lookups);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("geo"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);

            function __startSearchOnEnterPress(e){
                    var code = (e.keyCode ? e.keyCode : e.which);
                    if (code == 13) {
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        $dlg.hide( "blind", {}, 500 );
                        that._doSearch();
                    }
            }
            
            that._on( $dlg.find('.text'), { keypress: __startSearchOnEnterPress});
            
            //change list of field types on rectype change
            that._on( select_rectype, {
                change: function (event){

                    var rectype = (event)?Number(event.target.value):0;
                    
                    var topOptions2 = 'Any field type';
                    var bottomOptions = null;

                    if(!(rectype>0)){
                        //topOptions2 = [{key:'',title:window.hWin.HR('Any field type')}];
                        bottomOptions = [{key:'latitude',title:window.hWin.HR('geo: Latitude')},
                                         {key:'longitude',title:window.hWin.HR('geo: Longitude')}]; 
                    }
                    var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                    
                    select_fieldtype = window.hWin.HEURIST4.ui.createRectypeDetailSelect($("#sa_fieldtype").get(0), 
                                rectype, allowed, topOptions2, 
                                {show_parent_rt:true, show_latlong:true, bottom_options:bottomOptions, 
                                    useIds: (exp_level<2), useHtmlSelect:false});

                    var topOptions = [{key:'t', title:window.hWin.HR("record title")},
                        {key:'id', title:window.hWin.HR("record id")},
                        {key:'rt', title:window.hWin.HR("record type")},
                        {key:'u', title:window.hWin.HR("record URL")},
                        {key:'m', title:window.hWin.HR("date modified")},
                        {key:'a', title:window.hWin.HR("date added")},
                        {key:'r', title:window.hWin.HR("personal rating")},
                        {key:'p', title:window.hWin.HR("popularity")}];

                    if(Number(rectype)>0){
                        topOptions.push({optgroup:'yes', title:window.hWin.HEURIST4.rectypes.names[rectype]+' '+window.hWin.HR('fields')});
                        /*
                        var grp = document.createElement("optgroup");
                        grp.label =  window.hWin.HEURIST4.rectypes.names[rectype]+' '+window.hWin.HR('fields');
                        select_sortby.get(0).appendChild(grp);
                        */
                    }
                    select_sortby = window.hWin.HEURIST4.ui.createRectypeDetailSelect($("#sa_sortby").get(0), rectype, allowed, topOptions,
                                {initial_indent:1, useHtmlSelect:false});
                                
                                
                    that._on( select_fieldtype, {
                        change: __onFieldTypeChange
                    });
                    that._on( select_sortby, {
                        change: function(event){ 
                            this.calcShowSimpleSearch(); 
                            search_quick_go.focus();
                        }
                    });
                                
                    $("#sa_fieldvalue").val("");
                    $("#sa_negate").prop("checked",'');
                    $("#sa_negate2").prop("checked",'');
                    
                    $dlg.find("#fld_contain").show();
                    $dlg.find("#fld_enum").hide();
                    $dlg.find("#fld_coord").hide();
                    this.calcShowSimpleSearch();
                    search_quick_go.focus();
                }
            });
            
            //change compare option according to selected field type
            // enum, geocoord, others
            function __onFieldTypeChange(event){

                    if(event.target.value=='longitude' || event.target.value=='latitude'){

                        $dlg.find("#fld_contain").hide();
                        $dlg.find("#fld_enum").hide();
                        $dlg.find("#fld_coord").show();
                        
                    }else{
                        var dtID = Number(event.target.value);
                        
                        $dlg.find("#fld_coord").hide();
                    
                        var detailtypes = window.hWin.HEURIST4.detailtypes.typedefs;
                        var detailType = '';

                        if(Number(dtID)>0){
                            detailType = detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']];
                        }
                        if(detailType=='enum'  || detailType=='relationtype'){
                            $dlg.find("#fld_contain").hide();
                            $dlg.find("#fld_enum").show();
                            //fill terms
                            var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                            disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];

                            var select_terms = $("#sa_termvalue");

                            window.hWin.HEURIST4.ui.createTermSelectExt2(select_terms.get(0),
                            {datatype:detailType, termIDTree:allTerms, headerTermIDsList:disabledTerms, defaultTermID:null,
                                useIds: true, 
                                topOptions:[{ key:'any', title:window.hWin.HR('<any>')},{ key:'blank', title:'  '}], //window.hWin.HR('<blank>')
                                needArray:false, useHtmlSelect:false});
                                                 
                            that._on( select_terms, { change: function(event){
                                    this.calcShowSimpleSearch();
                                }
                            } );
                                                                                                     
                        } else {
                            $dlg.find("#fld_contain").show();
                            $dlg.find("#fld_enum").hide();
                        }
                        
                    }

                    this.calcShowSimpleSearch();
                    search_quick_go.focus();
            }//__onFieldTypeChange
                
            that._on( select_fieldtype, {
                change: __onFieldTypeChange
            });
            that._on( select_terms, { change: function(event){
                    this.calcShowSimpleSearch();
                    search_quick_go.focus();
                }
            } );
            that._on( select_sortby, { change: function(event){
                    this.calcShowSimpleSearch();
                    search_quick_go.focus();
                }
            } );
            that._on( $("#sa_fieldvalue"), {
                keyup: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_negate"), {
                change: function(event){
                    this.calcShowSimpleSearch();
                    search_quick_go.focus();
                }
            });
            that._on( $("#sa_negate2"), {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_coord1"), {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_coord2"), {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            
            that._on( sortasc, {
                click: function(event){
                    //window.hWin.HEURIST4.util.stopEvent(event);
                    //sortasc.prop('checked', !sortasc.is(':checked'));
                    this.calcShowSimpleSearch();
                }
            });

            select_rectype.trigger('change');
            that.showSearchAssistant();
        });

    }

    // recalculate search query value
    ,calcShowSimpleSearch: function (e) {

        
        
        var q = this.search_assistant.find("#sa_rectype").val(); if(q) q = "t:"+q;
        var fld = this.search_assistant.find("#sa_fieldtype").val(); 
        var ctn = '';
        
        if(fld=='latitude' || fld=='longitude'){
            var coord1 = $("#sa_coord1").val();
            var coord2 = $("#sa_coord2").val();
            
            var morethan = !isNaN(parseFloat(coord1));
            var lessthan = !isNaN(parseFloat(coord2));
            
            if(morethan && lessthan){
                fld = fld+':'+coord1+'<>'+coord2;
            }else if(morethan){
                fld = fld+'>'+coord1;
            }else if(lessthan){
                fld = fld+'<'+coord2;
            }else{
                fld = '';
            }
        }else{
            
            var isEnum = this.search_assistant.find("#fld_enum").is(':visible');
            
            if(fld) fld = "f:"+fld+":";
            
            if(isEnum){
                var termid = this.search_assistant.find("#sa_termvalue").val();
                if(termid=='any' || termid=='blank'){
                    ctn = ''; 
                }else{
                    ctn = termid;
                }
                if(termid=='blank' || this.search_assistant.find("#sa_negate2").is(':checked')){
                    fld  = '-'+fld;
                }
                
            }else{
                ctn =  this.search_assistant.find("#sa_fieldvalue").val();
                if(this.search_assistant.find("#sa_negate").is(':checked')){
                    fld  = '-'+fld;
                }
            }
        }

        var asc = ($("#sa_sortasc").val()==1?"-":'') ; //($("#sa_sortasc:checked").length > 0 ? "" : "-");
        var srt = $("#sa_sortby").val();
        srt = (srt == "t" && asc == "" ? "" : ("sortby:" + asc + (isNaN(srt)?"":"f:") + srt));

        q = (q? (fld?q+" ": q ):"") + (fld?fld: (ctn?" all:":"")) + (ctn?(isNaN(Number(ctn))?'"'+ctn+'"':ctn):"") + (srt? " " + srt : "");
        if(!q){
            q = "sortby:t";
        }

        this.input_search.val(q);
        this.input_search.change();

        e = window.hWin.HEURIST4.util.stopEvent(e);
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS
          +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
          + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT
          + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
          + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);

        // remove generated elements
        //this.btn_search_allonly.remove();  // bookamrks search off
        this.btn_search_as_guest.remove(); // bookamrks search on
        this.btn_search_as_user.remove();  // bookamrks search on
        this.btn_search_domain.remove();
        this.search_assistant.remove();
        this.menu_search_domain.remove();
        this.input_search.remove();
        this.input_search_prompt.remove();
        if(this.select_rectype) this.select_rectype.remove();

        this.div_search_as_user.remove();
        this.div_search_as_guest.remove();

        if(this.div_paginator) this.div_paginator.remove();

        this.div_search.remove();
    }


    , _doLogin: function(){

        if(typeof doLogin !== "undefined" && $.isFunction(doLogin)){  // already loaded in index.php
            doLogin(this.options.isloginforced);
        }else{
            //var that = this;
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_login.js', this._doLogin );
        }


    }

    , _doRegister: function(){

        if(false && !$.isFunction(doLogin)){  // already loaded in index.php
            //var that = this;
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_login.js', this._doRegister );
        }else{
            doRegister();
        }

        /*
        if($.isFunction($('body').profile_edit)){

        if(!this.div_profile_edit || this.div_profile_edit.is(':empty') ){
        this.div_profile_edit = $('<div>').appendTo( this.element );
        }
        this.div_profile_edit.profile_edit({'ugr_ID': window.hWin.HAPI4.currentUser.ugr_ID});

        }else{
        var that = this;
        $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_edit.js', function() {
        if($.isFunction($('body').profile_edit)){
        that._doRegister();
        }else{
        window.hWin.HEURIST4.msg.showMsgErr('Widget profile edit not loaded!');
        }
        });
        }
        */

    }

});
