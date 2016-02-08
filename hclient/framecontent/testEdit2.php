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
                    
                    top.HEURIST4.ui.initHintButton($('#btn_help_hints'));
                    
                }
            }
            
     
//----------------------------------
                        //rst_PtrFilteredIDs:$('#input_rectypes').val(),
                        //rst_FilteredJsonTermIDTree:$('#input_term').val(),  //configuration  rst_FilteredJsonTermIDTree
    var defDetailTypes = [
                {
                    dtID: 'dty_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName: 'Default field type name:',
                        rst_DisplayHelpText: "A concise generic name used as a default for this field wherever it is used eg. 'creator' rather than 'artist' or 'author'. Fields may be reused in multiple record types. This name is normally overridden with a name specific to each record type in which it is used.", 
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
                        rst_DisplayWidth:40,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1,
                        rst_JsonConfig:[  //it will be stored in 
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
                //specific elements 
                {
                    dtID: 'dty_Mode_freetext',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Input type:',
                        rst_DisplayHelpText: "Define specific subtype", 
                        rst_JsonConfig:['text','password','color']
                    }
                },
                {
                    dtID: 'dty_Mode_blocktext',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Input type:',
                        rst_DisplayHelpText: "Define specific subtype", 
                        rst_JsonConfig:['text','query','editor']
                    }
                },
                {
                    dtID: 'dty_Mode_date',
                    dtFields:{
                        dty_Type:'boolean',
                        rst_DisplayName:'Allow temporal object:',
                        rst_DisplayHelpText: "Define specific subtype"
                    }
                },
                {   // for example for defDetailType the configuration is  {entity:'defTerms', filter_group:'enum', mode:'single'}
                    // we do not need to specify all these 3 params here
                    dtID: 'dty_JsonTermIDTree',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Vocabulary (terms):',
                        rst_DisplayHelpText: "The set of terms which may be selected for this field", 
                        rst_JsonConfig: {entity:'DefTerms', filter_group:'enum', button_browse:true}
                    }
                },
                {
                    dtID: 'dty_PtrTargetRectypeIDs',
                    dtFields:{
                        dty_Type:'resource',
                        rst_DisplayName:'Target record types:',
                        rst_DisplayHelpText: "The set of record types to which this field can point (for pointer fields and relationship markers. If undefined, it can point to any record type.", 
                        rst_JsonConfig: {entity:'DefRecTypes',csv:true}  //select several recordtypes
                    }
                },
                {
                    groupHeader: 'Additional',
                    groupType: 'accordion',  //accordion, tabs, group 
                    groupStye: null,
                    children:[
                
                {
                    dtID: 'dty_Mode_freetext',
                    dtFields:{
                        dty_Type:'enum',
                        rst_DisplayName:'Status2222:',
                        rst_DisplayHelpText: "'Reserved' for the system, cannot be changed; 'Approved' for community standards; 'Pending' for work in progress; 'Open' for freely modifiable/personal record types", 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'optional',
                        rst_MaxValues:9,
                        rst_JsonConfig:['reserved','approved','pending','open']
                    }
                },
                
                ]},                
                {
                    groupHeader: 'Second22',
                    groupType: 'accordion',  //accordion, tabs, group 
                    groupStye: null,
                    children:[
                
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
                
                ]}                
     ];
     var defDetailTypeGroups = [
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
            
            
            
        <div id="main_div" style="height:700;width:700;border:1px solid">
        </div>

        <div id="btn_help_hints" style="position:absolute;top:4px;right:4px;"></div>        
    </body>
</html>
