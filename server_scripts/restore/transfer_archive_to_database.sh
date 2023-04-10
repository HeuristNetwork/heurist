#!/bin/bash

# transfer_archive_to_database.sh - run as sudo, specify the archive package name without extension

# This script copies a Heurist Archive package from /srv/transfer to /var/www/html/HEURIST/HEURIST_FILESTORE
# unzips it and creates the corresponding database

echo "this is $1"

cd /var/www/html/HEURIST/HEURIST_FILESTORE
echo "Now I am in the FILESTORE, creating database directory" 

mkdir $1
cd $1

echo "Now I am in the database directory $1" 

cp /srv/transfer/$1.zip .
unzip $1.zip

echo "Now I am going to build the database"

mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx -e "Create database IF NOT EXISTS hdb_$1;use hdb_$1;source $1_MySQL_Database_Dump.sql;"

chgrp -R apache .
chown -R apache .

# cleanup - zip file and extracted documentation and HML
rm $1.zip
rm $1.hml
rm $1_MySQL_Database_Dump.sql
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
