#!/bin/sh
#
# Looks for all databases with dates ending in -01 which may be months which have been inadvertently 
# converted to 1st day of month (mostly in early 2021, up to latest 6 April 2021 on USyd or Huma-Num)

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

for db in `echo "select schema_name from information_schema.schemata where schema_name like 'hdb_%' " | mysql -u root -pxxxxx_replace_with_pwd_xxxxx`; 
do 

sudo mysql --login-path=$connection --skip-column-names --silent $db -e "SELECT '$db',count(*) from recDetails where dtl_Value LIKE '%-01';"

read -p "Continuing in 5 Seconds...." -t 5

done

