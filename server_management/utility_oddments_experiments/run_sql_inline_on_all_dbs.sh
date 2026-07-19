#!/bin/sh
#
# Run the specified SQL for every Heurist database on the server
# Simply edit in the password and the appropriate SQL to run
# Could be updated to optionally accept the SQL as a parameter

# This is a simple and generic function rather than the specific 
# function of run_sql_query_on_all_dbs.sh which has all the hallmarks of AI ...

pwd='<MySQL password here>'

for db in $(sudo mysql -uheurist -p"$pwd" --skip-column-names --silent \
    -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'hdb\_%';")
do
    sudo mysql -uheurist -p"$pwd" --skip-column-names --silent "$db" -e \
    # replace the following with a suitable SQL instruction
        "UPDATE Records SET rec_URL = REPLACE(rec_URL, 'int-heuristweb-prod.intersect.org.au', 'heuristAU.net') WHERE rec_URL LIKE '%int-heuristweb-prod.intersect.org.au%';"
done


# ---------------------------------------
# Alternative useful for two tier systems

#     connection="mysql -H<SERVER> -P3306 -uheurist -p'$pwd'"

#     for db in `echo "select schema_name from information_schema.schemata \
#     where schema_name like 'hdb_%' " | mysql --login-path=$connection`; 
#     do 
#       sudo mysql --login-path=$connection --skip-column-names --silent $db \
#       -e "SELECT '>> $db >>>>',dtl_RecID from recDetails where dtl_Value Like '%h6-alpha%'";  
#     done







