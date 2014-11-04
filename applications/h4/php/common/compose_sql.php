<?php
    /** 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Stephen White   <stephen.white@sydney.edu.au>
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

    define('BOOKMARK', 'bookmark');
    define('BIBLIO', 'biblio'); //outdated @toremove
    define('EVERYTHING', 'everything');


    define('SORT_POPULARITY', 'p');
    define('SORT_RATING', 'r');
    define('SORT_URL', 'u');
    define('SORT_MODIFIED', 'm');
    define('SORT_ADDED', 'a');
    define('SORT_TITLE', 't');
    
    
    //defined in const.php define('DT_RELATION_TYPE', 6);

    $mysqli = null;

    /**
    * Use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $query prefix
    *
    * @param System $system
    * @param mixed $query  -  prefix (usually SELECT with list of fields)
    * @param mixed $params
    *
    parameters:

    stype  - (OUTDATED) type of search: key - by tag title, all - by title of record and titles of its resource, by default by record title
    s - sort order   (NOTE!!!  sort may be defined in "q" parameter also)
    l or limit  - limit of records
    o or offset
    w - domain of search a|all, b|bookmark, e (everything)

    qq - several conjunctions and disjunctions
    q  - query string

    keywords for 'q' parameter
    url:  url
    title: title contains
    t:  record type id
    f:   field id
    tag:   tag
    id:  id
    n:   description
    usr:   user id
    any:
    relatedto:
    sortby:

    *
    * @param mixed $currentUser - array with indexes ugr_ID, ugr_Groups (list of group ids)
    *                       we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted
    * @param mixed $publicOnly
    * 
    */
    function compose_sql_query($db, $select_clause, $params, $currentUser=null, $publicOnly=false) {

        $query = get_sql_query_clauses($db, $params, $currentUser=null, $publicOnly=false);

        $res_query =  $select_clause.$query["from"]." WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];
        /*****DEBUG****///error_log("request to query returns ".print_r($res_query,true));
        return $res_query;
    }
    /**
    * Use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $query prefix
    *
    * @param System $system
    * @param mixed $query  -  prefix (usually SELECT with list of fields)
    * @param mixed $params
    *
    parameters:

    stype  - (OUTDATED) type of search: key - by tag title, all - by title of record and titles of its resource, by default by record title
    s - sort order   (NOTE!!!  sort may be defined in "q" parameter also)
    l or limit  - limit of records
    o or offset
    w - domain of search a|all, b|bookmark, e (everything)

    qq - several conjunctions and disjunctions
    q  - query string

    keywords for 'q' parameter
    url:  url
    title: title contains
    t:  record type id
    f:   field id
    tag:   tag
    id:  id
    n:   description
    usr:   user id
    any:
    relatedto:
    sortby:

    *
    * @param mixed $currentUser - array with indexes ugr_ID, ugr_Groups (list of group ids)
    *                       we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted
    * @param mixed $publicOnly
    */
    function get_sql_query_clauses($db, $params, $currentUser=null, $publicOnly=false) {
        
        global $mysqli;
        
        $mysqli = $db;

        /* use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $select_clause */
        if (! $params) $params = array();//$_REQUEST;
        define('stype', @$params['stype']);

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

        // 2. DETECT SEARCH DOMAIN ------------------------------------------------------------------------------------------
        if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0) {    // my bookmark entries
            $search_domain = BOOKMARK;
        } else if (@$params['w'] == 'e') { //everything - including temporary
            $search_domain = EVERYTHING;
        } else {                // all records entries
            $search_domain = null;
        }


        // 4. QUERY MAY BE SIMPLE or full expressiveness ----------------------------------------------------------------------

        $query = parse_query($search_domain, @$params['q'], @$params['s'], @$params['parentquery'], $currUserID);

        $where_clause = $query->where_clause;

        // 5. DEFINE USERGROUP RESTRICTIONS ---------------------------------------------------------------------------------

        if ($search_domain != EVERYTHING) {

            if ($where_clause) $where_clause = '(' . $where_clause . ') and ';

            if ($search_domain == BOOKMARK) {
                $where_clause .= ' (bkm_UGrpID=' . $currUserID . ' and not TOPBIBLIO.rec_FlagTemporary) ';
            } else if ($search_domain == BIBLIO) {   //NOT USED
                $where_clause .= ' (bkm_UGrpID is null and not TOPBIBLIO.rec_FlagTemporary) ';
            } else {
                $where_clause .= ' not TOPBIBLIO.rec_FlagTemporary ';
            }

        }

        if($publicOnly){
            $query->recVisibilityType = "public";
        }

        if($query->recVisibilityType && $query->recVisibilityType!="hidden"){ 
            $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="'.$query->recVisibilityType.'")';  //'pending','public','viewable'
        }else{
            if($query->recVisibilityType){ //hidden 
                $where2 = 'TOPBIBLIO.rec_NonOwnerVisibility="hidden" and ';  
            }else{
                $where2 = '(not TOPBIBLIO.rec_NonOwnerVisibility="hidden") or ';  
            }

            $where2 = '( '.$where2.'TOPBIBLIO.rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
        }

        $where_clause = $where_clause . ' and ' . $where2;

        // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------
        
        $limit = get_limit($params);

        $offset = get_offset($params);

        // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
        return array("from"=>$query->from_clause, "where"=>$where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

    }

    function get_limit($params){
        if (@$params["l"]) {
            $limit = intval($params["l"]);
        }else if(@$params["limit"]) {
            $limit = intval($params["limit"]);
        }

        if (!@$limit || $limit < 1){
            $limit = 1000;
        }
        return $limit;
    }
    
    function get_offset($params){
        $offset = 0;
        if (@$params["o"]) {
            $offset = intval($params["o"]);
        }else if(@$params["offset"]) {
            $offset = intval($params["offset"]);  // this is back in since hml.php passes through stuff from sitemap.xmap
        }
        if (!@$offset || $offset < 1){
            $offset = 0;
        }
        return $offset;
    }

    /**
    * Returns array with 3 elements: FROM, WHERE and ORDER BY
    *
    * @param mixed $search_domain -   bookmark - searcch my bookmarks, otherwise all records
    * @param mixed $text     - query string
    * @param mixed $sort_order
    * $parentquery - array of SQL clauses of parent/top query - it is needed for linked and relation queries that are depended on source/top query
    * @$currUserID
    * NOTUSED @param mixed $wg_ids is a list of the workgroups we can access; records records marked with a rec_OwnerUGrpID not in this list are omitted
    * NOTUSED @param mixed $publicOnly
    */
    function parse_query($search_domain, $text, $sort_order='', $parentquery, $currUserID) { //$currUserID, $wg_ids=null, $publicOnly=false


        // remove any  lone dashes outside matched quotes.
        $text = preg_replace('/- (?=[^"]*(?:"[^"]*"[^"]*)*$)|-\s*$/', ' ', $text);
        // divide the query into dbl-quoted and other (note a dash(-) in front of a string is preserved and means negate)
        preg_match_all('/(-?"[^"]+")|([^" ]+)/',$text,$matches);
        $preProcessedQuery = "";
        $connectors=array(":",">","<","=",",");
        /*****DEBUG****///error_log("parse query matches = ".print_r($matches,true));
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
            /*****DEBUG****///error_log("query part = ".print_r($queryPart[0],true));
            /*****DEBUG****///error_log("preprocessed query = ".print_r($preProcessedQuery,true));
        }

        $query = new Query($search_domain, $preProcessedQuery, $currUserID, $parentquery);
        $query->makeSQL();

        $q = null;

        if ($query->sort_phrases) {
            // handled in Query logic
        } else if (preg_match('/^f:(\d+)/', $sort_order, $matches)) {
            //mindfuck!!!! - sort by detail?????
            $q = 'ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID='.$matches[1].' ORDER BY link.rec_Title limit 1), "~~"), rec_Title';
        } else {
            if ($search_domain == BOOKMARK) {
                switch ($sort_order) {
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
                    case SORT_TITLE: default:
                        $q = 'rec_Title = "", rec_Title';
                }
            } else {
                switch ($sort_order) {
                    case SORT_POPULARITY:
                        $q = 'rec_Popularity desc, rec_Added desc'; break;
                    case SORT_URL:
                        $q = 'rec_URL is null, rec_URL'; break;
                    case SORT_MODIFIED:
                        $q = 'rec_Modified desc'; break;
                    case SORT_ADDED:
                        $q = 'rec_Added desc'; break;
                    case SORT_TITLE: default:
                        $q = 'rec_Title = "", rec_Title';
                }
            }

        }
        if($q){ //sort defined in separate request param
            $query->sort_clause = ' ORDER BY '.$q;
        }
        /*****DEBUG****///print ("QUERY after parse ".print_r($q,true));
        return $query;
    }


    class Query {

        var $from_clause = '';
        var $where_clause = '';
        var $sort_clause = '';
        var $recVisibilityType;
        var $parentquery = null;

        var $top_limbs;
        var $sort_phrases;
        var $sort_tables;


        function Query($search_domain, $text, $currUserID, $parentquery, $absoluteStrQuery = false) {

            $this->search_domain = $search_domain;
            $this->recVisibilityType = null;
            $this->currUserID = $currUserID;
            $this->absoluteStrQuery = $absoluteStrQuery;
            $this->parentquery = $parentquery;

            $this->top_limbs = array();
            $this->sort_phrases = array();
            $this->sort_tables = array();

            // Find any 'vt:' phrases in the query, and pull them out.   vt - visibility type
            while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(vt:(?:f:|field:)?"[^"]+"\\S*|vt:\\S*)/', $text, $matches)) {
                $this->addVisibilityTypeRestriction(substr($matches[2],3));
                $text = preg_replace('/\bvt:\S+/i', '', $text); 
                //$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
            }

            // Find any 'sortby:' phrases in the query, and pull them out.
            // "sortby:..." within double quotes is regarded as a search term, and we don't remove it here
            while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(sortby:(?:f:|field:)?"[^"]+"\\S*|sortby:\\S*)/', $text, $matches)) {
                /*****DEBUG****///error_log("Create query obj ".print_r($matches, 1));
                $this->addSortPhrase($matches[2]);
                $text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
            }
            /*****DEBUG****///error_log("Create query obj text after processing sortby ".print_r($text, 1));

            // Search-within-search gives us top-level ANDing (full expressiveness of conjunctions and disjunctions)
            if(preg_match('/&&|\\bAND\\b/i', $text)){
                $q_bits = preg_split('/&&|\\bAND\\b/i', $text);
            }else{
                $q_bits = array($text);
            }
            foreach ($q_bits as $q_bit) {
                $this->addTopLimb($q_bit);
            }        
            /*****DEBUG****///error_log("Create query obj text after processing and/or ".print_r($this->top_limbs, true));
        }

        function addTopLimb($text) {


            
            $or_limbs = array();
            // According to WWGD, OR is the top-level delimiter (yes, more top-level than double-quoted text)
            $or_texts = preg_split('/\\b *OR *\\b/i', $text);
            for ($i=0; $i < count($or_texts); ++$i){
                if ($or_texts[$i]){
                    array_push( $or_limbs, new OrLimb($this, $or_texts[$i]) );
                } 
            }
            array_push($this->top_limbs, $or_limbs);
        }

        //
        function addSortPhrase($text) {
            array_unshift($this->sort_phrases, new SortPhrase($this, $text));
        }

        //
        function addVisibilityTypeRestriction($visibility_type) {
            if ($visibility_type){
                $visibility_type = strtolower($visibility_type);
                if(in_array($visibility_type,array('viewable','hidden','pending','public')))
                {
                    $this->recVisibilityType = $visibility_type;
                }
            }
        }

        function makeSQL() {

            //WHERE     
            $where_clause = '';
            $and_clauses = array();
            for ($i=0; $i < count($this->top_limbs); ++$i) {


                $or_clauses = array();
                $or_limbs = $this->top_limbs[$i];
                for ($j=0; $j < count($or_limbs); ++$j) {
                    $new_sql = $or_limbs[$j]->makeSQL();
                    array_push($or_clauses, '(' . $new_sql . ')');
                }
                sort($or_clauses);    // alphabetise
                $where_clause = join(' or ', $or_clauses);
                if(count($or_clauses)>1) $where_clause = '(' . $where_clause . ')';
                array_push($and_clauses, $where_clause);
            }
            sort($and_clauses);
            $this->where_clause = join(' and ', $and_clauses);        

            //error_log(">>> ". $this->where_clause);

            //SORT
            $sort_clause = '';
            $sort_clauses = array();
            for ($i=0; $i < count($this->sort_phrases); ++$i) {
                @list($new_sql, $new_sig, $new_tables) = $this->sort_phrases[$i]->makeSQL();

                if (! @$sort_clauses[$new_sig]) {    // don't repeat identical sort clauses
                    if ($sort_clause) $sort_clause .= ', ';

                    $sort_clause .= $new_sql;
                    if ($new_tables) array_push($this->sort_tables, $new_tables);

                    $sort_clauses[$new_sig] = 1;
                }
            }
            if ($sort_clause) $sort_clause = ' ORDER BY ' . $sort_clause;
            $this->sort_clause = $sort_clause;

            //FROM
            if ($this->search_domain == BOOKMARK) {
                $this->from_clause = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
            }else{
                $this->from_clause = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$this->currUserID.' ';
            }

            $this->from_clause .= join(' ', $this->sort_tables);    // sorting may require the introduction of more tables

            //MAKE
            return $this->from_clause . ' WHERE' . $this->where_clause . $this->sort_clause;
        }
    }


    class OrLimb {
        var $and_limbs;

        var $parent;


        function OrLimb(&$parent, $text) {
            $this->parent = &$parent;
            $this->absoluteStrQuery = $parent->absoluteStrQuery;
            $this->and_limbs = array();
            if (substr_count($text, '"') % 2 != 0) $text .= '"';    // unmatched quote

            //ORIGINAL if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches)) {

            // split by spaces - exclude text inside quotes and parentheses           
            if (preg_match_all('/(?:[^"( ]+|["(][^")]*[")])+(?= |$)/', $text, $matches)) {

                $and_texts = $matches[0];
                //csv import error_log("DEBUG2>>>>> ".print_r($and_texts,true));
                for ($i=0; $i < count($and_texts); ++$i){
                    $str = $and_texts[$i];
                    if ($str) {
                        $str = str_replace('+', " ", $str); //workaround until understand how to regex F:("AA BB CC")
                        $this->addAndLimb($str);
                    }
                }
            }
        }

        function addAndLimb($text) {
            $this->and_limbs[] = new AndLimb($this, $text);
        }


        function makeSQL() {
            $sql = '';

            $and_clauses = array();
            for ($i=0; $i < count($this->and_limbs); ++$i) {
                $new_sql = $this->and_limbs[$i]->pred->makeSQL();
                if (strlen($new_sql) > 0) {
                    array_push($and_clauses, $new_sql);
                }
            }
            sort($and_clauses);
            $sql = join(' and ', $and_clauses);

            return $sql;
        }
    }


    class AndLimb {
        var $negate;
        var $exact, $lessthan, $greaterthan;
        var $pred;

        var $parent;


        function AndLimb(&$parent, $text) {
            $this->parent = &$parent;
            $this->absoluteStrQuery = false;
            if (preg_match('/^".*"$/',$text,$matches)) {
                $this->absoluteStrQuery = true;
            }

            $this->exact = false;
            if ($text[0] == '-') {
                $this->negate = true;
                $text = substr($text, 1);
            } else {
                $this->negate = false;
            }

            //create predicate
            $this->pred = $this->createPredicate($text); //was by reference
            
        }


        function createPredicate($text) {
            
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

                if (defined('stype')  &&  stype == 'key')
                    return new TagPredicate($this, $pred_val);
                else if (defined('stype')  &&  stype == 'all')
                    return new AnyPredicate($this, $pred_val);
                else    // title search is default search
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
                    return new AnyPredicate($this, $pred_val);

                case 'id':
                case 'ids':
                    return new BibIDPredicate($this, $pred_val);

                case 'field':
                case 'f':

                    $colon_pos = strpos($raw_pred_val, ':');
                    if (! $colon_pos) {
                        if (($colon_pos = strpos($raw_pred_val, '='))) $this->exact = true;
                        else if (($colon_pos = strpos($raw_pred_val, '<'))) $this->lessthan = true;
                            else if (($colon_pos = strpos($raw_pred_val, '>'))) $this->greaterthan = true;
                    }
                    if ($colon_pos === FALSE)
                        return new AnyPredicate($this, $raw_pred_val);
                    else if ($colon_pos == 0)
                        return new AnyPredicate($this, substr($raw_pred_val, 1));
                        else
                            return new FieldPredicate($this, $this->cleanQuotedValue(substr($raw_pred_val, 0, $colon_pos)),
                                $this->cleanQuotedValue(substr($raw_pred_val, $colon_pos+1)));

                case 'linkedfrom': 
                    return new LinkedFromParentPredicate($this, $pred_val);                                            
                case 'relatedfrom': 
                    return new RelatedFromParentPredicate($this, $pred_val);
                case 'linked_to': 
                    return new LinkedToParentPredicate($this, $pred_val);                                            
                case 'related_to': 
                    return new RelatedToParentPredicate($this, $pred_val);
                case 'links': 
                    return new AllLinksPredicate($this, $pred_val);
                    
                case 'linkto':    // linkto:XXX matches records that have a recDetails reference to XXX
                    return new LinkToPredicate($this, $pred_val);
                case 'linkedto':    // linkedto:XXX matches records that are referenced in one of XXX's bib_details
                    return new LinkedToPredicate($this, $pred_val);
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

                case 'latitude':
                case 'lat':
                    return new LatitudePredicate($this, $pred_val);

                case 'longitude':
                case 'long':
                case 'lng':
                    return new LongitudePredicate($this, $pred_val);

                case 'hhash':
                    return new HHashPredicate($this, $pred_val);
            }

            // no predicate-type specified ... look at search type specification
            if (defined('stype')  &&  stype == 'key') {    // "default" search should be on tag
                return new TagPredicate($this, $pred_val);
            } else if (defined('stype')  &&  stype == 'all') {
                return new AnyPredicate($this, $pred_val);
            } else {
                return new TitlePredicate($this, $pred_val);
            }
        }


        function cleanQuotedValue($val) {
            if ($val[0] == '"') {
                if ($val[strlen($val)-1] == '"')
                    $val = substr($val, 1, -1);
                else
                    $val = substr($val, 1);
                return preg_replace('/ +/', ' ', trim($val));
            }

            return $val;
        }
    }


    class SortPhrase {
        var $value;

        var $parent;

        function SortPhrase(&$parent, $value) {
            $this->parent = &$parent;

            $this->value = $value;
        }
        // return list of  sql Phrase, signature, from clause for sort
        function makeSQL() {
            global $mysqli;
            
            $colon_pos = strpos($this->value, ':');
            $text = substr($this->value, $colon_pos+1);

            $colon_pos = strpos($text, ':');
            if ($colon_pos === FALSE) $subtext = $text;
            else $subtext = substr($text, 0, $colon_pos);

            // if sortby: is followed by a -, we sort DESCENDING; if it's a + or nothing, it's ASCENDING
            $scending = '';
            if ($subtext[0] == '-') {
                $scending = ' desc ';
                $subtext = substr($subtext, 1);
                $text = substr($text, 1);
            } else if ($subtext[0] == '+') {
                $subtext = substr($subtext, 1);
                $text = substr($text, 1);
            }

            switch (strtolower($subtext)) {
                case 'p': case 'popularity':
                    return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', NULL);

                case 'r': case 'rating':
                    if ($this->parent->search_domain == BOOKMARK) {
                        return array('-(bkm_Rating)'.$scending, 'bkmk_rating', NULL); //SAW Ratings Change todo: test queries with rating
                    } else {    // default to popularity sort
                        return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', NULL);
                    }

                case 'interest':    //todo: change help file to reflect depricated predicates
                case 'content':
                case 'quality':
                    return array('rec_Title'.$scending, NULL);    // default to title sort
                    break;

                case 'u': case 'url':
                    return array('rec_URL'.$scending, 'rec_URL', NULL);

                case 'm': case 'modified':
                    if ($this->parent->search_domain == BOOKMARK) return array('bkm_Modified'.$scending, NULL);
                    else return array('rec_Modified'.$scending, 'rec_Modified', NULL);

                case 'a': case 'added':
                    if ($this->parent->search_domain == BOOKMARK) return array('bkm_Added'.$scending, NULL);
                    else return array('rec_Added'.$scending, 'rec_Added', NULL);

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
                        } else if ($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                            return array(" cast(dtl_Value as unsigned)","dtl_Value is integer",
                                "left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=$field_id ");
                        } else {
                            // have to introduce a defDetailTypes join to ensure that we only use the linked resource's title if this is in fact a resource type (previously any integer, e.g. a date, could potentially index another records record)
                            return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                                "if(dty_Type='date',getTemporalDateString(dtl_Value),dtl_Value)) ".
                                "from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records link on dtl_Value=link.rec_ID ".
                                "where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=$field_id ".
                                "order by if($field_id=$CREATOR, dtl_ID, link.rec_Title) limit 1), '~~') ".$scending,
                                "dtl_DetailTypeID=$field_id", NULL);
                        }
                    } else if (preg_match('/^(?:f|field):"?([^":]+)"?(:m)?/i', $text, $matches)) {
                        @list($_, $field_name, $show_multiples) = $matches;
                        $res = $mysqli->query("select dty_Type from defDetailTypes where dty_Name = '$field_name'");
                        $baseType = $res->fetch_row();
                        $baseType = @$baseType[0];

                        if ($show_multiples) {    // "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
                            $bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
                            return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
                                "left join defDetailTypes bdt$bd_name on bdt$bd_name.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                                ."left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and $bd_name.dtl_DetailTypeID=bdt$bd_name.dty_ID ");
                        } else if ($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                            return array(" cast(dtl_Value as unsigned)","dtl_Value is integer",
                                "left join defDetailTypes bdtInt on bdtInt.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                                ."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
                        } else {
                            return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                                "if(dty_Type='date',getTemporalDateString(dtl_Value),dtl_Value)) ".
                                "from defDetailTypes, recDetails left join Records link on dtl_Value=link.rec_ID ".
                                "where dty_Name='".$mysqli->real_escape_string($field_name)."' and dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=dty_ID ".
                                "order by if(dty_ID=$CREATOR,dtl_ID,link.rec_Title) limit 1), '~~') ".$scending,
                                "dtl_DetailTypeID=$field_id", NULL);
                        }
                    }

                case 't': case 'title':
                    return array('rec_Title'.$scending, NULL);
                case 'rt': case 'type':
                    return array('rec_RecTypeID'.$scending, NULL);
            }
        }
    }


    class Predicate {
        var $value;

        var $parent;

        function Predicate(&$parent, $value) {
            $this->parent = &$parent;

            $this->value = $value;
            $this->query = NULL;
        }

        //$table_name=null
        function makeSQL() { return '1'; }


        //get the top most parent - the Query
        var $query;
        function &getQuery() {
            if (! $this->query) {
                $c = &$this->parent;
                
                //loop up to top-most parent "Query"
                while ($c  &&  strtolower(get_class($c)) != 'query') {
                    $c = &$c->parent;
                    /*if(!is_object($c)){
    error_log(">>>>>2NOT OBJECT ".print_r($c, true));
    error_log("2IN >>>>>".get_class($this));
                        return null;
                    }else{
    error_log(get_class($c)."    ".(strtolower(get_class($c)) != 'query'));
                    }*/
                }
                    

                $this->query = &$c;
            }
            return $this->query;
        }
        
        function isDateTime() {

            if (strpos($this->value,"<>")>0) {
                $vals = explode("<>", $this->value);
                $timestamp0 = strtotime($vals[0]);
                $timestamp1 = strtotime($vals[1]);
            }else{
                $timestamp0 = strtotime($this->value);
                $timestamp1 = 1;
            }
            return ($timestamp0  &&  $timestamp0 != -1 && $timestamp1  &&  $timestamp1 != -1);
        }

        function makeDateClause() {

            if (strpos($this->value,"<>")) {

                $vals = explode("<>", $this->value);
                $timestamp0 = strtotime($vals[0]);
                $timestamp1 = strtotime($vals[1]);
                return "between '".date('Y-m-d', $timestamp0)."' and '".date('Y-m-d', $timestamp1)."'";

            }else{

                $timestamp = strtotime($this->value);
                if ($this->parent->exact) {
                    $datestamp = date('Y-m-d H:i:s', $timestamp);
                    return "= '$datestamp'";
                }
                else if ($this->parent->lessthan) {
                    $datestamp = date('Y-m-d H:i:s', $timestamp);
                    return "< '$datestamp'";
                }
                else if ($this->parent->greaterthan) {
                    $datestamp = date('Y-m-d H:i:s', $timestamp);
                    return "> '$datestamp'";
                }
                else {
                    // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                    if (preg_match('!^\d{4}$!', $this->value)) {
                        $date = date('Y', $timestamp);
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
    }


    class TitlePredicate extends Predicate {
        function makeSQL($isTopRec=true) {
            global $mysqli;
            
            $not = ($this->parent->negate)? 'not ' : '';

            $query = &$this->getQuery(); //not used 
            $evalue = $mysqli->real_escape_string($this->value);

            if($isTopRec){
                $topbiblio = "TOPBIBLIO.";
            }else{
                $topbiblio = "";
            }

            if ($this->parent->exact)
                return $not . $topbiblio.'rec_Title = "'.$evalue.'"';
            else if ($this->parent->lessthan)
                return $not . $topbiblio.'rec_Title < "'.$evalue.'"';
                else if ($this->parent->greaterthan)
                    return $not . $topbiblio.'rec_Title > "'.$evalue.'"';
                    else
                        if(strpos($this->value,"%")===false){
                            return $topbiblio.'rec_Title ' . $not . 'like "%'.$evalue.'%"';    
                        }else{
                            return $topbiblio.'rec_Title ' . $not . 'like "'.$evalue.'"';        
            }

        }
    }

    class TypePredicate extends Predicate {
        function makeSQL($isTopRec=true) {
            global $mysqli;
            
            $eq = ($this->parent->negate)? '!=' : '=';
            if (is_numeric($this->value)) {
                $res = "rec_RecTypeID $eq ".intval($this->value);
            }
            else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                // comma-separated list of defRecTypes ids
                $in = ($this->parent->negate)? 'not in' : 'in';
                $res = "rec_RecTypeID $in (" . $this->value . ")";
            }
            else {
                $res = "rec_RecTypeID $eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
            }

            if($isTopRec){
                $res = "TOPBIBLIO.".$res;
            }
            return $res;
        }
    }


    class URLPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $not = ($this->parent->negate)? 'not ' : '';

            $query = &$this->getQuery();
            return 'TOPBIBLIO.rec_URL ' . $not . 'like "%'.$mysqli->real_escape_string($this->value).'%"';
        }
    }


    class NotesPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $not = ($this->parent->negate)? 'not ' : '';

            $query = &$this->getQuery();
            if ($query->search_domain == BOOKMARK)    // saw TODO change this to check for woot match or full text search
                return '';
            else
                return 'TOPBIBLIO.rec_ScratchPad ' . $not . 'like "%'.$mysqli->real_escape_string($this->value).'%"';
        }
    }


    class UserPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $not = ($this->parent->negate)? 'not ' : '';
            if (is_numeric($this->value)) {
                return $not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
                . ' and bkmk.bkm_UGrpID = ' . intval($this->value) . ')';
            }
            else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                return $not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
                . ' and bkmk.bkm_UGrpID in (' . $this->value . '))';
            }
            else if (preg_match('/^(\D+)\s+(\D+)$/', $this->value,$matches)){    // saw MODIFIED: 16/11/2010 since Realname field was removed.
                return $not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
                . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
                . ' and (usr.ugr_FirstName = "' . $mysqli->real_escape_string($matches[1]) 
                . '" and usr.ugr_LastName = "' . $mysqli->real_escape_string($matches[2]) . '"))';
            }
            else {
                return $not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
                . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
                . ' and usr.ugr_Name = "' . $mysqli->real_escape_string($this->value) . '"))';
            }
        }
    }


    class AddedByPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $eq = ($this->parent->negate)? '!=' : '=';
            if (is_numeric($this->value)) {
                return "TOPBIBLIO.rec_AddedByUGrpID $eq " . intval($this->value);
            }
            else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
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

    class AnyPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $not = ($this->parent->negate)? 'not ' : '';
            return $not . ' (exists (select rd.dtl_ID from recDetails rd '
            . 'left join defDetailTypes on dtl_DetailTypeID=dty_ID '
            . 'left join Records link on rd.dtl_Value=link.rec_ID '
            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
            . '  and if(dty_Type != "resource", '
            .'rd.dtl_Value like "%'.$mysqli->real_escape_string($this->value).'%", '
            .'link.rec_Title like "%'.$mysqli->real_escape_string($this->value).'%"))'
            .' or TOPBIBLIO.rec_Title like "%'.$mysqli->real_escape_string($this->value).'%") ';
        }
    }


    class FieldPredicate extends Predicate {
        var $field_type;
        var $nests = null;

        function FieldPredicate(&$parent, $type, $value) {
            $this->field_type = $type;
            parent::Predicate($parent, $value);

            if ($value[0] == '-') {    // DWIM: user wants a negate, we'll let them put it here
                $parent->negate = true;
                $value = substr($value, 1);
            }

            //nests are inside parentheses 
            preg_match('/\((.+?)(?:\((.+)\))?\)/', $this->value, $matches);
            if(count($matches)>0 && $matches[0]==$this->value){

                //error_log("matches = ".print_r($matches,true));        

                $this->nests = array();
                for ($k=1; $k < count($matches); ++$k) {

                    $text = $matches[$k];


                    if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches2)) {
                        $and_texts = $matches2[0];
                        $limbs = array();
                        for ($i=0; $i < count($and_texts); ++$i){
                            $limbs[] = new AndLimb($this, $and_texts[$i]);
                        }
                        array_push($this->nests, $limbs);
                    }
                }
            }
            /*        
            for ($i=0; $i < count($limbs); ++$i) {
            $new_sql = $limbs[$i]->pred->makeSQL();
            if (strlen($new_sql) > 0) {
            array_push($and_clauses, $new_sql);
            }
            }
            */        
        }

        function makeSQL() {
            $not = ($this->parent->negate)? 'not ' : '';
            /*****DEBUG****///error_log("FieldPred MakeSql value = ".print_r($this->value,true));

            if($this->nests){  //special case nested query for resources

                $field_value = '';
                $nest_joins = '';
                $relation_second_level = '';
                $relation_second_level_where = '';

                if( true ){ //new test version

                    $isrelmarker_0 = ($this->field_type=="relmarker");
                    $isrelmarker_1 = false;

                    for ($i=0; $i < count($this->nests); ++$i) {

                        $limbs = $this->nests[$i];
                        $type_clause = null;
                        $field_type = null;

                        for ($j=0; $j < count($limbs); ++$j) {
                            $cn = get_class($limbs[$j]->pred);

                            if($cn == 'TypePredicate'){
                                $type_clause = $limbs[$j]->pred->makeSQL(false);  // rec_RecTypeID in (12,14)
                            }else if($cn == 'FieldPredicate'){
                                if($i==0 && $limbs[$j]->pred->field_type=="relmarker"){ //allowed for i==0 only
                                    $isrelmarker_1 = true;

                                    $relation_second_level = ', recRelationshipsCache rel1';
                                    if($isrelmarker_0){
                                        $relation_second_level_where = ' and ((rel1.rrc_TargetRecID=rel0.rrc_SourceRecID and rel1.rrc_SourceRecID=link1.rec_ID) '
                                        .'or (rel1.rrc_SourceRecID=rel0.rrc_TargetRecID and rel1.rrc_TargetRecID=link1.rec_ID))';
                                    }else{
                                        $relation_second_level_where = ' and ((rel1.rrc_TargetRecID=rel0.rrc_SourceRecID and rel1.rrc_SourceRecID=rd.dtl_Value) '
                                        .'or (rel1.rrc_SourceRecID=rel0.rrc_TargetRecID and rel1.rrc_TargetRecID=rd.dtl_Value))';
                                    }

                                }else{

                                    $field_type = $limbs[$j]->pred->get_field_type_clause();
                                    if(strpos($field_type,"like")!==false){
                                        $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name ".$field_type." limit 1)";
                                    }
                                    if($limbs[$j]->pred->value){
                                        $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value(); 
                                    }

                                }
                            }else if($cn == 'TitlePredicate'){
                                $field_value .= ' and link'.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                            }else if($cn == 'DateModifiedPredicate'){
                                $field_value .= ' and link'.$i.'.'.$limbs[$j]->pred->makeSQL();
                                $field_value = str_replace("TOPBIBLIO.","",$field_value);
                            }
                        }//for predicates

                        if($type_clause){ //record type clause is mandatory     

                            $nest_joins .= ' left join Records link'.$i.' on link'.$i.'.'.$type_clause;
                            if($i==0){
                                if(!$isrelmarker_0)
                                    $nest_joins .= ' and rd.dtl_Value=link0.rec_ID ';    
                            }else{
                                if(!$isrelmarker_1)
                                    $nest_joins .= ' and linkdt0.dtl_Value=link1.rec_ID ';    
                            }

                            //$nest_joins .= ' and '.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID '; //STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0

                            if($field_type){
                                $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                            }

                        } else {
                            return ''; //fail - record type is mandatory for nested queries
                        }
                    }//for nests

                    if($isrelmarker_0){

                        $resq = $not . 'exists (select rel0.rrc_TargetRecID, rel0.rrc_SourceRecID from recRelationshipsCache rel0 '.$relation_second_level
                        .$nest_joins
                        .' where ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=link0.rec_ID)'
                        .' or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=link0.rec_ID)) '
                        .$relation_second_level_where
                        .$field_value.')';


                    }else{

                        $rd_type_clause = '';
                        $rd_type_clause = $this->get_field_type_clause();
                        if(strpos($rd_type_clause,"like")===false){
                            $rd_type_clause = " and rd.dtl_DetailTypeID ".$rd_type_clause;    
                        }else{
                            $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name ".$rd_type_clause." limit 1)";
                        }

                        $resq = $not . 'exists (select rd.dtl_ID from recDetails rd '.$relation_second_level
                        .$nest_joins
                        . ' where rd.dtl_RecID=TOPBIBLIO.rec_ID ' . $relation_second_level_where . $field_value . $rd_type_clause.')';
                    }

                }else{  //working copy!!!! 

                    for ($i=0; $i < count($this->nests); ++$i) {

                        $limbs = $this->nests[$i];
                        $type_clause = null;
                        $field_type = null;

                        for ($j=0; $j < count($limbs); ++$j) {
                            $cn = get_class($limbs[$j]->pred);

                            if($cn == 'TypePredicate'){
                                $type_clause = $limbs[$j]->pred->makeSQL(false);
                            }else if($cn == 'FieldPredicate'){
                                $field_type = $limbs[$j]->pred->get_field_type_clause();
                                if(strpos($field_type,"like")!==false){
                                    $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name ".$field_type." limit 1)";
                                }
                                if($limbs[$j]->pred->value){
                                    $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value(); 
                                }
                            }else if($cn == 'TitlePredicate'){
                                $field_value .= ' and link'.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                            }else if($cn == 'DateModifiedPredicate'){
                                $field_value .= ' and link'.$i.'.'.$limbs[$j]->pred->makeSQL();
                                $field_value = str_replace("TOPBIBLIO.","",$field_value);
                            }
                        }//for predicates

                        if($type_clause){ //record type clause is mandatory     STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0
                            $nest_joins .= ' left join Records link'.$i.' on '.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID and link'.$i.'.'.$type_clause;
                            if($field_type){
                                $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                            }
                        } else {
                            return ''; //fail - record type is mandatory for nested queries
                        }
                    }//for nests

                    $rd_type_clause = '';
                    $rd_type_clause = $this->get_field_type_clause();
                    if(strpos($rd_type_clause,"like")===false){
                        $rd_type_clause = " and rd.dtl_DetailTypeID ".$rd_type_clause;    
                    }else{
                        $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name ".$rd_type_clause." limit 1)";
                    }

                    $resq = $not . 'exists (select rd.dtl_ID from recDetails rd '
                    .$nest_joins
                    . ' where rd.dtl_RecID=TOPBIBLIO.rec_ID '.$field_value . $rd_type_clause.')';

                }


                //error_log("BBB>>>".$resq);            
                return $resq;
            } //end special case nested query for resources


            $match_pred = $this->get_field_value();

            if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                $isnumericvalue = false;
                $isin = true;
            }else{
                $isin = false;
                $isnumericvalue = is_numeric($this->value);
            }

            if($isin){
                $match_pred_for_term = $match_pred;
            }else if($isnumericvalue){
                $match_pred_for_term = $match_pred; //" = $match_value";
            }else{
                $match_pred_for_term = " = trm.trm_ID";
            }

            $timestamp = $isin ?false:$this->isDateTime();
            if ($timestamp) {
                $date_match_pred = $this->makeDateClause();
            }

            $rd_type_clause = $this->get_field_type_clause();
            if(strpos($rd_type_clause,"like")===false){
                $rd_type_clause = "rd.dtl_DetailTypeID ".$rd_type_clause;    
            }else{
                $rd_type_clause = "rdt.dty_Name ".$rd_type_clause;
            }

            return $not . 'exists (select rd.dtl_ID from recDetails rd '
            . 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID '
            . 'left join Records link on rd.dtl_Value=link.rec_ID '
            . (($isnumericvalue || $isin)?'':'left join defTerms trm on trm.trm_Label '. $match_pred ). " "
            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
            . ' and if(rdt.dty_Type = "resource" AND '.($isnumericvalue?'0':'1').', '
            .'link.rec_Title ' . $match_pred . ', '
            .'if(rdt.dty_Type in ("enum","relationtype"), '
            .'rd.dtl_Value '.$match_pred_for_term.', '
            . ($timestamp ? 'if(rdt.dty_Type = "date", '
                .'str_to_date(getTemporalDateString(rd.dtl_Value), "%Y-%m-%d %H:%i:%s") ' . $date_match_pred . ', '
                .'rd.dtl_Value ' . $match_pred . ')'
                : 'rd.dtl_Value ' . $match_pred ) . '))'
            .' and ' . $rd_type_clause . ')';

            //@todo avoid clauses for resource and date detail types if not necessary
            /* not working properly
            return $not . 'exists (select * from recDetails rd '
            .($timestamp || !is_numeric($this->value)? 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID ': '')
            .(!is_numeric($this->value)?'':'left join Records link on rd.dtl_Value=link.rec_ID ')
            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
            . (!is_numeric($this->value)?'  and if(rdt.dty_Type = "resource", '      // AND '.(is_numeric($this->value)?'0':'1').'
            .'link.rec_Title ' . $match_pred . ', '
            :' and ')
            . ($timestamp ? 'if(rdt.dty_Type = "date", '
            .'str_to_date(getTemporalDateString(rd.dtl_Value), "%Y-%m-%d %H:%i:%s") ' . $date_match_pred . ', '
            .'rd.dtl_Value ' . $match_pred . ')'
            : 'rd.dtl_Value ' . $match_pred ) 
            . (!is_numeric($this->value)?')':'')
            . ' and ' . $rd_type_clause . ')';
            */
        }

        function get_field_type_clause(){
            global $mysqli;
            
            if (preg_match('/^\\d+$/', $this->field_type)) {
                /* handle the easy case: user has specified a (single) specific numeric type */
                $rd_type_clause = '= ' . intval($this->field_type);
            }
            else if (preg_match('/^\d+(?:,\d+)+$/', $this->field_type)) {
                /* user has specified a list of numeric types ... match any of them */
                $rd_type_clause = 'in (' . $this->field_type . ')';
            }
            else {
                /* user has specified the field name */
                $rd_type_clause = 'like "' . $mysqli->real_escape_string($this->field_type) . '%"';
            }
            return  $rd_type_clause;       
        }

        //
        function get_field_value(){
            global $mysqli;

            if (strpos($this->value,"<>")>0) {  //(preg_match('/^\d+(\.\d*)?|\.\d+(?:<>\d+(\.\d*)?|\.\d+)+$/', $this->value)) {

                $vals = explode("<>", $this->value);
                $match_pred = ' between '.$vals[0].' and '.$vals[1].' ';

            }else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                // comma-separated list of ids
                $match_pred = ' in ('.$this->value.')';
                $isin = true;
            }else{
                $isin = false;
                $isnumericvalue = is_numeric($this->value);

                $match_value = '"' . ( $isnumericvalue? floatval($this->value) : $mysqli->real_escape_string($this->value) ) . '"';

                if ($this->parent->exact  ||  $this->value === "") {    // SC100
                    $match_pred = ' = "$match_value"'; //for unknown reason comparison with numeric takes ages
                } else if ($this->parent->lessthan) {
                    $match_pred = " < $match_value";
                } else if ($this->parent->greaterthan) {
                    $match_pred = " > $match_value";
                } else {
                    if($isnumericvalue){
                        $match_pred = " = $match_value";
                    }else if(strpos($this->value,"%")===false){
                        $match_pred = " like '%".$mysqli->real_escape_string($this->value)."%'";
                    }else{
                        $match_pred = " like '".$mysqli->real_escape_string($this->value)."'";
                    }
                }
            }

            return $match_pred;
        }

    }


    class TagPredicate extends Predicate {
        var $wg_value;

        function TagPredicate(&$parent, $value) {
            $this->parent = &$parent;

            $this->value = array();
            $this->wg_value = array();
            $values = explode(',', $value);
            $any_wg_values = false;

            // Heavy, heavy DWIM here: if the tag for which we're searching contains comma(s),
            // then split it into several tags, and do an OR search on those.
            for ($i=0; $i < count($values); ++$i) {
                if (strpos($values[$i], '\\') === FALSE) {
                    array_push($this->value, trim($values[$i]));
                    array_push($this->wg_value, '');
                } else {    // A workgroup tag.  How nice.
                    preg_match('/(.*?)\\\\(.*)/', $values[$i], $matches);
                    array_push($this->wg_value, trim($matches[1]));
                    array_push($this->value, trim($matches[2]));
                    $any_wg_values = true;
                }
            }
            if (! $any_wg_values) $this->wg_value = array();
            $this->query = NULL;
        }

        function makeSQL() {
            global $mysqli;
            
            $pquery = &$this->getQuery();
            $not = ($this->parent->negate)? 'not ' : '';
            if ($pquery->search_domain == BOOKMARK) {
                if (is_numeric(join('', $this->value))) {    // if all tag specs are numeric then don't need a join
                    return $not . 'exists (select * from usrRecTagLinks where rtl_RecID=bkm_RecID and rtl_TagID in ('.join(',', $this->value).'))';
                } else if (! $this->wg_value) {
                    // this runs faster (like TEN TIMES FASTER) - think it's to do with the join
                    $query=$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                    . 'where kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
                    $first_value = true;
                    foreach ($this->value as $value) {
                        if (! $first_value) $query .= 'or ';
                        if (is_numeric($value)) {
                            $query .= 'rtl_TagID='.intval($value).' ';
                        } else {
                            $query .=     ($this->parent->exact
                                ? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                        }
                        $first_value = false;
                    }
                    $query .=              ') and kwd.tag_UGrpID='.$pquery->currUserID.') ';
                } else {
                    $query=$not . 'exists (select * from sysUGrps, usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                    . 'where ugr_ID=tag_UGrpID and kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
                    for ($i=0; $i < count($this->value); ++$i) {
                        if ($i > 0) $query .= 'or ';

                        $value = $this->value[$i];
                        $wg_value = $this->wg_value[$i];

                        if ($wg_value) {
                            $query .= '(';
                            $query .=      ($this->parent->exact? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                            $query .=      ' and ugr_Name = "'.$mysqli->real_escape_string($wg_value).'") ';
                        } else {
                            $query .=      ($this->parent->exact? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                        }
                    }
                    $query .= ')) ';
                }
            } else {
                if (! $this->wg_value) {
                    $query = $not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                    . 'where kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
                    $first_value = true;
                    foreach ($this->value as $value) {
                        if (! $first_value) $query .= 'or ';
                        if (is_numeric($value)) {
                            $query .= "kwd.tag_ID=$value ";
                        } else {
                            $query .=      ($this->parent->exact? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                        }
                        $first_value = false;
                    }
                    $query .= ')) ';
                } else {
                    $query = $not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID left join sysUGrps on ugr_ID=tag_UGrpID '
                    . 'where kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
                    for ($i=0; $i < count($this->value); ++$i) {
                        if ($i > 0) $query .= 'or ';

                        $value = $this->value[$i];
                        $wg_value = $this->wg_value[$i];

                        if ($wg_value) {
                            $query .= '(';
                            $query .=      ($this->parent->exact? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                            $query .= ' and ugr_Name = "'.$mysqli->real_escape_string($wg_value).'") ';
                        } else {
                            if (is_numeric($value)) {
                                $query .= "kwd.tag_ID=$value ";
                            } else {
                                $query .= '(';
                                $query .=      ($this->parent->exact? 'kwd.tag_Text = "'.$mysqli->real_escape_string($value).'" '
                                    : 'kwd.tag_Text like "'.$mysqli->real_escape_string($value).'%" ');
                                $query .= ' and ugr_ID is null) ';
                            }
                        }
                    }
                    $query .= ')) ';
                }
            }

            return $query;
        }
    }


    class BibIDPredicate extends Predicate {
        function makeSQL() {
            $not = ($this->parent->negate)? 'not' : '';
            return "TOPBIBLIO.rec_ID $not in (" . join(',', array_map('intval', explode(',', $this->value))) . ')';
        }
    }

    
    //
    // this is special case 
    // find records that are linked from parent/top query         (resource field in parent record = record ID)
    // 
    // 1. take parent query from parent object
    //
    class LinkedFromParentPredicate extends Predicate {
        function makeSQL() {
            
            $rty_ID = null;
            $dty_ID = null;
            //if value is specified we search linked from specific source type and field
            if($this->value){
                $vals = explode('-', $this->value);
                if(count($vals)>1){
                    $rty_ID = $vals[0];
                    $dty_ID = $vals[1];
                }else{
                    $rty_ID = $vals[0];
                    $dty_ID = '';
                }
            }
/*
            //additions for FROM and WHERE 
            if($rty_ID){
            
                if($dty_ID){ 
                    //linked from specific record and fields
                    $add_from =  'recDetails bd ';
                    $add_where =  'bd.dtl_RecID=rd.rec_ID and bd.dtl_Value=TOPBIBLIO.rec_ID and rd.rec_RecTypeID='.$rty_ID.' and bd.dtl_DetailTypeID='.$dty_ID;
                }else{ 
                    //linked from specific record type (by any field)
                    $add_from =  'defDetailTypes, recDetails bd ';
                    $add_where = 'bd.dtl_RecID=rd.rec_ID and bd.dtl_Value=TOPBIBLIO.rec_ID and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" and rd.rec_RecTypeID='.$rty_ID.' '; 
                }
            }else{ //any linked from
                    $add_from =  'defDetailTypes, recDetails bd ';
                    $add_where = 'bd.dtl_RecID=rd.rec_ID and bd.dtl_Value=TOPBIBLIO.rec_ID and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" ';
            }

           $select = 'exists (select bd.dtl_RecID  ';
*/
            //NEW  ---------------------------
            $add_from  = 'recLinks rl ';
            $add_where = (($rty_ID) ?'rd.rec_RecTypeID='.$rty_ID.' and ':'') 
                         . ' rl.rl_SourceID=rd.rec_ID and '
                         . (($dty_ID) ?'rl.rl_DetailTypeID='.$dty_ID :'rl.rl_RelationID is null' );

            $select = 'TOPBIBLIO.rec_ID in (select rl.rl_TargetID ';
           
            
            $pquery = &$this->getQuery();
            if ($pquery->parentquery){
                
                $query = $pquery->parentquery;
                //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

                $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
                $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
                $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
                $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);
                
                $select = $select.$query["from"].', '.$add_from.' WHERE '.$query["where"].' and '.$add_where.' '.$query["sort"].$query["limit"].$query["offset"].')';

//error_log("select: ".$select);                
                
            }else{
               $select = $select.' FROM Records rd,'.$add_from.' WHERE '.$add_where.')';
            }
            
            return $select; 
        }
    }
    
    
    //
    // find records that are linked (have pointers) to  parent/top query 
    
    //  resource detail value of parent query equals to record id
    // 
    class LinkedToParentPredicate extends Predicate {
        function makeSQL() {
            
            $rty_ID = null;
            $dty_ID = null;
            //if value is specified we search linked from specific source type and field
            if($this->value){
                $vals = explode('-', $this->value);
                if(count($vals)>1){
                    $rty_ID = $vals[0];
                    $dty_ID = $vals[1];
                }else{
                    $rty_ID = $vals[0];
                    $dty_ID = '';
                }
            }

/*            
            //additions for FROM and WHERE 
            if($rty_ID){
            
                if($dty_ID){ 
                    //linked from specific record and fields
                    $add_from =  'recDetails bd ';
                    $add_where = 'TOPBIBLIO.rec_ID = bd.dtl_RecID and rd.rec_RecTypeID='.$rty_ID.' and bd.dtl_DetailTypeID='.$dty_ID.' and bd.dtl_Value=rd.rec_ID ';
                    
                }else{ 
                    //linked to specific record type (by any field)
                    $add_from =  'defDetailTypes, recDetails bd ';
                    $add_where = 'TOPBIBLIO.rec_ID = bd.dtl_RecID and rd.rec_RecTypeID='.$rty_ID.' and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" and bd.dtl_Value=rd.rec_ID ';
                }
            }else{ //any linked to
                    $add_from =  'defDetailTypes, recDetails bd ';
                    $add_where = 'TOPBIBLIO.rec_ID = bd.dtl_RecID and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" and bd.dtl_Value=rd.rec_ID ';
            }
            
            $select = 'exists (select bd.dtl_RecID  ';
*/            
            
            //NEW  ---------------------------
            $add_from  = 'recLinks rl ';
            $add_where = (($rty_ID) ?'rd.rec_RecTypeID='.$rty_ID.' and ':'') 
                         . ' rl.rl_TargetID=rd.rec_ID and '
                         . (($dty_ID) ?'rl.rl_DetailTypeID='.$dty_ID :'rl.rl_RelationID is null' );

            $select = 'TOPBIBLIO.rec_ID in (select rl.rl_SourceID ';
            
            
            $pquery = &$this->getQuery();
            if ($pquery->parentquery){
                
                $query = $pquery->parentquery;
                //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

                $query["from"]  = str_replace('TOPBIBLIO', 'rd', $query["from"]);
                $query["from"]  = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);
                $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
                $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
                
                $select = $select.$query["from"].', '.$add_from.' WHERE '.$query["where"].' and '.$add_where.' '.$query["sort"].$query["limit"].$query["offset"].')';

//error_log("select: ".$select);                
                
            }else{
               $select = $select.' FROM Records rd,'.$add_from.' WHERE '.$add_where.')';

            }
            
            return $select; 
        }
    }
    
    
    //
    // find records that are related  from (targets) parent/top query 
    // 
    // 1. take parent query from parent object
    //
    // rule sample: t:10 relatedfrom:29-3318    
    //                          [source rectype]-[relation type]
    //
    class RelatedFromParentPredicate extends Predicate {
        function makeSQL() {
            
            $source_rty_ID = null;
            //if value is specified we search linked from specific source type and field
            if($this->value){
                $vals = explode('-', $this->value);
                if(count($vals)>1){
                    $source_rty_ID = $vals[0];
                    $relation_type_ID = $vals[1];
                }else{
                    $source_rty_ID = $vals[0];
                    $relation_type_ID = '';
                }
            }
 /*
            //additions for FROM and WHERE 
            if($source_rty_ID){  //source record type is defined
            
            
                if($relation_type_ID){
                    $add_from =  'recRelationshipsCache rel, recDetails bd ';
                    $add_where =  ' rel.rrc_SourceRecID=rd.rec_ID and rel.rrc_TargetRecID=TOPBIBLIO.rec_ID and rd.rec_RecTypeID='.$source_rty_ID
                                  .'  and rel.rrc_RecID=bd.dtl_RecID and bd.dtl_DetailTypeID='.DT_RELATION_TYPE.' and  bd.dtl_Value='.$relation_type_ID;         //@todo  6 NEED TO CHANGE TO CONST
                }else{
                    $add_from =  'recRelationshipsCache rel ';
                    $add_where =  ' rel.rrc_SourceRecID=rd.rec_ID and rel.rrc_TargetRecID=TOPBIBLIO.rec_ID and rd.rec_RecTypeID='.$source_rty_ID;
                }
            

            }else{ //any related from
                    $add_from =  'recRelationshipsCache rel ';
                    $add_where = 'rel.rrc_TargetRecID=rd.rec_ID and rel.rrc_TargetRecID=TOPBIBLIO.rec_ID ';
            }
            
            $select = 'exists (select rel.rrc_TargetRecID  ';
*/            
            
            //NEW  ---------------------------
            $add_from  = 'recLinks rl ';
            $add_where = (($source_rty_ID) ?'rd.rec_RecTypeID='.$source_rty_ID.' and ':'') 
                         . ' rl.rl_SourceID=rd.rec_ID and '
                         . (($relation_type_ID) ?'rl.rl_RelationTypeID='.$relation_type_ID :'rl.rl_RelationID is not null' );

            $select = 'TOPBIBLIO.rec_ID in (select rl.rl_TargetID ';
           
            
            $pquery = &$this->getQuery();
            if ($pquery->parentquery){
                
                $query = $pquery->parentquery;
                //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

                $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
                $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
                $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
                $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);
                
                $select = $select.$query["from"].', '.$add_from.' WHERE '.$query["where"].' and '.$add_where.' '.$query["sort"].$query["limit"].$query["offset"].')';

//error_log("select: ".$select);                
                
            }else{
                
               $select = $select.' FROM Records rd,'.$add_from.' WHERE '.$add_where.')';
            }
            
            return $select; 
        }
    }

    //
    // find records that are related to (sources) parent/top query 
    // 
    // 1. take parent query from parent object
    //
    // rule sample: t:10 relatedfrom:29-3318    
    //                          [source rectype]-[relation type]
    //
    class RelatedToParentPredicate extends Predicate {
        function makeSQL() {
            
            $source_rty_ID = null;
            //if value is specified we search linked from specific source type and field
            if($this->value){
                $vals = explode('-', $this->value);
                if(count($vals)>1){
                    $source_rty_ID = $vals[0];
                    $relation_type_ID = $vals[1];
                }else{
                    $source_rty_ID = $vals[0];
                    $relation_type_ID = '';
                }
            }
/*
            //additions for FROM and WHERE 
            if($source_rty_ID){  //source record type is defined
            
            
                if($relation_type_ID){
                    $add_from =  'recRelationshipsCache rel, recDetails bd ';
                    $add_where =  ' rel.rrc_TargetRecID=rd.rec_ID and rel.rrc_SourceRecID=TOPBIBLIO.rec_ID and rd.rec_RecTypeID='.$source_rty_ID
                                  .'  and rel.rrc_RecID=bd.dtl_RecID and bd.dtl_DetailTypeID='.DT_RELATION_TYPE.' and  bd.dtl_Value='.$relation_type_ID;         //@todo  6 NEED TO CHANGE TO CONST
                }else{
                    $add_from =  'recRelationshipsCache rel ';
                    $add_where =  ' rel.rrc_TargetRecID=rd.rec_ID and rel.rrc_SourceRecID=TOPBIBLIO.rec_ID and rd.rec_RecTypeID='.$source_rty_ID;
                }
            

            }else{ //any related TO
                    $add_from =  'recRelationshipsCache rel ';
                    $add_where = 'rel.rrc_TargetRecID=rd.rec_ID and rel.rrc_SourceRecID=TOPBIBLIO.rec_ID ';
            }

            $select = 'exists (select rel.rrc_SourceRecID  ';
*/

            //NEW  ---------------------------
            $add_from  = 'recLinks rl ';
            $add_where = (($source_rty_ID) ?'rd.rec_RecTypeID='.$source_rty_ID.' and ':'') 
                         . ' rl.rl_TargetID=rd.rec_ID and '
                         . (($relation_type_ID) ?'rl.rl_RelationTypeID='.$relation_type_ID :'rl.rl_RelationID is not null' );

            $select = 'TOPBIBLIO.rec_ID in (select rl.rl_SourceID ';

            
            $pquery = &$this->getQuery();
            if ($pquery->parentquery){
                
                $query = $pquery->parentquery;
                //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

                $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
                $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
                $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
                $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);
                
                $select = $select.$query["from"].', '.$add_from.' WHERE '.$query["where"].' and '.$add_where.' '.$query["sort"].$query["limit"].$query["offset"].')';

//error_log("select: ".$select);                
                
            }else{
                
               $select = $select.' FROM Records rd,'.$add_from.' WHERE '.$add_where.')';
            }
            
            return $select; 
        }
    }


    
    class AllLinksPredicate  extends Predicate {
        function makeSQL() {
                        
            $rty_ID = null;
            //if value is specified we search linked from specific source type and field
            if($this->value){
                $vals = explode('-', $this->value);
                if(count($vals)>1){
                    $rty_ID = $vals[0];
                    $rty_ID = $vals[1];
                }
            }

/*                        
            $add_select = 'exists (select rd.rec_ID  ';
                            
            //parent query - rd (Records) and bd (Details)
            //linked from  - bd.dtl_Value=TOPBIBLIO.rec_ID and bd.dtl_RecID=rd.rec_ID
            //linked to      TOPBIBLIO.rec_ID=bd.dtl_RecID and bd.dtl_Value=rd.rec_ID
    
            $add_from =  'defDetailTypes, recDetails bd ';
            $add_where = '((bd.dtl_RecID=rd.rec_ID and bd.dtl_Value=TOPBIBLIO.rec_ID) or '
                         .'(TOPBIBLIO.rec_ID=bd.dtl_RecID and bd.dtl_Value=rd.rec_ID and TOPBIBLIO.rec_RecTypeID!=1)) '
                                .'and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" ';
            
            if($rty_ID){
                $add_where = $add_where . ' and rd.rec_RecTypeID='.$rty_ID;
            }

            //------------------
            $add_from_rel =  'recRelationshipsCache rel ';
            $add_where_rel = '((rel.rrc_SourceRecID=rd.rec_ID and rel.rrc_TargetRecID=TOPBIBLIO.rec_ID) or '
                             .'(rel.rrc_TargetRecID=rd.rec_ID and rel.rrc_SourceRecID=TOPBIBLIO.rec_ID))';
            if($rty_ID){  //source record type is defined
                    $add_where_rel = $add_where_rel.' and rd.rec_RecTypeID='.$rty_ID;
            }
*/
            
            //NEW  -------------------
            $add_from  = 'recLinks rl ';
            $add_where = ($rty_ID)?'rd.rec_RecTypeID='.$rty_ID.' and ':'';

            $add_where = $add_where.'rl.rl_TargetID=rd.rec_ID';
            $add_select = 'TOPBIBLIO.rec_ID in (select rl.rl_SourceID ';

            $add_from2  = 'recLinks rl ';
            $add_where2 = ($rty_ID)?'rd.rec_RecTypeID='.$rty_ID.' and ':'';
            
            $add_where2 = $add_where2.'rl.rl_SourceID=rd.rec_ID';
            $add_select2 = 'TOPBIBLIO.rec_ID in (select rl.rl_TargetID ';
            
            $pquery = &$this->getQuery();
            if ($pquery->parentquery){
                
                $query = $pquery->parentquery;
                //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

                $query["from"]  = str_replace('TOPBIBLIO', 'rd', $query["from"]);
                $query["from"]  = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);
                $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
                $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
                
                $select1 = $add_select.$query["from"].', '.$add_from.' WHERE '.$query["where"].' and '.$add_where.' '.$query["sort"].$query["limit"].$query["offset"].')';

                //create  

                $select2 = $add_select2.$query["from"].', '.$add_from2.' WHERE '.$query["where"].' and '.$add_where2.' '.$query["sort"].$query["limit"].$query["offset"].')';

                
            }else{
                $select1 = $add_select.' FROM Records rd,'.$add_from.' WHERE '.$add_where.')';
                $select2 = $add_select2.' FROM Records rd,'.$add_from2.' WHERE '.$add_where2.')';
            }

            
            $select = '(' . $select1 . ' OR ' .$select2. ')';
            //$select = $select1;

//error_log("select: ".$select);                
            
            return $select; 
        }        
    }
