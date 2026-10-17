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
    echo !$database ? 'Database is missing' : 'Image is missing';
    exit;
}

$language = array_key_exists('lang', $requestParameters) ? $requestParameters['lang'] : 'FRE';
$language = getLangCode3($language);

$system = new hserv\System();
if(!$system->init($database, true, false)){
    echo 'Unable to initialise Heurist System.';
    exit;
}

$mysqli = $system->getMysqli();

$files = [];
if($imageURL !== ''){

    if(!preg_match('~^https?://~i', $imageURL)){
        echo 'Invalid image URL provided';
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
        echo 'Invalid image identifier provided';
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
            'type' => strpos(strtolower($ulfRec['ulf_MimeExt']), 'pdf') !== false ? 'pdf': 'image',
            'url' => HEURIST_BASE_URL . "?db={$database}&fullres=1&file={$ulfRec['ulf_ObfuscatedFileID']}",
            'name' => $filename,
            'caption' => $caption,
            'desc' => $description,
            'copyright' => $ulfRec['ulf_Copyright'],
            'owner' => $ulfRec['ulf_Copyowner'],
            'isManifest' => $ulfRec['ulf_OrigFileName'] == '_iiif'
        ];

        break; // accept only the first file, for now
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
            src="https://cdnjs.cloudflare.com/ajax/libs/openseadragon/6.1.1/openseadragon.min.js" 
            crossorigin="anonymous" 
            integrity="sha384-KKly+4vqqBHitr2XfNKWa3+0asIVpzYs2HijkM/zz0X6rgnyLuQEVERX9HqkM5/P">
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
            :root{

                --dark: rgb(60 60 60);
                --darker: rgb(30 30 30);
                --bright: rgb(230 230 230);
                --brighter: rgb(245 245 245);
                --black: rgb(25 25 25);
                --lightgrey: rgb(200 200 200);
                --white: rgb(240 240 240);

                --osd-background: var(--brighter);
                --page-background: var(--bright);
                --page-colour: var(--black);
            }

            *{
                font-family: Helvetica,Arial,sans-serif;
            }

            body{
                background-color: var(--page-background);
                color: var(--page-colour);
            }

            #openseadragon-img{

                height: 70vh;
                width: 70vw;

                margin: 1em auto;
                padding: 5px;

                border: 1px solid black;
                border-radius: 5px;

                background-color: var(--osd-background);
            }

            #img-title{
                padding-top: 0.5em;
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

                background-color: transparent;
                color: var(--page-colour);
                font-size: 20px;
                cursor: pointer;
                transition: background-color 0.2s ease, transform 0.1s ease;

                position: absolute;
                top: 0.4em;
                left: 1em;
            }

            .osd-toggle-bg:active{
                transform: scale(0.95);
            }

            .osd-coveralls{

                background-color: rgba(0, 0, 0, 0.7);
                color: var(--white);
                opacity: 0;

                display: flex;
                justify-content: center;
                align-items: center;

                font-size: 1.2em;

                z-index: 100;

                transition: opacity 0.3s ease;

                user-select: none;
                -moz-user-select: none;
            }

            #osd-coverall{

                position: absolute;
                top: 0px;
                left: 0px;

                height: 100vh;
                width: 100vw;

                pointer-events: none;
            }

            #osd-error-coverall{

                position: absolute;

                padding: 5px;

                height: 70vh;
                width: 70vw;
            }
        </style>

        <script>

            let openSeadragonViewer;
            let files = <?php echo json_encode($files); ?>;

            let pdfFiles = {};
            const TILE_SIZE = 256;
            const PDF_RENDER_SCALE = 2;
            let fileIndex = 0;

            function showCoverall(message){
                const OSDCoverall = document.getElementById('osd-coverall');
                if(message && message !== ''){
                    OSDCoverall.querySelector('#osd-coverall-msg').textContent = message;
                }
                OSDCoverall.style.opacity = 1;
            }
            function hideCoverall(){
                const OSDCoverall = document.getElementById('osd-coverall');
                OSDCoverall.style.opacity = 0;
            }

            function showError(message, clearCanvas = false){

                const OSDErrorCoverall = document.getElementById('osd-error-coverall');

                message = message && message !== '' ? message : 'An error has occurred ...';
                OSDErrorCoverall.querySelector('#osd-error-coverall-msg').textContent = message;

                if(clearCanvas){
                    let canvas = openSeadragonViewer.canvas.querySelector('canvas');
                    let context = canvas.getContext('2d');
                    context.clearRect(0, 0, canvas.width, canvas.height);
                }

                const rectBounds = document.getElementById('openseadragon-img').getBoundingClientRect();
                OSDErrorCoverall.style.left = `${Math.ceil(rectBounds.x + 8)}px`;
                OSDErrorCoverall.style.top = `${Math.ceil(rectBounds.y)}px`;

                OSDErrorCoverall.style.display = '';
            }
            function hideError(){
                const OSDErrorCoverall = document.getElementById('osd-error-coverall');
                OSDErrorCoverall.style.display = 'none';
            }

            function loadFile(){

                if(!Number.isInteger(fileIndex)){
                    return;
                }

                const file = files[fileIndex];

                if(file.type === 'pdf'){
                    showPDFPage(fileIndex, 1);
                }else if(file.type === 'iiif'){
                    return;
                }else{
                    showCoverall('Loading Image ...');
                    openSeadragonViewer.open({
                        type: 'image',
                        url: file.url,
                        buildPyramid: false
                    })
                }
            }

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
                if(pdfControls){

                    if(pdfDetails){

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

            async function getPDFInstance(index){

                if(Object.hasOwn(pdfFiles, index)){
                    return pdfFiles[index].pdfInstance;
                }
                try{

                    const pdf = await pdfjsLib.getDocument(files[index].url).promise;

                    pdfFiles[index] = {
                        pdfInstance: pdf,
                        pageCount: pdf.numPages,
                        currentPage: 1,
                        pages: {}
                    };

                    return pdf;
                }catch(e){
                    console.error(e);
                    //`Unable to retrieve PDF document from ${files[index].url}. Error: ${e.message}`;
                }
            }

            async function getPDFPage(index, pageNumber){

                if(Object.hasOwn(pdfFiles[index].pages, pageNumber)){
                    return pdfFiles[index].pages[pageNumber];
                }else if(!Object.hasOwn(pdfFiles, index)){
                    return;
                }

                try{
                    const pdf = pdfFiles[index].pdfInstance;
                    const page = await pdf.getPage(pageNumber);
                    pdfFiles[index].pages[pageNumber] = page;

                    return page;
                }catch(e){
                    console.error(e);
                    //`Unable to retrieve page #${pageNumber} from PDF. Error: ${e.message}`;
                }
            }

            async function showPDFPage(index, pageNumber){

                showCoverall(`Loading PDF Page #${pageNumber} ...`);

                if(!pdfjsLib?.GlobalWorkerOptions?.workerSrc || pdfjsLib?.GlobalWorkerOptions?.workerSrc === ''){
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.worker.min.js';
                }

                // Get PDF
                const pdf = await getPDFInstance(index);
                if(!pdf){
                    hideCoverall();
                    return;
                }

                // Get PDF Page
                const page = await getPDFPage(index, pageNumber);
                if(!page){
                    hideCoverall();
                    return;
                }
                pdfFiles[index].currentPage = pageNumber;

                // Define DPI factor and max zoom in
                const viewport = page.getViewport({scale: PDF_RENDER_SCALE});
                const maxLevel = Math.ceil(Math.log2(Math.max(viewport.width, viewport.height)) / TILE_SIZE);

                // Define custom tile source
                const tileSource = new OpenSeadragon.TileSource({

                    width: viewport.width,
                    height: viewport.height,

                    tileSize: TILE_SIZE,
                    tileOverlap: 0,

                    minLevel: 0,
                    maxLevel: maxLevel,

                    getTileUrl: (level, x, y) => `pdf-file://${fileIndex}-${pageNumber}/${level}/${x}-${y}`,

                    downloadTileStart: async (tileJob) => {

                        const tile = tileJob.tile;

                        // Setup individual tile canvas
                        const tileCanvas = document.createElement('canvas');
                        tileCanvas.width = TILE_SIZE;
                        tileCanvas.height = TILE_SIZE;

                        const tileCanvasCtx = tileCanvas.getContext('2d');
                        if(!tileCanvasCtx){
                            tileJob.finish(null, 'Unable to retrieve canvas context for tiles.');
                            return;
                        }

                        // Get current scale
                        const maxLevel = tile.maxLevel ?? tileJob.source.maxLevel;
                        const scale = Math.pow(2, tile.level - maxLevel);

                        // Get bound coords
                        const offsetX = tile.x * TILE_SIZE / scale;
                        const offsetY = tile.y * TILE_SIZE / scale;

                        // Get viewport for tile
                        const tileViewport = page.getViewport({
                            scale: PDF_RENDER_SCALE * scale,
                            dontFlip: false
                        });

                        // Shift canvas to (0, 0)
                        tileCanvasCtx.translate(-offsetX * scale, -offsetY * scale);

                        try{

                            // Render page slice onto tile canvas
                            await page.render({
                                canvasContext: tileCanvasCtx,
                                viewport: tileViewport
                            }).promise;

                            // Fulfill OSD promise
                            tileJob.finish(tileCanvasCtx, null, 'context2d');
                        }catch(e){

                            console.error(e);
                            tileJob.finish(null, 'PDF page rendering to tile canvas failed.');
                        }
                    }
                });

                openSeadragonViewer.open(tileSource);
            }

            function _initiateOSD(){

                openSeadragonViewer = OpenSeadragon({
                    id: 'openseadragon-img',
                    prefixUrl: 'https://cdnjs.cloudflare.com/ajax/libs/openseadragon/5.0.1/images/',
                    showNavigator: false,
                    blendTime: 0
                });

                openSeadragonViewer.addHandler('open', (event) => {
                    setTimeout(hideCoverall, 250);
                    updateDetails();
                    openSeadragonViewer.raiseEvent('home'); // re-centre
                });

                openSeadragonViewer.addHandler('open-failed', (event) => {

                    console.error('OpenSeadragon failed to open image.', event);

                    hideCoverall();

                    let defaultElement = document.querySelector(".openseadragon-message");
                    let messageElement = event.eventSource.messageDiv.lastChild.lastChild.lastChild;
                    messageElement.innerHTML = '';

                    // dig down into the third child node, note: querySelector kept returning mixed results
                    let errorMessage = event.source.isManifest
                        ? 'OpenSeadragon cannot render IIIF Manifests, please use the Mirador viewer instead'
                        : `The provided file "${event.source.name}" cannot be rendered by OpenSeadragon`;

                    errorMessage = event.source.type === 'pdf'
                        ? `Heurist failed to prepare the PDF page for display`
                        : errorMessage;

                    errorMessage = event.source.errorMsg ?? errorMessage;

                    showError(errorMessage, true);
                });

                openSeadragonViewer.addHandler('tile-load-failed', (event) => {

                    console.error('Failed to load tile source.', event);

                    hideCoverall();
                    showError('OpenSeadragon failed to load the requested tile source.');
                });

                openSeadragonViewer.addHandler('animation-finish', () => {
                    hideCoverall();
                    hideError();
                });
                openSeadragonViewer.addHandler('canvas-press', () => {
                    hideCoverall();
                });

                loadFile();
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
                    showPDFPage(fileIndex, page);
                }
            }

            function _initiateHandlers(){

                const lightOn = '<i class="fa-regular fa-lightbulb"></i>';
                const lightOff = '<i class="fa-solid fa-lightbulb"></i>';

                let toggleBGBtn = document.querySelector('.osd-toggle-bg');
                toggleBGBtn.addEventListener('click', () => {

                    const root = document.querySelector(':root');

                    const currentBG = getComputedStyle(root).getPropertyValue('--osd-background');
                    const brightBG = getComputedStyle(root).getPropertyValue('--brighter');

                    if(currentBG === brightBG){
                        root.style.setProperty('--osd-background', 'var(--dark)');
                        root.style.setProperty('--page-background', 'var(--darker)');
                        root.style.setProperty('--page-colour', 'var(--lightgrey)');
                    }else{
                        root.style.setProperty('--osd-background', 'var(--brighter)');
                        root.style.setProperty('--page-background', 'var(--bright)');
                        root.style.setProperty('--page-colour', 'var(--black)');
                    }
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

        <button class="osd-toggle-bg" title="Toggle grey background"><i class="fa-regular fa-lightbulb"></i></button>

        <div id="img-title"></div>

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

        <div id="osd-coverall" class="osd-coveralls">
            <div id="osd-coverall-msg">Loading Resource ...</div>
        </div>
        <div id="osd-error-coverall" class="osd-coveralls" style="display: none;">
            <div id="osd-error-coverall-msg">An error has happened ...</div>
        </div>

    </body>

</html>