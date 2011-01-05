<?php

/* Reflect the uploaded file(s) back in JSON */

header("Content-type: text/javascript");

$first = true;
print "({\n";
foreach ($_FILES as $uploadName => $file) {
	if (! $first) print ",\n";
	$first = false;

	printAsJSON($uploadName, $file);
}
print "\n})\n";


function printAsJSON($uploadName, $file) {
	$content = file_get_contents($file["tmp_name"]);

	$jsonContent = str_replace('\\', '\\\\', $content);
	$jsonContent = str_replace('"', '\\"', $jsonContent);
	$jsonContent = str_replace("\n", '\\n', $jsonContent);
	$jsonContent = str_replace("\r", '\\r', $jsonContent);
	$jsonContent = str_replace("\0", '\\u0000', $jsonContent);
	$jsonContent = preg_replace("/([\\001-\\037\\177])/e", "sprintf('\\u%04X', ord('\\1'));", $jsonContent);

	print "\"" . addslashes($uploadName) . "\": { fileName: \"" . addslashes($file["name"]) . "\", content: \"" . $jsonContent . "\" }";
}

?>
