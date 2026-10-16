# Query language → human text, Filter Builder & inline helper — working plan

> Living design/context document. Keep it updated as decisions are made so a
> fresh session can resume without re-deriving everything.
> Created 2026-09-10. Owner: Artem (osmakov). Branch: `h7dev`.

---

## 0. Status / next action

**M1 — `/api/{db}/def/snapshot` endpoint: DONE (2026-09-10).** Verified end to end
against `osmak_mapping` (37/37 integration checks; HTTP 200 / 304 / 404 / 405).

Delivered:
- `srv/Definitions/DefinitionSnapshotService.php` — builds the payload
  from the `def*` tables, caches `def-snapshot.json` in the DB `entity/` dir.
- `srv/Controller/DefinitionController.php` — HTTP adapter, conditional GET (ETag
  = cache-file mtime).
- `srv/Definitions/_README.md`.
- `srv/Runtime/ServiceFactory.php` — `definitionController()` + `entityDirectory`
  passed from `getSysDir('entity')`.
- `hserv/controller/api.php` — `$is_def_query` branch, `'def'` in
  `$publicSearchResources`.
- `hserv/System.php::cleanDefCache()` — deletes `def-snapshot*.json`.
- `tests/DefinitionSnapshotTest.php` — `php tests/DefinitionSnapshotTest.php --db=NAME`.

Field notes from the live run (osmak_mapping: 82 rectypes / 339 fields / 5169
terms / 1181 structure rows): snapshot ≈ 624 KiB uncompressed, `build()` ≈ 30 ms.
`dbId` is `0` for an unregistered DB (concept codes then use `0-<localId>`;
reserved defs still resolve as `2-N`). `meta.languages` reflects whatever stray
`defTranslations` rows exist — cosmetic until the i18n phase.

**`srv/Records/Query/queryVocabulary.json` — DONE (2026-09-10).** `version:"1"`;
operators + phrase templates + `strings.eng` (complete). Section 6 for the shape.
Predicate keyword list is *not* in it — the client hard-copies `KEYWORD_ALIASES` +
`LINK_PREDICATES` from `RecordQueryParser.php`.

**M1 is complete.** Next: **M2 — `HDbDefs` in the `heurist-explorer` repo**,
consuming the snapshot. That repo is separate (only its built bundle lives here,
`hclient/bundles/heurist-explorer/`). Before starting M2:
- keep this plan doc reachable from that session (it lives in *this* repo), and
- grab a dev fixture:
  `curl "http://localhost/heurist/api/<db>/def/snapshot" > def-snapshot.sample.json`
- copy `srv/Records/Query/queryVocabulary.json` into the explorer repo.

---

## 1. Goal

Three capabilities around the Heurist record query language
(`documentation/context_help/searchQueryLanguage.htm`, parser at
`srv/Records/Query/`):

- **Task A** — convert a JSON or plain-text query into a human-readable sentence.
  e.g. `[{"query":{"t":12,"lf:134":[{"t":48}],"f:237":"10443"}}]` →
  *"Find Places linked from Events where type of event is Death"*.
  Output = **plain sentence** (the aligned "raw code under each phrase" layout in
  early notes was only illustrative).
- **Task B** — convert human-entered text into a plain/JSON query. Flat
  sentences first; simple `linked to/from` / `related to` next. **No LLM** for now
  (cannot afford it) — rule-based, deliberately narrow. If Task C is good enough,
  B is lower priority.
- **Task C** — inline helper on the client: as the user types a query in any
  input/textarea, show context-aware hints (rectype dropdown → field dropdown →
  operator dropdown → value), plus a button to open the full Filter Builder
  dialog. `searchBuilder.js` is the existing "regular way".

### Agreed strategy

- **A** — server-side canonical service (`QueryDescriber` in `srv/Records/Query/`),
  exposed via the records/system controller (`detail=describe` or a small
  endpoint). A minimal client-side describer (flat queries only) is acceptable
  for the inline helper's live display; server version is canonical.
- **B** — server-side. `RecordQueryParser::textToJson()` + `QueryValueResolver`
  already do ~80% (keyword syntax → resolved JSON). Missing piece is a small
  prose→keyword preprocessor. Expose parse-without-execute first.
