<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/**
*
* Function list:
* - setLastModified()
* - getCachedData()
* - setCachedData()
* - getResolvedIDs()
* - getBaseProperties()
* - getAllRecordDetails()
* - getAllReminders()
* - getAllComments()
* - getAllworkgroupTags()
* - attachChild()
* - getTermTree()
* - updateTermData()
* - getChildTerms()
* - getAllChildren()
* - setChildDepth()
* - getTermColNames()
* - getTerms()
* - getConceptID()
* - getTermConceptID()
* - getDetailTypeConceptID()
* - getRecTypeConceptID()
* - getOntologyConceptID()
* - getLocalID()
* - getTermLocalID()
* - getDetailTypeLocalID()
* - getRecTypeLocalID()
* - getOntologyLocalID()
* - getRectypeConstraints()
* - getAllRectypeConstraint()
* - getTermOffspringList()
* - getRectypeColNames()
* - getColumnNameToIndex()
* - getRectypeDef()
* - getRectypeStructureFieldColNames()
* - getRectypeFields()
* - getRectypeNames()
* - getRectypeStructure()
* - getRectypeStructures()
* - getAllRectypeStructures()
* - updateRecTypeUsageCount()
* - getRectypeGroups()
* - getRecTypesByGroup()
* - getDetailTypeDefUsage()
* - getRecTypeUsageCount()
* - getTransformsByOwnerGroup()
* - getToolsByTransform()
* - getDetailTypeUsageCount()
* - getDetailTypeColNames()
* - getDtLookups()
* - getAllDetailTypeStructures()
* - getDetailTypeGroups()
* - reltype_inverse()
* - fetch_relation_details()
* - getAllRelatedRecords()
*
* No Classes in this File
*
*/
require_once (dirname(__FILE__) . '/imageLibrary.php');
require_once (dirname(__FILE__) . '/../../records/files/uploadFile.php');
require_once(dirname(__FILE__).'/utilsTitleMask.php');

