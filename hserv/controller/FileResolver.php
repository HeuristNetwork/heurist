<?php
namespace hserv\controller;

/**
 * FileResolver
 *
 * PURE resolver used by root/index.php.
 *
 * Returns canonical, VERSIONED redirect URLs for file/asset/disclaimer requests.
 * Does not include scripts and does not send headers.
 */
final class FileResolver
{
    /**
     * @param string $version
     * @param array  $params Merged request params (query wins)
     * @return array|null ['url' => string, 'status' => int]
     */
    public static function resolve(string $version, array $params): ?array
    {
        // file/thumb/icon
        if (array_key_exists('file', $params) || array_key_exists('thumb', $params) || array_key_exists('icon', $params)) {
            // Legacy behaviour: icon uses fileGet, everything else uses fileDownload
            $script = array_key_exists('icon', $params)
                ? "/{$version}/hserv/controller/fileGet.php"
                : "/{$version}/hserv/controller/fileDownload.php";

            return ['url' => $script . '?' . http_build_query($params), 'status' => 302];
        }

        // asset (context help)
        if (!empty($params['asset'])) {
            return self::resolveAsset($version, $params);
        }

        // disclaimer: redirect to allow-listed static documents
        if (!empty($params['disclaimer'])) {
            $name = (string)$params['disclaimer'];
            if ($name === 'association_membership.html') {
                return ['url' => "/{$version}/admin/verification/" . rawurlencode($name), 'status' => 302];
            }
            if ($name === 'terms_and_conditions.html') {
                return ['url' => "/{$version}/movetoparent/" . rawurlencode($name), 'status' => 302];
            }
            return null;
        }

        return null;
    }

    private static function resolveAsset(string $version, array $params): ?array
    {
        $name = $params['asset']??'';
        
        $part = strstr($name,'#');
        if($part){
             $name = strstr($name,'#');
        }
        
        if($name===''){
            return ['url' => null, 'status' => 404];
        }
        
        $asset_folder = 'documentation/context_help/';

        $name = basename($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);

        // allowed extensions
        $allowed_ext = ['htm', 'html'];

        /* strip extension if it's not valid
        if (!$extension || !in_array($extension, $allowed_ext, true)) {
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extensions_to_try = $allowed_ext;
        } else {
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extensions_to_try = [$extension];
        }*/

        // locale handling
        $locale = $params['lang']; // locale
        if ($locale && preg_match('/^[A-Za-z]{3}$/', $locale)) {
            $locale = strtolower($locale);
            $locale = ($locale === 'eng') ? '' : ($locale . '/');
        } else {
            $locale = '';
        }

        // try to resolve asset (locale first, then fallback)
        $asset = null;

        foreach ($allowed_ext as $ext) {
            $candidate = $asset_folder . $locale . $base . '.' . $ext;
            if (file_exists($candidate)) {
                $asset = $candidate;
                break;
            }
        }

        if (!$asset && $locale !== '') {
            // fallback without locale
            foreach ($allowed_ext as $ext) {
                $candidate = $asset_folder . $base . '.' . $ext;
                if (file_exists($candidate)) {
                    $asset = $candidate;
                    break;
                }
            }
            if($asset){
                 $asset = $asset . ' ' . $part;
            }
        }
        
        return ['url' => $asset, 'status' => $asset?302:404];
    }
}
