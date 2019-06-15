<?php

/**
/**
*   Sync Heurist database with zotero group or user items
*   zotero API key in sys_SyncDefsWithDB/HEURIST_ZOTEROSYNC and mapping are specified in zoteroMap.xml
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('MANAGER_REQUIRED',1);
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_records.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');

require_once(dirname(__FILE__).'/../../external/php/phpZotero.php');

$system->defineConstants();

$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
if($dt_SourceRecordID==0){ //this field is critical - need to download it from heurist core defintions database
    
    $isOK = false;
    $importDef = new DbsImport( $system );
    if($importDef->doPrepare(  array('defType'=>'detailtype', 
                'conceptCode'=>$dtDefines['DT_ORIGINAL_RECORD_ID'] ) ))
    {
        $isOK = $importDef->doImport();
    }
    
    if(!$isOK){
            $system->addErrorMsg('Cannot download field "Source record" required by the function you have requested. ');
            include ERROR_REDIR;
            exit();
    }
    if(!$system->defineConstant('DT_ORIGINAL_RECORD_ID', true)){
        $system->addError(HEURIST_ERROR, 'Detail type "source record" id not defined');
        include ERROR_REDIR;
        exit();
    }
    
}

$HEURIST_ZOTEROSYNC = $system->get_system('sys_SyncDefsWithDB');
if($HEURIST_ZOTEROSYNC==''){
    $system->addError(HEURIST_ERROR, 'Library key for Zotero synchronisation is not defined. '
                .'Please configure Zotero connection in Database > Properties');
    include ERROR_REDIR;
    exit();
}

$mapping_file = "zoteroMap.xml";
$fh_data = null;

if(!file_exists($mapping_file) || !is_readable($mapping_file)){
    $system->addError(HEURIST_ERROR, 'Sorry, could not find/read configuration file .../import/biblio/zoteroMap.xml '
    .'required for Zotero synchronisation - please ask your system administrator to copy it from Heurist source code');
    include ERROR_REDIR;
    exit();
}

$step = @$_REQUEST['step'];
    
$fh_data = simplexml_load_file($mapping_file);
if($fh_data==null || is_string($fh_data)){
        $system->addError(HEURIST_ERROR, 'Sorry, configuration file import/biblio/zoteroMap.xml for Zotero '
        .'synchronisation is corrupted - please ask your system administrator to update it from Heurist source code');
        include ERROR_REDIR;
        exit();
}
?>
<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Zotero synchronization</title>
<!--
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
-->        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />

        <script>
            function __showLoading(){
                var ele = document.getElementById('divStart2');
                ele.style.display = 'none';
                ele = document.getElementById('divLoading');
                ele.style.display = 'block';
                return true;
            }
        </script>
    </head>

    <body class="popup">

        <?php

        // 1) load config file from import/biblio/zoteroMap.xml.
        // This file maps Zotero names to Heurist codes according to context
        
        $user_ID = null;
        $group_ID = null;
        $api_Key = null;
        $mapping_dt = null;
        $mapping_rt = array();
        $mapping_dt_errors = array();
        $mapping_rt_errors = array();
        $mapping_rt_errors2 = array();
        $rep_errors_only = true;

        // TODO: we need a link here which opens the Properties form in the main interface, rather than the old form
        // $linkToAdvancedProperties = "<a target=\"_blank\" href=\"../../admin/adminMenuStandalone.php?db="
        // . HEURIST_DBNAME
        // ."&mode=properties2\"Database > Prxoperties</a>";


        //print "<div>Orignal ID detail:".$dt_SourceRecordID."</div>";


        $lib_keys = explode("|", $HEURIST_ZOTEROSYNC);

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

if($user_ID!=null)$user_ID = trim($user_ID);
if($group_ID!=null)$group_ID = trim($group_ID);
if($api_Key!=null)$api_Key = trim($api_Key);

global $rectypes, $is_verbose;
$is_verbose = true;

if($is_verbose){
    $rectypes = dbs_GetRectypeStructures($system, null, 2);
    print '<table style="display:none" id="mapping_report">';
}

// 2) verify heurist codes in mapping and create mapping array
foreach ($fh_data->children() as $f_gen){
    if($f_gen->getName()=="zTypes"){

        foreach ($f_gen->children() as $f_type){

            if($f_type->getName()=="typeMap"){
                $arr = $f_type->attributes();
                if(@$arr['h3id']){

                    $zType = strval($arr['zType']);
                    // find record type with such code (or concept code)
                    $rt_id = ConceptCode::getRecTypeLocalID($arr['h3id']);
                    
                    printMappingReport_rt($arr, $rt_id);                    
                    
                    if($rt_id == null){
                        array_push($mapping_rt_errors, $arr['h3id']."  ".$zType);
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
                                                addMapping($arr, $zType, $rt_id); //, "creator");
                                            }
                                        }
                                    }

                                }else if(@$arr['h3id'])
                                {
                                    addMapping($arr, $zType, $rt_id);
                                }
                            }
                        }

                        if(count($mapping_dt)<1){
                            array_push($mapping_rt_errors2, $zType);
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

if($is_verbose){
    print '</table><br><br>';
    print 'Mapping check passed. <button class="h3button" onclick="{document.getElementById(\'mapping_report\').style.display=\'block\';}">Show report</button><br><br>';
}


if( ( is_empty($group_ID) && is_empty($user_ID) ) || is_empty($api_Key) ){
    print "<div class='ui-state-error'><br />Current Zotero access settings incomplete: ' ".$key.
    " ' <p>Please configure Zotero connection in Database > Properties.</div></body></html>";
    exit;
}

$zotero = null;
$zotero = new phpZotero($api_Key);

print "<div><b>zotero has been initiated with api key [$api_Key]</b></div>";
print "<br>" ;

/* test connection
$items = $zotero->getItemsTop($group_ID,
array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ));
$code = $zotero->getResponseStatus();
print $code;

//test new library
$zotero = new \Zotero\Library('user', $user_ID, 'Library', $api_Key);
$permissions = $zotero->getKeyPermissions('','');
print json_encode($permissions, JSON_PRETTY_PRINT);

$items = $zotero->fetchItemsTop(array(
'format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'10', 'order'=>'dateModified', 'sort'=>'desc' ));
//'limit'=>10, 'collectionKey'=>$collectionKey, 'order'=>'dateAdded', 'sort'=>'desc'));
*/

