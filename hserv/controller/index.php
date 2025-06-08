<?php
/**
 * Main entry point for the Heurist application.
 *
 * This script initializes and runs the FrontController, which is responsible
 * for handling incoming requests and routing them to the appropriate
 * controllers.
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2024 University of Sydney
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     6.6
 */
use hserv\controller\FrontController;

require_once dirname(__FILE__).'/../../autoload.php';

$frontController = new FrontController();
$frontController->run();
?>
