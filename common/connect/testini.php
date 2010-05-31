<?php
header("Content-type: text/javascript");

require_once('cred.php');

print "database = ".DATABASE." ".$cnt." host base = ".HOST_BASE." doc root = ".$DOCUMENT_ROOT." dir = ".dirname(__FILE__);
print_r($instances[$instance]);
?>
