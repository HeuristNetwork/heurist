/**
* LEAFLET layers
* interface between heurist layer/datasource and leaflet layer
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
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
        // mapwidget:        refrence to mapping.js      
        // record_id  - loads arbitrary layer (not from mapdocument) (used for basemap image layer)
        
        // mapdoc_recordset: // recordset to retrieve values from rec_layer and rec_datasource
        
        // rec_layer:       // record of type Heurist layer, it is needed 
                           // for symbology (DT_SYMBOLOGY), thematic map (DT_MAP_THEMATIC) and min/max zoom
        // rec_datasource:  // record of type map datasource
        
        // not_init_atonce  - if true don't add to nativemap
        // mapdocument_id
        // is_current_search - if true it requests geojson as separate objects (do not create GeometryCollection for heurist record)
        preserveViewport: true   //if false zoom to this layer
    };

    var _record,     //datasource record
        _recordset,  //referense to map document recordset
        _parent_mapdoc = null; //map document id
        
    var _nativelayer_id = 0,
        _dataset_type = null,
        _geojson_ids = null,  //all record ids in geojson response
        _geojson_dty_ids = null; //all dty_ID in geojson response (for current search only) 
    
    var is_inited = false,
        is_visible = false,
        is_outof_range = false;
        
    var _max_zoom_level = 0;
    
    var _has_zoom_setting_per_record = 0;//1 - it does, -1 it doesn't, 0 - not checked yet

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
            //in basemap zoom levels (1-19)
            var minZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM_LEVEL']); 
            var maxZoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM_LEVEL']);

            var tileUrlFunc = null; 
            
            var ccode1 = $Db.getConceptID('trm', tilingSchema);
            var ccode2 = $Db.getConceptID('trm', mimeType);
            var ext = (ccode2 == '2-540'? ".png" : (ccode2 == '2-537'?'.jpg':".gif"));

            var layer_options = {minZoom:minZoom , maxZoom:maxZoom, extension:ext};
            
            if(layer_url.indexOf('/info.json')>0){  //IIIF image
                
                layer_options['IIIF'] = true;
            
            }else
            if(ccode1=='2-549'){ //virtual earth
                
                layer_options['BingLayer'] = true;

                if(layer_url.indexOf('{q}')<0){
                    layer_url = layer_url + '{q}';
                }
                
            }else if(tilingSchema && ccode1=='2-548'){ // tilingSchema[idx_ccode]

                layer_options['TMS'] = true;

                if(layer_url.indexOf('{q}')<0 && layer_url.indexOf('{x}')<0){
                    layer_url = layer_url 
                                + (layer_url[layer_url.length-1]=='/'?'':'/') + '{q}'
                                + ext;
                }
                
            }else{
                //GoogleMap/OSM
                layer_options['OSM'] = true;
                
                if(layer_url.indexOf('{q}')<0 && layer_url.indexOf('{x}')<0){
                    layer_url = layer_url 
                                + (layer_url[layer_url.length-1]=='/'?'':'/') + '{z}/{x}/{y}'
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
                                dataset_type:'shp',
                                preserveViewport:options.preserveViewport });
                                
                        _setVisibilityForZoomRange();   
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
            
        //var url = window.hWin.HAPI4.baseURL + 'hserver/controller/record_map_source.php?db='
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
                                        dataset_type:'kml',
                                        preserveViewport:options.preserveViewport });
                                                             
                        }else {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        _setVisibilityForZoomRange();   
                    }
                }
            }
        );          
    }


    //
    // query layer
    //
    function _addQueryLayer(){

        var layer_popup_template = _recordset.fld(options.rec_layer || _record, 
                                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SMARTY_TEMPLATE']);

        var layer_geofields = []
        var layer_default_style = null;
        if(window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']>0){
            var layer_themes = _recordset.fld(options.rec_layer || _record, 
                                        window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
  
/*            
        {   //additional filter and default layer symbol
            "geofield": "2-134"//2-134 (birth) or 1161-254 (death) Field id for pointer to Place, optional    
            "symbol":{"iconType":"circle","stroke":"1","color":"#ff0000","weight":"2","fill":"1","fillColor":"#0000FF","iconSize":"8"},
        },  
        OR "symbol":{"geofield":...., }
        {
            "title": "First theme ever",
            "symbol":{"iconType":"circle","stroke":"1","color":"#ff0000","weight":"2","fill":"1","fillColor":"#0000FF","iconSize":"8"},
            "rules": {}, //find records linked to place
            "fields": []
        }
        
{
            "title": "Death",
            "symbol":{"iconType":"circle","stroke":"1","color":"#ff0000","weight":"2","fill":"1","fillColor":"#000000","iconSize":"6"}
}        
*/            
           
            //find "geofield" in thematic maps - download geodata from these fields only
            layer_themes = window.hWin.HEURIST4.util.isJSON(layer_themes);
            
            if(layer_themes){
                
                if($.isPlainObject(layer_themes)){
                    layer_themes = [layer_themes];
                }

                $.each(layer_themes, function(i,item){
                    if(item.geofield){
                        //geo field can be 2 types
                        //1. code request (rt:dt:rt:dt) that alows to drill for geo values in linked records
                        //2. dty_ID - pointer/resource field 
                        var geofields = item.geofield.split(',');
                        for(var k=0; k<geofields.length; k++){
                            var geofield = geofields[k];
                            if(geofield.indexOf(':')>0){
                                //1. code request
                                var field = window.hWin.HEURIST4.query.createFacetQuery(geofield, true, false);
                                if(field['facet'] && field['facet']=='$IDS'){
                                    layer_geofields.push({id:field['id']}); //in main record
                                }else{
                                    layer_geofields.push({id:field['id'],q:field['facet']}); //in linked records
                                }
                            }else{
                                //2. pointer field
                                layer_geofields.push(geofield);    
                            }
                        }
                        
                        layer_default_style = item.symbol?item.symbol:item;
                        return false;       
                    }else if(!item.fields){
                        layer_default_style = item.symbol?item.symbol:item;
                    }
                });

            }
        }
        if(layer_geofields.length==0) layer_geofields = null;
                                    
                                    
        var origination_db = null;
        
        var query = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING']);
        var request = window.hWin.HEURIST4.query.parseHeuristQuery(query);

        if(request.q){
            
             var server_request = {
                q: request.q,
                rules: request.rules,
                w: request.w,
                geofields: layer_geofields, //additional filter - get geodata from specified fields only
                //returns strict geojson and timeline data as two separate arrays, withoud details, only header fields rec_ID, RecTypeID, rec_Title and rec_MinZoom, rec_MaxZoom
                leaflet: 1, 
                simplify: 1, //simplify paths with more than 1000 vertices
                separate: (options.is_current_search===true)?1:0, //if true do not create GeometryCollection for heurist record
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
                    var timeline_dty_ids = [];
                    if(response['geojson'] && response['timeline']){
                        geojson_data = response['geojson'];
                        timeline_data = response['timeline'];   
                        
                        if(response['timeline_dty_ids']) timeline_dty_ids = response['timeline_dty_ids'];
                        if(response['layers_ids']) layers_ids = response['layers_ids']; //layers records from clearinghouse  
                    }else{
                        geojson_data = response;
                    }
                    
                    if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) 
                        || window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data) )
                    {
                         
                        _geojson_ids = response['geojson_ids']; //all record ids to be plotted on map
                        if(options.is_current_search && response['geojson_dty_ids']){
                            _geojson_dty_ids = response['geojson_dty_ids'];    
                            
                            var _geojson_rty_ids = response['geojson_rty_ids'];
                            //dynamic thematic map for current search
                            var thematic_map = [];
                            for(var i=0; i<_geojson_dty_ids.length; i++)
                            {
                                var dty_ID = _geojson_dty_ids[i];

                                var title = [];                                
                                if(dty_ID=='Path'){
                                    title = 'Path';                                   
                                }else if (typeof dty_ID === 'string' && dty_ID.indexOf('relation:')===0){
                                    title = $Db.trm(dty_ID.substring(9),'trm_Label');
                                    if(!title) title = dty_ID;
                                }else{
                                    for(var j=0; j<_geojson_rty_ids.length; j++){
                                        let t1 = $Db.rst(_geojson_rty_ids[j], dty_ID, 'rst_DisplayName');
                                        if(!window.hWin.HEURIST4.util.isempty(t1)) title.push(t1);
                                    }
                                    if(title.length>1){
                                        //let t1 = $Db.dty(dty_ID, 'dty_Name');
                                        //(window.hWin.HEURIST4.util.isempty(t1)?'':t1)
                                        title = title.join(' | ');
                                    }else if (title.length==1){
                                        title = title[0];    
                                    }else{
                                        title = 'Field: '+_geojson_rty_ids[0]; //not found
                                    }
                                }
                                
                                //special thematic map - filter by geo fields
                                thematic_map.push(
                                {
                                    "title": title,
                                    "active":true,
                                    "fields": [{"code":"rec_GeoField","ranges":[{"value": _geojson_dty_ids[i]}]}]
                                }
                                );
                            }//for
                            
                            _recordset.setFld(_record, 
                                        window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY'],
                                        JSON.stringify(thematic_map)
                                            );
                        }
                        
                        
                        _dataset_type = 'db';
                        _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                                    {geojson_data: geojson_data,
                                    timeline_data: timeline_data,
                                    timeline_dty_ids: timeline_dty_ids,
                                    layer_style: layer_default_style,
                                    popup_template: layer_popup_template,
                                    origination_db: origination_db,
                                    dataset_name:_recordset.fld(options.rec_layer || _record, 'rec_Title'),  //name for timeline
                                    dataset_type:'db',
                                    preserveViewport:options.preserveViewport });
                                                         
                        //_triggerLayerStatus( 'visible' );
                        _setVisibilityForZoomRange();   
                        
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
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SMARTY_TEMPLATE']);
                    
        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');    
        
        var data = options.recordset.toGeoJSON(null,0,MAXITEMS);

        var geojson_data = data['geojson'];
        var timeline_data = data['timeline'];   

        if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) 
            || window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data) )
        {
                         
            _geojson_ids = data['geojson_ids']; //simpify {all: data['geojson_ids']}; //all record ids to be plotted on map
            var timeline_dty_ids = window.hWin.HEURIST4.util.isArrayNotEmpty(data['timeline_dty_ids'])
                                    ?data['timeline_dty_ids']
                                    :[];                    
            
            _dataset_type = 'db';
            _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                        {geojson_data: geojson_data,
                        timeline_data: timeline_data,
                        timeline_dty_ids: timeline_dty_ids,
                        layer_style: layer_style,
                        popup_template: layer_popup_template,
                        dataset_name:_recordset.fld(options.rec_layer || _record, 'rec_Title'),  //name for timeline
                        dataset_type: 'db',
                        preserveViewport:options.preserveViewport });
                                             
            _setVisibilityForZoomRange(currZoom);   
        }else {
            _triggerLayerStatus( 'error' );
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
        
    }


    function _getMaxZoomLevel(){
        var layer_maxzoom = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM_LEVEL']);
        return layer_maxzoom;
    }
    
    //
    // return extent in leaflet format (for tiler and image layers)
    //
    function _getBoundingBox(){

        var ext = window.hWin.HEURIST4.geo.getWktBoundingBox(
            _recordset.getFieldGeoValue(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'])
        );
             
        if(options.mapwidget.mapping('getCurrentCRS')=='Simple'){
            var maxzoom = _getMaxZoomLevel();
            
            if($.isArray(ext) && ext.length==2){
            
                var max_dim = Math.max(ext[1][0]-ext[0][0], ext[1][1]-ext[0][1]);
                
                if(!(maxzoom>1) && max_dim>512){
                    //@todo 
                    maxzoom =  Math.ceil(
                        Math.log(
                            max_dim /
                            256
                        ) / Math.log(2)
                    );        
                }
                
                if(maxzoom>0 && max_dim>512){
                    //convert pixels to lat/long
                    var nativemap = options.mapwidget.mapping('getNativeMap');
                    var latlong1 = nativemap.unproject([ext[0][1],ext[0][0]], maxzoom);
                    var latlong2 = nativemap.unproject([ext[1][1],ext[1][0]], maxzoom);
                    
                    ext = [latlong1, latlong2];
                }          
            }
            _max_zoom_level = maxzoom;
        }
        
        return ext;
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
    
    //
    // active_themes - array of active themes
    //
    // 1. get all fields that are used in active_themes
    // 2. request server for field values and assign values to feature.properties.details
    //      using mapping.eachLayerFeature 
    // 3. if values are already obtained use mapping.eachLayerFeature to assign symbols to 
    //      layer.feature.thematic_style
    //        
    function _applyThematicMap( active_themes ){    
        
        //sample of thematic map configuration
        /*
        theme = 
        [
        {"geofield": "2-134","iconType":"circle","stroke":"1","color":"#ff0000","weight":"2","fill":"1","fillColor":"#0000FF","iconSize":"8"},
        {
            "title": "First theme ever",
            "symbol":{"iconType":"circle","stroke":"1","color":"#ff0000","weight":"2","fill":"1","fillColor":"#0000FF","iconSize":"8"},
            "rules": {}, //find records linked to place
            "fields": [
                {"code":1109,"title":"Population","ranges":[
                    {"value":"1", "symbol":{"iconSize":10 } },
                    {"value":"2<>3", "symbol":{"iconSize":20 } },
                    {"value":"4,5", "symbol":{"iconSize":30, "fillColor":"#00ff50" } }
                ]}
                //{"code":133,"title":"Place Type"}
            ]
        }];
        */

        //feature.properties.rec_ID
        // _geojson_ids - list of heurist records in layer, if it is empty layer is not loaded or empty
        if(_dataset_type!='db' || _geojson_ids==null || _geojson_ids.length==0) return;
        
        //theme = window.hWin.HEURIST4.util.isJSON(theme);
        
        if(active_themes==null || active_themes.length==0){
            //switch off current theme
            options.mapwidget.mapping('eachLayerFeature', _nativelayer_id, 
                function(layer){
                    //reset active thematic map symbol symbol
                    layer.feature.thematic_style = null;
                }
            );
            that.applyStyle(null, true);
            return;
        }
        
        // 1. get all fields that are used in active_themes and prepare ranges
        var theme_fields = {};
        var theme_queries = {};

        for(var j=0; j<active_themes.length; j++){
            var theme = active_themes[j];
            
            $.each(theme.fields, function(i, ftheme){
                
                if(ftheme.code!='rec_GeoField'){
                
                    var field = window.hWin.HEURIST4.query.createFacetQuery(ftheme.code, true, false);
                    
                    //field.code - code without last dty_ID
                    
                    if(theme_fields[field.code]){ //query already defined
                        theme_fields[field.code].push(field.id);
                    }else{
                    
                        var query;
                        if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                            query = '$IDS'; //'ids:'+_geojson_ids.join(',');//maplayer_query;

                        }else{
                            
                            query = window.hWin.HEURIST4.util.cloneJSON(field['facet']); //clone 
                            //change $IDS for current set of target record type
                            // it will be done on server side__fillQuery(query);                
                        }
                        
                        theme_queries[field.code] = query;
                        theme_fields[field.code] = [field.id];
                    }
                }
                
                /*
                if(typeof tfield == 'string' && tfield.indexOf(':')>0){
                    let codes = tfield.split(':');
                    tfield = codes[codes.length-1];
                }
                if(theme_fields.indexOf(tfield)<0){
                    theme_fields.push(tfield);    
                }
                */
                
                //prepare ranges
                for(var j=0; j<ftheme.ranges.length; j++){
                    var range = ftheme.ranges[j].value;
                    if(typeof range==='string'){
                        var values = range.split(',');
                        if(values.length>=2){
                            ftheme.ranges[j].value = values;
                        }else{
                            values = range.split('<>');
                            if(values.length==2){
                                ftheme.ranges[j].min = values[0];
                                ftheme.ranges[j].max = values[1];
                            }
                        }
                    }
                    if(!ftheme.ranges[j].symbol){
                        ftheme.ranges[j].symbol = {};  
                    } 
                }
            });
        }
        
        // 3. request for values. if there are not fields to request then assign symbols
        if(Object.keys(theme_queries).length>0)
        {
            
            var query_codes = Object.keys(theme_queries);
            
            function __executeQuery(query_idx){
                
                var code = query_codes[query_idx];
            
                //find values
                var server_request = {
                    q: theme_queries[code],
                    //rules: theme.rules,  search for linked records
                    a: 'links_details',
                    ids: _geojson_ids.join(','),
                    w: 'a',
                    zip: 1,
                    detail:theme_fields[code].join(',')  //request detail fields   'rec_RecTypeID,'+
                };
                
                //_new  format:'json'
                window.hWin.HAPI4.RecordMgr.search(server_request,
                    function(response){
                        

                        if(response.status == window.hWin.ResponseStatus.OK){
                            //var resdata = new hRecordSet(response.data);
                            //assign symbol for each element of layer
                            options.mapwidget.mapping('eachLayerFeature', _nativelayer_id, 
                                function(layer){
                                    //get record from result set and assign field values
                                    let id = layer.feature.properties.rec_ID; 
                                    var record = response.data[id];
                                    for (var k=0; k<theme_fields[code].length; k++){
                                        var dty_ID = theme_fields[code][k];
                                        //get first value from array
                                        for (var dtl_ID in record[dty_ID]){
                                            layer.feature.properties[code+':'+dty_ID] = record[dty_ID][dtl_ID];    
                                            break;
                                        }
                                    }

                                    /*
                                    var record = resdata.getRecord(id);
                                    for (var k=0; k<theme_fields[code].length; k++){
                                        var dty_ID = theme_fields[code][k];
                                        layer.feature.properties[code+':'+dty_ID] = resdata.fld(record, dty_ID);
                                    }
                                    */
                                }
                            );
                            
                            if(query_idx+1==query_codes.length){
                                //define thematic map symbol symbol
                                options.mapwidget.mapping('eachLayerFeature', _nativelayer_id, 
                                    function(layer){
                                        layer.feature.thematic_style = _defineThematicMapSymbol(layer.feature.properties, active_themes);
                                });
                                that.applyStyle(null, true);    
                            }else{
                                __executeQuery(query_idx+1);    
                            }

                        }else {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }

                });
            } //__executeQuery

            __executeQuery(0);
            
        }else{ 
            //all fields are already loaded
            options.mapwidget.mapping('eachLayerFeature', _nativelayer_id, 
                function(layer){
                    //define thematic map symbol symbol
                    layer.feature.thematic_style = _defineThematicMapSymbol(layer.feature.properties, active_themes);
                }
            );
            that.applyStyle(null, true);
        }
        
        
    }
    
    //
    // show/hide the entire layer depends on DT_MINIMUM_ZOOM_LEVEL,DT_MAXIMUM_ZOOM_LEVEL
    //
    function _setVisibilityForZoomRange(current_zoom){

        if(is_inited){
            
            if(!(current_zoom>=0)){
                var nativemap = options.mapwidget.mapping('getNativeMap');
                current_zoom = nativemap.getZoom();
            }
            
            var _rec = options.rec_layer || _record;
            if(_rec['maxzoom']==-1 && _rec['minzoom']==-1 && _has_zoom_setting_per_record<0) return; //not set

            var is_in_range = true;

            if(_rec['maxzoom']>0 || _rec['minzoom']>=0){ //already defined
                
                is_in_range = (_rec['maxzoom']==-1 || _rec['maxzoom']>=current_zoom)
                        && (_rec['minzoom']==-1 || current_zoom>=_rec['minzoom']);
                
            }else if(!(_rec['maxzoom']==-1 && _rec['minzoom']==-1))
            {
                _rec['maxzoom'] = -1;
                _rec['minzoom'] = -1;
                if(_rec['layer']){
                    
                    // in basemap zoom levels (0-19)
                    var dty_id_min = window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM_LEVEL'],
                        dty_id_max = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM_LEVEL'];
                        
                    if(dty_id_min>0){
                        var val = parseInt(_recordset.fld(_rec, dty_id_min));
                        if(val>=0){
                            _rec['minzoom'] = val;
                        }
                    }
                    if(dty_id_max>0){
                        var val = parseInt(_recordset.fld(_rec, dty_id_max));
                        if(val>0){
                            _rec['maxzoom'] = val;
                        }
                    }
                    
                    if(_rec['maxzoom']>0 || _rec['minzoom']>=0){ //already defined
                    
                        // in kilometers
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
                    }
                }
                _setVisibilityForZoomRange( current_zoom );
                return;
            }
        
            var status = null;
            if(is_in_range){
                is_outof_range = false;
                status =  (is_visible)?'visible':'hidden';
                options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, is_visible);
                
                //visibility per record
                if(_dataset_type=='db' && _has_zoom_setting_per_record>=0){
                
                    var show_rec_ids = [], hide_rec_ids = [];
                    
                    _has_zoom_setting_per_record = -1;
                    
                    options.mapwidget.mapping('eachLayerFeature', _nativelayer_id, 
                        function(layer){
                            
                            //get record from result set and assign field values
                            if(layer.feature.properties.rec_MinZoom>=0 || layer.feature.properties.rec_MaxZoom>0){
                                
                                let is_in_range = (!(layer.feature.properties.rec_MaxZoom>0) || layer.feature.properties.rec_MaxZoom>=current_zoom)
                                                  && (!(layer.feature.properties.rec_MinZoom>=0) || current_zoom>=layer.feature.properties.rec_MinZoom);
                                if(is_in_range){
                                    show_rec_ids.push(layer.feature.properties.rec_ID);    
                                }else{
                                    hide_rec_ids.push(layer.feature.properties.rec_ID);
                                }
                                
                                _has_zoom_setting_per_record = 1;
                            }
                        });
                                        
                    
                    options.mapwidget.mapping('setFeatureVisibility', show_rec_ids, true, 1);
                    options.mapwidget.mapping('setFeatureVisibility', hide_rec_ids, false, 1);

                }
                
            }else{
                status = 'out';
                is_outof_range = true;
                options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, false);
            }
        
            //trigger callback
            _triggerLayerStatus( status );
        }//inited
        
        
    }
                         
    
    //
    // find what theme fit for given feature
    //
    function _defineThematicMapSymbol(feature, themes){
        
        var recID = feature.rec_ID;
        var new_symbol = false;
        
        for(var k=0; k<themes.length; k++)
            for(var i=0; i<themes[k].fields.length; i++)
            {
                
                let theme = themes[k];
                let ftheme = theme.fields[i];
                
                let tfield = ftheme.code;
                /*
                if(typeof tfield == 'string' && tfield.indexOf(':')>0){
                    let codes = tfield.split(':');
                    tfield = codes[codes.length-1];
                }
                */
                
                let value = feature[tfield];

                let fsymb = null;

                if(ftheme.range_type=='equal' || ftheme.range_type=='log'){
                    //@todo find min and max value
                }else{
                    for(var j=0; j<ftheme.ranges.length; j++){
                        var range = ftheme.ranges[j];
                        if($.isArray(range.value))
                        {
                            if(window.hWin.HEURIST4.util.findArrayIndex(value, range.value)>-1){
                                fsymb = range.symbol;       
                                break;
                            }
                        }else if(!window.hWin.HEURIST4.util.isnull(range.min) && 
                            !window.hWin.HEURIST4.util.isnull(range.max)){

                                if(value>=range.min && value<=range.max){
                                    fsymb = range.symbol;
                                    break;
                                }
                            }else if(value==range.value){
                                fsymb = range.symbol;
                                break;
                            }
                    }//for
                    if(fsymb!=null){
                        //if theme.symbol is not defined it takes def_layer_style)
                        new_symbol = _mergeThematicSymbol(theme.symbol?theme.symbol:that.getStyle(), fsymb);
                    }
                }
            }
            
        return new_symbol;        
        
    }
    
    //
    //
    //
    function _mergeThematicSymbol(basesymbol, fsymb){
        
            var use_style = window.hWin.HEURIST4.util.cloneJSON( basesymbol );
        
            var keys = Object.keys(fsymb);
            for(var j=0; j<keys.length; j++){
                use_style[keys[j]] = fsymb[keys[j]];
            }
            
            return use_style;
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
        // if visiblity_set is array of ids it allows to show only certain objects for this layer
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
                        //switch the layer on                         
                        if(was_invisible) options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, true);
                        //
                        options.mapwidget.mapping('setVisibilityAndZoom', {native_id:_nativelayer_id}, visiblity_set, false);
                    }else{        
                        //show/hide entire layer
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
            
            _setVisibilityForZoomRange(current_zoom);
            
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
                    var params = window.hWin.HEURIST4.query.parseHeuristQuery(query);
                    sQuery = window.hWin.HEURIST4.query.composeHeuristQuery2(params, true);
                    sQuery = sQuery + '&db=' + (params.db?params.db:window.hWin.HAPI4.database);
                }
                sQuery = sQuery + '&format=geojson'; //(layer_ID>0?layer_ID:dataset_ID); //layerName; //zip=1&
                
                //@todo: attach hml for dataset record
                sQuery = sQuery + '&metadata='+window.hWin.HAPI4.database+'-'+dataset_ID; 
                 
                url = window.hWin.HAPI4.baseURL 
                        + 'hserver/controller/record_output.php'+ sQuery;
                                                                  
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
                        + 'hserver/controller/record_map_source.php?db='+ window.hWin.HAPI4.database
                        + '&format=rawfile&recID='+dataset_ID
                        + '&metadata='+dataset_ID+(layer_ID>0?(','+layer_ID):'');

                window.open(url, '_blank');
                
            }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_SHP_SOURCE']){
                
                url = window.hWin.HAPI4.baseURL 
                        + 'hserver/controller/record_shp.php?db='+ window.hWin.HAPI4.database
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
        // For images
        //
        getMaxZoomLevel: function(){
            return _max_zoom_level; 
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

        //
        //
        //
        removeLayer: function(){
            if(_nativelayer_id>0)
                options.mapwidget.mapping('removeLayer', _nativelayer_id);
        },
        
        applyStyle: function( newStyle, newTheme ){
            if(_nativelayer_id>0){
                options.mapwidget.mapping('applyStyle', _nativelayer_id, newStyle, newTheme);
            }
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
        },
        
        
        //
        // loop trough all elements of top_layer (record ids)
        // 1. find detail field values and assign them to feature.properties.details
        // 2. select the appropriate value style (part values)
        // 3. generate fully defined style object (base style+value style
        // 4. assign it to feature.theme[name]
        //
        // theme - json with thematic map configuration
        applyThematicMap: function(theme){
            _applyThematicMap(theme);
        }
    }

    _init( _options );
    return that;  //returns object
}
