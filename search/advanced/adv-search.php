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
	// wg_ids is a list of the workgroups we can access; records records marked with a rec_wg_id not in this list are omitted

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
		$q .= ' order by ifnull((select if(link.rec_id is null, rd_val, link.rec_title) from rec_details left join records link on rd_val=link.rec_id where rd_rec_id=TOPBIBLIO.rec_id and rd_type='.$matches[1].' order by link.rec_title limit 1), "~~"), rec_title';
	} else {
		if ($search_type == BOOKMARK) {
			switch ($sort_order) {
			    case SORT_POPULARITY:
				$q .= ' order by rec_popularity desc, rec_added desc'; break;
			    case SORT_RATING:
				$q .= ' order by -if(pers_content_rating > pers_quality_rating, if(pers_content_rating > pers_interest_rating, pers_content_rating, pers_interest_rating), if(pers_quality_rating > pers_interest_rating, pers_quality_rating, pers_interest_rating)), -(pers_content_rating+pers_quality_rating+pers_interest_rating)'; break;
			    case SORT_URL:
				$q .= ' order by rec_url is null, rec_url'; break;
			    case SORT_MODIFIED:
				$q .= ' order by bkm_Modified desc'; break;
			    case SORT_ADDED:
				$q .= ' order by bkm_Added desc'; break;
			    case SORT_TITLE: default:
				$q .= ' order by rec_title = "", rec_title';
			}
		} else {
			switch ($sort_order) {
			    case SORT_POPULARITY:
				$q .= ' order by rec_popularity desc, rec_added desc'; break;
			    case SORT_URL:
				$q .= ' order by rec_url is null, rec_url'; break;
			    case SORT_MODIFIED:
				$q .= ' order by rec_modified desc'; break;
			    case SORT_ADDED:
				$q .= ' order by rec_added desc'; break;
			    case SORT_TITLE: default:
				$q .= ' order by rec_title = "", rec_title';
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
		array_push($this->workgroups, 0);	// everybody can access records with rec_wg_id set to 0
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
			$from_clause = 'from usrBookmarks TOPBKMK left join records TOPBIBLIO on pers_rec_id=rec_id ';
		else
			$from_clause = 'from records TOPBIBLIO left join usrBookmarks TOPBKMK on pers_rec_id=rec_id and pers_usr_id='.get_user_id().' ';

		$from_clause .= join(' ', $this->sort_tables);	// sorting may require the introduction of more tables


		if ($this->search_type == BOOKMARK) {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'pers_usr_id = ' . get_user_id() . ' and (rec_temporary is null or not rec_temporary) ';
		} else if ($this->search_type == BIBLIO) {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'pers_usr_id is null and not rec_temporary ';
		} else {
			if ($where_clause) $where_clause = '(' . $where_clause . ') and ';
			$where_clause .= 'not rec_temporary ';
		}
		$where_clause = '(rec_wg_id is null or rec_visibility="viewable" or rec_wg_id in (' . join(',', $this->workgroups) . ')) and ' . $where_clause;

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
				return new KeywordPredicate($this, $pred_val);
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
			return new KeywordPredicate($this, $pred_val);

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

		    case 'linkto':	// linkto:XXX matches records that have a rec_details reference to XXX
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
		if (defined('stype')  &&  stype == 'key') {	// "default" search should be on keyword
			return new KeywordPredicate($this, $pred_val);
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
			return array('-rec_popularity'.$scending.', -rec_id'.$scending, 'rec_popularity', NULL);

		    case 'r': case 'rating':
			if ($this->parent->search_type == BOOKMARK) {
				return array('-if(pers_content_rating > pers_quality_rating, if(pers_content_rating > pers_interest_rating, pers_content_rating, pers_interest_rating), if(pers_quality_rating > pers_interest_rating, pers_quality_rating, pers_interest_rating))'.$scending.', -(pers_content_rating+pers_quality_rating+pers_interest_rating)'.$scending, 'bkmk_rating', NULL);
			} else {	// default to popularity sort
				return array('-rec_popularity'.$scending.', -rec_id'.$scending, 'rec_popularity', NULL);
			}

		    case 'interest':
			if ($this->parent->search_type == BOOKMARK) {
				return array('-pers_interest_rating'.$scending, 'pers_interest_rating', NULL);
			} else return array('rec_title'.$scending, NULL);	// default to title sort
			break;

		    case 'content':
			if ($this->parent->search_type == BOOKMARK) {
				return array('-pers_content_rating'.$scending, 'pers_content_rating', NULL);
			} else return array('rec_title'.$scending, NULL);	// default to title sort
			break;

		    case 'quality':
			if ($this->parent->search_type == BOOKMARK) {
				return array('-pers_quality_rating'.$scending, 'pers_quality_rating', NULL);
			} else return array('rec_title'.$scending, NULL);	// default to title sort
			break;

		    case 'u': case 'url':
			return array('rec_url'.$scending, 'rec_url', NULL);

		    case 'm': case 'modified':
			if ($this->parent->search_type == BOOKMARK) return array('bkm_Modified'.$scending, NULL);
			else return array('rec_modified'.$scending, 'rec_modified', NULL);

		    case 'a': case 'added':
			if ($this->parent->search_type == BOOKMARK) return array('bkm_Added'.$scending, NULL);
			else return array('rec_added'.$scending, 'rec_added', NULL);

		    case 'f': case 'field':
			/* Sort by field is complicated.
			 * Unless the "multiple" flag is set, then if there are multiple values for a particular field for a particular record,
			 * then we can only sort by one of them.  We choose a representative value: this is the lex-lowest of all the values,
			 * UNLESS it is field 158 (creator), in which case the order of the authors is important, and we choose the one with the lowest rd_id
			 */
			$CREATOR = 158;

			if (preg_match('/^(?:f|field):(\\d+)(:m)?/i', $text, $matches)) {
				@list($_, $field_id, $show_multiples) = $matches;

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining rec_details
					$bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
					return array("$bd_name.rd_val".$scending, "$bd_name.rd_val".$scending,
					             "left join rec_details $bd_name on $bd_name.rd_rec_id=rec_id and rd_type=$field_id ");
				} else {
					// have to introduce a rec_detail_types join to ensure that we only use the linked resource's title if this is in fact a resource type (previously any integer, e.g. a date, could potentially index another records record)
					return array(" ifnull((select if(rdt_type='resource', link.rec_title, rd_val) from rec_details left join rec_detail_types on rdt_id=rd_type left join records link on rd_val=link.rec_id where rd_rec_id=TOPBIBLIO.rec_id and rd_type=$field_id order by if($field_id=$CREATOR, rd_id, link.rec_title) limit 1), '~~') ".$scending,
							"rd_type=$field_id", NULL);
				}
			} else if (preg_match('/^(?:f|field):"?([^":]+)"?(:m)?/i', $text, $matches)) {
				@list($_, $field_name, $show_multiples) = $matches;

				if ($show_multiples) {	// "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining rec_details
					$bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
					return array("$bd_name.rd_val".$scending, "$bd_name.rd_val".$scending,
					             "left join rec_detail_types bdt$bd_name on bdt$bd_name.rdt_name='".addslashes($field_name)."' "
					            ."left join rec_details $bd_name on $bd_name.rd_rec_id=rec_id and $bd_name.rd_type=bdt$bd_name.rdt_id ");
				} else {
					return array(" ifnull((select if(rdt_type='resource', link.rec_title, rd_val) from rec_detail_types, rec_details left join records link on rd_val=link.rec_id where rdt_name='".addslashes($field_name)."' and rd_rec_id=TOPBIBLIO.rec_id and rd_type=rdt_id order by if(rdt_id=$CREATOR,rd_id,link.rec_title) limit 1), '~~') ".$scending,
							"rd_type=$field_id", NULL);
				}
			}

		    case 't': case 'title':
			return array('rec_title'.$scending, NULL);
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
			return $not . 'rec_title = "'.addslashes($this->value).'"';
		else if ($this->parent->lessthan)
			return $not . 'rec_title < "'.addslashes($this->value).'"';
		else if ($this->parent->greaterthan)
			return $not . 'rec_title > "'.addslashes($this->value).'"';
		else
			return 'rec_title ' . $not . 'like "%'.addslashes($this->value).'%"';
	}
}


class TypePredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_type $eq ".intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			// comma-separated list of rec_types ids
			$in = ($this->parent->negate)? 'not in' : 'in';
			return "rec_type $in (" . $this->value . ")";
		}
		else {
			return "rec_type $eq (select rft.rt_id from rec_types rft where rft.rt_name = '".addslashes($this->value)."' limit 1)";
		}
	}
}


class URLPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		return 'rec_url ' . $not . 'like "%'.addslashes($this->value).'%"';
	}
}


class NotesPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';

		$query = &$this->getQuery();
		if ($query->search_type == BOOKMARK)
			return 'pers_notes ' . $not . 'like "%'.addslashes($this->value).'%"';
		else
			return 'rec_scratchpad ' . $not . 'like "%'.addslashes($this->value).'%"';
	}
}


class UserPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';
		if (is_numeric($this->value)) {
			return $not . 'exists (select * from usrBookmarks bkmk where bkmk.pers_rec_id=rec_id '
			                                                  . ' and bkmk.pers_usr_id = ' . intval($this->value) . ')';
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			return $not . 'exists (select * from usrBookmarks bkmk where bkmk.pers_rec_id=rec_id '
			                                                  . ' and bkmk.pers_usr_id in (' . $this->value . '))';
		}
		else {
			return $not . 'exists (select * from usrBookmarks bkmk, '.USERS_DATABASE.'.Users usr '
			                    . ' where bkmk.pers_rec_id=rec_id and bkmk.pers_usr_id = usr.Id '
			                      . ' and (usr.Realname = "' . addslashes($this->value) . '" or usr.Username = "' . addslashes($this->value) . '"))';
		}
	}
}


class AddedByPredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_added_by_usr_id $eq " . intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			$not = ($this->parent->negate)? "not" : "";
			return "rec_added_by_usr_id $not in (" . $this->value . ")";
		}
		else {
			$not = ($this->parent->negate)? "not" : "";
			return "rec_added_by_usr_id $not in (select Id from ".USERS_DATABASE.".Users where Username = '" . addslashes($this->value) . "')";
		}
	}
}

class AnyPredicate extends Predicate {
	function makeSQL() {
		$not = ($this->parent->negate)? 'not ' : '';
		return $not . ' (exists (select * from rec_details rd '
		                          . 'left join rec_detail_types on rd_type=rdt_id '
		                          . 'left join records link on rd.rd_val=link.rec_id '
		                       . 'where rd.rd_rec_id=TOPBIBLIO.rec_id '
		                       . '  and if(rdt_type != "resource", '
		                                  .'rd.rd_val like "%'.addslashes($this->value).'%", '
		                                  .'link.rec_title like "%'.addslashes($this->value).'%"))'
		                         .' or rec_title like "%'.addslashes($this->value).'%") ';
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
			$rd_type_clause = 'rd.rd_type = ' . intval($this->field_type);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			/* user has specified a list of numeric types ... match any of them */
			$rd_type_clause = 'rd.rd_type in (' . $this->field_type . ')';
		}
		else {
			/* user has specified the field name */
			$rd_type_clause = 'rdt.rdt_name like "' . addslashes($this->field_type) . '%"';
		}

