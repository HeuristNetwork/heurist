/**
* Class to work with OS Timemap.js and Vis timeline. 
* It allows initialization of mapping and timeline controls and fills data for these controls
* 
* @param _map - id of map element container 
* @param _timeline - id of timeline element
* @param _basePath - need to specify path to timemap assets (images)
* #param mylayout - layout object that contains map and timeline
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


function hMappingDraw(_mapdiv_id) {
    var _className = "MappingDraw",
    _version   = "0.4";

    var mapdiv_id = null;
    
      var drawingManager;
      var selectedShape;
      var colors = ['#1E90FF', '#FF1493', '#32CD32', '#FF8C00', '#4B0082'];
      var selectedColor;
      var colorButtons = {};
      var gmap;
      var overlays = []; //all objects on map
      var geocoder = null;
      var map_viewpoints = []; //saved in user preferences (session) map viewpoints (bounds)

      function clearSelection() {
        if (selectedShape) {
          if(selectedShape.type != google.maps.drawing.OverlayType.MARKER) selectedShape.setEditable(false);
          selectedShape = null;
        }
      }

      function setSelection(shape) {
        clearSelection();
        selectedShape = shape;
        if(shape.type != google.maps.drawing.OverlayType.MARKER) shape.setEditable(true);
        selectColor(shape.get('fillColor') || shape.get('strokeColor'));
      }

      function _deleteSelectedShape() {
        if (selectedShape) {
          for(i in overlays){
              if(overlays[i]==selectedShape){
                    overlays.splice(i,1);
                    break;
              }
          }  
            
            
          selectedShape.setMap(null);
          selectedShape = null;
        }
      }

      function _deleteAllShapes() {
          while(overlays[0]){
            overlays.pop().setMap(null);
          }          
      }
      
      //start color
      function selectColor(color) {
        selectedColor = color;
        for (var i = 0; i < colors.length; ++i) {
          var currColor = colors[i];
          colorButtons[currColor].style.border = currColor == color ? '2px solid #789' : '2px solid #fff';
        }

        // Retrieves the current options from the drawing manager and replaces the
        // stroke or fill color as appropriate.
        var polylineOptions = drawingManager.get('polylineOptions');
        polylineOptions.strokeColor = color;
        drawingManager.set('polylineOptions', polylineOptions);

        var rectangleOptions = drawingManager.get('rectangleOptions');
        rectangleOptions.fillColor = color;
        drawingManager.set('rectangleOptions', rectangleOptions);

        var circleOptions = drawingManager.get('circleOptions');
        circleOptions.fillColor = color;
        drawingManager.set('circleOptions', circleOptions);

        var polygonOptions = drawingManager.get('polygonOptions');
        polygonOptions.fillColor = color;
        drawingManager.set('polygonOptions', polygonOptions);
      }

      //start color functions
      function setSelectedShapeColor(color) {
        if (selectedShape) {
          if (selectedShape.type == google.maps.drawing.OverlayType.POLYLINE) {
            selectedShape.set('strokeColor', color);
          } else {
            selectedShape.set('fillColor', color);
          }
        }
      }

      function makeColorButton(color) {
        var button = document.createElement('span');
        button.className = 'color-button';
        button.style.backgroundColor = color;
        google.maps.event.addDomListener(button, 'click', function() {
          selectColor(color);
          setSelectedShapeColor(color);
        });

        return button;
      }

       function buildColorPalette() {
         var colorPalette = document.getElementById('color-palette');
         for (var i = 0; i < colors.length; ++i) {
           var currColor = colors[i];
           var colorButton = makeColorButton(currColor);
           colorPalette.appendChild(colorButton);
           colorButtons[currColor] = colorButton;
         }
         selectColor(colors[0]);
       }
       //end color
       

    function _init(_mapdiv_id) {
        mapdiv_id = _mapdiv_id;

        map_viewpoints = top.HAPI4.get_prefs('map_viewpoints');
        
        var map = new google.maps.Map(document.getElementById(mapdiv_id), {
          zoom: 2,
          center: new google.maps.LatLng(0,0), //22.344, 114.048),
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          disableDefaultUI: true,
          zoomControl: true
        });
        
        gmap = map;

        var polyOptions = {
          strokeWeight: 0,
          fillOpacity: 0.45,
          editable: true
        };
        // Creates a drawing manager attached to the map that allows the user to draw
        // markers, lines, and shapes.
        drawingManager = new google.maps.drawing.DrawingManager({
          drawingMode: google.maps.drawing.OverlayType.POLYGON,
          markerOptions: {
            draggable: true
          },
          polylineOptions: {
            editable: true
          },
          rectangleOptions: polyOptions,
          circleOptions: polyOptions,
          polygonOptions: polyOptions,
          map: map
        });
        
        var deleteMenu = new DeleteMenu();
                
        //for plygon and polyline
        function _onPathComplete(shape) {

          // complete functions
          var thePath = shape.getPath();
           
          google.maps.event.addListener(shape, 'rightclick', function(e) {
            // Check if click was on a vertex control point
            if (e.vertex == undefined) {
              return;
            }
            deleteMenu.open(map, shape.getPath(), e.vertex);
          });           

          google.maps.event.addListener(thePath, 'set_at', function() {
            // complete functions
            $('#coords1').html(thePath.getArray().join(' '));  
          });

          google.maps.event.addListener(thePath, 'insert_at', function() {
            // complete functions
            $('#coords1').html(thePath.getArray().join(' '));  
          });
          
          google.maps.event.addListener(thePath, 'remove_at', function() {
            // complete functions
            $('#coords1').html(thePath.getArray().join(' '));  
          });

        }
        
        google.maps.event.addListener(drawingManager,'polygoncomplete',_onPathComplete);
        google.maps.event.addListener(drawingManager,'polylinecomplete',_onPathComplete);
        
        google.maps.event.addListener(drawingManager, 'circlecomplete', function (circle) {
            google.maps.event.addListener(circle, 'radius_changed', function () {
                console.log('radius changed');
            });
            google.maps.event.addListener(circle, 'center_changed', function () {
                console.log('radius changed');
            });
        });        
        

        google.maps.event.addListener(drawingManager, 'overlaycomplete', function(e) {
            var newShape = e.overlay;
            newShape.type = e.type;
            
            overlays.push(newShape);
                
          if (e.type != google.maps.drawing.OverlayType.MARKER) {
              
                // Switch back to non-drawing mode after drawing a shape.
                drawingManager.setDrawingMode(null);

           
                // Add an event listener that selects the newly-drawn shape when the user
                // mouses down on it.
                google.maps.event.addListener(newShape, 'click', function() {
                  setSelection(newShape);
                });
                setSelection(newShape);
          }
                
          if (e.type == google.maps.drawing.OverlayType.RECTANGLE) {

              
                var bnd = newShape.getBounds();
                
                //bnd.getNorthEast();
                //bnd.getSouthWest();
              
                $('#coords1').html(              
                    bnd.toUrlValue()
                );
              
          }else if (e.type == google.maps.drawing.OverlayType.CIRCLE) {
              
              
          }else if (e.type == google.maps.drawing.OverlayType.MARKER) {

                setSelection(newShape);
                $('#coords1').html(              
                newShape.getPosition().lng()+' '+newShape.getPosition().lat()
                );
                
                
              google.maps.event.addListener(newShape, 'rightclick', function(e) {
                deleteMenu.open(map, newShape, newShape.getPosition());
              });                     
          }else{
                //fill coordinatedes
                $('#coords1').html(newShape.getPath().getArray().join(' '));  
          }
            
          
          
        });

        // Clear the current selection when the drawing mode is changed, or when the
        // map is clicked.
        google.maps.event.addListener(drawingManager, 'drawingmode_changed', clearSelection); //clearSelection);
        google.maps.event.addListener(map, 'click', clearSelection);
        google.maps.event.addListener(map, 'mousemove', function (event) {
                    var pnt = event.latLng;
                    var lat = pnt.lat();
                    lat = lat.toFixed(4);
                    var lng = pnt.lng();
                    lng = lng.toFixed(4);
                    //console.log("Latitude: " + lat + "  Longitude: " + lng);
                    $('#coords2').html(lat + " " + lng);               
          });        
        
        _initUIcontrols();

        buildColorPalette();
    } 
    
    
    //
    //
    //
    function _startGeocodingSearch(){
    
        if(geocoder==null){
            geocoder = new google.maps.Geocoder();
        }
        
        var address = document.getElementById("input_search").value;
        
        geocoder.geocode( { 'address': address}, function(results, status) {
            
          if (status == google.maps.GeocoderStatus.OK) {
              
            if(results[0].geometry.viewport){
                gmap.fitBounds(results[0].geometry.viewport);
            }else{
                gmap.setCenter(results[0].geometry.location);
            }  
            /*var marker = new google.maps.Marker({
                map: map,
                position: results[0].geometry.location
            });*/
          } else {
            alert(top.HR("Geocode was not successful for the following reason: ") + status);
          }
        });        
    }
    
    //
    // init UI controls
    //
    function _initUIcontrols(){
                
        $('#btn_search_start')
          .button({label: top.HR("Start search"), text:false, icons: {
                      secondary: "ui-icon-search"
          }})
          .click(_startGeocodingSearch);
          
        $('#input_search')
                    .on('keypress',
                    function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                            if (code == 13) {
                                top.HEURIST4.util.stopEvent(e);
                                e.preventDefault();
                                 _startGeocodingSearch();
                            }
                    });          
        
        $('#delete-button').button().click(_deleteSelectedShape);
        $('#delete-all-button').button().click(_deleteAllShapes);
        
        //get save bounds (viewpoints)
        //map_viewpoints = top.HAPI4.get_prefs('map_viewpoints');
        
        var $sel_viepoints = $('#sel_viewpoints');
        
        //fill sel_viewpoints with bounds
        top.HEURIST4.util.createSelector( $sel_viepoints.get(0), 
            $.isEmptyObject(map_viewpoints)?top.HR('none defined'): map_viewpoints);
            
        $sel_viepoints.change(function(){
           var bounds = $(this).val();
           if(bounds!=''){
               //get LatLngBounds from urlvalue lat_lo,lng_lo,lat_hi,lng_hi
               bounds = bounds.split(',');
               gmap.fitBounds({south:Number(bounds[0]), west:Number(bounds[1]),
                                north:Number(bounds[2]), east:Number(bounds[3]) });
                               
               //gmap.fitBounds(new LatLngBounds(new LatLng(Number(bounds[1]), Number(bounds[2]))
               //    , new LatLng(Number(bounds[3]), Number(bounds[0])) );
           }
        });
        
        $('#btn_viewpoint_delete')
          .button({label: top.HR("Delete selected location"), text:false, icons: {
                      secondary: "ui-icon-close"
          }})
          .click(function(){
              var selval = $sel_viepoints.val();
              if(selval!=''){
                 // remove from preferences
                 $.each(map_viewpoints, function(index, item){
                     if(item['key']==selval){
                          map_viewpoints.splice(index,1);
                          return false;
                     }
                 });
                 top.HAPI4.SystemMgr.save_prefs({'map_viewpoints': map_viewpoints});
                  
                 // remove from selector
                 $sel_viepoints.find('option:selected').remove(); 
                 if($.isEmptyObject(map_viewpoints)){
                    top.HEURIST4.util.addoption( $sel_viepoints.get(0), 
                                '', top.HR('none defined'));
                 }
              }
          });

        $('#btn_viewpoint_save')
          .button({label: top.HR("Save location")})
          .click(function(){
              top.HEURIST4.msg.showPrompt('Name of location', function(location_name){
                  if(!top.HEURIST4.util.isempty(location_name)){
                      //save into preferences 
                      if($.isEmptyObject(map_viewpoints)){
                            map_viewpoints=[];   
                            $sel_viepoints.empty();
                      }
                      map_viewpoints.push({key:gmap.getBounds().toUrlValue(), title:location_name});
                      top.HAPI4.SystemMgr.save_prefs({'map_viewpoints': map_viewpoints});
                      // and add to selector
                      top.HEURIST4.util.addoption( $sel_viepoints.get(0), 
                                gmap.getBounds().toUrlValue(), location_name);
                                
                  }
              });
          });
          
        if(!$.isEmptyObject(map_viewpoints)){
            $sel_viepoints.find('option:last-child').attr('selected', 'selected');
            $sel_viepoints.change();
        }     
    }     
      
      
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init(_mapdiv_id);
    return that;  //returns object
}

