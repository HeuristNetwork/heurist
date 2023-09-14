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
    * fixCmsAbsPaths - replace absolute paths in CMS records to relative 
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
 
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/recordsBatch.php');

if( !isset($_REQUEST['db']) && $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    ?>
    
    <form action="fixCmsAbsPaths.php" method="POST">
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

//clear url parameters
?>            
<script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>')</script>          
       
       
<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
    <p>Fix absolute paths in web page content</p>
<?php            

$mysqli = $system->get_mysqli();
    
//find all database
$databases = mysql__getdatabases4($mysqli, false);   

// 
$servers = array('https:\/\/heuristref.net', 'https:\/\/heurist.sydney.edu.au', 'https:\/\/heuristplus.sydney.edu.au', 'https:\/\/heurist.huma-num.fr', 'https:\/\/heuristest.fdm.uni-hamburg.de:443');
if (is_array($absolutePathsToRemoveFromWebPages) && count($absolutePathsToRemoveFromWebPages)>0){
    foreach($absolutePathsToRemoveFromWebPages as $srv){
        $srv = str_replace("/","\/",$srv);
        $servers[] = $srv;
    }
}
print 'Servers: '.implode('<br>',$servers).'<br>';

 __correctAbsPaths();
   
//
//
//
function __correctAbsPaths(){

    global $system, $mysqli, $databases; 

    $dbRecDetails = new RecordsBatch($system, null);
    
    //$databases = array('amade_testnewsystem');
    //$databases = array('osmak_9c');
    //$databases = array('CBAP_Uncovering_Pacific_Pasts');    
    
    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

/*
    *       recIDs - list of records IDS to be processed or 'ALL'
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added,replaced or deleted
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value,  subs= 1 | 0
    *       for delete: sVal, subs= 1 | 0   
    *       tag  = 0|1  - add system tag to mark processed records
*/        

        $query = 'select rty_ID from defRecTypes where rty_OriginatingDBID=99 and rty_IDInOriginatingDB in (51, 52)';
        $rty_IDs = mysql__select_list2($mysqli, $query);

        $query = 'select dty_ID from defDetailTypes where dty_OriginatingDBID=2 and dty_IDInOriginatingDB=4';
        $dty_ID = mysql__select_value($mysqli, $query);

        $data = array(
        'recIDs'=>'ALL',
        'rtyID'=>$rty_IDs,
        'dtyID'=>$dty_ID,
        'dt_extended_description'=>$dty_ID,
        'sVal'=>'https://heurist',
        'rVal'=>'replaceAbsPathinCMS',
        'subs'=>1, //substring
        'debug'=>0,
        'tag'=>0
        );
        
        print '<h4>'.$db_name.'</h4><br>';
        
        print 'Rectypes: '.implode(',',$rty_IDs).' Field: '.$dty_ID.'<br>';
        
        $dbRecDetails->setData($data);
        $res = $dbRecDetails->detailsReplace();
        if(!$res){
            print 'ERROR: '.$system->getError()['message'];
        }
     
        print '<hr>';   
    }//for
    
}

function replaceAbsPathinCMS($recID, $val){
    
    global $servers;
    
//print '<xmp>BEFORE '.$val.'</xmp><br><hr><br>';

$paths0 = array('\/HEURIST', '\/html', ''); 
$paths = array('heurist', 'h5-alpha', 'h5-ao', 'h5', 'h5-beta', 'h6-alpha', 'h6-ao', 'h6', 'h6-beta');

$cnt = 0;

foreach ($servers as $srv) {
    foreach ($paths0 as $path0) {
        foreach ($paths as $path) {
            $s = '/'.$srv.$path0.'\/'.$path.'\//i';
            
            $matches = array();
            
            if(preg_match($s, $val, $matches)){
            
                $res = preg_replace($s, './', $val);    
                if($res!=null && $val != $res){
                    $val = $res;
                    $cnt = $cnt + count($matches);
                }
                
                foreach ($matches as $fnd) print $fnd.' &nbsp;&nbsp;&nbsp; ';
            }
        }
    }
}
//print '<xmp>AFTER '.$val.'</xmp><br><hr><br>';

//report
print '<br>RecID: '.$recID.'. Replaced '.$cnt.' entries<br>';


             
return $val;    
    
}
?>