- **C** — client-only, in `heurist-explorer` (framework-free ES classes). Needs a
  clean data layer (`HDbDefs`) and a clean Filter Builder
  (`HFilterBuilder` / `HFilterBuilderItem`) first, then `HFilterInlineHelper`.

### Order of work

`C` (with its prerequisites) → `A` → `B`.
Client work (M2–M5) is done **directly in the `heurist-explorer` repo** — no
standalone test project. Explorer already provides its own ES-class `HBaseWidget`,
`$HR()` i18n and a demo/host page; add a builder demo view there for manual QA.

```
M1  [heurist repo]   /api/{db}/def/snapshot endpoint + snapshot JSON shape
                     + queryVocabulary.json (operators/phrases only)
M2  [explorer repo]  HDbDefs — fetch/parse/store the snapshot; link graph on load
M3  [explorer repo]  HFilterBuilder / HFilterBuilderItem (visual; flat + one
                     linked-subquery level; sort optional)
M4  [explorer repo]  describe() — A-min (flat JSON query → sentence, HDbDefs + vocab)
M5  [explorer repo]  HFilterInlineHelper (empty input → token hints;
                     non-empty → sentence + "open builder" button)
M7  [heurist repo]   server QueryDescriber — canonical A (nested, API-exposed)
                     [can start parallel from M1]
M8  [heurist repo]   Task B — prose preprocessor → parser; link phrases as step 2
```
(There is no M6 — classes are authored in explorer, not copied in.)

---

## 2. Why a new data layer (HDbDefs)

`heurist-explorer` does **not** fetch DB structure yet. The legacy client layer
`hclient/core/utils_dbs.js` (`window.hWin.HEURIST4.dbs`, alias `$Db`, ~4000 lines)
is unusable in explorer: it depends on `HRecordSet`, `HAPI4.EntityMgr`,
`HAPI4.sysinfo.dbconst`, jQuery and other legacy code. It is a **read/query layer
over data that `EntityMgr` fetches** — it does not fetch or persist anything
itself.

So `HDbDefs` is new. It **requests, parses and stores** database definitions from
a new server endpoint, then answers the questions the Filter Builder and describer
need. In the Vite test project it is backed by a static JSON fixture that has the
exact snapshot shape.

---

## 3. Server endpoint: `/api/{db}/def/...`

### 3.1 Rationale

New OpenAPI resource family, database-engine-agnostic, implemented in `/srv`
(PDO via `Heurist\Database\DatabaseInterface`, no mysqli / no legacy `System`
inside services). Mirrors what was already done for `/records` (user data) and
`/sys` (`filter`, `user` — internal user data). `/def` = **database
definitions**.

`/def` will **eventually replace** the legacy `/api/{db}/rty|dty|trm|rst|trl`
routes (which run through `hserv` / `entityScrud.php`). Longer term, `sys` and
`def` both read from record-shaped storage (`sysRecords/sysRecDetails`,
`defRecords/defRecDetails`) with the same structure as user `Records/recDetails`.
**Not now** — for now `/def` reads the existing `defRecTypes` etc. tables, and the
individual `rty`/`dty`/`trm` item reads keep using the old OpenAPI requests.

**`snapshot` is the first and only `/def` service for this milestone.**

### 3.2 Route

```
GET /api/{db}/def/snapshot
    ?lang=eng                (optional; default DB language)
    ?since=<version|etag>    (optional; 304 if unchanged)
```

- Read-only. `allow_anonymous = true` (definitions are already public via
  `rty`/`dty`/`trm`).
- Response: standard modern envelope via `Heurist\Runtime\ApiResponse`
  (`{ ... }` on success; `{status,error,message}` on error).
- **Caching (decided):** persist the generated JSON as
  `def-snapshot[-<lang>].json` in `System::getSysDir('entity')` — the same folder
  as `dbdef_cache.json` (`<filestore>/<db>/entity/`). Serve the file if present,
  otherwise generate + write it. Invalidation is by **deletion**: add
  `fileDelete($entityDir.'def-snapshot*.json')` to `hserv/System.php ::cleanDefCache()`
  (~L1992), which is already called on every structure edit
  (`DbEntityBase`, `dbsImport`, `DbUtils`). Next request regenerates.
- Also send `ETag` (file mtime or structure version) and honour `If-None-Match`
  → `304` for HTTP-level caching; not required for correctness given the
  delete-on-change cache.

