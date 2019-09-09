/**
* navigation.js : menu for CMS
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


$.widget( "heurist.navigation", {

    options: {
       menu_recIDs:[],
       orientation: 'horizontal', //vertical
       use_next_level: false,
       onmenuselect: null,
       aftermenuselect: null,
       toplevel_css:null  //css for top level items
    },

    menues:{},
    pageStyles:{},  //page_id=>styles
    pageStyles_original:{}, //keep to restore  element_id=>css

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


        // MAIN MENU-----------------------------------------------------
        this.divMainMenu = $("<div>").appendTo(this.element);;

        this.divMainMenuItems = $('<ul>')
                //.css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em'})
                .appendTo( this.divMainMenu );
                
        if(this.options.orientation=='horizontal'){
            this.divMainMenuItems.addClass('horizontalmenu');
        }

        var ids = this.options.menu_recIDs;
        if(ids==null){
            this.options.menu_recIDs = [];
            ids = '';    
        } else {
            if($.isArray(ids)) {ids = ids.join(',');}
            else {this.options.menu_recIDs = this.options.menu_recIDs.split(',');}
        }
        //retrieve menu content from server side
        /*var request = { q: 'ids:'+ids,
            detail: //'detail'
               [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGE'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
                window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_ELEMENT']],
            id: window.hWin.HEURIST4.util.random(),
            source:this.element.attr('id') };
            */
        var request = {ids:ids, a:'menu'};
        var that = this;
            
            
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
                that._onGetMenuData(resdata);   
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },
    
    //
    // callback function on getting menu records
    //
    _onGetMenuData:function(resdata){
        
        var RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],

            DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
            DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
            DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
            DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
            DT_CMS_PAGE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGE'],  //pointer to page 
            DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'],
            DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'],
            DT_CMS_TARGET = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TARGET'];//target element on page or popup
            
        var that = this;
        
        function __getMenuContent(parent_id, menuitems, lvl){
            
            var res = '';
        
            for(var i=0; i<menuitems.length; i++){
                
                var record = resdata.getById(menuitems[i])
                
                var menuName = resdata.fld(record, DT_NAME);
                var menuTitle = resdata.fld(record, DT_SHORT_SUMMARY);
                var recType = resdata.fld(record, 'rec_RecTypeID');
                var page_id; //record id to be load into content
                
                if(recType == RT_CMS_MENU || recType == DT_CMS_TOP_MENU){
                    page_id = resdata.fld(record, DT_CMS_PAGE);
                    if(!(page_id>0)){
                        //pointer to page not defined - take content from menu record
                        page_id = resdata.fld(record, 'rec_ID');
                    }
                }else{
                    page_id = resdata.fld(record, 'rec_ID');
                }
                
                //target and position
                var pageTarget = resdata.fld(record, DT_CMS_TARGET);
                var pageStyle = resdata.fld(record, DT_CMS_CSS);
                if(pageStyle){
                    that.pageStyles[page_id] = window.hWin.HEURIST4.util.cssToJson(pageStyle);    
                }
                
                res = res + '<li><a href="#" style="padding:2px 1em" data-pageid="'
                                + page_id + '"'
                                + (pageTarget?' data-target="' + pageTarget +'"':'')
                                + ' title="'+window.hWin.HEURIST4.util.htmlEscape(menuTitle)+'">'
                                +window.hWin.HEURIST4.util.htmlEscape(menuName)+'</a>';
                
                var subres = '';
                var submenu = resdata.values(record, DT_CMS_MENU);
                if(!submenu){
                    submenu = resdata.values(record, DT_CMS_TOP_MENU);
                }
                if(submenu){
                    if(!$.isArray(submenu)) submenu = submenu.split(',');
                    
                    if(submenu.length>0){                          
                        subres = __getMenuContent(record, submenu, lvl+1);
                        if(subres!='')
                            res = res + '<ul style="min-width:200px"' 
                                        + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';
                    }
                }
                res = res + '</li>';
                
                if(lvl==0 && menuitems.length==1 && that.options.use_next_level){
                        return subres;    
                }
                
            }//for
            
            return res;
        }//__getMenuContent   
        
        var menu_content = __getMenuContent(0, this.options.menu_recIDs, 0);     
        
        $(menu_content).appendTo(this.divMainMenuItems);
        
        var opts = {};
        if(this.options.orientation=='horizontal'){
            opts = {position:{ my: "left top", at: "left+20 bottom" }};
        }
        
        this.divMainMenuItems.menu( opts );
        
        if(this.options.toplevel_css!==null){
            this.divMainMenuItems.children('li.ui-menu-item').children('a').css(this.options.toplevel_css);
        }
        
        
        this.divMainMenuItems.find('a').addClass('truncate').click(function(event){

            var pageid = $(event.target).attr('data-pageid');
            var page_target = $(event.target).attr('data-target');
            if(!page_target) page_target = '#main-content';
            
            if(pageid>0){
                
                //highlight top most menu
                var ele = $(event.target).parents('.ui-menu-item');
                that.divMainMenuItems.find('a').removeClass('selected');
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
                
                if($.isFunction(that.options.onmenuselect)){
                    
                    that.options.onmenuselect( pageid );
                    
                }else{
                
                    var page_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                    +'&field=1&recid='+pageid;
                                    
                    var pageCss = that.pageStyles[pageid];

                    if(page_target=='popup'){
                        
                        
                        var opts =  {  container:'cms-popup-'+window.hWin.HEURIST4.util.random(),
                                close: function(){
                                    $dlg.dialog('destroy');       
                                    $dlg.remove();
                                },
                                open: function(){


                                    var pagetitle = $($dlg.find('.ui-dialog-content').children()[0]);
                                    if(pagetitle.is('h2')){
                                        pagetitle.addClass("webpageheading");//.css({position:'absolute',left:0,width:'auto'});
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
                        //load page content to page_target element 
                        
                        if(page_target[0]!='#') page_target = '#'+page_target;
                        
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

                        $(page_target).empty().load(page_url,
                            function(){
                                
                                var pagetitle = $($(page_target).children()[0]);
                                if(pagetitle.is('h2')){
                                    if(page_target=='#main-content')
                                    {   //move page title
                                        pagetitle.addClass("webpageheading");
                                        $('#main-pagetitle').empty().append(pagetitle);
                                    }else{
                                        pagetitle.remove();
                                    }
                                }
                                window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, page_target );
                                //window.hWin.HEURIST4.msg.sendCoverallToBack();
                                if($.isFunction(that.options.aftermenuselect)){
                                    that.options.aftermenuselect( pageid );
                                    /*setTimeout(function(){
                                        that.options.aftermenuselect( pageid );
                                    },2000);*/
                                }
                        });
                    }                
                }
            }
        });
        
        //this._refresh();
    }, //end _create



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
        this.divMainMenu.remove();
    }
    
});
