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
header('Content-type: text/xml; charset=utf-8');

require_once(dirname(__FILE__).'/../../common/config/manageInstancesDeprecated.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
include_once('../../external/geoPHP/geoPHP.inc');

mysql_connection_select(DATABASE);

$islist = array_key_exists("q", $_REQUEST);

if(!$islist && array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!=""){
	//kml is stored in uploaded file
	$res = mysql_query("select ulf_ID from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = ".(defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:"0")." OR dtl_DetailTypeID = ".(defined('DT_KML_FILE')?DT_KML_FILE:"0").")");

	if (mysql_num_rows($res)) {
		$file_id = mysql_fetch_array($res);
		$file_id = $file_id[0];
		print file_get_contents(HEURIST_UPLOAD_DIR . "/" . $file_id);
		exit;
	}else{
		$res = mysql_query("select dtl_Value from recDetails where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = ".(defined('DT_KML')?DT_KML:"0"));
		if (mysql_num_rows($res)) {
			$kml = mysql_fetch_array($res);
			$kml = $kml[0];
			print $kml;
			exit;
		}
	}
}

if($islist || (array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")){

//kml is created from WKT stored in GEO field in database
		if($islist){
			$q = $_REQUEST["q"];
			$where = " in (".substr($q,4).")";
		}else{
			$where = "=".$_REQUEST["id"];
		}

		$squery = "select dtl_DetailTypeID, dtl_Value, if(dtl_Geo is null, null, asText(dtl_Geo)) as dtl_Geo from recDetails where dtl_RecID " . $where . " and (dtl_DetailTypeID = " . (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0")." or dtl_DetailTypeID = " . (defined('DT_KML')?DT_KML:"0")	.")";

		$res = mysql_query($squery);

		if (mysql_num_rows($res)) {

echo "<?xml version='1.0' encoding='UTF-8'?>\n";
echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
echo '<Document>';
echo '<name>Export from Heurist</name>';

			while ($row = mysql_fetch_row($res)) {
				$kml = null;
				$dt = $row[0];
//error_log(">>>>>".$dt." == ".(defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0"));
				if($dt == (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0")){
					$wkt = $row[2];
					$geom = geoPHP::load($wkt,'wkt');
					$kml = $geom->out('kml');
					if($kml){
						echo '<Placemark>';
						//echo '<name>'..'</name>';
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
			}

echo '</Document>';
echo '</kml>';

		}
}
?>
