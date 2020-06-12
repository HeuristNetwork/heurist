/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
    
    var loading_mapdoc_list = false;    // flag to prevent repeated request
    var menu_mapdocuments = null;

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
        var request = { q: {"t":window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']},
            w: 'a',
            detail: 'header',
            source: 'mapSelectorBtn'};

            var btnMapRefresh = $("#btnMapRefresh");
            var btnMapEdit = $("#btnMapEdit");
            window.hWin.HEURIST4.util.setDisabled(btnMapEdit, true);
            window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, true);

        
            //perform search
            window.hWin.HAPI4.RecordMgr.search(request, function(response){

                loading_mapdoc_list = false;
                    
                if(response.status == window.hWin.ResponseStatus.OK){
                    var resdata = new hRecordSet(response.data);

                    var mapdocs = '';
                    //ele.append("<option value='-1'>"+(resdata.length()>0?'select...':'none available')+"</option>");
                    mapdocs = mapdocs + "<li mapdoc_id='-1'><a>"+(resdata.length()>0?'None':'none available')+"</a></li>";

                    var idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            var record = records[idx];
                            var recID  = resdata.fld(record, 'rec_ID'),
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
                    
                    var w = $(window).width();
                    if (w < 400) {
                        $("#mapSelectorBtn").button({showLabel:false}).width(20);
                    }else{
                        $("#mapSelectorBtn").button({showLabel:true}).width(w-360);
                    }
                    
                    
                    if(startup_mapdocument>=0){
                        if(startup_mapdocument>0){
                            _loadMapDocumentById_init(startup_mapdocument);
                        }
                    }else{
                        
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

//console.log('load '+current_map_document_id);    
        menu_mapdocuments.hide();

        // Clean old data
        $('#map_extents').css('visibility','hidden');
        _removeMapDocumentOverlays();
        var selBookmakrs = document.getElementById('selMapBookmarks');
        $(selBookmakrs).empty();
        var btnMapRefresh = $("#btnMapRefresh");
        var btnMapEdit = $("#btnMapEdit");
        window.hWin.HEURIST4.util.setDisabled(btnMapEdit, true);
        window.hWin.HEURIST4.util.setDisabled(btnMapRefresh, true);
        btnMapEdit.attr('title','');

        if(current_map_document_id>0)
        {

            var baseurl = window.hWin.HAPI4.baseURL + 'hsapi/controller/map_data.php';
            var request = {db:window.hWin.HAPI4.database, id:current_map_document_id};
            
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
            for(var i=0;i<map_data.length;i++){
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

//console.log('load documents continue');        
        
        var mapdocument_id =  current_map_document_id;

        //find mapdoc data
        var doc = _getMapDocumentDataById(mapdocument_id);
        var lt = window.hWin.HAPI4.sysinfo['layout'];   

        var selBookmakrs = document.getElementById('selMapBookmarks');
        var btnMapRefresh = $("#btnMapRefresh");
        var btnMapEdit = $("#btnMapEdit");
        if( !window.hWin.HEURIST4.util.isnull(doc) ) {
            
            var bounds = null, err_msg_all = '';

            map_bookmarks = [];
            window.hWin.HEURIST4.ui.addoption(selBookmakrs, -1, 'bookmarks...');

            // Longitude,Latitude centrepoint, Initial minor span
            // add initial bookmarks based on long lat  minorSpan
            var cp_long = _validateCoord(doc.long,false);
            var cp_lat = _validateCoord(doc.lat,true);
            if(!isNaN(cp_long) && !isNaN(cp_lat) && doc.minorSpan>0){
                
                var body = $(this.document).find('body');
                var prop = body.innerWidth()/body.innerHeight();

                if(isNaN(prop) || prop==0) prop = 1;
                
                if(body.innerWidth()<body.innerHeight()){
                    span_x = doc.minorSpan;
                    span_y = doc.minorSpan/prop;
                }else{
                    span_x = doc.minorSpan*prop;
                    span_y = doc.minorSpan;
                }
                var init_extent = ['Initial extent', cp_long-span_x/2,cp_long+span_x/2, cp_lat-span_y/2,cp_lat+span_y/2 ];
                doc.bookmarks.unshift(init_extent);
            }
            

            for(var i=0;i<doc.bookmarks.length;i++){

                var bookmark = doc.bookmarks[i];
                var err_msg = '';

                if(bookmark.length<5){
                    err_msg = 'not enough parameters';
                }else{

                    var x1 = _validateCoord(bookmark[1],false); 
                    if(isNaN(x1)) err_msg = err_msg + ' bad value for xmin:' + x1;
                    var x2 = _validateCoord(bookmark[2],false);
                    if(isNaN(x2)) err_msg = err_msg + ' bad value for xmax:' + x2;
                    var y1 = _validateCoord(bookmark[3],true);
                    if(isNaN(y1)) err_msg = err_msg + ' bad value for ymin:' + y1;
                    var y2 = _validateCoord(bookmark[4],true);
                    if(isNaN(y2)) err_msg = err_msg + ' bad value for ymax:' + y2;

                    var tmin = null, tmax = null, dres = null;

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
                    err_msg_all = err_msg_all + '<br/>Map bookmark: "' + bookmark.join(',')+'" : '+err_msg;
                    continue;
                }

                var swBound = new google.maps.LatLng(y1, x1);
                var neBound = new google.maps.LatLng(y2, x2);
                bounds = new google.maps.LatLngBounds(swBound, neBound);

                window.hWin.HEURIST4.ui.addoption(selBookmakrs, map_bookmarks.length, bookmark[0]?bookmark[0]:'Extent '+(map_bookmarks.length+1));

                map_bookmarks.push({extent:bounds, tmin:tmin, tmax:tmax});

                if(i==doc.bookmarks.length-2 && map_bookmarks.length>0){
                    break; //skip last default bookmark if user define its own extents
                }
            }//for map bookmarks
            if(err_msg_all!=''){
                window.hWin.HEURIST4.msg.showMsgErr('<div>Map-zoom bookmark is not interpretable, set to Label,xmin,xmax,ymin,ymax,tmin,tmax (tmin,tmax are optional)</div>'
                +'<br>eg. Harlem, -74.000000,-73.900000,40.764134,40.864134,1915,1930<br/> '
                    +err_msg_all
                    +'<br><br><div>Please edit the map document (button next to map name dropdown above) and correct the contents of the map-zoom bookmark following the instructions in the field help.</div>'
                );
            }else{
                
                //show info popup
                
                if(lt && lt.indexOf('DigitalHarlem')==0){ //for DigitalHarlem we adds 2 dataset - points and links
                    if(!window.hWin.HEURIST4.util.isempty( doc['description']) ){
                        
                        var ele = $(window.hWin.document.body).find('#dh_search_2');
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(doc['description'], null, doc['title'], 
                        {resizable:true, modal:false, width:ele.width(), height:ele.height()-100,
                            my:'left top', at:'left top', of:ele});
                            
                    }
                }
                
            }



            var selBookmakrs = document.getElementById('selMapBookmarks')                  
            selBookmakrs.onmousedown = function(){ selBookmakrs.selectedIndex = 0; }
            selBookmakrs.onchange = function(){
                var val = $(selBookmakrs).val();
                if(val>=0){
                    map.fitBounds(map_bookmarks[val]['extent']);    
                    if(map_bookmarks[val]['tmin']!=null)
                        mapping.timelineZoomToRange(map_bookmarks[val]['tmin'],map_bookmarks[val]['tmax']);
                }
            }
            
            $('#map_extents').css('visibility','visible');
            selBookmakrs.selectedIndex = 1;
            $(selBookmakrs).change();

            mapping.setTimeMapProperty('centerOnItems', false);    
            
            var dataset_id = 0;
            
            // Map document layers
            var overlay_index = 1;
            if(doc.layers.length > 0) {
                for(var i = 0; i < doc.layers.length; i++) {
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


            var map_container = mapping.getMapContainerDiv();
            if(!map_container.is(':visible')){
                //console.log('!!!!container is not visible');
                
                var checkVisible = setInterval(function(){

                    if(!map_container.is(':visible')) return;
                    clearInterval(checkVisible); //stop listener

                    $(selBookmakrs).change();
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
        var crd = Number(value);
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

        var legend_content = $("#map_legend .content");

        for(var idx in overlays) {
            _removeOverlayById(idx);
        }
        overlays = {};
        
        _adjustLegendHeight();
    }

    //
    // dependent_layers -  dependent map layers  [{key :title}]
    //
    function _addLegendEntryForLayer(overlay_idx, layer_options, dependent_layers, ontop){

        var overlay = null,
        legendid,
        ismapdoc = (overlay_idx>0);
        
        var icon_bg = null;
        
        var title = layer_options.title;
        var bg_color = layer_options.color;
        var rectypeID = layer_options.rectypeID;


        if (ismapdoc) {
            legendid = 'md-'+overlay_idx;
            overlay = overlays[overlay_idx];
            
            if(!overlay) return;

            if(rectypeID && rectypeID==RT_SHP_SOURCE && overlay.visible){
                icon_bg = 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif);'
                + 'background-position: center; background-repeat: no-repeat;"'            
                + ' data-icon="'+(layer_options.iconMarker
                        ?layer_options.iconMarker
                        :(window.hWin.HAPI4.iconBaseURL + rectypeID + '.png'))
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
                var suffix = '.png';
                if(bg_color){
                    suffix = 'm.png&color='+encodeURIComponent(bg_color.replace(/ /g,''));            
                }
                icon_bg = 'url(\''+ window.hWin.HAPI4.iconBaseURL + rectypeID + suffix + '\');background-size: 18px 18px;"';
        }
        if(icon_bg!=null){
            icon_bg = ' style="background-image: '+icon_bg;
        }
        
        overlay.legendid = legendid;
        
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

        if(true || !ismapdoc){//it is possible to edit mapdoc layers since 2017/07/01

            $('<div class="svs-contextmenu ui-icon ui-icon-close" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 
                //delete layer from map  
                var overlay_id = $(this).attr("layerid");
                _removeOverlayById( overlay_id );

                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            
            $('<div class="svs-contextmenu ui-icon ui-icon-pencil" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 

                var overlayid = $(this).attr("layerid");
                var overlay = overlays[overlayid]? overlays[overlayid] : overlays_not_in_doc[overlayid];
                
                if(overlay['editProperties']){
                    overlay.editProperties();
                }

                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            
            
            $('<div class="svs-contextmenu ui-icon ui-icon-circle-zoomin" layerid="'+overlay_idx+'"></div>')
            .click(function(event){ 

                var overlayid = $(this).attr("layerid");
                var overlay = overlays[overlayid]? overlays[overlayid] : overlays_not_in_doc[overlayid];

                overlay.zoomToOverlay();
                
                window.hWin.HEURIST4.util.stopEvent(event); return false;})
            .appendTo(legenditem);
            

        }
        //add linked layers

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(dependent_layers)){     
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

        _adjustLegendHeight();
    }      

    //
    //
    //
    function _showHideOverlay(event){
        // Hide or display the layer
        var ele_cbox = $(this);
        var overlay_idx = ele_cbox.prop("value");
        var checked = ele_cbox.prop("checked");
        
        // Update overlay
        var overlay = overlays[overlay_idx] ?overlays[overlay_idx] :overlays_not_in_doc[overlay_idx];  //overlays[index]
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
        var mapdata_id = $(this).attr('data-mapdataid');
        var checked = $(this).prop("checked");

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
        //console.log("_addLayerOverlay");
        //console.log(layer);

        // Determine way of displaying
        if(layer !== undefined && layer.dataSource !== undefined) {
            var source = layer.dataSource;
//DEBUG console.log(source);
            source.title = layer.title;
            
            source.color = layer.color;
            source.iconMarker = layer.iconMarker;
            
            if(source.bounds){
                var resdata = window.hWin.HEURIST4.geo.wktValueToShapes( source.bounds, 'p', 'google' );
                if(resdata && resdata._extent){
                
                    var swBound = new google.maps.LatLng(resdata._extent.ymin, resdata._extent.xmin);
                    var neBound = new google.maps.LatLng(resdata._extent.ymax, resdata._extent.xmax);
                    bounds = new google.maps.LatLngBounds(swBound, neBound);
                }
            }            
            
            
            /** MAP IMAGE FILE (TILED) */
            if(source.rectypeID == RT_TILED_IMAGE_SOURCE) {
//DEBUG console.log("MAP IMAGE FILE (tiled)");
                addTiledMapImageLayer(source, bounds, index);

                /** MAP IMAGE FILE (NON-TILED) */
            }else if(source.rectypeID == RT_GEOTIFF_SOURCE) {
                // Map image file (non-tiled)
//DEBUG console.log("MAP IMAGE FILE (non-tiled)");
                addUntiledMapImageLayer(source, bounds, index);

                /** KML FILE OR SNIPPET */
            }else if(source.rectypeID == RT_KML_SOURCE) {
//DEBUG console.log("KML FILE or SNIPPET");
                addKMLLayer(source, index, is_mapdoc);

                /** SHAPE FILE */
            }else if(source.rectypeID == RT_SHP_SOURCE) {
//DEBUG console.log("SHAPE FILE");
                addShapeLayer(source, index);

                /* MAPPABLE QUERY */
            }else if(source.rectypeID == RT_MAPABLE_QUERY) {
//DEBUG console.log("MAPPABLE QUERY");
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

            var tileUrlFunc = null; 
            
            var schema = source.tilingSchema.label.toLowerCase();

            if(schema=='virtual earth'){

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

            }else if(schema=='osm' || schema=='gmapimage'){

                tileUrlFunc = function(coord, zoom) {
                    var tile_url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + coord.y
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
                    //console.log("URL: " + tile_url);
               
                    return tile_url;
                };
                
            }else {
/*
 function long2tile(lon,zoom) { return (Math.floor((lon+180)/360*Math.pow(2,zoom))); }
 function lat2tile(lat,zoom)  { return (Math.floor((1-Math.log(Math.tan(lat*Math.PI/180) + 1/Math.cos(lat*Math.PI/180))/Math.PI)/2 *Math.pow(2,zoom))); }                
*/                
                tileUrlFunc = function(coord, zoom) {
                    //console.log(coord);
                    //console.log(zoom);

                    var bound = Math.pow(2, zoom);
                    var tile_url = source.sourceURL + "/" + zoom + "/" + coord.x + "/" + (bound - coord.y - 1) 
                    + (source.mimeType.label == "image/png" ? ".png" : ".gif");
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
                name: "tile"
            });

            // Set map options
            var overlay_index = map.overlayMapTypes.push( tileType )-1;

            var overlay = {
                visible:true,
                // Set visibility
                setVisibility: function(checked) {
                    console.log("Setting visibility to: " + checked);
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
        var msg = '';                   
        if(window.hWin.HEURIST4.util.isempty(source.files) || window.hWin.HEURIST4.util.isempty(source.files[0])){
            msg = 'Image file is not defined';
        }else if(!source.bounds){        
            msg = 'Image\'s extent is not defined. Please add values in the bounding box field for this layer.';
        }else if(source.files[0].endsWith('.tiff')||source.files[0].endsWith('.tif')){
            msg = 'At this time the GMaps mapping component used does not support GeoTIFF.';
        }

        if(msg=='') {
            var imageURL = source.files[0];

            var image_bounds = window.hWin.HEURIST4.geo.parseCoordinates('rect', source.bounds, 1, google);

            var overlay = new HeuristOverlay(image_bounds.bounds, imageURL, map);

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
        var kmlLayer = {};

        if(is_mapdoc!==true){
            is_mapdoc = false;
        }

        // KML file
        if(source.files !== undefined) {
            var fileURL = source.files[0];

            // note google refuses kml from localhost
            if(fileURL.indexOf('://localhost')>0)
                console.log("Note: KML file can not be loaded from localhost: " + fileURL);
            // Display on Google Maps
            kmlLayer = new google.maps.KmlLayer({
                url: fileURL,
                suppressInfoWindows: true,
                preserveViewport: is_mapdoc,
                map: map,
                status_changed: function(){
                    //console.log('status: '+kmlLayer.getStatus());
                }
            });
        }

        // KML snippet
        if(source.kmlSnippet !== undefined) {
            /** NOTE: Snippets do not seem to be supported by the Google Maps API straight away.. */
            //console.log("KML snippet: " + source.kmlSnippet);

            var fileURL = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_map_source.php?db='
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
                                deferred.resolve(source, data, index);
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
    function addGeoJsonToMap(source, data, index) {
        // Add GeoJson to map
        //console.log(data);

        var overlay = {
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
                        for (var i = 0; i < this.features.length; i++) {
                            map.data.overrideStyle(this.features[i], 
                                {fillColor: this.source.color, strokeColor: this.source.color});
                        }
                    }
                    
                }else if(this.features!=null) {
                    
                    //map.data.setStyle({visible: false});
                    //Call the revertStyles() method to remove all style overrides.
                    
                    for (var i = 0; i < this.features.length; i++) {
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
                    var swBound = new google.maps.LatLng(data.geojson.bbox[1], data.geojson.bbox[0]);
                    var neBound = new google.maps.LatLng(data.geojson.bbox[3], data.geojson.bbox[2]);
                    var bounds = new google.maps.LatLngBounds(swBound, neBound);
                    map.fitBounds( bounds );
                }
                    
            },
            editProperties: function(){
                var that = this;
                _editLayerOverlayProperties( index, function(wasChanged){
                    if(wasChanged){
                        for (var i = 0; i < that.features.length; i++) {
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
        if(source && !window.hWin.HEURIST4.util.isempty(source.query)) {
            //console.log("Query: " + source.query);
            var request = window.hWin.HEURIST4.util.parseHeuristQuery(source.query);
            
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
                var image = window.hWin.HAPI4.baseURL+'hclient/assets/loading_bar.gif';
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
            window.hWin.HAPI4.SearchMgr.doSearchWithCallback( request, 
            
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
            
            /*
            window.hWin.HAPI4.RecordMgr.search(request,
            function(response){
            //console.log("QUERY RESPONSE:");
            //console.log(response);

            if(response.status == window.hWin.ResponseStatus.OK){

            source.recordset = hRecordSet(response.data);
            source.recordset.setMapEnabled( true );

            _addRecordsetLayer(source, index);

            }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            }
            );*/


        }else{
            $('#mapping').css('cursor','auto');
        }
    }

    //set of color for Digital Harlem dynamically added layers (or if color is not defined for layer in map document )
    var myColors = ['rgb(255,0,0)','rgb(0,255,0)','rgb(0,0,255)','rgb(34,177,76)','rgb(0,177,232)','rgb(163,73,164)','rgb(255,127,39)'];
    var colors_idx = -1;

    //
    //
    //    
    function _isPublicSite(){
        var lt = window.hWin.HAPI4.sysinfo['layout'];                                      
        return lt && (lt.indexOf('DigitalHarlem')==0 || lt=='Beyond1914' || lt=='UAdelaide');
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
        var mapdata = source.mapdata;
        if( window.hWin.HEURIST4.util.isnull(mapdata) ) {
            var recset = source.recordset;

            if( !window.hWin.HEURIST4.util.isnull(recset) ){

                if(!recset.isMapEnabled()){

                    var request = {w: 'all', 
                        detail: 'timemap'
                    };
                    
                    if(_isPublicSite()){
                        request['suppres_derivemaplocation']=1;
                    }

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
                    window.hWin.HAPI4.RecordMgr.search(request,
                    function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                    source.recordset = hRecordSet(response.data);
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

                var lt = window.hWin.HAPI4.sysinfo['layout'];
                if(lt && lt.indexOf('DigitalHarlem')==0){ 
                    //for DigitalHarlem we adds 3 dataset 
                    // For events search: primary events, secondary events+links, residences+links
                    // For persons search: everything on one layer

                    if(colors_idx>=myColors.length) colors_idx = -1;
                    colors_idx++;
                    source.color = myColors[colors_idx];

                    //points  DH_RECORDTYPE
                    //change last parameter to 1 - to treat links separately
                    mapdata = recset.toTimemap(source.id, 99913, {iconColor:source.color}, 0); //set to 1 to show main geo only (no links)
                    mapdata.id = source.id;
                    mapdata.title = source['title']?source['title']:mapdata.id;
                    
                    mapdata.forceZoom = source.forceZoom;
                    mapdata.min_zoom = source.min_zoom;

                    mapdata.depends = [];

                    //secondary points  DH_RECORDTYPE_SECONDARY
                    var random_name_for_secondary = "link_"+window.hWin.HEURIST4.util.random();
                    //change last parameter to 1 - to treat links separately
                    var mapdata3 = recset.toTimemap(random_name_for_secondary, 99914, {iconColor:source.color}, 0); //records with type "secondary"
                    mapdata3.id = random_name_for_secondary;
                    mapdata3.title = 'Secondary locations';
                    //mapdata3.timeenabled = 0;
                    //mapdata3.timeline = {items:[]};
                    if(mapdata3.mapenabled>0){
                        mapdata.depends.push(mapdata3);
                    }

                    //residences  DH_RECORDTYPE_RESIDENCES
                    var random_name_for_secondary = "link_"+window.hWin.HEURIST4.util.random();
                    //change last parameter to 1 - to treat links separately
                    var mapdata4 = recset.toTimemap(random_name_for_secondary, 99915, {iconColor:source.color}, 0); //records with type "residence"
                    mapdata4.id = random_name_for_secondary;
                    mapdata4.title = 'Residence of participants';
                    if(mapdata4.mapenabled>0){
                        mapdata.depends.push(mapdata4);
                    }
                    
                    /* if we wish show links as separate layer need to unremark this section
                    //links
                    var mapdata2 = recset.toTimemap(source.id, null, {iconColor:source.color}, 2); //rec_Shape only
                    mapdata2.id = "link_"+window.hWin.HEURIST4.util.random();
                    mapdata2.title = 'Links';
                    mapdata2.timeenabled = 0;
                    mapdata2.timeline = {items:[]};
                    if(mapdata2.mapenabled>0){
                        mapdata.depends.push(mapdata2);
                    }
                    */

                }else{

                    var symbology = {iconColor:source.color, iconMarker:source.iconMarker};
                    
                    if(lt=='Beyond1914' || lt=='UAdelaide'){//customized symbology and popup for expertnation
                    
                        recset.calcfields['rec_Info'] = function(record, fldname){
                            
                            var info_content = this.fld(record, 'rec_Description');
                            if(window.hWin.HEURIST4.util.isempty(info_content)){
                                info_content = this.fld(record, 'rec_Title');
                            }

                            if(info_content.indexOf('bor-map-infowindow-heading')<0){
                                info_content = '<div class="bor-map-infowindow-heading">'+info_content+'</div>';
                            }
                            
                            return '<div style="display: inline-block; overflow: auto; max-height: 369px; max-width: 260px;">'
                                    +'<div class="bor-map-infowindow">'
                                        + info_content
                                        + '<a href="'
                                        + window.hWin.HAPI4.baseURL+'place/'+this.fld(record,'rec_ID')+'/a" '
                                        + 'onclick="{window.hWin.enResolver(event);}" class="bor-button bor-map-button">See connections</a>'
                                    +'</div></div>';                            
                        }
                        
                        symbology.stroke = 'rgb(128,0,128)';
                        symbology.fill   = 'rgb(128,0,128)';
                        symbology['fill-opacity'] = 0.1;
                        
                    }else{
                        
                        symbology.iconColor = source.color;
                        symbology.fill = source.color;
                        symbology.stroke = source.color;
                        
                        if(!window.hWin.HEURIST4.util.isnull(source.opacity))
                            symbology['fill-opacity'] = source.opacity;
                    }
                    
                    
                    mapdata = recset.toTimemap(source.id, null, symbology);
                        

                    if(source.color) mapdata.color = source.color; //from layer
                    if(source.iconMarker) {
                        mapdata.iconMarker = source.iconMarker;    
                    }else{
                        var rts =recset.getRectypes();
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
                        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                        var s = '<p>The map and timeline are limited to display a maximum of <b>'+MAXITEMS+'</b> results to avoid overloading your browser.</p>'
                        +'<br/><p>There are <b>'+recset.count_total()+'</b> records with spatial and temporal data in the current results set. Please refine your filter to reduce the number of results.</p><br/>'
                        +'<p>The map/timeline limit can be reset in Settings > Preferences.</p>';                        

                        mapdata.title = {title:mapdata.title,
                            warning:'<div class="ui-icon ui-icon-alert" style="display:inline-block;width:20px" onclick="{window.hWin.HEURIST4.msg.showMsgDlg(\''+s+'\')}">&nbsp;</div>'};
                        */    
                    }
                }
            }
        }    


        //mapping.load(mapdata);
        if (mapping.addDataset(mapdata)){ //see map.js

            //add depends
            var dependent_layers = [];
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(mapdata.depends)){
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
                zoomToOverlay: function(){
                    mapping.zoomDataset( this.id );
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
                var legenditem = $("#map_legend .content").find('#'+source.id);
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
        var overlay = _getOverlayByMapdataId( _mapdataid );
        if(overlay!=null){
            mapping.zoomDataset( _mapdataid );
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
*/
    //
    //
    //
    function _removeOverlayById( overlay_id ){

        var ismapdoc = (overlay_id>0);
        var overlay = ismapdoc ?overlays[overlay_id] :overlays_not_in_doc[overlay_id];
        if(!window.hWin.HEURIST4.util.isnull(overlay)){
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
            _adjustLegendHeight();
        }
    }

    var edit_mapdata, edit_callback, overlay_legend_id;

    
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

            var layer_name = $dlg_edit_layer.find("#layer_name");
            var message = $dlg_edit_layer.find('.messages');

            var bValid = window.hWin.HEURIST4.msg.checkLength( layer_name, "Name", null, 1, 30 );

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

                if(window.hWin.HEURIST4.util.isnull(mapdata) &&
                 !window.hWin.HEURIST4.util.isnull(window.hWin.HAPI4.currentRecordset)){  //add current record set

                    /* load with current result set and new rules
                    _currentRequest??
                    var request = { q: 'ids:'+ window.hWin.HAPI4.currentRecordset.getMainSet().join(','),
                    rules: that._currentRequest.rules,
                    w: 'all'};

                    //add new layer with given name
                    var source = {id:"dhs"+window.hWin.HEURIST4.util.random(),
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
                    var source = {id:"dhs"+window.hWin.HEURIST4.util.random(),
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
                    var uniqids = {};
                    for (var i=0; i<mapdata.timeline.items.length; i++){
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
                    for (var i=0; i<mapdata.timeline.items.length; i++){
                        mapdata.timeline.items[i].id = mapdata.timeline.items[i].id.replace('main-',(mapdata.id+'-'));
                        mapdata.timeline.items[i].group = mapdata.id;
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
                    
                    var overlay_id = mapdata.id;
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
            
            var layer_name = $dlg_edit_layer.find("#layer_name");
            var message = $dlg_edit_layer.find('.messages');

            var bValid = window.hWin.HEURIST4.msg.checkLength( layer_name, "Name", null, 1, 30 );

            if(bValid){
                var new_title =  layer_name.val();
                var new_color = $dlg_edit_layer.find('#layer_color').colorpicker('val');
                var colorChanged = false;
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
            var mapdata = edit_mapdata;

            if( mapdata && mapdata.id!='main' ){
                $dlg_edit_layer.find("#layer_name").val(mapdata.title).removeClass( "ui-state-error" );
            }else{
                $dlg_edit_layer.find("#layer_name").val('').removeClass( "ui-state-error" );
            }
            var colorPicker = $dlg_edit_layer.find("#layer_color");

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
    var localIds = window.hWin.HAPI4.sysinfo['dbconst'];
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
        var legend = document.getElementById('map_legend');
        map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(legend); //LEFT_BOTTOM
        
        var warning = document.getElementById('map_limit_warning');
        map.controls[google.maps.ControlPosition.RIGHT_TOP].push(warning);
        
        //$(legend).css('top','60px');
        
        // Legend collapse listener
        $("#collapse").click(function(e) {
            var tocollapse = ($(this).text() == "-");
            tocollapse ? $(this).text("+") : $(this).text("-");  // Update text to + or -
            
            $("#map_legend .content").toggle();//(400);
            
            _adjustLegendHeight();
            
            window.hWin.HEURIST4.util.stopEvent(e);
            return false;
            
        });
        
        
        menu_mapdocuments = $('<ul>').appendTo( $('body') ).hide();

        var btn_mapdocs = $("#mapSelectorBtn").button({showLabel:true, label:'Select...',
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
    var that = {
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