### 3.3 Code layout (new)

```
srv/Controller/DefinitionController.php          # HTTP adapter, mirrors SystemQueryController
srv/Definitions/DefinitionSnapshotService.php   # builds the payload from def* tables
srv/Definitions/_README.md
```

- `ServiceFactory::definitionController()` — add alongside `systemQueryController()`
  (`srv/Runtime/ServiceFactory.php`).
- `hserv/controller/api.php` — add a branch like the existing `$is_system_query`
  block (search `'sys'` in that file, ~line 424 and ~line 525):
  ```php
  $is_def_query = ($resource === 'def');
  ...
  if($is_def_query){
      $defType = $requestUri[4] ?? null;      // 'snapshot'
      $controller = ServiceFactory::fromLegacySystem($system)->definitionController();
      $controller->output($req_params, $defType);
      $system->dbclose();
      exit;
  }
  ```
  Also add `'def'` to `$publicSearchResources` for `$allow_anonymous`.
- Reference implementations to copy structure/conventions from:
  - `srv/Controller/SystemQueryController.php`
  - `srv/System/Query/SystemQueryService.php`
  - `srv/System/Query/SystemEntitySchemaRegistry.php`
  - `srv/System/Query/_README.md`
  - envelope/error contract: `srv/Runtime/ApiResponse.php`

### 3.4 Cache invalidation (decided)

No structure-version query needed. The cache file is **deleted** whenever the
structure changes, via `System::cleanDefCache()` (`hserv/System.php` ~L1992),
which already runs on every definition edit / structure import. Extend it:

```php
public function cleanDefCache(){
    $entityDir = $this->getSysDir('entity');
    if ($entityDir) {
        fileDelete($entityDir . 'db.json');
        fileDelete($entityDir . 'dbdef_cache.json');
        foreach (glob($entityDir . 'def-snapshot*.json') ?: [] as $f) { fileDelete($f); }
    }
    ...
}
```

`meta.version` in the payload can just be the file mtime (also used as `ETag`).

---

## 4. Snapshot payload — proposed shape

Goal: **minimum** needed for (a) the Filter Builder dropdowns, (b) client-side
describer (id → name), (c) client-side value resolution (name/label → id) for
Task B-min. Everything else stays on the server.

```jsonc
{
  "meta": {
    "db": "osmak_9a",
    "dbId": 3,                     // rty_OriginatingDBID / registered id, for concept codes
    "version": "1739123456",      // cache-file mtime == ETag (see 3.4)
    "generated": "2026-09-10T12:00:00Z",
    "language": "eng",            // language this snapshot was rendered in
    "languages": ["eng","fre"],   // available; translations deferred (see §8)
    "dbconst": {                  // needed to identify relationship structures / special fields
      "RT_RELATION": 1,
      "DT_PRIMARY_RESOURCE": 247,
      "DT_TARGET_RESOURCE": 248,
      "DT_RELATION_TYPE": 246
    }
  },

  "rectypeGroups": {              // for grouping the rectype dropdown
    "<rtg_ID>": { "name": "...", "order": 1 }
  },
  "fieldGroups": {               // dtg — for grouping the field dropdown
    "<dtg_ID>": { "name": "...", "order": 1 }
  },

  "rectypes": {
    "<rty_ID>": {
      "name":   "Person",         // rty_Name (singular)
      "plural": "Persons",        // rty_Plural (defaults to name when empty)
      "group":  <rty_RecTypeGroupID>,
      "concept":"3-1001",         // "<OriginatingDBID>-<IDInOriginatingDB>", local -> "<dbId>-<localId>"
      "showInLists": true,        // rty_ShowInLists == 1
      "description": "..."        // rty_Description, omitted when the placeholder default
    }
  },

  "fields": {                     // dty — global field definition. "separator" fields omitted.
    "<dty_ID>": {
      "name":    "Given name",    // dty_Name
      "type":    "freetext",      // dty_Type  (see §4.1)
      "group":   <dty_DetailTypeGroupID>,
      "concept": "3-1",
      "vocabulary": 1023,         // first ID in dty_JsonTermIDTree - vocabulary root; omitted if none
      "targetTypes":[12,48]       // dty_PtrTargetRectypeIDs (resource / relmarker); omitted if none
    }
  },

  "structure": [                  // rst — per-rectype field placement. Separator fields excluded.
    {
      "rty":  <rst_RecTypeID>,
      "dty":  <rst_DetailTypeID>,
      "name": "Birth name",       // rst_DisplayName (overrides dty_Name in this rectype)
      "order":  12,               // rst_DisplayOrder
      "req":  "optional"          // rst_RequirementType; consumer drops "forbidden"
    }
  ],

  "terms": {                      // trm — flat map
    "<trm_ID>": {
      "label":   "Capital",       // trm_Label
      "code":    "capital",       // trm_Code
      "concept": "2-10443"        // trm_ConceptCode
      // parent/children come from termlinks, not here
    }
  },
  "termlinks": [                  // trl — hierarchy (also is-a vs part-of if needed later)
    { "parent": <trl_ParentID>, "term": <trl_TermID> }
  ]
}
```

