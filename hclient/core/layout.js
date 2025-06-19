/**
 * @file layout.js
 * @brief Defines the HLayout factory for an older layout management system.
 * @fileOverview This file contains the HLayout factory function, which provides an older system for
 * initializing and managing web page layouts in Heurist. It is based on configurations typically
 * defined in `layout_defaults.js` and can handle cardinal (pane-based) and "free" layouts
 * (derived from HTML attributes). It supports dynamic creation of panes, tabs, and embedding
 * of Heurist widgets. This system is noted as intended to be replaced by HLayoutMgr.
 * Key functions include `_appInitAll` for overall layout initialization, and helpers for
 * managing panes, widgets, and drag-drop listeners.
 * @see ext/layout
 * @see layout_defaults.js
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/* global cfg_widgets, cfg_layouts, HLayoutMgr, HRecordSet */ // Added HLayoutMgr, HRecordSet based on apparent context/potential usage

/**
 * Factory function for the HLayout object, an older layout management system for Heurist.
 * It initializes based on widget and layout configurations (typically from `cfg_widgets` and `cfg_layouts`).
 * The returned object provides methods to initialize layouts within specified containers,
 * manage widgets, and handle cardinal (pane-based) layouts.
 *
 * @constructor HLayout
 * @param {Object} [args] - Arguments for initialization (currently seems unused in the provided snippet,
 *                          as `_init` takes `cfg_widgets` and `cfg_layouts` which are global).
 * @returns {Object} An HLayout instance with methods for layout management.
 */
