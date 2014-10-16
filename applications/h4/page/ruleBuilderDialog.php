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
            if(top.HEURIST4.util.isnull(top.HEURIST4.rectypes)){
            <?php
                //fill database definitions  - remove comments for standalone usage/testing
                //print "top.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 2) ).";\n";
                //print "top.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
            ?>
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
                            $('#btn_add_level1').button().on('click', null, function(){
                                $("<div>").addClass('level1').uniqueId().ruleBuilder({level:1,     //add rule builder for level 1
                                        onremove: function(event, data){
                                              $('#'+data.id).remove();    //remove this rule builder
                                        }
                                }).appendTo($('#level1'));                
                            });
                            $('#btn_apply').button().on('click', 3, applyRules);

                        }
            }
     
            function getRulesArray(){
  
                   // original rule array
                   // rules:[ {query:query, levels:[]}, ....  ]           
                    
                   //get first level
                   var rules = []; 
                   $.each($('.level1'), function( index, value ) {
                        var subrule = $(value).ruleBuilder("getRules");
                        rules.push(subrule);  
                   }); 
                   return rules;
/*
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
*/                    
            }

            /**
            * Start search with current search
            */
            function applyRules(){
                    var res = getRulesArray();
                    if(res.length>0){
                        res = {mode:'apply', rules:res};
                        window.close(res);    
                    }
            }

            /**
            * Save rules with current search as a saved search
            */
            function saveRules(){
                    var res = getRulesArray();
                    if(res.length>0){
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
            
        </script>
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
                <button id="btn_add_level1">Add Level 1</button> <button id="btn_apply">Apply Rules</button>
            </div>
        </div>
    </body>
</html>
