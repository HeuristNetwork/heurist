<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('BOOKMARK', 'bookmark');
define('BIBLIO', 'biblio');
define('BOTH', 'both');

define('SORT_POPULARITY', 'p');
define('SORT_RATING', 'r');
define('SORT_URL', 'u');
define('SORT_MODIFIED', 'm');
define('SORT_ADDED', 'a');
define('SORT_TITLE', 't');

function prepareQuery($params, $squery, $search_type, $detailsTable, $where, $order=null, $limit=null)
{
            $squery = REQUEST_to_query($squery, $search_type, $params);
            //remove order by
            $pos = strpos($squery," order by ");
            if($pos>0){
                $squery = substr($squery, 0, $pos);
            }

            //$squery = str_replace(" where ", ",".$detailsTable." where ", $squery);
            $squery = preg_replace('/ where /', $detailsTable." where ", $squery, 1);

            //add our where clause and limit
            if($where){
                $squery = $squery.$where;
            }
            if($order){
                $squery = $squery." order by ".$order;
            }
            if($limit){
                $squery = $squery." limit ".$limit;
            }

            return $squery;
}


function parse_query($search_type, $text, $sort_order='', $wg_ids=NULL, $publicOnly = false) {
	// wg_ids is a list of the workgroups we can access; records records marked with a rec_OwnerUGrpID not in this list are omitted
	// remove any  lone dashes outside matched quotes.
	$text = preg_replace('/- (?=[^"]*(?:"[^"]*"[^"]*)*$)|-\s*$/', ' ', $text);
	// divide the query into dbl-quoted and other (note a dash(-) in front of a string is preserved and means negate)
	preg_match_all('/(-?"[^"]+")|([^" ]+)/',$text,$matches);
	$preProcessedQuery = "";
	$connectors=array(":",">","<","=",",");
	foreach ($matches[0] as $queryPart) {
		//if the query part is not a dbl-quoted string (ignoring a preceeding dash and spaces)
		//necessary since we want double quotes to allow all characters
		if (!preg_match('/^\s*-?".*"$/',$queryPart)) {
		// clean up the query.
		// liposuction out all the non-kocher characters
		// (this means all punctuation except -, _, :, ', ", = and ,  ...?)
			$queryPart = preg_replace('/[\000-\041\043-\046\050-\053\073\077\100\133\135\136\140\173-\177]+/s', ' ', $queryPart);
		}
		//reconstruct the string
		$addSpace = $preProcessedQuery != "" && !in_array($preProcessedQuery[strlen($preProcessedQuery)-1],$connectors) && !in_array($queryPart[0],$connectors);
		$preProcessedQuery .= ($addSpace ? " ":"").$queryPart;
	}
	$query = new Query($search_type, $preProcessedQuery, $publicOnly);
	$query->addWorkgroupRestriction($wg_ids);
	$q = $query->makeSQL();

	if ($query->sort_phrases) {
		// handled in Query logic
	} else if (preg_match('/^f:(\d+)/', $sort_order, $matches)) {
		$q .= ' order by ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID='.$matches[1].' order by link.rec_Title limit 1), "~~"), rec_Title';
	} else {
		if ($search_type == BOOKMARK) {
			switch ($sort_order) {
			    case SORT_POPULARITY:
				$q .= ' order by rec_Popularity desc, rec_Added desc'; break;
			    case SORT_RATING:
				$q .= ' order by bkm_Rating desc'; break;
			    case SORT_URL:
				$q .= ' order by rec_URL is null, rec_URL'; break;
			    case SORT_MODIFIED:
				$q .= ' order by bkm_Modified desc'; break;
			    case SORT_ADDED:
				$q .= ' order by bkm_Added desc'; break;
			    case SORT_TITLE: default:
				$q .= ' order by rec_Title = "", rec_Title';
			}
		} else {
			switch ($sort_order) {
			    case SORT_POPULARITY:
				$q .= ' order by rec_Popularity desc, rec_Added desc'; break;
			    case SORT_URL:
				$q .= ' order by rec_URL is null, rec_URL'; break;
			    case SORT_MODIFIED:
				$q .= ' order by rec_Modified desc'; break;
			    case SORT_ADDED:
				$q .= ' order by rec_Added desc'; break;
			    case SORT_TITLE: default:
				$q .= ' order by rec_Title = "", rec_Title';
			}
		}
	}
	return $q;
}


