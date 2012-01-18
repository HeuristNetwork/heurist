<?php

/**
 * editRectypeTitle.php
 *
 * Generates the title from mask, recid and rectype
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @author Artem Osmakov
 * @version 2011.0819
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
//require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');

mysql_connection_select(DATABASE);

$rectypeID = @$_REQUEST['rty_id'];
$mask = @$_REQUEST['mask'];

if(array_key_exists("check",@$_REQUEST))
{
	echo check_title_mask2($mask, $rectypeID, true);
}else{
	$recID = @$_REQUEST['rec_id'];
	echo fill_title_mask($mask, $recID, $rectypeID);
}
exit();
?>
