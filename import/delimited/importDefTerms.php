<?php

    /**
    * Import terms from CSV
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
define('LOGIN_REQUIRED',1);
define('PDIR','../../');    

require_once(dirname(__FILE__)."/../../hclient/framecontent/initPage.php");
?>
        <script type="text/javascript" src="importDefTerms.js"></script>

        <script type="text/javascript">

            // Callback function after initialization
            function onPageInit(success){
                if(success){
                    var trm_ID = window.hWin.HEURIST4.util.getUrlParameter('trm_ID',window.location.search);
                    var vcg_ID = window.hWin.HEURIST4.util.getUrlParameter('vcg_ID',window.location.search);
                    var importDefTerms = new hImportDefTerms(trm_ID, vcg_ID);
                }
            }
        
        
        </script>
        <style>
            body{
                font-size: 12px;
            }
            .tbmain td, .tbmain th
            {
                border-left: 1px solid lightgray;
                padding:3px;
                text-align:left;
            }
            .tbmain th
            {
                font-weight:bold;
                padding-top:6px;
            }
            .tbmain
            {
                /*border-collapse:collapse;*/
                font-size: 1em;
            }

            div.top-div, 
			div.bottom-div{
				display: flex;
				align-content: center;
				/*justify-content: center;*/
				flex-wrap: wrap;
			}
			div.bottom-div{
				flex-wrap: nowrap;
			}

			div#divStep1{
				margin-right:10px;
				flex: 1 1 450px;
			}
			div#divStep2{
				border-right:1px lightgray solid;
				flex: 0 1 175px;
			}
			div#divStep3{
				margin-left: 10px;
				flex: 0 1 200px;
			}
        </style>
        
    </head>

    <!-- HTML -->
    <body style="margin: 0;">
<div class="top-div">
<div id="divStep1">
    <div style="height:8em;padding:0">
        <p><b>Step 1</b></p>
        <p>Paste content in area below (one label per line, may optionally be followed by description, code and semantic URI, with usual delimiters eg. comma)</p>
        <input type="file" id="uploadFile" style="display:none">
      <div style="padding-top:4px">
        <h2 style="display: inline-block;margin:0">or</h2>
        <div id="btnUploadFile">Upload File</div>
        <div style="float:right">encoding: 
<select id="csv_encoding" class="text ui-widget-content ui-corner-all" style="width:120px;font-size:0.9em">
<option>UTF-8</option>
<option>UTF-16</option>
<option>UTF-16BE</option>
<option>UTF-16LE</option>
<option>CP1251</option>
<option>CP1252</option>
<option>KOI8-R</option>
<option>UCS-4</option>
<option>UCS-4BE</option>
<option>UCS-4LE</option>
<option>UCS-2</option>
<option>UCS-2BE</option>
<option>UCS-2LE</option>
<option>UTF-32</option>
<option>UTF-32BE</option>
<option>UTF-32LE</option>
<option>UTF-7</option>
<option>UTF7-IMAP</option>
<option>ASCII</option>
<option>EUC-JP</option>
<option>SJIS</option>
<option>eucJP-win</option>
<option>SJIS-win</option>
<option>ISO-2022-JP</option>
<option>ISO-2022-JP-MS</option>
<option>CP932</option>
<option>CP51932</option>
<option>MacJapanese</option>
<option>SJIS-DOCOMO</option>
<option>SJIS-KDDI</option>
<option>SJIS-SOFTBANK</option>
<option>UTF-8-DOCOMO</option>
<option>UTF-8-KDDI</option>
<option>UTF-8-SOFTBANK</option>
<option>ISO-2022-JP-KDDI</option>
<option>JIS</option>
<option>JIS-ms</option>
<option>CP50220</option>
<option>CP50220raw</option>
<option>CP50221</option>
<option>CP50222</option>
<option>ISO-8859-1</option>
<option>ISO-8859-2</option>
<option>ISO-8859-3</option>
<option>ISO-8859-4</option>
<option>ISO-8859-5</option>
<option>ISO-8859-6</option>
<option>ISO-8859-7</option>
<option>ISO-8859-8</option>
<option>ISO-8859-9</option>
<option>ISO-8859-10</option>
<option>ISO-8859-13</option>
<option>ISO-8859-14</option>
<option>ISO-8859-15</option>
<option>byte2be</option>
<option>byte2le</option>
<option>byte4be</option>
<option>byte4le</option>
<option>BASE64</option>
<option>HTML-ENTITIES</option>
<option>7bit</option>
<option>8bit</option>
<option>EUC-CN</option>
<option>CP936</option>
<option>GB18030</option>
<option>HZ</option>
<option>EUC-TW</option>
<option>CP950</option>
<option>BIG-5</option>
<option>EUC-KR</option>
<option>UHC</option>
<option>ISO-2022-KR</option>
<option>CP866</option>
                    </select>
       </div>
    </div>          
    <div class="heurist-helper1" style="padding-top: 9px;">
