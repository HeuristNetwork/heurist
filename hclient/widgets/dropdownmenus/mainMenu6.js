/**
* mainMenu6.js : main menu for v6
* 
* It loads mainMenu6_xxx.html for every section
* They took icons, titles and rollovers from mainMenu.js widget. Namely from mainMenuXXX.html files wish describe dropdown menues
* Action handlers are in mainManu.js as well. See menuActionById
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


$.widget( "heurist.mainMenu6", {

    // default options
    options: {
    },
    
    sections: ['design','populate','explore','publish','admin'],
    
    menues:{}, //section menu - div with menu actions
    containers:{}, //operation containers (next to section menu)
    introductions:{}, //context help containers
    
    _myTimeoutId: 0,  //delay on collapse main menu (_expandMainMenuPanel/_collapseMainMenuPanel)

    _myTimeoutId2: 0, //delay on close explore popup menu
    _myTimeoutId3: 0, //delay on show explore popup menu
    
    _myTimeoutId5: 0, //delay on prevent expand main menu (after search)
    
    _delayOnCollapseMainMenu: 800,
    _delayOnCollapse_ExploreMenu: 600,
    
    _delayOnShow_ExploreMenu: 500, //5 500,
    _delayOnShow_AddRecordMenu: 500, //10 1000,
    
    _widthMenu: 170, //left menu
    
    _is_prevent_expand_mainmenu: false,
    _explorer_menu_locked: false,
    _active_section: null,
    _current_explore_action: null,
    
    divMainMenu: null,  //main div

    
    currentSearch: null,
    reset_svs_edit: true,
    
    svs_list: null,
    
    coverAll: null,
    
    menues_explore_popup: null,  //popup next to explore menu (filters)
    menues_explore_gap: null,
    search_faceted: null,
    edit_svs_dialog: null,

    _left_position: 91, //normal width for most languages, for German it is 115

    // the widget's constructor
    _create: function() {

        var that = this;
        
        this._left_position = ('de'== window.hWin.HAPI4.getLocale())?115:91;

        
        this.element.addClass('ui-menu6')
        .addClass('selectmenu-parent')
        .disableSelection();// prevent double click to select text

        this.coverAll = $('<div>').addClass('coverall-div-bare')
            .css({'background-color': '#000', opacity: '0.6',zIndex:102,  
            filter: 'progid:DXImageTransform.Microsoft.Alpha(opacity=60)'})
            .hide()
            .appendTo( this.element );
        //91 200    
        this.divMainMenu = $('<div>')
        .addClass('mainMenu6')
        .css({position:'absolute',width: (this._left_position+'px'),
                top:'2px',left:'0px',bottom:'4px', 
                cursor:'pointer','z-index':104})
        .appendTo( this.element );
        
        this.divMainMenu.load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu6.html',
            function(){ 
                
                window.hWin.HAPI4.HRA(that.divMainMenu);

                that.divMainMenu.find('.menu-text').hide();

                //init all menues
                $.each(that.sections, function(i, section){
                    that._loadSectionMenu(section);
                    that._initIntroductory(section);
                });
                
                //explore menu in main(left) menu  -  quicklinks
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks').find('li.menu-explore, div.menu-explore'),
                {
                    mouseenter: that._expandMainMenuPanel,
                    mouseleave: that._collapseMainMenuPanel,
                });
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks'),{
                    mouseleave: that._collapseMainMenuPanel,
                });
                
                /* for saved filters 
                that._on(that.element.find('span.section-head').parent(), {
                    mouseenter: that._expandMainMenuPanel,
                });
                */
                
                //other entries in main(left) menu
                /* 
                remove these remarks to enable temp appearing section menu on mouse over 
                without it they can be opened by click only
                
                that._on(that.divMainMenu.children(':not(.ui-heurist-explore)'), {
                    mouseover: that._mousein_SectionMenu,  
                    mouseleave: that._mouseout_SectionMenu,
                });
                */
                //exit form explore menu section
/*BBBB                
                that._on(that.element.find('.ui-menu6-section.ui-heurist-explore'), {
                    mouseleave: that._collapseMainMenuPanel
                });
*/

                that._on(that.divMainMenu.find('.ui-heurist-header'),{
                    click: that._openSectionMenu
                });
                
                if(window.hWin.HEURIST4.util.getUrlParameter('welcome', window.hWin.location.search)){
                    //open explore by default, or "design" if db is empty
                    that._active_section = 'explore';
                    that.switchContainer( 'design' );
                }else{
                    that.switchContainer( 'explore' );
                }

                //init menu items in ui-heurist-quicklinks
                that._createListOfGroups(); //add list of groups for saved filters
                
                that.divMainMenu.find('.menu-text').hide();
                
                //init ui-heurist-quicklinks menu items
                that._on(that.divMainMenu.find('.menu-explore'),{
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                        if($(e.target).parent('#filter_by_groups').length==0){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            
                            if (!this._isCurrentActionFilter()) { //close on mouse exit
                            
                                //this._resetCloseTimers();//reset
                                this._myTimeoutId2 = setTimeout(function(){
                                            that._closeExploreMenuPopup();
                                        },  this._delayOnCollapse_ExploreMenu); //600
                            }
                        }
                    }
                });
                that._on(that.divMainMenu.find('#filter_by_groups'),{ //, #filter_by_groups
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            //this._resetCloseTimers();//reset
                            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu); //600
                    }
                });
                
                //forcefully hide coverAll on click
                that._on(that.coverAll, {
                    click: function(){
                            that._closeExploreMenuPopup();
                            that._collapseMainMenuPanel(true);
                    }
                });
                
                that._on(window.hWin.document, { //that.element
                    click: function(e){
                        //get current filter dialog
                        var ele = $('.save-filter-dialog:visible')
                        if(ele.length>0){
                                if (ele.parents('.ui-menu')) return;
                            
                                var prnt = ele.parent();
                                if(prnt.hasClass('ui-dialog') || prnt.hasClass('ui-menu6-section')){
                                    ele = prnt;
                                }
                                var x = e.pageX;
                                var y = e.pageY;
                                var x1 = $(ele).offset().left;
                                var y1 = $(ele).offset().top;
                                
//console.log(x+'   '+x1);                                    
                                if(x>0 && y>0){
                                if(x<x1 || x>x1+$(ele).width() ||
                                   y<y1 || y>y1+$(ele).height())
                                {
//console.log('outside');                                    
                                   that._closeExploreMenuPopup();
                                }
                                }
                        }                        
                    }
                });
                    
                    
                
                
                //keep main menu open on document mouse leave
                //that._on($(document),{mouseleave: that._resetCloseTimers });
                
                
                var cms_record_id = window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search);
                if(cms_record_id>0){
                    var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
                    widget.mainMenu('menuActionById','menu-cms-edit',{record_id:cms_record_id});
                }else{
                    //action 
                    var cmd = window.hWin.HEURIST4.util.getUrlParameter('cmd', window.hWin.location.search);
                    if(cmd){
                        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
                        widget.mainMenu('menuActionById',cmd);
                    }
                }
                