### 4.1 `dty_Type` values (operator-set selector for the builder)

`dty_Type` enum: `freetext`, `blocktext`, `integer`, `date`, `year`, `relmarker`,
`boolean`, `enum`, `relationtype`, `resource`, `float`, `file`, `geo`,
`separator`, `calculated`, `fieldsetmarker`, `urlinclude`. **`separator` is
excluded from the snapshot** (layout-only). Operator phrasing per type: see
`hclient/widgets/search/searchBuilderItem.js` (~L520–620).

### 4.2 Trimmed from the initial draft (per Artem, 2026-09-10)

- No `rectypes[].isRelation` — the client identifies the relationship rectype via
  `meta.dbconst.RT_RELATION`.
- `fields` with `type == "separator"` are dropped entirely (from `fields` **and**
  `structure`).
- `fields[].termTree` (array) → `fields[].vocabulary` (single int = vocabulary
  root term ID, from the first ID in `dty_JsonTermIDTree`; omitted when null).
- `structure[]` rows carry **only** `{rty, dty, name, order, req}` — no
  `rst_FilteredJsonTermIDTree`, `rst_PtrFilteredIDs` or per-rectype group. No
  per-rectype term/pointer narrowing; the builder uses the global field's
  `vocabulary` / `targetTypes`.

### 4.3 Link graph

Do **not** precompute the linked/reverse-pointer graph in the snapshot. `HDbDefs`
computes it from `fields.targetTypes` + `structure` on load — this is exactly what
`$Db.rst_links()` does (`utils_dbs.js` ~L1575–1660): builds `direct`, `reverse`,
`rel_direct`, `rel_reverse` maps keyed by rectype.

---

## 5. HDbDefs — client class (method surface)

Derived from how `searchBuilder.js` / `searchBuilderItem.js` use `$Db` today
(`$Db.rty`, `$Db.dty`, `$Db.rst`, `$Db.trm`, `$Db.trm_getLabel`,
`$Db.trm_TreeData`, `$Db.getBaseFieldInstances`,
`$Db.createRectypeStructureTree_new`, `getLocalID`/`getConceptID`).

Framework-free ES class. No `window.hWin`, no jQuery. Constructed with the parsed
snapshot (or a fetch URL + fetch fn injected).

```
class HDbDefs {
  static async load(url, { lang, fetchFn }): HDbDefs        // GET /api/{db}/def/snapshot
  constructor(snapshotJson)

  // meta
  dbId(): number
  version(): string
  dbconst(name): number

  // record types
  rectypes(): Array<{id,name,plural,group}>                 // for dropdown, group-sorted
  rectype(id): {id,name,plural,group,concept} | null
  rectypeIdByName(text): number|number[]|null               // singular/plural, exact then unique-partial
  rectypeName(id, {plural}): string

  // fields  (structure gives per-rectype name/order/req; type/vocabulary/targetTypes come from the global field)
  fields(rtyId): Array<{id,name,type,group,order,req}>       // structure-merged, "forbidden" excluded, order-sorted
  field(rtyId, dtyId): {id,name,type,group,order,req,vocabulary?,targetTypes?} | null
  fieldGlobal(dtyId): {...}                                  // no rectype context
  fieldIdByName(rtyIds, text): number|number[]|null          // dty_Name or rst_DisplayName, scoped to rtyIds
  fieldName(rtyId, dtyId): string
  fieldType(rtyId, dtyId): string

  // enum / relation terms
  vocabRoot(dtyId): number|0                                 // fields[dty].vocabulary
  termTree(rootId): tree|flat                                // via termlinks
  term(id): {id,label,code,concept} | null
  termIdByLabel(rootId, label): number|null                  // label or code, within vocab, dotted-path aware
  termLabel(id): string
  termDescendants(rootIds): number[]

  // link graph (computed on load, mirrors $Db.rst_links)
  linkedRectypes(rtyId, {direction:'to'|'from', relation:false}): number[]
  pointerFieldsBetween(fromRty, toRty): number[]             // candidate lt/lf field ids

  // concept codes
  localId(kind, conceptCode): number                         // kind: 'rty'|'dty'|'trm'
  conceptId(kind, localId): string
}
```