class Query {
	var $search_type;

	var $or_limbs;
	var $sort_phrases;
	var $sort_tables;

	var $workgroups;

	function Query($search_type, $text, $publicOnly, $absoluteStrQuery = false) {
		$this->search_type = $search_type;
		$this->recVisibilityType = $publicOnly;
		$this->absoluteStrQuery = $absoluteStrQuery;
		$this->or_limbs = array();
		$this->sort_phrases = array();
		$this->sort_tables = array();
		$this->workgroups = array();

        // Find any 'vt:' phrases in the query, and pull them out.
        while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(vt:(?:f:|field:)?"[^"]+"\\S*|vt:\\S*)/', $text, $matches)) {
            $this->addVisibilityTypeRestriction(substr($matches[2],3));
            $text = preg_replace('/\bvt:\S+/i', '', $text);
            //$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
        }

		// Find any 'sortby:' phrases in the query, and pull them out.
		// "sortby:..." within double quotes is regarded as a search term, and we don't remove it here
		while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(sortby:(?:f:|field:)?"[^"]+"\\S*|sortby:\\S*)/', $text, $matches)) {
			$this->addSortPhrase($matches[2]);
			$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
		}

		// According to WWGD, OR is the top-level delimiter (yes, more top-level than double-quoted text)
		$or_texts = preg_split('/\\b *OR *\\b/i', $text);
		for ($i=0; $i < count($or_texts); ++$i)
			if ($or_texts[$i]) $this->addOrLimb($or_texts[$i]);	// NO LONGER collapse uppercase -> lowercase ... let's wait till PHP understands UTF-8 (mysql match ignores case anyway)
	}

	function addOrLimb($text) {
		$this->or_limbs[] = new OrLimb($this, $text);
	}

	function addSortPhrase($text) {
		array_unshift($this->sort_phrases, new SortPhrase($this, $text));
	}

	function addWorkgroupRestriction($wg_ids) {
		if ($wg_ids) $this->workgroups = $wg_ids;
	}

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
		$where_clause = '';

		$or_clauses = array();
		for ($i=0; $i < count($this->or_limbs); ++$i) {
			$new_sql = $this->or_limbs[$i]->makeSQL();
			array_push($or_clauses, '(' . $new_sql . ')');
		}
		sort($or_clauses);	// alphabetise
		$where_clause = join(' or ', $or_clauses);

		$sort_clause = '';
		$sort_clauses = array();
		for ($i=0; $i < count($this->sort_phrases); ++$i) {
			@list($new_sql, $new_sig, $new_tables) = $this->sort_phrases[$i]->makeSQL();

			if (! @$sort_clauses[$new_sig]) {	// don't repeat identical sort clauses
				if ($sort_clause) $sort_clause .= ', ';

				$sort_clause .= $new_sql;
				if ($new_tables) array_push($this->sort_tables, $new_tables);

				$sort_clauses[$new_sig] = 1;
			}
		}
		if ($sort_clause) $sort_clause = ' order by ' . $sort_clause;

		if ($this->search_type == BOOKMARK)
			$from_clause = 'from usrBookmarks TOPBKMK left join Records TOPBIBLIO on bkm_recID=rec_ID ';
		else
			$from_clause = 'from Records TOPBIBLIO left join usrBookmarks TOPBKMK on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id().' ';

		$from_clause .= join(' ', $this->sort_tables);	// sorting may require the introduction of more tables


		if ($this->search_type == BOOKMARK) {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'bkm_UGrpID = ' . get_user_id() . ' and (rec_FlagTemporary is null or not rec_FlagTemporary) ';
		} else if ($this->search_type == BIBLIO) {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'bkm_UGrpID is null and not rec_FlagTemporary ';
		} else {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'not rec_FlagTemporary ';
		}
		array_push($this->workgroups,0); // be sure to include the generic everybody workgroup

        if(is_bool($this->recVisibilityType)){   //old way - in case vt param is not specified
            $isPublicOnly = $this->recVisibilityType;
		    $where_clause = '('.((is_logged_in() && !$isPublicOnly) ?'rec_OwnerUGrpID='. get_user_id().' or ':'').// this includes non logged in because it returns 0
							    ((is_logged_in() && !$isPublicOnly) ?'not rec_NonOwnerVisibility="hidden"':'rec_NonOwnerVisibility="public"').
							    ((!empty($this->workgroups) && !$isPublicOnly) ?(' or rec_OwnerUGrpID in (' . join(',', $this->workgroups) . '))'):')').
							' and ' . $where_clause;
        }else{
            //hidden - means visible for specific user/group ONLY
            if($this->recVisibilityType=="hidden"){ //pubic only
                $sw = is_logged_in()? 'rec_OwnerUGrpID='. get_user_id():'';
                if(!empty($this->workgroups)){
                    $sw = $sw.($sw!=''?' or ':'').'rec_OwnerUGrpID in (' . join(',', $this->workgroups).')';
                }
                if($sw==''){ //not logged in - cannot show hidden
                    $where2 = '(1=0)';
                }else{
                    $where2 = '('.$sw.') and (rec_NonOwnerVisibility="hidden")';
                }
            }else{
                $where2 = '(rec_NonOwnerVisibility="'.$this->recVisibilityType.'")';
            }
            $where_clause = $where2 . ' and ' . $where_clause;
        }

		return $from_clause . 'where ' . $where_clause . $sort_clause;
	}
}


