<?php

define('BOOKMARK', 'bookmark');
define('BIBLIO', 'biblio');
define('BOTH', 'both');

define('SORT_POPULARITY', 'p');
define('SORT_RATING', 'r');
define('SORT_URL', 'u');
define('SORT_MODIFIED', 'm');
define('SORT_ADDED', 'a');
define('SORT_TITLE', 't');


function parse_query($search_type, $text, $sort_order='', $wg_ids=NULL) {
	// wg_ids is a list of the workgroups we can access; records records marked with a rec_OwnerUGrpID not in this list are omitted

	// clean up the query.
	// liposuction out all the non-kocher characters
	// (this means all punctuation except -, _, :, ', ", = and ,  ...?)

	$text = preg_replace('/[\000-\041\043-\046\050-\053\073\077\100\133\135\136\140\173-\177]+/s', ' ', $text);
	$text = preg_replace('/- (?=[^"]*(?:"[^"]*"[^"]*)*$)/', ' ', $text); // remove any dashes outside matched quotes.

	$query = new Query($search_type, $text);
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

	function Query($search_type, $text) {
		$this->search_type = $search_type;
		$this->or_limbs = array();
		$this->sort_phrases = array();
		$this->sort_tables = array();
		$this->workgroups = array();

		// Find any 'sortby:' phrases in the query, and pull them out.
		// "sortby:..." within double quotes is regarded as a search term, and we don't remove it here
		while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(sortby:(?:f:|field:)?"[^"]+"\\S*|sortby:\\S*)/', $text, $matches)) {
//error_log(print_r($matches, 1));
			$this->addSortPhrase($matches[2]);
			$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
		}

		// According to WWGD, OR is the top-level delimiter (yes, more top-level than double-quoted text)
		$or_texts = preg_split('/\\b *OR *\\b/i', $text);
		for ($i=0; $i < count($or_texts); ++$i)
			if ($or_texts[$i]) $this->addOrLimb($or_texts[$i]);	// NO LONGER collapse uppercase -> lowercase ... let's wait till PHP understands UTF-8 (mysql match ignores case anyway)
	}

	function addOrLimb($text) {
		$this->or_limbs[] = & new OrLimb($this, $text);
	}

	function addSortPhrase($text) {
		$this->sort_phrases[] = & new SortPhrase($this, $text);
	}

	function addWorkgroupRestriction($wg_ids) {
		if ($wg_ids) $this->workgroups = $wg_ids;
		array_push($this->workgroups, 0);	// everybody can access records with rec_OwnerUGrpID set to 0
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
		$where_clause = '(rec_OwnerUGrpID is null or rec_NonOwnerVisibility="viewable" or rec_OwnerUGrpID in (' . join(',', $this->workgroups) . ')) and ' . $where_clause;

		return $from_clause . 'where ' . $where_clause . $sort_clause;
	}
}


class OrLimb {
	var $and_limbs;

	var $parent;


