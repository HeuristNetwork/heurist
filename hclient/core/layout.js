/**
*  appInitAll - main function which initialises everything
*
*  @see ext/layout
*  @see layout_defaults.js - configuration file
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


//
// 
//
function hLayout(args) {
     var _className = "hLayout",
         _version   = "0.4";      

    var Hul = window.hWin.HEURIST4.util;

     var widgetinstances = [], //@todo array of all inited widgets 
         widgets = [],
         layouts = [],  //json description of layout
         _containerid;
         
    /**
    * Initialization
    */
    function _init( cfg_widgets, cfg_layouts ) {
         widgets = cfg_widgets ?cfg_widgets :[]; 
         layouts = cfg_layouts ?cfg_layouts :[];
    }

    
    function _appGetWidgetByName( widgetname ){

        var i;
        for(i=0; i<widgets.length; i++){
            if(widgets[i].widgetname==widgetname){
                return widgets[i];
            }
        }
        return null;
    }
    

    /**
    * Finds application in widgets array (see layout_default.js)
    *
    * @param id
    */
    function _appGetWidgetById(id){

        var i;
        for(i=0; i<widgets.length; i++){
            if(widgets[i].id==id){
                return widgets[i];
            }
        }
        return null;
    }
    
    //
    // put specified widget on top
    //
    // implemented for tabs only and if several same widgets are in layout it show first only
    //
    function _putAppOnTop( widgetname ){
        
        var app = _appGetWidgetByName( widgetname );
        if(Hul.isnull(app)) return;
        
        var ele = $(app.widget);  //find panel with widget
        if( ele.hasClass('ui-tabs-panel') ){
            //get parent tab and make it active
            $(ele.parent()).tabs( "option", "active", ele.index()-1 );
        }
    }

    //
    // we may use several widgets of the same type: staticPage or recordListExt for example
    // put specific tab on top
    // note: you have to define unique layout_id in layout_default.js
    //
    function _putAppOnTopById( layout_id ){
        
        if(Hul.isnull(layout_id)) return;
        
        //var $container = $(_containerid);
        var ele = $('div[layout_id="'+layout_id+'"]');
        if( ele.hasClass('ui-tabs-panel') ){
            $(ele.parent()).tabs( "option", "active", ele.index()-1 );
        }
    }
    
    //
    // action: close, open
    // args - [pane, values] 
    //
    function _cardinalPanel(action, args, element){

        var $container = null;
        
        //by default find parent of element with ui-layout-container clas
        //find parent container
        if(element){
            if($(element).hasClass('ui-layout-container')){
                $container = $(element);
            }else{
                $container = $(element).parents().find('.ui-layout-container');
                if($container.length>0) $container = $($container[0]);
            }
        }
        //otherwise root container
        if(!$container || $container.length==0){
            $container = $(_containerid); 
        }
     
        //if(!$.isFunction($container.layout)) return;
        if(!$container.hasClass('ui-layout-container')) {
            $container = $container.children().find('.ui-layout-container');
        }
        if(!$container || $container.length==0){
            return;
        }
        
        
        var pane, 
            myLayout = $container.layout();
        
        
        if($.isArray(args)){
            if(args.length<1) return;
            pane = args[0];
        }else{
            pane = args;
        }
        
        if(action=='open'){
            myLayout.open(pane);
        }else if(action=='close'){
            myLayout.close(pane);
        }else if(action=='getSize' && args.length==2){
            return myLayout.state[pane][args[1]];
        }else if(action=='sizePane' && args.length==2){
            myLayout.sizePane(pane, args[1]);
        }

        return false;
    }
    
    /**
    *  Creates "free" layout from html elements attributes heurist-app-id and heurist-app-options
    */
    function _getLayoutParams($container){
        
        var eles = $container.find('div[heurist-app-id]');
        
        var layout = {id:'Dynamic', type:'free'};
        
        var is_layout = false;
        
        for(var i=0; i<eles.length; i++){
            var ele = $(eles[i]);
            var app_id = ele.attr('heurist-app-id');
            if(_appGetWidgetById(app_id)!=null){
                var opts = window.hWin.HEURIST4.util.isJSON(ele.attr('heurist-app-options'));
                
                layout[ele.attr("id")] = {dropable:false, apps:[{appid:app_id, hasheader:false, 
                        options:opts!=false?opts:null }]};    
                        
                is_layout = true;
            }
        }
        
        return is_layout ?layout :null;
    }
    
    /**
    * Main funtion that inits all stuff
    *
    * layoutid to be loaded (see layouts in layout_default.js)
    * $container - base dic layout will be created on
    *
    * this function
    * 1) creates panes (see ext/layout)
    * 2) inits layout container
    * 3) adds tabs/apps to pane
    */
    function _appInitAll(layoutid, $container){

    //--------------------------------------------
    var grid_min_size = 200;
    var grid_step_size = 100;
    var app_counter = 0; //to maintain unique id for panels and tabs
    //var layout, $container;

    /**
    * Finds layout by id
    *
    * @param id
    */
    function layoutGetById(id){
        var i;
        for(i=0; i<layouts.length; i++){
            if(layouts[i].id==id){
                return layouts[i];
            }
        }
        return null;
    }

    //
    // north-west-east-south layout
    //
    function _initLayoutCardinal(layout, $container){

        var layout_opts =  {
            applyDefaultStyles: true,
            maskContents:        true,
            //togglerContent_open:    '<div class="ui-icon"></div>',
            //togglerContent_closed:  '<div class="ui-icon"></div>',
            //togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
            //togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-e"></div>',
            togglerContent_open:    '&nbsp;',
            togglerContent_closed:  '&nbsp;',
            //togglerLength_open: 21, 
            west:{
              spacing_open:6,
              spacing_closed:16,  
              togglerAlign_open:'center',
              togglerAlign_closed:20,
              togglerLength_closed:32,  //to makes it square set to 16
              onopen_start : function(){ 
                  var  w = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['center','outerWidth'] );
                  var mw = 250; //window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['west','minWidth'] );
                  if(w<310){
                      var tw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                      window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['east', tw-w-mw]);
                      setTimeout( function(){window.hWin.HAPI4.LayoutMgr.cardinalPanel('open', ['west'] );}, 500 );
                      return 'abort';
                  }
              },
            },
            east:{
              spacing_open:6,
              spacing_closed:16,  
              togglerAlign_open:'center',
              togglerAlign_closed:20, //'top',
              togglerLength_closed:32,  //to makes it square set to 16
              onopen_start: function(){ 
                  
                  var  w = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['center','outerWidth'] );
                  var mw = 350;
                  if(w<310){
                      var tw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                      window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['west', tw-w-mw]);
                      setTimeout( function(){window.hWin.HAPI4.LayoutMgr.cardinalPanel('open', ['east'] );}, 500 );
                      return 'abort';
                  }
                  
                  
              },
            },
            tips: {
                Close:                "Click to minimise panel",
                Resize:               "Drag to resize panel"
            },
            onresize_end: function(){
                $(document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE); //global app event
                onLayoutResize( $container );
            }
            /*,
            onopen_end: function(pane_name, pane_element){
                if(pane_name=='west'){
                    var tog = pane_element.parent().find('.ui-layout-toggler-west');
                    //tog.removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-w');
                    //tog.find('.content-open').addClass('ui_icon ui-icon-triangle-1-e');
                    //tog.find('.content-closed').addClass('ui_icon ui-icon-triangle-1-w');
                    //tog.removeClass('ui_icon ui-icon-triangle-1-e');
                    //tog.addClass('ui_icon ui-icon-triangle-1-w');
                    
                }
            },
            onclose_end: function(pane_name, pane_element){
                if(pane_name=='west'){
                }
            }*/
        };

        // 1) create panes (see ext/layout)
        var $pane = $(document.createElement('div'));
        $pane.addClass('ui-layout-center')
        .appendTo($container);

        if(!layout.center){
            layout_opts.center__minWidth = 200;
            //$pane.addClass('pane_dropable');
            layout['center'].dropable = true;
        }else{
            if(layout.center.minsize){
                layout_opts.center__minWidth = layout.center.minsize;
            }
        }

        function __layoutAddPane(pos){
            if(layout[pos]){

                var lpane = layout[pos];

                $pane = $(document.createElement('div'));
                $pane.addClass('ui-layout-'+pos)
                .appendTo($container);

                if(lpane.size){
                    layout_opts[pos+'__size'] = lpane.size;
                }
                if(Hul.isnull(lpane.resizable) || lpane.resizable ){
                    if(lpane.minsize){
                        layout_opts[pos+'__minSize'] = lpane.minsize;
                    }
                }else{
                    layout_opts[pos+'__spacing_open'] = 0;
                }
            }
        }

        __layoutAddPane('north');
        __layoutAddPane('west');
        __layoutAddPane('east');
        __layoutAddPane('south');

        // 2) init layout container
        $container.layout( layout_opts );

        function __toogleIcons(pane, closed, opened){
            var tog = $container.find('.ui-layout-toggler-'+pane);
            tog.addClass('ui-heurist-btn-header1')
            
            var togc = tog.find('.content-closed'); togc.empty();
            $('<div>').addClass('ui-icon ui-icon-triangle-1-'+closed).appendTo(togc);
            
            togc = tog.find('.content-open'); togc.empty();
            $('<div>').addClass('ui-icon ui-icon-triangle-1-'+opened).appendTo(togc);
        }
        
        __toogleIcons('west', 'e', 'w');
        __toogleIcons('east', 'w', 'e');
        
        // 3) add tabs/apps to panes

        var bg_color = window.hWin.HEURIST4.util.getCSS('background-color', 'ui-widget-content');
        //$('.ui-widget-content:first').css('background-color');
        $('body').css('background-color', bg_color);

        layoutInitPane(layout, $container, 'north', bg_color);
        layoutInitPane(layout, $container, 'west', bg_color);
        layoutInitPane(layout, $container, 'east', bg_color);
        layoutInitPane(layout, $container, 'south', bg_color);
        layoutInitPane(layout, $container, 'center', bg_color);

        initDragDropListener();

        onLayoutResize( $container );
        
     /* to remove
        // listener for drag-n-droop
        // move tab to layout and create new tabcontrol
        $( ".pane_dropable" ).droppable({
            accept: function(draggable){ //draggable = li
                //is this tab_cotrol
                return (draggable.parent().hasClass('ui-tabs-nav') && draggable.parent().children().length>2);
            },
            //activeClass: "ui-state-hover",
            //hoverClass: "ui-state-active",
            drop: function( event, ui ) {

                if(isMouseoverTabControl(event)){
                    return false;
                }

                $pane_content =  $(this);

                var $li = ui.draggable;
                //find portlet (content of tab) by href
                var content_id = $li.find('a').attr('href');
                var $app_content = $(content_id);

                var $src_tab = $app_content.parent();

                var app = _appGetWidgetById($app_content.attr('widgetid')); //ART04-26
                var offset = $pane_content.offset();
                var $tab = appCreateTabControl($pane_content, {appid: $app_content.attr('widgetid'), content_id: content_id.substr(1) }, //to remove #
                    {dockable: true, dragable:true, resizable:true,
                        css:{top:event.pageY-offset.top,left:event.pageX-offset.left,height:200,width:200}});
                appAdjustContainer();
                $app_content.appendTo( $tab );
                //remove from source
                $li.remove();

                var $tab = $tab.tabs();
                appInitFeatures('#'+$pane_content.attr('id'));

                $src_tab.tabs( 'refresh' );

                $pane_content.find('.tab_ctrl').css('z-index', '0');
                $tab.css('z-index', '1');

            }
        });
    */
        //temp appAdjustContainer();
    }

    /**
    * put your comment there...
    *
    * @param layout
    * @param $container
    */
    function _initLayoutFree(layout, $container){

        //$container.hide();

        //find main container and load template
        if(layout['template']){
               $container.empty();
               $container.hide();
               $container.load(layout['template'], function(){ 
                    layout['template'] = null; 
                    
                    _initLayoutFree(layout, $container); 
                    setTimeout(function(){
                        $container.show();
                    },2000);
               });    
               return;
        }

        //1. loop trough all layout panes  - create divs or use existing ones
        var panes = Object.keys(layout);
        var i, reserved = ['id', 'name', 'theme', 'type', 'options', 'cssfile', 'template'];

        function __layoutAddPane($container, pos){
            
            if(layout[pos]){

                var lpane = layout[pos];

                var ele = $container.find('#'+pos);
                //this div may already exists
                if(ele.length<1){
                    $pane = $('<div>',{id:pos})
                            .addClass('ui-layout-'+pos)
                            .appendTo($container);
                    //apply css
                    if(lpane.css){
                        $pane.css(lpane.css);
                    }else{
                        $pane.css({'min-width':400,'min-height':400});
                    }
                }else if(!ele.hasClass('ui-layout-'+pos)){
                    
                    ele.addClass('ui-layout-'+pos); //need to proper init
                    if(lpane.css){
                        ele.css(lpane.css);
                    }
                }
            }
        }

        
        var bg_color = $('.ui-widget-content:first').css('background-color');
        $('body').css('background-color', '');
        
        
        for (i=0; i<panes.length; i++){
            if(reserved.indexOf(panes[i])<0){
                 __layoutAddPane($container, panes[i]);
                 layoutInitPane(layout, $container, panes[i], null);
            }
        }

        initDragDropListener();
        
        //setTimeout(function(){$container.show();},1000);
    }

    /**
    * Adds application/widgets to specified pane
    *
    * @param $container
    * @param pos - position in cardinal layout (north,eastwest,center,south) - searched by class name in $container
    *              for "free" layout designer has to specify container div with class ui-layout-NAME
    * @param bg_color
    */
    function layoutInitPane(layout, $container, pos, bg_color){

            if(layout[pos]){

                var lpane = layout[pos];

                var $pane = $container.find('.ui-layout-'+pos);

                $pane.empty();

                var $pane_content = $(document.createElement('div'));
                $pane_content.attr('id','content_'+pos).addClass('ui-layout-content')
                    .appendTo($pane);
                    
                if(bg_color)    {
                    $pane_content.css('background-color', bg_color);
                }

                if(lpane.dropable){
                    $pane_content.addClass('pane_dropable');
                }

                //create tabs and their children apps
                if(lpane.tabs){
                    $.each(lpane.tabs, function(idx, tabb){
                        appCreateTabControl($pane_content, tabb.apps, tabb);
                    });
                }

                //create standalone apps
                if(lpane.apps){

                    $.each(lpane.apps, function(idx, app){
                        if(app.dockable){
                            appCreateTabControl($pane_content, app, null);
                        }else{
                            appCreatePanel($pane_content, app, true);
                        }
                    });
                }

                //init all tabs on current pane
                var containment_sel = '.ui-layout-'+pos+' > .ui-layout-content';
                var $tabs = $( containment_sel+' > .tab_ctrl' ).tabs({
                    activate: function(event ,ui){                 
                        var action_id = $(ui.newTab[0]).attr('data-logaction');
                        if(action_id && window.hWin && window.hWin.HAPI4){
                            window.hWin.HAPI4.SystemMgr.user_log(action_id);
                        }
                        //console.log(ui.newTab.index());
                    }}
                );
                
                
                
                appInitFeatures(containment_sel);
                
            }
    } //end layoutInitPane

    //
    // adjust absolute position of tab pane according to ul height
    //
    function onLayoutResize( $container ){
        var $tabs = $container.find('.tab_ctrl_adjust' );
        
        $tabs.each(function(idx, tabctrl){
            var h = $(tabctrl).find('ul[role="tablist"]').height();
            $(tabctrl).find('div[role="tabpanel"]').css({'top':h+4,'bottom':0,'width':'100%','position':'absolute'});
        });
    }
    
    function initDragDropListener(){
        
        // listener for drag-n-droop
        // move tab to layout and create new tabcontrol
        $( ".pane_dropable" ).droppable({
            accept: function(draggable){ //draggable = li
                //is this tab_cotrol
                return (draggable.parent().hasClass('ui-tabs-nav') && draggable.parent().children().length>2);
            },
            //activeClass: "ui-state-hover",
            //hoverClass: "ui-state-active",
            drop: function( event, ui ) {

                if(isMouseoverTabControl(event)){
                    return false;
                }

                $pane_content =  $(this);

                var $li = ui.draggable;
                //find portlet (content of tab) by href
                var content_id = $li.find('a').attr('href');
                var $app_content = $(content_id);

                var $src_tab = $app_content.parent();

                var app = _appGetWidgetById($app_content.attr('widgetid')); //ART04-26
                var offset = $pane_content.offset();
                var $tab = appCreateTabControl($pane_content, {appid: $app_content.attr('widgetid'), content_id: content_id.substr(1) }, //to remove #
                    {dockable: true, dragable:true, resizable:true,
                        css:{top:event.pageY-offset.top,left:event.pageX-offset.left,height:200,width:200}});
                appAdjustContainer();
                $app_content.appendTo( $tab );

                /*the same
                //var tt = $tab.get();
                //var pp = $portlet.get();
                //tt.appendChild(tt);
                //$portlet.prependTo( $tab );
                //$portlet.appendTo($tab);
                //$tab.append($portlet);
                */

                //remove from source
                $li.remove();

                var $tab = $tab.tabs();
                appInitFeatures('#'+$pane_content.attr('id'));

                $src_tab.tabs( 'refresh' );

                $pane_content.find('.tab_ctrl').css('z-index', '0');
                $tab.css('z-index', '1');

            }
        });
        //temp appAdjustContainer();
    }


    /**
    * Create simple application panel
    * it may be dragable and/or resizable
    *
    * $container - pane in layout
    * app - tab or app - entry from layout array - need for ui parameters
    * needcontent - load and create widget/libk at once (for standalone app only)
    */
    function appCreatePanel($pane_content, app, needcontent){

        app_counter++;

        var $d = $(document.createElement('div'));
        $d.attr('id', 'pnl_'+app_counter)  //.css('border','solid')
            .appendTo($pane_content);
        
        if(app.dragable){
            $d.addClass('dragable');
        }
        if(app.resizable){
            $d.addClass('resizable');
        }

        if(needcontent){ //for standalone application laod content at once

            var application = _appGetWidgetById(app.appid); //find in app array

            if(app.hasheader){
                var $header = $("<div>");
                $header.append(window.hWin.HR(app.name || application.name))
                .addClass('ui-widget-header')
                .addClass('ui-corner-all')
                .addClass('header'+app.appid+'_'+app_counter)
                .appendTo($d);
            }

            appAddContent($d, application, app);

            $d.addClass('ui-widget-content')
            .addClass('ui-corner-all');
            //.css('padding','0.2em');
        }

        if(app.css){
            $.each(app.css, function(key, value){
                $d.css(key, value);
            });
        }else if(app.resizable) {
            $d.css('width', '98%');
            $d.css('height', '98%');
        }else {
            $d.css('width', '99.999%');
            $d.css('height', '99.999%');
        }

        return $d;
    }

    /**
    * Createas and add div with application content - widget or url
    *
    * $container - pane in layout
    * app - entry from widgets array
    * options - application options from layouts array - parameters to init application
    */
    function appAddContent($app_container, app, appcfg){
        
      var options = appcfg.options, 
          layout_id = appcfg.layout_id;
          app_css = appcfg.css;

        var $content = $(document.createElement('div'));
        $content.attr('id', app.id+'_'+app_counter)
        .attr('widgetid', app.id)
        .appendTo($app_container);
        
        if(layout_id){
            $content.attr('layout_id', layout_id);
        }
        

        if(app.isframe){
            $content.addClass('frame_container');
            $content.append('<iframe id="'+app.id+'_'+app_counter+'" src="'+app.url+'"></iframe>');
        }else if(app.widgetname=='include_layout'){
            //nested layouts

            var layout = layoutGetById(options.ref);
            if(layout){

                if(app_css){
                    $.each(app_css, function(key, value){
                        $content.css(key, value);
                    });
                }else if(app.resizable) {
                    $content.css('width', '98%');
                    $d.css('height', '98%');
                }else {
                    $content.css('width', '99%');
                    $content.css('height', '99%');
                }
                
                _initLayoutCardinal(layout, $content);
            }
            
        }else if (app.script && app.widgetname) {

            app.widget = $content;

            if($.isFunction($('body')[app.widgetname])){ //OK! widget script js has been loaded
            
                manage_dlg = $content[app.widgetname]( options );
            }else{
                
                
/*            
            if(app.widgetname=='resultList'){
                //DEBUG
                widget = $content.resultList( options );
            }else if(app.widgetname=='recordListExt'){
                //DEBUG
                widget = $content.recordListExt( options );
            }else if(app.widgetname=='app_timemap'){
                //DEBUG
                widget = $content.app_timemap( options );
            }else if(app.widgetname=='mainMenu'){
                //DEBUG
                widget = $content.mainMenu( options );
            }else if(app.widgetname=='svs_list'){
                    //DEBUG
                    widget = $content.svs_list( options ); //options
            }else if(app.widgetname=='search'){

                   widget = $content.search( options );

            }else if(app.widgetname=='connections'){

                   widget = $content.connections( options );
                   
            }else if(app.widgetname=='dh_search'){
                   widget = $content.dh_search( options );
            }else if(app.widgetname=='dh_maps'){
                   widget = $content.dh_maps( options );
            }else if(app.widgetname=='expertnation_place'){
                   widget = $content.expertnation_place( options );
            }else if(app.widgetname=='expertnation_nav'){
                   widget = $content.expertnation_nav( options );
            }else
*/            
                        //this is normal way of widget initialization
                        // script is loaded dynamically and init function is widget name


                        $.getScript( window.hWin.HAPI4.baseURL + app.script, function() {  //+'?t='+(new Date().getTime())
                            if($.isFunction($content[app.widgetname])){
                                $content[app.widgetname]( options );   //call function
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr('Widget '+app.widgetname+' not loaded. Verify your configuration');
                            }
                        });
            }


        }else if (app.url2) {
            $content.load(app.url2);
        }else{
            $content.html(app.content?app.content :app.name);
        }
    }

    /**
    * Creates new tabcontrol - it may contains several applications
    *
    * $content - pane in layout
    * apps - list of application for tab (from layouts array)
    * tab - entry from layout - need for ui parameters
    */
    function appCreateTabControl($pane_content, apps, tabcfg){

        if(!apps) return null;

        if(! $.isArray(apps)){
            apps = [apps];
        }
        if(tabcfg==null){
            tabcfg = apps[0];
        }

        //create
        $tab_ctrl = appCreatePanel($pane_content, tabcfg, false);
        $tab_ctrl.addClass('tab_ctrl').css('border', 'none');
        if(tabcfg && tabcfg.layout_id){
            $tab_ctrl.attr('layout_id', tabcfg.layout_id);
        }
        

        if(tabcfg.dockable){
            $tab_ctrl.addClass('dockable');
        }

        var $ul = $(document.createElement('ul')).appendTo($tab_ctrl);
        
        if(tabcfg.sortable){
            $ul.addClass('sortable_tab_ul')  //@todo .css({'border':'none', 'background':'red'})
        }
        if(tabcfg.adjust_positions){
            $tab_ctrl.addClass('tab_ctrl_adjust');
        }
        
        /*
        if(tabcfg.css){
            $ul.css(tabcfg.css);
        }
        */

        //every app is new tab
        $.each(apps, function(idx, _app){
            //_app - reference to widget in layout tab list

            var app = _appGetWidgetById(_app.appid);

            if(app){    
                var content_id;
                if(_app.content_id){
                    content_id = _app.content_id; //already exists
                }else{
                    app_counter++;
                    content_id = app.id+'_'+app_counter;
                }
                
                //to log user activity
                var action_id = '';
                if(_app.options && _app.options['data-logaction']){
                    action_id = ' data-logaction="'+_app.options['data-logaction']+'"';
                }


                var title_html = '<li'+action_id+'><a class="header'+content_id+'" href="#'+content_id+'">'
                        + (window.hWin.HR(_app.name || app.name)) +'</a></li>';
                $ul.append($(title_html));
                
                if(!_app.content_id){ //already exists
                    appAddContent($tab_ctrl, app, _app);
                }
            }

        });
        
        
        if(tabcfg && tabcfg.style){    //@todo!!!!!
            if(tabcfg.style['background-header']){
                //$tab_ctrl.attr('background-header', tabcfg.style['background-header']+' !important');
                //$(tab_ctrl).find('ul').css('background',tabb.style['background-header']+' !important');    
            }
        }
        
        return $tab_ctrl;
    }

    /**
    * adjust width and height of container
    */
    function appAdjustContainer(){
        /*
        var mw = 0, mh = 0;

        $(".float_tabs").each(
        function(ids, element){

        var $item = $(element);
        var pos = $item.position();

        mw = Math.max(mw, pos.left+$item.width());
        mh = Math.max(mh, pos.top+$item.height());

        });

        var $container = $("#layout_float");

        if(mw>$(window).width()){
        $container.css('width', mw);
        }else{
        $container.css('width','100%');
        }
        if(mh>$(window).height()){
        $container.css('height', mh);
        }else{
        $container.css('height','100%');
        }
        */
    }

    /**
    *  Init listeners for sort, drag-drop, resize
    */
    function appInitFeatures(containment_selector){

        //sort tabs within one tab control
        $( ".tab_ctrl > .sortable_tab_ul" ).sortable({
            //axis: "x",
            cursor: "move",
            start: function(event, ui){
                $('.tab_ctrl').css('z-index', '0');
                $( this ).parent().css('z-index', '1');
                //ui.helper.css('z-index', '999999');
            }
        });

        //drag tabs between tab controls
        $( ".tab_ctrl .dragable > .ui-tabs-nav li" ).draggable({
            revert: "invalid",
            connectToSortable:'.sortable_tab_ul'
        });

        //move tab from one tab control to another
        $( containment_selector+" > .dockable > .ui-tabs-nav" ).droppable({
            accept: function(draggable){


                if(draggable.parent().hasClass('ui-tabs-nav')){
                    var src_id = draggable.parent().parent().attr('id');
                    var trg_id = $( this ).parent().attr('id');
                    return src_id!=trg_id;
                }else{
                    return false;
                }
            },
            activeClass: "ui-state-hover",
            hoverClass: "ui-state-active",
            drop: function( event, ui ) {

                var $tab = $( this ).parent(); //parent of ul

                var $li = ui.draggable;
                //find portlet (content of tab) by href
                var content_id = $li.find('a').attr('href');
                var app_name = $li.find('a').html();
                var $app_content = $(content_id);

                var $src_tab = $app_content.parent();

                if($src_tab.attr('id')==$tab.attr('id')) return;

                //add new tab and content
                //$portlet.find(".ui-widget-header").html()
                $tab.find( ".ui-tabs-nav" ).append("<li><a href='"+content_id+"'>"+app_name+"</a></li>");
                $tab.append($app_content);

                if($li.parent().children().length==2){
                    $src_tab.remove();
                    appAdjustContainer(); //@todo remove?
                }else{
                    //remove from source
                    $li.remove();
                    $src_tab.tabs( 'refresh' );
                }

                $tab.tabs( 'refresh' );

                $tab.parent().find('.tab_ctrl').css('z-index', '0');
                $tab.css('z-index', '1');

            }
        });
        
        // drag float tab controls around layout
        $( containment_selector+' > .dragable' ).draggable({
            stack: '.tab_ctrl',
            handle: '.sortable_tab_ul',
            containment: containment_selector //'.ui-layout-content'
        });

        //to destrot use 'destroy' to disable $( '.ui-tabs-nav li' ).draggable('disable');
        
        
        $( '.tab_ctrl ul' ).disableSelection();

        //init resize feature
        $(containment_selector+' > .resizable').resizable({
            //grid: [grid_step_size, grid_step_size],
            animate: false,
            minWidth: grid_min_size,
            minHeight: grid_min_size,
            containment: containment_selector, //'.ui-layout-content',
            autoHide: true
        });
    }

    //
    //
    //
    function isMouseoverTabControl(e){

        var s = e.pageX+"  "+e.pageY;
        var res = false;

        $(".tab_ctrl").each(
            function(ids, element){

                var $item = $(element);

                //offset -> method allows you to retrieve the current position of an element 'relative' to the document
                var position = $item.offset();

                if(e.pageX>position.left && e.pageX<position.left+$item.width() &&
                    e.pageY>position.top && e.pageY<position.top+$item.height()){

                    res = true;
                    /*$("#lblhover").html($item.attr('id'));
                    s = s + ' OK';*/
                    return false;

                }
                //$("#lblhover").html("xxxx");

        });

        return res;
    }
    
    var layout = null;
    if($.isPlainObject(layoutid) && layoutid['type'] &&  layoutid['id']){
        layout = layoutid;
        layoutid = layout['id'];
    }else{
        layout = layoutGetById(layoutid);
    }
    
    //**********************************************************

        if(layout==null){
            window.hWin.HEURIST4.msg.redirectToError('Layout ID:'+layoutid+' is not found. Verify your layout_default.js');
            if(layoutid!='H5Default') layout = layoutGetById('H5Default');
            if(layout==null){
                return;
            }
        }
        
        if(layoutid.indexOf('DigitalHarlem')==0 || layoutid=='Beyond1914' || layoutid=='UAdelaide'){
            //name of application to window title
            window.hWin.document.title = layout.name;
        }
        

        //var $container = $(containerid);
        
        //add style to header
        if(!Hul.isempty(layout.cssfile)){
            
            if(!$.isArray(layout.cssfile)){
                layout.cssfile = [layout.cssfile];
            }
            for (var idx in layout.cssfile){
                $("head").append($('<link rel="stylesheet" type="text/css" href="'+layout.cssfile[idx]+'?t='+(new Date().getTime())+'">'));
            }
            layout.cssfile = null;
        }
        

        if(Hul.isempty(layout.type) || layout.type=='cardinal'){
            
            $container.empty();
            _initLayoutCardinal(layout, $container);

        }else { //}if(layout.type=='free'){

            _initLayoutFree(layout, $container);

            
            //special styles case for default layout
            //@todo - definition of styles for tab control via layuot_default.js
            var tabb = $container.find('div[layout_id="main_header_tab"]');
            if(tabb.length>0){
                
                $(tabb).tabs({activate: function( event, ui ) { 
                        $(window).resize(); 
                        //change/restore z-index and background color
                        $(ui.newTab[0]).css({'z-index': ui.newTab.attr('data-zmax'),
                                       'background': 'url(hclient/assets/tab_shape_sel.png)',
                            'background-size': 'cover',
                            'background-repeat': 'no-repeat',
                        });
                        $(ui.oldTab[0]).css({'z-index': ui.newTab.attr('data-zkeep'),
                                       'background': 'url(hclient/assets/tab_shape.png)',
                            'background-size': 'cover',
                            'background-repeat': 'no-repeat',
                        });   
                }});
                
                var tabheader = $(tabb).children('ul');
                tabheader.css({'border':'none', 'background':'#8ea9b9'});  //, 'padding-top':'1em'
                
                $(tabb).children('.ui-tabs-panel[layout_id!="FAP"]').css({position:'absolute', top:'5.01em',
                        left:0,bottom:'0.2em',right:0, 'min-width':'75em',overflow:'hidden'});
                
                tabheader.find('a').css({'width':'100%','outline':0}); //, 'color': 'rgb(142, 169, 185)'
                
                var lis = tabheader.children('li');
                var count_lis = lis.length;
                    lis.css({
                            'outline':0,
                            'border':'none',
                            'font-weight': 'bold',
                            'font-size': '1.4em',
                            'padding': '12px 20px 0 1px',
                            'margin': '12px 0px 0px -4px',
                            'z-index': 3,
                            'background': 'url(hclient/assets/tab_shape.png)',
                            'background-size': 'cover',
                            'background-repeat': 'no-repeat',
                            'text-align': 'center',
                            'width': '200px',
                            'height': (navigator.userAgent.indexOf('Firefox')<0)?'33px':'45px' });
                            
                lis.each(function(idx,item){
                    
                   if(idx == lis.length-1) $(item).css({width:'300px'});
                   //$(item).css({width:((idx+1)*100+'px')});
                   $(item).css({'z-index': count_lis - idx});
                   $(item).attr('data-zkeep', count_lis - idx);
                   $(item).attr('data-zmax', count_lis+1);
                   if(idx>0){
                       //'padding-left':'12px', 
                       $(item).css({'margin-left':'-12px', 'border-left':'none'});
                   }
                   
                   if(idx==1){
                       //hide admin tab initially
                       $(item).hide();
                       
                       $('<span class="ui-icon ui-icon-close" title="Close this tab" '
                       +'style="font-size: 16px;width:24px;height:24px;position:absolute;right:10;top:20;z-index:2;cursor:pointer"></span>')
                       .click(function(){ 
                            $(item).hide(); 
                            if($(tabb).tabs("option", "active")==1) $(tabb).tabs({active:0}); 
                       })
                       .appendTo($(item));
                       
                       //$(item).attr('admintab',1);
                   }
                   
                });
                
                tabheader.parent().css({
                   //'overflow-y': 'none',
                   //'overflow-x': 'none',
                   'background': '#8ea9b9' 
                });
                
                $('#content_center_pane').find('#pnl_2').css({position:'absolute', top:0,
                        left:0,bottom:0,right:0,width:'',height:''});

                  
                /*var clayout = $('#content_center_pane').find('div[layout_id="FAP"]');        
                var myLayout = clayout.layout();
                myLayout.resizeAll();*/
                
            }
            
        }


    }   
 
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        appGetWidgetById: function(id){
            return _appGetWidgetById(id);
        },
        
        appGetWidgetByName: function( widgetname ){
            return _appGetWidgetByName( widgetname );
        },
        
        appInitAll: function(layoutid, containerid){
            _containerid = containerid
            var $container = $(containerid);
            _appInitAll(layoutid, $container);
        },
        
        //
        // 
        //
        appInitFromContainer: function( document, containerid ){
            _containerid = containerid;
            var $container;
            if(document){
                $container = $(document.body).find(containerid);
            }else{
                $container = $(containerid);
            }
            //create layout based on heurist-app-id and heurist-app-options
            var layout = _getLayoutParams($container); 
            if(layout){
                _appInitAll(layout, $container); 
            }
                
        },

        putAppOnTop: function( widgetname ){
            _putAppOnTop( widgetname );
        },

        putAppOnTopById: function( widgetname ){
            _putAppOnTopById( widgetname );
        },
        

        visibilityAppById: function ( layout_id, show_or_hide ){
            
            if(Hul.isnull(layout_id)) return;
            //var $container = $(_containerid);
            var ele = $('div[layout_id="'+layout_id+'"]');
            
            if( ele.hasClass('ui-tabs-panel') ){
                var ele2 = $(ele.parent()).find('ul li:eq('+(ele.index()-1)+')');
                if(show_or_hide){
                    ele2.show();
                }else{
                    ele2.hide();
                }
            }
        },    
        
        
        init: function(cfg_widgets, cfg_layouts){
            _init(cfg_widgets, cfg_layouts)
        },
        
        cardinalPanel:function(pane, action, element){
            return _cardinalPanel(pane, action, element);
        },

    }

    _init( cfg_widgets, cfg_layouts );
    return that;  //returns object
}




