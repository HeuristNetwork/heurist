<?php
/**
* SearchTypes.php - Data contracts for the modern record search engine
*
* Contains the small immutable request, result, and compiled-query value objects
* shared by QueryBuilder and the future RecordSearchEngine. These are data
* contracts, not service classes.
*
* @project     Heurist academic knowledge management system
* @package     Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace hserv\records\search;

/**
 * Normalized top-level record search request.
 */
final class SearchRequest
{
    /** @var array Normalized JSON query. */
    public $query;

    /** @var int */
    public $limit;

    /** @var int */
    public $offset;

    /** @var mixed JSON ruleset or compact expansion path. */
    public $rules;

    /** @var array|string|null Requested output fields for the later output stage. */
    public $fields;

    /** @var string Requested output detail level. */
    public $detail;

    /** @var bool Resolve type-specific detail metadata such as term labels. */
    public $resolveDetails;

    /** @var mixed|null Request sort overriding a top-level query sort. */
    public $sort;

    /** @var bool Whether sort was explicitly supplied, including an empty sort. */
    public $sortProvided;

    /** @var mixed|null Additional query predicate/group ANDed with the base query. */
    public $filter;

    /**
     * @param array $query Normalized JSON query.
     * @param array $options Request options.
     */
    public function __construct(array $query, array $options = array())
    {
        $this->query = $query;
        $this->limit = min(100000, max(1, intval($options['limit'] ?? 1000)));
        $this->offset = max(0, intval($options['offset'] ?? 0));
        $this->rules = $options['rules'] ?? null;
        $this->fields = $options['fields'] ?? null;
        $detail = strtolower(trim((string)($options['detail'] ?? 'records')));
        $this->detail = $detail === '' ? 'records' : $detail;
        $this->resolveDetails = filter_var(
            $options['resolveDetails'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $this->sortProvided = array_key_exists('sort', $options);
        $this->sort = $this->sortProvided ? $options['sort'] : null;
        $this->filter = $options['filter'] ?? null;
    }
}

/**
 * IDs-only result returned by the future RecordSearchEngine.
 */
final class SearchResult
{
    /** @var array<int> */
    public $ids;

    /** @var int */
    public $total;

    /** @var int */
    public $offset;

    /** @var int */
    public $limit;

    /** @var string|null Reserved for future materialized expansion results. */
    public $resultToken;

    /** @var ExpansionResult|null Explicit graph output for this top-level page. */
    public $graph;

    public function __construct(
        array $ids,
        int $total,
        int $offset,
        int $limit,
        ?string $resultToken = null,
        ?ExpansionResult $graph = null
    ) {
        $this->ids = array_values(array_map('intval', $ids));
        $this->total = $total;
        $this->offset = max(0, $offset);
        $this->limit = max(1, $limit);
        $this->resultToken = $resultToken;
        $this->graph = $graph;
    }

    /** Return the stable array representation used at controller boundaries. */
    public function toArray(): array
    {
        $result = array(
            'ids' => $this->ids,
            'total' => $this->total,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'resultToken' => $this->resultToken
        );
        if($this->graph !== null){ $result['graph'] = $this->graph->toArray(); }
        return $result;
    }
}

/**
 * Parameterized SQL produced by QueryBuilder.
 */
final class CompiledQuery
{
    /** @var string */
    public $sql;

    /** @var string mysqli bind_param type string. */
    public $types;

    /** @var array Ordered values matching SQL placeholders. */
    public $values;

    /** @var array Normalized source query for diagnostics. */
    public $query;

    public function __construct(string $sql, string $types, array $values, array $query)
    {
        $this->sql = $sql;
        $this->types = $types;
        $this->values = array_values($values);
        $this->query = $query;
    }

    public function toArray(): array
    {
        return array(
            'sql' => $this->sql,
            'types' => $this->types,
            'values' => $this->values,
            'query' => $this->query
        );
    }
}

/** Invalid syntax or unsupported values in a Heurist query. */
class QueryValidationException extends \InvalidArgumentException
{
}

/** Valid Heurist syntax that belongs to a later implementation phase. */
class UnsupportedQueryException extends \RuntimeException
{
}

/** Database preparation or execution failure in the modern search engine. */
class SearchExecutionException extends \RuntimeException
{
}
