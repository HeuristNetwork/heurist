<?php
namespace hserv\entity;
use hserv\utilities\USanitize;
use hserv\entity\DbEntitySearch;

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Base class for all database entities.
*
* This abstract class handles core functionalities such as reading configurations from JSON files,
* managing entity field data, and providing base save, delete, and search operations
* for specific database entity classes that extend it. It forms the foundation for
* interacting with various tables in the Heurist database schema.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/
abstract class DbEntityBase
{
    /** @var \hserv\System System handler for core Heurist operations. */
    protected $system;

    /** @var bool Indicates if database operations should be wrapped in a transaction. Defaults to true. */
    protected $need_transaction = true;

    /** @var bool Flag indicating if the current operation is an addition (insert). Forces primary key to zero. */
    protected $is_addition = false;

    /**
     * @var array|null Holds the input data for the entity, typically from `$_REQUEST`.
     *                 It's processed by methods like `prepareRecords()` and can contain
     *                 `fields` for record data, action parameters (`a`), etc.
     */
    protected $data = null;

    /** @var array|null Entity configuration loaded from its corresponding JSON file (e.g., `defRecTypes.json`). */
    protected $config;

    /** @var string|null Name of the primary key field for this entity, derived from the JSON config (`dty_Role="primary"`). */
    protected $primaryField;

    /** @var array Names of fields that support multiple languages, derived from JSON config (`rst_MultiLang=1`). */
    protected $multilangFields = array();

    /** @var array Associative array describing the entity's fields, loaded from JSON config. Used for validation and access. */
    protected $fields;

    /** @var array Numerically indexed array of non-virtual field names for this entity. */
    protected $fieldNames;

    /**
     * @var array Holds one or more records being processed for save or delete operations.
     *            Populated from `$this->data['fields']` by `prepareRecords()`.
     */
    protected $records = array();

    /**
     * @var array Stores translated values for multi-language fields, extracted during `prepareRecords()`.
     *            Structured as `[$record_idx][$fieldname][$lang_code] = $translated_value`.
     */
    protected $translation = array();

    /**
     * @var array Stores IDs of records targeted by update or delete operations.
     *            Populated by `prepareRecords()` and used for permission validation.
     */
    protected $recordIDs = array();

    /** @var string The name of the entity (e.g., "defRecTypes"), derived from the class name. */
    private $entityName;

    /**
     * @var array|null Defines foreign key checks to be performed before deletion.
     *                 Each item is an array: `['SELECT COUNT(*) FROM ... WHERE field = ', 'Error message if count > 0']`.
     *                 `#IDS#` in the query string will be replaced by the record IDs being deleted.
     */
    protected $foreignChecks = null;

    /** @var bool Flag set by `deletePrepare()` indicating if pre-deletion checks passed. */
    protected $isDeleteReady = false;

    /** @var bool If true, administrative rights are required for operations on this entity. Defaults to true. */
    protected $requireAdminRights = true;

    /**
     * @var array|null Configuration for duplication checks.
     *                 Example: `['fieldName' => 'Error message if duplicate found']`.
     */
    protected $duplicationCheck = null;

    /** @var DbEntitySearch|null Instance used for building and executing search queries. */
    protected $searchMgr;

    /**
     * Constructor for DbEntityBase.
     *
     * Initializes the system object and entity name. If `$data` is provided,
     * it sets the entity's data and reads its configuration. Otherwise, it sets
     * a basic configuration based on the entity name. Calls the `init()` method
     * if it exists in the concrete subclass.
     *
     * @param \hserv\System $system The main Heurist system object.
     * @param array|null $data Optional data to initialize the entity with (e.g., request parameters).
     */
    public function __construct( $system, $data=null ) {
       $this->system = $system;

       if(method_exists($this,'init')){
            $this->init();
       }

       $reflect = new \ReflectionClass($this);

       $this->entityName = lcfirst(substr($reflect->getShortName(),2));
       if($data){
           $this->setData($data);
       }else{
           $this->config = array('entityName'=>$this->entityName, 'entityTable'=>$this->entityName);
       }
    }


    /**
     * Verify if the entity is valid (configuration loaded and fields not empty).
     *
     * @return bool True if valid, otherwise false.
     */
    public function isvalid(){
        return is_array($this->config) && !isEmptyArray($this->fields);
    }

    /**
     * Reads and parses the JSON configuration file for the entity.
     *
     * The configuration file is expected to be in the same directory as this class,
     * named `[entityName].json` (e.g., `defRecTypes.json`).
     * It populates `$this->config`, `$this->fields`, `$this->primaryField`,
     * `$this->multilangFields`, and `$this->fieldNames`.
     * Sets a fatal error in the system object if the config file is missing or invalid.
     *
     * @return void
     */
    private function _readConfig(){

        if(@$this->data['entity']){
            $this->entityName = lcfirst(@$this->data['entity']);
        }

        $entity_file = dirname(__FILE__).'/'.basename($this->entityName.'.json'); //HEURIST_DIR.'hserv/entity

        if(file_exists($entity_file)){

           $json = file_get_contents($entity_file);

           $this->config = json_decode($json, true);


           if(is_array($this->config) && $this->config['fields']){

                $this->fields = array();
                $this->_readFields($this->config['fields']);
           }

           if(!$this->isvalid()){
                $this->system->addError(HEURIST_SYSTEM_FATAL,
                    "Configuration file $entity_file is invalid. Cannot init instance on server");
           }

        }else{
           $this->system->addError(HEURIST_SYSTEM_FATAL, 'Cannot find configuration for entity '
                        .$this->entityName.' in '.dirname(__FILE__));
        }
    }

