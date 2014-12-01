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
            $rectype->id = $row["id"];
            $rectype->name = $row["name"];
            $rectype->count = $row["count"];
            $rectype->image = HEURIST_ICON_URL . $row["id"] . ".png";
            array_push($rectypes, $rectype);
        }    
        
        return $rectypes;
    }

    
    /**
    * Retrieves all records that the given RecType is pointing to
    * 
    * @param mixed $system  System reference
    * @param mixed $rectype The RecType to do the check for
    */
    function getTargets($system, $rectype) {
        $targets = array();
        
        // Select the rectype id's where each detail type points to if the parent record's rectype id = x  
        $query = "SELECT dt.dty_PtrTargetRectypeIDs as ids FROM Records r INNER JOIN recDetails rd ON r.rec_ID=rd.dtl_RecID INNER JOIN defDetailTypes dt ON rd.dtl_DetailTypeID=dt.dty_ID WHERE r.rec_RecTypeID=" .$rectype->id. " AND dt.dty_PtrTargetRectypeIDs IS NOT NULL AND NULLIF(dt.dty_PtrTargetRectypeIDs, '') IS NOT NULL GROUP BY dt.dty_PtrTargetRectypeIDs";
        $res = $system->get_mysqli()->query($query);
        while($row = $res->fetch_assoc()) {
            // Split ID's and create targets
            $ids = explode(",", $row["ids"]);
            foreach($ids as $id) {
                $target = new stdClass();
                $target->id = $id;
                $target->count = 5;
                array_push($targets, $target);
            }
        }  
        
        return $targets;
    }
    
    
    /**
    * Retrieves all links for a certain RecType
    * 
    * @param mixed $system  System reference
    * @param mixed $rectype Rectype reference
    */
    function getLinks($system, $rectypes) {
        $links = array();
        
        // Go through each RecType
        if(sizeof($rectypes) > 0) {
            for($i = 0; $i < sizeof($rectypes); $i++) {
                // Find to which RecTypes this RecType points
                $targets = getTargets($system, $rectypes[$i]);
                
                // Go through each target
                if(sizeof($targets > 0)) {
                    foreach($targets as $target) {
                        // Check what index the link target record has in our $rectypes array
                        for($j = 0; $j < sizeof($rectypes); $j++) {
                            if($target->id == $rectypes[$j]->id) {
                                // Link
                                $link = new stdClass();
                                $link->source = $i;
                                $link->target = $j; 
                                $link->targetcount = $target->count;
                                $link->value = 1;
                                
                                // Relation
                                $relation = new stdClass();
                                $relation->name = "TODO";
                                $relation->count = 1;
                                $link->relation = $relation;
                                
                                array_push($links, $link);
                                break;
                            }   
                        }      
                    }
                }   
            }
        }
        
        return $links;
    }
    
    

?>