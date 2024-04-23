<?php
    /*
    * Copyright (C) 2005-2023 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * https://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * 
    * Various actions to check/correct data and db structure per all databases on server
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
print 'disabled'; 
exit; 
ini_set('max_execution_time', '0');

 
//define('OWNER_REQUIRED', 1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/utilities/utils_db_load_script.php';

/*
//print htmlspecialchars($_REQUEST['db']).'<br>';
if( $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <form action="verifyConceptCodes4.php" method="POST">
        <div style="padding:20px 0px">
            Only an administrator (server manager) can carry out this action.<br>
            This action requires a special system administrator password (not a normal login password)
        </div>
    
        <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
        <input type="password" name="pwd" autocomplete="off" />

        <input type="submit" value="OK" />
    </form>

    <?php
    exit;
}
<script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>')</script>  
*/
?>            

         
<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
            <p>This report shows </p>
<?php            


$mysqli = $system->get_mysqli();
    
//find all database
$databases = mysql__getdatabases4($mysqli, false);   

foreach ($databases as $idx=>$db_name){
    $databases[$idx] = htmlspecialchars( $db_name );
}


/*    
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
*/

/*
if(false){
    //find non UTF-8 in rty_TitleMask
    __findWrongChars();   
}else if(false){
    
    __updateDatabase();
}else if(false){
    
    __checkVersionDatabase();
}else if(false){
    //trm_NameInOriginatingDB 
    __setTermNameTo255();
    //__findLongTermLabels();
}else if(false){
    findMissedTermLinks();
}else if(false){
    __setTermYesNo();
}else  if(false){
    __renameDegreeToKM();
}else  if(false){
    __recreateProceduresTriggers();
}else  if(false){
    __addOtherSources();
}else  if(false){
    __renameField39();
}else if(false ){
    __copy_RecType_And_Term_Icons_To_EntityFolder();
}else  if(false){
    __delete_OLD_RecType_And_Term_Icons_Folders();
}else if(false){
    __correctGetEstDate_and_ConvertTemporals_JSON_to_Plain();
}else if(false){
    
    __updateDatabases_To_V14( @$_REQUEST['process']);
}else if(false){
    __correctGetEstDate();
}else if(false){
    __removeDuplicationValues();
}else if(false){
    __listOfAdminUsers();
}else if(false){
    __convertTustep();
}
    __findRDF();
*/
    
    __dropBkpDateIndex();    

//
// Report database versions
//
function __checkVersionDatabase(){
    global $mysqli, $databases; 

    if(@$_REQUEST['reset']){
                $query = 'UPDATE sysIdentification SET sys_dbSubVersion=2, sys_dbSubSubVersion=0 WHERE sys_ID=1';
                $mysqli->query($query);
        print ' Database reset to v 1.2.0<br>';
        return;
    }

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $query = 'SELECT sys_dbSubVersion, sys_dbSubSubVersion from sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if(!$ver){
            print htmlspecialchars($db_name.'  >>> '.$mysqli->error);  
        }else{

            if($ver['sys_dbSubVersion']<3){
                print '<div style="color:red;font-weight:bold;">';
            }else if($ver['sys_dbSubVersion']>3){
                //$query = 'UPDATE sysIdentification SET sys_dbSubVersion=3 WHERE sys_ID=1';
                //$mysqli->query($query);
                print '<div style="color:green;font-weight:bold;">';
            }else{
                print '<div>';
            }
            print htmlspecialchars($db_name.'  >>>  1.'.$ver['sys_dbSubVersion'].'.'.$ver['sys_dbSubSubVersion']).'</div>';
        }        
    }

}

//
// updata database - add new fields
// 
function __updateDatabase(){
    
    global $mysqli, $databases; 

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
        
        $db_name = htmlspecialchars($db_name);
        
        if(hasTable($mysqli, 'defRecStructure')){
            
            if(hasColumn($mysqli, 'defRecStructure', 'rst_SemanticReferenceURL')){
                //print $db_name.': already exists<br>';
            }else{
                //alter table
                $query = "ALTER TABLE `defRecStructure` ADD `rst_SemanticReferenceURL` VARCHAR( 250 ) NULL "
                ." COMMENT 'The URI to a semantic definition or web page describing this field used within this record type' "
                .' AFTER `rst_LocallyModified`';
                $res = $mysqli->query($query);
                if(!$res){
                    print $db_name.' Cannot modify defRecStructure to add rst_SemanticReferenceURL: '.$mysqli->error;
                    return false;
                }else{
                    print $db_name.'<br>';
                }
            }    
            
            if(hasColumn($mysqli, 'defRecStructure', 'rst_TermsAsButtons')){
               print $db_name.': rst_TermsAsButtons already exists<br>';
            }else{
                //alter table
                $query = "ALTER TABLE `defRecStructure` ADD `rst_TermsAsButtons` TinyInt( 1 ) DEFAULT '0' "
                ." COMMENT 'If 1, term list fields are represented as buttons (if single value) or checkboxes (if repeat values)' "
                .' AFTER `rst_SemanticReferenceURL`';
                $res = $mysqli->query($query);
                if(!$res){
                    print $db_name.' Cannot modify defRecStructure to add rst_TermsAsButtons: '.$mysqli->error;
                    return false;
                }else{
                    print $db_name.'<br>';
                }
            }    
            
        }
    }
    print '[end report]';    
}

//------------------------------
//
//  
function __renameDegreeToKM(){
    global $mysqli, $databases; 

    print 'renameDegreeToKM<br>';
    
    
    //$query1 = 'UPDATE defRecStructure SET rst_DisplayName = REPLACE(rst_DisplayName, "degrees", "km"), rst_DefaultValue="" '
    //.'where rst_DisplayName like "%degrees%"';
    
    $query1 = 'UPDATE defRecStructure SET rst_DisplayName = REPLACE(rst_DisplayName, "degrees", "km"), rst_DefaultValue="" '
    .'where rst_DetailTypeID in (select dty_ID from defDetailTypes where dty_OriginatingDBID=3 and dty_IDInOriginatingDB in (1085,1086))';
    
    $query2 = 'UPDATE defDetailTypes SET dty_NameInOriginatingDB = REPLACE(dty_NameInOriginatingDB, "degrees", "km"), '
    .'dty_Name = REPLACE(dty_Name, "degrees", "km") WHERE dty_ID>0';

    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $db_name = htmlspecialchars($db_name);
        
        if(hasTable($mysqli, 'defRecStructure')){
            
            $res1 = $mysqli->query($query1);
            $res2 = $mysqli->query($query2); 
            $res2 = true;
                if($res1 && $res2){
                    print $db_name.'<br>';
                }else{
                    print $db_name.' Cannot modify defRecStructure, defDetailTypes: '.$mysqli->error;
                    return false;
                }
        }
    }//for
    print '[end report]';    
}

