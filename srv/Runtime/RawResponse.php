<?php
/**
* RawResponse.php - A response that is not JSON: an HTML page or a file
*
* An action of a record-based entity returns array('rawResponse' => new RawResponse(...)):
* the generic controllers stay the only place that writes to the client (plan 30).
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

/** Content, or a file to send, with its type and headers. */
final class RawResponse
{
    public string $contentType;
    public string $body;
    public string $path;
    /** @var array<string,string> */
    public array $headers;

    /**
     * @param string $contentType Content-Type.
     * @param string $body The content (unused with `$path`).
     * @param string $path File to send.
     * @param array<string,string> $headers Other headers.
     */
    public function __construct(string $contentType, string $body = '', string $path = '', array $headers = array())
    {
        $this->contentType = $contentType;
        $this->body = $body;
        $this->path = $path;
        $this->headers = $headers;
    }

    /** Write the response. */
    public function send(int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: '.$this->contentType);
        header('X-Content-Type-Options: nosniff');
        foreach($this->headers as $name => $value){ header($name.': '.$value); }
        if($this->path === ''){
            print $this->body;
            return;
        }
        header('Content-Length: '.intval(filesize($this->path)));
        readfile($this->path);
    }
}
