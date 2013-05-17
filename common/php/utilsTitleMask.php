<?php
/*
* Copyright (C) 2005-2013 University of Sydney
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
* TitleMask.php
* Two important functions in this file:
* check_title_mask($mask, $rt) => returns an error string if there is a fault in the given mask for the given reference type
* fill_title_mask($mask, $rec_id, $rt) => returns the filled-in title mask for this bibliographic entry
*
* Various other utility functions starting with _title_mask__ may be ignored and are unlikely to invade your namespaces.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  CommonPHP
*/


require_once('Temporal.php');

//mysql_connection_select(DATABASE);

function check_title_mask($mask, $rt) {
	return check_title_mask2($mask, $rt, false);
}

function check_title_mask2($mask, $rt, $checkempty) {
	/* Check that the given title mask is well-formed for the given reference type */
	/* Returns an error string describing any faults in the mask. */

	if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
		return $checkempty?'Mask must have at least one field to replace':'';	// no substitutions to make, therefore no errors

	foreach ($matches[1] as $field_name) {
		if ( (!$field_name) || $field_name == "ID" || $field_name == "Modified" || $field_name == "RecTitle") continue;
		$err = _title_mask__check_field_name($field_name, $rt);
		if ($err) return $err;
	}

	return '';
}

$relRT = (defined('RT_RELATION')?RT_RELATION:0);
$authRT = (defined('RT_AUTHOR_EDITOR')?RT_AUTHOR_EDITOR:0);
$authorDT = (defined('DT_CREATOR')?DT_CREATOR:0);
$editorDT = (defined('DT_EDITOR')?DT_EDITOR:0);
$titleDT = (defined('DT_NAME')?DT_NAME:0);
$surnameDT = (defined('DT_GIVEN_NAMES')?DT_GIVEN_NAMES:0);
$enum_params = array('id','code','label','conceptid');


