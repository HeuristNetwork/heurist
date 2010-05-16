<?php

require_once("modules/woot.php");

$data = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

print json_encode(saveWoot($data));

?>
