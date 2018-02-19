<?php
/**
* 
* fileGet.php - 1) get image for given entity, record ID, version and color 
*               2) get or check file from code folders - tips, help, doc content
*               3) load file from scratch folder (tries to convert to UTF8) - for import terms
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
//require_once(dirname(__FILE__).'/../../ext/jquery-file-upload/server/php/UploadHandler.php');

//secondary purpose - check file existance in code
$path = @$_REQUEST['check'];
if($path){
  if(file_exists(HEURIST_DIR.$path)){
        print 'ok';      
  }else{
        print '';
  }
  exit();
}

/*

id - record id
entity - by default recordtypes
def - if file not found return empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
version - thumb|icon|full (thumb is default)

*/


//main purpose - download entity images
$db = @$_REQUEST['db'];
$entity_name = @$_REQUEST['entity'];
$filename = @$_REQUEST['file'];

$error = null;

if(!$db){
    $error = "Db parameter is not defined";
}
/*
if(!$entity_name && !$filename){
    $error = "Entity parameter is not defined";
}else 
if(!$filename){
    $error = "File name not defined"; //to get file from scratch
}else
if(!$entity_name){
    $error = "Entity is not defined";
}else{
*/
$system = new System(); //without db connection and session - just paths
$system->initPathConstants($db);
    
if($filename){ //download from scratch
         
        $file_read = HEURIST_FILESTORE_DIR.'scratch/'.$filename;

        $content_type = null;//'image/'.$file_ext;
        
        $csv_encoding = @$_REQUEST['encoding'];
        
        if($csv_encoding && $csv_encoding!='UTF-8'){ //force convert to utf8       
        
            $handle = @fopen($file_read, "r");
            if (!$handle) {
                $s = null;
                if (! file_exists($file_read)) $s = ' does not exist.<br><br>'
                    .'Please clear your browser cache and try again. if problem persists please report immediately to Heurist developers (info at HeuristNetwork dot org)';
                else if (! is_readable($file_read)) $s = ' is not readable';
                else $s = ' could not be read';        
                
                if($s){
                    print 'Temporary file (uploaded csv data) '.$file_read. $s;                
                    exit();
                }
            }
        
            setlocale(LC_ALL, 'en_US.utf8');
            fclose($handle);
            /*
            // read header only - since import terms is no big we can omit this section
            $line = fgets($handle, 1000000);
            fclose($handle);
            if(!mb_check_encoding( $line, 'UTF-8' )){
               $line = mb_convert_encoding( $line, 'UTF-8'); 
            }
            if(!$line){
                print 'Temporary file (uploaded csv data) '.$file_read
                .' can\'t be converted to UTF-8. Please open it in any advanced editor and save with UTF-8 text encoding';
                exit();
            } 
            */           
            $content = file_get_contents($file_read);
            $content = mb_convert_encoding( $content, 'UTF-8', $csv_encoding);
            if(!$content){
                print 'Temporary file (uploaded csv data) '.$file_read
                .' can\'t be converted to UTF-8. Please open it in any advanced editor and save with UTF-8 text encoding';
                exit();
            } 
               
            //$encoded_file_name = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $original_filename);      
            $res = file_put_contents($file_read, $content);
            unset($content);
            if(!$res){
                print 'Cant save temporary file (with UTF-8 encoded csv data) '.$file_read;
                exit();
            }
            
        }
        download_file($file_read, null);
        
    }else{
        
        $content_type = 'image/png';

        $viewmode = @$_REQUEST['version'] ?$_REQUEST['version']:'thumb'; //icon, thumb, full
        if($viewmode=='thumbnail') $viewmode='thumb';
        
        $rec_id = @$_REQUEST['id'];  
        
        $path = HEURIST_FILESTORE_DIR . 'entity/'.$entity_name.'/';
                
        if($entity_name=='sysDatabases' && $rec_id){
            
            $db_name = $rec_id;
            if(strpos($rec_id,'hdb_')===0){
                $db_name = substr($rec_id,4);    
            }
            $rec_id = 1;    
            $path = HEURIST_FILESTORE_ROOT . $db_name . '/entity/sysIdentification/';    
            
        }
        
        if($rec_id>0){
        
            if($viewmode=='thumb'){
                $filename = $path.'thumbnail/'.$rec_id.'.png'; 
            }else if($viewmode=='icon'){
                $filename = $path.'icon/'.$rec_id.'.png';    
            }else{
                $exts = array('png','jpg','jpeg','gif');
                foreach ($exts as $ext){
                    if(file_exists($path.$rec_id.'.'.$ext)){
                        $content_type = 'image/'.$ext;
                        $filename = $path.$rec_id.'.'.$ext;
                        break;
                    }
                }
            }                  
            if(file_exists($filename)){
                download_file($filename, $content_type);
                exit();
            }
        }    
        
        //entity id either not defined or requested file doesn't exist
        //editmode: empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
        $default_mode = @$_REQUEST['def'] ?$_REQUEST['def']:2;
        
        if($default_mode==2) //get entity default icon or thumb
        {
            //at the moment we don't have full images that describe entity - only icons and thumbs
            $filename = dirname(__FILE__).'/../../hclient/assets/'
                            .$_REQUEST['entity'].(($viewmode=='icon')?'':'_thumb').'.png';    
                            
            if(file_exists($filename)){
                download_file($filename, $content_type);
                exit();
            }
        }
                
        if ($default_mode==1){ //show invitation to add image
            download_file(dirname(__FILE__).'/../../hclient/assets/100x100click.png', $content_type);
        }else {
            $content_type = 'image/gif';
            if($viewmode = 'icon'){
                download_file(dirname(__FILE__).'/../../hclient/assets/16x16.gif', $content_type);
            }else{
                download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', $content_type);
            }
        }
        exit();
}

if($error){
    if(!$entity_name){ //print error as text
        print $error;
    }else{ //@todo return error image
    
    }
}

function download_file($filename, $content_type){
        ob_start();    
        if($content_type) header('Content-type: '.$content_type);
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();        
        readfile($filename);
}    
?>

