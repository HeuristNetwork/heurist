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
    
function hMapManager( _element, _nativemap, _mapping_widget )
{    
    var _className = "MapManager",
    _version   = "0.4";

    // default options
    var main_container = null,
    nativemap = null,
    mapping_widget = null,
    
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
    function _init(_element, _nativemap, _mapping_widget) {
        
        main_container = $(_element);
        nativemap =  _nativemap;
        mapping_widget = _mapping_widget;
        
        $('<div>').attr('grpid','basemaps').addClass('svs-acordeon')
                .append( _defineHeader('Base Maps', 'basemaps'))
                .append( _defineContent('basemaps') ).appendTo(main_container);        
        $('<div>').attr('grpid','search').addClass('svs-acordeon')
                .append( _defineHeader('Result Sets', 'search'))
                .append( _defineContent('search') ).appendTo(main_container);
        $('<div>').attr('grpid','mapdocs').addClass('svs-acordeon')
                .append( _defineHeader('Map Documents', 'mapdocs'))
                .append( _defineContent('mapdocs') ).appendTo(main_container);
        
        //init list of accordions
        var keep_status = window.hWin.HAPI4.get_prefs('map_control_status');
        if(!keep_status) {
            keep_status = { 'search':true };
        }
        else keep_status = $.parseJSON(keep_status);
        
        var cdivs = main_container.find('.svs-acordeon');
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
        
        main_container.addClass('ui-widget-content')
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
        redefineContent: function(groupID, data){
            //find group div
            var grp_div = main_container.find('.svs-acordeon[grpid="'+groupID+'"]');
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

            main_container.find('input[data-mapindex="'+mapindex+'"]').attr('checked',true);

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
                        if(mapping_widget && mapping_widget.mapping('instance')){
                            mapping_widget.mapping('resetStyle', affected_layer);
                        }
                        /*affected_layer.setStyle( affected_layer.style );
                        if(!is_hidden){
                        affected_layer.remove();
                        affected_layer.addTo(nativemap);
                        }*/
                        that.redefineContent('search');
                    });
                }
            }

            if(action=='remove'){
                if(affected_layer) affected_layer.remove();
                var idx = _findInSearchResults(layer_id);
                map_searchresults.splice(idx,1);
                that.redefineContent('search');
            }
        }
    }

    _init( _element, _nativemap, _mapping_widget );
    return that;  //returns object
};

        
        