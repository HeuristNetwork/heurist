# Record-based entities (plan 28 §6, plan 27d)

A record-based entity is a record type with a name in the URL:
`/api/{db}/{entity}/{id}/{action}` (e.g. `/website/12`, `/page/0/import`, `/website/setup`).
It has no controller or service of its own: `Controller/RecordEditController::executeEntity()`
reads and writes it with the generic record services (`RecordQueryController`,
`RecordSaveService`, `RecordDeleteService`), and the entity adds only what is special to its
record type. There is no search on an entity: `GET /records?q=t:{record type}`.

- `RecordEntityRegistry.php` — entity names → record type constant and the job type of
  `execute` (`ENTITIES`, read by `hserv/controller/api.php` before the database is known);
  local record type ids and handlers (`ServiceFactory::recordEntities()`).
- `RecordEntityHandler.php` — base class with no-op hooks: `beforeSave` (may change the body;
  may write records the saved one needs, not on validate), `saveFailed`, `afterSave`,
  `beforeDelete` / `afterDelete`, `describe` (values for `meta` of `GET /{entity}/{id}`),
  `validate` (`GET /{entity}/{id}/validate`) and `action` (specific actions).

The hooks of an entity's record type run for every write of that type, also through
`/records/{id}`. Common actions (`validate`, `duplicate`) are those of `/records/{id}`;
`POST /{entity}/{id}/execute` starts the entity's job (routed by api.php to `/jobs`).

Entities now: `website` (RT_CMS_HOME) and `page` (RT_CMS_PAGE), handler
`Website/WebsiteEntityHandler.php`. Entities kept in legacy tables (tag, bookmark, user ...)
are served by `/sys` (`System/Query/SystemEntitySchemaRegistry.php`).
