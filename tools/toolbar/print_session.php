<?php
require_once("../php/modules/cred.php");

//session_start();
print_r($_SESSION);

print("\n\n\njump sessions...\n\n\n");
jump_sessions();
print_r($_SESSION);

print("\n\n\njump sessions...\n\n\n");
jump_sessions();
print_r($_SESSION);
?>