/*                
                that._on(that.divMainMenu.find('.menu-explore[data-action-onclick="svsAdd"]'), 
                {click: function(e){
                    that.addSavedSearch();
                }});
*/                
        });
        
        //find all saved searches for current user
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  
            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    }
            });
        }
        
        //that.initHelpDiv();
        
        //fix bug for tinymce popups - it lost focus if it is called from dialog
        $(window.hWin.document).on('focusin', function(e) {
            if ($(e.target).closest(".tox-tinymce-aux").length) {
                e.stopImmediatePropagation();
            }
        });

        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS, 
            function(e, data) {
                
                if(e.type == window.hWin.HAPI4.Event.ON_CUSTOM_EVENT){
                    if(data && data.userWorkSetUpdated){
                            that._refreshSubsetSign();
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    
                    //not need to check realm since this widget the only per instance
                    //if(data && that.options.search_realm && that.options.search_realm!=data.search_realm) return;
                    if(data && (data.ispreview || data.increment || data.search_realm)) return;
                    
                    that.reset_svs_edit = true;
                    if(data && !data.reset){
                        //keep current search for "Save Filter"
                        that.currentSearch = window.hWin.HEURIST4.util.cloneJSON(data);
                        that._updateSaveFilterButton(1);

                        that.switchContainer('explore');
                        that._mouseout_SectionMenu();
                        that._collapseMainMenuPanel(true, 1000);
                        
                    }else if(data.reset){
                        that.currentSearch = null;
                        that._updateSaveFilterButton(0);
                    }
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    if(data && data.request && (data.request.ispreview || data.request.increment || data.search_realm)) return;
                    
                    //if(data && that.options.search_realm && that.options.search_realm!=data.search_realm) return;
                    that.coverAll.hide();
                    // window.hWin.HAPI4.currentRecordset is the same as data.recordset
                    if(data.recordset && data.recordset.length()>0){
                        that._updateSaveFilterButton(2);
                    }else{
                        that._updateSaveFilterButton(0);
                    } 
                    
                    that._refreshSubsetSign();                    
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE || e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){
                    if(data && data.origin=='recordAdd'){
                        that._updateDefaultAddRectype( data.preferences );
                    }else{
                        that._updateDefaultAddRectype();
                    }
                }else{  //ON_STRUCTURE_CHANGE
                    //if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE){}
                    //refresh list of rectypes after structure edit
                    that._updateDefaultAddRectype();
                    window.hWin.HEURIST4.browseRecordCache = {};
                    window.hWin.HEURIST4.browseRecordTargets = {};
                }
        });
        
    }, //end _create
    
    //
    //
    //
    _isCurrentActionFilter: function(){
            return (this._current_explore_action=='searchBuilder' ||
                    this._current_explore_action=='svsAdd' || 
                    this._current_explore_action=='svsAddFaceted' );
    },
    
    //
    // 0 - disabled
    // 1 - search in progress
    // 2 - bounce and ready to save
    //
    _updateSaveFilterButton: function( mode ){
        
        var btn = this.divMainMenu.find('.menu-explore[data-action-popup="svsAdd"]');
        
        if(mode==0){ //disabled
           
            
            //btn.hide();//
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');
            window.hWin.HEURIST4.util.setDisabled(btn, true);
            
        }else if(mode==1){ //search in progress
            
            //btn.show();
            btn.find('span.ui-icon')
                .removeClass('ui-icon-filter-plus')
                .addClass('ui-icon-loading-status-lines rotate');
        }else{
            
            window.hWin.HEURIST4.util.setDisabled(btn, false);
            //btn.show();
            
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');
          
            var that = this;       
            btn.effect( 'pulsate', null, 4000, function(){
                btn.css({'padding':'6px 2px 6px '+(that.divMainMenu.width()==that._widthMenu?16:30)+'px'});
                //btn.find('.section-head').css({'padding-left':(that.divMainMenu.width()==that._widthMenu?0:12)+'px'});
            } );
            
        }
        
        
        
    },
    
    //
    //  
    //
    _updateDefaultAddRectype: function( preferences ){
      
      var prefs = (preferences)?preferences:window.hWin.HAPI4.get_prefs('record-add-defaults');
      if($.isArray(prefs) && prefs.length>0){
            var rty_ID = prefs[0];
            //var ele = this.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]');
            
            var ele = this.element.find('li[data-action-popup="recordAdd"]');
            
            if(ele.length>0){

                if(rty_ID>0 && $Db.rty(rty_ID,'rty_Name')){
                    ele.find('.menu-text').css('margin-left',0).html(window.hWin.HR('add_new_record2')
                            +'&nbsp;&nbsp; [<i>'
                            +window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_ID,'rty_Name'))+'</i>]');
                    ele.attr('data-id', rty_ID);
                    ele.attr('title', 'New ' + window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_ID,'rty_Name')) + ' record');
                    this._off(ele, 'click');
                    this._on(ele, {click: function(e){
                        var ele = $(e.target).is('li')?$(e.target):$(e.target).parents('li');
                        var rty_ID = ele.attr('data-id');

                        this._collapseMainMenuPanel(true); 
                        setTimeout('window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:{RecTypeID:'+rty_ID+'}})',200);
                    }});
                }else{
                    ele.find('.menu-text').text('New record');
                    ele.attr('data-id','');
                    this._off(ele, 'click');
                }
            
            }
      }
      
      //show/hide bookmarks section in saved filters list
      var bm_on = (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1');
      var ele = this.divMainMenu.find('.menu-explore[data-action-popup="svs_list"][data-id="bookmark"]')
      if(bm_on) ele.show();
      else ele.hide();
      
    },
    
    _refresh: function(){
    },
    
    _destroy: function() {
        
        this.divMainMenu.remove();
        
        if(this.edit_svs_dialog) this.edit_svs_dialog.remove();
        if(this.search_faceted) this.search_faceted.remove();
        
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS);
    },
    
    // 
    // returns true if explore menu popup should remain open (even on mouse out)
    //
    _isExplorerMenu_locked: function(){
        
        //var isSvsEditVisible = ( this.edit_svs_dialog && this.edit_svs_dialog.isModified() );
        //isSvsEditVisible = false;
        //console.log('>>>'+isSvsEditVisible);        
        
        return (this._explorer_menu_locked    //isSvsEditVisible || 
                || this.element.find('.ui-selectmenu-open').length>0
                || $('.list_div').is(':visible')      //tag selector dropdown      
                || $('.ui-widget-overlay.ui-front').is(':visible')   //some modal dialog is open
                );
    },

    //
    // collapse main menu panel on explore mouseout
    //    
    _collapseMainMenuPanel: function(is_instant, is_forcefully) {

//console.log('_collapseMainMenuPanel');        
        var that = this;
        if(is_forcefully>0){
            this._is_prevent_expand_mainmenu = true;
            this._myTimeoutId5 = setTimeout(function() { that._is_prevent_expand_mainmenu = false },is_forcefully);
        }else if(this._isExplorerMenu_locked() ){
            return;  
        } 
        
        if(is_instant && this._myTimeoutId>0){
            clearTimeout(this._myTimeoutId);
        }
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;
        
        this._myTimeoutId = setTimeout(function() {
            that._myTimeoutId = 0;

            that.coverAll.hide();
            
            if(that.menues_explore_gap) that.menues_explore_gap.hide();
            that.divMainMenu.find('.menu-text').hide();
            that.divMainMenu.find('ul').css({'padding-right':'30px'});
            that.divMainMenu.find('li.menu-explore').css({padding:'6px 2px 6px 30px',background:'none'});
            that.divMainMenu.find('div.menu-explore').css({padding:'6px 2px 6px 0px',background:'none'});
            //that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css({padding:'0px 2px 6px 30px'});
            that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'center'});
            
            $.each(that.divMainMenu.find('.section-head'), function(i,ele){
                ele = $(ele);
                ele.css({'padding-left': ele.attr('data-pl')+'px'});
            });
            
            that.divMainMenu.find('#svs_list').hide();
            //that.divMainMenu.find('#filter_by_groups').show(); IJ 2020-11-23 always show in explore menu only

            if(that.divMainMenu.width()>that._left_position)
                that.divMainMenu.stop().effect('size',  { to: { width: that._left_position } }, 
                    is_instant===true?10:300, function(){ //91
                    that.divMainMenu.css({bottom:'4px',height:'auto'});
                    that._closeExploreMenuPopup();
                });

            if (that.menues[that._active_section]) 
            {
                that.menues[that._active_section].css({left:(that._left_position+5)});
            }   
            
            that._switch_SvsList( 0 );
                
        }, is_instant===true?10:this._delayOnCollapseMainMenu); //800
    },
    
    //
    // expand main menu panel on explore mouse in
    //
    _expandMainMenuPanel: function(e) {
//console.log(' _expandMainMenuPanel ' );
        if(this._is_prevent_expand_mainmenu) return; // || this._active_section=='explore'

        clearTimeout(this._myTimeoutId); //terminate collapse
        this._myTimeoutId = 0;
        if(this.divMainMenu.width()==this._widthMenu) return; //200 already expanded
        
        this.coverAll.show();
        
        var that = this;
        this._mouseout_SectionMenu();
        this.divMainMenu.stop().effect('size',  { to: { width: that._widthMenu } }, 300, //300
                function(){
                    that.divMainMenu.find('ul').css({'padding-right':'0px'});
                    that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'left'});
                    that.divMainMenu.find('.section-head').css({'padding-left':'12px'});
                    that.divMainMenu.find('.menu-text').css({'display':'inline-block'}); //show('fade',300);
                    that.divMainMenu.css({bottom:'4px',height:'auto'});
                    that.divMainMenu.find('.menu-explore').css({padding:'6px 2px 6px 16px'});
                    //that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css({padding:'0px 2px 6px 16px'});
                    //that.divMainMenu.css({'box-shadow':'rgba(0, 0, 0, 0.5) 5px 0px 0px'});
                    
                    //change parent for cont? 
                    that.divMainMenu.find('#filter_by_groups').hide();
                    that._switch_SvsList( 1 );
                    
                    if (!(that.containers[that._active_section] &&
                        that.containers[that._active_section].is(':visible'))) 
                    {
                        that.menues[that._active_section].css({left:that._widthMenu+5});
                    }   
                    
                });
    },
    
    // RENAME 
    // leave explore popup
    //    
    _mouseout_SectionMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;
        
        var that = this;
        
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;
        //that.divMainMenu.find('li.menu-explore > .menu-text').css('text-decoration', 'none');
        
        function __closeAllsectionMenu() {
            
            var section_name = that._getSectionName(e);
            if(that._active_section!=section_name)
            {
                $.each(that.sections, function(i, section){
                    if(that._active_section!=section){
                        that._closeSectionMenu(section); 
                    }
                });
                that._closeExploreMenuPopup();
            }
        }        
        
        if(e){
            this._resetCloseTimers();//reset
            if (!this._isCurrentActionFilter()){
                this._myTimeoutId2 = setTimeout(function(){
                                                that._closeExploreMenuPopup();
                                                that._collapseMainMenuPanel();                                        
                                        },  this._delayOnCollapse_ExploreMenu);
            }
        }else{
            __closeAllsectionMenu();
        }
    },
    
    //
    // prevent close section and main menu
    //
    _resetCloseTimers: function(){

        clearTimeout(this._myTimeoutId2); this._myTimeoutId2 = 0; //delay on close explore popup menu
        clearTimeout(this._myTimeoutId); this._myTimeoutId = 0; //delay on collapse main menu (_expandMainMenuPanel/_collapseMainMenuPanel)
    },
    //
    // show explore menu popup (show_ExploreMenu) next to mainMenu6_explore or mainMany quick links
    //
    _mousein_ExploreMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;
        this._explorer_menu_locked = false;
        
        //this._expandMainMenuPanel();

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
        
        this._resetCloseTimers();
        
        this.divMainMenu.find('li.menu-explore, div.menu-explore').css('background','none');
        
        var ele, hasAction = false;
        
        if($(e.target).attr('id')=='filter_by_groups'){
            hasAction = false;
        }else{

            ele = $(e.target).is('li, div.menu-explore')?$(e.target):$(e.target).parents('li');
            if(ele){
                if(ele.parents('.ui-heurist-quicklinks').length>0) ele.css('background','aliceblue');
                hasAction = ele.attr('data-action-popup');
                
                if(hasAction=='search_recent') {
                    hasAction = false;   
                    return;
                }
                
            }
        }

        if(e && $(e.target).parents('.ui-heurist-quicklinks').length==0)
        {
            this._collapseMainMenuPanel(true); //close instantly 
        }
        
        
        if(hasAction){
            this.show_ExploreMenu(e);    
        } else {
            var that = this;
            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu); //600
        }
                   
        
    },
        
    //
    // show popup extension of explore menu
    //        
    show_ExploreMenu: function(e, action_name, position) {
        
        var menu_item;
        
        if(!action_name){
            menu_item = $(e.target).is('li, div.menu-explore')?$(e.target):$(e.target).parents('li');
            action_name = menu_item.attr('data-action-popup');
        }
        
        if(this._current_explore_action==action_name) return;
        
        
        var that = this,
            expandRecordAddSetting = false,
            delay = this._delayOnShow_ExploreMenu; //500

        if(action_name=='recordAddSettings'){
            action_name = 'recordAdd';
            expandRecordAddSetting = true;
        }
            
        //menu section has several containers with particular widgets
        var cont = this.menues_explore_popup.find('#'+action_name);
 
        if(action_name == 'recordAdd'){
            if(!expandRecordAddSetting && menu_item && menu_item.attr('data-id')>0){
                delay = this._delayOnShow_AddRecordMenu;
            }
        }else{
            this.menues_explore_popup
                    .removeClass('ui-heurist-populate record-addition').addClass('ui-heurist-explore');
        }      
      
        var explore_top = '2px',
        explore_height = 'auto',
        explore_left = that.divMainMenu.width()+4; //204; this._widthMenu
        
        if(menu_item && menu_item.parents('.ui-heurist-quicklinks').length==0 && 
                (this._active_section=='explore' || this._active_section=='populate')){
            explore_left = that._left_position + 211;// 302;
        }else if(menu_item && menu_item.parents('.ui-heurist-quicklinks').length==1){
            explore_left = this._widthMenu+4;
        }else{
            explore_left = ((that.divMainMenu.width()>that._left_position)?this._widthMenu:that._left_position)+4; 
        }
        
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear previous delay before open
        
        //delay before open explore section menu
        this._myTimeoutId3 = setTimeout(function(){

            that._current_explore_action = action_name;

            if(action_name == 'svsAddFaceted' && that.containers['explore'].find('div#db_overview').length > 0){
                that.containers['explore'].find('div#db_overview').hide();
            }

            if(cont.length==0){
                //create new one
                cont = $('<div id="'+action_name+'" class="explore-widgets">').appendTo(that.menues_explore_popup);
            }else if( cont.is(':visible')){ // && action_name!='svs_list'
                //return;
            }

            //stop show animation and hide others
            that.menues_explore_popup.find('.explore-widgets').finish().hide();
            
            if(action_name!='svsAdd'){
                //attempt for non modal 
                that.closeSavedSearch();
            }
            if(action_name!='svsAddFaceted'){
                that.closeFacetedWizard();
            }

            if(action_name=='searchByEntity'){

                if(!cont.searchByEntity('instance'))
                    cont.searchByEntity({use_combined_select:true, 
                        mouseover: function(){that._resetCloseTimers()}, //NOT USED
                        onClose: function() { 
                                //start search on close
                                that.switchContainer('explore'); 
                        },
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();    
                                that._explorer_menu_locked = is_locked;     
                            }
                        }
                    });    

                that.menues_explore_popup.css({bottom:'4px',width:'220px','overflow-y':'auto','overflow-x':'hidden'});

            }
            else if(action_name=='searchBuilder'){
                
                if(!cont.searchBuilder('instance')){
                    //initialization
                    this.search_builder = cont.searchBuilder({
                        is_h6style: true,
                        onClose: function() { that._closeExploreMenuPopup(); },
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();    
                                if(is_locked=='delay'){
                                    that.coverAll.show();
                                    that._delayOnCollapse_ExploreMenu = 2000;        
                                }else{
                                    that._explorer_menu_locked = is_locked;     
                                }
                            }
                    }  });    
                    
                    cont.addClass('save-filter-dialog');
                }else{
                    //refresh rectype dropdown
                    cont.searchBuilder('refreshRectypeMenu');
                }
                
                explore_top = 0;
                explore_height = 450;

                if(position){
                    explore_top = position.top;
                    explore_left = position.left;
                }else{
                    var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                    if(widget){
                        explore_top = widget.position().top + 100;
                    }else{
                        explore_top = menu_item.offset().top; //if called from menu
                    }
                }
                if(that.element.innerHeight()>0 && explore_top+explore_height>that.element.innerHeight()){
                    explore_top = that.element.innerHeight() - explore_height;
                }
                
                that.menues_explore_popup.css({width:'850px', overflow:'hidden'});
                
            }
            else if(action_name=='search_filters' || action_name=='search_rules'){ //list of saved filters

                that._init_SvsList(cont, (action_name=='search_rules')?2:1);                
                
                if(position){
                    explore_top = position.top;
                    explore_left = position.left;
                }
                
                that.menues_explore_popup.css({bottom:'4px',width:'300px','overflow-y':'auto','overflow-x':'hidden'});
            }
            else if(action_name=='svsAdd'){
                that._closeExploreMenuPopup();
                /*
                var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                if(widget){
                        explore_top = widget.position().top + 100;
                        if(explore_top+600>that.element.innerHeight()){
                            explore_top = '2px';
                        }
                }else{
                        explore_top = 2;
                }*/
                
                if(position){
                    explore_top = position.top;
                    explore_left = position.left;
                }else{
                    explore_top = 0;
                }
                
                that.addSavedSearch( 'saved', false, explore_left, explore_top );
                return;
            }
            else if(action_name=='svsAddFaceted'){
                that._closeExploreMenuPopup();
                that.addSavedSearch( 'faceted', false, explore_left );
                return;
            }
            else if(action_name=='recordAdd'){

                that.menues_explore_popup
                    .css({bottom:'4px',width:'300px',overflow:'hidden'})
                    .removeClass('ui-heurist-explore').addClass('ui-heurist-populate record-addition');

                if(position){
                    expandRecordAddSetting = true;
                    explore_top = position.top;
                    explore_left = position.left;
                }
                 
                if(!cont.recordAdd('instance')){
                    cont.recordAdd({
                        is_h6style: true,
                        onClose: function() { 
                            that._closeExploreMenuPopup();
                        },
                        isExpanded: expandRecordAddSetting, //false - show list, true - show preferences dialog
                        mouseover: function() { that._resetCloseTimers()},
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();
                                that._explorer_menu_locked = is_locked; 
                            }
                    }  });  
                }else{
                    cont.recordAdd('doExpand', expandRecordAddSetting);                        
                }
                //always expanded if(expandRecordAddSetting) explore_height = 430;

            }//endif
         
            if(explore_left+that.menues_explore_popup.outerWidth() > that.element.innerWidth()){
                explore_left = that.element.innerWidth() - that.menues_explore_popup.outerWidth();
            }

            if(explore_top<0) explore_top = 0;            
            
            that.menues_explore_popup.css({left:explore_left, top:explore_top, height:explore_height});
            
            //show menu section
            that.menues_explore_popup.css({'z-index':103}).show(); 
            
            //explore-widgets - show current widget in menu section
            //cont.show('fade',{},delay+200); 
            
            cont.fadeIn(delay+200, function(){
                var action_name = $(this).attr('id');
                that.menues_explore_popup.find('.explore-widgets[id!="'+action_name+'"]').hide();
                
                if(action_name=='searchByEntity'){
                    //trigger refresh  myOnShowEvent
                    $(this).searchByEntity('refreshOnShow');
                }
            });

        }, delay);
        
    },    

    //
    // List user's favourite filters
    //
    populateFavouriteFilters: function(favourite_filters){

        var that = this;

        if(!(this.menues && this.menues.explore)){
            setTimeout(function(){ that.populateFavouriteFilters(favourite_filters); }, 1000);
            return;
        }
        
        var $favourite_container = this.menues.explore.find('ul.favourite-filters');

        if(!favourite_filters){
            favourite_filters = window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);
        }

        if($favourite_container.find('li').length > 0){ // clear list
            $favourite_container.find('li').remove();
            this.menues.explore.find('.favour-help').show();
        }

        if($favourite_container.length > 0 && favourite_filters[0] != ''){

            if(!this.svs_list){
                this.getSvsList();
            }

            var cont_height = this.menues.explore.height() - $favourite_container.position().top - 50;
            $favourite_container.css('height', cont_height + 'px');

            for(var filter of favourite_filters){

                var $remove_btn = $('<span>')
                                 .addClass('smallbutton ui-icon ui-icon-redo')
                                 .attr('title', 'Remove filter from favourites')
                                 .attr('data-fid', filter[0])
                                 .css({
                                    'display': 'none',
                                    'float': 'right',
                                    'margin-top': '1px',
                                    'color': 'black'
                                 });

                var $txt = $('<span>')
                            .addClass('truncate')
                            .css({
                                'font-size': '13px',
                                'display': 'inline-block',
                                'max-width': '85%',
                                'vertical-align': 'text-top'
                            })
                            .attr('title', filter[1])
                            .text(filter[1]);


                $('<li>')
                .addClass('fancytree-node')
                .attr('data-fid', filter[0])
                .css({
                    'padding': '6px',
                    'cursor': 'pointer'
                })
                .append($txt) // attach text
                .append($remove_btn) // attach remove btn
                .appendTo($favourite_container);
            }

            if($favourite_container.find('li').length > 0){

                var block_filter = false;

                this._on($favourite_container.find('li'), {
                    click: function(event){

                        if(block_filter) { return; } // user current re-ordering favourite filters

                        var $ele = $(event.target);
                        if($ele.is('span') && !$ele.hasClass('smallbutton')){ // filter text clicked
                            $ele = $ele.parents('li.fancytree-node');
                        }
                        var id = $ele.attr('data-fid');

                        if($ele.is('li.fancytree-node')){
                            this.svs_list.svs_list('doSearchByID', id, $ele.text()); // perform filter
                        }else if($ele.is('span')){

                            // remove from list and preferences
                            $ele.parent().remove(); // remove from list

                            if(favourite_filters.length > 1){
                                // remove from prefs
                                var idx = favourite_filters.findIndex(filter => filter[0] == id);
                                favourite_filters.splice(idx, 1);
                            }else{
                                favourite_filters = [''];
                                this.menues.explore.find('.favour-help').show();
                            }

                            window.hWin.HAPI4.save_pref('favourite_filters', favourite_filters); // save prefs
                        }
                    },
                    mouseover: function(event){

                        if($(event.target).hasClass('smallbutton')){
                            $(event.target).show();
                        }else if($(event.target).hasClass('truncate')){
                            $(event.target).parent().find('span.smallbutton').show();
                        }else{
                            $(event.target).find('span.smallbutton').show();
                        }
                    },
                    mouseout: function(event){
                        $favourite_container.find('span.smallbutton').hide();
                    }
                });

                $favourite_container.sortable({
                    start: function(event, ui){
                        block_filter = true; // disable filtering
                    },
                    stop: function(event, ui){
                        
                        var new_order = [];

                        $favourite_container.find('li').each(function(idx, ele){
                            var $ele = $(ele);
                            new_order.push([$ele.attr('data-fid'), $ele.find('span.truncate').text()]);
                        });

                        window.hWin.HAPI4.save_pref('favourite_filters', new_order); // save prefs

                        setTimeout(function(){ block_filter = false; }, 1000); // re-enable filtering
                    }
                });

                var cont_height = this.menues.explore.height() - $favourite_container.position().top - 50;
                $favourite_container.css('height', cont_height + 'px');

                this.menues.explore.find('.favour-help').hide();
            }else{
                var cont_height = this.menues.explore.height() - $favourite_container.position().top - 100;
                $favourite_container.css('height', cont_height + 'px');
            }
        }
    },

    //
    // mode - filter mode - 0 all , 1 - filters only, 2 rules only
    //
    _init_SvsList: function(cont, mode){  //, group_ID
        
        if(!cont.svs_list('instance')){
            var that = this;
            
            cont.svs_list({
                is_h6style: true,
                hide_header: false,
                container_width: 300,
                filter_by_type: mode,
                onClose: function(noptions) { 
                    //!!! that.switchContainer('explore'); 

                    if(noptions==null){
                        //close faceted search
                        that._onCloseSearchFaceted();
                    }else{
                        noptions.onclose = function(){ that._onCloseSearchFaceted(); };
                        noptions.is_h6style = true;
                        noptions.maximize = true;

                        //open faceted search
                        that.search_faceted.show();
//BBB                                that.containers['explore'].css({left:'332px'}); //move to the right
//BBB                                that.containers['explore'].layout().resizeAll();  //update layout

                        if(that.search_faceted.search_faceted('instance')){ 
                            that.search_faceted.search_faceted('option', noptions ); //assign new parameters
                        }else{
                            //not created yet
                            that.search_faceted.search_faceted( noptions );
                        }
                        
                        that._closeExploreMenuPopup();
                        that._collapseMainMenuPanel(true);

                    } 
                },
                //show all groups! allowed_UGrpID:group_ID,
                menu_locked: function(is_locked, is_mouseleave){ 
                    if(!is_mouseleave){
                        that._resetCloseTimers();
                        that._explorer_menu_locked = is_locked; 
                    }
                },
                handle_favourites: function(filter_id, filter_name, is_drop=false){

                    var hasChanged = false;

                    var cur_favs = window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);
                    if(cur_favs[0] == '' || cur_favs.findIndex(filter => filter[0] == filter_id) == -1){ // add new
                        if(cur_favs[0] == ''){
                            cur_favs[0] = [filter_id, filter_name];
                        }else{
                            cur_favs.push([filter_id, filter_name]);
                        }

                        hasChanged = true;
                    }else if(!is_drop){ // remove existing

                        // remove from prefs
                        var idx = cur_favs.findIndex(filter => filter[0] == filter_id);
                        var removed = cur_favs.splice(idx, 1); console.log(cur_favs);

                        if(cur_favs.length == 0){
                            cur_favs = [''];
                        }

                        hasChanged = true;
                    }

                    if(hasChanged){ // save and re-make list
                        window.hWin.HAPI4.save_pref('favourite_filters', cur_favs);
                        that.populateFavouriteFilters(cur_favs);
                    }
                }
                //mouseover: function() { that._resetCloseTimers()},
            });
            
            this._on(cont,{mouseenter: this._resetCloseTimers}); 
            
        }else{
            cont.svs_list('option', 'filter_by_type', mode);
        }

        this.svs_list = cont;

        return cont;
    },
    
    //
    // mode = 0 - in menues['explore'],  1 in ui-heurist-quicklinks
    //    
    _switch_SvsList: function( mode ){
        
        return;//2020-12-15
        
        if(!this.svs_list && this.menues['explore'].find('#svs_list').length>0){
            this.svs_list = this._init_SvsList(this.menues['explore'].find('#svs_list'),1);
        }
        if(!this.svs_list){
            return;
        }
        
        mode = 0; //IJ 2020-11-13 always show in explore menu only
        
        if(mode==1){
            //show in leftside main menu
            if(!this.svs_list.parent().hasClass('ui-heurist-quicklinks')){
                    //show in left main menu
                    this.svs_list.detach().appendTo(this.divMainMenu.find('.ui-heurist-quicklinks'));
                    this.svs_list.css({'top':215, 'border-top':'none'}); //, 'font-size':'0.8em'});
                    this.svs_list.svs_list('option','container_width',170);
                    this.svs_list.svs_list('option','hide_header', true);
                    this._on(this.svs_list,{mouseenter: this._resetCloseTimers});//_expandMainMenuPanel});
            }
            
        }else{
            //show in menu section
            if(!this.svs_list.parent().hasClass('ui-menu6-section')){
                
                this.svs_list.detach().appendTo(this.menues['explore']);
                this.svs_list.css({'top':255, 'border-top':'3px #305586 solid'}); //, 'font-size':'1em'}).show();
                this.svs_list.svs_list('option','container_width',200);
                this.svs_list.svs_list('option','hide_header', true);
                this._off(this.svs_list,'mouseenter');
            }
        }
        
        this.svs_list.show();
    },

    //
    //
    //    
    getSvsList: function(){
        
        var cont = this.menues_explore_popup.find('#search_filters');
        
        if(cont.length==0){
            //create new one
            cont = $('<div id="search_filters" class="explore-widgets">').appendTo(this.menues_explore_popup);
        }
        return this._init_SvsList(cont, 1);
     
    },

    //
    //
    //
    _onCloseSearchFaceted: function(){
        if(this.search_faceted && this.search_faceted.is(':visible')){
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
                {reset:true, search_realm:this.options.search_realm} ]);  //global app event to clear views
            this.search_faceted.hide();
