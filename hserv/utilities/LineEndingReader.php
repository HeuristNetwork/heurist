<?php
/**
* LineEndingReader.php - Reads LF, CRLF and legacy CR lines without changing source byte offsets
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/
namespace hserv\utilities;

/** Reads LF, CRLF and legacy CR lines without changing source byte offsets. */
final class LineEndingReader
{
    private $stream;
    private string $buffer = '';

    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /** Returns the next physical line including its original terminator, or false at EOF. */
    public function readLine()
    {
        $line = '';
        while(true){
            $length = strcspn($this->buffer, "\r\n");
            if($length < strlen($this->buffer)){
                if($this->buffer[$length] === "\r" && $length+1 === strlen($this->buffer)
                    && !feof($this->stream)){
                    $this->fill();
                    continue;
                }
                $end = $length+1;
                if(substr($this->buffer, $length, 2) === "\r\n"){ $end++; }
                $line .= substr($this->buffer, 0, $end);
                $this->buffer = substr($this->buffer, $end);
                return $line;
            }
            $line .= $this->buffer;
            $this->buffer = '';
            if(feof($this->stream)){ return $line === '' ? false : $line; }
            $this->fill();
        }
    }

    /** Offset of the next unread byte, excluding buffered read-ahead. */
    public function tell(): int
    {
        return ftell($this->stream)-strlen($this->buffer);
    }

    private function fill(): void
    {
        $chunk = fread($this->stream, 8192);
        if($chunk === false){ throw new \RuntimeException('Unable to read input stream'); }
        $this->buffer .= $chunk;
    }

    /** Enable native CSV parsing of CR-only files without the deprecated INI setting. */
    public static function normalizeCsvStream($stream): void
    {
        $name = 'heurist.csv-line-endings';
        if(!in_array($name, stream_get_filters(), true)){
            stream_filter_register($name, CsvLineEndingFilter::class);
        }
        if(stream_filter_append($stream, $name, STREAM_FILTER_READ) === false){
            throw new \RuntimeException('Unable to initialize CSV line ending reader');
        }
    }
}

/** Converts bare CR to LF, retaining CRLF and carrying CR across stream buckets. */
final class CsvLineEndingFilter extends \php_user_filter
{
    private string $pending = '';

    public function filter($in, $out, &$consumed, $closing): int
    {
        while($bucket = stream_bucket_make_writeable($in)){
            $consumed += $bucket->datalen;
            $data = $this->pending.$bucket->data;
            $this->pending = '';
            if(substr($data, -1) === "\r"){
                $this->pending = "\r";
                $data = substr($data, 0, -1);
            }
            $bucket->data = preg_replace('/\r(?!\n)/', "\n", $data);
            stream_bucket_append($out, $bucket);
        }
        if($closing && $this->pending !== ''){
            stream_bucket_append($out, stream_bucket_new($this->stream, "\n"));
            $this->pending = '';
        }
        return PSFS_PASS_ON;
    }
}
