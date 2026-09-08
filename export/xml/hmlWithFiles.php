<?php
/**
 * Generates the normal result-set HML export and packages it with its local uploaded files.
 */

$_REQUEST['include_uploaded_files'] = '1';
unset($_REQUEST['file']);
unset($_REQUEST['filename']);

require dirname(__FILE__).'/flathml.php';
