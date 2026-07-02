<?php
/**
* openSeadragonViewer.php - Inits and handles the OpenSeadragon viewer. 
* 
* It uses the latest OpenSeadragon distribution from unpkg.com
*
* @project     Heurist academic knowledge management system
* @package     hclient\widgets\viewers
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

use hserv\utilities\USanitize;

require_once __DIR__ . '/../../../autoload.php';

$imageURL = '';
$requestParameters = USanitize::sanitizeInputArray();

$database = array_key_exists('db', $requestParameters) ? $requestParameters['db'] : null;
$ulfID = array_key_exists('recID', $requestParameters) ? $requestParameters['recID'] : null;
$imageURL = array_key_exists('image', $requestParameters) ? trim((string)$requestParameters['image']) : '';
if(!$database || (!$ulfID && $imageURL === '')){
    exit;
}

$language = array_key_exists('lang', $requestParameters) ? $requestParameters['lang'] : 'FRE';
$language = getLangCode3($language);

$system = new hserv\System();
if(!$system->init($database, true, false)){
    exit;
}

$mysqli = $system->getMysqli();

$files = [];
if($imageURL !== ''){
    if(!preg_match('~^https?://~i', $imageURL)){
        exit;
    }

    $files[] = [
        'type' => 'image',
        'url' => $imageURL,
        'buildPyramid' => false,
        'name' => basename(parse_url($imageURL, PHP_URL_PATH) ?: $imageURL),
        'caption' => '',
        'desc' => '',
        'copyright' => '',
        'owner' => '',
        'isManifest' => false
    ];
}else{
    $ulfQuery = '';
    $ulfIDs = prepareIds($ulfID);
    if(isPositiveInt($ulfID)){
        $ulfQuery = "ulf_ID = {$ulfID}";
    }elseif(preg_match('/^[a-z0-9]+$/', $ulfID)){
        $ulfQuery = "ulf_ObfuscatedFileID = '{$mysqli->real_escape_string($ulfID)}'";
    }elseif(!empty($ulfIDs)){
        $ulfQuery = "ulf_ID IN (". implode(',', $ulfIDs) .")";
    }

    if($ulfQuery === ''){
        exit;
    }

    $ulfRecords = mysql__select_assoc($mysqli, "SELECT * FROM recUploadedFiles WHERE {$ulfQuery}", 0);

    foreach($ulfRecords as $ulfRec){

    $fileID = $ulfRec['ulf_ID'];

    $caption = $ulfRec['ulf_Caption'] ?? '';
    $description = $ulfRec['ulf_Description'] ?? '';
    if($language && $language !== 'def'){

        $translatedCaption = mysql__select_value($mysqli, "SELECT trn_Translation FROM defTranslations WHERE trn_Source = 'ulf_Caption' AND trn_Code = {$fileID} AND trn_LanguageCode = '{$language}'");
        $translatedDesc = mysql__select_value($mysqli, "SELECT trn_Translation FROM defTranslations WHERE trn_Source = 'ulf_Description' AND trn_Code = {$fileID} AND trn_LanguageCode = '{$language}'");

        $caption = !empty($translatedCaption) ? $translatedCaption : $caption;
        $description = !empty($translatedDesc) ? $translatedDesc : $description;
    }

    $filename = !empty($ulfRec['ulf_ExternalFileReference']) ? $ulfRec['ulf_ExternalFileReference'] : $ulfRec['ulf_OrigFileName'];
        $files[] = [
            'type' => 'image',
            'url' => HEURIST_BASE_URL . "?db={$database}&fullres=1&file={$ulfRec['ulf_ObfuscatedFileID']}",
            'buildPyramid' => false,
            'name' => $filename,
            'caption' => $caption,
            'desc' => $description,
            'copyright' => $ulfRec['ulf_Copyright'],
            'owner' => $ulfRec['ulf_Copyowner'],
            'isManifest' => $ulfRec['ulf_OrigFileName'] == '_iiif'
        ];
    }
}
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

        <title>Heurist OpenSeaDragon Viewer</title>

        <script 
            src="https://cdnjs.cloudflare.com/ajax/libs/openseadragon/5.0.1/openseadragon.min.js" 
            crossorigin="anonymous" 
            integrity="sha384-Vh4b5HGyvDGMp6lVeYZe1zCqqyhS8oLTUC4SKIWmOuYH7nEuWiGIpSACopwLu9UD">
        </script>

        <link rel=icon href="../../../favicon.ico" type="image/x-icon">

        <style>
            *{
                font-family: Helvetica,Arial,sans-serif;
            }

            #img-title{
                padding-left: 4em;
                font-size: 1.2em;
                font-weight: bold;
            }

            #img-add-info{
                padding: 0px 4em;
            }

            #img-copyright{
                display: block;
                font-style: italic;
            }

            #img-desc{
                margin-top: 0.5em;
                text-align: justify;
                overflow-x: auto;
                max-height: 7.5em;
                padding-right: 0.4em;
            }
        </style>

    </head>

    <body>

        <div id="img-title" style="padding-left: 4em;"></div>
        <div id="openseadragon-img" style="margin: auto;"></div>
        <div id="img-add-info">
            <small id="img-copyright"></small>
            <div id="img-caption"></div>
            <div id="img-desc"></div>
        </div>

        <script>

            let files = <?php echo json_encode($files); ?>;

            function updateDetails(index){

                if(!Number.isInteger(index)){
                    return;
                }

                let imageTitle = document.getElementById('img-title');
                let imageRight = document.getElementById('img-copyright');
                let imageCaption = document.getElementById('img-caption');
                let imageDesc = document.getElementById('img-desc');

                let details = Array.isArray(files) ? files[index] : files;
                imageTitle.innerText = details.name;

                let copyright = details.owner && !details.copyright ? details.owner : '';
                copyright = !details.owner && details.copyright ? details.copyright : copyright;
                copyright = details.owner && details.copyright ? `${details.owner} - ${details.copyright}` : copyright;
                if(copyright.length > 0){
                    imageRight.innerHTML = copyright;
                    imageRight.style.display = 'block';
                }else{
                    imageRight.style.display = 'none';
                }

                if(details.caption && details.caption.length > 0){
                    imageCaption.innerHTML = details.caption;
                    imageCaption.style.display = 'block';
                }else{
                    imageCaption.style.display = 'none';
                }

                if(details.desc && details.desc.length > 0){
                    imageDesc.innerHTML = details.desc;
                    imageDesc.style.display = 'block';
                }else{
                    imageDesc.style.display = 'none';
                }
            }

            let width = window.innerWidth * 0.7;
            let height = window.innerHeight * 0.7;
            let openSeadragonEle = document.getElementById('openseadragon-img');

            openSeadragonEle.style.height = `${height}px`;
            openSeadragonEle.style.width = `${width}px`;

            window.addEventListener('resize', () => {

                let width = window.innerWidth * 0.7;
                let height = window.innerHeight * 0.7;

                openSeadragonEle.style.height = `${height}px`;
                openSeadragonEle.style.width = `${width}px`;
            });

            try{

                files = files.length > 1 ? files : files[0];

                let openSeadragonViewer = OpenSeadragon({
                    id: 'openseadragon-img',
                    prefixUrl: 'https://cdnjs.cloudflare.com/ajax/libs/openseadragon/5.0.1/images/',
                    sequenceMode: Array.isArray(files),
                    tileSources: files
                });

                openSeadragonViewer.addHandler('open', () => openSeadragonViewer.raiseEvent('home'));

                openSeadragonViewer.addHandler('open-failed', (event) => {
                    // dig down into the third child node, note: querySelector kept returning mixed results
                    let errorMessage = event.source.isManifest
                        ? 'OpenSeadragon cannot render IIIF Manifests, please use the Mirador viewer instead'
                        : `The provided file "${event.source.name}" cannot be rendered by OpenSeadragon`;
                    event.eventSource.messageDiv.lastChild.lastChild.lastChild.innerText = errorMessage;
                });

                openSeadragonViewer.addHandler('page', (event) => updateDetails(event.page));

                updateDetails(0);
            }catch{
                openSeadragonEle.innerHTML = 'Heurist has failed to prepare files for OpenSeadragon, please report this bug to <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a>';
            }
        </script>
    </body>

</html>