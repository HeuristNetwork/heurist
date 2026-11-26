import { cp } from "node:fs/promises";

// Resolve paths from this script so direct invocation also works outside the repo root.
const root = new URL("../../../", import.meta.url);

// Some external dependencies may have been manually edited. See
// documentation/structure_and_libraries/external_libraries/modifications_to_external_functions.txt
await cp(
  new URL("node_modules/jquery/dist/", root),
  new URL("hclient/bundles/external/jquery/", root),
  { recursive: true }
);

console.log("Copied jQuery to hclient/bundles/external/jquery/");
