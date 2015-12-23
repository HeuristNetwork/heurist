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
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export
* @todo        Update to use concept ids
*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

require_once(dirname(__FILE__).'/../../external/geoPHP/geoPHP.inc');

mysql_connection_select(DATABASE);

$isAtom = (array_key_exists("feed", $_REQUEST) && $_REQUEST['feed'] == "atom");

// TODO: Remove, enable or explain
//header('Content-type: text/xml; charset=utf-8');
//header("Cache-Control: public");
//header("Content-Description: File Transfer");
//header("Content-Disposition: attachment; filename=\"heuristfeed.xml\"");

header("Content-Type: application/".($isAtom?"atom":"rss")."+xml");

$explanation="This feed returns the results of a HEURIST search. The search URL specifies the search parameters and the search results are built live from the HEURIST database. If you are not logged in you may see fewer records than you expect, as only records marked as 'Publicly Visible' will be rendered in the feed";

print "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
if($isAtom){
    ?>
    <feed xmlns="http://www.w3.org/2005/Atom" xmlns:georss="http://www.georss.org/georss" xmlns:media="http://search.yahoo.com/mrss/">
    <title>HEURIST Search results</title>
    <link href="<?=htmlspecialchars(HEURIST_BASE_URL)?>"/>
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
        <link href="<?=htmlspecialchars(HEURIST_BASE_URL)?>?<?=htmlspecialchars($_SERVER['QUERY_STRING'])?>"/>
        <id><?=htmlspecialchars(HEURIST_BASE_URL."?db=".HEURIST_DBNAME)?></id>
        <published><?=date("r")?></published>
        <summary>HEURIST home page (search)</summary>
    </entry>
    <?php
}else{
    //
    // TODO: Remove, enable or explain
    //	<atom:link href="=urlencode(HEURIST_CURRENT_URL)" rel="self" type="application/rss+xml"/>
    ?>
    <rss version="2.0" xmlns:georss="http://www.georss.org/georss" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
    <title>HEURIST Search results</title>
    <link><?=htmlspecialchars(HEURIST_BASE_URL)?></link>
    <description><?=$explanation?></description>
    <language>en-gb</language>
    <pubDate><?=date("r")?></pubDate>
    <copyright>Copyright: (C) University of Sydney Faculty of Arts and Social Sciences</copyright>
    <generator>HEURIST search</generator>
    <managingEditor>info@heuristscholar.org (Information at Heurist)</managingEditor>
    <atom:link href="<?=htmlspecialchars(HEURIST_CURRENT_URL)?>" rel="self" type="application/rss+xml"/>
    <item>
        <title>HEURIST main page</title>
        <description>HEURIST main page (search)</description>
        <pubDate><?=date("r")?></pubDate>
        <link><?=htmlspecialchars(HEURIST_BASE_URL."?".$_SERVER['QUERY_STRING'])?></link>
        <guid isPermaLink="false"><?=htmlspecialchars(HEURIST_BASE_URL."?db=".HEURIST_DBNAME)?></guid>
    </item>
    <?php
}

//   0       1         2        3                4                5            6                                                        7
$squery = "select distinct rec_ID, rec_URL, rec_Title, rec_ScratchPad, rec_RecTypeID, rec_Modified, rec_Added, ".
"b.dtl_Value, c.dtl_Value ";
$joinTable = " left join recDetails b on (b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=".(defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:"0").
") left join recDetails c on (c.dtl_RecID=rec_ID and c.dtl_DetailTypeID=".(defined('DT_CREATOR')?DT_CREATOR:"0").") ";



