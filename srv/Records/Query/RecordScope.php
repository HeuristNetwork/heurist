<?php
/**
* RecordScope.php - Ids of a record scope of a tool: the data source query or the
* selection, optionally limited to record types
*
* Used by the export (Records/Export/ExportPlanner) and the record actions
* (Records/Actions). The ids are read in pages of PAGE ids with the search access
* rules of the user; more than the given maximum is refused.
*
*   scope = {query, ids?, rectypes?}: ids (the selection) replace the query.
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

use DomainException;
use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;

/** Resolves the ids of a tool scope (query or selection, record types). */
final class RecordScope
{
    /** Ids read per search request. */
    private const PAGE = 100000;

    private DatabaseInterface $database;
    private RuntimeContext $runtime;

    /**
     * @param DatabaseInterface $database Database.
     * @param RuntimeContext $runtime Current user (search access rules).
     */
    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->database = $database;
        $this->runtime = $runtime;
    }

    /**
     * Ids of the scope, in the order of the search.
     *
     * @param mixed $query Query of the data source (array or JSON text); not used with ids.
     * @param int[] $ids Selected ids (empty = the query).
     * @param int[] $rectypes Only records of these types (empty = all).
     * @param int $max At most this many records; more is refused.
     * @param int $limit Read only the first $limit ids (0 = all).
     * @param string $tooMany Message for more than $max records ({max} = the maximum).
     * @return int[]
     * @throws InvalidArgumentException An invalid query.
     * @throws DomainException More than $max records.
     */
    public function ids($query, array $ids, array $rectypes, int $max, int $limit = 0, string $tooMany = ''): array
    {
        $builder = new QueryBuilder($this->database);
        if(!empty($ids)){
            $search = array(array('ids' => implode(',', array_map('intval', $ids))));
        }else{
            if(is_string($query)){
                $decoded = json_decode($query, true);
                $query = is_array($decoded) ? $decoded : $query;
            }
            $search = $builder->normalize($query);
            if(!is_array($search)){
                throw new InvalidArgumentException('Invalid query');
            }
        }
        if(!empty($rectypes)){
            $search[] = array('t' => implode(',', array_map('intval', $rectypes)));
        }
        $service = new RecordSearchService($this->database, $this->runtime, $builder);
        $wanted = $limit > 0 ? min($limit, $max + 1) : $max + 1;
        $found = array();
        do{
            $page = min(self::PAGE, $wanted - count($found));
            $result = $service->search(new SearchRequest($search, array(
                'limit' => $page,
                'offset' => count($found),
                'total' => false
            )));
            $found = array_merge($found, array_map('intval', $result->ids));
        }while(count($result->ids) === $page && count($found) < $wanted);
        if(count($found) > $max){
            throw new DomainException(str_replace('{max}', (string)$max, $tooMany !== ''
                ? $tooMany : 'The query finds more than {max} records'));
        }
        return $found;
    }
}