/**
*
*
* @param mixed $mask - mask string
* @param mixed $rec_id
* @param mixed $rt
* @return string
*/
function fill_title_mask($mask, $rec_id, $rt)
{
	global $titleDT;
	/* Fill the title mask for the given records record */

	///mask not defined - take value from fieldtype "DT_NAME"
	if (! $mask) return trim(_title_mask__get_field_value($titleDT, $rec_id, $rt));

	if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
		return $mask;	// nothing to do -- no substitutions
/*****DEBUG****///error_log("fill mask matches = ".print_r($matches,true));
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
/*****DEBUG****///error_log("fill mask replacements = ".print_r($replacements,true));

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

/**
* Check that the given field name exists for the given reference type
* Returns an error string if it isn't
*
* @param mixed $field_name
* @param mixed $rt
*/
function _title_mask__check_field_name($field_name, $rt)
{
	global $relRT, $enum_params;

	$rdr = _title_mask__get_rec_detail_requirements();
	$rdt = _title_mask__get_rec_detail_types();
	if (is_array($rt)){
		return "$field_name was tested with Array of rectypes - bad parameter";
	}else if (!$rt){
		return "Record type is not defined";
	}
	$rct = _title_mask__get_rec_types(null);//get all rec types.  AO: it stores in static array - will it be updated if strcture is changed ???
/*****DEBUG****///error_log("fieldname = $field_name and rt = $rt   ".array_key_exists($rt, $rct));
	if (! array_key_exists($rt, $rct) ){
		return "Title mask cannot be tested with the existing record type. Record type $rt not found";
	}
	$dot_pos = strpos($field_name, '.');
	if ($dot_pos === FALSE) {	/* direct field-name check */
		if (preg_match('/^(\\d+)\\s*(?:-.*)?$/', $field_name, $matches)) {	// field number has been supplied
			if (! array_key_exists($matches[1], $rdr[$rt]))	// field does not exist for rectype
				return 'Type "' . $rct[$rt] . '" does not have field #' . $matches[1];
			$rdt_id = $matches[1];
			$rdt_name = $rdr[$rt][$rdt_id]['rst_DisplayName'];
		} else if(strtolower(trim($field_name))=== 'rectitle'){
			return '';
		}else{//fieldname lookup
			if ( array_key_exists(strtolower($field_name), $rdr[$rt])) {	// field name defined for rectype
				$rdt_id = $rdr[$rt][strtolower($field_name)]['dty_ID'];
			}else{
				$rdt_id = $rdt[strtolower($field_name)]['dty_ID'];
			}
			if ($rdt_id) {
				$rdt_name = $rdr[$rt][$rdt_id]['rst_DisplayName'];
			}else{
				return 'Type "' . $rct[$rt] . '" does not have "' . $field_name . '" field';
			}
		}

		// check that the field is of a sensible type
		if ($rdt[$rdt_id]['dty_Type'] != 'resource'  ||  $rt == $relRT) {	// special exception for relationships
			return '';
		} else {
			return $rdt_name . ' in type "' . $rct[$rt] . '" is a resource identifier - that is definitely not what you want.  ' .
					'Try "' . $field_name . '.RecTitle", for example';
		}
	}

	if ($dot_pos == 0  ||  $dot_pos == strlen($field_name)-1)
		return 'Illegal field name (superfluous dot)';
		//find any starting numbers stripping following charaters upto a fullstop
	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		// field number has been supplied
		if (! array_key_exists($matches[1], $rdr[$rt])) {
			return 'Type "' . $rct[$rt] . '" does not have field #' . $matches[1];
		}
		$rdt_id = $matches[1];
		$rdt_type = $rdt[$rdt_id]['dty_Type'];
		if (!( $rdt_type === 'resource' || $rdt_type === 'enum' || $rdt_type === 'relationtype')) {
			return 'Field "'. $rdr[$rdt_id]['rst_DisplayName']. "\" field type \"$rdt_type\" which doesn't support subfields like $matches[2]";
		}
		$inner_rec_type = $rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
		$inner_field_name = $matches[2];
	} else {	// match all characters before and after a fullstop

		if(checkPointerRec($field_name, $rdr[$rt], $matches)){
			$rdt_id = $matches[1];
			if(!$rdt_id){
				preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches);
				$rdt_id = $rdt[strtolower($matches[1])]['dty_ID'];
			}
		}
		if (!$rdt_id) {
			return 'Type "' . $rct[$rt] . '" does not have "' . $matches[1] . '" field';
		}
		$rdt_type = $rdt[$rdt_id]['dty_Type'];

		if (!( $rdt_type === 'resource' || $rdt_type === 'enum' || $rdt_type === 'relationtype')) {
			return 'Field "'. $rdr[$rdt_id]['rst_DisplayName']. "\" id type \"$rdt_type\" which doesn't support subfields like $matches[2]";
		}
		$inner_rec_type = $rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
		$inner_field_name = $matches[2];
	}
/*****DEBUG****///error_log("inner rec type = ".print_r($inner_rec_type,true));

	if (!$inner_rec_type && $inner_field_name) {
		// an unconstrained pointer: we can't say what fields might be available.
		// just check that the specified field exists.

		if (preg_match('/^(\\d+)\\s*(?:-.*)?$/', $inner_field_name, $matches)) {	// field number has been supplied
			if (! array_key_exists($matches[1], $rdt)) {
				return 'Field type "' . $matches[1] . '" does not exist';
			}
		} else {
			if (! array_key_exists(strtolower($inner_field_name), $rdt)&&
					strtolower($inner_field_name) !== 'rectitle') {
				if($rdt_type === 'enum' || $rdt_type === 'relationtype'){

					if(! in_array(strtolower($inner_field_name), $enum_params)){
						return 'Parameter "' . $inner_field_name . '" is not allowed for enumeration field '.$rdr[$rdt_id]['rst_DisplayName'];
					}
				}else{
					return 'Field type "' . $inner_field_name . '" does not exist';
				}
			}
		}
		return '';
	}

	/* recurse! */
	if (strpos($inner_rec_type, ',')){ // multi rt pointer
		$ret = "";
		$inner_rec_type = explode(",",$inner_rec_type);
		foreach ($inner_rec_type as $rtID){
			$rtid = intval($rtID);
			if (!$rtid) {
				continue;
			}

			if ($inner_field_name == "ID" || $inner_field_name == "Modified" || $inner_field_name == "RecTitle") {
				continue;
			}

			$errStr = _title_mask__check_field_name($inner_field_name, $rtid);
			if (!$errStr) return '';
			$ret .= $errStr;
		}
		if($ret) $ret = "multi-rt return = ".$ret;
		return $ret;
	}

	if ($inner_field_name == "ID" || $inner_field_name == "Modified" || $inner_field_name == "RecTitle"){
		return '';
	}else{
		return _title_mask__check_field_name($inner_field_name, $inner_rec_type);
	}
}


