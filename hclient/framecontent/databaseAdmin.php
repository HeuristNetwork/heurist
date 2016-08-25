<?php

    /**
    * Database structure / administration page
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    define('MANAGER_REQUIRED',1);
    
    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    require_once(dirname(__FILE__)."/initPage.php");
?>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/databaseAdmin.js"></script>

        <script type="text/javascript">
            var editing;
            
            // Callback function on initialization
            function onPageInit(success){
                if(success){
                    
                    var databaseAdmin = new hDatabaseAdmin();
                    
                    var $container = $("<div>").appendTo($("body"));
                }
            }            
        </script>
        <style>
    .menu-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: block;
    outline: none;
    border: none !important;
    }        
    .menu-list .ui-menu-item{    
    position: relative;
    margin: 0;
    padding: 3px 1em 3px .4em;
    cursor: pointer;
    min-height: 0;
    }
    .menu-list .ui-menu-item a{    
    display: block;
    font-weight: normal;
    line-height: 1.5;
    min-height: 0;
    padding: 2px 0.4em;
    margin-right: 10px;
    text-decoration: none;
    }        
    .menu-list .ui-menu-item a.selected{    
        background:lightblue;
    }
        </style>
    </head>
    <body style="background-color:white">
        <div style="width:280px;top:0;bottom:0;left:0;position:absolute;padding:5px;">
            <ul id="menu_container" style="margin-top:10;padding:2px"></ul>
        </div>
        <div style="left:300px;right:0;top:0;bottom:20;position:absolute;overflow:auto;padding-top:5px;">
            <iframe id="frame_container">
            </iframe>
        </div>
    </body>
</html>
