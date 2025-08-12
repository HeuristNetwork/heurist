<?php
declare(strict_types=1);

/**
 * Heurist Network membership checker.
 * - Standalone service (GET): ?email=...&host=...&db=...
 * - Or include and call checkHeuristNetworkMembership($email, $host, $db, $context)
 */
 
const HN_MEMBERS_FILE = '/var/www/html/HEURIST/association_members.txt';
const HN_LOG_FILE     = '/var/www/html/HEURIST/HEURIST_FILESTORE/_HEURISTNETWORK_membership_checkpoint.log';

//const HN_MEMBERS_FILE = 'c:/xampp/htdocs/association_members.txt';
//const HN_LOG_FILE     = 'c:/xampp/htdocs/HEURIST/HEURIST_FILESTORE/_HEURISTNETWORK_membership_checkpoint.log';
const HN_TIMEZONE     = 'Australia/Sydney';


// map hostnames/URLs to the "server name" stored in the CSV (3rd column of DATABASE rows).
const HOSTNAME_TO_SERVERNAME = [
    'heurist.huma-num.fr'   => 'Fr Huma-Num',
    'heurist2025.huma-num.fr'=> 'Fr Huma-Num',
];

/* ------------------------ Standalone handler ------------------------ */
if (php_sapi_name() !== 'cli') {
    //$req_params = filter_input_array(INPUT_GET);
    $email = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
    $host  = isset($_GET['host'])  ? trim((string)$_GET['host'])  : '';
    $db    = isset($_GET['db'])    ? trim((string)$_GET['db'])    : '';
    $ctx   = isset($_GET['ctx'])   ? trim((string)$_GET['ctx'])   : '';

    
//error_log('ENTER checkMembership '.$email);
    
    if ($email !== '' || ($host !== '' && $db !== '')) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo checkHeuristNetworkMembership($email, $host, $db, $ctx);
        exit;
    }
}

/* ------------------------ Public API ------------------------ */

/**
 * Returns:
 *   'individual'           – if email is an INDIVIDUAL member
 *   'database'             – if host+db is a DATABASE member
 *   'individual|database'  – if both match
 *   'nonmember'            – otherwise (also logs a line unless context indicates initial sign-in)
 */
function checkHeuristNetworkMembership(string $email, string $host = '', ?string $database = null, string $context = ''): string
{
    if(defined('HEURIST_INDEX_BASE_URL') && defined('HEURIST_SERVER_URL')){
        $isMainServer = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===0);    
    }else{
        $isMainServer = true;
    }
    
//error_log('checkHeuristNetworkMembership ='.$isMainServer.'  '.$email);
       
        
    if( $isMainServer ){ 
        return checkMembershipInFile($email, $host, $database, $context);
    }
    
    // Remote call fallback (works when included from other servers)
    if(defined('HEURIST_BASE_URL') && defined('HEURIST_MAIN_SERVER')){
        $isAlpha = (preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1) ? true :false;
        $base = ($isAlpha
            ? HEURIST_MAIN_SERVER . '/h7-alpha/'
            : HEURIST_INDEX_BASE_URL);
    }else{
        //hardcoded
        $base = 'https://heuristref.net/h7-alpha/';
    }

    $url = $base . 'admin/setup/dbproperties/checkMembership.php'
        . '?email=' . rawurlencode($email)
        . '&host='  . rawurlencode($host)
        . '&db='    . rawurlencode((string)$database)
        . '&ctx='   . rawurlencode($context);

    $resp = httpGet($url);
    return $resp !== '' ? $resp : 'nonmember';
}

function normalizeServerName(string $input): string
{
    if ($input === '') return '';

    // 1) If input looks like a URL/host, map it to a server-name when possible
    $raw = strtolower(trim($input));
    $raw = preg_replace('~^https?://~', '', $raw); // strip scheme
    $raw = preg_replace('~/.*$~', '', $raw);       // strip path

    if (isset(HOSTNAME_TO_SERVERNAME[$raw])) {
        return strtolower(HOSTNAME_TO_SERVERNAME[$raw]);
    }

    // Otherwise: convert host into "com domain" style
    $parts = explode('.', $raw);
    if (count($parts) > 1) {
        // Drop the first label (subdomain), keep the rest, reverse order
        array_shift($parts);
        $parts = array_reverse($parts);
        return implode(' ', $parts);
    }

    // Fallback: just return raw
    return $raw;
}

