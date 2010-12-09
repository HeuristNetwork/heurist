<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/db.php");

mysql_connection_select("heuristdb");	//FIXME:  need to use a configured value

$woots = array();
$res = mysql_query("select * from woots");// where woot_Title='record:96990'");
while ($row = mysql_fetch_assoc($res)) {
	array_push($woots, $row);
}

print "<table>\n";

foreach ($woots as $woot) {
	$valid = true;
	$errs = array();

	//print "\n\nchecking woot \"" . $woot["woot_Title"] . "\"... ";

	$res = mysql_query("select * from woot_Chunks where chunk_WootID = " . $woot["woot_ID"] . " and chunk_IsLatest and not chunk_Deleted");
	while ($row = mysql_fetch_assoc($res)) {
		$err = check($row["chunk_Text"]);
		if ($err) {
			$valid = false;
			array_push($errs, $err);
		}
	}

	if ($valid) {
		//print "ok\n";
	} else {
        print "<tr><td><a target=_blank href='".HEURIST_URL_BASE."records/woot/woot.html?w=";
		print $woot["woot_Title"] . "'>";
        print $woot["woot_Title"];
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
