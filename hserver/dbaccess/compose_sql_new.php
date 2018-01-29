<?php
/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
notall     NOT ( AND )
notany     NOT ( OR )

predicate:   keyword:value

KEYWORDS

plain            query in old plain text format
url, u:          url
notes, n:        record heder notes (scratchpad)
title:           title contains
addedby:         added by specified user
added:           creation date
date, modified:  edition date
after, since:
before:

workgroup,wg,owner:

user, usr:   user id  - bookmarked by user
tag, keyword, kwd:   tag

id, ids:  id
t, type:  record type id
f, field:   field id
cnt:field id  search for number of field instances

latitude, lat:
longitude, long, lng:

hhash:


link predicates

linked_to: pointer field type : query     recordtype
linkedfrom:
relatedfrom: relation type id (termid)    recordtype
related_to:
links: recordtype

recordtype is added to link query  as first predicate


sortby,sort,s - sort phrases must be on top level array - all others will be ignored

sort values:



-----
VALUE

1)  Literal   "f:1":"Peter%"
2)  CSV       "ids":"1,2,3,4"
3)  WKT       "f:5":"POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"
4)  Query     "linked_to:15": [{ t:4 }, {"f:1":"Alex" } ]
"f:10": {"any":[ {t:4}, {} ] }

5) Find all records without field  "f:5":"NULL"
6) Find all records with any value in field  "f:5":""

*/


/*

select


*/
$mysqli = null;
$wg_ids = null;
$publicOnly = false;

//keep params for debug only!
$params_global;
$top_query;

