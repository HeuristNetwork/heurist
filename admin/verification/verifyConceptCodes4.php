<?php
    /*
    * Copyright (C) 2005-2020 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * http://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * Find db v1.2 with existing defTermLinks
    *      and v1.3 with individual selection of terms
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

/*
if( $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    print $response = $system->getError()['message'];
    exit();
}
*/

?>            
<div style="font-family:Arial,Helvetica;font-size:12px">
            <p>This list shows definitions without concept codes for registered databases</p>
<?php            


$mysqli = $system->get_mysqli();
    
    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while (($row = $res->fetch_row())) {
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }

      
    $db2_with_links = array();
    $db2_with_terms = array();
    $db3_with_terms = array();
    
    foreach ($databases as $idx=>$db_name){

        $query = 'SELECT sys_dbSubVersion from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);
        
        if($ver<3){
            /* databases without trm_VocabularyGroupID
            $query = "SHOW COLUMNS FROM '.$db_name.'defTerms LIKE 'trm_VocabularyGroupID'";
            if(!hasColumn($mysqli, $query)){
                print $db_name.'<br>';
            }
            */
            continue;
                    
            
            //is defTermLinks exist
            $value = mysql__select_value($mysqli, 'SHOW TABLES FROM '.$db_name." LIKE 'defTermsLinks'");
            $not_exist = ($value==null || $value=="");
            if(!$not_exist){
                array_push($db2_with_links,$db_name);    
            }
            
        }else{
            
            
            $query = 'select count(*) from '.$db_name.'.defDetailTypes where '
            .'(dty_Type="relationtype") and (NOT dty_JsonTermIDTree>0)'; 
            //dty_Type="enum" OR dty_Type="relmarker" OR  OR dty_Type="relationtype"
            $value = mysql__select_value($mysqli, $query);
            if($value>0){

                $query = 'select dty_ID, dty_Name, dty_JsonTermIDTree from '.$db_name.'.defDetailTypes where '
                .'(dty_Type="relationtype") and (NOT dty_JsonTermIDTree>0)'; // OR dty_Type="relationtype"
                $value = mysql__select_all($mysqli, $query);
                
                if($ver<3){
                    $db2_with_terms[$db_name] = $value;
                }else{
                    if(@$_REQUEST["fix"]==1){
                        $query = 'UPDATE '.$db_name.'.defDetailTypes SET dty_JsonTermIDTree="" where (dty_Type="relationtype")';
                        $mysqli->query($query);
                        if($mysqli->error){
                            print '<div style="color:red">'.$mysqli->error.'</div>';                    
                            exit();
                        }
                    }
                    $db3_with_terms[$db_name] = $value;
                }

                /* datetime of creation for defTermsLinks
                $query = "SELECT create_time FROM INFORMATION_SCHEMA. TABLES WHERE table_schema = '"
                        .$db_name."' AND table_name = 'defTermsLinks'";
                $dt = mysql__select_value($mysqli, $query);
                $db3_with_terms[$db_name] = array($dt,$value);
                */
            }
        }
        //find terms with specific ccodes - create new or set vocabularies
        /*
Show legend on startup 3-1079  ( 2-6255 )  3-5074, 3-5075, 3-5076
Suppress timeline 3-1080  ( 2-6256 )  3-5078, 3-5079
Hide layer outside zoom range 3-1087  ( 2-6257 )  3-5081, 3-5082
Show labels 3-1088  ( 2-6258 )  3-5084, 3-5085, 3-5086
        */        
        //from fields to vocabs to terms - assign proper ccodes
        print "<br><br>".$db_name.'<br>';
        //verifySpatialVocab('Show legend on startup','3-1079','2-6255');
        //verifySpatialVocab('Suppress timeline','3-1080','2-6256');
        //verifySpatialVocab('Hide layer outside zoom range','3-1087','2-6257');
        //verifySpatialVocab('Show labels','3-1088','2-6258');
            
            
/*            
            $query = "SHOW COLUMNS FROM ".$db_name.".sysIdentification LIKE 'sys_ExternalReferenceLookups'";
            if(!hasColumn($mysqli, $query)){ //column not defined
                $query = "ALTER TABLE ".$db_name.".sysIdentification ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
                $res = $mysqli->query($query);
                print $db_name.': sys_ExternalReferenceLookups added<br>';
            }
*/            
            
        
    }//while  databases
    print '<p>v2 with defTermLinks</p>';
    print print_r($db2_with_links, true);


    print '<hr><p>v2 with individual term selection</p>';

    foreach ($db2_with_terms as $db_name=>$value){
        print $db_name.'<br>';
        print print_r($value, true).'<br>';
    }

    
    print '<hr><p>v3 with individual term selection</p>';

    foreach ($db3_with_terms as $db_name=>$value){
        print $db_name.'<br>';
        print print_r($value, true).'<br>';
    }
    
