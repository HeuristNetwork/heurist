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
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecord.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecord.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysUsers.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysUsers.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysGroups.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefRecTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypeGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefRecTypeGroups.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefDetailTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefDetailTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefDetailTypeGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefDetailTypeGroups.js"></script>

        
        <script type="text/javascript">
        
            var systemEntiries= 
                           [{"key":"Records",title:"Records"},
                            {"key":"SysUsers",title:"Users",icon:'ui-icon-person'},
                            {"key":"SysGroups",title:"Workgroups",icon:'ui-icon-group'},
                            {"key":"Tags",title:"Tags",icon:'ui-icon-tag'},
                            {"key":"RecUploadedFiles",title:"Uploaded Files",icon:'ui-icon-image'},
                            {"key":"Reminders",title:"Reminders",icon:'ui-icon-mail-closed'},
                            {"key":"Databases",title:"Databases",icon:'ui-icon-database'},
                            {"key":"Records",title:"Saved Searches",icon:'ui-icon-search'},
                            {"key":"DefRecTypes",title:"Record Types",icon:'ui-icon-image'},
                            {"key":"DefRecTypeGroups",title:"Record Type Groups"},
                            {"key":"DefDetailTypes",title:"Field Types"},
                            {"key":"DefDetailTypeGroups",title:"Field Type Groups"},
                            {"key":"DefTerms",title:"Terms"},
                            {"key":"RecComments",title:"Record Comments"},
                            {"key":"Smarty",title:"Smarty Reports"},
                            {"key":"SmartySchedule",title:"Smarty Reports Schedule"}];        
        
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    
                    //testUsers();
                    //testUsers();
                    var editing = hEditing('main_div');
                    editing.initEditForm(defDetailTypes);
                    
                    window.hWin.HEURIST4.ui.initHintButton($('#btn_help_hints'));
                    
                    var isOnTop = false;
                    $('#btn_style').button().on({click:function(e){
                        isOnTop = !isOnTop;
                        if(isOnTop){
                            $('fieldset').addClass('ontop');
                        }else{
                            $('fieldset').removeClass('ontop');
                        }
                    }  });
                    
                    
                }
            }
            
     
//----------------------------------
                        //rst_PtrFilteredIDs:$('#input_rectypes').val(),
                        //rst_FilteredJsonTermIDTree:$('#input_term').val(),  //configuration  rst_FilteredJsonTermIDTree
        </script>
    </head>

    <!-- HTML -->
    <body>
            
            
            
        <div id="main_div" style="height:700;width:700;border:1px solid">
        </div>

        <div id="btn_help_hints" style="position:absolute;top:4px;right:4px;"></div>        
        <div id="btn_style" style="position:absolute;top:4px;right:34px;">style</div>        
    </body>
</html>
