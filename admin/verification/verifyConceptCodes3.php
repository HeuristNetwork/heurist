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
    * Verifies missed concept codes for registered databases
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
    
    <form action="verifyConceptCodes3.php" method="POST">
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
            <p>This list shows definitions without concept codes for registered databases</p>
<?php            

$registered = array();

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
  
    $need_Details = @$_REQUEST['nodty']!=1;
    $need_Terms = @$_REQUEST['noterms']!=1;
 
 // max ids in Heurist_Core_Def
 // rty 59
 // dty 961
    $all_rty_regs = array();
  
    
    foreach ($databases as $idx=>$db_name){

        $query = 'SELECT sys_dbRegisteredID from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);
        if(!($ver>0)) continue;
/* assign values for unregistered databases
        if($db_name=='hdb_johns_test_028') continue;
        $query = 'UPDATE '.$db_name
.'.defRecTypes set rty_IDInOriginatingDB = rty_ID, rty_NameInOriginatingDB = rty_Name, rty_OriginatingDBID=0'
." WHERE (rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL "
."OR rty_IDInOriginatingDB='' OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)";
        $mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=0'
            ." WHERE (dty_ID>56 and dty_ID<73 and dty_OriginatingDBID=0 and dty_IDInOriginatingDB=0)";
            $mysqli->query($query);            
if($mysqli->error){print $query.'  '.$mysqli->error; break;}
            
$query = 'UPDATE '.$db_name
.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=0'
." WHERE (dty_OriginatingDBID='' OR dty_OriginatingDBID=0 OR dty_OriginatingDBID IS NULL "
." OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";
        $mysqli->query($query);            
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = SUBSTR(trm_Label,1,63),'
            .' trm_OriginatingDBID=0'
            ." WHERE (trm_ID>3257 and trm_ID<3297 and trm_OriginatingDBID=0 and trm_IDInOriginatingDB=0)";
$mysqli->query($query);            
if($mysqli->error){print $query.'  '.$mysqli->error; break;}
            
$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = SUBSTR(trm_Label,1,63),'
            .' trm_OriginatingDBID=0'
            ." WHERE (trm_OriginatingDBID='' OR trm_OriginatingDBID=0 OR trm_OriginatingDBID IS NULL "
            ." OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL)";
$mysqli->query($query);            
if($mysqli->error){print $query.'  '.$mysqli->error; break;}
        continue;
*/
        
        //$registered[$db_name] = $ver;

        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES
        
        $query = 'SELECT rty_ID, rty_Name, rty_NameInOriginatingDB, rty_OriginatingDBID, rty_IDInOriginatingDB FROM '
            .$db_name.".defRecTypes WHERE (rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL " 
            ."OR rty_IDInOriginatingDB='' OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)";
            //.' OR rty_Name LIKE "% 2" OR rty_Name LIKE "% 3"';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {
               $is_found = true;
               array_push($rec_types, $row);
        }
        
        
/*      set Ids for registered databases  
        if($is_found){
        
            $query = 'UPDATE '.$db_name.'.defRecTypes set rty_IDInOriginatingDB = rty_ID, rty_NameInOriginatingDB = rty_Name, rty_OriginatingDBID='.$ver
            ." WHERE (rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL "
            ."OR rty_IDInOriginatingDB='' OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)";
            $mysqli->query($query);
        }
*/
        
