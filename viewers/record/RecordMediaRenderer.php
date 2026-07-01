<?php
/**
 * RecordMediaRenderer.php - thumbnail and viewer-link renderer for renderRecordData.php.
 *
 * Keeps record media rendering out of the main record-data view script. It expects the
 * existing $thumb array shape produced by renderRecordData.php.
 * 
 * @project     Heurist academic knowledge management system
 * @package     Viewers\Record
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       7
 */
class RecordMediaRenderer
{
    private hserv\System $system;
    private int $recordID;
    private array $thumbs;
    private int $hideImages;
    private bool $isProduction;
    private bool $isMapPopup;
    private bool $withoutHeader;
    private bool $noclutter;
    private string $language;

    public function __construct(hserv\System $system, array $options)
    {
        $this->system = $system;
        $this->recordID = intval($options['recordID'] ?? 0);
        $this->thumbs = $options['thumbs'] ?? [];
        $this->hideImages = intval($options['hideImages'] ?? 0);
        $this->isProduction = !empty($options['isProduction']);
        $this->isMapPopup = !empty($options['isMapPopup']);
        $this->withoutHeader = !empty($options['withoutHeader']);
        $this->noclutter = !empty($options['noclutter']);
        $this->language = preg_replace('~[^a-z0-9_-]+~i', '', (string)($options['language'] ?? 'eng'));
    }

    public static function render(hserv\System $system, array $options): string
    {
        return (new self($system, $options))->renderMedia();
    }

    public function hasThumbs(): bool
    {
        return !empty($this->thumbs) && $this->hideImages !== 2;
    }

    private function renderMedia(): string
    {
        if ($this->hideImages === 2 || empty($this->thumbs)) {
            return '';
        }

        $thumbs = $this->orderedThumbs();
        $hasMain = $this->hasLinkedState($thumbs, false);
        $hasLinked = $this->hasLinkedState($thumbs, true);
        $showControls = !$this->isProduction && !$this->isMapPopup;
        $showLinkedToggle = $showControls && $hasMain && $hasLinked;
        $severalMedia = count($thumbs) > 1;

        $classes = ['thumbnail', 'record-media'];
        if ($this->isProduction) {
            $classes[] = 'production';
        }

        $html = [];
        $html[] = '<div class="'.implode(' ', $classes).'" data-record-media="1" data-recid="'.$this->h($this->recordID).'">';
        $html[] = $this->renderMediaJson($thumbs);

        $html[] = '<h4 class="record-media-header" style="margin: 5px 0px 2px; font-size: 1.1em; text-transform: uppercase;">MEDIA</h4>';

        
        if ($showControls && $severalMedia) {
            $html[] = '<div class="record-media-controls">';
            $html[] = '<a href="#" class="record-media-toggle-images" data-state="shown">'
                .'<span class="ui-icon ui-icon-menu" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;hide all media</a>';
            if ($showLinkedToggle) {
                $checked = $this->hideImages === 0 ? ' checked="checked"' : '';
                $html[] = '<label class="media-control"><input type="checkbox" class="show-linked-media"'.$checked.'> show all linked media</label>';
            }
            $html[] = '</div>';
        }

        $linkedHeaderPrinted = false;
        foreach ($thumbs as $idx => $thumb) {
            if (!empty($thumb['linked']) && !$linkedHeaderPrinted) {
                $linkedHeaderPrinted = true;
                $hidden = $this->hideImages === 1 && $hasMain ? ' style="display:none;"' : '';
                $html[] = '<h5 class="record-linked-media-header linked-media"'.$hidden.'>LINKED MEDIA</h5>';
            }

            $html[] = $this->renderThumb($thumb, $idx, $hasMain);

            if (!$this->noclutter && $this->isMapPopup) {
                $html[] = '<br>';
                break;
            }
        }

        $html[] = '</div><!--CLOSE ALL thumbnails-->';
        return implode("\n", $html);
    }

    private function orderedThumbs(): array
    {
        $main = [];
        $linked = [];
        foreach ($this->thumbs as $thumb) {
            if (!empty($thumb['linked'])) {
                $linked[] = $thumb;
            } else {
                $main[] = $thumb;
            }
        }
        return array_merge($main, $linked);
    }

    private function hasLinkedState(array $thumbs, bool $linked): bool
    {
        foreach ($thumbs as $thumb) {
            if (!empty($thumb['linked']) === $linked) {
                return true;
            }
        }
        return false;
    }

