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

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysUsers.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysUsers.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysGroups.js"></script>
        
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
                    //multi_checkboxes: $('#multi_checkboxes').is(':checked'),
                    selectbutton_ontop: $('#multi_selectbutton_ontop').is(':checked'),
                    selectbutton_label: $('#multi_selectbutton_label').val(),
                    page_size: $('#page_size').val(),
                    action_select: $('#action_select').is(':checked'), //may be true,false or array
                    action_buttons: $('#action_buttons').is(':checked'), //may be true,false or array
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
                       }
                        
                    }else{
                        top.HEURIST4.msg.showMsgWorkInProgress();
                    }

                    
                }
                
                
                
            }
            
        </script>
    </head>

    <!-- HTML -->
    <body>
            <div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Entity:
                        <select id="entity-sel">
                            <option value="SysUsers">Users +</option>
                            <option value="SysGroups">Workgroups +</option>
                            <option value="Records">Records</option>
                            <option value="Tags">Tags</option>
                            <option value="RecUploadedFiles">Uploaded Files</option>
                            <option value="Reminders">Reminders</option>
                            <option value="Databases">Databases</option>
                            <option value="Records">Saved Searches</option>
                            <option value="DefRecTypes">Record Types</option>
                            <option value="DefRecTypeGroups">Record Type Groups</option>
                            <option value="DefDetailTypes">Field Types</option>
                            <option value="DefDetailTypeGroups">Field Type Groups</option>
                            <option value="DefTerms">Terms</option>
                            <option value="RecComments">Record Comments</option>
                            <option value="Smarty">Smarty Reports</option>
                            <option value="SmartySchedule">Smarty Reports Schedule</option>
                        </select></label>
                </div>
                
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <span style="padding-right:20px">
                        <label>Mode</label>
                        <label><input type="radio" name="select_mode" value="manager">Manager</label>
                        <label><input type="radio" name="select_mode" value="select_single">Select Single</label>
                        <label><input type="radio" name="select_mode" value="select_multi" checked>Select Multi</label>
                    </span>
                    <span style="padding-right:20px;visibility:hidden">
                        <label>Options for Multi selection:</label>
                        <label><input type="checkbox" id="multi_checkboxes">Show checkboxes (Keep selections)</label>
                    </span>
                    <span style="padding-right:20px;visibility: hidden;">
                        <label><input type="checkbox" id="multi_selectbutton_ontop">Show Top Select Buttton (for popup select_multi mode)</label>
                    </span>
                    <label>Select button label<input type="text" id="multi_selectbutton_label" value="Select"></label>
                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <span style="padding-right:20px">
                        <label><input type="checkbox" id="action_select">Action selector</label>
                        <label><input type="checkbox" id="action_buttons" checked>Action button(s)</label>
                    </span>
                    <span style="padding-right:20px;visibility: hidden;">
                        <label>Page size<input type="text" id="page_size" value="50" size="3"></label>
                    </span>
                    <label>Initial filter by name<input type="text" id="filter_title" value=""></label>
                    <label>CSV group/rec types<input type="text" id="filter_groups" value=""></label>

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
