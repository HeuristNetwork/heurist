<?php
namespace hserv\records\batch;

/**
 * RecordsBatchAction.php - Base class for record batch actions
 *
 * Contains the existing shared batch selection, field validation,
 * sanitisation, reporting and tag-assignment behaviour.
 *
 * @project     Heurist academic knowledge management system
 * @package Records\Batch
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 */
abstract class RecordsBatchAction
{
    protected $system;
    protected $data;
    protected $recIDs;
    protected $rtyIDs;
    protected $result_data = array();
    protected $not_putify = null;
    protected $purifier = null;
    protected $session_id = null;

    public function __construct($system, $data)
    {
        $this->system = $system;
        $this->data = $data;
        $this->session_id = @$data['session'];
    }

    abstract public function execute();

    public function getReport()
    {
        return $this->result_data;
    }

    protected function _initPutifier(){
        if($this->purifier==null){
            $not_purify = array();
            if($this->system->defineConstant('DT_CMS_EXTFILES')){ array_push($not_purify, DT_CMS_EXTFILES);}

            $this->not_purify = $not_purify;
            //$this->purifier = USanitize::getHTMLPurifier(); DISABLED
        }
    }

    protected function _validateDetailType(){

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

    protected function _validateParamsAndCounts()
    {
        // Check that the user is allowed to edit records
        if(!userCheckPermissions($this->system, 'edit')){
            return false;
        }

        if(!in_array(@$this->data['a'], array('reset_thumbs', 'iiif_thumbs')) && !$this->_validateDetailType()){
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

    protected function getDetailType($dty_ID){
        return mysql__select_value($this->system->getMysqli(),
                 'Select dty_Type from defDetailTypes where dty_ID = '.intval($dty_ID));
    }

    protected function _removeScriptTag($value){
        $value = trim($value);
        return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
    }

    protected function _assignTagsAndReport($type, $recordIds, $baseTag)
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

    protected function _tagsAssign($record_ids, $tag_ids, $tag_names=null, $ugrID=null){

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

    protected function _tagGetByName($tag_names, $isadd, $ugrID=null){

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

    protected function _tagSave($tag){

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
}
?>
