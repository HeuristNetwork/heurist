<?php

/*
* Copyright (C) 2005-2019 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* @todo - REMOVE, It is replaced with widget/record/recordAddLink.js
* recordAddLink.php
* Adds link field or create relationship between 2 records
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2019 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__)."/initPage.php");

?>
<script type="text/javascript" src="recordAddLink.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>

<style>
</style>
</head>
<body style="overflow:hidden">

        <div id="mainForm" class="popup_content_div" class="ui-widget-content">
            <label id="helpmsg">Choose the field(s) on which to create the link(s)</label>
            <br><br>
            
            <label style="vertical-align: top; padding-top:4px;display:inline-block;width:95px;">Source record:</label>
            <div id="div_source1" style="display:inline-block;">
                <img src='../assets/16x16.gif' id="source_rectype_img" 
                    style="vertical-align:top;margin-left:0px;" />
            
                <h2 id="source_rectype" class="truncate" 
                    style="max-width:400px;margin-left:30px;display:inline-block;"></h2>
                    
                <h2 class="header truncate" id="source_title" 
                    style="display:inline-block;max-width:400px;color:black;vertical-align:top;margin-left:10px">
                </h2>
                    
            </div>    
            <div id="div_source2" style="display:none;">
                <select id="sel_record_scope" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
            
            <!-- field selector -->
            <div id="source_field" 
                style="margin-top:15px;margin-bottom:15px;max-height: 40%;overflow: auto;border:none;">
            </div>

            <label style="vertical-align: top; padding-top:4px;display:inline-block;width:95px;">Target record:</label>
            <div id="div_target2" style="display:none; padding-top:4px">
            </div>
            <div id="div_target1" style="display:inline-block;">
                <img src='../assets/16x16.gif' id="target_rectype_img" 
                    style="vertical-align:top;margin-left:0px;" />
                    
                <h2 id="target_rectype" class="truncate" 
                    style="max-width:400px;margin-left:30px;display:inline-block;"></h2>
            
                <h2 class="header truncate" id="target_title" 
                    style="display:inline-block;max-width:400px;color:black;vertical-align:top;margin-left:10px">
                </h2>

            </div>    
                
                <!-- 
                <div id="rec1_hint" style="display:none;padding-top:15px">
                    <hr>
                    <div style="padding-top:15px">
                    You may also create a link in the reverse direction with one of the following fields in the target record:
                    </div>
                </div>
                -->
                                    
            <!-- field selector  border: 1px solid lightgray; -->
            <div id="target_field" style="margin-top:5px;max-height:25%;overflow: auto;">
            </div>
                    
        </div>
        
        <div  id="infoForm" class="popup_content_div ui-widget-content" style="display:none;font-size:1.2em;padding:30px 5px">
            There are no suitable fields (pointers or relationship markers) to create a link between 
            <span id="rec_titles">xxxxxxx and yyyyyyy</span> record types.<br><br>

            It is possible to create a generic relationship via the <a target="_new" href="#" id="link_rec_edit">record editing form</a>, 
            but we do not recommend this as they do not form part of the controlled database model.<br><br>

            Instead we recommend using the <a href="#" id="link_structure_edit">structure editor</a> to add a record pointer or relationship marker field which 
            will hold the link. You will then be able to create links by dragging, and the links will be controlled for consistency.
        </div>

        <div class="popup_buttons_div" style="text-align:right">

            <button id="btn_save">Create links</button>
            <button id="btn_cancel" style="margin-left:30px;">Cancel</button>
        </div>

</body>
</html>