		return $not . 'exists (select * from rec_details rd '
		                        . 'left join rec_detail_types rdt on rdt.rdt_id=rd.rd_type '
		                        . 'left join records link on rd.rd_val=link.rec_id '
		                            . 'where rd.rd_rec_id=TOPBIBLIO.rec_id '
		                            . '  and if(rdt_type = "resource", '
		                                      .'link.rec_title ' . $match_pred . ', '
		                       . ($timestamp ? 'if(rdt_type = "date", '
		                                         .'str_to_date(rd.rd_val, "%Y-%m-%d %H:%i:%s") ' . $date_match_pred . ', '
		                                         .'rd.rd_val ' . $match_pred . ')'
		                                     : 'rd.rd_val ' . $match_pred ) . ')'
		                              .' and ' . $rd_type_clause . ')';
	}
}


class KeywordPredicate extends Predicate {
	var $wg_value;

	function KeywordPredicate(&$parent, $value) {
		$this->parent = &$parent;

		$this->value = array();
		$this->wg_value = array();
		$values = explode(',', $value);
		$any_wg_values = false;

		// Heavy, heavy DWIM here: if the keyword for which we're searching contains comma(s),
		// then split it into several keywords, and do an OR search on those.
		for ($i=0; $i < count($values); ++$i) {
			if (strpos($values[$i], '\\') === FALSE) {
				array_push($this->value, trim($values[$i]));
				array_push($this->wg_value, '');
			} else {	// A workgroup keyword.  How nice.
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
			if (is_numeric(join('', $this->value))) {	// if all keyword specs are numeric then don't need a join
				return $not . 'exists (select * from keyword_links where kwl_pers_id=bkm_ID and kwl_kwd_id in ('.join(',', $this->value).'))';
			} else if (! $this->wg_value) {
				// this runs faster (like TEN TIMES FASTER) - think it's to do with the join
				$query=$not . 'exists (select * from keyword_links kwi left join keywords kwd on kwi.kwl_kwd_id=kwd.kwd_id '
				                    . 'where kwi.kwl_rec_id=rec_id and (';
				$first_value = true;
				foreach ($this->value as $value) {
					if (! $first_value) $query .= 'or ';
					if (is_numeric($value)) {
						$query .= 'kwl_kwd_id='.intval($value).' ';
					} else {
						$query .=     ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
					                                           : 'kwd.kwd_name like "'.addslashes($value).'%" ');
					}
					$first_value = false;
				}
				$query .=              ') and kwd.kwd_usr_id='.get_user_id().') ';
			} else {
				$query=$not . 'exists (select * from '.USERS_DATABASE.'.Groups, keyword_links kwi left join keywords kwd on kwi.kwl_kwd_id=kwd.kwd_id '
				                    . 'where grp_id=kwd_wg_id and kwi.kwl_rec_id=rec_id and (';
				for ($i=0; $i < count($this->value); ++$i) {
					if ($i > 0) $query .= 'or ';

					$value = $this->value[$i];
					$wg_value = $this->wg_value[$i];

					if ($wg_value) {
						$query .= '(';
				        	$query .=      ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
					                                            : 'kwd.kwd_name like "'.addslashes($value).'%" ');
						$query .=      ' and grp_name = "'.addslashes($wg_value).'") ';
					} else {
				        	$query .=      ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
					                                            : 'kwd.kwd_name like "'.addslashes($value).'%" ');
					}
				}
				$query .= ')) ';
			}
		} else {
			if (! $this->wg_value) {
				$query = $not . 'exists (select * from keyword_links kwi left join keywords kwd on kwi.kwl_kwd_id=kwd.kwd_id '
				                    . 'where kwi.kwl_rec_id=rec_id and (';
				$first_value = true;
				foreach ($this->value as $value) {
					if (! $first_value) $query .= 'or ';
					if (is_numeric($value)) {
						$query .= "kwd.kwd_id=$value ";
					} else {
						$query .=      ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
					                                            : 'kwd.kwd_name like "'.addslashes($value).'%" ');
					}
					$first_value = false;
				}
				$query .= ')) ';
			} else {
				$query = $not . 'exists (select * from keyword_links kwi left join keywords kwd on kwi.kwl_kwd_id=kwd.kwd_id left join '.USERS_DATABASE.'.Groups on grp_id=kwd_wg_id '
				                    . 'where kwi.kwl_rec_id=rec_id and (';
				for ($i=0; $i < count($this->value); ++$i) {
					if ($i > 0) $query .= 'or ';

					$value = $this->value[$i];
					$wg_value = $this->wg_value[$i];

					if ($wg_value) {
						$query .= '(';
						$query .=      ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
					                                            : 'kwd.kwd_name like "'.addslashes($value).'%" ');
					        $query .=      ' and grp_name = "'.addslashes($wg_value).'") ';
					} else {
						if (is_numeric($value)) {
							$query .= "kwd.kwd_id=$value ";
						} else {
							$query .= '(';
							$query .=      ($this->parent->exact? 'kwd.kwd_name = "'.addslashes($value).'" '
						                                            : 'kwd.kwd_name like "'.addslashes($value).'%" ');
							$query .= ' and grp_id is null) ';
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
		return "rec_id $not in (" . join(',', array_map('intval', explode(',', $this->value))) . ')';
	}
}


class LinkToPredicate extends Predicate {
	function makeSQL() {
		if ($this->value) {
			return 'exists (select * from rec_detail_types, rec_details bd '
			              . 'where bd.rd_rec_id=TOPBIBLIO.rec_id and rdt_id=rd_type and rdt_type="resource" '
			              . '  and bd.rd_val in (' . join(',', array_map('intval', explode(',', $this->value))) . '))';
		}
		else {
			return 'exists (select * from rec_detail_types, rec_details bd '
			              . 'where bd.rd_rec_id=TOPBIBLIO.rec_id and rdt_id=rd_type and rdt_type="resource")';
		}
	}
}


class LinkedToPredicate extends Predicate {
	function makeSQL() {
		if ($this->value) {
			return 'exists (select * from rec_detail_types, rec_details bd '
			              . 'where bd.rd_rec_id in (' . join(',', array_map('intval', explode(',', $this->value))) .') and rdt_id=rd_type and rdt_type="resource" '
			              . '  and bd.rd_val=TOPBIBLIO.rec_id)';
		}
		else {
			return 'exists (select * from rec_detail_types, rec_details bd '
			              . 'where bd.rd_val=TOPBIBLIO.rec_id and rdt_id=rd_type and rdt_type="resource")';
		}
	}
}


class RelatedToPredicate extends Predicate {
	function makeSQL() {
		if ($this->value) {
			$ids = "(" . join(",", array_map("intval", explode(",", $this->value))) . ")";
			return "exists (select * from rec_relationships where (rr_rec_id199=TOPBIBLIO.rec_id and rr_rec_id202 in $ids)
		                                                   or (rr_rec_id202=TOPBIBLIO.rec_id and rr_rec_id199 in $ids))";
		}
		else {
			/* just want something that has a relation */
			return "TOPBIBLIO.rec_id in (select distinct rr_rec_id199 from rec_relationships union select distinct rr_rec_id202 from rec_relationships)";
		}
	}
}


