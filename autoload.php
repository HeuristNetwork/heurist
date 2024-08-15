<?php
spl_autoload_register(function ($class) {
    $prefix = 'hserv\\';
    if (strpos($class, $prefix) !== 0) {
        //$prefix = __NAMESPACE__ . $class;
        return;
    }
    //$filename = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    $filename = $class . '.php';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if (!is_readable($filepath)) {
        return;
    }
    require_once $filepath;
});

require_once dirname(__FILE__).'../configIni.php';// read in the configuration file
require_once dirname(__FILE__).'/hserv/consts.php';

require_once dirname(__FILE__).'/hserv/dbaccess/utils_db.php';
require_once dirname(__FILE__).'/hserv/utilities/UFile.php';
require_once dirname(__FILE__).'/hserv/utilities/UMail.php';
require_once dirname(__FILE__).'/hserv/utilities/ULocale.php';

?>
