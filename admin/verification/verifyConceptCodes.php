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
    * Verifies duplications for concept code in rectypes, fieldtypes and terms
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
    
    <form action="verifyConceptCodes.php" method="POST">
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
            <p>This list shows re-use of the same concept code within each database where this occurs. Re-use is an error, although it should have very little adverse effect on local operations.</p>
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
    
    foreach ($databases as $idx=>$db_name){
        
        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES
        
        $query = 'SELECT rty_OriginatingDBID, rty_IDInOriginatingDB, count(rty_ID) as cnt FROM '
            .$db_name.'.defRecTypes WHERE  rty_OriginatingDBID>0 AND rty_IDInOriginatingDB>0 '
            .' GROUP BY rty_OriginatingDBID, rty_IDInOriginatingDB HAVING cnt>1';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {

               $is_found = true;
                
               $query = 'SELECT rty_ID, rty_Name, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB), rty_NameInOriginatingDB FROM '
                .$db_name.'.defRecTypes WHERE  rty_OriginatingDBID='.$row[0].' AND rty_IDInOriginatingDB='.$row[1]
                .' ORDER BY rty_OriginatingDBID, rty_IDInOriginatingDB';
                
               $res2 = $mysqli->query($query);               
               if (!$res2) {  print $query.'  '.$mysqli->error;  return; }
               while (($row2 = $res2->fetch_row())) {
                      array_push($rec_types, $row2);
               }
        }

        //FIELD TYPES

        $query = 'SELECT dty_OriginatingDBID, dty_IDInOriginatingDB, count(dty_ID) as cnt FROM '
            .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID>0 AND dty_IDInOriginatingDB>0 '
            .' GROUP BY dty_OriginatingDBID, dty_IDInOriginatingDB HAVING cnt>1';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        $not_found = true;
        while (($row = $res->fetch_row())) {

               $is_found = true;
                
               $query = 'SELECT dty_ID, dty_Name, CONCAT(dty_OriginatingDBID,"-",dty_IDInOriginatingDB), dty_NameInOriginatingDB FROM '
                .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID='.$row[0].' AND dty_IDInOriginatingDB='.$row[1]
                .' ORDER BY dty_OriginatingDBID, dty_IDInOriginatingDB';
                
               $res2 = $mysqli->query($query);               
               if (!$res2) {  print $query.'  '.$mysqli->error;  return; }
               while (($row2 = $res2->fetch_row())) {
                      array_push($det_types, $row2);
               }
        }
        
        //TERMS

        $query = 'SELECT trm_OriginatingDBID, trm_IDInOriginatingDB, count(trm_ID) as cnt FROM '
            .$db_name.'.defTerms WHERE  trm_OriginatingDBID>0 AND trm_IDInOriginatingDB>0 '
            .' GROUP BY trm_OriginatingDBID, trm_IDInOriginatingDB HAVING cnt>1';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {

               $is_found = true;
                
               $query = 'SELECT trm_ID, trm_Label, CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB), trm_NameInOriginatingDB FROM '
                .$db_name.'.defTerms WHERE  trm_OriginatingDBID='.$row[0].' AND trm_IDInOriginatingDB='.$row[1]
                .' ORDER BY trm_OriginatingDBID, trm_IDInOriginatingDB';
                
               $res2 = $mysqli->query($query);               
               if (!$res2) {  print $query.'  '.$mysqli->error;  return; }
               while (($row2 = $res2->fetch_row())) {
                      array_push($terms, $row2);
               }
        }
        
        if($is_found){
            print '<h4 style="margin:0;padding-top:20px">'.substr($db_name,4).'</h4><table style="font-size:12px">';    
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
            print '</table>';
        } 
        
    }//while  databases
    print '[end report]</div>';
?>
