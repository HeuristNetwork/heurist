<?php
/**
* DbDefRecStructure.php - Class DbDefRecStructure
*
* Operations for the `defRecStructure` table.
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
use hserv\entity\DbEntityBase;

require_once dirname(__FILE__).'/../structure/dbsTerms.php';

/**
* Class DbDefRecStructure
*
* Provides database access and operations for the `defRecStructure` table,
* which defines the structure of record types (i.e., which fields they include and how).
*
*/
class DbDefRecStructure extends DbEntityBase
{

    /**
    * Searches for record structure definitions (`defRecStructure` entries) based on criteria in `$this->data`.
    *
    * This method extends the base search functionality. It first calls `parent::search()`
    * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
    * common search parameters from `$this->data`.
    *
    * It then adds specific predicates for this entity:
    * - `rst_ID`: If provided in `$this->data['rst_ID']`.
    * - `rst_RecTypeID`: If provided in `$this->data['rst_RecTypeID']`.
    * - `rst_DetailTypeID`: If provided in `$this->data['rst_DetailTypeID']`.
    * - `rst_CalcFunctionID`: If provided in `$this->data['rst_CalcFunctionID']`.
    *
    * The fields returned in the search results depend on `$this->data['details']`:
    * - 'id': Returns only `rst_ID`.
    * - 'name': Returns `rst_ID`, `rst_DisplayName`.
    * - 'rectype': Returns `rst_ID`, `rst_RecTypeID`, `rst_DetailTypeID`.
    * - 'listshort': Returns key fields including a calculated `rst_DisplayName` (preferring `rst_DisplayName` over `dty_Name`) and `dty_Type`. Involves a JOIN with `defDetailTypes`.
    * - 'list': Returns an extended set of fields, including calculated display names and help texts (preferring `rst_` values over `dty_` ones). Involves a JOIN with `defDetailTypes`.
    * - 'structure': Returns a comprehensive set of fields for defining structure, using `dty_` values as fallbacks or for specific overrides. Involves a JOIN with `defDetailTypes`.
    * - 'full': Returns all fields defined in `$this->fieldNames` for this entity.
    * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected.
    *
    * Results are ordered by `rst_DisplayOrder ASC`.
    * For modes 'listshort', 'list', and 'structure', a `LEFT JOIN` with `defDetailTypes` is performed.
    *
    * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
    *                     typically including 'records', 'count', 'total_count', etc.
    *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
    *                     or if the database query fails.
    */
    public function search(){

        if(parent::search()===false){
            return false;
        }

        $is_structure = false;

        $this->searchMgr->addPredicate('rst_ID');
        $this->searchMgr->addPredicate('rst_RecTypeID', true);
        $this->searchMgr->addPredicate('rst_DetailTypeID', true);
        $this->searchMgr->addPredicate('rst_CalcFunctionID');
        $this->searchMgr->addPredicate('rst_OriginatingDBID');

        switch (@$this->data['details']){
            case 'id': 
                $this->searchMgr->setSelFields('rst_ID'); 
                break;
            case 'name': $this->searchMgr->setSelFields('rst_ID,rst_DisplayName'); break;
            case 'rectype': $this->searchMgr->setSelFields('rst_ID,rst_RecTypeID,rst_DetailTypeID'); break;
            case 'listshort':
                $is_structure = true;
                $this->searchMgr->setSelFields('rst_ID,rst_RecTypeID,rst_DetailTypeID,if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName,dty_Type');
                break;
            case 'list':
                $is_structure = true;
                $this->searchMgr->setSelFields('rst_ID,rst_RecTypeID,rst_DetailTypeID,rst_DisplayName'
            .',if(rst_DisplayHelpText is not null and (dty_Type=\'separator\' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText'
            .',if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription'
            .',rst_RequirementType, rst_DisplayOrder, rst_DisplayWidth, rst_DisplayHeight, rst_DefaultValue, rst_MaxValues'
            .',rst_CreateChildIfRecPtr, rst_PointerMode, rst_PointerBrowseFilter, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_SemanticReferenceURL, rst_TermsAsButtons, rst_CalcFunctionID, rst_EntryMask ');
                break;
            case 'full':
                $this->searchMgr->setSelFields(implode(',', $this->fieldNames));
                break;
            case 'raw':
                $this->searchMgr->setSelFields('*');
                break;
            case 'structure':
                $is_structure = true;

            $colNames = array("rst_RecTypeID", "rst_DetailTypeID",
            //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
            "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
            //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
            "if(rst_DisplayHelpText is not null and (dty_Type='separator' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
            //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
            "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
            "rst_RequirementType",
            "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue","rst_CalcFunctionID","rst_EntryMask",
            //XXX "rst_RecordMatchOrder"

            "rst_NonOwnerVisibility", "rst_Status", "rst_MayModify", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
            //here we check for an override in the recTypeStrucutre for displayGroup
            //XXX "dty_DetailTypeGroupID as rst_DisplayDetailTypeGroupID",
            //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
            "dty_JsonTermIDTree as rst_FilteredJsonTermIDTree",
            //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
            "dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs",
            "rst_CreateChildIfRecPtr", "rst_PointerMode", "rst_PointerBrowseFilter",
            "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified",
            "rst_SemanticReferenceURL","rst_TermsAsButtons",
            "dty_TermIDTreeNonSelectableIDs",
            "dty_FieldSetRectypeID",
            "dty_Type");

                $this->searchMgr->setSelFields(implode(',', $colNames));
                break;
            default:
                if(!isEmptyArray(@$this->data['details'])){ //specific list of fields
                    $fields = implode(',', $this->data['details']);
                }else{
                    $fields = @$this->data['details'];
                }
                $is_structure = strpos($fields, 'dty_') !== false;
                if(isEmptyStr($fields)){
                    $fields =  implode(',', $this->fieldNames);
                }
                $this->searchMgr->setSelFields($fields);
        }

        $orderby = 'rst_DisplayOrder ASC';

        $sup_tables = null;
        if($is_structure){
            $sup_tables = ' left join defDetailTypes on rst_DetailTypeID = dty_ID ';
        }

        return $this->searchMgr->composeAndExecute($orderby, $sup_tables);
    }

