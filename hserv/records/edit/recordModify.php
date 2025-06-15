<?php
/**
* recordModify.php - Library to create/update/delete heurist (user data) records
*
* recordAdd  - create temporary record for given user
* recordSave - Save record
* recordDuplicate - Duplicate record
* recordDelete
*
* isWrongAccessRights - validate parameter values
* recordCanChangeOwnerwhipAndAccess  - Verifies access right value and is the current user able to change ownership for given record
*
* recordUpdateCalcFields
* recordUpdateTitle
* recordUpdateOwnerAccess
* _prepareDetails - validate records detail (need to combine with validators in fileParse)
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records\edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
* 
* @todo convert to Class
*/
use hserv\entity\DbRecUploadedFiles;
use hserv\entity\DbDefRecTypes;
use hserv\utilities\USanitize;
use hserv\utilities\UImage;
use hserv\utilities\Temporal;
use hserv\structure\ConceptCode;
use hserv\report\ReportRecord;

require_once dirname(__FILE__).'/recordTitleMask.php';
require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

require_once dirname(__FILE__).'/../../../hserv/records/indexing/elasticSearch.php';

require_once dirname(__FILE__).'/../../report/smartyInit.php';


global $useNewTemporalFormatInRecDetails;
global $recstructures, $detailtypes, $terms, $block_swf_email;

$recstructures = array();
$detailtypes   = array();
$terms         = null;
$useNewTemporalFormatInRecDetails = false;

$block_swf_email = false;

/**
* Returns default values for rec_NonOwnerVisibility, rec_NonOwnerVisibilityGroups, rec_OwnerUGrpID.
* These defaults are determined by a hierarchy: user preferences, then record values (if provided),
* then system settings, and finally hardcoded defaults.
*
* @param \hserv\System $system The Heurist system object.
* @param array|null $record Optional. An associative array representing the record.
*                           May contain keys like 'RecTypeID', 'NonOwnerVisibility',
*                           'NonOwnerVisibilityGroups', 'OwnerUGrpID'.
*                           Values can be prefixed with 'rec_'.
* @return array An associative array with the following keys:
*               - 'rectype': (int) The determined record type ID.
*               - 'owner_grps': (array) An array of owner group IDs.
*               - 'access': (string) The determined non-owner visibility (e.g., 'viewable', 'public').
*               - 'access_grps': (string|null) Comma-separated string of group IDs if access is 'viewable', otherwise null.
*/
function recordAddDefaultValues($system, $record=null){

    $sysvals = null;
    $rectype = null;
    $owner_grps = array();
    $ownerid = -1;
    $access = null;
    $access_grps = null;


    //obtain user preferences values
    $addRecDefaults = $system->userGetPreference('record-add-defaults');
    if ($addRecDefaults){
        if (@$addRecDefaults[0]){
            $userDefaultRectype = intval($addRecDefaults[0]);
        }
        if (@$addRecDefaults[1]!=null){ //default ownership
            if(is_string($addRecDefaults[1]) &&  $addRecDefaults[1]!=''){
                $userDefaultOwnerGroupID = explode(',', $addRecDefaults[1]);
            }elseif(is_numeric($addRecDefaults[1])){
                $userDefaultOwnerGroupID = intval($addRecDefaults[1]);
            }
        }
        if (@$addRecDefaults[2]){
            $userDefaultAccess = $addRecDefaults[2];
        }
        if (@$addRecDefaults[4]){
            $userDefaultAccessGroups = $addRecDefaults[4];
        }
    }

    //from record
    if(@$record){
        //it is allowed with prefix rec_ and without
        foreach ($record as $key=>$val){
            if(strpos($key,'rec_')===0){
                $record[substr($key,4)] = $val;
                unset($record[$key]);
            }
        }

        $rectype = @$record['RecTypeID'];
        $access = @$record['NonOwnerVisibility'];
        $access_grps = @$record['NonOwnerVisibilityGroups'];
        $ownerid = (empty(@$record['OwnerUGrpID']) && @$record['OwnerUGrpID']!=0) ? -1 : $record['OwnerUGrpID'];

        if($ownerid == 'current_user'){
            $ownerid = $system->getUserId();
        }else {
            $ownerid = prepareIds($ownerid, true);
        }

        $rectype = ConceptCode::getRecTypeLocalID($rectype);
    }


    // RECTYPE
    $rectype = intval($rectype);
    if(!$rectype && isset($userDefaultRectype)){
        $rectype = $userDefaultRectype;
    }
    // OWNERSHIP
    if(($ownerid == -1 || empty($ownerid)) && isset($userDefaultOwnerGroupID)){ // from user preferences
        $ownerid = is_array($userDefaultOwnerGroupID)?$userDefaultOwnerGroupID:array($userDefaultOwnerGroupID);
    }
    if(!is_array($ownerid) || !($ownerid[0]>=0)){
        if(!$sysvals) {$sysvals = $system->settings->get();}
        $ownerid = @$sysvals['sys_NewRecOwnerGrpID'];//from database properties
    }
    if(!(is_array($ownerid) && !empty($ownerid)) || !($ownerid[0]>=0)){
        $ownerid = $system->getUserId();//by default current user
    }
    if(is_array($ownerid)){
        $owner_grps = $ownerid;
    }elseif($ownerid>=0){
        $owner_grps = array($ownerid);
    }

    // NON OWNER VISIBILITY
    if($access==null && isset($userDefaultAccess)) {//from user prefs
        $access = $userDefaultAccess;
    }
    if(!$access){
        $sysvals = $system->settings->get();
        $access = @$sysvals['sys_NewRecAccess'];//from db properties
    }
    if(!$access){
        $access = 'viewable';// default value
    }
    //access groups
    if($access!='viewable'){
        $access_grps = null;
    }elseif($access_grps==null && isset($userDefaultAccessGroups)){
        $access_grps = $userDefaultAccessGroups;
    }

        return array('rectype'=>$rectype, 'owner_grps'=>$owner_grps, 'access'=>$access, 'access_grps'=>$access_grps );
}

