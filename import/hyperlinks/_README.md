# Directory: import/hyperlinks

## Overview
This directory contains scripts for importing hyperlinks into Heurist. Users can provide a URL or upload an HTML file, from which hyperlinks are extracted. These links can then be selectively bookmarked as new records in the Heurist database.

## Key files
- `importHyperlinks.php`: The main script that provides the user interface for importing hyperlinks. It handles the parsing of a source URL or uploaded HTML file, extracts all hyperlinks, and presents them to the user for selection and bookmarking. It also manages the interaction with `getTitleFromURL.php` and the JavaScript functions in `importHyperlinks.js`.
- `importHyperlinks.js`: Contains client-side JavaScript functions that enhance the hyperlink import form. This includes functionalities like selecting/deselecting all links, using notes associated with links, and dynamically looking up webpage titles via `getTitleFromURL.php`.
- `getTitleFromURL.php`: script that takes a URL as input, fetches the content of that webpage, and extracts its HTML `<title>`. This is used by the import interface to automatically populate the title field for a hyperlink.
