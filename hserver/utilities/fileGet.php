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

//main purpose - download entity images
$entity_name = @$_REQUEST['entity'];
$recID = @$_REQUEST['recID'];
$db = @$_REQUEST['db'];
$filename = @$_REQUEST['file'];
$csv_encoding = @$_REQUEST['encoding'];

$error = null;

if(!$db){
    $error = "Db parameter is not defined";
}else 
if(!$entity_name && !$filename){
    $error = "Entity parameter is not defined";
}else 
if(!$filename){
    $error = "File name not defined"; //to get file from scratch
}else
if($entity_name && !($recID>0)){
    $error = "Entity ID is not defined";
}else{

    $system = new System(); //without db connection
    $system->initPathConstants($db);
    
    if($filename){ //download from scratch
        
        $file_read = HEURIST_FILESTORE_DIR.'scratch/'.$filename;

        $content_type = null;//'image/'.$file_ext;
        
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
        
        
    }else{

        $filename = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';

        $version  = @$_REQUEST['version'];
        if($version=='thumbnail' || $version=='icon'){
            $filename =  $filename.$version.'/';   
        }
        //find file by recID
        $filename = $filename.$recID.'.';
        
        $exts = array('png','jpg','jpeg','gif');
        
        $file_read = HEURIST_DIR.'hclient/assets/13x13.gif';
        $file_ext = 'gif';
        
        foreach ($exts as $ext) {
           
            if(file_exists($filename.$ext)){
                $file_read = $filename.$ext;
                $file_ext = $ext;
                break;
            }        
        }
        
        $content_type = 'image/'.$file_ext;
    }
    
    ob_start();    
    if($content_type) header('Content-type: '.$content_type);
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_read));
    @ob_clean();
    flush();        
    readfile($file_read);
    
}

if($error){
    if(!$entity_name){ //print error as text
        print $error;
    }else{ //@todo return error image
    
    }
}
?>

