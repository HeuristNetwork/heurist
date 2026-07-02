<?php
/**
* IiifCanvasJson.php - Stateless parser/composer helpers for IIIF Canvas JSON.
*/
namespace hserv\iiif;

/**
 * Stateless helpers for IIIF Presentation Canvas JSON.
 * Initial Patch 1 home for format-specific methods; entity integration follows
 * in the Canvas-focused patch.
 */
class IiifCanvasJson
{
    public static function getJsonId(array $canvas): ?string
    {
        $id = $canvas['id'] ?? ($canvas['@id'] ?? null);
        return is_string($id) && $id!=='' ? $id : null;
    }

    public static function isV3Canvas(array $canvas): bool
    {
        return ($canvas['type'] ?? null)==='Canvas';
    }

    public static function isV2Canvas(array $canvas): bool
    {
        return ($canvas['@type'] ?? null)==='sc:Canvas';
    }

    /** Compose a basic v3 Canvas from ready JSON fields. */
    public static function composeCanvas(string $id, $label, array $options=array()): array
    {
        $canvas = array(
            'id' => $id,
            'type' => 'Canvas',
            'label' => IiifManifestJson::languageMapOrNone($label, 'Canvas'),
            'items' => $options['items'] ?? array()
        );

        if(!empty($options['summary'])){
            $canvas['summary'] = IiifManifestJson::languageMapOrNone($options['summary']);
        }
        if(!empty($options['height'])){ $canvas['height'] = intval($options['height']); }
        if(!empty($options['width'])){ $canvas['width'] = intval($options['width']); }
        if(!empty($options['duration'])){ $canvas['duration'] = floatval($options['duration']); }
        if(!empty($options['thumbnail']) && is_array($options['thumbnail'])){ $canvas['thumbnail'] = $options['thumbnail']; }
        if(!empty($options['annotations']) && is_array($options['annotations'])){ $canvas['annotations'] = $options['annotations']; }

        return $canvas;
    }

    public static function composePaintingPage(string $canvasId, array $body): array
    {
        return array(
            'id' => $canvasId.'/painting-page',
            'type' => 'AnnotationPage',
            'items' => array(
                array(
                    'id' => $canvasId.'/painting',
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => $body,
                    'target' => $canvasId
                )
            )
        );
    }

    public static function annotationPageRef(string $pageUrl): array
    {
        return array(
            'id' => $pageUrl,
            'type' => 'AnnotationPage'
        );
    }

    public static function bodyTypeFromMime(?string $mimeType): string
    {
        $mimeType = strtolower((string)$mimeType);
        if(strpos($mimeType, 'video/')===0){ return 'Video'; }
        if(strpos($mimeType, 'audio/')===0){ return 'Sound'; }
        return 'Image';
    }


    /** Extract the primary painting body URL from a IIIF v3/v2 Canvas. */
    public static function extractPrimaryPaintingBodyUrl(array $canvas): ?string
    {
        $info = self::extractPrimaryPaintingBodyInfo($canvas);
        return $info['body_url'] ?? null;
    }

    /**
     * Extract primary painting information from a IIIF v3/v2 Canvas.
     *
     * Returns:
     * - body_url: the painted image/resource URL, when present
     * - service: normalized Presentation 3 ImageService object, when present
     * - service_info_url: canonical service info.json URL, when service is present
     */
    public static function extractPrimaryPaintingBodyInfo(array $canvas): array
    {
        $empty = array('body_url'=>null, 'service'=>null, 'service_info_url'=>null);

        // IIIF Presentation v3: Canvas.items[] -> AnnotationPage.items[] -> painting Annotation.body
        foreach((array)@$canvas['items'] as $page){
            if(!is_array($page)){ continue; }
            foreach((array)@$page['items'] as $anno){
                if(!is_array($anno)){ continue; }
                $motivation = @$anno['motivation'];
                if(is_array($motivation)){ $motivation = reset($motivation); }
                if($motivation && self::stripIiifPrefix((string)$motivation)!=='painting'){
                    continue;
                }
                return self::paintingInfoFromBody(@$anno['body']);
            }
        }

        // IIIF Presentation v2: Canvas.images[] -> Annotation.resource
        foreach((array)@$canvas['images'] as $anno){
            if(!is_array($anno)){ continue; }
            return self::paintingInfoFromBody(@$anno['resource']);
        }

        return $empty;
    }

    private static function paintingInfoFromBody($body): array
    {
        $service = self::extractImageService($body);
        return array(
            'body_url' => self::extractBodyUrl($body),
            'service' => $service,
            'service_info_url' => self::imageServiceInfoUrl($service)
        );
    }

    /** Extract and normalize an Image API service from a v2/v3 body object. */
    public static function extractImageService($body): ?array
    {
        if(!is_array($body)){
            return null;
        }

        $service = $body['service'] ?? null;
        if(!$service){
            return null;
        }

        if(is_array($service) && array_keys($service)===range(0, count($service)-1)){
            $service = reset($service);
        }

        return self::normalizeImageService(is_array($service) ? $service : null);
    }

