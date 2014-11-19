if(top.HEURIST4.util.isnull(top.HEURIST4.rectypes)){
    //fill database definitions  - remove comments for standalone usage/testing
    //print "top.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 2) ).";\n";
    //print "top.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
}

$(document).ready(function() {

    if(!top.HAPI4){
        //this is case of standaloe page
        var db = top.HEURIST4.util.getUrlParameter('db',location.search)
        
        top.HAPI4 = new hAPI(db, onInit);//, < ?=json_encode($system->getCurrentUser())? > );
    }else{
        //otherwise we take everything from parent window
        onInit(true);
    }

});

function addLevel(){
            $("<div>").addClass('level1').uniqueId().ruleBuilder({level:1,     //add rule builder for level 1
                onremove: function(event, data){
                    $('#'+data.id).remove();    //remove this rule builder
                }
            }).appendTo($('#level1'));                
}

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
        $('#btn_add_level1').button().on('click', null, addLevel );

        $('#btn_save').button().on('click', 3, saveRules);
        
        $('#btn_apply').button().on('click', 3, applyRules);

        //create rule builders in case there is parameter 'rules'
        //var rules = top.HEURIST4.util.getUrlParameter('rules',location.search);
        if(!top.HEURIST4.util.isempty(rules)){

            if(!top.HEURIST4.util.isArray(rules)) rules = $.parseJSON(rules);
            var i;
            for(i=0; i<rules.length; i++){

                $("<div>").addClass('level1').uniqueId().ruleBuilder({level:1,     //add rule builder for level 1
                    rules: rules[i],
                    onremove: function(event, data){
                        $('#'+data.id).remove();    //remove this rule builder
                        
                    }
                }).appendTo($('#level1'));                

            }

        }else{
            addLevel(); //add first level by default
        }



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
            