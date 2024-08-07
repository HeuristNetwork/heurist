/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

/* global google, _adjustLegendHeight, Shapefile */

//ARTEM:   @todo JJ calls server side directly - need to fix - use hapi!!!!!
//
// move all these methods into hMapping class or create new one


/**
*  This class responsible for all interaction with UI and map object 
*   mapping - parent container
*/
function hMappingControls( mapping, startup_mapdocument_id ) {
    const _className = "MappingControls",
    _version   = "0.4";

    let map; //google map
    let current_map_document_id = 0;
    let map_data;                  // all map documents/layer/dataset related info
    let overlays = {};             // layers in current map document
    let map_bookmarks = [];        // geo and time extents
    let overlays_not_in_doc = {};  // main layers(current query) and layers added by user/manually
    let loadingbar = null;         // progress bar - overlay on map 
    let $dlg_edit_layer = null;
    
    let loading_mapdoc_list = false;    // flag to prevent repeated request
    let menu_mapdocuments = null;

    /**
    * Performs an API call which contains data - all map documents/layer/dataset related info.
    * The list of map documents will be loaded into selector
    */
    function _loadMapDocuments(startup_mapdocument, onload_callback) {

        loading_mapdoc_list = true;
        
        if(menu_mapdocuments!=null) menu_mapdocuments.remove();
            
        if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'])){
            //@todo change label - hide button
            $('#map-doc-select-lbl').text('No map documents available');
            $('#mapSelectorBtn').hide();
            return;
        }
        
        // Load Map Documents & Map Layers       
        // @TODO - change it to HAPI method!!!!
        let request = { q: {"t":window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']},
            w: 'a',
            detail: 'header',
            source: 'mapSelectorBtn'};

            let btnMapRefresh = $("#btnMapRefresh");
            let btnMapEdit = $("#btnMapEdit");
            window.hWin.HEURIST4.util.setDisabled(btnMapEdit, true);
            window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, true);

        
            //perform search
            window.hWin.HAPI4.RecordMgr.search(request, function(response){

                loading_mapdoc_list = false;
                    
                if(response.status == window.hWin.ResponseStatus.OK){
                    let resdata = new HRecordSet(response.data);

                    let mapdocs = '';
                    //ele.append("<option value='-1'>"+(resdata.length()>0?'select...':'none available')+"</option>");
                    mapdocs = mapdocs + "<li mapdoc_id='-1'><a>"+(resdata.length()>0?'None':'none available')+"</a></li>";

                    let idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            let record = records[idx];
                            let recID  = resdata.fld(record, 'rec_ID'),
                                recName = resdata.fld(record, 'rec_Title');

                            //ele.append("<option value='"+recID+"'>"+recName+"</option>");
                            mapdocs = mapdocs + "<li mapdoc_id='"+recID+"'><a>"+recName+"</a></li>";
                        }
                    }//for
                    
                    
                    menu_mapdocuments = $('<ul>'+mapdocs+'</ul>')
                        .addClass('menu-or-popup')
                        .css('position','absolute')
                        .appendTo( $('body') )
                        .menu({
                            select: function( event, ui ) {
                                if(loading_mapdoc_list) return;
                                current_map_document_id = ui.item.attr('mapdoc_id');
                                $("#mapSelectorBtn").attr('mapdoc-selected',current_map_document_id).button({label: ui.item.find('a').text() });
                                _loadMapDocumentById(); //loads current_map_document_id
                                menu_mapdocuments.hide();
                        }});

                    menu_mapdocuments.hide();
                    
                    if(_isPublicSite()){
                        menu_mapdocuments.hide();
                    }else if($.isFunction(onload_callback)) {
                        onload_callback.call();
                    }
                    
                    let w = $(window).width();
                    if (w < 400) {
                        $("#mapSelectorBtn").button({showLabel:false}).width(20);
                    }else{
                        $("#mapSelectorBtn").button({showLabel:true}).width(w-360);
                    }
                    
                    
                    if(startup_mapdocument>=0){
                        if(startup_mapdocument>0){
                            _loadMapDocumentById_init(startup_mapdocument);
                        }
                    }

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });

    }

    //
    // loads current_map_document_id
    //
    function _loadMapDocumentById() {

        menu_mapdocuments.hide();

        // Clean old data
        $('#map_extents').css('visibility','hidden');
        _removeMapDocumentOverlays();
        let selBookmarks = document.getElementById('selMapBookmarks');
        $(selBookmarks).empty();
        let btnMapRefresh = $("#btnMapRefresh");
        let btnMapEdit = $("#btnMapEdit");
        window.hWin.HEURIST4.util.setDisabled(btnMapEdit, true);
        window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, true);
        btnMapEdit.attr('title','');

        if(current_map_document_id>0)
        {

            let baseurl = window.hWin.HAPI4.baseURL + 'hserv/controller/map_data.php';
            let request = {db:window.hWin.HAPI4.database, id:current_map_document_id};
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response) {
                
                if(response.status == window.hWin.ResponseStatus.OK){
                
                    map_data = response.data;
                    // define bookmark by default
                    if(map_data && map_data.length > 0) {
                        if(map_data[0].bookmarks==null){
                            map_data[0].bookmarks = [];
                        }
                        map_data[0].bookmarks.push([window.hWin.HR('World'),-80,90,-30,50,1800,2050]); //default
                        _loadMapDocumentById_continue();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(
                        'Map document (ID '
                        + current_map_document_id
                        + ') does not exist in the database. '
                        + 'Please email the database owner ('
                        +window.hWin.HAPI4.sysinfo['dbowner_email']+') and ask them to correct the URL');
                    }
                
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
            });

        }

    }

    //
    //
    //
    function _getMapDocumentDataById(mapdocument_id) {    
        
        if(typeof mapdocument_id==='undefined'){
              mapdocument_id = current_map_document_id; 
        }
        
        //find mapdoc data
        if(mapdocument_id>0 && map_data)
            for(let i=0;i<map_data.length;i++){
                if(mapdocument_id==map_data[i].id){
                    return map_data[i];
                }
            }
        return null;
        
    }

    //
    // loads current_map_document_id
    //
    function _loadMapDocumentById_continue() {    

        let mapdocument_id =  current_map_document_id;

        //find mapdoc data
        let doc = _getMapDocumentDataById(mapdocument_id);
        let lt = window.hWin.HAPI4.sysinfo['layout'];   

        let selBookmarks = document.getElementById('selMapBookmarks');
        let btnMapRefresh = $("#btnMapRefresh");
        let btnMapEdit = $("#btnMapEdit");
        if( !window.hWin.HEURIST4.util.isnull(doc) ) {
            
            let bounds = null, err_msg_all = '';

            map_bookmarks = [];
            window.hWin.HEURIST4.ui.addoption(selBookmarks, -1, 'bookmarks...');

            // Longitude,Latitude centrepoint, Initial minor span
            // add initial bookmarks based on long lat  minorSpan
            let cp_long = _validateCoord(doc.long,false);
            let cp_lat = _validateCoord(doc.lat,true);
            if(!isNaN(cp_long) && !isNaN(cp_lat) && doc.minorSpan>0){
                
                let body = $(this.document).find('body');
                let prop = body.innerWidth()/body.innerHeight();

                if(isNaN(prop) || prop==0) prop = 1;
                
                let span_x,span_y;
                if(body.innerWidth()<body.innerHeight()){
                    span_x = doc.minorSpan;
                    span_y = doc.minorSpan/prop;
                }else{
                    span_x = doc.minorSpan*prop;
                    span_y = doc.minorSpan;
                }
                let init_extent = ['Initial extent', cp_long-span_x/2,cp_long+span_x/2, cp_lat-span_y/2,cp_lat+span_y/2 ];
                doc.bookmarks.unshift(init_extent);
            }
            

            for(let i=0;i<doc.bookmarks.length;i++){

                let bookmark = doc.bookmarks[i];
                let err_msg = '';

                let x1,x2,y1,y2,tmin,tmax;

                if(bookmark.length<5){
                    err_msg = 'not enough parameters';
                }else{

                    x1 = _validateCoord(bookmark[1],false); 
                    if(isNaN(x1)) err_msg = err_msg + ' bad value for xmin:' + x1;
                    x2 = _validateCoord(bookmark[2],false);
                    if(isNaN(x2)) err_msg = err_msg + ' bad value for xmax:' + x2;
                    y1 = _validateCoord(bookmark[3],true);
                    if(isNaN(y1)) err_msg = err_msg + ' bad value for ymin:' + y1;
                    y2 = _validateCoord(bookmark[4],true);
                    if(isNaN(y2)) err_msg = err_msg + ' bad value for ymax:' + y2;

                    tmin = null;
                    tmax = null;
                    let dres = null;

                    if(err_msg==''){

                        if(x1>x2 || y1>y2){
                            err_msg = err_msg + ' coordinates are inverted';    
                        }else{
                            if(bookmark.length>6){
                                dres = window.hWin.HEURIST4.util.parseDates(bookmark[5], bookmark[6]);
                            }
                            if(dres!==null){
                                tmin = dres[0];
                                tmax = dres[1];
                            }
                        }
                    }
                }

                if(err_msg!=''){
                    err_msg_all = err_msg_all + '<br>Map bookmark: "' + bookmark.join(',')+'" : '+err_msg;
                    continue;
                }

                let swBound = new google.maps.LatLng(y1, x1);
                let neBound = new google.maps.LatLng(y2, x2);
                bounds = new google.maps.LatLngBounds(swBound, neBound);

                window.hWin.HEURIST4.ui.addoption(selBookmarks, map_bookmarks.length, bookmark[0]?bookmark[0]:'Extent '+(map_bookmarks.length+1));

                map_bookmarks.push({extent:bounds, tmin:tmin, tmax:tmax});

                if(i==doc.bookmarks.length-2 && map_bookmarks.length>0){
                    break; //skip last default bookmark if user define its own extents
                }
            }//for map bookmarks
            if(err_msg_all!=''){
                window.hWin.HEURIST4.msg.showMsgErr('<div>Map-zoom bookmark is not interpretable, set to Label,xmin,xmax,ymin,ymax,tmin,tmax (tmin,tmax are optional)</div>'
                +'<br>eg. Harlem, -74.000000,-73.900000,40.764134,40.864134,1915,1930<br> '
                    +err_msg_all
                    +'<br><br><div>Please edit the map document (button next to map name dropdown above) and correct the contents of the map-zoom bookmark following the instructions in the field help.</div>'
                );
            }



            let selBookmarks = document.getElementById('selMapBookmarks')                  
            selBookmarks.onmousedown = function(){ selBookmarks.selectedIndex = 0; }
            selBookmarks.onchange = function(){
                let val = $(selBookmarks).val();
                if(val>=0){
                    map.fitBounds(map_bookmarks[val]['extent']);    
                    if(map_bookmarks[val]['tmin']!=null)
                        mapping.timelineZoomToRange(map_bookmarks[val]['tmin'],map_bookmarks[val]['tmax']);
                }
            }
            
            $('#map_extents').css('visibility','visible');
            selBookmarks.selectedIndex = 1;
            $(selBookmarks).change();

            mapping.setTimeMapProperty('centerOnItems', false);    
            
            let dataset_id = 0;
            
            // Map document layers
            let overlay_index = 1;
            if(doc.layers.length > 0) {
                for(let i = 0; i < doc.layers.length; i++) {
                    if(doc.layers[i].name) doc.layers[i].title = doc.layers[i].name; //use name istead of rec_Title
                    
                    if(doc.layers[i].iconMarker && doc.layers[i].iconMarker.indexOf('http')!==0){
                        doc.layers[i].iconMarker = window.hWin.HAPI4.baseURL + '?db=' 
                                + window.hWin.HAPI4.database + '&file=' + doc.layers[i].iconMarker;
                    }
                    
                    _addLayerOverlay(bounds, doc.layers[i], overlay_index, true);
                    overlay_index++;
                    //dataset_id = doc.layers[i].dataSource.id
                }
            }


            let map_container = mapping.getMapContainerDiv();
            if(!map_container.is(':visible')){
                let checkVisible = setInterval(function(){

                    if(!map_container.is(':visible')) return;
                    clearInterval(checkVisible); //stop listener

                    $(selBookmarks).change();
                    //mapping.autoCenterAndZoom();
                    //mapping.zoomDataset()
                    //zoom to map document extent
                    
                },1000);                
            }
            
            
            // Top layer - artem: JJ made it wrong
            //_addLayerOverlay(bounds, doc.toplayer, index);

            _initLegendListeners();

            window.hWin.HEURIST4.util.setDisabled(btnMapEdit, false);
            window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, false);
            btnMapEdit.attr('title',"Edit current map "+doc.title+" - add or remove map layers, change settings");
            
            //restore auto center on dataset addition, loadItems
            //@todo - restore after all datasets are added
            setTimeout(function(){
                mapping.setTimeMapProperty('centerOnItems', true);    
            }, 5000);
            
            
        }else{
            window.hWin.HEURIST4.util.setDisabled(btnMapEdit, true);
            window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, true);
            btnMapEdit.attr('title','');
        }

    }

    //
    //
    function _validateCoord(value, islat){
        let crd = Number(value);
        if(isNaN(crd)){
            return value+" is not a numeric value.";
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

        let legend_content = $("#map_legend .content");

        for(let idx in overlays) {
            _removeOverlayById(idx);
        }
        overlays = {};
        
        _adjustLegendHeight();
    }

    //
    // dependent_layers -  dependent map layers  [{key :title}]
    //
    function _addLegendEntryForLayer(overlay_idx, layer_options, dependent_layers, ontop){

        let overlay = null,
        legendid,
        ismapdoc = (overlay_idx>0);
        
        let icon_bg = null;
        
        let title = layer_options.title;
        let bg_color = layer_options.color;
        let rectypeID = layer_options.rectypeID;


        if (ismapdoc) {
            legendid = 'md-'+overlay_idx;
            overlay = overlays[overlay_idx];
            
            if(!overlay) return;

            if(rectypeID && rectypeID==RT_SHP_SOURCE && overlay.visible){
                icon_bg = 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif);'
                + 'background-position: center; background-repeat: no-repeat;"'            
                + ' data-icon="'+(layer_options.iconMarker
                        ?layer_options.iconMarker
                        :(window.hWin.HAPI4.iconBaseURL + rectypeID ))
                        +'"';
            } else if(layer_options.iconMarker){
                icon_bg = 'url('+ layer_options.iconMarker + ');background-size: 18px 18px;"';
            }
                        
        }else{
            legendid = overlay_idx;
            overlay = overlays_not_in_doc[overlay_idx];
            if(!overlay) return;
        }
        
        if(icon_bg==null && Number.isInteger(rectypeID)){
                let suffix = '.png';
                if(bg_color){
                    suffix = '&color='+encodeURIComponent(bg_color.replace(/ /g,''));            
                }
                icon_bg = 'url(\''+ window.hWin.HAPI4.iconBaseURL + rectypeID + suffix + '\');background-size: 18px 18px;"';
        }
        if(icon_bg!=null){
            icon_bg = ' style="background-image: '+icon_bg;
        }
        
        overlay.legendid = legendid;
        
        if(!overlay) return;

        let warning = '';
        if($.isPlainObject(title)){
            warning = title.warning;
            title = title.title;
        }


        let legenditem = '<div style="display:block;padding:2px;" id="'
        + legendid+'"><input type="checkbox" style="margin-right:5px" value="'
        + overlay_idx+'" id="chbox-'+legendid+'" class="overlay-legend" '
        + (overlay.visible?'checked="checked">':'>')
        + '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif" id="loading-'+overlay_idx+'" '
        + ' style="display:none;margin-right:2px;vertical-align: text-top;background:url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif) no-repeat center center">'
        + ((icon_bg)
            ? ('<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif"'
                + ' align="top" class="rt-icon" ' + icon_bg     
                + '>')
            : ('<div style="display:inline-block;vertical-align:-3px;border:6px solid '+bg_color+'" />')
        )
        + '<label for="chbox-'+legendid+'" style="padding-left:1em">' + title
        + '</label>'
        + warning
        + '</div>';

        legenditem = $(legenditem);

        let legend_content = $("#map_legend .content");    

        if(ontop){
            legend_content.prepend(legenditem);
        }else if(ismapdoc){  //insert according to order


            if( legend_content.children().each(function () { 
                let did = Number( $(this).attr('id').substring(3) );
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

            $('<div class="svs-contextmenu ui-icon ui-icon-close" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 
                //delete layer from map  
                let overlay_id = $(this).attr("layerid");
                _removeOverlayById( overlay_id );

                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            
            $('<div class="svs-contextmenu ui-icon ui-icon-pencil" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 

                let overlayid = $(this).attr("layerid");
                let overlay = overlays[overlayid]? overlays[overlayid] : overlays_not_in_doc[overlayid];
                
                if(overlay['editProperties']){
                    overlay.editProperties();
                }

                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            
            
            $('<div class="svs-contextmenu ui-icon ui-icon-circle-zoomin" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 

                let overlayid = $(this).attr("layerid");
                let overlay = overlays[overlayid]? overlays[overlayid] : overlays_not_in_doc[overlayid];

                overlay.zoomToOverlay();
                
                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            
        //add linked layers
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(dependent_layers)){     
            let idx;
            for (idx in dependent_layers){
                let mapdata_id = dependent_layers[idx].key;
                let mapdata_title = dependent_layers[idx].title;
                $('<div style="font-size:smaller;padding-left:16px"><label><input type="checkbox" '
                    + ' data-mapdataid="'+mapdata_id+'" class="overlay-legend-depend" '+(overlay.visible?'checked="checked">':'>')
                    + mapdata_title + '</label></div>').appendTo(legenditem);        
            }            
            legenditem.find(".overlay-legend-depend").change(_showHideLayer);
        }

        _adjustLegendHeight();
    }      

    //
    //
    //
    function _showHideOverlay(event){
        // Hide or display the layer
        let ele_cbox = $(this);
        let overlay_idx = ele_cbox.prop("value");
        let checked = ele_cbox.prop("checked");
        
        // Update overlay
        let overlay = overlays[overlay_idx] ?overlays[overlay_idx] :overlays_not_in_doc[overlay_idx];  //overlays[index]
        if(overlay){
            ele_cbox.hide();
            $('#loading-'+overlay_idx).show();
            
            setTimeout(function(){
                overlay.setVisibility(checked);
                overlay.visible = checked;
                $('#loading-'+overlay_idx).hide();
                ele_cbox.show();
            },200);
            
        }
       
        
    }
    
    //
    //
    ///
    function _showHideLayer(event){
        let mapdata_id = $(this).attr('data-mapdataid');
        let checked = $(this).prop("checked");

        mapping.showDataset(mapdata_id, checked);
    }

    //
    // assign listeners for checkboxes
    //
    function _initLegendListeners() {
        if(mapping.options('legendVisible') &&
            (Object.keys(overlays_not_in_doc).length + Object.keys(overlays).length)>0){
                
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
        // Determine way of displaying
        if(layer !== undefined && layer.dataSource !== undefined) {
            let source = layer.dataSource;
            source.title = layer.title;
            
            source.color = layer.color;
            source.iconMarker = layer.iconMarker;
            
            if(source.bounds){
                let resdata = window.hWin.HEURIST4.geo.wktValueToShapes( source.bounds, 'p', 'google' );
                if(resdata && resdata._extent){
                
                    let swBound = new google.maps.LatLng(resdata._extent.ymin, resdata._extent.xmin);
                    let neBound = new google.maps.LatLng(resdata._extent.ymax, resdata._extent.xmax);
                    bounds = new google.maps.LatLngBounds(swBound, neBound);
                }
            }            
            
            
            /** MAP IMAGE FILE (TILED) */
            if(source.rectypeID == RT_TILED_IMAGE_SOURCE) {
                addTiledMapImageLayer(source, bounds, index);

                /** MAP IMAGE FILE (NON-TILED) */
            }else if(source.rectypeID == RT_GEOTIFF_SOURCE) {
                // Map image file (non-tiled)
                addUntiledMapImageLayer(source, bounds, index);

                /** KML FILE OR SNIPPET */
            }else if(source.rectypeID == RT_KML_SOURCE) {
                addKMLLayer(source, index, is_mapdoc);

                /** SHAPE FILE */
            }else if(source.rectypeID == RT_SHP_SOURCE) {
                addShapeLayer(source, index);

                /* MAPPABLE QUERY */
            }else if(source.rectypeID == RT_MAPABLE_QUERY) {
                _addQueryLayer(source, index);
            }

            if(source.rectypeID != RT_MAPABLE_QUERY){

                _addLegendEntryForLayer(index, {title:layer.title, rectypeID:source.rectypeID} );
                
                if(overlays[index]){
                    overlays[index].source = source;       
                }
            }

        }
    }

    function _getStubOverlay(){
        return {visible:true,
            setVisibility:function(checked){},
            removeOverlay: function(){},
            zoomToOverlay: function(){}};
    }

    /**
    * Adds a tiled map image layer to the map
    * @param source Source object
    */
    function addTiledMapImageLayer(source, bounds, index) {

        // Source is a directory that contains folders in the following format: zoom / x / y eg. 12/2055/4833.png
        if(source.sourceURL !== undefined) {

            let tileUrlFunc = null; 
            
            let schema = source.tilingSchema.label.toLowerCase();

            if(schema=='virtual earth'){

                tileUrlFunc = function (a,b) {

                    function __tileToQuadKey(x, y, zoom) {
                        let i, mask, cell, quad = "";
                        for (i = zoom; i > 0; i--) {
                            mask = 1 << (i - 1);
                            cell = 0;
                            if ((x & mask) != 0) cell++;
                            if ((y & mask) != 0) cell += 2;
                            quad += cell;
                        }
                        return quad;
                    }


                    let res = source.sourceURL + __tileToQuadKey(a.x,a.y,b) 
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                    return res;
                };

            }else if(schema=='osm' || schema=='gmapimage'){

                tileUrlFunc = function(coord, zoom) {
                    let tile_url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + coord.y
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                    return tile_url;
                };
                
            }else {
/*
 function long2tile(lon,zoom) { return (Math.floor((lon+180)/360*Math.pow(2,zoom))); }
 function lat2tile(lat,zoom)  { return (Math.floor((1-Math.log(Math.tan(lat*Math.PI/180) + 1/Math.cos(lat*Math.PI/180))/Math.PI)/2 *Math.pow(2,zoom))); }                
*/                
                tileUrlFunc = function(coord, zoom) {
                    let bound = Math.pow(2, zoom);
                    let tile_url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) 
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                    return tile_url;
                };

            }


            // Tile type
            let tileType = new google.maps.ImageMapType({
                getTileUrl: tileUrlFunc,
                tileSize: new google.maps.Size(256, 256),
                minZoom: 1,
                maxZoom: 20,
                radius: 1738000,
                name: "tile"
            });

            // Set map options
            let overlay_index = map.overlayMapTypes.push( tileType )-1;

            let overlay = {
                visible:true,
                // Set visibility
                setVisibility: function(checked) {
                    this.visible = checked;
                    if(checked) {
                        map.overlayMapTypes.setAt(overlay_index, tileType);
                    }else{
                        map.overlayMapTypes.setAt(overlay_index, null);        
                    }
                },
                removeOverlay: function(){
                    map.overlayMapTypes.setAt(overlay_index, null);
                },
                zoomToOverlay: function(){
                    map.fitBounds(bounds);
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
        let msg = '';                   
        if(window.hWin.HEURIST4.util.isempty(source.files) || window.hWin.HEURIST4.util.isempty(source.files[0])){
            msg = 'Image file is not defined';
        }else if(!source.bounds){        
            msg = 'Image\'s extent is not defined. Please add values in the bounding box field for this layer.';
        }else if(source.files[0].endsWith('.tiff')||source.files[0].endsWith('.tif')){
            msg = 'At this time the GMaps mapping component used does not support GeoTIFF.';
        }

        if(msg=='') {
            let imageURL = source.files[0];

            let image_bounds = window.hWin.HEURIST4.geo.parseCoordinates('rect', source.bounds, 1, google);

            let overlay = new HeuristOverlay(image_bounds.bounds, imageURL, map);

            overlays[index] = overlay;
        }else{
            overlays[index] = _getStubOverlay();

            window.hWin.HEURIST4.msg.showMsgErr('Map layer: '+source.title
                +'<br>Unable to add image layer. '+msg);
            //Please check that the file or service specified is in one of the supported formats. 
        }
    }


    /**
    * Adds a KML layer to the map
    * @param source Source object
    */
    function addKMLLayer(source, index, is_mapdoc) {
        let kmlLayer = {};

        if(is_mapdoc!==true){
            is_mapdoc = false;
        }

        // KML file
        if(source.files !== undefined) {
            const fileURL = source.files[0];

            // note google refuses kml from localhost
            if(fileURL.indexOf('://localhost')>0)
                console.error("Error: KML file cannot be loaded from localhost: " + fileURL);
            // Display on Google Maps
            kmlLayer = new google.maps.KmlLayer({
                url: fileURL,
                suppressInfoWindows: true,
                preserveViewport: is_mapdoc,
                map: map,
                status_changed: function(){
                }
            });
        }

        // KML snippet
        if(source.kmlSnippet !== undefined) {
            /** NOTE: Snippets do not seem to be supported by the Google Maps API straight away.. */
            const fileURL = window.hWin.HAPI4.baseURL + 'hserv/controller/record_map_source.php?db='
                    +window.hWin.HAPI4.database+'&recID='+source.id;
            
            // Display on Google Maps
            kmlLayer = new google.maps.KmlLayer(fileURL, {
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
        kmlLayer.zoomToOverlay = function(){
            map.fitBounds(kmlLayer.getDefaultViewport());
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

            // Individual components
            if(source.shpFile !== undefined && source.dbfFile !== undefined) {
                // .shp & .dbf

                function __getShapeData(index){
                    let deferred = $.Deferred();
                    setTimeout(function () { 

                        let sf = new Shapefile({
                            shp: source.shpFile,
                            dbf: source.dbfFile
                            }, function (data) {
                                //addGeoJsonToMap(data, index);
                                deferred.resolve(source, data, index);
                        });
                        }, 500);

                    return deferred.promise();
                }

                $.when( __getShapeData(index) ).done(addGeoJsonToMap);
            }
    }

    /**
    * Adds GeoJson data to the map
    * @param data Data returned by the Shapefile parser
    */
    function addGeoJsonToMap(source, data, index) {
        // Add GeoJson to map
        let overlay = {
            visible:false,
            features: null,
            data: data,
            source: source,
            
            // Set visibility
            setVisibility: function(checked) {
                if(this.visible == checked) return;
                this.visible = checked;
                if(checked) {
                    this.features = map.data.addGeoJson(data.geojson);
                    
                    if(this.source.color){
                        for (let i = 0; i < this.features.length; i++) {
                            map.data.overrideStyle(this.features[i], 
                                {fillColor: this.source.color, strokeColor: this.source.color});
                        }
                    }
                    
                }else if(this.features!=null) {
                    
                    //map.data.setStyle({visible: false});
                    //Call the revertStyles() method to remove all style overrides.
                    
                    for (let i = 0; i < this.features.length; i++) {
                        map.data.remove(this.features[i]);
                    }
                    this.features = null;
                }
                
/* Set mouseover event for each feature.
map.data.addListener('mouseover', function(event) {
  document.getElementById('info-box').textContent =
      event.feature.getProperty('letter');
});*/                
                
            },
            removeOverlay: function(){
                this.setVisibility(false);
            },
            zoomToOverlay: function(){
                if(data && data.geojson && data.geojson.bbox){
                    let swBound = new google.maps.LatLng(data.geojson.bbox[1], data.geojson.bbox[0]);
                    let neBound = new google.maps.LatLng(data.geojson.bbox[3], data.geojson.bbox[2]);
                    let bounds = new google.maps.LatLngBounds(swBound, neBound);
                    map.fitBounds( bounds );
                }
                    
            },
            editProperties: function(){
                let that = this;
                _editLayerOverlayProperties( index, function(wasChanged){
                    if(wasChanged){
                        for (let i = 0; i < that.features.length; i++) {
                            map.data.overrideStyle(that.features[i], 
                                {fillColor: that.source.color, strokeColor: that.source.color});
                                //icon: that.source.iconMarker
                        }
                    }
                } );
                    
                    
            }
            
        };
        overlays[index] = overlay;
        overlay.setVisibility(true);

        let $img = $('#map_legend').find('#md-'+index+' > img.rt-icon');
        $img.css('background-image','url('+$img.attr('data-icon')+')');

    }



    /**
    * Adds a query layer to the map
    * @param source - parameters Source object
    * if index < 0 it does not belong to current map document
    */
    function _addQueryLayer(source, index) {
        // Query
        if(source && !window.hWin.HEURIST4.util.isempty(source.query)) {
            let request = window.hWin.HEURIST4.query.parseHeuristQuery(source.query);
            
            if(window.hWin.HEURIST4.util.isempty(request.q)){
                $('#mapping').css('cursor','auto');
                return;
            }

            //request['getrelrecs'] = 1;  //return all related records including relationship records
            request['detail'] = 'timemap'; //request.rules?'detail':'timemap'; //@todo on server side timemap details for rules
            
            if(_isPublicSite()){
                request['suppres_derivemaplocation']=1;
            }

            if(loadingbar==null){
                
                
                if(google.maps.marker){
                    const loadingImg = document.createElement("img");
                    loadingImg.src = window.hWin.HAPI4.baseURL+'hclient/assets/loading_bar.gif';
                    
                    loadingbar = new google.maps.marker.AdvancedMarkerElement({
                        content: loadingImg,
                        position: map.getCenter()
                    });            
                }else{
                    loadingbar = new google.maps.Marker({
                        icon: window.hWin.HAPI4.baseURL+'hclient/assets/loading_bar.gif',
                        position: map.getCenter(),
                        optimized: false
                    });            
                }
                
            }
            if(loadingbar){
                loadingbar.setMap(map);
                //loadingbar.setPosition(map.getCenter());
            }

            $('#mapping').css('cursor','progress');

            
            // Retrieve records for this request
            window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, 
            
                    function( recordset, original_recordset ){

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
            
        }else{
            $('#mapping').css('cursor','auto');
        }
    }

    //set of color for Digital Harlem dynamically added layers (or if color is not defined for layer in map document )
    let myColors = ['rgb(255,0,0)','rgb(0,255,0)','rgb(0,0,255)','rgb(34,177,76)','rgb(0,177,232)','rgb(163,73,164)','rgb(255,127,39)'];
    let colors_idx = -1;

    //
    //
    //    
    function _isPublicSite(){
        return false;
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
    *       color
    * 
    * @todo - unite with mapping.addDataset
    */
    function _addRecordsetLayer(source, index) {

        // Show info on map
        let mapdata = source.mapdata;
        if( window.hWin.HEURIST4.util.isnull(mapdata) ) {
            let recset = source.recordset;

            if( !window.hWin.HEURIST4.util.isnull(recset) ){

                if(!recset.isMapEnabled()){

                    let request = {w: 'all', 
                        detail: 'timemap'
                    };
                    
                    if(_isPublicSite()){
                        request['suppres_derivemaplocation']=1;
                    }

                    if(recset.length()<2001){ //limit query by id otherwise use current query
                        source.query = { q:'ids:'+recset.getIds().join(',') };
                    }else{
                        let curr_request = recset.getRequest();

                        source.query = { 
                            q: curr_request.q,
                            rules: curr_request.rules,
                            w: curr_request.w};
                    }

                    _addQueryLayer( source, index );

                    // Retrieve records for this request
                    /*
                    window.hWin.HAPI4.RecordMgr.search(request,
                    function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                    source.recordset = HRecordSet(response.data);
                    source.recordset.setMapEnabled( true );
                    _addRecordsetLayer(source, index);

                    }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                    }
                    );
                    */       


                    return;               
                }

                    let symbology = {iconColor:source.color, iconMarker:source.iconMarker};
                    
                        symbology.iconColor = source.color;
                        symbology.fill = source.color;
                        symbology.stroke = source.color;
                        
                        if(!window.hWin.HEURIST4.util.isnull(source.opacity))
                            symbology['fill-opacity'] = source.opacity;
                    
                    
                    
                    mapdata = recset.toTimemap(source.id, null, symbology);
                        

                    if(source.color) mapdata.color = source.color; //from layer
                    if(source.iconMarker) {
                        mapdata.iconMarker = source.iconMarker;    
                    }else{
                        let rts =recset.getRectypes();
                        if(rts && rts.length==1){
                            mapdata.rectypeID = rts[0];    
                        }
                        
                    }
                    mapdata.id = source.id;
                    mapdata.title = source['title']?source['title']:mapdata.id;

                    mapdata.forceZoom = source.forceZoom;
                    mapdata.min_zoom = source.min_zoom;

                    
                    if(recset['limit_warning']){
                        /* @todo - show individual warning per layer
                        const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                        let s = '<p>The map and timeline are limited to display a maximum of <b>'+MAXITEMS+'</b> results to avoid overloading your browser.</p>'
                        +'<br><p>There are <b>'+recset.count_total()+'</b> records with spatial and temporal data in the current results set. Please refine your filter to reduce the number of results.</p><br>'
                        +'<p>The map/timeline limit can be reset in Design > Preferences.</p>';                        

                        mapdata.title = {title:mapdata.title,
                            warning:'<div class="ui-icon ui-icon-alert" style="display:inline-block;width:20px" onclick="{window.hWin.HEURIST4.msg.showMsgDlg(\''+s+'\')}">&nbsp;</div>'};
                        */    
                    }
            }
        }    


        //mapping.load(mapdata);
        if (mapping.addDataset(mapdata)){ //see map.js

            //add depends
            let dependent_layers = [];
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(mapdata.depends)){
                let idx;
                for (idx in mapdata.depends){
                    let dep_mapdata = mapdata.depends[idx];
                    mapping.addDataset(dep_mapdata);
                    dependent_layers.push({key:dep_mapdata.id, title:dep_mapdata.title});
                }
            }


            let overlay = {
                id: mapdata.id,
                title: mapdata.title,
                dependent_layers: dependent_layers,
                visible:true,
                setVisibility: function(checked) {
                    this.visible = checked;
                    mapping.showDataset(this.id, checked); //mapdata.id
                    let idx;
                    for (idx in this.dependent_layers){
                        let mapdata_id = this.dependent_layers[idx].key;
                        mapping.showDataset(mapdata_id, checked);
                        let cb = $("#map_legend .content").find('input[data-mapdataid="'+mapdata_id+'"]');
                        if(cb.length>0){
                            cb.prop('checked',checked);
                        }
                    }
                },
                removeOverlay: function(){
                    mapping.deleteDataset( this.id ); //mapdata.id);
                    this.removeDependentLayers();
                },
                zoomToOverlay: function(){
                    mapping.zoomDataset( this.id );
                },
                removeDependentLayers: function(){
                    if(this.dependent_layers){
                        let idx;
                        for (idx in this.dependent_layers){
                            mapping.deleteDataset( this.dependent_layers[idx].key );
                        }
                    }
                },
                editProperties: function(){
                    _editLayerProperties( this.id, this.legendid );    
                }
            };
            
            if(index>=0){  //this layer belong to map document
                //was mapdata.title
                overlays[index] = overlay;
                _addLegendEntryForLayer(index, mapdata, dependent_layers); //was RT_MAPABLE_QUERY insteadof color

            }else{ // this layer is explicitely (by user) added

                //remove previous entry
                if(overlays_not_in_doc[source.id]){
                    overlays_not_in_doc[source.id].removeDependentLayers();
                }

                overlays_not_in_doc[source.id] = overlay;
                let legenditem = $("#map_legend .content").find('#'+source.id);
                if(legenditem.length>0) legenditem.remove();

                //show custom query on top
                _addLegendEntryForLayer(source.id, mapdata, dependent_layers, true );
            }
                 
            _adjustLegendHeight();

            _initLegendListeners();

        }else{  //dataset is empty or failed to add - remove from legend
            if(index<0){
                _removeOverlayById( source.id );
            }
        }
    }
        
/* NOT USED             
    function _zoomToMapdata( _mapdataid ) {
        let overlay = _getOverlayByMapdataId( _mapdataid );
        if(overlay!=null){
            mapping.zoomDataset( _mapdataid );
        }
    }
    

    //
    //
    //
    function _getOverlayByMapdataId( _mapdataid ){
        for(let idx in overlays) {
            if(overlays.hasOwnProperty(idx) && overlays[idx].id==_mapdataid){
                return overlays[idx];
            }
        }
        for(let mapdataid in overlays_not_in_doc) {
            if(overlays_not_in_doc.hasOwnProperty(mapdataid) && mapdataid==_mapdataid){
                return overlays_not_in_doc[mapdataid];
            }
        }
        return null;
    }
*/
    //
    //
    //
    function _removeOverlayById( overlay_id ){

        let ismapdoc = (overlay_id>0);
        let overlay = ismapdoc ?overlays[overlay_id] :overlays_not_in_doc[overlay_id];
        if(!window.hWin.HEURIST4.util.isnull(overlay)){
            try {
                $("#map_legend .content").find('#'+((ismapdoc)?'md-':'')+overlay_id).remove();

                //overlay.setVisibility(false);
                if(overlay['removeOverlay']){  // hasOwnProperty
                    overlay.removeOverlay();
                }
            } catch(err) {
                console.error(err);
            }
            if(ismapdoc){
                delete overlays_not_in_doc[overlay_id];
            }else{
                delete overlays[overlay_id];
            }

            _initLegendListeners();
            _adjustLegendHeight();
        }
    }

    let edit_mapdata, edit_callback, overlay_legend_id;

    
    //
    //open dialog and edit layer/dataset properties - name and color
    //
    function _editLayerProperties( dataset_id, legend_id, callback ){

        overlay_legend_id = legend_id;
        edit_callback = callback;
        
        if(!window.hWin.HEURIST4.util.isempty(dataset_id)){
                edit_mapdata = mapping.getDataset( dataset_id );
                if( window.hWin.HEURIST4.util.isnull(edit_mapdata) ){
                    if (edit_callback) edit_callback(false);
                    return;
                }
        }  

        _openDialogLayerProperties( function(){   //apply color and title

            let layer_name = $dlg_edit_layer.find("#layer_name");
            let message = $dlg_edit_layer.find('.messages');

            let bValid = window.hWin.HEURIST4.msg.checkLength( layer_name, "Name", null, 1, 30 );

            if(bValid){

                // if this is 'main' dataset (current query) 
                //      get mapdata from all_mapdata, generate id, change title and loop to change color (if required)
                //      add new overlay/dataset,  remove current (main) dataset
                // else 
                //      change titles in overlay, legend, timeline
                //      if required loop mapdata.options.items to change color and reload dataset
                let new_title = layer_name.val();
                let new_color = $dlg_edit_layer.find('#layer_color').colorpicker('val');
                let mapdata = edit_mapdata;

                if(window.hWin.HEURIST4.util.isnull(mapdata) &&
                 !window.hWin.HEURIST4.util.isnull(window.hWin.HAPI4.currentRecordset)){  //add current record set

                    /* load with current result set and new rules
                    _currentRequest??
                    let request = { q: 'ids:'+ window.hWin.HAPI4.currentRecordset.getMainSet().join(','),
                    rules: that._currentRequest.rules,
                    w: 'all'};

                    //add new layer with given name
                    let source = {id:"dhs"+window.hWin.HEURIST4.util.random(),
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
                    let source = {id:"dhs"+window.hWin.HEURIST4.util.random(),
                        title: new_title,
                        recordset:  window.hWin.HAPI4.currentRecordset,
                        color: new_color
                    };                 
                    _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);

                }else if(mapdata.id=='main'){  //rename and keep on map

                    mapdata.id = "dhs"+window.hWin.HEURIST4.util.random();
                    mapdata.title = new_title;
                    //change color scheme if required
                    mapping.changeDatasetColor( 'main', new_color, false );
                    //rename dataset for timeline items
                    /*
                    let uniqids = {};
                    for (let i=0; i<mapdata.timeline.items.length; i++){
                        if(uniqids[mapdata.timeline.items[i].recID]==undefined){
                            uniqids[mapdata.timeline.items[i].recID] = 0;
                        }else{
                            uniqids[mapdata.timeline.items[i].recID]++;
                        }
                        mapdata.timeline.items[i].id = mapdata.id 
                                        + '-' +  mapdata.timeline.items[i].recID 
                                        + '-' +uniqids[mapdata.timeline.items[i].recID];
                        mapdata.timeline.items[i].group = mapdata.id 
                    }
                    */
                    for (let i=0; i<mapdata.timeline.items.length; i++){
                        mapdata.timeline.items[i].id = mapdata.timeline.items[i].id.replace('main-',(mapdata.id+'-'));
                        mapdata.timeline.items[i].group = mapdata.id;
                    }
                    
                    //change color for dependent
                    for (let idx in mapdata.depends){
                        let dep_mapdata = mapdata.depends[idx];
                        mapping.changeDatasetColor( dep_mapdata.id, new_color, false);
                    }

                    //remove old 
                    _removeOverlayById( 'main' );
                    //add new    
                    _addRecordsetLayer({id:mapdata.id, title:new_title, mapdata:mapdata}, -1);

                    /*
                    for (let idx in mapdata.depends){
                    let dep_mapdata = mapdata.depends[idx];
                    mapping.addDataset(dep_mapdata);
                    }*/

                }else{
                    
                    let overlay_id = mapdata.id;
                    if(overlay_legend_id){
                        overlay_id = overlay_legend_id;
                    }

                    if(mapdata.title!=new_title){
                        mapdata.title = new_title;
                        $("#map_legend .content").find('#'+overlay_id+' label').html(new_title);
                        //$('#timeline > div > div.vis-panel.vis-left > div.vis-content > div > div:nth-child(2) > div
                        $('#timeline div[data-groupid="'+mapdata.id+'"]').html(new_title);
                    }
                    if(mapdata.color!=new_color){
                        $("#map_legend .content").find('#'+overlay_id+'>div').css('border-color',new_color);
                    }

                    for (let idx in mapdata.depends){
                        let dep_mapdata = mapdata.depends[idx];
                        mapping.changeDatasetColor( dep_mapdata.id, new_color, true);
                    }
                    mapping.changeDatasetColor( mapdata.id, new_color, true );
                }

                $dlg_edit_layer.dialog("close"); 
                if (edit_callback) edit_callback(true);
            }

        });  

    }
    
    //
    //
    //
    function _editLayerOverlayProperties(overlay_idx, callback){
        
        overlay_legend_id = overlay_idx;
        edit_callback = callback;
        edit_mapdata = overlays[overlay_idx].source;
        if(!edit_mapdata.title){
            edit_mapdata.title = edit_mapdata.name;
        }
        
        _openDialogLayerProperties( function(){
            
            let layer_name = $dlg_edit_layer.find("#layer_name");
            let message = $dlg_edit_layer.find('.messages');

            let bValid = window.hWin.HEURIST4.msg.checkLength( layer_name, "Name", null, 1, 30 );

            if(bValid){
                let new_title =  layer_name.val();
                let new_color = $dlg_edit_layer.find('#layer_color').colorpicker('val');
                let colorChanged = false;
                if(overlays[overlay_idx].source.title!=new_title){
                    overlays[overlay_idx].source.title = new_title;
                    $("#map_legend .content").find('#md-'+overlay_idx+' label').html(new_title);
                    //$('#timeline > div > div.vis-panel.vis-left > div.vis-content > div > div:nth-child(2) > div
                    $('#timeline div[data-groupid="'+overlay_idx+'"]').html(new_title);
                }
                if(overlays[overlay_idx].source.color!=new_color){
                    colorChanged = true;
                    overlays[overlay_idx].source.color = new_color
                    $("#map_legend .content").find('#md-'+overlay_idx+'>div').css('border-color',new_color);
                }
                
                $dlg_edit_layer.dialog("close"); 
                if (edit_callback) edit_callback(colorChanged);
            }            
        });
        
    }
    
    function _openDialogLayerProperties( onSaveCallback ){

        function __onOpen(){
            let mapdata = edit_mapdata;

            if( mapdata && mapdata.id!='main' ){
                $dlg_edit_layer.find("#layer_name").val(mapdata.title).removeClass( "ui-state-error" );
            }else{
                $dlg_edit_layer.find("#layer_name").val('').removeClass( "ui-state-error" );
            }
            let colorPicker = $dlg_edit_layer.find("#layer_color");

            colorPicker.colorpicker('val', mapdata.color);
            //colorPicker.
            //$('.evo-pop').css('z-index', '1000000 !important'); //ui-front in h4styles 999999+1

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
                title: window.hWin.HR('Define Layer'),
                //buttons: arr_buttons,
                close: function() {
                },
                open: function() {
                    __onOpen();
                }
            });
            
             $dlg_edit_layer.find("#layer_color").colorpicker({
                hideButton: false, //show button right to input
                showOn: "both"});


        }
        
        $dlg_edit_layer.dialog({
            buttons: [
                {text:window.hWin.HR('Save'), click: onSaveCallback},
                {text:window.hWin.HR('Cancel'), click: function() {
                    if (edit_callback) edit_callback(false);
                    $( this ).dialog( "close" );
                }}
            ]                
        });    
        $dlg_edit_layer.dialog("open");    
        
    }
    
    

    // 
    // Data types
    //
    let localIds = window.hWin.HAPI4.sysinfo['dbconst'];
    const RT_TILED_IMAGE_SOURCE = localIds['RT_TILED_IMAGE_SOURCE']; //2-11
    const RT_GEOTIFF_SOURCE = localIds['RT_GEOTIFF_SOURCE']; //3-1018;
    const RT_KML_SOURCE = localIds['RT_KML_SOURCE']; //3-1014;
    const RT_SHP_SOURCE = localIds['RT_SHP_SOURCE']; //3-1017;
    const RT_MAPABLE_QUERY = localIds['RT_QUERY_SOURCE']; //3-1021;


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
            this.div_.style.visibility = 'visible';
            //this.setMap(map);
        }else{
            //this.setMap(null);
            this.div_.style.visibility = 'hidden';
        }
    }


    /**
    * onAdd is called when the map's panes are ready and the overlay has been
    * added to the map.
    */
    HeuristOverlay.prototype.onAdd = function() {
        // Image div
        let div = document.createElement('div');
        div.style.borderStyle = 'none';
        div.style.borderWidth = '0px';
        div.style.position = 'absolute';

        // Title
        /*
        let span = document.createElement('span');
        span.innerHTML = "Title";
        div.appendChild(span);
        */

        // Create the img element and attach it to the div.
        let img = document.createElement('img');
        img.src = this.image_;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.position = 'absolute';
        div.appendChild(img);

        this.div_ = div;

        // Add the element to the "overlayLayer" pane.
        let panes = this.getPanes();
        panes.overlayLayer.appendChild(div);
    };

    /**
    * draw is called to draw the overlay
    */
    HeuristOverlay.prototype.draw = function() {
        // We use the south-west and north-east
        // coordinates of the overlay to peg it to the correct position and size.
        // To do this, we need to retrieve the projection from the overlay.
        let overlayProjection = this.getProjection();

        // Retrieve the south-west and north-east coordinates of this overlay
        // in LatLngs and convert them to pixel coordinates.
        // We'll use these coordinates to resize the div.
        let sw = overlayProjection.fromLatLngToDivPixel(this.bounds_.getSouthWest());
        let ne = overlayProjection.fromLatLngToDivPixel(this.bounds_.getNorthEast());

        // Resize the image's div to fit the indicated dimensions.
        let div = this.div_;
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
    
    HeuristOverlay.prototype.zoomToOverlay = function() {
        this.map_.fitBounds(this.bounds_);
    };
    //end custom overlay
    
    
    /**
    * Initialization
    */
    function _init(_mapping, startup_mapdocument_id) {

        mapping = _mapping;
        map = _mapping.getNativeMap();
        let legend = document.getElementById('map_legend');
        map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(legend); //LEFT_BOTTOM
        
        let warning = document.getElementById('map_limit_warning');
        map.controls[google.maps.ControlPosition.RIGHT_TOP].push(warning);
        
        //$(legend).css('top','60px');
        
        // Legend collapse listener
        $("#collapse").click(function(e) {
            let tocollapse = ($(this).text() == "-");
            tocollapse ? $(this).text("+") : $(this).text("-");  // Update text to + or -
            
            $("#map_legend .content").toggle();//(400);
            
            _adjustLegendHeight();
            
            window.hWin.HEURIST4.util.stopEvent(e);
            return false;
            
        });
        
        
        menu_mapdocuments = $('<ul>').appendTo( $('body') ).hide();

        let btn_mapdocs = $("#mapSelectorBtn").button({showLabel:true, label:'Select...',
                icon:"ui-icon-triangle-1-s", iconPosition:'end'}).css('max-height',22)
                .click( function(e) {
                $('.menu-or-popup').hide(); //hide other
                
                if(loading_mapdoc_list) return;
                _loadMapDocuments(null, function(){
                     menu_mapdocuments.show().position({my: "right top", at: "right bottom", of: $('#mapSelectorBtn') });    
                });
                
                $( document ).one( "click", function() { menu_mapdocuments.hide(); });
                return false;
        });
        $('#mapSelectorBtn').find('.ui-button-icon').css({position:'absolute',right:'2px'});
                

        _loadMapDocuments(startup_mapdocument_id>0?startup_mapdocument_id:0, null);

    }

    //
    //
    //    
    function _loadMapDocumentById_init(mapdocument_id, isforce){

        //if selector is hidden - onchange event does not work
        if(isforce===true || current_map_document_id != mapdocument_id){
            current_map_document_id = mapdocument_id;
            _loadMapDocumentById();      
        }
    }

    //public members
    let that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        getCurrentMapdocumentId:function(){
            return current_map_document_id;
        },
        
        loadMapDocuments:function(startup_mapdocument){
            current_map_document_id = 0;
            _loadMapDocuments(startup_mapdocument, null);
        },
        
        loadMapDocumentById: function(mapdocument_id, isforce){
            _loadMapDocumentById_init(mapdocument_id, isforce);
        },

        getMapDocumentDataById: function(mapdocument_id){
            return _getMapDocumentDataById(mapdocument_id);
        },
        
        addQueryLayer: function(params){
            _addQueryLayer(params, -1);   
        },

        addRecordsetLayer: function(params){
            _addRecordsetLayer(params, -1);
        },

        editLayerProperties: function( dataset_id, legendid, callback ){
            _editLayerProperties( dataset_id, legendid, callback );
        }

    }

    _init( mapping, startup_mapdocument_id );
    return that;  //returns object
}
