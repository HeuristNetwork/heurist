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
if(!$database || !$ulfID && $imageURL === ''){
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
            'name' => $filename,
            'caption' => $caption,
            'desc' => $description,
            'copyright' => $ulfRec['ulf_Copyright'],
            'owner' => $ulfRec['ulf_Copyowner'],
            'isManifest' => $ulfRec['ulf_OrigFileName'] == '_iiif',
            'isPDF' => strtolower($ulfRec['ulf_MimeExt']) === 'pdf',
            'usePDFImage' => true
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

        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.min.js"
            integrity="sha384-Rsf8MiIHKf4GYNodK6fZAeoKdbiBXCrgOdMVxIzYmk+gqnrHgC+AyneIM0UI2UFG"
            crossorigin="anonymous">
        </script>

        <link rel=icon href="../../../favicon.ico" type="image/x-icon" />

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
            integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"
        />

        <style>
            *{
                font-family: Helvetica,Arial,sans-serif;
            }

            #openseadragon-img{
                margin: 1em auto;
                padding: 5px;
                border: 1px solid black;
                border-radius: 5px;
            }
            #openseadragon-img.greyBackground{
                background-color: #282828;
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

            .pdf-controller{
                cursor: pointer;
            }
            #pdf-page-count{
                padding: 0px 0.5em;

                cursor: default;
            }

            .osd-toggle-bg{

                width: 2em;
                height: 2em;

                border-radius: 50%;

                display: inline-flex;
                justify-content: center;
                align-items: center;

                background-color: white;
                color: black;
                font-size: 20px;
                cursor: pointer;
                transition: background-color 0.2s ease, transform 0.1s ease;

                position: absolute;
                top: 2.5em;
                left: 8em;
            }

            .osd-toggle-bg:active{
                transform: scale(0.95);
            }
        </style>

        <script>

            let openSeadragonViewer;
            let files = <?php echo json_encode($files); ?>;

            let pdfFiles = {};
            const TILE_SIZE = 256;
            const PDF_CANVAS_SCALE = 4;
            const PDF_IMAGE_SCALE = 2;
            let fileIndex = 0;

            function updateDetails(){

                if(!Number.isInteger(fileIndex)){
                    return;
                }

                let imageTitle = document.getElementById('img-title');
                let imageRight = document.getElementById('img-copyright');
                let imageCaption = document.getElementById('img-caption');
                let imageDesc = document.getElementById('img-desc');

                let details = Array.isArray(files) ? files[fileIndex] : files;
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

                let pdfControls = document.getElementById('pdf-controls');
                let pdfDetails = pdfFiles[fileIndex];
                if(pdfControls && pdfDetails){

                    if(details.isPDF){

                        pdfControls.style.display = 'block';

                        let pdfPageElement = pdfControls.querySelector('#pdf-page-count');
                        if(pdfPageElement && pdfDetails.currentPage && pdfDetails.pageCount){
                            pdfPageElement.innerHTML = `Page ${pdfDetails.currentPage} of ${pdfDetails.pageCount}`;
                        }

                    }else{
                        pdfControls.style.display = 'none';
                    }
                }
            }

            async function preparePDF(idx, file){

                try{

                    const loadingTask = await pdfjsLib.getDocument(file.url);
                    const pdf = await loadingTask.promise;

                    const pageCount = pdf.numPages;

                    pdfFiles[idx] = {
                        pdfInstance: pdf,
                        pageCount: pageCount,
                        currentPage: 1,
                        pages: {}
                    };

                    files[idx]['url'] = '';
                    files[idx]['type'] = 'pdf';
                    files[idx]['pdfPage'] = 1;

                    // Prevent standard URL fetching behavior
                    files[idx]['getTileUrl'] = function(level, x, y){
                        return this.usePDFImage ? '' : `pdf-tile://${level}/${x}-${y}`;
                    };

                    if(files[idx].usePDFImage){
                        files[idx]['getTileHashKey'] = function(level, x, y){
                            return `${level}-${x}-${y}-${this.pdfPage}`;
                        };
                    }else{

                        const page = await pdf.getPage(1);
                        const viewport = page.getViewport({scale: PDF_CANVAS_SCALE});

                        files[idx]['tileSize'] = 256;
                        files[idx]['tileOverlap'] = 0;
                        files[idx]['minLevel'] = 0;

                        files[idx]['width'] = viewport.width * PDF_CANVAS_SCALE;
                        files[idx]['height'] = viewport.height * PDF_CANVAS_SCALE;

                        files[idx]['downloadTileStart'] = async function(imageJob){ //tile
    
                            const tile = imageJob.tile;
    
                            const canvas = await renderPDFPageAsCanvas(this.pdfPage, tile.level, tile.maxLevel ?? this.maxLevel);
    
                            !canvas ? imageJob.fail() : imageJob.finish(canvas);
                        }
    
                        files[idx]['downloadTileAbort'] = function(values){
                            console.error('Tile download was aborted.', values, this);
                        };
                    }

                }catch(e){
                    console.error('Failed to prepare PDF for OpenSeadragon.', e);
                    files[idx]['errorMsg'] = 'Failed to prepare PDF file for OpenSeadragon.';
                }

            }

            async function renderPDFPageAsCanvas(pageNumber, level = 0, maxLevel = 10){

                let pdfDetails = pdfFiles[fileIndex];
                if(!pdfDetails){
                    return;
                }

                try{
                    const page = await pdf.getPage(pageNumber);
                }catch(e){
                    console.error(`Unable to retrieve page #${pageNumber}`, e);
                    return;
                }

                const canvas = document.createElement('canvas');
                canvas.width = TILE_SIZE;
                canvas.height = TILE_SIZE;
                const context = canvas.getContext('2d');

                const scaleAtLevel = Math.pow(2, level) / Math.pow(2, maxLevel);

                const tileViewport = page.getViewport({
                    scale: PDF_CANVAS_SCALE * scaleAtLevel,
                    dontFlip: false,
                    offsetX: -tile.x * TILE_SIZE,
                    offsetY: -tile.y * TILE_SIZE
                });

                const renderContext = {
                    canvasContext: context,
                    viewport: tileViewport
                };

                const renderTask = page.render(renderContext);

                try{
                    await renderTask.promise;
                }catch(e){
                    console.error('Rendering task has failed.', e);
                    canvas = null;
                }

                return canvas;
            }

            async function renderPDFPageAsImage(pageNumber){

                let pdfDetails = pdfFiles[fileIndex];
                if(!pdfDetails){
                    return;
                }

                if(Object.hasOwn(pdfDetails.pages, pageNumber)){
                    pdfDetails.currentPage = pageNumber;
                    return pdfDetails.pages[pageNumber];
                }

                let pdf = pdfDetails.pdfInstance;

                const page = await pdf.getPage(pageNumber);
                const viewport = page.getViewport({ scale: PDF_IMAGE_SCALE });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;

                let imageURL = canvas.toDataURL('image/png');
                pdfDetails.pages[pageNumber] = imageURL;

                pdfDetails.currentPage = pageNumber;

                return imageURL;
            }

            async function _initiateOSD(){

                if(!pdfjsLib?.GlobalWorkerOptions?.workerSrc || pdfjsLib?.GlobalWorkerOptions?.workerSrc === ''){
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.worker.min.js';
                }

                for(const idx in files){
                    if(files[idx].isPDF){
                        await preparePDF(idx, files[idx]);
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

                    let OSDFiles = files.length > 1 ? files : files[0];

                    openSeadragonViewer = OpenSeadragon({
                        id: 'openseadragon-img',
                        prefixUrl: 'https://cdnjs.cloudflare.com/ajax/libs/openseadragon/5.0.1/images/',
                        sequenceMode: Array.isArray(OSDFiles),
                        tileSources: OSDFiles
                    });

                    openSeadragonViewer.addHandler('open', async (event) => {

                        if(!event.source.type === 'pdf' || !event.source.usePDFImage){
                            openSeadragonViewer.raiseEvent('home');
                            return;
                        }

                        const pdfDataURL = await renderPDFPageAsImage(event.source.pdfPage);
                        if(!pdfDataURL){
                            return;
                        }

                        openSeadragonViewer.open({
                            type: 'image',
                            url: pdfDataURL,
                            pdfPage: event.source.pdfPage
                        });

                        openSeadragonViewer.raiseEvent('home');
                    });

                    openSeadragonViewer.addHandler('open-failed', (event) => {

                        console.error('OpenSeadragon failed to open image.', event);
                        let defaultElement = document.querySelector(".openseadragon-message");
                        let messageElement = event.eventSource.messageDiv.lastChild.lastChild.lastChild;

                        // dig down into the third child node, note: querySelector kept returning mixed results
                        let errorMessage = event.source.isManifest
                            ? 'OpenSeadragon cannot render IIIF Manifests, please use the Mirador viewer instead'
                            : `The provided file "${event.source.name}" cannot be rendered by OpenSeadragon`;

                        errorMessage = event.source.isPDF
                            ? `Heurist failed to prepare the PDF page #${event.source.pdfPage} for display`
                            : errorMessage;

                        errorMessage = event.source.errorMsg ?? errorMessage;

                        messageElement.innerText = errorMessage;
                    });

                    openSeadragonViewer.addHandler('tile-load-failed', (event) => {
                        console.error('Failed to load tile source.', event);
                    });

                    openSeadragonViewer.addHandler('page', (event) => {
                        fileIndex = event.page;
                        updateDetails(event.page)
                    });

                    openSeadragonViewer.goToPage(0);
                    updateDetails(0);
                }catch(e){
                    console.error('OpenSeadragon has been initialised', e);
                    openSeadragonEle.innerHTML = 'Heurist has failed to prepare files for OpenSeadragon, please report this bug to <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a>';
                }
            }

            async function handlePageSeek(event){

                let target = event.target;
                let tagName = target.tagName;
                if(tagName === 'SPAN'){
                    target = target.parentNode;
                }

                let action = target.id;

                let page = 0;
                let pageDetails = pdfFiles[fileIndex];
                let pageCount = Number.parseInt(pageDetails.pageCount);
                let currentPage = Number.parseInt(pageDetails.currentPage);
                if(action === 'pdf-seek-backwards' || action === 'pdf-seek-forwards'){
                    page = action === 'pdf-seek-backwards' ? 1 : pageCount;
                }else{
                    page = action === 'pdf-backwards' ? currentPage - 1 : currentPage + 1;
                }

                page = page < 1 ? 1 : page;
                page = page > pageCount ? pageCount : page;

                if(page != currentPage){

                    const pdfDataURL = await renderPDFPageAsImage(page);

                    if(pdfDataURL){

                        openSeadragonViewer.open({
                            type: 'image',
                            url: pdfDataURL,
                            pdfIndex: fileIndex,
                            pdfPage: page
                        });

                        updateDetails(fileIndex);
                    }
                }
            }

            function _initiateHandlers(){

                const lightOn = '<i class="fa-regular fa-lightbulb"></i>';
                const lightOff = '<i class="fa-solid fa-lightbulb"></i>';

                let toggleBGBtn = document.querySelector('.osd-toggle-bg');
                toggleBGBtn.addEventListener('click', () => {
                    document.getElementById('openseadragon-img').classList.toggle('greyBackground');
                    let isDarkBG = document.getElementById('openseadragon-img').classList.contains('greyBackground');
                    toggleBGBtn.innerHTML = isDarkBG ? lightOff : lightOn;
                    toggleBGBtn.style.backgroundColor = isDarkBG ? 'lightgrey' : 'white';
                });

                let pdfControls = document.querySelectorAll('.pdf-controller');
                pdfControls.forEach((pdfControl, index) => {
                    pdfControl.addEventListener('click', handlePageSeek);
                });
            }

            document.addEventListener("DOMContentLoaded", () => {
                _initiateOSD();
                _initiateHandlers();
            });
        </script>
    </head>

    <body>

        <div id="img-title" style="padding-left: 4em;"></div>
        <button class="osd-toggle-bg" title="Toggle grey background"><i class="fa-regular fa-lightbulb"></i></button>
        <div id="openseadragon-img"></div>
        <div id="pdf-controls" style="display: none; text-align: center;">
            <span class="pdf-controller" id="pdf-seek-backwards" title="Go to first page"><span class="fa-solid fa-angles-left"></span></span>
            <span class="pdf-controller" id="pdf-backwards" title="Go to the previous page"><span class="fa-solid fa-chevron-left"></span></span>
            <span id="pdf-page-count"></span>
            <span class="pdf-controller" id="pdf-forwards" title="Go to the next page"><span class="fa-solid fa-chevron-right"></span></span>
            <span class="pdf-controller" id="pdf-seek-forwards" title="Go to last page"><span class="fa-solid fa-angles-right"></span></span>
        </div>
        <div id="img-add-info">
            <small id="img-copyright"></small>
            <div id="img-caption"></div>
            <div id="img-desc"></div>
        </div>

    </body>

</html>