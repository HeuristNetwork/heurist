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
* Returns kml for given record id. It searches detail with type 221 or 551
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
* @todo - only one kml per record, perhaps need to return the combination of kml
*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../external/geoPHP/geoPHP.inc');

mysql_connection_select(DATABASE);

$islist = array_key_exists("q", $_REQUEST);

// TODO: Remove, enable or explain
//header('Content-type: text/xml; charset=utf-8');

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"export.kml\"");
header("Content-Type: text/kml");

// TODO: Remove, enable or explain
//header("Content-Transfer-Encoding: binary");

/*
1. just print out content of uploaded kml files
*/
if(!$islist){

    if(array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")
    {
        //kml is stored in uploaded file
        $res = mysql_query("select ulf_ID, ulf_FilePath, ulf_FileName from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = ".(defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:"0")." OR dtl_DetailTypeID = ".(defined('DT_KML_FILE')?DT_KML_FILE:"0").")");

        if (mysql_num_rows($res)) {
            $file_data = mysql_fetch_array($res);

            if ($file_data[2]) {
                $filename = $file_data[1].$file_data[2]; // post 18/11/11 proper file path and name
            } else {
                $filename = HEURIST_FILESTORE_DIR."/".$file_data[0]; // pre 18/11/11 - bare numbers as names, just use file ID
            }

            print file_get_contents($filename);
        }else{
            $res = mysql_query("select dtl_Value from recDetails where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = ".(defined('DT_KML')?DT_KML:"0"));

            print "<?xml version='1.0' encoding='UTF-8'?>\n";
            print '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
            print '<Document>';

            if (mysql_num_rows($res)) {
                $kml = mysql_fetch_array($res);
                $kml = $kml[0];
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
print '<name>Export from Heurist</name>';

/*
2. create new KML output that contains placemarks created from WKT and links to heurist's uploaded kml files
*/
if($islist || (array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")){

    // for wkt
    $squery = "select rec_ID, rec_URL, rec_Title, d0.dtl_DetailTypeID, d0.dtl_Value, if(d0.dtl_Geo is null, null, asText(d0.dtl_Geo)) as dtl_Geo, ".
    "d1.dtl_Value as Date0, d2.dtl_Value as DateStart, d3.dtl_Value as DateEnd ";
    $ourwhere = " and (d0.dtl_RecID=rec_ID) and (d0.dtl_Geo is not null ".(defined('DT_KML')?" or d0.dtl_DetailTypeID=".DT_KML:"").")";

    $detTable =
    " left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=".(defined('DT_DATE')?DT_DATE:"0").
    " left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=".(defined('DT_START_DATE')?DT_START_DATE:"0").
    " left join recDetails d3 on d3.dtl_RecID=rec_ID and d3.dtl_DetailTypeID=".(defined('DT_END_DATE')?DT_END_DATE:"0").
    ", recDetails d0";

    //for kml
    $squery2 = "select  rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName ";
    $ourwhere2 = " and (dtl_RecID=rec_ID) and (dtl_DetailTypeID=".(defined('DT_KML_FILE')?DT_KML_FILE:"0").(defined('DT_FILE_RESOURCE')?" or (dtl_DetailTypeID = ".DT_FILE_RESOURCE." AND ulf_MimeExt='kml'))":")");
    $detTable2 = ", recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID";

    $isSearchKml = defined('DT_KML_FILE') || defined('DT_FILE_RESOURCE');


    if($islist){

        if(true || @$_REQUEST['rules']){ //search with h4 search engine

            $url = HEURIST_BASE_URL."/php/api/record_search.php?".$_SERVER["QUERY_STRING"]."&detail=ids&vo=h3"; //call h4
            $reclist = loadRemoteURLContent($url, false);
            $reclist = json_decode($reclist, true);
            $reccount = @$reclist['resultCount'];

            if (@$reclist['error']!=null || !($reccount>0)) {
                print '</Document></kml>';
                return;
            }
            
            $reclist = explode(",", $reclist['recIDs']);

            $reclist = array_slice($reclist,0,1000);

            $squery = $squery." from Records ".$detTable." where rec_ID in (".implode(",", $reclist).") ".$ourwhere;
            $squery2 = $squery2." from Records ".$detTable2." where rec_ID in (".implode(",", $reclist).") ".$ourwhere2;

        }else{

            if (array_key_exists('w',$_REQUEST)  && ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark'))
                $search_type = BOOKMARK;	// my bookmarks
            else
                $search_type = BOTH;	// all records

            $limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
            if (!$limit || $limit<1){
                $limit = 1000; //default limit in dispPreferences
            }

            $squery = prepareQuery(null, $squery, $search_type, $detTable, $ourwhere, null, $limit);
            if($isSearchKml){
                $squery2 = prepareQuery(null, $squery2, $search_type, $detTable2, $ourwhere2, null, $limit);
            }
        }


    }else{
        $squery = $squery." from Records ".$detTable." where rec_ID=".$_REQUEST["id"].$ourwhere;
        $squery2 = $squery2." from Records ".$detTable2." where rec_ID=".$_REQUEST["id"].$ourwhere2;
    }

    $wkt_reccount=0;
    $kml_reccount=0;

    if($squery){

        $res = mysql_query($squery);
        if($res===false){
            print '</Document></kml>';
            return;
        }
        $wkt_reccount = mysql_num_rows($res);

        $kml_reccount = 0;
        if($isSearchKml){
            $res2 = mysql_query($squery2);
            if($res2!==false)
                $kml_reccount = mysql_num_rows($res2);
        }

    }

    if ($wkt_reccount>0 || $kml_reccount>0)
    {

        if($wkt_reccount>0){
            while ($row = mysql_fetch_row($res)) {
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
                            $d1 = temporalToSimple($row[6]);
                            $d2 = temporalToSimple($row[7]); //start
                            $d3 = temporalToSimple($row[8]); //end date
                            if($d1 || ($d2 && $d3==null)){
                                if($d1==null){
                                    $d1 = $d2;
                                }
                                print "<TimeStamp><when>".$d1."</when></TimeStamp>";

                            }else{
                                if($d2 && $d3){
                                    print "<TimeSpan><begin>".$d2."</begin><end>".$d3."</end></TimeSpan>";
                                }
                            }
                        }

                        print '<id>'.$row[0].'</id>';
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
            while ($file_data = mysql_fetch_row($res2)) {
                if ($file_data[3]) {

                    print "<NetworkLink>";
                    print "<name>".$file_data[2]."</name>";
                    print "<Link id=\"".$file_data[0]."\">";
                    print "<href>".HEURIST_BASE_URL."export/xml/kml.php?id=".$file_data[0]."</href>";
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