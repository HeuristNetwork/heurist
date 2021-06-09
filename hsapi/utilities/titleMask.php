<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* titleMask.php
* static class with 
* Three MAIN methods
*
*   check($mask, $rt) => returns an error string if there is a fault in the given mask for the given record type
*   fill($mask, $rec_id, $rt) => returns the filled-in title mask for this record entry
*   execute($mask, $rt, $mode, $rec_id=null) => converts titlemask to coded, humanreadable or fill mask with values
*
*
* Note that title masks have been updated (Artem Osmakov late 2013) to remove the awkward storage of two versions - 'canonical' and human readable.
* They are now read and used as internal code values (the old 'canonical' form), decoded to human readable for editing,
* and then recoded back to internal codes for storage, as per original design.
*
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.6
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  CommonPHP
*/
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');


define('_ERR_REP_WARN', 0); // returns general message that titlemask is invalid - default
define('_ERR_REP_MSG', 1);  // returns detailed error message
define('_ERR_REP_SILENT', 2); // returns empty string

define('_ERROR_MSG', "Invalid title constructor: please define the title mask for this record type via link at top of record structure editor");
define('_EMPTY_MSG', "**** No data in title fields for this record ****");

//
// static class
//
class TitleMask {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $system = null;
    private static $mysqli = null;
    private static $db_regid = 0;
    private static $initialized = false;

    private static $fields_correspondence = null;
    private static $rdt = null;  //detail types array indexed by id,name and concept code
    private static $rdr = null;  //record detail types
    //private static $rectypes = null;
    private static $records = null;
    
    //private static $DT_PARENT_ENTITY = 0;

    public static function initialize()
    {
        
        if (self::$initialized)
            return;

        global $system;
        self::$system = $system;
        self::$mysqli = $system->get_mysqli();
        self::$db_regid = $system->get_system('sys_dbRegisteredID');
        self::$initialized = true;
        
        $system->defineConstant('DT_PARENT_ENTITY');
    }
    
    public static function set_fields_correspondence($fields_correspondence){
        self::$fields_correspondence = $fields_correspondence;
    }
    
/**
* Check that the given title mask is well-formed for the given reference type
* Returns an error string describing any faults in the mask.
*
* @param mixed $mask
* @param mixed $rt
* @param mixed $checkempty
*/
 public static function check($mask, $rt, $checkempty) {
     
    self::initialize(); 

    if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
    {
        // no substitutions to make
        return $checkempty?'Title mask must have at least one data field ( in [ ] ) to replace':'';
    }

    $res = self::execute($mask, $rt, 1, null, _ERR_REP_MSG);
    if(is_array($res)){
        return $res[0]; // mask is invalid - this is error message
    }else{
        return "";
    }
}

/**
* Execute titlemask - replace tags with values
*
* @param mixed $mask
* @param mixed $rec_id
* @param mixed $rt
*/
public static function fill($rec_id, $mask=null){
    
    self::initialize();
    
    $rec_value = self::__get_record_value($rec_id, true);
    if($rec_value){
        if($mask==null){
            $mask = $rec_value['rty_TitleMask'];
        }
        $rt = $rec_value['rec_RecTypeID'];
        return self::execute($mask, $rt, 0, $rec_id, _ERR_REP_WARN);
    }else{
        return "Title mask not generated. Record ".$rec_id." not found";
    }
}

/*
* Converts titlemask to coded, human readable or fill mask with values
* In case of invalid titlemask it returns either general warning, error message or empty string (see $rep_mode)
*
* @param mixed $mask - titlemask
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 to coded, 2 - to human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $rep_mode - output in case failure: 0 - general message(_ERR_REP_WARN), 1- detailed message, 2 - empty string (_ERR_REP_SILENT)
* @return string
*/
public static function execute($mask, $rt, $mode, $rec_id=null, $rep_mode=_ERR_REP_WARN) {

    self::initialize();

    if(self::$fields_correspondence!=null){
        self::$rdr = null;
    }

    if($rec_id){
        self::__get_record_value($rec_id, true); //keep recvalue in static
    }

    if (!$mask) {
        return ($rep_mode!=_ERR_REP_SILENT)?"Title mask is not defined": ($mode==0?self::__get_forempty($rec_id, $rt):"");
    }

    //find inside brackets
    if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
        return $mask;    // nothing to do -- no substitutions

    $replacements = array();
    $len = count($matches[1]);
    $fields_err = 0;
    $fields_blank = 0;
    for ($i=0; $i < $len; ++$i) {
        /* $matches[3][$i] contains the field name as supplied (the string that we look up),
        * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
        *        (this is what we replace if there is a substitution)
        * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
        *        (this is what we replace with an empty string if there is no substitution value available)
        */

        if(!trim($matches[3][$i])) continue; //empty []

        $value = self::__fill_field($matches[3][$i], $rt, $mode, $rec_id);

        if(is_array($value)){
            //ERROR
            if($rep_mode==_ERR_REP_WARN){
                return _ERROR_MSG;
            }else if($rep_mode==_ERR_REP_MSG){
                return $value;
            }else{
                $replacements[$matches[1][$i]] = "";
                $fields_err++; //return "";
            }
        }else if (null==$value || trim($value)==""){
            $replacements[$matches[1][$i]] = "";
            $fields_blank++;

        }else{
            if($mode==0){ //value
                $replacements[$matches[2][$i]] = $value;
            }else{ //coded or human readable
                $replacements[$matches[2][$i]] = "[$value]";
            }
        }
    }

    if($mode==0){
        if($fields_err==$len){
            return self::__get_forempty($rec_id, $rt);
        }
        $replacements['[['] = '[';
        $replacements[']]'] = ']';
    }

    $title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);

