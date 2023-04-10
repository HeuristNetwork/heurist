#!/bin/sh
#
# One-off use : detect any smarty reports which use php so we can block and then authorise individual databases
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

echo "Database,Report name" >  list_smarty_reports_calling_php.txt


echo "Processing ..."

for db in `/usr/bin/mysql --login-path=local -N  -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

# On Usyd: for db in `/usr/bin/mysql --login-path=local -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'$

do
  # For all datbases
  dbname=${db:4}
  #cd /var/www/html/HEURIST/HEURIST_FILESTORE/$dbname/smarty-templates

  for f in /var/www/html/HEURIST/HEURIST_FILESTORE/$dbname/smarty-templates/*.tpl
	do
  	fnd=$(cat "$f"|grep \<\?php)
	if [ -n "$fnd" ]
	then
      	   echo "$dbname $f : contains php code"
	fi
        done

done

echo For results:   less /srv/scripts/results/list_smarty_reports_calling_php.txt

