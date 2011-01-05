<?php

require_once(dirname(__FILE__)."/../woot.php");

$data = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

print json_encode(loadWoot($data));

?>
