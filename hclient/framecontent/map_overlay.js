/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

//ARTEM:   @todo JJ calls server side directly - need to fix - use hapi!!!!!
//
// move all these methods into hMapping class or create new one


/**
*  This class responsible for all interaction with UI and map object 
*/
function hMappingControls( mapping, startup_mapdocument_id ) {
    var _className = "MappingControls",
    _version   = "0.4";

    var mapping; //parent container
    
    
    var map; //google map
    var current_map_document_id = 0;
    var map_data;                  // all map documents/layer/dataset related info
    var overlays = {};             // layers in current map document
    var map_bookmarks = [];        // geo and time extents
    var overlays_not_in_doc = {};  // main layers(current query) and layers added by user/manually
    var loadingbar = null;         // progress bar - overlay on map 
    var $dlg_edit_layer = null;

/**
* Performs an API call which contains data - all map documents/layer/dataset related info.
* The list of map documents will be loaded into selector
*/
function _loadMapDocuments(startup_mapdocument) {

    // Load Map Documents & Map Layers       @TODO - change it to HAPI method!!!!
    var api = top.HAPI4.basePathV4 + "hserver/controller/map_data.php?db=" + top.HAPI4.database; // window.location.search;
//console.log("API call URL: " + api);
    $.getJSON(api, function(_data) {
        map_data = _data;
//console.log("DATA");
//console.log(map_data);

        // Have any map documents been defined?
        if(map_data && map_data.length > 0) {
            
            // Show options in dropdown
            var ele = $("#map-doc-select");
            ele.empty();
            ele.append("<option value='-1'>"+(map_data.length>0?'select...':'none available')+"</option>");
            for(var i = 0; i < map_data.length; i++) {
                ele.append("<option value='"+map_data[i].id+"'>"+map_data[i].title+"</option>"); //["+map_data[i].id+"]

                if(map_data[i].bookmarks==null){
                    map_data[i].bookmarks = [];
                }
                map_data[i].bookmarks.push([top.HR('World'),-90,90,-30,50,1800,2050]); //default
            }

            // select listener - load map documents
            ele.change(function(e) {
                if(current_map_document_id == $(this).val()) return;
                current_map_document_id = $(this).val();
                _loadMapDocumentById( null );
            });
            
            if(startup_mapdocument > 0){
                _loadMapDocumentById(startup_mapdocument);
            }
        }
    }).fail(function( jqxhr, textStatus, error ) {
        var msg = "Map Document API call failed: " + textStatus + ", " + error;
        console.log(msg);
        top.HEURIST4.msg.showMsgErr(msg);
    });
}

//
//
//
function _loadMapDocumentById(mapdocument_id) {
//console.log('load document '+mapdocument_id);

        var mapdocs = $("#map-doc-select");
        if(mapdocument_id){
            if(mapdocs.val()==mapdocument_id){
                return;
            }
            mapdocs.val(mapdocument_id);
        }else{
            mapdocument_id = mapdocs.val();
        }

        current_map_document_id = mapdocument_id;
        
        // Clean old data
        _removeMapDocumentOverlays();
        var selBookmakrs = document.getElementById('selMapBookmarks');
        $(selBookmakrs).empty();
        $('#map_extents').hide();
                        
        
        //find mapdoc data
        var index = -1;
        if(mapdocument_id>0 && map_data)
        for(var i=0;i<map_data.length;i++){
            if(mapdocument_id==map_data[i].id){
                index = i;
                break;
            }
        }

        var btnMapEdit = $("#btnMapEdit");
        if(index >= 0) {
            var doc = map_data[index];
            
            var bounds = null, err_msg_all = '';
            
            map_bookmarks = [];
            top.HEURIST4.ui.addoption(selBookmakrs, -1, 'bookmarks...');
            
            for(var i=0;i<doc.bookmarks.length;i++){
                
                var bookmark = doc.bookmarks[i];
                var err_msg = '';
                
                if(bookmark.length<5){
                    err_msg = 'no enough parameters';
                }else{
                
                    var x1 = _validateCoord(bookmark[1],false); 
                    if(isNaN(x1)) err_msg = x1;
                    var x2 = _validateCoord(bookmark[2],false);
                    if(isNaN(x2)) err_msg = x1;
                    var y1 = _validateCoord(bookmark[3],true);
                    if(isNaN(y1)) err_msg = x1;
                    var y2 = _validateCoord(bookmark[4],true);
                    if(isNaN(y2)) err_msg = x1;
                    
                    var tmin = null, tmax = null, dres = null;
                    
                    if(err_msg==''){
                    
                        if(x1>x2 || y1>y2){
                            err_msg = 'coordinates are inverted';    
                        }else{
                            if(bookmark.length>6){
                                dres = top.HEURIST4.util.parseDates(bookmark[5], bookmark[6]);
                            }
                            if(dres!==null){
                                tmin = dres[0];
                                tmax = dres[1];
                                //dres = top.HEURIST4.util.parseDates(1800,2050);
                            }
                        }
                    }
                
                }
                
                if(err_msg!=''){
                    err_msg_all = err_msg_all + '<br>"' + bookmark.join(',')+'"  - '+err_msg;
                    continue;
                }
                
                var swBound = new google.maps.LatLng(y1, x1);
                var neBound = new google.maps.LatLng(y2, x2);
                bounds = new google.maps.LatLngBounds(swBound, neBound);
                
                top.HEURIST4.ui.addoption(selBookmakrs, map_bookmarks.length, bookmark[0]?bookmark[0]:'Extent '+(map_bookmarks.length+1));
                
                map_bookmarks.push({extent:bounds, tmin:tmin, tmax:tmax});
                
                if(i==doc.bookmarks.length-2 && map_bookmarks.length>0){
                    break; //skip last default bookmark if user define its own extents
                }
            }//for
            if(err_msg_all!=''){
                top.HEURIST4.msg.showMsgErr('<div>Map zoom bookmark is not interpretable, set to xmin,xmax,ymin,ymax,tmin,tmax</div><br>Contains:'
                +err_msg_all
                +'<br><br><div>Please edit the map document (button next to map name dropdown above) and correct the contents of the map zoom bookmark following the instructions in the field help.</div>'
                );
            }else{
                //show info popup
                var lt = top.HAPI4.sysinfo['layout'];
                if(lt && lt.indexOf('DigitalHarlem')==0){ //for DigitalHarlem we adds 2 dataset - points and links
                    if(!top.HEURIST4.util.isempty( doc['description']) ){
                        top.HEURIST4.msg.showMsgDlg(doc['description'], null, doc['title'], null, false);
                    }
                }
            }

            
               
            var selBookmakrs = document.getElementById('selMapBookmarks')                  
            selBookmakrs.onchange = function(){
                var val = $(selBookmakrs).val();
                if(val>=0){
                    map.fitBounds(map_bookmarks[val]['extent']);    
                    if(map_bookmarks[val]['tmin']!=null)
                        mapping.timelineZoomToRange(map_bookmarks[val]['tmin'],map_bookmarks[val]['tmax']);
                }
            }
            $('#map_extents').show();
            selBookmakrs.selectedIndex = 1;
            $(selBookmakrs).change();

            // Map document layers
            var overlay_index = 1;
            if(doc.layers.length > 0) {
                for(var i = 0; i < doc.layers.length; i++) {
                    _addLayerOverlay(bounds, doc.layers[i], overlay_index, true);
                    overlay_index++;
                }
            }
            // Top layer - artem: JJ made it wrong
            //_addLayerOverlay(bounds, doc.toplayer, index);
            
            _initLegendListeners();
            
            btnMapEdit.button( "enable" );
            btnMapEdit.attr('title',"Edit current map "+doc.title+" - add or remove map layers, change settings");
        }else{
            btnMapEdit.button( "disable" );
            btnMapEdit.attr('title','');
        }

}

//
//
function _validateCoord(value, islat){
    var crd = Number(value);
    if(isNaN(crd)){
      return value+" is not number value";
    }else if(!islat && Math.abs(crd)>180){
      return value+" is wrong longitude value";
    }else if(islat && Math.abs(crd)>90){
      return value+" is wrong latitude value";
    }
    return crd;
}

/**
* Removes all overlays that are in map document (layers added manually remain in the legend and on map)
* it is invoked on map document load only
*/
function _removeMapDocumentOverlays() {
    
    var legend_content = $("#map_legend .content");
    
    for(var idx in overlays) {
        _removeOverlayById(idx);
    }
    overlays = {};
}

//
// dependent_layers -  dependent map layers  [{key :title}]
//
function _addLegendEntryForLayer(overlay_idx, title, rectypeID_or_color, dependent_layers, ontop){

    var overlay = null,
        legendid,
        rectypeID = 0,
        ismapdoc = (overlay_idx>0);
    var icon_bg;
        
        
    if (ismapdoc) {
        legendid = 'md-'+overlay_idx;
        overlay = overlays[overlay_idx];
        rectypeID = rectypeID_or_color;
        
        icon_bg = ' style="background-image: ';
        if(rectypeID==RT_SHP_SOURCE && overlay.visible){
            icon_bg = icon_bg+'url('+top.HAPI4.basePathV4+'hclient/assets/loading-animation-white20.gif);'
                    + 'background-position: center; background-repeat: no-repeat;"'            
                    + ' data-icon="'+top.HAPI4.iconBaseURL + rectypeID + '.png"';
        } else{
            icon_bg = icon_bg+'url('+ top.HAPI4.iconBaseURL + rectypeID + '.png)"';
        }
    }else{
        legendid = overlay_idx;
        overlay = overlays_not_in_doc[overlay_idx];
    }
    if(!overlay) return;
    
    var warning = '';
    if($.isPlainObject(title)){
        warning = title.warning;
        title = title.title;
    }
    
    
    var legenditem = '<div style="display:block;padding:2px;" id="'
            + legendid+'"><input type="checkbox" style="margin-right:5px" value="'
            + overlay_idx+'" id="chbox-'+legendid+'" class="overlay-legend" '
            + (overlay.visible?'checked="checked">':'>')
            + ((ismapdoc)
            ? ('<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif"'
                + ' align="top" class="rt-icon" ' + icon_bg     
                + '>')
            : ('<div style="display:inline-block;vertical-align:-3px;border:6px solid '+rectypeID_or_color+'" />')
            )
            + '<label for="chbox-'+legendid+'" style="padding-left:1em">' + title
            + '</label>'
            + warning
            + '</div>';
       
    legenditem = $(legenditem);
            
    var legend_content = $("#map_legend .content");    
            
    if(ontop){
        legend_content.prepend(legenditem);
    }else if(ismapdoc){  //insert according to order
        
        
       if( legend_content.children().each(function () { 
           var did = Number( $(this).attr('id').substring(3) );
           if(overlay_idx<did){
                $(this).before( legenditem );      
               return false;
           }
       }) ){
            legend_content.append(legenditem);    
       }
        
    }else{
        legend_content.append(legenditem);
    };
    
    legenditem.find(".overlay-legend").change(_showHideOverlay);

    if(!ismapdoc){
    
        $('<div class="svs-contextmenu ui-icon ui-icon-close" layerid="'+overlay_idx+'"></div>')
        .click(function(event){ 
                 //delete layer from map  
                 var overlay_id = $(this).attr("layerid");
                 _removeOverlayById( overlay_id );
                 
                 top.HEURIST4.util.stopEvent(event); return false;})
        .appendTo(legenditem);
        $('<div class="svs-contextmenu ui-icon ui-icon-pencil" layerid="'+overlay_idx+'"></div>')
        .click(function(event){ 
                 
                 var overlay_id = $(this).attr("layerid");
                 var overlay = overlays[overlay_id] ?overlays[overlay_id] :overlays_not_in_doc[overlay_id];  //overlays[index]
                 
                 if(overlay['editProperties']){
                    overlay.editProperties();
                 }
                 
                 top.HEURIST4.util.stopEvent(event); return false;})
        .appendTo(legenditem);
    
    }
    //add linked layers
    
    if(top.HEURIST4.util.isArrayNotEmpty(dependent_layers)){     
        var idx;
        for (idx in dependent_layers){
            var mapdata_id = dependent_layers[idx].key;
            var mapdata_title = dependent_layers[idx].title;
            $('<div style="font-size:smaller;padding-left:16px"><label><input type="checkbox" '
            + ' data-mapdataid="'+mapdata_id+'" class="overlay-legend-depend" '+(overlay.visible?'checked="checked">':'>')
            + mapdata_title + '</label></div>').appendTo(legenditem);        
        }            
        legenditem.find(".overlay-legend-depend").change(_showHideLayer);
    }
    

}      

//
//
//
function _showHideOverlay(event){
        // Hide or display the layer
        var overlay_idx = $(this).prop("value");
        var checked = $(this).prop("checked");

        // Update overlay
        var overlay = overlays[overlay_idx] ?overlays[overlay_idx] :overlays_not_in_doc[overlay_idx];  //overlays[index]
        if(overlay){
            overlay.setVisibility(checked);
            overlay.visible = checked;
        }
}
function _showHideLayer(event){
        var mapdata_id = $(this).attr('data-mapdataid');
        var checked = $(this).prop("checked");

        mapping.showDataset(mapdata_id, checked);
}
    
//
// assign listeners for checkboxes
//
function _initLegendListeners() {
        if(Object.keys(overlays_not_in_doc).length + Object.keys(overlays).length>0){
            $("#map_legend").show();
        }else{
            $("#map_legend").hide();
        }
}

/**
* Adds an overlay for the Layer object
* @param layer Layer object
*/
function _addLayerOverlay(bounds, layer, index, is_mapdoc) {
    //console.log("_addLayerOverlay");
    //console.log(layer);

    // Determine way of displaying
    if(layer !== undefined && layer.dataSource !== undefined) {
        var source = layer.dataSource;
        console.log(source);
          
        source.title = layer.title;

        /** MAP IMAGE FILE (TILED) */
        if(source.rectypeID == RT_TILED_IMAGE_SOURCE) {
            console.log("MAP IMAGE FILE (tiled)");
            addTiledMapImageLayer(source, bounds, index);

        /** MAP IMAGE FILE (NON-TILED) */
        }else if(source.rectypeID == RT_GEOTIFF_SOURCE) {
            // Map image file (non-tiled)
            console.log("MAP IMAGE FILE (non-tiled)");
            addUntiledMapImageLayer(source, bounds, index);

        /** KML FILE OR SNIPPET */
        }else if(source.rectypeID == RT_KML_SOURCE) {
            console.log("KML FILE or SNIPPET");
            addKMLLayer(source, index, is_mapdoc);

        /** SHAPE FILE */
        }else if(source.rectypeID == RT_SHP_SOURCE) {
            console.log("SHAPE FILE");
            addShapeLayer(source, index);

        /* MAPPABLE QUERY */
        }else if(source.rectypeID == RT_MAPABLE_QUERY) {
            console.log("MAPPABLE QUERY");
            _addQueryLayer(source, index);
        }
        
        if(source.rectypeID != RT_MAPABLE_QUERY){
            _addLegendEntryForLayer(index, layer.title, source.rectypeID);
        }
        
    }
}

function _getStubOverlay(){
    return {visible:true,
            setVisibility:function(checked){},
            removeOverlay: function(){}};
}

/**
* Adds a tiled map image layer to the map
* @param source Source object
*/
function addTiledMapImageLayer(source, bounds, index) {

    // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
     if(source.sourceURL !== undefined) {
         
        var tileUrlFunc = null; 
         
        if(source.tilingSchema.label=='virtual earth'){
            
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

                
                    var res = source.sourceURL + __tileToQuadKey(a.x,a.y,b) 
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                    return res;
                };
    
        }else{
            
            tileUrlFunc = function(coord, zoom) {
                //console.log(coord);
                //console.log(zoom);

                var bound = Math.pow(2, zoom);
                var url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) 
                            + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                //console.log("URL: " + url);
                return url;
            };
            
        }
         
         
        // Tile type
        var tileType = new google.maps.ImageMapType({
            getTileUrl: tileUrlFunc,
            tileSize: new google.maps.Size(256, 256),
            minZoom: 1,
            maxZoom: 20,
            radius: 1738000,
            name: "tile"
        });

        // Set map options
        map.overlayMapTypes.insertAt(index, tileType);
        map.overlayMapTypes.setAt(index, tileType);

        var overlay = {
            visible:true,
            // Set visibility
            setVisibility: function(checked) {
                console.log("Setting visibility to: " + checked);
                this.visible = checked;
                if(checked) {
                    map.overlayMapTypes.setAt(index, tileType);
                }else{
                    map.overlayMapTypes.setAt(index, null);
                }
            },
            removeOverlay: function(){
                map.overlayMapTypes.setAt(index, null);
            }
        };

        overlays[index] = overlay;
     }

}

