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
