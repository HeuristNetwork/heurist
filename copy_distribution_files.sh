#! /bin/sh
if [ -z $1 ] 
   then echo "Please supply version eg. 3.x.x - insert appropriate sub-versions" 
   exit
   fi
 
# copy_distribution_files.sh
# --------------------------
# This file copies all necessary (and some uncessary ..) Heurist vsn 3 distribution files 
# from the current directory to a temporary directory in the h3-setup directory
# (run git fetch/git stash/git pull first to synch with h3share - don't forget to 
# git pop afterwards to restore any local changes)
# Ian Johnson May 2013

echo -e "\n" 
echo Removing existing files from /var/www/htdocs/h3-setup/h$1
rm -rf /var/www/htdocs/h3-setup/h$1/*

echo -e "\n" 
echo copying files to /var/www/htdocs/h3-setup/h$1
cp -r admin /var/www/htdocs/h3-setup/h$1
cp -r common /var/www/htdocs/h3-setup/h$1
cp -r export /var/www/htdocs/h3-setup/h$1
cp -r hapi /var/www/htdocs/h3-setup/h$1
cp -r import /var/www/htdocs/h3-setup/h$1
cp -r records /var/www/htdocs/h3-setup/h$1
cp -r search /var/www/htdocs/h3-setup/h$1
cp -r viewers /var/www/htdocs/h3-setup/h$1
cp -r MYSQL /var/www/htdocs/h3-setup/h$1
cp -r documentation /var/www/htdocs/h3-setup/h$1

cp -r *.* /var/www/htdocs/h3-setup/h$1
cp -r /var/www/htdocs/h3/external /var/www/htdocs/h3-setup/h$1
cp -r /var/www/htdocs/h3/help /var/www/htdocs/h3-setup/h$1

echo -e "\n" 
echo create tarball h$1.tar.bz2
rm -f /var/www/htdocs/h3-setup/h$1.tar.bz2
tar -cjf /var/www/htdocs/h3-setup/h$1.tar.bz2 -C /var/www/htdocs/h3-setup/ h$1/
echo -n "\n" 

echo -e "\n"
echo now change to /var/www/htdocs/h3-setup and run the Google Code upload program as follows:
echo note: you must delete the upload from Google Code if it already exists
echo -e "\n"
echo gcode_wiki_upload.py -s \"Title for the download\" -p heurist --user=ijohnson222@gmail.com h$1.tar.bz2

