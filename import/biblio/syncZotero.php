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

/**
*   Sync h3 database with zotero group or user items
*   zotero API key in sys_SyncDefsWithDB/HEURIST_ZOTEROSYNC and mapping are specified in zoteroMap.xml
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
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
    <body class="popup">
<?php
            mysql_connection_overwrite(DATABASE);
            if(mysql_error()) {
                die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
            }

            $step = @$_REQUEST['step'];

    // 1) load mapping/config file
    $mapping_file = "zoteroMap.xml";
    $user_ID = null;
    $group_ID = null;
    $api_Key = null;
    $mapping_dt = null;
    $mapping_rt = array();
    $mapping_dt_errors = array();
    $mapping_rt_errors = array();
    $rep_errors_only = true;

    
    
    if(!defined('HEURIST_ZOTEROSYNC') && HEURIST_ZOTEROSYNC==''){
        die("Sorry, library key for Zotero sync is not defined. To set it up go to Admin/Database/Advanced Properties");
    }
    
    //parse lib keys
    //Artem,1958388,268611,YjUxZzcgq1fhCc9YyxgzzVNX|Ian,1836383,274864,DuFHBTyVYVhuIhttUotyqbsjb
    
    $lib_keys = explode("|", HEURIST_ZOTEROSYNC);

//print  HEURIST_ZOTEROSYNC."<br>";
//print  count($lib_keys)."   ".print_r($lib_keys, true)."<br>";
    
    if(!$step){
        if(count($lib_keys)>1){ //select key
?>
        <form action="syncZotero.php" style="padding:20px;">
        
            <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>" />
            <input type="hidden" name="step" value="1" />
            Please select library:
            <select name="lib_key">
<?php            
            foreach($lib_keys as $idx=>$key){
                $vals = explode(",",$key);
                print '<option value="'.$idx.'">'.$vals[0].'</option>';
            }
?>                
            </select>
            <input type="submit" value="Start" />
        </form>
        </body>
        </html>
<?php        
            exit();
        }else{
            $lib_key_idx = 0;            
            $step = "1";
        }
    }else{
        $lib_key_idx = @$_REQUEST['lib_key'];
    }
    
    $key = $lib_keys[$lib_key_idx];
    $vals = explode(",",$key);
    
    $user_ID = @$vals[1];
    $group_ID = @$vals[2];
    $api_Key  = @$vals[3];
    
    if( (  is_empty($group_ID) && is_empty($user_ID) ) || is_empty($api_Key) ){
        print "<div style='color:red'><br />Current settings: (".$key."). Please go to Admin/Database/Advanced Properties and correct it</div></body></html>";
        exit;
    }
    
    
    if(!file_exists($mapping_file) || !is_readable($mapping_file)){
        die("Sorry, could not find/read mapping/configuration file .../import/biblio/zoteroMap.xml required for Zotero synchronisation");
    }
    $fh_data = simplexml_load_file($mapping_file);
    if($fh_data==null || is_string($fh_data)){
        die("Sorry, mapping/configuration file for Zotero sync is corrupted");
    }
    
//print "<div>config has been loaded</div>";

    // 2) verify heurist codes in mapping and create mapping array
    foreach ($fh_data->children() as $f_gen){
         /* NOW IT IS DEFINED IN DATABASE
        if($f_gen->getName()=="settings"){

            $arr = $f_gen->attributes();
            $user_ID = @$arr['userId'];
            $group_ID = @$arr['groupId'];
            $api_Key = @$arr['key'];

        }else 
        */
        if($f_gen->getName()=="zTypes"){

            foreach ($f_gen->children() as $f_type){

                if($f_type->getName()=="typeMap"){
                    $arr = $f_type->attributes();
                    if(@$arr['h3id']){

                        $zType = strval($arr['zType']);
                        // find record type with such code (or concept code)
                        $rt_id = getRecTypeLocalID($arr['h3id']);
                        if($rt_id == null){
                                array_push($mapping_rt_errors, "Record type ".$arr['h3id']."  ".$zType.
                                    " was not found in the database (use Import Structure to obtain)");
                        }else{

                            $mapping_dt = array();

                            foreach ($f_type->children() as $f_field){
                                if($f_field->getName()=="field"){
                                    $arr = $f_field->attributes();

                                    if(strval($arr['value'])=="creator"){

                                        foreach ($f_field->children() as $f_ctype){
                                            if($f_ctype->getName()=="creatorType"){
                                                 $arr = $f_ctype->attributes();
                                                 if(@$arr['h3id'])
                                                 {
                                                    addMapping($arr, $zType); //, "creator");
                                                 }
                                            }
                                        }

                                    }else if(@$arr['h3id'])
                                    {
                                        addMapping($arr, $zType);
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

    $zotero = null;
    

   $zotero = new phpZotero($api_Key);

print "<div>zotero component has been inited with api key $api_Key   step:$step</div>";

    
if($step=="1"){  //first step - info about current status

    // 1) verify connection to zotero (get total count of top-level items in zotero)
    if($group_ID){
        $items = $zotero->getItemsTop($group_ID, array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ), "groups");
    }else{
        $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ));
    }

    $code = $zotero->getResponseStatus();
    
    if($code>499 ){
        print "<div style='color:red'><br />Zotero Server Side Error. It returns response code: $code.<br /><br />"
            ."Try this operation later</div>";   
    }else if($code>399){
        $msg = "<div style='color:red'><br />Error. Can not connect to Zotero API. It returns response code: $code.<br /><br />";
        if($code==401 || $code==403){
            $msg = $msg."Verify API key in Admin/Database/Advanced Properties";
        }else if($code==404 ){
            $msg = $msg."Verify User and Group ID in Admin/Database/Advanced Properties";
        }else if($code==407 ){
            $msg = $msg."Proxy Authentication Required";
        }
        print $msg."</div>";   
    }else if(!items){
        print "<div style='color:red'><br />Unrecognized Error. Can not connect to Zotero API. It returns response code: $code</div>";   
    }else{

        $totalitems = intval(substr($items,strpos($items, "<zapi:totalResults>") + 19, strpos($items, "</zapi:totalResults>") - strpos($items, "<zapi:totalResults>") - 19));

        //print $items;

        print "<div>Count items in Zotero: $totalitems</div>";
        if($totalitems>0){
            print "<div><br /><br /><a href='syncZotero.php?step=2&cnt=".$totalitems."&db=".HEURIST_DBNAME."&lib_key=".$lib_key_idx."'><button>Start</button></a></div>";
        }
        
            
    

        
        
        // 2) show mapping issues report
        if(count($mapping_rt_errors)>0 || count($mapping_dt_errors)>0){
            print "<div style='color:red'><br />Warning. There are problem in the mapping file (import/biblio/zoteroMap.xml)<br />";
            if(count($mapping_rt_errors)>0){
                print "<br />".implode("<br />",$mapping_rt_errors);
            }
            if(count($mapping_dt_errors)>0){
                print "<br /><br />Issues with detail types:<br />".implode("<br />",$mapping_dt_errors);
            }
            print "</div>";
        }
    
    }
}else if ($step=='2'){ //second step - sync

    $alldettypes = getAllDetailTypeStructures();
    $allterms = getTerms();

    $fi_dettype = $alldettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $fi_constraint = $alldettypes['typedefs']['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
    $fi_trmlabel = $allterms['fieldNamesToIndex']['trm_Label'];

    $report_log = "";
    $unresolved_pointers = array();


    // 1) start loop: fetch items by 100
    $start = 0;
    $fetch = min($_REQUEST['cnt'],100);
    $totalitems = $_REQUEST['cnt'];
    $new_recid = 0;

    while ($start<$totalitems){

        if($group_ID){
            $items = $zotero->getItemsTop($group_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start, 'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ), "groups");
        }else{
            $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start, 'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ));
        }

        $zdata = simplexml_load_string($items);

        foreach ($zdata->children() as $entry){

            if($entry->getName()=="entry"){

                $zotero_itemid = strval(findXMLelement($entry, "zapi", "key"));

                //debug if($zotero_itemid!="8FBUR933") continue;
                //debug if($zotero_itemid!="DM4822S4") continue;

    // 2) get content of item if itemType is supported
                $itemtype = strval(findXMLelement($entry, "zapi", "itemType"));

print " <br/>".$itemtype."  ".strval(findXMLelement($entry, null, "title"))."<br/>";
ob_flush();flush();

                if(!array_key_exists($itemtype, $mapping_rt)){ //this type is not mapped
                        print " ignored<br/>";
                        continue;
                }



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

                        if(false && $t_updated && $rec_modified>$t_updated){
print "Rec#".$recId." entry was not changed since last sync.  ".date("Y-m-d", $t_updated)." ".date("Y-m-d",$rec_modified )."  <br/>";
                            continue;
                        }
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

/* sample of creator objects in Zoterp
Array (
    [0] => stdClass Object
        (
            [creatorType] => editor
            [firstName] => Harold
            [lastName] => Mytum
        )

    [1] => stdClass Object
        (
            [creatorType] => editor
            [firstName] => Gilly
            [lastName] => Carr
        )

    [2] => stdClass Object
        (
            [creatorType] => author
            [firstName] => John H.
            [lastName] => Jameson
        )
)
*/


/*????
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
                                }*/


                        foreach($value as $creator){

                            $prop = 'creatorType';
                            $ctype = @$creator->$prop;

                            $key = @$mapping_dt[$ctype];
                            if(!$key) continue;

                            if(!is_array($key)){
                                $key = array($key, RT_PERSON, 0);
                            }

                            $prop = 'name';
                            $title = @$creator->$prop;

                            if(!$title){
                                $prop = 'lastName';
                                $lastName = @$creator->$prop;

                                if($lastName){
                                    $prop = 'firstName';
                                    assignUnresolvedPointer($unresolved_records, $key, array(DT_GIVEN_NAMES=>@$creator->$prop, DT_NAME=>$lastName) );
                                    continue;
                                }
                            }

                            if ($title){
                                assignUnresolvedPointer($unresolved_records, $key, array(DT_NAME => $title));
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
                              $detail_id = $key[0];
                        }else{
                              $detail_id = $key;
                        }

                        if(!@$alldettypes['typedefs'][$detail_id]) continue;

                        $dt_type = $alldettypes['typedefs'][$detail_id]['commonFields'][$fi_dettype];

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
                                if(!is_array($key)){ //by default
                                    $key = array($detail_id, RT_NOTE, DT_NAME);
                                }

                                assignUnresolvedPointer($unresolved_records, $key, $value);

                                continue;
                        }
                        if($zkey=="pages"){

                            $pages = explode("-",$value);
                            $details["t:".$detail_id] = array("0"=>$pages[0]);
                            $detail_id2 = getDetailTypeLocalID("3-1027"); //MAGIC NUMBER
                            if($detail_id2){
                                $details["t:".$detail_id2] = array("0"=>(count($pages)>1)?$pages[1] :$pages[0]);
                            }

                        }else{
                            $details["t:".$detail_id] = array("0"=>$value);
                        }

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
//print error_log( ">>>>> unresolved_records=".print_r($unresolved_records, true) );

            }//entry

        }//end loop by items in fetch

        $start = $start + $fetch;
    }// end of loop

//DEBUG error_log("unresolved pointers>>>>>".print_r($unresolved_pointers, true));

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
// pntdata = array of  detail id in main record => record id of resource => detail id in resource OR simialr array for next level  => value
//  $dt_id=>$resource_rt_id=>$resource_details


//debug print $rec_id."   ".print_r($pntdata, true)."<br/><br/>";

            foreach($pntdata as $dt_id=>$recdata){  //detail id in main record

                    foreach($recdata as $resource_rt_id=>$resource_details){ //recordtype

//DEBUG error_log( ">>>>> $resource_rt_id  unresolved_records=".print_r($resource_details, true) );
                        $recource_recid = createResourceRecord($resource_rt_id, $resource_details);

                        if(!is_array($recource_recid)){
                            $recource_recid = array("0"=>$recource_recid);
                        }
                        foreach($recource_recid as $idx=>$res_rec_id){
                            //update main record
                            $inserts = array($rec_id, $dt_id, $res_rec_id, 1);
                            $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values (" . join(",", $inserts).")";
                            mysql_query($query);
                            $ptr_cnt++;
                        }
/*
                        if(array_key_exists(0, $resource_details)){ //@$resource_details[0]){ //these are creators

                            foreach($resource_details as $idx=>$creator){

                                   $recource_recid = createResourceRecord($resource_rt_id, $creator);

                                   //update main record
                                   $inserts = array($rec_id, $dt_id, $recource_recid, 1);
                                   $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values (" . join(",", $inserts).")";
                                   mysql_query($query);
                                   $ptr_cnt++;
                            }

                        }else{

                            $recource_recid = createResourceRecord($resource_rt_id, $resource_details);
                            //update main record
                            $inserts = array($rec_id, $dt_id, $recource_recid, 1);
                            $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values (" . join(",", $inserts).")";
                            mysql_query($query);
                            $ptr_cnt++;

                        }
*/
                   }
            }

    }
    print "<br><br>Total count of resolved pointers:".$ptr_cnt;

    // done - show report log

    print "<br>Sync done";

}

/**
* add mapping for given zotero type
*
* @param mixed $arr - attributes
* @param mixed $zType - zotero type
*/
function addMapping($arr, $zType)
{

    global $mapping_dt, $mapping_dt_errors;

                                        $dt_code = strval($arr['h3id']);
                                        $resource_rt_id = null;
                                        $resource_dt_id = null;

                                        //pointer mapping
                                        if(strpos($dt_code,".")>0){

                                            $res = getResourceMapping($dt_code);
                                            if(is_array($res)){
                                                $mapping_dt[strval($arr['value'])] = $res;
//DEBUG error_log(">>>>>".print_r($res, true));
                                            }else{
                                                array_push($mapping_dt_errors, $arr['value'].$res." in ".$zType);
                                            }

                                        }else{

                                            $dt_id = getDetailTypeLocalID($dt_code);

//DEBUG  error_log($zType.">>>>".$arr['value']."  ".$arr['h3id']."  ".$dt_id);
                                            if($dt_id == null){
                                                array_push($mapping_dt_errors, $arr['value']." detail type not found ".$dt_code." in ".$zType);
                                            }else{
                                                $mapping_dt[strval($arr['value'])] = $dt_id;
                                            }

                                        }
}

/*
* parse resource mapping (recursive)
*/
function getResourceMapping($dt_code){

        $arrdt = explode(".",$dt_code);
        if(count($arrdt)>2){
            $dt_code = array_shift($arrdt); // $arrdt[0];
            $resource_rt_id = array_shift($arrdt); //$arrdt[1]; //resource record type
            $resource_dt_id = $arrdt[0];
        }else{
            return "  wrong resource mapping ".$dt_code;
        }

        $dt_id = getDetailTypeLocalID($dt_code);
        if($dt_id == null){
            return " detail type not found ".$dt_code;
        }


        $res_rt_id = getRecTypeLocalID($resource_rt_id);
        if($res_rt_id == null){
            return " resource record type not recognized: ".$resource_rt_id;
        }

        $res_dt_id = getDetailTypeLocalID($resource_dt_id);
        if($res_dt_id == null){
            return " detail type for resource not recognized ".$resource_dt_id;
        }


        if(count($arrdt)>1){
            // next level
            $subres = getResourceMapping( implode(".",$arrdt) );
            if(is_array($subres)){
                $res = array($dt_id, $res_rt_id, $subres);
            }else{
                return $subres;
            }
        }else{
            //pointer detail type and detail type in resource record
            $res = array($dt_id, $res_rt_id, $res_dt_id);
        }

        return $res;
}

/**
* add to list of resourse record pointers
*
* @param mixed $unresolved
* @param mixed $key
* @param mixed $value
*/
function assignUnresolvedPointer(&$unresolved, $key, $value){

    $detail_id      = $key[0];
    $resource_rt_id = $key[1];
    $resource_dt_id = $key[2];

    if(!@$unresolved[$detail_id]){
        $unresolved[$detail_id] = array();
    }
    if(!@$unresolved[$detail_id][$resource_rt_id]){
        $unresolved[$detail_id][$resource_rt_id] = array();
    }
    if(is_array($resource_dt_id)){

        assignUnresolvedPointer($unresolved[$detail_id][$resource_rt_id], $resource_dt_id, $value);

    }else{
        //array_push($unresolved_records[$key][$resource_rt_id], array($resource_dt_id => $value) );
        if( is_array($value) ){ //this is creator  detail id in value
            array_push($unresolved[$detail_id][$resource_rt_id], $value);
        }else{
            $unresolved[$detail_id][$resource_rt_id][$resource_dt_id] = $value;
        }
    }
}

/**
* try to find resource record, create it if not found
*
* @param mixed $record_type - recordtype for resource
* @param mixed $recdetails - array of dt_id=>value
*
* returns array of resource record ID
*/
function createResourceRecord($record_type, $recdetails){

        global $alldettypes, $fi_dettype;

        if(is_array($recdetails) && array_key_exists(0, $recdetails)){ //these are creators
            $recource_recids = array();
            foreach($recdetails as $idx=>$creator){
                   array_push($recource_recids, createResourceRecord($record_type, $creator));
            }
            return $recource_recids;
        }

       $query = "";
       $details = "";
       $dcnt = 1;
       $recource_recid = null; //returned value

//DEBUG error_log(">>>>".print_r($recdetails, true));

    foreach($recdetails as $dt_id=>$recdata){  //detail id in main record


        if(!@$alldettypes['typedefs'][$dt_id]) continue;  //detail type not found

        $dt_type = $alldettypes['typedefs'][$dt_id]['commonFields'][$fi_dettype];
        if($dt_type=='enum' || $dt_type=='relationtype'){

              $trm_value = resolveTermValue($dt_type, $recdata);
              if($trm_value==null){
                $report_log = $report_log."<br> term not found for ".$recdata;
                continue;
              }
              $value = $trm_value;

        }else if ($dt_type=='resource'){ //next level of reference

            if(!is_array($recdata)){

                $record_type_2 =  getConstrainedRecordType($dt_id);
                if($record_type_2){
                    $recdata = array(DT_NAME=>$recdata);
                    $value = createResourceRecord($record_type_2, $recdata);
                }else{
                    $report_log = $report_log."<br> resource record type unconstrained for detail type: ".$dt_id;
                    continue;
                }

            }else{
                $value = array();
                foreach($recdata as $record_type_2=>$recdata_nextlevel){ //recordtype

                    /*if(is_array($recdata_nextlevel) && array_key_exists(0, $recdata_nextlevel)){
                        foreach($recdata_nextlevel as $idx=>$creator){
//error_log(">>>>creator 1 rt=".$record_type_2."  ".print_r($creator, true));
                                $rec_id = createResourceRecord($record_type_2, $creator);
                                array_push($value, $rec_id);
                        }
                        //return $value;
                    }else{}*/

                       $value = createResourceRecord($record_type_2, $recdata_nextlevel); //return rec_id
                       //array_push($value, $rec_id);
                       break;

                }
            }
        }else{
            $value = $recdata;
            if($dt_id==9){
                $value = date('Y-m-d', strtotime($value));
            }
        }


        if($value){

            if (!is_array($value)) {
                $value = array("0"=>$value);
            }
            //query to search similar record

            $details["t:".$dt_id] = $value;
            foreach($value as $idx=>$val){
                $query = $query." and r.rec_Id=d$dcnt.dtl_recId and d$dcnt.dtl_DetailTypeID=".$dt_id." and d$dcnt.dtl_Value='".mysql_escape_string($val)."'";
                $dcnt++;
            }
        }
    }

    // try to find the existing record
    if($query){
           $qd = "";
           for ($idx=1; $idx<$dcnt; $idx++){ //count of details
                $qd = $qd.",recDetails d$idx ";
           }

           //find resouce record , if not found create new one
           $query = "select r.rec_ID from Records r $qd where r.rec_RecTypeID=".$record_type.$query;

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
           $recource_recid = addRecordFromZotero(null, $record_type, null, $details, null);
     }

     return $recource_recid;
}

/**
* find xml element
*
* @param mixed $xml
* @param mixed $ns
* @param mixed $name
*/
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

/**
* term value to id
*
* @param mixed $dt_type
* @param mixed $value
*/
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

/**
* get record type for resource detail type
*
* @param mixed $resource_dt_id
*/
function getConstrainedRecordType($resource_dt_id){

    global $alldettypes, $fi_constraint;

    $pointer_constraint = @$alldettypes['typedefs'][$resource_dt_id]['commonFields'][$fi_constraint];
    if(strpos($pointer_constraint,",")>0){
        $pointer_constraint = explode(",", $pointer_constraint);
        $pointer_constraint = $pointer_constraint[0];
    }
    return $pointer_constraint;
}


/**
* saves record in heurist DB
*
* @param mixed $recId
* @param mixed $recordType
* @param mixed $rec_URL
* @param mixed $details
* @param mixed $zotero_itemid
*/
function addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid){

    global $rep_errors_only;

    $new_recid = null;

                if( count($details)>0){

                        if($zotero_itemid){
                            $details["t:".DT_ZOTERKEY] = array("0"=>$zotero_itemid);
                        }
    // 8) save rtecord
                        $ref = null;


                        if($recId){
                            //sice we do not know dtl_ID - remove all details for updated record
                            $query = "DELETE FROM recDetails where dtl_RecID=".$recId;
                            $res = mysql_query($query);

                            if(!$res){
                                $syserror = mysql_error();
                                print "<div style='color:red'> Error: Can not delete record details ".$syserror."</div>";
                                return;
                            }
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

//isNullOrEmptyString
function is_empty($question){
    return (!isset($question) || trim($question)==='');
}

?>

    </body>
</html>
