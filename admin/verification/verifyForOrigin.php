<?php
    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * This is fix for bug of database creation (happened between 2017-09-22 and 2017-10-16)
    * New database has empty pointer constraints for resouce fields
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
    
    $_REQUEST['db'] = 'Heurist_Bibliographic';

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__).'/../../common/php/utilsMail.php');
    require_once('valueVerification.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (!is_admin()) {
        print "Sorry, you need to be a database owner to be able to modify the database structure";
        return;
    }
    
    $is_action = (@$_REQUEST['action']==1);
    $filter = @$_REQUEST['filter'];
    if(!$filter) $filter = 'hdb_johns';

    //1. find all database
    $query = 'show databases';

    $res = mysql_query($query);      
    if (!$res) {  print $query.'  '.mysql_error();  return; }
    $databases = array();
    while (($row = mysql_fetch_row($res))) {
        if( strpos($row[0], 'hdb_')===0 && ($filter=="all" || strpos($row[0], $filter)===0)){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }
    
    //origin database and record types to check
    $origin_dbs = array('hdb_Heurist_Bibliographic'=>array('rty_RecTypeGroupID'=>array(7,9),'rty_ID'=>array()),
                        'hdb_Heurist_Core_Definitions'=>array('rty_RecTypeGroupID'=>array(8),'rty_ID'=>array()));
    
    $rty_CodesToCheck = array(); //rty ids in origin db to be checked
    $rty_Names = array();   //code -> name
    
    $fields = array();      //per record type
    $constraints = array(); //per detail
    $terms_codes = array(); //per detail
    
    foreach ($origin_dbs as $db_name=>$ids){
        
        mysql_connection_select($db_name);
        
        resetGlobalTermsArrays();
        $all_terms = getTerms();
        $ti_dbid = $all_terms['fieldNamesToIndex']['trm_OriginatingDBID'];
        $ti_oid = $all_terms['fieldNamesToIndex']['trm_IDInOriginatingDB'];
        
        //get record structure for origin
        $where = null;
        if(count($ids['rty_RecTypeGroupID'])>0){
            $where = 'rty_RecTypeGroupID in ('.implode(',',$ids['rty_RecTypeGroupID']).')';
        }
        if(count($ids['rty_ID'])>0){
            if($where) $where = $where.' OR ';
            $where = 'rty_ID in ('.implode(',',$ids['rty_ID']).')';
        }
        if($where==null){
            print 'Record types to be checked not defined for '.$db_name;
            continue;
        }

        //all codes in this database    
        $rty_Codes = mysql__select_assoc('defRecTypes', 'rty_ID', 
                'CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code', '1=1');
        $rty_Names_temp = mysql__select_assoc('defRecTypes', 'CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code',
                'rty_Name', '1=1');
                
        $rty_IDs = mysql__select_array('defRecTypes', 'rty_ID', $where);
        $rty_IDs_ToCheck = array();
        
        foreach($rty_IDs as $rty_ID){
            $code = $rty_Codes[$rty_ID];
            if(!@$rty_CodesToCheck[$code]){ //not already was in previous database
                array_push($rty_IDs_ToCheck, $rty_ID); 
                array_push($rty_CodesToCheck, $code); 
                $rty_Names[$code] = $rty_Names_temp[$code]; 
            }
        }

        
        $query = 'select rst_RecTypeID, dty_Type, rst_DisplayName, dty_OriginatingDBID, dty_IDInOriginatingDB, '
        .'dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree '
        .'from defRecStructure, defDetailTypes  where dty_ID=rst_DetailTypeID AND rst_RecTypeID in ('
                .implode(',',$rty_IDs_ToCheck).')';
    
        $res = mysql_query($query);
        if (!$res) {  print $query.'  '.mysql_error();  return; }
        
        //gather fields ids, pointer constraints, vocabs/terms - as concept codes
        while (($row = mysql_fetch_assoc($res))) {
            
             $rty_Code = $rty_Codes[$row['rst_RecTypeID']];
             $dty_Code = $row['dty_OriginatingDBID'].'-'.$row['dty_IDInOriginatingDB'];
            
             if(!@$fields[$rty_Code]) $fields[$rty_Code] = array();
            
              /*if($row['rst_RecTypeID']==17){
                print $rty_Code.' '.$dty_Code.'  '.$row['rst_DisplayName'].'<br>';
              }*/
            
             $fields[$rty_Code][$dty_Code] = $row['rst_DisplayName'];
             
             if($row['dty_Type']=='resource' && $row['dty_PtrTargetRectypeIDs'] && !@$constraints[$dty_Code]){
                 //convert constraints to concept codes
                 $rids = explode(',', $row['dty_PtrTargetRectypeIDs']);
                 $codes = array();
                 //$codes[] = $row['dty_PtrTargetRectypeIDs'];
                 foreach($rids as $rty_id){
                     $codes[] = $rty_Codes[$rty_id];  
                 }
                 //keep constraints
                 $constraints[$dty_Code] = $codes;
                 
             } else if (($row['dty_Type']=='enum' || $row['dty_Type']=='relmarker') && !@$terms_codes[$dty_Code]){
                 
                 $domain = $row['dty_Type']=='enum'?'enum':'relation';
                 
                 $terms = getAllowedTerms($row['dty_JsonTermIDTree'], null, $row['dty_ID']);
                 $codes = array();
                 foreach($terms as $trm_id){
                     $term = $all_terms['termsByDomainLookup'][$domain][$trm_id];
                     $codes[] = $term[$ti_dbid].'-'.$term[$ti_oid];  
                 }
                 //get concept codes
                 $terms_codes[$dty_Code] = $codes;
             }
            
        }
    
    }//for origin    
    
   /* 
    print 'rectypes '.print_r($rty_CodesToCheck, true);
    
    print '<br><br>FIELDS: '.print_r($fields, true);
    
    print '<br><br>POINTERS: '.print_r($constraints, true);

    
    print '<br><br>TERMS: '.print_r($terms_codes, true);
    
    exit;
    */
    //-----------------------------------------------
    
    foreach ($databases as $idx=>$db_name){
        
        mysql_connection_select($db_name);
       
        print "<h4>db = $db_name</h4>";
        
        resetGlobalTermsArrays();        
        $all_terms = getTerms(false);
        $constraints2 = array(); //per detail
        $terms_codes2 = array(); //per detail
        
        //find all rty-codes
        $rty_Codes2 = mysql__select_assoc('defRecTypes', 'rty_ID', 
                'CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code', '1=1');
        //find all term codes
        $trm_Codes2 = mysql__select_assoc('defTerms', 'trm_ID', 
                'CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB) as trm_Code', '1=1');
        

        //loop by rectypes        
        foreach ($rty_CodesToCheck as $index=>$rty_Code){
   
       
            if(array_search($rty_Code, $rty_Codes2)==false) continue;//there is no such record type
            
            list($db_id, $orig_id) = explode('-',$rty_Code);
            
            //check for unexpected concept code by name
            $rty_Name = $rty_Names[$rty_Code];
            $query = 'select rty_OriginatingDBID, rty_IDInOriginatingDB '
            .' FROM defRecTypes WHERE rty_Name="'.mysql_real_escape_string($rty_Name).'"';
            $res = mysql_query($query);
            if (!$res) {  print $query.'  '.mysql_error();  return; }
            $row = mysql_fetch_row($res);
            if($row){
                if($row[0]!=$db_id || $row[1]!=$orig_id){
                   print "<p style='padding-left:20px'>name = $rty_Name : Unexpected concept ID ".$row[0].'-'.$row[1].'</p>';
                }
            }
            

            $query = 'select rst_RecTypeID, dty_Type, rty_Name, dty_Name, dty_OriginatingDBID, dty_IDInOriginatingDB, '
            .'dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree '
            .'from defRecStructure, defDetailTypes, defRecTypes '
            .'where rty_ID=rst_RecTypeID AND dty_ID=rst_DetailTypeID AND rty_OriginatingDBID='
            .$db_id.' AND rty_IDInOriginatingDB='.$orig_id;
        
            $res = mysql_query($query);
            if (!$res) {  print $query.'  '.mysql_error();  return; }
            
            $fields2 = array();      //per record type
            
            //gather fields ids, pointer constraints, vocabs/terms - as concept codes
            while (($row = mysql_fetch_assoc($res))) {

                 $rty_Name = $row['rty_Name'];
                 $dty_Code = $row['dty_OriginatingDBID'].'-'.$row['dty_IDInOriginatingDB'];
                 
                 array_push($fields2, $dty_Code);
                
                 if($row['dty_Type']=='resource' && $row['dty_PtrTargetRectypeIDs'] && !@$constraints2[$dty_Code]){
                     //convert constraints to concept codes
                     $rids = explode(',', $row['dty_PtrTargetRectypeIDs']);
                     $codes = array();
                     //$codes[] = $row['dty_PtrTargetRectypeIDs'];
                     foreach($rids as $r_id){
                         $codes[] = $rty_Codes2[$r_id];  
                     }
                     //keep constraints
                     $constraints2[$dty_Code] = $codes;
                     
                 } else if (($row['dty_Type']=='enum' || $row['dty_Type']=='relmarker') && !@$terms_codes2[$dty_Code]){
                     
                     $domain = $row['dty_Type']=='enum'?'enum':'relation';
                     
                     $terms = getAllowedTerms($row['dty_JsonTermIDTree'], null, $row['dty_ID']);
                     
                     $codes = array();
                     if(is_array($terms)){
                         foreach($terms as $trm_id){
                             if(@$all_terms['termsByDomainLookup'][$domain][$trm_id]){
                                $term = $all_terms['termsByDomainLookup'][$domain][$trm_id];
                                $codes[] = $term[$ti_dbid].'-'.$term[$ti_oid];  
                             }else{
                                print '<p>Term does not exist '.$trm_id.' in field '.$row['dty_ID'].'  '.$row['dty_Name'].' ('.$row['dty_JsonTermIDTree'].')</p>';
                             }
                             
                         }
                     }else{
                         print '<p>Can\'t parse '.$row['dty_JsonTermIDTree'].' for '.$row['dty_ID'].'  '.$row['dty_Name'].'</p>';
                     }
                     //get concept codes
                     $terms_codes2[$dty_Code] = $codes;
                 }
            }//for fields   
            
            //analyze
            
            if(count($fields2)===0) continue; //there is no such record type
            
            print "<p style='padding-left:20px'>id = ".$rty_Code.'  '.$rty_Names[$rty_Code]. 
                (($rty_Names[$rty_Code]!=$rty_Name)?' (in this database: '.$rty_Name.')':'').'</p>';
            
            //1. check fields 
            $missing = array();
            foreach($fields[$rty_Code] as $f_code=>$f_name){
                if(array_search($f_code, $fields2)===false){ //not found
                    array_push($missing, $f_code.'  '.$f_name);
                }
            }
            if(count($missing)>0){
                print "<p style='padding-left:40px'>missing fields ".implode(',',$missing)."</p>"; 
            }
            //2. check constraints
            foreach($constraints as $dty_Code=>$codes){
                if(@$fields[$rty_Code][$dty_Code]){ //exists in original recctype and in this db
                    $missing = array();
                    $missing2 = array();
                    foreach($codes as $rty_code){
                        if(!is_array(@$constraints2[$dty_Code]) || array_search($rty_code, $constraints2[$dty_Code])===false){ //not found or unconstrained
                            array_push($missing, $rty_code.'  '.$rty_Names[$rty_code] );
                        }
                        if(array_search($rty_code, $rty_Codes2)===false){
                            array_push($missing2, $rty_code.'  '.$rty_Names[$rty_code] );
                        }
                    }        
                    if(count($missing)>0){
                       //print print_r($fields[$rty_Code],true).'<br>';  
                        
                       print "<p style='padding-left:40px'>field ".$dty_Code.' '.$fields[$rty_Code][$dty_Code]
                                    .': missing constraint record types '.implode(',',$missing).'</p>';
                    }
                    //check rectype presence in this db
                    if(count($missing2)>0){
                       print "<p style='padding-left:40px'>field ".$dty_Code.' '.$fields[$rty_Code][$dty_Code]
                                    .': missing record types '.implode(',',$missing2).'</p>';
                    }
                            
                }
            }//foreach constaints
            
            //3. check terms 
            foreach($terms_codes as $dty_Code=>$codes){
                if(@$fields[$rty_Code][$dty_Code]){ //exists in original recctype and in this db
                    $missing = array();
                    $missing2 = array();
                    foreach($codes as $trm_code){
                        if(!is_array(@$terms_codes2[$dty_Code]) || array_search($trm_code, $terms_codes2[$dty_Code])===false){ //not found
                            array_push($missing, $trm_code );
                        }
                        if(array_search($trm_code, $trm_Codes2)===false){ //all codes of terms in this db
                            array_push($missing2, $trm_code );
                        }
                    }        
                    if(count($missing)>0){
                       print "<p style='padding-left:40px'>field ".$dty_Code.' '.$fields[$rty_Code][$dty_Code]
                                    .': missing terms in field list '.implode(',',$missing).'</p>';
                    }
                    //check rectype presence in this db
                    if(count($missing2)>0){
                       print "<p style='padding-left:40px'>field ".$dty_Code.' '.$fields[$rty_Code][$dty_Code]
                                    .': missing terms '.implode(',',$missing2).'</p>';
                    }
                            
                }
            }//foreach terms
            
                 
        }//for record types
    }//while  databases
?>
