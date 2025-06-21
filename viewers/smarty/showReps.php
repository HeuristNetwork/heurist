<?php
/**
 * showReps.php - Invokes the ReportController for displaying reports.
 *
 * @fileOverview This script directly sets the controller to 'ReportController' and runs
 * the `FrontController`. It is a dedicated entry point for generating and displaying
 * Smarty-based reports. This script is for backward capability.
 * 
 * 
 * @package     Heurist academic knowledge management system
 * @subpackage  viewers\smarty
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       6.6
 */

use hserv\controller\FrontController;
require_once dirname(__FILE__).'/../../autoload.php';
$_REQUEST['controller'] = 'ReportController';
$frontController = new FrontController();
$frontController->run();
