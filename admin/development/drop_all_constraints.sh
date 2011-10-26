#!/bin/bash

echo "";
echo "";
echo "This script drops all constraints from the specified hdb_ database";
echo "Usage: drop_all_constraints.sh dbname     (dbname without hdb_ prefix)";

mysql="mysql -psmith18 -u root hdb_$1"

fksQueryResult=`echo \
"SELECT TABLE_NAME, CONSTRAINT_NAME " \
"FROM information_schema.TABLE_CONSTRAINTS "\
"WHERE CONSTRAINT_TYPE='FOREIGN KEY' AND TABLE_SCHEMA='hdb_$1';"\
| $mysql`
declare -a fks
fks=($fksQueryResult)

for((i=2;i<${#fks[@]};i+=2)); do
  iinc=i+1
  tableName=${fks[$i]}
  keyName=${fks[$iinc]}
  echo "Dropping $tableName : $keyName"
  echo "ALTER TABLE $tableName"\
       "DROP FOREIGN KEY $keyName;"\
    | $mysql
  done

echo "";
echo "";
echo "";
