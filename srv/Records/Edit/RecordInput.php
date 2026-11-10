<?php
/**
* RecordInput.php - A record in the write format (input = output of GET /records)
*
* Parses a request body: a bare record object or {records:[record]}, with
* rec_* headers and details keyed by detail type id, each an array of values.
* Values stay as given (compact or resolved object form); RecordValidator
* reads them by field type. Legacy recordSave input (headers without "rec_",
* details keyed "t:NNN") is refused with a message.
* PATCH options (plan 32): "detailsAdd" (values appended to multi-value fields),
* "detailsMatch" (field => value condition of the query format: only the matching
* values of a field in "details" are replaced or deleted) and "addIfNone" (field
* ids of "details" whose new values are added when nothing matches).
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

    /** Body keys of the details and the PATCH options (plan 32). */
    private const DETAIL_KEYS = array('details', 'detailsAdd', 'detailsMatch', 'addIfNone');

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
    /** @var array<int,array<int,mixed>> dty => values appended (PATCH) */
    private array $detailsAdd;
    /** @var array<int,string> dty => value condition (PATCH) */
    private array $detailsMatch;
    /** @var array<int,int> fields of details whose values are added when nothing matches (PATCH) */
    private array $addIfNone;

    /**
     * @param array<string,mixed> $headers Writable headers present in the body.
     * @param array<int,array<int,mixed>> $details dty => values.
     * @param bool $hasDetails False when the body has no "details" key.
     * @param array<int,array<int,mixed>> $detailsAdd dty => values appended (PATCH).
     * @param array<int,string> $detailsMatch dty => value condition (PATCH).
     * @param array<int,int> $addIfNone Field ids (PATCH).
     */
    public function __construct(array $headers, array $details, bool $hasDetails = true,
        array $detailsAdd = array(), array $detailsMatch = array(), array $addIfNone = array())
    {
        $this->headers = $headers;
        $this->details = $details;
        $this->hasDetails = $hasDetails;
        $this->detailsAdd = $detailsAdd;
        $this->detailsMatch = $detailsMatch;
        $this->addIfNone = $addIfNone;
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
            if(in_array($key, self::DETAIL_KEYS, true)){ continue; }
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
        $details = $hasDetails ? self::parseDetails($body['details'], 'details') : array();
        $detailsAdd = array_key_exists('detailsAdd', $body) ? self::parseDetails($body['detailsAdd'], 'detailsAdd') : array();
        $detailsMatch = array();
        if(array_key_exists('detailsMatch', $body) && $body['detailsMatch'] !== null){
            if(!is_array($body['detailsMatch'])){
                throw RecordEditException::invalid('"detailsMatch" must be an object keyed by field id');
            }
            foreach($body['detailsMatch'] as $key => $condition){
                if(!is_scalar($condition)){
                    throw RecordEditException::invalid('The value condition of field '.$key.' must be a string');
                }
                $detailsMatch[self::fieldKey((string)$key)] = (string)$condition;
            }
        }
        $addIfNone = array();
        if(array_key_exists('addIfNone', $body) && $body['addIfNone'] !== null){
            $list = is_array($body['addIfNone']) ? $body['addIfNone'] : explode(',', (string)$body['addIfNone']);
            foreach($list as $key){
                $addIfNone[] = self::fieldKey(trim((string)$key));
            }
        }
        return new self($headers, $details, $hasDetails, $detailsAdd, $detailsMatch, array_values(array_unique($addIfNone)));
    }

    /**
     * dty => values of a "details" or "detailsAdd" object.
     *
     * @return array<int,array<int,mixed>>
     * @throws RecordEditException 400
     */
    private static function parseDetails($object, string $name): array
    {
        if(!is_array($object)){
            throw RecordEditException::invalid('"'.$name.'" must be an object keyed by field id');
        }
        $details = array();
        foreach($object as $key => $values){
            if($values === null){
                $values = array();
            }elseif(!is_array($values) || array_keys($values) !== range(0, count($values) - 1)){
                $values = array($values); // one value given without array
            }
            $details[self::fieldKey((string)$key)] = $values;
        }
        return $details;
    }

    /** @throws RecordEditException 400 for a key that is not a field id */
    private static function fieldKey(string $key): int
    {
        if(preg_match('/^t:\d+$/', $key)){
            throw RecordEditException::invalid('Detail key "'.$key.'" is the legacy format; use the field id "'.substr($key, 2).'"');
        }
        if(!preg_match('/^\d+$/', $key) || intval($key) < 1){
            throw RecordEditException::invalid('Detail key "'.$key.'" is not a field id');
        }
        return intval($key);
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

    /** @return array<int,array<int,mixed>> dty => values appended (PATCH, plan 32) */
    public function detailsAdd(): array
    {
        return $this->detailsAdd;
    }

    /** @return array<int,string> dty => value condition of the query format (PATCH, plan 32) */
    public function detailsMatch(): array
    {
        return $this->detailsMatch;
    }

    /** @return array<int,int> Fields of details whose values are added when nothing matches (PATCH, plan 32). */
    public function addIfNone(): array
    {
        return $this->addIfNone;
    }

    /** True when the body uses a PATCH option (detailsAdd, detailsMatch, addIfNone). */
    public function hasPatchOptions(): bool
    {
        return !empty($this->detailsAdd) || !empty($this->detailsMatch) || !empty($this->addIfNone);
    }

    /** Copy with other details (PATCH merge, stored values of forbidden fields). */
    public function withDetails(array $details): self
    {
        return new self($this->headers, $details, true, $this->detailsAdd, $this->detailsMatch, $this->addIfNone);
    }
}