/**
* Creates a new record in the database.
*
* This function handles the creation of a new record based on the provided data.
* It determines default values for ownership and visibility using `recordAddDefaultValues`.
* It performs permission checks to ensure the current user is allowed to add records
* and to own the record with the specified (or defaulted) ownership.
* For CMS menu record types, it forces public access and ownership by group 1.
* If a record ID is provided in `$record['ID']` (e.g., during CSV import with predefined IDs),
* that ID is used; otherwise, a new ID is auto-generated.
* After inserting the base record into the `Records` table, it updates associated user/group
* permissions using `updateUsrRecPermissions`.
*
* @param \hserv\System $system The Heurist system object.
* @param array $record An associative array containing the initial data for the new record.
*                      Expected keys include:
*                      - 'RecTypeID': (int, required) The ID of the record type.
*                      - 'OwnerUGrpID': (optional) Desired owner user/group ID(s). Can be 'current_user'.
*                      - 'NonOwnerVisibility': (optional) Desired visibility (e.g., 'public', 'viewable').
*                      - 'NonOwnerVisibilityGroups': (optional) Group IDs if visibility is 'viewable'.
*                      - 'URL': (optional) URL for the record.
*                      - 'ScratchPad': (optional) Scratchpad text for the record.
*                      - 'AddedByImport': (optional, bool) Flag if the record is added via import.
*                      - 'FlagTemporary': (optional, bool) Flag if the record is temporary.
*                      - 'Title': (optional, string) Initial title for the record.
*                      - 'ID': (optional, int) Predefined ID for the record (used in specific import scenarios).
*                      - 'swf': (optional, bool) If true, indicates ownership is set by workflow rules, bypassing some ownership checks.
*                      Other keys passed to `recordAddDefaultValues` might also influence behavior.
* @param bool $return_id_only Optional. If true, the function returns an array with only the new record ID
*                               and status. If false (default), it retrieves and returns the full new record
*                               data (including structure) via `recordSearch`.
* @return array|false Returns an associative array on success:
*                     If `$return_id_only` is true: `['status' => HEURIST_OK, 'data' => newRecordID]`
*                     If `$return_id_only` is false: The result of `recordSearch` for the new record.
*                     Returns false if the user is not allowed to add records, or an error array
*                     from the system object on other failures (e.g., invalid record type, DB error, permission issues).
*/
function recordAdd($system, $record, $return_id_only=false){

    // Check that the user is allowed to create records
    $is_allowed = userCheckPermissions($system, 'add');
    if(!$is_allowed){
        return false;
    }

    $mysqli = $system->getMysqli();

    $def_params = recordAddDefaultValues($system, $record);

    $rectype = $def_params['rectype'];
    $owner_grps = $def_params['owner_grps'];
    $access = $def_params['access'];
    $access_grps = $def_params['access_grps'];

    if (!($rectype && dbs_GetRectypeByID($mysqli, $rectype)) ) {
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined or wrong ('.$rectype.')');
    }

    // for CMS rectypes by default public and owner is Database owners group
    if ($system->defineConstant('RT_CMS_MENU') && $rectype==RT_CMS_MENU)
    {
        $access= 'public';
        $owner_grps = array(1);//database manager group
    }

    //$record['swf'] - ownership is set from swf rules
    if (!(@$record['swf'] || $system->isAdmin() || $system->isMember($owner_grps) || $system->isGuestUser() )){
        $system->addError(HEURIST_REQUEST_DENIED,
            'Current user does not have sufficient authority to add record with default ownership. '
            .'User must be member of the group that will own this record', 'Default ownership: '.implode(',', $owner_grps));
        return false;
    }
    //check that $owner_grps exists
    $usr_exists = mysql__select_value($mysqli, 'SELECT ugr_ID FROM sysUGrps WHERE ugr_ID='.intval($owner_grps[0]));
    if($usr_exists==null){
        $system->addError(HEURIST_REQUEST_DENIED,
'Proposed record ownership for record addition is invalid. Most probably the specified group or user has been deleted, or a non-existent  user or group has been specified.'
.'<br><br>Change the specified ownership  in the record addition link in the custom report or website, or in setup of the workflow (in Design menu).',
'Proposed ownership: '.implode(',', $owner_grps));
        return false;
    }


    if(isWrongAccessRights($system, $access)){
        return $system->getError();
    }


    //ActioN!

    if(is_numeric(@$record['ID']) && @$record['ID']>0){
        //case: insert csv with predefined ID
        $rec_id = $record['ID'];
        $recid1 = 'rec_ID, ';
        $recid2 = '?, ';
    }else{
        $rec_id = 0;
        $recid1 = '';
        $recid2 = '';
    }

    $query = "INSERT INTO Records
    ($recid1 rec_AddedByUGrpID, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility,"
    ."rec_URL, rec_ScratchPad, rec_Added, rec_Modified, rec_AddedByImport, rec_FlagTemporary, rec_Title) "
    ."VALUES ($recid2 ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($query);

    $currentUserId = $system->getUserId();
    $rec_url  = USanitize::sanitizeURL(@$record['URL']);

    $rec_scr  = @$record['ScratchPad'];
    $rec_imp  = (@$record['AddedByImport']?1:0);
    $rec_temp = (@$record['FlagTemporary']?1:0);
    $rec_title = @$record['Title']==null?'':$record['Title'];

    //DateTime('now')->format(DATE_8601) is same as date(DATE_8601)
    $data_add = date(DATE_8601);

    if(is_numeric(@$record['ID']) && @$record['ID']>0){
        //case: insert csv with predefined ID
        $stmt->bind_param('iiiisssssiis', $rec_id, $currentUserId, $rectype, $owner_grps[0], $access,
            $rec_url, $rec_scr, $data_add, $data_add, $rec_imp, $rec_temp, $rec_title);
    }else{
        $stmt->bind_param('iiisssssiis', $currentUserId, $rectype, $owner_grps[0], $access,
            $rec_url, $rec_scr, $data_add, $data_add, $rec_imp, $rec_temp, $rec_title);
    }
    $stmt->execute();
    $newId = $stmt->insert_id;
    $syserror = $mysqli->error;
    $stmt->close();


    if(!$newId){
    //HEURIST_DB_ERROR
        $response = $system->addError(HEURIST_ACTION_BLOCKED , 'Cannot add record '.$syserror, $syserror);

    }else {

        array_shift( $owner_grps );//remove first
        if($access_grps!=null || (is_array($owner_grps) && !empty($owner_grps))){
            updateUsrRecPermissions($mysqli, $newId, $access_grps, $owner_grps);
        }

        if($return_id_only){

            $response = array("status"=>HEURIST_OK, "data"=> $newId);

        }else{

            $params = array("q"=>"ids:".$newId, 'detail'=>'complete', "w"=>"e");
            //retrieve new record with structure
            $response = recordSearch($system, $params);
        }
    }
    return $response;
}

/**
* Save record
*   1) _prepareDetails
*   2) add or update header
*   3) remove old details, add new details
*   4) recordUpdateCalcFields
*   5) recordUpdateTitle
*
* This function is central to creating and updating records. It handles:
* - Captcha validation.
* - User permission checks for editing.
* - Data preparation: decoding details if needed, normalizing record keys.
* - Validation of details via `_prepareDetails`.
* - Special handling for different update modes (`$update_mode`).
* - Workflow stage processing via `recordWorkFlowStage`, including ownership/visibility changes and email notifications.
* - Transaction management for database operations.
* - Insertion of new records (delegating to `recordAdd` if `$recID` < 1) or updating existing record headers.
* - Deletion of old details and insertion of new/updated details.
* - Management of parent-child relationships (adding/removing reverse pointers).
* - Updating calculated fields, title masks, and temporary flags for the record and related/linked records.
* - Sending bug report update notifications if applicable.
*
* @param \hserv\System $system The Heurist system object.
* @param array $record An associative array containing the record data.
*                      Key properties:
*                      - 'ID': (int) The record ID. If 0 or less, a new record is created.
*                      - 'RecTypeID': (int) The record type ID.
*                      - 'OwnerUGrpID': (optional) User/group ID(s) for ownership.
*                      - 'NonOwnerVisibility': (optional) Visibility string (e.g., 'public', 'viewable').
*                      - 'NonOwnerVisibilityGroups': (optional) Group IDs for 'viewable' access.
*                      - 'URL': (optional) Record URL.
*                      - 'ScratchPad': (optional) Scratchpad text.
*                      - 'FlagTemporary': (optional) Temporary flag (0 or 1).
*                      - 'Title': (optional) Record title (used if title mask fails or is not present).
*                      - 'details': (array|string) Associative array or JSON string of record details.
*                                   Format: `['dty_ID' => [value1, value2...], ...]` or `['t:dty_ID' => [value1, ...], ...]`.
*                      - 'details_encoded': (optional, int) 1 or 2 if details are URL+JSON encoded, 3 if JSON encoded.
*                      - 'details_visibility': (optional, array|string) Visibility settings for details, mirrors `details` structure.
*                      - 'AddedByImport': (optional, int) Import mode (0=normal, 1=import, 2=FAIMS/Zotero import with less validation).
*                      - 'no_validation': (optional, string|bool) If 'ignore_all', skips all validation. If true, skips resource validation.
*                      - 'Captcha': (optional) User's captcha response.
*                      - 'swf': (optional) True if ownership is managed by workflow, bypassing some checks.
* @param bool $use_transaction Optional. If true (default), database operations are wrapped in a transaction.
* @param bool $suppress_parent_child Optional. If true, suppresses automatic updates to parent/child reverse pointers. Defaults to false.
* @param int $update_mode Optional. Defines how to handle existing data when updating a record:
*                         - 0 or 1: Overwrite completely. All existing details are replaced with new ones. (Default)
*                         - 2: Add new values, don't delete existing. Duplicates (exact value match for same field) are ignored.
*                         - 3: Add new values only if the field is currently empty.
*                         - 4: Replace existing values with new ones; if a new value isn't supplied for a field that had a value, the old value is kept.
* @param int $total_record_count Optional. The total number of records being saved in a batch operation.
*                                Used to limit workflow email notifications to only the first record in a batch. Defaults to 1.
* @return array An associative array indicating success or failure:
*               On success: `['status' => HEURIST_OK, 'data' => (int)$recID, 'rec_Title' => (string)$newTitle, 'affectedRty' => (int)$rectype, 'issues' => ['parents' => [...], 'entryMask' => [...]]]`
*                           `issues` contains arrays of parent records that were re-linked or entry mask validation failures.
*               On failure: An error array from the system object (e.g., `['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Error details']`).
*/
function recordSave($system, $record, $use_transaction=true, $suppress_parent_child=false, $update_mode=0, $total_record_count=1){

    global $block_swf_email, $useNewTemporalFormatInRecDetails;

    //check capture for newsletter subscription
    if (@$record['Captcha'] && @$_SESSION["captcha_code"]){

        $is_InValid = (@$_SESSION["captcha_code"] != @$record['Captcha']);

        if (@$_SESSION["captcha_code"]){
            unset($_SESSION["captcha_code"]);
        }
        if(@$record['Captcha']){
            unset($record['Captcha']);
        }

        if($is_InValid) {
            return $system->addError(HEURIST_ACTION_BLOCKED,
                'Are you a bot? Please enter the correct answer to the challenge question');
        }else{
            if($system->getUserId()<1){ //if captcha is valid allow
                $system->setCurrentUser(array('ugr_ID'=>5, 'ugr_FullName'=>'Guest'));
            }
        }
    }

    // Check that the user is allowed to edit records
    $is_allowed = userCheckPermissions($system, 'edit');
    if(!$is_allowed){
        return false;
    }

    $recID = intval(@$record['ID']);
    if ( @$record['ID']!=='0' && @$record['ID']!==0 && $recID==0 ) {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
    }

    $mysqli = $system->getMysqli();

    //it is allowed with prefix rec_ and without
    foreach ($record as $key=>$val){
        if(strpos($key,'rec_')===0){
            $record[substr($key,4)] = $val;
            unset($record[$key]);
        }
    }

    $useNewTemporalFormatInRecDetails = ($system->settings->get('sys_dbSubSubVersion')>=14);


    //0 normal, 1 import, 2 - faims or zotero import (add without recstructure check)
    $modeImport = @$record['AddedByImport']?intval($record['AddedByImport']):0;

    $validation_mode = 2; //check everything

    if(@$record['no_validation']==='ignore_all'){
        $validation_mode = 0; //no validation at all
    }elseif($modeImport==2 || @$record['no_validation']){
        $validation_mode = 1; //don't validate resources
    }

    if(@$record['no_validation']!=null){
        unset($record['no_validation']);
    }

    $rectype = intval(@$record['RecTypeID']);
    $detailValues = null;

    if ($rectype && !dbs_GetRectypeByID($mysqli, $rectype))  {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record type is wrong");
    }

    $is_insert = ($recID<1);
    $is_save_new_record = false;
    $missingParents = [];
    $entryMaskIssues = [];

    // recDetails data
    if ( @$record['details'] ) {

        if(@$record['details_encoded']==1 || @$record['details_encoded']==2){
            //$record['details'] = json_decode(str_replace( ' xxx_style=', ' style=',
            //            str_replace( '^^/', '../', urldecode($record['details']))), true);
            $record['details'] = json_decode(urldecode($record['details']), true);
            $record['details_visibility'] = json_decode(urldecode($record['details_visibility']), true);
        }elseif(@$record['details_encoded']==3){
            $record['details'] = json_decode($record['details'], true);
            $record['details_visibility'] = json_decode($record['details_visibility'], true);
        }

        $detailValues = _prepareDetails($system, $rectype, $record, $validation_mode, $recID, $modeImport);
        if(!$detailValues){
            return $system->getError();
        }

        //prepare header and details for special update modes
        if(!$is_insert && $update_mode>1){ //if 0 or 1 - it overwrites current version of record completely
            $detailValues = prepareRecordForUpdate($system, $record, $detailValues, $update_mode);

            if($update_mode!=1){ //1 - always overwrite
                $record_orig = recordSearchByID($system, $record['ID'], false);
                //keep previous header values if no new value supplied
                if( @$record['URL']==null || @$record['URL']==''
                || (@$record_orig['rec_URL'] && $update_mode==4)) //retain
                {
                    $record['URL'] = @$record_orig['rec_URL'];
                }
                if(@$record['ScratchPad']==null || @$record['ScratchPad']==''
                || (@$record_orig['rec_ScratchPad'] && $update_mode==4))
                {
                    $record['ScratchPad'] = @$record_orig['rec_ScratchPad'];
                }
            }
        }

        if(!$is_insert){
            $missingParents = validateParentRecords($system, $record, $detailValues);
        }

    }  else {
        return $system->addError(HEURIST_INVALID_REQUEST, "Details not defined");
    }



    $system->defineConstant('RT_RELATION');
    $system->defineConstant('DT_PARENT_ENTITY');

    // if source of target of relationship record is temporal - relationship is temporal as well
    if($record['RecTypeID']==RT_RELATION && @$record['FlagTemporary']!=1){
        $system->defineConstant('DT_PRIMARY_RESOURCE');
        $system->defineConstant('DT_TARGET_RESOURCE');

        $recids = array();
        foreach ($detailValues as $values) {
            $dtyID = $values['dtl_DetailTypeID'];
            if(($dtyID==DT_PRIMARY_RESOURCE || $dtyID==DT_TARGET_RESOURCE) && @$values['dtl_Value']){
                $recids[] = $values['dtl_Value'];
            }
        }

        $query = 'SELECT rec_FlagTemporary FROM Records where rec_FlagTemporary=1 AND rec_ID in ('
        .implode(',',$recids).')';
        if(mysql__select_value($mysqli, $query)>0){
            $record['FlagTemporary'] = 1;
        }
    }elseif(!$is_insert) {

        //check if previous FlagTemporary is 1
        if($system->defineConstant('TRM_SWF_ADDED')){
            $query = 'SELECT rec_FlagTemporary FROM Records WHERE rec_ID='.$recID;
            $is_save_new_record = (mysql__select_value($mysqli, $query)==1);
        }

        $record['FlagTemporary'] = 0;
    }

    //workflow stages
    $new_swf_stage = 0;
    $swf_emails = null;
    $swf_body = null;
    $stage_field_idx = -1;
    $is_new_record = $is_insert || $is_save_new_record;
    if($record['FlagTemporary']!=1 && $system->defineConstant('DT_WORKFLOW_STAGE')){

        if($modeImport > 0 && $system->defineConstant('TRM_SWF_IMPORT')){
            //hardcoded term id for "import" stage

            $recID = abs(intval(@$record['ID']));
            $existing_swf = mysql__select_value($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $recID AND dtl_DetailTypeID = " . DT_WORKFLOW_STAGE);

            $new_swf_stage = !$existing_swf ? TRM_SWF_IMPORT : 0;
        }else{
            foreach ($detailValues as $idx=>$values) {
                if($values['dtl_DetailTypeID']==DT_WORKFLOW_STAGE){
                    $stage_field_idx = $idx;
                    $new_swf_stage = @$values['dtl_Value'];
                    break;
                }
            }
            if($is_save_new_record && !($new_swf_stage>0)){
                $new_swf_stage = TRM_SWF_ADDED;
            }
        }
        if($new_swf_stage>0){
            // set $record onwership and visibility
            // and assign $record['swf'] = true, to avoid recordCanChangeOwnerwhipAndAccess
            // returns array( new_value, curr_value, emails )
            $swf_res = recordWorkFlowStage($system, $record, $new_swf_stage, $is_new_record);

            $new_swf_stage = @$swf_res['new_value'];
            if($new_swf_stage==0){ //not allowed - keep old stage
                if($stage_field_idx>=0 && @$swf_res['curr_value']>0){
                    $detailValues[$stage_field_idx]['dtl_Value'] = $swf_res['curr_value'];
                }
            }else{
                $swf_emails = @$swf_res['emails'];
                $swf_body = @$swf_res['body'];
                if($stage_field_idx<0){
                    array_push($detailValues,array('dtl_DetailTypeID'=>DT_WORKFLOW_STAGE, 'dtl_Value'=>$new_swf_stage));
                }
            }
        }
    }

    if($is_insert){   // ADD NEW RECORD

        //add with predifined id - this is is case happens only in import csv
        //to keep H-ID defined in source csv
        if($recID<0){
            $record['ID'] = abs($recID);
        }

        // start transaction
        if($use_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        $response = recordAdd($system, $record, true);
        if($response['status'] == HEURIST_OK){
            $recID = intval($response['data']);
        }else{
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $response;
        }

    }else{  //UPDATE EXISTING ONE

        $owner_grps = prepareIds(@$record['OwnerUGrpID'], true);//list of owner groups

        $access = @$record['NonOwnerVisibility'];
        $rectypes = array();

        if(!@$record['swf'] && !recordCanChangeOwnerwhipAndAccess($system, $recID, $owner_grps, $access, $rectypes)){
            return $system->getError();
        }

        // start transaction
        if($use_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        if(!$modeImport) {
            mysql__supress_trigger($mysqli, true);
        }

        $query = 'UPDATE Records set rec_Modified=?, rec_RecTypeID=?, rec_OwnerUGrpID=?, rec_NonOwnerVisibility=?,rec_FlagTemporary=? ';

        $rec_mod = date(DATE_8601);
        $rec_temp = (@$record['FlagTemporary']==1)?1:0;

        //$stmt->bind_param('siisssi', $rec_mod, $rectype, $owner_grps[0], $access, $rec_temp, $rec_url, $rec_spad);

        $params = array('siisi', $rec_mod, $rectype, $owner_grps[0], $access, $rec_temp);

        $rec_url = USanitize::sanitizeURL(@$record['URL']);
        if($rec_url || (array_key_exists('URL', $record) && $update_mode < 2)){
            $params[0] = $params[0].'s';
            $params[] = $rec_url;
            $query = $query.', rec_URL=?';
        }
        $rec_spad = @$record['ScratchPad'];
        if($rec_spad || (array_key_exists('ScratchPad', $record) && $update_mode < 2)){
            $params[0] = $params[0].'s';
            $params[] = $rec_spad;
            $query = $query.', rec_ScratchPad=?';
        }

        $query = $query.' where rec_ID='.$recID;

        $stmt = $mysqli->prepare($query);

        //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
        call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));

        if(!$stmt->execute()){
            $syserror = $mysqli->error;
            $stmt->close();
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $system->addError(HEURIST_DB_ERROR, 'Cannot save record', $syserror);
        }
        $stmt->close();

        //update group view and edit permissions
        $access_grps = ($access=='viewable')?@$record['NonOwnerVisibilityGroups']:null;
        array_shift($owner_grps);//remove first
        updateUsrRecPermissions($mysqli, $recID, $access_grps, $owner_grps);

        if(!$modeImport){
            if($system->getUserId()>0){
                //set current user for stored procedures (log purposes)
                $mysqli->query('set @logged_in_user_id = '.$system->getUserId());
            }
            mysql__supress_trigger($mysqli, false);
        }

        //delete ALL existing details
        $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
        if(!$mysqli->query($query)){
            $syserror = $mysqli->error;
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $system->addError(HEURIST_DB_ERROR, 'Cannot delete old details', $syserror);
        }
    }
    //END HEADER SAVE

    //ADD DETAILS
    $addedByImport = ($modeImport?1:0);


    $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID, dtl_Geo, dtl_HideFromPublic) '.
    "VALUES ($recID, ?, ?, $addedByImport, ?, ST_GeomFromText(?), ?)";
    $stmt = $mysqli->prepare($query);

    /* $query_geo = "INSERT INTO recDetails ".
    "(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_Geo) ".
    "VALUES ($recID, ?, ?, $addedByImport, ST_GeomFromText(?) )";
    $stmt_geo = $mysqli->prepare($query2);*/

    //


    if ($stmt) {

        // $stmt->bind_param('isis', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo);
        foreach ($detailValues as $idx=>$values) {

            $dtyID = $values['dtl_DetailTypeID'];
            $dtl_Value = @$values['dtl_Value'];
            if($dtl_Value) {$dtl_Value = super_trim($dtl_Value);}//including &nbsp; and &xef; (BOM)
            $dtl_UploadedFileID = @$values['dtl_UploadedFileID'];
            $dtl_Geo = @$values['dtl_Geo'];
            $dtl_HideFromPublic = @$values['dtl_HideFromPublic'];

            $stmt->bind_param('isisi', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo, $dtl_HideFromPublic);
            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                if($use_transaction){
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}
                }

                return $system->addError(HEURIST_DB_ERROR, 'Cannot save value - possibly bad encoding or invalid date format (System error: '.$syserror.').', $syserror);

            }

            //add reverce field "Parent Entity" (#247) in child resource record
            if(defined('DT_PARENT_ENTITY') && !$suppress_parent_child){
                if(@$values['dtl_ParentChild']==true){

                    // $dtl_Value  is id of child record
                    $res = addReverseChildToParentPointer($mysqli, $dtl_Value, $recID, $addedByImport, false);

                    if($res<0){
                        $syserror = $mysqli->error;
                        if($use_transaction){
                            $mysqli->rollback();
                            if($keep_autocommit===true) {$mysqli->autocommit(true);}
                        }
                        return $system->addError(HEURIST_DB_ERROR,
                            'Cannot save value. Cannot insert reverse pointer for child record', $syserror);
                    }elseif($res!=0){
                        //update record title for child record
                        list($child_rectype, $child_title) = mysql__select_row($mysqli,
                            'SELECT rec_RecTypeID, rec_Title FROM Records WHERE rec_ID='
                            .intval($dtl_Value));
                        recordUpdateTitle($system, $dtl_Value, $child_rectype, $child_title);
                    }

                }elseif($dtyID == DT_PARENT_ENTITY){

                    $res = addParentToChildPointer($mysqli, $recID, $rectype, $dtl_Value, null, $addedByImport);
                    if($res<0){
                        $syserror = $mysqli->error;
                        if($use_transaction){
                            $mysqli->rollback();
                            if($keep_autocommit===true) {$mysqli->autocommit(true);}
                        }
                        return $system->addError(HEURIST_DB_ERROR,
                            'Cannot save value. Cannot insert pointer for parent record', $syserror);
                    }elseif($res!=0){
                        //update record title for parent record
                        list($parent_rectype, $parent_title) = mysql__select_row($mysqli,
                            'SELECT rec_RecTypeID, rec_Title FROM Records WHERE rec_ID='
                            .intval($dtl_Value));
                        recordUpdateTitle($system, $dtl_Value, $parent_rectype, $parent_title);
                    }

                }
            }

        }
        $stmt->close();
        //$stmt_geo->close();

    }else{
        $syserror = $mysqli->error;
        if($use_transaction){
            $mysqli->rollback();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
        }
        return $system->addError(HEURIST_DB_ERROR, 'Cannot save details(3)', $syserror);
    }

    $newTitle = recordUpdateTitle($system, $recID, $rectype, @$record['Title']);
    $rty_counts = null;

    if(!$is_insert && !$modeImport)
    {
        mysql__supress_trigger($mysqli, true);

        recordUpdateCalcFields( $system, $recID, $rectype );//update calculated fields in this record

        $entryMaskIssues = recordUpdateMaskFields($system, $recID, $rectype);

        //check that this record my affect other records with calculated fields
        //1. cfn_RecTypeIDs -> cfn_ID
        //2. defRecStructure where rst_CalcFunctionID  -> rst_RecTypeID+rst_DetailTypeID
        //it may consume waste of time findAndUpdateAffectedCalcFields( $system, $rectype )

        removeReverseChildToParentPointer($system, $recID, $rectype);

        //find all relationship records and update FlagTemporary and record title
        $relRecsIDs = array();

        //@todo - rollback in case of error
        $mask = mysql__select_value($mysqli,"select rty_TitleMask from defRecTypes where rty_ID=".RT_RELATION);

        $relRecs = recordGetRelationship($system, $recID, null, array('detail'=>'ids'));
        if(!isEmptyArray($relRecs)){
            $relRecsIDs = $relRecs;
        }
        $relRecs = recordGetRelationship($system, null, $recID, array('detail'=>'ids'));
        if(!isEmptyArray($relRecs)){
            $relRecsIDs = array_merge($relRecsIDs, $relRecs);
        }
        //reset temporary flag for all relationship records
        if(!isEmptyArray($relRecsIDs)){
            foreach($relRecsIDs as $relID){
                $res = recordUpdateTitle($system, $relID, $mask, 'Title Mask for Relationship not defined');
            }
            $query = 'UPDATE Records set rec_FlagTemporary=0 where rec_ID in ('.implode(',',$relRecsIDs).')';
            $res = $mysqli->query($query);
        }

        //recordGetLinkedRecords - get all linked and related records and update them
        $links = recordGetLinkedRecords($system, $recID);
        if(!isEmptyArray($links)){
            //find title masks
            $links_rectypes = array_unique(array_values($links));
            $masks = mysql__select_assoc2($mysqli,'select rty_ID, rty_TitleMask from defRecTypes where rty_ID in ('
                .implode(',',$links_rectypes) .')');

            foreach($links as $linkRecID=>$linkRecTypeID){
                $res = recordUpdateTitle($system, $linkRecID, $masks[$linkRecTypeID], null);
            }
        }
        mysql__supress_trigger($mysqli, false);

    }//update flagtemporary and title for related,linked records

    //calculate counts
    //$rty = new DbDefRecTypes($system,array('mode'=>'record_count', 'rty_ID'=>array(1, $rectype)));
    //$rty_counts = $rty->counts();

    if($use_transaction){
        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
    }

    //send notification email
    $bugreportRecType = ConceptCode::getRecTypeLocalID('8-23');
    if($bugreportRecType && $bugreportRecType == $rectype){
        bugreportUpdate($system, $recID);
    }elseif($swf_emails!=null && !$block_swf_email){

        $stage_name = mysql__select_value($mysqli, 'select trm_Label from defTerms where trm_ID='.$new_swf_stage);
        $user = $system->getCurrentUser();
        $user = @$user['ugr_FullName'];
        $user = $user ?: $system->getUserId();

        $title = HEURIST_DBNAME . ", ID: $recID >> workflow: $stage_name";
        $msg = !empty($swf_body) ? $swf_body : '<b>'.$title.'</b> '
        .'<a href="'.HEURIST_BASE_URL.'hclient/framecontent/recordEdit.php?db='.HEURIST_DBNAME.'&recID='.$recID.'">Record #'.$recID
        .'  "'.USanitize::sanitizeString($newTitle, false).'"</a><br>'
        .' has been changed to "'.$stage_name
        .'"<br><br> by user: '.$user;

        if($total_record_count > 1){
            $msg = $msg . '<br><br><i>This is the first of multiple records'. ($modeImport > 0 ? ' imported' : '') .'. Please visit database for additional records.</i>';
        }

        $rec_view = $system->recordLink($recID);
        $rec_edit = strpos($rec_view, '/view/') !== false
                        ? str_replace('/view/', '/edit/', $rec_view)
                        : HEURIST_BASE_URL_PRO . "?fmt=edit&recID={$recID}&db=" . HEURIST_DBNAME;

        $msg = str_replace(['#title#', '#link_v#', '#link_e#'], [$newTitle, $rec_view, $rec_edit], $msg);

        $firstEmail = array_pop($swf_emails);
        $swf_emails = empty($swf_emails) ? [$firstEmail] : ['to' => [$firstEmail], 'bcc' => $swf_emails];

        $res = sendPHPMailer(HEURIST_MAIL_TO_ADMIN, 'Heurist DB '.HEURIST_DBNAME.'. ID: '.$recID, //'Workflow stage update notification',
                    $swf_emails, $title, $msg, null, true);

        if($total_record_count > 1 && $res){ // block further emails for imports, only if the email was sent
            $block_swf_email = true;
        }
    }

    $rtn = [
        'status' => HEURIST_OK,
        'data' => intval($recID),
        'rec_Title' => $newTitle,
        'affectedRty' =>$rectype,
        'issues' => []
    ];

    if(!empty($missingParents)){
        $rtn['issues']['parents'] = $missingParents;
    }
    if(!empty($entryMaskIssues)){
        $rtn['issues']['entryMask'] = $entryMaskIssues;
    }

    return $rtn;
    //, 'counts'=>$rty_counts
    /*
    $response = array("status"=>HEURIST_OK,
    "data"=> array(
    "count"=>$num_rows,
    "fields"=>$fields,
    "records"=>$records,
    "rectypes"=>$rectypes,
    "structures"=>$rectype_structures));
    */
}//recordSave


/**
* removes heurist record and all dependent entries
* (note: heurist record will be kept in sysArchive, this function performs a "soft delete" from active tables).
*
* This function manages the deletion of one or more records. Key operations include:
* - Permission checks: Verifies if the user is allowed to delete records in general and the specific records requested.
* - Filtering: Can filter records to be deleted by a specific record type ID.
* - Source link checking: Optionally, can prevent deletion if other records link to the target records.
* - Transaction management: Database operations can be wrapped in a transaction.
* - Progress tracking: Supports a session ID for tracking progress of batch deletions.
* - Delegation: Calls `deleteOneRecord` for each individual record to handle the actual deletion logic.
* - Error handling: Aggregates errors and provides a summary of the operation.
*
* @param \hserv\System $system The Heurist system object.
* @param int|string|array $recids A single record ID, a comma-separated string of record IDs, or an array of record IDs to be deleted.
* @param bool $need_transaction Optional. If true (default), database operations are wrapped in a transaction.
*                               Set to false if called from a context where a transaction is already managed (e.g., user/group deletion).
* @param bool $check_source_links Optional. If true, the function first checks if there are any other records
*                                 pointing to the records about to be deleted (via resource pointer fields).
*                                 If such links exist, the deletion is blocked, and information about the
*                                 linking records is returned. Defaults to false.
* @param int $filterByRectype Optional. If provided and greater than 0, only records matching this record type ID
*                             will be considered for deletion from the `$recids` list. Defaults to 0 (no filtering).
* @param string|null $progress_session_id Optional. A session ID used for tracking the progress of a large batch deletion.
*                                         Updates are written to a progress tracking mechanism. If the mechanism
*                                         indicates termination, the process is halted. Defaults to null.
* @return array|false Returns an associative array summarizing the deletion operation or an error status.
*               On successful processing (even if some records were not deleted due to permissions):
*               `['status' => HEURIST_OK, 'affectedRty' => (array)$affected_rectypes, 'data' => [
*                   'processed' => (int)count_allowed_to_process,
*                   'deleted' => (int)count_actually_deleted,
*                   'noaccess' => (int)count_no_access,
*                   'bkmk_count' => (int)total_bookmarks_deleted,
*                   'rels_count' => (int)total_relationships_deleted
*               ]]`
*               If `$check_source_links` is true and source links are found:
*               `['status' => HEURIST_OK, 'data' => ['source_links_count' => (int)count, 'source_links' => (string)comma_separated_ids]]`
*               On failure (e.g., user not allowed to delete, DB error, termination): An error array from the system object
*               or `false` in some permission scenarios.
*/
function recordDelete($system, $recids, $need_transaction=true,
    $check_source_links=false, $filterByRectype=0, $progress_session_id=null){

    // Check that the user is allowed to delete records
    $is_allowed = userCheckPermissions($system, 'delete');
    if($is_allowed !== true){
        return $is_allowed;
    }

    $recids = prepareIds($recids);
    if(!empty($recids)){

        if(count($recids)>100){
            ini_set('max_execution_time', '0');
        }


        /*narrow by record type
        $rec_RecTypeID = @$params['rec_RecTypeID'];
        if($rec_RecTypeID>0){
        $recids = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
        .implode(',', $recids).') and rec_RecTypeID='. $rec_RecTypeID);

        if($recids==null || empty($recids)){
        $this->system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
        return false;
        }
        }*/

        $rectypes = array();
        $noaccess_count = 0;
        $allowed_recids = array();

        //check permission
        foreach ($recids as $recID) {
            $ownerid = null;
            $access = null;
            $is_allowed = recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access, $rectypes);
            if( (!($filterByRectype>0)) || ($rectypes[$recID]==$filterByRectype)) {
                if($is_allowed){
                    array_push($allowed_recids, $recID);
                }else{
                    $noaccess_count++;
                }
            }
        }
        if(count($recids)==1 && $noaccess_count==1){
            return $system->getError();
            //}elseif(count($recids)==$noaccess_count){
        }else{
            $system->clearError();
        }

        //find reverse links to given set of ids
        if($check_source_links && !empty($allowed_recids)){
            $links = recordSearchRelated($system, $allowed_recids, -1, 'ids', 1);

            if($links['status']==HEURIST_OK && @$links['data']['reverse']!=null
                && !isEmptyArray(@$links['data']['reverse'])){
                return array('status'=>HEURIST_OK,
                    'data'=> array( 'source_links_count'=>count($links['data']['reverse']),
                        'source_links'=>implode(',',$links['data']['reverse']) ));
            }
        }

        $is_error = false;
        $mysqli = $system->getMysqli();
        if($need_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        $bkmk_count = 0;
        $rels_count = 0;
        $deleted = array();
        $affected_rectypes = array();
        $msg_error = '';
        $msg_termination = null;

        $system->defineConstant('RT_RELATION');

        if($system->getUserId()>0){
            //set current user for stored procedures (log purposes)
            $mysqli->query('set @logged_in_user_id = '.$system->getUserId());
        }
        mysql__supress_trigger($mysqli, false);

        $tot_count = count($allowed_recids);

        if($progress_session_id){
            //init progress session
            mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
        }

        foreach ($allowed_recids as $recID) {
            //$stat = array('deleted'=>array($recID), 'rels_count'=>0, 'bkmk_count'=>0);
            $stat = deleteOneRecord($system, $recID, $rectypes[$recID]);

            if( array_key_exists('error', $stat) ){
                $msg_error = $stat['error'];
                break;
            }else{
                $deleted = array_merge($deleted, $stat['deleted']);
                $rels_count += $stat['rels_count'];
                $bkmk_count += $stat['bkmk_count'];

                if(!in_array($rectypes[$recID],$affected_rectypes)){
                    array_push($affected_rectypes, $rectypes[$recID]);
                }
            }

            //update session and check for termination
            if($progress_session_id && (count($deleted) % 10 == 0)){
                $session_val = count($deleted).','.$tot_count;
                $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $msg_termination = 'Deletion is terminated by user';
                    break;
                }
            }
        }//foreach

        if($progress_session_id){
            //remove session file
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        if($msg_termination){
            $res = $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
        }elseif($msg_error){
            $res = $system->addError(HEURIST_DB_ERROR, 'Cannot delete record. '.$msg_error);
        }else{
            $res = array('status'=>HEURIST_OK,
                'affectedRty'=>$affected_rectypes,
                'data'=> array( 'processed'=>count($allowed_recids),
                    'deleted'=>count($deleted), 'noaccess'=>$noaccess_count,
                    'bkmk_count'=>$bkmk_count, 'rels_count'=>$rels_count));
        }

        if($need_transaction){
            mysql__end_transaction($mysqli, !($msg_termination || $msg_error), $keep_autocommit);
        }
        return $res;

    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record IDs not defined');
    }
}

