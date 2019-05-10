/**
* filename: explanation
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
        
        //rec_layer:       // record of type Heurist layer
        //rec_datasource:  // record of type map dataseource

        preserveViewport: true  
    };

    var _record, 
        _recordset;
        
    var _nativelayer_id = 0;

    //
    //
    //
    function _init( _options ){

        options = $.extend(options, _options);

        _recordset = options.mapdoc_recordset;
        _record = options.rec_datasource;

        //detect layer type
        var rectypeID = _recordset.fld(_record, 'rec_RecTypeID');

        if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] 
           || rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){

            _addQueryLayer();

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){

            _map_overlay = _addTiledImage();

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

            _addImage();

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE']){

            _addKML();
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

            }
            
            layer_options._extent = _getBoundingBox();
            
            _nativelayer_id = options.mapwidget.mapping('addTileLayer', 
                                                        layer_url, 
                                                        layer_options, 
                                                        _recordset.fld(_record, 'rec_Title') );
            
        }
    }
    
    //
    // return extent in leaflet format
    //
    function _getBoundingBox(){
        
         var geodata = _recordset.getFieldGeoValue(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']);           
         if(geodata && geodata[0]){
            var shape = window.hWin.HEURIST4.geo.wktValueToShapes( geodata[0].wkt, geodata[0].geotype, 'google' );
            if(shape && shape._extent){
                var extent = shape._extent;
                return [[extent.ymin,extent.xmin],[extent.ymax,extent.xmax]];
            }
         }else{
             return null;
         }
        
    }

    //
    // add image
    //
    function _addImage(){

         var imageFile = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
         
         var image_url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&file='+
                    imageFile[0];
                    
         var image_extent = _getBoundingBox();
         
          
         _nativelayer_id = options.mapwidget.mapping('addImageOverlay', 
                                                        image_url, 
                                                        image_extent, 
                                                        _recordset.fld(_record, 'rec_Title') );
          
    }

    //
    // add kml
    // files
    // kmlSnippet
    //
    function _addKML() {

        var rec_ID = _recordset.fld(_record, 'rec_ID');
            
        //var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_kml.php?db='
        //            +window.hWin.HAPI4.database+'&format=geojson&recID='+rec_ID;
                    
        request = {recID:rec_ID};             
        //perform loading kml as geojson
        window.hWin.HAPI4.RecordMgr.load_kml_as_geojson(request,
            function(response){
                if(response){
                    _nativelayer_id = options.mapwidget.mapping('addGeoJson', 
                                                response, 
                                                null, 
                                                _recordset.fld(_record, 'rec_Title') );
                }
            }
        );          
    }


    //
    // query layer
    //
    function _addQueryLayer(){

        var query = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING']);
        var request = window.hWin.HEURIST4.util.parseHeuristQuery(query);

        if(request.q){

            request = {
                q: request.q,
                rules: request.rules,
                w: request.w,
                leaflet: true, //returns strict geojson and timeline data as two separate arrays
                simplify: true,
                format:'geojson'};
                
            //perform search        
            window.hWin.HAPI4.RecordMgr.search_new(request,
                function(response){

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
                                                        geojson_data, 
                                                        timeline_data, 
                                                        _recordset.fld(_record, 'rec_Title') );
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );          
        }

    }

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        setVisibility:function(isvisible){

            if(_nativelayer_id>0){
                options.mapwidget.mapping('setLayerVisibility', _nativelayer_id, isvisible);
            }
            
        },

        zoomToLayer: function(){
            
            if(_nativelayer_id>0){
                options.mapwidget.mapping('zoomToLayer', _nativelayer_id);
            }

        },

        removeLayer: function(){
            if(_nativelayer_id>0)
                options.mapwidget.mapping('removeLayer', _nativelayer_id);
        },
        
        applyStyle: function( newStyle ){
            if(_nativelayer_id>0)
                options.mapwidget.mapping('applyStyle', _nativelayer_id, newStyle);
        }
        
    }

    _init( _options );
    return that;  //returns object
}