class OrLimb {
	var $and_limbs;

	var $parent;


	function OrLimb(&$parent, $text) {
		$this->parent = &$parent;
		$this->absoluteStrQuery = $parent->absoluteStrQuery;
		$this->and_limbs = array();
		if (substr_count($text, '"') % 2 != 0) $text .= '"';	// unmatched quote

		if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches)) {
			$and_texts = $matches[0];
			for ($i=0; $i < count($and_texts); ++$i)
				if ($and_texts[$i]) $this->addAndLimb($and_texts[$i]);
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

		$this->pred = $this->createPredicate($text); //was &$this
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

		if ($this->absoluteStrQuery || ! $colon_pos) {	// a colon was either NOT FOUND or AT THE BEGINNING OF THE STRING
			$pred_val = $this->cleanQuotedValue($text);

			if (defined('stype')  &&  stype == 'key')
				return new TagPredicate($this, $pred_val);
			else if (defined('stype')  &&  stype == 'all')
				return new AnyPredicate($this, $pred_val);
			else	// title search is default search
				return new TitlePredicate($this, $pred_val);
		}

		$pred_type = substr($text, 0, $colon_pos);
		if ($pred_type[0] == '-') {	// bit of DWIM here: did the user accidentally put the negate here instead?
			$this->negate = true;
			$pred_type = substr($pred_type, 1);
		}

		$raw_pred_val = substr($text, $colon_pos+1);
		$pred_val = $this->cleanQuotedValue($raw_pred_val);
		if ($pred_val === '""') {	// special case SC100:  xxx:"" becomes equivalent to xxx="" (to find blank values, not just values that contain any string)
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

		    case 'linkto':	// linkto:XXX matches records that have a recDetails reference to XXX
			return new LinkToPredicate($this, $pred_val);
		    case 'linkedto':	// linkedto:XXX matches records that are referenced in one of XXX's bib_details
			return new LinkedToPredicate($this, $pred_val);
		    case 'relatedto':	// relatedto:XXX matches records that are related (via a type-52 record) to XXX
			return new RelatedToPredicate($this, $pred_val);
		    case 'relationsfor':	// relatedto:XXX matches records that are related (via a type-52 record) to XXX, and the relationships themselves
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
		if (defined('stype')  &&  stype == 'key') {	// "default" search should be on tag
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
			if ($this->parent->search_type == BOOKMARK) {
				return array('-(bkm_Rating)'.$scending, 'bkmk_rating', NULL); //SAW Ratings Change todo: test queries with rating
			} else {	// default to popularity sort
				return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', NULL);
			}

		    case 'interest':	//todo: change help file to reflect depricated predicates
		    case 'content':
		    case 'quality':
				return array('rec_Title'.$scending, NULL);	// default to title sort
			break;

		    case 'u': case 'url':
			return array('rec_URL'.$scending, 'rec_URL', NULL);

		    case 'm': case 'modified':
			if ($this->parent->search_type == BOOKMARK) return array('bkm_Modified'.$scending, NULL);
			else return array('rec_Modified'.$scending, 'rec_Modified', NULL);

		    case 'a': case 'added':
			if ($this->parent->search_type == BOOKMARK) return array('bkm_Added'.$scending, NULL);
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
				$res = mysql_query("select dty_Type from defDetailTypes where dty_ID = $field_id");
				$baseType = mysql_fetch_row($res);  $baseType = @$baseType[0];

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
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
				$res = mysql_query("select dty_Type from defDetailTypes where dty_Name = '$field_name'");
				$baseType = mysql_fetch_row($res);  $baseType = @$baseType[0];

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
					$bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
					return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
								"left join defDetailTypes bdt$bd_name on bdt$bd_name.dty_Name='".mysql_real_escape_string($field_name)."' "
								."left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and $bd_name.dtl_DetailTypeID=bdt$bd_name.dty_ID ");
				} else if ($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
					return array(" cast(dtl_Value as unsigned)","dtl_Value is integer",
								"left join defDetailTypes bdtInt on bdtInt.dty_Name='".mysql_real_escape_string($field_name)."' "
								."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
				} else {
					return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
													"if(dty_Type='date',getTemporalDateString(dtl_Value),dtl_Value)) ".
											"from defDetailTypes, recDetails left join Records link on dtl_Value=link.rec_ID ".
											"where dty_Name='".mysql_real_escape_string($field_name)."' and dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=dty_ID ".
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

	function makeSQL() { return '1'; }


	var $query;
	function getQuery() {
		if (! $this->query) {
			$c = &$this->parent;
			while ($c  &&  strtolower(get_class($c)) != 'query')
				$c = &$c->parent;

			$this->query = &$c;
		}
		return $this->query;
	}

	function makeDateClause() {
        
         try{   
            $t2 = new DateTime($this->value);
            $datestamp = $t2->format('Y-m-d H:i:s');
            if ($this->parent->exact) {
                return "= '$datestamp'";
            }else if ($this->parent->lessthan) {
                return "< '$datestamp'";
            }else if ($this->parent->greaterthan) {
                return "> '$datestamp'";
            }else {
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                $match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];  
                }
                else if (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = $t2->format('Y-m'); //date('Y-m', $timestamp);
                }
                else {
                    $date = $t2->format('Y-m-d'); //date('Y-m-d', $timestamp);
                }
                return "like '$date%'";
            }
                            
                            
         } catch (Exception  $e){
                $match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];  
                    return "like '$date%'";
                }
         }                            
         return '=0';
        
	}
}


class TitlePredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		if ($this->parent->exact)
			return $not . 'rec_Title = "'.mysql_real_escape_string($this->value).'"';
		else if ($this->parent->lessthan)
			return $not . 'rec_Title < "'.mysql_real_escape_string($this->value).'"';
		else if ($this->parent->greaterthan)
			return $not . 'rec_Title > "'.mysql_real_escape_string($this->value).'"';
		else
			return 'rec_Title ' . $not . 'like "%'.mysql_real_escape_string($this->value).'%"';
	}
}


class TypePredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_RecTypeID $eq ".intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			// comma-separated list of defRecTypes ids
			$in = ($this->parent->negate)? 'not in' : 'in';
			return "rec_RecTypeID $in (" . $this->value . ")";
		}
		else {
			return "rec_RecTypeID $eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".mysql_real_escape_string($this->value)."' limit 1)";
		}
	}
}


class URLPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		return 'rec_URL ' . $not . 'like "%'.mysql_real_escape_string($this->value).'%"';
	}
}


class NotesPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		if ($query->search_type == BOOKMARK)	// saw TODO change this to check for woot match or full text search
			return '';
		else
			return 'rec_ScratchPad ' . $not . 'like "%'.mysql_real_escape_string($this->value).'%"';
	}
}


class UserPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';
		if (is_numeric($this->value)) {
			return $not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=rec_ID '
			                                                  . ' and bkmk.bkm_UGrpID = ' . intval($this->value) . ')';
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			return $not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=rec_ID '
			                                                  . ' and bkmk.bkm_UGrpID in (' . $this->value . '))';
		}
		else if (preg_match('/^(\D+)\s+(\D+)$/', $this->value,$matches)){	// saw MODIFIED: 16/11/2010 since Realname field was removed.
			return $not . 'exists (select * from usrBookmarks bkmk, '.USERS_DATABASE.'.sysUGrps usr '
			                    . ' where bkmk.bkm_recID=rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
			                      . ' and (usr.ugr_FirstName = "' . mysql_real_escape_string($matches[1]) . '" and usr.ugr_LastName = "' . mysql_real_escape_string($matches[2]) . '"))';
		}
		else {
			return $not . 'exists (select * from usrBookmarks bkmk, '.USERS_DATABASE.'.sysUGrps usr '
			                    . ' where bkmk.bkm_recID=rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
			                      . ' and usr.ugr_Name = "' . mysql_real_escape_string($this->value) . '"))';
		}
	}
}


class AddedByPredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_AddedByUGrpID $eq " . intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			$not = ($this->parent->negate)? "not" : "";
			return "rec_AddedByUGrpID $not in (" . $this->value . ")";
		}
		else {
			$not = ($this->parent->negate)? "not" : "";
			return "rec_AddedByUGrpID $not in (select usr.ugr_ID from ".USERS_DATABASE.".sysUGrps usr where usr.ugr_Name = '" . mysql_real_escape_string($this->value) . "')";
		}
	}
}

class AnyPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';
		return $not . ' (exists (select * from recDetails rd '
		                          . 'left join defDetailTypes on dtl_DetailTypeID=dty_ID '
		                          . 'left join Records link on rd.dtl_Value=link.rec_ID '
		                       . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
		                       . '  and if(dty_Type != "resource", '
		                                  .'rd.dtl_Value like "%'.mysql_real_escape_string($this->value).'%", '
		                                  .'link.rec_Title like "%'.mysql_real_escape_string($this->value).'%"))'
		                         .' or rec_Title like "%'.mysql_real_escape_string($this->value).'%") ';
	}
}


class FieldPredicate extends Predicate {
	var $field_type;

	function FieldPredicate(&$parent, $type, $value) {
		$this->field_type = $type;
		parent::Predicate($parent, $value);

		if ($value[0] == '-') {	// DWIM: user wants a negate, we'll let them put it here
			$parent->negate = true;
			$value = substr($value, 1);
		}
	}

	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

        $isnumericvalue = is_numeric($this->value);
		$match_value = $isnumericvalue? floatval($this->value) : '"' . mysql_real_escape_string($this->value) . '"';

