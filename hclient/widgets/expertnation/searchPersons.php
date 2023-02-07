<?php

/**
* ExpertNation Persons search - searches for People using all fields in the database 
* including the person's names, places, schools, universities and colleges, 
* and the text of the Book of Remembrance.
* 
* It is possible to perform this action from client side as a sequence of queries
* However, it is realiable and faster to perform this search in th only request
* 
* parameters
* db - heurist database
* search - search string
* 
* returns persons ids
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

require_once (dirname(__FILE__).'/../../../hsapi/System.php');
require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/utils_db.php');


$system = new System();

while(true){

    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        $response = $system->getError();
        break;
    }

    $search = array();
    $persons_ids = array();

    //prepare search string    
    $params = array('db'=>$_REQUEST['db'], 'detail'=>'ids');

    $search = $_REQUEST['search']; //'"'.$_REQUEST['search'].'"';

    $uni_id = '';
    if(@$_REQUEST['uni_ID']>0){
        $uni_id  = ',"f:196":'.$_REQUEST['uni_ID'];
    }
       
    //1. search persons by title,name,family,bor descr
    if(!is_array($search)){
        $search = array($search);
    }
    $search_predicate_1 = array();
    $search_predicate_2 = array();
    foreach($search as $val){
        $val = str_replace('"','\\"',$val);
        $search_predicate_1[] = '"title":"'.$val.'"';
        $search_predicate_1[] = '"f:134":"'.$val.'"';        
        $search_predicate_2[] = '"title":"'.$val.'"';
    }
    //search for persons
    $search_predicate_1 = '"any":{'.implode(',',$search_predicate_1).'}';    
    //search for places
    $search_predicate_2 = implode(',',$search_predicate_2);
    if(count($search)>1){
        $search_predicate_2 = '"any":{'.$search_predicate_2.'}';    
    }
    
    $params['q'] = '{"t":10,'.$search_predicate_1.$uni_id.'}';

    $response = recordSearch($system, $params);
    if($response['status']==HEURIST_OK){
        $persons_ids = $response['data']['records'];
    }else{
        break;
    }
    $response['data']['q2'] = $params['q'];
    
    if(@$_REQUEST['uni_ID']>0){
        $uni_id  = ' f:196:'.$_REQUEST['uni_ID'];
    }
    
    //2. search places by title and persons related to place
    $params['q'] = '{"t":25,'.$search_predicate_2.'}';
    $params['rules'] = '[{"query":"t:24,26,29,33,37 linked_to:25","ignore":1,'
        .'"levels":[{"query":"t:10 linkedfrom:24,29,33,37'.$uni_id.'"},'
                  .'{"query":"t:27,31 linked_to:26-97","ignore":1,'
                    .'"levels":[{"query":"t:10 linked_to:27,31'.$uni_id.'"}]} ] }]';
    $params['rulesonly'] = 1;
    $response = recordSearch($system, $params);
    if($response['status']==HEURIST_OK){
        $persons_ids = array_merge_unique($persons_ids,  $response['data']['records']);
    }else{
        break;
    }
    
    //3. search institution by title and persons related to institution via schooling and tertiary edu
    $params['q'] = '{"t":26,'.$search_predicate_2.'}';
    
    $params['rules'] = '[{"query":"t:27,31 linked_to:26-97","ignore":1,'
                    .'"levels":[{"query":"t:10 linked_to:27,31'.$uni_id.'"}]}]';
    $params['rulesonly'] = 1;
    $response = recordSearch($system, $params);
    if($response['status']==HEURIST_OK){
        $persons_ids = array_merge_unique($persons_ids, $response['data']['records']);
    }else{
        break;
    }
        
    $response['data']['records'] = $persons_ids;
    $response['data']['count'] = count($persons_ids);
    $response['data']['reccount'] = count($persons_ids);
    $response['data']['mainset'] = null;
    $response['data']['q'] = $params['q'];

        //$response['data']['via_place'] = $persons_ids2;
        //$response['data']['place'] = $place_query;
        
        //$response['data']['via_inst'] = $persons_ids3;
        //$response['data']['inst'] = $params['q'].'&'.$params['rules'];
        
    
    break;
}//while
  
// Return the response object as JSON
header('Content-type: application/json;charset=UTF-8');
print json_encode($response);
  
?>
