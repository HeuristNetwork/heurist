<?php

require_once("/var/www/htdocs/heurist/php/modules/db.php");

mysql_connection_select("heuristdb");

$woots = array();
$res = mysql_query("select * from woots");// where woot_title='record:96990'");
while ($row = mysql_fetch_assoc($res)) {
	array_push($woots, $row);
}

print "<table>\n";

foreach ($woots as $woot) {
	$valid = true;
	$errs = array();

	//print "\n\nchecking woot \"" . $woot["woot_title"] . "\"... ";

	$res = mysql_query("select * from woot_chunks where chunk_woot_id = " . $woot["woot_id"] . " and chunk_is_latest and not chunk_deleted");
	while ($row = mysql_fetch_assoc($res)) {
		$err = check($row["chunk_text"]);
		if ($err) {
			$valid = false;
			array_push($errs, $err);
		}
	}

	if ($valid) {
		//print "ok\n";
	} else {
        print "<tr><td><a target=_blank href='/heurist/woot.html?w=";
		print $woot["woot_title"] . "'>";
        print $woot["woot_title"];   
        print "</a></td>\n";

		print "<td><pre>" . htmlspecialchars(join("\n", $errs)) . "</pre></td></tr>\n";
	}
}

function check($html) {
//print "text: $html\n";
	$descriptorspec = array(
		0 => array("pipe", "r"),
		2 => array("pipe", "w"),
	);
	$proc = proc_open("xmllint -o /dev/null -", $descriptorspec, $pipes);

	fwrite($pipes[0], "<html>" . $html . "</html>");
	fclose($pipes[0]);

	$out = stream_get_contents($pipes[2]);
	fclose($pipes[2]);

	$rv = proc_close($proc);
//print "rv: $rv\n";
//print "out: $out\n";

	if ($rv != 0) {
		return $out;
	} else {
		return 0;
	}
}

?>