//------------------------------
//
//  
function findMissedTermLinks() {
    global $mysqli, $databases; 
    
    $db2_with_links = array();
    $db2_with_terms = array();
    $db3_with_terms = array();
    
    foreach ($databases as $idx=>$db_name){
        
        $db_name = preg_replace('/[^a-zA-Z0-9_]/', "", $db_name);

        $query = "SELECT sys_dbSubVersion from `$db_name`.sysIdentification";
        $ver = mysql__select_value($mysqli, $query);
        
        if(false && $ver<3){
            /* databases without trm_VocabularyGroupID
            if(!hasColumn($mysqli, 'defTerms', 'trm_VocabularyGroupID', $db_name)){
                print $db_name.'<br>';
            }
            */
            continue;
                    
            
            //is defTermLinks exist
            if(!hasTable($mysqli, 'defTermsLinks', $db_name)){
                array_push($db2_with_links,$db_name);    
            }
            
        }else{
            
            
            $query = "select count(*) from `$db_name`.defDetailTypes where "
            .'(dty_Type="relationtype") and (dty_JsonTermIDTree!="")'; 
            //dty_Type="enum" OR dty_Type="relmarker" OR  OR dty_Type="relationtype"
            $value = mysql__select_value($mysqli, $query);
            if($value>0){

                $query = "select dty_ID, dty_Name, dty_JsonTermIDTree from `$db_name`.defDetailTypes where "
                .'(dty_Type="relationtype") and (dty_JsonTermIDTree!="")'; // OR dty_Type="relationtype"
                $value = mysql__select_all($mysqli, $query);
                
                if($ver<3){
                    $db2_with_terms[$db_name] = $value;
                }else{
                    if(@$_REQUEST["fix"]==1){
                        $query = "UPDATE `$db_name`.defDetailTypes SET dty_JsonTermIDTree='' WHERE (dty_Type='relationtype')";
                        $mysqli->query($query);
                        if($mysqli->error){
                            print '<div style="color:red">'.$mysqli->error.'</div>';                    
                            exit;
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
 /*               
                //adds trash groups
                if(!(mysql__select_value($mysqli, 'select rtg_ID FROM '.$db_name.'.defRecTypeGroups WHERE rtg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defRecTypeGroups (rtg_Name,rtg_Order,rtg_Description) '
        .'VALUES ("Trash",255,"Drag record types here to hide them, use dustbin icon on a record type to delete permanently")';
                    //$mysqli->query($query);
                    $report[] = '"Trash" group has been added to rectype groups';                        
                }

                if(!(mysql__select_value($mysqli, 'select vcg_ID FROM '.$db_name.'.defVocabularyGroups WHERE vcg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defVocabularyGroups (vcg_Name,vcg_Order,vcg_Description) '
        .'VALUES ("Trash",255,"Drag vocabularies here to hide them, use dustbin icon on a vocabulary to delete permanently")';
                    //$mysqli->query($query);
                    $report[] = '"Trash" group has been added to vocabulary groups';                        
                }

                if(!(mysql__select_value($mysqli, 'select dtg_ID FROM '.$db_name.'.defDetailTypeGroups WHERE dtg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defDetailTypeGroups (dtg_Name,dtg_Order,dtg_Description) '
        .'VALUES ("Trash",255,"Drag base fields here to hide them, use dustbin icon on a field to delete permanently")';        
                    //$mysqli->query($query);
                    $report[] = '"Trash" group has been added to field groups';                        
                }
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
        //print "<br><br>".$db_name.'<br>';
        //verifySpatialVocab('Show legend on startup','3-1079','2-6255');
        //verifySpatialVocab('Suppress timeline','3-1080','2-6256');
        //verifySpatialVocab('Hide layer outside zoom range','3-1087','2-6257');
        //verifySpatialVocab('Show labels','3-1088','2-6258');
            
            
    }//while  databases
    
    if(count($db2_with_links)>0){
        print '<p>v2 with defTermLinks</p>';
        print htmlspecialchars(print_r($db2_with_links, true));
    }

    print '<hr><p>v2 with individual term selection for relationtype</p>';

    foreach ($db2_with_terms as $db_name=>$value){
        print $db_name.'<br>';
        //print print_r($value, true).'<br>';
    }

    
    print '<hr><p>v3 with individual term selection '.(@$_REQUEST["fix"]==1?'FIXED':'').'</p>';

    foreach ($db3_with_terms as $db_name=>$value){
        print $db_name.'<br>';
        print htmlspecialchars(print_r($value, true)).'<br>';
    }
    
/*    
    foreach ($db3_with_terms as $db_name=>$dt){
        print $db_name.'  '.$dt[1].'   '.$dt[0].'<br>';
    }
*/    
    
    print '[end report]</div>';
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
            
            print htmlspecialchars($fields[1]);
            
            if(!($fields[3]==$f_code[0] && $fields[4]==$f_code[1])){
                //need change ccode for field
                print '<div style="color:red">NEED CHANGE FIELD CCODES</div>';                    
            } 
                
            $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms where trm_ID='.intval($fields[2]);
            $vocab = mysql__select_row($mysqli, $query);
            if($vocab){
                if(!($vocab[2]==$v_code[0] && $vocab[3]==$v_code[1])){
                    print '<div>'.htmlspecialchars($vocab[1].' NEED CHANGE VOCAB CCODES '.$vocab[2].'-'.$vocab[3]).'</div>';
                    
                    if(@$_REQUEST["fix"]==1){
                        $query = 'UPDATE '.$db_name.'.defTerms SET trm_OriginatingDBID='.intval($v_code[0])
                            .', trm_IDInOriginatingDB='.intval($v_code[1])
                            .' where trm_ID='.intval($fields[2]);
                        $mysqli->query($query);
                        if($mysqli->error){
                            print '<div style="color:red">'.$mysqli->error.'</div>';                    
                            exit;
                        }
                    }
                    
                }
                //find terms
                $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms, '.$db_name.'.defTermsLinks WHERE trm_ID=trl_TermID AND trl_ParentID='.intval($vocab[0]);
                $terms = mysql__select_all($mysqli, $query);
                print '<table style="font-size:smaller">';
                foreach($terms as $term){
                    $list = str_replace(chr(29),'</td><td>',htmlspecialchars(implode(chr(29),$term)));
                    print '<tr><td>'.$list.'</td></tr>';
                }
                print '</table>';
            }else{
                print '<div style="color:red">VOCAB NOT DEFINED</div>';                    
            } 
        }else{
            $query = 'SELECT dty_ID, dty_Name, dty_JsonTermIDTree FROM '
                .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID='.intval($f_code[0]).' AND dty_IDInOriginatingDB='.intval($f_code[1]);
            $fields = mysql__select_row($mysqli, $query);
            if($fields){
                print '<div style="color:red">FIELD HAS DIFFERENT NAME '.htmlspecialchars($fields[1]).'</div>';                    
            }
        }
} 

//
//
//
function __findWrongChars(){
    
    global $mysqli, $databases; 


    print '[wrong characeters in rty_TitleMask]<br>';    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
        
        if(hasTable($mysqli, 'defRecTypes')){
            
            $list = mysql__select_assoc($mysqli, 'select rty_ID, rty_TitleMask from defRecTypes');

            $isOK = true;
            
            $db_name = htmlspecialchars($db_name);
            
            $res = json_encode($list); //JSON_INVALID_UTF8_IGNORE 
            if(true || !$res){

                foreach($list as $id => $val){
                    $wrong_string = null;
                    try{
                        find_invalid_string($val);
                        
                    }catch(Exception $exception) {
                        $isOK = false;
                        $wrong_string = $exception->getMessage();
                        print '<div style="color:red">'.$db_name.' rtyID='.$id.'. invalid: '.$wrong_string.'</div>';
                    }
                }//foreach
                
            }            
            if($isOK){
                    print $db_name.' OK<br>';
            }
        }
    }
    print '[end report]';    
}

function find_invalid_string($val){
    if(is_string($val)){
        $stripped_val = iconv('UTF-8', 'UTF-8//IGNORE', $val);
        if($stripped_val!=$val){
            throw new Exception(mb_convert_encoding($val,'UTF-8'));    
        }
    }
}

//
//
//
function __findLongTermLabels(){
    
    global $mysqli, $databases; 


    print '[long term labels]<br>';    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
        
        if(true){
            
            $list = mysql__select_assoc($mysqli, 'select trm_ID, trm_Label, CHAR_LENGTH(trm_Label) as chars, length(trm_Label) as len '
            .' from defTerms where length(trm_Label)>255');

            if($list && count($list)>0){
            
                print htmlspecialchars($db_name).'<br>';
                foreach($list as $id=>$row){
                    print '<div style="padding-left:100px">'.$id.'&nbsp;'.intval($row['chars']).'&nbsp;'.intval($row['len'])
                        .'&nbsp;'.htmlspecialchars($row['trm_Label']).'</div>';    
                }
                
            }
        }
    }
    print '[end report]';    
    
}

//
//
//
function __setTermNameTo255(){

    global $mysqli, $databases; 


    print '[set both trm_Label and trm_NameInOriginatingDB  to varchar(250)]<br>';    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
$query = "ALTER TABLE `defTerms` "
."CHANGE COLUMN `trm_Label` `trm_Label` VARCHAR(250) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank' ,"
."CHANGE COLUMN `trm_NameInOriginatingDB` `trm_NameInOriginatingDB` VARCHAR(250) NULL DEFAULT NULL COMMENT 'Name (label) for this term in originating database'" ;
        
        $res = $mysqli->query($query);
        if(!$res){
            print htmlspecialchars($db_name.' Cannot modify defTerms: '.$mysqli->error);
            return false;
        }else{
            print htmlspecialchars($db_name).'<br>';
        }
    }    
    print '[end update]';    
}

/*

In a correct database eg. core defs, 

2-531 = No
2-532 = Yes

In a problem database:

The Flags vocabulary used in the show headings field, and possibly other fields, has No = 99-5447 and Yes = 99-5446 as valid values, which throws out data entry when the 2-53x terms have been inserted, and any functions which check for 2-53x terms (although the web page headings field checks for both)

The correct 2-53x terms exist in the database:
Replace the local IDs in any vocabulary which uses 99-544x terms with the corresponding 2-53x local IDs
Update any record details which specify the local ID of 99-544x terms with the corresponding local IDs of the 2-53x terms 

The 2-53x terms are not present:
Add the 2-53x terms
Set the concept IDs of the 99-544x terms to 2-53x - these will now be associated with the correct terms
The local IDs specifying the terms in the vocab will now point to the correct terms
The local IDs in record details will continue to point to those terms

*/
function __setTermYesNo(){
    
    global $mysqli, $databases; 
    
    print '[Fix Yes/No terms]<br>';    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        print htmlspecialchars($db_name).' ';    
        
        if(!hasTable($mysqli, 'defTermsLinks')){
            print ' defTermsLinks does not exist<br>';
            continue;
        }
        
        
//get local codes for 2-532, 2-531 and 99-5446(yes) 99-5447 (no) in vocab (99-5445)
        $yes_0 = getLocalCode(2, 532);
        $no_0 = getLocalCode(2, 531);
        
        $yes_1 = getLocalCode(99, 5446);
        $no_1 = getLocalCode(99, 5447);

        if($yes_1>0 || $no_1>0){
            
            print '<b>';
        
        $vocab = getLocalCode(99, 5445);
        
// get all enum fields        
        $enums = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes WHERE dty_Type="enum"'); //, 'intval' snyk does not see it
        $enums = prepareIds($enums);
        $enums = 'dtl_DetailTypeID IN ('.implode(',',$enums).')';
        
        if($yes_1>0){
//replace 99-544x to 2-53x in recDetails
            $yes_0 = intval($yes_0);
            $yes_1 = intval($yes_1);
            $vocab = intval($vocab);
            if($yes_0>0){
                $query = 'UPDATE recDetails SET dtl_Value='.$yes_0.' WHERE dtl_Value='.$yes_1.' AND '.$enums;
                $mysqli->query($query);
    //replace in term links
                $query = 'UPDATE defTermsLinks trl_TermID='.$yes_0.' WHERE trl_TermID='.$yes_1;
                $mysqli->query($query);
    //add references to vocabulary 99-5445       
                if($vocab>0){
                    $query = 'INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES('.$vocab.','.$yes_0.')';
                    $mysqli->query($query);
                }
    //remove old term                            
                $query = 'DELETE FROM defTerms WHERE trm_ID='.intval($yes_1);
                $mysqli->query($query);
                
                
                print ' "yes" replaced';
            }else{
                $query = 'UPDATE defTerms set trm_OriginatingDBID=2 trm_IDInOriginatingDB=532 WHERE trm_ID='.$yes_1;
                $mysqli->query($query);
                if($vocab>0){
                $query = 'INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES('.$vocab.','.$yes_1.')';
                $mysqli->query($query);
                }
                print ' "yes" added';
            }
        }
        
        if($no_1>0){
//replace 99-544x to 2-53x in recDetails
            $no_0 = intval($no_0);
            $no_1 = intval($no_1);
            $vocab = intval($vocab);

            if($no_0>0){
                $query = 'UPDATE recDetails SET dtl_Value='.$no_0.' WHERE dtl_Value='.$no_1.' AND '.$enums;
                $mysqli->query($query);
    //replace in term links
                $query = 'UPDATE defTermsLinks trl_TermID='.$no_0.' WHERE trl_TermID='.$no_1;
                $mysqli->query($query);
    //add references to vocabulary 99-5445       
                if($vocab>0){
                $query = 'INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES('.$vocab.','.$no_0.')';
                $mysqli->query($query);
                }
    //remove old term                            
                $query = 'DELETE FROM defTerms WHERE trm_ID='.$no_1;
                $mysqli->query($query);
                
                print ' "no" replaced';
            }else{
                $query = 'UPDATE defTerms set trm_OriginatingDBID=2 trm_IDInOriginatingDB=531 WHERE trm_ID='.$no_1;
                $mysqli->query($query);
                if($vocab>0){
                $query = 'INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES('.$vocab.','.$no_1.')';
                $mysqli->query($query);
                }
                print ' "no" added';
            }
        }
        
        print '</b><br>';
        
        }else{
            print ' no wrong terms <br>';
        }
        
    }
    
}

function __recreateProceduresTriggers(){
    
    global $mysqli, $databases; 
    
    print 'Recreate procedures and triggers<br>';    
    
    foreach ($databases as $idx=>$db_name){

        print htmlspecialchars($db_name).'<br>';
        
        mysql__usedatabase($mysqli, $db_name);
        
        $res = false;
        if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/addProceduresTriggers.sql', false)){
            $res = true;
            if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/addFunctions.sql', false)){
                $res = true;    
            }else{
                exit;
            }
        }
    }//foreach
    
}

