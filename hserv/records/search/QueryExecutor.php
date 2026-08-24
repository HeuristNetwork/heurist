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

namespace hserv\records\search;

require_once dirname(__FILE__).'/SearchTypes.php';

/** Thin database boundary for the modern record search engine. */
class QueryExecutor
{
    /** @var \mysqli */
    protected $mysqli;

    /** @param \mysqli $mysqli Active database connection. */
    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
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
     * @param string $sql SQL containing mysqli placeholders.
     * @param string $types mysqli bind_param type string.
     * @param array $values Values in placeholder order.
     */
    public function executeRows(string $sql, string $types = '', array $values = array()): array
    {
        $statement = $this->mysqli->prepare($sql);
        if(!$statement){
            throw new SearchExecutionException('Unable to prepare record search: '.$this->mysqli->error);
        }

        try{
            if($types !== ''){
                if(strlen($types) !== count($values)){
                    throw new SearchExecutionException('Search parameter count does not match bind types');
                }
                $bindValues = array_values($values);
                $arguments = array($types);
                foreach($bindValues as $index=>&$value){ $arguments[] = &$value; }
                unset($value);
                if(!call_user_func_array(array($statement, 'bind_param'), $arguments)){
                    throw new SearchExecutionException('Unable to bind record search parameters: '.$statement->error);
                }
            }
            if(!$statement->execute()){
                throw new SearchExecutionException('Unable to execute record search: '.$statement->error);
            }

            $metadata = $statement->result_metadata();
            if(!$metadata){ return array(); }
            $columnCount = $metadata->field_count;
            $metadata->free();
            $row = array_fill(0, $columnCount, null);
            $references = array();
            foreach($row as $index=>&$value){ $references[$index] = &$value; }
            unset($value);
            call_user_func_array(array($statement, 'bind_result'), $references);

            $rows = array();
            while($statement->fetch()){
                $rows[] = array_map(static function($value){ return $value; }, $row);
            }
            return $rows;
        }finally{
            $statement->close();
        }
    }
}