function HLayout(args) {
     const _className = "HLayout",
         _version   = "0.4";      

    let  widgets = [],
         layouts = [],  //json description of layout
         _containerid,
         _is_container_layout = false;
         
    // Internal helper function - JSDoc intentionally omitted
    function _init( cfg_widgets_param, cfg_layouts_param ) { // Renamed params to avoid conflict if globals are modified
         widgets = cfg_widgets_param ? cfg_widgets_param :[];
         layouts = cfg_layouts_param ? cfg_layouts_param :[];
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _appGetWidgetByName( widgetname ){
        let i;
        for(i=0; i<widgets.length; i++){
            if(widgets[i].widgetname==widgetname){
                return widgets[i];
            }
        }
        return null;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _appGetWidgetById(id){
        for(let i=0; i<widgets.length; i++){
            if(widgets[i].id==id){
                return widgets[i];
            }
        }
        return null;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _cardinalPanel(action, args, element){
        let $container = null;
        if(element){
            if($(element).hasClass('ui-layout-container')){
                $container = $(element);
            }else{
                $container = $(element).parents().find('.ui-layout-container');
                if($container.length>0) $container = $($container[0]);
            }
        }
        if(!$container || $container.length==0){
            $container = $(_containerid); 
        }
        if(!$container.hasClass('ui-layout-container')) {
            $container = $container.children().find('.ui-layout-container');
        }
        if(!$container || $container.length==0){
            return;
        }
        let pane, 
            myLayout = $container.layout();
        if(Array.isArray(args)){
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
    
    // Internal helper function - JSDoc intentionally omitted
    function _getLayoutParams($container, supp_options){
        let eles = $container.find('div[data-heurist-app-id]');
        let layout = {id:'Dynamic', type:'free'};
        let is_layout = false;
        for(let i=0; i<eles.length; i++){
            let ele = $(eles[i]);
            let app_id = ele.attr('data-heurist-app-id');
            if(_appGetWidgetById(app_id)!=null){
                let cfgele = ele.find('span.widget-options:first');
                if(cfgele.length==0) cfgele = ele;
                let opts = window.hWin.HEURIST4.util.isJSON(cfgele.text());
                if(supp_options && supp_options[app_id]){
                    opts = (opts!=false)? $.extend(opts, supp_options[app_id])
                                    :supp_options[app_id];
                }
                layout[ele.attr("id")] = {dropable:false, apps:[{appid:app_id, hasheader:false, 
                        options:opts!=false?opts:null }]};    
                is_layout = true;
            }
        }
        return is_layout ?layout :null;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _defineMediaSource($container){
        $container.find('img[data-id], source[data-id], embed[data-id]').each(
            function(idx,item){
                let surl = window.hWin.HAPI4.baseURL_pro+'?db='
                        + window.hWin.HAPI4.database
                        + "&file=" + $(item).attr('data-id');
                $(item).attr('src', surl);
            }
        );
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _layoutGetById(id){
        if(id){
            id = id.toLowerCase();
            let i;
            for(i=0; i<layouts.length; i++){
                if(layouts[i].id.toLowerCase()==id){
                    return layouts[i];
                }
            }
        }
        return null;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _appInitAll(layoutid, $container){
        let grid_min_size = 200;
        let app_counter = 0;

        function _initLayoutCardinal(layout, $container_cardinal){ // Renamed param
            let layout_opts =  {
                applyDefaultStyles: true,
                maskContents:       true,
                togglerContent_open:    '&nbsp;',
                togglerContent_closed:  '&nbsp;',
                west:{
                  spacing_open:6,
                  spacing_closed:40,
                  togglerAlign_open:'center',
                  togglerAlign_closed:'top',
                  togglerLength_closed:40,
                  onopen_start : function(){
                      let  w = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['center','outerWidth'] );
                      let mw = 250;
                      if(w<310){
                          let tw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                          window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['east', tw-w-mw]);
                          setTimeout( function(){window.hWin.HAPI4.LayoutMgr.cardinalPanel('open', ['west'] );}, 500 );
                          return 'abort';
                      }
                      let tog = $container_cardinal.find('.ui-layout-toggler-west');
                      tog.removeClass('prominent-cardinal-toggler');
                  },
                  onclose_end : function(){
                       let tog = $container_cardinal.find('.ui-layout-toggler-west');
                       tog.addClass('prominent-cardinal-toggler');
                  }
                },
                east:{
                  spacing_open:6,
                  spacing_closed:40,
                  togglerAlign_open:'center',
                  togglerAlign_closed:'top',
                  togglerLength_closed:40,
                  onopen_start: function(){
                      let  w = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['center','outerWidth'] );
                      let mw = 350;
                      if(w<310){
                          let tw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                          window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['west', tw-w-mw]);
                          setTimeout( function(){window.hWin.HAPI4.LayoutMgr.cardinalPanel('open', ['east'] );}, 500 );
                          return 'abort';
                      }
                      let tog = $container_cardinal.find('.ui-layout-toggler-east');
                      tog.removeClass('prominent-cardinal-toggler togglerVertical');
                      tog.find('.heurist-helper2.eastTogglerVertical').remove();
                  },
                  onclose_end : function(){
                       let tog = $container_cardinal.find('.ui-layout-toggler-east');
                       tog.addClass('prominent-cardinal-toggler togglerVertical');
                       $('<span class="heurist-helper2 eastTogglerVertical" style="width:175px;">Visualisation</span>').appendTo(tog);
                  }
                },
                tips: { Close: "Click to minimise panel", Resize: "Drag to resize panel" },
                onresize_end: function(){
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE);
                    onLayoutResize( $container_cardinal );
                }
            };
            let $pane = $(document.createElement('div'));
            $pane.addClass('ui-layout-center').appendTo($container_cardinal);
            if(!layout.center){
                layout_opts.center__minWidth = 200;
                layout['center'] = { dropable: true }; // Ensure layout.center exists
            }else{
                if(layout.center.minsize){
                    layout_opts.center__minWidth = layout.center.minsize;
                }
            }
            function __layoutAddPane(pos){ // Nested helper
                if(layout[pos]){
                    let lpane_inner = layout[pos]; // Renamed to avoid conflict
                    $pane = $(document.createElement('div'));
                    $pane.addClass('ui-layout-'+pos).appendTo($container_cardinal);
                    if(lpane_inner.size){
                        layout_opts[pos+'__size'] = lpane_inner.size;
                    }
                    if(window.hWin.HEURIST4.util.isnull(lpane_inner.resizable) || lpane_inner.resizable ){
                        if(lpane_inner.minsize){
                            layout_opts[pos+'__minSize'] = lpane_inner.minsize;
                        }
                    }else{
                        layout_opts[pos+'__spacing_open'] = 0;
                    }
                }
            }
            __layoutAddPane('north'); __layoutAddPane('west'); __layoutAddPane('east'); __layoutAddPane('south');
            layout_opts['enableCursorHotkey'] = false;
            $container_cardinal.layout( layout_opts );
            __toogleIcons($container_cardinal, 'west', 'e', 'w');
            __toogleIcons($container_cardinal, 'east', 'w', 'e');
            let bg_color = window.hWin.HEURIST4.util.getCSS('background-color', 'ui-widget-content');
            $('body').css('background-color', bg_color);
            layoutInitPane(layout, $container_cardinal, 'north', bg_color);
            layoutInitPane(layout, $container_cardinal, 'west', bg_color);
            layoutInitPane(layout, $container_cardinal, 'east', bg_color);
            layoutInitPane(layout, $container_cardinal, 'south', bg_color);
            layoutInitPane(layout, $container_cardinal, 'center', bg_color);
            initDragDropListener();
            onLayoutResize( $container_cardinal );
        }

        // Internal helper function - JSDoc intentionally omitted
        function __toogleIcons($container_toggle, pane, closed, opened){ // Renamed param
            let tog = $container_toggle.find('.ui-layout-toggler-'+pane);
            let togc = tog.find('.content-closed'); togc.empty();
            $('<div>').addClass('ui-icon ui-icon-carat-2-'+closed).appendTo(togc);
            togc = tog.find('.content-open'); togc.empty();
            $('<div>').addClass('ui-icon ui-icon-triangle-1-'+opened).appendTo(togc);
        }

        // Internal helper function - JSDoc intentionally omitted
        function _initLayoutFree(layout_free, $container_free){ // Renamed params
            if(layout_free['template']){
                   $container_free.empty(); $container_free.hide();
                   $container_free.load(layout_free['template'], function(){
                        layout_free['template'] = null;
                        _initLayoutFree(layout_free, $container_free);
                        setTimeout(function(){ $container_free.show(); },2000);
                   });
                   return;
            }
            let panes = Object.keys(layout_free);
            let i, reserved = ['id', 'name', 'theme', 'type', 'options', 'cssfile', 'template'];
            function __layoutAddPaneFree(pos_free){ // Nested helper, renamed param
                if(layout_free[pos_free]){
                    let lpane_free = layout_free[pos_free]; // Renamed
                    let ele = $container_free.find('#'+pos_free);
                    if(ele.length<1){
                        let $pane_free = $('<div>',{id:pos_free}) // Renamed
                                .addClass('ui-layout-'+pos_free)
                                .appendTo($container_free);
                        if(lpane_free.css){ $pane_free.css(lpane_free.css); }
                        else{ $pane_free.css({'min-width':400,'min-height':400}); }
                    }else if(!ele.hasClass('ui-layout-'+pos_free)){
                        ele.addClass('ui-layout-'+pos_free);
                        if(lpane_free.css){ ele.css(lpane_free.css); }
                    }
                }
            }
            $('body').css('background-color', '');
            for (i=0; i<panes.length; i++){
                if(reserved.indexOf(panes[i])<0){
                     __layoutAddPaneFree(panes[i]);
                     layoutInitPane(layout_free, $container_free, panes[i], null);
                }
            }
            initDragDropListener();
        }
        
        // Internal helper function - JSDoc intentionally omitted
        function layoutInitPane(layout_pane, $container_pane, pos_pane, bg_color_pane){ // Renamed params
                if(layout_pane[pos_pane]){
                    let cardinal_panes = ['north','east','west','center','south'];
                    let $pane_content_init; // Renamed
                    let lpane_init = layout_pane[pos_pane]; // Renamed
                    let $pane_init = $container_pane.find('.ui-layout-'+pos_pane); // Renamed
                    if (lpane_init.apps && lpane_init.apps[0].appid == 'heurist_Groups') {
                        $pane_init.find('div.widget-design-header:first').remove();
                        $pane_init.find('span.widget-options:first').remove();
                        let mode = lpane_init.apps[0].options.groups_mode;
                        if(mode!='tabs'){
                            let pid = $pane_init.attr('id');
                            $pane_init.children('ul').remove();
                            $pane_init.children('.ui-tabs-panel').each(function(idx,item){
                                if(idx<lpane_init.apps[0].options.tabs.length){
                                    if(mode!='divs'){ $('<h3>').html(lpane_init.apps[0].options.tabs[idx].title).appendTo($pane_init); }
                                    let ele = $('<div>').html($(item).html()).appendTo($pane_init);
                                    ele.addClass('group-tab').attr('id',(pid+'-'+idx));
                                    if(mode=='divs' && idx>0) ele.hide();
                                }
                                $(item).remove();
                            });
                            if(mode=='accordion'){ $pane_init.accordion(); }
                        }else{
                            let locationUrl = window.hWin.HAPI4.baseURL;
                            $pane_init.find('li > a').each(function(idx, item){
                               let href = $(item).attr('href');
                               href = href.substr(href.indexOf('#'));
                               $(item).attr('href', locationUrl + href);
                            });
                            $pane_init.tabs();
                        }
                        return;
                    } else if (lpane_init.apps && lpane_init.apps[0].appid == 'heurist_Cardinals') {
                        $pane_init.find('div.widget-design-header:first').remove();
                        $pane_init.find('span.widget-options:first').remove();
                        if(!lpane_init.apps[0].options || !lpane_init.apps[0].options.tabs){ console.error('Cardinal layout widget does not have proper options'); return; }
                        let layout_opts_card = lpane_init.apps[0].options.tabs; // Renamed
                        let $cardinal_container_init; // Renamed
                        if(lpane_init.apps[0].options.container){ $cardinal_container_init = $('#'+lpane_init.apps[0].options.container); }
                        else{ $cardinal_container_init = $pane_init; }
                        let keys = Object.keys(layout_opts_card);
                        for(let i=0; i<keys.length; i++){
                            let ele_id = layout_opts_card[keys[i]].id;
                            $cardinal_container_init.children('#'+ele_id).addClass('ui-layout-'+keys[i]);
                        }
                        layout_opts_card['applyDefaultStyles'] = true; layout_opts_card['applyDemoStyles'] = false;
                        layout_opts_card['maskContents'] = true; layout_opts_card['togglerAlign_open']  = 'center';
                        layout_opts_card['togglerContent_open']   = '&nbsp;'; layout_opts_card['togglerContent_closed'] = '&nbsp;';
                        layout_opts_card['spacing_open'] = 6; layout_opts_card['spacing_closed'] = 16;
                        layout_opts_card['onresize_end'] = function(){ $(document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE); };
                        layout_opts_card['enableCursorHotkey'] = false;
                        if(!$cardinal_container_init.is(':visible')){
                            $cardinal_container_init.layout(layout_opts_card);
                            $cardinal_container_init.on("myOnShowEvent", function(event){
                                $cardinal_container_init.off("myOnShowEvent");
                                __toogleIcons($cardinal_container_init, 'west', 'e', 'w');
                                __toogleIcons($cardinal_container_init, 'east', 'w', 'e');
                            });
                        }else{
                            $cardinal_container_init.layout(layout_opts_card);
                            __toogleIcons($cardinal_container_init, 'west', 'e', 'w');
                            __toogleIcons($cardinal_container_init, 'east', 'w', 'e');
                        }
                        if($cardinal_container_init.attr('id') != $pane_init.attr('id')){ $pane_init.hide(); }
                        return;
                    }else{ $pane_init.empty(); }
                    if(cardinal_panes.indexOf(pos_pane)>=0){
                        $pane_content_init = $(document.createElement('div'));
                        $pane_content_init.attr('id','content_'+pos_pane).addClass('ui-layout-content').appendTo($pane_init);
                        if(bg_color_pane) { $pane_content_init.css('background-color', bg_color_pane); }
                        if(lpane_init.dropable){ $pane_content_init.addClass('pane_dropable'); }
                        if(lpane_init.tabs){
                            $.each(lpane_init.tabs, function(idx, tabb){ appCreateTabControl($pane_content_init, tabb.apps, tabb); });
                        }
                        if(lpane_init.apps){
                            $.each(lpane_init.apps, function(idx, app_item){ // Renamed
                                if(app_item.dockable){ appCreateTabControl($pane_content_init, app_item, null); }
                                else{ appCreatePanel($pane_content_init, app_item, true); }
                            });
                        }
                        let containment_sel = '.ui-layout-'+pos_pane+' > .ui-layout-content';
                        let $tabs = $container_pane.find( containment_sel+' > .tab_ctrl' ).tabs({
                            activate: function(event ,ui){
                                let action_id = $(ui.newTab[0]).attr('data-logaction');
                                if(action_id && window.hWin && window.hWin.HAPI4){ window.hWin.HAPI4.SystemMgr.user_log(action_id); }
                                if($(this).attr('data-keep-width')==1){
                                    let w = $(ui.oldTab[0]).parents('.ui-layout-pane').width();
                                    $(ui.oldTab[0]).attr('data-width', w);
                                    w = $(ui.newTab[0]).attr('data-width');
                                    if(w>0){ window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['east', w], $container_pane); }
                                }
                            }}
                        );
                        if(pos_pane == 'east'){
                            $tabs.find('.ui-tabs-anchor').on('keydown',function(e){ e.stopPropagation(); e.preventDefault(); return false; });
                        }
                        appInitFeatures(containment_sel);
                    }else{
                        $pane_content_init = $container_pane.find('.ui-layout-'+pos_pane);
                        appCreatePanel($pane_content_init, lpane_init.apps[0], true);
                    }
                }
        }

        // Internal helper function - JSDoc intentionally omitted
        function onLayoutResize( $container_resize ){ // Renamed param
            let $tabs_resize = $container_resize.find('.tab_ctrl_adjust' ); // Renamed
            $tabs_resize.each(function(idx, tabctrl){
                let h = $(tabctrl).find('ul[role="tablist"]').height();
                $(tabctrl).find('div[role="tabpanel"]').css({'top':h+4,'bottom':0,'position':'absolute',left:'2px',right:'4px'});
            });
        }
        
        // Internal helper function - JSDoc intentionally omitted
        function initDragDropListener(){
            $( ".pane_dropable" ).droppable({
                accept: function(draggable){
                    return (draggable.parent().hasClass('ui-tabs-nav') && draggable.parent().children().length>2);
                },
                drop: function( event, ui ) {
                    if(isMouseoverTabControl(event)){ return false; }
                    let $pane_content_drop =  $(this); // Renamed
                    let $li = ui.draggable;
                    let content_id = $li.find('a').attr('href');
                    let $app_content_drop = $(content_id); // Renamed
                    let $src_tab_drop = $app_content_drop.parent(); // Renamed
                    let offset_drop = $pane_content_drop.offset(); // Renamed
                    let $tab_drop = appCreateTabControl($pane_content_drop, {appid: $app_content_drop.attr('widgetid'), content_id: content_id.substr(1) },
                        {dockable: true, dragable:true, resizable:true,
                            css:{top:event.pageY-offset_drop.top,left:event.pageX-offset_drop.left,height:200,width:200}});
                    appAdjustContainer();
                    $app_content_drop.appendTo( $tab_drop );
                    $li.remove();
                    $tab_drop = $tab_drop.tabs();
                    appInitFeatures('#'+$pane_content_drop.attr('id'));
                    $src_tab_drop.tabs( 'refresh' );
                    $pane_content_drop.find('.tab_ctrl').css('z-index', '0');
                    $tab_drop.css('z-index', '1');
                }
            });
        }

        // Internal helper function - JSDoc intentionally omitted
        function appCreatePanel($pane_content_panel, app_panel, needcontent_panel){ // Renamed params
            app_counter++;
            let $d_panel; // Renamed
            if(!_is_container_layout ){
                $d_panel = $(document.createElement('div'));
                $d_panel.attr('id', 'pnl_'+app_counter).appendTo($pane_content_panel);
                if(app_panel.dragable){ $d_panel.addClass('dragable'); }
                if(app_panel.resizable){ $d_panel.addClass('resizable'); }
            }else{ $d_panel = $pane_content_panel; }
            if(needcontent_panel){
                let application_panel = _appGetWidgetById(app_panel.appid); // Renamed
                if(app_panel.hasheader){
                    let $header = $("<div>");
                    $header.append(window.hWin.HR(app_panel.name || application_panel.name))
                    .addClass('ui-corner-all').addClass('header'+app_panel.appid+'_'+app_counter)
                    .appendTo($d_panel);
                }
                appAddContent($d_panel, application_panel, app_panel);
                $d_panel.addClass('ui-widget-content');
            }
            if(app_panel.css){ $.each(app_panel.css, function(key, value){ $d_panel.css(key, value); });}
            else if(!_is_container_layout ) {
                if(app_panel.resizable) { $d_panel.css('width', '98%'); $d_panel.css('height', '98%'); }
                else { $d_panel.css('width', '99.999%'); $d_panel.css('height', '99.999%'); }
            }
            return $d_panel;
        }

        // Internal helper function - JSDoc intentionally omitted
        function appAddContent($app_container_add, app_add, appcfg_add){ // Renamed params
            let options_add = appcfg_add.options; // Renamed
            let layout_id_add = appcfg_add.layout_id; // Renamed
            const app_css_add = appcfg_add.css; // Renamed
            let $content_add = $(document.createElement('div')); // Renamed
            $content_add.attr('id', app_add.id+'_'+app_counter)
            .attr('widgetid', app_add.id).appendTo($app_container_add);
            if(layout_id_add){ $content_add.attr('layout_id', layout_id_add); }
            if(app_add.isframe){
                $content_add.addClass('frame_container');
                $content_add.append('<iframe id="'+app_add.id+'_'+app_counter+'" src="'+app_add.url+'"></iframe>');
            }else if(app_add.widgetname=='include_layout'){
                let layout_nested = _layoutGetById(options_add.ref); // Renamed
                if(layout_nested){
                    if(app_css_add){ $.each(app_css_add, function(key, value){ $content_add.css(key, value); });}
                    else if(app_add.resizable) { $content_add.css('width', '98%'); $content_add.css('height', '98%'); }
                    else { $content_add.css('width', '99%'); $content_add.css('height', '99%'); }
                    _initLayoutCardinal(layout_nested, $content_add); // Recursive call
                }
            }else if (app_add.script && app_add.widgetname) {
                app_add.widget = $content_add;
                if(window.hWin.HEURIST4.util.isFunction($('body')[app_add.widgetname])){
                    $content_add[app_add.widgetname]( options_add );
                }else{
                    $.getScript( window.hWin.HAPI4.baseURL + app_add.script, function() {
                        if(window.hWin.HEURIST4.util.isFunction($content_add[app_add.widgetname])){
                            $content_add[app_add.widgetname]( options_add );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: `Widget ${app_add.widgetname} not loaded. Verify your configuration`,
                                error_title: 'Widget loading failed',
                                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                            });
                        }
                    });
                }
            }else if (app_add.url2) { $content_add.load(app_add.url2); }
            else{ $content_add.html(app_add.content?app_add.content :app_add.name); }
        }

        // Internal helper function - JSDoc intentionally omitted
        function appCreateTabControl($pane_content_tab, apps_tab, tabcfg_tab){ // Renamed params
            if(!apps_tab) return null;
            if(! Array.isArray(apps_tab)){ apps_tab = [apps_tab]; }
            if(tabcfg_tab==null){ tabcfg_tab = apps_tab[0]; }
            let $tab_ctrl_create = appCreatePanel($pane_content_tab, tabcfg_tab, false); // Renamed
            $tab_ctrl_create.addClass('tab_ctrl').css('border', 'none');
            let $ul_create = $(document.createElement('ul')).appendTo($tab_ctrl_create); // Renamed
            if(tabcfg_tab) {
                if(tabcfg_tab.layout_id){ $tab_ctrl_create.attr('layout_id', tabcfg_tab.layout_id); }
                if(tabcfg_tab.keep_width){ $tab_ctrl_create.attr('data-keep-width', 1); }
                if(tabcfg_tab.dockable){ $tab_ctrl_create.addClass('dockable'); }
                if(tabcfg_tab.sortable){ $ul_create.addClass('sortable_tab_ul'); }
                if(tabcfg_tab.adjust_positions){ $tab_ctrl_create.addClass('tab_ctrl_adjust'); }
            }
            $.each(apps_tab, function(idx, _app_item){ // Renamed
                let app_detail = _appGetWidgetById(_app_item.appid); // Renamed
                if(app_detail){
                    let content_id_tab; // Renamed
                    if(_app_item.content_id){ content_id_tab = _app_item.content_id; }
                    else{ app_counter++; content_id_tab = app_detail.id+'_'+app_counter; }
                    let action_id_tab = ''; // Renamed
                    if(_app_item.options && _app_item.options['data-logaction']){
                        action_id_tab = ' data-logaction="'+_app_item.options['data-logaction']+'"';
                    }
                    let title_html = '<li'+action_id_tab+'><a class="header'+content_id_tab+'" href="#'+content_id_tab+'">'
                            + (window.hWin.HR(_app_item.name || app_detail.name)) +'</a></li>';
                    $ul_create.append($(title_html));
                    if(!_app_item.content_id){ appAddContent($tab_ctrl_create, app_detail, _app_item); }
                }
            });
            return $tab_ctrl_create;
        }

        // Internal helper function - JSDoc intentionally omitted
        function appAdjustContainer(){ } // Empty in original, kept as placeholder

        // Internal helper function - JSDoc intentionally omitted
        function appInitFeatures(containment_selector_feat){ // Renamed param
            $( ".tab_ctrl > .sortable_tab_ul" ).sortable({ cursor: "move", start: function(event, ui){ $('.tab_ctrl').css('z-index', '0'); $( this ).parent().css('z-index', '1'); } });
            $( ".tab_ctrl .dragable > .ui-tabs-nav li" ).draggable({ revert: "invalid", connectToSortable:'.sortable_tab_ul' });
            $( containment_selector_feat+" > .dockable > .ui-tabs-nav" ).droppable({
                accept: function(draggable){
                    if(draggable.parent().hasClass('ui-tabs-nav')){
                        let src_id = draggable.parent().parent().attr('id');
                        let trg_id = $( this ).parent().attr('id');
                        return src_id!=trg_id;
                    }else{ return false; }
                },
                activeClass: "ui-state-hover", hoverClass: "ui-state-active",
                drop: function( event, ui ) {
                    let $tab_drop_feat = $( this ).parent(); // Renamed
                    let $li_drop_feat = ui.draggable; // Renamed
                    let content_id_drop_feat = $li_drop_feat.find('a').attr('href'); // Renamed
                    let app_name_drop_feat = $li_drop_feat.find('a').html(); // Renamed
                    let $app_content_drop_feat = $(content_id_drop_feat); // Renamed
                    let $src_tab_drop_feat = $app_content_drop_feat.parent(); // Renamed
                    if($src_tab_drop_feat.attr('id')==$tab_drop_feat.attr('id')) return;
                    $tab_drop_feat.find( ".ui-tabs-nav" ).append("<li><a href='"+content_id_drop_feat+"'>"+app_name_drop_feat+"</a></li>");
                    $tab_drop_feat.append($app_content_drop_feat);
                    if($li_drop_feat.parent().children().length==2){ $src_tab_drop_feat.remove(); appAdjustContainer(); }
                    else{ $li_drop_feat.remove(); $src_tab_drop_feat.tabs( 'refresh' ); }
                    $tab_drop_feat.tabs( 'refresh' );
                    $tab_drop_feat.parent().find('.tab_ctrl').css('z-index', '0');
                    $tab_drop_feat.css('z-index', '1');
                }
            });
            $( containment_selector_feat+' > .dragable' ).draggable({ stack: '.tab_ctrl', handle: '.sortable_tab_ul', containment: containment_selector_feat });
            $( '.tab_ctrl ul' ).disableSelection();
            $(containment_selector_feat+' > .resizable').resizable({ animate: false, minWidth: grid_min_size, minHeight: grid_min_size, containment: containment_selector_feat, autoHide: true });
        }

        // Internal helper function - JSDoc intentionally omitted
        function isMouseoverTabControl(e){
            let res_mouse = false; // Renamed
            $(".tab_ctrl").each( function(ids, element){
                let $item_mouse = $(element); // Renamed
                let position_mouse = $item_mouse.offset(); // Renamed
                if(e.pageX>position_mouse.left && e.pageX<position_mouse.left+$item_mouse.width() &&
                    e.pageY>position_mouse.top && e.pageY<position_mouse.top+$item_mouse.height()){
                    res_mouse = true; return false;
                }
            });
            return res_mouse;
        }

        let layout_obj = null; // Renamed
        if($.isPlainObject(layoutid) && layoutid['type'] &&  layoutid['id']){
            layout_obj = layoutid;
            layoutid = layout_obj['id'];
        }else{
            layout_obj = _layoutGetById(layoutid);
        }
        if(layout_obj==null){
            window.hWin.HEURIST4.msg.redirectToError('Layout ID:'+layoutid+' was not found. Verify your layout_default.js');
            if(layoutid!='H5Default') layout_obj = _layoutGetById('H5Default');
            if(layout_obj==null){ return; }
        }
        if(!window.hWin.HEURIST4.util.isempty(layout_obj.cssfile)){
            if(!Array.isArray(layout_obj.cssfile)){ layout_obj.cssfile = [layout_obj.cssfile]; }
            for (let idx in layout_obj.cssfile){
                $("head").append($('<link rel="stylesheet" type="text/css" href="'+layout_obj.cssfile[idx]+'?t='+(new Date().getTime())+'">'));
            }
            layout_obj.cssfile = null;
        }
        if(window.hWin.HEURIST4.util.isempty(layout_obj.type) || layout_obj.type=='cardinal'){
            $container.empty();
            _initLayoutCardinal(layout_obj, $container);
        }else {
            _initLayoutFree(layout_obj, $container);
            let tabb = $container.find('div[layout_id="main_header_tab"]');
            if(tabb.length>0){
                $(tabb).tabs({activate: function( event, ui ) { 
                        $(window).trigger('resize'); 
                        $(ui.newTab[0]).css({'z-index': ui.newTab.attr('data-zmax'), 'background-size': 'cover', 'background-repeat': 'no-repeat'});
                        $(ui.oldTab[0]).css({'z-index': ui.newTab.attr('data-zkeep'), 'background-size': 'cover', 'background-repeat': 'no-repeat'});
                }});
                let tabheader = $(tabb).children('ul');
                tabheader.css({'border':'none', 'background':'#8ea9b9'});
                $(tabb).children('.ui-tabs-panel[layout_id!="FAP"]').css({position:'absolute', top:'5.01em', left:0,bottom:'0.2em',right:0, 'min-width':'75em',overflow:'hidden'});
                tabheader.find('a').css({'width':'100%','outline':'none'}); 
                let lis = tabheader.children('li');
                let count_lis = lis.length;
                lis.css({ 'outline':'none', 'border':'none', 'font-weight': 'bold', 'padding': '12px 20px 0 1px', 'margin': '12px 0px 0px -4px', 'z-index': 3, 'text-align': 'center', 'width': '200px', 'height': (navigator.userAgent.indexOf('Firefox')<0)?'33px':'45px' });
                lis.each(function(idx,item){
                   if(idx == lis.length-1) $(item).css({width:'300px'});
                   $(item).css({'z-index': count_lis - idx});
                   $(item).attr('data-zkeep', count_lis - idx);
                   $(item).attr('data-zmax', count_lis+1);
                   if(idx>0){ $(item).css({'margin-left':'-12px', 'border-left':'none'}); }
                   if(idx==1){
                       $(item).hide();
                       $('<span class="ui-icon ui-icon-close" title="Close this tab" style="font-size: 16px;width:24px;height:24px;position:absolute;right:10;top:20;z-index:2;cursor:pointer"></span>')
                       .on('click', function(){ $(item).hide(); if($(tabb).tabs("option", "active")==1) $(tabb).tabs({active:0}); }).appendTo($(item));
                   }
                });
                tabheader.parent().css({ 'background': '#8ea9b9' });
                $('#content_center_pane').find('#pnl_2').css({position:'absolute', top:0, left:0,bottom:0,right:0,width:'',height:''});
            }
        }
    }//END _appInitAll   
 
    //public members
    let that = {
        /**
         * Gets the class name of this HLayout instance.
         * @returns {string} The class name "HLayout".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the provided string matches the class name "HLayout".
         * @param {string} strClass - The class name to compare.
         * @returns {boolean} True if `strClass` is "HLayout", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of this HLayout instance.
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},
        /**
         * Retrieves the configuration for a widget by its ID.
         * @param {string} id - The ID of the widget to find.
         * @returns {Object|null} The widget configuration object if found, otherwise null.
         */
        appGetWidgetById: function(id){ // Corrected JSDoc based on usage
            return _appGetWidgetById(id);
        },
        /**
         * Retrieves a widget instance or related component by its widget name.
         * Special handling for 'svs_list' to get it from 'slidersMenu'.
         * @param {string} widgetname - The name of the widget (e.g., 'slidersMenu', 'resultList').
         * @returns {jQuery|Object|null} The jQuery widget instance or relevant object, or null if not found/initialized.
         */
        getWidgetByName: function( widgetname ){
            if(widgetname=='svs_list'){
                let app2 = _appGetWidgetByName('slidersMenu');
                if(app2.widget){
                    return $(app2.widget).slidersMenu('getSvsList');
                }
            }  
            let app = _appGetWidgetByName( widgetname );
            if(app && app.widget){
                return $(app.widget);
            }else{
                return null;
            }
        },
        /**
         * Executes a method on a specified widget instance.
         * @param {string} element_id - The DOM ID of the element the widget is attached to.
         * @param {string} widgetname - The name of the jQuery UI widget (e.g., 'slidersMenu').
         * @param {string} method - The name of the method to call on the widget.
         * @param {*} [params] - Parameters to pass to the widget method.
         * @returns {void}
         */
        executeWidgetMethod: function( element_id, widgetname, method, params ){
            let app = window.hWin.document.getElementById(element_id);
            if(app && window.hWin.HEURIST4.util.isFunction($(app)[widgetname]) && $(app)[widgetname]('instance')){
                $(app)[widgetname](method, params);
            }else if(!app){
                console.log('widget '+element_id+' not found');
            }else if(!window.hWin.HEURIST4.util.isFunction($(app)[widgetname])){
                console.log('widget '+widgetname+' not loaded');
            }
        },
        /**
        * Initializes the layout defined by `layoutid` within the container specified by `containerid`.
        * This is a primary entry point for setting up layouts.
        * @param {string|Object} layoutid - The ID of the layout to load (from `cfg_layouts`) or a layout configuration object.
        * @param {string} containerid - The DOM ID of the container element for the layout.
        * @returns {void}
        */
        appInitAll: function(layoutid, containerid){
            _containerid = containerid;
            let $container = $(containerid);
            _appInitAll(layoutid, $container);
        },
        /**
        * Initializes a layout from HTML element attributes within a specified container.
        * Widgets are defined using `data-heurist-app-id` and options in `data-heurist-app-options` or element content.
        * @param {Document} [document_context] - The document context to search for the container. Defaults to current document.
        * @param {string} containerid - The DOM ID of the container element.
        * @param {Object} [supp_options] - Supplementary options to extend widget configurations.
        * @param {function(): void} [onInitComplete] - Callback executed after layout initialization.
        * @returns {void}
        */
        appInitFromContainer: function( document_context, containerid_param, supp_options, onInitComplete ){ // Renamed param
            _containerid = containerid_param;
            _is_container_layout = true;
            let $container_init; // Renamed
            if(document_context){
                $container_init = $(document_context.body).find(containerid_param);
            }else{
                $container_init = $(containerid_param);
            }
            let layout_init = _getLayoutParams($container_init, supp_options);  // Renamed
            if(layout_init){
                _appInitAll(layout_init, $container_init);
            }else{
                if(window.hWin.HEURIST4.util.isFunction(onInitComplete)){
                        onInitComplete.call();
                }
            }
            _defineMediaSource($container_init);
        },
        /**
         * Initializes a layout based on HTML attributes within a given jQuery container.
         * Similar to `appInitFromContainer` but directly takes a jQuery object.
         * @param {jQuery} $container - The jQuery container element with widget configurations in attributes.
         * @param {Object} [supp_options] - Supplementary options to extend widget configurations.
         * @returns {void}
         */
        appInitFromContainer2: function( $container, supp_options ){
            let layout_init2 = _getLayoutParams($container, supp_options); // Renamed
            if(layout_init2){
                _appInitAll(layout_init2, $container);
            }
            _defineMediaSource($container); 
        },
        /**
        * Initializes the HLayout instance with widget and layout configurations.
        * Typically called with global `cfg_widgets` and `cfg_layouts`.
        * @param {Array<Object>} cfg_widgets_param - Array of widget configurations.
        * @param {Array<Object>} cfg_layouts_param - Array of layout definitions.
        * @returns {void}
        */
        init: function(cfg_widgets_param, cfg_layouts_param){ // Renamed params
            _init(cfg_widgets_param, cfg_layouts_param)
        },
        /**
         * Controls a pane within a cardinal layout (e.g., open, close, resize).
         * @param {string} action - The action to perform ('open', 'close', 'getSize', 'sizePane').
         * @param {string|Array} args - For 'open'/'close', the pane name (e.g., 'west').
         *                             For 'getSize', `[paneName, property]` (e.g., ['center', 'outerWidth']).
         *                             For 'sizePane', `[paneName, size]` (e.g., ['east', 300]).
         * @param {HTMLElement|jQuery} [element] - Optional element within the layout to help locate the target cardinal layout container.
         *                                        If not provided, uses the main layout container.
         * @returns {*|boolean} The result of the action (e.g., size for 'getSize'), or false if the action is not returning a value or fails.
         */
        cardinalPanel:function(action_param, args_param, element_param){ // Renamed params
            return _cardinalPanel(action_param, args_param, element_param);
        },
    }
    _init(cfg_widgets, cfg_layouts); // Initialize with global configs
    return that;
}
