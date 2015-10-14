<?php

    /**
    * Standalone search for related recprds. It may be used separately or wihin widget in iframe (for example in recordListExt )
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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

            var ruleBuilders = [], counter = 0;

            function onInit(success) //callback function of hAPI initialization
            {
                        if(success)  //system is inited
                        {
                            if(!top.HR){
                                var prefs = top.HAPI4.get_prefs();
                                //loads localization
                                top.HR = window.HAPI4.setLocale(prefs['layout_language']);
                            }

                            //init toolbar buttons
                            $('#btn_add_level1').button().on('click', 1, addRuleBuilder);
                            $('#btn_add_level2').button().button("disable").on('click', 2, addRuleBuilder);
                            $('#btn_add_level3').button().button("disable").on('click', 3, addRuleBuilder);


                            /*
                            ruleBuilder = $("<div>").appendTo($("body"));
                            var options = {};
                            //add rule sets builder
                            ruleBuilder.ruleBuilder( options );
                            */

                            //add record list widget
                            /*options = { showmenu: false };
                            var $container = $("<div>").appendTo($("body"));
                            $container.resultList( options );*/

                        }
            }

            function addRuleBuilder(event){
                counter++;
                var level = event.data;
                var ruleBuilder = $("<div>",{id:'rb'+counter}).ruleBuilder({level:level, onremove:removeRoleBuilder}).appendTo($('#level'+level));
                ruleBuilders.push(ruleBuilder);

                updateButtons();
            }

            function removeRoleBuilder(event, data){

                var level = $('#'+data.id).ruleBuilder('option' , 'level');
                var remove_on_levels_above = (level<3 && $('#level'+(level+1)).children().length>0 && $(('#level'+level)).children().length==1);

                function _onDelete(){


                    $.each(ruleBuilders, function( index, value ) {
                        if(value.attr('id') == data.id){
                            ruleBuilders.splice(index, 1);
                            return false;
                        }
                    });

                    $('#'+data.id).remove();

                    if(remove_on_levels_above){ //remove ALL on dependent levels
                        var index = 0;
                        while (index < ruleBuilders.length){
                            var $div = $(ruleBuilders[index]);
                            if($div.ruleBuilder('option' , 'level')>level){
                                ruleBuilders.splice(index, 1);
                                $div.remove();
                            }else{
                                index++;
                            }
                        }
                    }

                    updateButtons();
                }

                if(remove_on_levels_above){
                    top.HEURIST4.msg.showMsgDlg('You remove the last rule per level. All rules for dependent levels will be removed also. Please confirm',  _onDelete);
                }else{
                    _onDelete();
                }
            }

            // update add button disability
            function updateButtons(){
                var val = $('#level2').children().length>0?"enable":"disable";
                $('#btn_add_level3').button(val);
                val = $('#level1').children().length>0?"enable":"disable";
                $('#btn_add_level2').button(val);
            }

            //
            //
            //
            function updateRuleBuilder(rectypes, query_request){
                /*
                if(ruleBuilder && rectypes){
                    ruleBuilder.ruleBuilder('option', 'recordtypes', rectypes );
                    ruleBuilder.ruleBuilder('option', 'query_request', query_request );
                }
                */
            }

            $(document).ready(function() {

                if(!top.HAPI4){
                    //this is case of standaloe page
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>', onInit);//, < ?=json_encode($system->getCurrentUser())? > );
                }else{
                    //otherwise we take everything from parent window
                    onInit(true);
                }

            });

        </script>

    </head>
    <body>
        <div id="toolbar"><button id="btn_add_level1">Add Level 1</button><button id="btn_add_level2">Add Level 2</button><button id="btn_add_level3">Add Level 3</button></div>

        <div id="level1"></div>

        <div id="level2" style="padding-top:15px"></div>

        <div id="level3" style="padding-top:15px"></div>
    </body>
</html>
