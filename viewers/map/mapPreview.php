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
<script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/leaflet-providers.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
        
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>

<script type="text/javascript">

    var mapping, initial_layers, target_database, tlcmap_snapshot=null;

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
        $(window).on("beforeunload",  function() { 
console.log('beforeunload MAPPEVIEW');
            return;
        });
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
            layout_params:layout_params
            //oninit: onMapInit
        });                
        
        //initialize buttons
        $('#save-button').button().on({click:function()
        {
            _exportMapSpace();
        }});
        
    }
    
    function onFirstInit(){
        onMapInit();
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
        
        if(!target_database){
            window.hWin.HEURIST4.msg.showMsgErr('Target database not defined. '
                +'It is not possiblle to perform this operation');
            window.close(); 
        }
        

        if(initial_layers){ //create virtual mapspace
        
            //mapping.mapping( 'drawLoadWKT', initial_wkt, true);
            var dfd = new $.Deferred();            
            mapping.mapping('getMapManager').createVirtualMapDocument( initial_layers, dfd );
            
            //create map snapshot as soon as map is loaded
            $.when( dfd.promise() ).done(
                function(data){
                    //remove redundant nodes to create snapshot
                    function filterNode(node) {
                        if (node instanceof Text) {
                            return true;
                        }
                        return [
                            "div",
                            "span",
                            "p",
                            "i",
                            "img",
                            "svg",
                            "g",
                            "path"
                        ].includes(node.tagName.toLowerCase()) || /^h[123456]$/i.test(node.tagName);
                    }
                    setTimeout(function(){  
                        try{
                            domtoimage
                            .toPng(document.getElementById('map_container'),{
                                filter: filterNode
                            })
                            .then(function (dataUrl) {
                                //dataUrl - base64 upload to server and register 
                                tlcmap_snapshot = dataUrl;
                            });                                
                        }catch(e){
                        }

                        }, 2000);                    
                }
            );
        }
        
        //
        //
        //        
        if(target_database!=window.hWin.HAPI4.database){
            setTimeout(function(){
                //load check login iframe
                var ele = $('#checklogin');
                ele.attr('src', null);
                ele.attr('src', window.hWin.HAPI4.baseURL
                    +'hclient/framecontent/initPageLogin.php?db='+target_database);
            },500);
        }
        
    }
            
            
    //
    // export layers and datasource to target database
    //
    function _exportMapSpace(){
        
            if(!window.hWin.HEURIST4.msg.checkLength($('#mapspace_name'),'','Define name of map',3,120)){
                return;
            }
            
            //get all layers and datasources of document
            var recordset = mapping.mapping( 'getMapManager' ).getMapDocumentRecordset( 'temp' );
            if(recordset==null || recordset.length()==0){
                window.hWin.HEURIST4.msg.showMsgFlash('Temp mapspace is empty');
                return;    
            }

            var RT_MAP_DOCUMENT    = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'];
            var RT_MAP_LAYER       = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'];
            var RT_TLCMAP_DATASET  = window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'];
            
            //1. check that all layers and datasource records are public
            //2. calculate 
            var not_public = [], cnt_dt = 0, cnt_ds = 0;
            var idx, records = recordset.getRecords();
            
            var native_ids = [];  //leaflet ids
            var heurist_ids = []; //heurist layer ids in mapdoc
            
            for(idx in records){
                if(idx)
                {
                    var record = records[idx];
                    var recType = recordset.fld(record, 'rec_RecTypeID');
                    if(recordset.fld(record, 'rec_NonOwnerVisibility')!='public'){
                        
                        var recName = recordset.fld(record, 'rec_Title');
                        
                        if(recType==RT_MAP_LAYER || recType==RT_TLCMAP_DATASET){
                            recType = 'dataset';
                            cnt_dt++;
                        }else{
                            recType = 'datasource';
                            cnt_ds++;
                        }
                        
                        not_public.push(recName+' ('+recType+')');
                        
                    }else if((recType==RT_MAP_LAYER || recType==RT_TLCMAP_DATASET) && record['layer']){
                        heurist_ids.push(recordset.fld(record, 'rec_ID'));    
                        native_ids.push((record['layer']).getNativeId());    
                    }
                }
            }
            if(not_public.length>0){
                
                var is_sglr = (not_public.length==1);
                
                var sMsg = '<p>The following '
                +((cnt_dt>0)?('dataset registration'+(cnt_dt>1?'s':'')):'') 
                + ((cnt_dt>0 && cnt_ds>0)?' and ':'')
                +((cnt_ds>0)?('data source record'+(cnt_ds>1?'s':'')):'') 
                +(is_sglr?' is ':' are ')
+' not marked as publicly visible and cannot therefore be included in your saved map. '
+(is_sglr?'It is':'They are')+' visible to you as either the owner or because the owner has made '
+(is_sglr?'it':'them')+' visible to logged-in users.</p><p>'
+ not_public.join('<br>')
+'</p><p>Please remove '+(is_sglr?'this dataset':'these datasets')
+' from your map and try saving the map again.</p>'
+'Please ask the dataset owner(s) to make '+(is_sglr?'this data source':'these data sources')+' publicly visible. '
+'If you do not know the owner, please advise the system administrator ('
+'<a href="mailto:'+window.hWin.HAPI4.sysinfo.dbowner_email+'">'+window.hWin.HAPI4.sysinfo.dbowner_email+'</a>)</p>';
                
                window.hWin.HEURIST4.msg.showMsgErr( sMsg );
                return;
            }
            
            var mapdoc_name = $('#mapspace_name').val()
            
            //if target and source databases are the same - jusst create new mapdocument
            if(target_database==window.hWin.HAPI4.database){

                var layer_cnt = heurist_ids.length;
                
                var DT_NAME          = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
                var DT_MAP_BOOKMARK  = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_BOOKMARK'];
                var DT_ZOOM_KM_POINT = window.hWin.HAPI4.sysinfo['dbconst']['DT_ZOOM_KM_POINT'];
                var DT_GEO_OBJECT    = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
                var DT_MAP_LAYER     = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_LAYER'];
                
                var bounds = mapping.mapping( 'getBounds', native_ids);

                var mbookmark = 'Extent,'+bounds.getSouth()+','+bounds.getWest()
                         +','+bounds.getNorth()+','+bounds.getEast()+',1800,2050';
            
                var mbox = [bounds.getWest()+' '+bounds.getSouth(),
                            bounds.getWest()+' '+bounds.getNorth(),
                            bounds.getEast()+' '+bounds.getNorth(),
                            bounds.getEast()+' '+bounds.getSouth(),
                            bounds.getWest()+' '+bounds.getSouth()];
                mbox = 'pl POLYGON(('+mbox.join(',')+'))';
                //{geo:{type:'pl',wkt:'POLYGON(('+mbox.join(',')+'))'}};
                
                var zoomKm = Math.round(bounds.getSouthWest().distanceTo(bounds.getNorthEast())/10000); //100000
                
                var details = {};
                details['t:'+DT_NAME] = [ mapdoc_name ];
                details['t:'+DT_MAP_BOOKMARK]  = [ mbookmark ];
                details['t:'+DT_ZOOM_KM_POINT] = [ zoomKm ];
                details['t:'+DT_GEO_OBJECT]    = [ mbox ];
                details['t:'+DT_MAP_LAYER]     = heurist_ids;

                
                var request = {a: 'save', 
                    db: window.hWin.HAPI4.database,
                    ID:0, //new record
                    RecTypeID: RT_MAP_DOCUMENT,
                    RecTitle: mapdoc_name,
                    details: details};

                window.hWin.HAPI4.RecordMgr.saveRecord(request, 
                    function(response){
                        var  success = (response.status == window.hWin.ResponseStatus.OK);
                        if(success){
                            
                            var sMsg = '<br><p>'
    +' Created 1 map document with '+layer_cnt+' map layers.</p>'                       
    +'<p>Please go to <b>My Maps</b> to edit the styling, to obtain the URL,'
    +' or to obtain a snippet of html which will allow you to embed this map in an external website</p>';
                            
                            window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 'Map saved');
                            window.close();
                                                        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
                
                return;
            }//same database
            

            //$('#divStep2').hide();
            var session_id = Math.round((new Date()).getTime()/1000);  //for progress
        
            var request = { 
                source_db: window.hWin.HAPI4.database,
                db: target_database,
                ids: recordset.getIds(),  //layers and datasource
                tlcmapspace: mapdoc_name, //name of mapdocument
                tlcmapshot: tlcmap_snapshot, //base64 encoded image to be saved as mapdocument thumb
                action: 'import_records',
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
                   
            window.hWin.HAPI4.doImportAction(request, function( response ){
                
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        var cnt = response.data.count_imported-1;
                        if(cnt % 2 > 0){

                            window.hWin.HEURIST4.msg.showMsgDlg('It appears that some of datasource records are not public and hence they are not exported. Please make sure that all datasets and datasources are public and repeat this operation. Do not forget to remove wrong mapspace', null, 'Error');

                        }else{
                            
                            cnt = cnt/2;
                            //response.data.count_imported
                            var sMsg = '<br><p>'
    +' Exported 1 map document,'+cnt+' map layers, '+cnt+' datasets.</p>'                       
    +'<p>Please go to <b>My Maps</b> to edit the styling, to obtain the URL,'
    +' or to obtain a snippet of html which will allow you to embed this map in an external website</p>';
                            
                            window.hWin.HEURIST4.msg.showMsgDlg(sMsg,null, 'Map saved');
                            window.close();
                        }
                    }else{
                        if(response && response.status==window.hWin.ResponseStatus.REQUEST_DENIED){
                            var ele = $('#checklogin');
                            ele[0].contentWindow.verify_credentials();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                                
                    }
                });
    } //_exportMapSpace
    
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
                <div style="padding:6px;display:inline-block">
                    <label>Map title</label>
                    <input size="60" id="mapspace_name"/>
                    <button id="save-button">Save Map</button>
                </div>
                <iframe id="checklogin" style="width:10px !important; height:10px !important"></iframe>
            </div>
            <div class="ent_content_full" id="map_container" style="top:60px">
                <div id="map_digitizer"></div>
            </div>
        </div>
    </body>
</html>
