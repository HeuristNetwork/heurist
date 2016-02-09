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
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    
                    //testUsers();
                    //testUsers();
                    
                }
            }
            
            function testRecords(){
                    var ispopup = true;
                    var select_mode = 'select_multi'; //'single',
                    
                    var rectype_set = top.HEURIST4.util.getUrlParameter('rectype_set',window.location.search);
                    var options = {
                                rectype_set: rectype_set,
                                select_mode: select_mode,
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

            //
            //
            //
            function testEntity(mode){
                
                var options = {
                    select_mode: $('input[name="select_mode"]:checked').val(),
                    selectbutton_label: $('#multi_selectbutton_label').val(),
                    page_size: $('#page_size').val(),
                    action_select: $('#action_select').is(':checked'), //may be true,false or array
                    action_buttons: $('#action_buttons').is(':checked'), //may be true,false or array
                    list_header: false,  //$('#list_header').is(':checked'), //may be true,false
                    filter_title: $('#filter_title').val(),
                    filter_group_selected:null,
                    filter_groups: $('#filter_groups').val(),
                    onselect:function(event, data){
                        
                        $('#selected_div').empty();
                        var s = 'Selected ';
                        if(data && data.selection)
                        for(i in data.selection){
                            if(i>=0)
                                s = s+data.selection[i]+'<br>';
                        }
                        $('#selected_div').html(s);
                    }
                }
                
                //onselect
                //isdialog
                
                var entity = $('#entity-sel').val();
                
                //it is possible to call function by its name stored in variable - however for debug aim we have to call it explicitely
                
                if(mode){//popup
                
                    var func_name = 'showManage'+entity;
                    
                    if($.isFunction(window[func_name])){
                        
                       if(entity=='SysUsers'){
                            showManageSysUsers( options );      
                       }else if(entity=='SysGroups'){
                            showManageSysGroups( options );      
                       }else if(entity=='DefRecTypes'){
                            showManageDefRecTypes( options );      
                       }else if(entity=='DefRecTypeGroups'){
                            showManageDefRecTypeGroups( options );      
                       }else if(entity=='DefDetailTypes'){
                            showManageDefDetailTypes( options );      
                       }else if(entity=='DefDetailTypeGroups'){
                            showManageDefDetailTypeGroups( options );      
                       }
                        
                    }else{
                        top.HEURIST4.msg.showMsgWorkInProgress();
                    }
                    
                }else{//on this page
                
                    var widgetname = 'manage'+entity,
                        $content = $('#main_div');

                    if($.isFunction($content[widgetname])){
                    
                       $content.empty();
                       //$content[widgetname]( options );   //call constructor
                       
                       if(entity=='SysUsers'){
                            $content.manageSysUsers( options );      
                       }else
                       if(entity=='SysGroups'){
                            $content.manageSysGroups( options );      
                       }else
                       if(entity=='DefRecTypes'){
                            $content.manageDefRecTypes( options );      
                       }else
                       if(entity=='DefRecTypeGroups'){
                            $content.manageDefRecTypeGroups( options );      
                       }else
                       if(entity=='DefDetailTypes'){
                            $content.manageDefDetailTypes( options );      
                       }else
                       if(entity=='DefDetailTypeGroups'){
                            $content.manageDefDetailTypeGroups( options );      
                       }
                        
                    }else{
                        top.HEURIST4.msg.showMsgWorkInProgress();
                    }

                    
                }
                
                
                
            }
            
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
            
            
        </script>
    </head>

    <!-- HTML -->
    <body>
            <div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Entity:
                        <select id="entity-sel">
                            <option value="Records">Records</option>
                            <option value="SysUsers">Users +</option>
                            <option value="SysGroups">Workgroups +</option>
                            <option value="Tags">Tags</option>
                            <option value="RecUploadedFiles">Uploaded Files</option>
                            <option value="Reminders">Reminders</option>
                            <option value="Databases">Databases</option>
                            <option value="Records">Saved Searches</option>
                            <option value="DefRecTypes">Record Types +</option>
                            <option value="DefRecTypeGroups">Record Type Groups +</option>
                            <option value="DefDetailTypes">Field Types +</option>
                            <option value="DefDetailTypeGroups">Field Type Groups +</option>
                            <option value="DefTerms">Terms</option>
                            <option value="RecComments">Record Comments</option>
                            <option value="Smarty">Smarty Reports</option>
                            <option value="SmartySchedule">Smarty Reports Schedule</option>
                        </select></label>
                </div>
                
                <div style="padding:5px; xborder-bottom:1px solid lightgrey">
                    <label>Selection Mode</label>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="manager">Manager</label> as it currently implemented: records selected with shift, ctrl. Selection is not retained among pages and searches. Selected records are not returned from dialog
                    </div>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="select_single">Select Single</label> only one selection is allowed. Search result event triggered each time user click on record. In popup mode dialog is closed on select.
                    </div>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="select_multi" checked>Select Multi</label> Selection persists between pages and selections. Select event is triggered on special button click.
                    </div>
                </div>                        
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Initial filter by name<input type="text" id="filter_title" value=""></label>
                    <label>CSV group/rec types<input type="text" id="filter_groups" value=""></label>
                    <label>Select button label<input type="text" id="multi_selectbutton_label" value="Select"></label>
                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <span style="padding-right:20px">
                        <label><input type="checkbox" id="action_select">Action selector (show action menu and action icons in list)</label>
                        <label><input type="checkbox" id="action_buttons" checked>Action buttons (show action buttons in header of result viewer - usually Add Button)</label>
                        <!-- <label><input type="checkbox" id="list_header" checked>Header in list view mode</label> -->
                    </span>
                    <span style="padding-right:20px;visibility: hidden;">
                        <label>Page size<input type="text" id="page_size" value="50" size="3"></label>
                    </span>

                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <button onclick="testEntity(true)">show in popup dialog</button>
                    <button onclick="testEntity(false)">show on this page</button>
                </div>
            </div>
        <div id="main_div" style="position:fixed;height:700;width:700;border:1px solid">
        </div>
        
        <div id="selected_div" style="float:right;height:700;width:200;border:1px solid">
        </div>
        
    </body>
</html>
