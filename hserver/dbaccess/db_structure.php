<?php

    /**
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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

    /**
    * @todo convert to class (singleton?)
    *
    *
    * Library of function that provides database structure information: rectypes, fieldtypes and terms defined in database
    *
    * dbs_ - prefix for functions
    *
    *
    * dbs_GetRectypeStructures
    * dbs_GetRectypeStructure
    * dbs_GetRectypeGroups
    * dbs_GetRectypeByID
    * dbs_GetTerms
    * dbs_GetDetailTypes
    * dbs_GetDtLookups
    * 
    * TERMS RELATED FUNCTION - to be public methods, 
    * they work with global $terms array - need to be defined by dbs_GetTerms before call these methods
    * getTermChildren
    * getTermInTree    
    * getTermByLabel    
    * getTermById
    *
    * INTERNAL FUNCTIONS
    * __getRectypeColNames
    * __getColumnNameToIndex
    * __getRectypeStructureFieldColNames
    * __getTermTree
    * __attachChild
    * __getTermColNames
    *
    */


    /**
    * @return    object iformation describing all the rectypes defined in the database
    * an array of RecType Structures for all RecTypes
    * rectypes = {{"groups":{"groupIDToIndex":{recTypeGroupID:index},
    *                         recTypeGroupID:{propName:val,...},...}},
    *             "names":{rtyID:name,...},
    *             "pluralNames":{rtyID:pluralName,...},
    *             "usageCount":{rtyID:nonzero-count,...},
    *             "dtDisplayOrder":{rtyID:[ordered dtyIDs], ...},
    *             "typedefs":{"commonFieldNames":[defRecType Column names],
    *                         "commonNamesToIndex":{"defRecTypes columnName":index,...},
    *                         "dtFieldNamesToIndex":{"defRecStructure columnName":index,...},
    *                         "dtFieldNames":[defRecStructure Column names],
    *                         rtyID:{"dtFields":{dtyID:[val,...]},
    *                                 "commonFields":[val,....]},
    *                         ...},
    *             "constraints":[]}};
    *
    * @uses      dbs_GetRectypeColNames()
    * @uses      dbs_GetColumnNameToIndex()
    * @uses      dbs_GetRectypeStructureFieldColNames()
    * @uses      dbs_GetRectypeGroups()
    * @uses      dbs_GetRecTypeUsageCount()
    *
    *
    * @param mixed $rectypeids null means all, otherwise comma separated list of ids
    * @param mixed $imode  0 - only names and groupnames  (default)
    *                      1 - only structure (@TODO NO NAMES!!!!)
    *                      2 - full, both headers and structures
    */
    function dbs_GetRectypeStructures($system, $rectypeids=null, $imode) { //$useCachedData = false) {


        if($imode<0 || $imode>2){
            $imode = 0;
        }

        $mysqli = $system->get_mysqli();
        $dbID = $system->get_system('sys_dbRegisteredID');
        
        /*ARTEM $cacheKey = DATABASE . ":AllRecTypeInfo";
        if ($useCachedData) {
        $rtStructs = getCachedData($cacheKey);
        if ($rtStructs) {
        return $rtStructs;
        }
        }*/
        // NOTE: these are ordered to match the order of dbs_GetRectypeStructureFieldColNames from DisplayName on
        $colNames = array("rst_RecTypeID", "rst_DetailTypeID",
            //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
            "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
            //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
            "if(rst_DisplayHelpText is not null and (dty_Type='separator' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
            //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
            "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
            "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType",
            "rst_NonOwnerVisibility", "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
            //here we check for an override in the recTypeStrucutre for displayGroup
            "if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID) as rst_DisplayDetailTypeGroupID",
            //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
            "if(rst_FilteredJsonTermIDTree is not null and CHAR_LENGTH(rst_FilteredJsonTermIDTree)>0,rst_FilteredJsonTermIDTree,dty_JsonTermIDTree) as rst_FilteredJsonTermIDTree",
            //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
            "if(rst_PtrFilteredIDs is not null and CHAR_LENGTH(rst_PtrFilteredIDs)>0,rst_PtrFilteredIDs,dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs",
            "rst_CreateChildIfRecPtr",
            "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified",
            "dty_TermIDTreeNonSelectableIDs",
            "dty_FieldSetRectypeID",
            "dty_Type");

        $query = "select " . join(",", $colNames) .
        " from defRecStructure".
        " left join defDetailTypes on rst_DetailTypeID = dty_ID".
        " left join defDetailTypeGroups on dtg_ID = if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID)";
        if($rectypeids){
            $querywhere = " where rst_RecTypeID in (".(is_array($rectypeids)?implode(",", $rectypeids) :$rectypeids).")";
        } else {
            $querywhere = "";
        }

        $rtStructs = array();
        $rtStructs['names'] = array();
        $rtStructs['pluralNames'] = array();

        if($imode!=1){
            $rtStructs['groups'] = dbs_GetRectypeGroups($mysqli);
        }

        if($imode==2){
            //@todo  'usageCount' => getRecTypeUsageCount(),
            //@todo  'constraints' => getAllRectypeConstraint(),
        }
        if($imode>0){   //structure description

            $rtStructs['dtDisplayOrder'] = array();
            $rtStructs['typedefs'] = array('commonFieldNames' => __getRectypeColNames(),
                'commonNamesToIndex' => __getColumnNameToIndex(__getRectypeColNames()),
                'dtFieldNamesToIndex' => __getColumnNameToIndex(__getRectypeStructureFieldColNames()),
                'dtFieldNames' => __getRectypeStructureFieldColNames());

            $query .=  $querywhere." order by rst_RecTypeID, rst_DisplayOrder, rst_ID";
            $res = $mysqli->query($query);

            if($res){
                while ($row = $res->fetch_row()) {
                    if (!array_key_exists($row[0], $rtStructs['typedefs'])) {
                        $rtStructs['typedefs'][$row[0]] = array('dtFields' => array($row[1] => array_slice($row, 2)));
                        $rtStructs['dtDisplayOrder'][$row[0]] = array();
                    } else {
                        $rtStructs['typedefs'][$row[0]]['dtFields'][$row[1]] = array_slice($row, 2);
                    }
                    array_push($rtStructs['dtDisplayOrder'][$row[0]], $row[1]);
                }
                $res->close();
            }else{
                error_log('DATABASE: '.HEURIST_DBNAME.'. Error retrieving rectype structure '.$mysqli->error);
            }

        }

        // get rectypes ordered by the RecType Group order, then by Group Name, then by rectype order in group and then by rectype name
        $query = "select rty_ID, rtg_ID, rtg_Name, " . join(",", __getRectypeColNames());
        $query = preg_replace("/rty_ConceptID/", "", $query);
        if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
            $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID";
        } else {
            $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), '') as rty_ConceptID";
        }

        if($rectypeids){
            $querywhere = " where rty_ID in (".(is_array($rectypeids)?join(",", $rectypeids) :$rectypeids).")";
        } else {
            $querywhere = "";
        }

        $query = $query." from defRecTypes left join defRecTypeGroups  on rtg_ID = rty_RecTypeGroupID" .
        $querywhere.
        " order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name";


        $res = $mysqli->query($query);
        if($res){
            while ($row = $res->fetch_row()) {
                if($imode!=1){
                    $rtg_ID = $row[1];
                    if(!@$rtStructs['groups']['groupIDToIndex'][$rtg_ID]){
                        if($rtg_ID>0){
                            //error_log('Database '.HEURIST_DBNAME.' Definitions error: wrong group id '.$rtg_ID.' for record type '.$row[0]);
                        }
                        
                        $idxs = array_keys($rtStructs['groups']['groupIDToIndex']);
                        $rtg_ID = $idxs[0];
                    }
                    
                    array_push($rtStructs['groups'][$rtStructs['groups']['groupIDToIndex'][$rtg_ID]]['allTypes'], $row[0]);
                    if ($row[14]) { //rty_ShowInList
                        array_push($rtStructs['groups'][$rtStructs['groups']['groupIDToIndex'][$rtg_ID]]['showTypes'], $row[0]);
                    }
                }
                if($imode>0){
                    $commonFields = array_slice($row, 3);
                    $rtStructs['typedefs'][$row[0]]['commonFields'] = $commonFields;
                }
                $rtStructs['names'][$row[0]] = $row[3];
                $rtStructs['pluralNames'][$row[0]] = $row[8];
            }
            $res->close();
        }else{
            error_log('DATABASE: '.HEURIST_DBNAME.'. Error retrieving rectype structure '.$mysqli->error);
        }


        //ARTEM setCachedData($cacheKey, $rtStructs);
        return $rtStructs;
    }

    /**
    * Return record structure for given id and update $recstructures array
    *
    * @param mixed $system
    * @param mixed $recstructures
    * @param mixed $rectype
    */
    function dbs_GetRectypeStructure($system, &$recstructures, $rectype)
    {
        if (!@$recstructures[$rectype] ) {
            //load rectype
            $res = dbs_GetRectypeStructures($system, $rectype, 1);
            if(!@$recstructures['commonNamesToIndex']){
                $recstructures['commonNamesToIndex'] = $res['typedefs']['commonNamesToIndex'];
                $recstructures['dtFieldNamesToIndex'] = $res['typedefs']['dtFieldNamesToIndex'];
            }
            $recstructures[$rectype] = @$res['typedefs'][$rectype];
        }
        return @$recstructures[$rectype];
    }

    /**
    * get recType Group definitions
    * groups = {"groupIDToIndex":{recTypeGroupID:index},
    *           index:{propName:val,...},...}
    * @return    array recTypeGroup definitions as array of prop:val pairs
    */
    function dbs_GetRectypeGroups($mysqli) {
        $rtGroups = array('groupIDToIndex' => array());
        $index = 0;
        $res = $mysqli->query("select * from defRecTypeGroups order by rtg_Order, rtg_Name");
        while ($row = $res->fetch_assoc()) {
            array_push($rtGroups, array('id' => $row["rtg_ID"], 'name' => $row["rtg_Name"], 'order' => $row["rtg_Order"], 'description' => $row["rtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
            $rtGroups['groupIDToIndex'][$row["rtg_ID"]] = $index++;
        }
        $res->close();
        return $rtGroups;
    }


    /**
    * return rectype by id
    * mostly used to verify rectype existence
    *
    * @param mixed $mysqli
    * @param mixed $rty_ID
    */
    function dbs_GetRectypeByID($mysqli, $rty_ID) {

        $rectype = null;
        $query = 'select * from defRecTypes where rty_ID ='.$rty_ID;
        $res = $mysqli->query($query);
        if($res){
            $rectype =$res->fetch_assoc();
        }
        $res->close();
        return $rectype;
    }


    /**
    * Get term structure with trees from relation and enum domains
    *
    * @param     boolean $useCachedData whether to use cached data (default = false)
    * @return    object terms structure that contains domainLookups and domain term trees
    * @uses      __getTermColNames()
    * @uses      __getTermTree()
    * @uses      getCachedData()
    * @uses      setCachedData()
    */
    function dbs_GetTerms($system){ //$useCachedData = false) {

        $mysqli = $system->get_mysqli();
        $dbID = $system->get_system('sys_dbRegisteredID');

        /* ARTEM
        $cacheKey = DATABASE . ":dbs_GetTerms";
        if ($useCachedData) {
        $terms = getCachedData($cacheKey);
        if ($terms) {
        return $terms;
        }
        }*/
        $query = "select " . join(",", __getTermColNames());
        $query = preg_replace("/trm_ConceptID/", "", $query);
        if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
            $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), concat('$dbID-',cast(trm_ID as char(5)))) as trm_ConceptID";
        } else {
            $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), '') as trm_ConceptID";
        }
        $query.= " from defTerms order by trm_Domain, trm_Label";
        $res = $mysqli->query($query);
        $terms = array('termsByDomainLookup' =>  array('relation' => array(),
            'enum' => array()),
            'commonFieldNames' => array_slice(__getTermColNames(), 1),
            'fieldNamesToIndex' => __getColumnNameToIndex(array_slice(__getTermColNames(), 1)));
            
        $terms['fieldNamesToIndex']['trm_HasImage'] = count($terms['commonFieldNames']);
        array_push($terms['commonFieldNames'],'trm_HasImage');        
            
        if($res){
            $lib_dir = HEURIST_FILESTORE_DIR . 'term-images/';
            
            while ($row = $res->fetch_row()) {
                $terms['termsByDomainLookup'][$row[9]][$row[0]] = array_slice($row, 1);
                $hasImage = file_exists($lib_dir.$row[0].'.png');
                array_push($terms['termsByDomainLookup'][$row[9]][$row[0]], $hasImage);
            }
            
            $res->close();
        }else{
            error_log('DATABASE: '.HEURIST_DBNAME.'. Error retrieving terms '.$mysqli->error);
        }
        $terms['treesByDomain'] = array(
                'relation' => __getTermTree($mysqli, "relation", "prefix"), 
                'enum' => __getTermTree($mysqli, "enum", "prefix"));
        //ARTEM setCachedData($cacheKey, $terms);
        return $terms;
    }

    // to public method ------>
    
    /**
    * return all term children as plain array
    * 
    * @param mixed $system
    */
    function getTermChildren($parentID, $system, $firstlevel_only){
        
        $mysqli = $system->get_mysqli();
        $children = array();
    
        $query = 'select trm_ID from defTerms where trm_ParentTermID = ' . $parentID;
        $res = $mysqli->query($query);
        if ($res) {
            while ($row = $res->fetch_row()) {
                array_push($children, $row[0]);
                if(!$firstlevel_only){
                    $children = array_merge($children, getTermChildren($row[0], $system, $firstlevel_only));
                }
            }
        }
        
        return $children;
        
    }

    //
    // find tree in term tree
    // return branch with childs
    //
    function getTermInTree($term_id){

        global $terms;
        
        $res = null;
        
        if($term_id>0){

            $domain = 'enum';
            $term = @$terms['termsByDomainLookup']['enum'][$term_id];
            if(null==$term){
                $domain = 'relation';
                $term = @$terms['termsByDomainLookup']['relation'][$term_id];
            }
            if(null!=$term){
                  $res = getTermInTree2($terms['treesByDomain'][$domain], $term_id);
            }
        }
        return $res;
    }    
    
    function getTermInTree2($tree, $term_id){
        
        if(@$tree[$term_id]){
            return $tree[$term_id];
        }else{
        
            foreach($tree as $pID => $children){
                if(count($children)>0){
                    $res = getTermInTree2($children, $term_id);
                    if($res!=null){
                        return $res;
                    }
                }
            }
        }
        return null;
    }

    //
    // to public method
    //
    function getTermByLabel($term_label, $domain)
    {
        global $terms;

        $idx = $terms['fieldNamesToIndex']['trm_Label'];

        $list = $terms['termsByDomainLookup'][$domain];
        foreach($list as $term_id => $term){
            if(strcasecmp($term_label, $term[$idx])==0){
                return $term_id;
            }
        }

        return null;
    }

    function getTermById($term_id, $field='trm_Label'){

        global $terms;

        if($term_id>0){

            $term = @$terms['termsByDomainLookup']['enum'][$term_id];
            if(null==$term){
                $term = @$terms['termsByDomainLookup']['relation'][$term_id];
            }

            if($term){
                
                $idx = @$terms['fieldNamesToIndex'][$field];
                    
                if($idx>=0){
                    return $term[$idx];
                }else{
                    return $term;
                }
            }
        }

        return null;
    }

    //-----------------------------------------------------------------------------------------------
    // INTERNAL FUNCTIONS


    /**
    * return array of recType table column names
    */
    function __getRectypeColNames() {
        return array("rty_Name", "rty_OrderInGroup", "rty_Description", "rty_TitleMask", "rty_CanonicalTitleMask", "rty_Plural",
            "rty_Status", "rty_OriginatingDBID", "rty_NameInOriginatingDB", "rty_IDInOriginatingDB", "rty_NonOwnerVisibility",
            "rty_ShowInLists", "rty_RecTypeGroupID", "rty_RecTypeModelIDs", "rty_FlagAsFieldset",
            "rty_ReferenceURL", "rty_AlternativeRecEditor", "rty_Type", "rty_ShowURLOnEditForm", "rty_ShowDescriptionOnEditForm",
            "rty_Modified", "rty_LocallyModified", "rty_ConceptID");
    }
    /**
    * get name to index map for columns
    * @param     array $columns array of strings
    * @return    object string => index lookup
    */
    function __getColumnNameToIndex($columns) {
        $columnsNameIndexMap = array();
        $index = 0;
        foreach ($columns as $columnName) {
            $columnsNameIndexMap[$columnName] = $index++;
        }
        return $columnsNameIndexMap;
    }
    function __getRectypeStructureFieldColNames() {
        return array("rst_DisplayName", "rst_DisplayHelpText", "rst_DisplayExtendedDescription", "rst_DisplayOrder", 
            "rst_DisplayWidth", "rst_DisplayHeight",
            "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType", "rst_NonOwnerVisibility",
            "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues", "rst_DisplayDetailTypeGroupID",
            "rst_FilteredJsonTermIDTree", "rst_PtrFilteredIDs", "rst_CreateChildIfRecPtr", "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs",
            "rst_Modified", "rst_LocallyModified", "dty_TermIDTreeNonSelectableIDs", "dty_FieldSetRectypeID", "dty_Type");
    }

    /**
    * attaches a child branch to a parent, recursively calling itself to build the childs subtree
    * @param     int $parentIndex id of parent term to attach child branch to
    * @param     int $childIndex id of child branch in current array
    * @param     mixed $terms the array of branches to build the tree
    * @param     array $parents of current branch to avoid recursion
    * @return    object $terms
    */
    function __attachChild($parentIndex, $childIndex, $terms, $parents) {
        
        /*if (!@count($terms[$childIndex]) || $parentIndex == $childIndex) {//recursion termination
            return $terms;
        }*/
        
        if (array_key_exists($childIndex, $terms)) {//check if this child is parent itself
            if (count($terms[$childIndex])) { //has children
            
                if($parents==null){
                    $parents = array($childIndex);
                }else{
                    array_push($parents, $childIndex);
                }
            
                foreach ($terms[$childIndex] as $gChildID => $n) { //loop for his children
                    if ($gChildID != null) {
                        if(array_search($gChildID, $parents)===false){
                            $terms = __attachChild($childIndex, $gChildID, $terms, $parents);//depth first recursion
                        }else{
                            error_log('Recursion in '.HEURIST_DBNAME.'.defTerms!!! Tree '.implode('>',$parents)
                                    .'. Can\'t add term '.$gChildID);
                        }
                    }
                }
            }
            $terms[$parentIndex][$childIndex] = $terms[$childIndex];
            unset($terms[$childIndex]);
        }
        return $terms;
    }

    /**
    * build a tree of term ids for a given domain name or names (prefix or postfix subdomain naming)
    * @global    type description of global variable usage in a function
    * @staticvar type [$varname] description of static variable usage in function
    * @param     string $termDomain the term domain to build
    * @param     string $matching indicates whether the domain name is complete or portion of the domain
    * @return    mixed
    * @uses      __attachChild()
    */
    function __getTermTree($mysqli, $termDomain, $matching = 'exact') { // termDomain can be empty, 'reltype' or 'enum' or any future term use domain defined in the trm_Domain enum
        $whereClause = "a.trm_Domain " . ($matching == 'prefix' ? " like '" . $termDomain . "%' " : ($matching == 'postfix' ? " like '%" . $termDomain . "' " : "='" . $termDomain . "'"));
        $query = "select a.trm_ID as pID, b.trm_ID as cID
        from defTerms a
        left join defTerms b on a.trm_ID = b.trm_ParentTermID
        where $whereClause
        order by a.trm_Label, b.trm_Label";

        $res = $mysqli->query($query);
        $terms = array();
        // create array of parent => child arrays
        while ($row = $res->fetch_assoc()) {
            if (!@$terms[$row["pID"]]) {
                $terms[$row["pID"]] = array();
            }
            if ($row['cID']) {//insert child under parent
                $terms[$row["pID"]][$row['cID']] = array();
            }
        }//we have all the branches, now lets build a tree
        $res->close();
        
        foreach ($terms as $parentID => $childIDs) {
            foreach ($childIDs as $childID => $n) {
                //check that we have a child branch
                if ($childID != null && array_key_exists($childID, $terms)) {
                    if (count($terms[$childID])) {//yes then attach it and it's children's branches
                        $terms = __attachChild($parentID, $childID, $terms, null);
                    } else {//no then it's a leaf in a branch, remove this redundant node.
                        unset($terms[$childID]);
                    }
                }
            }
        }
        return $terms;
    }

    /**
    * return array of term table column names
    */
    function __getTermColNames() {
        return array("trm_ID", "trm_Label", "trm_InverseTermID", "trm_Description", "trm_Status", "trm_OriginatingDBID",
            //                    "trm_NameInOriginatingDB",
            "trm_IDInOriginatingDB", "trm_AddedByImport", "trm_IsLocalExtension", "trm_Domain", "trm_OntID", "trm_ChildCount", 
            "trm_ParentTermID", "trm_Depth", "trm_Modified", "trm_LocallyModified", "trm_Code", "trm_ConceptID");
    }

    //
    //-----------------------------------------------------------------------------------------------
    // DETAILS


    /**
    * get rectype usage by detailTypeID
    * rectypesByDetailType = { dtyID => [rtyID,...], ...}
    * @return    object rtyIDs (array) using detailType indexed by dtyID
    */
    function getDetailTypeDefUsage($mysqli) {
        $rectypesByDetailType = array();
        $res = $mysqli->query("select rst_DetailTypeID as dtID, rst_RecTypeID as rtID
        from defRecStructure order by dtID, rtID");
        while ($row = $res->fetch_assoc()) {
            if (!array_key_exists($row['dtID'], $rectypesByDetailType)) {
                $rectypesByDetailType[$row['dtID']] = array();
            }
            array_push($rectypesByDetailType[$row['dtID']], $row["rtID"]);
        }
        $res->close();
        return $rectypesByDetailType;
    }
    /**
    * get usage counts of rectypes from the record table
    * usageCount = {rtyID:nonzero-count,...}
    * @return    object non-zero usage counts indexed by rtyID
    */
    function getRecTypeUsageCount($mysqli) {
        $recCountByRecType = array();
        $res = $mysqli->query("select rty_ID as rtID, count(rec_ID) as usageCnt
            from Records left join defRecTypes on rty_ID = rec_RecTypeID
        group by rec_RecTypeID");
        while ($row = $res->fetch_assoc()) {
            $recCountByRecType[$row['rtID']] = $row["usageCnt"];
        }
        $res->close();
        return $recCountByRecType;
    }
    //-----------------------------------------------------------------------------------------------

    /**
    * returns an array of detailType structured information for all detailTypes
    *     detailTypes = {"groups" => {"groupIDToIndex":{detailTypeGroupID:index},
    *                                       index:{propName:val,...},...},
    *                    "names":{dtyID:name,...},
    *                    "rectypeUsage" => { dtyID => [rtyID,...], ...},
    *                    'usageCount' => {dtyID:nonzero-count,...},
    *                    "typedefs":{"commonFieldNames":[defDetailTypes Column names],
    *                                "commonNamesToIndex":{"defDetailTypes columnName":index,...},
    *                                dtyID:{"commonFields":[val,....]},
    *                                ...},
    *                    'lookups' => {basetype=>displayName);
    *
    * @global    int $dbID databse ID
    * @param     boolean $useCachedData if true does a lookup for the detailtypes structure in cache
    * @return    object information describing all the detailtypes defined in the database
    * @uses      getDetailTypeColNames()
    * @uses      __getColumnNameToIndex()
    * @uses      __getRectypeStructureFieldColNames()
    * @uses      getDetailTypeGroups()
    * @uses      getDetailTypeUsageCount()
    * @uses      getDetailTypeDefUsage()
    * @uses      dbs_GetDtLookups()
    * @uses      getCachedData()
    * @uses      setCachedData()
    *
    * $dettypeids null means all, otherwise comma separated list of ids  or list of field types (numeric,blocktext etc)
    * $imode  0 - only names and groupnames
    *         1 - only structure
    *         2 - full, both headers and structures
    *         3 - ids only
    */
    function dbs_GetDetailTypes($system, $dettypeids=null, $imode){

        $mysqli = $system->get_mysqli();
        $dbID = $system->get_system('sys_dbRegisteredID');

        /*  ARTEM
        global $mysqli, $dbID;
        $cacheKey = DATABASE . ":AllDetailTypeInfo";
        if ($useCachedData) {
        $dtStructs = getCachedData($cacheKey);
        if ($dtStructs) {
        return $dtStructs;
        }
        }
        */

        $dtStructs = array();

        if($imode!=1){
            $dtG = getDetailTypeGroups($mysqli);
            $dtStructs['groups'] = $dtG;
            $dtStructs['names'] = array();
        }
        if($imode>0){
            $dtStructs['typedefs'] = array('commonFieldNames' => getDetailTypeColNames(),
                'fieldNamesToIndex' => __getColumnNameToIndex(getDetailTypeColNames()));
            $dtStructs['lookups'] = dbs_GetDtLookups();
        }
        if(false && $imode==2){
            $dtStructs['rectypeUsage'] = getDetailTypeDefUsage($mysqli);
            $dtStructs['usageCount']   = getDetailTypeUsageCount($mysqli);
        }

        $where_exp = null;        
        if($dettypeids!=null || $dettypeids!='' && $dettypeids!='all'){
            if(!is_array($dettypeids)){
                $dettypeids = array($dettypeids);
            }
            if($dettypeids[0]!='all'){
                //detect ID or TYPE
                if(is_int($dettypeids[0])){
                    $where_exp = ' dty_ID in ('.implode(',',$dettypeids).')';        
                }else{
                    $where_exp = ' dty_Type in (\''.implode("','",$dettypeids).'\')';        
                }
            }
        }
        
        if($imode==3){ //ids only
            //$query = "select dty_ID from defDetailTypes";
            if($where_exp==null){
                $where_exp = '';
            }
            $res = mysql__select_list($mysqli, 'defDetailTypes', 'dty_ID', $where_exp);
            return $res;
        }else{

            $query = "select dtg_ID, dtg_Name, " . join(",", getDetailTypeColNames());
            $query = preg_replace("/dty_ConceptID/", "", $query);
    
            if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
                $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(dty_ID as char(5)))) as dty_ConceptID";
            } else {
                $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), '') as dty_ConceptID";
            }
            $query.= " from defDetailTypes left join defDetailTypeGroups  on dtg_ID = dty_DetailTypeGroupID";
            if($where_exp!=null){
                $query = $query.' where '.$where_exp;    
            }
            $query = $query . " order by dtg_Order, dtg_Name, dty_OrderInGroup, dty_Name";
        }
        $res = $mysqli->query($query);
        //ARTEM    $dtStructs['sortedNames'] = mysql__select_assoc('defDetailTypes', 'dty_Name', 'dty_ID', '1 order by dty_Name');

        try{
            if(!$res){
                error_log('FAILED QUERY: '.$mysqli->error);//$query);
                error_log('Database: '.HEURIST_DBNAME);
            }else{
                while ($row = $res->fetch_row()) {
                    if($imode!=1){
                        array_push($dtStructs['groups'][$dtG['groupIDToIndex'][$row[0]]]['allTypes'], $row[2]);
                        if ($row[17]) {// dty_ShowInLists
                            array_push($dtStructs['groups'][$dtG['groupIDToIndex'][$row[0]]]['showTypes'], $row[2]);
                        }
                        $dtStructs['names'][$row[2]] = $row[3];
                    }
                    $dtStructs['typedefs'][$row[2]]['commonFields'] = array_slice($row, 2);
                }
            }
        }catch(Exception $e) {
            //trying to find veird error - missed trm_Modified column
            error_log('Message: ' .$e->getMessage());
            error_log('QUERY: '.$query);
            error_log('Database: '.HEURIST_DBNAME);
        }
        
        //SPECIAL CASE for relation type #6
        if($imode>0){
            if($system->defineConstant('DT_RELATION_TYPE') && @$dtStructs['typedefs'][DT_RELATION_TYPE]){
                $idx = $dtStructs['typedefs']['fieldNamesToIndex']['dty_JsonTermIDTree'];
                $dtStructs['typedefs'][DT_RELATION_TYPE]['commonFields'][$idx] = 0;
                $idx = $dtStructs['typedefs']['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
                $dtStructs['typedefs'][DT_RELATION_TYPE]['commonFields'][$idx] = '';
            }
        }
        
        //ARTEM setCachedData($cacheKey, $dtStructs);

        return $dtStructs;
    }

    /**
    * return record structure for given id and update $recstructures array
    */
    function getDetailType($system, &$detailtypes, $dtyID)
    {
        if (!@$detailtypes[$dtyID] ) {
            //load
            $res = dbs_GetDetailTypes($system, $dtyID, 1);
            if(!@$detailtypes['fieldNamesToIndex']){
                $detailtypes['fieldNamesToIndex'] = $res['typedefs']['fieldNamesToIndex'];
            }
            $detailtypes[$dtyID] = @$res['typedefs'][$dtyID]['commonFields'];
        }
        return @$detailtypes[$dtyID];
    }
    /**
    * get detailType Group definitions
    * groups = {"groupIDToIndex":{detailTypeGroupID:index},
    *           index:{propName:val,...},...}
    * @return    object detailTypeGroup definitions as array of prop:val pairs indexed by dtgID
    */
    function getDetailTypeGroups($mysqli) {
        $dtGroups = array('groupIDToIndex' => array());
        $index = 0;
        $res = $mysqli->query("select * from defDetailTypeGroups order by dtg_Order, dtg_Name");
        while ($row = $res->fetch_assoc()) {
            array_push($dtGroups, array('id' => $row["dtg_ID"], 'name' => $row["dtg_Name"], 'order' => $row["dtg_Order"], 'description' => $row["dtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
            $dtGroups['groupIDToIndex'][$row["dtg_ID"]] = $index++;
        }
        $res->close();
        return $dtGroups;
    }
    /**
    * get detailType usage counts
    * @return    array usage counts index by detailTypeID
    * @link      URL
    */
    function getDetailTypeUsageCount($mysqli) {
        $useCntByDetailTypeID = array();
        $res = $mysqli->query("select dty_ID as dtID, count(dtl_ID) as usageCnt ".
            " from recDetails left join defDetailTypes on dty_ID = dtl_DetailTypeID".
            " group by dtl_DetailTypeID");
        while ($row = $res->fetch_assoc()) {
            $useCntByDetailTypeID[$row['dtID']] = $row["usageCnt"];
        }
        $res->close();
        return $useCntByDetailTypeID;
    }
    /**
    * return array of defDetailType table column names
    */
    function getDetailTypeColNames() {
        return array("dty_ID", "dty_Name", "dty_Documentation", "dty_Type", "dty_HelpText", "dty_ExtendedDescription", "dty_Status",
            "dty_OriginatingDBID", "dty_IDInOriginatingDB", "dty_DetailTypeGroupID", "dty_OrderInGroup", "dty_JsonTermIDTree",
            "dty_TermIDTreeNonSelectableIDs", "dty_PtrTargetRectypeIDs", "dty_FieldSetRectypeID", "dty_ShowInLists",
            "dty_NonOwnerVisibility", "dty_Modified", "dty_LocallyModified", "dty_EntryMask", "dty_ConceptID");
    }
    /**
    * get map for internal storage base datatype names to Human readable interface names.
    * note this must match defDetailTypes table's enumeration for the dty_Type column
    * @return    object lookup from basetype to Display name
    * @todo      compare this against table for complete list logging error if mismatch
    */
    function dbs_GetDtLookups() {
        return array(
            "enum" => "Terms list",
            "float" => "Numeric",
            "date" => "Date / temporal",
            "file" => "File",
            "geo" => "Geospatial",
            "freetext" => "Text (single line)",
            "blocktext" => "Memo (multi-line)",
            "resource" => "Record pointer",
            "relmarker" => "Relationship marker",
            "separator" => "Heading (no data)",
            "calculated" => "Calculated (not yet impl.)",
            // Note=> the following types are no longer deinable but may be required for backward compatibility
            "relationtype" => "Relationship type",
            //"fieldsetmarker" => "Field set marker",
            "integer" => "Numeric - integer",
            "year" => "Year (no mm-dd)",
            //"urlinclude" => "File/URL of include content",
            "boolean" => "Boolean (T/F)");
    }
?>
