/**
* LEAFLET layers
* interface between heurist layer/datasource and leaflet layer
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

/**
*  Represents the layer on map
*/
function hMapLayer2( _options ) {
    var _className = "MapLayer",
    _version   = "0.4";

    var options = {
        //mapwidget:        refrence to mapping.js      
        //record_id  - loads arbitrary layer (not from mapdocument) (used for basemap image layer)
        
        //mapdoc_recordset: // recordset to retrieve values from rec_layer and rec_datasource
        
        //rec_layer:       // record of type Heurist layer, it is needed for symbology and min/max zoom
        //rec_datasource:  // record of type map dataseource
        
        //not_init_atonce  - if true don't add to nativemap
        //mapdocument_id
        preserveViewport: true   //if false zoom to this layer
    };

    var _record,     //datasource record
        _recordset,  //referense to map document recordset
        _parent_mapdoc = null; //map document id
        
    var _nativelayer_id = 0;
    
    var is_inited = false,
        is_visible = false,
        is_outof_range = false;

    //
    //
    //
    function _init( _options ){

        options = $.extend(options, _options);
        
        if(options.record_id>0){
            //search record on server side
            _searchLayerRecord( options.record_id );
            
            options.record_id = -1;
        }else{

            _recordset = options.mapdoc_recordset;
            _record = options.rec_datasource;
            _parent_mapdoc = options.mapdocument_id;
            
            if(options.not_init_atonce) return;
            
            _addLayerToMap();
        
        }
        
    }
    
    //
    //
    //    
    function _searchLayerRecord(record_id){
        
            var request = {
                        q: {"ids":record_id},  
                        rules:[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}], //data sources linked to layers
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document'};
                        
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        
                        // detect map layer record                        
                        resdata.each(function(recID, record){
                                    
                                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER)
                                    {
                                        var datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    
                                        var datasource_record = resdata.getById( datasource_recID );
                                        
                                        //creates and add layer to nativemap
                                        //returns mapLayer object
                                        _init({rec_layer: record, 
                                               rec_datasource: datasource_record, 
                                               mapdoc_recordset: resdata, //need to get fields
                                               mapwidget: options.mapwidget});
                                    }
                        });
                        
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );           
        
    }
    
    //
    //
    //
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
    function _addTiledImage( layer_url ) {

        if(window.hWin.HEURIST4.util.isempty(layer_url)){
            
             //obfuscated file id
             var file_info = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
             
             if($.isArray(file_info)){
             
                 var url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&mode=url&file='+
                            file_info[0];
                            
                 window.hWin.HEURIST4.util.sendRequest(url, {}, null, 
                    function (response) {
                        if(response.status == window.hWin.ResponseStatus.OK && response.data){
                            _addTiledImage( response.data );
                        }
                    });
                 return;                           
                 
             }else{
                 //backward capability - value contains url to tiled image stack
                 layer_url = file_info;
             }
        }
        
        // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
        if(!window.hWin.HEURIST4.util.isempty(layer_url)) {

            var tilingSchema = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA']);
            var mimeType = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE']);
            var minZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM_LEVEL']);
            var maxZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM_LEVEL']);

            var tileUrlFunc = null; 
            
            var ccode1 = $Db.getConceptID('trm', tilingSchema);
            var ccode2 = $Db.getConceptID('trm', mimeType);
            var ext = (ccode2 == '2-540'? ".png" : (ccode2 == '2-537'?'.jpg':".gif"));

            var layer_options = {minZoom:minZoom , maxZoom:maxZoom, extension:ext};
            
            if(layer_url.indexOf('/info.json')>0){  //ccode1=='2-???' IIIF image not implemented
                
                layer_options['IIIF'] = true;
            
            }else
            if(ccode1=='2-549'){ //virtual earth
                
                layer_options['BingLayer'] = true;

                if(layer_url.indexOf('{q}')<0){
                    layer_url = layer_url + '{q}';
                }
                
            }else if(tilingSchema && ccode1=='2-548'){ //maptiler tilingSchema[idx_ccode]

                layer_options['MapTiler'] = true;

                if(layer_url.indexOf('{q}')<0 && layer_url.indexOf('{x}')<0){
                    layer_url = layer_url + '{q}'
                                + ext;
                }
                
            }else{
                
                if(layer_url.indexOf('{x}')<0){
                    layer_url = layer_url + '/{z}/{x}/{y}'
                                + ext;
                }
                /* Blocked because of possible Remote file disclosure
                if(layer_url.indexOf('http://')===0 && layer_url.indexOf('http://127.0.0.1')<0){
                    
                    var mimetype = 'image/'+ext;
                    
                    //load via proxy
                    layer_url = window.hWin.HAPI4.baseURL 
                            + '?db=' + window.hWin.HAPI4.database 
                            + '&mimetype=' + mimetype
                            + '&rurl=' + layer_url; //encodeURIComponent(layer_url);
                }
                */    
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
         var file_info = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
         
         var image_url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&file='+
                    file_info[0];
                    
         var worldFileData = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']);
         
         var image_extent = null; //window.hWin.HEURIST4.geo.parseWorldFile( worldFileData, image_width, image_height);
         
         if(image_extent==null){
            image_extent = _getBoundingBox();  //get wkt bbox from DT_GEO_OBJECT 
         } 
         
         
         if(image_extent==null){
             //error
            _triggerLayerStatus( 'error' );
            
            if(!window.hWin.HEURIST4.util.isempty(response.message)){
            var msg = 'Layer : '+dataset_name+'<br><br>'
                +'Extent of image is not defined.';
            }
            window.hWin.HEURIST4.msg.showMsgErr(msg);
         }else{
             
            _nativelayer_id = options.mapwidget.mapping('addImageOverlay', 
                                                        image_url, 
                                                        image_extent, 
                                                        _recordset.fld(_record, 'rec_Title') );
          
             
         }
          
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

            var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
            if(MAXITEMS>0){
                server_request['limit'] = MAXITEMS;
            }
                
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
                    
        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');    
        
        var data = options.recordset.toGeoJSON(null,0,MAXITEMS);

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
                options.mapwidget.mapping('onLayerStatus', layer_ID, status);
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
        
        //
        // visiblity_set true,false or array of ids
        //
        setVisibility:function(visiblity_set){
            
            
            var was_invisible = !is_visible;
            is_visible = (window.hWin.HEURIST4.util.isArrayNotEmpty(visiblity_set) || visiblity_set === true);

            if(is_outof_range) return;
            
            var status = null;
           
            if(is_inited){
                if(_nativelayer_id>0){
                    status =  (is_visible)?'visible':'hidden';
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(visiblity_set)){                            
                        if(was_invisible) options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, true);
                        options.mapwidget.mapping('setVisibilityAndZoom', {native_id:_nativelayer_id}, visiblity_set, false);
                    }else{        
                        options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, is_visible);
                    }
                }
            }else if(is_visible) {
                status = 'loading'
                _addLayerToMap();    
            }
            
            //trigger callback
            _triggerLayerStatus( status );
            
        },
        
        //
        //
        //
        setVisibilityForZoomRange:function(current_zoom){
            
            if(is_inited){
                
                var _rec = options.rec_layer || _record;
                if(_rec['maxzoom']==-1 && _rec['minzoom']==-1) return;

                var is_in_range = true;
                
                if(_rec['maxzoom']>0 || _rec['minzoom']>=0){ //already defined
                    
                    is_in_range = (_rec['maxzoom']==-1 || _rec['maxzoom']>=current_zoom)
                            && (_rec['minzoom']==-1 || current_zoom>=_rec['minzoom']);
                    
                }else{
                    _rec['maxzoom'] = -1;
                    _rec['minzoom'] = -1;
                    var dty_id = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM'];
                    var layer_bnd = (_rec['layer']).getBounds();
                    
                    if(dty_id>0){
                        var val = parseFloat(_recordset.fld(_rec, dty_id));
                        if(val>0.01){ //old default value
                            _rec['maxzoom'] = options.mapwidget.mapping('convertZoomToNative', val, layer_bnd);
                        }
                    }
                    dty_id = window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM'];
                    if(dty_id>0){
                        var val = parseFloat(_recordset.fld(_rec, dty_id));
                        if(val>0 && val!=20 && val!=90){ //old default value
                            _rec['minzoom'] = options.mapwidget.mapping('convertZoomToNative', val, layer_bnd);
                        }
                    }
                    
                    that.setVisibilityForZoomRange( current_zoom );
                    return;
                }
            
                var status = null;
                if(is_in_range){
                    is_outof_range = false;
                    status =  (is_visible)?'visible':'hidden';
                    options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, is_visible);
                }else{
                    status = 'out';
                    is_outof_range = true;
                    options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, false);
                }
            
                //trigger callback
                _triggerLayerStatus( status );
            }//inited
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