/**
 * A menu that lets a user delete a selected vertex of a path.
 * @constructor
 */
function DeleteMenu() {
  this.div_ = document.createElement('div');
  this.div_.className = 'delete-menu';
  this.div_.innerHTML = 'Delete';

  var menu = this;
  google.maps.event.addDomListener(this.div_, 'click', function() {
    menu.removeVertex();
  });
}
DeleteMenu.prototype = new google.maps.OverlayView();

DeleteMenu.prototype.onAdd = function() {
  var deleteMenu = this;
  var map = this.getMap();
  this.getPanes().floatPane.appendChild(this.div_);

  // mousedown anywhere on the map except on the menu div will close the
  // menu.
  this.divListener_ = google.maps.event.addDomListener(map.getDiv(), 'mousedown', function(e) {
    if (e.target != deleteMenu.div_) {
      deleteMenu.close();
    }
  }, true);
};

DeleteMenu.prototype.onRemove = function() {
  google.maps.event.removeListener(this.divListener_);
  this.div_.parentNode.removeChild(this.div_);

  // clean up
  this.set('position');
  this.set('path');
  this.set('vertex');
};

DeleteMenu.prototype.close = function() {
  this.setMap(null);
};

DeleteMenu.prototype.draw = function() {
  var position = this.get('position');
  var projection = this.getProjection();

  if (!position || !projection) {
    return;
  }

  var point = projection.fromLatLngToDivPixel(position);
  this.div_.style.top = point.y + 'px';
  this.div_.style.left = point.x + 'px';
};

/**
 * Opens the menu at a vertex of a given path.
 */
DeleteMenu.prototype.open = function(map, path, vertex) {
  if(path.type==google.maps.drawing.OverlayType.MARKER){
        this.set('position', vertex);
  }else{
        this.set('position', path.getAt(vertex));
  }
  this.set('path', path);
  this.set('vertex', vertex);
  this.setMap(map);
  this.draw();
};

/**
 * Deletes the vertex from the path.
 */
DeleteMenu.prototype.removeVertex = function() {
  var path = this.get('path');
  var vertex = this.get('vertex');

  if (!path || vertex == undefined) {
    this.close();
    return;
  }
  
  if(path.type==google.maps.drawing.OverlayType.MARKER){
        path.setMap(null);
  }else{
        path.removeAt(vertex);
  }
  this.close();
};