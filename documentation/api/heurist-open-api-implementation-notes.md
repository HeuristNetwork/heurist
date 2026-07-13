# Heurist Open API: implementation notes

This document records the implemented read-API baseline exposed through `hserv/controller/api.php`.

## Scope of the first modernization phase

The modern definition response contract applies only to four database-definition resources:

| Descriptive route | Compact alias | Internal entity |
|---|---|---|
| `rectypes` | `rty` | `DefRecTypes` |
| `fields` | `dty` | `DefDetailTypes` |
| `terms` | `trm` | `DefTerms` |
| `recstructure` | `rst` | `DefRecStructure` |

The descriptive and compact routes are equivalent. Neither form is deprecated.

Other entity routes, including users, groups, databases, tags and reminders, retain their existing behavior and are not part of this response-format change.

## Definition collection response

A collection returns three sections:

```json
{
  "items": [],
  "meta": {
    "database": "my_database",
    "entity": "rectypes"
  },
  "pagination": {
    "total": 0,
    "offset": 0,
    "limit": 1000,
    "self": "https://example.net/api/my_database/rectypes?offset=0&limit=1000",
    "next": null
  }
}
```

`meta.entity` always uses the canonical descriptive name, even when the compact alias was requested. `next` is an absolute URL preserving the request filters and is `null` on the final page.

An empty collection is successful and returns HTTP 200 with `items: []`.

## Single definition response

A semantic item route returns the definition object directly, without an envelope:

```text
/api/{database}/rectypes/{recordTypeId}
/api/{database}/fields/{fieldId}
/api/{database}/terms/{termId}
```

A missing item returns HTTP 404 through `System::errorExitApi()`.

A query-filtered collection remains a collection even when it matches one item.

## Record structure semantics

`rst_ID` is an internal primary key and is not used as the public identity.

```text
/api/{database}/recstructure/{recordTypeId}
/api/{database}/rst/{recordTypeId}
```

return the complete field structure for a record type as a collection.

```text
/api/{database}/recstructure/{recordTypeId}/{fieldId}
/api/{database}/rst/{recordTypeId}/{fieldId}
```

return one structure entry selected by `rst_RecTypeID` and `rst_DetailTypeID`.

## Internal compatibility

`DbDefXXX` classes and all existing `details` modes remain unchanged because these classes are used throughout Heurist, not only by the public API.

The API asks `DbEntitySearch` to retain its normal internal result and prepares the public envelope at the `entityScrud.php` controller boundary. The temporary `restapi=2` marker distinguishes this path from the legacy simplified `restapi=1` output. This numeric marker should eventually be replaced by an explicit API response context while keeping `restapi` boolean.

Normal non-API `entityScrud.php` responses remain unchanged.

## Records

The records endpoint keeps its existing JSON representation. The initial public specification does not document alternative `format`/`fmt` output, `rt`, or `sort`. Record response schemas require a separate compatibility review before implementation changes are made.

A request for a private or otherwise invisible record may continue to return a successful records response with an empty `records` array; this behavior is intentionally unchanged.

## IIIF

Successful IIIF responses are direct IIIF Presentation API v3 resources. They must never be wrapped in the Heurist definition envelope.

Supported resources include `manifest`, `canvas`, `page`, `annotation`, and `annotations`. The `omit_annotation_pages=1` option is documented where applicable.

## Authentication

External API clients should use JWT bearer authentication. Existing cookie-backed Heurist sessions remain supported for browser and compatibility workflows. Public records, IIIF resources, and the documented annotation reads may be accessed anonymously subject to their visibility rules.

## Harvester compatibility

The definitions harvester has been updated to consume:

```php
$response['items'];
$response['pagination']['total'];
$response['pagination']['next'];
```

It follows the API-provided `next` URL rather than issuing a separate count request and constructing offsets itself.

## Synchronized artifacts

Changes to these four definition endpoints must update together:

1. API implementation;
2. definitions harvester;
3. OpenAPI YAML;
4. Postman collection and tests;
5. API overview and implementation notes.


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


## Records response modernization

The records API retains the existing `records` array and native record/detail objects.
API responses now add `meta` and `pagination`, using the same pagination keys as
definition collections. Anonymous requests respect `limit` and `offset`; the API
default and maximum limit are both 1000.

The public `fields` parameter accepts record header names and numeric detail type IDs.
When omitted, the API returns `rec_ID`, `rec_RecTypeID`, `rec_Title` and all details.
`fields=rec_ID` still returns an array of record objects. The only documented `detail`
mode is `detail=ids`, which overrides `fields` and returns an array of record IDs inside
the normal records response envelope. `extended`, `depth`, `linkmode`, `format` and
`fmt` remain internal/export options and are not part of the public records contract.
