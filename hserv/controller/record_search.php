<?php
/**
* record_search.php - Handler for records search
*
* Main usage - search the records and output data as a json (for HRecordSet).
*
* parameters
* db - The target Heurist database name.
* remote=master - If set to 'master', directs the request to the HEURIST_INDEX_DATABASE.
* a  - action: Specifies the operation to perform.
*      minmax - Search for numeric minimum and maximum values for a "dt" (detail type/field) or "rt" (record type).
*      getfacets - Finds all possible facet values for the current query and calculates counts for each value.
*      related - Finds all related record IDs for a given set of record IDs ("ids" parameter).
*      search - default
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
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
    *       topids - List of record IDs, used to compose the 'parentquery' parameter for use in rules (@todo - replace with new rules algorithm).
    *
    *       INTERNAL/recursive (Not intended for direct client use)
    *       parentquery - SQL expression to substitute in a rule query.
    *       sql - SQL expression to execute (used as a recursive parameter for searching relationship records).
    *
    *       SEARCH parameters (used to compose SQL expressions)
    *       q - Query string (old mode) or JSON array (new mode).
    *       w - (=all|bookmark a|b) - Search scope: 'all' records or bookmarked records.
    *       limit  - Limit for the SQL query, set explicitly on the client side.
    *       offset - Offset parameter value for the SQL query.
    *       s - Sort order.
    *
    *       OUTPUT parameters
    *       needall - (=1) - If 1, returns all matching records; by default, returns only the first 1000. Set to 1 for server-side rules searches.
    *       publiconly - (=1) - If 1, ignores the current user and returns only public records.
    *
    *       detail (formerly 'f') - Level of detail for returned records:
    *                             ids       - Only record IDs.
    *                             header    - Record header information.
    *                             timemap   - Record header + timemap details.
    *                             detail    - Record header + all details.
    *                             structure - Record header + all details + record type structure (for editing) - NOT USED.
    *
    *       CLIENT SIDE parameters (used for UI tracking and context)
    *       id - Unique ID to synchronize with the client-side request.
    *       source - ID of the HTML element that originated this search.
    *       qname - Original name of a saved search (used for messaging).

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

    }elseif(@$req_params['a'] == 'getdatehistogramdata'){ // returns array of lower and upper limit plus a count for each interval

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