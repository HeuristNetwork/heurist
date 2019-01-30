<?php
//legacy of h3 - used in reportRecord and renderRecordData
//@todo urgent 1) use recLinks  2) move to db_recsearch use recordGetRelationship?


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
    
    global $system, $relTypDT, $relSrcDT, $relTrgDT, $intrpDT, $notesDT, $startDT, $endDT, $titleDT;
    
    /* get recDetails for the given linked resource and extract all the necessary values */
    $mysqli = $system->get_mysqli();
    $res = $mysqli->query('select * from recDetails where dtl_RecID = ' . $recID);
    
    $bd = array('recID' => $recID);
    if($res){
        while ($row = $res->fetch_assoc()) {
            
        switch ($row['dtl_DetailTypeID']) {
            case $relTypDT: //saw Enum change - added RelationValue for UI
                if ($i_am_primary) {
                    $bd['RelTermID'] = $row['dtl_Value'];
                } else {
                    $bd['RelTermID'] = reltype_inverse($row['dtl_Value']); // BUG: assumes reltype_inverse returns ID
                    //TODO: saw this should have a -1 which is different than self inverse and the RelTerm should be "inverse of ". term label requires checking smarty/showReps
                }
                $relval = mysql__select_row_assoc($mysqli, 
                        'select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' . intval($bd['RelTermID']));
                if($relval!=null){
                    $bd['RelTerm'] = $relval['trm_Label'];
                    if ($relval['trm_ParentTermID']) {
                        $bd['ParentTermID'] = $relval['trm_ParentTermID'];
                    }
                }
                break;
            case $relTrgDT: // linked resource
                if (!$i_am_primary) break;

                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                break;
            case $relSrcDT:
                if ($i_am_primary) break;
                
                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                
                break;
            case $intrpDT:
                
                $bd['InterpRecID'] = mysql__select_row_assoc($mysqli,
                                    'select rec_ID, rec_Title, rec_RecTypeID, rec_URL'.
                                    ' from Records where rec_ID = ' . intval($row['dtl_Value']) );
                
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
        $res->close();
    }
    
    return $bd;
}
?>
