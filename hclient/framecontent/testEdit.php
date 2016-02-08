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
                    
                }
            }
            
            //
            //
            //
            function testEdit(){
                
                var options = {
                    dtID: $('#field-name').val(),

                    dtFields:{
                        dty_Type:        $('#datatype-sel').val(),
                        rst_DisplayName: $('#input_label').val(),
                        rst_DisplayHelpText: $('#input_help').val(), 
                        rst_DisplayExtendedDescription:$('#input_title').val(),  //popup info
                        rst_DisplayWidth:$('#input_width').val(),
                        rst_DefaultValue:$('#input_def').val(),
                        rst_RequirementType:$('#rst_RequirementType').val(),
                        rst_MaxValues:$('#input_multi').val()?5:1,
                        rst_PtrFilteredIDs:$('#input_rectypes').val(),
                        rst_FilteredJsonTermIDTree:$('#input_term').val(),  //configuration  rst_FilteredJsonTermIDTree
                        
                        //rst_TermIDTreeNonSelectableIDs not used
                    }
                }
                
                var $content = $('#main_div');
                $content.empty();
                       
                $('<div>').appendTo($content).editing_input(options);       
            }
            
            
            function showManageEntity( entityName, options ){

                options.isdialog = true;

                var manage_dlg = $('<div>')
                    .uniqurId()
                    .appendTo( $('body') )
                    ['manage'+entityName]( options );

                manage_dlg.manageSysUsers( 'popupDialog' );
            }

