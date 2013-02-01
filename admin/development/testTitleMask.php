<?php

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

mysql_connection_select(DATABASE);

require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');


$mask = @$_REQUEST['mask']; //
$rt = @$_REQUEST['rtID']; //
$recID = @$_REQUEST['recID']; //
if (!$rt or !$mask){
	echo "please pass in at least a mask and a rectype ID  as mask=validMaskStringHere&rtID=# <br />";
	echo "you may also padd in a record ID of rectype to calculate the title <br />";
	echo "be sure to add the db=databaseName for the database you are working on.<br />";
	exit();
}
echo (($ret=check_title_mask($mask,$rt))?$ret."<br /><br /><br />":"title mask\"<b>$mask</b>\"checks out to be valid for rectype $rt<br /><br /><br />");
echo "canonical form for mask is \"<b>".make_canonical_title_mask($mask,$rt)."</b>\"<br /><br /><br />";
if (!$ret && $recID) {
	echo "Title for record $recID :     <b>".fill_title_mask($mask,$recID,$rt)."</b><br />";
}

//echo json_format(_title_mask__get_rec_detail_types(),true)."<br><br><br>";
//echo json_format(_title_mask__get_rec_detail_requirements(),true)."<br><br><br>";


//echo json_format(getTermTree("reltype","prefix"),true).";\n";
//echo json_format(getDetailTypeDef(158),true)."<br><br><br>";
//echo print_r(getTermSets('reltypevocab'),true)."<br><br><br>";
//require_once(dirname(__FILE__).'/../../hapi/php/loadHapiCommonInfo.php');
//echo json_format(getAllRectypeConstraint(),true)."<br>";
//require_once(dirname(__FILE__).'/../../common/php/loadCommonInfo.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
//require_once(dirname(__FILE__).'/../../import/bookmarklet/getRectypesAsJSON.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
?>
