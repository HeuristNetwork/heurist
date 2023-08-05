#!/bin/sh
#
# Run the specified SQL for every Heurist database on the server
#

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"


for db in `echo "select schema_name from information_schema.schemata where schema_name like 'hdb_%' " | mysql --login-path=$connection`; 
do 
# Add the statements to be executed below

sudo mysql --login-path=$connection --skip-column-names --silent $db -e "SELECT '>> $db >>>>',sys_MediaFolders from sysIdentification";  

done