/**
* Adds an untiled map image layer to the map
* @param source Source object
*/
function addUntiledMapImageLayer(source, bounds, index) {
    // Image
    var msg = '';                   
    if(top.HEURIST4.util.isempty(source.files) || top.HEURIST4.util.isempty(source.files[0])){
        msg = 'Image file is not defined';
    }else if(!source.bounds){        
        msg = 'Image\'s extent is not defined';
    }else if(source.files[0].endsWith('.tiff')||source.files[0].endsWith('.tif')){
        msg = 'At this time the GMaps mapping component used does not support GeoTIFF.';
    }
    
    if(msg=='') {
        var imageURL = source.files[0];
    
        var image_bounds = top.HEURIST4.util.parseCoordinates('rect', source.bounds, 1, google);
        
        var overlay = new HeuristOverlay(image_bounds.bounds, imageURL, map);
        
        overlays[index] = overlay;
    }else{
        overlays[index] = _getStubOverlay();
                           
        top.HEURIST4.msg.showMsgErr('Map layer: '+source.title
                +'<br>Unable to process this dataset. '+msg);
        //Please check that the file or service specified is in one of the supported formats. 
    }
}


/**
* Adds a KML layer to the map
* @param source Source object
*/
function addKMLLayer(source, index, is_mapdoc) {
    var kmlLayer = {};
    
    if(is_mapdoc!==true){
        is_mapdoc = false;
    }

    // KML file
    if(source.files !== undefined) {
        var fileURL = source.files[0];

        // note google refuses kml from localhost
        console.log("KML file: " + fileURL);
        // Display on Google Maps
        kmlLayer = new google.maps.KmlLayer({
            url: fileURL,
            suppressInfoWindows: true,
            preserveViewport: is_mapdoc,
            map: map,
            status_changed: function(){
                console.log('status: '+kmlLayer.getStatus());
            }
        });
    }

    // KML snippet
    if(source.kmlSnippet !== undefined) {
        /** NOTE: Snippets do not seem to be supported by the Google Maps API straight away.. */
        console.log("KML snippet: " + source.kmlSnippet);

        // Display on Google Maps
        kmlLayer = new google.maps.KmlLayer(source.kmlSnippet, {
            suppressInfoWindows: true,
            preserveViewport: is_mapdoc,
            map: map
        });
    }


    kmlLayer.visible = true;
    // Set visiblity method
    kmlLayer.setVisibility = function(checked) {
        this.visible = checked;
        if(checked) {
            kmlLayer.setMap(map);
        }else{
            kmlLayer.setMap(null);
        }
    };
    
    kmlLayer.removeOverlay = function(){
                kmlLayer.setMap(null);
            };
    

    overlays[index] = kmlLayer;

}



