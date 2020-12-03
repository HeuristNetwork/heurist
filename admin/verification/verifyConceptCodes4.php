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
            //is defTermLinks exist
            $value = mysql__select_value($mysqli, 'SHOW TABLES FROM '.$db_name." LIKE 'defTermsLinks'");
            $not_exist = ($value==null || $value=="");
            if(!$not_exist){
                array_push($db2_with_links,$db_name);    
            }
                    
        }
            
            $query = 'select count(*) from '.$db_name.'.defDetailTypes where '
            .'(dty_Type="enum" OR dty_Type="relmarker") and (NOT dty_JsonTermIDTree>0)'; // OR dty_Type="relationtype"
            $value = mysql__select_value($mysqli, $query);
            if($value>0){

                $query = 'select dty_ID, dty_JsonTermIDTree from '.$db_name.'.defDetailTypes where '
                .'(dty_Type="enum" OR dty_Type="relmarker") and (NOT dty_JsonTermIDTree>0)'; // OR dty_Type="relationtype"
                $value = mysql__select_all($mysqli, $query);
                
                if($ver<3){
                    $db2_with_terms[$db_name] = $value;
                }else{
                    $db3_with_terms[$db_name] = $value;
                }

                /* datetime of creation for defTermsLinks
                $query = "SELECT create_time FROM INFORMATION_SCHEMA. TABLES WHERE table_schema = '"
                        .$db_name."' AND table_name = 'defTermsLinks'";
                $dt = mysql__select_value($mysqli, $query);
                $db3_with_terms[$db_name] = array($dt,$value);
                */
            }
            
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
?>
