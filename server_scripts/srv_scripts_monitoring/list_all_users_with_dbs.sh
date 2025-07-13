#!/bin/sh
#
# List users on all Heurist databases on this server 
# Location: This script is intended to be in /srv/scripts and to save results in /srv/scripts/RESULTS
# Input: none
# Output: a CSV file  (TAB seperated using the -B batch option)

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

servername=<ENTER THE SERVER NAME>

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath

# Setup the headers, 0 ensures sorting at start
echo -e '0_BEFORE IMPORTING: convert to UTF8 and Unix LF, remove hdb_ from database names, remove this line ...' > $respath/list_of_users_on_all_dbs_temp.txt
echo -e '0_Email \tFirstName \tLastName \tDatabase \tLastLoginTime \tLoginCount \tOrganisation \tInterests' >> $respath/list_of_users_on_all_dbs_temp.txt

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`
do 
  # for all db(s)
  
  sudo mysql --login-path=$connection -N -B -e "
	SELECT ugr_eMail,ugr_FirstName,ugr_LastName,\"$db\",ugr_LastLoginTime,ugr_LoginCount,ugr_organisation,ugr_interests
	  FROM sysUGrps 
	  WHERE ugr_Type='user' AND NOT ugr_email='guest@null' AND NOT ugr_email='info@heuristscholar.org'
	  " $db >> $respath/list_of_users_on_all_dbs_temp.txt;  

done

# creates file ready for mailing to sysadmin

sort $respath/list_of_users_on_all_dbs_temp.txt > $respath/list_of_users_on_all_dbs.tsv		# write final file in users order
rm $respath/list_of_users_on_all_dbs_temp.txt		# Cleanup tmp file

chgrp -R  heurist $respath/
chmod -R ug+rwx $respath/

echo
echo "Done, use    less $respath/list_of_users_on_all_dbs.tsv     to view results"
echo