if(true || @$_REQUEST['rules']){ //search with h4 search engine

    $h4way = true;

    //$_REQUEST['idonly'] = 1;
    //$_REQUEST['vo'] = 'h3';
    //$result = recordSearch($system, $_REQUEST, false, false, $PUBONLY);
    $url = HEURIST_BASE_URL."../../../php/api/record_search.php?".$_SERVER["QUERY_STRING"]."&detail=ids&vo=h3";  //call h4
    $reclist = loadRemoteURLContent($url);
    $reclist = json_decode($reclist, true);

    if (array_key_exists('error', $reclist)) {
        print "Error: ".$reclist['error'];
        return;
    }

    $reccount = $reclist['resultCount'];
    $reclist = explode(",", $reclist['recIDs']);


}else{

    $h4way = false;

    if (array_key_exists('w',$_REQUEST)  && ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark'))
        $search_type = BOOKMARK;    // my bookmarks
    else
        $search_type = BOTH;    // all records

    $limit = intval(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['report-output-limit']);
    if (!$limit || $limit<1){
        $limit = 1000; //default limit in dispPreferences
    }

    $squery_res = prepareQuery(null, $squery, $search_type, $joinTable, "", null, $limit);

    $reclist = mysql_query($squery_res);
    $reccount = mysql_num_rows($reclist);
}

$uniq_id = 1;
$idx = 0;

if ($reccount>0)
{

    while (($h4way && $idx<$reccount) || (!$h4way && ($row = mysql_fetch_row($reclist)))) {

        if($h4way){
            $recID = $reclist[$idx];
            $idx++;
            $squery_res = $squery." from Records ".$joinTable." where rec_ID=".$recID;
            $res = mysql_query($squery_res);
            if($res){
                $row = mysql_fetch_row($res);
                if(!$row) continue;
            }
        }

        //find rectitle for creator
        if($row[8]){
            $creator = mysql__select_array("Records","rec_Title", "rec_ID=".$row[8]);
            $creator = count($creator)>0?$creator[0]:null;
        }else{
            $creator = null;
        }

        // grab the user tags, as a single comma-delimited string
        $kwds = mysql__select_array("usrRecTagLinks left join usrTags on tag_ID=rtl_TagID", "tag_Text",
            "rtl_RecID=".$row[0]." and tag_UGrpID=".get_user_id() . " order by rtl_Order, rtl_ID");
        $tagString = join(",", $kwds);

        //get url for thumbnail
        $thubURL = getThumbnailURL($row[0]);


        $url = 	($row[1]) ? htmlspecialchars($row[1]) : HEURIST_BASE_URL."records/view/viewRecord.php?db=".HEURIST_DBNAME."&amp;recID=".$row[0];
        $uid = HEURIST_BASE_URL."records/view/viewRecord.php?db=".HEURIST_DBNAME."&amp;recID=".$row[0];
        $uniq_id++;

        $date_published = date("r", strtotime(($row[5]==null)? $row[6] : $row[5]));

        $description = ($row[7])? "<![CDATA[".$row[7]."]]>":"";

        if($isAtom){
            ?>
            <entry>
            <title><?=htmlspecialchars($row[2])?></title>
            <summary><?=$description?></summary>
            <category>type/<?=$row[4]?></category>
            <published><?=$date_published?></published>
            <id><?=$uid?></id>
            <link href="<?=$url?>"/>
            <?php
            if($creator!=null){
                // TODO: this is email - not creator's record title. ? this has been fixed, see line 172
                print "<author><name><![CDATA[".$creator."]]></name></author>";
            }
        }else{
            ?>
            <item>
            <title><?=htmlspecialchars($row[2])?></title>
            <description><?=$description?></description>
            <category>type/<?=$row[4]?></category>
            <pubDate><?=$date_published?></pubDate>
            <guid isPermaLink="false"><?=$uid?></guid>
            <link><?=$url?></link>
            <?php
            if($creator!=null){
                // TODO: this is email - not creator's record title ? this has been fixed, see line 172
                print "<author><name><![CDATA[".$creator."]]></name></author>";
            }
        }

        if($tagString){
            print "\n	<media:keywords>".$tagString."</media:keywords>";
        }
        if($thubURL){
            //width=\"120\" height=\"80\"
            print "\n	<media:thumbnail url=\"".htmlspecialchars($thubURL)."\"/>";
        }

        //geo rss
        $geos = mysql__select_array("recDetails", "if(a.dtl_Geo is null, null, asText(a.dtl_Geo)) as dtl_Geo",
            "a.dtl_RecID=".$row[0]." and a.dtl_Geo is not null");

        if(count($geos)>0){
            $wkt = $geos[0];
            $geom = geoPHP::load($wkt,'wkt');
            $gml = $geom->out('georss');
            if($gml){
                $gml = "<georss:".substr($gml,1);
                $gml = str_replace("</","</georss:",$gml);
                print "\n	".$gml;
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



?>