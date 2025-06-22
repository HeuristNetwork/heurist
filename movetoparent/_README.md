# Directory: /movetoparent

## Overview

This directory contains files which should be placed in the parent directory of the codebase during installation of Heurist. We STRONGLY recommend using /var/www/html/HEURIST as the parent directory as all our server scripts, redirects and cron jobs are written for this address. Heurist does not touch anything outside the HEURIST root directory, except through simlinks you may add.

## Key files

-   `404.html`: Custom 404 error page.
-   `GDPR.html`: GDPR compliance information page.
-   `HeuristNameLogo.png`: Heurist logo image.
-   `copy_h6-alpha_to_heurist.sh`: Shell script to copy h6-alpha to Heurist.
-   `copy_h6-beta_to_heurist.sh`: Shell script to copy h6-beta to Heurist.
-   `databases_exclude_cronjobs.txt`: List of databases to be excluded from cron jobs.
-   `databases_not_to_purge.txt`: List of databases that should not be purged.
-   `disk_quota_allowances.txt`: Information about disk quota allowances.
-   `favicon.ico`: Favicon for the website.
-   `heuristConfigIni.php`: CRITICAL file that sets all local configuration parameters.
-   `index.html`: Main index page.
-   `js_in_database_authorised.txt`: List of authorized JavaScript in the database.
-   `organisation_logo.jpg`: Organization logo image.
-   `organisation_url.txt`: Organization URL.
-   `robots.txt`: Instructions for web crawlers.
-   `scheme_hml.xsd`: XML schema for HML.
-   `scheme_record.xsd`: XML schema for records.
-   `terms_and_conditions.html`: Terms and conditions page.