/*

[{"t":14},{"f:74":"X0"},
{"related_to:100":[{"t":"15"},{"f:80":"X1"}]},   //charge/conviction
{"relatedfrom:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:18":"X6"}]},   //persons involved

{"related_to:99":[{"t":"12"}, {"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}  //address->street(11)  and linkedfrom role(16) role of place
]

*/
// $params need for
// q - json query array
// w - domain all or bookmarked or e(everything)
// limit and offset
// nested = false - to avoid nested queries - it allows performs correct count for distinct target record type (see faceted search)
// @todo s - sort
// @todo publiconly
//
function get_sql_query_clauses_NEW($db, $params, $currentUser=null){

    global $mysqli, $wg_ids, $publicOnly, $params_global, $top_query;
    
    $params_global = $params;

    $mysqli = $db;

    if(!$params) $params = array();//$_REQUEST;

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

    if(is_array(@$params['q'])){
        $query_json = $params['q'];
    }else{
        $query_json = json_decode(@$params['q'], true);
    }

    
    $query = new HQuery( "0", $query_json, $search_domain, $currUserID );
    $top_query = $query;
    $query->makeSQL();

    //1. create tree of predicates
    //2. make where


    // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------

    $limit = get_limit($params);

    $offset = get_offset($params);

    if(!$query->where_clause){
        $query->where_clause = "(1=1)";
    }

    // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
    return array("from"=>" FROM ".$query->from_clause, "where"=>$query->where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

}

//http://heurist.sydney.edu.au/HEURIST/h4/hserver/controller/record_search.php?db=artem_clone1&q={"t":3}
//http://heurist.sydney.edu.au/HEURIST/h4/hserver/controller/record_search.php?db=artem_clone1&q={"f:1":"girls"}
//http://heurist.sydney.edu.au/HEURIST/h4/hserver/controller/record_search.php?db=artem_clone1&q={"all":[{"f:1":"girl"},{"t":"5"}]}

//http://heurist.sydney.edu.au/HEURIST/h4/hserver/controller/record_search.php?db=artem_clone1&q={"linked_to:15":{"t":"10"}}
//http://heurist.sydney.edu.au/HEURIST/h4/hserver/controller/record_search.php?db=artem_clone1&q=[{"t":3},{"linked_to:15":{"t":"10"}}]


// [{"t":3},{"f:1":"?"},{"linked_to:15":[{"t":"10"},{"f:1":"?"}   ] } ]

                                                                  
class HQuery {

    var $from_clause = '';
    var $where_clause = '';
    var $sort_clause = '';
    var $recVisibilityType;
    var $parentquery = null;

    var $top_limb = array();
    var $sort_phrases;
    var $sort_tables; // sorting may require the introduction of more tables

    var $currUserID;
    var $search_domain;

    var $level = "0";
    var $cnt_child_query = 0;


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

        global $publicOnly, $wg_ids, $params_global;

        $res = $this->top_limb->makeSQL(); //it creates where_clause and fill tables array

        if($this->search_domain!=null){

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

            if($recVisibilityType && $recVisibilityType!="hidden"){
                $where2 = '(r0.rec_NonOwnerVisibility="'.$recVisibilityType.'")';  //'pending','public','viewable'
            }else{
                if($recVisibilityType){ //hidden
                    $where2 = 'r0.rec_NonOwnerVisibility="hidden" and ';
                }else{
                    $where2 = '(not r0.rec_NonOwnerVisibility="hidden") or ';
                }

                $where2 = '( '.$where2.'r0.rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
            }
            $where2 = '(not r0.rec_FlagTemporary) and '.$where2;

            if(trim($this->where_clause)!=''){
                $this->where_clause = $this->where_clause. ' and ' . $where2;
            }else{
                $this->where_clause = $where2;
            }
            
            //apply sort clause for top level only
            $this->createSortClause();
        }

    }
    
    //
    // sort phrases must be on top level array - all others will be ignored
    //
    function extractSortPharses( $query_json ){
        
        $this->sort_phrases = array();
        
        foreach ($query_json as $key => $value){
            if(is_numeric($key)){  //this is sequental array
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
    //
    //    
    function createSortClause() {
        
        $sort_fields = array();
        $sort_expr = array();
        
//error_log(print_r($this->sort_phrases,true)        );
        
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
                    
                case 'f': case 'field':
                    if($dty_ID!=null && !in_array($dty_ID, $sort_fields)) {
                    $sort_expr[] = 
'ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=r'.$this->level.'.rec_ID and dtl_DetailTypeID='.$dty_ID.' ORDER BY link.rec_Title limit 1), "~~"), r'.$this->level.'.rec_Title';                    
                        $sort_fields[] = $dty_ID;   
                    }
                    
            }//switch
        
        }//foreach
        
//error_log(print_r($sort_expr,true)        );
        
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

    //result
    var $tables = array();
    var $where_clause = "";

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
            if(is_numeric($key)){  //this is sequental array
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

    function makeSQL(){

        $cnj = $this->allowed[$this->conjunction];
        $where = "";
        $from = "";

        if($this->conjunction=="not"){

            $res = $this->limbs[0]->makeSQL();
            if($res){
                $where = $cnj."(".$res["where"].")";
                $this->addTable(@$res["from"]);
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

class HPredicate {

    var $pred_type;
    var $field_id = null;
    var $field_type = null;

    var $value;
    var $valid = false;
    var $query = null;

    var $field_list = false; //list of id values

    var $qlevel;
    var $index_of_predicate;
    //@todo - remove?
    var $negate = false;
    var $exact = false;
    var $lessthan = false;
    var $greaterthan = false;

    var $allowed = array('title','t','modified','url','notes','type','ids','id','count','cnt',
            'f','field','linked_to','linkedfrom','related_to','relatedfrom','links','plain');
    /*
    notes, n:        record heder notes (scratchpad)
    title:           title contains
    url              record header url (rec_URL)
    addedby:         added by specified user
    added:           creation date
    date, modified:  edition date
    after, since:
    before:
    plain            query in old plain text format

    workgroup,wg,owner:

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
        if($ll>1){
            $this->field_id = $key[$ll-1];
        }

        if( in_array($this->pred_type, $this->allowed) ){
            $this->value = $value;

            if(is_array($value)){
                $level = $this->parent->level."_".$this->parent->cnt_child_query;
                $this->parent->cnt_child_query++;

                $this->query = new HQuery( $level, $value );
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

//error_log($this->value);
            
                $query = parse_query($top_query->search_domain, urldecode($this->value), null, null, $top_query->currUserID);

                $tab = 'r'.$this->qlevel;
                             
                $where_clause = $query->where_clause;
                $where_clause = str_replace('TOPBIBLIO',$tab,$where_clause);  //need to replace for particular level
                $where_clause = str_replace('TOPBKMK','b',$where_clause);
                
//error_log($where_clause);
            
                return array("where"=>$where_clause);
            case 'type':
            case 't':

                if(strpos($this->value, '-')===0){
                    $this->negate = true;
                    $this->value = substr($this->value, 1);
                }

                $eq = ($this->negate)? '!=' : '=';
                if (is_numeric($this->value)) {
                    $res = "$eq ".intval($this->value);
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
            
                $this->pred_type = strtolower($this->pred_type);
                return $this->predicateField();
                
            case 'ids':
            case 'id':

                $res = $this->predicateRecIds();

                return $res;

            case 'linked_to':

                return $this->predicateLinkedTo();

            case 'linkedfrom':

                return $this->predicateLinkedFrom();

            case 'related_to':

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

    function predicateField(){
        global $mysqli;

        $p = "rd".$this->qlevel.".";
        $p = "";
        
        if($this->pred_type=='count' || $this->pred_type=='cnt'){
            $this->field_type = 'link'; //integer without quotes
        }else if(intval($this->field_id)>0){
            //find field type - @todo from cache
            $this->field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$this->field_id);
        }else{
            $this->field_type = 'freetext';
        }
        
        $is_empty = $this->isEmptyValue(); //search for empty or not defined value
        
        $val = $this->getFieldValue();
        if(!$val) return null;
        
        $sHeaderField = null;
        
        if($this->pred_type=='title' || $this->field_id=='title'){

            $sHeaderField = "rec_Title";

        }else if($this->pred_type=='modified' || $this->field_id=='modified'){

            $sHeaderField = "rec_Modified";
            
        }else if($this->pred_type=='url' || $this->field_id=='url'){
            
            $sHeaderField = "rec_URL";

        }else if($this->pred_type=='notes' || $this->field_id=='notes'){
            
            $sHeaderField = "rec_ScratchPad";
        }
            
          
        if($sHeaderField){    
            
            if($this->pred_type=='f') $this->pred_type = $this->field_id;
            
            if($is_empty){
                $res = "(r".$this->qlevel.".$sHeaderField is NULL OR r".$this->qlevel.".$sHeaderField=='')";    
            }else{
                $res = "(r".$this->qlevel.".$sHeaderField ".$val.")";    
            }
            
            
        }else if( $is_empty ){ //search for records where field value is not defined
            
            $res = "NOT exists (select dtl_ID from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p."dtl_DetailTypeID=".$this->field_id.")";

        }else if($this->pred_type=='count' || $this->pred_type=='cnt'){ //search for records where field occurs N times (repeatable values)

            $res = "(select count(dtl_ID) from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p."dtl_DetailTypeID=".$this->field_id.")".$val;
        
            
        }else{
            
            if($this->field_type == 'date' && trim($this->value)!='') { //false && $this->isDateTime()){
                $field_name = 'getTemporalDateString('.$p.'dtl_Value) ';
                //$field_name = 'str_to_date(getTemporalDateString('.$p.'dtl_Value), "%Y-%m-%d %H:%i:%s") ';
            }else if($this->field_type == 'file'){
                $field_name = $p."dtl_UploadedFileID ";  //only for search non empty values
            }else{
                $field_name = $p."dtl_Value ";
            }

            //old $res = $p."dtl_DetailTypeID=".$this->field_id." AND ".$p."dtl_Value ".$val;

            $res = "exists (select dtl_ID from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p."dtl_DetailTypeID=".$this->field_id." AND ".$field_name.$val.")";

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
        $this->value = $keep_val;
        $val = $this->getFieldValue();
        if(!$val) return null;

        $res = 'exists (select dtl_ID from recDetails '.$p
        . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
        . ' left join Records link on '.$p.'dtl_Value=link.rec_ID '
        .' where r'.$this->qlevel.'.rec_ID='.$p.'dtl_RecID '
        .'  and if(dty_Type != "resource", '
                .' if(dty_Type="enum", '.$p.'dtl_Value'.$val_enum 
                   .', '.$p.'dtl_Value'.$val
                   .'), link.rec_Title '.$val.'))';   //'like "%'.$mysqli->real_escape_string($this->value).'%"))';

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
            }
        }

        $where = "r$p.rec_ID".$val;

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
        
        if($this->isEmptyValue()){
            
            $rd = "rd".$this->qlevel;
            
            if($this->field_id){
                //no pointer field
                $where = "NOT exists (select dtl_ID from recDetails $rd where r$p.rec_ID=$rd.dtl_RecID AND "
            ."$rd.dtl_DetailTypeID=".$this->field_id.")";
            
            }else{
                //no links at all
                $where = "r$p.rec_ID NOT IN (select rl_SourceID from recLinks where $rl.rl_RelationID is null";
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

            $where = "r$p.rec_ID=$rl.rl_SourceID AND ".
            (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID is null")
            ." AND $rl.rl_TargetID".$val;

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

        $where = "r$p.rec_ID=$rl.rl_TargetID AND ".
        (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID is null")
        ." AND $rl.rl_SourceID".$val;

        return array("from"=>"recLinks ".$rl, "where"=>$where);
    }


    /**
    * find records that are source relation for specified records
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
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        $where = "r$p.rec_ID=$rl.rl_SourceID AND ".
        (($this->field_id && false) ?"$rl.rl_RelationTypeID=".$this->field_id :"$rl.rl_RelationID is not null")
        ." AND $rl.rl_TargetID".$val;

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

            return "between '$datestamp0' and '$datestamp1'";

        }else{

            $datestamp = validateAndConvertToISO($this->value);

            if ($this->exact) {
                return "= '$datestamp'";
            }
            else if ($this->lessthan) {
                return "< '$datestamp'";
            }
            else if ($this->greaterthan) {
                return "> '$datestamp'";
            }
            else {
                return "like '$datestamp%'";

                //old way
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                $match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];
                }
                else if (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = date('Y-m', $timestamp);
                }
                else {
                    $date = date('Y-m-d', $timestamp);
                }
                return "like '$date%'";
            }
        }
    }

    /*
      is search for empty or null value
    */
    function isEmptyValue(){
                                            //$this->value=='' ||
        return !is_array($this->value) && ( strtolower($this->value)=='null');
    }

    /**
    * put your comment there...
    *
    */
    function getFieldValue(  ){

        global $mysqli, $params_global;

        //@todo between , datetime, terms
        if(is_array($this->value)){
            error_log('value expected string - array given');
            error_log(print_r($params_global,true));
            $this->value = reset($this->value);
        }

        //
        if (strpos($this->value,"<>")===false) {
            
            if(strpos($this->value, '-')===0){
                $this->negate = true;
                $this->value = substr($this->value, 1);
            }else if(strpos($this->value, '=')===0){
                $this->exact = true;
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

        if(trim($this->value)=='') return "!=''";   //find any non empty value

        $eq = ($this->negate)? '!=' : (($this->lessthan) ? '<' : (($this->greaterthan) ? '>' : '='));
        
        if($this->field_type=='enum' || $this->field_type=='relationtype'){
            
            if (preg_match('/^\d+(?:,\d*)+$/', $this->value)){   //numeric comma separated
                $res = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }else if(intval($this->value)>0){
                $res = ' in (select trm_ID from defTerms where trm_ID='
                    .$this->value.' or trm_ParentTermID='.$this->value.')';
            }else{
                $value = $mysqli->real_escape_string($this->value);
                $res  = ' in (select trm_ID from defTerms where trm_Label ';
                if($this->exact){
                    $res  =  $res.'="'.$value.'"'; 
                } else {
                    $res  =  $res.'like "%'.$value.'%"';
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
                $res = " $eq '".($this->field_type=='float'?floatval($this->value):intval($this->value))."'";
            }
            $this->field_list = true;
        }
        else {
            
            if($this->pred_type=='title' || $this->pred_type=='url' || $this->pred_type=='notes'){
                $cs_ids = null;
            }else{
                $cs_ids = getCommaSepIds($this->value);
            }
            
            if ($cs_ids) {  
            //if (preg_match('/^\d+(?:,\d*)+$/', $this->value)) { - it does not work for >500 entries
                                
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

                }else{

                    if($eq=='=' && !$this->exact){
                        $eq = 'like';
                        $k = strpos($this->value,"%");
                        if($k===false || ($k>0 && $k+1<strlen($this->value))){
                            $this->value = '%'.$this->value.'%';
                        }
                    }

                    $res = " $eq '" . $mysqli->real_escape_string($this->value) . "'";
                }
            }
            
        }

        return $res;

    }

}

/*
{ all: { t:4, f1:peter , any: {}  } }

query is json array  { conjunction: [ {predicate} , {predicate}, .... ] }

{ any: [ {predicate} , {predicate}, .... ],  }

[ ]

*/
?>
