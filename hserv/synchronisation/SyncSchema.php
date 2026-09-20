<?php

namespace hserv\synchronisation;

/**
 * Creates the self-contained experimental synchronisation tables on first use.
 */
final class SyncSchema
{
    public static function ensure(\mysqli $mysqli): bool
    {
        $requiredTables = [
            'sysSyncSessions', 'sysSyncRecordMap', 'sysSyncNonces', 'sysSyncOutboundRecords',
            'sysSyncPayloads', 'sysSyncFileMap', 'sysSyncInboundFileMap', 'sysSyncProgress'
        ];
        $quoted = "'".implode("','", $requiredTables)."'";
        $existing = (int)mysql__select_value(
            $mysqli,
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($quoted)"
        );
        if ($existing === count($requiredTables)) return true;

        $queries = [
            "CREATE TABLE IF NOT EXISTS sysSyncSessions (
                ssy_ID char(36) NOT NULL,
                ssy_SatelliteDBID int unsigned NOT NULL,
                ssy_State varchar(40) NOT NULL,
                ssy_BaseMasterChangeID bigint unsigned NOT NULL default 0,
                ssy_ResultMasterChangeID bigint unsigned NOT NULL default 0,
                ssy_Created datetime NOT NULL,
                ssy_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                ssy_Completed datetime default NULL,
                ssy_Error text default NULL,
                PRIMARY KEY (ssy_ID),
                KEY ssy_SatelliteState (ssy_SatelliteDBID, ssy_State),
                KEY ssy_Modified (ssy_Modified)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Persistent state for resumable Heurist database synchronisation'",
            "CREATE TABLE IF NOT EXISTS sysSyncRecordMap (
                srm_SatelliteDBID int unsigned NOT NULL,
                srm_SatelliteLocalRecID int unsigned NOT NULL,
                srm_MasterRecID int unsigned NOT NULL,
                srm_SessionID char(36) NOT NULL,
                srm_Created timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (srm_SatelliteDBID, srm_SatelliteLocalRecID),
                UNIQUE KEY srm_MasterRecID (srm_MasterRecID),
                KEY srm_SessionID (srm_SessionID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Permanent idempotent mapping from original satellite IDs to master IDs'",
            "CREATE TABLE IF NOT EXISTS sysSyncNonces (
                snc_SatelliteDBID int unsigned NOT NULL,
                snc_Nonce char(36) NOT NULL,
                snc_SeenAt timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (snc_SatelliteDBID, snc_Nonce),
                KEY snc_SeenAt (snc_SeenAt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Replay protection for signed synchronisation requests'",
            "CREATE TABLE IF NOT EXISTS sysSyncOutboundRecords (
                sor_OriginalLocalRecID int unsigned NOT NULL,
                sor_CurrentRecID int unsigned NOT NULL,
                sor_MasterRecID int unsigned NOT NULL,
                sor_SessionID char(36) NOT NULL,
                sor_FirstChangeID bigint unsigned NOT NULL,
                sor_Status enum('RESERVED','ALLOCATED','UPLOADED') NOT NULL default 'RESERVED',
                sor_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                PRIMARY KEY (sor_OriginalLocalRecID),
                UNIQUE KEY sor_CurrentRecID (sor_CurrentRecID),
                UNIQUE KEY sor_MasterRecID (sor_MasterRecID),
                KEY sor_SessionStatus (sor_SessionID,sor_Status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Persistent satellite-side outbound record allocation state'",
            "CREATE TABLE IF NOT EXISTS sysSyncPayloads (
                spl_Hash char(64) NOT NULL,
                spl_SessionID char(36) NOT NULL,
                spl_SatelliteDBID int unsigned NOT NULL,
                spl_RecordCount int unsigned NOT NULL,
                spl_State enum('RECEIVED','APPLIED','FAILED') NOT NULL default 'RECEIVED',
                spl_Error text default NULL,
                spl_Created timestamp NOT NULL default CURRENT_TIMESTAMP,
                spl_Applied datetime default NULL,
                PRIMARY KEY (spl_SessionID,spl_Hash),
                KEY spl_Session (spl_SessionID,spl_State)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Idempotency log for inbound synchronisation payloads'",
            "CREATE TABLE IF NOT EXISTS sysSyncFileMap (
                sfm_SatelliteDBID int unsigned NOT NULL,
                sfm_SatelliteFileID mediumint unsigned NOT NULL,
                sfm_MasterFileID mediumint unsigned NOT NULL,
                sfm_ContentHash char(64) default NULL,
                sfm_Created timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (sfm_SatelliteDBID,sfm_SatelliteFileID),
                UNIQUE KEY sfm_MasterFileID (sfm_MasterFileID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Permanent mapping for synchronised uploaded files'",
            "CREATE TABLE IF NOT EXISTS sysSyncInboundFileMap (
                sif_MasterFileID mediumint unsigned NOT NULL,
                sif_LocalFileID mediumint unsigned NOT NULL,
                sif_ContentHash char(64) default NULL,
                sif_Created timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (sif_MasterFileID),
                KEY sif_LocalFileID (sif_LocalFileID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Master-to-satellite uploaded-file ID mapping'",
            "CREATE TABLE IF NOT EXISTS sysSyncProgress (
                spr_ID tinyint unsigned NOT NULL default 1,
                spr_State varchar(20) NOT NULL default 'IDLE',
                spr_Step varchar(50) NOT NULL default 'IDLE',
                spr_NewRecords int unsigned NOT NULL default 0,
                spr_UpdatedRecords int unsigned NOT NULL default 0,
                spr_NewCompleted int unsigned NOT NULL default 0,
                spr_UpdatedCompleted int unsigned NOT NULL default 0,
                spr_Message varchar(1000) default NULL,
                spr_Log text default NULL,
                spr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                PRIMARY KEY (spr_ID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Current local synchronisation progress for UI polling'"
        ];
        foreach ($queries as $query) {
            if (!$mysqli->query($query)) {
                return false;
            }
        }
        return true;
    }

    /** Progress polling needs only its own table and must remain lightweight. */
    public static function ensureProgress(\mysqli $mysqli): bool
    {
        if (mysql__select_value(
            $mysqli,
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sysSyncProgress'"
        )) return true;
        return (bool)$mysqli->query("CREATE TABLE IF NOT EXISTS sysSyncProgress (
            spr_ID tinyint unsigned NOT NULL default 1,
            spr_State varchar(20) NOT NULL default 'IDLE',
            spr_Step varchar(50) NOT NULL default 'IDLE',
            spr_NewRecords int unsigned NOT NULL default 0,
            spr_UpdatedRecords int unsigned NOT NULL default 0,
            spr_NewCompleted int unsigned NOT NULL default 0,
            spr_UpdatedCompleted int unsigned NOT NULL default 0,
            spr_Message varchar(1000) default NULL,
            spr_Log text default NULL,
            spr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
            PRIMARY KEY (spr_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Current local synchronisation progress for UI polling'");
    }
}
