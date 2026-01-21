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
            return self::resolveAsset($version, (string)$params['asset'], $params);
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

    private static function resolveAsset(string $version, string $assetName, array $params): ?array
    {
        $part = '';
        if (strpos($assetName, '#') !== false) {
            [$assetName, $part] = explode('#', $assetName, 2);
            $part = '#' . $part;
        }

        // default extension .htm
        $ext = strtolower(pathinfo($assetName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $assetName .= '.htm';
        }

        $locale = '';
        if (!empty($params['lang']) && preg_match('/^[A-Za-z]{3}$/', (string)$params['lang'])) {
            $l = strtolower((string)$params['lang']);
            if ($l !== 'eng') {
                $locale = $l . '/';
            }
        }

        $base = "/{$version}/documentation/context_help/";
        $candidate = $base . $locale . rawurlencode(basename($assetName));
        // We cannot reliably check file existence at this layer without filesystem knowledge;
        // default to locale candidate when locale provided, else default.
        $url = $candidate;
        if ($locale === '') {
            $url = $base . rawurlencode(basename($assetName));
        }

        return ['url' => $url . $part, 'status' => 302];
    }
}
