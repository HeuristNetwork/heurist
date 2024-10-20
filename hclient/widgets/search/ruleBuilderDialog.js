/**
* ruleBuilderDialog.js:  Dialog elements for the RuleSet builder
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

/*
//new format for rules

"q":{"t":"5","lt:15":{"t":"10"}},"levels":[]

{"t":"5","lt:15":{"t":"10","f:1":"Sidor"}}
{"t":"5","lt:15":{"t":"10","plain":"Petia"}}

*/
let first_level_rty_ID = null;

function onPageInit(success) //callback function of hAPI initialization
{
    if(success)  //system is inited
    {

        let rules = window.hWin.HEURIST4.util.getUrlParameter('rules', window.location.search);
        if(!rules){
            rules = '[]';
            first_level_rty_ID = window.hWin.HEURIST4.util.getUrlParameter('rty_ID', window.location.search);  
        } 
        else rules = decodeURIComponent(rules);
        
        if(!(first_level_rty_ID>0)) first_level_rty_ID = null;
        
        //init toolbar buttons
        $('#btn_add_level1').attr('title', 'explanatory rollover' ).button().on('click', null, addLevel );

        $('#btn_save').addClass('ui-button-action').attr('title', 'explanatory rollover' ).button().on('click', 3, saveRules);

       
        
        $('#btn_help').button({icon:"ui-icon-help", showLabel:false}).on('click', 3, showHelp);
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

            rules = window.hWin.HEURIST4.util.isJSON(rules);

            if(rules!==false && rules.length>0){
                let i;
                for(i=0; i<rules.length; i++){

                    let ele = $("<div>").addClass('level1')
                            .uniqueId().insertBefore($('#div_add_level'));
                    
                    ele.ruleBuilder({level:1,     //add RuleSets builder for level 1
                        rules: rules[i],
                        onremove: function(event, data){
                            $('#'+data.id).remove();    //remove this RuleSets builder

                        }
                    })

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

    //need to add to dom first otherwise it will not filed selectmenu-parent
    let ele = $("<div>").addClass('level1')
            .uniqueId().insertBefore($('#div_add_level'));
    
    ele.ruleBuilder({level:1,
            recordtypes: first_level_rty_ID,
            onremove: function(event, data){
                $('#'+data.id).remove();
            }
    });

}

//
//  show/hide help panel
//
function showHelp(){
    let $helper = $("#helper");
    if($helper.dialog( "isOpen" )){
        $helper.dialog( "close" );
       
    }else{
        $helper.dialog( "open" );
       
    }
}

//
// create rules array as a result of this builder
//
function getRulesArray(){

    // original rule array
    // rules:[ {query:query, levels:[]}, ....  ]

    //get first level
    let rules = [];
    $.each($('.level1'), function( index, value ) {
        let subrule = $(value).ruleBuilder("getRules");
        if(!window.hWin.HEURIST4.util.isempty(subrule)) rules.push(subrule);
    });
    
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
    let res = getRulesArray();
    if(res.length>0){
        res = {mode:'apply', rules:res};
        window.close(res);
    }
}

/**
* Save rules with current search as a saved search
*/
function saveRules(){
    let res = getRulesArray();
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
