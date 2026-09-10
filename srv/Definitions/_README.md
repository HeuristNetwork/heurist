# Database definitions (`/api/{db}/def`)

`Heurist\Definitions` — a peer of `Heurist\Records` and `Heurist\System`, holding
the modern `/def` resource family. First (and so far only) service: a single
compact snapshot of the database structure for client query builders
(`HFilterBuilder`) and the query-language describer (Task A). `/def` will
eventually replace the legacy `/api/{db}/rty|dty|trm|rst|trl` routes; individual
item reads still use those for now. If definitions later move to
`defRecords/defRecDetails`, a `Heurist\Definitions\Query` would mirror
`Heurist\System\Query`.

## Route

```
GET /api/{db}/def/snapshot
```

Read-only, anonymous-allowed (definitions are already public). Returns the
standard modern envelope via `Heurist\Runtime\ApiResponse`. Sends an `ETag`
(cache-file mtime) and answers `304` to a matching `If-None-Match`.

## Caching

`DefinitionSnapshotService` writes `def-snapshot.json` into the database
`entity` system directory (beside `dbdef_cache.json`). It is served from disk
when present and rebuilt on the next request after removal.
`hserv/System.php::cleanDefCache()` deletes `def-snapshot*.json` on every
structure edit / import, so no explicit version query is needed.

## Payload

See `documentation/development/query-language-filter-builder-plan.md` §4 for the
full shape. Summary:

- `meta` — `db`, `dbId` (registered ID, for concept codes), `version` (== ETag),
  `generated`, `language`, `languages`, `dbconst`
  (`RT_RELATION`, `DT_PRIMARY_RESOURCE`, `DT_TARGET_RESOURCE`, `DT_RELATION_TYPE`).
- `rectypeGroups`, `fieldGroups` — `{id: {name, order}}`.
- `rectypes` — `{id: {name, plural, group, concept, showInLists, description?}}`.
- `fields` — `{id: {name, type, group, concept, vocabulary?, targetTypes?}}`.
  `type` = `dty_Type`; layout-only `separator` fields are omitted. `vocabulary`
  is the vocabulary root term ID (integer) for enum / relation fields, absent
  otherwise.
- `structure` — `[{rty, dty, name, order, req}]` (`rst` per-rectype placement;
  consumer must drop `req == "forbidden"`; separator fields excluded). No
  per-rectype term/pointer narrowing — the builder uses the global field's
  `vocabulary` / `targetTypes`.
- `terms` — `{id: {label, code?, concept, domain?, inverse?}}`.
- `termlinks` — `[{parent, term}]` (from `defTermsLinks`, falls back to
  `trm_ParentTermID`).

The link graph (linked / reverse-pointer rectypes) is **not** precomputed — the
client derives it from `fields.targetTypes` + `structure`.

## Deferred

- `defTranslations` — `language`/`languages` are advertised but labels are not yet
  translated; add a `translations` block (or per-entity `label_<lang>`) when the
  i18n phase starts.

## Files

- `DefinitionSnapshotService.php` (`Heurist\Definitions`) — builds the payload
  from the `def*` tables and manages the on-disk cache.
- `../Controller/DefinitionController.php` — HTTP adapter (conditional GET).
- Wiring: `srv/Runtime/ServiceFactory.php` (`definitionController()`),
  `hserv/controller/api.php` (`$is_def_query` branch).
