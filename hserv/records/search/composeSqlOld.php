<?php
/**
* composeSqlOld.php - Translates heurist query to SQL query
*
* @project     Heurist academic knowledge management system
* @package Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/
use hserv\utilities\Temporal;

define('BOOKMARK', 'bookmark');
define('NO_BOOKMARK', 'nobookmark');
define('BIBLIO', 'biblio');//outdated @toremove
define('EVERYTHING', 'everything');


define('SORT_FIXED', 'set');
define('SORT_POPULARITY', 'p');
define('SORT_RATING', 'r');
define('SORT_URL', 'u');
define('SORT_MODIFIED', 'm');
define('SORT_ADDED', 'a');
define('SORT_TITLE', 't');
define('SORT_ID', 'id');
define('REGEX_CSV', '/^\d+(?:,\d*)+$/');

define('SQL_RL_SOURCE_LINK',' rl.rl_SourceID=rd.rec_ID ');
define('SQL_RL_TARGET_LINK',' rl.rl_TargetID=rd.rec_ID ');
define('SQL_RELATION_IS_NULL',' rl.rl_RelationID IS NULL ');
define('SQL_RELATION_IS_NOT_NULL',' rl.rl_RelationID IS NOT NULL ');
define('SQL_RECLINK',' recLinks rl ');
define('SQL_RECORDS',' FROM Records rd ');

define('REC_MODIFIED','f:modified');

//defined in const.php define('DT_RELATION_TYPE', 6);
global $mysqli, $currUserID, $sortType;

$mysqli = null;
$currUserID = 0;
$sortType = 0;

/**
 * Constructs a full SQL query string by combining a SELECT clause with FROM, WHERE, ORDER BY, LIMIT, and OFFSET clauses.
 *
 * This function delegates the generation of individual SQL clauses (FROM, WHERE, etc.) to `get_sql_query_clauses`.
 * It then concatenates these clauses with the provided SELECT clause to form a complete SQL query.
 *
 * @param \mysqli $db The mysqli database connection object.
 * @param string $select_clause The initial part of the SQL query, typically the SELECT statement (e.g., "SELECT rec_ID, rec_Title").
 * @param array $params An associative array of query parameters used by `get_sql_query_clauses`. Expected keys include:
 *                      'q': (string) The main query string.
 *                      's': (string, Optional) Sort order specification.
 *                      'l' or 'limit': (int, Optional) Record limit for pagination.
 *                      'o' or 'offset': (int, Optional) Record offset for pagination.
 *                      'w': (string, Optional) Search domain ('all', 'b'/'bookmark', 'e' for everything).
 *                      'stype': (string, Optional, OUTDATED) Type of search (e.g., 'key', 'all').
 *                      'publiconly': (bool, Optional) If true, search only public records.
 *                      'use_user_wss': (bool, Optional) If true and user logged in, filter by user's working subset.
 *                      'parentquery': (array, Optional) SQL clauses of a parent query for linked/relation queries.
 * @param array|null $currentUser (Optional) Associative array representing the current user. Expected keys:
 *                                'ugr_ID': (int) The user's ID.
 *                                'ugr_Groups': (array) An array where keys are group IDs the user is a member of.
 *                                If null or no 'ugr_ID', treated as anonymous public user.
 * @return string The fully constructed SQL query string.
 */
function compose_sql_query($db, $select_clause, $params, $currentUser=null) {

    // Pass $currentUser to get_sql_query_clauses
    $query = get_sql_query_clauses($db, $params, $currentUser);

    $res_query =  $select_clause.$query["from"].SQL_WHERE.$query["where"].$query["sort"].$query["limit"].$query["offset"];
    return $res_query;
}

/**
 * Generates the main SQL clauses (FROM, WHERE, ORDER BY, LIMIT, OFFSET) for a Heurist search query.
 *
 * This function processes various parameters to define the search domain, user permissions,
 * query text, sorting, and pagination. It uses the `Query` class to parse the query string
 * and generate the core WHERE and FROM clauses. It then applies additional filters for
 * visibility, user worksets, and special conditions like '_BROKEN_' or '_NOTLINKED_'.
 *
 * @param \mysqli $db The mysqli database connection object.
 * @param array $params An associative array of query parameters. Expected keys:
 *                      'q': (string) The main query string containing keywords and values.
 *                      's': (string, Optional) Sort order specification (e.g., "title", "-modified").
 *                           Note: Sorting can also be specified within the 'q' parameter using "sortby:".
 *                      'l' or 'limit': (int, Optional) Record limit for pagination.
 *                      'o' or 'offset': (int, Optional) Record offset for pagination.
 *                      'w': (string, Optional) Search domain. Default 'all'.
 *                           - 'all': All records accessible to the user (excluding temporary unless admin).
 *                           - 'b' or 'bookmark': Records bookmarked by the current user.
 *                           - 'e' (everything): All records, including temporary ones (typically for admin users).
 *                      'stype': (string, Optional, OUTDATED) Type of search (e.g., 'key' for tag title, 'all' for record/resource title).
 *                               This influences default search fields if not specified in 'q'.
 *                      'publiconly': (bool, Optional) If true, restricts search to publicly visible records.
 *                      'use_user_wss': (bool, Optional) If true and a user is logged in, an additional filter
 *                                      is applied to include only records in the user's working subset (`usrWorkingSubsets`).
 *                      'parentquery': (array, Optional) SQL clauses of a parent/top query. This is used for context in
 *                                     linked and relation queries that depend on a source/top query.
 * @param array|null $currentUser (Optional) Associative array representing the current user. Expected keys:
 *                                'ugr_ID': (int) The user's ID.
 *                                'ugr_Groups': (array) An array where keys are group IDs the user is a member of.
 *                                If null, or if 'ugr_ID' is not set or is 0, the search is treated as by an anonymous public user.
 * @return array An associative array containing the generated SQL clauses:
 *               'from'   => (string) SQL FROM clause.
 *               'where'  => (string) SQL WHERE clause.
 *               'sort'   => (string) SQL ORDER BY clause.
 *               'limit'  => (string) SQL LIMIT clause.
 *               'offset' => (string) SQL OFFSET clause.
 */
function get_sql_query_clauses($db, $params, $currentUser=null) {

    global $mysqli, $currUserID, $sortType;

    $mysqli = $db;

    /* use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $select_clause */
    if (! $params) {$params = array();} // $_REQUEST
    if(@$params['stype']) {$sortType = @$params['stype'];}

    // 1. DETECT CURRENT USER AND ITS GROUPS, if not logged search only all records (no bookmarks) ----------------------
    $wg_ids = array();//may be better use $system->getUserGroupIds() ???
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
    array_push($wg_ids, 0);// be sure to include the generic everybody workgroup

    $publicOnly = (@$params['publiconly']==1);//@todo

    // 2. DETECT SEARCH DOMAIN ------------------------------------------------------------------------------------------
    if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0) {    // my bookmark entries
        $search_domain = BOOKMARK;
    } elseif (@$params['w'] == 'e') { //everything - including temporary
        $search_domain = EVERYTHING;
    } else {                // all records entries
        $search_domain = null;
    }

    //for database owner we will search records of any workgroup and view access
    //@todo UNLESS parameter owner is not defined explicitely
    if($currUserID==2 && $search_domain != BOOKMARK){
        $wg_ids = array();
    }

    // 3a. SPECIAL CASE for _BROKEN_

    $needbroken = false;
    if (@$params['q'] && preg_match('/\\b_BROKEN_\\b/', $params['q'])) {
        $params['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $params['q']);
        $needbroken = true;
    }
    // 3b. SPECIAL CASE for _NOTLINKED_

    $neednotlinked = false;
    if (@$params['q'] && preg_match('/\\b_NOTLINKED_\\b/', $params['q'])) {
        $params['q'] = preg_replace('/\\b_NOTLINKED_\\b/', '', $params['q']);
        $neednotlinked = true;
    }

    // 4. QUERY MAY BE SIMPLE or full expressiveness ----------------------------------------------------------------------

    $query = parse_query($search_domain, @$params['q'], @$params['s'], @$params['parentquery'], $currUserID);

    $where_clause = $query->where_clause;

    // 4a. SPECIAL CASE for _BROKEN_
    if($needbroken){
        $where_clause = ' (rec_URLErrorMessage is not null) '
        . ($where_clause? SQL_AND.$where_clause :'');
        //'(to_days(now()) - to_days(rec_URLLastVerified) >= 8) '
    }
    // 4b. SPECIAL CASE for _NOTLINKED_
    if($neednotlinked){
        $where_clause = '(not exists (select rl_ID from recLinks where rl_SourceID=TOPBIBLIO.rec_ID  or rl_TargetID=TOPBIBLIO.rec_ID )) '
            . ($where_clause? SQL_AND.$where_clause :'');
    }

    // 4c. SPECIAL CASE for USER WORKSET
    if(@$params['use_user_wss']===true && $currUserID>0){

        $q2 = "select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID=$currUserID LIMIT 1";
        if(mysql__select_value($mysqli, $q2)>0){
            $where_clause = '(exists (select wss_RecID from usrWorkingSubsets where wss_RecID=TOPBIBLIO.rec_ID and wss_OwnerUGrpID='.$currUserID.'))'
                . ($where_clause? SQL_AND.$where_clause :'');
        }
    }

    // 5. DEFINE USERGROUP RESTRICTIONS ---------------------------------------------------------------------------------

    if ($search_domain != EVERYTHING) {

        if ($where_clause) {$where_clause = "( $where_clause ) and ";}

        if ($search_domain == BOOKMARK) {
            $where_clause .= ' (bkm_UGrpID=' . $currUserID . ' and not TOPBIBLIO.rec_FlagTemporary) ';
        } elseif($search_domain == BIBLIO) {   //NOT USED
            $where_clause .= ' (bkm_UGrpID is null and not TOPBIBLIO.rec_FlagTemporary) ';
        } else {
            $where_clause .= ' (not TOPBIBLIO.rec_FlagTemporary) ';
        }

    }

    if($publicOnly){
        $query->recVisibilityType = "public";
    }

    $where2 = '';
    $where2_conj = '';

    if($query->recVisibilityType && $currUserID>0){

        if($query->recVisibilityType=="public"){
            $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="'.$query->recVisibilityType.'")';//'pending','public','viewable'
        }else{

            if($query->recVisibilityType=='viewable'){

                $query->from_clause = $query->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=TOPBIBLIO.rec_ID ';

                //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="viewable"';
                if(!empty($wg_ids)){
                    $where2 .= ' and (rcp_UGrpID is null or rcp_UGrpID in ('.join(',', $wg_ids).'))';
                }
                $where2 .= ')';

                $where2_conj = SQL_AND;
            }else{
                $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="'.$query->recVisibilityType.'")';
                $where2_conj = SQL_AND;
            }

            if(!isEmptyArray($wg_ids)){
                $where2 = '( '.$where2.$where2_conj.'TOPBIBLIO.rec_OwnerUGrpID ';
                if(count($wg_ids)>1){
                    $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';
                }else{
                    $where2 = $where2 .' = '.$wg_ids[0] . ' )';
                }
            }
        }


    }else{
        //visibility type not defined - show records visible for current user
        if($currUserID!=2){
            $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility in ("public","pending"))';//any can see public

            if ($currUserID>0){ //logged in can see viewable
                $query->from_clause = $query->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=TOPBIBLIO.rec_ID ';
                //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                $where2 = $where2.' or (TOPBIBLIO.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in ('
                        .join(',', $wg_ids).')))';
            }
            $where2_conj = ' or ';

        }elseif($search_domain != BOOKMARK){ //database owner can search everything (including hidden)
            $wg_ids = array();
        }

        if(!isEmptyArray($wg_ids) && $currUserID>0){
            //for hidden
            $where2 = '( '.$where2.$where2_conj.'TOPBIBLIO.rec_OwnerUGrpID ';
            if(count($wg_ids)>1){
                $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';
            }else{
                $where2 = $where2 .' = '.$wg_ids[0] . ' )';
            }
        }
    }

    if($where2!=''){
        $where_clause = $where_clause . SQL_AND . $where2;
    }

    // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------

    $limit = get_limit($params);

    $offset = get_offset($params);


    // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
    return array("from"=>$query->from_clause, "where"=>$where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

}

/**
 * Determines the record limit for an SQL query from request parameters.
 *
 * It checks for 'l' and then 'limit' keys in the `$params` array.
 * If neither is found, or if the value is less than 1, it defaults to 100,000.
 *
 * @param array $params An associative array of request parameters.
 * @return int The determined limit value.
 */
function get_limit($params){
    if (@$params["l"]) {
        $limit = intval($params["l"]);
    }elseif(@$params["limit"]) {
        $limit = intval($params["limit"]);
    }

    if (!@$limit || $limit < 1){
        $limit = 100000;
    }
    return $limit;
}

/**
 * Determines the record offset for an SQL query from request parameters.
 *
 * It checks for 'o' and then 'offset' keys in the `$params` array.
 * If neither is found, or if the value is less than 1, it defaults to 0.
 *
 * @param array $params An associative array of request parameters.
 * @return int The determined offset value.
 */
function get_offset($params){
    $offset = 0;
    if (@$params["o"]) {
        $offset = intval($params["o"]);
    }elseif(@$params["offset"]) {
        $offset = intval($params["offset"]);// this is back in since hml.php passes through stuff from sitemap.xmap
    }
    if (!@$offset || $offset < 1){
        $offset = 0;
    }
    return $offset;
}

/**
* Returns array with 3 elements: FROM, WHERE and ORDER BY
*
* @param string|null $search_domain Search domain: 'bookmark' for user's bookmarks, null or other values for all accessible records.
* @param string|null $text The raw query string text.
* @param string|null $sort_order Optional sort order string (e.g., "title", "-modified").
*                                This is an older way of specifying sort, `sortby:` in query text is preferred.
* @param array|null $parentquery An array of SQL clauses from a parent/top query, used for context in linked/relation sub-queries.
*                                Expected keys: "from", "where", "sort", "limit", "offset".
* @param int $currUserID The ID of the current user.
* @return Query A Query object containing the parsed query structure and generated SQL clauses.
*
* @todo Document the $wg_ids parameter if it's actually used or remove if fully NOTUSED. Currently marked NOTUSED.
*/
function parse_query($search_domain, $text, $sort_order, $parentquery, $currUserID) {

    if($sort_order==null) {$sort_order = '';}

    // remove any  lone dashes outside matched quotes.
    $text = preg_replace('/- (?=[^"]*(?:"[^"]*"[^"]*)*$)|(?:-\s*$)/', ' ', $text);
    // divide the query into dbl-quoted and other (note a dash(-) in front of a string is preserved and means negate)
    preg_match_all('/(-?"[^"]+")|([^" ]+)/',$text,$matches);
    $preProcessedQuery = "";
    $connectors = array(":",">","<","=",",");
    foreach ($matches[0] as $queryPart) {
        //if the query part is not a dbl-quoted string (ignoring a preceeding dash and spaces)
        //necessary since we want double quotes to allow all characters
        if (!preg_match('/^\s*-?".*"$/',$queryPart)) {
            // clean up the query.
            // liposuction out all the non-kocher characters
            // (this means all punctuation except -, _, %(45), () (50,51) :, ', ", = and ,  ...?)
            $queryPart = preg_replace('/[\000-\041\043-\044\046\052-\053\073\077\100\133\135\136\140\173-\177]+/s', ' ', $queryPart);
        }
        //reconstruct the string
        $addSpace = $preProcessedQuery != "" && !in_array($preProcessedQuery[strlen($preProcessedQuery)-1],$connectors) && !in_array($queryPart[0],$connectors);
        $preProcessedQuery .= ($addSpace ? " ":"").$queryPart;
    }
    if(trim($preProcessedQuery)==''){
        $preProcessedQuery = '"'.$text.'"';
    }


    $query = new Query($search_domain, $preProcessedQuery, $currUserID, $parentquery);
    $query->makeSQL();

    $q = null;

    if ($query->sort_phrases) {
        // already handled in Query logic
    } elseif (preg_match('/^f:(\d+)/', $sort_order, $matches)) {
        //mindfuck!!!! - sort by detail?????
        $q = 'ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID='.$matches[1].' ORDER BY link.rec_Title limit 1), "~~"), rec_Title';
    } else {
        if ($search_domain == BOOKMARK) {
            switch ($sort_order) {
                case SORT_FIXED:
                    if($query->fixed_sortorder){
                        $q = "FIND_IN_SET(TOPBIBLIO.rec_ID, '{$query->fixed_sortorder})";
                    }else{
                        $q = null;
                    }
                    break;
                case SORT_POPULARITY:
                    $q = 'rec_Popularity desc, rec_Added desc'; break;
                case SORT_RATING:
                    $q = 'bkm_Rating desc'; break;
                case SORT_URL:
                    $q = 'rec_URL is null, rec_URL'; break;
                case SORT_MODIFIED:
                    $q = 'bkm_Modified desc'; break;
                case SORT_ADDED:
                    $q = 'bkm_Added desc'; break;
                case SORT_ID:
                    $q = 'rec_ID asc'; break;
                case SORT_TITLE: default:
                    $q = 'rec_Title = "", rec_Title';
            }
        } else {
            switch ($sort_order) {
                case SORT_FIXED:
                    if($query->fixed_sortorder){
                        $q = "FIND_IN_SET(TOPBIBLIO.rec_ID, '{$query->fixed_sortorder}')";
                    }else{
                        $q = null;
                    }
                    break;
                case SORT_POPULARITY:
                    $q = 'rec_Popularity desc, rec_Added desc'; break;
                case SORT_URL:
                    $q = 'rec_URL is null, rec_URL'; break;
                case SORT_MODIFIED:
                    $q = 'rec_Modified desc'; break;
                case SORT_ADDED:
                    $q = 'rec_Added desc'; break;
                case SORT_ID:
                    $q = 'rec_ID asc'; break;
                case SORT_TITLE: default:
                    $q = 'rec_Title = "", rec_Title';
            }
        }

    }
    if($q){ //sort defined in separate request param
        $query->sort_clause = ' ORDER BY '.$q;
    }
    return $query;
}


class Query {

