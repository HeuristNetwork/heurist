<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Record search
    * 
    * parameters
    * db - heurist database
    * remote=master - request to Heurist_Master_Index
    * a  - action 
    *       minmax - seach numeric min and max value for "dt" (field) or "rt" (record type)
    *       getfacets -   finds all possible facet values for current query and calculates counts for every value 
    *       related -   finds all related record IDs for given set record "ids"
    *       search - default
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

    /*
     parameters

    *       FOR RULES
    *       rules - rules queries - to search related records on server side
    *       getrelrecs (=1) - search relationship records (along with related) on server side
    *       topids - list of records ids, it is used to compose 'parentquery' parameter to use in rules (@todo - replace with new rules algorithm)
    *
    *       INTERNAL/recursive
    *       parentquery - sql expression to substiture in rule query
    *       sql - sql expression to execute (used as recursive parameters to search relationship records)
    *
    *       SEARCH parameters that are used to compose sql expression
    *       q - query string (old mode) or json array (new mode)
    *       w (=all|bookmark a|b) - search among all or bookmarked records
    *       limit  - limit for sql query is set explicitely on client side
    *       offset - offset parameter value for sql query
    *       s - sort order
    *
    *       OUTPUT parameters
    *       vo (=h3) - output format in vsn 3 for backward capability (for detail=ids only)
    *       needall (=1) - by default it returns only first 1000, to return all set it to 1,
    *                      it is set to 1 for server-side rules searches
    *       publiconly (=1) - ignore current user and returns only public records
    *
    *       detail (former 'f') - ids       - only record ids
    *                             header    - record header
    *                             timemap   - record header + timemap details
    *                             detail    - record header + all details
    *                             structure - record header + all details + record type structure (for editing) - NOT USED
    *
    *       CLIENT SIDE
    *       id - unque id to sync with client side
    *       source - id of html element that is originator of this search
    *       qname - original name of saved search (for messaging)

    */

    //these are internal parameters, they cannot be sent from client side
    if( @$_REQUEST['sql'] )unset( $_REQUEST['sql'] );
    if( @$_REQUEST['parentquery'] ) unset ($_REQUEST['parentquery'] );
    //if( @$_REQUEST['needall'] ) unset ($_REQUEST['needall'] );

    //get list of registered database and master index db on the same server
    if(@$_REQUEST['remote'] == 'master' &&
       strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===0){ //the same server  - switch database only
       
       unset($_REQUEST['remote']);
       $_REQUEST['db'] = HEURIST_INDEX_DATABASE;
       if(!@$_REQUEST['q']) $_REQUEST['q'] = 't:22';
    }
    
    $response = array();

    $system = new System();
    
    
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        $response = $system->getError();

    }else if(@$_REQUEST['a'] == 'minmax'){

        $response = recordSearchMinMax($system, $_REQUEST);

    }else if(@$_REQUEST['a'] == 'getfacets'){ //returns counts for facets for given query

        $response = recordSearchFacets($system, $_REQUEST);

    }else if(@$_REQUEST['a'] == 'related'){

        $response = recordSearchRelated($system, $_REQUEST['ids'], @$_REQUEST['direction']);

    }else {
        
        if(@$_REQUEST['remote'] == 'master'){
            
                if(!@$_REQUEST['q']) $_REQUEST['q'] = 't:22'; //all registred db
            
                $reg_url = HEURIST_INDEX_BASE_URL.'hsapi/controller/record_search.php?db='.HEURIST_INDEX_DATABASE.'&q='.$_REQUEST['q'];
                if(@$_REQUEST['detail']){
                    $reg_url = $reg_url.'&detail='
                        .(is_array($_REQUEST['detail'])?json_encode($_REQUEST['detail']):$_REQUEST['detail']);
                }
                $data = loadRemoteURLContent($reg_url);            
                if($data==false){
                    $system->addError(HEURIST_SYSTEM_CONFIG, "Cannot access Master Index database on ".HEURIST_INDEX_BASE_URL);
                    $response = $system->getError();
                }else{
                    $response = json_decode($data, true);    
                }
                
            
        }else{

            // TODO: temporary (for backward compatibility) should be part of all databases
            // Check whether recLinks (relationships cache) table exists and create if not
            $isok = true;
            $value = mysql__select_value($system->get_mysqli(), "SHOW TABLES LIKE 'recLinks'");
            if($value==null || $value==""){
                include(dirname(__FILE__).'/../dbaccess/utils_db_load_script.php'); // used to execute SQL script

                if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__)."/../dbaccess/sqlCreateRecLinks.sql")){
                    $system->addError(HEURIST_DB_ERROR, "Cannot execute script sqlCreateRecLinks.sql");
                    $response = $system->getError();
                    $isok = false;
                }
            }
            if($isok){
                $response = recordSearch($system, $_REQUEST);
                $response['queryid'] = @$_REQUEST['id'];
            }
        
        }
    }

// Return the response object as JSON
header('Content-type: application/json;charset=UTF-8');
print json_encode($response);
?>