	function OrLimb(&$parent, $text) {
		$this->parent = &$parent;

		$this->and_limbs = array();
		if (substr_count($text, '"') % 2 != 0) $text .= '"';	// unmatched quote

		if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches)) {
			$and_texts = $matches[0];
			for ($i=0; $i < count($and_texts); ++$i)
				if ($and_texts[$i]) $this->addAndLimb($and_texts[$i]);
		}
	}

	function addAndLimb($text) {
		$this->and_limbs[] = & new AndLimb($this, $text);
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

		$this->exact = false;
		if ($text[0] == '-') {
			$this->negate = true;
			$text = substr($text, 1);
		} else {
			$this->negate = false;
		}

		$this->pred = &$this->createPredicate($text);
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

		if (! $colon_pos) {	// a colon was either NOT FOUND or AT THE BEGINNING OF THE STRING
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
			$CREATOR = 158;

			if (preg_match('/^(?:f|field):(\\d+)(:m)?/i', $text, $matches)) {
				@list($_, $field_id, $show_multiples) = $matches;

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
					$bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
					return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
					             "left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and dtl_DetailTypeID=$field_id ");
				} else {
					// have to introduce a defDetailTypes join to ensure that we only use the linked resource's title if this is in fact a resource type (previously any integer, e.g. a date, could potentially index another records record)
					return array(" ifnull((select if(dty_Type='resource', link.rec_Title, dtl_Value) from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records link on dtl_Value=link.rec_ID where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=$field_id order by if($field_id=$CREATOR, dtl_ID, link.rec_Title) limit 1), '~~') ".$scending,
							"dtl_DetailTypeID=$field_id", NULL);
				}
			} else if (preg_match('/^(?:f|field):"?([^":]+)"?(:m)?/i', $text, $matches)) {
				@list($_, $field_name, $show_multiples) = $matches;

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
					$bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
					return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
					             "left join defDetailTypes bdt$bd_name on bdt$bd_name.dty_Name='".addslashes($field_name)."' "
					            ."left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and $bd_name.dtl_DetailTypeID=bdt$bd_name.dty_ID ");
				} else {
					return array(" ifnull((select if(dty_Type='resource', link.rec_Title, dtl_Value) from defDetailTypes, recDetails left join Records link on dtl_Value=link.rec_ID where dty_Name='".addslashes($field_name)."' and dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=dty_ID order by if(dty_ID=$CREATOR,dtl_ID,link.rec_Title) limit 1), '~~') ".$scending,
							"dtl_DetailTypeID=$field_id", NULL);
				}
			}

		    case 't': case 'title':
			return array('rec_Title'.$scending, NULL);
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

	function makeSQL($table_name) { return '1'; }


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


class TitlePredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		if ($this->parent->exact)
			return $not . 'rec_Title = "'.addslashes($this->value).'"';
		else if ($this->parent->lessthan)
			return $not . 'rec_Title < "'.addslashes($this->value).'"';
		else if ($this->parent->greaterthan)
			return $not . 'rec_Title > "'.addslashes($this->value).'"';
		else
			return 'rec_Title ' . $not . 'like "%'.addslashes($this->value).'%"';
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
			return "rec_RecTypeID $eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".addslashes($this->value)."' limit 1)";
		}
	}
}


class URLPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		return 'rec_URL ' . $not . 'like "%'.addslashes($this->value).'%"';
	}
}


class NotesPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		if ($query->search_type == BOOKMARK)
			return 'pers_notes ' . $not . 'like "%'.addslashes($this->value).'%"';
		else
			return 'rec_ScratchPad ' . $not . 'like "%'.addslashes($this->value).'%"';
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
			                      . ' and (usr.ugr_FirstName = "' . addslashes($matches[1]) . '" and usr.ugr_LastName = "' . addslashes($matches[2]) . '"))';
		}
		else {
			return $not . 'exists (select * from usrBookmarks bkmk, '.USERS_DATABASE.'.sysUGrps usr '
			                    . ' where bkmk.bkm_recID=rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
			                      . ' and usr.ugr_Name = "' . addslashes($this->value) . '"))';
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
			return "rec_AddedByUGrpID $not in (select usr.ugr_ID from ".USERS_DATABASE.".sysUGrps usr where usr.ugr_Name = '" . addslashes($this->value) . "')";
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
		                                  .'rd.dtl_Value like "%'.addslashes($this->value).'%", '
		                                  .'link.rec_Title like "%'.addslashes($this->value).'%"))'
		                         .' or rec_Title like "%'.addslashes($this->value).'%") ';
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

		$match_value = is_numeric($this->value)? floatval($this->value) : '"' . addslashes($this->value) . '"';

		if ($this->parent->exact  ||  $this->value === "") {	// SC100
			$match_pred = " = $match_value";
		} else if ($this->parent->lessthan) {
			$match_pred = " < $match_value";
		} else if ($this->parent->greaterthan) {
			$match_pred = " > $match_value";
		} else {
			$match_pred = " like '%".addslashes($this->value)."%'";
		}

		$timestamp = strtotime($this->value);
		if ($timestamp) {
			$date_match_pred = $this->makeDateClause();
		}

		if (preg_match('/^\\d+$/', $this->field_type)) {
			/* handle the easy case: user has specified a (single) specific numeric type */
			$rd_type_clause = 'rd.dtl_DetailTypeID = ' . intval($this->field_type);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			/* user has specified a list of numeric types ... match any of them */
			$rd_type_clause = 'rd.dtl_DetailTypeID in (' . $this->field_type . ')';
		}
		else {
			/* user has specified the field name */
			$rd_type_clause = 'rdt.dty_Name like "' . addslashes($this->field_type) . '%"';
		}

		return $not . 'exists (select * from recDetails rd '
		                        . 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID '
		                        . 'left join Records link on rd.dtl_Value=link.rec_ID '
		                            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
		                            . '  and if(dty_Type = "resource", '
		                                      .'link.rec_Title ' . $match_pred . ', '
		                       . ($timestamp ? 'if(dty_Type = "date", '
		                                         .'str_to_date(rd.dtl_Value, "%Y-%m-%d %H:%i:%s") ' . $date_match_pred . ', '
		                                         .'rd.dtl_Value ' . $match_pred . ')'
		                                     : 'rd.dtl_Value ' . $match_pred ) . ')'
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
						$query .=     ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
					                                           : 'kwd.tag_Text like "'.addslashes($value).'%" ');
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
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
					                                            : 'kwd.tag_Text like "'.addslashes($value).'%" ');
						$query .=      ' and ugr_Name = "'.addslashes($wg_value).'") ';
					} else {
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
					                                            : 'kwd.tag_Text like "'.addslashes($value).'%" ');
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
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
					                                            : 'kwd.tag_Text like "'.addslashes($value).'%" ');
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
						$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
					                                            : 'kwd.tag_Text like "'.addslashes($value).'%" ');
						$query .= ' and ugr_Name = "'.addslashes($wg_value).'") ';
					} else {
						if (is_numeric($value)) {
							$query .= "kwd.tag_ID=$value ";
						} else {
							$query .= '(';
							$query .=      ($this->parent->exact? 'kwd.tag_Text = "'.addslashes($value).'" '
						                                            : 'kwd.tag_Text like "'.addslashes($value).'%" ');
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
			return "exists (select * from rec_relationships where (rr_rec_id199=TOPBIBLIO.rec_ID and rr_rec_id202 in $ids)
		                                                   or (rr_rec_id202=TOPBIBLIO.rec_ID and rr_rec_id199 in $ids))";
		}
		else {
			/* just want something that has a relation */
			return "TOPBIBLIO.rec_ID in (select distinct rr_rec_id199 from rec_relationships union select distinct rr_rec_id202 from rec_relationships)";
		}
	}
}