/*   find alternatives              
        if($ver==2){
            $dbid = 'in (2,3,1066)';
        }else if($ver==6){ //biblio
            $dbid = 'in (3,6)';
        }else{
            $dbid = '='.$ver;
        }

        $query = 'SELECT rty_ID, rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM '
            .$db_name.'.defRecTypes WHERE (rty_OriginatingDBID '.$dbid.' AND rty_OriginatingDBID>0)';
        
        $res = $mysqli->query($query);
        while (($row = $res->fetch_row())) {
               $row[1] = strtolower($row[1]);
               array_push($all_rty_regs, $row);
        }
*/        
        
        //FIELD TYPES
        if($need_Details){
        $query = 'SELECT dty_ID, dty_Name, dty_NameInOriginatingDB, dty_OriginatingDBID, dty_IDInOriginatingDB FROM '
            .$db_name.".defDetailTypes "
            ." WHERE  dty_OriginatingDBID='' OR dty_OriginatingDBID=0 OR dty_OriginatingDBID IS NULL " //
            ."OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL ";
            
            //.' OR dty_Name LIKE "% 2" OR dty_Name LIKE "% 3"';
        
        $res = $mysqli->query($query);
        if (!$res) {  print $query.'  '.$mysqli->error;  return; }
        
        while (($row = $res->fetch_row())) {
               $is_found = true;
               array_push($det_types, $row);
        }
        
        if(count($det_types)>0){
            
/*         
$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=2'
            ." WHERE (dty_ID>56 and dty_ID<73 and dty_OriginatingDBID=0 and dty_IDInOriginatingDB=0)";
$mysqli->query($query);            
            
$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID='.$ver
            ." WHERE (dty_OriginatingDBID='' OR dty_OriginatingDBID=0 OR dty_OriginatingDBID IS NULL "
            ." OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";
$mysqli->query($query);            
*/           
        }
        
        
        }
        
        //TERMS
        if($need_Terms){
            $query = 'SELECT trm_ID, trm_Label, trm_NameInOriginatingDB, trm_OriginatingDBID, trm_IDInOriginatingDB FROM '
                .$db_name.".defTerms "
            ."WHERE trm_OriginatingDBID='' OR trm_OriginatingDBID=0 OR trm_OriginatingDBID IS NULL " //
            ."OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL";
                
            $res = $mysqli->query($query);
            if (!$res) {  print $query.'  '.$mysqli->error;  return; }
            
            while (($row = $res->fetch_row())) {
                   $is_found = true;
                   array_push($terms, $row);
            }
          
          /*  
        if(count($terms)>0){
$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = SUBSTR(trm_Label,1,63),'
            .' trm_OriginatingDBID=2'
            ." WHERE (trm_ID>3257 and trm_ID<3297 and trm_OriginatingDBID=0 and trm_IDInOriginatingDB=0)";
$mysqli->query($query);            
            
$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = SUBSTR(trm_Label,1,63),'
            .' trm_OriginatingDBID='.$ver
            ." WHERE (trm_OriginatingDBID='' OR trm_OriginatingDBID=0 OR trm_OriginatingDBID IS NULL "
            ." OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL)";
$mysqli->query($query);            
          
        }
        */
        }
        
        if($is_found){
            $registered[$db_name] = array('id'=>$ver, 'rty'=>$rec_types, 'dty'=>$det_types, 'trm'=>$terms);
        }
        
    }//while  databases
      
    foreach($registered as $db_name=>$data){
        
            $rec_types = $data['rty'];
            $det_types = $data['dty'];
            $terms = $data['trm'];

            print '<h4 style="margin:0;padding-top:20px">'.$data[id].' - '.substr($db_name,4).'</h4><table style="font-size:12px">';    
            
            print '<tr><td>Internal code</td><td>Name in this DB</td><td>Name in origin DB</td><td>xxx_OriginDBID</td><td>xxx_IDinOriginDB</td></tr>';          
            
            if(count($rec_types)>0){
                print '<tr><td colspan=5><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    print '<tr><td>'.implode('</td><td>',$row).'</td></tr>';
                    
                    //find options what may be code for these rectypes
                    foreach($all_rty_regs as $k=>$rty)
                    {
                        if($rty[1]==strtolower($row[0]) || ($row[1] && $rty[1]==strtolower($row[1]))){
                            print '<tr><td colspan="2"></td><td>'.$rty[1].'</td><td>'.$rty[2].'</td><td>'.$rty[3].'</td></tr>';
                        }
                    }
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
        
    print '[end report]</div>';

/*    
    print '<div><table>';
    foreach($all_rty_regs as $k=>$rty)
    {
        print '<tr><td>'.$rty[1].'</td><td>'.$rty[2].'</td><td>'.$rty[3].'</td></tr>';
    }
    print '</table></div>';
*/    
/*    
    asort($registered);
    
    foreach ($registered as $db_name=>$regid){
        print $regid.'  '.$db_name.'<br>';
    }
*/    
?>
