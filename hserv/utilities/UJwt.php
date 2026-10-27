<?php
/**
* UJwt.php - JWT helpers
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\utilities;

/**
* Class UJwt
* 
* JWT helpers

*/
class UJwt {

    public const API_AUDIENCE = 'heurist-api';

    /** Validate API identity before applying a database-local numeric user ID. */
    public static function authenticateApiToken($system, string $token, string $secret, string $requiredScope): void {
        $payload = self::jwt_verify($token, $secret);
        $userID = is_array($payload) ? ($payload['sub'] ?? null) : null;
        if (!is_array($payload)
            || ($payload['db'] ?? null) !== $system->dbnameFull()
            || ($payload['aud'] ?? null) !== self::API_AUDIENCE
            || !isset($payload['exp']) || !is_int($payload['exp'])
            || !(is_int($userID) || (is_string($userID) && ctype_digit($userID)))
            || filter_var($userID, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
            throw new \DomainException('Invalid token', 401);
        }

        $user = \user_getById($system->getMysqli(), (int)$userID);
        if (!is_array($user) || ($user['ugr_Type'] ?? '') !== 'user'
            || !in_array($user['ugr_Enabled'] ?? null, ['y', 'y_no_add', 'y_no_delete', 'y_no_add_delete'], true)) {
            throw new \DomainException('Invalid token user', 401);
        }

        $scope = $payload['scope'] ?? null;
        if (!is_string($scope) || !in_array($requiredScope, explode(' ', $scope), true)) {
            throw new \DomainException('Insufficient scope', 403);
        }

        $user['api_token_scope'] = $scope;
        $user['ugr_ID'] = (int)$userID;
        $user['ugr_Groups'] = \user_getWorkgroups($system->getMysqli(), (int)$userID);
        $user['ugr_Permissions'] = [
            'disabled'=>false,
            'add'=>strpos($user['ugr_Enabled'], 'add') === false,
            'delete'=>strpos($user['ugr_Enabled'], 'del') === false
        ];
        $system->setCurrentUser($user);
    }

    private static function b64url_encode(string $d): string {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }
    
    private static function b64url_decode(string $d): string {
        $p = 4 - (strlen($d) % 4);
        if ($p < 4) $d .= str_repeat('=', $p);
        return base64_decode(strtr($d, '-_', '+/'));
    }
    
    public static function jwt_create(array $claims, string $secret): string {
        $header = ['alg'=>'HS256','typ'=>'JWT'];
        $h = self::b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        return "$h.$p.".self::b64url_encode($sig);
    }
    
    public static function jwt_verify(string $jwt, string $secret){ //}: array|false {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return false;
        [$h,$p,$s] = $parts;
        $sig = self::b64url_decode($s);
        $calc = hash_hmac('sha256', "$h.$p", $secret, true);
        if (!hash_equals($calc, $sig)) return false;
        $payload = json_decode(self::b64url_decode($p), true);
        if (!is_array($payload)) return false;
        $now = time();
        if (isset($payload['nbf']) && $payload['nbf'] > $now + 60) return false;
        if (isset($payload['iat']) && $payload['iat'] > $now + 60) return false;
        if (isset($payload['exp']) && $payload['exp'] < $now - 60) return false;
        return $payload;
    }
    
    public static function get_auth_header(): ?string {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        return $auth ? trim($auth) : null;
    }
    
    public static function json_out(int $code, array $body, array $headers = []): void {
        http_response_code($code);
        header('Content-Type: application/json');
        foreach ($headers as $k=>$v) header("$k: $v");
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
        exit;
    }


}
?>
