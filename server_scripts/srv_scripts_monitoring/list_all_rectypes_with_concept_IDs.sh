#!/bin/sh
#
# List record types on all Heurist databases on this server, including concept IDs
# Location: This script is intended to be in /srv/scripts and to save results in /srv/scripts/RESULTS
# Input: none
# Output: a CSV file

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

servername="<ENTER THE SERVER NAME>"

now=$(date)

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath

echo "00000 Record type, origin DB, origin code, database 00000">  $respath/list_of_rectypes_on_all_dbs_with_conceptIDs_temp.txt

echo "Processing ..."

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`


do 
  
# repeat for all databases
  /usr/bin/mysql --login-path=$connection -N -B -e " 
	SELECT rty_Name,rty_OriginatingDBID,rty_IDInOriginatingDB,'$db' 
	FROM defRecTypes 
	where 1 
	" $db >> $respath/list_of_rectypes_on_all_dbs_with_concept_IDs_temp.txt;  

done

# creates file ready for mailing to sysadmin

sort $respath/list_of_rectypes_on_all_dbs_with_concept_IDs_temp.txt|uniq > $respath/list_of_rectypes_on_all_dbs_with_concept_IDs.tsv

rm $respath/list_of_rectypes_on_all_dbs_with_concept_IDs_temp.txt

chgrp -R  heurist $respath/
chmod -R ug+rwx $respath/

echo
echo "Done, use    less $respath/list_of_rectypes_on_all_dbs_with_concept_IDs.tsv     to view results"
echo
