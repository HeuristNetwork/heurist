# Experimental master/satellite synchronisation

This directory contains the isolated foundation for synchronising registered
Heurist databases. It is inactive unless `$experimental = true` is set in the
server's parent `heuristConfigIni.php`.

## Implemented

- database-specific `settings/synchronisation.json` configuration;
- master/satellite roles and ordered satellite priorities;
- derived-key HMAC authentication, timestamp checks and nonce replay protection;
- persistent, resumable master sessions;
- idempotent master record-ID allocation using real temporary `Records`;
- a permanent `(satellite database ID, original local record ID) -> master ID` map;
- an append-only trigger-based record/detail/term change journal, installed only
  when a database is explicitly configured for synchronisation;
- discovery of newly completed satellite records, record-type resolution by
  concept code, remote ID allocation and resumable local remapping;
- transactional, two-stage satellite ID remapping with an audited list of
  record-ID-bearing tables and record-pointer detail values;
- normal structure editing blocked on satellites while term editing remains available.

Tables beginning `sysSync` are created on first operational use. They are kept
out of the core schema while the feature is experimental.

## Deliberately not yet enabled

- structure and term exchange;
- HML record and attachment transfer;
- three-way merge, deletion and priority conflict resolution;
- master-to-satellite change delivery and completion cursors.

The UI therefore performs a real authenticated start/resume handshake but does
not yet offer a control that changes record data. These later phases should use
the existing session states and must remain idempotent.
