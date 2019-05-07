<?php

    /**
    * Returns content of kml snippet field as kml output
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_recsearch.php');
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');
    
    $response = array();

    $system = new System();
    
    $params = $_REQUEST;
    
    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }
    if(!(@$params['recID']>0)){
        $system->error_exit_api('recID parameter is not defined or has wrong value'); //exit from script
    }
    if(!$system->defineConstant('DT_KML')){
        $system->error_exit_api('Database '.$params['db'].' does not have field definitionr for KML snipppet'); //exit from script
    }
    
    $record = array("rec_ID"=>$params['recID']);
    recordSearchDetails($system, $record, array(DT_KML));
    if (@$record["details"] && @$record["details"][DT_KML]){
            $out = array_shift($record["details"][DT_KML]);     
            header('Content-Type: application/vnd.google-earth.kml+xml');
            header('Content-disposition: attachment; filename=output.kml');
            header('Content-Length: ' . strlen($out));
            exit($out);
    }else{
            exit(); //nothingh found
    }
?>