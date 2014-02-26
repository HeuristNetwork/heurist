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
* utilsTitleMask.php
* Three important functions in this file:
* check_title_mask($mask, $rt) => returns an error string if there is a fault in the given mask for the given reference type
* fill_title_mask($mask, $rec_id, $rt) => returns the filled-in title mask for this bibliographic entry
* titlemask_make($mask, $rt, $mode, $rec_id=null) => converts titlemask to coded, humanreadable or fill mask with values
* Various other utility functions starting with _titlemask__ may be ignored and are unlikely to invade your namespaces.
* 
* Note that title masks have been updated (AO late 2013) to remove the awkward storage of two versions - 'canonical' and human readable. 
* They are now read and used as internal code values (the old 'canonical' form), decoded to human readable for editing, 
* and then recoded back to internal codes for storage, as per original design.
*
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/Heurist
* @version     3.1.6
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  CommonPHP
*/

require_once('Temporal.php');

define('_ERR_REP_WARN', 0); // returns general message that titlemask is invalid - default
define('_ERR_REP_MSG', 1);  // returns detailed error message
define('_ERR_REP_SILENT', 2); // returns empty string

define('_ERROR_MSG', "Please go to Designer View > Essentials > Record types/fields and edit the title mask for this record type");

/**
* Check that the given title mask is well-formed for the given reference type
* Returns an error string describing any faults in the mask.
*
* @param mixed $mask
* @param mixed $rt
* @param mixed $checkempty
*/
function check_title_mask2($mask, $rt, $checkempty) {

    if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
    {
        // no substitutions to make
        return $checkempty?'Title mask must have at least one data field ( in [ ] ) to replace':'';
    }

    $res = titlemask_make($mask, $rt, 1, null, _ERR_REP_MSG);
    if(is_array($res)){
        return $res[0]; // mask is invalid - this is error message
    }else{
        return '';
    }
}

/**
* Execute titlemask - replace tags with values
*
* @param mixed $mask
* @param mixed $rec_id
* @param mixed $rt
*/
function fill_title_mask($mask, $rec_id, $rt=null){
    return titlemask_value($mask, $rec_id);
}

/**
* Execute titlemask - replace tags with values
*
* @param mixed $mask
* @param mixed $rec_id
*/
function titlemask_value($mask, $rec_id) {
   $rec_value = _titlemask__get_record_value($rec_id);
   if($rec_value){
        $rt = $rec_value['rec_RecTypeID'];
        return titlemask_make($mask, $rt, 0, $rec_id, _ERR_REP_WARN);
   }else{
        return "Titlemask not generated. Record ".$rec_id." not found";
   }
}

/*
* Converts titlemask to coded, human readable or fill mask with values
* In case of invalid titlemask it returns either general warning, errormessage or empty string (see $rep_mode)
*
* @param mixed $mask - titlemask
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $rep_mode
* @return string
*/
function titlemask_make($mask, $rt, $mode, $rec_id=null, $rep_mode=_ERR_REP_WARN) {

    if (!$mask) {
        return ($rep_mode==_ERR_REP_WARN)?"Titlemask is not defined":"";
    }

    if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
        return $mask;    // nothing to do -- no substitutions

    $replacements = array();
    for ($i=0; $i < count($matches[1]); ++$i) {
        /* $matches[3][$i] contains the field name as supplied (the string that we look up),
         * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
         *        (this is what we replace if there is a substitution)
         * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
         *        (this is what we replace with an empty string if there is no substitution value available)
         */
         if(!trim($matches[3][$i])) continue;

        $value = _titlemask__fill_field($matches[3][$i], $rt, $mode, $rec_id);
        if(is_array($value)){
            //ERROR
            if($rep_mode==_ERR_REP_WARN){
                return _ERROR_MSG;
            }else if($rep_mode==_ERR_REP_MSG){
                return $value;
            }else{
                return "";
            }
        }else if ($value){
            if($mode==0){ //value
                $replacements[$matches[2][$i]] = $value;
            }else{ //coded
                $replacements[$matches[2][$i]] = "[$value]";
            }
        }else{
            $replacements[$matches[1][$i]] = "";
        }
    }

    if($mode==0){
        $replacements['[['] = '[';
        $replacements[']]'] = ']';
    }

    $title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);

    if($mode==0){
        /* Clean up miscellaneous stray punctuation &c. */
        if (! preg_match('/^\\s*[0-9a-z]+:\\S+\\s*$/i', $title)) {    // not a URI

            $puncts = '-:;,.@#|+=&'; // These are stripped from end of title if no field data follows them
            $puncts2 = '-:;,@#|+=&';
            
            $title = preg_replace('!^['.$puncts.'/\\s]*(.*?)['.$puncts2.'/\\s]*$!s', '\\1', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]+\\)!s', '', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]*(.*?)['.$puncts.'/\\s]*\\)!s', '(\\1)', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]*\\)|\\[['.$puncts.'/\\s]*\\]!s', '', $title);
            $title = preg_replace('!^['.$puncts.'/\\s]*(.*?)['.$puncts2.'/\\s]*$!s', '\\1', $title);
        