		if ($this->parent->exact  ||  $this->value === "") {	// SC100
			$match_pred = " = $match_value";
		} else if ($this->parent->lessthan) {
			$match_pred = " < $match_value";
		} else if ($this->parent->greaterthan) {
			$match_pred = " > $match_value";
		} else {
			$match_pred = " like '%".mysql_real_escape_string($this->value)."%'";
		}
        if($isnumericvalue){
            $match_pred_for_term = " = $match_value";
        }else{
            $match_pred_for_term = " = trm.trm_ID";
        }
        
         try{   
            $timestamp0 = new DateTime($this->value);
            if($timestamp0) $date_match_pred = $this->makeDateClause();
         } catch (Exception  $e){
         }                            

		if (preg_match('/^\\d+$/', $this->field_type)) {
			/* handle the easy case: user has specified a (single) specific numeric type */
			$rd_type_clause = 'rd.dtl_DetailTypeID = ' . intval($this->field_type);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->field_type)) {
			/* user has specified a list of numeric types ... match any of them */
			$rd_type_clause = 'rd.dtl_DetailTypeID in (' . $this->field_type . ')';
		}
		else {
			/* user has specified the field name */
			$rd_type_clause = 'rdt.dty_Name like "' . mysql_real_escape_string($this->field_type) . '%"';
		}

		return $not . 'exists (select * from recDetails rd '
		                        . 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID '
		                        . 'left join Records link on rd.dtl_Value=link.rec_ID '
		                        . (($isnumericvalue)?'':'left join defTerms trm on trm.trm_Label '. $match_pred ). " "
		                            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
		                            . ' and if(rdt.dty_Type = "resource" AND '.(is_numeric($this->value)?'0':'1').', '
		                                      .'link.rec_Title ' . $match_pred . ', '
		                                      .'if(rdt.dty_Type in ("enum","relationtype"), '
		                                      .'rd.dtl_Value '.$match_pred_for_term.', '
		                       . (isset($timestamp0) ? 'if(rdt.dty_Type = "date", '
		                                         .'str_to_date(getTemporalDateString(rd.dtl_Value), "%Y-%m-%d %H:%i:%s") ' . $date_match_pred . ', '
		                                         .'rd.dtl_Value ' . $match_pred . ')'
		                                     : 'rd.dtl_Value ' . $match_pred ) . '))'
		                              .' and ' . $rd_type_clause . ')';
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
			} else {	// A workgroup tag.  How nice.
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
		$query = &$this->getQuery();
		$not = ($this->parent->negate)? 'not ' : '';
		if ($query->search_type == BOOKMARK) {
			if (is_numeric(join('', $this->value))) {	// if all tag specs are numeric then don't need a join
				return $not . 'exists (select * from usrRecTagLinks where rtl_RecID=bkm_RecID and rtl_TagID in ('.join(',', $this->value).'))';
			} else if (! $this->wg_value) {
				// this runs faster (like TEN TIMES FASTER) - think it's to do with the join
				$query=$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
				                    . 'where kwi.rtl_RecID=rec_ID and (';
				$first_value = true;
				foreach ($this->value as $value) {
					if (! $first_value) $query .= 'or ';
					if (is_numeric($value)) {
						$query .= 'rtl_TagID='.intval($value).' ';
					} else {
						$query .=     ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
					                                           : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
					}
					$first_value = false;
				}
				$query .=              ') and kwd.tag_UGrpID='.get_user_id().') ';
			} else {
				$query=$not . 'exists (select * from '.USERS_DATABASE.'.sysUGrps, usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
				                    . 'where ugr_ID=tag_UGrpID and kwi.rtl_RecID=rec_ID and (';
				for ($i=0; $i < count($this->value); ++$i) {
					if ($i > 0) $query .= 'or ';

					$value = $this->value[$i];
					$wg_value = $this->wg_value[$i];

					if ($wg_value) {
						$query .= '(';
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
					                                            : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
						$query .=      ' and ugr_Name = "'.mysql_real_escape_string($wg_value).'") ';
					} else {
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
					                                            : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
					}
				}
				$query .= ')) ';
			}
		} else {
			if (! $this->wg_value) {
				$query = $not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
				                    . 'where kwi.rtl_RecID=rec_ID and (';
				$first_value = true;
				foreach ($this->value as $value) {
					if (! $first_value) $query .= 'or ';
					if (is_numeric($value)) {
						$query .= "kwd.tag_ID=$value ";
					} else {
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
					                                            : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
					}
					$first_value = false;
				}
				$query .= ')) ';
			} else {
				$query = $not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID left join '.USERS_DATABASE.'.sysUGrps on ugr_ID=tag_UGrpID '
				                    . 'where kwi.rtl_RecID=rec_ID and (';
				for ($i=0; $i < count($this->value); ++$i) {
					if ($i > 0) $query .= 'or ';

					$value = $this->value[$i];
					$wg_value = $this->wg_value[$i];

					if ($wg_value) {
						$query .= '(';
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
					                                            : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
						$query .= ' and ugr_Name = "'.mysql_real_escape_string($wg_value).'") ';
					} else {
						if (is_numeric($value)) {
							$query .= "kwd.tag_ID=$value ";
						} else {
							$query .= '(';
							$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.mysql_real_escape_string($value).'" '
						                                            : 'kwd.tag_Text like "'.mysql_real_escape_string($value).'%" ');
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
		return "rec_ID $not in (" . join(',', array_map('intval', explode(',', $this->value))) . ')';
	}
}


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
		$res = mysql_query("select group_concat( distinct rec_ID ) from Records, recRelationshipsCache where (rrc_RecID=rec_ID or rrc_TargetRecID=rec_ID or rrc_SourceRecID=rec_ID)
		                                                                                            and (rrc_SourceRecID in $ids or rrc_TargetRecID in $ids) and rec_ID not in $ids");
		$ids = mysql_fetch_row($res);  $ids = $ids[0];
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
			return "$not rec_Modified >= '$datestamp'";
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
			return "$not rec_Modified <= '$datestamp'";
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
        
         try{   
            $timestamp = new DateTime($this->value);
            $not = ($this->parent->negate)? 'not' : '';
            return "$not $col " . $this->makeDateClause();
         } catch (Exception  $e){
         }                            
		return '1';
	}
}

class DateAddedPredicate extends DatePredicate {
	function DateAddedPredicate(&$parent, $value) {
		parent::DatePredicate($parent, 'rec_Added', $value);
	}
}

class DateModifiedPredicate extends DatePredicate {
	function DateModifiedPredicate(&$parent, $value) {
		parent::DatePredicate($parent, 'rec_Modified', $value);
	}
}


class WorkgroupPredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_OwnerUGrpID $eq ".intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			$in = ($this->parent->negate)? 'not in' : 'in';
			return "rec_OwnerUGrpID $in (" . $this->value . ")";
		}
		else {
			return "rec_OwnerUGrpID $eq (select grp.ugr_ID from ".USERS_DATABASE.".sysUGrps grp where grp.ugr_Name = '".mysql_real_escape_string($this->value)."' limit 1)";
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
		$op = '';
		if ($this->parent->exact) {
			$op = $this->parent->negate? "!=" : "=";
			return "rec_Hash $op '" . mysql_real_escape_string($this->value) . "'";
		}
		else {
			$op = $this->parent->negate? " not like " : " like ";
			return "rec_Hash $op '" . mysql_real_escape_string($this->value) . "%'";
		}
	}
}


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
	if (@$_REQUEST['r']) $q .= 't:' . intval($_REQUEST['r']) . ' ';	// note: defRecTypes was 'r', now 't' (for TYPE!)
	if (@$_REQUEST['uid']) $q .= 'usr:' . intval($_REQUEST['uid']) . ' ';
	if (@$_REQUEST['bi']) $q .= 'id:"' . $_REQUEST['bi'] . '" ';
	if (@$_REQUEST['a']) $q .= 'any:"' . $_REQUEST['a'] . '" ';

	$_REQUEST['q'] = $q;
}

