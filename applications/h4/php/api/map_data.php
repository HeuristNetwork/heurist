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
    require_once (dirname(__FILE__).'/../common/db_files.php');
    
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
        $obj->id = intval($row["rec_ID"]);
        $obj->title = $row["rec_Title"];
        $obj->rectypeID = intval($row["rec_RecTypeID"]);
        return $obj;
    }
    
    /**
    * Finds the url that belongs to the file with the given fileID in the given system
    * 
    * @param mixed $system System reference
    * @param mixed $fileID FileID
    * @return mixed Image URL
    */
    function getImageURL($system, $fileID) {
        $paths = fileGetPath_URL_Type($system, $fileID);
        if(isset($paths[0][0])) {
            return HEURIST_FILESTORE_URL . $paths[0][0];
        }
        return HEURIST_FILESTORE_URL . "unknown.jpg";
    }
    
    /**
    * Retrieves a data source record out of the database
    * 
    * Allowed types according to field type 'Data source' (3-1083):
    * - Map image file (tiled)        RT_TILED_IMAGE_LAYER
    * - KML                           RT_KML_LAYER
    * - Shapefile                     RT_SHP_LAYER
    * - Map image file (untiled)      RT_IMAGE_LAYER
    * - Mappable query                RT_QUERY_LAYER
    * 
    * @param mixed $system System reference
    * @param mixed $id Record ID
    */
    function getDataSource($system, $id) {
        global $recordQuery;
        global $detailQuery;
        $record = new stdClass();
        
        // Select the record
        $query = $recordQuery." WHERE r.rec_ID=".$id;
        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($query);

        if ($res) {
            $row = $res->fetch_assoc();
            if($row) {
                // Data object containing the row values
                $record = getRecord($row);
                
                // Retrieve extended details
                $query = $detailQuery . $record->id;
                $details = $mysqli->query($query);
                if($details) {
                    // Source record type check
                    if($record->rectypeID == RT_TILED_IMAGE_LAYER) {
                        // Map image file (tiled)    
                        while($detail = $details->fetch_assoc()) {  
                            // Values
                            //print_r($detail);      
                            $record->type = "RT_TILED_IMAGE_LAYER";
                            $type = $detail["dtl_DetailTypeID"]; 
                            $value = $detail["dtl_Value"];

                        }
                        
                    }else if($record->rectypeID == RT_KML_LAYER){
                        // KML    
                        while($detail = $details->fetch_assoc()) {  
                            // Values
                            //print_r($detail);
                            $record->type = "RT_KML_LAYER";
                            $type = $detail["dtl_DetailTypeID"]; 
                            $value = $detail["dtl_Value"];

                        }
                        
                    }else if($record->rectypeID == RT_SHP_LAYER){
                        // Shapefile   
                        while($detail = $details->fetch_assoc()) {  
                            // Values
                            //print_r($detail);
                            $record->type = "RT_SHAPE_LAYER";
                            $type = $detail["dtl_DetailTypeID"]; 
                            $value = $detail["dtl_Value"];

                        }
                        
                        
                         
                    }else if($record->rectypeID == RT_IMAGE_LAYER){
                        // Map image file (untiled)
                        while($detail = $details->fetch_assoc()) {  
                            // Values
                            //print_r($detail);
                            $record->type = "RT_SHAPE_LAYER";
                            $type = $detail["dtl_DetailTypeID"]; 
                            $value = $detail["dtl_Value"];

                        } 
                        
                    }else if($record->rectypeID == RT_QUERY_LAYER){
                        // Mappable query  
                        while($detail = $details->fetch_assoc()) {  
                            // Values
                            //print_r($detail);
                            $record->type = "RT_QUERY_LAYER";
                            $type = $detail["dtl_DetailTypeID"]; 
                            $value = $detail["dtl_Value"];

                        }
                    }else{
                        // UNKNOWN TYPE
                        $record->type = "UNKNOWN";
                    }
                }
            }
        }
        return $record;
    }
    
    /**
    * Retrieves a Map Layer object from the database
    * 
    * Layer object:
    * -----------------------------------------
    * - id: Record ID
    * - title: Record title
    * - rectypeID: Record type ID
    * -----------------------------------------
    * - minZoom: Minimum zoom
    * - maxZoom: Maximum zoom  
    * - opacity: Opacity level  
    * - thumbnail: Thumbnail file
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
                        //echo "\nLAYER | Type: #" . $type . " --> " . $value;
                                
                        // Type check
                        if($type == DT_MAXIMUM_ZOOM) {
                            // Maximum zoom
                            $layer->maxZoom = floatval($value);
                             
                        }else if($type == DT_MINIMUM_ZOOM) {
                            // Minimum zoom
                            $layer->minZoom = floatval($value); 
                             
                        }else if($type == DT_OPACITY) {
                            // Opacity
                            $layer->opacity = floatval($value);
                            
                        }else if($type == DT_THUMBNAIL) {
                            // Uploaded thumbnail
                            $layer->thumbnail = getImageURL($system, $detail["dtl_UploadedFileID"]);
                            
                        }else if($type == DT_DATA_SOURCE) {
                            // Data source
                            $layer->dataSource = getDataSource($system, $value);
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
    * - id: Record ID
    * - title: Record title
    * - rectypeID: Record type ID
    * -----------------------------------------
    * - topLayer: Layer object
    * - layers: Array of Layer objects
    * - long: Longitude
    * - lat: Latitude
    * - minZoom: Minimum zoom
    * - maxZoom: Maximum zoom 
    * - minorSpan: Initial minor span in degrees
    * - thumbnail: Thumbnail file   
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
                    $document->layers = array();
                    
                    // Parse all details 
                    while($detail = $details->fetch_assoc()) {  
                        // Values
                        //print_r($detail);
                        $type = $detail["dtl_DetailTypeID"]; 
                        $value = $detail["dtl_Value"];
                        //echo "\nMAP DOCUMENT | Type: #" . $type . " --> " . $value;

                        // Type check
                        if($type == DT_TOP_MAP_LAYER) {
                            // Top map layer  
                            // Pointer to recID value
                            $document->toplayer = getMapLayer($system, $value);
    
                        }else if($type == DT_MAP_LAYER) {
                            // Map layer
                            // Pointer to recID value 
                            array_push($document->layers, getMapLayer($system, $value));
                            
                        }else if($type == DT_LONGITUDE_CENTREPOINT) {
                            // Longitude centrepoint
                            $document->long = floatval($value);
                            
                        }else if($type == DT_LATITUDE_CENTREPOINT) {
                            // Latitude centrepoint
                            $document->lat = floatval($value);
                        }else if($type == DT_MAXIMUM_ZOOM) {
                            // Maximum zoom
                            $document->maxZoom = floatval($value);
                             
                        }else if($type == DT_MINIMUM_ZOOM) {
                            // Minimum zoom
                            $document->minZoom = floatval($value); 
                             
                        }else if($type == DT_MINOR_SPAN) {
                            // Initial minor span
                            $document->minorSpan = floatval($value);
                            
                        }else if($type == DT_THUMBNAIL) {
                            // Uploaded thumbnail 
                            $document->thumbnail = getImageURL($system, $detail["dtl_UploadedFileID"]);
                            
                        } 
                    }                   
                }
                
                //print_r($document);
                array_push($documents, $document);
            }
        }
        
        //print_r($documents);
        return $documents;
    }
    
    

    // Initialize a System object that uses the requested database
    $system = new System();
    if( $system->init(@$_REQUEST['db']) ){                               
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