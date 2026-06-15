# Heurist API Requests — Brief Overview

This document summarizes the current API entrypoint and URL shape. It is not a full endpoint-by-endpoint API specification.

This document is intended as a high-level API overview. Detailed request examples are provided in the accompanying HeuristPostmanCollectionV1.json Postman collection. Please note that the Postman collection is currently beta and may not yet cover all endpoints or reflect the final API design.

## Entry point

API requests are routed through the centralized root router and then internally dispatched to:

```text
/<version>/hserv/controller/api.php
```

Supported URL forms include:

```text
/api/<db>/<entity>/<id-or-selector>
/<version>/api/<db>/<entity>/<id-or-selector>
```

Example:

```text
/api/my_db/rst/12
/h7-alpha/api/my_db/records?q=ids:1,2,3
```

If the request path begins directly with `/api`, the API script treats it as if the default production version had been supplied.

## Centralized Apache routing

Apache should route all non-static requests through the root `index.php` entrypoint:

```apache
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^.*$ /index.php [L,QSA]
```

The PHP router then recognizes `/api/...` and internally dispatches to the API entry script.

## Request methods

The API maps HTTP methods to internal Heurist actions:

| HTTP method | Internal action |
|---|---|
| `GET` | `search` |
| `POST` | `add` |
| `PUT` | `save` |
| `PATCH` | `save` |
| `DELETE` | `delete` |

The API also supports the `HTTP_X_HTTP_METHOD` override header for POST requests that need to emulate `PUT`, `PATCH`, or `DELETE`.

Allowed internal actions are:

```text
search
add
save
delete
```

Not every entity supports every action.

## Supported entity names

The API maps short REST entity names to internal Heurist entity controllers.

### Database structure entities

| API entity | Internal entity |
|---|---|
| `rtg` | `DefRecTypeGroups` |
| `dtg` | `DefDetailTypeGroups` |
| `vcg` | `DefVocabularyGroups` |
| `rty` | `DefRecTypes` |
| `dty` | `DefDetailTypes` |
| `trm` | `DefTerms` |
| `rst` | `DefRecStructure` |

Longer aliases are also supported:

| API alias | Internal entity |
|---|---|
| `fieldgroups` | `DefDetailTypeGroups` |
| `rectypegroups` | `DefRecTypeGroups` |
| `rectypes` | `DefRecTypes` |
| `fields` | `DefDetailTypes` |
| `terms` | `DefTerms` |

### User and workflow entities

| API entity | Internal entity |
|---|---|
| `rem` | `UsrReminders` |
| `reminders` | `DbUsrReminders` |
| `swf` | `SysWorkflowRules` |
| `tag` | `UsrTags` |
| `users` | `SysUsers` |
| `groups` | `SysGroups` |

### Database and records

| API entity | Internal behavior |
|---|---|
| `dbs` | `SysDatabases` |
| `records` | record search/output only |

The current API script supports record search via `records`. Other record write operations are not implemented through this REST entrypoint.

### Authentication and session entities

| API entity | Internal entity |
|---|---|
| `login` | `System` |
| `logout` | `System` |

### IIIF and annotations

| API entity | Behavior |
|---|---|
| `annotations` | IIIF annotation server |
| `iiif` | IIIF Presentation API v3, GET only |

Examples:

```text
/api/osmak_annot/iiif/annotations/<file-obfuscated-id>
/api/osmak_annot/annotations/pages?uri=<canvas-url>
```

## URL and parameter forms

The API supports multiple request forms.

### REST-style path

```text
/api/<db>/<entity>/<id>
```

Example:

```text
/api/my_db/rst/12
```

### Database as query parameter

For backward compatibility, the database can be supplied as a query parameter:

```text
/api/rst/12?db=my_db
```

### Entity as query parameter

The API can also accept entity-style requests using parameters:

```text
/<version>/hserv/controller/api.php?db=my_db&ent=rst&id=12
```

In this case `ent` is translated to the corresponding API entity.

## JSON request bodies

For `POST`, `PUT`, and `PATCH` requests with:

```text
Content-Type: application/json
```

the API reads the request body and merges decoded JSON into request parameters.

If the body contains a JSON object and no `fields` parameter is already present, the decoded JSON object is assigned to `fields`.

## Authentication

The API supports two authentication mechanisms:

1. existing cookie-backed Heurist sessions
2. JWT Bearer tokens

JWT authentication is enabled by defining the following in `heuristConfigIni.php`:

```php
$jwt_Secret = 'your-long-random-secret';
$jwt_TTL    = 600;
```

API clients authenticate with:

```text
Authorization: Bearer <token>
```

The API attempts to preserve an existing logged-in cookie session. If no user is present in the session, it tries JWT authentication.

## Anonymous and public routes

Some routes bypass or relax authentication.

### Authentication skipped

Authentication processing is skipped for:

```text
login
logout
iiif
public annotation reads
```

Public annotation reads include annotation page/page/annotation search requests.

### Anonymous search allowed

Anonymous search may be allowed for:

```text
records
groups
users
```

Other protected routes require authentication.

## Records API behavior

For:

```text
/api/<db>/records
```

`GET` maps to record search/output and internally includes:

```text
record_output.php
```

Other REST write methods for `records` currently return “Method Not Implemented”.

## IIIF API behavior

For:

```text
/api/<db>/iiif/<resource>/<id>
```

GET requests set IIIF request parameters and internally include:

```text
iiif_presentation.php
```

The API sets `restapi=1` so that controllers return API-appropriate responses and HTTP status codes.

## Error responses

API errors are returned as JSON with HTTP status codes.

Typical examples include:

| Condition | HTTP status |
|---|---|
| API route not found | `400` |
| Method not allowed | `405` |
| Unauthorized | `401` |
| Method not implemented | `405` |

When Bearer authentication fails, the API uses a `WWW-Authenticate` header describing the error.

