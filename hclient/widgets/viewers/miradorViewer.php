<?php
/**
* miradorViewer.php - Inits and handles Mirador viewer. 
* 
* It uses customized Mirador viewer (from external folder) with annotation and image tools
* If it is missed, it uses latest mirador distribution from unpkg.com
*
* For annotations, heurist database must have record type either RT_IIIF_ANNOTATION or RT_ANNOTATION
*
* As a mirador viewer with annotation tool we use customized https://github.com/ProjectMirador/mirador-integration
* Modified files are in mirador-integration-changes.zip in external5/mirador3 folder
* To perform further customizations download mirador-integration repository
* Install dependencies (use node.js v 16.20) including mirador-annotations 0.4.0
* Apply changes from mirador-integration-changes.zip
* To build webpack: npm run webpack
*
* We pass to mirador-integration application
* endpointURL - url to heurist api that pass all requests to dbAnnotation.php
* manifestUrl - url of iiif image (it needs for thumbnail creation for annotated area)
* sourceRecordId - heurist record id - reference to image to be annotated
*
* @project     Heurist academic knowledge management system
* @package  hclient\widgets\viewers
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
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

$dbname = @$_REQUEST['db'];
$rec_ID = intval(@$_REQUEST['recID']);
$manifestRecID = 0;
$canvasUri = null;
$baseUrl = null;
$system = null;
$preparedParams = null;

$validDatabaseName = !preg_match('[\W]', $dbname);
$needsSystem = $validDatabaseName && (
    array_key_exists('preparedID', $_REQUEST) ||
    ($rec_ID>0 && @$_REQUEST['iiif_image']==null && @$_REQUEST['iiif']==null)
);

if($needsSystem){

require_once dirname(__FILE__).'/../../../autoload.php';

    $system = new hserv\System();
    if( ! $system->init($_REQUEST['db'], true, false) ){
        //get error and response
        $system->errorExitApi();//exit from script
    }
    //get baseURL
    $baseUrl = defined('HEURIST_SERVER_URL')?HEURIST_SERVER_URL:null;

    if(array_key_exists('preparedID', $_REQUEST)){

        $params = $_REQUEST;
        $preferredType = array_key_exists('file', $params) ? 'export' : 'export-feed';
        $fallbackType = $preferredType === 'export' ? 'export-feed' : 'export';

        if(!\hserv\utilities\USystem::getPreparedParameters($system, $preferredType, $params)){
            $params = $_REQUEST;
            if(!\hserv\utilities\USystem::getPreparedParameters($system, $fallbackType, $params)){
                $system->errorExitApi(null, null, false);
            }
        }

        unset($params['preparedID'], $params['preparedMode'], $params['preparedType']);
        $params['db'] = $dbname;
        $_REQUEST = array_merge($_REQUEST, $params);
        $preparedParams = $params;
    }
}
//if database and record id are defined we take manifest url from database

if($system!=null && $rec_ID>0 && @$_REQUEST['iiif_image']==null && @$_REQUEST['iiif']==null){

    //detect is this mirador image or annotation
    if($system->defineConstant('RT_IIIF_ANNOTATION')){
        $system->defineConstant('RT_IIIF_MANIFEST');

        $res = recordSearchByID($system, $rec_ID, false, 'rec_ID,rec_RecTypeID');
        $system->defineConstant('DT_URL');
        $mysqli = $system->getMysqli();


        if($res['rec_RecTypeID']==RT_IIIF_ANNOTATION){
            //find parent record with iiif image - it returns obfuscation id

            $query = 'SELECT dtl_RecID, ulf_ObfuscatedFileID '
                .' FROM recLinks, recDetails, recUploadedFiles '
                .' WHERE rl_SourceID='.$rec_ID
                .' AND dtl_RecID=rl_TargetID '  //'AND dtl_DetailTypeID IN ('.implode(',',$file_field_types).')'
                .' AND dtl_UploadedFileID=ulf_ID '
                .' AND (ulf_PreferredSource="iiif" OR ulf_OrigFileName="'.ULF_IIIF.'")';

            $row = mysql__select_row($mysqli, $query);

            if($row!=null && defined('DT_URL')){

                //find canvas id for annotatation record
                $query = 'SELECT dtl_Value FROM recDetails WHERE '
                .' dtl_RecID='.$rec_ID.' AND dtl_DetailTypeID='.DT_URL;
                $canvasUri = mysql__select_value($mysqli, $query);

                $rec_ID = intval($row[0]);//record id with manifest
                $_REQUEST['iiif'] = $row[1];

            }
        }elseif(defined('RT_IIIF_MANIFEST') && $res['rec_RecTypeID']==RT_IIIF_MANIFEST){
            // Open a registered IIIF Manifest record via the Heurist overlay Manifest API,
            // so source annotations are replaced by annotation pages from this database.
            $manifestRecID = $rec_ID;

        }elseif(defined('DT_URL')){

            //find linked annotations
            //find CanvasURI linked annotation - to activate this page on mirador load
            $query = 'SELECT dtl_Value, count(*) as cnt FROM recDetails, recLinks, Records '
                .' WHERE rl_TargetID='.$rec_ID
                .' AND rec_ID=rl_SourceID AND rec_RecTypeID='.RT_IIIF_ANNOTATION
                .' AND dtl_RecID=rec_ID '
                .' AND dtl_DetailTypeID='.DT_URL
                .' GROUP BY dtl_Value ORDER BY cnt DESC';

            $row = mysql__select_row($mysqli, $query);//get first row

            if(is_array($row) && @$row[0]!=null){
                $canvasUri = $row[0];
            }

            if(@$_REQUEST['iiif']==null && @$_REQUEST['file']==null){
                //get manifest url from database
                $query = 'SELECT ulf_ObfuscatedFileID '
                    .' FROM recDetails, recUploadedFiles '
                    .' WHERE dtl_RecID='.$rec_ID //'AND dtl_DetailTypeID IN ('.implode(',',$file_field_types).')'
                    .' AND dtl_UploadedFileID=ulf_ID '
                .' AND (ulf_PreferredSource="iiif" OR ulf_OrigFileName="'.ULF_IIIF.'")';

                $_REQUEST['iiif'] = mysql__select_value($mysqli, $query);
            }

        }
    }
}else{
    $rec_ID = 0;
}

if($baseUrl==null){

    $https = (isset($_SERVER['HTTPS']) &&
                ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');

    $baseUrl = ($https ? 'https://' : 'http://').
        (!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
        (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
        ($https && $_SERVER['SERVER_PORT'] === 443 ||
        $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT'])));

}
    if(!(substr($baseUrl,-1)=='/' || substr($_SERVER['REQUEST_URI'],0,1)=='/')){
        $baseUrl = $baseUrl.'/';
    }
    $url = $baseUrl . $_SERVER['REQUEST_URI'];
    
    if(substr($baseUrl,-1)!='/'){
        $baseUrl = $baseUrl.'/';
    }

if($manifestRecID>0) {

    $url = $baseUrl.'heurist/api/'.rawurlencode($dbname).'/iiif/manifest/'.$manifestRecID.'?omit_annotation_pages=1';

}elseif(@$_REQUEST['url']) { //direct url to manifest

    $url = $_REQUEST['url'];

}elseif(@$_REQUEST['manifest'] || @$_REQUEST['iiif']){  //obfuscation id
    //load manifest directly
    $url = str_replace('hclient/widgets/viewers/miradorViewer.php','', $url);
    $url = str_replace($_SERVER['QUERY_STRING'],
        'db='.$_REQUEST['db']
            .'&file='.(@$_REQUEST['manifest']?$_REQUEST['manifest']:@$_REQUEST['iiif']),$url);
}else{
    if(is_array($preparedParams)){
        // record_output creates manifest dynamically. Rebuild the URL from restored
        // prepared parameters so the IIIF manifest endpoint receives the original q/rules.
        $preparedParams['format'] = 'iiif';
        $preparedParams['db'] = $dbname;

        $url = str_replace($_SERVER['QUERY_STRING'], http_build_query($preparedParams), $url);
        $url = str_replace('hclient/widgets/viewers/miradorViewer.php','hserv/controller/record_output.php', $url);

    }elseif(!@$_REQUEST['q'] && @$_REQUEST['iiif_image']){ //file obfuscatin id
        //find record linked to this media
        $url = str_replace($_SERVER['QUERY_STRING'],
            'db='.$_REQUEST['db']
            .'&iiif_image='.$_REQUEST['iiif_image'], $url);

    }elseif(!@$_REQUEST['q']){ //query not defined
        if($rec_ID>0){
             $url = $url.'&format=iiif&q=ids:'.$rec_ID;
        }else{
            exit('Need to define either query or file ID');
        }
    }else{
        if(strpos($url, 'format=iiif')===false){
                $url = $url.'&format=iiif';
        }
    }
    //record_output creates manifest dynamically
    if(!is_array($preparedParams)){
        $url = str_replace('hclient/widgets/viewers/miradorViewer.php','hserv/controller/record_output.php', $url);
    }
}



if(@$_REQUEST['canvasUri']){
    $canvasUri = $_REQUEST['canvasUri'];
}

$manifest_url = str_replace('&amp;','&',htmlspecialchars($url));

$use_custom_mirador = file_exists(dirname(__FILE__).'/../../../external/mirador3/dist/main.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta name="robots" content="noindex,nofollow">
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
<style>
html,
body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  min-width: 100%;
  min-height: 100%;
  overflow: hidden;
}

body {
  position: relative;
}

#demo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  min-width: 100%;
  min-height: 100%;
}
</style>
</head>
<body>
<div id="demo"></div>
<script>
<?php
if (!preg_match('[\W]', $dbname)){
?>
    //temporarely .($manifestRecID>0?'/'.$manifestRecID:'')
    window.endpointURL = "<?php echo $baseUrl.'heurist/api/'.htmlspecialchars($dbname).'/annotations';?>";
    window.manifestUrl = "<?php echo $manifest_url;?>";
<?php
}
?>
    window.hideThumbs = <?php echo @$_REQUEST['iiif_image']?'true':'false';?>;
    window.sourceRecordId = <?php echo $rec_ID;?>;//manifest record
    window.runManually = true;
</script>
<?php
if($use_custom_mirador){
      if($canvasUri==null){
          $canvasUri = '';
      }
//see https://github.com/ProjectMirador/mirador/blob/master/src/config/settings.js
?>
<script src="dist/main.js"></script>
<script type="text/javascript">
    function renderMiradorWhenReady(config) {
        const el = document.getElementById('demo');

        function hasSize() {
            return el && el.clientWidth > 50 && el.clientHeight > 50;
        }

        function render() {
            if (typeof window.renderMirador === 'function') {
                window.renderMirador(config);
            }
        }

        if (hasSize()) {
            render();
        } else {
            requestAnimationFrame(function retry() {
                if (hasSize()) {
                    render();
                } else {
                    requestAnimationFrame(retry);
                }
            });
        }
    }

    var config = {
        id: 'demo',
        windows: [{
            //canvasIndex: 2,
            canvasId: '<?php echo htmlspecialchars($canvasUri);?>',  //'https://fragmentarium.ms/metadata/iiif/F-hsd6/canvas/F-hsd6/fol_2r.jp2.json',
            imageToolsEnabled: true,
            imageToolsOpen: false,
            manifestId: window.manifestUrl,
            sideBarOpen: true,
            highlightAllAnnotations: true,
            sideBarPanel: 'annotations', // Configure which sidebar is selected by default
            //thumbnailNavigationPosition: "far-bottom"
        }],
        theme: {
            palette: {
                primary: {
                    main: '#1967d2',
                },
            },
        },
    };

    if(window.hideThumbs !==true){
        config['windows'][0]['thumbnailNavigationPosition'] = 'far-bottom';
    }

    renderMiradorWhenReady(config);

</script>
<?php
}else{
?>
<script type="text/javascript">
var mirador = Mirador.viewer({
  "id": "demo",
  "windows": [
    {
      "id": "uniqueid",
      //"canvasIndex": "1",
      "loadedManifest": "<?php echo $manifest_url;?>"
      <?php echo @$_REQUEST['iiif_image']?'':',"thumbnailNavigationPosition": "far-bottom"';?>
    }
  ]
});

</script>
<?php
}
?>
</body>
</html>