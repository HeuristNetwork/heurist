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
    * Verifies missed IDinOriginatingDB
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
  
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');


if( $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <form action="verifyConceptCodes2.php" method="POST">
        <div style="padding:20px 0px">
            Only an administrator (server manager) can carry out this action.<br />
            This action requires a special system administrator password (not a normal login password)
        </div>
    
        <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
        <input type="password" name="pwd" autocomplete="off" />

        <input type="submit" value="OK" />
    </form>

    <?php
    exit();
}

?>  

<script>window.history.pushState({}, '', '<?php echo $_SERVER['PHP_SELF']; ?>')</script>
          
<div style="font-family:Arial,Helvetica;font-size:12px">
            <p>Record and base field types with missing xxx_OriginatingDBID or xxx_IDinOriginatingDB fields</p>

<?php            


$mysqli = $system->get_mysqli();
    
    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while (($row = $res->fetch_row())) {
        if( strpos($row[0], 'hdb_DEF19')===0 || strpos($row[0], 'hdb_def19')===0) continue;
        
        if( strpos($row[0], 'hdb_')===0 ){
            //if($row[0]>'hdb_Masterclass_Cookbook')
                $databases[] = $row[0];
        }
    }
    
    $need_Details = true;
    $need_Terms = false;
    
    foreach ($databases as $idx=>$db_name){

        $query = 'SELECT sys_dbSubVersion from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);
        
        //if($ver<3) continue;

        
        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES
        
        $query = 'SELECT rty_ID, rty_Name, rty_NameInOriginatingDB, rty_OriginatingDBID, rty_IDInOriginatingDB FROM '
            .$db_name.'.defRecTypes WHERE  rty_OriginatingDBID>0 AND '
            ."(rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL)";
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {
               $is_found = true;
               array_push($rec_types, $row);
        }

        if($need_Details){
        
        //FIELD TYPES
        $query = 'SELECT dty_ID, dty_Name, dty_NameInOriginatingDB, dty_OriginatingDBID, dty_IDInOriginatingDB FROM '
            .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID>0 AND '
            ."(dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";
            //'(NOT (dty_IDInOriginatingDB>0)) ';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {
               $is_found = true;
               array_push($det_types, $row);
   /*
               $query = 'update '.$db_name.'.defDetailTypes set dty_OriginatingDBID=2,'
.'dty_NameInOriginatingDB="Related Person(s)", dty_IDInOriginatingDB = 235 '
.' where dty_Name like "Related Person%" AND dty_ID ='.$row[0];
               $mysqli->query($query);*/
        }
        
        }
        if($need_Terms){
        
        //TERMS
        $query = 'SELECT trm_ID, trm_Label, trm_NameInOriginatingDB, trm_OriginatingDBID, trm_IDInOriginatingDB FROM '
            .$db_name.'.defTerms WHERE  trm_OriginatingDBID>0 AND (NOT (trm_IDInOriginatingDB>0)) ';
            
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {
               $is_found = true;
               array_push($terms, $row);
        }
        
        }
        
        if($is_found){
            print '<h4 style="margin:0;padding-top:20px">'.substr($db_name,4).'</h4><table style="font-size:12px">';    
            
            print '<tr><td>Internal code</td><td>Name in this DB</td><td>Name in origin DB</td><td>xxx_OriginDBID</td><td>xxx_IDinOriginDB</td></tr>';            
            
            if(count($rec_types)>0){
                print '<tr><td colspan=5><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            if(count($det_types)>0){
                print '<tr><td colspan=5>&nbsp;</td></tr>';
                print '<tr><td colspan=5><i>Detail types</i></td></tr>';
                foreach($det_types as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            if(count($terms)>0){
                print '<tr><td colspan=5><i>Terms</i></td></tr>';
                foreach($terms as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                }
            }
            print '</table>';
        } 
        
    }//while  databases
    print '[end report]</div>';
?>
