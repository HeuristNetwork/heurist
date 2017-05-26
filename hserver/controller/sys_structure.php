<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *   database definitions - record types, record structure, field types, terms
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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
    require_once (dirname(__FILE__).'/../dbaccess/db_structure.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_structure_tree.php');

    /* DEBUG
    $_REQUEST['db'] = 'dos_3';
    $_REQUEST['q'] = 'manly';
    */

    $response = array();

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){

        //get error and response
        $response = $system->getError();
        
    }else{

        //$currentUser = $system->getCurrentUser();
        $data = array();

        if (@$_REQUEST['terms']) {
            $data["terms"] = dbs_GetTerms($system);
        }

        if (@$_REQUEST['detailtypes']) {
            $ids = $_REQUEST['detailtypes']=='all'?null:$_REQUEST['detailtypes'];
            $data["detailtypes"] = dbs_GetDetailTypes($system, $ids, intval(@$_REQUEST['mode']) );
        }

        if (@$_REQUEST['rectypes']) {
            $ids = $_REQUEST['rectypes']=='all'?null:$_REQUEST['rectypes'];
            $mode = intval(@$_REQUEST['mode']);

            if($mode>2){
                $data["rectypes"] = dbs_GetRectypeStructureTree($system, $ids, $mode, @$_REQUEST['fieldtypes']);
            }else{
                $data["rectypes"] = dbs_GetRectypeStructures($system, $ids, $mode );
            }
        }

        $response = array("status"=>HEURIST_OK, "data"=> $data );

    }               
    
    /*
    if ( extension_loaded('zlib') && (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) )
    {
            ob_start('ob_gzhandler');
    }*/    
/*
    ini_set("zlib.output_compression", 4096);
    ini_set("zlib.output_compression_level", 6);
    header('Content-type: text/javascript');
    print json_encode($response);
*/
    
ob_start(); 
echo json_encode($response);
$output = gzencode(ob_get_contents(),6); 
ob_end_clean(); 
header('Content-Encoding: gzip');
header('Content-type: text/javascript');
echo $output; 
unset($output);   
?>