/**
* Adds a shape layer to the map
* @param source Source object
*/
function addShapeLayer(source, index) {
    
    overlays[index] = _getStubOverlay();
    // File check
    if(false && source.zipFile !== undefined) {
        // Zip file
        console.log("Zip file: " + source.zipFile);
    }else{
        

        // Individual components
        if(source.shpFile !== undefined && source.dbfFile !== undefined) {
            // .shp & .dbf
            
            function __getShapeData(index){
                var deferred = $.Deferred();
                setTimeout(function () { 
                console.log("Reading DATA:");
                new Shapefile({
                    shp: source.shpFile,
                    dbf: source.dbfFile
                }, function (data) {
                    //addGeoJsonToMap(data, index);
                    deferred.resolve(data, index);
                });
                }, 500);
                    
                return deferred.promise();
            }
            
            $.when( __getShapeData(index) ).done(addGeoJsonToMap);
        }

    }
}

/**
* Adds GeoJson data to the map
* @param data Data returned by the Shapefile parser
*/
function addGeoJsonToMap(data, index) {
    // Add GeoJson to map
    //console.log(data);
    
    var overlay = {
            visible:false,
            features: null,
            data: data,
            
            // Set visibility
            setVisibility: function(checked) {
                if(this.visible == checked) return;
                this.visible = checked;
                if(checked) {
                    this.features = map.data.addGeoJson(data.geojson);
                }else if(this.features!=null) {
                    for (var i = 0; i < this.features.length; i++) {
                        map.data.remove(this.features[i]);
                    }
                    this.features = null;
                }
            },
            removeOverlay: function(){
                this.setVisibility(false);
            }
    };
    overlays[index] = overlay;
    overlay.setVisibility(true);

    var $img = $('#map_legend').find('#md-'+index+' > img.rt-icon');
    $img.css('background-image','url('+$img.attr('data-icon')+')');
    
}



