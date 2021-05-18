<?php
exit();

ini_set('max_execution_time', 0);

define('HEU_DB','hdb_def19_v2');

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$mysqli = $system->get_mysqli();

mysql__usedatabase($mysqli, HEU_DB);

$mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
$mysqli->query('SET SQL_SAFE_UPDATES=0');

$mysqli->query('insert into recDetails select * from  hdb_def19_v1.recdetails');
$mysqli->query('insert into Records select * from  hdb_def19_v1.records');

$mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
$mysqli->query('SET SQL_SAFE_UPDATES=1');
?>
