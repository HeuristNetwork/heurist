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
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancytree/jquery.fancytree-all.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.css" />
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.layout/jquery.layout-latest.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/select_imagelib.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/media_viewer.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecStructure.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefRecTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypeGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefDetailTypes.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefDetailTypes.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrSavedSearches.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrSavedSearches.js"></script>
        
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysIdentification.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysDatabases.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysDatabases.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysBugreport.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>admin/structure/import/importStructure.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordLookupCfg.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
        <link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilder.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderItem.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderSort.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchQuick.js"></script>
        
        <!--      

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefFileExtToMimetype.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefFileExtToMimetype.js"></script>


        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysGroups.js"></script>
-->
        <style>
            .fancytree-hide{
                display: none;
            }
            ul.fancytree-container li {
               padding:0; 
            }
            .fancytree-ext-filter .fancytree-node.fancytree-match{
                font-weight: normal;
            }
            .fancytree-focused{
                background-color:lightblue;
            }
            .fancytree-title{
                font-weight: normal !important;
                white-space:normal;                
            }
            .fancybox-container{
                z-index:99999999;
            }
        </style>
        
        <script type="text/javascript">
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    
                    
                    
                    $('#curr_user').html(window.hWin.HAPI4.currentUser['ugr_ID']+'  '+window.hWin.HAPI4.currentUser['ugr_Name']);
                }
                
                
                        var $datepicker = $('#datepicker').datepicker({
                            showOn: "button",
                            /*buttonImage: "ui-icon-calendar",
                            buttonImageOnly: true,*/
                            showButtonPanel: true,
                            changeMonth: true,
                            changeYear: true,
                            dateFormat: "yy-mm-dd"
                        });

                        var $btn_datepicker = $( "<button>", {title: "Show calendar"})
                        .addClass("smallbutton")
                        .appendTo( $('#inputdiv') )
                        .button({icons:{primary: "ui-icon-calendar"},text:false});

                        $btn_datepicker.on( { click: function(){$datepicker.datepicker( "show" ); }} );
                        $('#inputdiv').find('.ui-datepicker-trigger').hide();
                
                //open at once 
                //testLookuService();
                //testEntity(true);
                //testEditSymbology();
                
                //test console.log('>>>'+$Db.isTermByReference(6302, 528));

