<?php
/**
* http://127.0.0.1/h6-ao/applications/EdArc/importEdArc.php
* import EdArc database structure
* 
* configuration is included into this script
*/
exit();

ini_set('max_execution_time', 0);

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once (dirname(__FILE__).'/../../hsapi/import/importParser.php');


define('HEU_DB','hdb_Tuffery_EdArc');
define('F_TABLE',0);
define('F_FIELD',1);
define('F_HELP',2);
define('F_TYPE',3);
define('F_REQ',4);
define('F_REP',5);

$fieldtypes = array('DATE'=>'date','NUMERIC'=>'float', 'TEXT'=>'freetext', 'BOOL'=>'enum', 'TERMS'=>'enum');

$mysqli = $system->get_mysqli();
mysql__usedatabase($mysqli, HEU_DB);


$content = file_get_contents('EdARC.tsv'); //D:/work/acl/csv/
  
$params = array('content'=>$content,
                'csv_delimiter'=>"\t",
                'csv_enclosure'=>'#',
                'csv_linebreak'=>'auto');

$list = ImportParser::simpleCsvParser($params);

//clear rectypes and details from previous import
$mysqli->query('delete from defRecStructure where rst_RecTypeID>94');    
$mysqli->query('delete from defRecTypes where rty_ID>94');    
$mysqli->query('delete from defDetailTypes where dty_ID>1052');    
$mysqli->query('delete from defTerms where trm_ID>6878');    

$mysqli->query('delete from defRecTypeGroups where rtg_Name="EdArc"');    
$mysqli->query('delete from defDetailTypeGroups where dtg_Name="EdArc"');    
$mysqli->query('delete from defVocabularyGroups where vcg_Name="EdArc"');    

$rtg_ID = mysql__insertupdate($mysqli, 'defRecTypeGroups', 'rtg', array('rtg_Name'=>'EdArc','rtg_Description'=>'EdArc'));
$dtg_ID = mysql__insertupdate($mysqli, 'defDetailTypeGroups', 'dtg', array('dtg_Name'=>'EdArc','dtg_Description'=>'EdArc'));
$vcg_ID = mysql__insertupdate($mysqli, 'defVocabularyGroups', 'vcg', array('vcg_Name'=>'EdArc','vcg_Description'=>'EdArc'));

$curr_table = null;
$rty_ID_cnt = 94;
$dty_ID_cnt = 1052;
$rty_ID = 0;
$order = 1;

array_shift($list);

foreach($list as $row){
    
    $row[F_TABLE] = trim($row[F_TABLE]);
    $row[F_FIELD] = trim($row[F_FIELD]);
    $row[F_TYPE] = trim($row[F_TYPE]);
    $row[F_HELP] = trim($row[F_HELP]);
    
    //ADD RECTYPE
    if($curr_table!=$row[F_TABLE]){

            $curr_table = $row[F_TABLE];
            $order = 1;
            
            $rty_rec = mysql__select_value($mysqli, 'select rty_ID from defRecTypes where rty_Name="'.$row[F_TABLE].'"');
            if($rty_rec>94){
                $rty_ID = $rty_rec;    
            }else{
                if($rty_rec>0){ //avoid duplication
                    $row[F_TABLE] = $row[F_TABLE].' (EdArc)';
                }
        
                $rty_ID_cnt++;
                $rty_ID = $rty_ID_cnt;

                $query = 'insert into defRecTypes (rty_ID, rty_Name, rty_Description, rty_TitleMask,'
        .'rty_RecTypeGroupID, rty_ShowURLOnEditForm ) '
        .' values ('.$rty_ID.',"'.$row[F_TABLE].'","","record [ID]",'.$rtg_ID.',0)'; 
                $ret = $mysqli->query($query);
                if(!$ret){
                    echo $mysqli->error;
                    break; 
                }else{
                    $rty_ID = $mysqli->insert_id;
                }
print $row[F_TABLE].'<br>';             
            }
    }

    //ADD FIELD
    $dty_Type = $fieldtypes[$row[F_TYPE]];
    $dty_ID = null;

    $detal_rec = mysql__select_row($mysqli, 'select dty_ID, dty_Type from defDetailTypes where dty_Name="'.$row[F_FIELD].'"');
    
    if($detal_rec!=null){
        if($detal_rec[1]==$dty_Type || ($row[F_TYPE]=='TEXT' && $detal_rec[1]=='blocktext')){
            $dty_ID = $detal_rec[0];
print '&nbsp;&nbsp;&nbsp;'.$detal_rec[0].'  ('.$row[F_FIELD].'  '.$detal_rec[1].')<br>';    

            if(mysql__select_value($mysqli, 'select rst_ID from defRecStructure where rst_RecTypeID='.$rty_ID.
            ' AND rst_DetailTypeID='.$dty_ID)>0){
print '&nbsp;&nbsp;&nbsp; already exist<br>';                
                continue;
            }


         
        }else{
            $row[F_FIELD] = $row[F_FIELD].' (EdArc)';
print '&nbsp;&nbsp;&nbsp;'.$row[F_FIELD].'  ['.$row[F_TYPE].'  '.$detal_rec[1].'])<br>';             
        }
    }
    
    if($dty_ID==null){
    
        $dty_Vocab = '';
        if($row[F_TYPE]=='BOOL'){
            $dty_Vocab = 6247; //yes-no
        }else if($row[F_TYPE]=='TERMS'){
            $dty_Vocab = mysql__insertupdate($mysqli, 'defTerms', 'trm', 
                    array('trm_Label'=>'Vocabulary for field '.$row[F_FIELD], 'trm_VocabularyGroupID'=>$vcg_ID));
        }

        $dty_ID_cnt++;
        $dty_ID = $dty_ID_cnt;
        
        $query = 'insert into defDetailTypes '
    .'(dty_ID, dty_Name, dty_Type, dty_HelpText, dty_DetailTypeGroupID, dty_JsonTermIDTree)'
    .' values ('.$dty_ID.',"'.$row[F_FIELD].'","'.$dty_Type.'","'.$row[F_HELP].'",'.$dtg_ID.',"'.$dty_Vocab.'")'; 
        
        $ret = $mysqli->query($query);
            if(!$ret){
                echo $mysqli->error;
                break; 
            }
        
        $dty_ID = $mysqli->insert_id;
    }
    
//define('F_REQ',4);
//define('F_REP',5);    
    //ADD STRUCTURE
    $query = 'insert into defRecStructure '
.'(rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayOrder, rst_RequirementType, rst_MaxValues)'
.' values ('.$rty_ID.','.$dty_ID.',"'.$row[F_FIELD].'","'.$row[F_HELP].'",'.$order.',"recommended",1)';

            $res2 = $mysqli->query($query);
            if(!$res2){
                $ret = false;
                echo $mysqli->error;
                break; 
            }
    $order++;
    
}
?>