function getLocalCode($db_id, $id){
    global $mysqli;
    $query = 'select trm_ID from defTerms where trm_OriginatingDBID='.$db_id.' and trm_IDInOriginatingDB='.$id;
    return mysql__select_value($mysqli, $query);
}
function getDtyLocalCode($db_id, $id){
    global $mysqli;
    $query = 'select dty_ID from defDetailTypes where dty_OriginatingDBID='.$db_id.' and dty_IDInOriginatingDB='.$id;
    return mysql__select_value($mysqli, $query);
}

//---------------
function __addOtherSources(){
/*    
    global $mysqli;
    mysql__usedatabase($mysqli, 'hdb_judaism_and_rome');
    
    //1. assign HID     
    $query = 'SELECT * FROM import20220906103458';
    $res = $mysqli->query($query);

    if ($res){
        
        $query_match = 'SELECT dtl_RecID FROM recDetails WHERE `dtl_DetailTypeID`=36 AND dtl_Value=';
        $query_update = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_Annotation) VALUES '; //1107
        $query_check = 'SELECT dtl_ID FROM recDetails WHERE dtl_DetailTypeID=1107 AND dtl_RecID=';
        
        
        $not_found1 = array();
        $not_found2 = array();
        $cnt = 0;
        $cnt2 = 0;
        $cnt3 = 0;
        
        while ($row = $res->fetch_row()){
            
            $nid = $row[1];
            
            $rec_ID = mysql__select_value($mysqli, $query_match.$nid);        
            
            if($rec_ID>0){
print '<br>'.htmlspecialchars($row[3]);            
                $nids = explode('|',$row[3]);
                if(count($nids)>0){
                    $values = array();
                    foreach ($nids as $id=>$nid){
                        $os_rec_ID = mysql__select_value($mysqli, $query_match.$nid);        
                        if($os_rec_ID>0){
                            $dtl_ID = mysql__select_value($mysqli, $query_check.$rec_ID.' AND dtl_Value='.$os_rec_ID);
                            if($dtl_ID>0){ //already added?
print '<br>  exist '.$os_rec_ID;                            
                                $cnt2++;
                            }else{
                                $val = '('.$rec_ID.',1107,'.$os_rec_ID.',"art220906")';
                                $values[] = $val;    
print '<br>&nbsp;&nbsp;&nbsp;'.$val;                                
                            }
                        }else{
                            $not_found2[] = $nid;    
                        }
                    }
                    if(count($values)>0){
                        $res2 = $mysqli->query($query_update.implode(',',$values));                        
                        $cnt = $cnt + $mysqli->affected_rows;
                    }
                }
            
            }else{
                $not_found1[] = $nid;
                //echo 'Record not found for NID '.$nid.'<br>';
            }

        }
        $res->close();
        
     
        print '<br>AAAdded '.$cnt;
        print '<br>Already added '.$cnt2;
        print '<br>Not found '.implode(', ',$not_found1);
        print '<br>Other sources not found '.implode(', ',$not_found2);
    }
    
    
*/    
}

