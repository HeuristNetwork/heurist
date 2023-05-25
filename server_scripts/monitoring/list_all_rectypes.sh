#!/bin/sh
#
# List record types on all Heurist databases on this server
# Input: none
# Output: a CSV file

# define_connections.sh
# include this file into all the scripts which operate on mysql databases and gemerate result files
# (db monitoring and summarisation, backup and restore)

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

servername="<ENTER THE SERVER NAME>"

now=$(date)

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath

echo "00000 Record types on all databases alphabetically 00000" >  $respath/list_of_rectypes_on_all_dbs_temp.txt

echo "Processing ..."

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`


do 
  
# repeat for all databases
  /usr/bin/mysql --login-path=$connection -N -B -e " 
	SELECT rty_Name 
	FROM defRecTypes 
	where 1 
	" $db >> $respath/list_of_rectypes_on_all_dbs_temp.txt;  

done

# creates file ready for mailing to sysadmin

sort $respath/list_of_rectypes_on_all_dbs_temp.txt|uniq > $respath/list_of_rectypes_on_all_dbs.tsv

rm $respath/list_of_rectypes_on_all_dbs_temp.txt

chgrp -R  heurist $respath/
chmod -R ug+rwx $respath/

echo
echo "Done, use    less $respath/list_of_rectypes_on_all_dbs.tsv     to view results"
echo
