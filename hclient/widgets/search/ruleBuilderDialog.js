/**
* @file ruleBuilderDialog.js
* @brief Dialog elements for the RuleSet builder.
* @fileOverview This file provides the JavaScript logic for the RuleSet builder dialog.
* It handles the initialization of the dialog, including loading existing rules
* or starting with a default first-level rule. It manages adding new rule levels,
* saving the defined rules, and interacting with the `ruleBuilder` widget
* to construct the individual rules. It also includes functionality for
* displaying a help panel.
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/*
//new format for rules

"q":{"t":"5","lt:15":{"t":"10"}},"levels":[]

{"t":"5","lt:15":{"t":"10","f:1":"Sidor"}}
{"t":"5","lt:15":{"t":"10","plain":"Petia"}}

*/
/**
 * @type {?number}
 * @description Stores the record type ID for the first level rule, if provided as a URL parameter.
 */
let first_level_rty_ID = null;

/**
 * @function onPageInit
 * @description Callback function executed after HAPI (Heurist API) initialization.
 *              It initializes the RuleSet builder dialog, loads existing rules from URL parameters if provided,
 *              or adds a default first-level rule. Sets up toolbar buttons and help dialog.
 * @param {boolean} success - True if HAPI initialization was successful, false otherwise.
 */
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

/**
 * @function addLevel
 * @description Adds a new first-level rule builder instance to the dialog.
 *              This function is typically called to add the initial rule or when the "Add Level 1 Rule" button is clicked.
 *              It initializes the `ruleBuilder` widget for the new level.
 */
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

/**
 * @function showHelp
 * @description Toggles the visibility of the help panel dialog.
 *              If the help dialog is open, it closes it. If closed, it opens it.
 */
function showHelp(){
    let $helper = $("#helper");
    if($helper.dialog( "isOpen" )){
        $helper.dialog( "close" );
       
    }else{
        $helper.dialog( "open" );
       
    }
}

/**
 * @function getRulesArray
 * @description Collects the rule definitions from all first-level `ruleBuilder` instances in the dialog.
 * @returns {Array<Object>} An array of rule objects, where each object represents a first-level rule
 *                          (potentially with nested sub-rules) in the format expected by the `ruleBuilder` widget.
 */
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
* @function applyRules
* @description Gathers the defined rules and closes the dialog, passing the rules back
*              to the calling context with the mode set to 'apply'. This typically indicates
*              that the rules should be immediately applied to a search.
*/
function applyRules(){
    let res = getRulesArray();
    if(res.length>0){
        res = {mode:'apply', rules:res};
        window.close(res);
    }
}

/**
* @function saveRules
* @description Gathers the defined rules and closes the dialog, passing the rules back
*              to the calling context with the mode set to 'save'. This typically indicates
*              that the rules should be saved (e.g., as part of a saved search).
*/
function saveRules(){
    let res = getRulesArray();
    if(res.length>0){
        res = {mode:'save', rules:res};
        window.close(res);
    }
}


/**
 * @function updateRuleBuilder
 * @description Placeholder or deprecated function. Currently does not have any active functionality.
 *              It appears to have been intended to update `ruleBuilder` instances with new record types or query requests.
 * @param {any} rectypes - Intended to be the new record types.
 * @param {any} query_request - Intended to be the new query request.
 */
function updateRuleBuilder(rectypes, query_request){
    /*
    if(ruleBuilder && rectypes){
    ruleBuilder.ruleBuilder('option', 'recordtypes', rectypes );
    ruleBuilder.ruleBuilder('option', 'query_request', query_request );
    }
    */
}
