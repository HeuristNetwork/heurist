<?php

    /**
    *  Standalone record edit page. It may be used separately or wihin widget (in iframe)
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
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/rec_relation.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        
        <script type="text/javascript" src="recordEdit.js"></script>

        <script type="text/javascript">
            var editing;
            
            // Callback function on initialization
            function onPageInit(success){
                if(success){
                    var $container = $("<div>").appendTo($("body"));
                    editing = new hEditing({container:$container});
                    
                    //@todo take from HAPI parameters
                    var q = window.hWin.HEURIST4.util.getUrlParameter('q', window.location.search);
                    //t:26 f:85:3313  f:1:building
                    if( q )
                    {
                        window.hWin.HAPI4.RecordMgr.search({q: q, w: "all", f:"structure", l:1},
                            function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                    var recset = new hRecordSet(response.data);
                                    editing.load(recset);

                                }else{
                                    alert(response.message);
                                }
                            }
                        );

                    }else{ //add new record

                        var rt = window.hWin.HEURIST4.util.getUrlParameter('rt', window.location.search);

                        window.hWin.HAPI4.RecordMgr.add( {rt:rt, temp:1}, //ro - owner,  rv - visibility
                            function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                    var recset = new hRecordSet(response.data);
                                    editing.load(recset);

                                }else{
                                    alert(response.message);
                                }
                            }

                        );


                    }
                        
                }
            }            
        </script>
    </head>
    <body>
    </body>
</html>
