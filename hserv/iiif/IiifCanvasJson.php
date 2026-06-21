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
