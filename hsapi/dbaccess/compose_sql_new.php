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

query is json array  { conjunction: [ {predicate} , {predicate}, .... ] }

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
t, type:  record type id
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
rel, r: - relation type (enum)

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

//https://heuristplus.sydney.edu.au/HEURIST/h4/hsapi/controller/record_search.php?db=artem_clone1&q={"t":3}
//https://heuristplus.sydney.edu.au/HEURIST/h4/hsapi/controller/record_search.php?db=artem_clone1&q={"f:1":"girls"}
//https://heuristplus.sydney.edu.au/HEURIST/h4/hsapi/controller/record_search.php?db=artem_clone1&q={"all":[{"f:1":"girl"},{"t":"5"}]}

//https://heuristplus.sydney.edu.au/HEURIST/h4/hsapi/controller/record_search.php?db=artem_clone1&q={"linked_to:15":{"t":"10"}}
//https://heuristplus.sydney.edu.au/HEURIST/h4/hsapi/controller/record_search.php?db=artem_clone1&q=[{"t":3},{"linked_to:15":{"t":"10"}}]


// [{"t":3},{"f:1":"?"},{"linked_to:15":[{"t":"10"},{"f:1":"?"}   ] } ]

                                                                  
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


    function HQuery($level, $query_json, $search_domain=null, $currUserID=null) {

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
                    $where2 = '( '.$where2.$where2_conj.'r0.rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
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
    // {"sort":"f:233"}  {"sort":"-title"}  
    //    
    function createSortClause() {
        
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
                    if($dty_ID!=null && !in_array($dty_ID, $sort_fields)) {
                    $sort_expr[] = 
'ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=r'.$this->level.'.rec_ID and dtl_DetailTypeID='.$dty_ID.' ORDER BY link.rec_Title limit 1), "~~"), r'.$this->level.'.rec_Title';                    
                        $sort_fields[] = $dty_ID;   
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

    function HLimb(&$parent, $conjunction, $query_json) {

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
    
    var $relation_types = null; //for related_to

    var $field_list = false; //list of id values
    
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

    var $allowed = array('title','t','modified','url','notes','type','ids','id','count','cnt',
            'f','field','geo','linked_to','linkedto','linkedfrom','related','related_to','relatedto','relatedfrom','links','plain',
            'addedby','owner','access','tag','keyword','kwd');
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


    function HPredicate(&$parent, $key, $value, $index_of_predicate) {

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
                //special bahvior for related_to - extract reltypes
                if(strtolower($this->pred_type)=='related_to'){
                    foreach($value as $idx=>$val){
                        if(@$val['r']){
                            $this->relation_types = $val['r'];
                            if(is_array($this->relation_types) && count(is_array($this->relation_types)==1)){
                                $this->relation_types = $this->relation_types[0];  
                            } 
                            array_splice($value, $idx, 1);
                            $this->value = $value;
                            break;
                        }
                    }
                    foreach($value as $idx=>$val){
                        if(@$val['ids']){
                            $this->value = $val['ids'];
                            $value = array();
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
                else {
                    $res = "$eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
                }
                $res = "r".$this->qlevel.".rec_RecTypeID ".$res;
                return array("where"=>$res);

            case 'geo':
                
                return $this->predicateSpatial();

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

            case 'title':
            case 'modified':
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

            case 'tag':
            case 'keyword':
            case 'kwd':
                
                return $this->predicateKeywords();

            case 'linked_to':
            case 'linkedto':

                return $this->predicateLinkedTo();

            case 'linkedfrom':

                return $this->predicateLinkedFrom();
                
            case 'related':

                return $this->predicateRelated();

            case 'related_to':
            case 'relatedto':

                return $this->predicateRelatedTo();

            case 'relatedfrom':

                return $this->predicateRelatedFrom();

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
            $res = $res.$p.'dtl_Geo IS NULL)'; //not defined
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
        }else if(intval($this->field_id)>0){
            //find field type - @todo from cache
            $this->field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$this->field_id);
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

        }else if($this->pred_type=='modified' || $this->field_id=='modified'){

            $sHeaderField = "rec_Modified";
            
        }else if($this->pred_type=='url' || $this->field_id=='url'){
            
            $sHeaderField = "rec_URL";

        }else if($this->pred_type=='notes' || $this->field_id=='notes'){
            
            $sHeaderField = 'rec_ScratchPad';
            $ignoreApostrophe = (strpos($val, 'LIKE')==1);
            
        }else if($this->pred_type=='addedby'){
            
            $sHeaderField = 'rec_AddedByUGrpID';
            
        }else if($this->pred_type=='owner'){
            
            $sHeaderField = 'rec_OwnerUGrpID';
            
        }else if($this->pred_type=='access'){
            
            $sHeaderField = "rec_NonOwnerVisibility";
        }
            
          
        if($sHeaderField){    
            
            if($this->pred_type=='f') $this->pred_type = $this->field_id;
            
            if($is_empty){
                $res = "(r".$this->qlevel.".$sHeaderField is NULL OR r".$this->qlevel.".$sHeaderField=='')";    
            }else{
                $ignoreApostrophe = $ignoreApostrophe && (strpos($val,"'")===false);
                
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

            //old $res = $p."dtl_DetailTypeID=".$this->field_id." AND ".$p."dtl_Value ".$val;
            if($this->fulltext){
                $res = 'select dtl_RecID from recDetails where dtl_DetailTypeID';
            }else{
                $res = "exists (select dtl_ID from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
                .$p.'dtl_DetailTypeID';
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
                    $res = 'r'.$this->qlevel.'.rec_ID'
                        .(count($list_ids)>1
                            ?' IN ('.implode(',',$list_ids).')'
                            :'='.$list_ids[0]);
                }else{
                    $res = '(1=0)'; //nothing found    
                }
                
            }else{
                $res = $res.' AND '.$field_name.$val.')';    
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

            if(!$this->field_list){
                $val = "=0";
            }else if ($p==0){
                $cs_ids = getCommaSepIds($this->value);
                if ($cs_ids && strpos($cs_ids, ',')>0) {  
                    $top_query->fixed_sortorder = $cs_ids;
                }
            }
        }

        $where = "r$p.rec_ID".$val;

        return array("where"=>$where);

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
    
        global $top_query, $params_global;
        $not_nested = (@$params_global['nested']===false);

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;
        
        if(!is_array($this->value) && strpos($this->value, '-')===0){
            $this->negate = true;
            $this->value = substr($this->value, 1);
        }
        
        if($this->isEmptyValue()){
            
            $rd = "rd".$this->qlevel;
            
            if($this->field_id){
                //no pointer field exists among record details
                $where = (($this->negate)?'':'NOT')." exists (select dtl_ID from recDetails $rd where r$p.rec_ID=$rd.dtl_RecID AND "
            ."$rd.dtl_DetailTypeID=".$this->field_id.")";
            
            }else{
                //no links at all or any link
                $where = "r$p.rec_ID ".(($this->negate)?'':'NOT')
                    ." IN (select rl_SourceID from recLinks where $rl.rl_RelationID is null";
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
                        $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                    }
                    
                }else{
                    return null;
                }
            }else{
                
                $val = $this->getFieldValue();

                if(!$this->field_list){
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
                $where = "r$p.rec_ID=$rl.rl_SourceID AND ".
                (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID is null")
                ." AND $rl.rl_TargetID".$val;
            }

            return array("from"=>"recLinks ".$rl, "where"=>$where);
        
        }
    }

    /**
    * find records that have pointers to specified records
    */
    function predicateLinkedFrom(){
        
        global $top_query, $params_global;
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
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{

            $val = $this->getFieldValue();

            if(!$this->field_list){
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
        
            $where = "r$p.rec_ID=$rl.rl_TargetID AND ".
            (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID is null")
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

        global $top_query, $params_global;
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
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if(!$this->field_list){
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
    * "related_to:term_id": query for target record
    * 
    * $this->field_id - relation type (term id)
    */
    function predicateRelatedTo(){

        global $top_query, $params_global;
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
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if(!$this->field_list){
                $val = "!=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        //search by relation type is disabled since it assigns field id instead  @todo fix
        $where = "r$p.rec_ID=$rl.rl_SourceID AND $rl.rl_TargetID".$val;
        
        if($this->relation_types){
            //(($this->field_id && false) ?"$rl.rl_RelationTypeID=".$this->field_id :)
            if(is_array($this->relation_types)&& count($this->relation_types)){
                $where = $where . " AND $rl.rl_RelationTypeID IN (".implode(',',$this->relation_types).")";    
            }else{
                $where = $where . " AND $rl.rl_RelationTypeID = ".$this->relation_types;    
            }
            
        }else{
            $where = $where . " AND $rl.rl_RelationID is not null";
        }
        

        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }

    /**
    * find records that are target relation for specified records
    */
    function predicateRelatedFrom(){

        global $top_query, $params_global;
        $not_nested = (@$params_global['nested']===false);
        
        $this->field_type = "link";
        $p = $this->qlevel; //parent level
        $rl = "rl".$p."x".$this->index_of_predicate;

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

            if(!$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        //search by relation type is disabled since it assigns field id instead  @todo fix
        $where = "r$p.rec_ID=$rl.rl_TargetID AND ".
        (($this->field_id && false) ?"$rl.rl_RelationTypeID=".$this->field_id :"$rl.rl_RelationID is not null")
        ." AND $rl.rl_SourceID".$val;

        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }


    /**
    * find records that any links (both pointers and relations) to specified records
    */
    function predicateLinks(){
        
        global $top_query, $params_global;
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
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if(!$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        $where = "($rl.rl_RelationID is not null) AND ((r$p.rec_ID=$rl.rl_SourceID AND $rl.rl_TargetID".$val.") OR (r$p.rec_ID=$rl.rl_TargetID AND $rl.rl_SourceID".$val."))";


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

            if ($this->exact) {
                return ($this->negate?'!':'')."= '$datestamp'";
            }
            else if ($this->lessthan) {
                return "< '$datestamp'";
            }
            else if ($this->greaterthan) {
                return "> '$datestamp'";
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
        return !is_array($this->value) && ( strtolower($this->value)=='null'); // {"f:18":"NULL"}
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
            $cs_ids = getCommaSepIds($this->value);
            if($cs_ids!=null){
                $this->value = implode(',',$this->value);
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
                $this->fulltext = true;
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '<')===0){
                $this->lessthan = true;
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '>')===0){
                $this->greaterthan = true;
                $this->value = substr($this->value, 1);
            }
            
            
        }
        $this->value = $this->cleanQuotedValue($this->value);

        if(is_string($this->value) && trim($this->value)=='') return "!=''";   //find any non empty value

        $eq = ($this->negate)? '!=' : (($this->lessthan) ? '<' : (($this->greaterthan) ? '>' : '='));
        
        if($this->field_type=='enum' || $this->field_type=='relationtype'){
            
            if (preg_match('/^\d+(?:,\d*)+$/', $this->value)){   //numeric comma separated
                $res = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }else if(intval($this->value)>0){
                $res = ' in (select trm_ID from defTerms where trm_ID='
                    .$this->value;
                if(!$this->exact){
                    $res = $res . ' or trm_ParentTermID='.$this->value;   
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
            $res = (($this->negate)?' not':'').$res;
            
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
                $values = explode(',',$this->value);
                $userids = array();
                foreach($values as $username){
                    if(is_numeric($username)){
                        $userids[] = $username;        
                    }else if(strtolower($username)=='currentuser' || strtolower($username)=='current_user'){
                        $userids[] = $currUserID;
                    }else{
                         $username = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where ugr_Name = "'
                                .$mysqli->real_escape_string($username).'" limit 1');
                         if($username!=null) $userids[] = $username;
                    }
                }
                $this->value = implode(',', $userids);
                $this->exact = true;
            }       
            
            
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
        
        $fld = mysql__select_value($mysqli,
        'select group_concat(distinct column_name) as fld'
        .' from information_schema.STATISTICS '
        ." where table_schema = '".HEURIST_DBNAME_FULL."' and table_name = 'recDetails' and index_type = 'FULLTEXT'");        
        
        if($fld==null){

            $mysqli->query('ALTER TABLE recDetails ADD FULLTEXT INDEX `dtl_Value_FullText` (`dtl_Value`) VISIBLE');            
          
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