$lastModified = null;
$dbID = intval(HEURIST_DBID);
/**
* description
* @global    int $lastModified represents the last modified datetime
*/
function setLastModified() {
    global $lastModified;

    $res = mysql_query("select max(tlu_DateStamp) from sysTableLastUpdated where tlu_CommonObj = 1");
    $lastModified = mysql_fetch_row($res);
    $lastModified = strtotime($lastModified[0]);
}
/**
* get cached object
* @global    object $memcache
* @global    int $lastModified represents the last modified date
* @param     string [$key] key for data to retrieve
* @return    cached data for $key or null if not in cache
* @uses      MEMCACHED_PORT
*/
function getCachedData($key) {
    /*NO MEMCACHE ANYMORE global $memcache, $lastModified;

    setLastModified();

    // check the cached lastupdate value and return false on not equal meaning recreate data
    if ($lastModified > $memcache->get('lastUpdate:' . $key)) {
        return null;
    }

    return $memcache->get($key);
    */
    return null;
}
/**
* store object in cache
* @global    object $memcache
* @global    int $lastModified represents the last modified date
* @param     string [$key] key for data to store
* @param     mixed [$var] variable with data to store
* @return    boolean true if success, false otherwise
* @uses      MEMCACHED_PORT
*/
function setCachedData($key, $var) {
    /*NO MEMCACHE ANYMORE global $memcache, $lastModified;

    setLastModified();

    try{
        @$memcache->set('lastUpdate:' . $key, $lastModified);
        return $memcache->set($key, $var);
    }catch(Exception $e){
    }
    */
}
/**
* resolves a recID to any forwarded value and returns resolved recID with any bmkID for user and indicates
* that it had to resolve. If currect recID it returns with any user bkmID. If bkmID it returns with the
* corresponding recID.
* @param     int $recID record ID
* @param     int $bmkID bookmark ID
* @return    array list of recID,bkmID,isReplaced
*  @uses     get_user_id() in the lookup query for bookmarks
*/
function getResolvedIDs($recID, $bmkID) {
    // Look at the request parameters rec_ID and bkm_ID,
    // return the actual rec_ID and bkm_ID as the user has access to them

    /* chase down replaced-by-bib-id references */
    $replaced = false;
    if (intval(@$recID)) {
        $res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=$recID");
        $recurseLimit = 10;
        $resolvedRecID = 0;
        while (mysql_num_rows($res) > 0) {
            $row = mysql_fetch_row($res);
            $resolvedRecID = $row[0];
            $replaced = true;
            $res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=$resolvedRecID");
            if ($recurseLimit-- === 0) {
                return array();
            }
        }
        if ($resolvedRecID !== 0) {
            $recID = $resolvedRecID;
        }
    }
    $rec_id = 0;
    $bkm_ID = 0;
    if (intval(@$recID)) {
        $rec_id = intval($recID);
        $res = mysql_query('select rec_ID, bkm_ID from Records'.
            ' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID=' . get_user_id() . ' where rec_ID=' . $rec_id);
        $row = mysql_fetch_assoc($res);
        $rec_id = intval($row['rec_ID']);
        $bkm_ID = intval($row['bkm_ID']);
    }
    if (!$rec_id && intval(@$bmkID)) {
        $bkm_ID = intval($bmkID);
        $res = mysql_query('select bkm_ID, rec_ID from usrBookmarks'.
            ' left join Records on bkm_recID=rec_ID where bkm_ID=' . $bkm_ID . ' and bkm_UGrpID=' . get_user_id());
        $row = mysql_fetch_assoc($res);
        $bkm_ID = intval($row['bkm_ID']);
        $rec_id = intval($row['rec_ID']);
    }
    return array($rec_id, $bkm_ID, $replaced);
}
/**
* Return an array of the basic scalar properties for this record / bookmark
*
* @param     int $recID record ID
* @param     int $bmkID bookmark ID
* @return    object base properties of the record-bookmark pair
* @link      URL
* @see       name of another element (function or object) used in this function
* @todo      add code to get personal woots
*/
function getBaseProperties($recID, $bkmID) {
    if (!$recID && !$bkmID) {
        return array("error" => "invalid parameters passed to getBaseProperties");
    }

    if ($bkmID) {
        $res = mysql_query('select rec_ID, rec_Title as title, rty_Name as rectype,
            rty_ID as rectypeID, rec_URL as url, grp.ugr_ID as workgroupID,
            concat(grp.ugr_FirstName,\' \',grp.ugr_LastName) as name,
            grp.ugr_Name as workgroup, rec_ScratchPad as notes,
            rec_NonOwnerVisibility as visibility, bkm_PwdReminder as passwordReminder,
            bkm_Rating as rating, rec_Added, rec_Modified, rec_FlagTemporary
            from usrBookmarks left join Records on bkm_recID=rec_ID and bkm_UGrpID=' . get_user_id() . '
            left join defRecTypes on rty_ID = rec_RecTypeID
            left join ' . USERS_DATABASE . '.sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID
            where bkm_ID=' . $bkmID);
    } else if ($recID) {
        $res = mysql_query('select rec_ID, rec_Title as title, rty_Name as rectype, rty_ID as rectypeID,
            rec_URL as url, grp.ugr_ID as workgroupID, grp.ugr_Name as workgroup,
            concat(grp.ugr_FirstName,\' \',grp.ugr_LastName) as name,
            rec_ScratchPad as notes, rec_NonOwnerVisibility as visibility, rec_Added, rec_Modified,
            rec_FlagTemporary
            from Records left join usrBookmarks on bkm_recID=rec_ID
            left join defRecTypes on rty_ID = rec_RecTypeID
            left join ' . USERS_DATABASE . '.sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID
            where rec_ID=' . $recID);
    }
    $row = mysql_fetch_assoc($res);
    $recID = $row["rec_ID"];
    $props = array();
    if ($recID) $props["bibID"] = $recID;
    if ($recID) $props["recID"] = $recID; // saw TODO leave both for now until weed out bibID
    if ($bkmID) $props["bkmkID"] = $bkmID;
    $props["title"] = $row["title"];
    $props["rectype"] = $row["rectype"];
    $props["rectypeID"] = $row["rectypeID"];
    $props["url"] = $row["url"];
    $props["adddate"] = $row["rec_Added"];
    $props["moddate"] = $row["rec_Modified"];
    $props["isTemporary"] = $row["rec_FlagTemporary"] ? true : false;
    if (@$row["passwordReminder"]) {
        $props["passwordReminder"] = $row["passwordReminder"];
    }
    if (@$row["rating"]) {
        $props['rating'] = $row['rating'];
    }
    $props["quickNotes"] = @$row["notes"] ? $row["notes"] : "";
    if ($row['workgroupID'] != null) {
        $props['workgroupID'] = $row['workgroupID'];
        $props['workgroup'] = $row['workgroupID'] == get_user_id() ? $row['name'] : $row['workgroup'];
    }
    $props['visibility'] = ($row['visibility'] ? $row['visibility'] : '');
    $props['notes'] = $row['notes']; // saw TODO: add code to get personal woots
    if ($bkmID) {
        // grab the user tags for this bookmark, as a single comma-delimited string
        $kwds = mysql__select_array("usrRecTagLinks left join usrTags on tag_ID=rtl_TagID", "tag_Text", "rtl_RecID=$recID and tag_UGrpID=" . get_user_id() . " order by rtl_Order, rtl_ID");
        $props["tagString"] = join(",", $kwds);
    }
    return $props;
}
/**
* description
* Get all recDetails entries for this record,
* as an array.
* File entries have file data associated,
* geo entries have geo data associated,
* record references have title data associated.
* here we want  list of detail values for this record grouped by detail Type
* recordType can contain pseudoDetailTypes that are structural only information (relmarker, separator and fieldsetmarker)
* since these will never be instantiated to a detail (data value) we don't need to worry about them here. Any detail will
* will be of valid type and might not be in the definition of the rec type (Heurist separates data storage for type definition).
*
* @param     int $recID record ID
* @return    object array of details index by local detailID
* @uses      get_uploaded_file_info() to get the file info
*/
function getAllRecordDetails($recID) {
    $res = mysql_query("select dtl_ID, dtl_DetailTypeID, dtl_Value, rec_Title, dtl_UploadedFileID, trm_Label,
        if(dtl_Geo is not null, AsWKT(envelope(dtl_Geo)), null) as envelope,
        if(dtl_Geo is not null, AsWKT(dtl_Geo), null) as dtl_Geo
        from recDetails
        left join defDetailTypes on dty_ID=dtl_DetailTypeID
        left join Records on rec_ID=dtl_Value and dty_Type='resource'
        left join defTerms on trm_ID = dtl_Value
        where dtl_RecID = $recID order by dtl_DetailTypeID, dtl_ID");
    $recDetails = array();
    while ($row = mysql_fetch_assoc($res)) {
        $detail = array();
        $detail["id"] = $row["dtl_ID"];
        $detail["value"] = $row["dtl_Value"];
        if (array_key_exists('trm_Label', $row) && $row['trm_Label']) $detail["enumValue"] = $row["trm_Label"];
        if ($row["rec_Title"]) $detail["title"] = $row["rec_Title"];
        if ($row["dtl_UploadedFileID"]) {
            $fileres = get_uploaded_file_info($row["dtl_UploadedFileID"], false);
            if ($fileres) {
                $detail["file"] = $fileres["file"];
            }
        } else if ($row["envelope"] && preg_match("/^POLYGON[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", $row["envelope"], $poly)) {
            list($match, $minX, $minY, $maxX, $maxY) = $poly;
            $x = 0.5 * ($minX + $maxX);
            $y = 0.5 * ($minY + $maxY);
            // This is a bit ugly ... but it is useful.
            // Do things differently for a path -- set minX,minY to the first point in the path, maxX,maxY to the last point
            if ($row["dtl_Value"] == "l" && preg_match("/^LINESTRING[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/", $row["dtl_Geo"], $matches)) {
                list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
            }
            switch ($row["dtl_Value"]) {
                case "p":
                    $type = "point";
                    break;
                case "pl":
                    $type = "polygon";
                    break;
                case "c":
                    $type = "circle";
                    break;
                case "r":
                    $type = "rectangle";
                    break;
                case "l":
                    $type = "path";
                    break;
                default:
                    $type = "unknown";
            }
            $wkt = $row["dtl_Value"] . " " . $row["dtl_Geo"]; // well-known text value
            $detail["geo"] = array("minX" => $minX, "minY" => $minY, "maxX" => $maxX, "maxY" => $maxY, "x" => $x, "y" => $y, "type" => $type, "value" => $wkt);
        }
        if (!@$recDetails[$row["dtl_DetailTypeID"]]) $recDetails[$row["dtl_DetailTypeID"]] = array();
        array_push($recDetails[$row["dtl_DetailTypeID"]], $detail);
    }
    return $recDetails;
}
/**
* Get any reminders as an array
*
* @param     int [$recID] record ID
* @return    array of reminder structures
*/
function getAllReminders($recID) {
    // Get any reminders as an array
    if (!$recID) return array();
    // ... MYSTIFYINGLY these are stored by rec_ID+user_id, not bkm_ID
    $res = mysql_query("select * from usrReminders where rem_RecID=$recID and rem_OwnerUGrpID=" . get_user_id() . " order by rem_StartDate");
    $reminders = array();
    if (mysql_num_rows($res) > 0) {
        while ($rem = mysql_fetch_assoc($res)) {
            array_push($reminders, array("id" => $rem["rem_ID"],
                "user" => $rem["rem_ToUserID"],
                "group" => $rem["rem_ToWorkgroupID"],
                "email" => $rem["rem_ToEmail"],
                "message" => $rem["rem_Message"],
                "when" => $rem["rem_StartDate"],
                "frequency" => $rem["rem_Freq"]));
        }
    }
    return $reminders;
}
/**
* Get any comments as an array
*
* @param     int [$recID] record ID
* @return    array of comment structures index by comment ID
*/
function getAllComments($recID) {
    $res = mysql_query("select cmt_ID, cmt_Deleted, cmt_Text, cmt_ParentCmtID, cmt_Added, cmt_Modified, cmt_OwnerUgrpID, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from recThreadedComments left join " . USERS_DATABASE . ".sysUGrps usr on cmt_OwnerUgrpID=usr.ugr_ID where cmt_RecID = $recID order by cmt_Added");
    $comments = array();
    if($res==false){
        
    }else{
        while ($cmt = mysql_fetch_assoc($res)) {
            if ($cmt["cmt_Deleted"]) {
                /* indicate that the comments exists but has been deleted */
                $comments[$cmt["cmt_ID"]] = array("id" => $cmt["cmt_ID"],
                    "owner" => $cmt["cmt_ParentCmtID"],
                    "deleted" => true);
                continue;
            }
            $comments[$cmt["cmt_ID"]] = array("id" => $cmt["cmt_ID"],
                "text" => $cmt["cmt_Text"],
                "owner" => $cmt["cmt_ParentCmtID"], /* comments that owns this one (i.e. parent, just like in Dickensian times) */
                "added" => $cmt["cmt_Added"],
                "modified" => $cmt["cmt_Modified"],
                "user" => $cmt["Realname"],
                "userID" => $cmt["cmt_OwnerUgrpID"],
                "deleted" => false);
        }//while
    }
    return $comments;
}
/**
* Get all workgroup tagODs for this record as an array
*
* @param     int [$recID] record ID
* @return    array of comment structures index by comment ID
* @todo      should limit this just to workgroups that the user is in? keeps from viewing others tags
*/
function getAllworkgroupTags($recID) {
    $res = mysql_query("select tag_ID from usrRecTagLinks, usrTags where rtl_TagID=tag_ID and rtl_RecID=$recID");
    $wgTagIDs = array();
    while ($row = mysql_fetch_row($res)) {
        array_push($wgTagIDs, $row[0]);
    }
    return $wgTagIDs;
}
/**
* attaches a child branch to a parent, recursively calling itself to build the childs subtree
* @param     int $parentIndex id of parent term to attach child branch to
* @param     int $childIndex id of child branch in current array
* @param     mixed $terms the array of branches to build the tree
* @return    object $terms
*/
function attachChild($parentIndex, $childIndex, $terms, $parents) {

    if (array_key_exists($childIndex, $terms)) {//check for
        if (count($terms[$childIndex])) {
            
            
            if($parents==null){
                $parents = array($childIndex);
            }else{
                array_push($parents, $childIndex);
            }
            
            
            foreach ($terms[$childIndex] as $gChildID => $n) {
                if ($gChildID != null) {

                    if(array_search($gChildID, $parents)===false){
                        $terms = attachChild($childIndex, $gChildID, $terms, $parents);//depth first recursion
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
* @uses      attachChild()
*/
function getTermTree($termDomain, $matching = 'exact') { // termDomain can be empty, 'reltype' or 'enum' or any future term use domain defined in the trm_Domain enum
    $whereClause = "a.trm_Domain " . ($matching == 'prefix' ? " like '" . $termDomain . "%' " : ($matching == 'postfix' ? " like '%" . $termDomain . "' " : "='" . $termDomain . "'"));
    $query = "select a.trm_ID as pID, b.trm_ID as cID
    from defTerms a
    left join defTerms b on a.trm_ID = b.trm_ParentTermID
    where $whereClause
    order by a.trm_Label, b.trm_Label";
    $res = mysql_query($query);
    $terms = array();
    // create array of parent => child arrays
    while ($row = mysql_fetch_assoc($res)) {
        if (!@$terms[$row["pID"]]) {
            $terms[$row["pID"]] = array();
        }
        if ($row['cID']) {//insert child under parent
            $terms[$row["pID"]][$row['cID']] = array();
        }
    }//we have all the branches, now lets build a tree
    foreach ($terms as $parentID => $childIDs) {
        foreach ($childIDs as $childID => $n) {
            //check that we have a child branch
            if ($childID != null && array_key_exists($childID, $terms)) {
                if (count($terms[$childID])) {//yes then attach it and it's children's branches
                    $terms = attachChild($parentID, $childID, $terms, null);
                } else {//no then it's a leaf in a branch, remove this redundant node.
                    unset($terms[$childID]);
                }
            }
        }
    }
    return $terms;
}

// 
//  return all term children as plain array
//
function getAllChildren($parentID){
    
        $children = array();
    
        $res = mysql_query("select trm_ID from trm_ParentTermID = " . $parentID);
        if ($res) {
            while ($row = mysql_fetch_row($res)) {
                array_push($children, $row[0]);
                $children = array_merge($children, getAllChildren($row[0]));
            }
        }
        
        return $children;
}


/**
* calculate depth and child count for each term
*/
function updateTermData() {
    //mysql_query("start transaction");
    // set al child counts to zero
    // and set all depths to zero
    mysql_query("update defTerms set trm_ChildCount = 0, trm_Depth = 0");
    // update  all child counts
    mysql_query("update defTerms c " . "join (select distinct a.trm_ID as ID, count(b.trm_ID) as cnt " . "from defTerms b left join defTerms a on b.trm_ParentTermID = a.trm_ID " . "where a.trm_ID is not null and not b.trm_ID = a.trm_ID " . "group by a.trm_ID) temp on temp.ID = c.trm_ID " . "set c.trm_ChildCount = temp.cnt");
    function getChildTerms($parentID) {
        $children = array();
        if ($parentID == "top") {
            $whereClause = "trm_ParentTermID is null or trm_ParentTermID = 0";
        } else {
            $whereClause = "trm_ParentTermID = " . $parentID;
        }
        $res = mysql_query("select trm_ID,trm_ChildCount from defTerms where $whereClause");
        // if we have an error or found nothing return null
        if (!mysql_num_rows($res)) {
            return null;
        }
        while ($row = mysql_fetch_row($res)) {
            $children[$row[0]] = $row[1];
        }
        return $children;
    }
    function setChildDepth($parentID, $parentDepth) {
        $children = getChildTerms($parentID);
        if (!$children) {// if no children nothing to do so return
            return;
        }
        $childIDList = join(",", array_keys($children));
        $depth = $parentDepth + 1;
        // set every childs depth
        $query = "update defTerms set trm_Depth = " . $depth . " where trm_ID in(" . $childIDList . ")";
        mysql_query($query);
        foreach ($children as $childID => $childCount) {
            if ($childCount) {
                setChildDepth($childID, $depth);
            }
        }
    }
    //find all top level termIDs
    $rootTermIDs = getChildTerms("top");
    //for each top level if children setChildren depth (recursively)
    foreach ($rootTermIDs as $rootID => $childCount) {
        if ($childCount) {
            setChildDepth($rootID, 0);
        }
    }
    //mysql_query("commit");

}
/**
* return array of term table column names
*/
function getTermColNames() {
    return array("trm_ID", "trm_Label", "trm_InverseTermID", "trm_Description", "trm_Status", "trm_OriginatingDBID",
        //					"trm_NameInOriginatingDB",
        "trm_IDInOriginatingDB", "trm_AddedByImport", "trm_IsLocalExtension", "trm_Domain", "trm_OntID", "trm_ChildCount", "trm_ParentTermID", "trm_Depth", 
        "trm_Modified", "trm_LocallyModified", "trm_Code", "trm_ConceptID");
}
/**
* get term structure with trees from relation and enum domains
* @param     boolean $useCachedData whether to use cached data (default = false)
* @return    object terms structure that contains domainLookups and domain term trees
* @uses      getTermColNames()
* @uses      getTermTree()
* @uses      getCachedData()
* @uses      setCachedData()
*/
function getTerms($useCachedData = false) {
    global $dbID;
    $cacheKey = DATABASE . ":getTerms";
    if ($useCachedData) {
        $terms = getCachedData($cacheKey);
        if ($terms) {
            return $terms;
        }
    }
    $query = "select " . join(",", getTermColNames());
    $query = preg_replace("/trm_ConceptID/", "", $query);
    if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
        $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), concat('$dbID-',cast(trm_ID as char(5)))) as trm_ConceptID";
    } else {
        $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), '') as trm_ConceptID";
    }
    $query.= " from defTerms order by trm_Domain, trm_Label";
    $res = mysql_query($query);
    $terms = array('termsByDomainLookup' => array('relation' => array(), 'enum' => array()), 
        'commonFieldNames' => array_slice(getTermColNames(), 1), 
        'fieldNamesToIndex' => getColumnNameToIndex(array_slice(getTermColNames(), 1)));

    $terms['fieldNamesToIndex']['trm_HasImage'] = count($terms['commonFieldNames']);
    array_push($terms['commonFieldNames'],'trm_HasImage');        

    if(!$res){
        if (mysql_error()) {
            return array("error" => mysql_error());
        }
    }else{    
        $lib_dir = HEURIST_FILESTORE_DIR . 'term-images/';
        while ($row = mysql_fetch_row($res)) {
            $terms['termsByDomainLookup'][$row[9]][$row[0]] = array_slice($row, 1);

            $hasImage = file_exists($lib_dir.$row[0].'.png');
            array_push($terms['termsByDomainLookup'][$row[9]][$row[0]], $hasImage);
        }
    }
    $terms['treesByDomain'] = array('relation' => getTermTree("relation", "prefix"), 'enum' => getTermTree("enum", "prefix"));
    setCachedData($cacheKey, $terms);
    return $terms;
}
/**
* translate a local id for a given table to it's concept ID
* @param     string $lclID local id of row in $tableName table
* @param     string $tableName name of table
* @param     string $fieldNamePrefix column name prefix used in $tableName table
* @return    string concept id (dbID-origID) or null if no HEURIST DBID
* @uses      HEURIST_DBID
*/
function getConceptID($lclID, $tableName, $fieldNamePrefix) {
    $query = "select " . $fieldNamePrefix . "OriginatingDBID," . $fieldNamePrefix . "IDInOriginatingDB from $tableName where " . $fieldNamePrefix . "ID = $lclID";
    $res = mysql_query($query);
    if($res){
        $ids = mysql_fetch_array($res);
    }else{
        $ids = null;
    }
    //return "".$ids[0]."-".$ids[1];
    if ($ids && count($ids) == 4 && is_numeric($ids[0]) && is_numeric($ids[1])) {
        return "" . $ids[0] . "-" . $ids[1];
    } else if (HEURIST_DBID) {
        return "" . HEURIST_DBID . "-" . $lclID;
    } else {
        return null;
    }
}
/**
* return a terms concpet ID
* @param     int $lclTermID local Term ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
function getTermConceptID($lclTermID) {
    return getConceptID($lclTermID, "defTerms", "trm_");
}
/**
* return a detailTypes concpet ID
* @param     int $lclDtyID local detailType ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
function getDetailTypeConceptID($lclDtyID) {
    return getConceptID($lclDtyID, "defDetailTypes", "dty_");
}
/**
* return a recTypes concpet ID
* @param     int $lclRecTypeID local recType ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
function getRecTypeConceptID($lclRecTypeID) {
    return getConceptID($lclRecTypeID, "defRecTypes", "rty_");
}
/**
* return a ontologies concpet ID
* @param     int $lclOntID local ontology ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
function getOntologyConceptID($lclOntID) {
    return getConceptID($lclOntID, "defOntologies", "ont_");
}
/**
* get local id for concept id of a given table row
* @global    type description of global variable usage in a function
* @staticvar type [$varname] description of static variable usage in function
* @param     string $conceptID concept id of row in $tableName table
* @param     string $tableName name of table
* @param     string $fieldNamePrefix column name prefix used in $tableName table
* @return    int id or null if not found
* @uses      HEURIST_DBID
*/
function getLocalID($conceptID, $tableName, $fieldNamePrefix) {
    $ids = explode("-", $conceptID);
    $res_id = null;
    if ($ids && (count($ids) == 1 && is_numeric($ids[0])) || (count($ids) == 2 && is_numeric($ids[1]) && $ids[0] == HEURIST_DBID)) {
        if (count($ids) == 2) {
            $res_id = $ids[1]; //this code is already local
        } else {
            $res_id = $ids[0];
        }
        $res = mysql_query("select " . $fieldNamePrefix . "ID from $tableName where " . $fieldNamePrefix . "ID=" . $res_id);
        $id = mysql_fetch_array($res);
        if ($id && count($id) > 0 && is_numeric($id[0])) {
            $res_id = $id[0];
        } else {
            $res_id = null;
        }
    } else if ($ids && count($ids) == 2 && is_numeric($ids[0]) && is_numeric($ids[1])) {
        $query = "select " . $fieldNamePrefix . "ID from $tableName where " . $fieldNamePrefix . "OriginatingDBID=" . $ids[0] . " and " . $fieldNamePrefix . "IDInOriginatingDB=" . $ids[1];
        $res = mysql_query($query);
        $id = mysql_fetch_array($res);
        if ($id && count($id) > 0 && is_numeric($id[0])) {
            $res_id = $id[0];
        }
    }
    return $res_id;
}
/**
* return local term id for a terms concept ID
* @param     int $trmConceptID Term concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
function getTermLocalID($trmConceptID) {
    return getLocalID($trmConceptID, "defTerms", "trm_");
}
/**
* return local detailType id for a detailTypes concept ID
* @param     int $dtyConceptID detailType concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
function getDetailTypeLocalID($dtyConceptID) {
    return getLocalID($dtyConceptID, "defDetailTypes", "dty_");
}
/**
* return local recType id for a recTypes concept ID
* @param     int $rtyConceptID recType concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
function getRecTypeLocalID($rtyConceptID) {
    return getLocalID($rtyConceptID, "defRecTypes", "rty_");
}
/**
* return local ontology id for a ontologys concept ID
* @param     int $ontConceptID ontology concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
function getOntologyLocalID($ontConceptID) {
    return getLocalID($ontConceptID, "defOntologies", "ont_");
}
/**
* get array of constraints for a recType using the recType in the source record role
* @param     int $rectypeID constraint source recType ID
* @return    array constraint array indexed by srcID then trgID then by trmID with max values
*/
function getRectypeConstraints($rectypeID) {
    $query = "select rcs_SourceRectypeID as srcID,
    rcs_TermID as trmID,
    rcs_TargetRectypeID as trgID,
    rcs_TermLimit as max,
    trm_Depth as level
    from defRelationshipConstraints
    left join defTerms on rcs_TermID = trm_ID
    " . (@$rectypeID ? " where rcs_SourceRectypeID = $rectypeID or rcs_SourceRectypeID is null" : "") . "
    order by rcs_SourceRectypeID is null,
    rcs_SourceRectypeID,
    trm_Depth,
    rcs_TermID is null,
    rcs_TermID,
    rcs_TargetRectypeID is null,
    rcs_TargetRectypeID";
    $res = mysql_query($query);
    $cnstrnts = array();
    while ($row = mysql_fetch_assoc($res)) {
        $srcID = (@$row['srcID'] === null ? "" . '0' : $row['srcID']);
        $trmID = (@$row['trmID'] === null ? "" . '0' : $row['trmID']);
        $trgID = (@$row['trgID'] === null ? "" . '0' : $row['trgID']);
        $max = (@$row['max'] === null ? '' : $row['max']);
        if (!@$cnstrnts[$srcID]) {
            $cnstrnts[$srcID] = array();
        }
        if (!@$cnstrnts[$srcID][$trmID]) {
            $cnstrnts[$srcID][$trmID] = array($trgID => $max);
        } else {
            $cnstrnts[$srcID][$trmID][$trgID] = $max;
        }
    }
    return $cnstrnts;
}
/**
* get rectype constraint structure with lookups by target and term id index by srcID
* constraints = array( [recID | any] => array(
*                          byTerm => array(
*                              [trmID|any] => array([trgID|any] => array(
*                                                          limit => $max
*                                                          [,addsTo => parentTrmID]))
*                              [,offspring =>array(trmID[,trmID])),
*                          byTarget => array(
*                              [trgID|any] => array([trmID|any] => array(
*                                                          limit => $max,
*                                                          notes => $notes
*                                                          [,addsTo => parentTrmID])))))
* where $max can be "unlimited" or any positive integer with 0 meaning not allowed
* addsTo atttemps to handle child terms with out specific constraints, they effectively
* inherit the parents limit.
* @return    object constraint structure
* @uses      getTermOffspringList()
*/
function getAllRectypeConstraint() {
    $query = "select rcs_SourceRectypeID as srcID,
    rcs_TermID as trmID,
    rcs_TargetRectypeID as trgID,
    rcs_TermLimit as max,
    rcs_Description as notes,
    trm_Depth as level,
    if(trm_ChildCount > 0, true, false) as hasChildren
    from defRelationshipConstraints
    left join defTerms on rcs_TermID = trm_ID
    order by rcs_SourceRectypeID is null,
    rcs_SourceRectypeID,
    trm_Depth,
    rcs_TermID is null,
    rcs_TermID,
    rcs_TargetRectypeID is null,
    rcs_TargetRectypeID";
    $res = mysql_query($query);
    $cnstrnts = array();
    while ($row = mysql_fetch_assoc($res)) {
        //		$srcID = (@$row['srcID'] === null ? "".'0' : $row['srcID']);
        //		$trmID = (@$row['trmID'] === null ? "".'0' : $row['trmID']);
        //		$trgID = (@$row['trgID'] === null ? "".'0' : $row['trgID']);
        //		$max = (@$row['max'] === null ? '' : $row['max']);
        $srcID = (@$row['srcID'] === null ? "any" : $row['srcID']);
        $trmID = (@$row['trmID'] === null ? "any" : $row['trmID']);
        $trgID = (@$row['trgID'] === null ? "any" : $row['trgID']);
        $max = (@$row['max'] === null ? 'unlimited' : $row['max']);
        $notes = $row['notes'];
        $hasChildren = $row['hasChildren'];
        if (!@$cnstrnts[$srcID]) {//first instance of this recType as source, create structure
            $cnstrnts[$srcID] = array('byTerm' => array(), 'byTarget' => array());
        }
        if (!@$cnstrnts[$srcID]['byTerm'][$trmID]) {//first instance of this recType as target, create structure
            $cnstrnts[$srcID]['byTerm'][$trmID] = array($trgID => array('limit' => $max));
        }
        if (!@$cnstrnts[$srcID]['byTarget'][$trgID]) {//first instance of this recType as bytarget target, create structure
            $cnstrnts[$srcID]['byTarget'][$trgID] = array($trmID => array('limit' => $max, "notes" => $notes));
        } else if (!@$cnstrnts[$srcID]['byTarget'][$trgID][$trmID]) {
            $cnstrnts[$srcID]['byTarget'][$trgID][$trmID] = array('limit' => $max, "notes" => $notes);
        }
        if (!@$cnstrnts[$srcID]['byTerm'][$trmID][$trgID]) {//new target for term lookup
            $cnstrnts[$srcID]['byTerm'][$trmID][$trgID] = array('limit' => $max);
        }
        if (@$cnstrnts[$srcID]['byTerm'][$trmID][$trgID]['addsTo']) {
            $cnstrnts[$srcID]['byTerm'][$trmID][$trgID]['limit'] = $max;
        }
        if (@$cnstrnts[$srcID]['byTarget'][$trgID][$trmID]['addsTo']) {
            $cnstrnts[$srcID]['byTarget'][$trgID][$trmID]['limit'] = $max;
        }
        $offspring = $trmID && $trmID !== "any" && $hasChildren ? getTermOffspringList($trmID) : null;
        if ($offspring) {
            $cnstrnts[$srcID]['byTerm'][$trmID]['offspring'] = $offspring;
            foreach ($offspring as $childTermID) { // point all offspring to inherit from term
                $cnstrnts[$srcID]['byTerm'][$childTermID][$trgID] = array('addsTo' => $trmID);
                $cnstrnts[$srcID]['byTarget'][$trgID][$childTermID] = array('addsTo' => $trmID);
            }
        }
    }
    return $cnstrnts;
}
/**
* returns array list of all terms under a given term
* @param     int $termID
* @param     boolean $getAllDescentTerms determines whether to recurse and retrieve children of children (default = true)
* @return    array  of term IDs
*/
function getTermOffspringList($termID, $parentlist = null) {
    if($parentlist==null) $parentlist = array($termID);
    $offspring = array();
    if ($termID) {
        $res = mysql_query("select * from defTerms where trm_ParentTermID=$termID");
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_assoc($res)) { // for each child node
                $subTermID = $row['trm_ID'];
                if(array_search($subTermID, $parentlist)===false){
                    array_push($offspring, $subTermID);
                    array_push($parentlist, $subTermID);
                    $offspring = array_merge($offspring, getTermOffspringList($subTermID, $parentlist));
                }else{
                    error_log('Database '.DATABASE.'. Recursion in parent-term hierarchy '.$termID.'  '.$subTermID);
                }
            }
        }
    }
    return $offspring;
}
//
// get tree for domain
//
function getTermListAll($termDomain) {
        $terms = array();
        $res = mysql_query('SELECT * FROM defTerms
            where (trm_Domain="'.$termDomain.'") and (trm_ParentTermId=0 or trm_ParentTermId is NULL)');

        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_assoc($res)) { // for each child node
                array_push($terms, $row['trm_ID']);
                if (true){ //ARTEM: trm_ChildCount is not reliable   }$row['trm_ChildCount'] > 0 && $getAllDescentTerms) {
                    $terms = array_merge($terms, getTermOffspringList( $row['trm_ID'] ));
                }
            }
        }else{
        }
        return $terms;
}

function getTermCodes($termIDs) {
    $labels = array();
    if ($termIDs) {
        $res = mysql_query("select trm_ID, LOWER(trm_Code) from defTerms where trm_ID in (".implode(",", $termIDs).")");
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_row($res)) {
                $labels[$row[0]] = $row[1];
            }
        }
    }
    return $labels;
}

function getTermByCode($code){

    if ($label) {
        $res = mysql_query("select trm_ID from defTerms where LOWER(trm_Code) = '".mysql_real_escape_string($code)."'" );
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_row($res)) {
                return $row[0];
            }
        }
    }
    return false;
}


