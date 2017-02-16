<?php

    /**
    * springDiagram.php: Renders search resultset as a network diagram. Uses hclient/visualize/visualize.js
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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

if(!defined('PDIR')) define('PDIR','../../../');
require_once(dirname(__FILE__)."/../initPage.php");

?>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>common/css/global.css">
        <style>
            body, html {
                background-color: #fff;
            }
        </style>

        <!-- D3 -->
        <script type="text/javascript" src="<?php echo PDIR;?>ext/d3/d3.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/d3/fisheye.js"></script>

        <!-- Colpick -->
        <script type="text/javascript" src="<?php echo PDIR;?>ext/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>ext/colpick/colpick.css">

        <!-- Visualize plugin -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/settings.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/overlay.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/selection.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/gephi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/drag.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.css">
    </head>

    <body>
        <!-- Visualize HTML -->
        <?php include "visualize.html"; ?>

        <!-- Call from parent iframe -->
        <script>
            /** Shows data visually */
            var limit = 2000; // TO DO : remove. this does not appear to be used, there is a limit variable in visualize.js which IS used

            function showSelection( selectedRecordsIds ){
                 visualizeSelection( selectedRecordsIds );
            }

            function showData(data, selectedRecordsIds, onSelectEvent, onRefreshData) {
                // Processing...
                if(data && data.nodes && data.links)
                console.log("showData called inside springDiagram nodes:"+data.nodes.length+'  edges:'+data.links.length);
                $("#d3svg").html('<text x="25" y="25" fill="black">Buiding graph ...</text>');

                // Custom data parsing
                function getData(data) {
                    //console.log("Custom getData() call");
                    return data;
                }

                // Calculates the line length
                function getLineLength(record) {
                    var length = getSetting(setting_linelength);
                    if(record !== undefined && record.hasOwnProperty("depth")) {
                        length = length / (record.depth+1);
                    }
                    return length;
                }

                $("#visualize").visualize({
                    data: data,
                    getData: function(data) { return getData(data); },
                    getLineLength: function(record) { return getLineLength(record); },

                    selectedNodeIds: selectedRecordsIds,   //assign current selection
                    triggerSelection: onSelectEvent,
                    onRefreshData: onRefreshData,
                    /*function(selection){
                        //parentDocument    top.window.document
                        $(parentDocument).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, { selection:selection, source:'d3svg' } ); //this.element.attr('id')} );
                    },*/

                    entityradius: 1,
                    linewidth: 1,

                    showCounts: false,
                    showEntitySettings: false,
                    showFormula: false
                });
            }

        </script>
    </body>

</html>