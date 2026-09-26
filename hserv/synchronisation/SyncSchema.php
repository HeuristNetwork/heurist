<?php
namespace hserv\synchronisation;

/** Runtime-only tables: no sync journal, cursor or persistent session register. */
final class SyncSchema
{
    public static function ensure(\mysqli $mysqli): bool
    {
        $queries=["CREATE TABLE IF NOT EXISTS sysSyncNonces (
            snc_SatelliteDBID int unsigned NOT NULL,snc_Nonce char(36) NOT NULL,
            snc_SeenAt timestamp NOT NULL default CURRENT_TIMESTAMP,
            PRIMARY KEY (snc_SatelliteDBID,snc_Nonce),KEY snc_SeenAt (snc_SeenAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Replay protection for signed synchronisation requests'",
        self::progressSQL()];
        foreach($queries as $query) if(!$mysqli->query($query)) return false;
        return true;
    }
    public static function ensureProgress(\mysqli $mysqli): bool { return (bool)$mysqli->query(self::progressSQL()); }
    private static function progressSQL(): string
    {
        // Legacy count columns remain so older progress readers do not fail.
        return "CREATE TABLE IF NOT EXISTS sysSyncProgress (
            spr_ID tinyint unsigned NOT NULL default 1,spr_State varchar(20) NOT NULL default 'IDLE',
            spr_Step varchar(50) NOT NULL default 'IDLE',spr_NewRecords int unsigned NOT NULL default 0,
            spr_UpdatedRecords int unsigned NOT NULL default 0,spr_NewCompleted int unsigned NOT NULL default 0,
            spr_UpdatedCompleted int unsigned NOT NULL default 0,spr_Message varchar(1000) default NULL,
            spr_Log mediumtext default NULL,spr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
            PRIMARY KEY (spr_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Disposable local synchronisation progress for UI polling'";
    }
}