/**
* Adds a query layer to the map
* @param source - parameters Source object
* if index < 0 it does not belong to current map document
*/
function _addQueryLayer(source, index) {
     // Query
    if(source.query !== undefined) {
        //console.log("Query: " + source.query);

        var request = null;
        try{
            var query = top.HEURIST4.util.isObject(source.query) ?source.query :JSON.parse(source.query);
            if(query && query['q']){
                request = { q: query['q'], 
                            rules: query['rules'],
                            w: query['w']?query['w']:'all'}; 
            }
        }catch(err){
        }
        if(request==null){
            if(source.query){ //this is simple (non JSON) query without rules
                request = {q: source.query, w: 'all'};
            }else{
                $('#mapping').css('cursor','auto');
                return;
            }
        }
        
        //request['getrelrecs'] = 1;  //return all related records including relationship records
        request['detail'] = 'timemap';
        
        if(loadingbar==null){
            var image = top.HAPI4.basePathV4+'hclient/assets/loading_bar.gif';
            loadingbar = new google.maps.Marker({
                    icon: image,
                    optimized: false
                });            
        }
        if(loadingbar){
            loadingbar.setMap(map);
            loadingbar.setPosition(map.getCenter());
        } 
        
        $('#mapping').css('cursor','progress');
        
        // Retrieve records for this request
        top.HAPI4.SearchMgr.doSearchWithCallback( request, function( recordset, original_recordset ){

            if(loadingbar)  loadingbar.setMap(null);
            
            if(recordset!=null){
                source.recordset = recordset;
                source.recordset.setMapEnabled( true );
                _addRecordsetLayer(source, index);
            }
            if( source.callback && $.isFunction(source.callback) ){
                   source.callback( recordset, original_recordset );     
            }
            
            $('#mapping').css('cursor','auto');
            
        });
        
        /*
        top.HAPI4.RecordMgr.search(request,
            function(response){
                //console.log("QUERY RESPONSE:");
                //console.log(response);

                if(response.status == top.HAPI4.ResponseStatus.OK){

                    source.recordset = hRecordSet(response.data);
                    source.recordset.setMapEnabled( true );
                    
                    _addRecordsetLayer(source, index);

                }else{
                    top.HEURIST4.msg.showMsgErr(response);
                }
            }
        );*/


    }
}