    /** @var string The generated SQL FROM clause. */
    public $from_clause = '';
    /** @var string The generated SQL WHERE clause. */
    public $where_clause = '';
    /** @var string The generated SQL ORDER BY clause. */
    public $sort_clause = '';
    /** @var string|null Visibility type restriction (e.g., "public", "viewable", "hidden"). Set by `addVisibilityTypeRestriction`. */
    public $recVisibilityType;
    /** @var array|null SQL clauses from a parent query, used for context in sub-queries. */
    public $parentquery = null;

    /** @var array Array of top-level "AND" limbs. Each element is an array of `OrLimb` objects. */
    public $top_limbs;
    /** @var array Array of `SortPhrase` objects representing the sort criteria. */
    public $sort_phrases;
    /** @var array Array of table names/aliases that need to be added to the FROM clause due to sorting requirements. */
    public $sort_tables;

    /** @var string|null A comma-separated list of record IDs for fixed order sorting (used with `sortby:set` or `sortby:fixed`). */
    public $fixed_sortorder = null;

    /** @var string|null The search domain (e.g., 'bookmark', 'all'). */
    public $search_domain;
    /** @var int The ID of the current user. */
    public $currUserID;
    /** @var bool Flag indicating if the query string should be treated as an absolute string (influences predicate creation). */
    public $absoluteStrQuery;


    /**
     * Constructor for the Query class.
     *
     * Initializes the Query object, parses the input query text to extract
     * visibility type restrictions (`vt:`), sort phrases (`sortby:`),
     * and then breaks down the remaining query text into a structure of
     * AND/OR limbs.
     *
     * @param string|null $search_domain The search domain (e.g., 'bookmark', 'all').
     * @param string $text The raw query string.
     * @param int $currUserID The ID of the current user.
     * @param array|null $parentquery SQL clauses from a parent query, for context.
     * @param bool $absoluteStrQuery If true, the query text is treated as an absolute string. Default false.
     */
    public function __construct($search_domain, $text, $currUserID, $parentquery, $absoluteStrQuery = false) {

        $this->search_domain = $search_domain;
        $this->recVisibilityType = null;
        $this->currUserID = $currUserID;
        $this->absoluteStrQuery = $absoluteStrQuery;
        $this->parentquery = $parentquery;

        $this->top_limbs = array();
        $this->sort_phrases = array();
        $this->sort_tables = array();

        // Find any 'vt:' phrases in the query, and pull them out.   vt - visibility type
        // Handles 'vt:"public"', 'vt:f:"field_name_public"' etc.
        while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(vt:(?:f:|field:|geo:)?"[^"]+"\\S*|vt:\\S*)/', $text, $matches)) {
            $this->addVisibilityTypeRestriction(substr($matches[2],3));
            $text = preg_replace('/\bvt:\S+/i', '', $text); // Remove the matched vt: phrase
            //$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2])); // Alternative way to remove
        }

        // Find any 'sortby:' phrases in the query, and pull them out.
        // "sortby:..." within double quotes is regarded as a search term, and we don't remove it here.
        // Handles 'sortby:title', 'sortby:"-f:23"' etc.
        while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(sortby:(?:f:|field:)?"[^"]+"\\S*|sortby:\\S*)/', $text, $matches)) {
            $this->addSortPhrase($matches[2]); // Pass the full sortby phrase e.g., "sortby:title"
            $text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2])); // Remove the matched sortby phrase
        }

        // Search-within-search gives us top-level ANDing (full expressiveness of conjunctions and disjunctions)
        // except matches between quotes
        preg_match_all('/"[^"]+"|(&&|\\bAND\\b)/i', $text, $matches, PREG_OFFSET_CAPTURE);
        $q_bits = array();
        $offset = 0;
        if(!empty($matches[1])){
            foreach($matches[1] as $entry){
                if(is_array($entry)){ //
                    array_push($q_bits, substr($text, $offset, $entry[1]-$offset));
                    $offset = $entry[1]+strlen($entry[0]);
                }
            }
        }
        if($offset<strlen($text)){
            array_push($q_bits, substr($text, $offset));
        }

        foreach ($q_bits as $q_bit) {
            $this->addTopLimb($q_bit);
        }

    }

    /**
     * Parses a segment of the query string (assumed to be an AND-conjoined part)
     * and breaks it into OR limbs.
     *
     * The input text is split by " OR " (case-insensitive) or "&&" (though "&&" typically means AND,
     * its use here as an OR delimiter might be a legacy artifact or specific local convention).
     * Text within double quotes is treated as a single unit and not split.
     * Each resulting segment is used to create a new `OrLimb` object, which is then
     * added to an array representing this top-level AND limb. This array of `OrLimb`s
     * is then added to `$this->top_limbs`.
     *
     * @param string $text A part of the query string, expected to be a series of conditions implicitly ANDed.
     * @return void
     */
    private function addTopLimb($text) {

        $or_limbs = array();
        // According to WWGD,
        // OR is treated as a high-level delimiter.
        // The regex looks for " OR " (case-insensitive) or "&&" outside of double quotes.
        preg_match_all('/"[^"]+"|(&&|\\ OR \\b)/i', $text, $matches, PREG_OFFSET_CAPTURE);
        $offset = 0;
        if(!empty($matches[1])){ // If OR or && delimiters are found
            foreach($matches[1] as $entry){
                if(is_array($entry)){ // Matched a delimiter
                    // Create an OrLimb from the text segment before this delimiter
                    array_push( $or_limbs, new OrLimb($this, substr($text, $offset, $entry[1]-$offset)) );
                    // Move offset past the delimiter
                    $offset = $entry[1]+strlen($entry[0]);
                }
            }
        }
        // Add the final segment (or the whole text if no OR delimiters were found)
        array_push( $or_limbs, new OrLimb($this, substr($text, $offset)) );

        // This group of OrLimbs (which will be ORed together) forms one AND component of the overall query.
        array_push($this->top_limbs, $or_limbs);
    }

    /**
     * Creates a `SortPhrase` object from a sort directive string and adds it to the query's sort phrases.
     *
     * The sort phrases are added to the beginning of the `$this->sort_phrases` array (using `array_unshift`),
     * implying that the last `sortby:` directive encountered in the query string might take precedence
     * or be processed first, depending on how `makeSortClause` handles this array.
     *
     * @param string $text The sort directive string (e.g., "sortby:title", "sortby:-f:23").
     * @return void
     */
    private function addSortPhrase($text) {
        array_unshift($this->sort_phrases, new SortPhrase($this, $text));
    }

    /**
     * Sets the record visibility type restriction for the query.
     *
     * The input string is cleaned (lowercase, leading '-' removed for negation, though negation isn't implemented here).
     * If the resulting string is one of 'viewable', 'hidden', 'pending', or 'public',
     * it's stored in `$this->recVisibilityType`.
     *
     * @param string $visibility_type The visibility type string (e.g., "public", "-hidden").
     * @return void
     */
    private function addVisibilityTypeRestriction($visibility_type) {
        if ($visibility_type){
            $visibility_type = strtolower($visibility_type);
            if ($visibility_type[0] == '-') {
                // Negation for visibility types is noted as not implemented in the original comment.
                $visibility_type = substr($visibility_type, 1);
            }
            if(in_array($visibility_type,array('viewable','hidden','pending','public')))
            {
                $this->recVisibilityType = $visibility_type;
            }
        }
    }

    public function makeSQL() {

        //WHERE
        $where_clause = '';
        $and_clauses = array();
        if(is_array($this->top_limbs)){
            for ($i=0; $i < count($this->top_limbs);++$i) {


            $or_clauses = array();
            $or_limbs = $this->top_limbs[$i];
            for ($j=0; $j < count($or_limbs);++$j) {
                $new_sql = $or_limbs[$j]->makeSQL();
                array_push($or_clauses, '(' . $new_sql . ')');
            }
            sort($or_clauses);// alphabetise
            $where_clause = join(' or ', $or_clauses);
            if(count($or_clauses)>1) {$where_clause = '(' . $where_clause . ')';}
            array_push($and_clauses, $where_clause);
        }
        }
        sort($and_clauses);
        $this->where_clause = join(SQL_AND, $and_clauses);

        //SORT
        $this->sort_clause = $this->makeSortClause();

        //FROM
        if ($this->search_domain == BOOKMARK) {
            $this->from_clause = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
        }else{
            $this->from_clause = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$this->currUserID.' ';
        }

        $this->from_clause .= join(' ', $this->sort_tables);// sorting may require the introduction of more tables

        //MAKE
        return $this->from_clause . SQL_WHERE . $this->where_clause . $this->sort_clause;
    }

    /**
     * Generates the SQL ORDER BY clause from the collected sort phrases.
     *
     * Iterates through each `SortPhrase` object in `$this->sort_phrases`.
     * For each phrase, it calls its `makeSQL()` method, which returns the SQL sort expression,
     * a signature for the sort clause (to avoid duplicates), and any tables required for the sort.
     * These are aggregated into a final " ORDER BY " string.
     * Tables required for sorting are added to `$this->sort_tables`.
     *
     * @return string The complete SQL ORDER BY clause (e.g., " ORDER BY rec_Title ASC, rec_Modified DESC").
     *                Returns an empty string if no valid sort phrases were processed.
     */
    private function makeSortClause() {

        $sort_clause = '';
        $sort_clauses = array(); // Used to track signatures of sort clauses to avoid duplicates
        for ($i=0; $i < count($this->sort_phrases);++$i) {
            // makeSQL() from SortPhrase returns [sql_expression, signature, tables_needed]
            @list($new_sql, $new_sig, $new_tables) = $this->sort_phrases[$i]->makeSQL();

            if($new_sql!=null && ! @$sort_clauses[$new_sig]) {    // Don't repeat identical sort clauses
                    if ($sort_clause) {$sort_clause .= ', ';} // Add comma if not the first sort criterion

                    $sort_clause .= $new_sql;
                    if ($new_tables) {array_push($this->sort_tables, $new_tables);}

                    $sort_clauses[$new_sig] = 1; // Mark this sort signature as used
            }
        }
        if ($sort_clause) {$sort_clause = ' ORDER BY ' . $sort_clause;}
        return $sort_clause;
    }

}


class OrLimb {
    /** @var array<AndLimb> Array of `AndLimb` objects. Conditions within this array are ANDed together. */
    public $and_limbs;

    /** @var Query Reference to the parent `Query` object. */
    public $parent;

    /** @var bool Inherited from the parent Query's context, indicating if the original query part was an absolute string. */
    public $absoluteStrQuery;


    /**
     * Constructor for OrLimb.
     *
     * Parses a text segment (which is part of an OR-conjoined set of conditions)
     * into `AndLimb` objects. The text is split by spaces, respecting quoted strings
     * and parenthesized groups as single units. Each resulting part forms an `AndLimb`.
     *
     * @param Query $parent Reference to the parent `Query` object.
     * @param string $text The query text segment to be parsed for AND conditions.
     */
    public function __construct(&$parent, $text) {
        $this->parent = &$parent;
        $this->absoluteStrQuery = $parent->absoluteStrQuery;
        $this->and_limbs = array();
        if (substr_count($text, '"') % 2 != 0) {$text .= '"';}// Ensure matched quotes

        // Regex to split by spaces, but keep quoted strings ("...") and parenthesized groups (...) as single tokens.
        // Example: "field:value (subfield:subvalue "quoted subvalue") another_field:"another value""
        if (preg_match_all('/(?:[^"( ]+|["(][^")]*[")])+(?= |$)/', $text, $matches)) {
            $and_texts = $matches[0];

            for ($i=0; $i < count($and_texts);++$i){
                $str = $and_texts[$i];
                if ($str!=null && $str!='') {
                    // '+' might be used as a space placeholder in some contexts, replace with actual space.
                    $str = str_replace('+', " ", $str);
                    $this->addAndLimb($str);
                }
            }
        }
    }

    /**
     * Creates an `AndLimb` object from a text segment and adds it to this `OrLimb`.
     *
     * Each `AndLimb` represents a condition that will be ANDed with others in this `OrLimb`.
     *
     * @param string $text The text segment representing a single condition or a set of implicitly ANDed conditions.
     * @return void
     */
    private function addAndLimb($text) {
        $this->and_limbs[] = new AndLimb($this, $text);
    }

    /**
     * Generates the SQL WHERE clause fragment for this OR limb.
     *
     * It iterates through all its `AndLimb` children, calls their `makeSQL()` method
     * to get their SQL conditions, and then joins these conditions with " AND ".
     * The resulting string represents the complete ANDed condition for this OR limb.
     *
     * @return string The SQL WHERE clause fragment for this limb (e.g., "(condition1 AND condition2)").
     */
    public function makeSQL() {

        $and_clauses = array();
        for ($i=0; $i < count($this->and_limbs);++$i) {
            $new_sql = $this->and_limbs[$i]->pred->makeSQL();
            if (strlen($new_sql) > 0) {
                array_push($and_clauses, $new_sql);
            }
        }
        sort($and_clauses);
        return join(SQL_AND, $and_clauses);
    }
}


class AndLimb {
    /** @var bool Whether the predicate's condition should be negated (e.g., NOT LIKE, !=). */
    public $negate;
    /** @var bool Whether an exact match (e.g., =) is required, as opposed to LIKE. */
    public $exact;
    /** @var bool Whether a less-than comparison is intended. */
    public $lessthan;
    /** @var bool Whether a greater-than comparison is intended. */
    public $greaterthan;
    /** @var bool Whether a full-text search is intended (currently not fully implemented in `createPredicate` parsing for this flag). */
    public $fulltext; // Note: This flag is set in FieldPredicate based on '@' but not directly parsed here.
    /** @var Predicate The Predicate object (e.g., TitlePredicate, FieldPredicate) representing the specific condition. */
    public $pred;

    /** @var OrLimb Reference to the parent `OrLimb` object. */
    public $parent;

    /** @var bool True if the original text for this limb was a double-quoted string, indicating it should be treated as a literal phrase. */
    public $absoluteStrQuery;


    /**
     * Constructor for AndLimb.
     *
     * An AndLimb represents a single condition part of an AND conjunction.
     * It parses operator prefixes (like '-', '=', '<', '>') from the input text,
     * sets boolean flags (`negate`, `exact`, `lessthan`, `greaterthan`),
     * and then calls `createPredicate()` to instantiate the appropriate `Predicate` subclass.
     *
     * @param OrLimb $parent Reference to the parent `OrLimb` object.
     * @param string $text The query text segment for this specific condition.
     */
    public function __construct(&$parent, $text) {
        $this->parent = &$parent;
        $this->absoluteStrQuery = false;
        if (preg_match('/^".*"$/',$text,$matches)) {
            // Check if the entire text is enclosed in double quotes.
            $this->absoluteStrQuery = true;
        }

        $this->exact = false; // Default exact match to false
        if ($text[0] == '-') {
            $this->negate = true;
            $text = substr($text, 1);
        } else {
            $this->negate = false;
        }

        //create predicate
        $this->pred = $this->createPredicate($text); // $this->pred will be an object of a Predicate subclass

    }