class RelationsForPredicate extends Predicate {
	function makeSQL() {
		$ids = "(" . join(",", array_map("intval", explode(",", $this->value))) . ")";
/*
		return "exists (select * from rec_relationships where ((rr_rec_id199=TOPBIBLIO.rec_id or rr_rec_id=TOPBIBLIO.rec_id) and rr_rec_id202=$id)
		                                                   or ((rr_rec_id202=TOPBIBLIO.rec_id or rr_rec_id=TOPBIBLIO.rec_id) and rr_rec_id199=$id))";
*/
/*
		return "TOPBIBLIO.rec_id in (select rr_rec_id199 from rec_relationships where rr_rec_id202=$id
		                       union select rr_rec_id202 from rec_relationships where rr_rec_id199=$id
		                       union select rr_rec_id    from rec_relationships where rr_rec_id199=$id or rr_rec_id202=$id)";
*/
/*
		return "exists (select * from bib_relationships2 where ((rr_rec_id199=TOPBIBLIO.rec_id or rr_rec_id=TOPBIBLIO.rec_id) and rr_rec_id202=$id))";
*/
		/* Okay, this isn't the way I would have done it initially, but it benchmarks well:
		 * All of the methods above were taking 4-5 seconds.
		 * Putting rec_relationships into the list of tables at the top-level gets us down to about 0.8 seconds, which is alright, but disruptive.
		 * Coding the 'relationsfor:' predicate as   TOPBIBLIO.rec_id in (select distinct rec_id from rec_relationships where (rr_rec_id=TOPBIBLIO.rec_id etc etc))
		 *   gets us down to about 2 seconds, but it looks like the optimiser doesn't really pick up on what we're doing.
		 * Fastest is to do a SEPARATE QUERY to get the record IDs out of the bib_relationship table, then pass it back encoded in the predicate.
		 * Certainly not the most elegant way to do it, but the numbers don't lie.
		 */
		$res = mysql_query("select group_concat( distinct rec_id ) from records, rec_relationships where (rr_rec_id=rec_id or rr_rec_id199=rec_id or rr_rec_id202=rec_id)
		                                                                                            and (rr_rec_id202 in $ids or rr_rec_id199 in $ids) and rec_id not in $ids");
		$ids = mysql_fetch_row($res);  $ids = $ids[0];
		if (! $ids) return "0";
		else return "TOPBIBLIO.rec_id in ($ids)";
	}
}


class AfterPredicate extends Predicate {
	function makeSQL() {
		$timestamp = strtotime($this->value);
		if ($timestamp  &&  $timestamp != -1) {
			$not = ($this->parent->negate)? 'not' : '';
			$datestamp = date('Y-m-d H:i:s', $timestamp);
			return "$not rec_modified >= '$datestamp'";
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
			return "$not rec_modified <= '$datestamp'";
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
		parent::DatePredicate($parent, 'rec_added', $value);
	}
}

class DateModifiedPredicate extends DatePredicate {
	function DateModifiedPredicate(&$parent, $value) {
		parent::DatePredicate($parent, 'rec_modified', $value);
	}
}


class WorkgroupPredicate extends Predicate {
	function makeSQL() {
		$eq = ($this->parent->negate)? '!=' : '=';
		if (is_numeric($this->value)) {
			return "rec_wg_id $eq ".intval($this->value);
		}
		else if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) {
			$in = ($this->parent->negate)? 'not in' : 'in';
			return "rec_wg_id $in (" . $this->value . ")";
		}
		else {
			return "rec_wg_id $eq (select grp.grp_id from ".USERS_DATABASE.".Groups grp where grp.grp_name = '".addslashes($this->value)."' limit 1)";
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
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and y( PointN( ExteriorRing( Envelope(bd.rd_geo) ), 4 ) ) $op " . floatval($this->value) . " limit 1)";
		}
		else if ($op[0] == '>') {
			// see if the SOUTHERNmost point of the bounding box lies north of the given latitude
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and y( StartPoint( ExteriorRing( Envelope(bd.rd_geo) ) ) ) $op " . floatval($this->value) . " limit 1)";

		}
		else if ($this->parent->exact) {
			$op = $this->parent->negate? "!=" : "=";
			// see if there is a Point with this exact latitude
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null and bd.rd_val = 'p'
			                   and y(bd.rd_geo) $op " . floatval($this->value) . " limit 1)";
		}
		else {
			// see if this latitude passes through the bounding box
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and ".floatval($this->value)." between y( StartPoint( ExteriorRing( Envelope(bd.rd_geo) ) ) )
			                                                      and y( PointN( ExteriorRing( Envelope(bd.rd_geo) ), 4 ) ) limit 1)";
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
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and x( PointN( ExteriorRing( Envelope(bd.rd_geo) ), 4 ) ) $op " . floatval($this->value) . " limit 1)";
		}
		else if ($op[0] == '>') {
			// see if the EASTERNmost point of the bounding box lies west of the given longitude
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and x( StartPoint( ExteriorRing( Envelope(bd.rd_geo) ) ) ) $op " . floatval($this->value) . " limit 1)";

		}
		else if ($this->parent->exact) {
			$op = $this->parent->negate? "!=" : "=";
			// see if there is a Point with this exact longitude
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null and bd.rd_val = 'p'
			                   and x(bd.rd_geo) $op " . floatval($this->value) . " limit 1)";
		}
		else {
			// see if this longitude passes through the bounding box
			return "exists (select * from rec_details bd
			                 where bd.rd_rec_id=TOPBIBLIO.rec_id and bd.rd_geo is not null
			                   and ".floatval($this->value)." between x( StartPoint( ExteriorRing( Envelope(bd.rd_geo) ) ) )
			                                                      and x( PointN( ExteriorRing( Envelope(bd.rd_geo) ), 2 ) ) limit 1)";
		}
	}
}


