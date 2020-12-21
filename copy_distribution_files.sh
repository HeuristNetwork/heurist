#! /bin/sh

# If you get 'file not found' when you try to run this script, it is due to the file being converted to DoS format (ctrl-M line endings)
# Use dos2unix copy_distribution_files.sh to fix.

# copy_distribution_files.sh: Creates a distribution package in heurist-build from a Heurist working directory
# This file is intended for internal use of the development team and should normally be deleted from the install package.

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov     <osmakov@gmail.com>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     6.0

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

if [ -z $1 ]
   then echo "Usage:   sudo ./copy_distribution_files.sh. h5.x.x.xxxx   - insert appropriate sub-versions and alpha or beta in x.x-xxxx"
   exit
   fi

# copy_distribution_files.sh
# --------------------------
# This file copies all necessary Heurist distribution files and support files from the current directory
# (any Heurist hx-xx program directory) to a temporary directory in the heurist-build directory in /var/www/html/HEURIST.
# Make sure the current directory is the up-to-date version you want to package.
# The script is set up for the HeuristScholar server and may require modification for other directory layouts

# RUN THIS FILE FROM AN hx-xx DIRECTORY CONTAINING DESIRED HEURIST CODE

# Creates heurist-build directory in /var/www/html/HEURIST


echo -e "\n\n\n\n"
echo -------------------------------------------------------------------------------------------------
echo
echo "Heurist distribution builder - run from /var/www/html/HEURIST/hx-xx program directory"

# File paths specified with explicit heurist-build to ensure it will only remove from this specific directory
# and files in the subdirectory of this directory specified by the parameter

# no effect if already exists
mkdir /var/www/html/HEURIST/heurist-build

# added Ian J. 19 Dec 2020: update record type icons and icon library from Heurist_Core_Configuration database.
# This ensures that when new record types are added to the core definitions text file (generated from this database)
# that you will not have a record type without an icon
cp /heur-filestore/Heurist_Core_Definitions/rectype-icons/*.* admin/setup/rectype-icons
cp /heur-filestore/Heurist_Core_Definitions/rectype-icons/thumb/*.* admin/setup/rectype-icons/thumb

echo
echo removing existing heurist-build/$1
rm -rf /var/www/html/HEURIST/heurist-build/$1

echo
echo copying files to /var/www/html/HEURIST/heurist-build/$1

mkdir /var/www/html/HEURIST/heurist-build/$1

# Copy all the files in the root of hx-xx
cp -r *.* /var/www/html/HEURIST/heurist-build/$1

# Remember to add any new directories here

cp -r admin /var/www/html/HEURIST/heurist-build/$1
cp -r applications /var/www/html/HEURIST/heurist-build/$1
cp -r common /var/www/html/HEURIST/heurist-build/$1
cp -r context_help /var/www/html/HEURIST/heurist-build/$1
cp -r documentation_and_templates /var/www/html/HEURIST/heurist-build/$1
cp -r export /var/www/html/HEURIST/heurist-build/$1
cp -r hclient /var/www/html/HEURIST/heurist-build/$1
cp -r hsapi /var/www/html/HEURIST/heurist-build/$1
cp -r import /var/www/html/HEURIST/heurist-build/$1
cp -r installers  /var/www/html/HEURIST/heurist-build/$1
cp -r records  /var/www/html/HEURIST/heurist-build/$1
cp -r redirects /var/www/html/HEURIST/heurist-build/$1
cp -r viewers /var/www/html/HEURIST/heurist-build/$1
cp -r startup /var/www/html/HEURIST/heurist-build/$1

echo
echo NEW DIRECTOREIS? HAVE YOU UPDATED LIST?
echo

# remove any superfluous files - add others as appropriate
# add here as required ...

# Now zip it all up as a tarball for distribution on the Heurist web site

echo
echo creating tarball /var/www/html/HEURIST/heurist-build/$1.tar.bz2
rm -f /var/www/html/HEURIST/heurist-build/$1.tar.bz2
tar -cjf /var/www/html/HEURIST/heurist-build/$1.tar.bz2 -C /var/www/html/HEURIST/heurist-build/ $1/
rm -rf /var/www/html/HEURIST/heurist-build/$1/

# show what we have got in the directory
echo
ls -alt /var/www/html/HEURIST/heurist-build

echo
echo Copying installer and update shellscripts from root of current instance
echo The installer and update scripts are accessed directly to download and install tarballs
cp *.sh /var/www/html/HEURIST/DISTRIBUTION
cp installers/*.sh /var/www/html/HEURIST/DISTRIBUTION

# avoid problems with shellscripts having DoS line endings
# TODO: need to do the same for installer directory shellscripts
# dos2unix no longer exists on our server @ Feb 2019 ??? dos2unix install_heurist.sh
# dos2unix no longer exists on our server @ Feb 2019 ??? dos2unix update_heurist.sh

# PHP extensions verification function
# TODO: Why is this being zipped? What is the purpose? Who added it? Needs an explanation. [Ian 4 Oct 2016]
zip -j /var/www/html/HEURIST/DISTRIBUTION/verifyInstallation.zip admin/verification/verifyInstallation.php

echo
echo creating tarballs in /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5, vendor, and help
 cd /var/www/html/HEURIST/HEURIST_SUPPORT
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ external_h5/
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/vendor.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ vendor/
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ help/

chown -R apache:apache /var/www/html/HEURIST/DISTRIBUTION

sudo cp /var/www/html/HEURIST/heurist-build/$1.tar.bz2  /var/www/html/HEURIST/DISTRIBUTION
echo
echo Distribution $1.tar.bz2 built. DO NOT CHANGE THE NAME of the tar.bz2 file - it extracts to a folder 
echo of this name and the installation is dependant on the filename parameter to find this folder
echo
echo