/**
* check that field_name is recordpointer fieldname
*
* @param mixed $field_name
* @param mixed $rdr
* @param string $matches
*/
function checkPointerRec($field_name, $rdr, &$matches)
{
	if(preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {

		//specific case - recordtype name ends with point.
		if(substr($matches[2],0,1)=="."){
			$matches[1] = $matches[1].".";
			$matches[2] = substr($matches[2],1);
		}

		$matches[1] = @$rdr[strtolower($matches[1])]['dty_ID'];  //$rdt_id
		$matches[2] = $matches[2]; //$inner_field_name
		return true;
	}else{
		return false;
	}
}


/**
* gets value of field
*
* @param mixed $field_name - name of field
* @param mixed $rec_id - record id to get values
* @param array $rt  - record type
*/
function _title_mask__get_field_value($field_name, $rec_id, $rt)
{
	global $surnameDT, $authRT, $enum_params;

/*****DEBUG****///error_log("[$field_name]  rec [$rec_id]  rty [$rt]");
/*****DEBUG****///error_log(" rt info ".print_r($rt,true));

	if (!$rec_id) { // return blank can't lookup values without a recID
		return '';
	}
	if($field_name=='ID'){
		return $rec_id;
	}
	if (!$rt || $field_name=='Modified') { // lookup the rectype of this
		$resRec = mysql_query("select rec_RecTypeID, rec_Modified  from Records where rec_ID=$rec_id");
		if (mysql_error()) {
			return '';
		}
		$rt = mysql_fetch_row($resRec);
		if($field_name=='Modified'){
			return $rt[1];
		}
		$rt = $rt[0];
	}

//	if (! @$rdt) $rdt = _title_mask__get_rec_detail_types();
	if (! @$rdr) $rdr = _title_mask__get_rec_detail_requirements();

	//check if this is term (enum or relationship)
	if(strpos($field_name, '.')>0){
		$rdt_id = 0;
		if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
			$rdt_id = $matches[1];
			$enum_param_name = $matches[2];
		// match all characters before and after a fullstop
		} else if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {

			$rdts = @$rdr[$rt][strtolower($matches[1])];
			if($rdts) $rdt_id = $rdts['dty_ID'];
			$enum_param_name = $matches[2];
		}
/*****DEBUG****///error_log("field mask matches = ".print_r($matches,true));
		if($rdt_id>0 && in_array(strtolower($enum_param_name), $enum_params)){ //this is enum
/*****DEBUG****///error_log("expand enumeration".print_r($enum_param_name,true));
			$val = _title_mask__get_enum_value($rec_id, $rdt_id, strtolower($enum_param_name));
			if($val!=''){
				return $val;
			}
		}
	}

	/* Returns the value for the given field in the given records record */
	if (strpos($field_name, '.') === FALSE) {	/* this is field name - direct field-name lookup */

		if (preg_match('/^(\\d+)/', $field_name, $matches)) {	// field is dtyID
			$rdt_id = $matches[1];
		} else {	// do a field name lookup
			if (!is_array($rt)) {
				$rdt_id = @$rdr[$rt][strtolower($field_name)]['dty_ID'];
			}else{
				foreach ( $rt as $rtID) {
					if (@$rdr[$rtID] && array_key_exists(strtolower($field_name), $rdr[$rtID])){
						$rdt_id = @$rdr[$rtID][strtolower($field_name)]['dty_ID'];
						break;
					}
				}
/*****DEBUG****///error_log("rdt ID ".print_r($rdt_id,true));
				if (!@$rdt_id && strtolower($field_name) !== "rectitle") {
/*****DEBUG****///error_log(" rdr rt $field_name info ".print_r(@$rdr[$rtID][strtolower($field_name)],true));
					return "'$field_name' field not defined for rectype ".join(",",$rt);
				}
			}
/*****DEBUG****///error_log(" rdr rt info ".print_r($rdr[$rt][strtolower($field_name)],true));
			if (!@$rdt_id && strtolower($field_name) === "title") {
				return '"title" field not defined for rectype '.$rt;
			}else if (!@$rdt_id || strtolower($field_name) === "rectitle") {

				$resRec = mysql_query("select rec_Title from Records where rec_ID=$rec_id");
				if (mysql_error()) {
					return '';
				}else if (@$rtd_id) {
					return 'error - reserved word "rectitle" used as field name in rectype '.$rt;
				}
				$title = mysql_fetch_row($resRec);
				$title = $title[0];
				return $title;
			}
		}
/*****DEBUG****///error_log("$rt field $field_name 's dty ID ".print_r($rdt_id,true));

		return _title_mask__get_rec_detail($rec_id, $rdt_id);
	}

	//find any starting numbers stripping following charaters upto a fullstop
	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $matches[1];
		$inner_field_name = $matches[2];
	// match all characters before and after a fullstop
	} else if (checkPointerRec($field_name, $rdr[$rt], $matches)) {

		$rdt_id = $matches[1]; //@$rdr[$rt][strtolower($matches[1])]['dty_ID'];
		$inner_field_name = $matches[2];
	} else {	// doesn't match a title mask pattern so return an empty string so nothing is added to title
		return '';
	}
	$rt_id = @$rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
	$rt_id = $rt_id ? explode(",",$rt_id) : 0;

	$res = mysql_query('select dtl_Value from recDetails
							left join defDetailTypes on dty_ID=dtl_DetailTypeID
							where dtl_RecID='.$rec_id.' and dty_ID='.$rdt_id.' order by dtl_ID asc');
	$value = '';

	if ($rt_id != 0 &&  $inner_field_name) { //reference to another record
	//TODO: error $rt_id could be array
	//todo: need to adjust the following code to check if dty Typei is Author or Editor
		if ($rt_id != $authRT) {	// not an AuthorEditor
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
				if ($inner_field_name == $surnameDT  ||  strtolower($inner_field_name) == 'given names')
					return 'Anonymous';
				else return '';
			}
			$inner_rec_id = intval($inner_rec_id);
			return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);

		} else if (mysql_num_rows($res) > 1) {	// multiple AuthorEditors
			$inner_rec_id = mysql_fetch_row($res); $inner_rec_id = $inner_rec_id[0];

			if ($inner_rec_id == 'anonymous'  ||  ! intval($inner_rec_id)) {
				if ($inner_field_name == $surnameDT  ||  strtolower($inner_field_name) == 'given names')
					return 'multiple anonymous authors';	// let's hope DDJ finds this fun
				else return '';
			}
			$inner_rec_id = intval($inner_rec_id);

			// only return details for the first author
			// unless we're looking for their GIVEN NAMES (which typically appear last), where we add "et al."
			if ($inner_field_name == $surnameDT  ||  strtolower($inner_field_name) == 'given names') {
				return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id) . ' et al.';
			} else {
				return _title_mask__get_field_value($inner_field_name, $inner_rec_id, $rt_id);
			}
		}

	} else if(!mysql_error()){
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

/**
* get value of specific parameter of enumeration field
*/
function _title_mask__get_enum_value($rec_id, $rdt_id, $enum_param_name)
{
	$resval = null;

	//find enum values in details
	$res = mysql_query('
		select rd.dtl_value from recDetails rd, defDetailTypes dt where dtl_DetailTypeId=dty_Id and dtl_RecId='.
		intval($rec_id).' and dt.dty_Type="enum" and dty_Id='.intval($rdt_id) );

	while ($rd = mysql_fetch_array($res)) {

		$ress = mysql_query("select trm_id, trm_label, trm_code, concat(trm_OriginatingDBID, '-', trm_IDInOriginatingDB) as trm_conceptid from defTerms where trm_ID = ".$rd[0]);
		if(!mysql_error()){
			$relval = mysql_fetch_assoc($ress);
			$str = $relval['trm_'.$enum_param_name];

			if ($resval==null)
				$resval = $str;
			else
				$resval .=', ' . $str;
		}
	}

	return $resval;
}

/**
* retuns value of given detail id for record id
*
* @param mixed $rec_id
* @param mixed $rdt_id - detail type id
* @return mixed
*/
function _title_mask__get_rec_detail($rec_id, $rdt_id)
{
	static $rec_details;
	if (! $rec_details) $rec_details = array();

	if (array_key_exists($rec_id, $rec_details)  &&
		array_key_exists($rdt_id, $rec_details[$rec_id])  &&
		$rec_details[$rec_id][$rdt_id] != "") {
		return $rec_details[$rec_id][$rdt_id];
	}

	$rdt = _title_mask__get_rec_detail_types();


	$rec_details[$rec_id] = array();

	$res = mysql_query('select recDetails.* from recDetails'.
						' where dtl_RecID = ' . intval($rec_id) . ' order by dtl_ID asc');

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

			$str = temporalToHumanReadableString($str);
/*
			if (strlen($str) > 0 && preg_match("/\|VER/",$str)) { // we have a temporal
				preg_match("/TYP=([s|p|c|f|d])/",$str,$typ);
				switch  ($typ[1]) {
					case 's': // simple date
						preg_match("/DAT=([^\|]+)/",$str,$dat);
						$dat = (count($dat)>1 && $dat[1]) ? $dat[1] : null;
						preg_match("/COM=([^\|]+)/",$str,$com);
						$com = (count($com)>1 && $com[1]) ? $com[1] : null;
						$str = $dat ? $dat :($com ? $com:"temporal simple date");
						break;
					case 'p': // probable date
						preg_match("/TPQ=([^\|]+)/",$str,$tpq);
						$tpq = (count($tpq)>1 && $tpq[1]) ? $tpq[1] : null;
						preg_match("/TAQ=([^\|]+)/",$str,$taq);
						$taq = (count($taq)>1 && $taq[1]) ? $taq[1] : null;
						preg_match("/PDB=([^\|]+)/",$str,$pdb);
						$pdb = (count($pdb)>1 && $pdb[1]) ? $pdb[1] :($tpq ? $tpq:"");
						preg_match("/PDE=([^\|]+)/",$str,$pde);
						$pde = (count($pde)>1 && $pde[1]) ? $pde[1] :($taq ? $taq:"");
						$str = $pdb . " – " . $pde;
						break;
					case 'c': //c14 date
						preg_match("/BCE=([^\|]+)/",$str,$bce);
						$bce = (count($bce)>1 && @$bce[1]) ? $bce[1] : null;
						preg_match("/BPD=([^\|]+)/",$str,$c14);
						$c14 = @$c14[1] ? $c14[1] :(@$bce ? $bce:" c14 temporal");
						$suff = preg_match("/CAL=/",$str) ? " Cal" : "";
						$suff .= $bce ? " BCE" : " BP";
						preg_match("/DVP=P(\d+)Y/",$str,$dvp);
						$dvp = (count($dvp)>1 && $dvp[1]) ? $dvp[1] : null;
						preg_match("/DEV=P(\d+)Y/",$str,$dev);
						$dev = $dev ? " ±" . $dev[1]. " yr". ($dev[1]>1?"s":""):($dvp ? " +" . $dvp . " yr" . ($dvp>1?"s":""):" + ??");
						preg_match("/DVN=P(\d+)Y/",$str,$dvn);
						$dev .= $dvp ? ($dvn[1] ? " -" . $dvn[1] . " yr" . ($dvn[1]>1?"s":""): " - ??") : "";
						$str = "(" . $c14 . $dev . $suff . ")";
						break;
					case 'f': //fuzzy date
						preg_match("/DAT=([^\|]+)/",$str,$dat);
						$dat = (count($dat)>1 && $dat[1]) ? $dat[1] : null;
						preg_match("/RNG=P(\d*)(Y|M|D)/",$str,$rng);
//						error_log("title mask match rng - ".print_r($rng,true));
						$units = ($rng[2] ? ($rng[2]=="Y" ? "year" : ($rng[2]=="M" ? "month" : ($rng[2]=="D" ? "day" :""))): "");
						$rng = ($rng && $rng[1] ? " ± " . $rng[1] . " " . ($units ? $units . ($rng[1]>1 ? "s":""):""): "");
						$str = $dat . $rng;
						break;
					default:
						$str = "temporal encoded time";
				}
			}
*/
			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] .=', ' . $str;
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = $str;
		} else {
			if ($rdt_type == 'enum' || $rdt_type == 'relationtype'){ //substitute term for it's id
				$ress = mysql_query("select trm_Label from defTerms where trm_ID = ".$rd['dtl_Value']);
				if(!mysql_error()){
					$relval = mysql_fetch_assoc($ress);
					$rd['dtl_Value'] = $relval['trm_Label'];
				}


			/*
			$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = 'enum';
			$rec_details[$rec_id][$rd['dtl_DetailTypeID']]['id'] = 'label enum';
			$rec_details[$rec_id][$rd['dtl_DetailTypeID']]['code']
			$rec_details[$rec_id][$rd['dtl_DetailTypeID']]['label']
			$rec_details[$rec_id][$rd['dtl_DetailTypeID']]['conceptid']
			*/



			}
			if (@$rec_details[$rec_id][$rd['dtl_DetailTypeID']])// repeated values
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] .= ', ' . $rd['dtl_Value'];
			else
				$rec_details[$rec_id][$rd['dtl_DetailTypeID']] = $rd['dtl_Value'];
		}
	}

	return @$rec_details[$rec_id][$rdt_id];
}


