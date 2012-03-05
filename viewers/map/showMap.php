<?php

	/**
	* showMap.php
	*
	* search records and fills the structure to display on map
	*
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
	require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

	require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');

	require_once("encodePolyline.php");

	header("Content-type: text/javascript");

	$imagelayerRT = (defined('RT_IMAGE_LAYER')?RT_IMAGE_LAYER:0);

	mysql_connection_db_select(DATABASE);

	if (array_key_exists('layers', $_REQUEST)) { //special mode - load ALL image layers and kml records only - for general drop down list on map

		$_REQUEST['ver'] = "1";
		$_REQUEST['q'] = "type:".$imagelayerRT;
		$search_type = BOTH;
	}else{

		if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
		construct_legacy_search();      // migration path

		if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
			$search_type = BOOKMARK;	// my bookmarks
		else
			$search_type = BOTH;	// all records

	}

	if( !array_key_exists("limit", $_REQUEST) ){ //not defined
		$_REQUEST["limit"] = "500"; //force offset and limit (max 500)
	}

	// find all matching records
	$cols = "rec_ID as bibID, rec_RecTypeID as rectype, rec_Title as title, rec_URL as URL";
	$query = REQUEST_to_query("select $cols ", $search_type);

//error_log(">>>>>>>>>>>>>>>>>>>>>>>".$search_type."<<<<<<".$query);
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

	function getKmlFilePath($fileID){
		if ($fileID) {/* search for KML file */
			$fres = mysql_query(
			"select ulf_ObfuscatedFileID, ulf_MimeExt from recUploadedFiles where ulf_ID = ".intval($fileID));

			if ($fres) {
				$row2 = mysql_fetch_row($fres);
				$ext = strtolower( $row2[1] );
				if($row2[0] && ($ext=="kml")){
					return $row2[0];
				}
			}
		}
		return null;
	}

	foreach ($bibIDs as $bibID) {
		//0 DT_SHORT_SUMMARY
		//1 DT_EXTENDED_DESCRIPTION
		//2 - record URL
		//3 DT_ASSOCIATED_FILE
		//REMOVED 4 DT_LOGO_IMAGE
		//REMOVED 5 DT_THUMBNAIL
		//REMOVED 6 DT_IMAGES
		//4 7 DT_MAP_IMAGE_LAYER_REFERENCE
		//5 8 DT_KML
		//6 9 DT_KML_FILE
		//7 10 record type

//d.dtl_UploadedFileID,e.dtl_UploadedFileID,f.dtl_UploadedFileID,

		$res = mysql_query("select a.dtl_Value, b.dtl_Value, rec_URL, c.dtl_UploadedFileID, g.dtl_Value, h.dtl_Value, i.dtl_UploadedFileID, rec_RecTypeID
		from Records
		left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:"0").
		" left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(defined('DT_EXTENDED_DESCRIPTION')?DT_EXTENDED_DESCRIPTION:"0").
		" left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:"0").
//		" left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(defined('DT_LOGO_IMAGE')?DT_LOGO_IMAGE:"0").
//		" left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(defined('DT_THUMBNAIL')?DT_THUMBNAIL:"0").
//		" left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(defined('DT_IMAGES')?DT_IMAGES:"0").
		" left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(defined('DT_MAP_IMAGE_LAYER_REFERENCE')?DT_MAP_IMAGE_LAYER_REFERENCE:"0").
		" left join recDetails h on h.dtl_RecID=rec_ID and h.dtl_DetailTypeID=".(defined('DT_KML')?DT_KML:"0").
		" left join recDetails i on i.dtl_RecID=rec_ID and i.dtl_DetailTypeID=".(defined('DT_KML_FILE')?DT_KML_FILE:"0").
		" where rec_ID=$bibID");
		$row = mysql_fetch_row($res);
		$records[$bibID]["recID"] = $bibID;
		$records[$bibID]["rectype"] = $row[7];
		$records[$bibID]["description"] = ( $row[0] ?$row[0] :($row[1]?$row[1]:"") );
		$records[$bibID]["url"] = $row[2]; //($row[2] ? "'".$row[2]."' target='_blank'"  :"'javascript:void(0);'");
		//'javascript:{this.href="'+$row[2]+'"}' : 'javascript:{return false;}');//javascript:void(0)}');
		$records[$bibID]["icon_url"] = HEURIST_ICON_URL_BASE . $row[7] . ".png";;

		$thumb_url = getThumbnailURL($bibID); //function from uploadFile.php
		if($thumb_url==""){  //if thumb not defined - use rectype default thumb
			$thumb_url = HEURIST_ICON_URL_BASE.	"thumb/th_" . $row[7] . ".png";
		}

		$records[$bibID]["thumb_url"] = $thumb_url;

		if($row[4] && is_numeric($row[4]) && ! in_array($row[4],$imageLayers)){ //DT_MAP_IMAGE_LAYER_REFERENCE
			array_push($imageLayers, $row[4]);
		}
		$kml_path =  getKmlFilePath($row[3]); //DT_ASSOCIATED_FILE
