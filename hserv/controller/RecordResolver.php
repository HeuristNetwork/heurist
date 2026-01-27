<?php
namespace hserv\controller;

/**
 * RecordResolver
 *
 * PURE resolver used by root/index.php.
 *
 * It does NOT include any scripts and does NOT send headers.
 * It only returns a canonical, VERSIONED URL to redirect to.
 *
 * Responsibilities:
 *  - recID/recid/id: choose view/edit/export endpoints
 *  - rty/dty/trm/rst: choose getDBStructureAsXML endpoint
 *  - concept IDs (DBID-RECID): redirect to remote server if registry entry exists
 *
 * Conventions:
 *  - query wins; the caller should pass merged params
 *  - action=hml normalizes to fmt=hml
 */
final class RecordResolver
{
    /**
     * @param string $version  Version folder, e.g. "heurist" or "h7-alpha".
     * @param array  $params   Merged request params (query wins).
     * @param string $serverRoot Absolute filesystem path to web root (for loading registry when needed).
     *
     * @return array|null ['url' => string, 'status' => int]
     */
    public static function resolve(string $version, array $params, string $serverRoot=null): ?array
    {
        // ---- Definitions (structure export)
        foreach (['rty','dty','trm','rst'] as $k) {
            if (!empty($params[$k])) {
                $qs = http_build_query(['db' => $params['db'] ?? null, $k => $params[$k]]);
                return ['url' => "/{$version}/hserv/structure/export/getDBStructureAsXML.php?{$qs}", 'status' => 302];
            }
        }

        // ---- Records
        $recToken = $params['recID'] ?? ($params['recid'] ?? ($params['id'] ?? null));
        if ($recToken === null || $recToken === '') {
            return null;
        }

        $db = $params['db'] ?? null;

        // Determine fmt/action
        $fmt = $params['fmt'] ?? ($params['format'] ?? null);
        $fmt = ($fmt === null || $fmt === '') ? null : strtolower((string)$fmt);

        $action = !empty($params['action']) ? strtolower((string)$params['action']) : null;
        if ($action === 'hml') {
            $fmt = 'hml';
        } elseif ($action === 'view') {
            $fmt = 'html';
        } elseif ($action === 'edit' || $params['edit'] == 1) {
            $fmt = 'edit';
        }
        if ($fmt === null) {
            $fmt = 'hml';
        }

        // Concept ID? DBID-RECID
        $database_id = 0;
        $recid = $recToken;
        if (strpos((string)$recid, '-') !== false) {
            [$database_id, $recid] = explode('-', (string)$recid, 2);
            $database_id = (int)$database_id;
            $recid = (int)$recid;
        } else {
            $recid = (int)$recid;
            // Legacy: numeric db treated as registry ID
            if (isset($params['db']) && is_numeric($params['db']) && (string)(int)$params['db'] === (string)$params['db']) {
                $database_id = (int)$params['db'];
            }
        }

        // Remote registry resolution
        if ($database_id > 0) {
            $remote = self::resolveRemoteDbUrl($serverRoot, $version, $database_id);
            if ($remote) {
                // Remote endpoints expect parameterized URL (as per legacy resolver.php)
                $q = ['recID' => $database_id . '-' . $recid, 'fmt' => $fmt];
                if (!empty($params['depth']))    $q['depth'] = (int)$params['depth'];
                if (!empty($params['noheader'])) $q['noheader'] = 1;
                if (!empty($params['action']))   $q['action'] = (string)$params['action'];

                $sep = (strpos($remote, '?') === false) ? '?' : '&';
                return ['url' => $remote . $sep . http_build_query($q), 'status' => 302];
            }
        }

        // Local routes require db
        if (!$db) {
            return null;
        }

        // Build redirect to concrete script
        if ($fmt === 'html') {
            if (!empty($params['noheader'])) {
                $q = self::pick($params, ['db','recID','recid','id','noheader']);
                $q['db'] = $db;
                $q['recID'] = $recid;
                $q['noheader'] = 1;
                return ['url' => "/{$version}/viewers/record/renderRecordData.php?" . http_build_query($q), 'status' => 302];
            }
            $q = self::pick($params, ['db','recID','recid','id']);
            $q['db'] = $db;
            $q['recID'] = $recid;
            return ['url' => "/{$version}/viewers/record/viewRecord.php?" . http_build_query($q), 'status' => 302];
        }

        if ($fmt === 'edit') {
            // Preserve as many params as possible (edit surface uses many)
            $q = $params;
            $q['db'] = $db;
            $q['recID'] = $recid;
            unset($q['fmt'], $q['format']);
            return ['url' => "/{$version}/hclient/framecontent/recordEdit.php?" . http_build_query($q), 'status' => 302];
        }

        // Export
        if (in_array($fmt, ['xml','json','rdf','gephi','geojson','iiif'], true)) {
            $q = [
                'vers' => 2,
                'fmt'  => $fmt,
                'db'   => $db,
                'q'    => 'ids:' . $recid,
            ];
            if (!empty($params['depth'])) $q['depth'] = (int)$params['depth'];
            return ['url' => "/{$version}/hserv/controller/record_output.php?" . http_build_query($q), 'status' => 302];
        }

        // Default: hml via flathml
        $q = [
            'w'  => 'a',
            'db' => $db,
            'q'  => 'ids:' . $recid,
        ];
        if (!empty($params['depth'])) $q['depth'] = (int)$params['depth'];
        return ['url' => "/{$version}/export/xml/flathml.php?" . http_build_query($q), 'status' => 302];
    }

    private static function pick(array $params, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $params)) {
                $out[$k] = $params[$k];
            }
        }
        return $out;
    }

    private static function resolveRemoteDbUrl(string $serverRoot, string $version, int $dbID): ?string
    {
        // Load just enough to call DbRegis. This request will end with a redirect.
        //$autoload = rtrim($serverRoot, '/\\') . "/{$version}/autoload.php";
        //$dbregis  = rtrim($serverRoot, '/\\') . "/{$version}/hserv/utilities/DbRegis.php";
        
        $autoload = dirname(__FILE__).'/../../autoload.php';
        $dbregis = dirname(__FILE__).'/../utilities/DbRegis.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (is_file($dbregis)) {
            require_once $dbregis;
        }
        if (!class_exists('hserv\\utilities\\DbRegis')) {
            return null;
        }
        try {
            $url = \hserv\utilities\DbRegis::registrationGet(['dbID' => $dbID]);
            return $url ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
