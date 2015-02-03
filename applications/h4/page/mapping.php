<?php

    /** 
    * Standalone mapping page (for development purposes )
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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

    // System manager
    require_once(dirname(__FILE__)."/../php/System.php"); 
    $system = new System();

    // Database check
    if(@$_REQUEST['db']){
        if(! $system->init(@$_REQUEST['db']) ){
            //@todo - redirect to error page
            print_r($system->getError(),true);
            exit();
        }
    }else{
        header('Location: /../php/databases.php');
        exit();
    }
?>

<html>
    <!-- HEAD -->
    <head>
        <title><?=HEURIST_TITLE ?></title>

        <link rel=icon href="../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../favicon.ico" type="image/x-icon">

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <!-- Styles -->
        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style3.css">

        <!-- jQuery -->
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <script type="text/javascript" src="../ext/layout/jquery.layout-latest.js"></script>

        <!-- Timemap -->
        <!-- <script type="text/javascript">Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";</script -->
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/timeline-2.3.0.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/timemap.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/param.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/xml.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/kml.js"></script>     
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/manipulation.js"></script>     
        
        <!-- Shape file converting -->
        <script type="text/javascript" src="../ext/shapefile/stream.js"></script> 
        <script type="text/javascript" src="../ext/shapefile/shapefile.js"></script> 
        <script type="text/javascript" src="../ext/shapefile/dbf.js"></script>

        <!-- Mapping -->
        <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>
        <!-- <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script> -->
        <script type="text/javascript" src="../js/mapping.js"></script>
        <script type="text/javascript" src="../js/map_overlay.js"></script>
        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>

        <!-- Initializing -->
        <script type="text/javascript">
        
            var mapping;

            // Standalone check
            $(document).ready(function() {
                if(!top.HAPI4){
                    // In case of standalone page
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>', onHapiInit);//, < ?=json_encode($system->getCurrentUser())? > );
                }else{
                    // Not standalone, use HAPI from parent window
                    onHapiInit(true);
                }
            });
            
            // Callback function on hAPI initialization
            function onHapiInit(success)
            {
                if(success) // Successfully initialized system
                {
                    if(!top.HR){
                        var prefs = top.HAPI4.get_prefs();
                        //loads localization
                        top.HR = top.HAPI4.setLocale(prefs['layout_language']); 
                    }
                    if(!top.HEURIST4.rectypes){
                        top.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                top.HEURIST4.rectypes = response.data.rectypes;
                                top.HEURIST4.terms = response.data.terms;
                                top.HEURIST4.detailtypes = response.data.detailtypes;
                                
                                onMapInit();
                            }else{
                                top.HEURIST4.util.showMsgErr('Can not obtain database definitions');
                            }
                        });
                    }else{
                        onMapInit();
                    }
                    
                }else{
                    top.HEURIST4.util.showMsgErr('Can not init HAPI');    
                }
            }
            
            // Callback function on map initialization 
            function onMapInit(){
                // Layout options
                var layout_opts =  {
                    applyDefaultStyles: true,
                    togglerContent_open:    '<div class="ui-icon"></div>',
                    togglerContent_closed:  '<div class="ui-icon"></div>'
                };
  
                // Setting layout
                layout_opts.center__minHeight = 300;
                layout_opts.center__minWidth = 200;
                layout_opts.north__size = 30;
                layout_opts.north__spacing_open = 0;
                layout_opts.south__size = 200;
                layout_opts.south__spacing_open = 7;
                layout_opts.south__spacing_closed = 7;
                $('#mapping').layout(layout_opts);

                // Mapping data 
                var mapdata = [];
                mapping = new hMapping("map", "timeline", top.HAPI4.basePath);
                var q = '<?=@$_REQUEST['q']?$_REQUEST['q']:""?>';

                //t:26 f:85:3313  f:1:building
                // Perform database query if possible
                if( !top.HEURIST4.util.isempty(q) )
                {
                    top.HAPI4.RecordMgr.search({q: q, w: "all", f:"map", l:1000},
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                console.log("onMapInit response");
                                console.log(response);
                                
                                // Show info on map
                                var recset = new hRecordSet(response.data);
                                mapping.load(recset.toTimemap());

                            }else{
                                alert(response.message);
                            }
                        }
                    );                     
                }
                
                
                //init buttons
                $("#btnPrint").button({text:false, icons: {
                            primary: "ui-icon-print"
                 }})
                 .click(mapping.printMap);

                $("#btnEmbed").button({text:false, icons: {
                            primary: "ui-icon-gear"
                 }})
                 .click(showEmbedDialog);
                
            }
            
            function showEmbedDialog(){
                
                 var query = top.HEURIST4.util.composeHeuristQuery2(top.HEURIST4.current_query_request);
                 query = query + ((query=='?')?'':'&') + 'db='+top.HAPI4.database;
                 var url = top.HAPI4.basePath+'page/mapping.php' + query;

                 //document.getElementById("linkTimeline").href = url;

                 document.getElementById("code-textbox").value = '<iframe src="' + url +
                                             '" width="800" height="650" frameborder="0"></iframe>';

                 //var url_kml = top.HAPI4.basePathOld+"export/xml/kml.php?" + query;

                 //document.getElementById("linkKml").href = url_kml;
                 
                 var $dlg = $("#embed-dialog");
                 
                 $dlg.dialog({
                    autoOpen: true,
                    height: 240,
                    width: 700,
                    modal: true,
                    resizable: false,
                    title: top.HR('Publish Map')
                 });
            }
            
            
        </script>

    </head>
    
    <!-- HTML -->
    <body>
        <div id="mapping" style="height:100%; width:100%;">
            <!-- Map -->
            <div class="ui-layout-center">
                <div id="map" style="width:100%; height:100%">Mapping</div>
            </div>
            
            <!-- Toolbar -->
            <div class="ui-layout-north" id="toolbar" style="display: block !important; height: 30px important; z-index:999">
                <!-- Map document selector -->
                <i>Map document:</i>
                <select id="map-doc-select">
                    <option value="-1" selected="selected" disabled="disabled">None</option>
                </select>
                
                <button id="btnPrint" style="position: fixed; right: 40">Print</button>
                <button id="btnEmbed" style="position: fixed; right: 10">Embed</button>
                
                
                <!-- Menu -->
                <!--<select id="menu" style="position: fixed; right: 10">
                    <option value="-1" selected="selected" disabled="disabled">Menu</option>
                    <option>Google Map/Timeline</option>
                    <option>Google Earth</option>
                    <option>Embed Map Code</option>
                </select>-->
                
                <!-- Legend -->
                <div id="legend" style="background-color: rgba(0, 0, 0, 0.7); color:#eee; padding:8px">
                    <span style="font-size: 1.25em">Legend</span>
                    <span id="collapse" style="font-size: 1.25em; float:right; padding: 0px 5px; cursor: pointer">-</span>
                    <div class="content"></div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="ui-layout-south">
                <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
            </div> 
        </div>
        <div id="embed-dialog" style="display:none">
            <p>Embed this Google Map (plus timeline) in your own web page: Copy the following html code into your page where you want to place the map, or use the URL on its own. The map will be generated live from the database using the current search criteria whenever the map is loaded. </p>
            <textarea id="code-textbox" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 60px;" readonly=""></textarea>
            <p>Note: records will only appear on the map if they include geographic objects. You may get an empty or sparsely populated map if the search results do not contain map data. Records must have public status. </p>
        </div>
    </body>
</html>