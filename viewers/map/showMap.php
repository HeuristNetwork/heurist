<?php

	/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

	define('SEARCH_VERSION', 1);

	require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");

	require_once(dirname(__FILE__)."/../../search/parseQueryToSQL.php");

	require_once("encodePolyline.php");

	header("Content-type: text/javascript");


	mysql_connection_db_select(DATABASE);

	if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
        construct_legacy_search();      // migration path

	if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
	else
	$search_type = BOTH;	// all records

	// find all matching records
	$cols = "rec_ID as bibID, rec_RecTypeID as rectype, rec_Title as title, rec_URL as URL";
	$query = REQUEST_to_query("select $cols ", $search_type);

	error_log(">>>>>>>>>>>>>>>>>>>>>>>".$query);
	$res = mysql_query($query);
	print mysql_error();

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

	if($bib["rectype"]==168){ //map image layer
		array_push($imageLayers, $bibID);
		$geoBibIDs[$bibID] = $bibID;
	}
	}

	function getKmlFilePath($fileID){
	if ($fileID) {/* search for KML file */
			$fres = mysql_query(
			    "select ulf_ObfuscatedFileID, ulf_MimeExt from recUploadedFiles where ulf_ID = ".intval($fileID));

			if ($fres) {
				$row2 = mysql_fetch_row($fres);
				$ext = strtolower( $row2[1] );
				if($row2[0] && $ext=="kml"){
					return $row2[0];
				}
			}
	}
	return null;
	}

	foreach ($bibIDs as $bibID) {
	//0 303 short summary
	//1 191 extended desc
	//2 - record URL
	//3 221 assoc file (may be kml)
	//4 222 logo image
	//5 223 thumbnail
	//6 224 images
	//7 588 reference to record Map Image Layer
	//8 551 dtl_value contains KML
	//9 552 reference to KML file (like associated file 221)

	$res = mysql_query("select a.dtl_Value, b.dtl_Value, rec_URL, c.dtl_UploadedFileID,d.dtl_UploadedFileID,e.dtl_UploadedFileID,f.dtl_UploadedFileID, g.dtl_Value, h.dtl_Value, i.dtl_UploadedFileID
						  from Records
		  left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(DT_SHORT_SUMMARY?DT_SHORT_SUMMARY:"303").
		" left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(DT_EXTENDED_DESCRIPTION?DT_EXTENDED_DESCRIPTION:"191").
		" left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(DT_ASSOCIATED_FILE?DT_ASSOCIATED_FILE:"221").
		" left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(DT_LOGO_IMAGE?DT_LOGO_IMAGE:"222").
		" left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(DT_THUMBNAIL?DT_THUMBNAIL:"223").
		" left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(DT_IMAGES?DT_IMAGES:"224").
		" left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(DT_MAP_IMAGE_LAYER_REFERENCE?DT_MAP_IMAGE_LAYER_REFERENCE:"588").
		" left join recDetails h on h.dtl_RecID=rec_ID and h.dtl_DetailTypeID=".(DT_KML?DT_KML:"551").
		" left join recDetails i on i.dtl_RecID=rec_ID and i.dtl_DetailTypeID=".(DT_KML_FILE?DT_KML_FILE:"552").
		" where rec_ID=$bibID");
	$row = mysql_fetch_row($res);
	$records[$bibID]["description"] = ($row[0] ? $row[0] : $row[1]);
	$records[$bibID]["url"] = ($row[2] ? $row[2] : '');
	$records[$bibID]["thumb_file_id"] = "";

	if($row[7]){
		array_push($imageLayers, $row[7]);
	}

	$fileID = ($row[3] ? $row[3] : ($row[4] ? $row[4] : ($row[5] ? $row[5] : ($row[6] ? $row[6] : null))));
	if ($fileID!=null) {/* search for thumbnail image */
			$fres = mysql_query(
			    "select ulf_ObfuscatedFileID, ulf_MimeExt from recUploadedFiles where ulf_ID = ".intval($fileID));
			if ($fres) {
				$row2 = mysql_fetch_row($fres);
				$ext = strtolower($row2[1]);
				if($row2[0] && ($ext=="jpg" || $ext=="png" || $ext=="gif")){
					$records[$bibID]["thumb_file_id"] = $row2[0];
				}
			}
	}
	$kml_path =  getKmlFilePath($row[3]); //221
	if($kml_path==null){
		$kml_path =  getKmlFilePath($row[9]); //552
	}
	if($kml_path!=null){
		array_push($geoObjects, array("bibID" => $bibID, "type" => "kmlfile", "fileid" => $kml_path));
		$geoBibIDs[$bibID] = $bibID;
	}else if ($row[8]) { //551 dtl_value contains KML
		array_push($geoObjects, array("bibID" => $bibID, "type" => "kml", "recid" => $bibID));
		$geoBibIDs[$bibID] = $bibID;
	}
	}//for

	// Find the records that actually have any geographic data to plot
	$res = mysql_query("select dtl_RecID, dtl_Value, astext(dtl_Geo), astext(envelope(dtl_Geo)) from recDetails where dtl_Geo is not null and dtl_RecID in (" . join(",", $bibIDs) . ")");
	error_log(mysql_error());
	if($res){
		while ($val = mysql_fetch_row($res)) {
	// get the bounding box
	if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[3], $matches)) {
		$bbox = array("w" => floatval($matches[1]), "s" => floatval($matches[2]), "e" => floatval($matches[5]), "n" => floatval($matches[6]));
	}
	else $bbox = null;

	switch ($val[1]) {
	    case "p":
		if (preg_match("/POINT\\((\\S+)\\s+(\\S+)\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]))));
		} else continue;
		break;

	    case "r":
		if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "rect",
			           "geo" => array("x0" => floatval($matches[1]), "y0" => floatval($matches[2]), "x1" => floatval($matches[5]), "y1" => floatval($matches[6]), "bounds" => $bbox)));
		} else continue;
		break;

	    case "c":
		if (preg_match("/LINESTRING\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+\\S+,\\s*\\S+\\s+\\S+,\\s*\\S+\\s+\\S+\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "circle",
			           "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]), "radius" => floatval($matches[3] - $matches[1]), "bounds" => $bbox)));
		} else continue;
		break;

	    case "pl":
		if (! preg_match("/POLYGON\\(\\((.+)\\)\\)/i", $val[2], $matches)) continue;
		if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) continue;
		$matches = $matches[0];

		$points = array();
		for ($j=0; $j < count($matches)-1; ++$j) {
			preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);
			array_push($points, array("x" => floatval($match_matches[1]), "y" => floatval($match_matches[2])));
		}
		array_push($geoObjects, array("bibID" => $val[0], "type" => "polygon", "geo" => array("points" => $points, "bounds" => $bbox)));
		break;

	    case "l":
		if (! preg_match("/LINESTRING\\((.+)\\)/i", $val[2], $matches)) continue;
		if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) continue;
		$matches = $matches[0];

		$points = array();
		for ($j=0; $j < count($matches)-1; ++$j) {
			preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);
			array_push($points, array("x" => floatval($match_matches[1]), "y" => floatval($match_matches[2])));
					//			array_push($points, array(floatval($match_matches[2]), floatval($match_matches[1])));
		}
		if (count($points) <= 1) continue;
		array_push($geoObjects, array("bibID" => $val[0], "type" => "polyline", "geo" => array("points" => $points, "bounds" => $bbox)));

				//		$encoding = dpEncode($points);
				//		array_push($geoObjects, array("bibID" => $val[0], "type" => "path", "geo" => array("points" => $encoding[0], "levels" => $encoding[1], "bounds" => $bbox)));

	}
	$geoBibIDs[$val[0]] = $val[0];
			/*DEBUG
			error_log("ADDED1:".is_string($val[0])."    ".$geoBibIDs[$val[0]]);
			error_log("1>>>>>>".$geoBibIDs[$val[0]]);
			error_log("2>>>>>>".$geoBibIDs["97025"]);
			error_log("3>>>>>>".$geoBibIDs[$bibID]);
			*/
		}
	}

	// OLD WAY TO STORE GEO DATA - directly in dtl_value as dettypes: 210(long) and 211(lat)
	$res = mysql_query("select LAT.dtl_RecID, LNG.dtl_Value, LAT.dtl_Value from recDetails LAT, recDetails LNG where LAT.dtl_DetailTypeID=211 and LNG.dtl_DetailTypeID=210 and LAT.dtl_RecID=LNG.dtl_RecID and LNG.dtl_RecID in (" . join(",", $bibIDs) . ")");
	if($res){
		while ($val = mysql_fetch_row($res)) {
	array_push($geoObjects, array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($val[1]), "y" => floatval($val[2]))));
	$geoBibIDs[$val[0]] = $val[0];
		}
	}

	//some records may contain reference to map image layer record (dettype 588),
	// but we may have such records in search result as well rectype=168
	/*
	$res = mysql_query("select rec_ID  from Records
						 where rec_ID in (" . join(",", $bibIDs) . ") and rec_RecTypeID=168");
	if($res){
		while ($val = mysql_fetch_row($res)) {
			array_push($imageLayers, $val[0]);
			$geoBibIDs[$val[0]] = $val[0];
		}
	}
	*/


	$layers = array();
	//find image layers
	if(count($imageLayers)>0){

		$res = mysql_query("select rec_ID, a.dtl_Value as title,
		(select trm_Label from defTerms where trm_ID=b.dtl_Value) as type,
        c.dtl_Value as url,
        (select trm_Label from defTerms where trm_ID=d.dtl_Value) as mime_type,
		e.dtl_Value as min_zoom, f.dtl_Value as max_zoom, g.dtl_Value as copyright
						  from Records
		  left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(DT_TITLE_SHORT?DT_TITLE_SHORT:"173").
		" left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(DT_MAP_IMAGE_LAYER_SCHEMA?DT_MAP_IMAGE_LAYER_SCHEMA:"585").
		" left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(DT_SERVICE_URL?DT_SERVICE_URL:"339").
		" left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(DT_MIME_TYPE?DT_MIME_TYPE:"289").
		" left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(DT_MINMUM_ZOOM_LEVEL?DT_MINMUM_ZOOM_LEVEL:"586").
		" left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(DT_MAXIMUM_ZOOM_LEVEL?DT_MAXIMUM_ZOOM_LEVEL:"587").
		" left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(DT_ALTERNATE_NAME?DT_ALTERNATE_NAME:"331").
		" where rec_ID in (" . join(",", $imageLayers) . ")");

		while ($rec = mysql_fetch_assoc($res)) {
	array_push($layers, $rec);
		}


		/*
	$res = mysql_query("select dtl_DetailTypeId, dtl_Value, dtl_RecID
						from recDetails
						where dtl_RecID in (" . join(",", $imageLayers) . ")
				  		order by dtl_RecID");

	$currrecid = null;
			$l_title = "";
			$l_type= null;
			$l_url = null;
			$l_mime_type = 245;
			$l_min_zoom = 1;
			$l_max_zoom = 19;
			$l_copyright = "";

	while ($val = mysql_fetch_row($res)) {
		error_log(">>>>>>".$val[0]."   ".$val[1]);
		if($currrecid != $val[2]){

			if($currrecid){
				array_push($layers,  array(
							"title"=>$l_title,
							"type"=>$l_type,
							"url"=>$l_url,
							"mime_type"=>$l_mime_type,
							"min_zoom"=>$l_min_zoom,
							"max_zoom"=>$l_max_zoom,
							"copyright"=>$l_copyright
							));
			}

			$l_title = "";
			$l_type= null;
			$l_url = null;
			$l_mime_type = 245;
			$l_min_zoom = 1;
			$l_max_zoom = 19;
			$l_copyright = "";

			$currrecid = $val[2];
		}

		if($val[0]==173) $l_title = $val[1];
		if($val[0]==585) $l_type= $val[1];
		if($val[0]==339) $l_url = $val[1];
		if($val[0]==289) $l_mime_type = $val[1];
		if($val[0]==586) $l_min_zoom = $val[1];
		if($val[0]==587) $l_max_zoom = $val[1];
		if($val[0]==331) $l_copyright = $val[1];
	}//while

		error_log("ADDED!!!!!");

		array_push($layers,  array(
							"title"=>$l_title,
							"type"=>$l_type,
							"url"=>$l_url,
							"mime_type"=>$l_mime_type,
							"min_zoom"=>$l_min_zoom,
							"max_zoom"=>$l_max_zoom,
							"copyright"=>$l_copyright
							));
		*/

	}

	// Find time extents -- must have at least a start time (end time is optional)
	/*
	$timeObjects = array();
	$res = mysql_query("select START.dtl_RecID, START.dtl_Value, END.dtl_Value from recDetails START left join recDetails END on START.dtl_RecID=END.dtl_RecID and END.dtl_DetailTypeID=178 where START.dtl_DetailTypeID=177 and START.dtl_Value and START.dtl_RecID in (" . join(",", $bibIDs) . ")");
	while ($val = mysql_fetch_row($res)) {
	$timeObjects[$val[0]] = array($val[1], $val[2]);
	}
	*/

	$timeObjects = array();
	//"
	//select d.dtl_RecID, min(d.dtl_Value), max(d.dtl_Value), min(y.dtl_Value), max(y.dtl_Value)
	//from recDetails b, recDetails y
	//"
	$dates = array();
	$years =array();
	$res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
					  from Records
				cross join defDetailTypes dt
				 left join recDetails d on d.dtl_RecID = rec_ID and d.dtl_DetailTypeID = dt.dty_ID
					 where rec_ID in (" . join(",", $bibIDs) . ")
					   and dt.dty_Type = 'date'
				  group by rec_ID");
	if($res){
		while ($val = mysql_fetch_row($res)) {
	if (preg_match("/^\\d+\\s*bc/i", $val[1])) {
		$val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
	}
	if (preg_match("/^\\d+\\s*bc/i", $val[2])) {
		$val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
	}

	$dates[$val[0]] = array($val[1],$val[2]);
		}
	}

	$res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
					  from Records
				cross join defDetailTypes yt
				 left join recDetails y on y.dtl_RecID = rec_ID and y.dtl_DetailTypeID = yt.dty_ID
					 where rec_ID in (" . join(",", $bibIDs) . ")
					   and yt.dty_Type = 'yesr'
				  group by rec_ID");
	if($res){
		while ($val = mysql_fetch_row($res)) {
	if (preg_match("/^\\d+\\s*bc/i", $val[1])) {
		$val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
	}
	if (preg_match("/^\\d+\\s*bc/i", $val[2])) {
		$val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
	}

	$years[$val[0]] = array($val[1],$val[2]);
		}
	}

	foreach( $bibIDs as $bibID){
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

	//sort($geoBibIDs);

	$geoRecords = array();
	$cnt_geo = 0;
	$cnt_time = 0;
	//foreach ($geoBibIDs as $bibID) {
	foreach ($bibIDs as $bibID) { //loop for all records
		//	$bibID = ""+$bibID;

		//error_log("2>>>>>>".$geoBibIDs["97025"]);
		//error_log("3>>>>>>".$geoBibIDs[$bibID]);
	$isnogeo = !@$geoBibIDs[$bibID];

	if((!$isnogeo) || @$timeObjects[$bibID]){
			//error_log(">>>>>".$bibID."=".$geoBibIDs[$bibID]."<<<<<<".(!@$geoBibIDs[$bibID])."<<<<<<");

		$geoRecords[$bibID] = $records[$bibID];
		if($isnogeo){
			//error_log(">>>>>PUSH EMPTY");
			//no geo data - only timedata
			array_push($geoObjects, array("bibID" => $bibID, "type" => "none")); //empty georeference
			$geoBibIDs[$bibID] = $bibID;
		}else{
			$cnt_geo++;
		}
		if (@$timeObjects[$bibID]) {
			list($start, $end) = $timeObjects[$bibID];
			$geoRecords[$bibID]["start"] = $start;
			if ($end) $geoRecords[$bibID]["end"] = $end;
			$cnt_time++;
		}
	}
	}

	//count($geoRecords)
	$mapobjects = array("records"=>$geoRecords,"geoObjects"=>$geoObjects,
						"cntWithGeo"=>$cnt_geo,
						"cntWithTime"=>$cnt_time,"layers"=>$layers);

	print json_format($mapobjects);
	exit();

	/*
	if (! HEURIST) HEURIST = {};
	if (! HEURIST.tmap) HEURIST.tmap = {};
	HEURIST.tmap.records = <?= json_format($geoRecords) ?>;
	HEURIST.tmap.geoObjects = <?= json_format($geoObjects) ?>;
	HEURIST.tmap.totalRecordCount = <?= count($geoRecords) ?>;
	*/

?>