    private function renderMediaJson(array $thumbs): string
    {
        if ($this->isMapPopup || $this->withoutHeader) {
            return '';
        }

        $files = [];
        foreach ($thumbs as $thumb) {
            // IIIF Presentation Manifests are opened through Mirador/IIIF links and
            // should not be passed to the generic media viewer.  IIIF Image API
            // resources, however, are image-like: pass a raster default.jpg URL
            // rather than the info.json service document.
            if ($this->is3d($thumb) || $this->isIiifManifest($thumb) || $this->isAudioVideo($thumb)) {
                continue;
            }
            $files[] = [
                'rec_ID' => $this->recordID,
                'id' => (string)($thumb['nonce'] ?? ''),
                'mimeType' => $this->isIiifImage($thumb) ? 'image/jpeg' : (string)($thumb['mimeType'] ?? ''),
                'filename' => (string)($thumb['orig_name'] ?? ''),
                'external' => $this->mediaViewerUrl($thumb),
            ];
        }

        $json = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = str_replace('</script', '<\/script', $json);

        return '<script type="application/json" class="record-media-files-json">'
            .$json
            .'</script>';
    }

    private function renderThumb(array $thumb, int $idx, bool $hasMain): string
    {
        $isLinked = !empty($thumb['linked']);
        $isAudioVideo = $this->isAudioVideo($thumb);
        $isImage = $this->isImage($thumb);
        $isIiifImage = $this->isIiifImage($thumb);
        $isImageOrPdf = $isImage || $isIiifImage || $this->isPdf($thumb);
        $hideLinked = $isLinked && $this->hideImages === 1 && $hasMain;
        $style = [];

        if ($hideLinked) {
            $style[] = 'display:none;';
        }
        if ($this->isIiifManifest($thumb) || $this->is3d($thumb)) {
            $style[] = 'cursor:pointer;';
        } elseif (!$isImageOrPdf && !$isAudioVideo) {
            $style[] = 'cursor:default;';
        }
        if ($isAudioVideo && !empty($thumb['player']) && !$this->isMapPopup) {
            $style[] = 'text-align:left;';
            if ($this->isProduction) {
                $style[] = 'margin-left:100px;';
            }
        } else {
            $style[] = 'min-height:140px;';
        }

        $classes = ['media-content', 'media_container'];
        if ($isAudioVideo && !empty($thumb['player']) && !$this->isMapPopup) {
            $classes[] = 'fullSize';
        } elseif ($this->isIiifManifest($thumb) || $this->is3d($thumb)) {
            $classes[] = 'viewer_thumb';
        } else {
            $classes[] = 'thumb_image';
        }
        if ($isLinked) {
            $classes[] = 'linked-media';
        }

        $html = [];
        $html[] = '<div class="'.implode(' ', $classes).'" style="'.implode('', $style).'">';

        if (!$this->isMapPopup) {
            $html[] = $this->renderViewLinks($thumb);
        }

        $html[] = $this->renderMediaBody($thumb, $isAudioVideo, $isImage);
        $html[] = '</div><!--CLOSE THUMB SECTION-->';

        return implode("\n", $html);
    }