/*          TODO: Old version, removed 4th Jan 2014, to delete  
            $title = preg_replace('!^[-:;,./\\s]*(.*?)[-:;,/\\s]*$!s', '\\1', $title);
            $title = preg_replace('!\\([-:;,./\\s]+\\)!s', '', $title);
            $title = preg_replace('!\\([-:;,./\\s]*(.*?)[-:;,./\\s]*\\)!s', '(\\1)', $title);
            $title = preg_replace('!\\([-:;,./\\s]*\\)|\\[[-:;,./\\s]*\\]!s', '', $title);
            $title = preg_replace('!^[-:;,./\\s]*(.*?)[-:;,/\\s]*$!s', '\\1', $title);
*/            
            $title = preg_replace('!,\\s*,+!s', ',', $title);
            $title = preg_replace('!\\s+,!s', ',', $title);
        }
        $title = trim(preg_replace('!  +!s', ' ', $title));
        
        if($title==""){
            if($rep_mode==_ERR_REP_MSG){
                return array(_ERROR_MSG);
            }else{
                return _ERROR_MSG;
            }
        }
    }
    
    return $title;
}

//-------------- inner functions -----------------


/*
* Returns ALL field types definitions and keeps it into static array
*/
function _titlemask__get_detail_types() {
    static $rdt;

    if (! $rdt) {
        $rdt = array();

        $res = mysql_query('select dty_ID, lower(dty_Name) as dty_Name, dty_Name as originalName, dty_Type, '
        .' dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs, dty_Name as dty_NameOrig, '
        .' dty_OriginatingDBID, dty_IDInOriginatingDB from defDetailTypes');

        while ($row = mysql_fetch_assoc($res)) {

            if (is_numeric($row['dty_OriginatingDBID']) && is_numeric($row['dty_IDInOriginatingDB'])) {
                $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
            } else if (HEURIST_DBID) {
                $dt_cc = "" . HEURIST_DBID . "-" . $row['dty_ID'];
            } else {
                $dt_cc = $row['dty_ID'];
            }

            $row['dty_ConceptCode'] = $dt_cc;

            $rdt[$row['dty_ID']] = $row;
            $rdt[$row['dty_Name']] = $row;
            $rdt[$dt_cc] = $row;
        }
    }

    return $rdt;
}

/*
* Fill record type structure
* keeps it in static array
* this array for each given record type
*/
function _titlemask__get_rec_detail_types($rt) {
    static $rdr;

    if (! $rdr) {
        $rdr = array();
    }

   if(!@$rdr[$rt]){

       //dty_Name as dty_NameOrig,

        $query ='select rst_RecTypeID, '
            .' lower(rst_DisplayName) as rst_DisplayName, rst_DisplayName as originalName, '   //lower(dty_Name) as dty_Name,
            .' dty_Type, if(rst_PtrFilteredIDs,rst_PtrFilteredIDs, dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs,'
            .' dty_OriginatingDBID, dty_IDInOriginatingDB, dty_ID '
            .' from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID '
            .' where rst_RequirementType in ("required", "recommended", "optional") '
            .' and rst_RecTypeID='.$rt;

        $res = mysql_query($query);
        $rdr[$rt] = array();

        while ($row = mysql_fetch_assoc($res)) {

            if (is_numeric($row['dty_OriginatingDBID']) && is_numeric($row['dty_IDInOriginatingDB'])) {
                $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
            } else if (HEURIST_DBID) {
                $dt_cc = "" . HEURIST_DBID . "-" . $row['dty_ID'];
            } else {
                $dt_cc = $row['dty_ID'];
            }

            $row['dty_ConceptCode'] = $dt_cc;

            $rdr[$rt][$row['dty_ID']] = $row;
            $rdr[$rt][$row['rst_DisplayName']] = $row;
            $rdr[$rt][$dt_cc] = $row;
        }
    }
    return $rdr[$rt];
}