Separate term hierarchy levels with a period ( . ) eg. "History.Medieval.Late". 
<br/>If supplying optional standard code, description and semantic URI, 
separate term and each of these values with comma or tab.
    </div>                                    
                    
    </div>
    <textarea id="sourceContent" rows="15" cols="0" 
    style="width:100%;resize:none;padding:0.5em;border:2px solid lightblue;"></textarea>
    
    
</div>
<div id="divStep2">
    <div style="height:10em;">
        <p><b>Step 2</b></p>
        <div>
            <input id="csv_header" 
                style="margin:0 0.5em 0 0"
                class="text ui-widget-content ui-corner-all" type="checkbox" value="1">
            <label for="csv_header">Labels in line 1</label>
        </div>
        <div id="btnParseData" style="margin-top: 10px;">Analyse</div>
    </div>
    <fieldset style="padding-top:1em;">
            <div>
                <label for="csv_delimiter">Field separator:</label>
                <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
                        <option value="space">space</option>
                </select>
            </div>
            <div>
                <label for="csv_enclosure">Fields enclosed in:</label>
                <select id="csv_enclosure" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option selected value='2'>"</option><option value="1">'</option>
                </select>
            </div>
            <div>
                <label for="csv_linebreak">Line separator:</label>
                <select id="csv_linebreak" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    <option selected value="auto">auto detect</option>
                    <option value="1">No lines</option>
                    <option value="2">No lines. Group by 2</option>
                    <option value="3">No lines. Group by 3</option>
                    <!--
                    <option value="win">Windows</option>
                    <option value="nix">Unix</option>
                    <option value="mac">Mac</option>
                    -->
                </select>
            </div>
            <div>
                <label for="term_separator">Sub-term separator:</label>
                <input id="term_separator" class="text ui-widget-content ui-corner-all" style="width: 25px;margin-left: 8px;">
            </div>
    </fieldset>            
</div>
<div id="divStep3"> 
    <div style="height:6em;">
        <p><b>Step 3</b></p>
        <p style="padding-top:0.4em; margin-bottom: 10px;">Select field assignment<br>(Term label is required)</p>
    </div>
    <fieldset style="padding-top:1em;">
           
            <div>
                <label style="color:red">Term (Label)</label><br>
                <select id="field_term" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                </select>
            </div>
            <div>
                <label>Standard Code</label><br>
                <select id="field_code" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                </select>
            </div>
            <div>
                <label>Description</label><br>
                <select id="field_desc" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                </select>
            </div>
            <div>
                <label>Semantic/Reference URI</label><br>
                <select id="field_uri" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                </select>
            </div>
            
            
    </fieldset> 
    <div style="padding-left:5px;height:5em">
        <div id="preparedInfo2" style="font-weight:bold;font-size:1.1em;padding:4px"></div>
        <div id="btnImportData">Import</div>
    </div>
</div>
<!-- <div id="divCurtain" style="position:absolute;right:0px;height:350px;width:400px;" class="semitransparent">
 curtain
</div> -->
</div>

<div style="margin-top:10px;">
    <div style="height:2em;border-bottom:none;border-top:1px solid lightgray;padding-top:10px;">
        <b>Preview of the data as it will be imported:</b>
        <div id="preparedInfo" style="float:right;padding-right:10px"> <!-- div to show results of data preparation --></div>
    </div>
    <div style="font-size:0.9em;" id="divParsePreview">
    </div>
</div>



</body>
</html>