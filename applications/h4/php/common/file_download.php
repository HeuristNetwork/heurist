<?php
/**
* Download (or proxy) files that are registered in heursit database
*/
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_files.php');

$system = new System();
if($system->init(@$_REQUEST['db']) ){

    $fileid = @$_REQUEST['thumb'];
    if($fileid){
        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        if(file_exists($thumbfile)){
            downloadFile('image/png', $thumbfile);
        }else{
            //@todo $thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".HEURIST_DBNAME."&ulf_ID=".$file['ulf_ObfuscatedFileID'];
        }
    }else{
        
        $fileid = @$_REQUEST['id'];
        //find 
        $location = fileGetPathOrURL($system, $file_ids);
        if($location){
            $location = $location[0];
            if(file_exists($location[0])){
                downloadFile(null, $location);
            }else{
                header('Location: '.$location);
            }
        }
    }
}

/**
* direct file download
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename){

    if ($mimeType) {
      header('Content-type: ' .$mimeType);
    }else{
      header('Content-type: binary/download');
    }

    if($mimeType!="video/mp4"){
      header('access-control-allow-origin: *');
      header('access-control-allow-credentials: true');
    }
    readfile($filename);
}
?>
