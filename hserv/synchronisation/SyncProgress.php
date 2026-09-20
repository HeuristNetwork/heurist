<?php

namespace hserv\synchronisation;

/** Stores the current satellite operation so the browser can show real progress. */
final class SyncProgress
{
    private \mysqli $mysqli;

    public function __construct(\hserv\System $system)
    {
        $this->mysqli = $system->getMysqli();
    }

    public function begin(int $newRecords, int $updatedRecords): void
    {
        SyncSchema::ensureProgress($this->mysqli);
        $message = "New records found: $newRecords\nUpdated records found: $updatedRecords";
        $stmt = $this->mysqli->prepare(
            "INSERT INTO sysSyncProgress (spr_ID,spr_State,spr_Step,spr_NewRecords,spr_UpdatedRecords,"
            ."spr_NewCompleted,spr_UpdatedCompleted,spr_Message,spr_Log) VALUES (1,'RUNNING','COUNTED',?,?,0,0,?,?) "
            ."ON DUPLICATE KEY UPDATE spr_State='RUNNING',spr_Step='COUNTED',spr_NewRecords=VALUES(spr_NewRecords),"
            ."spr_UpdatedRecords=VALUES(spr_UpdatedRecords),spr_NewCompleted=0,spr_UpdatedCompleted=0,"
            ."spr_Message=VALUES(spr_Message),spr_Log=VALUES(spr_Log)"
        );
        $stmt->bind_param('iiss', $newRecords, $updatedRecords, $message, $message);
        $stmt->execute();
        $stmt->close();
    }

    /** Clears the previous report before the new run starts counting records. */
    public function startCounting(): void
    {
        SyncSchema::ensureProgress($this->mysqli);
        $message = 'Counting new and updated records…';
        $stmt = $this->mysqli->prepare(
            "INSERT INTO sysSyncProgress (spr_ID,spr_State,spr_Step,spr_NewRecords,spr_UpdatedRecords,"
            ."spr_NewCompleted,spr_UpdatedCompleted,spr_Message,spr_Log) VALUES (1,'COUNTING','COUNTING',0,0,0,0,?,'') "
            ."ON DUPLICATE KEY UPDATE spr_State='COUNTING',spr_Step='COUNTING',spr_NewRecords=0,"
            ."spr_UpdatedRecords=0,spr_NewCompleted=0,spr_UpdatedCompleted=0,"
            ."spr_Message=VALUES(spr_Message),spr_Log=''"
        );
        $stmt->bind_param('s', $message);
        $stmt->execute();
        $stmt->close();
    }

    public function update(string $step, string $message, ?int $newCompleted = null, ?int $updatedCompleted = null): void
    {
        $newCompleted = $newCompleted ?? (int)mysql__select_value($this->mysqli,
            'SELECT spr_NewCompleted FROM sysSyncProgress WHERE spr_ID=1');
        $updatedCompleted = $updatedCompleted ?? (int)mysql__select_value($this->mysqli,
            'SELECT spr_UpdatedCompleted FROM sysSyncProgress WHERE spr_ID=1');
        $stmt = $this->mysqli->prepare(
            "UPDATE sysSyncProgress SET spr_Step=?,spr_Message=?,spr_NewCompleted=?,spr_UpdatedCompleted=?,"
            ."spr_Log=CONCAT(COALESCE(spr_Log,''),'\n',?) WHERE spr_ID=1"
        );
        $stmt->bind_param('ssiis', $step, $message, $newCompleted, $updatedCompleted, $message);
        $stmt->execute();
        $stmt->close();
    }

    public function complete(string $message, int $newCompleted, int $updatedCompleted): void
    {
        $this->update('COMPLETED', $message, $newCompleted, $updatedCompleted);
        $this->mysqli->query("UPDATE sysSyncProgress SET spr_State='COMPLETED' WHERE spr_ID=1");
    }

    public function awaitUserAction(string $message, int $newCompleted, int $updatedCompleted): void
    {
        $this->update('AWAITING_USER_ACTION', $message, $newCompleted, $updatedCompleted);
        $this->mysqli->query("UPDATE sysSyncProgress SET spr_State='AWAITING_USER_ACTION' WHERE spr_ID=1");
    }

    public function fail(string $message): void
    {
        SyncSchema::ensureProgress($this->mysqli);
        $stmt = $this->mysqli->prepare(
            "INSERT INTO sysSyncProgress (spr_ID,spr_State,spr_Step,spr_Message,spr_Log) "
            ."VALUES (1,'FAILED','FAILED',?,'') ON DUPLICATE KEY UPDATE spr_State='FAILED',spr_Step='FAILED',"
            ."spr_Message=VALUES(spr_Message)"
        );
        $stmt->bind_param('s', $message);
        $stmt->execute();
        $stmt->close();
    }

    public function get(): array
    {
        $result = $this->mysqli->query('SELECT * FROM sysSyncProgress WHERE spr_ID=1');
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->close();
        if (!$row) return ['state' => 'IDLE', 'step' => 'IDLE', 'newRecords' => 0, 'updatedRecords' => 0,
            'newCompleted' => 0, 'updatedCompleted' => 0, 'message' => '', 'log' => ''];
        return [
            'state' => $row['spr_State'],
            'step' => $row['spr_Step'],
            'newRecords' => (int)$row['spr_NewRecords'],
            'updatedRecords' => (int)$row['spr_UpdatedRecords'],
            'newCompleted' => (int)$row['spr_NewCompleted'],
            'updatedCompleted' => (int)$row['spr_UpdatedCompleted'],
            'message' => (string)$row['spr_Message'],
            'log' => trim((string)$row['spr_Log']),
            'modified' => $row['spr_Modified']
        ];
    }
}
