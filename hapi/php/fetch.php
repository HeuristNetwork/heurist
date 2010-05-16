<?php

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$token = $_REQUEST["token"];
if (! preg_match('/^data[a-f0-9]{16}$/', $token)) { return; }

session_start();

if (@$_SESSION[$token]) {
	print $_SESSION[$token];
	unset($_SESSION[$token]);
}

session_write_close();

?>
