<?php

/**
* 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
exit();

require_once (dirname(__FILE__).'/../System.php');

$system = new System();
$isSystemInited = $system->init(@$_REQUEST['db'], true);


if( !$isSystemInited ){  //cannot init system (apparently connection to Database Server is wrong or server is down)
    $err = $system->getError();
    $error_msg = @$err['message'];
}else if(!$system->has_access()){ 
    $error_msg = 'You must be logged in';
}
if(isset($error_msg)){
    print $error_msg;
    exit();
}
$mysqli = $system->get_mysqli();

//find duplicates for districts
$query = 'select rec_ID, d1.dtl_Value, d2.dtl_Value from Records, recDetails d1, recDetails d2
where rec_RecTypeID=25 
and rec_ID=d1.dtl_RecID and d1.dtl_DetailTypeID=1
and rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID=169
order by d1.dtl_Value, d2.dtl_Value';

$removed = 0;

$res = $mysqli->query($query);
$name = '';
$gov_id = '';
$ids = array();
while ( $row = $res->fetch_row() ) {
    
    if($name!=$row[1] || $gov_id != $row[2]){
        //take first on and replace in towns 
        if(count($ids)>1) print '<br>'.$name.' '.count($ids);

        removeDuplicates($ids); 
        $name   = $row[1];
        $gov_id = $row[2];    
        //if(count($ids)>1) exit();
        $ids = array();
    }
    array_push($ids, $row[0]);
}
        if(count($ids)>1){
            print '<br>'.$name.' '.count($ids);
        }
removeDuplicates($ids);

print '<br>Removed: '.$removed;

//
//
//
function removeDuplicates($ids){
    global $mysqli, $removed;
    if(count($ids)>1){
        //take first on and replace in towns 
        $rec_id = array_shift($ids);
        
        $ids_todel = implode(',',$ids);
        
        $query = 'update recDetails set dtl_Value='.$rec_id.' where dtl_Value in ('.$ids_todel.') and dtl_DetailTypeID=170';
//print $query; return;
        
        $res = $mysqli->query($query);

        $mysqli->query('SET foreign_key_checks = 0');
        
        //delete rest of districts
        $query = 'delete from recDetails where dtl_RecID in ('.$ids_todel.')';
        $res = $mysqli->query($query);
        $query = 'delete from Records where rec_ID in ('.$ids_todel.')';
        $res = $mysqli->query($query);
        
        $query = 'delete from usrReminders where rem_RecID in ('.$ids_todel.')';
        $res = $mysqli->query($query);
        $query = 'delete from usrRecTagLinks where rtl_RecID in ('.$ids_todel.')';
        $res = $mysqli->query($query);
        $query = 'delete from usrBookmarks where bkm_recID in ('.$ids_todel.')';
        $res = $mysqli->query($query);
        $query = 'delete from recLinks where rl_SourceID in ('.$ids_todel.') or rl_TargetID in ('.$ids_todel.')';
        $res = $mysqli->query($query);

        $mysqli->query('SET foreign_key_checks = 1');
        
        $removed = $removed + count($ids);
    }
}
?>