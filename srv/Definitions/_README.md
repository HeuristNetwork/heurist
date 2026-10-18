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
(hash of the complete response, including translation-language settings) and answers
`304` to a matching `If-None-Match`. Responses use `Cache-Control: private,
no-cache` and negotiated gzip compression; browsers store the JSON and revalidate
it on each load. Weak validators cover both compressed and uncompressed responses.

## Caching

`DefinitionSnapshotService` writes `def-snapshot.json` into the database
`entity` system directory (beside `dbdef_cache.json`). It is served from disk
when present and rebuilt on the next request after removal.
`hserv/System.php::cleanDefCache()` deletes `def-snapshot*.json` on every
structure edit / import, so no explicit version query is needed.

## Payload

See `documentation/development/query-language-filter-builder-plan.md` §4 for the
full shape. Summary:

- `meta` — `db`, `format` (2; a cached snapshot of an older format is rebuilt),
  `dbId` (registered ID, for concept codes), `version` (cache-file mtime; HTTP ETag hashes the complete response),
  `generated`, `language`, `languages`, `dbconst`
  (`RT_RELATION`, `DT_PRIMARY_RESOURCE`, `DT_TARGET_RESOURCE`, `DT_RELATION_TYPE`).
- `rectypeGroups`, `fieldGroups` — `{id: {name, order}}`.
- `rectypes` — `{id: {name, plural, group, concept, showInLists, description?}}`.
- `fields` — `{id: {name, type, group, concept, vocabulary?, targetTypes?}}`.
  `type` = `dty_Type`; layout-only `separator` fields are omitted. `vocabulary`
  is the vocabulary root term ID (integer) for enum / relation fields, absent
  otherwise.
- `structure` — `[{rty, dty, name, order, req, max}]` (`rst` per-rectype placement;
  `max` = `rst_MaxValues`, 0 = unlimited (repeatable);
  consumer must drop `req == "forbidden"`; separator fields excluded). No
  per-rectype term/pointer narrowing — the builder uses the global field's
  `vocabulary` / `targetTypes`.
- `terms` — `{id: {term, code?, conceptid, desc?, domain?, inverse?}}` (snapshot format 3; the names of
  the report field tree and the Smarty term subfields; `HDbDefs.term()` adds `internalid`).
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
  from `DefinitionLookup` and manages the on-disk cache.
- `../Controller/DefinitionController.php` — HTTP adapter (conditional GET).
- Wiring: `srv/Runtime/ServiceFactory.php` (`definitionController()`),
  `hserv/controller/api.php` (`$is_def_query` branch).

## DefinitionLookup

`DefinitionLookup.php` is the **only** srv class that queries the definition tables
(plan 15, heurist-explorer `docs/development/15 DONE Data-Access-Layer-Plan.md`):
defRecTypes(+Groups), defDetailTypes(+Groups), defRecStructure, defTerms,
defTermsLinks, defTranslations, defCalcFunctions, sysWorkflowRules. A move of the
definitions to other tables changes this class only. Do not add definition queries
anywhere else — add a method here.

- `DefinitionLookup::shared($database)` — one instance and one cache per connection.
- **Concept codes** (agreed 2026-10-13): `conceptCode()` / `rectypeConcept()` write
  `<origin db>-<origin id>`, else `<registered id>-<local id>` (`0-<id>` unregistered);
  `localId(kind, code)` reads every form — a value without `<db>-` is a local id,
  `0-<id>` and `<registered id>-<id>` are local ids, other codes by origin.
- **Record types / fields**: `rectype`, `rectypeName`, `rectypes`, `rectypeIdByName`,
  `findRectypes`, `rectypeGroups`; `field`, `fieldType`, `fields`, `fieldConstraints`,
  `findFields`, `fieldOwnerTypes`, `fieldGroups` (both tables loaded once).
- **Structure**: `structureRows(rty)` (edit structure, per record type),
  `structureFields`, `structureNames`, `fieldName`, `structure()` (snapshot),
  `calculatedFields(rty)`, `workflowRule(rty, stage)`.
- **Terms** (loaded per id): `term`, `terms(ids)`, `allTerms` (snapshot only),
  `termHierarchyLabel`, `termsByLabelOrCode`, `termDescendants` (term links, or the
  parent column when a database has none), `termInverse`, `termLinks` (snapshot).
- **Translations**: `languages`, `translations(source, ids, language)`.
- User and group names are `Users/UserGroupRepository` (no longer here).

Terms are `{internalid, term, code, conceptid, desc, parent, inverse, domain}`.
Database settings (registered id, version) are `Runtime/DatabaseSettings`.
Test: `tests/DefinitionLookupTest.php`.

