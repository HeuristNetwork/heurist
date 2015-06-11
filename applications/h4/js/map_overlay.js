//ARTEM:   @todo JJ calls server side directly - need to fix - use hapi!!!!!
// move all these methods into hMapping class or create new one
// rename legend to map_legend

HeuristOverlay.prototype = new google.maps.OverlayView();
var map;
var data;
var overlays = {};
var overlays_not_in_doc = {};

/**
* Adds a map overlay to the given map.
* Performs an API call which contains data, which will be drawed upon selection
*/
function loadMapDocuments(_map, _startup_mapdocument) {
    map = _map;
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(document.getElementById('legend'));

    // Legend collapse listener
    $("#collapse").click(function(e) {
        $(this).text() == "-" ? $(this).text("+") : $(this).text("-");  // Update text to + or -
        $("#legend .content").toggle(400);   
    });
    
    // Load Map Documents & Map Layers
    var api = top.HAPI4.basePath + "php/api/map_data.php?db=" + top.HAPI4.database; // window.location.search;
//console.log("API call URL: " + api);
    $.getJSON(api, function(_data) {
        data = _data;
//console.log("DATA");
//console.log(data);

        // Have any map documents been defined?
        if(data.length > 0) {
            fillMapDocumentsDropDown();
        }
        if(_startup_mapdocument > 0){
            _loadMapDocumentById(_startup_mapdocument);
        }
    }).fail(function( jqxhr, textStatus, error ) {
        var msg = "Map Document API call failed: " + textStatus + ", " + error;
        console.log(msg);
        alert(msg);
    });
}

/**
* Adds options to the dropdown
*  assign listener for dropdown
*/
function fillMapDocumentsDropDown() {
    // Show options in dropdown
    for(var i = 0; i < data.length; i++) {
        $("#map-doc-select").append("<option value='"+data[i].id+"'>["+data[i].id+"] "+data[i].title+"</option>");
    } 
    
    // Option listener
    $("#map-doc-select").change(function(e) {
        // Clean old data
        removeOverlays();
        _emptyLegend();  
        
        // Show overlays for the selected option 
        var index = -1;
        var map_document_id = Number($(this).val()); //prop("value");
        for(var i=0;i<data.length;i++){
            if(map_document_id==data[i].id){
                index = i;
                break;
            }
        }
        
        
        if(index >= 0 && index < data.length) {
            // Show overlay for selected Map Document
            loadMapDocument(data[index]);  
            
            $("#legend").show();
            
            _initLegend();
            
            $("#btnMapEdit").button( "enable" );
            $("#btnMapEdit").attr('title',"Edit current map "+data[index].title+" - add or remove map layers, change settings");
        }else{
            $("#btnMapEdit").button( "disable" );
            $("#btnMapEdit").attr('title','');
            $("#legend").hide();
        }
    });   
}

/**
* Removes all overlays 
*/
function removeOverlays() {
    for(var property in overlays) {
        if (overlays.hasOwnProperty(property) && overlays[property] !== undefined) {
            try {
                overlays[property].setVisibility(false);   
                if(overlays[property]['removeOverlay']){
                    overlays[property].removeOverlay();
                }
            } catch(err) {
                console.log(err);
            }  
            overlays[property] = null;
        }
    }
    overlays = {};
}

/**
* Empties the legend
*/
function _emptyLegend() {
    $("#legend .content").empty();
    
    //add list of layers that are not in map document
    for(var idx in overlays_not_in_doc) {
        if (overlays_not_in_doc.hasOwnProperty(idx) && overlays_not_in_doc[idx] !== undefined) {
            $("#legend .content").append("<label style='display:block;'><input type='checkbox' style='margin-right:5px' value='"+idx+"' checked>"+overlays_not_in_doc[idx].title+"</label>");
        }
    }
}

function _initLegend() {
            // Listen to checkbox changes
            $("#legend input").change(function(e) {
                // Hide or display the layer
                var index = $(this).prop("value");
                var checked = $(this).prop("checked");
   
                // Update overlay
                var overlay = (index>=0) ?overlays[index] :overlays_not_in_doc[index];
                overlay.setVisibility(checked);                          
            });
}

function _loadMapDocumentById(recId) {
        var mapdocs = $("#map-doc-select");
        mapdocs.val(recId).change();
}


/**
* Adds overlays for a Map Document
* @param doc A map document
*/
function loadMapDocument(doc) {
    
    //show info popup
    if(!top.HEURIST4.util.isempty( doc['description']) ){
        top.HEURIST4.util.showMsgDlg(doc['description'], null,doc['title'], null, true);    
    }
    
    // Bounds
    var swBound = new google.maps.LatLng(doc.lat-doc.minorSpan, doc.long-doc.minorSpan);
    var neBound = new google.maps.LatLng(doc.lat+doc.minorSpan, doc.long+doc.minorSpan);
    var bounds = new google.maps.LatLngBounds(swBound, neBound); 

    // Map document overlay
    addMapDocumentOverlay(bounds, doc);
    map.fitBounds(bounds);
    
    // Map document layers
    var index = 0;
    if(doc.layers.length > 0) {
        for(var i = 0; i < doc.layers.length; i++) {
            addLayerOverlay(bounds, doc.layers[i], index);
            index++;
        }
    }
    
    // Top layer
    addLayerOverlay(bounds, doc.toplayer, index);
}

