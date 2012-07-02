<?php

/**
 * Returns kml for given record id. It searches detail with type 221 or 551
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo - only one kml per record, perhaps need to return the combination of kml
require_once(dirname(__FILE__).'/../../common/config/initialise.php');
 * **/

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
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
	$res = mysql_query("select ulf_ID, ulf_FilePath, ulf_FileName from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = ".(defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:"0")." OR dtl_DetailTypeID = ".(defined('DT_KML_FILE')?DT_KML_FILE:"0").")");

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
		$squery = "select rec_ID, rec_URL, rec_Title, dtl_DetailTypeID, dtl_Value, if(dtl_Geo is null, null, asText(dtl_Geo)) as dtl_Geo ";
		$ourwhere = " and (dtl_RecID=rec_ID) and (dtl_Geo is not null ".(defined('DT_KML')?" or dtl_DetailTypeID=".DT_KML:"").")";

		//for kml
		$squery2 = "select  rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName ";
		$ourwhere2 = " and (dtl_RecID=rec_ID) and (dtl_DetailTypeID=".(defined('DT_KML_FILE')?DT_KML_FILE:"0").(defined('DT_FILE_RESOURCE')?" or (dtl_DetailTypeID = ".DT_FILE_RESOURCE." AND ulf_MimeExt='kml'))":")");
		$detTable = "recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID";

		$isSearchKml = defined('DT_KML_FILE') || defined('DT_FILE_RESOURCE');


		if($islist){

			if (array_key_exists('w',$_REQUEST)  && ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark'))
				$search_type = BOOKMARK;	// my bookmarks
			else
				$search_type = BOTH;	// all records

			$limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
			if (!$limit || $limit<1){
					$limit = 1000; //default limit in dispPreferences
			}

			$squery = prepareQuery($squery, $search_type, "recDetails", $ourwhere, $limit);
			if($isSearchKml){
				$squery2 = prepareQuery($squery2, $search_type, $detTable, $ourwhere2, $limit);
			}


		}else{
			$squery = $squery." from Records, recDetails where rec_ID=".$_REQUEST["id"].$ourwhere;
			$squery2 = $squery2." from Records,".$detTable." where rec_ID=".$_REQUEST["id"].$ourwhere2;
		}

/*****DEBUG****///error_log("1.>>>>".$squery);

		$res = mysql_query($squery);
		$wkt_reccount = mysql_num_rows($res);

/*****DEBUG****///error_log("2.>>>>".$wkt_reccount);

/*****DEBUG****///error_log(">>>>".$isSearchKml."2.>>>>".$squery2);
		if($isSearchKml){
			$res2 = mysql_query($squery2);
			$kml_reccount = mysql_num_rows($res2);
		}else{
			$kml_reccount = 0;
		}

		if ($wkt_reccount>0 || $kml_reccount>0)
		{

			if($wkt_reccount>0){
				while ($row = mysql_fetch_row($res)) {
					$kml = null;
					$dt = $row[3];
	/*****DEBUG****///error_log(">>>>>".$dt." == ".(defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0"));
					if($row[5]){
						$wkt = $row[5];
						$geom = geoPHP::load($wkt,'wkt');
						$kml = $geom->out('kml');
						if($kml){
							print '<Placemark>';
							print '<name>'.$row[2].'</name>';
							if($row[1]){
  								print '<description><![CDATA[ <a href="'.$row[1].'">link</a>]]></description>'; 										}
							print $kml;
							print '</Placemark>';
						}
					}else{
						/* @todo - tomorrow
						$kml = $row[1];
	/*****DEBUG****///error_log($kml);
/*						if(strpos($kml, "<?xml")>=0){
							$start = strpos($kml, "<Placemark>");
							$len = strpos($kml, strrchr($kml, "</Placemark>"))+strlen("</Placemark>")-$start;
/*****DEBUG****///error_log("START ".$start.",  len=".$len);
/*							$kml = substr($kml, $start, $len);
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

//
function prepareQuery($squery, $search_type, $detailsTable, $where, $limit)
{
			$squery = REQUEST_to_query($squery, $search_type);
			//remove order by
			$pos = strpos($squery," order by ");
			if($pos>0){
				$squery = substr($squery, 0, $pos);
			}

			//$squery = str_replace(" where ", ",".$detailsTable." where ", $squery);
			$squery = preg_replace('/ where /', $detailsTable." where ", $squery, 1);

			//add our where clause and limit
			$squery = $squery.$where." limit ".$limit;

			return $squery;
}
?>