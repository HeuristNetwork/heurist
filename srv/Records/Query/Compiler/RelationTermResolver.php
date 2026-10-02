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
use Heurist\Records\Query\QueryValidationException;

/** Term closure, inverse terms and relation-marker constraints, cached per instance. */
final class RelationTermResolver
{
    private const CHUNK_SIZE = 500;
    private $database;
    /** @var array<int,array{types:?array,recordTypes:array}> */
    private $markers = array();

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** Terms plus every descendant through defTermsLinks. */
    public function expand(array $termIds): array
    {
        $all = array_fill_keys(self::ids($termIds), true);
        $frontier = array_keys($all);
        while(!empty($frontier)){
            $next = array();
            foreach(array_chunk($frontier, self::CHUNK_SIZE) as $chunk){
                $sql = 'SELECT DISTINCT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('
                    .implode(',', array_fill(0, count($chunk), '?')).')';
                foreach($this->database->fetchRows($sql, $chunk) as $row){
                    $id = intval(array_values($row)[0]);
                    if($id>0 && !isset($all[$id])){ $all[$id] = true; $next[] = $id; }
                }
            }
            $frontier = $next;
        }
        return array_map('intval', array_keys($all));
    }

    /**
     * The terms a relationship reads as from its target, plus descendants. A term's
     * reverse is its trm_InverseTermID or any term naming it as inverse; a term with
     * no inverse is undirected and is its own reverse.
     */
    public function inverse(array $termIds): array
    {
        $inverse = array();
        foreach(array_chunk(self::ids($termIds), self::CHUNK_SIZE) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = 'SELECT trm_ID, trm_InverseTermID FROM defTerms'
                .' WHERE trm_ID IN ('.$placeholders.') OR trm_InverseTermID IN ('.$placeholders.')';
            $found = array_fill_keys($chunk, false);
            foreach($this->database->fetchRows($sql, array_merge($chunk, $chunk)) as $row){
                list($termId, $inverseId) = array_map('intval', array_values($row));
                if(isset($found[$termId]) && $inverseId>0){ $inverse[] = $inverseId; $found[$termId] = true; }
                if(isset($found[$inverseId]) && $termId>0){ $inverse[] = $termId; $found[$inverseId] = true; }
            }
            foreach($found as $termId=>$hasInverse){
                if(!$hasInverse){ $inverse[] = intval($termId); }
            }
        }
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
        $rows = $this->database->fetchRows(
            'SELECT dty_JsonTermIDTree,dty_PtrTargetRectypeIDs,dty_Type FROM defDetailTypes WHERE dty_ID=?',
            array($fieldId)
        );
        if(empty($rows)){ throw new QueryValidationException('Unknown relationship field ID: '.$fieldId); }
        $row = array_values($rows[0]);
        if(isset($row[2]) && $row[2] !== 'relmarker'){
            throw new QueryValidationException('Field '.$fieldId.' is not a relationship field');
        }
        $rootTypes = self::idsFromText($row[0] ?? '');
        $owners = $this->database->fetchRows(
            'SELECT DISTINCT rst_RecTypeID FROM defRecStructure WHERE rst_DetailTypeID=?', array($fieldId)
        );
        $this->markers[$fieldId] = array(
            'types' => empty($rootTypes) ? null : $this->expand($rootTypes),
            'ownerTypes' => self::ids(array_map(static function($owner){ return array_values($owner)[0]; }, $owners)),
            'recordTypes' => self::idsFromText($row[1] ?? '')
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

    private static function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values), static function($id){
            return $id>0;
        })));
    }

    private static function idsFromText($value): array
    {
        preg_match_all('/(?<![0-9])[1-9][0-9]*(?![0-9])/', (string)$value, $matches);
        return self::ids($matches[0] ?? array());
    }
}
