#!/bin/bash

# transfer_archive_to_database_FILES.sh - run as sudo

# Loops through all zip files in /srv/transfer and creates the HEURIST_FILESTORE directories from the archive package zip files

# If DB server and file server are separate: You also need to run transfer_archive_to_database_DB.sh on the database server to create the databases

cd /var/www/html/HEURIST/HEURIST_FILESTORE

for f in `ls /srv/transfer/*.zip`; do

        # remove zip extension
	f=$(basename $f .zip)

	echo "Creating $f ..."

	# make database directory and change into it
	cd /var/www/html/HEURIST/HEURIST_FILESTORE
	mkdir $f
	cd $f

	# copy zip archive and unzip in situ in the db directory 
	cp /srv/transfer/$f.zip .
	unzip $f.zip

	chgrp -R heurist .
	chown -R apache .

	# cleanup - zip file and extracted documentation and HML
	rm $f.zip
	rm $f.hml
	rm ${f}_MySQL_Database_Dump.sql
	rm Database_Structure.xml
	rm Database_Structure.txt
	rm -rf context_help
	rm -rf documentation_and_templates
	rm -rf scratch/*
	rm -rf hml-output/*
	rm -rf html-output/*

	echo " ok"

done


echo "FILESTORE directories extracted and created. Please delete zip files from /srv/transfer to avoid repetition"

echo "----------------------------------------------------"
echo ""
