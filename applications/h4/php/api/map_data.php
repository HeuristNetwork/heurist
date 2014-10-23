<?php

    /**
    * Retrieves map data for a certain database.
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
    
    $recordQuery = "SELECT r.rec_ID, r.rec_Title, r.rec_RecTypeID FROM Records r INNER JOIN defRecTypes d ON r.rec_RecTypeID=d.rty_ID";
    $detailQuery = "SELECT * FROM recDetails rd WHERE rd.dtl_RecID=";
    
    /**
    * Constructs a record object from a SQL result row
    * Record object: 
    * -------------------------------
    * - ID: the id
    * - Title: record title
    * - RectypeID: record type id
    * -------------------------------
    * 
    * @param mixed $row SQL row
    * @return stdClass Record object
    */
    function getRecord($row) {
        $obj = new stdClass();
        $obj->id = $row["rec_ID"];
        $obj->title = $row["rec_Title"];
        $obj->rectypeID = $row["rec_RecTypeID"];
        return $obj;
    }
    
    /**
    * Retrieves a Map Layer object from the database
    * 
    * Layer object:
    * -------------------------------
    * - ID: the id
    * - Title: record title
    * - RectypeID: record type id
    * -------------------------------
    * 
    * @param mixed $system System reference
    * @param mixed $id     Map Layer record ID
    */
    function getMapLayer($system, $id) {
        //echo "getMapLayer for id".$id;
        global $recordQuery;
        global $detailQuery;
        $layer = new stdClass();
        
        // Retrieve record information
        $query =  $recordQuery." WHERE r.rec_ID=$id";
        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($query);
        if ($res) {
            // Get details
            $row = $res->fetch_assoc();
            if($row) {
                // Layer object containing the row values
                $layer = getRecord($row);
                
                // Retrieve extended details
                $query = $detailQuery . $layer->id;
                $details = $mysqli->query($query);
                if($details) {
                    // Parse all details 
                    while($detail = $details->fetch_assoc()) {  
                        // Values
                        //print_r($detail);
                        $type = $detail["dtl_DetailTypeID"]; 
                        $value = $detail["dtl_Value"];
                        
                        // Type check
                        if($type == 120) {
                            // Maximum zoom
                            $layer->maxZoom = $value;
                             
                        }else if($type == 119) {
                            // Minimum zoom
                            $layer->minZoom = $value;  
                        }else if($type == 127) {
                            // Opacity
                            $layer->opacity = $value;
                        }else if($type == 39) {
                            // Uploaded file ID
                            // Get file somehow
                            
                        }
                    }
                    
                    
                }
            }
        } 
        
        //print_r($layer);
        return $layer;
    }

    /**
    * Returns an array of all Map Documents for this database
    * 
    * Document object:
    * -----------------------------------------
    * - ID: Record ID
    * - Title: Record title
    * - RectypeID: Record type ID
    * -----------------------------------------
    * - Long: Longitude
    * - Lat: Latitude
    * - Top layer: Layer object
    * - Layers: Array of Layer objects
    * - Details: Array containing all details
    * ------------------------------------------
    * 
    * @param mixed $system System reference
    */
    function getMapDocuments($system) {
        //echo "getMapDocuments() called!";
        global $recordQuery;
        global $detailQuery;
        $documents = array();
        
        // Select all Map Document types
        $query = $recordQuery." WHERE d.rty_IDInOriginatingDB=".RT_MAP_DOCUMENT;
        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($query);
        if ($res) {
            // Loop through all rows
            while($row = $res->fetch_assoc()) {
                // Document object containing the row values
                $document = getRecord($row);
                
                // Retrieve extended details
                $query = $detailQuery . $document->id;
                $details = $mysqli->query($query);
                if($details) {
                    // New attributes
                    $document->details = array();
                    $document->layers = array();
                    
                    // Parse all details 
                    while($detail = $details->fetch_assoc()) {  
                        // Values
                        //print_r($detail);
                        $type = $detail["dtl_DetailTypeID"]; 
                        $value = $detail["dtl_Value"];
                        
                        // Type check
                        if($type == 131) {
                            // Top map layer  
                            // Pointer to recID value
                            $document->toplayer = getMapLayer($system, $value);
    
                        }else if($type == 1096) {
                            // Map layer
                            // Pointer to recID value 
                            array_push($document->layers, getMapLayer($system, $value));
                            
                        }else if($type == 114) {
                            // Longitude centrepoint
                            $document->long = $value;
                            
                        }else if($type == 115) {
                            // Latitude centrepoint
                            $document->lat = $value;
                        }
                    }                   
                }
                
                print_r($document);
                array_push($documents, $document);
            }
        }
        
        //print_r($documents);
        return $documents;
    }
    
    

    // Initialize a System object that uses the requested database
    $system = new System();
    if( $system->init(@$_REQUEST['db']) ){
        // Globals
        echo "\n\nRT INTERPRETATION: " . RT_INTERPRETATION;  
        echo "\nRT MAP DOCUMENT: " . RT_MAP_DOCUMENT . "\n\n";  
                                              
        // Get all Map Documents
        $documents = getMapDocuments($system);

        // Return the response object as JSON
        header('Content-type: application/json');
        print json_encode($documents);
    }else {
        // Show construction error
        echo $system->getError();   
    }
    exit();

?>