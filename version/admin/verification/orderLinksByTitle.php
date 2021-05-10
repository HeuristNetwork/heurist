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
    * Order resource fields by rec_Title
    * 
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

require_once (dirname(__FILE__).'/../../hsapi/System.php');

$rv = array();

// init main system class
$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    $response = $system->getError();
    print json_encode($response);
    exit();
}
if (!$system->is_admin()) {
    print 'To perform this action you must be logged in  as Administrator of group \'Database Managers\'';
    exit();
}

if(!(@$_REQUEST['rty_ID']>0 && @$_REQUEST['dty_ID']>0)){
    print 'You have to define rty_ID (rectype id) and dty_ID (field id) parameters';
    exit();
}
    
$mysqli = $system->get_mysqli();

//3, 134

$query = 'SELECT dtl_ID, r1.rec_ID, dtl_Value, r2.rec_Title FROM recDetails, Records r1, Records r2 '
.' where r1.rec_ID=dtl_RecID and r1.rec_RecTypeID='.$_REQUEST['rty_ID'].' and dtl_DetailTypeID='.$_REQUEST['dty_ID']
.' and dtl_Value=r2.rec_ID order by r1.rec_ID, r2.rec_Title ';
// and r1.rec_ID=494461    
$res = $mysqli->query($query);

$rec_ID = 0;

$vals = array();
$titles = array();
$ids = array();

$cnt = 0;

if($res){
    while ($row = $res->fetch_row()) {
        
        if($rec_ID!=$row[1]){
            $cnt = $cnt + updateDtlValues($mysqli, $ids, $vals, $titles);    
            $rec_ID=$row[1];
            $vals = array();
            $ids = array();
            $titles = array();
        }
        $ids[]  = $row[0];
        $vals[] = $row[2];
        //$titles[] = $row[0].'  '.$row[2].'  '.$row[3];
    }
    $cnt = $cnt + updateDtlValues($mysqli, $ids, $vals, $titles);    
}

print $cnt.' records updated';

function updateDtlValues($mysqli, $ids, $vals, $titles){

    if(count($vals)>1){
        
        sort($ids);
        $k = 0;
        foreach ($ids as $dt) { //sorted dtl_ID
//print $ids[$k].'  dtl_Value='.$vals[$k].'  title='.$titles[$k].'<br>';
            $query = "update recDetails set dtl_Value=".$vals[$k].' where dtl_ID='.$ids[$k];
            //print $query.'<br>';                
            $res = $mysqli->query($query);
            if ($mysqli->error) {
                    print 'Error for query '.$query.' '.$mysqli->error;
            	    exit();
            }

	    $k++;
        }
        return 1;  
    }else{
        return 0;
    }    
}    
?>