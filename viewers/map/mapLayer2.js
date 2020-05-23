/**
* filename: LEAFLET layers
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

/**
*  Represents the layer on map
*/
function hMapLayer2( _options ) {
    var _className = "MapLayer",
    _version   = "0.4";

    var options = {
        //mapwidget:              
        //mapdoc_recordset: // recordset to retrieve values from rec_layer and rec_datasource
        
        //rec_layer:       // record of type Heurist layer, it is needed for symbology and min/max zoom
        //rec_datasource:  // record of type map dataseource

        preserveViewport: true  
    };

    var _record,     //datasource record
        _recordset,  //referense to map document recordset
        _parent_mapdoc = null; //map document id
        
    var _nativelayer_id = 0;
    
    var is_inited = false,
        is_visible = false;

    //
    //
    //
    function _init( _options ){

        options = $.extend(options, _options);

        _recordset = options.mapdoc_recordset;
        _record = options.rec_datasource;
        _parent_mapdoc = options.mapdocument_id;
        
        if(options.not_init_atonce) return;
        
        _addLayerToMap();
        
    }
    
    function _addLayerToMap()
    {
        is_inited = true;
        is_visible = true;
        
        //detect layer type
        var rectypeID = _recordset.fld(_record, 'rec_RecTypeID');

        if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] 
            || rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET']
            || rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){
               
               
             if(options.recordset){
                _addRecordSet(); //convert recordset to geojson    
             }else{
                _addQueryLayer();     
             }  

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){

            _addTiledImage();
            
            setTimeout(function(){ _triggerLayerStatus( 'visible' ); },200);

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

            _addImage();

            setTimeout(function(){ _triggerLayerStatus( 'visible' ); },200);
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE'] ||
                 rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_FILE_SOURCE']){  //csv

            _addFileSource();
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_SHP_SOURCE']){
            _addSHP();
        }
    }

    //
    // add tiled image
    //
    function _addTiledImage() {

        var layer_url = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);

        // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
        if(!window.hWin.HEURIST4.util.isempty(layer_url)) {

            var tilingSchema = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA']);
            var mimeType = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE']);
            var minZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MINMUM_ZOOM_LEVEL']);
            var maxZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM_LEVEL']);

            var layer_options = {minZoom:minZoom , maxZoom:maxZoom};
            
            var tileUrlFunc = null; 

            var idx_ccode = window.hWin.HEURIST4.terms.fieldNamesToIndex.trm_ConceptID;

            tilingSchema = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'][tilingSchema];
            mimeType = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'][mimeType];

            if(tilingSchema && tilingSchema[idx_ccode]=='2-549'){ //virtual earth
                
                layer_options['BingLayer'] = true;

                if(layer_url.indexOf('{q}')<0){
                    layer_url = layer_url + '{q}';
                }
                
            }else if(tilingSchema && tilingSchema[idx_ccode]=='2-548'){ //maptiler

                layer_options['MapTiler'] = true;

                if(layer_url.indexOf('{q}')<0){
                    layer_url = layer_url + '{q}'
                                + (mimeType && mimeType[idx_ccode] == '2-540'? ".png" : ".gif");
                }
                
            }else{
                
                if(layer_url.indexOf('{x}')<0){
                    layer_url = layer_url + '/{z}/{x}/{y}'
                                + (mimeType && mimeType[idx_ccode] == '2-540'? ".png" : ".gif");
                }
                
                if(layer_url.indexOf('http://')===0){
                    
                    var mimetype = 'image/'+(mimeType && mimeType[idx_ccode] == '2-540'? "png" : "gif");
                    
                    //load via proxy
                    layer_url = window.hWin.HAPI4.baseURL 
                            + '?db=' + window.hWin.HAPI4.database 
                            + '&mimetype=' + mimetype
                            + '&rurl=' + layer_url; //encodeURIComponent(layer_url);
                }

            }
            
            layer_options._extent = _getBoundingBox();
            
            _nativelayer_id = options.mapwidget.mapping('addTileLayer', 
                                                        layer_url, 
                                                        layer_options, 
                                                        _recordset.fld(_record, 'rec_Title') );
            
        }
    }
    
    //
    // add image
    //
    function _addImage(){

         //obfuscated file id
         var imageFile = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
         
         var image_url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&file='+
                    imageFile[0];
                    
         var worldFileData = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']);
         
         var image_extent = null; //window.hWin.HEURIST4.geo.parseWorldFile( worldFileData, image_width, image_height);
         
         if(image_extent==null) image_extent = _getBoundingBox();
         
          
         _nativelayer_id = options.mapwidget.mapping('addImageOverlay', 
                                                        image_url, 
                                                        image_extent, 
                                                        _recordset.fld(_record, 'rec_Title') );
          
    }

    //
    // parses shp+dbf files and converts them to geojson 
    //
    function _addSHP() {

        var layer_style = _recordset.fld(options.rec_layer || _record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
        var rec_ID = _recordset.fld(_record, 'rec_ID');
                    
        request = {recID:rec_ID};             
        //perform loading kml as geojson
        window.hWin.HAPI4.RecordMgr.load_shp_as_geojson(request,
            function(response){
                if(response){
                    var dataset_name = _recordset.fld(options.rec_layer || _record, 'rec_Title');
                    
                    if(response.status && response.status != window.hWin.ResponseStatus.OK){
                        _triggerLayerStatus( 'error' );
                        
                        if(!window.hWin.HEURIST4.util.isempty(response.message)){
                            response.message = 'Layer : '+dataset_name+'<br><br>'+response.message;
                        }
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        
                        _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                                {geojson_data: response,
                                timeline_data: null,
                                layer_style: layer_style,
                                dataset_name:dataset_name,
                                preserveViewport:options.preserveViewport });
                                
                        _triggerLayerStatus( 'visible' );
                    }
                    
                }
            }
        );          
    }
    
    
    //
    // add kml, csv, tsv or dbf files
    // 
    // or kmlSnippet
    //
    function _addFileSource() {

        var layer_style = _recordset.fld(options.rec_layer || _record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
        var rec_ID = _recordset.fld(_record, 'rec_ID');
            
        //var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_map_source.php?db='
        //            +window.hWin.HAPI4.database+'&format=geojson&recID='+rec_ID;
                    
        request = {recID:rec_ID};             
        //perform loading kml as geojson
        window.hWin.HAPI4.RecordMgr.load_kml_as_geojson(request,
            function(response){
                if(response){
                    var dataset_name = _recordset.fld(options.rec_layer || _record, 'rec_Title');
                    
                    if(response.status && response.status != window.hWin.ResponseStatus.OK){
                        _triggerLayerStatus( 'error' );
                        
                        
                        if(!window.hWin.HEURIST4.util.isempty(response.message)){
                            response.message = 'Layer : '+dataset_name+'<br><br>'+response.message;
                        }
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        
                        var geojson_data = null;
                        var timeline_data = [];
                        if(response['geojson'] && response['timeline']){
                            geojson_data = response['geojson'];
                            timeline_data = response['timeline'];   
                        }else{
                            geojson_data = response;
                        }

                        if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) 
                            || window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data) )
                        {
                                                             
                            _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                                        {geojson_data: geojson_data,
                                        timeline_data: timeline_data,
                                        layer_style: layer_style,
                                        //popup_template: layer_popup_template,
                                        //origination_db: null,
                                        dataset_name:_recordset.fld(options.rec_layer || _record, 'rec_Title'),  //name for timeline
                                        preserveViewport:options.preserveViewport });
                                                             
                        }else {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        _triggerLayerStatus( 'visible' );
                    }
                }
            }
        );          
    }


    //
    // query layer
    //
    function _addQueryLayer(){

        var layer_style = _recordset.fld(options.rec_layer || _record, 
                                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
        var layer_popup_template = _recordset.fld(options.rec_layer || _record, 
                                    window.hWin.HAPI4.sysinfo['dbconst']['DT_POPUP_TEMPLATE']);
        var origination_db = null;
        
        var query = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING']);
        var request = window.hWin.HEURIST4.util.parseHeuristQuery(query);

        if(request.q){

            var server_request = {
                q: request.q,
                rules: request.rules,
                w: request.w,
                //returns strict geojson and timeline data as two separate arrays, withoud details, only header fields rec_ID, RecTypeID and rec_Title
                leaflet: true, 
                simplify: true, //simplify paths with more than 1000 vertices
                zip: 1,
                format:'geojson'};
                
            //dataset origination db can be different from map heurist instance    
            if(!window.hWin.HEURIST4.util.isempty(request.db) && request.db!=window.hWin.HAPI4.database){
                server_request.db = request.db;
                origination_db = request.db;
            }
                
            //perform search see record_output.php       
            window.hWin.HAPI4.RecordMgr.search_new(server_request,
                function(response){

                    var geojson_data = null;
                    var timeline_data = [];
                    var layers_ids = [];
                    if(response['geojson'] && response['timeline']){
                        geojson_data = response['geojson'];
                        timeline_data = response['timeline'];   
                        if(response['layers_ids']) layers_ids = response['layers_ids'];   
                    }else{
                        geojson_data = response;
                    }

                    if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) 
                        || window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data) )
                    {
                                                         
                        _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                                    {geojson_data: geojson_data,
                                    timeline_data: timeline_data,
                                    layer_style: layer_style,
                                    popup_template: layer_popup_template,
                                    origination_db: origination_db,
                                    dataset_name:_recordset.fld(options.rec_layer || _record, 'rec_Title'),  //name for timeline
                                    preserveViewport:options.preserveViewport });
                                                         
                        _triggerLayerStatus( 'visible' );
                   }else {
                        _triggerLayerStatus( 'error' );
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                    
                    //check if there are layers and tlcmapdatasets among result set
                    if( _parent_mapdoc==0 ){ // && window.hWin.HEURIST4.util.isArrayNotEmpty(layers_ids)
                        options.mapwidget.mapping('getMapManager').addLayerRecords( layers_ids );
                    } 
                    

                }
            );          
        }

    }

    
    //
    // recordset layer
    //
    function _addRecordSet(){
        
        var layer_style = _recordset.fld(options.rec_layer || _record, 
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
        var layer_popup_template = _recordset.fld(options.rec_layer || _record, 
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_POPUP_TEMPLATE']);
        
        var data = options.recordset.toGeoJSON();

        var geojson_data = data['geojson'];
        var timeline_data = data['timeline'];   

        if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) 
            || window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data) )
        {
                                             
            _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                        {geojson_data: geojson_data,
                        timeline_data: timeline_data,
                        layer_style: layer_style,
                        popup_template: layer_popup_template,
                        dataset_name:_recordset.fld(options.rec_layer || _record, 'rec_Title'),  //name for timeline
                        preserveViewport:options.preserveViewport });
                                             
            _triggerLayerStatus( 'visible' );
        }else {
            _triggerLayerStatus( 'error' );
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
        
    }
    
    //
    // return extent in leaflet format (for tiler and image layers)
    //
    function _getBoundingBox(){

        return window.hWin.HEURIST4.geo.getWktBoundingBox(
            _recordset.getFieldGeoValue(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'])
        );

    }


    //
    // trigger callback
    //
    function _triggerLayerStatus( status ){

        if(status!=null){
            var layer_ID = 0;
            if(options.rec_layer){
                layer_ID = _recordset.fld(options.rec_layer, 'rec_ID');
            }
            if(layer_ID>0){

                var onlayerstatus = options.mapwidget.mapping('option','onlayerstatus');
                if($.isFunction(onlayerstatus)){
                    onlayerstatus.call(options.mapwidget, layer_ID, status);
                }
            }
        }
    }
    
    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        isVisible: function(){
            return is_visible;
            /* it works
            return is_inited
                 && _nativelayer_id>0 && 
                 options.mapwidget.mapping('isLayerVisibile', _nativelayer_id);
            */                 
        },
        
        setVisibility:function(isvisible){
            
            is_visible = isvisible;
            
            var status = null;
           
            if(is_inited){
                if(_nativelayer_id>0){
                    status =  isvisible?'visible':'hidden'
                    
                    options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, isvisible);
                }
            }else if(isvisible) {
                status = 'loading'
                _addLayerToMap();    
            }
            
            //trigger callback
            _triggerLayerStatus( status );
            
        },
        
        //
        //  sends request for map data (json, kml or shp) and text file with links (to record view and hml) 
        //
        getMapData: function(){
            
            //detect datasource type 
            var rectypeID = _recordset.fld(_record, 'rec_RecTypeID');
            var request = {}, url = null;
            var layerName = _recordset.fld(_record, 'rec_Title');
            
            var layer_ID = 0;
            var dataset_ID = _recordset.fld(_record, 'rec_ID');
            if(options.rec_layer){
                layer_ID = _recordset.fld(options.rec_layer, 'rec_ID');
            }

            if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] 
                || rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET']
                || rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){
                   
                var sQuery;
                if(options.recordset){ 
                    sQuery = '?q=ids:'+options.recordset.getIds()
                        + '&db=' + window.hWin.HAPI4.database;
                }else{
                    var query = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING']);
                    var params = window.hWin.HEURIST4.util.parseHeuristQuery(query);
                    sQuery = window.hWin.HEURIST4.util.composeHeuristQuery2(params, true);
                    sQuery = sQuery + '&db=' + (params.db?params.db:window.hWin.HAPI4.database);
                }
                sQuery = sQuery + '&format=geojson'; //(layer_ID>0?layer_ID:dataset_ID); //layerName; //zip=1&
                
                //@todo: attach hml for dataset record
                sQuery = sQuery + '&metadata='+window.hWin.HAPI4.database+'-'+dataset_ID; 
                 
                url = window.hWin.HAPI4.baseURL 
                        + 'hsapi/controller/record_output.php'+ sQuery;
                                                                  
                window.open(url, '_blank');

            }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){
                
                var layer_url = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
                
                var sMsg = '<p>This dataset is a tiled image running on a server. '
                +'Tiled images should be accessed via their URL which delivers the appropriate tiles for the area being mapped</p>'
                +layer_url
                +'<p>Link to metadata :'
                +window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&recID='+dataset_ID+'</p>';
                
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg);

            }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

                var imageFile = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);                
                
                url = window.hWin.HAPI4.baseURL+'?db='+ window.hWin.HAPI4.database 
                        +'&metadata='+dataset_ID+'&file='+imageFile;
                                                                  
                window.open(url, '_blank');

            }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE'] ||
                     rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_FILE_SOURCE']){  //csv

                url = window.hWin.HAPI4.baseURL 
                        + 'hsapi/controller/record_map_source.php?db='+ window.hWin.HAPI4.database
                        + '&format=rawfile&recID='+dataset_ID
                        + '&metadata='+dataset_ID+(layer_ID>0?(','+layer_ID):'');

                window.open(url, '_blank');
                
            }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_SHP_SOURCE']){
                
                url = window.hWin.HAPI4.baseURL 
                        + 'hsapi/controller/record_shp.php?db='+ window.hWin.HAPI4.database
                        + '&format=rawfile&recID='+dataset_ID
                        + '&metadata='+dataset_ID+(layer_ID>0?(','+layer_ID):'');

                window.open(url, '_blank');
            }
            
        },
        
        //
        //
        //
        zoomToLayer: function(){
            
            if(_nativelayer_id>0){
                options.mapwidget.mapping('zoomToLayer', _nativelayer_id);
            }

        },
        
        //
        //
        //
        getBounds: function (format){

            var bnd = options.mapwidget.mapping('getBounds', _nativelayer_id);
            
            if(!(bnd && bnd.isValid())) return null;

            if(format=='wkt'){
                var aCoords = [];
                var sw = bnd.getSouthWest();
                var nw = bnd.getNorthEast();

                //move go util_geo?
                function __formatPntWKT(pnt, d){
                    if(isNaN(d)) d = 7;
                    var lat = pnt.lat;
                    lat = lat.toFixed(d);
                    var lng = pnt.lng;
                    lng = lng.toFixed(d);
                    return lng + ' ' + lat;               
                }            

                aCoords.push(__formatPntWKT(sw));  
                aCoords.push(__formatPntWKT( {lat:nw.lat, lng:sw.lng} ));  
                aCoords.push(__formatPntWKT(nw));  
                aCoords.push(__formatPntWKT( {lat:sw.lat, lng:nw.lng} ));  
                aCoords.push(__formatPntWKT(sw));  
                return "POLYGON ((" + aCoords.join(",") + "))"
            }else{
                return bnd;
            }
        },



        removeLayer: function(){
            if(_nativelayer_id>0)
                options.mapwidget.mapping('removeLayer', _nativelayer_id);
        },
        
        applyStyle: function( newStyle ){
            if(_nativelayer_id>0)
                options.mapwidget.mapping('applyStyle', _nativelayer_id, newStyle);
        },

        getStyle: function(){
            if(_nativelayer_id>0){
                return options.mapwidget.mapping('getStyle', _nativelayer_id);
            }else{
                return options.mapwidget.mapping('setStyleDefaultValues');
            }
        },
        
        getNativeId: function(){
            return _nativelayer_id;
        }
        
    }

    _init( _options );
    return that;  //returns object
}
