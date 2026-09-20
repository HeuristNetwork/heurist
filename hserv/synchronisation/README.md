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
- a one-time first-sync inventory which journals non-temporary satellite
  records created before the synchronisation triggers were installed;
- transactional, two-stage satellite ID remapping with an audited list of
  record-ID-bearing tables and record-pointer detail values;
- validated record-content upload into the reserved master records, including
  concept-code mapping for record types, fields and existing terms;
- retry-safe, hashed payload batches (100 records per satellite request) with
  transactional application on the master;
- transfer of permitted satellite-created vocabulary terms beneath existing
  master vocabularies;
- transfer of external-file registrations and local uploaded files, with
  content hashes and permanent satellite-to-master file mappings;
- detection and full-record upload of changes to previously synchronised
  satellite records;
- incremental download and local application of master records contributed by
  other satellites, including their permitted terms and uploaded files;
- dependency-safe record-pointer application using batch pre-creation and
  temporary shells for master targets delivered in later batches;
- automatic master-to-satellite structure updates before records are applied,
  including record types, fields, record structures and vocabularies;
- preflight detection and holdback of satellite-created record types and fields
  used by pending records, while compatible records continue to synchronise,
  with an explicit list and master template-import guidance;
- pollable progress reporting which begins with new/updated record counts and
  reports each allocation, dependency and record-transfer phase;
- JSON diagnostics for otherwise blank controller failures and an extended
  execution allowance for slow master connections;
- normal structure editing blocked on satellites while term editing remains available.

Tables beginning `sysSync` are created on first operational use. They are kept
out of the core schema while the feature is experimental.

## Deliberately not yet enabled

- three-way merge, deletion and priority conflict resolution;
- master-to-satellite change delivery and completion cursors.

The UI performs a real authenticated start/resume handshake, permanent-ID
remapping, dependency transfer and record-content upload. New satellite terms
are accepted only below an existing master vocabulary. Structure remains under
master control and is refreshed on every synchronisation. Local files are
currently limited to 20 MB each and 40 MB per dependency batch.
