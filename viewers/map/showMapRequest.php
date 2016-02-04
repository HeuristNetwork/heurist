<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* search records and fills the structure to display on map
*
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

/**
* Returns array of mapobjects

$mapobjects = array(
"records"=>$geoRecords,
"geoObjects"=>$geoObjects,
"cntWithGeo"=>$cnt_geo,
"cntWithTime"=>$cnt_time,
"layers"=>$layers);

*/
function getMapObjects($request){

    $imagelayerRT = (defined('RT_IMAGE_SOURCE')?RT_IMAGE_SOURCE:0);
    $KMLlayerRT = (defined('RT_KML_SOURCE')?RT_KML_SOURCE:0);

    mysql_connection_select(DATABASE);

    if (array_key_exists('layers', $request)) { //special mode - load ALL image layers and kml records only - for general drop down list on map

        $request['ver'] = "1";
        $request['q'] = "type:".$imagelayerRT;
        if($KMLlayerRT){
            $request['q'] = $request['q'].",".$KMLlayerRT;
        }
        $search_type = BOTH;

    }else{

        if (! @$request['q']  ||  (@$request['ver'] && intval(@$request['ver']) < SEARCH_VERSION)){
            construct_legacy_search();      // migration path
        }

        if (@$request['w'] && ($request['w'] == 'B'  ||  $request['w'] == 'bookmark'))
            $search_type = BOOKMARK;	// my bookmarks
        else
            $search_type = BOTH;	// all records

    }

    if( !array_key_exists("limit", $request) ){ //not defined

        $limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['smarty-output-limit']);
        if (!$limit || $limit<1){
            $limit = 1000; //default limit in dispPreferences
        }

        $request["limit"] = $limit; //force limit
    }

    // find all matching records
    $cols = "rec_ID as bibID, rec_RecTypeID as rectype, rec_Title as title, rec_URL as URL";
    $query = REQUEST_to_query("select $cols ", $search_type, $request);

    $res = mysql_query($query);
    if (mysql_error()) {
        print mysql_error();
    }

    $records = array();
    $bibIDs = array();
    $imageLayers = array(); // list of ids of map image layers
    $geoObjects = array();  // coordinates
    $geoBibIDs = array();   // list of id of records that have geo references

    while ($bib = mysql_fetch_assoc($res)) {
        $bibID = $bib["bibID"];
        if (! $bibID) continue;

        $records[$bibID] = $bib;
        array_push($bibIDs, $bibID);

        if($bib["rectype"]==$imagelayerRT){ //map image layer
            array_push($imageLayers, $bibID);
            $geoBibIDs[$bibID] = $bibID;
        }
    }



    foreach ($bibIDs as $bibID) {
        //0 DT_SHORT_SUMMARY
        //1 DT_EXTENDED_DESCRIPTION
        //2 - record URL
        //3 DT_FILE_RESOURCE
        //REMOVED 4 DT_LOGO_IMAGE
        //REMOVED 5 DT_THUMBNAIL
        //REMOVED 6 DT_IMAGES
        //4 7 DT_MAP_IMAGE_LAYER_REFERENCE
        //5 8 DT_KML
        //6 9 DT_KML_FILE
        //7 10 record type

        //d.dtl_UploadedFileID,e.dtl_UploadedFileID,f.dtl_UploadedFileID,

        //					0				1			2			3				4 imagelayer 5 kmltext		6 kmlfile				7			8
        $squery = "select a.dtl_Value, b.dtl_Value, rec_URL, c.dtl_UploadedFileID, g.dtl_Value, h.dtl_Value, i.dtl_UploadedFileID, rec_RecTypeID, rec_Title, j.dtl_Value
        from Records
        left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:"0").
        " left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(defined('DT_EXTENDED_DESCRIPTION')?DT_EXTENDED_DESCRIPTION:"0").
        " left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:"0").
        //		" left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(defined('DT_LOGO_IMAGE')?DT_LOGO_IMAGE:"0").
        //		" left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(defined('DT_THUMBNAIL')?DT_THUMBNAIL:"0").
        //		" left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(defined('DT_IMAGES')?DT_IMAGES:"0").
        " left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(defined('DT_MAP_IMAGE_LAYER_REFERENCE')?DT_MAP_IMAGE_LAYER_REFERENCE:"0").
        " left join recDetails h on h.dtl_RecID=rec_ID and h.dtl_DetailTypeID=".(defined('DT_KML')?DT_KML:"0").
        " left join recDetails i on i.dtl_RecID=rec_ID and i.dtl_DetailTypeID=".(defined('DT_KML_FILE')?DT_KML_FILE:"0").
        " left join recDetails j on j.dtl_RecID=rec_ID and j.dtl_DetailTypeID=".(defined('DT_SHOW_IN_MAP_BG_LIST')?DT_SHOW_IN_MAP_BG_LIST:"0").
        " where rec_ID=$bibID";

        $res = mysql_query($squery);
        $row = mysql_fetch_row($res);
        if($row)
        {
            $records[$bibID]["recID"] = $bibID;
            $records[$bibID]["rectype"] = $row[7];
            $records[$bibID]["description"] = ( $row[0] ?$row[0] :($row[1]?$row[1]:"") );
            $records[$bibID]["url"] = $row[2]; //($row[2] ? "'".$row[2]."' target='_blank'"  :"'javascript:void(0);'");
            //'javascript:{this.href="'+$row[2]+'"}' : 'javascript:{return false;}');//javascript:void(0)}');
            $records[$bibID]["icon_url"] = HEURIST_ICON_URL . $row[7] . ".png";;

            $thumb_url = getThumbnailURL($bibID); //function from uploadFile.php
            if($thumb_url==""){  //if thumb not defined - use rectype default thumb
                $thumb_url = HEURIST_ICON_URL.	"thumb/th_" . $row[7] . ".png";
            }

            $records[$bibID]["thumb_url"] = $thumb_url;

            if($row[4] && is_numeric($row[4]) && ! in_array($row[4],$imageLayers)){ //DT_MAP_IMAGE_LAYER_REFERENCE
                array_push($imageLayers, $row[4]);
            }
            $kml_path =  getKmlFilePath($row[3]); //DT_FILE_RESOURCE
            // removed by SAW as DT_KML_FILE changed from a file base type to blocktext
            //		if($kml_path==null){
            //			$kml_path =  getKmlFilePath($row[6]); //DT_KML_FILE
            //			}
            if($kml_path!=null){
                array_push($geoObjects, array("bibID" => $bibID, "type" => "kmlfile", "fileid" => $kml_path, "title" => $row[8], "isbackground"=>$row[9]));
                $geoBibIDs[$bibID] = $bibID;
            }else if ($row[5]) { //DT_KML dtl_value contains KML		saw TODO: modify to check that text is valid KML.
                array_push($geoObjects, array("bibID" => $bibID, "type" => "kml", "recid" => $bibID, "title" => $row[8]));
                $geoBibIDs[$bibID] = $bibID;
            }
        }
    }//for

    if($bibIDs && count($bibIDs)>0)
    {
        // Find the records that actually have any geographic data to plot
        $res = mysql_query("select dtl_RecID, dtl_Value, astext(dtl_Geo), astext(envelope(dtl_Geo)) from recDetails where dtl_Geo is not null and dtl_RecID in (" . join(",", $bibIDs) . ")");
        if(mysql_error()) {
        }
        if($res){
            while ($val = mysql_fetch_row($res)) {
                // get the bounding box
                if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[3], $matches)) {
                    $bbox = array("w" => floatval($matches[1]), "s" => floatval($matches[2]), "e" => floatval($matches[5]), "n" => floatval($matches[6]));
                }
                else $bbox = null;

                $geoobj = parseValueFromDb($val[0], $val[1], $val[2], $bbox);
                if($geoobj){
                    array_push($geoObjects, $geoobj);
                    $geoBibIDs[$val[0]] = $val[0];
                }
            }
        }
    }//$bibIDs!=null

    // some records may contain reference to map image layer record (dettype 588),
    // but we may have such records in search result as well rectype=$imagelayerRT
    if($bibIDs && count($bibIDs)>0){
        $squery = "select rec_ID  from Records
        where rec_ID in (" . join(",", $bibIDs) . ") and rec_RecTypeID=$imagelayerRT";
        $res = mysql_query($squery);
        if($res){
            while ($val = mysql_fetch_row($res)) {
                array_push($imageLayers, $val[0]);
                $geoBibIDs[$val[0]] = $val[0];
            }
        }
    }


    $layers = array();
    //find image layers
    if(count($imageLayers)>0){

        $squery = "select rec_ID, a.dtl_Value as title,".
        " (select trm_Label from defTerms where trm_ID=b.dtl_Value) as type,".
        " c.dtl_Value as url,".
        " (select trm_Label from defTerms where trm_ID=d.dtl_Value) as mime_type,".
        " e.dtl_Value as min_zoom, f.dtl_Value as max_zoom, g.dtl_Value as copyright, j.dtl_Value as isbackground".
        " from Records".
        " left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(defined('DT_SHORT_NAME')?DT_SHORT_NAME:"0").
        " left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(defined('DT_MAP_IMAGE_LAYER_SCHEMA')?DT_MAP_IMAGE_LAYER_SCHEMA:"0").
        " left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(defined('DT_SERVICE_URL')?DT_SERVICE_URL:"0").
        " left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(defined('DT_MIME_TYPE')?DT_MIME_TYPE:"0").
        " left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(defined('DT_MINMUM_ZOOM_LEVEL')?DT_MINMUM_ZOOM_LEVEL:"0").
        " left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(defined('DT_MAXIMUM_ZOOM_LEVEL')?DT_MAXIMUM_ZOOM_LEVEL:"0").
        " left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(defined('DT_ALTERNATE_NAME')?DT_ALTERNATE_NAME:"0").
        " left join recDetails j on j.dtl_RecID=rec_ID and j.dtl_DetailTypeID=".(defined('DT_SHOW_IN_MAP_BG_LIST')?DT_SHOW_IN_MAP_BG_LIST:"0").
        " where rec_ID in (" . join(",", $imageLayers) . ")";

        $res = mysql_query($squery);
        while ($rec = mysql_fetch_assoc($res)) {

            //find the extent for image layer
            if($rec['type'] == "maptiler"){
                $manifest_file = $rec['url']."tilemapresource.xml";
            }else if($rec['type'] == "virtual earth"){
                $manifest_file = $rec['url']."MapCruncherMetadata.xml";
            }else{
                $rec['error'] = "Wrong or non supported map type ".$rec['type'];
            }

            if(!$rec['url']){

                $rec['error'] = "URL is not defined for image layer";

            }else if($manifest_file){

                if(file_exists($manifest_file)){
                    $manifest = simplexml_load_file($manifest_file);
                }else{
                    $content = loadRemoteURLContent($manifest_file, false);
                    $manifest = simplexml_load_string($content);
                }


                if($manifest==null || is_string($manifest)){ //manifest not found

                    $rec['error'] = "Cannot load manifest file image layer. ".$manifest_file;

                }else{
                    if($rec['type'] == "maptiler"){

                        foreach ($manifest->children() as $f_gen){
                            if($f_gen->getName()=="BoundingBox"){
                                $arr = $f_gen->attributes();
                                $rec['extent'] = $arr['miny'].','.$arr['minx'].','.$arr['maxy'].','.$arr['maxx']; //warning!!! wrong labels in these manifests!!!!
                                break;
                            }
                        }
                    }else{

                        $rect = findXMLelement($manifest, 0, array("LayerList","Layer","SourceMapRecordList","SourceMapRecord","MapRectangle"));

                        if($rect){
                            $rec['extent'] = "";
                            foreach ($rect->children() as $pnt){
                                $arr = $pnt->attributes();
                                $lon = $arr['lon'];
                                $lat = $arr['lat'];
                                $rec['extent'] = $rec['extent'].$lon.",".$lat.",";
                            }
                            //$rec['extent'] = $sw['lon'].','.$sw['lat'].','.$ne['lon'].','.$ne['lat'];
                        }
                    }

                    if(!array_key_exists('extent',$rec)){
                        $rec['error'] = "Cannot find bounds parameters in manifest file";
                    }
                }
            }
            if(!$rec['max_zoom']){
                $rec['max_zoom'] = 19;
            }

            array_push($layers, $rec);
        }


    }//count($imageLayers)>0


    $timeObjects = array();

    if(!@$request['layers'])
    {

        // Find time extents -- must have at least a start time (end time is optional)

        // check for specific details first
        //saw TODO; modify this for handle durations with a start or end date
        if (defined('DT_START_DATE') && defined('DT_END_DATE') && $bibIDs && count($bibIDs)>0){

            $squery = "select START.dtl_RecID, START.dtl_Value, END.dtl_Value ".
            "from recDetails START left join recDetails END on START.dtl_RecID=END.dtl_RecID ".
            "and END.dtl_DetailTypeID=".DT_END_DATE.
            " where START.dtl_DetailTypeID=".DT_START_DATE.
            " and (START.dtl_Value || END.dtl_Value) ".
            "and START.dtl_RecID in (" . join(",", $bibIDs) . ")";

            $res = mysql_query($squery);
            while ($val = mysql_fetch_row($res)) {
                if ($val[1] || $val[2]) {
                    $timeObjects[$val[0]] = array($val[1], $val[2]);
                }
            }
        }

        $anyDateBibIDs = array();  //no date enabled
        foreach($bibIDs as $bibID) {
            if (!array_key_exists($bibID, $timeObjects) ){
                array_push($anyDateBibIDs, $bibID);
            }
        }

        // now process those records that don't have specific start and end dates -
        // try to extract date from details
        if (count($anyDateBibIDs) > 0) {
            $dates = array();
            $years =array();
            $res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
                from Records
                cross join defDetailTypes dt
                left join recDetails d on d.dtl_RecID = rec_ID and d.dtl_DetailTypeID = dt.dty_ID
                where rec_ID in (" . join(",", $anyDateBibIDs) . ")
                and dt.dty_Type = 'date'
            group by rec_ID");
            if($res){
                while ($val = mysql_fetch_row($res)) {
                    if ($val[1] && preg_match("/^\\d+\\s*bc/i", $val[1])) {// convert BC to a - sign
                        $val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
                    }
                    if ($val[2] && preg_match("/^\\d+\\s*bc/i", $val[2])) {// convert BC to a - sign
                        $val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
                    }

                    $dates[$val[0]] = array($val[1],($val[1] !== $val[2] ? $val[2]:null));
                }
            }

            $res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
                from Records
                cross join defDetailTypes yt
                left join recDetails y on y.dtl_RecID = rec_ID and y.dtl_DetailTypeID = yt.dty_ID
                where rec_ID in (" . join(",", $anyDateBibIDs) . ")
                and yt.dty_Type = 'year'
            group by rec_ID");
            if($res){
                while ($val = mysql_fetch_row($res)) {
                    if ($val[1] && preg_match("/^\\d+\\s*bc/i", $val[1])) {// convert BC to a - sign
                        $val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
                    }
                    if ($val[2] && preg_match("/^\\d+\\s*bc/i", $val[2])) {// convert BC to a - sign
                        $val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
                    }

                    $years[$val[0]] = array($val[1],($val[1] !== $val[2] ? $val[2]:null));
                }
            }

            foreach( $anyDateBibIDs as $bibID){

                $sd = (@$dates[$bibID][0] ? $dates[$bibID][0]:null);
                $ed = (@$dates[$bibID][1] ? $dates[$bibID][1]:null);
                $sy = (@$years[$bibID][0] ? $years[$bibID][0]:null);
                $ey = (@$years[$bibID][1] ? $years[$bibID][1]:null);

                if (! $sd  &&  ! $ed  && $sy && $sy == $ey)
                    $ey = $sy + 1;

                $s = ($sd ? $sd : $sy);
                $e = ($ed ? $ed : $ey);
                if ($s >= $e) $e = null;

                $timeObjects[$bibID] = array($s, $e);
            }
        }//if

    }//if not layer request

    //sort($geoBibIDs);

    $geoRecords = array();
    $cnt_geo = 0;
    $cnt_time = 0;

    //foreach ($geoBibIDs as $bibID) {
    foreach ($bibIDs as $bibID) { //loop for all records
        //	$bibID = ""+$bibID;

        $isNotGeoLoc = !@$geoBibIDs[$bibID];

        if((!$isNotGeoLoc) || array_key_exists($bibID, $timeObjects) ){

            $geoRecords[$bibID] = $records[$bibID];
            if($isNotGeoLoc){
                //no geo data - only timedata
                array_push($geoObjects, array("bibID" => $bibID, "type" => "none")); //empty georeference
                $geoBibIDs[$bibID] = $bibID;
            }else{
                $cnt_geo++;
            }
            if ( array_key_exists($bibID, $timeObjects) ) {
                list($start, $end) = $timeObjects[$bibID];
                $geoRecords[$bibID]["start"] = $start;
                if ($end) $geoRecords[$bibID]["end"] = $end;
                $cnt_time++;
            }
        }
    }

    //count($geoRecords)
    $mapobjects = array(
        "records"=>$geoRecords,
        "geoObjects"=>$geoObjects,
        "cntWithGeo"=>$cnt_geo,
        "cntWithTime"=>$cnt_time,
        "layers"=>$layers);

    return $mapobjects;

}

