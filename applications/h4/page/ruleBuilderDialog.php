<?php

    /** 
    * Rule Builder Dialog
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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


    require_once(dirname(__FILE__)."/../php/System.php");
    //require_once(dirname(__FILE__).'/../php/common/db_structure.php'); //may be removed

    $system = new System();

    if(@$_REQUEST['db']){
        if(! $system->init(@$_REQUEST['db']) ){
            //@todo - redirect to error page
            print_r($system->getError(),true);
            exit();
        }
    }else{
        header('Location: /../php/databases.php');
        exit();
    }
?>
<html>
    <head>
        <title>Rule Builder</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style3.css">

        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

       
        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>

        <script type="text/javascript" src="../apps/search/ruleBuilder.js"></script>
        <script type="text/javascript" src="../apps/search/resultList.js"></script>
        
        <script type="text/javascript">
            var db =  '<?=$_REQUEST['db']?>';
            var rules = <?=@$_REQUEST['rules']?$_REQUEST['rules']:''?>;                             
        </script>
        <script type="text/javascript" src="ruleBuilderDialog.js"></script>
        
        <style>
            .rulebuilder{
                display: block !important;
                padding:4 4 4 0;
                width:99% !important;
                text-align:left !important;
            }
            .rulebuilder>div{
                text-align:center;
                display: inline-block;
                width:160px;
            }
            .rulebuilder>div>select{
                min-width:150px;
                max-width:150px;
            }
        </style>
    </head>
    <body>
        <div style="overflow:hidden;width:100%;height:100%">

            <div style="position:absolute;width:98%;top:0" class="rulebuilder">
                <div style="width:250px">Source</div><div>Field</div><div>Relation Type</div><div>Target</div><div>Filter</div>
            </div>
        
            <div style="position:absolute;width:98%;top:2em;bottom:4em;overflow-y:auto" id="level1">
            </div>
            
            <div style="position:absolute;width:98%;height:2em;bottom:10px">
                <button id="btn_add_level1">Add Level 1</button> 
                <button id="btn_save">Save Rules</button>
                <button id="btn_apply">Apply Rules</button>
            </div>
        </div>
    </body>
</html>
