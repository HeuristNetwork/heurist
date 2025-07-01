<?php
/**
* DbEntitySearch.php - Class DbEntitySearch
*
* Handles the construction and execution of search queries for database entities.
*
* @project     Heurist academic knowledge management system
* @package Entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\entity;

/**
* Class DbEntitySearch
*
* Handles the construction and execution of search queries for database entities.
* It validates search parameters, builds SQL WHERE clauses based on these parameters,
* and executes the query, returning results in various formats.
* This class is typically instantiated and used by `DbEntityBase` and its subclasses.
*
*/
class DbEntitySearch
{
    /** @var \hserv\System The main Heurist system object. */
    private $system;

    /** @var array Holds the validated search request data. Initialized by `validateParams()`. */
    private $data = array();

    /** @var string|null The name of the primary key field for the entity being searched. */
    private $primaryField;

    /** @var array An array of SQL WHERE conditions built from search parameters. */
    private $whereConditions;

    /** @var array Associative array describing the entity's fields, from its JSON configuration. */
    private $fields = array();

    /** @var array Entity configuration array, loaded from its JSON file. */
    private $config = array(); 

    /**
     * Constructor for DbEntitySearch.
     *
     * Initializes the search manager with the system object, entity configuration,
     * and field definitions.
     *
     * @param \hserv\System $system The main Heurist system object.
     * @param array $config The configuration array for the entity.
     * @param array $fields An associative array of field definitions for the entity.
     */
    public function __construct( $system, $config, $fields) {
       $this->system = $system;
       $this->fields = $fields;
       $this->config = $config;
       $this->whereConditions = array();
    }

    //
    //
    //
    /**
     * Validates if the given field's value(s) are valid IDs (numeric).
     *
     * Handles single IDs or comma-separated strings/arrays of IDs.
     *
     * @param string $fieldname The name of the field to validate.
     * @param string|null $data_type The data type of the field (e.g., 'freetext'). If 'freetext', validation is skipped.
     * @return bool True if valid or not applicable, false if any ID is non-numeric.
     *              Errors are added to the system object on validation failure.
     */
    private function _validateIds($fieldname, $data_type=null){

        $values = @$this->data[$fieldname];

        if($values==null || $data_type=='freetext'){
            return true;
        }
            //array of integer or integer
            if(!is_array($values)){
                $values = explode(',', $values);
            }
            foreach($values as $val){  //intval()
                if( !(is_numeric($val) && $val!=null)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname: $val");
                    return false;
                }
            }

        return true;
    }

    /**
     * Validates that a given field's value is a valid enum.
     *
     * If the field is empty, it returns true. Otherwise, it checks whether the value
     * exists in the corresponding enum array for the field.
     *
     * @param string $fieldname The name of the field to validate.
     * @return bool Returns true if the value is valid, otherwise false.
     */
    private function _validateEnum($fieldname, $data_type=null){

        $value = @$this->data[$fieldname];

        if($value==null){
            return true;
        }

        $enums = $this->fields[$fieldname]['rst_FieldConfig'];
        $values = is_array($value) ? $value : explode(',', $value);
        $isKeyBased = is_array($enums[0]);

            foreach($values as $value){
                // remove negation
                if(strpos($value, '-') === 0){ $value = substr($value, 1); }

                $isNotFound = true;
                if($isKeyBased){
                    if(findInArray($enums, 'key', $value)!==null){
                                $isNotFound = false;
                                break;
                    }
                }elseif(array_search($value, $enums, true)!==false){
                    $isNotFound = false;
                }
                if($isNotFound){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter for field $fieldname: $value");
                    return false;
                }
            }//for

        return true;
    }

