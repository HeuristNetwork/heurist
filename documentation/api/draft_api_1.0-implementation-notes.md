# Heurist Read API: implementation notes

This draft documents the retrieval-only API exposed through
`hserv/controller/api.php` on the `h7dev` branch.

## What can be published immediately

The existing descriptive routes can be documented without changing the controller:

- `GET /api/{database}/records`
- `GET /api/{database}/records/{recordId}`
- `GET /api/{database}/rectypes`
- `GET /api/{database}/fields`
- `GET /api/{database}/terms`
- `GET /api/{database}/iiif/manifest/{identifier}`
- `GET /api/{database}/iiif/canvas/{identifier}`
- `GET /api/{database}/iiif/annotations/{identifier}`

The existing abbreviated aliases `rty`, `dty`, `trm` and `rst` are retained and
marked deprecated for new integrations.

## One route that needs a small controller alias

The draft uses:

`GET /api/{database}/record-structure/{recordTypeId}`

The current controller exposes this as:

`GET /api/{database}/rst/{recordTypeId}`

Add `record-structure => DefRecStructure` to the `$entities` map before publishing
the descriptive route. This is a routing alias only; it does not require new
business logic or database changes.

## Important verification before declaring version 1.0

1. Capture representative JSON from records, record types, fields, terms and
   record structures.
2. Replace the permissive `additionalProperties: true` schemas with exact,
   stable schemas where practical.
3. Confirm the public record-search parameters accepted by `record_output.php`.
   Remove undocumented internal parameters from the public contract.
4. Decide whether database structure is intentionally authentication-only.
   The current controller permits anonymous access to records, groups and users,
   but not to record types, fields, terms or record structures.
5. Confirm whether `offset` is supported consistently. If not, remove it from
   this document or implement it.
6. Serve the specification at a stable URL such as:
   `/api/openapi.yaml`
7. Add Swagger UI or Redoc at a URL such as:
   `/api/docs`

## Recommended later improvements

These do not require a write API:

- add `ETag`, `Last-Modified` and conditional GET support;
- return `Allow: GET, HEAD, OPTIONS` with 405 responses;
- implement `HEAD` and `OPTIONS`;
- standardise errors using `application/problem+json`;
- expose explicit pagination metadata;
- use an API version prefix, such as `/api/v1`, when a breaking response change
  is eventually introduced.

Do not add `/api/v1` to the OpenAPI `servers` URL until the router actually
supports it. OpenAPI should describe the deployed interface, not an intended one.