    if($mode==0){  //fill the mask with values


        if($fields_blank==$len && $rec_id){ //If all the title mask fields are blank
            $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
        }

        /* Clean up miscellaneous stray punctuation &c. */
        if (! preg_match('/^\\s*[0-9a-z]+:\\S+\\s*$/i', $title)) {    // not a URI
        
            $puncts = '-:;,.@#|+=&(){}'; // These are stripped from begining and end of title
            $puncts2 = '-:;,@#|+=&'; // same less period

            $title = preg_replace('!^['.$puncts.'/\\s]*(.*?)['.$puncts2.'/\\s]*$!s', '\\1', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]+\\)!s', '', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]*(.*?)['.$puncts.'/\\s]*\\)!s', '(\\1)', $title);
            $title = preg_replace('!\\(['.$puncts.'/\\s]*\\)|\\[['.$puncts.'/\\s]*\\]!s', '', $title);
            $title = preg_replace('!^['.$puncts.'/\\s]*(.*?)['.$puncts2.'/\\s]*$!s', '\\1', $title);
            $title = preg_replace('!,\\s*,+!s', ',', $title);
            $title = preg_replace('!\\s+,!s', ',', $title);
            
            
        }
        $title = trim(preg_replace('!  +!s', ' ', $title)); //remove double spaces

        if($title==""){

            if($rep_mode==_ERR_REP_SILENT){
                $title = self::__get_forempty($rec_id, $rt);
            }else if($rep_mode==_ERR_REP_MSG){
                return array(_EMPTY_MSG);
            }else{
                return _EMPTY_MSG;
            }
        }
    }

    return $title;
}

//-------------- private methods -----------------

