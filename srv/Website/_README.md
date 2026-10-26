# Website format v4 (plan 27a)

Live pages of the websites of format version 4 (`heurist-explorer`
docs/development/27a Website-Format-v4-Plan.md). Legacy websites (versions 1-3)
stay with `hserv/web` and `hclient/widgets/cms`; field 2-49 = 4 keeps the two apart.

Records: a website is `RT_CMS_HOME` (99-51), a page `RT_CMS_PAGE` (99-52). Page HTML
in 2-4 (header 2-929, footer 2-940 on the website), configuration and node styles in
`DT_CONFIGURATION` 2-1187 (`{"schema": "heurist-cms/4", "instances": {}, "styles": {}}`),
translated text blocks in `DT_CMS_PAGE_TRANSLATIONS` 2-1202 (one `xx:{json}` value per
language, split into rows by `RecordValidator` and joined by `RecordDataService`).

- `WebsiteRepository.php` — local ids of the definitions (concept codes, `DefinitionLookup`),
  records with the visitor's rights, the menu tree (99-742 / 99-761).
- `WebsiteService.php` — what a URL shows: website home / page or standalone page, language,
  configuration, problems (`WebsiteException` with the HTTP status).
- `PageRenderer.php` — the HTML document and the `#h-site-config` block read by the site
  runtime (`heurist-explorer` apps/website); the iframe page of an instance.
- `CmsHtml.php` — nodes and hosts of a page HTML; translated text blocks put in place.
- `CmsValidator.php` — checks of §7 (also used on save through `CmsRecordCheck.php`).
- `StyleCompiler.php` — base settings and node styles as CSS (values: `Utilities/StoredCss`).
- `SiteUrls.php` — `/{db}/site/{id}[/{page}]` or `?db=&site=&pageid=`.
- `WebsiteSetup.php`, `WebsiteDefinitionsInterface.php` — `POST /api/{db}/website/setup`;
  the definition writer is `hserv/web/WebsiteDefinitions.php`.

The same checks and CSS are in `heurist-explorer` shared/src/site/cmsDocument.js; both run
the cases of shared/test/fixtures/cms-v4 (`tests/CmsFormatTest.php`). Database test:
`tests/WebsiteRenderTest.php`.
