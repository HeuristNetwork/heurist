/**
*  
* 
*  @see ext/layout
*  @see layout_defaults.js - configuration file
*
*  appInitAll - main function that init all stuff 
*/


var Hul = top.HEURIST.util;

var grid_min_size = 200;
var grid_step_size = 100;
var app_counter = 0; //to maintain unique id for panels and tabs

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

/**
* Finds application in widgets array
*
* @param id
*/
function appGetWidgetById(id){

    var i;
    for(i=0; i<widgets.length; i++){
        if(widgets[i].id==id){
            return widgets[i];
        }
    }
    return null;
}

/**
* Main funtion that inits all stuff
*  
* layoutid to be loaded (see layouts in layout_default.js)
* containerid - container div id 
* 
* this function
* 1) creates panes (see ext/layout)
* 2) inits layout container
* 3) adds tabs/apps to pane
*/
function appInitAll(layoutid, containerid){

    var layout = layoutGetById(layoutid);


    var $container = $(containerid);
    $container.empty();

    var layout_opts =  {
        applyDefaultStyles: true,
        maskContents:        true,
        togglerContent_open:    '<div class="ui-icon"></div>',
        togglerContent_closed:  '<div class="ui-icon"></div>'};

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

    // 3) add tabs/apps to panes

        function __layoutInitPane(pos){
            if(layout[pos]){

                var lpane = layout[pos];

                var $pane = $('.ui-layout-'+pos);

                $pane.empty();

                var $pane_content = $(document.createElement('div'));
                    $pane_content.attr('id','content_'+pos).addClass('ui-layout-content').appendTo($pane);

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


                //init all tabs
                var containment_sel = '.ui-layout-'+pos+' > .ui-layout-content';
                var $tabs = $( containment_sel+' > .tab_ctrl' ).tabs();
                appInitFeatures(containment_sel);
            }
        } //end __layoutInitPane


        __layoutInitPane('north');
        __layoutInitPane('west');
        __layoutInitPane('east');
        __layoutInitPane('south');
        __layoutInitPane('center');


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

              var app = appGetWidgetById($app_content.attr('widgetid')); //ART04-26
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
function appCreatePanel($container, app, needcontent){

        app_counter++;

        var $d = $(document.createElement('div'));
            $d.attr('id', 'pnl_'+app_counter)  //.css('border','solid')
                .appendTo($container);

        if(app.dragable){
            $d.addClass('dragable');
        }
        if(app.resizable){
            $d.addClass('resizable');
        }

        if(needcontent){ //for standalone application laod content at once

            var application = appGetWidgetById(app.appid); //find in app array

            if(app.hasheader){
                    var $header = $("<div>");
                    $header.append(top.HR(app.name || application.name))
                        .addClass('ui-widget-header')
                        .addClass('ui-corner-all')
                        .addClass('header'+app.appid+'_'+app_counter) 
                        .appendTo($d);
            }

            appAddContent($d, application, app.options);

            $d.addClass('ui-widget-content')
              .addClass('ui-corner-all')
              .css('padding','0.2em');
        }

        if(app.css){
            $.each(app.css, function(key, value){
                 $d.css(key, value);
            });
        }else if(app.resizable) {
            $d.css('width', '98%');
            $d.css('height', '98%');
        }else {
            $d.css('width', '99%');
            $d.css('height', '99%');
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
function appAddContent($container, app, options){

                var $content = $(document.createElement('div'));
                    $content.attr('id', app.id+'_'+app_counter)
                            .attr('widgetid', app.id)
                            .appendTo($container);

                if(app.isframe){
                    $content.addClass('frame_container');
                    $content.append('<iframe id="'+app.id+'_'+app_counter+'" src="'+app.url+'"></iframe>');
                }else if (app.script && app.widgetname) {

                    //this is debug mode to init widgets 
                    // app javascript are loaded in index.php header
                    if(app.widgetname=='rec_viewer'){
                        //DEBUG
                        $content.rec_viewer();
                    }else
                    if(app.widgetname=='search'){
                        //DEBUG
                        $content.search( ); //options
                    }else
                     if(app.widgetname=='rec_list'){
                        
                        $content.rec_list( options );
                    }else    
                    {
                     //this is normal way of widget initialization   
                     // script is loaded dynamically and init function is widget name
                        
                     $.getScript(app.script, function() {
                         if($.isFunction($content[app.widgetname])){
                              $content[app.widgetname]( options );
                         }else{
                             top.HEURIST.util.showMsgErr('Widget '+app.widgetname+' not loaded. Verify your configuration');
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
* $container - pane in layout
* apps - list of application for tab (from layouts array)
* tab - entry from layout - need for ui parameters
*/
function appCreateTabControl($container, apps, tabcfg){

        if(!apps) return null;

        if(! $.isArray(apps)){
            apps = [apps];
        }
        if(tabcfg==null){
            tabcfg = apps[0];
        }

        //create
        $tab_ctrl = appCreatePanel($container, tabcfg, false);
        $tab_ctrl.addClass('tab_ctrl');

        if(tabcfg.dockable){
            $tab_ctrl.addClass('dockable');
        }


        var $ul = $(document.createElement('ul'));
            $ul.addClass('sortable_tab_ul')
               .appendTo($tab_ctrl);

        $.each(apps, function(idx, _app){
            //_app - reference to widget in layout tab list

            var app = appGetWidgetById(_app.appid);

            var content_id;
            if(_app.content_id){
                content_id = _app.content_id; //already exists
            }else{
                app_counter++;
                content_id = app.id+'_'+app_counter;
            }

            $ul.append('<li><a class="header'+content_id+'" href="#'+content_id+'">'+ (top.HR(_app.name || app.name)) +'</a></li>')

            if(!_app.content_id){ //already exists
                appAddContent($tab_ctrl, app, _app.options);
            }

        });

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