    /**
     * Creates and returns a specific Predicate subclass based on keywords in the query text.
     *
     * This method inspects the input text for keywords (e.g., "title:", "f:", "id:", "tag:")
     * to determine the type of predicate to create. It also handles operator prefixes
     * like '=', '<', '>' if they appear before a colon, setting flags like `$this->exact`,
     * `$this->lessthan`, `$this->greaterthan` accordingly.
     *
     * If no keyword is found, or if `absoluteStrQuery` is true, it defaults to creating a `TitlePredicate`.
     * The `$sortType` global variable (OUTDATED) could also influence the default predicate type.
     *
     * @param string $text The query text segment, potentially including a keyword and value.
     * @global string|null $sortType (Outdated) Global sort type that might influence default predicate.
     * @return Predicate An instance of a `Predicate` subclass (e.g., `TitlePredicate`, `FieldPredicate`).
     */
    private function createPredicate($text) {
        global $sortType;

        $colon_pos = strpos($text, ':');
        if ($equals_pos = strpos($text, '=')) {
            if (! $colon_pos  ||  $equals_pos < $colon_pos) {
                // an exact match has been requested
                $colon_pos = $equals_pos;
                $this->exact = true;
            }
        }
        if ($lessthan_pos = strpos($text, '<')) {
            if (! $colon_pos  ||  $lessthan_pos < $colon_pos) {
                // a less-than match has been requested
                $colon_pos = $lessthan_pos;
                $this->lessthan = true;
            }
        }
        if ($greaterthan_pos = strpos($text, '>')) {
            if (! $colon_pos  ||  $greaterthan_pos < $colon_pos) {
                // a greater-than match has been requested
                $colon_pos = $greaterthan_pos;
                $this->greaterthan = true;
            }
        }

        if ($this->absoluteStrQuery || ! $colon_pos) {    // a colon was either NOT FOUND or AT THE BEGINNING OF THE STRING
            $pred_val = $this->cleanQuotedValue($text);
            /* 2024-08-02
            if ($sortType == 'key'){
                return new TagPredicate($this, $pred_val);
            }elseif($sortType == 'all'){
                return new AnyPredicate($this, $pred_val);
            }else{    // title search is default search
                return new TitlePredicate($this, $pred_val);
            }
            */
            return new TitlePredicate($this, $pred_val);
        }

        $pred_type = substr($text, 0, $colon_pos);

        if ($pred_type[0] == '-') {    // bit of DWIM here: did the user accidentally put the negate here instead?
            $this->negate = true;
            $pred_type = substr($pred_type, 1);
        }

        $raw_pred_val = substr($text, $colon_pos+1);
        $pred_val = $this->cleanQuotedValue($raw_pred_val);
        if ($pred_val === '""') {    // special case SC100:  xxx:"" becomes equivalent to xxx="" (to find blank values, not just values that contain any string)
            $this->exact = true;
        }

        switch (strtolower($pred_type)) {
            case 'type':
            case 't':
                return new TypePredicate($this, $pred_val);

            case 'url':
            case 'u':
                return new URLPredicate($this, $pred_val);

            case 'notes':
            case 'n':
                return new NotesPredicate($this, $pred_val);

            case 'user':
            case 'usr':
                return new UserPredicate($this, $pred_val);

            case 'addedby':
                /* JT6728, fuck knows what this is going to be used for ... maybe it is for EBKUZS az FAXYUQ */
                return new AddedByPredicate($this, $pred_val);

            case 'title':
                return new TitlePredicate($this, $pred_val);

            case 'keyword':
            case 'kwd':
            case 'tag':
                return new TagPredicate($this, $pred_val);

            case 'any':
            case 'all':
                $value = $this->cleanQuotedValue($pred_val);
                return new AnyPredicate($this, $value);

            case 'id':
            case 'ids':
                return new BibIDPredicate($this, $pred_val);

            case 'fc':  //field counter

                $colon_pos = strpos($raw_pred_val, ':');
                if (! $colon_pos) {
                    if ($colon_pos = strpos($raw_pred_val, '=')) {$this->exact = true;}
                    elseif ($colon_pos = strpos($raw_pred_val, '<')) {$this->lessthan = true;}
                        elseif ($colon_pos = strpos($raw_pred_val, '>')) {$this->greaterthan = true;}
                }

                $fieldtype_id = null;

                if ($colon_pos === false){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                } elseif($colon_pos == 0){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                    $value =  substr($value, 1);
                }else{
                    $fieldtype_id = $this->cleanQuotedValue(substr($raw_pred_val, 0, $colon_pos));
                    $value = $this->cleanQuotedValue(substr($raw_pred_val, $colon_pos+1));

                    if (($colon_pos = strpos($value, '='))===0) {$this->exact = true;}
                    elseif (($colon_pos = strpos($value, '<'))===0) {$this->lessthan = true;}
                        elseif (($colon_pos = strpos($value, '>'))===0) {$this->greaterthan = true;}
                            if($colon_pos===0){
                        $value = substr($value,1);
                    }
                }

                return new FieldCountPredicate($this, $fieldtype_id, $value);

            case 'field':
            case 'f':

                $colon_pos = strpos($raw_pred_val, ':');
                if (! $colon_pos) {
                    if ($colon_pos = strpos($raw_pred_val, '=')) {$this->exact = true;}
                    elseif ($colon_pos = strpos($raw_pred_val, '<')) {$this->lessthan = true;}
                        elseif ($colon_pos = strpos($raw_pred_val, '>')) {$this->greaterthan = true;}
                            //elseif (($colon_pos = strpos($raw_pred_val, '@'))) {$this->fulltext = true;}
                }
                if ($colon_pos === false){
                    $value = $this->cleanQuotedValue($raw_pred_val);

                    if (($colon_pos = strpos($value, '@'))===0) {$this->fulltext = true;}
                    if($colon_pos===0){
                        $value = substr($value,1);
                    }

                    return new AnyPredicate($this, $value);
                } elseif($colon_pos == 0){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                    return new AnyPredicate($this, substr($value, 1));
                }else{
                    //field id is defined

                    $fieldtype_id = $this->cleanQuotedValue(substr($raw_pred_val, 0, $colon_pos));
                    $value = $this->cleanQuotedValue(substr($raw_pred_val, $colon_pos+1));

                    if (($colon_pos = strpos($value, '='))===0) {$this->exact = true;}
                    elseif (($colon_pos = strpos($value, '<'))===0) {$this->lessthan = true;}
                        elseif (($colon_pos = strpos($value, '>'))===0) {$this->greaterthan = true;}
                            elseif (($colon_pos = strpos($value, '@'))===0) {$this->fulltext = true;}
                    if($colon_pos===0){
                        $value = substr($value,1);
                    }

                    return new FieldPredicate($this, $fieldtype_id, $value);
                }

            case 'linkedfrom':
            case 'linkto':
                return new LinkedFromParentPredicate($this, $pred_val);
            case 'linked_to':
            case 'linkedto':
                return new LinkedToParentPredicate($this, $pred_val);
            case 'relatedfrom': //related from given record type + relation type
                return new RelatedFromParentPredicate($this, $pred_val);
            case 'related_to':  //related to given record type + relation type
                return new RelatedToParentPredicate($this, $pred_val);
            case 'related':
                return new RelatedPredicate($this, $pred_val);
            case 'links':
                return new AllLinksPredicate($this, $pred_val);
/* 2016-02-29
            case 'linkto':    // linkto:XXX matches records that have a recDetails reference to XXX
                return new LinkToPredicate($this, $pred_val);
            case 'linkedto':    // linkedto:XXX matches records that are referenced in one of XXX's bib_details
                return new LinkedToPredicate($this, $pred_val);
*/
            case 'relatedto':    // relatedto:XXX matches records that are related (via a type-1 record) to XXX
                return new RelatedToPredicate($this, $pred_val);
            case 'relationsfor':    // relatedto:XXX matches records that are related (via a type-1 record) to XXX, and the relationships themselves
                return new RelationsForPredicate($this, $pred_val);

            case 'after':
            case 'since':
                return new AfterPredicate($this, $pred_val);

            case 'before':
                return new BeforePredicate($this, $pred_val);

            case 'date':
            case 'modified':
                return new DateModifiedPredicate($this, $pred_val);

            case 'added':
                return new DateAddedPredicate($this, $pred_val);

            case 'workgroup':
            case 'wg':
            case 'owner':
                return new WorkgroupPredicate($this, $pred_val);

            case 'geo':
                return new SpatialPredicate($this, $pred_val);

            case 'latitude':
            case 'lat':
                return new CoordinatePredicate($this, $pred_val, 'ST_Y');

            case 'longitude':
            case 'long':
            case 'lng':
                return new CoordinatePredicate($this, $pred_val, 'ST_X');

            case 'hhash':
                return new HHashPredicate($this, $pred_val);
            default:
                return new TitlePredicate($this, $pred_val);
        }

        // 2024-08-02
        /*
        // no predicate-type specified ... look at search type specification
        if ($sortType == 'key') {    // "default" search should be on tag
            return new TagPredicate($this, $pred_val);
        } elseif($sortType == 'all') {
            return new AnyPredicate($this, $pred_val);
        } else {
            return new TitlePredicate($this, $pred_val);
        }
        */

    }


    /**
     * Cleans a string value by removing leading/trailing double quotes and normalizing internal spaces.
     *
     * - If the value starts and ends with a double quote (`"`), these quotes are stripped.
     * - If the value only starts with a double quote, that quote is stripped.
     * - After quote stripping (if any), multiple consecutive internal spaces are collapsed into single spaces.
     * - The string is also trimmed of leading/trailing whitespace that might result from quote stripping.
     *
     * This is a utility method used by `createPredicate` when extracting values.
     *
     * @param string $val The input string value.
     * @return string The cleaned and normalized string value.
     */
    private function cleanQuotedValue($val) {
        if (strlen($val)>0 && $val[0] == '"') {
            if ($val[strlen($val)-1] == '"'){
                $val = substr($val, 1, -1);
            }else{
                $val = substr($val, 1);
            }
            return preg_replace('/ +/', ' ', trim($val));
        }

        return $val;
    }
}

//
//
//
/**
 * Represents a single sort criterion (e.g., "sortby:title", "sortby:-f:23") extracted from the query.
 */
class SortPhrase {
    /** @var string The raw sort phrase string (e.g., "sortby:title", "sortby:-f:23"). */
    public $value;

    /** @var Query Reference to the parent Query object. */
    public $parent;

    /**
     * Constructor for SortPhrase.
     *
     * @param Query $parent Reference to the parent Query object.
     * @param string $value The raw sort phrase string.
     */
    public function __construct(&$parent, $value) {
        $this->parent = &$parent;

        $this->value = $value;
    }

    /**
     * Generates the SQL components for this sort phrase.
     *
     * Parses the `value` to determine the field, direction (ASC/DESC),
     * and any specific handling for field types (e.g., resource, date, numeric).
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return array An array containing three elements:
     *               0: (string|null) The SQL expression for the ORDER BY clause (e.g., "rec_Title ASC", "CAST(dtl_Value AS DECIMAL) DESC").
     *                  Null if the sort phrase is invalid or leads to no sort.
     *               1: (string|null) A signature for this sort criterion (e.g., "rec_Title", "dtl_DetailTypeID=23").
     *                  Used to prevent duplicate sort clauses. Null if no valid signature.
     *               2: (string|null) Any additional SQL table join string required for this sort
     *                  (e.g., "LEFT JOIN recDetails bd1 ON ..."). Null if no extra tables needed.
     */
    public function makeSQL() {
        global $mysqli;

        $colon_pos = strpos($this->value, ':');
        $text = substr($this->value, $colon_pos+1);

        $colon_pos = strpos($text, ':');
        if ($colon_pos === false) {$subtext = $text;}
        else {$subtext = substr($text, 0, $colon_pos);}

        // if sortby: is followed by a -, we sort DESCENDING; if it's a + or nothing, it's ASCENDING
        $scending = '';
        if ($subtext[0] == '-') {
            $scending = ' desc ';
            $subtext = substr($subtext, 1);
            $text = substr($text, 1);
        } elseif($subtext[0] == '+') {
            $subtext = substr($subtext, 1);
            $text = substr($text, 1);
        }

        switch (strtolower($subtext)) {
            case 'set': case 'fixed': //sort as defined in ids predicate
                if($this->parent->fixed_sortorder){
                    return array("FIND_IN_SET(TOPBIBLIO.rec_ID, '{$this->parent->fixed_sortorder}')", 'rec_ID', null);
                }else{
                    return array(null, null, null);
                }

            case 'p': case 'popularity':
                return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', null);

            case 'r': case 'rating':
                if ($this->parent->search_domain == BOOKMARK) {
                    return array('-(bkm_Rating)'.$scending, 'bkmk_rating', null);//SAW Ratings Change todo: test queries with rating
                } else {    // default to popularity sort
                    return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', null);
                }

            case 'interest':    //todo: change help file to reflect depricated predicates
            case 'content':
            case 'quality':
                return array('rec_Title'.$scending, null);// default to title sort
                break;

            case 'u': case 'url':
                return array('rec_URL'.$scending, 'rec_URL', null);

            case 'm': case 'modified':
                if ($this->parent->search_domain == BOOKMARK) {return array('bkm_Modified'.$scending, null);}
                else {return array('rec_Modified'.$scending, 'rec_Modified', null);}

            case 'a': case 'added':
                if ($this->parent->search_domain == BOOKMARK) {return array('bkm_Added'.$scending, null);}
                else {return array('rec_Added'.$scending, 'rec_Added', null);}

            case 'f': case 'field':
                /* Sort by field is complicated.
                * Unless the "multiple" flag is set, then if there are multiple values for a particular field for a particular record,
                * then we can only sort by one of them.  We choose a representative value: this is the lex-lowest of all the values,
                * UNLESS it is field 158 (creator), in which case the order of the authors is important, and we choose the one with the lowest dtl_ID
                */
                $CREATOR = (defined('DT_CREATOR')?DT_CREATOR:'0');

                if (preg_match('/^(?:f|field):(\\d+)(:m)?/i', $text, $matches)) {
                    @list($_, $field_id, $show_multiples) = $matches;
                    $res = $mysqli->query("select dty_Type from defDetailTypes where dty_ID = $field_id");
                    $baseType = $res->fetch_row();
                    $baseType = @$baseType[0];

                    if ($show_multiples) {    // "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
                        $bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
                        return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
                            "left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and dtl_DetailTypeID=$field_id ");
                    } elseif($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as unsigned)".$scending,"dtl_Value is integer",
                            "left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=$field_id ");
                    } elseif($baseType == "float"){//sort field is an numeric so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as decimal)".$scending,"dtl_Value is decimal",
                            "left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=$field_id ");
                    } else {
                        // have to introduce a defDetailTypes join to ensure that we only use the linked resource's
                        // title if this is in fact a resource (record pointer)) type (previously any integer, e.g. a date, could potentially
                        // index another records record)
                        return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                            "if(dty_Type='date',getEstDate(dtl_Value,0),dtl_Value)) ".
                            "from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records link on dtl_Value=link.rec_ID ".
                            "where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=$field_id ".
                            "order by if($field_id=$CREATOR, dtl_ID, link.rec_Title) limit 1), '~~') ".$scending,
                            "dtl_DetailTypeID=$field_id", null);
                    }
                } elseif (preg_match('/^(?:f|field):"?([^":]+)"?(:m)?/i', $text, $matches)) {
                    @list($_, $field_name, $show_multiples) = $matches;
                    $res = $mysqli->query("select dty_ID, dty_Type from defDetailTypes where dty_Name = '$field_name'");
                    $baseType = $res->fetch_row();
                    $field_id = @$baseType[0];
                    $baseType = @$baseType[1];

                    if ($show_multiples) {    // "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
                        $bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
                        return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
                            "left join defDetailTypes bdt$bd_name on bdt$bd_name.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and $bd_name.dtl_DetailTypeID=bdt$bd_name.dty_ID ");
                    } elseif($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as decimal)".$scending,"dtl_Value is decimal",
                            "left join defDetailTypes bdtInt on bdtInt.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
                    } elseif($baseType == "float"){//sort field is an numeric so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as unsigned)".$scending,"dtl_Value is integer",
                            "left join defDetailTypes bdtInt on bdtInt.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
                    } else {
                        return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                            "if(dty_Type='date',getEstDate(dtl_Value,0),dtl_Value)) ".
                            "from defDetailTypes, recDetails left join Records link on dtl_Value=link.rec_ID ".
                            "where dty_Name='".$mysqli->real_escape_string($field_name)."' and dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=dty_ID ".
                            "order by if(dty_ID=$CREATOR,dtl_ID,link.rec_Title) limit 1), '~~') ".$scending,
                            "dtl_DetailTypeID=$field_id", null);
                    }
                }

            case 't': case 'title':
                return array('rec_Title'.$scending, null);
            case 'id': case 'ids':
                return array('rec_ID'.$scending, null);
            case 'rt': case 'type':
                return array('rec_RecTypeID'.$scending, null);
            default;
        }
    }
}


/**
 * Base class for all predicate types (e.g., TitlePredicate, FieldPredicate).
 * A predicate represents a single condition in the WHERE clause of a query.
 */
class Predicate {
    /** @var string The value associated with the predicate (e.g., the search term for a title, the ID for a type). */
    public $value;

    /** @var AndLimb Reference to the parent AndLimb object that contains this predicate. */
    public $parent;

    /** 
     * @var bool Flag used by some predicate types (like RelatedFromParentPredicate, RelatedToParentPredicate) 
     * to control whether to recursively find inverse relationships. Defaults to true.
     */
    public $need_recursion = true;

    /** @var Query|null Cached reference to the top-level Query object, obtained via `getQuery()`. */
    public $query;

    /**
     * Constructor for Predicate.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value The value for this predicate.
     */
    public function __construct(&$parent, $value) {
        $this->parent = &$parent;

        $this->value = $value;
        $this->query = null; // Initialize query cache
    }

    /**
     * Generates the SQL WHERE clause condition for this predicate.
     * This is a placeholder in the base class and should be overridden by subclasses.
     *
     * @param string|null $table_name Optional table name/alias to prefix field names, not consistently used by all subclasses.
     * @return string Default implementation returns '1' (a neutral condition). Subclasses return specific SQL.
     */
    public function makeSQL() { return '1';}


    /**
     * Sets the `$need_recursion` flag to false.
     * Used by relationship predicates to prevent infinite recursion when finding inverse relationships.
     * @return void
     */
    public function stopRecursion() {
       $this->need_recursion = false;
    }

    /**
     * Gets a reference to the top-most parent `Query` object.
     *
     * It traverses up the parent chain (Predicate -> AndLimb -> OrLimb -> Query)
     * to find the Query object. The result is cached in `$this->query` for subsequent calls.
     *
     * @return Query Reference to the top-level Query object.
     */
    public function &getQuery() {
        if (! $this->query) {
            $c = &$this->parent; // Starts from AndLimb

            // Loop up to top-most parent "Query"
            // AndLimb parent is OrLimb, OrLimb parent is Query
            while ($c  &&  strtolower(get_class($c)) != 'query') {
                if (property_exists($c, 'parent')) {
                    $c = &$c->parent;
                } else {
                    // Should not happen in a well-formed structure
                    // Trigger error or handle as appropriate if $c has no parent but is not Query
                    break; 
                }
            }
            $this->query = ($c && strtolower(get_class($c)) == 'query') ? $c : null;
        }
        return $this->query;
    }

    /**
     * Checks if the predicate's value represents a date or a date range.
     *
     * It attempts to parse `$this->value` as a single date or a date range
     * separated by "<>". It returns true if the value (or both parts of the range)
     * can be successfully parsed into DateTime objects.
     *
     * @return bool True if the value is recognized as a date or date range, false otherwise.
     */
    public function isDateTime() {

        $timestamp0 = null;
        $timestamp1 = null;
        if (strpos($this->value,"<>")>0) {
            $vals = explode("<>", $this->value);

             try{
                $timestamp0 = new DateTime($vals[0]);
                $timestamp1 = new DateTime($vals[1]);
             } catch (Exception  $e){
                 return false; // Parsing failed
             }
        }else{
             try{
                $timestamp0 = new DateTime($this->value);
                $timestamp1 = 1; // Indicates single date was successfully parsed
             } catch (Exception  $e){
                 return false; // Parsing failed
             }
        }
        return $timestamp0  &&  $timestamp1; // Both parts must be valid if range, or single date must be valid
    }

    /**
     * Generates an SQL date condition string (older version).
     * Likely deprecated in favor of `makeDateClause` which uses `recDetailsDateIndex`.
     *
     * This method parses `$this->value` for a single date or a date range ("<>").
     * It uses `Temporal::dateToISO()` for conversion and applies operators
     * (`=`, `<`, `>`, `LIKE`) based on the parent AndLimb's flags (`exact`, `lessthan`, `greaterthan`).
     *
     * @return string The SQL condition string for the date.
     */
    public function makeDateClause_old() {

        if (strpos($this->value,"<>")) {

            $vals = explode("<>", $this->value);
            $datestamp0 = Temporal::dateToISO($vals[0]);
            $datestamp1 = Temporal::dateToISO($vals[1]);

            return "between '$datestamp0'".SQL_AND."'$datestamp1'";

        }else{

            $datestamp = Temporal::dateToISO($this->value);

            if ($this->parent->exact) {
                return "= '$datestamp'";
            }
            elseif($this->parent->lessthan) {
                return "< '$datestamp'";
            }
            elseif($this->parent->greaterthan) {
                return "> '$datestamp'";
            }
            else {
                // Default to LIKE matching the beginning of the date string
                return "like '$datestamp%'";

                //old way
                /*
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                $match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];
                }
                elseif (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = date('Y-m', $timestamp);
                }
                else {
                    $date = date('Y-m-d', $timestamp);
                }
                return "like '$date%'";
                */
            }
        }
    }