function __renameField39(){

    global $mysqli, $databases; 
    
    print 'Rename field #2-39<br>';    
    
    $old1 = 'Representative image or thumbnail';
    $new1 = 'Primary / preferred image';

    $query1 = "UPDATE defDetailTypes SET dty_Name='$new1' WHERE dty_Name='$old1' AND dty_ID=";
    $query3 = "UPDATE defRecStructure SET rst_DisplayName='$new1' WHERE rst_DisplayName='$old1' AND rst_DetailTypeID=";

    $old2 = 'An image of up to 400 pixels wide, used approx. 200 pixels wide to represent the record in search results and other compact listings';
    $new2 = 'The representative image used in record view, in search results and in other compact listings';
    
    $query2 = "UPDATE defDetailTypes SET dty_HelpText='$new2' WHERE dty_HelpText='$old2' AND dty_ID=";
    $query4 = "UPDATE defRecStructure SET rst_DisplayHelpText='$new2' WHERE rst_DisplayHelpText='$old2' AND rst_DetailTypeID=";
    
    foreach ($databases as $idx=>$db_name){

        print htmlspecialchars($db_name);
        
        mysql__usedatabase($mysqli, $db_name);
        
        $dty_ID = intval(getDtyLocalCode(2, 39));
        
        if($dty_ID>0){
            
            //1. rename in `defDetailTypes`
            $mysqli->query($query1.$dty_ID);
            $c1 = $mysqli->affected_rows;
            $mysqli->query($query2.$dty_ID);
            $c2 = $mysqli->affected_rows;
                
            //2. rename in `defRecStructure`
            $mysqli->query($query3.$dty_ID);
            $c3 = $mysqli->affected_rows;
            $mysqli->query($query4.$dty_ID);
            $c4 = $mysqli->affected_rows;
            
            print '&nbsp;&nbsp;'.$c1.' '.$c2.' '.$c3.' '.$c4.'<br>';
            
        }else{
            print '&nbsp;&nbsp;not found<br>';
        }
        
        
        
    }
    
}

