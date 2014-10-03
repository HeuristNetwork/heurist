<?php

    /** 
    * Standalone search for related recprds. It may be used separately or wihin widget in iframe (for example in recordListExt )
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
    require_once(dirname(__FILE__).'/../php/common/db_structure.php');

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
        <title><?=HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style3.css">

        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

        <!-- @todo load this 4 scripts dynamically -->
        <!-- script type="text/javascript" src="../apps/search.js"></script>
        <script type="text/javascript" src="relatedRecords.js"></script-->
        
        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>

        <script type="text/javascript" src="../apps/search/ruleBuilder.js"></script>
        <script type="text/javascript" src="../apps/search/resultList.js"></script>
        
        <script type="text/javascript">
            if(top.HEURIST4.util.isnull(top.HEURIST4.rectypes)){
            <?php
                //fill database definitions
                print "top.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 2) ).";\n";
                print "top.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
            ?>
            }

            var relatedRecords, ruleBuilder;

            function onLoadHAPI(success) //callback function of hAPI initialization
            {
                        if(success)  //system is inited
                        {
                            if(!top.HR){
                                var prefs = top.HAPI4.get_prefs();
                                //loads localization
                                top.HR = window.HAPI4.setLocale(prefs['layout_language']); 
                            }
                            
                            ruleBuilder = $("<div>").appendTo($("body"));
                            var options = {};
                            
                            //add rule builder
                            ruleBuilder.ruleBuilder( options );
                            
                            //add record list widget
                            options = { showmenu: false };
                            var $container = $("<div>").appendTo($("body"));
                            $container.resultList( options );

                            //relatedRecords = new hRelatedRecords($container);
                        }
            }
            
            //
            //
            //
            function updateRuleBuilder(rectypes){
                
                if(ruleBuilder && rectypes){
                    ruleBuilder.ruleBuilder('option', 'recordtypes', rectypes );
                } 
            }
            
            $(document).ready(function() {

                if(!top.HAPI4){
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>', onLoadHAPI);//, < ?=json_encode($system->getCurrentUser())? > );
                }else{
                    onLoadHAPI(true);
                }

            });
            
        </script>

    </head>
    <body>
    </body>
</html>