//
//
//                
                $('#btn_qs').button().click(function(){
                    
                showSearchBuilder();
                    
/*                    
                    var cont = $('#searchQuick');
                
                //if( cont.is(':visible')){ // already visisble
                //    return;
                //}
            
                    if(!cont.search_quick_new('instance'))
                    //initialization
                    cont.search_quick_new({
                        onClose: function() {  },
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                //that._resetCloseTimers();    
                                //that._explorer_menu_locked = is_locked; 
                            }
                    }  });  
                    
                    var explore_left = 100, explore_top = 100, explore_height = 400;
                    
                    //cont.dialog({autoOpen: true});
                    
                    cont.css({left:explore_left, top:explore_top, height:explore_height,'z-index':103}).show();
  */                    
                    
                });
                
                
            }
            
            function testLookuService(){
                 window.hWin.HEURIST4.ui.showRecordActionDialog('recordLookupCfg');
            }

            function testImportStruc(){

                if(false){
                    var manage_dlg = $('<div id="heurist-dialog-importRectypes-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( $('body') )
                    .importStructure( {isdialog: true} );
                }else{
                    $('#main_div').empty().importStructure( {isdialog: false} );
                }
             
            }
            // to remove 
            function testRecords(){
                    var ispopup = true;
                    var select_mode = 'select_multi'; //'single',
                    
                    var rectype_set = window.hWin.HEURIST4.util.getUrlParameter('rectype_set',window.location.search);
                    var options = {
                                rectype_set: rectype_set,
                                select_mode: select_mode,
                                onselect:function(event, selection){
                                    if(selection && selection.isA('hRecordSet')){
                                       // alert( selection.getIds().join(',') );
                                       window.hWin.HAPI4.save_pref('recent_Records', selection.getIds(25), 25);      
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

            function testTags(){
                
                        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                container: '#main_div',
                                select_mode:'select_multi', 
                                layout_mode: '<div class="recordList"/>',
                                list_mode: 'compact', //special option for tags
                                selection_ids:[4,5,6,11,12,15,18], //already selected tags
                                select_return_mode:'recordset', //ids by default
                                onselect:function(event, data){
                                    if(data && data.selection){
                                        /*assign new set of tags to record
                                        var request = {};
                                        request['a']          = 'action'; //batch action
                                        request['entity']     = 'usrTags';
                                        request['tagIDs']  = data.selection.getOrder();
                                        request['recIDs']  = that._currentEditID;
                                        request['request_id'] = window.hWin.HEURIST4.util.random();
                                        
                                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                            function(response){
                                                if(response.status == window.hWin.ResponseStatus.OK){
                                                }
                                            });
                                        //update panel
                                        that._renderSummaryTags(data.selection, panel);
                                        */
                                    }
                                }
                        });
                
                
            }
            //
            //
            //
            function testEntity(mode){
                
                var options = {
                    select_mode: $('input[name="select_mode"]:checked').val(),
                    selectbutton_label: $('#multi_selectbutton_label').val(),
                    
                    pagesize: $('#page_size').val(),
                    edit_mode: $('input[name="edit_mode"]:checked').val(),
                    use_cache: $('#use_cache').is(':checked'),
                    
                    //action_select: $('#action_select').is(':checked'), //may be true,false or array
                    //action_buttons: $('#action_buttons').is(':checked'), //may be true,false or array
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
                
                    options.isdialog = true;
                
/*                
                    var func_name = 'showManage'+entity;
                    
                    if($.isFunction(window[func_name])){
                   
                       if(entity=='UsrTags'){
                            showManageUsrTags( options );      

                       }else if(entity=='SysGroups'){
                            
                           options.height = 640;
                            options.width = 840;
                            showManageSysGroups( options );      
                                
                       }else if(entity=='DefFileExtToMimetype'){
                            options.height = 400;
                            options.width = 840;
                            showManageDefFileExtToMimetype( options );      
                       }else if(entity=='DefTerms'){
                           
                            options.height = 600;
                            options.width = 840;
                            showManageDefTerms( options );      
                            
                       }else if(entity=='RecUploadedFiles'){
                           
                            options.height = 600;
                            options.width = 940;
                            showManageRecUploadedFiles( options );
                                  
                       }else if(entity=='Records'){
                           
                            options.height = 600;
                            options.width = 840;
                            showManageRecords( options );      
                            
                       }
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgWorkInProgress();
                    }
*/
                }else{//on this page

                    options.isdialog = false;
                    options.container = '#main_div';
/*                
                    var widgetname = 'manage'+entity,
                        $content = $('#main_div');

                    if($.isFunction($content[widgetname])){
                    
                       $content.empty();
                       //$content[widgetname]( options );   //call constructor
                       
                       if(entity=='UsrTags'){
                            $content.manageUsrTags( options );      
                       }else
                       if(entity=='DefFileExtToMimetype'){
                            $content.manageDefFileExtToMimetype( options );      
                       }else
                       if(entity=='DefTerms'){
                            $content.manageDefTerms( options );      

                       }else 
                       if(entity=='RecUploadedFiles'){
                            $content.manageRecUploadedFiles( options );      
                       }else 
                       if(entity=='Records'){
                            $content.manageRecords( options );      
                       }
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgWorkInProgress();
                    }
*/                
                }
                
                
                if(entity=='DefTerms'){
                    //options.select_mode = 'images';
                    //options.initialTermsIds = [414,415,5424,528,5426,5449];
                }else if(entity=='SysGroups'){
                    //options.edit_mode = 'none';
                    //options.select_mode = 'select_role';
                    //options.ugl_UserID = 
                }else if(entity=='UsrTags'){
                    
                }
                
                
                window.hWin.HEURIST4.ui.showEntityDialog(entity, options);
                
            }
            
/*            
            var systemEntities= 
                           [{"key":"Records",title:"Records"},
                            {"key":"SysUsers",title:"Users",icon:'ui-icon-person'},
                            {"key":"SysGroups",title:"Workgroups",icon:'ui-icon-group'},
                            {"key":"Tags",title:"Tags",icon:'ui-icon-tag'},
                            {"key":"RecUploadedFiles",title:"Uploaded Files",icon:'ui-icon-image'},
                            {"key":"usrReminders",title:"Reminders",icon:'ui-icon-mail-closed'},
                            {"key":"sysDatabases",title:"Databases",icon:'ui-icon-database'},
                            {"key":"UsrSavedSearches",title:"Saved Searches",icon:'ui-icon-search'},
                            {"key":"DefRecTypes",title:"Record Types",icon:'ui-icon-image'},
                            {"key":"DefRecTypeGroups",title:"Record Type Groups"},
                            {"key":"DefDetailTypes",title:"Field Types"},
                            {"key":"DefDetailTypeGroups",title:"Field Type Groups"},
                            {"key":"DefTerms",title:"Terms"},
                            {"key":"RecComments",title:"Record Comments"},
                            {"key":"Smarty",title:"Smarty Reports"},
                            {"key":"SmartySchedule",title:"Smarty Reports Schedule"}];
            
 */           
        </script>
    </head>

    <!-- HTML -->
    <body>
    
<?php
            /*
    function getRelativePath($basePath, $targetPath)
    {
        $targetPath = str_replace("\0", '', $targetPath);
        $targetPath = str_replace('\\', '/', $targetPath);
        
        
print $targetPath.'<br>';    

        if ($basePath === $targetPath) {
            return '';
        }
        //else  if(strpos($basePath, $targetPath)===0){
        //    $relative_path = $dirname;


        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? './'.$path : $path;
    }
    print HEURIST_FILESTORE_DIR.'<br>';
    print getRelativePath(HEURIST_FILESTORE_DIR, 'C:\\xampp\\htdocs\\HEURIST_FILESTORE\\artem_delete11\\klassiki\\');
     */   
?>    
    
            <div>
                <button id="btn_qs">Quick search</button>
            </div>
            <div id="searchQuick" style="position: absolute;height: 400;width:900; display:none">afweewfwefwefe</div>
        
            
            <div>
                
            
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Entity:
                        <select id="entity-sel">
                            <option value="SysBugreport">Bug report</option>
                            <option value="Records">Records</option>
                            <option value="SysUsers" >Users +</option>
                            <option value="SysGroups">Workgroups +</option>
                            <option value="UsrTags">Tags +</option>
                            <option value="RecUploadedFiles">Uploaded Files +</option>
                            <option value="UsrReminders" >Reminders</option>
                            <option value="UsrSavedSearches">Filters</option>
                            <option value="SysIdentification">Database Property</option>
                            <option value="SysDatabases">Databases</option>
                            <option value="DefRecTypes">Record Types</option>
                            <option value="DefRecStructure">Record Type Structure</option>
                            <option value="DefRecTypeGroups">Record Type Groups+</option>
                            <option value="DefDetailTypes">Field Types</option>
                            <option value="DefDetailTypeGroups">Field Type Groups +</option>
                            <option value="DefTerms">Terms</option>
                            <option value="DefFileExtToMimetype">Ext To Mimetype +</option>
                            <option value="RecThreadedComments">Record Comments</option>
                            <option value="Smarty">Smarty Reports</option>
                            <option value="SmartySchedule">Smarty Reports Schedule</option>
                        </select></label>
                        
                        
                        <label id="curr_user"></label>
                </div>
                
                <div style="padding:5px; xborder-bottom:1px solid lightgrey">
                    <label>Selection Mode</label>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="manager" checked>Manager</label> as it currently implemented: records selected with shift, ctrl. Selection is not retained among pages and searches. Selected records are not returned from dialog
                    </div>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="select_single">Select Single</label> only one selection is allowed. Search result event triggered each time user click on record. In popup mode dialog is closed on select.
                    </div>
                    <div style="padding-right:20px">
                        <label style="font-weight: bold;"><input type="radio" name="select_mode" value="select_multi" >Select Multi</label> Selection persists between pages and selections. Select event is triggered on special button click.
                    </div>
                    
                    <div id='inputdiv'>
                        <input id='datepicker'/>
                    </div>
                    
                </div>                        
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <label>Initial filter by name<input type="text" id="filter_title" value=""></label>
                    <label>CSV group/rec types<input type="text" id="filter_groups" value=""></label>
                    <label>Select button label<input type="text" id="multi_selectbutton_label" value="Select"></label>
                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <span style="padding-left:20px;">
                        <label>Edit mode</label>
                        <label><input type="radio" name="edit_mode" value="editonly">edit only</label> 
                        <label><input type="radio" name="edit_mode" value="inline">inline</label> 
                        <label><input type="radio" name="edit_mode" checked=checked  value="popup">popup</label> 
                        <label><input type="radio" name="edit_mode" value="none">none</label> 
                    </span>
                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <span style="padding-right:20px;">
                        <label>Page size<input type="text" id="page_size" value="100" size="3"> 0 means 'no pages'</label>
                    </span>

                    <label><input type="checkbox" id="use_cache" checked=checked>Use cache (Not implemented for entities with reccount>1500)</label>
                </div>
                <div style="padding:5px; border-bottom:1px solid lightgrey">
                    <button onclick="testEntity(true)">show in popup dialog</button>
                    <button onclick="testEntity(false)">show on this page</button>
                    <button onclick="testTags()">embedded select tags</button>
                    <button onclick="testImportStruc()">Import structure</button>

                    <button onclick="testLookuService()">Lookup service config</button>

                    
                    <!-- button onclick="testEditSymbology()">Edit symbology</button -->
                </div>
                
            </div>
        <div id="main_div" style="position:absolute;top:250px;min-height:300px;width:900;border:1px solid">
        </div>
        
        <div id="selected_div" style="float:right;width:200;border:1px solid">
        </div>
        
    </body>
</html>
