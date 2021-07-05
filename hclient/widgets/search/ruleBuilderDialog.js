/**
* ruleBuilderDialog.js:  Dialog elements for the RuleSet builder
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

/*
//new format for rules

"q":{"t":"5","lt:15":{"t":"10"}},"levels":[]

{"t":"5","lt:15":{"t":"10","f:1":"Sidor"}}
{"t":"5","lt:15":{"t":"10","plain":"Petia"}}

*/
var first_level_rty_ID = null;

function onPageInit(success) //callback function of hAPI initialization
{
    if(success)  //system is inited
    {

        var rules = window.hWin.HEURIST4.util.getUrlParameter('rules', window.location.search);
        if(!rules){
            rules = '[]';
            first_level_rty_ID = window.hWin.HEURIST4.util.getUrlParameter('rty_ID', window.location.search);  
        } 
        else rules = decodeURIComponent(rules);
        
console.log(first_level_rty_ID);          
        if(!(first_level_rty_ID>0)) first_level_rty_ID = null;
        
        //init toolbar buttons
        $('#btn_add_level1').attr('title', 'explanatory rollover' ).button().on('click', null, addLevel );

        $('#btn_save').addClass('ui-button-action').attr('title', 'explanatory rollover' ).button().on('click', 3, saveRules);

        //$('#btn_apply').button().on('click', 3, applyRules);
        
        var ishelp_on = window.hWin.HAPI4.get_prefs('help_on');
        ishelp_on = (ishelp_on==1 || ishelp_on==true || ishelp_on=='true');
        
        //@todo - use common helper/competency level functionality
        $('#btn_help').button({icons: { primary: "ui-icon-help" }, text:false}).on('click', 3, showHelp);
        $( "#helper" ).dialog({
            autoOpen: false, width:800,
            position: { my: "right bottom", at: "right top", of: $('#btn_help') },
            show: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            },
            hide: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            }
        });

        //create RuleSets builders in case there is parameter 'rules'
        if(!window.hWin.HEURIST4.util.isempty(rules)){

            if(!window.hWin.HEURIST4.util.isArray(rules)) rules = $.parseJSON(rules);
            
            if(rules.length>0){
                var i;
                for(i=0; i<rules.length; i++){

                    $("<div>").addClass('level1').uniqueId().ruleBuilder({level:1,     //add RuleSets builder for level 1
                        rules: rules[i],
                        onremove: function(event, data){
                            $('#'+data.id).remove();    //remove this RuleSets builder

                        }
                    }).insertBefore($('#div_add_level'));

                }
                return;
            }
        }
        
        //add first level by default
        addLevel();
    }
}

//
// add first level (init ruleBuilder widget)
//
function addLevel(){    

    $("<div>").addClass('level1').uniqueId().ruleBuilder({level:1,
            recordtypes: first_level_rty_ID,
            onremove: function(event, data){
                $('#'+data.id).remove();
            }
    }).insertBefore($('#div_add_level'));

}

//
//  show/hide help panel
//
function showHelp(){
    var $helper = $("#helper");
    if($helper.dialog( "isOpen" )){
        $helper.dialog( "close" );
        //$helper.hide( 'explode', {}, 1000 );
    }else{
        $helper.dialog( "open" );
        //$helper.show( 'drop', {}, 1000 );
    }
}

//
// create rules array as a result of this builder
//
function getRulesArray(){

    // original rule array
    // rules:[ {query:query, levels:[]}, ....  ]

    //get first level
    var rules = [];
    $.each($('.level1'), function( index, value ) {
        var subrule = $(value).ruleBuilder("getRules");
        if(!window.hWin.HEURIST4.util.isempty(subrule)) rules.push(subrule);
    });
console.log(rules);    
    
    return rules;
    /*
    var res = {};
    var rules = [];

    $.each(ruleBuilders, function( index, value ) {
    var $div = $(value);
    var qs = $div.ruleBuilder("queries"); //queries for this rule
    if(!window.hWin.HEURIST4.util.isempty(qs)){
    var level = $div.ruleBuilder('option' , 'level');

    if(window.hWin.HEURIST4.util.isnull(res[level])){
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