if($step=="1"){  //first step - info about current status

    // 1) verify connection to zotero (get total count of top-level items in zotero)
    if($group_ID){
        $items = $zotero->getItemsTop($group_ID,
            array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ), "groups");
    }else{
        $items = $zotero->getItemsTop($user_ID,
            array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'sort'=>'dateModified', 'direction'=>'desc' ));
    }

    $code = $zotero->getResponseStatus();

    if($code>499 ){
        print "<div class='ui-state-error'><br />Zotero Server Side Error: returns response code: $code.<br /><br />"
        ."Please try this operation later.</div>";
    }else if($code>399){
        $msg = "<div class='ui-state-error'><br />Error. Cannot connect to Zotero API: returns response code: $code.<br /><br />";
        if($code==400 || $code==401 || $code==403){
            $msg = $msg."Please verify Zotero API key in Database > Properties - it may be incorrect or truncated.";

        }else if($code==404 ){
            $msg = $msg."Please verify Zotero User and Group ID in Database > Properties - values may be incorrect.";
        }else if($code==407 ){
            $msg = $msg."Proxy Authentication Required, please ask system administrator to set it";
        }
        print $msg."</div>";
    }else if(!$items){
        print "<div class='ui-state-error'><br />Unrecognized Error: cannot connect to Zotero API: returns response code: $code</div>";
        if($code==0){
            print "<div class='ui-state-error'><br />Please ask your system administrator to check that the Heurist proxy settings are correctly set.</div>";
        }
    }else{

        //DEBUG print '<xmp>'.$items.'</xmp>';
        //it does not work anymore
        //intval(substr($items,strpos($items, "<zapi:totalResults>") + 19,strpos($items, "</zapi:totalResults>") - strpos($items, "<zapi:totalResults>") - 19));
        //Responses for multi-object read requests will include a custom HTTP header, Total-Results
        $totalitems = $zotero->getTotalCount();

        //print $items;

        print "<div>Count items in Zotero: $totalitems</div>";
        if($totalitems>0){
            print "<div id='divStart2'><br /><br /><a href='syncZotero.php?step=2&cnt=".$totalitems."&db=".HEURIST_DBNAME.
            "&lib_key=".$lib_key_idx."' onclick='__showLoading()'><button class='h3button'>Start</button></a></div>";
            print "<div id='divLoading' style='display:none;height:40px;background-color:#FFF; background-image: url(../../common/images/loading-animation-white.gif);background-repeat: no-repeat;background-position:50%;'>loading...</div>";
        }



        // 2) show mapping issues report
 if(count($mapping_rt_errors)>0 || count($mapping_rt_errors2)>0 || count($mapping_dt_errors)>0){

            /* old version of message
            print "<div style='color:red'><br />
            The following record types required for Zotero synchronisation are not present in your database
            Synchronisation requires bibliographic record types to be defined in the database (Zotero to Heurist type mappings are defined in the code at /import/biblio/zoteroMap.xml).";
            */

            if(count($mapping_rt_errors)>0){
                print "<p style='color:red'>The following record types required for Zotero synchronisation are not present in your database:";
                print "<br />".implode("<br />",$mapping_rt_errors).'</p>';
            }
            if(count($mapping_rt_errors2)>0){
                print "<p style='color:red'><br />No proper field mapping found for record types:";
                print "<br />".implode("<br />",$mapping_rt_errors2).'</p>';
            }
            if(count($mapping_dt_errors)>0){
                print "<p style='color:red'><br />Issues with base field (detail) types:<br />".implode("<br />",$mapping_dt_errors).'</p>';
            }

            print "<p style='color:red'>Please import them from the Heurist_Reference_Set database (# 3) using Database > Structure > From Databases</p>";
            print "</div>";
        }

    }
}else if ($step=='2'){ //second step - sync

    $alldettypes = dbs_GetDetailTypes($system);
    $allterms = dbs_GetTerms($system);

    $fi_dettype = $alldettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $fi_constraint = $alldettypes['typedefs']['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
    $fi_trmlabel = $allterms['fieldNamesToIndex']['trm_Label'];

    $report_log = "";
    $unresolved_pointers = array();


    // 1) start loop: fetch items by 100
    $cnt_updated = 0;
    $cnt_added = 0;
    $cnt_ignored = 0;
    $arr_ignored = array();

    $start = 0;
    $fetch = min($_REQUEST['cnt'],100);
    $totalitems = $_REQUEST['cnt'];
    $new_recid = 0;
    $isFailure = false;
    
    $mysqli = $system->get_mysqli();
    
    //$tmp_destination = HEURIST_SCRATCH_DIR.'zotero.xml';
    //$fd = fopen($tmp_destination, 'w');  //less than 1MB in memory otherwise as temp file 

    while ($start<$totalitems){

        if($group_ID){
            $items = $zotero->getItemsTop($group_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start,
                'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ), "groups");
        }else{
            $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start,
                'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ));
        }

//fwrite($fd, $items);        
        
        $zdata = simplexml_load_string($items);

        if($zdata===false){
            print "<div style='color:red'>Error: zotero returns non valid xml response for range $start ~ ".($start+$fetch)." </div>";
            $isFailure = true;
            break;
        }else if(count($zdata->children())<1){
            print "<div style='color:red'>Error: zotero returns empty response for range $start ~ ".($start+$fetch)." </div>";
            $isFailure = true;
            break;
        }

        foreach ($zdata->children() as $entry){

            if($entry->getName()=="entry"){

                $zotero_itemid = strval(findXMLelement($entry, "zapi", "key"));


                // 2) get content of item if itemType is supported
                $itemtype = strval(findXMLelement($entry, "zapi", "itemType"));
                $itemtitle = strval(findXMLelement($entry, null, "title"));

                #print " <br/>".$itemtype."  ".strval(findXMLelement($entry, null, "title"))."<br/>";


                @ob_flush();
                @flush();

                if(!array_key_exists($itemtype, $mapping_rt)){ //this type is not mapped
                    #print " <br/> Undefined Record type".$itemtype."  ".$itemtitle."<br/>";
                    array_push($arr_ignored, $itemtype.':  '.$itemtitle);
                    $cnt_ignored++;
                    continue;
                }
                else
                {
                    print $itemtype.": ".$itemtitle."&nbsp;";
                }

                $recId = null;
                $rec_URL = null;

                // 3) try to search record in database by zotero id
                $query = "select r.rec_ID, r.rec_Modified from Records r, recDetails d  ".
                "where  r.rec_Id=d.dtl_recId and d.dtl_DetailTypeID=".$dt_SourceRecordID." and d.dtl_Value='".$zotero_itemid."'";
                $res = $mysqli->query($query);
                if($res){
                    $row = $res->fetch_row();
                    if($row){
                        $recId = $row[0];

                        $rec_modified = strtotime($row[1]);

                        // 4) compare updated time - if it is less than in Heurist database, ignore this entry
                        $t_updated = strtotime(strval(findXMLelement($entry, null, "updated")));

                        if(false && $t_updated && $rec_modified>$t_updated){
                            print "Rec#".$recId." entry was not changed since last sync.  ".
                            date("Y-m-d", $t_updated)." ".date("Y-m-d",$rec_modified )."  <br/>";
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
                                    assignUnresolvedPointer($unresolved_records, $key,
                                        array(DT_GIVEN_NAMES=>@$creator->$prop, DT_NAME=>$lastName) );
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
                    
//print '<br>'.$zkey.'  ->'.$key.'   '.$value;                   

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
                            $detail_id2 = ConceptCode::getDetailTypeLocalID("3-1027"); //MAGIC NUMBER
                            if($detail_id2){
                                $details["t:".$detail_id2] = array("0"=>(count($pages)>1)?$pages[1] :$pages[0]);
                            }

                        }else{
                            $details["t:".$detail_id] = array("0"=>$value);
                        }

                        //debug print $key."  ".$value."<br/>";

                    }
                }//for fields in content



                $new_recid = null;
                if(count($details)<1){
                    print "<div style='color:red'>Warning: zotero id $zotero_itemid: no data recorded in Zotero for this entry</div>";
                }else{
                    //DEBUG echo print_r($details, true);
                    $new_recid = addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, true);
                    if($new_recid){
                        if(count($unresolved_records)>0){
                            $unresolved_pointers[$new_recid] = $unresolved_records;
                        }
                        if($recId==$new_recid){
                            $cnt_updated++;
                        }else{
                            $cnt_added++;
                        }
                    }
                }


            }//entry

        }//end of for each loop by items in fetch

        $start = $start + $fetch;

    }// end of while loop

//fclose($fd);        
    
    print "<div><br>Added: ".$cnt_added."</div>";
    print "<div>Updated: ".$cnt_updated."</div>";
    if($cnt_ignored>0){
        print "<div>Ignored: ".$cnt_ignored."</div>";
        print "<br><div style='color:red'><b>Zotero items ignored:</b><br></div>";
        print "<div style ='color:red; padding-left:20px'>- ".implode('<br>- ',$arr_ignored).'</div>';
    }
    if($cnt_ignored>0 || $isFailure){
        print '<span><br>Please '.CONTACT_HEURIST_TEAM.' - to provide a mapping for each of the undefined record types.</span>';
    }

    if(count($unresolved_pointers)>0){
        //print "<div><br>Create/update resource records</div>";//ij: need hide this info
        print "<br>";

    }

    ob_flush();flush();

    // try to find 'unresolved pointers
    // $rec_id - record to be updated
    // $dt_id - field that must contain pointer to resource
    // $resource_rt_id - record type for resouce record
    // $resource_dt_id


    $ptr_cnt = 0;
    foreach($unresolved_pointers as $rec_id=>$pntdata)
    {
        // pntdata = array of  detail id in main record => record id of resource =>
        //           detail id in resource OR simialr array for next level  => value
        //  $dt_id=>$resource_rt_id=>$resource_details


        foreach($pntdata as $dt_id=>$recdata){  //detail id in main record

            foreach($recdata as $resource_rt_id=>$resource_details){ //recordtype

                $recource_recid = createResourceRecord($mysqli, $resource_rt_id, $resource_details);

                if(!is_array($recource_recid)){
                    $recource_recid = array("0"=>$recource_recid);
                }

                foreach($recource_recid as $idx=>$res_rec_id){
                    //update main record
                    $inserts = array($rec_id, $dt_id, $res_rec_id, 1);
                    $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values ("
                    . join(",", $inserts).")";
                    $mysqli->query($query);
                    $ptr_cnt++;
                }
            }
        }

    }

    #if($ptr_cnt>0)
        #print "<br><br>Total count of RESOLVED pointers:".$ptr_cnt;

    // done - show report log

    #print "<br>Sync done";

}

/**
* add mapping for given zotero type
*
* @param mixed $arr - attributes
* @param mixed $zType - zotero type
*/
function addMapping($arr, $zType, $rt_id)
{

    global $mapping_dt, $mapping_dt_errors;

    $dt_code = strval($arr['h3id']);
    $resource_rt_id = null;
    $resource_dt_id = null;
    
    //pointer mapping
    if(strpos($dt_code,".")>0){

        $res = getResourceMapping($dt_code, $rt_id, $arr);
        if(is_array($res)){
            $mapping_dt[strval($arr['value'])] = $res;
        }else{
            //not found
            array_push($mapping_dt_errors, $arr['value'].$res." in ".$zType);
        }

    }else{

        $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);

        if($dt_id == null){
            //not found
            array_push($mapping_dt_errors, $arr['value']." detail type not found ".$dt_code." in ".$zType);
        }else{
            $mapping_dt[strval($arr['value'])] = $dt_id;
        }
        
        printMappingReport_dt($arr, $rt_id, $dt_id);
        

    }
}

//
//
//
function printMappingReport_rt($arr, $rt_id){
    global $rectypes, $is_verbose;
    
    if($is_verbose){
        
            if(is_object($arr)){
                $zType = strval($arr['zType']);
                $code = $arr['h3id'];
            }else{
                $zType = '->';
                $code = $arr;
            }
        
        
        
        //' '.print_r($arr,true).
        print '<tr><td colspan=2><b>'.$zType.'</b></td><td><b>'.$code.'</b></td>';
        if($rt_id==null){
            print '<td>NOT FOUND'.'</td><td></td>';
        }else{
            print '<td>'.$rectypes['names'][$rt_id].'</td><td>'.$rt_id.'</td>';                                   
        }
        print '</tr>';
    }                    
}

//
//
//
function printMappingReport_dt($arr, $rt_id, $dt_id){
    global $rectypes, $is_verbose;
    
    if($is_verbose){
        
            if(is_object($arr)){
                $label = $arr['value'];
                $code = $arr['h3id'];
            }else{
                $label = '';
                $code = $arr;
            }
    
            print '<tr>&nbsp;&nbsp;&nbsp;<td></td><td>'.$label.'</td><td>'.$code.'</td>';
            if($dt_id==null){
                print '<td>NOT FOUND'.'</td><td></td>';
            }else{
                if(@$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id]){
                    print '<td>'.$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id][0].'</td><td>'.$dt_id.'</td>';                                       
                }else{
//print print_r($rectypes['typedefs'][$rt_id]['dtFields'][$dt_id],true);                    
//$is_verbose=false;
                    print '<td>NOT FOUND IN RECORD TYPE STRUCTURE</td><td>'.$dt_id.'</td>';                                   
                }
                
            }
            print '</tr>';
    }
}

