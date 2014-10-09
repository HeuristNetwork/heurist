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
            if(top.HEURIST4.util.isnull(top.HEURIST4.rectypes)){
            <?php
                //fill database definitions  - remove comments for standalone usage/testing
                //print "top.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 2) ).";\n";
                //print "top.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
            ?>
            }

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
                            $('#btn_apply').button().on('click', 3, applyRules);

                        }
            }
            
            /**
            * 
            */
            function addRuleBuilder(event){
                counter++;
                var level = event.data;
                var ruleBuilder = $("<div>",{id:'rb'+counter}).ruleBuilder({level:level, onremove:removeRoleBuilder}).appendTo($('#level'+level));
                ruleBuilders.push(ruleBuilder);
                
                updateButtons();
            }
            
            /**
            * 
            */
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
                    top.HEURIST4.util.showMsgDlg('You remove the last rule per level. All rules for dependent levels will be removed also. Please confirm',  _onDelete);
                }else{
                    _onDelete();
                }
            }
            
            /**
            *  update add button disability
            */
            function updateButtons(){
                var val = $('#level2').children().length>0?"enable":"disable";
                $('#btn_add_level3').button(val);
                val = $('#level1').children().length>0?"enable":"disable";
                $('#btn_add_level2').button(val);
            }
            
            function getRulesArray(){
  
/*                
         rules:[   {parent: index,  // index to top level
                    level: level,   
                    query: ],    
*/                
                
                    var res = {};
                    var rules = [];
                
                    $.each(ruleBuilders, function( index, value ) {
                        var $div = $(value);
                        var qs = $div.ruleBuilder("queries"); //queries for this rule
                        if(!top.HEURIST4.util.isempty(qs)){
                            var level = $div.ruleBuilder('option' , 'level');
                            
                            if(top.HEURIST4.util.isnull(res[level])){
                                res[level] = [];
                            }
                            res[level] = res[level].concat(qs);
                            
                            rules.push({parent: level==1?'root':(level-1),   //@todo - make rules hierarchical
                                        level: level,
                                        query: qs[0]
                            });
                        }
                    });
                    
                    return res;
                
            }

            /**
            * Start search with current search
            */
            function applyRules(){
                    var res = getRulesArray();
                    if(Object.keys(records).length>0){
                        res = {mode:'apply', rules:res};
                        window.close(res);    
                    }
            }

            /**
            * Save rules with current search as a saved search
            */
            function saveRules(){
                    var res = getRulesArray();
                    if(Object.keys(records).length>0){
                        res = {mode:'save', rules:res};
                        window.close(res);    
                    }
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
        <div style="overflow:hidden;width:100%;height:100%">
        
            <div id="toolbar" style="position:absolute;width:98%;height:2em">
                <button id="btn_add_level1">Add Level 1</button>
                <button id="btn_add_level2">Add Level 2</button>
                <button id="btn_add_level3">Add Level 3</button>
            </div>
            
            <div style="position:absolute;width:98%;top:4em;bottom:4em;overflow-y:auto">
                <div id="level1" style="padding-top:15px"></div>
            
                <div id="level2" style="padding-top:15px"></div>
            
                <div id="level3" style="padding-top:15px"></div>
            </div>
            
            <div style="position:absolute;width:98%;height:2em;bottom:10px">
                <button id="btn_apply">Apply Rules</button>
            </div>
        </div>
    </body>
</html>