/**
 * Gets an incremented value for a specified field within a record type.
 *
 * If the field is numeric, it finds the maximum current value and adds 1.
 * If the field is text, it finds the last value and increments a trailing number, or appends '1'.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array with parameters:
 *                      - 'rtyID': The record type ID.
 *                      - 'dtyID': The detail type ID (field ID).
 * @return array|false An array `['status'=>HEURIST_OK, 'result'=>$value]` on success,
 *                     or an error array from the system object on failure.
 */
function recordGetIncrementedValue($system, $params){


    $rt_ID = intval(@$params["rtyID"]);
    $dt_ID = intval(@$params["dtyID"]);

    if($rt_ID>0 && $dt_ID>0){

        $mysqli = $system->getMysqli();

        //1. get detail type
        $res = mysql__select_list($mysqli, 'defDetailTypes','dty_Type','dty_ID='.$dt_ID);
        if(!isEmptyArray($res)){
            $isNumeric = ($res[0]!='freetext');

            //2. get max value for numeric and last value for non numeric
            if($isNumeric){
                $res = mysql__select_value($mysqli, 'select max(CAST(dtl_Value as SIGNED)) FROM recDetails, Records'
                    ." WHERE dtl_RecID=rec_ID and rec_RecTypeID=$rt_ID and dtl_DetailTypeID=$dt_ID");
            }else{
                $res = mysql__select_value($mysqli, 'select dtl_Value FROM recDetails, Records'
                    ." WHERE dtl_RecID=rec_ID and rec_RecTypeID=$rt_ID and dtl_DetailTypeID=$dt_ID"
                    .' ORDER BY rec_ID DESC LIMIT 1');
            }

            $value = 1;

            if($res!=null){

                if($isNumeric){
                    $value = 1 + intval($res);
                }else{
                    //find digits at the end of string
                    $value = $res;
                    $matches = array();
                    if (preg_match('/(\d+)$/', $value, $matches)){
                        $digits = $matches[1];
                        $increment_digit = str_pad(intval($digits) + 1, strlen($digits), '0', STR_PAD_LEFT);

                        $value = substr($value,0,-strlen($digits)).($increment_digit);

                    }else{
                        $value = $value.'1';
                    }
                }
            }

            return array("status"=>HEURIST_OK, 'result'=>$value);
        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, 'Get incremented value. Detail type '.$dt_ID.' not found');
        }
    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Get incremented value. Parameters are wrong or undefined');
    }

}

/**
 * Gets all incremented values for fields configured with "increment_new_values_by_1"
 * as their default value within a given record type.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array with parameters:
 *                      - 'rtyID': The record type ID.
 *                      - 'ignore_dtys': (Optional) Comma-separated string or array of dty_IDs to ignore.
 * @return array|false An associative array `[dty_ID => incremented_value]` on success,
 *                     or an error array from the system object if `rtyID` is missing.
 */
function recordGetAllIncremenetedValues($system, $params){

    $rty_ID = intval(@$params['rtyID']);
    $ignore_dtys = @$params['ignore_dtys'];

    if(!($rty_ID > 0)){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Get all ] incremented values. Record type is missing');
    }

    $ret = array();

        if(!empty($ignore_dtys) && !is_array($ignore_dtys)){
            $ignore_dtys = explode(',', $ignore_dtys);
        }
        if(!empty($ignore_dtys)){
            $ignore_dtys = array_filter($ignore_dtys, function($id){
                return intval($id) > 0;
            });
        }

        $mysqli = $system->getMysqli();
        $rst_dty_filter = '';

        if(!empty($ignore_dtys)){
            $rst_dty_filter = 'AND rst_DetailTypeID ' . (count($ignore_dtys) > 1 ? ' NOT IN ('. implode(',', $ignore_dtys) .')' : ' != ' . $ignore_dtys[0]);
        }

        $query = "SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_DefaultValue = 'increment_new_values_by_1' AND rst_RecTypeID = $rty_ID $rst_dty_filter";

        $dty_IDs = mysql__select_list2($mysqli, $query);

        foreach ($dty_IDs as $dty_ID) {

            $result = recordGetIncrementedValue($system, array('rtyID' => $rty_ID, 'dtyID' => $dty_ID));

            if($result['status'] === HEURIST_OK){
                $ret[$dty_ID] = $result['result'];
            }
        }

    return $ret;
}

/**
 * Updates the ownership and access visibility for a set of records.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array with parameters:
 *                      - 'ids': Array or comma-separated string of record IDs.
 *                      - 'OwnerUGrpID': New owner user/group ID(s). Can be 'current_user'.
 *                      - 'NonOwnerVisibility': New visibility setting (e.g., 'public', 'viewable', 'hidden').
 *                      - 'NonOwnerVisibilityGroups': (Optional) Group IDs for 'viewable' if $access is 'viewable'.
 *                      - 'rec_RecTypeID': (Optional) Filter records by this record type ID.
 *                      - 'session': (Optional) Progress session ID for large updates.
 * @return array|false An associative array on success:
 *                     `['status' => HEURIST_OK, 'data' => [
 *                         'processed' => (int)count_allowed_recids,
 *                         'updated' => (int)updated_record_count,
 *                         'noaccess' => (int)noaccess_count
 *                     ]]`
 *                     Returns false on critical failure (e.g., DB error during update, user termination).
 *                     Errors (e.g., invalid parameters, no records found, permission issues for all records)
 *                     are added to the system object and may result in an error array being returned by the calling context.
 */
function recordUpdateOwnerAccess($system, $params){

    $recids = @$params['ids'];

    $recids = prepareIds($recids);
    if(!empty($recids)){

        if(@$params['OwnerUGrpID']=='current_user'){
            $params['OwnerUGrpID'] = $system->getUserId();
        }

        $owner_grps = prepareIds( @$params['OwnerUGrpID'], true);
        $access = @$params['NonOwnerVisibility'];

        if((isEmptyArray($owner_grps) || $access==null) && !$system->isAdmin()){
            return $system->addError(HEURIST_INVALID_REQUEST, 'Neither owner nor visibility parameters defined');
        }

        $mysqli = $system->getMysqli();

        //narrow by record type
        $rec_RecTypeID = @$params['rec_RecTypeID'];
        if($rec_RecTypeID>0){
            $recids = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
                .implode(',', $recids).') and rec_RecTypeID='. $rec_RecTypeID);
            $recids = prepareIds($recids);//for snyk
            if(isEmptyArray($recids)){
                return $system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
            }
        }

        $rectypes = array();//stub param for recordCanChangeOwnerwhipAndAccess

        $noaccess_count = 0;

        $allowed_recids = array();

        $msg_termination = null;
        $tot_count = count($recids);
        $processed = 0;
        $progress_session_id = @$params['session'];

        if($system->isAdmin())  //admin can change everything
        {

            $allowed_recids = $recids;
        }else{

            if($progress_session_id){
                //init progress session
                mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
            }

            foreach ($recids as $recID) {
                if(!recordCanChangeOwnerwhipAndAccess($system, $recID, $owner_grps, $access, $rectypes)){
                    $noaccess_count++;
                }else{
                    array_push($allowed_recids, $recID);
                }
                $processed++;
                //update session and check for termination
                if($progress_session_id && ($processed % 1000 == 0)){
                    $session_val = $processed.','.$tot_count;
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        $msg_termination = 'Operation is terminated by user';
                        break;
                    }
                }


            }//foreach

        }//not admin

        $cnt_allowed_recids = count($allowed_recids);

        if($cnt_allowed_recids==0 && $progress_session_id){
            //remove session file
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
            if($msg_termination){
                return $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
            }
        }

        if(count($recids)==1 && $noaccess_count==1){
            return $system->getError();
        }elseif($cnt_allowed_recids==0) {
            return $system->addError(HEURIST_REQUEST_DENIED,
                'User does not have sufficient authority to change ownership and access for any of '.count($recids).' selected record');
        }else{
            $system->clearError();
        }


        // start transaction
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $msg_termination = null;
        $tot_count = $cnt_allowed_recids;

        $rec_mod = date(DATE_8601);
        $main_owner = null;
        if(!empty($owner_grps)){
            $main_owner = $owner_grps[0];
            array_shift( $owner_grps );//other owners
        }
        $access_grps = @$params['NonOwnerVisibilityGroups'];
        $success = true;
        $updated_count = 0;

        //update by chunks
        $k = 0;

        // Setup base query
        $fields = ['rec_Modified=?'];
        $data = [$rec_mod];
        $types = 's';
        if(!empty($main_owner)){
            $fields[] = 'rec_OwnerUGrpID=?';
            $data[] = $main_owner;
            $types .= 'i';
        }
        if(!empty($access)){
            $fields[] = 'rec_NonOwnerVisibility=?';
            $data[] = $access;
            $types .= 's';
        }
        $base_query = 'UPDATE Records set ' . implode(', ', $fields);

        while ($k < $cnt_allowed_recids) {

            if($progress_session_id && $cnt_allowed_recids>5000){

                $session_val = $k.','.$cnt_allowed_recids;
                $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $success = false;
                    $msg_termination = 'Operation is terminated by user';
                    break;
                }
            }

            $chunk = array_slice($allowed_recids, $k, 5000);

            $query = $base_query . ' where rec_ID in ('.implode(',', $chunk).')';

            $stmt = $mysqli->prepare($query);

            $stmt->bind_param($types, ...$data);

            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                $system->addError(HEURIST_DB_ERROR, 'Cannot updated ownership and access', $syserror);
                $success = false;
                break;
            }else{
                $updated_count = $updated_count + $mysqli->affected_rows;
                $stmt->close();

                updateUsrRecPermissions($mysqli, $chunk, $access_grps, $owner_grps);
            }

            $k = $k + 5000;
        }//while

        if($progress_session_id && $cnt_allowed_recids>5000){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        //
        // commit ot rollback
        //
        if($success){
            $mysqli->commit();

            $res = array("status"=>HEURIST_OK,
                "data"=> array('processed'=>$cnt_allowed_recids,
                    'updated'=>$updated_count,
                    'noaccess'=>$noaccess_count));

        }else{
            $mysqli->rollback();

            if($msg_termination){
                $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
            }
            $res = false;
        }


        //restore
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
        return $res;


    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record IDs not defined');
    }

}

/**
 * Deletes a single record and all its associated data.
 *
 * This includes details, reminders, permissions, tags, comments, bookmarks,
 * and WOOT entries. It also recursively deletes child records (if `rst_CreateChildIfRecPtr` is set on a field pointing to them)
 * and related "relationship" type records where this record is a source or target.
 *
 * Note: This function performs actual deletions from tables like `Records`, `recDetails`, etc.
 * It assumes that the record is not archived yet (archiving is a separate process).
 * Foreign key checks are temporarily disabled during deletions to manage complex dependencies.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $id The ID of the record to delete.
 * @param int $rectype The record type ID of the record to delete. This is used to identify potential child records.
 * @return array An associative array indicating the outcome:
 *               - `['error' => string $errorMessage]` if a database error occurred or a parameter was wrong.
 *               - `['deleted' => array $deleted_ids, 'bkmk_count' => int $bkmk_count, 'rels_count' => int $rels_count]` on success.
 *                 `$deleted_ids` includes the initially provided `$id` and IDs of any recursively deleted child/relationship records.
 *                 `$bkmk_count` is the count of bookmarks deleted for this record.
 *                 `$rels_count` is the count of directly related "relationship" records deleted.
 */
function deleteOneRecord($system, $id, $rectype){


    $id = intval($id);
    $rectype = intval($rectype);

    if(!($id>0)){
        return array("error" => errorWrongParam('Record id'));
    }

    $bkmk_count = 0;
    $rels_count = 0;
    $deleted = array();//ids of deleted records
    $msg_error = '';
    $mysqli = $system->getMysqli();

    //get list if child records
    $query = 'SELECT dtl_Value FROM recDetails, defRecStructure '
    ." WHERE dtl_RecID=$id AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID=$rectype";

    $child_records = mysql__select_list2($mysqli, $query);
    if(is_array($child_records) && !empty($child_records)){
        $query = 'SELECT rec_ID, rec_RecTypeID FROM Records WHERE '.predicateId('rec_ID',$child_records);
        $child_records = mysql__select_assoc2($mysqli, $query);
    }

    //find target records where resource (record pointer) field points to record to be deleted
    $links = recordSearchRelated($system, array($id), -1, false, 1);
    if($links['status']==HEURIST_OK && count(@$links['data']['reverse'])>0){
        $links = $links['data']['reverse'];
    }else{
        $links = null;
    }

    while(true){
        mysql__foreign_check($mysqli, false);

        $id = intval($id);
        //
        $mysqli->query('delete from recDetails where dtl_RecID = ' . $id);
        if ($mysqli->error) {break;}

        //
        $mysqli->query('delete from Records where rec_ID = ' . $id);
        if ($mysqli->error) {break;}
        array_push($deleted, $id);

        //remove pointer fields
        if($links){
            foreach ($links as $relation) {
                $mysqli->query('delete from recDetails where dtl_RecID = ' . intval($relation->sourceID)
                    .' and dtl_DetailTypeID = '.intval($relation->dtID).' and dtl_Value='.$id);
                if ($mysqli->error) {break;}
            }
        }

        ElasticSearch::deleteRecordIndexEntry(HEURIST_DBNAME, $rectype, $id);

        $mysqli->query('delete from usrReminders where rem_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from usrRecPermissions where rcp_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from recForwarding where rfw_NewRecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from usrRecTagLinks where rtl_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from recThreadedComments where cmt_RecID = ' . $id);
        if ($mysqli->error) {break;}


        //change all woots with title bookmark: to user:
        $mysqli->query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
        if ($mysqli->error) {break;}


        $mysqli->query('delete from usrBookmarks where bkm_recID = ' . $id);
        if ($mysqli->error) {break;}
        $bkmk_count = $bkmk_count + $mysqli->affected_rows;

        //delete from woot
        $mysqli->query('delete from woot_ChunkPermissions where wprm_ChunkID in '.
            '(SELECT chunk_ID FROM woots, woot_Chunks where chunk_WootID=woot_ID and woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woot_Chunks where chunk_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woot_RecPermissions where wrprm_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woots where woot_Title="record:'.$id.'"');
        if ($mysqli->error) {break;}

        mysql__foreign_check($mysqli, true);

        //remove special kind of record - relationship
        $refs_res = $mysqli->query('select rec_ID from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records on rec_ID=dtl_RecID where dty_Type="resource" and dtl_Value='.$id.' and rec_RecTypeID='.RT_RELATION);
        if($refs_res){
            while ($row = $refs_res->fetch_assoc()) {
                $res = deleteOneRecord($system, $row['rec_ID'], RT_RELATION);
                if( array_key_exists('error', $res) ){
                    $msg_error = $res['error'];
                    break;
                }else{
                    $deleted = array_merge($deleted, $res['deleted']);
                    $rels_count += $res['rels_count'];
                    $bkmk_count += $res['bkmk_count'];
                }
            }
            $refs_res->close();
        } else {
            $msg_error = 'Cannot get relationship records';
            break;
        }


        if(!isEmptyArray($child_records)){
            foreach ($child_records as $recid => $rectypeid) {
                $res = deleteOneRecord($system, $recid, $rectypeid);
                if( array_key_exists('error', $res) ){
                    $msg_error = 'Cannot delete child records'.$res['error'];
                    break;
                }else{
                    $deleted = array_merge($deleted, $res['deleted']);
                    $rels_count += $res['rels_count'];
                    $bkmk_count += $res['bkmk_count'];
                }
            }
        }
        break;
    }//while

    if($mysqli->error || $msg_error){
        $res = array("error" => $msg_error.'  '.$mysqli->error);
    }else{
        $res = array("deleted"=>$deleted, "bkmk_count"=>$bkmk_count, "rels_count"=>$rels_count);
    }
    mysql__foreign_check($mysqli, true);
    return $res;
}

/**
 * Adds or updates a "Parent Entity" (reverse pointer) detail in a child record.
 *
 * This function creates or modifies a detail of type DT_PARENT_ENTITY in the child record,
 * making it point to the parent record. It checks if a similar pointer already exists
 * and, based on `$allow_multi_parent`, either updates the existing one or adds a new one.
 * The constant DT_PARENT_ENTITY must be defined for this function to operate.
 *
 * @param \mysqli $mysqli The mysqli connection object.
 * @param int $child_id The ID of the child record.
 * @param int $parent_id The ID of the parent record to point to.
 * @param int $addedByImport Optional. Flag indicating if this operation is part of an import process (0 or 1). Defaults to 0.
 * @param bool $allow_multi_parent Optional. If true, allows multiple DT_PARENT_ENTITY details in the child record.
 *                                 If false (default), it will update an existing DT_PARENT_ENTITY detail if one is found
 *                                 (pointing to a different parent) rather than adding a new one. If an exact pointer
 *                                 (same child, same parent, same type) already exists, it does nothing.
 * @return int Returns:
 *             - -1: If a database error occurs during insertion or update.
 *             -  0: If DT_PARENT_ENTITY is not defined, or if the exact same parent-child pointer already exists.
 *             -  1: If a new "Parent Entity" detail was successfully inserted.
 *             -  2: If an existing "Parent Entity" detail was successfully updated to point to the new parent_id
 *                   (this happens when `$allow_multi_parent` is false and a pointer to a different parent existed).
 */
function addReverseChildToParentPointer($mysqli, $child_id, $parent_id, $addedByImport=0, $allow_multi_parent=false){

    if(!defined('DT_PARENT_ENTITY')){
        return 0;
    }

    $res = 0;

        $child_id  = intval($child_id);
        $dtl_ID = -1;

        $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID=$child_id AND dtl_DetailTypeID=".DT_PARENT_ENTITY;
        $res = $mysqli->query($query);
        if ($res){
            $matches = array();
            while ($row = $res->fetch_row()){
                if($parent_id == $row[1]){
                    return 0; //exactly the same already exists
                }
                $dtl_ID = $row[0];
            }
            $res->close();
            $res = ($dtl_ID>0)?2:1;
        }

        $parent_id = intval($parent_id);

        if($dtl_ID>0 && !$allow_multi_parent){ //pointer already exists
            $mysqli->query('UPDATE recDetails '.
                'SET dtl_Value='.$parent_id.' WHERE dtl_ID='.intval($dtl_ID));

            if($mysqli->error) {$res = -1; }
            return $res;
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) '.
                "VALUES ($child_id, ".DT_PARENT_ENTITY.", $parent_id, $addedByImport )");
        if(!($mysqli->insert_id>0)) {$res=-1;}
        return $res;
}


/**
 * Removes "Parent Entity" (reverse pointer) details from child records if the corresponding
 * forward pointer from the parent no longer exists or is not a `rst_CreateChildIfRecPtr` field.
 *
 * This is typically called after a parent record is updated to clean up outdated reverse pointers.
 * It ensures that child records only point back to parent records that legitimately
 * link to them via a `rst_CreateChildIfRecPtr` field.
 * The constant DT_PARENT_ENTITY must be defined for this function to operate.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $parent_id The ID of the parent record.
 * @param int $rectype The record type ID of the parent record.
 * @return void
 */
function removeReverseChildToParentPointer($system, $parent_id, $rectype){

    if($system->defineConstant('DT_PARENT_ENTITY')){
        //get list of valid record
        $query = 'SELECT dtl_Value FROM recDetails, defRecStructure '
        ." WHERE dtl_RecID=$parent_id AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID=$rectype";

        $mysqli = $system->getMysqli();

        $recids = mysql__select_list2($mysqli, $query, 'intval');

        $query = "DELETE FROM recDetails WHERE dtl_Value=$parent_id AND dtl_DetailTypeID=".DT_PARENT_ENTITY;

        if(!isEmptyArray($recids)){
            $recids = prepareIds($recids);//redundant for snyk
            $query = $query.' AND dtl_RecID NOT IN ('.implode(',',$recids).')';
        }

        $mysqli->query($query);
    }
}

