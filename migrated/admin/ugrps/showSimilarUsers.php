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

if (! is_logged_in()) {
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}

?>
<html>
    <head>
        <title>Find similar users</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">

        <link rel="stylesheet" type="text/css" href= "../../common/css/global.css">
    </head>

    <body class="popup" width=600 height=500 style="font-size: 11px;">

        <form action="showSimilarUsers.php?db=<?=HEURIST_DBNAME?>" method="get">

        <?php

        if (!array_key_exists('user',$_REQUEST) || !$_REQUEST['user'] ) $_REQUEST['user'] = get_user_id();

        mysql_connection_select(DATABASE);

        if ($_REQUEST['user']) {
	        if (array_key_exists('kwd',$_REQUEST) && $_REQUEST['kwd']) {
		        $res = mysql_query('
                   select B.bkm_UGrpID,
                          concat(usr.'.USERS_FIRSTNAME_FIELD.'," ",usr.'.USERS_LASTNAME_FIELD.') as name,
		                  count(B.bkm_UGrpID) as freq
                     from usrRecTagLinks
                left join usrBookmarks A on A.bkm_RecID=rtl_RecID
                left join Records on rec_ID=A.bkm_recID
                left join usrBookmarks B on B.bkm_recID=rec_ID
                left join '.USERS_DATABASE.'.'.USERS_TABLE.' usr on usr.'.USERS_ID_FIELD.'=B.bkm_UGrpID
                    where rtl_TagID='.$_REQUEST['kwd'].'
                      and A.bkm_UGrpID='.$_REQUEST['user'].'
	                  and B.bkm_UGrpID!='.$_REQUEST['user'].'
	                  and usr.'.USERS_ID_FIELD.' is not null
	                  and usr.'.USERS_ACTIVE_FIELD.'="Y"
                 group by B.bkm_UGrpID
                 order by freq desc, name;');

	                }
	                else {
		                $res = mysql_query('
                   select B.bkm_UGrpID,
                          concat(usr.'.USERS_FIRSTNAME_FIELD.'," ",usr.'.USERS_LASTNAME_FIELD.') as name,
		                  count(B.bkm_UGrpID) as freq
                     from usrBookmarks A
                left join Records on rec_ID=A.bkm_recID
                left join usrBookmarks B on B.bkm_recID=rec_ID
                left join '.USERS_DATABASE.'.'.USERS_TABLE.' usr on usr.'.USERS_ID_FIELD.'=B.bkm_UGrpID
                    where A.bkm_UGrpID='.$_REQUEST['user'].'
	                  and B.bkm_UGrpID!='.$_REQUEST['user'].'
	                  and usr.'.USERS_ID_FIELD.' is not null
	                  and usr.'.USERS_ACTIVE_FIELD.'="Y"
                 group by B.bkm_UGrpID
                 order by freq desc, name;');
	                }
             ?>

            <h2>Find similar users</h2>
            <p>This page attempts to find other users of Heurist who have similar interests to you.
               It ranks other users based on how many records you have in common with them.
               Click on a user's name to view their profile.
               Click on "x records in common" to view the records you share with that user.</p>
            <!--'-->
            You are: &nbsp;&nbsp;<b><?= get_user_name(); ?></b>

            <br>
            <br>

            <script type="text/javascript">
                <!--
                function show_rows(rows) {
                  var elt = document.getElementById('user_table');
                  if (!elt) return;

                  var i = 0;
                  for (elt = elt.firstChild; elt; elt = elt.nextSibling) {
                    if (elt.nodeName != 'TR') continue;
	                i++;
	                if (i <= rows) elt.style.display = '';
	                if (i > rows)  elt.style.display = 'none';
                  }

                  elt = document.getElementById('show10');
                  if (rows == 10) elt.style.fontWeight = 'bold';
                  else elt.style.fontWeight = '';
                  elt = document.getElementById('show20');
                  if (rows == 20) elt.style.fontWeight = 'bold';
                  else elt.style.fontWeight = '';
                  elt = document.getElementById('show50');
                  if (rows == 50) elt.style.fontWeight = 'bold';
                  else elt.style.fontWeight = '';
                  elt = document.getElementById('show100');
                  if (rows == 100) elt.style.fontWeight = 'bold';
                  else elt.style.fontWeight = '';
                }
                -->
            </script>
        
            Users with similar interests to you: (show)
            <span id="show10" style="cursor: pointer; text-decoration: underline; font-weight: bold;" onclick="show_rows(10);">10</span>
            <span id="show20" style="cursor: pointer; text-decoration: underline;" onclick="show_rows(20);">20</span>
            <span id="show50" style="cursor: pointer; text-decoration: underline;" onclick="show_rows(50);">50</span>
            <span id="show100" style="cursor: pointer; text-decoration: underline;" onclick="show_rows(100);">100</span>

            <table class="reminder">
                <tbody id="user_table">
                <?php

	                $i = 0;
	                while ($row = mysql_fetch_assoc($res)) {
		                echo ' <tr'. (++$i > 10 ? ' style="display: none;"' : '') .'><td><a href="'.HEURIST_SITE_PATH.'admin/ugrps/viewUserDetails.php?db='.HEURIST_DBNAME.'&Id='.$row['bkm_UGrpID'].'" title="View user profile for '.$row['name'].'">'.$row['name']."</a>&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
		                echo '     <td><a target="_top" href="'.HEURIST_BASE_URL_V4.'?db='.HEURIST_DBNAME.'&w=bookmark&q=user:%22'.$row['name'].'%22" title="Search for records that you and '.$row['name'].' share"><b>'.$row['freq']."</b> records in common</a></td></tr>\n";
	                }
                }


                ?>
                </tbody>
            </table>
        </form>
    </body>
</html>

