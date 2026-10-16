<?php

namespace hserv\synchronisation;

/** A checked transaction boundary: only finish(true) can commit a sync stage. */
final class SyncTransaction
{
    private \mysqli $mysqli;
    private bool $active = false;
    private static array $connections = [];

    public function __construct(\mysqli $mysqli, bool $unusedJournalFlag = false)
    {
        $this->mysqli = $mysqli;
        $id = spl_object_id($mysqli);
        if (isset(self::$connections[$id])) throw new \RuntimeException('Nested synchronisation transactions are not allowed.');
        if (!$mysqli->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || !$mysqli->begin_transaction()) {
            throw new \RuntimeException('Unable to start a synchronisation transaction.');
        }
        self::$connections[$id] = true;
        $this->active = true;
    }

    public function finish(bool $ok): bool
    {
        if (!$ok) { $this->close(); return false; }
        if (!$this->mysqli->commit()) throw new \RuntimeException('Synchronisation commit failed.');
        $this->active = false;
        unset(self::$connections[spl_object_id($this->mysqli)]);
        return true;
    }

    public function close(): void
    {
        if ($this->active) $this->mysqli->rollback();
        $this->active = false;
        unset(self::$connections[spl_object_id($this->mysqli)]);
    }
}
