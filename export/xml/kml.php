<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Returns kml for given record id. It searches detail with type 221 or 551
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
* @todo - only one kml per record, perhaps need to return the combination of kml
*/

require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');

require_once(dirname(__FILE__).'/../../hsapi/utilities/Temporal.php');

require_once(dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP

$system = new System();
if( !$system->init(@$_REQUEST['db']) ){
    die("Cannot connect to database");
}

$islist = array_key_exists("q", $_REQUEST);

if(@$_REQUEST['file']==1 || @$_REQUEST['file']===true){
header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"export.kml\"");
}
header("Content-Type: text/kml");


$dtFile = ($system->defineConstant('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:0);
$dtKMLfile = ($system->defineConstant('DT_KML_FILE')?DT_KML_FILE:0);
$dtKML = ($system->defineConstant('DT_KML')?DT_KML:0);
$dtDate = ($system->defineConstant('DT_DATE')?DT_DATE:0);
$dtDateStart = ($system->defineConstant('DT_START_DATE')?DT_START_DATE:0);
$dtDateEnd = ($system->defineConstant('DT_END_DATE')?DT_END_DATE:0);



$mysqli = $system->get_mysqli();

/*
1. just print out content of uploaded kml files
*/
if(!$islist){

    if(array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")
    {
        //kml is stored in uploaded file
        $kml_file = mysql__select_value($mysqli, 
            'select concat(ulf_FilePath,ulf_FileName) as fullPath from recDetails '
            .'left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = ' 
            . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = "
            .$dtFile." OR dtl_DetailTypeID = ".$dtKMLfile.")");

        if ($kml_file!=null) {

            $kml_file = resolveFilePath($kml_file);
            if(file_exists($kml_file)){
                print file_get_contents($kml_file);
            }   
                
        }else{
            //kml snippet
            $kml = mysql__select_value($mysqli, "select dtl_Value from recDetails where dtl_RecID = " 
                            . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = ".$dtKML);

            print "<?xml version='1.0' encoding='UTF-8'?>\n";
            print '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
            print '<Document>';

            if($kml!=null){
                print $kml;
            }

            print '</Document>';
            print '</kml>';
        }

    }
    exit;
}

print "<?xml version='1.0' encoding='UTF-8'?>\n";
print '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
print '<Document>';
print '<name>Exported from Heurist</name>';

/*
2. create new KML output that contains placemarks created from WKT and links to heurist's uploaded kml files
*/
if($islist || (array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")){

    // for wkt
    $squery = "select rec_ID, rec_URL, rec_Title, d0.dtl_DetailTypeID, d0.dtl_Value, if(d0.dtl_Geo is null, null, ST_asWKT(d0.dtl_Geo)) as dtl_Geo, ".
    "d1.dtl_Value as Date0, d2.dtl_Value as DateStart, d3.dtl_Value as DateEnd ";
    $ourwhere = " and (d0.dtl_RecID=rec_ID) and (d0.dtl_Geo is not null ".($dtKML>0?" or d0.dtl_DetailTypeID=".intval($dtKML):"").")";

    $detTable =
    " left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=".intval($dtDate).
    " left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=".intval($dtDateStart).
    " left join recDetails d3 on d3.dtl_RecID=rec_ID and d3.dtl_DetailTypeID=".intval($dtDateEnd).
    ", recDetails d0";

    //for kml
    $squery2 = "select  rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName ";
    
    $ourwhere2 = " and (dtl_RecID=rec_ID) and (dtl_DetailTypeID=".intval($dtKMLfile)
            .($dtFile>0?" or (dtl_DetailTypeID = ".intval($dtFile)." AND ulf_MimeExt='kml'))":")");
            
    $detTable2 = ", recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID";

    $isSearchKml = ($dtKMLfile>0 || $dtFile>0);

    if($islist){

            $_REQUEST['detail'] = 'ids'; // return ids only

            $result = recordSearch($system, $_REQUEST); //see db_recsearch.php
        
            if(!(@$result['status']==HEURIST_OK && @$result['data']['reccount']>0)){
                $error_msg = $system->getError();
                $error_msg = $error_msg[0]['message'];
                print $error_msg;
                print '</Document></kml>';
                return;
            }
            $result = $result['data'];
            $rec_ids = $result['records'];
            $limit = intval(@$_REQUEST['limit']);
            if($limit>0){
                $rec_ids = array_slice($rec_ids,0,$limit);    
            }

            $squery = $squery." from Records ".$detTable." where rec_ID in (".implode(",", prepareIds($rec_ids)).") ".$ourwhere;
            $squery2 = $squery2." from Records ".$detTable2." where rec_ID in (".implode(",", prepareIds($rec_ids)).") ".$ourwhere2;

    }else{
        $squery = $squery." from Records ".$detTable." where rec_ID=".intval($_REQUEST["id"]).$ourwhere;
        $squery2 = $squery2." from Records ".$detTable2." where rec_ID=".intval($_REQUEST["id"]).$ourwhere2;
    }

    $wkt_reccount=0;
    $kml_reccount=0;

    if($squery){

        $res = $mysqli->query($squery);
        if($res===false){
            print '</Document></kml>';
            return;
        }
        $wkt_reccount = $res->num_rows;

        $kml_reccount = 0;
        if($isSearchKml){
            $res2 = $mysqli->query($squery2);
            if($res2!==false)
                $kml_reccount = $res2->num_rows;
        }

    }

    if ($wkt_reccount>0 || $kml_reccount>0)
    {

        if($wkt_reccount>0){
            while ($row = $res->fetch_row()) {
                $kml = null;
                $dt = $row[3];
                if($row[5]){
                    $wkt = $row[5];
                    $geom = geoPHP::load($wkt,'wkt');
                    $kml = $geom->out('kml');
                    if($kml){
                        print '<Placemark>';

                        //timestap or timespan
                        if($row[6] || $row[7] || $row[8]){
                            
                            if($row[7] || $row[8]){
                                
                                if(!$row[7]) $row[7] = $row[8];
                                
                                //create timespan from two temporal objects
                                $dt = Temporal::mergeTemporals($row[7], $row[8]);
                                
                            }else if($row[6]){
                                $dt = new Temporal($row[6]);
                            }
                            if($dt && $dt->isValid()){
                                print $dt->toKML();
                            }
                        }

                        print '<id>'.htmlspecialchars($row[0]).'</id>';
                        print '<name>'.htmlspecialchars ($row[2]).'</name>';
                        if($row[1]){
                            print '<description><![CDATA[ <a href="'.$row[1].'">link</a>]]></description>'; 										}
                        print $kml;
                        print '</Placemark>';
                    }
                }else{
                    // TODO: Remove, enable or explain: /* @todo - tomorrow
                    /* @todo - tomorrow (!!!)
                    $kml = $row[1];
                    if(strpos($kml, "<?xml")>=0){
                    $start = strpos($kml, "<Placemark>");
                    $len = strpos($kml, strrchr($kml, "</Placemark>"))+strlen("</Placemark>")-$start;
                    $kml = substr($kml, $start, $len);
                    }
                    print $kml;
                    */
                }
            }//while wkt records
        }

        if($kml_reccount>0){
            while ($file_data = $res2->fetch_row()) {
                if ($file_data[3]) {
                    
                    $file_id = $file_data[0];

                    print "<NetworkLink>";
                    print "<name>".htmlspecialchars($file_data[2])."</name>";
                    print "<Link id=\"$file_id\">";
                    print "<href>".HEURIST_BASE_URL."export/xml/kml.php?id=$file_id</href>";
                    print "</Link>";
                    print "</NetworkLink>";
                }
            }//while kml records
        }


    }
}
print '</Document>';
print '</kml>';

?>