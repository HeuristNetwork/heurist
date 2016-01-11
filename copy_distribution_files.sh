#! /bin/sh

# copy_distribution_files.sh: Creates a distribution package in h4-build from a Heurist working directory
# This file is intended for internal use of the development team and should normally be deleted from the install package.

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2016 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov     <osmakov@gmail.com>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     4.0

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

if [ -z $1 ]
   then echo "Usage:   sudo ./copy_distribution_files.sh. h4.x.x.xxxx   - insert appropriate sub-versions and alpha or beta in x.x-xxxx"
   exit
   fi

# copy_distribution_files.sh
# --------------------------
# This file copies all necessary Heurist vsn 4 distribution files and support files from the current directory
# (any Heurist h4-xx program directory) to a temporary directory in the h4-build directory in /var/www/html/HEURIST.
# Make sure the current directory is the up-to-date version you want to package.
# The script is set up for the HeuristScholar server and may require modification for other directory layouts

# RUN THIS FILE FROM AN h4-xx DIRECTORY CONTAINING DESIRED HEURIST CODE

# Creates h4-build directory in /var/www/html/HEURIST


echo -e "\n\n\n\n"
echo -------------------------------------------------------------------------------------------------
echo
echo "Heurist Vsn 4 distribution builder - run from /var/www/html/HEURIST/h4-xx program directory"

# File paths specified with explicit h4-build to ensure it will only remove from this specific directory
# and files in the subdirectory of this directory specified by the parameter

# no effect if already exists
mkdir /var/www/html/HEURIST/h4-build

echo
echo removing existing h4-build/$1
rm -rf /var/www/html/HEURIST/h4-build/$1

echo
echo copying files to /var/www/html/HEURIST/h4-build/$1

mkdir /var/www/html/HEURIST/h4-build/$1

# Copy all the files in the root of h4-xx
cp -r *.* /var/www/html/HEURIST/h4-build/$1

# Remember to add any new directories here

# H4 directories
cp -r hclient /var/www/html/HEURIST/h4-build/$1
cp -r hserver /var/www/html/HEURIST/h4-build/$1

# H3 directories
cp -r admin /var/www/html/HEURIST/h4-build/$1
cp -r applications /var/www/html/HEURIST/h4-build/$1
cp -r common /var/www/html/HEURIST/h4-build/$1
cp -r export /var/www/html/HEURIST/h4-build/$1
cp -r hapi /var/www/html/HEURIST/h4-build/$1
cp -r import /var/www/html/HEURIST/h4-build/$1
cp -r installers  /var/www/html/HEURIST/h4-build/$1
cp -r records  /var/www/html/HEURIST/h4-build/$1
cp -r search  /var/www/html/HEURIST/h4-build/$1
cp -r viewers /var/www/html/HEURIST/h4-build/$1

# Shared directories
cp -r context_help /var/www/html/HEURIST/h4-build/$1
cp -r documentation_and_templates /var/www/html/HEURIST/h4-build/$1

# remove any superfluous files - add others as appropriate
# add here as required ...

# Now zip it all up as a tarball for distribution on the Heurist web site

echo
echo creating tarball /var/www/html/HEURIST/h4-build/$1.tar.bz2
rm -f /var/www/html/HEURIST/h4-build/$1.tar.bz2
tar -cjf /var/www/html/HEURIST/h4-build/$1.tar.bz2 -C /var/www/html/HEURIST/h4-build/ $1/
rm -rf /var/www/html/HEURIST/h4-build/$1/

# show what we have got in the directory
echo
ls -alt /var/www/html/HEURIST/h4-build

echo
echo Copying installer and update shellscripts from root of current instance
echo The installer and update scripts are accessed directly to download and install tarballs
cp *.sh /var/www/html/HEURIST/DISTRIBUTION
cp installers/*.sh /var/www/html/HEURIST/DISTRIBUTION

echo
echo creating tarballs in /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external, external_h4, and help
cd /var/www/html/HEURIST/HEURIST_SUPPORT
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ external/
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h4.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ external_h4/
tar -cjf /var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2 -C /var/www/html/HEURIST/HEURIST_SUPPORT/ help/

chown -R www-data:www-data /var/www/html/HEURIST/DISTRIBUTION

echo
echo
echo ----------------------------------------------------------------------------------------------
echo
echo "Now complete distribution (if code ready) with:"
echo
echo "    sudo cp /var/www/html/HEURIST/h4-build/$1.tar.bz2  /var/www/html/HEURIST/DISTRIBUTION"
echo
echo DO NOT CHANGE THE NAME of the tar.bz2 file - it extracts to a folder of this name
echo and the installation is dependant on the filename parameter to find this folder
echo
echo

