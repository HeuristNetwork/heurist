<?php

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');

// Deals with all the database connections stuff

mysql_connection_select(DATABASE);
//place code here
//echo DATABASE."\n";
//echo json_format(getVocabTree('reltype'),true)."<br><br><br>";
echo json_format(getAllRectypeStructures(),true)."<br><br><br>";
/*
echo $st = microtime(true)."<br><br><br>";
echo json_format(getAllRelatedRecords2(205),true)."<br><br><br>";
echo (($et = microtime(true))-$st)."<br><br><br>";
echo json_format(getAllRelatedRecords(205),true)."<br><br><br>";
echo microtime(true) - $et;
*/

/*
($str = '{"3001":{"3108":{}},"3406":{"3083":{},"3084":{},"3087":{},"3088":{},"3089":{},"3090":{},"3092":{},"3094":{},"3095":{},"3099":{},"3100":{},"3103":{},"3104":{},"3105":{}},"3407":{"3006":{},"3009":{},"3013":{},"3014":{},"3015":{},"3016":{},"3017":{},"3018":{},"3025":{},"3026":{},"3027":{},"3028":{},"3029":{},"3030":{},"3031":{},"3032":{},"3033":{},"3034":{},"3041":{},"3042":{},"3043":{},"3044":{},"3045":{},"3046":{},"3047":{},"3048":{},"3049":{},"3050":{},"3051":{},"3052":{},"3055":{},"3056":{},"3059":{},"3060":{},"3070":{},"3071":{}},"3408":{"3004":{},"3021":{},"3022":{},"3039":{},"3040":{},"3053":{},"3054":{},"3067":{},"3072":{},"3073":{},"3074":{},"3075":{},"3076":{},"3077":{},"3078":{},"3079":{},"3106":{},"3107":{}},"3409":{"3005":{},"3011":{},"3012":{},"3091":{}},"3410":{"3019":{},"3020":{},"3023":{},"3024":{},"3035":{},"3036":{},"3037":{},"3038":{},"3057":{},"3058":{},"3061":{},"3062":{},"3063":{},"3064":{},"3065":{},"3066":{},"3068":{},"3069":{},"3101":{},"3102":{}}}';
echo (strpos($str,"[")===false?0:1). "<br />";
$temp = preg_replace("/[\{\}\",]/","",$str);
echo strrpos($temp,":"). "<br />";
echo strlen($temp). "<br />";
$temp = substr($temp,0, strlen($temp)-1);
echo $temp. "<br />";
$ids = explode(":",$temp);
var_dump($ids);
echo $ids[0] . "<br />";
$str2 = preg_replace("/\"".$ids[1]."\"/","\"9999\"",$str);
echo $str2 . "<br />";

$str = '["123","456"]';
echo $str . "<br />";
$temp = preg_replace("/[\[\]\"]/","",$str);
echo $temp . "<br />";
$termIDs = explode(",",$temp);
echo $termIDs[0] . "<br />";
var_dump($termIDs);
echo preg_replace("/\"".$termIDs[0]."\"/","\"9999\"",$str);
*/
//echo $temp2 . "<br />";
//echo json_format(expandCollections(array("147960"),true),true)."<br><br><br>";
//echo json_format(getTerms(),true)."<br><br><br>";
//echo json_format(getTermOffspringList(1820),true)."<br><br><br>";
//echo json_format(getAllRectypeConstraint(),true)."<br><br><br>";
//echo json_format(getAllDetailTypeStructures(),true)."<br><br><br>";
//echo json_format(getTermTree("reltype","prefix"),true).";\n";
//echo print_r(getTermSets('reltypevocab'),true)."<br><br><br>";
//require_once(dirname(__FILE__).'/../../hapi/php/loadHapiCommonInfo.php');
//echo json_format(getAllRectypeConstraint(),true)."<br>";
//require_once(dirname(__FILE__).'/../../common/php/loadCommonInfo.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
//require_once(dirname(__FILE__).'/../../import/bookmarklet/getRectypesAsJSON.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
?>