//BBB            this.containers['explore'].css({left:'206px'});
//BBB            this.containers['explore'].layout().resizeAll();
        }
    },
    
    //
    //
    //
    _closeExploreMenuPopup: function(){

        if(this.menues_explore_popup){
            
            this.menues_explore_popup.hide();  
                
            //attempt for non modal 
//23-12            this.divMainMenu.find('li.menu-explore > .menu-text').css('text-decoration', 'none');
//23-12            this.menues['explore'].find('li.menu-explore > .menu-text').css('text-decoration', 'none');
            
            this._current_explore_action = null;
            this.closeSavedSearch();
            this.closeFacetedWizard();
            
        }
        // restore default values
        this._explorer_menu_locked = false;
        this._delayOnCollapse_ExploreMenu = 600;
        this.coverAll.hide();

    },
    
    //
    //
    //
    _closeSectionMenu: function( section ){
        
        if(this.menues[section]) {
            this.menues[section].css({'z-index':0}).hide();
        }
        if(this.menues_explore_gap){
            this.menues_explore_gap.hide();    
        }
        //this.menues[section].css({'z-index':2,left:'200px'}).show(); 
        if(section=='explore'){
            this._closeExploreMenuPopup();
        }
    },
    
    //
    //
    //
    _getSectionName: function(e){
        var that = this;
        var section_name = null;
        if(e){
            var ele;
            if($(e.target).hasClass('ui-heurist-header') || $(e.target).hasClass('ui-heurist-quicklinks')){
                ele = $(e.target);
            }else{
                ele = $(e.target).parents('.ui-heurist-quicklinks');
                if(ele.length==0){
                    ele = $(e.target).parents('.ui-heurist-header');
                }
            }
            if(ele.length>0){
            $.each(this.sections, function(i, section){
                if(ele.hasClass('ui-heurist-'+section)){
                    section_name = section;
                    return false; //exit loop
                }
            });
            }
        }
        
        return section_name;
    },
    
    
    //
    // opens section menu permanently and switches container 
    //
    _openSectionMenu: function(e){
        
        var section = this._getSectionName(e);
        if(section=='explore' && this._active_section==section){
            this._onCloseSearchFaceted();
        }
        this.switchContainer( section );
        
        this._collapseMainMenuPanel(true, 200);
        
    },
    
    //
    // loads content of section from mainMenu6_section.html
    //
    _loadSectionMenu: function( section ){
        
        this.menues[section] = $('<div>')
            .addClass('ui-menu6-section ui-heurist-'+section)
            .css({width:'200px'})   //,'border-left':'4px solid darkgray'})
            .appendTo( this.element );
            
        this.containers[section] = $('<div>')
            .addClass('ui-menu6-widgets ui-menu6-container ui-heurist-'+section) //ui-menu6-widgets to distinguish with introduction
            .appendTo( this.element );
            
        this.containers[section].css('left',(this._left_position+211)+'px');
            
        if(section=='explore'){

            this.menues_explore_popup = $('<div>')
                    .addClass('ui-menu6-section ui-heurist-explore')
                    .css({width:'200px'})
                    .hide()
                    .appendTo( this.element );
                    
            this._on(this.menues_explore_popup,{
                mouseover: function(e){
                    that._resetCloseTimers();
                    //clearTimeout(this._myTimeoutId2); this._myTimeoutId2 = 0; //prevent collapse of section menu popup
                },
                mouseleave: function(e){
                    if(this._active_section=='explore' || this._active_section=='populate'){
                        this._mouseout_SectionMenu(e);       
                    }else{
                        this._collapseMainMenuPanel()    
                    }
                }
            });
                    
                    
                    
            
            this.menues_explore_gap = $('<div>')
                    .css({'width':'4px', position:'absolute', opacity: '0.8', 'z-index':103, left:this._widthMenu+'px'}) //200
                    //.addClass('ui-heurist-explore-fade')
                    .hide()
                    .appendTo( this.element );
                    
            this.search_faceted = $('<div>')
                    .addClass('ui-menu6-container ui-heurist-explore')
                    .css({left:(this._left_position+5)+'px', width:'200px', 'z-index':102})
                    .hide()
                    .appendTo( this.element );
            
            this.containers[section]
                //.css({left:'206px'})
                .show();
                
            this.menues[section].show();

                
            //init explore container    
            window.hWin.HAPI4.LayoutMgr.appInitAll('SearchAnalyze3', this.containers[section] );
        }
        
        var that = this;
        this.menues[section].load(
                window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu6_'+section+'.html',
                function(){ 
                    
                    window.hWin.HAPI4.HRA( that.menues[section] );
                    
                    if(section=='explore'){
                        that._initSectionMenuExplore();                        
                    }else{
                        that._initSectionMenu(section);    
                    }
                    
                });
        
        
    },
    
    //
    // special behaviour form mainMenu6_explore
    //
    _initSectionMenuExplore: function(){
        
            var that = this;
            this._on(this.menues['explore'].find('.menu-explore'),{
                mouseenter: this._mousein_ExploreMenu,
                mouseleave: function(e){
                        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                        //this._resetCloseTimers();//reset
                        this._myTimeoutId2 = setTimeout(function(){
                                    that._closeExploreMenuPopup();
                                },  this._delayOnCollapse_ExploreMenu); //600
                }
            });
            this._updateDefaultAddRectype();

            var exp_img = $(this.element.find('img[data-src="gs_explore_cb.png"]')[0]);
            exp_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + exp_img.attr('data-src'));

            this._on(this.element.find('li[data-action-popup="search_recent"]'),{
                click: function(){
                    var q = '?w=a&q=sortby:-m';
                    var qname = 'All records';
                    if(!$(event.target).attr('data-search-all')){
                         q = q + ' after:"1 week ago"';
                         qname = 'Recent changes';
                    }
                    var request = window.hWin.HEURIST4.util.parseHeuristQuery(q); 
                    request.qname = qname;
                    window.hWin.HAPI4.RecordSearch.doSearch( this, request );
                }
            });

           
            //init 
            this._switch_SvsList( 0 );
            //this.svs_list = this._init_SvsList(this.menues['explore'].find('#svs_list'));  
            this._initSectionMenu( 'explore' );
    },

    //
    // finds menu actions and assigns icon and title 
    // source of all actions in mainMenu widget 
    // see mainMenuXXX.html snippets for descriptions of actions
    //
    _initSectionMenu: function( section ){
        
        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
        if(!widget) return;

        $.each(this.menues[section].find('li[data-action]'),
            function(i, item){
                item = $(item);
                var action_id = item.attr('data-action');
                if( action_id ){
                    item.addClass('fancytree-node');
                    var link = widget.mainMenu('menuGetActionLink', action_id);    
                    
                    if(link!=null){
                    
                        $('<span class="ui-icon '+link.attr('data-icon')+'"/>'
                         +'<span class="menu-text truncate" style="max-width: 109px;">'+window.hWin.HR( action_id )+'</span>')
                        .appendTo(item);
                        
                        if(action_id=='menu-import-get-template' || action_id=='menu-import-csv-fieldtypes' || action_id=='menu-import-csv-rectypes'){
                            item.css({'font-size':'10px', padding:'0 0 0 8px','margin-top':'-2px'})
                        }else{
                            item.css({'font-size':'smaller', padding:'6px'})    
                        }
                        
                        item.attr('title',window.hWin.HR(action_id+'_hint')); //link.attr('title')
                        
                    }
                }
            });
            
        $(this.menues[section].children()[1]).find('.ui-icon')
                    .addClass('ui-heurist-title')  //apply color
                    .css({cursor:'pointer'});
                    
        this.menues[section].find('.ui-icon-circle-b-help').css({cursor:'pointer'});
        this._on(this.menues[section].find('.ui-icon-circle-b-help'),
            {click: this._loadIntroductoryGuide});
        
        //execute menu on click           
        this._on(this.menues[section].find('li[data-action]'),{click:function(e){
            var li = $(e.target);
            if(!li.is('li')) li = li.parents('li');
            
            if(li.attr('data-action')=='menu-admin-server'){
                this.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');
            }
            
            
            if(section=='design'){    
                    $(this.containers[section])
                        .css({left:(this._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'});
            }
            
            //this.switchContainer(section, true);
            widget.mainMenu('menuActionById', li.attr('data-action')); 
            //{container:this.containers[section]}
        }});
        
        if(section=='publish'){
            this._on(this.menues[section].find('li[data-cms-action]'),{click:function(e){
                var li = $(e.target);
                if(!li.is('li')) li = li.parents('li');
                
                this.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');
                
                //this.switchContainer('publish', true);
                
                var btn = this.containers['publish'].find('#'
                                                    +li.attr('data-cms-action'));
                if(btn.length>0) btn.click();
                
            }});
        }else  if (section=='populate'){ //DEBUG - open record types 
        
                this._updateDefaultAddRectype();
                
                //special behavior for 
                var ele = this.menues['populate'].find('li[data-action-popup="recordAdd"]');
                var that = this;
                this._on(ele,{
                     mouseenter: function(e){

                        this._resetCloseTimers();
                        this.show_ExploreMenu(e);
                     },
                     mouseleave: function(e){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            this._resetCloseTimers();//reset
                            this._myTimeoutId2 = setTimeout(function(){  //was 6

                                        that._closeExploreMenuPopup();
                                        //that.menues['explore'].hide();
                                        //that.menues_explore_gap.hide();
                                        //that._closeSectionMenu('explore');
                                    },  this._delayOnCollapse_ExploreMenu); 
                     }
                });         
                
                
        
        }else  if (section=='design'){ //DEBUG - open record types 
        
        
            /* DEBUG    
                this._active_section = 'explore';
                this.switchContainer('design', true);
                this.menues['design'].find('li[data-action="menu-structure-rectypes"]').click();
            */
        }
        
        /*
        if(section=='publish'){
            var that = this;
            var menu_container = this.menues[section].find('.heurist-export-menu6');
            $.getScript( window.hWin.HAPI4.baseURL+'hclient/framecontent/exportMenu.js',
            function() {
                var exportMenu = new hexportMenu( menu_container );    
                exportMenu.setDialogOptions({
                        is_h6style: true,
                        isdialog: false, 
                        need_reload: true,
                        onInitFinished: function(){
                            that.switchContainer('publish');
                        },
                        onClose: function() { that.containers['publish'].hide() },
                        container: that.containers['publish']});
            });            
        }*/

        if(this.menues[section].find('ul.accordionContainer').length > 0){ // check if current menu has any accordion groupings

            this.menues[section].find('ul.accordionContainer').accordion({
                header: '.accordionHeader',
                collapsible: true,
                active: false,
                beforeActivate: function(event, ui){

                    $(ui.newPanel).css({ // remove special styling of accordion content
                        'background': 'none',
                        'border': 'none',
                        'height': '',
                        'font-size': '14px'
                    });
                }
            });

            this.menues[section].find('li.accordionHeader').css('padding', '0px 0px 8px');
        }
    },
    
    //
    // switch section on section menu click
    //
    switchContainer: function( section, force_show ){

        //hide all intros
        $.each(this.introductions, function(i, item){$(item).hide();});
        
        var that = this;
        if(that._active_section!=section ){

            that._closeExploreMenuPopup();
            that._onCloseSearchFaceted();
            
            if(that._active_section && that.menues[that._active_section])
            {
                that.containers[that._active_section].hide();
                that.menues[that._active_section].hide();
                that.element.removeClass('ui-heurist-'+that._active_section+'-fade');
                that.menues_explore_gap.removeClass('ui-heurist-'+that._active_section+'-fade');
            }
            that._current_explore_action = null;
            that._active_section = section;

            //show menu and section 
            if(that.menues[section]){
                that.menues[section].css('z-index',101).show();    
            }

            if(force_show || (that.containers[section] && !that.containers[section].is(':empty'))){
                that.containers[section].show();    
            }else if(that.introductions && that.introductions[section]){
                that.introductions[section].css('left', (that._left_position+211)+'px').show();    
            }
            
            //change main background
            this.element.addClass('ui-heurist-'+section+'-fade');    
            if(this.menues_explore_gap){
                this.menues_explore_gap.addClass('ui-heurist-'+section+'-fade');    
            }
            
        }else if(force_show || section=='explore'){
            that.containers[section].show();    
        }else if(typeof editCMS_instance !=='undefined' && section=='publish'){
            editCMS_instance.closeCMS();
        }else{
            return;
        }
        
        if(section == 'explore' && that.containers[section]) {
            if(that.containers[section].hasClass('ui-layout-container'))
                 that.containers[section].layout().resizeAll();
            that._switch_SvsList( 0 );

            if(that.containers['explore'].find('div#db_overview').is(':visible')){
                that.containers['explore'].find('div#db_overview').hide();
            }
        }

    },

    //-----------------------------------------------------------------
    //
    // SAVED FILTERS
    //
    _createListOfGroups: function(){
        
        //IJ 2020-11-13 always show in explore menu only
        return;
        
        var bm_on = (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1');
        
        
        var s = '<li class="menu-explore" data-id="bookmark" style="display:'+(bm_on?'block':'none')+'">'  //data-action="svs_list" 
            +'<span class="ui-icon ui-icon-user"/><span class="menu-text">'+window.hWin.HR('My Bookmarks')
            +'</span></li>'
            +'<li class="menu-explore" data-id="all">'  //data-action="svs_list" 
            +'<span class="ui-icon ui-icon-user"/><span class="menu-text">'+window.hWin.HR('My Searches')
            +'</span></li>'            
        
        var groups = window.hWin.HAPI4.currentUser.ugr_Groups;
        for (var groupID in groups)
        {
            if(groupID>0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                var sicon = 'users';
                var struncate =  ' truncate" style="max-width: 109px;';
                if(groupID==1){
                    sicon = 'database';
                    struncate = '';
                }else if(groupID==5){
                    sicon = 'globe';
                }
                
                s = s + '<li class="menu-explore" data-id="'+groupID+'">' // data-action="svs_list" 
                    +'<span class="ui-icon ui-icon-'+sicon+'"/><span class="menu-text'+struncate+'">'
                    +name
                    +'</span></li>';
            }
        }
        
        var cont = this.divMainMenu.find('#filter_by_groups');
        cont.children().remove(); //.not(':first')
        $(s).appendTo(cont);

        /*        
        window.hWin.HEURIST4.filters.getFiltersTree( function(data){
            window.hWin.HAPI4.currentUser.ugr_SvsTreeData = data; 
        });
        */
        
    },

    //
    //
    //
    closeSavedSearch: function(){
        if(this.edit_svs_dialog){
            this.edit_svs_dialog.closeEditDialog();
        }
    },
    closeFacetedWizard: function(){
        var faceted_search_wiz = $('#heurist-search-faceted-dialog');
        if(faceted_search_wiz && faceted_search_wiz.length>0){
            faceted_search_wiz.dialog('close');
        }
    },
        
    //
    // define new saved filter/search
    // mode - saved or faceted
    //
    addSavedSearch: function( mode, is_modal, left_position, top_position ){

        if(this.edit_svs_dialog==null){
            this.edit_svs_dialog = new hSvsEdit();    
        }
        
        if(!(left_position>0)){
            left_position = ((that.divMainMenu.width()>this._left_position)?this._widthMenu:this._left_position) + 4; 
            if(this._active_section=='explore'){
                left_position = this._left_position + 211; //302;
            }
        }
        if(!(top_position>0)){
            top_position = 40; //140;
        }

        is_modal = (is_modal!==false);
        
        var that = this;
  
/*        
        //find all saved searches for current user
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  
            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    }
            });
        }
*/        
        //for faceted wizard and save filter delay is increased to 2sec
        this._delayOnCollapse_ExploreMenu = 2000;

        var $dlg = this.edit_svs_dialog.showSavedFilterEditDialog( mode, null, null, this.currentSearch , false, 
            { my: 'left+'+left_position+' top+'+top_position, at: 'left top', of:this.divMainMenu},
            function(){  //after save - trigger refresh of saved filter tree
                
                window.hWin.HAPI4.currentUser.usr_SavedSearch = null;
                //!!! window.hWin.HAPI4.currentUser.ugr_SvsTreeData = null;
                if(that.svs_list){ 
                    that.svs_list.svs_list('option','hide_header',true);//to trigger refresh
                }
            }, 
            is_modal, 
            true, //is_h6style                                                                                                         
            function(is_locked, is_mouseleave){  //menu_locked
                if(is_mouseleave){
                    that._resetCloseTimers();
                    return; //prevent close on mouse out
                    
                    if(that._explorer_menu_locked) return;
                    that._myTimeoutId2 = setTimeout(function(){ //delay on close
                            that._closeExploreMenuPopup();
                            that._collapseMainMenuPanel();
                    }, that._delayOnCollapse_ExploreMenu);
                }else if(is_locked=='close'){
                    that.coverAll.hide();                 
                    //that._resetCloseTimers();
                    //that._closeExploreMenuPopup();
                    //that._collapseMainMenuPanel();
                }else{
                    that._resetCloseTimers();    
                    
                    if(is_locked=='delay'){
                        that.coverAll.show();
                        that._delayOnCollapse_ExploreMenu = 2000;        
                    }else{
                        that._explorer_menu_locked = is_locked;     
                    }
                }
            },
            that.reset_svs_edit
        );  
        
        /*
        setTimeout(function(){
            $dlg.parent('.ui-dialog').css({top:that.divMainMenu.offset().top, left:(that._widthMenu+4)});    
        },300);
        */
        
        that.reset_svs_edit = false;
    },
    
    //
    //
    //
    initHelpDiv: function(){
        
        this.helper_div = $('<div>').addClass('ui-helper-popup').hide().appendTo(this.element);
        
        var _innerTitle = $('<div>').addClass('ui-heurist-header').appendTo(this.helper_div);  
                                
        $('<span>').appendTo(_innerTitle);
        var btn = $('<button>')
                    .button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                    .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                    .appendTo(_innerTitle);
                    
                    
        this._on( btn, {click : function(){
                    this.helper_div.hide();
        }});
                                
        $('<div>').css({top:38}).addClass('ent_wrapper').appendTo(this.helper_div);  
        //this.containers[this._active_section]
    },
    
    //
    //
    //
    _refreshSubsetSign: function(){
        
            var container = this.menues['explore'].find('li[data-action="menu-subset-set"]');
            //container.removeClass('fancytree-node');
            var ele = container.find('span.subset-info');
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                if(ele.length==0){
                    ele = $('<span class="subset-info"><span '
+'style="display:inline-block;color:red;font-size:smaller;padding-left:22px"></span>' //font-style:italic ;color:lightgray
+'<span class="ui-icon ui-icon-arrowrefresh-1-w clear_subset" style="font-size:0.7em;color:black;" '
+'title="'+window.hWin.HR('Click to revert to whole database')+'">'+
'</span></span>')
                        .appendTo(container);
                        
                    this._on(ele.find('span.clear_subset').css('cursor','pointer'),
                        {click: function(e){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                            if(widget){
                                widget.resultList('callResultListMenu', 'menu-subset-clear'); //call method
                            }
                        }});
                
                }
                ele.find('span:first').html(window.hWin.HR('Current subset')
                        +' n&nbsp;&nbsp;=&nbsp;&nbsp;'+window.hWin.HAPI4.sysinfo.db_workset_count);
                ele.show();
                
            }else if(ele.length>0){
                ele.hide();
            }
    },

    //
    //
    //
    _initIntroductory: function( section ){
        
        if(!this.introductions[section]){
            
            var sname;
            if(section=='populate'){
                sname = 'Populate';
            }else{
                sname = section[0].toUpperCase()+section.substr(1);
            }
            
            this.introductions[section] = $('<div><div class="gs-box" style="margin:10px;max-width:500px;height:100px;cursor:pointer">'
        +'<div style="display:inline-block"><img width="110" height="60" alt="" src="'
            +window.hWin.HAPI4.baseURL+'hclient/assets/v6/gs_'+section+'.png"></div>'
            
        +'<span class="ui-heurist-title header" id="menu-guide" style="display: inline-block; font-weight: normal;padding-left:20px;cursor: pointer">'
            +'<span class="ui-icon ui-icon-help"/>&nbsp;Menu guide</span>'            
            
        +'<span class="ui-heurist-title header" id="start-hints" style="display: inline-block; font-weight: normal;padding-left:20px;cursor: pointer">'
            +'<span class="ui-icon ui-icon-help"/>&nbsp;Startup hints</span>' 			
			
        +'<div class="ui-heurist-title" style="font-size: large !important;width: 80px;padding-top: 6px;">'+sname+'</div>'
        +'</div></div>')            
                .addClass('ui-menu6-container AAA'+this._left_position+' ui-heurist-'+section)
                .css({'background':'none'})
                .appendTo( this.element );
                
		this._on(this.introductions[section].find('#menu-guide'),{click:this._loadIntroductoryGuide});  
		this._on(this.introductions[section].find('#start-hints'),{click:this._loadStartHints});			
                
            if(section=='design' && window.hWin.HEURIST4.util.getUrlParameter('welcome', window.hWin.location.search))
            {
                var ele = $('<div class="gs-box" style="margin:10px;width:500px;height:400px;">')
                    .appendTo(this.introductions[section]);
                ele.load(window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/welcome.html',
                        function(){
                           var url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
                           $('.bookmark-url').html('<a href="#">'+url+'</a>').click(function(e){
                                window.hWin.HEURIST4.util.stopEvent(e);
                                window.hWin.HEURIST4.msg.showMsgFlash('Press Ctrl+D to bookmark this page',1000);
                                return false;
                           });
                           $('.template-url').attr('href', window.hWin.HAPI4.baseURL
                                            +'documentation_and_templates/db_design_template.rtf');
                        });
            }            
        }
    },

    //
    // Landing Page when not loading the Welcome page
    //
    showDatabaseOverview: function(){

        var that = this;
        var editingProperties = false;

        // Check that the mainMenu widget has been created
        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
        if(!widget){
            return;
        }

        // Move to explore section
        if(this._active_section != 'explore'){
            this.switchContainer('explore');
        }

        function openDBProperties(event){
            if($(event.target).is('a')){
                return;
            }else if(editingProperties){
                return;
            }
            editingProperties = true;
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(e){
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification', {
                    beforeClose: function(){
                        editingProperties = false;
                        that.showDatabaseOverview();
                    }
                });
            }, 1);
        }

        function changeFuncAccess(){

            // DB Logo container
            var $thumb_container = $('div#db-thumb')
            .css({
                'display': 'block',
                'margin-left': 'auto',
                'margin-right': 'auto'
            })
            .off('click');

            $('#title_cont, #owner_cont, #rights_cont').off('click');
            $('div#description, button#btnEdit').off('click');

            // Check if logged in user has access to DB Props
            if(window.hWin.HAPI4.has_access(1)){ // Has access

                $('h3#title, span#owner, span#rights')
                .parent()
                .css('cursor', 'pointer')
                
                $('#title_cont, #owner_cont, #rights_cont')
                .one('click', openDBProperties);

                $('div#description')
                .css({'cursor': 'pointer', 'white-space': 'pre-wrap'})
                .on('click', openDBProperties);

                $('button#btnEdit')
                .prop('disabled', false)
                .button({label: 'Edit Metadata'})
                .addClass('ui-button-action')
                .css({
                    'display': 'inline-block'
                })
                .on('click', openDBProperties);

                $thumb_container.on('click', openDBProperties);

            }else{ // Has no access

                $('h3#title, span#owner, span#rights')
                .parent()
                .css({'cursor': 'default', 'resize': 'none'});

                $('button#btnEdit')
                .hide()
                .prop('disabled', true);

                $thumb_container
                .css('cursor', 'default');
            }
        }

        function changeStyles(){

            var title = $('h3#title');
            var owner = $('span#owner');
            var rights = $('span#rights');
            var desc = $('div#description');

            if(!window.hWin.HEURIST4.util.isempty(owner.html()) && owner.html() != 'Database Ownership'){
                owner.parent()
                .css({
                    'background': 'white',
                    'border': 'none',
                    'width': 'auto'
                });
            }else{
                owner.parent()
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'width': '90%'
                });
            }

            if(!window.hWin.HEURIST4.util.isempty(rights.html()) && rights.html() != 'Database Rights'){
                rights.parent()
                .css({
                    'background': 'white',
                    'border': 'none',
                    'width': 'auto'
                });
            }else{
                rights.parent()
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'width': '90%'
                });
            }

            if(!window.hWin.HEURIST4.util.isempty(desc.html()) && desc.html() != 'Database Description'){
                desc
                .css({
                    'background': 'white',
                    'border': 'none', 
                    'height': 'auto'
                });

                //desc.css('height', desc[0].scrollHeight);
            }else{
                desc
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'height': '150px'
                });
            }
        }
        
        function updateDetails(){

            // Add DB Logo
            var $thumb_container = $('div#db-thumb')
            .text('')
            .css({
                'display': 'block',
                'margin-left': 'auto',
                'margin-right': 'auto',
                'border': 'none',
                'background': 'none'
            });

            var thumb_url = window.hWin.HAPI4.getImageUrl('sysIdentification', 1, 'thumb', 1);
            var date = new Date();

            thumb_url += '&ts=' + date.getTime();
            $('<img src='+ thumb_url +' class="image_input"></img>').appendTo($thumb_container);

            window.hWin.HAPI4.checkImage('sysIdentification', 1, 'thumb', 
                function(response){

                    if(response.status != 'ok' || response.data != 'ok'){
                        $thumb_container.find('img').remove();

                        $thumb_container
                        .text('No Logo')
                        .css({
                            'border': '1px solid gray', 
                            'background': '#F4F2F4',
                            'display': 'flex',
                            'justify-content': 'center',
                            'align-items': 'center',
                            'width': 'fit-content',
                            'color': 'gray'
                        });
                    }
                }
            );

            // Fill in System Identification info {DB Name, Owner, Copyright and Description}
            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(response){
                
                if(!window.hWin.HEURIST4.util.isempty(response)){
                
                    var record = response.getFirstRecord();

                    var name = record[14];
                    var ownership = record[15];
                    var rights = record[16];
                    var desc = record[17];

                    if(!window.hWin.HEURIST4.util.isempty(name) && name != 'Please enter a DB name ...'){
                        $('h3#title').text(name);
                    }else{
                        $('h3#title').text('Database Title');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(ownership)){
                        $('span#owner').html("Owner: " + ownership);
                    }else{
                        $('span#owner').html('Database Ownership');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(rights) && rights != 'Please define ownership and rights here ...'){
                        $('span#rights').html("Rights: " + rights);
                    }else{
                        $('span#rights').html('Database Rights');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(desc)){
                        $('div#description').html(desc);
                    }else{
                        $('div#description').html('Database Description');
                    }
                }

                changeStyles();

            });
        }

        // Check if the page already exists
        if(widget.mainMenu('hasOverviewRendered')){

            this.containers['explore'].find('div#db_overview').css('z-index', '10').show();

            updateDetails();
            changeFuncAccess();

            return;
        }

        // Create main container
        var $ele = $('<div id="db_overview" class="ent_wrapper" style="background: white;">')
            .css('z-index', '10')
            .appendTo(this.containers['explore']);
        
        // Load Content
        $ele.load(window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/database_overview.html',
            function(){

                // Section headers within Content
                $('div.mock-header')
                .css({
                    'font-weight': 'bold',
                    'text-align': 'center',
                    'padding': '10px 0px',
                    'width': '85px',
                    'display': 'inline-block',
                    'color': 'white',
                    'border': '1px solid lightgray',
                    'cursor': 'pointer'
                })
                .on('click', function(e){
                    var option = $(e.target).is('img') ? 'explore' : $(e.target).attr('id');

                    if(window.hWin.HEURIST4.util.isempty(option)){
                        option = $(e.target).parent().attr('id');
                    }

                    $ele.hide();
                    
                    if(option != 'explore'){
                        that.switchContainer(option[0].toLowerCase() + option.substr(1));
                    }
                });
                // Add image to explore header
                var $explore_img = $('img#explore-img');
                $explore_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + $explore_img.attr('data-src'));
                // "New" and "Add record"
                $('div.add-new')
                .css({
                    'background': 'lightgray',
                    'display': 'inline-block',
                    'margin-left': '10px',
                    'padding': '10px',
                    'cursor': 'pointer'
                })
                .on('click', function(){
                    $('li.menu-explore[data-action-popup="recordAdd"]').mouseover();
                });

                // Explanation Text for each section
                $('span.flavour-text')
                .css({
                    'display': 'inline-block',
                    'margin-left': '10px',
                    'cursor': 'default'
                });

                // Commonly used entities/rectypes
                var entity_container = $('ul#entity-usage').css({
                    'list-style-type': 'none',
                    'padding-left': '30px',
                    'margin-top': '0px'
                });

                var options = {
                    select_name: 'usage_select', 
                    useCounts: true, 
                    useGroups: false, 
                    useHtmlSelect: true, 
                    useIcons: true, 
                    useIds: true, 
                    ancor: null
                };

                var entities_usage = window.hWin.HEURIST4.ui.createRectypeSelectNew(null, options);

                entities_usage.find('option').each(function(idx, item){

                    if(idx >= 11){ // Max of 10
                        return false;
                    }

                    var $opt = $(item);
                    var count = $opt.attr('rt-count') >= 0 ? $opt.attr('rt-count') : '';

                    $('<li data-id="'+$opt.attr('entity-id')+'" style="font-size:smaller;padding:4px 0px 2px 0px">'
                        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                            + '" class="rt-icon" style="vertical-align:bottom;background-image: url(&quot;'+$opt.attr('icon-url')+ '&quot;);"/>'
                        +'<div class="menu-text truncate" style="max-width:130px;display:inline-block;" title="Search for '+$opt.text()+' records">'
                        +$opt.text()+'</div>'
                        +'<span style="float:right;min-width:20px;margin-left:10px;">'+count+'</span>'
                       +'</li>')
                    .appendTo(entity_container); 

                });

                entity_container.find('li[data-id]').on('click', function(e){
                    var $ele = $(e.target).is('li') ? $(e.target) : $(e.target).parent('li');

                    var rectype_id = $ele.attr('data-id');

                    if(rectype_id > 0){

                        var request = {
                            q: 't:'+rectype_id,
                            w: 'a',
                            qname: $Db.rty(rectype_id, 'rty_Plural'),
                            detail: 'ids'
                        };

                        window.hWin.HAPI4.RecordSearch.doSearch(this, request);

                        that.switchContainer('explore');
                    }
                });

                updateDetails();
                changeFuncAccess();
            }
        );
    },

    //
    //
    //
    _loadIntroductoryGuide: function(e){
        
        var section = this._active_section;

        this._off(this.introductions[section].find('.gs-box'),'click');
        
        var that = this;
        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/getting_started.html div.gs-box.ui-heurist-'+section,
                function(){
                    //init images and video
                    that.introductions[section].find('img').each(function(i,img){
                        img = $(img);
                        img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    that.introductions[section].find('div.gs-box.ui-heurist-'+section)
                    .prepend( '<span class="ui-heurist-title header" id="start-hints" style="padding-top:57px;font-weight:normal;padding-left:20px;cursor:pointer">'
                                +'<span class="ui-icon ui-icon-help"/>&nbsp;Startup hints</span>' ).click(function(){ that._loadStartHints(null); });					

                    that.introductions[section].find('.gs-box')
                        .css({position:'absolute', left:'10px', right:'10px', top:'10px', 'min-width':'700px', margin:0}) //,'padding-left':20
                        .show();
                    that.introductions[section].find('.gs-box > div:first').css('margin','23px 0');
                    that.introductions[section].find('.gs-box .ui-heurist-title.header')
                        .css({position:'absolute', left:'160px', top:'40px', right:'400px', 'max-width':'540px'});

                    $('<div class="gs-box">')
                        .css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
                        .load( window.hWin.HRes('menu_'+section)+' #content' )
                        .appendTo( that.introductions[section] );
                })
                .css({left:(that._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})  //,'z-index':104
                .show();
                    
        this.containers[section].hide();
    },
    
    _loadStartHints: function(e){

        var section = this._active_section;

        this._off(this.introductions[section].find('#start-hints'),'click');
        this.introductions[section].find('#start-hints').hide();

        var that = this;

        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/getting_started.html div.gs-box.ui-heurist-'+section,
                function(){
                    // Display Section Img, hide link to YouTube video
                    that.introductions[section].find('img').each(function(i,img){
						img = $(img);
						img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    // Link to Menu Guide
                    that.introductions[section].find('div.gs-box.ui-heurist-'+section)
                    .prepend( '<span class="ui-heurist-title header" id="menu-guide" style="padding-top:57px;font-weight:normal;padding-left:20px;cursor:pointer">'
								+'<span class="ui-icon ui-icon-help"/>&nbsp;Menu guide</span>' ).click(function(){ that._loadIntroductoryGuide(null); });

                    // Display Content
                    that.introductions[section].find('.gs-box')
							.css({position:'absolute', left:10, right:10, top:10, 'min-width':700, margin:0}) //,'padding-left':20
							.show();
                    that.introductions[section].find('.gs-box > div:first').css('margin','23px 0');

                    that.introductions[section].find('.gs-box .ui-heurist-title.header')
							.css({position:'absolute', left:160, top:40, right:400, 'max-width':'540px'});

                    // Load Welcome Content
                    $container = $('<div class="gs-box">')
						.css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
						.load(window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/welcome.html', function(){
							
							// Bookmark Link
							var url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
							$('.bookmark-url').html('<a href="#">'+url+'</a>').click(function(e){
								window.hWin.HEURIST4.util.stopEvent(e);
								window.hWin.HEURIST4.msg.showMsgFlash('Press Ctrl+D to bookmark this page',1000);
								return false;
							});
						})
						.appendTo( that.introductions[section] );
                })
                .css({left: ((that._left_position+211)+'px'),
                      right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})  //,'z-index':104
                .show();

        this.containers[section].hide();
    },

    closeContainer: function(section){
        this.containers[section].empty().hide();
    }
});
