<?php
/**
* GraphRequest.php - Normalized graph document request
*
* Carries the top-level query, initial link selection, one optional interactive
* expansion rule and the effective node/edge/depth budget from the graph
* controller to the graph service. Server ceilings are the final authority: a
* client may request a smaller budget but never a larger one.
*
* @project     Heurist academic knowledge management system
* @package     Records\Graph
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Records\Graph;

/** Immutable normalized request for one graph document or graph fragment. */
final class GraphRequest
{
    /** Hard server ceilings - the last line of defence against dense graphs. */
    public const SERVER_MAX_NODES = 5000;
    public const SERVER_MAX_EDGES = 10000;
    public const SERVER_MAX_DEPTH = 10;

    /** Normalized top-level query group. */
    public array $query;
    /** Caller's original query representation, preserved for the response. */
    public $displayQuery;
    public int $limit;
    public int $offset;
    /**
     * Initial link selection.
     * - null: no edge discovery
     * - 'all': every internal edge, bounded by the effective edge budget
     * - string[]: explicit compact link specs such as 10:lt240:48
     * @var string[]|string|null
     */
    public $links;
    /** One optional interactive expansion rule (executed from Stage 3). */
    public $rule;
    public int $maxNodes;
    public int $maxEdges;
    public int $maxDepth;

    /** Initialise and constrain every externally supplied option. */
    public function __construct(array $query, array $options = array())
    {
        $this->query = $query;
        $this->displayQuery = $options['displayQuery'] ?? $query;
        $this->offset = max(0, intval($options['offset'] ?? 0));
        $this->links = self::normalizeLinks($options['links'] ?? null);
        $this->rule = self::normalizeRule($options['rule'] ?? null, $options['rules'] ?? null);

        $limits = self::structuredLimits($options);
        $this->maxNodes = self::clamp($limits['maxNodes'] ?? null, self::SERVER_MAX_NODES);
        $this->maxEdges = self::clamp($limits['maxEdges'] ?? null, self::SERVER_MAX_EDGES);
        $this->maxDepth = self::clamp($limits['maxDepth'] ?? null, self::SERVER_MAX_DEPTH);

        // The seed page can never exceed the effective node budget.
        $requested = min(self::SERVER_MAX_NODES, max(1, intval($options['limit'] ?? 1000)));
        $this->limit = min($requested, $this->maxNodes);
    }

    /** Accept null, the 'all' keyword, a comma list, or an array of specs. */
    private static function normalizeLinks($value)
    {
        if($value === null || $value === '' || $value === array()){ return null; }
        if(is_string($value)){
            if(strcasecmp(trim($value), 'all') === 0){ return 'all'; }
            $value = preg_split('/\s*,\s*/', trim($value));
        }
        if(!is_array($value)){
            throw new \InvalidArgumentException('links must be "all", a comma list, or an array of link specs');
        }
        $specs = array();
        foreach($value as $spec){
            if(!is_string($spec) || trim($spec) === ''){
                throw new \InvalidArgumentException('Each link spec must be a non-empty string');
            }
            if(strcasecmp(trim($spec), 'all') === 0){ return 'all'; }
            $specs[] = trim($spec);
        }
        return empty($specs) ? null : array_values(array_unique($specs));
    }

    /** Exactly one rule per graph request; reject a multi-rule array. */
    private static function normalizeRule($rule, $rules)
    {
        $candidate = $rule;
        if($candidate === null || $candidate === '' || $candidate === array()){
            $candidate = $rules;
        }
        if($candidate === null || $candidate === '' || $candidate === array()){ return null; }
        if(is_string($candidate)){ return $candidate; }
        if(!is_array($candidate)){
            throw new \InvalidArgumentException('rule must be a JSON rule object or compact path');
        }
        if(array_key_exists('query', $candidate)){ return $candidate; }
        if(array_keys($candidate) === range(0, count($candidate) - 1)){
            if(count($candidate) > 1){
                throw new \InvalidArgumentException('Only one expansion rule may be executed per graph request');
            }
            return $candidate[0] ?? null;
        }
        return $candidate;
    }

    /** Read maxNodes/maxEdges/maxDepth from a nested object or flat options. */
    private static function structuredLimits(array $options): array
    {
        $limits = $options['limits'] ?? null;
        if(is_string($limits) && trim($limits) !== ''){
            $decoded = json_decode(trim($limits), true);
            $limits = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        if(!is_array($limits)){ $limits = array(); }
        foreach(array('maxNodes', 'maxEdges', 'maxDepth') as $key){
            if(!array_key_exists($key, $limits) && isset($options[$key]) && $options[$key] !== ''){
                $limits[$key] = $options[$key];
            }
        }
        return $limits;
    }

    /** A missing client value yields the ceiling; any value is capped by it. */
    private static function clamp($value, int $ceiling): int
    {
        if($value === null || $value === ''){ return $ceiling; }
        return min($ceiling, max(1, intval($value)));
    }
}
