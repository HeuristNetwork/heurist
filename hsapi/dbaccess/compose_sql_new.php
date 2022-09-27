<?php
/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once (dirname(__FILE__).'/compose_sql.php');

/*
Heurist is either json array  { conjunction: [ {predicate} , {predicate}, .... ] }
or simplified plain string format

Predicat is a pair of keyword and search value

t 7 t:7  => {"t":"7"}
t:7 f:20:value f1:"value with space" => {"t":"7","f:20":"value", "f:1":"value with space"}
: means that it should have value after and before it   t  :  7  =? t:7
linkedto:[ t:4 r:6421 ]  => {"linkedto":[{"t":4},{"r":6421}]}    [] - means subquery

conjunction:  not, all (default)
any        OR                                                                                           
@todo  notall     NOT ( AND )
@todo  notany     NOT ( OR )

predicate:   keyword:value

KEYWORDS

plain            query in old plain text format
url, u:          url
notes, n:        record heder notes (scratchpad)
title:           title contains
added:           creation date
date, modified:  edition date  (rec_Modified
after, since:
before:

addedby:         added by specified user (rec_AddedByUGrpID
owner:  - owned by  (rec_OwnerUGrpID
access:  visibility outiside owner group (rec_NonOwnerVisibility

user, usr:   user id  - bookmarked by user
tag, keyword, kwd:   tag

id, ids:  id
t, type:  record type id (for rectype allowed name or concept code also)
f, field:   field id
cnt:field id  search for number of field instances

latitude, lat:
longitude, long, lng:
geo:  

hhash:


link predicates

linked_to: pointer field type : query     recordtype
linkedfrom:
related: related in any direction
relatedfrom: relation type id (termid)    recordtype
related_to:
links: recordtype
relf, r: - relation type (enum)
    relf:  - field from relationship record  (for example relf:10 - start date)
          
    "rf:245":[{"t":4},{"r":6421}] - related from organization (4) with relation type 6421


recordtype is added to link query  as first predicate


sortby,sort,s - sort phrases must be on top level array - all others will be ignored

sort options:

set, fixed     as defined in ids:            
r, rating      by bkm_Rating
p, popularity  by rec_Popularity
u, url         rec_URL
m, modified    bkm_Modified, rec_Modified
a, added       bkm_Added, rec_Added
t, title       rec_Title
rt, type       rec_RecTypeID
f, field       by dty_ID  "sortby":"f:25"

-----
VALUE

1)  Literal   "f:1":"Peter%"
2)  CSV       "ids":"1,2,3,4"
3)  WKT       "f:5":"POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"
4)  Query     "linked_to:15": [{ t:4 }, {"f:1":"Alex" } ]
"f:10": {"any":[ {t:4}, {} ] }

5) Find all records without field  "f:5":"NULL"  - TODO!!!!
6) Find all records with any value in field  "f:5":""
7) Find all records with value not equal ABC  "f:5":"-ABC"

Compare predicates
        @    fulltext (any word)
        @-   no words
        @+   all words
    OR
        -   negate value
            <> between 
            OR
            = exact  (== case sensetive)
            > <  for numeric and dates only

Valuew Prefixes to search record via registered files
 ^  file size
 @  obfuscation id
 
            
================================================

new simplified heurist query rules.

1) keywords and values are separated either by : or spaces or for subqueries by parentheses.

t 10 f1 Peter  is equal to t:10 f1:Peter and produces [{"t":"10"},{"f:1":"Peter"}]
t Person linkedto(t Place London)  is equal to  t:10 lt(t:12 London) and produces [{"t":"10"},{"lt":[{"t":"12"},{"title":"London"}]}]

2) search values follows the same rules as currently ones

a) To isolate several words value use double quotes:  t 12 "City of London"  =>  [{"t":"12"},{"title":"City of London"}]

b) Use comparison predicates as first character of search value:
f1 =Boris    =>  [{"f:1":"=Boris"}]
f9 "<100 years ago"   => [{"f:9":"<100 years ago"}]  
t 10 f20 -male  =>  [{"t":"10"},{"f:20":"-male"}]

c) If keyword is not recognized then word is assumed as value for default keyword "title"
London means title:London

Here is cheat sheet of Heurist search keywords

Record type: typename|typeid|type|t
Record header fields:  ids|id|title|added|url|notes|addedby|access
Record rec_Modified:  modified|before|after|since
Record rec_OwnerUGrpID:  workgroup|wg|owner  
Base field:    field|f      
Base field counts:    count|cnt  
Search location dtl_Geo:   geo : WKT
Search record by tags:     tag|keyword|kwd  

Search by resource/link:  linked_to|linkedto|linkto|link_to|lt    
                                         linked_from|linkedfrom|linkfrom|link_from|lf      
Search by relationships:  related_to|relatedto|rt
                                         related_from|relatedfrom|rf
Search by linked, related in both directions: related  
                                                                      links
Search for field in relationship record (#1)  relation type:  r
                                                                    any other field:   relf:XXX  

Keywords for all groups (except record header fields) are synonyms. The last one in the list is used as the default one.

Search by linked/related records should refer to subquery for these records or list of record ids.
Example:  search for person linked to London (recid 452)
t Person  linkedto(t Place London)  
[{"t":"Person"},{"lt":[{"t":"Place"},{"title":"London"}]}]  
t 10 lt 452 
                   
Sort keywords: sortby|sort|s    - sort order is applicable for first level query, it is ignored in subqueries
Logic operators:  any|all|not    -  by default predicates are logically connected by Conjunction (AND), to use Disjunction (OR) use "any" keyword with the following subquery

any(London Paris Berlin)

To test new format in UI place * in the beginning of search string.

*/


/*

select


*/
$mysqli = null;
$wg_ids = null; //groups current user is member
$publicOnly = false;
$currUserID = 0;
//$use_user_wss = false;

//keep params for debug only!
$params_global;
$top_query;

/*

[{"t":14},{"f:74":"X0"},
{"related_to:100":[{"t":"15"},{"f:80":"X1"}]},   //charge/conviction
{"relatedfrom:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:18":"X6"}]},   //persons involved

{"related_to:99":[{"t":"12"}, {"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}  //address->street(11)  and linkedfrom role(16) role of place
]

[{"t":"12"},{"linkedfrom:134":{"t":"49"}}]   places with events 
[{"t":"12"}, {"not":{"ids": [{"t":"12"},{"linkedfrom:134":{"t":"4"}}] }} ]  places not linked from events
    
*/

/**
* Parses siplified heurist query and returns it in  json format
* 
* @param String $query - heurist query in simplified notation
*/
function parse_query_to_json($query){
    
    $res = array();
    $subres = array(); //parsed subqueries
    
    if($query!=null && $query!=''){

    //1) get subqueries        /\[([^[\]]|(?R))*\]/
        $cnt = preg_match_all('/\(([^[\)]|(?R))*\)/', $query, $subqueries);

        
        if($cnt>0){
            
            $subqueries = $subqueries[0];
            foreach($subqueries as $subq){

                 //trim parenthesis in begining and end of string   
                 $subq = preg_replace_callback('/^\(|\)$/', function($m) {return '';}, $subq);
                 
                 $r = parse_query_to_json($subq);
                 $subres[] = ($r)?$r:array();
            }
            
            //for square brackets '/\[([^[\]]|(?R))*\]/'
            
            $query = preg_replace_callback('/\(([^[\)]|(?R))*\)/', 
                function($m) {
                    return ' [subquery] ';
                }, $query);            
            
            
        }
    }else{
        return false; //empty string
    }    
    
//2) split by words ignoring quotes    
    $cnt = preg_match_all('/[^\s":]+|"([^"]*)"|:{1}/',  $query, $matches);
    if($cnt>0){
        
        $matches = $matches[0];
        
        $idx = 0;

        $keyword = null;
        
//3) detect predicates  - they are in pairs: keyword:value
        foreach ($matches as $word) 
        {            
            if($word==':'){
              continue;  
            } 
            
            $word = trim($word,'"');


            if($keyword==null)
            {
                $dty_id = null;
                
                if(preg_match('/^(typename|typeid|type|t)$/', $word, $match)>0){ //rectype

                    $keyword = 't';

                }else if(preg_match('/^(ids|id|title|added|modified|url|notes|addedby|access|before)$/', $word, $match)>0){ //header fields

                    $keyword = $match[0];
                    
                }else if(preg_match('/^(workgroup|wg|owner)$/', $word, $match)>0){ //ownership (header field)
                
                    $keyword = 'owner';

                }else if(preg_match('/^(user|usr)$/', $word, $match)>0){ //ownership (header field)
                
                    $keyword = 'user';
                    
                }else if(preg_match('/^(after|since)$/', $word, $match)>0){ //special case for modified
                    
                    $keyword = 'after';

                }else if(preg_match('/^(sortby|sort|s)$/', $word, $match)>0){ //sort keyword
                    
                    $keyword = 'sortby';
                    
                }else if(preg_match('/^(field|f)(\d*)$/', $word, $match)>0){ //base field

                    $keyword = 'f';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(count|cnt|geo|file)(\d*)$/', $word, $match)>0){ //special base field

                    $keyword = $match[1];
                    $dty_id = @$match[2];

                }else if(preg_match('/^(tag|keyword|kwd)$/', $word, $match)>0){ //tags

                    $keyword = 'tag';

                }else if(preg_match('/^(linked_to|linkedto|linkto|link_to|lt)(\d*)$/', $word, $match)>0){ //resource - linked to

                    $keyword = 'lt';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(linked_from|linkedfrom|linkfrom|link_from|lf)(\d*)$/', $word, $match)>0){ //backward link

                    $keyword = 'lf';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related_to|relatedto|rt)(\d*)$/', $word, $match)>0){ //relationship to

                    $keyword = 'rt';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related_from|relatedfrom|rf)(\d*)$/', $word, $match)>0){ //relationship from

                    $keyword = 'rf';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related|links)$/', $word, $match)>0){ //link or relation in any direction

                    $keyword = $match[0];
                    
                }else if(preg_match('/^(relf|r)(\d*)$/', $word, $match)>0){ //field from relation record 

                    $keyword = $match[1];
                    $dty_id = @$match[2];
                    
                }else if(preg_match('/^(any|all|not)$/', $word, $match)>0){ //logical operation

                    $keyword = $match[0];
                
                }else{
                    $warn = 'Keywords '.$word.' not recognized';
                    //by default this is title (or add to previous value or skip pair?)
                    if($word=='[subquery]'){ 
                        $res = array_merge($res, array_shift($subres) );
                    }else{
                        
                        //if($previous_key && )
                        
                        if(false && @$res[count($res)-1]['title']){
                            $res[count($res)-1]['title'] .= (' '.$word);
                        }else{
                            array_push($res, array( 'title'=>$word ));        
                        }
                    }
                    continue;
                }

                if($keyword){
                    if($dty_id>0){
                        $keyword = $keyword.':'.$dty_id;
                    }
                }
                
            }//keyword turn
            else{
                
                if($word=='[subquery]'){ 
                    $word = array_shift($subres);
                }
                
                array_push($res, array( $keyword=>$word ));    
                
                $previous_key = $keyword;
                
                $keyword = null;
            }
        }//for
    
    }else{
        $res =  false; //no entries
    }
    
    return $res;
    
    
}//END parse_query_to_json