    /**
     * Initializes the concrete entity class.
     *
     * This method is intended to be overridden by subclasses to perform
     * specific initialization tasks, such as setting up `$foreignChecks` or
     * `$duplicationCheck` properties.
     *
     * @return void
     */
    public function init(){}

    /**
     * Sets the data for the entity, typically from client request parameters.
     *
     * If the entity's configuration (`$this->fields`) hasn't been loaded yet,
     * this method also triggers reading the configuration file via `_readConfig()`.
     * It attempts to map generic 'ID' or 'recID' from input data to the entity's
     * actual primary key field name.
     *
     * @param array $data The data to be set for the entity.
     * @return void
     */
    public function setData($data){
        $this->data = $data;
        $this->records = null;

        if(!$this->isvalid()){
           $this->_readConfig();
           //rename generic ID or recID to valid primary field name for particular entity
           if(@$this->data[$this->primaryField]==null){
                if(@$this->data['ID']>0) {
                    $this->data[$this->primaryField] = $this->data['ID'];
                }elseif(@$this->data['recID']>0) {
                    $this->data[$this->primaryField] = $this->data['recID'];
                }
           }
        }
    }

    /**
     * Gets the current data array of the entity.
     *
     * @return array|null The current data stored in the entity.
     */
    public function getData(){
        return $this->data;
    }

    /**
     * Sets the records to be processed by the entity.
     *
     * This is typically used before update or delete actions.
     *
     * @param array $records An array of records.
     * @return void
     */
    public function setRecords($records){
        $this->records = $records;
    }

    /**
     * Get the current records
     *
     * @return array Returns the current records in format
     *           [id=>[field1=>val1, field2=>val2,....]
     */    
    public function getRecords($records){
        $res = array();
        if(is_array(@$records['records']))
        foreach($records['records'] as $id=>$values){
            $res[$id] = array();
            foreach($records['fields'] as $idx=>$field){
                $res[$id][$field] = $values[$idx];
            }
        }
        return $res;
    }
    

    /**
     * Gets the current records being processed by the entity.
     *
     * @return array The array of records.
     */
    public function records(){
        return $this->records;
    }


    /**
     * Sets the flag indicating whether a database transaction is needed for operations.
     *
     * @param bool $value True if a transaction is required, false otherwise.
     * @return void
     */
    public function setNeedTransaction($value){
        $this->need_transaction = $value;
    }

    /**
     * Gets the entity's configuration, optionally localized.
     *
     * If a locale different from the currently loaded one is requested,
     * it attempts to load the localized configuration via `_readConfigLocale()`.
     *
     * @param string $locale The desired locale code (e.g., 'en', 'fr'). Defaults to 'en'.
     * @return array|null The entity configuration array, or null if not loaded.
     */
    public function config( $locale='en' ){

        if(!@$this->config['locale']){
            @$this->config['locale'] = 'en';
        }

        if(@$this->config['locale']!=$locale){
            $this->_readConfigLocale($locale);
        }

        return $this->config;
    }

    //
    // working with config/setting  see configEntity.js
    //
    /**
     * Manages entity-specific configuration files (e.g., for datatables, CSV exports).
     *
     * Supports operations like listing, getting, putting (saving/creating), renaming,
     * and deleting these `.cfg` files within predefined subfolders (`datatable`, `csvexport`, `crosstabs`)
     * under the entity's storage directory (`HEURIST_FILESTORE_DIR/entity/[entityName]/`).
     *
     * Requires admin privileges.
     *
     * @param array $action An associative array specifying the operation and parameters:
     *                      - `folder`: The subfolder (e.g., 'datatable').
     *                      - `operation`: 'list', 'get', 'put', 'rename', 'delete'.
     *                      - `content`: (For 'put') The JSON string content to save.
     *                      - `file`: (For 'get', 'put', 'rename', 'delete') The filename (e.g., 'myconfig.cfg').
     *                      - `fileOld`: (For 'rename') The old filename.
     *                      - `rec_ID`: Optional record ID to scope files to a specific record's subfolder.
     *                                  Can be 'all' for listing across all record-specific folders.
     * @return mixed The result of the file operation:
     *               - For 'list': An array from `folderContent()`.
     *               - For 'get': The file content as a string.
     *               - For 'put', 'rename': The new/renamed filename on success.
     *               - For 'delete': True on success.
     *               - False on any failure or if permissions are insufficient.
     *               Errors are added to the system object.
     */
    public function files( $action ){

        if(!($this->system->getUserId()>0)){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'Insufficient rights (logout/in to refresh) for this operation');
            return false;
        }

        $res = false;

        $folder = $action['folder'];//folder
        $operation = $action['operation'];
        $content = @$action['content'];
        $filename = @$action['file'];
        if($filename!=null){
            $filename = basename($filename);
        }
        $entity_name = $this->config['entityName'];
        $rec_ID = intval(@$action['rec_ID']);