/**
 * Adds a pointer detail from a parent record to a child record.
 *
 * This is used when a parent record has a "Create Child If Rec Ptr" field that
 * points to a child. This function ensures the corresponding forward pointer exists
 * in the parent record. It assumes only one such pointer field should exist from
 * the parent to this child type.
 * The constant DT_PARENT_ENTITY is checked, but its primary role is to enable the broader parent-child mechanism;
 * this function specifically deals with the forward pointer from parent to child.
 *
 * @param \mysqli $mysqli The mysqli connection object.
 * @param int $child_id The ID of the child record.
 * @param int $child_rectype The record type ID of the child record. This is used to find a suitable pointer field in the parent's record type structure if `$detailTypeId` is not provided.
 * @param int $parent_id The ID of the parent record where the pointer detail will be added.
 * @param int|null $detailTypeId Optional. The specific detail type ID (field ID) in the parent record that should point to the child.
 *                               If null, the function searches for a field in the parent's record type definition
 *                               that is configured with `rst_CreateChildIfRecPtr=1` and whose `dty_PtrTargetRectypeIDs`
 *                               includes the `$child_rectype`.
 * @param int $addedByImport Optional. Flag indicating if this operation is part of an import process (0 or 1). Defaults to 0.
 * @return int Returns:
 *             - -1: If a database error occurs during insertion.
 *             -  0: If DT_PARENT_ENTITY is not defined, or if no suitable pointer field (`$detailTypeId`) is found in the parent record type that matches the child's record type, or if the exact same parent-to-child pointer already exists.
 *             -  1: If a new pointer detail was successfully inserted into the parent record.
 */
function addParentToChildPointer($mysqli, $child_id, $child_rectype, $parent_id,  $detailTypeId=null, $addedByImport=0){

    $res = 0;

    if(defined('DT_PARENT_ENTITY')){

        $dtl_ID = -1;
        $parent_id = intval($parent_id);
        $child_id = intval($child_id);

        //find what field in parent record refers
        if(!($detailTypeId>0)){

            $query =
            'SELECT rst_DetailTypeID, dty_PtrTargetRectypeIDs FROM defRecStructure, defDetailTypes, Records '
            .'WHERE rec_ID='.$parent_id.' AND rec_RecTypeID=rst_RecTypeID AND rst_CreateChildIfRecPtr=1 '
            .'AND rst_DetailTypeID=dty_ID';

            $pointers = mysql__select_assoc2($mysqli, $query);
            if(!isEmptyArray($pointers)){
                foreach($pointers as $dt_ID=>$ptr){
                    if($ptr) {$ptr = explode(',',$ptr);}
                    if(!empty($ptr) && in_array($child_rectype, $ptr)){
                        $detailTypeId = $dt_ID;
                        break;
                    }
            }}
        }

        if(!($detailTypeId>0)){
            return 0; //appropriate pointer field in parent record type not found
        }

        //check if already exists
        $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID=$parent_id AND dtl_DetailTypeID=$detailTypeId";
        $res = $mysqli->query($query);
        if ($res){
            $matches = array();
            while ($row = $res->fetch_row()){
                if($child_id == $row[1]){
                    return 0; //exactly the same already exists
                }
                $dtl_ID = $row[0];
            }
            $res->close();
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) '.
            "VALUES ($parent_id, ".$detailTypeId.", $child_id, $addedByImport )");

        $res = 1;
        if(!($mysqli->insert_id>0)) {$res=-1;}
    }

    return $res;

}

/**
 * Adds or updates a resource pointer detail in a source record, pointing to a target record.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $source_id The ID of the record where the pointer detail will be added/updated.
 * @param int $target_id The ID of the record to be pointed to.
 * @param \hserv\System $system The Heurist system object.
 * @param int $source_id The ID of the source record where the pointer detail will be added or updated.
 * @param int $target_id The ID of the target record to be pointed to.
 * @param int $dty_ID The detail type ID (field ID) of the resource pointer field in the source record.
 * @param bool $to_replace If true, and a pointer of this `$dty_ID` already exists in the `$source_id` record,
 *                         its `dtl_Value` (target) will be updated to the new `$target_id`.
 *                         If false, and such a pointer already exists (even if pointing to a different target),
 *                         no change is made. If multiple pointers of the same `$dty_ID` exist and `$to_replace` is true,
 *                         all existing ones are deleted before the new one is added.
 * @return int Returns:
 *             - -1: If there's a database error during insertion/deletion, or if input parameters (`$source_id`, `$target_id`, `$dty_ID`) are invalid. An error is added to the system object.
 *             -  0: If the exact `$source_id` -> `$target_id` link via `$dty_ID` already exists, or if a link with `$dty_ID` exists on `$source_id` and `$to_replace` is false.
 *             -  1: If a new pointer detail was successfully inserted, or if existing pointer(s) were successfully replaced.
 */
function addPointerField($system, $source_id, $target_id, $dty_ID, $to_replace){

    $res = 0;

    $mysqli = $system->getMysqli();

        $dtl_ID = -1;
        $source_id = intval($source_id);
        $target_id = intval($target_id);
        $dty_ID = intval($dty_ID);

        if(!($source_id>0 && $target_id && $dty_ID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Wrong paramters for records link creation');
            return -1;
        }

        //check that link already exists
        $target_IDs = mysql__select_assoc2($mysqli, 'SELECT rl_DetailID, rl_TargetID FROM recLinks WHERE rl_SourceID='.$source_id
                //.' AND rl_TargetID='.$target_id
                .' AND rl_DetailTypeID='.$dty_ID);
        if(!empty($target_IDs)){
            if(in_array($target_id, $target_IDs)){
                return 0; //such link already exists
            }
            if($to_replace){
                //remove existing one
                $dtl_IDs = array_keys($target_IDs);
                if(count($dtl_IDs)==1){
                    $mysqli->query('DELETE FROM recDetails WHERE dtl_ID ='.intval($dtl_IDs[0]));
                }else{
                    $dtl_IDs = prepareIds($dtl_IDs);
                    $mysqli->query('DELETE FROM recDetails WHERE dtl_ID IN ('.implode(',',$dtl_IDs).')');
                }
            }
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) '.
            "VALUES ($source_id, $dty_ID, $target_id)");

        $res = 1;
        if(!($mysqli->insert_id>0)){
            $system->addError(HEURIST_DB_ERROR, 'Can not add record pointer field', $mysqli->error);
            $res=-1;
        }

    return $res;
}


/**
 * Validates if the provided access string is one of the allowed Heurist visibility values.
 * Allowed values are 'viewable', 'hidden', 'public', 'pending'.
 * If the value is invalid, an error is added to the system object.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $access The access string to validate.
 * @return bool True if the access string is invalid (wrong), false if it is valid.
 */
function isWrongAccessRights($system, $access){
    if ($access=='viewable' || $access=='hidden' || $access=='public' || $access=='pending') {
        return false;
    }else{
        $system->addError(HEURIST_INVALID_REQUEST, 'Non-owner visibility value is missing or invalid');
        return true;
    }
}

/**
* Verifies access right value and is the current user able to change ownership for given record
*
* This function performs a series of checks to determine if the current user has the authority
* to modify the ownership and/or non-owner visibility of a given record.
* It considers:
* - Current user's administrative status.
* - Current user's membership in the record's current owner group(s).
* - Current user's membership in the proposed new owner group(s).
* - System settings like 'sys_SetPublicToPendingOnEdit'.
* - Validity of the proposed access string.
*
* The `$owner_grps` and `$access` parameters are passed by reference and may be modified
* by this function (e.g., to set default ownership if none is proposed, or to change
* 'public' to 'pending' based on system settings).
*
* @param \hserv\System $system The Heurist system object.
* @param int $recID The ID of the record whose ownership and/or access permissions are being checked or changed.
* @param array|null &$owner_grps Passed by reference.
*                                On input: An array of proposed new owner user/group IDs for the record.
*                                          If empty or null, current ownership is assumed to be retained or defaulted.
*                                On output: The actual array of owner group IDs that will be applied or checked against.
*                                           This may be the input array, or the record's current owner(s) if the input was empty.
*                                           Can also become `[0]` if "everyone" is proposed.
* @param string|null &$access Passed by reference.
*                             On input: The proposed new non-owner visibility string (e.g., 'public', 'viewable', 'hidden', 'pending').
*                                       If empty or null, the current visibility is assumed to be retained.
*                             On output: The actual access string that will be applied or checked against.
*                                        This may be the input string, the record's current visibility, or adjusted
*                                        (e.g., 'public' changed to 'pending' if `sys_SetPublicToPendingOnEdit` is active).
* @param array &$rectypes Passed by reference. An array that will be populated with `[$recID => $recTypeID]`
*                         for the given record, if the checks are generally successful up to that point.
* @return bool True if the current user is authorized to make the proposed (or defaulted) changes to ownership and access,
*              and the access string is valid.
*              False otherwise. Specific error messages are added to the system object if checks fail.
*/
function recordCanChangeOwnerwhipAndAccess($system, $recID, &$owner_grps, &$access, &$rectypes)
{

    $mysqli = $system->getMysqli();
    $recID = intval($recID);
    //get current values
    $query = 'select rec_OwnerUGrpID, rec_NonOwnerVisibility, rec_RecTypeID from Records where rec_ID = '.$recID;
    $res = $mysqli->query($query);

    if($res){
        $record = $res->fetch_assoc();
        $res->close();
    } else {
        $system->addError(HEURIST_DB_ERROR, 'Cannot get record', $mysqli->error);
        return false;
    }
    //get group permissions
    $isEveryOne = true;
    $current_owner_groups = null;
    if($record["rec_OwnerUGrpID"]>0){ //not everyone
        $isEveryOne = false;
        $query = 'select rcp_UGrpID from usrRecPermissions where rcp_Level="edit" AND rcp_RecID = '.$recID; //not used
        $current_owner_groups = mysql__select_list2($mysqli, $query);

    }
    if(!$current_owner_groups) {$current_owner_groups = array();}
    array_unshift($current_owner_groups, $record["rec_OwnerUGrpID"]);//add to begin of array

    if(count($current_owner_groups)==1 && !($current_owner_groups[0]>=0)){
        //rare case when current record has wrong value
        $current_owner_groups = array($system->getUserId());
    }

    //$ownerid_old = @$record["rec_OwnerUGrpID"];//current ownership
    //new owners are not defined - take current one
    if(isEmptyArray($owner_grps) || !($owner_grps[0]>=0)){
        $owner_grps = $current_owner_groups;
    }
    if(array_search(0, $owner_grps, true)!==false){ //there is "everyone"
        $owner_grps = array(0);
    }

    //1. Can current user edit this record?
    // record is not "everyone" and current user is_admin or itself or member of group
    if (!$isEveryOne  && !($system->isAdmin() || $system->isMember($current_owner_groups) || $system->isGuestUser() )){

        $system->addError(HEURIST_REQUEST_DENIED,
            'Current user does not have sufficient authority to change the record ID:'.$recID
            .'. User must be either the database administrator or member of the group'
            .(count($current_owner_groups)>1?'s':'')
            .' that own'
            .(count($current_owner_groups)>1?'':'s').' this record');
        return false;
    }

    //2. Can current user change ownership of this record?
    if(!$system->isAdmin()){

        if($isEveryOne  && $owner_grps[0]>0){
            //C. Only DB admin can change "Everyone" record to group record
            $system->addError(HEURIST_REQUEST_DENIED,
                'User does not have sufficient authority to change public record to group record');
            return false;

        }else{

            //check that new ownership is different
            //A. new owners
            foreach($owner_grps as $grp){
                if(array_search($grp, $current_owner_groups)===false){
                    if(!$system->isMember($grp)){
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'Cannot set ownership of record to the group without membership in this group', 'Group#'.$grp);
                        return false;
                    }
                }
            }
            //B. owners to remove
            foreach($current_owner_groups as $grp){
                if(array_search($grp, $owner_grps)===false){
                    if(!$system->hasAccess($grp)){
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'Cannot change ownership. User does not have ownership rights. '
                            .'User must be either database administrator, record owner or administrator or record\'s ownership group',
                            'Group#'.$grp);
                        return false;
                    }
                }
            }
        }

    }


    //---------------------------
    //change public to pending in case db system preferences
    if($access=='public' && $record["rec_NonOwnerVisibility"]=='public'
    && $system->settings->get('sys_SetPublicToPendingOnEdit')==1){
        $access='pending';
    }elseif(!$access){
        $access = $record["rec_NonOwnerVisibility"];
    }
    //if defined and wrong it fails
    if($access && isWrongAccessRights($system, $access))
    {
        return false;
    }

    //return record type for given record id
    if(is_array($rectypes)){
        $rectypes[$recID] = $record["rec_RecTypeID"];
    }

    return $res;


}

/**
 * Finds and updates calculated fields in records of other types that might be affected
 * by changes in records of the given record type (`$rty_ID`).
 *
 * This function identifies all calculated fields (`defCalcFunctions`) that list `$rty_ID`
 * in their `cfn_RecTypeIDs` (meaning they depend on this record type).
 * It then finds all record types (`defRecStructure.rst_RecTypeID`) that use these
 * calculation functions and triggers `recordUpdateCalcFields` for those record types.
 * This ensures that dependent calculations are refreshed when underlying data changes.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $rty_ID The record type ID that has been modified, potentially affecting other calculated fields.
 * @return void
 */
function findAndUpdateAffectedCalcFields( $system, $rty_ID ){

    $mysqli = $system->getMysqli();

    $query = 'SELECT cfn_ID FROM defCalcFunctions WHERE find_in_set('.$mysqli->real_escape_string($rty_ID).',cfn_RecTypeIDs) <> 0';
    $field_ids = mysql__select_list2($mysqli, $query);

    if(!isEmptyArray($field_ids)){

        $query = 'SELECT rst_RecTypeID WHERE rst_CalcFunctionID IN ('.implode(',',$field_ids).')';
        $rectype_ids = mysql__select_list2($mysqli, $query);

        if(!isEmptyArray($rectype_ids)){
            recordUpdateCalcFields($system, null, $rectype_ids);
        }
    }
}

/**
 * Updates calculated fields for specified records or record types.
 *
 * Iterates through records and their configured calculated fields (`rst_CalcFunctionID`),
 * executes the Smarty template defined in `cfn_FunctionSpecification` for each,
 * and updates the `dtl_Value` of the calculated field in `recDetails`.
 *
 * This function identifies records and their associated calculated fields (defined by `rst_CalcFunctionID`
 * in `defRecStructure`) and recomputes their values. The calculation itself is performed by
 * executing a Smarty template (`cfn_FunctionSpecification` from `defCalcFunctions`) via `executeSmarty`.
 *
 * The scope of records to update can be:
 * - A specific record ID or list of record IDs.
 * - All records of a specific record type ID or list of record type IDs.
 * - All records in the database (if both `$recID` and `$rty_ID` are null).
 *
 * It handles progress tracking for large operations if a `$progress_session_id` is provided.
 * It collects statistics on how many fields were updated, cleared (new value is empty), or remained unchanged.
 * Errors encountered during Smarty execution (e.g., bad template syntax) are collected.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int|array|null $recID A single record ID, an array of record IDs, or null.
 *                              If null, the function will process records based on `$rty_ID`.
 * @param int|array|null $rty_ID A single record type ID, or an array of record type IDs.
 *                               If `$recID` is provided, `$rty_ID` is used to determine the record type(s) of those specific records.
 *                               If `$recID` is null, all non-temporary records of the specified type(s) in `$rty_ID` are processed.
 *                               If both `$recID` and `$rty_ID` are null, all record types and all their non-temporary records are processed.
 * @param string|null $progress_session_id Optional. A session ID for tracking progress of large update operations.
 *                                         If provided and the operation involves many records, progress updates will be logged.
 * @return array|false An associative array containing statistics and results of the update.
 *                     Structure for multi-record updates (`$rec_count > 1`):
 *                     `[
 *                         'fld_changed' => (int)count_fields_new_value_different,
 *                         'fld_same' => (int)count_fields_new_value_same,
 *                         'fld_cleared' => (int)count_fields_new_value_empty,
 *                         'rec_updates' => (int)count_records_with_at_least_one_updated_field,
 *                         'rec_cleared' => (int)count_records_with_at_least_one_cleared_field,
 *                         'rec_processed' => (int)total_records_processed,
 *                         'rec_total' => (int)total_records_targeted,
 *                         'errors' => (array) ['rty_ID.dty_ID' => 'message', ...],
 *                         'q_updates' => (string) 'ids:xxx,yyy...' (query for updated records, max 1000),
 *                         'q_cleared' => (string) 'ids:aaa,bbb...' (query for records with cleared fields, max 1000)
 *                     ]`
 *                     Structure for single record update (`$rec_count <= 1`):
 *                     `['errors' => (array) ['rty_ID.dty_ID' => 'message', ...]]`
 *                     Returns `false` on critical database errors (e.g., cannot fetch record type, cannot save field).
 *                     Returns `['message' => 'Smarty init error...']` or `['message' => 'Operation terminated...']`
 *                     in case of Smarty setup failure or user termination via progress session.
 */
