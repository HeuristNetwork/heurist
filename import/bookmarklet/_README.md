# Directory: import/bookmarklet

## Overview
This directory contains the files necessary for the Heurist bookmarklet functionality. The bookmarklet allows users to quickly save web pages as records in their Heurist database directly from their browser.

## Key files
- `bookmarkletPopup.php`: Defines the HTML and JavaScript for the popup window that appears when the bookmarklet is activated. This script handles user interaction within the popup.
- `bookmarkletSource.js`: Contains the minified source code for the bookmarklet itself (the code that is dragged to the browser's bookmark bar). It initiates the loading of `bookmarkletPopup.php`.
- `getRecordIDFromURL.php`: A PHP script that checks if the current URL (from the page where the bookmarklet is activated) already exists as a record in the Heurist database. It returns information like record ID if found.
- `getRectypesAsJSON.php`: A PHP script that fetches all available record types from the Heurist database and provides them in JSON format. This is used to populate a dropdown in the bookmarklet popup, allowing the user to choose a specific record type for the new bookmark.
- `bookmarklet-popup.css`: Provides the CSS styling for the bookmarklet popup window to ensure it is displayed correctly.