Both directions of resolution live here: **id → name** (Task A) and
**name/label → id** (Task B-min, builder value entry).

---

## 6. `queryVocabulary.json` — shared resource  ✅ written

Canonical file: **`srv/Records/Query/queryVocabulary.json`** (done, `version:"1"`).
`heurist-explorer` keeps an identical copy; a CI/lint check keeps them in sync.

Holds **operators + phrase templates + their translations**. The predicate keyword
vocabulary is *not* here — the client **hard-copies** `KEYWORD_ALIASES` and
`LINK_PREDICATES` from `srv/Records/Query/Parser/RecordQueryParser.php` (~L26–40)
into a small JS constant (`queryPredicates.js`). The PHP parser stays hardcoded —
no server-side dependency on this file.

Top-level keys as written:

- `fieldKinds` — `dty_Type` → operator group
  (`text|number|date|enum|term|record|file|geo|bool`).
- `headerKinds` — `title|url|notes|added|modified|id|type|tag` → operator group.
- `operators` — per group, `[{ token, input, i18nKey, pattern?, whole? }]` where
  `token` is the value **prefix the parser understands** (`""`, `=`, `==`, `>`,
  `>=`, `<`, `<=`, `<>`, `><`, `-`, `@`, `@+`, `@-`), `input` is the widget the
  builder should show (`text|number|date|term|record|range|wkt|bool|tag|tags|
  none`), and `pattern` is a value template for builder-only shorthands
  (`starts_with` → `{v}%`, `between` → `{a}<>{b}`).
- `common` + `commonAppliesTo` — the `NULL` / `-NULL` (has value / has no value)
  operators appended to most kinds; `whole:true` means the token *is* the value,
  no user input.
- `phrases` — describer (Task A) sentence templates as i18n keys with `{…}`
  placeholders (`phrase.find` = `"Find {rectype}"`, `phrase.linked_from`,
  `phrase.field_cond` = `"{field} {op} {value}"`, …).
- `strings` — `{ "<lang>": { "<i18nKey>": "text" } }`. **`eng` is fully
  populated.** Per Answer 5, translations live **in this JSON**, not a separate
  i18n system; explorer's `describe()` / builder look strings up here, falling
  back to `eng`. Add `fre`, etc. as sibling blocks.

Operator phrasing was seeded from `hclient/widgets/search/searchBuilderItem.js`
(~L518–625) and the query-language doc's "Values and comparison operators" table.

---

## 7. Client implementation (in `heurist-explorer`)

**Decided: no standalone test project.** The classes are authored directly in the
`heurist-explorer` repo, extending its ES-class `HBaseWidget`, using its `$HR()`
i18n and `localization_*.txt`. Add a builder demo view to explorer's host page for
manual QA (input bound to `HFilterInlineHelper`, "open builder" button →
`HFilterBuilder` dialog, live panels for raw JSON query + human sentence). Unit
tests use explorer's existing test setup.