/**
* If the title mask is blank or contains no valid fields, build the title using the values of the first three
* data fields (excluding memo fields) truncated to 40 characters if longer, separated with pipe symbols
*/
private static function __get_forempty($rec_id, $rt){

    $rdr = self::__get_rec_detail_types($rt);
    //$rec_values = self::__get_record_value($rec_id);

    $allowed = array("freetext", "enum", "float", "date", "relmarker", "integer", "year", "boolean");
    $cnt = 0;
    $title = array();
    foreach($rdr as $dt_id => $detail){
        if( is_numeric($dt_id) && in_array($detail['dty_Type'], $allowed) && $detail['rst_RequirementType']!='forbidden'){
            $val = self::__get_field_value($dt_id, $rt, 0, $rec_id);
            $val = trim(substr($val,0,40));
            if($val){
                array_push($title, $val);
                $cnt++;
                if($cnt>2) break;
            }
        }
    }
    $title = implode("|", $title);
    if(!$title){
        $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
    }
    return $title;
}


/*
* Returns ALL field types definitions and keeps it into static array
*/
private static function __get_detail_types() {

    if (! self::$rdt) {
        self::$rdt = array();

        $res = self::$mysqli->query('select dty_ID, lower(dty_Name) as dty_Name, dty_Name as originalName, dty_Type, '
            .' dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs, dty_Name as dty_NameOrig, '
            .' dty_OriginatingDBID, dty_IDInOriginatingDB from defDetailTypes');

        if ($res){    
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {
                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } else if (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                self::$rdt[$row['dty_ID']] = $row;
                self::$rdt[$row['dty_Name']] = $row;
                self::$rdt[$dt_cc] = $row;
            }
            $res->close();
        }
    }

    return self::$rdt;
}

/*
* Fill record type structure
* keeps it in static array
* this array for each given record type
*/
private static function __get_rec_detail_types($rt) {

    if (!self::$rdr) {
        self::$rdr = array();
    }

    if(!@self::$rdr[$rt]){

        //dty_Name as dty_NameOrig,

        $query ='select rst_RecTypeID, '
        .' lower(rst_DisplayName) as rst_DisplayName, rst_DisplayName as originalName, '   //lower(dty_Name) as dty_Name,
        .' dty_Type, if(rst_PtrFilteredIDs,rst_PtrFilteredIDs, dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs,'
        .' dty_OriginatingDBID, dty_IDInOriginatingDB, dty_ID, rst_RequirementType '
        .' from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID '
        .' where '//since 2017-11-25 rst_RequirementType in ("required", "recommended", "optional") and '
        .' rst_RecTypeID='.$rt
        .' order by rst_DisplayOrder';

        $res = self::$mysqli->query($query);
        
        if($res){
            self::$rdr[$rt] = array();
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {

                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } else if (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                //keep 3 indexes by id, name and concept code    
                self::$rdr[$rt][$row['dty_ID']] = $row;
                self::$rdr[$rt][$row['rst_DisplayName']] = $row;
                self::$rdr[$rt][$dt_cc] = $row;
            }
            $res->close();
        }
    }
    return self::$rdr[$rt];

}

/*
* load the record values (except forbidden fields)
*
* @param mixed $rec_id
*/
private static function __get_record_value($rec_id, $reset=false) {

/*    
    $memory_limit = get_php_bytes('memory_limit');
    $mem_used = memory_get_usage();
    if($mem_used>$memory_limit-104857600){ //100M
    
    }
*/    
    //if not reset it leads to memory exhaustion
    //$reset = true;
    if ($reset || !is_array(self::$records) || count(self::$records)>1000) {
        self::$records = array();
    }

    if(!@self::$records[$rec_id]){

        $ret = null;

        $query = 'SELECT rec_ID, rec_Title, rec_Modified, rec_RecTypeID, rty_Name, rty_TitleMask '
                    .'FROM Records, defRecTypes where rec_RecTypeID=rty_ID and rec_ID='.$rec_id;
        $res = self::$mysqli->query($query);
        if($res){
            $row = $res->fetch_assoc();
            if($row){

                $ret = $row;
                $ret['rec_Details'] = array();

                //trim(substr(dtl_Value,0,300)) as 
                $query = 'SELECT dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType '
                .'FROM recDetails LEFT JOIN defRecStructure '
                .'ON rst_RecTypeID='.$ret['rec_RecTypeID']
                   .' AND rst_DetailTypeID=dtl_DetailTypeID ' 
                   .' WHERE dtl_RecID='.$rec_id." order by dtl_DetailTypeID";
                $res2 = self::$mysqli->query($query);
                while ($row = $res2->fetch_assoc()){
                    if($row['rst_RequirementType']!='forbidden'){
                        array_push($ret['rec_Details'], $row);
                    }
                }
                $res2->close();
            }
            $res->close();
        }

        self::$records[$rec_id] = $ret;
    }
    return self::$records[$rec_id];
}

