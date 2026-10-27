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

  // First safe pass for old global code.
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
    mangle:false,
/*
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

  console.log(`Built ${path.relative(root, path.join(outDir, outFile))}`);
}