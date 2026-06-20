<?php
/**
* IiifMediaHelper.php - Shared IIIF media/file helpers.
*
* Small stateless helpers used by IIIF Manifest/Canvas output.  Keep file/media
* URL and dimension resolution here rather than in record-type entity classes.
*/
namespace hserv\utilities;

class IiifMediaHelper
{

    public static function registeredFileInfoForUlfID($system, int $ulfID): ?array
    {
        if($ulfID<1 || !function_exists('fileGetFullInfo')){
            return null;
        }

        // fileGetFullInfo() already accepts either ulf_ID or ulf_ObfuscatedFileID.
        // Do not duplicate recordFile.php logic here.
        $info = fileGetFullInfo($system, intval($ulfID));
        return !empty($info) && is_array($info[0]) ? $info[0] : null;
    }

    public static function isIiifImageInfoFile(array $fileinfo): bool
    {
        return ($fileinfo['ulf_PreferredSource'] ?? '') === 'iiif_image'
            || (defined('ULF_IIIF_IMAGE') && ($fileinfo['ulf_OrigFileName'] ?? '') === ULF_IIIF_IMAGE);
    }

    /**
     * Return the media URL used by Heurist IIIF output for a registered file.
     *
     * Preserve existing behaviour: secure/non-local external references are used
     * directly, while ordinary/local resources are served through Heurist's file
     * endpoint. Plain http external resources are also served through Heurist.
     */
    public static function resourceUrlFromFileInfo($system, array $fileinfo, bool $fullres=true, ?string $baseUrl=null): string
    {
        $externalUrl = trim((string)($fileinfo['ulf_ExternalFileReference'] ?? ''));
        if($externalUrl !== '' && strpos($externalUrl, 'http://') !== 0){
            return $externalUrl;
        }

        $baseUrl = $baseUrl ?: (defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : HEURIST_BASE_URL);
        return $baseUrl.'?db='.$system->dbname()
            .($fullres ? '&fullres=1' : '')
            .'&file='.$fileinfo['ulf_ObfuscatedFileID'];
    }

    /**
     * Resolve media dimensions using the common IIIF/Heurist fallback order.
     *
     * $preferredDimensions should already contain any Canvas-record dimensions,
     * for example ['width'=>..., 'height'=>..., 'duration'=>...].
     */
    public static function resolveDimensionsForFileInfo(array $fileinfo, array $preferredDimensions=array(), ?string $resourceUrl=null, array $defaults=array()): array
    {
        $out = array(
            'width' => self::numericOrNull($defaults['width'] ?? null),
            'height' => self::numericOrNull($defaults['height'] ?? null),
            'duration' => self::numericOrNull($defaults['duration'] ?? null)
        );

        foreach(array('width', 'height', 'duration') as $key){
            $value = self::numericOrNull($preferredDimensions[$key] ?? null);
            if($value !== null && $value > 0){
                $out[$key] = $value;
            }
        }

        if(($out['width']===null || $out['height']===null) && self::isIiifImageInfoFile($fileinfo)){
            $infoUrl = $fileinfo['ulf_ExternalFileReference'] ?? null;
            if($infoUrl){
                $json = json_decode(loadRemoteURLContent($infoUrl), true);
                if(is_array($json)){
                    if(($out['width']===null || $out['width']<=0) && !empty($json['width']) && is_numeric($json['width'])){
                        $out['width'] = floatval($json['width']);
                    }
                    if(($out['height']===null || $out['height']<=0) && !empty($json['height']) && is_numeric($json['height'])){
                        $out['height'] = floatval($json['height']);
                    }
                }
            }
        }

        $mimeType = strtolower((string)($fileinfo['fxm_MimeType'] ?? ''));
        $externalUrl = $fileinfo['ulf_ExternalFileReference'] ?? null;
        if(($out['width']===null || $out['height']===null)
            && strpos($mimeType, 'image/')===0
            && !$externalUrl
            && !self::isIiifImageInfoFile($fileinfo)
            && $resourceUrl){
            $size = @getimagesize($resourceUrl);
            if(is_array($size)){
                if(($out['width']===null || $out['width']<=0) && !empty($size[0])){
                    $out['width'] = floatval($size[0]);
                }
                if(($out['height']===null || $out['height']<=0) && !empty($size[1])){
                    $out['height'] = floatval($size[1]);
                }
            }
        }

        return $out;
    }

    private static function numericOrNull($value): ?float
    {
        if($value === null || $value === ''){
            return null;
        }
        if(is_string($value)){
            $value = trim($value);
        }
        return is_numeric($value) ? floatval($value) : null;
    }
}
?>