/*
if (! HEURIST) HEURIST = {};
if (! HEURIST.tmap) HEURIST.tmap = {};
HEURIST.tmap.records = <?= json_format($geoRecords) ?>;
HEURIST.tmap.geoObjects = <?= json_format($geoObjects) ?>;
HEURIST.tmap.totalRecordCount = <?= count($geoRecords) ?>;
*/

function getKmlFilePath($fileID){
    if ($fileID) {/* search for KML file */
        $fres = mysql_query(
            "select ulf_ObfuscatedFileID, ulf_MimeExt, ulf_FileName from recUploadedFiles where ulf_ID = ".intval($fileID));

        if ($fres) {
            $row2 = mysql_fetch_row($fres);
            $ext = strtolower( $row2[1] );
            if($ext!="kml" && $row2[2] && preg_match('/\\.([^.]+)$/', $row2[2], $matches)){
                $ext = strtolower($matches[1]);
            }
            if($row2[0] && ($ext=="kml")){
                return $row2[0];
            }
        }
    }
    return null;
}

/**
* helper function to scan xml for element with given name
*
* @param mixed $xml
* @param mixed $ind
* @param mixed $atree
*/
function findXMLelement($xml, $ind, $atree){

    foreach ($xml->children() as $f_gen){
        if($f_gen->getName()==$atree[$ind]){

            $ind++;
            if($ind==count($atree)){
                return $f_gen;
            }else{
                return findXMLelement($f_gen, $ind, $atree);
            }

        }
    }

    return null;
}

