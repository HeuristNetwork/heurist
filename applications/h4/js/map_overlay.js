HeuristOverlay.prototype = new google.maps.OverlayView();
var map;
var data;
var overlays = {};

/**
* Adds a map overlay to the given map.
* Performs an API call which contains data, which will be drawed upon selection
*/
function addMapOverlay(_map) {
    map = _map;
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(document.getElementById('legend'));

    // Legend collapse listener
    $("#collapse").click(function(e) {
        $(this).text() == "-" ? $(this).text("+") : $(this).text("-");  // Update text to + or -
        $("#legend .content").toggle(400);   
    });
    
    // Load Map Documents & Map Layers
    var api = "../php/api/map_data.php" + window.location.search;
    console.log("API call URL: " + api);
    $.getJSON(api, function(_data) {
        data = _data;
        console.log("DATA");
        console.log(data);

        // Have any map documents been defined?
        if(data.length > 0) {
            addOptions();
        }
    }).fail(function( jqxhr, textStatus, error ) {
        var msg = "Map Document API call failed: " + textStatus + ", " + error;
        console.log(msg);
        alert(msg);
    });
}

/**
* Adds options to the dropdown
* 
*/
function addOptions() {
    // Show options in dropdown
    for(var i = 0; i < data.length; i++) {
        $("#map-doc-select").append("<option value='"+i+"'>["+data[i].id+"] "+data[i].title+"</option>");
    } 
    
    // Option listener
    $("#map-doc-select").change(function(e) {
        // Clean old data
        removeOverlays();
        emptyLegend();  
        
        // Show overlays for the selected option 
        var index = $(this).prop("value");
        if(index >= 0 && index < data.length) {
            // Show overlay for selected Map Document
            addOverlays(data[index]);  
            
            // Listen to checkbox changes
            $("#legend input").change(function(e) {
                // Hide or display the layer
                var index = $(this).prop("value");
                var checked = $(this).prop("checked");
   
                // Update overlay
                var overlay = overlays[index];
                overlay.setVisibility(checked);                          
            });      
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
function emptyLegend() {
    $("#legend .content").empty();
}

/**
* Adds overlays for a Map Document
* @param doc A map document
*/
function addOverlays(doc) {
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
        }  
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
        
        // Retrieve records for this query
        top.HAPI4.RecordMgr.search({q: source.query, w: "all", f:"map", l:200},
            function(response){
                console.log("QUERY RESPONSE:");
                console.log(response);
                
                if(response.status == top.HAPI4.ResponseStatus.OK){
                    // Show info on map
                    var recset = new hRecordSet(response.data);
                    console.log(recset);
                    //mapping.load(recset.toTimemap());

                }else{
                    alert(response.message);
                }
            }
        ); 
        
        
    }
}









/** Data types */
var map_image_file_tiled = 11;
var map_image_file_untiled = 1018;
var kml_file = 1014;
var shape_file = 1017;
var mappable_query = 1021;

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