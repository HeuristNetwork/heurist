/**
* mapManager.js - manage layers and proejcts - map legend
* 
* Manager control - map legend
* Addmapdoc control - addition of new record with type "Map Document"
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

L.Control.Manager = L.Control.extend({
    onAdd: function(map) {
        
        var container = this._container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);              

        $(container)
            //.css({'width':'200px','overflow-y':'auto'})
            .resizable({ghost:true,handles:'w',minWidth:250, maxWidth:500,
                        stop:function(event, ui){ $(ui.element).css({left:0}); }})
            .resizable( "disable" );

                        
        return container;
    },
    
    onRemove: function(map) {
        // Nothing to do here
    }
});

L.control.manager = function(opts) {
    return new L.Control.Manager(opts);
}

//--------------------------------
// add map doc control - see createNewMapDocument
//
L.Control.Addmapdoc = L.Control.extend({
    
    _mapwidget: null,
    
    initialize: function(options) {

        options = options || {};
        
        L.Util.setOptions(this, options);
        
        this._mapwidget = options.mapwidget;
        
        L.Control.prototype.initialize.call(this, this.options);
    },

    
    onAdd: function(map) {
        
        //if ( !$.isFunction($('body').hMapPublish) ) return;
        
        var container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);
          
        $('<a>').attr('title', window.hWin.HR('Create new map document'))
            .html('<span class="ui-icon ui-map-document" style="width:22px;margin:2px 0px">'
+'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:0px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                +'</span>')
            .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            //.addClass('ui-icon ui-map-document')
            .appendTo(container);
        
        L.DomEvent
            .on(container, 'click', this._onClick, this);
        
        return container;
    },

    onRemove: function(map) {
        // Nothing to do here
    },
    
    _onClick: function() {
       //this.mapPublish.openPublishDialog();
       this._mapwidget.mapManager.createNewMapDocument();
    }
});

L.control.addmapdoc = function(opts) {
    return new L.Control.Addmapdoc(opts);
}



//--------------------------------
//        
// creates accordion with 3(4) panes
// loads prededifned list of base layers
// calls refresh map documents
//        
//$.widget( "heurist.mapmanager", {
    
function hMapManager( _options )
{    
    var _className = "MapManager",
    _version   = "0.4",

    options = {
        container:null,  
        mapwidget:null,   
        visible_panels:null,
        hasTempMap: false,  //show hide this panel
        is_ui_main: false   //if true - hide on collapse
    },
        
    mapDocuments = null, //hMapDocument for db map docs,  Note: mapdocument with index=0 is search results
    mapdoc_treeview = null,
    mapdoc_select = null,  //selector in map toolbar
    mapdoc_visible = {}, //since 2022-07-29 the mapdocument is visible
    btn_expand = null,
    btn_collapse = null,
    
    maxHeight = 9999999,
    
    isExpanded = false,
    keepWidth = 250,
    
    basemaplayer =null;  //current basemap layer
    


    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init (_options ) {
       
        options = $.extend(options, _options);

        options.container = $(options.container);
        
        options.container.css({border: '2px solid rgba(0,0,0,0.2)','background-clip': 'padding-box'}); 
        
        btn_expand = $('<a>').attr('title', window.hWin.HR('Map Legend'))
            .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            .addClass('ui-icon ui-icon-list')
            .on({click: _onExpand })
            .appendTo(options.container);

        btn_collapse = $('<a>').attr('title', 'Minimize legend')
            .css({'width':'16px','height':'16px','z-index':1001,float:'right',background:'none'})
            .addClass('ui-icon ui-icon-carat-1-ne')
            .on({click: _onCollapse }).hide()
            .appendTo(options.container);
        
        options.container.find('.ui-resizable-handle')
            .css({'border-right': '1px solid lightgray', 'width': '7px'})
            .append($('<span class="ui-icon ui-icon-carat-1-w" style="height:100%;width:11px;color:gray;top:3px;left:-3px;font-size:10px;">'));
        
        mapDocuments = hMapDocument( { mapwidget:options.mapwidget } ); 
        
        $('<div>').attr('grpid','search').addClass('svs-acordeon outline_suppress')
                .css('border-bottom-color','lightgray')
                .append( _defineHeader('Result Sets', 'search'))
                .append( _defineContent('search') ).appendTo(options.container);
        $('<div>').attr('grpid','mapdocs').addClass('svs-acordeon outline_suppress')
                .css('border-bottom-color','lightgray')
                .append( _defineHeader('Map Documents', 'mapdocs'))
                .append( _defineContent('mapdocs') ).appendTo(options.container);
        $('<div>').attr('grpid','tempmap').addClass('svs-acordeon outline_suppress')
                .append( _defineHeader('Temp Map', 'tempmap'))
                .append( _defineContent('tempmap') ).appendTo(options.container);        
        $('<div>').attr('grpid','basemaps').addClass('svs-acordeon outline_suppress')
                .css('border-bottom','none')
                .append( _defineHeader('Base Maps', 'basemaps'))
                .append( _defineContent('basemaps') ).appendTo(options.container);        
                
        
        //restore expand/collapse status
        var keep_status = window.hWin.HAPI4.get_prefs('map_control_status');
        if(keep_status){
            keep_status = window.hWin.HEURIST4.util.isJSON(keep_status);   
        }
        if(!keep_status) {
            keep_status = { 'search':true };
        }
        
        //init list of accordions
        var cdivs = options.container.find('.svs-acordeon');
        $.each(cdivs, function(i, cdiv){

            cdiv = $(cdiv);
            var groupid = cdiv.attr('grpid');
            cdiv.accordion({
                active: ( ( keep_status && keep_status[ groupid ] )?0:false),
                header: "> h3",
                heightStyle: "content",
                collapsible: true,
                activate: function(event, ui) {
                    //save status of accordions - expandad/collapsed
                    if(ui.newHeader.length>0 && ui.oldHeader.length<1){ //activated
                        keep_status[ ui.newHeader.attr('grpid') ] = true;
                    }else{ //collapsed
                        keep_status[ ui.oldHeader.attr('grpid') ] = false;
                    }
                    //save
                    window.hWin.HAPI4.save_pref('map_control_status', JSON.stringify(keep_status));
                    //replace all ui-icon-triangle-1-s to se
                    cdivs.find('.ui-icon-triangle-1-e').removeClass('ui-icon-triangle-1-se');
                    cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');
                    
                    that.setHeight();

                }
            });
        });
        //replace all ui-icon-triangle-1-s to se
        cdivs.find('.ui-accordion-header-icon').css('font-size','inherit');
        cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');        
       
        //that.loadBaseMap(0);
        
        options.container.addClass('ui-widget-content')
            .css({'margin-right':'5px','font-size':'0.97em'});
            
        /*  
        if($.isArray(options.visible_panels) && options.visible_panels.indexOf('off')<0){
            _onExpand(); //expand at once
        }else{
            that.updatePanelVisibility();  
        }
        */
        //
        
    }

    
    //
    //
    //    
    function _defineHeader(name, domain){

        var sIcon = '';
        //<span class="ui-icon ui-icon-'+sIcon+'" ' + 'style="display:inline-block;padding:0 4px"></span>
        
        var $header = $('<h3 grpid="'+domain+'" class="hasmenu">' + sIcon + '<span style="vertical-align:top;">'
            + name + '</span></h3>')
            .addClass('tree-accordeon-header outline_suppress svs-header');

        /*    
        if('dbs'!=domain){
            var context_opts = this._getAddContextMenu(domain);
            $header.contextmenu(context_opts);
        }
        */
         var isPublished = options.mapwidget.mapping('option','isPublished');
         if(domain=='mapdocs' && !isPublished){

            /*old version             
            var append_link = $('<a title="create new map document">',{href:'#'})
                .html('<span class="ui-icon ui-map-document" style="width:22px">'
+'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                +'</span>New map document')
                .css({height:'26px',width: '100%',background: 'none'})
                .click(_createNewMapDocument)
                .appendTo($header);
            */
            var append_link = $('<a title="create new map document">',{href:'#'})
                .html('Add <span class="ui-icon ui-map-document" style="width:22px">'
+'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                +'</span>')
                .css({'line-height':'15px',height:'14px',width:'50px',background: 'none',float:'right'})
                .click(_createNewMapDocument)
                .appendTo($header);
                
           //$header.addClass('with_supplementals');
        }

        return $header

    }
    
    //
    //  adds new mapdocument record
    //
    function _createNewMapDocument(event){
        
                    window.hWin.HEURIST4.util.stopEvent(event);
                    
                    //edit layer or mapdocument record
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null,
                    {selectOnSave:true,
                     onClose: function(){ 
                         //parent_span.find('.svs-contextmenu4').hide();
                     },
                     onselect:function(event, data){
                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            
                            _addToMapDocumentTree( data.selection );
                            
                        }
                    },
                    new_record_params:{rt:window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']}
                    });
    }
    
    //
    // redraw legend entries
    //
    function _defineContent(groupID, data, container){

        var content = null;
        
        if(groupID=='search' || groupID=='tempmap'){
            
            content = $('<div>');
            
            var resdata = mapDocuments.getTreeData((groupID=='search')?0:'temp');
            
            _refreshMapDocumentTree( resdata, content )
        
        }else if(groupID=='basemaps'){
            // load list of predefined base layers 
            // see extensive list in leaflet-providers.js
            var map_providers = options.mapwidget.mapping('getBaseMapProviders');
            
            content = '';
            for (var k=0; k<map_providers.length; k++){
                content = content + '<label><input type="radio" name="basemap" data-mapindex="'+k
                                  + '" data-mapid="'+map_providers[k]['name']+'">'
                                  + map_providers[k]['name']+'</label><br>';
            }
            
            content = content + '<div style="text-align:center; display: inline-block;margin-left: 5px;" id="basemap_filter_btns">'
                +'<button name="basemap_filter">filters</button>'
                +'<button name="basemap_filter_reset">reset</button></div>';
            
            content = $(content);
            content.find('input').on( { change: that.loadBaseMap });
            
            content.find('button[name="basemap_filter"]')
                .button()
                .css({'font-size': '0.8em', 'padding': '0em 1em'})
                .on( { click: function(){
                        var cfg = options.mapwidget.mapping('getBaseMapFilter');
                        imgFilter(cfg,null,function(filter){
                            options.mapwidget.mapping('setBaseMapFilter', filter);
                        });   
                }});
            content.find('button[name="basemap_filter_reset"]')
                .button()
                .css({'font-size': '0.8em', 'padding': '0em 1em'})
                .on( { click: function(){
                    options.mapwidget.mapping('setBaseMapFilter', null);
                }});
            
        }else  if(groupID=='mapdocs'){
            //load list of mapddocuments
            mapdoc_treeview = $('<div>');
            
            mapDocuments.loadMapDocuments( function(resdata){
                        _refreshMapDocumentTree( resdata, mapdoc_treeview );
                        options.mapwidget.mapping('onInitComplete', 'mapdocs');
                    } );
            
            content = mapdoc_treeview;        
        }
        
        if(window.hWin.HEURIST4.util.isempty(content) ){
            content = '';
        }
        if(window.hWin.HEURIST4.util.isnull(container)){
            return $('<div>').append(content);
        }else{
            container.empty();
            container.append(content);
            return container;
        }
        
    }
    
    //
    //
    //
    function _refreshSearchContent(){
        
            var grp_div = options.container.find('.svs-acordeon[grpid="search"]');
            _defineContent('search', null, grp_div.find('.ui-accordion-content'));
            //defineContent( 'search' );
            
            //hide invisible layers and allow max 5 entries
            
            
            
            that.setHeight();
        
    }
    
    //
    // adds new mapdoc entry in mapdoc tree
    //
    function _addToMapDocumentTree( resdata ){
        
            if(resdata==null){

                var $res = {};  
                $res['key'] = 99999999;
                $res['title'] = 'temp';
                $res['type'] = 'mapdocument';
                $res['lazy'] = true;
                    
            }else{

                var rec = resdata.getFirstRecord();
            
                var $res = {};  
                $res['key'] = resdata.fld(rec, 'rec_ID');
                $res['title'] = resdata.fld(rec, 'rec_Title');
                $res['type'] = 'mapdocument';
                $res['lazy'] = true;
        
            }
            
            that.populateMapDocuments(mapdoc_select); // update mapdoc dropdown

            //mapdoc_treeview.find('.ui-fancytree').show();
            mapdoc_treeview.find('.empty_msg').remove();
            var tree = mapdoc_treeview.fancytree("getTree");
            tree.getRootNode().addChildren( [$res] ).setSelected(true);
        
    }
    
    //
    // it is invoked on retieve of map document list 
    // or on update of search results
    //
    function _refreshMapDocumentTree( resdata, tree_container ){

        //create treeview data
        var treedata = [];
        
        if( (typeof resdata.isA == "function") && resdata.isA("hRecordSet") ){
        
            var idx, records = resdata.getRecords();
            for(idx in records){
                if(idx)
                {
                    var record = records[idx];
                    var recID  = resdata.fld(record, 'rec_ID'),
                    recName = resdata.fld(record, 'rec_Title'),
                    extent = resdata.fld(record, DT_GEO_OBJECT); //initial extent
                    
                    var $res = {};  
                    $res['key'] = recID;
                    $res['title'] = recName;
                    $res['type'] = 'mapdocument';
                    $res['extent'] = extent;
                    $res['lazy'] = true;
                    //$res['checkbox'] = 'radio';
                    //$res['checkbox'] = false;
                    //$res['radiogroup'] = true;

                    treedata.push($res);
                    //ele.append("<option value='"+recID+"'>"+recName+"</option>");
                    //mapdocs = mapdocs + "<li mapdoc_id='"+recID+"'><a>"+recName+"</a></li>";
                }
            }//for
            
        }else{
            treedata = resdata;
        }
                
        tree_container.empty();    
        
        if($.isFunction($('body').fancytree)){
     
            tree_container.fancytree({  //addClass('tree-facets').
                                    //extensions: ["filter"],
                                    //            extensions: ["select"],
                                    checkbox: true,
                                    selectMode: 3,  // hierarchical multi-selection
                                    source: treedata,
                                    /*
                                    beforeSelect: function(event, data){
                                        // A node is about to be selected: prevent this, for folder-nodes:
                                        if( data.node.hasChildren() ){
                                            return false;
                                        }
                                    },*/
                                    lazyLoad: function(event, data){
                                    //load: function(forceReload){
                                        //load content of mapdocument
                                        var node = data.node;
                                        var dfd = new $.Deferred();
                                        data.result = dfd.promise();
                                        mapDocuments.zoomToMapDocument(node.key);
                                        mapDocuments.openMapDocument(node.key, dfd);
                                        //return dfd.promise();
                                        
                                    },
                                    expand: function(e, data){
                                       
                                    },
                                    loadChildren: function(e, data){
    //
    //console.log('loaded '+data.node.title+'  '+data.node.children.length);
                                        setTimeout(function(){
                                            
                                            if(data.node.data.type=='mapdocument'){
                                                data.node.setSelected(true, {noEvents:true} );
                                                //enable buttons
                                                var btns = $(data.node.li).find('.svs-contextmenu3');
                                                btns.find('span.ui-icon-arrow-4-diag').css({color:'black'});    
                                                btns.find('span.ui-icon-refresh').css({color:'black'});
                                            }
                                                
                                            
                                            //$.each(data.node.children, function( idx, item ){
                                            //    _defineActionIcons( item );
                                            //}) 
                                        }, 500);                                           
                                    },
                                    select: function(e, data) {  //show/hide   checkbox event listener
                                        
                                        var node = data.node;
                                        if(node.data.type=='mapdocument'){
                                            //if not expanded, expand, it loads layers (opens mapdocument)
                                            
                                            if(node.isSelected()){
                                                delete mapdoc_visible[mapdoc_id];
                                                //hide all other mapdocs
                                                that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false); 
                                                mapdoc_visible = {};
                                            }
                                            
                                            var mapdoc_id = node.key;
                                            if(!node.isExpanded() && !mapDocuments.isLoaded(mapdoc_id)){
                                                node.setExpanded(true); //load content - init lazy load - see lazyLoad ->  mapDocuments.openMapDocument
                                            }else{

                                                mapDocuments.setMapDocumentVisibility(mapdoc_id, node.isSelected());
                                            }
                                            
                                            if(node.isSelected()){
                                                mapdoc_visible[mapdoc_id] = node.title; // add to list
                                            }else{
                                                delete mapdoc_visible[mapdoc_id]; // remove from list
                                            }
                                            
                                            //
                                            //mapDocuments.openMapDocument(node.key, dfd);
                                        }else if(node.data.type=='layer'){
                                            
                                            var mapdoc_id = node.data.mapdoc_id;
                                            var not_visible = true;
                                            if((mapdoc_id>0 && mapdoc_visible[mapdoc_id]) || mapdoc_id==0 || mapdoc_id=='temp'){
                                                //node.key - heurist layer record id
                                                var layer_rec = mapDocuments.getLayer(mapdoc_id, node.key);
                                                if(layer_rec){
                                                    (layer_rec['layer']).setVisibility( node.isSelected() );  
                                                    not_visible = !node.isSelected();
                                                    
                                                } 
                                            }
                                            if(not_visible){ //reset
                                                node.setSelected(false);
                                            }
                                        }


                                        /* Get a list of all selected nodes, and convert to a key array:
                                        var selKeys = $.map(data.tree.getSelectedNodes(), function(node){
                                        return node.key;
                                        });
                                        $("#echoSelection3").text(selKeys.join(", "));

                                        // Get a list of all selected TOP nodes
                                        var selRootNodes = data.tree.getSelectedNodes(true);
                                        // ... and convert to a key array:
                                        var selRootKeys = $.map(selRootNodes, function(node){
                                        return node.key;
                                        });
                                        $("#echoSelectionRootKeys3").text(selRootKeys.join(", "));
                                        $("#echoSelectionRoots3").text(selRootNodes.join(", "));
                                        */
                                    },
                                    /*
                                    click: function(e, data){
                                       if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                                           data.node.setExpanded(!data.node.isExpanded());
                                           //treediv.find('.fancytree-expander').hide();
                                           
                                       }else if( data.node.lazy) {
                                           data.node.setExpanded( true );
                                       }
                                    },
                                    */
                                    dblclick: function(e, data) {
                                        data.node.toggleSelected();
                                    },
                                    keydown: function(e, data) {
                                        if( e.which === 32 ) {
                                            data.node.toggleSelected();
                                            return false;
                                        }
                                    },
                                    renderNode: function(event, data) {
                                        // Optionally tweak data.node.span
                                        var item = data.node;
                                        if(item.data.type=='layer'){
                                            var rec_id = item.key;
                                            var mapdoc_id = item.data.mapdoc_id;
                                            
                                            var style = mapDocuments.getSymbology( mapdoc_id, rec_id );
                                            
                                            if(style['rectypeIconUrl']){
                                                var dcss = {'display':'inline-block', 'background-image':'url('+style['rectypeIconUrl']+')'};
                                            }else{
                                                
                                                var dcss = {'display':'inline-block', 'background-image':'none'};
                                                if(style['stroke']!==false){
                                                    
                                                    var opacity = style['opacity']>0?style['opacity']:1;
                                                    var weight = (style['weight']>0&&style['weight']<4)?style['weight']:3;
                                                    dcss['width']  = 16-weight*2; 
                                                    dcss['height'] = 16-weight*2;
                                                    
                                                    dcss['border'] = weight+'px solid '
                                                                    + window.hWin.HEURIST4.ui.hexToRgbStr(style['color'], opacity);
                                                    if ( style['opacity']>0 && style['opacity']<1 ) {
                                                        dcss['-webkit-background-clip'] = 'padding-box'; //for Safari
                                                        dcss['background-clip'] = 'padding-box'; //for IE9+, Firefox 4+, Opera, Chrome
                                                    }
                                                    
                                                } else {
                                                    dcss['border'] = 'none';
                                                }

                                                if(style['fill']!==false){
                                                    var fillColor = style['fillColor']?style['fillColor']:style['color'];
                                                    var fillOpacity = style['fillOpacity']>0?style['fillOpacity']:0.2;
                                                    dcss['background-color'] = window.hWin.HEURIST4.ui.hexToRgbStr(fillColor, fillOpacity);
                                                }else{
                                                    dcss['background'] = 'none';
                                                }
                                            }
                                            var $span = $(item.span);
                                            $span.find("> span.fancytree-icon")
                                            .css(dcss);
                                            
                                                //backgroundImage: "url(skin-custom/customDoc2.gif)",
                                                //backgroundPosition: "0 0"
                                        }
                                        else if(item.data.type=='mapdocument'){
                                            var $span = $(item.span);
                                            $span.find("> span.fancytree-checkbox").addClass('fancytree-radio');
                                                //.css('background-position-y', '-48px !important');
                                        }
                                        _defineActionIcons( item );
                                    }
                                    // The following options are only required, if we have more than one tree on one page:
                                    //          initId: "treeData",
                                    //cookieId: "fancytree-Cb3",
                                    //idPrefix: "fancytree-Cb3-"
                                });     
                                
                                tree_container.addClass('tree-map');
                            
        }
        
        
        
        
        if(treedata.length==0){    
            //tree_container.find('.ui-fancytree').hide();
            tree_container.append('<span class="empty_msg" style="font-style:italic;padding-left:10px;line-height:10px">none</span>');
        }
        that.setHeight();
    
    }
    
    //
    //  Adds menu buttons to treeview items
    //
    function _defineActionIcons(item)
    { 
        var item_li = $(item.li), 
            recid = item.key, 
            mapdoc_id = 0;
            
        if($(item).find('.svs-contextmenu3').length==0){
            
            if(item.data.type=='layer'){
                mapdoc_id = item.data.mapdoc_id;
            }else{
                mapdoc_id = item.key;
                recid = -1;
            }
            
            var parent_span = item_li.children('span.fancytree-node');
            
            var isEditAllowed = options.mapwidget.mapping('option','isEditAllowed');
            
            var actionspan = '<div class="svs-contextmenu3" '
                    +((mapdoc_id>=0 || mapdoc_id=='temp')?('" data-mapdoc="'+mapdoc_id+'"'):'')
                    +(recid>0?('" data-recid="'+recid+'"'):'')+'>'
                +'<span class="ui-icon ui-icon-arrow-4-diag" '
                    +((item.data.type=='mapdocument' && !item.data.extent)?'style="color:gray"':'')
                    +' title="Zoom to '+item.data.type+' extent"></span>';
                    
            if(isEditAllowed){        
                
                if(item.data.type=='mapdocument' && mapdoc_id>0){
                    
                    actionspan +=
                        ('<span class="ui-icon ui-icon-pencil" title="Modify the map document"></span>'
                        +'<span class="ui-icon ui-map-layer" title="Add map layer">'
                            +'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                        +'</span>'
                        +'<span class="ui-icon ui-icon-refresh" style="color:gray" title="Reload map document"></span>'
                        //+'<span class="ui-icon ui-icon-trash" style="color:gray" title="Close map document"></span>'
                        )
                    
                }else if(mapdoc_id>0){
                    
                    actionspan +=
                        '<span class="ui-icon ui-icon-pencil" title="Change symbology and behaviour of map layer"></span>';
                    
                    
                }else{
                    
                    if(recid<9000000){
                    actionspan +=
                        '<span class="ui-icon ui-icon-pencil" title="Change symbology and behaviour of map layer"></span>'
                        +'<span class="ui-icon ui-icon-trash" title="Remove map layer"></span>';
                    
                    }else{
                    actionspan +=
                        '<span class="ui-icon ui-icon-pencil" title="Change symbology"></span>'
                        +'<span class="ui-icon ui-icon-arrowstop-1-s" title="Save result set as layer"></span>'
                        +'<span class="ui-icon ui-icon-trash" title="Remove map layer"></span>';
                    }
                }

            }
            actionspan = $(actionspan+'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"/>').appendTo(parent_span);
                
                
            actionspan.find('.ui-icon').click(function(event){
                var ele = $(event.target);
                var parent_span = ele.parents('span.fancytree-node');
                
                function __in_progress(){
                    if(parent_span.find('.svs-contextmenu4').is(':visible')) {
                        return true; 
                    }
                    parent_span.find('.svs-contextmenu4').show();
                    parent_span.find('.svs-contextmenu3').hide();
                    return false;
                }

                //timeout need to activate current node    
                setTimeout(function(){                         
                    var recid = ele.parents('.svs-contextmenu3').attr('data-recid');
                    var mapdoc_id = ele.parents('.svs-contextmenu3').attr('data-mapdoc');
                    
                        if(ele.hasClass('ui-icon-arrow-4-diag')){
                            
                            if(recid>0){
                                
                                if(mapdoc_id>=0 || mapdoc_id=='temp'){
                                    var layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                    if(layer_rec) (layer_rec['layer']).zoomToLayer();
                                } 
                                
                            }else if(mapdoc_id>0){
                                    mapDocuments.zoomToMapDocument(mapdoc_id);
                            }
                            
                        }else if(ele.hasClass('ui-icon-plus')){ //add new layer to map document
                        
                            var in_progress = __in_progress();
                            if(mapdoc_id>0 && !in_progress){

                                mapDocuments.selectLayerRecord(mapdoc_id, function(data){
                                        parent_span.find('.svs-contextmenu4').hide();
                                        if(data){
                                            item.removeChildren();
                                            item.addChildren( data );
                                            /*setTimeout(function(){
                                            $.each(item.children, function( idx, item_ch ){
                                                _defineActionIcons( item_ch );
                                                });},500);*/
                                        }
                                    });
                                /*
                                var dfd = new $.Deferred();
                                mapDocuments.selectLayerRecord(mapdoc_id, dfd);
                                $.when( dfd.promise() ).done(
                                    function(data){
                                        item.removeChildren();
                                        item.addChildren( data );
                                    }
                                );
                                */
                            }
                            
                        }else if(ele.hasClass('ui-icon-arrowstop-1-s')){ 
                        
                                mapDocuments.saveResultSetAsLayerRecord(recid, function(data){
                                        ele.hide();
                                        parent_span.find('.svs-contextmenu4').hide();
                                });
                            
                        }else if(ele.hasClass('ui-icon-pencil')){
                            
                                if(mapdoc_id>0 || recid<9000000){

                                    __in_progress();
                                    //edit layer or mapdocument record
                                    window.hWin.HEURIST4.ui.openRecordEdit(recid>0?recid:mapdoc_id, null,
                                    {selectOnSave:true,
                                     onClose: function(){ 
                                         parent_span.find('.svs-contextmenu4').hide();
                                     },
                                     onselect:function(event, data){
                                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                            var recset = data.selection;
                                            var rec = recset.getFirstRecord();
                                            item.title = recset.fld(rec, 'rec_Title');
                                            parent_span.find('span.fancytree-title').text( item.title );
                                            
                                            var symbology = recset.fld(rec
                                                            , window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
                                            if(recid>0 && symbology){
                                                //apply symbolgy on map
                                                var layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                                if(layer_rec) (layer_rec['layer']).applyStyle( symbology );
                                                //render new symbology in legend
                                                item.render(true);
                                            }
                                            
                                        }
                                    }});
                                }else{
                                    // get layer record, take symbology field and title 
                                    // open symbology editor
                                    // on exit 1) call mapLayer.applyStyles
                                    //         2) change title in tree and timeline
                                    mapDocuments.editSymbology(0, recid, function(new_title, new_style){
                                        //update layer title in legend and timeline
                                        if(new_title && item.title != new_title){
                                            item.title = new_title;
                                            parent_span.find('span.fancytree-title').text( new_title );
                                        }
                                        
                                        //render new symbology in legend
                                        item.render(true);
                                        
                                    });
                                }
                        }else if(ele.hasClass('ui-icon-refresh') && (mapdoc_id>0) && mapDocuments.isLoaded(mapdoc_id)){
                               
                                mapDocuments.closeMapDocument(mapdoc_id);
                                //remove children from treeview
                                //item.setSelected(false, {noEvents:true});
                                item.removeChildren();
                                item.resetLazy();
                                
                                item.selected = false;
                                parent_span.removeClass('fancytree-selected fancytree-partsel');
                                
                                that.toggleMapDocument(mapdoc_id, true);
                        }else if(ele.hasClass('ui-icon-trash')){
                            
                            //mapdocument - remove from map and unload from memory
                            if(mapdoc_id>0){
                                mapDocuments.closeMapDocument(mapdoc_id);
                                //remove children from treeview
                                //item.setSelected(false, {noEvents:true});
                                item.removeChildren();
                                item.resetLazy();
                                
                                item.selected = false;
                                parent_span.removeClass('fancytree-selected fancytree-partsel');
                            }else if(recid>0){
                                //search result
                                mapDocuments.removeLayer(0, recid);
                                item.remove();
                            }
                            
                        }
                    
                },500);
            });

            //hide icons on mouse exit
            function _onmouseexit(event){
                var node;
                if($(event.target).is('li')){
                    node = $(event.target).find('.fancytree-node');
                }else if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    //hide icon for parent 
                    node = $(event.target).parents('.fancytree-node');
                    if(node) node = $(node[0]);
                }
                var ele = node.find('.svs-contextmenu3');
                ele.hide();
            }               

            $(parent_span).hover(
                function(event){
                    var node;
                    if($(event.target).hasClass('fancytree-node')){
                        node =  $(event.target);
                    }else{
                        node = $(event.target).parents('.fancytree-node');
                    }
                    if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                        var ele = $(node).find('.svs-contextmenu3');
                        ele.css({'display':'inline-block'});//.css('visibility','visible');
                    }
                }
            );               
            $(parent_span).mouseleave(
                _onmouseexit
            );
        }
    }
    
    function _onExpand(){
        isExpanded = true;
        
        btn_expand.hide();
        btn_collapse.show();
        options.container.css({'width':keepWidth,'overflow-y':'auto',padding:'4px'}).resizable( "enable" ).show();
        that.updatePanelVisibility(); //refresh
        that.setHeight();
    }

    function _onCollapse(){
        isExpanded = false;
        keepWidth = options.container.width();
        btn_expand.show();
        btn_collapse.hide();
        options.container.css({'width':'22px','height':'22px','overflow-y':'hidden','overflow-x':'hidden',padding:'1px'})
            .resizable( "disable" );
            
        if(options.is_ui_main){
            options.container.hide();
        }
            
        that.updatePanelVisibility();
    }

    

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        /*
        // data - layer name and reference
        // 
        defineContent: function(groupID, data){
            //find group div
            var grp_div = options.container.find('.svs-acordeon[grpid="'+groupID+'"]');
            //define new
            _defineContent(groupID, data, grp_div.find('.ui-accordion-content'));
        },
        */
        setHeight: function( val ){
            
           if(isExpanded){
               
               if(val>0) maxHeight  = val;
                
               var ele = options.container.find('.svs-acordeon:visible');
               var h = 20;
               $(ele).each(function(idx,item){h=h+$(item).height()});
                
               options.container.height( Math.min(h, maxHeight) );
               
           }else{
               options.container.height( 22 );
               options.container.width( 22 );
           }
           
        },
        
        //
        // params
        // either json: {width:200,open:1|0,basemaps:0|1|-1,mapdocs: ,  }   -1 hidden,0-close,1-open
        // or   off,width(numeric),-basemaps,mapdocs,search  
        //
        updatePanelVisibility: function(params)
        {
            if(params){
                if($.isArray(params)){
                    if(params.indexOf('all')>=0){ //default
                        params = {basemaps:0,mapdocs:0,search:1};
                    }else{
                        var defWidth = 250;
                        $.each(params,function(i,item){
                           if(window.hWin.HEURIST4.util.isNumber(item) && item>0){
                               defWidth = item;
                               return false;
                           } 
                        });
                        params = {basemaps:params.indexOf('-basemaps')<0?(params.indexOf('basemaps')<0?-1:1):0,
                                  mapdocs:params.indexOf('-mapdocs')<0?(params.indexOf('mapdocs')<0?-1:1):0,
                                  search:params.indexOf('-search')<0?(params.indexOf('search')<0?-1:1):0,
                                  tempmap:params.indexOf('-tempmap')<0?(params.indexOf('tempmap')<0?-1:1):0,
                                  open:params.indexOf('off')<0?1:0,
                                  width:defWidth};
                    }
                }
                
                keepWidth  = params.width>0?params.width:250;
                isExpanded = window.hWin.HEURIST4.util.istrue(params.open, true);
                
                options.visible_panels = params; //array of visible panels
                
                if(isExpanded){
                    _onExpand();
                }else{
                    _onCollapse();
                    keepWidth  = params.width>0?params.width:250;
                }
                
                return;
            }
            
            if(!options.visible_panels) options.visible_panels = {basemaps:0,mapdocs:0,search:1};//['all'];
            
            function __set(val){
                var is_visible = true;//(options.visible_panels.indexOf('all')>=0 || options.visible_panels.indexOf(val)>=0);
                var ele = options.container.find('.svs-acordeon[grpid="'+val+'"]');
                if(val=='tempmap'){
                    is_visible = options.hasTempMap;
                    is_collapsed = !window.hWin.HEURIST4.util.istrue(options.visible_panels[val], true);
                }else{
                    val = options.visible_panels[val];
                    is_collapsed = !window.hWin.HEURIST4.util.istrue(val, false);
                    is_visible = (val!=-1);//window.hWin.HEURIST4.util.istrue(val, false);
                }
                
                if(is_visible){
                    ele.show();
                    ele.accordion("option", "active", is_collapsed?false:0);
                }else{
                    ele.hide();
                }
            }
            
            if(isExpanded){
                __set('basemaps');            
                __set('search');            
                __set('mapdocs'); 
                __set('tempmap'); 
                options.container.find('.ui-resizable-handle').show();
            }else{
                options.container.children('div').hide(); 
            }       
        
            //__set('onedoc');            
        },
        
        //
        //
        //
        populateMapDocuments: function($select){

            if($select.hSelect('instance') != undefined){
                $select.hSelect('destroy');
            }
            $select.empty();

            mapdoc_select = $select;

            mapDocuments.loadMapDocuments(function(resdata){
                
                window.hWin.HEURIST4.ui.addoption(mapdoc_select[0], '', 'None');

                resdata.each2(function(id, record){
                    window.hWin.HEURIST4.ui.addoption(mapdoc_select[0], record['rec_ID'], record['rec_Title']);
                });

                mapdoc_select.hSelect({
                    'open': function(event, ui){ // get active mapdoc(s), highlight visible map doc(s)

                        var selected_opts = Object.values(mapdoc_visible);
                        var $menu_items = mapdoc_select.hSelect('menuWidget').find('li');
                        for(var i = 0; i < $menu_items.length; i++){

                            var title = $($menu_items[i]).find('div').text();

                            if(selected_opts.indexOf(title) > -1){
                                $($menu_items[i]).addClass('activated-mapdoc');
                            }else{
                                $($menu_items[i]).removeClass('activated-mapdoc');
                            }
                        }

                        if(selected_opts.length < 2){
                            mapdoc_select.removeClass('multi-select');
                        }else{
                            mapdoc_select.addClass('multi-select');
                        }

                        mapdoc_select.hSelect('menuWidget').css('width', '250px');
                        mapdoc_select.hSelect('menuWidget').find('div.ui-menu-item-wrapper').addClass('truncate');
                    },
                    /*'change': function(event, ui){

                    },*/
                    'close': function(event, ui){ // check if we need to keep it open, only if multi-select

                        var $selected_opt = $(event.currentTarget).is('li') ? $(event.currentTarget) : $(event.currentTarget).parent();

                        if(mapdoc_select.hasClass('multi-select') && $selected_opt.hasClass('ui-menu-item')){
                            mapdoc_select.hSelect('open');
                        }
                    },
                    'select': function(event, ui){

                        var $selected_opt = $(event.currentTarget).is('li') ? $(event.currentTarget) : $(event.currentTarget).parent();

                        if(ui.item.value == ''){ //current result set - all mapdocs are off

                            mapdoc_select.hSelect('menuWidget').find('.activated-mapdoc').removeClass('activated-mapdoc');

                            that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false); //hide all other mapdocs
                            mapdoc_visible = {};

                            mapdoc_select.removeClass('multi-select');
                        }else if($selected_opt.hasClass('activated-mapdoc')){ // 
                            //on the second click - switch off current map document 
                            /* 2022-07-29
                            $selected_opt.removeClass('activated-mapdoc');
                            that.toggleMapDocument(mapdoc_select.val(), false);
                            */
                        }else{

                            if(mapdoc_select.hasClass('multi-select')){ // enable selected option
                                $selected_opt.addClass('activated-mapdoc');
                                that.toggleMapDocument(mapdoc_select.val(), true);
                            }else{ // disable previous selection, enabled current selection

                                mapdoc_select.hSelect('menuWidget').find('.activated-mapdoc').removeClass('activated-mapdoc');

                                that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false);
                                mapdoc_visible = {};

                                $selected_opt.addClass('activated-mapdoc');
                                that.toggleMapDocument(mapdoc_select.val(), true);
                            }
                        }
                    }
                });

                mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text('Current result set');
            });
            
            options.mapwidget.mapping('adjustToolbarHeight');
        },

        //
        // select map document in treeview - it makes it visible or triggers loading of content
        //
        toggleMapDocument: function(mapdoc_id, show_doc=true){

            mapdoc_id = mapdoc_id.split(',');
            
            if($.isFunction($('body').fancytree)){
            
                var tree = mapdoc_treeview.fancytree("getTree");
                var selected = 0;

                tree.visit(function(node){
                    if( window.hWin.HEURIST4.util.findArrayIndex(node.key, mapdoc_id)>=0){
                        node.setSelected( show_doc );
                        selected++;
                        if(selected==mapdoc_id.length) return false;
                    }
                });
            }
        },
        
        //
        // adds new mapdoc record
        //
        createNewMapDocument: function(event){
            _createNewMapDocument(event);
        },

        //
        // creates virtual mapspace from given layer_ids
        //
        createVirtualMapDocument: function(layer_ids, dfd_top){
            
            options.hasTempMap = false;
            var dfd = new $.Deferred();
            mapDocuments.createVirtualMapDocument(layer_ids, dfd);
            
            $.when( dfd.promise() ).done(
                function(data){
                    options.hasTempMap = true;
                    //refresh list of tempmap
                    var grp_div = options.container.find('.svs-acordeon[grpid="tempmap"]');
                    _defineContent('tempmap', null, grp_div.find('.ui-accordion-content'));
                    that.updatePanelVisibility();
                    that.setHeight();
                    
                    setTimeout(function(){
                        mapDocuments.zoomToMapDocument('temp');
                        
                        if(dfd_top) dfd_top.resolve();
                    },500);
                }
            );
            
        },

        // 
        // returns default style for mapdoc or layer
        //
        getSymbology: function(mapdoc_id, layer_id) {
            return mapDocuments.getSymbology( mapdoc_id, layer_id );
        },
        
        //
        //  get all layers and datasources of document
        //
        getMapDocumentRecordset: function(mapdoc_id){
            return mapDocuments.getMapDocumentRecordset(mapdoc_id);
        },
        
        //
        //
        //
        getMapDocuments: function(){
            return mapDocuments;
        },

        //
        // returns list of mapdocuments ids
        // mode - all|loaded|visible
        // 
        getMapDocumentsIds: function( mode ) {
            var res = [];
            var tree = mapdoc_treeview.fancytree("getTree");
            tree.visit(function(node){
                if(node.data.type=='mapdocument'){
                    if((mode=='visible'&& node.isSelected()) ||
                       (mode=='loaded'&& node.hasChildren()) || (mode=='all'))
                    {
                        res.push(node.key);
                    }
                }
            });
            return res;
        },
        
        //
        // adds new layer to search results mapdoc
        // data - recordset, heurist query or json
        // it sends request to server and obtain geojson to be passed to mapping.addGeoJson
        //
        addSearchResult: function( data, dataset_name, preserveViewport )
        {
            var record = mapDocuments.addSearchResult( 0, data, dataset_name, preserveViewport );
            
            //refresh search results
            _refreshSearchContent(); 
            
            return record;
        },

        //
        // converts given recordset to geojson and pass it to to mapping.addGeoJson
        //
        addRecordSet: function(recset, dataset_name) {
            
            var record = mapDocuments.addRecordSet( 0, recset, dataset_name );
            
            //refresh search results 
            _refreshSearchContent(); 
            
            return record;
            
        },
        
        //
        //  add entries into search result mapdocument (id=0) and refresh legend
        // (see hMapLayer2._addQueryLayer)        
        //
        addLayerRecords: function( layers_ids ) {
            
            mapDocuments.addLayerRecords( 0, layers_ids, function(){
                _refreshSearchContent();     
            } );
            
        },
        
        //
        // show/hide datasets
        // visibility - true or false to show/hide entire layer or array of record ids 
        //
        setLayersVisibility: function (mapdoc_ID, _selection, visibility_set){
            
            if(!(mapdoc_ID>=0)) mapdoc_ID = 0;
            
            function __setVis(recID, record){
                
                    var layer_rec = mapDocuments.getLayer(mapdoc_ID, recID);
                    if(layer_rec){
                        var curr_visible = (layer_rec['layer']).isVisible();
                        if(visibility_set!==curr_visible){
                            (layer_rec['layer']).setVisibility( visibility_set );  
                        }
                        
                    } 
            }
            
            if($.isArray(_selection)){
                for(var i=0; i<_selection.length; i++){
                     __setVis(_selection[i]);
                }
            }else{
                _selection.each(__setVis);
            }
            _refreshSearchContent();
        },

        //
        //
        // returns leaflet layer_id by database mapdoc and name 
        // dataset = {mapdoc_id:, dataset_name:, dataset_id: }
        //
        getLayerNativeId: function( dataset ){

            if(dataset){
                if(dataset.native_id>=0){
                    return dataset.native_id;
                
                }else if(dataset.mapdoc_id>=0){
                    var layer = mapDocuments.getLayerByName(dataset.mapdoc_id, dataset.dataset_name, dataset.dataset_id);
                    if(layer){
                        return layer.getNativeId();
                    }
                }
            }
            return 0;
            
        },
        
        //
        // returns layer OR map document (if recid is not defined)
        //
        getLayer: function( mapdoc_id, recid ){
            return mapDocuments.getLayer(mapdoc_id, recid);
        },
                
        //
        // switch base map layer
        //
        loadBaseMap: function( e ){

            var idx = 0;

            if(window.hWin.HEURIST4.util.isNumber(e) && e>=0){
                idx = e;
            }else if(typeof e == 'string'){
                idx = options.container.find('input[data-mapid="'+e+'"]').attr('data-mapindex');
            }else{
                idx = $(e.target).attr('data-mapindex');
            }

            options.mapwidget.mapping('loadBaseMap', idx);
            options.container.find('input[data-mapindex="'+idx+'"]').attr('checked',true);

            options.container.find('input[data-mapindex="'+idx+'"]').parent().after(options.container.find('#basemap_filter_btns'));
        },
        
        //
        //
        //
        toggle: function(){
            
            if(isExpanded){
                _onCollapse();
            }else{
                _onExpand();
            }
        }
        
    }

    _init( _options );
    return that;  //returns object
};

        
        