<?php

    /**
    * Application interface. See HRecordMgr in hapi.js
    * Record search
    *
    * parameters
    * db - heurist database
    * remote=master - request to HEURIST_INDEX_DATABASE
    * a  - action
    *       minmax - seach numeric min and max value for "dt" (field) or "rt" (record type)
    *       getfacets -   finds all possible facet values for current query and calculates counts for every value
    *       related -   finds all related record IDs for given set record "ids"
    *       search - default
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
    use hserv\utilities\USanitize;
    
    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../records/search/recordSearch.php';

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

    $req_params = USanitize::sanitizeInputArray();

    //these are internal parameters, they cannot be sent from client side
    if( @$req_params['sql'] ) {unset( $req_params['sql'] );}
    if( @$req_params['parentquery'] ) {unset ($req_params['parentquery'] );}
    
    //get list of registered database and master index db on the same server
    if(@$req_params['remote'] == 'master' &&
       strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===0){ //the same server  - switch database only

       unset($req_params['remote']);
       $req_params['db'] = HEURIST_INDEX_DATABASE;
       if(!@$req_params['q']) {$req_params['q'] = '{"t":"'.HEURIST_INDEX_DBREC.'"}';}
    }


    if(@$req_params['details_encoded']==1){

        if(@$req_params['q']){
            $req_params['q'] = str_replace( ' xxx_style=', ' style=',
                        str_replace( '^^/', '../', urldecode($req_params['q'])));
        }

        if(@$req_params['count_query']){
            $req_params['count_query'] = json_decode(str_replace( ' xxx_style=', ' style=',
                        str_replace( '^^/', '../', urldecode($req_params['count_query']))),true);
        }

    }elseif(@$req_params['details_encoded']==2){

        if(@$req_params['q']){
            $req_params['q'] = urldecode($req_params['q']);
        }
        if(@$req_params['count_query']){
            $req_params['count_query'] = json_decode(urldecode($req_params['count_query']), true);
        }
    }

    $response = array();

    $system = new hserv\System();

    if( ! $system->init(@$req_params['db']) ){
        //get error and response
        $response = $system->getError();

    }elseif(@$req_params['a'] == 'minmax'){

        $response = recordSearchMinMax($system, $req_params);

    }elseif(@$req_params['a'] == 'count_details'){

        $response = recordSearchDistinctValue($system, $req_params);

    }elseif(@$req_params['a'] == 'count_matches'){

        $response = recordSearchMatchedValues($system, $req_params);

    }elseif(@$req_params['a'] == 'getfacets'){ //returns counts for facets for given query

        $response = recordSearchFacets($system, $req_params);

    }elseif(@$req_params['a'] == 'gethistogramdata'){ // returns array of lower and upper limit plus a count for each interval

        $response = getDateHistogramData($system, $req_params['range'], $req_params['interval'],
                    @$req_params['recids'], @$req_params['dtyid'], @$req_params['format'], @$req_params['is_between']==1);

    }elseif(@$req_params['a'] == 'related'){

        $response = recordSearchRelated($system, $req_params['ids'], @$req_params['direction']);

    }elseif(@$req_params['a'] == 'links_count'){

        $response = recordLinkedCount($system, @$req_params['source_ID'], @$req_params['target_ID'], @$req_params['dty_ID']);

    }elseif(@$req_params['a'] == 'cms_menu'){  //retrieve all child cms entries for given menu entries

        $system->defineConstants();

        if(!($system->defineConstant('RT_CMS_HOME') &&
             $system->defineConstant('RT_CMS_MENU'))){

            $response = $system->addError(HEURIST_ERROR, 'Required record type "Menu" not defined in this database');

        }elseif(!($system->defineConstant('DT_CMS_MENU') &&
                   $system->defineConstant('DT_CMS_TOP_MENU'))){

            $response = $system->addError(HEURIST_ERROR, 'Required field type "Menu pointer" not defined in this database');

        }else{

            $resids = array();
            $response = recordSearchMenuItems($system, $req_params['ids'], $resids, (@$req_params['main_menu']==1) );
        }

    /* not implemented
    }elseif(@$req_params['a'] == 'map_document'){  //retrieve all layers and datasource records fro given map document

        $resids = array();
        $response = recordSearchMapDocItems($system, $req_params['ids'], $resids);
    */
    }elseif(@$req_params['a'] == 'links_details'){

        $ids = prepareIds($req_params['ids']);
        $response = array();
        if($req_params['q']=='$IDS'){
            $response = recordSearchDetailsForRecIds($system, $ids, $req_params['detail']);
        }else{
            foreach ($ids as $recID){
                $response[$recID] = recordSearchLinkedDetails($system, $recID, $req_params['detail'], $req_params['q']);
            }
        }
        $response = array('status'=>HEURIST_OK, 'data'=> $response);


    }elseif(@$req_params['a'] == 'get_linked_media'){

        $ids = prepareIds($req_params['ids']);
        $response = array();

        foreach ($ids as $id) {
            $res = fileGetThumbnailURL($system, $id, false, true);
            $response[$id] = !$res || empty($res['url']) ? '' : $res['url'];
        }

        $response = array('status' => HEURIST_OK, 'data' => $response);

    }else{

        if(@$req_params['remote'] == 'master'){

                if(!@$req_params['q']) {$req_params['q'] = '{"t":"'.HEURIST_INDEX_DBREC.'"}';}//all registred db
                //change hsapi to hserv when master index will be v6.5
                $reg_url = HEURIST_INDEX_BASE_URL
                .'hserv/controller/record_search.php?db='.HEURIST_INDEX_DATABASE.'&q='.$req_params['q'];
                if(@$req_params['detail']){
                    $reg_url = $reg_url.'&detail='
                        .(is_array($req_params['detail'])?json_encode($req_params['detail']):$req_params['detail']);
                }
                $data = loadRemoteURLContent($reg_url);//search master index database for all regitered databases

                if($data==false){
                    $msg = 'Cannot access Master Index database on '.HEURIST_INDEX_BASE_URL;
                    if(@$glb_curl_error){
                        $msg = $msg.'(CURL ERROR: '.$reg_url.' '.$glb_curl_error.')';
                    }
                    $system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                    $response = $system->getError();
                }else{
                    $response = json_decode($data, true);
                }


        }else{
            $response = recordSearch($system, $req_params);
            $response['queryid'] = @$req_params['id'];
        }
    }

    $system->dbclose();

// Return the response object as JSON
$system->setResponseHeader();
print json_encode($response);
?>