var myColors = ['rgb(255,0,0)','rgb(0,255,0)','rgb(0,0,255)','rgb(255,127,39)','rgb(34,177,76)','rgb(0,177,232)','rgb(163,73,164)'];
var colors_idx = -1;

/**
*  if recordset has property mapenabled = true, convert recordset to timemap and vis_timeline formats 
*  if mapenabled = false the request to server side is performed for first 1000 map/time enabled records
* 
*  source
*       id
*       title
*       recordset - in harlem format
*       mapdata - in timemap/vis format
* 
* @todo - unite with mapping.addDataset
*/
function _addRecordsetLayer(source, index) {

    // Show info on map
    var mapdata = source.mapdata;
    if( top.HEURIST4.util.isnull(mapdata) ) {
            var recset = source.recordset;

            if( !top.HEURIST4.util.isnull(recset) ){
            
                if(!recset.isMapEnabled()){

                    var request = {w: 'all', 
                                   detail: 'timemap', 
                                   };
                    
                    if(recset.length()<2001){ //limit query by id otherwise use current query
                        source.query = { q:'ids:'+recset.getIds().join(',') };
                    }else{
                        var curr_request = recset.getRequest();
                        
                        source.query = { 
                                    q: curr_request.q,
                                    rules: curr_request.rules,
                                    w: curr_request.w};
                    }
                    
                    _addQueryLayer( source, index );
                           
                    // Retrieve records for this request
                    /*
                    top.HAPI4.RecordMgr.search(request,
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){

                                source.recordset = hRecordSet(response.data);
                                source.recordset.setMapEnabled( true );
                                _addRecordsetLayer(source, index);

                            }else{
                                top.HEURIST4.msg.showMsgErr(response);
                            }
                        }
                    );
                    */       
                                   
                                                   
                    return;               
                }
               
                var lt = top.HAPI4.sysinfo['layout'];
                if(lt && lt.indexOf('DigitalHarlem')==0){ //for DigitalHarlem we adds 2 dataset - points and links

                    if(colors_idx>=myColors.length) colors_idx = -1;
                    colors_idx++;
                    source.color = myColors[colors_idx];
                
                    //points
                    mapdata = recset.toTimemap(source.id, null, source.color, 1); //main geo only
                    mapdata.id = source.id;
                    mapdata.title = source['title']?source['title']:mapdata.id;
                    
                    mapdata.depends = [];
                    
                    //links
                    var mapdata2 = recset.toTimemap(source.id, null, source.color, 2); //rec_Shape only
                    mapdata2.id = "link_"+top.HEURIST4.util.random();
                    mapdata2.title = 'Links';
                    mapdata2.timeenabled = 0;
                    mapdata2.timeline = {items:[]};
                    if(mapdata2.mapenabled>0){
                        mapdata.depends.push(mapdata2);
                    }
                    
                }else{
                
                    mapdata = recset.toTimemap(source.id, null, source.color);
                    mapdata.id = source.id;
                    mapdata.title = source['title']?source['title']:mapdata.id;
                    
                    if(recset.count_total()>recset.length()){
                        var s = '<p>The map and timeline are limited to display a maximum of <b>'+recset.length()+'</b> results to avoid overloading your browser.</p>'
+'<br/><p>There are <b>'+recset.count_total()+'</b> records with spatial and temporal data in the current results set. Please refine your filter to reduce the number of results.</p><br/>'
+'<p>The map/timeline limit can be reset in Profile > Preferences.</p>';                        
                        
                        mapdata.title = {title:mapdata.title,
                        warning:'<div class="ui-icon ui-icon-alert" style="display:inline-block;width:20px" onclick="{top.HEURIST4.msg.showMsgDlg(\''+s+'\')}">&nbsp;</div>'};
                    }
                }
            }
    }    
            

            //mapping.load(mapdata);
            if (mapping.addDataset(mapdata)){ //see map.js
            
                //add depends
                var dependent_layers = [];
                if(top.HEURIST4.util.isArrayNotEmpty(mapdata.depends)){
                    var idx;
                    for (idx in mapdata.depends){
                        var dep_mapdata = mapdata.depends[idx];
                        mapping.addDataset(dep_mapdata);
                        dependent_layers.push({key:dep_mapdata.id, title:dep_mapdata.title});
                    }
                }
            

               var overlay = {
                    id: mapdata.id,
                    title: mapdata.title,
                    dependent_layers: dependent_layers,
                    visible:true,
                    setVisibility: function(checked) {
                        this.visible = checked;
                        mapping.showDataset(this.id, checked); //mapdata.id

                        var idx;
                        for (idx in this.dependent_layers){
                            var mapdata_id = this.dependent_layers[idx].key;
                            mapping.showDataset(mapdata_id, checked);
                            var cb = $("#map_legend .content").find('input[data-mapdataid="'+mapdata_id+'"]');
                            if(cb.length>0){
                                cb.prop('checked',checked);
                            }
                        }
                    },
                    removeOverlay: function(){
                        mapping.deleteDataset( this.id ); //mapdata.id);
                        this.removeDependentLayers();
                    },
                    removeDependentLayers: function(){
                        if(this.dependent_layers){
                            var idx;
                            for (idx in this.dependent_layers){
                                mapping.deleteDataset( this.dependent_layers[idx].key );
                            }
                        }
                    },
                    editProperties: function(){
                        _editLayerProperties( this.id );    
                    }
               };
               if(index>=0){  //this layer belong to map document
                    overlays[index] = overlay;
                    
                    _addLegendEntryForLayer(index, mapdata.title, RT_MAPABLE_QUERY, dependent_layers);
                    
               }else{ // this layer is explicitely (by user) added

                    //remove previous entry
                    if(overlays_not_in_doc[source.id]){
                        overlays_not_in_doc[source.id].removeDependentLayers();
                    }
                    
                    overlays_not_in_doc[source.id] = overlay;
                    var legenditem = $("#map_legend .content").find('#'+source.id);
                    if(legenditem.length>0) legenditem.remove();

                    //show custom query on top
                    _addLegendEntryForLayer(source.id, mapdata.title, mapdata.color, dependent_layers, true );
               }
               
               
               _initLegendListeners();

            }else{  //dataset is empty or failed to add - remove from legend
               if(index<0){
                    _removeOverlayById( source.id );
               }
            }
}

