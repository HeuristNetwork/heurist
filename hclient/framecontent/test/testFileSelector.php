<?php

    /**
    * Search and select record. It is used in relationship editor and pointer selector
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

define('PDIR','../../../');    
    
require_once(dirname(__FILE__)."/../initPage.php");
?>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
        
        <script type="text/javascript">
            // Callback function on page initialization
            function onPageInit(success){
                if(success){
                   showFileSelectPopup(false);
                }
            }
            
            function showFileSelectPopup(ispopup){

                    var options = {
                        select_mode: 'select_single',
                        selectbutton_label: 'Select',
                        pagesize: 100,
                        use_cache: false,
                        list_header: false,
                        edit_mode: 'none',
                        edit_dialog:false,
                        height: 840,
                        width: 600,
                        onselect:function(event, data){
console.log('onselect');                            
                            if(data && data.selection)
                            for(i in data.selection){
                                if(i>=0){
                                    var fileid = data.selection[i];
                         
                         
                $.ajax( {
                    url: window.hWin.HAPI4.baseURL + 'records/files/getFileInfo.php?db='+window.hWin.HAPI4.database+'&ulf_ID='+fileid,
                    //+'&DBGSESSID=423997564615200001;d=1,p=0,c=0',
                    })
                    .error(function() {
                    })
                    .success(function(response, textStatus, jqXHR){
                        window.close(response);
                    });                                      
                                    
                                }
                            }
                        }
                    };
                
                    if(ispopup){
                        showManageRecUploadedFiles( options );                          
                    }else{
                        $('#content').manageRecUploadedFiles( options )
                    }
                    
                
            }
        </script>
    </head>

    <!-- HTML -->
    <body>
        <div id="content" style="width:100%;height:100%">
        </div>
    </body>
</html>
