#!/bin/sh
#
# List fields on all Heurist databases on this server
# Input: none
# Output: a CSV file
#

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

cd /srv/scripts/results

echo "0 Fields on all databases alphabetically ">  list_of_fields_on_all_dbs_temp.txt

echo "Processing ..."

for db in `/usr/bin/mysql --login-path=local -N -u root -pxxxxxx_password_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`


do 
  
# repeat for all databases
  /usr/bin/mysql --login-path=local -N -B -u root -pxxxxxx_password_xxxxxx -e " 
	SELECT rst_DisplayName,rst_DisplayHelpText 
	FROM defRecStructure
	where 1 
	" $db >> list_of_fields_on_all_dbs_temp.txt;  

done


sort list_of_fields_on_all_dbs_temp.txt|uniq > list_of_fields_on_all_dbs.tsv

rm list_of_fields_on_all_dbs_temp.txt

echo
echo "Done, use    less /srv/scripts/results/list_of_fields_on_all_dbs.tsv     to view results"
echo
