#!/bin/sh
#
# Runs all the descriptive scripts and sends the resultatn files by email to thy system admin.
# This should be run weekly by cron

# For multiple recipients: RCPTS="ian.johnson.heurist@gmail.com,support@heuristnetwork.org"

RCPTS="ian.johnson.france@gmail.com"

# Run all the scripts to generate latest reports 

echo "Running scripts to build lists ..."

/srv/scripts/list_all_websites.sh
/srv/scripts/list_all_dbs.sh
/srv/scripts/list_all_rectypes.sh
/srv/scripts/list_all_fields.sh
/srv/scripts/list_all_rectypes_with_concept_IDs.sh

# This gives one record for each user in each database, with dbname, institution and interests
/srv/scripts/list_all_users_with_dbs.sh

# This generate a cumulative list of unique emails + given and family name, retainign users after their databases have disappeared
/srv/scripts/list_all_users_unique.sh


# Email out the reports
echo -e "Find attached reports from Heurist DB server at Huma-Num" | mail -s "[Heurist] Huma-Num DB server reports"  -a /srv/scripts/results/list_of_dbs.tsv  -a /srv/scripts/results/list_of_users_on_all_dbs.tsv -a /srv/scripts/results/cumulative_list_of_users_unique.tsv  -a /srv/scripts/results/list_of_rectypes_on_all_dbs.tsv  -a /srv/scripts/results/list_of_fields_on_all_dbs.tsv -a /srv/scripts/results/list_of_websites_on_all_dbs.tsv $RCPTS 

echo "Emails sent .." 


