<?php

    /**
    * db access to sysUGrpps table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once (dirname(__FILE__).'/../System.php');
//require_once (dirname(__FILE__).'/db_tags.php');

class DbSysUGrps
{
    private $system;  
    
    /*  
     parametrs
    
     list of fields to search or update
     
    
    */    
    private $data;  

    //@todo???? load this mapping dynamically fromn db
    // to validate fieldnames and value
    
    
    //data types: ids, int, float, date, bool, enum
    private static $fields = array( 
   'ugr_ID'=>'ids',    //ids
   'ugr_Type'=>array('user','workgroup','ugradclass'), //t
   'ugr_Name'=>63,     //title
   'ugr_LongName'=>128,
   'ugr_Description'=>1000,
   'ugr_Password'=>40,
   'ugr_eMail'=>100,
   'ugr_FirstName'=>40,
   'ugr_LastName'=>63,
   'ugr_Department'=>120,
   'ugr_Organisation'=>120,
   'ugr_City'=>63,
   'ugr_State'=>40,
   'ugr_Postcode'=>20,
   'ugr_Interests'=>255,
   'ugr_Enabled'=>'bool',
   'ugr_Modified'=>'date',    //date, after, before
   'ugl_Role'=>array('admin','member'),
   'ugl_UserID'=>'ids',
   'ugl_GroupID'=>'ids' );
   
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    
    //
    // @todo inherit
    //
    private function _validateIds($fieldname){
    
        $values = @$this->data[$fieldname];
        
        if($values!=null){
            //array of integer or integer
            if(!is_array($values)){
                $values = explode(',', $values);
                //$values = array($values);
            }
            //if (preg_match('/^\d+(?:,\d+)+$/', $this->value)) 
            foreach($values as $val){  //intval()
                if(!(is_numeric($val) && $val!=null)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname: $val");
                    return false;
                }
            }
            //return $values;        
        }
        return true;        
    }

    //
    // @todo inherit
    //
    private function _validateEnum($fieldname){
    
        $value = @$this->data[$fieldname];
        
        if($value!=null){
            
            $enums = DbSysUGrps::$fields[$fieldname];

            if(!is_array($values)){
                $values = explode(',', $values);
            }
            foreach($values as $val){ 
                if(array_search($value, $enums, true)===false){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname: $value");
                    return false;
                }
            }
        }
        return true;        
    }

    //
    // @todo inherit
    //
    private function _validateBoolean($fieldname){

        $value = @$this->data[$fieldname];
        
        if($value!=null){

            if(is_bool($value)){
                $value = $value?'y':'n';
            }else if(is_numeric($value)){
                $value = $value==1?'y':'n';
            }
            if(!($value=='y' || $value=='n')){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname ".$this->data[$fieldname]);
                return false;
            }
            return $value;
            
        }
        return true;
    }
    //
    // @todo inherit
    //
    private function _validateParams(){
        
        
        foreach(DbSysUGrps::$fields as $fieldname=>$data_type){
            if(!@$this->data[$fieldname]){
                
                if($data_type=='ids'){
                    $res = $this->_validateIds($fieldname); //, 'user/group IDs');
                }else if(is_array($data_type)){
                    $res = $this->_validateEnum($fieldname);
                }else if($data_type=='bool'){
                    $res = $this->_validateBoolean($fieldname);
                }else{
                    $res = true;
                }
        
                if(!is_bool($res)){
                    $this->data[$fieldname] = $res;
                }else{
                    if(!$res) return false;        
                }        
            }
        }
        
        return true;
    }
    
    //
    // @todo inherit
    //
    function _getOffset(){
        if(@$this->data['offset']){
            $offset = intval($this->data['offset']);
            if($offset>=0){
                return ' OFFSET '.$offset;
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter offset: ".$this->data['offset']);
                return false;
            }
        }
    }
    //
    // @todo inherit
    //
    function _getLimit(){
        if(@$this->data['limit']){
            $limit = intval($this->data['limit']);
            if($offset>=0){
                return ' LIMIT '.$limit;
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter limit: ".$this->data['limit']);
                return false;
            }
        }
    }

    //
    // remove quoted values and double spaces
    //
    function _cleanQuotedValue($val) {
        if (strlen($val)>0 && $val[0] == '"') {
            if ($val[strlen($val)-1] == '"')
                $val = substr($val, 1, -1);
            else
                $val = substr($val, 1);
            return preg_replace('/ +/', ' ', trim($val));
        }

        return $val;
    }
        
    //
    // extract first charcter to determine comparison opeartor =,like, >, <, between
    //
    function _getSearchPredicate($fieldname) {
        
        $value = @$this->data[$fieldname];
        if($value==null) return null;
        
        $data_type = DbSysUGrps::$fields[$fieldname];
        if(is_array($data_type)){
            $data_type = 'enum';
        }else if(is_numeric($data_type)){
            $data_type = 'varchar';
        }

        //special case for ids - several values can use IN operator        
        if ($data_type == 'ids') {  //preg_match('/^\d+(?:,\d+)+$/', $value)
        
            if(is_array($value)){
                $value = implode(',',$value);
            }
            if(strpos($value, '-')===0){
                $negate = true;
                $value = substr($value, 1);
            }else{
                $negate = false;
            }
            if($value=='') return null;
            $mysqli = $this->system->get_mysqli();
            
            if(strpos($value, ',')>0){
                // comma-separated list of ids
                $in = ($negate)? 'not in' : 'in';
                $res = " $in (" . $value . ")";
            }else{
                $res = ($negate)? ' !=' : '='.$value;
            }

            return $fieldname.$res;    
        }   
        
        if(!is_array($value)){      
            $or_values = array($value);
        }else{
            $or_values = $value;
        }
        
        $or_predicates = array();
        
        foreach($or_values as $value){
        
            $exact = false;
            $negate = false;
            $between = (strpos($value,'<>')>0);
            $lessthan = false;
            $greaterthan = false;
            
            if ($between) {
                if(strpos($value, '-')===0){
                    $negate = true;
                    $value = substr($value, 1);
                }
            }else{
                if(strpos($value, '-')===0){
                    $negate = true;
                    $value = substr($value, 1);
                }else if(strpos($value, '=')===0){
                    $exact = true;
                    $value = substr($value, 1);
                }else if(strpos($value, '<')===0){
                    $lessthan = true;
                    $value = substr($value, 1);
                }else if(strpos($value, '>')===0){
                    $greaterthan = true;
                    $value = substr($value, 1);
                }
            }
            
            $value = $this->_cleanQuotedValue($value);

            if($value=='') continue;
            
            $mysqli = $this->system->get_mysqli();
            
            if($between){
                $values = explode('<>', $value);
                $between = ((negate)?' not':'').' between ';
                $values[0] = $mysqli->real_escape_string($values[0]);
                $values[1] = $mysqli->real_escape_string($values[1]);
            }else{
                $value = $mysqli->real_escape_string($value);
            }
            
            $eq = ($negate)? '!=' : (($lessthan) ? '<' : (($greaterthan) ? '>' : '='));
            
            if ($data_type == 'int' || $data_type == 'float') {

                if($between){
                    $res = $between.$values[0].' and '.$values[1];
                }else{
                    $res = " $eq ".($data_type == 'int'?intval($value):$value);  //no quotes
                }
            }
            else if ($data_type == 'date') {    

                //$datestamp = validateAndConvertToISO($this->value);
                
                if($between){
                    $res = $between." '".$values[0]."' and '".$values[1]."'";
                }else{
                    
                    if($eq=='=' && !$exact){
                        $eq = 'like';
                        $value = $value.'%';
                    }
                    
                    $res = " $eq '".$value. "'";
                }
                

            } else {
                
                if($between){
                    $res = $between.$values[0].' and '.$values[1];
                }else{
                    
                    if($eq=='=' && !$exact && $data_type == 'varchar'){
                        $eq = 'like';
                        $k = strpos($value,"%");
                        if($k===false || ($k>0 && $k+1<strlen($value))){
                            $value = '%'.$value.'%';
                        }
                    }
                    
                    $res = " $eq '".$value. "'";
                }

            }
            
            array_push($or_predicates, $fieldname.$res);
        
        }//for or_values
        
        if(count($or_predicates)>0){
            $res = implode(' OR ', $or_predicates);
            return $res;
        }else{
            return null;
        }
            
        
        
    }
    
        
    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    *  @todo overwrite
    */
    public function search(){
        
//error_log(print_r($this->data,true));        

        /*
        if (!(@$this->data['val'] || @$this->data['geo'] || @$this->data['ulfID'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }
        */
        if(!$this->_validateParams()){
            return false;
        }
        $isJoin = false;
        $joinSysUsrGrpLinks = '';

        $mysqli = $this->system->get_mysqli();

        //compose WHERE 
        $where = array('(ugr_ID>0)');    
        
        $pred = $this->_getSearchPredicate('ugr_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->_getSearchPredicate('ugr_Name');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->_getSearchPredicate('ugr_Enabled');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->_getSearchPredicate('ugr_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->_getSearchPredicate('ugr_Type');
        if($pred!=null) {
            array_push($where, $pred);
        
            //find group where this user is member or admin
            $pred = $this->_getSearchPredicate('ugl_UserID');
            if($pred!=null && $this->data['ugr_Type']=='workgroup') {
                array_push($where, $pred);
                $isJoin = true;
            }
            //find users for given groups
            $pred = $this->_getSearchPredicate('ugl_GroupID');
            if($pred!=null && $this->data['ugr_Type']=='user') {
                array_push($where, $pred);
                $isJoin = true;
            }
            $pred = $this->_getSearchPredicate('ugl_Role');
            if($pred!=null) {
                array_push($where, $pred);
                $isJoin = true;
            }
            
            if($isJoin){
                if($this->data['ugr_Type']=='user'){
                    array_push($where, "(sysUsrGrpLinks.ugl_UserID = ugr_ID)");
                }else{
                    array_push($where, "(sysUsrGrpLinks.ugl_GroupID = ugr_ID)");
                }
                $joinSysUsrGrpLinks = ', sysUsrGrpLinks';
            }
        }

       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'ugr_ID,ugr_Type,ugr_Name,ugr_FirstName,ugr_LastName,ugr_Description,ugr_Enabled,ugr_Organisation';
            if($isJoin) $this->data['details'] .= ',ugl_Role';
            
        }else if(@$this->data['details']=='full'){

            //remove ugl_XXX fields from the end of fields array
            $this->data['details'] = implode(',',  $isJoin
                        ? array_slice(DbSysUGrps::$fields,0,count(DbSysUGrps::$fields)-2)
                        : array_slice(DbSysUGrps::$fields,0,count(DbSysUGrps::$fields)-3) );
        }
        
        if(!is_array($this->data['details'])){
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@DbSysUGrps::$fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }
        //exclude ugr_Password   - DO NOT SEND to client side 
        $idx = array_search('ugr_Password', $this->data['details']);
        if($idx>=0){
            unset($this->data['details'][$idx]);
        }
        //ID field is mandatory and MUST be first in the list
        $idx = array_search('ugr_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'ugr_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details']).' FROM sysUGrps'
                .$joinSysUsrGrpLinks;
         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->_getOffset()
                        .$this->_getLimit();
        
//error_log($query);        
        
        $res = $mysqli->query($query);
        if (!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'UserGroups search error', $mysqli->error);
            return false;
        }else{

            $fres = $mysqli->query('select found_rows()');
            if (!$fres)     {
                $this->system->addError(HEURIST_DB_ERROR, 'UserGroups search error (retrieving number of records)', $mysqli->error);
                return false;
            }else{

                $total_count_rows = $fres->fetch_row();
                $total_count_rows = $total_count_rows[0];
                $fres->close();

                if($is_ids_only){ //------------------------  LOAD and RETURN only IDS

                    $records = array();

                    while ($row = $res->fetch_row()) //&& (count($records)<$chunk_size)  //3000 max allowed chunk
                    {
                        array_push($records, (int)$row[0]);
                    }
                    $res->close();

                    $response = array(
                                'queryid'=>@$this->data['request_id'],  //query unqiue id
                                'offset'=>@$this->data['offset'],
                                'count'=>$total_count_rows,
                                'reccount'=>count($records),
                                'records'=>$records);

                }else{ //----------------------------------


                    // read all field names
                    $_flds =  $res->fetch_fields();
                    $fields = array();
                    foreach($_flds as $fld){
                        array_push($fields, $fld->name);
                    }

                    $rectype_structures  = array();
                    $rectypes = array();
                    $records = array();
                    $order = array();

                    // load all records
                    while ($row = $res->fetch_row())// && (count($records)<$chunk_size) ) {  //3000 maxim allowed chunk
                    {
                        $records[$row[0]] = $row;
                        array_push($order, $row[0]);
                    }
                    $res->close();

                    $response = array(
                            'queryid'=>@$this->data['request_id'],  //query unqiue id
                            'offset'=>@$this->data['offset'],
                            'count'=>$total_count_rows,
                            'reccount'=>count($records),
                            'fields'=>$fields,
                            'records'=>$records,
                            'order'=>$order);

                }//$is_ids_only
            }

        }        
        
        return $response;
    }

     
    
}
?>
