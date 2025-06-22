# Directory: /server_scripts

## Overview

This directory contains scripts for installation, update, and verification of Heurist instances, as well as management of databases on a server.

## Subfolders

-   `experiments/`: Contains experimental scripts and test files.
-   `housekeeping/`: Contains scripts for regular cleanup and problem alerting.
-   `monitoring/`: Contains scripts for monitoring various aspects of the Heurist installation and server.
-   `prerequisites/`: Contains scripts related to installing prerequisites for Heurist. These are older scripts, retained mainly for reference.
-   `safeguard/`: Contains scripts for backing up, archiving backups, and managing archive space. These are typically placed in `/srv/scripts` and called from cron jobs.
-   `utility/`: Contains various utility scripts for managing and maintaining Heurist.

## Key files

-   `apache_configurations.txt`: Example Apache configurations.
-   `crontab file examples.txt`: Example crontab entries for scheduling tasks.
-   `databases_not_to_purge.txt`: List of databases that should not be purged.
-   `js_in_database_authorised.txt`: List of authorized JavaScript in the database.
-   `model_crontab_to_run_scripts.txt`: Model crontab file for running scripts.
-   `model_robots.txt`: Model `robots.txt` file.
-   `virtual_host_configurations.txt`: Example virtual host configurations.
