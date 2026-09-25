/*
 * Heurist database structure 1.5.0.
 *
 * Synchronised records and file references use permanent concept identities:
 * originating database ID plus the ID assigned there. Sync-state values record
 * completeness, not historical progress; every run compares full manifests.
 *
 * Safety rating: SAFE
 * Description: Add concept identities and completeness states for records and files.
 */

ALTER TABLE Records
  ADD COLUMN rec_OriginatingDBID MEDIUMINT UNSIGNED DEFAULT NULL
    COMMENT 'Registered ID of the database where this record originated',
  ADD COLUMN rec_IDInOriginatingDB INT UNSIGNED DEFAULT NULL
    COMMENT 'rec_ID assigned by the database where this record originated',
  ADD COLUMN rec_SyncState ENUM('local','allocated','pending_dependencies','complete')
    NOT NULL DEFAULT 'local'
    COMMENT 'Completeness state for restartable master-satellite synchronisation',
  ADD UNIQUE KEY rec_OriginIdentity (rec_OriginatingDBID,rec_IDInOriginatingDB);

ALTER TABLE recUploadedFiles
  ADD COLUMN ulf_OriginatingDBID MEDIUMINT UNSIGNED DEFAULT NULL
    COMMENT 'Registered ID of the database where this file reference originated',
  ADD COLUMN ulf_IDInOriginatingDB MEDIUMINT UNSIGNED DEFAULT NULL
    COMMENT 'ulf_ID assigned by the database where this file reference originated',
  ADD COLUMN ulf_SyncState ENUM('local','pending_metadata','pending_content','complete')
    NOT NULL DEFAULT 'local'
    COMMENT 'Completeness state for restartable master-satellite synchronisation',
  ADD UNIQUE KEY ulf_OriginIdentity (ulf_OriginatingDBID,ulf_IDInOriginatingDB);

UPDATE Records r JOIN sysIdentification s
   SET r.rec_OriginatingDBID=s.sys_dbRegisteredID,
       r.rec_IDInOriginatingDB=r.rec_ID,
       r.rec_SyncState='local'
 WHERE COALESCE(r.rec_OriginatingDBID,0)=0
    OR COALESCE(r.rec_IDInOriginatingDB,0)=0;

UPDATE recUploadedFiles f JOIN sysIdentification s
   SET f.ulf_OriginatingDBID=s.sys_dbRegisteredID,
       f.ulf_IDInOriginatingDB=f.ulf_ID,
       f.ulf_SyncState='local'
 WHERE COALESCE(f.ulf_OriginatingDBID,0)=0
    OR COALESCE(f.ulf_IDInOriginatingDB,0)=0;

UPDATE sysIdentification
   SET sys_dbVersion=1, sys_dbSubVersion=5, sys_dbSubSubVersion=0
 WHERE 1=1;
