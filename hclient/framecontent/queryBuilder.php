<?php

    /**
    * Enhanced Filter Builder Dialog
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @designer    Ian Johnson <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     6.0
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
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/queryBuilderItem.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="queryBuilder.js"></script>

        <script type="text/javascript">
            
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    //to parse and restore query
                    var query = window.hWin.HEURIST4.util.getUrlParameter('q', window.location.search);
                    var queryBuilder = new hQueryBuilder(query, $('#div_main'));
                    
                    // init helper (see utils.js)
                    window.hWin.HEURIST4.ui.initHelper( {button:$('#btn_help'), 
                            title:'Filter Builder', 
                            url:'../../context_help/search_query_builder.html #content'});
                }
            }            
        </script>

        <style type="text/css">
            .rulebuilder{
                display: block !important;
                padding:4 4 4 0;
                width:99% !important;
                text-align:left !important;
            }
            .rulebuilder>div{
                text-align:left;
                display: inline-block;
                width:200px;
            }
            .rulebuilder>div>select{
                min-width:180px;
                max-width:180px;
            }
        </style>
    </head>
    <body style="overflow:hidden">
    
        <div id="div_main" class="popup_content_div">
    sss
        </div>
    
        <div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix popup_buttons_div">
            <div class="ui-dialog-buttonset">
                <div id="btn-ok">OK</div>
                <div id="btn-cancel">Cancel</div>
                <button id="btn_help">Help</button>
            </div>
        </div>
    </body>
</html>
