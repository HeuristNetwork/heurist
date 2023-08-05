#!/bin/sh
#
# List CMS Homepage (concept code 99-51) records as link for each Heurist database on this server
# Location: This script is intended to be in /srv/scripts and to save results in /srv/scripts/RESULTS
# Input: none
# Output: Simple html list of database website links
# Updated: Ian Johnson 21 May 2023

# Connection to the MySQL database on two tier server (DB server separate from eweb server).
# If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use --login-path=local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

servername="<ENTER THE SERVER NAME>"

now=$(date)

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

cd /srv/scripts/results

# Headers insert

echo "<html> <body>"  >  list_of_websites_on_all_dbs_temp.txt
echo "<h1> Heurist websites on $HOSTNAME at $now </h1>"  >>  list_of_websites_on_all_dbs_temp.txt

echo "Processing ..."

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`

do
  # For all databases
  # it would be nice to strip out all the formatting except bold and italics from the rec_Title
  /usr/bin/mysql --login-path=$connection -N -B -e " 
        SELECT '<h3><a href=https://$server-name/heurist/?db=$db&website&id=',rec_ID,' target=_blank>$db</a></h3>',
        '<pre style=\"font-size: 10px;margin-left:30px;\">',rec_Title, '</pre>'
        FROM Records
        WHERE rec_RecTypeID IN (SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=51)
        and Not rec_FlagTemporary
        and Not rec_Title Like '%Enter a website name here%'
        and Not rec_Title Like '%UNCONFIGURED SITE TITLE%'" $db >> $respath/list_of_websites_on_all_dbs_temp.txt;


done

# creates file ready for mailing to sysadmin

sort $respath/list_of_websites_on_all_dbs_temp.txt > $respath/list_of_websites_on_all_dbs.tsv
rm $respath/list_of_websites_on_all_dbs_temp.txt

chgrp -R heurist $respath/
chmod -R ug+rwx $respath/


# Create accessible slightly formatted web page
cp $respath/list_of_websites_on_all_dbs.tsv /var/www/html/HEURIST/list_of_websites_on_all_dbs.html

chown apache /var/www/html/HEURIST/list_of_websites_on_all_dbs.html
chgrp heurist /var/www/html/HEURIST/list_of_websites_on_all_dbs.html
chmod ug+rwx /var/www/html/HEURIST/list_of_websites_on_all_dbs.html

echo
echo "Done, use    less $respath/list_of_websites_on_all_dbs.tsv     to view results"
echo "       or    <server>/HEURIST/list_of_websites_on_all_dbs.html"

echo





