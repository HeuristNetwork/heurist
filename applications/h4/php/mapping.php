<?php

    /** 
    * Standalone mapping page (for development purposes
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


    require_once(dirname(__FILE__)."/System.php");

    $system = new System();

    if(@$_REQUEST['db']){
        if(! $system->init(@$_REQUEST['db']) ){
            //@todo - redirect to error page
            print_r($system->getError(),true);
            exit();
        }
    }else{
        header('Location: php/databases.php');
        exit();
    }
?>

<html>
    <head>
        <title><?=HEURIST_TITLE ?></title>

        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style.css">


        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>


        <script type="text/javascript" src="../ext/layout/jquery.layout-latest.js"></script>


        <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
        <!-- script type="text/javascript">
        Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";
        </script -->
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/timeline-1.2.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/timemap.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/param.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/xml.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/kml.js"></script>

        <script type="text/javascript" src="../js/mapping.js"></script>
        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>


        <script type="text/javascript">

            var mapping;

            $(document).ready(function() {

                var layout_opts =  {
                    applyDefaultStyles: true,
                    togglerContent_open:    '<div class="ui-icon"></div>',
                    togglerContent_closed:  '<div class="ui-icon"></div>'
                };


                layout_opts.center__minHeight = 300;
                layout_opts.center__minWidth = 200;
                layout_opts.north__size = 30;
                layout_opts.north__spacing_open = 0;
                layout_opts.south__size = 200;
                layout_opts.south__spacing_open = 7;
                layout_opts.south__spacing_closed = 7;


                $('#mapping').layout(layout_opts);

                var mapdata = [];

                if(!top.HAPI4){
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>');//, <?=json_encode($system->getCurrentUser())?> );
                }

                mapping = new hMapping("map", "timeline", top.HAPI4.basePath);

                var q = '<?=@$_REQUEST['q']?>';

                //t:26 f:85:3313  f:1:building
                if( q )
                {
                    top.HAPI4.RecordMgr.search({q: q, w: "all", f:"map", l:200},
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){

                                var recset = new hRecordSet(response.data);
                                mapping.load(recset.toTimemap());

                            }else{
                                alert(response.message);
                            }
                        }

                    );
                    //}else{
                    //    mapping.load(null);
                }

                //height:100%;

            });
        </script>

    </head>
    <body>

        <div id="mapping" style="{height:100%;width:100%;}">
            <div class="ui-layout-center"><div id="map" style="width:100%;height:100%">Mapping</div></div>
            <div class="ui-layout-north">Toolbar</div>
            <div class="ui-layout-south"><div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div></div>
        </div>

    </body>
</html>