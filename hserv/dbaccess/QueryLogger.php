<?php
namespace hserv\dbaccess;

class QueryLogger
{
    private ?string $logFile = null;
    private ?string $logPath = '/tmp';
    private array $log = [];
 
    public function setLogPath(string $path): void
    {
        $this->logPath = $path;
    }

    public function clear(): void
    {
        $this->log = [];
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    public function get(): array
    {
        return $this->log;
    }

    public function log(string $currentDb, string $query, float $duration): void
    {

        if ($this->logFile) {
            file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND);
        }
        
        $this->logFile = $this->logPath."/db_query_log_{$currentDb}.txt";

        $entry = sprintf("[%s] (%.4f s) %s\n", date('Y-m-d H:i:s'), $duration, $query);
        $fp = fopen($this->logFile, 'a');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $entry);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        $this->log[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'duration' => $duration,
            'database' => $currentDb,
            'query' => $query
        ];
        
    }
}