/*
* parse resource mapping (recursive)
*/
function getResourceMapping($dt_code, $rt_id, $arr=null){

    $arrdt = explode(".",$dt_code);
    if(count($arrdt)>2){
        $dt_code = array_shift($arrdt); // $arrdt[0];
        $resource_rt_id = array_shift($arrdt); //$arrdt[1]; //resource record type
        $resource_dt_id = $arrdt[0];
    }else{
        return "  wrong resource mapping ".$dt_code;
    }

    $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);
    
    if($arr!=null){
        printMappingReport_dt($arr, $rt_id, $dt_id);    
    }else{
        printMappingReport_dt($dt_code, $rt_id, $dt_id);            
    }
    
    
    
    if($dt_id == null){
        return " detail type not found ".$dt_code;
    }


    $res_rt_id = ConceptCode::getRecTypeLocalID($resource_rt_id);
    
    printMappingReport_rt($resource_rt_id, $res_rt_id);
    
    if($res_rt_id == null){
        return " resource record type not recognized: ".$resource_rt_id;
    }

    $res_dt_id = ConceptCode::getDetailTypeLocalID($resource_dt_id);
    
        
    
    if($res_dt_id == null){
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id);
        return " detail type for resource not recognized ".$resource_dt_id;
    }


    if(count($arrdt)>1){
        // next level
        $subres = getResourceMapping( implode(".",$arrdt), $res_rt_id );
        if(is_array($subres)){
            $res = array($dt_id, $res_rt_id, $subres);
        }else{
            return $subres;
        }
    }else{
        //pointer detail type and detail type in resource record
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id);
        
        $res = array($dt_id, $res_rt_id, $res_dt_id);
    }

    return $res;
}

