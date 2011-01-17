<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
	/*<!-- TitleMask.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/
/* TitleMask.php
 * 2006-09-21
 *
 * Two important functions in this file:
 *  check_title_mask($mask, $rt) => returns an error string if there is a fault in the given mask for the given reference type
 *  fill_title_mask($mask, $rec_id, $rt) => returns the filled-in title mask for this bibliographic entry
 *
 * Various other utility functions starting with _title_mask__ may be ignored and are unlikely to invade your namespaces.
 */


mysql_connection_db_select(DATABASE);


function check_title_mask($mask, $rt) {
	/* Check that the given title mask is well-formed for the given reference type */
	/* Returns an error string describing any faults in the mask. */

	if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
		return '';	// no substitutions to make, therefore no errors

	foreach ($matches[1] as $field_name) {
		if (! $field_name) continue;
		$err = _title_mask__check_field_name($field_name, $rt);
		if ($err) return $err;
	}

	return '';
}


function fill_title_mask($mask, $rec_id, $rt) {
	/* Fill the title mask for the given records record */

	if (! $mask) return trim(_title_mask__get_field_value('160', $rec_id, $rt));

	if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
		return $mask;	// nothing to do -- no substitutions

	$replacements = array();
	for ($i=0; $i < count($matches[1]); ++$i) {
		/*
		 * $matches[3][$i] contains the field name as supplied (the string that we look up),
		 * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
		 *        (this is what we replace if there is a substitution)
		 * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
		 *        (this is what we replace with an empty string if there is no substitution value available)
		 */
		$value = _title_mask__get_field_value($matches[3][$i], $rec_id, $rt);
		if ($value)
			$replacements[$matches[2][$i]] = $value;
		else
			$replacements[$matches[1][$i]] = '';
	}
	$replacements['[['] = '[';
	$replacements[']]'] = ']';
// error_log(print_r($replacements, 1));

	$title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);
	if (! preg_match('/^\\s*[0-9a-z]+:\\S+\\s*$/i', $title)) {	// not a URI
		$title = preg_replace('!^[-:;,./\\s]*(.*?)[-:;,/\\s]*$!s', '\\1', $title);
		$title = preg_replace('!\\([-:;,./\\s]+\\)!s', '', $title);
		$title = preg_replace('!\\([-:;,./\\s]*(.*?)[-:;,./\\s]*\\)!s', '(\\1)', $title);
		$title = preg_replace('!\\([-:;,./\\s]*\\)|\\[[-:;,./\\s]*\\]!s', '', $title);
		$title = preg_replace('!^[-:;,./\\s]*(.*?)[-:;,/\\s]*$!s', '\\1', $title);
		$title = preg_replace('!,\\s*,+!s', ',', $title);
		$title = preg_replace('!\\s+,!s', ',', $title);
	}
	$title = preg_replace('!  +!s', ' ', $title);

	/* Clean up miscellaneous stray punctuation &c. */
	return trim($title);
}
/*
 * TOUCH IT YOU RUBBISH
 * SHUT IT YOU TOILET
 */