class RelationsForPredicate extends Predicate {
	function makeSQL() {
		$ids = "(" . join(",", array_map("intval", explode(",", $this->value))) . ")";
/*
		return "exists (select * from rec_relationships where ((rr_rec_id199=TOPBIBLIO.rec_ID or rr_rec_id=TOPBIBLIO.rec_ID) and rr_rec_id202=$id)
		                                                   or ((rr_rec_id202=TOPBIBLIO.rec_ID or rr_rec_id=TOPBIBLIO.rec_ID) and rr_rec_id199=$id))";
*/
/*
		return "TOPBIBLIO.rec_ID in (select rr_rec_id199 from rec_relationships where rr_rec_id202=$id
		                       union select rr_rec_id202 from rec_relationships where rr_rec_id199=$id
		                       union select rr_rec_id    from rec_relationships where rr_rec_id199=$id or rr_rec_id202=$id)";
*/
/*
		return "exists (select * from bib_relationships2 where ((rr_rec_id199=TOPBIBLIO.rec_ID or rr_rec_id=TOPBIBLIO.rec_ID) and rr_rec_id202=$id))";
*/
		/* Okay, this isn't the way I would have done it initially, but it benchmarks well:
		 * All of the methods above were taking 4-5 seconds.
		 * Putting rec_relationships into the list of tables at the top-level gets us down to about 0.8 seconds, which is alright, but disruptive.
		 * Coding the 'relationsfor:' predicate as   TOPBIBLIO.rec_ID in (select distinct rec_ID from rec_relationships where (rr_rec_id=TOPBIBLIO.rec_ID etc etc))
		 *   gets us down to about 2 seconds, but it looks like the optimiser doesn't really pick up on what we're doing.
		 * Fastest is to do a SEPARATE QUERY to get the record IDs out of the bib_relationship table, then pass it back encoded in the predicate.
		 * Certainly not the most elegant way to do it, but the numbers don't lie.
		 */
		$res = mysql_query("select group_concat( distinct rec_ID ) from Records, rec_relationships where (rr_rec_id=rec_ID or rr_rec_id199=rec_ID or rr_rec_id202=rec_ID)
		                                                                                            and (rr_rec_id202 in $ids or rr_rec_id199 in $ids) and rec_ID not in $ids");
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
		$timestamp = strtotime($this->value);
		if ($timestamp  &&  $timestamp != -1) {
			$not = ($this->parent->negate)? 'not' : '';
			return "$not $col " . $this->makeDateClause();
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
			return "rec_OwnerUGrpID $eq (select grp.ugr_ID from ".USERS_DATABASE.".sysUGrps grp where grp.ugr_Name = '".addslashes($this->value)."' limit 1)";
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
			return "rec_Hash $op '" . addslashes($this->value) . "'";
		}
		else {
			$op = $this->parent->negate? " not like " : " like ";
			return "rec_Hash $op '" . addslashes($this->value) . "%'";
		}
	}
}


function construct_legacy_search() {
	$q = '';

	if (@$_REQUEST['search_title']) $_REQUEST['t'] = $_REQUEST['search_title'];
	if (@$_REQUEST['search_tagString']) $_REQUEST['k'] = $_REQUEST['search_tagString'];
	if (@$_REQUEST['search_url']) $_REQUEST['u'] = $_REQUEST['search_url'];
	if (@$_REQUEST['search_description']) $_REQUEST['n'] = $_REQUEST['search_description'];
	if (@$_REQUEST['search_reftype']) $_REQUEST['r'] = $_REQUEST['search_reftype'];
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


function REQUEST_to_query($query, $search_type, $parms=NULL, $wg_ids=NULL) {
	// wg_ids is a list of the workgroups we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted

	/* use the supplied _REQUEST variables (or $parms if supplied) to construct a query starting with $query */
	if (! $parms) $parms = $_REQUEST;
	define('stype', @$parms['stype']);

	if (! $wg_ids  &&  function_exists('get_user_id')) {
		$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
		                              'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
	}

	if (! @$parms['qq']  &&  ! preg_match('/&&|\\bAND\\b/i', @$parms['q'])) {
		$query .= parse_query($search_type, $parms['q'], @$parms['s'], $wg_ids);
	} else {
		// search-within-search gives us top-level ANDing (full expressiveness of conjunctions and disjunctions! hot damn)
		// basically for free!
/*
		$q_bits = explode('&&', $parms['qq']);
		if ($parms['q']) array_push($q_bits, $parms['q']);
*/
		$qq = $parms['qq'];
		if ($parms['q']) {
			if ($qq) $qq .= ' && ' . $parms['q'];
			else $qq = $parms['q'];
		}
		$q_bits = preg_split('/&&|\\bAND\\b/i', $qq);

		$where_clause = '';
		$q_clauses = array();
		foreach ($q_bits as $q_bit) {
			$q = parse_query($search_type, $q_bit, $parms['s'], $wg_ids);
			preg_match('/.*?where [(]rec_OwnerUGrpID is null or rec_NonOwnerVisibility="viewable" or rec_OwnerUGrpID in \\([0-9,]*\\)[)] and (.*) order by/s', $q, $matches);
			if ($matches[1]) {
				array_push($q_clauses, '(' . $matches[1] . ')');
			}
		}
		sort($q_clauses);
		$where_clause = join(' and ', $q_clauses);

		if (preg_match('/(.*?where [(]rec_OwnerUGrpID is null or rec_NonOwnerVisibility="viewable" or rec_OwnerUGrpID in [(][0-9,]*[)][)] and ).*( order by.*)/s', $q, $matches))
			$query .= $matches[1] . $where_clause . $matches[2];
	}

	return $query;
}

?>
