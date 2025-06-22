# Directory: /startup

## Overview

This directory contains files related to the setup sequence, including new user registration, new database creation, and getting started guides.

## Key files

-   `gettingStarted.html`: Contains HTML snippets for introductory guides, used on startup and as hints from the main menu.
-   `index.php`: Main script for the startup process. It handles:
    -   Displaying the "Register new user" / "Set up new database" wizard (accessed from `https://heuristserver.tld/`).
    -   Displaying a list of all databases if a database is not found or the `db` parameter is missing (accessed from `https://heuristserver.tld/heurist/?db=`).
-   `listDatabases.php`: Returns a JSON array with all databases on the server. It can also produce a page with a list of all databases.
-   `userRegistration.html`: Contains HTML snippets for the content of the new user registration form.
