<?php
/**
* ApiResponse.php - Standard JSON API response writer
*
* Emits successful results and consistent HTTP error envelopes for modern
* read-only controllers without depending on legacy System error handling.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

/** Writes JSON at the outermost HTTP boundary. */
final class ApiResponse
{
    /** Emit one successful JSON value. */
    public function send(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        print json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Emit the standard modern API error envelope. */
    public function sendError(int $status, string $code, string $message): void
    {
        $this->send(array('status'=>$status, 'error'=>$code, 'message'=>$message), $status);
    }
}