/*
* find and return value for enumeration field
*
* @param mixed $enum_id
* @param mixed $enum_param_name
*/
private static function __get_enum_value($enum_id, $enum_param_name)
{
    $ret = null;

    if($enum_param_name==null || strcasecmp($enum_param_name,'term')==0){
        $enum_param_name = "label";
    }else if(strcasecmp($enum_param_name,'internalid')==0){
        $enum_param_name = "id";
    }

    $ress = self::$mysqli->query("select trm_id, trm_label, trm_code, concat(trm_OriginatingDBID, '-', trm_IDInOriginatingDB) as trm_conceptid from defTerms where trm_ID = ".$enum_id);
    if($ress){
        $relval = $ress->fetch_assoc();
        $ret = @$relval['trm_'.mb_strtolower($enum_param_name, 'UTF-8')];
        $ress->close();
    }

    return $ret;
}

//
//
//
private static function __get_file_name($ulf_ID){

    if($ulf_ID>0){
        $fileinfo = fileGetFullInfo(self::$system, $ulf_ID);
        if(is_array($fileinfo) && count($fileinfo)>0){
            return $fileinfo[0]['ulf_OrigFileName'];
            //  array("file" => $fileinfo[0], "fileid"=>$fileinfo[0]["ulf_ObfuscatedFileID"]);
        }        
    }
    return '';
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
private static function __get_field_value( $rdt_id, $rt, $mode, $rec_id, $enum_param_name=null) {

    if($mode==0){

        $rec_values = self::__get_record_value($rec_id);

        if(!$rec_values){
            return "";
        }else if (strcasecmp($rdt_id,'id')==0){
            return $rec_values['rec_ID'];
        }else if (strcasecmp($rdt_id,'rectitle')==0) {
            return $rec_values['rec_Title'];
        }else if (strcasecmp($rdt_id,'rectypeid')==0) {
            return $rec_values['rec_RecTypeID'];
        }else if (strcasecmp($rdt_id,'rectypename')==0) {
            return $rec_values['rty_Name'];
        }else if (strcasecmp($rdt_id,'modified')==0) {
            return $rec_values['rec_Modified'];
        }

        $rdt_id = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_ID'); //local dt id
        $dt_type = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_Type');

        $details = $rec_values['rec_Details'];

        //dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType
        $res = array();
        $found = false;
        foreach($details as $detail){
            if($detail['dtl_DetailTypeID']==$rdt_id){
                $found = true;
                if($dt_type=="enum" || $dt_type=="relationtype"){
                    $value = self::__get_enum_value($detail['dtl_Value'], $enum_param_name);
                }else if($dt_type=="date"){
                    $value = temporalToHumanReadableString(trim($detail['dtl_Value']));
                }else if($dt_type=="file"){
                    $value = self::__get_file_name($detail['dtl_UploadedFileID']);
                }else{
                    $value = $detail['dtl_Value'];
                }
                if($value!=null && $value!=''){
                    array_push($res, $value);
                }
            }else if($found){
                break;
            }
        }

        if(count($res)==0){
            return "";
        /*}else if ($dt_type == 'file'){
            return count($res)." file".(count($res)>1?"s":"");*/
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
        }else if($mode==1){ //convert to
            return $rdt_id; //concept code
        } else {
            return self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');  //original name (with capital chars)
        }
    }
}

/*
* Returns detail type attribute by  dty_ID, rst_DisplayName, dty_ConceptCode
* returns  dty_ConceptCode, dty_Type or original name (not lowercased)
*
* @param mixed $rt - record type
* @param mixed $search_fieldname  - search value: name of attribute(field) of detail type: dty_ID, rst_DisplayName, dty_ConceptCode
* @param mixed $result_fieldname - result filed
*/
private static function __get_dt_field($rt, $search_fieldname, $mode, $result_fieldname='dty_ConceptCode'){

    $rdr = self::__get_rec_detail_types($rt);

    $search_fieldname = mb_strtolower($search_fieldname, 'UTF-8');
    //$search_fieldname = strtolower($search_fieldname);

    if(strpos($search_fieldname, 'parent entity')===0 
        || strpos($search_fieldname, 'record parent')===0 
        || (defined('DT_PARENT_ENTITY') && $search_fieldname==DT_PARENT_ENTITY) 
        || $search_fieldname=='2-247'){ 
            
        if (defined('DT_PARENT_ENTITY')){    
            $rdt = self::__get_detail_types();
            if(@$rdt[DT_PARENT_ENTITY]){
                return $rdt[DT_PARENT_ENTITY][$result_fieldname];
            }
        }else{
            return '';
        }
    }else
    //search in record type structure
    if(@$rdr[$search_fieldname]){  //search by dty_ID, rst_DisplayName, dty_ConceptCode
        return $rdr[$search_fieldname][$result_fieldname];
    }else if($mode!=1) { //allow to search among all fields
        //if not found in structure - search among all detail types
        $rdt = self::__get_detail_types();
        if(@$rdt[$search_fieldname]){
            return $rdt[$search_fieldname][$result_fieldname];
        }
    }
    return null;
}

//
// get rectype id by name, cc or id
//
private static function __get_rt_id( $rt_search ){
    
        $query = 'SELECT rty_ID, rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes where ';
        
        if (strpos($rt_search,'-')>0){
            $pos = strpos($rt_search,'-');
            $query = $query . 'rty_OriginatingDBID ='.substr($rt_search,0,$pos)
                .' AND rty_IDInOriginatingDB ='.substr($rt_search,$pos+1);
        }else if($rt_search>0){
            $query = $query . 'rty_ID='.$rt_search;    
        }else{
            $query = $query . 'LOWER(rty_Name)="'.mb_strtolower($rt_search, 'UTF-8').'"';    
        }
        
        $res = self::$mysqli->query($query);
        if($res){
            $row = $res->fetch_assoc();
            if($row){
                
                if (is_numeric($row['rty_OriginatingDBID']) && $row['rty_OriginatingDBID']>0 &&
                is_numeric($row['rty_IDInOriginatingDB']) && $row['rty_IDInOriginatingDB']>0) {
                    $rt_cc = "" . $row['rty_OriginatingDBID'] . "-" . $row['rty_IDInOriginatingDB'];
                } else if (self::$db_regid>0) {
                    $rt_cc = "" . self::$db_regid . "-" . $row['rty_ID'];
                } else {
                    $rt_cc = $row['rty_ID'];
                }
                return array($row['rty_ID'], $rt_cc, $row['rty_Name']);
            }
            $res->close();
        }    
        return array(0, '', '');
}

/*
* replace title mask tag to value, coded (concept codes) or textual representation
*
* @param mixed $field_name - mask tag
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param $rec_id - record id for value mode
* @return mixed
*/
private static function __fill_field($field_name, $rt, $mode, $rec_id=null) {

    if (is_array($rt)){
        //ERROR
        return array("Field name '$field_name' was tested with Array of record types - bad parameter");
        // TODO: what does this error message mean? Make it comprehensible to the user
    }
    
    if(strcasecmp($field_name,'Record Title')==0){
        $field_name = 'rectitle';
    }else if(strcasecmp($field_name,'Record ID')==0){
        $field_name = 'id';
    }else if(strcasecmp($field_name,'Record TypeID')==0){
        $field_name = 'rectypeid';
    }else if(strcasecmp($field_name,'Record TypeName')==0){
        $field_name = 'rectypename';
    }else if(strcasecmp($field_name,'Record Modified')==0){
        $field_name = 'modified';
    }
  
    
    if (strcasecmp($field_name,'id')==0 ||
    strcasecmp($field_name,'rectitle')==0 ||
    strcasecmp($field_name,'modified')==0 ||
    strcasecmp($field_name,'rectypeid')==0 ||
    strcasecmp($field_name,'rectypename')==0)
    {
        $field_val = self::__get_field_value( $field_name, $rt, $mode, $rec_id );
        return $field_val;
    }

    $fullstop = '.';
    $fullstop_ex = '/^([^.]+?)\\s*\\.\\s*(.+)$/';
    $fullstop_concat = '.';
    
    if($mode==1 || strpos($field_name, '..')>0){ //convert to concept codes
        $fullstop = '..';
        $fullstop_ex = '/^([^..]+?)\\s*\\..\\s*(..+)$/';
    }
    if($mode==2){ //convert to human readable codes
        $fullstop_concat = '..';
    }
    
    // Return the rec-detail-type ID for the given field in the given record type
    if (strpos($field_name, $fullstop) === FALSE) {    // direct field name lookup

        if($mode==1 && self::$fields_correspondence!=null){
            $field_name = self::__replaceInCaseOfImport($field_name);
        }

        $rdt_id = self::__get_dt_field($rt, $field_name, $mode);  //get concept code
        if(!$rdt_id){
            //ERROR
            return array("Field name '$field_name' not recognised");
        }else {
            return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id );
        }
    }
    
    $parent_field_name = null;
    $inner_field_name = null;

    if (preg_match($fullstop_ex, $field_name, $matches)) {
        $parent_field_name = $matches[1];


        if($mode==1 && self::$fields_correspondence!=null){  //special case
            $parent_field_name = self::__replaceInCaseOfImport($parent_field_name);
        }//special case

        $rdt_id = self::__get_dt_field($rt, $parent_field_name, $mode);

        if($rdt_id){

            $inner_field_name = $matches[2];

            $dt_type = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_Type');
            if($dt_type=="enum" || $dt_type=="relationtype"){

                if(!$inner_field_name || strcasecmp($inner_field_name,'label')==0){
                    $inner_field_name = "term";
                }else if(strcasecmp($inner_field_name,'id')==0){
                    $inner_field_name = "internalid";
                }

                if (strcasecmp($inner_field_name,'internalid')==0 ||
                strcasecmp($inner_field_name,'term')==0 ||
                strcasecmp($inner_field_name,'code')==0 ||
                strcasecmp($inner_field_name,'conceptid')==0)
                {

                    if($mode==0){
                        return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id, $inner_field_name);
                    }else{
                        if($mode==1){
                            $s1 = $rdt_id;
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                        }

                        return $s1. $fullstop_concat .$inner_field_name;
                    }

                }else{
                    //ERROR
                    return array("'$inner_field_name' is an unrecognised qualifier for a terms list field");
                }
            }
            if($dt_type== 'relmarker') { //@todo - to implement it in nearest future
                return array("'$parent_field_name' is a relationship marker field type. This type is not supported at present.");
            }
            if($dt_type!== 'resource') {
                //ERROR
                return array("'$parent_field_name' must be either a record type name, a terms list field name or a record pointer field name. "
                    ."Periods are used as separators between record type name and field names. If you have a period in your record type name or field name, "
                    ."please rename it to use an alternative punctuation such as - _ , ) |");
            }

        }else{
            //ERROR
            return array("'$parent_field_name' not recognised as a field name");
        }
    } else {
        return "";
    }

    //parent field id and inner field
    if ($rdt_id  &&  $inner_field_name) {
        
        //recordttype for pointer field may be defined in mask
        //it is required to distiguish rt for multiconstrained pointers
        $inner_rectype = 0;
        $inner_rectype_name = '';
        $inner_rectype_cc = '';
        $pos = strpos($inner_field_name, $fullstop);
        $pos2 = strpos($inner_field_name, "}");
        if ( $pos>0 &&  $pos2>0 && $pos2 < $pos ) { 
            $inner_rectype_search = substr($inner_field_name, 1, $pos-strlen($fullstop)); 
            list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search ); 
            $inner_field_name = substr($inner_field_name, $pos+strlen($fullstop)); 
        }

        if($mode==0){ //replace with values
//[Note title]  [Author(s).{PersonBig}.Family Name] ,  [Author(s).{Organisation}.Full name of organisation] 
// [2-1]  [2-15.{2-10}.2-1] ,  [2-15.{2-4}.2-1] 

            //get values for resource field
            $pointer_ids = self::__get_field_value( $rdt_id, $rt, $mode, $rec_id);
            $pointer_ids = prepareIds($pointer_ids);
            $res = array();
            foreach ($pointer_ids as $rec_id){

                $rec_value = self::__get_record_value($rec_id);
                if($rec_value){
                    $res_rt = $rec_value['rec_RecTypeID']; //resource rt
                    
                    if($inner_rectype>0 && $inner_rectype!=$res_rt) continue;
                    
                    $fld_value = self::__fill_field($inner_field_name, $res_rt, $mode, $rec_id);
                    if(is_array($fld_value)){
                        //for multiconstraint it may return error since field may belong to different rt
                        return '';//$fld_value; //ERROR
                    }else if($fld_value) {
                        array_push($res, $fld_value);
                    }
                }
                //self::__get_field_value( $rdt_id, $rt, $mode, $rec_id) );
            }
            return implode(", ", $res);

        }else{ //convert  coded<->human
        
            $inner_rec_type = self::__get_dt_field($rt, $rdt_id, $mode, 'rst_PtrFilteredIDs'); //$rdr[$rt][$rdt_id]['rst_PtrFilteredIDs'];
            $inner_rec_type = explode(",", $inner_rec_type);
            if(count($inner_rec_type)>0){ //constrained
                $field_not_found = null;
                foreach ($inner_rec_type as $rtID){
                    $rtid = intval($rtID);
                    if (!$rtid) continue;
                    if($inner_rectype>0 && $inner_rectype!=$rtid) continue;
                    
                    $inner_rdt = self::__fill_field($inner_field_name, $rtid, $mode);
                    if(is_array($inner_rdt)){
                        //it may be found in another record type for multiconstaints
                        $field_not_found = $inner_rdt; //ERROR
                    }else if ($inner_rdt) {

                        if($mode==1){
                            $s1 = $rdt_id; //parent detail id
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_cc.'}';
                            }
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_name. '}';
                            }
                        }
                        return $s1. $fullstop_concat .$inner_rdt;
                    }
                }
                if($field_not_found){
                    return $field_not_found;
                }
            }
            if($mode==1){  //return concept code
                $s1 = $rdt_id;
            }else{
                $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
            }
            return $s1. ($inner_field_name? $fullstop_concat.$inner_field_name:"");
        }
    }

    return "";
}

//
// replace local dty_ID to concept code (for import)
//
private static function __replaceInCaseOfImport($dty_ID){
    //special case - replace dty_ID in case of definition import
    if(strpos($dty_ID,"-")===false && is_numeric($dty_ID)){ //this is not concept code and numeric

        if(self::$fields_correspondence!=null && count(self::$fields_correspondence)>0 && @self::$fields_correspondence[$dty_ID]){
            //print "<br>>>>was ".$dty_ID;
            $dty_ID = @self::$fields_correspondence[$dty_ID];
            //print "<br>>>>replace to ".$dty_ID;
        }
    }
    return $dty_ID;
}

}//end of class

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