//
//
//
function __correctGetEstDate(){

    global $mysqli, $databases; 
    
    //$databases = array('hdb_MPCE_Mapping_Print_Charting_Enlightenment');
    print '__correctGetEstDate<br>';    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
        

        $query = 'SELECT dtl_ID, dtl_Value, dtl_RecID FROM recDetails, recDetailsDateIndex where rdi_DetailID=dtl_ID AND rdi_estMaxDate>2100'; //' and rdi_DetailTypeID=1151';
        $res = $mysqli->query($query);
        if ($res){
            $cnt=0;
            $is_invalid = false;
            while ($row = $res->fetch_row()){
                $dtl_ID = $row[0];
                $dtl_Value = $row[1];
                $rec_ID = $row[2];
                
                $preparedDate = new Temporal( $dtl_Value );
                if($preparedDate && $preparedDate->isValidSimple()){
                    
                    $dtl_NewValue = $preparedDate->getValue(true);
                    
                    $query = 'UPDATE recDetails SET dtl_Value="'.
                                                    $mysqli->real_escape_string($dtl_NewValue).'" WHERE dtl_ID='.$dtl_ID;
                    //$mysqli->query($query);
                    print htmlspecialchars($rec_ID.'  '.$dtl_Value.'  '.$dtl_NewValue).'<br>';
                                    
                    $cnt++;
                    if($cnt>10) break;
                }else{
                    print htmlspecialchars($rec_ID.'  '.$dtl_Value).'<br>';
                    $is_invalid = true;
                }
                
            }
            
            if($cnt>0 || $is_invalid)
                print htmlspecialchars($db_name.'  '.$cnt).'<br>';
        }
        
    }//for
}

//
// converts back to plain
//
function __correctGetEstDate_and_ConvertTemporals_JSON_to_Plain(){
    
    global $mysqli, $databases; 

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        //get version of database        
        $query = 'SELECT sys_dbSubVersion, sys_dbSubSubVersion from sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if($ver['sys_dbSubSubVersion']==13){
        
            print '<br>'.htmlspecialchars($db_name);
        
            // recreate getEstDate function
            if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/getEstDate.sql', false)){

                $cnt = 0;    
                //converts back to plain  
                $query = 'SELECT dtl_ID,dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails, defDetailTypes '
                .'WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "%\"estMinDate\":%"';
                $res = $mysqli->query($query);
            
                if ($res){

                    while ($row = $res->fetch_row()){
                        $dtl_ID = $row[0];
                        $dtl_RecID = $row[1];
                        $dtl_DetailTypeID = $row[2];
                        $dtl_Value = $row[3];
                        $dtl_NewValue = '';
                        $error = '';
                        
                        $value = json_decode($dtl_Value,true);
                        
                        if(is_array($value)){

                            $preparedDate = new Temporal( $dtl_Value );
                            if($preparedDate && $preparedDate->isValid()){
                                $dtl_NewValue = $preparedDate->toPlain();
                                
                                $query = 'UPDATE recDetails SET dtl_Value="'.
                                                $mysqli->real_escape_string($dtl_NewValue).'" WHERE dtl_ID='.intval($dtl_ID);
                                $mysqli->query($query);
                                
                                $cnt++;
                            }
                        }
                        

                    }//while  
                    
                    print ' '.$cnt;
                }
            }
        }
    }
    
}

//
//
//
function __delete_OLD_RecType_And_Term_Icons_Folders(){
    global $mysqli, $databases; 
    
    echo '__delete_OLD_RecType_And_Term_Icons_Folders<br>';

    foreach ($databases as $idx=>$db_name){

        $cnt = 0;
        
        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/rectype-icons/';
        if(file_exists($old_path)){
            folderDelete($old_path, true);    
            $cnt++;
        }
        

        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/term-images/';
        if(file_exists($old_path) && $db_name!='digital_harlem'){
            folderDelete($old_path, true);    
            $cnt++;
        }
        

        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/term-icons/';
        if(file_exists($old_path)){
            folderDelete($old_path, true);    
            $cnt++;
        }
        

        echo htmlspecialchars($db_name.'  '.$cnt).'<br>';
    }
    
}

//
//
//
function __copy_RecType_And_Term_Icons_To_EntityFolder(){
    global $mysqli, $databases; 
    
    echo '__copy_RecType_And_Term_Icons_To_EntityFolder<br>';
    
    return;

    
    if(!defined('HEURIST_FILESTORE_ROOT')) return;


    foreach ($databases as $idx=>$db_name){

        //mysql__usedatabase($mysqli, $db_name);
        
        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/rectype-icons/';
        
        $path = HEURIST_FILESTORE_ROOT . $db_name . '/entity/defRecTypes/';

        folderCreate($path, false);
        folderCreate($path.'icon/', false);
        folderCreate($path.'thumbnail/', false);
        
        $content = folderContent($old_path);
        
        $cnt = 0;
        $cnt2 = 0;
        
        foreach ($content['records'] as $object) {
            if ($object[1] != '.' && $object[1] != '..') {
                
                $rty_id = substr($object[1],0,-4);
                
                if(intval($rty_id)>0 || $rty_id=='0'){
                    $old_icon = $old_path.$object[1];
                    
                    if(file_exists($old_icon)){
                        
                        $ext = substr($object[1],-3);
                    
                        //if icon exists skip
                        list($fname, $ctype,$url) = resolveEntityFilename('defRecTypes', $rty_id, 'icon', $db_name, $ext);
                        if($fname==null){
                        
                            //copy icon
                            $new_icon = $path.'icon/'.$object[1];
                            copy($old_icon, $new_icon);
                            
                            $cnt++;
                        }
                    }       
                    
                    //copy thumb
                    $old_thumb = $old_path.'thumb/th_'.$object[1];
                    if(file_exists($old_thumb)){
                        $new_thumb = $path.'thumbnail/'.$object[1];
                        if(!file_exists($new_thumb)){
                            copy($old_thumb, $new_thumb);
                            $cnt2++;
                        }
                    }
                    
                }
            }
        }

        echo $db_name.'  '.$cnt.'  '.$cnt2.'<br>';
        //remove old folder
        //folderDelete($old_path, true);
        
        //thumbnails
        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/term-images/';
        
        $path = HEURIST_FILESTORE_ROOT . $db_name . '/entity/defTerms/';
        
        $content = folderContent($old_path);

        folderCreate($path, false);
        folderCreate($path.'thumbnail/', false);
        $cnt = 0;
        
        foreach ($content['records'] as $object) {
            if ($object[1] != '.' && $object[1] != '..') {
        
                $trm_id = substr($object[1],0,-4);
                
                if(intval($trm_id)>0 || $trm_id=='0'){
                    $old_icon = $old_path.$object[1];
                    
                    if(file_exists($old_icon)){
                        
                        $ext = substr($object[1],-3);
                    
                        //if icon exist skip
                        list($fname, $ctype,$url) = resolveEntityFilename('defTerms', $trm_id, 'icon', $db_name, $ext);
                        if($fname!=null) continue;
                        
                        $new_icon = $path.$object[1];
                        if(file_exists($new_icon)){
                            continue;
                        }
                        //copy icon
                        copy($old_icon, $new_icon);
                        
                        //copy thumb
                        copy($old_icon, $path.'thumbnail/'.$object[1]);
                        
                        $cnt++;
                    }       
                }
            }
        }        

if($cnt>0) echo $db_name.'   terms:'.$cnt.'<br>';
        
        //remove old folder
        //folderDelete($old_path, true);
        
        

    }        
}


