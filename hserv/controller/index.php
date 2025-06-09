<?php
/**
* index.php - Main entry point for the Heurist controllers
*
* This script initializes and runs the FrontController, which is responsible
* for handling incoming requests and routing them to the appropriate
* controllers.
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       6.6
*/
use hserv\controller\FrontController;

require_once dirname(__FILE__).'/../../autoload.php';

$frontController = new FrontController();
$frontController->run();
?>
