/**
* mapManager.js - manage layers and proejcts
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

    // default options
    // default options
    options = {
        container:null,  //@todo all ui via mapcontrol
        mapwidget:null,   
        visible_panels:null
    },
        
    mapDocuments = null, //hMapDocument for db map docs,  Note: mapdocument with index=0 is search results
    mapdoc_treeview = null,
    btn_expand = null,
    btn_collapse = null,
    
    maxHeight = 9999999,
    
    isExpanded = false,
    keepWidth = 200,
    
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
            .append($('<span class="ui-icon ui-icon-carat-1-w" style="height:100%;color:gray;left:-3px;font-size:10px;">'));
        
        mapDocuments = hMapDocument( { mapwidget:options.mapwidget } ); 
        
        $('<div>').attr('grpid','search').addClass('svs-acordeon outline_suppress')
                .append( _defineHeader('Result Sets', 'search'))
                .append( _defineContent('search') ).appendTo(options.container);
        $('<div>').attr('grpid','mapdocs').addClass('svs-acordeon outline_suppress')
                .append( _defineHeader('Map Documents', 'mapdocs'))
                .append( _defineContent('mapdocs') ).appendTo(options.container);
        $('<div>').attr('grpid','basemaps').addClass('svs-acordeon outline_suppress')
                .append( _defineHeader('Base Maps', 'basemaps'))
                .append( _defineContent('basemaps') ).appendTo(options.container);        
                
        //init list of accordions
        var keep_status = window.hWin.HAPI4.get_prefs('map_control_status');
        if(!keep_status) {
            keep_status = { 'search':true };
        }
        else keep_status = $.parseJSON(keep_status);
        
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
            
        that.updatePanelVisibility();
    }

    //
    //
    //    
    function _defineHeader(name, domain){

        var sIcon = '';
        //<span class="ui-icon ui-icon-'+sIcon+'" ' + 'style="display:inline-block;padding:0 4px"></span>
        
        var $header = $('<h3 grpid="'+domain+'" class="hasmenu">' + sIcon + '<span style="vertical-align:top;">'
            + name + '</span>')
            .css({color:'rgb(142, 169, 185)'}).addClass('tree-accordeon-header outline_suppress');

        /*    
        if('dbs'!=domain){
            var context_opts = this._getAddContextMenu(domain);
            $header.contextmenu(context_opts);
        }
        */
        
         if(domain=='mapdocs'){
             
            var append_link = $('<a title="create new map document">',{href:'#'})
                .html('<span class="ui-icon ui-map-document"></span>')
                .css({height:'14px',float:'right',width: '16px',background: 'none'})
                .click(function(event){
                    
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
                    
                }).appendTo($header);
        }

        return $header

    }
    
    //
    // redraw legend entries
    //
    function _defineContent(groupID, data, container){
        
        var content = null;
        
        if(groupID=='search'){
            
            content = $('<div>');
            
            var resdata = mapDocuments.getTreeData(0);
            
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
            content = $(content);
            content.find('input').on( { change: that.loadBaseMap });
            
        }else  if(groupID=='mapdocs'){
            //load list of mapddocuments
            mapdoc_treeview = $('<div>');
            
            mapDocuments.loadMapDocuments( function(resdata){
                        _refreshMapDocumentTree( resdata, mapdoc_treeview );
                        options.mapwidget.mapping('onInitComplete');
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
    // adds new mapdoc entry in mapdoc tree
    //
    function _addToMapDocumentTree( resdata ){
        
            if(resdata==null){

                var $res = {};  
                $res['key'] = 1236;
                $res['title'] = 'TTTTTTEST';
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
                                                btns.find('span.ui-icon-trash').css({color:'black'});
                                            }
                                                
                                            
                                            //$.each(data.node.children, function( idx, item ){
                                            //    _defineActionIcons( item );
                                            //}) 
                                        }, 500);                                           
                                    },
                                    select: function(e, data) {  //show/hide
                                        
                                        var node = data.node;
                                        if(node.data.type=='mapdocument'){
                                            //if not expanded, expand, it loads layers (opens mapdocument)
                                            var mapdoc_id = node.key;
                                            if(!node.isExpanded() && !mapDocuments.isLoaded(mapdoc_id)){
                                                node.setExpanded(true); //load content - init lazy load - see lazyLoad ->  mapDocuments.openMapDocument
                                            }else{

                                                mapDocuments.setMapDocumentVisibility(mapdoc_id, node.isSelected());
                                            }
                                            //
                                            //mapDocuments.openMapDocument(node.key, dfd);
                                        }else if(node.data.type=='layer'){
                                            
                                            var mapdoc_id = node.data.mapdoc_id;
                                            if(mapdoc_id>=0){
                                                var layer_rec = mapDocuments.getLayer(mapdoc_id, node.key);
                                                if(layer_rec) (layer_rec['layer']).setVisibility( node.isSelected() );
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
                                        _defineActionIcons( item );
                                    }
                                    // The following options are only required, if we have more than one tree on one page:
                                    //          initId: "treeData",
                                    //cookieId: "fancytree-Cb3",
                                    //idPrefix: "fancytree-Cb3-"
                                });     
                            
        }
        
        
        if(treedata.length==0){    
            tree_container.append('<span class="empty_msg" style="font-style:italic">none</span>');
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
            
            var actionspan = $('<div class="svs-contextmenu3" '
                    +(mapdoc_id>=0?('" data-mapdoc="'+mapdoc_id+'"'):'')
                    +(recid>0?('" data-recid="'+recid+'"'):'')+'>'
                +'<span class="ui-icon ui-icon-arrow-4-diag" '
                    +((item.data.type=='mapdocument' && !item.data.extent)?'style="color:gray"':'')
                    +' title="Zoom to '+item.data.type+' extent"></span>'
                + (isEditAllowed
                   ?('<span class="ui-icon ui-icon-pencil" title="'
                    + ((mapdoc_id>0) 
                        ?(item.data.type=='mapdocument'?'Modify the map document':'Change symbology and behaviour of map layer')
                        :'Change symbology')
                    +'"></span>')
                    :'')                                                              //arrowstop-1-s
                + (isEditAllowed && (item.data.type=='mapdocument' && mapdoc_id>0)
                    ?('<span class="ui-icon ui-map-layer" title="Add map layer">'
                        +'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                        +'</span>')
                    :( (isEditAllowed && mapdoc_id==0)
                        ?'<span class="ui-icon ui-icon-arrowstop-1-s" title="Save result set as layer"></span>'
                        :''))
                        
                + (isEditAllowed && ((mapdoc_id>0 && recid==-1)||mapdoc_id==0)
                    ?'<span class="ui-icon ui-icon-trash" '
                    +((item.data.type=='mapdocument')?'style="color:gray"':'')
                    +' title="'+(item.data.type=='mapdocument'?'Delete map document':'Remove map layer')
                    +'"></span>':'')+
                +'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"/>').appendTo(parent_span);
                
                
            actionspan.find('.ui-icon').click(function(event){
                var ele = $(event.target);
                var parent_span = ele.parents('span.fancytree-node');
                
                function __in_progress(){
                    parent_span.find('.svs-contextmenu4').show();
                    parent_span.find('.svs-contextmenu3').hide();
                }

                //timeout need to activate current node    
                setTimeout(function(){                         
                    var recid = ele.parents('.svs-contextmenu3').attr('data-recid');
                    var mapdoc_id = ele.parents('.svs-contextmenu3').attr('data-mapdoc');
                    
                        if(ele.hasClass('ui-icon-arrow-4-diag')){
                            
                            if(recid>0){
                                
                                if(mapdoc_id>=0){
                                    var layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                    if(layer_rec) (layer_rec['layer']).zoomToLayer();
                                } 
                                
                            }else if(mapdoc_id>0){
                                    mapDocuments.zoomToMapDocument(mapdoc_id);
                            }
                            
                        }else if(ele.hasClass('ui-icon-plus')){ //add new layer to map document
                        
                            __in_progress();
                            if(mapdoc_id>0){

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
                            }else{
                                mapDocuments.saveResultSetAsLayerRecord(recid, function(data){
                                        ele.hide();
                                        parent_span.find('.svs-contextmenu4').hide();
                                });
                            }
                            
                        }else if(ele.hasClass('ui-icon-pencil')){
                            
                                if(mapdoc_id>0){

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
        options.container.css({'width':keepWidth,'overflow-y':'auto',padding:'4px'}).resizable( "enable" );
        that.updatePanelVisibility();
        that.setHeight();
    }

    function _onCollapse(){
        isExpanded = false;
        keepWidth = options.container.width();
        btn_expand.show();
        btn_collapse.hide();
        options.container.css({'width':'22px','height':'22px','overflow-y':'hidden','overflow-x':'hidden',padding:'1px'})
            .resizable( "disable" );
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
        
        // params = [basemaps,search,mapdocuments|onedoc]
        //
        updatePanelVisibility: function(params){
            if(params){
                options.visible_panels = params; //array of visible panels
            }
            if(!options.visible_panels) options.visible_panels = ['all'];
            
            function __set(val){
                var is_visible = (options.visible_panels.indexOf('all')>=0 || options.visible_panels.indexOf(val)>=0);
                var ele = options.container.find('.svs-acordeon[grpid="'+val+'"]');
                if(is_visible){
                    ele.show();
                }else{
                    ele.hide();
                }
            }
            
            if(isExpanded){
                __set('basemaps');            
                __set('search');            
                __set('mapdocs'); 
                options.container.find('.ui-resizable-handle').show();
            }else{
                options.container.children('div').hide(); 
            }       
        
            //__set('onedoc');            
        },
        
        //
        // select map document in treeview - it makes it visible or triggers loading of content
        //
        openMapDocument: function(mapdoc_id){
            
            mapdoc_id = mapdoc_id.split(',');
            
            if($.isFunction($('body').fancytree)){
            
                var tree = mapdoc_treeview.fancytree("getTree");
                var selected = 0;

                tree.visit(function(node){
                    if( window.hWin.HEURIST4.util.findArrayIndex(node.key, mapdoc_id)>=0){
                        node.setSelected( true );
                        selected++;
                        if(selected==mapdoc_id.length) return false;
                    }
                });
            }
            
        },
        
        //
        // returns list of mapdocuments ids
        // mode - all|loaded|visible
        // 
        getMapDocuments: function( mode ) {
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
        addSearchResult: function( data, dataset_name )
        {
            var record = mapDocuments.addSearchResult( 0, data, dataset_name );
            
            //refresh search results 
            var grp_div = options.container.find('.svs-acordeon[grpid="search"]');
            _defineContent('search', null, grp_div.find('.ui-accordion-content'));
            //defineContent( 'search' );
            that.setHeight();
            
            return record;
        },

        //
        // converts given recordset to geojson and pass it to to mapping.addGeoJson
        //
        addRecordSet: function(recset, dataset_name) {
            
            var record = mapDocuments.addRecordSet( 0, recset, dataset_name );
            
            //refresh search results 
            var grp_div = options.container.find('.svs-acordeon[grpid="search"]');
            _defineContent('search', null, grp_div.find('.ui-accordion-content'));
            //defineContent( 'search' );
            that.setHeight();
            
            return record;
            
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
        }
        
    }

    _init( _options );
    return that;  //returns object
};

        
        