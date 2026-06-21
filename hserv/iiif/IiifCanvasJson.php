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
        // IIIF Presentation v3: Canvas.items[] -> AnnotationPage.items[] -> painting Annotation.body.id
        foreach((array)@$canvas['items'] as $page){
            if(!is_array($page)){ continue; }
            foreach((array)@$page['items'] as $anno){
                if(!is_array($anno)){ continue; }
                $motivation = @$anno['motivation'];
                if(is_array($motivation)){ $motivation = reset($motivation); }
                if($motivation && self::stripIiifPrefix((string)$motivation)!=='painting'){
                    continue;
                }
                $url = self::extractBodyUrl(@$anno['body']);
                if($url){ return $url; }
            }
        }

        // IIIF Presentation v2: Canvas.images[] -> Annotation.resource.@id/id
        foreach((array)@$canvas['images'] as $anno){
            if(!is_array($anno)){ continue; }
            $url = self::extractBodyUrl(@$anno['resource']);
            if($url){ return $url; }
        }

        return null;
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
    public static function bodyFromFileInfo($system, array $fileinfo, array $dimensions=array(), bool $fullres=false, ?string $baseUrl=null): ?array
    {
        if(empty($fileinfo['ulf_ObfuscatedFileID'])){
            return null;
        }

        $url = IiifMediaHelper::resourceUrlFromFileInfo($system, $fileinfo, $fullres, $baseUrl);
        $mimeType = $fileinfo['fxm_MimeType'] ?? null;
        if(!$mimeType){
            $mimeType = self::mimeTypeFromExtension((string)($fileinfo['ulf_MimeExt'] ?? ''));
        }
        $type = self::bodyTypeFromMime($mimeType);

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

        return $body;
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

        $body = self::bodyFromFileInfo($system, $fileinfo, $dimensions, !empty($options['body_fullres']), $options['base_url'] ?? null);
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