// $params need for
// q - json query array
// w - domain all or bookmarked or e(everything)
// limit and offset
// nested = false - to avoid nested queries - it allows performs correct count for distinct target record type (see faceted search)
// NOT USED - s - sort 
// @todo vt - visibility type 
// @todo publiconly
//
function get_sql_query_clauses_NEW($db, $params, $currentUser=null){

    global $mysqli, $wg_ids, $currUserID, $publicOnly, $params_global, $top_query;//, $use_user_wss;
    
    $params_global = $params;

    $mysqli = $db;

    if(!$params) $params = array();//$_REQUEST;
    
    if(is_array(@$params['q'])){
        $query_json = $params['q'];
    }else{
        $query_json = json_decode(@$params['q'], true);
    }
    
    // 1. DETECT CURRENT USER AND ITS GROUPS, if not logged search only all records (no bookmarks) ----------------------
    $wg_ids = array(); //may be better use $system->get_user_group_ids() ???
    if($currentUser && @$currentUser['ugr_ID']>0){
        if(@$currentUser['ugr_Groups']){
            $wg_ids = array_keys($currentUser['ugr_Groups']);
        }
        $currUserID = $currentUser['ugr_ID'];
        array_push($wg_ids, $currUserID);
        
    }else{
        $currUserID = 0;
        $params['w'] = 'all';
    }
    array_push($wg_ids, 0); // be sure to include the generic everybody workgroup

    $publicOnly = (@$params['publiconly']==1); //@todo change to vt - visibility type parameter of query

    // 2. DETECT SEARCH DOMAIN ------------------------------------------------------------------------------------------
    if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0) {    // my bookmark entries
        $search_domain = BOOKMARK;
    } else if (@$params['w'] == 'e') { //everything - including temporary
        $search_domain = EVERYTHING;
    } else if (@$params['w'] == 'nobookmark') { //all without BOOKMARK
        $search_domain = NO_BOOKMARK;
    } else {                // all records entries
        $search_domain = "a";
    }

    //$system->is_dbowner()
    //for database owner we will search records of any workgroup and view access
    //@todo UNLESS parameter owner is not defined explicitely
    if($currUserID==2 && $search_domain != BOOKMARK){
        $wg_ids = array();    
    }
    //$use_user_wss = (@$params['use_user_wss']===true);
    
    $query = new HQuery( "0", $query_json, $search_domain, $currUserID );
    $top_query = $query;
    $query->makeSQL();
    
    
    if($query->error_message){
        return array('error'=>$query->error_message);
    }

    //1. create tree of predicates
    //2. make where

    // 4c. SPECIAL CASE for USER WORKSET
    if(@$params['use_user_wss']===true && $currUserID>0){
        
        $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$currUserID.' LIMIT 1';
        if(mysql__select_value($mysqli, $q2)>0){
            $query->where_clause = '(exists (select wss_RecID from usrWorkingSubsets where wss_RecID=r0.rec_ID and wss_OwnerUGrpID='.$currUserID.'))'
                . ($query->where_clause && trim($query->where_clause)!=''? ' and '.$query->where_clause :'');
        }
    }
    
    
    // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------

    $limit = get_limit($params);

    $offset = get_offset($params);

    if(!$query->where_clause || trim($query->where_clause)==''){
        $query->where_clause = "(1=1)";
    }

    // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
    return array("from"=>" FROM ".$query->from_clause, "where"=>$query->where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

}
                                                                  
class HQuery {

    var $from_clause = '';
    var $where_clause = '';
    var $sort_clause = '';
    var $recVisibilityType;
    var $parentquery = null;
    
    var $error_message = null;

    var $top_limb = array();
    var $sort_phrases;
    var $sort_tables; // sorting may require the introduction of more tables

    var $currUserID;
    var $search_domain;

    var $level = "0";
    var $cnt_child_query = 0;
    
    var $fixed_sortorder = null;


    function __construct($level, $query_json, $search_domain=null, $currUserID=null) {

        $this->level = $level;
        $this->search_domain = $search_domain;
        $this->currUserID = $currUserID;

        $this->top_limb = new HLimb($this, "all", $query_json);
        // $top_limbs =

        //find search phrases
        if($level==0){
            $this->extractSortPharses( $query_json );
        }
    }

    function makeSQL(){

        global $publicOnly, $wg_ids, $params_global; //, $mysqli, $use_user_wss;

        $res = $this->top_limb->makeSQL(); //it creates where_clause and fill tables array
        
        if($this->top_limb->error_message){
             $this->error_message = $this->top_limb->error_message;
             return;
        }else if($this->search_domain!=null){

            if ($this->search_domain == NO_BOOKMARK){
                $this->from_clause = 'Records r'.$this->level.' ';
            }else if ($this->search_domain == BOOKMARK) {
                $this->from_clause = 'usrBookmarks b LEFT JOIN Records r'.$this->level.' ON b.bkm_recID=r'.$this->level.'.rec_ID ';
            }else{
                $this->from_clause = 'Records r'.$this->level.' LEFT JOIN usrBookmarks b ON b.bkm_recID=r'.$this->level.'.rec_ID and b.bkm_UGrpID='.$this->currUserID.' ';
            }

        }else{
            $this->from_clause =  "Records r".$this->level;
        }
        
        //add usrRecPermission join
        if($this->level=='0' && !$publicOnly && $this->currUserID>0 && $this->currUserID!=2){
                $this->from_clause = $this->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';
        } 
        /*           
        if($this->level=='0' && $this->currUserID>0 && $use_user_wss===true)
        {
            $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$this->currUserID.' LIMIT 1';
            if(mysql__select_value($mysqli, $q2)>0){
                $this->from_clause = $this->from_clause.' LEFT JOIN usrWorkingSubsets ON wss_RecID=r0.rec_ID and wss_OwnerUGrpID='.$this->currUserID.' ';
            }
        }*/
            
        // add tables from other    
        foreach($this->top_limb->tables as $table){
            $this->from_clause =  $this->from_clause.", ".$table; //.$this->level;    ART20150807
            if($table=="recDetails rd"){
                $this->where_clause = " (r".$this->level.".rec_ID=rd".$this->level.".dtl_RecID) AND ";
            }
        }
        $this->where_clause = $this->where_clause . $this->top_limb->where_clause." ";

        //add owner access restriction @todo look at parmeter owner
        if($this->level=='0'){ //apply this restriction for top level only
            if($publicOnly){
                $recVisibilityType = "public";
            }else{
                $recVisibilityType = null;
            }

            $where2 = '';    
            
            
            if($recVisibilityType && $recVisibilityType!="hidden"){
                $where2 = '(r0.rec_NonOwnerVisibility="'.$recVisibilityType.'")';  //'pending','public','viewable'
            }else{
                $where2_conj = '';
                if($recVisibilityType){ //hidden
                    $where2 = '(r0.rec_NonOwnerVisibility="hidden")';
                    $where2_conj = ' and ';
                }else if($this->currUserID!=2){ //by default always exclude "hidden" for not database owner
                    //$where2 = '(not r0.rec_NonOwnerVisibility="hidden")';
                
                    $where2 = '(r0.rec_NonOwnerVisibility in ("public","pending"))';
                    if ($this->currUserID>0){ //logged in
                    
                        //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                        $where2 = $where2.' or (r0.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in ('
                                .join(',', $wg_ids).')))';
                    }
                    
                    $where2_conj = ' or ';
                }else if ($this->search_domain != BOOKMARK){ //database owner can search everything (including hidden)
                    $wg_ids = array();
                }
                
                if($this->currUserID>0 && count($wg_ids)>0){
                    $where2 = '( '.$where2.$where2_conj.'r0.rec_OwnerUGrpID ';
                    if(count($wg_ids)>1){
                        $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';    
                    }else{
                        $where2 = $where2 .' = '.$wg_ids[0] . ' )';    
                    }
                }
            }
            
            if($this->search_domain!=EVERYTHING){
                $where2 = '(not r0.rec_FlagTemporary)'.($where2?' and ':'').$where2;
            }

            if(trim($where2)!=''){
                if(trim($this->where_clause)!=''){
                    $this->where_clause = $this->where_clause. ' and ' . $where2;
                }else{
                    $this->where_clause = $where2;
                }
            }
            
            //apply sort clause for top level only
            $this->createSortClause();
        }

    }
    
    //
    // sort phrases must be on top level array - all others will be ignored
    // {"sort":"rt"}
    //
    function extractSortPharses( $query_json ){
        
        $this->sort_phrases = array();
        
        foreach ($query_json as $key => $value){
            if(is_numeric($key) && is_array($value)){  //this is sequental array
                $key = array_keys($value);
                $key = $key[0];
                if(is_string($value[$key])){
                    $value = strtolower($value[$key]);
                }else{
                    continue;
                }
            }
            if( ($key == 'sortby' || $key == 'sort' || $key == 's')
                &&!in_array($value, $this->sort_phrases)){ //this is sort phrase
                
                        $this->sort_phrases[] = $value;
            }
        }
        
    }
    
