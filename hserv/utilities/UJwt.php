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
    
    public static function jwt_verify(string $jwt, string $secret): array|false {
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
