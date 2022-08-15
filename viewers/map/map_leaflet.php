<?php

/**
* Mapping page. It can be loaded in app_timemap widget or launched as standalone page
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

//&callback=initMap" async defer  for gmap
//<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=drawing"></script>
//
define('PDIR','../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../hclient/framecontent/initPage.php');

$system->defineConstants();
?>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.layout/jquery.layout-latest.js"></script>

<link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/geocoder/Control.Geocoder.css" />
<?php
if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/leaflet.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancytree/jquery.fancytree-all.min.js"></script>
<?php
}else{
?>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
      integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
      crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
      integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
      crossorigin=""></script>
    <!-- link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" 
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>  /-->
    
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.16.1/jquery.fancytree-all.min.js"></script>
<?php
}
?>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/geocoder/Control.Geocoder.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet-tileLayerPixelFilter.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.js"></script>

<!-- leaflet plugins -->
<script src="<?php echo PDIR;?>external/leaflet/leaflet-providers.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/bookmarks/Leaflet.Bookmarks.min.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/draw/leaflet.draw-src.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/bookmarks/leaflet.bookmarks.css">
<script src="<?php echo PDIR;?>external/leaflet/leaflet.browser.print.min.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/draw/leaflet.draw.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/markercluster/MarkerCluster.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/markercluster/MarkerCluster.Default.css">
<script src="<?php echo PDIR;?>external/leaflet/markercluster/leaflet.markercluster.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/wise-leaflet-pip.js"></script>

<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/bookmarks/leaflet.bookmarks.css">


<script src="<?php echo PDIR;?>external/leaflet/leaflet-iiif.js"></script>


<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/timeline.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapPublish.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/cheapRuler.js" charset="utf-8"></script>

<!-- this scripts can be omitted for publishing -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
<link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/imgFilter.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_color.js"></script>


<!--
// WARNING: CHANGES MADE TO vis.js
// These changes are not in our repository
// line:285 remove margin for item's label
// line:345,15753, 15860, 16108 correct orientation for bottom order
// line: 13607 getDataRangeHeurist function
// line: 13594 catch exception of datetime convertation
//
-->

<?php 
print '<!--';
print @$_REQUEST['notimeline'];
print '<br>';
print !@$_REQUEST['notimeline'];
print '-->';
if (!(@$_REQUEST['notimeline']=='true' || @$_REQUEST['notimeline']=='1')) { ?>
<script type="text/javascript" src="<?php echo PDIR;?>external/vis/dist/vis.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/vis/dist/vis.css" />
<?php } ?>
<style>
    body{
        margin:0px;
    }
    
    .leaflet-div-icon {
        background: none;
        border: none;
    }
    .leaflet-editing-icon{
        background: #fff;
        border: 1px solid #666;        
    }

    /*   
    .vis-item-overflow{
    position:absolute;
    }
    .vis-item-overflow .vis-item{
    left:0px;
    top:0px;
    height:100%;
    border:none;
    }
    .vis-item-overflow .vis-item-content{
    position:relative;
    left: 4px;
    }
    .vis-item-overflow .vis-selected{
    z-index:2;
    background-color:#bee4f8 !important;
    border:none;
    }
    .vis-item.vis-selected{
    background-color:#bee4f8 !important;
    }
    */

    .vis-panel.vis-left, .vis-time-axis.vis-foreground{
        background-color:#DDDDDD;
    }
    .vis-item-content{
        width: 10em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vis-item-bbox{
        background-color:rgb(142, 169, 185);
    }
    
    #term_tree{
         background:none;
         border:none;
    }

    div.svs-contextmenu3{
        right:7px;
        display:none;
        background:lightgray;
        color:black;/*#95A7B7;*/
    }

    .leaflet-control-browser-print{
        width: 22px !important;
        height: 22px !important;
    }
    
    .leaflet-browser-print{
        width: 22px !important;
        height: 22px !important;
        line-height: 22px !important;
        background-position: 3px !important;
    }
    
    .v1 .browser-print-mode{
        padding: 3px 10px;
    }

    span.ui-selectmenu-text{
        background-color: transparent !important;
    }

    .activated-mapdoc{
        background-color: #4477B9 !important;
        color: white;
    }

    /*
    .leaflet-layer {
        filter: sepia(100%);
        filter: invert(100%); grayscale(100%) sepia(100%) hue-rotate(180deg)
    }    
    */
