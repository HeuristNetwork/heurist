# Heurist Pretty URL Routing Rules

This document summarizes the pretty URL routes currently supported by the centralized Heurist request routing layer.

The routing model is:

```text
Apache
  -> webroot /index.php
  -> RequestRouter::route()
  -> optional RecordResolver / FileResolver canonical redirect
  -> /<version>/index.php for UI flows
```

Apache should rewrite all non-static requests to the webroot `index.php` entrypoint:

```apache
RewriteEngine On

# Do not rewrite existing files or directories.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Route everything else through the centralized PHP router.
RewriteRule ^.*$ /index.php [L,QSA]
```

## Version prefixes

A request may explicitly name the Heurist code version:

```text
/<version>/...
```

Examples:

```text
/heurist/...
/h7-alpha/...
/h7-mybranch/...
```

If no version is specified, the router uses the configured default version, normally `heurist`.

Versioned pretty URLs are preserved where appropriate. For example:

```text
/h7-alpha/osmak_1
```

canonicalizes to:

```text
/h7-alpha/osmak_1/web/
```

while:

```text
/osmak_1
```

canonicalizes to:

```text
/osmak_1/web/
```

## Welcome page

For an unmapped host, the root path shows the static welcome page:

```text
/
```

The router also treats `/index.php` and `/index.html` as root.

If the host is mapped in `domainWebsites.json`, `/` is treated as the mapped website home page instead.

## Domain and database mappings

The router uses `domainWebsites.json` for two types of mapping.

### Own-domain mapping

A host can be mapped to a database, optional website id, and optional version:

```json
{
  "domains": {
    "parramattafoodcultures.net": {
      "db": "parramatta_food_cultures",
      "website": 68,
      "version": "heurist"
    }
  }
}
```

For a mapped host:

```text
https://parramattafoodcultures.net/
```

is internally treated as:

```text
db=parramatta_food_cultures
website=68
```

The browser host is preserved.

### DBREF mapping

Short database aliases can be expanded:

```json
{
  "dbref": {
    "MBH": {
      "db": "MBH_Manuscripta_Bibliae_Hebraicae",
      "website": 123
    },
    "BEnum": {
      "db": "BE_Bulletin_Epigraphique"
    }
  }
}
```

Example:

```text
/MBH/web/
```

is internally treated as:

```text
db=MBH_Manuscripta_Bibliae_Hebraicae
website=123
```

The `website` value from the DBREF entry is used as the default website when the pretty URL omits an explicit website id.

Query parameters win over path-derived parameters.

## Website routes

The canonical website routes are:

```text
/<db>/web/
/<db>/web/<websiteId>/
/<db>/web/<websiteId>/<pageId>/

/<db>/website/
/<db>/website/<websiteId>/
/<db>/website/<websiteId>/<pageId>/
```

Versioned equivalents are also supported:

```text
/<version>/<db>/web/<websiteId>/<pageId>/
```

Short numeric website routes are supported:

```text
/<db>/<websiteId>/<pageId>
```

This is equivalent to:

```text
/<db>/web/<websiteId>/<pageId>
```

If `<websiteId>` is omitted:

```text
/<db>/web/
```

the router uses the DBREF default website if available; otherwise it uses `website=0`.

If only `<db>` is supplied, the router redirects to the canonical website form:

```text
/<db>           -> /<db>/web/
/<version>/<db> -> /<version>/<db>/web/
```

## Own-domain numeric routes

For mapped own-domain hosts, the URL does not need to contain the database name.

If the domain mapping defines a fixed website:

```json
"parramattafoodcultures.net": {
  "db": "parramatta_food_cultures",
  "website": 68
}
```

then:

```text
/
```

means website 68 home page, and:

```text
/<pageId>
```

means page `<pageId>` within website 68.

If the domain mapping does not define a fixed website, numeric paths are interpreted as:

```text
/<websiteId>
/<websiteId>/<pageId>
```

To avoid ambiguity in client-side browser history updates, own-domain website JavaScript should normally emit both website and page id for non-home pages:

```text
/<websiteId>/<pageId>
```

## Record resolver routes

Record resolver routes are translated into canonical versioned script URLs by `RecordResolver`. The resolver returns redirects rather than including legacy scripts, because the legacy record/export scripts are most reliable when executed as entry scripts.

Supported record forms include:

