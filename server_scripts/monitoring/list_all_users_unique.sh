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

# Copy our existing cumulative lsit of users to the temporary file
cat cumulative_list_of_users_unique.tsv > cumulative_list_of_users_unique_temp.tsv

# Now add all users to this file
for db in `/usr/bin/mysql --login-path=local -N   -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`
do 
  # for all db(s)
  
  sudo mysql --login-path=local -N -B   -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "
	SELECT ugr_eMail,ugr_FirstName,ugr_LastName
	  FROM sysUGrps 
	  WHERE ugr_Type='user' AND NOT ugr_email='guest@null' AND NOT ugr_email='info@heuristscholar.org'
	" $db >> cumulative_list_of_users_unique_temp.tsv;  

done

#Sort temporary file, remove duplicates by duplicated email address (field 1) and copy back to cumulative list
sort -u -k 1,1 -t , cumulative_list_of_users_unique_temp.tsv > cumulative_list_of_users_unique.tsv	# write final file in users order

# TODO: New zip file each month, just as a precaution against loss of the cumulative list
yest=$(date -d yesterday +'%Y-%m-%d')
mnth=$(date -d today +'%Y-%m')
zip ${mnth}_archive_cumulative_list_of_users_unique.zip cumulative_list_of_users_unique.tsv

rm cumulative_list_of_users_unique_temp.tsv # Cleanup tmp file

echo
echo "Done, use    less /srv/scripts/results/cumulative_list_of_users_unique.tsv     to view results"
echo