function recordUpdateCalcFields($system, $recID, $rty_ID=null, $progress_session_id=null)
{
    $mysqli = $system->getMysqli();

    $rectypes = null;
    $rec_count = 0;

    if($recID!=null && !isEmptyArray($recID)){ //for selected set of records
        //group records by rectype
        $query = 'select rec_RecTypeID, rec_ID from Records where rec_ID in ('
                        .implode(',',$recID).') ORDER BY rec_RecTypeID';

        $rectypes = array();
        $rty_ID = null;
        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){
                if($rty_ID != $row[0]){
                    if($rty_ID && is_array(@$rectypes[$rty_ID])){
                        $rec_count = $rec_count + count($rectypes[$rty_ID]);
                    }
                    $rty_ID = $row[0];
                    $rectypes[$rty_ID] = array();
                }
                array_push($rectypes[$rty_ID], $row[1]);
            }
            $res->close();
        }
        if($rty_ID && is_array(@$rectypes[$rty_ID])){
          $rec_count = $rec_count + count($rectypes[$rty_ID]);
        }

    }elseif($recID>0){

        //find record type if not defined
        if(!(isset($rty_ID) && $rty_ID>0)){
            $rty_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
            if(!($rty_ID>0)){
                $system->addError(HEURIST_DB_ERROR, 'Cannot get record for calculation fields update. Rec#'.$recID);
                return false;
            }
        }

        $rectypes = array($rty_ID=>array($recID));
        $rec_count = 1;
    }else //record is not defined - update all records
    {

        if($rty_ID!=null && !is_array($rty_ID)){
            $rty_ID = prepareIds($rty_ID);
        }

        if(isEmptyArray($rty_ID)){
            //all rectypes - entire database
            $rty_ID = mysql__select_list2($mysqli, 'SELECT rty_ID FROM defRecTypes');
            $rec_count = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records WHERE (NOT rec_FlagTemporary)');
        }else{
            $rec_count = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records '
            .'WHERE (rec_RecTypeID IN ('.implode(',',$rty_ID).')) AND (NOT rec_FlagTemporary)');
        }
        $rectypes = array();
        foreach ($rty_ID as $id){
            $rectypes[$id] = '*';
        }
    }

    try{
        $smarty = smartyInit($system);
    } catch (Exception $e) {
        return array('message'=>'Smarty init error: '.$e->getMessage());
    }

    if($progress_session_id>0 && $rec_count>100){
        mysql__update_progress(null, $progress_session_id, true, '0,'.$rec_count);
    }else{
        $progress_session_id = 0;
    }

    $progress_count = 0;

    $updates = array();// record ids
    $cleared = array();// record ids
    $errors  = array();// formulae errors

    $updated_count = 0;   // updated fields
    $cleared_count = 0;   // cleared fields
    $unchanged_count = 0; // unchanged fields

    $heuristRec = new ReportRecord($system);//helper class - to obtain access to heurist data from smarty report

    foreach ($rectypes as $rty_ID => $record_ids){

        //find calculation fields for this record type
        // dty_ID => cfn_FunctionSpecification
        $formulae = mysql__select_assoc2($mysqli,
            'SELECT rst_DetailTypeID, cfn_FunctionSpecification FROM defRecStructure, defCalcFunctions '
            .' WHERE rst_RecTypeID='.$rty_ID
            .' AND cfn_ID=rst_CalcFunctionID');

        //there are not calculation fields for this record type
        if(isEmptyArray($formulae)){

            if($record_ids=='*'){
               $cnt = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records '
                .'WHERE (rec_RecTypeID='.$rty_ID.') AND (NOT rec_FlagTemporary)');
               $progress_count = $progress_count + $cnt;
            }elseif (is_array($record_ids)) {
               $progress_count = $progress_count + count($record_ids);
            }

            continue; //no formulae for this record type
        }

        $keep = $progress_count;

        //@todo calculation field can not be repeatable
        foreach($formulae  as $dty_ID => $formula){

            $idx = 0;
            $rows = null;
            $mode = null;
            if($record_ids=='*'){
                $query = 'SELECT rec_ID FROM Records WHERE (rec_RecTypeID='.intval($rty_ID).') AND (NOT rec_FlagTemporary)';
                $rows = $mysqli->query($query);
                //$mode = 'string:';
            }elseif (count($record_ids)>1){
                //$mode = 'string:';
            }

            $params = array();
            $params['template'] = $formula;

            $progress_count = $keep; //reset - each record can have several calculated fields

            while(true){ //loop for records

                if($record_ids=='*'){
                     $row = $rows->fetch_row();
                     if($row){
                         $recID = intval($row[0]);
                     }else{
                         break;
                     }
                }else{
                    if(is_array($record_ids) && $idx<count($record_ids)){
                         $recID = intval($record_ids[$idx]);
                         $idx++;
                    }else{
                         break;
                    }
                }

                $params['records'] = array($recID);

                $new_value = executeSmarty($system, $smarty, $params, $mode, $heuristRec);

                if(is_array($new_value)){
                    if($new_value[0]=='fatal'){  //fatal smarty error
                        if($progress_session_id>0){
                            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
                        }
                        return array('message'=>$new_value[1]);
                    }else{
                        //formula has errors - skip
                        $errors[$rty_ID.'.'.$dty_ID] = $new_value[1];
                        break;
                    }
                }elseif($new_value == 'NAN' || $new_value == 'INF' || $new_value == SQL_NULL){
                    // relpace not a number, infinite, and null with an empty string
                    $new_value = '';
                }

                $current_value = mysql__select_value($mysqli,
                    "SELECT dtl_Value FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=$dty_ID");

                if($new_value!=null) {$new_value = trim($new_value);}

                if($current_value==$new_value){
                    $unchanged_count++;
                }else{

                    if($current_value!=null && $current_value!=''){
                        $query = "DELETE FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=$dty_ID";
                        $mysqli->query($query);
                    }

                    if($new_value!=null && $new_value!=''){
                        $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) '
                        .' VALUES ('.$recID.', '.$dty_ID.', ? )';
                        $stmt = $mysqli->prepare($query);

                        $stmt->bind_param('s', $new_value);
                        if(!$stmt->execute()){
                            $syserror = $mysqli->error;
                            $stmt->close();
                            $system->addError(HEURIST_DB_ERROR, "Cannot save calculated field $dty_ID for record # $recID", $syserror);
                            return false;
                        }
                        $stmt->close();

                        $updates[] = $recID;
                        $updated_count++;
                    }else{
                        $cleared[] = $recID;
                        $cleared_count++;
                    }
                }
                $progress_count++;

                if($progress_session_id>0 && ($progress_count % 100 == 0)){
                    $session_val = $progress_count.','.$rec_count;
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
                        return array('message'=>'Operation has been terminated by user');
                    }
                }

            }//while records
        }//for formulae

    }//for record types

    if($rec_count>1){

        //remove session file
        if($progress_session_id>0){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        $q_updates = '';
        $q_cleared = '';

        if(count($updates)>1000){
            $q_updates = 'ids:'.array_slice($updates, 0, 1000);
        }elseif(!empty($updates)){
            $q_updates = 'ids:'.implode(',',$updates);
        }
        if(count($cleared)>1000){
            $q_cleared = 'ids:'.array_slice($cleared, 0, 1000);
        }elseif(!empty($cleared)){
            $q_cleared = 'ids:'.implode(',',$cleared);
        }

        return array(
            // fields
            'fld_changed'=>$updated_count,
            'fld_same'=>$unchanged_count,
            'fld_cleared'=>$cleared_count,
            //records
            'rec_updates'=>count($updates),
            'rec_cleared'=>count($cleared),
            'rec_processed'=>$progress_count,
            'rec_total'=>$rec_count,
            //errors in formula  rty_ID.dty_ID - message
            'errors'=>$errors,
            //queries
            'q_updates'=>$q_updates, 'q_cleared'=>$q_cleared);
    }else{
            return array('errors'=>$errors);
    }
}

/**
 * Executes a Smarty template, typically used for calculated fields.
 *
 * Assigns a `ReportRecord` object (as `$heurist`) and the target record(s) (as `$results` and `$r`)
 * to the Smarty instance, then fetches the template output.
 *
 * The primary use case is to render a calculated field's value. The template (`$params['template']`)
 * can use the `$heurist` object to fetch data and the `$r` variable (representing the first record
 * in `$params['records']`) for context-specific calculations.
 *
 * @param \hserv\System $system The Heurist system object (used by ReportRecord).
 * @param \Smarty $smarty A pre-configured Smarty instance.
 * @param array $params An associative array containing:
 *                      - 'template': (string, required) The Smarty template string/code to execute.
 *                      - 'records': (array, required) An array of record IDs. The first ID is used to set
 *                                   the context for `$r` in Smarty. If empty, the function returns an empty string.
 * @param string|null $mode Optional. The Smarty fetch mode (e.g., 'eval:', 'string:').
 *                          'eval:' compiles the template every time.
 *                          'string:' uses a compiled template if available.
 *                          Defaults to 'eval:'.
 * @param \hserv\report\ReportRecord|null $heuristRec Optional. A pre-initialized `ReportRecord` object.
 *                                                  If null, a new one is instantiated.
 * @return string|array Returns the string output of the rendered Smarty template on success.
 *                      Returns an array `['error', string $message]` if:
 *                      - The 'template' in `$params` is empty or not provided.
 *                      - A Smarty exception occurs during template fetching.
 *                      Returns an empty string if `$params['records']` is empty.
 */
function executeSmarty($system, $smarty, $params, $mode=null, $heuristRec=null){

  $content = (array_key_exists('template',$params)?$params['template']:null);

  if($content==null || $content=='') {return array('error', 'Formula not defined');}

  $record_ids = @$params['records'];

  if(!is_array($record_ids) || empty($record_ids)) {return '';}

  $mode = $mode ?$mode:'eval:';//string: - use complied or eval: - compile every time

  /*
  $template_folder = $smarty->getTemplateDir();
  if(is_array($template_folder)) {$template_folder = $template_folder[0];}

  //$user = $system->getCurrentUser();'_'.$user['ugr_Name']
  $template_file = $template_folder.'calc_fld_'.uniqid().'.tpl';
  $file = fopen ($template_file, "w");
  fwrite($file, $content);
  fclose ($file);
  */

  //@todo use ReportExecute class
  if($heuristRec==null) {$heuristRec = new ReportRecord($system);}

  $smarty->assign('heurist', $heuristRec);

  $smarty->assign('results', $record_ids);//assign
  $smarty->error_reporting = 0;
  $smarty->debugging = false;

  $smarty->assign('r', $heuristRec->getRecord($record_ids[0]));

  try{
      $output = $smarty->fetch($mode.$content);

  } catch (Exception $e) {
      $output = array('error', 'Exception on field calculation: '.$e->getMessage());
  }
  //unlink($file);
  return $output; //new value
}

/**
 * Calculates and updates a record's title based on its title mask.
 *
 * If a mask string is provided, it's used directly. Otherwise, the mask is fetched
 * from the record type definition. If the generated title is empty or indicates an error,
 * and `$recTitleDefault` is provided, the default title is used.
 * The record's `rec_Title` (truncated to 1023 chars if longer) and `rec_Modified` fields are updated in the database.
 *
 * The title generation process is as follows:
 * 1. If `$rectype_or_mask` is a string, it's used directly as the title mask.
 * 2. If `$rectype_or_mask` is a numeric record type ID, the title mask (`rty_TitleMask`) is fetched from `defRecTypes` for that type.
 * 3. If no mask is provided or found, and `$recTitleDefault` is also null, an error message is returned.
 * 4. The `TitleMask::fill()` method is called to generate the title based on the mask and record data.
 * 5. If `TitleMask::fill()` returns null or an error string, and `$recTitleDefault` is provided, `$recTitleDefault` is used as the title.
 * 6. If the resulting title is empty, an error message "Can't get title for #..." is returned.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The ID of the record whose title is to be updated. Must be a positive integer.
 * @param int|string $rectype_or_mask Either the numeric record type ID (to fetch `rty_TitleMask` from `defRecTypes`)
 *                                    or the title mask string itself.
 * @param string|null $recTitleDefault A default title to use if the mask generation fails or results in an empty string.
 *                                     If this is null and mask generation fails, the function may return an error string or false.
 * @return string|false The new title string that was saved to the record on success.
 *                      Returns a specific error string (e.g., "Can't get title for #...") if the title could not be generated and no suitable default was available.
 *                      Returns `false` if there's a database error (e.g., cannot get record type, cannot get mask, cannot save title).
 *                      Errors are also added to the system object in case of DB issues.
 */
function recordUpdateTitle($system, $recID, $rectype_or_mask, $recTitleDefault)
{

    $mysqli = $system->getMysqli();

    $mask = null;
    $rectype = null;

    if(is_numeric($rectype_or_mask) && $rectype_or_mask>0){
        $rectype = $rectype_or_mask;
    }elseif(!isEmptyStr($rectype_or_mask)){
        $mask = $rectype_or_mask;
    }

    $recID = intval($recID);

    if($mask == null)
    {
        if(!isPositiveInt($rectype)){
            $rectype = mysql__select_value($mysqli, "select rec_RecTypeID from Records where rec_ID=".$recID);
        }

        if(!isPositiveInt($rectype)){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get record for title mask update. Rec#'.$recID);
            return false;
        }

        $mask = mysql__select_value($mysqli, 'select rty_TitleMask from defRecTypes where rty_ID='.$rectype);
        if(!$mask){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get title mask for record type', $mysqli->error);
            return false;
        }
    }

    TitleMask::initialize($system);
    $new_title = TitleMask::fill($recID, $mask);

    if(($new_title==null || strpos($new_title, 'Title mask not generated.') === 0) && $recTitleDefault!=null) {
        $new_title = $recTitleDefault;
    }

    $new_title = trim($new_title);

    if(isEmptyStr($new_title)){
        return 'Can\'t get title for #'.$recID;
    }

    if(mb_strlen($new_title)>1023){
        $new_title = mb_substr($new_title,0,1023);
    }

    $params = ['ss', $new_title, date(DATE_8601)];
    $res = mysql__exec_param_query($mysqli, "UPDATE Records set rec_Title=?, rec_Modified=? where rec_ID={$recID}", $params );
    if($res!==true){
        $system->addError(HEURIST_DB_ERROR, 'Cannot save record title', $res);
        return false;
    }

    return $new_title;
}

/*
*   $record - new values for record
*   $detailValues -  array ready to insert (dtl_DetailTypeID=>, dtl_Value=>, dtl_Geo=>....)
*   $update_mode
*   - 2 Add new values without deletion of existing values (duplicates are ignored)
*   - 3 Add new values only if field is empty (new values ignored for non-empty fields)
*   - 4 Replace existing values with new values, retain existing value if no new value supplied
*
*   It fetches the existing details of a record from the database using `recordSearchDetailsRaw`.
*   Then, based on the `$update_mode`, it merges the `$detailValuesNew` with the existing details:
*   - Mode 2 (Add, no delete, ignore duplicates): Adds new values if they don't already exist (case-insensitive for short strings, exact for geo/long strings).
*   - Mode 3 (Add if empty): Adds new values only for detail types that have no existing values.
*   - Mode 4 (Replace, retain if no new): Replaces all existing details of a certain type with the new ones of that type. If a detail type existed before but no new values are provided for it, the old values are effectively removed (as the main `recordSave` logic deletes all old details first).
*   The function returns a consolidated list of detail values that should be saved to the database.
*
*   Note: This function modifies the `$detailValuesNew` array by incorporating existing values based on the update mode.
*   The actual update of record header fields like URL or ScratchPad is expected to be handled by the calling function (`recordSave`)
*   which might use the original record's values if new ones are not provided in certain update modes.
*
* @param \hserv\System $system The Heurist system object.
* @param array $record The incoming record data as passed to `recordSave`. Must contain 'ID'.
*                      This function primarily uses `$record['ID']` to fetch existing details.
* @param array $detailValuesNew An array of new detail values intended to be saved, formatted as
*                               `[['dtl_DetailTypeID'=>ID, 'dtl_Value'=>value, ...], ...]`.
*                               This array will be augmented with existing details based on the `$update_mode`.
* @param int $update_mode Defines how existing details are merged with new ones:
*                         - 2: Add new values without deletion of existing values (duplicates are ignored based on value and type).
*                         - 3: Add new values only if the field (detail type) is currently empty in the database.
*                         - 4: Replace existing values with new values for a given detail type. If new values are supplied for a type, all old values of that type are discarded.
*                              (Effectively, this mode means the returned array will contain only `$detailValuesNew` plus any existing details for types *not* present in `$detailValuesNew`).
*                              The term "retain existing value if no new value supplied" in the original comment refers more to the overall `recordSave` behavior for header fields, not details here.
* @return array The consolidated array of detail values that reflects the merge of new and existing data
*               according to the specified `$update_mode`. This array is then used by `recordSave`
*               to write to the database after deleting all previous details.
*/
function prepareRecordForUpdate($system, $record, $detailValuesNew, $update_mode){

    /*
    todo
    $rec_url = USanitize::sanitizeURL(@$record['URL']);
    $rec_spad = @$record['ScratchPad'];
    $rec_temp = (@$record['FlagTemporary']==1)?1:0;
    */

    $detailValues = recordSearchDetailsRaw($system, $record['ID']);
    $processed_dtyID = array();

    foreach($detailValuesNew as $idx=>$values){

        $dty_ID = $values['dtl_DetailTypeID'];

        if(in_array($dty_ID,$processed_dtyID)){
            continue;
        }
        $processed_dtyID[] = $dty_ID;

        //find in original
        $is_found = false;
        foreach($detailValues as $idx2=>$val){
            if($val['dtl_DetailTypeID']==$dty_ID){
                $is_found = true;
                break;
            }
        }

        if($is_found){  //exists

            if($update_mode==3){
                continue; //Add new values only if field is empty
            }

            if($update_mode==4){
                //replace all fields of certain type with new value
                foreach($detailValues as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        unset($detailValues[$idx2]);
                    }
                }
                foreach($detailValuesNew as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        array_push($detailValues, $val);
                    }
                }

            }else{ //$update_mode==2  always add but prevent duplications

                $details_lc = array();
                foreach($detailValues as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        if(@$val['dtl_Geo']){
                            if(strlen(@$val['dtl_Geo'])<1000){
                                $details_lc[] = $val['dtl_Geo'];
                            }
                        }elseif($val['dtl_Value'] && strlen($val['dtl_Value'])<200){
                            $details_lc[] = trim_lower_accent($val['dtl_Value']);
                        }
                    }
                }

                foreach($detailValuesNew as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){

                        $need_add = false;

                        if(@$val['dtl_UploadedFileID']>0){
                            $need_add = true;
                        }elseif(@$val['dtl_Geo']){

                            if(strlen($val['dtl_Geo'])>=1000
                            || array_search($val['dtl_Geo'], $details_lc, true)===false){
                                $need_add = true;
                            }

                        }elseif(strlen($val['dtl_Value'])>=200
                        || array_search(trim_lower_accent($val['dtl_Value']), $details_lc, true)===false)
                        {
                            $need_add = true;
                        }

                        if($need_add){
                            array_push($detailValues, $val);
                        }
                    }
                }
            }

        }else{ //not exists
            //add new values
            foreach($detailValuesNew as $idx2=>$val){
                if($val['dtl_DetailTypeID']==$dty_ID){
                    array_push($detailValues, $val);
                }
            }
        }
    }//for

    return $detailValues;
}

