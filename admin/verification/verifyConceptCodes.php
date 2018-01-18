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
    * Verifies duplications for concept code in rectypes, fieldtypes and terms
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/utilsMail.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (!is_admin()) {
        print "Sorry, you need to be a database owner to be able to modify the database structure";
        return;
    }
    
    //1. find all database
    $query = 'show databases';

    $res = mysql_query($query);
    if (!$res) {  print $query.'  '.mysql_error();  return; }
    $databases = array();
    while (($row = mysql_fetch_row($res))) {
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }
    
    foreach ($databases as $idx=>$db_name){
        
        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES
        
        $query = 'SELECT rty_OriginatingDBID, rty_IDInOriginatingDB, count(rty_ID) as cnt FROM '
            .$db_name.'.defRecTypes WHERE  rty_OriginatingDBID>0 AND rty_IDInOriginatingDB>0 '
            .' GROUP BY rty_OriginatingDBID, rty_IDInOriginatingDB HAVING cnt>1';
        
        $res = mysql_query($query);
        if (!$res) {  print $query.'  '.mysql_error();  return; }
        
        while (($row = mysql_fetch_row($res))) {

               $is_found = true;
                
               $query = 'SELECT rty_ID, rty_Name, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB), rty_NameInOriginatingDB FROM '
                .$db_name.'.defRecTypes WHERE  rty_OriginatingDBID='.$row[0].' AND rty_IDInOriginatingDB='.$row[1]
                .' ORDER BY rty_OriginatingDBID, rty_IDInOriginatingDB';
                
               $res2 = mysql_query($query);               
               if (!$res2) {  print $query.'  '.mysql_error();  return; }
               while (($row2 = mysql_fetch_row($res2))) {
                      array_push($rec_types, $row2);
               }
        }

        //FIELD TYPES

        $query = 'SELECT dty_OriginatingDBID, dty_IDInOriginatingDB, count(dty_ID) as cnt FROM '
            .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID>0 AND dty_IDInOriginatingDB>0 '
            .' GROUP BY dty_OriginatingDBID, dty_IDInOriginatingDB HAVING cnt>1';
        
        $res = mysql_query($query);
        if (!$res) {  print $query.'  '.mysql_error();  return; }
        
        $not_found = true;
        while (($row = mysql_fetch_row($res))) {

               $is_found = true;
                
               $query = 'SELECT dty_ID, dty_Name, CONCAT(dty_OriginatingDBID,"-",dty_IDInOriginatingDB), dty_NameInOriginatingDB FROM '
                .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID='.$row[0].' AND dty_IDInOriginatingDB='.$row[1]
                .' ORDER BY dty_OriginatingDBID, dty_IDInOriginatingDB';
                
               $res2 = mysql_query($query);               
               if (!$res2) {  print $query.'  '.mysql_error();  return; }
               while (($row2 = mysql_fetch_row($res2))) {
                      array_push($det_types, $row2);
               }
        }
        
        //TERMS

        $query = 'SELECT trm_OriginatingDBID, trm_IDInOriginatingDB, count(trm_ID) as cnt FROM '
            .$db_name.'.defTerms WHERE  trm_OriginatingDBID>0 AND trm_IDInOriginatingDB>0 '
            .' GROUP BY trm_OriginatingDBID, trm_IDInOriginatingDB HAVING cnt>1';
        
        $res = mysql_query($query);
        if (!$res) {  print $query.'  '.mysql_error();  return; }
        
        while (($row = mysql_fetch_row($res))) {

               $is_found = true;
                
               $query = 'SELECT trm_ID, trm_Label, CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB), trm_NameInOriginatingDB FROM '
                .$db_name.'.defTerms WHERE  trm_OriginatingDBID='.$row[0].' AND trm_IDInOriginatingDB='.$row[1]
                .' ORDER BY trm_OriginatingDBID, trm_IDInOriginatingDB';
                
               $res2 = mysql_query($query);               
               if (!$res2) {  print $query.'  '.mysql_error();  return; }
               while (($row2 = mysql_fetch_row($res2))) {
                      array_push($terms, $row2);
               }
        }
        
        if($is_found){
            print '<h4>'.substr($db_name,4).'</h4><table>';    
            if(count($rec_types)>0){
                print '<tr><td colspan=4><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            if(count($det_types)>0){
                print '<tr><td colspan=4><i>Detail types</i></td></tr>';
                foreach($det_types as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            if(count($terms)>0){
                print '<tr><td colspan=4><i>Terms</i></td></tr>';
                foreach($terms as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            print '</table><br><br>';
        } 
        
    }//while  databases
?>
