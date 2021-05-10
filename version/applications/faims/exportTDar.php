<?php
/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/**
*
* exportTDar.php : export data to a TDar (The Digital Archaeological Record) format repository
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

if(isForAdminOnly("to export to FAIMS tDAR repository")){
    return;
}

/** The list of attachment files */
define('API_FIELD_UPLOAD_FILE',"uploadFile");
/** The meta-data describing the files, an xml file itself, adhering to the published schema */
define('API_FIELD_RECORD',"record");
/** The project to which the files are to be added. Will overwrite anything within the record */
define('API_FIELD_PROJECT_ID',"projectId");


/*
$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
if($dt_SourceRecordID==0){
print "Detail type 'source record id' not defined";
return;
}

$dt_Geo = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);
*/
?>
<html>
    <head>
        <title>Export to FAIMS tDAR Repository</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">

        <script type="text/javascript" src="../../common/js/utilsUI.js"></script>
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="selectRectype.js"></script>
        <style>
            .err_message {
                color:red;
                border-bottom: 1px solid black;
                text-transform:uppercase;
                padding-top:5px;
                padding-bottom:5px;
            }
            .lbl_form{
                color:red;
                width:180px;
                display:inline-block;
                text-align:right;
                padding:14px;
            }
        </style>
    </head>

    <body style="padding:44px;" class="popup">

        <script src="../../common/php/loadCommonInfo.php"></script>

        <div class="banner"><h2>Export to FAIMS tDAR Repository</h2></div>
        <div id="page-inner" style="margin:0px 5%; padding: 0.5em;">

            <?php

            $no_registration = @$_REQUEST['noreg'];

            $protocol = "http://";
            $rt_toexport = @$_REQUEST['rt'];
            $projname = @$_REQUEST['projname'];
            $projectId = @$_REQUEST['projid'];
            $host = @$_REQUEST['host'];
            $fusername = @$_REQUEST['username'];
            $fpwd = @$_REQUEST['pwd'];
            if(!$host) $host = '115.146.85.232:8080'; // the FAIMS tDAR repository
            $step = @$_REQUEST['step'];

            if($step=='1'){

                if(!$projectId){
                    print "<div class='err_message'>FAIMS project ID is mandatory</div>";
                    $step = null;
                }
                if(!$no_registration){
                    if(!$fusername){
                        print "<div class='err_message'>Username is mandatory</div>";
                        $step = null;
                    }
                    if(!$fpwd){
                        print "<div class='err_message'>Password is mandatory</div>";
                        $step = null;
                    }
                }
                if(!$rt_toexport){
                    print "<div class='err_message'>Select record types for export</div>";
                    $step = null;
                }
            }

            //print "DEBUG>".(function_exists('HTTP_Request2')?"YES":"NO")."<br>";

            print "<form name='startform' action='exportTDar.php' method='get'>";
            print "<input name='step' value='1' type='hidden'>";
            print "<input id='rt_selected' name='rt' type='hidden'>";
            print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";

            print "<div><div class='lbl_form'>FAIMS project ID:</div><input style='color:black' name='projid' value='".$projectId."' size='10'>";
            print "<div style='padding-left:205px;color:grey;'>To obtain the project ID, create the project in the FAIMS repository ".
            "and read the FAIMS ID shown on the project description page</div>";

            // print "<br><br><div>Project name: <input name='projname' value='".($projname?$projname:HEURIST_DBNAME).
            // "' size='100'></div><br/>";

            print "<div><div class='lbl_form'>Host:</div><input name='host' value='".$host."' size='20'></div>";
            print "<div><div class='lbl_form'>Username:</div><input name='username' value='".$fusername."' size='20'></div>";
            print "<div><div class='lbl_form'>Password:</div><input name='pwd' value='".$fpwd."' size='20' type='password'></div>";

            print "<div id='buttondiv2' style='display:".(($step=='1')?"none":"block")."'><div class='lbl_form'>Record types to include in export:</div>";
            print "<input type='button' value='Select Record Types' id='btnSelRecType1' ".
            "onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/></div>";

            print "<div id='selectedRectypes' style='width:470px;color:black;padding-left:200px;font-weight:bold;'></div>";

            print "<div><div class='lbl_form' style='color:black;'>Create only. Do not upload/register:</div><input name='noreg' value='1' type='checkbox'></div>";
            print "<div id='buttondiv' style='display:".(($rt_toexport && $step!='1')?"block":"none")."'><div class='lbl_form'></div><input type='submit' value='Start tDAR Export' /></div>";


            print "</form><br><br>";
            if($rt_toexport){
                print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
            }

            if ($step!='1') {
                print "</div></body></html>";
                exit();
            }


            $folder = HEURIST_FILESTORE_DIR."faims/tdar/".$projectId;
            $folders = null;
            $batch = "#!/bin/bash\ncd ".HEURIST_DIR;
            $data_toreg = array();
            $coding_sheet = array();
            $images = array();
            $documents = array();
            $allowed_img_ext = array('jpg','jpeg','jfif','bmp','pict','tif','tiff','png','gif');
            $allowed_doc_ext = array('pdf','doc','docx','rtf','txt');

            rrmdir($folder);

            //create export folder
            if(!file_exists($folder)){
                if (!mkdir($folder, 0777, true)) {
                    die('Failed to create folder for '.$folder);
                }
            }else{ //clear folder
                delFolderTree($folder, false);
            }

            //cookie file
            $cookie_file =  $folder."/cookies";
            //first of all try tp connect to tDAR server and keep cookie session id

            if(!$no_registration){

                $resp = post_request($protocol.$host."/login/process", $fusername, $fpwd, array("loginUsername"=>$fusername, "loginPassword"=>$fpwd), $cookie_file, true);

                if($resp=="ERROR"){
                    print "<script>document.getElementById('buttondiv').style.display = 'block';document.getElementById('buttondiv2').style.display = 'block';</script>";
                    print "<div class='err_message'>Unable to connect to the tDAR server - please check IP address and port</div>";
                    print "</div></body></html>";
                    exit();

                }else if(strpos($resp, 'Set-Cookie: crowd.token_key=""')>0){  //not registered
                    print "<script>document.getElementById('buttondiv').style.display = 'block';document.getElementById('buttondiv2').style.display = 'block';</script>";
                    print "<div class='err_message'>Authentification to tDAR server failed. Please check user name and password.</div>";
                    print "</div></body></html>";
                    exit();
                }

            }


            $rectyps = explode(",", $rt_toexport);
            if (!in_array(RT_RELATION, $rectyps)) {
                array_push($rectyps, RT_RELATION);
            }

            $rtStructs = getAllRectypeStructures(true);
            $dtStructs = getAllDetailTypeStructures(true);
            $dtTerms = getTerms(true);
            $ind_label =  $dtTerms['fieldNamesToIndex']['trm_Label'];
            $ind_descr =  $dtTerms['fieldNamesToIndex']['trm_Description'];
            $ind_tcode =  $dtTerms['fieldNamesToIndex']['trm_Code'];
            $ind_parentid =  $dtTerms['fieldNamesToIndex']['trm_ParentTermID'];

            $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
            $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];


            //START OF TERMS-RELTYPES

            print "Prepare coding sheet for relation types<br>";
            $termLookup = $dtTerms['termsByDomainLookup']['relation'];

            foreach ($termLookup as $termid=>$term) {

                if($term[$ind_parentid]>0){
                    $name = getFullTermName($term, 'relation');
                    $descr = $termid."-".$term[$ind_label].($term[$ind_descr]?"-".$term[$ind_descr]:"");
                    $coding_sheet[$termid] = array( $name, $descr);
                }
            }

            if(count($coding_sheet)>0){

                $rtfolder = $folder."/reltypes";
                if (!mkdir($rtfolder, 0777, true)) {
                    print "<p class='error'>'Failed to create folder: ".$rtfolder."</p>";
                }else{

                    $filename = $rtfolder."/sheet.csv";
                    $fp = fopen($filename, 'w');
                    foreach ($coding_sheet as $term_id => $details) {
                        $fields = array($term_id, $details[0], $details[1]);
                        fputcsv($fp, $fields);
                    }
                    fclose($fp);

                    $meta = getMetadata('Heurist relation types coding sheet', 'Heursit relationship types correspond to tDAR coding sheet', 'CODING_SHEET');
                    $metadata_content = utf8_encode($meta->asXML());
                    file_put_contents($rtfolder."/coding_sheet.xml", $metadata_content);

                    addRegistration("Coding Sheet", $rtfolder."/coding_sheet.xml",  $metadata_content, $filename, "Relationship types");
                }
            }


            //find all terms used in selected rectypes

            print "Prepare coding sheet for enumerations (terms)<br>";
            ob_flush();flush();

            $processed = array();

            foreach ($rectyps as $rt) {
                $details =  $rtStructs['typedefs'][$rt]['dtFields'];
                foreach ($details as $dtid=>$detail) {

                    $det = $dtStructs['typedefs'][$dtid]['commonFields'];
                    $dt_type = $det[$int_dt_type];
                    if($dt_type=='enum' && !in_array($dtid, $processed)){ //} || $dt_type=='relationtype'){
                        array_push($processed, $dtid);

                        $terms = $detail[$int_dt_termtree];

                        if(is_numeric($terms)){
                            $termTree =  $dtTerms['treesByDomain'][$dt_type][$terms];
                        }else{
                            $termTree = json_decode($terms);
                        }
                        if(count($termTree)<1){
                            continue;
                        }

                        $coding_sheet = array();
                        fillTerms($dt_type, $termTree, "");


                        if(count($coding_sheet)>0){

                            $rtfolder = $folder."/terms".$dtid;
                            if (!mkdir($rtfolder, 0777, true)) {
                                print "<p class='error'>'Failed to create folder: ".$rtfolder."</p>";
                            }else{

                                $filename = $rtfolder."/sheet.csv";
                                $fp = fopen($filename, 'w');
                                foreach ($coding_sheet as $term_id => $details) {
                                    $fields = array($term_id, $details[0], $details[1]);
                                    fputcsv($fp, $fields);
                                }
                                fclose($fp);

                                $meta = getMetadata('Coding sheet for Heursit field: '.$dtStructs['names'][$dtid],
                                    'This coding sheet contains terms exported from Heurist corresponding with field'.$dtStructs['names'][$dtid], 'CODING_SHEET');
                                $metadata_content = utf8_encode($meta->asXML());
                                file_put_contents($rtfolder."/coding_sheet.xml", $metadata_content);

                                addRegistration("Coding Sheet", $rtfolder."/coding_sheet.xml",  $metadata_content, $filename, $dtid);
                            }
                        }
                    }
                }
            }

            if(!$no_registration){
                doRegistration();
            }
            //END OF TERMS

            $squery = "select rec_ID, dtl_DetailTypeID, dtl_Value, if(dtl_Geo is null, null, ST_asWKT(dtl_Geo)) as dtl_Geo, ulf_ID, ulf_FilePath, ulf_FileName, ulf_OrigFileName, ulf_MimeExt, trm_Label "; //, trm_Description
            $ourwhere = " and (dtl_RecID=rec_ID) ";
            $detTable = ", recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID left join defTerms on trm_ID = dtl_Value ";
            $order = "rec_ID, dtl_DetailTypeID";
            $params = array();

            $mysqli = mysqli_connection_overwrite(DATABASE); // "hdb_".@$_REQUEST['db']);

            // Note: enum fields are exported as 'coding sheets'
            $unsupported = array('relmarker','separator','calculated','fieldsetmarker','urlinclude'); //'enum','resource','relationtype',


            /*
            * Main LOOP for all selected record types
            */
            foreach ($rectyps as $rt) {

                print "Prepare record type ".$rt." ".$rtStructs['names'][$rt]."<br>";
                ob_flush();flush();

                //get record type structure
                $dettypes = array();
                $recstruc = array();

                $dettypes[0] = array(); //for record ID
                $recstruc[0] = array(0, 'Heursit ID', 'Heurist Record ID', 'integer');   //keep detail defs


                $query =  "select rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, dty_Type from defRecStructure, defDetailTypes where rst_DetailTypeID=dty_ID and rst_RecTypeID=".$rt." order by rst_DisplayOrder";
                $res = $mysqli->query($query);
                if (!$res){
                    //die('Failed to obtain strcuture for record type: '.$rt);
                    print "<p class='error'>Failed to obtain structure for record type: ".$rt."</p>";
                    continue;
                }else{

                    while ($row = $res->fetch_row()) {  //$row = $res->fetch_assoc()) {

                        if(in_array($row[3], $unsupported)){
                            continue;
                        }

                        $dettypes[$row[0]] = array();
                        $recstruc[$row[0]] = $row;   //keep detail defs
                    }
                }

                $params["q"] = "t:".$rt;

                //get all records for specified recordtype
                $query = prepareQuery($params, $squery, BOTH, $detTable, $ourwhere, $order);

                $filename;

                $res = $mysqli->query($query);
                if (!$res){
                    //die('Failed to obtain data for record type: '.$rt);
                    print "<p class='error'>Failed to obtain data for record type: ".$rt."</p>";
                    continue;
                }else{

                    $recid = null;
                    $records = array();
                    $details = $dettypes;
                    $hasdetails = false;
                    //loop for records/details
                    while ($row = $res->fetch_row()) {  //$row = $res->fetch_assoc()) {
                        if($recid!=$row[0]){ //new line
                            if($hasdetails){
                                //print ">>>".$recid."  ADDED<br>";
                                array_push($details[0], $recid);
                                $records[$recid] = $details;
                                $details = $dettypes; //reset
                            }
                            $recid = $row[0];
                            $hasdetails = false;
                        }

                        $value = null;
                        if($row[3]){ //geographic
                            $value = $row[3];
                        }else if($row[4]){ //file id

                            $dtype = null;
                            $filename_orig = $row[7];
                            $value = $row[7]; //original filename
                            //array($row[5], $row[6], $row[7], 8); //path and name and origname, mimeext
                            if(in_array($row[8], $allowed_img_ext)){
                                $images[$row[4]] =  $filename_orig;
                                $dtype = 'IMAGE';
                            }else if(in_array($row[8], $allowed_doc_ext)){
                                $documents[$row[4]] =  $filename_orig;
                                $dtype = 'DOCUMENT';
                            }else{
                                $value = null;
                            }

                            if($value){

                                $imgfolder = $folder."/".strtolower($dtype);
                                if(!file_exists($imgfolder) && !mkdir($imgfolder, 0777, true)){
                                    print "<p class='error'>'Failed to create folder: ".$imgfolder."</p>";
                                }else{
                                    $imgfolder = $imgfolder."/".$row[4];
                                    if(!file_exists($imgfolder)){
                                        if(!mkdir($imgfolder, 0777, true)){
                                            print "<p class='error'>'Failed to create folder: ".$imgfolder."</p>";
                                        }else{
                                            if( file_exists($row[5].$row[6]) ){

                                                $filename = $imgfolder."/".$filename_orig;
                                                //copy file
                                                copy($row[5].$row[6], $filename);

                                                $f_name = mysqli__select_value($mysqli, 'select dtl_Value from recDetails where dtl_RecID='.$recid.' and dtl_DetailTypeID='.DT_NAME );
                                                $f_desc = mysqli__select_value($mysqli, 'select dtl_Value from recDetails where dtl_RecID='.$recid.' and dtl_DetailTypeID='.DT_SHORT_SUMMARY);
                                                if(!$f_desc){
                                                    $f_desc = mysqli__select_value($mysqli, 'select dtl_Value from recDetails where dtl_RecID='.$recid.' and dtl_DetailTypeID='.DT_EXTENDED_DESCRIPTION);
                                                }
                                                if(!$f_name){
                                                    $f_name = $dtype.' id '.$row[4];
                                                }
                                                if(!$f_desc){
                                                    $f_desc = $dtype.' from Heurist db '.HEURIST_DBNAME;
                                                }


                                                //create metadata
                                                $meta = getMetadata($f_name, $f_desc, $dtype);
                                                $metadata_content = $meta->asXML();
                                                $metadata_file = $imgfolder."/".strtolower($dtype).".xml";

                                                file_put_contents($metadata_file,  utf8_encode($metadata_content));

                                                //add command line to batch file
                                                $batch = $batch."\n java -cp  org.tdar.utils.CommandLineAPITool -http -username $fusername -password $fpwd -file ".$imgfolder."/ -host ".$host." -projectid ".$projectId;

                                                addRegistration($dtype, $metadata_file,  $metadata_content, $filename, $filename_orig);
                                            }else{
                                                print "<p class='error'>".$row[5].$row[6]."<br>File was not found. Please ".CONTACT_SYSADMIN."</p>";
                                            }
                                        }
                                    }
                                }

                            }


                        }else if($row[9]){ //term value
                            $value = $row[2];
                            //save into coding sheet
                            //$coding_sheet[$row[2]] = array($row[9], $row[10]);
                        }else if($row[2]){

                            if( $recstruc[$row[1]][3] == 'resource'  ) {   //
                                //find title and record type name for pointer
                                $pointer = mysqli__select_array($mysqli, 'select rec_ID, rty_Name, rec_Title  from Records, defRecTypes where rty_ID=rec_RecTypeID and rec_ID='.$row[2] );
                                if($pointer){
                                    $value = implode(':',$pointer); // 'RESOURCE'.$row[2];
                                }
                            }else{
                                $value = $row[2];
                            }
                        }


                        if($value){
                            /*if(!@$details[$row[1]]){ //such dettype already exists
                            $details[$row[1]] = array();
                            }*/

                            if(is_array(@$details[$row[1]])){
                                array_push($details[$row[1]], $value);
                                $hasdetails = true;
                            }
                        }
                    }//while
                    $res->close();

                    if($hasdetails){
                        array_push($details[0], $recid);
                        $records[$recid] = $details;
                    }

                    if(count($records)<1){
                        print "<p>No data found for record type ".$rt."</p>";
                        continue;
                    }


                    //create folder for particular record type
                    $rtfolder = $folder."/".$rt;
                    if (!mkdir($rtfolder, 0777, true)) {
                        print "<p class='error'>'Failed to create folder: ".$rtfolder."</p>";
                        continue;
                        //die('Failed to create folder: '.$rtfolder);
                    }

                    $filename = $rtfolder."/".$rt.".csv";
                    $fp = fopen($filename, 'w');

                    $fields = array();
                    foreach ($recstruc as $dt_id => $detail) {
                        array_push($fields, getProperName($detail[1]));
                    }
                    fputcsv($fp, $fields);

                    foreach ($records as $rec_id => $details) {
                        $fields = array();
                        foreach ($details as $detail) {
                            array_push($fields, implode("|", $detail));
                        }
                        fputcsv($fp, $fields);
                    }


                    /*
                    $recid = null;
                    $det_type = null;

                    //$outp = "";
                    $line = array();
                    $value = array();

                    while ($row = $res->fetch_row()) {  //$row = $res->fetch_assoc()) {

                    if($recid!=$row[0]){ //new line
                    if(count($value)>0){
                    array_push($line, implode("|",$value));
                    }
                    if(count($line)>0){
                    //$outp = $outp.implode(",",$line)."\n";
                    fwrite($fp, implode(",",$line)."\n");
                    print $recid."  ".implode(",",$line)."<br/><br/>";
                    }
                    $line = array();
                    $value = array();
                    $recid=$row[0];
                    }


                    if($det_type != $row[1]){
                    if(count($value)>0){
                    array_push($line, implode("|",$value));
                    }
                    $det_type = $row[1];
                    $value = array();
                    }

                    if($row[2]){
                    array_push($value, '"'.$row[2].'"');
                    }else{
                    array_push($value, "");
                    }
                    }//while

                    if(count($value)>0){
                    array_push($line, implode("|",$value));
                    }
                    if($line){
                    print ">>>".$recid."  ".implode(",",$line)."<br/>";
                    //$outp = $outp.implode(",",$line)."\n";
                    fwrite($fp, implode(",",$line)."\n");
                    }
                    $res->close();
                    */
                    fclose($fp);
                }

                //save csv output for dataset: records of particular record type
                //file_put_contents($rtfolder."/".$rt.".csv", $outp);


                $rectypedef = mysqli__select_array($mysqli, 'select rty_Name, rty_Description from defRecTypes where rty_ID='.$rt );

                //save metadata for dataset
                $meta = getMetadata($rectypedef[0], 'Heurist db '.HEURIST_DBNAME.'. Rectype#'.$rt.'. '.$rectypedef[1], 'DATASET');

                //$datatable = $meta->dataTables->addChild('dataTable');


                $dataTable = $meta->addChild('dataTables')->addChild('dataTable');
                $columns = $dataTable->addChild('dataTableColumns');
                $cnt = 0;
                foreach ($recstruc as $dt_id => $detail) {

                    $column = $columns->addChild('dataTableColumn');

                    if($detail[3]=="date" || $detail[3]=="year"){
                        $column->columnDataType = 'DATETIME';
                    }else if($detail[3]=="integer"){
                        $column->columnDataType = 'BIGINT';
                    }else if($detail[3]=="float"){
                        $column->columnDataType = 'DOUBLE';
                    }else if($detail[3]=="freetext"){
                        $column->columnDataType = 'VARCHAR';
                    }else if($detail[3]=="blocktext" || $detail[3]=="geo"){
                        $column->columnDataType = 'TEXT';
                    }else if($detail[3]=="boolean"){
                        $column->columnDataType = 'BOOLEAN';
                    }else if($detail[3]=="enum" || $detail[3]=="relationtype"){
                        $column->columnDataType = 'BIGINT';
                        $column->columnEncodingType = 'CODED_VALUE';
                    }

                    if($detail[3]=="enum" || $detail[3]=="relationtype"){
                        //find id of registered coding sheet
                        $regid = ""; //IT DOES NOT WORK in TDAR findCodingSheetId(($detail[3]=="enum")?$dt_id:"Reltypes");

                        $column->codingSheetRef = $regid;
                    }else{
                        $column->columnEncodingType = 'UNCODED_VALUE';
                    }

                    /* @todo - to understand what does it mean
                    $column->codingSheetRef = '';
                    $column->delimiterValue = '';
                    $column->mappingColumn = '';
                    $column->measurementUnit = '';
                    $column->name = '';
                    $column->length = '';
                    */
                    $column->description = $detail[2];
                    $column->displayName = $detail[1];
                    $column->ignoreFileExtension = 'false';
                    $column->mappingColumn = 'false';
                    $column->name = $detail[1]?getProperName($detail[1]):'noname'.$cnt;
                    $column->visible = 'true';

                    //ENUM('relmarker','enum','relationtype','resource','file','separator','calculated','fieldsetmarker','urlinclude')

                    $cnt++;
                }//foreach

                $dataTable->description = $rectypedef[1];
                $dataTable->name = 'H4_'.HEURIST_DBNAME.'_rectype_'.$rt;


                $metadata_content = utf8_encode($meta->asXML());
                file_put_contents($rtfolder."/dataset.xml", $metadata_content);

                //../ROOT/WEB-INF/lib/*:.

                $batch = $batch."\n java -cp  org.tdar.utils.CommandLineAPITool -http -username $fusername -password $fpwd -file ".$rtfolder."/ -host ".$host." -projectid ".$projectId;

                $folders = $rtfolder."/".(($folders!=null)?",":"").$folders;

                addRegistration("Dataset", $rtfolder."/dataset.xml",  $metadata_content, $filename, $rt);

                //break;
            } // end foreach ($rectyps as $rt)
            // END OF LOOP FOR EACH RECORD TYPE TO BE EXPORTED

            //print "Generation of output data have been completed<br/>";
            //print "Start upload/registration<br/>";


            //copy images
            //print "<p>Export images</p>";
            //copy used terms to coding sheet
            //print "<p>Create coding sheet from terms</p>";


            file_put_contents($folder."/loadtotdar.sh", $batch);

            // REGISTRATION
            if(count($data_toreg)<1){
                print "No data to export<br>";
                return;
            }

            $scount = array();

            foreach ($data_toreg as $regdata)
            {
                $dtype = $regdata[0];
                if(@$scount[$dtype]){
                    $scount[$dtype]++;
                }else{
                    $scount[$dtype] = 1;
                }
            }
            print "<br>";
            foreach ($scount as $dtype=>$cnt){
                print strtoupper($dtype)."S: ".$cnt."<br>";
            }
            print "<br><b>End of preparation data</b><br><br>";

            if($no_registration)
            {

                print "<p><b>You may verify the result in ".$folder."</b></p>";

            }else{

                doRegistration();

                print "<br><b>End of export</b><br/><br/>";
                print "<hr style='position:absolute;width:70%;max-width:700px;'/><br/><br/>";
                print '<b>tDAR Project Upload Summary: ID '.$projectId.'(created) <a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/project/'.$projectId.'"></a></b><br/><br/>';

                print "<p style='width:70%;max-width:700px;'>Heurist uploads individual record types, including relationships, as separate tDAR tables (storing the data as CSV files). However, not all metadata for uploaded tables can be pre-created, either because it does not exist in the Heurist database or because it is not handled by the tDAR API (programming interface).</p>";
                print "<p style='width:70%;max-width:700px;'>This is particularly the case for the metadata mapping which associates coding sheets and ontologies with columns in the tables. We therefore recommend that you edit the metadata for each of the uploaded tables through the web interface (you may wish to save this page for later reference):</p>";

                $datasets = "";
                $codingsheet = "";
                $images = "";
                $documents = "";
                foreach ($data_toreg as $regdata)
                {
                    $dtype = $regdata[0];
                    $rt = $regdata[4];
                    $regid = $regdata[5];

                    if($dtype == 'Dataset'){

                        $datasets = $datasets."<tr><td style='text-align:left;'>".$rt.' '.$rtStructs['names'][$rt]."</td>";
                        if($regid>0){
                            $datasets = $datasets.
                            '<td><a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/dataset/'.$regid.'/edit"></a></td><td>'.
                            '<a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/dataset/'.$regid.'"></a></td><td>'.
                            '<a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/dataset/'.$regid.'/columns"></a></td></tr>';
                        }else{
                            $datasets = $datasets."<td colspan='3'>FAILURE</td></tr>";
                        }


                    }else if($dtype == 'Coding Sheet'){

                        $codingsheet = $codingsheet.
                        "<tr><td style='text-align:left;'>".(is_numeric($rt)?'Terms for '.$dtStructs['names'][$rt]:$rt)."</td>";
                        if($regid>0){
                            $codingsheet = $codingsheet.
                            '<td><a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/coding-sheet/'.$regid.'/edit"></a></td><td>'.
                            '<a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/coding-sheet/'.$regid.'"></a></td><td>&nbsp;</td></tr>';
                        }else{
                            $codingsheet = $codingsheet."<td colspan='3'>FAILURE</td></tr>";
                        }

                    }else if($dtype == 'IMAGE'){

                        $images = $images."<tr><td style='text-align:left;'>".$rt."</td>";
                        if($regid>0){
                            $images = $images.
                            '<td><a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/image/'.$regid.'/edit"></a></td><td>'.
                            '<a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/image/'.$regid.'"></a></td><td>&nbsp;</td></tr>';
                        }else{
                            $images = $images."<td colspan='3'>FAILURE</td></tr>";
                        }

                    }else if($dtype == 'DOCUMENT'){

                        $documents = $documents."<tr><td style='text-align:left;'>".$rt."</td>";
                        if($regid>0){
                            $documents = $documents.
                            '<td><a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/document/'.$regid.'/edit"></a></td><td>'.
                            '<a class="externalLink" title="Open in new window" target="_blank" href="http://'.$host.'/document/'.$regid.'"></a></td><td>&nbsp;</td></tr>';
                        }else{
                            $documents = $documents."<td colspan='3'>FAILURE</td></tr>";
                        }

                    }

                }

                print "<table border='1' cellpadding='3' cellspacing='0' style='text-align:center;'>";
                print "<tr><td colspan='4' style='text-align:left;font-weight:bold'>Datasets (record types):</td></tr>";
                print "<tr><td width='200px'>name</td><td width='100px' >metadata</td><td width='100px'>general</td><td width='100px'>columns map</td></tr>".$datasets;
                print "<tr><td colspan='4' style='text-align:left;font-weight:bold'>Coding sheets (terms and relationship types):</td></tr>";
                print $codingsheet;
                print "<tr><td colspan='4' style='text-align:left;font-weight:bold'>Images:</td></tr>";
                print $images;
                print "<tr><td colspan='4' style='text-align:left;font-weight:bold'>Documents:</td></tr>";
                print $documents."</table><br><br>";
            }

            //-----------------------------------------------------------------------------
            function doRegistration(){
                global $data_toreg,$projectId,$host,$protocol,$cookie_file;

                foreach ($data_toreg as $idx=>$regdata)
                {
                    if($data_toreg[$idx][5]==0)  //not yet registred since coding sheets are registred before datasets
                    {
                        $metadata_file = $regdata[1];
                        $metadata_content = $regdata[2];
                        $filename = $regdata[3];

                        print "<br>Uploading file : ".$filename."<br>";
                        print "Send : ".(filesize($filename) % 1024)." kB ".date("F d Y H:i:s.", fileatime($filename))."<br>";
                        ob_flush();flush();

                        // TODO: this does not appear to be a valid URL. api directory was in /php/api which would not be reached by this path. /php/api is now /hsapi/controller
                        $url = $protocol.$host."/api/upload?uploadedItem=".urlencode($metadata_file);
                        $postdata = array(
                            //"uploadedItem" => $rtfolder."/dataset.xml",
                            API_FIELD_PROJECT_ID => $projectId,
                            API_FIELD_RECORD => $metadata_content,
                            API_FIELD_UPLOAD_FILE => "@".$filename,
                            "fileAccessRestriction" => "PUBLIC"
                        );  //, API_FIELD_UPLOAD_FILE => );

                        //$data_toreg[$idx][5] = 1;
                        //continue;
                        $resp = post_request($url, null, null, $postdata, $cookie_file, false); //WORKS!

                        // TO DO: TEMPORARY VERSION FOR TESTING, REPLACE WITH PARAMETEREISERD VERSION ABOVE
                        //$resp = http_post("115.146.85.232", "8080", "/api/upload", $fusername, $fpwd, $postdata);
                        //$postdata = array("loginUsername"=>$fusername, "loginPassword"=>$fpwd);
                        //$resp = http_post("115.146.85.232","8080", "/login/process", null, null, $postdata);

                        $regid = 0;

                        //print "tDAR server response:";
                        //print "<xmp>".$resp."</xmp>";

                        if($resp)
                        {
                            $resp = new SimpleXMLElement($resp);
                            if($resp->status == 'created') {
                                $regid = $resp->recordId;
                                print "Registred with id: ".$regid."<br>";
                            }else{
                                $regid = -1;
                                print "<p class='error'>'FAILURE:</p><xmp>".$resp->message."</xmp><br>";
                            }
                        }else{
                            $regid = -2;
                            print "<p class='error'>'Server does not return responce. It is assumed that registration is OK</p>";
                        }
                        ob_flush();flush();

                        //<apiResult> <status>badrequest</status> <message
                        //<apiResult> <status>created</status> <recordId>6923</recordId> <message>created:6923</message> </apiResult>

                        //add ID to result
                        $data_toreg[$idx][5] = $regid;
                    }
                }//for
            }

            /**
            * put your comment there...
            *
            * @param mixed $metadata_file  - path to metadata file
            * @param mixed $metadata_content - metadata content
            * @param mixed $filename - path to file to b uploaded
            *
            * $rt - recordtype, detailtype id, OR filename
            */
            function addRegistration($type, $metadata_file, $metadata_content, $filename, $rt=null){
                global $data_toreg;
                array_push($data_toreg, array($type, $metadata_file, $metadata_content, $filename, $rt, 0));
            }


            function findCodingSheetId($dtid){
                global $data_toreg;

                foreach ($data_toreg as $idx=>$regdata){
                    if($regdata[0]=="Coding Sheet" && $regdata[4]==$dtid){
                        return ($regdata[5]>0) ?$regdata[5]: "";
                    }
                }
                return "";
            }


            /**
            * put your comment there...
            *
            * @param mixed $title
            * @param mixed $description
            * @param mixed $dtype
            * @return SimpleXMLElement
            */
            function getMetadata($title, $description, $dtype){

                if($dtype=='CODING_SHEET'){
                    $ele_dtype = 'codingSheet';
                }else{
                    $ele_dtype = strtolower($dtype);
                }


                $meta = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tdar:'.$ele_dtype.' xmlns:tdar="http://www.tdar.org/namespace" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://localhost:8180/schema/current schema.xsd" />');

                $meta->description = $description?$description:'no description';
                $meta->resourceType = $dtype;
                $meta->title = $title;
                $meta->date = '2013';
                $meta->dateNormalized = '2013';

                $meta->externalReference='false';
                $meta->inheritingCollectionInformation='false';
                $meta->inheritingCulturalInformation='false';
                $meta->inheritingIdentifierInformation='false';
                $meta->inheritingInvestigationInformation='false';
                $meta->inheritingMaterialInformation='false';
                $meta->inheritingNoteInformation='false';
                $meta->inheritingOtherInformation='false';
                $meta->inheritingSiteInformation='false';
                $meta->inheritingSpatialInformation='false';
                $meta->inheritingTemporalInformation='false';
                $meta->relatedDatasetData='';


                if($dtype=='CODING_SHEET'){
                    $meta->generated='false';
                }

                return $meta;
            }


            // remove directory recursively
            function rrmdir($dir) {
                if (is_dir($dir)) {
                    $objects = scandir($dir);
                    foreach ($objects as $object) {
                        if ($object != "." && $object != "..") {
                            if (filetype($dir."/".$object) == "dir"){
                                rrmdir($dir."/".$object);
                            } else {
                                unlink   ($dir."/".$object);
                            }
                        }
                    }
                    reset($objects);
                    rmdir($dir);
                }
            }

            function post_request($url, $fusername, $fpwd, $postdata, $cookie_file, $is_save_cookies){


                //print "DEBUG>".HEURIST_HTTP_PROXY_AUTH."   ".HEURIST_HTTP_PROXY."<br>";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, $is_save_cookies); //Tell curl not to return headers, but do return the response
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );

                if($cookie_file){
                    if($is_save_cookies){
                        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
                    }else{
                        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
                    }
                }

                //curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                if($is_save_cookies){ //authorization
                    curl_setopt($ch, CURLOPT_USERPWD, $fusername.":".$fpwd);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY); //CURLAUTH_ANY
                }
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                /*
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                */
                if(defined("HEURIST_HTTP_PROXY")){
                    curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
                    if(defined("HEURIST_HTTP_PROXY_AUTH")){
                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
                    }
                }

                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);

                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);
                if(!(strpos($statusCode,"2")===0 || strpos($statusCode,"3")===0)){ //success or redirect
                    $response = "ERROR";
                }

                //return $statusCode."  ".$response;
                return $response;

            }

            function http_post($server, $port, $url, $fusername, $fpwd, $vars) {

                // get urlencoded vesion of $vars array
                $urlencoded = "";

                foreach ($vars as $Index => $Value)
                    $urlencoded .= urlencode($Index ) . "=" . urlencode($Value) . "&";

                $urlencoded = substr($urlencoded,0,-1);

                if(!$fusername){
                    $headers = "POST $url HTTP/1.0\n"
                    . "Content-Type: application/x-www-form-urlencoded\n"
                    . "Content-Length: ". strlen($urlencoded) . "\n\n";
                }else{
                    $headers = "POST $url HTTP/1.1\r\n".
                    "Content-Type: application/x-www-form-urlencoded\n".
                    "User-Agent: PHP-Code\n".
                    "Content-Length: " . strlen($urlencoded) . "\n\n".
                    "Authorization: Basic ".base64_encode($fusername.':'.$fpwd)."\n"
                    ."Connection: close\n\n";
                }

                $fp = fsockopen($server, $port, $errno, $errstr, 10);
                if (!$fp) return "ERROR: fsockopen failed.\r\nError no: $errno - $errstr";

                fputs($fp, $headers);
                fputs($fp, $urlencoded);

                $ret = "";
                while (!feof($fp)) $ret .= fgets($fp, 1024);

                fclose($fp);
                return $ret;
            }

            function getProperName($name){
                $goodname = preg_replace('~[^a-z0-9]+~i','_', $name);
                return str_replace('__','_',$goodname);
            }

            function mysqli__select_array($mysqli, $query) {
                $result = null;
                if($mysqli){
                    $res = $mysqli->query($query);
                    if($res){
                        $row = $res->fetch_row();
                        if($row){
                            $result = $row;
                        }
                        $res->close();
                    }
                }
                return $result;
            }

            function mysqli__select_value($mysqli, $query) {
                $row = mysqli__select_array($mysqli, $query);
                if($row && @$row[0]){
                    $result = $row[0];
                }else{
                    $result = null;
                }
                return $result;
            }

            function fillTerms($datatype, $termTree, $parentname){

                global $dtTerms, $ind_label, $ind_descr, $coding_sheet, $ind_tcode;
                $termLookup = $dtTerms['termsByDomainLookup'][$datatype];

                foreach ($termTree as $termid=>$child_terms){

                    $termName = @$termLookup[$termid][$ind_label];

                    if($termName){
                        if(count($child_terms)>0){
                            fillTerms($datatype, $child_terms, $parentname.$termName."-");
                        }else{

                            $termCode = @$termLookup[$termid][$ind_tcode];
                            $termDescr = @$termLookup[$termid][$ind_descr];
                            $termCode = ($termCode)?"[".$termCode."]":"";

                            $coding_sheet[$termid] = array( $parentname.$termName.$termCode, $termDescr);
                        }
                    }
                }
            }

            //
            // dash separated list of term label and all its parents (similar in exportFAIMS.php)
            //
            function getFullTermName($term, $datatype){

                global $dtTerms, $ind_parentid, $ind_label;

                $name = "";

                if($term){
                    if($term[$ind_parentid]>0){
                        $allterms = $dtTerms['termsByDomainLookup'][$datatype];
                        $name = getFullTermName( @$allterms[$term[$ind_parentid]], $datatype);
                    }
                    $name = ($name?$name."-":"").$term[$ind_label];
                }
                return $name;
            }
            ?>
        </div>
    </body>
</html>