/**
* @todo make private
*
* uses getHTMLPurifier, checkMaxLength
*
* This is a critical internal function responsible for validating and preparing raw detail data
* before it's saved to the database. It performs a wide range of checks and transformations:
* - Normalizes input `details` keys (e.g., "t:1" to "1").
* - Fetches detail type definitions (`defDetailTypes`) and record structure (`defRecStructure`)
*   including required fields and child pointer configurations.
* - Iterates through each detail value, applying validation rules based on its `dty_Type`:
*   - **Text types (freetext, blocktext):** Checks for emptiness, applies HTML purification (unless exempted),
*     and handles special splitting for large CMS extended descriptions. Validates max length.
*   - **Date:** Converts various input formats (including 'today', 'now', temporal JSON) to a standard
*     database format using `Temporal::getValueForRecDetails`.
*   - **Float, Integer, Boolean, Year:** Validates against expected numeric/boolean formats.
*   - **Enum, Relationtype:** Validates term IDs against allowed vocabularies and non-selectable terms,
*     using `isValidTerm`. Converts term labels to IDs if necessary.
*   - **Resource:** Validates that the target record ID exists and its record type conforms to
*     any constraints defined for the pointer field, using `isValidRectype`. Marks details
*     as `dtl_ParentChild` if they correspond to a `rst_CreateChildIfRecPtr` field.
*   - **File:** Handles file uploads/references. Can generate thumbnails from URLs (`UImage::makeURLScreenshot`),
*     register base64 encoded images, or link existing `ulf_ID`s.
*   - **Geo:** Prepares geographic data using `prepareGeoValue` to get WKT and geo type.
* - Checks for missing required fields (if `$validation_mode > 1`).
* - Handles different validation levels (`$validation_mode`):
*   - 0: Minimal validation, mostly skips errors, but still processes values.
*   - 1: Skips resource pointer validation.
*   - 2: Full validation.
* - Collects errors and adds them to the system object. If errors occur, returns false.
* - If `rst_DefaultValue` is defined for a missing required field, it attempts to add this default value.
*
* @internal This function is intended for internal use by `recordSave` and related functions.
*
* @param \hserv\System $system The Heurist system object.
* @param int $rectype The record type ID of the record whose details are being prepared.
* @param array $record The raw record data as passed to `recordSave`. Crucially, this includes
*                      `$record['details']` (the detail values to process) and potentially
*                      `$record['details_visibility']`.
* @param int $validation_mode Controls the level of validation:
*                             0: No validation (or minimal, skips most errors).
*                             1: Don't validate resource pointers' existence or type constraints.
*                             2: Full validation (default behavior in `recordSave`).
* @param int $recID The ID of the record being processed. Used primarily for error messages and context
*                   (e.g., naming snapshot images for file details).
* @param int $modeImport Indicates if operating in an import context, which can alter some validation stringency.
*                        0: Normal operation.
*                        1: Standard import mode.
*                        2: Special import (e.g., Zotero/FAIMS), may have further relaxed validation for some types.
* @return array|false An array of prepared detail values on success, ready for database insertion. Each element is an
*                     associative array like `['dtl_DetailTypeID' => ..., 'dtl_Value' => ..., 'dtl_UploadedFileID' => ..., 'dtl_Geo' => ..., 'dtl_HideFromPublic' => ..., 'dtl_ParentChild' => ...]`.
*                     Returns `false` if any validation errors occur (and errors are added to `$system->addError()`).
*                     Also returns `false` if required fields are missing (after attempting to apply defaults) or if no valid details are produced.
*/
function _prepareDetails($system, $rectype, $record, $validation_mode, $recID, $modeImport)
{
    global $terms, $useNewTemporalFormatInRecDetails;

    $details = $record['details'];

    /*
    * $details is the form
    *    $details = array("t:1" => array("bd:234463" => "7th Ave"),
    *                      ,,,
    *                     "t:11" => array("0" => "p POINT (-73.951172 40.805661)"));
    * where t:id means detail type id  and bd:id means detail record id
    * new details are array values without a preceeding detail ID as in the last line of this example
    */

    //1. load record structure
    //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence
    //3. delete existing details
    //4. insert new set


    $mysqli = $system->getMysqli();

    //exlude empty and wrong entries         t:dty_ID:[0:value, 1:value]
    $details2 = array();
    foreach ($details as $dtyID => $pairs) {

        if( (is_array($pairs) && empty($pairs)) || $pairs=='') {continue;} //empty value

        if(preg_match("/^t:\\d+$/", $dtyID)){ //old format with t:NNN
            $dtyID = substr($dtyID, 2);
        }
        if(is_numeric($dtyID) && $dtyID>0){  //ignore header and supplementary fields
            $details2[$dtyID] = is_array($pairs)?$pairs:array($pairs);
        }
    }

    //get list of fieldtypes for all details
    $query = 'SELECT dty_ID, dty_Type FROM defDetailTypes WHERE dty_ID in (' . implode(',', array_keys($details2)) . ')';
    $det_types = mysql__select_assoc2($mysqli, $query);

    $det_required = array();
    if($validation_mode>1){
        //load list of required details except relmarker
        $query = 'SELECT rst_DetailTypeID, IF((rst_DisplayName=\'\' OR rst_DisplayName IS NULL), dty_Name, rst_DisplayName) as rst_DisplayName '
        .'FROM defRecStructure, defDetailTypes WHERE '
        ."rst_RecTypeID=$rectype and rst_RequirementType='required' and dty_ID=rst_DetailTypeID "
        ." and dty_Type!='relmarker' and dty_Type!='separator'";
        $det_required = mysql__select_assoc2($mysqli, $query);
    }

    $det_childpointers =  mysql__select_list($mysqli, "defRecStructure",
        "rst_DetailTypeID",
        "rst_RecTypeID=$rectype and rst_CreateChildIfRecPtr=1");


    //$query_size = 'select LENGTH(?)';
    //$stmt_size = $mysqli->prepare($query_size);

    $system->defineConstant('RT_CMS_HOME');
    $system->defineConstant('RT_CMS_MENU');
    $system->defineConstant('DT_EXTENDED_DESCRIPTION');

    //list of field ids that will not html purified
    $not_purify = array();
    /*if($system->defineConstant('DT_CMS_SCRIPT')){ array_push($not_purify, DT_CMS_SCRIPT);}
    if($system->defineConstant('DT_CMS_CSS')){ array_push($not_purify, DT_CMS_CSS);}
    if($system->defineConstant('DT_SYMBOLOGY')){ array_push($not_purify, DT_SYMBOLOGY);}
    if($system->defineConstant('DT_KML')){ array_push($not_purify, DT_KML);}
    if($system->defineConstant('DT_QUERY_STRING')){ array_push($not_purify, DT_QUERY_STRING);}
    if($system->defineConstant('DT_SERVICE_URL')){ array_push($not_purify, DT_SERVICE_URL);}*/
    if($system->defineConstant('DT_CMS_EXTFILES')){ array_push($not_purify, DT_CMS_EXTFILES);}
    // $purifier = USanitize::getHTMLPurifier();
    //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence

    $insertValues = array();
    $errorValues = array();
    $cntErrors = 0;

    foreach ($details2 as $dtyID => $values) {

        $splitValues = array();
        $idx_in_vis = 0;

        foreach ($values as $eltID => $dtl_Value) {

            if(!is_array($dtl_Value) && strlen(super_trim($dtl_Value))==0){
                $idx_in_vis++;
                continue;
            }

            $dtl_HideFromPublic = null;
            if(@$record['details_visibility'][$dtyID]){
                $dtl_HideFromPublic = (@$record['details_visibility'][$dtyID][$idx_in_vis]>0)?1:0;
            }
            $idx_in_vis++;

            $dval = array('dtl_DetailTypeID'=>$dtyID);

            $dtl_UploadedFileID = null;
            $dtl_Geo = null;
            $isValid = false;
            $err_msg = '';

            if(!(is_array($dtl_Value) || $det_types[$dtyID]=='geo' || $det_types[$dtyID]=='file')){
                $rval = $mysqli->real_escape_string( $dtl_Value );


                //special case: split huge web content
                if(defined('RT_CMS_MENU') && $rectype==RT_CMS_MENU && $dtyID==DT_EXTENDED_DESCRIPTION){
                    $lim = checkMaxLength2($rval);
                    //TEST $lim = 100;
                    if($lim>0){
                        //remove script tag
                        $dtl_Value = super_trim($dtl_Value);
                        $dtl_Value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $dtl_Value);

                        //$dtl_Value = $purifier->purify($dtl_Value);
                        //$dtl_Value = htmlspecialchars_decode( $dtl_Value );

                        $iStart = 0;
                        while($iStart<mb_strlen($dtl_Value)){
                            array_push($splitValues, mb_substr($dtl_Value, $iStart, $lim));
                            $iStart = $iStart + $lim;
                        }
                    }
                }else{
                    $err_msg = checkMaxLength('#'.$dtyID, $rval);
                    if($err_msg!=null) {break;}
                }
            }

            switch ($det_types[$dtyID]) {

                case "freetext":
                case "blocktext":
                    $len  = strlen(super_trim($dtl_Value));
                    $isValid = ($len > 0);
                    if(!$isValid ){
                        $err_msg = 'Value is empty';
                    }elseif(!in_array($dtyID, $not_purify)){
                        $dtl_Value = super_trim($dtl_Value);
                        $dtl_Value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $dtl_Value);

                        if($det_types[$dtyID]=="freetext"){ //remove non standard attributes
                        //(\w+)
                        $allowed = array('src','class','style','href');
                        $allowed2 = implode('=|',$allowed).'=';
                        $regex = ')[^>]))*((?:';
                        $allowed = implode('|',$allowed);
$dtl_Value = preg_replace('#<([A-Z][A-Z0-9]*)(\s*)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)[^>]*>#si','<$1$2$3$4$5>',$dtl_Value);
                        }

                    }
                    break;

                case "date":

                    if(is_array($dtl_Value)){ //date is temporal json array
                        $isValid = count($dtl_Value)>1 && (@$dtl_Value['timestamp'] || @$dtl_Value['start']);
                    }else{
                        $len  = strlen(super_trim($dtl_Value));
                        $isValid = ($len > 0);
                    }

                    if(!$isValid ){
                        $err_msg = 'Value is empty';
                    }else{

                        $dtl_Value = Temporal::getValueForRecDetails( $dtl_Value, $useNewTemporalFormatInRecDetails );

/* Use old plain temporals
                        }else{
                            //yesterday, today, tomorrow, now
                            $sdate = strtolower(super_trim($dtl_Value));
                            if($sdate=='today'){
                                $dtl_Value = date('Y-m-d');
                            }elseif($sdate=='now'){
                                $dtl_Value = date(DATE_8601);
                            }elseif($sdate=='yesterday'){
                                $dtl_Value = date('Y-m-d',strtotime("-1 days"));
                            }elseif($sdate=='tomorrow'){
                                $dtl_Value = date('Y-m-d',strtotime("+1 days"));
                            }elseif(strlen($dtl_Value)>=8 && strpos($dtl_Value,'-')==false){

                                try{
                                    $t2 = new DateTime($dtl_Value);

                                    $format = 'Y-m-d';
                                    if($t2->format('H')>0 || $t2->format('i')>0 || $t2->format('s')>0){
                                    //strlen($dtl_Value)>=12 || strpos($dtl_Value,'T')>7 || strpos($dtl_Value,' ')>7){
                                        if($t2->format('s')>0){
                                            $format .= ' H:i:s';
                                        }else{
                                            $format .= ' H:i';
                                        }
                                    }
                                    $dtl_Value = $t2->format($format);

                                }catch(Exception  $e){
                                    //skip conversion

                                }
                            }
                        }
*/
                    }
                    break;
                case "float":
                    $isValid = preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $dtl_Value);
                    //preg_match('/^0(?:[.]0*)?$/', $dtl_Value)
                    if(!$isValid ) {$err_msg = 'Not valid float value '.htmlspecialchars($dtl_Value);}
                    break;
                case "enum":
                case "relationtype":

                    if($validation_mode>1){

                        if(!$terms){
                            $terms = new DbsTerms($system, dbs_GetTerms($system));
                        }

                        $term_domain = ($det_types[$dtyID]=="enum"?"enum":"relation");

                        if (is_numeric($dtl_Value)){
                            $term_tocheck = $dtl_Value;
                        }else{
                            $term_tocheck = $terms->getTermByLabel($term_domain, $dtl_Value);//within domain
                        }
                        $isValid = isValidTerm($system, $term_tocheck, $term_domain, $dtyID, $rectype);
                        if($isValid){
                            $dtl_Value = $term_tocheck;
                        }else{
                            $trm = $terms->getTerm($dtl_Value);
                            $err_msg = 'Term ID '.htmlspecialchars($dtl_Value)
                            . ($trm!=null
                                ?( ' <i>'.htmlspecialchars($trm[0]).'</i> is not in the list of values defined for this field')
                                :' not found');
                        }
                    }else{
                        $isValid = (intval($dtl_Value)>0);
                    }

                    break;

                case "resource":

                    if($validation_mode>1){
                        //check if resource record exists
                        $rectype_tocheck = mysql__select_row($mysqli, 'select rec_RecTypeID, rec_Title '
                            .'from Records where rec_ID = '.$dtl_Value);//or dbs_GetRectypeByID from db_strucuture
                        if($rectype_tocheck){


                            //check that this rectype is valid for given detail (constrained pointer)
                            $isValid = isValidRectype($system, $rectype_tocheck[0], $dtyID, $rectype);
                            if(!$isValid){

                                $err_msg = '<div style="padding-left:30px">'
                                . _getRtConstraintNames($system, $dtyID, $rectype)
                                . '<br>Target ID:'.$dtl_Value.'  '.USanitize::sanitizeString($rectype_tocheck[1], false).DIV_E;


                                //$err_msg = 'Record type '.$rectype_tocheck.' is not valid for specified constraints';
                            }
                        }else{
                            $err_msg = 'Record with specified id '.htmlspecialchars($dtl_Value).' does not exist';
                        }
                    }else{
                        $isValid = (intval($dtl_Value)>0);
                        if(!$isValid){
                            $err_msg = 'Record ID '.htmlspecialchars($dtl_Value).' is not valid integer';
                        }
                    }
                    //this is parent-child resource (record pointer)
                    if($isValid && in_array($dtyID, $det_childpointers)){
                        $dval['dtl_ParentChild'] = true;
                    }

                    break;


                case "file": //@TODO

                    if($dtl_Value=='generate_thumbnail_from_url' && @$record['URL']){

                        $tmp_file = UImage::makeURLScreenshot($record['URL']);

                        if(!is_a($tmp_file,'stdClass')){
                            $err_msg = is_array($tmp_file) ?$tmp_file['error'] :('System message: '.$tmp_file);
                        }else{
                            $entity = new DbRecUploadedFiles($system);

                            $dtl_UploadedFileID = $entity->registerFile($tmp_file, null);//it returns ulf_ID

                            if($dtl_UploadedFileID===false){
                                $err_msg = $system->getError();
                                $err_msg = $err_msg['message'];
                                $system->clearError();
                            }else{
                                $dtl_UploadedFileID = $dtl_UploadedFileID[0];
                            }
                        }

                        if($err_msg!=''){
                            //send email to heurist team about fail generation from url
                            $msg = 'The thumbnailer fails to return an image '.$record['URL'].'. '.$err_msg;
                            sendEmail(HEURIST_MAIL_TO_ADMIN, 'The thumbnailer fails to return an image '.$system->dbname(), $msg);
                            $err_msg = '';
                            $dtl_Value = '';
                            $isValid = 'ignore';
                            break; //just ignore this value
                        }

                    }elseif(is_numeric($dtl_Value)){  //this is ulf_ID
                        $dtl_UploadedFileID = intval($dtl_Value);

                        //TODO !!! mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".dtl_UploadedFileID)) <=0 )

                    }elseif(is_string($dtl_Value)){  //this is base64 encoded image

                        //save encoded image as file and register it
                        $entity = new DbRecUploadedFiles($system);
                        $dtl_UploadedFileID = $entity->registerImage($dtl_Value, 'map_snapshot_'.$recID);//it returns ulf_ID
                        if( is_bool($dtl_UploadedFileID) && !$dtl_UploadedFileID ){
                            $dtl_UploadedFileID = -1; //fail
                            $err_msg = 'Can\'t register snapshot image';
                        }
                        if(is_array($dtl_UploadedFileID)){
                            $dtl_UploadedFileID = $dtl_UploadedFileID[0];
                        }


                    }else{  // new way - URL or JSON string with file data array (structure similar get_uploaded_file_info)
                        //TODO!!!!!
                        // $dtl_UploadedFileID = register_external($dtl_Value);
                        $dtl_UploadedFileID = intval(@$dtl_Value['ulf_ID']);
                    }


                    $dtl_Value = null;
                    $isValid = ($dtl_UploadedFileID>0);

                    if($validation_mode==0 && !$isValid) {$isValid = 'ignore';}

                    break;

                case "geo":

                    //note geoType can be not defined - detect it from dtl_Geo
                    list($dtl_Value, $dtl_Geo) = prepareGeoValue($mysqli, $dtl_Value);
                    if($dtl_Value===false){
                        $err_msg = $dtl_Geo;
                        $isValid = ($validation_mode==0)?'ignore':false;
                        if(!$isValid && $modeImport == 1){
                            $dval['dtl_Value'] = $values[$eltID];
                            $dval['dtl_UploadedFileID'] = null;
                            $dval['dtl_Geo'] = null;
                            array_push($insertValues, $dval);
                            $isValid = 'ignore';
                        }
                    }else{
                        $isValid = true;
                    }


                    /*
                    $res = $mysqli->query("select ST_asWKT(ST_GeomFromText('".addslashes($dtl_Geo)."'))");
                    if ($res){
                    if($res->fetch_row()){
                    $dtl_Value = $geoType;
                    $isValid = true;
                    }
                    $res->close();
                    }*/
                    break;
                    // retained for backward compatibility
                case "year":
                    $isValid = preg_match("/^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$/i", $dtl_Value);
                    if(!$isValid){
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid Year";
                    }
                    break;
                case "boolean":

                    $isValid = preg_match("/^(?:yes|true|no|false|1|0|T|F|Y|N)$/", $dtl_Value);
                    if($isValid){
                        if ($dtl_Value==1 || $dtl_Value == 'T' || $dtl_Value == 'Y'
                            || $dtl_Value == "yes"  ||  $dtl_Value == "true"){
                            $dtl_Value = "true";
                        }else{
                            $dtl_Value = "false";
                        }
                    }else{
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid boolean";
                    }
                    break;
                case "integer":
                    $isValid = preg_match("/^\\s*-?\\d+\\s*$/", $dtl_Value);
                    if(!$isValid){
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid integer";
                    }
                    break;

                case "separator":
                case "relmarker":
                default:
                    break;    //noop since separators and relmarker have no detail values
            } //switch


            if($isValid==='ignore') {continue;}

            //ignore all errors and skip empty values
            if($validation_mode==0 && $isValid!==true){
                if(strlen(super_trim($dtl_Value))==0) {continue;}
                $isValid = true;
            }

            if($isValid == true){

                if(@$det_required[$dtyID]!=null){
                    unset($det_required[$dtyID]);//value is valid - removes from list of required
                }

                $dval['dtl_UploadedFileID'] = $dtl_UploadedFileID;
                $dval['dtl_Geo'] = $dtl_Geo;
                $dval['dtl_HideFromPublic'] = $dtl_HideFromPublic;
                if(!empty($splitValues)){
                    foreach($splitValues as $val){
                        $dval['dtl_Value'] = $val;
                        array_push($insertValues, $dval);
                    }
                }else{
                    $dval['dtl_Value'] = $dtl_Value;
                    array_push($insertValues, $dval);
                }
            }else{
                if(!@$errorValues[$dtyID])
                {
                    $query = 'SELECT rst_DisplayName FROM defRecStructure WHERE rst_RecTypeID='.$rectype
                        .' and rst_DetailTypeID='.$dtyID;
                    $field_name = mysql__select_value($mysqli, $query);
                    if(!$field_name){
                        $query = 'SELECT dty_Name FROM defDetailTypes WHERE dty_ID='.$dtyID;
                        $field_name = mysql__select_value($mysqli, $query);
                    }

                    $dt_names = dbs_GetDtLookups();

                    if($modeImport>0){
                        $errorValues[$dtyID] = $field_name;
                    }else{
                        $errorValues[$dtyID] = '<br><div>Field ID '.$dtyID.': "'
                        .$field_name.'" ('.@$dt_names[$det_types[$dtyID]].')</div>';
                    }
                }
                if($modeImport>0){
                    $errorValues[$dtyID] .= (' '.$err_msg);
                }else{
                    $errorValues[$dtyID] .= ('<div style="padding-left:20px">'.$err_msg.DIV_E);
                }
                $cntErrors++;
            }

        }//for values
    }//for detail types

    //$stmt_size->close();


    $res = false;

    //there is undefined required details
    if ($cntErrors>0) {

        $ss = ($cntErrors>1?'s':'');
        /*
        array_push($errorValues,
        '<br><br>Please run Verify > Verify integrity to check for and fix data problems.<br>'
        .'If the problem cannot be fixed, or re-occurs frequently, please '.CONTACT_HEURIST_TEAM);
        */

        if($modeImport>0){
            $sMsg = implode(' ',$errorValues);
        }else{
            $sMsg = 'Encountered invalid value'.$ss
            .' for Record# '.$recID.'<br>'.implode(' ',$errorValues)
            .'<br> This may be due to your browser cache being out-of-date (use Ctrl-F5 to reload the page)';
        }

        $system->addError(HEURIST_ACTION_BLOCKED, $sMsg, null);

    }else{

        if (!isEmptyArray($det_required)) {

            $missed_req_dty = array_keys($det_required);
            foreach($missed_req_dty as $dty_ID){
                //try to add default values for missed required fields
                $query = 'SELECT rst_DefaultValue FROM defRecStructure WHERE rst_RecTypeID='.$rectype
                                .' and rst_DetailTypeID='.$dty_ID;
                $defaultValue = mysql__select_value($mysqli, $query);
                if($defaultValue!=null && $defaultValue!=''){
                    array_push($insertValues, array('dtl_DetailTypeID'=>$dty_ID, 'dtl_Value'=>$defaultValue));
                    unset($det_required[$dty_ID]);
                }
            }
        }

        if (!isEmptyArray($det_required)) {
            $isMulti = (count($det_required)>1);
            $query = 'SELECT rty_Name FROM defRecTypes WHERE rty_ID='.$rectype;
            $rty_Name = mysql__select_value($mysqli, $query);

            $system->addError(HEURIST_ACTION_BLOCKED, 'Required field'.($isMulti?'s':'')
                .' missing value or '.
                (count($det_required)>1?'have':'has')
                .' invalid value:<div style="padding-left:10px;font-style:italic;">'.implode('<br>',array_values($det_required)).DIV_E
                .' <br>Please change '.($isMulti?'these fields':'this field')
                .' in record type "'.htmlspecialchars($rty_Name)
                .'" to "optional" or specify default value for the field');

        }elseif (!is_array($insertValues) || empty($insertValues)) {
            $system->addError(HEURIST_INVALID_REQUEST, "It is not possible save record. No fields are defined");
        }else{
            $res = $insertValues;
        }
    }

    return $res;

} //END _prepareDetails

/**
 * Prepares a geographic value by validating its WKT (Well-Known Text) format and extracting its type.
 *
 * It expects the input `$dtl_Value` to be either a full WKT string (e.g., "POINT (1 2)")
 * or a shorthand Heurist geo string (e.g., "p 1 2").
 *
 * @param \mysqli $mysqli The mysqli connection object.
 * @param string $dtl_Value The geographic value string.
 * @return array An array `[$geoType, $geoWKT]` on success, where `$geoType` is the shorthand
 *               (p, l, pl, c, r, m) and `$geoWKT` is the WKT string.
 *               Returns `[false, 'Error message']` on failure (e.g., invalid WKT or unrecognized shorthand).
 *
 * @param \mysqli $mysqli The mysqli connection object, used to validate the WKT via `ST_GeomFromText` and `ST_asWKT`.
 * @param string $dtl_Value The geographic value string. This can be:
 *                          - A full WKT string, e.g., "POINT (1 2)", "POLYGON ((0 0, 1 1, 0 1, 0 0))".
 *                          - A Heurist shorthand, e.g., "p 1 2" (for POINT), "l 1 2, 3 4" (for LINESTRING),
 *                            "pl 0 0, 1 1, 0 1, 0 0" (for POLYGON), "m ..." (for MULTI* or GEOMETRYCOLLECTION),
 *                            "c ..." (for CIRCLE - though circle might be handled by specific logic not shown here),
 *                            "r ..." (for RECTANGLE - though rectangle might be handled by specific logic not shown here).
 *                          The function attempts to identify the type from the shorthand or the WKT itself.
 * @return array An array `[$geoType, $geoWKT]` on success:
 *               - `$geoType`: (string) The Heurist shorthand geo type (e.g., 'p', 'l', 'pl', 'm').
 *               - `$geoWKT`: (string) The validated Well-Known Text string (e.g., "POINT(1 2)").
 *               On failure, returns `[false, string $errorMessage]`.
 */
