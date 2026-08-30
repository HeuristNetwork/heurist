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

The public definition contract currently documents GET only. Record collection searches support GET and POST, while structured map/timeline searches, login/logout, and annotation writes use POST for their specific operations.

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

The public collection contract is JSON-only and documents `query`/`q`, `ids`, `fields`, `detail`, `resolveDetails`, `rules`, `sort`, `filter`, `limit`, and `offset`. A supplied `sort` replaces the query's top-level sort. `filter` is an additional predicate or group ANDed with the complete base query. GET is convenient for compact searches; POST accepts the same search as a JSON body and avoids URL-length restrictions. Existing alternative export formats remain implemented but are not yet part of the stable OpenAPI contract.

Existing visibility behavior is retained: an inaccessible record query may return HTTP 200 with an empty `records` array.

### Direct and linked record details

```text
POST /api/<database>/records
```

The records collection operation retrieves selected detail values for either a query or an explicit set of source records. It accepts the existing Heurist short field-path notation used by facet and thematic-map definitions. For example:

```json
{
  "ids": [101, 102, 103],
  "fields": [
    "10:237",
    "10:lt240:48:237",
    "10:lt240:48:1160"
  ],
  "resolveDetails": true
}
```

A direct path such as `10:237` reads detail type 237 from each visible source record. A linked path such as `10:lt240:48:237` follows the encoded link path and returns detail type 237 from the matching linked records. Fields sharing the same path are grouped internally so their linked IDs can be retrieved together.

The operation is available anonymously for public records and also honours the current session or JWT. Missing or inaccessible source records are omitted. Linked records and individual fields are filtered through the same record and field visibility rules as normal record retrieval.

The response follows the Records API envelope but uses full field paths as keys under each record's `details` object:

```json
{
  "records": [
    {
      "rec_ID": "101",
      "rec_RecTypeID": "10",
      "rec_Title": "Source record",
      "details": {
        "10:237": ["value"],
        "10:lt240:48:237": [{
          "value": "9463",
          "trm_ID": "9463",
          "trm_Label": "Commercial",
          "trm_Code": "Com",
          "path": {"id": "1", "recordIDs": ["101", "204874"]}
        }]
      }
    }
  ],
  "meta": {
    "database": "mydb",
    "entity": "records",
    "fields": {
      "headers": ["rec_ID", "rec_RecTypeID", "rec_Title"],
      "details": [
        {"dty_ID": "237", "dty_PathCode": "10:237", "dty_Name": "...", "dty_Type": "..."},
        {"dty_ID": "237", "dty_PathCode": "10:lt240:48:237", "dty_Name": "...", "dty_Type": "..."}
      ]
    },
    "paths": {"1": "10:lt240:48"}
  },
  "pagination": {"total": 1, "offset": 0, "limit": 1000, "self": "..."}
}
```

Each requested field contains an ordered array of values. `resolveDetails=false` is the default and returns stored values without optional type-specific metadata. With `resolveDetails=true`, enum/relation values add `trm_*`, resource values add `rec_*`, and file values retain the existing `ulf_*` information. Every linked value contains `path`; requested fields with no visible value may be absent. Pagination always applies to the top-query records, including an explicit `ids` request.

The legacy `record_search.php?a=links_details` controller action is unchanged and remains a compatibility mechanism for existing internal clients.


## Map and timeline presentation API

The map API exposes stable, engine-neutral representations and geographic data. It does not return Leaflet objects, runtime layer IDs, jQuery widget state, or `window.hWin` configuration. Anonymous requests return only records visible under normal Heurist database and record permissions. Session or JWT authentication may expose additional records.

### MapDocument and MapLayer

```text
GET /api/<database>/map/document/<recordId>
GET /api/<database>/map/layer/<recordId>
```

A MapDocument response uses `format: heurist-map-document` and preserves the original map bookmark in `mapBookmark.raw`. Recognised bookmarks also include parsed bounds or point coordinates. CRS and world-base-map terms include local term ID, code, and label. Layer references remain in stored `DT_MAP_LAYER` order. Minimum and maximum zoom values retain existing Heurist semantics and are not automatically interpreted as Leaflet zoom levels.

A MapLayer response uses `format: heurist-map-layer`. It separates data acquisition (`source`), symbology (`style`), temporal settings (`timeline`), and display behaviour (`options`). Supported source types include `heurist-query`, `record`, `inline-geojson`, `remote-geojson`, `tile`, `image`, `iiif`, and `geotiff`.

MapDocument and MapLayer presentation routes are GET-only. File-backed `remote-geojson` sources use the common `/map/data/<recordId>` endpoint rather than format-specific legacy controllers.

### Heurist record/query GeoJSON

```text
GET  /api/<database>/map/<recordId>
GET  /api/<database>/map?q=<encoded-query>
POST /api/<database>/map
```

The response is a GeoJSON `FeatureCollection` with a `meta` object. POST is accepted for the ordinary record/query route only and is intended for serialisable queries too large or structured for a URL. It accepts a query plus `limit`, `offset`, and `simplify`.

```json
{
  "query": {"t": 10, "sort": "rec_Title"},
  "limit": 1000,
  "offset": 0,
  "simplify": true
}
```

### File-backed map datasource

```text
GET /api/<database>/map/data/<recordId>?format=geojson
GET /api/<database>/map/data/<recordId>?format=rawfile
GET /api/<database>/map/data/<recordId>?format=source
```

This route handles datasource records backed by KML, KMZ, CSV, TSV, GeoJSON, or SHP/DBF/SHX files. `format=geojson` converts the source when required; a GeoJSON source is returned without conversion. `format=rawfile` returns a ZIP archive containing the original source file(s), optionally with metadata. `format=source` proxies the original representation; shapefiles remain ZIP archives because they consist of multiple related files.

Datasource conversion simplifies complex geometries by default; use `simplify=0` to disable simplification. `metadata=1` applies to `rawfile`. The `/map/data/...` route is **GET-only**; POST is reserved for ordinary map record/query searches.

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
    "fields": {"headers": ["rec_ID", "rec_RecTypeID", "rec_Title"], "details": []}
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

The `fields` parameter adds record headers, numeric detail type IDs, type-qualified direct fields and linked field paths. If it is omitted, only `rec_ID`, `rec_RecTypeID` and `rec_Title` are returned. `detail=ids` overrides `fields` and returns the separate IDs DTO rather than record objects:

```json
{"ids": [101, 102], "total": 2, "offset": 0, "limit": 1000, "resultToken": null}
```

Missing or inaccessible records return HTTP 200 with an empty result.

### Records field and item response rules

For collection record responses, `rec_ID`, `rec_RecTypeID` and `rec_Title` are always returned. Details are returned only when requested through `fields`. A single-record path retains its established non-paginated contract. Missing or inaccessible records return HTTP 200 with an empty `records` array.
