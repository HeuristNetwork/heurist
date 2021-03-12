/**
* queryBuilder.js:  Dialog elements for the RuleSet builder         EXPEREMENTAL
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

function hQueryBuilder(query_original, container) {
    var _className = "QueryBuilder",
    _version   = "0.4",

    container,

    arr_tokens = [{key:'t',title:'Entity(Record) Type'},  //t,type
             {key:'f',title:'Field'},                //f,field
             {key:'links',title:'Link/relation'},    //linked_to,linkedfrom,related_to,relatedfrom,links

             {key:'',title:'-'},
             {key:'title',title:'Title'},
             {key:'ids',title:'ID'},
             {key:'url',title:'URL'},
             {key:'notes',title:'Notes'},

             {key:'added',title:'Added'},
             {key:'date',title:'Modified'},  //after, since, before
             {key:'addedby',title:'Added by user'},
             {key:'owner',title:'Owner'},  //owner,workgroup,wg

             {key:'',title:'-'},
             {key:'tag',title:'Tag(Keyword)'},         //tag, keyword, kwd
             {key:'user',title:'Bookmarked by user'}  //user, usr
             ];

    function _init(query_original, _container){

        container = _container;

        _loadQuery(query_original);

    }

    //
    //parses query and recreate query builder items
    //
    function _loadQuery(){

      //detect type of query - json or plain

      //parse query on separate tokens

      //create items

      //query not defined - create empty set
      $('<div>').queryBuilderItem().appendTo($(container));

    }

    //create query in given format from filter builder items
    function _getQuery(){

    }


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init(query_original, container);
    return that;  //returns object
}


//
// add first level (init ruleBuilder widget)
//
function addLevel(){
    $("<div>").addClass('level1').uniqueId().queryBuilder({level:1,     //add RuleSets builder for level 1
        onremove: function(event, data){
            $('#'+data.id).remove();    //remove this RuleSets builder
        }
    }).insertBefore($('#div_add_level'));
}
//
// create filters array as a result of this builder
//
function getFiltersArray(){

    // original rule array
    // rules:[ {query:query, levels:[]}, ....  ]

    //get first level
    var rules = [];
    $.each($('.level1'), function( index, value ) {
        var subrule = $(value).ruleBuilder("getRules");
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
