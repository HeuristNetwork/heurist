/**
* navigation.js : menu for CMS
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


$.widget( "heurist.navigation", {

    options: {
       menu_recIDs:[],  //top level menu records
       orientation: 'horizontal', //vertical or treeview
       target: 'inline', // or popup 
       use_next_level: true,  //if top level consists of the single entry use next level of menues
       onmenuselect: null,   //for cms edit mode it performs special behavior
       aftermenuselect: null,
       toplevel_css:null,  //css for top level items
       expand_levels:0,  //expand levels for treeview
       onInitComplete: null
    },

    pageStyles:{},  //menu_id=>styles
    pageStyles_original:{}, //keep to restore  element_id=>css
    
    first_not_empty_page_id:0,

    _current_query_string:'',

    // the widget's constructor
    _create: function() {

        var that = this;

        if(this.element.parent().attr('data-heurist-app-id') || this.element.attr('data-heurist-app-id')){
            //this is CMS publication - take bg from parent
            if(this.element.parent().attr('data-heurist-app-id')){
                this.element.parent().css({'background':'none','border':'none'});
            }
            //A11 this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else{
            this.element.css('height','100%').addClass('ui-heurist-header2')    
        }
        
        this.element.disableSelection();// prevent double click to select text
      
        if(this.options.orientation=='treeview'){

            var fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: null,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: null,
                extensions:[],
                activate: function(event, data) { 
                    //main entry point to start edit rts field - open formlet
                    if(data.node.key>0){
                        that._onMenuItemAction(data.node.data);    
                    }
                }
            };

            this.element.fancytree(fancytree_options).addClass('tree-cms');

        }else{

            this.divMainMenu = $("<div>").appendTo(this.element);
            
            // MAIN MENU-----------------------------------------------------
            this.divMainMenuItems = $('<ul>').attr('data-level',0)
                    //.css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em'})
                    .appendTo( this.divMainMenu );
                    
            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.addClass('horizontalmenu');
            }
        }


        //find menu contents by top level ids    
        var ids = this.options.menu_recIDs;
        if(ids==null){
            this.options.menu_recIDs = [];
            ids = '';    
        } else {
            if($.isArray(ids)) {ids = ids.join(',');}
            else if(window.hWin.HEURIST4.util.isNumber(ids)){
                this.options.menu_recIDs = [ids];
            }else{
                this.options.menu_recIDs = ids.split(',')  
            } 
        }

        //retrieve menu content from server side
        /*var request = { q: 'ids:'+ids,
            detail: //'detail'
               [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
                window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_ELEMENT']],
            id: window.hWin.HEURIST4.util.random(),
            source:this.element.attr('id') };
            */
        var request = {ids:ids, a:'cms_menu'};
        var that = this;
            
            
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
                that._onGetMenuData(resdata);   
            }else{
                $('<p class="ui-state-error">Can\'t init menu: '+response.message+'</p>').appendTo(that.divMainMenu);
                //window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },
    
    //
    // callback function on getting menu records
    // resdata - recordset with menu records (full data)
    //
    _onGetMenuData:function(resdata){
        
        var RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
            DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
            DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
            DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
            DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
            DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
            DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'],
            DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'],
            DT_CMS_TARGET = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TARGET'],//target element on page or popup
            DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],//show page title above content
            DT_THUMBNAIL = window.hWin.HAPI4.sysinfo['dbconst']['DT_THUMBNAIL'],
            
            TERM_NO = $Db.getLocalID('trm','2-531'),
            TERM_NO_old = $Db.getLocalID('trm','99-5447');
            
        var that = this;
        var ids_was_added = [], ids_recurred = [];
        
        function __getMenuContent(parent_id, menuitems, lvl){
            
            var res = '';
            var resitems = [];
        
            for(var i=0; i<menuitems.length; i++){
                
                var record = resdata.getById(menuitems[i])

                if(ids_was_added.indexOf(menuitems[i])>=0){
                    //already was included
                    ids_recurred.push(menuitems[i]);
                }else{
                
                    var menuName = resdata.fld(record, DT_NAME);
                    var menuTitle = resdata.fld(record, DT_SHORT_SUMMARY);
                    var menuIcon = resdata.fld(record, DT_THUMBNAIL);

                    var recType = resdata.fld(record, 'rec_RecTypeID');
                    var page_id = menuitems[i]; //resdata.fld(record, 'rec_ID');
                    
                    //target and position
                    var pageTarget = resdata.fld(record, DT_CMS_TARGET);
                    var pageStyle = resdata.fld(record, DT_CMS_CSS);
                    var showTitle = resdata.fld(record, DT_CMS_PAGETITLE); 
                    
                    showTitle = (showTitle!==TERM_NO && showTitle!==TERM_NO_old);
                    
                    var hasContent = !window.hWin.HEURIST4.util.isempty(resdata.fld(record, DT_EXTENDED_DESCRIPTION))
                    
                    if(!(that.first_not_empty_page_id>0) && hasContent){
                        that.first_not_empty_page_id = page_id;
                    }

                    if(pageStyle){
                        that.pageStyles[page_id] = window.hWin.HEURIST4.util.cssToJson(pageStyle);    
                    }
                     
                    ids_was_added.push(page_id);
                        

                    if(that.options.orientation=='treeview'){
                        var $res = {};  
                        $res['key'] = page_id;
                        $res['title'] = menuName;
                        $res['parent_id'] = parent_id; //reference to parent menu(or home)
                        $res['page_id'] = page_id;
                        $res['page_showtitle'] = showTitle?1:0;
                        $res['page_target'] = (that.options.target=='popup')?'popup':pageTarget;
                        $res['expanded'] = (that.options.expand_levels>1 || lvl<=that.options.expand_levels); 
                                            //&& menuitems.length==1);
                        resitems.push($res);

                    }else{
                    
                        res = res + '<li><a href="#" style="padding:2px 1em;'
                                        +(hasContent?'':'cursor:default;')
                                        +'" data-pageid="'+ page_id + '"'
                                        + (pageTarget?' data-target="' + pageTarget +'"':'')
                                        + (showTitle?' data-showtitle="1"':'')
                                        + (hasContent?' data-hascontent="1"':'')
                                        + ' title="'+window.hWin.HEURIST4.util.htmlEscape(menuTitle)+'">'
                                        
                                        + (menuIcon?('<span><img src="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                            +'&thumb='+menuIcon+'" '
                                            +'style="height:16px;width:16px;padding-right:4px;vertical-align: text-bottom;"></span>'):'')
                                        + window.hWin.HEURIST4.util.htmlEscape(menuName)+'</a>';
                    }
                        
                    var subres = '';
                    var submenu = resdata.values(record, DT_CMS_MENU);
                    if(!submenu){
                        submenu = resdata.values(record, DT_CMS_TOP_MENU);
                    }
                    //has submenu
                    if(submenu){
                        if(!$.isArray(submenu)) submenu = submenu.split(',');
                        
                        if(submenu.length>0){ 
                            //next level                         
                            subres = __getMenuContent(record, submenu, lvl+1);
                            
                            if(that.options.orientation=='treeview'){
                                
                                $res['children'] = subres;
                                
                            } else if(subres!='') {
                                
                                res = res + '<ul style="min-width:200px"' 
                                            + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';
                            }
                        }
                    }
                    
                    if(that.options.orientation!='treeview'){
                        res = res + '</li>';
                    }
                    
                    if(lvl==0 && menuitems.length==1 && that.options.use_next_level){
                            return subres;    
                    }
                    
                
                }
            }//for
            
            return (that.options.orientation=='treeview') ?resitems :res;
        }//__getMenuContent   
        
        var menu_content = __getMenuContent(0, this.options.menu_recIDs, 0);     
        
        if(ids_recurred.length>0){
            var s = [];
            for(var i=0;i<ids_recurred.length;i++){
                s.push(ids_recurred[i]+' '
                    +resdata.fld(resdata.getById(ids_recurred[i]), DT_NAME));
            }
            window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recursive references to a menu containing themselves. Such a structure is not permissible for obvious reasons.<p>'
            +(s.join('<br>'))
            +'</p>Ask website author to fix this issue');
            /*+'<p>How to fix:<ul><li>Open in record editor</li>'
            +'<li>Find parent menu(s) in "Linked From" section</li>'
            +'<li>Open parent menu record and remove link to this record</li></ul>');*/
            /*window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recurred.<p>'
            +(s.join('<br>'))
            +'</p>Ask website author to fix this issue');*/            
        }
        
        //
        //
        //
        if(this.options.orientation=='treeview'){
            
            var tree = this.element.fancytree('getTree');
            tree.reload( menu_content );
            this.element.find('.ui-fancytree').show();
            
        }else{

            $(menu_content).appendTo(this.divMainMenuItems);

            var opts = {};
            if(this.options.orientation=='horizontal'){
                //opts = {position:{ my: "left top", at: "left bottom" }}; //+20
                
                opts = { position:{ my: "left top", at: "left bottom" },
                        focus: function( event, ui ){
                            
                   if(!$(ui.item).parent().hasClass('horizontalmenu')){
                        //indent for submenu
                        var ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'0px',  left:'200px'}); }, 300);      
                        }
                   }else {
                        //show below
                        var ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'29px',  left:'0px'}); }, 500);      
                        }
                   } 
                }};
                
            }

            var myTimeoutId = 0;
            //show hide function
            var _hide = function(ele) {
                myTimeoutId = setTimeout(function() {
                    $( ele ).hide();
                    }, 800);
            },
            _show = function(ele, parent) {
                clearTimeout(myTimeoutId);
                /*
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "left-2 top", at: "left bottom", of: parent });
                */
                return false;
            };

            opts['icons'] = {submenu: "ui-icon-carat-1-e" }; 
            
            //init jquery menu widget
            this.divMainMenuItems.menu( opts );