function _title_mask__get_rec_types($rt) {
	static $rct;
	if (! $rct) {

		$cond = ($rt) ?'rty_ID='.$rt :'1';

		$rct = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', $cond);
/*****DEBUG****///error_log("rt ".print_r($rct,true));
	}
	return $rct;
}

/*
*
*/
function _title_mask__get_rec_detail_requirements() {
	static $rdr;

	if (! $rdr) {
		$rdr = array();

		$res = mysql_query('select rst_RecTypeID, dty_ID, lower(dty_Name) as dty_Name, lower(rst_DisplayName) as rst_DisplayName,
									if(rst_PtrFilteredIDs,rst_PtrFilteredIDs, dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs
								from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID
								where rst_RequirementType in ("required", "recommended", "optional")
								order by rst_RecTypeID, dty_ID' );
		while ($row = mysql_fetch_assoc($res)) {
			if (@$rdr[$row['rst_RecTypeID']]) {
				$rdr[$row['rst_RecTypeID']][$row['dty_ID']] = $row;
				$rdr[$row['rst_RecTypeID']][$row['dty_Name']] = $row;
			} else {
				$rdr[$row['rst_RecTypeID']] = array(
					$row['dty_ID'] => $row,
					$row['dty_Name'] => $row
				);
			}
			if (!@$rdr[$row['rst_RecTypeID']][$row['rst_DisplayName']]) {
				$rdr[$row['rst_RecTypeID']][$row['rst_DisplayName']] = $row;
			}
		}
/*****DEBUG****///error_log("rf ".print_r($rdr,true));
	}
	return $rdr;
}

/**
* Returns ALL (AO: !!!!!) field types definitions into static array
*/
function _title_mask__get_rec_detail_types() {
	static $rdt;

	if (! $rdt) {
		$rdt = array();

		$res = mysql_query('select dty_ID, lower(dty_Name) as dty_Name, dty_Type, dty_PtrTargetRectypeIDs from defDetailTypes');
		while ($row = mysql_fetch_assoc($res)) {
			$rdt[$row['dty_ID']] = $row;
			$rdt[strtolower($row['dty_Name'])] = $row;
		}
/*****DEBUG****///error_log("dt ".print_r($rdt,true));
	}

	return $rdt;
}


function make_canonical_title_mask($mask, $rt) {
global $titleDT;
	// convert all name-style substitutions to numerical-style

	if (! $mask) return "[$titleDT]";	// title field

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
	$rdt = _title_mask__get_rec_detail_types();
	$rdr = _title_mask__get_rec_detail_requirements();

	if (is_array($rt)){
		return "$field_name was tested with Array of rectypes - bad parameter";
	}
/*****DEBUG****///error_log("fieldname = $field_name and rt = $rt");
	if(strtolower($field_name) == 'rectitle') {
		return $field_name;
	}
	// Return the rec-detail-type ID for the given field in the given record type
	if (strpos($field_name, ".") === FALSE) {	// direct field name lookup
		if (preg_match('/^\s*(\\d+)\s*/', $field_name, $matches)) {
			$rdt_id = $matches[1];
		} else {
			$rdt_id = $rdr[$rt][strtolower($field_name)]['dty_ID'];
			if (!$rdt_id) $rdt_id = $rdt[strtolower($field_name)]['dty_ID'];
		}
		return $rdt_id ? $rdt_id : $field_name;
	}

	if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $matches[1];
		if (!array_key_exists($rdt_id,$rdt) ||
				!array_key_exists($rdt_id,$rdr[$rt]) ||
				$rdt[$rdt_id]['dty_Type'] !== 'resource') {
			return "invalid field id $rdt_id";
		}
		$inner_field_name = $matches[2];
	} else if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {
		$rdt_id = $rdr[$rt][strtolower($matches[1])]['dty_ID'];
		if (!$rdt_id) $rdt_id = $rdt[strtolower($matches[1])]['dty_ID'];
		if (!$rdt_id ||
				!array_key_exists($rdt_id,$rdt) ||
				!array_key_exists($rdt_id,$rdr[$rt]) ||
				$rdt[$rdt_id]['dty_Type'] !== 'resource') {
/*****DEBUG****///error_log("dt = ".print_r($rdt[$rdt_id],true));
/*****DEBUG****///error_log("fld = ".print_r($rdr[$rt][$rdt_id],true));
			return "invalid field $matches[1] of type ".$rdt[$rdt_id]['dty_Type'];
		}
		$inner_field_name = $matches[2];
	} else {
		return "";
	}

	if ($rdt_id  &&  $inner_field_name) {
		$inner_rec_type = $rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
		$inner_rec_type = explode(",",$inner_rec_type);
		foreach ($inner_rec_type as $rtID){
			$rtid = intval($rtID);
			if (!$rtid) continue;
			$inner_rdt = _title_mask__get_field_number($inner_field_name, $rtid);
			if ($inner_rdt) {
				return $rdt_id . "." . $inner_rdt;
			}
		}
		return $rdt_id. ($inner_field_name? ".".$inner_field_name:"");
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
/*****DEBUG****///error_log(">>>>".$subject." IN ".$search[$i]);
			if($search[$i]==null || $search[$i]=='') continue;
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

?>