class HHashPredicate extends Predicate {
	function makeSQL() {
		$op = '';
		if ($this->parent->exact) {
			$op = $this->parent->negate? "!=" : "=";
			return "rec_hhash $op '" . addslashes($this->value) . "'";
		}
		else {
			$op = $this->parent->negate? " not like " : " like ";
			return "rec_hhash $op '" . addslashes($this->value) . "%'";
		}
	}
}


function construct_legacy_search() {
	$q = '';

	if (@$_REQUEST['search_title']) $_REQUEST['t'] = $_REQUEST['search_title'];
	if (@$_REQUEST['search_keywordstring']) $_REQUEST['k'] = $_REQUEST['search_keywordstring'];
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
	if (@$_REQUEST['r']) $q .= 't:' . intval($_REQUEST['r']) . ' ';	// note: rec_types was 'r', now 't' (for TYPE!)
	if (@$_REQUEST['uid']) $q .= 'usr:' . intval($_REQUEST['uid']) . ' ';
	if (@$_REQUEST['bi']) $q .= 'id:"' . $_REQUEST['bi'] . '" ';
	if (@$_REQUEST['a']) $q .= 'any:"' . $_REQUEST['a'] . '" ';

	$_REQUEST['q'] = $q;
}


function REQUEST_to_query($query, $search_type, $parms=NULL, $wg_ids=NULL) {
	// wg_ids is a list of the workgroups we can access; records records marked with a rec_wg_id not in this list are omitted

	/* use the supplied _REQUEST variables (or $parms if supplied) to construct a query starting with $query */
	if (! $parms) $parms = $_REQUEST;
	define('stype', @$parms['stype']);

	if (! $wg_ids  &&  function_exists('get_user_id')) {
		$wg_ids = mysql__select_array(USERS_DATABASE.'.UserGroups left join '.USERS_DATABASE.'.Groups on grp_id=ug_group_id', 'ug_group_id',
		                              'ug_user_id='.get_user_id().' and grp_type != "Usergroup"order by ug_group_id');
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
			preg_match('/.*?where [(]rec_wg_id is null or rec_visibility="viewable" or rec_wg_id in \\([0-9,]*\\)[)] and (.*) order by/s', $q, $matches);
			if ($matches[1]) {
				array_push($q_clauses, '(' . $matches[1] . ')');
			}
		}
		sort($q_clauses);
		$where_clause = join(' and ', $q_clauses);

		if (preg_match('/(.*?where [(]rec_wg_id is null or rec_visibility="viewable" or rec_wg_id in [(][0-9,]*[)][)] and ).*( order by.*)/s', $q, $matches))
			$query .= $matches[1] . $where_clause . $matches[2];
	}

	return $query;
}

?>
