/**
*  appInitAll - main function which initialises everything
* 
*  to be replaced with HLayoutMgr
*
*  @see ext/layout
*  @see layout_defaults.js - configuration file
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global cfg_widgets, cfg_layouts */
//
// 
//
function HLayout(args) {
     const _className = "HLayout",
         _version   = "0.4";      

    let  widgets = [],
         layouts = [],  //json description of layout
         _containerid,
         _is_container_layout = false;
         
    /**
    * Initialization
    */
    function _init( cfg_widgets, cfg_layouts ) {
         widgets = cfg_widgets ?cfg_widgets :[]; 
         layouts = cfg_layouts ?cfg_layouts :[];
    }

    
    function _appGetWidgetByName( widgetname ){
        
        let i;
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

        for(let i=0; i<widgets.length; i++){
            if(widgets[i].id==id){
                return widgets[i];
            }
        }
        return null;
    }
    
    //
    // action: close, open
    // args - [pane, values] 
    //
    function _cardinalPanel(action, args, element){

        let $container = null;
        
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
    
    /**
    *  Creates "free" layout from html elements attributes heurist-app-id and heurist-app-options
    *  supp_options - additional options that cannot be set via configuration in html - such as event listeners
    * 
    *  
    */
    function _getLayoutParams($container, supp_options){
        
        let eles = $container.find('div[data-heurist-app-id]');
        
        let layout = {id:'Dynamic', type:'free'};
        
        let is_layout = false;
        
        for(let i=0; i<eles.length; i++){
            let ele = $(eles[i]);
            let app_id = ele.attr('data-heurist-app-id');
            if(_appGetWidgetById(app_id)!=null){ //is defined in layout_default.cfg_widgets

                let cfgele = ele.find('span.widget-options:first'); //old version
                if(cfgele.length==0) cfgele = ele;
                // in new version config is in body
                let opts = window.hWin.HEURIST4.util.isJSON(cfgele.text());
                
                //extend options with supplimentary ones
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
    
    
    //
    // define src attribute for img, source and embed elements 
    // file id will be taken from data-id attribute
    //
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
    
    /**
    * Finds layout by id
    *
    * @param id
    */
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
    let grid_min_size = 200;
    let app_counter = 0; //to maintain unique id for panels and tabs

    //
    // north-west-east-south layout
    //
    function _initLayoutCardinal(layout, $container){
        
        let layout_opts =  {
            applyDefaultStyles: true,
            maskContents:       true,  //alows resize over iframe
            //togglerContent_open:    '<div class="ui-icon"></div>',
            //togglerContent_closed:  '<div class="ui-icon"></div>',
            //togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
            //togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-e"></div>',
            togglerContent_open:    '&nbsp;',
            togglerContent_closed:  '&nbsp;',
            //togglerLength_open: 21, 
            west:{
              spacing_open:6,
              spacing_closed:40,  
              togglerAlign_open:'center',
              togglerAlign_closed:'top',
              //togglerAlign_closed:16,   //top position   
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
                  let tog = $container.find('.ui-layout-toggler-west');
                  tog.removeClass('prominent-cardinal-toggler');
              },
              onclose_end : function(){ 
                   let tog = $container.find('.ui-layout-toggler-west');
                   tog.addClass('prominent-cardinal-toggler');
              }
            },
            east:{
              spacing_open:6,
              spacing_closed:40,  
              togglerAlign_open:'center',
              togglerAlign_closed:'top',
              //togglerAlign_closed:16,   //top position   
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
                  let tog = $container.find('.ui-layout-toggler-east');
                  tog.removeClass('prominent-cardinal-toggler togglerVertical');
                  tog.find('.heurist-helper2.eastTogglerVertical').remove();
              },
              onclose_end : function(){ 
                   let tog = $container.find('.ui-layout-toggler-east');
                   tog.addClass('prominent-cardinal-toggler togglerVertical');
                   $('<span class="heurist-helper2 eastTogglerVertical" style="width:175px;">Visualisation</span>').appendTo(tog);
              }
            },
            tips: {
                Close:                "Click to minimise panel",
                Resize:               "Drag to resize panel"
            },
            onresize_end: function(){
                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE); //global app event
                onLayoutResize( $container );
            }
            /*,
            onopen_end: function(pane_name, pane_element){
                if(pane_name=='west'){
                    var tog = pane_element.parent().find('.ui-layout-toggler-west');
                   
                   
                   
                   
                   
                    
                }
            },
            onclose_end: function(pane_name, pane_element){
                if(pane_name=='west'){
                }
            }*/
        };

        // 1) create panes (see ext/layout)
        let $pane = $(document.createElement('div'));
        $pane.addClass('ui-layout-center')
        .appendTo($container);

        if(!layout.center){
            layout_opts.center__minWidth = 200;
           
            layout['center'].dropable = true;
        }else{
            if(layout.center.minsize){
                layout_opts.center__minWidth = layout.center.minsize;
            }
        }

        function __layoutAddPane(pos){
            if(layout[pos]){

                let lpane = layout[pos];

                $pane = $(document.createElement('div'));
                $pane.addClass('ui-layout-'+pos)
                .appendTo($container);

                if(lpane.size){
                    layout_opts[pos+'__size'] = lpane.size;
                }
                if(window.hWin.HEURIST4.util.isnull(lpane.resizable) || lpane.resizable ){
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

        __toogleIcons($container, 'west', 'e', 'w');
        __toogleIcons($container, 'east', 'w', 'e');
        
        // 3) add tabs/apps to panes

        let bg_color = window.hWin.HEURIST4.util.getCSS('background-color', 'ui-widget-content');
       
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
       
    }

    //
    //
    //
    function __toogleIcons($container, pane, closed, opened){
        let tog = $container.find('.ui-layout-toggler-'+pane);
       
        
        let togc = tog.find('.content-closed'); togc.empty();
        $('<div>').addClass('ui-icon ui-icon-carat-2-'+closed).appendTo(togc);
        
        togc = tog.find('.content-open'); togc.empty();
        $('<div>').addClass('ui-icon ui-icon-triangle-1-'+opened).appendTo(togc);
    }
    
    /**
    * put your comment there...
    *
    * @param layout
    * @param $container
    */
    function _initLayoutFree(layout, $container){

       

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
        let panes = Object.keys(layout);
        let i, reserved = ['id', 'name', 'theme', 'type', 'options', 'cssfile', 'template'];

        //
        // pos - element id and index in layout (cfg) array
        //
        function __layoutAddPane($container, pos){
            
            if(layout[pos]){

                let lpane = layout[pos];

                let ele = $container.find('#'+pos);
                //this div may already exists
                if(ele.length<1){
                    //does not exist - add new one
                    let $pane = $('<div>',{id:pos})
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

        $('body').css('background-color', '');
        
        for (i=0; i<panes.length; i++){
            if(reserved.indexOf(panes[i])<0){
                 __layoutAddPane($container, panes[i]);
                 layoutInitPane(layout, $container, panes[i], null);//init widget
            }
        }

        initDragDropListener();
        
       
    }

    
    /**
    * Initializes content (applications/widgets) within a specific pane of a layout.
    * This function is called by both `_initLayoutCardinal` and `_initLayoutFree`.
    * It handles different types of content:
    * - Special Heurist widgets like `heurist_Groups` (which can render as tabs, accordion, or divs)
    *   and `heurist_Cardinals` (for nested cardinal layouts).
    * - Standard tabbed content where multiple apps are placed in a jQuery UI Tabs container.
    * - Standalone applications/widgets directly placed in the pane.
    *
    * @private
    * @param {Object} layout_config - The overall layout configuration object (either the main layout or a nested one).
    * @param {jQuery} $parent_container - The jQuery object for the parent container of the pane being initialized
    *                                  (this is the container on which `.layout()` was called for cardinal, or the main `$container` for free).
    * @param {string} pane_key - The key/identifier of the pane to initialize (e.g., 'north', 'center', or an ID from a free layout).
    * @param {string|null} background_color - Optional background color to apply to content areas within cardinal panes.
    * @returns {void}
    */
    function layoutInitPane(layout_config, $parent_container, pane_key, background_color){

            if(layout_config[pane_key]){ // Check if pane configuration exists
                const pane_definition = layout_config[pane_key];
                // Find the pane element. For cardinal, it's '.ui-layout-<pane_key>'. For free, it's '#<pane_key>'.
                let $pane_element = $parent_container.find((['north','east','west','center','south'].includes(pane_key)) ? '.ui-layout-' + pane_key : '#' + pane_key);
                let $actual_content_container = $pane_element; // By default, content goes directly into the pane element

                // For cardinal panes, create a specific '.ui-layout-content' div inside the pane if not already there.
                // This is standard practice for jQuery UI Layout.
                if (['north','east','west','center','south'].includes(pane_key)) {
                    if ($pane_element.find('> .ui-layout-content').length === 0) { // Avoid creating if one already exists (e.g. from template)
                        $actual_content_container = $('<div>')
                            .attr('id','content_' + pane_key) // Give it a unique ID
                            .addClass('ui-layout-content')    // Standard class for layout content
                            .appendTo($pane_element);
                    } else {
                        $actual_content_container = $pane_element.find('> .ui-layout-content:first');
                    }
                    if(background_color) $actual_content_container.css('background-color', background_color);
                    if(pane_definition.dropable) $actual_content_container.addClass('pane_dropable'); // Make it a drop target for tabs
                }
                
                // Handle specific complex widget types first, as they might have unique rendering logic
                if (pane_definition.apps && pane_definition.apps.length > 0) {
                    const first_app_config = pane_definition.apps[0];
                    if (first_app_config.appid === 'heurist_Groups') {
                        // Special rendering for 'heurist_Groups' widget (can be tabs, accordion, or simple divs)
                        $pane_element.find('div.widget-design-header:first, span.widget-options:first').remove(); // Clean up design-mode artifacts
                        const groupWidgetMode = first_app_config.options.groups_mode;
                        if(groupWidgetMode !== 'tabs'){ // Accordion or divs mode
                            const paneId = $pane_element.attr('id');
                            $pane_element.children('ul').remove(); // Remove any pre-existing UL (for tabs)
                            $pane_element.children('.ui-tabs-panel').each(function(idx,item){ // Repurpose tab panels
                                if(idx < first_app_config.options.tabs.length){ // Max items from config
                                    if(groupWidgetMode !== 'divs') $('<h3>').html(first_app_config.options.tabs[idx].title).appendTo($pane_element); // Header for accordion
                                    $('<div>').html($(item).html()).addClass('group-tab').attr('id',(paneId + '-' + idx)).appendTo($pane_element);
                                    if(groupWidgetMode === 'divs' && idx > 0) $pane_element.children().last().hide(); // Hide subsequent divs if mode is 'divs'
                                }
                                $(item).remove(); // Remove original panel
                            });
                            if(groupWidgetMode === 'accordion') $pane_element.accordion({ heightStyle: "content", collapsible: true, active: false });
                        } else { // Default to jQuery UI tabs for 'heurist_Groups'
                            $pane_element.tabs();
                        }
                        return; // Initialization for heurist_Groups is complete
                    } else if (first_app_config.appid === 'heurist_Cardinals') {
                        // Special rendering for nested 'heurist_Cardinals' widget
                        $pane_element.find('div.widget-design-header:first, span.widget-options:first').remove();
                        if(!first_app_config.options || !first_app_config.options.tabs){
                            console.error('Cardinal layout widget is missing "tabs" options defining its panes.'); return;
                        }
                        const nested_layout_opts_from_config = { ...first_app_config.options.tabs }; // Copy pane definitions
                        let $cardinal_widget_container = first_app_config.options.container ? $('#'+first_app_config.options.container) : $pane_element;
                        
                        // Prepare child elements to be layout panes by adding classes
                        Object.keys(nested_layout_opts_from_config).forEach(key => {
                            if (typeof nested_layout_opts_from_config[key] === 'object' && nested_layout_opts_from_config[key].id) {
                                $cardinal_widget_container.children('#'+nested_layout_opts_from_config[key].id).addClass('ui-layout-'+key);
                            }
                        });
                        // Standard jQuery UI Layout options for the nested layout
                        const final_nested_layout_opts = $.extend({}, nested_layout_opts_from_config, {
                            applyDefaultStyles: true, maskContents: true, togglerAlign_open: 'center',
                            togglerContent_open: '&nbsp;', togglerContent_closed: '&nbsp;',
                            spacing_open: 6, spacing_closed: 16,
                            onresize_end: function(){ $(document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE); }
                        });

                        $cardinal_widget_container.layout(final_nested_layout_opts);
                        __toogleIcons($cardinal_widget_container, 'west', 'e', 'w');
                        __toogleIcons($cardinal_widget_container, 'east', 'w', 'e');
                        // If the cardinal widget uses a different container than the pane it's in, hide the original pane element.
                        if($cardinal_widget_container.attr('id') !== $pane_element.attr('id')) $pane_element.hide();
                        return; // Initialization for heurist_Cardinals is complete
                    } else {
                         // If not a special widget, clear the target content area for cardinal panes.
                         // For free layouts, $actual_content_container is $pane_element, which might already have content from a template.
                         if (['north','east','west','center','south'].includes(pane_key)) {
                            $actual_content_container.empty();
                         }
                    }
                } else if (['north','east','west','center','south'].includes(pane_key)) {
                     // If no apps defined for a cardinal pane, ensure its content area is empty.
                     $actual_content_container.empty();
                }

                // Initialize tabs if defined for the pane
                if(pane_definition.tabs){
                    $.each(pane_definition.tabs, function(idx, tab_config){
                        appCreateTabControl($actual_content_container, tab_config.apps, tab_config);
                    });
                }

                // Initialize standalone apps if defined for the pane
                if(pane_definition.apps){ // Re-check as special widgets above would have returned
                    $.each(pane_definition.apps, function(idx, app_config){
                        if(app_config.dockable){ // Dockable apps are rendered in a tab control
                            appCreateTabControl($actual_content_container, app_config, null);
                        }else{ // Non-dockable apps are rendered in a simple panel
                            appCreatePanel($actual_content_container, app_config, true);
                        }
                    });
                }

                // Initialize jQuery UI Tabs for all tab controls within this pane's content area
                const containment_selector_for_tabs = '#' + $actual_content_container.attr('id');
                const $tab_widgets = $actual_content_container.find('.tab_ctrl').tabs({
                    activate: function(event ,ui){
                        let action_id = $(ui.newTab[0]).attr('data-logaction');
                        if(action_id && window.hWin && window.hWin.HAPI4){
                            window.hWin.HAPI4.SystemMgr.user_log(action_id); // Log tab activation
                        }
                        // Logic for adjusting pane width based on active tab (data-keep-width)
                        if($(this).attr('data-keep-width') === "1" && ui.oldTab && ui.newTab){ // Check if attributes exist
                            let $layoutPane = $(ui.oldTab[0]).closest('.ui-layout-pane');
                            if ($layoutPane.length) {
                                $(ui.oldTab[0]).attr('data-width', $layoutPane.width());
                                let newWidth = $(ui.newTab[0]).attr('data-width');
                                if(newWidth && parseInt(newWidth) > 0){
                                   // Assuming HAPI4.LayoutMgr.cardinalPanel can target a specific layout instance if $parent_container is passed
                                   window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', [$layoutPane.data('layoutEdge'), parseInt(newWidth)], $parent_container);
                                }
                            }
                        }
                    }
                });
                // Special keydown handling for 'east' pane tabs to prevent arrow key navigation conflicts
                if(pane_key === 'east'){
                    $tab_widgets.find('.ui-tabs-anchor').on('keydown',function(e){ e.stopPropagation(); e.preventDefault(); return false; });
                }
                appInitFeatures(containment_selector_for_tabs); // Initialize drag/drop/resize for elements in these tabs
            }
    } //end layoutInitPane

    /**
     * Adjusts the position of tab panels within tab controls that have the class `tab_ctrl_adjust`.
     * This is typically called on layout resize to ensure tab content fits correctly beneath the tab headers.
     * @private
     * @param {jQuery} $layout_container - The main layout container to search within for adjustable tab controls.
     * @returns {void}
     */
    function onLayoutResize( $layout_container ){
        let $tab_controls_to_adjust = $layout_container.find('.tab_ctrl_adjust' );
        
        $tab_controls_to_adjust.each(function(idx, tabctrl_element){
            const $tabctrl = $(tabctrl_element);
            let $tablist = $tabctrl.find('ul[role="tablist"]:first');
            let tablist_height = $tablist.length ? $tablist.outerHeight() : 0;
            // Adjust top position of tab panels based on header height
            $tabctrl.find('div[role="tabpanel"]').css({
                'top': (tablist_height + 4) + 'px', // +4 for potential padding/border
                'bottom':'0px', 'position':'absolute', left:'2px', right:'4px'
            });
        });
    }
    
    /**
     * Initializes drag-and-drop functionality for tabs between different tab controls
     * within panes marked as `pane_dropable`. Enables interactive rearrangement of tabs.
     * @private
     * @returns {void}
     */
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

                let $pane_content =  $(this);

                let $li = ui.draggable;
                //find portlet (content of tab) by href
                let content_id = $li.find('a').attr('href');
                let $app_content = $(content_id);

                let $src_tab = $app_content.parent();

                let offset = $pane_content.offset();
                let $tab = appCreateTabControl($pane_content, {appid: $app_content.attr('widgetid'), content_id: content_id.substr(1) }, //to remove #
                    {dockable: true, dragable:true, resizable:true,
                        css:{top:event.pageY-offset.top,left:event.pageX-offset.left,height:200,width:200}});
                appAdjustContainer();
                $app_content.appendTo( $tab );

                //remove from source
                $li.remove();

                $tab = $tab.tabs();
                appInitFeatures('#'+$pane_content.attr('id'));

                $src_tab.tabs( 'refresh' );

                $pane_content.find('.tab_ctrl').css('z-index', '0');
                $tab.css('z-index', '1');

            }
        });
       
    }


    /**
    * Creates a simple panel for a single application/widget.
    * The panel can be draggable and/or resizable if specified in the `app_config`.
    *
    * @private
    * @param {jQuery} $pane_container - The container element (a layout pane) where this panel will be created.
    * @param {Object} app_config - Configuration for the app/widget from the layout definition.
    *                              Properties used: `appid`, `name` (fallback to widget's default), `dragable`, `resizable`, `hasheader`, `css`.
    * @param {boolean} create_content_now - If true, loads and creates the widget/content immediately.
    * @returns {jQuery} The jQuery object for the created panel's main div.
    */
    function appCreatePanel($pane_container, app_config, create_content_now){
        app_counter++;
        let $panel_div;
        
        // If not a container-based layout (i.e., free-form where panel is distinct), create a new div.
        // Otherwise, the $pane_container itself is treated as the panel div.
        if(!_is_container_layout){
            $panel_div = $('<div>')
                .attr('id', 'pnl_' + app_counter)
                .appendTo($pane_container);
            
            if(app_config.dragable) $panel_div.addClass('dragable');
            if(app_config.resizable) $panel_div.addClass('resizable');
        } else {
            $panel_div = $pane_container;
        }
        
        if(create_content_now){
            const widget_definition = _appGetWidgetById(app_config.appid); // Get base widget definition
            if (!widget_definition) {
                console.error("Widget definition not found for appid:", app_config.appid);
                return $panel_div; // Return potentially empty panel
            }

            if(app_config.hasheader){ // Add a header bar to the panel if specified
                $('<div>')
                    .append(window.hWin.HR(app_config.name || widget_definition.name)) // Use configured name or widget default name
                    .addClass('ui-corner-all header' + app_config.appid + '_' + app_counter) // Basic styling
                    .appendTo($panel_div);
            }
            // Add the actual content (widget or URL) to the panel
            appAddContent($panel_div, widget_definition, app_config);
            $panel_div.addClass('ui-widget-content'); // Standard jQuery UI class for content areas
        }

        // Apply custom CSS from layout configuration
        if(app_config.css){
            $panel_div.css(app_config.css);
        } else if(!_is_container_layout) { // Default sizing for non-container layouts if no CSS
            if(app_config.resizable) {
                $panel_div.css({'width': '98%', 'height': '98%'});
            } else {
                $panel_div.css({'width': '99.999%', 'height': '99.999%'}); // Fill container
            }
        }
        return $panel_div;
    }

    /**
    * Creates and adds the actual content (a widget instance or loaded URL) to an application container div.
    *
    * @private
    * @param {jQuery} $app_container_div - The jQuery div element that will host the content.
    * @param {Object} widget_definition - The base configuration of the widget from `cfg_widgets` (contains `id`, `script`, `widgetname`, `url2`, `content`).
    * @param {Object} app_instance_config - Instance-specific configuration for this app from the layout definition (contains `options`, `layout_id`, `css`).
    * @returns {void}
    */
    function appAddContent($app_container_div, widget_definition, app_instance_config){
        const options = app_instance_config.options;
        const layout_id_attr = app_instance_config.layout_id; // Attribute for linking back to layout definition
        const app_css_styles = app_instance_config.css; // Instance-specific CSS

        // Create the content div that will actually hold the widget or loaded HTML
        const $content_div = $('<div>')
            .attr('id', widget_definition.id + '_' + app_counter) // Unique ID for the content holder
            .attr('widgetid', widget_definition.id) // Store base widget ID
            .appendTo($app_container_div);
        
        if(layout_id_attr) $content_div.attr('layout_id', layout_id_attr);
        
        if(widget_definition.isframe){ // Content is an iframe
            $content_div.addClass('frame_container');
            // Ensure URL is correctly formed if it's relative
            const frame_url = widget_definition.url.startsWith('http') ? widget_definition.url : window.hWin.HAPI4.baseURL + widget_definition.url;
            $content_div.append(`<iframe id="${widget_definition.id}_${app_counter}_frame" src="${frame_url}"></iframe>`);
        } else if(widget_definition.widgetname === 'include_layout'){ // Special widget to include another layout
            const nested_layout_id = options.ref; // ID of the layout to include
            const nested_layout_config = _layoutGetById(nested_layout_id);
            if(nested_layout_config){
                if(app_css_styles) $content_div.css(app_css_styles);
                else if(widget_definition.resizable) $content_div.css({'width': '98%', 'height': '98%'});
                else $content_div.css({'width': '99%', 'height': '99%'});
                
                _initLayoutCardinal(nested_layout_config, $content_div); // Initialize the nested layout
            } else {
                console.error("Nested layout not found:", nested_layout_id);
            }
        } else if (widget_definition.script && widget_definition.widgetname) { // Standard jQuery UI widget pattern
            // Ensure widget's JS is loaded, then initialize
            if(window.hWin.HEURIST4.util.isFunction($('body')[widget_definition.widgetname])){ // Check if plugin is loaded
                $content_div[widget_definition.widgetname](options); // Initialize widget
            } else { // Script not loaded, load it dynamically
                $.getScript( window.hWin.HAPI4.baseURL + widget_definition.script)
                    .done(function() {
                        if(window.hWin.HEURIST4.util.isFunction($content_div[widget_definition.widgetname])){
                            $content_div[widget_definition.widgetname](options);
                        } else {
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: `Widget ${widget_definition.widgetname} function not found after loading script ${widget_definition.script}.`,
                                error_title: 'Widget Loading Failed',
                                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                            });
                        }
                    })
                    .fail(function(jqxhr, settings, exception) {
                         console.error("Error loading widget script:", widget_definition.script, exception);
                         window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Failed to load script for widget ${widget_definition.widgetname} (${widget_definition.script}).`,
                            error_title: 'Script Loading Failed'
                         });
                    });
            }
        } else if (widget_definition.url2) { // Load content from a URL
            $content_div.load(widget_definition.url2);
        } else { // Static content defined in widget config
            $content_div.html(widget_definition.content ? widget_definition.content : widget_definition.name);
        }
    }

    /**
    * Creates a new tab control (jQuery UI Tabs) within a pane.
    * Each app in the `apps_list` array becomes a tab in this control.
    *
    * @private
    * @param {jQuery} $pane_container - The layout pane where the tab control will be created.
    * @param {Array<Object>|Object} apps_list - An array of app configurations for the tabs, or a single app config object.
    *                                         Each app config should have `appid` and optionally `name`, `options`, `content_id`.
    * @param {Object} [tab_control_config] - Configuration for the tab control itself (from layout definition).
    *                                   Used for properties like `layout_id`, `keep_width`, `dockable`, `sortable`, `adjust_positions`, `css`.
    *                                   If null, and `apps_list` is a single app, that app's config is used.
    * @returns {jQuery|null} The jQuery object for the created tab control div, or null if `apps_list` is empty.
    */
    function appCreateTabControl($pane_container, apps_list, tab_control_config){
        if(!apps_list) return null;

        const apps_array = Array.isArray(apps_list) ? apps_list : [apps_list];
        if(apps_array.length === 0) return null;

        const effective_tab_config = tab_control_config || apps_array[0]; // Use first app's config if no specific tab_control_config

        // Create the main div for the tab control panel
        const $tab_control_div = appCreatePanel($pane_container, effective_tab_config, false); // false: don't create content yet
        $tab_control_div.addClass('tab_ctrl').css('border', 'none'); // Basic tab control styling
        const $tab_nav_ul = $('<ul>').appendTo($tab_control_div); // UL for tab headers
        
        // Apply configurations to the tab control div
        if(effective_tab_config) {
            if(effective_tab_config.layout_id) $tab_control_div.attr('layout_id', effective_tab_config.layout_id);
            if(effective_tab_config.keep_width) $tab_control_div.attr('data-keep-width', 1);
            if(effective_tab_config.dockable) $tab_control_div.addClass('dockable');
            if(effective_tab_config.sortable) $tab_nav_ul.addClass('sortable_tab_ul');
            if(effective_tab_config.adjust_positions) $tab_control_div.addClass('tab_ctrl_adjust');
        }

        // Create each tab (header and panel placeholder)
        $.each(apps_array, function(idx, app_instance_config){
            const widget_definition = _appGetWidgetById(app_instance_config.appid); // Get base widget config
            if(widget_definition){
                let panel_content_id;
                if(app_instance_config.content_id){ // If content_id is provided (e.g., when moving existing tab)
                    panel_content_id = app_instance_config.content_id;
                } else { // Generate new ID for new tab panel
                    app_counter++;
                    panel_content_id = widget_definition.id + '_' + app_counter;
                }
                
                let action_log_attr = ''; // For logging tab activation
                if(app_instance_config.options && app_instance_config.options['data-logaction']){
                    action_log_attr = ` data-logaction="${app_instance_config.options['data-logaction']}"`;
                }

                // Tab header (li > a)
                const tab_title = window.hWin.HR(app_instance_config.name || widget_definition.name);
                $('<li>').attr('data-logaction', action_log_attr ? app_instance_config.options['data-logaction'] : null) // Store for event handler
                           .append($('<a>').addClass('header' + panel_content_id).attr('href', '#' + panel_content_id).html(tab_title))
                           .appendTo($tab_nav_ul);
                
                // If content_id was not provided, it means this is a new tab, so add its content panel
                if(!app_instance_config.content_id){
                    // appAddContent will create a div with id=panel_content_id inside $tab_control_div
                    appAddContent($tab_control_div, widget_definition, app_instance_config);
                }
            }
        });
        
        // Apply custom styling to tab header if specified
        if(effective_tab_config && effective_tab_config.style && effective_tab_config.style['background-header']){
            // Example: $tab_nav_ul.css('background', effective_tab_config.style['background-header']);
        }
        return $tab_control_div;
    }

    /**
    * Adjusts the width and height of a container, possibly to fit its floating tab children.
    * @private
    * @description The original implementation is commented out and seems to target a specific class "float_tabs"
    *              and container "#layout_float", which might be part of a specific layout type not fully detailed here.
    *              It appears to attempt to make a container large enough to encompass all its absolutely positioned children.
    *              Currently, this function does nothing as its logic is commented.
    */
    function appAdjustContainer(){
        /* // Original logic:
        var mw = 0, mh = 0;
        $(".float_tabs").each(function(ids, element){
            var $item = $(element); var pos = $item.position();
            mw = Math.max(mw, pos.left+$item.width());
            mh = Math.max(mh, pos.top+$item.height());
        });
        var $container = $("#layout_float");
        if(mw>$(window).width()){ $container.css('width', mw); } else { $container.css('width','100%'); }
        if(mh>$(window).height()){ $container.css('height', mh); } else { $container.css('height','100%'); }
        */
    }

    /**
    * Initializes jQuery UI features (sortable, draggable, resizable) for elements
    * within a specified containment selector. This enables interactive layout manipulation.
    * @private
    * @param {string} containment_selector - A jQuery selector for the container within which these features should be constrained.
    * @returns {void}
    */
    function appInitFeatures(containment_selector){
        // Make tab lists sortable within their tab control
        $( ".tab_ctrl > .sortable_tab_ul" ).sortable({
            cursor: "move",
            start: function(event, ui){ // Bring current tab control to front when sorting starts
                $(this).closest('.tab_ctrl').css('z-index', '1').siblings('.tab_ctrl').css('z-index', '0');
            }
        });

        // Make individual tabs draggable between sortable tab lists (if they have class .dragable)
        // Note: '.dragable' class seems to be on the tab_ctrl, not individual li. This might need review.
        // Assuming it means tabs within a "dragable" tab_ctrl can be moved.
        $( ".tab_ctrl.dragable > .ui-tabs-nav li" ).draggable({
            revert: "invalid", // Snap back if not dropped on a valid target
            connectToSortable:'.sortable_tab_ul' // Allow dropping onto any sortable tab list
        });

        // Make tab navigations (ul) within dockable tab controls droppable targets for tabs from other controls.
        $( containment_selector + " > .tab_ctrl.dockable > .ui-tabs-nav" ).droppable({
            accept: function($draggable_li){ // $draggable_li is the <li> being dragged
                if($draggable_li.parent().hasClass('ui-tabs-nav')){ // Must be a tab
                    const $source_tab_control = $draggable_li.closest('.tab_ctrl');
                    const $target_tab_control = $(this).closest('.tab_ctrl');
                    return $source_tab_control.attr('id') !== $target_tab_control.attr('id'); // Don't drop on self
                }
                return false;
            },
            activeClass: "ui-state-hover", // Visual feedback when draggable is over
            hoverClass: "ui-state-active",  // Visual feedback when draggable hovers
            drop: function( event, ui ) {
                const $target_tab_nav_ul = $(this); // The UL where tab is dropped
                const $target_tab_control = $target_tab_nav_ul.closest('.tab_ctrl');
                const $dragged_li = ui.draggable; // The dragged <li>

                const content_href = $dragged_li.find('a').attr('href');
                const tab_title_html = $dragged_li.find('a').html();
                const $tab_panel_content = $(content_href); // The content panel
                const $source_tab_control = $tab_panel_content.parent(); // Original tab control of the panel

                // Append tab header and panel to new tab control
                $('<li>').append($('<a>').attr('href', content_href).html(tab_title_html)).appendTo($target_tab_nav_ul);
                $target_tab_control.append($tab_panel_content);
                $dragged_li.remove(); // Remove from original source

                // If source tab control becomes empty (only placeholder/template tab left), remove it.
                // This logic assumes a tab control with 1 actual tab + 1 template/hidden tab = 2 children before removal.
                if($source_tab_control.find('.ui-tabs-nav li').length < 2){
                    $source_tab_control.remove();
                    // appAdjustContainer(); // Potentially adjust container if one is removed (original comment: @todo remove?)
                } else {
                    $source_tab_control.tabs('refresh');
                }
                $target_tab_control.tabs('refresh');

                // Z-index management
                $target_tab_control.siblings('.tab_ctrl').css('z-index', '0');
                $target_tab_control.css('z-index', '1');
            }
        });
        
        // Make "dragable" tab controls (panels) draggable within their containment area
        $( containment_selector + ' > .tab_ctrl.dragable' ).draggable({ // Ensure selector targets .tab_ctrl directly if it's .dragable
            stack: '.tab_ctrl',          // Bring to front on drag
            handle: '.ui-tabs-nav',      // Drag by the tab navigation bar
            containment: containment_selector
        });
        
        $( '.tab_ctrl ul' ).disableSelection(); // Prevent text selection in tab headers

        // Make "resizable" tab controls (panels) resizable
        $(containment_selector + ' > .tab_ctrl.resizable').resizable({ // Ensure selector targets .tab_ctrl
            animate: false,
            minWidth: grid_min_size,
            minHeight: grid_min_size,
            containment: containment_selector,
            autoHide: true // Hide resize handles when not hovering
        });
    }

    /**
     * Checks if the mouse event occurred over any existing tab control.
     * @private
     * @param {MouseEvent} e - The mouse event.
     * @returns {boolean} True if the mouse is over a tab control, false otherwise.
     */
    function isMouseoverTabControl(e){
        let is_over_tab = false;
        $(".tab_ctrl").each(function(){ // Iterate over all elements with class .tab_ctrl
            const $item = $(this);
            const position = $item.offset();
            const width = $item.width();
            const height = $item.height();

            if(e.pageX > position.left && e.pageX < (position.left + width) &&
               e.pageY > position.top && e.pageY < (position.top + height)){
                is_over_tab = true;
                return false; // Exit .each loop
            }
        });
        return is_over_tab;
    }

    //********************************************************** Main body of _appInitAll
    
        let layout = null;
        if($.isPlainObject(layoutid) && layoutid['type'] &&  layoutid['id']){
            layout = layoutid;
            layoutid = layout['id'];
        }else{
            layout = _layoutGetById(layoutid);
        }
    
        if(layout==null){
            window.hWin.HEURIST4.msg.redirectToError('Layout ID:'+layoutid+' was not found. Verify your layout_default.js');
            if(layoutid!='H5Default') layout = _layoutGetById('H5Default');
            if(layout==null){
                return;
            }
        }
        
        //add style to header
        if(!window.hWin.HEURIST4.util.isempty(layout.cssfile)){
            
            if(!Array.isArray(layout.cssfile)){
                layout.cssfile = [layout.cssfile];
            }
            for (let idx in layout.cssfile){
                $("head").append($('<link rel="stylesheet" type="text/css" href="'+layout.cssfile[idx]+'?t='+(new Date().getTime())+'">'));
            }
            layout.cssfile = null;
        }
        

        if(window.hWin.HEURIST4.util.isempty(layout.type) || layout.type=='cardinal'){
            
            $container.empty();
            _initLayoutCardinal(layout, $container);

        }else { //}if(layout.type=='free'){

            _initLayoutFree(layout, $container);

            
            //special styles case for default layout
            //@todo - definition of styles for tab control via layuot_default.js
            let tabb = $container.find('div[layout_id="main_header_tab"]');
            if(tabb.length>0){
                
                $(tabb).tabs({activate: function( event, ui ) { 
                        $(window).trigger('resize'); 
                        //change/restore z-index and background color
                        $(ui.newTab[0]).css({'z-index': ui.newTab.attr('data-zmax'),
                            'background-size': 'cover',
                            'background-repeat': 'no-repeat',
                        });
                        $(ui.oldTab[0]).css({'z-index': ui.newTab.attr('data-zkeep'),
                            'background-size': 'cover',
                            'background-repeat': 'no-repeat',
                        });   
                }});
                
                let tabheader = $(tabb).children('ul');
                tabheader.css({'border':'none', 'background':'#8ea9b9'});  //, 'padding-top':'1em'
                
                $(tabb).children('.ui-tabs-panel[layout_id!="FAP"]').css({position:'absolute', top:'5.01em',
                        left:0,bottom:'0.2em',right:0, 'min-width':'75em',overflow:'hidden'});
                
                tabheader.find('a').css({'width':'100%','outline':'none'}); 
                
                let lis = tabheader.children('li');
                let count_lis = lis.length;
                    lis.css({
                            'outline':'none',
                            'border':'none',
                            'font-weight': 'bold',
                            //A11 'font-size': '1.4em',
                            'padding': '12px 20px 0 1px',
                            'margin': '12px 0px 0px -4px',
                            'z-index': 3,
                            'text-align': 'center',
                            'width': '200px',
                            'height': (navigator.userAgent.indexOf('Firefox')<0)?'33px':'45px' });
                            
                lis.each(function(idx,item){
                    
                   if(idx == lis.length-1) $(item).css({width:'300px'});
                  
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
                       .on('click', function(){ 
                            $(item).hide(); 
                            if($(tabb).tabs("option", "active")==1) $(tabb).tabs({active:0}); 
                       })
                       .appendTo($(item));
                       
                      
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


    }//END _appInitAll   
 
    //public members
    /**
     * @lends HLayout.prototype
     * @description Public interface for the HLayout manager.
     */
    let that = {

        /**
         * Gets the class name.
         * @returns {string} The class name "HLayout".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the provided string matches the class name.
         * @param {string} strClass - The class name to compare.
         * @returns {boolean} True if `strClass` is "HLayout", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of this layout manager.
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},

        /**
         * Retrieves a widget configuration object by its ID.
         * Note: Original comment mentioned "WRONG USAGE, TO REMOVE: used to obtain instance of widget".
         * This method returns the configuration, not an instance.
         * @param {string} id - The ID of the widget to find.
         * @returns {Object|null} The widget configuration object from `cfg_widgets` or null if not found.
         */
        appGetWidgetById: function(id){
            return _appGetWidgetById(id);
        },
        
        /**
         * Retrieves a widget configuration by its `widgetname` or a widget instance if already initialized.
         * Special handling for 'svs_list' to get it from 'slidersMenu' widget.
         * @param {string} widgetname - The `widgetname` to search for.
         * @returns {Object|jQuery|null} The widget configuration object, or the jQuery widget instance if `app.widget` is set,
         *                                 or null if not found.
         */
        getWidgetByName: function( widgetname ){
            if(widgetname === 'svs_list'){ // Special case for 'svs_list'
                let slidersMenuApp = _appGetWidgetByName('slidersMenu');
                if(slidersMenuApp && slidersMenuApp.widget){ // If slidersMenu widget instance exists
                    // Assuming slidersMenu has a method to get 'svs_list'
                    return $(slidersMenuApp.widget).slidersMenu('getSvsList');
                }
            }  
            
            let app_config = _appGetWidgetByName( widgetname );
            if(app_config && app_config.widget){ // If widget instance is stored on the config
                return $(app_config.widget);
            } else if (app_config) { // Return configuration if instance not found
                return app_config;
            }
            return null;
        },

        /**
         * Executes a method on a widget instance identified by its element ID and widget name.
         * Used, for example, in map.php.
         * @param {string} element_id - The DOM ID of the element where the widget is initialized.
         * @param {string} widgetname - The name of the jQuery UI widget (e.g., "heurist_Map").
         * @param {string} method - The name of the method to call on the widget.
         * @param {*} [params] - Parameters to pass to the widget method.
         * @returns {void}
         */
        executeWidgetMethod: function( element_id, widgetname, method, params ){
            let app_element = window.hWin.document.getElementById(element_id);
            if(app_element && typeof $(app_element)[widgetname] === 'function' && $(app_element)[widgetname]('instance')){
                $(app_element)[widgetname](method, params);
            }else if(!app_element){
                console.warn('HLayout.executeWidgetMethod: Element with ID "'+element_id+'" not found.');
            }else if(typeof $(app_element)[widgetname] !== 'function'){
                console.warn('HLayout.executeWidgetMethod: Widget "'+widgetname+'" not found on element.');
            } else {
                 console.warn('HLayout.executeWidgetMethod: Widget "'+widgetname+'" instance not found on element "'+element_id+'".');
            }
        },
    
        /**
         * Initializes a layout based on a layout ID or configuration object within a specified container.
         * This is a primary public method for rendering layouts.
         * (Corresponds to `cfg_layouts` in `layout_default.js` if `layoutid` is a string).
         * @param {string|Object} layoutid - The ID of the layout to load or a layout configuration object.
         * @param {string|jQuery} containerid - The ID or jQuery selector for the container element.
         * @returns {void}
         */
        appInitAll: function(layoutid, containerid){
            _containerid = containerid; // Set the main container ID for this HLayout instance
            let $container = $(containerid);
            _appInitAll(layoutid, $container); // Call the internal main initialization function
        },
        
        /**
         * Initializes a "free" layout by parsing `data-heurist-app-id` attributes from elements within a given container.
         * Useful for layouts defined directly in HTML rather than a predefined configuration.
         * @param {Document} [doc_context=window.document] - The document context in which to find the container. Defaults to current window's document.
         * @param {string|jQuery} containerid - The ID or jQuery selector for the container element.
         * @param {Object} [supp_options] - Supplementary options to merge with those parsed from HTML.
         * @param {function} [onInitComplete] - Callback function to execute if layout parsing/initialization completes (or if no layout elements found).
         * @returns {void}
         */
        appInitFromContainer: function( doc_context, containerid, supp_options, onInitComplete ){
            _containerid = containerid;
            _is_container_layout = true; // Flag that this is a container-defined layout
            let $resolved_container;
            if(doc_context && typeof $(doc_context.body).find === 'function'){ // Ensure doc_context is a document
                $resolved_container = $(doc_context.body).find(containerid);
            }else{
                $resolved_container = $(containerid); // Fallback if doc_context is not as expected
            }

            let layout_config = _getLayoutParams($resolved_container, supp_options);
            
            if(layout_config){ // If layout elements were found and config generated
                _appInitAll(layout_config, $resolved_container);
            }else{ // No dynamic layout elements found
                if(window.hWin.HEURIST4.util.isFunction(onInitComplete)){
                        onInitComplete.call(); // Call completion callback if provided
                }
            }
            _defineMediaSource($resolved_container); // Set src for media elements
        },
        
        /**
         * Initializes a "free" layout within a given jQuery container, typically for popups or dynamic sections.
         * Parses `data-heurist-app-id` attributes from elements within the container.
         * @param {jQuery} $container - The jQuery object for the container.
         * @param {Object} [supp_options] - Supplementary options to merge.
         * @returns {void}
         */
        appInitFromContainer2: function( $container, supp_options ){
            // _containerid is not explicitly set here, assuming $container is the direct target
            _is_container_layout = true; // Or false, depending on if $container itself is the layout root or contains layout parts
            let layout_config = _getLayoutParams($container, supp_options);
            if(layout_config){
                _appInitAll(layout_config, $container);
            }
            _defineMediaSource($container); 
        },
        
        /**
         * Public method to explicitly initialize the HLayout manager with widget and layout configurations.
         * This is typically called by the HLayout constructor itself using global `cfg_widgets` and `cfg_layouts`.
         * @param {Array<Object>} cfg_widgets_param - Widget configurations.
         * @param {Array<Object>} cfg_layouts_param - Layout definitions.
         * @returns {void}
         */
        init: function(cfg_widgets_param, cfg_layouts_param){
            _init(cfg_widgets_param, cfg_layouts_param);
        },
        
        /**
         * Public method to control jQuery UI Layout cardinal panes.
         * @param {('open'|'close'|'getSize'|'sizePane')} action - The action for the pane.
         * @param {string|Array<*>} args - Arguments for the action (pane selector, options).
         * @param {HTMLElement|jQuery} [element] - Context element to find the layout.
         * @returns {*|false} Result of the action or false.
         */
        cardinalPanel:function(action, args, element){ // Renamed 'pane' to 'action' to match usage
            return _cardinalPanel(action, args, element);
        }
    };

    _init( typeof cfg_widgets !== 'undefined' ? cfg_widgets : [], typeof cfg_layouts !== 'undefined' ? cfg_layouts : [] );
    return that;  //returns object
}