function getTermLabels($termIDs) {
    $labels = array();
    if ($termIDs) {
        $res = mysql_query("select trm_ID, LOWER(trm_Label) from defTerms where trm_ID in (".implode(",", $termIDs).")");
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_row($res)) {
                $labels[$row[0]] = $row[1];
            }
        }
    }
    return $labels;
}



function getTermByLabel($label){

    if ($label) {
        $res = mysql_query("select trm_ID from defTerms where LOWER(trm_Label) = '".mysql_real_escape_string($label)."'" );
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_row($res)) {
                return $row[0];
            }
        }
    }
    return false;
}


function getFullTermLabel($dtTerms, $term, $domain, $withVocab, $parents=null){

    $fi = $dtTerms['fieldNamesToIndex'];
    $parent_id = $term[ $fi['trm_ParentTermID'] ];

    $parent_label = '';

    if($parent_id!=null && $parent_id>0){
        $term_parent = @$dtTerms['termsByDomainLookup'][$domain][$parent_id];
        if($term_parent){
            if(!$withVocab){
                $parent_id = $term_parent[ $fi['trm_ParentTermID'] ];
                if(!($parent_id>0)){
                    return $term[ $fi['trm_Label']];
                }
            }
            
            if($parents==null){
                $parents = array();
            }
            
            if(array_search($parent_id, $parents)===false){
                array_push($parents, $parent_id);
                
                $parent_label = getFullTermLabel($dtTerms, $term_parent, $domain, $withVocab, $parents);    
                if($parent_label) $parent_label = $parent_label.'.';
            }
        }    
    }
    return $parent_label.$term[ $fi['trm_Label']];
}

