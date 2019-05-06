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
      //map_recordset:   // creates layer from given records
      //rec_layer:  
      //rec_datasource:
      
      //map_recordset
      //map_recordset_name
      preserveViewport: true  
    };
    
    var _record, _recordset; //record from recordset
    var _map_overlay, DT_QUERY_STRING;  //object with layer action methods 
    
    //
    //
    //
    function _init( _options ){

        DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'];
            
        options = $.extend(options, _options);
        
        _recordset = options.map_recordset;
        
        if(!options.rec_datasource){
            //_record = _recordset.getFirstRecord();
            _map_overlay = _addRecordsetLayer();
            return;
        }else{
            //_record = options.rec_layer;
            _record = options.rec_datasource;
        }
        
        //detect layer type
        var rectypeID = _recordset.fld(_record, 'rec_RecTypeID');
        
        if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){
            
            _map_overlay = _addQueryLayer();

        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){
            
            _map_overlay = _addTiledImage();
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

            _map_overlay = _addImage();
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE']){
            
            _map_overlay = _addKML();
        }
    }
    
    //
    // add tiled image
    //
    function _addTiledImage() {
    
        var imageLayer = null;

        var sourceURL = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
        
        // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
        if(sourceURL !== undefined) {

            var tilingSchema = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA']);
            var mimeType = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE']);
            
            var tileUrlFunc = null; 

            var idx_ccode = window.hWin.HEURIST4.terms.fieldNamesToIndex.trm_ConceptID;
            
            tilingSchema = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'][tilingSchema];
            mimeType = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'][mimeType];
            
            if(tilingSchema && tilingSchema[idx_ccode]=='2-549'){ //virtual earth

                tileUrlFunc = function (a,b) {

                    function __tileToQuadKey(x, y, zoom) {
                        var i, mask, cell, quad = "";
                        for (i = zoom; i > 0; i--) {
                            mask = 1 << (i - 1);
                            cell = 0;
                            if ((x & mask) != 0) cell++;
                            if ((y & mask) != 0) cell += 2;
                            quad += cell;
                        }
                        return quad;
                    }


                    var res = sourceURL + __tileToQuadKey(a.x,a.y,b) 
                    + (mimeType && mimeType[idx_ccode] == '2-540'? ".png" : ".gif");
                    return res;
                };

            }else{

                tileUrlFunc = function(coord, zoom) {
                    //console.log(coord);
                    //console.log(zoom);

                    var bound = Math.pow(2, zoom);
                    var tile_url = sourceURL + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) 
                    + (mimeType && mimeType[idx_ccode] == '2-540'? ".png" : ".gif");
                    //console.log("URL: " + tile_url);
               
                    return tile_url;
                };

            }


            // Tile type
            var tileType = new google.maps.ImageMapType({
                getTileUrl: tileUrlFunc,
                tileSize: new google.maps.Size(256, 256),
                minZoom: 1,
                maxZoom: 20,
                radius: 1738000,
                name: _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'])
            });
  
/*
        visible
        setVisibility
        removeOverlay
        zoomToOverlay
*/
            var overlay_index = options.gmap.overlayMapTypes.push( tileType )-1;

            imageLayer = {
                
                overlay_index: overlay_index,
                
                visible:true,
                // Set visibility
                setVisibility: function(isvisible) {
                    console.log("Setting visibility to: " + isvisible);
                    this.visible = isvisible;
                    if(isvisible) {
                        options.gmap.overlayMapTypes.setAt(this.overlay_index, tileType);
                    }else{
                        options.gmap.overlayMapTypes.setAt(this.overlay_index, null);        
                    }
                },
                removeOverlay: function(){
                    options.gmap.overlayMapTypes.setAt(this.overlay_index, null);
                },
                zoomToOverlay: function(){
                    //gmap.fitBounds(bounds);
                }
            };

        }
        
        return imageLayer;
    }
    
    //
    // add image
    //
    function _addImage(){
        
        
    }
    
    //
    // add kml
    // files
    // kmlSnippet
    //
    function _addKML() {
        
        var kmlLayer = null;
        
        var fileID = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
        var kmlSnippet = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_KML']);

        // KML file
        if(!window.hWin.HEURIST4.util.isnull(fileID)) {

            var fileURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+fileID[0];
            
            // note google refuses kml from localhost
            //console.log("KML file: " + fileURL);
            
            // Display on Google Maps
            kmlLayer = new google.maps.KmlLayer({
                url: fileURL,
                suppressInfoWindows: true,
                preserveViewport: options.preserveViewport,
                map: options.gmap,
                status_changed: function(){
                    //console.log('status: '+kmlLayer.getStatus());
                }
            });
        }

        // KML snippet
        if(!window.hWin.HEURIST4.util.isnull(kmlSnippet)) {
            /** NOTE: Snippets do not seem to be supported by the Google Maps API straight away.. */
            //console.log("KML snippet: " + kmlSnippet);

            // Display on Google Maps
            kmlLayer = new google.maps.KmlLayer(kmlSnippet, {
                suppressInfoWindows: true,
                preserveViewport: options.preserveViewport,
                map: options.gmap
            });
        }

        if(kmlLayer!=null){
            
            kmlLayer.title = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
            kmlLayer.url = fileURL;
            kmlLayer.visible = true;
            // Set visiblity method
            kmlLayer.setVisibility = function(isvisible) {
                this.visible = isvisible;
                if(isvisible) {
                    this.setMap(options.gmap);
                }else{
                    this.setMap(null);
                }
            };

            kmlLayer.removeOverlay = function(){
                this.setMap(null);
            };
            
            kmlLayer.zoomToOverlay = function(){
                //map.fitBounds(bounds);
                console.log('to implement');
            };
        }
        
        return kmlLayer;
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
                        options.mapwidget.mapping('addGeoJson', geojson_data, timeline_data, _recordset.fld(_record, 'rec_Title') );
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );          
        }
        
    }


    //
    // recordset layer - get record ids and returns geojson via _addQueryLayer
    //
    function _addRecordsetLayer(){
        
        if(options.map_recordset){
            
                var curr_request;
                
                if(options.map_recordset.length()<2001){ //limit query by id otherwise use current query
                    curr_request = { w:'all', q:'ids:'+options.map_recordset.getIds().join(',') };
                }else{
                    curr_request = options.map_recordset.getRequest();
                }            
                
                _record = {rec_Title: options.map_recordset_name, d:{}};
                _record['d'][DT_QUERY_STRING] = curr_request;
            
                _addQueryLayer();
        }

    }
    

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        setVisibility:function(isvisible){
            if(_map_overlay) _map_overlay.setVisibility( isvisible );  
        },
            
        zoomToLayer: function(){
            
        },
        
        removeLayer: function(){
            if(_map_overlay){
                _map_overlay.removeOverlay();  
                delete _map_overlay;
                _map_overlay = null;
            } 
        }
    }

    _init( _options );
    return that;  //returns object
}
