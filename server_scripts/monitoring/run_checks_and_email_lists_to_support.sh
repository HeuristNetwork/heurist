#!/bin/sh
#
# Runs all the descriptive scripts and sends the resultant files by email to the system admin.
# This should be run weekly by cron

# NOTE: despite the name, this does not run checks, only lists

# For multiple recipients use commas, eg: RCPTS="ian.johnson.heurist@gmail.com,support@heuristnetwork.org"

RCPTS="ian.johnson.heurist@gmail.com"

# Run all the scripts to generate latest reports 

echo "Running scripts to build lists ..."

/srv/scripts/list_all_websites.sh
/srv/scripts/list_all_dbs.sh
/srv/scripts/list_all_rectypes.sh
/srv/scripts/list_all_fields.sh
/srv/scripts/list_all_rectypes_with_concept_IDs.sh
/srv/scripts/list_filestore_sizes.sh

# This gives one record for each user in each database, with dbname, institution and interests
/srv/scripts/list_all_users_with_dbs.sh

# This generate a cumulative list of unique emails + given and family name, retainingy users after their databases have disappeared
/srv/scripts/list_all_users_unique.sh

# Email out the reports
# BEWARE this will fail if there is a space after the \ at end of each line as it escapes the following LF
echo -e "Find attached reports from Heurist DB server at Huma-Num" | mail -s "[Heurist] Huma-Num DB server reports"  \
-a /srv/scripts/results/list_of_dbs.tsv  \
-a /srv/scripts/results/list_of_users_on_all_dbs.tsv \
-a /srv/scripts/results/cumulative_list_of_users_unique.tsv  \
-a /srv/scripts/results/list_of_rectypes_on_all_dbs.tsv  \
-a /srv/scripts/results/list_of_fields_on_all_dbs.tsv \
-a /srv/scripts/results/list_of_websites_on_all_dbs.tsv $RCPTS 

echo "Emails sent .." 