    /**
     * Generates an SQL condition string for date fields, comparing against the `recDetailsDateIndex` table.
     *
     * This method handles date queries for detail fields, focusing on ranges and specific comparisons
     * using pre-calculated min/max estimated dates from `recDetailsDateIndex` (rdi_estMinDate, rdi_estMaxDate).
     *
     * - Parses `$this->value` which can be a single date or a range string (e.g., "YYYY/YYYY", "YYYY<>YYYY").
     *   Uses the `Temporal` class to convert these into min/max timestamps.
     * - Applies comparison logic based on parent AndLimb's flags (`exact`, `lessthan`, `greaterthan`):
     *   - Exact (`exact`): Checks if the search timespan is entirely within the database range.
     *     `(rdi_estMinDate <= search_min AND search_max <= rdi_estMaxDate)`
     *   - Less than (`lessthan`): Checks if the database range's end is before the search timespan's start.
     *     `(search_max < rdi_estMinDate)` (Note: original code logic was `{$timespan[1]} < rdi_estMinDate`, which means search_end < db_start)
     *   - Greater than (`greaterthan`): Checks if the database range's start is after the search timespan's end.
     *     `(rdi_estMaxDate < search_min)` (Note: original code logic was `rdi_estMaxDate < {$timespan[0]}`, which means db_end < search_start)
     *   - Default (overlap/intersects): Checks if the database range overlaps with the search timespan.
     *     `(rdi_estMaxDate >= search_min AND rdi_estMinDate <= search_max)`
     *
     * @return string|null The SQL condition string for the date field comparison against `recDetailsDateIndex`,
     *                     or null if date parsing fails.
     */
    public function makeDateClause() {

        // if($this->isEmptyValue()){ return SQL_NULL; } 

        if (strpos($this->value,"<>")) { // Value represents a range
            $vals = explode("<>", $this->value);
            $temporal1 = new Temporal($vals[0]);
            $temporal2 = new Temporal($vals[1]);

            if(!$temporal1->isValid() || !$temporal2->isValid()){
                return null; // Invalid date format in range
            }

            $timespan1_minmax = $temporal1->getMinMax();
            $min_search_time = $timespan1_minmax[0];

            $timespan2_minmax = $temporal2->getMinMax();
            $max_search_time = $timespan2_minmax[1];

            $timespan = array($min_search_time, $max_search_time);

        }else{ // Single date value
            $temporal = new Temporal($this->value);
            if(!$temporal->isValid()){
                return null; // Invalid single date format
            }
            $timespan = $temporal->getMinMax(); // For a single date, min and max of timespan will be start/end of that day/month/year
        }

        $res = '';

        if ($this->parent->exact) {
            // Checks if the database timespan (rdi_estMinDate to rdi_estMaxDate)
            // is entirely contained within the search timespan.
            $res = "(rdi_estMinDate <= {$timespan[0]} AND {$timespan[1]} <= rdi_estMaxDate)";
        }
        elseif($this->parent->lessthan) {
            // Checks if the end of the search timespan is less than the start of the database timespan.
            // (i.e., search period is entirely before database period)
            $res = "({$timespan[1]} < rdi_estMinDate)";
        }
        elseif($this->parent->greaterthan) {
            // Checks if the start of the search timespan is greater than the end of the database timespan.
            // (i.e., search period is entirely after database period)
            $res = "(rdi_estMaxDate < {$timespan[0]})";
        }
        else { // Default case: Overlaps/intersects
            // Checks if the database timespan overlaps with the search timespan.
            // (db_end >= search_start AND db_start <= search_end)
            $res = "(rdi_estMaxDate>={$timespan[0]} AND rdi_estMinDate<={$timespan[1]})";
        }

        // Negation is not explicitly handled here but would typically be applied by the caller if $this->parent->negate is true.
        return $res;
    }


    /**
     * Retrieves the inverse term ID for a given relationship type.
     *
     * @param int $relation_type_ID The relationship type ID.
     * @return int|null The inverse term ID, or null if not found.
     */
    protected function getInverseTermId($relation_type_ID) {
        global $mysqli;
        $res = $mysqli->query("SELECT trm_InverseTermID FROM defTerms WHERE trm_ID = " . intval($relation_type_ID));
        if ($res) {
            $inverseTermId = $res->fetch_row();
            return $inverseTermId[0] ?? null;
        }
        return null;
    }
}

/**
 * Predicate for searching by record title (`rec_Title`).
 */
class TitlePredicate extends Predicate {

    /**
     * Generates SQL for matching the record title.
     * Handles exact match, less than, greater than, and LIKE comparisons.
     * Supports negation. Allows specifying if it's for the top-level record.
     *
     * @param bool $isTopRec If true, prefixes field with "TOPBIBLIO.". Default true.
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL($isTopRec=true) {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';
        // $query = &$this->getQuery(); // Original comment said "not used"

        $evalue = $mysqli->real_escape_string($this->value);
        $topbiblioPrefix = $isTopRec ? "TOPBIBLIO." : "";

        if ($this->parent->exact){
            return $not . $topbiblioPrefix.'rec_Title = "'.$evalue.'"';
        }elseif($this->parent->lessthan){
            return $not . $topbiblioPrefix.'rec_Title < "'.$evalue.'"';
        }elseif($this->parent->greaterthan){
                return $not . $topbiblioPrefix.'rec_Title > "'.$evalue.'"';
        }elseif(strpos($this->value,"%")===false){ // If no wildcards in value, add them for LIKE
                return $topbiblioPrefix."rec_Title $not like '%$evalue%'";
        }else{ // Value already contains wildcards
                return $topbiblioPrefix."rec_Title $not like '$evalue'";
        }
    }
}

/**
 * Predicate for searching by record type (`rec_RecTypeID`).
 */
class TypePredicate extends Predicate {

    /**
     * Generates SQL for matching the record type ID.
     * Handles numeric ID, comma-separated list of IDs, or type name.
     * Supports negation. Allows specifying if it's for the top-level record.
     *
     * @param bool $isTopRec If true, prefixes field with "TOPBIBLIO.". Default true.
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL($isTopRec=true) {
        global $mysqli;

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            $res = "rec_RecTypeID $eq ".intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            // comma-separated list of defRecTypes ids
            $in = ($this->parent->negate)? 'not in' : 'in';
            $res = "rec_RecTypeID $in (" . $this->value . ")";
        }
        else {
            $name = $mysqli->real_escape_string($this->value);
            $res = "rec_RecTypeID $eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '$name' limit 1)";
        }

        if($isTopRec){
            $res = "TOPBIBLIO.".$res;
        }
        return $res;
    }
}

/**
 * Predicate for searching by record URL (`rec_URL`).
 */
class URLPredicate extends Predicate {

    /**
     * Generates SQL for matching the record URL using LIKE.
     * Supports negation.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';
        // $query = &$this->getQuery();
        $val = $mysqli->real_escape_string($this->value);
        return "TOPBIBLIO.rec_URL $not like '%$val%'";
    }
}

/**
 * Predicate for searching by record notes/scratchpad (`rec_ScratchPad`).
 */
class NotesPredicate extends Predicate {

    /**
     * Generates SQL for matching the record scratchpad using LIKE.
     * Supports negation. Returns empty string if search domain is BOOKMARK.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string, or empty string.
     */
    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';
        $query = &$this->getQuery();
        if ($query->search_domain == BOOKMARK){
            // Scratchpad search is not applicable to bookmarks domain in this implementation
            return '';
        }else{
            $val = $mysqli->real_escape_string($this->value);
            return "TOPBIBLIO.rec_ScratchPad $not like '%$val%'";
        }
    }
}

/**
 * Predicate for searching records bookmarked by a specific user.
 */
class UserPredicate extends Predicate {

    /**
     * Generates SQL for finding records bookmarked by a user.
     * User can be specified by ID, comma-separated IDs, username, or full name.
     * Supports negation.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string using an EXISTS subquery on `usrBookmarks` and `sysUGrps`.
     */
    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';
        if (is_numeric($this->value)) {
            return '('.$not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
            . ' and bkmk.bkm_UGrpID = ' . intval($this->value) . '))';
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            return '('.$not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
            . ' and bkmk.bkm_UGrpID in (' . $this->value . ')))';
        }
        elseif (preg_match('/^(\D+)\s+(\D+)$/', $this->value,$matches)){    // saw MODIFIED: 16/11/2010 since Realname field was removed.
            return '('.$not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
            . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
            . ' and (usr.ugr_FirstName = "' . $mysqli->real_escape_string($matches[1])
            . '" and usr.ugr_LastName = "' . $mysqli->real_escape_string($matches[2]) . '")))';
        }
        else {
            return '('.$not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
            . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
            . ' and usr.ugr_Name = "' . $mysqli->real_escape_string($this->value) . '"))';
        }
    }
}

/**
 * Predicate for searching by the user who added the record (`rec_AddedByUGrpID`).
 */
class AddedByPredicate extends Predicate {

    /**
     * Generates SQL for matching the `rec_AddedByUGrpID`.
     * User can be specified by ID, comma-separated IDs, or username.
     * Supports negation.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            return "TOPBIBLIO.rec_AddedByUGrpID $eq " . intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            $not = ($this->parent->negate)? "not" : "";
            return "TOPBIBLIO.rec_AddedByUGrpID $not in (" . $this->value . ")";
        }
        else {
            $not = ($this->parent->negate)? "not" : "";
            return "TOPBIBLIO.rec_AddedByUGrpID $not in (select usr.ugr_ID from sysUGrps usr where usr.ugr_Name = '"
            . $mysqli->real_escape_string($this->value) . "')";
        }
    }
}

/**
 * Predicate for searching across multiple fields (record title, detail values, linked resource titles).
 * This acts as a general-purpose "any field" search.
 */
class AnyPredicate extends Predicate {

    /**
     * Generates SQL for matching the value across various fields.
     * If `fulltext` flag is set on parent, attempts a full-text search on `dtl_Value` and `rec_Title` of linked resources,
     * falling back to LIKE for `rec_Title`.
     * Otherwise, uses LIKE against `dtl_Value` (or term label/code for enums), linked resource `rec_Title`, and `TOPBIBLIO.rec_Title`.
     * Supports negation.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $val = $mysqli->real_escape_string($this->value);

        if($this->parent->fulltext){
               // Attempt full-text search on detail values and titles of linked resources.
               $res_details_ft = 'select dtl_RecID from recDetails '
                . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
                . ' left join Records link on dtl_Value=link.rec_ID ' // Join to get linked record titles
                .' where if(dty_Type="resource", '
                    .'link.rec_Title like "%'.$val.'%", ' // Fallback to LIKE for linked resource titles
                    .' MATCH(dtl_Value) AGAINST("'.$val.'"))'; // Full-text on dtl_Value

                $list_ids = mysql__select_list2($mysqli, $res_details_ft);
                $condition_details = '';
                if($list_ids && !empty($list_ids)){
                    $condition_details = predicateId('TOPBIBLIO.rec_ID',$list_ids);
                }

                // Full-text on main record title (if supported, otherwise LIKE)
                // Assuming rec_Title might not always have FT index or might be handled differently
                $condition_title = 'TOPBIBLIO.rec_Title like "%'.$val.'%"'; 

                if($condition_details && $condition_title){
                    return "($condition_details OR $condition_title)";
                } elseif ($condition_details){
                    return "($condition_details)";
                } elseif ($condition_title){
                    return "($condition_title)";
                }
                return '(1=0)'; // Nothing to search
        }

        // Standard LIKE search if not full-text
        $not = ($this->parent->negate)? SQL_NOT : '';
        return $not . ' (exists (select rd.dtl_ID from recDetails rd '
        . 'left join defDetailTypes on rd.dtl_DetailTypeID=dty_ID '
        . 'left join Records link on rd.dtl_Value=link.rec_ID ' // Join for linked resource titles
        . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
        . '  and if(dty_Type != "resource", ' // If not a resource field
        . 'if(dty_Type="enum", dtl_Value in (select trm_ID from defTerms where trm_Label like "%'.$val.'%" or trm_Code="'.$val.'"), rd.dtl_Value like "%'.$val.'%"), ' // Search enum by label/code or other detail by value
        .'link.rec_Title like "%'.$val.'%"))' // Search linked resource title
        .' or TOPBIBLIO.rec_Title like "%'.$val.'%") '; // Also search main record title
    }
}

/**
 * Predicate for searching by a specific detail field value.
 * Handles nested queries for resource fields (where the field value points to another record,
 * and that linked record is then queried), various comparison types, and different field data types
 * like resource, enum, date, file, or freetext.
 */
class FieldPredicate extends Predicate {
    /** @var string|int The ID or name of the detail field type (dty_ID or dty_Name). */
    public $field_type;
    /** @var string|null The actual data type of the field (e.g., 'resource', 'enum', 'date'), fetched from `defDetailTypes`. */
    public $field_type_value;
    /** @var array|null If the query involves searching within linked resources (nested query), this holds the parsed structure of that nested query. Each element is an array of `AndLimb` objects. */
    public $nests = null;

    /**
     * Constructor for FieldPredicate.
     *
     * Parses the field type and value. If the value indicates a nested query
     * (e.g., "f:field_id:(t:type_id title:some_title)"), it parses this nested
     * structure into `$this->nests`.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string|int $type The ID (numeric) or name (string) of the detail field type.
     * @param string $value The value to search for in the field, or a nested query string.
     */
    public function __construct(&$parent, $type, $value) {
        $this->field_type = $type;
        parent::__construct($parent, $value);

        if (strlen($value)>0 && $value[0] == '-') {    // DWIM: user wants a negate, allow it here
            $parent->negate = true;
            $this->value = substr($value, 1); // Update value after processing negation
        }

        // Check for nested queries within parentheses, e.g., f:pointer_field:(t:OtherRecordType title:Something)
        // The regex captures up to two levels of parentheses, assuming a simple structure.
        preg_match('/\((.+?)(?:\((.+)\))?\)/', $this->value, $matches);
        if(!empty($matches) && $matches[0]==$this->value){ // If the entire value is a parenthesized expression
            $this->nests = array();
            for ($k=1; $k < count($matches);++$k) { // Iterate through captured groups (nested parts)
                if (empty($matches[$k])) continue;
                $text = $matches[$k];

                // Split the nested query part into AND limbs (space-separated tokens, respecting quotes)
                if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches2)) {
                    $and_texts = $matches2[0];
                    $limbs = array();
                    for ($i=0; $i < count($and_texts);++$i){
                        // Note: Passing $this (FieldPredicate) as parent to AndLimb here.
                        // This might be unusual if AndLimb expects an OrLimb, review context if issues arise.
                        // However, AndLimb constructor takes generic parent and accesses parent->absoluteStrQuery.
                        // FieldPredicate doesn't have absoluteStrQuery, so this could lead to notices.
                        // For now, documenting as is. A proper fix might involve a more specific parsing context for nests.
                        $limbs[] = new AndLimb($this, $and_texts[$i]);
                    }
                    array_push($this->nests, $limbs);
                }
            }
        }
    }

    /**
     * Generates the SQL WHERE condition for this field predicate.
     *
     * This is a complex method that handles:
     * - Nested queries (searching fields on linked records).
     * - Different field types: 'resource', 'enum', 'relationtype', 'date', 'file', 'fulltext', and others.
     * - Various comparison operators derived from parent AndLimb's flags or specific value parsing.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string. Can be quite complex, often involving EXISTS subqueries.
     */
    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';

        $and_link = ' and link';
        $sql_detail_exists = 'exists (select rd.dtl_ID from recDetails rd ';
        $sql_recdetail_link = ' where rd.dtl_RecID=TOPBIBLIO.rec_ID ';
        $sql_and_detailtype = ' and rd.dtl_DetailTypeID=';