    private function renderViewLinks(array $thumb): string
    {
        $html = ['<div class="download_link">'];

        if ($this->isIiifManifest($thumb)) {
            $miradorUrl = $this->miradorUrl($thumb, false);
            $html[] = '<a href="'.$this->h($miradorUrl).'" target="_blank" rel="noopener">open in new tab</a>';
            $html[] = '<a href="'.$this->h($miradorUrl).'" class="record-media-mirador">'.$this->miradorIcon().'&nbsp;Mirador</a>';
            $manifestUrl = $this->manifestUrl($thumb);
            if($manifestUrl!==''){
                $html[] = '<a href="'.$this->h($manifestUrl).'" target="_blank" rel="noopener">'
                    .'<span class="external-link" style="display:inline-block;" title="Manifest content"></span>manifest</a>';
            }
            $html[] = '</div><!-- CLOSE download_link -->';
            return implode('', $html);
        }

        if ($this->is3d($thumb)) {
            $viewerUrl = $this->viewer3dUrl($thumb);

            $html[] = '<a href="'.$this->h($viewerUrl).'" target="_blank" rel="noopener">open in new tab</a>';
            $html[] = '<a href="'.$this->h($viewerUrl).'" class="record-media-3d-viewer">3D viewer</a>';
            $html[] = '</div><!-- CLOSE download_link -->';

            return implode('', $html);
        }

        if (!$this->isAudioVideo($thumb) && !$this->isIiifImage($thumb)) {
            $viewerUrl = $this->mediaViewerUrl($thumb);
            $dataUrl = $viewerUrl !== '' ? ' data-url="'.$this->h($viewerUrl).'"' : '';
            $html[] = '<a href="#" data-id="'.$this->h($thumb['nonce'] ?? '').'"'.$dataUrl.' class="mediaViewer_link">'
                .'<span class="ui-icon ui-icon-fullscreen" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;full screen</a>';
            $html[] = '<a href="#" data-id="'.$this->h($thumb['nonce'] ?? '').'"'.$dataUrl.' class="popupMedia_link">'
                .'<span class="ui-icon ui-icon-popup" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;view in popup</a>';
        }

        if ($this->canOpenInMirador($thumb)) {
            $html[] = '<a href="'.$this->h($this->miradorUrl($thumb, true)).'" data-id="'.$this->h($thumb['nonce'] ?? '').'" class="record-media-mirador">'
                .$this->miradorIcon().'&nbsp;Mirador</a>';
        }

        if ($this->canOpenInOpenSeadragon($thumb)) {
            $html[] = '<a href="'.$this->h($this->openSeadragonUrl($thumb)).'" target="_blank" rel="noopener">'
                .'<span class="ui-icon ui-icon-image" style="display:inline-block;"></span>&nbsp;OpenSeadragon</a>';
        }

        if (!empty($thumb['external_url'])) {
            $html[] = '<a href="'.$this->h($this->openInNewTabUrl($thumb)).'" class="external-link" target="_blank" rel="noopener">open in new tab'.(!empty($thumb['linked']) ? '<br>(linked media)' : '').'</a>';
            if ($this->system->hasAccess()) {
                $html[] = '<a href="#" data-id="'.$this->h($thumb['nonce'] ?? '').'" class="refreshThumb_link">'
                    .'<span class="ui-icon ui-icon-refresh" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;refresh thumbnail</a>';
            }
        } else {
            $html[] = '<a href="'.$this->h($this->downloadUrl($thumb)).'" class="image_tool" target="_surf">'
                .'<span class="ui-icon ui-icon-download" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;download'.(!empty($thumb['linked']) ? '<br>(linked media)' : '').'</a>';
        }

        $html[] = $this->renderMetaLinks($thumb);
        $html[] = '</div><!-- CLOSE download_link -->';
        return implode('', $html);
    }

    private function renderMediaBody(array $thumb, bool $isAudioVideo, bool $isImage): string
    {
        $onclick = '';
        $imgStyle = '';

        if ($this->isIiifManifest($thumb)) {
            $onclick = ' onclick="window.HeuristRecordMedia.openMirador(\''.$this->js($this->miradorUrl($thumb, false)).'\')"';
            $imgStyle = ' style="cursor:pointer;"';
            $src = $thumb['thumb'] ?? HEURIST_BASE_URL.'hclient/assets/iiif_logo200.png';
            return '<img src="'.$this->h($src).'"'.$imgStyle.$onclick.'>';
        }

        if ($this->is3d($thumb)) {
            $onclick = ' onclick="window.HeuristRecordMedia.open3dViewer(\''.$this->js($this->viewer3dUrl($thumb)).'\')"';
            $imgStyle = ' style="cursor:pointer;"';
            return '<img src="'.$this->h($thumb['thumb'] ?? '').'"'.$imgStyle.$onclick.'>';
        }

        if ($this->isIiifImage($thumb)) {
            // IIIF Image API records have info.json as the stored external URL and
            // may also carry a generated player URL.  Do not route thumbnail clicks
            // through showPlayer/fileDownload; the zoom toggle needs a real raster
            // image URL (default.jpg).
            $thumbUrl = $this->thumbnailUrl($thumb);
            if(!$this->isMapPopup && !$this->withoutHeader){
                $onclick = ' onclick="window.HeuristRecordMedia.zoomInOut(this,\''.$this->js($thumbUrl).'\',\''.$this->js($this->fileUrl($thumb)).'\')"';
            }
            return '<img src="'.$this->h($thumbUrl).'"'.$onclick.'>';
        }

        if (!empty($thumb['player']) && !$this->isMapPopup && $isAudioVideo && ($this->noclutter || !$this->isMapPopup)) {
            return '<div id="player'.$this->h($thumb['id'] ?? '').'" style="min-height:100px;min-width:200px;text-align:left;">'
                .fileGetPlayerTag($this->system, $thumb['nonce'], $thumb['mimeType'], $thumb['params'], $thumb['external_url'])
                .'</div>';
        }

        if (!empty($thumb['player']) && ($this->noclutter || !$this->isMapPopup) && !$isAudioVideo) {
            $onclick = '';
            if (($isImage || $this->isPdf($thumb)) && !$this->withoutHeader) {
                $onclick = ' onclick="window.hWin.HEURIST4.ui.showPlayer(this,this.parentNode,'.$this->h($thumb['id'] ?? '').',\''.$this->h($thumb['player'].'&origin=recview').'\')"';
            }
            return '<img id="img'.$this->h($thumb['id'] ?? '').'" style="width:200px" src="'.$this->h($thumb['thumb'] ?? '').'"'.$onclick.'>'
                .'<div id="player'.$this->h($thumb['id'] ?? '').'" style="min-height:240px;min-width:320px;display:none;"></div>';
        }

        $thumbUrl = $this->thumbnailUrl($thumb);
        if (($isImage || $this->isIiifImage($thumb)) && !$this->isMapPopup && !$this->withoutHeader) {
            $onclick = ' onclick="window.HeuristRecordMedia.zoomInOut(this,\''.$this->js($thumbUrl).'\',\''.$this->js($this->fileUrl($thumb)).'\')"';
        }

        return '<img src="'.$this->h($thumbUrl).'"'.$onclick.'>';
    }

