<?php

/**
 * Returns kml for given record id. It searches detail with type 221 or 551
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 */

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
include_once('../../external/geoPHP/geoPHP.inc');

mysql_connection_select(DATABASE);

$isAtom = (array_key_exists("feed", $_REQUEST) && $_REQUEST['feed'] == "atom");

//header('Content-type: text/xml; charset=utf-8');

//header("Cache-Control: public");
//header("Content-Description: File Transfer");
//header("Content-Disposition: attachment; filename=\"heuristfeed.xml\"");
header("Content-Type: application/".($isAtom?"atom":"rss")."+xml");

$explanation="This feed returns the results of a HEURIST search. The search URL specifies the search parameters and the search results are built live from the HEURIST database. If you are not logged in you may see fewer records than you expect, as only records marked as 'Publicly Visible' will be rendered in the feed";

print "<?xml version='1.0' encoding='UTF-8'?>\n";
if($isAtom){
?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:georss="http://www.georss.org/georss">
	<title>HEURIST Search results</title>
	<link href="<?=HEURIST_URL_BASE?>"/>
	<subtitle><?=$explanation?></subtitle>
	<updated><?=date("r")?></updated>
	<copyright>Copyright: (C) University of Sydney Digital Innovation Unit</copyright>
	<generator>HEURIST search</generator>
	<author>
		<name>Information at Heurist</name>
		<email>info@heuristscholar.org</email>
	</author>
	<entry>
		<title>HEURIST home</title>
		<link href="<?=HEURIST_URL_BASE?>search/search.html?<?=htmlspecialchars($_SERVER['QUERY_STRING'])?>"/>
		<id><?=HEURIST_URL_BASE?>search/search.html?db=<?=HEURIST_DBNAME?></id>
		<published><?=date("r")?></published>
		<summary>HEURIST home page (search)</summary>
	</entry>
<?php
}else{
?>
<rss version="2.0" xmlns:georss="http://www.georss.org/georss">
<channel>
	<title>HEURIST Search results</title>
	<link><?=HEURIST_URL_BASE?></link>
	<description><?=$explanation?></description>
	<language>en-gb</language>
	<pubDate><?=date("r")?></pubDate>
	<copyright>Copyright: (C) University of Sydney Digital Innovation Unit</copyright>
	<generator>HEURIST search</generator>
	<managingEditor>info@heuristscholar.org (Information at Heurist)</managingEditor>
<item>
	<title>HEURIST home</title>
	<description>HEURIST home page (search)</description>
	<pubDate><?=date("r")?></pubDate>
	<link><?=HEURIST_URL_BASE?>search/search.html?<?=htmlspecialchars($_SERVER['QUERY_STRING'])?></link>
	<guid isPermaLink="false"><?=HEURIST_URL_BASE?>search/search.html?db=<?=HEURIST_DBNAME?></guid>
</item>
<?php
}

								//   0       1         2		3				4				5			6
		$squery = "select distinct rec_ID, rec_URL, rec_Title, rec_ScratchPad, rec_RecTypeID, rec_Modified, if(dtl_Geo is null, null, asText(dtl_Geo)) as dtl_Geo ";
		$joinTable = " left join recDetails  on (dtl_RecID=rec_ID and dtl_Geo is not null) ";

		if (array_key_exists('w',$_REQUEST)  && ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark'))
			$search_type = BOOKMARK;	// my bookmarks
		else
			$search_type = BOTH;	// all records

		$limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
		if (!$limit || $limit<1){
				$limit = 1000; //default limit in dispPreferences
		}

		$squery = prepareQuery($squery, $search_type, $joinTable, "", $limit);

/*****DEBUG****///error_log("1.>>>>".$squery);

		$res = mysql_query($squery);
		$reccount = mysql_num_rows($res);

		if ($reccount>0)
		{
//strtotime(//date("r", $row[5])

				while ($row = mysql_fetch_row($res)) {

	$url = 	($row[1]) ? htmlspecialchars($row[1]) : HEURIST_URL_BASE."records/view/viewRecord.php?db=".HEURIST_DBNAME."&amp;recID=".$row[0];
	$uid = HEURIST_URL_BASE."search/search.html?db=".HEURIST_DBNAME."&amp;q=ids:".$row[0];

if($isAtom){
?>
<entry>
	<title><?=htmlspecialchars($row[2])?></title>
	<summary><![CDATA[<?=$row[3]?>]]></summary>
	<category>type/<?=$row[4]?></category>
	<published><?=$row[5]?></published>
	<id><?=$uid?></id>
	<link href="<?=$url?>"/>
<?php
}else{
?>
<item>
	<title><?=htmlspecialchars($row[2])?></title>
	<description><![CDATA[<?=$row[3]?>]]></description>
	<category>type/<?=$row[4]?></category>
	<pubDate><?=$row[5]?></pubDate>
	<guid isPermaLink="false"><?=$uid?></guid>
	<link><?=$url?></link>
<?php
}

	/*****DEBUG****///error_log(">>>>>".$dt." == ".(defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"0"));

					if($row[6]){
						$wkt = $row[6];
						$geom = geoPHP::load($wkt,'wkt');
						$gml = $geom->out('georss');
						if($gml){
							print $gml;
						}
					}

print ($isAtom)?'</entry>':'</item>';


				}//while wkt records


		}

if($isAtom){
print '</feed>';
}else{
print '</channel>';
print '</rss>';
}

// the same in kml.php
function prepareQuery($squery, $search_type, $joinTable, $where, $limit)
{
			$squery = REQUEST_to_query($squery, $search_type, '', null, false); //public only
			//remove order by
			$pos = strpos($squery," order by ");
			if($pos>0){
				$squery = substr($squery, 0, $pos);
			}

			//$squery = str_replace(" where ", $joinTable." where ", $squery);
			$squery = preg_replace('/ where /', $joinTable." where ", $squery, 1);

			//add our where clause and limit
			$squery = $squery.$where." limit ".$limit;

			return $squery;
}
?>