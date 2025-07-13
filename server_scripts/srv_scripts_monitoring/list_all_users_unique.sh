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

# Copy our existing cumulative lsit of users to the temporary file
cat $respath/cumulative_list_of_users_unique.tsv > $respath/cumulative_list_of_users_unique_temp.tsv

# Now add all users to this file
for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`
do 
  # for all db(s)
  
  sudo mysql --login-path=$connection -N -B -e "
	SELECT ugr_eMail,ugr_FirstName,ugr_LastName
	  FROM sysUGrps 
	  WHERE ugr_Type='user' AND NOT ugr_email='guest@null' AND NOT ugr_email='info@heuristscholar.org'
	" $db >> $respath/cumulative_list_of_users_unique_temp.tsv;  

done

#Sort temporary file, remove duplicates by duplicated email address (field 1) and copy back to cumulative list
sort -u -k 1,1 -t , $respath/cumulative_list_of_users_unique_temp.tsv > $respath/cumulative_list_of_users_unique.tsv	# write final file in users order

# TODO: New zip file each month, just as a precaution against loss of the cumulative list
yest=$(date -d yesterday +'%Y-%m-%d')
mnth=$(date -d today +'%Y-%m')
zip $respath/${mnth}_archive_cumulative_list_of_users_unique.zip $respath/cumulative_list_of_users_unique.tsv

rm $respath/cumulative_list_of_users_unique_temp.tsv # Cleanup tmp file

chgrp -R  heurist $respath/
chmod -R ug+rwx $respath/

echo
echo "Done, use    less $respath/cumulative_list_of_users_unique.tsv     to view results"
echo
