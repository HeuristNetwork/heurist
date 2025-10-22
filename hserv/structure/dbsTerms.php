<?php
/**
* dbsTerms.php - Class DbsTerms
* 
* Provides an in-memory interface for accessing and manipulating terms
*
* @project     Heurist academic knowledge management system
* @package Structure
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/*
* Public methods
*  findTermByConceptCode
*  getTermsFromFormat
*  getTermLabel
*  getTermField
*  getTermReferenceURL
*  getTermCode
*  getTerm
*  getTermByLabel
*  getVocabs - for specified domain
*  getSiblings
*  treeData($parent_id, $mode) - returns tree of flat array of children ids (all levels)
*  addNewTerm
*  addNewTermRef
*  addChild - private
*  getTopMostTermParent
*  doDisambiguateTerms
*  getSameLevelLabelsAndCodes
*/
use hserv\utilities\USanitize;

/**
 * Class DbsTerms
 *
 * Provides an in-memory interface for accessing and manipulating Heurist term taxonomies (vocabularies).
 * This class operates on a pre-loaded data structure (typically fetched by a global function like `dbs_GetTerms()`)
 * which contains all terms, their properties, and their hierarchical relationships.
 *
 * The primary purpose of this class is to offer efficient methods for:
 * - Retrieving term details (label, code, concept ID, custom fields).
 * - Traversing term hierarchies (finding children, parents, siblings).
 * - Validating terms against specific vocabularies or domains ('enum', 'relation').
 * - Converting between term IDs and concept codes.
 * - Dynamically adding new terms to the in-memory cache (useful during import processes).
 * - Disambiguating term labels/codes within the same vocabulary level.
 *
 * An instance of this class is often used by other parts of Heurist (like ReportRecord or import processes)
 * to work with term data without repeated database queries.
 *
 */
class DbsTerms
{
    /** @var \hserv\System The Heurist system object, providing context. */
    protected $system;

    /**
     * @var array The comprehensive in-memory representation of term data.
     *            This array is expected to be structured as returned by `dbs_GetTerms()`, including:
     *            - 'fieldNamesToIndex': Mapping of term field names (e.g., 'trm_Label') to array indices.
     *            - 'termsByDomainLookup': Terms indexed by domain ('enum', 'relation') and then by term_id.
     *            - 'treesByDomain': Hierarchical tree structure of terms, by domain.
     *            - 'trm_Links': Adjacency list for term hierarchy (parent_id => [child_ids...]).
     */
    protected $data;