        if($this->nests){  //special case nested query for resources

            $field_value = '';
            $nest_joins = '';
            $relation_second_level = '';
            $relation_second_level_where = '';
            $use_new_version = true;

            if( $use_new_version ){ //new test version

                $isrelmarker_0 = ($this->field_type=="relmarker");
                $isrelmarker_1 = false;

                for ($i=0; $i < count($this->nests);++$i) {

                    $limbs = $this->nests[$i];
                    $type_clause = null;
                    $field_type = null;

                    for ($j=0; $j < count($limbs);++$j) {
                        $cn = get_class($limbs[$j]->pred);

                        if($cn == 'TypePredicate'){
                            $type_clause = $limbs[$j]->pred->makeSQL(false);// rec_RecTypeID in (12,14)
                        }elseif($cn == 'FieldPredicate'){
                            if($i==0 && $limbs[$j]->pred->field_type=="relmarker"){ //allowed for i==0 only
                                $isrelmarker_1 = true;

                                $relation_second_level = ', recLinks rel1';
                                if($isrelmarker_0){
                                    $relation_second_level_where = ' and (rel1.rl_RelationID is not null)'
                                    .' and ((rel1.rl_TargetID=rel0.rl_SourceID and rel1.rl_SourceID=link1.rec_ID) '
                                    .'or (rel1.rl_SourceID=rel0.rl_TargetID and rel1.rl_TargetID=link1.rec_ID))';
                                }else{
                                    $relation_second_level_where =
                                    ' and (rel1.rl_RelationID is not null)'
                                    .' and ((rel1.rl_TargetID=rel0.rl_SourceID and rel1.rl_SourceID=rd.dtl_Value) '
                                    .'or (rel1.rl_SourceID=rel0.rl_TargetID and rel1.rl_TargetID=rd.dtl_Value))';
                                }

                            }else{

                                $field_type = $limbs[$j]->pred->get_field_type_clause();
                                if(strpos($field_type,"like")!==false){
                                    $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $field_type limit 1)";
                                }
                                if($limbs[$j]->pred->value){
                                    $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value();
                                }

                            }
                        }elseif($cn == 'TitlePredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'DateModifiedPredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL();
                            $field_value = str_replace("TOPBIBLIO.","",$field_value);
                        }
                    }//for predicates

                    if($type_clause){ //record type clause is mandatory

                        $nest_joins .= ' left join Records link'.$i.' on link'.$i.'.'.$type_clause;
                        if($i==0){
                            if(!$isrelmarker_0){
                                $nest_joins .= ' and rd.dtl_Value=link0.rec_ID ';
                            }
                        }elseif(!$isrelmarker_1){
                                $nest_joins .= ' and linkdt0.dtl_Value=link1.rec_ID ';

                        }

                        //$nest_joins .= SQL_AND.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID ';//STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0

                        if($field_type){
                            $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                        }

                    } else {
                        return '';//fail - record type is mandatory for nested queries
                    }
                }//for nests

                if($isrelmarker_0){

                    $resq = '('.$not . 'exists (select rel0.rl_TargetID, rel0.rl_SourceID from recLinks rel0 '.$relation_second_level
                    .$nest_joins
                    .' where (rel0.rl_RelationID is not null) and ((rel0.rl_TargetID=TOPBIBLIO.rec_ID and rel0.rl_SourceID=link0.rec_ID)'
                    .' or (rel0.rl_SourceID=TOPBIBLIO.rec_ID and rel0.rl_TargetID=link0.rec_ID)) '
                    .$relation_second_level_where
                    .$field_value.'))';

                }else{

                    $rd_type_clause = '';
                    $rd_type_clause = $this->get_field_type_clause();
                    if(strpos($rd_type_clause,"like")===false){
                        $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
                    }else{
                        $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $rd_type_clause limit 1)";
                    }

                    $resq = '('.$not . $sql_detail_exists.$relation_second_level
                    .$nest_joins
                    . $sql_recdetail_link . $relation_second_level_where . $field_value . $rd_type_clause.'))';
                }

            }else{  //working copy!!!!

                for ($i=0; $i < count($this->nests);++$i) {

                    $limbs = $this->nests[$i];
                    $type_clause = null;
                    $field_type = null;

                    for ($j=0; $j < count($limbs);++$j) {
                        $cn = get_class($limbs[$j]->pred);

                        if($cn == 'TypePredicate'){
                            $type_clause = $limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'FieldPredicate'){
                            $field_type = $limbs[$j]->pred->get_field_type_clause();
                            if(strpos($field_type,"like")!==false){
                                $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $field_type limit 1)";
                            }
                            if($limbs[$j]->pred->value){
                                $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value();
                            }
                        }elseif($cn == 'TitlePredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'DateModifiedPredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL();
                            $field_value = str_replace("TOPBIBLIO.","",$field_value);
                        }
                    }//for predicates

                    if($type_clause){ //record type clause is mandatory     STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0
                        $nest_joins .= ' left join Records link'.$i.' on '.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID and link'.$i.'.'.$type_clause;
                        if($field_type){
                            $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                        }
                    } else {
                        return '';//fail - record type is mandatory for nested queries
                    }
                }//for nests

                $rd_type_clause = '';
                $rd_type_clause = $this->get_field_type_clause();
                if(strpos($rd_type_clause,"like")===false){
                    $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
                }else{
                    $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $rd_type_clause limit 1)";
                }

                $resq = '('.$not . $sql_detail_exists
                .$nest_joins
                . $sql_recdetail_link. $field_value . $rd_type_clause.'))';

            }

            return $resq;
        } //end special case nested query for resources

        if (preg_match('/^\\d+$/', $this->field_type)) {
            $dt_query = "select rdt.dty_Type from defDetailTypes rdt where rdt.dty_ID = ".intval($this->field_type);
            $this->field_type_value = mysql__select_value($mysqli, $dt_query);
        }else{
            $this->field_type_value ='';
        }

        $match_pred = $this->get_field_value();

        $isnumericvalue = false;
        $isin = false;
        if($this->field_type_value!='date'){

            $cs_ids = getCommaSepIds($this->value);

            if ($cs_ids) {
                $isnumericvalue = false;
                $isin = true;
            }else{
                $isin = false;
                $isnumericvalue = is_numeric($this->value);
            }
        }

        /*
        if($isin){
            $match_pred_for_term = $match_pred;
        }elseif($isnumericvalue){
            $match_pred_for_term = $match_pred; //" = $match_value";
        }else{
            $match_pred_for_term = " = trm.trm_ID";
        }*/

        $timestamp = $isin?false:true; //numeric values $this->isDateTime();

        if($this->field_type_value=='resource'){ //field type is found - search for specific detailtype
            return '('.$not . $sql_detail_exists
            . ' left join Records link on rd.dtl_Value=link.rec_ID '
            . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type).SQL_AND
            . ($isnumericvalue ? 'rd.dtl_Value ':' link.rec_Title ').$match_pred . '))';

        }elseif($this->field_type_value=='enum' || $this->field_type_value=='relationtype'){

            return '('.$not . $sql_detail_exists
            //. (($isnumericvalue || $isin)?'':'left join defTerms trm on trm.trm_Label '. $match_pred )
            . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type)
            . " and rd.dtl_Value $match_pred))";

        }elseif($this->field_type_value=='date'){


            $res = '('.$not.'EXISTS (SELECT rdi_DetailID FROM recDetailsDateIndex WHERE TOPBIBLIO.rec_ID=rdi_RecID AND '
                    .'rdi_DetailTypeID='. intval($this->field_type);

            $dateindex_clause = $this->makeDateClause();
            if($dateindex_clause){
                $res = $res.SQL_AND.$dateindex_clause.'))';
            }else{
                $res = '';
            }

            return $res;

        }elseif($this->field_type_value){

            if($this->field_type_value=='file'){
                $fieldname = 'rd.dtl_UploadedFileID';

                if(!($isnumericvalue || $isin)){
                    $q = 'exists (select rd.dtl_ID from recDetails rd, recUploadedFiles rf '
                    . $sql_recdetail_link . ' and rf.ulf_ID=rd.dtl_UploadedFileID'
                    . $sql_and_detailtype . intval($this->field_type)
                    . ' and (rf.ulf_OrigFileName ' . $match_pred. ' or rf.ulf_MimeExt '. $match_pred.'))';

                    if($not){
                        $q = '('.$not . $q . ')';
                    }
                    return $q;
                }
            }elseif($this->parent->fulltext){

                $res = 'select dtl_RecID from recDetails where '
                .' dtl_DetailTypeID='.intval($this->field_type)
                .' AND MATCH(dtl_Value) AGAINST("'.$mysqli->real_escape_string($this->value).'")';

                $list_ids = mysql__select_list2($mysqli, $res);

                $res = predicateId('TOPBIBLIO.rec_ID',$list_ids);

                return $res;

            }else{
                $fieldname = 'rd.dtl_Value';
            }

            return '('.$not . $sql_detail_exists
                . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type)
                . SQL_AND . $fieldname . ' ' . $match_pred. '))';


        }else{

            $rd_type_clause = $this->get_field_type_clause();
            if(strpos($rd_type_clause,"like")===false){ //several field type
                $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
            }else{
                if($rd_type_clause=='like "%"'){ //any field type
                    $rd_type_clause = '';
                }else{
                    $rd_type_clause = " and rdt.dty_Name ".$rd_type_clause;
                }
            }

            if($this->parent->fulltext){


                $res = 'select dtl_RecID from recDetails '
                . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
                . ' left join Records link on dtl_Value=link.rec_ID '
                .' where if(dty_Type="resource", '
                    .'link.rec_Title ' . $match_pred . ', '
                    .' MATCH(dtl_Value) AGAINST("'.$mysqli->real_escape_string($this->value).'"))';

                $list_ids = mysql__select_list2($mysqli, $res);

                $res = predicateId('TOPBIBLIO.rec_ID',$list_ids);

                return $res;
            }


            $dateindex_clause = $this->makeDateClause();

            return '('.$not . $sql_detail_exists
            . 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID '
            . 'left join Records link on rd.dtl_Value=link.rec_ID '
            .' left join recDetailsDateIndex on rd.dtl_ID=rdi_DetailID '
//. (($isnumericvalue || $isin)?'':'left join defTerms trm on trm.trm_Label '. $match_pred ). " "
            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
            . ' and if(rdt.dty_Type = "resource" AND '.($isnumericvalue?'0':'1').', '
            .'link.rec_Title ' . $match_pred . ', '     //THEN
//see 1377            .'if(rdt.dty_Type in ("enum","relationtype"), rd.dtl_Value '.$match_pred_for_term.', '
            . ($dateindex_clause!=null
                ? 'if(rdt.dty_Type = "date", (rdi_DetailTypeID=rd.dtl_DetailTypeID AND '.$dateindex_clause.') , '
                . "rd.dtl_Value $match_pred)"
                : "rd.dtl_Value $match_pred"
              ) . ')'
            . $rd_type_clause . '))';
        }

    }

    /**
     * Generates the SQL clause for the detail field type ID or name.
     *
     * If `$this->field_type` is numeric, it's treated as a dty_ID.
     * If it's a comma-separated list of numbers, it's used for an IN clause.
     * Otherwise, it's treated as a dty_Name and used in a LIKE clause.
     * If the value is empty, it generates `!=''` which is likely not intended and
     * should ideally mean "any field type" if that's a valid use case.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition part for the field type.
     */
    public function get_field_type_clause(){
        global $mysqli;

        if(trim($this->value)===''){ // If the predicate's *value* is empty
            // This condition seems problematic. It implies if the search value for the field is empty,
            // the type clause becomes `!=''` which means "any type that is not an empty string".
            // This might be a fallback or an attempt to match any field if no specific type is being filtered by name/ID.
            // However, $this->field_type refers to the *type of field* being queried, not its search value.
            // A more logical interpretation might be that if $this->field_type itself is empty/unspecified,
            // then it should match any field type, but this is not what the code does.
            // Documenting current behavior.
            $rd_type_clause = "!=''";

        }elseif (preg_match('/^\\d+$/', $this->field_type)) {
            /* Handle the case where user has specified a (single) specific numeric type ID */
            $rd_type_clause = '= ' . intval($this->field_type);
        }
        elseif (preg_match(REGEX_CSV, $this->field_type)) {
            /* User has specified a list of numeric type IDs ... match any of them */
            $rd_type_clause = 'in (' . $this->field_type . ')';
        }
        else {
            /* User has specified the field name (string) */
            $val = $mysqli->real_escape_string($this->field_type);
            $rd_type_clause = 'like "' . $val . '%"'; // Matches dty_Name starting with the given string
        }
        return  $rd_type_clause;
    }

    /**
     * Generates the SQL comparison part for the field's value.
     *
     * This method determines the SQL operator and formats the value based on:
     * - The field's actual data type (`$this->field_type_value` e.g., 'enum', 'date', 'integer').
     * - Parent AndLimb flags (`exact`, `lessthan`, `greaterthan`).
     * - Special value formats (e.g., comma-separated IDs, range operator "<>").
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL comparison string (e.g., "= 'some_value'", "LIKE '%text%'", "IN (1,2,3)").
     */
    public function get_field_value(){
        global $mysqli;

        if(trim($this->value)==='' || $this->value===false){   // If value is not defined, find any non-empty value
            $match_pred = " !='' ";
        } elseif($this->field_type_value=='enum' || $this->field_type_value=='relationtype'){
            // Handle enum or relationtype fields
            if(preg_match(REGEX_CSV, $this->value)){ // Comma-separated numeric IDs
                $match_pred = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))'; // Includes children
            }elseif(intval($this->value)>0){ // Single numeric ID
                $match_pred = ' in (select trm_ID from defTerms where trm_ID='
                    .$this->value.' or trm_ParentTermID='.$this->value.')'; // Includes children
            }else{ // Textual value - search label or code
                $value = $mysqli->real_escape_string($this->value);
                $match_pred  = ' in (select trm_ID from defTerms where trm_Label ';
                if($this->parent->exact){
                    $match_pred  .= '="'.$value.'"';
                } else {
                    $match_pred  .= " like '%$value%'";
                }
                $match_pred  .= ' or trm_Code="'.$value.'")'; // Also check against term code
            }
        } elseif (strpos($this->value,"<>")>0) {  // Numeric range (e.g., "10<>20")
            $vals = explode("<>", $this->value);
            // Ensure values are numeric before using in BETWEEN for safety
            $val1 = is_numeric($vals[0]) ? $vals[0] : '0';
            $val2 = is_numeric($vals[1]) ? $vals[1] : '0';
            $match_pred = SQL_BETWEEN.$val1.SQL_AND.$val2.' ';
        } else { // Other types or generic text search
            $cs_ids = null;
            // Avoid treating numeric values for float/integer fields as comma-separated ID lists
            if(!($this->field_type_value=='float' || $this->field_type_value=='integer')){
                $cs_ids = getCommaSepIds($this->value);
            }

            if ($cs_ids) { // Comma-separated list of IDs
                $match_pred = ' in ('.$cs_ids.')';
            } else { // Single value, potentially numeric or text
                $isnumericvalue = is_numeric($this->value);
                $match_value_sql = '';

                if($isnumericvalue && $this->value!==''){
                    $match_value_sql = floatval($this->value); // Use raw number for numeric types
                }else{
                    $match_value_sql = '"'.$mysqli->real_escape_string($this->value).'"'; // Quote string values
                }

                if ($this->parent->exact  ||  $this->value === "") {    // SC100: xxx:"" treated as exact match for empty string
                    $match_pred = ' = '.$match_value_sql;
                } elseif($this->parent->lessthan) {
                    $match_pred = " < $match_value_sql";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $match_value_sql";
                } else { // Default to LIKE for strings, or exact for numbers if not otherwise specified
                    if(($this->field_type_value=='float' || $this->field_type_value=='integer') && $isnumericvalue){
                        // For numeric types, if no other operator, treat as exact match.
                        // Using direct numeric comparison:
                        $match_pred = ' = '.floatval($this->value);
                    } elseif(strpos($this->value,"%")===false){ // If no wildcards in value, add them for LIKE
                        $match_pred = " like '%".$mysqli->real_escape_string($this->value)."%'";
                    } else { // Value already contains wildcards
                        $match_pred = " like '".$mysqli->real_escape_string($this->value)."'";
                    }
                }
            }
        }
        return $match_pred;
    }
}

/**
 * Predicate for searching by the count of a specific detail field.
 */
class FieldCountPredicate extends Predicate {
    /** @var string|int The ID or name of the detail field type whose instances are to be counted. */
    public $field_type;

    /**
     * Constructor for FieldCountPredicate.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string|int $type The ID or name of the detail field type.
     * @param string $value The count value to compare against (e.g., "=5", ">2").
     */
    public function __construct(&$parent, $type, $value) {
        $this->field_type = $type;
        parent::__construct($parent, $value);

        // DWIM: Allow negation prefix on the value itself.
        if ($value[0] == '-') {
            $parent->negate = true;
            $this->value = substr($value, 1); // Update value after processing negation
        }
    }

    /**
     * Generates SQL for comparing the count of a field's occurrences.
     *
     * Constructs a subquery `(select count(rd.dtl_ID) from recDetails rd ...)`
     * and compares its result with the value specified, using operators
     * derived from parent AndLimb flags or parsed from the value.
     *
     * @global \mysqli $mysqli The global mysqli database connection (unused directly, but assumed by context).
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? '(not ' : '';
        $not2 = ($this->parent->negate)? ') ' : '';

        $match_pred = $this->get_field_value();

        $ft_compare = '';
        if($this->field_type>0){
            $ft_compare = 'and rd.dtl_DetailTypeID='.intval($this->field_type);
        }

        return $not . '(select count(rd.dtl_ID) from recDetails rd left join Records link on rd.dtl_Value=link.rec_ID
where rd.dtl_RecID=TOPBIBLIO.rec_ID '.$ft_compare.' )'.$match_pred . $not2;
    }

    //
    public function get_field_value(){
        global $mysqli;

        if (strpos($this->value,"<>")>0) {  //(preg_match('/^\d+(\.\d*)?|\.\d+(?:<>\d+(\.\d*)?|\.\d+)+$/', $this->value)) {

            $vals = explode("<>", $this->value);
            $match_pred = SQL_BETWEEN.$vals[0].SQL_AND.$vals[1].' ';

        }else {

                if(!is_numeric($this->value)){
                    $match_value = 0;
                }else{
                    $match_value = intval($this->value);
                }

                if ($this->parent->lessthan) {
                    $match_pred = " < $match_value";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $match_value";
                } else {
                    $match_pred = ' = '.$match_value;
                }
        }

        return $match_pred;
    }

}

/**
 * Predicate for searching records by tags.
 * Handles multiple tags (OR logic), workgroup-specific tags, and numeric tag IDs.
 */
class TagPredicate extends Predicate {
    /** @var array<string> Array of workgroup names associated with tags. Parallel to `$this->value`. Empty string if no workgroup. */
    public $wg_value;

    /**
     * Constructor for TagPredicate.
     *
     * Parses the input value, which can be a comma-separated list of tags.
     * Tags can also be workgroup-specific using a backslash separator (e.g., "wg_name\\tag_name").
     * Populates `$this->value` (tag names or IDs) and `$this->wg_value` (workgroup names).
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value Comma-separated string of tags, potentially with workgroup prefixes.
     */
    public function __construct(&$parent, $value) {
        $this->parent = &$parent; // Direct assignment, not by reference as in original & $parent.

        $this->value = array();    // Stores tag texts or IDs
        $this->wg_value = array(); // Stores corresponding workgroup names for tags
        $values = explode(',', $value);
        $any_wg_values = false;

        // DWIM (Do What I Mean): If the tag string contains commas, split into multiple tags for an OR search.
        for ($i=0; $i < count($values);++$i) {
            if (strpos($values[$i], '\\') === false) { // Standard tag
                array_push($this->value, trim($values[$i]));
                array_push($this->wg_value, ''); // No specific workgroup
            } else {    // Workgroup-specific tag (e.g., "workgroup_name\\tag_text")
                preg_match('/(.*?)\\\\(.*)/', $values[$i], $matches);
                array_push($this->wg_value, trim($matches[1])); // Workgroup name
                array_push($this->value, trim($matches[2]));    // Tag text
                $any_wg_values = true;
            }
        }
        if (! $any_wg_values) {$this->wg_value = array();} // If no wg values were found, ensure it's an empty array.
        $this->query = null; // Initialize query cache from base Predicate class.
    }

