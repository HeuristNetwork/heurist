<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to import records")){
        return;
    }

    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
    require_once(dirname(__FILE__).'/../../external/php/phpZotero.php');

    define("DT_ZOTERKEY", 94);
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Zotero synchronization</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">
    </head>
    <body class="popup">r

<?php
            mysql_connection_overwrite(DATABASE);
            if(mysql_error()) {
                die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
            }

            $step = @$_REQUEST['step'];

    // 1) load mapping/config file
    $mapping_file = "zoteroMap.xml";
    $user_ID = null;
    $api_Key = null;
    $mapping_rt = array();
    $mapping_dt_errors = array();
    $mapping_rt_errors = array();
    $rep_errors_only = true;

    if(!file_exists($mapping_file) || !is_readable($mapping_file)){
        die("Sorry, could not find/read mapping/configuration file for Zotero sync");
    }
    $fh_data = simplexml_load_file($mapping_file);
    if($fh_data==null || is_string($fh_data)){
        die("Sorry, mapping/configuration file for Zotero sync is corrupted");
    }

    // 2) verify heurist codes in mapping and create mapping array
    foreach ($fh_data->children() as $f_gen){
        if($f_gen->getName()=="settings"){

            $arr = $f_gen->attributes();
            $user_ID = @$arr['userId'];
            $api_Key = @$arr['key'];

        }else if($f_gen->getName()=="zTypes"){

            foreach ($f_gen->children() as $f_type){

                if($f_type->getName()=="typeMap"){
                    $arr = $f_type->attributes();
                    if(@$arr['h3id']){

                        $zType = strval($arr['zType']);
                        // find record type with such code (or concept code)
                        $rt_id = getRecTypeLocalID($arr['h3id']);
                        if($rt_id == null){
                                array_push($mapping_rt_errors, "Rectype code is not recognized: ".$arr['h3id']."  ".$zType);
                        }else{

                            $mapping_dt = array();

                            foreach ($f_type->children() as $f_field){
                                if($f_field->getName()=="field"){
                                    $arr = $f_field->attributes();
                                    if(@$arr['h3id'])
                                    {
                                        $dt_code = strval($arr['h3id']);
                                        $resource_rt_id = null;
                                        $resource_dt_id = null;

                                        //pointer mapping
                                        if(strpos($dt_code,".")>0){
                                            $arrdt = explode(".",$dt_code);
                                            if(count($arrdt)==3){
                                                $dt_code = $arrdt[0];
                                                $resource_rt_id = $arrdt[1]; //resource record type
                                                $resource_dt_id = $arrdt[2]; //dettype in resource record
                                            }else{
                                                array_push($mapping_dt_errors, $arr['value']."  wrong resource mapping ".$dt_code." in ".$zType);
                                                continue;
                                            }
                                        }

                                        $dt_id = getDetailTypeLocalID($dt_code);

//error_log($arr['value']."  ".$arr['h3id']."  ".$dt_id);
                                        if($dt_id == null){
                                            array_push($mapping_dt_errors, $arr['value']." detail type not found ".$dt_code." in ".$zType);
                                        }else{
                                            if($resource_rt_id){

                                                $res_rt_id = getRecTypeLocalID($resource_rt_id);
                                                if($rt_id == null){
                                                    array_push($mapping_dt_errors, $arr['value']." resource rectype not recognized: ".$resource_rt_id." in ".$zType);
                                                }else{

                                                    $res_dt_id = getDetailTypeLocalID($resource_dt_id);
                                                    if($dt_id == null){
                                                        array_push($mapping_dt_errors, $arr['value']." detail type for resource not recognized ".$resource_dt_id." in ".$zType);
                                                    }else{
                                                        //pointer detail type and detail type in resource record
                                                        $mapping_dt[strval($arr['value'])] = array($dt_id, $res_rt_id, $res_dt_id);
                                                    }
                                                }
                                            }else{
                                                $mapping_dt[strval($arr['value'])] = $dt_id;
                                            }
                                        }
                                    }
                                }
                            }

                            if(count($mapping_dt)<1){
                                array_push($mapping_rt_errors, "No proper field mapping found for ".$zType);
                            }else{
                                $mapping_dt["h3rectype"] = $rt_id;
                                $mapping_rt[$zType] = $mapping_dt;
                            }

                        }
                    }
                }
            }
        }
    }///foreach

    if($user_ID == null || $api_Key == null){
        die("Sorry, connection parameters are not defined in configuration file for Zotero sync");
    }

    $zotero = new phpZotero($api_Key);

//debug print print_r($mapping_rt, true);


