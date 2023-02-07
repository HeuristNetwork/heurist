/**
* filename: google overlay
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
function hMapLayer( _options ) {
    var _className = "MapLayer",
    _version   = "0.4";

    var options = {
      //gmap:              //google map
      //recordset:  
      //record
      preserveViewport: true  
    };
    
    var _record, _recordset; //record from recordset
    var _map_overlay;
    
    //
    //
    //
    function _init( _options ){

        options = $.extend(options, _options);
        
        _recordset = options.recordset;
        if(!options.record){
            _record = _recordset.getFirstRecord();
        }else{
            _record = options.record;
        }
        
        //detect layer type
        var rectypeID = _recordset.fld(_record, 'rec_RecTypeID');

        if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){
            
            _map_overlay = _addTiledImage( null );
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

            _map_overlay = _addImage();
            
        }else if(rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE']){
            
            _map_overlay = _addKML();
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
        
        var imageLayer = null;
        
        // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
        if(!window.hWin.HEURIST4.util.isempty(layer_url)){

            var tilingSchema = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA']);
            var mimeType = _recordset.fld(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE']);
            
            var tileUrlFunc = null; 

            var ccode1 = $Db.getConceptID('trm', tilingSchema);
            var ccode2 = $Db.getConceptID('trm', mimeType);
            
            if(ccode1=='2-549'){ //virtual earth

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


                    var res = layer_url + __tileToQuadKey(a.x,a.y,b) 
                    + (ccode2=='2-540'? ".png" : ".gif");
                    return res;
                };

            }else{

                tileUrlFunc = function(coord, zoom) {
                    //console.log(coord);
                    //console.log(zoom);

                    var bound = Math.pow(2, zoom);
                    var tile_url = layer_url + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) 
                    + (ccode2=='2-540'? ".png" : ".gif");
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
