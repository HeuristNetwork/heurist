<?php
declare(strict_types=1);

/**
* checkMembership.php - Checks to see if the user or database (eventually group) is a member of the association
*
* @fileOverview This is an independent function which compares a user email address and/or host+database name against a text file
*               list of members (and databses owned by members) and returns whether the person is a member of the Heurist 
*               Network association, either individually or because the database is authorised as belonging to a group which is a member.
*               It also logs non-member requests except in specific situations (notably program startup or independent enquiry).
*               This function is unique to the HeuristRef.net server which contains the membership list updated daily.
*
* - Standalone service (GET): ?email=...&host=...&db=...
* - Or include and call checkHeuristNetworkMembership($email, $host, $db, $context)
* 
* To set this protection for particular function, the developer has to the following:
* 
* 1) Define ASSOC_MEMBERSHIP_REQUIRED constant for standalone page (ie for script that uses initPage.php or initPageMin.php)
* define('ASSOC_MEMBERSHIP_REQUIRED', 'context');
* 
* 2) For dynamic UI. IE for actions that called via main menu or dashboard  (eventually via ActionHandles.js)
* need to define  "is_association_member": "1" for action entry in  actions.json - list of all heurist actions
* Example:
*   {
*       "id": "menu-magic-tool",
*       "text": "Magic tool",
*       "data": {
*         "is_association_member": "1",
*         "icon": "ui-icon-lightning-explore"
*       }
*    }
* 
* @project     Heurist academic knowledge management system
* @package     Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>  specification
* @author      Artem Osmakov <osmakov@gmail.com> corrections
* @since       7.0
*/
const HN_MEMBERS_FILE = '/var/www/html/HEURIST/association_members.txt';
const HN_LOG_FILE     = '/var/www/html/HEURIST/HEURIST_FILESTORE/_HEURISTNETWORK_membership_checkpoint.log';
const HN_TIMEZONE     = 'Australia/Sydney';


// map hostnames/URLs to the "server name" stored in the CSV (3rd column of DATABASE rows).
const HOSTNAME_TO_SERVERNAME = [
    'huma-num.fr'   => 'Fr Huma-Num',
    'heurist.huma-num.fr'   => 'Fr Huma-Num',
    'heurist2025.huma-num.fr'=> 'Fr Huma-Num',
];

/* ------------------------ Standalone handler ------------------------ */
if (php_sapi_name() !== 'cli') {
    if(@$_SERVER['REQUEST_METHOD']=='POST'){
        $req_params = filter_input_array(INPUT_POST);
    }else{
        $req_params = filter_input_array(INPUT_GET);
    }

    $email = isset($req_params['email']) ? trim((string)$req_params['email']) : '';
    $lastName = isset($req_params['lastName']) ? trim((string)$req_params['lastName']) : '';
    $firstName = isset($req_params['firstName']) ? trim((string)$req_params['firstName']) : '';
    $email = isset($req_params['email']) ? trim((string)$req_params['email']) : '';
    $host  = isset($req_params['host'])  ? trim((string)$req_params['host'])  : '';
    $db    = isset($req_params['db'])    ? trim((string)$req_params['db'])    : '';
    $ctx   = isset($req_params['ctx'])   ? trim((string)$req_params['ctx'])   : '';

    if ($email !== '' || ($firstName!=='' && $lastName!=='') || ($host !== '' && $db !== '')) {
        header('Content-Type: text/plain; charset=UTF-8');
        if(isset($req_params['log']) && trim((string)$req_params['log'])==='1'){
            checkMembershipLogNonmember($ctx, $email, $host, $db);
            echo "ok";
        }else{
            echo checkHeuristNetworkMembership($email, $host, $db, $ctx, $firstName, $lastName);    
        }
        exit;
    }
}

/* ------------------------ Public API ------------------------ */

function getMainServerUrl(): ?string
{
    $isMainServer = (@$_SERVER["SERVER_NAME"]=='heuristref.net');
    
    if($isMainServer){
        return null;    
    }
    //hardcoded
    $base = 'https://heuristref.net/h7-alpha/';
    return $base;
}

