<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
header("Content-type: text/javascript");

require_once(dirname(__FILE__).'/../../common/config/initialise.php');

print "doc root = ".$DOCUMENT_ROOT." ".$cnt." uploadpath = ".HEURIST_UPLOAD_BASEPATH." db prefix = ".HEURIST_DB_PREFIX." common db name = ".HEURIST_COMMON_DB;
?>
