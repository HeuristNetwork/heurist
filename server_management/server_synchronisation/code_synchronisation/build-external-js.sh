#!/usr/bin/env bash

set -e

# Note: some external dependencies may have been manually edited
# See `documentation/structure_and_libraries/external_libraries/modifications_to_external_functions.txt`

# JS Dependencies
EXTERNAL_JS=hclient/bundles/external
mkdir -p $EXTERNAL_JS

## jquery
mkdir -p $EXTERNAL_JS/jquery
cp -R node_modules/jquery/dist/ $EXTERNAL_JS/jquery/
