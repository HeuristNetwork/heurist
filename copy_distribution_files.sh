#! /bin/sh
if [ -z $1 ] 
   then echo "Please supply version eg. 3.x.x - insert appropriate sub-versions" 
   exit
   fi

# RUN THIS FILE FROM AN H3-xx DIRECTORY CONTAINING DESIRED HEURIST CODE
 
# copy_distribution_files.sh
# --------------------------
# This file copies all necessary (and some uncessary ..) Heurist vsn 3 distribution files 
# from the current directory on the AeR development machine (sylvester) to a temporary directory in the h3-setup directory
# Make sure the directory is the up-to-date version you want to package. The script will requrie modification for other servers
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

# remove the Heurist Project home page, only applicable to HeuristScholar.org
rm /var/www/htdocs/h3-setup/h$1/index.html

echo -e "\n" 
echo create tarball <version>>.tar.bz2
rm -f /var/www/htdocs/h3-setup/h$1.tar.bz2
tar -cjf /var/www/htdocs/h3-setup/h$1.tar.bz2 -C /var/www/htdocs/h3-setup/ h$1/
echo -e "\n" 

echo -e "\n\n\n"
echo Change to /var/www/htdocs/h3-setup and run the Google Code upload program:
echo note: you MUST delete the upload from Google Code if it already exists
echo -e "\n"
echo gcode_wiki_upload.py -s \"EDIT IN TITLE HERE\" -p heurist --user=ijohnson222@gmail.com h$1.tar.bz2
echo -e "\n\n"
echo "You may wish to rename and upload the tarball as h3_alpha, h3_beta or h3_latest"
echo -e "\n\n"