function _title_mask__check_field_name($field_name, $rt) {
	/* Check that the given field name exists for the given reference type */
	/* Returns an error string if it isn't */
	$rdr = _title_mask__get_rec_detail_requirements();
	$rdt = _title_mask__get_rec_detail_types();
	$rct = _title_mask__get_rec_types();

	$dot_pos = strpos($field_name, '.');
	if ($dot_pos === FALSE) {	/* direct field-name check */
		if (preg_match('/^(\\d+)\\s*(?:-.*)?$/', $field_name, $matches)) {	// field number has been supplied
			if (! array_key_exists($matches[1], $rdr[$rt]))	// field does not exist
				return 'Type "' . $rct[$rt] . '" does not have field #' . $matches[1];
			$rdt_id = $matches[1];
			$rdt_name = 'Field #' . $rdt_id;
		} else {
			if (! array_key_exists(strtolower($field_name), $rdr[$rt]))	// field does not exist
				return 'Type "' . $rct[$rt] . '" does not have "' . $field_name . '" field';
			$rdt_id = $rdt[strtolower($field_name)]['dty_ID'];
			$rdt_name = '"' . $field_name . '" field';
		}

		// check that the field is of a sensible type
		if ($rdt[$rdt_id]['dty_Type'] != 'resource'  ||  $rt == 52) {	// special exception for relationships
			return '';
		} else {
			return $rdt_name . ' in type "' . $rct[$rt] . '" is a resource identifier - that is definitely not what you want.  ' .
					'Try "' . $field_name . '.Title", for example';
		}
	}

	if ($dot_pos == 0  ||  $dot_pos == strlen($field_name)-1)
		return 'Illegal field name (superfluous dot)';

	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		// field number has been supplied
		if (! array_key_exists($matches[1], $rdr[$rt])) {
			return 'Type "' . $rct[$rt] . '" does not have field #' . $matches[1];
		}

		$inner_rec_type = $rdr[$rt][$matches[1]];
		$inner_field_name = $matches[2];
	} else {
		preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches);
		if (! array_key_exists(strtolower($matches[1]), $rdr[$rt])) {
			return 'Type "' . $rct[$rt] . '" does not have "' . $matches[1] . '" field';
		}
		$inner_rec_type = $rdr[$rt][strtolower($matches[1])];
		$inner_field_name = $matches[2];
	}

	if ($inner_rec_type == 0) {
		// an unconstrained pointer: we can't say what fields might be available.
		// just check that the specified field exists.

		if (preg_match('/^(\\d+)\\s*(?:-.*)?$/', $inner_field_name, $matches)) {	// field number has been supplied
			if (! array_key_exists($matches[1], $rdt)) {
				return 'Field type "' . $matches[1] . '" does not exist';
			}
		} else {
			if (! array_key_exists(strtolower($inner_field_name), $rdt)) {
				return 'Field "' . $inner_field_name . '" does not exist';
			}
		}
		return '';
	}

	/* recurse! */
	return _title_mask__check_field_name($inner_field_name, $inner_rec_type);
}


function _title_mask__get_field_value($field_name, $rec_id, $rt) {
// error_log("[$field_name]   [$rec_id]   [$rt]");
	/* Return the value for the given field in the given records record */
	if (strpos($field_name, '.') === FALSE) {	/* direct field-name lookup */
		if (preg_match('/^(\\d+)/', $field_name, $matches)) {
			$rdt_id = $matches[1];
		} else {
			$rdt = _title_mask__get_rec_detail_types();
			$rdt_id = $rdt[strtolower($field_name)]['dty_ID'];
		}

		return _title_mask__get_rec_detail($rec_id, $rdt_id);
	}

	if (! @$rdt) $rdt = _title_mask__get_rec_detail_types();

	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $matches[1];
		$inner_field_name = $matches[2];
	} else if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $rdt[strtolower($matches[1])]['dty_ID'];
		$inner_field_name = $matches[2];
	} else {
		return '';
	}

	$rt_id = $rdt[$rdt_id]['dty_PtrConstraints'];

	$res = mysql_query('select dtl_Value from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID where dtl_RecID='.$rec_id.' and dty_ID='.$rdt_id.' order by dtl_ID asc');

	if ($rt_id != 0  &&  $inner_field_name) {
		if ($rt_id != 75) {	// not an AuthorEditor
			$value = '';
			while ($inner_rec_id = mysql_fetch_row($res)) {
				$inner_rec_id = $inner_rec_id[0];
				$new_value = _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);
				if ($value) $value .= ', ' . $new_value;
				else $value = $new_value;
			}
			return $value;

		} else if (mysql_num_rows($res) == 1) {	// an AuthorEditor
			$inner_rec_id = mysql_fetch_row($res); $inner_rec_id = $inner_rec_id[0];
			if ($inner_rec_id == 'anonymous'  ||  ! intval($inner_rec_id)) {
				if ($inner_field_name == 291  ||  strtolower($inner_field_name) == 'given names')
					return 'Anonymous';
				else return '';
			}
			$inner_rec_id = intval($inner_rec_id);
			return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);

		} else if (mysql_num_rows($res) > 1) {	// multiple AuthorEditors
			$inner_rec_id = mysql_fetch_row($res); $inner_rec_id = $inner_rec_id[0];

			if ($inner_rec_id == 'anonymous'  ||  ! intval($inner_rec_id)) {
				if ($inner_field_name == 291  ||  strtolower($inner_field_name) == 'given names')
					return 'multiple anonymous authors';	// let's hope DDJ finds this fun
				else return '';
			}
			$inner_rec_id = intval($inner_rec_id);

			// only return details for the first author
			// unless we're looking for their GIVEN NAMES (which typically appear last), where we add "et al."
			if ($inner_field_name == 291  ||  strtolower($inner_field_name) == 'given names') {
				return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id) . ' et al.';
			} else {
				return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);
			}
		}

	} else {
		// an unconstrained pointer - don't do any of the craziness above.
		while ($inner_rec_id = mysql_fetch_row($res)) {
			$inner_rec_id = $inner_rec_id[0];
			$new_value = _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);
			if ($value) $value .= ', ' . $new_value;
			else $value = $new_value;
		}
		return $value;
	}
	return '';
}