    /**
     * Generates the SQL WHERE clause part for a single tag or a list of tags (ORed).
     * This private helper method was part of the original TagPredicate but seems to have been
     * largely integrated or superseded by the logic within makeSQL itself.
     * For documentation, assuming it was intended to build the core "(tag_Text = 'X' OR tag_ID=Y) AND (ugr_Name='Z' OR ugr_ID IS NULL)" part.
     *
     * Note: This method is marked private and its return `null` suggests it might be incomplete or its logic
     * was merged into `makeSQL`. The PHPDoc describes its apparent original intent.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string|null The SQL condition string for the tag expressions, or null if logic was removed/merged.
     */
    private function tagWhereExp(){
        global $mysqli;
/*
        $query = '';

        $sql_tag_eq = 'kwd.tag_Text ="';
        $sql_tag_like = 'kwd.tag_Text like "';

        for ($i=0; $i < count($this->value);++$i) {
                if ($i > 0) {$query .= 'or ';}

                $value = $this->value[$i];
                $wg_value = $this->wg_value[$i];

                $sql_tags = ($this->parent->exact? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                        : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');

                if ($wg_value) {
                    $query .= '( '. $sql_tags .' and ugr_Name = "'.$mysqli->real_escape_string($wg_value).'") ';

                } elseif (is_numeric($value)) {
                    $query .= "kwd.tag_ID=$value ";
                } else {
                    $query .= '( '.$sql_tags;

                    $pquery = &$this->getQuery();
                    if($pquery->search_domain != BOOKMARK){
                        $query .= ' and ugr_ID is null ';
                    }
                    $query .= ') ';
                }
        }
*/
        return null;        
    }

    /**
     * Generates SQL for matching records based on tags.
     *
     * Constructs an EXISTS subquery against `usrRecTagLinks` and `usrTags` (and `sysUGrps` if workgroup tags are involved).
     * Handles multiple tags with OR logic. Supports negation.
     * Differentiates logic based on whether the search is within BOOKMARK domain (searches user's tags)
     * or general domain (searches global/non-user-specific tags).
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $sql_where = 'where kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
        $sql_tag_eq = 'kwd.tag_Text ="';
        $sql_tag_like = 'kwd.tag_Text like "';

        $pquery = &$this->getQuery();
        $not = ($this->parent->negate)? SQL_NOT : '';
        if ($pquery->search_domain == BOOKMARK) {
            if (is_numeric(join('', $this->value))) {    // if all tag specs are numeric then don't need a join
                return '('.$not . 'exists (select * from usrRecTagLinks where rtl_RecID=bkm_RecID and rtl_TagID in ('.join(',', $this->value).')))';
            } elseif (! $this->wg_value) {
                // this runs faster (like TEN TIMES FASTER) - think it's to do with the join
                $query='('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . $sql_where;
                $first_value = true;
                foreach ($this->value as $value) {
                    if (! $first_value) {$query .= 'or ';}
                    if (is_numeric($value)) {
                        $query .= 'rtl_TagID='.intval($value).' ';
                    } else {
                        $query .=  ($this->parent->exact
                            ? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                            : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');
                    }
                    $first_value = false;
                }
                $query .= ') and kwd.tag_UGrpID='.$pquery->currUserID.')) ';
            } else {
                $query='('.$not . 'exists (select * from sysUGrps, usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . ' where ugr_ID=tag_UGrpID and kwi.rtl_RecID=TOPBIBLIO.rec_ID and ('
                . tagWhereExp(). ')))';
            }
        } else {
            if (! $this->wg_value) {
                $query = '('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . $sql_where;
                $first_value = true;
                foreach ($this->value as $value) {
                    if (! $first_value) {$query .= 'or ';}
                    if (is_numeric($value)) {
                        $query .= "kwd.tag_ID=$value ";
                    } else {
                        $query .=      ($this->parent->exact? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                            : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');
                    }
                    $first_value = false;
                }
                $query .= '))) ';
            } else {
                $query = '('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID left join sysUGrps on ugr_ID=tag_UGrpID '
                . $sql_where
                . tagWhereExp(). '))) ';

            }
        }

        return $query;
    }
}

/**
 * Predicate for searching by record ID (`rec_ID`).
 * Handles single ID, comma-separated IDs, ranges, and record ID replacements.
 */
class BibIDPredicate extends Predicate {

    /**
     * Generates SQL for matching the `rec_ID`.
     * It calls `get_field_value()` to get the appropriate comparison string.
     *
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        $res = "TOPBIBLIO.rec_ID ".$this->get_field_value();
        return $res;
    }

    /**
     * Generates the SQL comparison part for the record ID value.
     *
     * - Handles ranges specified with "<>".
     * - Handles comma-separated lists of IDs (for IN clauses).
     * - Handles single IDs with operators `<`, `>`, or `=` (default).
     * - Uses `recordSearchReplacement()` to potentially map old IDs to new IDs.
     * - Sets `$pquery->fixed_sortorder` if a list of IDs is provided, for `sortby:set`.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL comparison string (e.g., "= 123", "IN (1,2,3)", "BETWEEN 10 AND 20").
     */
    public function get_field_value(){
        global $mysqli;

        if (strpos($this->value,"<>")>0) { // Range
            $vals = explode("<>", $this->value);
            // Apply replacement for each part of the range
            $vals[0] = recordSearchReplacement($mysqli, trim($vals[0]));
            $vals[1] = recordSearchReplacement($mysqli, trim($vals[1]));
            $match_pred = SQL_BETWEEN.intval($vals[0]).SQL_AND.intval($vals[1]).' ';
        } else {
            $cs_ids = getCommaSepIds($this->value); // Check for comma-separated IDs
            if ($cs_ids && strpos($cs_ids,',')>0) { // List of IDs
                $pquery = &$this->getQuery();
                // Apply replacement to each ID in the list
                // The condition `true || $pquery->search_domain == EVERYTHING` always evaluates to true,
                // meaning replacements are always attempted.
                if(true || $pquery->search_domain == EVERYTHING){
                    $id_array = explode(',', $cs_ids);
                    $replaced_ids = array();
                    foreach($id_array as $recid){
                        array_push($replaced_ids, recordSearchReplacement($mysqli, trim($recid)));
                    }
                    $cs_ids = implode(',', array_map('intval', $replaced_ids)); // Ensure IDs are integers
                }

                $not = ($this->parent->negate)? ' NOT' : ''; // Corrected: Add space before NOT
                $match_pred = $not.' IN ('.$cs_ids.')';

                if(!$this->parent->negate) { // Only set fixed_sortorder if not negated
                    $pquery->fixed_sortorder = $cs_ids; // For sortby:set
                }
            } else { // Single ID
                $this->value = recordSearchReplacement($mysqli, $this->value);
                $value = intval($this->value); // Ensure it's an integer

                if ($this->parent->lessthan) {
                    $match_pred = " < $value";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $value";
                } else { // Default to exact match or not equal
                    if($this->parent->negate){
                        $match_pred = ' <> '.$value;
                    }else{
                        $match_pred = '='.$value;
                    }
                }
            }
        }
        return $match_pred;
    }
}

/**
 * Abstract base class for predicates that deal with linked records (via `recLinks` table).
 * Provides common structure but `makeSQL` needs to be implemented by subclasses.
 */
abstract class LinkedPredicate extends Predicate {

    /** @var string SQL field name for the source ID in `recLinks` (e.g., "rl.rl_SourceID"). Set by subclasses. */
    protected $fromField;
    /** @var string SQL field name for the target ID in `recLinks` (e.g., "rl.rl_TargetID"). Set by subclasses. */
    protected $toField;
    /** @var string SQL join condition to link `recLinks` with `Records` (e.g., "rl.rl_TargetID=rd.rec_ID"). Set by subclasses. */
    protected $toRLink;

    /**
     * Constructs an SQL query for finding records that are linked to or from records matching a parent query,
     * or linked to/from specific record types/detail types.
     *
     * The behavior depends on whether a parent query context (`$pquery->parentquery`) exists:
     * - **With Parent Query**: Finds records (identified by `$this->toField`) that are linked to/from
     *   records (`rd`) matching the parent query. The link is further filtered by `$this->fromField`
     *   matching `rd.rec_ID` (via `$this->toRLink`) and optionally by `$rty_ID` and `$dty_ID` from `$this->value`.
     * - **Without Parent Query (Standalone)**: Finds records (identified by `$this->toField`) that are linked to/from
     *   records specified by `$rty_ID` (via `$this->fromField`) and `$dty_ID`.
     *
     * The `$this->value` (e.g., "recordTypeID-detailTypeID") is parsed to get `$rty_ID` and `$dty_ID`
     * which specify constraints on the *other* side of the link.
     *
     * Subclasses (`LinkedFromParentPredicate`, `LinkedToParentPredicate`) define the direction
     * of the link by setting `$fromField`, `$toField`, and `$toRLink`.
     *
     * @return string The constructed SQL query string, typically an `IN (SELECT ...)` clause.
     */
    public function makeSQL() {

        $rty_ID = null;
        $dty_ID = null;
        //if value is specified we search linked from specific source type and field
        if($this->value){
            $vals = explode('-', $this->value);
            $rty_ID = @$vals[0] ?? null;
            $dty_ID = @$vals[1] ?? '';
        }

        // Prepare record and detail type IDs for SQL
        $rty_IDs = prepareIds($rty_ID);
        $dty_IDs = prepareIds($dty_ID);

        // Initialize the additional WHERE clause for the SQL query
        $add_where = '';

        if($rty_ID==1){ //special case for relationship records
            $add_where = "rd.rec_RecTypeID=$rty_ID and rl.rl_RelationID=rd.rec_ID ";
        }else{

            $add_where = $add_where . SQL_RL_SOURCE_LINK
                . predicateId('rd.rec_RecTypeID', $rty_IDs, SQL_AND) // Add predicate for record type ID if available
                . SQL_AND;

            if(!empty($dty_IDs)){
                $add_where .= predicateId('rl.rl_DetailTypeID', $dty_IDs);
            }else{
                $add_where .= SQL_RELATION_IS_NULL;
            }
        }

        $add_from  = SQL_RECLINK;

        $select = 'TOPBIBLIO.rec_ID in (select '.$this->toField.' ';

        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $query = $pquery->parentquery;

            // Adjust FROM and WHERE clauses for parent query
            $query["from"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["from"]);
            $query["where"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["where"]);


            // Construct the full SQL query using the parent query
            $select = $select.$query["from"].', '.$add_from.SQL_WHERE.$query["where"]
                        .SQL_AND.$add_where
                        .' '.$query["sort"].$query["limit"].$query["offset"].')';

        }else{

            $add_where = predicateId($this->fromField,$rty_IDs, SQL_AND);

            $add_where = $this->toRLink . $add_where;
            if($rty_ID!=1){
                $add_where .= SQL_AND;

                if(!empty($dty_IDs)){
                    $add_where = predicateId('rl.rl_DetailTypeID',$dty_IDs, SQL_AND);
                }else{
                    $add_where = $add_where.SQL_RELATION_IS_NULL;
                }
            }

            // Final SELECT clause for standalone query
            $select = $select.SQL_RECORDS.','.$add_from.SQL_WHERE.$add_where.')';
        }

        return $select;
    }
}

/**
 * Predicate for finding records that are targets of links originating from records matching a parent query
 * or specific criteria.
 *
 * Example: Find "Records B" where "Records A" (matching parent query or criteria) have a pointer field linking to "Records B".
 * `$this->value` can specify `recordTypeID-detailTypeID` of "Records A".
 * - `fromField` becomes `rl.rl_SourceID` (Record A is the source of the link).
 * - `toField` becomes `rl.rl_TargetID` (Record B is the target we are looking for).
 * - `toRLink` defines how Record A (as `rd`) is joined via its `rec_ID` to `rl.rl_TargetID` if parentquery is used,
 *   or how `rl_TargetID` (Record B) is related to `rd` (Record A) in standalone. (This seems reversed, check logic of makeSQL carefully)
 *   Actually, `SQL_RL_TARGET_LINK` is `rl.rl_TargetID=rd.rec_ID`.
 */
class LinkedFromParentPredicate extends LinkedPredicate {
    /**
     * Constructor for LinkedFromParentPredicate.
     * Sets the direction of the link search.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value Value string, potentially "rty_ID-dty_ID" for the source of the link.
     */
    public function __construct(&$parent, $value) {
        parent::__construct( $parent, $value );

        $this->fromField = 'rl.rl_SourceID'; // The record matching parent/criteria is the source of the link
        $this->toField = 'rl.rl_TargetID';   // We are looking for the target of this link

        // This defines how the 'other' side of the link (rd) is connected in the subquery.
        // If parent query context: rd is the record from parent query. We want rd.rec_ID to be rl.rl_TargetID.
        // If standalone: rd is the 'other' record. We want rd.rec_ID to be rl.rl_TargetID.
        $this->toRLink = SQL_RL_TARGET_LINK; // `rl.rl_TargetID=rd.rec_ID`
    }
}

/**
 * Predicate for finding records that are sources of links pointing to records matching a parent query
 * or specific criteria.
 *
 * Example: Find "Records A" where "Records A" have a pointer field linking to "Records B" (matching parent query or criteria).
 * `$this->value` can specify `recordTypeID-detailTypeID` of "Records B".
 * - `fromField` becomes `rl.rl_TargetID` (Record B is the target of the link).
 * - `toField` becomes `rl.rl_SourceID` (Record A is the source we are looking for).
 * - `toRLink` defines how Record B (as `rd`) is joined via its `rec_ID` to `rl.rl_SourceID`.
 *   Actually, `SQL_RL_SOURCE_LINK` is `rl.rl_SourceID=rd.rec_ID`.
 */
class LinkedToParentPredicate extends LinkedPredicate {
    /**
     * Constructor for LinkedToParentPredicate.
     * Sets the direction of the link search.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value Value string, potentially "rty_ID-dty_ID" for the target of the link.
     */
    public function __construct(&$parent, $value) {
        parent::__construct( $parent, $value );

        $this->fromField = 'rl.rl_TargetID'; // The record matching parent/criteria is the target of the link
        $this->toField = 'rl.rl_SourceID';   // We are looking for the source of this link

        // This defines how the 'other' side of the link (rd) is connected in the subquery.
        $this->toRLink = SQL_RL_SOURCE_LINK; // `rl.rl_SourceID=rd.rec_ID`
    }
}

/**
 * Abstract base class for predicates dealing with related records (via type 1 records in `recLinks`).
 * Provides helper methods `buildWhereClause` and `buildSelectClause`.
 */
abstract class RelatedParentPredicate extends Predicate {

    /**
     * Constructs a partial WHERE clause for relationship queries.
     *
     * This helper method is used by subclasses to build a WHERE clause segment that filters by:
     * - Source record type ID (`$source_rty_ID`), if provided.
     * - A specific SQL link type condition (`$linkType`), e.g., `rl.rl_SourceID=rd.rec_ID`.
     * - A specific relation type ID (`$relation_type_ID`), if provided. If not, it defaults to ensuring
     *   that a relation exists (`rl.rl_RelationID IS NOT NULL` which is `SQL_RELATION_IS_NOT_NULL`).
     *
     * @param int|string|null $source_rty_ID The record type ID of the source/other side of the relationship.
     * @param int|string|null $relation_type_ID The term ID of the specific relationship type.
     * @param string $linkType The SQL condition string that defines how `rd` (record from one side)
     *                         is linked to `rl` (recLinks table), e.g., `SQL_RL_SOURCE_LINK`.
     * @return string The constructed partial WHERE clause string.
     */
    protected function buildWhereClause($source_rty_ID, $relation_type_ID, $linkType) {
        $where = '';
        if ($source_rty_ID) {
            // Ensure $source_rty_ID is safe if it's a direct value or handle if it's a list
            $where .= "rd.rec_RecTypeID = " . intval($source_rty_ID) . SQL_AND;
        }
        $where .= $linkType . SQL_AND;
        if ($relation_type_ID) {
            $where .= "rl.rl_RelationTypeID = " . intval($relation_type_ID);
        } else {
            $where .= SQL_RELATION_IS_NOT_NULL; // Ensures it's a relationship link
        }
        return $where;
    }

    /**
     * Builds the main part of a subquery (FROM and WHERE clauses) for relationship predicates.
     *
     * This helper is used by subclasses to construct the core of an `IN (SELECT ...)` subquery.
     * - If a parent query context exists (`$pquery->parentquery`), it adapts the parent query's FROM/WHERE
     *   clauses by aliasing 'TOPBIBLIO' to 'rd' (representing the records from the parent query context)
     *   and 'TOPBKMK' to 'MAINBKMK'. It then appends the `$add_from` (typically 'recLinks rl') and
     *   `$add_where` (conditions generated by `buildWhereClause`) to these adapted parent clauses.
     *   The parent query's sort, limit, and offset are also appended, though their effect inside an
     *   `IN` subquery is usually limited.
     * - If no parent query context, it constructs a simpler `FROM Records rd, {$add_from} WHERE {$add_where}`.
     *
     * @param string $add_from Additional FROM clause elements, typically 'recLinks rl'.
     * @param string $add_where Additional WHERE clause conditions, typically from `buildWhereClause`.
     * @return string The constructed FROM, WHERE, and potentially ORDER BY, LIMIT, OFFSET parts of a subquery.
     */
    protected function buildSelectClause($add_from, $add_where) {
        $pquery = &$this->getQuery();
        if ($pquery->parentquery) { // If there's a parent query context
            $query = $pquery->parentquery; // Get parent query's SQL parts
            $query["from"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["from"]);
            $query["where"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["where"]);

            return $query["from"] . ', ' . $add_from . SQL_WHERE . $query["where"] . SQL_AND . $add_where .
                ' ' . $query["sort"] . $query["limit"] . $query["offset"] . ')';
        } else {
            return SQL_RECORDS . ',' . $add_from . SQL_WHERE . $add_where . ')';
        }
    }

}

/**
 * Predicate for finding records that are targets of relationships originating from
 * records defined by a parent query or specific criteria.
 *
 * Example: Find "Records B" where "Records A" (matching parent query/criteria) are related to "Records B"
 * with a specific relationship type.
 * `$this->value` (e.g., "sourceRecordTypeID-relationTypeID") specifies constraints on "Records A" and the relation.
 * This class looks for `rl_TargetID` in `recLinks` where `rl_SourceID` matches the context.
 */
class RelatedFromParentPredicate extends RelatedParentPredicate {