/**
 * Returns:
 *   'individual'           – if email is an INDIVIDUAL member
 *   'database'             – if host+db is a DATABASE member
 *   'individual|database'  – if both match
 *   'nonmember'            – otherwise (also logs a line unless context indicates initial sign-in)
 */
function checkHeuristNetworkMembership(string $email, string $host = '', ?string $database = null, ?string $context = '', string $firstName = '', string $lastName = ''): string
{
    $base = getMainServerUrl();
    if( $base==null ){ 
        return checkMembershipInFile($email, $host, $database, $context, $firstName, $lastName);
    }

    $url = $base . 'admin/utilities/checkMembership.php'
        . '?email=' . rawurlencode($email)
        . '&host='  . rawurlencode($host)
        . '&db='    . rawurlencode((string)$database)
        . '&ctx='   . rawurlencode($context??'');

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
        $parts = array_reverse($parts);
        return implode(' ', array_splice($parts,0,2));
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

function checkMembershipInFile(string $email, string $host = '', ?string $database = null, string $context = '', string $firstName = '', string $lastName = ''): string
{
    $hits = [];
    $toCheck = 0;
    
    $email = strtolower(trim($email));
    $firstName = strtolower(trim($firstName));
    $lastName = strtolower(trim($lastName));
    if ($email !== '' || ($firstName!=='' && $lastName!=='')) $toCheck++;
  
    $serverName = normalizeServerName($host);   // compare as case-insensitive server NAME
    $dbName     = normalizeDbName($database);
  
    //check taht either email or host+database are defined
    if ($serverName !== '' && $dbName !== '') $toCheck++;
    
    if($toCheck==0){
        //neither email nor db defined
        return 'nonmember';
    }
      
    if (!is_file(HN_MEMBERS_FILE) || !is_readable(HN_MEMBERS_FILE)) {
        //checkMembershipLogNonmember($context, $email, $serverName, $dbName);
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

        if ($type === 'INDIVIDUAL'){
            
            if($email !== '') {
                // INDIVIDUAL,email,"last name","firstname"
                $email2 = strtolower(trim($parts[1]));
                if ($email2 == $email) {
                    $hits['individual'] = true;
                }
            }
            if (!isset($hits['individual']) && $firstName !== '' && $lastName !== '') {
                // INDIVIDUAL,email,"last name","firstname"
                $firstName2 = strtolower(trim($parts[3]));
                $lastName2 = strtolower(trim($parts[2]));
                if ($firstName2 == $firstName && $lastName2==$lastName) {
                    $hits['individual'] = true;
                }
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
        checkMembershipLogNonmember($context, $dbName, $serverName, $email);
    } 
    
    return $result;
}


// --- log helper ---
function checkMembershipLogNonmember(string $context, string $email, string $host='', string $database=''): void 
{
    if (!$context || in_array($context, array('Initial sign-in', ''), true)) { return; }
    
    $base = getMainServerUrl();
    if( $base!=null ){ 

        $url = $base . 'admin/utilities/checkMembership.php'
            . '?email=' . rawurlencode($email)
            . '&host='  . rawurlencode($host)
            . '&db='    . rawurlencode((string)$database)
            . '&ctx='   . rawurlencode($context)
            . '&log=1';

        httpGet($url);
        return;
    }
    

    // Time (avoid Throwable / typed exceptions for max compatibility)
    try {
        $tz  = new DateTimeZone(defined('HN_TIMEZONE') ? HN_TIMEZONE : 'UTC');
        $now = (new DateTime('now', $tz))->format(DateTime::ATOM);
    } catch (Exception $e) { // PHP 7.4-safe
        $now = date('c');
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

    $entry = json_encode(
        array(
            'ts'       => $now,
            'database' => $database,
            'name'     => $host,
            'email'    => $email,
            'context'  => $context,
            'ip'       => $ip,
            'ua'       => $ua,
        ),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($entry !== false) {
        @file_put_contents(HN_LOG_FILE, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }    
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