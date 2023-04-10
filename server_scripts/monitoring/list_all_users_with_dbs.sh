#!/bin/sh
#
# List users on all Heurist databases on this server 
# Input: none
# Output: a CSV file  (TAB seperated using the -B batch option)
#

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."


cd /srv/scripts/results

# Setup the headers, 0 ensures sorting at start
echo -e '0_BEFORE IMPORTING: convert to UTF8 and Unix LF, remove hdb_ from database names, remove this line ...' > list_of_users_on_all_dbs_temp.txt
echo -e '0_Email \tFirstName \tLastName \tDatabase \tLastLoginTime \tLoginCount \tOrganisation \tInterests' > list_of_users_on_all_dbs_temp.txt

for db in `/usr/bin/mysql --login-path=local -N   -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`
do 
  # for all db(s)
  
  sudo mysql --login-path=local -N -B   -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "
	SELECT ugr_eMail,ugr_FirstName,ugr_LastName,\"$db\",ugr_LastLoginTime,ugr_LoginCount,ugr_organisation,ugr_interests
	  FROM sysUGrps 
	  WHERE ugr_Type='user' AND NOT ugr_email='guest@null' AND NOT ugr_email='info@heuristscholar.org'
	  " $db >> list_of_users_on_all_dbs_temp.txt;  

done


sort list_of_users_on_all_dbs_temp.txt > list_of_users_on_all_dbs.tsv		# write final file in users order

rm list_of_users_on_all_dbs_temp.txt		# Cleanup tmp file

echo
echo "Done, use    less /srv/scripts/results/list_of_users_on_all_dbs.tsv     to view results"
echo
