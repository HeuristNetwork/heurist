<?php
/**
* recordsBatch.php - Class RecordsBatch
*
* Class to perform actions in batch of records
*   1) add/replace and delete details
*   2) change record type
*   3) add reverse parent pointer field
*
* Controller is record_batch.php
*
* @project     Heurist academic knowledge management system
* @package Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;
use hserv\entity\DbRecUploadedFiles;

// Include Composer autoloader if not already done.
require_once dirname(__FILE__).'/../../../vendor/autoload.php';

require_once dirname(__FILE__).'/recordModify.php';
require_once dirname(__FILE__).'/recordTitleMask.php';
require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';

define('DEBUG_RUN', false);
define('ERR_REC_MODDATE','Cannot update record modification date. ');
define('ERR_REC_TITLE', 'Cannot update record title');
define('R_ARROW',' &Rightarrow; ');
define('FILE_NO','File #');

/**
* Class RecordsBatch
* 
* Methods for batch actions for list of records (recIDs) OR by record type rtyID
*
* detailsAdd - add details
* replace
* detailsReplace - replace + detailsAdd
* detailsDelete
* multiAction  - executes several actions in turn: add,replace,delete
*
* addRevercePointerForChild - Adds parent pointer field converts - converts existing
*                             records to child record for given rectype/detailtype
* 
* changeRecordTypeInBatch - Changes rec_RecTypeID in batch
*
* extractPDF - extracts PDF file content is put it into DT_EXTRACTED_TEXT field
* @package Records\Edit
*/
class RecordsBatch
{
    private $system;

    /*
    *       recIDs - list of records IDS to be processed or 'ALL'
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added,replaced or deleted
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value,  subs= 1 | 0
    *       for delete: sVal, subs= 1 | 0
    *       tag  = 0|1  - add system tag to mark processed records
    *       details_encoded = 0|1 - val or rVal should be decoded
    *                   2 - restore "../" from ^^/
    */
    private $data;

    /*
    * records to be processed
    */
    private $recIDs;

    /*
    * distinct array of record types
    */
    private $rtyIDs;

    private $dt_extended_description = 0;

    /*
    passed     _tag _error
    noaccess
    processed - no rights to edit
    undefined - field definition not found (add) or search value not found (edit,delete)
    limited
    errors    - sql error on search or updata
    */
    private $result_data = array();


    private $session_id = null;

    private $not_putify = null; //fields that will not html purified
    private $purifier = null;   //html purifier instance

    public function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;

       $this->session_id = @$this->data['session'];
       if($this->session_id!=null){
            mysql__update_progress($system->getMysqli(), $this->session_id, true, '0,1');
       }

