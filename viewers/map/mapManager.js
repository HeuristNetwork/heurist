/**
* map_control.js - manage layers and proejcts
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
        var container = $('<div>').css({'width':'200px'});
        return container[0];
    },

    onRemove: function(map) {
        // Nothing to do here
    }
});

L.control.manager = function(opts) {
    return new L.Control.Manager(opts);
}


//        
// create accordion with 3(4) panes
// load prededifned list of base layers
// call refresh map documents
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
    },
        
    nativemap = null,
    mapDocuments = null, //hMapDocument for db map docs,  Note: mapdocument with index=0 is search results
    
    basemaplayer =null,  //current basemap layer
    
    map_providers = [
    {name:'MapBox', options:{accessToken: 'pk.eyJ1Ijoib3NtYWtvdiIsImEiOiJjanV2MWI0Y3Awb3NmM3lxaHI2NWNyYjM0In0.st2ucaGF132oehhrpHfYOw'
    , attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://    creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>'
    }},
    {name:'Esri.WorldStreetMap'},
    {name:'Esri.WorldTopoMap'},
    {name:'Esri.WorldImagery'},
    //{name:'Esri.WorldTerrain'},
    {name:'Esri.WorldShadedRelief'},
    {name:'OpenStreetMap'},
    //{name:'OpenPtMap'},
    {name:'OpenTopoMap'},
    {name:'Stamen.Toner'},
    {name:'Stamen.TerrainBackground'},
    //{name:'Stamen.TopOSMRelief'},
    //{name:'OpenWeatherMap'}
    {name:'Esri.NatGeoWorldMap'},
    {name:'Esri.WorldGrayCanvas'}
    ];

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init (_options ) {
       
        options = $.extend(options, _options);

        options.container = $(options.container);
        nativemap =  options.mapwidget.mapping('getNativemap');
        
        mapDocuments = hMapDocument( { mapwidget:options.mapwidget } ); 
        
        $('<div>').attr('grpid','basemaps').addClass('svs-acordeon')
                .append( _defineHeader('Base Maps', 'basemaps'))
                .append( _defineContent('basemaps') ).appendTo(options.container);        
        $('<div>').attr('grpid','search').addClass('svs-acordeon')
                .append( _defineHeader('Result Sets', 'search'))
                .append( _defineContent('search') ).appendTo(options.container);
        $('<div>').attr('grpid','mapdocs').addClass('svs-acordeon')
                .append( _defineHeader('Map Documents', 'mapdocs'))
                .append( _defineContent('mapdocs') ).appendTo(options.container);
        
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

                }
            });
        });
        //replace all ui-icon-triangle-1-s to se
        cdivs.find('.ui-accordion-header-icon').css('font-size','inherit');
        cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');        
       
        that.loadBaseMap(0);
        
        options.container.addClass('ui-widget-content')
            .css({'margin-right':'5px',border:'1px solid gray',padding:'4px','font-size':'0.97em'});
    }

    //
    //
    //    
    function _defineHeader(name, domain){

        var sIcon = '';
        //<span class="ui-icon ui-icon-'+sIcon+'" ' + 'style="display:inline-block;padding:0 4px"></span>
        
        var $header = $('<h3 grpid="'+domain+'" class="hasmenu">' + sIcon + '<span style="vertical-align:top;">'
            + name + '</span>')
            .css({color:'rgb(142, 169, 185)'}).addClass('tree-accordeon-header');

        /*    
        if('dbs'!=domain){
            var context_opts = this._getAddContextMenu(domain);
            $header.contextmenu(context_opts);
        }
        */

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
            content = '';
            for (var k=0; k<map_providers.length; k++){
                content = content + '<label><input type="radio" name="basemap" data-mapindex="'+k+'">'
                                  + map_providers[k]['name']+'</label><br>';
            }
            content = $(content);
            content.find('input').on( { change: that.loadBaseMap });
            
        }else  if(groupID=='mapdocs'){
            //load list of mapddocuments
            content = $('<div>');
            
            mapDocuments.loadMapDocuments( function(resdata){
                        _refreshMapDocumentTree( resdata, content );
                    } );
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
                    recName = resdata.fld(record, 'rec_Title');
                    
                    var $res = {};  
                    $res['key'] = recID;
                    $res['title'] = recName;
                    $res['type'] = 'mapdocument';
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
                                    mapDocuments.openMapDocument(node.key, dfd);
                                    //return dfd.promise();
                                    
                                },
                                expand: function(e, data){

                                    var node = data.node;
//console.log(node.children);                                    
                                    if(node.data.type=='mapdocument' && node.children.length==0) {
                                        node.setSelected(true, {noEvents:true} );
                                    }
                                    
                                },
                                loadChildren: function(e, data){
//
//console.log('loaded '+data.node.title+'  '+data.node.children.length);
                                    setTimeout(function(){
                                    $.each(data.node.children, function( idx, item ){
                                            _defineActionIcons( item );
                                    }) }, 500);                                           
                                },
                                select: function(e, data) {
                                    
                                    var node = data.node;
                                    if(node.data.type=='mapdocument'){
                                        //if not expanded, expand, it loads layers
                                        var mapdoc_id = node.key;
                                        if(!node.isExpanded() && !mapDocuments.isLoaded(mapdoc_id)){
                                            node.setExpanded(true);
                                        }else{

                                            mapDocuments.setMapDocumentVisibulity(mapdoc_id, node.isSelected());
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
                                }
                                // The following options are only required, if we have more than one tree on one page:
                                //          initId: "treeData",
                                //cookieId: "fancytree-Cb3",
                                //idPrefix: "fancytree-Cb3-"
                            });        
    
    }
    
    //
    //  Adds menu buttons
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
            
            var actionspan = $('<div class="svs-contextmenu3" '
                    +(mapdoc_id>=0?('" data-mapdoc="'+mapdoc_id+'"'):'')
                    +(recid>0?('" data-recid="'+recid+'"'):'')+'>'
                +'<span class="ui-icon ui-icon-zoomin" title="Zoom to '+item.data.type+' extent"></span>'
                +'<span class="ui-icon ui-icon-pencil" title="Edit '
                    +(item.data.type=='mapdocument' || mapdoc_id>0?(item.data.type+' record'):' symbology')+'"></span>'
                +(item.data.type=='mapdocument' && mapdoc_id>0
                    ?'<span class="ui-icon ui-icon-plus" title="Add new layer to mapdocument"></span>'
                    :'')    
                + (((mapdoc_id>0 && recid==-1)||mapdoc_id==0)
                    ?'<span class="ui-icon ui-icon-close" title="Unload and remove from map"></span>':'')+
                +'</div>').appendTo(parent_span);

            actionspan.find('.ui-icon').click(function(event){
                var ele = $(event.target);
                //timeout need to activate current node    
                setTimeout(function(){                         
                    var recid = ele.parent('.svs-contextmenu3').attr('data-recid');
                    var mapdoc_id = ele.parent('.svs-contextmenu3').attr('data-mapdoc');
                    
                    
                        if(ele.hasClass('ui-icon-zoomin')){
                            
                            if(recid>0){
                                
                                if(mapdoc_id>=0){
                                    var layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                    if(layer_rec) (layer_rec['layer']).zoomToLayer();
                                } 
                                
                            }else if(mapdoc_id>0){
                                    mapDocuments.zoomToMapDocument(mapdoc_id);
                            }
                            
                        }else if(ele.hasClass('ui-icon-plus')){ //add new layer to ma document
                        
                            //open layer selector dialog
                        
                            
                        }else if(ele.hasClass('ui-icon-pencil')){
                            
                                if(mapdoc_id>0){
                                    //edit layer or mapdocument record
                                    window.hWin.HEURIST4.ui.openRecordEdit(recid>0?recid:mapdoc_id);
                                }else{
                                    // get layer record, take symbology field and title 
                                    // open symbology editor
                                    // on exit 1) call mapLayer.applyStyles
                                    //         2) change title in tree and timeline
                                    mapDocuments.editSymbology(0, recid, function(new_title, new_style){
                                        //update layer title in legend and timeline
                                        if(new_title){
                                            item.title = new_title;
                                            parent_span.find('span.fancytree-title').text( new_title );
                                        }
                                        
                                        //render new symbology in legend
                                        
                                    });
                                }
                        }else if(ele.hasClass('ui-icon-close')){
                            
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
                    var ele = $(node).find('.svs-contextmenu3');
                    ele.css({'display':'inline-block'});//.css('visibility','visible');
                }
            );               
            $(parent_span).mouseleave(
                _onmouseexit
            );
        }
    }

    //
    // returns index
    //
    function _findInSearchResults(byId, byName){
        var idx = -1;
        for (var k=0; k<map_searchresults.length; k++){
            if( map_searchresults[k]['layer_id'] == byId || 
                map_searchresults[k]['layer_name'] == byName){
                idx = k;
                break;
            }
        }
        return idx;
    }
    
    
    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //
        // data - layer name and reference
        // 
        defineContent: function(groupID, data){
            //find group div
            var grp_div = options.container.find('.svs-acordeon[grpid="'+groupID+'"]');
            //define new
            _defineContent(groupID, data, grp_div.find('.ui-accordion-content'));
        },

        removeEntry: function(groupID, data){

            var idx = _findInSearchResults(data['layer_id'], data['layer_name']);

            if(idx>=0){
                layer_id = map_searchresults[idx]['layer_id'];
                that.layerAction( layer_id, 'remove' );
            }
        },
        
        //
        // adds new layer to search results mapdoc
        // data - recordset, heurist query or json
        //
        addLayerToSearchResults: function( data, dataset_name )
        {
            mapDocuments.addLayer( 0, data, dataset_name );
            
            //refresh search results 
            that.defineContent( 'search' );
        },

        //
        // switch base map layer
        //
        loadBaseMap: function( e ){

            var mapindex = 0;

            if(window.hWin.HEURIST4.util.isNumber(e) && e>=0){
                mapindex = e;
            }else{
                mapindex = $(e.target).attr('data-mapindex');
            }

            var provider = map_providers[mapindex];

            if(basemaplayer!=null){
                basemaplayer.remove();
            }

            basemaplayer = L.tileLayer.provider(provider['name'], provider['options'] || {})
            .addTo(nativemap);        

            options.container.find('input[data-mapindex="'+mapindex+'"]').attr('checked',true);

        }

    }

    _init( _options );
    return that;  //returns object
};

        
        