    //
    //
    //
    /**
     * Prepares record structure records before saving.
     *
     * This method determines if a record is new or an update by checking existing
     * `rst_ID` for the given `rst_DetailTypeID` and `rst_RecTypeID`.
     * It sets `rst_LocallyModified` accordingly for new or existing records.
     * It also sets default values for `rst_Status`, `rst_DisplayName` (if 'tabs'),
     * `rst_MaxValues`, and updates `rst_Modified`.
     *
     * @return bool True if preparation is successful, false otherwise.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //find real rst_ID
            $mysqli = $this->system->getMysqli();

            $row = mysql__select_row_assoc($mysqli,
                'SELECT rst_ID, rst_OriginatingDBID FROM '.$this->config['tableName']
                .SQL_WHERE
                .predicateId('rst_DetailTypeID',$this->records[$idx]['rst_DetailTypeID'])
                .predicateId('rst_RecTypeID',$this->records[$idx]['rst_RecTypeID'],SQL_AND));

            $isInsert = !(@$row['rst_ID']>0);

            if($isInsert){
                $this->records[$idx]['rst_ID'] = -1;
                $this->records[$idx]['rst_LocallyModified'] = 0;

            }else{
                $this->records[$idx]['rst_ID'] = $row['rst_ID'];
                $this->records[$idx]['rst_LocallyModified'] = ($row['rst_OriginatingDBID']>0)?1:0;
            }

            if(isEmptyStr(@$this->records[$idx]['rst_Status'])) {
                $this->records[$idx]['rst_Status'] = 'open';
            }
            if(isEmptyStr(@$this->records[$idx]['rst_MayModify'])) {
                $this->records[$idx]['rst_MayModify'] = 'open';
            }

            if($this->records[$idx]['rst_DefaultValue']=='tabs' && isEmptyStr(@$this->records[$idx]['rst_DisplayName'])){
                $this->records[$idx]['rst_DisplayName'] = 'Divider '.$idx;
            }

            if(@$this->records[$idx]['rst_MaxValues']==null ||
                !(intval(@$this->records[$idx]['rst_MaxValues'])>=0)) {$this->records[$idx]['rst_MaxValues'] = 1;}

            if(@$this->records[$idx]['rst_PointerMode']==null || $this->records[$idx]['rst_PointerMode']==''){
                $this->records[$idx]['rst_PointerMode'] = 'dropdown_add';
            }
                
            $this->records[$idx]['rst_Modified'] = date(DATE_8601);//reset

            $this->records[$idx]['is_new'] = $isInsert;
        }

        return $ret;

    }

    /**
     * Saves record structure definitions.
     *
     * After saving via `parent::save()`, this method reconstructs the `$savedRecIds`
     * array to contain `rst_DetailTypeID` values instead of `rst_ID` values.
     *
     * @return array|false An array of `rst_DetailTypeID`s for the saved records on success,
     *                     false on failure.
     */
    public function save(){

        $savedRecIds = parent::save();
        if($savedRecIds!==false){
            $savedRecIds = array();
            foreach($this->records as $rec_idx => $record){
                $savedRecIds[] = $this->records[$rec_idx]['rst_DetailTypeID'];
            }
        }
        return $savedRecIds;
    }

