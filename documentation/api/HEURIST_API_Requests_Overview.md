# Heurist API Requests — Overview

## Entry point and URI forms

API requests are routed through the centralized root router and internally dispatched to the selected installation's `hserv/controller/api.php`.

Primary forms:

```text
/api/<database>/<resource>/<selector>
/<installation>/api/<database>/<resource>/<selector>
```

Examples:

```text
/api/my_db/rectypes
/heurist/api/my_db/records?q=ids:1,2,3
```

The compact unversioned `/api` form uses the configured production installation. Direct controller-query forms remain compatibility mechanisms and are not the preferred public interface.

## Methods

The generic entity router maps HTTP methods to internal actions:

| HTTP method | Internal action |
|---|---|
| `GET` | `search` |
| `POST` | `add` |
| `PUT` / `PATCH` | `save` |
| `DELETE` | `delete` |

The public definition contract currently documents GET only. Records and IIIF are GET-only through this entry point. Annotation writes exist for the Mirador workflow but are outside this read-API overview.

## Definition API

The first modernized group contains four resources:

| Descriptive | Compact |
|---|---|
| `rectypes` | `rty` |
| `fields` | `dty` |
| `terms` | `trm` |
| `recstructure` | `rst` |

Both names are equivalent aliases.

Collections return:

```json
{
  "items": [],
  "meta": {"database": "my_db", "entity": "rectypes"},
  "pagination": {"total": 0, "offset": 0, "limit": 1000, "self": "...", "next": null}
}
```

Single record types, fields and terms return their definition object directly. Collection filters use native entity field names such as `rty_ID`, `dty_Type`, or `trm_ParentTermID`; there is no generic public `id` query parameter.

Record structure is addressed semantically:

```text
/recstructure/<recordTypeId>
/recstructure/<recordTypeId>/<fieldId>
```

The first returns all fields for a record type; the second returns one field selected by record type and detail type. Internal `rst_ID` values are not part of this public route design.

The existing `details` projections remain available because the same `DbDefXXX` classes serve internal Heurist clients.

## Records

```text
/api/<database>/records
/api/<database>/records/<recordId>
```

Anonymous requests return only visible public records. Authenticated session or JWT requests may return additional records according to Heurist permissions.

The initial public contract is JSON-only and documents `q`, `w`, `ids`, `detail`, `limit`, and `offset`. Existing alternative export formats remain implemented but are not yet part of the stable OpenAPI contract.

Existing visibility behavior is retained: an inaccessible record query may return HTTP 200 with an empty `records` array.


## Map and timeline presentation API

The map API exposes stable, engine-neutral representations and geographic data. It does not return Leaflet objects, runtime layer IDs, jQuery widget state, or `window.hWin` configuration. Anonymous requests return only records visible under normal Heurist database and record permissions. Session or JWT authentication may expose additional records.

### MapDocument and MapLayer

```text
GET /api/<database>/map/document/<recordId>
GET /api/<database>/map/layer/<recordId>
```

A MapDocument response uses `format: heurist-map-document` and preserves the original map bookmark in `mapBookmark.raw`. Recognised bookmarks also include parsed bounds or point coordinates. CRS and world-base-map terms include local term ID, code, and label. Layer references remain in stored `DT_MAP_LAYER` order. Minimum and maximum zoom values retain existing Heurist semantics and are not automatically interpreted as Leaflet zoom levels.

A MapLayer response uses `format: heurist-map-layer`. It separates data acquisition (`source`), symbology (`style`), temporal settings (`timeline`), and display behaviour (`options`). Supported source types include `heurist-query`, `record`, `inline-geojson`, `remote-geojson`, `tile`, `image`, `iiif`, and `geotiff`.

### GeoJSON

```text
GET  /api/<database>/map/<recordId>
GET  /api/<database>/map?q=<encoded-query>
POST /api/<database>/map
```

The response is a GeoJSON `FeatureCollection` with a `meta` object. POST accepts a serialisable query plus `limit`, `offset`, and `simplify`. GET is intended for simple or URL-safe encoded queries.

```json
{
  "query": {"t": 10, "sort": "rec_Title"},
  "limit": 1000,
  "offset": 0,
  "simplify": true
}
```

### Timeline

```text
GET  /api/<database>/time?q=<encoded-query>
POST /api/<database>/time
```

Timeline responses use `format: heurist-timeline` and contain normalised `recordId`, `title`, `start`, `end`, and `group` values. POST accepts a serialisable query plus `limit` and `offset`.

In the initial implementation, `meta.total` is the number of returned features or timeline items, not the complete pre-pagination search count.

## Authentication

The API supports:

1. cookie-backed Heurist sessions;
2. JWT bearer tokens.

Bearer tokens are supplied as:

```text
Authorization: Bearer <token>
```

External integrations should prefer JWT. Browser session login and logout are available at:

```text
POST /api/<database>/login
POST /api/<database>/logout
```

## IIIF

Public IIIF Presentation API v3 routes include:

```text
/api/<database>/iiif/manifest/<identifier>
/api/<database>/iiif/canvas/<identifier>
/api/<database>/iiif/page/<identifier>
/api/<database>/iiif/annotation/<identifier>
/api/<database>/iiif/annotations/<identifier>
```

Successful output is direct IIIF JSON and is not wrapped in a Heurist response envelope.

## Annotation reads

Public annotation reads include:

```text
/api/<database>/annotations/pages?uri=<canvas-url>
/api/<database>/annotations/<annotationId>
/api/<database>/annotations/<manifestRecordId>/pages?uri=<canvas-url>
/api/<database>/annotations/<manifestRecordId>/<annotationId>
```

## Errors and empty results

API item lookups return HTTP 404 using `System::errorExitApi()` when the semantic item is not found. Collection searches with no matches return HTTP 200 with an empty collection.

Normal non-API `entityScrud` clients keep their existing response behavior.

## Test collection

`HeuristPostmanCollectionV1.json` is the executable beta test suite. It covers routing, public access, session and JWT authentication, the modern definition response envelope, semantic record-structure routes, and negative authentication cases.


## API error responses

API errors use HTTP status codes and the existing Heurist response-status constants from `consts.php`:

```json
{
  "status": 404,
  "error": "notfound",
  "message": "Definition not found"
}
```

The `error` value is one of the existing `HEURIST_*` status values, such as `invalid`, `notfound`, `denied`, `blocked`, `database`, or `system`. Internal diagnostics such as `sysmsg` are not exposed by the public API. Ordinary non-API Heurist responses are unchanged.


## Public records response

`GET /api/<db>/records` returns JSON with three top-level sections:

```json
{
  "records": [],
  "meta": {
    "database": "mydb",
    "entity": "records",
    "fields": {"headers": [], "details": []}
  },
  "pagination": {
    "total": 0,
    "offset": 0,
    "limit": 1000,
    "self": "...",
    "next": null
  }
}
```

The `fields` parameter selects record header fields and numeric detail type IDs. If it
is omitted, the default headers `rec_ID`, `rec_RecTypeID` and `rec_Title` are returned
with all details. `detail=ids` overrides `fields` and returns IDs rather than record
objects. Missing or inaccessible records return HTTP 200 with an empty `records` array.

### Records field and item response rules

For normal records object responses, `rec_ID` and `rec_RecTypeID` are always returned. When `fields` is omitted, `rec_Title` and all visible details are also returned. A single-record path is not paginated: its response contains `records` and `meta.self`, but no `pagination` section. Missing or inaccessible records return HTTP 200 with an empty `records` array.
