#!/bin/sh
#
# List all Heurist databases on this server 
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

echo "Processing ..."

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath

# Setup the headers 
echo '0 Database,Owner email, Owner Family Name, Owner Givn Name, Record count, Data modified, Year data mod, Month data mod,Move DB to archive,Registration ID,DB Format Version' > $respath/list_of_dbs_temp.tsv
echo '0 Sort by Year + Month + Record count to identify databases for deletion' >> $respath/list_of_dbs_temp.tsv

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

do  # for all db(s)

p=`basename $db | cut -c 5-`

/usr/bin/mysql --login-path=$connection -N -e "
	SELECT \"$db\",ugr_eMail,ugr_LastName,ugr_FirstName,count(Records.rec_ID), 
	MAX(Records.rec_Modified),YEAR( MAX(Records.rec_Modified)),MONTH( MAX(Records.rec_Modified) ), 
 	 \" \/srv\/scripts\/delete_database_to_archive.sh $p \",sys_dbRegisteredID,sys_dbVersion,sys_dbSubVersion
	FROM sysIdentification, sysUGrps, Records 
	WHERE ugr_ID=2 
	" $db >> $respath/list_of_dbs_temp.tsv;  

done

# creates file ready for mailing to sysadmin

sort $respath/list_of_dbs_temp.tsv > $respath/list_of_dbs.tsv		# write final file in alphabetic order
rm $respath/list_of_dbs_temp.tsv

chgrp -R  heurist $respath/
chmod -R ug+rwx $respath/

echo
echo "Done, use    less $respath/list_of_dbs.tsv     to view results"
echo