//
//
//
function _getOverlayByMapdataId( _mapdataid ){
    for(var idx in overlays) {
        if(overlays.hasOwnProperty(idx) && overlays[idx].id==_mapdataid){
            return overlays[idx];
        }
    }
    for(var mapdataid in overlays_not_in_doc) {
        if(overlays_not_in_doc.hasOwnProperty(mapdataid) && mapdataid==_mapdataid){
            return overlays_not_in_doc[mapdataid];
        }
    }
    return null;
}

//
//
//
function _removeOverlayById( overlay_id ){

    var ismapdoc = (overlay_id>0);
    var overlay = ismapdoc ?overlays[overlay_id] :overlays_not_in_doc[overlay_id];
    if(!top.HEURIST4.util.isnull(overlay)){
        try {
            $("#map_legend .content").find('#'+((ismapdoc)?'md-':'')+overlay_id).remove();

            //overlay.setVisibility(false);
            if(overlay['removeOverlay']){  // hasOwnProperty
                overlay.removeOverlay();
            }
        } catch(err) {
            console.log(err);
        }
        if(ismapdoc){
            delete overlays_not_in_doc[overlay_id];
        }else{
            delete overlays[overlay_id];
        }
        
        _initLegendListeners();
    }
}

var edit_mapdata, edit_callback;