    private function thumbnailUrl(array $thumb): string
    {
        if ($this->isIiifImage($thumb)) {
            return $this->iiifImageThumbnailUrl($thumb);
        }
        return (string)($thumb['thumb'] ?? '');
    }

    private function iiifImageThumbnailUrl(array $thumb): string
    {
        $fileid = (string)($thumb['nonce'] ?? '');
        if($fileid !== ''){
            return HEURIST_BASE_URL.'?db='.$this->system->dbname()
                .'&offer_download=1&thumb='.rawurlencode($fileid);
        }
        return (string)($thumb['thumb'] ?? '');
    }

    
    private function renderMetaLinks(array $thumb): string
    {
        $html = '';
        $caption = !empty($thumb['caption']) ? linkifyValue($thumb['caption']) : '';
        $description = !empty($thumb['description']) ? linkifyValue($thumb['description']) : '';
        $rights = !empty($thumb['rights']) ? linkifyValue($thumb['rights']) : '';
        $owner = !empty($thumb['owner']) ? linkifyValue($thumb['owner']) : '';

        if ($caption !== '' || $description !== '') {
            $val = $caption !== '' ? $caption : '';
            $val = $description !== '' && $val !== '' ? $val.BR2.$description : $val;
            $val = $val === '' ? $description : $val;
            $html .= '<span class="media-desc" style="cursor:pointer;color:#2080C0;padding-left:7.5px;" title="'.$this->h($val).'">description</span>';
        }

        if ($rights !== '' || $owner !== '') {
            $val = $rights !== '' ? $rights : '';
            $val = $owner !== '' && $val !== '' ? $val.BR2.$owner : $val;
            $val = $val === '' ? $owner : $val;
            $html .= '<span class="media-right" style="cursor:pointer;color:#2080C0;padding-left:7.5px;" title="'.$this->h($val).'">rights</span>';
        }

        if (!empty($thumb['player']) && !$this->withoutHeader) {
            $html .= '<a id="lnk'.$this->h($thumb['id'] ?? '').'" href="#" oncontextmenu="return false;" style="display:none;" '
                .'onclick="window.hWin.HEURIST4.ui.hidePlayer('.$this->h($thumb['id'] ?? '').', this.parentNode)">show thumbnail</a>';
        }

        return $html;
    }

    private function isIiifManifest(array $thumb): bool
    {
        if (!empty($thumb['iiif_manifest_record']) || !empty($thumb['iiif_annotation_record']) || !empty($thumb['manifest_rec_id'])) {
            return true;
        }

        $sourceType = (string)($thumb['sourceType'] ?? '');
        $origName = (string)($thumb['orig_name'] ?? '');

        return in_array($sourceType, ['iiif', 'iiif_manifest'], true)
            || (defined('ULF_IIIF') && $origName===ULF_IIIF);
    }

    private function isIiifImage(array $thumb): bool
    {
        $sourceType = (string)($thumb['sourceType'] ?? '');
        $origName = (string)($thumb['orig_name'] ?? '');

        return $sourceType === 'iiif_image'
            || (defined('ULF_IIIF_IMAGE') && $origName === ULF_IIIF_IMAGE);
    }

