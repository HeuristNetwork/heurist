#!/bin/sh
#
# Dumps all databases to SQL in /srv/BACKUP
#

cd /srv/BACKUP

# Allow others to read backups
umask 022

echo ""
echo "----------------------------------------------------------------------------------" >> /srv/BACKUP/backup_failure_log.txt
echo ""

# Backup databases starting with "hdb_"

for db in `mysql -uroot -pxxxxxxxxxx -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`; do


        echo "$db : backing up ..."

# bzip2 provides >90% compression, gzip much less

        res="$( ( /usr/bin/mysqldump -uroot -pxxxxxxxxx  --single-transaction --routines --triggers --events --add-drop-database --skip-dump-date --databases $db | bzip2  > $db.sql.bz2; ) 2>&1 )"

        if [ "$?" -eq  0 ]

        then
                echo "$db : success ..."
        else

                # Note: this is in the BACKUP directory
                echo "$db : backup failed at $(date +'%d-%m-%Y %H:%M:%S')" >> /srv/BACKUP/backup_failure_log.txt
                echo "$db : $res" >> /srv/BACKUP/backup_failure_log.txt

                read -p "$db : FAILURE 30 sec pause...." -t 30
                echo " continuing ..."
                echo ""
        fi

done

                echo "Backup completed $(date +'%d-%m-%Y %H:%M:%S')" >> /srv/BACKUP/backup_failure_log.txt

