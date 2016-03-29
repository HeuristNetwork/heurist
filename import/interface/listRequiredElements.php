<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



	require_once(dirname(__FILE__).'/../biblio/importRefer.php');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


	mysql_connection_select(DATABASE);

	$bdt = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_Name', '1');
	$rft = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', '1');
	$res = mysql_query('select * from defRecTypes left join defRecStructure on rst_RecTypeID=rty_ID
								left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
								order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name');
	$bdr = array();
	while ($row = mysql_fetch_assoc($res)) {
	if (! $bdr[$row['rty_ID']])
		$bdr[$row['rty_ID']] = array();
	$bdr[$row['rty_ID']][$row['rst_DetailTypeID']] = $row;
	foreach ($bdt as $rdt_id => $rdt_name)
		$bdr[$row['rty_ID']][$rdt_id]['dty_Name'] = $rdt_name;
	}

?>
<style type="text/css">
	* { font-family: monospace; }
	li .red { color: red; }
	li .gray { color: lightgray; }
	li .gray .red { color: lightgray; }
</style>

<?php

	print "<h2>EndNote export field definitions</h2>";
	print "<p>Heurist imports the EndNote field on the left to the Heurist field on the right</p>";

	print '<ul>';
	foreach ($refer_to_heurist_map as $type => $details) {
	print '<li><b>' . $type . ':</b><br>';
	print '<ul>';
	foreach ($details as $code => $bdts) {
		if (! is_array($bdts)) $bdts = array($bdts);
		foreach ($bdts as $bdt) {
			$label = decode_bdt($type, $bdt);

			if (strlen($code) == 1) {
				print '<li>%' . htmlspecialchars($code) . ': ' . ($label) . '</li>';
			} else {
				if ($code == 'Yr') {
					print '<li>%D: ' . ($label) . '</li>';
					print '<li>%8: ' . ($label) . '</li>';
				}
	//			print '<li><span class=gray>' . htmlspecialchars($code) . ': ' . ($label) . '</span></li>';
			}
		}
	}
	print '</ul>';
	print '<br>';
	}
	print '</ul>';



	function decode_bdt($rec_types, $bdt_code) {
	global $refer_to_heurist_type_map;
	global $bdr;
	global $rft;

	$rectypeDescription = "";

	$colon_count = substr_count($bdt_code, ':');
	for ($i=1; $i <= $colon_count; ++$i) {
		$rt_id = $refer_to_heurist_type_map[$rec_types][$i];
		if (! $rt_id) return '<i>error</i>';

		$rectypeDescription .= $rft[$rt_id] . ".";
	}

	$rt_id = $refer_to_heurist_type_map[$rec_types][$colon_count];
	$my_bdr = $bdr[$rt_id][intval(substr($bdt_code, $colon_count))];
	$name = $my_bdr['rst_DisplayName']? $my_bdr['rst_DisplayName'] : $my_bdr['dty_Name'];
	return '<span class=red>'.$rectypeDescription.'</span>' . $name;
	}

?>