```text
/db/record/<recordId>
/db/rec/<recordId>
/db/record/<dbId>-<recordId>
/db/record/<dbId>/<recordId>

/<db>/record/<recordId>
/<db>/rec/<recordId>
/<db>/view/<recordId>
/<db>/edit/<recordId>
/<db>/hml/<recordId>
```

Versioned equivalents are also supported:

```text
/<version>/<db>/record/<recordId>
```

### Local record ids

For a local record id:

```text
/osmak_1/record/184
```

the router produces parameters equivalent to:

```text
db=osmak_1
recid=184
fmt=html
```

and `RecordResolver` redirects to the versioned `viewRecord.php` URL.

### Concept codes

Record ids may be concept codes:

```text
<dbId>-<recordId>
```

Examples:

```text
/db/record/2-8
/osmak_1/record/2-8
```

When a concept code is used, the database id in the concept code is authoritative. The database name in the path can be a placeholder, such as `db`.

If the database id resolves to a remote registered server, the resolver redirects to that remote server. If it resolves locally, it uses the local record id.

### Record actions and formats

The resolver chooses the target script according to action and format.

| Input | Result |
|---|---|
| `action=view` or `fmt=html` | `/<version>/viewers/record/viewRecord.php` |
| `noheader=1` with HTML view | `/<version>/viewers/record/renderRecordData.php` |
| `action=edit` or `edit=1` | `/<version>/hclient/framecontent/recordEdit.php` |
| `action=hml` | normalizes to `fmt=hml` |
| `fmt=hml` or default resolver output | `/<version>/export/xml/flathml.php` |
| `fmt=xml,json,rdf,gephi,geojson,iiif` | `/<version>/hserv/controller/record_output.php` |

Additional parameters such as `depth`, `noheader`, `action`, and query parameters are preserved where applicable.

### HML query route

The HML route can point to a record id or to a query string:

```text
/<db>/hml/<recordId>
/<db>/hml/<query>
/<db>/hml/<query>/<depth>
```

If the first argument is a positive integer, it is treated as a record id. Otherwise it is treated as the `q` query value.

## Definition resolver routes

Definition resolver routes are always XML structure exports.

Supported definition entities:

```text
rty  record type
dty  detail/field type
rst  record structure field
trm  term
```

Supported forms include:

```text
/db/rty/<id>
/db/dty/<id>
/db/rst/<id>
/db/trm/<id>

/db/rty/<dbId>-<id>
/db/dty/<dbId>-<id>
/db/trm/<dbId>-<id>

/<db>/rty/<id>
/<db>/dty/<id>
/<db>/rst/<id>
/<db>/trm/<id>
```

Examples:

```text
/osmak_1/rty/10
/db/dty/2-4
```

The resolver redirects to:

```text
/<version>/hserv/structure/export/getDBStructureAsXML.php
```

with the appropriate entity parameter.

Concept-code ids can trigger remote database resolution in the same way as records.

## Template routes

Template routes support template id or template name:

```text
/<db>/tpl/<templateId>/<query>
/<db>/tpl/<templateName>/<query>
```

If the query segment can be parsed as record ids, it is converted to:

```text
q=ids:<id1>,<id2>,...
```

Otherwise it is used as the raw `q` value.

Examples:

```text
/MBH/tpl/123/10,11,12
/MBH/tpl/myTemplate/title:manuscript
```

## Administration route

```text
/<db>/adm
```

is mapped to:

```text
db=<db>
adm=1
```

## URL substitutions

For a given database, optional URL substitution rules can be loaded from:

```text
<database-folder>/settings/URLSubstitutions.txt
```

These rules allow database-specific short paths or aliases to be translated to either another path or a query string. They are applied before action dispatch for both own-domain and database-prefixed routes.

Substitution patterns can be literal paths, regular expressions, or simplified token patterns.

## API routes

Pretty API routes are recognized by the router but are handled by the API entry script:

```text
/api/...
/<version>/api/...
```

The router internally dispatches these to:

```text
/<version>/hserv/controller/api.php
```

API route details are summarized in the separate API requests overview document.

## Invalid paths

If a path looks like:

```text
/<db>/<unknown>
```

and `<unknown>` is not a supported route action, the router returns 404 instead of falling through into the UI. This prevents accidental loading of the generic Heurist interface for invalid pretty URLs.

## Debugging routes

Append:

```text
?routerDebug=1
```

to a request to inspect the router decision without executing redirects or including target scripts.