/*    
    foreach ($db3_with_terms as $db_name=>$dt){
        print $db_name.'  '.$dt[1].'   '.$dt[0].'<br>';
    }
*/    
    
    print '[end report]</div>';
    
function hasColumn($mysqli, $query){
    $res = $mysqli->query($query);
    $row_cnt = 0;
    if($res) {
        $row_cnt = $res->num_rows; 
        $res->close();
    }
    return ($row_cnt>0);
}   
//
//
// 
function verifySpatialVocab($sName,$f_code,$v_code){
    global $mysqli, $db_name;
    
        $query = 'SELECT dty_ID, dty_Name, dty_JsonTermIDTree, dty_OriginatingDBID, dty_IDInOriginatingDB FROM '
                .$db_name.'.defDetailTypes WHERE dty_Name="'.$sName.'"';
                
        $fields = mysql__select_row($mysqli, $query);
        if($fields){
            
            $f_code = explode('-',$f_code);
            $v_code = explode('-',$v_code);
            
            print $fields[1];
            
            if(!($fields[3]==$f_code[0] && $fields[4]==$f_code[1])){
                //need change ccode for field
                print '<div style="color:red">NEED CHANGE FIELD CCODES</div>';                    
            } 
                
            $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms where trm_ID='.$fields[2];
            $vocab = mysql__select_row($mysqli, $query);
            if($vocab){
                if(!($vocab[2]==$v_code[0] && $vocab[3]==$v_code[1])){
                    print '<div>'.$vocab[1].' NEED CHANGE VOCAB CCODES '.$vocab[2].'-'.$vocab[3].'</div>';
                    
                    if(@$_REQUEST["fix"]==1){
                        $query = 'UPDATE '.$db_name.'.defTerms SET trm_OriginatingDBID='.$v_code[0].', trm_IDInOriginatingDB='
                            .$v_code[1].' where trm_ID='.$fields[2];
                        $mysqli->query($query);
                        if($mysqli->error){
                            print '<div style="color:red">'.$mysqli->error.'</div>';                    
                            exit();
                        }
                    }
                    
                }
                //find terms
                $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms, '.$db_name.'.defTermsLinks WHERE trm_ID=trl_TermID AND trl_ParentID='.$vocab[0];
                $terms = mysql__select_all($mysqli, $query);
                print '<table style="font-size:smaller">';
                foreach($terms as $term){
                    print '<tr><td>'.implode('</td><td>',$term).'</td></tr>';
                }
                print '</table>';
            }else{
                print '<div style="color:red">VOCAB NOT DEFINED</div>';                    
            } 
        }else{
            $query = 'SELECT dty_ID, dty_Name, dty_JsonTermIDTree FROM '
                .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID='.$f_code[0].' AND dty_IDInOriginatingDB='.$f_code[1];
            $fields = mysql__select_row($mysqli, $query);
            if($fields){
                print '<div style="color:red">FIELD HAS DIFFERENT NAME '.$fields[1].'</div>';                    
            }
        }
} 
?>