//
//
//
function __updateDatabases_To_V14($db_process){
    
    global $system, $mysqli, $databases; 
    
    $cnt_db = 0;
    $cnt_db_old = 0;
    $skip_work = true;
  
    /*
    $is_action = ($db_process!=null);
    
    if($db_process=='all'){
        $db_process = null;
    }
    
    */

    foreach ($databases as $idx=>$db_name){
        
        if($db_name=='') continue;
        
        if($db_process!=null){
            $db_name = $db_process;
        }
        /*
        if($db_name=='misha_cruches_gallo_romaines'){
            $skip_work = false;
            //continue;
        }else if($skip_work){
            continue;
        }*/

        if( !$system->set_dbname_full($db_name, true) ){
                $response = $system->getError();    
                print '<div><h3 class="error">'.$response['message'].'</h3></div>';
                break;
        }
        
        mysql__usedatabase($mysqli, $db_name);

        //get version of database        
        $query = 'SELECT sys_dbSubVersion, sys_dbSubSubVersion from sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        

        //statistics
        $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value!=""';
        $cnt_dates = intval(mysql__select_value($mysqli, $query));

        $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "|VER=1%"';
        $cnt_fuzzy_dates = intval(mysql__select_value($mysqli, $query));
        
        $cnt_index = 0;
        $cnt_fuzzy_dates2 = 0;
        
        $is_big = ($cnt_dates>100000);
        
        if($is_big){
            $cnt_dates = '<b>'.$cnt_dates.'</b>';
        }
        
        if($ver['sys_dbSubSubVersion']>12){
            $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex';
            $cnt_index = intval(mysql__select_value($mysqli, $query));

            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "%estMinDate%"';
            $cnt_fuzzy_dates2 = intval(mysql__select_value($mysqli, $query));
            
            print '<br>'.htmlspecialchars($db_name).'  v.'.$ver['sys_dbSubSubVersion'].'  '.$cnt_dates
                .($cnt_dates<>$cnt_index?'<span style="color:red">':'<span>')
                .'  index='.$cnt_index.' ( '.($cnt_fuzzy_dates>0?'<b>'.$cnt_fuzzy_dates.'</b>':'0').','.$cnt_fuzzy_dates2.' )</span>';
        }else{
            $cnt_db_old++;
            print '<br>'.htmlspecialchars($db_name).'  v'.($ver['sys_dbSubVersion']<3?'<b>'.$ver['sys_dbSubVersion'].'</b>':'')
            .'.'.$ver['sys_dbSubSubVersion'].'  '.$cnt_dates.'  ( '.$cnt_fuzzy_dates.' )';
            
            if($ver['sys_dbSubVersion']<3){
                continue;   
            }
            
            if(!updateDatabseTo_v1_3_12($system)){
                $response = $system->getError();    
                print '<div><h3 class="error">'.$response['message'].'</h3></div>';
                break;
            }
        }
        
        if(true && ($db_process!=null || 
            (!$is_big && ($ver['sys_dbSubSubVersion']<=13 || ($cnt_dates>0 && $cnt_index*100/$cnt_dates<94) ))))
        {
            print '<br>';
            if(recreateRecDetailsDateIndex($system, true, true)){
            
            }else{
                $response = $system->getError();    
                print '<div><h3 class="error">'.$response['message'].'</h3></div>';
                break;
            }
        }
        
        $cnt_db++;
        
        //if($db_name=='bnf_lab_musrdm_test') break;
        if($db_process!=null){
            break;   
        }
    }    
    
    print '<br><br>'.$cnt_db_old.'  '.$cnt_db;
    
}

//
//
//
function __removeDuplicationValues(){

    global $system, $mysqli, $databases; 
    
    $cnt = 0;
    /*
    foreach ($databases as $idx=>$db_name){
        if($db_name=='') continue;
    }
    */
    
    //mysql__usedatabase($mysqli, 'MBH_Manuscripta_Bibliae_Hebraicae');
    //mysql__usedatabase($mysqli, 'osmak_9c');
    
    $query = 'SELECT dtl_RecID, dtl_DetailTypeID, dtl_Value, count(dtl_Value) as cnt '.
    'FROM recDetails WHERE dtl_Geo IS NULL AND dtl_UploadedFileID IS NULL '.
    'GROUP BY dtl_RecID, dtl_DetailTypeID, dtl_Value HAVING cnt>1';

    $res = $mysqli->query($query);
    
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    
    while (($row = $res->fetch_row())) {
        
        $q = 'DELETE FROM recDetails WHERE dtl_RecID='.intval($row[0]).' AND dtl_DetailTypeID='.intval($row[1])
            .' AND dtl_Value="'.$mysqli->real_escape_string($row[2])
            .'" LIMIT '.(intval($row[3])-1);
        $mysqli->query($q);
        $cnt = $cnt + $mysqli->affected_rows;  
    }
    $res->close();    
    
    print 'DONE. Removed '.$cnt.' duplications';
}

