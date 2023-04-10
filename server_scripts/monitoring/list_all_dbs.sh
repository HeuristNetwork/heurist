#!/bin/sh
#
# List all Heurist databases on this server 
# Input: none
# Output: a CSV file
#

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

cd /srv/scripts/results

# Setup the headers 
echo '0 Database,Owner email, Owner Family Name, Owner Givn Name, Record count, Data modified, Year data mod, Month data mod,Move DB to archive,Registration ID,DB Format Version' > list_of_dbs_temp.tsv
echo '0 Sort by Year + Month + Record count to identify databases for deletion' >> list_of_dbs_temp.tsv

# for db in `/usr/bin/mysql --login-path=local -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`
for db in `/usr/bin/mysql -u root -pxxxxxx_replace_with_pwd_xxxxxx -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

do  # for all db(s)

p=`basename $db | cut -c 5-`

/usr/bin/mysql -u root -pxxxxxx_replace_with_pwd_xxxxxx -e "
	SELECT \"$db\",ugr_eMail,ugr_LastName,ugr_FirstName,count(Records.rec_ID), 
	MAX(Records.rec_Modified),YEAR( MAX(Records.rec_Modified)),MONTH( MAX(Records.rec_Modified) ), 
 	 \" \/srv\/scripts\/delete_database_to_archive.sh $p \",sys_dbRegisteredID,sys_dbVersion,sys_dbSubVersion
	FROM sysIdentification, sysUGrps, Records 
	WHERE ugr_ID=2 
	" $db >> list_of_dbs_temp.tsv;  

done


sort list_of_dbs_temp.tsv > list_of_dbs.tsv		# write final file in alphabetic order

rm list_of_dbs_temp.tsv

echo
echo "Done, use    less /srv/scripts/results/list_of_dbs.tsv     to view results"
echo
