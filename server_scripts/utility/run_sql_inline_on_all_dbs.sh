#!/bin/sh
#
# Run the specified SQL for every Heurist database on the server
#



for db in `echo "select schema_name from information_schema.schemata where schema_name like 'hdb_%' " | mysql -u root -pxxxxx_replace_with_pwd_xxxxx`; 
do 
# Add the statements to be executed below

sudo mysql -uroot -pxxxxx_replace_with_pwd_xxxxx --skip-column-names --silent $db -e "SELECT '>> $db >>>>',sys_MediaFolders from sysIdentification";  

done



# ----------------  FOR REFERENCE ------------------------------------------

# "SELECT '>> $db >>>>',dty_Name,dty_JsonTermIDTree from defDetailTypes,sysIdentification where sys_dbVersion=1 AND sys_dbSubVersion=3 AND dty_JsonTermIDTree LIKE '%{%'" ;  done
# "SELECT '>>>>>>> $db >>>', sys_SyncDefsWithDB FROM sysIdentification where sys_SyncDefsWithDB IS NOT NULL"

# Find JSonTermTrees for terms and realtionshiop types which sdtill contain JSon rather than a vocab
#  sudo mysql -uroot -pxxxxx_replace_with_pwd_xxxxx --skip-column-names --silent $db -e 
# "SELECT '>> $db >>>>',dty_Name,dty_JsonTermIDTree from defDetailTypes,sysIdentification where sys_dbVersion=1 AND sys_dbSubVersion=3 AND dty_JsonTermIDTree LIKE '%{%'" ;  done

# Find all specifically selected term list - no longer used, replaced by vocabs
# "SELECT dty_Name,dty_JsonTermIDTree from defDetailTypes where dty_JsonTermIDTree LIKE '%{%'"

#find org in any record url
# "select '>> $db >>>>',rec_ID,rec_URL,rec_Title from Records where rec_URL LIKE '%org%'"

# find iafd in any detail record
# "select '>> $db >>>>',dtl_RecID,dtl_Value from recDetails where dtl_Value like '%iafd%'"

# sudo mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx --skip-column-names --silent $db -e "update recDetails set dtl_Value = REPLACE(dtl_Value,'heuristplus.sydney.edu.au','heuristref.net') where dtl_Value like '%heuristplus.sydney.edu.au%'";

# sudo mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx --skip-column-names --silent $db -e "update Records set rec_URL = REPLACE(rec_URL,'heuristplus.sydney.edu.au','heuristref.net') where rec_URL  like '%heuristplus.sydney.edu.au%'";

# sudo mysql -uroot -pxxxxxx_replace_with_pwd_xxxxxx --skip-column-names --silent $db -e "update recUploadedFiles set ulf_ExternalFileReference = REPLACE(ulf_ExternalFileReference,'heuristplus.sydney.edu.au','heuristref.net') where ulf_ExternalFileReference like '%heuristplus.sydney.edu.au%'";

