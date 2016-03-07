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


/*

query is json array  { conjunction: [ {predicate} , {predicate}, .... ] }

conjunction:  not, all (default)
any        OR
notall     NOT ( AND )
notany     NOT ( OR )

predicate:   keyword:value

KEYWORDS

url, u:           url
notes, n:        description
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

-----
VALUE

1)  Literal   "f:1":"Peter%"
2)  CSV       "ids":"1,2,3,4"
3)  WKT       "f:5":"POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"
4)  Query     "linked_to:15": [{ t:4 }, {"f:1":"Alex" } ]
"f:10": {"any":[ {t:4}, {} ] }




*/


/*

select


*/
$mysqli = null;
$wg_ids = null;
$publicOnly = false;

/*

[{"t":14},{"f:74":"X0"},
{"related_to:100":[{"t":"15"},{"f:80":"X1"}]},   //charge/conviction
{"relatedfrom:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:18":"X6"}]},   //persons involved

{"related_to:99":[{"t":"12"}, {"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}  //address->street(11)  and linkedfrom role(16) role of place
]

*/
// $params need for
// q - json query array
// w - domain all or bookmarked
// limit and offset
// @todo s - sort
// @todo publiconly
//
function get_sql_query_clauses_NEW($db, $params, $currentUser=null){

    global $mysqli, $wg_ids, $publicOnly;

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
    var $sort_tables;

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

        //find all field types


    }

    function makeSQL(){

        global $publicOnly, $wg_ids;

        $res = $this->top_limb->makeSQL();

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
        $this->where_clause = $this->where_clause.$this->top_limb->where_clause." ";

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

            $this->where_clause = $this->where_clause. ' and ' . $where2;
        }

    }

}

/**
*
*
*/
class HLimb {

    var $parent;           //query
    var $limbs = array();  //limbs and predicates
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

        foreach ($query_json as $key => $value){

            //echo "<br>key=".$key."  ".(print_r($value,true));
            if(is_numeric($key)){
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
        return array("from"=>$this->tables, "where"=>$where);
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

    var $allowed = array("title","t","type","ids","id","f","field","linked_to","linkedfrom","related_to","relatedfrom","links");
    /*
    url, u:           url
    notes, n:        description
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

    function makeSQL(){

        global $mysqli;

        /*if(false && $this->query){

        $this->query->makeSQL();
        $query = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
        //return array("from"=>"", "where"=>$this->pred_type.$query);
        }*/

        switch (strtolower($this->pred_type)) {
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
                else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                    // comma-separated list of defRecTypes ids
                    $in = ($this->negate)? 'not in' : 'in';
                    $res = "$in (" . $this->value . ")";
                }
                else {
                    $res = "$eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
                }
                $res = "r".$this->qlevel.".rec_RecTypeID ".$res;
                return array("where"=>$res);

            case 'field':
            case 'f':

                if(!$this->field_id){ //field type not defined
                    return $this->predicateAnyField();
                }else{
                    return $this->predicateField();
                }

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

        if(intval($this->field_id)>0){
            //find field type
            $this->field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$this->field_id);
        }else{
            $this->field_type = 'freetext';
        }
        
        $val = $this->getFieldValue();
        if(!$val) return null;

        if($this->field_id=="title"){

            $res = "(r".$this->qlevel.".rec_Title ".$val.")";

        }else if($this->field_id=="modified"){

            $res = "(r".$this->qlevel.".rec_Modified ".$val.")";

        }else{
            
            if(false && $this->isDateTime()){
                $field_name = 'str_to_date(getTemporalDateString('.$p.'.dtl_Value), "%Y-%m-%d %H:%i:%s") ';
            }else{
                $field_name = $p."dtl_Value ";
            }

            //old $res = $p."dtl_DetailTypeID=".$this->field_id." AND ".$p."dtl_Value ".$val;

            $res = "exists (select dtl_ID from recDetails ".$p." where r".$this->qlevel.".rec_ID=".$p."dtl_RecID AND "
            .$p."dtl_DetailTypeID=".$this->field_id." AND ".$field_name.$val.")";

        }

        return array("where"=>$res);  ///"from"=>"recDetails rd",

    }

    function predicateAnyField(){


    }

    function predicateRecIds(){

        $this->field_type = "link";
        $p = $this->qlevel;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

        $this->field_type = "link";

        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

    /**
    * find records that have pointers to specified records
    */
    function predicateLinkedFrom(){

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){

            $this->query->makeSQL();

            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

        $this->field_type = "link";
        $p = $this->qlevel; //parent level
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE ".$this->query->where_clause.")";
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

        if (strpos($this->value,"<>")>0) {
            $vals = explode("<>", $this->value);

            if(is_numeric($vals[0]) || is_numeric($vals[1])) return false;

            $timestamp0 = strtotime($vals[0]);
            $timestamp1 = strtotime($vals[1]);
        }else{

            if(is_numeric($this->value)) return false;

            $timestamp0 = strtotime($this->value);
            $timestamp1 = 1;
        }
        return ($timestamp0  &&  $timestamp0 != -1 && $timestamp1  &&  $timestamp1 != -1);
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


    /**
    * put your comment there...
    *
    */
    function getFieldValue(  ){

        global $mysqli;

        //@todo between , datetime, terms

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

        if($this->value=='') return null;

        $eq = ($this->negate)? '!=' : (($this->lessthan) ? '<' : (($this->greaterthan) ? '>' : '='));
        
        if($this->field_type=='enum' || $this->field_type=='relationtype'){
            
            if (preg_match('/^\d+(?:,\d+)+$/', $this->value)){
                $res = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }else if(intval($this->value)>0){
                $res = ' in (select trm_ID from defTerms where trm_ID='
                    .$this->value.' or trm_ParentTermID='.$this->value.')';
            }else{
                $value = $mysqli->real_escape_string($this->value);
                $match_pred = ' in (select trm_ID from defTerms where trm_Label="'
                    .$value.'" or trm_Code="'.$value.'")';
            }
            $res = (($this->negate)?' not':'').$res;
            
        }else
        if (is_numeric($this->value)) {
            if($this->field_type == "link"){
                $res = " $eq ".intval($this->value);  //no quotes
            }else{
                $res = " $eq '".intval($this->value)."'"; //floatval
            }
            $this->field_list = true;
        }
        else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
            // comma-separated list of defRecTypes ids
            $in = ($this->negate)? 'not in' : 'in';
            $res = " $in (" . $this->value . ")";

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