    /** Normalize Image API service JSON to the Presentation 3 service object shape. */
    public static function normalizeImageService(?array $service): ?array
    {
        if(!is_array($service)){
            return null;
        }

        $id = $service['id'] ?? ($service['@id'] ?? null);
        if(!is_string($id) || trim($id)===''){
            return null;
        }
        $id = self::imageServiceIdFromUrl($id);

        $type = $service['type'] ?? ($service['@type'] ?? null);
        $contextText = '';
        if(isset($service['@context'])){
            $contextText = is_array($service['@context']) ? json_encode($service['@context']) : (string)$service['@context'];
        }

        if(!$type || $type==='iiif:ImageService' || $type==='ImageService'){
            $type = (strpos($contextText, '/api/image/3/')!==false) ? 'ImageService3' : 'ImageService2';
        }

        $profile = $service['profile'] ?? null;
        if(is_array($profile)){
            $profile = reset($profile);
        }
        if(is_string($profile)){
            $profile = self::normalizeImageServiceProfile($profile);
        }else{
            $profile = null;
        }

        $out = array(
            'id' => $id,
            'type' => (string)$type
        );
        if($profile){
            $out['profile'] = $profile;
        }
        return $out;
    }

    public static function imageServiceIdFromUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('~/info\.json$~', '', $url);
        return rtrim($url, '/');
    }

    public static function imageServiceInfoUrl(?array $service): ?string
    {
        if(!is_array($service) || empty($service['id']) || !is_string($service['id'])){
            return null;
        }
        return self::imageServiceIdFromUrl($service['id']).'/info.json';
    }

    private static function normalizeImageServiceProfile(string $profile): string
    {
        switch($profile){
            case 'http://iiif.io/api/image/2/level0.json':
            case 'https://iiif.io/api/image/2/level0.json':
                return 'level0';
            case 'http://iiif.io/api/image/2/level1.json':
            case 'https://iiif.io/api/image/2/level1.json':
                return 'level1';
            case 'http://iiif.io/api/image/2/level2.json':
            case 'https://iiif.io/api/image/2/level2.json':
                return 'level2';
            default:
                return $profile;
        }
    }

    /** Extract a Canvas thumbnail URL, when the Manifest has a dedicated thumbnail entry. */
    public static function extractThumbnailUrl(array $canvas): ?string
    {
        return self::extractBodyUrl(@$canvas['thumbnail']);
    }

    /** Extract URL from IIIF body/thumbnail object, array, or string. */
    public static function extractBodyUrl($body): ?string
    {
        if(is_string($body)){
            return preg_match('/^https?:\/\//i', $body) ? $body : null;
        }

        if(!is_array($body)){
            return null;
        }

        if(array_keys($body)===range(0, count($body)-1)){
            foreach($body as $item){
                $url = self::extractBodyUrl($item);
                if($url){ return $url; }
            }
            return null;
        }

        foreach(array('id', '@id') as $key){
            if(!empty($body[$key]) && is_string($body[$key]) && preg_match('/^https?:\/\//i', $body[$key])){
                return $body[$key];
            }
        }

        // Some IIIF Image API entries expose the service URL but not a direct body URL.
        // Use the service id only as a fallback.
        $service = @$body['service'];
        if(is_array($service)){
            $url = self::extractBodyUrl($service);
            if($url){ return $url; }
        }

        return null;
    }

    /** Compose a IIIF v3 body object from a registered Heurist file info array. */
    public static function bodyFromFileInfo($system, array $fileinfo, array $dimensions=array(), bool $fullres=false, ?string $baseUrl=null, ?array $imageService=null): ?array
    {
        if(empty($fileinfo['ulf_ObfuscatedFileID'])){
            return null;
        }

        $url = IiifMediaHelper::resourceUrlFromFileInfo($system, $fileinfo, $fullres, $baseUrl);

        // Only IIIF Image API resources have a Presentation body service.
        // Do not infer an ImageService from an ordinary Heurist image URL such as
        // ?db=...&fullres=1&file=..., because that URL is a file endpoint, not an
        // IIIF Image API service endpoint.
        $explicitImageService = self::normalizeImageService($imageService);
        $isIiifImageInfo = self::isIiifImageFileInfo($fileinfo);
        $imageService = $explicitImageService ?: ($isIiifImageInfo ? self::imageServiceFromFileInfo($fileinfo, $url) : null);
        $isIiifImage = $isIiifImageInfo && is_array($imageService);

        $mimeType = $fileinfo['fxm_MimeType'] ?? null;
        if(!$mimeType){
            $mimeType = self::mimeTypeFromExtension((string)($fileinfo['ulf_MimeExt'] ?? ''));
        }

        if($isIiifImage){
            $type = 'Image';
            $mimeType = 'image/jpeg';
            $url = self::defaultImageRequestUrlForService($imageService);
        }else{
            $type = self::bodyTypeFromMime($mimeType);
        }

        $body = array(
            'id' => $url,
            'type' => $type
        );
        if($mimeType){
            $body['format'] = $mimeType;
        }

        $height = $dimensions['height'] ?? null;
        $width = $dimensions['width'] ?? null;
        $duration = $dimensions['duration'] ?? null;

        if($height !== null && $height > 0 && in_array($type, array('Image', 'Video'), true)){
            $body['height'] = intval($height);
        }
        if($width !== null && $width > 0 && in_array($type, array('Image', 'Video'), true)){
            $body['width'] = intval($width);
        }
        if($duration !== null && $duration > 0 && in_array($type, array('Video', 'Sound'), true)){
            $body['duration'] = floatval($duration);
        }

        if(is_array($imageService) && !empty($imageService['id']) && $type==='Image'
            && ($isIiifImage || is_array($explicitImageService))){
            $body['service'] = array(self::serviceForPresentationBody($imageService));
        }

        return $body;
    }

    private static function isIiifImageFileInfo(array $fileinfo): bool
    {
        $source = (string)($fileinfo['ulf_PreferredSource'] ?? '');
        $url = (string)($fileinfo['ulf_ExternalFileReference'] ?? '');
        return $source === 'iiif_image' || preg_match('~/info\.json$~', $url) === 1;
    }

    private static function imageServiceFromFileInfo(array $fileinfo, ?string $resourceUrl=null): ?array
    {
        $sourceUrl = (string)($fileinfo['ulf_ExternalFileReference'] ?? '');
        if($sourceUrl===''){
            $sourceUrl = (string)$resourceUrl;
        }
        if($sourceUrl==='' || !preg_match('/^https?:\/\//i', $sourceUrl)){
            return null;
        }
        return array(
            'id' => self::imageServiceIdFromUrl($sourceUrl),
            'type' => 'ImageService2'
        );
    }

    private static function defaultImageRequestUrlForService(array $service): string
    {
        $id = self::imageServiceIdFromUrl((string)$service['id']);
        $size = ((string)($service['type'] ?? '') === 'ImageService3') ? 'max' : 'full';
        return $id.'/full/'.$size.'/0/default.jpg';
    }

    private static function serviceForPresentationBody(array $service): array
    {
        $out = array(
            'id' => self::imageServiceIdFromUrl((string)$service['id']),
            'type' => (string)($service['type'] ?? 'ImageService2')
        );
        if(!empty($service['profile'])){
            $out['profile'] = $service['profile'];
        }
        return $out;
    }

    /** Compose a complete managed/generated IIIF v3 Canvas from ready file metadata. */
    public static function composeCanvasFromFileInfo($system, array $fileinfo, string $canvasUrl, $label, array $options=array()): ?array
    {
        $resourceUrl = IiifMediaHelper::resourceUrlFromFileInfo(
            $system,
            $fileinfo,
            !empty($options['body_fullres']),
            $options['base_url'] ?? null
        );

        $dimensions = IiifMediaHelper::resolveDimensionsForFileInfo(
            $fileinfo,
            is_array($options['dimensions'] ?? null) ? $options['dimensions'] : array(),
            $resourceUrl,
            is_array($options['default_dimensions'] ?? null) ? $options['default_dimensions'] : array('width'=>1000, 'height'=>800)
        );

        $body = self::bodyFromFileInfo(
            $system,
            $fileinfo,
            $dimensions,
            !empty($options['body_fullres']),
            $options['base_url'] ?? null,
            is_array($options['image_service'] ?? null) ? $options['image_service'] : null
        );
        if(!$body){
            return null;
        }

        $thumbnail = null;
        if(is_array($options['thumbnail_fileinfo'] ?? null)){
            $thumbnail = self::bodyFromFileInfo($system, $options['thumbnail_fileinfo'], array(), false, $options['base_url'] ?? null);
        }
        if(!$thumbnail && !empty($options['thumbnail_url'])){
            $thumbnail = array(
                'id' => (string)$options['thumbnail_url'],
                'type' => 'Image',
                'format' => $options['thumbnail_format'] ?? 'image/png'
            );
        }

        $canvasOptions = array(
            'summary' => $options['summary'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'width' => $dimensions['width'] ?? null,
            'duration' => $dimensions['duration'] ?? null,
            'items' => array(self::composePaintingPage($canvasUrl, $body))
        );

        if($thumbnail){
            $canvasOptions['thumbnail'] = array($thumbnail);
        }

        if(!empty($options['annotation_page_url'])){
            $canvasOptions['annotations'] = array(self::annotationPageRef((string)$options['annotation_page_url']));
        }

        return self::composeCanvas($canvasUrl, $label, $canvasOptions);
    }

    private static function stripIiifPrefix(string $value): string
    {
        $pos = strrpos($value, ':');
        return $pos!==false ? substr($value, $pos+1) : $value;
    }

    public static function mimeTypeFromExtension(string $ext): ?string
    {
        $ext = strtolower(trim($ext));
        if($ext==='') { return null; }
        if(strpos($ext, '/')!==false){ return $ext; }
        switch($ext){
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            case 'png': return 'image/png';
            case 'gif': return 'image/gif';
            case 'webp': return 'image/webp';
            case 'tif':
            case 'tiff': return 'image/tiff';
            case 'mp4': return 'video/mp4';
            case 'mp3': return 'audio/mpeg';
            case 'wav': return 'audio/wav';
            default: return null;
        }
    }
}
?>