    /**
     * Deletes record structure entries.
     *
     * Can delete by `rst_ID` (if `recID` in `$this->data` is numeric),
     * by a composite key "rty_ID.dty_ID" (if `recID` is in this format),
     * or all entries for a specific `dtyID`.
     *
     * @param bool $disable_foreign_checks Unused in this implementation, but part of parent signature.
     * @return bool|array False on error or if no records found to delete, otherwise result of `parent::delete()`.
     */
    public function delete($disable_foreign_checks = false){

        $mysqli = $this->system->getMysqli();

        if(@$this->data['recID'] && strpos($this->data['recID'],'.')){
            list($rty_ID, $dty_ID) = explode('.', $this->data['recID']);

            $this->recordIDs = 0;
            if(is_numeric($rty_ID) && $rty_ID>0 && is_numeric($dty_ID) && $dty_ID>0){
                $this->recordIDs = mysql__select_value($mysqli,
                    'SELECT rst_ID FROM '.$this->config['tableName']
                    .SQL_WHERE
                    .predicateId('rst_DetailTypeID', $dty_ID)
                    .SQL_AND
                    .predicateId('rst_RecTypeID', $rty_ID));
            }
            if(!($this->recordIDs>0)){
                $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found for given record and field type');
                return false;
            }

            $this->recordIDs = array($this->recordIDs);

        }elseif(@$this->data['dtyID']){
            $dty_ID = $this->data['dtyID'];

            $this->recordIDs = null;
            if(is_numeric($dty_ID) && $dty_ID > 0){
                $this->recordIDs = mysql__select_list2($mysqli,  //always returns array
                    'SELECT rst_ID FROM '.$this->config['tableName']
                    .SQL_WHERE.predicateId('rst_DetailTypeID',$dty_ID));
            }
            if(empty($this->recordIDs)){
                $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found for field ID ' . $dty_ID);
                return false;
            }
        }

        $this->isDeleteReady = false;

        return parent::delete();
    }

    //
    // A. update order for fields in record type - see parameter "orders"
    // B. add set of new fields - see parameter "newfields"
    //
    /**
     * Performs batch actions on record structures.
     *
     * Supported actions:
     * - Adding new fields to a record type (if `newfields` is in `$this->data`).
     * - Setting the display order of fields in a record type (if `orders` is in `$this->data`).
     *
     * Requires `rtyID` (record type ID) to be present in `$this->data`.
     *
     * @return bool|array|null Result of the specific batch action (e.g., from `addNewFields` or `setNewFieldOrder`),
     *                         or false if `rtyID` is missing or no valid action is specified.
     */
    public function batch_action(){

        if(!(@$this->data['rtyID']>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Record type identificator not defined');
            return false;
        }
        if(@$this->data['newfields']){
            return $this->addNewFields();
        }elseif (@$this->data['orders']){
            return $this->setNewFieldOrder();
        }
    }

    //
    //
    //
    /**
     * Sets the display order for a list of fields within a specific record type.
     *
     * Expects `rtyID`, `recID` (array of dty_IDs), and `orders` (array of order values)
     * in `$this->data`.
     *
     * @return bool True on success, false if input is invalid or a database error occurs.
     */
    private function setNewFieldOrder(){

        $rty_ID = $this->data['rtyID'];

        //dty_ID
        $this->recordIDs = prepareIds(@$this->data['recID']);
        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid field identificators');
            return false;
        }

        $orders = prepareIds(@$this->data['orders'], true);
        if(empty($orders)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid values for fields order');
            return false;
        }

        $ret = true;
        $mysqli = $this->system->getMysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);

