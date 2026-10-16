<?php
/**
* RelationTermResolver.php - Relationship-term lookups for SQL compilation
*
* Resolves, while a query is compiled, what the chunked RecordSearchService
* resolves while it executes: descendant relationship terms, inverse terms
* (a relationship seen from its target) and relation-marker constraints.
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Query\Compiler;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Query\QueryValidationException;

/** Term closure, inverse terms and relation-marker constraints, cached per instance. */
final class RelationTermResolver
{
    private DefinitionLookup $definitions;
    /** @var array<int,array{types:?array,recordTypes:array}> */
    private $markers = array();

    public function __construct(DatabaseInterface $database)
    {
        $this->definitions = DefinitionLookup::shared($database);
    }

    /** Terms plus every descendant (DefinitionLookup::termDescendants). */
    public function expand(array $termIds): array
    {
        return $this->definitions->termDescendants($termIds);
    }

    /**
     * The terms a relationship reads as from its target, plus descendants. A term's
     * reverse is its trm_InverseTermID or any term naming it as inverse; a term with
     * no inverse is undirected and is its own reverse.
     */
    public function inverse(array $termIds): array
    {
        $inverse = $this->definitions->termInverse($termIds);
        return empty($inverse) ? array() : $this->expand($inverse);
    }

    /**
     * A relationship (relmarker) field: its vocabulary with child terms (`types`, null = any),
     * the record types that have the field (`ownerTypes`) and its target record types
     * (`recordTypes`); an empty type list means any record type.
     */
    public function marker(int $fieldId): array
    {
        if(isset($this->markers[$fieldId])){ return $this->markers[$fieldId]; }
        $field = $this->definitions->field($fieldId);
        if($field === null){ throw new QueryValidationException('Unknown relationship field ID: '.$fieldId); }
        if($field['type'] !== 'relmarker'){
            throw new QueryValidationException('Field '.$fieldId.' is not a relationship field');
        }
        $this->markers[$fieldId] = array(
            'types' => empty($field['vocabulary']) ? null : $this->expand($field['vocabulary']),
            'ownerTypes' => $this->definitions->fieldOwnerTypes($fieldId),
            'recordTypes' => $field['targetTypes']
        );
        return $this->markers[$fieldId];
    }

    /**
     * The edge reads ("legs") of a relationship predicate, one meaning for search, graph
     * steps and expansion rules (docs/development/09 ... §11). Outer = the record the
     * predicate is on, linked = the records in its value.
     *
     * - `rt[:N]`: outer is the stored source; `rf[:N]`: outer is the stored target.
     *   Types: the stored type is in `r` / N's vocabulary. Record types: rt outer ∈ owners(N),
     *   linked ∈ targets(N); rf the other way round.
     * - `related[:N]`: either stored direction; the stored type or its inverse is in `r` /
     *   N's vocabulary; (outer ∈ owners and linked ∈ targets) or (outer ∈ targets and
     *   linked ∈ owners).
     *
     * @param string $base rt|rf|related (aliases resolved by the caller)
     * @param string $suffix Relationship field ID, or ''
     * @param array|null $explicitTypes Relation types given as `r` in the value, or null
     * @return array<int,array{direction:string,types:?array,outerTypes:?array,linkedTypes:?array}>
     *         direction 'to' = outer is the stored source, 'from' = outer is the stored
     *         target; null lists mean any. No legs = nothing can match.
     */
    public function relationshipLegs(string $base, string $suffix, ?array $explicitTypes): array
    {
        $related = $base === 'related';
        $marker = null;
        if($suffix !== ''){
            if(!ctype_digit($suffix) || intval($suffix) < 1){
                throw new QueryValidationException('Relationship field ID must be a positive integer: '.$suffix);
            }
            $marker = $this->marker(intval($suffix));
        }
        // a term or its inverse reads the same relationship from its other end
        $both = function(array $terms): array {
            return array_values(array_unique(array_merge($this->expand($terms), $this->inverse($terms))));
        };
        $types = null;
        if($explicitTypes !== null){
            $types = $related ? $both($explicitTypes) : $this->expand($explicitTypes);
        }
        if($marker !== null && $marker['types'] !== null){
            $vocabulary = $related ? $both($marker['types']) : $marker['types'];
            $types = $types === null ? $vocabulary : array_values(array_intersect($types, $vocabulary));
        }
        if($types !== null && empty($types)){ return array(); }

        $owners = $marker === null || empty($marker['ownerTypes']) ? null : $marker['ownerTypes'];
        $targets = $marker === null || empty($marker['recordTypes']) ? null : $marker['recordTypes'];
        if($related){
            $pairs = array(array($owners, $targets));
            if($marker !== null && $owners !== $targets){ $pairs[] = array($targets, $owners); }
            $directions = array('to', 'from');
        }else{
            $pairs = array($base === 'rt' ? array($owners, $targets) : array($targets, $owners));
            $directions = array($base === 'rt' ? 'to' : 'from');
        }
        $legs = array();
        foreach($directions as $direction){
            foreach($pairs as list($outer, $linked)){
                $legs[] = array('direction'=>$direction, 'types'=>$types, 'outerTypes'=>$outer, 'linkedTypes'=>$linked);
            }
        }
        return $legs;
    }
}
