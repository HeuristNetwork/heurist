<?php
/**
 * autoload.php - Registers autoload function and includes common scripts.
 *
 * @fileOverview Registers an autoload function to enable classes and interfaces to be
 * automatically loaded if they are not currently defined. It also includes
 * common scripts such as configuration, constants, database access utilities,
 * and other static utility classes.
 * 
 * @project     Heurist academic knowledge management system
 * @package Core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

spl_autoload_register(
    /**
     * Autoloads HSERV classes.
     *
     * PSR-4 style autoloader for classes under the 'hserv\' namespace.
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    function ($class) {
        $prefix = 'hserv\\';
        if (strpos($class, $prefix) !== 0) {
            // Alternative: $prefix = __NAMESPACE__ . $class;
            return;
        }

        $filename = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

        if (!is_readable($filepath)) {
            return;
        }
        require_once $filepath;
    }
);

require_once dirname(__FILE__).'/configIni.php';// read in the configuration file

require_once dirname(__FILE__).'/hserv/consts.php';

require_once dirname(__FILE__).'/hserv/dbaccess/utils_db.php';
require_once dirname(__FILE__).'/hserv/utilities/UFile.php';
require_once dirname(__FILE__).'/hserv/utilities/UMail.php';
require_once dirname(__FILE__).'/hserv/utilities/ULocale.php';

global $system;
global $glb_curl_error;
?>