//----------------------------------
                        //rst_PtrFilteredIDs:$('#input_rectypes').val(),
                        //rst_FilteredJsonTermIDTree:$('#input_term').val(),  //configuration  rst_FilteredJsonTermIDTree
    var defDetailTypes = [
                {
                    dtID: 'dty_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Default field type name:',
                        rst_DisplayHelpText: 'A concise generic name used as a default for this field wherever it is used eg. \'creator\' rather than \'artist\' or \'author\'. Fields may be reused in multiple record types. This name is normally overridden with a name specific to each record type in which it is used.', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1
                    }
                },
                {
                    dtID: 'dty_HelpText',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Default help text:',
                        rst_DisplayHelpText: 'A default generic help text which may be overridden with more specific help for each record type that uses this field type', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1
                    }
                },
                {
                    dtID: 'dty_ExtendedDescription',
                    dtFields:{
                        dty_Type:'blocktext',
                        rst_DisplayName:'Extended description:',
                        rst_DisplayHelpText: 'An extended description of the content of this field type and references to any standards used', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'optional',
                        rst_MaxValues:1
                    }
                },
                {
                    dtID: 'dty_Type',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Data type:',
                        rst_DisplayHelpText: 'The type of data to be recorded in this field. Note: in most cases this cannot be changed once set', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1,
                        rst_JsonConfig:[
{key:"blocktext", title:"Memo (multi-line)"},
{key:"boolean", title:"Boolean (T/F)"},
//{key:"calculated", title:"Calculated (not yet impl.)"},
{key:"date", title:"Date / temporal"},
{key:"enum", title:"Terms list"},
{key:"file", title:"File"},
{key:"float", title:"Numeric"},
{key:"freetext", title:"Text (single line)"},
{key:"geo", title:"Geospatial"},
{key:"integer", title:"Numeric - integer"},
{key:"relationtype", title:"Relationship type"},
{key:"relmarker", title:"Relationship marker"},
{key:"resource", title:"Record pointer"},
//{key:"separator", title:"Heading (no data)"},
{key:"year", title:"Year (no mm-dd)"}]                        
                    }
                },
                {
                    dtID: 'dty_Status',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Status:',
                        rst_DisplayHelpText: "'Reserved' for the system, cannot be changed; 'Approved' for community standards; 'Pending' for work in progress; 'Open' for freely modifiable/personal record types", 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'optional',
                        rst_MaxValues:1,
                        rst_JsonConfig:['reserved','approved','pending','open']
                    }
                },
                {
                    dtID: 'dty_NonOwnerVisibility',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Non-owner visibility:',
                        rst_DisplayHelpText: "Hidden = visible only to owners, Viewable = any logged in user, Public = visible to non-logged in viewers", 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'optional',
                        rst_MaxValues:1,
                        rst_JsonConfig:['hidden','viewable','public'] //,'pending'
                    }
                },
                {
                    dtID: 'dty_ShowInLists',
                    dtFields:{
                        dty_Type:'boolean',
                        rst_DisplayName:'Show in lists:',
                        rst_DisplayHelpText: "Show this field type in pulldown lists etc. (always visible in field management screen)", 
                        rst_DisplayExtendedDescription:'',
                        rst_DefaultValue:'',
                        rst_RequirementType:'optional',
                        rst_MaxValues:1
                    }
                }
     ];
     var defDetailTypes = [
                    {dtID: 'dtg_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Name of Group:',
                        rst_DisplayHelpText:'Descriptive heading to be displayed for each group of details (fields)'
                    }},
                    {dtID: 'dtg_Description',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Description:',
                        rst_DisplayHelpText:'General description fo this group of detail (field) types'
                    }},
                    {dtID: 'dtg_Order',
                    dtFields:{
                        dty_Type:'integer',
                        rst_DisplayName:'Order:',
                        rst_DisplayHelpText:'Ordering of detail type groups within pulldown lists'
                    }}
                    ];
        </script>
    </head>

    <!-- HTML -->
    <body>
            <div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Field Name (dt id)<input type="text" id="field_name" value="test"></label>
                    <label>Data type:
                        <select id="datatype-sel">
                        
            <option value="freetext">Text (single line)</option>
            <option value="blocktext">Memo (multi-line)</option>
            <option value="integer">Numeric - integer</option>
            <option value="float">Numeric</option>
            <option value="date">Date / temporal</option>
            <option value="year">Year (no mm-dd)</option>
            <option value="boolean">Boolean (T/F)</option>
            
            <option value="enum">SELECT (Terms list for Records)</option>
            <option value="resource">PICKER (Record pointer for Records)</option>
            <option value="file">File</option>
            <option value="geo">Geospatial</option>
            <option value="relmarker">Relationship marker</option>

                        </select></label>
                </div>
                
                <div style="padding:5px; xborder-bottom:1px solid lightgrey">
                    <label>Label<input type="text" id="input_label" value="This is label"></label>
                    <label>Help<input type="text" id="input_help" value="Help what to do Help what to do Help what to do Help what to do"></label>
                    <label>Title(tip)<input type="text" id="input_title" value="Title over input"></label>
                </div>
                
                <div style="padding:5px; xborder-bottom:1px solid lightgrey">
                        <label>MaxValues
                            <select style="width:4em" id="input_multi">
                                <option>1</option>
                                <option>2</option>
                                <option>3</option>
                                <option>4</option>
                                <option>999</option>
                            </select>
                        </label>
                        <label>Requirement
                        <select id="rst_RequirementType">
                            <option value="required">required</option>
                            <option value="recommended">recommended</option>
                            <option value="optional">optional</option>
                            <option value="forbidden">forbidden</option>
                        </select>
                        </label>
                        
                        <label>Req<input type="checkbox" id="input_req" /></label>
                        <label>Disp width<input type="text" id="input_width" value="60" size=4></label>
                        
                        <label>Default value (defined from demo)<input type="text" id="rst_DefaultValue" value=""></label>
                </div>
                <div style="padding:5px; border-top:1px solid lightgrey">
                    <label style="font-weight: bold;">Extension and special properties</label><br>
                
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="freetext"> <!-- TEXT -->
                   <label><input type="radio" name="freetext_mode" value="plain" checked>Plain text</label>
                   <label><input type="radio" name="freetext_mode" value="password">Password</label>
                   <label><input type="radio" name="freetext_mode" value="color">Color picker</label>
                </div>
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="blocktext"> <!-- TEXTAREA -->
                   <label><input type="radio" name="blocktext_mode" value="plain" checked>Plain</label>
                   <label><input type="radio" name="blocktext_mode" value="password">Query builder</label>
                   <label><input type="radio" name="blocktext_mode" value="extended">Rich Text (external editor)</label>
                   <label><input type="checkbox" name="blockteext_resize">Resizeable</label>
                </div>
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="date"> <!-- DATE -->
                   <label><input type="checkbox" name="date_temporal">Allow Temporal</label>
                </div>
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="enum"> <!-- TERMS SELECT -->
                   ENUM: 
                   <label><input type="radio" name="enum_mode" value="list">Explicitely defined</label>
                   <label><input type="radio" name="enum_mode" value="reference">Entity reference</label>
                   <div>
                        <label>Entity (terms by default):<select style="width:7em" id="enum_entity"></select></label>
                        <label>Group filter<input type="text" id="enum_filter_groups" value=""></label>
                         (another resource field to pickup ids)
                   </div>
                </div>
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="resource"> <!-- RESOURCE -->
                   RESOURCE
                   <label><input type="radio" name="resource_mode" value="single" checked>Single</label>
                   <label><input type="radio" name="resource_mode" value="csv">CSV</label>
                   
                   <div>
                        <label>Entity (records by default):<select style="width:7em" id="resource_entity"></select></label>
                        <label>Group filter (another resource field)<input type="text" id="resource_filter_groups" value=""></label>                   </div>
                </div>
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="file"> <!-- FILE -->
                <!--
                   <label><input type="radio" name="file_mode" value="form" checked>Add/edit form (default for records)</label>
                   <label><input type="radio" name="file_mode" value="resource">Picker (use Resource???)</label>
                   <label><input type="radio" name="file_mode" value="upload">Upload file element</label>
                -->
                </div>

                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="relmaker"> <!-- RELATION MAKER -->
                   RELMAKER
                   <label>Reltype vocabulary<input type="text" name="relmaker_vocab"></label>
                   <label>Allowed Record types<input type="text" name="relmaker_rt"></label>
                </div>
                
                </div>
                <!--
                <div style="padding:5px; xborder-bottom:1px solid lightgrey" class="freetext">
                    <label>Rectypes(entity groups)<input type="text" id="input_rectypes" value=""></label>
                    <span>in format:  3,5 lookup for Records recype#3,5 or 
{"entity":"SysUsers","filter":"2,3","multi":true}  lookup for users in group#2,3, allow multi selection (if MaxValues=1 - store as csv)</span>
                    <br><label>Vocab ID|JSON enumtree|or CSV list<input type="text" id="input_term" value=""></label>
                </div>
                -->
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <button onclick="testEdit(true)">Test</button>
                </div>
            </div>
            
        <fieldset id="main_div" style="height:700;width:700;border:1px solid">
        </fieldset>
        
    </body>
</html>
