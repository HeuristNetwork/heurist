<?php
/**
* import OMKEA details and rt structure
* 
* configuration is included into this script
*/
ini_set('max_execution_time', '0');

exit();

//enum flag properties
/* 227,228,232,285,357,370
update value set uri=5442 where value='Non' and property_id in (227,228,232,285,357,370)
update value set uri=5443 where value='N/A' and property_id in (227,228,232,285,357,370)
update value set uri=5444 where value='Oui' and property_id in (227,228,232,285,357,370)
*/

//enum properties
//202,224,318,377,223,245,325,278,280,281,342,283,291,329,344,346,290,383

//2,creator,380,,NULL,
//24,modified,6239,,NULL,

//OMEKA property_ID=>dty_ID
$config = <<<'EOD'
7,9
35,937
48,938
125,20
131,939
138,132
139,18
141,940
143,1
150.143,16
187,3
189,231
202,941
206,16
207,16
209,942
211,943
218,944
220,945
223,26
224,234
226,4
227,946
228,947
229,948
230,949
231,950
232,951
234,952
239,953
242,954
244,955
245,26
247,956
248,957
250,958
251,959
252,960
253,9
262,961
267,962
268,963
271,964
272,10
273,11
274,965
275,966
276,967
277,968
278,969
279,10
280,970
149.281,971
150.281,1042
282,11
283,973
284,974
285,975
286,976
287,10
288,11
289,977
290,978
291,979
292,980
294,981
298,982
299,983
300,984
301,985
305,986
306,987
308,1041
309,988
311,10
312,11
313,989
315,990
316,991
317,992
318,234
319,993
320,994
322,995
324,996
325,26
326,10
327,11
329,997
330,231
331,998
336,999
341,1000
342,1001
343,1002
344,1003
345,1004
346,1005
347,1006
348,1007
349,1008
351,1009
352,1010
353,1011
354,1012
355,1013
356,1014
357,1015
358,1016
359,1017
360,1018
361,1019
362,1020
363,1021
364,1022
366,1023
368,1024
369,1025
370,1026
371,1027
373,1028
374,1029
375,1030
376,1031
377,234
378,1032
379,1033
382,1034
383,1035
384,1036
385,1037
386,1038
388,1039
389,1040
EOD;

/*


Translate gender for 2-20 and Flag 2-530 
*/
define('O_DB','def19'); //OMEKA_DB
define('HEU_DB','hdb_def19_v1');


define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once (dirname(__FILE__).'/../../hsapi/import/importParser.php');
//require_once (dirname(__FILE__).'/../../hsapi/entity/dbDefTerms.php');

$mysqli = $system->get_mysqli();

mysql__usedatabase($mysqli, HEU_DB);

$cfg = ImportParser::simpleCsvParser(array('content'=>$config));

$ret = true;
//$keep_autocommit = mysql__begin_transaction($mysqli);

$pairs = array();//omeka=>heurist

//adds fields
foreach($cfg as $idx=>$row){
    $pairs[$row[0]] = $row[1];    
}

                
$query = 'delete from recDetails where dtl_RecID>62030';
$mysqli->query($query);
$res2 = $mysqli->query($query);
if(!$res2){
    echo $mysqli->error;
    exit();
}
$query = 'delete from Records where rec_ID>62030';
$mysqli->query($query);
$res2 = $mysqli->query($query);
if(!$res2){
    echo $mysqli->error;
    exit();
}


//get properties by classes
$query = 'SELECT r.resource_class_id, p.id, r.id, v.value, v.value_resource_id, v.uri, v.id '
.'FROM '.O_DB.'.value v, '.O_DB.'.property p, '.O_DB.'.resource r '
.'where v.resource_id = r.id  and v.property_id=p.id and r.id>62030 '
.'order by r.id, v.id';

$res = $mysqli->query($query);
if ($res){

    $currID = null;
    while ($vals = $res->fetch_row()){

            $rty_ID = $vals[0];
            $property_ID = $vals[1];
            $rec_ID = $vals[2];
            if($currID != $rec_ID){
                $currID = $rec_ID;
                //echo "add str ".$currID."</br>";
                //add record
                
                $query = 'insert into Records '
    .'(rec_ID, rec_Title, rec_RecTypeID, rec_AddedByUGrpID, rec_AddedByImport, rec_OwnerUGrpID, rec_NonOwnerVisibility)'    
    .' values ('.$rec_ID.',"record #'.$rec_ID.'",'.$rty_ID.',2,1,2,"public")';
                $res2 = $mysqli->query($query);
                if(!$res2){
                    $ret = false;
                    echo htmlspecialchars($rec_ID).'  '.$mysqli->error;
                    break; 
                }
            }
            
            $dty_ID = null;
            //find dty_ID in pairs
            if($property_ID==281 || ($property_ID==143 && $rty_ID==150)){
                $dty_ID = @$pairs[$rty_ID.'.'.$property_ID];
            }else{
                $dty_ID = @$pairs[$property_ID];
            }
            
            if($dty_ID==null){
                continue;
            }
            
            $value = $vals[3];
            $link_RecID = $vals[4];
            $term_ID = $vals[5];
            $det_ID = $vals[6];
            
            if($term_ID>0){
                $value = $term_ID;    
            }else if($link_RecID>0){
                $value = $link_RecID;    
            }
            
    $query = 'insert into recDetails '
.'(dtl_ID, dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport)'
.' values ('.$det_ID.','.$rec_ID.','.$dty_ID.',"'.$mysqli->real_escape_string($value).'",1)';

            $res2 = $mysqli->query($query);
            if(!$res2){
                $ret = false;
                echo htmlspecialchars($det_ID).'  '.$mysqli->error;
                break; 
            }
            
            $vals = null;
            unset($vals);

    }//while
    
    print 'OK!!!!!';

}else{
    $ret = false;
    echo $mysqli->error;
}

/*
if($ret===false){
    $mysqli->rollback();
}else{
    $mysqli->commit();    
}

if($keep_autocommit===true) $mysqli->autocommit(TRUE);
*/
?>
