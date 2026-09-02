<?php
/**
* QueryExecutor.php - Parameterized search SQL executor
*
* Executes SQL compiled by QueryBuilder and returns primitive rows, record IDs,
* or scalar values. It contains no query-language, expansion, or output logic.
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

namespace Heurist\Records\Query;

use Heurist\Database\DatabaseException;
use Heurist\Database\DatabaseInterface;

/** Thin database boundary for the modern record search engine. */
class QueryExecutor
{
    private DatabaseInterface $database;

    /** Initialise the executor with the modern database abstraction. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** Execute an IDs query and return integer record IDs in database order. */
    public function executeIds(CompiledQuery $query): array
    {
        $rows = $this->executeRows($query->sql, $query->types, $query->values);
        return array_values(array_map('intval', array_column($rows, 0)));
    }

    /** Execute a count/scalar query and return its first value. */
    public function executeScalar(CompiledQuery $query)
    {
        $rows = $this->executeRows($query->sql, $query->types, $query->values);
        return empty($rows) ? null : $rows[0][0];
    }

    /** Execute a grouped record-type count query. */
    public function executeRectypeCounts(CompiledQuery $query): array
    {
        return array_values(array_map(static function(array $row): array {
            return array('rec_RecTypeID'=>intval($row[0]), 'count'=>intval($row[1]));
        }, $this->executeRows($query->sql, $query->types, $query->values)));
    }

    /** Count record types for an already filtered fallback ID set. */
    public function executeRectypeCountsForIds(array $ids, int $chunkSize = 1000): array
    {
        $counts = array();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        foreach(array_chunk($ids, max(1, $chunkSize)) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $this->executeRows(
                'SELECT rec_RecTypeID, COUNT(*) FROM Records WHERE rec_ID IN ('
                    .$placeholders.') GROUP BY rec_RecTypeID',
                str_repeat('i', count($chunk)),
                $chunk
            );
            foreach($rows as $row){
                $typeId = intval($row[0]);
                $counts[$typeId] = ($counts[$typeId] ?? 0) + intval($row[1]);
            }
        }
        ksort($counts, SORT_NUMERIC);
        $result = array();
        foreach($counts as $typeId=>$count){
            $result[] = array('rec_RecTypeID'=>$typeId, 'count'=>$count);
        }
        return $result;
    }

    /**
     * Execute arbitrary parameterized SQL and return numeric rows.
     *
     * @param string $sql SQL containing positional placeholders.
     * @param string $types Legacy type metadata retained during compiler migration.
     * @param array $values Values in placeholder order.
     */
    public function executeRows(string $sql, string $types = '', array $values = array()): array
    {
        try{
            if($types !== ''){
                if(strlen($types) !== count($values)){
                    throw new SearchExecutionException('Search parameter count does not match bind types');
                }
            }
            return $this->database->fetchRows($sql, array_values($values));
        }catch(DatabaseException $exception){
            throw new SearchExecutionException('Unable to execute record search', 0, $exception);
        }
    }
}