function prepareGeoValue($mysqli, $dtl_Value){

    $geoType = super_trim(substr($dtl_Value, 0, 2));
    $hasGeoType = false;
    $res = false;

    if($geoType=='p'||$geoType=='l'||$geoType=='pl'||$geoType=='c'||$geoType=='r'||$geoType=='m'){
        $geoValue = super_trim(substr($dtl_Value, 2));
        $hasGeoType = true;
    }else{
        $geoValue = super_trim($dtl_Value);
        if(strpos($geoValue, 'GEOMETRYCOLLECTION')!==false || strpos($geoValue, 'MULTI')!==false){
            $geoType = "m";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'POINT')!==false){
            $geoType = "p";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'LINESTRING')!==false){
            $geoType = "l";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'POLYGON')!==false){ //MULTIPOLYGON
            $geoType = "pl";
            $hasGeoType = true;
        }
    }

    if(preg_match('/\d/', $geoValue) && $hasGeoType){ // check that the value has ANY numbers (coordinates) and has an identified geo type
        try{
            $res = mysql__select_value($mysqli, "select ST_asWKT(ST_GeomFromText('".addslashes($geoValue)."'))");
        } catch (Exception $e) {
            return array(false, 'Geo WKT value '.substr(htmlspecialchars($geoValue),0,15).'... is not valid');
        }
    }

    if($res){
        return array($geoType, $geoValue);
    }else{
        return array(false, 'Geo WKT value '.substr(htmlspecialchars($geoValue),0,15).'... is not valid');
    }

}
//
//
//
function recordDuplicate($system, $id){

    // Check that the user is allowed to create records
    $is_allowed = userCheckPermissions($system, 'add');
    if(!$is_allowed){
        return false;
    }

    $mysqli = $system->getMysqli();

    $id = intval($id);
    if ( $id<1 ) {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
    }

    $def_params = recordAddDefaultValues($system);
    $new_owner = $def_params['owner_grps'][0];
    $access = $def_params['access'];
    $access_grps = $def_params['access_grps'];

    $currentUserId = $system->getUserId();

    $row = mysql__select_row($mysqli, "SELECT rec_OwnerUGrpID, rec_RecTypeID FROM Records WHERE rec_ID = ".$id);
    //$owner = $row[0];
    $recTypeID = intval($row[1]);
    if (!is_numeric($new_owner) || !(intval($new_owner)>=0)){   //current user is not member of current group
        $new_owner = $currentUserId;
        //return $system->addError(HEURIST_REQUEST_DENIED, 'User not authorised to duplicate record');
    }


    $bkmk_count = 0;
    $rels_count = 0;

    $error = null;

    $system->defineConstant('DT_TARGET_RESOURCE');
    $system->defineConstant('DT_PRIMARY_RESOURCE');

    $prefixDbErrorMsg = 'database error - ';

    while (true) {

        mysql__foreign_check($mysqli, false);

        //duplicate record header
        $new_id = mysql__duplicate_table_record($mysqli, 'Records', 'rec_ID', $id, null);

        $query = 'UPDATE Records set rec_Modified=NOW(), rec_Added=NOW(), rec_AddedByUGrpID='.$currentUserId;
        if(is_numeric($new_owner) && intval($new_owner)>=0){
            $query = $query.', rec_OwnerUGrpID='.$new_owner;
        }
        if($access){
            $query = $query.', rec_NonOwnerVisibility="'.$access.'"';
        }

        $query = $query.' where rec_ID='.$new_id;
        $res = $mysqli->query($query);
        if(!$res){
            $error = $prefixDbErrorMsg .$mysqli->error;
            break;
        }


        if(!is_int($new_id)){ $error = $new_id; break; }


        if($access_grps!=null){
            updateUsrRecPermissions($mysqli, $new_id, $access_grps, null);
        }

        //duplicate record details
        $res = mysql__duplicate_table_record($mysqli, 'recDetails', 'dtl_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }


        //assign increment values
        //1. find increment detail types
        $dty_IDs = mysql__select_list2($mysqli,
            'SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID='.$recTypeID
            .' AND rst_DefaultValue="increment_new_values_by_1"');

        if(!isEmptyArray($dty_IDs)){
            foreach($dty_IDs as $dty_ID){
                //2. get new incremented value
                $res = recordGetIncrementedValue($system, array('rtyID'=>$recTypeID, 'dtyID'=>$dty_ID));
                if($res['status']==HEURIST_OK){
                    $new_val = $res['result'];

                    $query = 'UPDATE recDetails set dtl_Value=?'
                    ." where dtl_RecID=$new_id and dtl_DetailTypeID=$dty_ID";

                    $res = mysql__exec_param_query($mysqli, $query, array('s', $new_val));

                    // .$mysqli->real_escape_string( $new_val )
                    // $res = $mysqli->query($query);
                    if(!$res){
                        $error = $prefixDbErrorMsg .$mysqli->error;
                        break;
                    }
                }else{
                    return $res;
                }
            }//for
        }



        //remove pointer fields where Parent-Child flag is ON
        $query = 'DELETE FROM recDetails where dtl_RecID='.$new_id.' and dtl_DetailTypeID in '
        .'(SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID='.$recTypeID.' AND rst_CreateChildIfRecPtr=1)';
        $res = $mysqli->query($query);
        if(!$res){
            $error = $prefixDbErrorMsg .$mysqli->error;
            break;
        }

        $res = mysql__duplicate_table_record($mysqli, 'usrReminders', 'rem_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrRecTagLinks', 'rtl_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrRecPermissions', 'rcp_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrBookmarks', 'bkm_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }
        $bkmk_count = $mysqli->affected_rows;

        mysql__foreign_check($mysqli, true);

        //add special kind of record - relationships
        $refs_res = mysql__select_list($mysqli, 'recLinks', 'rl_RelationID',
            '(rl_RelationTypeID is not null) and  (rl_SourceID='.$id.' or rl_TargetID='.$id.')');


        foreach ($refs_res as $rel_recid){

            $res = recordDuplicate($system, $rel_recid);

            if($res && @$res['status']==HEURIST_OK){

                $new_rel_recid = intval(@$res['data']['added']);

                if($new_rel_recid>0){

                    //change reference to old record id to new one
                    $query = 'UPDATE recDetails set dtl_Value='.$new_id
                    .' where dtl_RecID='.$new_rel_recid
                    .' and dtl_Value='.$id   //old record id
                    .' and (dtl_DetailTypeID='.DT_TARGET_RESOURCE.' or dtl_DetailTypeID='.DT_PRIMARY_RESOURCE.')';

                    $res = $mysqli->query($query);
                    if(!$res){
                        $error = $prefixDbErrorMsg .$mysqli->error;
                        break;
                    }else{
                        $rels_count++;
                    }
                }
            }else{
                $error = @$res['message'];
            }
        } //foreach

        break;
    }//while

    if($error==null){
        $res = array("status"=>HEURIST_OK,
            'affectedRty'=>$recTypeID,
            'data'=>array("added"=>$new_id, "bkmk_count"=>$bkmk_count, "rel_count"=>$rels_count));
    }else{
        $res = $system->addError(HEURIST_DB_ERROR, $error);
    }
    mysql__foreign_check($mysqli, true);
    return $res;

}

/**
 * Updates record permissions in `usrRecPermissions` for a set of records.
 *
 * Deletes existing 'view' and 'edit' permissions for the specified records,
 * then inserts new permissions based on `$access_grps` (for 'view') and `$owner_grps` (for 'edit').
 *
 * @param \mysqli $mysqli The mysqli connection object.
 * @param array $recIDs An array of record IDs whose permissions are to be updated.
 * @param array|null $access_grps An array of group IDs to grant 'view' permission.
 * @param array|null $owner_grps An array of group IDs to grant 'edit' permission (these are typically owner groups).
 * @return void
 */
function updateUsrRecPermissions($mysqli, $recIDs, $access_grps, $owner_grps){

    $recIDs = prepareIds($recIDs);

    if(isEmptyArray($recIDs)){
        return;
    }


        $access_grps = prepareIds($access_grps);
        $owner_grps = prepareIds($owner_grps, true);

        $has_access_values = !empty($access_grps);
        $has_owner_values = !empty($owner_grps);

        if($has_access_values){
            $query = 'DELETE FROM usrRecPermissions WHERE rcp_RecID in ('.implode(',', $recIDs).') AND rcp_Level = "view"';
            $mysqli->query($query);
        }
        if($has_owner_values){
            $query = 'DELETE FROM usrRecPermissions WHERE rcp_RecID in ('.implode(',', $recIDs).') AND rcp_Level = "edit"';
            $mysqli->query($query);
        }

        if(!($has_access_values || $has_owner_values)){
            return;
        }

        //add group record permissions
        $values = array();
        foreach($recIDs as $recID){

                foreach ($owner_grps as $grp_id){
                    array_push($values,'('.intval($grp_id).','.$recID.',"edit")');
                }

                foreach ($access_grps as $grp_id){
                    array_push($values,'('.intval($grp_id).','.$recID.',"view")');
                }
        }
        $query = 'INSERT INTO usrRecPermissions (rcp_UGrpID,rcp_RecID,rcp_Level) VALUES '.implode(',',$values);
        $mysqli->query($query);

}


// @todo REMOVE - all these functions are duplicated in VerifyValue and dbsData.php

/**
 * Checks if a given record type (`$rectype_tocheck`) is a valid target for a resource pointer field.
 *
 * It considers constraints defined either in the record's structure (`rst_PtrFilteredIDs`)
 * or in the detail type definition (`dty_PtrTargetRectypeIDs`).
 *
 * @global array $recstructures Cache of record type structures.
 * @global array $detailtypes Cache of detail type definitions.
 * @param \hserv\System $system The Heurist system object.
 * @param int $rectype_tocheck The record type ID of the target record being checked.
 * @param int $dtyID The detail type ID of the resource pointer field.
 * @param int $rectype The record type ID of the record containing the pointer field.
 * @return bool True if the target record type is allowed, false otherwise.
 */
function isValidRectype($system, $rectype_tocheck, $dtyID, $rectype)
{
    global $recstructures, $detailtypes;

    $rectype_ids = null;

    $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

    if($recstr && @$recstr['dtFields'][$dtyID])
    {
        $val = $recstr['dtFields'][$dtyID];
        $idx = $recstructures['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        $rectype_ids = $val[$idx];//constraint for pointer
    }else{
        //detail type may be not in rectype structure

        $dtype = getDetailType($system, $detailtypes, $dtyID);
        if ($dtype) {
            $idx = $detailtypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
            $rectype_ids = @$dtype[$idx];
        }
    }

    if($rectype_ids){
        $allowed_rectypes = explode(",", $rectype_ids);
        return in_array($rectype_tocheck, $allowed_rectypes);
    }

    return true;
}

/**
 * Gets a human-readable string describing the record type constraints for a pointer field.
 *
 * Used for generating error messages when `isValidRectype` fails.
 *
 * @global array $recstructures Cache of record type structures.
 * @param \hserv\System $system The Heurist system object.
 * @param int $dtyID The detail type ID of the pointer field.
 * @param int $rectype The record type ID of the record containing the pointer field.
 * @return string A descriptive string of allowed target record type names, or an empty string if no constraints found.
 */
function _getRtConstraintNames($system, $dtyID, $rectype)
{
    global $recstructures;

    $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

    if($recstr && @$recstr['dtFields'][$dtyID])
    {
        $val = $recstr['dtFields'][$dtyID];
        $idx = $recstructures['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        $rectype_ids = $val[$idx];//constraint for pointer

        $idx_name = $recstructures['commonNamesToIndex']['rty_Name'];

        $rty_Name = $recstructures[$rectype]['commonFields'][$idx_name];


        $allowed_rectypes = explode(",", $rectype_ids);
        $allowed_names = array();

        foreach($allowed_rectypes as $rty_ID){
            $rty_ID = intval($rty_ID);
            if(!isPositiveInt($rty_ID)) continue;
            $recstr = dbs_GetRectypeStructure($system, $recstructures, $rty_ID);
            array_push( $allowed_names, $recstructures[$rty_ID]['commonFields'][$idx_name] );
        }
        return 'Field expects target type <i>'.implode(', ',$allowed_names)
        .'</i><br>Target record is type <i>'.$rty_Name.'</i>';
    }
    return '';
}

// @todo use DbsTerms
// @todo REMOVE - all these functions are duplicated in DbsTerms and dbsData.php
// see VerifyValue
/**
 * Checks if a given term ID is a valid selection for an enum or relationtype field.
 *
 * Considers the vocabulary (`dty_JsonTermIDTree`) and non-selectable terms
 * (`dty_TermIDTreeNonSelectableIDs`) defined for the field.
 *
 * @global array $recstructures Cache of record type structures (unused directly, but context).
 * @global array $detailtypes Cache of detail type definitions.
 * @global \hserv\entity\DbDefTerms $terms Instantiated DbsTerms object for term lookups.
 * @param \hserv\System $system The Heurist system object.
 * @param int $term_tocheck The term ID to validate.
 * @param string $domain The domain of the term ('enum' or 'relation').
 * @param int $dtyID The detail type ID of the enum/relationtype field.
 * @param int $rectype The record type ID of the record containing the field (unused directly, context).
 * @return bool True if the term is a valid selection, false otherwise.
 */
function isValidTerm($system, $term_tocheck, $domain, $dtyID, $rectype)
{
    global $recstructures, $detailtypes, $terms; //DbsTerms

    $terms_ids = null;

    $dtype = getDetailType($system, $detailtypes, $dtyID);
    if ($dtype) {
        $idx = $detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree'];
        $terms_ids = @$dtype[$idx];
        $idx = $detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
        $terms_none = @$dtype[$idx];
    }

    if($terms_ids){

        //get all terms for given vocabulary
        $allowed_terms = $terms->treeData($terms_ids,'set');

        /*
        $terms = getTermsFromFormat2($terms_ids, $domain);//parse

        if (($cntTrm = count($terms)) > 0) {
        if ($cntTrm == 1) { //vocabulary
        $vocabId = $terms[0];
        $terms = getTermsByParent($terms[0], $domain);
        array_push($terms, $vocabId);
        }else{
        $nonTerms = getTermsFromFormat2($terms_none, $domain);
        if (!empty($nonTerms)) {
        $terms = array_diff($terms, $nonTerms);
        }
        }
        if (!empty($terms)) {
        $allowed_terms = $terms;
        }
        }
        */

        return $allowed_terms && in_array($term_tocheck, $allowed_terms);
    }

    return true;

}

/**
 * Handles workflow stage changes for a record.
 *
 * When a record's workflow stage (DT_WORKFLOW_STAGE) changes, this function:
 * - Checks if the current user is allowed to move the record to the `$new_value` stage based on `swf_StageRestrictedTo`.
 * - If allowed, updates the record's ownership (`OwnerUGrpID`) and visibility (`NonOwnerVisibility`, `NonOwnerVisibilityGroups`)
 *   based on the rules defined in `sysWorkflowRules` for the new stage.
 * - Gathers email addresses for notification from `swf_SendEmail` (user/group IDs), `swf_EmailList` (literal emails),
 *   and `swf_RecEmailField` (a detail field in the record containing email addresses).
 * - Prepares the email body from `swf_EmailText`. It substitutes placeholders:
 *   - `#stage#`: Name of the new workflow stage.
 *   - `#user#`: Full name of the current user.
 *   - `#url#`: URL of the record (from `$record['URL']`).
 *   - `#link_v#`: URL to view the record (generated by `$system->recordLink()`). (This part seems to be handled in `recordSave` instead).
 *   - `#link_e#`: URL to edit the record. (This part seems to be handled in `recordSave` instead).
 *   - `#title#`: Title of the record. (This part seems to be handled in `recordSave` instead).
 *   - `#(\d+)#`: Placeholder for a field value, where `\d+` is the detail type ID. The function will
 *                retrieve the name of the field and its value(s) from `$record['details']` and format them.
 *
 * The function modifies the `$record` array by reference, potentially setting 'OwnerUGrpID',
 * 'NonOwnerVisibility', 'NonOwnerVisibilityGroups', and 'swf' (to true, to bypass some later permission checks).
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array &$record The record data array, passed by reference. It is read for 'ID', 'RecTypeID',
 *                       'FlagTemporary', 'URL', and 'details'. It is modified to update ownership/visibility
 *                       fields if workflow rules dictate, and 'swf' flag is set.
 * @param int $new_value The term ID of the new workflow stage.
 * @param bool $is_insert True if the record is being inserted, false if it's an existing record being updated.
 *                        This influences whether a "current_value" for the stage is sought.
 * @return array An associative array detailing the outcome and data for potential notifications:
 *               - 'new_value': (int) The ID of the workflow stage that was actually set. This will be 0 if the
 *                                user was not allowed to transition to `$new_value`, otherwise it's `$new_value`.
 *               - 'curr_value': (int) The previous workflow stage ID of the record. 0 if `$is_insert` is true
 *                                 or if no previous stage was found.
 *               - 'emails': (array) A list of unique, sanitized email addresses for notification, compiled from
 *                             workflow rules and potentially the record's own fields.
 *               - 'body': (string|null) The prepared email body text if `swf_EmailText` was defined for the rule,
 *                         otherwise null. Placeholders in the text are substituted.
 */
function recordWorkFlowStage($system, &$record, $new_value, $is_insert){

    $current_value = 0;
    $emails = [];

    $res = array('new_value'=>$new_value, 'curr_value'=>$current_value, 'emails'=>$emails, 'body'=>null);

    if (!($new_value>0 && @$record['FlagTemporary']!=1)) {
        return $res;
    }


    $recID = intval(@$record['ID']);
    $recID = abs($recID);


    $mysqli = $system->getMysqli();

    if(!$is_insert){
        //find current stage
        $query = "SELECT dtl_Value FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=".DT_WORKFLOW_STAGE;
        $current_value = mysql__select_value($mysqli, $query);
        $res['curr_value'] = $current_value;
    }

    if($current_value==$new_value){
        return $res;
    }


    //if stage is changed - assign new values for rec_OwnerUGrpID and rec_NonOwnerVisibility
    $query = 'SELECT swf_StageRestrictedTo, swf_SetOwnership, swf_SetVisibility, swf_SendEmail, swf_EmailList, swf_RecEmailField, swf_EmailText FROM sysWorkflowRules '
    .'WHERE swf_RecTypeID='.$record['RecTypeID'].' AND swf_Stage='.$new_value;
    $rule = mysql__select_row_assoc($mysqli, $query);

    //check that current user can change workflow stage
    $is_allowed = false;
    if($rule!=null &&
        ($rule['swf_StageRestrictedTo']==null
        || $system->isAdmin()
        || $system->isMember($rule['swf_StageRestrictedTo']))
    ){

        $is_allowed = true;
    }

    if($is_allowed){

        //changing ownership
        if($rule['swf_SetOwnership']!=null && $rule['swf_SetOwnership']>=0){
            $record['OwnerUGrpID'] = $rule['swf_SetOwnership'];
            $record['swf'] = true; //marker that ownership is change by workflow stage - it will not check that current user has rights
        }
        //changing visibility
        if($rule['swf_SetVisibility']!=null){
            if($rule['swf_SetVisibility']=='public' ||
                $rule['swf_SetVisibility']=='viewable' ||
                $rule['swf_SetVisibility']=='hidden'){
                $record['NonOwnerVisibility'] = $rule['swf_SetVisibility'];
            }else{
                $record['NonOwnerVisibility'] = 'viewable';
                $record['NonOwnerVisibilityGroups'] = $rule['swf_SetVisibility'];
            }
        }

        //get email addresses for notification

        if($rule['swf_SendEmail']!=null){

            $query = 'SELECT ugr_eMail FROM sysUGrps '
            .'WHERE ugr_ID IN ('.$rule['swf_SendEmail'].')';

            $res['emails'] = mysql__select_list2($mysqli, $query);
        }

        if($rule['swf_EmailList'] != null){

            $list = explode(',', $rule['swf_EmailList']);

            $list = array_filter($list, function($email){
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });

            $res['emails'] = array_merge_unique($res['emails'], $list);
        }

        $recordField = intval($rule['swf_RecEmailField']);
        if($recordField > 0 && array_key_exists($recordField, $record['details'])){
            $emails = is_array($record['details'][$recordField]) ? $record['details'][$recordField] : [$record['details'][$recordField]];
            foreach($emails as $email){
                if(!filter_var($email, FILTER_VALIDATE_EMAIL) || array_search($email, $res['emails']) !== false){
                    continue;
                }
                $res['emails'][] = $email;
            }
        }

        if(!empty($rule['swf_EmailText'])){

            $new_stage_name = mysql__select_value($mysqli, "select trm_Label from defTerms where trm_ID = {$new_value}");
            $cur_user = $system->getCurrentUser()['ugr_FullName'];

            $res['body'] = str_replace(['#stage#', '#user#', '#url#'], [$new_stage_name, $cur_user, $record['URL']], $rule['swf_EmailText']);

            $res['body'] = mb_ereg_replace_callback('#(\d+)#', function($matches) use ($record, $mysqli){

                $name = mysql__select_value($mysqli, "SELECT rst_DisplayName FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID = ?", ['ii', $record['RecTypeID'], $matches[1]]);
                $type = mysql__select_value($mysqli, "SELECT dty_Type FROM defDetailTypes WHERE dty_ID = ?", ['i', $matches[1]]);

                if(!array_key_exists($matches[1], $record['details']) || empty($record['details'][$matches[1]])){
                    return "No {$name} values";
                }

                $replace = [];
                $values = !is_array($record['details'][$matches[1]]) ? [$record['details'][$matches[1]]] : $record['details'][$matches[1]];

                foreach($values as $value){

                    switch($type){
    
                        case 'freetext':
                        case 'blocktext':
                        case 'float':
                        case 'integer':
                            $replace[] = $value;
                            break;
    
                        case 'date':
                            $replace[] = Temporal::toHumanReadable($value, true, 1);
                            break;
    
                        case 'enum':
                            $replace[] = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $value]);
                            break;
    
                        case 'resource':
                            $replace[] = mysql__select_value($mysqli, "SELECT rec_Title FROM Records WHERE rec_ID = ?", ['i', $value]);
                            break;
    
                        case 'file':
                            $file_details = mysql__select_row($mysqli, "SELECT ulf_OrigFileName, ulf_ExternalFileReference FROM recUploadedFiles WHERE ulf_ID = ?", ['i', $value]);
                            $replace[] = strpos($file_details[0], '_') === 0 && !empty($file_details[1]) ? $file_details[1] : $file_details[0] ;
                            break;
    
                        default:
                            break;
                    }
                }

                return empty($replace) ? "No {$name} values" : implode(' | ', $replace);

            }, $res['body']);
        }

    }else{
        $res['new_value'] = 0; //not allowed
    }

    if(!empty($res['emails'])){
        $res['emails'] = array_filter(array_map(function($email){ return filter_var($email, FILTER_SANITIZE_EMAIL); }, $res['emails']));
    }

    return $res;
}

/**
 * Validates and potentially re-adds "Parent Entity" pointers to a child record's details.
 *
 * If a child record was previously linked from multiple parent records (via fields marked as
 * `rst_CreateChildIfRecPtr`), and some of these parent links are missing from the
 * incoming `$new_child_details` (e.g., during an update where some parent links were removed by the user),
 * this function identifies such valid, missing parent links and adds them back to `$new_child_details`.
 * This helps maintain data integrity for mandated parent-child relationships defined by `rst_CreateChildIfRecPtr`.
 *
 * The function checks if the child record type has a "Parent Entity" field defined in its structure.
 * It then queries `recDetails` to find all records that point to the current child record (`$child_record['ID']`).
 * For each such potential parent, it verifies if the link from the parent is via a field marked as `rst_CreateChildIfRecPtr = 1`
 * and that the child's record type is allowed by the parent field's `dty_PtrTargetRectypeIDs` (if specified).
 * If a valid parent link is found that is *not* already present in the `$new_child_details` (as a DT_PARENT_ENTITY pointer),
 * a new DT_PARENT_ENTITY detail pointing to this parent is added to `$new_child_details`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $child_record An associative array containing the child record's header data.
 *                            Must include 'ID' (int, child's record ID) and 'RecTypeID' (int, child's record type ID).
 * @param array &$new_child_details Passed by reference. An array of the child record's new or updated detail values,
 *                                  formatted as `[['dtl_DetailTypeID'=>X, 'dtl_Value'=>Y], ...]`.
 *                                  This array will be modified in place if missing valid parent entity pointers are found and re-added.
 * @return array An associative array where keys are the `rec_ID`s of parent records whose "Parent Entity" links
 *               were re-added to `$new_child_details`. Each value is an array:
 *               `['title' => string $parent_title, 'type' => int $parent_rectype_ID, 'field' => int $parent_dty_ID_pointing_to_child]`
 *               Returns an empty array if no parent links needed to be re-added, or if DT_PARENT_ENTITY is not defined,
 *               or if the child record type doesn't have a Parent Entity field in its structure.
 */
function validateParentRecords($system, $child_record, &$new_child_details){

    $mysqli = $system->getMysqli();
    $rec_ID = $child_record['ID'];
    $rectype_ID = $child_record['RecTypeID'];

    $system->defineConstant('DT_PARENT_ENTITY');
    $parent_entity = defined('DT_PARENT_ENTITY') ? DT_PARENT_ENTITY : 0;

    if($parent_entity <= 0){
        return [];
    }

    $has_parent_entity_fld = mysql__select_value($mysqli, "SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID = ?", ['ii', $rectype_ID, $parent_entity]);
    $is_child_of = mysql__select_assoc2($mysqli, "SELECT dtl_RecID, dtl_DetailTypeID FROM recDetails WHERE dtl_Value = {$rec_ID} AND dtl_RecID != {$rec_ID}");

    if(!$has_parent_entity_fld || empty($is_child_of)){
        return [];
    }

    $new_parents = array_filter($new_child_details, function($detail) use ($parent_entity){
        return $detail['dtl_DetailTypeID'] == $parent_entity;
    });
    $missing_parents = [];

    foreach($is_child_of as $parent_rec_ID => $parent_dty_ID){

        if(in_array($parent_rec_ID, array_column($new_parents, 'dtl_Value'))){
            // Still a parent
            continue;
        }

        [$parent_title, $parent_type] = mysql__select_row($mysqli, "SELECT rec_Title, rec_RecTypeID FROM Records WHERE rec_ID = ?", ['i', $parent_rec_ID]);
        $parent_type = intval($parent_type);

        $rectype_list_query = "SELECT dty_PtrTargetRectypeIDs, rst_ID FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE dty_ID = {$parent_dty_ID} AND dty_Type = 'resource' AND rst_RecTypeID = {$parent_type} AND rst_CreateChildIfRecPtr = 1";
        [$rectype_list, $rst_ID] = mysql__select_row($mysqli, $rectype_list_query);
        $rst_ID = $rst_ID ?? 0;
        $rectype_list = $rectype_list ?? '';
        $rectype_list = explode(',', $rectype_list);

        if($rst_ID <= 0 || !empty($rectype_list) && !in_array($rectype_ID, $rectype_list)){
            continue;
        }

        $new_child_details[] = [
            'dtl_DetailTypeID' => intval($parent_entity),
            'dtl_Value' => intval($parent_rec_ID)
        ];
        $missing_parents[$parent_rec_ID] = [
            'title' => $parent_title,
            'type' => $parent_type,
            'field' => intval($parent_dty_ID)
        ];
    }

    return $missing_parents;
}

/**
 * Updates record detail values based on `rst_EntryMask` definitions in `defRecStructure`.
 *
 * Iterates through fields of a given record that have an entry mask defined.
 * For each such field, it retrieves its current values from `recDetails`.
 * It then applies the entry mask logic (using `updateMaskFields`) to each value.
 * If a value is transformed by the mask (i.e., it didn't already conform to the mask's prefix but is now valid after processing),
 * the corresponding `recDetails` entry for that specific `dtl_ID` is updated with the newly masked value.
 *
 * This function relies on `updateMaskFields()` to perform the actual validation and transformation for each value based on the mask type.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The ID of the record whose detail fields are to be processed against their entry masks.
 * @param int $rtyID Optional. The record type ID of the record. If 0 or not provided, it's fetched from the database using `$recID`.
 * @param bool $verbose Optional. If true, the return array includes detailed counts of operations:
 *                      `['skipped' => int, 'updated' => int, 'invalid' => int, 'invalid_masks' => [dty_ID => mask_string], ...other_results...]`.
 *                      The `...other_results...` part refers to the standard (non-verbose) return where keys are `dty_ID`s.
 *                      If false (default), the function returns only an array of values that failed validation.
 * @return array If `$verbose` is false (default): An associative array where each key is a `dty_ID` (detail type ID)
 *               and the value is an array of items that failed the mask. Each item is `['value' => original_value, 'reason' => failure_reason_string]`.
 *               Returns an empty array if all applicable fields already conformed or were successfully updated, or if no fields with entry masks were found.
 *               If `$verbose` is true: An associative array as described above, augmented with 'skipped', 'updated', 'invalid', and 'invalid_masks' counts/lists.
 */
function recordUpdateMaskFields($system, $recID, $rtyID = 0, $verbose = false){

    $mysqli = $system->getMysqli();
    $entryMaskFields = null;
    $result = [];

    if($recID > 0 && $rtyID <= 0){
        $rtyID = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = {$recID}");
    }

    if($recID > 0 && $rtyID > 0){
        $entryMaskFields = mysql__select_assoc2($mysqli, "SELECT rst_DetailTypeID, rst_EntryMask FROM defRecStructure WHERE rst_RecTypeID = {$rtyID} AND rst_EntryMask IS NOT NULL");
    }

    if(empty($entryMaskFields)){
        return [];
    }

    $values_updated = 0;
    $values_skipped = 0;
    $values_invalid = 0;
    $mask_invalid = [];

    foreach($entryMaskFields as $dtyID => $mask){

        $cur_vals = mysql__select_assoc2($mysqli, "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = {$recID} AND dtl_DetailTypeID = {$dtyID}");

        preg_match('~\$([adimn])(\d)*(\(\d,?\d*\))\$~', $mask, $matches);

        if(count($matches) < 2){ // invalid mask

            $values_skipped ++;
            $mask_invalid[$dtyID] = $mask;

            continue;
        }

        $to_replace = $matches[0]; // mask's logical substring, replaced with value
        $check_for = substr($mask, 0, strpos($mask, $to_replace)); // used to check if mask has been applied
        $type = $matches[1]; // mask type [a,d,i,m,n]

        $length = count($matches) > 2 && is_numeric($matches[2]) ? intval($matches[2]) : 0;

        // Number range
        $range = count($matches) > 2 && is_string($matches[2]) && $matches[2][0] == '(' ? $matches[2] : [];
        $range = count($matches) > 3 && is_string($matches[3]) && $matches[3][0] == '(' ? $matches[3] : $range;
        $range = empty($range) ? [] : explode(',', str_replace(['(',')'], '', $range));

        if(count($range) !== 2){ // only one number was provided
            $range = null;
        }elseif(count($range) == 2 && $range[0] > $range[1]){ // swap min and max
            $temp = $range[0];
            $range[0] = $range[1];
            $range[1] = $temp;
        }

        foreach($cur_vals as $dtl_ID => $value){

            if(strpos($value, $check_for) === 0){ // mask already applied
                $values_skipped ++;
                continue;
            }

            $org_value = $value;
            $reason = false;

            [$value, $reason] = updateMaskFields($type, $value, $length, $range);

            if($reason === false){ // type not handled
                $mask_invalid[$dtyID] = $mask;
                continue;
            }elseif(!empty($reason)){ // value doesn't match the mask, leave value unchanged
                if(!array_key_exists($dtyID, $result)){
                    $result[$dtyID] = [];
                }

                $result[$dtyID][] = ['value' => $org_value, 'reason' => $reason];

                $values_invalid ++;

                continue;
            }

            // Valid value, update value using mask
            $value = str_replace($to_replace, $value, $mask);

            $res = mysql__insertupdate($mysqli, 'recDetails', 'dtl', ['dtl_ID' => $dtl_ID, 'dtl_Value' => $value]);
            if(!$res){ // failed to update record detail
                if(!array_key_exists($dtyID, $result)){
                    $result[$dtyID] = [];
                }

                $result[$dtyID][] = ['value' => $value, 'reason' => 'Failed to update record detail'];

                $values_invalid ++;

                continue;
            }

            $values_updated ++;
        }
    }

    if($verbose){
        $result['skipped'] = $values_skipped;
        $result['updated'] = $values_updated;
        $result['invalid'] = $values_invalid;

        $result['invalid_masks'] = $mask_invalid;
    }

    return $result;
}

/**
 * Processes and validates a numeric value against entry mask rules.
 *
 * Converts the value to the specified numeric type (integer or float).
 * Checks if it falls within an optional min/max range.
 * Formats float values to a specified number of decimal places.
 *
 * @param string $type The specific numeric type from the entry mask:
 *                     'i' for integer,
 *                     'd' for decimal (float),
 *                     'n' for generic numeric (will be treated as float if it contains a decimal point, otherwise integer).
 * @param mixed $value The input value to be processed and validated.
 * @param int $length If the type is 'd' (decimal) or 'n' (and the value is float), this specifies the number of decimal places
 *                    to format the number to using `number_format`. For integers, this parameter is not directly used for formatting.
 * @param array|null $range An optional two-element numeric array `[min, max]` specifying the inclusive valid range for the value.
 *                          If null, no range check is performed.
 * @return array An array with two elements:
 *               - First element (`$processed_value`): The processed numeric value (int or string formatted float).
 *                 If validation fails (e.g., not numeric, out of range), this will be the original `$value`.
 *               - Second element (`$reason_string`): An empty string if the value is valid and conforms to the type and range.
 *                 Otherwise, a string describing the reason for validation failure (e.g., "Not an integer", "Out of range: 0 - 100").
 */
function updateMaskFieldsNumeric($type, $value, $length, $range) {

    $type_text = $type === 'i' ? 'an integer' : 'numeric';
    $type_text = $type === 'd' ? 'a decimal number' : $type_text;

    if(is_numeric($value)){
        $value_str = strval($value);
        $value = ($type === 'n' && substr_count($value_str, '.') == 1) || $type === 'd' ? floatval($value) : intval($value);
    }

    $reason = '';

    if(!is_float($value) && !is_int($value)){
        $reason = "Not {$type_text}";
    }elseif(count($range) === 2 && ($value < $range[0] || $value > $range[1])){
        $reason = "Out of range: {$range[0]} - {$range[1]}";
    }elseif(is_float($value)){
        $value = number_format($value, $length, '.', '');
    }

    return [$value, $reason];
}

/**
 * Processes and validates a value against a specified entry mask type and constraints.
 *
 * Supported mask types:
 * - 'a': Alphabetic (letters and common punctuation). Checks for non-alphabetic chars and length.
 * - 'd': Decimal (float). Converts to float, checks range, formats decimal places.
 * - 'i': Integer. Converts to integer, checks range.
 * - 'n': Numeric (integer or float). Checks range, formats decimal places if float.
 * - 'm': Mixed alphanumeric. Checks for non-alphanumeric/non-punctuation chars and length.
 *
 * @param string $type The entry mask type ('a', 'd', 'i', 'n', 'm').
 * @param mixed $value The value to validate.
 *
 * This function serves as a dispatcher for different mask type validations.
 * For numeric types ('d', 'i', 'n'), it delegates to `updateMaskFieldsNumeric`.
 * For string types ('a', 'm'), it performs direct validation for allowed characters and length.
 *
 * @param string $type The entry mask type character code. Supported codes:
 *                     'a': Alphabetic. Allows letters and common punctuation (defined in `$_PUNCTUATION`).
 *                          Checks against `mb_ereg_match` with `[^\w{$_PUNCTUATION}]`.
 *                     'd': Decimal. Processed by `updateMaskFieldsNumeric`.
 *                     'i': Integer. Processed by `updateMaskFieldsNumeric`.
 *                     'n': Numeric. Processed by `updateMaskFieldsNumeric`.
 *                     'm': Mixed alphanumeric. Allows letters, numbers, and common punctuation (defined in `$_PUNCTUATION`).
 *                          Checks against `mb_ereg_match` with `[^\w\d{$_PUNCTUATION}]`.
 * @param mixed $value The value to be validated and potentially transformed (for numeric types).
 * @param int $length For string types ('a', 'm'), this is the maximum allowed string length. If 0, no length check.
 *                    For numeric types ('d', 'n' when float), this is the number of decimal places for formatting.
 * @param array|null $range An optional two-element numeric array `[min, max]` for range validation,
 *                          applicable only to numeric types ('d', 'i', 'n').
 * @return array An array with two elements:
 *               - First element (`$processed_value`): The original `$value` for string types, or the
 *                 numerically processed (int or string formatted float) value for numeric types.
 *                 If validation fails for string types, it remains the original `$value`.
 *               - Second element (`$reason_string`):
 *                 - Empty string (''): If the value is valid and conforms to the mask type and constraints.
 *                 - Non-empty string: A message describing the validation failure (e.g., "Contains non-alphabetic characters",
 *                   "Size exceeds set length X", "Not an integer", "Out of range: Y - Z").
 *                 - `false` (boolean): If the provided `$type` is not one of the handled mask types ('a', 'd', 'i', 'n', 'm').
 */
function updateMaskFields($type, $value, $length, $range){

    $reason = '';
    $_PUNCTUATION = '.,\'"?!()\[\]-`:;/ ';

    switch($type){

        case 'a': // alphabetic, letters only

            $validate_alpha = "[^\w{$_PUNCTUATION}]";

            if(mb_ereg_match($validate_alpha, $value)){
                $reason = 'Contains non-alphabetic characters';
            }elseif($length > 0 && strlen($value) > $length){
                $reason = "Size exceeds set length {$length}";
            }

            break;

        case 'd': // decimal, float point number, will convert integers
        case 'i': // integer, whole number, will conevrt float points
        case 'n': // numeric, any type of number

            [$value, $reason] = updateMaskFieldsNumeric($type, $value, $length, $range);

            break;

        case 'm': // mixed, alphanumeric no special characters

            $validate_mixed = "[^\w\d{$_PUNCTUATION}]";

            if(mb_ereg_match($validate_mixed, $value)){
                $reason = 'Contains non-alphanumeric characters';
            }elseif($length > 0 && strlen($value) > $length){
                $reason = "Size exceeds set length {$length}";
            }

            break;

        default:

            $reason = false;
            break;
    }

    return [$value, $reason];
}

/**
 * Sends an email notification when a bug report record's status is updated to 'DONE' or a child term of 'DONE'.
 *
 * This function checks if the updated record is a bug report (identified by `ConceptCode::getRecTypeLocalID('8-23')`).
 * If the status field (identified by `ConceptCode::getDetailTypeLocalID('2-810')`) is set to a term that is 'DONE'
 * (identified by `ConceptCode::getTermLocalID('1037-3246')`) or one of its child terms, an email is sent to the
 * reporter (email from field `ConceptCode::getDetailTypeLocalID('1317-242')`).
 * The email includes details like the bug title, description, database, and a link to the bug report.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The ID of the record that was updated.
 * @return void This function does not return a value. It sends an email if conditions are met.
 */
function bugreportUpdate($system, $recID){

    $mysqli = $system->getMysqli();
    $recRecTypeID = mysql__select_value($mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID = ?', ['i', $recID]);

    // Get local IDs for bug report fields
    $bugreportRecType = ConceptCode::getRecTypeLocalID('8-23');
    $doneTrmID = ConceptCode::getTermLocalID('1037-3246');
    $statusDtyID = ConceptCode::getDetailTypeLocalID('2-810');
    $titleDtyID = ConceptCode::getDetailTypeLocalID('2-1');
    $descDtyID = ConceptCode::getDetailTypeLocalID('2-3');
    $reporterEmailDtyID = ConceptCode::getDetailTypeLocalID('1317-242');
    $reporterNameDtyID = ConceptCode::getDetailTypeLocalID('1317-243');
    $databaseDtyID = ConceptCode::getDetailTypeLocalID('1623-993');

    // Get bug report details
    $details = recordSearchDetailsRaw($system, $recID);

    if(!isset($recRecTypeID, $bugreportRecType, $doneTrmID, $statusDtyID, $titleDtyID, $descDtyID, $reporterEmailDtyID, $reporterNameDtyID, $databaseDtyID) || empty($details) || $bugreportRecType != $recRecTypeID){
        return;
    }

    // Get child terms of status DONE, to check against
    $terms = getTermChildrenAll($mysqli, $doneTrmID);
    $status = array_key_exists($statusDtyID, $details) ? $details[$statusDtyID] : null;
    $status = is_array($status) ? $status[0] : $status;

    $reportersEmail = array_key_exists($reporterEmailDtyID, $details) ? $details[$reporterEmailDtyID] : null;
    $reportersEmail = is_array($reportersEmail) ? $reportersEmail[0] : $reportersEmail;

    $reportersName = array_key_exists($reporterEmailDtyID, $details) ? $details[$reporterEmailDtyID] : null;
    $reportersName = is_array($reportersEmail) ? $reportersEmail[0] : $reportersName;
    $reportersName = empty($reportersName) ? $reportersEmail : $reportersName;

    if(!$status || !$reportersEmail || !in_array($status, $terms)){
        return;
    }

    // Retrieve specific report details (database, title, description) and prepare email
    $status = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $status]);

    $database = array_key_exists($databaseDtyID, $details) ? $details[$databaseDtyID] : null;
    $database = is_array($database) ? $database[0] : $database;
    $database = !empty($database) ? "Database: {$database}<br>" : '';

    $title = array_key_exists($titleDtyID, $details) ? $details[$titleDtyID] : null;
    $title = is_array($title) ? $title[0] : $title;
    $title = !empty($title) ? $title : "Bug report #{$recID}";

    $desc = array_key_exists($descDtyID, $details) ? $details[$descDtyID] : null;
    $desc = is_array($desc) ? $desc[0] : $desc;
    $desc = !empty($desc) ? $desc : "Description is missing";

    $url = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/view/$recID";

    $updateEmail = <<<EMAIL
    The status of the following bug report has been completed, final status: <strong>$status</strong>.<br><br>
    You can view the report here: <a href="$url">$url</a><br><br>
    Reporter: $reportersName<br>
    $database
    Title: $title<br>
    Bug description:<br>
    $desc<br><br>
    When an issue is fixed and marked as DONE, the change will typically appear in the alpha version (/h6-alpha/, /h7-alpha/, ...) within a couple of days on HeuristRef.net<br>
    and any server which has automated daily update of the alpha version, and a week or more on the Huma-Num server (due to new approval protocols from July 2025).
    EMAIL;

    $to = [$reportersEmail];

    sendPHPMailer(null, 'Bug report updater', ['to' => $to], "Heurist tracker #{$recID}: {$title}", $updateEmail, null, true);
}
?>