/**
* parse value from database (asText)
*
* @param mixed $recId
* @param mixed $type
* @param mixed $geovalue
*/
function parseValueFromDb($recId, $type, $geovalue, $bbox)
{
    $val = array($recId, $type, $geovalue);
    $res = null;

    switch ($val[1]) {
        case "p":
            if (preg_match("/POINT\\((\\S+)\\s+(\\S+)\\)/i", $val[2], $matches)) {
                $res = array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2])));
            }
            break;

        case "r":
            if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[2], $matches)) {
                $res = array("bibID" => $val[0], "type" => "rect",
                    "geo" => array("x0" => floatval($matches[1]), "y0" => floatval($matches[2]), "x1" => floatval($matches[5]), "y1" => floatval($matches[6]), "bounds" => $bbox));
            }
            break;

        case "c":
            if (preg_match("/LINESTRING\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+\\S+,\\s*\\S+\\s+\\S+,\\s*\\S+\\s+\\S+\\)/i", $val[2], $matches)) {
                $res = array("bibID" => $val[0], "type" => "circle",
                    "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]), "radius" => floatval($matches[3] - $matches[1]), "bounds" => $bbox));
            }
            break;

        case "pl":
            if (! preg_match("/POLYGON\\(\\((.+)\\)\\)/i", $val[2], $matches)) return null;
            if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) return null;
            $matches = $matches[0];

            $points = array();
            for ($j=0; $j < count($matches)-1; ++$j) {
                preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);
                array_push($points, array("x" => floatval($match_matches[1]), "y" => floatval($match_matches[2])));
            }
            $res = array("bibID" => $val[0], "type" => "polygon", "geo" => array("points" => $points, "bounds" => $bbox));
            break;

        case "l":

            if (! preg_match("/LINESTRING\\((.+)\\)/i", $val[2], $matches)) return null;
            if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) return null;

            $matches = $matches[0];

            $points = array();
            for ($j=0; $j < count($matches); ++$j) {
                preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);

                array_push($points, array("x" => floatval($match_matches[1]), "y" => floatval($match_matches[2])));
                //			array_push($points, array(floatval($match_matches[2]), floatval($match_matches[1])));
            }
            if (count($points) <= 1) continue;
            $res = array("bibID" => $val[0], "type" => "polyline", "geo" => array("points" => $points, "bounds" => $bbox));

            //		$encoding = dpEncode($points);
            //		array_push($geoObjects, array("bibID" => $val[0], "type" => "path", "geo" => array("points" => $encoding[0], "levels" => $encoding[1], "bounds" => $bbox)));

    }
    return $res;
}
?>
