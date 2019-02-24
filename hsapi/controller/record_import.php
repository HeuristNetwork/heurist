<?php
   /**
    * Interface/Controller for  xml, json in heurist format import 
    * 
    * for external formats import see importController.php
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
require_once(dirname(__FILE__)."/../System.php");

require_once (dirname(__FILE__).'/../import/importParser.php'); //parse CSV, KML and save into import table
require_once (dirname(__FILE__).'/../dbaccess/db_records.php');
require_once (dirname(__FILE__).'/../structure/dbsImport.php');

set_time_limit(0);
    
$response = null;

$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{
    
   if(!$system->is_admin()){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'Administrator permissions are required');
   }else{
       
        $action = @$_REQUEST["action"];
        $res = false;        
        
        // I. sources are
        //1) url to remote database with search query. use saveURLasFile/loadRemoteContent and save to scratch folder
            // (if on the same domain request this database directly)
        //2) file that is uploaded with help fileupload widget and utilities/fileUpload.php to scratch folder
        if(@$_REQUEST["url"]){
            
            //@todo similar in dbsImport -> _getDatabaseDefinitions
            
            $rawdata = loadRemoteURLContent($url, false); //use proxy 
            
            $res = ImportParser::saveToTempFile( $rawdata, 'json' );  //save data in temp file
            
            $tmp_file = @$res['fullpath'];
            
        }else if(@$_REQUEST["upload_file_name"]){ //file with data already exists in scratch
        
            $tmp_file = @$_REQUEST["upload_file_name"];
        }
        
        if(!file_exists($tmp_file)){
            $response = $system->addError(HEURIST_ERROR, 'Import data file not found');
        }else{
            // II. load data (json or xml) to records array
            // get all record types that are not found in target database (by concept code)
            //@todo https://github.com/salsify/jsonstreamingparser
            try{
                $data = json_decode(file_get_contents($tmp_file), true);
            } catch (Exception  $e){
                $data = null;
            }   
            $imp_rectypes = @$data['heurist']['database']['rectypes'];
            if($data==null || !$imp_rectypes){
                $response = $system->addError(HEURIST_ERROR, 'Import data has wrong data');
            }else{
                
                if($action=='preview'){
                    
                    $database_defs = dbs_GetRectypeStructures($system, null, 2);
                    $database_defs = array('rectypes'=>$database_defs);
                    
                    foreach ($imp_rectypes as $rtid=>$rt){
                        $conceptCode = $rt['code'];
                        $local_id = DbsImport::getLocalCode('rectypes', $database_defs, $conceptCode, false);
                        $imp_rectypes[$rtid]['localid'] = $local_id;
                    }
                    
                    //return array of $imp_rectypes - record types to be imported
                    $response = array("status"=>HEURIST_OK, "data"=> $imp_rectypes );
                    
                }else{
                    // III. import remote definitions
                    // import missed record types (along with dependencies)  dbsImport.php
                    // maintain mapping remote->local definitions (dbsImport->xxx_correspondence)
                    $isOK = false;  
                    ini_set('max_execution_time', 0);
                    $importDef = new DbsImport( $system );

                    if($importDef->doPrepare(  array('defType'=>'rectype', 
                                'databaseID'=>@$data['heurist']['database']['id'], 
                                'definitionID'=>array_keys($imp_rectypes) )))
                    {
                        $isOK = $importDef->doImport();
                    }

                    if(!$isOK){
                        $err = $system->getError();
                        if($err && $err['status']!=HEURIST_NOT_FOUND){
                            $system->error_exit(null);  //produce json output and exit script
                        }
                    }
                    // IV. import records
                    //owner will be replaced with default owner
                    
                    //record type will be replaced with new correspondent record type
                    $defs_correspondence = $importDef->getCorrespondences();
                    
                    $imp_rectypes = @$data['heurist']['database']['rectypes'];
                    
                    // replace recordtypes and terms with new ids 
                    $response = recordSave($system, $record);
                    
                }
            }
        }
   }
}    

header('Content-type: application/json');
print json_encode($response);
?>
