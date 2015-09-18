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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (!is_logged_in()) {
	header('Location: '.HEURIST_BASE_URL.'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

mysql_connection_overwrite(DATABASE);

$refurl = HEURIST_BASE_URL."admin/ugrps/showSimilarUsers.php?db=".HEURIST_DBNAME;

$sortby = (array_key_exists('sort',$_REQUEST) && $_REQUEST['sort'] == 'alpha' ? 'alpha' : 'freq');
$ugr_ID = $_REQUEST['Id'];

$table = USERS_DATABASE.'.'.USERS_TABLE;
$fields = "ugr_FirstName, ugr_LastName, ugr_Department, ugr_Organisation, ugr_eMail";
$condition = USERS_ID_FIELD.'='.$ugr_ID;

$res = mysql_query("SELECT $fields FROM $table WHERE $condition");
if (!$res) {
	print "<html><body>user not found</body></html>";
	return;
}
$row = mysql_fetch_array($res);

$ugr_FullName = $row[0]." ".$row[1];
$ugr_Department = $row[2];
$ugr_Organisation = $row[3];
$ugr_eMail = $row[4];

$tags = '';

$res = mysql_query('select tag_Text,count(rtl_ID) as bkmks
                      from usrRecTagLinks
                 left join usrTags on rtl_TagID=tag_ID
                     where tag_UGrpID='.$_REQUEST['Id'].'
                  group by tag_Text
                  order by '.
(array_key_exists('sort',$_REQUEST) && $_REQUEST['sort'] == 'alpha' ? 'tag_Text, bkmks desc' : 'bkmks desc, tag_Text'));

$tags .= '<span id="top10">'."\n";
$i = 0;
while ($row = mysql_fetch_assoc($res)) {
	if ($i == 10)
		$tags .= "</span>\n".'<span id="top20" style="display: none;">'."\n";
	if ($i == 20)
		$tags .= "</span>\n".'<span id="top50" style="display: none;">'."\n";
	if ($i == 50)
		$tags .= "</span>\n".'<span id="top100" style="display: none;">'."\n";
	$i++;
	$tags .= '<a target="_top" href="'.HEURIST_BASE_URL_V4.'?w=all&q=tag:%22'.urlencode($row['tag_Text']).'%22+user:'.$_REQUEST['Id'].'" title="Search for '.$ugr_FullName.'\'s references with the tag \''.$row['tag_Text'].'\'" style="white-space:nowrap;">'.$row['tag_Text'].' ('.$row['bkmks'].")</a>&nbsp&nbsp\n";
}
$tags .= "</span>\n";
?>

<html>
    <head>
      <title>HEURIST - User Profile</title>

      <meta http-equiv="content-type" content="text/html; charset=utf-8">
      <link rel="icon" href="../../favicon.ico" type="image/x-icon">
      <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">
      <link rel="stylesheet" type="text/css" href= "../../common/css/global.css">
    </head>

    <body>
        <table border="0" cellpadding="0" cellspacing="4" width="100%" style="background-color: black; color: white;">
            <tr>
                <td nowrap><b>User Profile</b></td>
                <td width="100%"></td>
            </tr>
        </table>

        <div style="padding: 10px;">

        <div style="float: right;"><a href="<?=$refurl?>">back</a></div>

        <table border="0" cellpadding="2" cellspacing="0" style="border-collapse: collapse">
            <tr>
                <td><b><?=$ugr_FullName?></b></td>
            </tr>
            
             <?php
             if ($ugr_Department){
  	            print "<tr><td>".$ugr_Department."</td></tr>";
             }
             if ($ugr_Organisation){
  	            print "<tr><td>".$ugr_Organisation."</td></tr>";
             }
             ?>
             <tr>
                <td><a href="mailto:[<?=$ugr_eMail?>"><?=$ugr_eMail?></a></td>
             </tr>
        </table>

        <hr color="#C0C0C0" size="1">

        <b>Tags</b><br>

        <script type="text/javascript">
            <!--
            function show_tags(n) {
              top10_elt = document.getElementById('top10');
              top20_elt = document.getElementById('top20');
              top50_elt = document.getElementById('top50');
              top100_elt = document.getElementById('top100');

              if (n == 10) {
                if (top100_elt) top100_elt.style.display = 'none';
                if (top50_elt) top50_elt.style.display = 'none';
                if (top20_elt) top20_elt.style.display = 'none';
              }
              else if (n == 20) {
                if (top100_elt) top100_elt.style.display = 'none';
                if (top50_elt) top50_elt.style.display = 'none';
                if (top20_elt) top20_elt.style.display = '';
              }
              else if (n == 50) {
                if (top100_elt) top100_elt.style.display = 'none';
                if (top20_elt) top20_elt.style.display = '';
                if (top50_elt) top50_elt.style.display = '';
              }
              else if (n == 100) {
                if (top20_elt) top20_elt.style.display = '';
                if (top50_elt) top50_elt.style.display = '';
                if (top100_elt) top100_elt.style.display = '';
              }

              elt = document.getElementById('show10');
              if (n == 10) elt.style.fontWeight = 'bold';
              else elt.style.fontWeight = '';
              elt = document.getElementById('show20');
              if (n == 20) elt.style.fontWeight = 'bold';
              else elt.style.fontWeight = '';
              elt = document.getElementById('show50');
              if (n == 50) elt.style.fontWeight = 'bold';
              else elt.style.fontWeight = '';
              elt = document.getElementById('show100');
              if (n == 100) elt.style.fontWeight = 'bold';
              else elt.style.fontWeight = '';
            }
            -->
        </script>
        
        sort:
        <a href="viewUserDetails.php?Id=<?=$ugr_ID?>&amp;sort=freq&amp;db=<?=HEURIST_DBNAME?>" style="font-weight:<?=$sortby=='freq'?'bold':'normal'?>;">freq</a>
        <a href="viewUserDetails.php?Id=<?=$ugr_ID?>&amp;sort=alpha&amp;db=<?=HEURIST_DBNAME?>" style="font-weight: <?=$sortby=='alpha'?'bold':'normal'?>;">alpha</a>

        &nbsp;&nbsp;

        show:
        <span id="show10" style="cursor: pointer; text-decoration: underline; font-weight: bold;" onclick="show_tags(10);">10</span>
        <span id="show20" style="cursor: pointer; text-decoration: underline;" onclick="show_tags(20);">20</span>
        <span id="show50" style="cursor: pointer; text-decoration: underline;" onclick="show_tags(50);">50</span>
        <span id="show100" style="cursor: pointer; text-decoration: underline;" onclick="show_tags(100);">all</span>

        <br>
        <br>

        <?=$tags?>

        </div>

    </body>
</html>
