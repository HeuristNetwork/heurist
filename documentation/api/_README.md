# Heurist Open API documentation

This folder contains the public specification, examples, tests, and landing pages for the Heurist Open API.

## Public URLs

The root `RequestRouter` maps stable API documentation URLs to files in the active Heurist version's `documentation/api` folder.

| Public URL | Repository file |
|---|---|
| `/api/docs/` | `documentation/api/index.html` |
| `/api/docs/swagger/` | `documentation/api/swagger/index.html` |
| `/api/openapi.yaml` | `documentation/api/heurist-openapi.yaml` |
| `/api/docs/HeuristPostmanCollectionV1.json` | `documentation/api/HeuristPostmanCollectionV1.json` |
| `/api/docs/HeuristPostmanEnvironmentV1.json` | `documentation/api/HeuristPostmanEnvironmentV1.json` |

The same routes work with an explicit Heurist version prefix, for example:

```text
/heurist/api/docs/
/heurist/api/docs/swagger/
/heurist/api/openapi.yaml
```

No files need to be copied into a separate webroot `/api` directory. The router serves the files from the selected code version.

## Contents

- `heurist-openapi.yaml` — authoritative machine-readable OpenAPI specification.
- `index.html` — public API documentation landing page.
- `swagger/index.html` — interactive Swagger UI.
- `HeuristPostmanCollectionV1.json` — executable API examples and regression tests.
- `HeuristPostmanEnvironmentV1.json` — editable Postman environment template.
- `HEURIST_API_Requests_Overview.md` — overview of routes and request behavior.
- `HEURIST_API_Authentication.md` — session and JWT authentication notes.
- `ApiDebug.txt` — manual routing and authentication checks.
- `curl_api_auth_examples.sh` and `r_httr2_api_auth_examples.R` — command-line and R examples.

## Swagger UI

Swagger UI is loaded from the jsDelivr CDN using a fixed `swagger-ui-dist` version. This keeps generated third-party files out of the Heurist repository and avoids a separately maintained `external/swagger-ui` installation.

The Swagger page loads the specification through the relative public URL:

```text
../../openapi.yaml
```

From `/api/docs/swagger/`, this resolves to `/api/openapi.yaml`. It also works when an explicit version prefix is present.

A self-hosted Swagger distribution can replace the CDN later without changing the public URLs or OpenAPI specification. Self-hosting may be preferable on installations that must work without internet access.

## Postman

1. Download or open `HeuristPostmanCollectionV1.json` in Postman.
2. Import `HeuristPostmanEnvironmentV1.json`.
3. Set the server, database, username, password, and test identifiers.
4. Run requests individually or execute the full collection.

The published environment file must contain placeholders only. Never commit real passwords or active JWT tokens.

## Maintenance rule

The following must be updated together when the public API contract changes:

1. PHP implementation and routing.
2. `heurist-openapi.yaml`.
3. Postman collection and environment when required.
4. API overview/authentication documents.
5. Definition harvester if definition response formats change.

Successful IIIF responses remain direct IIIF Presentation API resources and must not be wrapped in a Heurist response envelope.

## Publication checks

After deployment, verify:

```text
/api/docs/
/api/docs/swagger/
/api/openapi.yaml
/api/docs/HeuristPostmanCollectionV1.json
/api/docs/HeuristPostmanEnvironmentV1.json
```

Also test Swagger's **Authorize** function, one anonymous records request, one anonymous definition request, one MapDocument request, one GeoJSON query, one timeline query, one authenticated private-record request, and one IIIF request.

## Validate the OpenAPI specification

After modifying `heurist-openapi.yaml`, validate it before committing:

```bash
npx @redocly/cli lint documentation/api/heurist-openapi.yaml
```

The specification must parse as valid YAML, satisfy OpenAPI 3.1 requirements,
contain no unresolved local `$ref` references, and declare every path parameter
used in a route.
