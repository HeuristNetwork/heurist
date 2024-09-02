<?php
namespace hserv\entity;

    /**
    *
    *
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
class DbEntitySearch
{
    private $system;

    private $data = array();//request - assigned in validate params

    //name of primary key field from $config  by dty_Role="primary"
    private $primaryField;
    
    private $whereConditions;
    
    //data types: ids, int, float, date, bool, enum
    //structure
    private $fields = array(); //fields from configuration

    private $config = array(); //configuration
    
    
    public function __construct( $system, $config, $fields) {
       $this->system = $system;
       $this->fields = $fields;
       $this->config = $config;
       $this->whereConditions = array();
    }

    //
    //
    //
    private function _validateIds($fieldname, $data_type=null){

        $values = @$this->data[$fieldname];

        if($values!=null && $data_type!='freetext'){
            //array of integer or integer
            if(!is_array($values)){
                $values = explode(',', $values);
                //$values = array($values);
            }
            //if (preg_match('/^\d+(?:,\d+)+$/', $this->value))
            foreach($values as $val){  //intval()
                if( !(is_numeric($val) && $val!=null)){
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

            $enums = $this->fields[$fieldname]['rst_FieldConfig'];

            if(!is_array($value)){
                $values = explode(',', $value);
            }else{
                $values = $value;
            }
            $iskeybased = (is_array($enums[0]));

            foreach($values as $val){
                //search in enums

                if(strpos($val, '-') === 0){ // remove negation
                    $val = substr($val, 1);
                }

                $isNotFound = true;
                if($iskeybased){
                    foreach($enums as $enum){
                        if($enum['key']==$val){
                            $isNotFound = false;
                            break;
                        }
                    }
                }elseif(array_search($value, $enums, true)!==false){
                    $isNotFound = false;
                }
                if($isNotFound){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname: $value");
                    return false;
                }
            }//for
        }
        return true;
    }

    //
    //
    //
    private function _validateBoolean($fieldname){

        $value = @$this->data[$fieldname];

        if($value!=null){

            if(is_bool($value)){
                $value = $value?1:0;
            }elseif(is_numeric($value)){
                $value = $value==1?1:0;
            }else{
                $value = $value=='y'?1:0;
            }
            if(!($value==1 || $value==0)){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname ".$this->data[$fieldname]);
                return false;
            }
            return $value;

        }
        return true;
    }


    //
    //
    //
    public function validateParams($data){

        $this->data = $data;

        //loop for config
        foreach($this->fields as $fieldname=>$field_config){
            $value = @$this->data[$fieldname];
            
            $data_role = @$field_config['dty_Role'];
            if($data_role=='primary'){
                $this->primaryField = $fieldname;
            }

            if($value!=null){

                $data_type = $field_config['dty_Type'];
                $data_role = @$field_config['dty_Role'];

                $is_ids = ($data_role=='primary') || (@$field_config['rst_FieldConfig']['entity']!=null);

                if($value==SQL_NULL || $value=='-'.SQL_NULL){
                    $res = true;
                }elseif($is_ids=='ids'){
                    
                    $res = $this->_validateIds($fieldname, $data_type);//, 'user/group IDs');

                }elseif($data_type == 'enum' && !$is_ids){
                    $res = $this->_validateEnum($fieldname);

                }elseif($data_type=='boolean'){
                    $res = $this->_validateBoolean($fieldname);

                }else{
                    $res = true;
                }

                if(!is_bool($res)){
                    $this->data[$fieldname] = $res;
                }else{
                    if(!$res) {return false;}
                }
            }
        }

        return $this->data;
    }


    //
    // remove quoted values and double spaces
    //
    private function _cleanQuotedValue($val) {
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

    
    public function addPredicate($fieldname, $is_ids=false) {
        
        $pred = $this->getPredicate($fieldname, $is_ids);
        if($pred!=null) {array_push($this->whereConditions, $pred);}
        
    }
    
    public function setSelFields($fields){
        if(!is_array(@$this->data['details'])){
            $this->data['details'] = $fields;
        }
    }
    
    
    public function composeAndExecute($orderBy){
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //ID field is mandatory and MUST be first in the list
        $idx = array_search($this->primaryField, $this->data['details']);
        if($idx>0){ //remove from list if not on first place
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){ 
            array_unshift($this->data['details'], $this->primaryField); //insert first
        }
        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.$this->config['tableName'];

        if(count($this->whereConditions)>0){
            $query = $query.SQL_WHERE.implode(SQL_AND,$this->whereConditions);
        }
         
        if($orderBy!=null){
            $query = $query.' ORDER BY '.$orderBy;   
        }
         
        $query = $query.' '.$this->getLimit().$this->getOffset();

        $res = $this->execute($query, $is_ids_only);
        return $res;
        
        
    }
    
    
    //
    // extract first charcter to determine comparison opeartor =,like, >, <, between
    //
    public function getPredicate($fieldname, $is_ids=false) {

        $value = @$this->data[$fieldname];
        if($value==null) {return null;}

        $field_config = @$this->fields[$fieldname];
        if($field_config==null) {return null;}
        $data_type = $field_config['dty_Type'];
        $is_ids = ($is_ids || @$field_config['dty_Role']=='primary') || (@$field_config['rst_FieldConfig']['entity']!=null);

        //special case for ids - several values can be used in IN operator
        if ($is_ids) {  //preg_match('/^\d+(?:,\d+)+$/', $value)

            if($value == SQL_NULL){
                return '(NOT ('.$fieldname.'>0))';
            }elseif($value == '-NULL'){
                return '('.$fieldname.'>0)';
            }

            if(!is_array($value) && is_string($value) && strpos($value, '-')===0){
                $negate = true;
                $value = substr($value, 1);
                if($value=='') {return null;}
            }else{
                $negate = false;
            }

            if($data_type=='freetext' ||  $data_type=='url' || $data_type=='blocktext'){
                $value = prepareStrIds($value);
            }else{
                $value = prepareIds($value);
            }

            if(count($value)==0) {return null;}

            if(count($value)>1){
                // comma-separated list of ids
                $in = ($negate)? 'not in' : 'in';
                $res = " $in (" . implode(',', $value) . ")";
            }else{
                $res = ($negate)? ' !=' : '='.$value[0];
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

            if($value == SQL_NULL){
                array_push($or_predicates, $fieldname.' IS NULL');
                continue;
            }elseif($value == '-NULL'){
                array_push($or_predicates, '('.$fieldname.' IS NOT NULL AND '.$fieldname.'<>"")');
                continue;
            }

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
                }
                if(strpos($value, '=')===0){
                    $exact = true;
                    $value = substr($value, 1);
                }elseif(strpos($value, '<')===0){
                    $lessthan = true;
                    $value = substr($value, 1);
                }elseif(strpos($value, '>')===0){
                    $greaterthan = true;
                    $value = substr($value, 1);
                }
            }

            $value = $this->_cleanQuotedValue($value);

            if($value=='') {continue;}

            $mysqli = $this->system->get_mysqli();

            if($between){
                $values = explode('<>', $value);
                $between = ((negate)?' not':'').SQL_BETWEEN;
                $values[0] = $mysqli->real_escape_string($values[0]);
                $values[1] = $mysqli->real_escape_string($values[1]);
            }else{
                $value = $mysqli->real_escape_string($value);
            }

            $eq = ($negate)? '!=' : (($lessthan) ? '<' : (($greaterthan) ? '>' : '='));

            if ($data_type == 'integer' || $data_type == 'float' || $data_type == 'year') {

                if($between){
                    $res = $between.$values[0].SQL_AND.$values[1];
                }else{
                    $res = " $eq ".($data_type == 'int'?intval($value):$value);//no quotes
                }
            }
            elseif($data_type == 'date') {

                //$datestamp = Temporal::dateToISO($this->value);

                if($between){
                    $res = $between." '".$values[0]."' ".SQL_AND." '".$values[1]."'";
                }else{

                    if($eq=='=' && !$exact){
                        $eq = 'like';
                        $value = $value.'%';
                    }

                    $res = " $eq '".$value. "'";
                }


            } else {

                if($between){
                    $res = $between.$values[0].SQL_AND.$values[1];
                }else{

                    if(($eq=='=' || $eq=='!=') && !$exact && ($data_type == 'freetext' || $data_type == 'url' || $data_type == 'blocktext') ){
                        $eq = 'like';
                        if($negate){
                            $eq = 'not like';
                        }
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
            $res = '('.implode(' OR ', $or_predicates).')';
            return $res;
        }else{
            return null;
        }
    }

    //
    // @todo inherit
    //
    public function getOffset(){
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
    public function getLimit(){
        if(@$this->data['limit']){
            $limit = intval($this->data['limit']);
            if($limit>=0){
                return ' LIMIT '.$limit;
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter limit: ".$this->data['limit']);
                return false;
            }
        }
    }

    //
    //
    // $calculatedFields - is function that returns array of fieldnames or calculate and adds values of this field to result row
    //
    public function execute($query, $is_ids_only, $entityName=null, $calculatedFields=null, $multiLangs=null){

        $mysqli = $this->system->get_mysqli();
        
        $res = $mysqli->query($query);
        if (!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Search error', $mysqli->error);
            return false;
        }
        $total_count_rows = mysql__found_rows($mysqli);
        
        if($entityName==null){
            $entityName = $this->config['entityName'];
        }

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
                    $fields_idx = array();
                    foreach($_flds as $fld){
                        array_push($fields, $fld->name);

                        if($multiLangs && in_array($fld->name, $multiLangs)){
                            $fields_idx[$fld->name] = count($fields)-1;
                        }
                    }
                    //add calculated fields to header
                    if($calculatedFields!=null){
                        $fields = $calculatedFields($fields);//adds names of calulated fields
                    }

                    $records = array();
                    $order = array();

                    // load all records
                    while ($row = $res->fetch_row())// && (count($records)<$chunk_size) ) {  //3000 maxim allowed chunk
                    {

                        if($calculatedFields!=null){
                            $row = $calculatedFields($fields, $row);//adds values
                        }
                        $records[$row[0]] = $row;   //record[primary key] = row from db table
                        $order[] = $row[0];
                    }
                    $res->close();


                    if(@$this->data['restapi']==1){

                       //converts records to [fieldname=>value,... ]
                       $response = array();
                       foreach ($records as $record) {
                           $rec = array();
                           foreach ($fields as $idx=>$field){
                               $rec[$field] = $record[$idx];
                           }
                           $response[] = $rec;
                       }
                       if(@$this->data[$this->primaryField]>0 && count($response)==1){
                           $response = $response[0];
                       }

                    }else{

                        //search for translations
                        if($multiLangs!=null && count($order)==1){

                            $query = 'SELECT trn_Code, trn_Source, trn_LanguageCode, trn_Translation FROM defTranslations '
                            .'WHERE trn_Code = '.intval($order[0])   //'IN ('.implode(',',$order).') '
                            .' AND trn_Source IN ("'.implode('","', $multiLangs).'")';

                            $res = $mysqli->query($query);
                            if ($res){
                                while ($row = $res->fetch_row()){

                                    $idx = $fields_idx[$row[1]];

                                    if($idx>0){
                                        if(!is_array($records[$row[0]][$idx])){
                                            $records[$row[0]][$idx] = array($records[$row[0]][$idx]);
                                        }
                                        array_push($records[$row[0]][$idx], $row[2].':'.$row[3]);
                                    }
                                }
                                $res->close();
                            }

                        }

                        //form result array
                        $response = array(
                                'queryid'=>@$this->data['request_id'],  //query unqiue id set in doRequest
                                'pageno'=>@$this->data['pageno'],  //page number to sync
                                'offset'=>@$this->data['offset'],
                                'count'=>$total_count_rows,
                                'reccount'=>count($records),
                                'fields'=>$fields,
                                'records'=>$records,
                                'order'=>$order,
                                'entityName'=>$entityName);
                    }

                }//$is_ids_only
            
        return $response;
    }

}
?>