        foreach ($this->recordIDs as $idx => $dty_ID){

            $order = $orders[$idx];

            $query = 'UPDATE '.$this->config['tableName'].' SET rst_DisplayOrder='.$order
                    .SQL_WHERE
                    .predicateId('rst_DetailTypeID',$dty_ID)
                    .SQL_AND
                    .predicateId('rst_RecTypeID',$rty_ID);

            $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $this->system->addError(HEURIST_DB_ERROR, 'Can\'t set order for fields in rectord type #'.$rty_ID, $mysqli->error );
                    break;
                }
        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    /**
     * Adds new fields to the record type. If no fields exist, default fields are added.
     *
     * newfields=>array(
     *        fields=>  array of ids
     *        reqs=>   array of ids
     *        values=>  [dty_ID][fieldName]=>value
     *
     *
     * @return bool - Returns true if fields are successfully added, false otherwise.
     */
    private function addNewFields(){

        $rty_ID = intval($this->data['rtyID']);
        $newfields = @$this->data['newfields'];

        if (isEmptyArray($newfields) && !$this->addDefaultFields($rty_ID)){
            //if rt structure has zero fields adds 2 default fields: DT_NAME and DT_DESCRIPTION
            return false; // If there are no fields, adding default fields fails.
        }

        $fields = prepareIds($newfields['fields'], false);
        $reqs   = @$newfields['reqs'] ? $newfields['reqs'] : [];
        $newfields_values  = @$newfields['values'] ? $newfields['values'] : [];

        $provided_order = is_numeric($this->data['order']) ? intval($this->data['order']) : -1;
        $order = $provided_order >= 0 ? $provided_order : 0;

        $dt_fields = dbs_GetDetailTypes($this->system, $fields);
        $dt_fields = $dt_fields['typedefs'];
        $di = $dt_fields['fieldNamesToIndex'];

        $records = [];
        foreach($fields as $dty_ID){

            if(!@$dt_fields[$dty_ID]) {
                continue; //field not found defDetailTypes
            }

            $dt = $dt_fields[$dty_ID]['commonFields'];

            $recvalues = [
                'rst_ID'=> $dty_ID,
                'rst_RecTypeID'=> $rty_ID,
                'rst_DisplayOrder'=> $order,
                'rst_DetailTypeID'=> $dty_ID,
                'rst_DisplayName'=> @$newfields_values[$dty_ID]['dty_Name']
                                            ? $newfields_values[$dty_ID]['dty_Name']
                                            : $dt[$di['dty_Name']],
                'rst_DisplayHelpText'=> @$newfields_values[$dty_ID]['dty_HelpText']
                                            ? $newfields_values[$dty_ID]['dty_HelpText']
                                            : $dt[$di['dty_HelpText']],
                'rst_RequirementType'=> in_array($dty_ID,$reqs) ? 'required' : 'recommended',
                'rst_MaxValues'=> 1,
                'rst_DisplayWidth'=>($dt[$di['dty_Type']]=='date') ? 20 : 100
            ];


            if(@$dt[$di['dty_SemanticReferenceURL']]){
                $recvalues['rst_SemanticReferenceURL'] = $dt[$di['dty_SemanticReferenceURL']];
            }
            if(@$newfields_values[$dty_ID]['dty_DefaultValue']){
                $recvalues['rst_DefaultValue'] = $newfields_values[$dty_ID]['dty_DefaultValue'];
            }elseif(@$newfields_values[$dty_ID]['rst_DefaultValue']){
                $recvalues['rst_DefaultValue'] = $newfields_values[$dty_ID]['rst_DefaultValue'];
            }

            $records[] = $recvalues;

            // Increment the order
            $order = $provided_order >= 0 ? $order + 1 : $order + 10;
        }

        if($provided_order){

            $query = "SELECT rst_DetailTypeID, rst_RecTypeID, rst_DisplayName FROM defRecStructure WHERE rst_RecTypeID = {$rty_ID} AND rst_DisplayOrder >= {$provided_order} ORDER BY rst_DisplayOrder";
            $rst_fields = mysql__select_assoc($this->system->getMysqli(), $query, 0);

            foreach($rst_fields as $rst_field){
                $rst_field['rst_DisplayOrder'] = $order;
                $records[] = $rst_field;
                $order = $provided_order >= 0 ? $order + 1 : $order + 10;
            }
        }

        if(empty($records)){
            return false;
        }

        $this->data['fields'] = $records;
        $this->is_addition = true;
        return $this->save();
    }