    //
    // {"sort":"f:233"}  {"sort":"-title"}  {"sort":"set:4,5,1"}  
    //    
    function createSortClause() {
        
        global $mysqli;
        
        $sort_fields = array();
        $sort_expr = array();
        
        
        foreach($this->sort_phrases as $subtext){
        
            // if sortby: is followed by a -, we sort DESCENDING; if it's a + or nothing, it's ASCENDING
            $scending = '';
            if ($subtext[0] == '-') {
                $scending = ' DESC ';
                $subtext = substr($subtext, 1);
            } else if ($subtext[0] == '+') {
                $subtext = substr($subtext, 1);
            }
            $dty_ID = null;
            if(strpos($subtext,':')>0){
                list($subtext,$dty_ID) = explode(':',$subtext);
            }
      
            switch (strtolower($subtext)) {
                case 'set': case 'fixed': //no sort - returns as is
                
                        if($this->fixed_sortorder){
                            $sort_fields[] = 'r0.rec_ID';
                            $sort_expr[] = 'FIND_IN_SET(r0.rec_ID, \''.$this->fixed_sortorder.'\')';
                        }
                        break;
                case 'r': case 'rating':
                    if ($this->search_domain == BOOKMARK) {
                        if(!in_array('bkm_Rating', $sort_fields)) {
                            $sort_fields[] = 'bkm_Rating';   
                            $sort_expr[] = 'bkm_Rating'.$scending;   
                        }
                        break;
                    }
                case 'p': case 'popularity':
                    if(!in_array('r0.rec_Popularity', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_Popularity';   
                        $sort_expr[] = 'r0.rec_Popularity'.$scending;   
                    }
                    if(!in_array('r0.rec_ID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_ID';   
                        $sort_expr[] = 'r0.rec_ID'.$scending;   
                    }
                    break;
                case 'u': case 'url':
                    if(!in_array('r0.rec_URL', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_URL';   
                        $sort_expr[] = 'r0.rec_URL'.$scending;   
                    }
                    break;
                case 'm': case 'modified':
                    $fld = ($this->search_domain == BOOKMARK)?'bkm_Modified':'r0.rec_Modified';
                    if(!in_array($fld, $sort_fields)) {
                        $sort_fields[] = $fld;   
                        $sort_expr[] = $fld.$scending;   
                    }
                    break;
                case 'a': case 'added':
                    $fld = ($this->search_domain == BOOKMARK)?'bkm_Added':'r0.rec_Added';
                    if(!in_array($fld, $sort_fields)) {
                        $sort_fields[] = $fld;   
                        $sort_expr[] = $fld.$scending;   
                    }
                    break;
                case 't': case 'title':
                    if(!in_array('r0.rec_Title', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_Title';   
                        $sort_expr[] = 'r0.rec_Title'.$scending;   
                    }
                    break;
                case 'id': case 'ids':
                    if(!in_array('r0.rec_ID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_ID';   
                        $sort_expr[] = 'r0.rec_ID'.$scending;   
                    }
                    break;
                case 'rt': case 'type':
                    if(!in_array('r0.rec_RecTypeID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_RecTypeID';   
                        $sort_expr[] = 'r0.rec_RecTypeID'.$scending;   
                    }
                    break;

                case 'hie': //special case for Hamburg EARLY ISLAMIC EMPIRE

                    if(!in_array('hie', $sort_fields)) {
                        $sort_expr[] = 
    '(select GREATEST(getTemporalDateString(ifnull(sd2.dtl_Value,\'0\')), getTemporalDateString(ifnull(sd3.dtl_Value,\'0\')))' 
                        .' from recDetails sd1'
                        .' left join recDetails sd2 on sd1.dtl_Value=sd2.dtl_RecID and sd2.dtl_DetailTypeID=9'
                        .' left join recDetails sd3 on sd1.dtl_Value=sd3.dtl_RecID and sd3.dtl_DetailTypeID=10'
                        .' where r0.rec_ID=sd1.dtl_RecID and sd1.dtl_DetailTypeID=293 limit 1) DESC';                    
                        $sort_fields[] = 'hie';   
                    }
                    
                    break;
                case 'f': case 'field':
                    if($dty_ID>0 && !in_array($dty_ID, $sort_fields)) {
                       
                        $field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dty_ID);
                        
                        if($field_type!=null){ //field type found

                            if($field_type=='enum'){
                                $sortby = 'ifnull((select trm_Label from recDetails left join defTerms on trm_ID=dtl_Value where dtl_RecID=r'
                                    .$this->level.'.rec_ID and dtl_DetailTypeID='.$dty_ID
                                    .' ORDER BY trm_Label limit 1), "~~") ';
                            }else{                            
                                $sortby = 'ifnull((select dtl_Value from recDetails where dtl_RecID=r'
                                        .$this->level.'.rec_ID and dtl_DetailTypeID='.$dty_ID
                                        .' ORDER BY dtl_Value limit 1), "~~") ';
                            }
                            
                            if($field_type=='float'){
                                $sortby = 'CAST('.$sortby.' AS DECIMAL) ';       
                            }else if ($field_type=='integer'){
                                $sortby = 'CAST('.$sortby.' AS SIGNED) ';       
                            }

                            $sort_expr[] = $sortby . $scending . ', r'.$this->level . '.rec_Title';
                            $sort_fields[] = $dty_ID;
                        }
                    }
                    
            }//switch
        
        }//foreach
                
        //
        if(count($sort_expr)>0){
            $this->sort_clause = ' ORDER BY '.implode(',',$sort_expr);
        }
        
    }

}

/**
*
*
*/
class HLimb {

    var $parent;           // query
    var $limbs = array();  // limbs and predicates
    var $conjunction = "all"; //and

    //results
    var $tables = array();
    var $where_clause = "";
    var $error_message = null;

    var $allowed = array("all"=>" AND ","any"=>" OR ","not"=>"NOT ");

    //besides  not,any
    //

    function __construct(&$parent, $conjunction, $query_json) {

        $this->parent = &$parent;
        $this->conjunction = $conjunction;

        //echo "<br>".(print_r($query_json, true));
        if(is_array($query_json))
        foreach ($query_json as $key => $value){

            //echo "<br>key=".$key."  ".(print_r($value,true));
            if(is_numeric($key) && is_array($value)){  //this is sequental array
                $key = array_keys($value);
                $key = $key[0];
                $value = $value[$key];
            }

            //echo "<br>key=".$key." value=".(print_r($value,true));

            if( array_key_exists($key, $this->allowed) ){ //this is limb
                $limb = new HLimb($this->parent, $key, $value);
                //echo "<br>".$limb->conjunction."  count=".count($limb->limbs);
                if(count($limb->limbs)>0){
                    //do not add empty limb
                    array_push( $this->limbs, $limb );
                }
            }else{ //this is predicate

                $predicate = new HPredicate($this->parent, $key, $value, count($this->limbs) );

                //echo  $key."  ".$value."  ".$predicate->valid;

                if($predicate->valid){
                    array_push( $this->limbs,  $predicate);
                }
            }
        }
    }

    
    function setRelationPrefix($val){
        foreach ($this->limbs as $ind=>$limb){
            $res = $limb->setRelationPrefix($val);
        }
    }
    //
    // fills $tables and $where_clause
    //
    function makeSQL(){

        $cnj = $this->allowed[$this->conjunction];
        $where = "";
        $from = "";

        if($this->conjunction=="not"){

            $res = $this->limbs[0]->makeSQL();
            if($res){
                $where = $cnj."(".$res["where"].")";
                $this->addTable(@$res["from"]);
            }else{
                $this->error_message = $this->limbs[0]->error_message;
                return null;
            }
            

        }else{

            $wheres = array();
            $cnt = count($this->limbs)-1;
            foreach ($this->limbs as $ind=>$limb){
                $res = $limb->makeSQL();

                //echo print_r($res, true)."<br>";

                if($res && @$res["where"]){
                    $this->addTable(@$res["from"]);
                    if(false && $cnt==1){
                        $where = $res["where"];
                    }else{
                        array_push($wheres, "(".$res["where"].")");
                        //$where = $where."(".$res["where"].")";
                        //if($ind<$cnt) $where = $where.$cnj;
                    }
                }else if($limb->error_message){
                    $this->error_message = $limb->error_message;
                    return null;
                }
            }

            //IMPORTANT!!!!!!!!
            if(count($wheres)>0){  //@TODO!  this is temporal solution!!!!!
                $where = implode($cnj, $wheres);
                
//DEBUG error_log("TEST $cnj >>> ".$where);
                
            }
        }


        //print $from."<br>";

        //$where = $where."}";

        $this->where_clause = $where;
        $res = array("from"=>$this->tables, "where"=>$where);
        
        return $res;        
    }

    function addTable($table){
        if($table){
            if(is_array($table)){
                $this->tables = array_merge($this->tables, $table);
                $this->tables = array_unique($this->tables);
            }else if(!in_array($table, $this->tables)){
                array_push($this->tables, $table);
            }
        }
    }

}

// ===========================
//
//
class HPredicate {

    var $pred_type;
    var $field_id = null;
    var $field_type = null;

    var $value;
    var $valid = false;
    var $query = null;
    
    //for related_to, related_from 
    var $relation_types = null; 
    var $relation_fields = null; // field in relationshio record: array(field_id=>value)
    var $relation_prefix = ''; //prefix for recLinks
    
    var $field_list = false; //list of id values used in predicate IN (val1, val2, val3... )
    
    var $error_message = null;    

    var $qlevel;
    var $index_of_predicate;
    //@todo - remove?
    var $negate = false;
    var $exact = false;
    var $fulltext = false;
    var $case_sensitive = false;
    var $lessthan = false;
    var $greaterthan = false;

    var $allowed = array('t','type','typeid','typename',
            'ids','id','title','added','modified','url','notes',
            'after','before', 
            'addedby','owner','access',
            'user','usr',
            'f','field',
            'count','cnt','geo', 'file',
            'lt','linked_to','linkedto','lf','linkedfrom',
            'related','rt','related_to','relatedto','rf','relatedfrom',
            'links','plain',
            'tag','keyword','kwd');
    /*
    notes, n:        record heder notes (scratchpad)
    title:           title contains
    url              record header url (rec_URL)
    added:           creation date
    date, modified:  edition date
    after, since:
    before:
    plain            query in old plain text format

    addedby:         added by specified user (rec_AddedByUGrpID)
    owner:  - owned by  (rec_OwnerUGrpID)
    access:  visibility outiside owner group (rec_NonOwnerVisibility)

    user, usr:   user id  - bookmarked by user
    tag, keyword, kwd:   tag

    id, ids:  id
    t, type:  record type id
    f, field:   field id
    */


    function __construct(&$parent, $key, $value, $index_of_predicate) {

        $this->parent = &$parent;
        $this->qlevel = $this->parent->level; //
        $this->index_of_predicate = $index_of_predicate;
        
        $key = explode(":", $key);
        $this->pred_type  = $key[0];
        $ll = count($key);
        if($ll>1){ //get field ids "f:5" -> 5
            $this->field_id = $key[$ll-1];
        }

        if( in_array($this->pred_type, $this->allowed) ){
            $this->value = $value;

            if(is_array($value) &&  count($value)>0 && 
                !(is_numeric(@$value[0]) || is_string(@$value[0])) )
            { //subqueries
                //special bahvior for relation - extract reltypes and record ids
                $p_type = strtolower($this->pred_type);
                if($p_type=='related_to' || $p_type=='relatedto' || $p_type=='rt' ||
                   $p_type=='related_from' || $p_type=='relatedfrom' || $p_type=='rf')
                {
                    
                    $this->relation_fields = array();
                    $this->value = array();
                    
                    
                    // extract all with predicate type "r" - this is either relation type or other fields from relatinship record
                    // "rf:245":[{"t":4},{"r":6421}] - related from organization (4) with relation type 6421
                    foreach($value as $idx=>$val){
                        if($idx==='r'){ //for relation type
                            
                            $this->relation_types = prepareIds($val);
                            
                        }else if (is_array($val) && @$val['r']) //for relation type
                        {
                            $val = $val['r'];
                            $this->relation_types = prepareIds($val);
                            
                            //remove from array
                            //array_splice($this->value, $idx, 1);
                            //break;

                        //for fields in relation record
                        }else if(strpos($idx,'r:')===0 || strpos($idx,'relf:')===0){  //that's for {"r:10":10,,}
                               //fields in relationship record
                               //{"r:10":">2010"}
                               $rel_field = $idx;
                               $rel_field = strpos($rel_field,'r:')===0
                                                    ?str_replace('r:','f:',$rel_field)
                                                    :str_replace('relf:','f:',$rel_field);    
                               $this->relation_fields[$rel_field] = $val;       
                        
                        }else if(is_array($val) && (@array_keys($val)[0]==='r:' || @array_keys($val)[0]==='relf:')){  //that's for [{"r:10":10},{}]
                            //{"t":10,"rf:245":[{"t":4},{"r":6421},{"relf:10":">2010"}]}}
                               $rel_field = array_keys($val)[0];  
                               $rel_field2 = strpos($rel_field,'r:')===0
                                                    ?str_replace('r:','f:',$rel_field)
                                                    :str_replace('relf:','f:',$rel_field);    
                            
                                $this->relation_fields[$rel_field2] = $val[$rel_field];       
                        }else{
                            $this->value[$idx] = $val;
                        }
                    }
                    $value = $this->value;
                    
                    
                    if(count($this->relation_fields)>0){
                        $this->relation_fields = new HLimb($this->parent, 'all', $this->relation_fields);    
                    }else{
                        $this->relation_fields = null;
                    }


                    
                    
                    // related to particular records
                    foreach($value as $idx=>$val){
                        if($idx==='ids' || (is_array($val) && @$val['ids']) ){
                            if(is_array($val) && @$val['ids']) $val = $val['ids'];
                            $this->value = $val;
                            $value = array(); //reset
                            break;
                        }
                    }    
                }
                if(count($value)>0){
            
                    $level = $this->parent->level."_".$this->parent->cnt_child_query;
                    $this->parent->cnt_child_query++;

                    $this->query = new HQuery( $level, $value );
                }
            }
            $this->valid = true; //@todo
        }

    }
    
    function getTopLevelQuery(){
        if($this->parent->level==0){
            return $this->parent;
        }else{
            return getTopLevelQuery($parent->parent);
        }
    }
    
    function setRelationPrefix($val){
        $this->relation_prefix = $val;
    }

    function makeSQL(){

        global $mysqli, $top_query;

        /*if(false && $this->query){

        $this->query->makeSQL();
        $query = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
        //return array("from"=>"", "where"=>$this->pred_type.$query);
        }*/

        switch (strtolower($this->pred_type)) {
            case 'plain':            //query in old plain text format
                //[{"t":"12"},{"plain":"0"},{"sortby":"t"}]

            
                $query = parse_query($top_query->search_domain, urldecode($this->value), null, null, $top_query->currUserID);

                $tab = 'r'.$this->qlevel;
                             
                $where_clause = $query->where_clause;
                $where_clause = str_replace('TOPBIBLIO',$tab,$where_clause);  //need to replace for particular level
                $where_clause = str_replace('TOPBKMK','b',$where_clause);
                
            
                return array("where"=>$where_clause);
            case 'typename':
            case 'typeid':
            case 'type':
            case 't':

                if(strpos($this->value, '-')===0){
                    $this->negate = true;
                    $this->value = substr($this->value, 1);
                }

                $eq = ($this->negate)? '!=' : '=';
                if (is_numeric($this->value)) {
                    if(intval($this->value)==0){
                        return null;
                    }else{
                        $res = "$eq ".intval($this->value);    
                    }
                    
                }
                else if (preg_match('/^\d+(?:,\d*)+$/', $this->value)) {
                    // comma-separated list of defRecTypes ids
                    $in = ($this->negate)? 'not in' : 'in';
                    $res = "$in (" . $this->value . ")";
                }
                else if (preg_match('/^\d+-\d+$/', $this->value)) {
                    $local_rtyid = ConceptCode::getRecTypeLocalID($this->value);
                    if($local_rtyid>0){
                        $res = "$eq $local_rtyid";
                    }else{
                        $res = "=0"; //localcode not found
                    }
                }
                else
                {
                    $res = "$eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
                }
                $res = "r".$this->qlevel.".rec_RecTypeID ".$res;
                return array("where"=>$res);

            case 'geo':
                
                return $this->predicateSpatial();

            case 'file':
            
                $this->pred_type = 'field';
                $this->field_type = 'file';
                return $this->predicateField();
            
            case 'count':
            case 'cnt':
            
                if(!$this->field_id) return null;
                $this->pred_type = strtolower($this->pred_type);
                
            case 'field':
            case 'f':

                if(!$this->field_id){ //field type not defined
                    return $this->predicateAnyField();
                }else{
                    return $this->predicateField();
                }
                
            case 'after':
            case 'before':
            case 'modified':
            case 'added':
            case 'title':
            case 'url':
            case 'notes':
            case 'addedby':
            case 'owner':
            case 'access':
            
                $this->pred_type = strtolower($this->pred_type);
                return $this->predicateField();
                
            case 'ids':
            case 'id':

                $res = $this->predicateRecIds();

                return $res;

            case 'user':
            case 'usr':
                //bookmarked by 
                //$this->search_domain = BOOKMARK;
                return $this->predicateBookmarked();
                
            case 'tag':
            case 'keyword':
            case 'kwd':
                
                return $this->predicateKeywords();

            case 'lt':
            case 'linked_to':
            case 'linkedto':

                return $this->predicateLinkedTo();

            case 'lf':
            case 'linkedfrom':

                return $this->predicateLinkedFrom();
                
            case 'related':

                return $this->predicateRelated();

            case 'rt':
            case 'related_to':
            case 'relatedto':

                return $this->predicateRelatedDirect(false);

            case 'rf':
            case 'relatedfrom':

                return $this->predicateRelatedDirect(true);

            case 'links':

                return $this->predicateLinks();
        }

        return null;
        //return array("from"=>"", "where"=>$this->key."=".$this->value);


    }

    function cleanQuotedValue($val) {
        if (strlen($val)>0 && $val[0] == '"') {
            if ($val[strlen($val)-1] == '"')
                $val = substr($val, 1, -1);
            else
                $val = substr($val, 1);
            return preg_replace('/ +/', ' ', trim($val));
        }

        return $val;
    }
    
    //
    //
    //
    function predicateSpatial(){
        
        $p = "rd".$this->qlevel.".";
        $p = "";

        $res = "exists (select dtl_ID from recDetails $p "
            .' where r'.$this->qlevel.'.rec_ID='.$p.'dtl_RecID AND ';
        
        if($this->isEmptyValue()){
            $res = ' NOT '.$res.$p.'dtl_Geo IS NOT NULL)'; //not defined
        }else if($this->value==''){  
            $res = $res.$p.'dtl_Geo IS NOT NULL)'; //any not null value
        }else {
            $res = $res
            .$p.'dtl_Geo is not null AND ST_Contains(ST_GeomFromText(\''.$this->value.'\'), '
            .$p.'dtl_Geo) limit 1)';  //MBRContains
        }
        return array("where"=>$res);
    }

    //
    //
    //
    function predicateField(){
        global $mysqli;

        $p = "rd".$this->qlevel.".";
        $p = "";
        
        $several_ids = prepareIds($this->field_id); //getCommaSepIds - returns validated string
        if(is_array($several_ids) && count($several_ids)>0){
            $this->field_id = $several_ids[0];    
        }else{
            $several_ids = null;
        }
        
        if($this->pred_type=='count' || $this->pred_type=='cnt'){
            $this->field_type = 'link'; //integer without quotes
        }else if($this->field_type == 'file'){
            
        }else if(intval($this->field_id)>0){
            //find field type - @todo from cache
            
            $this->field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$this->field_id);
            
            if($this->field_type=='relmarker'){
                return $this->predicateRelatedDirect(false);    
            }
        }else{
            $this->field_type = 'freetext';
        }
        
        $is_empty = $this->isEmptyValue(); //search for empty or not defined value
        
        
        //@todo future - here we need to add code that will
        // call $this->getFieldValue() inside the loop if $this->value is array
        // any:[val1,val2,...] or all:[val1,val2,...] or [val1,val2,...] - by default use any
        //
        
        $val = $this->getFieldValue();
        if(!$val) return null;
        
        $ignoreApostrophe = false;
        
        $sHeaderField = null;
        
        if($this->pred_type=='title' || $this->field_id=='title'){
            
            $sHeaderField = 'rec_Title';
            $ignoreApostrophe = (strpos($val, 'LIKE')==1);

        }else if($this->pred_type=='added' || $this->field_id=='added'){

            $sHeaderField = "rec_Added";
            
        }else if($this->pred_type=='modified' || $this->pred_type=='after' || $this->pred_type=='before' || $this->field_id=='modified'){

            $sHeaderField = "rec_Modified";
            
        }else if($this->pred_type=='url' || $this->field_id=='url'){
            
            $sHeaderField = "rec_URL";

        }else if($this->pred_type=='notes' || $this->field_id=='notes'){
            
            $sHeaderField = 'rec_ScratchPad';
            $ignoreApostrophe = (strpos($val, 'LIKE')==1);
            
        }else if($this->pred_type=='addedby' || $this->field_id=='addedby'){
            
            $sHeaderField = 'rec_AddedByUGrpID';
            
        }else if($this->pred_type=='owner' || $this->field_id=='owner'){
            
            $sHeaderField = 'rec_OwnerUGrpID';
            
        }else if($this->pred_type=='access' || $this->field_id=='access'){
            
            $sHeaderField = "rec_NonOwnerVisibility";
            
        }else if($this->pred_type=='rectype' || $this->field_id=='rectype' ||
                 $this->pred_type=='typeid' || $this->field_id=='typeid'){

            $sHeaderField = "rec_RecTypeID";

        }else if($this->pred_type=='ids' || $this->field_id=='ids'){
            
            $sHeaderField = "rec_ID";

        }
            
          
        if($sHeaderField){    
            
            if($this->pred_type=='f') $this->pred_type = $this->field_id;
            
            if($is_empty){
                $res = "(r".$this->qlevel.".$sHeaderField is NULL OR r".$this->qlevel.".$sHeaderField='')";    
            }else{
                $ignoreApostrophe = $ignoreApostrophe && (strpos($val,"'")===false);
                
                if($this->fulltext){
                    
                    if($sHeaderField=='rec_Title'){
                        //execute fulltext search query
                        $res = $res.'(MATCH(r'.$this->qlevel.'.'.$sHeaderField.') '.$val.')';
                    }else{
                        $this->error_message = 'Full text search is allowed for rec_Title only';
                        return null;
                    }
                }else
                if($ignoreApostrophe){
                    $res = "( replace( r".$this->qlevel.".$sHeaderField, \"'\", \"\")".$val.")";     
                }else{
                    $res = "(r".$this->qlevel.".$sHeaderField ".$val.")";    
                }
            }
            
            
        }else if( $is_empty ){ //search for records where field value is not defined
            
            $res = "NOT exists (select dtl_ID from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p.' dtl_DetailTypeID';
            if($several_ids && count($several_ids)>0){
                $res = $res.(count($several_ids)>1
                        ?' IN ('.implode(',',$several_ids).')'
                        :'='.$several_ids[0])
                        .')';    
            }else{
                $res = $res.'='.$this->field_id.')';
            }

        
        }else if($this->pred_type=='tag' || $this->field_id=='tag'){
            // it is better to use kwd keyword instead of f:tag
            
            $res = "(r".$this->qlevel.".rec_ID IN ".$val.")"; 
        
        }else if($this->pred_type=='count' || $this->pred_type=='cnt'){ //search for records where field occurs N times (repeatable values)

            $res = "(select count(dtl_ID) from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p.'dtl_DetailTypeID';
        
            if($several_ids && count($several_ids)>0){
                $res = $res.(count($several_ids)>1
                        ?' IN ('.implode(',',$several_ids).')'
                        :'='.$several_ids[0])
                        .')';    
            }else{
                $res = $res.'='.$this->field_id.')';
            }
            
            $res = $res.$val; //$val is number of occurences
            
        }else{
            
            if($this->field_type == 'date' && trim($this->value)!='') { //false && $this->isDateTime()){
                $field_name = 'getTemporalDateString('.$p.'dtl_Value) ';
                //$field_name = 'str_to_date(getTemporalDateString('.$p.'dtl_Value), "%Y-%m-%d %H:%i:%s") ';
            }else if($this->field_type == 'file'){
                $field_name = $p."dtl_UploadedFileID ";  //only for search non empty values
            }else{
                
                $field_name = $p."dtl_Value ";
                $ignoreApostrophe = ((strpos($val, 'LIKE')==1) && (strpos($val,"'")===false));
                if($ignoreApostrophe){
                    $field_name = 'replace('.$field_name.", \"'\", \"\") ";                   
                }
            }

            if($this->relation_prefix){ //special case for search related records by values of relationship field
                $recordID = $this->relation_prefix.'.rl_RelationID';
            }else{
                $recordID = 'r'.$this->qlevel.'.rec_ID';
            }

            
            if($this->field_id>0){ //field id defined (for example "f:25")
                
                if($this->fulltext){
                    $res = 'select dtl_RecID from recDetails where dtl_DetailTypeID';
                }else{
                    $res = "exists (select dtl_ID from recDetails ".$p." where $recordID=".$p."dtl_RecID AND "
                    .$p.'dtl_DetailTypeID';
                    
                    if($this->negate && ($this->field_type=='enum' || $this->field_type=='relationtype')){
                        $res = 'NOT '.$res;       
                    }
                }
                
                if($several_ids && count($several_ids)>0){
                    $res = $res.(count($several_ids)>1
                            ?' IN ('.implode(',',$several_ids).')'
                            :'='.$several_ids[0]);
                            
                }else{
                    $res = $res.'='.$this->field_id;
                }
                
                if($this->fulltext){
                    //execute fulltext search query
                    $res = $res.' AND MATCH(dtl_Value) '.$val;
                    $list_ids = mysql__select_list2($mysqli, $res);
                    
                    if($list_ids && count($list_ids)>0){
                        $res = $recordID
                            .(count($list_ids)>1
                                ?' IN ('.implode(',',$list_ids).')'
                                :'='.$list_ids[0]);
                    }else{
                        $res = '(1=0)'; //nothing found    
                    }
                    
                }else{
                    $res = $res.' AND '.$field_name.$val.')';        
                }
            }else{
                //field id not defined - at the moment used for search via registered file
                $res = 'select dtl_RecID from recDetails where '.$field_name.$val;
                $list_ids = mysql__select_list2($mysqli, $res);

                if($list_ids && count($list_ids)>0){
                    $res = $recordID
                        .(count($list_ids)>1
                            ?' IN ('.implode(',',$list_ids).')'
                            :'='.$list_ids[0]);
                }else{
                    $res = '(1=0)'; //nothing found    
                }
                
            }
            
        }

        return array("where"=>$res);  ///"from"=>"recDetails rd",

    }

    //
    //
    //
    function predicateAnyField(){
        global $mysqli;
        
        $p = "rd".$this->qlevel.".";
        $p = "";

        $keep_val = $this->value;
        
        $this->field_type = 'enum';
        $val_enum = $this->getFieldValue();
        
        $this->field_type = 'freetext';
        $val_wo_prefixes = $this->value;
        $this->value = $keep_val;
        $val = $this->getFieldValue();
        if(!$val) return null;
        
        $field_name1 =  $p.'dtl_Value ';
        $field_name2 =  'link.rec_Title ';
        
        $ignoreApostrophe = (strpos($val, 'LIKE')==1);
        if($ignoreApostrophe){
            $field_name1 = 'replace('.$field_name1.", \"'\", \"\") ";                   
            $field_name2 = 'replace('.$field_name2.", \"'\", \"\") ";                   
        }
        
        if($this->fulltext){
                
            
                //execute fulltext search query
                $res = 'select dtl_RecID from recDetails '
                . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
                . ' left join Records link on dtl_Value=link.rec_ID '
                .' where if(dty_Type != "resource", '
                    .' if(dty_Type="enum", dtl_Value'.$val_enum 
                       .', MATCH(dtl_Value) '.$val
                       .'), '.$field_name2.' LIKE "%'.$val_wo_prefixes.'%")'; 
                
                $list_ids = mysql__select_list2($mysqli, $res);
                
                if($list_ids && count($list_ids)>0){
                    $res = 'r'.$this->qlevel.'.rec_ID'
                        .(count($list_ids)>1
                            ?' IN ('.implode(',',$list_ids).')'
                            :'='.$list_ids[0]);
                }else{
                    $res = '(1=0)'; //nothing found    
                }
                
            
            
        }else{

            $res = 'exists (select dtl_ID from recDetails '.$p
            . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
            . ' left join Records link on '.$p.'dtl_Value=link.rec_ID '
            .' where r'.$this->qlevel.'.rec_ID='.$p.'dtl_RecID '
            .'  and if(dty_Type != "resource", '
                    .' if(dty_Type="enum", '.$p.'dtl_Value'.$val_enum 
                       .', '.$field_name1.$val
                       .'), '.$field_name2.$val.'))';   //'LIKE "%'.$mysqli->real_escape_string($this->value).'%"))';
                   
        }

        return array("where"=>$res);
    }

    //
    //
    //
    function predicateRecIds(){

        global $top_query, $params_global;
        $not_nested = (@$params_global['nested']===false);
        
        $this->field_type = "link";
        $p = $this->qlevel;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                if($not_nested){
                    $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                    $top_query->top_limb->addTable($this->query->from_clause);
                }else{
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if(strpos($val,'between')===false){
                
                if(!$this->field_list){
                    $val = "=0";
                }else if ($p==0){
                    $cs_ids = getCommaSepIds($this->value);
                    if ($cs_ids && strpos($cs_ids, ',')>0) {  
                        $top_query->fixed_sortorder = $cs_ids;
                    }
                }
            }
        }

        $where = "r$p.rec_ID".$val;

        return array("where"=>$where);

    }

    
    //
    //
    //
    function predicateBookmarked(){
        
        $where = '';
        $this->field_type = "link";
        $p = $this->qlevel;

        $cs_ids = $this->value;
        if(strpos($cs_ids, '-')===0){
            $this->negate = true;
            $cs_ids = substr($cs_ids, 1);
        }

        $cs_ids = $this->getUserIds($cs_ids);
        //$cs_ids = getCommaSepIds($this->value);
        if ($cs_ids) {  

                if(strpos($cs_ids, ',')>0){  //more than one

                    $where = ' IN (SELECT bkm_RecID FROM usrBookmarks where '
                            . 'bkm_UGrpID '.($this->negate?'NOT':'').' IN ('.$cs_ids.'))';
                    
                }else{
                    $where = ' IN (SELECT bkm_RecID FROM usrBookmarks where bkm_UGrpID '.($this->negate?'!=':'=').$cs_ids.')';
                }
        }
        if($where){
            $where = "r$p.rec_ID ".$where;
            return array("where"=>$where);
        }else{
            return null;
        }
        
    }
    
    //
    //
    //
    function predicateKeywords(){
        
        $this->field_type = "link";
        $p = $this->qlevel;
        
        $where = '';

        if(false && !$this->field_list){
            $where = "=0";
        }else{
            
            $isAll = false;

            if(is_array($this->value)){
                if(@$this->value['any']!=null){
                    $this->value = $this->value['any'];
                }else if(@$this->value['all']!=null){
                    $this->value = $this->value['all'];
                    $isAll = true;
                }
            }

            
            $cs_ids = getCommaSepIds($this->value);
            if ($cs_ids) {  
                
                if(strpos($cs_ids, '-')===0){
                    $this->negate = true;
                    $cs_ids = substr($cs_ids, 1);
                }
                
                if(strpos($cs_ids, ',')>0){  //more than one

                    $where = (($this->negate)?'NOT':'')
                            . ' IN (SELECT rtl_RecID FROM usrRecTagLinks where '
                            . 'rtl_TagID in ('.$cs_ids.')';
                    if($isAll){
                        $cnt = count(explode(',',$cs_ids));
                        $where = $where.' GROUP BY rtl_RecID HAVING count(*)='.$cnt;
                    }
                    $where = $where.')';
                    
                }else{
                    $where = (($this->negate)?'NOT':'')
                        . ' IN (SELECT rtl_RecID FROM usrRecTagLinks where rtl_TagID = '.$cs_ids.')';
                }
                
                
//SELECT rtl_RecID as cnt FROM usrrectaglinks where rtl_TagID in (1,3) group by rtl_RecID having count(*)=2);                
            }else{
                $pred = null;
                
                if(is_array($this->value)){
                    $values = $this->value;
                    $pred = array();
                    foreach ($values as $val){
                        $this->value = $val;    
                        $val = $this->getFieldValue();
                        if($val) array_push($pred,'tag_Text '.$val);
                    }
                    
                    $cnt = count($pred);
                    if($cnt>0){
                        $pred = '('.implode(' OR ',$pred).')';
                        
                        if($isAll){
                            $pred = $pred.' GROUP BY rtl_RecID HAVING count(*)='.$cnt;
                        }
                    }
                    
                }else{
                    $val = $this->getFieldValue();
                    if($val) $pred = 'tag_Text '.$val;
                }
                
                if($pred){
                    $where = ' IN (SELECT rtl_RecID FROM usrRecTagLinks, usrTags where rtl_TagID=tag_ID and '.$pred.')';    
                }else{
                    $where = '=0';
                }                
            }
        }

        $where = "r$p.rec_ID ".$where;

        return array("where"=>$where);
        
    }    
    
    /*
    linked_to: pointer field type : query     recordtype
    linkedfrom:
    relatedfrom: relation type id (termid)    recordtype
    related_to:
    links: recordtype
    */

    /**
    * find records that have pointers to specified records
    */
    function predicateLinkedTo(){
    
        global $top_query, $params_global, $mysqli;
        $not_nested = (@$params_global['nested']===false);

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;
        
        if($this->isEmptyValue()){

            if(strpos($this->value, '-')===0){
                $this->negate = true;
                $this->value = substr($this->value, 1);
            }
            
            $rd = "rd".$this->qlevel;
            
            if($this->field_id){
                //no pointer field exists among record details
                $where = (($this->negate)?'':'NOT')." EXISTS (select dtl_ID from recDetails $rd where r$p.rec_ID=$rd.dtl_RecID AND "
            ."$rd.dtl_DetailTypeID=".$this->field_id.")";
            
            }else{
                //no links at all or any link
                $where = "r$p.rec_ID ".(($this->negate)?'':'NOT')
                    ." IN (select rl_SourceID from recLinks $rl where $rl.rl_RelationID IS NULL)";
            }
            
            return array("where"=>$where);
            
        }else{
        
//$top_query->top_limb->addTable tables
//$top_query->top_limb->$where_clause
            
            $rl = "rl".$p."x".$this->index_of_predicate;

            if($this->query){
                $this->query->makeSQL();
                
                if($this->query->where_clause && trim($this->query->where_clause)!=""){

                    if($not_nested){
                        $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                        $top_query->top_limb->addTable($this->query->from_clause);
                    }else{
                        $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.' WHERE '.$this->query->where_clause;
                        $ids = mysql__select_list2($mysqli, $sub_query);
                        if(is_array($ids) && count($ids)>0){
                            //if(count($ids)>2000)
                            $val = ' IN ('.implode(',',$ids).')';
                        }else{
                            $val = ' =0';
                        }
                        
                        //OLD $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                    }
                    
                }else{
                    return null;
                }
            }else{
                
                $val = $this->getFieldValue();

                if($val=='' && !$this->field_list){
                    $val = "=0";
                    //@todo  findAnyField query
                    //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
                }
            }
            
            if($this->field_id==7){ //special case for relationship records
                //relation record linked to specific target or source
                
                $where = "r$p.rec_ID=$rl.rl_RelationID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_SourceID".$val;
                
            }else if($this->field_id==5){ //special case for relationship records
            
                $where = "r$p.rec_ID=$rl.rl_RelationID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_TargetID".$val;
                
            }else{
                
                $field_compare = "$rl.rl_RelationID IS NULL";
                if($this->field_id){
                    $several_ids = prepareIds($this->field_id); //getCommaSepIds - returns validated string
                    if(is_array($several_ids) && count($several_ids)>0){
                        
                        if(count($several_ids)>1){
                            $field_compare = "$rl.rl_DetailTypeID IN (".implode(',',$several_ids).')';
                        }else{
                            $field_compare = "$rl.rl_DetailTypeID = ".$this->field_id;
                        }
                    }
                }
                
                $where = "r$p.rec_ID=$rl.rl_SourceID AND ".
                $field_compare
                ." AND $rl.rl_TargetID".$val;
            }

            return array("from"=>"recLinks ".$rl, "where"=>$where);
        
        }
    }

    /**
    * find records that have pointers to specified records
    */
    function predicateLinkedFrom(){
        
        global $top_query, $params_global, $mysqli;
        $not_nested = (@$params_global['nested']===false);

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){

            $this->query->makeSQL();

            if($this->query->where_clause && trim($this->query->where_clause)!=""){

                if($not_nested){
                    $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                    $top_query->top_limb->addTable($this->query->from_clause);
                }else{
                    $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.' WHERE '.$this->query->where_clause;
                    $ids = mysql__select_list2($mysqli, $sub_query);
                    if(is_array($ids) && count($ids)>0){
                        //if(count($ids)>2000)
                        $val = ' IN ('.implode(',',$ids).')';
                    }else{
                        $val = ' =0';
                    }
                
                    //OLD $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{

            $val = $this->getFieldValue();

            if($val=='' && !$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        if($this->field_id==5){ //special case for relationship records
            //find target or source linked from relation record
            
            $where = "r$p.rec_ID=$rl.rl_TargetID "
            ." AND $rl.rl_DetailTypeID IS NULL "
            ." AND $rl.rl_RelationID".$val;

        }else if($this->field_id==7){ //special case for relationship records

            $where = "r$p.rec_ID=$rl.rl_SourceID "
            ." AND $rl.rl_DetailTypeID IS NULL "
            ." AND $rl.rl_RelationID".$val;
            
        }else{
            
            $field_compare = "$rl.rl_RelationID IS NULL";
            if($this->field_id){
                $several_ids = prepareIds($this->field_id); //getCommaSepIds - returns validated string
                if(is_array($several_ids) && count($several_ids)>0){
                    
                    if(count($several_ids)>1){
                        $field_compare = "$rl.rl_DetailTypeID IN (".implode(',',$several_ids).')';
                    }else{
                        $field_compare = "$rl.rl_DetailTypeID = ".$this->field_id;
                    }
                }
            }
        
            $where = "r$p.rec_ID=$rl.rl_TargetID AND "
            .$field_compare
            //OLD (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID IS NULL")
            ." AND $rl.rl_SourceID".$val;
        }

        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }

    
    /**
    * find records that are source relation for specified records
    * 
    * "related:term_id": query for target record
    * 
    * $this->field_id - relation type (term id)
    */
    function predicateRelated(){

        global $top_query, $params_global, $mysqli;
        $not_nested = (@$params_global['nested']===false);
        

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                
                if($not_nested){
                    $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                    $top_query->top_limb->addTable($this->query->from_clause);
                }else{
                    
                    $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.' WHERE '.$this->query->where_clause;
                    $ids = mysql__select_list2($mysqli, $sub_query);
                    if(is_array($ids) && count($ids)>0){
                        //if(count($ids)>2000)
                        $val = ' IN ('.implode(',',$ids).')';
                    }else{
                        $val = ' =0'; //not found
                    }
                    
                    //OLD $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if($val=='' && !$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        $where = 
        (($this->field_id && false) ?"$rl.rl_RelationTypeID=".$this->field_id :"$rl.rl_RelationID is not null")
         ." AND ((r$p.rec_ID=$rl.rl_SourceID AND  $rl.rl_TargetID".$val
            .") OR (r$p.rec_ID=$rl.rl_TargetID AND  $rl.rl_SourceID".$val.'))';

        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }
    

    /**
    * find records that are source relation for specified records
    * 
    * "related_to:field_id": query for target record
    * 
    * $this->field_id - relmarker field - it is used to get constraintes (dty_JsonTermIDTree, dty_PtrTargetRectypeIDs)
    * It takes relation type (term id) from value
    * for example "rf":[{"t":4},{"r":"6590"}]
    * 
    * "rf":245 - related from record id 245
    * {"rf:245":[{"t":4},{"r":6421}]}   related from organization(t:4) with reltype id 6421
    */
    function predicateRelatedDirect($is_reverse){

        global $top_query, $params_global, $mysqli;
        $not_nested = (@$params_global['nested']===false);
        
        if($is_reverse){
            $part1 = 'rl_TargetID';
            $part2 = 'rl_SourceID';
        }else{
            $part1 = 'rl_SourceID';
            $part2 = 'rl_TargetID';
        }
        

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;
       
       
       //{"t":10,"rf:245":[{"t":4},{"r":6421},{"relf:10":">2010"}]}}
       //{"t":10,"rf:6421}
        
       if($this->isEmptyValue()){
            
            $rd = "rd".$this->qlevel;
            
            if($this->field_id){
                //find constraints and all terms 
                list($vocab_id, $rty_constraints) = mysql__select_row($mysqli, 
                        'SELECT dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
                        .'FROM defDetailTypes WHERE dty_ID='.$this->field_id);
                $reltypes = getTermChildrenAll($mysqli, $vocab_id);
                $rty_constraints = explode(',',$rty_constraints);
                
                if(count($rty_constraints)>0){
                    if(count($rty_constraints)==1){
                        $rty_constraints = '='.$rty_constraints[0];       
                    }else{
                        $rty_constraints = 'IN ('.implode(',',$rty_constraints).')';
                    }
                    
                    $rty_constraints = ', Records where '.$part2.'=rec_ID and rec_RecTypeID '.$rty_constraints.' AND ';
                }else{
                    $rty_constraints = ' where ';
                }
                
                if(count($reltypes)>0){
                    if(count($reltypes)==1){
                        $reltypes = '='.$reltypes[0];       
                    }else{
                        $reltypes = 'IN ('.implode(',',$reltypes).')';
                    }
                    $reltypes = ' rl_RelationTypeID '.$reltypes;
                }else{
                    $reltypes = 'rl_RelationTypeID IS NOT NULL';
                }
                
                $where = "r$p.rec_ID ".(($this->negate)?'':'NOT')
                    ." IN (select $part1 from recLinks"
                                .$rty_constraints
                                .$reltypes.')';
            
            }else{
                //relmarker field not defined
                //no relation at all or any relation with specified reltypes (term) and record types
                $where = "r$p.rec_ID ".(($this->negate)?'':'NOT')
                        ." IN (select $part1 from recLinks where rl_RelationID IS NOT NULL)";
            }
            
            return array("where"=>$where);
            
        }else{

            if($this->query){
                $this->query->makeSQL();
                if($this->query->where_clause && trim($this->query->where_clause)!=""){
                    
                    if($not_nested){
                        $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                        $top_query->top_limb->addTable($this->query->from_clause);
                    }else{
                        //OLD $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                        
                        $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.' WHERE '.$this->query->where_clause;
                        $ids = mysql__select_list2($mysqli, $sub_query);
                        if(is_array($ids) && count($ids)>0){
                            //if(count($ids)>2000)
                            $val = ' IN ('.implode(',',$ids).')';
                        }else{
                            $val = ' =0';
                        }
                        
                    }
                }else{
                    return null;
                }

            }else{
                //related to/from list of record ids
                $val = $this->getFieldValue();                

                if($val=='' && !$this->field_list){
                    $val = "!=0";
                    //@todo  findAnyField query
                    //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
                }
            }

            //compose where with recLinks ($rl) fields
            $where = "r$p.rec_ID=$rl.$part1 ";
            if($val){
                $where = $where . "AND $rl.$part2".$val;
            }
            
            if(is_array($this->relation_types)&& count($this->relation_types)>0){
                
                $this->relation_types = array_merge($this->relation_types, 
                                getTermChildrenAll($mysqli, $this->relation_types));
                
                $where = $where . " AND ($rl.rl_RelationTypeID " .(count($this->relation_types)>1
                            ?' IN ('.implode(',',$this->relation_types).')'
                            :'='.$this->relation_types[0])
                            .')';    
                
            }else{
                $where = $where . " AND $rl.rl_RelationID is not null";
            }
            
            if($this->relation_fields!=null){
                $this->relation_fields->setRelationPrefix($rl);
                $w2 = $this->relation_fields->makeSQL();
//$res = array("from"=>$this->tables, "where"=>$where);                
                if($w2 && trim($w2['where'])!=''){
                    $where = $where . ' AND ' . $w2['where'];
                }
            }
            return array("from"=>"recLinks ".$rl, "where"=>$where);
        }
    }

    /**
    * find records that any links (both pointers and relations) to specified records
    */
    function predicateLinks(){
        
        global $top_query, $params_global, $mysqli;
        $not_nested = (@$params_global['nested']===false);
        

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                if($not_nested){
                    $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                    $top_query->top_limb->addTable($this->query->from_clause);
                }else{
                    //OLD $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                    $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.' WHERE '.$this->query->where_clause;
                    $ids = mysql__select_list2($mysqli, $sub_query);
                    if(is_array($ids) && count($ids)>0){
                        //if(count($ids)>2000)
                        $val = ' IN ('.implode(',',$ids).')';
                    }else{
                        $val = ' =0';
                    }
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if($val=='' && !$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        //($rl.rl_RelationID is not null) AND 
        $where = "((r$p.rec_ID=$rl.rl_SourceID AND $rl.rl_TargetID".$val.") OR (r$p.rec_ID=$rl.rl_TargetID AND $rl.rl_SourceID".$val."))";


        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }


    function isDateTime() {

        $timestamp0 = null;
        $timestamp1 = null;
        
        if (strpos($this->value,"<>")>0) {
            $vals = explode("<>", $this->value);

            if(is_numeric($vals[0]) || is_numeric($vals[1])) return false;

             try{   
                $timestamp0 = new DateTime($vals[0]);
                $timestamp1 = new DateTime($vals[1]);
             } catch (Exception  $e){
             }                            
        }else{

            if(is_numeric($this->value)) return false;

             try{   
                $timestamp0 = new DateTime($this->value);
                $timestamp1 = 1;
             } catch (Exception  $e){
             }                            
        }
        return ($timestamp0  &&  $timestamp1);
    }

    function makeDateClause() {

        if (strpos($this->value,"<>")) {

            $vals = explode("<>", $this->value);
            $datestamp0 = validateAndConvertToISO($vals[0]);
            $datestamp1 = validateAndConvertToISO($vals[1]);

            return ($this->negate?'not ':'')."between '$datestamp0' and '$datestamp1'";

        }else{

            $datestamp = validateAndConvertToISO($this->value);
            
            if($datestamp==null){
                return null;
            }else
            if ($this->exact) {
                return ($this->negate?'!':'')."= '$datestamp'";
            }
            else if ($this->lessthan) {
                return $this->lessthan." '$datestamp'";
            }
            else if ($this->greaterthan) {
                return $this->greaterthan." '$datestamp'";
            }
            else {
                //return "LIKE '$datestamp%'";

                //old way
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                /*$match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];
                }
                else */
                if (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = $this->value; //date('Y-m', $datestamp);
                }
                else {
                    $date = $datestamp; //date('Y-m-d', $datestamp);
                }
                return ($this->negate?'not ':'')."LIKE '$date%'";
            }
        }
    }

    /*
      is search for empty or null value
    */
    function isEmptyValue(){
                                            //$this->value=='' ||
        return !is_array($this->value) && ( strtolower($this->value)=='null' || strtolower($this->value)=='-null'); // {"f:18":"NULL"}
    }

    
    /**
    * Search user ids - prepare list of user ids
    * 
    * @param mixed $value
    */
    function getUserIds($value){
        global $mysqli, $currUserID;
        
        $values = explode(',',$value);
        $userids = array();
        foreach($values as $username){
            if(is_numeric($username)){
                $userids[] = $username;    //ugr_ID    
            }else if(strtolower($username)=='currentuser' || strtolower($username)=='current_user'){
                $userids[] = $currUserID;
            }else{
                 $username = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where ugr_Name = "'
                        .$mysqli->real_escape_string($username).'" limit 1');
                 if($username!=null) $userids[] = $username;
            }
        }
        return implode(',',$userids);
    }
                
    
    /**
    * put your comment there...
    *
    */
    function getFieldValue(){

        global $mysqli, $params_global, $currUserID;
        
        $this->case_sensitive = false;

        //@todo between , datetime, terms
        if(is_array($this->value)){
            if(count($this->value)>0){
                $cs_ids = getCommaSepIds($this->value);
                if($cs_ids!=null){
                    $this->value = implode(',',$this->value);
                }
            }else{
                return '';
            }
            /*
            error_log('value expected string - array given');
            error_log(print_r($params_global,true));
            $this->value = reset($this->value);
            */
        }

        // -   negate value
        // <> between 
        
        // then it analize for
        // == case sensetive
        // and at last
        // = - exact or LIKE (check for % at begin and end)
        // @    fulltext
        // > <  for numeric and dates only
        
        $this->negate = false;
        $this->exact = false;
        $this->fulltext = false;
        $this->lessthan = false;
        $this->greaterthan = false;
        
        //
        if(strpos($this->value, '-')===0){
            $this->negate = true;
            $this->value = substr($this->value, 1);
        }
                
        if (strpos($this->value,"<>")===false) {
            
            if(strpos($this->value, '==')===0){
                $this->case_sensitive = true;
                $this->value = substr($this->value, 2);
            }
            
            if(strpos($this->value, '=')===0){
                $this->exact = true;
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '@')===0){
                $this->fulltext = true; //for detail type "file" - search by obfuscation id
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '<=')===0){
                $this->lessthan = '<=';
                $this->value = substr($this->value, 2);
            }else if(strpos($this->value, '<')===0){
                $this->lessthan = '<';
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '>=')===0){
                $this->greaterthan = '>=';
                $this->value = substr($this->value, 2);
            }else if(strpos($this->value, '>')===0){
                $this->greaterthan = '>';
                $this->value = substr($this->value, 1);
            }
            
        }
        $this->value = $this->cleanQuotedValue($this->value);

        if(is_string($this->value) && trim($this->value)=='') return "!=''";   //find any non empty value

        $eq = ($this->negate)? '!=' : (($this->lessthan) ? $this->lessthan : (($this->greaterthan) ? $this->greaterthan : '='));
        
        if($this->field_type=='enum' || $this->field_type=='relationtype'){
            
            if (preg_match('/^\d+(?:,\d*)+$/', $this->value)){   //numeric comma separated
                $res = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }else if(preg_match('/^\d+$/', trim($this->value)) && intval($this->value)){   //integer
            
                $int_v = intval($this->value);
                $res = ' in (select trm_ID from defTerms where trm_ID='
                    .$int_v;
                if(!$this->exact){
                    $res = $res . ' or trm_ParentTermID='.$int_v;   
                }
                $res = $res.')';
                    
            }else{
                $value = $mysqli->real_escape_string($this->value);
                $res  = ' in (select trm_ID from defTerms where trm_Label ';
                if($this->exact){
                    $res  =  $res.'="'.$value.'"'; 
                } else {
                    $res  =  $res.'LIKE "%'.$value.'%"';
                }
                $res  =  $res.' or trm_Code="'.$value.'")';
            }
            //if put negate here is will accept any multivalue enum field
            //see negate for enum on level above $res = (($this->negate)?' not':'').$res;
        }else
        // it is better to use kwd keyword instead of f:tag
        if($this->field_id=='tag'){
            
            if(preg_match('/^\d+$/', trim($this->value)) && intval($this->value)>0){
                $res = '(SELECT rtl_RecID FROM usrRecTagLinks where rtl_TagID='.$this->value.')';
            }else{
                $res = '(SELECT rtl_RecID FROM usrRecTagLinks, usrTags where rtl_TagID=tag_ID AND tag_Text="'
                        .$mysqli->real_escape_string($this->value).'")';
            }
        
        }else
        if ($this->field_type=='file'){        
            
            $value = $mysqli->real_escape_string($this->value);
            
            //
            if($this->fulltext){ //search obfuscation id

                $this->fulltext = false;
                $res = "ulf_ObfuscatedFileID = '".$this->value."'";
            
            }else
            if(strpos($value,'^')===0){ //search file size
                
                $this->value = substr($this->value, 1);
                $res = "ulf_FileSizeKB $eq ".intval($this->value);
                
            }else {
                if($this->exact){
                    $res  =  $res.'="'.$value.'"'; 
                } else {
                    $res  =  $res.'LIKE "%'.$value.'%"';
                }
                //path, filename, description and remote URL 
                $res = "(ulf_OrigFileName $res) OR "
                       ."(ulf_ExternalFileReference $res)  OR (ulf_Description $res)";
            }
            $res = ' in (select ulf_ID from recUploadedFiles where '.$res.')';
            
        }else
        if (($this->field_type=='float' || $this->field_type=='integer' || $this->field_type == 'link') && is_numeric($this->value)) {
            
            if (strpos($this->value,"<>")>0) {
                $vals = explode("<>", $this->value);
                $between = (($this->negate)?" not":"")." between ";
                if(is_numeric($vals[0]) && is_numeric($vals[1])){
                    $res = $between.$vals[0]." and ".$vals[1];
                }
            }else if($this->field_type == "link"){
                $res = " $eq ".intval($this->value);  //no quotes
            }else{
                $res = " $eq ".($this->field_type=='float'?floatval($this->value):intval($this->value));//."'";
            }
            $this->field_list = true;
        }
        else {
            
            
            if($this->pred_type=='addedby' || $this->pred_type=='owner')
            {
                
                
                //if value is not numeric (user id), find user id by user login name
                $this->value = $this->getUserIds($this->value);
                $this->exact = true;
            }       
            
            if($this->pred_type=='modified' || $this->pred_type=='added' 
                || $this->pred_type=='after'  || $this->pred_type=='before'){
                    
                    
                if($this->pred_type=='before') $this->lessthan = '<=';
                if($this->pred_type=='after')  $this->greaterthan = '>';
            
                    
                $this->field_type = 'date';
                $cs_ids = null;    
            }else
            if($this->pred_type=='title' || $this->pred_type=='url' || $this->pred_type=='notes'
                || $this->field_type =='date'){
                $cs_ids = null;
            }else{
                $cs_ids = getCommaSepIds($this->value);
            }
            
            if ($cs_ids && strpos($cs_ids, ',')>0) {  
            //if (preg_match('/^\d+(?:,\d*)+$/', $this->value)) { - regex does not work for >500 entries
                                
                // comma-separated list of defRecTypes ids
                $in = ($this->negate)? 'not in' : 'in';
                $res = " $in (" . $cs_ids . ")";

                $this->field_list = true;

            } else if($this->field_type =='date'){ //$this->isDateTime()){
                //
                $res = $this->makeDateClause();

            } else {

                if (strpos($this->value,"<>")>0) {
                    $vals = explode("<>", $this->value);

                    $between = (($this->negate)?" not":"")." between ";
                    if(is_numeric($vals[0]) && is_numeric($vals[1])){
                        $res = $between.$vals[0]." and ".$vals[1];
                    }else{
                        $res = $between."'".$mysqli->real_escape_string($vals[0])."' and '".$mysqli->real_escape_string($vals[1])."'";
                    }
                
                }else if($this->fulltext && $this->field_type!='link'){
                    
                    //1. check that fulltext index exists
                    if($this->checkFullTextIndex()){ 
                    //returns true and fill $this->error_message if something wrong
                        return null;
                    }
                    
                    $res = '';
                    
                    if(strpos($this->value, '++')===0 || strpos($this->value, '--')===0){
                       
                        $op = (strpos($this->value, '+')===0)?' +':' -';
                        
                        //get all words
                        $pattern = "/(\w+)/";
                        if (preg_match_all($pattern, $this->value, $matches)) {
                                 $this->value = trim($op).implode($op, $matches[0]);
                        }
                        
                    }
                     
                    if(strpos($this->value, '+')>=0 || strpos($this->value, '-')>=0){
                        $res = ' IN BOOLEAN MODE ';
                    }
                    
                    $res = " AGAINST ('".$mysqli->real_escape_string($this->value)."'$res)";
                    
                }else{

                    if(!$this->exact){ //$eq=='=' && 
                            $eq = ($this->negate?'NOT ':'').'LIKE';
                            $k = strpos($this->value,"%"); //if begin or end
                            if($k===false || ($k>0 && $k+1<strlen($this->value))){
                                $this->value = '%'.$this->value.'%';
                            }
                    }else{
                        $eq = ($this->negate?'!=':'=');
                    }
                    if($this->case_sensitive){
                            $eq = 'COLLATE utf8_bin '.$eq;
                    }

                    $res = " $eq '" . $mysqli->real_escape_string($this->value) . "'";
                }
            }
            
        }

        return $res;

    }//getFieldValue
    
    //
    // check existanse of full text index and creates it
    // return true - if index is missed or its creation is in progress
    //
    function checkFullTextIndex(){
        global $mysqli;
        
        if($this->pred_type=='title' || $this->field_id=='title'){

            $fld = mysql__select_value($mysqli,
            'select group_concat(distinct column_name) as fld'
            .' from information_schema.STATISTICS '
            ." where table_schema = '".HEURIST_DBNAME_FULL."' and table_name = 'Records' and index_type = 'FULLTEXT'");        
            
            if($fld==null){
                $mysqli->query('ALTER TABLE Records ADD FULLTEXT INDEX `rec_Title_FullText` (`rec_Title`)'); // VISIBLE
                $this->error_message = 'create_fulltext';  
                return true;
            }
                    
        }else{
        
            $fld = mysql__select_value($mysqli,
            'select group_concat(distinct column_name) as fld'
            .' from information_schema.STATISTICS '
            ." where table_schema = '".HEURIST_DBNAME_FULL."' and table_name = 'recDetails' and index_type = 'FULLTEXT'");        
            
            if($fld==null){

                $mysqli->query('ALTER TABLE recDetails ADD FULLTEXT INDEX `dtl_Value_FullText` (`dtl_Value`)'); // VISIBLE
              
    /*            
                $k=0;
                while($k<10){
                    $prog = mysql__select_value($mysqli,"select progress from FROM sys.session where db='".HEURIST_DBNAME_FULL."' "
                    ." AND current_statement like 'ALTER TABLE recDetails ADD FULLTEXT%'");
    error_log($prog);     
    usleep(200);           
                    $k++;
                }
    */            
                
                $this->error_message = 'create_fulltext';  
                return true;
            }
            
        }
        
        return false;
/*        
ALTER TABLE `hdb_osmak_7`.`recdetails` DROP INDEX `dtl_Value_FullText` ;        
ALTER TABLE `hdb_osmak_7`.`recdetails` ADD FULLTEXT INDEX `dtl_Value_FullText` (`dtl_Value`) VISIBLE;

SELECT thd_id, conn_id, db, command, state, current_statement,
              statement_latency, progress, current_memory, program_name
         FROM sys.session
        WHERE progress IS NOT NULL
*/
    }
                            


}//HPredicate

/*
{ all: { t:4, f1:peter , any: {}  } }

query is json array  { conjunction: [ {predicate} , {predicate}, .... ] }

{ any: [ {predicate} , {predicate}, .... ],  }

[ ]

*/
?>
