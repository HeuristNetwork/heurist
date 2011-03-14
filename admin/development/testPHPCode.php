<?php

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

// Deals with all the database connections stuff

mysql_connection_db_select(DATABASE);
//place code here
//echo DATABASE."\n";
//echo json_format(getVocabTree('reltype'),true)."<br><br><br>";
//echo json_format(getAllRectypeStructures(),true)."<br><br><br>";
/*
echo $st = microtime(true)."<br><br><br>";
echo json_format(getAllRelatedRecords2(205),true)."<br><br><br>";
echo (($et = microtime(true))-$st)."<br><br><br>";
echo json_format(getAllRelatedRecords(205),true)."<br><br><br>";
echo microtime(true) - $et;
*/
//echo json_format(getTermOffspringList(1820),true)."<br><br><br>";
echo json_format(getAllRectypeConstraint(),true)."<br><br><br>";
//echo json_format(getAllDetailTypeStructures(),true)."<br><br><br>";
//echo json_format(getTermTree("reltype","prefix"),true).";\n";
//echo json_format(getDetailTypeDef(158),true)."<br><br><br>";
//echo print_r(getTermSets('reltypevocab'),true)."<br><br><br>";
//require_once(dirname(__FILE__).'/../../hapi/php/loadHapiCommonInfo.php');
//echo json_format(getAllRectypeConstraint(),true)."<br>";
//require_once(dirname(__FILE__).'/../../common/php/loadCommonInfo.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
//require_once(dirname(__FILE__).'/../../import/bookmarklet/getRectypesAsJSON.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
?>