/**
* return array of recType table column names
*/
function getRectypeColNames() {
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
function getColumnNameToIndex($columns) {
    $columnsNameIndexMap = array();
    $index = 0;
    foreach ($columns as $columnName) {
        $columnsNameIndexMap[$columnName] = $index++;
    }
    return $columnsNameIndexMap;
}
/**
* get recType info ordered by the recTypeGroup order, then by recTypeGroup name,
* then by recType order in group and finally by recType name
* @param     int [$rtID] recType ID
* @return    array of values for columns defined in getRectypeColNames()
* @uses      getRectypeColNames()
*/
function getRectypeDef($rtID) {
    global $dbID;

    $rtDef = array();
    //
    $query = "select " . join(",", getRectypeColNames());

    $query = preg_replace("/rty_ConceptID/", "", $query);
    if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
        $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID";
    } else {
        $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), '') as rty_ConceptID";
    }

    $query = $query . " from defRecTypes".
    " left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID".
    " where rty_ID=$rtID".
    " order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name";


    $res = mysql_query($query);
    if($res){
        $rtDef = mysql_fetch_row($res);

        //special behaviour for rty_TitleMask
        //it stores as cocept codes - need to convert it to human readable string
        $rtDef = makeTitleMaskHumanReadable($rtDef, $rtID);
    }else{
    }

    return $rtDef;
}
/**
* return array of defRecStructure (fields) table column names
*/
function getRectypeStructureFieldColNames() {
    return array("rst_DisplayName", "rst_DisplayHelpText", "rst_DisplayExtendedDescription", "rst_DisplayOrder", 
        "rst_DisplayWidth", "rst_DisplayHeight",
        "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType", "rst_NonOwnerVisibility",
        "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues", "rst_DisplayDetailTypeGroupID",
        "rst_FilteredJsonTermIDTree", "rst_PtrFilteredIDs", "rst_CreateChildIfRecPtr",
        "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs",
        "rst_Modified", "rst_LocallyModified", "dty_TermIDTreeNonSelectableIDs", "dty_FieldSetRectypeID", "dty_Type");
}
/**
* get field definitions for a given rectype
* @param     int [$rtID] recType ID
* @return    object index by detatilType array of field definitions ordered the same as getRectypeStructureFieldColNames()
*/
function getRectypeFields($rtID) {
    
    
        //verify that required column exists in sysUGrps
        $query = "SHOW COLUMNS FROM `defRecStructure` LIKE 'rst_CreateChildIfRecPtr'";
        $res = mysql_query($query);
        if($res && mysql_num_rows($res)==0){
            //alter table
            $query = "ALTER TABLE `defRecStructure` ADD COLUMN `rst_CreateChildIfRecPtr` TINYINT(1) DEFAULT 0 COMMENT 'For pointer fields, flags that new records created from this field should be marked as children of the creating record' AFTER `rst_PtrFilteredIDs`";

            $res = mysql_query($query);
            if(!$res){
                error_log('Cannot modify defRecStructure to add rst_CreateChildIfRecPtr');
            }
            return false;
        }
    
    
    $rtFieldDefs = array();
    // NOTE: these are ordered to match the order of getRectypeStructureFieldColNames from DisplayName on
    $colNames = array("rst_DetailTypeID",
        //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
        "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
        //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
        "if(dty_Type='separator' OR (rst_DisplayHelpText is not null and CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
        //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
        "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
        "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType",
        "rst_NonOwnerVisibility", "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
        //here we check for an override in the recTypeStrucutre for displayGroup
        "if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID) as rst_DisplayDetailTypeGroupID",
        //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
        //ARTEM we never use rst_FilteredJsonTermIDTree "if(rst_FilteredJsonTermIDTree is not null and CHAR_LENGTH(rst_FilteredJsonTermIDTree)>0,rst_FilteredJsonTermIDTree,dty_JsonTermIDTree) as rst_FilteredJsonTermIDTree",
        "dty_JsonTermIDTree as rst_FilteredJsonTermIDTree",
        //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
        //ARTEM we never use rst_PtrFilteredIDs "if(rst_PtrFilteredIDs is not null and CHAR_LENGTH(rst_PtrFilteredIDs)>0,rst_PtrFilteredIDs,dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs",
        "dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs",
        "rst_CreateChildIfRecPtr",
        "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified", "dty_TermIDTreeNonSelectableIDs",
        "dty_FieldSetRectypeID", "dty_Type");
    // get rec Structure info ordered by the detailType Group order, then by recStruct display order and then by ID in recStruct incase 2 have the same order
    $res = mysql_query("select " . join(",", $colNames) . " from defRecStructure
        left join defDetailTypes on rst_DetailTypeID = dty_ID
        left join defDetailTypeGroups on dtg_ID = if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID)
        where rst_RecTypeID=" . $rtID . "
    order by rst_DisplayOrder, rst_ID");
    while ($row = mysql_fetch_row($res)) {
        //use first element as index
        $rtFieldDefs[$row[0]] = array_slice($row, 1);
    }
    return $rtFieldDefs;
}
/**
* get a recType structure consisting of recType commonFields and recType fields
* @param     int [$rtID] recType ID
* @return    object containing recType commonFields and dtFields (fields index by detailType)
* @uses      getRectypeDef()
* @uses      getRectypeFields()
*/
function getRectypeStructure($rtID) {
    $rectypesStructure = array();
    $rectypesStructure['commonFields'] = getRectypeDef($rtID);
    $rectypesStructure['dtFields'] = getRectypeFields($rtID);
    return $rectypesStructure;
}

