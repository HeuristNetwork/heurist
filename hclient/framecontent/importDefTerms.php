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

require_once(dirname(__FILE__)."/initPage.php");
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
    <div class="ent_header" style="height:6em;">
        <h2>1. Upload your data or paste content in area below</h2>
        
        <input type="file" id="uploadFile" style="display:none">
        <div id="btnUploadFile" style="position:absolute;bottom:2px;">Upload File</div>
    </div>
    <textarea id="sourceContent" class="ent_content_full" style="top:6em;width:100%;resize:none">
    
    </textarea>
</div>
<div style="position:absolute;left:200px;width:150px; height:100%; border-right:1px lightgray solid">
    <div class="ent_header" style="height:6em;">
        <h2>2. Define parse parameters and click</h2>
        <div id="btnParseData" style="position:absolute;bottom:2px;">Start Parse</div>
    </div>
    <fieldset class="ent_content_full" style="top:6em;padding-top:2em;">
            <div>
                <label>Field separator:</label>
                <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
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
                        <option selected value="1">No lines</option>
                        <option value="2">No lines. Group by 2</option>
                        <option value="3">No lines. Group by 3</option>
                        <option value="win">Windows</option>
                        <option value="nix">Unix</option>
                        <option value="max">Mac</option>
                    </select>
            </div>
    </fieldset>            
</div>
<div style="position:absolute;left:350px;height:100%;right:150px; border-right:1px lightgray solid" >
    <div class="ent_header" style="height:6em;">
        <h2>3. Data to be imported</h2>
        <div id="preparedInfo" style="position:absolute;bottom:2px;"> <!-- div to shw results of data preparation -->
            
        </div>
    </div>
    <div class="ent_content_full" style="top:6em;padding-top:20px;font-size:0.9em" id="divStep2">
    </div>
</div>
<div style="position:absolute;right:0px;height:100%;width:150px;">
    <div class="ent_header" style="height:6em;">
        <h2>4. Define column roles and click</h2>
        <div id="btnImportData" style="position:absolute;bottom:2px;">Start Import</div>
    </div>
    <fieldset class="ent_content_full" style="top:6em;padding-top:2em;">
           
            <div>
                <label>Term(Label)</label><br>
                <select id="field_term" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
            <div>
                <label>Term Code</label><br>
                <select id="field_code" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
            <div>
                <label>Term Description</label><br>
                <select id="field_desc" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;">
                </select>
            </div>
</fieldset> 
</div>

</body>
</html>