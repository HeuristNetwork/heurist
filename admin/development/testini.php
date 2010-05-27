<?php
header("Content-type: text/javascript");

require_once(dirname(__FILE__).'/../../common/config/heurist-ini.php');

print "doc root = ".$DOCUMENT_ROOT." ".$cnt." uploadpath = ".HEURIST_UPLOAD_BASEPATH." db prefix = ".HEURIST_DB_PREFIX." common db name = ".HEURIST_COMMON_DB;
?>
