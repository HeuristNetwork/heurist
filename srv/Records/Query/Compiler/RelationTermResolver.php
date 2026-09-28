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

    /** A relation marker's allowed relationship types (null = any) and endpoint record types. */
    public function marker(int $fieldId): array
    {
        if(isset($this->markers[$fieldId])){ return $this->markers[$fieldId]; }
        $rows = $this->database->fetchRows(
            'SELECT dty_JsonTermIDTree,dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_ID=?',
            array($fieldId)
        );
        if(empty($rows)){ throw new QueryValidationException('Unknown relation-marker field ID: '.$fieldId); }
        $row = array_values($rows[0]);
        $rootTypes = self::idsFromText($row[0] ?? '');
        $this->markers[$fieldId] = array(
            'types' => empty($rootTypes) ? null : $this->expand($rootTypes),
            'recordTypes' => self::idsFromText($row[1] ?? '')
        );
        return $this->markers[$fieldId];
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