        //available values are hardcoded - prevent
        if($entity_name!='defRecTypes'){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Entity parameter is wrong or not defined');
            $res = false;
        }elseif($folder==null || !in_array($folder, array('datatable','csvexport','crosstabs'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Folder parameter is wrong or not defined');
            $res = false;
        }else{

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/'.$folder.'/'.($rec_ID>0?$rec_ID.'/':'');

        if($operation=='list'){

            if($rec_ID=='all'){

                if(file_exists($path)){

                    $dirs = array();
                    $dir = new \DirectoryIterator($path);
                    foreach ($dir as $node) {
                        if ($node->isDir() && !$node->isDot()) {
                            $folder_name = $node->getFilename();
                            if(is_numeric($folder_name)){
                                array_push($dirs, $path.$folder_name.'/');
                            }
                        }
                    }
                    $res = folderContent($dirs, 'cfg');
                }else{
                    $res = array('count'=>0,'reccount'=>0,'order'=>array());
                }
            }else{
                $res = folderContent($path, 'cfg');
            }

        }elseif($operation=='get'){

            $sMsg = 'Cannot get content of settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.errorWrongParam('Filename'));
                $res = false;
            }elseif (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = file_get_contents($path.$filename);
            }

        }elseif($operation=='put'){

            $filename = USanitize::sanitizeFileName($filename);

            //verify exetension for $filename
            $path_parts = pathinfo($filename);
            $ext = strtolower(@$path_parts['extension']);

            $sMsg = 'Cannot save content the settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.errorWrongParam('Filename'));
                $res = false;
            }elseif($ext!='cfg'){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
                $res = false;
            }elseif($content==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.errorWrongParam('Content'));
                $res = false;
            }else{

                //verify that content is valid JSON
                $config = json_decode($content, true);
                if(!is_array($config)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Content is not valid json');
                    $res = false;
                }else{

                    $swarn = folderCreate2($path, '', false);
                    if($swarn!=''){
                        $this->system->addError(HEURIST_ERROR, $sMsg.$swarn);
                        $res = false;
                    }else{
                        $res = file_put_contents($path.$filename, $content);
                        if($res!==false){
                            $res = $filename;
                        }
                    }
                }
            }

        }elseif($operation=='rename'){

            $sMsg = 'Cannot rename the settings file. ';

            $fileOld = @$action['fileOld'];
            if(file_exists($path.$fileOld)){

                $filename = USanitize::sanitizeFileName($filename);

                //verify exetension for $filename
                $path_parts = pathinfo($filename);
                $ext = strtolower(@$path_parts['extension']);


                if($filename==null){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.errorWrongParam('New filename'));
                    $res = false;
                }elseif($ext!='cfg'){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
                    $res = false;
                }elseif(!copy($path.$fileOld, $path.$filename)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg);
                    $res = false;
                }else{
                    unlink($path.$fileOld);
                    $res = $filename;
                }

            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Settings file does not exist');
                $res = false;
            }

        }elseif($operation=='delete'){

            $sMsg = 'Cannot remove the settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.errorWrongParam('Filename'));
                $res = false;
            }elseif (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = unlink($path.$filename);
            }

        }

        }

        return $res;
    }


    /**
     * Perform actions based on the current data request.
     *
     * @return mixed Result of the action.
     */
    public function run(){

        if(!$this->isvalid()){
            return false;
        }

        $res = false;

        $action =@$this->data['a'];

        switch ($action) {
           case 'search':
                $res = $this->search();
                break;
           case 'title':
                $res = $this->search_title();
                break;
           case 'add':
                $this->is_addition = true;
                $this->data['a'] = 'save';
                $res = $this->save();
                $this->is_addition = false;
                break;
           case 'save':
                $res = $this->save();
                break;
           case 'delete':
                $res = $this->delete();
                break;
           case 'config':
                $res = $this->config( @$this->data['locale'] );
                break;
           case 'files':
                // working with settings/config files
                // get list of files by extension
                // put data into file
                // load date from file
                $res = $this->files($this->data);
                break;
           case 'counts':
                //various counts(aggregations) request - implementation depends on entity
                $res = $this->counts();
                break;
           case 'action':
           case 'batch':
                $res = $this->batch_action();
                if($res &&
                    !(@$this->data['get_translations'] ||
                    in_array($this->config['entityName'],array('defRecTypes','defDetailTypes','defTerms','defRecTypes'))))
                {
                        $this->_cleanDbDefCache();
                }
                break;
           default:
                $this->system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
        }
        return $res;
    }

    //
    //
    //
    /**
     * Clears the database definition cache if the current entity is one that affects it.
     *
     * Checks if the entity's `tablePrefix` is in a predefined list of cache-affecting prefixes
     * (rty, dty, rst, trm, rtg, dtg, vcg, swf).
     *
     * @return void
     */
    private function _cleanDbDefCache(){

        if(is_array($this->config) &&
            in_array($this->config['tablePrefix'], array('rty','dty','rst','trm','rtg','dtg','vcg','swf')))
        { //affected entity
            $this->system->cleanDefCache();
        }
    }

    /**
     * Saves one or more entity records to the database.
     *
     * This method orchestrates the save process:
     * 1. Prepares records using `prepareRecords()` if not already done.
     * 2. Validates user permissions via `_validatePermission()`.
     * 3. Validates mandatory fields and general field values for each record
     *    using `_validateMandatory()` and `_validateValues()`.
     * 4. If all validations pass, iterates through records and saves them using `mysql__insertupdate()`.
     *    - Handles database transactions if `need_transaction` is true.
     *    - Updates translations for multi-language fields.
     * 5. Clears the database definition cache if applicable.
     *
     * @return array|false An array of saved record IDs on success, or false if any step fails.
     *                     Errors are added to the system object.
     */
    public function save(){

        //extract records from $_REQUEST data
        if($this->records==null && !$this->prepareRecords()){ //records can be pepared beforehand
            return false;
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }

        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }

            //validate values
            if(!$this->_validateValues()){
                return false;
            }
        }

        //array of inserted or updated record IDs
        $results = array();

        //start transaction
        $mysqli = $this->system->getMysqli();

        if($this->need_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        foreach($this->records as $rec_idx => $record){

            //exclude virtual fields
            $fieldvalues = $record;
            $values = array();
            foreach($this->fields as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual' || !array_key_exists($fieldname, $record)) {continue;}
                $values[$fieldname] = $record[$fieldname];
            }

            $isinsert = (intval(@$record[$this->primaryField])<1);

            if(!$isinsert && count($values)<2){
                //no fields except id - skip this record
                $ret = $record[$this->primaryField];
            }else{
                //save data
                $ret = mysql__insertupdate($mysqli,
                                        $this->config['tableName'], $this->fields,
                                        $values );
            }

            if($ret===true || $ret==null){ //it returns true for non-numeric primary field
                   $results[] = $record[$this->primaryField];
            }elseif(is_numeric($ret)){
                   $this->records[$rec_idx][$this->primaryField] = $ret;
                   $results[] = $ret;
            }else{
                    //rollback
                    if($this->need_transaction){
                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}
                    }
                    $this->system->addError(HEURIST_INVALID_REQUEST,
                        'Cannot save data in table '.$this->config['entityName'], $ret);
                    return false;
            }

            //update translations
            if(!isEmptyArray(@$this->translation[$rec_idx]))
            {
                foreach($this->multilangFields as $fieldname){
                    //delete previous translations for this record
                    if(!$isinsert){
                        $mysqli->query('DELETE FROM defTranslations where trn_Source="'
                            .$fieldname.'" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);
                    }
                    if(@$this->translation[$rec_idx][$fieldname]!=null){

                        $langs = $this->translation[$rec_idx][$fieldname];

                        foreach($langs as $lang=>$value){
                            if($value!=null && trim($value)!=''){
                                mysql__insertupdate($mysqli,
                                        'defTranslations', 'trn',
                                array('trn_ID'=>0,
                                      'trn_Source'=>$fieldname,
                                      'trn_Code'=>$this->records[$rec_idx][$this->primaryField],
                                      'trn_LanguageCode'=>$lang,
                                      'trn_Translation'=>$value));
                            }
                        }
                    }
                }

            }elseif(!$isinsert && !empty($this->multilangFields)){
                //remove all translation for this record

                $mysqli->query('DELETE FROM defTranslations where trn_Source LIKE "'
                .$this->config['tablePrefix']
                .'%" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);

            }



        }//for records
        if($this->need_transaction){
            //commit
            $mysqli->commit();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}

            $this->_cleanDbDefCache();
        }
        return $results;
    }//save

    /**
     * Prepares for a delete operation by validating record IDs and permissions.
     *
     * Also performs foreign key checks if `foreignChecks` is configured for the entity.
     * Sets the `isDeleteReady` flag.
     *
     * @return bool True if all checks pass and records are ready for deletion, false otherwise.
     *              Errors are added to the system object on failure.
     */
     protected function deletePrepare(){

        if(!@$this->recordIDs){
            $this->recordIDs = prepareIds($this->data[$this->primaryField]);
        }

        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        if(!$this->_validatePermission()){
            return false;
        }

        $this->isDeleteReady = true;

        if(empty($this->foreignChecks)){
            $this->isDeleteReady = true;
            return true;
        }

        $compare = (count($this->recordIDs)==1?'='.$this->recordIDs[0] :SQL_IN.implode(',', $this->recordIDs).')');

        foreach($this->foreignChecks as $check){

            $query = $check[0];

            if(strpos($query,'#IDS#')>0){
                $query = str_replace('#IDS#',implode(',', $this->recordIDs),$query);
            }else{
                $query .= $compare;
            }

            $ret = mysql__select_value($this->system->getMysqli(), $query);

            if($ret>0){
                $msg = @$check[1]?$check[1]:'Cannot delete '.$this->config['entityTitle'];
                $this->system->addError(HEURIST_ACTION_BLOCKED, $msg);
                return false;
            }
        }

        $this->isDeleteReady = true;
        return true;
    }

    /**
     * Deletes records from the database.
     *
     * Calls `deletePrepare()` if not already done. If preparation is successful,
     * it proceeds to delete the records specified in `$this->recordIDs`.
     * Handles disabling/enabling foreign key checks around the delete query.
     * Also deletes associated translations for multi-language fields.
     * Clears the database definition cache if applicable.
     *
     * @param bool $disable_foreign_checks If true, foreign key checks are temporarily disabled
     *                                     during the delete operation. This parameter is effectively
     *                                     ignored as foreign key checks are always disabled/re-enabled
     *                                     around the main delete query.
     * @return bool True on successful deletion of all specified records, false otherwise.
     *              Errors (e.g., no records found, DB error) are added to the system object.
     */
    public function delete($disable_foreign_checks=false){

        if(!$this->isDeleteReady && !$this->deletePrepare()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

        mysql__foreign_check($mysqli, false);
        $query = SQL_DELETE.$this->config['tableName'].SQL_WHERE.predicateId($this->primaryField, $this->recordIDs);

        $ret = $mysqli->query($query);
        $affected = $mysqli->affected_rows;

        //
        // delete from translation table all fields that starts with current table prefix and with given record ids
        // array('rty','dty','ont','vcb','trm','rst','rtg')
        //
        if(!empty($this->multilangFields))
        {
            $mysqli->query(SQL_DELETE.'defTranslations where trn_Source LIKE "'
                                .$this->config['tablePrefix'].'%" AND '
                                .predicateId('trn_Code', $this->recordIDs));

        }

        mysql__foreign_check($mysqli, true);

        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR,
                    'Cannot delete from table '.$this->config['entityName'], $mysqli->error);
            return false;
        }elseif($affected===0){
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found');
            return false;
        }

        $this->_cleanDbDefCache();

        return true;
    }

    /**
     * Placeholder for batch actions. Concrete entity classes should override this.
     *
     * @return false Always returns false in the base class.
     */
    public function batch_action(){
        return false;
    }

    //
    // various counts(aggregations) request - implementation depends on entity
    //
    /**
     * Placeholder for count/aggregation queries. Concrete entity classes should override this.
     *
     * @return int Always returns 0 in the base class.
     */
    public function counts(){
        return 0;
    }

    //
    //
    //
    /**
     * Validates field names requested for a search operation.
     *
     * Ensures that all field names specified in `$this->data['details']` exist in
     * the entity's field configuration (`$this->fields`).
     * Also ensures the primary key field is the first field in the list if multiple fields are requested.
     *
     * @return bool True if all requested fields are valid, false otherwise.
     *              Errors are added to the system object for invalid field names.
     */
    protected function _validateFieldsForSearch(){

            foreach($this->data['details'] as $fieldname){
                if(!@$this->fields[$fieldname]){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                    return false;
                }
            }
            //ID field is mandatory and MUST be first in the list
            $idx = array_search($this->primaryField, $this->data['details']);
            if($idx>0){
                unset($this->data['details'][$idx]);
                $idx = false;
            }
            if($idx===false){
                array_unshift($this->data['details'], $this->primaryField);
            }

            return true;
    }


    //
    // Validates permission for delete and update operations
    //
    /**
     * Validates if the current user has the necessary permissions for the operation.
     *
     * Checks if admin rights are required (`$this->requireAdminRights`) and if the user is an admin.
     * Also checks if the user is logged in.
     * This method is typically called before save or delete operations involving specific records.
     *
     * @return bool True if permissions are sufficient, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){


        if($this->requireAdminRights &&
            !$this->system->isAdmin() &&
            ((!isEmptyArray($this->recordIDs))
            || (!isEmptyArray($this->records)))){ //there are records to update/delete

            $ent_name = @$this->config['entityTitlePlural']?$this->config['entityTitlePlural']:'this entity';

            $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You are not admin and can\'t edit '.$ent_name
                    .'. Insufficient rights (logout/in to refresh) for this operation '
                    .$this->system->getUserId().'  '.print_r($this->system->getCurrentUser(), true));
            // You have to be Administrator of group \'Database Managers\' for this operation
            return false;
        }

        if(!$this->system->hasAccess()){
             $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You must be logged in. Insufficient rights (logout/in to refresh) for this operation');
             return false;
        }

        return true;
    }

    //
    //
    //
    /**
     * Validates field values based on their type.
     *
     * Currently, it ensures that 'resource' type fields (except 'ulf_MimeExt')
     * are set to null if their integer value is less than 1.
     * This method is intended to be called on a single record's data, typically
     * stored in `$this->data['fields']`.
     *
     * @return bool Always returns true in the current implementation.
     *              (Future extensions might add more validation rules and return false.)
     */
    protected function _validateValues(){

        $fieldvalues = $this->data['fields'];//current record

        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') {continue;}

            $value = @$fieldvalues[$fieldname];

            //ulf_MimeExt is the only nonnumeric resource
            if(@$field_config['dty_Type']=='resource' && $fieldname!='ulf_MimeExt'){
                if(intval($value)<1) {$this->data['fields'][$fieldname] = null;}
            }
        }

        return true;
    }

    //
    //
    //
    /**
     * Validates that all mandatory fields have values.
     *
     * Checks fields marked with `rst_RequirementType = 'required'` in the entity's
     * field configuration (`$this->fields`).
     * This method operates on a single record's data, typically from `$this->data['fields']`.
     * For multi-language fields, it checks the first value if it's an array.
     *
     * @return bool True if all mandatory fields are filled, false otherwise.
     *              Errors are added to the system object for missing mandatory fields.
     */
    protected function _validateMandatory(){

        $fieldvalues = $this->data['fields'];

        $rec_ID = intval(@$fieldvalues[$this->primaryField]);
        $isinsert = ($rec_ID<1);

        foreach($this->fields as $fieldname=>$field_config){
            if (@$field_config['dty_Role']=='virtual' ||
                @$field_config['dty_Role']=='primary' ||
                @$field_config['rst_RequirementType'] != 'required')
            {
                continue;
            }

            if(!(array_key_exists($fieldname, $fieldvalues) || $isinsert)){
                continue;
            }

            $value = @$fieldvalues[$fieldname];

            if(@$field_config['rst_MultiLang'] && is_array($value)){
                $value = !empty($value)?$value[0]:'';
            }

            if( isEmptyStr($value) ){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Field $fieldname is mandatory.");
                return false;
            }
        }
        return true;
    }

    //
    // Returns localized configuration
    //
    /**
     * Reads localized entity configuration (title, plural title, field display names, help text).
     *
     * Merges localized values into the existing `$this->config` if a locale-specific
     * JSON file (e.g., `[entityName]_[locale].json`) is found.
     *
     * @param string $locale The locale code (e.g., 'fr', 'de'). Defaults to 'en'.
     * @return void
     */
    private function _readConfigLocale( $locale='en' ){

        $entity_file = dirname(__FILE__).'/'.lcfirst(@$this->data['entity']) //HEURIST_DIR.'hserv/entity/'
            .($locale=='en'?'':('_'.$locale)).'.json';

        if(file_exists($entity_file)){

           $json = file_get_contents($entity_file);
           $locale_config = json_decode($json, true);

           $this->config['locale'] = $locale;
           $this->config['entityTitle'] = $locale_config['entityTitle'];
           $this->config['entityTitlePlural'] = $locale_config['entityTitlePlural'];

           $this->_fieldsSetLocale( $this->config['fields'], $locale_config['fields'] );
        }
    }

    //
    //
    //
    /**
     * Recursively searches for a field definition by its ID within a nested field structure.
     *
     * @param string $id The 'dtID' of the field to find.
     * @param array $fields An array of field definition objects, potentially with 'children'.
     * @return array|null The field definition array if found, null otherwise.
     */
    private function _getFieldByID($id, $fields){
        foreach($fields as $field){

            if(@$field['dtID']==$id){
                return $field;
            }elseif(!isEmptyArray(@$field['children'])){
                $res = $this->_getFieldByID($id, $field['children']);
                if($res){
                    return $res;
                }
            }
        }
        return null;
    }

    //
    // assign localized name and description for fields
    //
    /**
     * Recursively applies localized display names and help text to a nested field structure.
     *
     * Modifies the `$fields` array by reference.
     *
     * @param array &$fields The field structure to update (passed by reference).
     * @param array $fields_locale The localized field definitions containing `rst_DisplayName` and `rst_DisplayHelpText`.
     * @return void
     */
    private function _fieldsSetLocale( &$fields, $fields_locale ){

        foreach($fields as $idx=>$field){

            if(!isEmptyArray(@$field['children'])){

                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['groupHeader']){
                    $fields[$idx]['groupHeader'] = $fld_loc['groupHeader'];
                }

                $this->_fieldsSetLocale($fields[$idx]['children'], $fields_locale);

            }else{

                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['dtFields']){
                    if(@$fld_loc['dtFields']['rst_DisplayName']){
                        $fields[$idx]['dtFields']['rst_DisplayName'] = $fld_loc['dtFields']['rst_DisplayName'];
                    }
                    if(@$fld_loc['dtFields']['rst_DisplayHelpText']){
                        $fields[$idx]['dtFields']['rst_DisplayHelpText'] = $fld_loc['dtFields']['rst_DisplayHelpText'];
                    }
                }


            }
        }
    }

    //
    // read fields definition from config file
    // assign primaryField, multilangFields
    //
    /**
     * Recursively reads field definitions from the configuration and populates internal properties.
     *
     * Populates `$this->fieldNames`, `$this->fields`, `$this->primaryField`,
     * and `$this->multilangFields` based on the provided field structure.
     *
     * @param array $fields An array of field definition objects from the JSON configuration,
     *                      potentially with nested 'children'.
     * @return void
     */
    private function _readFields($fields){

        foreach($fields as $field){

            if(!isEmptyArray(@$field['children'])){
                $this->_readFields($field['children']);

            }else{
                if(@$field['dtFields']['dty_Role']!='virtual'){
                    $this->fieldNames[] = $field['dtID'];
                }
                $this->fields[ $field['dtID'] ] = $field['dtFields'];

                if(@$field['dtFields']['dty_Role']=='primary'){
                    $this->primaryField = $field['dtID'];
                }
                if(@$field['dtFields']['rst_MultiLang']){
                    array_push($this->multilangFields, $field['dtID']);
                }
            }
        }

    }

    // need to rename temporary enity files to permanent  "entity/[entity name]/recID.png"
    // $tempfile - file to be either
    // 1) renamed to recID (if it is temp file started with ~)
    // 2) copied
    /**
     * Renames or copies a temporary entity image file to its permanent location.
     *
     * Handles different versions (icon, thumbnail, full). If a temporary file
     * (name starting with '~') is provided, it's moved. Otherwise, the provided
     * `$tempfile` is copied. Also handles removing existing files with different extensions
     * if a new image replaces an old one (e.g. png replacing svg).
     *
     * @param string $tempfile The path to the temporary image file or its temporary name (starting with '~').
     * @param int $recID The record ID to associate the image with (used in the permanent filename).
     * @param string|null $version Optional image version ('icon', 'thumbnail'). If null, processes full image and thumbnail.
     * @return void
     */
    protected function renameEntityImage($tempfile, $recID, $version=null){

        $isSuccess = false;

        $entity_name = $this->config['entityName'];
        if($version==null){  //if version is defined we copy only it (icon or thumbnail)
            $version = '';
        }
        $lv = strlen($version);

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/';//destination

        if(strpos($tempfile,'~')===0){
            //temp file is in the same folder as destination

            $directory = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($directory);

            foreach ($iterator as $filepath => $info) {  //rec. iteration need to copy all versions (thumb and full img)
                  if(!$info->isFile()) {continue;}

                  $filename = $info->getFilename();
                  $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
                  //$extension = $info->getExtension(); since 5.3.6


                  if ($filename==$tempfile.'.'.$extension) {
                      $pathname = $info->getPath();
                      $tempfile_ = $info->getPathname();
                      if($lv==0 || substr($pathname, -$lv) === $version){
                            $new_name = $pathname.'/'.$recID.'.'.$extension;
                            $isSuccess = rename ($tempfile_, $new_name);
                      }
                      if(file_exists($tempfile_)){
                            unlink( $tempfile_ );
                      }
                  }
            }

        }elseif(file_exists($tempfile)){
            $path_parts = pathinfo($tempfile);
            $ext = strtolower($path_parts['extension']);

            //special case - remove file with the same name and different ext
            $ext2 = ($ext=='svg')?'png':'svg';

            if($version!=''){ //copy only icon or thumb
                $new_name = $path.$version.'/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name);

                fileDelete($path.$version.'/'.$recID.'.'.$ext2);
            }else{
                $new_name = $path.$recID.'.'.$ext;
                $new_name_thumb = $path.'thumbnail/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name) &&  fileCopy($tempfile, $new_name_thumb);

                fileDelete($path.$recID.'.'.$ext2);
                fileDelete($path.'thumbnail/'.$recID.'.'.$ext2);
            }



        }

        if(!$isSuccess){
            //Can't copy file $tempfile as  $entity_name  image
        }
    }

    //find $tempfile among temporary files in entity folder and return file info
    /**
     * Finds a temporary entity image file within the entity's image directory.
     *
     * Searches for a file matching `[tempfile].[extension]` in the entity's base image directory.
     *
     * @param string $tempfile The base name of the temporary file (without extension, e.g., starting with '~').
     * @return \DirectoryIterator|null A DirectoryIterator object for the found file, or null if not found.
     */
    protected function getTempEntityFile($tempfile){
        $entity_name = $this->config['entityName'];

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/';

        $directory = new \DirectoryIterator($path);//RecursiveDirectoryIterator
        $iterator = new \IteratorIterator($directory);//Recursive

        foreach ($iterator as $filepath => $info) {
              if(!$info->isFile()) {continue;}

              $filename = $info->getFilename();
              $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
              //$extension = $info->getExtension(); since 5.3.6

              if ($filename==$tempfile.'.'.$extension) {
                    return $info;
              }
        }
        return null;
    }

    //
    //
    //
    /**
     * Gets the server file path for an entity's image.
     *
     * Wraps `resolveEntityFilename` to get the filename component.
     *
     * @param int $recID The record ID.
     * @param string|null $version The image version (e.g., 'icon', 'thumbnail').
     * @param string|null $db_name Optional database name.
     * @param string|null $extension Optional file extension.
     * @return string|null The absolute file path to the image, or null if not found.
     */
    protected function getEntityImagePath($recID, $version=null, $db_name=null, $extension=null){

            $entity_name = $this->config['entityName'];

            list($filename, $content_type, $url) = resolveEntityFilename($entity_name, $recID, $version, $db_name, $extension);

            return $filename;
    }

    //
    // validate duplication
    //
    /**
     * Performs a duplication check for a specific field value.
     *
     * Checks if the value of `$this->records[$idx][$field]` already exists in another record
     * for the specified `$field`.
     *
     * @param int $idx The index of the current record in `$this->records`.
     * @param string $field The name of the field to check for duplication.
     * @param string $message The error message to set if a duplicate is found.
     * @param string $title Optional title for the error message.
     * @return bool True if no duplicate is found, false otherwise.
     *              Errors are added to the system object on duplication.
     */
    protected function doDuplicationCheck($idx, $field, $message, $title = ''){

            if(@$this->records[$idx][$field]){
                $mysqli = $this->system->getMysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT {$this->primaryField} FROM ".$this->config['tableName']."  WHERE $field='"
                        .$mysqli->real_escape_string( $this->records[$idx][$field] )."'");
                if($res>0 && $res!=@$this->records[$idx][$this->primaryField]){

                    $sup_info = null;
                    if($this->config['tableName']=='defDetailTypes'){ //special case
                        $sup_info = array($this->primaryField=>$res);
                    }

                    $this->system->addError(HEURIST_ACTION_BLOCKED, $message, $sup_info, $title);
                    return false;
                }
            }
            return true;
    }

    //
    // extracts records from "data" parameter and fills $this->recordIDs and $this->records
    // it is used in delete, save
    //
    //  fields:[fldname1:value,fieldname2:values,.....]
    //  translation:[fldname:"lang:value",fieldname2:"lang:value",.....]
    //
    /**
     * Prepares records from `$this->data` for save or delete operations.
     *
     * - Parses `$this->data['fields']` (which can be a single record or an array of records).
     * - Populates `$this->records` with the record(s) to be processed.
     * - Populates `$this->recordIDs` with the primary key values of these records.
     * - Extracts translations for multi-language fields into `$this->translation`.
     * - Performs duplication checks if `$this->duplicationCheck` is configured.
     *
     * @return bool True if records are prepared successfully and duplication checks pass,
     *              false if `fields` parameter is missing or a duplication check fails.
     *              Errors are added to the system object on failure.
     */
    protected function prepareRecords(){
        //fields contains record data
        if(@$this->data['fields'] && is_string($this->data['fields'])){
            $this->data['fields'] = json_decode($this->data['fields'], true);
        }


        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missing 'fields' parameter. Fields are not defined");
                return false;
        }
        //detect whether this is multi record save
        if(array_keys($this->data['fields']) !== range(0, count($this->data['fields']) - 1)){
            //number of keys equals to number of entries it means single record
            $this->records = array();
            $this->records[0] = $this->data['fields'];
        }else{
             //this is 2dim array
            $this->records = $this->data['fields'];
        }

        //exctract primary keys
        $this->recordIDs = array();
        foreach($this->records as $idx=>$record){
            if($this->is_addition){
                $this->records[$idx][$this->primaryField] = 0;
            }
            $rec_ID = @$this->records[$idx][$this->primaryField];
            if($rec_ID>0){
                $this->recordIDs[] = $rec_ID;
            }

            //extract translated values into separate array
            foreach($this->multilangFields as $fieldname){
                $values = $record[$fieldname];
                if(is_array($values)){
                    $mainvalue = null;
                    foreach($values as $k => $val){

                        list($lang, $val) = extractLangPrefix($val);

                        if($lang!=null)
                        {
                            if(!@$this->translation[$idx]){
                                $this->translation[$idx] = array();
                            }
                            if(!@$this->translation[$idx][$fieldname]){
                                $this->translation[$idx][$fieldname] = array();
                            }

                            $this->translation[$idx][$fieldname][$lang] = $val;

                        }elseif(!$mainvalue){
                            //without lang prefix, take first only
                            $mainvalue = $val;
                        }
                    }//values
                    if($mainvalue!=null && @$this->translation[$idx][$fieldname]){
                        $this->records[$idx][$fieldname] = $mainvalue;
                    }
                }
            }

            if(!empty($this->duplicationCheck)){
                foreach($this->duplicationCheck as $field=>$msg){
                    if(!$this->doDuplicationCheck($idx, $field, $msg)){
                         return false;
                    }
                }
            }
        }//foreach

        return true;
    }

    //
    //
    //
    /**
     * Searches for entity records and returns only their titles (or names).
     *
     * Sets the 'details' parameter to 'name' and calls the main `search()` method.
     * It then extracts the second column (assumed to be the name/title) from each result.
     *
     * @return array|false An array of titles/names, or false if the search fails.
     */
    public function search_title(){

        $this->data['details'] = 'name';

        $ret = $this->search();
        if($ret!==false){
            $res = array();
            foreach($ret['records'] as $record){
                //$record[0]
                $res[] = $record[1];
            }
            return $res;
        }else{
            return false;
        }

    }


    /**
     * Prepares for a search operation by initializing the search manager and validating search parameters.
     *
     * This base method is a prerequisite for conducting searches on the entity. It performs the following:
     * 1. Checks if the current entity instance is valid (i.e., configuration loaded) using `isvalid()`.
     * 2. If valid, it instantiates a `DbEntitySearch` manager (`$this->searchMgr`) with the
     *    current system, entity configuration (`$this->config`), and field definitions (`$this->fields`).
     * 3. It then calls the `validateParams()` method of the `DbEntitySearch` manager, passing
     *    the current entity's data (`$this->data`) which typically contains the search query and options.
     *    `$this->data` may be updated by `validateParams()` if parameter modifications occur (e.g., defaults applied).
     *
     * Note: This method itself does not execute the search query or return search results.
     * Concrete subclasses are expected to either override this method to perform the full search
     * or use the initialized `$this->searchMgr` in subsequent steps to execute the query and retrieve results.
     *
     * The search parameters are expected to be in `$this->data`, often populated from `$_REQUEST`.
     * Common parameters managed by `DbEntitySearch` might include:
     * - `q`: The search query string or structured query.
     * - `details`: Specifies which fields to return ('ids', 'full', 'name', or an array of field names).
     * - `limit`: The maximum number of records to return.
     * - `offset`: The starting offset for results (for pagination).
     * - `sortby`: Field(s) to sort by.
     * - `facet`: Fields for faceted search.
     * - `fmt`: Output format (though this base method doesn't handle final output).
     *
     * @return bool True if the entity is valid and search parameters are successfully validated by `DbEntitySearch`.
     *              Returns false if the entity is invalid or parameter validation fails.
     *              Errors are typically added to the system object by `DbEntitySearch::validateParams()` on failure.
     */
    public function search(){

        if($this->isvalid()){
            $this->searchMgr = new DbEntitySearch( $this->system, $this->config, $this->fields);

            $res = $this->searchMgr->validateParams( $this->data );
            if(!is_bool($res)){
                $this->data = $res;
            }else{
                if(!$res) {return false;}
            }

            return true;
        }else{
            return false;
        }
    }

}
?>