/**
* put your comment there...
* 
* @param mixed $rtyIDs
*/
function getRecTypeNames($rtyIDs) {
    $labels = array();
    if ($rtyIDs) {
        $res = mysql_query("select rty_ID, rty_Name from defRecTypes where rty_ID in (".implode(",", $rtyIDs).")");
        if ($res && mysql_num_rows($res)) {
            while ($row = mysql_fetch_row($res)) {
                $labels[$row[0]] = $row[1];
            }
        }
    }
    return $labels;
}

/**
* returns an array of RecType Structures for array of ids passed in
* @param     array [$rtIDs] of recType IDs
* @return    object containing recType structures indexed by recTypeID and column names with index lookups.
* @uses      getRectypeColNames()
* @uses      getColumnNameToIndex()
* @uses      getRectypeStructureFieldColNames()
* @uses      getRectypeStructure()
*/
function getRectypeStructures($rtIDs) {
    $rtStructs = array('commonFieldNames' => getRectypeColNames(),
        'commonNamesToIndex' => getColumnNameToIndex(getRectypeColNames()),
        'dtFieldNamesToIndex' => getColumnNameToIndex(getRectypeStructureFieldColNames()),
        'dtFieldNames' => getRectypeStructureFieldColNames());
    foreach ($rtIDs as $rt_id) {
        $rtStructs[$rt_id] = getRectypeStructure($rt_id);
    }
    return $rtStructs;
}
/**
* returns an array of RecType Structures for all RecTypes
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
* @global    int $dbID databse ID
* @param     boolean $useCachedData if true does a lookup for the rectypes structure in cache
* @return    object iformation describing all the rectypes defined in the database
* @uses      getRectypeColNames()
* @uses      getColumnNameToIndex()
* @uses      getRectypeStructureFieldColNames()
* @uses      DATABASE
* @uses      getCachedData()
* @uses      setCachedData()
* @uses      getRectypeGroups()
* @uses      getRecTypeUsageCount()
*/
function getAllRectypeStructures($useCachedData = false) {
    global $dbID;

    $cacheKey = DATABASE . ":AllRecTypeInfo";
    if ($useCachedData) {
        $rtStructs = getCachedData($cacheKey);
        if ($rtStructs) {
            return $rtStructs;
        }
    }

    // NOTE: these are ordered to match the order of getRectypeStructureFieldColNames from DisplayName on
    $colNames = array("rst_RecTypeID", "rst_DetailTypeID",
        //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
        "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
        //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
        "if(dty_Type='separator' OR (rst_DisplayHelpText is not null and CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
        //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
        "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
        "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType",
        "rst_NonOwnerVisibility", "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
        //here we check for an override in the recTypeStrucutre for displayGroup
        "if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID) as rst_DisplayDetailTypeGroupID",
        //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
        //ARTEM WE NEVER USE rst_FilteredJsonTermIDTree "if(rst_FilteredJsonTermIDTree is not null and CHAR_LENGTH(rst_FilteredJsonTermIDTree)>0,rst_FilteredJsonTermIDTree,dty_JsonTermIDTree) as rst_FilteredJsonTermIDTree",
        "dty_JsonTermIDTree as rst_FilteredJsonTermIDTree",
        //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
        //ARTEM WE NEVER USE "if(rst_PtrFilteredIDs is not null and CHAR_LENGTH(rst_PtrFilteredIDs)>0,rst_PtrFilteredIDs,dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs",
        "dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs",
        "rst_CreateChildIfRecPtr",
        "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified", "dty_TermIDTreeNonSelectableIDs",
        "dty_FieldSetRectypeID", "dty_Type");
    $query = "select " . join(",", $colNames) .
    " from defRecStructure".
    " left join defDetailTypes on rst_DetailTypeID = dty_ID".
    " left join defDetailTypeGroups on dtg_ID = if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID)".
    " order by rst_RecTypeID, rst_DisplayOrder, rst_ID";
    
    $res = mysql_query($query);
    $rtStructs = array('groups' => getRectypeGroups(),
        'names' => array(),
        'pluralNames' => array(),
        'usageCount' => getRecTypeUsageCount(),
        'dtDisplayOrder' => array());
    $rtStructs['typedefs'] = array('commonFieldNames' => getRectypeColNames(),
        'commonNamesToIndex' => getColumnNameToIndex(getRectypeColNames()),
        'dtFieldNamesToIndex' => getColumnNameToIndex(getRectypeStructureFieldColNames()),
        'dtFieldNames' => getRectypeStructureFieldColNames());
    if($res)
    while ($row = mysql_fetch_row($res)) {
        if (!array_key_exists($row[0], $rtStructs['typedefs'])) {
            $rtStructs['typedefs'][$row[0]] = array('dtFields' => array($row[1] => array_slice($row, 2)));
            $rtStructs['dtDisplayOrder'][$row[0]] = array();
        } else {
            $rtStructs['typedefs'][$row[0]]['dtFields'][$row[1]] = array_slice($row, 2);
        }
        array_push($rtStructs['dtDisplayOrder'][$row[0]], $row[1]);
    }

    $rtnames = getRectypeColNames();
    $rt_groupid_idx = array_search("rty_RecTypeGroupID", $rtnames) + 3;

    // get rectypes ordered by the RecType Group order, then by Group Name, then by rectype order in group and then by rectype name
    $query = "select rty_ID, rtg_ID, rtg_Name, " . join(",", getRectypeColNames());
    $query = preg_replace("/rty_ConceptID/", "", $query);
    if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
        $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID";
    } else {
        $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), '') as rty_ConceptID";
    }
    $query.= " from defRecTypes left join defRecTypeGroups  on rtg_ID = rty_RecTypeGroupID" . " order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name";

    $res = mysql_query($query);
    while ($row = mysql_fetch_row($res)) {
        $rtID = $row[0];

        $gidx = @$rtStructs['groups']['groupIDToIndex'][$row[1]];
        if( $row[1]==null || !@$rtStructs['groups'][$gidx]){
            $gidx = 0;
            $row[$rt_groupid_idx] = $rtStructs['groups'][$gidx]['id'];
        }

        array_push($rtStructs['groups'][$gidx]['allTypes'], $rtID);
        if ($row[14]) { //rty_ShowInList
            array_push($rtStructs['groups'][$gidx]['showTypes'], $rtID);
        }
        $commonFields = array_slice($row, 3);

        $commonFields = makeTitleMaskHumanReadable($commonFields, $rtID);

        $rtStructs['typedefs'][$row[0]]['commonFields'] = $commonFields;
        $rtStructs['names'][$row[0]] = $row[3];
        $rtStructs['pluralNames'][$row[0]] = $row[8];
    }

    $rtStructs['constraints'] = getAllRectypeConstraint();
    setCachedData($cacheKey, $rtStructs);
    return $rtStructs;
}

/*
* special behaviour for rty_TitleMask
* it is stored as concept codes - need to convert it to human readable string
*/
function makeTitleMaskHumanReadable($fields, $rtID)
{
    $cols = getRectypeColNames();
    $ind1 = array_search('rty_TitleMask', $cols);
    $ind2 = array_search('rty_CanonicalTitleMask', $cols);
    //$ind2 = array_search('rty_Type', $cols);
    if($ind2){
        $fields[$ind2] = $fields[$ind1]; //keep canonical
    }
    $fields[$ind1] = titlemask_make($fields[$ind1], $rtID, 2, null, _ERR_REP_SILENT);
    return $fields;
}

