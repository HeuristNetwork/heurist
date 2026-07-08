# JavaScript minification with Terser

## A. Purpose

This document describes how to configure and run Terser to build a minified JavaScript bundle for Heurist.

The main purpose is to reduce JavaScript download size while keeping compatibility with the existing legacy client-side code. Since the current codebase does not use JavaScript modules and contains many global functions and variables, the default configuration avoids variable name mangling.

The generated bundle is intended to replace a group of individual `<script>` tags with one minified file:

```php
<script type="text/javascript" src="<?php echo PDIR;?>hclient/bundles/heurist-core.bundle.min.js"></script>
```

The bundle is generated into:

```text
hclient/bundles/heurist-core.bundle.min.js
hclient/bundles/heurist-core.bundle.min.js.map
```

## B. Build script and usage

### 1. Build script

The build script is located in:

```text
scripts/build-terser.mjs
```

It reads a defined list of JavaScript files in the required loading order, combines them, minifies them with Terser, and writes the result to:

```text
hclient/bundles/
```

The current bundle name is:

```text
heurist-core.bundle.min.js
```

To build the bundle, run from the Heurist codebase root:

```bash
npm run build:js
```

This executes:

```bash
node scripts/build-terser.mjs
```

### 2. Where to use the bundle

The generated bundle can be used in the CMS script/style loader, for example in:

```text
CMS websiteScriptAndStyles.php
```

Replace the corresponding group of individual script includes with:

```php
<script type="text/javascript" src="<?php echo PDIR;?>hclient/bundles/heurist-core.bundle.min.js"></script>
```

For development or debugging, it may still be useful to keep an option to load the original individual scripts instead of the minified bundle.

## C. Configuration of Terser and environment

### 1. Install Terser

Terser is installed as a development dependency with npm:

```bash
npm install --save-dev terser
```

The current `package.json` uses:

```json
"terser": "^5.48.0"
```

The `node_modules/` folder should not be committed to Git.

The following files should be committed:

```text
package.json
package-lock.json
scripts/build-terser.mjs
hclient/bundles/heurist-core.bundle.min.js
hclient/bundles/heurist-core.bundle.min.js.map
```

Committing the generated bundle is useful for Heurist deployments where production servers are updated directly from Git and may not have Node.js, npm, or Terser installed.

### 2. Build script content

Create or update:

```text
scripts/build-terser.mjs
```

with the following content:

```js
import { minify } from "terser";
import { readFile, writeFile, mkdir } from "node:fs/promises";
import path from "node:path";

const root = process.cwd();

const bundles = {
  "heurist-core.bundle.min.js": [
    "hclient/core/detectHeurist.js",

    "hclient/widgets/baseAction.js",

    "hclient/core/temporalObjectLibrary.js",
    "hclient/core/utils.js",
    "hclient/core/utils_ui.js",
    "hclient/core/utils_dbs.js",
    "hclient/core/utils_query.js",
    "hclient/core/utils_msg.js",
    "hclient/core/utils_geo.js",
    "hclient/core/utilsCollection.js",
    "external/js/wellknown.js",

    "hclient/core/hapi.js",
    "hclient/core/HSystemMgr.js",
    "hclient/core/HLayoutMgr.js",
    "hclient/core/layout.js",
    "hclient/core/hRecordSearch.js",
    "hclient/core/recordset.js",

    "layout_default.js",

    "hclient/widgets/cpanel/navigation.js",

    "hclient/widgets/viewers/resultList.js",
    "hclient/widgets/cpanel/buttonsMenu.js",
    "hclient/widgets/admin/progressReport.js",
  ],
};

const outDir = path.join(root, "hclient/bundles");

const terserOptions = {
  ecma: 5,

  compress: {
    defaults: true,

    // Safer for old code.
    unused: false,
    evaluate: false,
    reduce_vars: false,

    // Avoid changing behavior around console/debugger initially.
    drop_console: false,
    drop_debugger: false,
  },

  // Keep variable and function names unchanged for legacy global code.
  mangle: false,

  /*
  // Possible future option after testing.
  mangle: {
    toplevel: false,
    keep_fnames: true,
    reserved: [
      "window",
      "document",
      "jQuery",
      "$",
      "heurist",
      "HAPI4"
    ]
  },
  */

  // Do not wrap, module-transform, or assume strict/module scope.
  module: false,
  toplevel: false,

  format: {
    comments: false,
    ascii_only: true,
  },

  sourceMap: {
    filename: "heurist-core.bundle.min.js",
    url: "heurist-core.bundle.min.js.map",
  },
};

await mkdir(outDir, { recursive: true });

for (const [outFile, files] of Object.entries(bundles)) {
  const input = {};

  for (const rel of files) {
    const abs = path.join(root, rel);
    input[rel] = await readFile(abs, "utf8");
  }

  const result = await minify(input, {
    ...terserOptions,
    sourceMap: {
      filename: outFile,
      url: `${outFile}.map`,
    },
  });

  if (!result.code) {
    throw new Error(`Terser produced no output for ${outFile}`);
  }

  await writeFile(path.join(outDir, outFile), result.code, "utf8");

  if (result.map) {
    await writeFile(path.join(outDir, `${outFile}.map`), result.map, "utf8");
  }

  console.log(`Built ${path.join("hclient/bundles", outFile)}`);
}
```

### 3. Add script into `package.json`

Add the following npm script:

```json
"scripts": {
  "build:js": "node scripts/build-terser.mjs"
}
```

The relevant part of `package.json` should include:

```json
{
  "name": "heurist",
  "version": "1.0.0",
  "description": "Heurist network",
  "scripts": {
    "build:js": "node scripts/build-terser.mjs"
  },
  "devDependencies": {
    "terser": "^5.48.0"
  }
}
```

If `package.json` already contains other scripts or development dependencies, keep them and only add or update the `build:js` entry and the `terser` dependency.

## Notes

The current configuration is intentionally conservative.

Compression is enabled, but the most risky transformations for legacy code are disabled:

```js
unused: false,
evaluate: false,
reduce_vars: false,
```

Name mangling is disabled:

```js
mangle: false,
```

This is important because older non-module JavaScript may depend on global variable names, global function names, string-based callbacks, or indirect references from HTML and PHP-generated scripts.

After thorough testing, limited mangling may be considered, but it should not be enabled as the default production setting for legacy Heurist code.
