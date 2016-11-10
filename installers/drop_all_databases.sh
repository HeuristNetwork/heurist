#!/bin/bash

# This script removes all databases on server
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
                mysql -uroot -p$1 $db -N -e "drop database $db;"
                fi
      fi
    fi
done