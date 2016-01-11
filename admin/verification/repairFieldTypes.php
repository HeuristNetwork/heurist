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
    * Write out corrected version of field definitions
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

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    $rv = array();

    header('Content-type: text/javascript');

    if (!is_admin()) {
        $rv['error'] = "Sorry, you need to be a database owner to be able to modify the database structure";
        print json_format($rv);
        return;
    }

    $data = null;
    if(@$_REQUEST['data']){
        $data = json_decode(urldecode(@$_REQUEST['data']), true);
    }else{
        $rv['error'] = "Data not defined!"; // TODO: Explain what this means and what do to about it ...
        print json_format($rv);
        return;
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

        $res = mysql_query($query);
        if (mysql_error()) {
            $rv['error'] = "SQL error updating field type ".$dt_id.": ".mysql_error();
            print json_format($rv);
            return;
        }

    }//for

    $rv['result'] = $k." field".($k>1?"s have":" has")." been repaired. If you have unsaved data in an edit form, save your changes and reload the page to apply revised/corrected field definitions";
    print json_format($rv);
?>