if(!$step){

    // 1) verify connection to zotero (get total count of top-level items in zotero)
    $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ));

    $totalitems = intval(substr($items,strpos($items, "<zapi:totalResults>") + 19, strpos($items, "</zapi:totalResults>") - strpos($items, "<zapi:totalResults>") - 19));

    //print $items;

    print "<div>Count items in Zotero: $totalitems";
    if($totalitems>0){
        print "<br /><br /><a href='syncZotero.php?step=1&cnt=".$totalitems."&db=".HEURIST_DBNAME."'><button>Start</button></a></div>";
    }
    // 2) show mapping issues report
    if(count($mapping_rt_errors)>0 || count($mapping_dt_errors)>0){
        print "<div style='color:red'><br />Warning. There are problem in mapping file.<br />";
        if(count($mapping_rt_errors)>0){
            print "<br />".implode("<br />",$mapping_rt_errors);
        }
        if(count($mapping_dt_errors)>0){
            print "<br /><br />Issues with detail types:<br />".implode("<br />",$mapping_dt_errors);
        }
        print "</div>";
    }

}else if ($step=='1'){

    $alldettypes = getAllDetailTypeStructures();
    $allterms = getTerms();

    $fi_dettype = $alldettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $fi_constraint = $alldettypes['typedefs']['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
    $fi_trmlabel = $allterms['fieldNamesToIndex']['trm_Label'];

    $report_log = "";
    $unresolved_pointers = array();


    // 1) start loop: fetch items by 100
    $start = 0;
    $fetch = 100;
    $totalitems = $_REQUEST['cnt'];

    while ($start<$totalitems){

        $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start, 'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ));

//print $items;
//print "<br/>";

        $zdata = simplexml_load_string($items);

        foreach ($zdata->children() as $entry){

            if($entry->getName()=="entry"){

    // 2) get content of item if itemType is supported
                $itemtype = strval(findXMLelement($entry, "zapi", "itemType"));

ob_flush();flush();
print " <br/>".$itemtype."  ".strval(findXMLelement($entry, null, "title"))."<br/>";

                if(!array_key_exists($itemtype, $mapping_rt)){ //this type is not mapped
print " ignored<br/>";
                        continue;
                }


                $zotero_itemid = strval(findXMLelement($entry, "zapi", "key"));
                $recId = null;
                $rec_URL = null;

    // 3) try to search record in database by zotero id
                $query = "select r.rec_ID, r.rec_Modified from Records r, recDetails d  where  r.rec_Id=d.dtl_recId and d.dtl_DetailTypeID=".DT_ZOTERKEY." and d.dtl_Value='".$zotero_itemid."'";
                $res = mysql_query($query);
                if($res){
                    $row = mysql_fetch_array($res);
                    if($row){
                        $recId = $row[0];

                        $rec_modified = strtotime($row[1]);

    // 4) compare updated time - if it is less than in H3 database, ignore this entry
                        $t_updated = strtotime(strval(findXMLelement($entry, null, "updated")));

                        if($t_updated && $rec_modified>$t_updated){
print "Rec#".$recId." entry was not changed since last sync.  ".date("Y-m-d", $t_updated)." ".date("Y-m-d",$rec_modified )."  <br/>";
                            continue;
                        }
//print ">>>> $recId  ".date("Y-m-d", $t_updated)." ".date("y-m-d",$rec_modified )."  <br/>";

                    }
                }

                $content = json_decode(strval(findXMLelement($entry, null, "content")));

    // 5) create "details" array based on mapping

                $unresolved_records = array();
                $details = array();

                $mapping_dt = $mapping_rt[$itemtype];

                $recordType = $mapping_dt["h3rectype"];
                foreach ($content as $zkey => $value){

                    if(!$value) continue;

                    if($zkey == "creators"){

                        $key = @$mapping_dt["creator"];

                        if(!$key) continue;

                                if(is_array($key)){ //reference to record pointer
                                      $resource_rt_id = $key[1];
                                      $resource_dt_id = $key[2];
                                      $key = $key[0];
                                }

                                if($resource_rt_id==null){
                                    $resource_rt_id = RT_PERSON;
                                }
                                if($res){
                                    $resource_dt_id = DT_NAME;
                                }

                                if(!@$unresolved_records[$key]){
                                    $unresolved_records[$key] = array();
                                }
                                if(!@$unresolved_records[$key][$resource_rt_id]){
                                    $unresolved_records[$key][$resource_rt_id] = array();
                                }


                        foreach($value as $creator){


                            $prop = 'name';
                            $title = @$creator->$prop;

                            if(!$title){
                                $prop = 'lastName';
                                $lastName = @$creator->$prop;

                                if($lastName){
                                    $prop = 'firstName';
                                    $title = trim(@$creator->$prop." ".$lastName);
                                }
                            }

                            if ($title){
                                array_push($unresolved_records[$key][$resource_rt_id], array($resource_dt_id => $title) );
                            }
                        }

                        continue;
                    }

                    if($zkey == "url"){
                        $rec_URL = $value;
                    }

                    $key = @$mapping_dt[$zkey];
                    $resource_rt_id = null;
                    $resource_dt_id = null;

                    if($key){

                        if(is_array($key)){ //reference to record pointer
                              $resource_rt_id = $key[1];
                              $resource_dt_id = $key[2];
                              $key = $key[0];
                        }

                        if(!@$alldettypes['typedefs'][$key]) continue;

                        $dt_type = $alldettypes['typedefs'][$key]['commonFields'][$fi_dettype];

                        if($dt_type=='enum' || $dt_type=='relationtype'){
    // 6) find terms by label values
                              $trm_value = resolveTermValue($dt_type, $value);
                              if($trm_value==null){
                                $report_log = $report_log." term not found for ".$value;
                                continue;
                              }

                              $value = $trm_value;

                        }else if ($dt_type=='resource'){

    // 7) store pointer titles in 'unresolved' pointers

                                if($resource_rt_id==null){
                                    $resource_rt_id = RT_NOTE;   //@todo - take from constraints
                                }
                                if($resource_dt_id==null){
                                    $resource_dt_id = DT_NAME;
                                }

                                if(!@$unresolved_records[$key]){
                                    $unresolved_records[$key] = array();
                                }
                                if(!@$unresolved_records[$key][$resource_rt_id]){
                                    $unresolved_records[$key][$resource_rt_id] = array();
                                }


                                //array_push($unresolved_records[$key][$resource_rt_id], array($resource_dt_id => $value) );
                                $unresolved_records[$key][$resource_rt_id][$resource_dt_id] = $value;

                                continue;
                        }

                        $details["t:".$key] = array("0"=>$value);

//debug print $key."  ".$value."<br/>";

                    }
                }//for fields in content


                $new_recid = addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid);
                if($new_recid){
                            if(count($unresolved_records)>0){
                                $unresolved_pointers[$new_recid] = $unresolved_records;
                            }
                }

//print print_r($content, true)."<br/><br/>";
//print print_r($details, true)."<br/><br/>";
//print print_r($unresolved_records, true)."<br/><br/>";

            }//entry

        }//end loop by items in fetch

        $start = $start + $fetch;
    }// end of loop


print "<div>Create/update resource records</div>";
ob_flush();flush();

    // try to find 'unresolved pointers
    // $rec_id - record to be updated
    // $dt_id - field that must contain pointer to resource
    // $resource_rt_id - record type for resouce record
    // $resource_dt_id

    $ptr_cnt = 0;
    foreach($unresolved_pointers as $rec_id=>$pntdata)
    {
//debug print $rec_id."   ".print_r($pntdata, true)."<br/><br/>";

            foreach($pntdata as $dt_id=>$recdata){

                    foreach($recdata as $resource_rt_id=>$resource_details){

                        if(@$resource_details[0]){ //these are creators



                            foreach($resource_details as $idx=>$creator){

                                   /*$resource_dt_id = array_keys($creator);
                                   $resource_dt_id = $resource_dt_id[0];
                                   $value = $creator[$resource_dt_id];
                                   */

                                   $recource_recid = createResourceRecord($resource_rt_id, $creator);

                                   $inserts = array($rec_id, $dt_id, $recource_recid, 1);
                                   $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values (" . join(",", $inserts).")";
                                   mysql_query($query);
                                   $ptr_cnt++;
                            }

                        }else{

                            $recource_recid = createResourceRecord($resource_rt_id, $resource_details);
                            $inserts = array($rec_id, $dt_id, $recource_recid, 1);
                            $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values (" . join(",", $inserts).")";
                            mysql_query($query);
                            $ptr_cnt++;

                        }

                   }




            }

    }
    print "<br><br>Total count of resolved pointers:".$ptr_cnt;

    // done - show report log

    print "<br>Sync done";

}

