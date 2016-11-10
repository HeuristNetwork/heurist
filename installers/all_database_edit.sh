#!/bin/bash

# This script removes local paths from file paths for files which are in the database filestore
# it does not affect paths outside the filestore or to external resources
# IJ 25 Sep 14

if [ -z $1 ]
   then
      echo -e "\n\n"
      echo "Please supply root password for MySQL as a parameter "
      exit
   fi

all_dbs="$(mysql -uroot $user -p$1 -Bse 'show databases')"

for db in $all_dbs
     do
        echo $db
        if test $db != "information_schema"
            then if test $db != "mysql"
            then if test $db != "performance_schema"
            then
                mysql -uroot -p$1 $db -N -e "update recUploadedFiles set ulf_FilePath=replace(ulf_FilePath,'/var/www/HEURIST_FILESTORE/$db/','');"
                mysql -uroot -p$1 $db -N -e "update recUploadedFiles set ulf_FilePath=replace(ulf_FilePath,'/var/www/HEURIST/HEURIST_FILESTORE/$db/','');"
                mysql -uroot -p$1 $db -N -e "update recUploadedFiles set ulf_FilePath=replace(ulf_FilePath,'/var/www/html/HEURIST_FILESTORE/$db/','');"
                mysql -uroot -p$1 $db -N -e "update recUploadedFiles set ulf_FilePath=replace(ulf_FilePath,'/var/www/html/HEURIST/HEURIST_FILESTORE/$db/','');"
        fi
      fi
    fi
done