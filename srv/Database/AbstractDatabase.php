<?php
/**
* AbstractDatabase.php - Shared PDO wrapper for provider implementations
*
* Keeps the common fetch/prepare/bind logic in one place while allowing each
* engine to provide only its own DSN and connection bootstrap.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Shared implementation for PDO-backed database providers.
 *
 * Concrete classes only need to define the connection bootstrap and driver name
 * decisions; fetch and statement execution remains common.
 */
abstract class AbstractDatabase implements DatabaseInterface
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function fetchRows(string $sql, array $parameters = array()): array
    {
        return $this->statement($sql, $parameters)->fetchAll(PDO::FETCH_NUM);
    }

    public function fetchAll(string $sql, array $parameters = array()): array
    {
        return $this->statement($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchColumn(string $sql, array $parameters = array()): array
    {
        return $this->statement($sql, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function fetchValue(string $sql, array $parameters = array(), $default = null)
    {
        $value = $this->statement($sql, $parameters)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public function getDriver(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Prepare and execute a statement with positional or named parameters.
     *
     * @param string $sql Parameterised SQL.
     * @param array<int|string,mixed> $parameters Bound values.
     * @throws DatabaseException When preparation or execution fails.
     */
    protected function statement(string $sql, array $parameters): PDOStatement
    {
        try{
            $statement = $this->pdo->prepare($sql);
            $position = 1;
            foreach($parameters as $name => $value){
                $parameter = is_int($name) ? $position++ : (string)$name;
                if(!is_int($name) && strpos($parameter, ':') !== 0){
                    $parameter = ':'.$parameter;
                }
                $type = is_int($value) ? PDO::PARAM_INT
                    : (is_bool($value) ? PDO::PARAM_BOOL
                    : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
                $statement->bindValue($parameter, $value, $type);
            }
            $statement->execute();
            return $statement;
        }catch(PDOException $exception){
            throw new DatabaseException('Database query failed', 0, $exception);
        }
    }
}