    /**
     * Adds default fields (DT_NAME and DT_DESCRIPTION) to the record type if no fields exist.
     *
     * @param int $rty_ID - The record type ID.
     * @return bool - Returns true if default fields are added, false otherwise.
     */
    private function addDefaultFields($rty_ID) {
        $mysqli = $this->system->getMysqli();

        $fieldCount = mysql__select_value(
            $mysqli,
            'SELECT count(*) FROM ' . $this->config['tableName'] . ' WHERE rst_RecTypeID=' . intval($rty_ID)
        );

        if ($fieldCount === 0) {
            $this->data['newfields']['fields'] = [DT_NAME, DT_DESCRIPTION];
            $this->data['newfields']['reqs'] = [DT_NAME];
            return true;
        } else {
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid values for new fields');
            return false;
        }
    }

    //
    // Counts:
    //  rectype_field_usage: count all bits of data for all records of the provided record type
    //
    /**
     * Retrieves counts related to record structure and field usage.
     *
     * Currently supports 'rectype_field_usage' mode:
     * Counts the usage of each detail type (field) for a given record type (`rtyID`).
     * This includes counts from `recDetails` and also calculates usage for `relmarker` type fields
     * by checking `recLinks`.
     * If `get_meta_counts` is requested, it also includes total record count, URL count, and tag count for the record type.
     *
     * @return array|false|null An associative array 현실 `[dty_ID => usage_count]` or `['rec_ID' => count, ...]`
     *                          if `get_meta_counts` is true. Returns `[0]` if no usage found.
     *                          Returns false on database error or invalid input.
     *                          Returns null if mode is not 'rectype_field_usage'.
     */
    public function counts(){

        $mysqli = $this->system->getMysqli();
        $res = null;

        if(@$this->data['mode'] == 'rectype_field_usage'){

            $rty_ID = intval(@$this->data['rtyID'], 10);

            // For checking relation types
            $defTerms = dbs_GetTerms($this->system);
            $defTerms = new \DbsTerms($this->system, $defTerms);

            if(isset($rty_ID) && is_numeric($rty_ID) && $rty_ID > 0){

                // Get count for all details, except relmarkers
                $query = 'SELECT dtl_DetailTypeID, count(dtl_ID) '
                    . 'FROM recDetails '
                    . 'INNER JOIN Records ON rec_ID=dtl_RecID '
                    . 'WHERE rec_RecTypeID=' . $rty_ID . ' '
                    . 'GROUP BY dtl_DetailTypeID';
                $detail_usage = mysql__select_assoc2($mysqli, $query);// [ dty_ID1 => count1, ... ]
                if($detail_usage){
                    $res = $detail_usage;
                }elseif(empty($mysqli->error)){
                    $res = array();
                }else{
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve field usages for record type #'.$rty_ID, $mysqli->error);
                    return false;
                }

                // Check for relmarkers
                $query = 'SELECT dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree '
                    . 'FROM defRecStructure '
                    . 'INNER JOIN defDetailTypes ON rst_DetailTypeID=dty_ID '
                    . 'WHERE dty_Type="relmarker" AND rst_RecTypeID=' . $rty_ID;
                $relmarker_filters = mysql__select_assoc($mysqli, $query);// [ relmarker_fld_ID1 => [rty_id_list1, trm_id1], ... ]
                if($relmarker_filters && !empty($relmarker_filters)){

                    // Retrieve record ids that are relevant
                    $query = 'SELECT DISTINCT rec_ID FROM Records, recLinks WHERE rec_RecTypeID=' . $rty_ID
                        . ' AND rl_RelationID > 0 AND (rl_SourceID=rec_ID OR rl_TargetID=rec_ID)';
                    $ids = mysql__select_list2($mysqli, $query);// returns array of rec ids
                    if(is_array($ids) && !empty($ids)){

                        $rec_ids = implode(',', $ids);
                        foreach ($relmarker_filters as $dty_id => $fld_details) {

                            $allowed_recs = array();// records that meet the rectype requirement
                            $not_allowed_recs = array();// records that don't meet the rectype requirement
                            $count = 0;

                            // Get possible related types (relation terms)
                            $terms = $defTerms->treeData($fld_details['dty_JsonTermIDTree'], 'set');
                            // Split possible related rectypes
                            $rectypes = explode(',', $fld_details['dty_PtrTargetRectypeIDs']);
                            $allow_all = empty($rectypes);

                            if(is_array($terms) && !empty($terms)){
                                // Retrieve relmarker count - from
                                $query = 'SELECT rl_TargetID '
                                    . 'FROM recLinks '
                                    . 'WHERE rl_RelationTypeID IN (' . implode(',', $terms) . ') AND rl_SourceID IN ('. $rec_ids .')';
                                $rel_usage_to = mysql__select_list2($mysqli, $query);// returns count
                                if(!empty($mysqli->error)){
                                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve relationship marker usage for field #'.$dty_id.' from record type #'.$rty_ID, $mysqli->error);
                                    return false;
                                }

                                foreach($rel_usage_to as $rec_id){
                                    if(in_array($rec_id, $allowed_recs)){
                                        $count ++;
                                        continue;
                                    }elseif(in_array($rec_id, $not_allowed_recs)){
                                        continue;
                                    }

                                    $check_res = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = $rec_id");
                                    if(!empty($mysqli->error)){
                                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve record type id for record #'.$rec_id, $mysqli->error);
                                        return false;
                                    }

                                    if($allow_all || in_array($check_res, $rectypes)){
                                        $count ++;
                                        $allowed_recs[] = $rec_id;
                                    }else{
                                        $not_allowed_recs[] = $rec_id;
                                    }
                                }
                            }

                            $res[$dty_id] = $count;
                        }
                    }elseif(!empty($mysqli->error)){
                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve related records for counting relationship marker field usage for record type #'.$rty_ID, $mysqli->error);
                        return false;
                    }
                }elseif(!empty($mysqli->error)){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot check record type #'.$rty_ID.' for relationship marker fields', $mysqli->error);
                    return false;
                }

                if(@$this->data['get_meta_counts'] == 1){ // Include count of rec_ field counts

                    // Get number of records
                    $query = "SELECT count(rec_ID) "
                        . "FROM Records "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $rec_counts = mysql__select_value($mysqli, $query);
                    $res['rec_ID'] = !$rec_counts ? 0 : $rec_counts;

                    // Get number of rec URLs
                    $query = "SELECT count(rec_URL) "
                        . "FROM Records "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $url_counts = mysql__select_value($mysqli, $query);
                    $res['rec_URL'] = !$url_counts ? 0 : $url_counts;

                    // Get number of records with tags
                    $query = "SELECT DISTINCT count(rtl_RecID)"
                        . "FROM usrRecTagLinks "
                        . "INNER JOIN Records ON rec_ID = rtl_ID "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $tag_count = mysql__select_value($mysqli, $query);
                    $res['rec_Tags'] = !$tag_count ? 0 : $tag_count;
                }

                if(!$res || empty($res)){
                    $res = [0];
                }
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid record type id provided '.$rty_ID);
                $res = false;
            }
        }

        return $res;
    }
}
?>