</style>

<!-- Initializing -->

<script type="text/javascript">

    var mapping, menu_datasets, btn_datasets;
    
    // Callback function on page initialization - see initPage.php
    function onPageInit(success){
        
        if(!success) return;
        
        var lt = window.hWin.HAPI4.sysinfo['layout'];
        if(lt=='Beyond1914' || lt=='UAdelaide'){
                $("head").append($('<link rel="stylesheet" type="text/css" href="'
                    +window.hWin.HAPI4.baseURL+'hclient/widgets/expertnation/expertnation.css?t='+(new Date().getTime())+'">'));
        }        
        
        //since 2022-06-09 layout_params as passed via option on map init   
        
        function __gp(name){
            return window.hWin.HEURIST4.util.getUrlParameter(name, location.search);
        }

        var layout_params = null;
        
        //take layout parameters from url 
        // params: 
        //   nomap, notimeline
        //   controls: [all,none,zoom,bookmark,geocoder,print,publish,legend]
        //   legend: [basemaps,search,mapdocs|onedoc]
        //   basemap: name of initial basemap
        //   extent: fixed extent    
        layout_params = {};
        layout_params['ui_main'] = __gp('ui_main'); //separate toolbar for map controls
        layout_params['nomap'] = __gp('nomap');
        layout_params['notimeline'] = __gp('notimeline');
        layout_params['nocluster'] = __gp('nocluster');
        layout_params['editstyle'] = __gp('editstyle');
        layout_params['basemap'] = __gp('basemap');  //name of basemap
        layout_params['basemap_filter'] = __gp('basemap_filter');  //name of basemap
        layout_params['extent'] = __gp('extent'); //@todo
        
        layout_params['controls'] = __gp('controls'); //comma separated list of visible controls
        layout_params['legend'] = __gp('legend'); //legend configuration: csv basemaps,mapdocs,search,off,width
        layout_params['template'] = __gp('template'); //smarty template for popup info

        layout_params['popup_behaviour'] = __gp('popup_behaviour'); // fixed size, fixed width, scale to content
        layout_params['popup_width'] = __gp('popup_width'); // = width, for scale = max-width
        layout_params['popup_height'] = __gp('popup_height'); // = height, for scale = max-height
        layout_params['popup_resizing'] = __gp('popup_resizing'); // whether to enable resizing, currently turned off

        layout_params['published'] = __gp('published');
        layout_params['popup'] = __gp('popup');
        layout_params['map_rollover'] = __gp('map_rollover');
        layout_params['maxzoom'] = __gp('maxzoom');
        layout_params['minzoom'] = __gp('minzoom');
        layout_params['pntzoom'] = __gp('pntzoom');
        layout_params['style'] = window.hWin.HEURIST4.util.isJSON(__gp('style')); //default style
        
        mapping = $('#mapping').mapping({
            element_layout: '#mapping',
            element_map: '#map',
            layout_params:layout_params,
            oninit: onMapInit,
            drawMode: layout_params['ui_main']?'full':''
        });
    }

    //
    // init map data based on url parameters
    //
    function onMapInit( mapwdiget ){
        
//console.log('>>>onMapInit');
        
        //take url parameters and open mapdocument or/and perform query
            //take from frame
            var mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', location.search);
            //take from top most
            if( window.hWin.HEURIST4.util.isempty(mapdocument) ){
                mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
                if(!(mapdocument>0)){
                    mapdocument = null;
                }
            }
            
            var with_mapdoc = !window.hWin.HEURIST4.util.isempty(mapdocument);

            if( with_mapdoc ){
                mapwdiget.mapping('getMapManager').toggleMapDocument( mapdocument ); //load map document
            }
        
            var request = window.hWin.HEURIST4.util.parseHeuristQuery(location.search );
            if( !window.hWin.HEURIST4.util.isempty(request['q']) ){
                //do not zoom to current search if mapdoc is defined - preserve viewport
                mapwdiget.mapping('addSearchResult', request, 'Current results', with_mapdoc);
            }
    }

    //
    // Add group headers to record viewer
    //
    function createRecordGroups(groups){

        var $group_container = $('div#div_public_data');
        var $data = $group_container.find('div[data-order]');

        var $g_ele = null, $g_header = null;
        var current_type = null;

        if(groups == null || $data.length < 0 || $group_container.length < 0){
            return;
        }else{
            $.each(groups, function(idx, group){

                var group_name = group[0];
                var order = group[1];

                var next_group = groups[Number(idx)+1];
                var key = (next_group == null) ? null : next_group[0];
                var next_order = (key == null) ? null : next_group[1];
                var $field_container = $('<fieldset>').attr('id', order);

                $.each($data, function(idx, detail){

                    var $detail = $(detail);
                    var detail_order = $detail.attr('data-order');
                    if(detail_order < order){ // detail belongs in previous group
                        return;
                    }else if(detail_order > order && (next_order == null || order < next_order)){
                        $detail.appendTo($field_container);
                    }else{ // detail belongs in next group
                        return false;
                    }
                });

                $('<h4>').attr('data-order', order).css({'margin': '5px 0px 2px 0px', 'font-size': '1.1em'}).text(group_name).appendTo($group_container);

                $field_container.appendTo($group_container);
            });
        }
    }
