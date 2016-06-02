<?php

    /**
    * Import terms from CSV
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
    
define('PDIR','../../../');    

require_once(dirname(__FILE__)."/../initPage.php");
?>
        <script type="text/javascript" src="importDefTerms.js"></script>

        <script type="text/javascript">

            // Callback function after initialization
            function onPageInit(success){
                if(success){
                    var importDefTerms = new hImportDefTerms(top.HEURIST4.util.getUrlParameter('trm_ID',window.location.search));
                }
            }
        
        
        </script>
    </head>

    <!-- HTML -->
    <body class="ui-heurist-bg-light" style="overflow:hidden;">

<div style="position:absolute;width:200px; height:100%;  border-right:1px lightgray solid" id="divStep1">
    <div class="ent_header" style="height:5em;">
        <h2>Step 1<br>Paste content in area below</h2>
        <h2 style="position:absolute;bottom:6px;">or</h2>
        <input type="file" id="uploadFile" style="display:none">
        <div id="btnUploadFile" style="position:absolute;bottom:6px;left:3em">Upload File</div>
    </div>
    <textarea id="sourceContent" class="ent_content_full" style="top:5em;width:100%;resize:none">
    
    </textarea>
</div>
<div style="position:absolute;left:200px;width:150px; height:100%; border-right:1px lightgray solid">
    <div class="ent_header" style="height:5em;">
        <h2>Step 2</h2>
        <div id="btnParseData" style="position:absolute;bottom:2px;">Start Parse</div>
    </div>
    <fieldset class="ent_content_full" style="top:5em;padding-top:2em;">
            <div>
                <label>Field separator:</label>
                <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
                        <option value="space">space</option>
                </select>
            </div>
            <div>
                <label>Fields enclosed in:</label>
                <select id="csv_enclosure" class="text ui-widget-content ui-corner-all" style="width:120px;">
                        <option selected value='2'>"</option><option value="1">'</option>
                </select>
            </div>
            <div>
                <label>Line separator:</label>
                    <select id="csv_linebreak" class="text ui-widget-content ui-corner-all" style="width:120px;">
                        <option selected value="auto">auto detect</option>
                        <option value="1">No lines</option>
                        <option value="2">No lines. Group by 2</option>
                        <option value="3">No lines. Group by 3</option>
                        <!--
                        <option value="win">Windows</option>
                        <option value="nix">Unix</option>
                        <option value="max">Mac</option>
                        -->
                    </select>
            </div>
    </fieldset>            
</div>
<div style="position:absolute;left:350px;height:100%;right:150px; border-right:1px lightgray solid" >
    <div class="ent_header" style="height:5em;">
        <h2 style="position:absolute;bottom:6px;">Preview data to be imported</h2>
    </div>
    <div class="ent_content_full" style="top:6em;font-size:0.9em;" id="divParsePreview">
    </div>
</div>
<div style="position:absolute;right:0px;height:100%;width:150px;" id="
">
    <div class="ent_header" style="height:5em;">
        <h2>Step 3<br>Select field order<br>(Term is required)</h2>
    </div>
    <fieldset class="ent_content" style="top:5em;padding-top:2em;">
           
            <div>
                <label style="color:red">Term(Label)</label><br>
                <select id="field_term" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
            <div>
                <label>Standard Code</label><br>
                <select id="field_code" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
            <div>
                <label>Description</label><br>
                <select id="field_desc" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
            
            <div id="preparedInfo"> <!-- div to show results of data preparation --></div>
    </fieldset> 
    <div class="ent_footer" style="text-align:center">
        <div id="btnImportData" xstyle="position:absolute;bottom:2px;">Start Import</div>
    </div>
</div>
<div id="divCurtain" style="position:absolute;right:0px;height:100%;left:200px;" class="semitransparent">
<!-- curtain -->
</div>
</body>
</html>