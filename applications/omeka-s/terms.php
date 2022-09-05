<?php
/**
* import OMKEA terms, creates and fills vocabularies based on special tables and values
* 
* 2 options
* 1) by prperty id - select distinct values and add to specified vocabulary
* 2) by values from specified tables
* 
* configuration is included into this script
*/
ini_set('max_execution_time', 0);

exit();

//enum flag properties
/* 227,228,232,285,357,370
update value set uri=5442 where value='Non' and property_id in (227,228,232,285,357,370);
update value set uri=5443 where value='N/A' and property_id in (227,228,232,285,357,370);
update value set uri=5444 where value='Oui' and property_id in (227,228,232,285,357,370);

update value set uri=414 where value='H' and property_id=125;
update value set uri=415 where value='F' and property_id=125;
update value set uri=528 where value='N/A' and property_id=125;
*/

//enum properties
//202,224,318,377,223,245,325,278,280,281,342,283,291,329,344,346,290,383

/*
Property id: list of enum properties uses the same vocabulary
Tabale name: takes terms from this table, don’t worry if value is missed in this table it will be added to target vocabulary
Vocab name: name of vocabulary to be added to heurist
Resource class ID: check properties for these class only. (in OMEKA some fields are inconsistent for its types for different classes)
*/
//pid,table,name,rcid
$config = <<<'EOD'
202,fonctions,fonctions,155
"224,318,377",departments,departments,
"223,245,325",,pays,
278,typesdebrevet,types de brevet,
280,qualites,qualites,
281,specialites,specialites,149
342,diplomes,diplomes,
283,causes-fin-brevets,brevet cause fin,
291,genres,genres,
329,types-adresses,types de adresses,
344,statuts,statut,
346,typesdeproces,types de proces,
"290,383",roles,roles,
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
require_once (dirname(__FILE__).'/../../hsapi/entity/dbDefTerms.php');

$mysqli = $system->get_mysqli();

mysql__usedatabase($mysqli, HEU_DB);

$cfg = ImportParser::simpleCsvParser(array('content'=>$config));

$entity = new DbDefTerms($system, array('entity'=>'defTerms'));

//print print_r($res, true);
foreach($cfg as $idx=>$row){
//1. remove vocabulary
$query = 'select trm_ID FROM defTerms WHERE trm_ParentTermID is null '
    .' and trm_Label="'.$mysqli->real_escape_string($row[2]).'"';
$trm_ID = mysql__select_value($mysqli, $query);
if($trm_ID>0){
    $query = 'DELETE FROM defTerms WHERE trm_ParentTermID='.$trm_ID;
    $ret = $mysqli->query($query);
    $query = 'DELETE FROM defTerms WHERE trm_ID='.$trm_ID;
    $ret = $mysqli->query($query);
}

//2. add new vocabulary   
$parent_id = addTerm($entity, 0, $row[2], null);
echo 'VOCAB '.$parent_id.'  '.$row[2].'<br>';

//3. getterms from def19 terms table
if($row[1]=='departments'){
    $query = 'select nom,code from '.O_DB.'.`'.$row[1].'` where code>0';
}else{
    $query = 'select nom from '.O_DB.'.`'.$row[1].'`';    
}
$list = mysql__select_all($mysqli, $query);
//4. save terms
$term_defs = array();
foreach($list as $i => $term_def){
    $term_id = addTerm($entity, $parent_id, $term_def[0], @$term_def[1]);
    $term_defs[$term_id] = $term_def;
    
    echo $term_id.'  '.$term_def[0].'<br>';
}
//4. save term_id in uri field
$from  = '';
if(strpos($row[0],',')>0){
    $where = ' in ('.$row[0].')';
}else{
    $where = ' = '.$row[0];
    if(@$row[3]>0){
        $from  = ', '.O_DB.'.resource r ';
        $where = $where.' and v.resource_id = r.id and r.resource_class_id='.$row[3];    
    }
}

//clear uri fields
$query = 'update '.O_DB.'.value v '.$from.' set uri=null where v.property_id '.$where;
$mysqli->query($query);    

$query = 'select v.id, v.value from '.O_DB.'.value as v '.$from.' where v.property_id '.$where;
$res = $mysqli->query($query);
if ($res){
    $result = array();
    while ($vals = $res->fetch_row()){
        //remove []
        $val = $vals[1];
        $k = strpos($val,'[');
        if($k>0){
            $val = trim(substr($val,0,$k));
        }
        //5. find term id 
        $term_found = -1;
        foreach($term_defs as $term_id=>$term){
            if(strcasecmp($val,$term[0])==0){
                $term_found = $term_id;                        
                break;
            }else if(is_numeric($val) && @$term[1]==$val){
                $term_found = $term_id;                        
                break;
            }
        }
        //6. add new term id
        if(!($term_found>0)){
            $term_id = addTerm($entity, $parent_id, $val, null);    
            echo 'added '.htmlspecialchars($term_id.'  '.$val).'<br>';
            $term_defs[$term_id] = array($val);
        }
        //6. save term id in uri field
        $query = 'update '.O_DB.'.value set uri='.$term_id.' where id='.$vals[0];
        $mysqli->query($query);    
    }//while
}else{
   echo $mysqli->error;
   break; 
}

//debug  break;
    
}//for config

//------------------------
//
//
function addTerm($entity, $parent_id, $label, $code){
    
        $recinfo = array(
            'entity'=>'defTerms', 
            'fields'=>array(    
                'trm_ID' => -1,
                'trm_Label' => $label,
                'trm_Code' => $code,
                'trm_ParentTermID' => $parent_id,
                'trm_Domain' =>'enum'
            )
        );
        
        $entity->setData($recinfo);
        
        $ret = $entity->save();
        if($ret!==false){
            $records = $entity->records();
            return $records[0]['trm_ID'];
        }else{
            return null;
        }
}
  
?>