    /**
     * DbsTerms constructor.
     *
     * Initializes the DbsTerms object with the Heurist system context and the pre-loaded term data.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $data The pre-loaded term data structure (typically from a global `dbs_GetTerms()` call).
     */
    public function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }

    /**
     * Assigns or replaces the internal term data structure.
     *
     * Allows updating the DbsTerms instance with a new set of term data after construction.
     *
     * @param array $data The new term data structure.
     */
    public function setTerms($data){
        $this->data = $data;
    }

    /**
     * Finds a local term ID by its global Concept Code, optionally filtering by domain.
     *
     * If no domain is specified, it searches in 'enum' first, then 'relation'.
     *
     * @param string $ccode The Concept Code to search for (e.g., "DBID-OrigID").
     * @param string|null $domain (Optional) The domain to search within ('enum' or 'relation').
     * @return int|null The local term ID if found, otherwise null.
     */
    public function findTermByConceptCode($ccode, $domain=null){

        if($domain==null){ //search both domains
            $term_id = $this->findTermByConceptCode($ccode, 'enum');
            if($term_id==null){
                $term_id = $this->findTermByConceptCode($ccode, 'relation');
            }
            return $term_id;
        }

        $idx_ccode = intval($this->data['fieldNamesToIndex']["trm_ConceptID"]);

        foreach ($this->data['termsByDomainLookup'][$domain] as $term_id => $def) {
            if(is_numeric($term_id) && $def[$idx_ccode]==$ccode){
                return $term_id;
            }
        }
        return null;
    }

    //
    /**
     * Parses a string containing term IDs and validates them against a specified domain.
     *
     * The input string can be a comma-separated list of term IDs (optionally enclosed in brackets/quotes)
     * or a JSON-like string format (e.g., `"{trmID:trmID}"` though the parsing logic seems to simplify this).
     * Each extracted ID is checked for existence within the specified `$domain` using the cached term data.
     *
     * @param string $formattedStringOfTermIDs The string containing term IDs.
     * @param string|null $domain The domain ('enum', 'relation') against which to validate the term IDs.
     *                            If null, term IDs are extracted but not validated against a specific domain list.
     * @return array An array of valid and unique term IDs found in the input string that belong to the specified domain.
     */
    public function getTermsFromFormat($formattedStringOfTermIDs, $domain) {


        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }

        // Validate termIDs
        if($domain!=null){
            $trmLookup2 = $this->data['termsByDomainLookup'][$domain];

            foreach ($termIDs as $trmID) {
                // check that the term valid
                if ( $trmID && array_key_exists($trmID, $trmLookup2) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                    array_push($validTermIDs,$trmID);
                }
            }
        }else{
            $validTermIDs = $termIDs; //no validation - take all
        }
        return $validTermIDs;
    }

    //
    //
    //
    /**
     * Retrieves the label for a given term ID.
     *
     * If `$with_hierarchy` is true, it reconstructs the full hierarchical label by traversing
     * up to the vocabulary root (e.g., "Top.Mid.Leaf Label"). It includes logic to remove
     * redundant prefixes if parent labels are part of child labels (e.g. "Parent.Parent.Child" becomes "Parent.Child").
     *
     * @param int $term_id The ID of the term.
     * @param bool $with_hierarchy (Optional) If true, returns the full hierarchical path label. Default false.
     * @return string The term label, or an empty string if the term is not found.
     */
    public function getTermLabel($term_id, $with_hierarchy=false) {

        $term = $this->getTerm($term_id);
        if(!$term){
            return '';
        }

        $idx_term_label = $this->data['fieldNamesToIndex']['trm_Label'];

        if(!$with_hierarchy){
            return @$term[$idx_term_label]?$term[$idx_term_label]:'';
        }

        $labels = '';
        $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
        $idx_term_domain = $this->data['fieldNamesToIndex']['trm_Domain'];


        $labels = explode('.', $term[$idx_term_label]);

        while($term[$idx_term_parent] > 0){
            $term = $this->getTerm($term[$idx_term_parent]);

            if($term[$idx_term_parent] > 0){
                $trmLabels = explode('.', $term[$idx_term_label]);
                $labels = array_merge($trmLabels, $labels);
            }else{
                break; //ignore vocabulary
            }
        }

        // Remove duplicated hierarchy labels
        $i = 1;
        while($i < count($labels)){

            $prefix = implode('.', array_slice($labels, 0, $i)) . '.';
            $test = implode('.', array_slice($labels, $i));

            if(strpos($test, $prefix) === 0){
                $labels = array_slice($labels, $i);
                $i = 1;
            }else{
                $i ++;
            }
        }

        return implode('.',$labels);
    }

    //
    //
    //
    /**
     * Retrieves the value of a specific field from a term's definition.
     *
     * This is a generic getter for any property stored in the term's definition array.
     *
     * @param int $term_id The ID of the term.
     * @param string $field_name The name of the field to retrieve (e.g., 'trm_Code', 'trm_ParentTermID').
     *                           Must match a key in the `fieldNamesToIndex` map of the term data.
     * @return mixed|string The value of the specified field, or an empty string if the term or field is not found.
     */
    public function getTermField($term_id, $field_name) {
        $term = $this->getTerm($term_id);
        if($term){
            $idx_term_code = $this->data['fieldNamesToIndex'][$field_name];
            return @$term[$idx_term_code]?$term[$idx_term_code]:'';
        }else{
            return '';
        }
    }


    //
    //
    //
    /**
     * Retrieves the 'Code' (Standardabkürzung) of a term.
     * Convenience wrapper for `getTermField($term_id, 'trm_Code')`.
     *
     * @param int $term_id The ID of the term.
     * @return string The term's code, or an empty string if not found.
     */
    public function getTermCode($term_id) {
        return $this->getTermField($term_id, 'trm_Code');
    }

    //
    //
    //
    /**
     * Retrieves the Concept ID of a term.
     * Convenience wrapper for `getTermField($term_id, 'trm_ConceptID')`.
     *
     * @param int $term_id The ID of the term.
     * @return string The term's Concept ID, or an empty string if not found.
     */
    public function getTermConceptID($term_id) {
        return $this->getTermField($term_id, 'trm_ConceptID');
    }

    //
    //
    //
    /**
     * Retrieves the Semantic Reference URL of a term.
     * Convenience wrapper for `getTermField($term_id, 'trm_SemanticReferenceURL')`.
     *
     * @param int $term_id The ID of the term.
     * @return string The term's Semantic Reference URL, or an empty string if not found.
     */
    public function getTermReferenceURL($term_id) {
        return $this->getTermField($term_id, 'trm_SemanticReferenceURL');
    }

    /**
     * Retrieves the raw definition array for a specific term ID.
     *
     * It first looks in the specified `$domain` (defaulting to 'enum'). If not found,
     * it will also check the other domain ('relation' if 'enum' was primary, or vice-versa).
     *
     * @param int $term_id The ID of the term to retrieve.
     * @param string $domain (Optional) The primary domain to search in ('enum' or 'relation'). Default 'enum'.
     * @return array|null The term's definition array as stored in the cached data, or null if not found in either domain.
     */
    public function getTerm($term_id, $domain='enum') {
        $term = null;

        if(@$this->data['termsByDomainLookup'][$domain][$term_id]!=null){
            $term = $this->data['termsByDomainLookup'][$domain][$term_id];
        }else{
            //search in other domain too
            $term = @$this->data['termsByDomainLookup'][$domain=='enum'?'relation':'enum'][$term_id];
        }
        return $term;
    }

    //
    //
    //
    /**
     * Finds a term ID within a specific vocabulary by its exact label.
     *
     * The search is case-insensitive and accent-insensitive. It first retrieves all child term IDs
     * under the given `$vocab_id` (parent term ID) using `treeData()`, then iterates
     * through them comparing their labels.
     *
     * @param int $vocab_id The ID of the parent term (vocabulary root) to search within.
     * @param string $label The term label to search for.
     * @return int|null The ID of the found term, or null if no term matches the label within the vocabulary.
     */
    public function getTermByLabel($vocab_id, $label){

        $all_terms = $this->treeData($vocab_id, 3);

        $label = trim(mb_strtolower($label));

        foreach($all_terms as $trm_id){

            $label2 = mb_strtolower($this->getTermLabel($trm_id));

            if($label2==$label){
                return $trm_id;
            }
        }
        return null;
    }

    //
    /**
     * Retrieves the top-level term IDs (vocabulary root IDs) for a specified domain.
     *
     * @param string $domain The domain ('enum' or 'relation') for which to get vocabularies.
     * @return array An array of term IDs representing the roots of vocabularies in that domain.
     */
    public function getVocabs($domain){
        return array_keys(@$this->data['treesByDomain'][$domain]);
    }

    //
    /**
     * Retrieves sibling terms for a given term ID.
     *
     * NOTE: This method is marked as "NOT USED" in the original source comments.
     * It finds the parent of the given term and then returns all children of that parent
     * (which includes the original term itself).
     *
     * @param int $term_id The ID of the term whose siblings are to be found.
     * @param string $domain The domain of the term ('enum' or 'relation').
     * @return array An array of term IDs, including the original term and its siblings.
     */
    public function getSiblings($term_id, $domain) {

        $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
        $term = $this->getTerm($term_id, $domain);

        return $this->treeData($term[$idx_term_parent], 3);
    }


    // $parent_id -  parent term
    // mode - 1, tree - returns treedata for fancytree
    //        3, set  - array of ids
    //        4, labels - flat array of labels in lower case
    //
    /**
     * Retrieves term data in a tree structure or as a flat list, starting from a parent term or a domain.
     *
     * This method recursively traverses the term hierarchy stored in `$this->data['trm_Links']`.
     * - If `$parent_id` is a domain ('enum' or 'relation'), it iterates through all vocabularies in that domain.
     * - Based on `$mode`:
     *   - 1 or 'tree': Returns a nested array representing the hierarchy, where keys are term IDs
     *                  and values are arrays of their children (e.g., `[parent_id => [child_id1 => [], child_id2 => []]]`).
     *   - 3 or 'set': Returns a flat array containing all unique child term IDs (recursive).
     *   - 4 or 'labels': Returns a flat array of all unique lowercase child term labels (recursive).
     *
     * Includes error logging for recursive tree structures or duplicate term IDs within a 'set' mode.
     *
     * @param int|string $parent_id The ID of the parent term to start from, or a domain string ('enum', 'relation')
     *                              to process all vocabularies in that domain.
     * @param int|string $mode The desired output format:
     *                         1 or 'tree': Hierarchical tree array.
     *                         3 or 'set': Flat array of term IDs.
     *                         4 or 'labels': Flat array of lowercase term labels.
     * @return array The requested term data structure.
     */
    public function treeData($parent_id, $mode):array{

        if($mode=='set'){
            $mode = 3;
        }elseif($mode=='tree'){
            $mode = 1;
        }elseif($mode=='labels'){
            $mode = 4;
        }


        if($mode==1){
            $res = array($parent_id=>array());
        }else{
            $res = array();
        }

        if($parent_id=='relation' || $parent_id=='enum'){
            //find all vocabulary with domain "relation"
            $vocab_ids = $this->getVocabs($parent_id);
            foreach($vocab_ids as $trm_ID){
                $res2 = $this->treeData($trm_ID, $mode);
                $res = array_merge($res,$res2);
            }
        }else{

            $children = @$this->data['trm_Links'][$parent_id];
            if(!isEmptyArray($children)){

                foreach($children as $trm_ID){

                    if($trm_ID==$parent_id){
                        USanitize::errorLog('!!!!Database '.$this->system->dbname()
                            .' Recursive tree for term '.$trm_ID.' parent '.$parent_id);
                        continue;
                    }

                    if($mode==1){ //tree
                        $res[$parent_id][$trm_ID] = array();

                    }elseif($mode==3){
                        if(in_array($trm_ID, $res)){ //already in set
                            USanitize::errorLog('!!!!Database '.$this->system->dbname()
                                .' Recursive tree or duplication for term '.$trm_ID.' parent '.$parent_id);
                            continue;
                        }else{
                            array_push($res, $trm_ID);
                        }
                    }else{
                        array_push($res, strtolower($this->getTermLabel($trm_ID)));
                    }

                    $res2 = $this->treeData($trm_ID, $mode);
                    if(!isEmptyArray($res2)){
                        if($mode==1){
                            //tree
                            $res[$trm_ID] = $res2;
                        }else{
                            //flat array
                            $res = array_merge($res,$res2);
                        }
                    }
                }
            }
        }
        return $res;
    }

    //
    /**
     * Retrieves all unique term labels and codes for terms that are direct children of a given parent term ID within a specific domain.
     *
     * Uses `treeData()` to get the direct children, then extracts their labels and codes.
     *
     * @param int $parent_id The ID of the parent term.
     * @param string $domain The domain ('enum' or 'relation') to consider.
     * @return array An associative array `['code' => [code1, code2,...], 'label' => [label1, label2,...]]`.
     */
    public function getSameLevelLabelsAndCodes($parent_id, $domain){

        $lvl_src = array('code'=>array(),'label'=>array());

        if($parent_id>0){
            $children = $this->treeData($parent_id, 3);//ids
            if(!empty($children)){
                $idx_code = intval($this->data['fieldNamesToIndex']["trm_Code"]);
                $idx_label = intval($this->data['fieldNamesToIndex']["trm_Label"]);

                foreach($children as $trmId){
                    if(@$this->data['termsByDomainLookup'][$domain][$trmId]){
                        $code = (trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx_code]));//removeLastNum
                        $label = (trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx_label]));//removeLastNum
                        $lvl_src['code'][] = $code;
                        $lvl_src['label'][] = $label;
                    }
                }
            }
        }

        return $lvl_src;
    }

    //
    //
    //
    /**
     * Checks if a term is a direct child of a specified parent term in the cached hierarchy.
     *
     * @param int $parent_id The ID of the potential parent term.
     * @param int $term_id The ID of the term to check.
     * @return bool True if `$term_id` is a direct child of `$parent_id`, false otherwise.
     */
    public function isTermLinked($parent_id, $term_id){

        if(@$this->data['trm_Links'][$parent_id]){
            return in_array($term_id, $this->data['trm_Links'][$parent_id] );
        }
        return false;
    }

    //
    //
    //
    /**
     * Adds a new child term reference to a parent term in the cached `$this->data['trm_Links']` structure.
     *
     * If the parent already has children, the new term ID is appended. Otherwise, a new list of children is started.
     * This method directly modifies the in-memory cache.
     *
     * @param int $parent_id The ID of the parent term.
     * @param int $new_term_id The ID of the new child term to add.
     */
    public function addNewTermRef($parent_id, $new_term_id){

        if(@$this->data['trm_Links'][$parent_id]){

            if( !in_array($new_term_id, $this->data['trm_Links'][$parent_id] )){
                $this->data['trm_Links'][$parent_id][] = $new_term_id;
            }
        }else{
            $this->data['trm_Links'][$parent_id] = array($new_term_id);
        }
    }

    //
    //
    //
    /**
     * Adds a new term's definition and hierarchical links to the in-memory cache.
     *
     * This method updates:
     * - `$this->data['termsByDomainLookup']`: Adds the full term definition array.
     * - `$this->data['treesByDomain']`: Adds the new term to the appropriate place in the domain's tree structure using `addChild()`.
     * - `$this->data['trm_Links']`: Adds the parent-child link using `addNewTermRef()`.
     *
     * This is used to dynamically update the cache when new terms are created, e.g., during an import.
     *
     * @param int $new_term_id The ID of the new term being added.
     * @param array $term_to_add The full definition array for the new term (as it would appear in `termsByDomainLookup`).
     */
    public function addNewTerm($new_term_id, $term_to_add){

        $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
        $idx_term_domain = $this->data['fieldNamesToIndex']['trm_Domain'];

        $domain = $term_to_add[$idx_term_domain];
        $parent_id = $term_to_add[$idx_term_parent];

        $this->data['termsByDomainLookup'][$domain][$new_term_id] = $term_to_add;
        $this->addChild($this->data['treesByDomain'][$domain], $parent_id, $new_term_id);
        $this->addNewTermRef($parent_id, $new_term_id);

    }

    private function addChild(&$lvl, $parent_id, $new_term_id) {

        if($parent_id>0){

            foreach($lvl as $trmId=>$children){
                if($trmId==$parent_id){

                    if(!is_array(@$lvl[$trmId])) {$lvl[$trmId] = array();}
                    $lvl[$trmId][$new_term_id] = array();

                    break;

                }elseif(!isEmptyArray($children)){
                    $this->addChild($lvl[$trmId], $parent_id, $new_term_id);
                }
            }

        }else{
            //vocabulary
            if(!is_array($lvl)) {$lvl = array();}
            $lvl[$new_term_id] = array();
        }
    }


    //
    /**
     * Finds the top-most parent (vocabulary root ID) for a given term ID within a specified domain or tree structure.
     *
     * This method recursively traverses up the term hierarchy from the given `$term_id`
     * until it reaches a term with no parent (or the root of the provided `$domain` tree structure).
     *
     * @param int $term_id The ID of the term whose top-most parent is to be found.
     * @param string|array $domain The domain ('enum', 'relation') or the actual term tree array to search within.
     * @param int|null $topmost (Internal use for recursion) The current candidate for the top-most parent.
     * @return int|null The ID of the top-most parent term (vocabulary ID), or null if not found.
     */
    public function getTopMostTermParent($term_id, $domain, $topmost=null) {

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $this->data['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$children){

            if($sub_term_id == $term_id){
                return $topmost?$topmost:$term_id;
            }elseif( !isEmptyArray($children) ) {

                $res = $this->getTopMostTermParent($term_id, $children, $topmost?$topmost:$sub_term_id );
                if($res) {return $res;}
            }
        }
        return null; //not found
    }



    // Disambiguate elements (including terms at the same level of a vocabulary) which have the same label but
    // different concept IDs, by adding 1, 2, 3 etc. to the end of the label.
    //
    /**
     * Disambiguates a term label or code by appending a numeric suffix if it already exists
     * among its siblings within the same domain.
     *
     * This method first gathers all existing labels/codes (depending on `$idx` which implies
     * if it's a label or code index) of sibling terms within both 'enum' and 'relation' domains
     * (this seems broad, typically disambiguation is within a single vocabulary).
     * It then calls `doDisambiguateTerms2` to perform the actual disambiguation.
     *
     * @param string $term_import The term label or code to disambiguate.
     * @param int $idx The array index corresponding to the field (label or code) in the term definition array.
     * @return string The original or disambiguated term label/code.
     */
    public function doDisambiguateTerms($term_import, $idx){

        if(!$term_import || $term_import=="") {return $term_import;}

        $lvl_values = array();

        $domain = 'enum';
        $lvl_src = $this->data['treesByDomain'][$domain];

        if(is_array($lvl_src)){
            foreach($lvl_src as $trmId=>$children){
                $lvl_values[] = trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx]);
            }
        }

        $domain = 'relation';
        $lvl_src = $this->data['treesByDomain'][$domain];

        if(is_array($lvl_src)){
            foreach($lvl_src as $trmId=>$children){
                $lvl_values[] = trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx]);
            }
        }

        return $this->doDisambiguateTerms2($term_import, $lvl_values);
    }

    /**
     * Disambiguates a term value (label or code) by appending a numeric suffix if it's found in a list of existing values.
     *
     * It checks if `$term_value` exists in `$same_level_values`. If it does, it appends " 1", then " 2", etc.,
     * to the base name (original `$term_value` with any previous numeric suffix removed by `removeLastNum`)
     * until a unique value is found.
     *
     * @param string $term_value The term label or code to make unique.
     * @param array $same_level_values An array of existing sibling labels or codes to check against.
     * @return string The original or disambiguated (e.g., "My Term 2") term value.
     */
    public function doDisambiguateTerms2($term_value, $same_level_values){

        if(!$term_value || $term_value=="") {return $term_value;}
/*
        $name = removeLastNum(trim($term_value));
        $found = 0;

        if(!empty($same_level_values))
        foreach ($same_level_values as $value){
                $name1 = removeLastNum(trim($value));
                if(strcasecmp($name, $name1)==0){
                    $found++;
                }
        }
        if($found>0){
                $term_value = $name." ".($found+1);
        }
*/
        $name = removeLastNum(trim($term_value));
        $found = 1;

        while (in_array($term_value, $same_level_values)){
            $term_value = $name.' '.$found;
            $found++;
        }


        return $term_value;
    }

}
?>
