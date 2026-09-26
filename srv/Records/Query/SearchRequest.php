<?php
/**
* SearchRequest.php - Normalized record search request
*
* Carries query, pagination and output options from the controller to the
* modern record search workflow.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Records\Query;

/** Immutable normalized top-level record search request. */
final class SearchRequest
{
    public array $query;
    public int $limit;
    public int $offset;
    public $fields;
    public string $detail;
    public bool $resolveDetails;
    /** Request sort overriding a top-level query sort. */
    public $sort;
    /** Whether sort was explicitly supplied, including an empty sort. */
    public bool $sortProvided;
    /** Additional query predicate/group ANDed with the base query. */
    public $filter;
    /** detail=values: the field, a substring filter and the order (count|value). */
    public $valueField;
    public string $valueText;
    public string $valueSort;

    /** Initialise and constrain all externally supplied request options. */
    public function __construct(array $query, array $options = array())
    {
        $this->query = $query;
        $this->limit = min(100000, max(1, intval($options['limit'] ?? 1000)));
        $this->offset = max(0, intval($options['offset'] ?? 0));
        $this->fields = $options['fields'] ?? null;
        $detail = strtolower(trim((string)($options['detail'] ?? 'records')));
        $this->detail = $detail === '' ? 'records' : $detail;
        $this->resolveDetails = filter_var($options['resolveDetails'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->sortProvided = array_key_exists('sort', $options);
        $this->sort = $this->sortProvided ? $options['sort'] : null;
        $this->filter = $options['filter'] ?? null;
        $this->valueField = $options['field'] ?? null;
        $this->valueText = trim((string)($options['text'] ?? ''));
        $this->valueSort = strtolower(trim((string)($options['valueSort'] ?? 'count'))) === 'value' ? 'value' : 'count';
        if($this->detail === 'values'){
            $this->limit = min(FieldValueCounter::MAX_LIMIT, $this->limit);
        }
    }
}