// try to find resource record, create it if not found
// returns resource record ID
function  createResourceRecord($resource_rt_id, $resource_details){

       global $alldettypes, $fi_dettype;

       $query = "";
       $details = "";
       $dcnt = 1;
       $recource_recid = null;

       foreach($resource_details as $resource_dt_id=>$value)
       {
                    if(!@$alldettypes['typedefs'][$resource_dt_id]) continue;

                    $dt_type = $alldettypes['typedefs'][$resource_dt_id]['commonFields'][$fi_dettype];
                    if($dt_type=='enum' || $dt_type=='relationtype'){
                          $trm_value = resolveTermValue($dt_type, $value);
                          if($trm_value==null){
                            $report_log = $report_log."<br> term not found for ".$value;
                            continue;
                          }
                          $value = $trm_value;

                    }else if ($dt_type=='resource'){ //2d level of reference

                        $resource2_rt_id =  getConstrainedRecordType($resource_dt_id);
                        if($resource2_rt_id){
                            $value = createResourceRecord($resource2_rt_id, array(DT_NAME=>$value));
                        }else{
                            $report_log = $report_log."<br> resource record type unconstrained for detail type: ".$resource_dt_id;
                            continue;
                        }
                    }

                    if($value){
                        $query = $query." and r.rec_Id=d$dcnt.dtl_recId and d$dcnt.dtl_DetailTypeID=".$resource_dt_id." and d$dcnt.dtl_Value='".mysql_escape_string($value)."'";
                        $dcnt++;

                        $details["t:".$resource_dt_id] = array("0"=>$value);
                    }

       }

       if($query){
           $qd = "";
           for ($idx=1; $idx<$dcnt; $idx++){
                $qd = $qd.",recDetails d$idx ";
           }

           //find resouce record , if not found create new one
           $query = "select r.rec_ID from Records r $qd where r.rec_RecTypeID=".$resource_rt_id.$query;

//debug print $query."<br>";

           $res = mysql_query($query);
           if($res){
                $row = mysql_fetch_array($res);
                if($row){
                    $recource_recid = $row[0];
                }
           }
       }

       if($recource_recid==null){
           //such record not found - create new one
           $recource_recid = addRecordFromZotero(null, $resource_rt_id, null, $details, null);
       }

       return $recource_recid;

}