Files (in explorer's source tree):
```
HDbDefs.js
queryPredicates.js        (hard copy of KEYWORD_ALIASES + LINK_PREDICATES)
HFilterBuilder.js
HFilterBuilderItem.js
HFilterInlineHelper.js
describe.js                (A-min: flat JSON query -> sentence)
parse.js                   (B-min: prose -> keyword text -> {json})
assets/queryVocabulary.json (copy of srv/Records/Query/queryVocabulary.json)
```

Dependency-injection contract for the three widgets (freeze before coding):
`dbdefs` (HDbDefs), `vocabulary` (parsed `queryVocabulary.json` — includes its own
`strings` translations, so no separate i18n dep), `lang`,
`onChange(jsonQuery, textQuery)`, optional `describe(json)` / `parse(text)` hooks
(host swaps in the server versions later; explorer supplies client-side minimal
ones for now).

---

## 8. Inline helper behaviour (Task C UX — agreed)

- Bindable to **any** `<input>` / `<textarea>` (`HFilterInlineHelper` +
  `HFilterBuilder` pair).
- **While typing** → live token hints only (state machine:
  `rectype → field | header-keyword → operator → value → space → repeat`).
  **No human-readable rendering during typing** (D6). This is the primary dev
  path — simplest to build and exercises everything.
- **After a JSON query exists** — produced by parsing the typed text, or by the
  builder — render the generated human-readable sentence (`describe()`) next to
  the raw query so the user can compare/confirm. Post-parse step, not
  keystroke-live.
- A button next to the input always opens the full `HFilterBuilder` dialog for the
  "regular way".

---

## 9. i18n

- **`localization_*.txt` (explorer `$HR`) is for UI chrome only** — buttons,
  labels, tooltips, messages. Query-language operator/phrase wording is a
  near-static, versioned dataset and stays **with the data it describes**:
  inside `queryVocabulary.json` under `strings.<lang>` (Answer 5 / D1). `eng` is
  complete; consumers fall back to `eng` for a missing key/lang. So the builder
  and `describe()` read operator/phrase text straight from the parsed vocabulary
  object — no `$HR` call for these.
- Rectype / field / term labels: from `defTranslations` — **not currently in the
  snapshot**. Add a `translations` block (or per-entity `label_<lang>`) to the
  snapshot builder when the i18n phase starts. English-only for v1;
  `meta.languages` already advertises availability.

---

## 10. Decisions log

- **D1 (resolved 2026-09-10)** — `queryVocabulary.json` = operators + phrase
  templates + their `strings.<lang>` translations (Answer 5). Client
  **hard-copies** `KEYWORD_ALIASES` + `LINK_PREDICATES` from
  `RecordQueryParser.php`. PHP parser stays hardcoded, no JSON dependency.
  Written 2026-09-10 (`version:"1"`, `eng` complete).
- **D2 (resolved 2026-09-10)** — snapshot cached as
  `def-snapshot[-<lang>].json` in `getSysDir('entity')` (beside
  `dbdef_cache.json`); invalidated by deletion in `System::cleanDefCache()`;
  regenerated on next request. `ETag` = file mtime.
- **D3 (resolved 2026-09-10)** — HFilterBuilder v1 scope: flat predicates +
  single-level linked subquery, sort optional. **Defer** rules/expansion,
  relationship-marker constraints, multi-rectype.
- **D4 (resolved 2026-09-10)** — no standalone test project; client classes are
  written directly in `heurist-explorer`.

- **D5 (resolved 2026-09-10)** — `meta.version` / `ETag` = cache-file **mtime**.
- **D6 (resolved 2026-09-10)** — the inline helper shows **no** human-readable
  text while the user is typing (token hints only). The describer runs **after**
  a JSON query is produced (from parse, or from the builder), to show the
  generated sentence for the user to compare/confirm. So `describe()` is a
  post-parse step, not keystroke-live — client A-min is fine for it; server
  `QueryDescriber` is canonical and used for nested queries.
- **D7 (resolved 2026-09-10)** — the `heurist-explorer` source repo is available
  to work in directly for M2+.

### Still open

- none.

---

## 11. Key file references

### Server — query language (exists)
- `srv/Records/Query/Parser/RecordQueryParser.php` — text↔JSON, normalize,
  validate; `KEYWORD_ALIASES`, `LINK_PREDICATES`, `isKnownPredicate()`.
- `srv/Records/Query/Compiler/QueryValueResolver.php` — name/label → local id
  (rty, dty, trm, user); the **forward** direction. Reverse (id → name) for
  Task A does not exist yet.
- `srv/Records/Query/Compiler/RecordPredicateCompiler.php`,
  `FieldPredicateCompiler.php` — predicate → SQL.
- `srv/Controller/RecordQueryController.php` — `/api/{db}/records` adapter;
  `buildRequest()` → `QueryBuilder::normalize()`.
- `documentation/context_help/searchQueryLanguage.htm` — full language reference.

### Server — patterns to copy for `/def`
- `srv/Controller/SystemQueryController.php`
- `srv/System/Query/{SystemQueryService,SystemEntitySchemaRegistry,SystemQueryBuilder}.php`
- `srv/System/Query/_README.md`
- `srv/Runtime/ServiceFactory.php` — controller factory (`systemQueryController()`).
- `srv/Runtime/ApiResponse.php` — success/error envelope.
- `hserv/controller/api.php` — router; `$is_system_query` branch (~L424, ~L525),
  `$entities` map (~L107), `$publicSearchResources` (~L492).
- `documentation/modern-records-workflow.md` — `/srv` scope & legacy-boundary
  rules (services must not `require`/`include`, no mysqli/System inside services).

### Client — existing (legacy, to be replaced, use as behaviour reference only)
- `hclient/widgets/search/searchBuilder.js` (~1900 ln) — the current visual
  builder.
- `hclient/widgets/search/searchBuilderItem.js` (~1050 ln) — field/operator/value
  row; operator phrasing tables ~L520–620.
- `hclient/core/utils_dbs.js` (~4000 ln) — `$Db`; accessors `rty`/`dty`/`rst`/
  `trm` (~L1450–1750), `rst_idx2()` (~L1547), `rst_links()` (~L1575),
  `trm_TreeData()` (~L2134), `trm_getLabel()` (~L2510),
  `getBaseFieldInstances()` (~L3344), `createRectypeStructureTree_new()` (~L423).
- `hclient/core/utils_query.js` — `parseHeuristQuery`, `composeHeuristQuery2`,
  `createFacetQuery` (legacy client query utils).
- `hclient/widgets/HBase/HBaseWidget.js` — the **jQuery** base (NOT the explorer
  one); `hclient/widgets/HFilter/HFilter.js` — unrelated existing widget (saved-
  search runner); mind the `HFilter` vs `HFilterBuilder` naming.

### Client — target
- `hclient/bundles/heurist-explorer/` — built bundle only; source is a separate
  repo. Framework-free ES modules, own i18n (`$HR` / `localization_*.txt`
  `#key#value`), own `InlineHelp` class, mounts on `<main id="heurist-explorer">`.
- `hclient/modules/README.md` — describes the independent Vite module pattern and
  the reserved future `@heurist/client-core` shared package.

---

## 12. Changelog

- 2026-09-10 — initial draft (design discussion with Artem). Order fixed as
  C→A→B; `/api/{db}/def/snapshot` chosen as first `/def` service; snapshot shape
  drafted with gaps in the initial field list flagged.
- 2026-09-10 — decisions D1–D4 recorded: vocab JSON = operators/phrases only +
  hard-copied predicate constants on client; snapshot cached in `entity/` dir and
  invalidated via `cleanDefCache()`; HFilterBuilder v1 scope confirmed; no test
  project (client work goes straight into `heurist-explorer`). Milestones
  renumbered (M6 removed).
- 2026-09-10 — D5–D7: `meta.version`/`ETag` = file mtime; inline helper shows no
  human-readable text while typing (describe() is a post-parse compare step);
  explorer repo available for M2+. All open questions closed — M1 ready to build.
- 2026-09-10 — **M1 endpoint implemented and verified** (`DefinitionController` +
  `DefinitionSnapshotService`, wired into `ServiceFactory` / `api.php` /
  `cleanDefCache`, `tests/DefinitionSnapshotTest.php`). `queryVocabulary.json`
  still outstanding within M1.
- 2026-09-10 — service moved `Heurist\System\Definitions` → `Heurist\Definitions`
  (`srv/Definitions/`, a peer of `Records`/`System`). Payload trimmed (§4.2):
  no `rectypes.isRelation`; `separator` fields excluded; `fields.termTree[]` →
  `fields.vocabulary` (int); `structure[]` = `{rty,dty,name,order,req}` only.
  41/41 checks pass; snapshot ≈ 603 KiB for osmak_mapping.
- 2026-09-10 — **`srv/Records/Query/queryVocabulary.json` written** (`version:"1"`):
  `fieldKinds`/`headerKinds`, `operators` per kind, `common` NULL ops, `phrases`,
  `strings.eng` (46 keys, all referenced keys covered). **M1 complete** — next is
  M2 (HDbDefs) in the heurist-explorer repo.
