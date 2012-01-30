<?php

/**
 * Returns kml for given record id. It searches detail with type 221 or 551
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo - only one kml per record, perhaps need to return the combination of kml
 * **/

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
include_once('../../external/geoPHP/geoPHP.inc');

mysql_connection_select(DATABASE);

$islist = array_key_exists("q", $_REQUEST);


//header('Content-type: text/xml; charset=utf-8');

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"export.kml\"");
header("Content-Type: text/kml");
//header("Content-Transfer-Encoding: binary");

/*
1. just print out content of uploaded kml files
*/
if(!$islist){

	if(array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")
	{
	//kml is stored in uploaded file
	$res = mysql_query("select ulf_ID, ulf_FilePath, ulf_FileName from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = ".(defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:"0")." OR dtl_DetailTypeID = ".(defined('DT_KML_FILE')?DT_KML_FILE:"0").")");

	if (mysql_num_rows($res)) {
			$file_data = mysql_fetch_array($res);

			if ($file_data[2]) {
				$filename = $file_data[1].$file_data[2]; // post 18/11/11 proper file path and name
			} else {
				$filename = HEURIST_UPLOAD_DIR."/".$file_data[0]; // pre 18/11/11 - bare numbers as names, just use file ID
			}

		print file_get_contents($filename);
	}else{
		$res = mysql_query("select dtl_Value from recDetails where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = ".(defined('DT_KML')?DT_KML:"0"));

		echo "<?xml version='1.0' encoding='UTF-8'?>\n";
		echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
		echo '<Document>';

		if (mysql_num_rows($res)) {
			$kml = mysql_fetch_array($res);
			$kml = $kml[0];
			print $kml;
		}

		echo '</Document>';
		echo '</kml>';
	}

	}
	exit;
}

echo "<?xml version='1.0' encoding='UTF-8'?>\n";
echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
echo '<Document>';
echo '<name>Export from Heurist</name>';

/*
2. create new KML output that contains placemarks created from WKT and links to heurist's uploaded kml files
*/
if($islist || (array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")){

//kml is created from WKT stored in GEO field in database
		if($islist){
			$q = $_REQUEST["q"];
			$where = " in (".substr($q,4).")";
		}else{
			$where = "=".$_REQUEST["id"];
		}

		$squery = "select rec_ID, rec_URL, rec_Title, dtl_DetailTypeID, dtl_Value, if(dtl_Geo is null, null, asText(dtl_Geo)) as dtl_Geo from Records, recDetails where dtl_RecID=rec_ID and  rec_ID " . $where . " and (dtl_DetailTypeID = " . (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0")." or dtl_DetailTypeID = " . (defined('DT_KML')?DT_KML:"0")	.")";

//error_log("1.>>>>".$squery);

		$res = mysql_query($squery);
		$wkt_reccount = mysql_num_rows($res);


		$squery = "select  rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName from Records, recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID=rec_ID and  rec_ID " . $where . " and ".
"((dtl_DetailTypeID = ".(defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:"0")." AND ulf_MimeExt='kml') OR dtl_DetailTypeID = ".(defined('DT_KML_FILE')?DT_KML_FILE:"0").")";

//error_log("2.>>>>".$squery);

		$res2 = mysql_query($squery);

		$kml_reccount = mysql_num_rows($res2);

		if ($wkt_reccount>0 || $kml_reccount>0)
		{


			while ($row = mysql_fetch_row($res)) {
				$kml = null;
				$dt = $row[3];
//error_log(">>>>>".$dt." == ".(defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0"));
				if($dt == (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0")){
					$wkt = $row[5];
					$geom = geoPHP::load($wkt,'wkt');
					$kml = $geom->out('kml');
					if($kml){
						echo '<Placemark>';
						echo '<name>'.$row[2].'</name>';
						if($row[1]){
  							echo '<description><![CDATA[ <a href="'.$row[1].'">link</a>]]></description>'; 										}
						echo $kml;
						echo '</Placemark>';
					}
				}else{
					/* @todo - tomorrow
					$kml = $row[1];
//error_log($kml);
					if(strpos($kml, "<?xml")>=0){
						$start = strpos($kml, "<Placemark>");
						$len = strpos($kml, strrchr($kml, "</Placemark>"))+strlen("</Placemark>")-$start;
error_log("START ".$start.",  len=".$len);
						$kml = substr($kml, $start, $len);
					}
					echo $kml;
					*/
				}
			}//while wkt records


			while ($file_data = mysql_fetch_row($res2)) {
				if ($file_data[3]) {

echo "<NetworkLink>";
echo "<name>".$file_data[2]."</name>";
echo "<Link id=\"".$file_data[0]."\">";
echo "<href>".HEURIST_BASE_URL."export/xml/kml.php?id=".$file_data[0]."</href>";
echo "</Link>";
echo "</NetworkLink>";
				}
			}//while kml records


		}
}
echo '</Document>';
echo '</kml>';
?>