//
//
//
function __listOfAdminUsers(){

    global $system, $mysqli, $databases; 

    $mind = '9999';
    
    foreach ($databases as $idx=>$db_name){
    
        if($db_name=='') continue;
        
        mysql__usedatabase($mysqli, $db_name);

        //get version of database        
        $query = "SELECT ugr_Name, ugr_eMail, ugr_Modified FROM sysUGrps where  ugr_FirstName = 'sys' AND ugr_LastName = 'admin'";
        $vals = mysql__select_row_assoc($mysqli, $query);
        if($vals){
            if(strpos($vals['ugr_Modified'],'2019')!==0 && $vals['ugr_Modified']<$mind) $mind = $vals['ugr_Modified'];
            echo  '<br>'.htmlspecialchars($db_name.'   '.$vals['ugr_Modified']);//.'   '.$vals['ugr_Name'].'  '.$vals['ugr_eMail'];
        }
    }
    print '<br>Earliest: '.$mind.'<br>END';
}


function hexToString($str){return chr(hexdec(substr($str, 2)));}
//
//
//
function __convertTustep(){
     global $system, $mysqli, $databases; 

/*
    print '<xmp>'.$s.'</xmp>';
    print '<br>';


    $m = html_entity_decode($s, ENT_QUOTES|ENT_HTML401, 'UTF-8' );

    // Convert the codepoints to entities
    //$str = preg_replace("/\\\\u([0-9a-fA-F]{4})/", "&#x\\1;", $str);
    
    //preg_replace_callback("/(@[^\0-\x]@u)/isU", function($n) { return hexToString($n[0] ); }, $s);

    $matches = array();
    preg_match_all("/\&[0-9a-zA-Z]+;/", $s, $matches);

    $matches2 = array();
    preg_match_all("/\&#x[0-9a-fA-F]{4};/", $s, $matches2);

    
    print '<xmp>'.$m.'</xmp>';
    print '<br>';
    //print '<xmp>'.$s.'</xmp>';
    print '<br>';
    print print_r($matches, true);
    print '<br>';
    print print_r($matches2, true);
    
    //print '<xmp>'.htmlspecialchars_decode($s).'</xmp>';
*/    
    //print print_r(get_html_translation_table(HTML_ENTITIES),true);
$tustep_to_html = array(
'&amp;' =>'#%#%#',
'^u'    =>'&uuml;',
'#.s'   =>'&#x017F;',
'%/u'   =>'&uacute;',
//'_'     =>'&nbsp;',
"^'"    =>'&lsquo;',
'^-'    =>'&mdash;',
'#(MPU)'=>'&#x00B7;',
'%/U'   =>'&Uacute;',
'#(MKR)'=>'&#x00D7;',
'#.>'   =>'&ldquo;',
'#.<'   =>'&rdquo;',
'%<u'   =>'&ucirc;',
'^o'    =>'&ouml;',
'^s'    =>'&szlig;',
'^a'    =>'&auml;',
'^U'    =>'&Uuml;',
'#.z'   =>'&#x0292;',
'%:w'   =>'&#x1E85;',
'#.>'   =>'&#x00BB;',
'#.<'   =>'&#x00AB;',
'%:e'   =>'&#x00EB;',
'%:i'   =>'&#x00EF;',
'^O'    =>'&Ouml;',
'#.:'   =>'&rsaquo;',
'#.;'   =>'&lsaquo;',
"^'^'^'"=>'&#x2037;',
"''"    =>'&#x2034;',
//'_'     =>'&thinsp;',
"#.'#.'"=>'&#x201C;',
//"#.'#.'"=>'',
'#.^o'  =>'&#x0153;',
'%/e'   =>'&eacute;',
'%<e'   =>'&ecirc;',
'%/o'   =>'&oacute;',
'%<o'   =>'&ocirc;',
'%<u'   =>'&ucirc;',
'%<a'   =>'&acirc;',
'%<i'   =>'&icirc;',
'#.^a'  =>'&aelig;',
'^.'    =>'&middot;',
'%/w'   =>'&x#1E83;',
'%>o'   =>'&#x014F;',
'%<A'   =>'&Acirc;',
'%<E'   =>'&Ecirc;',
'%<U'   =>'&Ucirc;',
'%;;e'  =>'&#x0119;',
'%;;a'  =>'&#x0115;',
'%;;e'  =>'&#x0119;',
'%<y'   =>'&#x0177;',
'%<w'   =>'&#x0175;',
'%<O'   =>'&Ocirc;',
'^A'    =>'&Auml;',
'%<j'   =>'&#x0135;',
'%/a'   =>'&aacute;',
'%-a'   =>'&#x0101;',
'%-e'   =>'&#x0113;',
'%-i'   =>'&#x012B;',
'%-o'   =>'&#x014D;',
'%-u'   =>'&#x016B;',
'%.w'   =>'&#x1E87;',
'#.l'   =>'&#x0197;',
'#.^A'  =>'&AElig;',
'^+'    =>'&dagger;',
//','     =>'&sbquo;',
'%/y'   =>'&yacute;',
'%/Y'   =>'&Yacute;',
'%<I'   =>'&Icirc;',
'#.^O'  =>'&OElig;',
'#;er'  =>'&re;',
'%;e'   =>'&#x0229;',
'>llll' =>'&gt;',
'<IIII' =>'&lt;',
'%-H'   =>'&#x0048;&#x0304;', //  '&Hmacr;',
'#;e#.^a'=>'&aelige;',
'#;vw'  =>'&wv;',  //????
'%)u'   =>'&uslenis;',
'%?n'   =>'&ntilde;',
'#;oo'  =>'&oo;',
'%>a'   =>'&ahacek;',
'%/i'   =>'&iacute;',
'%a'    =>'&agrave;',
'%e'    =>'&egrave;',
'%i'    =>'&igrave;',
'%o'    =>'&ograve;',
'%u'    =>'&ugrave;',
'%:y'   =>'&ytrema;',
'#;iv'  =>'&vi;', //????
'#;iu'  =>'&ui;',
'%-y'   =>'&ymacr;',
'%..d'  =>'&dundpunkt;',
'%>c'   =>'&chacek;',
'%/'    =>'&#180;',
'#.ä'   =>'&#230;',
'#.ö'   =>'&oelig;');

$html_to_hex = array(
'&Hmacr;' =>  '&#x0048;&#x0304;'
);

/* test
    $s = '<p>#.ö   &#163; > %/Y#;iv < &#x017F;  &longs; &Ouml;  &#x201E; &ldquo;  &#x201C; &rdquo; &#x0153; &oelig; &Hmacr;  &#x0048;&#x0304;   &wv;</p>';

    print '<xmp>'.$s.'</xmp>';
    print '<br>';
*/    
    $cnt = 0;
    $missed = array();     //dty_Type="freetext" OR blocktext
    $txt_field_types = mysql__select_list2($mysqli, 'SELECT dty_ID FROM defDetailTypes WHERE dty_Type="freetext" OR dty_Type="blocktext"','intval');
    $txt_field_types = prepareIds($txt_field_types); //snyk does see intval in previous function  
   
    $update_stmt = $mysqli->prepare('UPDATE recDetails SET dtl_Value=? WHERE dtl_ID=?');
    $keep_autocommit = mysql__begin_transaction($mysqli);    
    $isOk = true;
   
    // dtl_RecID=18 AND   dtl_RecID=85057 AND 
    //     
    $query = 'SELECT dtl_ID, dtl_Value, dtl_DetailTypeID, dtl_RecID FROM recDetails, Records '
    .'WHERE dtl_RecID=rec_ID AND rec_RecTypeID NOT IN (51,52) AND dtl_DetailTypeID in ('.implode(',',$txt_field_types).')';
    //.' AND rec_ID IN (1593,4060 ,8603, 11704, 22025, 22491, 25393 , 25570, 28848, 28959    )';     
    $res = $mysqli->query($query);
    if ($res){
        while ($row = $res->fetch_row()){
            
            //skip json content
            if(strpos($row[1],'{')===0 || strpos($row[1],'[')===0){
                $r = json_decode($row[1],true);
                if(is_array($r)) continue;
            }
    
            $s = ''.$row[1];

            //1. Convert TUSTEP to html entities
            foreach ($tustep_to_html as $tustep=>$entity) {
                if(strpos($s,$tustep)!==false){
                    $s = str_replace($tustep, $entity, $s);
                }
            }
            
            //2. Decode HTML entities    
            $m = html_entity_decode($s, ENT_QUOTES|ENT_HTML401, 'UTF-8' );
            
            $m2 = str_replace('#%#%#', '&amp;', $m); //convert back

            //3. List unrecognized
            if($m2!=$row[1]){
                // remove remarks to see raw output
                /*  remarked due snyk security report
                print $row[3].' '.$row[0].'<xmp>'.$row[1].'</xmp>';
                print '<xmp>'.$m2.'</xmp>';
                */
                $cnt++;
                //if($cnt>1000) break;
            }
            
            //find missed unconverted HTML entities
            $matches = array();
            preg_match_all("/\&[0-9a-zA-Z]+;/", $m, $matches);

            if(is_Array(@$matches[0]) && count($matches[0])>0){
                    $missed = array_merge_unique($missed, $matches[0]);            
            }
            
            $m = str_replace('#%#%#', '&amp;', $m); //convert back
            
            //update in database
            /*
            $update_stmt->bind_param('si', $m, $row[0]);
            $res33 = $update_stmt->execute();
            if(! $res33 )
            {
                $isOk = false;
                print '<div class="error" style="color:red">Record #'.$row[3].'. Cannot replace value in record details. SQL error: '.$mysqli->error.'</div>';
                $mysqli->rollback();
                break;
            }
            */
            
        }//while
        $res->close();
    }
    
    if($isOk){
        $mysqli->commit();  
    }
    if($keep_autocommit===true) $mysqli->autocommit(TRUE);

    print '<br>Replaced in '.$cnt.' fields';
    
    print '<br>Missed:';
    print print_r($missed, true);
    
    print '<xmp>'.print_r($missed, true).'</xmp>';
    
    
}

