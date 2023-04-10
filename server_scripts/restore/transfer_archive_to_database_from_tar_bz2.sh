#!/bin/bash

# transfer_archive_to_database_from_tar_bz2.sh - run as sudo, specify the archive package name without extension
# Note that the extraction of DB name does NOT work correctly if there is no date info following it

# This script copies a Heurist Archive package from /srv/transfer to /var/www/html/HEURIST/HEURIST_FILESTORE
# unzips it and creates the corresponding database

if [ -z "$1" ]
then
      echo "database name parameter is empty - include the archive date but leave off the extensions"
      exit 0
fi


if [ ! -f /srv/transfer/$1.tar.bz2 ]
then
      echo "archive $1.tar.bz2 does not exist"
      exit 0
fi

echo "Parameter read: $1"

#get database name - note that this does NOT work if there is no date info following the db name

db=$(echo $1 | rev | cut -c21- | rev)

echo "Database name will be : $db"

cd /var/www/html/HEURIST/HEURIST_FILESTORE
echo "Changed to FILESTORE, creating database directory $db" 

mkdir $db
cd $db

echo "Changed to database directory $db" 

cp /srv/transfer/$1.tar.bz2 .
tar -xf $1.tar.bz2

if [ ! -f $db_MySQL_Database_Dump.sql ]
then
    echo "Renaming database dump to $db_MySQL_Database_Dump.sql "

    mv hdb_$db*.sql $db_MySQL_Database_Dump.sql
fi

echo "Building the database hdb_$db"

mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx -e "Create database IF NOT EXISTS hdb_$db;use hdb_$db;source $db_MySQL_Database_Dump.sql;"

chgrp -R apache .
chown -R apache .

# cleanup - zip file and extracted documentation and HML
rm $1.tar.bz2
rm $db.hml
rm $db_MySQL_Database_Dump.sql
rm Database_Structure.xml
rm Database_Structure.txt
rm -rf context_help
rm -rf documentation_and_templates
rm -r scratch/*
rm -r hml-output/*
rm -r html-output/*


echo "Setup of $1 completed"

echo "----------------------------------------------------"

echo ""
