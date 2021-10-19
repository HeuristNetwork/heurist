<?php
/**
* import OMEKA rectypes
* 
* it imports directly resource class id is preserved as rectype rty_ID
*/
exit();

ini_set('max_execution_time', 0);

define('O_DB','def19'); //OMEKA_DB
define('HEU_DB','hdb_def19_v1');

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
//require_once (dirname(__FILE__).'/../../hsapi/import/importParser.php');
require_once (dirname(__FILE__).'/../../hsapi/entity/dbDefRectypes.php');

$mysqli = $system->get_mysqli();

mysql__usedatabase($mysqli, HEU_DB);

//$entity = new DbDefRectypes($system, array('entity'=>'defRectypes'));

$query = 'delete from defRecTypes where rty_ID>94';
$mysqli->query($query);    

//$cfg = ImportParser::simpleCsvParser(array('content'=>$config));
$query = 'SELECT distinct rc.id, rc.label, rc.comment FROM '.O_DB.'.value v, '.O_DB.'.resource r, '.O_DB.'.resource_class rc '
.'where r.resource_class_id=rc.id AND  v.resource_id = r.id ';
$res = $mysqli->query($query);
if ($res){
    $result = array();
    while ($row = $res->fetch_row()){
        if($row[1]=='Organisation') $row[1]='Organisation (DEF)';
        
        $query = 'insert into defRecTypes (rty_ID, rty_Name, rty_Description, rty_TitleMask,'
.'rty_RecTypeGroupID, rty_ShowURLOnEditForm ) '
.' values ('.$row[0].',"'.$row[1].'","'.($row[2]==null?'':$row[2]).'","[2-1]",9,0)'; 
        $ret = $mysqli->query($query);
        if(!$ret){
            echo $mysqli->error;
            break; 
        }
    }
    echo 'FINISHED';
    //rty_Status
}else{
    echo $mysqli->error;
}
?>