//
//
//
function __findRDF(){
    global $system, $mysqli, $databases; 

    foreach ($databases as $idx=>$db_name){
    
        if($db_name=='') continue;
        
        mysql__usedatabase($mysqli, $db_name);
        
        $r1 = intval(mysql__select_value($mysqli, 'select count(rty_ID) from defRecTypes'));
        $d1 = intval(mysql__select_value($mysqli, 'select count(dty_ID) from defDetailTypes'));
        //$s1 = mysql__select_value($mysqli, 'select count(rst_ID) from defRecStructure');
        $t1 = intval(mysql__select_value($mysqli, 'select count(trm_ID) from defTerms'));
        
        $r2 = intval(mysql__select_value($mysqli, 'select count(rty_ID) from defRecTypes where rty_ReferenceURL!="" and rty_ReferenceURL is not null'));
        $d2 = intval(mysql__select_value($mysqli, 'select count(dty_ID) from defDetailTypes where dty_SemanticReferenceURL!="" and dty_SemanticReferenceURL is not null'));
        //$s2 = mysql__select_value($mysqli, 'select count(rst_ID) from defRecStructure where rst_SemanticReferenceURL!="" and rst_SemanticReferenceURL is not null');
        $t2 = intval(mysql__select_value($mysqli, 'select count(trm_ID) from defTerms where trm_SemanticReferenceURL!="" and trm_SemanticReferenceURL is not null'));

        if($r2>0 && $d2>1){
            if($r2/$r1>0.2 || $d2>50){
                $s = 'bold';
            }else{
                $s = 'normal';
            }
            
            $rec_cnt2 = intval(mysql__select_value($mysqli, 'select count(rec_ID) from Records, defRecTypes '
                .'where rty_ID=rec_RecTypeID and rty_ReferenceURL!=""'));

            $rec_cnt1 = intval(mysql__select_value($mysqli, 'select count(rec_ID) from Records'));
                
            $dtl_cnt = intval(mysql__select_value($mysqli, 'select count(dtl_ID) from recDetails, defDetailTypes '
                .'where dty_ID=dtl_DetailTypeID and dty_SemanticReferenceURL!=""'));
            
            echo  "<div style='font-weight:$s'>$db_name rty: $r1/$r2&nbsp;&nbsp;&nbsp;dty: $d1/$d2 &nbsp;&nbsp;&nbsp;trm:$t1/$t2 &nbsp;&nbsp;&nbsp;Records:$rec_cnt1/$rec_cnt2 $dtl_cnt</div>";     //$s1/$s2 
        }
    }
    print '<br>END';
}

function __dropBkpDateIndex(){
    
    
    global $system, $mysqli, $databases; 

    foreach ($databases as $idx=>$db_name){
    
        if($db_name=='') continue;

        $db_name = htmlspecialchars($db_name);        
        
        mysql__usedatabase($mysqli, $db_name);
        
        if(hasTable($mysqli, 'bkpDetailsDateIndex')){
            $mysqli->query('DROP TABLE bkpDetailsDateIndex');
            print $db_name.'<br>';
        }
    }
    print '<br>END';
}
?>