</script>

</head>

<!-- HTML -->

<body>
    <div id="mapping" style="min-height:1px;height:100%; width:100%;cursor:progress">
        <!-- Map -->
        <div class="ui-layout-center">
                <div id="map" style="width:100%; height:100%" class="ui-layout-content"><span id="map-loading">Mapping...</span></div>
                <div id="map_empty_message" style="margin:7px;display: none;position: absolute;left: 50px;top: 0px;z-index: 1000;">There are no spatial objects to plot on map</div>
            
        </div>

        <!-- Toolbar -->
        <div class="ui-layout-north ui-heurist-explore" id="mapToolbarDiv" style="display: block; height: 30px; z-index:999;">
            <div id="mapToolbarContentDiv">

                <span style="font-size: small;cursor: pointer;margin-top:5px">
                    Map 
                    <select id="mapDocumentSel"></select>
                </span>

                <span style="display:inline-block;margin-left: 20px;font-size: small;">
                    New <a id="btn_add_mapdoc" style="width: 22px; height:22px;top:-1px;padding:0px;display:inline-block;margin-left: 5px;"></a>
                </span>

                <button id="btn_layout_map" class="ui-heurist-button" style="margin-top:5px;height: 24px;display:inline-block;margin-left: 10px;">Map</button>
                <button id="btn_layout_timeline" class="ui-heurist-button" style="margin-top:5px;height: 24px;display:inline-block;">Timeline</button>

                <a class="ui-icon ui-icon-plus" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-minus" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>

                <a class="ui-icon ui-icon-bookmark" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-search" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>


                <a class="ui-icon ui-icon-print" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-globe" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>
                <a class="ui-icon ui-icon-help" style="margin-top:5px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>

                <span style="display:inline-block;margin-left: 10px;font-size: small;margin-top:5px">
                    Legend <a class="ui-icon ui-icon-list" style="width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 5px;"></a>
                </span>

                <a id="btn_digitizing" class="ui-icon ui-icon-fullscreen-off" style="min-width:0px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>                
            </div>
        </div>

        <!-- Timeline -->
        <div class="ui-layout-south">
            <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
            <div id="timeline_toolbar" style="position:absolute;top:1;left:1;height:20px;"></div>
        </div>
    </div>
    
    <div id="timeline-edit-dialog"  style="display:none" class="ui-heurist-bg-light">
        
            <div style="padding:5px">
                <label><input type="radio" name="time-label" checked value="0">Full length labels</label><br>
                <label><input type="radio" name="time-label" value="1">Truncate label to bar</label><br>
                <label><input type="radio" name="time-label" value="2">Fixed label width</label><br>
                <label><input type="radio" name="time-label" value="3">Hide labels</label>
            </div>
            
            <div style="padding:5px">
                <label><input type="radio" name="time-label-pos" checked value="0">Label within the bar</label><br>
                <!-- <label><input type="radio" name="time-label-pos" value="1">Label to the right of the bar</label><br> -->
                <label><input type="radio" name="time-label-pos" value="2">Label above the bar</label>
            </div>
        
            <div style="padding:5px">
                <label><input type="radio" name="time-label-stack" checked value="0">Bars stacked on the above the other</label><br>
                <label><input type="radio" name="time-label-stack" value="1">Bars wrapped to minimise height of timeline</label>
            </div>

            <div style="padding:5px">
                <label><input type="checkbox" name="time-filter-map" value="1">Filter map with current timeline range</label>
            </div>
        
    </div>
    
</body>
</html>
