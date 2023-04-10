#!/bin/sh
#
# List CMS Homepage (concept code 99-51) records as link for each Heurist database on this server
# Input: none
# Output: a CSV file
#


if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

cd /srv/scripts/results

# Headers insert

echo "Database,Record ID,Website Title" >  list_of_websites_on_all_dbs_temp.txt


echo "Processing ..."

for db in `/usr/bin/mysql --login-path=local -N  -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

# On Usyd: for db in `/usr/bin/mysql --login-path=local -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'$

do
  # For all datbases
  /usr/bin/mysql --login-path=local -N -B  -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e " 
        SELECT \"$db\",\"++++++\",rec_ID,\"++++++\",rec_Title,\"++++++\",\"<a href=https://heurist.huma-num.fr/heurist/?db=$db&website&id=$rec_ID target=_blank>website</a>\"
        FROM Records
        WHERE rec_RecTypeID IN (SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=51) and Not rec_FlagTemporary" $db >> list_of_websites_on_all_dbs_temp.txt;

done

sort list_of_websites_on_all_dbs_temp.txt > list_of_websites_on_all_dbs.tsv

rm list_of_websites_on_all_dbs_temp.txt

echo
echo "Done, use    less /srv/scripts/results/list_of_websites_on_all_dbs.tsv     to view results"
echo





