<?php

    /**
    * Search and select record. It is used in relationship editor and pointer selector
    * 
    * It is combination of generic record search by title (maybe it should be separated into widget) abd resultList widget
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecord.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecord.js"></script>
        
        <script type="text/javascript">
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    
                    var ispopup = true;
                    var select_mode = 'multi'; //'single',
                    
                    var rectype_set = top.HEURIST4.util.getUrlParameter('rectype_set',window.location.search);
                    var options = {
                                rectype_set: rectype_set,
                                select_mode: 'multi', //'single',
                                onselect:function(event, selection){
                                    if(selection && selection.isA('hRecordSet')){
                                       // alert( selection.getIds().join(',') );
                                       top.HAPI4.save_pref('recent_Records', selection.getIds(25), 25);      
                                    }
                                }
                            };
                    
                if(ispopup){
                       // in popup
                       showManageRecord( options ); 
                        
                    }else{
                        //within page
                        $('#main_div').manageRecord( options );

                    }
                    
                }
            }
        </script>
    </head>

    <!-- HTML -->
    <body>
        <div id="main_div" style="width:100%;height:100%">
        </div>
    </body>
</html>
