#! /bin/sh

# Run as sudo

# copy_distribution_files.sh: Creates a distribution package in heurist-build from a Heurist working directory
# This file is intended for internal use of the development team and should normally be deleted from the install package.

# GOTCHA: If you get 'file not found' when you try to run this script, it is due to the file being converted to DoS 
# format (ctrl-M line endings). Use dos2unix copy_distribution_files.sh to fix.

# ---------------------

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2022 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov   <osmakov@gmail.com>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     6.0

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

if [ -z $1 ]
   then echo "Usage:   sudo ./copy_distribution_files.sh. h6.x.x ( insert appropriate sub-versions or alpha or beta in .x.x)"
   exit
   fi

# copy_distribution_files.sh
# --------------------------
# This file copies all necessary Heurist distribution files and support files from the current directory
# (any Heurist hx-xx program directory) to a temporary directory in the heurist-build directory in /var/www/html/HEURIST.
# Make sure the current directory is the up-to-date version you want to package.
# The script is set up for the HeuristScholar server and may require modification for other directory layouts

# RUN THIS FILE FROM AN hx-xx DIRECTORY CONTAINING DESIRED HEURIST CODE

# Creates heurist-build directory in /var/www/html/HEURIST and places finale result in HEURIST/DISTRIBUTION


echo -e "\n\n\n\n"
echo -------------------------------------------------------------------------------------------------
echo
echo "HEURIST DISTRIBUTION BUILDER"
echo
echo "Run from /var/www/html/HEURIST/hx-xx program directory using sudo"
echo
echo


# PRELIMINARIES
# -------------

# Copy the standard install and update scripts to the DISTRIBUTION  directory
echo Copying installer and update shellscripts from root of current instance
echo The installer and update scripts are accessed directly to download and install tarballs
cp *.sh /var/www/html/HEURIST/DISTRIBUTION
cp installers/*.sh /var/www/html/HEURIST/DISTRIBUTION

# update record type icons and icon library from Heurist_Core_Configuration database.
# This ensures that when new record types are added to the core definitions text file 
#(generated from this database) that you will not have a record type without an icon
cp ../HEURIST_FILESTORE/Heurist_Core_Definitions/rectype-icons/*.* admin/setup/dbcreate/icons/defRecTypes/icon
cp ../HEURIST_FILESTORE/Heurist_Core_Definitions/rectype-icons/thumb/*.* admin/setup/dbcreate/icons/defRecTypes/thumbnail



# BUILD DISTRIBUTION IN heurist-build
# -----------------------------------

# File paths specified with explicit heurist-build to ensure it will only remove from this specific directory
# and files in the subdirectory of this directory specified by the parameter

# clean up the build directory and recreate
sudo rm -rf /var/www/html/HEURIST/heurist-build
mkdir ../heurist-build


echo
echo copying files to /var/www/html/HEURIST/heurist-build/$1

echo
rm -rf /var/www/html/HEURIST/heurist-build/$1
mkdir ../heurist-build/$1

# Copy all the files in current codebase to hx.x.x as defined in parameter

cp -r * ../heurist-build/$1

# remove any superfluous files - add others as appropriate

rm ../heurist-build/$1/copy_distribution_files.sh
rm ../heurist-build/$1/update_heurist.sh
rm ../heurist-build/$1/install_heurist.sh

# Now zip it all up as a tarball for distribution on the Heurist web site

echo
echo creating tarball /var/www/html/HEURIST/heurist-build/$1.tar.bz2
# Note: c=create j=bzip2 f=file
tar -cjf ../heurist-build/$1.tar.bz2 -C ../heurist-build/ $1/
rm -rf ../heurist-build/$1/

# show what we have got in the directory
echo
echo tarball in the heurist-build directory:
ls -alt ../heurist-build

# avoid problems with shellscripts having DoS line endings
# TODO: need to do the same for installer directory shellscripts
# dos2unix no longer exists on our server @ Feb 2019 ??? dos2unix install_heurist.sh
# dos2unix no longer exists on our server @ Feb 2019 ??? dos2unix update_heurist.sh

# PHP extensions verification function
# TODO: Why is this being zipped? What is the purpose? Who added it? Needs an explanation. [Ian 4 Oct 2016]
# Perhaps to allow it to be downloaded on its own without runnign into php execution barrier
zip -j ../DISTRIBUTION/verifyInstallation.zip admin/verification/verifyInstallation.php

echo
echo creating support library tarballs in /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5, vendor, and help

# GOTCHA: If support directory not set up you get an error trying to tar into it
mkdir /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT
cd /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT


tar -cjf ./external_h5.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ external_h5/

tar -cjf ./vendor.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ vendor/

tar -cjf ./help.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ help/

cp /var/www/html/HEURIST/heurist-build/$1.tar.bz2  /var/www/html/HEURIST/DISTRIBUTION

chown -R apache:heurist /var/www/html/HEURIST/DISTRIBUTION

# Cleanup
rm -rf /var/www/html/HEURIST/heurist-build


echo
echo Distribution $1.tar.bz2 copied to .../HEURIST/DISTRIBUTION. 
echo
echo DO NOT CHANGE THE NAME of the tar.bz2 file - it extracts to a folder of this name  
echo and the installation is dependant on the filename parameter to find this folder
echo
echo
