# External libraries used by Heurist

**Last updated:** 18 July 2026  
**Maintainers:** Artem Osmakov and Ian Johnson

This document describes external JavaScript, PHP, and support libraries used by Heurist.

External libraries that are not stored in the Heurist Git repository are normally installed under `HEURIST_SUPPORT` and symlinked into each Heurist installation:

- `HEURIST_SUPPORT/external_h5` → `/external`
- `HEURIST_SUPPORT/vendor` → `/vendor`
- `HEURIST_SUPPORT/help` → `/help`

Composer-managed PHP libraries are installed under `HEURIST_SUPPORT/vendor` according to `composer.json`.

See also `documentation/modifications_to_external_functions.txt` for changes made to third-party source code and the reasons for those changes.

## Contents of `HEURIST_SUPPORT/external_h5`

### Editors and interface libraries

- `bootstrap-5.0.2` — responsive layout and interface components in users' custom websites based on Heurist CMS.
- `codemirror-5.61.0` — code editor used for Smarty templates and direct HTML editing.
- `tinymce5` — TinyMCE 5.0.11, used for WYSIWYG editing of block-text fields.
- `jquery-3.7.1` — core JavaScript library.
- `jquery-ui-1.14.0` and themes — interface widgets and interactions.
- `jquery-file-upload` — version 9.12.1; used by term editing, Smarty templates, file management, and record editing. `UploadHandler.php` has been integrated into the Heurist repository and the JavaScript has minor modifications.
- `jquery-ui-iconfont-master` — icon font compatible with jQuery UI icon class names. The upstream project is discontinued.
- `jquery.calendars-2.1.1` — calendar control used by the temporal-object editor.
- `jquery.fancybox-3.3.5` — gallery and image viewer. The local source has been modified.
- `jquery.fancytree` — version 2.5.0; tree view used by saved searches, terms, and record-type structures. The CSS has local modifications.
- `evol.colorpicker.js` — version 3.2.4; colour picker used by mapping interfaces. The local source has been modified.
- `jquery.layout` — version 1.3.0; cardinal layout control. The local source has been modified; upstream development is discontinued.
- `jquery.ui-contextmenu.js` — version 1.8.0; context-menu plugin used by the saved-search tree. The local source has been modified.
- `ui.tabs.paging` — paging extension for jQuery UI tabs.
- `js/datatable` — DataTables 2.1.6, maintained as an offline/local copy.

### Mapping and geospatial libraries

- `leaflet-1.9.4` — interactive mapping library.
- `leaflet.plugins/bookmarks` — Leaflet bookmarks control.
- `leaflet.plugins/leaflet.draw` — version 1.0.4; geometry drawing and editing. Authors include Jacob Toye, Jon West, Smartrak and Leaflet contributors.
- `leaflet.plugins/geocoder` — Leaflet Control Geocoder 1.7.0 by Per Liedman.
- `leaflet.plugins/markercluster` — version 1.4.1 by Dave Leaver, Smartrak and contributors.
- `leaflet.plugins/leaflet-iiif.js` — Leaflet-IIIF 3.0.0 by Jack Reed; IIIF tile-layer extension.
- `leaflet.plugins/leaflet-tileLayerPixelFilter.js` — tile-layer pixel filtering.
- `leaflet.plugins/leaflet.browser.print.min.js` — browser printing for Leaflet maps.
- `leaflet.plugins/leaflet.circle.topolygon-src.js` — converts circles to polygons.
- `leaflet.plugins/wise-leaflet-pip.js` — point-in-polygon extension.
- `js/geodesy-master` — Geodesy 1.1.3 by Chris Veness; UTM/WGS coordinate conversion used by `mapDraw.php`.
- `js/shapefile` — Shapefile-js; Shapefile-to-GeoJSON parser used by mapping tools. The deployed version could not be identified.
- `js/wellknown.js` — WKT parser/stringifier used by mapping tools and `utils_geo.parseWKTCoordinates`. The deployed version could not be identified.
- `js/cheapRuler.js` — fast approximations for common geodesic measurements. The deployed version could not be identified.

### Visualisation and media libraries

- `d3` — D3 3.4.11, used by relationship/network visualisations, including the fisheye plugin.
- `mirador4` — Mirador 4.1.0 IIIF viewer.
- `mirador-annotation-editor` — MAE 1.3.0 annotation editor integrated with Mirador.
- `vis` — VIS 4.4.0 timeline code, heavily modified for fuzzy dates. Only the timeline component is used.

### Utility libraries

- `js/platform.js` — browser and platform detection.
- `php/Mysqldump8.php` — database export support.
- `php/phpZotero.php` — Zotero synchronisation support.
- `php/tileserver.php` — server for pre-rendered map tiles using the OGC WMTS standard.

## Composer-managed PHP libraries

Versions and licences below are taken from the supplied `composer.lock`.

- `easyrdf/easyrdf` 1.1.1 — BSD-3-Clause; RDF parsing, serialisation and graph handling.
- `phayes/geophp` 1.2 — GPL-2 or New-BSD; geometry operations.
- `gasparesganga/php-shapefile` 3.4.1 — MIT; Shapefile reading and writing.
- `smarty/smarty` 5.4.5 — LGPL-3.0; custom report templates.
- `ezyang/htmlpurifier` 4.19.0 — LGPL-2.1-or-later; HTML filtering and sanitisation.
- `smalot/pdfparser` 2.12.5 — LGPL-3.0; PDF text and metadata parsing.
- `phpmailer/phpmailer` 6.12.0 — LGPL-2.1-only; email composition and delivery.
- `symfony/polyfill-mbstring` 1.37.0 — MIT; UTF-8 support where the PHP `mbstring` extension is unavailable.
- `tecnickcom/tcpdf` is not present in the supplied `composer.lock`; it may be separately installed where required.

## Maintenance notes

- Keep the versions in this document synchronized with the deployed files and `composer.lock`.
- Preserve upstream licence and copyright files when distributing third-party code.
- Record every local modification to third-party source in `documentation/modifications_to_external_functions.txt`.
- Legacy or modified libraries should be reviewed before replacement or upgrade because Heurist may depend on local behaviour not present upstream.