function _title_mask__get_rec_detail($rec_id, $rdt_id) {
	static $rec_details;
	if (! $rec_details) $rec_details = array();

	if (array_key_exists($rec_id, $rec_details)  &&
	    array_key_exists($rdt_id, $rec_details[$rec_id])  &&
	    $rec_details[$rec_id][$rdt_id] != "") {
		return $rec_details[$rec_id][$rdt_id];
	}

	$rdt = _title_mask__get_rec_detail_types();


	$rec_details[$rec_id] = array();

	$res = mysql_query('select recDetails.* from recDetails'
	                  .' where dtl_RecID = ' . intval($rec_id) . ' order by dtl_ID asc');
	while ($rd = mysql_fetch_assoc($res)) {
		$rdt_type = $rdt[$rd['dtl_DetailTypeID']]['dty_Type'];

		if ($rdt_type == 'file') {	/* handle files specially */
			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = (intval($rec_details[$rec_id][$rd['dtl_DetailTypeID']])+1).' files';
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = '1 file';

		} else if ($rdt_type == 'geo') {	/* handle geographic objects specially */
			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] =
				  (intval($rec_details[$rec_id][$rd['dtl_DetailTypeID']])+1).' geographic objects';
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = '1 geographic object';
		} else if ($rdt_type == 'date') {	/* handle date objects specially */
			$str = trim($rd['dtl_Value']);
			if (strlen($str) > 0 && preg_match("/\|VER/",$str)) { // we have a temporal
				preg_match("/TYP=([s|p|c|f|d])/",$str,$typ);
				switch  ($typ[1]) {
					case 's': // simple date
						preg_match("/DAT=([^\|]+)/",$str,$dat);
						$dat = $dat[1] ? $dat[1] : null;
						preg_match("/COM=([^\|]+)/",$str,$com);
						$com = $com[1] ? $com[1] : null;
						$str = $dat ? $dat :($com ? $com:"temporal simple date");
						break;
					case 'p': // probable date
						preg_match("/TPQ=([^\|]+)/",$str,$tpq);
						$tpq = $tpq[1] ? $tpq[1] : null;
						preg_match("/TAQ=([^\|]+)/",$str,$taq);
						$taq = $taq[1] ? $taq[1] : null;
						preg_match("/PDB=([^\|]+)/",$str,$pdb);
						$pdb = $pdb[1] ? $pdb[1] :($tpq ? $tpq:"");
						preg_match("/PDE=([^\|]+)/",$str,$pde);
						$pde = $pde[1] ? $pde[1] :($taq ? $taq:"");
						$str = "(" . $pdb . " – " . $pde . ")";
						break;
					case 'c': //c14 date
						preg_match("/BCE=([^\|]+)/",$str,$bce);
						$bce = $bce[1] ? $bce[1] : null;
						preg_match("/BPD=([^\|]+)/",$str,$c14);
						$c14 = $c14[1] ? $c14[1] :($bce ? $bce:" c14 temporal");
						$suff = preg_match("/CAL=/",$str) ? " Cal" : "";
						$suff .= $bce ? " BCE" : " BP";
						preg_match("/DVP=P(\d+)Y/",$str,$dvp);
						$dvp = $dvp[1] ? $dvp[1] : null;
						preg_match("/DEV=P(\d+)Y/",$str,$dev);
						$dev = $dev ? " ±" . $dev[1]. " yr". ($dev[1]>1?"s":""):($dvp ? " +" . $dvp . " yr" . ($dvp>1?"s":""):" + ??");
						preg_match("/DVN=P(\d+)Y/",$str,$dvn);
						$dev .= $dvp ? ($dvn[1] ? " -" . $dvn[1] . " yr" . ($dvn[1]>1?"s":""): " - ??") : "";
						$str = "(" . $c14 . $dev . $suff . ")";
						break;
					case 'f': //fuzzy date
						preg_match("/DAT=([^\|]+)/",$str,$dat);
						$dat = $dat[1] ? $dat[1] : null;
						preg_match("/RNG=P(\d*)(Y|M|D)/",$str,$rng);
						$units = $rng[2] ? ($rng[2]=="Y" ? "year" : $rng[2]=="M" ? "month" :$rng[2]=="D" ? "day" :""): "";
						$rng = $rng && $rng[1] ? " ± " . $rng[1] . " " . $units . ($rng[1]>1 ? "s":""): "";
						$str = "(" . $dat . $rng . ")";
						break;
					default:
						$str = "temporal encoded time";
				}
			}

			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] .=', ' . $str;
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = $str;
		} else {
			if ($rdt_type == 'enum'){ //saw Enum change
				$relval = mysql_fetch_assoc(mysql_query("select trm_Label from defTerms where trm_ID = ".$rd['dtl_Value']));
				$rd['dtl_Value'] = $relval['trm_Label'];
			}
			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] .= ', ' . $rd['dtl_Value'];
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = $rd['dtl_Value'];
		}
	}

	return @$rec_details[$rec_id][$rdt_id];
}


