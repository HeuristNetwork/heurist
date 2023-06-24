<?php
    /**
    * Mirador viewer. It uses customized Mirador viwer (from external folder) with annotation and image tools
    * If it is missed, it uses latest mirador distribution from unpkg.com
    * 
    * For annotations, heurist database must have either RT_MAP_ANNOTATION or RT_ANNOTATION
    * 
    * As a mirador viewer with annotation tool we use customized https://github.com/ProjectMirador/mirador-integration
    * Modified files are in mirador-integration-changes.zip in external5/mirador3 folder
    * To perform further customizations download mirador-integration repository
    * Install dependencies (use node.js v 16.20) including mirador-annotations 0.4.0
    * Apply changes from mirador-integration-changes.zip
    * To build webpack: npm run webpack
    * 
    * 
    * We pass to mirador-integration application 
    * endpointURL - url to heurist api that pass all requests to dbAnnotation.php
    * manifestUrl - url of iiif image (it needs for thumbnail creation for annotated area)
    * sourceRecordId - heurist record id - reference to image to be annotated 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


/*
Parameters:

manifest or iiif - obfuscation id for registred manifest
OR
iiif_image - obfuscation id for image,video or audio - manifest will be generated dynamically
OR
q  - standard heurist query - all suitable media files linked to records will be included into generated manifest 

if iiif_image is defined only this image will be included into manifest
if q only defined all images linked to record(s) will be included


*/
        $https = (isset($_SERVER['HTTPS']) &&
                    ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                    $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
        
        $baseUrl = ($https ? 'https://' : 'http://').
            (!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
            (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
            ($https && $_SERVER['SERVER_PORT'] === 443 ||
            $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT'])));

        $url = $baseUrl . '/' .  $_SERVER['REQUEST_URI'];  
        
        if(@$_REQUEST['manifest'] || @$_REQUEST['iiif']){
            //load manifest directly
            $url = str_replace('hclient/widgets/viewers/miradorViewer.php','', $url);
            $url = str_replace($_SERVER['QUERY_STRING'],
                'db='.$_REQUEST['db']
                    .'&file='.(@$_REQUEST['manifest']?$_REQUEST['manifest']:@$_REQUEST['iiif']),$url);
        }else{
            if(!@$_REQUEST['q'] && @$_REQUEST['iiif_image']){ //file obfuscatin id
                //find record linked to this media
                //$url = $url.'&q=*file @'.$_REQUEST['iiif_image'];
            }else if(!@$_REQUEST['q']){ //query not defined
                exit('Need to define either query or file ID');
            }else{
                if(strpos('format=iiif',$url)===false){
                        $url = $url.'&format=iiif';    
                }
            }
            //record_output creates manifest dynamically
            $url = str_replace('hclient/widgets/viewers/miradorViewer.php','hsapi/controller/record_output.php', $url);
        }
        
    
    //$_SERVER['QUERY_STRING'];
        $manifest_url = str_replace('&amp;','&',htmlspecialchars($url));
        
        $use_custom_mirador = file_exists(dirname(__FILE__).'/../../../external/mirador3/dist/main.js');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Lang" content="en">
<meta name="author" content="">
<meta name="description" content="">
<meta name="keywords" content="">
<title>Heurist Mirador Viewer</title>
<?php 
if($use_custom_mirador){
    print '<base href="../../../external/mirador3/"/>';    
}else{
?>
<script src="https://unpkg.com/mirador@latest/dist/mirador.min.js"></script>
<!-- By default uses Roboto font. Be sure to load this or change the font -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500">
<?php
}
?> 
</head>
<body>
<div id="mirador_container"></div>
<script>
<?php
    $dbname = @$_REQUEST['db'];
    if (!preg_match('[\W]', $dbname)){
?>      
    window.endpointURL = "<?php echo $baseUrl.'/h6-alpha/api/'.$dbname.'/annotations';?>";
    window.manifestUrl = "<?php echo $manifest_url;?>";
<?php    
    }
?>
    window.hideThumbs = <?php echo (@$_REQUEST['iiif_image']?'true':'false');?>; 
    window.sourceRecordId = <?php echo (@$_REQUEST['recID']>0?intval($_REQUEST['recID']):0);?>; 
</script>
<?php
if($use_custom_mirador){
    print '<script src="dist/main.js"></script>';
}else{
?>
<script type="text/javascript">
var mirador = Mirador.viewer({
  "id": "mirador_container",
  "windows": [
    {
      "loadedManifest": "<?php echo $manifest_url;?>"
      <?php echo (@$_REQUEST['iiif_image']?'':',"thumbnailNavigationPosition": "far-bottom"');?>
    }
  ]
});
</script>  
<?php
}
?> 
</body>
</html>