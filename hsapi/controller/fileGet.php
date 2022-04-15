<?php
/**
* Service to get icons and thumbs for entities 
* (for recUploadFiles see file_download.php)
* 
* fileGet.php - 1) get image for given entity, record ID, version and color 
*               2) get or check file from code folders - tips, help, doc content
*               3) load file from scratch folder (tries to convert to UTF8) - for import terms
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

require_once(dirname(__FILE__)."/../System.php");

/*

id - record id
entity - by default recordtypes
def - if file not found return empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
version - thumb or thumbnail|icon|full (thumb is default)

*/


//main purpose - download entity images
$db = @$_REQUEST['db'];
$filename = @$_REQUEST['file'];

$error = null;

if(!$db){
    $error = "Db parameter is not defined";
}

$system = new System(); //without db connection and session - just paths
$system->initPathConstants($db);
    
if($filename){ //download from scratch (for csv import)

        //remove slashes - prevents Local file disclosure
        $filename = fileNameSanitize($filename, false);

        $file_read = HEURIST_FILESTORE_DIR.'scratch/'.$filename;
        
        if(!isPathInHeuristUploadFolder( $file_read ))        
        {
            print 'Temporary file (uploaded csv data) '.$filename. ' not found';                
            exit();
        }

        $content_type = null;//'image/'.$file_ext;
        
        $csv_encoding = @$_REQUEST['encoding'];
        
        if($csv_encoding && $csv_encoding!='UTF-8'){ //force convert to utf8       
        
            $handle = @fopen($file_read, "r");
            if (!$handle) {
                $s = null;
                if (! file_exists($file_read)) $s = ' does not exist.<br><br>'
                    .'Please clear your browser cache and try again. if problem persists please '.CONTACT_HEURIST_TEAM.' immediately';
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
        _download_file($file_read, null);
        
}else{  //download entity images (icons, thumbs) for entity folder in HEURIST_FILESTORE_DIR
    
        
        $content_type = 'image/png';  

        $rec_id = @$_REQUEST['icon'];  
        if($rec_id==null) $rec_id = @$_REQUEST['id'];  
        
        //default - defRecTypes
        $entity_name = @$_REQUEST['entity'];
        if(!$entity_name) $entity_name = 'rty';
        $entity_name = entityResolveName($entity_name);
        
        //icon, thumb, full
        $viewmode = @$_REQUEST['version']; 
        if(!$viewmode){
            $viewmode = ($entity_name=='defRecTypes')?'icon':'thumbnail';   
        }
        if($viewmode=='thumb') $viewmode='thumbnail';
        
        if($rec_id && substr($rec_id,0,4)=='term'){
            //backward support - icons for Digital Harlem
            $rec_id = substr($rec_id, 4);
            $entity_name  = 'trm';
            $viewmode = 'full';
            $path = HEURIST_TERM_ICON_DIR;
        }else
        if($entity_name=='sysDatabases' && $rec_id){
            
            $db_name = $rec_id;
            if(strpos($rec_id,'hdb_')===0){
                $db_name = substr($rec_id,4);    
            }
            $rec_id = 1;    
            $path = HEURIST_FILESTORE_ROOT . $db_name . '/entity/sysIdentification/';    
        }else{
            
            $path = HEURIST_FILESTORE_DIR . 'entity/'.$entity_name.'/';
        } 
        
        if($viewmode!='full'){
            $path = $path.$viewmode.'/';
        }
        $filename = $path.$rec_id;
        
        //if file does not exist in entity folder
        //backward capability - copy from old rectype-icons to new location - entity folder
        if($entity_name=='defRecTypes' && !(file_exists($filename.'.png') || file_exists($filename.'.svg'))){
            
            if($viewmode=='thumbnail'){
                $old_filename = HEURIST_ICON_DIR . 'thumb/th_' . $rec_id . '.png';
            }else{
                $old_filename = HEURIST_ICON_DIR . $rec_id .'.png';
            }
            if(file_exists($old_filename)){
                
                //recreate entity folder
                if(file_exists($path) || folderCreate($path, true)){
                    copy($old_filename, $filename.'.png');
                }else{
                    //error_log('CANT CREATE FOLDER '.$path);
                }
            }
        }
        
        $exts = array('png','jpg','svg','jpeg','jpe','jfif','gif');
        foreach ($exts as $ext){
            if(file_exists($filename.'.'.$ext)){
                if($ext=='jpg' || $ext=='jfif' || $ext=='jpe'){
                    $content_type = 'image/jpeg';
                }else if($ext=='svg'){
                    $content_type = 'image/svg+xml';
                }else{
                    $content_type = 'image/'.$ext;    
                }
                $filename = $filename.'.'.$ext;
                break;
            }
        }
            
        //entity id either not defined or requested file doesn't exist
        //editmode: empty gif (0) or add image gif (1) or default icon/thumb for entity (2), or (check)  'ok' if it exists or '' missing
        
        //what return by default?
        // 3 (or check) - return "ok" if file exists otherwise "not found"
        // 2 - entity default icon or thumb
        // 1 - returns image with invitation "add image"
        // otherwise it returns empty image placeholder (100x100 or 16x16 for icons)
        $default_mode = @$_REQUEST['def'];
        if($default_mode=='check') $default_mode = 3;
        else if($default_mode==null) $default_mode = 2;

                   
        if(file_exists($filename) && !is_dir($filename)){
            if($default_mode==3){ //check

                $response = array('status'=>HEURIST_OK, 'data'=>'ok');
                header('Content-type: application/json;charset=UTF-8');
                print json_encode($response);

            }else{
                
                //color, bg, circle
                if(@$_REQUEST['color'] && $ext!='svg'){
                    UtilsImage::changeImageColor($filename, null, @$_REQUEST['color'], @$_REQUEST['circle'], @$_REQUEST['bg']);    
                }else{
                    _download_file($filename, $content_type);    
                }
            }

        }else if($default_mode==3){ //check existance
            
                $response = array('status'=>HEURIST_OK, 'data'=>'not found');
                header('Content-type: application/json;charset=UTF-8');
                print json_encode($response);
        
        }else{
        
            if(@$_REQUEST['entity'] && ($default_mode=='view' || $default_mode==2)) //get entity default icon or thumb
            {
                //at the moment we don't have full images that describe entity - only icons and thumbs
                $filename = dirname(__FILE__).'/../../hclient/assets/'
                                .$_REQUEST['entity'].(($viewmode=='icon')?'':'_thumb').'.png';    
                //$filename = dirname(__FILE__).'/../../hclient/assets/cross-red.png';
                                
                if(file_exists($filename) && !is_dir($filename)){
                    _download_file($filename, $content_type);
                    exit();
                }
            }
                    
            if ($default_mode=='edit' || $default_mode==1){ //show invitation to add image
                _download_file(dirname(__FILE__).'/../../hclient/assets/100x100click.png', $content_type);
            }else {
                $content_type = 'image/gif';
                if($viewmode = 'icon'){
                    _download_file(dirname(__FILE__).'/../../hclient/assets/16x16.gif', $content_type);
                }else{
                    _download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', $content_type);
                }
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

//
//
// 
function _download_file($filename, $content_type){
    
        ob_start();    
        if($content_type) header('Content-type: '.$content_type);
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();        
        readfile($filename);
}    
?>

