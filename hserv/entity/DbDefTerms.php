<?php
/**
* DbDefTerms.php - Class DbDefTerms
*
* Operations for the `defTerms` table
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
use hserv\utilities\USystem;
use hserv\utilities\USanitize;

/**
* Class DbDefTerms
*
* Provides database access and operations for the `defTerms` table, which stores
* vocabulary terms used for enumerated fields and relationship types.
* Handles searching, creating, updating, deleting, importing, and managing term hierarchies
* and translations.
*/
class DbDefTerms extends DbEntityBase
{
    private $records_all = null;
    private $labels_to_idx = null;
    /*
    'trm_OriginatingDBID'=>'int',
    'trm_NameInOriginatingDB'=>250,
    'trm_IDInOriginatingDB'=>'int',

    'trm_AddedByImport'=>'bool2',
    'trm_IsLocalExtension'=>'bool2',

    'trm_OntID'=>'int',
    'trm_ChildCount'=>'int',

    'trm_Depth'=>'int',
    'trm_LocallyModified'=>'bool2',
    */


    /**
     * Searches for Term definitions based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `trm_ID`: If provided in `$this->data['trm_ID']`. Can be filtered by `$this->data['withimages']==1`
     *   to only include terms with associated images.
     * - `trm_Label`: If provided in `$this->data['trm_Label']`.
     * - `trm_Domain`: If provided in `$this->data['trm_Domain']`.
     * - `trm_Status`: If provided in `$this->data['trm_Status']`.
     * - `trm_Modified`: If provided in `$this->data['trm_Modified']`.
     * - `trm_Code`: If provided in `$this->data['trm_Code']`.
     * - `trm_ParentTermID`: If provided in `$this->data['trm_ParentTermID']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `trm_ID`.
     * - 'name' (or null/default): Returns `trm_ID`, `trm_Label`.
     * - 'list': Returns `trm_ID`, `trm_Label`, `trm_InverseTermID`, `trm_Description`, `trm_Domain`,
     *   `trm_ParentTermID` (defaulted to 0 if null), `trm_VocabularyGroupID`, `trm_OrderInBranch`, `trm_Code`, `trm_Status`.
     * - 'full': Returns an extended set of fields including `trm_ID`, `trm_Label`, `trm_Description`,
     *   `trm_InverseTermID`, `trm_ParentTermID` (defaulted to 0 if null), `trm_VocabularyGroupID`,
     *   `trm_OrderInBranch`, `trm_Code`, `trm_Status`, `trm_Domain`, `trm_SemanticReferenceURL`,
     *   `trm_OriginatingDBID`, `trm_IDInOriginatingDB`, and an empty `trm_Parents` (likely for future use or client-side population).
     *   Multi-language fields are handled if `$this->multilangFields` is populated.
     * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected.
     *
     * The primary key `trm_ID` is always included as the first field in the results.
     * Ordering is not explicitly defined in this method, relying on `DbEntitySearch::setOrderBy()` if called,
     * or default database order.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
     *                     or if the database query fails.
     */
    public function search(){


        if(@$this->data['withimages']==1){

            $ids = $this->data['trm_ID'];
            $new_lib_dir = $this->system->getSysDir() . 'entity/defTerms/thumbnail/';
            $files = array();

            foreach ($ids as $id){
                $new_filename = $new_lib_dir.$id.'.png';
                if(file_exists($new_filename)){
                    array_push($files, $id);
                }
            }
            if(empty($files)){
                $this->data['trm_ID'] = 999999999;
            }else{
                $this->data['trm_ID'] = $files;
            }

        }

        if(parent::search()===false){
            return false;
        }

        $multiLangs = null;
        $orderBy = '';
        //compose WHERE
        $where = array();

        $pred = $this->searchMgr->getPredicate('trm_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_Label');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_Domain');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_Status');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_Modified');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_Code');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('trm_ParentTermID', true);
        if($pred!=null) {array_push($where, $pred);}


        $needCheck = false;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'trm_ID';

        }elseif($this->data['details']==null || @$this->data['details']=='name'){

            $this->data['details'] = 'trm_ID,trm_Label';

        }elseif(@$this->data['details']=='list'){

            $this->data['details'] = 'trm_ID,trm_Label,trm_InverseTermID,trm_Description,'
            .'trm_Domain,IFNULL(trm_ParentTermID, 0) as trm_ParentTermID'
            .',trm_VocabularyGroupID,trm_OrderInBranch,trm_Code,trm_Status';

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = 'trm_ID,trm_Label,trm_Description,trm_InverseTermID,'
            .'IFNULL(trm_ParentTermID, 0) as trm_ParentTermID'
            .',trm_VocabularyGroupID,trm_OrderInBranch,trm_Code,trm_Status,trm_Domain,trm_SemanticReferenceURL'
            .',trm_OriginatingDBID,trm_IDInOriginatingDB, "" as trm_Parents';//trm_Modified

            $multiLangs = $this->multilangFields;
        }else {
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        if($needCheck){
            //validate names of fields
            foreach($this->data['details'] as $fieldname){
                if(!@$this->fields[$fieldname]){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                    return false;
                }
            }
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('trm_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'trm_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details']).' FROM defTerms';

        if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
        }
        $query = $query.$orderBy.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $res = $this->searchMgr->execute($query, $is_ids_only, 'defTerms', null, $multiLangs );
        return $res;

    }

    //
    // loads all term links   from defTermsLinks
    //
    /**
     * Loads all term links (parent-child relationships) from `defTermsLinks` or `defTerms`.
     *
     * The source table depends on the database version.
     * Returns an associative array where keys are parent term IDs and values are arrays of child term IDs.
     *
     * @return array An associative array of term links (parent_id => [child_id1, child_id2,...]).
     */
    public function getTermLinks(){

        $matches = array();

        $mysqli = $this->system->getMysqli();

        $dbVer = $this->system->settings->get('sys_dbVersion');
        $dbVerSub = $this->system->settings->get('sys_dbSubVersion');

        //compose query
        if($dbVer==1 && $dbVerSub>2){
            $query = 'SELECT trl_ParentID, trl_TermID FROM defTermsLinks ORDER BY trl_ParentID';
        }else{
            $query = 'SELECT trm_ParentTermID, trm_ID FROM defTerms ORDER BY trm_ParentTermID';
        }

        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){

                if(@$matches[$row[0]]){
                    $matches[$row[0]][] = $row[1];
                }else{
                    $matches[$row[0]] = array($row[1]);
                }
            }
            $res->close();
        }


        return $matches;

    }
    
    //
    // get list of icons ids
    //
    /**
     * Gets a list of term IDs that have associated thumbnail images.
     *
     * Scans the `entity/defTerms/thumbnail/` directory for .png files
     * where the filename (without extension) is a numeric term ID.
     *
     * @return array An array of term IDs that have thumbnails.
     */
    public function getTermIcons(){
        
        $res = [];
        $dir = $this->system->getSysDir() . 'entity/defTerms/thumbnail/';
        $all_files = scandir($dir);
        foreach ($all_files as $filename){
             $filename = strstr(basename($filename),'.',true);
             if(intval($filename)>0){
                 $res[] = $filename;
             }
        }
        
       return $res;
    }    

    //
    // trm_Label may have periods. Periods are taken as indicators of hierarchy.
    //
    /**
     * Imports terms, potentially creating a hierarchy based on a separator in `trm_Label`.
     *
     * This method processes records from `$this->records` (expected to be prepared by `prepareRecords`).
     * If a `trm_ParentTermID` is set in the first record, it assumes a flat list import under that parent.
     * Otherwise, it parses `trm_Label` fields using a separator (default '.') to build a tree structure,
     * then saves this tree using `_saveTree`.
     *
     * @return array|false An array of added term IDs on success, or false on failure.
     *                     Returns an empty array if no records to import.
     */
    private function _importTerms(){

        //extract records from $_REQUEST data
        if(!$this->prepareRecords(true)){
            return false;
        }

        //create tree array $record['trm_ParentTermID']
        if(isEmptyArray($this->records)){
            return array();
        }

        if(@$this->records[0]['trm_VocabularyGroupID']>0){
            return $this->save();
        }


        //group by parent term ID
        $records_by_prent_id = array();
        foreach($this->records as $idx => $record){
            if(!($record['trm_ParentTermID']>0)){
                continue;
            }
            if(!@$records_by_prent_id[$record['trm_ParentTermID']]){
                $records_by_prent_id[$record['trm_ParentTermID']] = array();
            }
            $records_by_prent_id[$record['trm_ParentTermID']][] = $record;

        }

        $terms_added = array();

        foreach($records_by_prent_id as $parentID => $records){

            //root, children are record idx
            $this->records_all = array();

            $this->labels_to_idx = array();//term label to records_all index

            //label->array(labels)
            $tree = $this->_parseHierarchy( $records );

            //keep index
            foreach($records as $record_idx => $record){
                $this->labels_to_idx[$record['trm_Label']] = $record_idx;
            }
            $this->records_all = $records;

            $ret = $this->_saveTree($tree, $parentID, '');
            if($ret===false){
                return false;
            }
            if(is_array($ret)){
                $terms_added = array_merge($terms_added, $ret);
            }
        }

        return $terms_added;
    }

    //
    //
    //
    /**
     * Parses a flat list of term labels into a hierarchical tree structure.
     *
     * Uses a specified separator (default '.') within the term labels to determine hierarchy.
     * For example, "Animal.Mammal.Dog" becomes a tree: Animal -> Mammal -> Dog.
     * Optionally retains parent labels in child keys (e.g., "Animal.Mammal" instead of just "Mammal").
     *
     * @param array $input An array of term records, each expected to have a 'trm_Label' key.
     * @return array A nested array representing the term hierarchy.
     *               Keys are term labels, values are arrays of children (or an empty array for leaf nodes).
     */
    private function _parseHierarchy($input) {
        $result = array();

        $trm_sep = @$this->data['term_separator'];

        if($trm_sep === null){
            $trm_sep = '.';
        }

        $trm_extendLabel = @$this->data['term_RetainParentLabel'];
        if($trm_extendLabel != 1){
            $trm_extendLabel = false;
        }

        foreach ($input as $path) {
            $path = $path['trm_Label'];

            $prev = &$result;

            if($trm_sep!=''){
                $s = strtok($path, $trm_sep);

                $hierarchy = "{$s}{$trm_sep}";

                //iterate path
                while (($next = strtok($trm_sep)) !== false) {
                    if (!isset($prev[$s])) {
                        $prev[$s] = array();
                    }

                    $prev = &$prev[$s];
                    $s = !$trm_extendLabel ? $next : "{$hierarchy}{$next}";
                    $hierarchy .= "{$next}{$trm_sep}";
                }

            }else{
                $s = $path;
            }
            if (!isset($prev[$s])) {
                $prev[$s] = array();
            }

            unset($prev);
        }
        return $result;
    }

    //
    // tree: idx->array(idx->array(),.... )
    //
    /**
     * Recursively saves a tree of terms to the database.
     *
     * For each term in the current level of the tree:
     * - Checks if a term with the same label already exists under the given `$parentID`.
     * - If not, or if the existing term is different, it prepares and saves the new term.
     * - Recursively calls itself to save any children of the current term.
     *
     * Uses `$this->labels_to_idx` and `$this->records_all` to map parsed labels back to original
     * record data and update/create term records.
     *
     * @param array $tree The current level of the term tree (label => children_array).
     * @param int $parentID The `trm_ID` of the parent for the current level of terms.
     * @param string $parentLabel The concatenated label of the parent path (used for reconstructing full labels).
     * @return array|false An array of newly added term IDs on success, or false on any save failure.
     */
    private function _saveTree($tree, $parentID, $parentLabel){

        //reset array of record for save
        $this->records = array();

        $mysqli = $this->system->getMysqli();

        //fill records array
        foreach($tree as $label => $children)
        {
            $record_idx = @$this->labels_to_idx[$parentLabel.$label];
            if($record_idx===null){ //one of parent terms not defined - add it
                $record_idx = count($this->records_all);
                $this->labels_to_idx[$parentLabel.$label] = $record_idx;
                $this->records_all[] = array();
            }

            $this->records_all[$record_idx]['trm_ParentTermID'] = $parentID;
            $this->records_all[$record_idx]['trm_Label'] = $label;
            $this->records_all[$record_idx]['trm_Domain'] = $this->records_all[0]['trm_Domain'];

            $record = $this->records_all[$record_idx];

            //check for term with the same name for this parent
            if(@$record['trm_ID']>0){
                //already exists
                continue;
            }else{
                $query = 'select trm_ID from defTerms where trm_ParentTermID='
                .$parentID.' and trm_Label="'.$mysqli->real_escape_string($label).'"';
                $trmID = mysql__select_value($mysqli, $query);
                if($trmID>0){
                    //already exists
                    $this->records_all[$record_idx]['trm_ID'] = $trmID;
                    continue;
                }
            }

            $this->records[$record_idx] = $record;
        }

        $terms_added = array();

        if(!empty($this->records)){
            $ret = $this->save();
            if($ret!==false) {
                $terms_added = $ret;
            }
        }else{
            $ret = true; //all terms already in db
        }

        if($ret!==false){
            //assign recID from records to records_all
            foreach($this->records as $record_idx => $record){
                //$this->primaryField
                $this->records_all[$record_idx]['trm_ID'] = $record['trm_ID'];
            }

            //go to next level
            foreach($tree as $label => $children)
            {
                if(!isEmptyArray($children)){
                    $record_idx = @$this->labels_to_idx[$parentLabel.$label];
                    $ret = $this->_saveTree($children, $this->records_all[$record_idx]['trm_ID'], $parentLabel.$label.'.');
                    if($ret===false){
                        return false;
                    }
                    if(is_array($ret)){
                        $terms_added = array_merge($terms_added, $ret);
                    }
                }
            }
            return $terms_added;
        }else{
            return false;
        }

    }

    //
    // Validates values before save and sets default values
    //
    /**
     * Prepares term records before saving.
     *
     * Validates `trm_Label` for duplication within the same parent/vocabulary.
     * Validates `trm_Code` for duplication if provided.
     * Prevents moving a term to a different vocabulary if it or its children are in use.
     * Sets default values for `trm_Modified`, `trm_Domain`, `trm_Status`, `trm_InverseTermID`, `trm_OrderInBranch`.
     * Sets `is_new` flag.
     *
     * @param bool $ignore_duplications If true, duplicate labels/codes will not immediately cause failure,
     *                                  but the duplicate records will be removed from `$this->records`.
     *                                  Used by `_importTerms`. Defaults to false.
     * @return bool True if preparation is successful and validation passes, false otherwise.
     */
    protected function prepareRecords($ignore_duplications=false){

        $ret = parent::prepareRecords();

        $duplications = [];

        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication on the same level
            $mysqli = $this->system->getMysqli();

            if(@$this->records[$idx]['trm_Label']!=null && $this->records[$idx]['trm_Label']!=''){

                $s2 = null;

                // Strip trailing + double spacing
                $this->records[$idx]['trm_Label'] = USanitize::cleanupSpaces($this->records[$idx]['trm_Label']);

                if(@$this->records[$idx]['trm_ParentTermID']>0){

                    if(isset($this->data['trm_parentID'])){
                        $parent_id = $this->data['trm_parentID'];// Replace with alternative parent, if supplied
                    }else{
                        $parent_id = $this->records[$idx]['trm_ParentTermID'];
                    }

                    if(@$this->records[$idx]['trm_Label'] || @$this->records[$idx]['trm_Code']){

                        $labels = $this->getLabelsAndCodes( $parent_id, false );

                        if(is_array($labels)){

                            foreach($labels as $id=>$vals){

                                if($id!=@$this->records[$idx]['trm_ID'])
                                {

                                    if(@$this->records[$idx]['trm_Label'] &&
                                    strcasecmp($this->records[$idx]['trm_Label'],$vals['trm_Label'])==0){
                                        $s2 = 'Duplicate label ('.$this->records[$idx]['trm_Label'].') ';
                                    }elseif (@$this->records[$idx]['trm_Code'] &&
                                    strcasecmp($this->records[$idx]['trm_Code'],$vals['trm_Code'])==0)
                                    {
                                        $s2 = 'Duplicate code ('.$this->records[$idx]['trm_Code'].') ';
                                    }

                                    if($s2!==null){ //duplication
                                        if($ignore_duplications){
                                            $duplications[] = $idx;
                                            $s2 = null;
                                        }else{
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $s1 = 'Term';
                    $s3 = ' in the vocabulary';
                }else{
                    //vocabulary
                    $this->records[$idx]['trm_ParentTermID'] = null;
                    $sWhere = ' AND (trm_ParentTermID IS NULL OR trm_ParentTermID=0)';

                    $s1 = 'Vocabulary';
                    $s3 = '';


                    $res = mysql__select_value($mysqli,
                        "SELECT trm_ID FROM ".$this->config['tableName']."  WHERE (trm_Label='"
                        .$mysqli->real_escape_string( $this->records[$idx]['trm_Label'])."'"
                        .') '.$sWhere );
                    if($res>0 && $res!=@$this->records[$idx]['trm_ID']){
                        $s2 = 'The provided name already exists';
                    }

                }


                if($s2){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, $s1.' cannot be saved. '.$s2.$s3);
                    return false;
                }
            }
            //move term to different vocabulary - prevent if it or its children are in use in recDetails
            if(@$this->records[$idx]['trm_ID']>0 && @$this->records[$idx]['trm_ParentTermID']>0){

                $real_vocab_id = getTermTopMostParent($mysqli, $this->records[$idx]['trm_ID']);
                //the same vocabulary
                if(!($this->records[$idx]['trm_ParentTermID']==$real_vocab_id ||
                getTermTopMostParent($mysqli,$this->records[$idx]['trm_ParentTermID'])==$real_vocab_id ))
                {
                    $check_dty_IDs = $this->getFieldsThatUseVocabulary($real_vocab_id);
                    $ret = $this->findRecordWhereTermInUse($this->records[$idx]['trm_ID'], $check_dty_IDs);
                    if($ret && @$ret['reccount']>0){
                        $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'Term or its children already used in records', $ret);
                        return false;
                    }
                }

            }

            $this->records[$idx]['trm_Modified'] = date(DATE_8601);//reset
            if(@$this->records[$idx]['trm_Domain']!='relation') {$this->records[$idx]['trm_Domain'] = 'enum';}
            if(!@$this->records[$idx]['trm_Status']) {$this->records[$idx]['trm_Status'] = 'open';}
            if(!(@$this->records[$idx]['trm_InverseTermID']>0)) {$this->records[$idx]['trm_InverseTermID'] = null;}
            if(!(@$this->records[$idx]['trm_OrderInBranch']>0)) {$this->records[$idx]['trm_OrderInBranch'] = null;}

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['trm_ID']>0));
        }//foreach

        if(!empty($duplications)){
            foreach($duplications as $idx){
                unset($this->records[$idx]);
            }
        }

        return $ret;
    }

    //
    // returns array of saved record ids or false
    //
    /**
     * Saves term records.
     *
     * Calls `parent::save()` and then performs additional operations:
     * - Updates `trm_OriginatingDBID`, `trm_NameInOriginatingDB`, `trm_IDInOriginatingDB` for new terms.
     * - Updates `trm_LocallyModified` for existing terms.
     * - Handles term thumbnail image (renaming temporary file or deleting).
     * - Manages symmetrical inverse term relationships (`trm_InverseTermID`).
     *
     * The behavior can be modified by `$this->data['isfull']` (defaults to true if not set),
     * which controls whether thumbnail and inverse term logic is executed.
     *
     * @return array|false An array of saved term IDs on success, false on failure.
     */
    public function save(){

        $mysqli = $this->system->getMysqli();

        $is_full = (@$this->data['isfull']!=0);

        if($is_full){

            //extract records from $_REQUEST data
            if($this->records==null){ //records can be pepared beforehand
                if(!$this->prepareRecords()){
                    return false;
                }
            }
            //keep old inverse id
            foreach($this->records as $idx=>$record){
                if(!$record['is_new']){

                    $this->records[$idx]['old_inverse_id'] = mysql__select_value($mysqli,
                        'select trm_InverseTermID from defTerms where trm_ID='.$record['trm_ID']);

                }
            }
        }

        $ret = parent::save();

        //treat thumbnail image and symmetrical inverse terms (the latter for new term only)
        if($ret!==false){

            $dbID = $this->system->settings->get('sys_dbRegisteredID');
            if(!($dbID>0)) {$dbID = 0;}

            foreach($this->records as $record){
                $trm_ID = @$record['trm_ID'];
                if($trm_ID>0 && in_array($trm_ID, $ret) && $is_full)
                {

                    $query = null;
                    //set dbid or update modified locally
                    if($record['is_new']){

                        $query= 'UPDATE defTerms SET trm_OriginatingDBID='.$dbID
                        .', trm_NameInOriginatingDB=trm_Label'
                        .', trm_IDInOriginatingDB='.$trm_ID
                        .' WHERE (NOT trm_OriginatingDBID>0 OR trm_OriginatingDBID IS NULL) AND trm_ID='.$trm_ID;

                    }else{
                        $query = 'UPDATE defTerms SET trm_LocallyModified=IF(trm_OriginatingDBID>0,1,0)'
                        . ' WHERE trm_ID = '.$trm_ID;
                    }
                    $res = $mysqli->query($query);


                    $thumb_file_name = @$record['trm_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name == 'delete'){

                        $thumb = parent::getEntityImagePath($trm_ID, 'thumb', HEURIST_DBNAME, 'png');
                        $icon = parent::getEntityImagePath($trm_ID, 'icon', HEURIST_DBNAME, 'png');

                        if(!empty($thumb) && file_exists($thumb)){
                            unlink($thumb);
                        }
                        if(!empty($icon) && file_exists($icon)){
                            unlink($icon);
                        }

                    }elseif($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }

                    $inverse_termid = @$record['trm_InverseTermID'];
                    $inverse_termid_old = @$record['old_inverse_id'];
                    $is_symmetrical = (@$record['trm_InverseSymmetrical']!=0);

                    if($inverse_termid_old!=$inverse_termid && $is_symmetrical){
                        $trmID = $record['trm_ID'];
                        if($inverse_termid>0){
                            //set mutual inversion for inverse term
                            $query = "update defTerms set trm_InverseTermID=$trmID where trm_ID=$inverse_termid";
                            $res = $mysqli->query($query);
                        }
                        if ($inverse_termid_old>0){
                            //clear mutual inversion for previous inversion
                            $query = "update defTerms set trm_InverseTermID=null where trm_ID=$inverse_termid_old and trm_InverseTermID=$trmID";
                            $res = $mysqli->query($query);
                        }
                    }



                }
            }
        }

        return $ret;
    }

    //   Actions:
    //   1) reference=1 - add/move/remove terms by reference
    //   2) merge_id>0 retain_id>0 - merge terms within vocabulary
    //   3) import terms from csv
    //   4) get_translations - from defTranslations
    //
    /**
     * Performs batch actions on terms.
     *
     * Supported actions (determined by parameters in `$this->data`):
     * - **Reference Management**: Add/move/remove terms as references under a vocabulary/parent term.
     *   (Requires: `reference=1`, `trm_ID` (array), `new_VocabID`, `new_ParentTermID`, `old_VocabID`, `old_ParentTermID`)
     * - **Term Merging**: Merge one term (`merge_id`) into another (`retain_id`).
     *   (Requires: `merge_id`, `retain_id`, optionally `trm_Code`, `trm_Description` for the retained term)
     * - **Get Translations**: Retrieves translations for terms.
     *   (Requires: `get_translations` (array of term IDs or single ID), optionally `search_by` ('trm_ParentTermID' or 'trm_ID'))
     * - **Set Translations**: Saves translations for terms.
     *   (Requires: `set_translations` (array of translation data), `vcb_ID`)
     * - **Import Terms (CSV)**: Imports terms, potentially creating hierarchies. (Calls `_importTerms`)
     *   (Default action if others are not specified, expects term data in `$this->records` or via `prepareRecords()`)
     *
     * Most actions are performed within a database transaction.
     *
     * @return array|bool|null Result of the specific batch action.
     *                         - For reference management, merge, import: True on success, false on failure.
     *                         - For get_translations: Array of translation data.
     *                         - For set_translations: Array with counts of added/updated/etc. translations.
     *                         Returns false if an invalid action or parameters are provided.
     */
    public function batch_action(){

        $mysqli = $this->system->getMysqli();

        if(!@$this->data['get_translations']){
            $this->need_transaction = false;
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        $ret = true;

        if(@$this->data['reference'])
        {
            //add or remove term to vocabuary by reference
            $trm_IDs = prepareIds($this->data['trm_ID']);

            if(empty($trm_IDs)){

                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
                $ret = false;

            }else{
                //old_ParentTermID
                //new_ParentTermID

                $isOK = true;

                $new_vocab = @$this->data['new_VocabID'];
                $new_parent = @$this->data['new_ParentTermID'];
                if(!($new_parent>0) && $new_vocab>0){
                    $new_parent  = $new_vocab;
                }elseif($new_parent>0){
                    $real_vocab_id = getTermTopMostParent($mysqli, $new_parent);
                    if($real_vocab_id != $new_vocab){
                        $this->system->addError(HEURIST_ACTION_BLOCKED, 'Reference can\'t have children.');
                        $isOK = false;
                        $ret = false;
                    }
                }

                if($isOK)
                {
                    $all_children = null;
                    $labels = null;
                    $check_dty_IDs = null;

                    $old_vocab = @$this->data['old_VocabID'];
                    $old_parent = @$this->data['old_ParentTermID'];
                    if(!($old_parent>0) && $old_vocab>0) {$old_parent = $old_vocab;}

                    if($new_parent>0){
                        //get labels and codes for vocabulary
                        $labels = $this->getLabelsAndCodes($new_vocab);
                        if(isEmptyArray($labels)) {$labels = null;}
                    }
                    if($new_vocab>0 && $new_vocab!=$old_vocab){
                        $all_children = $this->getChildren($new_vocab);
                    }
                    if($old_vocab>0 && $new_vocab!=$old_vocab){
                        $check_dty_IDs = $this->getFieldsThatUseVocabulary($old_vocab);
                    }

                    foreach($trm_IDs as $trm_ID){

                        if($new_parent>0)
                        {
                            //1. check that term is not in vocabulary already - avoid duplications
                            if(is_array($all_children) && in_array($trm_ID, $all_children)){
                                $this->system->addError(HEURIST_ACTION_BLOCKED,  'Term is already in vocabulary');
                                $ret = false;
                                break;
                            }
                            //2. check that the same level does not have the term with the same name
                            if($labels){
                                //get label and code for current term
                                $vals = mysql__select_row_assoc($mysqli,
                                    'SELECT trm_Label, trm_Code FROM '
                                    .$this->config['tableName'].' WHERE trm_ID='.$trm_ID);
                                foreach($labels as $id=>$vals2)
                                {
                                    if($id!=$trm_ID){
                                        $sMsg = null;
                                        if(strcasecmp($vals['trm_Label'],$vals2['trm_Label'])==0){
                                            $sMsg = 'Term with label <b>'.$vals['trm_Label'];
                                        }elseif($vals['trm_Code'] && strcasecmp($vals['trm_Code'],$vals2['trm_Code'])==0){
                                            $sMsg = 'Term with code <b>'.$vals['trm_Code'];
                                        }
                                        if($sMsg){
                                            $this->system->addError(HEURIST_ACTION_BLOCKED,
                                                $sMsg.'</b> already exists in the vocabulary');
                                            $ret = false;
                                            break;
                                        }
                                    }
                                }
                                if(!$ret) {break;}
                            }

                        }
                        //3. term cannot be removed if it has real children (not reference)
                        if($old_parent>0){
                            // cannot delete term if it is a real parent
                            $parent_id = mysql__select_value($mysqli,
                                'SELECT trm_ParentTermID FROM defTerms where trm_ID='.$trm_ID);
                            if($parent_id == $old_parent){
                                $this->system->addError(HEURIST_ACTION_BLOCKED,
                                    'Term cannot be orphaned. ('.$old_parent.')');
                                $ret =false;
                                break;
                            }
                        }
                        //4. term is removed from vocabulary - check its usage in recDetails
                        if(!isEmptyArray($check_dty_IDs)){

                            $ret = $this->findRecordWhereTermInUse($trm_ID, $check_dty_IDs);

                            if($ret && @$ret['reccount']>0){
                                $this->system->addError(HEURIST_ACTION_BLOCKED,
                                    'Term or its children already used in records', $ret);
                                $ret = false;
                                break;
                            }
                        }


                        if($new_parent>0)
                        {
                            $ret = $mysqli->query(
                                'insert into defTermsLinks (trl_ParentID,trl_TermID)'
                                .'values ('.intval($new_parent).','.intval($trm_ID).')');
                            if(!$ret){
                                $this->system->addError(HEURIST_DB_ERROR,
                                    'Cannot insert to defTermsLinks table', $mysqli->error);
                                $ret = false;
                                break;
                            }
                        }
                        if($old_parent>0)
                        {
                            $ret = $mysqli->query(
                                'delete from defTermsLinks where trl_ParentID='
                                .intval($old_parent).' AND trl_TermID='.intval($trm_ID));

                            if(!$ret){
                                $this->system->addError(HEURIST_DB_ERROR,
                                    'Cannot delete from defTermsLinks table', $mysqli->error);
                                $ret = false;
                                break;
                            }
                        }
                    }//for



                }
            }

        }
        elseif(@$this->data['merge_id']>0 && @$this->data['retain_id']>0)
        {
            //merging is performed within one vocabulary only!!!!
            //check that both have the same vocab

            //@TODO check usage for term by ref!!!!


            //MERGE TERMS

            $merge_id = $this->data['merge_id'];
            $retain_id = $this->data['retain_id'];

            //check usage
            $ret = $this->isTermNotInUse($merge_id, true, false);//check detailtypes, do not check in records
            if(is_array($ret)){
                $trm_Label = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $merge_id]);
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                    "Cannot merge {$trm_Label} (#{$merge_id}). This term has references", $ret);
                $ret = false;
            }

            if($ret){
                //1. change parent id for all children terms
                $query = 'update defTerms set trm_ParentTermID='.intval($retain_id).' where trm_ParentTermID = '.intval($merge_id);
                $res = $mysqli->query($query);
                if ($mysqli->error) {
                    $this->system->addError(HEURIST_DB_ERROR,
                        'SQL error - cannot change parent term for '.$merge_id.' from defTerms table', $mysqli->error);
                    $ret = false;
                }
            }
            if($ret){
                mysql__supress_trigger($mysqli, true );

                //2. update entries in recDetails for all detail type enum or reltype
                $query = "update recDetails, defDetailTypes set dtl_Value=".intval($retain_id)
                ." where (dty_ID = dtl_DetailTypeID ) and "
                ." (dty_Type='enum' or dty_Type='relationtype') and "
                ." (dtl_Value=".intval($merge_id).")";

                $res = $mysqli->query($query);
                if ($mysqli->error) {
                    $this->system->addError(HEURIST_DB_ERROR,
                        'SQL error in mergeTerms updating record details', $mysqli->error);
                    $ret = false;
                }
                mysql__supress_trigger($mysqli, false);

            }
            if($ret){
                //3. delete term $merge_id
                $query = 'delete from defTerms where trm_ID = '.intval($merge_id);
                $res = $mysqli->query($query);
                if ($mysqli->error) {
                    $this->system->addError(HEURIST_DB_ERROR,
                        "SQL error deleting term $merge_id from defTerms table", $mysqli->error);
                    $ret = false;
                }
            }
            if($ret){

                //4. update term $retain_id
                $values = array('trm_ID'=>$retain_id);
                if(@$this->data['trm_Code']) {$values['trm_Code'] = $this->data['trm_Code'];}
                if(@$this->data['trm_Description']) {$values['trm_Description'] = $this->data['trm_Description'];}

                if(count($values)>1){

                    $ret = mysql__insertupdate($mysqli,
                        $this->config['tableName'], $this->fields,
                        $values );

                    if(!$ret){
                        $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'Cannot save data in table '.$this->config['entityName'], $ret);
                        $ret = false;
                    }

                }

            }

        }
        elseif(@$this->data['get_translations']){

            $field = array_key_exists('search_by', $this->data) ? $this->data['search_by'] : 'trm_ID';
            $field = $field != 'trm_ParentTermID' && $field != 'trm_ID' ? 'trm_ID' : $field;

            $ids = $this->data['get_translations'];
            if($field == 'trm_ParentTermID'){
                $ids = mysql__select_list2($mysqli, 'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID=' . $ids[0]);
            }
            if(is_array($ids)){
                $ids = implode(',', $ids);
            }elseif(!is_int($ids) || $ids < 0){
                $ids = '';
            }

            return $this->_getTermTranslations(false, $ids);//see dbsData.php

        }
        elseif(@$this->data['set_translations']){
            //set_translations - is array of pairs (trm_ID or trm_Label=>translated label (with lang prefix))
            $ret = $this->_setTermTranslations(intval($this->data['vcb_ID']), $this->data['set_translations']);//see dbsData.php


        }else{
            //import terms (from csv)
            $ret = $this->_importTerms();
        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    //
    // Retrieve and create a recordset of term translations
    //
    /**
     * Retrieves term translations from the `defTranslations` table.
     *
     * @param bool $label_only If true, only retrieves translations for 'trm_Label'. Otherwise, for all 'trm_%' sources.
     * @param string|array|null $trm_ids A single term ID, an array of term IDs, or a comma-separated string of term IDs
     *                                   to filter translations. If null, retrieves for all terms matching $label_only criteria.
     * @return array An associative array representing a recordset of translations,
     *               with keys 'reccount', 'fields', 'records', 'order', 'entityName'.
     */
    private function _getTermTranslations($label_only = true, $trm_ids = null){

        $mysqli = $this->system->getMysqli();

        $fields = array('trn_ID', 'trn_Code', 'trn_Source', 'trn_LanguageCode', 'trn_Translation');
        $records = array();

        $where_clause = $label_only ? 'trn_Source = "trm_Label"' : 'trn_Source LIKE "trm_%"';

        if(!empty($trm_ids)){ // add term id filter

            $code_clause = '';
            if(is_array($trm_ids)){
                $trm_ids = prepareIds($trm_ids);

                $code_clause = !empty($trm_ids) ? 'trn_Code IN (' . implode(',', $trm_ids) . ')' : '';
            }elseif(is_int($trm_ids) && $trm_ids > 0){
                $code_clause = 'trn_Code = ' . intval($trm_ids);
            }

            $where_clause .= empty($code_clause) ? '' : SQL_AND . $code_clause;
        }

        $query = 'SELECT trn_ID, trn_Code, trn_Source, trn_LanguageCode, trn_Translation '
        . 'FROM defTranslations '
        . 'WHERE ' . $where_clause;

        $res = $mysqli->query($query);
        if($res){

            while($row = $res->fetch_row()){
                $records[$row[0]] = $row;
            }
        }

        return array(
            'reccount'=>count($records),
            'fields'=>$fields,
            'records'=>$records,
            'order'=>array_keys($records),
            'entityName'=>$this->config['entityName']
        );
    }

    //
    //
    //
    /**
     * Sets (inserts or updates) term translations in the `defTranslations` table.
     *
     * Input data is expected to be an array where each item has a 'ref_id' (original term label)
     * and then key-value pairs for field_name => "lang_code:translated_value".
     *
     * @param int $vcb_ID The vocabulary ID to which these terms belong (used for context, not direct filtering here).
     * @param array|string $data An array of translation data, or a JSON string representing such an array.
     * @return array|false An array with counts of operations (lang_missed, not_found, added, updated, error)
     *                     on success, or false on initial validation failure.
     */
    private function _setTermTranslations($vcb_ID, $data){
        if(!($vcb_ID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Vocabulary not defined');
            return false;
        }

        if(!is_array($data)){
            $data = json_decode($data, true);
        }
        if(!is_array($data)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Data to be imported is not valid json');
            $res = false;
        }else{

            $cnt_lang_missed = 0;
            $cnt_not_found = 0;
            $cnt_added = 0;
            $cnt_updated = 0;
            $cnt_error = 0;

            $mysqli = $this->system->getMysqli();

            $stmt_select = $mysqli->prepare('SELECT trm_ID FROM defTerms WHERE trm_Label=?');
            $stmt_update = $mysqli->prepare('UPDATE defTranslations SET trn_Translation=? WHERE trn_Code=? AND trn_Source=? AND trn_LanguageCode=?');
            $stmt_insert = $mysqli->prepare('INSERT INTO defTranslations (trn_Code, trn_Source, trn_LanguageCode, trn_Translation) VALUES (?,?,?,?)');
            $stmt_select2 = $mysqli->prepare('SELECT trn_Translation FROM defTranslations WHERE trn_Code=? AND trn_Source=? AND trn_LanguageCode=?');


            $all_labels = $this->getLabelsAndCodes($vcb_ID);


            foreach($data as $translation){

                $ref_id = $translation['ref_id'];

                //find trm_ID
                $trm_ID = -1;
                foreach($all_labels as $id=>$val){
                    if(strcasecmp($ref_id, $val['trm_Label'])==0){
                        $trm_ID = $id;
                        break;
                    }

                }

                if($trm_ID>0){

                    foreach($translation as $field_name=>$value){

                        if($field_name=='ref_id') {continue;}

                        list($lang_code, $value) = extractLangPrefix($value);

                        if($lang_code!=null){

                            $stmt_select2->bind_param('iss', $trm_ID, $field_name, $lang_code);
                            if($stmt_select2->execute()){
                                $result = $stmt_select2->get_result();
                                $row = $result->fetch_row();
                                if($row){
                                    if($value==$row[0]){
                                        continue; //already exist
                                    }else{
                                        $stmt_update->bind_param('siss', $value, $trm_ID, $field_name, $lang_code );
                                        $stmt_update->execute();
                                        if($mysqli->affected_rows>0){
                                            $cnt_updated++;
                                            continue;
                                        }
                                    }
                                }
                            }else{
                                $cnt_error++;
                                continue;
                            }
                            $stmt_insert->bind_param('isss', $trm_ID, $field_name, $lang_code, $value);
                            $stmt_insert->execute();
                            if($mysqli->insert_id>0){
                                $cnt_added++;
                            }else{
                                $cnt_error++;
                            }

                        }else{
                            $cnt_lang_missed++;
                        }
                    }
                }else{
                    $cnt_not_found++;
                }
            }

            return array('cnt_lang_missed'=>$cnt_lang_missed,'cnt_not_found'=>$cnt_not_found,
                'cnt_added'=>$cnt_added,'cnt_updated'=>$cnt_updated,'cnt_error'=>$cnt_error);
        }

    }

    //
    //  Checks that term can be removed
    //   1) Has no
    //
    /**
     * Validates if the current user has permission for the requested term operation (especially delete).
     *
     * - Only administrators can perform operations.
     * - For deletions, it checks if the term or its children are in use (referenced by other
     *   detail types or used in records). If so, deletion is blocked.
     * - If a term and its children can be deleted, their IDs are added to `$this->recordIDs` for deletion.
     *
     * @return bool True if the user has permission and (for deletions) the term is not in use,
     *              false otherwise (errors are added to the system object).
     */
    protected function _validatePermission()
    {

        if(!$this->system->isAdmin()){ //there are records to update/delete

            $this->system->addError(HEURIST_REQUEST_DENIED,
                'You are not admin and can\'t edit vocabulary and terms. Insufficient rights (logout/in to refresh) for this operation');
            return false;
        }

        if(@$this->data['a'] == 'delete'){

            if(!@$this->recordIDs){
                $this->recordIDs = prepareIds($this->data[$this->primaryField]);
            }

            $children = array();
            $mysqli = $this->system->getMysqli();

            foreach($this->recordIDs as $trm_ID)
            {
                $ret = $this->isTermNotInUse($trm_ID, true, true);//check both records and defs
                if(is_array($ret)){
                    $trm_Label = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $trm_ID]);
                    $this->system->addError(HEURIST_ACTION_BLOCKED,
                        "Cannot delete {$trm_Label} (#{$trm_ID}). This term has references", $ret);//$ret
                    return false;
                }elseif($ret===false){ //mysql error
                    return false;
                }

                //get real children (not refs)
                $children2 = getTermChildren($trm_ID, $this->system, false);//see dbsData.php
                $children = array_merge($children, $children2);
            }
            $this->recordIDs = array_merge($this->recordIDs, $children);//delete children as well
        }
        return true;
    }

    //--------------------------------------------------------------------------
    //

    //
    //  get all enum and relmarker fields where vocabulary is in use
    //
    /**
     * Gets all detail type IDs (enum or relmarker) that use the given vocabulary ID.
     *
     * @param int $trm_ID The vocabulary term ID (trm_ParentTermID = 0 or NULL).
     * @return array An array of dty_ID values.
     */
    private function getFieldsThatUseVocabulary($trm_ID){

        $mysqli = $this->system->getMysqli();

        $query = 'SELECT dty_ID FROM defDetailTypes WHERE '
        .'(dty_JsonTermIDTree='.$trm_ID.') '
        .'AND (dty_Type=\'enum\' or dty_Type=\'relmarker\')';
        return mysql__select_list2($mysqli, $query, 'intval');

    }

    //
    // returns array - list of fields (where vocabulary is in use) and number of records
    // false - mysql error
    // true - term and its children are not in use
    //
    // $trm_ID - vocabulary
    // $infield - check in base fields
    // $indetails - check in recDetails
    //
    /**
     * Checks if a term (and its children, if it's a vocabulary root) is currently in use.
     *
     * A term is considered "in use" if:
     * - It's referenced as a vocabulary by any detail type (`dty_JsonTermIDTree`) (if `$infield` is true).
     * - It or its children are used as values in `recDetails` for relevant enum/relationtype fields (if `$indetails` is true).
     *
     * @param int $trm_ID The ID of the term (can be a vocabulary root or any term).
     * @param bool $infield If true, checks usage in `defDetailTypes.dty_JsonTermIDTree`.
     * @param bool $indetails If true, checks usage in `recDetails.dtl_Value`.
     * @return bool|array True if the term is not in use. An array detailing usage (children count,
     *                    detail types using it, record count using it, and specific record IDs if count is low)
     *                    if it is in use. False on MySQL error.
     */
    private function isTermNotInUse($trm_ID, $infield, $indetails){

        $mysqli = $this->system->getMysqli();

        $ret = array('children'=>0, 'detailtypes'=>array(), 'reccount'=>0);

        // is this vocabulary
        if($infield){
            //find possible entries in defDetailTypes dty_JsonTermIDTree
            $ret['detailtypes'] = $this->getFieldsThatUseVocabulary($trm_ID);
            //TODO: need to check inverseid or it will error by foreign key constraint?
        }

        // is this used in records (find usage in recDetails)
        if($indetails && (isEmptyArray($ret['detailtypes']))){

            $ret = $this->findRecordWhereTermInUse($trm_ID, null);

        }

        //$ret['children']>0 ||
        if((is_array(@$ret['detailtypes']) && !empty($ret['detailtypes']) )|| $ret['reccount']>0){
            return $ret;
        }else{
            return true;
        }
    }

    //
    // Returns flat array of all children for given term
    // uses defTermsLinks
    // $all_levels - false return direct children only
    //
    /**
     * Gets all children (descendants) of given parent term(s).
     *
     * Wraps the global function `getTermChildrenAll`.
     *
     * @param int|array $parent_ids A single parent term ID or an array of parent term IDs.
     * @param bool $all_levels If true, retrieves all descendants; if false, only direct children. Defaults to true.
     * @return array A flat array of child term IDs.
     */
    private function getChildren($parent_ids, $all_levels=true){
        return getTermChildrenAll($this->system->getMysqli(),$parent_ids, $all_levels);
    }

    //
    // get flat array of trm_ID=>trm_Label for given parent
    //
    /**
     * Gets the labels and codes for all children of a given parent term.
     *
     * @param int $parent_id The ID of the parent term.
     * @param bool $all_levels If true, retrieves for all descendants; if false, only direct children. Defaults to true.
     * @return array|null An associative array (`trm_ID => ['trm_Label' => ..., 'trm_Code' => ...]`)
     *                    or null if no children are found.
     */
    private function getLabelsAndCodes($parent_id, $all_levels=true){

        //get first level children
        $children = $this->getChildren($parent_id, $all_levels);
        if(is_array($children) && !empty($children)){

            $query = 'SELECT trm_ID, trm_Label, trm_Code FROM '
            .$this->config['tableName'].SQL_WHERE.predicateId('trm_ID', $children);
            //finds labels and codes
            return mysql__select_assoc($this->system->getMysqli(), $query);
        }
        return null;
    }

    //
    //
    //
    /**
     * Finds records where a given term (or its children) is used.
     *
     * Checks `recDetails.dtl_Value` for enum or relationtype fields.
     * If `$check_dty_IDs` is provided, limits the search to those detail types. Otherwise,
     * it determines relevant detail types by finding those that use the term's vocabulary.
     *
     * @param int $trm_ID The term ID whose usage is to be checked.
     * @param array|null $check_dty_IDs Optional array of specific `dty_ID`s to check.
     *                                   If null, relevant `dty_ID`s are determined from the term's vocabulary.
     * @return array|false An array containing `reccount` (total records using the term/children),
     *                     `records` (array of up to 10000 rec_IDs if total is below that or memory allows),
     *                     `recID` (the input $trm_ID), `fields` (the dty_IDs checked), and `children` (count of children checked).
     *                     Returns false on MySQL error.
     */
    private function findRecordWhereTermInUse($trm_ID, $check_dty_IDs){

        $ret = array();

        //find all children terms (including by reference)
        $children = $this->getChildren($trm_ID);
        $children[] = $trm_ID;  //itself

        $s = predicateId('dtl_Value', $children, SQL_AND);

        $mysqli = $this->system->getMysqli();

        if(isEmptyArray($check_dty_IDs)){
            $real_vocab_id = getTermTopMostParent($mysqli, $trm_ID);
            $check_dty_IDs = $this->getFieldsThatUseVocabulary($real_vocab_id);
        }

        if(!isEmptyArray($check_dty_IDs)){
            $check_dty_IDs = prepareIds($check_dty_IDs);//for snyk
            $this->system->defineConstant('DT_RELATION_TYPE');
            $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT dtl_RecID FROM recDetails '
            .'WHERE (dtl_DetailTypeID IN ('.DT_RELATION_TYPE.','.implode(',',$check_dty_IDs).')) '.$s;

        }else{
            $query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT dtl_RecID FROM recDetails, defDetailTypes "
            ."WHERE (dty_ID = dtl_DetailTypeID ) AND "
            ."(dty_Type='enum' or dty_Type='relationtype') $s";
        }

        $total_count_rows = 0;
        $records = array();
        $res = $mysqli->query($query);
        if ($res){
            $total_count_rows = mysql__found_rows($mysqli);

            if($total_count_rows>0 && ($total_count_rows<10000 || $total_count_rows*10<USystem::getConfigBytes('memory_limit'))){

                $records = array();
                while ($row = $res->fetch_row())  {
                    array_push($records, (int)$row[0]);
                }
            }

            $res->close();
        }

        if($mysqli->error){
            $this->system->addError(HEURIST_DB_ERROR,
                'Search query error (retrieving number of records that uses terms)', $mysqli->error);
            return false;
        }

        $ret['recID'] = $trm_ID;
        $ret['fields'] = $check_dty_IDs;
        $ret['reccount'] = $total_count_rows;
        $ret['records'] = $records;
        $ret['children'] = count($children);

        return $ret;

    }

    //
    // Counts:
    //  term_usage => count the usage for the provided term ids
    //
    /**
     * Retrieves usage counts for specified terms.
     *
     * Supports 'term_usage' mode, which counts how many distinct records use each of the
     * provided term IDs (`trmID` in `$this->data`) in `enum` type fields (`recDetails`)
     * and as `relationtype` in `recLinks`.
     *
     * @return array|false An associative array `[trm_ID => usage_count]` on success.
     *                     If no usage, term IDs might be keys with value 0 or based on input.
     *                     Returns false on error or invalid input.
     */
    public function counts(){

        $mysqli = $this->system->getMysqli();
        $res = null;

        if(@$this->data['mode'] == 'term_usage'){

            $trm_ID = @$this->data['trmID'];

            if(isset($trm_ID)){

                if(is_array($trm_ID)){
                    $trm_ID = implode(',', $trm_ID);
                }
                $trm_ID = $mysqli->real_escape_string($trm_ID);

                $query = 'SELECT trm_ID, COUNT(DISTINCT dtl_RecID) '
                . 'FROM recDetails '
                . 'INNER JOIN defTerms ON trm_ID = dtl_Value '
                . 'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID '
                . 'WHERE dtl_Value IN ('. $trm_ID .') AND dty_Type="enum" '
                . 'GROUP BY trm_ID';
                $trm_usage = mysql__select_assoc2($mysqli, $query);// [ trm_ID1 => count1, ... ]
                if($trm_usage){
                    $res = $trm_usage;
                }elseif(empty($mysqli->error)){
                    $res = explode(',', $trm_ID);
                }else{
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve term usages', $mysqli->error);
                    return false;
                }

                // Retrieve terms used in relmarkers
                $query = 'SELECT rl_RelationTypeID, count(rl_ID) FROM recLinks WHERE rl_RelationTypeID IN (' . $trm_ID . ') GROUP BY rl_RelationTypeID';
                $reltype_usage = mysql__select_assoc2($mysqli, $query);// [ trm_ID1 => count1, ... ]
                if($reltype_usage){
                    // Add results to $res
                    foreach ($reltype_usage as $trmid => $count) {
                        if(array_key_exists($trmid, $res)){
                            $res[$trmid] = intval($res['trmid']) + $count;
                        }else{
                            $res[$trmid] = $count;
                        }
                    }
                }elseif(!empty($mysqli->error)){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve term used for relationship marker type', $mysqli->error);
                    return false;
                }
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCK, 'Invalid term id(s) provided ' . $trm_ID);
                $res = false;
            }
        }

        return $res;
    }
}
