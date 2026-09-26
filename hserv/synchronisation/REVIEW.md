# Master–satellite synchronisation review

Reviewed source: HeuristNetwork/heurist, h7dev commit `9e0a43ba14a505c84065fec9b8cf49b08d0baccc`. This package contains the reviewed implementation, all dedicated synchronisation source files (including unchanged UI files), unchanged shared integration references, executable regression fixtures, a patch and a SHA-256 manifest. The final GitHub refresh found no upstream changes to the included synchronisation or integration files since the initial review baseline (5940d87). No changes were deployed or pushed. No live user database was accessed.

## Principal finding: existing field values could be lost

Both upload application on the master and download application on the satellite deleted all existing recDetails rows for a record before inserting an incoming snapshot. An omitted image field could therefore disappear even during a successful synchronisation, leaving the physical upload unreferenced exactly as the screenshot shows. This is a confirmed defect in the code; attribution of the particular incident still requires record IDs, archive history or a backup. The changes do not restore values already lost.

The replacement writer retains every existing detail row and its ID, including image references. It appends missing occurrences, preserves repeated identical values, and makes replay of an identical batch idempotent. Existing nonempty header values are preserved. Conflicting source values accumulate instead of replacing earlier values. Sync does not propagate deletions.

## Other corrections

- Checked transactions roll back incomplete batches and restore the journal guard. Commit failure cannot be acknowledged as success. Nested transaction entry is rejected.
- Two-stage record-ID remapping moves parent records before pointer updates, satisfying foreign keys and pointer triggers. Allocation-state completion is committed in the same transaction as renumbering.
- Incoming record IDs require proven provenance or an established outbound mapping. Equal numeric IDs alone cannot authorise writing into an unrelated local record.
- Retries use durable ownership/allocation mappings across sessions. Dependency and content exports share a consistent snapshot.
- Journal reconciliation revisits history so a transaction committing late cannot be permanently skipped by an auto-increment checkpoint. Master download pages group changes by record.
- File writes use fresh immutable attempt paths, check complete writes, and preserve files on uncertain commits. Changed bytes under an existing mapped file identity stop for reconciliation. Orphan attempt files may remain; cleanup is separate.
- Atomic configuration writes preserve keys and checkpoints; established database identities cannot be silently repointed. Browser configuration no longer exposes satellite secrets.
- Sync operations/configuration saves are serialised; relevant nontransactional tables are rejected. SQL and pagination failures receive explicit handling.

## Documentation retained

The original README is retained verbatim, followed by a detailed explanation of the revised algorithm, ordering, failure recovery, configuration, file handling, ownership limitations and component responsibilities. Existing code comments are retained, with additional function/block explanations. Existing UI files are unchanged, and detailed field/record/file diagnostic messages are retained.

Historical statements that no longer apply are flagged as superseded, not removed. In particular, the old README statement about unimplemented master download and the old pointer-before-parent remapping explanation should eventually be edited after review; their replacements explain the current behaviour.

## Validation

- PHP 8.3 syntax checks: all 22 dedicated production PHP files passed.
- Additive writer/transaction harness: 12 checks passed, covering omitted images, stable detail IDs, multiplicity, replay, headers, insert failure, rollback, failed commit, malformed payload and nested transactions. This uses the actual helpers with an in-memory mysqli double.
- Real filesystem configuration harness: 8 checks passed, including key/checkpoint preservation, identity rejection, corrupt-file preservation and secret redaction.
- Real filesystem file-storage harness: 3 checks passed for immutable attempts, retained old bytes and zero-byte files.
- MariaDB 10.11 bootstrap SQL fixture passed parent-first remapping/foreign-key/pointer-trigger and image-reference rollback checks. This is a small engine fixture, not the complete Heurist schema.
- Git whitespace checks passed. Original README prefix and existing code comments were checked for preservation.

A full two-server Heurist/MySQL 8 integration run has not been performed. The environment cannot open the database socket required for it. Do not treat these unit/fixture checks as production certification.

## Upgrade and remaining limits

Install matching dedicated files on both ends in a backed-up test environment first. The included shared integration files are unchanged baseline references: compare them with your current files instead of blindly overwriting unrelated newer work. Apply the patch only to a compatible checkout. The manifest identifies modified, new and unchanged files.

Older satellites lack the new inbound-provenance table. Ambiguous pre-existing downloaded records will stop until an administrator verifies their identity; do not populate provenance by assuming equal IDs mean equal records. The README explains the evidence needed.

Use a quiet maintenance window for record-ID remapping. Sync locks do not stop every ordinary editor; an already-open form can submit a stale ID after renumbering. Full-history reconciliation increases work and needs performance measurement on large databases. Structure remains master-controlled and can affect display of retained data. Existing ownership rules still exclude a contributor's own records from its master download; this is not a general shared-record editing protocol.

Before deployment, run backed-up master/satellite copies, compare every original record/detail/file reference before and after, replay batches and interrupt at each phase, then verify links, dates, structure and display. Do not remove orphan files based solely on a failed run. Disk failures, external deletions and arbitrary custom triggers remain outside the guarantee.

See `hserv/synchronisation/README.md` for the complete human-readable sequence and interruption table. Tests are under `tests/synchronisation/`.
