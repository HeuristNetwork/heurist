<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



	/*<!--
	* databaseSummary.php - request aggregation query for all records grouped by record type
	*
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/parseQueryToSQL.php');
	require_once(dirname(__FILE__).'/../common/php/getRecordInfoLibrary.php');

	mysql_connection_select(DATABASE);

	$searchType = BOTH;
	$args = array();
	$publicOnly = false;

	$query = REQUEST_to_query("select rec_RecTypeID, count(*) ", $searchType, $args, null, $publicOnly);

	$query = substr($query,0, strpos($query,"order by"));

	$query .= " group by rec_RecTypeID";

	// style="width:640px;height:480px;"
	$rtStructs = getAllRectypeStructures();
?>
<html>
	<head>

		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Database Summary</title>

		<link rel="stylesheet" type="text/css" href="../common/css/global.css">

		<script type="text/javascript">
				function onrowclick(rt_ID){
					window.open("search.html?w=all&ver=1&db=<?=HEURIST_DBNAME?>&q=t:"+rt_ID, "_blank");

				}
		</script>
		<style>
			.row {
				cursor:pointer;
			}
			.row:hover {
				background-color: #CCCCCC;
			}

			table.tbcount{
				border-width: 0 0 1px 1px;
				border-spacing: 0;
				border-collapse: collapse;
				border-style: solid;
			}
			.tbcount td, .tbcount th{
				margin: 0;
				padding: 4px;
				border-width: 1px 1px 0 0;
				border-style: solid;
			}
		</style>
	</head>
	<body class="popup">
		<table class="tbcount" cellpadding="4" cellspacing="1" width="100%">
		 	<tr>
				<th>ID</th><th>&nbsp;</th><th align='left' style="padding-left: 10px;">Record type</th><th>Count</th>
			</tr>
<?php
	/*****DEBUG****///print("QUERY:".$query);

	$res = mysql_query($query);

	while ($row = mysql_fetch_row($res)) {

		$rt_ID = $row[0];
		$rectypeTitle = $rtStructs['names'][$rt_ID];
		$rectypeImg = "style='background-image:url(".HEURIST_ICON_SITE_PATH.$rt_ID.".png)'";

		$img = "<img src='../common/images/16x16.gif' title='$rectypeTitle' ".$rectypeImg." class='rft' />";

		echo "<tr class='row' onclick='{onrowclick($rt_ID)}'><td align='center'>$rt_ID</td><td align='center'>$img</td><td>$rectypeTitle</td><td align='center'>".$row[1]."</td></tr>";

	}//end while
?>
		</table>
	</body>
</html>