function _title_mask__get_rec_types() {
	static $rct;
	if (! $rct) $rct = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', '1');
	return $rct;
}




function _title_mask__get_rec_detail_requirements() {
	static $rdr;

	if (! $rdr) {
		$rdr = array();

		$res = mysql_query('select rst_RecTypeID, dty_ID, lower(dty_Name) as dty_Name, dty_PtrConstraints
		                      from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID
		                     where rst_RequirementType in ("Required", "Recommended", "Optional")');
		while ($row = mysql_fetch_assoc($res)) {
			if (@$rdr[$row['rst_RecTypeID']]) {
				$rdr[$row['rst_RecTypeID']][$row['dty_ID']] = $row['dty_PtrConstraints'];
				$rdr[$row['rst_RecTypeID']][$row['dty_Name']] = $row['dty_PtrConstraints'];
			} else {
				$rdr[$row['rst_RecTypeID']] = array(
					$row['dty_ID'] => $row['dty_PtrConstraints'],
					$row['dty_Name'] => $row['dty_PtrConstraints']
				);
			}
		}
	}

	return $rdr;
}


function _title_mask__get_rec_detail_types() {
	static $rdt;

	if (! $rdt) {
		$rdt = array();

		$res = mysql_query('select dty_ID, dty_Name, dty_Type, dty_PtrConstraints from defDetailTypes');
		while ($row = mysql_fetch_assoc($res)) {
			$rdt[$row['dty_ID']] = $row;
			$rdt[strtolower($row['dty_Name'])] = $row;
		}
	}

	return $rdt;
}


function make_canonical_title_mask($mask, $rt) {
	// convert all name-style substitutions to numerical-style

	if (! $mask) return "[160]";	// title field

	if (! preg_match_all('/\\[\\[|\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
		return $mask;	// nothing to do -- no substitutions

	$replacements = array();
	for ($i=0; $i < count($matches[1]); ++$i) {
		/* $matches[3][$i] contains the field name as supplied (the string that we look up),
		 * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
		 *        (this is what we replace if there is a substitution)
		 * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
		 *        (this is what we replace with an empty string if there is no substitution value available)
		 */
		$value = _title_mask__get_field_number($matches[3][$i], $rt);
		if ($value)
			$replacements[$matches[2][$i]] = "[$value]";
		else
			$replacements[$matches[1][$i]] = "";
	}

	return array_str_replace(array_keys($replacements), array_values($replacements), $mask);
}


function _title_mask__get_field_number($field_name, $rt) {
	// Return the rec-detail-type ID for the given field in the given record type
	if (strpos($field_name, ".") === FALSE) {	// direct field name lookup
		if (preg_match('/^(\\d+)/', $field_name, $matches)) {
			$rdt_id = $matches[1];
		} else {
			$rdt = _title_mask__get_rec_detail_types();
			$rdt_id = $rdt[strtolower($field_name)]['dty_ID'];
		}
		return $rdt_id;
	}

	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $matches[1];
		$inner_field_name = $matches[2];
	} else if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt = _title_mask__get_rec_detail_types();
		$rdt_id = $rdt[strtolower($matches[1])]['dty_ID'];
		$inner_field_name = $matches[2];
	} else {
		return "";
	}


	if ($rdt_id  &&  $inner_field_name) {
		$rdr = _title_mask__get_rec_detail_requirements();
		$inner_rec_type = $rdr[$rt][$rdt_id];
		$inner_rdt = _title_mask__get_field_number($inner_field_name, $inner_rec_type);
		if ($inner_rdt) {
			return $rdt_id . "." . $inner_rdt;
		}
	}

	return "";
}


if (! function_exists('array_str_replace')) {

function array_str_replace($search, $replace, $subject) {
	/*
	 * PHP's built-in str_replace is broken when $search is an array:
	 * it goes through the whole string replacing $search[0],
	 * then starts again at the beginning replacing $search[1], &c.
	 * array_str_replace instead looks for non-overlapping instances of each $search string,
	 * favouring lower-indexed $search terms.
	 *
	 * Whereas str_replace(array("a","b"), array("b", "x"), "abcd") returns "xxcd",
	 * array_str_replace returns "bxcd" so that the user values aren't interfered with.
	 */

	$val = '';

	while ($subject) {
		$match_idx = -1;
		$match_offset = -1;
		for ($i=0; $i < count($search); ++$i) {
			$offset = strpos($subject, $search[$i]);
			if ($offset === FALSE) continue;
			if ($match_offset == -1  ||  $offset < $match_offset) {
				$match_idx = $i;
				$match_offset = $offset;
			}
		}

		if ($match_idx != -1) {
			$val .= substr($subject, 0, $match_offset) . $replace[$match_idx];
			$subject = substr($subject, $match_offset + strlen($search[$match_idx]));
		} else {	// no matches for any of the strings
			$val .= $subject;
			$subject = '';
			break;
		}
	}

	return $val;
}

}


//print check_title_mask('[Title], [Creator]', 4);
// print check_title_mask('[Title], [Creator], [Year] ([Book Series Reference.Publisher Reference.Publisher])', 5);
//print check_title_mask('[Title], [Creator], [Year] ([Book Series Reference.Publisher Reference.Publisher])', 5);
//print fill_title_mask('[Title], [Creator], [Year] ([Book Series Reference.Publisher Reference.Publisher])', 46842, 5) . "\n";


?>