/*            
            var all_menues = this.divMainMenuItems.find('ul.ui-menu');
            this._on( all_menues, {
                mouseenter : function(){ _show(); },
                mouseleave : function(){ 
                    _hide(all_menues) 
                }
            });
*/
            //prevents default jquery delay         
            
            this.divMainMenuItems.children('li.ui-menu-item').hover(function(event) {
                    event.preventDefault();
                    $(this).children('.ui-menu').show();  
                },
                function(event) {
                    event.preventDefault();
                    $(this).find('.ui-menu').hide();
                }
            );        

            if(this.options.toplevel_css!==null){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').css(this.options.toplevel_css);
            }

            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').find('span.ui-menu-icon').hide();
            }
                        
            //
            // if onmenuselect function define it is used for action
            // otherwise it loads content to page_target (#main-content by default)
            //
            this._on(this.divMainMenuItems.find('a').addClass('truncate'),{click:this._onMenuClickEvent});
        }
        
        
        if($.isFunction(this.options.onInitComplete)){
            this.options.onInitComplete.call(this, this.first_not_empty_page_id);
        }

        
        
        //this._refresh();
    }, //end _onGetMenuData

    //
    //
    //
    _onMenuClickEvent: function(event){

        var data = {
            page_id: $(event.target).attr('data-pageid'), 
            page_target: $(event.target).attr('data-target'),
            page_showtitle: ($(event.target).attr('data-showtitle')==1),
            hasContent: ($(event.target).attr('data-hascontent')==1)
        };

        //hide submenu
        $(event.target).parents('.ui-menu[data-level!=0]').hide();
        /*var mele = $(event.target).parents('.ui-menu[data-level!=0]');
        if(mele.attr('data-level')!=0) mele.hide();*/
        
        if(!data.hasContent && !$.isFunction(this.options.onmenuselect)){
            //no action if content is not defined
            
        }else if(data.page_id>0){

            //highlight top most menu
            var ele = $(event.target).parents('.ui-menu-item');
            this.divMainMenuItems.find('a').removeClass('selected');
            $(ele[ele.length-1].firstChild).addClass('selected');    
            /*
            $.each(ele, function (idx, item){
            if($(item).parent().hasClass('ui-menu')){ //top most
            that.divMainMenuItems.find('a').removeClass('selected');
            $(item).children('a').addClass('selected');    
            return false;
            }
            });
            */


            this._onMenuItemAction(data);                

        }

    },
    
    //
    //
    //
    _onMenuItemAction: function(data){

        var that = this;

        if($.isFunction(that.options.onmenuselect)){

            this.options.onmenuselect( data.page_id );

        }else{

            // redirected to websiteRecord.php 
            // with field=1 it loads DT_EXTENDED_DESCRIPTION
            var page_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
            +'&field=1&recid='+data.page_id;

            var pageCss = that.pageStyles[data.page_id];

            if(data.page_target=='popup' || this.options.target=='popup'){


                var opts =  {  container:'cms-popup-'+window.hWin.HEURIST4.util.random(),
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: function(){

                        var pagetitle = $dlg.find('h2.webpageheading');
                        if(pagetitle.length>0){ //find title - this is first children
                            //pagetitle.addClass("webpageheading");//.css({position:'absolute',left:0,width:'auto'});
                            if(!data.page_showtitle){
                                pagetitle.hide();
                            }
                        }

                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer2( $dlg );
                    }
                };

                var dlg_css = null;
                if(pageCss){

                    if(pageCss['position']){
                        var val = window.hWin.HEURIST4.util.isJSON(pageCss['position']);
                        if(val==false){
                            delete pageCss['position'];
                        }else{
                            pageCss['position'] = val;
                        }
                    }  
                    opts = $.extend(opts, pageCss);

                    dlg_css = window.hWin.HEURIST4.util.cloneJSON(pageCss);
                    if(dlg_css['width']) delete dlg_css['width'];
                    if(dlg_css['height']) delete dlg_css['height'];

                }else{
                    opts['width']= 750;                            
                }                                


                var $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(page_url, null, 
                    'Heurist', opts, dlg_css);

                if(dlg_css){
                    $dlg.css(dlg_css);
                }


            }else{

                var page_target = '#main-content';   
                
                if(this.options.target=='inline_page_content'){
                    page_target = '#page-content';
                }else if(!window.hWin.HEURIST4.util.isempty(data.page_target)) {
                    page_target = data.page_target;
                }

                //load page content to page_target element 
                if(page_target[0]!='#') page_target = '#'+page_target;

                var continue_load_page = function() {
                    
                    if(pageCss && Object.keys(pageCss).length>0){
                        if(!that.pageStyles_original[page_target]){ //keep to restore
                            that.pageStyles_original[page_target] = $(page_target).clone();
                            //document.getElementById(page_target.substr(1)).style;//$(page_target).css();
                        }
                        $(page_target).css(pageCss);
                    }else if(that.pageStyles_original[page_target]){ //restore
                        //document.getElementById(page_target.substr(1)).style = that.pageStyles_original[page_target];
                        $(page_target).replaceWith(that.pageStyles_original[page_target]);                            
                    }
                    
                    var page_footer = $(page_target).find('#page-footer');
                    if(page_footer.length>0) page_footer.detach();

                    $(page_target).empty().load(page_url,
                        function(){

                            if(page_footer.length>0){
                                page_footer.appendTo( $(page_target) );
                                $(page_target).css({'min-height':$(page_target).parent().height()-page_footer.height()-10 });
                            } 
                            
                            window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, page_target );
                            //window.hWin.HEURIST4.msg.sendCoverallToBack();
                            if($.isFunction(that.options.aftermenuselect)){
                                that.options.aftermenuselect( document, data.page_id );
                                /*setTimeout(function(){
                                that.options.aftermenuselect( data.page_id );
                                },2000);*/
                            }
                    });
                };

                //before load we trigger  function

                var event_assigned = false;

                $.each($._data( $( page_target )[0], "events"), function(eventname, event) {
                    if(eventname=='onexitpage'){
                        event_assigned = true;
                        return false;
                    }
                    /*
                    var ele = $('#main-content').find('div[widgetid="heurist_resultListCollection"]');
                    if(ele.length>0 && ele.search('instance')) ele.resultListCollection('warningOnExit');

                    console.log(eventname);
                    $.each(event, function(j, h) {
                    console.log("- " + h.handler);
                    });
                    */
                });                        

                if(event_assigned){
                    $( page_target ).trigger( "onexitpage", continue_load_page );
                }else{
                    continue_load_page();
                }
            }                
        }
    },


    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

   //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },



    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },

    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        if(this.divMainMenu) this.divMainMenu.remove();
    },
    
    getFirstPageWithContent: function(){
        return this.first_not_empty_page_id;
    }
    
});
