<?php

    /**
    * Preview selected layers as mapspace
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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

define('PDIR','../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../hclient/framecontent/initPage.php');
?>
        <!-- script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCan9ZqKPnKXuzdb2-pmES_FVW2XerN-eE&libraries=drawing,geometry"></script -->

<?php
if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/leaflet.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancytree/jquery.fancytree-all.min.js"></script>
<?php
}else{
?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css"
       integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA=="
       crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js"
       integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg=="
       crossorigin=""></script>   
    <!-- link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" /-->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.16.1/jquery.fancytree-all.min.js"></script>
<?php
}
?>
<!-- leaflet plugins -->
<script src="<?php echo PDIR;?>external/leaflet/leaflet-providers.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
        
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>

<script type="text/javascript">

    var mapping, initial_layers, target_database;

    // Callback function on map initialization
    function onPageInit(success){
        
        if(!success) return;

        /* init helper (see utils.js)
        window.hWin.HEURIST4.ui.initHelper( $('#btn_help'), 
                    'Mapping Drawing Overview', 
                    '../../context_help/mapping_drawing.html #content');
        */            

        handleApiReady();
/*
        if (typeof window.hWin.google === 'object' && typeof window.hWin.google.maps === 'object') {
console.log('google map api: already loaded')                    
            handleApiReady();
        }else{                            
console.log('load google map api')                    
            $.getScript('https://maps.googleapis.com/maps/api/js?key=AIzaSyDtYPxWrA7CP50Gr9LKu_2F08M6eI8cVjk'
            +'&libraries=drawing,geometry&callback=handleApiReady');                                           
            //AIzaSyCan9ZqKPnKXuzdb2-pmES_FVW2XerN-eE
        }
*/
    } //onPageInit
    
    function handleApiReady(){
 
        
        
        var layout_params = {};
        layout_params['notimeline'] = '1';
        layout_params['nocluster'] = '1'
    
        layout_params['controls'] = 'legend';//',bookmark,geocoder,draw';
        layout_params['legend'] = 'basemaps';//',mapdocs';
        layout_params['published'] = 1;//'1';
        
        initial_layers = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);
        target_database = window.hWin.HEURIST4.util.getUrlParameter('target_db', location.search);

        mapping = $('#map_container').mapping({
            element_map: '#map_digitizer',
            layout_params:layout_params,
            oninit: onMapInit
        });                
        
        //initialize buttons
        $('#save-button').button().on({click:function()
        {
            _exportMapSpace();
        }});
    }
    
    //
    // called from showDialog
    //
    function assignParameters(params){
        
        if(params && params['ids']){
            initial_layers = params['ids'];
            if(params['target_db']){
               target_database = params['target_db']; 
            }
        }else{
            initial_layers = null;
        }
        onMapInit();
    } 
    
    //
    //
    //           
    function onMapInit(){
        if(initial_layers){ //create virtual mapspace
            //mapping.mapping( 'drawLoadWKT', initial_wkt, true);
            mapping.mapping( 'createVirtualMapDocument', initial_layers);
        }
    }
            
            
    //
    // export layers and datasource to target database
    //
    function _exportMapSpace(){
        
            if(!window.hWin.HEURIST4.msg.checkLength($('#mapspace_name'),'','Define name of map',3,120)){
                return;
            }
            
            var recordset = mapping.mapping( 'getMapDocumentRecordset', 'temp');
            if(recordset==null || recordset.length()==0){
                window.hWin.HEURIST4.msg.showMsgFlash('Temp mapspace is empty');
                return;    
            }
            
            //check login t target database            
            

            //$('#divStep2').hide();
            var session_id = Math.round((new Date()).getTime()/1000);  //for progress
        
            var request = { 
                source_db: window.hWin.HAPI4.database,
                db: target_database,
                ids: recordset.getIds(),
                tlcmapspace: $('#mapspace_name').val(),
                action: 'import_records',
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
            //todo _showProgress( session_id, 2 );
                   
            window.hWin.HAPI4.doImportAction(request, function( response ){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        //_hideProgress(3);
                        
                        //response.data.count_imported
                        var sMsg = 'Map saved. Exported '+response.data.count_imported
+' records. "Please go to My Maps" &gt; to edit the styling, to obtain the URL,'
+'or to obtain a snippet of html which will allow you to embed this map in an external website';
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                    }else{
                        //_hideProgress(2);
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
    }        

        </script>
        <style type="text/css">
            #map_digitizer {
                height:100%;
                width:100%;
            }
        </style>

    </head>

    <!-- HTML -->
    <body style="overflow:hidden">
        <div class="ent_wrapper">
            <div class="ent_header" style="height:60px">
                <div class="heurist-helper1" style="font-size:0.8em">
                    To save this map for future access or embedding in websites, enter a title and click Save Map. We suggest using a concise but informative title. The map layers and style can be edited later.
                </div>
                <div style="padding:6px">
                    <label>Map title</label>
                    <input size="60" id="mapspace_name"/>
                    <button id="save-button">Save Map</button>
                </div>
            </div>
            <div class="ent_content_full" id="map_container" style="top:60px">
                <div id="map_digitizer"></div>
            </div>
        </div>
    </body>
</html>
