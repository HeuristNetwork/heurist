#!/bin/sh
#
# List CMS Homepage (concept code 99-51) records as link for each Heurist database on this server
# Input: none
# Output: Simple html list of database website links
# Updated: Ian Johnson 21 May 2023


if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

cd /srv/scripts/results

# Headers insert

now=$(date)

echo "<html> <body>"  >  list_of_websites_on_all_dbs_temp.txt
echo "<h1> Heurist websites on $HOSTNAME at $now </h1>"  >>  list_of_websites_on_all_dbs_temp.txt

echo "Processing ..."

for db in `/usr/bin/mysql --login-path=local -N  -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

# On Usyd: for db in `/usr/bin/mysql --login-path=local -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'$

do
  # For all databases
  # it would be nice to strip out all the formatting except bold and italics from the rec_Title
  /usr/bin/mysql --login-path=local -N -B  -u root -pxxxxxx_replace_with_pwd_xxxxxx  -e " 
        SELECT '<h3><a href=https://xxxxx_replace with server name_xxxxxx/heurist/?db=$db&website&id=',rec_ID,' target=_blank>$db</a></h3>',
        '<pre style=\"font-size: 10px;margin-left:30px;\">',rec_Title, '</pre>'
        FROM Records
        WHERE rec_RecTypeID IN (SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=51)
        and Not rec_FlagTemporary
        and Not rec_Title Like '%Enter a website name here%'
        and Not rec_Title Like '%UNCONFIGURED SITE TITLE%'" $db >> list_of_websites_on_all_dbs_temp.txt;


done

sort list_of_websites_on_all_dbs_temp.txt > list_of_websites_on_all_dbs.tsv

# creates file ready for mailing tgo sysadmin
rm list_of_websites_on_all_dbs_temp.txt

# Create accessible slightly formatted web page
cp list_of_websites_on_all_dbs.tsv /var/www/html/HEURIST/list_of_websites_on_all_dbs.html

echo
echo "Done, use    less /srv/scripts/results/list_of_websites_on_all_dbs.tsv     to view results"
echo "       or    <server>/HEURIST/list_of_websites_on_all_dbs.html"

echo





