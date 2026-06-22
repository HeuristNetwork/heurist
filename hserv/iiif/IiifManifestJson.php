<?php
/**
* IiifManifestJson.php - Stateless parser/composer helpers for IIIF Manifests.
*
* Keeps IIIF Presentation JSON shape logic out of record-backed entity classes.
*/
namespace hserv\iiif;

/**
 * Stateless helpers for IIIF Presentation Manifest and Collection JSON.
 */
class IiifManifestJson
{
    public const CONTEXT_V3 = 'http://iiif.io/api/presentation/3/context.json';

    public static function getJsonId(array $json): ?string
    {
        $id = $json['id'] ?? ($json['@id'] ?? null);
        return is_string($id) && $id!=='' ? $id : null;
    }

    public static function isV3Manifest(array $json): bool
    {
        return ($json['type'] ?? null)==='Manifest' && is_array($json['items'] ?? null);
    }

    public static function isV2Manifest(array $json): bool
    {
        return ($json['@type'] ?? null)==='sc:Manifest' && is_array($json['sequences'] ?? null);
    }

    public static function isManifest(array $json): bool
    {
        return self::isV3Manifest($json) || self::isV2Manifest($json);
    }

    /** Compose a minimal legal IIIF Presentation API v3 Manifest. */
    public static function emptyManifest(string $id, $label='Heurist IIIF manifest'): array
    {
        return self::composeManifest($id, $label, array());
    }

    /** Compose a IIIF Presentation API v3 Manifest from ready JSON fields. */
    public static function composeManifest(string $id, $label, array $items=array(), array $options=array()): array
    {
        $manifest = array(
            '@context' => self::CONTEXT_V3,
            'id' => $id,
            'type' => 'Manifest',
            'label' => self::languageMapOrNone($label, 'Heurist IIIF manifest'),
            'items' => array_values($items)
        );

        if(!empty($options['summary'])){
            $manifest['summary'] = self::languageMapOrNone($options['summary']);
        }

        if(!empty($options['requiredStatement'])){
            $manifest['requiredStatement'] = $options['requiredStatement'];
        }elseif(!empty($options['copyright'])){
            $manifest['requiredStatement'] = self::composeRequiredStatement('Copyright', $options['copyright']);
        }

        if(!empty($options['rights']) && is_string($options['rights'])){
            $manifest['rights'] = $options['rights'];
        }

        if(!empty($options['thumbnail']) && is_array($options['thumbnail'])){
            $manifest['thumbnail'] = $options['thumbnail'];
        }

        if(!empty($options['metadata']) && is_array($options['metadata'])){
            $manifest['metadata'] = $options['metadata'];
        }

        if(!empty($options['provider']) && is_array($options['provider'])){
            $manifest['provider'] = $options['provider'];
        }

        return $manifest;
    }

    /** Compose a IIIF Presentation API v3 Collection. */
    public static function composeCollection(string $id, $label, array $items=array(), array $options=array()): array
    {
        $collection = array(
            '@context' => self::CONTEXT_V3,
            'id' => $id,
            'type' => 'Collection',
            'label' => self::languageMapOrNone($label, 'Heurist IIIF collection'),
            'items' => array_values($items)
        );

        if(!empty($options['summary'])){
            $collection['summary'] = self::languageMapOrNone($options['summary']);
        }

        return $collection;
    }

    /** Compose a v3 requiredStatement object. */
    public static function composeRequiredStatement($label, $value): array
    {
        return array(
            'label' => self::languageMapOrNone($label, 'Copyright'),
            'value' => self::languageMapOrNone($value)
        );
    }

    /**
     * Transform a v3 source Manifest for overlay output.
     * Source Canvas.annotations are always removed; Heurist AnnotationPage links are
     * then added unless $omitAnnotationPages is true.
     *
     * @param callable $annotationPageUrlProvider function(string $canvasId): string
     */
    public static function transformOverlayV3Manifest(array $manifest, callable $annotationPageUrlProvider, bool $omitAnnotationPages=false): ?array
    {
        if(!self::isV3Manifest($manifest)){
            return null;
        }

        foreach($manifest['items'] as $idx=>$canvas){
            if(!is_array($canvas) || ($canvas['type'] ?? null)!=='Canvas'){
                continue;
            }

            $canvasId = $canvas['id'] ?? null;
            if(!is_string($canvasId) || $canvasId===''){
                continue;
            }

            unset($manifest['items'][$idx]['annotations']);

            if(!$omitAnnotationPages){
                $manifest['items'][$idx]['annotations'] = array(
                    array(
                        'id' => $annotationPageUrlProvider($canvasId),
                        'type' => 'AnnotationPage'
                    )
                );
            }
        }

        return $manifest;
    }

    /** Accept an already-valid language map, list of text values, or plain string. */
    public static function languageMapOrNone($value, ?string $fallback=null): array
    {
        if(is_array($value)){
            if(self::looksLikeLanguageMap($value)){
                return $value;
            }

            $out = array();
            foreach($value as $item){
                if(is_string($item) && trim($item)!==''){
                    $out['none'][] = trim($item);
                }
            }
            if(!empty($out)){
                return $out;
            }
        }elseif(is_string($value) && trim($value)!==''){
            return array('none' => array(trim($value)));
        }

        return array('none' => array($fallback ?? ''));
    }

    private static function looksLikeLanguageMap(array $value): bool
    {
        if(empty($value)){
            return false;
        }
        foreach($value as $lang=>$vals){
            if(!is_string($lang) || !is_array($vals)){
                return false;
            }
        }
        return true;
    }
}
?>
