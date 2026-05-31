<?php
namespace hserv\controller;

//require_once dirname(__FILE__).'/../../autoload.php';

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
 * Pretty path format:
 *   <domain>/<database>/<record|rec|rty|rst|dty|trm|action>/<resource id>
 *   resource id - either ID or concept ID (DBID-RECID)  
 *   If resource id is concept code, it redirects to remote server if registry entry (DBID) exists. 
 *   <database> specified in path is ignored in this case. So user can use placeholder "db"
 *   "action" means the resource is "record". action can be view|edit|hml - view is default
 * 
 * For example
 *  http://127.0.0.1/osmak_1/record/184 - search for rec 184 in db osmak_1
 *  http://127.0.0.1/osmak_1/record/2-8 - search for rec 8 in db #2 
 *  http://127.0.0.1/db/record/2-8      - the same
 *  http://127.0.0.1/osmak_1/rty/10     - returns definitions (xml) for record type 10 
 *  http://127.0.0.1/db/dty/2-4         - returns definitions for field type 4 from database #2
 * 
 *  See conversion of pretty path to $params in ReuestRouter->paramsFromDbResolverPath and paramsFromPrettyRoute
 * 
 * Returned format:
 *  Definitions (rst,rty,dty,trm) are salways XML
 *  For record by default HTML
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
     * @return array|null ['url' => string, 'status' => int] or ['error' => array, 'status' => int]
     */
    public static function resolve(string $version, array $params, string $serverRoot=null): ?array
    {
        // ---- Definitions (structure export)
        foreach (['rty','dty','trm','rst'] as $entity) {
            if (!empty($params[$entity])) {
                
                $params['fmt'] = 'xml';
                list($remote, $recid, $error) = self::resolveRemoteDbUrl($params[$entity], $params['db']??null);
                if($error){
                    self::renderResolverError($error, $params);
                    return ['error' => $error, 'status' => (int)($error['http_status'] ?? 502)];
                }
                if($remote){
                    $sep = (strpos($remote, '?') === false) ? '?' : '&';
                    $qs = [$entity => $params[$entity]];
                    return ['url' => $remote . $sep . http_build_query($qs), 'status' => 302];
                }else{
                    if(!self::isPositiveInt($params[$entity])){
                        $error = array('status'=>HEURIST_INVALID_REQUEST, 'message'=>'Resource Id is not defined');
                        self::renderResolverError($error, $params);
                        return null;
                    }
                    $qs = http_build_query(['db' => $params['db'] ?? null, $entity => $params[$entity]]);
                    return ['url' => "/{$version}/hserv/structure/export/getDBStructureAsXML.php?{$qs}", 'status' => 302];
                }
            }
        }
        
        // Determine fmt/action
        $fmt = $params['fmt'] ?? ($params['format'] ?? null);
        $fmt = ($fmt === null || $fmt === '') ? null : strtolower((string)$fmt);
        $action = !empty($params['action']) ? strtolower((string)$params['action']) : null;
        if ($action === 'hml') {
            $fmt = 'hml';
        } elseif ($action === 'view') {
            $fmt = 'html';
        } elseif ($action === 'edit' || ($params['edit']??null) == 1) {
            $fmt = 'edit';
        }
        
        $query = null;
        if($fmt==='hml'){
            $query = $params['q'] ?? null;    
        }
        
        // ---- Records
        $recToken = $params['recID'] ?? ($params['recid'] ?? ($params['id'] ?? null));
        if (($recToken === null || $recToken === '') && ($query === null || $query === '')) {
            return null; //neither query nor record id defined
        }
        $useRecToken = !($recToken === null || $recToken === '');

        $db = $params['db'] ?? null;


        if ($fmt === null) {
            $fmt = 'hml';
        }
        // Concept ID? DBID-RECID
        // Remote registry resolution
        if($useRecToken){
            list($remote, $recid, $error) = self::resolveRemoteDbUrl($recToken, $db);
            if($error){
                self::renderResolverError($error, $params);
                return ['error' => $error, 'status' => (int)($error['http_status'] ?? 502)];
            }
            if ($remote) {
                // Remote endpoints expect parameterized URL (as per legacy resolver.php)
                $q = ['recID' => $recid, 'fmt' => $fmt];
                if (!empty($params['depth']))    $q['depth'] = (int)$params['depth'];
                if (!empty($params['noheader'])) $q['noheader'] = 1;
                if (!empty($params['action']))   $q['action'] = (string)$params['action'];

                $sep = (strpos($remote, '?') === false) ? '?' : '&';
                return ['url' => $remote . $sep . http_build_query($q), 'status' => 302];
            }
        

            // Local routes require db
            if (!$db) {
                $error = array('status'=>HEURIST_INVALID_REQUEST, 'message'=>'Database is not defined');
                self::renderResolverError($error, $params);
                return null;
            }

            if(!self::isPositiveInt($recid)){
                $error = array('status'=>HEURIST_INVALID_REQUEST, 'message'=>'Record Id is not defined');
                self::renderResolverError($error, $params);
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
        }

        // Export
        if (in_array($fmt, ['xml','json','rdf','gephi','geojson','iiif'], true)) {
            $q = [
                'vers' => 2,
                'fmt'  => $fmt,
                'db'   => $db,
                'q'    => $query ?? ('ids:' . $recid),
            ];
            if (!empty($params['depth'])) $q['depth'] = (int)$params['depth'];
            return ['url' => "/{$version}/hserv/controller/record_output.php?" . http_build_query($q), 'status' => 302];
        }

        // Default: hml via flathml
        $q = [
            'w'  => 'a',
            'db' => $db,
            'q'  => $query ?? ('ids:' . $recid),
        ];
        if (!empty($params['depth'])) $q['depth'] = (int)$params['depth'];
        return ['url' => "/{$version}/export/xml/flathml.php?" . http_build_query($q), 'status' => 302];
    }
    
    
    private static function renderResolverError(array $error, array $params=array()){
        global $globalMessage;
        //$status = (int)($error['http_status'] ?? 502);
        //if($status < 400 || $status > 599){ $status = 500; }
        //http_response_code($status);

        $fmt = strtolower((string)($params['fmt'] ?? ($params['format'] ?? '')));
        if($fmt===''){
            $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
            if(strpos($accept, 'application/json')!==false){
                $fmt = 'json';
            }elseif(strpos($accept, 'application/xml')!==false || strpos($accept, 'text/xml')!==false){
                $fmt = 'xml';
            }else{
                $fmt = 'html';
            }
        }
        if(!in_array($fmt, array('html','xml','json'), true)){
            $fmt = 'html';
        }

        $message = (string)($error['message'] ?? 'Unable to resolve requested resource');
        $sysmsg = isset($error['sysmsg']) && is_array($error['sysmsg']) ?$error['sysmsg'] :[];
        $code = (string)($sysmsg['code'] ?? 'RESOLUTION_ERROR');

        if($fmt==='json'){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('status'=>'error', 'error'=>$error));
        }elseif($fmt==='xml'){
            header('Content-Type: application/xml; charset=utf-8');
            $xml = new \SimpleXMLElement('<error/>');
            $xml->addChild('code', htmlspecialchars($code, ENT_XML1));
            $xml->addChild('message', htmlspecialchars($message, ENT_XML1));
            if(!empty($sysmsg['stage'])) $xml->addChild('stage', htmlspecialchars((string)$sysmsg['stage'], ENT_XML1));
            if(!empty($sysmsg['remote_url'])) $xml->addChild('remote_url', htmlspecialchars((string)$sysmsg['remote_url'], ENT_XML1));
            echo $xml->asXML();
        }else{
            
            header('Content-Type: text/html; charset=utf-8');

            $globalMessage = self::formatResolverErrorMessage($error);
            include_once dirname(__FILE__).'/../../hclient/framecontent/infoMessage.php';
/*            
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Heurist resolver error</title></head><body>';
            echo '<h1>Unable to resolve requested resource</h1>';
            echo '<p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';
            echo '<dl>';
            echo '<dt>Code</dt><dd>'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</dd>';
            if(!empty($error['stage'])) echo '<dt>Stage</dt><dd>'.htmlspecialchars((string)$error['stage'], ENT_QUOTES, 'UTF-8').'</dd>';
            if(!empty($error['remote_url'])) echo '<dt>URL requested</dt><dd>'.htmlspecialchars((string)$error['remote_url'], ENT_QUOTES, 'UTF-8').'</dd>';
            echo '</dl></body></html>';
*/            
        }
        exit;
    }
    
    private static function formatResolverErrorMessage(array $error): string{

        $message = '<h2>Request could not be resolved</h2>';
        $sysmsg = isset($error['sysmsg']) && is_array($error['sysmsg']) ?$error['sysmsg'] :[];

        if(!empty($error['message'])){
            $message .= '<p>'.htmlspecialchars($error['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }

        if(!empty($sysmsg['remote_url'])){
            $message .= '<p><b>URL checked:</b> '
                .htmlspecialchars($sysmsg['remote_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p>';
        }

        if(!empty($sysmsg['code'])){
            $message .= '<p><b>Error code:</b> '
                .htmlspecialchars($sysmsg['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p>';
        }

        return $message;
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

    private static function resolveRemoteDbUrl(string $recToken, string $dbParam=null): array
    {
        // Load just enough to call DbRegis. This request will end with a redirect.
        //$autoload = rtrim($serverRoot, '/\\') . "/{$version}/autoload.php";
        //$dbregis  = rtrim($serverRoot, '/\\') . "/{$version}/hserv/utilities/DbRegis.php";
        
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
            if (isset($dbParam) && is_numeric($dbParam) && (string)(int)$dbParam === (string)$dbParam) {
                $database_id = (int)$dbParam;
            }
        }
        
        if (!($database_id > 0)) {
            return array(null, $recid, null);                
        }
        
        $dbregis = dirname(__FILE__).'/../utilities/DbRegis.php';
        /*
        $autoload = dirname(__FILE__).'/../../autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }*/

        if (is_file($dbregis)) {
            require_once $dbregis;
        }
        if (!class_exists('hserv\\utilities\\DbRegis')) {
            return array(null, $recid, null);                
        }
        $error = null;
        try {
            $url = \hserv\utilities\DbRegis::registrationGet(['dbID' => $database_id]);
            if(!$url){
                $error = \hserv\utilities\DbRegis::getLastError();
            }
        } catch (\Throwable $e) {
            $url = null;
            $error = [
                'status' => defined('HEURIST_SYSTEM_FATAL') ? HEURIST_SYSTEM_FATAL : 'error',
                'message' => $e->getMessage(),
                'sysmsg' => array(
                    'code' => 'REMOTE_RESOLUTION_EXCEPTION',
                    'stage' => 'remote_db_resolution')
            ];
        }
        return array($url?:null, $recid, $error);
    }
    
    private static function isPositiveInt($val){
        return isset($val) && (is_int($val) || ctype_digit((string)$val)) && (int)$val > 0;
    }
    
}