/**
* add to list of resource record pointers
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
function createResourceRecord($mysqli, $record_type, $recdetails){

    global $alldettypes, $fi_dettype, $report_log;

    if(is_array($recdetails) && array_key_exists(0, $recdetails)){ //these are creators
        $recource_recids = array();
        foreach($recdetails as $idx=>$creator){
            array_push($recource_recids, createResourceRecord($mysqli, $record_type, $creator));
        }
        return $recource_recids;
    }

    $query = "";
    $details = array();
    $dcnt = 1;
    $recource_recid = null; //returned value

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
                    $value = createResourceRecord($mysqli, $record_type_2, $recdata);
                }else{
                    $report_log = $report_log."<br> resource record type unconstrained for detail type: ".$dt_id;
                    continue;
                }

            }else{
                $value = array();
                foreach($recdata as $record_type_2=>$recdata_nextlevel){ //recordtype

                    $value = createResourceRecord($mysqli, $record_type_2, $recdata_nextlevel); //return rec_id
                    break;

                }
            }
        }else{
            $value = $recdata;
            if($dt_id==DT_DATE){
                try{
                    $t2 = new DateTime($value);
                    $value = $t2->format('Y-m-d H:i:s');
                } catch (Exception  $e){
                }
            }
        }


        if($value){

            if (!is_array($value)) {
                $value = array("0"=>$value);
            }
            //query to search similar record

            $details['t:'.$dt_id] = $value;
            foreach($value as $idx=>$val){
                $query = $query." and r.rec_Id=d$dcnt.dtl_recId and d$dcnt.dtl_DetailTypeID=".$dt_id.
                " and d$dcnt.dtl_Value='".$mysqli->real_escape_string($val)."'";
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

        $res = $mysqli->query($query);
        if($res){
            $row = $res->fetch_row();
            if($row){
                $recource_recid = $row[0];
            }
        }
    }

    if($recource_recid==null){
        //such record not found - create new one
        $recource_recid = addRecordFromZotero(null, $record_type, null, $details, null, false);
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
function addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, $is_echo){

    global $system, $rep_errors_only, $dt_SourceRecordID;

    $new_recid = null;

    if( count($details)>0){

        if($zotero_itemid){
            $details["t:".$dt_SourceRecordID] = array("0"=>$zotero_itemid);
        }
        // 8) save rtecord
        $ref = null;

        //add-update Heurist record
        $record = array();
        $record['ID'] = $recId?$recId:0; //0 means insert
        $record['RecTypeID'] = $recordType;
        $record['AddedByImport'] = 2;
        $record['no_validation'] = true;
        $record['URL'] = $rec_URL;
        $record['ScratchPad'] = null;
        $record['details'] = $details;
        
        $out = recordSave($system, $record);  //see db_records.php
        
/*
        if($recId){
            //sice we do not know dtl_ID - remove all details for updated record
            $query = "DELETE FROM recDetails where dtl_RecID=".$recId;
            $res = mysql_query($query);

            if(!$res){
                $syserror = mysql_error();
                print "<div style='color:red'> Error: Cannot delete record details ".$syserror."</div>";
                return;
            }
        }
*/
    if ( @$out['status'] != HEURIST_OK ) {
           print "<div style='color:red'> Error: ".$out["message"]."</div>";
    }else{

            $new_recid = intval($out['data']);

            if($is_echo){
                print '['.($new_recid==$recId?"Updated":"Added")."&nbsp;Id&nbsp".$new_recid.']<br>';
            }


            if(!$rep_errors_only){
                if (@$out['warning']) {
                    print "<div style='color:red'>Warning: ".implode("; ",$out["warning"])."</div>";
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
