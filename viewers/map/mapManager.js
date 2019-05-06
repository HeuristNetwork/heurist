/**
* map_control..js - manage layers and proejcts
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
    mapDocuments = null, //hMapDocument for db map docs
    searchResults = null, //hMapDocument that contains the only one fake mapdoc with search results as layers
    
    hidden_layers = [],  //hidden layers (removed from map)
    
    map_searchresults = [], //list of layer ids and names {layer_id: , layer_name:} 
    
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
            
            if(data){
                if($.isArray(data)){
                    map_searchresults = data;
                }else{
                    //replace or add new layer
                    var idx = _findInSearchResults(null, data['layer_name']);
                    if(idx<0){
                        //not found - add new entry
                        map_searchresults.push(data);
                    }else{
                        //replace to new layer id
                        map_searchresults[idx]['layer_id'] = data['layer_id'];
                    }
                }
            }    
            content = '';
            for (var k=0; k < map_searchresults.length; k++){
                //internal leaflet layer id
                //var layer = 
                var layer_id = map_searchresults[k]['layer_id']; //layer._leaflet_id;
                var layerName = map_searchresults[k]['layer_name'];
                
                content = content + '<div style="display:block;padding:2px;" data-layerid="'+layer_id+'">'
                    +'<input type="checkbox" checked style="margin-right:5px" data-layerid="'+layer_id+'" data-action="visibility">'
                    +'<span/>' //symbology
                    +'<label style="padding-left:1em">' + layerName + '</label>'
                    +'<div class="svs-contextmenu ui-icon ui-icon-close"  data-action="remove" data-layerid="'+layer_id+'"></div>'
                    +'<div class="svs-contextmenu ui-icon ui-icon-pencil" data-action="edit" data-layerid="'+layer_id+'"></div>'
                    +'<div class="svs-contextmenu ui-icon ui-icon-zoomin" data-action="zoom" data-layerid="'+layer_id+'"></div>'
                    +'</div>';

            }
            if(content!=''){
                content = $(content);
                content.find('input').on({ change: _layerAction });
                content.find('div.svs-contextmenu').on({ click: _layerAction });
            }
            
        }else if(groupID=='basemaps'){
            // load list of predefined base layers 
            // see extensive list at leaflet-providers.js
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
            mapDocuments = hMapDocument( {container:content, mapwidget:options.mapwidget, 
                    onRefreshList:function(resdata){
                        _refreshMapDocumentTree( resdata, content);
                    }} );
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
    // it is invoked on map document list retieve
    //
    function _refreshMapDocumentTree( resdata, tree_container ){

        //create treeview data
        var treedata = [];
        
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
                                    var mapdoc_id = node.key;
                                        
console.log('expand '+mapdoc_id);       
                                    _defineActionIcons( data.node );                      
                                    $.each(data.node.children, function( idx, item ){
                                            _defineActionIcons( item );
                                    });
                                    
                                },
                                loadChildren: function(e, data){
/*console.log(data.node.key)
                                    $.each(data.node.children)

                                    $.each( $(data.node.li).find('.fancytree-node'), function( idx, item ){
                                        _defineActionIcons(item);
                                    });
                                    */
                                    /*
                                    var node = data.node;
                                    if(node.data.type=='mapdocument'){
                                        //after loading of children - loads
                                        var mapdoc_id = node.key;
                                        mapDocuments.openMapDocument(node.key);
                                    }*/
                                },
                                select: function(e, data) {
                                    
                                    var node = data.node;
                                    if(node.data.type=='mapdocument'){
                                        //if not expanded, expand, it loads layers
                                        var mapdoc_id = node.key;
                                        if(!node.isExpanded() && !mapDocuments.isLoaded(mapdoc_id)){
                                            node.setExpanded(true);
                                        }
                                        //
                                        //mapDocuments.openMapDocument(node.key, dfd);
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
        var item_li = item.li, 
            recid = item.key;
        
        if($(item).find('.svs-contextmenu3').length==0){
            
            var actionspan = $('<div class="svs-contextmenu3" data-recid="'+recid+'">'
                +'<span class="ui-icon ui-icon-zoomin" title="Zoom to layer extent"></span>'
                +'<span class="ui-icon ui-icon-pencil" title="Edit symbology"></span>'
                +'<span class="ui-icon ui-icon-close" title="Remove from map"></span>'
                +'</div>').appendTo(item);

            actionspan.find('.ui-icon').click(function(event){
                var ele = $(event.target);
                //timeout need to activate current node    
                setTimeout(function(){                         
                    var recid = ele.parent('.svs-contextmenu3').attr('data-recid');
                    if(recid>0){
                        if(ele.hasClass('ui-icon-zoomin')){
                            
                            
                            //mapDocuments.  
                            //record['layer']
                            
                            
                        }else if(ele.hasClass('ui-icon-pencil')){
                              window.hWin.HEURIST4.ui.openRecordEdit(recid);
                        }else if(ele.hasClass('ui-icon-close')){
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

            $(item).hover(
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
            $(item).mouseleave(
                _onmouseexit
            );
        }
    }
    
    //
    //
    //
    function _layerAction( e ){
        
        var layerid = $(e.target).attr('data-layerid');
        var action  = $(e.target).attr('data-action'); 
        var value = null;
        if(action=='visibility'){
            value = $(e.target).is(':checked');
        }
     
        that.layerAction( layerid, action, value );
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

        },

        //
        //
        //
        layerAction: function( layer_id, action, value ){

            var affected_layer = null;
            var is_hidden = false;

            for (var k=0; k<hidden_layers.length; k++){
                if( hidden_layers[k]['_leaflet_id'] == layer_id ){
                    affected_layer = hidden_layers[k];
                    is_hidden = true;
                    break;
                }
            }
            if(!is_hidden){
                nativemap.eachLayer(function(layer){
                    if(layer._leaflet_id==layer_id){
                        affected_layer = layer;
                        return;
                    }
                });
            }

            if(affected_layer){

                if(action=='visibility'){
                    if(value===false){
                        hidden_layers.push(affected_layer);
                        affected_layer.remove();
                    }else{
                        affected_layer.addTo(nativemap);
                    }
                }else if(action=='zoom'){

                    var bounds = affected_layer.getBounds();
                    nativemap.fitBounds(bounds);

                }else if(action=='edit'){ //@todo it when map is opened in standalone page

                    var current_value = affected_layer.options.default_style;
                    ///console.log(affected_layer);                   
                    current_value.sym_Name = affected_layer.options.layer_name;
                    //open edit dialog to specify symbology
                    window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_value, function(new_value){

                        //rename in list
                        if(!window.hWin.HEURIST4.util.isempty(new_value.sym_Name)
                            && current_value.sym_Name!=new_value.sym_Name)
                        {
                            affected_layer.options.layer_name = new_value.sym_Name;

                            var idx = _findInSearchResults(affected_layer._leaflet_id);
                            map_searchresults[idx]['layer_name'] = new_value.sym_Name;       
                            delete new_value.sym_Name;
                        }
                        //update style
                        affected_layer.options.default_style = new_value;
                        affected_layer.eachLayer(function(layer){
                            layer.feature.default_style = new_value;
                        });  
                        if(options.mapwidget && options.mapwidget.mapping('instance')){
                            options.mapwidget.mapping('resetStyle', affected_layer);
                        }
                        /*affected_layer.setStyle( affected_layer.style );
                        if(!is_hidden){
                        affected_layer.remove();
                        affected_layer.addTo(nativemap);
                        }*/
                        that.defineContent('search');
                    });
                }
            }

            if(action=='remove'){
                if(affected_layer) affected_layer.remove();
                var idx = _findInSearchResults(layer_id);
                map_searchresults.splice(idx,1);
                that.defineContent('search');
            }
        }
    }

    _init( _options );
    return that;  //returns object
};

        
        