    /**
     * Creates the SQL query for fetching records that are targets of specified relationships.
     *
     * It identifies records (`TOPBIBLIO.rec_ID`) that are `rl_TargetID` in `recLinks`.
     * The other side of the relationship (`rl_SourceID`, aliased as `rd`) is constrained by
     * `$this->value` (source record type and relation type) and potentially a parent query context.
     * If `$this->need_recursion` is true and a relation type is specified, it also includes
     * records found via the inverse relationship type (using `RelatedToParentPredicate`).
     *
     * @global \mysqli $mysqli (Potentially used by `getInverseTermId` if called, though not directly in this method body).
     * @return string SQL query string, typically an `IN (SELECT ...)` clause, possibly combined with an OR for inverse relations.
     */
    public function makeSQL() {
        global $mysqli; // $mysqli is used by getInverseTermId

        $select_relto_inverse = null; // SQL for inverse relationship
        $source_rty_ID = null;        // Record Type ID of the source of the relationship
        $relation_type_ID = null;     // Term ID of the relationship type

        // Parse the value (e.g., "sourceRtyID-relationTypeID")
        if ($this->value) {
            $vals = explode('-', $this->value);
            $source_rty_ID = $vals[0] ?? null;
            $relation_type_ID = !empty($vals[1]) ? $vals[1] : null;

            // If recursion is needed and a relation type is specified, find its inverse
            if ($this->need_recursion && $relation_type_ID) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
                if ($inverseTermId) {
                    // Construct a predicate for the inverse relation
                    // This will find records where TOPBIBLIO is the source and 'rd' is the target via inverse relation
                    $relto_inverse_pred = new RelatedToParentPredicate($this->parent, $source_rty_ID . '-' . $inverseTermId);
                    $relto_inverse_pred->stopRecursion(); // Prevent infinite recursion
                    $select_relto_inverse = $relto_inverse_pred->makeSQL();
                }
            }
        }

        $add_from = SQL_RECLINK; // " recLinks rl "
        // Define how 'rd' (source of relation) links to 'rl' (recLinks table)
        $add_where = $this->buildWhereClause($source_rty_ID, $relation_type_ID, SQL_RL_SOURCE_LINK);

        // We are looking for records that are the TARGET of these relationships
        $select = 'TOPBIBLIO.rec_ID IN (SELECT rl.rl_TargetID ';
        $select .= $this->buildSelectClause($add_from, $add_where);

        // If an inverse relationship search was also constructed, combine with OR
        if ($select_relto_inverse !== null) {
            $select = '(' . $select . ') OR (' . $select_relto_inverse . ')';
        }

        return $select;
    }

}

/**
 * Predicate for finding records that are sources of relationships pointing to
 * records defined by a parent query or specific criteria.
 *
 * Example: Find "Records A" where "Records B" (matching parent query/criteria) are related to "Records A"
 * with a specific relationship type.
 * `$this->value` (e.g., "targetRecordTypeID-relationTypeID") specifies constraints on "Records B" and the relation.
 * This class looks for `rl_SourceID` in `recLinks` where `rl_TargetID` matches the context.
 */
class RelatedToParentPredicate extends RelatedParentPredicate {

    /**
     * Creates the SQL query for fetching records that are sources of specified relationships.
     *
     * It identifies records (`TOPBIBLIO.rec_ID`) that are `rl_SourceID` in `recLinks`.
     * The other side of the relationship (`rl_TargetID`, aliased as `rd`) is constrained by
     * `$this->value` (target record type and relation type) and potentially a parent query context.
     * If `$this->need_recursion` is true and a relation type is specified, it also includes
     * records found via the inverse relationship type (using `RelatedFromParentPredicate`).
     *
     * @global \mysqli $mysqli (Potentially used by `getInverseTermId` if called).
     * @return string SQL query string, typically an `IN (SELECT ...)` clause, possibly combined with an OR for inverse relations.
     */
    public function makeSQL() {
        global $mysqli; // $mysqli is used by getInverseTermId

        $select_relfrom_inverse = null; // SQL for inverse relationship
        $target_rty_ID = null;          // Record Type ID of the target of the relationship
        $relation_type_ID = null;       // Term ID of the relationship type

        // Parse the value (e.g., "targetRtyID-relationTypeID")
        if ($this->value) {
            $vals = explode('-', $this->value);
            $target_rty_ID = $vals[0] ?? null;
            $relation_type_ID = !empty($vals[1]) ? $vals[1] : null;

            // If recursion is needed and a relation type is specified, find its inverse
            if ($this->need_recursion && $relation_type_ID) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
                if ($inverseTermId) {
                    // Construct a predicate for the inverse relation
                    // This will find records where TOPBIBLIO is the target and 'rd' is the source via inverse relation
                    $relfrom_inverse_pred = new RelatedFromParentPredicate($this->parent, $target_rty_ID . '-' . $inverseTermId);
                    $relfrom_inverse_pred->stopRecursion(); // Prevent infinite recursion
                    $select_relfrom_inverse = $relfrom_inverse_pred->makeSQL();
                }
            }
        }

        $add_from = SQL_RECLINK; // " recLinks rl "
        // Define how 'rd' (target of relation) links to 'rl' (recLinks table)
        $add_where = $this->buildWhereClause($target_rty_ID, $relation_type_ID, SQL_RL_TARGET_LINK);

        // We are looking for records that are the SOURCE of these relationships
        $select = 'TOPBIBLIO.rec_ID IN (SELECT rl.rl_SourceID ';
        $select .= $this->buildSelectClause($add_from, $add_where);

        // If an inverse relationship search was also constructed, combine with OR
        if ($select_relfrom_inverse !== null) {
            $select = '(' . $select . ') OR (' . $select_relfrom_inverse . ')';
        }

        return $select;
    }
}

/**
 * Predicate for finding records that are related (in either direction) to records
 * matching a parent query or specific criteria.
 *
 * This predicate searches for relationships in both directions:
 * - Records that are sources of a relationship where the target matches the context.
 * - Records that are targets of a relationship where the source matches the context.
 * The context can be a parent query or specific record type/relation type defined in `$this->value`.
 */
class RelatedPredicate extends Predicate {

    /**
     * Creates the SQL query for fetching records related in both directions.
     *
     * Parses `$this->value` (e.g., "relatedRecordTypeID-relationTypeID") to constrain the "other side"
     * of the relationship.
     * If a parent query context exists, it constructs a complex query joining with the parent query results (`rd`).
     * If standalone, it forms an EXISTS subquery checking `recLinks`.
     * It also considers inverse relationship types if `relation_type_ID` is provided.
     *
     * @global \mysqli $mysqli (Potentially used by `getInverseTermId` if called).
     * @return string|false SQL query string, or false if `related_rty_ID` is not found.
     */
    public function makeSQL() {
        global $mysqli;

        $related_rty_ID = null;
        $inverseTermId = 0;
        $relation_type_ID = 0;

        // Parse the provided value to get related record type and relation type.
        if ($this->value) {
            $vals = explode('-', $this->value);
            $related_rty_ID = $vals[0] ?? null;
            $relation_type_ID = $vals[1] ?? 0;

            // Find inverse relationship term if needed.
            if ($relation_type_ID > 0) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
            }
        }

        // Return false if no related record type is found.
        if (!$related_rty_ID) {
            return false;
        }

        //NEW  ---------------------------
        $add_from  = SQL_RECLINK;
        $add_where = '';
        if($relation_type_ID>0){
            $add_where = $add_where.'(';
            if($inverseTermId>0){
                $add_where = $add_where."(rl.rl_RelationTypeID=$inverseTermId) OR ";
            }
            $add_where = $add_where."(rl.rl_RelationTypeID=$relation_type_ID))";
        }else{
            $add_where = $add_where. SQL_RELATION_IS_NOT_NULL;
        }

        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $add_where = "(rd.rec_RecTypeID=$related_rty_ID) and ".$add_where;

            $query = $pquery->parentquery;

            $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
            $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
            $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
            $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);

            $select = '(TOPBIBLIO.rec_ID in (select rl.rl_SourceID '.$query["from"].',recLinks rl '
                      .SQL_WHERE.$query["where"].SQL_AND.$add_where.' and ('.SQL_RL_TARGET_LINK.'))) OR '
                      .'(TOPBIBLIO.rec_ID in (select rl.rl_TargetID '.$query["from"].',recLinks rl '
                      .SQL_WHERE.$query["where"].SQL_AND.$add_where.' and ('.SQL_RL_SOURCE_LINK.')))';

        }else{

            $add_where = "(TOPBIBLIO.rec_RecTypeID=$related_rty_ID) and ".$add_where;

            return '(EXISTS (SELECT rl.rl_ID FROM '.SQL_RECLINK.SQL_WHERE .
                    '(rl.rl_TargetID=TOPBIBLIO.rec_ID OR rl.rl_SourceID=TOPBIBLIO.rec_ID) AND '
                    .$add_where . '))';
        }


        return $select;
    }
}


/**
 * Predicate for finding records that have any link (resource pointer or relationship)
 * to or from records matching a parent query or specific criteria.
 * It effectively combines "linked to/from" and "related to/from" in a broad sense.
 */
class AllLinksPredicate  extends Predicate {
    /**
     * Generates SQL to find records that have any type of link (resource or relationship)
     * to or from records defined by `$this->value` (as a record type ID or list of IDs)
     * or by a parent query context.
     *
     * The method constructs two main subqueries, one for links where `TOPBIBLIO.rec_ID` is a source (`rl1.rl_SourceID`)
     * and one where it's a target (`rl2.rl_TargetID`). These are ORed together.
     *
     * - If a parent query context exists (`$pquery->parentquery`):
     *   The subqueries join against the parent query's results (aliased as `rd`) to find links
     *   connected to those parent records. `$this->value` (source_rty_ID) seems to be ignored or
     *   misapplied in this branch in the original code, as `rd.rec_RecTypeID=$source_rty_ID` is commented out.
     * - If standalone:
     *   The subqueries link `Records rd` with `recLinks rl1/rl2`. `$this->value` is used to filter
     *   `rl1.rl_TargetID` or `rl2.rl_SourceID` (the "other side" of the link).
     *
     * @return string|false SQL query string, or `SQL_FALSE` ("0") if `source_rty_ID` is empty in standalone mode.
     */
    public function makeSQL() {

        $source_rty_ID = $this->value; // This is the rty_ID(s) of the "other" records in the link.

        $add_select1 = 'TOPBIBLIO.rec_ID in (select rl1.rl_SourceID '; // Find our records as sources
        $add_select2 = 'TOPBIBLIO.rec_ID in (select rl2.rl_TargetID ';

        //NEW
        $add_from1 = 'recLinks rl1 ';
        $add_where1 = ((false && $source_rty_ID) ?"rd.rec_RecTypeID=$source_rty_ID".SQL_AND:'')
            . ' rl1.rl_TargetID=rd.rec_ID';

        $add_from2 = 'recLinks rl2 ';
        $add_where2 = ((false && $source_rty_ID) ?"rd.rec_RecTypeID=$source_rty_ID".SQL_AND:'')
            . ' rl2.rl_SourceID=rd.rec_ID';


        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $query = $pquery->parentquery;
            //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

            $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
            $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
            $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
            $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);

            $select1 = $add_select1.$query["from"].', '.$add_from1.SQL_WHERE.$query["where"].SQL_AND.$add_where1.' '.$query["sort"].$query["limit"].$query["offset"].')';

            $select2 = $add_select2.$query["from"].', '.$add_from2.SQL_WHERE.$query["where"].SQL_AND.$add_where2.' '.$query["sort"].$query["limit"].$query["offset"].')';


        }else{

            $ids = prepareIds($source_rty_ID);
            if(count($ids)>1){
                $add_where1 = $add_where1.' and rl1.rl_TargetID in ('.implode(',',$ids).')';
                $add_where2 = $add_where2.' and rl2.rl_SourceID in ('.implode(',',$ids).')';
            }elseif(!empty($ids)){
                $add_where1 = $add_where1.' and rl1.rl_TargetID = '.$ids[0];
                $add_where2 = $add_where2.' and rl2.rl_SourceID = '.$ids[0];
            }else{
                return SQL_FALSE;
            }

            $select1 = $add_select1.SQL_RECORDS.', recLinks rl1 WHERE '.$add_where1.')';
            $select2 = $add_select2.SQL_RECORDS.', recLinks rl2 WHERE '.$add_where2.')';

        }

        $select = '(' . $select1 . ' OR ' .$select2. ')';

        return $select;
    }
}

define('SQL_LINKED_EXISTS', '(exists (select dtl_ID from defDetailTypes, recDetails bd '
            .'where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource" LIMIT 1))');
/**
 * Predicate for finding records that have a resource pointer field (`dty_Type="resource"`)
 * pointing to a specific record ID or set of record IDs.
 *
 * `SQL_LINKED_EXISTS` is a pre-defined string:
 * `(exists (select dtl_ID from defDetailTypes, recDetails bd where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource" LIMIT 1))`
 *
 * If `$this->value` is provided, it's a comma-separated list of target record IDs. The `LIMIT 1` in `SQL_LINKED_EXISTS`
 * is replaced with a condition `and bd.dtl_Value in (target_ids) LIMIT 1`.
 * If `$this->value` is not provided, it checks for the existence of any resource pointer field.
 */
class LinkToPredicate extends Predicate {
    /**
     * Generates SQL to find records that have resource pointer fields pointing to specified target record(s).
     *
     * @return string The SQL condition string. Returns `SQL_FALSE` ("0") if value implies multiple IDs in a way that seems unintended by original "???" comment.
     */
    public function makeSQL() {
        if ($this->value) {
            $ids = prepareIds($this->value); // Converts to array of IDs
            if(count($ids)>1){
                return str_replace('LIMIT 1',' and bd.dtl_Value IN (' . join(',', $ids) . ') LIMIT 1',SQL_LINKED_EXISTS);
            } elseif(!empty($ids)) { // Single ID
                return str_replace('LIMIT 1',' and bd.dtl_Value = ' . $ids[0] . ' LIMIT 1',SQL_LINKED_EXISTS);
            } else { // No valid IDs from prepareIds
                 return SQL_FALSE;
            }
        }
        else { // No value provided, check for any link
            return SQL_LINKED_EXISTS;
        }
    }
}

/**
 * Predicate for finding records that are themselves targets of resource pointer fields from other specified records.
 *
 * `SQL_LINKED_EXISTS` is a pre-defined string:
 * `(exists (select dtl_ID from defDetailTypes, recDetails bd where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource" LIMIT 1))`
 *
 * This class's `makeSQL` seems to misuse `SQL_LINKED_EXISTS`. `SQL_LINKED_EXISTS` checks if `TOPBIBLIO.rec_ID` *has* a resource pointer.
 * To find if `TOPBIBLIO.rec_ID` *is* a target, the query needs to look for other records (`bd.dtl_RecID`) whose `dtl_Value` is `TOPBIBLIO.rec_ID`.
 * The current implementation, by modifying `bd.dtl_RecID` in the replacement, fundamentally changes the subquery to search
 * if specific `$ids` *have* resource pointers, which is not "being linked to".
 * Documenting current behavior, but noting the logical discrepancy.
 */
class LinkedToPredicate extends Predicate {
    /**
     * Generates SQL. Based on its structure, it appears to intend to find if records specified in `$this->value`
     * themselves have resource pointers, rather than finding `TOPBIBLIO.rec_ID` being pointed to.
     * This might be a misinterpretation or a bug in the original class logic compared to its name.
     *
     * @return string The SQL condition string. Returns `SQL_FALSE` if value implies multiple IDs in a way that seems unintended.
     */
    public function makeSQL() {
        if ($this->value) {
            $ids = prepareIds($this->value); // IDs of records that are supposed to be linking TO TOPBIBLIO
            // The SQL_LINKED_EXISTS is about TOPBIBLIO having a link.
            // Replacing bd.dtl_RecID with a check against $ids means we are checking if THESE $ids records have links.
            // This does not check if TOPBIBLIO is linked TO by $ids.
            if(count($ids)>1){ 
                return str_replace('LIMIT 1',' and bd.dtl_RecID IN (' . join(',', $ids) . ') LIMIT 1',SQL_LINKED_EXISTS);
            } elseif(!empty($ids)) { // Single ID
                return str_replace('LIMIT 1',' and bd.dtl_RecID = ' . $ids[0] . ' LIMIT 1',SQL_LINKED_EXISTS);
            } else {
                return SQL_FALSE;
            }
        }
        else { // No value provided, check if TOPBIBLIO has any link (standard SQL_LINKED_EXISTS behavior)
            return SQL_LINKED_EXISTS;
        }
    }
}

/**
 * Predicate for finding records related to specified record IDs via relationship records (type 1).
 * A relationship involves a "relationship record" (often type 1) that links two other records (source and target).
 */
class RelatedToPredicate extends Predicate {
    /**
     * Generates SQL to find records that are related to a given set of record IDs.
     *
     * If `$this->value` (a comma-separated list of record IDs) is provided, it constructs an EXISTS subquery.
     * This subquery checks `recLinks` for entries where `rl_RelationID` is not null (indicating a relationship)
     * AND either:
     *  - `TOPBIBLIO.rec_ID` is the target (`rl_TargetID`) and the source (`rl_SourceID`) is one of the provided IDs.
     *  - OR `TOPBIBLIO.rec_ID` is the source (`rl_SourceID`) and the target (`rl_TargetID`) is one of the provided IDs.
     *
     * If `$this->value` is not provided, it finds records that participate in *any* relationship
     * by selecting distinct source or target IDs from `recLinks` where `rl_RelationID` is not null.
     *
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        if ($this->value) {
            $ids = prepareIds($this->value); // Array of IDs
            if (empty($ids)) return SQL_FALSE; // No valid IDs to check against
            $ids_string = "(" . implode(",", array_map('intval', $ids)) . ")"; // Ensure integer IDs in SQL string "(1,2,3)"

            return "(exists (select 1 from recLinks where (rl_RelationID is not null) "
            ." and ((rl_TargetID=TOPBIBLIO.rec_ID and rl_SourceID in $ids_string) "
            ."   or (rl_SourceID=TOPBIBLIO.rec_ID and rl_TargetID in $ids_string)) ))";
        }
        else {
            /* Find records that have any relationship */
            return "TOPBIBLIO.rec_ID in (select distinct rl_TargetID from recLinks WHERE rl_RelationID is not null " // Missing single quote at end of line
            ."union select distinct rl_SourceID from recLinks WHERE rl_RelationID is not null)";
        }
    }
}