/*

DROP TABLE IF EXISTS recLinks;

CREATE TABLE recLinks (
  rl_ID   int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  rl_SourceID int(10) unsigned NOT NULL COMMENT 'Source record ID',
  rl_TargetID int(10) unsigned NOT NULL COMMENT 'Target record ID',
  rl_RelationID int(10) unsigned        COMMENT 'Realtionship record ID',
  rl_RelationTypeID int(10) unsigned    COMMENT 'Realtionship type - defTerms.trm_ID',
  rl_DetailTypeID int(10) unsigned      COMMENT 'Pointer (Resource) detail type ID',
  rl_DetailID int(10) unsigned          COMMENT 'Pointer Detail type',
  PRIMARY KEY  (rl_ID),
  KEY rl_SourcePtrKey (rl_SourceID),
  KEY rl_TargetPtrKey (rl_TargetID),
  KEY rl_RelationKey (rl_RelationID),
  KEY rl_DetailKey (rl_DetailID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='A cache for records links (pointers and relationships) to speed access';

-- add relationships
insert into recLinks (rl_SourceID, rl_TargetID, rl_RelationID, rl_RelationTypeID)
select d1.dtl_Value, d2.dtl_Value, rd.rec_ID, d3.dtl_Value from Records rd 
left join recDetails d1 on d1.dtl_RecID = rd.rec_ID and d1.dtl_DetailTypeID = 7
left join recDetails d2 on d2.dtl_RecID = rd.rec_ID and d2.dtl_DetailTypeID = 5
left join recDetails d3 on d3.dtl_RecID = rd.rec_ID and d3.dtl_DetailTypeID = 6
where rd.rec_RecTypeID=1 and d1.dtl_Value is not null and d2.dtl_Value is not null;

-- add pointers
insert into recLinks (rl_SourceID, rl_TargetID, rl_DetailTypeID, rl_DetailID)
select rd.rec_ID, bd.dtl_Value, dty_ID, bd.dtl_ID from Records rd, defDetailTypes, recDetails bd 
where rd.rec_RecTypeID!=1 and bd.dtl_RecID=rd.rec_ID and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" and bd.dtl_Value is not null; 

*/    
    
    //
    // find records that have pointed records
    // 
    class LinkToPredicate extends Predicate {
        function makeSQL() {
            if ($this->value) {
                return 'exists (select * from defDetailTypes, recDetails bd '
                . 'where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource" '
                . '  and bd.dtl_Value in (' . join(',', array_map('intval', explode(',', $this->value))) . '))';
            }
            else {
                return 'exists (select * from defDetailTypes, recDetails bd '
                . 'where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource")';
            }
        }
    }

    //
    // find records that are pointed (targets)
    // search if parents(source) records exist
    //
    class LinkedToPredicate extends Predicate {
        function makeSQL() {
            if ($this->value) {
                return 'exists (select * from defDetailTypes, recDetails bd '
                . 'where bd.dtl_RecID in (' . join(',', array_map('intval', explode(',', $this->value))) .') and dty_ID=dtl_DetailTypeID and dty_Type="resource" '
                . '  and bd.dtl_Value=TOPBIBLIO.rec_ID)';
            }
            else {
                return 'exists (select * from defDetailTypes, recDetails bd '
                . 'where bd.dtl_Value=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource")';
            }
        }
    }


    class RelatedToPredicate extends Predicate {
        function makeSQL() {
            if ($this->value) {
                $ids = "(" . join(",", array_map("intval", explode(",", $this->value))) . ")";
                return "exists (select * from recRelationshipsCache where (rrc_TargetRecID=TOPBIBLIO.rec_ID and rrc_SourceRecID in $ids)
                or (rrc_SourceRecID=TOPBIBLIO.rec_ID and rrc_TargetRecID in $ids))";
            }
            else {
                /* just want something that has a relation */
                return "TOPBIBLIO.rec_ID in (select distinct rrc_TargetRecID from recRelationshipsCache union select distinct rrc_SourceRecID from recRelationshipsCache)";
            }
        }
    }


    class RelationsForPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            $ids = "(" . join(",", array_map("intval", explode(",", $this->value))) . ")";
            /*
            return "exists (select * from recRelationshipsCache where ((rrc_TargetRecID=TOPBIBLIO.rec_ID or rrc_RecID=TOPBIBLIO.rec_ID) and rrc_SourceRecID=$id)
            or ((rrc_SourceRecID=TOPBIBLIO.rec_ID or rrc_RecID=TOPBIBLIO.rec_ID) and rrc_TargetRecID=$id))";
            */
            /*
            return "TOPBIBLIO.rec_ID in (select rrc_TargetRecID from recRelationshipsCache where rrc_SourceRecID=$id
            union select rrc_SourceRecID from recRelationshipsCache where rrc_TargetRecID=$id
            union select rrc_RecID    from recRelationshipsCache where rrc_TargetRecID=$id or rrc_SourceRecID=$id)";
            */
            /*
            return "exists (select * from bib_relationships2 where ((rrc_TargetRecID=TOPBIBLIO.rec_ID or rrc_RecID=TOPBIBLIO.rec_ID) and rrc_SourceRecID=$id))";
            */
            /* Okay, this isn't the way I would have done it initially, but it benchmarks well:
            * All of the methods above were taking 4-5 seconds.
            * Putting recRelationshipsCache into the list of tables at the top-level gets us down to about 0.8 seconds, which is alright, but disruptive.
            * Coding the 'relationsfor:' predicate as   TOPBIBLIO.rec_ID in (select distinct rec_ID from recRelationshipsCache where (rrc_RecID=TOPBIBLIO.rec_ID etc etc))
            *   gets us down to about 2 seconds, but it looks like the optimiser doesn't really pick up on what we're doing.
            * Fastest is to do a SEPARATE QUERY to get the record IDs out of the bib_relationship table, then pass it back encoded in the predicate.
            * Certainly not the most elegant way to do it, but the numbers don't lie.
            */
            $res = $mysqli->query("select group_concat( distinct rec_ID ) from Records, recRelationshipsCache where (rrc_RecID=rec_ID or rrc_TargetRecID=rec_ID or rrc_SourceRecID=rec_ID)
                and (rrc_SourceRecID in $ids or rrc_TargetRecID in $ids) and rec_ID not in $ids");
            $ids = $res->fetch_row();  
            $ids = $ids[0];
            
            if (! $ids) return "0";
            else return "TOPBIBLIO.rec_ID in ($ids)";
        }
    }


    class AfterPredicate extends Predicate {
        function makeSQL() {
            $timestamp = strtotime($this->value);
            if ($timestamp  &&  $timestamp != -1) {
                $not = ($this->parent->negate)? 'not' : '';
                $datestamp = date('Y-m-d H:i:s', $timestamp);
                return "$not TOPBIBLIO.rec_Modified >= '$datestamp'";
            }
            return '1';
        }
    }


    class BeforePredicate extends Predicate {
        function makeSQL() {
            $timestamp = strtotime($this->value);
            if ($timestamp  &&  $timestamp != -1) {
                $not = ($this->parent->negate)? 'not' : '';
                $datestamp = date('Y-m-d H:i:s', $timestamp);
                return "$not TOPBIBLIO.rec_Modified <= '$datestamp'";
            }
            return '1';
        }
    }


    class DatePredicate extends Predicate {
        var $col;

        function DatePredicate(&$parent, $col, $value) {
            $this->col = $col;
            parent::Predicate($parent, $value);
        }

        function makeSQL() {
            $col = $this->col;

            if($this->isDateTime()){
                $not = ($this->parent->negate)? 'not' : '';    
                $s = $this->makeDateClause();
                if(strpos($s, "between")===0){
                    return " $col $not ".$s;
                }else{
                    return " $not $col ".$s;
                }
            }
            return '1';
        }
    }

    class DateAddedPredicate extends DatePredicate {
        function DateAddedPredicate(&$parent, $value) {
            parent::DatePredicate($parent, 'TOPBIBLIO.rec_Added', $value);
        }
    }

    class DateModifiedPredicate extends DatePredicate {
        function DateModifiedPredicate(&$parent, $value) {
            parent::DatePredicate($parent, 'TOPBIBLIO.rec_Modified', $value);
        }
    }


    class WorkgroupPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $eq = ($this->parent->negate)? '!=' : '=';
            if (is_numeric($this->value)) {
                return "TOPBIBLIO.rec_OwnerUGrpID $eq ".intval($this->value);
            }
            else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
                $in = ($this->parent->negate)? 'not in' : 'in';
                return "TOPBIBLIO.rec_OwnerUGrpID $in (" . $this->value . ")";
            }
            else {
                return "TOPBIBLIO.rec_OwnerUGrpID $eq (select grp.ugr_ID from sysUGrps grp where grp.ugr_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
            }
        }
    }


    class LatitudePredicate extends Predicate {
        function makeSQL() {
            $op = '';
            if ($this->parent->lessthan) {
                $op = ($this->parent->negate)? '>=' : '<';
            } else if ($this->parent->greaterthan) {
                $op = ($this->parent->negate)? '<=' : '>';
            }

            if ($op[0] == '<') {
                // see if the northernmost point of the bounding box lies south of the given latitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and y( PointN( ExteriorRing( Envelope(bd.dtl_Geo) ), 4 ) ) $op " . floatval($this->value) . " limit 1)";
            }
            else if ($op[0] == '>') {
                // see if the SOUTHERNmost point of the bounding box lies north of the given latitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and y( StartPoint( ExteriorRing( Envelope(bd.dtl_Geo) ) ) ) $op " . floatval($this->value) . " limit 1)";

            }
            else if ($this->parent->exact) {
                $op = $this->parent->negate? "!=" : "=";
                // see if there is a Point with this exact latitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null and bd.dtl_Value = 'p'
                and y(bd.dtl_Geo) $op " . floatval($this->value) . " limit 1)";
            }
            else {
                // see if this latitude passes through the bounding box
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and ".floatval($this->value)." between y( StartPoint( ExteriorRing( Envelope(bd.dtl_Geo) ) ) )
                and y( PointN( ExteriorRing( Envelope(bd.dtl_Geo) ), 4 ) ) limit 1)";
            }
        }
    }


    class LongitudePredicate extends Predicate {
        function makeSQL() {
            $op = '';
            if ($this->parent->lessthan) {
                $op = ($this->parent->negate)? '>=' : '<';
            } else if ($this->parent->greaterthan) {
                $op = ($this->parent->negate)? '<=' : '>';
            }

            if ($op[0] == '<') {
                // see if the westernmost point of the bounding box lies east of the given longitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and x( PointN( ExteriorRing( Envelope(bd.dtl_Geo) ), 4 ) ) $op " . floatval($this->value) . " limit 1)";
            }
            else if ($op[0] == '>') {
                // see if the EASTERNmost point of the bounding box lies west of the given longitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and x( StartPoint( ExteriorRing( Envelope(bd.dtl_Geo) ) ) ) $op " . floatval($this->value) . " limit 1)";

            }
            else if ($this->parent->exact) {
                $op = $this->parent->negate? "!=" : "=";
                // see if there is a Point with this exact longitude
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null and bd.dtl_Value = 'p'
                and x(bd.dtl_Geo) $op " . floatval($this->value) . " limit 1)";
            }
            else {
                // see if this longitude passes through the bounding box
                return "exists (select * from recDetails bd
                where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
                and ".floatval($this->value)." between x( StartPoint( ExteriorRing( Envelope(bd.dtl_Geo) ) ) )
                and x( PointN( ExteriorRing( Envelope(bd.dtl_Geo) ), 2 ) ) limit 1)";
            }
        }
    }


    class HHashPredicate extends Predicate {
        function makeSQL() {
            global $mysqli;
            
            $op = '';
            if ($this->parent->exact) {
                $op = $this->parent->negate? "!=" : "=";
                return "TOPBIBLIO.rec_Hash $op '" . $mysqli->real_escape_string($this->value) . "'";
            }
            else {
                $op = $this->parent->negate? " not like " : " like ";
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
    if (@$_REQUEST['r']) $q .= 't:' . intval($_REQUEST['r']) . ' ';    // note: defRecTypes was 'r', now 't' (for TYPE!)
    if (@$_REQUEST['uid']) $q .= 'usr:' . intval($_REQUEST['uid']) . ' ';
    if (@$_REQUEST['bi']) $q .= 'id:"' . $_REQUEST['bi'] . '" ';
    if (@$_REQUEST['a']) $q .= 'any:"' . $_REQUEST['a'] . '" ';

    $_REQUEST['q'] = $q;
    }
    */

?>