/**
* put your comment there...
*
* @param mixed $query
* @param mixed $search_type
* @param mixed $parms
* @param mixed $wg_ids
* @param mixed $publicOnly
*/
function REQUEST_to_query($query, $search_type, $parms=NULL, $wg_ids=NULL, $publicOnly = false) {
	// wg_ids is a list of the workgroups we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted

	/* use the supplied _REQUEST variables (or $parms if supplied) to construct a query starting with $query */
	if (! $parms) $parms = $_REQUEST;
    if (!defined('stype') && @$parms['stype']){
            define('stype', @$parms['stype']);
    }
	        

	if (! $wg_ids  &&  function_exists('get_user_id')) {
		$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
		                              'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
	}

	if (! @$parms['qq']  &&  ! preg_match('/&&|\\bAND\\b/i', @$parms['q'])) {
		$query .= parse_query($search_type, @$parms['q'], @$parms['s'], $wg_ids, $publicOnly);
	} else {
		// search-within-search gives us top-level ANDing (full expressiveness of conjunctions and disjunctions! hot damn)
		// basically for free!
/*
		$q_bits = explode('&&', $parms['qq']);
		if ($parms['q']) array_push($q_bits, $parms['q']);
*/
		$qq = @$parms['qq'];
		if ($parms['q']) {
			if ($qq) $qq .= ' && ' . $parms['q'];
			else $qq = $parms['q'];
		}
		$q_bits = preg_split('/&&|\\bAND\\b/i', $qq);
		$where_clause = '';
		$q_clauses = array();
		foreach ($q_bits as $q_bit) {
			$q = parse_query($search_type, $q_bit, @$parms['s'], $wg_ids, $publicOnly);
			// for each qbit if there is owner/vis followed by clause followed by order by, capture it for and'ing
			preg_match('/.*?where [(]rec_OwnerUGrpID=[-0-9]* or (?:rec_NonOwnerVisibility="public"|not rec_NonOwnerVisibility="hidden")(?: or rec_OwnerUGrpID in \\([0-9,]*\\))?[)] and (.*?) order by/s', $q, $matches);
			if ($matches[1]) {
				array_push($q_clauses, '(' . $matches[1] . ')');
			}
		}
		sort($q_clauses);
		$where_clause = join(' and ', $q_clauses);
		// check last qbits for form of owner/vis prefix and order by suffix, then capture and add them
		if (preg_match('/(.*?where [(]rec_OwnerUGrpID=[0-9]* or (?:rec_NonOwnerVisibility="public"|not rec_NonOwnerVisibility="hidden")(?: or rec_OwnerUGrpID in [(][0-9,]*[)])?[)] and ).*?( order by.*)$/s', $q, $matches))
			$query .= $matches[1] . $where_clause . $matches[2];
	}

	if(array_key_exists("l",$parms) || array_key_exists("limit",$parms)){
		if (array_key_exists("l", $parms)) {
			$limit = intval(@$parms["l"]);
			unset($parms["l"]);
		}else if(array_key_exists("limit", $parms)) {
			$limit = intval(@$parms["limit"]);  // this is back in since hml.php passes through stuff from sitemap.xmap
		}else{
			$limit = 100;
		}
		if ($limit < 1 ) unset($limit);
		if (@$limit){
			//ARTEM. It should not overwrite the limit specified in dispPreferences $limit = min($limit, 1000);
		}else{
			$limit = 100; // Artem says 12/3/12 that this will not happen b/c it only happens if the parameter is bad.
		}

		if (array_key_exists("o", $parms)) {
			$offset = intval(@$parms["o"]);
			unset($parms["o"]);
		}else if(array_key_exists("offset", $parms)) {
			$offset = intval(@$parms["offset"]);  // this is back in since hml.php passes through stuff from sitemap.xmap
		}

		$query .=  (@$limit? " limit $limit" : "") . (@$offset? " offset $offset " : "");
	}

	return $query;
}

?>
