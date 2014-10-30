HeuristOverlay.prototype = new google.maps.OverlayView();
var map;
var data;
var overlays = [];

/**
* Adds a map overlay to the given map.
* Performs an API call which contains data, which will be drawed
*/
function addMapOverlay(_map) {
    map = _map;
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(document.getElementById('legend'));

    // Collapsing legend
    $("#collapse").click(function(e) {
        var text = $(this).text();
        if(text == "-") {
            $(this).text("+");
        }else{
            $(this).text("-");
        }
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
        var doc = data[i];
        $("#map-doc-select").append("<option value='"+i+"'>["+doc.id+"] "+doc.title+"</option>");
    } 
    
    // Option listener
    $("#map-doc-select").change(function(e) {
        // Clean old data
        removeOverlays();
        emptyLegend();  
        
        // Show overlays for the selected option 
        var index = $(this).prop("value");
        if(index >= 0 && index < data.length) {
            // Selected document
            var doc = data[index];
            addOverlays(doc);  
            
            // Listen to checkbox changes
            $("#legend input").change(function(e) {
                // Hide or display the layer
                var index = $(this).prop("value");
                var checked = $(this).prop("checked");
                console.log("Checked: " + checked);
                if(checked) {
                    overlays[index].setMap(map); 
                }else{
                    overlays[index].setMap(null);
                }
            });
        }
    });   
}

/**
* Removes all overlays 
*/
function removeOverlays() {
    if(overlays.length > 0) {
        for(var i = 0; i < overlays.length; i++) {
            overlays[i].setMap(null);
            overlays[i] = null;
        }
    }
    overlays = [];
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
    var overlay = new HeuristOverlay(bounds, doc.thumbnail, map);
    overlays.push(overlay);
}

/**
* Adds an overlay for the Layer object
* @param layer Layer object
*/
function addLayerOverlay(bounds, layer, index) {
    console.log("addLayerOverlay");
    console.log(layer);
        
    if(layer !== undefined) {
        // Legend
        $("#legend .content").append("<label style='display:block;'><input type='checkbox' style='margin-right:5px' value='"+index+"' checked>["+layer.id+"] "+layer.title+"</label>");
    
        // Map
        var overlay = new HeuristOverlay(bounds, layer.thumbnail, map);
        overlays.push(overlay);
        
        /*
        layer.opacity;
        layer.minZoom;
        layer.maxZoom;
        */   
    }
}




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