//open dialog and edit layer/dataset properties - name and color
function _editLayerProperties( dataset_id, callback ){
    
       edit_mapdata = mapping.getDataset( dataset_id );
       edit_callback = callback;
       if( !top.HEURIST4.util.isempty(dataset_id) && top.HEURIST4.util.isnull(edit_mapdata) ){
           if (edit_callback) edit_callback(false);
           return;
       }
    
       function __doSave(){   //save search
            
            var layer_name = $dlg_edit_layer.find("#layer_name");
            var message = $dlg_edit_layer.find('.messages');
            
            var bValid = top.HEURIST4.msg.checkLength( layer_name, "Name", message, 1, 30 );
            
            if(bValid){
                
                // if this is 'main' dataset (current query) 
                //      get mapdata from all_mapdata, generate id, change title and loop to change color (if required)
                //      add new overlay/dataset,  remove current (main) dataset
                // else 
                //      change titles in overlay, legend, timeline
                //      if required loop mapdata.options.items to change color and reload dataset
                var new_title = layer_name.val();
                var new_color = $dlg_edit_layer.find('#layer_color').colorpicker('val');
                var mapdata = edit_mapdata;

                if(top.HEURIST4.util.isnull(mapdata) && !top.HEURIST4.util.isnull(top.HAPI4.currentRecordset)){  //add current record set
                        
                        /* load with current result set and new rules
                        _currentRequest??
                        var request = { q: 'ids:'+ top.HAPI4.currentRecordset.getMainSet().join(','),
                                rules: that._currentRequest.rules,
                                w: 'all'};

                        //add new layer with given name
                        var source = {id:"dhs"+top.HEURIST4.util.random(),
                             title: new_title,
                             query: request,
                             color: new_color,
                             callback: function(){
                                 //that.res_div_progress.hide();
                             }
                        };                 
                        _addRecordsetLayer( source, -1);
                        */
                       
                       //load new recordset 
                        var source = {id:"dhs"+top.HEURIST4.util.random(),
                             title: new_title,
                             recordset:  top.HAPI4.currentRecordset,
                             color: new_color
                        };                 
                        _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);
                
                }else if(mapdata.id=='main'){  //rename and keep on map
                        
                        mapdata.id = "dhs"+top.HEURIST4.util.random();
                        mapdata.title = new_title;
                        //change color scheme if required
                        mapping.changeDatasetColor( 'main', new_color, false );
                        //rename dataset for timeline items
                        for (var i=0; i<mapdata.timeline.items.length; i++){
                            mapdata.timeline.items[i].id = mapdata.id + '-' +  mapdata.timeline.items[i].recID;
                            mapdata.timeline.items[i].group = mapdata.id 
                        }
                        //change color for dependent
                        var idx;
                        for (idx in mapdata.depends){
                            var dep_mapdata = mapdata.depends[idx];
                            mapping.changeDatasetColor( dep_mapdata.id, new_color, false);
                        }
                        
                        //remove old 
                        _removeOverlayById( 'main' );
                        //add new    
                        _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);
                        
                        /*var idx;
                        for (idx in mapdata.depends){
                            var dep_mapdata = mapdata.depends[idx];
                            mapping.addDataset(dep_mapdata);
                        }*/
                        
                }else{
                    
                    if(mapdata.title!=new_title){
                        mapdata.title = new_title;
                        $("#map_legend .content").find('#'+mapdata.id+' label').html(new_title);
                        //$('#timeline > div > div.vis-panel.vis-left > div.vis-content > div > div:nth-child(2) > div
                        $('#timeline div[data-groupid="'+mapdata.id+'"]').html(new_title);
                    }
                    if(mapdata.color!=new_color){
                        $("#map_legend .content").find('#'+mapdata.id+'>div').css('border-color',new_color);
                    }
                    
                    var idx;
                    for (idx in mapdata.depends){
                        var dep_mapdata = mapdata.depends[idx];
                        mapping.changeDatasetColor( dep_mapdata.id, new_color, true);
                    }
                    mapping.changeDatasetColor( mapdata.id, new_color, true );
                    
                    
                }
                
                $dlg_edit_layer.dialog("close"); 
                if (edit_callback) edit_callback(true);
            }

       }  
       
       function __onOpen(){
            var mapdata = edit_mapdata;
           
            if( mapdata && mapdata.id!='main' ){
                $dlg_edit_layer.find("#layer_name").val(mapdata.title).removeClass( "ui-state-error" );
            }else{
                $dlg_edit_layer.find("#layer_name").val('').removeClass( "ui-state-error" );
            }
            var colorPicker = $dlg_edit_layer.find("#layer_color");
            
            colorPicker.colorpicker('val', mapdata.color);
            
            //colorPicker.css('background-color', mapdata.color)
                       
            $dlg_edit_layer.find(".messages").removeClass( "ui-state-highlight" ).text('');
       }
               
       if($dlg_edit_layer==null){
                // login dialog definition
                $dlg_edit_layer = $('#layer-edit-dialog').dialog({
                    autoOpen: false,
                    //height: 300,
                    width: 450,
                    modal: true,
                    resizable: false,
                    title: top.HR('Define Layer'),
                    //buttons: arr_buttons,
                    buttons: [
                        {text:top.HR('Save'), click: function(){
                            __doSave();   
                        }},
                        {text:top.HR('Cancel'), click: function() {
                            if (edit_callback) edit_callback(false);
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                    },
                    open: function() {
                        __onOpen();
                    }
                });
                
               $dlg_edit_layer.find("#layer_color").colorpicker({
                   hideButton: false, //show button right to input
                   showOn: "both"  //button, focus
               });
               
               /*$dlg_edit_layer.find("#layer_color").colorPicker({
                   cssAddon:'.cp-color-picker{z-index:999999999999 !important;background-color:#fff;border-radius: 0px;}',
                   renderCallback: function($elm, toggled){
                        if($elm && !toggled){
                            $(this.$UI).hide();
                        }
                   }
               });*/
               
       }       
       $dlg_edit_layer.dialog("open");    
    
}

// 
// Data types
//
var localIds = top.HAPI4.sysinfo['dbconst'];
var RT_TILED_IMAGE_SOURCE = localIds['RT_TILED_IMAGE_SOURCE']; //2-11
var RT_GEOTIFF_SOURCE = localIds['RT_GEOTIFF_SOURCE']; //3-1018;
var RT_KML_SOURCE = localIds['RT_KML_SOURCE']; //3-1014;
var RT_SHP_SOURCE = localIds['RT_SHP_SOURCE']; //3-1017;
var RT_MAPABLE_QUERY = localIds['RT_QUERY_SOURCE']; //3-1021;


//
// custom google map overlay
//
HeuristOverlay.prototype = new google.maps.OverlayView();
/**
* HeuristOverlay constructor
* - bounds
* - image
* - map
*/
function HeuristOverlay(bounds, image, map) {
    // Initialize all properties.
    this.bounds_ = bounds;
    this.image_ = image;
    this.map_ = map;
    this.div_ = null;

    // Explicitly call setMap on this overlay.
    this.setMap(map);
    this.visible = true;
}

        // Set visibility
HeuristOverlay.prototype.setVisibility = function(checked) {
    this.visible = checked;
    if(checked) {
        this.setMap(map);
    }else{
        this.setMap(null);
    }
}


 /**
 * onAdd is called when the map's panes are ready and the overlay has been
 * added to the map.
 */
HeuristOverlay.prototype.onAdd = function() {
    // Image div
    var div = document.createElement('div');
    div.style.borderStyle = 'none';
    div.style.borderWidth = '0px';
    div.style.position = 'absolute';

    // Title
    /*
    var span = document.createElement('span');
    span.innerHTML = "Title";
    div.appendChild(span);
    */

    // Create the img element and attach it to the div.
    var img = document.createElement('img');
    img.src = this.image_;
    img.style.width = '100%';
    img.style.height = '100%';
    img.style.position = 'absolute';
    div.appendChild(img);

    this.div_ = div;

    // Add the element to the "overlayLayer" pane.
    var panes = this.getPanes();
    panes.overlayLayer.appendChild(div);
};

/**
* draw is called to draw the overlay
*/
HeuristOverlay.prototype.draw = function() {
    // We use the south-west and north-east
    // coordinates of the overlay to peg it to the correct position and size.
    // To do this, we need to retrieve the projection from the overlay.
    var overlayProjection = this.getProjection();

    // Retrieve the south-west and north-east coordinates of this overlay
    // in LatLngs and convert them to pixel coordinates.
    // We'll use these coordinates to resize the div.
    var sw = overlayProjection.fromLatLngToDivPixel(this.bounds_.getSouthWest());
    var ne = overlayProjection.fromLatLngToDivPixel(this.bounds_.getNorthEast());

    // Resize the image's div to fit the indicated dimensions.
    var div = this.div_;
    div.style.left = sw.x + 'px';
    div.style.top = ne.y + 'px';
    div.style.width = (ne.x - sw.x) + 'px';
    div.style.height = (sw.y - ne.y) + 'px';
};

// The onRemove() method will be called automatically from the API if
// we ever set the overlay's map property to 'null'.
HeuristOverlay.prototype.onRemove = function() {
    this.div_.parentNode.removeChild(this.div_);
    this.div_ = null;
};
//end custom overlay


    /**
    * Initialization
    */
    function _init(_mapping, startup_mapdocument_id) {

        mapping = _mapping;
        map = _mapping.getNativeMap();
        map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(document.getElementById('map_legend'));
        // Legend collapse listener
        $("#collapse").click(function(e) {
            $(this).text() == "-" ? $(this).text("+") : $(this).text("-");  // Update text to + or -
            $("#map_legend .content").toggle(400);
        });
        
        _loadMapDocuments(startup_mapdocument_id);
        
    }

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        
        loadMapDocumentById: function(mapdocument_id){
            _loadMapDocumentById(mapdocument_id);    
        },
        
        addQueryLayer: function(params){
             _addQueryLayer(params, -1);   
        },
        
        addRecordsetLayer: function(params){
             _addRecordsetLayer(params, -1);
        },
        
        editLayerProperties: function( dataset_id, callback ){
            _editLayerProperties( dataset_id, callback );
        }
        
    }

    _init( mapping, startup_mapdocument_id );
    return that;  //returns object
}
