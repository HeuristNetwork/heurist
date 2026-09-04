# Heurist client-module host layer

This directory contains parent-side adapters between core Heurist and the
independent Vite client modules. Code here may use HAPI4, HRecordSet, jQuery,
legacy document events and `window.hWin`. It must not be imported by the
independent applications.

The `mapViewer.js` and `dataViewer.js` adapters load the shared base classes and
their concrete module class automatically. Legacy callers therefore load only
the relevant viewer adapter. The resulting dependency order is:

1. `core/HeuristModuleViewer.js`
2. `core/HeuristModuleRecordset.js`
3. the concrete module class, for example `map/HeuristModuleMap.js`
4. its optional legacy jQuery adapter, for example `map/mapViewer.js`

Current structure:

- `core` — generic iframe lifecycle and record-result/selection integration;
- `map` — working host integration for `heurist-map`;
- `data` — working host adapter, jQuery wrapper and bundle-loading page for `heurist-data`;
- `graph` — working host adapter for the independent `heurist-graph` module;
- `timeline`, `chart` — reserved for future module adapters.

Shared code used *inside* Vite applications belongs in the separate future
`@heurist/client-core` package, not in this directory.
