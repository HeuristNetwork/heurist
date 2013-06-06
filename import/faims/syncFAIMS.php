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
*   Sync h3 database with FAIMS sqlite
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");

    if(isForAdminOnly("to sync FAIMS database")){
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

</head>
<body style="padding:44px;" class="popup">

    <div class="banner"><h2>FAIMS sync</h2></div>
    <div id="page-inner" style="width:640px; margin:0px auto; padding: 0.5em;">
<?php

    $dbname_faims = @$_REQUEST['faims'];

    if( !$dbname_faims ||  !file_exists($dbname_faims)){

        if($dbname_faims && !file_exists($dbname_faims)){
            print "<div style='color:red; font-weight:bold;padding:10px'>The specified database file does not found</div>";
        }


        //$dbname_faims = HEURIST_UPLOAD_DIR."faims/db2.sqlite3";
        //$dbname_faims = HEURIST_UPLOAD_DIR."faims/tracklog/db.sqlite3";
        // /var/www/HEURIST_FILESTORE/johns_FAIMS_UAT_test/faims/tracklog/db.sqlite3
        // /var/www/HEURIST_FILESTORE/johns_FAIMS_UAT_test/faims/syncdemo/db.sqlite3
        if(!$dbname_faims){
            $dbname_faims = "/var/www/faims-server/";
        }

        print "<form name='selectdb' action='syncFAIMS.php' method='get'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div>FAIMS database: <input name='faims' value='".$dbname_faims."' size='100'></div><br/>";
        print "<div>Show structure: <input name='showstr' value='1' type='checkbox'></div><br/>";
        print "<div><input type='submit' value='Start' /></div>";
        print "</form></div></body></html>";
        exit();
    }

    // $dir_faims.
    // $dbname_faims = HEURIST_UPLOAD_DIR. "faims/db2.sqlite3";

    $mysqli = mysqli_connection_overwrite(DATABASE);

    print "<br />FAIMS db: ".$dbname_faims."<br /><br />";

    if(!file_exists($dbname_faims)){
        print "DB file not found";
        exit();
    }

    if ( !class_exists('SQLite3' ) ) {
        print "<div style='color:red; font-weight:bold;padding:10px'>FAIMS synchronisation requires installation of the SQLite3 extension to PHP."
        ." Please ask your system administrator to install this extension. See <put in appropriate URL to doco> for installation information.</div>";
        exit();
    }

    $dbfaims = new SQLite3($dbname_faims); //':memory:');
    if ( !$dbfaims ) {
        print "<div style='color:red; font-weight:bold;padding:10px'>Cannot open the database</div>";
        exit();
    }



# loading SpatiaLite as an extension
$dbfaims->loadExtension('libspatialite.so');

# enabling Spatial Metadata
# using v.2.4.0 this automatically initializes SPATIAL_REF_SYS
# and GEOMETRY_COLUMNS
$dbfaims->exec("SELECT InitSpatialMetadata()");

# reporting some version info
$rs = $dbfaims->query('SELECT sqlite_version()');
while ($row = $rs->fetchArray())
{
  print "<h3>SQLite version: $row[0]</h3>&nbsp;";
}
$rs = $dbfaims->query('SELECT spatialite_version()');
while ($row = $rs->fetchArray())
{
  print "<h3>SpatiaLite version: $row[0]</h3>";
}

     print "<br>";
/* THIS IS ORIGINAL BRIAN QUERY TO RETRIEVE SPATIAL DATA
$query = "SELECT uuid, asText(transform(casttosingle(geospatialcolumn), 3857)) as Coordinate, group_concat(attributename||': '||freetext, ', ') as note
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

    if(@$_REQUEST['showstr']){
        // AttributeKey -> defDetailTypes
        print "<h3>AttributeKey (fields)</h3><br>";
        $query =  "SELECT AttributeID, AttributeType, AttributeName, AttributeDescription FROM AttributeKey";
        $rs = $dbfaims->query($query);
        while ($row = $rs->fetchArray(SQLITE3_NUM))
        {
                echo $row[0]."  ".$row[1]."  ".$row[2]."<br>";
        }


       // Vocabulary  -> defTerms
        print "<h3>Vocabulary (terms)</h3><br>";
        $query =  "SELECT VocabID, AttributeID, VocabName FROM Vocabulary";
        $rs = $dbfaims->query($query);
        while ($row = $rs->fetchArray(SQLITE3_NUM))
        {
                echo $row[0]."  ".$row[2]."<br>";
        }


        //AEntType - defRecTypes   IdealAEnt - defRecStrucutre
        print "<h3>Entity type (record types and structures)</h3><br>";
        $query =  "SELECT AEntTypeID, AEntTypeName, AEntTypeCategory, AEntTypeDescription FROM AEntType";
        $rs = $dbfaims->query($query);
        while ($row = $rs->fetchArray(SQLITE3_NUM))
        {
                echo $row[0]."  ".$row[1]."  (".$row[3].")<br>";

                $query2 =  "SELECT AEntTypeID, AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".$row[0];
                $rs2 = $dbfaims->query($query2);
                while ($row2 = $rs2->fetchArray(SQLITE3_NUM))
                {
                      echo "<div style='padding-left:30px'>".$row2[1]."  ".$row2[2]."</div>";
                }
        }
    }

    print "<h3>Sync structure</h3><br>";

    $rectypeMap = array();
    $detailMap = array();
    $termsMap = array();

    print "<h3>Details</h3><br>";

    //create/update defDetailTypes on base of AEntValue
    $query1 =  "SELECT AttributeID, AttributeName, AttributeType, AttributeDescription FROM AttributeKey";

    $res1 = $dbfaims->query($query1);
    while ($row1 = $res1->fetchArray(SQLITE3_NUM))
    {
        $attrID = $row1[0];

            //try to find correspondant dettype in Heurist
            $row = mysqli__select_array($mysqli, "select dty_ID, dty_Name, dty_JsonTermIDTree, dty_Type from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$attrID."'");
            if($row){

print  "H3 DT ".$row[0]."  (".$row[1].")  => FAIMS ".$attrID."<br/>";

                $dtyId = $row[0];
                $dtyName = $row[1];
                $vocabID = $row[2];

                $detailMap[$attrID] = array($row[0], $row[3]);
            }else{
                //add new detail type into HEURIST
                $query = "INSERT INTO defDetailTypes (dty_Name, dty_Documentation, dty_Type, dty_NameInOriginatingDB) VALUES (?,?,?,?)";
                $stmt = $mysqli->prepare($query);
                if(!$stmt){
                    print "ERROR ".$mysqli->error;
                    exit();
                }
                $fid = 'FAIMS.'.$attrID;
                $ftype = faimsToH3_dt_mapping($row1[2]);
//print ">>>>".$fid." ".$row1[1];
                $fname = ($row1[1]." [$attrID]");

                $stmt->bind_param('ssss', $fname, $row1[3], $ftype, $fid);
                if(!$stmt->execute()){
print "ERROR! detail type not inserted ".$mysqli->error." ( based on ".$attrID." ".$row1[1]." ".$row1[3].").  ".$mysqli->error."<br/>";
exit();
                }

                $dtyId = $stmt->insert_id;
                $dtyName = $row1[1];
                $vocabID = null;

                $stmt->close();

                $detailMap[$attrID] = array($dtyId, $ftype);

print  "H3 DT added as ".$dtyId."  based on ".$attrID." ".$row1[1]." ".$row1[3]."<br/>";
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
                    $flbl = 'Vocab #'.$dtyId;
                    $fdesc = 'Vocabulary for detailtype '.$dtyId;
                    $stmt->bind_param('ss', $flbl , $fdesc );
                    $stmt->execute();
                    $vocabID = $stmt->insert_id;
                    $stmt->close();

                    $query = "UPDATE defDetailTypes set dty_Type='enum', dty_JsonTermIDTree=$vocabID where dty_ID=$dtyId";
                    $stmt = $mysqli->prepare($query);
                    $stmt->execute();
                    $stmt->close();
                }


                $row = mysqli__select_array($mysqli, "select trm_ID, trm_Label from defTerms where trm_NameInOriginatingDB='FAIMS.".$row_vocab[0]."'");
                if($row){

        print  "&nbsp;&nbsp;&nbsp;&nbsp;Term ".$row[0]."  ".$row[1]."  =>".$row_vocab[0]."<br/>";

                        $termsMap[$row_vocab[0]] = $row[0];
                }else{
                        //add new detail type into HEURIST
                        $query = "INSERT INTO defTerms (trm_Label, trm_Domain, trm_NameInOriginatingDB, trm_ParentTermID) VALUES (?,'enum',?, $vocabID)";
                        $stmt = $mysqli->prepare($query);
                        $fid = 'FAIMS.'.$row_vocab[0];
                        $stmt->bind_param('ss', $row_vocab[1], $fid );
                        $stmt->execute();
                        $trm_ID = $stmt->insert_id;
                        $stmt->close();

                        $termsMap[$row_vocab[0]] = $trm_ID;

        print  "&nbsp;&nbsp;&nbsp;&nbsp;Term added ".$trm_ID."  based on ".$row_vocab[0]." ".$row_vocab[1]."<br/>";
                }//add terms

            }


    }//for attributes

//----------------------------------------------------------------------------------------

    print "<h3>Rectypes</h3><br>";


    //create/update defRecTypes/defRecStrucure on base of AEntType and IdealAEnt
    $query1 =  "SELECT AEntTypeID, AEntTypeName, AEntTypeDescription FROM AEntType";
    $res1 = $dbfaims->query($query1);
    while ($row1 = $res1->fetchArray(SQLITE3_NUM))
    {
            $attrID = $row1[0];
            $rtyName = $row1[1]." [$attrID]";

            //try to find correspondant rectype in Heurist
            $row = mysqli__select_array($mysqli, "select rty_ID, rty_Name from defRecTypes where rty_NameInOriginatingDB='FAIMS.".$attrID."'");
            if($row){ //already exists

print  "RT ".$row[0]."  ".$row[1]."  =>".$attrID."<br/>";

                $rtyId = $row[0];
                $rtyName = $row[1];

                $rectypeMap[$attrID] = $row[0];
            }else{
                //add new record type into HEURIST
                $query = "INSERT INTO defRecTypes (rty_Name, rty_TitleMask, rty_Description, rty_NameInOriginatingDB) VALUES (?,'Record #[ID]',?,?)";
                $stmt = $mysqli->prepare($query);
                $fid = 'FAIMS.'.$attrID;

                $stmt->bind_param('sss', $rtyName, $row1[2], $fid);
                $stmt->execute();

                $rtyId = $stmt->insert_id;
                if($rtyId<1){
                    print "ERROR - rectype is not added !!!  ".$mysqli->error;
                    exit();
                }


                $stmt->close();

                $rectypeMap[$attrID] = $rtyId;

print  "RT added ".$rtyId."  based on ".$attrID." ".$rtyName." ".$row1[2]."<br/>";
            }

            //if AEntType has strucute described in IdealAEnt

            $query2 =  "SELECT AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".$attrID;

            $recstructure = $dbfaims->query($query2);
            while ($row_recstr = $recstructure->fetchArray(SQLITE3_NUM))
            {


                    $row = mysqli__select_array($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName from defDetailTypes d, defRecStructure r ".
                        "where d.dty_ID=r.rst_DetailTypeID and r.rst_RecTypeID=$rtyId and d.dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");

                    if($row){  //such detal in structure already exists

        print  "&nbsp;&nbsp;&nbsp;&nbsp;detail ".$row[0]."  ".$row[1]."<br/>";

                    }else{

                        $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");
                        if($row3){
                                //add new detail type into HEURIST
                                $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                                $stmt = $mysqli->prepare($query);
                                $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                                $stmt->execute();

                                $stmt->close();


        print  "&nbsp;&nbsp;&nbsp;&nbsp;detail added ".$row3[0]."  ".$row3[1]."  based on ".$row_recstr[0]."<br/>";
                        }else{
                            print  "&nbsp;&nbsp;&nbsp;DETAIL NOT FOUND FAIMS.".$row_recstr[0]." !<br>";
                        }
                    }

            }//for add details for structure

            //verify spatial data for this record type
            if(isset($dt_Geo) && $dt_Geo>0)
            {
                $row = mysqli__select_array($mysqli,
                            "select rst_DetailTypeID, rst_DisplayName from defRecStructure r ".
                            "where r.rst_RecTypeID=$rtyId and r.rst_DetailTypeID".$dt_Geo."'");

                if($row){  //such detal in structure already exists
        print  "&nbsp;&nbsp;&nbsp;&nbsp;detail ".$row[0]."  ".$row[1]."<br/>";
                }else{

                    $query3 = "SELECT count(*) FROM archentity ae where ae.AEntTypeID="
                                .$attrID." and asText(transform(casttosingle(ae.geospatialcolumn), 4326)) is not null";
                    $hasgeo = $dbfaims->query($query3);
                    $hasgeo = $hasgeo->fetchArray(SQLITE3_NUM);

        print "HAS GEO : ".$hasgeo[0]." entries<br>";

                    if($hasgeo[0]>0){
                        $row3 = mysqli__select_array($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_ID=".$dt_Geo);
                        if($row3){
                                        $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                                        $stmt = $mysqli->prepare($query);
                                        $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                                        $stmt->execute();
                                        $stmt->close();
        print  "&nbsp;&nbsp;&nbsp;&nbsp;detail added ".$row3[0]."  ".$row3[1]."<br/>";
                        }
                    }
                }
            }

    }//for AEntTypes

//exit();

//----------------------------------------------------------------------------------------
/* */

    print "<h3>Update records in H3 accoring to the most current Record set in FAIMS</h3><br>";

//asText(transform(casttosingle(ae.geospatialcolumn), 4326)) as Coordinate
    $query = "SELECT ae.uuid, ae.AEntTimestamp, ae.AEntTypeID, asText(transform(casttosingle(ae.geospatialcolumn), 4326)) as Coordinate,
                    av.freeText, av.VocabID, av.AttributeID, av.Measure, av.Certainty
    FROM aentvalue av
    JOIN (SELECT uuid, attributeid, max(valuetimestamp) as valuetimestamp, max(aenttimestamp) as aenttimestamp, archentity.deleted as entDel, aentvalue.deleted as valDel
            FROM aentvalue
            JOIN archentity USING (uuid)

        GROUP BY uuid, attributeid
          HAVING MAX(ValueTimestamp)
             AND MAX(AEntTimestamp)) USING (uuid, attributeid, valuetimestamp)
    JOIN archentity ae using (uuid, aenttimestamp)
    WHERE entDel is NULL
      AND valDel is NULL
 ORDER BY ae.uuid asc";

    $faims_id = null;
    $details = null;
    $rectype = null;
    $recID = null;
    $skip_faimsrec = false;
    $cntInsterted = 0;
    $cntUpdated = 0;

    $rs = $dbfaims->query($query);
    while ($row = $rs->fetchArray(SQLITE3_NUM))
    {
        if($faims_id!=$row[0]){

            if($details && count($details)>0){
                insert_update_Record($recID, $rectype, $details, $faims_id);
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
                $recID = mysqli__select_value($mysqli, "select dtl_RecID from recDetails where dtL_DetailTypeID=$dt_SourceRecordID and dtl_Value=$faims_id");
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

                  //get detailtype id in H3 by attrib id
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
                         }else if($row[4]){ //freetext
                            $value = $row[4];
                         }else if($row[7]){ //measure
                            $value = $row[7];
                         }else if($row[8]){ //Certainty
                            $value = $row[8];
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


          //  break; //DEBUG

    }//for records
*/

    print "Inserted ".$cntInsterted."<br/>";
    print "Updated ".$cntUpdated."<br/>";

//insert or update records in H3 databse
function insert_update_Record($recID, $rectype, $details, $faims_id)
{
    global $cntUpdated, $cntInsterted, $mysqli;

    if($recID>0){
        //delete existing details
        $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
        if(!$mysqli->query($query)){
            $syserror = $mysqli->error;
            print "<div style='color:red'> Error: Can not delete record details ".$syserror."</div>";
            return;
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

}


//
function faimsToH3_dt_mapping($ftype){

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
//H3 types ENUM('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude')
                return $res;
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
?>
  </div>
</body>
</html>

