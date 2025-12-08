How to use


Bash script sync_modified_db_filestores_identify.sh on hourly cron (hourny ould be uite adequate) to determine if any file in each db filestore directory has been added or modified in the previous hour. Creates output file modified_db_filestores_last_hour.csv 

sync_modified_db_filestores.sh run 5 minutes later runs rsync for each of the filestore directories, pushing any changes back to the origin database server.

---------------------------------------
To use:

Make executable:

sudo install -m 0750 -o root -g root /path/to/sync_modified_db_filestores.sh /srv/scripts/


Ensure the SSH key and permissions are correct on the syncing host:

# Example key path used above; adjust to your setup

sudo -u heuristeu ls -l /home/heuristeu/.ssh/id_ed25519


Test with a dry run:

SRC_ROOT=/var/www/html/HEURIST/HEURIST_FILESTORE \
DEST_ROOT=/var/www/html/HEURIST/HEURIST_FILESTORE \
DEST_HOST=heuristeu@heurist.huma-num.fr \
LIST_FILE=/var/log/heurist-sync/modified_db_dirs.txt \
DRY_RUN=1 \
/srv/scripts/sync_modified_db_filestores.sh


Then run for real (remove DRY_RUN=1). Add to cron after the “modified list” job:

# After the finder runs (e.g., minute 7), sync at minute 12

12 * * * * SRC_ROOT=/var/www/html/HEURIST/HEURIST_FILESTORE \
  DEST_ROOT=/var/www/html/HEURIST/HEURIST_FILESTORE \
  DEST_HOST=heuristeu@heurist.huma-num.fr \
  LIST_FILE=/var/log/heurist-sync/modified_db_dirs.txt \
  /srv/scripts/sync_modified_db_filestores.sh >/dev/null 2>&1

Notes / tweaks

Delete behavior: --delete mirrors deletions inside each DB dir (typical for mirrors). Drop it if you prefer additive updates.

Bandwidth: set BWLIMIT_KB=40960 (≈40 MB/s) if you want to throttle.

Performance: --no-inc-recursive keeps memory use steadier on large trees; remove it if you prefer rsync’s incremental recursion.

Logging: per-DB log files go to $LOG_DIR. You can rotate them with logrotate if needed.

Safety: the script only syncs paths under SRC_ROOT, ignoring anything else in the list. It also locks to avoid overlapping runs.