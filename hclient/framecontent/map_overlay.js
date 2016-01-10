
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
// rename div legend to map_legend


/**
*  This class responsible for all interaction with UI and map object 
*/
function hMappingControls( mapping, startup_mapdocument_id ) {
    var _className = "MappingControls",
    _version   = "0.4";

    var mapping; //parent container
    
    
    var map; //google map
    var map_data;                  // all map documents/layer/dataset related info
    var overlays = {};             // layers in current map document
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
            }

            // select listener - load map documents
            ele.change(function(e) {
                var map_document_id = Number($(this).val());
                _loadMapDocumentById( map_document_id );
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
        if(mapdocs.val()==mapdocument_id){
            return;
        }
        mapdocs.val(mapdocument_id);

        // Clean old data
        _removeMapDocumentOverlays();
        
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
            //show info popup
            if(!top.HEURIST4.util.isempty( doc['description']) ){
                top.HEURIST4.msg.showMsgDlg(doc['description'], null, doc['title'], null, true);
            }

            // Zoom to Bounds
            var swBound = new google.maps.LatLng(doc.lat-doc.minorSpan, doc.long-doc.minorSpan);
            var neBound = new google.maps.LatLng(doc.lat+doc.minorSpan, doc.long+doc.minorSpan);
            var bounds = new google.maps.LatLngBounds(swBound, neBound);
            
            map.fitBounds(bounds);

            // Map document layers
            var overlay_index = 1;
            if(doc.layers.length > 0) {
                for(var i = 0; i < doc.layers.length; i++) {
                    _addLayerOverlay(bounds, doc.layers[i], overlay_index);
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

/**
* Removes all overlays that are in map document (layers added manually remain in the legend and on map)
* it is invoked on map document load only
*/
function _removeMapDocumentOverlays() {
    
    var legend_content = $("#legend .content");
    
    for(var idx in overlays) {
        _removeOverlayById(idx);
    }
    overlays = {};
}

//
//
//
function _addLegendEntryForLayer(overlay_idx, title, icon_or_color, ontop){

    var overlay = null,
        legendid,
        ismapdoc = (overlay_idx>0);
        
        
    if (ismapdoc) {
        legendid = 'md-'+overlay_idx;
        overlay = overlays[overlay_idx];
    }else{
        legendid = overlay_idx;
        overlay = overlays_not_in_doc[overlay_idx];
    }
    if(!overlay) return;

    var legenditem = $('<div style="display:block;padding:2px;" id="'
            + legendid+'"><input type="checkbox" style="margin-right:5px" value="'
            + overlay_idx+'" id="chbox-'+legendid+'" '
            + (overlay.visible?'checked="checked">':'>')
            + ((ismapdoc)
            ? ('<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif"'
                + ' align="top" class="rt-icon" '     
                + ( (ismapdoc)?('style="background-image: url('+icon_or_color+');"'):'')+'>')
            : ('<div style="display:inline-block;border:6px solid '+icon_or_color+'" />')
            )
            + '<label for="chbox-'+legendid+'" style="padding-left:1em">' + title
            + '</label></div>');
            
    var legend_content = $("#legend .content");    
            
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
    
    legenditem.find("input").change(_showHideLayer);

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

}      

//
//
//
function _showHideLayer(event){
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

//
// assign listeners for checkboxes
//
function _initLegendListeners() {
        if(Object.keys(overlays_not_in_doc).length + Object.keys(overlays).length>0){
            $("#legend").show();
        }else{
            $("#legend").hide();
        }
}

/**
* Adds an overlay for the Layer object
* @param layer Layer object
*/
function _addLayerOverlay(bounds, layer, index) {
    console.log("_addLayerOverlay");
    console.log(layer);

    // Determine way of displaying
    if(layer !== undefined && layer.dataSource !== undefined) {
        var source = layer.dataSource;
        console.log(source);
          
        source.title = layer.title;

        /** MAP IMAGE FILE (TILED) */
        if(source.rectypeID == map_image_file_tiled) {
            console.log("MAP IMAGE FILE (tiled)");
            addTiledMapImageLayer(source, bounds, index);

        /** MAP IMAGE FILE (NON-TILED) */
        }else if(source.rectypeID == map_image_file_untiled) {
            // Map image file (non-tiled)
            console.log("MAP IMAGE FILE (non-tiled)");
            addUntiledMapImageLayer(source, bounds, index);

        /** KML FILE OR SNIPPET */
        }else if(source.rectypeID == kml_file) {
            console.log("KML FILE or SNIPPET");
            addKMLLayer(source, index);

        /** SHAPE FILE */
        }else if(source.rectypeID == shape_file) {
            console.log("SHAPE FILE");
            addShapeLayer(source, index);

        /* MAPPABLE QUERY */
        }else if(source.rectypeID == RT_MAPABLE_QUERY) {
            console.log("MAPPABLE QUERY");
            _addQueryLayer(source, index);
        }
        
        _addLegendEntryForLayer(index, layer.title, top.HAPI4.iconBaseURL + source.rectypeID + '.png');
        //_addLegendEntryForLayer(index, source.title, top.HAPI4.iconBaseURL + RT_MAPABLE_QUERY +'.png', false );
        
    }
}

/**
* Adds a tiled map image layer to the map
* @param source Source object
*/
function addTiledMapImageLayer(source, bounds, index) {

    // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
     if(source.sourceURL !== undefined) {
        // Tile type
        var tileType = new google.maps.ImageMapType({
            getTileUrl: function(coord, zoom) {
                //console.log(coord);
                //console.log(zoom);

                var bound = Math.pow(2, zoom);
                var url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) + ".png";
                console.log("URL: " + url);
                return url;
            },
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
    var overlay = {};

    // Image
    if(source.sourceURL !== undefined) {
        overlay = new HeuristOverlay(bounds, source.sourceURL, map);

        overlay.visible = true;
        // Set visibility
        overlay.setVisibility = function(checked) {
            this.visible = checked;
            if(checked) {
                overlay.setMap(map);
            }else{
                overlay.setMap(null);
            }
        }
    }

    overlays[index] = overlay;

}



/**
* Adds a KML layer to the map
* @param source Source object
*/
function addKMLLayer(source, index) {
    var kmlLayer = {};

    // KML file
    if(source.files !== undefined) {
        var fileURL = source.files[0];
        console.log("KML file: " + fileURL);

        // Display on Google Maps
        kmlLayer = new google.maps.KmlLayer({
            url: fileURL,
            suppressInfoWindows: true,
            preserveViewport: false,
            map: map
        });
    }

    // KML snippet
    if(source.kmlSnippet !== undefined) {
        /** NOTE: Snippets do not seem to be supported by the Google Maps API straight away.. */
        console.log("KML snippet: " + source.kmlSnippet);

        // Display on Google Maps
        kmlLayer = new google.maps.KmlLayer(source.kmlSnippet, {
            suppressInfoWindows: true,
            preserveViewport: false,
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
    // File check
    if(source.zipFile !== undefined) {
        // Zip file
        console.log("Zip file: " + source.zipFile);

        var layer = {};
        overlays[index] = layer;


    }else{
        console.log("Reading DATA:");

        // Individual components
        if(source.shpFile !== undefined && source.dbfFile !== undefined) {
            // .shp & .dbf
            new Shapefile({
                shp: source.shpFile,
                dbf: source.dbfFile
            }, function (data) {
                addGeoJsonToMap(data, index);
            });
        }


    }
}

/**
* Adds GeoJson data to the map
* @param data Data returned by the Shapefile parser
*/
function addGeoJsonToMap(data, index) {
    // Add GeoJson to map
    console.log(data);
    var features = map.data.addGeoJson(data.geojson);

    // Set visiblity method
    data.setVisibility = function(checked) {
        this.visible = checked;
        if(checked) {
            features = map.data.addGeoJson(data.geojson);
        }else{
            for (var i = 0; i < features.length; i++) {
                map.data.remove(features[i]);
            }
        }
    };
    data.removeOverlay = function() {
            this.setVisibility(false);
    };
    overlays[index] = data;
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
            if(source.query){ //this is simple (non JSON) queru without rules
                request = {q: source.query, w: 'all'};
            }else{
                return;
            }
        }
        //request['getrelrecs'] = 1;  //return all related records including relationship records
        request['detail'] = 'timemap';
        request['limit'] = 2000;
        
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
                                   limit: 3000};
                    
                    if(recset.length()<2001){
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
                
                mapdata = recset.toTimemap(source.id, null, source.color);
                
                mapdata.id = source.id;
                mapdata.title = source['title']?source['title']:mapdata.id;
            }
    }    
            

            //mapping.load(mapdata);
            if (mapping.addDataset(mapdata)){

               var overlay = {
                    id: mapdata.id,
                    title: mapdata.title,
                    visible:true,
                    setVisibility: function(checked) {
                        this.visible = checked;
                        mapping.showDataset(this.id, checked); //mapdata.id
                        
                    },
                    removeOverlay: function(){
                        mapping.deleteDataset( this.id ); //mapdata.id);
                    },
                    editProperties: function(){
                        _editLayerProperties( this.id );    
                        
                        //overlay.title = 
                        //color
                        
                    }
               };
               if(index>=0){  //this layer belong to map document
                    overlays[index] = overlay;
                    
                    _addLegendEntryForLayer(index, source.title, top.HAPI4.iconBaseURL + RT_MAPABLE_QUERY +'.png');
                    
               }else{ // this layer is explicitely (by user) added
                    //index = 'A'+Math.floor((Math.random() * 10000) + 1);
                    //overlays_not_in_doc[index] = overlay;

                    var MAXITEMS = top.HAPI4.get_prefs('maxRecordsShowOnMap');
                    if(isNaN(MAXITEMS) || MAXITEMS<500) MAXITEMS=500;
                    MAXITEMS = 2000;
                    var sMsg = '';
                    if(mapdata.mapenabled > MAXITEMS){
                           sMsg = mapdata.mapenabled +' records to display on this map';
                    }
                    if(mapdata.timeenabled > MAXITEMS){
                           sMsg = (sMsg?' and ':'') + mapdata.timeenabled +' records to display on timeline';
                    }
                    if(sMsg!=''){

    sMsg = '<p style="padding-bottom:1em;">There are '+ sMsg +', which exceeds the limit of '+MAXITEMS+' records set in your profile.</p>'
    + '<p style="padding-bottom:1em;">Google Maps and VIS Timeline do not work well with thousands of objects, and may hang your browser or cause it to report the page as non-responsive. Only the first '+MAXITEMS+' records will therefore be displayed.</p>'
    + '<p>We recommend refining your filters to retrieve fewer records before mapping. You may also change the record limits for map and timeline in Profile &gt; My Preferences.</p>';


                            overlay.title = overlay.title + ' <span style="font-weight:bold;color:red">(Partial)</span>'
                    }



                    /*if(!overlays_not_in_doc[source.id])
                    {
                        overlays_not_in_doc[source.id] = overlay;
                        $("#legend .content").find('#'+source.id).remove();
                    }*/

                    overlays_not_in_doc[source.id] = overlay;
                    var legenditem = $("#legend .content").find('#'+source.id);
                    if(legenditem.length>0) legenditem.remove();

                    if(sMsg!=''){
                       setTimeout(function(){top.HEURIST4.msg.showMsgErr(sMsg);}, 1000);
                    }

                    //top.HAPI4.iconBaseURL + RT_MAPABLE_QUERY +'.png'
                    _addLegendEntryForLayer(source.id, source.title, mapdata.color, true );
               }
               
               
               _initLegendListeners();

            }else{  //dataset is empty or failed to add - remove from legend
               if(index<0){
                    _removeOverlayById( source.id );
               }
            }
}

function _removeOverlayById( overlay_id ){

    var ismapdoc = (overlay_id>0);
    var overlay = ismapdoc ?overlays[overlay_id] :overlays_not_in_doc[overlay_id];
    if(!top.HEURIST4.util.isnull(overlay)){
        try {
            $("#legend .content").find('#'+((ismapdoc)?'md-':'')+overlay_id).remove();

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
                var new_color = $dlg_edit_layer.find("#layer_color").css('background-color');
                var mapdata = edit_mapdata;

                if(top.HEURIST4.util.isnull(mapdata) && !top.HEURIST4.util.isnull(top.HAPI4.currentRecordset)){  //add current record set
                        
                        /* load with current result set and new rules
                        _currentRequest??
                        var request = { q: 'ids:'+ top.HAPI4.currentRecordset.getMainSet().join(','),
                                rules: that._currentRequest.rules,
                                w: 'all'};

                        //add new layer with given name
                        var source = {id:"dhs"+Math.floor((Math.random() * 10000) + 1),
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
                        var source = {id:"dhs"+Math.floor((Math.random() * 10000) + 1),
                             title: new_title,
                             recordset:  top.HAPI4.currentRecordset,
                             color: new_color
                        };                 
                        _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);
                
                }else if(mapdata.id=='main'){ 
                        
                        mapdata.id = "dhs"+Math.floor((Math.random() * 10000) + 1); //random id 
                        mapdata.title = new_title;
                        //change color scheme if required
                        mapping.changeDatasetColor( 'main', new_color, false );
                        //rename dataset for timeline items
                        for (var i=0; i<mapdata.timeline.items.length; i++){
                            mapdata.timeline.items[i].id = mapdata.id + '-' +  mapdata.timeline.items[i].recID;
                            mapdata.timeline.items[i].group = mapdata.id 
                        }
                        
                        //remove old 
                        _removeOverlayById( 'main' );
                        //add new    
                        _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);
                }else{
                    
                    if(mapdata.title!=new_title){
                        mapdata.title = new_title;
                        $("#legend .content").find('#'+mapdata.id+' label').html(new_title);
                        //$('#timeline > div > div.vis-panel.vis-left > div.vis-content > div > div:nth-child(2) > div
                        $('#timeline div[data-groupid="'+mapdata.id+'"]').html(new_title);
                    }
                    if(mapdata.color!=new_color){
                        $("#legend .content").find('#'+mapdata.id+'>div').css('border-color',new_color);
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
                $dlg_edit_layer.find("#layer_color").val(mapdata.color);
            }else{
                $dlg_edit_layer.find("#layer_name").val('').removeClass( "ui-state-error" );
            }
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
                
               $dlg_edit_layer.find("#layer_color").colorPicker({
                   //color:'#f00',
                   opacity: false,
                   //valueRanges: {rgb: {r: [0, 255], g: [0, 255], b: [0, 255]} },
                   cssAddon:'.cp-color-picker{z-index:999999999999 !important;background-color:#fff;border-radius: 0px;}',
                   renderCallback: function($elm, toggled){
                        if($elm && !toggled){
                            $(this.$UI).hide();
                        }
                   }                   
               }); 
               
       }       
       $dlg_edit_layer.dialog("open");    
    
}

// Data types
//  @TODO - get it from magic number constants defined on server side
//
var map_image_file_tiled = 17; //11;
var map_image_file_untiled = 1018;
var kml_file = 21; //1014;
var shape_file = 1017;
var RT_MAPABLE_QUERY = 24; //1021;

// delirium - to remove

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
    var span = document.createElement('span');
    span.innerHTML = "Title";
    div.appendChild(span);

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



    /**
    * Initialization
    */
    function _init(_mapping, startup_mapdocument_id) {

        mapping = _mapping;
        map = _mapping.getNativeMap();
        map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(document.getElementById('legend'));
        // Legend collapse listener
        $("#collapse").click(function(e) {
            $(this).text() == "-" ? $(this).text("+") : $(this).text("-");  // Update text to + or -
            $("#legend .content").toggle(400);
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
