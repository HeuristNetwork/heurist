<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* actionMethods.php
* manipulations for bookmarks, tags and user/group access/visibility
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Search
*/

  require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
  require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');

  if (!@$ACCESSABLE_OWNER_IDS) {
    $ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID', 'ugl_UserID=' . get_user_id() . ' and grp.ugr_Type != "user" order by ugl_GroupID');
    array_push($ACCESSABLE_OWNER_IDS, get_user_id());
    if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
        array_push($ACCESSABLE_OWNER_IDS, 0);
    }
  }

  /**
  *
  *
  * @param mixed $data
  */
  function add_detail($data) {
    global $ACCESSABLE_OWNER_IDS;

    $result = array();
    if (!((@$data['recIDs'] || @$data['rtyID']) && @$data['dtyID'] && (@$data['val'] || @$data['geo'] || @$data['ulfID']))){
      $result['problem'] = "Data problem: insufficent data passed to add_detail (field/value) service";
      return result;
    }
    //normalize recIDs to an array for code below
    $recIDs = $data['recIDs'];
    if ($recIDs && ! is_array($recIDs)){
      $recIDs = array($recIDs);
    }
    $passedRecIDCnt = count(@$recIDs);
    if ($passedRecIDCnt) {//check editable access for passed records
      $recIDs = mysql__select_array('Records','rec_ID',"rec_ID in (".join(",",$recIDs).") and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $inAccessableRecCnt = $passedRecIDCnt - count(@$recIDs);
    }
    // user chose add by rectype not recIDs so calc recID set
    if (!$passedRecIDCnt && $data['rtyID']) {
      $rtyID = $data['rtyID'];
      if (is_array($rtyID)){
        $rtyID = $rtyID[0];// limit to single type for now
      }
      $recIDs = mysql__select_array('Records','rec_ID',"rec_RecTypeID = $rtyID and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $totalRecTypeCnt = mysql_num_rows(mysql_query("select * from Records where rec_RecTypeID = $rtyID"));
      $inAccessableRecCnt = $totalRecTypeCnt - count($recIDs);
    }
    $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                              'rtyRecs'=> (@$totalRecTypeCnt?$totalRecTypeCnt:0),
                              'noAccess'=> (@$inAccessibleRecCnt?$inAccessibleRecCnt:0));


    if (count($recIDs) == 0){
      $result['none'] = "No editable records found in current set". ($inAccessableRecCnt ? " ($inAccessableRecCnt inaccessable records).":".");
      return result;
    }else{
      $rtyIDs = mysql__select_array('Records','distinct(rec_RecTypeID)',"rec_ID in (".join(",",$recIDs).")");
    }
    $dtyID = $data['dtyID'];
    $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");
    $rtyLimits = mysql__select_assoc("defRecStructure","rst_RecTypeID","rst_MaxValues","rst_DetailTypeID = $dtyID and rst_RecTypeID in (".join(",",$rtyIDs).")");

    $now = date('Y-m-d H:i:s');
    $dtl = Array('dtl_DetailTypeID'  => $dtyID,
                  'dtl_Modified'  => $now);
    $baseTag = "~add field $dtyName $now";
    if(@$data['val']){
      $dtl['dtl_Value'] = $data['val'];
    }
    if(@$data['geo']){
      $dtl['dtl_Geo'] = array("geomfromtext(\"" . $data['geo'] . "\")");
    }
    if(@$data['ulfID']){
      $dtl['dtl_UploadedFileID'] = $data['ulfID'];
    }
    $undefinedFieldsRecIDs = array();
    $processedRecIDs = array();
    $limittedRecIDs = array();
    $insertErrors = array();

    mysql_connection_overwrite(DATABASE);
    foreach ($recIDs as $recID) {
      //check field limit for this record
      $query = "select rec_RecTypeID, tmp.cnt from Records ".
                "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
                            "from recDetails ".
                            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
                "where rec_ID = $recID";

      $res = mysql_query($query);
      $row = mysql_fetch_row($res);

      if (!array_key_exists($row[0],$rtyLimits)) {
          array_push($undefinedFieldsRecIDs, $recID);
          continue;
      }else if (intval($rtyLimits[$row[0]])>0 && $row[1]>0 && ($rtyLimits[$row[0]] - $row[1]) < 1){
          array_push($limittedRecIDs, $recID);
          continue;
      }
      //limit ok so insert field
      $dtl['dtl_RecID'] = $recID;
      mysql__insert('recDetails', $dtl);
      if (mysql_error()) {
        $insertErrors[$recID] = "MySQL error while inserting field type $dtyName for record ($recID): ". mysql_error();
        continue;
      }
      array_push($processedRecIDs, $recID);
    }
    if (count($processedRecIDs)){
      $resIndex = 'ok';
      $processedTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $processedRecIDs, 'tagString' => $baseTag));
      $result[$resIndex] = "Added field type $dtyName to ".count($processedRecIDs). " Record(s).\n\n";
      $result['count']['processed'] = count($processedRecIDs);
      $result['added'] = array('recIDs' => $processedRecIDs, 'queryString' => "tag:\"$baseTag\"", 'tagResults' => $processedTagResult);
      updateRecTitles($processedRecIDs);
    }
    if (@$inAccessibleRecCnt > 0) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $result[$resIndex] .= "Skipped ".$inAccessibleRecCnt. " inaccessible Record(s) from selected action scope.\n\n";
    }
    if (count($undefinedFieldsRecIDs)) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $undefinedFieldsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $undefinedFieldsRecIDs, 'tagString' => $baseTag." undefined"));
      $result[$resIndex] .= "Skipped undefined field type $dtyName for ".count($undefinedFieldsRecIDs). " Record(s).\n\n";
      $result['count']['notDefined'] = count($undefinedFieldsRecIDs);
      $result['notDefined'] = array('recIDs' => $undefinedFieldsRecIDs, 'queryString' => "tag:\"$baseTag undefined\"", 'tagResults' => $undefinedFieldsTagResult);
    }
    if (count($limittedRecIDs)) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $limittedFieldsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $limittedRecIDs, 'tagString' => $baseTag." limitted"));
      $result[$resIndex] .= "Exceeded repeat values limit for field $dtyName in ".count($limittedRecIDs).
        " Record(s). The additional values have not been added to these records.\n\n";
      $result['count']['limitted'] = count($limittedRecIDs);
      $result['limitted'] = array('recIDs' => $limittedRecIDs, 'queryString' => "tag:\"$baseTag limitted\"", 'tagResults' => $limittedFieldsTagResult);
    }
    if (count($insertErrors)) {
      if (!@$resIndex ) {
            $resIndex = 'problem';
            $result[$resIndex] = '';
      }
      $insertErrorsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => array_keys($insertErrors), 'tagString' => $baseTag." error"));
      $result[$resIndex] .= "Skipped insert errors on field type $dtyName for ".count($insertErrors). " Record(s).\n\n";
      $result['count']['error'] = count($insertErrors);
      $result['errors'] = array('byRecID' => $insertErrors, 'queryString' => "tag:\"$baseTag error\"", 'tagResults' => $insertErrorsTagResult);
    }
    return $result;
  }



  function replace_detail($data) {
    global $ACCESSABLE_OWNER_IDS;

    $result = array();
    if (!((@$data['recIDs'] || @$data['rtyID']) && @$data['dtyID'] && @$data['sVal'] && @$data['rVal'] )){
      $result['problem'] = "Data problem: insufficent data passed to replace_detail (filed/value) service";
      return $result;
    }
    //normalize recIDs to an array for code below
    $recIDs = @$data['recIDs'];
    if ($recIDs && ! is_array($recIDs)){
      $recIDs = array($recIDs);
    }
    $passedRecIDCnt = count(@$recIDs);
    if ($passedRecIDCnt) {//check editable access for passed records
      $recIDs = mysql__select_array('Records','rec_ID',"rec_ID in (".join(",",$recIDs).") and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $inAccessableRecCnt = $passedRecIDCnt - count(@$recIDs);
    }
    // user chose add by rectype not recIDs so calc recID set
    if (!$passedRecIDCnt && $data['rtyID']) {
      $rtyID = $data['rtyID'];
      if (is_array($rtyID)){
        $rtyID = $rtyID[0];// limit to single type for now
      }
      $recIDs = mysql__select_array('Records','rec_ID',"rec_RecTypeID = $rtyID and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $totalRecTypeCnt = mysql_num_rows(mysql_query("select * from Records where rec_RecTypeID = $rtyID"));
      $inAccessableRecCnt = $totalRecTypeCnt - count($recIDs);
    }
    $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                              'rtyRecs'=> (@$totalRecTypeCnt?$totalRecTypeCnt:0),
                              'noAccess'=> (@$inAccessibleRecCnt?$inAccessibleRecCnt:0));

    if (count($recIDs) == 0){
      $result['none'] = "No editable records found in current set". ($inAccessableRecCnt ? " ($inAccessableRecCnt inaccessable records).":".");
      return $result;
    }else{
      $rtyIDs = mysql__select_array('Records','distinct(rec_RecTypeID)',"rec_ID in (".join(",",$recIDs).")");
    }
    $dtyID = $data['dtyID'];
    $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");

    $basetype = mysql__select_array('defDetailTypes','dty_Type',"dty_ID = $dtyID");
    $basetype = $basetype[0];
    switch ($basetype) {
      case "freetext":
      case "blocktext":
        $searchClause = "dtl_Value like \"%".$data['sVal']."%\"";
        $partialReplace = true;
        break;
      case "enum":
      case "relationtype":
      case "float":
      case "integer":
      case "resource":
      case "date":
        $searchClause = "dtl_Value = \"".$data['sVal']."\"";
        $partialReplace = false;
        break;
      default:
        $result['problem'] = "Value (detail) base type problem - $basetype fields are not supported by value-replace service";
        return $result;
    }

    $nonMatchingFieldsRecIDs = array();
    $processedRecIDs = array();
    $updateErrors = array();
    $detailCnt = 0;
    $detailErrorCnt = 0;
    mysql_connection_overwrite(DATABASE);
    foreach ($recIDs as $recID) {
      //get matching detail value for record if there is one
      $query = "select dtl_ID, dtl_Value from recDetails ".
                "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
      $res = mysql_query($query);
      if (mysql_num_rows($res)==0) {
        array_push($nonMatchingFieldsRecIDs, $recID);
        continue;
      }
      //update the details
      $recDetailWasUpdated = false;
      while ($row = mysql_fetch_row($res)) {
        $dtlID = @$row[0];
        if ($partialReplace) {// need to replace sVal with rVal
          $dtlVal = @$row[1];
          $newVal = preg_replace("/".$data['sVal']."/",$data['rVal'],$dtlVal);
        }else{
          $newVal = $data['rVal'];
        }
        mysql_query("update recDetails set dtl_Value = '$newVal' where dtl_ID = $dtlID");
        if (mysql_error()) {
          $detailErrorCnt++;
          $updateErrors[$recID] = "MySQL error finding base field $dtyName for record ($recID): ". mysql_error();
          continue;
        } else {
          $recDetailWasUpdated = true;
          $detailCnt++;
        }
      }
      if ($recDetailWasUpdated) {//only put in processed if a detail was processed, obscure case when record has multiple details we record in error array also
        array_push($processedRecIDs, $recID);
      }
    }

    $now = date('Y-m-d H:i:s');
    $dtl = Array('dtl_Modified'  => $now);
    $baseTag = "~replace field $dtyName $now";

    if (count($processedRecIDs)){
      $resIndex = 'ok';
      $processedTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $processedRecIDs, 'tagString' => $baseTag));
      $result[$resIndex] = "Updated field type ($dtyID) for $detailCnt fields in ".count($processedRecIDs). " Record(s).\n\n";
      $result['count']['processed'] = count($processedRecIDs);
      $result['replaced'] = array('recIDs' => $processedRecIDs, 'queryString' => "tag=\"$baseTag\"", 'tagResults' => $processedTagResult);
      updateRecTitles($processedRecIDs);
    }
    if (@$inAccessibleRecCnt > 0) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $result[$resIndex] .= "Skipped ".$inAccessibleRecCnt. " inaccessible Record(s) from selected action scope.\n\n";
    }
    if (count($nonMatchingFieldsRecIDs)) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $nonMatchingFieldsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $nonMatchingFieldsRecIDs, 'tagString' => $baseTag." nonMatching"));
      $result[$resIndex] .= "Skipped due to no matching field type $dtyName for ".count($nonMatchingFieldsRecIDs). " Record(s).\n\n";
      $result['count']['noMatch'] = count($nonMatchingFieldsRecIDs);
      $result['nonMatching'] = array('recIDs' => $nonMatchingFieldsRecIDs, 'queryString' => "tag:\"$baseTag nonMatching\"", 'tagResults' => $nonMatchingFieldsTagResult);
    }
    if (count($updateErrors)) {
      if (!@$resIndex ) {
            $resIndex = 'problem';
            $result[$resIndex] = '';
      }
      $updateErrorsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => array_keys($updateErrors), 'tagString' => $baseTag." error"));
      $result[$resIndex] .= "Skipped update errors on field type $dtyName for $detailErrorCnt fields in ".count($updateErrors). " Record(s).\n\n";
      $result['count']['error'] = count($updateErrors);
      $result['errors'] = array('byRecID' => $updateErrors, 'queryString' => "tag:\"$baseTag error\"", 'tagResults' => $updateErrorsTagResult);
    }
    return $result;
  }


  function delete_detail($data) {
    global $ACCESSABLE_OWNER_IDS;

    $result = array();
    if (!((@$data['recIDs'] || @$data['rtyID']) && @$data['dtyID'] && (@$data['sVal'] || @$data['rAll']) )){
      $result['problem'] = "Data problem: insufficent data passed to delete_detail (field/value) service";
      return $result;
    }
    //normalize recIDs to an array for code below
    $recIDs = @$data['recIDs'];
    if ($recIDs && ! is_array($recIDs)){
      $recIDs = array($recIDs);
    }
    $passedRecIDCnt = count(@$recIDs);
    if ($passedRecIDCnt) {//check editable access for passed records
      $recIDs = mysql__select_array('Records','rec_ID',"rec_ID in (".join(",",$recIDs).") and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $inAccessableRecCnt = $passedRecIDCnt - count(@$recIDs);
    }
    // user chose add by rectype not recIDs so calc recID set
    if (!$passedRecIDCnt && $data['rtyID']) {
      $rtyID = $data['rtyID'];
      if (is_array($rtyID)){
        $rtyID = $rtyID[0];// limit to single type for now
      }
      $recIDs = mysql__select_array('Records','rec_ID',"rec_RecTypeID = $rtyID and rec_OwnerUGrpID in (".join(",",$ACCESSABLE_OWNER_IDS).")");
      $totalRecTypeCnt = mysql_num_rows(mysql_query("select * from Records where rec_RecTypeID = $rtyID"));
      $inAccessableRecCnt = $totalRecTypeCnt - count($recIDs);
    }
    $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                              'rtyRecs'=> (@$totalRecTypeCnt?$totalRecTypeCnt:0),
                              'noAccess'=> (@$inAccessibleRecCnt?$inAccessibleRecCnt:0));

    if (count($recIDs) == 0){
      $result['none'] = "No editable records found in current set". ($inAccessableRecCnt ? " ($inAccessableRecCnt inaccessable records).":".");
      return $result;
    }else{
      $rtyIDs = mysql__select_array('Records','distinct(rec_RecTypeID)',"rec_ID in (".join(",",$recIDs).")");
    }

    $dtyID = $data['dtyID'];
    $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");

    $basetype = mysql__select_array('defDetailTypes','dty_Type',"dty_ID = $dtyID");
    $basetype = $basetype[0];
    switch ($basetype) {
      case "freetext":
      case "blocktext":
      case "enum":
      case "relationtype":
      case "float":
      case "integer":
      case "resource":
      case "date":
        $searchClause = (array_key_exists("rAll",$data) || empty($data['sVal']) ? "1" : "dtl_Value = \"".$data['sVal']."\"");
        break;
      default:
        $result['problem'] = "Field type problem - $basetype fields are not supported by deletion service.";
        return $result;
    }

    $nonMatchingFieldsRecIDs = array();
    $processedRecIDs = array();
    $deleteErrors = array();
    $detailCnt = 0;
    $detailErrorCnt = 0;
    mysql_connection_overwrite(DATABASE);
    foreach ($recIDs as $recID) {
      //get matching detail value for record if there is one
      $query = "select dtl_ID, dtl_Value from recDetails ".
                "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";


      $res = mysql_query($query);
      if (mysql_num_rows($res)==0) {
        array_push($nonMatchingFieldsRecIDs, $recID);
        continue;
      }
      //delete the details
      $recDetailWasDeleted = false;
      $errorDtlIDs = array();
      $mysqlErrorDtl = array();
      while ($row = mysql_fetch_row($res)) {
        $dtlID = @$row[0];
        mysql_query("delete from recDetails where dtl_RecID = $recID and dtl_ID = $dtlID");
        if (mysql_error() || ! mysql_affected_rows()) {
          $detailErrorCnt++;
          array_push($errorDtlIDs, $dtlID);
          array_push($mysqlErrorDtl, mysql_error());
          $deleteErrors[$recID] = "MySQL error deleting base field $dtyName (dtlID=".join(",",$errorDtlIDs).") for record ($recID): ". join("- error -",$mysqlErrorDtl);
          continue;
        } else {
          $recDetailWasDeleted = true;
          $detailCnt++;
        }
      }
      if ($recDetailWasDeleted) {//only put in processed if a detail was processed, obscure case when record has multiple details we record in error array also
        array_push($processedRecIDs, $recID);
      }
    }

    $now = date('Y-m-d H:i:s');
    $dtl = Array('dtl_Modified'  => $now);
    $baseTag = "~delete field $dtyName $now";

    if (count($processedRecIDs)){
      $resIndex = 'ok';
      $processedTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $processedRecIDs, 'tagString' => $baseTag));
      $result[$resIndex] = "Deleted field type ($dtyID) for $detailCnt fields in ".count($processedRecIDs). " Record(s).\n\n";
      $result['count']['processed'] = count($processedRecIDs);
      $result['deleted'] = array('recIDs' => $processedRecIDs, 'queryString' => "tag=\"$baseTag\"", 'tagResults' => $processedTagResult);
      updateRecTitles($processedRecIDs);
    }
    if (@$inAccessibleRecCnt > 0) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $result[$resIndex] .= "Skipped ".$inAccessibleRecCnt. " inaccessible record(s) from selected action scope.\n\n";
    }
    if (count($nonMatchingFieldsRecIDs)) {
      if (!@$resIndex ) {
            $resIndex = 'none';
            $result[$resIndex] = '';
      }
      $nonMatchingFieldsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => $nonMatchingFieldsRecIDs, 'tagString' => $baseTag." nonMatching"));
      $result[$resIndex] .= "Skipped due to no matching field type $dtyName for ".count($nonMatchingFieldsRecIDs). " Record(s).\n\n";
      $result['count']['noMatch'] = count($nonMatchingFieldsRecIDs);
      $result['nonMatching'] = array('recIDs' => $nonMatchingFieldsRecIDs, 'queryString' => "tag:\"$baseTag nonMatching\"", 'tagResults' => $nonMatchingFieldsTagResult);
    }
    if (count($deleteErrors)) {
      if (!@$resIndex ) {
            $resIndex = 'problem';
            $result[$resIndex] = '';
      }
      $deleteErrorsTagResult = bookmark_and_tag_record_ids(array('rec_ids' => array_keys($deleteErrors), 'tagString' => $baseTag." error"));
      $result[$resIndex] .= "Skipped delete errors on field type $dtyName for $detailErrorCnt fields in ".count($deleteErrors). " Record(s).\n\n";
      $result['count']['error'] = count($deleteErrors);
      $result['errors'] = array('byRecID' => $deleteErrors, 'queryString' => "tag:\"$baseTag error\"", 'tagResults' => $deleteErrorsTagResult);
    }
    return $result;
  }

  function updateRecTitles ($recIDs) {
    if (!is_array($recIDs)) {
      if (is_numeric($recIDs)) {
        $recIDs = array($recIDs);
      }else{
        return false;
      }
    }
    $res = mysql_query("select rec_ID, rec_Title, rec_RecTypeID from Records".
                        " where ! rec_FlagTemporary and rec_ID in (".join(",",$recIDs).") order by rand()");
    $recs = array();
    while ($row = mysql_fetch_assoc($res)) {
      $recs[$row['rec_ID']] = $row;
    }
    $masks = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_TitleMask', '1');

    foreach ($recIDs as $recID) {
      $rtyID = $recs[$recID]['rec_RecTypeID'];
      $new_title = fill_title_mask($masks[$rtyID], $recID, $rtyID);
      mysql_query("update Records set rec_Title = '" . addslashes($new_title) . "'where rec_ID = $recID");
    }
  }

  function delete_bookmarks($data) {

    $result = array();
    $bkmk_ids = $data['bkmk_ids'];

    mysql_connection_overwrite(DATABASE);
    mysql_query('delete usrRecTagLinks from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_RecID where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
    mysql_query('delete from usrBookmarks where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
    $deleted_count = mysql_affected_rows();

    if (mysql_error()) {
      $result['problem'] = 'MySQL error: '. addslashes(mysql_error()) . ' : no bookmarks deleted';
    }else{
      $result['ok'] = "Deleted ". $deleted_count. " bookmark".($deleted_count>1?"s":"");
    }
    return $result;
  }

  function add_tags($data) {

    $result = array();
    $bkmk_ids = $data['bkmk_ids'];
    $tagString = trim($data['tagString']);

    if ($tagString) {
      mysql_connection_overwrite(DATABASE);

      $tags = get_ids_for_tags(array_filter(explode(',', $tagString)), true);
      mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
        . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
        . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
        . ' and tag_ID in (' . join(',', $tags) . ')');
      $tag_count = mysql_affected_rows();

      if (mysql_error()) {
        $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no tags added';
      } else if ($tag_count == 0) {

        $result = bookmark_and_tag_record_ids($data);

      } else if ($tag_count > 0) {

        $result['ok'] = ($tag_count.' Tags added');
      }

    } else {
      $result['problem'] = "No tags have been added";
    }

    return $result;
  }

  function add_wgTags_by_id($data) {

    $result = array();
    $rec_ids = $data['rec_ids'];
    $wgTags = $data['wgTag_ids'];

    if (count($wgTags) && count($rec_ids)) {
      mysql_connection_overwrite(DATABASE);

      mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
        . 'select rec_ID, tag_ID from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, Records '
        . ' where rec_ID in (' . join(',', $rec_ids) . ') '
        . ' and ugl_GroupID=tag_UGrpID and ugl_UserID='.get_user_id()	//make sure the user blongs to the workgroup
        . ' and tag_ID in (' . join(',', $wgTags) . ')');
      $wgTag_count = mysql_affected_rows();

      if (mysql_error()) {
        $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no workgroup tags added';
      } else if ($wgTag_count == 0) {
        $result['none'] = 'No new workgroup tags needed to be added';
      } else {
        $result['ok'] = $wgTag_count.' workgroup tags added';
      }

    }else{
      $result['problem'] = 'No workgroup tags have been added';
    }

    return $result;
  }

  function remove_wgTags_by_id($data) {

    $result = array();
    $rec_ids = $data['rec_ids'];
    $wgTags = $data['wgTag_ids'];

    $wgTag_count = 0;

    if (count($wgTags) && count($rec_ids)) {

      mysql_connection_overwrite(DATABASE);

      mysql_query('delete usrRecTagLinks from usrRecTagLinks'
        . ' left join usrTags on tag_ID = rtl_TagID'
        . ' left join '.USERS_DATABASE.'.sysUsrGrpLinks on ugl_GroupID = tag_UGrpID'
        . ' where rtl_RecID in (' . join(',', $rec_ids) . ')'
        . ' and ugl_UserID = ' . get_user_id()
        . ' and tag_ID in (' . join(',', $wgTags) . ')');
      $wgTag_count = mysql_affected_rows();

      if (mysql_error()) {
        $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no workgroup tags added';
      } else if ($wgTag_count == 0) {
        $result['none'] = 'No workgroup tags matched, none removed';
      } else {
        $result['ok'] = $wgTag_count.' workgroup tags removed';
      }

    }else{
      $result['problem'] = 'No workgroup tags have been removed';
    }

    return $result;
  }

  function remove_tags($data) {

    $result = array();
    $bkmk_ids = $data['bkmk_ids'];
    $tagString = trim($data['tagString']);

    if ($tagString) {
      mysql_connection_overwrite(DATABASE);

      $tag_count = 0;
      $tags = get_ids_for_tags(array_filter(explode(',', $tagString)), false);
      if (count($bkmk_ids)  &&  $tags  &&  count($tags)) {
        mysql_query('delete usrRecTagLinks from usrBookmarks'
          . ' left join usrRecTagLinks on rtl_RecID = bkm_RecID'
          . ' left join usrTags on tag_ID = rtl_TagID'
          . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
          . ' and tag_ID in (' . join(',', $tags) . ')'
          . ' and tag_UGrpID = bkm_UGrpID');
        $tag_count = mysql_affected_rows();
      }
      if (mysql_error()) {
        $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no tags removed';
      } else if ($tag_count == 0) {

        $result['none'] = "No tags matched, none removed";

      } else {

        $result['ok'] = ($tag_count.' Tags removed');
      }

    } else {
      $result['problem'] = "No tags removed";
    }

    return $result;
  }

  function set_ratings($data)
  {
    $result = array();
    $bkmk_ids = $data['bkmk_ids'];
    $rating = intval($data['ratings']);

    mysql_connection_overwrite(DATABASE);
    $query =  'update usrBookmarks set bkm_Rating = ' . $rating . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id();
    mysql_query($query);
    $update_count = mysql_affected_rows();

    if (mysql_error()) {
      $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : ratings not set';
    }else  if ($update_count == 0) {
      $result['none'] = "No changes made - ratings are up-to-date";
    } else {
      $result['none'] = "Ratings have been set";
    }

    return $result;
  }

  function bookmark_references($data) {

    $result = array();

    mysql_connection_overwrite(DATABASE);

    $rec_ids = record_filter($data['rec_ids']);
    $new_rec_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
      'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $rec_ids) . ')');
    //find bookmarks for given list of records
    $existing_bkmk_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
      'concat(bkm_ID,":true")', 'bkm_ID is not null and rec_ID in (' . join(',', $rec_ids) . ')');

    if ($new_rec_ids) {
      //add new bookmarks
      mysql_query('insert into usrBookmarks
        (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
        select ' . get_user_id() . ', now(), now(), rec_ID
        from Records where rec_ID in (' . join(',', $new_rec_ids) . ')');
      $inserted_count = mysql_affected_rows();
      $bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_recID in ('.join(',',$new_rec_ids).') and bkm_UGrpID = ' . get_user_id());
    } else {
      $inserted_count = -1;
    }

    if (mysql_error()) {
      $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no bookmarks added';
    }else  if ($inserted_count < 1  &&  count($existing_bkmk_ids) < 1) {

      //neither new bookmarks, nor existing ones - try to add tags in this case
      //was $result['execute'] = array('addTagsPopup', true);
      $result['none'] = 'No boomarks added (2)';

    } else {

      if(isset($bkmk_ids)){ //fresh bookmarks
        $result['execute'] = array('addRemoveTagsPopup', true, $rec_ids , $bkmk_ids);
      }else{
        $rec_ids = null;
        $bkmk_ids = null;
        $result['none'] = 'No boomarks added';
      }

    }

    return $result;
  }

  //
  //
  //
  function bookmark_and_tag_record_ids ( $data ) {

    $result = array();

    mysql_connection_overwrite(DATABASE);

    $rec_ids = record_filter($data['rec_ids']);
    $tagString = trim($data['tagString']);

    if (!$tagString) {
      $result['none'] = 'No tags selected for records.';
    }else{

      $new_rec_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
        'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $rec_ids) . ')');

      if ($new_rec_ids) {
        mysql_query('insert into usrBookmarks
          (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
          select ' . get_user_id() . ', now(), now(), rec_ID
          from Records where rec_ID in (' . join(',', $new_rec_ids) . ')');
        $inserted_count = mysql_affected_rows();
      }

      $bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_recID in ('.join(',',$rec_ids).') and bkm_UGrpID = ' . get_user_id());

      if (mysql_error()) {
        $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no bookmarks added';
      } else if (count($bkmk_ids) < 1) {
        $result['none'] = 'No bookmark found or created for selected records.';
      } else {	//we have bookmarks lets add the tags

        $tags = get_ids_for_tags(array_filter(explode(',', $tagString)), true);
        mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
          . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
          . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
          . ' and tag_ID in (' . join(',', $tags) . ') and tag_UGrpID = bkm_UGrpID');
        $tag_count = mysql_affected_rows();

        if (mysql_error()) {
          $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()) . ' : no tags added.';
        } else {
          if ($tag_count == 0) {
            $message = 'No new tags needed to be added' ;
          } else {
            $message = 'Tagged '.count($bkmk_ids). ' records';
          }
          $message .= ''.(@$inserted_count ? ' ('. $inserted_count . ' new bookmarks, ' : ' (').
          ($tag_count ? $tag_count . ' tags)' : ')');

          $result[((@$inserted_count>0 || $tag_count>0)?'ok':'none')] = $message;
        }
      }
    }
    return $result;
  }

  //
  function save_search($data) {

    $result = array();

    $wg = intval(@$data['svs_UGrpID']);
    $sID = @$data['svs_ID'];
    //$publish = $data['publish'];
    $label = @$data['svs_Name'];

    $now = date('Y-m-d');
    $cmb = Array(
      'svs_Name'     => $label,
      'svs_Query'    => @$data['svs_Query'], //urldecode
      'svs_UGrpID'   => ($wg>0?$wg:get_user_id()),
      'svs_Added'     => $now,
      'svs_Modified'  => $now);

    /* overwrites saved search with same name
    $res = mysql_query('select svs_ID, svs_UGrpID from usrSavedSearches where svs_Name="'.slash($_REQUEST['svs_Name']).'"'.
    ' and svs_UGrpID='.$cmb['svs_UGrpID']);
    $row = mysql_fetch_row($res);*/

    mysql_connection_overwrite(DATABASE);

    if ($sID) {
      /*$row ||  if ($row ) {
      $ss = intval($row[0]);
      }*/
      mysql__update('usrSavedSearches', 'svs_ID='.$sID, $cmb);
    } else {
      mysql__insert('usrSavedSearches', $cmb);
      $sID = mysql_insert_id();
    }

    if (mysql_error()) {

      $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()).' : search not saved';
    } else {// execute function in calling context insertSavedSearch(ssName, ssQuery, wg, ssID)
      $result['execute'] = array('insertSavedSearch', $data['svs_Name'], $data['svs_Query'] , $wg, $sID);

      //$onload = "location.replace('actionHandler.php?db=".HEURIST_DBNAME."'); top.HEURIST.search.insertSavedSearch('".slash($data['svs_Name'])."', '".slash($data['svs_Query'])."', ".$wg.", ".$sID.");";
      /*if ($publish) {
      $onload .= " top.location.href = top.location.href + (top.location.href.match(/\?/) ? '&' : '?') + 'pub=1&label=".$label."&sid=".$ss."'+(top.location.href.match(/db=/) ? '' : '&db=".HEURIST_DBNAME."');";
      }else{
      $onload .= ' top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'label='.$label.'&sid='.$ss.'\'+(top.location.href.match(/db=/) ? \'\' : \'&db='.HEURIST_DBNAME.'\');';
      }*/
    }
    return $result;
  }

  //
  // set access and visibility
  //
  function set_wg_and_vis($data) {

    $result = array();

    if (is_admin()) {
      $rec_ids = $data['rec_ids'];
      $wg = intval(@$data['wg_id']);
      $vis = $data['vis'];

      if (($wg == -1 ||  $wg == 0 || in_array($wg, get_group_ids()))  &&
        (in_array(strtolower($vis),array('viewable','hidden','pending','public'))))
      {
        mysql_connection_overwrite(DATABASE);

        if ($wg === 0 && $vis === 'hidden') $vis = 'viewable';
        if ($wg >= 0){
          $editable = ' rec_OwnerUGrpID = ' . $wg . ', ';
        }else{
          $editable = '';
        }

        $query = 'update Records set '.$editable.'rec_NonOwnerVisibility = "' . $vis . '"'.
        ' where rec_ID in (' . join(',', $rec_ids) . ')';

        mysql_query($query);
        if (mysql_error()) {
          $result['problem'] = 'MySQL error: ' . addslashes(mysql_error()).' : visibility not reset';
        }else{
          $result['ok'] = mysql_affected_rows().' records updated';
        }

      } else {
        $result['problem'] = 'Invalid arguments for workgoup or visibility';
      }
    } else {
      $result['problem'] = 'Permission denied for workgroup or visibility setting';
    }
    return $result;
  }

  /* not used anymore
  function print_input_form() {
  ?>
  <form action="actionHandler.php?db=<?=HEURIST_DBNAME?>" method="get" id="action_form">
  <input type="hidden" name="bkmk_ids" id="bkmk_ids" value="">
  <input type="hidden" name="bib_ids" id="bib_ids" value="">
  <input type="hidden" name="tagString" id="tagString" value="">
  <input type="hidden" name="wgTag_ids" id="wgTag_ids" value="">
  <input type="hidden" name="rating" id="rating" value="">
  <input type="hidden" name="svs_ID" id="svs_ID" value="">
  <input type="hidden" name="svs_Name" id="svs_Name" value="">
  <input type="hidden" name="svs_Query" id="svs_Query" value="">
  <input type="hidden" name="svs_UGrpID" id="svs_UGrpID" value="">
  <input type="hidden" name="publish" id="publish" value="">
  <input type="hidden" name="wg_id" id="wg_id" value="">
  <input type="hidden" name="vis" id="vis" value="">
  <input type="hidden" name="db" id="db" value="<?=HEURIST_DBNAME?>">
  <input type="hidden" name="action" id="action" value="">
  <input type="hidden" name="reload" id="reload" value="">
  </form>
  <?php
  }
  */

  function record_filter($rec_ids) {
    // return an array of only the rec_ids that exist and the user has access to (workgroup filtered)

    $wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks', 'ugl_GroupID', 'ugl_UserID='.get_user_id());
    array_push($wg_ids, 0);	// zero as record owner means owned by all
    if (! in_array(get_user_id(),$wg_ids)) {
      array_push($wg_ids, get_user_id());
    }
    $f_rec_ids = mysql__select_array('Records', 'rec_ID',
      'rec_ID in ('.join(',', array_map('intval', $rec_ids)).') and (rec_OwnerUGrpID in ('.join(',', $wg_ids).') or rec_NonOwnerVisibility = "viewable")');

    return $f_rec_ids;
  }

  // $tags - array of tag names
  //
  function get_ids_for_tags($tags, $add, $userid=null) {

    if(!$userid){
      $userid = get_user_id();
    }

    $tag_ids = array();
    foreach ($tags as $tag_name) {
      $tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
      if(strlen($tag_name)>0){

        if ( ($slashpos = strpos($tag_name, '\\')) ) {	// it's a workgroup tag
          $grp_name = substr($tag_name, 0, $slashpos);
          $tag_name = substr($tag_name, $slashpos+1);
          $res = mysql_query('select tag_ID from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks,
            '.USERS_DATABASE.'.sysUGrps grp where ugr_Type != "User" and tag_UGrpID=ugl_GroupID and '.
            'ugl_GroupID=grp.ugr_ID and ugl_UserID='.$userid.
            ' and grp.ugr_Name="'.mysql_real_escape_string($grp_name).
            '" and lower(tag_Text)=lower("'.mysql_real_escape_string($tag_name).'")');
        }
        else {
          $res = mysql_query('select tag_ID from usrTags where lower(tag_Text)=lower("'.
            mysql_real_escape_string($tag_name).'") and tag_UGrpID='.$userid);
        }

        if (mysql_num_rows($res) > 0) {
          $row = mysql_fetch_row($res);
          array_push($tag_ids, $row[0]);
        }else if ($add) {
          // non-existent tag ... add it
          $tag_name = str_replace("\\", "/", $tag_name);	// replace backslashes with forwardslashes
          $query = "insert into usrTags (tag_Text, tag_UGrpID) values (\"" . mysql_real_escape_string($tag_name) . "\", " . $userid . ")";
          mysql_query($query);
          if (mysql_error()) {
/*****DEBUG****///error_log(">>>> Erorr adding tag ".mysql_error());
          }else{
            // saw TODO: add error coding here
            array_push($tag_ids, mysql_insert_id());
          }
        }
      }
    }

    return $tag_ids;
  }
?>
