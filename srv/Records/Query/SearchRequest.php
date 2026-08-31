<?php
/**
* SearchRequest.php - Normalized record search request
*
* Carries query, pagination, expansion and output options from the controller to
* the modern record search workflow.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Records\Query;

/** Immutable normalized top-level record search request. */
final class SearchRequest
{
    public array $query;
    public int $limit;
    public int $offset;
    public $rules;
    public $fields;
    public string $detail;
    public bool $resolveDetails;
    /** Request sort overriding a top-level query sort. */
    public $sort;
    /** Whether sort was explicitly supplied, including an empty sort. */
    public bool $sortProvided;
    /** Additional query predicate/group ANDed with the base query. */
    public $filter;

    /** Initialise and constrain all externally supplied request options. */
    public function __construct(array $query, array $options = array())
    {
        $this->query = $query;
        $this->limit = min(100000, max(1, intval($options['limit'] ?? 1000)));
        $this->offset = max(0, intval($options['offset'] ?? 0));
        $this->rules = $options['rules'] ?? null;
        $this->fields = $options['fields'] ?? null;
        $detail = strtolower(trim((string)($options['detail'] ?? 'records')));
        $this->detail = $detail === '' ? 'records' : $detail;
        $this->resolveDetails = filter_var($options['resolveDetails'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->sortProvided = array_key_exists('sort', $options);
        $this->sort = $this->sortProvided ? $options['sort'] : null;
        $this->filter = $options['filter'] ?? null;
    }
}
