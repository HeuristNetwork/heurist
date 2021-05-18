<?php
    /** 
    * Search facets based on current query or list of ids
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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

    
/*
 param facets    
 [{code:  , type:   , step: } ]

 code - list of rt and ft to biuld facet query
 type - field type @todo get it dynamically
 step - facet depth level

 1. loop for facets
 2. create search query
 3. create select that depends on field type and step
 4. execute query
 5. gather results
 
*/    
function recordSearchFacets_New2($system, $params, $ids, $currentUser, $publicOnly){
    
    $facets = $params['facets'];
    
//1. loop for facets
    foreach ($facets as $facet) {
        
//2. create search query

        //if $ids is not defined - substitute parameter with parent query
        $query_struct = _getFacetQuery($facet['code'], $params['qa']);
        
        
        $mysqli = $system->get_mysqli();
        
        $params2 = array('qa'=>$query_struct, 'w'=>@$params['w']);
        
        $aquery = get_sql_query_clauses_NEW($mysqli, $params2, $currentUser, $publicOnly);   
        
        
// 3. create select that depends on field type and step
        
        $query =  $select_clause.$aquery["from"]." WHERE ".$aquery["where"].$group_clause;

// 4. execute query


// 5. gather results

        
        
    }//for facets
    
    
    return null;
}
  
function _getFacetQuery( $code, $qparent) {
    return null;
    
}
  
?>
