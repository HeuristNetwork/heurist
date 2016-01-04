<?php

/**
* dh_header.php (Digital Harlem): Writes the header with statistical information about the database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__)."/../../php/System.php");

$statistics = "";
$system = new System();
// connect to given database
if(@$_REQUEST['db'] && $system->init(@$_REQUEST['db'])){

    // Building query
    $query = "SELECT rec_RecTypeID as id, count(*) as count FROM Records WHERE rec_RecTypeID in (14,10,12,15) GROUP BY id";

    // Put record types & counts in the table
    $res = $system->get_mysqli()->query($query);
    $stats = array();
    while($row = $res->fetch_assoc()) { // each loop is a complete table row
        $stats[$row["id"]]= $row["count"];
    }

    $statistics = '<p>Currently presenting <b title="Number of people">'.@$stats[10]
    .'</b> people, <b title="Number of events">'.@$stats[14]
    .'</b> events, <b title="Number of addresses">'.@$stats[12]
    .'</b> addresses and <b title="Number of documents">'.@$stats[15]
    .'</b> documentary sources related to Harlem, 1915-1930</p>';

}
?>

<div id="topstuff">

    <div  id="header">

        <div id="header_title">
            <div class="home">
                <p><b><a title="Home - Digital Harlem" href="#" onclick="{location.reload()}">Home</a></b>&nbsp;<b><a title="Focus on Map" href="#contentstart">Center Map</a></b>&nbsp;<!--<a href="javascript:void(0)" onClick="toggleFullScreen()">X</a>--></p>
            </div>
        </div>

        <div class="intro">
            <?=$statistics?>
        </div>

        <div class="spacer"></div>

    </div>

    <div id="searchbar">


        <div class="menubutton"><a class="menuitem" href="javascript:void(0)" onClick="{location.reload(true);}">HOME</a></div>
        <?php

        // Building query
        $query = "SELECT rec_ID id, rec_Title as title, d1.dtl_Value as content, d2.dtl_Value as ord"
        ." FROM Records left join recDetails d2 on rec_ID=d2.dtl_recID and d2.dtl_DetailTypeID=94, recDetails d1 "
        ." WHERE rec_ID=d1.dtl_recID and rec_RecTypeID=25 and d1.dtl_DetailTypeID=4 "
        ." ORDER BY d2.dtl_Value";

        // Put record types & counts in the table
        $res = $system->get_mysqli()->query($query);
        $stats = array();
        while($row = $res->fetch_assoc()) { // each loop is a complete table row
            if($row["ord"]>0){
                ?>
                <div class="menubutton"><a class="menuitem" href="javascript:void(0)"
                    onClick="{ top.HEURIST4.msg.showMsgDlg('#webcontent<?=$row["id"]?>', null,'<?=$row["title"]?>');}"><?=$row["title"]?></a></div>
                <?php
            }else{
                ?>
                <script>
                    top.HEURIST4.msg.showMsgDlg('#webcontent<?=$row["id"]?>', null,'<?=$row["title"]?>');
                </script>
                <?php
            }
            ?>
            <div style="display:none;" id="webcontent<?=$row["id"]?>">
                <?php
                print $row["content"];
                ?>
            </div>

            <?php
        }
        ?>
        <div class="menubutton"><a class="menuitem" href="javascript:void(0)" onClick="{ window.open('http://digitalharlemblog.wordpress.com/', 'DHBlog'); }">BLOG</a></div>

    </div>

</div>  <!-- topstuff -->

