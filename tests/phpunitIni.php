<?php
require_once __DIR__ . '/../configIni.php';
$dbHost = '127.0.0.1';
$dbAdminUsername = getenv('HEURIST_DB_ADMIN_USERNAME') ?: 'heurist';
$dbAdminPassword = getenv('HEURIST_DB_ADMIN_PASSWORD') ?: 'heurist';
$dbPort = 3306;
require_once __DIR__ . '/../autoload.php';
?>