/*
* load the record values
*
* @param mixed $rec_id
*/
function _titlemask__get_record_value($rec_id) {
/* it leads to memory exhaustion
    static $records;

    if (! $records) {
        $records = array();
    }
*/    
    $records = array();

   if(!@$records[$rec_id]){

       $ret = null;

       $query = 'SELECT rec_ID, rec_Title, rec_Modified, rec_RecTypeID FROM Records where rec_ID='.$rec_id;
       $res = mysql_query($query);
       if($res){
           $row = mysql_fetch_assoc($res);
           if($row){

                $ret = $row;
                $ret['rec_Details'] = array();

                $query = 'SELECT dtl_DetailTypeID, dtl_Value FROM recDetails where dtl_RecID='.$rec_id." order by dtl_DetailTypeID";
                $res = mysql_query($query);
                while ($row = mysql_fetch_array($res)) {
                    array_push($ret['rec_Details'], $row);
                }
           }
       }

       $records[$rec_id] = $ret;
   }
   return $records[$rec_id];
}

/*
* find and return value for enumeration field
*
* @param mixed $enum_id
* @param mixed $enum_param_name
*/
function _titlemask__get_enum_value($enum_id, $enum_param_name)
{
    $ret = null;

        if($enum_param_name==null){
            $enum_param_name = "label";
        }

        $ress = mysql_query("select trm_id, trm_label, trm_code, concat(trm_OriginatingDBID, '-', trm_IDInOriginatingDB) as trm_conceptid from defTerms where trm_ID = ".$enum_id);
        if(!mysql_error()){
            $relval = mysql_fetch_assoc($ress);
            $ret = @$relval['trm_'.strtolower($enum_param_name)];
        }

    return $ret;
}

/*
* Returns value for given detail type
*
* @param mixed $rdt_id - detail type id
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $enum_param_name - name of term field for value mode
* @return mixed
*/
function _titlemask__get_field_value( $rdt_id, $rt, $mode, $rec_id, $enum_param_name=null) {

    if($mode==0){

        $rec_values = _titlemask__get_record_value($rec_id);

        if(!$rec_values){
            return "";
        }else if (strcasecmp($rdt_id,'id')==0){
            return $rec_values['rec_ID'];
        }else if (strcasecmp($rdt_id,'rectitle')==0) {
            return $rec_values['rec_Title'];
        }else if (strcasecmp($rdt_id,'modified')==0) {
            return $rec_values['rec_Modified'];
        }

        $rdt_id = _titlemask__get_dt_field($rt, $rdt_id, 'dty_ID'); //local dt id
        $dt_type = _titlemask__get_dt_field($rt, $rdt_id, 'dty_Type');

        $details = $rec_values['rec_Details'];

        $res = array();
        $found = false;
        foreach($details as $detail){
            if($detail[0]==$rdt_id){
                $found = true;
                if($dt_type=="enum" || $dt_type=="relationtype"){
                    $value = _titlemask__get_enum_value($detail[1], $enum_param_name);
                }else if($dt_type=="date"){
                    $value = temporalToHumanReadableString(trim($detail[1]));
                }else{
                    $value = $detail[1];
                }
                if($value){
                   array_push($res, $value);
                }
            }else if($found){
                break;
            }
        }

        if(count($res)==0){
            return "";
        }else if ($dt_type == 'file'){
            return count($res)." file".(count($res)>1?"s":"");
        }else if ($dt_type == 'geo') {
            return count($res)." geographic object".(count($res)>1?"s":"");
        }else{
            return implode(",", $res);
        }

    }else{

        if (strcasecmp($rdt_id,'id')==0 ||
            strcasecmp($rdt_id,'rectitle')==0 ||
            strcasecmp($rdt_id,'modified')==0){
                return $rdt_id;
        }else if($mode==1){
            return $rdt_id; //concept code
        } else {
            return _titlemask__get_dt_field($rt, $rdt_id, 'originalName');  //original name (with capital chars)
        }
    }
}

/*
* Returns detail type attribute by  dty_ID, rst_DisplayName, dty_ConceptCode
* returns  dty_ConceptCode, dty_Type or original name (not lowercased)
*
* @param mixed $rt - record type
* @param mixed $fieldname  - search value: name of attribute(field) of detail type: dty_ID, rst_DisplayName, dty_ConceptCode
* @param mixed $field - result filed
*/
function _titlemask__get_dt_field($rt, $search_fieldname, $result_fieldname='dty_ConceptCode'){

    $rdr = _titlemask__get_rec_detail_types($rt);

    $search_fieldname = mb_strtolower($search_fieldname, 'UTF-8');
    //$search_fieldname = strtolower($search_fieldname);

    //search in record type structure
    if(@$rdr[$search_fieldname]){  //search by dty_ID, rst_DisplayName, dty_ConceptCode
        return $rdr[$search_fieldname][$result_fieldname];
    }else{
        //if not found in structure - search among all detail types
        $rdt = _titlemask__get_detail_types();
        if(@$rdt[$search_fieldname]){
            return $rdt[$search_fieldname][$result_fieldname];
        }
    }
    return null;
}