//
// After addition of new record - update this value
//
/**
* get rectype usageCount and update cache value if exist
* usageCount = {rtyID:nonzero-count,...}
* @return    object non-zero usage counts indexed by rtyID
* @uses      DATABASE
* @uses      getCachedData()
* @uses      setCachedData()
* @uses      getAllRectypeStructures()
*/
function updateRecTypeUsageCount() {
    $cacheKey = DATABASE . ":AllRecTypeInfo";
    $rtStructs = getCachedData($cacheKey);
    if ($rtStructs) {
        $usageCount = getRecTypeUsageCount();
        $rtStructs['usageCount'] = $usageCount;
        //save into cache
        setCachedData($cacheKey, $rtStructs);
        return $usageCount;
    } else { //there is no cache - create entire structure
        $rtStructs = getAllRectypeStructures(true);
        return $rtStructs['usageCount'];
    }
}
/**
* get recType Group definitions
* groups = {"groupIDToIndex":{recTypeGroupID:index},
*           index:{propName:val,...},...}
* @return    array recTypeGroup definitions as array of prop:val pairs
*/
function getRectypeGroups() {
    $rtGroups = array('groupIDToIndex' => array());
    $index = 0;
    $res = mysql_query("select * from defRecTypeGroups order by rtg_Order, rtg_Name");
    while ($row = mysql_fetch_assoc($res)) {
        array_push($rtGroups, array('id' => $row["rtg_ID"], 'name' => $row["rtg_Name"], 'order' => $row["rtg_Order"], 'description' => $row["rtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
        $rtGroups['groupIDToIndex'][$row["rtg_ID"]] = $index++;
    }
    return $rtGroups;
}
/**
* get recTypeIDs by recTypeGroupID
*  types => { rtgID => {rtyIF => bShowInList,...}, ...}
* @return    object rtyIDs by rtgID lookup
*/
function getRecTypesByGroup() {
    $rectypesByGroup = array();
    // query assumes rty_RecTypeGroupID is ordered single functional group ID followed by zero or more model group ids
    $res = mysql_query("select rtg_ID,rtg_Name,rty_ID, rty_ShowInLists
        from defRecTypes left join defRecTypeGroups  on rtg_ID = rty_RecTypeGroupID
    where 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
    while ($row = mysql_fetch_assoc($res)) {
        if (!array_key_exists($row['rtg_ID'], $rectypesByGroup)) {
            $rectypesByGroup[$row['rtg_ID']] = array('name' => $row["rtg_Name"], 'types' => array());
        }
        $rectypesByGroup[$row['rtg_ID']]['types'][$row["rty_ID"]] = $row["rty_ShowInLists"];
    }
    return $rectypesByGroup;
}
/**
* get rectype usage by detailTypeID
* rectypesByDetailType = { dtyID => [rtyID,...], ...}
* @return    object rtyIDs (array) using detailType indexed by dtyID
*/
function getDetailTypeDefUsage() {
    $rectypesByDetailType = array();
    $res = mysql_query("select rst_DetailTypeID as dtID, rst_RecTypeID as rtID
    from defRecStructure order by dtID, rtID");
    while ($row = mysql_fetch_assoc($res)) {
        if (!array_key_exists($row['dtID'], $rectypesByDetailType)) {
            $rectypesByDetailType[$row['dtID']] = array();
        }
        array_push($rectypesByDetailType[$row['dtID']], $row["rtID"]);
    }
    return $rectypesByDetailType;
}
/**
* get usage counts of rectypes from the record table
* usageCount = {rtyID:nonzero-count,...}
* @return    object non-zero usage counts indexed by rtyID
*/
function getRecTypeUsageCount() {
    $recCountByRecType = array();
    $res = mysql_query("select rty_ID as rtID, count(rec_ID) as usageCnt
        from Records left join defRecTypes on rty_ID = rec_RecTypeID
    group by rec_RecTypeID");
    while ($row = mysql_fetch_assoc($res)) {
        $recCountByRecType[$row['rtID']] = $row["usageCnt"];
    }
    return $recCountByRecType;
}
/**
* get object of defined transforms grouped by owner group (user or workgroup)
* transforms = {"groupOrder" => [groupName],
*               "groups" => {groupName => [transRecID,...], ...},
*               "nameLookup" => {transformName => [transRecID,...], ...},
*               "byID" => {transRecID => {property => val,...},...}}
* @return    object transform information structured for interface use
* @uses      HEURIST_BASE_URL
* @uses      HEURIST_DBNAME
* @uses      get_user_id()
*/
function getTransformsByOwnerGroup() {
    $transRT = (defined('RT_TRANSFORM') ? RT_TRANSFORM : 0);
    $transNameDT = (defined('DT_NAME') ? DT_NAME : 0);
    $transFileDT = (defined('DT_FILE_RESOURCE') ? DT_FILE_RESOURCE : 0);
    $transTypeDT = (defined('DT_FILE_TYPE') ? DT_FILE_TYPE : 0);
    $transDT = (defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0);
    $toolRT = (defined('RT_TOOL') ? RT_TOOL : 0);
    $transformDT = (defined('DT_TRANSFORM_RESOURCE') ? DT_TRANSFORM_RESOURCE : 0);
    $colourDT = (defined('DT_COLOUR') ? DT_COLOUR : 0);
    $toolTypeDT = (defined('DT_TOOL_TYPE') ? DT_TOOL_TYPE : 0);
    $rectypeDT = (defined('DT_RECORD_TYPE') ? DT_RECORD_TYPE : 0);
    $detailTypeDT = (defined('DT_DETAIL_TYPE') ? DT_DETAIL_TYPE : 0);
    $commandDT = (defined('DT_COMMAND') ? DT_COMMAND : 0);
    $ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID', 'ugl_UserID=' . get_user_id() . ' and grp.ugr_Type != "user" order by ugl_GroupID');
    if (is_logged_in()) {
        array_push($ACCESSABLE_OWNER_IDS, get_user_id());
        if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
            array_push($ACCESSABLE_OWNER_IDS, 0);
        }
    }
    $transforms = array("groupOrder" => array(), "groups" => array(), "nameLookup" => array(), "byID" => array());
    $query = 'select rec_ID,' .
    ' if(ugr_Type="workgroup", ugr_Name,if(ugr_id = ' . get_user_id() . ',"personal",concat(ugr_FirstName," ",ugr_LastName))) as grpName,' .
    ' if(ugr_id = ' . get_user_id() . ',0, if(ugr_id = 0,1,2)) as dispOrder,' .
    ' dtname.dtl_Value as lbl, ulf_ExternalFileReference as uri, ulf_ObfuscatedFileID as fileID,' .
    ' trm_Label as typ, dttrans.dtl_Value as trans' .
    ' from Records' .
    ' left join recDetails dtname on rec_ID=dtname.dtl_RecID and dtname.dtl_DetailTypeID=' . $transNameDT .
    ' left join recDetails dtfile on rec_ID=dtfile.dtl_RecID and dtfile.dtl_DetailTypeID=' . $transFileDT .
    ' left join recUploadedFiles on dtfile.dtl_UploadedFileID = ulf_ID' .
    ' left join recDetails dttrantyp on rec_ID=dttrantyp.dtl_RecID and dttrantyp.dtl_DetailTypeID=' . $transTypeDT .
    ' left join defTerms on dttrantyp.dtl_Value = trm_ID' .
    ' left join recDetails dttrans on rec_ID=dttrans.dtl_RecID and dttrans.dtl_DetailTypeID=' . $transDT .
    ' left join sysUGrps on ugr_ID=rec_OwnerUGrpID' .
    ' where rec_RecTypeID=' . $transRT;
    if($ACCESSABLE_OWNER_IDS && count($ACCESSABLE_OWNER_IDS)>0){
        $query = $query.' and (rec_OwnerUGrpID in (' . join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' . 'NOT rec_NonOwnerVisibility = "hidden")';
    }else{
        $query = $query.' and (NOT rec_NonOwnerVisibility = "hidden")';
    }
    $query = $query.' order by dispOrder, grpName, lbl';
    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $transRecID = $row['rec_ID'];
        $uri = (@$row['uri'] ? $row['uri'] : (@$row['fileID'] ? HEURIST_BASE_URL . "records/files/downloadFile.php?db=" . HEURIST_DBNAME . "&ulf_ID=" . $row['fileID'] : null));
        if (!$uri) {
            continue;
        }
        if (!@$transforms["groups"][$row['grpName']]) {
            $transforms["groups"][$row['grpName']] = array($transRecID);
            array_push($transforms["groupOrder"], $row['grpName']);
        } else {
            array_push($transforms["groups"][$row['grpName']], $transRecID);
        }
        $transforms["nameLookup"][$row['lbl']] = $transRecID;
        $transforms["byID"][$transRecID] = array("label" => $row['lbl'], "uri" => $uri, "type" => $row['typ'], "trans" => (@$row['trans'] ? $row['trans'] : null));
    }
    return $transforms;
}
/**
* get object of tools with lookup by toolRecID and by transformRecID
* tools = {"byTransform" => {transRecID => [toolRecID,...], ...},
*          "byID" => {toolRecID => {property => val,...},...}}
* @return    object tool information lookup by toolRecID and transRecID
* @uses      HEURIST_BASE_URL
* @uses      HEURIST_DBNAME
* @uses      get_user_id()
*/
function getToolsByTransform() {
    $toolRT = (defined('RT_TOOL') ? RT_TOOL : 0);
    $toolNameDT = (defined('DT_NAME') ? DT_NAME : 0);
    $toolIconDT = (defined('DT_THUMBNAIL') ? DT_THUMBNAIL : 0);
    $colourDT = (defined('DT_COLOUR') ? DT_COLOUR : 0);
    $toolTransDT = (defined('DT_TRANSFORM_RESOURCE') ? DT_TRANSFORM_RESOURCE : 0);
    $rectypeDT = (defined('DT_RECORD_TYPE') ? DT_RECORD_TYPE : 0);
    $detailTypeDT = (defined('DT_DETAIL_TYPE') ? DT_DETAIL_TYPE : 0);
    $toolDtValueDT = (defined('DT_TOOL_TYPE') ? DT_TOOL_TYPE : 0);
    $commandDT = (defined('DT_COMMAND') ? DT_COMMAND : 0);
    $ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID', 'ugl_UserID=' . get_user_id() . ' and grp.ugr_Type != "user" order by ugl_GroupID');
    if (is_logged_in()) {
        array_push($ACCESSABLE_OWNER_IDS, get_user_id());
        if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
            array_push($ACCESSABLE_OWNER_IDS, 0);
        }
    }
    $tools = array("byTransform" => array(), "byId" => array());
    $query = 'select rec_ID, dtname.dtl_Value as name, ulf_ExternalFileReference as uri, ulf_ObfuscatedFileID as fileID,' .
    ' clrTrm.trm_Label as colour, dttype.dtl_Value as dt, rttype.dtl_Value as rt, dtv.trm_Label as value,' .
    ' cmd.dtl_Value as command' .
    ' from Records' .
    ' left join recDetails dtname on rec_ID=dtname.dtl_RecID and dtname.dtl_DetailTypeID=' . $toolNameDT .
    ' left join recDetails dtIcon on rec_ID=dtIcon.dtl_RecID and dtIcon.dtl_DetailTypeID=' . $toolIconDT .
    ' left join recUploadedFiles on dtIcon.dtl_UploadedFileID = ulf_ID' .
    ' left join recDetails clr on rec_ID=clr.dtl_RecID and clr.dtl_DetailTypeID=' . $colourDT .
    ' left join defTerms clrTrm on clr.dtl_Value = clrTrm.trm_ID' .
    ' left join recDetails rttype on rec_ID=rttype.dtl_RecID and rttype.dtl_DetailTypeID=' . $rectypeDT .
    ' left join recDetails dttype on rec_ID=dttype.dtl_RecID and dttype.dtl_DetailTypeID=' . $detailTypeDT .
    ' left join recDetails dtValue on rec_ID=dtValue.dtl_RecID and dtValue.dtl_DetailTypeID=' . $toolDtValueDT .
    ' left join defTerms dtv on dtValue.dtl_Value = dtv.trm_ID' .
    ' left join recDetails cmd on rec_ID=cmd.dtl_RecID and cmd.dtl_DetailTypeID=' . $commandDT .
    ' where rec_RecTypeID=' . $toolRT;

    if($ACCESSABLE_OWNER_IDS && count($ACCESSABLE_OWNER_IDS)>0){
        $query = $query.' and (rec_OwnerUGrpID in (' . join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' . 'NOT rec_NonOwnerVisibility = "hidden")';
    }else{
        $query = $query.' and (NOT rec_NonOwnerVisibility = "hidden")';
    }
    $query = $query.' order by name';
    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $toolRecID = $row['rec_ID'];
        $tools["byId"][$toolRecID] = array("name" => $row['name'],
            "recID" => $row['rec_ID'],
            "img" => (@$row['uri'] ? $row['uri'] : (@$row['fileID'] ? (HEURIST_BASE_URL . "records/files/downloadFile.php?db=" . HEURIST_DBNAME . "&ulf_ID=" . $row['fileID']) : null)),
            "colour" => $row['colour'],
            "dt" => $row['dt'],
            "rt" => $row['rt'],
            "value" => $row['value'],
            "command" => $row['command'],
            "trans" => mysql__select_array("recDetails", "dtl_Value", ("dtl_RecID=" . $row['rec_ID'] . " and dtl_DetailTypeID=" . $toolTransDT)));
        foreach ($tools["byId"][$toolRecID]["trans"] as $transRecID) {
            if (!array_key_exists($transRecID, $tools["byTransform"])) {
                $tools["byTransform"][$transRecID] = array($toolRecID);
            } else if (!in_array($toolRecID, $tools["byTransform"][$transRecID])) {
                array_push($tools["byTransform"][$transRecID], $toolRecID);
            }
        }
    }
    return $tools;
}
/**
* get detailType usage counts
* @return    array usage counts index by detailTypeID
* @link      URL
*/
function getDetailTypeUsageCount() {
    $useCntByDetailTypeID = array();
    $res = mysql_query("select dty_ID as dtID, count(dtl_ID) as usageCnt ".
        " from recDetails left join defDetailTypes on dty_ID = dtl_DetailTypeID".
        " group by dtl_DetailTypeID");
    while ($row = mysql_fetch_assoc($res)) {
        $useCntByDetailTypeID[$row['dtID']] = $row["usageCnt"];
    }
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
function getDtLookups() {
    return array("enum" => "Terms list",
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
* @uses      getColumnNameToIndex()
* @uses      getRectypeStructureFieldColNames()
* @uses      getDetailTypeGroups()
* @uses      getDetailTypeUsageCount()
* @uses      getDetailTypeDefUsage()
* @uses      getDtLookups()
* @uses      getCachedData()
* @uses      setCachedData()
*/
function getAllDetailTypeStructures($useCachedData = false) {
    global $dbID;
    $cacheKey = DATABASE . ":AllDetailTypeInfo";
    if ($useCachedData) {
        $dtStructs = getCachedData($cacheKey);
        if ($dtStructs) {
            return $dtStructs;
        }
    }
    $dtG = getDetailTypeGroups();
    $dtStructs = array('groups' => $dtG,
        'names' => array(),
        'rectypeUsage' => getDetailTypeDefUsage(),
        'usageCount' => getDetailTypeUsageCount(),
        'typedefs' => array('commonFieldNames' => getDetailTypeColNames(),
            'fieldNamesToIndex' => getColumnNameToIndex(getDetailTypeColNames())),
        'lookups' => getDtLookups());
    $query = "select dtg_ID, dtg_Name, " . join(",", getDetailTypeColNames());
    $query = preg_replace("/dty_ConceptID/", "", $query);
    if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
        $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(dty_ID as char(5)))) as dty_ConceptID";
    } else {
        $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), '') as dty_ConceptID";
    }
    $query.= " from defDetailTypes left join defDetailTypeGroups  on dtg_ID = dty_DetailTypeGroupID" . " order by dtg_Order, dtg_Name, dty_OrderInGroup, dty_Name";
    $res = mysql_query($query);
    $dtStructs['sortedNames'] = mysql__select_assoc('defDetailTypes', 'dty_Name', 'dty_ID', '1 order by dty_Name');
    while ($row = mysql_fetch_row($res)) {
        array_push($dtStructs['groups'][$dtG['groupIDToIndex'][$row[0]]]['allTypes'], $row[2]);
        if ($row[17]) {// dty_ShowInLists
            array_push($dtStructs['groups'][$dtG['groupIDToIndex'][$row[0]]]['showTypes'], $row[2]);
        }
        $dtStructs['typedefs'][$row[2]]['commonFields'] = array_slice($row, 2);
        $dtStructs['names'][$row[2]] = $row[3];
    }
    setCachedData($cacheKey, $dtStructs);
    return $dtStructs;
}
/**
* get detailType Group definitions
* groups = {"groupIDToIndex":{detailTypeGroupID:index},
*           index:{propName:val,...},...}
* @return    object detailTypeGroup definitions as array of prop:val pairs indexed by dtgID
*/
function getDetailTypeGroups() {
    $dtGroups = array('groupIDToIndex' => array());
    $index = 0;
    $res = mysql_query("select * from defDetailTypeGroups order by dtg_Order, dtg_Name");
    while ($row = mysql_fetch_assoc($res)) {
        array_push($dtGroups, array('id' => $row["dtg_ID"], 'name' => $row["dtg_Name"], 'order' => $row["dtg_Order"], 'description' => $row["dtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
        $dtGroups['groupIDToIndex'][$row["dtg_ID"]] = $index++;
    }
    return $dtGroups;
}
/**
* determine the inverse of a relationship term
* @global    array llokup of term inverses by trmID to inverseTrmID
* @param     int $relTermID reltionship trmID
* @return    int inverse trmID
* @todo      modify to retrun -1 in case not inverse defined
*/
function reltype_inverse($relTermID) { //saw Enum change - find inverse as an id instead of a string
    global $inverses;
    if (!$relTermID) return;
    if (!$inverses) {
        $inverses = mysql__select_assoc("defTerms A left join defTerms B on B.trm_ID=A.trm_InverseTermID", "A.trm_ID", "B.trm_ID", "A.trm_Label is not null and B.trm_Label is not null");
    }
    $inverse = @$inverses[$relTermID];
    if (!$inverse) $inverse = array_search($relTermID, $inverses);//do an inverse search and return key.
    if (!$inverse) $inverse = 'Inverse of ' . $relTermID; //FIXME: This should be -1 indicating no inverse found.
    return $inverse;
}
$relRT = (defined('RT_RELATION') ? RT_RELATION : 0);
$relTypDT = (defined('DT_RELATION_TYPE') ? DT_RELATION_TYPE : 0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE') ? DT_PRIMARY_RESOURCE : 0);
$relTrgDT = (defined('DT_TARGET_RESOURCE') ? DT_TARGET_RESOURCE : 0);
$intrpDT = (defined('DT_INTERPRETATION_REFERENCE') ? DT_INTERPRETATION_REFERENCE : 0);
$notesDT = (defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0);
$startDT = (defined('DT_START_DATE') ? DT_START_DATE : 0);
$endDT = (defined('DT_END_DATE') ? DT_END_DATE : 0);
$titleDT = (defined('DT_NAME') ? DT_NAME : 0);
/**
* get related record structure for a give relationship record
* related = { "recID" => recID,
*             "RelTermID" => relationTrmID,
*             "RelTerm" => trmLabel,
*             "ParentTermID" => parentTrmID,
*             "RelatedRecID" => linkedRecID,
*             "InterpRecID" => interpretationRecID,
*             "Notes" => relationshipNotes,
*             "Title" => relationshipTitle,
*             "StartData" => relationshipStartDate,
*             "EndDate" => relationshipEndDate}
*
* @global    int $relTypDT local id of relationtype detailType (magic number)
* @global    int $relSrcDT local id of source record pointer detailType (magic number)
* @global    int $relTrgDT local id of target record pointer detailType (magic number)
* @global    int $intrpDT local id of interpretation record pointer detailType (magic number)
* @global    int $notesDT local id of notes detailType (magic number)
* @global    int $startDT local id of start time detailType (magic number)
* @global    int $endDT local id of end time detailType (magic number)
* @global    int $titleDT local id of title detailType (magic number)
* @param     int $recID relationshipRecID to use for related
* @param     boolean $i_am_primary true if context is "from" record of relationship link
* @return    object related record structure
* @todo      change $i_am_primary to useInverseRelation
*/
function fetch_relation_details($recID, $i_am_primary) {
    global $relTypDT, $relSrcDT, $relTrgDT, $intrpDT, $notesDT, $startDT, $endDT, $titleDT;
    /* get recDetails for the given linked resource and extract all the necessary values */
    $res = mysql_query('select * from recDetails where dtl_RecID = ' . $recID);
    $bd = array('recID' => $recID);
    while ($row = mysql_fetch_assoc($res)) {
        switch ($row['dtl_DetailTypeID']) {
            case $relTypDT: //saw Enum change - added RelationValue for UI
                if ($i_am_primary) {
                    $bd['RelTermID'] = $row['dtl_Value'];
                } else {
                    $bd['RelTermID'] = reltype_inverse($row['dtl_Value']); // BUG: assumes reltype_inverse returns ID
                    //TODO: saw this should have a -1 which is different than self inverse and the RelTerm should be "inverse of ". term label requires checking smarty/showReps
                }
                $relval = mysql_fetch_assoc(mysql_query('select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' . intval($bd['RelTermID'])));
                $bd['RelTerm'] = $relval['trm_Label'];
                if ($relval['trm_ParentTermID']) {
                    $bd['ParentTermID'] = $relval['trm_ParentTermID'];
                }
                break;
            case $relTrgDT: // linked resource
                if (!$i_am_primary) break;
                $r = mysql_query('select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                    ' from Records where rec_ID = ' . intval($row['dtl_Value']));
                $bd['RelatedRecID'] = mysql_fetch_assoc($r);
                break;
            case $relSrcDT:
                if ($i_am_primary) break;
                $r = mysql_query('select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                    ' from Records where rec_ID = ' . intval($row['dtl_Value']));
                $bd['RelatedRecID'] = mysql_fetch_assoc($r);
                break;
            case $intrpDT:
                $r = mysql_query('select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                    ' from Records where rec_ID = ' . intval($row['dtl_Value']));
                $bd['InterpRecID'] = mysql_fetch_assoc($r);
                break;
            case $notesDT:
                $bd['Notes'] = $row['dtl_Value'];
                break;
            case $titleDT:
                $bd['Title'] = $row['dtl_Value'];
                break;
            case $startDT:
                $bd['StartDate'] = $row['dtl_Value'];
                break;
            case $endDT:
                $bd['EndDate'] = $row['dtl_Value'];
                break;
        }
    }
    return $bd;
}
/**
* get related records structure
*  relations = {"byRectype" => { rtyID =>{trmID =>[recID,...]},...},
*               "byTerm" => { trmID => { rtyID =>[recID,...]},...},
*               "relationshipRecs" => { relnRecID => { "recID" => val,
*                                                      "relInvTerm" => val,
*                                                      "relInvTermID" => val,
*                                                      "relTerm" => val,
*                                                      "relTermID" => val,
*                                                      "relatedRec" => { "title" => val,
*                                                                        "recID" => val,
*                                                                        "rectype" => val,
*                                                                        "URL" => val,
*                                                                      }
*                                                      "relnID" => val,
*                                                      "role" => val,
*                                                      "title" => val},...}}
*
* @global    int $relTypDT local id of relationtype detailType (magic number)
* @global    int $relSrcDT local id of source record pointer detailType (magic number)
* @global    int $relTrgDT local id of target record pointer detailType (magic number)
* @global    int $intrpDT local id of interpretation record pointer detailType (magic number)
* @global    int $notesDT local id of notes detailType (magic number)
* @global    int $startDT local id of start time detailType (magic number)
* @global    int $endDT local id of end time detailType (magic number)
* @global    int $titleDT local id of title detailType (magic number)
* @staticvar type [$varname] description of static variable usage in function
* @param     int $recID context record recID for which to find related records
* @param     int $relnRecID relationshipRecID used to get a specific related record default=0 for find all related
* @return    object related records structure
*/
function getAllRelatedRecords($recID, $relnRecID = 0) {
    global $relRT, $relTypDT, $relSrcDT, $relTrgDT, $intrpDT, $notesDT, $startDT, $endDT, $titleDT;
    if (!$recID) return null;
    $query = "select relnID, src.dtl_Value as src, srcRec.rec_RecTypeID as srcRT, srcRec.rec_Title as srcTitle, srcRec.rec_URL as srcURL, trg.dtl_Value as trg," .
    " if(srcRec.rec_ID = $recID, 'Primary', 'Non-Primary') as role, trgRec.rec_RecTypeID as trgRT, trgRec.rec_Title as trgTitle," .
    " trgRec.rec_URL as trgURL, trm.dtl_Value as trmID, term.trm_Label as term, inv.trm_ID as invTrmID," .
    " if(inv.trm_ID>0, inv.trm_Label, term.trm_Label) as invTrm, rlnTtl.dtl_Value as title," .    //concat('inverse of ', term.trm_Label)
    " rlnNote.dtl_Value as note, strDate.dtl_Value as strDate, endDate.dtl_Value as endDate, intrpRec.rec_ID as intrp," .
    " intrpRec.rec_RecTypeID as intrpRT, intrpRec.rec_Title as intrpTitle, intrpRec.rec_URL as intrpURL" .
    " from (select rrc_RecID as relnID from recRelationshipsCache) rels" .
    " left join recDetails src on src.dtl_RecID = rels.relnID and src.dtl_DetailTypeID = $relSrcDT" .
    " left join Records srcRec on src.dtl_Value = srcRec.rec_ID" .
    " left join recDetails trg on trg.dtl_RecID = rels.relnID and trg.dtl_DetailTypeID = $relTrgDT" .
    " left join Records trgRec on trg.dtl_Value = trgRec.rec_ID" .
    " left join recDetails trm on trm.dtl_RecID = rels.relnID and trm.dtl_DetailTypeID = $relTypDT" .
    " left join defTerms term on term.trm_ID = trm.dtl_Value" .
    " left join defTerms inv on inv.trm_ID = term.trm_InverseTermID" .
    " left join recDetails intrp on intrp.dtl_RecID = rels.relnID and intrp.dtl_DetailTypeID = $intrpDT" .
    " left join Records intrpRec on intrp.dtl_Value = intrpRec.rec_ID" .
    " left join recDetails rlnTtl on rlnTtl.dtl_RecID = rels.relnID and rlnTtl.dtl_DetailTypeID = $titleDT" .
    " left join recDetails rlnNote on rlnNote.dtl_RecID = rels.relnID and rlnNote.dtl_DetailTypeID = $notesDT" .
    " left join recDetails strDate on strDate.dtl_RecID = rels.relnID and strDate.dtl_DetailTypeID = $startDT" .
    " left join recDetails endDate on endDate.dtl_RecID = rels.relnID and endDate.dtl_DetailTypeID = $endDT" .
    " where (srcRec.rec_ID = $recID or trgRec.rec_ID = $recID)";
    if ($relnRecID) $query.= " and rels.relnID = $relnRecID";
    
//error_log($query);    
    $res = mysql_query($query);
    if (!$res || !mysql_num_rows($res)) {
        return array();
    }
    //if (@$res && mysql_error(@$res)) {
    if (mysql_error()) {
        return array("error" => mysql_error());
    }
    
    $relations = array('relationshipRecs' => array());
    while ($row = mysql_fetch_assoc($res)) {
        $relnRecID = $row["relnID"];
        $relations['relationshipRecs'][$relnRecID] = array("relnID" => $relnRecID,
            "title" => $row['title'],
            "recID" => $recID,
            "role" => $row['role'],
            "relTermID" => $row['trmID'],
            "relTerm" => $row['term'],
            "relInvTerm" => $row['invTrm']);
        if (@$row['invTrmID']) {
            $relations['relationshipRecs'][$relnRecID]["relInvTermID"] = $row['invTrmID'];
        }
        if (@$row['note']) {
            $relations['relationshipRecs'][$relnRecID]["notes"] = $row['note'];
        }
        if (@$row['strDate']) {
            $relations['relationshipRecs'][$relnRecID]["startDate"] = $row['strDate'];
        }
        if (@$row['endDate']) {
            $relations['relationshipRecs'][$relnRecID]["endDate"] = $row['endDate'];
        }
        if (@$row['intrp']) {
            $relations['relationshipRecs'][$relnRecID]["interpRec"] = array("title" => $row["intrpTitle"],
                "rectype" => $row["intrpRT"],
                "URL" => $row["intrpURL"],
                "recID" => $row["intrp"]);
        }
        if ($row['src'] == $recID) {
            $relations['relationshipRecs'][$relnRecID]["relatedRec"] = array("title" => $row["trgTitle"],
                "rectype" => $row["trgRT"],
                "URL" => $row["trgURL"],
                "recID" => $row["trg"]);
        } else {
            $relations['relationshipRecs'][$relnRecID]["relatedRec"] = array("title" => $row["srcTitle"],
                "rectype" => $row["srcRT"],
                "URL" => $row["srcURL"],
                "recID" => $row["src"]);
        }
    }

    foreach ($relations['relationshipRecs'] as $relnRecID => $reln) {
        $relRT = $reln['relatedRec']['rectype'];
        $relRecID = $reln['relatedRec']['recID'];
        $relTermID = $reln['relTermID'];
        if (!array_key_exists('byRectype', $relations)) {
            $relations['byRectype'] = array();
        }
        if (!array_key_exists($relRT, $relations['byRectype'])) {
            $relations['byRectype'][$relRT] = array();
        }
        if (!array_key_exists($relTermID, $relations['byRectype'][$relRT])) {
            $relations['byRectype'][$relRT][$relTermID] = array($relnRecID);
        } else {
            array_push($relations['byRectype'][$relRT][$relTermID], $relnRecID);
        }
        if (!array_key_exists('byTerm', $relations)) {
            $relations['byTerm'] = array();
        }
        if (!array_key_exists($relTermID, $relations['byTerm'])) {
            $relations['byTerm'][$relTermID] = array();
        }
        if (!array_key_exists($relRT, $relations['byTerm'][$relTermID])) {
            $relations['byTerm'][$relTermID][$relRT] = array($relnRecID);
        } else {
            array_push($relations['byTerm'][$relTermID][$relRT], $relnRecID);
        }
    }
    return $relations;
}
/*no carriage returns after closing script tags please, it breaks xml script genenerator that uses this file as include */
?>
