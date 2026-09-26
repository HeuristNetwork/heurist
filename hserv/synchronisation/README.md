# Master–Satellite synchronisation (database 1.5.0)

This implementation intentionally synchronises only terms and uploaded-file
references/content. Record data is disabled until its separate specification is
approved. Existing Master/Satellite configuration and the Satellite definition
editing restrictions remain in place.

## Identity and authority

Every object is identified permanently by `originating database ID + ID in that
originating database`. Local IDs are not used to decide whether two concepts are
the same. After Master allocation, record IDs and uploaded-file IDs themselves
are changed on the Satellite to the Master IDs, including all audited pointers.

The Master is authoritative for the content of every existing concept. A
Satellite can contribute a new concept, but it cannot overwrite an existing
Master term or file. A replacement file on Master keeps the same row and concept;
its MD5 and bytes replace the Satellite copy on the next run.

MD5 (`ulf_MD5Checksum`) remains fundamental. It verifies transferred bytes and
reports the exceptional legacy-clone case where the same historical `ulf_ID`
contains different bytes. No second checksum field is introduced.

## Restart model

Every run exchanges complete manifests. There are no last-sync timestamps,
change journals, cursors, or permanent session state. The receiver requests any
concept which is missing, incomplete, or different from Master. An interrupted
run is restarted normally: allocated placeholder rows and explicit `SyncState`
values reveal unfinished work, while completed work is ignored.

The only support tables are `sysSyncNonces` (HTTP replay protection) and
`sysSyncProgress` (a disposable UI log). They are not synchronization state.

## Ordered stages

1. Satellite integrity check assigns missing concept identities and calculates
   missing MD5 values for local files.
2. Satellite sends all locally originated term concepts. Master requests and
   inserts only missing concepts.
3. Master sends its complete term manifest. Satellite requests missing or
   different terms and applies Master labels and hierarchy positions.
4. Satellite sends all file concepts with local IDs and MD5 values. Master
   allocates or reuses `recUploadedFiles.ulf_ID` rows.
5. Satellite transactionally changes its file primary IDs and all
   `recDetails.dtl_UploadedFileID` references to the Master IDs.
6. Master requests metadata and bytes only for incomplete file rows. Each file
   is logged with concept, name, MD5, direction and byte count.
7. Master sends its full file manifest. Satellite requests missing, incomplete,
   or different files and applies the Master content.

Terms finish before files begin. Record data will begin only after both stages
finish in the later record implementation.

## Structure limitation

Satellite-created terms must be added with the term-field `+` button beneath an
existing vocabulary. Only label and hierarchy are synchronized in this stage.
General definition changes (record types, fields, descriptions, codes, URIs and
restructured vocabularies) require a future, separate structure synchronization
operation from the Master configuration form. When record transfer is added, a
missing definition must stop that record and tell the administrator to run that
structure operation; it must never upload corrupted or partially mapped data.
