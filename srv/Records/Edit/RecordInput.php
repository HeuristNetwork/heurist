<?php
/**
* RecordInput.php - A record in the write format (input = output of GET /records)
*
* Parses a request body: a bare record object or {records:[record]}, with
* rec_* headers and details keyed by detail type id, each an array of values.
* Values stay as given (compact or resolved object form); RecordValidator
* reads them by field type. Legacy recordSave input (headers without "rec_",
* details keyed "t:NNN") is refused with a message.
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

/** Headers and details of one record to write. */
final class RecordInput
{
    /** Headers that may be written. */
    public const WRITABLE_HEADERS = array(
        'rec_ID', 'rec_RecTypeID', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility',
        'rec_NonOwnerVisibilityGroups', 'rec_EditGroups', 'rec_URL', 'rec_ScratchPad', 'rec_Modified'
    );

    /** Headers of the GET output that are ignored on input. */
    public const READ_ONLY_HEADERS = array(
        'rec_Title', 'rec_Added', 'rec_AddedByUGrpID', 'rec_Hash', 'rec_FlagTemporary',
        'rec_OwnerName', 'rec_Bookmarked', 'rec_ThumbnailURL'
    );

    /** @var array<string,mixed> Writable headers present in the body. */
    private array $headers;
    /** @var array<int,array<int,mixed>> dty => values as given */
    private array $details;
    private bool $hasDetails;

    /**
     * @param array<string,mixed> $headers Writable headers present in the body.
     * @param array<int,array<int,mixed>> $details dty => values.
     * @param bool $hasDetails False when the body has no "details" key.
     */
    public function __construct(array $headers, array $details, bool $hasDetails = true)
    {
        $this->headers = $headers;
        $this->details = $details;
        $this->hasDetails = $hasDetails;
    }

    /**
     * Parse a request body.
     *
     * @param mixed $body Decoded JSON.
     * @throws RecordEditException 400 for a body that is not a record in the new format.
     */
    public static function fromBody($body): self
    {
        if(!is_array($body)){
            throw RecordEditException::invalid('The request body must be a JSON record object');
        }
        if(isset($body['records'])){
            if(!is_array($body['records']) || count($body['records']) !== 1){
                throw RecordEditException::invalid('"records" must contain exactly one record');
            }
            $body = array_values($body['records'])[0];
            if(!is_array($body)){
                throw RecordEditException::invalid('The record must be a JSON object');
            }
        }

        $headers = array();
        foreach($body as $key => $value){
            $key = (string)$key;
            if($key === 'details'){ continue; }
            if(in_array($key, self::WRITABLE_HEADERS, true)){
                $headers[$key] = $value;
            }elseif(in_array($key, self::READ_ONLY_HEADERS, true)){
                continue;
            }elseif(in_array($key, array('ID', 'RecTypeID', 'OwnerUGrpID', 'NonOwnerVisibility', 'URL', 'ScratchPad'), true)){
                throw RecordEditException::invalid('Header "'.$key.'" is the legacy format; use "rec_'.$key.'"');
            }else{
                throw RecordEditException::invalid('Unknown header "'.$key.'"');
            }
        }

        $hasDetails = array_key_exists('details', $body);
        $details = array();
        if($hasDetails){
            if(!is_array($body['details'])){
                throw RecordEditException::invalid('"details" must be an object keyed by field id');
            }
            foreach($body['details'] as $key => $values){
                $key = (string)$key;
                if(preg_match('/^t:\d+$/', $key)){
                    throw RecordEditException::invalid('Detail key "'.$key.'" is the legacy format; use the field id "'.substr($key, 2).'"');
                }
                if(!preg_match('/^\d+$/', $key) || intval($key) < 1){
                    throw RecordEditException::invalid('Detail key "'.$key.'" is not a field id');
                }
                if($values === null){
                    $values = array();
                }elseif(!is_array($values) || array_keys($values) !== range(0, count($values) - 1)){
                    $values = array($values); // one value given without array
                }
                $details[intval($key)] = $values;
            }
        }
        return new self($headers, $details, $hasDetails);
    }

    /** True when the body has this header. */
    public function has(string $header): bool
    {
        return array_key_exists($header, $this->headers);
    }

    /** Header value as given (null when missing). */
    public function header(string $header)
    {
        return $this->headers[$header] ?? null;
    }

    /** Integer header, or $default when missing or empty. */
    public function intHeader(string $header, int $default = 0): int
    {
        $value = $this->headers[$header] ?? null;
        return ($value === null || $value === '') ? $default : intval($value);
    }

    /**
     * Group id list header (array or comma separated string).
     *
     * @return array<int,int>
     */
    public function idsHeader(string $header): array
    {
        $value = $this->headers[$header] ?? array();
        if(is_string($value) || is_int($value)){
            $value = explode(',', (string)$value);
        }
        if(!is_array($value)){ return array(); }
        return array_values(array_unique(array_filter(array_map('intval', $value), static function($id){
            return $id > 0;
        })));
    }

    /** True when the body has a "details" object. */
    public function hasDetails(): bool
    {
        return $this->hasDetails;
    }

    /** @return array<int,array<int,mixed>> dty => values as given */
    public function details(): array
    {
        return $this->details;
    }

    /** Copy with other details (PATCH merge, stored values of forbidden fields). */
    public function withDetails(array $details): self
    {
        return new self($this->headers, $details, true);
    }
}
