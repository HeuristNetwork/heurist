<?php
/**
* Registers autload function to enable for classes and interfaces to be
* automatically loaded if they are currently not defined (by include/require).
*
* Includes common scripts: config, const, db access and 3 static classes
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @version     6.0
*/

spl_autoload_register(function ($class) {

    $prefix = 'hserv\\';
    if (strpos($class, $prefix) !== 0) {
        //alternative $prefix = __NAMESPACE__ . $class;
        return;
    }

    $filename = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    if (!is_readable($filepath)) {
        return;
    }
    require_once $filepath;
});
require_once dirname(__FILE__).'/configIni.php';// read in the configuration file

require_once dirname(__FILE__).'/hserv/consts.php';

require_once dirname(__FILE__).'/hserv/dbaccess/utils_db.php';
require_once dirname(__FILE__).'/hserv/utilities/UFile.php';
require_once dirname(__FILE__).'/hserv/utilities/UMail.php';
require_once dirname(__FILE__).'/hserv/utilities/ULocale.php';

global $system;
global $glb_curl_error;
?>
