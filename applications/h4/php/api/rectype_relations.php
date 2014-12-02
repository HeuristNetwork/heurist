<?php

    /**
    * Determines rectype relations for a certain database.
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
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

    if(isset($_REQUEST['db'])) {
        $dbName = $_REQUEST['db'];
        
        // Initialize a System object that uses the requested database
        $system = new System();
        if( $system->init($dbName) ){
            // Result object
            $result = new stdClass();
            $result->HeuristVersion = HEURIST_VERSION; 
            $result->HeuristBaseURL = HEURIST_BASE_URL;
            $result->HeuristDBName = $dbName;
            
            // Retrieving all nodes
            $rectypes = getRectypes($system);
            $result->nodes = $rectypes;
            
            // Retrieving all links
            $links = getLinks($system, $rectypes);
            $result->links = $links;
            
            // Returning result as JSON
            header('Content-type: application/json');
            print json_encode($result);
        }else {
            // Show construction error
            echo $system->getError();   
        }
    }else{
        echo "\"db\" parameter is required";
    }
    
    /**
    * Retrieves all RecTypes
    * @param mixed $system System reference
    * @return Array of nodes
    */
    function getRectypes($system) {
        $rectypes = array();
        
        // Select all rectype ids, names and count the occurence in the Record table
        $query = "SELECT d.rty_ID as id, d.rty_Name as name, COUNT(r.rec_RecTypeID) as count FROM defRecTypes d LEFT JOIN Records r ON d.rty_ID=r.rec_RecTypeID GROUP BY id";
        $res = $system->get_mysqli()->query($query);
        while($row = $res->fetch_assoc()) {   
            $rectype = new stdClass();
            $rectype->id = intval($row["id"]);
            $rectype->name = $row["name"];
            $rectype->count = intval($row["count"]);
            $rectype->image = HEURIST_ICON_URL . $row["id"] . ".png";
            
            //print_r($rectype);
            array_push($rectypes, $rectype);
        }    
        
        return $rectypes;
    }

    /**
    * Retrieve all detail types of a record type that point to other records
    * 
    * @param mixed $system   System reference
    * @param mixed $rectype  Record type
    */
    function getRelations($system, $rectype) {
        $relations = array();
        
        // Select all resource details that have "resource" as their type, filter NULL or empty id's
        $query = "SELECT rst_DetailTypeID as id, rst_DisplayName as name, COUNT(rd.dtl_ID) as count, dty.dty_PtrTargetRectypeIDs as ids FROM defRecStructure rst INNER JOIN defDetailTypes dty ON rst.rst_DetailTypeID=dty.dty_ID LEFT JOIN recDetails rd ON rd.dtl_DetailTypeID=rst.rst_DetailTypeID WHERE rst.rst_RectypeID=" .$rectype->id. " AND dty.dty_Type LIKE \"resource\" AND NOT (dty.dty_PtrTargetRectypeIDs IS NULL OR dty.dty_PtrTargetRectypeIDs='') GROUP BY rst.rst_DetailTypeID;";
        $res = $system->get_mysqli()->query($query);
        while($row = $res->fetch_assoc()) { 
            $relation = new stdClass();
            $relation->id = intval($row["id"]);
            $relation->name = $row["name"];
            $relation->count = intval($row["count"]);
            $relation->ids = $row["ids"];
            
            //print_r($relation);
            array_push($relations, $relation);
        } 
        
        return $relations;  
    }
    
    /**
    * Retrieves all record types that the relationship object points to
    * 
    * @param mixed $system   System reference
    * @param mixed $rectype  Parent rectype
    * @param mixed $relation Relation object
    */
    function getTargets($system, $rectype, $relation) {
        $targets = array();
        
        // Go through all ID's
        //echo "\nID's for relation #" . $relation->id . ": " . $relation->ids;
        $ids = explode(",", $relation->ids);
        
        foreach($ids as $id) {
            // Count how many types the $relation points to this id      
            $query = "SELECT COUNT(r2.rec_ID) as count, rl.rl_DetailTypeID, r1.rec_Title, r1.rec_RecTypeID, r2.rec_Title, r2.rec_RecTypeID FROM recLinks rl INNER JOIN Records r1 ON r1.rec_ID=rl.rl_SourceID INNER JOIN Records r2 ON r2.rec_ID=rl.rl_TargetID WHERE r1.rec_RecTypeID=" .$rectype->id. " AND r2.rec_RecTypeID=".$id;
            $res = $system->get_mysqli()->query($query);
            if($row = $res->fetch_assoc()) {
                $target = new stdClass();
                $target->id = intval($id);
                $target->count = intval($row["count"]);
                
                //print_r($target);
                array_push($targets, $target);
            }         
        }    
        
        return $targets;
    }
    
    function getIndex($rectypes, $target) {   
        for($i = 0; $i < sizeof($rectypes); $i++) {
            if($rectypes[$i]->id == $target->id) {
                return $i;
            }
        }
        return 0;
    }

    /**
    * Retrieves all links for a certain RecType
    * 
    * @param mixed $system  System reference
    * @param mixed $rectype Rectype reference
    */
    function getLinks($system, $rectypes) {
        $links = array();
        
        // Go through all rectypes
        for($i = 0; $i < sizeof($rectypes); $i++) {
            // Find relations
            $relations = getRelations($system, $rectypes[$i]); 
            // Find all targets for each relation
            foreach($relations as $relation) {
                $targets = getTargets($system, $rectypes[$i], $relation);
                // Construct a link for each target
                foreach($targets as $target) {
                    $link = new stdClass();
                    
                    $link->source = $i;
                    $link->target = getIndex($rectypes, $target);
                    $link->targetcount = $target->count;
                    $link->relation = $relation;

                    //print_r($link);
                    array_push($links, $link);      
                }
            }  
        }

        return $links;
    }
    
    

?>