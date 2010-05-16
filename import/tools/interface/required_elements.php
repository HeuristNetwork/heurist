<?php

require_once('HeuristReferImport.php');

require_once('../php/modules/cred.php');
require_once('../php/modules/db.php');


mysql_connection_db_select(DATABASE);

$bdt = mysql__select_assoc('rec_detail_types', 'rdt_id', 'rdt_name', '1');
$rft = mysql__select_assoc('rec_types', 'rt_id', 'rt_name', '1');
$res = mysql_query('select * from rec_types left join rec_detail_requirements on rdr_rec_type=rt_id order by !rt_primary, rt_name');
$bdr = array();
while ($row = mysql_fetch_assoc($res)) {
	if (! $bdr[$row['rt_id']])
		$bdr[$row['rt_id']] = array();
	$bdr[$row['rt_id']][$row['rdr_rdt_id']] = $row;
	foreach ($bdt as $rdt_id => $rdt_name)
		$bdr[$row['rt_id']][$rdt_id]['rdt_name'] = $rdt_name;
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

	$reftypeDescription = "";

	$colon_count = substr_count($bdt_code, ':');
	for ($i=1; $i <= $colon_count; ++$i) {
		$rt_id = $refer_to_heurist_type_map[$rec_types][$i];
		if (! $rt_id) return '<i>error</i>';

		$reftypeDescription .= $rft[$rt_id] . ".";
	}

	$rt_id = $refer_to_heurist_type_map[$rec_types][$colon_count];
	$my_bdr = $bdr[$rt_id][intval(substr($bdt_code, $colon_count))];
	$name = $my_bdr['rdr_name']? $my_bdr['rdr_name'] : $my_bdr['rdt_name'];
	return '<span class=red>'.$reftypeDescription.'</span>' . $name;
}

?>
