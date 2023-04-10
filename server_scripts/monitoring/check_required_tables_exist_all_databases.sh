#!/bin/sh
#
# Check databases for key tabels!
#

OUTPUT=""
HOST=`hostname`
RCPTS="ian.johnson@sydney.edu.au,support@heuristnetwork.org"

# Loop over databases starting with "hdb_"
for db in `/usr/bin/mysql --login-path=local -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`; do
	# echo $db;

	/usr/bin/mysql --login-path=local -N -B -D $db -e "show tables;"  | sort > /tmp/table_dump.$$
	_x=`/usr/bin/comm -23 /srv/scripts/required_tables_list_for_checking_script.txt /tmp/table_dump.$$`
	if [ -n "$_x" ]; then
		OUTPUT+="$db is missing the following tables:\n"
		OUTPUT+="\t( $_x )\n\n"
	fi
	rm /tmp/table_dump.$$
done

# Email the results
if [ -z "$OUTPUT" ]; then
	echo "All databases OK" | mail -s "$HOST: all dbs have correct tables" "$RCPTS"
else
	echo -e $OUTPUT | mail -s "$HOST: WARNING databases with missing tables" "$RCPTS"
fi
