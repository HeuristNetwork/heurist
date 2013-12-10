#! /bin/sh
if [ -z $1 ] 
   then echo "Please supply version eg. 3.x.x - insert appropriate sub-versions" 
   exit
   fi

# copy_distribution_files.sh
# --------------------------
# This file copies all necessary (and some uncessary ..) Heurist vsn 3 distribution files 
# from the current directory on our development machine to a temporary directory in the h3-setup directory
# Make sure the directory is the up-to-date version you want to package. 
# The script may require modification for other servers / directory layouts

# RUN THIS FILE FROM AN H3-xx DIRECTORY CONTAINING DESIRED HEURIST CODE

# Expects h3-setup directory in same directory as h3-xx directory
# Modified 9th dec 2013 to run on new virtual server
# Ian Johnson Dec 2013
 
echo -e "\n"
# will only remove from a directory called h3-stup and files in subdirectory specified by the parameter 
echo Removing existing files from h3-setup/h$1
rm -rf ../h3-setup/h$1/*

echo -e "\n" 
echo copying files to ../h3-setup/h$1
# Remember to add any new directories here
cp -r admin ../h3-setup/h$1
cp -r common ../h3-setup/h$1
cp -r export ../h3-setup/h$1
cp -r hapi ../h3-setup/h$1
cp -r import ../h3-setup/h$1
cp -r records ../h3-setup/h$1
cp -r search ../h3-setup/h$1
cp -r viewers ../h3-setup/h$1
cp -r applications ../h3-setup/h$1
cp -r documentation ../h3-setup/h$1

# Dec 2013 What was this for ??? I think it was the stored procedures, now superceded
# cp -r MYSQL ../h3-setup/h$1

# Copy all the files in the root of h3-xx
cp -r *.* ../h3-setup/h$1

# Copy the simlinked directories for external and help as real directories 
cp -r ../h3/external ../h3-setup/h$1
cp -r ../h3/help ../h3-setup/h$1 

# remove the Heurist Project home page, only applicable to HeuristScholar.org
# Dec 2013: we may be removing thuis anyway from the code as we move to a more managed Heurist web site
rm ../h3-setup/h$1/index.html

# Now zip it all up as a tarball for distribution on the Google code site
echo -e "\n" 
echo create tarball h$1.tar.bz2
rm -f ../h3-setup/h$1.tar.bz2
tar -cjf ../h3-setup/h$1.tar.bz2 -C ../h3-setup/ h$1/
echo -e "\n" 

echo -e "\n\n\n"
echo Change to ../h3-setup and run the Google Code upload program:
echo note: you MUST delete the upload from Google Code if it already exists
echo -e "\n"
echo gcode_wiki_upload.py -s \"EDIT IN TITLE HERE\" -p heurist --user=ijohnson222@gmail.com h$1.tar.bz2
echo -e "\n\n"
echo "You may wish to rename and upload the tarball as h3_alpha, h3_beta or h3_latest"
echo -e "\n\n"


