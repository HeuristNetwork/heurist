#!/bin/bash

# transfer_archive_to_database_from_tar_bz2.sh - run as sudo, specify the archive package name without extension

# This script copies a Heurist Archive package from /srv/transfer to /var/www/html/HEURIST/HEURIST_FILESTORE
# unzips it and creates the corresponding database

if [ -z "$1" ]
then
      echo "database name parameter is empty - please give full .tar.bz2 file name"
      exit 0
fi

if [ ! -f /srv/transfer/$1 ]
then
      echo "archive $1 does not exist - it must be located in /srv/transfer"
      exit 0
fi

echo "processing $1"

#get database name

db=$(echo $1 | rev | cut -c21- | rev)
echo 'database:$db'

cd /var/www/html/HEURIST/HEURIST_FILESTORE
echo "About to create database directory /var/www/html/HEURIST/HEURIST_FILESTORE/$db" 

mkdir $db
cd $db

echo "About to copy archive file to /var/www/html/HEURIST/HEURIST_FILESTORE/$db" 

cp /srv/transfer/$1 .
tar -xf $1

if [ ! -f $db_MySQL_Database_Dump.sql ]
then
    echo "Renaming database SQL dump to  $db_MySQL_Database_Dump.sql"

    mv hdb_$db*.sql $db_MySQL_Database_Dump.sql
fi

echo "About to build the MySQL database"
echo "If MySQL is on separate server this will fail, SQL file is in /srv/transfer ready to copy to MySQL server"

mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx -e "Create database IF NOT EXISTS hdb_$db;use hdb_$db;source $db_MySQL_Database_Dump.sql;"

chgrp -R apache .
chown -R apache .

# cleanup - zip file and extracted documentation and HML
rm $1.tar.bz2
rm $db.hml
# on multi-tier system the SQL file is needed separately to build the MySQL database
cp $db_MySQL_Database_Dump.sql /srv/transfer/
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