/**
* Adds an overlay for the Map Document object
* @param doc Map Document object
*/
function addMapDocumentOverlay(bounds, doc) {
    //var overlay = new HeuristOverlay(bounds, doc.thumbnail, map);
    //overlays.push(overlay);
}

/**
* Adds an overlay for the Layer object
* @param layer Layer object
*/
function addLayerOverlay(bounds, layer, index) {
    console.log("addLayerOverlay");
    console.log(layer);

    // Determine way of displaying   
    if(layer !== undefined && layer.dataSource !== undefined) {
        var source = layer.dataSource;
        console.log(source);
        
        // Append to legend  
        $("#legend .content").append("<label style='display:block;'><input type='checkbox' style='margin-right:5px' value='"+index+"' checked>["+layer.id+"] "+layer.title+"</label>");
        

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
        }else if(source.rectypeID == mappable_query) {
            console.log("MAPPABLE QUERY");
            addQueryLayer(source, index);
        }
    }   
}



/**
* Adds a tiled map image layer to the map
* @param source Source object
*/                                              
function addTiledMapImageLayer(source, bounds, index) {  
    var overlay = {};   
    
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

        // Set visibility
        overlay.setVisibility = function(checked) {
            console.log("Setting visibility to: " + checked);
            if(checked) {
                map.overlayMapTypes.setAt(index, tileType);    
            }else{
                map.overlayMapTypes.setAt(index, null);   
            }
        };
        
        overlay.removeOverlay = function(){
            map.overlayMapTypes.setAt(index, null);
        };
          
     }

    overlays[index] = overlay; 
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

        // Set visibility
        overlay.setVisibility = function(checked) {
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
    
    
    // Set visiblity method
    kmlLayer.setVisibility = function(checked) {
        if(checked) {
            kmlLayer.setMap(map); 
        }else{
            kmlLayer.setMap(null);
        }
    }
    
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
        if(checked) {
            features = map.data.addGeoJson(data.geojson);     
        }else{
            for (var i = 0; i < features.length; i++) {
                map.data.remove(features[i]);
            }
        }
    }
    
    overlays[index] = data;     
}



/**
* Adds a query layer to the map
* @param source Source object
*/
function addQueryLayer(source, index) {
     // Query
    if(source.query !== undefined) {
        console.log("Query: " + source.query);
        
        var query = null;
        try{
            var query = top.HEURIST4.util.isObject(source.query) ?source.query :JSON.parse(source.query);
            if(!(query && query['qa'])){
                query = null;
            }else{
                //query = {q: JSON.stringify(query['qa']), rules: JSON.stringify(query['rules']), w: "all", f:"map", l:2000};    
            }
        }catch(err){
        }
        if(query==null){
            query = {q: source.query, w: "all"};
        }
        query['rules_onserver'] = 1; 
        query['getrelrecs'] = 1;  //return all related records including relationship records
        query['f'] = "map"; 
        query['l'] = 3000;
        
        
        // Retrieve records for this query
        top.HAPI4.RecordMgr.search(query,
            function(response){
                //console.log("QUERY RESPONSE:");
                //console.log(response);
                
                if(response.status == top.HAPI4.ResponseStatus.OK){
                    // Show info on map
                    var recset = new hRecordSet(response.data);
                    
                    //preprocess for Digital Harlem 
                    // recset.preprocessForDigitalHarlem();
                    
                    //convert to map datasource
                    var mapdata = recset.toTimemap("dyn"+source.id);
                    
                    //mapping.load(mapdata);
                    if (mapping.addDataset(mapdata)){
        
                       var overlay = {
                        id: source.id,
                        title: source['title'],                
                        setVisibility: function(checked) {
                            mapping.showDataset(mapdata[0].id, checked);
                        },                          
                        removeOverlay: function(){
                            mapping.deleteDataset(mapdata[0].id);
                        }
                       };
                       if(index>=0){
                            overlays[index] = overlay;
                       }else{
                            overlays_not_in_doc[index] = overlay;
                            $("#legend .content").append("<label style='display:block;'><input type='checkbox' style='margin-right:5px' value='"+index+"' checked>"+overlay.title+"</label>");
                            _initLegend();
                       }
                    }
                    //console.log(recset);
                    //mapping.load();

                }else{
                    alert(response.message);
                }
            }
        ); 
        
        
    }
}









/** Data types */
var map_image_file_tiled = 17; //11;
var map_image_file_untiled = 1018;
var kml_file = 21; //1014;
var shape_file = 1017;
var mappable_query = 24; //1021;

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