       //refresh list of current user groups
       $this->system->getUserGroupIds(null, true);
    }

    /**
     * Initializes the HTML Purifier instance and sets up a list of detail type IDs
     * that should not be subjected to HTML purification.
     *
     * Currently, only `DT_CMS_EXTFILES` is added to the `not_purify` list.
     * The HTML Purifier instance itself (`$this->purifier`) is intended to be initialized
     * via `USanitize::getHTMLPurifier()`, but this line is currently disabled in the code.
     * This method ensures that initialization happens only once.
     *
     * @access private
     * @return void
     */
    private function _initPutifier(){
        if($this->purifier==null){
            $not_purify = array();
            if($this->system->defineConstant('DT_CMS_EXTFILES')){ array_push($not_purify, DT_CMS_EXTFILES);}

            $this->not_purify = $not_purify;
            //$this->purifier = USanitize::getHTMLPurifier(); DISABLED
        }
    }

    /**
     * Sets or updates the internal data array for the batch operation.
     *
     * This method allows changing the parameters for the batch operation after the object has been instantiated.
     * The `$data` array typically includes parameters like 'recIDs', 'rtyID', 'dtyID', values for addition/replacement, etc.
     *
     * @param array $data An associative array containing the parameters for the batch operation.
     *                    The specific required and optional keys depend on the batch action to be performed.
     * @return void
     */
    public function setData($data){
        $this->data = $data;
    }

    /**
     * Retrieves the results of the last executed batch operation.
     *
     * The structure of the result array (`$this->result_data`) is populated by methods like
     * `_assignTagsAndReport` and varies depending on the operation performed. It generally includes
     * counts of processed records, errors, and potentially lists of affected/failed record IDs.
     *
     * @return array An associative array containing the report of the last batch operation.
     *               The array is empty if no operation has been run or if it was reset.
     */
    public function getReport(){
        return $this->result_data;
    }

    /**
     * Validates the record type ID (`rtyID`) and detail type ID (`dtyID`) parameters
     * provided in `$this->data`.
     *
     * - `rtyID` (if provided) must be a positive integer or an array of positive integers.
     * - `dtyID` must be a positive integer.
     *
     * Errors are added to the system object if validation fails.
     *
     * @access private
     * @return bool True if the parameters are valid, false otherwise.
     */
    private function _validateDetailType(){

        $rtyID = @$this->data['rtyID'];
        $dtyID = $this->data['dtyID'];//detail to be affected

        if ($rtyID && !((is_array($rtyID) || (ctype_digit($rtyID) && $rtyID>0))) ){
            $this->system->addError(HEURIST_ERROR, "Wrong parameter record type id $rtyID");
            return false;
        }

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $this->system->addError(HEURIST_ERROR, "Wrong parameter detail type id $dtyID");
            return false;
        }

        return true;
    }

    /**
     * Validates overall parameters for a batch operation and determines the count and
     * accessibility of records to be processed.
     *
     * It performs the following checks and operations:
     * 1. User Permissions: Checks if the current user has 'edit' permissions using `userCheckPermissions`.
     * 2. Detail Type Validation: Calls `_validateDetailType` unless the action is 'reset_thumbs'.
     * 3. Record IDs Presence: Ensures `recIDs` are provided in `$this->data`.
     * 4. Record Counting and Accessibility:
     *    - If the user is an admin and `recIDs` is 'ALL', it counts all records, optionally filtered by `rtyID`.
     *      Sets `$this->recIDs` to `['all']`.
     *    - Otherwise, it normalizes `recIDs` to an array.
     *    - Filters these IDs by `rtyID` if provided.
     *    - Further filters IDs based on user's edit rights (admin sees all, others see owned/group-owned).
     *    - Populates `$this->recIDs` with the list of accessible record IDs.
     *    - Populates `$this->rtyIDs` with the distinct record type IDs of the accessible records.
     *    - Initializes `$this->result_data` with 'passed' and 'noaccess' counts.
     *
     * Errors are added to the system object for failed validations.
     *
     * @access private
     * @return bool True if initial validations pass and there are potentially processable records (or 'all' mode),
     *              false if critical validation fails (e.g., no permission, missing recIDs).
     *              Note: It can return true even if `$this->recIDs` ends up empty after filtering,
     *              allowing the calling method to report based on initial 'passed' vs 'noaccess' counts.
     */
    private function _validateParamsAndCounts()
    {
        // Check that the user is allowed to edit records
        if(!userCheckPermissions($this->system, 'edit')){
            return false;
        }

        if(@$this->data['a']!='reset_thumbs' && !$this->_validateDetailType()){
            return false;
        }

        if (!( @$this->data['recIDs'])){ //record ids to be updated
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Insufficent data passed: records not defined');
            return false;
        }

        $mysqli = $this->system->getMysqli();

        if($this->system->isAdmin() && $this->data['recIDs']=='ALL'){

            $query = 'select count(*) from Records';

            $rty_ID = @$this->data['rtyID'];
            if(is_array($rty_ID)){
                if(!empty($rty_ID)){
                    $query .= ' WHERE rec_RecTypeID in ('.getCommaSepIds($rty_ID).')';
                    $this->rtyIDs = $rty_ID;
                }
            }elseif($rty_ID >0){
                $query .= ' WHERE rec_RecTypeID = '.$rty_ID;
                $this->rtyIDs = array($rty_ID);
            }

            $passedRecIDCnt = mysql__select_value($mysqli, $query);

            $this->result_data = array('passed'=>$passedRecIDCnt,
                        'noaccess'=>0,'processed'=>0);

            $this->recIDs = array('all');

        }else{

            //normalize recIDs to an array for code below
            $recIDs = prepareIds($this->data['recIDs']);

            $rtyID = intval(@$this->data['rtyID']);

            $passedRecIDCnt = count($recIDs);

            if ($passedRecIDCnt>0) {//check editable access for passed records

                if($rtyID>0){ //filter for record type
                    $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in ("
                                        .implode(",",$recIDs).")");
                    $recIDs = prepareIds($recIDs);//redundant for snyk
                    $passedRecIDCnt = is_array($recIDs)?count($recIDs):0;
                }
                if($passedRecIDCnt>0){
                    //exclude records if user has no right to edit
                    if($this->system->isAdmin()){ //admin of database managers
                        $this->recIDs = $recIDs;
                    }else{
                        $this->recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in ("
                            .implode(",",$recIDs).") and rec_OwnerUGrpID in (0,"
                            .join(",",$this->system->getUserGroupIds()).")");
                        $this->recIDs = prepareIds($this->recIDs);//redundant for snyk
                    }

                    $inAccessibleRecCnt = $passedRecIDCnt - count(@$this->recIDs);
                }
            }

            $this->result_data = array('passed'=> $passedRecIDCnt>0?$passedRecIDCnt:0,
                                       'noaccess'=> @$inAccessibleRecCnt ?$inAccessibleRecCnt :0);

            if (isEmptyArray(@$this->recIDs)){
                $this->result_data['processed'] = 0;
                return true;
            }

            if($rtyID>0){
                $this->rtyIDs = array($rtyID);
            }else {
                $this->rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in ("
                    .implode(",",$this->recIDs).")");

                $this->rtyIDs = prepareIds($this->rtyIDs);
            }

        }


        return true;
    }

    /**
     * Retrieves the base type of a given detail type ID.
     *
     * Queries the `defDetailTypes` table for the `dty_Type` (e.g., 'freetext', 'enum', 'resource').
     *
     * @access private
     * @param int $dty_ID The detail type ID.
     * @return string|null The `dty_Type` string if found, otherwise null.
     */
    private function getDetailType($dty_ID){
        return mysql__select_value($this->system->getMysqli(),
                 'Select dty_Type from defDetailTypes where dty_ID = '.intval($dty_ID));
    }


    /**
     * Removes `<script>` tags and their content from a given string.
     *
     * Also trims whitespace from the beginning and end of the string.
     *
     * @access private
     * @param string $value The input string.
     * @return string The string with script tags removed and trimmed.
     */
    private function _removeScriptTag($value){
        $value = trim($value);
        return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
    }


    /**
    * Converts existing records to child record for given rectype/detailtype
    * (adds reverse pointer field DT_PARENT_ENTITY) to child records.
    *
    * This method identifies parent-child relationships based on a specified pointer field (`dtyID`)
    * within a given parent record type (`rtyID`). For each such identified child record, it adds
    * a "Parent Entity" (DT_PARENT_ENTITY) detail pointing back to its parent.
    * It also updates the child record's title and sets the `rst_CreateChildIfRecPtr` flag
    * to 1 for the specified parent pointer field in `defRecStructure`.
    *
    * This operation requires admin privileges.
    * The constant `DT_PARENT_ENTITY` must be defined.
    *
    * Expected parameters in `$this->data`:
    * - 'rtyID': (int) The record type ID of the parent records.
    * - 'dtyID': (int) The detail type ID of the pointer field in the parent records that points to the child records.
    * - 'allow_multi_parent': (bool, optional) If true, allows a child record to have multiple Parent Entity pointers.
    *                           If false (default), an existing Parent Entity pointer might be updated if one already exists.
    *
    * @return array|false Returns an associative array summarizing the operation on success, including counts of:
    *                     - 'passed': Total potential parent-child links found.
    *                     - 'noaccess': Child records the current user couldn't access (should be 0 for admin).
    *                     - 'disambiguation': Child records linked from multiple parents when `allow_multi_parent` is false.
    *                     - 'processedParents': Array of parent record IDs that were processed.
    *                     - 'childInserted': Array of child record IDs where a new Parent Entity pointer was inserted.
    *                     - 'childUpdated': Array of child record IDs where an existing Parent Entity pointer was updated.
    *                     - 'childAlready': Array of child record IDs that already had the correct Parent Entity pointer.
    *                     - 'childMiltiplied': Array of child record IDs that received an additional Parent Entity pointer (if `allow_multi_parent` was true).
    *                     - 'titlesFailed': Array of child record IDs whose titles failed to update.
    *                     Returns `false` if there's a permission error, critical system error (like DT_PARENT_ENTITY not defined),
    *                     invalid parameters, or a database error during the process. Errors are added to the system object.
    */
    public function addRevercePointerForChild(){


        if (! $this->system->isAdmin() ) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }

        if(!$this->system->defineConstant('DT_PARENT_ENTITY')){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type 2-247 is not defined in this database');
            return false;
        }

        if(!$this->_validateDetailType()){
            return false;
        }

        $allow_multi_parent = ($this->data['allow_multi_parent']==true);

        $mysqli = $this->system->getMysqli();

        //1. find resource (child) records for given record type and detail
        $query = 'SELECT dtl_RecID as parent_id, d.dtl_Value as child_id, child.rec_OwnerUGrpID, child.rec_RecTypeID, child.rec_Title '
            .'FROM  recDetails d LEFT JOIN Records child on child.rec_ID=d.dtl_Value, Records parent '
            .'WHERE d.dtl_RecID=parent.rec_ID and parent.rec_RecTypeID='
            .$this->data['rtyID'].' and d.dtl_DetailTypeID='.$this->data['dtyID'];

        $res = $mysqli->query($query);
        if ($res){

                $passedValues = 0;       //total values found
                $inAccessibleRecCnt = 0; //no rights for child records
                $cntDisambiguation = 0;  //more than one parent record

                $childNotFound = array();
                $parentRecords = array();
                $childRecords = array();

                $toProcess = array();

                $groups = $this->system->getUserGroupIds();
                array_push($groups, 0);

                while ($row = $res->fetch_row()){

                    $passedValues++;

                    if(!($row[3]>0)){  //rec_RecTypeID
                        array_push($childNotFound, $row[0]);
                    }elseif(in_array($row[2], $groups)){  //rec_OwnerUGrpID
                        if($allow_multi_parent || !@$childRecords[$row[1]]){

                            $toProcess[] = $row;  //parent_id,child_id,0,child_rectype,child_title
                            if(!in_array($row[0],$parentRecords)){
                                $parentRecords[] = $row[0];
                            }
                            $childRecords[$row[1]] = 1;

                        }else{
                            $cntDisambiguation++;
                        }
                    }else{
                        $inAccessibleRecCnt++;
                    }
                }
                $res->close();
        }else{
            $this->system->addError(HEURIST_DB_ERROR, "Can't find child records ".$mysqli->error );
            return false;
        }

        $this->result_data = array('passed'=> $passedValues,
                                   'noaccess'=> $inAccessibleRecCnt,
                                   'disambiguation'=> $cntDisambiguation);

        $keep_autocommit = mysql__begin_transaction($mysqli);

        if (!empty($toProcess)){
        //3. add reverse pointer field in child record to parent record
        $processedParents = array();
        $childInserted = array();
        $childUpdated = array();
        $childAlready = array();
        $titlesFailed = array();
        $childMiltiplied = array();

        foreach ($toProcess as $row) {
            //parent_id,child_id,0,child_rectype,child_title

            //check if child record has parent already
            $parent_id = $row[0];
            $child_id = $row[1];
            $res = addReverseChildToParentPointer($mysqli, $child_id, $parent_id, 0, $allow_multi_parent);

            if($res<0){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return $this->system->addError(HEURIST_DB_ERROR,
                    'Unable to insert reverse pointer for child record ID:'.$child_id.' - ', $syserror);
            }elseif($res==0){
                 array_push($childAlready, $child_id);
            }else{

                if($res==2){
                    if($allow_multi_parent){
                        if(!in_array($child_id, $childMiltiplied)) {array_push($childMiltiplied, $child_id);}
                    }else{
                        array_push($childUpdated, $child_id);
                    }

                }else{
                    array_push($childInserted, $child_id);
                }
                if(!in_array($parent_id, $processedParents)){
                    array_push($processedParents, $parent_id);
                }

                //update record title for child record
                $child_rectype = $row[3];
                $child_title = $row[4];

                if(!recordUpdateTitle($this->system, $child_id, $child_rectype, $child_title)){ //on add child record
                    $titlesFailed[] = $child_id;
                }
            }



        } //foreach

        $this->result_data['processedParents'] = $processedParents;
        $this->result_data['childInserted'] = $childInserted;
        $this->result_data['childUpdated'] = $childUpdated;
        $this->result_data['childAlready'] = $childAlready;
        $this->result_data['childMiltiplied'] = $childMiltiplied;
        $this->result_data['titlesFailed'] = $titlesFailed;


        }
        //set rst_CreateChildIfRecPtr=1
        $query = 'UPDATE defRecStructure set rst_CreateChildIfRecPtr=1 WHERE rst_RecTypeID='
            .$this->data['rtyID'].' and rst_DetailTypeID='.$this->data['dtyID'];

        $res = $mysqli->query($query);
        if(!$res){
            $syserror = $mysqli->error;
            $mysqli->rollback();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
            return $this->system->addError(HEURIST_DB_ERROR,
                'Unable to set value in record sructure table', $syserror);
        }


        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $this->result_data;
    }

    /**
    * Adds a new detail value to a batch of records for a specified detail type.
    *
    * This method processes a list of record IDs (`$this->recIDs`), adding a new detail value
    * (`$this->data['val']`, `['geo']`, or `['ulfID']`) for the specified detail type (`$this->data['dtyID']`).
    *
    * Key operations:
    * - Validates that a value to add is provided.
    * - Decodes URL-encoded values if `details_encoded` is set.
    * - Validates general parameters and record counts/accessibility using `_validateParamsAndCounts`.
    * - Checks field limits (`rst_MaxValues`) for each record; skips addition if the limit would be exceeded.
    * - Prepares the detail value:
    *   - For geo fields, uses `prepareGeoValue`.
    *   - For date fields, uses `Temporal::getValueForRecDetails`.
    *   - For text fields, applies HTML purification (unless field is exempt) via `_initPutifier` and `_removeScriptTag`.
    * - Inserts the new detail into `recDetails`.
    * - Updates the `rec_Modified` timestamp for the affected record.
    * - Updates the record's title using `recordUpdateTitle`.
    * - Assigns system tags to records based on the outcome (processed, undefined field limit, over limit, SQL errors)
    *   if tagging is enabled (`$this->data['tag'] == 1`).
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs': (string|array) List of record IDs or 'ALL'.
    * - 'rtyID': (int|array, optional) Filter by record type(s).
    * - 'dtyID': (int) The detail type ID for which to add the value.
    * - 'dtyName': (string, optional) Name of the detail type (for tagging).
    * - 'val': (string, optional) The textual value to add.
    * - 'geo': (string, optional) The geographic WKT string to add.
    * - 'ulfID': (int, optional) The uploaded file ID to link.
    * - 'details_encoded': (int, optional) 1 or 2 if 'val' is URL-encoded.
    * - 'tag': (int, optional) 1 to enable system tagging of processed records.
    *
    * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'passed',
    *                     'noaccess', 'processed', 'undefined', 'limited', 'errors', and tag info).
    *                     Returns `false` if critical validation fails (e.g., missing value, invalid params).
    *                     Errors are added to the system object.
    */
    public function detailsAdd(){

        if( !@$this->data['val'] && @$this->data['rVal']){
            $this->data['val'] = $this->data['rVal'];
        }

        if (!(@$this->data['val']!=null || @$this->data['geo']!=null || @$this->data['ulfID']!=null)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New field value not defined");
            return false;
        }

        if(@$this->data['val']!=null){
            //attempt to pass server filters against malicious code
            if(@$this->data['details_encoded']==1 || @$this->data['details_encoded']==2){
                //$this->data['val'] = json_decode(str_replace( ' xxx_style=', ' style=',
                //        str_replace( '^^/', '../', urldecode($this->data['val']))));
                //}elseif(@$this->data['details_encoded']==2){
                $this->data['val'] = urldecode( $this->data['val'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray($this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']."");

        $mysqli = $this->system->getMysqli();

        //get array of max allowed values per record type
        $query = "SELECT rst_RecTypeID,rst_MaxValues FROM defRecStructure WHERE rst_DetailTypeID = $dtyID and rst_RecTypeID in ("
                        .implode(',', $this->rtyIDs).')';
        $rtyLimits = mysql__select_assoc2($mysqli, $query);

        $basetype = null;
        if(@$this->data['geo']==null){
            $basetype = $this->getDetailType($dtyID);
            if($basetype=='geo'){
                $this->data['geo'] = $this->data['val'];
            }
        }

        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~add field $dtyName $now";//name of tag assigned to modified records

        if(@$this->data['geo']!=null){

            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['geo']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
            $dtl['dtl_Value'] = $geoType;
            $dtl['dtl_Geo'] = $geoValue;
            //$dtl['dtl_Geo'] = array("ST_GeomFromText(\"" . $this->data['geo'] . "\")");
        }elseif($basetype=='date'){

            $useNewTemporalFormatInRecDetails = ($this->system->settings->get('sys_dbSubSubVersion')>=14);

            $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $this->data['val'], $useNewTemporalFormatInRecDetails );


        }elseif(@$this->data['val']!=null){ //sanitize new value

            $this->_initPutifier();
            if(!in_array($dtyID, $this->not_purify)){

                //remove html script tags
                $dtl['dtl_Value'] = $this->_removeScriptTag($this->data['val']);

                //$s = $this->purifier->purify( $this->data['val']);
                //$dtl['dtl_Value'] = htmlspecialchars_decode( $this->data['val'] );
            }else{
                $dtl['dtl_Value'] = $this->data['val'];
            }

        }

        if(@$this->data['ulfID']>0){
            $dtl['dtl_UploadedFileID'] = $this->data['ulfID'];
        }

        $undefinedFieldsRecIDs = array();//limit not defined
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//over limit - skip
        $sqlErrors = array();

        foreach ($this->recIDs as $recID) {
            $recID = intval($recID);//redundant for snyk
            //check field limit for this record
            $query = "select rec_RecTypeID, tmp.cnt from Records ".
            "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
            "from recDetails ".
            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
            "where rec_ID = $recID";

            $res = $mysqli->query($query);
            if(!$res){
                array_push($undefinedFieldsRecIDs, $recID);//cannot retrieve limit
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }

            $row = $res->fetch_row();

            $rectype_ID = $row[0];

            if (!array_key_exists($rectype_ID,$rtyLimits)) { //limit not defined
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }elseif (intval($rtyLimits[$rectype_ID])>0 && $row[1]>0 && ($rtyLimits[$rectype_ID] - $row[1]) < 1){
                array_push($limitedRecIDs, $recID);//over limit - skip
                continue;
            }

            //limit ok so insert field
            $dtl['dtl_RecID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = $ret;
                continue;
            }
            array_push($processedRecIDs, $recID);
            //update record edit date
            $rec_update['rec_ID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
            }else{
                //update record title
                if(!recordUpdateTitle($this->system, $recID, $rectype_ID, null)){ //on add details
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
            }
        }

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);



        return $this->result_data;
    }

    /**
    * Executes a sequence of batch actions (add, replace, delete) in a single transaction.
    *
    * The actions to be performed are defined in the `$this->data['actions']` array.
    * Each element in this array is an associative array specifying an action ('a') and its parameters.
    * Supported actions:
    *  - 'add': Calls `detailsAdd()`.
    *  - 'replace': Calls `detailsReplace()`.
    *  - 'addreplace': Calls `detailsReplace()`. If `detailsReplace` indicates the field was undefined (value not found to replace),
    *                  it then calls `detailsAdd()` to add the value as a new detail.
    *  - 'delete': Calls `detailsDelete(true)`.
    *
    * All database operations are wrapped in a single MySQL transaction. If any action fails,
    * the transaction is rolled back.
    * The results of each action (specifically the 'processed' count) are aggregated into `$this->result_data`.
    *
    * Expected structure for `$this->data['actions']`:
    * `[
    *   ['a' => 'action_name_1', 'param1' => 'value1', ...],
    *   ['a' => 'action_name_2', 'param1' => 'valueX', ...],
    *   ...
    * ]`
    * Each sub-array's parameters must match what's expected by the corresponding method
    * (`detailsAdd`, `detailsReplace`, `detailsDelete`).
    *
    * @return array|false The aggregated `$this->result_data` array if all actions succeed and the transaction is committed.
    *                     Returns `false` if any action returns `false` (indicating a critical error),
    *                     leading to a transaction rollback. System errors are set by the individual action methods.
    */
    public function multiAction(){

        $main_data = $this->data['actions'];

        $mysqli = $this->system->getMysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);

        foreach ($main_data as $action_data) {

            $this->setData($action_data);

            if(@$this->data['a'] == 'add'){

                $res = $this->detailsAdd();

            }elseif(@$this->data['a'] == 'replace'){ //returns

                $res = $this->detailsReplace();

            }elseif(@$this->data['a'] == 'addreplace'){

                $res = $this->detailsReplace();
                if(is_array($res) && @$res['passed']==1 && @$res['undefined']==1){
                    //detail not found - add new one
                    $res = $this->detailsAdd();
                }

            }elseif(@$this->data['a'] == 'delete'){

                $res = $this->detailsDelete(true);
            }

            if($res===false){
                break;
            }else{
                if(!@$this->result_data['processed']) {$this->result_data['processed'] = 0;}
                $this->result_data['processed'] = $this->result_data['processed']
                    +(@$res['processed']>0?$res['processed']:0);
            }
        }

        if($res===false){
            $mysqli->rollback();
            $res_data = $res;
        }else{
            $mysqli->commit();
            $res_data = $this->result_data;
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res_data;

    }

    /**
    * Replaces existing detail values in a batch of records.
    *
    * This method allows for replacing detail values based on various criteria:
    * - Replace all occurrences of a specific detail type (`dtyID`) if search value (`sVal`) is not provided.
    * - Replace specific values (`sVal`) if provided.
    * - Partial replacement within a string (`subs` or `substr` flags).
    * - Whole value exact match (`wholeval` flag).
    * - Option to insert the new value if the search value is not found (`insert_new_values` flag).
    *
    * Key operations:
    * - Validates parameters: presence of replacement value (`rVal`), general params via `_validateParamsAndCounts`.
    * - Handles URL decoding of `rVal` if `encoded` flag is set.
    * - Checks for max length of `rVal` and splits it if it's for `DT_EXTENDED_DESCRIPTION` and `needSplit` is true.
    * - Constructs a search clause based on `sVal` and field type (`basetype`).
    * - Iterates through records, finds matching details, and updates them.
    *   - For text fields, performs string replacement or sets new value. HTML purification is applied.
    *   - For geo/date fields, prepares value using `prepareGeoValue`/`Temporal::getValueForRecDetails`.
    * - Updates `rec_Modified` and record title for affected records.
    * - Assigns system tags if enabled.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID', 'dtyID', 'dtyName', 'tag': Common batch parameters.
    * - 'rVal': (string, required unless 'replace_empty' is 1) The replacement value.
    * - 'sVal': (string, optional) The value to search for. If null, all values of `dtyID` are targeted.
    * - 'encoded': (int, optional) 1 or 2 if `rVal` is URL-encoded.
    * - 'replace_empty': (int, optional) If 1, allows `rVal` to be null/empty.
    * - 'needSplit': (bool, optional) If true and `dtyID` is `DT_EXTENDED_DESCRIPTION`, splits long `rVal`.
    * - 'dt_extended_description': (int, optional) Overrides `DT_EXTENDED_DESCRIPTION` constant.
    * - 'subs'/'substr': (int, optional) If 1, enables partial string replacement.
    * - 'wholeval': (int, optional) If 1, search for whole value match.
    * - 'insert_new_values': (int, optional) If 1 and `sVal` is not found (or not provided), insert `rVal` as a new detail.
    * - 'debug': (bool, optional) If true, skips actual database updates.
    *
    * @return array|false The result array (`$this->result_data`) summarizing the operation.
    *                     Returns `false` on critical validation failure or DB error during main query.
    */
    public function detailsReplace()
    {
        if (@$this->data['rVal']==null && @$this->data['replace_empty'] != 1){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New value not defined");
            return false;
        }

        $useNewTemporalFormatInRecDetails = ($this->system->settings->get('sys_dbSubSubVersion')>=14);


        if(@$this->data['rVal']!=null || @$this->data['encoded']==2){
            if(@$this->data['encoded']==1){
                $this->data['rVal'] = urldecode( $this->data['rVal'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $insert_new_value = false;

        $mysqli = $this->system->getMysqli();

        $rval = $mysqli->real_escape_string($this->data['rVal']);

        $this->_initPutifier();

        //split value if exceeds 64K
        $splitValues = array();

        if(@$this->data['dt_extended_description']>0){
            $this->dt_extended_description = $this->data['dt_extended_description'];
        }elseif(!($this->dt_extended_description>0)){
            $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
            $this->dt_extended_description = DT_EXTENDED_DESCRIPTION;
        }

        if(@$this->data['needSplit'] && $dtyID==$this->dt_extended_description){
                //split replacement value
               $lim = checkMaxLength2($rval);
               //TEST $lim = 100;
               if($lim>0){
                    $dtl_Value = $this->data['rVal'];

                    $dtl_Value = $this->_removeScriptTag($dtl_Value);

                    //$dtl_Value =  $this->purifier->purify($dtl_Value);
                    //$dtl_Value = htmlspecialchars_decode( $dtl_Value );

                    $iStart = 0;
                    while($iStart<mb_strlen($dtl_Value)){

                        array_push($splitValues, mb_substr($dtl_Value, $iStart, $lim));
                        $iStart = $iStart + $lim;
                    }
               }
        }else{
            $err_msg = checkMaxLength($dtyName, $rval);
            if($err_msg!=null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $err_msg);
                return false;
            }
        }

        $basetype = $this->getDetailType($dtyID);

        $partialReplace = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeReplace = @$this->data['wholeval'] == 1;

        if(@$this->data['sVal']==null){    //value to be replaced
            //all except file type
            //$searchClause = '1=1';
            $replace_all_occurences = true;   //search value not defined replace all
            $insert_new_value = @$this->data['insert_new_values'] == 1;

            //??? why we need it if $dtyID is defined
            $types = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes where dty_Type = "file"');// OR dty_Type = "geo"
            $types = prepareIds($types);//redundant for snyk
            $searchClause = 'dtl_DetailTypeID NOT IN ('.implode(',',$types).')';

        }else{

            $searchClause = null;
            $is_like = false;

            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialReplace || $wholeReplace){
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);
                    $partialReplace = false;
                    break;
                case "date":

                    $dtl_Value = Temporal::getValueForRecDetails( @$this->data['sVal'], $useNewTemporalFormatInRecDetails );

                    $searchClause = $mysqli->real_escape_string($dtl_Value);

                    $partialReplace = false;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by value-replace service");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }


            $replace_all_occurences = false;
        }

        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~replace field $dtyName $now";

        if($basetype=='geo'){
            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['rVal']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
        }

        $is_multiline = !empty($splitValues);

        foreach ($this->recIDs as $recID) {

            $query = 'SELECT dtl_ID, dtl_RecID '
                    .($is_multiline?'':', dtl_Value')
                    .'  FROM recDetails ';

            if($recID=='all' && is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){
                if(is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){

                    $query = $query.', Records '
                            .'WHERE rec_ID=dtl_RecID AND  rec_RecTypeID '
            .(count($this->rtyIDs)>1?('in ('.implode(',',$this->rtyIDs).')'):('='.$this->rtyIDs[0]))
                            ." AND dtl_DetailTypeID = $dtyID and $searchClause";
                }
            }else{
                $query = $query."WHERE  dtl_DetailTypeID = $dtyID and $searchClause";
                //get matching detail value for record if there is one
                if($recID!='all'){
                    $query = $query.' AND  dtl_RecID = '.intval($recID);
                }
            }
            $query = $query.' ORDER BY dtl_RecID';

            //$valuesToBeReplaced = mysql__select_assoc2($mysqli, $query);

            $res = $mysqli->query($query);

            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            //}elseif(isEmptyArray($valuesToBeReplaced)){  //not found
            //    array_push($undefinedFieldsRecIDs, $recID);
            //    continue;
            }

            $recDetailWasUpdated = false;
            $valuesToBeDeleted = array();
            $keep_recID = $recID;
            $recID = 0;
            $get_next_row = true;

            //update the details
            while (true) {

                if($get_next_row) {$row = $res->fetch_row();}
                $get_next_row = true;
                $inserting_value = $insert_new_value && $res->num_rows == 0;

                if(!$row || ($recID>0 && $row[1]!=$recID) ){

                    //next record - update changed record and
                    if($recID>0){

                        if ($recDetailWasUpdated) {
                            //only put in processed if a detail was processed,
                            // obscure case when record has multiple details we record in error array also
                            array_push($processedRecIDs, $recID);

                            //update record edit date
                            $rec_update['rec_ID'] = $recID;
                            $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                            if (!is_numeric($ret)) {
                                $sqlErrors[$recID] = 'Cannot update modify data. '.$ret;
                            }
                        }else{
                            array_push($undefinedFieldsRecIDs, $recID);
                        }
                        if(!empty($valuesToBeDeleted) && !@$this->data['debug']){
                            //remove the rest for replace all occurences
                            $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
                            if ($mysqli->query($sql) === false) {
                                $sqlErrors[$recID] = $mysqli->error;
                            }
                        }
                    }

                    if(!$row && ($res->num_rows > 0 || !$insert_new_value)){ //end of loop

                        if($recID==0){
                            array_push($undefinedFieldsRecIDs, $keep_recID);
                        }
                        break;
                    }

                    $recDetailWasUpdated = false;
                    $valuesToBeDeleted = array();
                }

                $dtlID = $inserting_value ? -1 : intval($row[0]);
                $recID = $inserting_value ? $keep_recID : intval($row[1]);

                if($is_multiline){ //replace with several values (long text)

                    foreach($splitValues as $val){
                        $dtl['dtl_ID'] = -1;
                        $dtl['dtl_RecID'] = $recID;
                        $dtl['dtl_DetailTypeID'] = intval($dtyID);
                        $dtl['dtl_Value'] = $val;
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                    }
                    $recDetailWasUpdated = true;

                }else{
                    $dtlVal = $inserting_value ? null : $row[2];

                    if($this->data['rVal']=='replaceAbsPathinCMS'){

                        $newVal = replaceAbsPathinCMS($recID, $dtlVal);

                    }elseif (!$replace_all_occurences && $partialReplace) {// need to replace sVal with rVal
                        $newVal = preg_replace("/".preg_quote($this->data['sVal'], "/")."/i", $this->data['rVal'], $dtlVal);
                    }else{
                        $newVal = $this->data['rVal'];
                    }

                    $dtl['dtl_ID'] = $dtlID;  //detail type id

                    if(($basetype=='freetext' || $basetype=='blocktext')
                        && !in_array($dtyID, $this->not_purify))
                    {
                            //remove html script tags
                            $dtl['dtl_Value'] = $this->_removeScriptTag($newVal);

                            //$s = $this->purifier->purify( $newVal );
                            //$dtl['dtl_Value'] = htmlspecialchars_decode( $dtl['dtl_Value'] );
                    }elseif($basetype=='geo'){

                        $dtl['dtl_Value'] = $geoType;
                        $dtl['dtl_Geo'] = $geoValue;

                    }elseif($basetype=='date'){

                        $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $newVal, $useNewTemporalFormatInRecDetails );

                    }else{
                        $dtl['dtl_Value'] = $newVal;
                    }

                    if(!@$this->data['debug']){

                        if($insert_new_value){
                            $dtl['dtl_RecID'] = $recID;
                            $dtl['dtl_DetailTypeID'] = $dtyID;
                        }else{
                            unset($dtl['dtl_RecID']);
                            unset($dtl['dtl_DetailTypeID']);
                        }

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;

                            if($inserting_value){
                                break;
                            }else{
                                continue;
                            }
                        }elseif($inserting_value){
                            array_push($processedRecIDs, $recID);
                        }
                    }

                    $recDetailWasUpdated = true;

                }

                if($replace_all_occurences || $is_multiline){

                    if($is_multiline && $dtlID > 0){
                        array_push($valuesToBeDeleted, intval($dtlID));
                    }

                    while ($row = $res->fetch_row()) { //gather all old detail IDs
                        if($row[1]!=$recID){
                            break;
                        }
                        array_push($valuesToBeDeleted, intval($row[0]));
                    }
                    $get_next_row = false;
                }

                if($inserting_value){ break; }

            }//while

        }//while records

        if($res) {$res->close();}

        //update record title
        foreach ($processedRecIDs as $recID){
                if(!recordUpdateTitle($this->system, $recID, null, null)){ //on replace details
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
        }

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }

    /**
    * Deletes detail values from a batch of records based on specified criteria.
    *
    * Key operations:
    * - Validates parameters and record accessibility using `_validateParamsAndCounts`.
    * - Constructs a search clause based on `$this->data['sVal']` (search value) and the field's base type.
    *   - If `sVal` is not provided, all details of the specified `dtyID` are targeted for deletion.
    *   - For text fields, allows partial (`subs`/`substr`) or whole value (`wholeval`) matching.
    * - Checks if the detail type is 'required' for any of the record types being processed. If so, and if
    *   the deletion would remove all instances of this required field for a record, that record is skipped
    *   (unless `$unconditionally` is true).
    * - Deletes matching `recDetails` entries.
    * - If `partialRemove` is true for text fields, it replaces the `sVal` substring with an empty string instead of deleting the whole detail.
    *   If this results in an empty string, the detail is deleted.
    * - Updates `rec_Modified` and record title for affected records.
    * - Assigns system tags if enabled.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID', 'dtyID', 'dtyName', 'tag': Common batch parameters.
    * - 'sVal': (string, optional) The value to search for deletion. If empty or not set, all details of `dtyID` are targeted.
    * - 'subs'/'substr': (int, optional) If 1, enables partial string match for deletion (effectively a replace-with-empty).
    * - 'wholeval': (int, optional) If 1, search for whole value exact match for deletion.
    *
    * @param bool $unconditionally If true, bypasses checks for 'required' fields and deletes them even if they are mandatory.
    *                              Also, if `sVal` is not provided, this being true implies deleting all instances of the field.
    *                              Defaults to false.
    * @return array|false The result array (`$this->result_data`) summarizing the operation.
    *                     Returns `false` on critical validation failure or if the field type is unsupported for deletion (e.g., 'relmarker').
    */
    public function detailsDelete($unconditionally=false){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $isDeleteAll = (!array_key_exists("sVal",$this->data) || $this->data['sVal']=='');//without conditions
        if($isDeleteAll){
            $unconditionally = true;
        }


        $isDeleteInAllRecords = $this->recIDs[0]=='all' && !isEmptyArray($this->rtyIDs);

        $partialRemove = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeRemove = @$this->data['wholeval'] == 1;

        $mysqli = $this->system->getMysqli();

        if($isDeleteAll){
            $searchClause = '1=1';
        }else{

            $searchClause=null;
            $is_like=false;

            $basetype = $this->getDetailType($dtyID);
            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialRemove || $wholeRemove){
                        $unconditionally = true;
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                case "date":
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "geo":
                    $isDeleteAll = true;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by batch deletion");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by deletion service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }else{
                $searchClause = "(1=1)";
            }
        }


        //get array of required detail types per record type
        $rtyRequired = mysql__select_list($mysqli, "defRecStructure","rst_RecTypeID",
        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$this->rtyIDs).") and rst_RequirementType='required'");


        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//it is not possible to delete requried fields
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        if($partialRemove){
            $baseTag = "~replace field $dtyName $now";
        }else{
            $baseTag = "~delete field $dtyName $now";
        }



        //
        if($isDeleteInAllRecords)
        {

            //special case remove field for all records of specified record type
            if($isDeleteAll){  //for admin only

                $query = 'DELETE d FROM recDetails d, Records r WHERE r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID '
                        .((count($this->rtyIDs)==1)
                                ?('='.$this->rtyIDs[0])
                                :('in ('.implode(',', $this->rtyIDs).')'))
                        .' AND d.dtl_DetailTypeID='.$dtyID;
                $mysqli->query($query);

                if($mysqli->error!=null || $mysqli->error!=''){
                    $this->result_data['processed'] = 0;
                    $this->result_data['error'] = $mysqli->error;
                    return $this->result_data;
                }else{
                    $this->result_data['processed'] = $mysqli->affected_rows;
                    return $this->result_data;
                }
            }

            //find all records of particular record type
            $query = 'SELECT rec_ID FROM Records WHERE rec_RecTypeID '
                    .((count($this->rtyIDs)==1)
                            ?('='.$this->rtyIDs[0])
                            :('in ('.implode(',', $this->rtyIDs).')'));

            $this->recIDs = mysql__select_list2($mysqli, $query);
            if($mysqli->error!=null || $mysqli->error!=''){
                $this->result_data['processed'] = 0;
                $this->result_data['error'] = $mysqli->error;
                return $this->result_data;
            }
        }

        foreach ($this->recIDs as $recID) {

            $recID = intval($recID);

            //get matching detail value for record if there is one
            $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
            $valuesToBeDeleted = mysql__select_assoc2($mysqli, $query);

//$valuesToBeDeleted = mysql__select_list($mysqli, "recDetails", "dtl_ID", "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");

            if($valuesToBeDeleted==null && $mysqli->error){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }elseif(isEmptyArray($valuesToBeDeleted)){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }


            if(!$unconditionally){
                //validate if details can be deleted for required fields
                if(count($this->rtyIDs)>1){
                    //get rectype for current record
                    $rectype_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
                }else{
                    $rectype_ID = $this->rtyIDs[0];
                }
                if(array_search($rectype_ID, $rtyRequired)!==false){ //this is required field
                    if(!$isDeleteAll){
                        //find total count
                        $total_cnt = mysql__select_value($mysqli, "SELECT count(*) FROM recDetails ".
                            " WHERE dtl_RecID = $recID AND dtl_DetailTypeID = $dtyID");

                    }
                    if($isDeleteAll || ($total_cnt == count($valuesToBeDeleted))){
                        array_push($limitedRecIDs, $recID);
                        continue;
                    }
                }
            }

            if($partialRemove){
                //this is not real delete - this is replacement of value part with empty string
                $now = date(DATE_8601);
                $dtl = ['dtl_Modified' => $now];

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtlID => $dtlVal) {

                    $newVal = preg_replace($sRegEx,'',$dtlVal);

                    if(trim($newVal)==''){
                        $sql = 'delete from recDetails where dtl_ID = '.$dtlID;
                        if ($mysqli->query($sql) === true) {
                            $sqlErrors[$recID] = $mysqli->error;
                        }

                    }else{
                        $dtl['dtl_ID'] = $dtlID;
                        $dtl['dtl_Value'] = $newVal;

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;
                        }
                    }
                }//for

                $sql = true;

            }elseif($wholeRemove){

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtl_ID => $dtl_Value) {

                    if(preg_match($sRegEx, $dtl_Value)){
                        $sql = "DELETE FROM recDetails WHERE dtl_ID = $dtl_ID";
                        if($mysqli->query($sql) !== true){
                            $sqlErrors[$recID] = $mysqli->error;
                        }
                    }
                }

                $sql = true;

            }else{
                //delete the details
                $sql = 'delete from recDetails where dtl_ID in ('.implode(',',array_keys($valuesToBeDeleted)).')';
            }

            if ($sql===true || $mysqli->query($sql) === true) {
               array_push($processedRecIDs, $recID);
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
               }else{
                    if(!recordUpdateTitle($this->system, $recID, null, null)){ //on remove details
                        $sqlErrors[$recID] = ERR_REC_TITLE;
                    }
               }

            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for records


        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }


    /**
    * assign child detail type (2-272) that refers to parent record
    * or change given detail type to 2-272
    */
    public function setRecordAsChild(){
        // parameters
        // 1. required. detail type (pointer) from parent record - to detect what record types will be affected
        // 2. optional. detail type in child record that already has backward reference to parent record -
        //             if hot defined ALL records of given record type will be affected
        //             this detail will be replaced to


    }


    /**
    * Changes the record type (`rec_RecTypeID`) for a batch of records.
    *
    * Key operations:
    * - Validates parameters and record accessibility using `_validateParamsAndCounts`.
    *   (A dummy `dtyID` is set to pass this validation as it's not strictly needed here).
    * - Iterates through the accessible records and updates their `rec_RecTypeID` and `rec_Modified` timestamp.
    * - Updates the title for each modified record using `TitleMask::fill` (which uses the new record type's mask).
    * - Assigns system tags if enabled.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID' (original type for filtering, optional), 'tag': Common batch parameters.
    * - 'rtyID_new': (int, required) The ID of the new record type to assign.
    * - 'rtyName': (string, optional) The name of the new record type (for tagging).
    *
    * @return array|false The result array (`$this->result_data`) summarizing the operation.
    *                     Returns `false` on critical validation failure.
    */
    public function changeRecordTypeInBatch(){

        $this->data['dtyID'] = '1';//dumb value to pass validation

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $rtyID_new = $this->data['rtyID_new'];
        $rtyName = (@$this->data['rtyName'] ? "'".$this->data['rtyName']."'" : "id:".$this->data['rtyID_new']);

        $mysqli = $this->system->getMysqli();

        $processedRecIDs = array();//success
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                            'rec_Modified'  => $now,
                            'rec_RecTypeID'  => $rtyID_new);

        $baseTag = "~changed rectype $rtyName $now";

        foreach ($this->recIDs as $recID) {
               //update record edit date
               $rec_update['rec_ID'] = $recID;

               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
               }else{
                   array_push($processedRecIDs, $recID);
                   //update title
                   $new_title = TitleMask::fill($recID); //on change rectype
                   $rec_update2 = array('rec_ID'  => $recID, 'rec_Title'  => $new_title);
                   mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update2);
               }
        }//for recors



        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }


    /**
    * Extracts text content from PDF files associated with records in a batch
    * and stores the extracted text into a specified detail field (defaulting to `DT_EXTRACTED_TEXT`).
    *
    * Key operations:
    * - Validates parameters and record accessibility. Defaults `dtyID` to `DT_EXTRACTED_TEXT` if not provided.
    * - For each record:
    *   - Checks if the target text field already has a value; if so, skips (adds to `skippedRecIDs`).
    *   - Searches for PDF files linked to the record via any file-type field using `recordSearchByID`.
    *   - For each PDF found:
    *     - Parses the PDF using `\Smalot\PdfParser\Parser`.
    *     - Extracts text from pages (up to a limit of 60000 chars or 10 pages).
    *     - Handles UTF-8 encoding issues.
    *     - If text is extracted, it's split into chunks of max 20000 chars and new details are created
    *       for the target `dtyID` with this text.
    *     - Updates `rec_Modified` for the record.
    * - Handles progress tracking if `session_id` is set.
    * - Assigns system tags if enabled and reports various outcomes (processed, skipped, errors, parse exceptions).
    *
    * Note: Requires the `smalot/pdfparser` library.
    * If `DEBUG_RUN` constant is true, actual PDF parsing is skipped.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID' (optional), 'tag': Common batch parameters.
    * - 'dtyID': (int, optional) The detail type ID where extracted text should be stored.
    *            Defaults to `DT_EXTRACTED_TEXT` (constant `2-652`).
    *
    * @return array|false The result array (`$this->result_data`) summarizing the operation.
    *                     Returns `false` on critical validation failure or if `DT_EXTRACTED_TEXT` is not defined and no `dtyID` is given.
    */
    public function extractPDF(){

        //default value to pass validation
        if(!@$this->data['dtyID']){
            if(!defined('DT_EXTRACTED_TEXT')){
                $this->system->addError(HEURIST_NOT_FOUND, 'Field "Extracted text" (2-652) not found');
                return false;
            }
            $this->data['dtyID'] = DT_EXTRACTED_TEXT;
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        $tot_count = count($this->recIDs);

        $execution_counter = 0;

        $processedRecIDs = array();//success
        $sqlErrors = array();
        $skippedRecIDs = array();//values already defined

        $skippedNoPDF   = array();//no assosiated records
        $skippedEmpty   = array();//empty
        $skippedParseEx = array();//parse exception



        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $this->data['dtyID'],
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~extract pdf $now";

        $parser = new \Smalot\PdfParser\Parser();

        foreach ($this->recIDs as $recID) {

            $sql = 'select count(dtl_ID) from recDetails where dtl_RecID='.$recID.' AND dtl_DetailTypeID = '.$this->data['dtyID'];
            $isExistsAlready = mysql__select_value($mysqli, $sql)>0;

            if($isExistsAlready){
               $skippedRecIDs[] = $recID;
               continue;
            }

            $details = array();
            $hasPDFs = false;

            $record = recordSearchByID($this->system, $recID, array('file'));
            foreach ($record['details'] as $dtl_ID => $detailValue){
    // 2. find assosiated pdf files
                if(is_array($detailValue)){
                    foreach ($detailValue as $id => $fileValue){
                    if($fileValue['file']['fxm_MimeType']=='application/pdf'){

                        $hasPDFs = true;

                        $file = $fileValue['file']['fullPath'];
                        $file = resolveFilePath($file);
                        if(file_exists($file)){
        // 3. Parse pdf file
                            try{

                                if(!DEBUG_RUN){
                                    $pdf    = $parser->parseFile($file);

                                        // Retrieve all pages from the pdf file.
                                        $pages  = $pdf->getPages();
                                        $page_cnt = 0;
                                        $text = '';
                                        // Loop over each page to extract text.
                                        foreach ($pages as $page) {

                                            $pagetext = $page->getText();

                                            if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){

                                                $pagetext = iconv("UTF-8","UTF-8//IGNORE", $pagetext);// to remove

                                                //$pagetext = Encoding::fixUTF8($pagetext);
                                                if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){
                                                    $pagetext = 'Page '.$page_cnt.' cannot be converted to UTF-8';
                                                }
                                            }

                                            $text = $text . $pagetext;
                                            if(strlen($text)>60000 || $page_cnt>10){
                                                break;
                                            }
                                            $page_cnt++;


                                        }//foreach

                                }else{
                                    //debug without real parsing
                                    sleep(1);
                                    $text = 'test';
                                    $skippedParseEx[$recID] = $file.' Debug parse exception';
                                }

                                if($text==null || mb_strlen(trim($text))==0){
                                    $skippedEmpty[$recID] = $file;
                                }else{
                                    $orig_len = mb_strlen($text);
                                    $maxlen = 20000;
                                    if($orig_len>$maxlen){ //split by 20k

                                            $k=0;
                                            while (strlen($text)>$maxlen && $k<3){
                                                $details[] = mb_substr($text,0,$maxlen);
                                                $text = mb_substr($text,$maxlen);
                                                $k++;
                                            }
                                            if($k>2){
                                                $len = count($details)-1;
                                                $details[$len] =
                                                    $details[$len]
                                                    .' <more text is available. Remaining text has not been extracted from file>';
                                            }
                                   }else{
                                        $details[] = $text;
                                   }
                                }

                            } catch (\Exception $ex) {
                                //throw new ParseException($ex);
                                $skippedParseEx[$recID] = $file.' '.print_r($ex, true);
                            }
                        }else{
                            $skippedNoPDF[$recID] = 'PDF file not found';
                        }
                    }
                }
                }
            }//details

            if(!$hasPDFs){
                $skippedNoPDF[] = $recID;
            }elseif(!empty($details)){

                /*
                // 4. remove old 2-652 "Extracted text"
                $sql = 'delete from recDetails where dtl_RecID='.$recID.' AND dtl_ID = '.$this->data['dtyID'];
                if ($mysqli->query($sql) !== true) {
                    $sqlErrors[$recID] = 'Cannot remove dt#'.$this->data['dtyID'].' for record # '.$recID.'  '.$mysqli->error;
                }else{}
                */
    // 5. Add new values to 2-652 - one entry per file
                if(!DEBUG_RUN){
                    $dtl['dtl_RecID'] = $recID;
                    foreach($details as $text){
                        $dtl['dtl_Value'] = $text;
                        if(mb_detect_encoding($dtl['dtl_Value'], 'UTF-8', true)===false){
                            $sqlErrors[$recID] = 'Extracted text has not valid utf8 encoding';
                            break;
                            /*
                            $query = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('
                            .$dtl['dtl_RecID'].', '.$dtl['dtl_DetailTypeID'].', '
                            .'CONVERT( CAST(? AS BINARY) USING utf8mb4))';

                            $ret = mysql__exec_param_query($mysqli, $query, array($dtl['dtl_Value']));
                            */
                        }else{
                            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                            if (!is_numeric($ret)) {
                                    $sqlErrors[$recID] = $ret;
                                    break;
                            }
                        }
                    }//foreach
                    if(@$sqlErrors[$recID]) {continue;}

                    //update record edit date
                    $rec_update['rec_ID'] = $recID;
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = 'Cannot update record "Modify date". '.$ret;
                    }
                }
                $processedRecIDs[] = $recID;
            }


            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    break;
                }
            }

        }//for records

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skippedNoPDF, null); //no pdf assigned
        $this->_assignTagsAndReport('limited',   $skippedRecIDs, null); //value already defined
        $this->_assignTagsAndReport('parseexception', $skippedParseEx, null);
        $this->_assignTagsAndReport('parseempty', $skippedEmpty, null);
        $this->_assignTagsAndReport('errors',  $sqlErrors, null);

        return $this->result_data;
    }

    /**
    * Converts remote file URLs (stored as `_remote` type in `recUploadedFiles`) within a specified
    * file-type detail field (`dtyID`) into locally stored files for a batch of records.
    *
    * Key operations:
    * - Validates parameters and record accessibility.
    * - Iterates through records and the specified `dtyID`:
    *   - Finds `recDetails` entries that link to `recUploadedFiles` where `ulf_OrigFileName` is "_remote".
    *   - Optionally filters by a substring in `ulf_ExternalFileReference` (`$this->data['url_substring']`).
    *   - For each unique remote URL (`ulf_ExternalFileReference`):
    *     - Downloads the file and registers it as a new local entry in `recUploadedFiles` using `DbRecUploadedFiles::downloadAndRegisterdURL()`.
    *       This method can check for existing files by name/checksum to avoid duplicates, based on the `$match_only` parameter.
    *     - If successful, the new local `ulf_ID` is obtained.
    *   - Updates all `recDetails` entries that pointed to the old remote `ulf_ID` to now point to the new local `ulf_ID`
    *     using `_updateUploadedFileIDs()`. This also updates `dtl_Modified`.
    * - If `$this->data['delete_file']` is 1, and after updating references, if the original remote `ulf_ID`
    *   is no longer referenced by any details, or if all its references were updated, the original `recUploadedFiles`
    *   entry for the remote URL is deleted.
    * - Assigns system tags if enabled and reports outcomes.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
    * - 'url_substring': (string, optional) Only process URLs containing this substring.
    * - 'match_only': (int, optional) Matching mode for `downloadAndRegisterdURL`. 1 for name only, 2 for name and checksum.
    * - 'delete_file': (int, optional) If 1, delete original `_remote` `recUploadedFiles` entry if no longer used or if all references updated.
    *
    * @return array|false The result array (`$this->result_data`) summarizing the operation.
    *                     Returns `false` on critical validation failure.
    */
    public function changeUrlToFileInBatch(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        $date_mode = date(DATE_8601);

        $tot_count = count($this->recIDs);

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace url to file $dtyName $date_mode";

        $processedRecIDs = array();
        $sqlErrors = array();
        $downloadError = array();

        //1. find external urls for field values
        //2. ulf_ExternalFileReference - extract filename and decode it
        //3. match_only!=1 Download of the remote file and check if the file already exists with the same name and checksum in the database and will not create a duplicate.
        //4. match_only==1 Check the file name only (avoids having to download the remote file if the name exists)
        //5. If download - register new file
        //6. Replace ulf_ID in dtl_UploadedFileID

        $file_entity = new DbRecUploadedFiles($this->system);

        //1. find external urls for field values
        $query = 'SELECT dtl_ID, ulf_ID, ulf_ExternalFileReference, dtl_RecID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND ulf_OrigFileName="_remote" AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        if($this->data['url_substring']){
            $query = $query.' AND ulf_ExternalFileReference LIKE "%'.$mysqli->real_escape_string($this->data['url_substring']).'%"';
        }

        $query = $query.' ORDER BY ulf_ID';

        $res = $mysqli->query($query);
        if ($res){
            $ulf_ID = null;
            $dtl_IDs = array();
            $rec_IDs = array();
            $ulf_ID_new = null;

            while ($row = $res->fetch_row()){
                if($ulf_ID!=$row[1]){

                    if($ulf_ID_new>0){
                        if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                            $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                        }else{
                            $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                        }
                    }

                    $ulf_ID = $row[1];
                    $dtl_IDs = array();
                    $rec_IDs = array();
                    $ulf_ID_new = null;

                    //find local ulf_ID

                    //2. ulf_ExternalFileReference
                    $surl = $row[2];

                    //5. If download - register new file
                    $file_entity->setRecords(null);
                    //$ulf_ID_new = false;
                    $ulf_ID_new = $file_entity->downloadAndRegisterdURL($surl, null, (@$this->data['match_only']==1)?1:2);//it returns ulf_ID
                    if(!$ulf_ID_new){
                        //can't download
                        $downloadError[] = $row[3];//rec_ID
                    }

                }

                $dtl_IDs[] = intval($row[0]);
                $rec_IDs[] = intval($row[3]);


            }//while

            if($ulf_ID_new>0){
                if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                    $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                }else{
                    $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                }
            }
        }

        //$this->result_data['processed'] = $tot_count;

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sqlErrors, $baseTag);
        $this->result_data['fails'] = count($downloadError);
        $this->result_data['fails_list'] = $downloadError;

        return $this->result_data;
    }

    /**
     * Updates the `dtl_UploadedFileID` and `dtl_Modified` timestamp for a given list of detail IDs (`dtl_ID`).
     *
     * This is a helper function typically used after a file operation (e.g., downloading an external URL
     * to create a local file, or uploading a local file to a repository and then linking back to the URL).
     * It changes which uploaded file (`recUploadedFiles` entry) a set of `recDetails` entries point to.
     *
     * @access private
     * @param int $ulf_ID_new The new `ulf_ID` (ID from `recUploadedFiles`) to assign to the details. Must be > 0.
     * @param array $dtl_IDs An array of `dtl_ID`s (primary keys from `recDetails`) to update. Must not be empty.
     * @param string $date_mode The timestamp string (e.g., from `date(DATE_8601)`) to set for `dtl_Modified`.
     * @return bool True if the update query was successful (or if no update was needed due to invalid params),
     *              false if a database error occurred during the update.
     */
    private function _updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode){

        if($ulf_ID_new>0 && !empty($dtl_IDs)){
            $mysqli = $this->system->getMysqli();
            //6. Replace ulf_ID in dtl_UploadedFileID
            $query2 = 'UPDATE recDetails SET dtl_Modified="'.$date_mode
                .'", dtl_UploadedFileID='.intval($ulf_ID_new).' WHERE dtl_ID in ('.implode(',',$dtl_IDs).')';
            $res2 = $mysqli->query($query2);

            if(!$res2){
                //$this->system->addError(HEURIST_DB_ERROR,'Cannot assign IDs for registered files', $mysqli->error );
                return false;
            }
            //$tag_count = $mysqli->affected_rows;
        }
        return true;

    }


    /**
    * Deletes thumbnail image files for all files associated with the selected records.
    *
    * This method identifies all uploaded files (`recUploadedFiles`) linked to the
    * specified batch of records via `recDetails`. For each such file, it constructs
    * the path to its thumbnail (e.g., `HEURIST_THUMB_DIR.'ulf_'.$obfuscatedFileID.'.png'`)
    * and deletes the thumbnail file if it exists.
    *
    * Note: This action is identified by `$this->data['a'] == 'reset_thumbs'` within `_validateParamsAndCounts`
    * to bypass the usual detail type validation, as it operates on all file fields.
    *
    * Expected parameters in `$this->data`:
    * - 'recIDs', 'rtyID' (optional): Common batch parameters to select records.
    *
    * @return array|false The result array (`$this->result_data`) with `['processed']` set to the count
    *                     of successfully deleted thumbnail files.
    *                     Returns `false` on critical validation failure.
    */
    public function resetThumbnails(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        //1. find external urls for field values
        $query = 'SELECT ulf_ObfuscatedFileID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID '
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        $cnt = 0;
        $res = $mysqli->query($query);
        if ($res){

            while ($row = $res->fetch_row()){
                $obfuscation_id = preg_replace('/[^a-z0-9]/', "", $row[0]);//for snyk
                $thumbnail_file = HEURIST_THUMB_DIR.'ulf_'.$obfuscation_id.'.png';//'ulf_ObfuscatedFileID'
                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                    $cnt++;
                }
            }
        }

        $this->result_data['processed'] = $cnt;
        return $this->result_data;
    }

    /**
     * Removes the progress tracking session file/entry associated with the current batch operation.
     *
     * If a `session_id` was provided during the instantiation or set in the data,
     * this method calls `mysql__update_progress` with the 'REMOVE' action
     * to clean up the progress tracking data. This is typically called after a long-running
     * batch operation is completed or terminated.
     *
     * @return void
     */
    public function removeSession(){
        if($this->session_id!=null){
            mysql__update_progress($this->system->getMysqli(), $this->session_id, false, 'REMOVE');
        }
    }

    /**
     * Updates the batch operation report (`$this->result_data`) with counts for a given processing type
     * and optionally assigns a system tag to the affected records.
     *
     * If `$type` indicates an error or specific failure (e.g., 'errors', 'parseexception'),
     * `$recordIds` is expected to be an associative array [recID => message], and this list is stored
     * in `$this->result_data[$type.'_list']`. For other types, `$recordIds` is a simple array of record IDs.
     * The count of records is stored in `$this->result_data[$type]`.
     *
     * If `$this->data['tag']` is 1 and a `$baseTag` string is provided, this function
     * attempts to assign a system tag to the `$recordIds`. The tag name is `$baseTag` or
     * `$baseTag . ' ' . $type` if `$type` is not 'processed'.
     * The outcome of tagging (tag name or error) is stored in `$this->result_data[$type.'_tag']`
     * or `$this->result_data[$type.'_tag_error']`.
     *
     * @access private
     * @param string $type A string key indicating the type of processing outcome for these records
     *                     (e.g., 'processed', 'undefined', 'limited', 'errors', 'parseexception', 'parseempty', 'fails').
     * @param array $recordIds An array of record IDs, or an associative array [recID => message] for error types.
     * @param string|null $baseTag The base name for the system tag to be assigned. If null, no tagging occurs.
     * @return void
     * 
     * @todo all tags routine must be from DbUsrTags
     */
    private function _assignTagsAndReport($type, $recordIds, $baseTag)
    {
        if (!isEmptyArray($recordIds)) {

            if($type=='errors' || $type=='parseexception' || $type=='parseempty' || $type=='fails'){
                $this->result_data[$type.'_list'] = $recordIds;
                $recordIds = array_keys($recordIds);
            }

            $this->result_data[$type] = count($recordIds);

            $needBookmark = (@$this->data['tag']==1);

            if($baseTag!=null && $needBookmark){

                if($type!='processed'){
                    $baseTag = $baseTag.' '.$type;
                }

                $success = $this->_tagsAssign($recordIds, null, $baseTag);
                if($success){
                    $this->result_data[$type.'_tag'] = $baseTag;
                }else{
                    //error on tag assign
                    $this->result_data[$type.'_tag_error'] = $this->system->getError();
                }
            }
        }
    }

    /**
    * Assigns specified tags to a list of records and creates bookmarks for these records for the given user/group.
    *
    * - Validates user access rights for the target user/group (`$ugrID`).
    * - If `$tag_ids` are not provided, it resolves `$tag_names` to tag IDs using `_tagGetByName`, creating new tags if necessary.
    * - Inserts new tag links into `usrRecTagLinks` (ignoring duplicates).
    * - If `$ugrID` represents a user (not a group), it creates bookmarks in `usrBookmarks` for records that are not already bookmarked by that user.
    *
    * @access private
    * @param array $record_ids An array of record IDs to which tags/bookmarks will be applied.
    * @param array|null $tag_ids An array of tag IDs to assign. If null, `$tag_names` must be provided.
    * @param string|null $tag_names A comma-separated string of tag names. Used if `$tag_ids` is null.
    *                                New tags will be created if they don't exist for the `$ugrID`.
    * @param int|null $ugrID The user/group ID for whom the tags are being assigned and bookmarks created.
    *                        Defaults to the current user ID from `$this->system`.
    * @return array|false Returns an associative array `['tags_added' => count, 'bookmarks_added' => count]` on success.
    *                     Returns `false` if there's a permission error, invalid parameters (e.g., no record IDs, no tag IDs/names),
    *                     or a database error during insertion. Errors are added to the system object.
    */
    private function _tagsAssign($record_ids, $tag_ids, $tag_names=null, $ugrID=null){

        $system = $this->system;

        if($ugrID<1) {$ugrID = $system->getUserId();}

        if (!$system->hasAccess($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            //find tag_ids by tag name
            if($tag_ids==null){
                if($tag_names==null){
                    $system->addError(HEURIST_INVALID_REQUEST, 'Tag name is not defined');
                    return false;
                }else{

                    $tag_ids = $this->_tagGetByName(array_filter(explode(',', $tag_names)), true, $ugrID);
                }
            }
            if( isEmptyArray($record_ids) ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Record ids are not defined');
                return false;
            }

            if( isEmptyArray($tag_ids) ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Tags ids either not found or not defined');
                return false;
            }

            $mysqli = $system->getMysqli();

            $record_ids = prepareIds($record_ids);//for snyk
            $tag_ids    = prepareIds($tag_ids);//for snyk

            //assign links
            $insert_query = 'insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'select rec_ID, tag_ID from usrTags, Records '
                . ' where rec_ID in (' . implode(',', $record_ids) . ') '
                . ' and tag_ID in (' . implode(',', $tag_ids) . ')'
                . ' and tag_UGrpID = '.$ugrID;
            $res = $mysqli->query($insert_query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $tag_count = $mysqli->affected_rows;

            /*$new_rec_ids = mysql__select_column($mysqli,
            'select rec_ID from Records '
            .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
            .' where bkm_ID is null and rec_ID in (' . join(',', $record_ids) . ')');*/

            //if $ugrID is not a group - create bookmarks
            $bookmarks_added = 0;
            if ($ugrID==$system->getUserId() ||
                mysql__select_value($mysqli, 'select ugr_Type from sysUGrps where ugr_ID ='.$ugrID)=='user')
            { //not bookmarked yet
                $query = 'insert into usrBookmarks '
                .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                .' select ' . $ugrID . ', now(), now(), rec_ID from Records '
                .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
                .' where bkm_ID is null and rec_ID in (' . implode(',', $record_ids) . ')';

                //$stmt = $mysqli->query($query);

                $res = $mysqli->prepare($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,"Cannot add bookmarks", $mysqli->error);
                    return false;
                }
                $bookmarks_added = $mysqli->affected_rows;
            }

            return array('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added);
        }
    }

    /**
    * Retrieves tag IDs for a given list of tag names, optionally creating them if they don't exist.
    *
    * Tags are specific to a user/group (`$ugrID`).
    * If `$isadd` is true, any tag name not found for the given `$ugrID` will be created using `_tagSave`.
    *
    * @access private
    * @param array|string $tag_names An array of tag name strings, or a single comma-separated string of tag names.
    * @param bool $isadd If true, new tags will be created if they don't already exist for the specified user/group.
    * @param int|null $ugrID The user/group ID to which the tags belong or will belong.
    *                        Defaults to the current user ID from `$this->system`.
    * @return array|null An array of unique tag IDs (integers) corresponding to the provided names.
    *                    Returns null if `$ugrID` cannot be determined.
    *                    Returns an empty array if no tag names are provided or no tags are found/created.
    */
    private function _tagGetByName($tag_names, $isadd, $ugrID=null){

        $system = $this->system;

        if (!$ugrID) {
            $ugrID = $system->getUserId();
        }
        if(!$ugrID) {return null;}

        if(is_string($tag_names)){
            $tag_names = explode(",", $tag_names);
        }

        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
            if(strlen($tag_name)>0){

                $res = mysql__select_value($system->getMysqli(), 'select tag_ID from usrTags where lower(tag_Text)=lower("'.
                    $system->getMysqli()->real_escape_string($tag_name).'") and tag_UGrpID='.$ugrID);
                if($res){
                    array_push($tag_ids, $res);
                }elseif($isadd){
                    $res = $this->_tagSave( array('tag_UGrpID'=>$ugrID, 'tag_Text'=>$tag_name));
                    if($res){
                        array_push($tag_ids, $res);
                    }
                }
            }
        }
        $tag_ids = array_unique($tag_ids, SORT_NUMERIC);

        return $tag_ids;
    }

    /**
    * Inserts or updates a tag in the `usrTags` table.
    *
    * - Validates that the tag text (`$tag['tag_Text']`) is provided.
    * - Checks if the current user has access rights for the specified `$tag['tag_UGrpID']`.
    * - If inserting a new tag (no `tag_ID` provided or `tag_ID` < 1), it first checks if a tag with the
    *   same text already exists for the `tag_UGrpID` using `_tagGetByName`. If so, it updates that existing tag.
    * - Uses `mysql__insertupdate` for the actual database operation.
    *
    * @access private
    * @param array $tag An associative array containing tag data. Expected keys:
    *                   - 'tag_Text': (string, required) The text of the tag.
    *                   - 'tag_UGrpID': (int, required) The user/group ID to associate with the tag.
    *                   - 'tag_ID': (int, optional) The ID of the tag to update. If not provided or < 1, an insert is attempted.
    *                   - 'tag_Description': (string, optional) Description for the tag.
    *                   - 'tag_AddedByImport': (int, optional) Flag indicating if added by import.
    * @return int|false The ID of the inserted or updated tag on success.
    *                   Returns `false` if there's an error (e.g., missing text, permission denied, DB error).
    *                   Errors are added to the system object.
    */
    private function _tagSave($tag){

        $system = $this->system;

        if(!@$tag['tag_Text']){
            $system->addError(HEURIST_INVALID_REQUEST, "Text not defined");
            return false;
        }

        if (!$system->hasAccess(@$tag['tag_UGrpID'])) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(intval(@$tag['tag_ID'])<1){
                $samename = $this->_tagGetByName($tag['tag_Text'], false, $tag['tag_UGrpID']);

                if(!isEmptyArray($samename)){
                    $tag['tag_ID'] = $samename[0];
                }
            }

            $res = mysql__insertupdate($system->getMysqli(), "usrTags", "tag", $tag);
            if(is_numeric($res) && $res>0){
                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                return false;
            }

        }
    }

    /**
     * Create sub records for the given record type
     *  Selected record fields are transferred to the newly created records of the selected 'sub-record' type
     *  Selected record fields are transferred to the newly created records of the selected 'sub-record' type.
     *  These new 'sub-records' are then set as child records to the original source record by:
     *  1. Adding a "Parent Entity" (DT_PARENT_ENTITY) pointer in the new sub-record, pointing to the original source record.
     *  2. Adding a record pointer detail in the original source record (field specified by `trg_dty`), pointing to the new sub-record.
     *  The original detail values are moved from the source record to the new sub-record(s).
     *
     * This operation requires admin privileges and the `DT_PARENT_ENTITY` constant to be defined.
     *
     * Expected parameters in `$this->data`:
     * - 'src_rty': (int) ID of the source record type from which values will be taken.
     * - 'trg_rty': (int) ID of the target record type for the new sub-records.
     * - 'src_dtys': (array|string) Detail type ID(s) in the source record type whose values will be moved.
     * - 'trg_dty': (int) Detail type ID in the source record type that will be used to point to the newly created sub-record(s).
     *                  This field must be of type 'resource'.
     * - 'split_values': (int, optional) If 0 (default), all selected details from a source record are moved to one new sub-record.
     *                     If 1, each individual detail value from the source fields will result in a separate new sub-record.
     *
     * @return array|false Returns an associative array `['count' => (int)num_new_records, 'record_ids' => (string)comma_separated_IDs]` on success.
     *                     Returns `false` on error (e.g., permission denied, parameters missing/invalid, DB error, DT_PARENT_ENTITY not defined).
     *                     Errors are added to the system object.
     */
    public function createSubRecords(){

        // Can only be used by an administrator
        if(!$this->system->isAdmin()){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can create sub records');
            return false;
        }

        $system = $this->system;
        $mysqli = $system->getMysqli();

        $system->defineConstant('DT_PARENT_ENTITY');
        if(!defined('DT_PARENT_ENTITY')){
            $system->addError(HEURIST_ERROR, 'An error occurred while attempting to define system constants');
            return false;
        }

        $data = $this->data;

        // Retrieve and validate values

        /** List of values:
         * src_rty => Source record type
         * src_dtys => Source base fields
         * trg_rty => Target record type
         * trg_dty => Target record pointer field
         * split_values => Create a record per repeated value for each record
         */

        if(empty(@$data['src_rty']) || empty(@$data['trg_rty']) || empty(@$data['src_dtys']) || empty(@$data['trg_dty'])){
            $system->addError(HEURIST_INVALID_REQUEST, 'Parameters missing');
            return false;
        }

        $source_rty = intval($data['src_rty']);
        $target_rty = intval($data['trg_rty']);
        $split_values = empty(@$data['split_value']) ? 0 : $data['split_value'];
        $source_ids = prepareIds($data['src_dtys']);
        $target_field = intval($data['trg_dty']);

        if($source_rty <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source record type provided');
            return false;
        }
        if($target_rty <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target record type provided');
            return false;
        }
        if(empty($source_ids)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source fields prepared');
            return false;
        }
        if($target_field <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target field provided');
            return false;
        }

        // Ensure target field exists in source structure and is a record pointer
        $query = "SELECT rst_ID FROM defRecStructure INNER JOIN defDetailTypes ON dty_ID = rst_DetailTypeID WHERE rst_RecTypeID = $source_rty AND rst_DetailTypeID = $target_field AND dty_Type = 'resource'";
        $target_in_struct = mysql__select_value($mysqli, $query);
        if($target_in_struct <= 0){
            $system->addError(HEURIST_ACTION_BLOCKED, 'Invalid target field');
            return false;
        }

        // Retieve existing records of source type
        $record_ids = mysql__select_list2($mysqli, "SELECT rec_ID FROM Records WHERE rec_FlagTemporary != 1 AND rec_RecTypeID = $source_rty", 'intval');

        if(empty($record_ids)){
            return ['count' => 0, 'record_ids' => []];
        }

        $rec_count = count($record_ids);// this is to avoid multiple swf emails when creating records
        $cur_count = 0;
        if($this->session_id != null){
            mysql__update_progress($mysqli, $this->session_id, true, "0,{$rec_count}");
        }

        $new_records = [];// final array of newly created records

        $keep_autocommit = mysql__begin_transaction($mysqli);

        if(!$this->checkRecordStructure($target_rty, $source_ids, $source_rty)){
            $mysqli->rollback();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
            return false;
        }

        foreach($record_ids as $rec_id){

            $cur_count ++;
            $rec_id = intval($rec_id);//snyk does not see intval in mysql__select_list2

            if($this->session_id != null){
                $current_val = mysql__update_progress($mysqli, $this->session_id, true, "{$cur_count},{$rec_count}");
                if($current_val == 'terminate'){
                    break;
                }
            }

            // 1. Get values -----
            $details_to_transfer = array();
            $has_values = false;

            foreach($source_ids as $dty_id){

                $details = mysql__select_list2($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $rec_id AND dtl_DetailTypeID = $dty_id");

                if(empty($details)){ // skip
                    continue;
                }

                $idx = 0;

                if($split_values == 0){
                    array_push($details_to_transfer, ...$details);
                }else{
                    foreach($details as $dtl_ID){

                        if(!array_key_exists($idx, $details_to_transfer)){
                            $details_to_transfer[$idx] = array();
                        }
                        array_push($details_to_transfer[$idx], ...$dtl_ID);

                        ++ $idx;
                    }
                }

                $has_values = true;
            }

            if(!$has_values){
                continue;
            }

            // 2. Create new sub-records -----
            // Include references to the parent record
            $record = [
                'ID' => 0,
                'no_validation' => 'ignore_all',
                'rec_RecTypeID' => $target_rty,
                'details' => [
                    DT_PARENT_ENTITY => [$rec_id]
                ]
            ];

            $new_rec_ids = array();
            if($split_values == 0){

                //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                if($result['status'] != HEURIST_OK){

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    return false;
                }

                $new_rec_ids[] = $result['data'];
            }else{

                foreach($details_to_transfer as $details){

                    //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                    $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                    if($result['status'] != HEURIST_OK){

                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}

                        return false;
                    }

                    $new_rec_ids[] = $result['data'];
                }
            }

            // 3. Update dtl_RecID for original values to point to new records -----
            foreach($new_rec_ids as $idx => $rec_id){

                $dtl_IDs = $details_to_transfer;
                if($split_values != 0){
                    $dtl_IDs = $details_to_transfer[$idx];
                }

                $dtl_IDs = prepareIds($dtl_IDs);//for snyk

                $upd_where = count($dtl_IDs) == 1 ? ("= " . $dtl_IDs[0]) : ("IN (" . implode(',', $dtl_IDs) . ")");
                $upd_query = "UPDATE recDetails SET dtl_RecID = $rec_id WHERE dtl_ID $upd_where";
                $res = $mysqli->query($upd_query);

                if(!$res || $mysqli->affected_rows == 0){ // affected rows should always be greater than 0

                    $msg = "<br><br>Error => " . $mysqli->error . "<br><br>Query => $upd_query";
                    $msg .= "<br><br>dtl_IDs => " . print_r($dtl_IDs, true);
                    $system->addError(HEURIST_DB_ERROR, "An SQL error occurred while attempting to update the original values from record #$rec_id");

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    return false;
                }
            }

            // 4. Add child reference to original record -----

            // Get original record's header fields, to avoid lossing them
            $record = recordSearchByID($this->system, $rec_id, false);
            if(!$record){

                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return false;
            }

            // Add rec pointer value(s)
            $record['ID'] = $record['rec_ID'];
            unset($record['rec_ID']);
            $record['no_validation'] = 1;
            $record['details'] = array(
                $target_field => $new_rec_ids
            );

            $result = recordSave($this->system, $record, false, false, 2);
            if($result['status'] != HEURIST_OK || $result['data'] != $record['ID']){

                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}

                return false;
            }

            array_push($new_records, ...$new_rec_ids);// add new rec ids to array
        }

        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        $final_count = count($new_records);// get final count of new records

        return ['count' => $final_count, 'record_ids' => implode(',', $new_records)];
    }

    /**
     * Checks if a target record type (`$rtyID`) includes all specified detail types (`$dtyIDs`).
     * If `$importFromRty` is provided and greater than 0, and a field is missing in `$rtyID`,
     * this function will attempt to copy the field definition from `$importFromRty`'s structure
     * into `$rtyID`'s structure.
     *
     * @access private
     * @param int $rtyID The ID of the target record type whose structure is to be checked (and potentially updated).
     * @param array $dtyIDs An array of detail type IDs that must exist in the target record type's structure.
     * @param int $importFromRty Optional. The ID of a source record type from which to copy field definitions
     *                           if they are missing in `$rtyID`. Defaults to 0 (no import).
     * @return bool True if all specified `$dtyIDs` exist in `$rtyID`'s structure (either initially or after import),
     *              false otherwise (e.g., invalid parameters, a field is missing and cannot be imported, DB error).
     *              Errors are added to the system object on failure.
     */
    private function checkRecordStructure($rtyID, $dtyIDs, $importFromRty = 0){

        $dtyIDs = prepareIds($dtyIDs);
        $rtyID = intval($rtyID);
        $importFromRty = intval($importFromRty);

        if($rtyID <= 0 || empty($dtyIDs)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, $rtyID <= 0 ? 'Invalid record type to check has been provided' : 'No fields have been provided to check for');
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $hasAllFields = true;

        foreach($dtyIDs as $dtyID){

            $hasFld = mysql__select_value($mysqli, "SELECT rst_ID FROM defRecStructure WHERE rst_DetailTypeID = ? AND rst_RecTypeID = ?", ['ii', $dtyID, $rtyID]);

            if($hasFld > 0){
                continue;
            }

            if($importFromRty <= 0){
                $hasAllFields = $dtyID;
                break;
            }

            $fieldDetails = mysql__select_row_assoc($mysqli, "SELECT * FROM defRecStructure WHERE rst_DetailTypeID = {$dtyID} AND rst_RecTypeID = {$importFromRty}");
            if(empty($fieldDetails)){
                $hasAllFields = $dtyID;
                break;
            }

            unset($fieldDetails['rst_ID']);

            $fieldDetails['rst_RecTypeID'] = $rtyID;

            $rstID = mysql__insertupdate($mysqli, 'defRecStructure', 'rst', $fieldDetails, true);

            if(!$rstID){
                $hasAllFields = $dtyID;
                break;
            }
        }

        if(is_int($hasAllFields)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, "Record structure is missing field type {$hasAllFields}");
            return false;
        }

        return true;
    }

    /**
     * Change letter cases fo values found in freetext and blocktext (memo) fields based on selection:
     *  1 - Lowercase, uppercase first letter + first letter following fullstops
     *  2 - Lowercase, uppercase first letter of each word
     *  3 - All lowercase
     *  4 - All capital
     * Also changes words/phrases based on list of exceptions (performed last to avoid further editing)
     *  1: Lowercase all, then uppercase first letter of string AND first letter following each full stop.
     *  2: Lowercase all, then uppercase first letter of each word (respects camelCase words).
     *  3: Convert all to lowercase.
     *  4: Convert all to uppercase.
     * After the primary case conversion, a list of exceptions (words/phrases) can be applied to ensure
     * they are cased exactly as provided in the exceptions list, overriding the general conversion for those specific terms.
     * This function handles HTML content by processing only text nodes, preserving HTML tags.
     *
     * Expected parameters in `$this->data`:
     * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
     * - 'op': (int, required) The operation type (1-4 as described above).
     * - 'except': (array|string, optional) An array of exception strings, or a pipe ('|') separated string list.
     *             These strings will be enforced with their exact casing after the main operation.
     *
     * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
     *                     'undefined' (no values in field or no change needed), 'errors', and tag info).
     *                     Returns `false` on critical validation failure (e.g., invalid field type, invalid operation).
     */
    public function caseConversion(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        $operation = intval($this->data['op']);// number corresponding to an operation below
        $doc = new DOMDocument; // for handling html text

        // Prepare exceptions list
        $exceptions = empty(@$this->data['except']) ? array() : $this->data['except'];
        if(!is_array($exceptions)){
            $exceptions = explode('|', $exceptions);
        }

        if(!empty($exceptions)){
            $new_excepts = array();

            foreach ($exceptions as $value) {
                if(empty($value)){
                    continue;
                }

                array_push($new_excepts, $mysqli->real_escape_string($value));
            }

            $exceptions = $new_excepts;
        }

        // Regular expressions for operations
        $regex = $operation == 1 ? '(\.\s+)(\w+)' : '';
        $regex = $operation != 1 ? '\w+' : $regex;

        // Temp tags for HTML handling, loadHTML will tend to add paragraph tags if no outer tag exists
        $temp_open = "<span data-t='zzz_temp'>";
        $temp_close = "</span>";

        // Callback function for regex functions
        $callback = function($match) use ($operation){

            $word = $operation == 1 ? $match[2] : $match[0];

            if($operation == 1){
                // lowercase then capitalise first letter + first letter following full stop

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return $match[1] . mb_strtoupper($first) . $remainder;

            }elseif($operation == 2){
                // lowercase then capitalise first letter for all words

                if(strlen($word) == 1 || mb_ereg("[a-z][A-Z]|[A-Z][a-z]", $word)){ // skip if one letter or camel case
                    return $word;
                }

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return mb_strtoupper($first) . $remainder;

            }
        };

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace case convert $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Case conversion only works on valid freetext and blocktext fields');
            return false;
        }

        // Validate operation value
        if($operation < 1 || $operation > 4){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Provided operation is not handled by case converter');
            return false;
        }

        $use_reg = $operation < 2; // whether to use the regex functions

        //$keep_autocommit = mysql__begin_transaction($mysqli);

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            // Cycle through values
            while($values = $res->fetch_row()){

                $value = '';

                if($values[1] != strip_tags($values[1])){ // potentially has HTML

                    $value = $temp_open.$values[1].$temp_close; // add temp tags, to avoid extra elements

                    $doc->loadHTML($value, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);// load html

                    $xpath = new DOMXPath($doc);// retrieve text only
                    $text_nodes = $xpath->query('//text()');

                    foreach($text_nodes as $node){

                        $text = $operation == 1 || $operation == 3 ? mb_strtolower($node->textContent) : $node->textContent;
                        $text = $operation == 4 ? mb_strtoupper($text) : $text;

                        $node->textContent = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;
                    }

                    $value = $doc->saveHTML();// save new value

                    // strip temp tags
                    $value = mb_substr($value, strlen($temp_open));
                    $value = mb_substr($value, 0, mb_strlen($value) - strlen($temp_close) - 1);

                }else{ // normal text

                    $text = $operation == 1 ? mb_strtolower($values[1]) : $values[1];
                    $text = $operation == 4 ? mb_strtoupper($text) : $text;

                    $value = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;

                    if($operation == 1 && !empty($value)){ // capitalise first letter

                        $first = mb_substr($value, 0, 1);
                        $remainder = mb_strlen($value) == 1 ? "" : mb_substr($value, 1, null);

                        $value = mb_strtoupper($first) . $remainder;
                    }
                }

                if(empty($value)){ // ensure there is a value to save
                    continue;
                }

                foreach($exceptions as $except){ // apply exceptions
                    $regex = preg_quote($except);
                    $regex = "\b$regex\b";
                    if(mb_eregi($regex, $value)){ // check if exception appears in string
                        $value = mb_eregi_replace($regex, $except, $value);// replace
                    }
                }

                // Update details value + modified
                $dtl_rec = array('dtl_ID' => intval($values[0]), 'dtl_Value' => $value, 'dtl_Modified' => $date_mode);

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                }
            }//for

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }


    /**
     * Translates the content of a specified freetext or blocktext field for a batch of records
     * to a target language using an external translation service (via `getDeepLTranslation`).
     *
     * Key operations:
     * - Validates parameters, record accessibility, and ensures the target field is text-based.
     * - For each record and its values in the specified field (`dtyID`):
     *   - Identifies the source text: Prefers text without a language prefix. If multiple values exist,
     *     the logic for selecting the definitive source value is based on the first value encountered without a prefix,
     *     or the first value if all have prefixes (source language is then detected from that prefix).
     *   - Checks if a translation to the target language (`$this->data['lang']`) already exists.
     *     - If `delete` is true: Deletes the existing translation if found.
     *     - If `replace` is true or no existing translation: Translates the source text.
     *     - If already translated and not replacing/deleting: Skips (adds to `already_translated`).
     *   - If translation occurs:
     *     - The translated text is prefixed with the target language code (e.g., "en:Translated text").
     *     - The new or updated translated detail is saved to `recDetails`.
     *     - `rec_Modified` is updated.
     * - Assigns system tags if enabled.
     *
    * Expected parameters in `$this->data`:
     * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
     * - 'lang': (string, required) The target language code (e.g., 'en', 'fr').
     * - 'replace': (int, optional) If 1, existing translations for the target language will be replaced.
     * - 'delete': (int, optional) If 1, existing translations for the target language will be deleted.
     *
     * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
     *                     'undefined' (no source value), 'translated' (already had target translation), 'errors', and tag info).
     *                     Returns `false` on critical validation/translation failure or if the target language is not defined.
     */
    public function fieldTranslation(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~translation $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Translation only works on valid freetext and blocktext fields');
            return false;
        }

        $is_replacement = (@$this->data['replace']==1);
        $is_deletion = (@$this->data['delete']==1);
        $lang = @$this->data['lang'];

        $lang = getLangCode3($lang);

        if($lang==null){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Language is not defined');
            return false;
        }

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $already_translated = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            $replacement_dtl_id = -1;
            $value_to_translate = null;
            $all_detected = 0;
            $source_lang = null;

            // Cycle through values - find and source and possible replacement
            while( ($values = $res->fetch_row()) && ($all_detected<2)){

                //detect language
                list($lang2, $val) = extractLangPrefix($values[1]);
                if($lang2==null){
                    //source
                    $value_to_translate = $val;      // $is_replacement
                    $all_detected++;
                    $source_lang = null;
                }elseif($lang2==$lang){
                    //already has this translation
                    if($is_replacement || $is_deletion){
                        $replacement_dtl_id = intval($values[0]);
                    }else{
                        $replacement_dtl_id = 0;
                    }
                    $all_detected++;
                }elseif(empty($value_to_translate)){
                    // temporary source, is replaced by value w/o language prefix
                    $value_to_translate = $val;
                    $source_lang = $lang2;
                }
            }//while

            if($is_deletion){

                if($replacement_dtl_id>0){
                    $query = 'DELETE FROM recDetails WHERE dtl_ID='.$replacement_dtl_id;
                    $ret = $mysqli->query($query);
                    if(!$ret){
                        $sql_errors[$recID][] = $mysqli->error;
                    }
                }else{
                    array_push($skipped_recs, $recID);
                }
            }elseif($value_to_translate==null){
                //source not found - skip
                array_push($skipped_recs, $recID);
            }elseif($replacement_dtl_id==0){
                //already translated
                array_push($already_translated, $recID);
            }else {

                // get translated value
                $translated = getDeepLTranslation($this->system, $value_to_translate, $lang, $source_lang);

                //$translated = $lang.': TRNASLATED! '.$value_to_translate;

                if($translated===false){
                    //break;
                    $this->system->addErrorMsg('Translation has been terminated for record# '.$recID.'. <br>');
                    return false;
                }

                $translated = $lang.':'.$translated;

                // Update details value + modified
                $dtl_rec = array('dtl_Value' => $translated, 'dtl_Modified' => $date_mode);
                if($replacement_dtl_id>0){
                    $dtl_rec['dtl_ID'] = $replacement_dtl_id;
                }else{
                    $dtl_rec['dtl_RecID'] = $recID;
                    $dtl_rec['dtl_DetailTypeID'] = $dtyID;
                }

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }
            }

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }//foreach records

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('translated', $already_translated, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }

    /**
     * Uploads locally stored files (associated with a specified detail field in a batch of records)
     * to an external repository (currently supports Nakala) and updates the record details
     * to point to the new external URL.
     *
     * Key operations:
     * - Validates parameters and record accessibility.
     * - Retrieves user credentials for the specified repository using `user_getRepositoryCredentials2`.
     * - For each record and its values in the specified file field (`dtyID`):
     *   - Identifies local files (not `_remote`, `__iiif__`, or `__tiled__`).
     *   - For each unique local file (`ulf_ID`):
     *     - Gathers metadata for the file (name, path, MIME type, description, uploader, added date).
     *     - Prepares repository-specific metadata (e.g., for Nakala: title, type, license, creator).
     *     - Uploads the file to the repository (e.g., `uploadFileToNakala`).
     *     - If upload is successful, registers the returned URL as a new `_remote` entry in `recUploadedFiles`
     *       using `DbRecUploadedFiles::registerURL()`, associating repository info in `ulf_Parameters`.
     *     - Updates all `recDetails` that pointed to the original local `ulf_ID` to now point to the new
     *       `ulf_ID` for the remote URL, using `_updateUploadedFileIDs()`.
     * - Optionally deletes the original local file and its `recUploadedFiles` entry if `$this->data['delete_file']` is 1
     *   and all references to it have been updated or if it's no longer referenced.
     * - Assigns system tags if enabled and reports outcomes.
     *
     * Expected parameters in `$this->data`:
     * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
     * - 'repository': (string, required) Service ID of the target repository (e.g., "nakala.fr", "test.nakala.fr").
     * - 'license': (string, required for some repositories like Nakala) License for the uploaded file.
     * - 'delete_file': (int, optional) If 1, delete original local file after successful upload and reference update.
     *
     * @return array|false The result array (`$this->result_data`) summarizing the operation.
     *                     Returns `false` on critical validation/upload failure or if repository credentials are not found/valid.
     */
    public function uploadFileToRepository(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $today = date(DATE_8601);

        $dtyID = $this->data['dtyID'];
        $dtyName = @$this->data['dtyName'] ? "'{$this->data['dtyName']}'" : "id:{$this->data['dtyID']}";
        $baseTag = "~replace file to url $dtyName $today";

        $processedRecIDs = [];
        $sqlErrors = [];
        $uploadError = [];
        $failedIDs = [];

        $fileEntity = new DbRecUploadedFiles($this->system);

        // Find relevant local files
        $query = 'SELECT dtl_ID, ulf_ID, dtl_RecID '
        .'FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND '
        .'(NOT(ulf_OrigFileName="_remote" OR ulf_OrigFileName LIKE "'.ULF_IIIF.'%" OR ulf_OrigFileName LIKE "'.ULF_TILED_IMAGE.'%" '
        .'OR COALESCE(ulf_PreferredSource,"") LIKE "iiif%" OR COALESCE(ulf_PreferredSource,"") LIKE "tiled%"))'
        .' AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs)
        .' ORDER BY ulf_ID';
        $res = $mysqli->query($query);
        /** $row:
         * [0] => Rec Detail ID
         * [1] => File ID
         * [2] => Record ID
         */

        if(!$res){ // mysql error, end
            $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to retrieve records using locally stored files.<br><br>MySQLi Error: {$mysqli->error}");
            return false;
        }

        $cur_ulfID = 0;
        $new_ulfID = 0;
        $dtlIDs = [];
        $recIDs = [];
        $completed_ulfIDs = [];

        //2024-03-23
        // Obtain write API key/credentials
        $serviceID = $this->data['repository'];

        $credentials = user_getRepositoryCredentials2($this->system, $serviceID);

        if($credentials==null){

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Credentials for sepecified repository and user/group not found');
            return false;

        }elseif(!@$credentials[$serviceID]['params']['writeApiKey']){

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Write Credentials for sepecified repository and user/group not defined');
            return false;

        }elseif(strpos($serviceID,'nakala')===0 || strpos($serviceID,'nakala')===1){

            if(!array_key_exists('license', $this->data) || empty($this->data['license'])){ // ensure a license has been provided
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'A license is missing');
                return false;
            }

            $metaValues = [];
            $file = [];

            // General Meta data
            // Normal Creator field (we use alternative author field, as this requires Author Ids/ORCIDs)
            $metaValues['creator'] = [
                'value' => null,
                'lang' => null,
                'typeUri' => null,
                'propertyUri' => NAKALA_REPO.'terms#creator'
            ];
            // Provided by user - used for all files
            $metaValues['license'] = [
                'value' => $this->data['license'],
                'lang' => null,
                'typeUri' => W3_XML_SCHEMA_STRING,
                'propertyUri' => NAKALA_REPO.'terms#license'
            ];

            $apiKey = $credentials[$serviceID]['params']['writeApiKey']; // $this->system->settings->get('sys_NakalaKey')
            $status = @$this->data['status'] === 'pending' || @$this->data['status'] === 'published' ? $this->data['status'] : 'pending'; // pending | published

            while($row = $res->fetch_row()){

                if($cur_ulfID != $row[1]){

                    if($new_ulfID > 0){
                        if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                            $completed_ulfIDs[$row[1]] = $new_ulfID;
                            $processedRecIDs = array_merge($processedRecIDs, $recIDs);
                        }else{
                            $failedIDs = array_merge($failedIDs, $recIDs);
                        }
                    }

                    $cur_ulfID = $row[1];
                    $dtlIDs = [];
                    $recIDs = [];
                    $new_ulfID = 0;

                    [$fileMetadata, $file] = getFileDetailsForNakala($mysqli, $row[1]);
                    if(!$fileMetadata){
                        $sqlErrors[$row[2]][] = $file;
                        $failedIDs[] = $row[2];
                        continue;
                    }

                    $fileMetadata = array_merge($fileMetadata, $metaValues);

                    $rtn = uploadFileToNakala($this->system, [
                        'apiKey' => $apiKey, 'file' => $file,
                        'meta' => $fileMetadata, 'status' => $status
                    ]);

                    if($rtn){ // register URL ($rtn)

                        $nakalaIdentifier = @$rtn['DOI']; // reserved DOI string, set by uploadFilesToNakala() regardless of returnType

                        $ulfParams = ['repository' => $serviceID];
                        if($nakalaIdentifier){
                            // "Touch" the file record with its DOI - the Nakala identifier IS the DOI
                            // string from creation onward, but is only registered/resolvable with
                            // DataCite once $status is 'published' (see getNakalaDataDetails() to
                            // confirm/regain this later if it changes after the fact)
                            $ulfParams['doi'] = $nakalaIdentifier;
                            $ulfParams['doiRegistered'] = $status === 'published';
                        }

                        $fields = [];
                        if($serviceID){
                            $fields['ulf_Parameters'] = json_encode($ulfParams);
                        }else{
                            $fields = null;
                        }

                        $new_ulfID = $fileEntity->registerURL($rtn['URL'], false, 0, $fields);// register nakala url
                        if(!isPositiveInt($new_ulfID)){
                            $sqlErrors[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $mysqli->error;
                            $failedIDs[] = $row[2];
                        }elseif($nakalaIdentifier){
                            // Also log it at database level, alongside any other repository deposits
                            // (eg. the whole-database archive backup) in this database's external_IDs.json
                            recordExternalIdentifier($this->system, "{$serviceID}_{$new_ulfID}", [
                                'Service' => 'nakala',
                                'Label' => "Nakala file transfer (file #{$new_ulfID})",
                                'ID' => $nakalaIdentifier,
                                'DOI' => $nakalaIdentifier,
                                'DOIRegistered' => ($status === 'published'),
                                'URL' => $rtn['URL'],
                                'Date' => $today
                            ]);
                        }
                    }else{

                        $errMsg = $this->system->getError();

                        if(array_key_exists('message', $errMsg) && !empty($errMsg['message'])){
                            $errMsg = $errMsg['message'];
                        }else{
                            $errMsg = 'Unknown error occurred while uploading to Nakala';
                        }

                        $uploadError[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $errMsg;
                        $failedIDs[] = $row[2];
                    }
                }

                $dtlIDs[] = intval($row[0]);
                $recIDs[] = intval($row[3]);

            } // while
        }
        if($new_ulfID > 0){
            if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                $completed_ulfIDs[$row[1]] = $new_ulfID;
                $processedRecIDs = array_merge($processedRecIDs, $recIDs);
            }else{
                $failedIDs = array_merge($failedIDs, $recIDs);
            }
        }

        if(!empty($completed_ulfIDs)){
            $ulfToDelete = [];
            foreach ($completed_ulfIDs as $org_ulfID => $new_ulfID) {
                $query = "SELECT dtl_ID FROM recDetails WHERE dtl_UploadedFileID = {$org_ulfID}";
                $dtlIDs = mysql__select_list2($mysqli, $query, 'intval');

                if(!$dtlIDs){
                    continue;
                }

                if(empty($dtlIDs)){ // delete file reference + local file
                    $ulfToDelete[] = $org_ulfID;
                }elseif(array_key_exists('delete_file', $this->data) && $this->data['delete_file'] == 1){
                    // update references
                    $dtlIDs = prepareIds($dtlIDs);//for snyk
                    if($this->_updateUploadedFileIDs($new_ulfID, $dtlIDs, $today)){
                        // then delete the file reference + local file
                        $ulfToDelete[] = $org_ulfID;
                    }
                }
            }

            if(!empty($ulfToDelete)){
                $curData = $fileEntity->getData();
                $curData['ulf_ID'] = array_unique($ulfToDelete);
                $fileEntity->setData($curData);
                $fileEntity->delete();
            }
        }

        $failedIDs = array_unique($failedIDs);

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  array_merge($sqlErrors, $uploadError), $baseTag);
        $this->result_data['fails'] = count($failedIDs);
        $this->result_data['fails_list'] = $failedIDs;

        return $this->result_data;
    }


    /**
     * Creates links (resource/record pointer field) or Adds relationship records between records
     * based on certain fields values matching
     *
     * $data parameters
     *          dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
     *          rty_src or recids_src, dty_src, rty_trg, dty_trg - matching conditions
     *          `replace` - (int, optional) If 1, existing links of the same type on the source record will be replaced. Otherwise, new links are added.
     *
     * The method first validates parameters and user permissions (admin only).
     * It then uses `recordSearchMatchedValues()` to find pairs of source and target record IDs based on matching field values.
     * For each pair:
     *  - If `dty_ID` (resource pointer field) is specified, it calls `addPointerField()` to create/update the link.
     *  - If `trm_ID` (relation type) is specified, it's intended to create a relationship record (currently, this part seems to just set `$res=1` without full implementation for relationship record creation).
     * It handles progress tracking if a `session_id` is provided.
     *
     * @return array|false Returns an associative array `['added' => count, 'exist' => count, 'records_updated' => count]` on success.
     *                     `added`: New links/relationships created.
     *                     `exist`: Links/relationships that already existed and were skipped.
     *                     `records_updated`: Number of unique source records that had links added/updated.
     *                     Returns `false` on error (e.g., permission denied, invalid parameters, DB error, user termination).
     *                     Errors are added to the system object.
     */
    public function createRecordLinksByMatching(){

        // Can only be used by an administrator
        if(!$this->system->isAdmin()){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can add multiple links');
            return false;
        }

        $system = $this->system;
        $mysqli = $system->getMysqli();

        $data = $this->data;

        //dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
        //recIDs or rty_src, dty_src, rty_trg, dty_trg - matching conditions

        //1. Validate dty_ID or trm_ID from parameters
        $dty_ID = intval(@$data['dty_ID']);
        $trm_ID = intval(@$data['trm_ID']);


        //check that this is resouce filed
        if($dty_ID>0){

            if('resource' != $this->getDetailType($dty_ID)){
                $system->addError(HEURIST_INVALID_REQUEST, 'Wrong paramters for records link creation. Given field is not a record pointer ("resource") field');

                return false;
            }
        }elseif($trm_ID>0){
            //check that trm_ID is valid

        }



        $to_replace = (@$data['replace']==1);//replace existing link

        //2. Find matching pairs - [source rec_ID, target rec_ID]
        $data['pairs'] = 1;
        $pairs = recordSearchMatchedValues($system, $data);

        if(@$pairs['status']==HEURIST_OK){
            $pairs = $pairs['data'];
        }else{
            return false;
        }

        //3. Add pointer or create relationship record

        if($trm_ID>0){
            $system->defineConstant('RT_RELATION');
            $system->defineConstant('DT_PRIMARY_RESOURCE');
            $system->defineConstant('DT_TARGET_RESOURCE');
        }

        $keep_autocommit = mysql__begin_transaction($mysqli);

        $this->result_data = array('added'=>0,'exist'=>0,'records_updated'=>0);

        $res = true;

        $execution_counter = 0;
        $tot_count = count($pairs);

        $prev_rec_ID = 0;

        foreach($pairs as $row){

            $source_id = $row[0];
            $target_id = $row[1];

            if($trm_ID>0){
                $res = 1;
            }else{
                $res = addPointerField($system, $source_id, $target_id, $dty_ID, $to_replace);
            }
            if($res<0){
                $mysqli->rollback();
                $res = false;
                break;
            }elseif($res==0){
                $res = true;
                $this->result_data['exist']++;
            }else{
                $this->result_data['added']++;
                if($prev_rec_ID!=$source_id){
                    $prev_rec_ID=$source_id;
                    $this->result_data['records_updated']++;
                }
            }


            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Action has been terminated by user');
                    return false;
                }
            }
        }

        if($res){
            $mysqli->commit();
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
        
        //update titles for both source and target records
        foreach ([0, 1] as $idx) {
            $titleMask = null;
            foreach($pairs as $row){
                $recID = intval($row[$idx]);
                if ($recID <= 0) {
                    continue;
                }
                if(!$titleMask){
                    
                    $titleMask = mysql__select_value(
                        $mysqli,
                        'SELECT rty_TitleMask
                           FROM Records
                           JOIN defRecTypes ON rty_ID = rec_RecTypeID
                          WHERE rec_ID = '.$recID
                    );
                    
                }
                recordUpdateTitle($system, $recID, $titleMask, null);
            }    
        }    

        return $res?$this->result_data:false;
    }

    
    /**
     * Converts newline characters (`\n`, `\r\n`) to HTML line breaks (`<br>`)
     * and sequences of multiple spaces to non-breaking spaces (`&nbsp;`)
     * within a specified blocktext field for a batch of records.
     *
     * Key operations:
     * - Validates parameters, record accessibility, and ensures the target field (`dtyID`) is of type 'blocktext'.
     * - For each record and its values in the specified field:
     *   - Skips if the value already contains HTML tags.
     *   - Replaces double spaces with a non-breaking space followed by a regular space (`&nbsp; `).
     *   - Converts newlines to `<br>` using `nl2br()`.
     *   - If the conversion results in changes:
     *     - Replaces sequences of two or more `<br>` tags with `</p><p>` and wraps the whole value in `<p>...</p>`.
     *       Empty paragraphs resulting from this are removed.
     *     - Updates the `dtl_Value` and `dtl_Modified` for the detail.
     *     - Updates `rec_Modified` for the record.
     * - Assigns system tags if enabled and reports outcomes.
     *
     * Expected parameters in `$this->data`:
     * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
     *
     * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
     *                     'undefined' (no values in field or value unchanged/contained HTML), 'errors', and tag info).
     *                     Returns `false` on critical validation failure (e.g., invalid field type).
     */
    public function nl2brConversion(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace nl2br $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Multiline to HTML conversion only works on valid blocktext fields');
            return false;
        }

        //$keep_autocommit = mysql__begin_transaction($mysqli);

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            // Cycle through values
            while($values = $res->fetch_row()){

                $value = '';

                if($values[1] == strip_tags($values[1])){ // skip values with HTML
                    $value = nl2br(str_replace('  ', '&nbsp; ', $values[1]));
                    
                    if($value==$values[1]){
                        array_push($skipped_recs, $recID);
                        continue;
                    }
                    //replace repeated <br> with <p>
                    $value2 = preg_replace('#(?:<br\s*/?>\s*?){2,}#', '</p><p>', $value);
                    if($value!=$value2){
                        //remove empty para
                        $value = "<p>$value2</p>";    
                        $pattern = "/<p[^>]*>\s*<\/p[^>]*>/";
                        $value = preg_replace($pattern, '', $value);
                    }
                }

                if(empty($value)){ // ensure there is a value to save
                    array_push($skipped_recs, $recID);
                    continue;
                }

                // Update details value + modified
                $dtl_rec = array('dtl_ID' => intval($values[0]), 'dtl_Value' => $value, 'dtl_Modified' => $date_mode);

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                }
            }//for

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }

    
    public function fieldIncrementValue(){
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~increment value $dtyName $date_mode";

        // Check field is freetext or numeric
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || (!($fld_type == 'freetext' || $fld_type == 'integer') || $fld_type == 'float')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Increment value can be assigned either to freetext or numeric field');
            return false;
        }
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        
        $fillGaps = $this->data['fillgaps']==1;
        $resetIncValue = $this->data['continue']!=1;
        
        // need to add cycle by record type (rec_RecTypeID)
        $this->recIDs = mysql__select_list2($mysqli, 'SELECT rec_ID FROM Records where rec_ID in ('.implode(',',$this->recIDs).') ORDER BY rec_RecTypeID, rec_ID');
        
        
        // if $resetIncValue is false need to find max current value
        
        
        // Cycle through records
        foreach ($this->recIDs as $recID){
            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();
            
            // Cycle through values
            while($values = $res->fetch_row()){

                $value = $values[1];
                
                //value must either numeric or alphanumeric with suffix -123
                


                // Update details value + modified
                $dtl_rec = array('dtl_ID' => intval($values[0]), 'dtl_Value' => $value, 'dtl_Modified' => $date_mode);

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                }
            }
            
            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }        
        
        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag); //skipped
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }
    
}
?>