error_log(">>>>>>".$row[3]."=".$kml_path);
// removed by SAW as DT_KML_FILE changed from a file base type to blocktext
//		if($kml_path==null){
//			$kml_path =  getKmlFilePath($row[6]); //DT_KML_FILE
//			}
		if($kml_path!=null){
			array_push($geoObjects, array("bibID" => $bibID, "type" => "kmlfile", "fileid" => $kml_path));
			$geoBibIDs[$bibID] = $bibID;
		}else if ($row[5]) { //DT_KML dtl_value contains KML		saw TODO: modify to check that text is valid KML.
			array_push($geoObjects, array("bibID" => $bibID, "type" => "kml", "recid" => $bibID));
			$geoBibIDs[$bibID] = $bibID;
		}
	}//for

	if($bibIDs && count($bibIDs)>0)
	{
	// Find the records that actually have any geographic data to plot
	$res = mysql_query("select dtl_RecID, dtl_Value, astext(dtl_Geo), astext(envelope(dtl_Geo)) from recDetails where dtl_Geo is not null and dtl_RecID in (" . join(",", $bibIDs) . ")");
if(mysql_error()) {
	error_log("ERROR in ShowMap=".mysql_error());
}
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
	}//$bibIDs!=null

	// OLD WAY TO STORE GEO DATA - directly in dtl_value as dettypes: 210(long) and 211(lat)
/* removed by SAW  as 211 is an old magic number not brought forward.
	$res = mysql_query("select LAT.dtl_RecID, LNG.dtl_Value, LAT.dtl_Value from recDetails LAT, recDetails LNG where LAT.dtl_DetailTypeID=211 and LNG.dtl_DetailTypeID=210 and LAT.dtl_RecID=LNG.dtl_RecID and LNG.dtl_RecID in (" . join(",", $bibIDs) . ")");
	if($res){
		while ($val = mysql_fetch_row($res)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($val[1]), "y" => floatval($val[2]))));
			$geoBibIDs[$val[0]] = $val[0];
		}
	}
*/
	// some records may contain reference to map image layer record (dettype 588),
	// but we may have such records in search result as well rectype=$imagelayerRT
	if($bibIDs && count($bibIDs)>0){
		$squery = "select rec_ID  from Records
							 where rec_ID in (" . join(",", $bibIDs) . ") and rec_RecTypeID=$imagelayerRT";
//error_log($squery);
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
		" e.dtl_Value as min_zoom, f.dtl_Value as max_zoom, g.dtl_Value as copyright".
		" from Records".
		" left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=".(defined('DT_TITLE_SHORT')?DT_TITLE_SHORT:"0").
		" left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(defined('DT_MAP_IMAGE_LAYER_SCHEMA')?DT_MAP_IMAGE_LAYER_SCHEMA:"0").
		" left join recDetails c on c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(defined('DT_SERVICE_URL')?DT_SERVICE_URL:"0").
		" left join recDetails d on d.dtl_RecID=rec_ID and d.dtl_DetailTypeID=".(defined('DT_MIME_TYPE')?DT_MIME_TYPE:"0").
		" left join recDetails e on e.dtl_RecID=rec_ID and e.dtl_DetailTypeID=".(defined('DT_MINMUM_ZOOM_LEVEL')?DT_MINMUM_ZOOM_LEVEL:"0").
		" left join recDetails f on f.dtl_RecID=rec_ID and f.dtl_DetailTypeID=".(defined('DT_MAXIMUM_ZOOM_LEVEL')?DT_MAXIMUM_ZOOM_LEVEL:"0").
		" left join recDetails g on g.dtl_RecID=rec_ID and g.dtl_DetailTypeID=".(defined('DT_ALTERNATE_NAME')?DT_ALTERNATE_NAME:"0").    //change to DT_COPYRIGHT (#311 in sandpit5)
		" where rec_ID in (" . join(",", $imageLayers) . ")";
//error_log($squery);
		$res = mysql_query($squery);
//error_log(mysql_error());
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

				$manifest = simplexml_load_file($manifest_file);
				if($manifest==null || is_string($manifest)){ //manifest not found

					$rec['error'] = "Cant load manifest file image layer. ".$manifest_file;

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
							$sw = $rect[0];
							$ne = $rect[1];
							$rec['extent'] = $sw['lon'].','.$sw['lat'].','.$ne['lon'].','.$ne['lat'];
						}
					}

					if(!array_key_exists('extent',$rec)){
						$rec['error'] = "Can't find bounds parameters in manifest file";
					}
				}
			}

			array_push($layers, $rec);
		}


	}//count($imageLayers)>0

	// Find time extents -- must have at least a start time (end time is optional)

	// check for specific details first
	//saw TODO; modify this for handle durations with a start or end date
	$timeObjects = array();
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
//error_log("XXXX>>>>>> ". $val[0]."  ".$val[1]."  ".$val[2] );
			}
		}
	}


//	$timeObjects = array();
	//"
	//select d.dtl_RecID, min(d.dtl_Value), max(d.dtl_Value), min(y.dtl_Value), max(y.dtl_Value)
	//from recDetails b, recDetails y
	//"
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

	//sort($geoBibIDs);

	$geoRecords = array();
	$cnt_geo = 0;
	$cnt_time = 0;

	//foreach ($geoBibIDs as $bibID) {
	foreach ($bibIDs as $bibID) { //loop for all records
		//	$bibID = ""+$bibID;

		//error_log("2>>>>>>".$geoBibIDs["97025"]);
		//error_log("3>>>>>>".$bibID."    ".$geoBibIDs[$bibID]."   time=".array_key_exists($bibID, $timeObjects) );

		$isNotGeoLoc = !@$geoBibIDs[$bibID];

		if((!$isNotGeoLoc) || array_key_exists($bibID, $timeObjects) ){
			//error_log(">>>>>".$bibID."=".$geoBibIDs[$bibID]."<<<<<<".(!@$geoBibIDs[$bibID])."<<<<<<");

			$geoRecords[$bibID] = $records[$bibID];
			if($isNotGeoLoc){
				//error_log(">>>>>PUSH EMPTY");
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
						return findXMLelement($xml, $ind, $atree);
					}

				}
			}

			return null;
	}

?>
