<?php
/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/**
*
* syncFAIMS.php : imports database structure plus data from FAIMS sqlite database
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
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

if(isForAdminOnly("to sync (import FAIMS --> Heurist) FAIMS database")){
    return;
}
/*
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../search/actions/actionMethods.php');
*/

//@todo HARDCODED id of OriginalID
$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
if($dt_SourceRecordID==0){
    print "Detail type 'source record id' not defined";
    return;
}

$dt_Geo = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);

?>
<html>
    <head>
        <title>Faims sync</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">

        <style>
            .err_message {
                color:red;
                border-bottom: 1px solid black;
                text-transform:uppercase;
                padding-top:5px;
                padding-bottom:5px;
            }
            .lbl_form{
                width:230px;
                display:inline-block;
                text-align:right;
                padding:14px;
            }
        </style>
    </head>
    <body style="padding:44px;" class="popup">

        <script>
            function showErrorMessage(smessage){
                showFinalMessage(smessage, true);
            }
            function showFinalMessage(smessage, iserror){
                var ele = document.getElementById("topmessage");
                ele.style.display = 'block';
                ele.innerHTML = smessage;
                if(iserror){
                    ele.style.color = 'red';
                }
                alert(smessage);
            }
        </script>

        <div class="banner"><h2>FAIMS sync</h2></div>
        <div id="page-inner" style="margin:0px 5px; padding: 0.5em;">
            <div id="topmessage"></div>
            <?php

            if ( !class_exists('SQLite3' ) ) {
                print "<div style='color:red; font-weight:bold;padding:10px'>FAIMS synchronisation requires installation of the SQLite3 extension to PHP."
                ." Please ask your system administrator to install this extension. See <put in appropriate URL to doco> for installation information.</div></body></html>";
                exit();
            }

            if(!openSQLiteDb(':memory:')){
                exit();
            }


            $step = @$_REQUEST['step'];

            $mode_dir = @$_REQUEST['mode'];
            if(!$mode_dir) $mode_dir = 1; //by default - file
            if($mode_dir==2 && !defined('HEURIST_FAIMS_DIR')){
                $mode_dir = 1;
                $step = 0;
            }

            $upload = @$_FILES["file"];
            $dbname_faims = null;

            if($step=='1'){

                if($mode_dir==1){

                    if ($upload["error"] > 0 && $upload["error"] < 4)
                    {
                        echo "<p class='err_message'>Upload error: ".$upload["error"]."</p>";
                        if($upload['error']==1){
                            echo "<p class='err_message'>The uploaded file exceeds the upload_max_filesize directive in php.ini.</p>";
                        }else if($upload['error']==1){
                            echo "<p class='err_message'>Failed to write file to disk.</p>";
                        }

                    }else if(!@$upload["tmp_name"]){

                        echo "<p class='err_message'>You have to upload FAIMS project tar or sqlite database file</p>";

                    }else{
                        //untar archive and place db in faims folder, or just copy database to this folder

                        $tmp_name = $upload["tmp_name"];

                        if(strpos($upload["name"],".tar.bz")<0){
                            $project_name = substr($upload["name"],0,strpos($upload["name"],".tar.bz2"));
                        }else{
                            $project_name = substr($upload["name"],0,strpos($upload["name"],".tar.bz"));
                        }

                        $folder = HEURIST_FILESTORE_DIR."faims/import"; //.;
                        $folder_proj = $folder."/".$project_name; //"/module";

                        //create export folder
                        if(!file_exists($folder)){
                            $old_umask = umask(0);
                            if (!mkdir($folder, 0777, true)) {
                                umask($old_umask);
                                die("Failed to create folder: ".$folder);
                            }
                            umask($old_umask);
                        }else{ //clear folder
                            delFolderTree($folder, false);
                        }

                        if($upload["name"]=="db.sqlite3"){

                            $dbname_faims = $folder . "/" . $upload["name"];

                            if(copy($tmp_name, $dbname_faims)){
                                unlink($tmp_name);
                            }else{
                                die('Cannot copy file '.$upload["name"].' to '.$folder);
                            }


                        }else{

                            $tarfile_orig = $upload["name"];
                            $tarfile = $folder . "/project.".(strpos($upload["name"],".tar.bz")>0?"bz2":"gz");

                            /*debug print "<br>temp :".$tmp_name;
                            print "<br>".$upload["name"]."   ".$tarfile."<br>";
                            */

                            print "<h3>Extracting FAIMS database from tarball to<br />".$folder_proj."/db.sqlite3</h3><br>";
                            ob_flush();flush();


                            //echo  str_replace("(","\\(",$tarfile)."DEBUG>".$tarfile."<br>";

                            if(copy($tmp_name, $tarfile)){
                                unlink($tmp_name);
                            }else{
                                die('Cannot copy file '.$upload["name"].' to '.$folder);
                            }

                            //$cmdline = "tar --extract --file=".$tarfile." db.sqlite3";
                            //$cmdline = "tar -xvf ".$tarfile; //." #utar/project/db.sqlite3";
                            $cmdline = "tar -xvf ".$tarfile." -C ".$folder;
                            //tar --extract --file={tarball.tar} {file}
                            //tar -xvf {tarball.tar} {path/to/file}

                            /*debug print "<br>cmdline :".$cmdline; */

                            $res1 = 0;
                            $output1 = exec($cmdline, $output, $res1);
                            if ($res1 != 0 ) {
                                echo "<p class='err_message'>Error code $res1: Unable to extract database from archive $tarfile_orig<br><br>";
                                //echo print_r($output1, true)."<br>out=";
                                //echo print_r($output, true);
                                echo "</p>";
                            }

                            $dbname_faims = $folder_proj."/db.sqlite3";
                        }

                    }

                }elseif($mode_dir==2){
                    $dbname_faims = @$_REQUEST['faims_module'];
                }else{
                    $dbname_faims = @$_REQUEST['faims'];
                }

            }

            if( !$dbname_faims ||  !file_exists($dbname_faims)){

                if($dbname_faims && !file_exists($dbname_faims)){
                    print "<div class='err_message'>Database file: $dbname_faims was not found</div>";
                }


                //$dbname_faims = HEURIST_FILESTORE_DIR."faims/db2.sqlite3";
                //$dbname_faims = HEURIST_FILESTORE_DIR."faims/tracklog/db.sqlite3";
                // /var/www/HEURIST/HEURIST_FILESTORE/johns_FAIMS_UAT_test/faims/tracklog/db.sqlite3
                // /var/www/HEURIST/HEURIST_FILESTORE/johns_FAIMS_UAT_test/faims/syncdemo/db.sqlite3
                if(!$dbname_faims){
                    $dbname_faims = "/var/www/faims-server/";
                    //$dbname_faims = "/var/www/HEURIST/HEURIST_FILESTORE/artem_test/faims2/db.sqlite3";
                }

                print "<form name='selectdb' action='syncFAIMS.php' method='post' enctype='multipart/form-data'>";
                print "<input name='step' value='1' type='hidden'>";
                print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
                print "<div><input type='radio' ".($mode_dir==1?"checked='true'":"")." name='mode' value='1'><div class='lbl_form'>Upload FAIMS db or project tar:</div><input type='file' name='file'></div>";
                print "<div><input type='radio' ".($mode_dir==0?"checked='true'":"")." name='mode' value='0'><div class='lbl_form'>Or specify server path to FAIMS database:</div><input name='faims' value='".$dbname_faims."' size='80'></div>";
                if(defined('HEURIST_FAIMS_DIR')){
                    $options = getListOfModules();
                    if(""==$options){
                        print "<div><input type='radio' disabled><div class='lbl_form'>Or select from the list of modules:</div>No module tarballs available in path: ".HEURIST_FAIMS_DIR."</div>";
                    }else{
                        print "<div><input type='radio' ".($mode_dir==2?"checked='true'":"")." name='mode' value='2'><div class='lbl_form'>Or select from the list of modules:</div><select name='faims_module'>".$options."</select></div>";
                    }
                }else{
                    print "<div>If you need to import existing FAIMS modules from the FAIMS server, ask the system administrator to configure the path for module tarball files in heuristConfigIni.php</div>";
                }

                print "<div><div class='lbl_form'>Check to verify structure (no data import):</div><input name='showstr' value='1' type='checkbox'></div>";
                print "<div><div class='lbl_form'></div><input type='submit' value='Start' /></div>";
                print "</form></div></body></html>";
                exit();
            }

            // $dir_faims.
            // $dbname_faims = HEURIST_FILESTORE_DIR. "faims/db2.sqlite3";

            $mysqli = mysqli_connection_overwrite(DATABASE);

            print "<h3>Extracted: ".$dbname_faims."</h3><br />";

            if(!file_exists($dbname_faims)){
                print "DB file not found";
                exit();
            }

            $dbfaims = openSQLiteDb($dbname_faims);
            if ( !$dbfaims ) {
                print "<div style='color:red; font-weight:bold;padding:10px'>Cannot open the database</div>";
                exit();
            }
            $dbfaims->close();

            print "<br>";
            /* THIS IS ORIGINAL BRIAN QUERY TO RETRIEVE SPATIAL DATA
            $query = "SELECT uuid, ST_asWKT(transform(casttosingle(geospatialcolumn), 3857)) as Coordinate, group_concat(attributename||': '||freetext, ', ') as note
            FROM aentvalue
            JOIN attributekey USING (attributeid)
            LEFT OUTER JOIN vocabulary USING (vocabid, attributeid)
            JOIN (SELECT uuid, attributeid, max(valuetimestamp) as valuetimestamp, max(aenttimestamp) as aenttimestamp, archentity.deleted as entDel, aentvalue.deleted as valDel
            FROM aentvalue
            JOIN archentity USING (uuid)

            GROUP BY uuid, attributeid
            HAVING MAX(ValueTimestamp)
            AND MAX(AEntTimestamp)) USING (uuid, attributeid, valuetimestamp)
            JOIN archentity using (uuid, aenttimestamp)
            WHERE entDel is NULL
            AND valDel is NULL
            group by uuid
            ORDER BY uuid, attributename ASC";
            //-- AND uuid = ?

            print "<br>";

            $rs = $db->query($query);
            while ($row = $rs->fetchArray(SQLITE3_ASSOC))
            {
            print print_r($row, true)."<br>";
            }
            exit();
            */

            /* database structure
            print "<h3>Tables</h3>";
            $query =  "SELECT name FROM sqlite_master WHERE type='table'";
            foreach ($dbfaims->query($query) as $row)
            {
            echo $row[0]."<br>";
            }*/

            if(@$_REQUEST['showstr']){   //show strucuture only

                print "<h3>STRUCTURE</h3><br>";

                // AttributeKey -> defDetailTypes
                print "<h3>AttributeKey (fields)</h3><br><table>";
                $query =  "SELECT AttributeID, AttributeType, AttributeName, AttributeDescription FROM AttributeKey";
                $rs = $dbfaims->query($query);
                $fields = array();
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    echo "<tr><td width='150px'>".$row[0]."</td><td>".$row[1]."</td><td>".trunc50($row[2])."</td></tr>";
                    $fields[$row[0]] = $row[2];
                }
                print "</table>";

                //AEntType - defRecTypes   IdealAEnt - defRecStrucutre
                print "<h3>Entity type (record types and structures)</h3><br><table>";
                $query =  "SELECT AEntTypeID, AEntTypeName, AEntTypeCategory, AEntTypeDescription FROM AEntType";
                $rs = $dbfaims->query($query);
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {

                    echo "<tr style='font-weight:bold'><td width='150px'>".$row[0]."</td><td width='150px'>".$row[1]."</td><td>".trunc50($row[3])."</td></tr>";

                    $query2 =  "SELECT AEntTypeID, AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".$row[0];
                    $rs2 = $dbfaims->query($query2);
                    while ($row2 = $rs2->fetchArray(SQLITE3_NUM))
                    {

                        if(trim($row2[2])==""){
                            $sdesc = $fields[$row2[1]];
                        }else{
                            $sdesc = $row2[2];
                        }

                        echo "<tr><td>&nbsp;</td><td>".$row2[1]."</td><td>".trunc50($sdesc)."</td></tr>";
                    }
                }
                print "</table>";

                print "<h3>'Container' relationship types (will be mapped to separate record type)</h3><br><table>";
                $query =  'SELECT "RelnTypeID", "RelnTypeName", "RelnTypeDescription" FROM RelnType where RelnTypeCategory="container"';
                $rs = $dbfaims->query($query);
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    echo "<tr style='font-weight:bold'><td width='150px'>".$row[0]."</td><td width='150px'>".$row[1]."</td><td>".trunc50($row[3])."</td></tr>";

                    $query2 =  'SELECT "RelnTypeID", "AttributeID", "RelnDescription", "IsIdentifier", "MinCardinality", "MaxCardinality" FROM "IdealReln"
                    where RelnTypeID='.$row[0];
                    $rs2 = $dbfaims->query($query2);
                    while ($row2 = $rs2->fetchArray(SQLITE3_NUM))
                    {
                        if(trim($row2[2])==""){
                            $sdesc = $fields[$row2[1]];
                        }else{
                            $sdesc = $row2[2];
                        }

                        echo "<tr><td>&nbsp;</td><td>".$row2[1]."</td><td>".trunc50($sdesc)."</td></tr>";
                    }
                }
                print "</table>";


                // Vocabulary  -> defTerms
                print "<h3>Vocabulary (terms)</h3><br><table>";
                $query =  "SELECT VocabID, AttributeID, VocabName FROM Vocabulary";
                $rs = $dbfaims->query($query);
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    //echo $row[0]."  ".$row[2]."<br>";
                    echo "<tr><td width='150px'>".$row[0]."</td><td>".$row[2]."</td></tr>";
                }
                print "</table>";

                // Relationship types  -> defTerms
                print "<h3>Relationship types (terms)</h3><br><table>";
                $query =  "SELECT RelnTypeID, RelnTypeName, RelnTypeCategory, Parent, Child FROM RelnType";  //RelnTypeCategory='bidirectional'

                $rs = $dbfaims->query($query);
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    echo "<tr><td width='150px'>".$row[0]."</td><td>".$row[1]."</td><td>".$row[2]."</td><td>".$row[3]."</td><td>".$row[4]."</td></tr>";
                }
                print "</table>";


                exit();
            }

            print "<h3>Sync structure</h3><br>";

            $rectypeMap = array();
            $detailMap = array();
            $termsMap = array();
            $reltypeMap = array();

            print "<h3>Adding fields and terms</h3><br>";

            //create/update defDetailTypes on base of AEntValue
            $query1 =  "SELECT AttributeID, AttributeName, AttributeType, AttributeDescription FROM AttributeKey";

            $res1 = $dbfaims->query($query1);
            while ($row1 = $res1->fetchArray(SQLITE3_NUM))
            {
                $attrID = $row1[0];

                //try to find correspondant dettype in Heurist
                $row = mysqli__select_array($mysqli, "select dty_ID, dty_Name, dty_JsonTermIDTree, dty_Type from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$attrID."'");
                if($row){

                    print  "<div style='color:gray'>Field ".$row[0]." already exists. Based on FAIMS ".$attrID." | ".$row[1]."</div>";

                    $dtyId = $row[0];
                    $dtyName = $row[1];
                    $vocabID = $row[2];

                    $detailMap[$attrID] = array($row[0], $row[3]);
                }else{
                    $dtyName = $row1[1] ?$row1[1] :"FAIMS_ID_".$attrID;

                    //rename existing to deprecated
                    renameExisitingDt($dtyName, 0, 1);

                    $fid = 'FAIMS.'.$attrID;
                    $ftype = faimsToHeurist_dt_mapping($row1[2]);
                    //print "DEBUG>".$fid." ".$row1[1];
                    $dtyName = $row1[1] ?$row1[1] :"FAIMS_ID_".$attrID;
                    $dtyDescr = trim($row1[3]." FAIMS ID ".$attrID);


                    //add new detail type into HEURIST
                    $query = "INSERT INTO defDetailTypes (dty_Name, dty_HelpText, dty_Documentation, dty_Type, dty_NameInOriginatingDB) VALUES (?,?,?,?,?)";
                    $stmt = $mysqli->prepare($query);
                    if(!$stmt){
                        print "<script>showErrorMessage('".addslashes("ERROR: Field type not inserted for:  FAIMS"
                            .$attrID." | ".$row1[1]." | ".trunc50($row1[3])." <br> "
                            ."SQL message:".$mysqli->error)."')</script></body></html>";
                        exit();
                    }

                    $stmt->bind_param('sssss', $dtyName, $dtyDescr, $dtyDescr, $ftype, $fid);
                    if(!$stmt->execute()){
                        print "<script>showErrorMessage('".addslashes("ERROR: Field type not inserted for:  FAIMS"
                            .$attrID." | ".$row1[1]." | ".trunc50($row1[3])." <br> "
                            ."SQL message:".$mysqli->error)."')</script></body></html>";

                        exit();
                    }

                    $dtyId = $stmt->insert_id;
                    $dtyName = $row1[1];
                    $vocabID = null;

                    $stmt->close();

                    $detailMap[$attrID] = array($dtyId, $ftype);

                    print  "<div>Added field ".$dtyId."  based on FAIMS ".$attrID." | ".$row1[1]." | ".trunc50($row1[3])."</div>";
                }


                //if AttributeKey has Vocabulary entries it will be ENUM on Heurist
                $query2 = "select VocabID, VocabName from Vocabulary where AttributeID=".$attrID;
                $vocabs = $dbfaims->query($query2);
                while ($row_vocab = $vocabs->fetchArray(SQLITE3_NUM))
                {
                    if(!$vocabID){ // vocabulary not defined
                        //make shure that we have parent term in Heursit and our detail type refers to this vocabulary (parent term)
                        $query = "INSERT INTO defTerms (trm_Label, trm_Description, trm_Domain) VALUES (?,?,'enum')";
                        $stmt = $mysqli->prepare($query);
                        $flbl = $dtyName.' [vocab]';
                        $fdesc = 'Vocabulary for detailtype '.$dtyName;
                        $stmt->bind_param('ss', $flbl , $fdesc );
                        $stmt->execute();
                        $vocabID = $stmt->insert_id;
                        $stmt->close();

                        //set vocabulary ID in field type
                        $query = "UPDATE defDetailTypes set dty_Type='enum', dty_JsonTermIDTree=$vocabID where dty_ID=$dtyId";
                        $stmt = $mysqli->prepare($query);
                        $stmt->execute();
                        $stmt->close();
                    }


                    $row = mysqli__select_array($mysqli, "select trm_ID, trm_Label from defTerms where trm_NameInOriginatingDB='FAIMS.".$row_vocab[0]."'");
                    if($row){

                        print  "<div style='color:gray;padding-left:100px;'>Term ".$row[0]." already exists. Based on FAIMS ".$row_vocab[0]." | ".$row[1]."</div>";

                        $termsMap[$row_vocab[0]] = $row[0];
                    }else{
                        //add new term into HEURIST
                        $query = "INSERT INTO defTerms (trm_Label, trm_Domain, trm_NameInOriginatingDB, trm_ParentTermID) VALUES (?,'enum',?, $vocabID)";
                        $stmt = $mysqli->prepare($query);
                        $fid = 'FAIMS.'.$row_vocab[0];
                        $flbl = $row_vocab[1];
                        if(strpos($flbl,"{")===0){
                            $flbl = substr($flbl,1, strlen($flbl)-2 );
                        }
                        $stmt->bind_param('ss', $flbl, $fid );
                        $stmt->execute();
                        $trm_ID = $stmt->insert_id;
                        $stmt->close();

                        $termsMap[$row_vocab[0]] = $trm_ID;

                        print  "<div style='padding-left:100px;'>Added term ".$trm_ID." based on FAIMS ".$row_vocab[0]." | ".$row_vocab[1]."</div>";
                    }//add terms

                }


            }//for attributes

            //----------------------------------------------------------------------------------------

            print "<h3>Relationship types</h3><br>";

            //find parent term for FAIMS relationships
            $parentTermID = null;
            $row = mysqli__select_array($mysqli, "select distinct trm_ParentTermID from defTerms where trm_Domain='relation' and trm_NameInOriginatingDB like 'FAIMS%'");
            if($row){
                $parentTermID = $row[0];
            }else{
                $query = "INSERT INTO defTerms (trm_Label, trm_Description, trm_Domain) VALUES (?,?,'relation')";
                $stmt = $mysqli->prepare($query);
                $flbl = 'FAIMS relationships';
                $fdesc = 'Relationship types in FAIMS database';
                $stmt->bind_param('ss', $flbl , $fdesc );
                $stmt->execute();

                $parentTermID = $stmt->insert_id;
                $stmt->close();
            }


            $query1 =  "SELECT RelnTypeID, RelnTypeName, RelnTypeCategory, Parent, Child FROM RelnType";  //RelnTypeCategory='bidirectional'

            $rs = $dbfaims->query($query1);

            //    if(!$rs){print "ERROR:".$dbfaims->lastErrorMsg();}

            while ($row_vocab = $rs->fetchArray(SQLITE3_NUM))
            {

                $is_hierarchy = ($row_vocab[2]=="hierarchy" && $row_vocab[3] && $row_vocab[4]);

                $row = mysqli__select_array($mysqli, "select trm_ID, trm_Label from defTerms where trm_NameInOriginatingDB='FAIMS.".$row_vocab[0]."'");

                if($row){

                    //reltype already exists- fill $reltypeMap

                    if($is_hierarchy){
                        $parent = $row[0];
                        $row = mysqli__select_array($mysqli, "select trm_ID, trm_Label from defTerms where trm_NameInOriginatingDB='FAIMS.".$row_vocab[0]."_child'");
                        if(null==$row || !$row[0]){
                            $trm_ID = addChildRelType($parentTermID, $parent, $row_vocab[0], $row_vocab[4]);
                        }else{
                            $trm_ID = $row[0];
                        }
                        $reltypeMap[$row_vocab[0]] = array($row_vocab[3]=>$parent, $row_vocab[4]=>$trm_ID);   // hierarchy - contains two heurist id - parent and child
                        print  "<div style='color:gray;padding-left:50px;'>Relationship type hierarchy already exists based on FAIMS:".$row_vocab[0]
                        ." | Parent term ID:"
                        .$parent." (FAIMS:".$row_vocab[3].") and child term ID:" .$trm_ID." (FAIMS:".$row_vocab[4].")</div>";

                    }else{
                        print  "<div style='color:gray;padding-left:50px;'>Relationship type already exists based on FAIMS:".$row_vocab[0]
                        ." | ".$row[0]." | ".$row[1]."</div>";
                        $reltypeMap[$row_vocab[0]] = $row[0];
                    }


                }else{

                    //add new term into HEURIST
                    $query = "INSERT INTO defTerms (trm_Label, trm_Domain, trm_NameInOriginatingDB, trm_ParentTermID) VALUES (?,'relation',?, ".$parentTermID.")";
                    $stmt = $mysqli->prepare($query);

                    $fid = 'FAIMS.'.$row_vocab[0];
                    $nameorig = $is_hierarchy?$row_vocab[3]:$row_vocab[1];
                    $stmt->bind_param('ss', $nameorig, $fid );
                    $stmt->execute();

                    $trm_ID = $stmt->insert_id;
                    $parent = $trm_ID;
                    $stmt->close();

                    if($is_hierarchy){ //add child reltype

                        $parent = $trm_ID;

                        $trm_ID = addChildRelType($parentTermID, $parent, $row_vocab[0], $row_vocab[4]);

                        $reltypeMap[$row_vocab[0]] = array($row_vocab[3]=>$parent, $row_vocab[4]=>$trm_ID);
                        print  "<div style='padding-left:50px;'>Added Relationship type Hierarchy based on FAIMS: ".$row_vocab[0]
                        .". Parent term ID:".$parent." (FAIMS:".$row_vocab[3].") and child term ID:" .$trm_ID." (FAIMS:".$row_vocab[4].")<br/>";

                    }else{
                        $reltypeMap[$row_vocab[0]] = $trm_ID;
                        print  "<div style='padding-left:50px;'>Added Relationship type ".$trm_ID."  based on FAIMS ".$row_vocab[0]." | ".$row_vocab[1]."</div>";
                    }


                }//add terms
            }


            //----------------------------------------------------------------------------------------

            print "<h3>Rectypes</h3><br>";


            //create/update defRecTypes/defRecStrucure on base of AEntType and IdealAEnt
            $query1 =  "SELECT AEntTypeID, AEntTypeName, AEntTypeDescription FROM AEntType";
            $res1 = $dbfaims->query($query1);
            while ($row1 = $res1->fetchArray(SQLITE3_NUM))
            {
                $attrID = $row1[0];
                $rtyName = $row1[1] ?$row1[1] :"FAIMS_ID_".$attrID;
                $rtyDescr = $row1[2] ?$row1[2] :"FAIMS ID ".$attrID;

                $currTitleMask = null;
                $rtyId = null;

                //try to find correspondant rectype in Heurist
                $row = mysqli__select_array($mysqli, "select rty_ID, rty_Name, rty_TitleMask from defRecTypes where rty_NameInOriginatingDB='FAIMS.".$attrID."'");
                if($row){ //already exists

                    print  "<div style='color:gray'>Record type ".$row[0]." already exists. Based on FAIMS ".$attrID." | ".$row[1]."</div>";

                    $rtyId = $row[0];
                    $rtyName = $row[1];
                    $currTitleMask = $row[2];

                    $rectypeMap[$attrID] = $row[0];
                }else{

                    //rename existing to deprecated
                    renameExisitingRt($rtyName, 0, 1);

                    //add new record type into HEURIST
                    $query = "INSERT INTO defRecTypes (rty_Name, rty_TitleMask, rty_Description, rty_NameInOriginatingDB) VALUES (?,'Record #[ID]',?,?)";
                    $stmt = $mysqli->prepare($query);
                    $fid = 'FAIMS.'.$attrID;

                    $stmt->bind_param('sss', $rtyName, $rtyDescr, $fid);
                    $stmt->execute();

                    $rtyId = $stmt->insert_id;
                    if($rtyId<1){
                        print "<script>showErrorMessage('".addslashes("ERROR: Reord type not inserted for:  FAIMS"
                            .$attrID." | ".$row1[1]." | ".trunc50($row1[2])." <br> "
                            ."SQL message:".$mysqli->error)."')</script></body></html>";

                        exit();
                    }


                    $stmt->close();

                    $currTitleMask = "Record #[ID]";

                    $rectypeMap[$attrID] = $rtyId;

                    print  "<div>Added Record type ".$rtyId." based on FAIMS ".$attrID." | ".$rtyName." | ".trunc50($rtyDescr)."</div>";
                }

                //if AEntType has strucute described in IdealAEnt

                $titleMask = "";

                $query2 =  "SELECT AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".$attrID;

                $recstructure = $dbfaims->query($query2);
                while ($row_recstr = $recstructure->fetchArray(SQLITE3_NUM))
                {

                    $dt_Id = null;

                    $row = mysqli__select_array($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName, rst_DetailTypeID from defDetailTypes d, defRecStructure r ".
                        "where d.dty_ID=r.rst_DetailTypeID and r.rst_RecTypeID=$rtyId and d.dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");

                    if($row){  //such detal in structure already exists

                        $dt_Id = $row[2];
                        print  "<div style='color:gray;padding-left:100px'>Field ".$row[0]." is already in structure. Based on FAIMS ".$row_recstr[0]." | ".$row[1]."</div>";

                    }else{

                        $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");
                        if($row3){
                            //add new detail type into HEURIST
                            $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                            $stmt = $mysqli->prepare($query);
                            $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                            $stmt->execute();

                            $stmt->close();

                            $dt_Id = $row3[0];

                            print "<div style='color:gray;padding-left:100px'>Added field ".$row3[0]." to structure. Based on FAIMS ".$row_recstr[0]." | ".$row3[1]."</div>";
                        }else{
                            print  "<div style='color:red;padding-left:100px'>FIELD NOT ADDED TO STRUCTURE. IT WAS NOT FOUND. FAIMS:".$row_recstr[0]." !</div>";
                        }
                    }

                    if($dt_Id && $row_recstr[2]){ //isIdentifier
                        $titleMask = $titleMask."[".$dt_Id."] ";
                    }

                }//for add details for structure

                //update title mask
                if($currTitleMask!=$titleMask){
                    print "<div>Titlemask is updated: ".$titleMask."</div>";
                    $query = "UPDATE defRecTypes set rty_TitleMask =? where rty_ID=".$rtyId;
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param('s', $titleMask);
                    $stmt->execute();
                    $stmt->close();
                }

                //verify spatial data for this record type
                if($use_Spatialite && isset($dt_Geo) && $dt_Geo>0)
                {
                    $row = mysqli__select_array($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName from defRecStructure r ".
                        "where r.rst_RecTypeID=$rtyId and r.rst_DetailTypeID".$dt_Geo."'");

                    if($row){  //such detal in structure already exists
                        print "<div style='color:gray;padding-left:100px'>Spatial field ".$row[0]." is already in structure | ".$row[1]."</div>";
                    }else{

                        $query3 = "SELECT count(*) FROM archentity ae where ae.AEntTypeID="
                        .$attrID." and ST_asWKT(transform(casttosingle(ae.geospatialcolumn), 4326)) is not null";
                        $hasgeo = $dbfaims->query($query3);
                        $hasgeo = $hasgeo->fetchArray(SQLITE3_NUM);

                        print "<div>HAS GEO : ".$hasgeo[0]." entries</div>";

                        if($hasgeo[0]>0){
                            $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_ID=".$dt_Geo);
                            if($row3){
                                $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                                $stmt = $mysqli->prepare($query);
                                $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                                $stmt->execute();
                                $stmt->close();
                                print "<div style='padding-left:100px'>Added spatial field ".$row3[0]." to structure | ".$row3[1]."</div>";
                            }
                        }
                    }
                }

            }//for AEntTypes


            //exit();

            //----------------------------------------------------------------------------------------

            print "<h3>Record types for Container relationship types</h3><br>";

            //create/update defRecTypes/defRecStrucure on base of RelnType and IdealReln
            $query1 =  'SELECT "RelnTypeID", "RelnTypeName", "RelnTypeDescription" FROM RelnType where RelnTypeCategory="container"';
            $res1 = $dbfaims->query($query1);
            while ($row1 = $res1->fetchArray(SQLITE3_NUM))
            {
                $attrID = $row1[0];
                $rtyName = $row1[1]." [$attrID]";

                //try to find correspondant rectype in Heurist
                $row = mysqli__select_array($mysqli, "select rty_ID, rty_Name from defRecTypes where rty_NameInOriginatingDB='FAIMS.".$attrID."'");
                if($row){ //already exists

                    print  "<div style='color:gray'>Record type ".$row[0]." already exists. Based on FAIMS ".$attrID." | ".$row[1]."</div>";

                    $rtyId = $row[0];
                    $rtyName = $row[1];

                    $rectypeMap[$attrID] = $row[0];
                }else{

                    //rename existing to deprecated
                    renameExisitingRt($rtyName, 0, 1);

                    //add new record type into HEURIST
                    $query = "INSERT INTO defRecTypes (rty_Name, rty_TitleMask, rty_Description, rty_NameInOriginatingDB) VALUES (?,?,?,?)";
                    $stmt = $mysqli->prepare($query);
                    $fid = 'FAIMS.'.$attrID;
                    //$rtyMask = 'Container Record #[ID] for '.$row1[1];
                    $rtyMask = '[2-7.RecTitle] [2-6] [2-5.RecTitle]';

                    $stmt->bind_param('ssss', $rtyName, $rtyMask, $row1[2], $fid);
                    $stmt->execute();

                    $rtyId = $stmt->insert_id;
                    if($rtyId<1){
                        print "<script>showErrorMessage('".addslashes("ERROR: Reord type not inserted for:  FAIMS"
                            .$attrID." | ".$row1[1]." | ".trunc50($row1[2])." <br> "
                            ."SQL message:".$mysqli->error)."')</script></body></html>";

                        exit();
                    }

                    $stmt->close();

                    $rectypeMap[$attrID] = $rtyId;

                    print  "<div>Added Record type ".$rtyId." based on FAIMS ".$attrID." | ".$rtyName." | ".substr($row1[2],0,50)."</div>";
                }

                //if AEntType has strucute described in IdealReln
                $query2 =  'SELECT "AttributeID", "RelnDescription", "IsIdentifier", "MinCardinality", "MaxCardinality" FROM "IdealReln"
                where RelnTypeID='.$attrID;

                $recstructure = $dbfaims->query($query2);
                while ($row_recstr = $recstructure->fetchArray(SQLITE3_NUM))
                {

                    $row = mysqli__select_array($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName from defDetailTypes d, defRecStructure r ".
                        "where d.dty_ID=r.rst_DetailTypeID and r.rst_RecTypeID=$rtyId and d.dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");

                    if($row){  //such detal in structure already exists

                        print  "<div style='color:gray;padding-left:100px'>Field ".$row[0]." is already in structure. Based on FAIMS ".$row_recstr[0]." | ".$row[1]."</div>";

                    }else{

                        $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");
                        if($row3){
                            //add new detail type into HEURIST
                            $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                            $stmt = $mysqli->prepare($query);
                            $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                            $stmt->execute();

                            $stmt->close();

                            print "<div style='color:gray;padding-left:100px'>Added field ".$row3[0]." to structure. Based on FAIMS ".$row_recstr[0]." | ".$row3[1]."</div>";
                        }else{
                            print  "<div style='color:red;padding-left:100px'>FIELD NOT ADDED TO STRUCTURE. IT WAS NOT FOUND. FAIMS:".$row_recstr[0]." !</div>";
                        }
                    }

                }//for add details for structure




                //verify spatial data for this record type
                if($use_Spatialite && isset($dt_Geo) && $dt_Geo>0)
                {
                    $row = mysqli__select_array($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName from defRecStructure r ".
                        "where r.rst_RecTypeID=$rtyId and r.rst_DetailTypeID".$dt_Geo."'");

                    if($row){  //such detal in structure already exists
                        print "<div style='color:gray;padding-left:100px'>Spatial field ".$row[0]." is already in structure | ".$row[1]."</div>";
                    }else{

                        $query3 = "SELECT count(*) FROM Relationship ae where ae.RelnTypeID="
                        .$attrID." and ST_asWKT(transform(casttosingle(ae.geospatialcolumn), 4326)) is not null";
                        $hasgeo = $dbfaims->query($query3);
                        $hasgeo = $hasgeo->fetchArray(SQLITE3_NUM);

                        print "<div>HAS GEO : ".$hasgeo[0]." entries</div>";

                        if($hasgeo[0]>0){
                            $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_ID=".$dt_Geo);
                            if($row3){
                                $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                                $stmt = $mysqli->prepare($query);
                                $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                                $stmt->execute();
                                $stmt->close();
                                print "<div style='padding-left:100px'>Added spatial field ".$row3[0]." to structure | ".$row3[1]."</div>";
                            }
                        }
                    }
                }

            }//for RelnType

            //----------------------------------------------------------------------------------------
            /* */

            print "<h3>Update records in H3 accoring to the most current Record set in FAIMS</h3><br>";

            $query = "select uuid, aenttimestamp, aenttypeid, ST_asWKT(transform(geometryn(geospatialcolumn, 1), 4326)) as Coordinate, freetext, vocabid, attributeid, measure, Certainty
            from latestnondeletedaentvalue join latestnondeletedarchent using (uuid) ORDER BY uuid asc";



            $faims_id = null;
            $details = null;
            $rectype = null;
            $recID = null;
            $skip_faimsrec = false;
            $cntInsterted = 0;
            $cntUpdated = 0;

            $rs = $dbfaims->query($query);
            if($rs)
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    if($faims_id!=$row[0]){

                        if($details && count($details)>0){
                            insert_update_Record($recID, $rectype, $details, $faims_id);
                        }

                        $details = array();

                        echo "Entity id:".$row[0]."  time=".$row[1]."  atype=".$row[2]."  geo=".$row[3]."<br>";
                        $faims_id = $row[0];
                        $faims_atype = $row[2];
                        $faims_time = $row[1];

                        if(@$rectypeMap[$faims_atype]){
                            $rectype = $rectypeMap[$faims_atype];
                        }else{
                            $skip_faimsrec = true;
                            print "RECORD TYPE NOT FOUND for Vocabulary ".$faims_atype."<br />";
                            continue;
                        }

                        $skip_faimsrec = false;

                        //add special detail type 2-589 - reference to original record id
                        if(isset($dt_SourceRecordID) && $dt_SourceRecordID>0){
                            $details["t:".$dt_SourceRecordID] = array('0'=>$faims_id);

                            //find the existing record in Heurist database
                            $recID = getRecordByFaimsId($faims_id);

                            print "RECID ".($recID>0?$recID:"NEW")." for ".$faims_id."<br>";

                        }else{
                            $recID = 0;
                        }

                        if(isset($dt_Geo) && $dt_Geo>0 && $row[3]){

                            if(strpos($row[3],"POINT")===0){
                                $pred = "p ";
                            }else if(strpos($row[3],"LINE")===0){
                                $pred = "l ";
                            }else if(strpos($row[3],"POLY")===0){
                                $pred = "pl ";
                            }else{
                                $pred = " ";
                            }

                            $details["t:".$dt_Geo] = array('0'=>$pred.$row[3]);

                        }

                    }else if($skip_faimsrec){
                        $details = null;
                        continue;
                    }

                    //4 av.freeText, 5 av.VocabID, 6 av.AttributeID, 7 av.Measure, 8 av.Certainty
                    //attr id, freetext, measure, certainity, vocabid
                    echo "<div style='padding-left:30px'>".$row[6]."  ".$row[4]."  ".$row[7]."  ".$row[8]."  ".$row[5]."  "."</div>";

                    //get detailtype id in Heurist by attrib id
                    $key = -1;
                    if(@$detailMap[$row[6]]){
                        $key = intval($detailMap[$row[6]][0]);
                        $detType = $detailMap[$row[6]][1];
                    }
                    if($key>0){

                        if($detType=="file"){


                            $filename = dirname($dbname_faims).DIRECTORY_SEPARATOR.$row[4];

                            if(file_exists($filename)){
                                //add-update the uploaded file
                                $value = register_file($filename, null, false);
                                if(!is_numeric($value)){
                                    print "<div style=\"color:red\">warning $filename failed to register, detail type ignored. $value</div>";
                                    $value = null;
                                }
                            }else{
                                print "<div style=\"color:red\">warning $filename file not found, detail type ignored</div>";
                                $value = null;
                            }


                        }else{

                            $vocabID = $row[5];

                            if($vocabID){ //vocabID
                                if(@$termsMap[$vocabID]){
                                    $value = $termsMap[$vocabID];
                                }else{
                                    print "TERM NOT FOUND for Vocabulary ".$vocabID."<br />";
                                    continue;
                                }
                            }else if($row[7]){ //measure
                                $value = $row[7];
                            }else if($row[4]){ //freetext
                                $value = $row[4];
                                /*}else if($row[8]){ //Certainty
                                $value = $row[8];*/
                            }else{
                                continue;
                            }

                        }

                        if($value){
                            if(!@$details["t:".$key]){
                                $details["t:".$key] = array();
                            }
                            array_push($details["t:".$key], $value);
                        }


                    }else{
                        print "DETAIL TYPE NOT FOUND for Attrubute ".$row[6]."<br />";
                    }

            }//loop all faims records

            if($details && count($details)>0){
                insert_update_Record($recID, $rectype, $details, $faims_id);
            }

            print "Inserted ".$cntInsterted."<br/>";
            print "Updated ".$cntUpdated."<br/>";

            //----------------------------------------------------------------------------------------

            print "<h3>Update special records  for FAIMS relationship category 'Container'</h3><br>";

            $query = "SELECT ae.RelationshipID, ae.RelnTimestamp, ae.RelnTypeID, ST_asWKT(transform(casttosingle(ae.geospatialcolumn), 4326)) as Coordinate,
            av.freeText, av.VocabID, av.AttributeID, null, av.Certainty
            FROM RelnValue av
            JOIN (SELECT RelationshipID, attributeid, max(RelnValueTimestamp) as RelnValueTimestamp, max(RelnTimestamp) as RelnTimestamp, Relationship.deleted as entDel, RelnValue.deleted as valDel
            FROM RelnValue
            JOIN Relationship USING (RelationshipID)

            GROUP BY RelationshipID, attributeid
            HAVING MAX(RelnValueTimestamp)
            AND MAX(RelnTimestamp)) USING (RelationshipID, attributeid, RelnValueTimestamp)
            JOIN  Relationship ae using (RelationshipID, RelnTimestamp),
            RelnType rt on ae.RelnTypeID = rt.RelnTypeID and rt.RelnTypeCategory='container'
            WHERE entDel is NULL
            AND valDel is NULL
            ORDER BY ae.RelationshipID asc";

            //     $query1 =  'SELECT "RelnTypeID", "RelnTypeName", "RelnTypeDescription" FROM RelnType where RelnTypeCategory="container"';


            $faims_id = null;
            $details = null;
            $rectype = null;
            $recID = null;
            $skip_faimsrec = false;
            $cntInsterted = 0;
            $cntUpdated = 0;

            $containerRecords = array();

            $rs = $dbfaims->query($query);
            if($rs)
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    if($faims_id!=$row[0]){

                        if($details && count($details)>0){
                            $nrecid = $insert_update_Record($recID, $rectype, $details, $faims_id);
                            if($nrecid){
                                $containerRecords[$faims_id] = $nrecid;
                            }
                        }

                        $details = array();

                        echo "Entity id:".$row[0]."  ".$row[1]."  ".$row[2]."  ".$row[3]."<br>";
                        $faims_id = $row[0];
                        $faims_atype = $row[2];
                        $faims_time = $row[1];

                        if(@$rectypeMap[$faims_atype]){
                            $rectype = $rectypeMap[$faims_atype];
                        }else{
                            $skip_faimsrec = true;
                            print "RECORD TYPE NOT FOUND for Vocabulary ".$faims_atype."<br />";
                            continue;
                        }

                        $skip_faimsrec = false;

                        //add special detail type 2-589 - reference to original record id
                        if(isset($dt_SourceRecordID) && $dt_SourceRecordID>0){
                            $details["t:".$dt_SourceRecordID] = array('0'=>$faims_id);

                            //find the existing record in Heurist database
                            $recID = getRecordByFaimsId($faims_id);
                        }else{
                            $recID = 0;
                        }

                        if(isset($dt_Geo) && $dt_Geo>0 && $row[3]){

                            if(strpos($row[3],"POINT")===0){
                                $pred = "p ";
                            }else if(strpos($row[3],"LINE")===0){
                                $pred = "l ";
                            }else if(strpos($row[3],"POLY")===0){
                                $pred = "pl ";
                            }else{
                                $pred = " ";
                            }

                            $details["t:".$dt_Geo] = array('0'=>$pred.$row[3]);

                        }

                    }else if($skip_faimsrec){
                        $details = null;
                        continue;
                    }

                    //attr id, freetext, measure, certainity, vocabid
                    echo "<div style='padding-left:30px'>".$row[6]."  ".$row[4]."  ".$row[7]."  ".$row[8]."  ".$row[5]."  "."</div>";

                    //get detailtype id in Heurist by attrib id
                    $key = -1;
                    if(@$detailMap[$row[6]]){
                        $key = intval($detailMap[$row[6]][0]);
                        $detType = $detailMap[$row[6]][1];
                    }
                    if($key>0){

                        if($detType=="file"){


                            $filename = dirname($dbname_faims).DIRECTORY_SEPARATOR.$row[4];

                            if(file_exists($filename)){
                                //add-update the uploaded file
                                $value = register_file($filename, null, false);
                                if(!is_numeric($value)){
                                    print "<div style=\"color:red\">warning $filename failed to register, detail type ignored. $value</div>";
                                    $value = null;
                                }
                            }else{
                                print "<div style=\"color:red\">warning $filename file not found, detail type ignored</div>";
                                $value = null;
                            }


                        }else{

                            $vocabID = $row[5];

                            if($vocabID){ //vocabID
                                if(@$termsMap[$vocabID]){
                                    $value = $termsMap[$vocabID];
                                }else{
                                    print "TERM NOT FOUND for Vocabulary ".$vocabID."<br />";
                                    continue;
                                }
                            }else if($row[7]){ //measure
                                $value = $row[7];
                            }else if($row[4]){ //freetext
                                $value = $row[4];
                                /*}else if($row[8]){ //Certainty
                                $value = $row[8];*/
                            }else{
                                continue;
                            }

                        }

                        if($value){
                            if(!@$details["t:".$key]){
                                $details["t:".$key] = array();
                            }
                            array_push($details["t:".$key], $value);
                        }


                    }else{
                        print "DETAIL TYPE NOT FOUND for Attrubute ".$row[6]."<br />";
                    }

            }//loop all faims records

            if($details && count($details)>0){
                $nrecid = insert_update_Record($recID, $rectype, $details, $faims_id);
                if($nrecid){
                    $containerRecords[$faims_id] = $nrecid;
                }
            }

            print "Inserted ".$cntInsterted."<br/>";
            print "Updated ".$cntUpdated."<br/>";

            //----------------------------------------------------------------------------------------

            print "<h3>Update relationship records in H3 accoring to the most current relations in FAIMS</h3><br>";

            $query = 'select ar.RelationShipID, ae.UUID, ar.RelnTypeID, ae.ParticipatesVerb
            from "latestNonDeletedRelationship" ar, "latestNonDeletedAentReln" ae
            where ar.RelationShipID=ae.RelationShipID  order by ar.RelationShipID';

            $faims_id = null;
            $details = null;
            $rectype = RT_RELATION;
            $is_source_rec = true;
            $reltype = null;
            $recID = null;
            $skip_faimsrec = false;
            $cntInsterted = 0;
            $cntUpdated = 0;

            $rs = $dbfaims->query($query);
            if($rs)
                while ($row = $rs->fetchArray(SQLITE3_NUM))
                {
                    if($faims_id!=$row[0]){

                        //another relation - save previous
                        if($details && count($details)>0){

                            //since hiearchy is a graph in faims - it may be many-to-many relationship
                            foreach ($details["t:".DT_PRIMARY_RESOURCE] as $idx=>$recId_Source) {
                                if($recId_Source>0)
                                    foreach ($details["t:".DT_TARGET_RESOURCE] as $idx=>$recId_Target) {
                                        if($recId_Target>0){
                                            $details2 = array("t:".DT_PRIMARY_RESOURCE=>array(0=>$recId_Source),
                                                "t:".DT_TARGET_RESOURCE =>array(0=>$recId_Target),
                                                "t:".DT_NAME => array('0'=>'FAIMS Relationship'),
                                                "t:".DT_RELATION_TYPE => $details["t:".DT_RELATION_TYPE],
                                                "t:".$dt_SourceRecordID => $details["t:".$dt_SourceRecordID]
                                            );

                                            insert_update_Record($recID, $rectype, $details2, $faims_id);
                                        }
                                }
                            }
                        }

                        $details = array();
                        $details["t:".DT_PRIMARY_RESOURCE]=array();
                        $details["t:".DT_TARGET_RESOURCE]=array();

                        $is_source_rec = true;

                        echo "---<br>";
                        echo "Entity id:".$row[0]."  relent_id=".$row[1]."  atype=".$row[2]."<br>";
                        $faims_id = $row[0];
                        $faims_atype = $row[2];
                        $faims_relent_id = $row[1];

                        if(@$reltypeMap[$faims_atype]){

                            if(@$containerRecords[$faims_id]){ //container reltype


                                $recIdcontainer = $containerRecords[$faims_id];
                                $faims_id = $faims_id."_container";

                                array_push( $details["t:".DT_PRIMARY_RESOURCE], $recIdcontainer);
                                $reltype = $reltypeMap[$faims_atype];
                                $is_source_rec = false;

                            }else if(is_array($reltypeMap[$faims_atype])){ //hiearchy reltype

                                $participatesVerb = $row[3];
                                $reltype = reset($reltypeMap[$faims_atype]);
                                $is_source_rec = ( $reltype == $reltypeMap[$faims_atype][$participatesVerb] ); //first element of array

                            }else{
                                $reltype = $reltypeMap[$faims_atype];
                            }
                        }else{
                            $skip_faimsrec = true;
                            print "RELATION TYPE NOT FOUND for FAIMS relationship type ".$faims_atype."<br />";
                            continue;
                        }

                        $skip_faimsrec = false;

                        //add special detail type 2-589 - reference to original record id
                        if(isset($dt_SourceRecordID) && $dt_SourceRecordID>0){
                            $details["t:".$dt_SourceRecordID] = array('0'=>$faims_id);

                            //find the existing record in Heurist database
                            $recID = getRecordByFaimsId($faims_id);

                        }else{
                            $recID = 0;
                        }

                        $rel_recID = getRecordByFaimsId($faims_relent_id);
                        if($rel_recID==null || $rel_recID<1){
                            print '<span style="color:red">'.($is_source_rec?"SOURCE":"TARGET").' Heurist record not found for faims_id='.$faims_relent_id.'</span><br>';
                        }else{
                            print ($is_source_rec?"SOURCE":"TARGET")."   recid=".$rel_recID."  faims=".$faims_relent_id."<br>";
                        }

                        array_push($details["t:".($is_source_rec?DT_PRIMARY_RESOURCE:DT_TARGET_RESOURCE)], $rel_recID );
                        $details["t:".DT_RELATION_TYPE] = array('0'=> $reltype);

                    }else if($skip_faimsrec){
                        $details = null;
                    }else {
                        $faims_atype = $row[2];
                        $faims_relent_id = $row[1];
                        $is_source_rec = false;

                        if(@$reltypeMap[$faims_atype]){

                            if(@$containerRecords[$row[0]]){ //container reltype

                            }else if(is_array($reltypeMap[$faims_atype])){ //hiearchy
                                $participatesVerb = $row[3];
                                $reltype = reset($reltypeMap[$faims_atype]);
                                $is_source_rec = ( $reltype == $reltypeMap[$faims_atype][$participatesVerb] ); //first element of array

                            }
                        }else{
                            $details = true;
                            print "RELATION TYPE NOT FOUND for FAIMS relationship type ".$faims_atype."<br />";
                            continue;
                        }

                        $rel_recID = getRecordByFaimsId($faims_relent_id);
                        if($rel_recID==null || $rel_recID<1){
                            print '<span style="color:red">'.($is_source_rec?"SOURCE":"TARGET").' Heurist record not found for faims_id='.$faims_relent_id.'</span><br>';
                        }else{
                            print "".($is_source_rec?"SOURCE":"TARGET")."   recid=".$rel_recID."  faims=".$faims_relent_id."<br>";
                        }

                        array_push($details["t:".($is_source_rec?DT_PRIMARY_RESOURCE:DT_TARGET_RESOURCE)], $rel_recID );
                        //$faims_id = null;
                    }

            }//loop all faims records

            if($details && count($details)>0){


                foreach ($details["t:".DT_PRIMARY_RESOURCE] as $idx=>$recId_Source) {
                    foreach ($details["t:".DT_TARGET_RESOURCE] as $idx=>$recId_Target) {
                        $details2 = array("t:".DT_PRIMARY_RESOURCE=>array(0=>$recId_Source),
                            "t:".DT_TARGET_RESOURCE =>array(0=>$recId_Target),
                            "t:".DT_NAME => array('0'=>'FAIMS Relationship'),
                            "t:".DT_RELATION_TYPE => $details["t:".DT_RELATION_TYPE],
                            "t:".$dt_SourceRecordID => $details["t:".$dt_SourceRecordID]
                        );
                        insert_update_Record($recID, $rectype, $details2, $faims_id);
                    }
                }
            }

            print "<br><h3>Module import completed</h3><br><br>";
            print "<script>showFinalMessage('Module import completed', false)</script>";

            /**
            * returns Heurist record id by faims id
            *
            * @param mixed $faims_id
            */
            function getRecordByFaimsId($faims_id){
                global $mysqli, $dt_SourceRecordID;
                return mysqli__select_value($mysqli, "select dtl_RecID from recDetails where dtL_DetailTypeID=$dt_SourceRecordID and dtl_Value=$faims_id");
            }


            /*
            $query = "SELECT  a1.uuid, a1.AEntTimestamp, a1.AEntTypeID FROM    ArchEntity a1 INNER JOIN ".
            "(SELECT  uuid, MAX(AEntTimestamp) AS vn FROM  ArchEntity where deleted is null GROUP BY uuid )  a2 ".
            "ON  a1.uuid=a2.uuid and a1.AEntTimestamp=a2.vn";

            $cntInsterted = 0;
            $cntUpdated = 0;

            foreach ($dbfaims->query($query) as $row)
            {
            echo $row[0]."  ".$row[1]."  ".$row[2]."<br>";

            $faims_id = $row[0];
            $faims_atype = $row[2];
            $faims_time = $row[1];

            if(@$rectypeMap[$faims_atype]){
            $rectype = $rectypeMap[$faims_atype];
            }else{
            print "RECORD TYPE NOT FOUND for Vocabulary ".$faims_atype."<br />";
            continue;
            }

            $details = array();

            //add special detail type 2-589 - reference to original record id
            if(isset($dt_SourceRecordID) && $dt_SourceRecordID>0){
            $details["t:".$dt_SourceRecordID] = array('0'=>$faims_id);

            //find the existing record in Heurist database
            $recID = mysqli__select_value($mysqli, "select dtl_RecID from recDetails where dtL_DetailTypeID=$dt_SourceRecordID and dtl_Value=$faims_id");
            }else{
            $recID = 0;
            }

            $query2 =  "SELECT uuid, ValueTimestamp, VocabID, AttributeID, Measure, FreeText, Certainty FROM AEntValue where uuid=".$faims_id." and ValueTimestamp='".$faims_time."'";
            foreach ($dbfaims->query($query2) as $row2)
            {
            //attr id, freetext, measure, certainity, vocabid
            echo "<div style='padding-left:30px'>".$row2[3]."  ".$row2[5]."  ".$row2[4]."  ".$row2[6]."  ".$row2[2]."</div>";

            //detail type
            $key = intval(@$detailMap[$row2[3]]);
            if($key>0){

            $vocabID = $row2[2];

            if($vocabID){ //vocabID
            if(@$termsMap[$vocabID]){
            $value = $termsMap[$vocabID];
            }else{
            print "TERM NOT FOUND for Vocabulary ".$vocabID."<br />";
            continue;
            }
            }else if($row2[5]){ //freetext
            $value = $row2[5];
            }else if($row2[4]){ //measure
            $value = $row2[4];
            }else if($row2[6]){ //Certainty
            $value = $row2[6];
            }else{
            continue;
            }

            if($value){
            if(!@$details["t:".$key]){
            $details["t:".$key] = array();
            }
            array_push($details["t:".$key], $value);
            }


            }else{
            print "DETAIL TYPE NOT FOUND for Attrubute ".$row2[3]."<br />";
            }

            }

            $ref = null;

            //add-update Heurist record
            $out = saveRecord($recID, //record ID
            $rectype,
            null, // URL
            null, //Notes
            get_group_ids(), //???get_group_ids(), //group
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
            print "<br>Source record# ".$faims_id."&nbsp;&nbsp;&nbsp;";
            print "=><div style='color:red'> Error: ".implode("; ",$out["error"])."</div>";
            }else{

            if($recID){
            $cntUpdated++;
            print "UPDATED as #".$recID."<br/>";
            }else{
            $cntInsterted++;
            print "INSERTED as #".$out["bibID"]."<br/>";
            }
            }



            }//for records
            */

            //insert or update records in Heurist databse
            function insert_update_Record($recID, $rectype, $details, $faims_id)
            {
                global $cntUpdated, $cntInsterted, $mysqli;

                if($recID>0){
                    //delete existing details
                    $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
                    if(!$mysqli->query($query)){
                        $syserror = $mysqli->error;
                        print "<div style='color:red'> Error: Cannot delete record details ".$syserror."</div>";
                        return null;
                    }
                }


                $ref = null;

                //add-update Heurist record
                $out = saveRecord($recID, //record ID
                    $rectype,
                    null, // URL
                    null, //Notes
                    get_group_ids(), //???get_group_ids(), //group
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
                    print "<br>Source record# ".$faims_id."&nbsp;&nbsp;&nbsp;";
                    print "=><div style='color:red'> Error: ".implode("; ",$out["error"])."</div>---<br>";
                }else{

                    if($recID){
                        $cntUpdated++;
                        print "UPDATED as #".$recID."<br/>";
                    }else{
                        $cntInsterted++;
                        print "INSERTED as #".$out["bibID"]."<br/>";
                        $recID = $out["bibID"];
                    }
                }

                return $recID;
            }


            // TODO: this also exists in hsapi\sync\faims_to_heurist.php

            function faimsToHeurist_dt_mapping($ftype){

                $res = null;

                switch (strtolower($ftype)){
                    case 'string':
                    case 'text':
                    case 'checklist':
                    case 'radiogroup':
                        $res = 'freetext';
                        break;
                    case 'integer':
                    case 'measure':
                    case 'float':
                        $res = 'float';
                        break;
                    case 'date':
                    case 'timestamp':
                    case 'time':
                        $res = 'date';
                        break;
                    case 'lookup':
                        $res = 'enum';
                        break;
                    case 'dropdown':
                        $res = 'resource';
                        break;
                    case 'file':
                        $res = 'file';
                        break;
                    default:
                        $res = 'freetext';
                        break;
                }
                //Heurist types ENUM('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude')
                return $res;
            }

            /**
            * put your comment there...
            *
            * @param mixed $parentTermID
            * @param mixed $parent
            * @param mixed $fid
            * @param mixed $nameorig
            */
            function addChildRelType($parentTermID, $parent, $fid, $nameorig){
                global $mysqli;
                $query = "INSERT INTO defTerms (trm_Label, trm_Domain, trm_NameInOriginatingDB, trm_ParentTermID, trm_InverseTermId) ".
                "VALUES (?,'relation',?, ".$parentTermID.",".$parent.")";
                $stmt = $mysqli->prepare($query);

                $fid = 'FAIMS.'.$fid.'_child';
                $stmt->bind_param('ss', $nameorig, $fid );
                $stmt->execute();

                $trm_ID = $stmt->insert_id;
                $stmt->close();


                $query = "UPDATE defTerms set trm_InverseTermId=".$trm_ID." where trm_ID=".$parent;
                $stmt = $mysqli->prepare($query);
                $stmt->execute();

                $stmt->close();
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

            function trunc50($str){

                if(strlen($str)<51){
                    return $str;
                } else {
                    return substr($str,0,50)."...";
                }

            }

            //
            // rename the existing field type to deprecated
            //
            function renameExisitingDt($dtyName, $dty_ID, $cnt){
                global $mysqli;

                $query = "select dty_ID from defDetailTypes where dty_Name = '".mysql_real_escape_string($dtyName)."'";

                $row = mysqli__select_array($mysqli, $query);

                if($row && $row[0]){ //found

                    if(strpos($dtyName,"_deprecated")>0){
                        $dtyName = $dtyName."_".$cnt;
                    }else{
                        $dtyName = $dtyName."_deprecated";
                    }

                    renameExisitingDt($dtyName, $dty_ID>0?$dty_ID:$row[0], $cnt+1);

                }else if($dty_ID>0){

                    $query = "UPDATE defDetailTypes set dty_Name=? where dty_ID=".$dty_ID;
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param('s', $dtyName);
                    if(!$stmt->execute()){
                        //sql error
                        print  "<div style='color:red'>'Cannot rename Field type $dty_ID. MySQL error: ".$mysqli->error."</div>";
                    }else{
                        print  "<div style='color:purple'>Existing Field type ".$dty_ID." is renamed to ".$dtyName."</div>";
                    }
                }
            }

            //
            // rename the existing Record type to deprecated
            //
            function renameExisitingRt($rtyName, $rty_ID, $cnt){
                global $mysqli;

                $row = mysqli__select_array($mysqli, "select rty_ID from defDetailTypes where rty_Name = '".mysql_real_escape_string($rtyName)."'");
                if($row && $row[0]){ //found

                    if(strpos($rtyName,"_deprecated")>0){
                        $rtyName = $rtyName."_".$cnt;
                    }else{
                        $rtyName = $rtyName."_deprecated";
                    }
                    renameExisitingRt($rtyName, $rty_ID>0?$rty_ID:$row[0], $cnt+1);

                }else if($rty_ID>0){
                    $query = "UPDATE defDetailTypes set rty_Name=? where rty_ID=".$rty_ID;
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param('s', $rtyName);
                    if(!$stmt->execute()){
                        //sql error
                        print  "<div style='color:red'>'Cannot rename Record type $rty_ID. MySQL error: ".$mysqli->error."</div>";
                    }else{
                        print  "<div style='color:purple'>Existing Record type ".$row[0]." was renamed to ".$rtyName."</div>";
                    }
                }
            }

            //
            // find all modules in specified folder
            //
            function getListOfModules(){
                $res = "";
                if(defined('HEURIST_FAIMS_DIR')){
                    $list = recurse_find_modules(HEURIST_FAIMS_DIR);
                    foreach($list as $idx=>$module){
                        $res = $res."<option value='".htmlspecialchars($module["path"])."'>".htmlspecialchars($module["name"])."</option>";
                    }

                }
                return $res;
            }

            /**
            * return array of modules
            */
            function recurse_find_modules($folder) {

                $res = array();

                if(file_exists($folder)){
                    //detect if module.settings exists
                    if(file_exists($folder.'/module.settings') && file_exists($folder.'/db.sqlite3'))
                    {
                        //read JSON from file and get faims module name
                        $module_info = json_decode(file_get_contents($folder.'/module.settings'), true);

                        //this is faism module folder
                        return array("name"=>$module_info["name"], "path"=>$folder.'/db.sqlite3');
                    }

                    //$files1 = scandir($dir);
                    $dh  = opendir($folder);
                    while (false !== ($file = readdir($dh))) {
                        if (( $file != '.' ) && ( $file != '..' )) {
                            if ( is_dir($folder . '/' . $file) ) {
                                $list = recurse_find_modules($folder. '/' . $file);
                                if($list && @$list['name']){
                                    array_push( $res, $list );
                                }else if(count($list)>0){
                                    $res = array_merge($res, $list);
                                }

                            }
                        }
                    }
                    closedir($dh);
                }
                return $res;
            }

            function openSQLiteDb($dbname_faims){
                $dbfaims = new SQLite3($dbname_faims); //':memory:');
                if ( !$dbfaims ) {
                    print "<div style='color:red; font-weight:bold;padding:10px'>Cannot open the database</div>";
                    return false;
                }

                # reporting some version info
                $rs = $dbfaims->query('SELECT sqlite_version()');
                while ($row = $rs->fetchArray())
                {
                    print "<h3>Server running: SQLite version: $row[0]</h3>&nbsp;";

                }

                $use_Spatialite = true;

                if($use_Spatialite){ 

                    # loading SpatiaLite as an extension
                    $dbfaims->loadExtension('libspatialite.so');

                    # enabling Spatial Metadata
                    # using v.2.4.0 this automatically initializes SPATIAL_REF_SYS
                    # and GEOMETRY_COLUMNS
                    $dbfaims->exec("SELECT InitSpatialMetadata()");

                    $rs = $dbfaims->query('SELECT spatialite_version()');
                    if($rs){
                        while ($row = $rs->fetchArray())
                        {
                            print "<h3>Server running: SpatiaLite version: $row[0]</h3>";
                        }
                    }else{
                        print "<div style='color:red; font-weight:bold;padding:10px'>
                        Sorry, we are unable to sync the Heurist database with the FAIMS SQLite database
                        <br/>as we cannot detect SpatiaLite (spatial functions for SQLite).
                        <br/>Please ask your sysadmin to install SpatiaLite on the server.</div>";
                        return false;
                    }

                }  //use spatialite

                return $dbfaims;
            }
            ?>
        </div>
    </body>
</html>