/*
* replace title masl tag to value, coded (concept codes) or textual representation
*
* @param mixed $field_name - mask tag
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param $rec_id - record id for value mode
* @return mixed
*/
function _titlemask__fill_field($field_name, $rt, $mode, $rec_id=null) {

    if (is_array($rt)){
        //ERROR
        return array("$field_name was tested with Array of record types - bad parameter");
        // TODO: what does this error message mean? Make it comprehensible to the user
    }

    if (strcasecmp($field_name,'id')==0 ||
        strcasecmp($field_name,'rectitle')==0 ||
        strcasecmp($field_name,'modified')==0)
    {
        $field_val = _titlemask__get_field_value( $field_name, $rt, $mode, $rec_id );
        return $field_val;
    }
    // Return the rec-detail-type ID for the given field in the given record type
    if (strpos($field_name, ".") === FALSE) {    // direct field name lookup
    
        $rdt_id = _titlemask__get_dt_field($rt, $field_name);  //get concept code
        if(!$rdt_id){
            //ERROR
            return array("Field $field_name not recognised");
        }else {
            return _titlemask__get_field_value( $rdt_id, $rt, $mode, $rec_id );
        }
    }

    if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {
        $parent_field_name = $matches[1];
        $rdt_id = _titlemask__get_dt_field($rt, $parent_field_name);
        if($rdt_id){

            $inner_field_name = $matches[2];

            $dt_type = _titlemask__get_dt_field($rt, $rdt_id, 'dty_Type');
            if($dt_type=="enum" || $dt_type=="relationtype"){

                if(!$inner_field_name){
                    $inner_field_name = "label";
                }

                if (strcasecmp($inner_field_name,'id')==0 ||
                strcasecmp($inner_field_name,'label')==0 ||
                strcasecmp($inner_field_name,'code')==0 ||
                strcasecmp($inner_field_name,'conceptid')==0)
                {

                    if($mode==0){
                        return _titlemask__get_field_value( $rdt_id, $rt, $mode, $rec_id, $inner_field_name);
                    }else{
                        return ( ($mode==1) ?$rdt_id :_titlemask__get_dt_field($rt, $rdt_id, 'originalName')) . "." .$inner_field_name;
                    }

                }else{
                    //ERROR
                    return array("$inner_field_name is an unrecognised qualifier for a terms list field");
                }
            }
            if($dt_type== 'relmarker') { //@todo - to implement it in nearest future
                return array("$parent_field_name is relmarker field type. Not supported at the moment");
            }
            if($dt_type!== 'resource') {
                //ERROR
                return array("$parent_field_name must be either enum or resource(record pointer) field type");
            }

        }else{
            //ERROR
            return array("$parent_field_name not recognised");
        }
    } else {
        return "";
    }

    if ($rdt_id  &&  $inner_field_name) {
        if($mode==0){ //replace with values

            //get values for resource field
            $pointer_ids = _titlemask__get_field_value( $rdt_id, $rt, $mode, $rec_id);
            $pointer_ids =  explode(",",$pointer_ids);
            $res = array();
            foreach ($pointer_ids as $rec_id){

                $rec_value = _titlemask__get_record_value($rec_id);
                if($rec_value){
                    $rt = $rec_value['rec_RecTypeID'];
                    $fld_value = _titlemask__fill_field($inner_field_name, $rt, $mode, $rec_id);
                    if(is_array($fld_value)){
                        return $fld_value; //ERROR
                    }else if($fld_value) {
                        array_push($res, $fld_value);
                    }
                }
                //_titlemask__get_field_value( $rdt_id, $rt, $mode, $rec_id) );
            }
            return implode(", ", $res);

        }else{ //convert  coded<->human

            $inner_rec_type = _titlemask__get_dt_field($rt, $rdt_id, 'rst_PtrFilteredIDs'); //$rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
            $inner_rec_type = explode(",",$inner_rec_type);
            foreach ($inner_rec_type as $rtID){
                $rtid = intval($rtID);
                if (!$rtid) continue;
                $inner_rdt = _titlemask__fill_field($inner_field_name, $rtid, $mode);
                if(is_array($inner_rdt)){
                        return $inner_rdt; //ERROR
                }else if ($inner_rdt) {
                    return ( ($mode==1) ?$rdt_id :_titlemask__get_dt_field($rt, $rdt_id, 'originalName')) . "." . $inner_rdt;
                }
            }
            return ( ($mode==1) ?$rdt_id :_titlemask__get_dt_field($rt, $rdt_id, 'originalName')). ($inner_field_name? ".".$inner_field_name:"");

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
        } else {    // no matches for any of the strings
            $val .= $subject;
            $subject = '';
            break;
        }
    }

    return $val;
}

}
?>