function normalizeDbName(?string $database): string
{
    if (!$database) return '';
    $db = strtolower(trim($database));
    return (strpos($db, 'hdb_') === 0) ? substr($db, 4) : $db;
}

function checkMembershipInFile(string $email, string $host = '', ?string $database = null, string $context = ''): string
{
    $hits = [];
    $toCheck = 0;
    
//error_log('checkMembershipInFile '.$email);
    //@todo verify email    
    $email = strtolower(trim($email));
    if ($email !== '') $toCheck++;
  
    $serverName = normalizeServerName($host);   // compare as case-insensitive server NAME
    $dbName     = normalizeDbName($database);
  
    //check taht either email or host+database are defined
    if ($serverName !== '' && $dbName !== '') $toCheck++;
    
    if($toCheck==0){
        //neither email nor db defined
        return 'nonmember';
    }
      
    if (!is_file(HN_MEMBERS_FILE) || !is_readable(HN_MEMBERS_FILE)) {
        checkMembershipLogNonmember('nonmember', $dbName, $serverName, $email, $context);
        return 'nonmember';
    }      

    $lines = file(HN_MEMBERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $firstLine = true;

    foreach ($lines as $ln) {
        $line = trim($ln);
            // Skip blank and commented lines (allow leading whitespace)
        if ($line === '' || preg_match('/^\s*#/', $line)) { continue; }

        // Strip UTF-8 BOM on the very first line if present
        if ($firstLine) {
            $line = ltrim($line, "\xEF\xBB\xBF");
            $firstLine = false;
        }

        // Robust CSV parsing with quotes and escapes
        $parts = str_getcsv($line, ',', '"', '\\');
        if (!$parts || count($parts) < 4) { continue; }
        
        $type = strtoupper(trim($parts[0]));

        if ($type === 'INDIVIDUAL' && $email !== '') {
            // INDIVIDUAL,email,"last name","firstname"
            $email2 = strtolower(trim($parts[1]));
            if ($email2 == $email) {
                $hits['individual'] = true;    
            }
        } elseif ($type === 'DATABASE' && $serverName !== '' && $dbName !== '') {
            // DATABASE, contactEmail, ServerName, DbName
            $server = strtolower(trim($parts[2]));
            $db     = strtolower(trim($parts[3]));
            if ($server === $serverName && $db === $dbName) {
                $hits['database'] = true;
            }
        }
        
        if(count($hits)==$toCheck){
            break;
        }
    }

    $result = empty($hits) ? 'nonmember' : implode('|', array_keys($hits));

    if ($result === 'nonmember') {
        checkMembershipLogNonmember($result, $dbName, $serverName, $email, $context);
    } 
    
    return $result;
}


// --- log helper ---
function checkMembershipLogNonmember(string $result, string $database, string $host, string $email, string $context): void {
   /*
    if ($result !== 'nonmember') return;
    if (in_array($context, ['Initial sign-in', ''], true)) return;  // do not log sign-in message or a call with no context

    try {
        $tz = new DateTimeZone(HN_TIMEZONE);
        $now = (new DateTime('now', $tz))->format(DateTime::ATOM);
    } catch (Throwable) {
        $now = date('c');
    }

    $entry = json_encode([
        'ts'      => $now,
        'result'  => $result,
        'database'=> $database,
        'name'    => $host,
        'email'   => $email,
        'context' => $context,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($entry !== false) {
        @file_put_contents(HN_LOG_FILE, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
   */
}

function httpGet(string $url, int $timeout = 5): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'HN-Membership/1.0',
        ]);
        $out = curl_exec($ch);
        curl_close($ch);
        return is_string($out) ? $out : '';
    }
    // fallback
    return (string)@file_get_contents($url);
}