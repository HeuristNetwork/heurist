<?php

/**
 * redirect.php - acts as a PID redirector to view record  - provides a minimal URL for redirection
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo - only one kml per record, perhaps need to return the combination of kml
 * **/


// called by applyCredentials require_once('configIni.php');
// called by applyCredentials  require_once(dirname(__FILE__).'/common/config/initialise.php');
require_once(dirname(__FILE__).'/common/connect/applyCredentials.php');

// Input is of the form .../redirect.php?db=sandpit5&recID=3456

$db = $_REQUEST["db"];
$id = $_REQUEST["recID"];

// Redirect to .../records/view/viewRecord.php

header('Location: ./records/view/viewRecord.php?db='.$db.'&recID='.$id);

return;

?>
