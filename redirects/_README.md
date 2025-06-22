# Directory: /redirects

## Overview

This directory contains redirector files to functions called across servers, notably to the Heurist Reference Index Server. By using these redirectors, dependency on scripts remaining in the same location is avoided (e.g. if the program code is restructured or new versions are written). This means that a newer, restructured version of the code can be accessed by an older version on a remote server, without the latter seeing any difference.

Version (e.g. `_V1`) suffix is used to allow new versions of the target scripts to generate different output without breaking older code on third party servers.

Some files are intended to provide a stable shortcut to a specific output, such as record view, which is human readable and may be referenced at a stable URL. If no version suffix is included the redirect is to human-readable output which may be changed/improved i.e. it is a stable reference but to content which may potentially change in its detailed form.

## Key files

-   `getStructure_V1.php`: Redirects to the `getStructure_V1.php` script, providing a stable access point for retrieving database structure information.
-   `resolver.php`: Handles the resolving of persistent identifiers or other resolvable links.
-   `viewRecord.php`: Provides a stable URL for viewing records, redirecting to the appropriate record viewing script.
