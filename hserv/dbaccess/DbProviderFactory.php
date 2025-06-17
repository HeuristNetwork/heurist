<?php
namespace hserv\dbaccess;

use PDO;
use InvalidArgumentException;

class DbProviderFactory
{
    
    public static function create(string $driverOrDsn): DbProviderInterface
    {
        try {
            $driver = str_contains($driverOrDsn, ':') ? explode(':', $driverOrDsn, 2)[0] : strtolower($driverOrDsn);

            //$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            return match (strtolower($driver)) {
                'mysql' => new MysqlProvider(),
                'pgsql', 'postgresql' => new PostgresqlProvider(),
                'sqlite' => new SqliteProvider(),
                default => throw new InvalidArgumentException("Unsupported driver: $driver")
            };
        } catch (Throwable $e) {
            //$message = sprintf("[ERROR] (%s) %s", $e->getCode(), $e->getMessage());
            //file_put_contents('/tmp/db_query_log.txt', "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
            throw new RuntimeException("DbProvider creation failed: " . $e->getMessage(), 0, $e);
        }
    }    
}
