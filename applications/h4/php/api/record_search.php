<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Record search
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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
    require_once (dirname(__FILE__).'/../common/db_recsearch.php');
    require_once (dirname(__FILE__).'/../common/utils_db.php');
    //require_once (dirname(__FILE__).'/../common/utils_db_script.php');

    /* DEBUG
    $_REQUEST['db'] = 'dos_3';
    $_REQUEST['q'] = 'manly';
    */

    $response = array();

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){

        //get error and response
        $response = $system->getError();

    }else if(@$_REQUEST['a'] == 'minmax'){

        $response = recordSearchMinMax($system, $_REQUEST);

    }else if(@$_REQUEST['a'] == 'getfacets'){    

        $response = recordSearchFacets($system, $_REQUEST);

    }else {
        
        //temorary!!!
        //verify than recLinks does not exist
        $isok = true;
        $value = mysql__select_value($system->get_mysqli(), "SHOW TABLES LIKE 'recLinks'");
        if($value==null || $value==""){
            include(dirname(__FILE__).'/../common/utils_db_script.php');
            
            if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__)."/../common/sqlCreateRecLinks.sql")){
                $system->addError(HEURIST_DB_ERROR, "Can not execure script sqlCreateRecLinks.sql");
                $response = $system->getError();
                $isok = false;
            }        
        }
        if($isok){
        //temorary!!!

            //DEGUG        $currentUser = array('ugr_ID'=>2);
            $need_structure = (@$_REQUEST['f']=='structure');
            $need_details = (@$_REQUEST['f']=='map' || $need_structure);

            $response = recordSearch($system, $_REQUEST, $need_structure, $need_details);    
        
        }
    }

    header('Content-type: application/json'); //'text/javascript');
    print json_encode($response);

    exit();
?>