function findXMLelement($xml, $ns, $name){

            if($ns){
                $children = $xml->children($ns, true);
            }else{
                $children = $xml->children();
            }


            foreach ($children as $f_gen){
                if($f_gen->getName()==$name){
                        return $f_gen;
                }else{
                        $res = findXMLelement($f_gen, $ns, $name);
                        if($res){
                               return $res;
                        }
                }
            }

            return null;
}

function resolveTermValue($dt_type, $value)
{
      global $allterms, $fi_trmlabel;

      $terms = $allterms['termsByDomainLookup'][($dt_type=='enum'?'enum':'relation')];
      $trm_value = null;
      foreach ($terms as $trmid => $term){
          if( strpos(strtolower($term[$fi_trmlabel]), strtolower($value))===0 ){
                $trm_value = $trmid;
                break;
          }
      }
      return $trm_value;
}

function getConstrainedRecordType($resource_dt_id){

    global $alldettypes, $fi_constraint;

    $pointer_constraint = @$alldettypes['typedefs'][$resource_dt_id]['commonFields'][$fi_constraint];
    if(strpos($pointer_constraint,",")>0){
        $pointer_constraint = explode(",", $pointer_constraint);
        $pointer_constraint = $pointer_constraint[0];
    }
    return $pointer_constraint;
}


//
//
//
function addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid){

    global $rep_errors_only;

    $new_recid = null;

                if(count($details)>0){

                        if($zotero_itemid){
                            $details["t:".DT_ZOTERKEY] = array("0"=>$zotero_itemid);
                        }
    // 8) save rtecord
                        $ref = null;


                        if($recId){
                            //sice we do not know dtl_ID - remove all details for updated record
                            $query = "delete from recDetails where dtl_recId=".$recId;
                            $res = mysql_query($query);
                        }

                        //add-update Heurist record
                        $out = saveRecord($recId, //record ID
                            $recordType,
                            $rec_URL, // URL
                            null, //Notes
                            null, //???get_group_ids(), //group
                            null, //viewable    TODO: SHOULD BE A CHOICE
                            null, //bookmark
                            null, //pnotes
                            null, //rating
                            null, //tags
                            null, //wgtags
                            $details,
                            null, //-notify
                            null, //+notify
                            null, //-comment
                            null, //comment
                            null, //+comment
                            $ref,
                            $ref,
                            2    // import without check of record type structure
                        );

                        if (@$out['error']) {
                            print "<div style='color:red'> Error: ".implode("; ",$out["error"])."</div>";
                        }else{

                            $new_recid = $out["bibID"];

                            print ($recId?"Updated":"Added")."&nbsp; #".$out["bibID"]."<br>";

                            if(!$rep_errors_only){
                                if (@$out['warning']) {
                                    print "<br>Warning: ".implode("; ",$out["warning"])."<br>";
                                }
                            }

                        }

                }
                return $new_recid;
}


?>

    </body>
</html>
