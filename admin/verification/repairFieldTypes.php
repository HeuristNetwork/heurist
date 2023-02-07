<?php
    /*
    * Copyright (C) 2005-2020 University of Sydney
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
    * Write out corrected version of field definitions
    *
    * see listDatabaseErrors
    * 
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

require_once (dirname(__FILE__).'/../../hsapi/System.php');

header('Content-type: application/json;charset=UTF-8');

$rv = array();

// init main system class
$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    $response = $system->getError();
    print json_encode($response);
    exit();
}
if (!$system->is_admin()) {
    $response = $system->addError(HEURIST_REQUEST_DENIED,
                 'To perform this action you must be logged in  as Administrator of group \'Database Managers\'');
    print json_encode($response);
    exit();
}
    
$mysqli = $system->get_mysqli();
    

$rv = array();
    

    $data = null;
    if(@$_REQUEST['data']){
        $data = $_REQUEST['data']; //json_decode(urldecode(@$_REQUEST['data']), true);
    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST,
                     'Data not defined');
        print json_encode($response);
        exit();
    }

    $k = 0;
    foreach ($data as $dt) {

        $dt_id = $dt[0];
        $dt_mode = $dt[1];
        $dt_val = $dt[2];

        $query = "update defDetailTypes set ";
        if($dt_mode==0){
            $query = $query."dty_JsonTermIDTree";
        }else if($dt_mode==1){
            $query = $query."dty_TermIDTreeNonSelectableIDs";
        }else if($dt_mode==2){
            $query = $query."dty_PtrTargetRectypeIDs";
        }else{
            continue;
        }
        $query = $query."='".$dt_val."' where dty_ID=".$dt_id;

        $k++;

        $res = $mysqli->query($query);
        if ($mysqli->error) {
            $response = $system->addError(HEURIST_DB_ERROR,
                         'SQL error updating field type '.$dt_id, $mysqli->error);
            print json_encode($response);
            exit();
        }

    }//for

print json_encode(array('status'=>HEURIST_OK,
 'result'=>  $k." field".($k>1?"s have":" has")
    ." been repaired. If you have unsaved data in an edit form, save your changes and reload the page to apply revised/corrected field definitions"
 ));
?>