    //
    //
    //
    /**
     * Validates and normalizes a boolean field value.
     *
     * Converts boolean true/'y'/1 to 1, and others to 0.
     *
     * @param string $fieldname The name of the field to validate.
     * @param string|null $data_type Unused.
     * @return int|false 1 for true, 0 for false. Returns false if the input cannot be resolved to a boolean.
     *                   Errors are added to the system object on validation failure.
     */
    private function _validateBoolean($fieldname, $data_type=null){

        $value = @$this->data[$fieldname];

        if($value==null){
            return true;
        }

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


    /**
     * Validates all input search parameters based on the entity's field configurations.
     *
     * Iterates through configured fields and calls specific validation methods
     * (e.g., `_validateIds`, `_validateEnum`, `_validateBoolean`) based on field type.
     * Sets the `primaryField` property.
     *
     * @param array $data The raw input data array (typically request parameters).
     * @return array|false The validated and potentially modified data array on success,
     *                     or false if any validation fails.
     */
    public function validateParams($data){

        $this->data = $data;

        //loop for config
        foreach($this->fields as $fieldname=>$field_config)
        {
            $value = $this->data[$fieldname] ?? null;

            if($this->isPrimaryField($field_config)){
                $this->primaryField = $fieldname;
            }

            if($value==null){
                continue;
            }

            if($value==SQL_NULL || $value=='-'.SQL_NULL){
                continue;
            }

            if(!$this->validateParam($fieldname, $field_config)){
                return false;
            }
        }

        return $this->data;
    }

    /**
     * Validates a single search parameter value against its field configuration.
     *
     * Dynamically calls a specific validation method (e.g., `_validateIds` for ID types,
     * `_validateEnum` for enum types) based on the `dty_Type` or if it's a primary key.
     *
     * @param string $fieldname The name of the field whose value is being validated.
     * @param array $field_config The configuration array for this specific field.
     * @return bool True if validation is successful or not applicable, false otherwise.
     *              If a validation method returns a non-boolean (e.g., a normalized value),
     *              `$this->data[$fieldname]` is updated with that value and true is returned.
     */
    private function validateParam($fieldname, $field_config){

            $data_type = $field_config['dty_Type'];
            $methodName = $data_type;

            if($this->primaryField == $fieldname || @$field_config['rst_FieldConfig']['entity']!=null){
                $methodName = 'ids';
            }

            $methodName = '_validate'.ucfirst($methodName);
            $res = true;

            if(method_exists($this, $methodName)){ //&& is_callable(array($this, $methodName))){
                $res = $this->$methodName($fieldname, $data_type);
            }

            if(!is_bool($res)){
                $this->data[$fieldname] = $res;
                return true;
            }

            return $res;
    }

    //
    // remove quoted values and double spaces
    //
    /**
     * Cleans a quoted search value by removing surrounding quotes and normalizing spaces.
     *
     * If the value starts and ends with a double quote, these are removed.
     * Multiple spaces within the value are then condensed to single spaces.
     *
     * @param string $val The input string value.
     * @return string The cleaned string value.
     */
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

    /**
     * Generates an SQL predicate for a given field and adds it to the internal list of WHERE conditions.
     *
     * Uses `getPredicate()` to create the condition string.
     *
     * @param string $fieldname The name of the field for the predicate.
     * @param bool $is_ids True if the field should be treated as an ID list for `IN()` or `NOT IN()` clauses.
     *                     Defaults to false.
     * @return void
     */
    public function addPredicate($fieldname, $is_ids=false) {

        $pred = $this->getPredicate($fieldname, $is_ids);
        if($pred!=null) {array_push($this->whereConditions, $pred);}

    }

    /**
     * Sets the fields to be selected in the search query.
     *
     * Updates `$this->data['details']` with the provided field list if it's not already an array.
     *
     * @param string|array $fields A comma-separated string or an array of field names to select.
     * @return void
     */
    public function setSelFields($fields){
        if(!is_array(@$this->data['details'])){
            $this->data['details'] = $fields;
        }
    }

    /**
     * Composes the final SQL query from selected fields, WHERE conditions, and ordering, then executes it.
     *
     * Ensures the primary key field is the first in the SELECT list if multiple fields are selected.
     *
     * @param string|null $orderBy The ORDER BY clause string (e.g., "fieldName ASC").
     * @param string|null $sup_tables Additional tables to include in the FROM clause (e.g., for JOINs).
     * @param string|null $sup_where Additional WHERE clause conditions to append.
     * @return array|false The result of the search query execution, typically an array from `DbEntitySearch::execute()`,
     *                     or false on error.
     */
    public function composeAndExecute($orderBy, $sup_tables=null, $sup_where=null){

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

        if($sup_tables!=null){
            $query .= $sup_tables;
        }
        if($sup_where!=null){
            $this->whereConditions[] = $sup_where;
        }

        if(!empty($this->whereConditions)){
            $query .= SQL_WHERE.implode(SQL_AND,$this->whereConditions);
        }


        if($orderBy!=null){
            $query .= ' ORDER BY '.$orderBy;
        }

        $query .= ' '.$this->getLimit().$this->getOffset();

        $res = $this->execute($query, $is_ids_only);
        return $res;

    }

    /**
     * Determines if a field is marked as the primary field.
     *
     * @param array $field_config The configuration for the field.
     * @return bool True if the field is primary, otherwise false.
     */
    private function isPrimaryField($field_config) {
        return isset($field_config['dty_Role']) && $field_config['dty_Role'] === 'primary';
    }

    //
    // extract first charcter to determine comparison opeartor =,like, >, <, between
    //
    /**
     * Generates an SQL predicate string for a given field based on its value and type.
     *
     * Handles various comparison operators (exact match, negation, less than, greater than, between)
     * and data types (IDs, text, numeric, date).
     * - For ID fields (`$is_ids` is true or field is primary/entity ref): Handles single IDs,
     *   comma-separated lists (for IN/NOT IN), and negation.
     * - For other types: Parses operators like '=', '<', '>', '<>' (between), and leading '-' (negation).
     *   Uses LIKE for text fields unless an exact match ('=') is specified.
     *
     * @param string $fieldname The name of the field.
     * @param bool $is_ids If true, treat the field value as a list of IDs. Defaults to false.
     * @return string|null The SQL predicate string (e.g., "fieldName = 'value'", "fieldName IN (1,2)"),
     *                     or null if the value is empty or invalid.
     */
    public function getPredicate($fieldname, $is_ids=false) {

        $value = @$this->data[$fieldname];
        if($value==null) {return null;}

        $field_config = @$this->fields[$fieldname];
        if($field_config==null) {return null;}
        $data_type = $field_config['dty_Type'];
        $is_ids = ($is_ids || $this->isPrimaryField($field_config) || @$field_config['rst_FieldConfig']['entity']!=null);

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

            if(empty($value)) {return null;}

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

            if($value == 'NULL'){
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

            $mysqli = $this->system->getMysqli();

            if($between){
                $values = explode('<>', $value);
                $between = (($negate)?' not':'').SQL_BETWEEN;
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

        if(!empty($or_predicates)){
            $res = '('.implode(' OR ', $or_predicates).')';
            return $res;
        }else{
            return null;
        }
    }

    //
    // @todo inherit
    //
    /**
     * Gets the SQL OFFSET clause string based on `data['offset']`.
     *
     * @return string|false The OFFSET clause (e.g., " OFFSET 10") or false if offset is invalid.
     *                      Returns an empty string if no offset is specified.
     *                      Adds an error to the system if offset is invalid.
     */
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
    /**
     * Gets the SQL LIMIT clause string based on `data['limit']`.
     *
     * @return string|false The LIMIT clause (e.g., " LIMIT 100") or false if limit is invalid.
     *                      Returns an empty string if no limit is specified.
     *                      Adds an error to the system if limit is invalid.
     */
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
    //
    /**
     * Determines the ORDER BY clause based on `data` parameters (e.g., `sort:[fieldName]=1` for ASC, `0` for DESC).
     *
     * @param string|null $default Default ORDER BY string if no sort parameter is found.
     * @return string|null The ORDER BY clause string (e.g., "fieldName ASC") or the default.
     */
    public function setOrderBy($default=null){
        $orderby = null;
        foreach($this->data as $key=>$value){
            if(strpos($key,'sort:')===0){
                $field = substr($key,5);
                $orderby = $field.' '.($value==1?'ASC':'DESC');
                break;
            }
        }

        if($orderby==null && $default!=null){
            $orderby = $default;
        }
        return $orderby;
    }


    //
    //
    // $calculatedFields - is function that returns array of fieldnames or calculate and adds values of this field to result row
    //
    /**
     * Executes the constructed SQL query and formats the results.
     *
     * Handles different result formats:
     * - If `$is_ids_only` is true: Returns an array of record IDs.
     * - Otherwise: Returns a structured array including field names, record data (potentially with
     *   calculated fields and multi-language translations), total count, offset, etc.
     * - Supports a `restapi` format for simpler output.
     *
     * @param string $query The SQL query to execute.
     * @param bool $is_ids_only If true, only primary key IDs are fetched and returned.
     * @param string|null $entityName The name of the entity, defaults to `$this->config['entityName']`.
     * @param callable|null $calculatedFields A callback function to add or modify fields/values in the result set.
     *                                      It's called twice: once to get additional field names, once per row to add values.
     *                                      `function ($fields_array, $row_array=null)`
     * @param array|null $multiLangs An array of field names that support multiple languages, for which translations should be fetched.
     * @return array|false The formatted search result array, or false on database error.
     */
    public function execute($query, $is_ids_only, $entityName=null, $calculatedFields=null, $multiLangs=null){

        $mysqli = $this->system->getMysqli();

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