    private function isIiifMedia(array $thumb): bool
    {
        $sourceType = (string)($thumb['sourceType'] ?? '');
        $origName = (string)($thumb['orig_name'] ?? '');

        return $this->isIiifManifest($thumb)
            || strpos($sourceType, 'iiif') === 0
            || (defined('ULF_IIIF_IMAGE') && $origName === ULF_IIIF_IMAGE);
    }

    private function is3d(array $thumb): bool
    {
        return !empty($thumb['mode_3d_viewer']);
    }

    private function isAudioVideo(array $thumb): bool
    {
        $mime = (string)($thumb['mimeType'] ?? '');
        return strpos($mime, 'audio/') === 0 || strpos($mime, 'video/') === 0;
    }

    private function isImage(array $thumb): bool
    {
        return strpos((string)($thumb['mimeType'] ?? ''), 'image/') === 0;
    }

    private function isPdf(array $thumb): bool
    {
        return ($thumb['mimeType'] ?? '') === 'application/pdf';
    }

    private function canOpenInMirador(array $thumb): bool
    {
        if ($this->isIiifImage($thumb)) {
            return true;
        }

        $mime = (string)($thumb['mimeType'] ?? '');
        if (strpos($mime, 'image/') === 0) {
            return true;
        }
        if ($this->isAudioVideo($thumb)) {
            return strpos($mime, 'youtube') === false && strpos($mime, 'vimeo') === false && strpos($mime, 'soundcloud') === false;
        }
        return false;
    }

    private function canOpenInOpenSeadragon(array $thumb): bool
    {
        return $this->isImage($thumb) || $this->isIiifImage($thumb);
    }

    private function fileUrl(array $thumb): string
    {
        if ($this->isIiifImage($thumb)) {
            return $this->iiifImageDefaultJpgUrl($thumb, 400);
        }
        if (!empty($thumb['external_url']) && strpos($thumb['external_url'], 'http://') !== 0) {
            return (string)$thumb['external_url'];
        }
        return HEURIST_BASE_URL.'?db='.$this->system->dbname().'&fullres=1&file='.rawurlencode((string)($thumb['nonce'] ?? ''));
    }

    private function mediaViewerUrl(array $thumb): string
    {
        if ($this->isIiifImage($thumb)) {
            return $this->iiifImageDefaultJpgUrl($thumb, 400);
        }
        return (string)($thumb['external_url'] ?? '');
    }

    private function openInNewTabUrl(array $thumb): string
    {
        if ($this->isIiifImage($thumb)) {
            return $this->iiifImageDefaultJpgUrl($thumb, 400);
        }
        return (string)($thumb['external_url'] ?? '');
    }

    private function iiifImageDefaultJpgUrl(array $thumb, ?int $maxSize=400): string
    {
        $url = trim((string)($thumb['external_url'] ?? ''));
        if($url === ''){
            return HEURIST_BASE_URL.'?db='.$this->system->dbname().'&fullres=1&file='.rawurlencode((string)($thumb['nonce'] ?? ''));
        }

        $serviceId = $this->iiifImageServiceIdFromUrl($url);
        $size = $maxSize && $maxSize > 0 ? '!'.intval($maxSize).','.intval($maxSize) : 'full';
        return $serviceId.'/full/'.$size.'/0/default.jpg';
    }

    private function iiifImageServiceIdFromUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('~/info\.json(?:\?.*)?$~', '', $url);
        return rtrim((string)$url, '/');
    }

    private function downloadUrl(array $thumb): string
    {
        return HEURIST_BASE_URL.'?db='.$this->system->dbname().'&debug=3&download=1&file='.rawurlencode((string)($thumb['nonce'] ?? ''));
    }

    private function miradorUrl(array $thumb, bool $asImage): string
    {
        $base = HEURIST_BASE_URL.'hclient/widgets/viewers/miradorViewer.php?db='.rawurlencode($this->system->dbname());

        if(!$asImage){

            $manifestRecID = intval($thumb['manifest_rec_id'] ?? 0);

            if(!empty($thumb['iiif_annotation_record']) && $manifestRecID<1){
                // Annotation record without a managed/linked RT_IIIF_MANIFEST record.
                //
                // Do not open /api/{db}/iiif/manifest/{annotationRecID}; an
                // annotation record is not a Manifest, and exporting it through
                // record_output.php produces an empty Manifest.
                //
                // Instead open miradorViewer.php with id={annotationRecID}. The
                // viewer keeps special annotation-record resolution: it resolves
                // the parent overlay/Manifest context and uses canvasUri to select
                // the Canvas targeted by DT_URL.
                return $this->appendCanvasUri($base.'&id='.rawurlencode((string)$this->recordID), $thumb);
            }

            // Managed Manifest record or registered Manifest file.
            //
            // manifest={recID} opens a fully managed RT_IIIF_MANIFEST through the
            // IIIF API. manifest={fileObfuscatedID} opens the registered Manifest
            // file through the IIIF API; for v3 files without RT_IIIF_MANIFEST this
            // is the Heurist annotation-overlay Manifest.
            $manifestID = $manifestRecID>0 ? (string)$manifestRecID : (string)($thumb['nonce'] ?? '');
            return $this->appendCanvasUri($base.'&manifest='.rawurlencode($manifestID), $thumb);
        }

        // Ordinary image/audio/video/IIIF image file.
        //
        // Public miradorViewer.php no longer accepts iiif_image. Use id={file
        // obfuscated ID}; the viewer translates non-numeric id into the internal
        // record_output.php iiif_image parameter for dynamic Manifest generation.
        return $base.'&id='.rawurlencode((string)($thumb['nonce'] ?? ''));
    }

    private function manifestUrl(array $thumb): string
    {
        $manifestRecID = intval($thumb['manifest_rec_id'] ?? 0);
        $apiRoot = HEURIST_BASE_URL.'api/'.rawurlencode($this->system->dbname()).'/iiif/manifest/';

        if($manifestRecID>0){
            // Explicit managed Manifest record known from the renderer.
            return $apiRoot.rawurlencode((string)$manifestRecID);
        }

        if(!empty($thumb['iiif_manifest_record'])){
            // Current record is RT_IIIF_MANIFEST, but the thumbnail did not carry
            // manifest_rec_id. The current record id is therefore the Manifest id.
            return $apiRoot.rawurlencode((string)$this->recordID);
        }

        if(!empty($thumb['iiif_annotation_record'])){
            // Annotation records are not Manifests. If the renderer has not supplied
            // manifest_rec_id above, there is no safe Manifest-content URL to expose
            // here. Mirador opening is still handled by miradorUrl() via id={recordID}.
            return '';
        }

        // Registered IIIF Manifest files must go through the Heurist IIIF API.
        // The API decides whether the file is already managed by an RT_IIIF_MANIFEST
        // record, or should be served as v3 overlay / v2 pass-through source Manifest.
        if($this->isIiifManifest($thumb) && !empty($thumb['nonce'])){
            return $apiRoot.rawurlencode((string)$thumb['nonce']);
        }

        // Raw/original file content fallback.
        if(!empty($thumb['external_url'])){
            return (string)$thumb['external_url'];
        }

        return HEURIST_BASE_URL.'?db='.rawurlencode($this->system->dbname())
            .'&file='.rawurlencode((string)($thumb['nonce'] ?? ''));
    }

    private function appendCanvasUri(string $url, array $thumb): string
    {
        if(!empty($thumb['canvas_uri'])){
            $url .= '&canvasUri='.rawurlencode((string)$thumb['canvas_uri']);
        }
        return $url;
    }

    private function openSeadragonUrl(array $thumb): string
    {
        $url = HEURIST_BASE_URL.'hclient/widgets/viewers/openSeadragonViewer.php?db='.rawurlencode($this->system->dbname())
            .'&lang='.rawurlencode($this->language);

        if($this->isIiifImage($thumb)){
            // For IIIF Image API resources, pass a rendered default.jpg URL, not
            // the info.json URL stored as ulf_ExternalFileReference.  Use full
            // size here; ordinary record-view links use the bounded 400px URL.
            return $url.'&image='.rawurlencode($this->iiifImageDefaultJpgUrl($thumb, null));
        }

        $fileID = $thumb['id'] ?? $thumb['nonce'] ?? '';
        return $url.'&recID='.rawurlencode((string)$fileID);
    }

    private function viewer3dUrl(array $thumb): string
    {
        return HEURIST_BASE_URL.'hclient/widgets/viewers/'.rawurlencode((string)$thumb['mode_3d_viewer']).'Viewer.php?db='
            .rawurlencode($this->system->dbname()).'&file='.rawurlencode((string)($thumb['nonce'] ?? ''));
    }

    private function miradorIcon(): string
    {
        return '<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align:middle;filter:invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);"></span>';
    }

    private function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function js($value): string
    {
        return str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", '', ''], (string)$value);
    }
}