/**
 * Predicate for finding records that are part of relationships involving a specified set of record IDs.
 * This includes the relationship records themselves, and the source/target records, *excluding* the initial set of IDs.
 *
 * The implementation executes a direct query to find all related and relationship records,
 * then uses this list in an `IN (...)` clause. This approach was chosen for performance reasons,
 * as noted in the original comments.
 */
class RelationsForPredicate extends Predicate {
    /**
     * Generates SQL to find records that are part of relationships involving the record IDs in `$this->value`.
     *
     * It first executes a query to gather all distinct record IDs that are:
     * - Relationship records (`rl_RelationID = rec_ID`)
     * - Target records (`rl_TargetID = rec_ID`)
     * - Source records (`rl_SourceID = rec_ID`)
     * where the relationship involves any of the IDs specified in `$this->value` (as either source or target),
     * and are not themselves part of the initial set of IDs.
     *
     * The resulting list of record IDs is then used in an `IN (id_list)` condition against `TOPBIBLIO.rec_ID`.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @return string The SQL condition string (e.g., "TOPBIBLIO.rec_ID IN (1,2,3)" or "0" if no related records found).
     */
    public function makeSQL() {
        global $mysqli;
        $input_ids_array = prepareIds($this->value);
        if (empty($input_ids_array)) return SQL_FALSE; // No input IDs
        $input_ids_sql_string = "(" . implode(",", array_map('intval', $input_ids_array)) . ")";

        // Query to find all records involved in relationships with input_ids, excluding input_ids themselves.
        $query_str = "select group_concat( distinct rec_ID ) from Records, recLinks "
                   . "where (rl_RelationID=rec_ID or rl_TargetID=rec_ID or rl_SourceID=rec_ID) " // Record is part of the link triad
                   . "and (rl_RelationID is not null) " // It's a relationship
                   . "and (rl_SourceID in $input_ids_sql_string or rl_TargetID in $input_ids_sql_string) " // Link involves one of the input IDs
                   . "and rec_ID not in $input_ids_sql_string"; // Exclude the input IDs themselves from the result set

        $res = $mysqli->query($query_str);
        $related_ids_row = $res->fetch_row();
        $related_ids_concat = $related_ids_row[0] ?? null;

        if (! $related_ids_concat) { // No other records found related to the input set
            return SQL_FALSE; // "0"
        } else{
            return "TOPBIBLIO.rec_ID in ($related_ids_concat)";
        }
    }
}

/**
 * Predicate for finding records modified after (or on) a specific date (`rec_Modified >= date`).
 */
class AfterPredicate extends Predicate {

    /**
     * Generates SQL to find records modified on or after the given date.
     * Uses `TOPBIBLIO.rec_Modified`. Supports negation.
     *
     * @return string The SQL condition string, or '1' (true) if date parsing fails.
     */
    public function makeSQL() {
         try{
            $timestamp = new DateTime($this->value); // Attempt to parse the date value
            $not = ($this->parent->negate)? 'NOT' : ''; // SQL NOT keyword
            $datestamp = $timestamp->format(DATE_8601); // Format to ISO 8601 (YYYY-MM-DDTHH:MM:SS+ZZZZ)

            return "$not TOPBIBLIO.rec_Modified >= '$datestamp'";
         } catch (Exception  $e){
            // Date parsing failed, log or handle error if necessary
            // print $this->value.' => NOT SUPPORTED<br>'; // Original debug print
         }
        return '1'; // Default to a neutral condition if date is invalid
    }
}

/**
 * Predicate for finding records modified before (or on) a specific date (`rec_Modified <= date`).
 */
class BeforePredicate extends Predicate {

    /**
     * Generates SQL to find records modified on or before the given date.
     * Uses `TOPBIBLIO.rec_Modified`. Supports negation.
     *
     * @return string The SQL condition string, or '1' (true) if date parsing fails.
     */
    public function makeSQL() {
         try{
            $timestamp = new DateTime($this->value); // Attempt to parse the date value
            $not = ($this->parent->negate)? 'NOT' : ''; // SQL NOT keyword
            $datestamp = $timestamp->format(DATE_8601); // Format to ISO 8601

            return "$not TOPBIBLIO.rec_Modified <= '$datestamp'";
         } catch (Exception  $e){
            // Date parsing failed, log or handle error if necessary
            // print $this->value.' => NOT SUPPORTED<br>'; // Original debug print
         }
        return '1'; // Default to a neutral condition if date is invalid
    }
}

/**
 * Base class for date-based predicates that operate on a specific record column
 * (e.g., `rec_Added`, `rec_Modified`).
 * Subclasses must specify the column name in their constructor.
 */
class DatePredicate extends Predicate {
    /** @var string The database column name to apply the date comparison to (e.g., "TOPBIBLIO.rec_Added"). */
    public $col;

    /**
     * Constructor for DatePredicate.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $col The database column name for the date comparison.
     * @param string $value The date string value for comparison.
     */
    public function __construct(&$parent, $col, $value) {
        $this->col = $col;
        parent::__construct($parent, $value);
    }

    /**
     * Generates SQL for comparing the specified date column.
     *
     * Uses `isDateTime()` to validate the date value and `makeDateClause()`
     * (which itself uses `Temporal` and compares against `rdi_estMinDate`/`rdi_estMaxDate`)
     * to generate the comparison logic.
     *
     * Note: The `makeDateClause()` method in the Predicate base class is designed for
     * `recDetailsDateIndex` (rdi_ columns). Applying it directly to header columns like
     * `rec_Added` or `rec_Modified` might be a logical mismatch unless the query structure
     * ensures these columns are also somehow indexed or handled by a similar mechanism
     * if `makeDateClause` is used. The original `makeDateClause_old` was for header fields.
     * This `makeSQL` implementation seems to intend using the newer `makeDateClause`.
     * If `$this->isDateTime()` is false, it defaults to '1' (true).
     *
     * @return string The SQL condition string, or '1' if date is invalid.
     */
    public function makeSQL() {
        $col = $this->col; // The specific column like TOPBIBLIO.rec_Added

        if($this->isDateTime()){ // Validates $this->value
            $not = ($this->parent->negate)? 'NOT ' : ''; // SQL NOT keyword
            // makeDateClause() generates conditions like "(rdi_estMaxDate>=val AND rdi_estMinDate<=val)"
            // This is problematic if $col is 'TOPBIBLIO.rec_Added'.
            // For this to work correctly with $col, makeDateClause would need to be adapted,
            // or a different date clause generation (like makeDateClause_old) should be used.
            // Assuming original intent was to use a generic date comparison logic:
            $s = $this->makeDateClause_old(); // Using makeDateClause_old as it's for header fields.

            // makeDateClause_old returns strings like "between 'X' and 'Y'" or "like 'Z%'" or "= 'W'"
            if(strpos(strtolower($s), "between") === 0 || strpos(strtolower($s), "like") === 0) {
                 // For "BETWEEN" or "LIKE", $not should prefix the whole expression: "NOT (col BETWEEN ...)"
                return $not ? "NOT ($col $s)" : "$col $s";
            } else { // For "= 'date'", "< 'date'", ">= 'date'"
                return "$col $not$s"; // e.g. "rec_Added >= 'date'" or "rec_Added NOT >= 'date'" (which is "rec_Added < 'date'")
            }
        }
        return '1'; // Default to a neutral condition if date is invalid
    }
}

/**
 * Predicate for searching by record creation date (`rec_Added`).
 */
class DateAddedPredicate extends DatePredicate {
    /**
     * Constructor for DateAddedPredicate.
     * Sets the column to `TOPBIBLIO.rec_Added`.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value The date string for comparison.
     */
    public function __construct(&$parent, $value) {
        parent::__construct($parent, 'TOPBIBLIO.rec_Added', $value);
    }
}

/**
 * Predicate for searching by record modification date (`rec_Modified`).
 * This is also used for general 'date:' queries.
 */
class DateModifiedPredicate extends DatePredicate {
    /**
     * Constructor for DateModifiedPredicate.
     * Sets the column to `TOPBIBLIO.rec_Modified`.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value The date string for comparison.
     */
    public function __construct(&$parent, $value) {
        parent::__construct($parent, 'TOPBIBLIO.rec_Modified', $value);
    }
}

/**
 * Predicate for searching by record owner workgroup (`rec_OwnerUGrpID`).
 */
class WorkgroupPredicate extends Predicate {
    /**
     * Generates SQL for matching the `rec_OwnerUGrpID`.
     *
     * The value can be:
     * - A numeric user/group ID.
     * - A comma-separated list of numeric user/group IDs.
     * - The string "currentUser" or "current_user", which resolves to the `$currUserID`.
     * - A workgroup name, which is resolved to its ID via a subquery on `sysUGrps`.
     * Supports negation.
     *
     * @global \mysqli $mysqli The global mysqli database connection.
     * @global int $currUserID The ID of the current user.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli, $currUserID;

        if(strtolower($this->value)=='currentuser' || strtolower($this->value)=='current_user'){
            $this->value = (string)$currUserID; // Ensure value is string for consistent processing
        }

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            return "TOPBIBLIO.rec_OwnerUGrpID $eq ".intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) { // Comma-separated list of IDs
            $in = ($this->parent->negate)? 'NOT IN' : 'IN'; // Use SQL NOT IN
            return "TOPBIBLIO.rec_OwnerUGrpID $in (" . $this->value . ")";
        }
        else { // Assume it's a workgroup name
            $val = $mysqli->real_escape_string($this->value);
            return "TOPBIBLIO.rec_OwnerUGrpID $eq (select grp.ugr_ID from sysUGrps grp where grp.ugr_Name = '$val' limit 1)";
        }
    }
}

/**
 * Predicate for spatial searches using WKT (Well-Known Text) geometry.
 * Finds records whose `dtl_Geo` is contained within the specified geometry.
 */
class SpatialPredicate extends Predicate {
    /**
     * Generates SQL for a spatial containment search.
     *
     * Uses `ST_Contains(ST_GeomFromText('WKT_VALUE'), bd.dtl_Geo)`.
     * The search value (`$this->value`) is expected to be a WKT string.
     * Note: Negation is not directly supported by this predicate's `makeSQL`.
     *
     * Supports:
     *   geo:"-16,32,40,72"   => viewport extent, ST_Intersects
     *   geo:"POLYGON (...)"  => legacy WKT, ST_Contains     * 
     * @return string The SQL condition string using an EXISTS subquery.
     */
    public function makeSQL() {

        global $mysqli;

        $value = trim((string)$this->value);
        $spatialFunction = 'ST_Contains';
        $wkt = $value;

        // Legacy/plain-query viewport form: west,south,east,north
        $extent = array_map('trim', explode(',', $value));
        if(count($extent) === 4
            && is_numeric($extent[0]) && is_numeric($extent[1])
            && is_numeric($extent[2]) && is_numeric($extent[3])){

            $west = (float)$extent[0];
            $south = (float)$extent[1];
            $east = (float)$extent[2];
            $north = (float)$extent[3];

            if($west < -180 || $west > 180 || $east < -180 || $east > 180
                || $south < -90 || $south > 90 || $north < -90 || $north > 90
                || $south > $north){
                return '(1=0)';
            }

            $wkt = "POLYGON (($west $south, $east $south, $east $north, $west $north, $west $south))";
            $spatialFunction = 'ST_Intersects';
        }

        $wkt = $mysqli->real_escape_string($wkt);

        return "(exists (select dtl_ID from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and {$spatialFunction}(ST_GeomFromText('{$wkt}'), bd.dtl_Geo) limit 1))";
    }
    
}

/**
 * Predicate for searching by geographic coordinates (latitude or longitude).
 * It compares a given coordinate value against the geometry (`dtl_Geo`) of records.
 */
class CoordinatePredicate extends Predicate {

    /** @var string The MySQL spatial function to extract the coordinate (e.g., 'ST_X', 'ST_Y'). */
    private $coordFunction;

    /**
     * Constructor for CoordinatePredicate.
     *
     * @param AndLimb $parent Reference to the parent AndLimb.
     * @param string $value The coordinate value string for comparison.
     * @param string $coordFunction The MySQL spatial function (ST_X or ST_Y) to use for extracting the coordinate from geometry.
     */
    public function __construct(&$parent, $value, $coordFunction) {
        parent::__construct( $parent, $value );
        $this->coordFunction = $coordFunction;
    }

    /**
     * Generates SQL for coordinate-based spatial searches.
     *
     * Handles several comparison types based on parent flags (`lessthan`, `greaterthan`, `exact`, `negate`):
     * - Less than: Checks if the northernmost point of the geometry's envelope is south of the given coordinate.
     * - Greater than: Checks if the southernmost point of the geometry's envelope is north of the given coordinate.
     * - Exact: Checks if a Point geometry (`dtl_Value = 'p'`) has the exact coordinate.
     * - Range (`<>` in value): Checks if the centroid of the geometry's envelope falls within the given coordinate range.
     * - Default (contains): Checks if the given coordinate falls within the North-South range of the geometry's envelope.
     *
     * @return string The SQL condition string using an EXISTS subquery.
     */
    public function makeSQL() {
        $op = '';
        $val = floatval($this->value); // The coordinate value to compare against.

        if ($this->parent->lessthan) {
            $op = ($this->parent->negate)? '>=' : '<';
            // Checks if the northernmost point of the bounding box (envelope) is less than (south of) the given value.
            // ST_PointN(ST_ExteriorRing(ST_Envelope(bd.dtl_Geo)), 4) gets the NE corner of the envelope.
            return "(exists (select 1 from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and {$this->coordFunction}( ST_PointN( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ), 4 ) ) $op $val limit 1))";
        } elseif($this->parent->greaterthan) {
            $op = ($this->parent->negate)? '<=' : '>';
            // Checks if the southernmost point of the bounding box (envelope) is greater than (north of) the given value.
            // ST_StartPoint(ST_ExteriorRing(ST_Envelope(bd.dtl_Geo))) gets the SW corner of the envelope.
            return "(exists (select 1 from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and {$this->coordFunction}( ST_StartPoint( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ) ) ) $op $val limit 1))";
        } elseif($this->parent->exact) {
            $op = $this->parent->negate? "!=" : "=";
            // Checks for an exact match on a Point geometry. Assumes dtl_Value = 'p' for points.
            return "(exists (select 1 from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null and bd.dtl_Value = 'p'
            and {$this->coordFunction}(bd.dtl_Geo) $op $val limit 1))";
        }

        // Default behavior or range check
        $match_pred = '';
        if (strpos($this->value,"<>")>0) { // Range query, e.g., "lat:10<>20"
            $vals = explode("<>", $this->value);
            $val1 = floatval($vals[0]);
            $val2 = floatval($vals[1]);
            // Checks if the centroid of the geometry's envelope is within the specified range.
            $match_pred = $this->coordFunction.'( ST_Centroid( ST_Envelope(bd.dtl_Geo) ) ) BETWEEN '.$val1.SQL_AND.$val2;
        } else { // Default: check if the coordinate value is within the N-S or E-W extent of the geometry's envelope
            // Checks if the provided coordinate value falls between the min and max coordinate of the geometry's envelope.
            $match_pred = "$val BETWEEN {$this->coordFunction}( ST_StartPoint( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ) ) )
                        AND {$this->coordFunction}( ST_PointN( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ), 4 ) )";
        }

        return "(exists (select 1 from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and $match_pred limit 1))";
    }
}

/**
 * Predicate for searching by record hash (`rec_Hash`).
 */
class HHashPredicate extends Predicate {
    /**
     * Generates SQL for matching the `rec_Hash`.
     * Handles exact match (equals or not equals) or LIKE match (starts with).
     *
     * @global \mysqli $mysqli The global mysqli database connection for escaping.
     * @return string The SQL condition string.
     */
    public function makeSQL() {
        global $mysqli;

        $op = '';
        if ($this->parent->exact) {
            $op = $this->parent->negate? "!=" : "=";
            return "TOPBIBLIO.rec_Hash $op '" . $mysqli->real_escape_string($this->value) . "'";
        }
        else { // Default to a "starts with" search
            $op = $this->parent->negate? " NOT LIKE " : " LIKE "; // Corrected: added spaces around NOT LIKE
            return "TOPBIBLIO.rec_Hash $op '" . $mysqli->real_escape_string($this->value) . "%'";
        }
    }
}
/*

keywords for 'q' parameter
u:  url
t:  title
tag:   tag
id:  id
n:   description
usr:   user id
any:

function construct_legacy_search() {
$q = '';

if (@$_REQUEST['search_title']) $_REQUEST['t'] = $_REQUEST['search_title'];
if (@$_REQUEST['search_tagString']) $_REQUEST['k'] = $_REQUEST['search_tagString'];
if (@$_REQUEST['search_url']) $_REQUEST['u'] = $_REQUEST['search_url'];
if (@$_REQUEST['search_description']) $_REQUEST['n'] = $_REQUEST['search_description'];
if (@$_REQUEST['search_rectype']) $_REQUEST['r'] = $_REQUEST['search_rectype'];
if (@$_REQUEST['search_user_id']) $_REQUEST['uid'] = $_REQUEST['search_user_id'];


if (@$_REQUEST['t']) $q .= $_REQUEST['t'] . ' ';
if (@$_REQUEST['k']) {
$K = split(',', $_REQUEST['k']);
foreach ($K as $k) {
if (strpos($k, '"'))
$q .= 'tag:' . $k . ' ';
else
$q .= 'tag:"' . $k . '" ';
}
}
if (@$_REQUEST['u']) $q .= 'u:"' . $_REQUEST['u']. '" ';
if (@$_REQUEST['n']) $q .= 'n:"' . $_REQUEST['n']. '" ';
if (@$_REQUEST['r']) $q .= 't:' . intval($_REQUEST['r']) . ' ';// note: defRecTypes was 'r', now 't' (for TYPE!)
if (@$_REQUEST['uid']) $q .= 'usr:' . intval($_REQUEST['uid']) . ' ';
if (@$_REQUEST['bi']) $q .= 'id:"' . $_REQUEST['bi'] . '" ';
if (@$_REQUEST['a']) $q .= 'any:"' . $_REQUEST['a'] . '" ';

$_REQUEST['q'] = $q;
}
*/
?>
