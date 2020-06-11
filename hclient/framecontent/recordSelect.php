<?php

    /**
    * Search and select record. It uses manageRecords
    * 
    * It is combination of generic record search by title (maybe it should be separated into widget) abd resultList widget
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

require_once(dirname(__FILE__)."/initPage.php");
?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.layout/jquery.layout-latest.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>

        
        <script type="text/javascript">
            // Callback function on page initialization
            function onPageInit(success){
                if(success){
                    var $container = $("<div>").appendTo($("body"));

                    var rectype_set = window.hWin.HEURIST4.util.getUrlParameter('rectype_set', window.location.search);
                    var parententity = window.hWin.HEURIST4.util.getUrlParameter('parententity', window.location.search);
                    
                    var options = {
                        select_mode: 'select_single',
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        rectype_set: rectype_set,
                        parententity: parententity,
                        onselect:function(event, data){
                            
                            if(data && data.selection && window.hWin.HEURIST4.util.isRecordSet(data.selection)){
                                window.close(data.selection);
                            }
                        }                        
                    }
                    
                    $container.manageRecords( options );
                }
            }            
        </script>
    </head>

    <!-- HTML -->
    <body>
    </body>
</html>
